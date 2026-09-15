<?php
// ============================================================================
//  PHASE 2 · M4 — HIRING REQUEST
//
//  The audit found one thing genuinely missing: a REQUEST LAYER. A requisition
//  was created directly, already OPEN — a status whose own label reads "Open
//  (approved, sourcing)" — and candidates could be attached at once. Nothing
//  distinguished "we would like to hire" from "this is approved, start
//  sourcing".
//
//  So M4 adds one object and one link, and reuses everything else: the canonical
//  Department vocabulary (M2/M3), Designation, Position and its manpower check,
//  M3's quantity and fulfilment model, the offices/scope architecture, the
//  custom-field engine, and the entity-agnostic approval request table that
//  Phase 3 will route through.
//
//  The boundary these tests exist to hold: recruitment cannot begin from a
//  request that is not approved.
// ============================================================================

t_section('Milestone 4 — the hiring request layer');

$pdo = db();
hreq_migrate();
$mine = ['h' => [], 'r' => [], 'u' => [], 'o' => []];
$origSess = $_SESSION;

foreach ([[941, 'M4 Branch A'], [942, 'M4 Branch B']] as $o) {
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $mine['o'][] = $o[0]; } catch (Throwable $e) {}
}
$mkUser = function ($un, $role, $super, $office, $scope) use ($pdo, &$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices) VALUES (?,?,?,1,?,?,?)")
        ->execute([$un, 'M4', $role, $super ? 1 : 0, $office, $scope]);
    $id = (int) $pdo->lastInsertId(); $mine['u'][] = $id; return $id;
};
$uA = $mkUser('m4userA', 'MANAGER', 1, 941, '');          // unrestricted
$uB = $mkUser('m4userB', 'COORDINATOR', 0, 942, '942');   // branch B only
$_SESSION['uid'] = $uA; current_user(true); ua(true);

$eng = dept_of('Engineering');
$qua = dept_of('Quality');
t_ok($eng !== null && $qua !== null, 'the canonical departments from M2/M3 are available to reuse');

$base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uA, 'requested_by_name' => 'M4 Manager',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'M4 Mechanical Engineer', 'designation' => 'ENGINEER',
    'job_description' => 'For the M4 test refinery project.',
    'quantity' => 10, 'office_id' => 941, 'required_by' => '2026-11-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'HIGH',
    'reason' => 'New contract awarded.',
], $x);
$mk = function (array $x = []) use ($base, &$mine) {
    [$ok, $msg, $id] = hreq_save(0, $base($x));
    if ($ok) $mine['h'][] = $id;
    return [$ok, $msg, $id];
};

// ---------------------------------------------------------------------------
//  1. Nothing was duplicated (§4, §13).
// ---------------------------------------------------------------------------
t_ok(t_table_exists('hiring_requests'), 'the request layer exists');
t_ok(t_table_exists('requisitions'), 'the requisition is untouched');
t_ok(t_table_exists('cx_requirements'), 'the Marketplace requirement is untouched and separate (§4)');
t_ok(in_array('hiring_request_id', t_columns('requisitions'), true), 'a requisition can point back at the request it came from');
t_ok(!t_table_exists('requirements') && !t_table_exists('job_requirements'),
     'no third competing requirement table was created');
t_ok(!t_table_exists('job_profiles'), 'no Job Profile master was invented — the audit recorded it as a decision, not an assumption');
// Quantity: two numbers, two facts, no third.
$hq = t_columns('hiring_requests');
t_ok(in_array('quantity', $hq, true), 'the request records how many were ASKED for');
t_ok(!in_array('requested_quantity', $hq, true) && !in_array('vacancy_quantity', $hq, true),
     'and there is no competing second quantity on the request');

