<?php
// The stylesheets' braces are balanced: a block left open (a small-screen @media without its "}") silently turns every
// rule after it into a phone-only rule, as happened when module styles were merged.
return function (string $root): void {
    foreach (glob($root . '/public/assets/css/*.css') ?: [] as $file) {
        $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($file));
        $depth = 0;
        $low = 0;
        foreach (str_split($css) as $c) {
            $depth += $c === '{' ? 1 : ($c === '}' ? -1 : 0);
            $low = min($low, $depth);
        }
        check(basename($file) . ' has every block closed', $depth === 0 && $low === 0, "depth $depth, lowest $low");
    }
};
