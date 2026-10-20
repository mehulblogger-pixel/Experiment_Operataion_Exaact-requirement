<?php
// ============================================================================
//  No duplicate customers (Sales audit Stage 2.1 — SA-18).
//
//  Before: accepting a quotation typed "ACME VALVES PRIVATE LIMITED" created a
//  second customer although "Acme Valves Pvt Ltd" was on file; two people
//  converting one lead at the same moment made two customers and two
//  enquiries; converted enquiries were numbered "count + 1" and one number was
//  issued twice; Ads Pro duplicated a lead whose e-mail differed only in
//  capitals, or one typed by hand before the import.
//
//  A name is evidence, not proof (the earlier rule, Phase 6 · F5): nothing is
//  linked silently by name — the person recording the acceptance is asked.
// ============================================================================
t_section('Sales — one customer, one record');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$cmdFor = function ($uid, $route, $method, array $get, array $post, array $needles = []) use ($root, $env) {
    return $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
};
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = []) use ($cmdFor) {
    $raw = (string) shell_exec($cmdFor($uid, $route, $method, $get, $post, $needles) . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? []));
$pdo = db();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('DUP Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$BDM = $mk('dup_bdm', 'BUSINESS_DEV_MANAGER');
$BD  = $mk('dup_bd',  'BUSINESS_DIRECTOR');
$ME  = $mk('dup_me',  'MARKETING_EXECUTIVE');
$pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status) VALUES ('C-ACME-0001','Acme Valves Pvt Ltd','Acme Valves Pvt Ltd',1,'ACTIVE')")->execute();
$acme = (int) $pdo->lastInsertId();
$partners = fn() => (int) ops_val("SELECT COUNT(*) FROM business_partners");
$sent = function ($typed) use ($pdo, $BDM, $off) {
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_name, subject, office_id, subtotal, gst_pct, gst_amount, total_amount, owner_id, created_by, created_at, sent_at)
                   VALUES (?,0,1,'SENT',?,'Inspection',?,10000,18,1800,11800,?,'dup_bdm',?,?)")
        ->execute(['DUP-' . uniqid(), $typed, $off, $BDM, date('c'), date('c')]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, qty, unit, rate, amount) VALUES (?,1,'Inspection',1,'MANDAY',10000,10000)")->execute([$id]);
    return $id;
};
$row = fn($id) => ops_one("SELECT status, client_id FROM quotations WHERE id=?", [$id]);

// ---- acceptance asks, once, when a typed name looks like a customer on file ---------------
$q1 = $sent('ACME VALVES PRIVATE LIMITED');
$p0 = $partners();
$r = $drive($BDM, 'quote-status', 'POST', ['id' => $q1], ['to' => 'ACCEPTED']);
t_eq($row($q1)['status'], 'SENT', 'a quotation typed "ACME VALVES PRIVATE LIMITED" is not accepted blind while "Acme Valves Pvt Ltd" is on file');
t_ok(strpos($said($r), 'same company') !== false, '…the person recording it is asked: ' . $said($r));
$pg = $drive($BDM, 'quote', 'GET', ['id' => $q1], [], ['Yes — same company', 'No — a different company']);
t_ok(!empty($pg['has']['Yes — same company']) && !empty($pg['has']['No — a different company']), 'the quotation page offers both answers');
$drive($BDM, 'quote-status', 'POST', ['id' => $q1], ['to' => 'ACCEPTED', 'same_as' => $acme]);
t_eq([$row($q1)['status'], (int) $row($q1)['client_id']], ['ACCEPTED', $acme], '"same company": accepted against the customer already on file');
t_eq($partners(), $p0, '…and no second customer was created');

$q2 = $sent('Acme Valves Ltd.');
$drive($BDM, 'quote-status', 'POST', ['id' => $q2], ['to' => 'ACCEPTED', 'new_company' => '1']);
t_eq($row($q2)['status'], 'ACCEPTED', '"a different company": accepted');
t_eq($partners(), $p0 + 1, '…and a new customer is created, because a person said it is a different one');

