<?php
// Consistency: every route points to a real method, English and Arabic have the same keys, every literal t('…') in
// the code exists, every setting has a label, and the user guide is complete.
return function (string $root): void {
    $router = require $root . '/app/routes.php';
    $routes = (fn () => $this->routes)->call($router);
    $missing = [];
    foreach ($routes as $r) {
        [$class, $method] = $r['handler'];
        if (!method_exists($class, $method)) {
            $missing[] = "$class::$method";
        }
    }
    check('every route has a controller method (' . count($routes) . ' routes)', !$missing, implode(', ', array_slice($missing, 0, 5)));

    // The platform speaks Arabic and English, even where an installed config file still lists French.
    check('only Arabic and English are offered', array_keys(config('i18n.locales')) == ['en', 'ar'] || array_keys(config('i18n.locales')) == ['ar', 'en']);
    check('French is not selectable', \ITAM\Core\Lang::with('fr', fn () => \ITAM\Core\Lang::locale()) === \ITAM\Core\Lang::locale());
    check('no French files remain', !is_file($root . '/resources/lang/fr.php') && !is_dir($root . '/resources/help/fr'));

    // imagedestroy() is deprecated since PHP 8.5 and fills the server log.
    $old = array_filter(glob($root . '/app/Services/*.php'), fn ($f) => preg_match('/^\s*imagedestroy\(/m', (string) file_get_contents($f)));
    check('no deprecated image calls remain', !$old, implode(', ', $old));

    $lang = [];
    foreach (['en', 'ar'] as $l) {
        $lang[$l] = require $root . "/resources/lang/$l.php";
    }
    foreach (['ar'] as $l) {
        $diff = array_merge(array_diff_key($lang['en'], $lang[$l]), array_diff_key($lang[$l], $lang['en']));
        check("en and $l have the same keys", !$diff, implode(', ', array_slice(array_keys($diff), 0, 8)));
    }
    // A placeholder may run into a unit in English (":dd" is :d then "d"), so each language's placeholders must appear in English.
    $params = [];
    foreach ($lang['en'] as $k => $v) {
        foreach (['ar'] as $l) {
            preg_match_all('/:[a-z_]+/', (string) ($lang[$l][$k] ?? ''), $b);
            foreach (array_unique($b[0]) as $ph) {
                if (!str_contains((string) $v, $ph)) {
                    $params[] = "$l:$k";
                }
            }
        }
    }
    check('placeholders (:name) present in every language', !$params, implode(', ', array_slice($params, 0, 8)));

    $src = '';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ([$it, new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/resources/views', FilesystemIterator::SKIP_DOTS))] as $files) {
        foreach ($files as $f) {
            if (str_ends_with((string) $f, '.php')) {
                $src .= file_get_contents((string) $f);
            }
        }
    }
    preg_match_all("/\\bt\\('([a-z_]+\\.[a-z0-9_.]*[a-z0-9_])'/", $src, $m);
    // Keys ending in "_" are prefixes completed at run time (t('proc.decided_' . $status)).
    $unknown = array_values(array_filter(array_unique($m[1]), fn ($k) => !str_ends_with($k, '_') && !isset($lang['en'][$k])));
    check('every literal t(\'…\') key exists (' . count(array_unique($m[1])) . ' keys)', !$unknown, implode(', ', array_slice($unknown, 0, 8)));

    $noLabel = array_filter(array_keys(\ITAM\Services\PlatformSettings::FIELDS), fn ($k) => !isset($lang['en']['settings.f.' . $k]));
    check('every setting has a label', !$noLabel, implode(', ', array_slice($noLabel, 0, 8)));

    exec(implode(' ', array_map('escapeshellarg', [...t_php(), $root . '/scripts/check-help.php'])) . ' 2>&1', $out, $code);
    check('user guide complete in the three languages', $code === 0 && str_contains(implode("\n", $out), 'OK'), implode(' | ', array_slice($out, -3)));
};
