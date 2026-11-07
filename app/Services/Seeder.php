<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Database;

/**
 * First run: reference data, the first administrator account (replaced by the one chosen in the installation wizard)
 * and, optionally, a demo estate. Every name, address and number in the demo data is invented.
 */
final class Seeder
{
    public const DEFAULT_ADMIN_EMAIL = 'admin@itam.local';
    public const DEFAULT_ADMIN_PASSWORD = 'Admin@12345';

    public function __construct(private Database $db)
    {
    }

    public function run(bool $demo = true): void
    {
        $this->db->transaction(function () use ($demo) {
            $this->base();
            if ($demo) {
                $this->demo();
            }
            Catalog::syncFromAssets();
        });
        Ledger::record('install', 'system', null, $demo ? 'Installed with demo data' : 'Installed');
    }

    private function base(): void
    {
        $now = now();
        $this->db->insert('users', [
            'name' => 'System Administrator', 'email' => self::DEFAULT_ADMIN_EMAIL,
            'password_hash' => password_hash(self::DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
            'role' => 'admin', 'locale' => 'en', 'theme' => 'system', 'active' => 1, 'created_at' => $now,
        ]);
        $categories = [
            // name, code, icon, lifespan, watts, hours/day, PM interval, Arabic name
            ['Laptop', 'LAP', 'laptop', 48, 45, 8, 0, 'لابتوب'],
            ['Desktop', 'DSK', 'monitor', 60, 110, 9, 365, 'كمبيوتر مكتبي'],
            ['Server', 'SRV', 'server', 72, 450, 24, 180, 'خادم'],
            ['Virtual Server', 'VM', 'cloud', 60, 0, 24, 0, 'خادم افتراضي'],
            ['Network Device', 'NET', 'network', 84, 90, 24, 365, 'جهاز شبكة'],
            ['Printer', 'PRN', 'printer', 60, 60, 9, 120, 'طابعة'],
            ['Monitor', 'MON', 'monitor', 72, 25, 8, 0, 'شاشة'],
            ['Mobile Phone', 'MOB', 'phone', 36, 5, 10, 0, 'جوال'],
            ['Tablet', 'TAB', 'tablet', 48, 8, 6, 0, 'جهاز لوحي'],
            ['Storage', 'STO', 'server', 72, 300, 24, 180, 'وحدة تخزين'],
            ['UPS', 'UPS', 'zap', 60, 120, 24, 180, 'مزود طاقة UPS'],
        ];
        foreach ($categories as [$name, $code, $icon, $life, $w, $h, $pm, $nameAr]) {
            $this->db->insert('categories', [
                'name' => $name, 'name_ar' => $nameAr, 'code' => $code, 'icon' => $icon, 'lifespan_months' => $life,
                'power_watts' => $w, 'hours_per_day' => $h, 'maintenance_interval_days' => $pm,
            ]);
        }
    }

    private function demo(): void
    {
        mt_srand(2026);
        $db = $this->db;
        $now = now();

        $deps = [];
        foreach ([['IT Infrastructure', 'ITI'], ['Service Desk', 'SD'], ['Finance', 'FIN'], ['Human Resources', 'HR'], ['Operations', 'OPS'], ['Sales', 'SAL'], ['Executive Office', 'EXE']] as [$n, $c]) {
            $deps[$c] = $db->insert('departments', ['name' => $n, 'code' => $c]);
        }
        // The executive office is the top of the structure: the other units branch from it.
        $db->update('departments', $deps['EXE'], ['level' => 'executive']);
        foreach ($deps as $c => $id) {
            if ($c !== 'EXE') {
                $db->update('departments', $id, ['parent_id' => $deps['EXE']]);
            }
        }
        $locs = [];
        foreach ([
            ['Riyadh HQ', 'Riyadh', 'Tower A', '12', null],
            ['Riyadh Data Center', 'Riyadh', 'DC-1', 'B1', 'Hall 2'],
            ['Jeddah Branch', 'Jeddah', 'Corniche Plaza', '4', null],
            ['Dammam Branch', 'Dammam', 'Gulf Center', '2', null],
            ['Remote / Home Office', null, null, null, null],
        ] as $i => [$n, $city, $b, $f, $r]) {
            $locs[$i] = $db->insert('locations', ['name' => $n, 'city' => $city, 'building' => $b, 'floor' => $f, 'room' => $r]);
        }
        $cat = [];
        foreach ($db->all('SELECT id, code FROM categories') as $c) {
            $cat[$c['code']] = (int) $c['id'];
        }

        $people = [
            ['Ahmed Al-Qahtani', 'ITI'], ['Sara Al-Otaibi', 'SD'], ['Mohammed Al-Harbi', 'FIN'], ['Noura Al-Shehri', 'HR'],
            ['Khalid Al-Zahrani', 'OPS'], ['Fatimah Al-Dosari', 'SAL'], ['Omar Al-Ghamdi', 'EXE'], ['Reem Al-Mutairi', 'FIN'],
            ['Yousef Al-Anazi', 'ITI'], ['Lina Haddad', 'SAL'], ['Faisal Al-Subaie', 'OPS'], ['Hessa Al-Rashid', 'HR'],
            ['Julien Moreau', 'ITI'], ['Camille Laurent', 'SAL'], ['David Miller', 'OPS'], ['Emily Carter', 'FIN'],
            ['Abdullah Al-Malki', 'SD'], ['Maha Al-Juhani', 'EXE'], ['Turki Al-Shammari', 'OPS'], ['Rana Al-Saadi', 'SAL'],
            ['Nasser Al-Yami', 'ITI'], ['Amal Al-Khaldi', 'FIN'], ['Sophie Bernard', 'HR'], ['Majed Al-Omari', 'SD'],
        ];
        $emps = [];
        foreach ($people as $i => [$n, $d]) {
            $slug = strtolower(str_replace([' ', '-'], ['.', ''], $n));
            $emps[] = $db->insert('employees', [
                'name' => $n, 'employee_no' => 'E' . (1001 + $i), 'email' => $slug . '@example.com',
                'title' => null, 'department_id' => $deps[$d], 'location_id' => $locs[$i % 4 === 3 ? 2 : ($i % 5 === 4 ? 3 : 0)], 'active' => 1,
            ]);
        }

        $models = [
            'LAP' => [['Dell', 'Latitude 5440'], ['Lenovo', 'ThinkPad T14 Gen 4'], ['HP', 'EliteBook 840 G10'], ['Apple', 'MacBook Pro 14']],
            'DSK' => [['Dell', 'OptiPlex 7010'], ['HP', 'ProDesk 600 G6'], ['Lenovo', 'ThinkCentre M70q']],
            'MON' => [['Dell', 'P2723D'], ['LG', '27UP850']],
            'PRN' => [['HP', 'LaserJet Enterprise M607'], ['Canon', 'imageRUNNER C3226']],
            'MOB' => [['Apple', 'iPhone 15'], ['Samsung', 'Galaxy S24']],
            'TAB' => [['Apple', 'iPad Air']],
        ];
        $os = ['LAP' => ['Windows 11 Pro', 'Windows 11 Pro', 'macOS 15'], 'DSK' => ['Windows 11 Pro', 'Windows 10 Pro']];
        $tagN = [];
        $mk = function (string $code, array $data) use ($db, $cat, &$tagN, $now): int {
            $tagN[$code] = ($tagN[$code] ?? 0) + 1;
            $row = $data + [
                'tag' => config('app.tag_prefix', 'IT') !== ''
                    ? config('app.tag_prefix', 'IT') . '-' . $code . '-' . str_pad((string) $tagN[$code], 4, '0', STR_PAD_LEFT)
                    : str_pad((string) array_sum($tagN), AssetQuery::NUMERIC_TAG_DIGITS, '0', STR_PAD_LEFT),
                'category_id' => $cat[$code], 'status' => 'deployed', 'condition_state' => 'good', 'criticality' => 2,
                'source' => 'manual', 'created_at' => $now, 'updated_at' => $now,
            ];
            return $db->insert('assets', $row);
        };
        $ago = fn (int $minDays, int $maxDays) => date('Y-m-d', strtotime('-' . mt_rand($minDays, $maxDays) . ' days'));
        $seen = fn () => date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 20) . ' days -' . mt_rand(0, 23) . ' hours'));
        $serial = fn (string $p) => strtoupper($p . substr(md5((string) mt_rand()), 0, 8));

        // ---------- End-user devices ----------
        $endUser = [];
        foreach (['LAP' => 46, 'DSK' => 22, 'MON' => 18, 'MOB' => 14, 'TAB' => 6] as $code => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$man, $model] = $models[$code][mt_rand(0, count($models[$code]) - 1)];
                if ($code === 'DSK' && $i < 7) {
                    [$man, $model] = ['HP', 'ProDesk 600 G6'];       // the unreliable model for the demo
                }
                $acq = $ago(60, 1900);
                $empIdx = mt_rand(0, count($emps) - 1);
                $roll = mt_rand(1, 100);
                $status = $roll <= 74 ? 'deployed' : ($roll <= 88 ? 'in_stock' : ($roll <= 93 ? 'in_repair' : ($roll <= 97 ? 'reserved' : 'retired')));
                $cond = ['excellent', 'good', 'good', 'good', 'fair', 'fair', 'poor'][mt_rand(0, 6)];
                $data = [
                    'name' => "$man $model", 'manufacturer' => $man, 'model' => $model, 'serial' => $serial(substr($man, 0, 2)),
                    'status' => $status, 'condition_state' => $cond, 'criticality' => mt_rand(1, 10) > 8 ? 3 : (mt_rand(0, 1) + 1),
                    'acquired_at' => $acq, 'warranty_until' => date('Y-m-d', strtotime($acq . ' +' . ($code === 'MOB' ? 24 : 36) . ' months')),
                    'value' => ['LAP' => 5200, 'DSK' => 3800, 'MON' => 1100, 'MOB' => 3900, 'TAB' => 2800][$code] + mt_rand(-400, 900),
                    'location_id' => $locs[[0, 0, 0, 2, 3, 4][mt_rand(0, 5)]],
                    'department_id' => $db->value('SELECT department_id FROM employees WHERE id = ?', [$emps[$empIdx]]),
                    'last_seen_at' => mt_rand(1, 100) <= 92 ? $seen() : date('Y-m-d H:i:s', strtotime('-' . mt_rand(200, 400) . ' days')),
                ];
                if (in_array($code, ['LAP', 'DSK'], true)) {
                    $data += [
                        'hostname' => strtoupper($code === 'LAP' ? 'LT' : 'WS') . '-' . str_pad((string) ($i + 101), 4, '0', STR_PAD_LEFT),
                        'os' => $man === 'Apple' ? 'macOS 15' : $os[$code][mt_rand(0, count($os[$code]) - 1)],
                        'cpu' => $man === 'Apple' ? 'Apple M3 Pro' : ['Intel Core i5-1345U', 'Intel Core i7-1365U', 'Intel Core i7-13700', 'AMD Ryzen 7 PRO 7840U'][mt_rand(0, 3)],
                        'ram_gb' => [8, 16, 16, 32][mt_rand(0, 3)], 'storage_gb' => [256, 512, 512, 1024][mt_rand(0, 3)],
                        'mac_address' => implode(':', array_map(fn () => sprintf('%02X', mt_rand(0, 255)), range(1, 6))),
                    ];
                }
                if ($status === 'deployed') {
                    $data['assigned_to'] = $emps[$empIdx];
                }
                $id = $mk($code, $data);
                $endUser[] = ['id' => $id, 'code' => $code, 'status' => $status, 'emp' => $data['assigned_to'] ?? null, 'model' => $model, 'acq' => $acq];
            }
        }

        // ---------- Data center & network ----------
        $dc = $locs[1];
        $infra = fn (string $code, string $man, string $model, string $name, int $crit, array $extra = []) => $mk($code, $extra + [
            'name' => $name, 'manufacturer' => $man, 'model' => $model, 'serial' => $serial(substr($man, 0, 2)),
            'criticality' => $crit, 'location_id' => $dc, 'department_id' => $deps['ITI'], 'acquired_at' => $ago(200, 2400),
            'condition_state' => 'good', 'last_seen_at' => $seen(), 'value' => mt_rand(18000, 95000),
        ]);
        $warranty = fn (int $id, int $days) => $db->update('assets', $id, ['warranty_until' => days_from_today($days)]);

        $fw    = $infra('NET', 'Fortinet', 'FortiGate 100F', 'Core Firewall FG-01', 4, ['hostname' => 'fw-core-01', 'ip_address' => '10.0.0.1']);
        $core  = $infra('NET', 'Cisco', 'Catalyst 9300', 'Core Switch CS-01', 4, ['hostname' => 'sw-core-01', 'ip_address' => '10.0.0.2']);
        $core2 = $infra('NET', 'Cisco', 'Catalyst 9300', 'Core Switch CS-02', 4, ['hostname' => 'sw-core-02', 'ip_address' => '10.0.0.3']);
        $wan   = $infra('NET', 'Cisco', 'ISR 4331', 'WAN Router RT-01', 3, ['hostname' => 'rt-wan-01', 'ip_address' => '10.0.0.254']);
        $accH  = $infra('NET', 'Aruba', '6300M', 'HQ Access Switch AS-HQ12', 3, ['hostname' => 'sw-hq-12', 'ip_address' => '10.1.12.2', 'location_id' => $locs[0]]);
        $accJ  = $infra('NET', 'Aruba', '6300M', 'Jeddah Access Switch AS-JED', 3, ['hostname' => 'sw-jed-01', 'ip_address' => '10.2.0.2', 'location_id' => $locs[2]]);
        $accD  = $infra('NET', 'Aruba', '6300M', 'Dammam Access Switch AS-DMM', 3, ['hostname' => 'sw-dmm-01', 'ip_address' => '10.3.0.2', 'location_id' => $locs[3]]);
        $ups   = $infra('UPS', 'APC', 'Smart-UPS SRT 3000', 'Data Center UPS A', 4, ['acquired_at' => $ago(1700, 2000)]);
        $san   = $infra('STO', 'NetApp', 'AFF A250', 'Primary SAN NA-01', 4, ['hostname' => 'san-01', 'ip_address' => '10.0.10.5']);
        $nas   = $infra('STO', 'Synology', 'RS3621xs+', 'Backup NAS SY-01', 3, ['hostname' => 'nas-bkp-01', 'ip_address' => '10.0.10.9']);
        $esx1  = $infra('SRV', 'Dell', 'PowerEdge R750', 'Virtualization Host ESX-01', 4, ['hostname' => 'esx-01', 'ip_address' => '10.0.20.11', 'os' => 'VMware ESXi 8.0', 'cpu' => '2x Xeon Gold 6338', 'ram_gb' => 512, 'storage_gb' => 1920]);
        $esx2  = $infra('SRV', 'Dell', 'PowerEdge R750', 'Virtualization Host ESX-02', 4, ['hostname' => 'esx-02', 'ip_address' => '10.0.20.12', 'os' => 'VMware ESXi 8.0', 'cpu' => '2x Xeon Gold 6338', 'ram_gb' => 512, 'storage_gb' => 1920]);
        $legacy = $infra('SRV', 'HPE', 'ProLiant DL380 Gen10', 'Legacy File Server FS-01', 3, ['hostname' => 'fs-01', 'ip_address' => '10.0.20.30', 'os' => 'Windows Server 2016', 'acquired_at' => $ago(2300, 2500), 'condition_state' => 'fair']);
        $vm = fn (string $name, string $host, string $ip, int $crit) => $mk('VM', [
            'name' => $name, 'hostname' => $host, 'ip_address' => $ip, 'criticality' => $crit, 'os' => 'Windows Server 2022',
            'location_id' => $dc, 'department_id' => $deps['ITI'], 'acquired_at' => $ago(100, 900), 'last_seen_at' => $seen(), 'source' => 'agent',
        ]);
        $vmErp  = $vm('ERP Application Server', 'vm-erp-01', '10.0.30.10', 4);
        $vmDb   = $vm('ERP Database Server', 'vm-sql-01', '10.0.30.11', 4);
        $vmAd   = $vm('Domain Controller DC-01', 'vm-dc-01', '10.0.30.2', 4);
        $vmAd2  = $vm('Domain Controller DC-02', 'vm-dc-02', '10.0.30.3', 4);
        $vmMail = $vm('Mail Relay Server', 'vm-mail-01', '10.0.30.20', 3);
        $vmHr   = $vm('HR Portal', 'vm-hr-01', '10.0.30.40', 2);
        $vmDup  = $vm('Monitoring Server', 'vm-mon-01', '10.0.30.20', 2);   // deliberate IP conflict with mail relay

        $warranty($core, 25);
        $warranty($san, 40);
        $warranty($legacy, -400);
        $warranty($ups, -90);
        $warranty($fw, 300);

        $printers = [];
        foreach ([[0, $accH], [0, $accH], [2, $accJ], [3, $accD]] as $i => [$li, $sw]) {
            [$man, $model] = $models['PRN'][$i % 2];
            $printers[] = [$mk('PRN', [
                'name' => "$man $model", 'manufacturer' => $man, 'model' => $model, 'serial' => $serial('PR'),
                'location_id' => $locs[$li], 'acquired_at' => $ago(400, 1800), 'ip_address' => '10.' . ($li + 1) . '.50.' . (10 + $i),
                'last_seen_at' => $seen(), 'condition_state' => $i === 2 ? 'poor' : 'good', 'criticality' => 1,
            ]), $sw];
        }

        // ---------- Assignment history ----------
        foreach ($endUser as $a) {
            if ($a['emp']) {
                $out = date('Y-m-d H:i:s', strtotime($a['acq'] . ' +' . mt_rand(2, 20) . ' days'));
                $db->insert('assignments', [
                    'asset_id' => $a['id'], 'employee_id' => $a['emp'], 'checked_out_at' => $out,
                    'expected_return' => mt_rand(1, 100) <= 6 ? days_from_today(-mt_rand(5, 40)) : null,
                    'condition_out' => 'good', 'user_id' => 1,
                ]);
            }
        }

        // ---------- Maintenance ----------
        $issues = ['Battery not holding charge', 'Overheating under load', 'Keyboard malfunction', 'Disk SMART warnings', 'Fan noise', 'Display flickering', 'No boot / POST failure', 'Network port failure', 'Power supply replaced', 'Paper jam sensor fault'];
        foreach ($endUser as $a) {
            $n = $a['model'] === 'ProDesk 600 G6' ? mt_rand(2, 4) : (mt_rand(1, 100) <= 30 ? mt_rand(1, 2) : 0);
            for ($k = 0; $k < $n; $k++) {
                $created = date('Y-m-d H:i:s', strtotime('-' . mt_rand(5, 340) . ' days'));
                $open = $a['status'] === 'in_repair' && $k === 0;
                $db->insert('maintenance', [
                    'asset_id' => $a['id'], 'type' => 'corrective', 'title' => $issues[mt_rand(0, count($issues) - 1)],
                    'status' => $open ? 'in_progress' : 'done', 'priority' => ['low', 'medium', 'high'][mt_rand(0, 2)],
                    'completed_at' => $open ? null : date('Y-m-d H:i:s', strtotime($created . ' +' . mt_rand(1, 6) . ' days')),
                    'downtime_hours' => mt_rand(2, 30), 'technician_id' => 1, 'created_at' => $open ? $now : $created,
                ]);
            }
        }
        foreach ([[$esx1, 'Firmware & BIOS update', 20], [$esx2, 'Firmware & BIOS update', 20], [$ups, 'Battery health inspection', -12], [$san, 'Controller firmware upgrade', 9], [$legacy, 'Quarterly hardware inspection', -30], [$core, 'IOS-XE patching', -3]] as [$aid, $title, $in]) {
            $db->insert('maintenance', [
                'asset_id' => $aid, 'type' => 'preventive', 'title' => $title, 'status' => 'open', 'priority' => 'high',
                'scheduled_for' => days_from_today($in), 'technician_id' => 1, 'recurrence_days' => 180, 'created_at' => $now,
            ]);
        }
        foreach ([[$legacy, 'RAID controller battery failure', 18], [$legacy, 'Disk replaced in bay 3', 6], [$ups, 'Battery module replaced', 4]] as [$aid, $title, $dt]) {
            $db->insert('maintenance', [
                'asset_id' => $aid, 'type' => 'corrective', 'title' => $title, 'status' => 'done', 'priority' => 'high',
                'completed_at' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(20, 200) . ' days')), 'downtime_hours' => $dt,
                'technician_id' => 1, 'created_at' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(30, 210) . ' days')),
            ]);
        }

        // ---------- Licenses ----------
        $lic = function (string $name, string $pub, string $type, int $seats, ?int $expiresInDays, int $used, string $edition = '') use ($db, $now, $emps, $endUser) {
            $id = $db->insert('licenses', [
                'name' => $name, 'publisher' => $pub, 'edition' => $edition, 'license_type' => $type, 'seats' => $seats,
                'expires_at' => $expiresInDays === null ? null : days_from_today($expiresInDays),
                'license_key' => strtoupper(implode('-', str_split(substr(md5($name), 0, 25), 5))), 'created_at' => $now,
            ]);
            for ($i = 0; $i < $used; $i++) {
                $perUser = $type === 'per_user';
                $db->insert('license_seats', [
                    'license_id' => $id,
                    'employee_id' => $perUser ? $emps[$i % count($emps)] : null,
                    'asset_id' => $perUser ? null : $endUser[$i % count($endUser)]['id'],
                    'last_used_at' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 120) . ' days')),
                    'assigned_at' => $now,
                ]);
            }
        };
        $lic('Microsoft 365', 'Microsoft', 'subscription', 30, 210, 24, 'E3');
        $lic('Adobe Creative Cloud', 'Adobe', 'per_user', 10, 120, 3, 'All Apps');
        $lic('AutoCAD', 'Autodesk', 'per_user', 5, 300, 6, '2026');
        $lic('Windows Server Datacenter', 'Microsoft', 'per_device', 4, null, 3, '2022');
        $lic('VMware vSphere', 'Broadcom', 'per_device', 4, 33, 2, 'Enterprise Plus');
        $lic('FortiCare Support', 'Fortinet', 'subscription', 1, 20, 1, '24x7');
        $lic('Kaspersky Endpoint Security', 'Kaspersky', 'per_device', 120, 160, 88, 'Advanced');

        // ---------- A completed physical audit ----------
        $auditId = $db->insert('audits', [
            'name' => 'Q3 Riyadh HQ inventory', 'location_id' => $locs[0], 'status' => 'closed', 'started_by' => 1,
            'started_at' => date('Y-m-d H:i:s', strtotime('-40 days')), 'closed_at' => date('Y-m-d H:i:s', strtotime('-39 days')),
        ]);
        foreach (array_slice($endUser, 0, 30) as $k => $a) {
            $db->insert('audit_items', [
                'audit_id' => $auditId, 'asset_id' => $a['id'], 'result' => $k % 11 === 5 ? 'missing' : ($k % 13 === 7 ? 'misplaced' : 'found'),
                'scanned_at' => date('Y-m-d H:i:s', strtotime('-40 days')),
            ]);
        }

        self::extras($db, $cat, $locs, $emps);
    }

    /** Demo data around the register: SLA due times, QR fault reports, custom fields, consumables, loaners, receipts and retirements. */
    private static function extras(Database $db, array $cat, array $locs, array $emps): void
    {
        $now = now();
        // SLA targets on corrective work: most finished within target, some late.
        foreach ($db->all("SELECT id, priority, status, created_at, completed_at FROM maintenance WHERE type = 'corrective'") as $i => $m) {
            $due = $m['status'] === 'done' && $m['completed_at']
                ? date('Y-m-d H:i:s', strtotime($m['completed_at']) + ($i % 7 === 3 ? -7200 : 3600))
                : Sla::dueAt($m['priority'], $m['created_at']);
            $db->update('maintenance', (int) $m['id'], ['due_at' => $due]);
        }
        // A few faults reported from QR labels, without an account.
        $reports = [
            ['LAP', 'Laptop battery drains in under an hour and the fan is very loud.', 'high', 'Visitor at reception', 'reception@example.com', 30],
            ['PRN', 'Printer shows paper jam but there is no paper inside.', 'medium', 'Second floor team', 'floor2@example.com', 5],
            ['MON', 'Screen flickers when the room lights are on.', 'low', 'Meeting room user', 'rooms@example.com', 2],
        ];
        foreach ($reports as [$code, $text, $prio, $name, $contact, $hoursAgo]) {
            $aid = $db->value("SELECT id FROM assets WHERE category_id = ? AND status = 'deployed' ORDER BY id DESC LIMIT 1", [$cat[$code]]);
            if ($aid) {
                $created = date('Y-m-d H:i:s', time() - $hoursAgo * 3600);
                $db->insert('maintenance', [
                    'asset_id' => $aid, 'type' => 'corrective', 'title' => mb_strimwidth($text, 0, 80, '…'), 'description' => $text,
                    'status' => 'open', 'priority' => $prio, 'source' => 'report', 'reporter_name' => $name, 'reporter_contact' => $contact,
                    'reporter_ip' => '192.0.2.40', 'due_at' => Sla::dueAt($prio, $created), 'created_at' => $created,
                ]);
            }
        }

        // Custom fields per category.
        $field = fn (string $code, string $label, string $type, ?string $options = null) =>
            $db->insert('custom_fields', ['category_id' => $cat[$code], 'label' => $label, 'type' => $type, 'options' => $options, 'required' => 0, 'sort' => 0]);
        $phone = $field('MOB', 'Phone number', 'text');
        $carrier = $field('MOB', 'Carrier', 'select', 'Carrier A, Carrier B, Carrier C');
        $ports = $field('NET', 'Port count', 'number');
        $encryption = $field('LAP', 'Disk encryption', 'select', 'BitLocker, FileVault, None');
        foreach ($db->all("SELECT a.id, c.code, a.manufacturer FROM assets a JOIN categories c ON c.id = a.category_id WHERE c.code IN ('MOB', 'NET', 'LAP')") as $i => $a) {
            $vals = match ($a['code']) {
                'MOB' => [$phone => '0500000' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), $carrier => ['Carrier A', 'Carrier B', 'Carrier C'][$i % 3]],
                'NET' => [$ports => (string) [24, 48, 48, 8][$i % 4]],
                default => [$encryption => $a['manufacturer'] === 'Apple' ? 'FileVault' : ($i % 9 === 4 ? 'None' : 'BitLocker')],
            };
            foreach ($vals as $fid => $v) {
                $db->insert('custom_values', ['asset_id' => $a['id'], 'field_id' => $fid, 'value' => $v]);
            }
        }

        // Accessories, consumables and spare parts.
        $items = [
            ['65 W USB-C charger', 'USBC-65W', 'power', 9, 5, 120.0],
            ['Wireless mouse', 'MS-WL-01', 'peripheral', 14, 6, 65.0],
            ['USB-C docking station', 'DOCK-USBC', 'peripheral', 3, 4, 690.0],
            ['Cat6 patch cable 2 m', 'CAT6-2M', 'cable', 48, 20, 18.0],
            ['HP 59A toner cartridge', 'CF259A', 'toner', 4, 6, 312.4],
            ['512 GB NVMe M.2 SSD', 'NVME-512', 'spare_part', 3, 4, 240.0],
            ['16 GB DDR4 SO-DIMM', 'SODIMM-16', 'spare_part', 6, 4, 185.0],
        ];
        foreach ($items as $k => [$name, $sku, $kind, $qty, $min, $cost]) {
            $opening = $qty + 4 + $k % 3;
            $id = $db->insert('consumables', [
                'name' => $name, 'sku' => $sku, 'kind' => $kind, 'location_id' => $locs[0], 'quantity' => $qty, 'min_quantity' => $min,
                'unit' => 'pcs', 'unit_cost' => $cost, 'avg_cost' => $cost, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $db->insert('consumable_moves', ['consumable_id' => $id, 'change_qty' => $opening, 'balance' => $opening, 'reason' => 'receive',
                'note' => 'Opening stock', 'unit_cost' => $cost, 'user_id' => 1, 'created_at' => date('Y-m-d H:i:s', strtotime('-90 days'))]);
            $db->insert('consumable_moves', ['consumable_id' => $id, 'change_qty' => $qty - $opening, 'balance' => $qty, 'reason' => 'issue',
                'employee_id' => $emps[$k % count($emps)], 'unit_cost' => $cost, 'user_id' => 1, 'created_at' => date('Y-m-d H:i:s', strtotime('-' . (5 + $k) . ' days'))]);
        }

        // A loaner pool: some laptops in stock lent out for days at a time.
        $pool = array_column($db->all("SELECT id FROM assets WHERE category_id = ? AND status = 'in_stock' ORDER BY id LIMIT 3", [$cat['LAP']]), 'id');
        foreach ($pool as $id) {
            $db->update('assets', (int) $id, ['loanable' => 1]);
        }
        if ($pool) {
            Loans::book($emps[2], $cat['LAP'], null, days_from_today(1), days_from_today(4), 'Workshop in Jeddah', 'portal');
            Loans::book($emps[5], $cat['LAP'], null, days_from_today(7), days_from_today(9), 'Client visit', 'staff');
        }

        // Custody receipts: one signed by remote link and one waiting for the employee.
        foreach ($db->all("SELECT a.assigned_to AS emp, a.id FROM assets a WHERE a.assigned_to IS NOT NULL ORDER BY a.id LIMIT 2") as $n => $r) {
            $emp = $db->one('SELECT * FROM employees WHERE id = ?', [(int) $r['emp']]);
            $rid = Custody::create('issue', $emp, [(int) $r['id']]);
            if ($n === 0 && function_exists('imagecreatetruecolor')) {
                Custody::sign(Custody::find($rid), self::signature(), null, (string) $emp['name'], 'link');
            }
        }

        // Two retired devices.
        foreach ($db->all("SELECT a.id, a.tag, a.name, a.serial, c.name AS category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id
            WHERE a.status = 'retired' ORDER BY a.id LIMIT 2") as $n => $a) {
            $date = days_from_today(-(10 + 20 * $n));
            $db->insert('disposals', [
                'certificate_no' => sprintf('RET-%s-%04d', substr($date, 0, 4), $n + 1), 'asset_id' => $a['id'], 'asset_tag' => $a['tag'], 'asset_name' => $a['name'],
                'serial' => $a['serial'], 'category_name' => $a['category_name'], 'reason' => $n === 0 ? 'beyond_repair' : 'end_of_life',
                'method' => $n === 0 ? 'recycle' : 'donate', 'sanitization' => 'not_applicable', 'recipient' => $n === 0 ? 'Licensed e-waste recycler' : 'Partner school',
                'disposed_at' => $date, 'user_id' => 1, 'created_at' => $now,
            ]);
        }
    }

    /** A hand-drawn-looking signature (a wavy stroke) as a PNG data URL, for the demo receipt. */
    private static function signature(): string
    {
        $img = imagecreatetruecolor(360, 120);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        $ink = imagecolorallocate($img, 20, 40, 90);
        imagesetthickness($img, 3);
        $px = 20;
        $py = 70;
        for ($x = 20; $x <= 340; $x += 4) {
            $y = (int) (70 + 28 * sin($x / 18) * cos($x / 53));
            imageline($img, $px, $py, $x, $y, $ink);
            [$px, $py] = [$x, $y];
        }
        ob_start();
        imagepng($img);
        return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
    }
}
