<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Auth;

/**
 * Accessories, consumables and spare parts tracked by quantity rather than asset tag (mice, chargers, cables, toner,
 * memory…): stock received, items issued to employees (optionally fulfilling a portal request) or used on work orders,
 * and a low-stock alert raised once when an item drops to its minimum. The quantity is the total of every warehouse
 * (with a weighted average cost).
 */
final class Consumables
{
    public const KINDS = ['accessory', 'cable', 'power', 'peripheral', 'toner', 'storage', 'spare_part', 'other'];
    public const UNITS = ['pcs', 'box', 'pack', 'm', 'roll', 'set'];

    /**
     * Change stock by $change (negative to issue). Returns false when there is not enough. $opt: warehouse_id and
     * bin_id (where the units go or come from; the default warehouse otherwise), unit_cost (the price of units received,
     * which moves the weighted average), maintenance_id and asset_id (spare parts used on a work order), move_id (the
     * stock document).
     */
    public static function move(int $id, int $change, string $reason, ?int $employeeId = null, ?int $requestId = null, ?string $note = null, array $opt = []): bool
    {
        $fulfilled = false;
        $ok = db()->transaction(function () use ($id, $change, $reason, $employeeId, $requestId, $note, $opt, &$fulfilled) {
            $c = db()->one('SELECT * FROM consumables WHERE id = ?', [$id]);
            if (!$c) {
                return false;
            }
            $balance = $reason === 'count' ? max(0, $change) : (int) $c['quantity'] + $change;
            if ($balance < 0) {
                return false;
            }
            $delta = $reason === 'count' ? $balance - (int) $c['quantity'] : $change;
            $warehouse = null;
            $avg = $c['avg_cost'] !== null ? (float) $c['avg_cost'] : ($c['unit_cost'] !== null ? (float) $c['unit_cost'] : null);
            $cost = $avg;
            if ($delta > 0 && isset($opt['unit_cost'])) {
                $cost = (float) $opt['unit_cost'];
                $have = max(0, (int) $c['quantity']);
                $avg = $avg === null || $have === 0 ? $cost : round(($have * $avg + $delta * $cost) / ($have + $delta), 4);
            }
            db()->insert('consumable_moves', ['consumable_id' => $id, 'change_qty' => $delta, 'balance' => $balance, 'reason' => $reason,
                'employee_id' => $employeeId, 'request_id' => $requestId, 'note' => $note !== null ? mb_substr($note, 0, 255) : null,
                'maintenance_id' => $opt['maintenance_id'] ?? null, 'asset_id' => $opt['asset_id'] ?? null, 'unit_cost' => $cost, 'warehouse_id' => $warehouse,
                'move_id' => $opt['move_id'] ?? null, 'user_id' => Auth::id(), 'created_at' => now()]);
            $upd = ['quantity' => $balance, 'avg_cost' => $avg, 'updated_at' => now()];
            if ($balance > (int) $c['min_quantity']) {
                $upd['alerted_at'] = null;            // back above minimum: alert again next time
            }
            db()->update('consumables', $id, $upd);
            if ($reason === 'issue' && $employeeId) {
                $emp = (string) db()->value('SELECT name FROM employees WHERE id = ?', [$employeeId]);
                Ledger::record('consumable_issue', 'employee', $employeeId, -$delta . " × {$c['name']} issued to $emp" . ($requestId ? " (REQ-$requestId)" : ''));
            }
            if ($balance <= (int) $c['min_quantity'] && !$c['alerted_at']) {
                self::alert(array_merge($c, ['quantity' => $balance]));
            }
            return true;
        });
        return $ok;
    }

    /** One notification, e-mail and webhook per drop below minimum. */
    private static function alert(array $c): void
    {
        db()->update('consumables', (int) $c['id'], ['alerted_at' => now()]);
        $title = t('consumables.notify_low', ['name' => $c['name'], 'n' => $c['quantity']]);
        db()->insert('notifications', ['type' => 'stock', 'title' => $title, 'body' => t('consumables.min_is', ['n' => $c['min_quantity']]),
            'link' => url('consumables/' . $c['id']), 'fingerprint' => 'stock:' . $c['id'] . ':' . now(), 'created_at' => now()]);
        Notifier::send($title, t('consumables.min_is', ['n' => $c['min_quantity']]), Notifier::absolute('/consumables/' . $c['id']));
        Ledger::record('stock_low', 'consumable', (int) $c['id'], "{$c['name']} at {$c['quantity']} (minimum {$c['min_quantity']})");
    }

    public static function low(): array
    {
        return db()->all('SELECT * FROM consumables WHERE quantity <= min_quantity AND active = 1 ORDER BY quantity - min_quantity, name');
    }

    /** Items issued over the last $days days, most used first (for reordering). */
    public static function usage(int $days = 90): array
    {
        return array_column(db()->all("SELECT consumable_id, -SUM(change_qty) AS n FROM consumable_moves WHERE reason = 'issue' AND created_at >= ? GROUP BY consumable_id",
            [days_from_today(-$days)]), 'n', 'consumable_id');
    }
}
