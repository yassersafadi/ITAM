<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Outgoing notifications by e-mail (SMTP).
 * Messages are queued in the outbox and delivered after the response is sent, so a slow
 * mail server never delays the page. Every attempt is kept in the outbox for diagnosis.
 */
final class Notifier
{
    private static bool $flushScheduled = false;

    public static function enabled(): bool
    {
        return self::emailReady();
    }

    /** Queue a message for every configured channel. */
    public static function send(string $subject, string $body, ?string $link = null): void
    {
        if (!self::enabled()) {
            return;
        }
        $now = now();
        if (self::emailReady()) {
            foreach (PlatformSettings::emailList((string) config('notify.recipients')) as $to) {
                db()->insert('outbox', ['channel' => 'email', 'recipient' => $to, 'subject' => mb_substr($subject, 0, 255), 'body' => $body, 'link' => $link, 'created_at' => $now]);
            }
        }
        self::flushLater();
    }

    /** Deliver the queue after the response is sent (once per request). */
    private static function flushLater(): void
    {
        if (!self::$flushScheduled) {
            self::$flushScheduled = true;
            register_shutdown_function(static function () {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                try {
                    self::flush();
                } catch (\Throwable $e) {
                    error_log('Notifier: ' . $e->getMessage());     // after the response: never a fatal error
                }
            });
        }
    }

    /** Minutes to wait before each retry of a failed message; after the last one it is marked failed. */
    private const BACKOFF = [5, 30, 120];

    /** Deliver pending messages that are due. Returns [sent, failed attempts]. */
    /** False on a copy that must not reach anyone (config app.outbound): messages are kept in the outbox, never sent. */
    public const HELD = 'Outgoing messages are off on this copy';

    public static function outbound(): bool
    {
        return config('app.outbound', true) !== false;
    }

    public static function flush(int $limit = 50): array
    {
        $sent = $failed = 0;
        if (!self::outbound()) {
            db()->run("UPDATE outbox SET status = 'skipped', error = ? WHERE status = 'pending'", [self::HELD]);
            return [0, 0];
        }
        $rows = db()->all("SELECT * FROM outbox WHERE status = 'pending' AND (next_attempt_at IS NULL OR next_attempt_at <= ?) ORDER BY id LIMIT " . (int) $limit, [now()]);
        foreach ($rows as $m) {
            $attempts = (int) ($m['attempts'] ?? 0) + 1;
            try {
                $done = true;
                self::email($m);
                db()->update('outbox', (int) $m['id'], ['status' => $done ? 'sent' : 'skipped', 'sent_at' => now(), 'error' => null, 'attempts' => $attempts, 'next_attempt_at' => null]);
                $sent += $done ? 1 : 0;
            } catch (\Throwable $e) {
                // Stays pending until the next try (scheduler or the next page that sends something), like webhooks.
                $wait = self::BACKOFF[$attempts - 1] ?? null;
                db()->update('outbox', (int) $m['id'], ['error' => mb_substr($e->getMessage(), 0, 255), 'attempts' => $attempts]
                    + ($wait === null ? ['status' => 'failed', 'next_attempt_at' => null] : ['next_attempt_at' => date('Y-m-d H:i:s', time() + $wait * 60)]));
                $failed++;
            }
        }
        return [$sent, $failed];
    }

    /** Absolute link for messages leaving the platform. */
    public static function absolute(?string $path): ?string
    {
        if ($path === null || $path === '' || preg_match('#^https?://#', $path)) {
            return $path ?: null;
        }
        $base = rtrim((string) config('app.url'), '/');
        if ($base === '' && isset($_SERVER['HTTP_HOST'])) {
            $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        }
        return $base === '' ? null : $base . '/' . ltrim($path, '/');
    }

    /** Whether personal e-mails (e.g. portal sign-in links) can be sent at all. */
    public static function canEmail(): bool
    {
        return (string) config('mail.host') !== '' && (string) config('mail.from') !== '';
    }