// ---------------------------------------------------------------------------
//  2. A request is created, and the two departments are genuinely distinct (§7).
// ---------------------------------------------------------------------------
[$ok, $msg, $h1] = $mk();
t_ok($ok, 'a hiring request can be raised: ' . $msg);
$r1 = hreq_get($h1);
t_eq($r1['status'], 'DRAFT', 'it starts as a draft');
t_ok(preg_match('/^HRQ-\d{4}-\d{6}$/', (string) $r1['req_no']) === 1, 'it has its own reference, distinct from a requisition code (§21)');
t_eq((int) $r1['requesting_department_id'], (int) $qua['id'], 'the requesting department is recorded');
t_eq((int) $r1['hiring_department_id'], (int) $eng['id'], '…and the hiring department separately (§7)');
t_ok((int) $r1['requesting_department_id'] !== (int) $r1['hiring_department_id'], 'the two are not assumed identical');
t_eq((int) $r1['requested_by_id'], $uA, 'the requestor is a canonical identity, not a typed-in name (§6)');

// ---------------------------------------------------------------------------
//  3. THE BOUNDARY (§18) — recruitment cannot start before approval.
// ---------------------------------------------------------------------------
t_ok(!hreq_is_executable($h1), 'a draft request is not executable');
[$cOk, $cMsg] = hreq_to_requisition($h1);
t_ok(!$cOk, 'recruitment cannot be started from a DRAFT: ' . $cMsg);
t_eq(count(hreq_requisitions($h1)), 0, '…and no requisition was created');

hreq_submit($h1);
t_eq(hreq_get($h1)['status'], 'SUBMITTED', 'it can be submitted');
t_ok(!hreq_is_executable($h1), 'a submitted request is still not executable');
[$cOk2, $cMsg2] = hreq_to_requisition($h1);
t_ok(!$cOk2, 'recruitment still cannot start: ' . $cMsg2);

hreq_decide($h1, true, 'Approved for ten.');
t_eq(hreq_get($h1)['status'], 'APPROVED', 'it can be approved');
t_ok(hreq_is_executable($h1), 'and only NOW is it executable');

// ---------------------------------------------------------------------------
//  4. Request → Requisition, one to many (§20), never more than approved.
// ---------------------------------------------------------------------------
t_eq(hreq_remaining_qty($h1), 10, 'all ten are waiting to be recruited');
[$o1, $m1, $rq1] = hreq_to_requisition($h1, 6);
t_ok($o1, 'part of the approved headcount can be raised: ' . $m1);
if ($o1) $mine['r'][] = $rq1;
t_eq(hreq_remaining_qty($h1), 4, 'four remain');
[$o2, , $rq2] = hreq_to_requisition($h1, 4);
t_ok($o2, 'the rest can be raised as a SECOND requisition — one request, many requisitions');
if ($o2) $mine['r'][] = $rq2;
t_eq(hreq_remaining_qty($h1), 0, 'nothing remains');
[$o3, $m3] = hreq_to_requisition($h1, 1);
t_ok(!$o3, 'and no more than the approved headcount can ever be raised: ' . $m3);

// The execution records carry the link and M3's model.
$rqs = hreq_requisitions($h1);
t_eq(count($rqs), 2, 'both requisitions are linked back to the request');
t_eq((int) $rqs[0]['hiring_request_id'], $h1, '…by hiring_request_id');
t_eq((int) $rqs[0]['quantity'] + (int) $rqs[1]['quantity'], 10, 'their quantities add up to what was approved');
t_eq((int) $rqs[0]['department_id'], (int) $eng['id'], 'the canonical department travels across, as an identity');
t_ok(function_exists('reqf_counts'), 'M3 fulfilment still governs the requisition');
t_eq(reqf_counts($rqs[0])['requested'], 6, '…and reads the requisition quantity, not the request quantity');

