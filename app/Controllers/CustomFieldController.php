<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Services\CustomFields;
use ITAM\Services\Ledger;

final class CustomFieldController extends Controller
{
    public function store(string $category): void
    {
        $cat = db()->one('SELECT id, name FROM categories WHERE id = ?', [(int) $category]) ?? abort(404);
        $this->validate(['label' => 'required|max:80', 'options' => 'max:1000']);
        $type = in_array(input('type'), CustomFields::TYPES, true) ? input('type') : 'text';
        if ($type === 'select' && trim((string) input('options')) === '') {
            flash('error', t('cf.need_options'));
            back();
        }
        $id = db()->insert('custom_fields', [
            'category_id' => $cat['id'], 'label' => trim((string) input('label')), 'type' => $type,
            'options' => $type === 'select' ? input('options') : null, 'required' => input('required') ? 1 : 0,
            'sort' => (int) db()->value('SELECT COALESCE(MAX(sort), 0) + 1 FROM custom_fields WHERE category_id = ?', [$cat['id']]),
        ]);
        Ledger::record('create', 'custom_field', $id, "Custom field “" . input('label') . "” added to {$cat['name']}");
        flash('success', t('common.saved'));
        redirect('/directory/categories/' . $cat['id'] . '/edit#fields');
    }

    public function destroy(string $id): void
    {
        $f = db()->one('SELECT * FROM custom_fields WHERE id = ?', [(int) $id]) ?? abort(404);
        db()->delete('custom_fields', (int) $id);
        Ledger::record('delete', 'custom_field', (int) $id, "Custom field “{$f['label']}” removed");
        flash('success', t('common.deleted'));
        redirect('/directory/categories/' . $f['category_id'] . '/edit#fields');
    }
}
