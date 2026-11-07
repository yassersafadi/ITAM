<?php
// A copy that is not the live platform (a test copy of live data) holds every outgoing e-mail: queued messages are kept, never sent.
use ITAM\Services\Notifier;

return function (): void {
    $db = db();
    $saved = $GLOBALS['itam_config']['app']['outbound'] ?? true;
    $GLOBALS['itam_config']['app']['outbound'] = false;
    $id = $db->insert('outbox', ['channel' => 'email', 'recipient' => 'held@tests.local', 'subject' => 'Held', 'body' => 'x', 'created_at' => now()]);
    try {
        check('the switch is read from the configuration', Notifier::outbound() === false);
        Notifier::flush();
        $m = $db->one('SELECT status, error, sent_at FROM outbox WHERE id = ?', [$id]);
        check('a queued message is kept as skipped, with the reason, and never sent', $m['status'] === 'skipped' && $m['error'] === Notifier::HELD && $m['sent_at'] === null, json_encode($m));
        $GLOBALS['itam_config']['app']['outbound'] = true;
        check('the live platform sends as before', Notifier::outbound());
    } finally {
        $GLOBALS['itam_config']['app']['outbound'] = $saved;
        $db->delete('outbox', $id);
    }
};
