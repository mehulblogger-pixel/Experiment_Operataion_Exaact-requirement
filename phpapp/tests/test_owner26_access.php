<?php
// Owner, 26 Nov 2026 — "go ahead and fix everything as recommended" (Sales test results).
//
//   B1 (R-04 / R-06)  The Branch Manager and the SBU Head read and download leads and deals
//                     (owner, 21 Nov 2026) but could still ADD a lead through the Quick lead
//                     page that the Sales home "New lead" tile opens. Refused now; the tiles
//                     they cannot use are not shown; the lead page offers no Delete they lack.
//   B2 (LP-20)        A lead, deal or enquiry is never handed to someone who could not open it
//                     (another branch / unit); the pick-lists show only people who could.
//   B3 (R-11)         "My team" lists only the reportees who do sales work.
//   B4 (DL-20)        A deletion is recorded as "Deleted" (a system entry, the record's branch),
//                     and Directory → Activity keeps to the viewer's branches.
//   B5 (J1-05)        Converting a lead onto a client already on file hides the GSTIN boxes.
//   B6                Whose-records changes are named in the person's Recent changes; the
//                     add-a-person form no longer promises fine-tuning a company role.
t_section('Owner 26 Nov 2026 — access and records (B1–B6)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$made = ['l' => [], 'o' => [], 'i' => [], 'u' => [], 'p' => []];
putenv('BBV_RAW=1');
try {
    bbv_as($f['master']); leads_migrate(); opp_migrate();
    if (function_exists('crm_migrate')) crm_migrate();
    if (function_exists('act_migrate')) act_migrate();
    $mkUser = function ($key, $role, $office, $reportsTo = null) use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, reports_to_id) VALUES (?,?,?,?,1,?,'',?)")
            ->execute([$f['tag'] . '_' . $key, ucfirst($key), 'T', $role, $office, $reportsTo]);
        $id = (int) $pdo->lastInsertId(); $made['u'][] = $id; return $id;
    };
    $bdmMum = $mkUser('bdmmum', 'BUSINESS_DEV_MANAGER', $f['mum'], $f['kiran']);
    $meMum  = $mkUser('mexmum', 'MARKETING_EXECUTIVE', $f['mum'], $f['kiran']);
    $meAhm  = $mkUser('mexahm', 'MARKETING_EXECUTIVE', $f['ahm']);
    $pdo->prepare("UPDATE users SET scope_sbus='IND' WHERE id=?")->execute([$f['sbu']]);
    $refused = fn($r) => !empty($r['restricted']) || (int) ($r['code'] ?? 0) === 403
                        || (bool) array_filter($r['flash'] ?? [], fn($x) => stripos($x, 'error') === 0);
    $html = fn($r) => (string) ($r['html'] ?? '');

    // ---- B1 · nobody reads-only adds a lead ------------------------------------------------
    foreach (['kiran' => 'Branch Manager', 'sbu' => 'SBU Head'] as $k => $who) {
        $name = 'B1 ' . $who . ' ' . uniqid();
        $r = bbv_route('sales-lead', 'POST', $f[$k], 0, ['company_name' => $name, 'contact_phone' => '9825033333', 'source' => 'REFERRAL', 'confirm' => '1']);
        t_ok($refused($r), "B1 · $who · the Quick lead form refuses to save a lead");
        t_eq((int) ops_val("SELECT COUNT(*) FROM leads WHERE company_name=?", [$name]), 0, "B1 · $who · no lead was created");
        $r = bbv_route('sales-lead', 'GET', $f[$k], 0);
        t_ok($refused($r), "B1 · $who · the Quick lead page itself is refused");
        $r = bbv_route('sales-home', 'GET', $f[$k], 0);
        $h = $html($r);
        t_ok(!$refused($r) && $h !== '', "B1 · $who · Sales home still opens");
        t_ok(strpos($h, 'href="/sales-lead"') === false, "B1 · $who · Sales home shows no New lead shortcut");
        t_ok(strpos($h, 'href="/opportunity-new"') === false, "B1 · $who · Sales home shows no New deal shortcut");
        t_ok(strpos($h, '>Log activity<') === false && strpos($h, 'Log activity</a>') === false, "B1 · $who · Sales home shows no Log activity shortcut");
        t_ok(strpos($h, 'href="/sales-orders"') !== false, "B1 · $who · the reading shortcut Orders won is still there");
    }
    $name = 'B1 BDM ' . uniqid();
    $r = bbv_route('sales-lead', 'POST', $bdmMum, 0, ['company_name' => $name, 'contact_phone' => '9825044444', 'source' => 'REFERRAL', 'confirm' => '1']);
    $bid = (int) ops_val("SELECT id FROM leads WHERE company_name=?", [$name]);
    if ($bid) $made['l'][] = $bid;
    t_ok($bid > 0, 'B1 · a Business Development Manager still saves a quick lead');
    $r = bbv_route('sales-home', 'GET', $bdmMum, 0);
    t_ok(strpos($html($r), 'href="/sales-lead"') !== false && strpos($html($r), 'href="/opportunity-new"') !== false, 'B1 · the BDM still sees New lead and New deal on Sales home');
    //  The lead page offers Delete only to someone the server lets delete.
    $lm = lead_create(['company_name' => 'B1 delete ' . uniqid(), 'contact_phone' => '9825055555', 'source' => 'REFERRAL']);
    $pdo->prepare("UPDATE leads SET office_id=?, sbu='IND' WHERE id=?")->execute([$f['mum'], $lm['id']]); $made['l'][] = (int) $lm['id'];
    $r = bbv_route('lead', 'GET', $f['kiran'], (int) $lm['id']);
    t_ok(!$refused($r) && strpos($html($r), 'Delete this lead') === false && strpos($html($r), 'Remove this lead') === false, 'B1 · the Branch Manager is not offered "Delete this lead"');
    $r = bbv_route('lead', 'GET', $f['admin'], (int) $lm['id']);
    t_ok(strpos($html($r), 'Remove this lead') !== false, 'B1 · an administrator with the delete right still has it');
    bbv_as($f['kiran']);
    t_ok(!(leads_can_edit() && leads_works_leads()), 'B1 · the rule behind it: the Branch Manager does not work leads');

    // ---- B2 · hand-over only to someone who could open it ---------------------------------
    bbv_as($f['master']);
    $l2 = lead_create(['company_name' => 'B2 lead ' . uniqid(), 'contact_phone' => '9825066666', 'source' => 'REFERRAL']);
    $pdo->prepare("UPDATE leads SET office_id=?, sbu='IND', owner_user_id=? WHERE id=?")->execute([$f['mum'], $bdmMum, $l2['id']]); $made['l'][] = (int) $l2['id'];
    $r = lead_change_owner((int) $l2['id'], $meAhm, 'Moving it');
    t_ok(!empty($r['err']) && strpos($r['err'], 'does not work for the Mumbai') !== false && strpos($r['err'], 'Choose someone from that branch') !== false,
         'B2 · a Mumbai lead cannot go to an Ahmedabad person — the reason is named');
    t_eq((int) ops_val("SELECT owner_user_id FROM leads WHERE id=?", [$l2['id']]), $bdmMum, 'B2 · the lead stays with its owner');
    $r = lead_change_owner((int) $l2['id'], $meMum, 'Moving it');
    t_ok(empty($r['err']), 'B2 · it can go to a Mumbai person');
    t_ok(sales_handover_problem($f['sbu'], $f['mum'], 'OGC', 'lead') !== '' && strpos(sales_handover_problem($f['sbu'], $f['mum'], 'OGC', 'lead'), 'business unit') !== false,
         'B2 · an SBU Head of Industrial cannot be given an Oil & Gas record — the unit is named');
    t_eq(sales_handover_problem($f['master'], $f['ahm'], 'OGC', 'lead'), '', 'B2 · a Master Admin can be given anything');
    $r = bbv_route('lead', 'GET', $f['master'], (int) $l2['id']);
    $h = $html($r);
    t_ok(strpos($h, 'Mexmum') !== false && strpos($h, 'Mexahm') === false, 'B2 · the lead page\'s "Handled by" list offers Mumbai people, not Ahmedabad ones');
    //  A new lead given at once to someone of another branch.
    $r = lead_create(['company_name' => 'B2 new ' . uniqid(), 'contact_phone' => '9825077777', 'source' => 'REFERRAL', 'office_id' => $f['mum'], 'owner_user_id' => $meAhm]);
    if (!empty($r['id'])) $made['l'][] = (int) $r['id'];
    t_ok(!empty($r['err']) && strpos($r['err'], 'does not work for the Mumbai') !== false, 'B2 · a new lead cannot be allocated at once to another branch\'s person');
    //  Deals.
    $pipe = pipeline_default('OPPORTUNITY');
    $o = opp_create(['name' => 'B2 deal ' . uniqid(), 'partner_name' => 'B2 Co', 'value' => 100000, 'pipeline_id' => $pipe['id']]);
    $pdo->prepare("UPDATE opportunities SET office_id=?, sbu='IND', owner_user_id=? WHERE id=?")->execute([$f['mum'], $bdmMum, $o['id']]); $made['o'][] = (int) $o['id'];
    $err = opp_update((int) $o['id'], ['owner_user_id' => $meAhm]);
    t_ok(is_string($err) && strpos($err, 'does not work for the Mumbai') !== false, 'B2 · a Mumbai deal cannot be given to an Ahmedabad person');
    t_eq((int) ops_val("SELECT owner_user_id FROM opportunities WHERE id=?", [$o['id']]), $bdmMum, 'B2 · the deal stays with its owner');
    $err = opp_update((int) $o['id'], ['office_id' => $f['ahm']]);
    t_ok(is_string($err) && strpos($err, 'does not work for the Ahmedabad') !== false, 'B2 · moving the deal to Ahmedabad while a Mumbai person holds it is refused');
    t_eq((int) ops_val("SELECT office_id FROM opportunities WHERE id=?", [$o['id']]), (int) $f['mum'], 'B2 · the deal stays in Mumbai');
    $err = opp_update((int) $o['id'], ['owner_user_id' => $meMum]);
    t_ok(!$err, 'B2 · it can be given to a Mumbai person');
    $r = bbv_route('opportunity', 'GET', $f['master'], (int) $o['id']);
    t_ok(strpos($html($r), 'Mexahm') === false && strpos($html($r), 'Mexmum') !== false, 'B2 · the deal\'s people list offers Mumbai people only');
    //  Enquiries — recorded by the Mumbai BDM: since 27 Nov 2026 (decision 4a, docs/02 19ac) the
    //  Branch Manager reads enquiries only (tests/test_owner27_ops.php), so it no longer records them.
    $subj = 'B2 enquiry ' . uniqid();
    $post = ['client_name' => 'B2 Enquiry Co', 'contact_name' => 'Ravi', 'contact_mobile' => '9825088888', 'sbu' => 'IND', 'subject' => $subj, 'source' => 'EMAIL', 'assigned_to' => $meAhm];
    $r = bbv_route('inquiry-new', 'POST', $bdmMum, 0, $post);
    t_eq((int) ops_val("SELECT COUNT(*) FROM crm_inquiries WHERE subject=?", [$subj]), 0, 'B2 · an enquiry of Mumbai is not saved assigned to an Ahmedabad person');
    t_ok(strpos((string) ($r['out'] ?? ''), 'does not work for the Mumbai') !== false, 'B2 · the enquiry form says why');
    $r = bbv_route('inquiry-new', 'POST', $bdmMum, 0, ['assigned_to' => $meMum] + $post);
    $iid = (int) ops_val("SELECT id FROM crm_inquiries WHERE subject=?", [$subj]);
    if ($iid) $made['i'][] = $iid;
    t_ok($iid > 0, 'B2 · assigned to a Mumbai person it is saved');
    $r = bbv_route('inquiry-new', 'GET', $bdmMum, 0);
    t_ok(strpos($html($r), 'Mexmum') !== false && strpos($html($r), 'Mexahm') === false, 'B2 · the enquiry form\'s "Assigned to" offers Mumbai people only');

    // ---- B3 · My team = people who do sales work ------------------------------------------
    bbv_as($f['kiran']);
    $team = sx_team_people();
    t_ok(isset($team[$meMum]) && isset($team[$bdmMum]), 'B3 · the Marketing Executive and the BDM who report to the Branch Manager are in "My team"');
    t_ok(!isset($team[$f['neha']]) && !isset($team[$f['sunil']]), 'B3 · the coordinator and the operation manager in the reporting line are not');
    t_ok(in_array($f['neha'], sx_my_reportees(), true), 'B3 · (they still report to them — only the sales picker leaves them out)');

    // ---- B4 · deletions recorded, and the Activity register keeps to branches ---------------
    bbv_as($f['master']);
    $dn = 'B4 Ahm ' . uniqid();
    $l4 = lead_create(['company_name' => $dn, 'contact_phone' => '9825099999', 'source' => 'REFERRAL']);
    $pdo->prepare("UPDATE leads SET office_id=?, sbu='IND' WHERE id=?")->execute([$f['ahm'], $l4['id']]);
    $r = bbv_route('lead-delete', 'POST', $f['master'], 0, ['id' => (int) $l4['id']]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM leads WHERE id=?", [$l4['id']]), 0, 'B4 · the lead is deleted');
    $a = ops_one("SELECT * FROM activities WHERE entity_kind='LEAD' AND entity_id=? ORDER BY id DESC LIMIT 1", [$l4['id']]);
    t_ok($a && $a['kind'] === 'DELETED', 'B4 · the entry is a "Deleted" entry, not a Note');
    t_ok($a && (int) $a['auto'] === 1, 'B4 · it is marked as written by the system, not typed by a person');
    t_ok($a && (int) $a['office_id'] === (int) $f['ahm'], 'B4 · it carries the deleted lead\'s branch (Ahmedabad)');
    t_ok($a && strpos((string) $a['subject'], $dn) !== false && strpos((string) $a['subject'], '(by ') !== false, 'B4 · it names the lead and who deleted it');
    t_ok(!isset(act_kinds_manual()['DELETED']) && (ACT_KINDS['DELETED'] ?? '') === 'Deleted', 'B4 · "Deleted" is a filter on the register and never offered to type by hand');
    $r = bbv_route('activities', 'GET', $f['admin'], 0, [], ['kind' => 'DELETED']);
    t_ok(!$refused($r) && strpos((string) $r['out'], $dn) !== false, 'B4 · an administrator finds it under Directory → Activity → Deleted');
    $r = bbv_route('activities', 'GET', $f['neha'], 0, [], ['kind' => 'DELETED']);
    t_ok(!$refused($r), 'B4 · the Mumbai coordinator still opens the Activity register');
    t_ok(strpos((string) $r['out'], $dn) === false, 'B4 · …but no longer sees an Ahmedabad entry in it (it used to show every branch)');
    $r = bbv_route('activities', 'GET', $f['co_ahm'], 0, [], ['kind' => 'DELETED']);
    t_ok(strpos((string) $r['out'], $dn) !== false, 'B4 · the Ahmedabad coordinator sees it');

    // ---- B5 · converting onto a client already on file -------------------------------------
    bbv_as($f['master']);
    $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, is_vendor, status, created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute([$f['tag'] . 'C', 'B5 Client ' . $f['tag'], 'B5 Client ' . $f['tag'], date('c')]);
    $pid = (int) $pdo->lastInsertId(); $made['p'][] = $pid;
    $l5 = lead_create(['company_name' => 'B5 lead ' . uniqid(), 'contact_phone' => '9825012121', 'source' => 'REFERRAL']);
    $pdo->prepare("UPDATE leads SET office_id=?, partner_id=? WHERE id=?")->execute([$f['mum'], $pid, $l5['id']]); $made['l'][] = (int) $l5['id'];
    $r = bbv_route('lead-convert', 'GET', $f['master'], (int) $l5['id']);
    $h = $html($r);
    t_ok(preg_match('#id="cv-cid-wrap"[^>]*hidden#', $h) === 1, 'B5 · a lead already linked to a client: the GSTIN / PAN boxes start hidden');
    t_ok(preg_match('#id="cv-cid-onfile"(?![^>]*hidden)#', $h) === 1 && strpos($h, 'GSTIN is taken from the') !== false, 'B5 · …and one line says the GSTIN comes from the client on file');
    $l5b = lead_create(['company_name' => 'B5 new ' . uniqid(), 'contact_phone' => '9825013131', 'source' => 'REFERRAL']);
    $pdo->prepare("UPDATE leads SET office_id=? WHERE id=?")->execute([$f['mum'], $l5b['id']]); $made['l'][] = (int) $l5b['id'];
    $r = bbv_route('lead-convert', 'GET', $f['master'], (int) $l5b['id']);
    t_ok(preg_match('#id="cv-cid-wrap"(?![^>]*hidden)#', $html($r)) === 1, 'B5 · a lead for a new client still shows the GSTIN / PAN boxes');

    // ---- B6 · whose-records changes named; honest new-role words ---------------------------
    if (function_exists('idems_log')) {
        bbv_as($f['admin']);
        idems_log('user', $meMum, 'ACCESS_CHANGED', ['field' => 'x', 'reason' => 'test', 'new' => ['records_seen' => ['from' => 'as role', 'to' => 'own']]]);
        $hist = whocan_history('user', $meMum);
        $txt = implode(' | ', array_column($hist, 'what'));
        t_ok(strpos($txt, 'whose records they see: as the role → own') !== false, 'B6 · Recent changes names a whose-records change (it said "saved (no right changed)")');
    }
    $uf = (string) file_get_contents(dirname(__DIR__) . '/views/ops/user_form.php');
    t_ok(strpos($uf, 'fine-tune exactly what it can do') === false && strpos($uf, "always has exactly that role's switches") !== false,
         'B6 · the new-role help no longer promises fine-tuning that never applied');
} finally {
    putenv('BBV_RAW');
    $pdo = db();
    if ($made['o']) $pdo->exec("DELETE FROM opportunities WHERE id IN (" . implode(',', array_map('intval', $made['o'])) . ")");
    if ($made['l']) $pdo->exec("DELETE FROM leads WHERE id IN (" . implode(',', array_map('intval', $made['l'])) . ")");
    if ($made['i']) $pdo->exec("DELETE FROM crm_inquiries WHERE id IN (" . implode(',', array_map('intval', $made['i'])) . ")");
    if ($made['p']) $pdo->exec("DELETE FROM business_partners WHERE id IN (" . implode(',', array_map('intval', $made['p'])) . ")");
    foreach ($made['u'] as $id) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    bbv_cleanup($f);
}
