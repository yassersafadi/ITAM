<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Reads a raw e-mail (RFC 5322 / MIME) into what the service desk needs: sender, subject, thread ids, the text, and the
 * attached files. Handles multipart nesting, base64 and quoted-printable, any charset, encoded headers (=?UTF-8?B?...?=)
 * and HTML-only messages. Works the same for IMAP and for Microsoft Graph (which hands back the raw message too).
 */
final class MimeMessage
{
    public array $headers = [];
    public string $fromEmail = '';
    public string $fromName = '';
    /** @var list<array{0: string, 1: string}> people copied (Cc), as [name, address] */
    public array $cc = [];
    public string $subject = '';
    public string $messageId = '';
    /** @var list<string> message ids this one answers (In-Reply-To and References) */
    public array $thread = [];
    public string $text = '';
    public string $html = '';
    /** @var list<array{name: string, mime: string, content: string}> */
    public array $attachments = [];

    public static function parse(string $raw): self
    {
        $m = new self();
        [$head, $body] = self::split($raw);
        $m->headers = self::headers($head);
        [$m->fromName, $m->fromEmail] = self::address($m->header('from'));
        $m->cc = self::addresses($m->header('cc'));
        $m->subject = trim(self::decodeHeader($m->header('subject')));
        $m->messageId = trim($m->header('message-id'), " <>\t");
        preg_match_all('/<([^>]+)>/', $m->header('in-reply-to') . ' ' . $m->header('references'), $ids);
        $m->thread = array_values(array_unique($ids[1]));
        $m->walk($m->headers, $body);
        if (trim($m->text) === '' && $m->html !== '') {
            $m->text = self::htmlToText($m->html);
        }
        $m->text = trim(str_replace("\r\n", "\n", $m->text));
        return $m;
    }

    public function header(string $name): string
    {
        return (string) ($this->headers[strtolower($name)] ?? '');
    }

    /**
     * Sent by a machine: auto-replies (out of office), bounces, mailing lists and bulk mail. Such mail never opens or
     * reopens a ticket, which also stops two auto-responders answering each other forever.
     */
    public function automatic(): bool
    {
        $auto = strtolower($this->header('auto-submitted'));
        return ($auto !== '' && $auto !== 'no')
            || in_array(strtolower($this->header('precedence')), ['bulk', 'junk', 'list', 'auto_reply'], true)
            || $this->header('x-autoreply') !== '' || $this->header('x-autorespond') !== ''
            || $this->header('list-id') !== ''
            || preg_match('/^(mailer-daemon|postmaster|no-?reply)@/i', $this->fromEmail)
            || str_contains(strtolower($this->header('content-type')), 'report-type=delivery-status');
    }

