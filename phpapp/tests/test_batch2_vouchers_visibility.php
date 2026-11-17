<?php
// Batch 2 (owner, 17 Nov 2026 — plan 29 section A, Q3, points 2–3) — who sees,
// raises and reopens an expense voucher.
//   "Every individual can create, edit, submit their own vouchers … Assistant
//    Manager can approve vouchers of their own reportee. They cannot see anyone
//    else." Q3: strictly own + chain, also for a Branch Manager.
//   Each person sees ONLY (a) their own vouchers, (b) those they must approve
//   now, (c) those they approved or sent back. Master Admin / Admin: all.
//   Finance: those that reach (or passed) a Finance level, in its offices.
//   Reopen: APPROVED → Finance (its offices) / MA / AD; PAID → MA / AD only;
//   Business Director and Business Unit Head never; nobody else.
t_section('batch 2 — voucher visibility own + chain, raising your own, reopen (plan 29 A, Q3, points 2–3)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$sees = function ($uid, $vid) { bbv_as($uid); return can_view_voucher(bbv_v($vid)); };
$approve = function ($vid, $uid) { bbv_as($uid); return vappr_approve(bbv_v($vid)); };
// The register through the REAL route: which claimants' names are listed.
$listed = function ($uid) use ($f) {
    $r = bbv_route('vouchers', 'GET', $uid, 0);
    $out = (string) ($r['out'] ?? '');
    $names = [];
    foreach (['Amit', 'Rahul', 'Sr', 'Lone'] as $n) if (strpos($out, $n . ' ' . $f['tag']) !== false) $names[] = $n;
    return ['ok' => ($r['view'] ?? '') === 'ops/voucher_list' && !empty($r['view_ok']), 'names' => $names];
};
$opened = fn($r, $view) => empty($r['restricted']) && (int) ($r['code'] ?? 0) !== 403 && ($r['view'] ?? '') === $view && !empty($r['view_ok']);
try {
    // Asst. Manager Ravi (fixture 'am') has two reportees: Rahul and the Senior
    // Inspector. Amit reports to Coordinator Neha (N+1), Neha to Sunil, Sunil to
    // Branch Manager Kiran. Default chain (nothing configured): N+1 → Finance.
    $pdo->prepare("UPDATE inspectors SET reports_to_id=? WHERE id IN (?,?)")->execute([$f['am'], $f['rahul_ins'], $f['sr_ins']]);
    $vAmit  = bbv_voucher($f, $f['amit_ins'], 3000);
    $vRahul = bbv_voucher($f, $f['rahul_ins'], 2000);
    $vSr    = bbv_voucher($f, $f['sr_ins'], 1500);
    $vRahulDraft = bbv_voucher($f, $f['rahul_ins'], 900);
    $vLone  = bbv_voucher($f, $f['lone_ins'], 700);                  // stays a draft
    foreach ([[$vAmit, 'amit'], [$vRahul, 'rahul'], [$vSr, 'sr']] as [$vid, $who]) bbv_submit($vid, $f[$who]);

    // ---- (a) the inspector: own only ------------------------------------------
    t_ok($sees($f['amit'], $vAmit) && !$sees($f['amit'], $vRahul), 'inspector · sees their own voucher, not another inspector\'s');
    $l = $listed($f['amit']);
    t_ok($l['ok'] && !array_intersect($l['names'], ['Rahul', 'Sr', 'Lone']), 'inspector · the register lists nobody else');

    // ---- the Asst. Manager with two reportees ----------------------------------
    t_ok($sees($f['am'], $vRahul) && $sees($f['am'], $vSr), 'Asst. Manager · sees both reportees\' vouchers waiting for them (N+1)');
    t_ok(!$sees($f['am'], $vAmit), 'Asst. Manager · does not see another inspector\'s voucher in the same branch');
    t_ok(!$sees($f['am'], $vRahulDraft) && !$sees($f['am'], $vLone), 'Asst. Manager · nor a draft that is not waiting for them');
    $l = $listed($f['am']);
    sort($l['names']);
    t_eq($l['names'], ['Rahul', 'Sr'], 'Asst. Manager · the register lists exactly their two reportees');
    [$ok] = $approve($vRahul, $f['am']);
    t_ok($ok && $sees($f['am'], $vRahul), 'Asst. Manager · approves a reportee\'s voucher and still sees it afterwards (c)');

    // ---- Branch Manager / Director / BU Head / coordinator not in the chain ----
    foreach (['kiran' => 'Branch Manager', 'bd' => 'Business Director', 'sbu' => 'Business Unit Head', 'co_ahm' => 'another office\'s coordinator'] as $k => $who) {
        t_ok(!$sees($f[$k], $vAmit) && !$sees($f[$k], $vRahul) && !$sees($f[$k], $vLone), "Q3 · the $who, not in the chain, sees none of these vouchers");
    }
    $l = $listed($f['kiran']);
    t_ok($l['ok'] && !$l['names'], 'Q3 · the Branch Manager\'s register lists nobody else (no branch-wide register by role)');
    t_ok(!$sees($f['neha'], $vRahul) && $sees($f['neha'], $vAmit), 'coordinator · Neha sees Amit\'s (waiting for her), not the Asst. Manager\'s reportee\'s');

    // ---- the Operations home "Pending" tile counts own + chain only ------------
    $ownPending = function ($uid) use ($pdo) {
        $ins = (int) ($pdo->query("SELECT COALESCE(inspector_id,0) FROM users WHERE id=" . (int) $uid)->fetchColumn());
        return $ins ? (int) ops_val("SELECT COUNT(*) FROM vouchers WHERE inspector_id=? AND status IN ('DRAFT','SUBMITTED')", [$ins]) : 0;
    };
    $all = (int) ops_val("SELECT COUNT(*) FROM vouchers WHERE status IN ('DRAFT','SUBMITTED')");
    bbv_as($f['kiran']);
    t_eq(voucher_pending_count_for_me(), $ownPending($f['kiran']), 'Q3 · Operations home tile: the Branch Manager (not in any chain) counts only their own vouchers');
    t_ok(voucher_pending_count_for_me() < $all, 'Q3 · …never the company-wide total (' . $all . ')');
    bbv_as($f['neha']);
    t_eq(voucher_pending_count_for_me(), $ownPending($f['neha']) + 1, 'Q3 · Operations home tile: Neha counts her own plus Amit\'s waiting for her');
    bbv_as($f['master']);
    t_eq(voucher_pending_count_for_me(), $all, 'Operations home tile: the Master Admin still counts every draft or waiting voucher');

    // ---- (b) only once it reaches you: N+2 waits for N+1 ------------------------
    bbv_as($f['master']);
    vappr_config_save(['use' => ['N1' => 1, 'N2' => 1, 'OFFICE_FIN' => 1], 'above' => []]);
    $vUp = bbv_voucher($f, $f['amit_ins'], 4000);
    bbv_submit($vUp, $f['amit']);
    t_ok(!$sees($f['sunil'], $vUp), 'chain · N+2 does not see it while it waits for N+1');
    $approve($vUp, $f['neha']);
    t_ok($sees($f['sunil'], $vUp), 'chain · …and sees it once it is their turn');

    // ---- Finance: at (or past) its step, in its offices -----------------------
    t_ok(!$sees($f['meera'], $vUp) && !$sees($f['meera'], $vLone), 'Finance · does not see a voucher before it reaches Finance, nor a draft');
    $approve($vUp, $f['sunil']);
    t_ok($sees($f['meera'], $vUp), 'Finance · sees it when it reaches the Finance step');
    $l = $listed($f['meera']);
    t_ok($l['ok'] && in_array('Amit', $l['names'], true) && !in_array('Lone', $l['names'], true), 'Finance · its register lists it (and not the draft)');
    bbv_as($f['meera']);
    [$ok] = vappr_send_back(bbv_v($vUp), 'Hotel bill missing');
    t_ok($ok && $sees($f['meera'], $vUp) && $sees($f['rakesh'], $vUp), 'Finance · a voucher Finance sent back stays visible to Finance');
    t_ok($sees($f['neha'], $vUp) && $sees($f['sunil'], $vUp), '(c) · the approvers who signed it still see it after the send-back');
    $vAhmOk = bbv_voucher($f, $f['rahul_ins'], 800, 'APPROVED', '2031-06', $f['ahm']);
    t_ok(!$sees($f['meera'], $vAhmOk) && $sees($f['rakesh'], $vAhmOk), 'Finance · office Finance (Mumbai) does not see an approved Ahmedabad voucher; central Finance does');

    // ---- Master Admin and Admin: everything ------------------------------------
    foreach (['master' => 'Master Admin', 'admin' => 'Admin'] as $k => $who)
        t_ok($sees($f[$k], $vLone) && $sees($f[$k], $vRahulDraft) && $sees($f[$k], $vAhmOk), "$who · sees every voucher, drafts and other offices included");
    $l = $listed($f['admin']);
    t_ok($l['ok'] && count(array_intersect($l['names'], ['Amit', 'Rahul', 'Sr', 'Lone'])) === 4, 'Admin · the register lists every claimant');

    // ---- Raising: your own (anyone linked), someone else's only MA / AD --------
    $pdo->prepare("INSERT INTO inspectors (name, home_office_id, status) VALUES (?,?, 'ACTIVE')")->execute(['Bdm ' . $f['tag'], $f['mum']]);
    $bdmIns = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, inspector_id) VALUES (?,?,?,?,1,?,?)")
        ->execute([$f['tag'] . '_bdm', 'Bdm', 'T', 'BUSINESS_DEV_MANAGER', $f['mum'], $bdmIns]);
    $bdm = (int) $pdo->lastInsertId();
    bbv_route('voucher', 'GET', $bdm, 0, [], ['month' => '2031-09']);
    $own = (int) ops_val("SELECT id FROM vouchers WHERE inspector_id=? AND month='2031-09'", [$bdmIns]);
    t_ok($own > 0, 'own · a desk person without the Vouchers module (Business Development Manager) opens their own month');
    $r = bbv_route('voucher', 'GET', $bdm, $own);
    t_ok($opened($r, 'ops/voucher_detail') && strpos($r['out'], 'Add an expense') !== false, 'own · …and edits it');
    $r = bbv_route('voucher-status', 'POST', $bdm, $own, ['action' => 'submit']);
    t_eq($r['status'] ?? '', 'SUBMITTED', 'own · …and submits it');
    bbv_route('voucher', 'GET', $f['neha'], 0, [], ['ins' => $f['rahul_ins'], 'month' => '2031-10']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM vouchers WHERE inspector_id=? AND month='2031-10'", [$f['rahul_ins']]), 0,
        'on behalf · a coordinator (Vouchers edit) can no longer open a month for an inspector');
    bbv_as($f['neha']);
    t_ok(!can_edit_voucher(bbv_v($vRahulDraft)) && !can_submit_voucher(bbv_v($vRahulDraft)), 'on behalf · nor edit or submit somebody else\'s draft');
    bbv_route('voucher', 'GET', $f['admin'], 0, [], ['ins' => $f['rahul_ins'], 'month' => '2031-10']);
    $made = ops_one("SELECT * FROM vouchers WHERE inspector_id=? AND month='2031-10'", [$f['rahul_ins']]);
    t_ok($made && (int) $made['office_id'] === $f['mum'], 'on behalf · an Admin opens one for him, in the claimant\'s own office');

    // ---- Reopen ----------------------------------------------------------------
    $vApp  = bbv_voucher($f, $f['amit_ins'], 2200, 'APPROVED');
    $vPaid = bbv_voucher($f, $f['amit_ins'], 2300, 'PAID');
    $vSub  = bbv_voucher($f, $f['amit_ins'], 2400);
    bbv_submit($vSub, $f['amit']);
    $re = function ($uid, $vid) { bbv_as($uid); return can_reopen_voucher(bbv_v($vid)); };
    t_ok($re($f['meera'], $vApp) && $re($f['rakesh'], $vApp), 'reopen · Finance covering the office reopens an APPROVED voucher');
    t_ok(!$re($f['meera'], $vAhmOk), 'reopen · office Finance cannot reopen outside its offices');
    t_ok($re($f['admin'], $vApp) && $re($f['master'], $vApp), 'reopen · the Admin and Master Admin reopen an APPROVED voucher');
    t_ok(!$re($f['meera'], $vPaid) && $re($f['admin'], $vPaid) && $re($f['master'], $vPaid), 'reopen · PAID: the Master Admin and Admin only, not Finance');
    foreach (['bd' => 'Business Director', 'sbu' => 'Business Unit Head', 'kiran' => 'Branch Manager', 'sunil' => 'Operation Manager', 'neha' => 'coordinator (an approver)', 'am' => 'Asst. Manager'] as $k => $who)
        t_ok(!$re($f[$k], $vApp) && !$re($f[$k], $vPaid) && !$re($f[$k], $vSub), "reopen · the $who reopens nothing");
    t_ok(!$re($f['amit'], $vApp), 'reopen · the claimant does not reopen their own approved voucher');
    t_ok(!$re($f['meera'], $vSub) && $re($f['admin'], $vSub), 'reopen · a voucher waiting for approval: an administrator only (approvers send back)');
    $r = bbv_route('voucher-status', 'POST', $f['bd'], $vApp, ['action' => 'reopen']);
    t_eq($r['status'] ?? '', 'APPROVED', 'reopen · the server refuses the Business Director');
    $r = bbv_route('voucher-status', 'POST', $f['sbu'], $vApp, ['action' => 'reopen']);
    t_eq($r['status'] ?? '', 'APPROVED', 'reopen · the server refuses the Business Unit Head');
    $r = bbv_route('voucher-status', 'POST', $f['meera'], $vPaid, ['action' => 'reopen']);
    t_eq($r['status'] ?? '', 'PAID', 'reopen · the server refuses Finance on a PAID voucher');
    $r = bbv_route('voucher-status', 'POST', $f['meera'], $vApp, ['action' => 'reopen', 'reason' => 'wrong month']);
    $h = vappr_history($vApp); $h = end($h);
    t_ok(($r['status'] ?? '') === 'DRAFT' && $h['action'] === 'REOPENED' && $h['level_key'] === 'FROM_APPROVED' && $h['reason'] === 'wrong month',
        'reopen · Finance reopens the APPROVED voucher through the real route; History records it, from Approved, with the reason');
    t_ok($sees($f['meera'], $vApp), 'reopen · Finance still sees the voucher it reopened');
    $r = bbv_route('voucher-status', 'POST', $f['admin'], $vPaid, ['action' => 'reopen']);
    t_eq($r['status'] ?? '', 'DRAFT', 'reopen · the Admin reopens the PAID voucher through the real route');
    // Paid is unchanged: Finance / Admin.
    $vApp2 = bbv_voucher($f, $f['amit_ins'], 2500, 'APPROVED');
    $pay = function ($uid, $vid) { bbv_as($uid); return can_mark_voucher_paid(bbv_v($vid)); };
    t_ok($pay($f['meera'], $vApp2) && $pay($f['admin'], $vApp2) && !$pay($f['kiran'], $vApp2) && !$pay($f['bd'], $vApp2), 'Paid stays with Finance and the administrators');
} finally {
    bbv_cleanup($f);
}
