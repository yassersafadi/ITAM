<?php
// Every section of the settings page has its title, card line and description in the three languages (the card line
// once showed its key, settings.card.hr, for sections added later).
use ITAM\Core\Lang;
use ITAM\Services\PlatformSettings;

return function (): void {
    $missing = [];
    foreach (['en', 'ar'] as $lang) {
        foreach (PlatformSettings::GROUPS as $group => $tabs) {
            foreach (['settings.group.' . $group, ...array_merge(...array_map(fn ($tb) => ["settings.tab.$tb", "settings.card.$tb", "settings.tab.{$tb}_desc"], $tabs))] as $key) {
                if (Lang::with($lang, fn () => t($key)) === $key) {
                    $missing[] = "$lang:$key";
                }
            }
        }
    }
    check('every settings section is worded in the three languages', !$missing, implode(', ', $missing));
    check('every settings tab is in a group', !array_diff(PlatformSettings::TABS, array_merge(...array_values(PlatformSettings::GROUPS))));
};