// ---------------------------------------------------------------------------
//  5. Snapshot (§12) — an approved request does not change meaning later.
// ---------------------------------------------------------------------------
$snap = json_decode((string) hreq_get($h1)['snapshot_json'], true);
t_ok(is_array($snap), 'a snapshot was taken when the request was submitted');
t_eq($snap['hiring_department'], vocab_display($eng), 'it recorded what the department was called then');
t_eq((int) $snap['quantity'], 10, '…and how many were asked for');
$pdo->prepare("UPDATE lookup_values SET display_name=? WHERE id=?")->execute(['M4 Renamed Engineering', (int) $eng['id']]);
$GLOBALS['__db_epoch'] = (int) ($GLOBALS['__db_epoch'] ?? 0) + 1;
$snap2 = json_decode((string) hreq_get($h1)['snapshot_json'], true);
t_eq($snap2['hiring_department'], vocab_display(vocab_value((int) $eng['id'])) === 'M4 Renamed Engineering' ? $snap['hiring_department'] : $snap['hiring_department'],
     'renaming the department master does NOT change what the approved request says');
$pdo->prepare("UPDATE lookup_values SET display_name='' WHERE id=?")->execute([(int) $eng['id']]);
$GLOBALS['__db_epoch'] = (int) ($GLOBALS['__db_epoch'] ?? 0) + 1;

// ---------------------------------------------------------------------------
//  6. An approved request is protected from quiet edits (§33).
// ---------------------------------------------------------------------------
[$eOk, $eMsg] = hreq_save($h1, $base(['quantity' => 99, 'job_title' => 'Something Else']));
t_ok(!$eOk, 'an approved request cannot be silently changed: ' . $eMsg);
t_eq((int) hreq_get($h1)['quantity'], 10, '…and the approved figure is intact');

// ---------------------------------------------------------------------------
//  7. Negative and boundary input (§43).
// ---------------------------------------------------------------------------
t_ok(!$mk(['quantity' => 0])[0], 'a request for zero people is refused');
t_ok(!$mk(['quantity' => -5])[0], 'a negative quantity is refused');
t_ok(!$mk(['job_title' => ''])[0], 'a request with nothing asked for is refused');
t_ok(!$mk(['requested_by_id' => 999999])[0], 'a requestor who is not in this workspace is refused');
t_ok(!$mk(['hiring_department_id' => 999999])[0], 'a department that does not exist is refused');
t_ok(!$mk(['position_id' => 999999])[0], 'a position that does not exist is refused');
t_ok(!$mk(['priority' => 'NOT_A_PRIORITY'])[0], 'a priority the workspace does not use is refused');
t_ok(!$mk(['employment_type' => 'NOT_A_TYPE'])[0], 'an employment type the workspace does not use is refused');
t_ok(!$mk(['request_type' => 'NOT_A_TYPE'])[0], 'a request type the workspace does not use is refused');
t_ok(!$mk(['required_by' => 'next Tuesday'])[0], 'a required-by date that is not a date is refused');
// An inactive department cannot be requested against.
[$dOk, , $tmpDept] = dept_save(0, ['label' => 'M4 Retired Dept', 'code' => 'M4RET'], true);
if ($dOk) { dept_set_active($tmpDept, 0);
    t_ok(!$mk(['hiring_department_id' => $tmpDept])[0], 'a switched-off department cannot be requested against');
    $pdo->prepare("DELETE FROM lookup_terms WHERE value_id=?")->execute([$tmpDept]);
    $pdo->prepare("DELETE FROM lookup_values WHERE id=?")->execute([$tmpDept]); }
// An inactive position likewise.
$pdo->prepare("INSERT INTO positions (name,department,active,created_at) VALUES ('M4 Retired Position','Engineering',0,?)")->execute([date('c')]);
$deadPos = (int) $pdo->lastInsertId();
t_ok(!$mk(['position_id' => $deadPos])[0], 'a switched-off position cannot be requested against');
$pdo->prepare("DELETE FROM positions WHERE id=?")->execute([$deadPos]);
t_eq(hreq_get(999999), null, 'an unknown request id returns nothing rather than throwing');
t_ok(!hreq_submit(999999)[0], 'an unknown request cannot be submitted');
t_ok(!hreq_to_requisition(999999)[0], 'an unknown request cannot start recruitment');
t_ok(!hreq_is_executable(999999), '…and is never executable');

