<?php
// Batch 2 (owner, 17 Nov 2026 — plan 29, Change 2, point 1) — expense voucher
// approval by BOTH the level and the amount.
//   * Role levels: any role from the role list (Business Director, Business Unit
//     Head, Branch Manager, a custom role …), each with its own "needed above ₹"
//     and a place in the order the Master Admin sets. Finance stays last.
//   * The owner's example (amounts are the test's, never preset):
//       N+1 always · N+2 above ₹5,000 · Business Director above ₹20,000 · Office Finance always
//       ₹3,000 → N+1 → Finance;  ₹8,000 → N+1 → N+2 → Finance;
//       ₹25,000 → N+1 → N+2 → Director → Finance.
//   * Any active holder covering the voucher's office signs; a custom role built
//     on the role counts; nobody holds it → an administrator, warned.
//   * No double signing: the Director who is also the claimant's N+2 is not asked
//     twice — the Director level counts as signed ("already signed at N+2").
//   * Backward compatible with the setting saved by the previous batch.
t_section('batch 2 — voucher role levels by amount, order, no double signing (plan 29 Change 2)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$keys = fn($vid) => array_map(fn($l) => $l['key'], vappr_chain(bbv_v($vid)));
$approve = function ($vid, $uid, $note = '') { bbv_as($uid); return vappr_approve(bbv_v($vid), $note); };
$status = fn($vid) => (string) ops_val("SELECT status FROM vouchers WHERE id=?", [$vid]);
$ids = fn($rows) => array_map(fn($u) => (int) $u['id'], (array) $rows);
// Save levels as the screen posts them: [[type, role, above], …] in order.
$save = function (array $levels, array $extra = []) use ($f) {
    bbv_as($f['master']);
    $lv = []; $i = 0;
    foreach ($levels as $l) { $i++; $lv[$i] = ['type' => $l[0], 'role' => $l[1] ?? '', 'use' => '1', 'above' => $l[2] ?? '', 'order' => $l[3] ?? $i]; }
    return vappr_config_save(['lv' => $lv] + $extra);
};
$example = [['N1'], ['N2', '', '5000'], ['ROLE', 'BUSINESS_DIRECTOR', '20000'], ['OFFICE_FIN']];

// Only this fixture's holders of the roles used here count: everybody else's is
// switched off for the test and switched back on in `finally`.
$otherHolders = array_map('intval', array_column(ops_all("SELECT id FROM users WHERE is_active=1 AND username NOT LIKE ?
    AND role IN ('BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER','BRANCH_APP_MANAGER')", [$f['tag'] . '_%']) ?: [], 'id'));
if ($otherHolders) $pdo->exec("UPDATE users SET is_active=0 WHERE id IN (" . implode(',', $otherHolders) . ")");
$oldCustom = (string) setting_get('custom_roles', '');
try {
    // ---- The setup screen takes role levels and an order ----------------------
    t_ok(function_exists('vappr_level_roles'), 'the role list for voucher levels exists');
    $roles = function_exists('vappr_level_roles') ? vappr_level_roles() : [];
    t_ok(isset($roles['BUSINESS_DIRECTOR'], $roles['SBU_HEAD'], $roles['BRANCH_MANAGER']), 'the role list offers Business Director, Business Unit Head and Branch Manager');
    t_ok(!isset($roles['MASTER_ADMIN']) && !isset($roles['ADMIN']) && !isset($roles['FINANCE']), '…but not the administrators (they sign any stuck level) nor Finance (its own levels, always last)');
    [$ok] = $save($example);
    t_ok($ok, 'the Master Admin saves N+1 · N+2 > ₹5,000 · Business Director > ₹20,000 · Office Finance');
    $cfg = vappr_config();
    t_eq(array_map(fn($l) => $l['type'] . ($l['role'] !== '' ? ':' . $l['role'] : ''), (array) $cfg), ['N1', 'N2', 'ROLE:BUSINESS_DIRECTOR', 'OFFICE_FIN'], 'the levels are kept in the order set');
    bbv_as($f['admin']);
    [$ok] = vappr_config_save(['lv' => [1 => ['type' => 'N1', 'use' => '1', 'above' => '']]]);
    t_ok(!$ok, 'an Admin (not Master) cannot change the levels');

    // ---- The owner's three amounts ------------------------------------------
    $v3  = bbv_voucher($f, $f['amit_ins'], 3000);
    $v8  = bbv_voucher($f, $f['amit_ins'], 8000);
    $v25 = bbv_voucher($f, $f['amit_ins'], 25000);
    t_eq($keys($v3), ['N1', 'OFFICE_FIN'], '₹3,000 → N+1 → Finance');
    t_eq($keys($v8), ['N1', 'N2', 'OFFICE_FIN'], '₹8,000 → N+1 → N+2 → Finance');
    t_eq($keys($v25), ['N1', 'N2', 'R:BUSINESS_DIRECTOR', 'OFFICE_FIN'], '₹25,000 → N+1 → N+2 → Business Director → Finance');
    $v20 = bbv_voucher($f, $f['amit_ins'], 20000);
    t_eq($keys($v20), ['N1', 'N2', 'OFFICE_FIN'], 'exactly ₹20,000 is not "above ₹20,000" — no Director');

    bbv_submit($v25, $f['amit']);
    $p = vappr_progress(bbv_v($v25));
    t_eq($ids($p['levels'][2]['approvers']), [$f['bd']], 'the Director level: every active Business Director covering Mumbai (here, one)');
    t_ok(strpos(vappr_waiting_text($p), 'Neha T (N+1) → Sunil T (N+2) → Business Director → Office Finance') === 0, '"Waiting for: Neha (N+1) → Sunil (N+2) → Business Director → Office Finance"');
    [$ok] = $approve($v25, $f['bd']);   t_ok(!$ok, 'the Director cannot jump the queue');
    [$ok] = $approve($v25, $f['neha']); t_ok($ok, 'N+1 signs');
    [$ok] = $approve($v25, $f['sunil']); t_ok($ok, 'N+2 signs');
    [$ok] = $approve($v25, $f['meera']); t_ok(!$ok && $status($v25) === 'SUBMITTED', 'Finance waits for the Director');
    $r = bbv_route('voucher', 'GET', $f['bd'], $v25);
    t_ok(!empty($r['view_ok']) && strpos($r['out'], 'Waiting for: Business Director (Director T) → Office Finance') !== false, 'the Director opens it and reads "Waiting for: Business Director (Director T) → Office Finance"');
    $r = bbv_route('voucher-status', 'POST', $f['bd'], $v25, ['action' => 'approve']);
    t_eq($r['status'] ?? '', 'SUBMITTED', 'the Director signs through the real route; it moves on to Finance');
    $last = vappr_history($v25); $last = end($last);
    t_ok($last['level_key'] === 'R:BUSINESS_DIRECTOR' && (int) $last['user_id'] === $f['bd'] && $last['level_label'] === 'Business Director', 'History: approved at "Business Director" by the Director');
    [$ok] = $approve($v25, $f['meera']); t_ok($ok && $status($v25) === 'APPROVED', 'Finance signs last → APPROVED');
    t_eq((string) bbv_v($v25)['approved_by'], 'Director T', 'the print\'s "Approved by" is the last level before Finance (the Director)');

    // ---- A custom role built on Business Director counts ---------------------
    setting_set('custom_roles', json_encode(['BD_IND_' . strtoupper($f['tag']) => ['label' => 'Director Industrial ' . $f['tag'], 'base' => 'BUSINESS_DIRECTOR']]));
    $custom = 'BD_IND_' . strtoupper($f['tag']);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active) VALUES (?,?,?,?,1)")->execute([$f['tag'] . '_bdind', 'Ind', 'T', $custom]);
    $bdInd = (int) $pdo->lastInsertId();
    $vC = bbv_voucher($f, $f['amit_ins'], 30000);
    bbv_submit($vC, $f['amit']);
    $pc = vappr_progress(bbv_v($vC));
    $dir = $ids($pc['levels'][2]['approvers']); sort($dir);
    $want = [$f['bd'], $bdInd]; sort($want);
    t_eq($dir, $want, 'custom role · "Director Industrial" (built on Business Director) is asked to sign the Business Director level too');
    $approve($vC, $f['neha']); $approve($vC, $f['sunil']);
    [$ok] = $approve($vC, $bdInd);
    t_ok($ok && vappr_progress(bbv_v($vC))['levels'][2]['done']['user_id'] == $bdInd, 'custom role · the first to approve signs the level');
    t_ok(!vappr_can_act(bbv_v($vC), null, vappr_user($f['bd']))['ok'], '…and the other Director is not asked any more');
    t_ok(function_exists('vappr_level_roles') && isset(vappr_level_roles()[$custom]), 'custom role · it is also on the role list for a level of its own');
    $save([['N1'], ['ROLE', $custom], ['OFFICE_FIN']]);
    $vCx = bbv_voucher($f, $f['amit_ins'], 100);
    bbv_submit($vCx, $f['amit']);
    t_eq($ids(vappr_progress(bbv_v($vCx))['levels'][1]['approvers']), [$bdInd], 'custom role · a level for the custom role is signed by its holders only, not by every Business Director');

    // ---- No double signing ---------------------------------------------------
    $save($example);
    $pdo->prepare("UPDATE users SET reports_to_id=? WHERE id=?")->execute([$f['bd'], $f['neha']]);   // the Director is Amit's N+2
    $vD = bbv_voucher($f, $f['amit_ins'], 25000);
    bbv_submit($vD, $f['amit']);
    $pd = vappr_progress(bbv_v($vD));
    t_eq(array_map(fn($l) => $l['key'], $pd['levels']), ['N1', 'N2', 'R:BUSINESS_DIRECTOR', 'OFFICE_FIN'], 'no double signing · the chain still lists the Director level');
    t_eq($ids($pd['levels'][1]['approvers']), [$f['bd']], 'no double signing · the Director is the claimant\'s N+2');
    $approve($vD, $f['neha']);
    [$ok, $msg] = $approve($vD, $f['bd']);
    $pd = vappr_progress(bbv_v($vD));
    t_ok($ok && $pd['current'] === 3 && !empty($pd['levels'][2]['auto']), 'no double signing · signing N+2 also counts for the Director level; it goes straight to Finance');
    t_ok(strpos((string) $msg, 'counts as signed') !== false, 'no double signing · the Director is told the Director level counts as signed');
    $auto = array_values(array_filter(vappr_history($vD), fn($h) => $h['action'] === 'ALREADY_SIGNED'));
    t_ok(count($auto) === 1 && $auto[0]['level_key'] === 'R:BUSINESS_DIRECTOR' && (int) $auto[0]['user_id'] === $f['bd']
        && strpos($auto[0]['level_label'], 'already signed at N+2') !== false, 'no double signing · History: "Business Director — already signed at N+2"');
    t_ok(!vappr_can_act(bbv_v($vD), null, vappr_user($f['bd']))['ok'], 'no double signing · the Director is not asked again');
    $r = bbv_route('voucher', 'GET', $f['amit'], $vD);
    t_ok(strpos($r['out'], 'already signed at N+2') !== false && strpos($r['out'], 'Counted as signed') !== false, 'no double signing · the voucher screen shows the level as already signed, and History says so');
    [$ok] = $approve($vD, $f['meera']);
    t_ok($ok && $status($vD) === 'APPROVED', 'no double signing · Finance signs last → APPROVED (one signature from the Director)');
    // Any level: the Director placed BEFORE N+2 — then N+2 counts as signed.
    [$ok] = $save([['N1'], ['ROLE', 'BUSINESS_DIRECTOR', '20000'], ['N2', '', '5000'], ['OFFICE_FIN']]);
    $vD2 = bbv_voucher($f, $f['amit_ins'], 25000);
    t_ok($ok && $keys($vD2) === ['N1', 'R:BUSINESS_DIRECTOR', 'N2', 'OFFICE_FIN'], 'order · the Director may sit before N+2');
    bbv_submit($vD2, $f['amit']);
    $approve($vD2, $f['neha']); $approve($vD2, $f['bd']);
    $pd2 = vappr_progress(bbv_v($vD2));
    t_ok($pd2['current'] === 3 && !empty($pd2['levels'][2]['auto']) && ($pd2['levels'][2]['done']['via'] ?? '') === 'Business Director',
        'no double signing · any level: N+2 counts as signed ("already signed at Business Director")');
    // Maker ≠ checker stays: the claimant / submitter is never counted as a signer.
    $vD3 = bbv_voucher($f, $f['amit_ins'], 25000);
    bbv_submit($vD3, $f['bd']);                                     // the Director submitted it
    $approve($vD3, $f['neha']);
    $pd3 = vappr_progress(bbv_v($vD3));
    t_ok($pd3['current'] === 1 && empty($pd3['levels'][1]['auto']) && !vappr_can_act(bbv_v($vD3), $pd3, vappr_user($f['bd']))['ok'],
        'maker ≠ checker · a Director who submitted the voucher signs no level of it');
    $pdo->prepare("UPDATE users SET reports_to_id=? WHERE id=?")->execute([$f['sunil'], $f['neha']]);
    // Finance is never counted: the N+1 who is also Finance does not sign Finance too.
    $save([['N1'], ['OFFICE_FIN']]);
    $pdo->prepare("UPDATE inspectors SET reports_to_id=? WHERE id=?")->execute([$f['meera'], $f['rahul_ins']]);
    $vF = bbv_voucher($f, $f['rahul_ins'], 500);
    bbv_submit($vF, $f['rahul']);
    $approve($vF, $f['meera']);
    $pf = vappr_progress(bbv_v($vF));
    t_ok($pf['current'] === 1 && empty($pf['levels'][1]['auto']), 'Finance is the paying check: never counted as signed by an earlier signature');
    [$ok] = $approve($vF, $f['rakesh']); t_ok($ok && $status($vF) === 'APPROVED', '…another Finance person signs it');
    $pdo->prepare("UPDATE inspectors SET reports_to_id=? WHERE id=?")->execute([$f['neha'], $f['rahul_ins']]);

    // ---- Ordering ------------------------------------------------------------
    [$ok] = $save([['OFFICE_FIN'], ['N1'], ['ROLE', 'SBU_HEAD', '10000']]);
    t_ok($ok && array_column((array) vappr_config(), 'type') === ['N1', 'ROLE', 'OFFICE_FIN'], 'order · Finance always ends up last, wherever it was put');
    [$ok, $why] = $save([['N2', '', '5000'], ['N1']]);
    t_ok(!$ok && strpos($why, 'upwards') !== false, 'order · the reporting line must go upwards (N+2 before N+1 is refused)');
    [$ok] = $save([['N1'], ['ROLE', 'SBU_HEAD'], ['ROLE', 'SBU_HEAD', '500'], ['OFFICE_FIN']]);
    t_ok(!$ok, 'order · the same role twice is refused');
    [$ok] = $save([['N1'], ['ROLE', 'ADMIN'], ['OFFICE_FIN']]);
    t_ok(!$ok, 'order · a role that is not on the list (Admin) is refused');
    [$ok] = $save([['N1', '', '', 1], ['ROLE', 'BRANCH_MANAGER', '', 3], ['ROLE', 'SBU_HEAD', '', 2], ['OFFICE_FIN', '', '', 4]]);
    t_eq(array_map(fn($l) => $l['role'] ?: $l['type'], (array) vappr_config()), ['N1', 'SBU_HEAD', 'BRANCH_MANAGER', 'OFFICE_FIN'], 'order · the Master Admin reorders levels with the order numbers');
    [$ok] = $save([['N1'], ['OFFICE_FIN']], ['add_role' => 'SBU_HEAD', 'add_above' => '10000']);
    $cfg = vappr_config();
    t_ok($ok && $cfg[1]['type'] === 'ROLE' && $cfg[1]['role'] === 'SBU_HEAD' && $cfg[1]['above'] === 10000.0 && $cfg[2]['type'] === 'OFFICE_FIN',
        '"Add a level by role" puts it after the others and before Finance, with its own amount');
    // A level already signed stays signed when the order changes.
    [$ok] = $save($example);
    $vO = bbv_voucher($f, $f['amit_ins'], 25000);
    bbv_submit($vO, $f['amit']);
    $approve($vO, $f['neha']);
    $save([['N1'], ['ROLE', 'BUSINESS_DIRECTOR', '20000'], ['N2', '', '5000'], ['OFFICE_FIN']]);
    $po = vappr_progress(bbv_v($vO));
    t_ok($po['levels'][0]['done'] !== null && $po['current'] === 1 && $po['levels'][1]['key'] === 'R:BUSINESS_DIRECTOR', 'order · a reorder keeps the levels already signed; the next one is the Director');

    // ---- Who holds the role: office scope; no holder → administrator -----------
    $save([['N1'], ['ROLE', 'BRANCH_MANAGER'], ['OFFICE_FIN']]);
    $vM = bbv_voucher($f, $f['amit_ins'], 100);
    $vA = bbv_voucher($f, $f['rahul_ins'], 100, 'DRAFT', '2031-05', $f['ahm']);
    $chM = vappr_chain(bbv_v($vM)); $chA = vappr_chain(bbv_v($vA));
    t_eq($ids($chM[1]['approvers']), [$f['kiran']], 'holders · the Mumbai Branch Manager signs a Mumbai voucher\'s Branch Manager level');
    t_ok(!$chA[1]['approvers'] && strpos($chA[1]['problem'], 'Ahmedabad') !== false, 'holders · …not an Ahmedabad one: nobody covers it there, and the problem says so');
    $save([['N1'], ['ROLE', 'BRANCH_APP_MANAGER'], ['OFFICE_FIN']]);
    $vN = bbv_voucher($f, $f['amit_ins'], 1200);
    bbv_submit($vN, $f['amit']);
    $approve($vN, $f['neha']);
    $pn = vappr_progress(bbv_v($vN));
    t_ok($pn['current'] === 1 && !$pn['levels'][1]['approvers'] && strpos($pn['levels'][1]['problem'], 'Nobody active holds the role') !== false,
        'no holder · the level waits for an administrator with a clear warning');
    [$ok] = $approve($vN, $f['sunil']); t_ok(!$ok, 'no holder · a manager outside the chain cannot take that level');
    bbv_as($f['admin']);
    $hit = array_values(array_filter(voucher_waiting_on_me(), fn($x) => (int) $x['id'] === $vN));
    t_ok($hit && strpos((string) $hit[0]['_warning'], 'Nobody active holds') !== false, 'no holder · it is in the administrator\'s inbox with the warning');
    $r = bbv_route('voucher-approvals', 'GET', $f['master'], 0);
    t_ok(!empty($r['view_ok']) && strpos($r['out'], 'Nobody active holds Branch Application Manager') !== false, 'no holder · the setup screen warns the Master Admin too');
    [$ok] = $approve($vN, $f['admin']);
    t_ok($ok && vappr_progress(bbv_v($vN))['current'] === 2, 'no holder · the administrator signs it; Finance still signs last');

    // ---- The setup screen ----------------------------------------------------
    $save($example);
    $r = bbv_route('voucher-approvals', 'GET', $f['master'], 0);
    t_ok(!empty($r['view_ok']) && strpos($r['out'], 'Add a level by role') !== false && strpos($r['out'], 'Business Director') !== false
        && strpos($r['out'], 'Nobody signs twice') !== false, 'the setup screen shows the role level, "Add a level by role" and the no-double-signing rule');
    $r = bbv_route('voucher-approvals', 'POST', $f['master'], 0, ['lv' => [1 => ['type' => 'N1', 'use' => '1', 'above' => '', 'order' => '1'],
        2 => ['type' => 'OFFICE_FIN', 'use' => '1', 'above' => '', 'order' => '1002']], 'add_role' => 'SBU_HEAD', 'add_above' => '15000']);
    // The route saved in its own process; read what it stored.
    $sc = &settings_cache(); $sc[VAPPR_SETTING] = (string) ops_val("SELECT svalue FROM settings WHERE skey=?", [VAPPR_SETTING]); unset($sc);
    $cfg = vappr_config();
    t_ok(($cfg[1]['role'] ?? '') === 'SBU_HEAD' && ($cfg[1]['above'] ?? null) === 15000.0, 'the real setup screen saves a new role level');

    // ---- Backward compatibility with the previous batch's setting ------------
    setting_set(VAPPR_SETTING, json_encode(['N1' => null, 'N2' => 5000, 'OFFICE_FIN' => null]));
    $cfg = vappr_config();
    t_eq(array_column((array) $cfg, 'type'), ['N1', 'N2', 'OFFICE_FIN'], 'backward compatible · the setting saved by the previous batch still reads, in its fixed order');
    t_eq($keys($v8), ['N1', 'N2', 'OFFICE_FIN'], 'backward compatible · …and gives the same chain (₹8,000 → N+1 → N+2 → Finance)');
    $r = bbv_route('voucher-approvals', 'GET', $f['master'], 0);
    t_ok(!empty($r['view_ok']), 'backward compatible · the setup screen opens on the old setting');
    bbv_as($f['master']); vappr_config_save(['reset' => 1]);
    t_ok(vappr_config() === null && $keys($v8) === ['N1', 'FIN'], 'amounts are never preset · back to the default N+1 → Finance');
} finally {
    if ($otherHolders) $pdo->exec("UPDATE users SET is_active=1 WHERE id IN (" . implode(',', $otherHolders) . ")");
    setting_set('custom_roles', $oldCustom);
    bbv_cleanup($f);
}
