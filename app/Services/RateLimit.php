<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Counts sensitive actions per bucket (usually an action and a user) over a sliding window, in the database so a new
 * browser session does not reset it. Routes ask for it with the 'rate' option (Router); services call hit() directly.
 */
final class RateLimit
{
    /** Record one attempt. False when the bucket already had $max attempts in the last $seconds (the attempt is refused). */
    public static function hit(string $bucket, int $max, int $seconds): bool
    {
        $bucket = mb_substr($bucket, 0, 120);
        try {
            if (self::count($bucket, $seconds) >= $max) {
                return false;
            }
            db()->insert('rate_hits', ['bucket' => $bucket, 'created_at' => now()]);
            // Old rows are useless once outside the longest window anyone uses (one day).
            if (random_int(1, 50) === 1) {
                db()->run('DELETE FROM rate_hits WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
            }
        } catch (\PDOException) {
            // Table not migrated yet: never block.
        }
        return true;
    }

    public static function count(string $bucket, int $seconds): int
    {
        try {
            return (int) db()->value('SELECT COUNT(*) FROM rate_hits WHERE bucket = ? AND created_at > ?', [mb_substr($bucket, 0, 120), date('Y-m-d H:i:s', time() - $seconds)]);
        } catch (\PDOException) {
            return 0;
        }
    }

    public static function clear(string $bucket): void
    {
        try {
            db()->run('DELETE FROM rate_hits WHERE bucket = ?', [mb_substr($bucket, 0, 120)]);
        } catch (\PDOException) {
        }
    }
}
