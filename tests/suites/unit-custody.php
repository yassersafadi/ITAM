<?php
// Custody receipts: handovers join the employee's unsigned receipt, a signed one is never touched again,
// and a signature sent for an older version of the receipt is refused.
use ITAM\Services\Custody;

return function (): void {
    $db = db();
    $emp = ['id' => $db->insert('employees', ['name' => 'UT Custody', 'email' => 'ut-custody@tests.local', 'active' => 1]), 'name' => 'UT Custody'];
    $assets = array_map('intval', array_column($db->all('SELECT id FROM assets ORDER BY id LIMIT 3'), 'id'));
    $ids = [];
    try {
        $ids[] = $first = Custody::create('issue', $emp, [$assets[0]], [], 'Laptop');
        $seen = Custody::contentKey(Custody::find($first));
        $second = Custody::create('issue', $emp, [$assets[1], $assets[0]], ['Office 365'], 'Charger');
        $r = Custody::find($first);
        check('a handover joins the unsigned receipt of the same employee', $second === $first);
        check('with the new items once each, and both notes', count($r['items']) === 3 && $r['items'][1]['id'] === $assets[1] && $r['items'][2]['name'] === 'Office 365'
            && $r['notes'] === "Laptop\nCharger", json_encode($r['items']) . ' ' . $r['notes']);
        $ids[] = $return = Custody::create('return', $emp, [$assets[2]]);
        check('a return gets its own receipt', $return !== $first);

        // An item added by mistake comes off; the last one cannot (the receipt is cancelled instead).
        Custody::removeItem($r, 'lOffice 365');
        $r = Custody::find($first);
        check('an item comes off an unsigned receipt', count($r['items']) === 2 && !in_array('license', array_column($r['items'], 'kind'), true));
        $last = false;
        try {
            Custody::removeItem(Custody::find($return), 'a' . $assets[2]);
        } catch (\InvalidArgumentException $e) {
            $last = $e->getMessage() === t('custody.last_item');
        }
        check('but not the last item', $last && count(Custody::find($return)['items']) === 1);

        // Late receipts: the employee is reminded with a fresh link, then not again the next night; the IT team is told once a day.
        $hadLate = (bool) $db->value('SELECT 1 FROM notifications WHERE fingerprint = ?', ['custody_late:' . today()]);
        $db->update('custody_receipts', $return, ['created_at' => days_from_today(-5) . ' 09:00:00', 'token_expires' => days_from_today(-1) . ' 09:00:00']);
        $oldToken = (string) $db->value('SELECT sign_token FROM custody_receipts WHERE id = ?', [$return]);
        Custody::remindPending();
        $rr = $db->one('SELECT * FROM custody_receipts WHERE id = ?', [$return]);
        check('a late receipt is reminded with a renewed link', $rr['reminded_at'] && $rr['sign_token'] !== $oldToken && $rr['token_expires'] > now());
        check('and the IT team gets one notice for the day', (int) $db->value('SELECT COUNT(*) FROM notifications WHERE fingerprint = ?', ['custody_late:' . today()]) === 1);
        Custody::remindPending();
        check('no second reminder the next run', $db->value('SELECT reminded_at FROM custody_receipts WHERE id = ?', [$return]) === $rr['reminded_at']
            && (int) $db->value('SELECT COUNT(*) FROM notifications WHERE fingerprint = ?', ['custody_late:' . today()]) === 1);
        if (!$hadLate) {
            $db->run('DELETE FROM notifications WHERE fingerprint = ?', ['custody_late:' . today()]);
        }

        $png = static function (): string {
            $img = imagecreatetruecolor(200, 80);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
            imagesavealpha($img, true);
            imagesetthickness($img, 3);
            imageline($img, 10, 10, 190, 70, imagecolorallocate($img, 0, 0, 0));
            imageline($img, 10, 70, 190, 10, imagecolorallocate($img, 0, 0, 0));
            ob_start();
            imagepng($img);
            return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
        };
        $stale = false;
        try {
            Custody::sign($r, $png(), null, 'UT Custody', 'link', $seen);
        } catch (\InvalidArgumentException $e) {
            $stale = $e->getMessage() === t('custody.changed');
        }
        check('a signature for an older version of the receipt is refused', $stale && Custody::find($first)['status'] === 'pending');
        Custody::sign($r, $png(), null, 'UT Custody', 'link', Custody::contentKey($r));
        check('the current version signs', Custody::find($first)['status'] === 'signed' && Custody::intact(Custody::find($first)));
        $ids[] = $third = Custody::create('issue', $emp, [$assets[2]]);
        check('after signing, the next handover starts a new receipt', $third !== $first && count(Custody::find($first)['items']) === 2);
    } finally {
        foreach ($ids as $id) {
            foreach (['employee_signature', 'staff_signature'] as $col) {
                $file = (string) $db->value("SELECT $col FROM custody_receipts WHERE id = ?", [$id]);
                if ($file !== '' && is_file(Custody::dir() . '/' . basename($file))) {
                    unlink(Custody::dir() . '/' . basename($file));
                }
            }
        }
        $db->run('DELETE FROM custody_receipts WHERE employee_id = ?', [$emp['id']]);
        $db->run("DELETE FROM outbox WHERE recipient = 'ut-custody@tests.local'");
        $db->delete('employees', (int) $emp['id']);
    }
};