    /** Queue an e-mail to one person (not the IT distribution list). */
    public static function sendTo(string $to, string $subject, string $body, ?string $link = null, ?string $attachment = null): bool
    {
        if (!self::canEmail() || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        db()->insert('outbox', ['channel' => 'email', 'recipient' => $to, 'subject' => mb_substr($subject, 0, 255), 'body' => $body, 'link' => $link, 'attachment' => $attachment, 'created_at' => now()]);
        self::flushLater();
        return true;
    }

    private static function emailReady(): bool
    {
        return config('notify.email') && self::canEmail()
            && PlatformSettings::emailList((string) config('notify.recipients'));
    }

    /** Minimal SMTP client: SSL or STARTTLS, AUTH LOGIN, UTF-8 multipart message. */
    private static function email(array $m): void
    {
        $host = (string) config('mail.host');
        $port = (int) config('mail.port', 587);
        $enc = (string) config('mail.encryption', 'tls');
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException("SMTP connect: $errstr ($errno)");
        }
        stream_set_timeout($fp, 15);
        $expect = static function (array $codes) use ($fp): string {
            $reply = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $reply .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            if (!in_array((int) substr($reply, 0, 3), $codes, true)) {
                throw new \RuntimeException('SMTP: ' . trim($reply ?: 'no reply'));
            }
            return $reply;
        };
        $cmd = static function (string $c, array $codes) use ($fp, $expect): string {
            fwrite($fp, $c . "\r\n");
            return $expect($codes);
        };
        $expect([220]);
        $me = gethostname() ?: 'itam.local';
        $cmd("EHLO $me", [250]);
        if ($enc === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new \RuntimeException('SMTP: TLS negotiation failed');
            }
            $cmd("EHLO $me", [250]);
        }
        if ((string) config('mail.username') !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode((string) config('mail.username')), [334]);
            $cmd(base64_encode((string) config('mail.password')), [235]);
        }
        $from = (string) config('mail.from');
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<{$m['recipient']}>", [250, 251]);
        $cmd('DATA', [354]);

        $boundary = 'itam' . bin2hex(random_bytes(8));
        $text = $m['body'] . ($m['link'] ? "\n\n" . $m['link'] : '');
        $html = self::html($m);
        $headers = [
            'From: =?UTF-8?B?' . base64_encode(app_name('en')) . "?= <$from>",
            "To: <{$m['recipient']}>",
            'Subject: =?UTF-8?B?' . base64_encode((string) $m['subject']) . '?=',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'itam.local') . '>',
            'MIME-Version: 1.0',
        ];
        $alternative = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
            . "--$boundary--\r\n";
        $file = (string) ($m['attachment'] ?? '');
        if ($file !== '' && is_file($file)) {
            // Text and HTML alternatives inside a mixed message that carries the file.
            $mixed = 'itamx' . bin2hex(random_bytes(8));
            $name = basename($file);
            $headers[] = "Content-Type: multipart/mixed; boundary=\"$mixed\"";
            $body = "--$mixed\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n" . $alternative
                . "--$mixed\r\nContent-Type: " . (str_ends_with($name, '.pdf') ? 'application/pdf' : (str_ends_with($name, '.ics') ? 'text/calendar; method=' . (str_starts_with($name, 'cancel-') ? 'CANCEL' : 'REQUEST') : 'application/octet-stream')) . "; name=\"$name\"\r\n"
                . "Content-Disposition: attachment; filename=\"$name\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode((string) file_get_contents($file)))
                . "--$mixed--\r\n";
        } else {
            $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
            $body = $alternative;
        }
        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body . '.';
        $cmd($data, [250]);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
    }
    /**
     * HTML body of an e-mail. Branded (Settings › Identity): a header bar in the theme colour with the logo and a
     * button for the link; otherwise the plain layout. Inline styles only, as mail clients ignore style sheets.
     */
    public static function html(array $m): string
    {
        $primary = Themes::primary();
        $ink = Themes::ink($primary);
        $footer = '<p style="color:#7a8aa3;font-size:12px;margin:18px 0 0">' . e(app_name('en')) . ' · ' . e(vendor_name('en')) . '</p>';
        if (!config('mail.branded', true)) {
            return '<div dir="' . \ITAM\Core\Lang::dir() . '" style="font-family:Segoe UI,Tahoma,sans-serif;font-size:14px;color:#0f1b33">'
                . '<h2 style="margin:0 0 12px;color:' . e($primary) . '">' . e($m['subject']) . '</h2>'
                . '<p style="white-space:pre-line">' . e((string) $m['body']) . '</p>'
                . ($m['link'] ? '<p><a href="' . e($m['link']) . '">' . e($m['link']) . '</a></p>' : '') . $footer . '</div>';
        }
        $logo = self::absolute(brand_asset('logo_full'));
        return '<div dir="' . \ITAM\Core\Lang::dir() . '" style="background:#f4f7fb;padding:24px 12px;font-family:Segoe UI,Tahoma,sans-serif">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e3e9f2">'
            . '<tr><td style="background:' . e($primary) . ';padding:14px 20px;color:' . $ink . ';font-size:15px;font-weight:600">'
            . ($logo ? '<img src="' . e($logo) . '" alt="" height="28" style="height:28px;vertical-align:middle;background:#ffffff;border-radius:4px;padding:3px 6px;margin-inline-end:10px">' : '')
            . e(app_name()) . '</td></tr>'
            . '<tr><td style="padding:20px;font-size:14px;color:#0f1b33">'
            . '<h2 style="margin:0 0 12px;font-size:18px;color:#0f1b33">' . e($m['subject']) . '</h2>'
            . '<p style="white-space:pre-line;margin:0 0 16px;color:#4a5a75">' . e((string) $m['body']) . '</p>'
            . ($m['link'] ? '<a href="' . e($m['link']) . '" style="display:inline-block;background:' . e($primary) . ';color:' . $ink . ';padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600">' . e(t('mail.open')) . '</a>' : '')
            . $footer . '</td></tr></table></div>';
    }
}
