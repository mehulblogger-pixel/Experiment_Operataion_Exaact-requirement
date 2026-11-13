<?php
// ============================================================================
//  An enquiry follows its quotation: Open → Quoted → Won / Lost. Only Dropped is
//  chosen by hand, with a reason, and only while no quotation exists.
//
//  Owner, 13 Nov 2026: "Why inquiries does not have any pipeline or anything either
//  like won or lost?" — answered "Automatic Won/Lost (Recommended)" and "Only
//  'Dropped', with a reason (Recommended)".
// ============================================================================
t_section('Enquiry outcome — it follows its quotation');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode(array_values($needles)));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? [])) . (!empty($r['error']) ? ' [' . $r['error'] . ']' : '');

$pdo = db(); crm_migrate(); if (function_exists('act_migrate')) act_migrate();
$tag = 'ioc' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['IOC Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
    ->execute([$tag . '_mex', 'Mira', 'Ioc', '', 'MARKETING_EXECUTIVE', $off]);
$MEX = (int) $pdo->lastInsertId();
$mkInq = function ($subject) use ($pdo, $tag, $off) {
    $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, client_name, contact_name, contact_mobile, subject, sbu, source, status, office_id, created_by, created_at)
                   VALUES (?,?,?,?,?,'IND','EMAIL','OPEN',?,'test',?)")->execute([strtoupper($tag) . '-' . mt_rand(1000, 99999), 'IOC Client', 'Ravi', '9800000000', $subject, $off, date('c')]);
    return (int) $pdo->lastInsertId();
};
$mkQ = function ($inq, $status) use ($pdo, $tag) {
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, inquiry_id, client_name, status, created_at) VALUES (?,0,1,?,?,?,?)")
        ->execute([strtoupper($tag) . 'Q' . mt_rand(1000, 99999), $inq, 'IOC Client', $status, date('c')]);
    return (int) $pdo->lastInsertId();
};
$st = fn($id) => (string) ops_val("SELECT status FROM crm_inquiries WHERE id=?", [$id]);
$made = [];

