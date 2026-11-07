<?php
declare(strict_types=1);

namespace ITAM\Core;

final class Lang
{
    private static ?string $locale = null;
    private static array $lines = [];

    public static function locale(): string
    {
        if (self::$locale === null) {
            $locales = config('i18n.locales');
            $candidate = $_SESSION['locale'] ?? (Auth::user()['locale'] ?? null)
                ?? (config('i18n.detect_browser', true) ? self::fromBrowser() : null);
            self::$locale = isset($locales[$candidate]) ? $candidate : self::fallback();
        }
        return self::$locale;
    }

    public static function set(string $locale): void
    {
        if (isset(config('i18n.locales')[$locale])) {
            $_SESSION['locale'] = $locale;
            self::$locale = $locale;
            self::$lines = [];
        }
    }

    /** Run $fn in another language without touching the visitor's session (e-mails, scheduled reports). */
    public static function with(string $locale, callable $fn): mixed
    {
        $saved = self::$locale;
        if (isset(config('i18n.locales')[$locale])) {
            self::$locale = $locale;
        }
        try {
            return $fn();
        } finally {
            self::$locale = $saved;
        }
    }

    public static function dir(): string
    {
        return config('i18n.locales')[self::locale()]['dir'] ?? 'ltr';
    }

    public static function get(string $key, array $params = []): string
    {
        $locale = self::locale();
        $line = self::lines($locale)[$key] ?? self::lines('en')[$key] ?? $key;
        // Longest keys first, so ":n" never eats the start of ":name".
        uksort($params, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        foreach ($params as $k => $v) {
            $line = str_replace(':' . $k, (string) $v, $line);
        }
        return $line;
    }

    /** All keys with a given prefix, for the client-side dictionary. */
    public static function group(string $prefix): array
    {
        $out = [];
        foreach (self::lines('en') as $k => $_) {
            if (str_starts_with($k, $prefix)) {
                $out[$k] = self::get($k);
            }
        }
        return $out;
    }

    private static function lines(string $locale): array
    {
        if (!isset(self::$lines[$locale])) {
            $file = ITAM_ROOT . "/resources/lang/{$locale}.php";
            self::$lines[$locale] = is_file($file) ? require $file : [];
        }
        return self::$lines[$locale];
    }

    private static function fromBrowser(): string
    {
        $header = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach (array_keys(config('i18n.locales')) as $code) {
            if (str_starts_with($header, $code)) {
                return $code;
            }
        }
        return self::fallback();
    }

    /** The default language, when it is still one the platform speaks. */
    private static function fallback(): string
    {
        $default = (string) config('i18n.default', 'en');
        return isset(config('i18n.locales')[$default]) ? $default : 'en';
    }
}
