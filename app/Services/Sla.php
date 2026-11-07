<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Response targets for corrective work: due time per priority, live state, escalation and compliance.
 */
final class Sla
{
    private const ESCALATE = ['low' => 'medium', 'medium' => 'high', 'high' => 'critical', 'critical' => 'critical'];

    public static function appliesTo(string $type): bool
    {
        return $type === 'corrective';
    }

    public static function dueAt(string $priority, ?string $from = null): string
    {
        $hours = (int) config('sla.' . $priority, 24);
        return date('Y-m-d H:i:s', strtotime($from ?? now()) + $hours * 3600);
    }

    /** @return array{state: string, seconds: int}|null  ok · warning · breached · met · missed */
    public static function state(array $m): ?array
    {
        if (empty($m['due_at']) || $m['status'] === 'cancelled') {
            return null;
        }
        $due = strtotime($m['due_at']);
        if ($m['status'] === 'done') {
            $done = strtotime((string) $m['completed_at']) ?: time();
            return ['state' => $done <= $due ? 'met' : 'missed', 'seconds' => $due - $done];
        }
        $left = $due - time();
        $total = max(1, $due - (strtotime((string) $m['created_at']) ?: time()));
        return ['state' => $left < 0 ? 'breached' : ($left < $total * 0.25 ? 'warning' : 'ok'), 'seconds' => $left];
    }

    public static function duration(int $seconds): string
    {
        $s = abs($seconds);
        $d = intdiv($s, 86400);
        $h = intdiv($s % 86400, 3600);
        $m = intdiv($s % 3600, 60);
        return $d ? t('sla.dh', ['d' => $d, 'h' => $h]) : ($h ? t('sla.hm', ['h' => $h, 'm' => $m]) : t('sla.m', ['m' => $m]));
    }

    public static function label(array $s): string
    {
        return match ($s['state']) {
            'met'      => t('sla.met'),
            'missed'   => t('sla.missed_by', ['t' => self::duration($s['seconds'])]),
            'breached' => t('sla.late', ['t' => self::duration($s['seconds'])]),
            default    => t('sla.left', ['t' => self::duration($s['seconds'])]),
        };
    }

    /** Raise priority on breached work orders once, notify the team and record it. Returns how many were escalated. */
    public static function escalate(): int
    {
        $late = db()->all(
            "SELECT m.*, a.tag FROM maintenance m JOIN assets a ON a.id = m.asset_id
             WHERE m.status IN ('open','in_progress') AND m.due_at IS NOT NULL AND m.due_at < ? AND m.escalated_at IS NULL",
            [now()]
        );
        foreach ($late as $m) {
            $to = self::ESCALATE[$m['priority']] ?? 'high';
            db()->update('maintenance', (int) $m['id'], ['escalated_at' => now(), 'priority' => $to]);
            $title = t('sla.escalated_title', ['tag' => $m['tag']]);
            $body = t('sla.escalated_body', ['title' => $m['title'], 'p' => t('priority.' . $to)]);
            db()->insert('notifications', [
                'user_id' => null, 'type' => 'sla_breach', 'title' => $title, 'body' => $body,
                'link' => url('maintenance', ['status' => 'active']), 'fingerprint' => 'sla:' . $m['id'], 'created_at' => now(),
            ]);
            Ledger::record('escalate', 'maintenance', (int) $m['id'], "SLA breached on {$m['tag']}: “{$m['title']}” → {$to}");
            Notifier::send($title, $body, Notifier::absolute('/maintenance'));
        }
        return count($late);
    }

    /** Share of corrective work finished within target over the last $days (branch-limited users: their locations). */
    public static function compliance(int $days = 30): ?float
    {
        $rows = db()->all("SELECT m.due_at, m.completed_at FROM maintenance m JOIN assets a ON a.id = m.asset_id
             WHERE m.status = 'done' AND m.due_at IS NOT NULL AND m.completed_at >= ? AND " . scope_sql('a'), [days_from_today(-$days)]);
        if (!$rows) {
            return null;
        }
        $met = count(array_filter($rows, fn ($r) => $r['completed_at'] <= $r['due_at']));
        return $met / count($rows) * 100;
    }

    /** Per-technician load and performance, counting only work on assets in the viewer's locations. */
    public static function workload(): array
    {
        $since = days_from_today(-30);
        $rows = db()->all(
            "SELECT u.id, u.name, u.role,
                    SUM(CASE WHEN m.status IN ('open','in_progress') THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN m.status IN ('open','in_progress') AND m.due_at IS NOT NULL AND m.due_at < ? THEN 1 ELSE 0 END) AS breached,
                    SUM(CASE WHEN m.status IN ('open','in_progress') AND m.priority IN ('high','critical') THEN 1 ELSE 0 END) AS urgent,
                    SUM(CASE WHEN m.status = 'done' AND m.completed_at >= ? THEN 1 ELSE 0 END) AS done_30,
                    SUM(CASE WHEN m.status = 'done' AND m.completed_at >= ? AND m.due_at IS NOT NULL AND m.completed_at <= m.due_at THEN 1 ELSE 0 END) AS met_30,
                    SUM(CASE WHEN m.status = 'done' AND m.completed_at >= ? AND m.due_at IS NOT NULL THEN 1 ELSE 0 END) AS sla_30
             FROM users u LEFT JOIN maintenance m ON m.technician_id = u.id AND m.asset_id IN (SELECT a.id FROM assets a WHERE " . scope_sql('a') . ")
             WHERE u.active = 1 AND u.role IN ('admin','manager','technician')
             GROUP BY u.id, u.name, u.role ORDER BY open_count DESC, u.name",
            [now(), $since, $since, $since]
        );
        foreach ($rows as &$r) {
            $hours = array_map(
                fn ($x) => (strtotime($x['completed_at']) - strtotime($x['created_at'])) / 3600,
                db()->all("SELECT m.created_at, m.completed_at FROM maintenance m JOIN assets a ON a.id = m.asset_id
                    WHERE m.technician_id = ? AND m.status = 'done' AND m.type = 'corrective' AND m.completed_at >= ? AND " . scope_sql('a'), [$r['id'], $since])
            );
            sort($hours);
            $r['median_hours'] = $hours ? $hours[intdiv(count($hours), 2)] : null;
            $r['compliance'] = (int) $r['sla_30'] ? (int) $r['met_30'] / (int) $r['sla_30'] * 100 : null;
        }
        unset($r);
        return $rows;
    }
}
