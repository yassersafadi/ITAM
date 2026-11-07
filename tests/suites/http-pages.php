<?php
// HTTP pass on a separate PHP server using the test database: every page as an administrator in English and Arabic,
// pages with ids, the employee portal, the public fault report, the API, permissions, CSRF and the About page.
return function (string $root, string $tmp): void {
    $port = random_int(18100, 18900);
    $env = array_merge(getenv(), ['ITAM_DB_PATH' => getenv('ITAM_DB_PATH'), 'ITAM_STORAGE' => getenv('ITAM_STORAGE')]);
    $proc = proc_open([...t_php(), '-S', "127.0.0.1:$port", '-t', $root . '/public', $root . '/public/index.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/server.log', 'a'], 2 => ['file', $tmp . '/server.log', 'a']], $pipes, $root, $env);
    $base = "http://127.0.0.1:$port";
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
        usleep(100000);
    }
    $get = function (string $path, ?array $post = null, string $cookies = '', array $headers = []) use ($base): array {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60] + ($post !== null ? [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)] : []));
        $body = (string) curl_exec($ch);
        return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $body, (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL)];
    };
    $token = fn (string $html) => preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
    $broken = fn (string $html) => preg_match('#<b>(Fatal error|Warning|Notice|Deprecated)</b>:|Uncaught [A-Za-z\\\\]+Exception|Stack trace:#', $html, $m) ? $m[0] : null;
    // Visible text that looks like an untranslated key ("assets.title").
    $rawKey = function (string $html): ?string {
        $text = html_entity_decode(strip_tags((string) preg_replace('#<(script|style|code|pre|textarea)\b.*?</\1>#s', ' ', $html)));
        preg_match_all('/(?<![\w.@\/:-])([a-z][a-z0-9_]+\.(?:[a-z0-9_]+\.)*[a-z0-9_]{2,})(?![\w.@\/-])/', $text, $m);
        $keys = array_filter($m[1], fn ($k) => isset((require ITAM_ROOT . '/resources/lang/en.php')[$k]));
        return $keys ? implode(' ', array_slice(array_unique($keys), 0, 3)) : null;
    };
    $logFile = ITAM_STORAGE . '/logs/itam.log';
    $logFrom = is_file($logFile) ? filesize($logFile) : 0;
    $login = function (string $email, string $password, string $cookies) use ($get, $token): bool {
        [, $html] = $get('/login', null, $cookies);
        [$code, , $to] = $get('/login', ['_token' => $token($html), 'email' => $email, 'password' => $password], $cookies);
        return $code === 302 && !str_contains($to, '/login');
    };
    try {
        $admin = $tmp . '/admin.txt';
        check('administrator signs in', $login(\ITAM\Services\Seeder::DEFAULT_ADMIN_EMAIL, \ITAM\Services\Seeder::DEFAULT_ADMIN_PASSWORD, $admin));

        preg_match_all("/\\\$r->get\\('([^']+)'/", (string) file_get_contents($root . '/app/routes.php'), $m);
        $skip = '#\{|^/(logout|lang/|manifest|api/|portal|install|sign/|report/|a/|admin/backups|login|forgot-password|reset-password|auth/)|/export$|/template$#';
        $pages = array_values(array_filter(array_unique($m[1]), fn ($p) => !preg_match($skip, $p)));
        foreach (['assets' => '/assets/', 'employees' => '/employees/', 'maintenance' => '/work-orders/', 'licenses' => '/licenses/', 'consumables' => '/consumables/',
                'audits' => '/audits/', 'custody_receipts' => '/custody/'] as $table => $prefix) {
            if ($id = db()->value("SELECT MIN(id) FROM $table")) {
                $pages[] = $prefix . $id;
            }
        }
        $asset = (int) db()->value("SELECT MIN(id) FROM assets WHERE status = 'deployed'");
        array_push($pages, "/assets/$asset/edit", "/assets/$asset/dispose", "/assets/$asset/preview", '/assets?view=cards', '/assets?view=groups', '/assets/labels?ids=' . $asset);
        foreach (['employees', 'locations', 'departments', 'categories', 'manufacturers', 'models'] as $type) {
            $pages[] = "/directory/$type";
            if ($id = db()->value('SELECT MIN(id) FROM ' . (['models' => 'asset_models'][$type] ?? $type))) {
                $pages[] = "/directory/$type/$id/edit";
            }
        }
        foreach (\ITAM\Services\PlatformSettings::TABS as $tab) {
            $pages[] = '/admin/settings?tab=' . $tab;
        }
        foreach (\ITAM\Controllers\ReportController::TABS as $tab) {
            $pages[] = '/reports?tab=' . $tab;
        }
        foreach (array_keys(\ITAM\Services\Help::SECTIONS) as $slug) {
            $pages[] = '/help/' . $slug;
        }
        foreach (['en', 'ar'] as $locale) {
            $get('/lang/' . $locale, null, $admin);
            $bad = [];
            foreach ($pages as $p) {
                [$code, $html] = $get($p, null, $admin);
                if ($code !== 200 || ($why = $broken($html)) || ($why = $rawKey($html))) {
                    $bad[] = "$p ($code" . (isset($why) && $why ? ": $why" : '') . ')';
                }
                $why = null;
            }
            check("every page opens in $locale (" . count($pages) . ' pages)', !$bad, implode(', ', array_slice($bad, 0, 6)));
        }
        [, $html] = $get('/', null, $admin);
        check('the page says it is ITAM and credits AIM in the footer', str_contains($html, 'AGPL-3.0') && str_contains($html, (string) config('promo.url')));
        check('the AIM card is under the menu for administrators', str_contains($html, 'itam-promo'));
        $get('/about/promo/hide', ['_token' => $token($html)], $admin);
        [, $html] = $get('/', null, $admin);
        check('the AIM card can be hidden', !str_contains($html, 'class="itam-promo"'));
        [$code, $html] = $get('/about', null, $admin);
        check('the About page explains the licence and links to AIM', $code === 200 && str_contains($html, 'AGPL-3.0') && str_contains($html, 'id="aim"'));
        check('nothing on the pages calls another server', !preg_match('#<script[^>]+src="https?://#', $html));

        // Permissions: an auditor sees the register but not the administration.
        db()->insert('users', ['name' => 'Test Auditor', 'email' => 'auditor@tests.local', 'password_hash' => password_hash('Audit@12345', PASSWORD_DEFAULT),
            'role' => 'auditor', 'locale' => 'en', 'theme' => 'system', 'active' => 1, 'created_at' => now()]);
        $aud = $tmp . '/auditor.txt';
        check('auditor signs in', $login('auditor@tests.local', 'Audit@12345', $aud));
        check('auditor reads the register', $get('/assets', null, $aud)[0] === 200);
        foreach (['/admin/settings', '/admin/users', '/admin/roles', "/assets/$asset/edit"] as $p) {
            check("auditor is refused $p", $get($p, null, $aud)[0] === 403);
        }
        [, $html] = $get('/', null, $aud);
        check('no AIM card for people who are not administrators', !str_contains($html, 'class="itam-promo"'));

        // CSRF: a form sent without the token is refused.
        check('POST without a token is refused', $get('/assets', ['name' => 'x'], $admin)[0] === 419);

        // The installation wizard is closed once installed.
        [$code, , $to] = $get('/install', null, $admin);
        check('the wizard is closed after installation', $code === 302 && !str_contains($to, '/install'));

        // Public fault report from a QR label (no account).
        $tag = (string) db()->value('SELECT tag FROM assets WHERE id = ?', [$asset]);
        $pub = $tmp . '/public.txt';
        [$code, $html] = $get('/report/' . rawurlencode($tag), null, $pub);
        check('the QR report form opens without an account', $code === 200 && !$broken($html));
        $before = (int) db()->value("SELECT COUNT(*) FROM maintenance WHERE source = 'report'");
        $get('/report/' . rawurlencode($tag), ['_token' => $token($html), 'reporter_name' => 'Visitor', 'reporter_contact' => 'visitor@tests.local',
            'description' => 'The screen stays black.', 'urgency' => 'degraded'], $pub);
        check('the report becomes a work order', (int) db()->value("SELECT COUNT(*) FROM maintenance WHERE source = 'report'") === $before + 1);

        // Employee portal: a one-time link signs the employee in.
        $emp = (int) db()->value('SELECT assigned_to FROM assets WHERE id = ?', [$asset]);
        $link = bin2hex(random_bytes(32));
        db()->insert('portal_tokens', ['employee_id' => $emp, 'token_hash' => hash('sha256', $link), 'expires_at' => date('Y-m-d H:i:s', time() + 600), 'created_at' => now()]);
        $portal = $tmp . '/portal.txt';
        $get('/portal/auth/' . $link, null, $portal);
        foreach (['/portal', '/portal/devices', '/portal/devices/' . $asset, '/portal/documents'] as $p) {
            [$code, $html] = $get($p, null, $portal);
            check("portal page $p", $code === 200 && !$broken($html) && !$rawKey($html), (string) $code);
        }
        [, $html] = $get('/portal', null, $portal);
        check('no AIM card in the employee portal', !str_contains($html, 'itam-promo') && !str_contains($html, (string) config('promo.url')));
        $before = (int) db()->value("SELECT COUNT(*) FROM maintenance WHERE source = 'portal'");
        $get('/portal/report', ['_token' => $token($html), 'asset_id' => $asset, 'description' => 'Battery drains fast.', 'urgency' => 'minor'], $portal);
        check('a fault reported in the portal becomes a work order', (int) db()->value("SELECT COUNT(*) FROM maintenance WHERE source = 'portal'") === $before + 1);
        check('the portal does not open the staff pages', $get('/assets', null, $portal)[0] === 302);

        // REST API with a read token.
        $plain = 'itam_' . bin2hex(random_bytes(24));
        db()->insert('api_tokens', ['name' => 'Test', 'token_hash' => hash('sha256', $plain), 'scopes' => 'read', 'created_at' => now()]);
        [$code, $json] = $get('/api/v1/assets?limit=5', null, '', ['Authorization: Bearer ' . $plain]);
        $data = json_decode($json, true);
        check('the API lists assets with a token', $code === 200 && count($data['data'] ?? []) === 5, (string) $code);
        check('the API never exposes cut fields', !array_key_exists('health_score', $data['data'][0] ?? []));
        check('the API refuses a wrong token', $get('/api/v1/assets', null, '', ['Authorization: Bearer nope'])[0] === 401);
        [$code] = $get('/api/v1/assets/' . rawurlencode($tag), null, '', ['Authorization: Bearer ' . $plain]);
        check('the API returns one asset by tag', $code === 200);
    } finally {
        proc_terminate($proc);
        proc_close($proc);
    }
    $log = is_file($logFile) ? (string) file_get_contents($logFile, false, null, $logFrom) : '';
    check('no PHP errors in the log during the HTTP pass', !preg_match('/PHP (Fatal|Warning|Notice|Deprecated)/', $log), mb_substr($log, 0, 400));
};