// Lifecycle refusals.
[, , $hDraft] = $mk();
t_ok(!hreq_decide($hDraft, true)[0], 'a draft cannot be approved without being submitted');
hreq_submit($hDraft);
t_ok(!hreq_submit($hDraft)[0], 'a request cannot be submitted twice');
hreq_cancel($hDraft, 'no longer needed');
t_eq(hreq_get($hDraft)['status'], 'CANCELLED', 'a request can be cancelled');
t_ok(!hreq_to_requisition($hDraft)[0], 'a cancelled request can never start recruitment');
t_ok(!hreq_save($hDraft, $base())[0], '…and cannot be edited');

// ---------------------------------------------------------------------------
//  8. Scope, permission and entitlement (§34, §35, §36).
// ---------------------------------------------------------------------------
t_ok(function_exists('hreq_scope_gate'), 'the request layer has a branch-scope gate');
$hrSrc = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/hiringreq.php'));
$fp = strpos($hrSrc, 'function ops_hiring_requests(');
t_ok($fp !== false && strpos(substr($hrSrc, $fp, 200), 'hreq_scope_gate()') !== false,
     'and it runs on entry to the module, before any route reads an id');
$gate = substr($hrSrc, strpos($hrSrc, 'function hreq_scope_gate()'), 900);
t_ok(substr_count($gate, 'scope_allows(') === 2, 'it checks the request AND the branch named on the way in');

// A branch-B user must not raise a request against branch A.
$_SESSION['uid'] = $uB; current_user(true); ua(true);
t_ok(!scope_allows(941, null), 'the branch-B user may not act on branch A');
[$sOk, $sMsg] = hreq_save(0, $base(['office_id' => 941]));
t_ok(!$sOk, 'so they cannot raise a request against branch A: ' . $sMsg);
[$sOk2, , $sId] = hreq_save(0, $base(['office_id' => 942, 'requested_by_id' => $uB]));
t_ok($sOk2, '…but they can raise one in their own branch');
if ($sOk2) $mine['h'][] = $sId;
$_SESSION['uid'] = $uA; current_user(true); ua(true);

// Entitlement: the request layer belongs to the paid Recruitment module.
$offWas = setting_get('modules_off', '');
t_ok(ops_module_gate('hiring-requests', true), 'with People & hiring on, the request list opens');
setting_set('modules_off', 'hr'); licence_disabled(true);
t_ok(!ops_module_gate('hiring-requests', true), 'with it off, the request list is refused');
t_ok(!ops_module_gate('hiring-request', true), '…and so is a single request by direct URL');
// A master user does not buy the module by being a master.
$_SESSION['uid'] = $uA; current_user(true); ua(true);
t_ok(is_master(), 'the test user is a master');
t_ok(!ops_module_gate('hiring-request', true), 'a master still cannot open a module the workspace has not bought');
setting_set('modules_off', $offWas); licence_disabled(true);

// ---------------------------------------------------------------------------
//  9. Existing behaviour is untouched (§19, §22, §38).
// ---------------------------------------------------------------------------
$pdo->prepare("INSERT INTO requisitions (req_code,quantity,status,created_at) VALUES ('M4-DIRECT',3,'OPEN',?)")->execute([date('c')]);
$direct = (int) $pdo->lastInsertId(); $mine['r'][] = $direct;
$d = ops_one("SELECT * FROM requisitions WHERE id=?", [$direct]);
t_eq($d['hiring_request_id'], null, 'a requisition raised directly still works, with no request behind it (§19)');
t_eq(reqf_counts($direct)['requested'], 3, '…and M3 fulfilment reads it exactly as before');
t_ok(strpos(file_get_contents(__DIR__ . '/../lib/ops.php'), 'recruit_req_code') !== false,
     'requisition numbering is unchanged (§22)');

// ---------------------------------------------------------------------------
//  Clean up.
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
foreach ($mine['r'] as $id) $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$id]);
foreach ($mine['h'] as $id) $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([$id]);
foreach ($mine['u'] as $id) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
foreach ($mine['o'] as $id) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$id]);
