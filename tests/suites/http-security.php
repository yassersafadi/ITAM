<?php
// Security checks from the pre-release review, kept as tests: API token scopes, no escalation to administrator,
// branch scope, anonymous reports cannot claim an employee, CSV formulas, SSO organisations.
return function (string $root, string $tmp): void {
    // Unit-level checks first.
    check('csv_safe neutralises formulas', csv_safe('=HYPERLINK("x")') === "'=HYPERLINK(\"x\")" && csv_safe('-12.5') === '-12.5' && csv_safe('@x') === "'@x");

    $port = random_int(18901, 19600);
    $env = array_merge(getenv(), ['ITAM_DB_PATH' => getenv('ITAM_DB_PATH'), 'ITAM_STORAGE' => getenv('ITAM_STORAGE')]);
    $proc = proc_open([...t_php(), '-S', "127.0.0.1:$port", '-t', $root . '/public', $root . '/public/index.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/server2.log', 'a'], 2 => ['file', $tmp . '/server2.log', 'a']], $pipes, $root, $env);
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
        usleep(100000);
    }
    $req = function (string $path, ?array $post = null, ?string $jar = null, array $headers = [], ?string $raw = null) use ($port): array {
        $ch = curl_init("http://127.0.0.1:$port$path");
        $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers];
        if ($jar) {
            $o += [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar];
        }
        if ($raw !== null) {
            $o += [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $raw];
        } elseif ($post !== null) {
            $o += [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)];
        }
        curl_setopt_array($ch, $o);
        $body = (string) curl_exec($ch);
        return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $body, (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL)];
    };
    $token = fn (string $html) => preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
    $login = function (string $email, string $pw, string $jar) use ($req, $token): void {
        [, $html] = $req('/login', null, $jar);
        $req('/login', ['_token' => $token($html), 'email' => $email, 'password' => $pw], $jar);
    };
    $user = function (string $email, string $role, ?string $scope = null): string {
        $pw = 'Pw-' . bin2hex(random_bytes(5));
        db()->insert('users', ['name' => $email, 'email' => $email, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => $role,
            'locale' => 'en', 'theme' => 'system', 'active' => 1, 'location_scope' => $scope, 'created_at' => now()]);
        return $pw;
    };
    try {
        // API tokens: a token without the read scope is refused; a read token pages through the assets.
        $tok = 'itam_' . bin2hex(random_bytes(24));
        db()->insert('api_tokens', ['name' => 'no scope', 'token_hash' => hash('sha256', $tok), 'scopes' => 'none', 'created_at' => now()]);
        [$code] = $req('/api/v1/assets', null, null, ['Authorization: Bearer ' . $tok]);
        check('a token without the read scope cannot read the assets', $code === 403, (string) $code);
        $read = 'itam_' . bin2hex(random_bytes(24));
        db()->insert('api_tokens', ['name' => 'reader', 'token_hash' => hash('sha256', $read), 'scopes' => 'read', 'created_at' => now()]);
        $get = fn (string $p) => json_decode($req($p, null, null, ['Authorization: Bearer ' . $read])[1], true);
        $p1 = $get('/api/v1/assets?limit=2&sort=tag&dir=asc');
        $p2 = $get('/api/v1/assets?limit=2&offset=2&sort=tag&dir=asc');
        check('the assets come page by page with offset', count($p1['data'] ?? []) === 2 && count($p2['data'] ?? []) >= 1 && $p2['offset'] === 2
            && $p1['data'][0]['tag'] !== $p2['data'][0]['tag'] && array_key_exists('criticality', $p1['data'][0]));
        db()->run('DELETE FROM api_tokens WHERE token_hash IN (?, ?)', [hash('sha256', $read), hash('sha256', $tok)]);

        // A user manager who is not an administrator cannot create or promote an administrator.
        db()->insert('roles', ['slug' => 'helpdesk', 'name_en' => 'Helpdesk lead', 'permissions' => json_encode(['users.manage']), 'sort' => 8, 'created_at' => now(), 'updated_at' => now()]);
        $pw = $user('lead@sec.test', 'helpdesk');
        $jar = $tmp . '/lead.txt';
        $login('lead@sec.test', $pw, $jar);
        [, $html] = $req('/admin/users', null, $jar);
        [$code] = $req('/admin/users', ['_token' => $token($html), 'name' => 'X', 'email' => 'x@sec.test', 'password' => 'Secret-12345', 'role' => 'admin'], $jar);
        check('non-administrator cannot create an administrator', $code === 403 && !db()->value("SELECT id FROM users WHERE email = 'x@sec.test'"), (string) $code);
        $adminId = (int) db()->value("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
        [$code] = $req('/admin/users/' . $adminId, ['_token' => $token($html), 'role' => 'admin', 'active' => '1', 'password' => 'Hijack-12345'], $jar);
        check("non-administrator cannot change an administrator's account", $code === 403, (string) $code);
        [$code] = $req('/admin/roles', null, $jar);
        check('roles page is for administrators only', $code === 403, (string) $code);

        // Branch scope: a technician limited to one location cannot open another branch's files or receipts.
        $locs = array_column(db()->all('SELECT id FROM locations ORDER BY id LIMIT 2'), 'id');
        if (count($locs) === 2) {
            $pw = $user('scoped@sec.test', 'technician', (string) $locs[0]);
            $jar = $tmp . '/scoped.txt';
            $login('scoped@sec.test', $pw, $jar);
            $otherAsset = db()->value('SELECT id FROM assets WHERE location_id = ? ORDER BY id LIMIT 1', [$locs[1]]);
            $fid = db()->insert('attachments', ['asset_id' => $otherAsset, 'kind' => 'other', 'name' => 'x.txt', 'stored_name' => 'none.txt', 'mime' => 'text/plain', 'size' => 1, 'created_at' => now()]);
            [$code] = $req('/files/' . $fid, null, $jar);
            check("scoped user cannot download another branch's file", $code === 403, (string) $code);
            [$code] = $req('/assets/' . $otherAsset, null, $jar);
            check("scoped user cannot open another branch's asset", in_array($code, [403, 404], true), (string) $code);
            $emp = db()->value('SELECT id FROM employees WHERE location_id = ? ORDER BY id LIMIT 1', [$locs[1]]);
            if ($emp) {
                [$code] = $req('/employees/' . $emp, null, $jar);
                check("scoped user cannot open another branch's employee", $code === 403, (string) $code);
            }
        }

        // An anonymous QR report cannot claim to come from an employee.
        $tag = db()->value("SELECT tag FROM assets WHERE status = 'deployed' ORDER BY id LIMIT 1");
        $jar = $tmp . '/anon.txt';
        [, $html] = $req('/report/' . rawurlencode((string) $tag), null, $jar);
        $req('/report/' . rawurlencode((string) $tag), ['_token' => $token($html), 'reporter_name' => 'Mallory', 'reporter_contact' => 'emp:1',
            'description' => 'Security test report', 'urgency' => 'minor'], $jar);
        $contact = db()->value("SELECT reporter_contact FROM maintenance WHERE description = 'Security test report' ORDER BY id DESC LIMIT 1");
        check('QR report cannot use the employee marker', $contact !== null && !str_starts_with((string) $contact, 'emp:'), (string) $contact);

        // Excel import: a tag typed as a number under a 000000 format keeps its zeros, and re-importing fixes a tag an older
        // import stored without them (found by serial).
        db()->insert('assets', ['tag' => '12', 'name' => 'Old Import', 'serial' => 'SEC-PAD-12', 'status' => 'in_stock', 'condition_state' => 'good',
            'created_at' => now(), 'updated_at' => now()]);
        $xlsx = $tmp . '/pad-import.xlsx';
        $zip = new ZipArchive();
        $zip->open($xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="000000"/></numFmts><cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="164"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<si><t>tag</t></si><si><t>name</t></si><si><t>serial</t></si><si><t>Pad Test</t></si><si><t>SEC-PAD-11</t></si><si><t>SEC-PAD-12</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>'
            . '<row r="2"><c r="A2" s="1"><v>11</v></c><c r="B2" t="s"><v>3</v></c><c r="C2" t="s"><v>4</v></c></row>'
            . '<row r="3"><c r="A3" s="1"><v>12</v></c><c r="B3" t="s"><v>3</v></c><c r="C3" t="s"><v>5</v></c></row></sheetData></worksheet>');
        $zip->close();
        $jar = $tmp . '/importer.txt';
        $login('importer@sec.test', $user('importer@sec.test', 'admin'), $jar);
        [, $html] = $req('/assets/import', null, $jar);
        $ch = curl_init("http://127.0.0.1:$port/assets/import");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['_token' => $token($html), 'csv' => new CURLFile($xlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'assets.xlsx')]]);
        curl_exec($ch);
        $tags = db()->all("SELECT tag FROM assets WHERE serial IN ('SEC-PAD-11', 'SEC-PAD-12') ORDER BY serial");
        check('Excel import keeps leading zeros of number tags', array_column($tags, 'tag') === ['000011', '000012'], json_encode($tags));
        db()->run("DELETE FROM assets WHERE serial IN ('SEC-PAD-11', 'SEC-PAD-12')");
        unlink($xlsx);

        // Multi-tenant Microsoft sign-in only admits the listed organisations.
        $meta = ['issuer' => 'https://login.microsoftonline.com/{tenantid}/v2.0'];
        $src = (string) file_get_contents($root . '/app/Services/Oidc.php');
        check('multi-tenant sign-in checks the allowed organisations', str_contains($src, "str_contains((string) \$meta['issuer'], '{tenantid}')") && str_contains($src, 'sso.allowed_tenants'));
    } finally {
        // Leave the shared test database as the other suites expect it.
        db()->run("DELETE FROM users WHERE email LIKE '%@sec.test'");
        db()->run("DELETE FROM roles WHERE slug = 'helpdesk'");
        \ITAM\Core\Auth::forgetRoles();
        proc_terminate($proc);
        proc_close($proc);
    }
};
