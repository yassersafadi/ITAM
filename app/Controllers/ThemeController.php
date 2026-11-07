<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Services\Ledger;
use ITAM\Services\Themes;

/** Themes engine actions on the identity page: apply, build, delete, export and import themes. */
final class ThemeController extends Controller
{
    private const BACK = '/admin/settings?tab=identity';

    public function activate(): void
    {
        $key = (string) input('key');
        if (!Themes::activate($key)) {
            abort(404);
        }
        Ledger::record('update', 'settings', null, 'Theme applied: ' . self::label($key));
        flash('success', t('theme.applied', ['name' => self::label($key)]));
        redirect(self::BACK);
    }

    /** Create or update a custom theme from the builder; "apply" also makes it the platform theme. */
    public function save(): void
    {
        $theme = Themes::sanitize($_POST, $error);
        if ($theme === null) {
            flash('error', t('theme.invalid', ['field' => $error]));
            redirect(self::BACK);
        }
        $id = Themes::saveCustom(input_or_null('id'), $theme);
        $applied = input('apply') || Themes::activeKey() === 'custom:' . $id;
        if ($applied) {
            Themes::activate('custom:' . $id);
        }
        Ledger::record('update', 'settings', null, 'Theme saved: ' . $theme['name'] . ($applied ? ' (applied)' : ''));
        flash('success', t($applied ? 'theme.saved_applied' : 'theme.saved', ['name' => $theme['name']]));
        redirect(self::BACK);
    }

    public function destroy(string $id): void
    {
        $theme = Themes::custom()[$id] ?? abort(404);
        Themes::deleteCustom($id);
        Ledger::record('delete', 'settings', null, 'Theme deleted: ' . $theme['name']);
        flash('success', t('common.deleted'));
        redirect(self::BACK);
    }

    public function export(): void
    {
        $key = (string) ($_GET['key'] ?? '');
        $theme = Themes::resolve($key) ?? abort(404);
        $name = self::label($key);
        $file = 'theme-' . (trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'custom') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        echo Themes::export($theme, $name);
        exit;
    }

    public function import(): void
    {
        $file = $_FILES['theme'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 64 * 1024) {
            flash('error', t('theme.import_failed'));
            redirect(self::BACK);
        }
        $theme = Themes::import((string) file_get_contents($file['tmp_name']), $error);
        if ($theme === null) {
            flash('error', t('theme.import_failed'));
            redirect(self::BACK);
        }
        Themes::saveCustom(null, $theme);
        Ledger::record('create', 'settings', null, 'Theme imported: ' . $theme['name']);
        flash('success', t('theme.imported', ['name' => $theme['name']]));
        redirect(self::BACK);
    }

    /** Display name of a preset (translated) or a custom theme. */
    public static function label(string $key): string
    {
        [$kind, $id] = array_pad(explode(':', $key, 2), 2, '');
        return $kind === 'preset' ? t('theme.preset.' . $id) : (string) (Themes::custom()[$id]['name'] ?? $id);
    }
}
