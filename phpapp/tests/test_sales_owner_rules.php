<?php
// ============================================================================
//  The owner's rules for quotations (20 Oct 2026) — Sales audit D-02…D-08,
//  SA-01…SA-06, SA-09, SA-10, SA-12.
//
//  Approval is whatever the company's own approval rules say; a value band is
//  measured before tax; any commercial or terms change cancels an approval; the
//  person assigned records the customer's answer. Every rule is driven through
//  the REAL route, as a real role, in a separate process
//  (tests/_sales_route_worker.php) — a hidden button is not a control, so the
//  test never relies on one. (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('Sales — the owner\'s quotation rules hold at the server');

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
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300)];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? []));
$pdo  = db();

// ---- people, offices, a customer ------------------------------------------------
$office = function ($name) use ($pdo) {
    $pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute([$name]);
    return (int) $pdo->lastInsertId();
};
$offA = $office('SOR Office A'); $offB = $office('SOR Office B');
$mk = function ($un, $role, $off, $super = 0) use ($pdo) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,?,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $super, $off]);
    return (int) $pdo->lastInsertId();
};
$MA  = $mk('sor_ma',  'MASTER_ADMIN', $offA, 1);
$MM  = $mk('sor_mm',  'MARKETING_MANAGER', $offA);
$BDM = $mk('sor_bdm', 'BUSINESS_DEV_MANAGER', $offA);
$FIN = $mk('sor_fin', 'FINANCE', $offA);
$BMA = $mk('sor_bma', 'BRANCH_MANAGER', $offA);
$BMB = $mk('sor_bmb', 'BRANCH_MANAGER', $offB);
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('SOR Client','SOR Client',1,'ACTIVE')")->execute();
$cli = (int) $pdo->lastInsertId();

