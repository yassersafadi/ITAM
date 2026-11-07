<?php
declare(strict_types=1);
/**
 * Scheduled jobs: escalate work orders past their due time, deliver queued notifications, remind people of unsigned
 * custody receipts and late loaners, take the nightly backup and send the weekly digest.
 *
 *   Linux cron:        15 2 * * *          php /path/to/itam/scripts/scheduler.php
 *                      0,15,30,45 * * * *  php /path/to/itam/scripts/scheduler.php --quick
 *   Windows scheduler: schtasks /Create /SC DAILY /ST 02:15 /TN "ITAM nightly" /TR "php C:\itam\scripts\scheduler.php"
 *
 * --quick runs only the frequent part (escalations and notifications).
 */

use ITAM\Services\Backup;
use ITAM\Services\Ledger;
use ITAM\Services\Notifier;
use ITAM\Services\Sla;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
if (empty($GLOBALS['itam_installed'])) {
    exit("ITAM is not installed yet: open it in a browser to run the installation wizard.\n");
}
$started = microtime(true);
Ledger::actingAs('scheduler');
$quick = in_array('--quick', $argv ?? [], true);

if ($escalated = Sla::escalate()) {
    printf("[%s] %d work orders escalated\n", now(), $escalated);
}

if (!$quick) {
    if ($expired = \ITAM\Services\Loans::expireUncollected()) {
        printf("[%s] loans: %d uncollected bookings expired\n", now(), $expired);
    }
    if ($late = \ITAM\Services\Loans::remindOverdue()) {
        printf("[%s] loans: %d overdue reminders\n", now(), $late);
    }
    if ($unsigned = \ITAM\Services\Custody::remindPending()) {
        printf("[%s] custody: %d reminders for unsigned receipts\n", now(), $unsigned);
    }
    if (config('backup.daily') && Backup::supported()) {
        try {
            printf("[%s] backup %s\n", now(), Backup::create(true));
        } catch (\RuntimeException $e) {
            printf("[%s] backup failed: %s\n", now(), $e->getMessage());   // the rest of the nightly run still goes ahead
        }
    }
    // Weekly digest on the first run of each ISO week.
    if (config('notify.weekly_digest') && Notifier::enabled() && db()->setting('digest_week') !== date('o-W')) {
        $digest = implode("\n", [
            t('digest.assets', ['n' => db()->value("SELECT COUNT(*) FROM assets WHERE status NOT IN ('retired','lost')")]),
            t('digest.open_work', ['n' => db()->value("SELECT COUNT(*) FROM maintenance WHERE status IN ('open','in_progress')"),
                'b' => db()->value("SELECT COUNT(*) FROM maintenance WHERE status IN ('open','in_progress') AND due_at < ?", [now()])]),
            t('digest.sla', ['p' => ($c = Sla::compliance(7)) === null ? '—' : fmt_num($c) . '%']),
            t('digest.consumables', ['n' => count(\ITAM\Services\Consumables::low())]),
            t('digest.warranty', ['n' => db()->value("SELECT COUNT(*) FROM assets WHERE status NOT IN ('retired','lost') AND warranty_until BETWEEN ? AND ?", [today(), days_from_today(30)])]),
        ]);
        Notifier::send(t('digest.subject', ['week' => date('o-W')]), $digest, Notifier::absolute('/'));
        db()->setSetting('digest_week', date('o-W'));
    }
    db()->setSetting('scheduler_last_run', now());
}

[$sent, $failed] = Notifier::flush(200);
if ($sent || $failed) {
    printf("[%s] notifications: %d sent, %d failed\n", now(), $sent, $failed);
}
printf("[%s] done in %.2fs\n", now(), microtime(true) - $started);
