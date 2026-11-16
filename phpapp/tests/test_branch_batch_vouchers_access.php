<?php
// Branch batch R5 — who may open, edit, submit, approve, pay and reopen an
// expense voucher, row by row against docs/02 (Vouchers row, footnote 3).
//   DEF-OPS-031  an Inspector / Senior Inspector could not open their OWN voucher
//   DEF-OPS-003  Finance could not read vouchers (D-07 says View)
//   DEF-OPS-033  the Asst. Manager could not open a voucher they approve
//   DEF-OPS-034  the Business Director was offered (and granted) Approve / Reopen
// C3: Paid is marked by Finance (and the Master Admin / Admin), not any coordinator.
// Branch scope: staff read vouchers of the offices they cover, not the company.
t_section('branch batch R5 — voucher access per docs/02 (DEF-OPS-031/003/033/034, C3)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
// The screen really opened: the route rendered its template without an error.
$opened = fn($r, $view) => empty($r['restricted']) && (int) ($r['code'] ?? 0) !== 403
    && ($r['view'] ?? '') === $view && !empty($r['view_ok']);
try {
    $vAmit  = bbv_voucher($f, $f['amit_ins'], 3000);              // Amit's draft
    $vSr    = bbv_voucher($f, $f['sr_ins'], 1500);                // Senior Inspector's draft
    $vRahul = bbv_voucher($f, $f['rahul_ins'], 2000);             // another inspector's draft

    // ---- Inspector & Senior Inspector: OWN vouchers, through the REAL route --
    $r = bbv_route('vouchers', 'GET', $f['amit'], 0);
    t_ok($opened($r, 'ops/voucher_list'), 'DEF-OPS-031 · an Inspector opens "My vouchers" (/vouchers) — no "restricted" screen');
    $r = bbv_route('voucher', 'GET', $f['amit'], $vAmit);
    t_ok($opened($r, 'ops/voucher_detail'), 'DEF-OPS-031 · an Inspector opens their own voucher (/voucher?id=)');
    $r = bbv_route('voucher', 'GET', $f['sr'], $vSr);
    t_ok($opened($r, 'ops/voucher_detail'), 'DEF-OPS-031 · a Senior Inspector opens their own voucher');
    $r = bbv_route('voucher', 'GET', $f['amit'], $vRahul);
    t_ok(!$opened($r, 'ops/voucher_detail'), 'an Inspector still cannot open ANOTHER inspector\'s voucher');
    $r = bbv_route('voucher-header', 'POST', $f['amit'], $vAmit, ['nature' => 'REVENUE', 'advance' => '0', 'office_incurred' => '0']);
    t_eq((string) ops_val("SELECT nature FROM vouchers WHERE id=?", [$vAmit]), 'REVENUE', 'DEF-OPS-031 · the Inspector edits their own draft (the save reaches the record)');
    $r = bbv_route('voucher-status', 'POST', $f['amit'], $vAmit, ['action' => 'submit']);
    t_eq($r['status'] ?? '', 'SUBMITTED', 'DEF-OPS-031 · the Inspector submits their own draft');
    $r = bbv_route('voucher-header', 'POST', $f['amit'], $vRahul, ['nature' => 'MD']);
    t_ok((string) ops_val("SELECT nature FROM vouchers WHERE id=?", [$vRahul]) !== 'MD', 'the Inspector cannot edit another inspector\'s draft');

    // ---- Finance: VIEW (D-07), within the offices it covers ------------------
    $r = bbv_route('vouchers', 'GET', $f['meera'], 0);
    t_ok($opened($r, 'ops/voucher_list'), 'DEF-OPS-003 · Finance opens the vouchers register');
    $r = bbv_route('voucher', 'GET', $f['meera'], $vRahul);
    t_ok($opened($r, 'ops/voucher_detail'), 'DEF-OPS-003 · Finance opens a voucher of an office it covers');
    bbv_as($f['meera']);
    t_ok(function_exists('can_edit_voucher') && !can_edit_voucher(bbv_v($vRahul)), 'Finance does not edit somebody\'s draft (View only)');
    $vAhm = bbv_voucher($f, $f['rahul_ins'], 900, 'DRAFT', '2031-02', $f['ahm']);
    t_ok(!can_view_voucher(bbv_v($vAhm)), 'branch scope · office Finance (Mumbai) does not read an Ahmedabad voucher');
    bbv_as($f['rakesh']);
    t_ok(can_view_voucher(bbv_v($vAhm)), 'branch scope · central Finance (all offices) reads it');

    // ---- Branch scope for staff ---------------------------------------------
    bbv_as($f['co_ahm']);
    t_ok(!can_view_voucher(bbv_v($vRahul)), 'branch scope · an Ahmedabad coordinator does not open a Mumbai voucher (today: every coordinator saw every voucher)');
    t_ok(can_view_voucher(bbv_v($vAhm)), 'branch scope · the Ahmedabad coordinator opens an Ahmedabad voucher');
    bbv_as($f['neha']);
    t_ok(can_edit_voucher(bbv_v($vRahul)), 'a Mumbai coordinator (Vouchers edit) still edits a Mumbai draft');

    // ---- Business Director: View only ---------------------------------------
    $vSub = bbv_voucher($f, $f['rahul_ins'], 2500);
    bbv_submit($vSub, $f['rahul']);
    bbv_as($f['bd']);
    t_ok(can_view_voucher(bbv_v($vSub)), 'the Business Director reads a submitted voucher (View)');
    t_ok(function_exists('vappr_can_act') && !vappr_can_act(bbv_v($vSub))['ok'], 'DEF-OPS-034 · the Business Director (not in this claimant\'s line) cannot approve it');
    t_ok(function_exists('can_reopen_voucher') && !can_reopen_voucher(bbv_v($vSub)), 'DEF-OPS-034 · the Business Director cannot reopen it');
    t_ok(!can_edit_voucher(bbv_v($vAmit)) && !can_edit_voucher(bbv_v($vRahul)), 'the Business Director edits no voucher (View only)');
    $r = bbv_route('voucher-status', 'POST', $f['bd'], $vSub, ['action' => 'approve']);
    t_eq($r['status'] ?? '', 'SUBMITTED', 'DEF-OPS-034 · the server refuses the Business Director\'s Approve');
    $r = bbv_route('voucher-status', 'POST', $f['bd'], $vSub, ['action' => 'reopen']);
    t_eq($r['status'] ?? '', 'SUBMITTED', 'DEF-OPS-034 · the server refuses the Business Director\'s Reopen');

    // ---- Asst. Manager: approves in the chain, opens what they approve -------
    db()->prepare("UPDATE inspectors SET reports_to_id=? WHERE id=?")->execute([$f['am'], $f['rahul_ins']]);
    $vAm = bbv_voucher($f, $f['rahul_ins'], 1200);
    bbv_submit($vAm, $f['rahul']);
    $r = bbv_route('voucher', 'GET', $f['am'], $vAm);
    t_ok($opened($r, 'ops/voucher_detail'), 'DEF-OPS-033 · the Asst. Manager opens the voucher they are to approve');
    $r = bbv_route('voucher-status', 'POST', $f['am'], $vAm, ['action' => 'approve']);
    try { $lvl = (string) ops_val("SELECT level_key FROM voucher_approval_log WHERE voucher_id=? AND action='APPROVED' ORDER BY id DESC LIMIT 1", [$vAm]); }
    catch (Throwable $e) { $lvl = ''; }
    t_eq($lvl, 'N1', 'DEF-OPS-033 · the Asst. Manager (the claimant\'s N+1) approves it through the real route');
    db()->prepare("UPDATE inspectors SET reports_to_id=? WHERE id=?")->execute([$f['neha'], $f['rahul_ins']]);

    // ---- C3: Paid by Finance (and Master Admin / Admin) only ------------------
    $vApp = bbv_voucher($f, $f['rahul_ins'], 2200, 'APPROVED');
    // null when the predicate does not exist (the code before this batch).
    $paid   = fn($vid) => function_exists('can_mark_voucher_paid') ? can_mark_voucher_paid(bbv_v($vid)) : null;
    $reopen = fn($vid) => function_exists('can_reopen_voucher') ? can_reopen_voucher(bbv_v($vid)) : null;
    bbv_as($f['neha']);   t_ok($paid($vApp) === false, 'C3 · a coordinator no longer marks a voucher paid');
    bbv_as($f['kiran']);  t_ok($paid($vApp) === false, 'C3 · a branch manager does not either');
    bbv_as($f['bd']);     t_ok($paid($vApp) === false, 'C3 · nor the Business Director');
    bbv_as($f['meera']);  t_ok($paid($vApp) === true, 'C3 · Finance covering the office marks it paid');
    bbv_as($f['admin']);  t_ok($paid($vApp) === true, 'C3 · an Admin may');
    bbv_as($f['master']); t_ok($paid($vApp) === true, 'C3 · the Master Admin may');
    bbv_as($f['meera']);  t_ok($paid($vAhm) === false, 'C3 · office Finance cannot pay outside its offices (and only APPROVED is payable)');
    $r = bbv_route('voucher-status', 'POST', $f['neha'], $vApp, ['action' => 'paid']);
    t_eq($r['status'] ?? '', 'APPROVED', 'C3 · the server refuses a coordinator\'s Mark paid');
    $r = bbv_route('voucher-status', 'POST', $f['meera'], $vApp, ['action' => 'paid']);
    t_eq($r['status'] ?? '', 'PAID', 'C3 · Finance marks it paid through the real route');

    // ---- Reopen otherwise unchanged ----------------------------------------
    bbv_as($f['neha']);  t_ok($reopen($vSub) === true, 'reopen · a coordinator still reopens a SUBMITTED voucher in their office');
    t_ok($reopen($vApp) === false, 'reopen · a coordinator cannot reopen a PAID voucher');
    bbv_as($f['kiran']); t_ok($reopen($vApp) === true, 'reopen · a branch manager reopens a PAID voucher');
} finally {
    bbv_cleanup($f);
}