// A quotation as the screen would leave it: header, one line, subtotal and total.
$n = 0;
$quote = function ($sub, $status = 'DRAFT', array $extra = []) use ($pdo, $BDM, $cli, $offA, &$n) {
    $n++;
    $gst = round($sub * 0.18, 2);
    $row = array_merge(['quote_no' => 'SOR-' . $n, 'rev' => 0, 'is_current' => 1, 'status' => $status, 'client_id' => $cli,
        'client_name' => 'SOR Client', 'subject' => 'Inspection', 'sbu' => '', 'office_id' => $offA, 'currency' => 'INR',
        'validity_days' => 30, 'gst_pct' => 18, 'subtotal' => $sub, 'gst_amount' => $gst, 'total_amount' => $sub + $gst,
        'owner_id' => $BDM, 'created_by' => 'sor_bdm', 'created_at' => date('c'), 'contact_email' => ''], $extra);
    $pdo->prepare("INSERT INTO quotations (" . implode(',', array_keys($row)) . ") VALUES (" . implode(',', array_fill(0, count($row), '?')) . ")")
        ->execute(array_values($row));
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, qty, unit, rate, amount) VALUES (?,1,'Inspection',1,'MANDAY',?,?)")
        ->execute([$id, $sub, $sub]);
    return $id;
};
$st    = fn($id) => (string) ops_val("SELECT status FROM quotations WHERE id=?", [$id]);
$steps = fn($id) => ops_all("SELECT * FROM quote_approvals WHERE quote_id=? ORDER BY level, id", [$id]) ?: [];
$hist  = fn($id) => implode(' || ', array_map(fn($r) => $r['summary'], ops_all("SELECT summary FROM quote_revisions WHERE quote_id=? ORDER BY id", [$id]) ?: []));
$rule  = function ($name, $level, $min, $max, $role, $uid = null) use ($pdo) {
    $pdo->prepare("INSERT INTO quote_approval_rules (name, level, active, match_type, sbu, min_amount, max_amount, approver_role, approver_user_id)
                   VALUES (?,?,1,'ANY','',?,?,?,?)")->execute([$name, $level, $min, $max, $role, $uid]);
};
$pdo->exec("DELETE FROM quote_approval_rules");
setting_set('quote_unmatched_approval', '');

// ---- D-04: the band is measured before tax --------------------------------------
$rule('From one lakh', 1, 100000, 0, 'MARKETING_MANAGER');
$under = $quote(90000);    // 90,000 + 18 % = 1,06,200 — over a lakh only WITH tax
$over  = $quote(110000);
t_eq(crm_quote_band_value(crm_quote_get($under)), 90000.0, 'the approval value is the quotation before tax');
t_ok(!crm_quote_matching_rules(crm_quote_get($under)), '90,000 before tax is under a one-lakh rule although its total with GST is 1,06,200');
t_ok((bool) crm_quote_matching_rules(crm_quote_get($over)), '1,10,000 before tax falls in the one-lakh rule');

// ---- D-03: a value no rule covers does what the company chose ---------------------
t_eq(crm_quote_unmatched_policy(), 'any', 'with nothing chosen, an uncovered value keeps the behaviour it always had: any approver');
t_ok(crm_quote_needs_approval(crm_quote_get($under)), '…so it still needs an approval');
setting_set('quote_unmatched_approval', 'none');
t_ok(!crm_quote_needs_approval(crm_quote_get($under)), 'when the company says "no approval needed", an uncovered value needs none');
t_ok(crm_quote_needs_approval(crm_quote_get($over)), '…but a value a rule covers still does');
setting_set('quote_unmatched_approval', 'top');
$rule('Above five lakh', 2, 500000, 0, 'BUSINESS_DIRECTOR');
$top = crm_quote_rule_steps(crm_quote_get($under));
t_eq(array_map(fn($r) => $r['approver_role'], $top), ['BUSINESS_DIRECTOR'], 'when the company says "the highest level", an uncovered value goes to the top level\'s approver');
$pdo->exec("DELETE FROM quote_approval_rules WHERE level=2");
setting_set('quote_unmatched_approval', 'none');

// ---- D-02: approval before sending only where the rules call for it ---------------
$r = $drive($BDM, 'quote-status', 'POST', ['id' => $over], ['to' => 'SENT']);
t_eq($st($over), 'DRAFT', 'a draft the rules cover cannot be sent before it is approved (SA-01)');
t_ok(strpos($said($r), 'needs approval') !== false, '…and the refusal says why: ' . $said($r));
$r = $drive($BDM, 'quote-status', 'POST', ['id' => $under], ['to' => 'SENT']);
t_eq($st($under), 'SENT', 'a draft no rule covers can be sent straight away when the company needs no approval for it');
t_ok(strpos($hist($under), 'no approval was needed') !== false, '…and the history says it went without approval, and who sent it');
$pend = $quote(110000, 'PENDING_APPROVAL');
$drive($BDM, 'quote-status', 'POST', ['id' => $pend], ['to' => 'SENT']);
t_eq($st($pend), 'PENDING_APPROVAL', 'a quotation awaiting approval cannot be sent');
$rej = $quote(110000, 'REJECTED');
$drive($BDM, 'quote-status', 'POST', ['id' => $rej], ['to' => 'SENT']);
t_eq($st($rej), 'REJECTED', 'a rejected quotation cannot be sent');

// ---- D-07: a commercial change cancels the approval -------------------------------
$q7 = $quote(110000);
$drive($BDM, 'quote-status', 'POST', ['id' => $q7], ['to' => 'PENDING_APPROVAL']);
$s7 = $steps($q7);
t_eq(count($s7), 1, 'submitting builds the chain from the rules');
$drive($MM, 'quote-approve', 'POST', [], ['step' => (int) $s7[0]['id'], 'decision' => 'approve']);
t_eq($st($q7), 'APPROVED', 'the approver approves it');
$form = fn($rate, $contact) => ['client_id' => $cli, 'subject' => 'Inspection', 'sbu' => '', 'office_id' => $offA, 'currency' => 'INR',
    'validity_days' => 30, 'gst_pct' => 18, 'contact_name' => $contact,
    'l_desc' => ['Inspection'], 'l_qty' => ['1'], 'l_unit' => ['MANDAY'], 'l_rate' => [(string) $rate]];
$drive($BDM, 'quote-edit', 'POST', ['id' => $q7], $form(110000, 'Mr First'));
// The first save through the real form normalises the fields the fixture left
// blank; whatever it did, the approval is judged on what changed after it.
if ($st($q7) !== 'APPROVED') {
    $drive($BDM, 'quote-status', 'POST', ['id' => $q7], ['to' => 'PENDING_APPROVAL']);
    $s7 = $steps($q7);
    $drive($MM, 'quote-approve', 'POST', [], ['step' => (int) $s7[0]['id'], 'decision' => 'approve']);
}
t_eq($st($q7), 'APPROVED', 'arranged: an approved quotation, saved once through the real form');
$drive($BDM, 'quote-edit', 'POST', ['id' => $q7], $form(110000, 'Mrs Second'));
t_eq($st($q7), 'APPROVED', 'changing only the contact person keeps the approval — it is not a commercial term');
$drive($BDM, 'quote-edit', 'POST', ['id' => $q7], $form(10000000, 'Mrs Second'));
t_eq($st($q7), 'DRAFT', 'changing the rate cancels the approval (SA-02)');
t_eq(count($steps($q7)), 0, '…and its approval steps go with it');
t_ok(strpos($hist($q7), 'approval was cancelled') !== false, '…and the history records it, with the old and new totals');
$drive($BDM, 'quote-status', 'POST', ['id' => $q7], ['to' => 'SENT']);
t_eq($st($q7), 'DRAFT', '…so the re-priced quotation cannot be sent on the old approval');
$tq = $quote(110000);
$drive($BDM, 'quote-status', 'POST', ['id' => $tq], ['to' => 'PENDING_APPROVAL']);
$f = $form(110000, 'Mrs Second') + ['terms_conditions' => 'Payment within 30 days.'];
$drive($BDM, 'quote-edit', 'POST', ['id' => $tq], $f);
$drive($BDM, 'quote-status', 'POST', ['id' => $tq], ['to' => 'PENDING_APPROVAL']);
$sx = $steps($tq);
$drive($MM, 'quote-approve', 'POST', [], ['step' => (int) $sx[0]['id'], 'decision' => 'approve']);
$drive($BDM, 'quote-edit', 'POST', ['id' => $tq], ['terms_conditions' => 'Payment within 90 days.'] + $f);
t_eq($st($tq), 'DRAFT', 'changing the terms & conditions cancels the approval too');

// ---- SA-05: a rejection ends the chain; an old step cannot move a quotation -------
$pdo->exec("DELETE FROM quote_approval_rules");
$rule('Branch first', 1, 100000, 0, 'BRANCH_MANAGER');
$rule('Then marketing', 2, 100000, 0, 'MARKETING_MANAGER');
$q5 = $quote(200000);
$drive($BDM, 'quote-status', 'POST', ['id' => $q5], ['to' => 'PENDING_APPROVAL']);
$s5 = $steps($q5);
t_eq(count($s5), 2, 'two levels, two steps');
$drive($BMA, 'quote-approve', 'POST', [], ['step' => (int) $s5[0]['id'], 'decision' => 'reject', 'remarks' => 'Rate too low']);
t_eq($st($q5), 'REJECTED', 'level 1 rejects it');
$drive($MM, 'quote-approve', 'POST', [], ['step' => (int) $s5[1]['id'], 'decision' => 'approve']);
t_eq($st($q5), 'REJECTED', 'level 2 can no longer approve a rejected quotation (it used to end APPROVED)');
$drive($BDM, 'quote-status', 'POST', ['id' => $q5], ['to' => 'PENDING_APPROVAL']);
t_ok(strpos($hist($q5), 'rejected by') !== false && strpos($hist($q5), 'Rate too low') !== false,
     'resubmitting keeps the earlier rejection, and its reason, in the history');

// ---- SA-06: approval authority stays inside the branch ----------------------------
$s6 = $steps($q5);
$r = $drive($BMB, 'quote-approve', 'POST', [], ['step' => (int) $s6[0]['id'], 'decision' => 'approve']);
t_eq((string) ops_val("SELECT status FROM quote_approvals WHERE id=?", [(int) $s6[0]['id']]), 'PENDING',
     'a Branch Manager of office B cannot sign office A\'s quotation by posting only the step');
$drive($BMA, 'quote-approve', 'POST', [], ['step' => (int) $s6[0]['id'], 'decision' => 'approve']);
t_eq((string) ops_val("SELECT status FROM quote_approvals WHERE id=?", [(int) $s6[0]['id']]), 'APPROVED',
     '…while office A\'s own Branch Manager can');
$pdo->exec("DELETE FROM quote_approval_rules");

// ---- D-08: the person assigned records the customer's answer ----------------------
$q8 = $quote(50000, 'SENT', ['sent_at' => date('c')]);
$drive($FIN, 'quote-status', 'POST', ['id' => $q8], ['to' => 'ACCEPTED']);
t_eq($st($q8), 'SENT', 'Finance cannot mark someone else\'s quotation accepted (SA-03)');
$r = $drive($MM, 'quote-status', 'POST', ['id' => $q8], ['to' => 'ACCEPTED']);
t_eq($st($q8), 'SENT', 'nor can a Marketing Manager who is not the person assigned');
t_ok(strpos($said($r), 'person assigned') !== false, '…and the refusal names who can: ' . $said($r));
$drive($BDM, 'quote-status', 'POST', ['id' => $q8], ['to' => 'LOST']);
t_eq($st($q8), 'SENT', 'the person assigned must say why a quotation was lost');
$drive($BDM, 'quote-status', 'POST', ['id' => $q8], ['to' => 'ACCEPTED']);
t_eq($st($q8), 'ACCEPTED', 'the person assigned records the acceptance');
t_ok(strpos($hist($q8), 'acceptance was recorded by') !== false, '…and the history says who recorded it');
$r = $drive($BDM, 'quote-status', 'POST', ['id' => $q8], ['to' => 'LOST', 'lost_reason' => 'PRICE']);
t_eq($st($q8), 'ACCEPTED', 'an accepted quotation cannot be flipped to lost — acceptance is final');
$d8 = $quote(50000);
$drive($BDM, 'quote-status', 'POST', ['id' => $d8], ['to' => 'ACCEPTED']);
t_eq($st($d8), 'DRAFT', 'a quotation never sent cannot be accepted, even by the person assigned');
$drive($MA, 'quote-status', 'POST', ['id' => $q8], ['to' => 'DRAFT']);
t_eq($st($q8), 'ACCEPTED', 'no one — not even the master — moves an accepted quotation back to draft through the status route');
foreach (['EXPIRED', 'REJECTED'] as $bad) {
    $x = $quote(50000, 'SENT', ['sent_at' => date('c')]);
    $drive($BDM, 'quote-status', 'POST', ['id' => $x], ['to' => $bad]);
    t_eq($st($x), 'SENT', $bad . ' is never typed in: it is the sweep\'s or the approver\'s');
}

// ---- the screen offers what the server allows, and no more ------------------------
$pg = $drive($BDM, 'quote', 'GET', ['id' => $q8], [], ['Mark accepted', 'Mark lost', 'Actions']);
t_eq($pg['done'], 'return', 'the quotation page draws for the person assigned (no error: ' . ($pg['error'] ?? 'none') . ')');
$sentQ = $quote(50000, 'SENT', ['sent_at' => date('c')]);
$pa = $drive($BDM, 'quote', 'GET', ['id' => $sentQ], [], ['Mark accepted (won)', 'Mark lost']);
t_ok(!empty($pa['has']['Mark accepted (won)']) && !empty($pa['has']['Mark lost']), 'the person assigned sees "Mark accepted" and "Mark lost" on a sent quotation');
$pf = $drive($FIN, 'quote', 'GET', ['id' => $sentQ], [], ['Mark accepted (won)', 'records the client']);
t_ok(empty($pf['has']['Mark accepted (won)']), 'Finance does not see "Mark accepted" on someone else\'s quotation');
t_ok(!empty($pf['has']['records the client']), '…the page says who records the answer instead');
$freeQ = $quote(5000);
$pd = $drive($BDM, 'quote', 'GET', ['id' => $freeQ], [], ['No approval needed', 'Submit for approval']);
t_ok(!empty($pd['has']['No approval needed']) && empty($pd['has']['Submit for approval']),
     'a draft no rule covers, in a company that needs no approval for it, offers Send — not Submit for approval');

// ---- SA-10: a superseded revision cannot move ---------------------------------------
$old = $quote(50000, 'SENT', ['quote_no' => 'SOR-REV', 'rev' => 0, 'is_current' => 0, 'sent_at' => date('c')]);
$new = $quote(45000, 'DRAFT', ['quote_no' => 'SOR-REV', 'rev' => 1, 'parent_id' => $old]);
$r = $drive($BDM, 'quote-status', 'POST', ['id' => $old], ['to' => 'ACCEPTED']);
t_eq($st($old), 'SENT', 'an earlier revision cannot be accepted once a later one exists');
t_ok(strpos($said($r), 'earlier revision') !== false, '…and the refusal says so');
$po = $drive($BDM, 'quote', 'GET', ['id' => $old], [], ['This is an earlier revision', 'Mark accepted']);
t_ok(!empty($po['has']['This is an earlier revision']) && empty($po['has']['Mark accepted']),
     'an earlier revision\'s page says it was replaced and offers no actions');

// ---- SA-09: a contract only on the current, accepted quotation ----------------------
$before = (int) ops_val("SELECT COUNT(*) FROM partner_contracts");
$dq = $quote(50000);
$drive($FIN, 'quote-contract', 'POST', ['id' => $dq], ['auto_contract' => '1']);
t_eq((int) ops_val("SELECT COUNT(*) FROM partner_contracts"), $before, 'no contract is registered on a draft quotation');
$drive($FIN, 'quote-contract', 'POST', ['id' => $old], ['auto_contract' => '1']);
t_eq((int) ops_val("SELECT COUNT(*) FROM partner_contracts"), $before, 'nor on a superseded revision');
$drive($FIN, 'quote-contract', 'POST', ['id' => $q8], ['auto_contract' => '1']);
t_eq((int) ops_val("SELECT COUNT(*) FROM partner_contracts"), $before + 1, 'control: Finance registers the contract on the current, accepted quotation');

// ---- SA-04: a portal registration cannot invent a status or a total --------------------
$ext = fn($status, $total, $lines = []) => ['client_id' => $cli, 'origin' => 'CLIENT_PORTAL', 'origin_portal' => 'Ariba', 'status' => $status,
    'total_amount' => (string) $total, 'subject' => 'Portal bid'] + $lines;
$cnt = fn() => (int) ops_val("SELECT COUNT(*) FROM quotations WHERE origin='CLIENT_PORTAL'");
$c0 = $cnt();
foreach (['ACCEPTED', 'APPROVED', 'NOT_A_STATUS'] as $bad) {
    $drive($BDM, 'quote-external', 'POST', [], $ext($bad, 900000));
    t_eq($cnt(), $c0, 'a portal registration as ' . $bad . ' is refused');
}
$drive($BDM, 'quote-external', 'POST', [], $ext('SENT', 100, ['l_desc' => ['Visits'], 'l_qty' => ['10'], 'l_rate' => ['100000']]));
t_eq($cnt(), $c0, 'a total that does not match its lines is refused');
$drive($BDM, 'quote-external', 'POST', [], $ext('SENT', 0));
t_eq($cnt(), $c0, 'a portal registration with no total and no lines is refused');
$drive($BDM, 'quote-external', 'POST', [], $ext('SENT', 1000000, ['l_desc' => ['Visits'], 'l_qty' => ['10'], 'l_rate' => ['100000']]));
t_eq($cnt(), $c0 + 1, 'a portal registration as sent, with matching lines, is accepted');

// ---- SA-10: one commitment per quotation in the Command Centre -----------------------
t_as_admin();
$base = (float) (financial_rollup()['committed'] ?? 0);
$quote(100000, 'ACCEPTED', ['quote_no' => 'SOR-CMT', 'rev' => 0, 'is_current' => 0]);
$quote(60000, 'ACCEPTED', ['quote_no' => 'SOR-CMT', 'rev' => 1]);
t_eq(round((float) financial_rollup()['committed'] - $base, 2), 70800.0,
     'two accepted revisions of one quotation count once — the latest (60,000 + GST = 70,800), not both');

setting_set('quote_unmatched_approval', '');
