<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Auth;

/**
 * Re-authentication before sensitive finance actions (changing or revealing bank details, closing periods, payments):
 * the form carries a "stepup_code" field — the authenticator code (or a recovery code) for users with two-factor
 * authentication, otherwise the password — and a success stays valid for a few minutes (Settings › Finance). When
 * Settings › Finance requires it, users without two-factor authentication cannot take these actions at all.
 * Five wrong codes in five minutes lock the step-up for that user; every attempt is in the audit ledger.
 */
final class StepUp
{
    private const MAX_FAILURES = 5;
    private const WINDOW = 300;

    public static function minutes(): int
    {
        return max(1, min(60, (int) config('finance.stepup_minutes', 5)));
    }

    public static function requires2fa(): bool
    {
        return (bool) config('finance.require_2fa', true);
    }

    /** The signed-in user verified themselves within the last few minutes. */
    public static function fresh(): bool
    {
        return Auth::id() !== null && (int) ($_SESSION['stepup_user'] ?? 0) === Auth::id()
            && time() - (int) ($_SESSION['stepup_at'] ?? 0) <= self::minutes() * 60;
    }

    /** 'totp' when the user has two-factor authentication, otherwise 'password'. */
    public static function method(): string
    {
        return (int) (Auth::user()['totp_enabled'] ?? 0) ? 'totp' : 'password';
    }

    /** Why the user cannot step up at all (two-factor authentication required but not set up), or null. */
    public static function blocked(): ?string
    {
        return self::requires2fa() && self::method() !== 'totp' ? t('stepup.need_2fa') : null;
    }

    /**
     * Fresh, or verify the code typed with the action. Returns null when the action may go on, else the error to show.
     * $what names the action in the audit ledger.
     */
    public static function check(?string $code, string $what): ?string
    {
        if ($blocked = self::blocked()) {
            return $blocked;
        }
        if (self::fresh()) {
            return null;
        }
        $code = trim((string) $code);
        if ($code === '') {
            return t('stepup.required');
        }
        $bucket = 'stepup:' . Auth::id();
        if (RateLimit::count($bucket, self::WINDOW) >= self::MAX_FAILURES) {
            Ledger::record('step_up_locked', 'user', Auth::id(), "Step-up locked after too many wrong codes ($what)");
            return t('stepup.locked', ['n' => (int) ceil(self::WINDOW / 60)]);
        }
        if (!self::verify($code)) {
            RateLimit::hit($bucket, PHP_INT_MAX, self::WINDOW);
            Ledger::record('step_up_failed', 'user', Auth::id(), "Wrong step-up code ($what)");
            return t('stepup.wrong');
        }
        RateLimit::clear($bucket);
        $_SESSION['stepup_at'] = time();
        $_SESSION['stepup_user'] = Auth::id();
        Ledger::record('step_up', 'user', Auth::id(), "Re-authenticated (" . self::method() . ") for $what");
        return null;
    }

    private static function verify(string $code): bool
    {
        $u = db()->one('SELECT * FROM users WHERE id = ? AND active = 1', [Auth::id()]);
        if (!$u) {
            return false;
        }
        if ((int) $u['totp_enabled']) {
            if (preg_match('/^\d{6}$/', str_replace(' ', '', $code))) {
                return Totp::verify(PlatformSettings::unseal((string) $u['totp_secret']), str_replace(' ', '', $code));
            }
            $left = Totp::useRecovery($u['recovery_codes'] ?? null, $code);
            if ($left !== null) {
                db()->update('users', (int) $u['id'], ['recovery_codes' => $left]);
                return true;
            }
            return false;
        }
        return password_verify($code, (string) $u['password_hash']);
    }

    public static function forget(): void
    {
        unset($_SESSION['stepup_at'], $_SESSION['stepup_user']);
    }
}
