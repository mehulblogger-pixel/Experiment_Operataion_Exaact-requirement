<?php
// Branch batch R5 + 0.4 + Q1–Q4 — the expense voucher approval chain.
//   Default (nothing configured): N+1, then Finance (office Finance, else central).
//   Bands (owner's example, illustrative): ₹8,000 with N+2 > 5k, N+3 > 10k, N+4 > 20k
//     → N+1 → N+2 → Finance (Q1: in order; Finance last).
//   Q2: a short line stops at its highest person; no "Reports to" → an admin, warned.
//   Q3: send back with a mandatory reason → DRAFT; resubmission restarts the chain.
//   Maker ≠ checker; a delegate acts; the pending inbox / dashboard list it.
//   Legacy: a voucher SUBMITTED before the chain starts at the first level.
t_section('branch batch R5 — voucher approval chain (Q1–Q4, 0.4, delegation, inbox)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$keys = fn($v) => array_map(fn($l) => $l['key'], vappr_chain(bbv_v($v)));
$approve = function ($vid, $uid, $note = '') { bbv_as($uid); return vappr_approve(bbv_v($vid), $note); };
$status = fn($vid) => (string) ops_val("SELECT status FROM vouchers WHERE id=?", [$vid]);
try {
    // ---- Setup screen gate --------------------------------------------------
    bbv_as($f['master']);
    $areas = array_column(apsetup_open(), 'key');
    t_ok(in_array('vouchers', $areas, true), 'Admin → Approvals offers "Expense vouchers" to the Master Admin');
    bbv_as($f['admin']);
    t_ok(!vappr_config_can() && !in_array('vouchers', array_column(apsetup_open(), 'key'), true), 'an Admin (not Master) is not offered the voucher chain');
    [$ok] = vappr_config_save(['use' => ['N1' => 1], 'above' => []]);
    t_ok(!$ok && vappr_config() === null, 'the save itself refuses anyone but the Master Admin');
    t_ok(vappr_config() === null, 'ships with NOTHING configured (no amounts invented)');
    $r = bbv_route('voucher-approvals', 'GET', $f['master'], 0);
    t_ok(($r['view'] ?? '') === 'ops/voucher_approvals' && !empty($r['view_ok']) && strpos($r['out'], 'N+1') !== false,
        'the Expense vouchers setup screen renders for the Master Admin and states the default');
    $r = bbv_route('voucher-approvals', 'GET', $f['admin'], 0);
    t_ok(($r['view'] ?? '') !== 'ops/voucher_approvals', 'the setup screen refuses an Admin');
    $r = bbv_route('voucher-approvals', 'POST', $f['coordinator'] ?? $f['neha'], 0, ['use' => ['N1' => '1'], 'above' => ['N1' => '']]);
    t_ok(vappr_config() === null, 'a coordinator\'s POST to the setup screen changes nothing');

    // ---- Default chain: N+1 → Finance ---------------------------------------
    $v1 = bbv_voucher($f, $f['amit_ins'], 8000);
    bbv_submit($v1, $f['amit']);
    t_eq($keys($v1), ['N1', 'FIN'], 'default · N+1 then Finance');
    $p = vappr_progress(bbv_v($v1));
    t_eq(array_map('intval', array_column($p['levels'][0]['approvers'], 'id')), [$f['neha']], 'default · N+1 is the claimant\'s "Reports to" (Neha)');
    $fin = array_map('intval', array_column($p['levels'][1]['approvers'], 'id'));
    t_ok(in_array($f['meera'], $fin, true) && in_array($f['rakesh'], $fin, true), 'default · Finance = those whose offices cover Mumbai (office and central)');
    t_ok(strpos(vappr_waiting_text($p), 'Neha T (N+1) → Finance') === 0, 'the voucher says "Waiting for: Neha (N+1) → Finance"');
    [$ok] = $approve($v1, $f['meera']);
    t_ok(!$ok, 'Q1 · Finance cannot approve before the reporting line');
    [$ok] = $approve($v1, $f['sunil']);
    t_ok(!$ok, 'Q1 · N+2 cannot jump the queue (only N+1 is needed by default)');
    [$ok] = $approve($v1, $f['neha']);
    t_ok($ok && $status($v1) === 'SUBMITTED', 'N+1 approves; the voucher stays SUBMITTED (no new status)');
    [$ok] = $approve($v1, $f['meera']);
    t_ok($ok && $status($v1) === 'APPROVED', 'Finance approves last → APPROVED');
    $row = bbv_v($v1);
    t_ok($row['checked_by'] === 'Neha T' && $row['authorized_by'] === 'Meera T', 'the print boxes come from the chain (checked by N+1, authorized by Finance)');
    $hist = vappr_history($v1);
    t_eq(array_column($hist, 'action'), ['SUBMITTED', 'APPROVED', 'APPROVED'], 'History records the submission and each approval');
    t_ok($hist[1]['level_key'] === 'N1' && $hist[1]['user_name'] === 'Neha T' && $hist[1]['created_at'] !== '', 'History · who, which level, when');

    // Default Finance when nobody covers the office → central Finance only.
    db()->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$f['meera']]);
    $vC = bbv_voucher($f, $f['rahul_ins'], 500, 'DRAFT', '2031-03', $f['ahm']);
    bbv_submit($vC, $f['rahul']);
    $pc = vappr_progress(bbv_v($vC));
    t_eq(array_map('intval', array_column($pc['levels'][1]['approvers'], 'id')), [$f['rakesh']], 'default · no Finance covers the office → central Finance (all offices)');
    db()->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$f['meera']]);

    // ---- The owner's band example (illustrative amounts, set by the test) -----
    bbv_as($f['master']);
    [$ok] = vappr_config_save(['use' => ['N2' => 1], 'above' => ['N2' => '5000']]);
    t_ok(!$ok, 'a chain where every level has an amount is refused (a small voucher would have no approver)');
    [$ok] = vappr_config_save(['use' => [], 'above' => []]);
    t_ok(!$ok, 'an empty chain is refused');
    [$ok] = vappr_config_save(['use' => ['N1' => 1, 'N2' => 1, 'N3' => 1, 'N4' => 1, 'OFFICE_FIN' => 1],
                                'above' => ['N1' => '', 'N2' => '5000', 'N3' => '10000', 'N4' => '20000', 'OFFICE_FIN' => '']]);
    t_ok($ok, 'the Master Admin saves bands N+2 > ₹5,000, N+3 > ₹10,000, N+4 > ₹20,000, Office Finance always');
    $v2 = bbv_voucher($f, $f['amit_ins'], 8000);
    bbv_submit($v2, $f['amit']);
    t_eq($keys($v2), ['N1', 'N2', 'OFFICE_FIN'], 'band · ₹8,000 → N+1 → N+2 → Finance (Kiran is not asked)');
    $p2 = vappr_progress(bbv_v($v2));
    $of = array_map('intval', array_column($p2['levels'][2]['approvers'], 'id')); sort($of);
    $want = [$f['meera'], $f['rakesh']]; sort($want);
    t_eq($of, $want, 'Office Finance · the Finance people whose ticked offices include Mumbai (Meera; Rakesh covers all offices)');
    [$ok] = $approve($v2, $f['neha']);   t_ok($ok, 'band · Neha (N+1) approves first');
    $r = bbv_route('voucher', 'GET', $f['sunil'], $v2);
    t_ok(!empty($r['view_ok']) && strpos($r['out'], 'Waiting for: Sunil T (N+2) → Office Finance') !== false,
        'the voucher screen says "Waiting for: Sunil (N+2) → Office Finance"');
    t_ok(strpos($r['out'], 'History') !== false && strpos($r['out'], 'Reporting line N+1') !== false, 'the voucher screen shows the chain and its History');
    $r = bbv_route('voucher-status', 'POST', $f['sunil'], $v2, ['action' => 'sendback', 'reason' => '']);
    t_eq($r['status'] ?? '', 'SUBMITTED', 'Q3 · the real route refuses a send-back without a reason');
    [$ok] = $approve($v2, $f['meera']);  t_ok(!$ok, 'band · Finance still waits for N+2');
    [$ok] = $approve($v2, $f['sunil']);  t_ok($ok && $status($v2) === 'SUBMITTED', 'band · Sunil (N+2) approves second');
    [$ok] = $approve($v2, $f['meera']);  t_ok($ok && $status($v2) === 'APPROVED', 'band · Finance approves last → APPROVED');
    $v3 = bbv_voucher($f, $f['amit_ins'], 4000);
    t_eq($keys($v3), ['N1', 'OFFICE_FIN'], 'band · ₹4,000 → N+1 → Finance');
    $v4 = bbv_voucher($f, $f['amit_ins'], 25000);
    t_eq($keys($v4), ['N1', 'N2', 'N3', 'N4', 'OFFICE_FIN'], 'band · ₹25,000 → N+1 … N+4 → Finance');
    bbv_as($f['master']);
    vappr_config_save(['use' => ['N1' => 1, 'N3' => 1, 'OFFICE_FIN' => 1, 'CENTRAL_FIN' => 1], 'above' => ['N3' => '10000']]);
    $v5 = bbv_voucher($f, $f['amit_ins'], 15000);
    t_eq($keys($v5), ['N1', 'N2', 'N3', 'OFFICE_FIN', 'CENTRAL_FIN'], 'Q1 · a higher level includes those below it; office Finance, then central Finance');

    // ---- Q2: short chain; no Reports to ------------------------------------
    db()->prepare("UPDATE users SET reports_to_id=NULL WHERE id=?")->execute([$f['sunil']]);   // Sunil reports to nobody
    t_eq($keys($v4), ['N1', 'N2', 'OFFICE_FIN', 'CENTRAL_FIN'], 'Q2 · the line is shorter than the band → it ends at its highest person (Sunil, N+2)');
    db()->prepare("UPDATE users SET reports_to_id=? WHERE id=?")->execute([$f['kiran'], $f['sunil']]);
    bbv_as($f['master']);
    vappr_config_save(['reset' => 1]);
    t_ok(vappr_config() === null, 'the Master Admin can go back to the default');
    $vL = bbv_voucher($f, $f['lone_ins'], 1000);
    bbv_submit($vL, $f['lone']);
    $pl = vappr_progress(bbv_v($vL));
    t_ok($pl['levels'][0]['key'] === 'LINE' && !$pl['levels'][0]['approvers'] && strpos($pl['levels'][0]['problem'], 'Reports to') !== false,
        'Q2 · no "Reports to" at all → the first level waits for an administrator, with a warning to fill it in');
    [$ok] = $approve($vL, $f['neha']);   t_ok(!$ok, 'Q2 · a coordinator cannot take that level');
    bbv_as($f['admin']);
    $inbox = voucher_waiting_on_me();
    $hit = array_values(array_filter($inbox, fn($x) => (int) $x['id'] === $vL));
    t_ok($hit && strpos((string) $hit[0]['_warning'], 'Reports to') !== false, 'Q2 · the admin sees it in their inbox with the warning');
    [$ok] = $approve($vL, $f['admin']);  t_ok($ok, 'Q2 · the admin approves that level');
    t_ok(vappr_progress(bbv_v($vL))['levels'][1]['key'] === 'FIN' && $status($vL) === 'SUBMITTED', 'Q2 · …and Finance still approves last');

    // ---- Q3: send back with a reason → DRAFT; restart -----------------------
    $v6 = bbv_voucher($f, $f['amit_ins'], 3000);
    bbv_submit($v6, $f['amit']);
    $approve($v6, $f['neha']);
    bbv_as($f['meera']);
    [$ok] = vappr_send_back(bbv_v($v6), '   ');
    t_ok(!$ok && $status($v6) === 'SUBMITTED', 'Q3 · sending back needs a reason');
    [$ok] = vappr_send_back(bbv_v($v6), 'Hotel bill for 12 Nov missing');
    $r6 = bbv_v($v6);
    t_ok($ok && $r6['status'] === 'DRAFT', 'Q3 · Finance sends it back → DRAFT (no "Rejected" status)');
    t_ok($r6['sent_back_reason'] === 'Hotel bill for 12 Nov missing' && $r6['sent_back_by'] === 'Meera T', 'Q3 · the reason and who sent it back are on the voucher for the claimant');
    $last = vappr_history($v6); $last = end($last);
    t_ok($last['action'] === 'SENT_BACK' && $last['reason'] === 'Hotel bill for 12 Nov missing' && $last['level_key'] === 'FIN', 'Q3 · History keeps the send-back, its level and its reason');
    $r = bbv_route('voucher', 'GET', $f['amit'], $v6);
    t_ok(!empty($r['view_ok']) && strpos($r['out'], 'Hotel bill for 12 Nov missing') !== false && strpos($r['out'], 'Sent back by Meera T') !== false,
        'Q3 · the claimant\'s voucher screen shows who sent it back and why');
    bbv_as($f['amit']);
    t_ok(can_edit_voucher(bbv_v($v6)), 'Q3 · the claimant corrects the draft');
    bbv_submit($v6, $f['amit']);
    $p6 = vappr_progress(bbv_v($v6));
    t_ok($p6['current'] === 0 && $p6['levels'][0]['done'] === null, 'Q3 · resubmission restarts the chain at N+1 (Neha\'s earlier approval does not carry over)');
    t_eq((string) bbv_v($v6)['sent_back_reason'], '', 'Q3 · the send-back note clears on resubmission (it stays in History)');
    bbv_as($f['sunil']);
    [$ok] = vappr_send_back(bbv_v($v6), 'not mine');
    t_ok(!$ok, 'Q3 · only someone who may act at the current level sends back');

    // ---- Maker ≠ checker -----------------------------------------------------
    $v7 = bbv_voucher($f, $f['amit_ins'], 2000);
    bbv_submit($v7, $f['neha']);                        // Neha submits on Amit's behalf
    [$ok, $why] = $approve($v7, $f['neha']);
    t_ok(!$ok && strpos($why, 'maker') !== false, 'maker ≠ checker · the submitter (even as N+1) cannot approve');
    [$ok] = $approve($v7, $f['amit']);
    t_ok(!$ok, 'maker ≠ checker · the claimant cannot approve their own voucher');
    bbv_as($f['admin']);
    t_ok(vappr_can_act(bbv_v($v7))['ok'], 'maker ≠ checker · with N+1 barred and no delegate, the level goes to an administrator');
    db()->prepare("UPDATE users SET role='FINANCE', scope_offices='ALL' WHERE id=?")->execute([$f['sunil']]);  // N+1 who is also Finance
    db()->prepare("UPDATE inspectors SET reports_to_id=? WHERE id=?")->execute([$f['sunil'], $f['rahul_ins']]);
    $v8 = bbv_voucher($f, $f['rahul_ins'], 1000);
    bbv_submit($v8, $f['rahul']);
    $approve($v8, $f['sunil']);
    [$ok] = $approve($v8, $f['sunil']);
    t_ok(!$ok && $status($v8) === 'SUBMITTED', 'one person approves one level: N+1 who is also Finance cannot sign the Finance level too');
    [$ok] = $approve($v8, $f['rakesh']);  t_ok($ok && $status($v8) === 'APPROVED', '…another Finance person does');
    db()->prepare("UPDATE users SET role='OPERATION_MANAGER', scope_offices='' WHERE id=?")->execute([$f['sunil']]);
    db()->prepare("UPDATE inspectors SET reports_to_id=? WHERE id=?")->execute([$f['neha'], $f['rahul_ins']]);

    // ---- Delegation: Neha is away, the Ahmedabad coordinator covers her voucher approvals ------------
    bbv_as($f['master']);
    [$ok] = appr_delegation_save(0, ['entity' => 'VOUCHER', 'delegator_user_id' => $f['neha'], 'delegate_user_id' => $f['co_ahm'],
                                     'effective_from' => date('Y-m-d'), 'effective_to' => date('Y-m-d', strtotime('+7 days'))]);
    t_ok($ok, 'delegation · the Master Admin records a voucher-only delegation (existing table, entity VOUCHER)');
    $v9 = bbv_voucher($f, $f['amit_ins'], 1500);
    bbv_submit($v9, $f['amit']);
    bbv_as($f['co_ahm']);
    $c = vappr_can_act(bbv_v($v9));
    t_ok($c['ok'] && (int) $c['on_behalf'] === $f['neha'], 'delegation · the delegate may act at Neha\'s level, on her behalf');
    t_ok(can_view_voucher(bbv_v($v9)), 'delegation · the delegate can open the voucher (even from another office)');
    [$ok] = vappr_approve(bbv_v($v9));
    $h9 = vappr_history($v9); $h9 = end($h9);
    t_ok($ok && $h9['on_behalf_name'] === 'Neha T' && (int) $h9['user_id'] === $f['co_ahm'], 'delegation · History shows who approved, for whom');
    t_ok(!vappr_can_act(bbv_v($v9))['ok'], 'delegation · the delegate does not inherit the next (Finance) level');
    t_ok(appr_delegators_for($f['co_ahm'], 'OFFER', null) === [], 'delegation · a VOUCHER delegation lends nothing in recruitment');

    // ---- Pending inbox and "Your pending tasks" ------------------------------
    $v10 = bbv_voucher($f, $f['rahul_ins'], 700);
    bbv_submit($v10, $f['rahul']);
    bbv_as($f['neha']);
    $ids = array_map(fn($x) => (int) $x['id'], voucher_waiting_on_me());
    t_ok(in_array($v10, $ids, true), 'inbox · Neha (N+1) has it waiting');
    $secs = array_column(approvals_hub_sections(), null, 'key');
    t_ok(isset($secs['vouchers']) && in_array($v10, array_map(fn($x) => (int) $x['id'], $secs['vouchers']['items']), true), 'inbox · it is in the approvals hub ("Expense vouchers")');
    $row = approvals_hub_row('vouchers', $secs['vouchers']['items'][0]);
    t_ok(strpos($row['url'], '/voucher?id=') === 0, 'inbox · the row opens the voucher');
    $tasks = array_column(ops_pending_tasks(), 'n', 'label');
    t_ok(($tasks['vouchers to approve'] ?? 0) >= 1, '"Your pending tasks" counts it for Neha');
    bbv_as($f['meera']);
    t_ok(!in_array($v10, array_map(fn($x) => (int) $x['id'], voucher_waiting_on_me()), true), 'inbox · Finance does not see it until N+1 has approved');
    bbv_as($f['neha']); vappr_approve(bbv_v($v10));
    bbv_as($f['meera']);
    t_ok(in_array($v10, array_map(fn($x) => (int) $x['id'], voucher_waiting_on_me()), true), 'inbox · …then it moves to Finance');
    bbv_as($f['co_ahm']);
    $tasksC = array_column(ops_pending_tasks(), 'n', 'label');
    t_ok(!in_array($v10, array_map(fn($x) => (int) $x['id'], voucher_waiting_on_me()), true), 'inbox · a coordinator outside the chain is not counted it (it was: every submitted voucher)');
    bbv_as($f['meera']);
    vappr_approve(bbv_v($v10));
    $tasksF = array_column(ops_pending_tasks(), 'n', 'label');
    t_ok(($tasksF['vouchers to pay'] ?? 0) >= 1, 'C3 · Finance sees approved vouchers "to pay" on its dashboard');

    // ---- Legacy: SUBMITTED before the chain existed --------------------------
    $v11 = bbv_voucher($f, $f['amit_ins'], 1000, 'SUBMITTED');   // round 0, no log rows, no submitter
    $p11 = vappr_progress(bbv_v($v11));
    t_ok($p11['current'] === 0 && $p11['levels'][0]['key'] === 'N1', 'legacy · an already-SUBMITTED voucher starts the chain at its first level');
    [$ok] = $approve($v11, $f['neha']);
    t_ok($ok && $status($v11) === 'SUBMITTED', 'legacy · it is approved level by level like any other');
} finally {
    bbv_cleanup($f);
}