$q3 = $sent('Acme Valves Pvt Ltd');
$drive($BDM, 'quote-status', 'POST', ['id' => $q3], ['to' => 'ACCEPTED']);
t_eq([$row($q3)['status'], (int) $row($q3)['client_id']], ['ACCEPTED', $acme], 'an exactly matching name is still linked without asking');

// ---- a typed enquiry name is warned about --------------------------------------------------
$r = $drive($ME, 'inquiry-new', 'POST', [], ['client_name' => 'ACME VALVES PVT. LTD.', 'contact_name' => 'Ravi', 'contact_email' => 'r@example.test',
                                             'sbu' => 'TPI', 'subject' => 'Inspection']);
t_ok(strpos($said($r), 'similar name is already on file') !== false, 'an enquiry typed for a near-match is saved with a warning naming the customer on file: ' . $said($r));

// ---- two people converting one lead at the same moment ----------------------------------------
t_as_admin();
$lead = lead_create(['company_name' => 'Race Brothers Engineering', 'contact_name' => 'Asha', 'contact_email' => 'asha@race.test']);
$lid = (int) $lead['id'];
$p1 = $partners(); $i1 = (int) ops_val("SELECT COUNT(*) FROM crm_inquiries");
$c = $cmdFor($BD, 'lead-convert', 'POST', ['id' => $lid], ['id' => $lid]);
// Each worker boots, then waits for the same start instant, so the three
// conversions really are simultaneous (see the worker's SALES_WORKER_START_AT).
$go = 'SALES_WORKER_START_AT=' . sprintf('%.3f', microtime(true) + 4) . ' ';
shell_exec('(' . $go . $c . ' > /dev/null 2>&1 & ' . $go . $c . ' > /dev/null 2>&1 & ' . $go . $c . ' > /dev/null 2>&1 & wait)');
t_eq((string) ops_val("SELECT status FROM leads WHERE id=?", [$lid]), 'CONVERTED', 'the lead is converted');
t_eq($partners(), $p1 + 1, 'three simultaneous Convert presses create ONE customer');
t_eq((int) ops_val("SELECT COUNT(*) FROM crm_inquiries") - $i1, 1, '…and one enquiry');
$lead2 = lead_create(['company_name' => 'Stale Claim Works']);
$pdo->prepare("UPDATE leads SET converted_at=? WHERE id=?")->execute([date('c', time() - 600), (int) $lead2['id']]);
t_ok(empty(lead_convert((int) $lead2['id'])['err']), 'a conversion that died half-way ten minutes ago does not leave the lead stuck');

// ---- converted enquiries take the shared numbering -----------------------------------------------
$nos = array_column(ops_all("SELECT inquiry_no FROM crm_inquiries") ?: [], 'inquiry_no');
t_eq(count($nos), count(array_unique($nos)), 'no enquiry number is issued twice');

// ---- Ads Pro does not duplicate a lead -----------------------------------------------------------
$typed = lead_create(['company_name' => 'Hand Typed Pumps', 'contact_name' => 'Meera', 'contact_email' => 'meera@pumps.test']);
t_eq(ads_existing_lead(['email' => '  MEERA@Pumps.TEST '], 'Someone else'), (int) $typed['id'],
     'an imported contact whose e-mail differs only in capitals and spaces is matched to the lead already here');
t_eq(ads_existing_lead(['email' => 'new@person.test'], 'Hand Typed Pumps'), (int) $typed['id'], '…as is one for a company already being chased');
t_eq(ads_existing_lead(['email' => 'new@person.test'], 'Brand New Co'), 0, 'control: someone genuinely new is not matched');
if (function_exists('ads_make_lead')) {
    $l0 = (int) ops_val("SELECT COUNT(*) FROM leads");
    $res = ads_make_lead(['email' => 'MEERA@pumps.test', 'name' => 'Meera', 'company' => 'Hand Typed Pumps'], 'ext-dup-1');
    t_eq((int) ops_val("SELECT COUNT(*) FROM leads"), $l0, 'importing that contact links it to the existing lead instead of adding one');
    t_eq((int) ($res['linked'] ?? 0), (int) $typed['id'], '…and says which lead it linked to');
}