    /** The new part of a reply: the text above the quoted earlier message ("On ... wrote:", "From:", "-----Original"). */
    public function reply(): string
    {
        $lines = explode("\n", $this->text);
        $out = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if (preg_match('/^(-{2,}\s*(Original Message|الرسالة الأصلية|Message d\'origine)|_{5,})/iu', $t)
                || preg_match('/^(On|Le|في)\s.+(wrote|a écrit|كتب)\s*:?$/iu', $t)
                || preg_match('/^(From|De|من)\s*:\s.+@/iu', $t)) {
                break;
            }
            if (str_starts_with($t, '>')) {
                continue;
            }
            $out[] = rtrim($line);
        }
        $reply = trim(implode("\n", $out));
        return $reply !== '' ? $reply : $this->text;
    }

    /** @return array{0: string, 1: string} headers and body, split at the first empty line */
    private static function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $pos = strpos($raw, "\n\n");
        return $pos === false ? [$raw, ''] : [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    /** Header lines (folded lines joined), keys in lower case; the first occurrence wins. */
    private static function headers(string $head): array
    {
        $out = [];
        $head = preg_replace("/\n[ \t]+/", ' ', $head);
        foreach (explode("\n", (string) $head) as $line) {
            $p = strpos($line, ':');
            if ($p > 0) {
                $k = strtolower(trim(substr($line, 0, $p)));
                $out[$k] ??= trim(substr($line, $p + 1));
            }
        }
        return $out;
    }

    /** "Name <a@b>" (possibly encoded) → [name, address in lower case]. */
    private static function address(string $value): array
    {
        $value = self::decodeHeader($value);
        if (preg_match('/^(.*)<([^>]+)>/', $value, $m)) {
            return [trim($m[1], " \"'"), strtolower(trim($m[2]))];
        }
        return ['', strtolower(trim($value))];
    }

    /** A list of addresses ("Name" <a@b.c>, d@e.f): each as [name, address], invalid ones left out. */
    private static function addresses(string $value): array
    {
        $out = [];
        // Commas inside a quoted name do not separate addresses.
        foreach (preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', self::decodeHeader($value)) ?: [] as $part) {
            [$name, $email] = self::address($part);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[$email] = [$name, $email];
            }
        }
        return array_values($out);
    }

    /** Encoded words (=?charset?B|Q?...?=) to UTF-8. */
    public static function decodeHeader(string $value): string
    {
        // Spaces between two encoded words are not part of the text.
        $value = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $value);
        return (string) preg_replace_callback('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', function ($m) {
            $bytes = strtoupper($m[2]) === 'B' ? (string) base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));
            return self::utf8($bytes, $m[1]);
        }, $value);
    }

    /** Parameters of a header such as Content-Type: text/plain; charset="utf-8"; name*=UTF-8''r%C3%A9sum%C3%A9.pdf */
    private static function params(string $value): array
    {
        $out = ['' => strtolower(trim(strtok($value, ';') ?: ''))];
        preg_match_all('/;\s*([a-z0-9*_-]+)\s*=\s*("([^"]*)"|[^;]+)/i', $value, $m, PREG_SET_ORDER);
        foreach ($m as $p) {
            $key = strtolower($p[1]);
            $val = isset($p[3]) && $p[3] !== '' ? $p[3] : trim($p[2], " \"");
            if (str_ends_with($key, '*')) {
                // RFC 2231: charset'language'percent-encoded
                $parts = explode("'", $val, 3);
                $val = count($parts) === 3 ? self::utf8(rawurldecode($parts[2]), $parts[0] ?: 'UTF-8') : rawurldecode($val);
                $key = rtrim($key, '*');
            }
            $out[$key] = self::decodeHeader($val);
        }
        return $out;
    }

    private function walk(array $headers, string $body, int $depth = 0): void
    {
        $type = self::params($headers['content-type'] ?? 'text/plain');
        $disposition = self::params($headers['content-disposition'] ?? '');
        if (str_starts_with($type[''], 'multipart/') && !empty($type['boundary']) && $depth < 8) {
            $parts = explode('--' . $type['boundary'], $body);
            array_shift($parts);                              // the preamble
            foreach ($parts as $part) {
                if (str_starts_with($part, '--')) {
                    break;                                    // the closing boundary
                }
                [$h, $b] = self::split(ltrim($part, "\n"));
                $this->walk(self::headers($h), $b, $depth + 1);
            }
            return;
        }
        $content = match (strtolower(trim($headers['content-transfer-encoding'] ?? ''))) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body)),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        $name = $disposition['filename'] ?? $type['name'] ?? '';
        if ($type[''] === 'message/rfc822' && $name === '') {
            $name = 'message.eml';
        }
        $isFile = $name !== '' || $disposition[''] === 'attachment' || !str_starts_with($type[''], 'text/');
        if ($isFile) {
            if ($content !== '') {
                $this->attachments[] = ['name' => $name !== '' ? $name : 'file', 'mime' => $type[''], 'content' => $content];
            }
            return;
        }
        $content = self::utf8($content, $type['charset'] ?? 'UTF-8');
        if ($type[''] === 'text/html') {
            $this->html .= $content;
        } elseif ($this->text === '') {
            $this->text = $content;
        }
    }

    private static function utf8(string $bytes, string $charset): string
    {
        $charset = strtoupper(trim($charset)) ?: 'UTF-8';
        if (in_array($charset, ['UTF-8', 'UTF8', 'US-ASCII', 'ASCII'], true)) {
            return mb_check_encoding($bytes, 'UTF-8') ? $bytes : mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
        }
        $aliases = ['CP1256' => 'Windows-1256', 'WINDOWS-1256' => 'Windows-1256', 'CP1252' => 'Windows-1252', 'ISO-8859-6' => 'ISO-8859-6'];
        $from = $aliases[$charset] ?? $charset;
        $out = @iconv($from, 'UTF-8//IGNORE', $bytes);
        if ($out === false) {
            $out = @mb_convert_encoding($bytes, 'UTF-8', $from) ?: $bytes;
        }
        return $out;
    }

    /** Readable text from an HTML body: line breaks for blocks, no styles or scripts. */
    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", (string) $html);
        $html = preg_replace('#</(p|div|li|tr|h[1-6]|blockquote)>#i', "\n", (string) $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        return trim((string) preg_replace("/\n\s*\n\s*\n+/", "\n\n", (string) $text));
    }
}
