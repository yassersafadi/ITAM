<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Core\Lang;
use ITAM\Services\Ledger;
use ITAM\Services\PlatformSettings;
use ITAM\Services\Totp;

final class AuthController extends Controller
{
    /** Minutes allowed between the password step and the code step. */
    private const SECOND_FACTOR_WINDOW = 5;

    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        $this->view('auth/login', [], 'layouts/guest');
    }

    public function login(): void
    {
        $email = (string) input('email', '');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (Auth::throttled($email, $ip)) {
            $_SESSION['_old'] = ['email' => $email];
            flash('error', t('auth.throttled', ['n' => (int) config('security.lockout_minutes', 5)]));
            redirect('/login');
        }
        $user = Auth::verifyCredentials($email, (string) input('password', ''));
        if (!$user) {
            Auth::recordFailure($email, $ip);
            $_SESSION['_old'] = ['email' => $email];
            flash('error', t('auth.failed'));
            redirect('/login');
        }
        if ((int) $user['totp_enabled']) {
            // Failed sign-ins are forgotten only once the code is right too, so wrong codes add up across attempts.
            $_SESSION['2fa_pending'] = ['id' => (int) $user['id'], 'at' => time(), 'tries' => 0,
                'default_pw' => input('password') === \ITAM\Services\Seeder::DEFAULT_ADMIN_PASSWORD];
            redirect('/login/2fa');
        }
        Auth::clearFailures($email);
        $this->complete($user, input('password') === \ITAM\Services\Seeder::DEFAULT_ADMIN_PASSWORD);
    }

    public function showSecondFactor(): void
    {
        if (!$this->pending()) {
            redirect('/login');
        }
        $this->view('auth/two_factor', [], 'layouts/guest');
    }

    public function secondFactor(): void
    {
        $pending = $this->pending() ?? redirect('/login');
        $user = db()->one('SELECT * FROM users WHERE id = ? AND active = 1', [$pending['id']]);
        if (!$user) {
            unset($_SESSION['2fa_pending']);
            redirect('/login');
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        // Wrong codes count like wrong passwords, per account and address, so signing in again does not reset the guesses.
        if (Auth::throttled((string) $user['email'], $ip)) {
            unset($_SESSION['2fa_pending']);
            flash('error', t('auth.throttled', ['n' => (int) config('security.lockout_minutes', 5)]));
            redirect('/login');
        }
        $code = (string) input('code', '');
        $ok = Totp::verify(PlatformSettings::unseal((string) $user['totp_secret']), $code);
        if (!$ok && ($left = Totp::useRecovery($user['recovery_codes'], $code)) !== null) {
            db()->update('users', (int) $user['id'], ['recovery_codes' => $left]);
            Ledger::record('2fa_recovery', 'user', (int) $user['id'], 'Signed in with a recovery code');
            flash('warning', t('twofa.recovery_used', ['n' => count(json_decode($left, true))]));
            $ok = true;
        }
        if (!$ok) {
            Auth::recordFailure((string) $user['email'], $ip);
            $_SESSION['2fa_pending']['tries']++;
            if ($_SESSION['2fa_pending']['tries'] >= 5) {
                unset($_SESSION['2fa_pending']);
                flash('error', t('twofa.too_many'));
                redirect('/login');
            }
            flash('error', t('twofa.invalid'));
            redirect('/login/2fa');
        }
        unset($_SESSION['2fa_pending']);
        Auth::clearFailures((string) $user['email']);
        $this->complete($user, (bool) $pending['default_pw']);
    }

    // ---------- Single sign-on ----------
    public function ssoStart(): void
    {
        if (!\ITAM\Services\Oidc::enabled()) {
            redirect('/login');
        }
        try {
            redirect(\ITAM\Services\Oidc::authorizeUrl());
        } catch (\Throwable $e) {
            error_log('SSO start: ' . $e->getMessage());
            flash('error', t('sso.failed', ['reason' => $e->getMessage()]));
            redirect('/login');
        }
    }

    public function ssoCallback(): void
    {
        try {
            $id = \ITAM\Services\Oidc::callback($_GET);
        } catch (\Throwable $e) {
            error_log('SSO callback: ' . $e->getMessage());
            flash('error', t('sso.failed', ['reason' => $e->getMessage()]));
            redirect('/login');
        }
        if (($_SESSION['sso_target'] ?? '') === 'portal') {
            unset($_SESSION['sso_target']);
            $emp = db()->one('SELECT id FROM employees WHERE LOWER(email) = ? AND active = 1', [$id['email']]);
            if (!$emp) {
                flash('error', t('portal.no_employee', ['email' => $id['email']]));
                redirect('/portal/login');
            }
            \ITAM\Controllers\PortalController::signIn((int) $emp['id'], 'sso');
            redirect('/portal');
        }
        $user = db()->one('SELECT * FROM users WHERE external_id = ?', [$id['sub']])
            ?? db()->one('SELECT * FROM users WHERE LOWER(email) = ?', [$id['email']]);
        if ($user && !(int) $user['active']) {
            flash('error', t('sso.inactive'));
            redirect('/login');
        }
        if (!$user) {
            if (!config('sso.auto_provision')) {
                flash('error', t('sso.no_account', ['email' => $id['email']]));
                redirect('/login');
            }
            $role = in_array(config('sso.default_role'), Auth::roles(), true) ? config('sso.default_role') : Auth::defaultRole();
            $uid = db()->insert('users', [
                'name' => mb_substr($id['name'], 0, 120), 'email' => $id['email'],
                // No usable local password: this account signs in through the identity provider only.
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'role' => $role, 'locale' => Lang::locale(), 'theme' => 'system', 'active' => 1,
                'auth_provider' => 'sso', 'external_id' => $id['sub'], 'created_at' => now(),
            ]);
            Ledger::record('create', 'user', $uid, "User provisioned by single sign-on: {$id['email']} ($role)");
            $user = db()->one('SELECT * FROM users WHERE id = ?', [$uid]);
        } elseif ($user['external_id'] !== $id['sub']) {
            db()->update('users', (int) $user['id'], ['external_id' => $id['sub']]);
        }
        Auth::login($user);
        Ledger::record('login', 'user', (int) $user['id'], 'Signed in (single sign-on)');
        $intended = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);
        redirect($intended && !str_contains($intended, '/api/') ? $intended : '/');
    }

    public function logout(): void
    {
        Ledger::record('logout', 'user', Auth::id(), 'Signed out');
        Auth::logout();
        redirect('/login');
    }

    public function locale(string $code): void
    {
        Lang::set($code);
        if (Auth::check()) {
            db()->update('users', Auth::id(), ['locale' => $code]);
        }
        back();
    }

    // ---------- Two-factor enrolment (profile) ----------
    public function setup2fa(): void
    {
        $_SESSION['2fa_setup'] = Totp::secret();
        redirect('/profile#twofa');
    }

    public function enable2fa(): void
    {
        $secret = $_SESSION['2fa_setup'] ?? null;
        if (!$secret || !Totp::verify($secret, (string) input('code', ''))) {
            flash('error', t('twofa.invalid'));
            redirect('/profile#twofa');
        }
        [$codes, $hashes] = Totp::recoveryCodes();
        db()->update('users', Auth::id(), ['totp_secret' => PlatformSettings::seal($secret), 'totp_enabled' => 1, 'recovery_codes' => $hashes]);
        unset($_SESSION['2fa_setup']);
        $_SESSION['2fa_codes'] = $codes;
        Ledger::record('2fa_enable', 'user', Auth::id(), 'Two-factor authentication enabled');
        flash('success', t('twofa.enabled'));
        redirect('/profile#twofa');
    }

    public function disable2fa(): void
    {
        $user = Auth::user();
        if (!password_verify((string) input('current_password'), $user['password_hash'])) {
            flash('error', t('profile.wrong_password'));
            redirect('/profile#twofa');
        }
        db()->update('users', (int) $user['id'], ['totp_secret' => null, 'totp_enabled' => 0, 'recovery_codes' => null]);
        Ledger::record('2fa_disable', 'user', (int) $user['id'], 'Two-factor authentication disabled');
        flash('warning', t('twofa.disabled'));
        redirect('/profile#twofa');
    }

    // ------------------------------------------------------------------

    private function pending(): ?array
    {
        $p = $_SESSION['2fa_pending'] ?? null;
        if (!$p || time() - $p['at'] > self::SECOND_FACTOR_WINDOW * 60) {
            unset($_SESSION['2fa_pending']);
            return null;
        }
        return $p;
    }

    private function complete(array $user, bool $defaultPassword): never
    {
        Auth::login($user);
        Ledger::record('login', 'user', Auth::id(), 'Signed in' . ((int) $user['totp_enabled'] ? ' (2FA)' : ''));
        if ($defaultPassword) {
            flash('warning', t('auth.default_password'));
        }
        $intended = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);
        redirect($intended && !str_contains($intended, '/api/') ? $intended : '/');
    }
}
