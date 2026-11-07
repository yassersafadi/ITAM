<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Auth;

/** Discussion threads on assets, work orders and employees (staff notes), with @mentions that notify the people named. */
final class Comments
{
    public const ENTITIES = ['asset', 'maintenance', 'employee'];

    public static function thread(string $entity, int $id): array
    {
        return db()->all('SELECT c.*, u.name AS author FROM comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.entity = ? AND c.entity_id = ? AND c.portal = 0 ORDER BY c.id', [$entity, $id]);
    }

    /** Active users, longest names first so "@Sara Al-Otaibi" wins over "@Sara". */
    public static function people(): array
    {
        $rows = db()->all('SELECT id, name, email FROM users WHERE active = 1 ORDER BY name');
        usort($rows, fn ($a, $b) => mb_strlen($b['name']) <=> mb_strlen($a['name']));
        return $rows;
    }

    /** @return array<int, array> users mentioned in $body (by full name after "@") */
    public static function mentions(string $body): array
    {
        $found = [];
        $text = mb_strtolower($body);
        foreach (self::people() as $u) {
            $needle = '@' . mb_strtolower($u['name']);
            if (str_contains($text, $needle)) {
                $found[(int) $u['id']] = $u;
                $text = str_replace($needle, '', $text);
            }
        }
        return $found;
    }

    /** Escaped body with mentions highlighted. */
    public static function render(string $body): string
    {
        $html = nl2br(e($body));
        foreach (self::people() as $u) {
            $html = preg_replace('/@' . preg_quote(e($u['name']), '/') . '(?![\p{L}\p{N}])/iu', '<span class="mention">@' . e($u['name']) . '</span>', $html) ?? $html;
        }
        return $html;
    }

    public static function add(string $entity, int $id, string $body): int
    {
        $cid = db()->insert('comments', ['entity' => $entity, 'entity_id' => $id, 'user_id' => Auth::id(), 'body' => $body, 'created_at' => now()]);
        [$label, $link] = self::target($entity, $id);
        $me = Auth::user();
        foreach (self::mentions($body) as $uid => $u) {
            if ($uid === (int) ($me['id'] ?? 0)) {
                continue;
            }
            $title = t('comments.mentioned', ['who' => $me['name'] ?? '', 'where' => $label]);
            db()->insert('notifications', ['user_id' => $uid, 'type' => 'mention', 'title' => $title, 'body' => mb_substr($body, 0, 240),
                'link' => $link . '#comments', 'created_at' => now()]);
            if ($u['email']) {
                Notifier::sendTo($u['email'], $title, $body, Notifier::absolute($link . '#comments'));
            }
        }
        Ledger::record('comment', $entity, $id, mb_substr("Comment on $label: $body", 0, 255));
        return $cid;
    }

    /** Human label and page of the thing being discussed. */
    public static function target(string $entity, int $id): array
    {
        if ($entity === 'maintenance') {
            $m = db()->one('SELECT m.id, m.title, a.tag FROM maintenance m JOIN assets a ON a.id = m.asset_id WHERE m.id = ?', [$id]);
            return ["WO-$id " . ($m['title'] ?? ''), url('work-orders/' . $id)];
        }
        if ($entity === 'employee') {
            return [(string) db()->value('SELECT name FROM employees WHERE id = ?', [$id]), url('employees/' . $id)];
        }
        $a = db()->one('SELECT tag, name FROM assets WHERE id = ?', [$id]);
        return [($a['tag'] ?? '') . ' ' . ($a['name'] ?? ''), url('assets/' . $id)];
    }

    /** Location of the asset behind a thread, for branch-scoped access. */
    public static function locationOf(string $entity, int $id): mixed
    {
        return match ($entity) {
            'maintenance' => db()->value('SELECT a.location_id FROM maintenance m JOIN assets a ON a.id = m.asset_id WHERE m.id = ?', [$id]),
            'employee' => db()->value('SELECT location_id FROM employees WHERE id = ?', [$id]),
            default => db()->value('SELECT location_id FROM assets WHERE id = ?', [$id]),
        };
    }
}