try {
    t_eq(array_keys(INQUIRY_STATUS), ['OPEN', 'QUOTED', 'WON', 'LOST', 'DROPPED'], 'the statuses: Open, Quoted, Won, Lost, Dropped');

    $i1 = $made[] = $mkInq('Follows its quotation');
    $q1 = $mkQ($i1, 'DRAFT');
    t_eq(crm_inquiry_sync($i1), 'QUOTED', 'a quotation drafted from it: Quoted');
    foreach (['PENDING_APPROVAL', 'APPROVED', 'SENT', 'REJECTED'] as $s) {
        $pdo->prepare("UPDATE quotations SET status=? WHERE id=?")->execute([$s, $q1]);
        t_eq(crm_inquiry_sync($i1), 'QUOTED', '…still Quoted while its quotation is ' . strtolower($s));
    }
    $pdo->prepare("UPDATE quotations SET status='ACCEPTED' WHERE id=?")->execute([$q1]);
    crm_log_change($q1, 'accepted (test)');
    t_eq($st($i1), 'WON', 'its quotation accepted: Won — set by itself the moment the quotation records the change');

    $i2 = $made[] = $mkInq('Lost');
    $q2 = $mkQ($i2, 'LOST');
    t_eq(crm_inquiry_sync($i2), 'LOST', 'its only quotation lost: Lost');
    $pdo->prepare("UPDATE quotations SET status='EXPIRED' WHERE id=?")->execute([$q2]);
    t_eq(crm_inquiry_sync($i2), 'LOST', '…or expired: Lost');
    $q2b = $mkQ($i2, 'SENT');
    t_eq(crm_inquiry_sync($i2), 'QUOTED', 'a second quotation sent for it: Quoted again — one lost does not end it while another is out');
    $pdo->prepare("UPDATE quotations SET status='ACCEPTED' WHERE id=?")->execute([$q2b]);
    t_eq(crm_inquiry_sync($i2), 'WON', 'and that one accepted: Won');
    $pdo->prepare("UPDATE quotations SET is_current=0 WHERE id=?")->execute([$q2b]);
    $pdo->prepare("UPDATE quotations SET status='LOST' WHERE id=?")->execute([$q2]);
    t_eq(crm_inquiry_sync($i2), 'LOST', 'only the current revision counts — an old revision does not keep it Won');

    $i3 = $made[] = $mkInq('Quotation removed');
    $q3 = $mkQ($i3, 'DRAFT'); crm_inquiry_sync($i3);
    $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$q3]);
    t_eq(crm_inquiry_sync($i3), 'OPEN', 'its only quotation removed: back to Open, never left saying Quoted');
    t_ok(str_contains((string) ops_val("SELECT subject FROM activities WHERE entity_kind='INQUIRY' AND entity_id=? ORDER BY id DESC LIMIT 1", [$i3]), 'is now open'),
        'every change of outcome is written to the enquiry\'s history');

    //  Something recorded before this existed is brought up to date when the register opens.
    $i4 = $made[] = $mkInq('Old record');
    $mkQ($i4, 'ACCEPTED');
    $pdo->prepare("UPDATE crm_inquiries SET status='QUOTED' WHERE id=?")->execute([$i4]);
    crm_inquiries_sync_all();
    t_eq($st($i4), 'WON', 'an enquiry left at "Quoted" from before is corrected to Won when the register is opened');

    t_section('Enquiry outcome — only Dropped is chosen by hand, with a reason');
    t_ok(in_array('mod.inquiries.edit', role_perms('MARKETING_EXECUTIVE') ?: [], true), 'ARMING · a Marketing Executive may edit enquiries');
    $i5 = $made[] = $mkInq('To drop');
    $r = $drive($MEX, 'inquiry-drop', 'POST', [], ['id' => $i5, 'drop_reason' => '']);
    t_ok(stripos($said($r), 'say why') !== false && $st($i5) === 'OPEN', 'no reason: refused — "Say why it is dropped"');
    $r = $drive($MEX, 'inquiry-drop', 'POST', [], ['id' => $i5, 'drop_reason' => 'DECLINED', 'drop_note' => 'Outside our scope']);
    $row = ops_one("SELECT status, drop_reason, drop_note, dropped_by FROM crm_inquiries WHERE id=?", [$i5]);
    t_eq([$row['status'], $row['drop_reason'], $row['drop_note'], $row['dropped_by']], ['DROPPED', 'DECLINED', 'Outside our scope', 'Mira Ioc'],
        'dropped with a reason and a note, and who did it: ' . $said($r));
    t_eq(crm_inquiry_sync($i5), 'DROPPED', 'a dropped enquiry with no quotation stays dropped');
    $r = $drive($MEX, 'inquiry-reopen', 'POST', [], ['id' => $i5]);
    t_ok($st($i5) === 'OPEN' && (string) ops_val("SELECT drop_reason FROM crm_inquiries WHERE id=?", [$i5]) === '', 'reopened: Open again, its reason cleared: ' . $said($r));
    $r = $drive($MEX, 'inquiry-drop', 'POST', [], ['id' => $i1, 'drop_reason' => 'WITHDRAWN']);
    t_ok(stripos($said($r), 'follows that') !== false && $st($i1) === 'WON', 'an enquiry with a quotation cannot be dropped — it follows the quotation: ' . $said($r));

    //  The edit form no longer sets the status.
    $inq = crm_inquiry_get($i4);
    $r = $drive($MEX, 'inquiry-edit', 'POST', ['id' => $i4], ['client_name' => 'IOC Client', 'contact_name' => 'Ravi', 'contact_mobile' => '9800000000',
        'sbu' => 'IND', 'subject' => 'Old record — edited', 'source' => 'EMAIL', 'status' => 'DROPPED']);
    t_ok($st($i4) === 'WON' && (string) ops_val("SELECT subject FROM crm_inquiries WHERE id=?", [$i4]) === 'Old record — edited',
        'saving the form keeps the status that follows the quotation, even if a status is sent — the edit itself is saved');

    t_section('Enquiry outcome — the screens');
    //  Opened straight from a link (not via the register): the page brings itself up to date.
    $i6 = $made[] = $mkInq('Opened directly'); $mkQ($i6, 'ACCEPTED');
    $g = $drive($MEX, 'inquiry-edit', 'GET', ['id' => $i6], [], ['Won — its ']);
    t_ok(!empty($g['has']['Won — its ']) && $st($i6) === 'WON', 'an enquiry page opened directly shows Won at once — it does not wait for the register');
    $g = $drive($MEX, 'inquiry-edit', 'GET', ['id' => $i1], [], ['name="status"', 'Where it stands', 'Won — its ', 'History of this ']);
    t_ok(empty($g['has']['name="status"']), 'the enquiry form has no status dropdown');
    t_ok(!empty($g['has']['Where it stands']) && !empty($g['has']['Won — its ']) && !empty($g['has']['History of this ']), 'it says where the enquiry stands, and keeps its history');
    $g = $drive($MEX, 'inquiry-edit', 'GET', ['id' => $i5], [], ['Drop this ', 'name="drop_reason"']);
    t_ok(!empty($g['has']['Drop this ']) && !empty($g['has']['name="drop_reason"']), 'an open enquiry with no quotation offers "Drop this enquiry" with a reason to pick');
    $g = $drive($MEX, 'inquiries', 'GET', ['fy' => 'ALL', 'q' => strtoupper($tag)], [], ['Enquiries</div>', 'Won</div>', 'of enquiries became orders', 'Hit rate']);
    t_ok(!empty($g['has']['Enquiries</div>']) && !empty($g['has']['of enquiries became orders']) && !empty($g['has']['Hit rate']),
        'the register shows what became of the enquiries — quoted, won, lost, dropped, and the hit rate');
    t_eq(ads_status_out('INQUIRY', ['status' => 'WON']), 'customer', 'the ads sync is told a won enquiry is a customer');
} finally {
    foreach ($made as $id) {
        $pdo->prepare("DELETE FROM activities WHERE entity_kind='INQUIRY' AND entity_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM quotations WHERE inquiry_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM crm_inquiries WHERE id=?")->execute([$id]);
    }
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$MEX]);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
