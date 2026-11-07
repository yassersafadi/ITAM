<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Auth;

/**
 * Tamper-evident audit ledger. Every entry stores the SHA-256 of its own content chained to the previous
 * entry's hash, so editing or deleting any historical row breaks verification from that point on.
 */
final class Ledger
{
    private const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /** Name entries are recorded under while an automation rule acts (null = the signed-in user). */
    private static ?string $actor = null;

    /** Record the following entries as the system on behalf of a rule ("Automation: …"); null ends it. */
    public static function actingAs(?string $name): void
    {
        self::$actor = $name === null ? null : mb_substr($name, 0, 120);
    }

    public static function record(string $action, string $entity, ?int $entityId, string $summary, array $payload = []): void
    {
        $db = db();
        $user = self::$actor === null ? Auth::user() : ['id' => null, 'name' => self::$actor];
        $prev = $db->value('SELECT hash FROM ledger ORDER BY id DESC LIMIT 1') ?? self::GENESIS;
        $row = [
            'created_at' => now(),
            'user_id'    => $user['id'] ?? null,
            'user_name'  => $user['name'] ?? 'system',
            'action'     => $action,
            'entity'     => $entity,
            'entity_id'  => $entityId,
            'summary'    => mb_substr($summary, 0, 255),
            'payload'    => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'prev_hash'  => $prev,
        ];
        $row['hash'] = self::hash($row);
        $db->insert('ledger', $row);
    }

    public static function hash(array $row): string
    {
        return hash('sha256', implode('|', [
            $row['prev_hash'], $row['created_at'], (string) $row['user_id'], $row['user_name'],
            $row['action'], $row['entity'], (string) $row['entity_id'], $row['summary'], (string) $row['payload'],
        ]));
    }

    /** @return array{valid: bool, checked: int, broken_at: ?int} */
    public static function verify(): array
    {
        $prev = self::GENESIS;
        $checked = 0;
        $st = db()->run('SELECT * FROM ledger ORDER BY id ASC');
        while ($row = $st->fetch()) {
            $checked++;
            if ($row['prev_hash'] !== $prev || self::hash($row) !== $row['hash']) {
                return ['valid' => false, 'checked' => $checked, 'broken_at' => (int) $row['id']];
            }
            $prev = $row['hash'];
        }
        return ['valid' => true, 'checked' => $checked, 'broken_at' => null];
    }

    /** Field-level diff for update entries. */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $k => $v) {
            if (in_array($k, ['updated_at', 'health_score', 'failure_risk', 'predicted_replacement', 'health_factors'], true)) {
                continue;
            }
            if (array_key_exists($k, $before) && (string) $before[$k] !== (string) $v) {
                $changes[$k] = [$before[$k], $v];
            }
        }
        return $changes;
    }
}
