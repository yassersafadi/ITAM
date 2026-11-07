<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Document numbers whose layout the administrator chooses in Settings › Procurement, such as "PR-{YYYY}-{####}".
 * Tokens: {YYYY} year, {YY} two-digit year, {MM} month, and one {###…} counter whose width is the number of #.
 * The counter continues from the highest number already issued with the same text around it, so it starts again at 1
 * when the year in the layout changes, and cancelled numbers are never reused.
 */
final class Numbering
{
    /** Document => [table, column, setting, default layout]. */
    public const DOCS = [];

    /** Letters, digits and - _ . around the tokens, with exactly one counter of 3 to 8 digits. */
    public const PATTERN = '/^(?=(?:[^#]*\{#{3,8}\}[^#]*)$)(?:[A-Za-z0-9._-]|\{YYYY\}|\{YY\}|\{MM\}|\{#{3,8}\})+$/';

    public static function valid(string $format): bool
    {
        return mb_strlen($format) <= 40 && (bool) preg_match(self::PATTERN, $format);
    }

    public static function format(string $doc): string
    {
        $f = trim((string) config(self::DOCS[$doc][2], ''));
        return self::valid($f) ? $f : self::DOCS[$doc][3];
    }

    /** Text before and after the counter with the dates filled in, and the counter width. */
    private static function parts(string $format, string $date): array
    {
        $time = strtotime($date) ?: time();
        $fill = fn (string $s) => strtr($s, ['{YYYY}' => date('Y', $time), '{YY}' => date('y', $time), '{MM}' => date('m', $time)]);
        preg_match('/\{(#+)\}/', $format, $m, PREG_OFFSET_CAPTURE);
        return [$fill(substr($format, 0, $m[0][1])), $fill(substr($format, $m[0][1] + strlen($m[0][0]))), strlen($m[1][0])];
    }

    /** The next number for a document, dated $date (today when null). */
    public static function next(string $doc, ?string $date = null): string
    {
        [$table, $column] = self::DOCS[$doc];
        [$before, $after, $width] = self::parts(self::format($doc), $date ?? today());
        $esc = fn (string $s) => strtr($s, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $last = 0;
        foreach (db()->all("SELECT $column AS n FROM $table WHERE $column LIKE ? ESCAPE '!'", [$esc($before) . '%' . $esc($after)]) as $r) {
            $mid = substr((string) $r['n'], strlen($before), strlen((string) $r['n']) - strlen($before) - strlen($after));
            if ($mid !== '' && ctype_digit($mid)) {
                $last = max($last, (int) $mid);
            }
        }
        return $before . str_pad((string) ($last + 1), $width, '0', STR_PAD_LEFT) . $after;
    }

    /** How a layout looks today with the counter at 1, for the settings page. */
    public static function example(string $format): string
    {
        if (!self::valid($format)) {
            return '';
        }
        [$before, $after, $width] = self::parts($format, today());
        return $before . str_pad('1', $width, '0', STR_PAD_LEFT) . $after;
    }
}
