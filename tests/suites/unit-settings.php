<?php
// Settings: setSetting is one atomic upsert, so two overlapping scheduler runs writing monitor_last_run
// never collide on the primary key (it used to delete then insert, and MySQL rejected the second insert).
return function (): void {
    $count = fn () => (int) db()->value("SELECT COUNT(*) FROM settings WHERE skey = 'ttest_key'");
    db()->setSetting('ttest_key', 'a');
    check('new setting inserted', db()->setting('ttest_key') === 'a' && $count() === 1);
    db()->setSetting('ttest_key', 'a');
    check('same value written again keeps one row', db()->setting('ttest_key') === 'a' && $count() === 1);
    db()->setSetting('ttest_key', 'b');
    check('changed value updated in place', db()->setting('ttest_key') === 'b' && $count() === 1);

    // Another process inserting the key between a delete and an insert: a trigger plays that process
    // (SQLite only: a MySQL trigger cannot write to its own table).
    if (db()->driver === 'mysql') {
        db()->run("DELETE FROM settings WHERE skey = 'ttest_key'");
        return;
    }
    db()->run("CREATE TRIGGER ttest_race AFTER DELETE ON settings WHEN old.skey = 'ttest_key'
        BEGIN INSERT INTO settings (skey, svalue) VALUES ('ttest_key', 'other'); END");
    try {
        db()->setSetting('ttest_key', 'c');
        check('overlapping writer does not break setSetting', db()->setting('ttest_key') === 'c' && $count() === 1);
    } catch (\PDOException $e) {
        check('overlapping writer does not break setSetting', false, $e->getMessage());
    } finally {
        db()->run('DROP TRIGGER ttest_race');
        db()->run("DELETE FROM settings WHERE skey = 'ttest_key'");
    }
};
