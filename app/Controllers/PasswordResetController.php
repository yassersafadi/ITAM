<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Services\Ledger;
use ITAM\Services\Notifier;

/**
 * "Forgot your password?" for staff accounts with a local password. A one-time link is e-mailed, valid 30 minutes;
 * the answer is the same whether or not the address exists, so the form cannot be used to list accounts. Accounts that
 * sign in with Microsoft are left out: a local password would bypass the organisation's own sign-in rules.
 */
final class PasswordResetController extends Controller
{
    private const MINUTES = 30;
    private const PER_IP_HOUR = 5;
    private const PER_USER_HOUR = 3;

    public function showRequest(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        $this->view('auth/forgot', ['email' => Notifier::canEmail()], 'layouts/guest');
    }

    public function sendLink(): void
    {
        $email = strtolower(trim((string) input('email', '')));
        $ip = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
        $hour = date('Y-m-d H:i:s', time() - 3600);
        $byIp = (int) db()->value('SELECT COUNT(*) FROM password_resets WHERE ip = ? AND created_at > ?', [$ip, $hour]);
        if ($byIp < self::PER_IP_HOUR && filter_var($email, FILTER_VALIDATE_EMAIL) && Notifier::canEmail()) {
            $user = db()->one("SELECT id, name, email, locale FROM users WHERE LOWER(email) = ? AND active = 1 AND auth_provider = 'local'", [$email]);
            $recent = $user ? (int) db()->value('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > ?', [$user['id'], $hour]) : 0;
            if ($user && $recent < self::PER_USER_HOUR) {
                $token = bin2hex(random_bytes(32));
                db()->insert('password_resets', ['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'ip' => $ip,
                    'expires_at' => date('Y-m-d H:i:s', time() + self::MINUTES * 60), 'created_at' => now()]);
                $locale = isset(config('i18n.locales')[(string) $user['locale']]) ? (string) $user['locale'] : (string) config('i18n.default', 'en');
                \ITAM\Core\Lang::with($locale, fn () => Notifier::sendTo((string) $user['email'], t('reset.mail_subject', ['app' => app_name()]),
                    t('reset.mail_body', ['name' => $user['name'], 'n' => self::MINUTES]), Notifier::absolute('/reset-password/' . $token)));
                Ledger::record('password_reset_request', 'user', (int) $user['id'], 'Password reset link sent to ' . $user['email']);
            } elseif (!$user) {
                // Counted against the IP even when no account matches, so guessing addresses is limited too.
                db()->insert('password_resets', ['user_id' => null, 'token_hash' => '', 'ip' => $ip, 'expires_at' => now(), 'used_at' => now(), 'created_at' => now()]);
            }
        }
        flash('success', t('reset.sent', ['n' => self::MINUTES]));
        redirect('/forgot-password');
    }

    public function showReset(string $token): void
    {
        $this->view('auth/reset', ['token' => $token, 'valid' => (bool) $this->find($token)], 'layouts/guest');
    }

    public function reset(string $token): void
    {
        $row = $this->find($token) ?? (function () {
            flash('error', t('reset.invalid'));
            redirect('/forgot-password');
        })();
        $pw = (string) input('password', '');
        $min = (int) config('security.password_min', 8);
        if (mb_strlen($pw) < $min || $pw !== (string) input('password_confirmation', '')) {
            flash('error', mb_strlen($pw) < $min ? t('validation.min', ['field' => t('field.password'), 'n' => $min]) : t('reset.mismatch'));
            redirect('/reset-password/' . $token);
        }
        db()->update('users', (int) $row['user_id'], ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)]);
        // Every outstanding link of this account stops working, and the failed sign-ins are forgotten.
        db()->run('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $row['user_id']]);
        Auth::clearFailures((string) $row['email']);
        Ledger::record('password_reset', 'user', (int) $row['user_id'], 'Password changed with an e-mailed reset link (' . $row['email'] . ')');
        flash('success', t('reset.done'));
        redirect('/login');
    }

    /** A link that exists, is unused, unexpired and belongs to an active local account. */
    private function find(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return db()->one("SELECT r.id, r.user_id, u.email FROM password_resets r JOIN users u ON u.id = r.user_id
            WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > ? AND u.active = 1 AND u.auth_provider = 'local'", [hash('sha256', $token), now()]);
    }
}
