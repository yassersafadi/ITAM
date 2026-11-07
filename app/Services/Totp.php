<?php
declare(strict_types=1);

namespace ITAM\Services;

/** RFC 6238 time-based one-time passwords (Microsoft/Google Authenticator compatible) and recovery codes. */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function uri(string $secret, string $account): string
    {
        $issuer = app_name('en');
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
    }

    public static function code(string $secret, ?int $slice = null): string
    {
        $slice ??= intdiv(time(), 30);
        $key = self::decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $slice), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Accept the current code and one step either side for clock drift. */
    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen((string) $code) !== 6) {
            return false;
        }
        $now = intdiv(time(), 30);
        foreach ([-1, 0, 1] as $d) {
            if (hash_equals(self::code($secret, $now + $d), $code)) {
                return true;
            }
        }
        return false;
    }

    /** @return array{0: list<string>, 1: string} [plain codes to show once, JSON of hashes to store] */
    public static function recoveryCodes(int $n = 8): array
    {
        $plain = [];
        for ($i = 0; $i < $n; $i++) {
            $plain[] = strtoupper(bin2hex(random_bytes(3)) . '-' . bin2hex(random_bytes(3)));
        }
        return [$plain, json_encode(array_map(fn ($c) => password_hash($c, PASSWORD_DEFAULT), $plain))];
    }

    /** Consume a recovery code. Returns the updated JSON, or null when the code is not valid. */
    public static function useRecovery(?string $stored, string $code): ?string
    {
        $hashes = json_decode((string) $stored, true) ?: [];
        $code = strtoupper(trim($code));
        foreach ($hashes as $i => $h) {
            if (password_verify($code, $h)) {
                unset($hashes[$i]);
                return json_encode(array_values($hashes));
            }
        }
        return null;
    }

    private static function decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($secret, '='))) as $ch) {
            $pos = strpos(self::ALPHABET, $ch);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
