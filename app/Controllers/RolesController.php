<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Services\Ledger;

/**
 * Roles and what they may do: rename any role (in both languages), change the permissions of every role but the
 * administrator (who always has them all), add roles, copy one, delete an unused one, tie a role to an Entra group,
 * and order them (the order is the priority when someone is in several Entra role groups).
 */
final class RolesController extends Controller
{
    private const LOCALES = ['ar', 'en'];

    public function __construct()
    {
        // Only administrators: a role holding Settings could otherwise widen its own permissions.
        if (!Auth::isAdmin()) {
            abort(403, t('users.admin_only'));
        }
    }

    public function index(): void
    {
        $rows = Auth::roleRows();
        $slug = isset($rows[$_GET['role'] ?? '']) ? (string) $_GET['role'] : (string) array_key_first($rows);
        $counts = array_column(db()->all('SELECT role, COUNT(*) AS n FROM users GROUP BY role'), 'n', 'role');
        $this->view('admin/roles', ['rows' => $rows, 'slug' => $slug, 'role' => $rows[$slug], 'counts' => $counts]);
    }

    public function store(): void
    {
        $names = $this->names();
        if (!array_filter($names)) {
            flash('error', t('roles.name_needed'));
            redirect('/admin/roles');
        }
        $from = Auth::roleRows()[(string) input('copy_from')] ?? null;
        $slug = 'r' . bin2hex(random_bytes(4));
        db()->insert('roles', $names + [
            'slug' => $slug, 'permissions' => json_encode(self::clean($from ? Auth::permissionsOf($from['slug']) : [])),
            'sort' => (int) db()->value('SELECT COALESCE(MAX(sort), 0) + 1 FROM roles'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Auth::forgetRoles();
        Ledger::record('create', 'role', null, 'Role created: ' . Auth::roleName($slug) . ($from ? ' (copy of ' . Auth::roleName($from['slug']) . ')' : ''));
        flash('success', t('roles.created'));
        redirect('/admin/roles?role=' . $slug);
    }

    public function update(string $slug): void
    {
        $role = Auth::roleRows()[$slug] ?? abort(404);
        $data = $this->names() + ['updated_at' => now()];
        // The administrator keeps every permission: only the name and the Entra group change.
        if ($slug !== 'admin') {
            $data['permissions'] = json_encode(self::clean((array) ($_POST['permissions'] ?? [])));
        }
        $before = Auth::permissionsOf($slug);
        db()->run('UPDATE roles SET ' . implode(', ', array_map(fn ($k) => "$k = ?", array_keys($data))) . ' WHERE slug = ?', [...array_values($data), $slug]);
        Auth::forgetRoles();
        $after = Auth::permissionsOf($slug);
        Ledger::record('update', 'role', null, 'Role updated: ' . Auth::roleName($slug), [
            'added' => array_values(array_diff($after, $before)), 'removed' => array_values(array_diff($before, $after)),
        ]);
        flash('success', t('common.saved'));
        redirect('/admin/roles?role=' . $slug);
    }

    public function destroy(string $slug): void
    {
        Auth::roleRows()[$slug] ?? abort(404);
        $users = (int) db()->value('SELECT COUNT(*) FROM users WHERE role = ?', [$slug]);
        if ($slug === 'admin' || $users > 0) {
            flash('error', $slug === 'admin' ? t('roles.admin_locked') : t('roles.in_use', ['n' => $users]));
            redirect('/admin/roles?role=' . $slug);
        }
        if (config('sso.default_role') === $slug) {
            flash('error', t('roles.is_default'));
            redirect('/admin/roles?role=' . $slug);
        }
        $name = Auth::roleName($slug);
        db()->run('DELETE FROM roles WHERE slug = ?', [$slug]);
        Auth::forgetRoles();
        Ledger::record('delete', 'role', null, "Role deleted: $name");
        flash('success', t('common.deleted'));
        redirect('/admin/roles');
    }

    /** Move a role up or down the list (its priority for Entra role groups). The administrator stays first. */
    public function move(string $slug): void
    {
        $order = array_values(array_filter(Auth::roles(), fn ($s) => $s !== 'admin'));
        $i = array_search($slug, $order, true);
        $j = $i === false ? false : $i + (input('dir') === 'up' ? -1 : 1);
        if ($i !== false && isset($order[$j])) {
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
            foreach ($order as $n => $s) {
                db()->run('UPDATE roles SET sort = ? WHERE slug = ?', [$n + 1, $s]);
            }
            db()->run("UPDATE roles SET sort = 0 WHERE slug = 'admin'");
            Auth::forgetRoles();
        }
        redirect('/admin/roles?role=' . $slug);
    }

    /** name_ar and name_en from the form (empty = the built-in translation, for the four built-in roles). */
    private function names(): array
    {
        $out = [];
        foreach (self::LOCALES as $l) {
            $out['name_' . $l] = mb_substr(trim((string) input('name_' . $l)), 0, 80) ?: null;
        }
        return $out;
    }

    /** Only known permissions are stored. */
    private static function clean(array $perms): array
    {
        $known = array_merge(...array_values(Auth::PERMISSION_GROUPS));
        return array_values(array_intersect($known, array_map('strval', $perms)));
    }
}
