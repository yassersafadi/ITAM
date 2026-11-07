<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * HTML → PDF through a headless Chromium browser (Microsoft Edge or Google Chrome), which gets
 * Arabic shaping and right-to-left layout exactly as on screen. Nothing to install on Windows
 * servers: Edge ships with the OS. Without a browser, reports are still available as a printable page.
 */
final class Pdf
{
    private const CANDIDATES = [
        'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome', '/usr/bin/microsoft-edge',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ];

    public static function browser(): ?string
    {
        $configured = trim((string) config('executive.pdf_browser'));
        // A configured path wins; when it no longer exists (browser moved or uninstalled), fall back to auto-detection.
        foreach ($configured !== '' ? [$configured, ...self::CANDIDATES] : self::CANDIDATES as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    public static function available(): bool
    {
        return self::browser() !== null && function_exists('proc_open');
    }

    /** Write $html as a PDF to $target. Returns the path, or null when no browser is available or rendering failed. */
    public static function fromHtml(string $html, string $target): ?string
    {
        $browser = self::browser();
        if (!$browser || !function_exists('proc_open')) {
            return null;
        }
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        // Profiles of earlier runs may still have been locked when they finished; clear the old ones now.
        foreach (glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itam-pdf-*', GLOB_ONLYDIR) ?: [] as $old) {
            if (filemtime($old) < time() - 600) {
                self::cleanup($old);
            }
        }
        $work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itam-pdf-' . bin2hex(random_bytes(6));
        mkdir($work, 0700);
        $src = $work . DIRECTORY_SEPARATOR . 'report.html';
        file_put_contents($src, $html);
        @unlink($target);
        $uri = 'file:///' . ltrim(str_replace('\\', '/', $src), '/');
        $cmd = [
            $browser, '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-extensions',
            '--user-data-dir=' . $work . DIRECTORY_SEPARATOR . 'profile', '--no-pdf-header-footer', '--print-to-pdf-no-header',
            '--virtual-time-budget=4000', '--print-to-pdf=' . $target, $uri,
        ];
        // On Linux the web server user usually has no writable home, and Chrome's crash reporter aborts without one.
        $env = null;
        if (DIRECTORY_SEPARATOR === '/') {
            $env = getenv();
            $env['HOME'] = $env['XDG_CONFIG_HOME'] = $env['XDG_CACHE_HOME'] = $work;
            // PHP-FPM usually runs under systemd with RestrictNamespaces and PrivateDevices, where Chrome's own sandbox
            // cannot start and Chrome never writes the file. The page is our own HTML from a local file, so it runs
            // without that sandbox, and with /tmp instead of a small /dev/shm.
            array_splice($cmd, 1, 0, ['--no-sandbox', '--disable-dev-shm-usage']);
        }
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($proc)) {
            self::cleanup($work);
            return null;
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = time() + 60;
        while (proc_get_status($proc)['running'] && time() < $deadline) {
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            usleep(200000);
        }
        if (proc_get_status($proc)['running']) {
            proc_terminate($proc);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        // On Windows msedge.exe hands the job to a background process and returns at once: wait for the finished file.
        // Elsewhere the browser has finished (or failed) once it exits, so a missing file is not waited for.
        while (DIRECTORY_SEPARATOR === '\\' && !self::complete($target) && time() < $deadline) {
            usleep(250000);
        }
        $ok = self::complete($target);
        self::cleanup($work);
        return $ok ? $target : null;
    }

    private static function complete(string $file): bool
    {
        clearstatcache(true, $file);
        if (!is_file($file) || filesize($file) < 1000) {
            return false;
        }
        $fp = @fopen($file, 'rb');
        if (!$fp) {
            return false;
        }
        $head = fread($fp, 4);
        fseek($fp, -64, SEEK_END);
        $tail = (string) fread($fp, 64);
        fclose($fp);
        return $head === '%PDF' && str_contains($tail, '%%EOF');
    }

    private static function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        // Best effort: the browser may still be removing its own profile files while we walk it.
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                @($f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()));
            }
        } catch (\Throwable) {
        }
        @rmdir($dir);
    }
}
