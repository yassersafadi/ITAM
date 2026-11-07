<?php
declare(strict_types=1);

namespace ITAM\Services;

/** Extra per-category asset attributes defined by administrators (e.g. SIM number for phones). */
final class CustomFields
{
    public const TYPES = ['text', 'number', 'date', 'select'];

    /** @return array<int, list<array>> fields grouped by category id */
    public static function byCategory(): array
    {
        $out = [];
        foreach (db()->all('SELECT * FROM custom_fields ORDER BY sort, id') as $f) {
            $f['choices'] = self::choices($f);
            $out[(int) $f['category_id']][] = $f;
        }
        return $out;
    }

    public static function forCategory(?int $categoryId): array
    {
        return $categoryId ? (self::byCategory()[$categoryId] ?? []) : [];
    }

    /** @return array<int, string> field id → value */
    public static function values(int $assetId): array
    {
        return array_column(db()->all('SELECT field_id, value FROM custom_values WHERE asset_id = ?', [$assetId]), 'value', 'field_id');
    }

    /** Values with their labels, for display. */
    public static function display(int $assetId, ?int $categoryId): array
    {
        $values = self::values($assetId);
        $out = [];
        foreach (self::forCategory($categoryId) as $f) {
            $v = $values[$f['id']] ?? '';
            if ($v !== '') {
                $out[] = ['label' => $f['label'], 'value' => $f['type'] === 'date' ? fmt_date($v) : $v];
            }
        }
        return $out;
    }

    /** @return array<int, string> field id → error message */
    public static function validate(?int $categoryId, array $input): array
    {
        $errors = [];
        foreach (self::forCategory($categoryId) as $f) {
            $v = trim((string) ($input[$f['id']] ?? ''));
            if ($v === '') {
                if ($f['required']) {
                    $errors[$f['id']] = t('validation.required', ['field' => $f['label']]);
                }
                continue;
            }
            $ok = match ($f['type']) {
                'number' => is_numeric($v),
                'date'   => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v),
                'select' => in_array($v, $f['choices'], true),
                default  => mb_strlen($v) <= 255,
            };
            if (!$ok) {
                $errors[$f['id']] = t('settings.invalid', ['field' => $f['label']]);
            }
        }
        return $errors;
    }

    /** Store values for the asset's category; returns changed labels for the ledger. */
    public static function save(int $assetId, ?int $categoryId, array $input): array
    {
        $before = self::values($assetId);
        $changed = [];
        foreach (self::forCategory($categoryId) as $f) {
            $v = trim((string) ($input[$f['id']] ?? ''));
            if (($before[$f['id']] ?? '') === $v) {
                continue;
            }
            db()->run('DELETE FROM custom_values WHERE asset_id = ? AND field_id = ?', [$assetId, $f['id']]);
            if ($v !== '') {
                db()->insert('custom_values', ['asset_id' => $assetId, 'field_id' => $f['id'], 'value' => $v]);
            }
            $changed[$f['label']] = $v;
        }
        return $changed;
    }

    private static function choices(array $f): array
    {
        return $f['type'] === 'select' ? array_values(array_filter(array_map('trim', explode("\n", str_replace(',', "\n", (string) $f['options']))))) : [];
    }
}
