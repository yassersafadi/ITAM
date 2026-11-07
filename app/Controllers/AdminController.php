<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Core\Lang;
use ITAM\Services\Backup;
use ITAM\Services\Ledger;
use ITAM\Services\PlatformSettings;

final class AdminController extends Controller
{
    // ---------- Users ----------
    public function users(): void
    {
        $users = db()->all('SELECT * FROM users ORDER BY name');
        $this->view('admin/users', ['users' => $users, 'locations' => db()->all('SELECT id, name FROM locations ORDER BY name')]);
    }

    /** Locations ticked for a user, as stored (comma list), or null for every location. */
    private static function scopeInput(): ?string
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['location_scope'] ?? []))));
        $valid = $ids ? array_map('intval', array_column(db()->all('SELECT id FROM locations WHERE id IN (' . implode(',', $ids) . ')'), 'id')) : [];
        return $valid ? implode(',', $valid) : null;
    }

    public function storeUser(): void
    {
        $this->validate(['name' => 'required|max:120', 'email' => 'required|email', 'password' => 'required|min:' . config('security.password_min', 8)]);
        if (db()->value('SELECT id FROM users WHERE LOWER(email) = LOWER(?)', [input('email')])) {
            flash('error', t('users.email_taken'));
            back();
        }
        if (!Auth::mayManage(null, (string) input('role'))) {
            abort(403, t('users.admin_only'));
        }
        $id = db()->insert('users', [
            'name' => input('name'), 'email' => strtolower((string) input('email')),
            'password_hash' => password_hash((string) input('password'), PASSWORD_DEFAULT),
            'role' => in_array(input('role'), Auth::roles(), true) ? input('role') : Auth::defaultRole(),
            'locale' => array_key_exists((string) input('locale'), config('i18n.locales')) ? input('locale') : 'en',
            'theme' => 'system', 'active' => 1, 'created_at' => now(), 'location_scope' => self::scopeInput(),
        ]);
        Ledger::record('create', 'user', $id, 'Created user ' . input('email') . ' (' . input('role') . ')');
        flash('success', t('common.saved'));
        redirect('/admin/users');
    }

    public function updateUser(string $id): void
    {
        $user = db()->one('SELECT * FROM users WHERE id = ?', [(int) $id]) ?? abort(404);
        if (!Auth::mayManage($user, (string) input('role'))) {
            abort(403, t('users.admin_only'));
        }
        $data = [
            'role' => in_array(input('role'), Auth::roles(), true) ? input('role') : $user['role'],
            'active' => input('active') ? 1 : 0,
            'location_scope' => self::scopeInput(),
        ];
        if ((int) $id === Auth::id()) {
            // Never lock yourself out.
            $data = ['role' => $user['role'], 'active' => 1, 'location_scope' => $user['location_scope']];
        }
        if ($pw = input_or_null('password')) {
            if (mb_strlen($pw) < (int) config('security.password_min', 8)) {
                flash('error', t('validation.min', ['field' => t('field.password'), 'n' => config('security.password_min', 8)]));
                back();
            }
            $data['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        }
        db()->update('users', (int) $id, $data);
        Ledger::record('update', 'user', (int) $id, "Updated user {$user['email']}", ['role' => $data['role'], 'active' => $data['active'], 'locations' => $data['location_scope'] ?? null, 'password_reset' => isset($data['password_hash'])]);
        flash('success', t('common.saved'));
        redirect('/admin/users');
    }

    /** Turn off another user's two-factor sign-in (lost phone and recovery codes). Your own is managed from your profile. */
    public function resetTwoFactor(string $id): void
    {
        $user = db()->one('SELECT * FROM users WHERE id = ?', [(int) $id]) ?? abort(404);
        if (!Auth::mayManage($user)) {
            abort(403, t('users.admin_only'));
        }
        if ((int) $id === Auth::id()) {
            flash('error', t('users.2fa_reset_self'));
            redirect('/admin/users');
        }
        if ((int) $user['totp_enabled']) {
            db()->update('users', (int) $id, ['totp_secret' => null, 'totp_enabled' => 0, 'recovery_codes' => null]);
            Ledger::record('2fa_reset', 'user', (int) $id, "Two-factor sign-in reset for {$user['email']} by an administrator");
        }
        flash('success', t('users.2fa_reset_done', ['name' => $user['name']]));
        redirect('/admin/users');
    }

    // ---------- Settings & API tokens ----------
    public function settings(): void
    {
        // No tab: the settings home (cards); otherwise one section.
        $tab = in_array($_GET['tab'] ?? '', PlatformSettings::TABS, true) ? $_GET['tab'] : null;
        $tokens = $tab === 'integrations' ? db()->all('SELECT * FROM api_tokens ORDER BY id DESC') : [];
        $backups = $tab === 'backup' ? Backup::all() : [];
        $outbox = $tab === 'notifications' ? db()->all('SELECT * FROM outbox ORDER BY id DESC LIMIT 25') : [];
        $newToken = $_SESSION['_new_token'] ?? null;
        unset($_SESSION['_new_token']);
        $this->view('admin/settings', compact('tab', 'tokens', 'newToken', 'backups', 'outbox'));
    }

    public function saveSettings(): void
    {
        $tab = $this->settingsTab();
        [$errors, $changed] = PlatformSettings::save($tab, $_POST);
        if ($tab === 'identity') {
            foreach (PlatformSettings::LOGOS as $logo) {
                if (input('remove_' . $logo)) {
                    PlatformSettings::removeLogo($logo);
                    $changed['brand.' . $logo] = 'default';
                } elseif ($err = PlatformSettings::storeLogo($logo, $_FILES[$logo] ?? [])) {
                    $errors['brand.' . $logo] = t('settings.f.brand.' . $logo) . ': ' . $err;
                } elseif (($_FILES[$logo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $changed['brand.' . $logo] = 'uploaded';
                }
            }
        }
        if ($errors) {
            $_SESSION['_old'] = array_map(fn ($v) => is_string($v) ? $v : '', $_POST);
            foreach ($errors as $msg) {
                flash('error', $msg);
            }
            redirect('/admin/settings?tab=' . $tab);
        }
        if ($changed) {
            Ledger::record('update', 'settings', null, 'Settings updated: ' . $tab, $changed);
        }
        flash('success', t('common.saved'));
        redirect('/admin/settings?tab=' . $tab);
    }

    public function resetSettings(): void
    {
        $tab = $this->settingsTab();
        PlatformSettings::reset($tab);
        Ledger::record('update', 'settings', null, 'Settings restored to defaults: ' . $tab);
        flash('success', t('settings.reset_done'));
        redirect('/admin/settings?tab=' . $tab);
    }

    private function settingsTab(): string
    {
        $tab = (string) input('tab');
        if (!in_array($tab, PlatformSettings::TABS, true)) {
            abort(400);
        }
        return $tab;
    }

    public function testNotification(): void
    {
        if (!\ITAM\Services\Notifier::enabled() && !\ITAM\Services\Notifier::canEmail()) {
            flash('error', t('notify.not_configured'));
            redirect('/admin/settings?tab=notifications');
        }
        \ITAM\Services\Notifier::send(t('notify.test_subject'), t('notify.test_body', ['user' => auth()['name']]), \ITAM\Services\Notifier::absolute('/'));
        // The test also goes to whoever pressed it, so the result shows even without a distribution list.
        \ITAM\Services\Notifier::sendTo((string) (auth()['email'] ?? ''), t('notify.test_subject'), t('notify.test_body', ['user' => auth()['name']]), \ITAM\Services\Notifier::absolute('/'));
        [$sent, $failed] = \ITAM\Services\Notifier::flush();
        flash($failed ? 'error' : 'success', $failed ? t('notify.test_failed', ['n' => $failed]) : t('notify.test_sent', ['n' => $sent]));
        redirect('/admin/settings?tab=notifications');
    }

    public function createBackup(): void
    {
        if (!Backup::supported()) {
            flash('error', t('backup.unsupported'));
            redirect('/admin/settings?tab=backup');
        }
        try {
            $name = Backup::create();
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/settings?tab=backup');
        }
        Ledger::record('backup', 'system', null, "Backup created: $name");
        flash('success', t('backup.created', ['name' => $name]));
        redirect('/admin/settings?tab=backup');
    }

    public function downloadBackup(string $name): void
    {
        $path = Backup::path($name) ?? abort(404, t('errors.not_found'));
        Ledger::record('backup_download', 'system', null, "Backup downloaded: $name");
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        readfile($path);
        exit;
    }

    public function deleteBackup(string $name): void
    {
        if (Backup::delete($name)) {
            Ledger::record('backup_delete', 'system', null, "Backup deleted: $name");
            flash('success', t('common.deleted'));
        }
        redirect('/admin/settings?tab=backup');
    }

    public function createToken(): void
    {
        $this->validate(['name' => 'required|max:120']);
        $token = 'itam_' . bin2hex(random_bytes(24));
        $scopes = array_values(array_intersect(ApiController::SCOPES, (array) ($_POST['scopes'] ?? []))) ?: ['read'];
        $id = db()->insert('api_tokens', ['name' => input('name'), 'token_hash' => hash('sha256', $token), 'scopes' => implode(',', $scopes), 'created_at' => now()]);
        Ledger::record('create', 'api_token', $id, 'API token created: ' . input('name') . ' (' . implode(', ', $scopes) . ')');
        $_SESSION['_new_token'] = $token;
        redirect('/admin/settings?tab=integrations');
    }

    public function revokeToken(string $id): void
    {
        db()->delete('api_tokens', (int) $id);
        Ledger::record('delete', 'api_token', (int) $id, 'API token revoked');
        flash('success', t('common.deleted'));
        redirect('/admin/settings?tab=integrations');
    }

    // ---------- Ledger ----------
    public function ledger(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $w = '1=1';
        $p = [];
        if (!empty($_GET['entity'])) {
            $w .= ' AND entity = ?';
            $p[] = $_GET['entity'];
        }
        if (!empty($_GET['q'])) {
            $w .= ' AND (summary LIKE ? OR user_name LIKE ?)';
            array_push($p, '%' . $_GET['q'] . '%', '%' . $_GET['q'] . '%');
        }
        $total = (int) db()->value("SELECT COUNT(*) FROM ledger WHERE $w", $p);
        $rows = db()->all("SELECT * FROM ledger WHERE $w ORDER BY id DESC LIMIT 50 OFFSET " . (($page - 1) * 50), $p);
        $entities = array_column(db()->all('SELECT DISTINCT entity FROM ledger ORDER BY entity'), 'entity');
        $this->view('admin/ledger', ['rows' => $rows, 'page' => $page, 'pages' => (int) max(1, ceil($total / 50)), 'entities' => $entities, 'total' => $total]);
    }

    // ---------- Profile ----------
    public function profile(): void
    {
        $this->view('admin/profile', ['user' => Auth::user()]);
    }

    public function saveProfile(): void
    {
        $user = Auth::user();
        $this->validate(['name' => 'required|max:120']);
        $data = [
            'name' => input('name'),
            'locale' => array_key_exists((string) input('locale'), config('i18n.locales')) ? input('locale') : $user['locale'],
            'theme' => in_array(input('theme'), ['system', 'light', 'dark'], true) ? input('theme') : 'system',
        ];
        if ($new = input_or_null('new_password')) {
            if (!password_verify((string) input('current_password'), $user['password_hash'])) {
                flash('error', t('profile.wrong_password'));
                back();
            }
            if (mb_strlen($new) < (int) config('security.password_min', 8)) {
                flash('error', t('validation.min', ['field' => t('field.password'), 'n' => config('security.password_min', 8)]));
                back();
            }
            $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
            Ledger::record('password', 'user', (int) $user['id'], 'Password changed');
        }
        db()->update('users', (int) $user['id'], $data);
        Lang::set($data['locale']);
        flash('success', t('common.saved'));
        redirect('/profile');
    }

    // ---------- Notifications ----------
    public function notifications(): void
    {
        $rows = db()->all('SELECT * FROM notifications WHERE user_id IS NULL OR user_id = ? ORDER BY id DESC LIMIT 100', [Auth::id()]);
        $this->view('admin/notifications', ['rows' => $rows]);
    }

    public function readNotifications(): void
    {
        // Broadcast notifications are shared, so "read" is tracked per user as a high-water mark.
        db()->setSetting('notif_seen_' . Auth::id(), (string) (int) db()->value('SELECT MAX(id) FROM notifications'));
        back();
    }
}
