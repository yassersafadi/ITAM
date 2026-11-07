<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Services\Installer;

/** Six-step installation wizard. Answers are kept in the session until the last step runs the install. */
final class InstallController extends Controller
{
    public const STEPS = ['language', 'requirements', 'database', 'organization', 'admin', 'finish'];
    public const CURRENCIES = ['SAR', 'AED', 'KWD', 'QAR', 'BHD', 'OMR', 'EGP', 'JOD', 'USD', 'EUR', 'GBP'];

    public function start(): void
    {
        $this->guard();
        redirect('/install/' . ($_SESSION['install']['step'] ?? 'language'));
    }

    public function show(string $step): void
    {
        $this->guard();
        $this->reachable($step);
        $data = $_SESSION['install'] ?? [];
        $this->view('install/wizard', [
            'step' => $step, 'steps' => self::STEPS, 'data' => $data, 'errors' => $_SESSION['_install_errors'] ?? [],
            'requirements' => Installer::requirements(), 'ready' => Installer::requirementsMet(),
            'timezones' => \DateTimeZone::listIdentifiers(), 'locales' => config('i18n.locales'),
        ], 'layouts/guest');
        unset($_SESSION['_install_errors']);
    }

    public function save(string $step): void
    {
        $this->guard();
        $this->reachable($step);
        $errors = [];
        switch ($step) {
            case 'language':
                $locale = (string) input('locale');
                if (!array_key_exists($locale, config('i18n.locales'))) {
                    $errors[] = t('errors.invalid');
                    break;
                }
                \ITAM\Core\Lang::set($locale);
                $_SESSION['install']['locale'] = $locale;
                break;
            case 'requirements':
                if (!Installer::requirementsMet()) {
                    $errors[] = t('install.req_missing');
                }
                break;
            case 'database':
                $driver = input('driver') === 'mysql' ? 'mysql' : 'sqlite';
                $db = $driver === 'mysql'
                    ? ['driver' => 'mysql', 'host' => trim((string) input('host', '127.0.0.1')), 'port' => (int) input('port', 3306) ?: 3306,
                       'database' => trim((string) input('database', 'itam')), 'username' => trim((string) input('username')), 'password' => (string) ($_POST['password'] ?? '')]
                    : ['driver' => 'sqlite', 'path' => ITAM_STORAGE . '/itam.sqlite'];
                if ($driver === 'mysql' && !extension_loaded('pdo_mysql')) {
                    $errors[] = t('install.no_pdo_mysql');
                } elseif ($driver === 'mysql' && ($db['database'] === '' || !preg_match('/^[A-Za-z0-9_]+$/', $db['database']) || $db['username'] === '')) {
                    $errors[] = t('install.db_fields');
                } elseif ($err = Installer::testDatabase($db)) {
                    $errors[] = $err;
                }
                $_SESSION['install']['db'] = $db;
                break;
            case 'organization':
                $org = ['vendor' => trim((string) input('vendor')), 'name' => trim((string) input('name')), 'name_ar' => trim((string) input('name_ar')),
                    'locale' => array_key_exists((string) input('locale'), config('i18n.locales')) ? (string) input('locale') : 'en',
                    'timezone' => in_array(input('timezone'), \DateTimeZone::listIdentifiers(), true) ? (string) input('timezone') : 'Asia/Riyadh',
                    'currency' => in_array(input('currency'), self::CURRENCIES, true) ? (string) input('currency') : 'SAR'];
                if ($org['vendor'] === '' || $org['name'] === '' || $org['name_ar'] === '' || mb_strlen($org['vendor'] . $org['name'] . $org['name_ar']) > 240) {
                    $errors[] = t('install.org_fields');
                }
                $_SESSION['install']['org'] = $org;
                break;
            case 'admin':
                $admin = ['name' => trim((string) input('name')), 'email' => strtolower(trim((string) input('email')))];
                $password = (string) ($_POST['password'] ?? '');
                $min = (int) config('security.password_min', 8);
                if ($admin['name'] === '' || !filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = t('install.admin_fields');
                } elseif (mb_strlen($password) < $min || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
                    $errors[] = t('install.weak_password', ['n' => $min]);
                } elseif (!hash_equals($password, (string) ($_POST['password_confirm'] ?? ''))) {
                    $errors[] = t('install.password_mismatch');
                }
                // Kept in the session only until the install runs.
                $_SESSION['install']['admin'] = $admin + ['password' => $errors ? '' : $password];
                break;
            case 'finish':
                $a = $_SESSION['install'];
                try {
                    Installer::run(['db' => $a['db'], 'org' => $a['org'], 'admin' => $a['admin'], 'demo' => (bool) input('demo')]);
                } catch (\Throwable $e) {
                    error_log((string) $e);
                    $errors[] = t('install.failed') . ' ' . mb_substr($e->getMessage(), 0, 200);
                    break;
                }
                $email = $a['admin']['email'];
                unset($_SESSION['install']);
                flash('success', t('install.done', ['email' => $email]));
                redirect('/login');
        }
        if ($errors) {
            $_SESSION['_install_errors'] = $errors;
            redirect('/install/' . $step);
        }
        $next = self::STEPS[array_search($step, self::STEPS, true) + 1];
        $_SESSION['install']['step'] = $next;
        $_SESSION['install']['done'][$step] = true;
        redirect('/install/' . $next);
    }

    /** The wizard closes for good once the platform is installed. */
    private function guard(): void
    {
        if (!empty($GLOBALS['itam_installed'])) {
            redirect('/login');
        }
    }

    /** Steps open in order: a later step needs every earlier one completed. */
    private function reachable(string $step): void
    {
        $i = array_search($step, self::STEPS, true);
        if ($i === false) {
            abort(404);
        }
        foreach (array_slice(self::STEPS, 0, $i) as $prev) {
            if (empty($_SESSION['install']['done'][$prev])) {
                redirect('/install/' . $prev);
            }
        }
    }
}
