<?php
// Manufacturers and models over HTTP: the pages in three languages, add / duplicate / rename-merge / delete, the asset
// form (only users with catalog.edit may add a name), and the Excel/CSV import filling the lists.
return function (string $root, string $tmp): void {
    $port = random_int(19601, 20300);
    $env = array_merge(getenv(), ['ITAM_DB_PATH' => getenv('ITAM_DB_PATH'), 'ITAM_STORAGE' => getenv('ITAM_STORAGE')]);
    $proc = proc_open([...t_php(), '-S', "127.0.0.1:$port", '-t', $root . '/public', $root . '/public/index.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/server3.log', 'a'], 2 => ['file', $tmp . '/server3.log', 'a']], $pipes, $root, $env);
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
        usleep(100000);
    }
    $req = function (string $path, array|string|null $post = null, ?string $jar = null, array $headers = []) use ($port): array {
        $ch = curl_init("http://127.0.0.1:$port$path");
        $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers];
        if ($jar) {
            $o += [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar];
        }
        if ($post !== null) {
            // An array with a CURLFile is sent as multipart; other arrays as a form.
            $multipart = is_array($post) && array_filter($post, fn ($v) => $v instanceof CURLFile);
            $o += [CURLOPT_POST => true, CURLOPT_POSTFIELDS => is_array($post) && !$multipart ? http_build_query($post) : $post];
        }
        curl_setopt_array($ch, $o);
        $body = (string) curl_exec($ch);
        return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $body, (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL)];
    };
    $token = fn (string $html) => preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
    $user = function (string $email, string $role, string $jar) use ($req, $token): void {
        $pw = 'Pw-' . bin2hex(random_bytes(5));
        db()->insert('users', ['name' => $email, 'email' => $email, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => $role,
            'locale' => 'en', 'theme' => 'system', 'active' => 1, 'created_at' => now()]);
        [, $html] = $req('/login', null, $jar);
        $req('/login', ['_token' => $token($html), 'email' => $email, 'password' => $pw], $jar);
    };
    $broken = fn (string $html) => (bool) preg_match('#<b>(Fatal error|Warning|Notice|Deprecated)</b>:|Uncaught [A-Za-z\\\\]+Exception#', $html);
    $mid = fn (string $name) => db()->value('SELECT id FROM manufacturers WHERE name = ?', [$name]);
    $csrf = null;
    try {
        $admin = $tmp . '/cat-admin.txt';
        $user('cat-admin@cat.test', 'admin', $admin);
        [, $html] = $req('/directory/manufacturers', null, $admin);
        $csrf = $token($html);
        $post = fn (string $path, array $data, ?string $jar = null) => $req($path, ['_token' => $csrf] + $data, $jar ?? $admin);

        // Pages in three languages.
        $anyM = (int) db()->value('SELECT MIN(id) FROM manufacturers');
        $anyX = (int) db()->value('SELECT MIN(id) FROM asset_models');
        $bad = [];
        foreach (['en', 'ar'] as $l) {
            $req('/lang/' . $l, null, $admin);
            foreach (['/directory/manufacturers', '/directory/models', "/directory/models?manufacturer=$anyM", "/directory/manufacturers/$anyM/edit", "/directory/models/$anyX/edit", '/assets/create', '/help/reference-data'] as $p) {
                [$code, $html] = $req($p, null, $admin);
                if ($code !== 200 || $broken($html)) {
                    $bad[] = "$l $p $code";
                }
            }
        }
        check('manufacturer and model pages open in three languages', !$bad, implode(', ', $bad));
        $req('/lang/en', null, $admin);
        [, $html] = $req('/assets/create', null, $admin);
        check('the asset form carries the lists', str_contains($html, 'data-catalog=') && str_contains($html, '&quot;canAdd&quot;:true'));

        // Add, duplicate, rename-merge, delete.
        $post('/directory/manufacturers', ['name' => 'Cat Test Co', 'website' => 'cattest.example', 'support_phone' => '+966 11 000 0000']);
        $post('/directory/manufacturers', ['name' => 'cat test co']);
        check('a manufacturer is added once', $mid('Cat Test Co') && (int) db()->value("SELECT COUNT(*) FROM manufacturers WHERE LOWER(name) = 'cat test co'") === 1);
        $post('/directory/models', ['manufacturer_id' => $mid('Cat Test Co'), 'name' => 'CT-1', 'category_id' => '']);
        check('a model is added under its manufacturer', (bool) db()->value('SELECT id FROM asset_models WHERE manufacturer_id = ? AND name = ?', [$mid('Cat Test Co'), 'CT-1']));
        $post('/directory/manufacturers', ['name' => 'CatTest Typo']);
        $typo = $mid('CatTest Typo');
        db()->insert('assets', ['tag' => 'HT-CAT-1', 'name' => 'Cat http 1', 'status' => 'in_stock', 'manufacturer' => 'CatTest Typo', 'model' => 'CT-1', 'created_at' => now(), 'updated_at' => now()]);
        [$code, , $to] = $post("/directory/manufacturers/$typo/delete", []);
        check('a manufacturer still on devices is not deleted', (bool) $mid('CatTest Typo') && str_contains($to, '/edit'), "$code $to");
        $post("/directory/manufacturers/$typo", ['name' => 'Cat Test Co', 'website' => '', 'support_phone' => '']);
        check('renaming to a listed name merges and renames the devices', !db()->value('SELECT id FROM manufacturers WHERE id = ?', [$typo])
            && db()->value("SELECT manufacturer FROM assets WHERE tag = 'HT-CAT-1'") === 'Cat Test Co');

        // Asset form: a technician picks from the lists; an administrator may add.
        $tech = $tmp . '/cat-tech.txt';
        $user('cat-tech@cat.test', 'technician', $tech);
        [, $html] = $req('/assets/create', null, $tech);
        check('a technician cannot add from the form', str_contains($html, '&quot;canAdd&quot;:false'));
        $techCsrf = $token($html);
        $req('/assets', ['_token' => $techCsrf, 'name' => 'Cat http 2', 'tag' => 'HT-CAT-2', 'manufacturer' => 'Unlisted Maker', 'model' => 'U1'], $tech);
        check('a technician cannot save an unlisted manufacturer', !db()->value("SELECT id FROM assets WHERE tag = 'HT-CAT-2'") && !$mid('Unlisted Maker'));
        $req('/assets', ['_token' => $techCsrf, 'name' => 'Cat http 3', 'tag' => 'HT-CAT-3', 'manufacturer' => 'cat test co', 'model' => 'ct-1'], $tech);
        check('a technician saves a listed one, in the list spelling', db()->one("SELECT manufacturer, model FROM assets WHERE tag = 'HT-CAT-3'") === ['manufacturer' => 'Cat Test Co', 'model' => 'CT-1']);
        $post('/assets', ['name' => 'Cat http 4', 'tag' => 'HT-CAT-4', 'manufacturer' => 'Cat Test Co', 'model' => 'CT-9']);
        check('an administrator adds a new model from the form', db()->value("SELECT model FROM assets WHERE tag = 'HT-CAT-4'") === 'CT-9'
            && db()->value('SELECT id FROM asset_models WHERE manufacturer_id = ? AND name = ?', [$mid('Cat Test Co'), 'CT-9']));

        // The import adds unknown names.
        $csv = $tmp . '/cat-import.csv';
        file_put_contents($csv, "tag,name,manufacturer,model\nHT-CAT-5,Cat http 5,Imported Maker,IM-5\n");
        $req('/assets/import', ['_token' => $csrf, 'csv' => new CURLFile($csv, 'text/csv', 'assets.csv')], $admin);
        check('a CSV import adds the unknown manufacturer and model', db()->value("SELECT tag FROM assets WHERE tag = 'HT-CAT-5'") === 'HT-CAT-5'
            && $mid('Imported Maker') && db()->value('SELECT id FROM asset_models WHERE manufacturer_id = ? AND name = ?', [$mid('Imported Maker'), 'IM-5']));
        unlink($csv);

        db()->run("DELETE FROM assets WHERE tag LIKE 'HT-CAT-%' OR serial = 'CAT-TEST-SN'");
        $x = (int) db()->value('SELECT id FROM asset_models WHERE manufacturer_id = ? AND name = ?', [$mid('Cat Test Co'), 'CT-9']);
        $post("/directory/models/$x/delete", []);
        check('an unused model is deleted', !db()->value('SELECT id FROM asset_models WHERE id = ?', [$x]));
    } finally {
        // Leave the shared test database as the other suites expect it.
        db()->run("DELETE FROM assets WHERE tag LIKE 'HT-CAT-%' OR serial = 'CAT-TEST-SN'");
        db()->run("DELETE FROM asset_models WHERE manufacturer_id IN (SELECT id FROM manufacturers WHERE name IN ('Cat Test Co', 'CatTest Typo', 'Unlisted Maker', 'Imported Maker', 'Agent Maker'))");
        db()->run("DELETE FROM manufacturers WHERE name IN ('Cat Test Co', 'CatTest Typo', 'Unlisted Maker', 'Imported Maker', 'Agent Maker')");
        db()->run("DELETE FROM api_tokens WHERE name = 'cat agent'");
        db()->run("DELETE FROM users WHERE email LIKE '%@cat.test'");
        proc_terminate($proc);
        proc_close($proc);
    }
};
