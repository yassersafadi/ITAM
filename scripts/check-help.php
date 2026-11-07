<?php
/**
 * Validates the user guide: every section in Help::SECTIONS exists in en/ar with the expected shape,
 * block types are known, [[links]] point at real sections and [label](/path) links stay inside the platform.
 *   php scripts/check-help.php            summary per language
 *   php scripts/check-help.php <slug>...  only those sections
 */
declare(strict_types=1);

define('ITAM_ROOT', dirname(__DIR__));
$src = (string) file_get_contents(ITAM_ROOT . '/app/Services/Help.php');
preg_match('/public const SECTIONS = \[(.*?)\n    \];/s', $src, $m) || exit("SECTIONS not found\n");
preg_match_all("/'([a-z0-9-]+)'\s*=>\s*\[/", $m[1], $mm);
$slugs = $mm[1];
$only = array_slice($argv, 1) ?: $slugs;
$types = ['h' => 'string', 'p' => 'string', 'tip' => 'string', 'warn' => 'string', 'steps' => 'list', 'list' => 'list', 'fields' => 'pairs', 'faq' => 'pairs'];
$errors = 0;
$words = [];
$missing = [];
foreach (['en', 'ar'] as $loc) {
    $words[$loc] = 0;
    foreach ($only as $slug) {
        $file = ITAM_ROOT . "/resources/help/$loc/$slug.php";
        if (!is_file($file)) {
            $missing[$loc][] = $slug;
            continue;
        }
        $out = shell_exec('php -l ' . escapeshellarg($file) . ' 2>&1');
        if (!str_contains((string) $out, 'No syntax errors')) {
            echo "[$loc/$slug] SYNTAX: " . trim((string) $out) . "\n";
            $errors++;
            continue;
        }
        $d = require $file;
        $err = static function (string $msg) use ($loc, $slug, &$errors) {
            echo "[$loc/$slug] $msg\n";
            $errors++;
        };
        if (!is_array($d) || !is_string($d['title'] ?? null) || !is_string($d['summary'] ?? null) || !is_array($d['blocks'] ?? null)) {
            $err('needs title, summary (strings) and blocks (array)');
            continue;
        }
        $text = [$d['title'], $d['summary']];
        foreach ($d['blocks'] as $i => $b) {
            if (!is_array($b) || count($b) !== 2 || !isset($types[$b[0] ?? ''])) {
                $err("block $i: expected [type, content] with a known type");
                continue;
            }
            [$t, $c] = $b;
            $ok = match ($types[$t]) {
                'string' => is_string($c),
                'list' => is_array($c) && array_is_list($c) && !array_filter($c, fn ($x) => !is_string($x)),
                'pairs' => is_array($c) && array_is_list($c) && !array_filter($c, fn ($x) => !is_array($x) || count($x) !== 2 || !is_string($x[0]) || !is_string($x[1])),
            };
            if (!$ok) {
                $err("block $i ($t): wrong content shape");
                continue;
            }
            if (is_array($c)) {
                array_walk_recursive($c, function ($v) use (&$text) { $text[] = $v; });
            } else {
                $text[] = $c;
            }
        }
        $all = implode("\n", array_filter($text, 'is_string'));
        preg_match_all('/\[\[([^\]]+)\]\]/', $all, $links);
        foreach (array_unique($links[1]) as $l) {
            if (!in_array($l, $slugs, true)) {
                $err("unknown section link [[$l]]");
            }
        }
        preg_match_all('/\]\(([^)]*)\)/', $all, $paths);
        foreach (array_unique($paths[1]) as $p) {
            if (!preg_match('#^/[A-Za-z0-9/_?=&.-]*$#', $p)) {
                $err("link must be a platform path starting with /: ($p)");
            }
        }
        $words[$loc] += count(preg_split('/\s+/u', trim(strip_tags($all))));
    }
}
foreach (['en', 'ar'] as $loc) {
    printf("%s: %d words, %d/%d sections%s\n", $loc, $words[$loc], count($only) - count($missing[$loc] ?? []), count($only),
        !empty($missing[$loc]) ? ' — missing: ' . implode(', ', $missing[$loc]) : '');
}
echo $errors ? "$errors problem(s)\n" : "OK\n";
exit($errors ? 1 : 0);
