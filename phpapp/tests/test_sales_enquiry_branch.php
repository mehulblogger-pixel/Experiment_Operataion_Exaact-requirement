<?php
// ============================================================================
//  An enquiry belongs to the branch of the person who recorded it — the owner's
//  answer of 20 Oct 2026 ("Yes"). Before: crm_inquiries had no branch at all, so
//  every branch saw, edited and quoted every other branch's enquiries, while the
//  quotation raised from one was branch-scoped (Sales audit, enquiry scope).
//  An enquiry with no branch — older rows, website imports — stays visible to
//  every branch, as an unassigned lead does, so nothing is lost.
//  Also: converting a lead wrote the owner's NAME into the enquiry's assigned_to
//  (a person's id), so a converted enquiry showed nobody as its owner.
// ============================================================================
t_section('Sales — an enquiry belongs to the branch that recorded it');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? []));
$pdo = db();
$office = function ($name) use ($pdo) { $pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute([$name]); return (int) $pdo->lastInsertId(); };
$A = $office('EB North'); $B = $office('EB South');
$mk = function ($un, $role, $off) use ($pdo) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$NA = $mk('eb_north', 'BUSINESS_DEV_MANAGER', $A);
$SB = $mk('eb_south', 'BUSINESS_DEV_MANAGER', $B);
$BD = $mk('eb_dir',   'BUSINESS_DIRECTOR',   $A);   // the whole company
$new = fn($uid, $subj) => $drive($uid, 'inquiry-new', 'POST', [], ['client_name' => 'EB Prospect ' . $subj, 'contact_name' => 'Ravi',
        'contact_email' => 'r@eb.test', 'sbu' => 'TPI', 'subject' => $subj, 'received_date' => date('Y-m-d', time() - 40 * 86400)]);
$row = fn($subj) => ops_one("SELECT * FROM crm_inquiries WHERE subject=?", [$subj]);

// ---- recorded with the recorder's branch -----------------------------------------------------
$r = $new($NA, 'North tank inspection');
$n = $row('North tank inspection');
t_ok($n !== null, 'arranged: North records an enquiry (' . $said($r) . ')');
t_eq((int) $n['office_id'], $A, 'it belongs to the branch of the person who recorded it');

// ---- other branches do not see or work it -------------------------------------------------------
$pg = $drive($SB, 'inquiries', 'GET', ['fy' => 'all'], [], [$n['inquiry_no']]);
t_ok(empty($pg['has'][$n['inquiry_no']]), 'South\'s enquiry register does not list North\'s enquiry');
$pg = $drive($NA, 'inquiries', 'GET', ['fy' => 'all'], [], [$n['inquiry_no'], 'EB North']);
t_ok(!empty($pg['has'][$n['inquiry_no']]) && !empty($pg['has']['EB North']), 'North\'s register lists it, with its branch');
$pg = $drive($BD, 'inquiries', 'GET', ['fy' => 'all'], [], [$n['inquiry_no']]);
t_ok(!empty($pg['has'][$n['inquiry_no']]), 'the Business Director, who sees every branch, sees it');
$drive($SB, 'inquiry-edit', 'POST', ['id' => $n['id']], ['client_name' => 'Hijacked', 'contact_name' => 'X', 'contact_email' => 'x@x.test',
        'sbu' => 'TPI', 'subject' => 'North tank inspection']);
t_eq((string) $row('North tank inspection')['client_name'], 'EB Prospect North tank inspection', 'South cannot edit North\'s enquiry');
$pg = $drive($SB, 'inquiry-edit', 'GET', ['id' => $n['id']], [], ['name="contact_name"']);
t_ok(empty($pg['has']['name="contact_name"']), '…nor open its form');
$pg = $drive($NA, 'inquiry-edit', 'GET', ['id' => $n['id']], [], ['name="contact_name"']);
t_ok(!empty($pg['has']['name="contact_name"']), 'control: North opens the same form');
$pg = $drive($SB, 'quote-new', 'GET', ['inquiry' => $n['id']], [], ['EB Prospect North tank inspection']);
t_ok(empty($pg['has']['EB Prospect North tank inspection']), 'a quotation started from it in South is not filled from North\'s enquiry');
$_SESSION['uid'] = $SB; current_user(true); ua(true);
$due = array_column(inquiries_due(), 'id');
t_ok(!in_array((int) $n['id'], array_map('intval', $due), true), 'it is not in South\'s "enquiries waiting for a quote" count');
$_SESSION['uid'] = $NA; current_user(true); ua(true);
t_ok(in_array((int) $n['id'], array_map('intval', array_column(inquiries_due(), 'id')), true), '…but is in North\'s');
unset($_SESSION['uid']); current_user(true); ua(true);

// ---- an enquiry with no branch is everyone's ------------------------------------------------------
$pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, client_name, subject, sbu, status, created_by, created_at) VALUES ('INQ-EB-OLD','Old Co','Older enquiry','TPI','OPEN','x',?)")
    ->execute([date('c')]);
$pg = $drive($SB, 'inquiries', 'GET', ['fy' => 'all'], [], ['INQ-EB-OLD', 'No branch — every branch sees it']);
t_ok(!empty($pg['has']['INQ-EB-OLD']) && !empty($pg['has']['No branch — every branch sees it']), 'an older enquiry with no branch stays visible to every branch, and says so');

// ---- a converted lead's enquiry -----------------------------------------------------------------
t_as_admin();
$lead = lead_create(['company_name' => 'EB Lead Works', 'contact_name' => 'Asha', 'contact_email' => 'asha@eb.test']);
$pdo->prepare("UPDATE leads SET office_id=?, owner_user_id=?, owner_name='Eb_south Probe' WHERE id=?")->execute([$B, $SB, (int) $lead['id']]);
$res = lead_convert((int) $lead['id']);
$ci = ops_one("SELECT * FROM crm_inquiries WHERE lead_id=? OR notes LIKE ?", [(int) $lead['id'], '%' . $lead['ref'] . '%']);
t_ok($ci !== null, 'arranged: the lead is converted (' . ($res['err'] ?? 'ok') . ')');
t_eq((int) $ci['office_id'], $B, 'the enquiry takes the lead\'s branch');
t_eq((int) $ci['assigned_to'], $SB, 'and is assigned to the lead\'s owner — it was given their name, so it showed nobody');
