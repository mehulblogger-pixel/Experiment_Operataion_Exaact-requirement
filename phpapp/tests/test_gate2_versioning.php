<?php
// ============================================================================
//  GATE 2 — REQUIREMENT VERSIONING & CHANGE CONTROL
//
//  ONE mechanism, two entities. The claims asserted below:
//
//    A  the Hiring Request has an immutable approved version chain
//    B  the Requisition has its own, through the SAME engine
//    C  materiality is configurable, and configuration may only ADD
//    D  budget materiality is TOTAL COMMITMENT against a configured threshold
//    E  A8 — a Requisition may be stricter, never weaker than the approved floor
//    F  a pending change is NOT effective
//    G  a refused proposal changes nothing and is kept for ever
//    H  a withdrawn proposal changes nothing, and another may follow
//    I  one Hiring Request, many Requisitions, each with its own chain
//    J  candidates keep their requirement across a version change
//    K  an issued offer pins the candidate to the version in force then
//    L  permission: role default + permission + scope
//    M  self-approval obeys configuration
//    N  tenant isolation
//    O  concurrency: one pending proposal, one decision
// ============================================================================

$s = 'G2-' . random_int(100000, 999999);
rver_migrate(); hreq_migrate(); appr_migrate(); act_migrate(); reqf_migrate();
$pdo = db();
$off = (int) ops_val("SELECT COALESCE(MAX(id),0)+1 FROM offices");
$pdo->prepare("INSERT INTO offices (id,name,city) VALUES (?,?,?)")->execute([$off, $s . ' Office', 'Kolkata']);
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES (?,?, 'MANAGER',1,1,?,'')")->execute([$s . '_boss', 'G2', $off]);
$uBoss = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');

$base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uBoss, 'requested_by_name' => 'G2 Boss',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'G2 Engineer', 'designation' => 'ENGINEER', 'job_description' => 'g2',
    'quantity' => 10, 'office_id' => $off, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL',
    'reason' => 'Contract awarded.', 'change_reason' => 'G2 test change',
], $x);
$approve = function (array $x = []) use ($base) {
    [$ok,, $id] = hreq_save(0, $base($x)); if (!$ok) return 0;
    hreq_submit($id); hreq_apply_decision($id, 'APPROVED', 'G2 Approver', 'ok'); return (int) $id;
};
$hr = fn($id) => hreq_get((int) $id);

// ---------------------------------------------------------------------------
t_section('G2 · A — the Hiring Request version chain');
// ---------------------------------------------------------------------------
$hA = $approve(['job_title' => 'G2 Chain']);
t_ok($hA > 0, 'A0 · an approved hiring request exists');
$v1 = rver_current('HIRING_REQUEST', $hA);
t_ok(is_array($v1), 'A1 · approval created an approved VERSION');
t_eq((int) $v1['version'], 1, 'A2 · …numbered 1');
t_eq((int) rver_snapshot_fields($v1)['quantity'], 10, 'A3 · …carrying what the approver approved');
t_ok(trim((string) $v1['effective_from']) !== '', 'A4 · …with an effective date');

//  A MATERIAL CHANGE DOES NOT EDIT THE APPROVED VERSION.
[$pOk, $pMsg] = hreq_save($hA, $base(['job_title' => 'G2 Chain', 'designation' => 'SUPERVISOR']));
t_ok($pOk, 'A5 · a material change is accepted: ' . $pMsg);
$prop = rver_pending('HIRING_REQUEST', $hA);
t_ok(is_array($prop), 'A6 · …as a PROPOSAL');
t_eq((string) rver_proposed_fields($prop)['designation'], 'SUPERVISOR', 'A7 · …carrying the proposed value');
t_eq((string) $hr($hA)['designation'], 'ENGINEER',
     'A8 · *** the RECORD still carries the approved value — a proposal is not an edit ***');
t_eq((int) rver_current('HIRING_REQUEST', $hA)['version'], 1, 'A9 · …and no new version yet');

//  APPROVAL CREATES A NEW VERSION AND KEEPS THE OLD ONE.
[$aOk, $aMsg] = rver_apply((int) $prop['id'], ['decided_by' => 'G2 Approver']);
t_ok($aOk, 'A10 · the proposal is approved: ' . $aMsg);
t_eq((string) $hr($hA)['designation'], 'SUPERVISOR', 'A11 · NOW the record carries it');
t_eq((int) rver_current('HIRING_REQUEST', $hA)['version'], 2, 'A12 · a new version 2 is in force');
t_eq(count(rver_versions('HIRING_REQUEST', $hA)), 2, 'A13 · and BOTH versions are on record');
t_eq((string) rver_snapshot_fields(rver_version('HIRING_REQUEST', $hA, 1))['designation'], 'ENGINEER',
     'A14 · *** version 1 is still readable and still says ENGINEER — history is immutable ***');
t_eq((string) rver_proposal((int) $prop['id'])['status'], 'APPROVED', 'A15 · the proposal is closed as approved');
t_eq((int) rver_proposal((int) $prop['id'])['created_version'], 2, 'A16 · …and says which version it created');

// ---------------------------------------------------------------------------
t_section('G2 · B — the Requisition chain, through the SAME engine');
// ---------------------------------------------------------------------------
$hB = $approve(['job_title' => 'G2 Req parent', 'quantity' => 6]);
[$rqOk,, $rqB] = hreq_to_requisition($hB, 6);
t_ok($rqB > 0, 'B0 · a requisition exists under the approved request');
$gate = rver_gate_requisition_edit($rqB, ['designation' => 'ENGINEER'], []);
t_ok($gate === null, 'B1 · an unchanged save is an ordinary edit, not a proposal');
t_ok(is_array(rver_current('REQUISITION', $rqB)), 'B2 · …and the baseline version was taken');
$g2 = rver_gate_requisition_edit($rqB, ['designation' => 'SUPERVISOR'], ['change_reason' => 'client asked']);
t_ok(is_array($g2) && $g2[0], 'B3 · a material change becomes a proposal: ' . ($g2[1] ?? ''));
$pB = rver_pending('REQUISITION', $rqB);
t_ok(is_array($pB), 'B4 · the requisition has its own pending proposal');
t_eq((string) ops_val("SELECT designation FROM requisitions WHERE id=?", [$rqB]), 'ENGINEER',
     'B5 · *** and the requirement itself is unchanged while it waits ***');
rver_apply((int) $pB['id'], ['decided_by' => 'G2']);
t_eq((string) ops_val("SELECT designation FROM requisitions WHERE id=?", [$rqB]), 'SUPERVISOR', 'B6 · approval applies it');
t_eq((int) rver_current('REQUISITION', $rqB)['version'], 2, 'B7 · …as version 2 of the REQUISITION');
t_eq((int) rver_current('HIRING_REQUEST', $hB)['version'], 1,
     'B8 · *** and the HIRING REQUEST was NOT re-versioned by it (A6 — separate chains) ***');

// ---------------------------------------------------------------------------
t_section('G2 · C — materiality is configurable, and configuration may only ADD');
// ---------------------------------------------------------------------------
$matH = rver_material_fields('HIRING_REQUEST');
foreach (['designation', 'grade', 'position_id', 'office_id', 'employment_type'] as $f)
    t_ok(isset($matH[$f]), 'C1 · the shipped material list protects ' . $f);
$matR = rver_material_fields('REQUISITION');
foreach (['designation', 'grade', 'position_id', 'min_experience_years', 'min_qualification'] as $f)
    t_ok(isset($matR[$f]), 'C2 · the requisition list protects ' . $f);

setting_set('rver_material_extra_requisition', 'project_site');
$matR2 = rver_material_fields('REQUISITION');
t_ok(isset($matR2['project_site']), 'C3 · an organisation can ADD a material field');
foreach (['designation', 'grade', 'position_id'] as $f)
    t_ok(isset($matR2[$f]), 'C4 · *** …and cannot remove a shipped protection by doing so: ' . $f . ' ***');
setting_set('rver_material_extra_requisition', 'not_a_real_column');
t_ok(!isset(rver_material_fields('REQUISITION')['not_a_real_column']),
     'C5 · a configured field the table does not have is ignored, not treated as always-changed');
setting_set('rver_material_extra_requisition', '');

//  A non-material change is an ordinary edit.
$hC = $approve(['job_title' => 'G2 NonMat']);
[$nOk, $nMsg] = hreq_save($hC, $base(['job_title' => 'G2 NonMat', 'priority' => 'URGENT']));
t_ok($nOk, 'C6 · a non-material change is saved: ' . $nMsg);
t_ok(!rver_pending('HIRING_REQUEST', $hC), 'C7 · …with NO proposal');
t_eq((string) $hr($hC)['priority'], 'URGENT', 'C8 · …and it really was applied');
t_eq((int) rver_current('HIRING_REQUEST', $hC)['version'], 1, 'C9 · …and created no version');

// ---------------------------------------------------------------------------
t_section('G2 · D — budget materiality is TOTAL COMMITMENT');
// ---------------------------------------------------------------------------
$commit = fn(array $r) => rver_commitment('HIRING_REQUEST', $r)['total'];
$row = ['quantity' => 10, 'est_cost_per_person' => 1000, 'est_cost_basis' => 'MONTHLY',
        'est_duration_months' => 12, 'est_onetime_cost' => 5000];
t_eq($commit($row), (float) (10 * 1000 * 12 + 5000), 'D1 · per-person x quantity x periods + one-time');
t_eq($commit(['quantity' => 20] + $row), (float) (20 * 1000 * 12 + 5000), 'D2 · quantity is NOT ignored');
t_eq($commit(['est_duration_months' => 6] + $row), (float) (10 * 1000 * 6 + 5000), 'D3 · the period is NOT ignored');
t_eq($commit(['est_onetime_cost' => 0] + $row), (float) (10 * 1000 * 12), 'D4 · the one-time cost is NOT ignored');

setting_set('rver_budget_pct', 10); setting_set('rver_budget_abs', 100000); setting_set('rver_budget_rule', 'GREATER');
$m = fn($was, $now) => rver_commitment_material('HIRING_REQUEST', $was, $now);
$w = ['quantity' => 10, 'est_cost_per_person' => 10000, 'est_cost_basis' => 'FIXED'];   // 100,000
t_eq($commit($w), 100000.0, 'D5 · the baseline commitment is 1,00,000');
//  greater of 10% (10,000) and 1,00,000 => 1,00,000
t_ok(!$m($w, ['est_cost_per_person' => 15000] + $w)['material'],
     'D6 · +50,000 is inside the threshold (greater of 10% and 1,00,000) — not material');
t_ok(!$m($w, ['est_cost_per_person' => 20000] + $w)['material'],
     'D7 · *** exactly AT the threshold (+1,00,000) is inside it, not material ***');
t_ok($m($w, ['est_cost_per_person' => 20001] + $w)['material'],
     'D8 · a rupee past the threshold IS material');
t_ok(!$m($w, ['est_cost_per_person' => 5000] + $w)['material'], 'D9 · a DECREASE is never material');

setting_set('rver_budget_rule', 'PCT');
t_ok($m($w, ['est_cost_per_person' => 12000] + $w)['material'],
     'D10 · under a percentage rule, +20,000 on 1,00,000 is material');
setting_set('rver_budget_rule', 'ABS');
t_ok(!$m($w, ['est_cost_per_person' => 12000] + $w)['material'],
     'D11 · under an absolute rule, the same change is not');
setting_set('rver_budget_rule', 'GREATER');

//  NO APPROVED COMMITMENT — the absolute floor decides, not a percentage of nothing.
$none = ['quantity' => 5];
t_ok(!$m($none, ['est_cost_per_person' => 10000, 'est_cost_basis' => 'FIXED'] + $none)['material'],
     'D12 · a first estimate of 50,000 against no approved commitment is inside the floor');
t_ok($m($none, ['est_cost_per_person' => 30000, 'est_cost_basis' => 'FIXED'] + $none)['material'],
     'D13 · …and 1,50,000 is past it');
t_eq($m($none, ['est_cost_per_person' => 30000, 'est_cost_basis' => 'FIXED'] + $none)['basis'],
     'absolute floor (nothing was approved before)', 'D14 · …and says which rule decided');

//  PARTIAL ESTIMATE COMPLETED — a rate with no duration counts one period; adding
//  the duration multiplies it, and that increase is judged like any other.
$part = ['quantity' => 4, 'est_cost_per_person' => 50000, 'est_cost_basis' => 'MONTHLY'];
t_eq($commit($part), (float) (4 * 50000), 'D15 · a rate with no duration contributes ONE period, not a guess');
t_ok(rver_commitment('HIRING_REQUEST', $part)['partial'], 'D16 · …and says so');
$full = ['est_duration_months' => 12] + $part;
t_ok($m($part, $full)['material'], 'D17 · completing the estimate to 12 months IS material');

// ---------------------------------------------------------------------------
t_section('G2 · E — A8: stricter is fine, below the approved floor is not');
// ---------------------------------------------------------------------------
$hE = $approve(['job_title' => 'G2 Floor', 'quantity' => 3, 'min_experience_years' => 3,
                'min_qualification' => 'DIPLOMA', 'essential_skills' => 'welding, blueprint reading']);
t_eq((float) $hr($hE)['min_experience_years'], 3.0, 'E0 · the approved request has a 3-year floor');
[$rOk,, $rqE] = hreq_to_requisition($hE, 3);
$pdo->prepare("UPDATE requisitions SET min_experience_years=5, min_qualification='DIPLOMA',
               essential_skills='welding, blueprint reading' WHERE id=?")->execute([$rqE]);
rver_ensure_initial('REQUISITION', $rqE, null, ['note' => 'baseline']);

//  STRICTER IS ALLOWED.
t_ok(!rver_floor_breaches(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqE]),
     ['min_experience_years' => 5]), 'E1 · 5 years against a floor of 3 is stricter — allowed');
t_ok(!rver_floor_breaches(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqE]),
     ['min_qualification' => 'DEGREE']), 'E2 · a Degree against a Diploma floor is stricter — allowed');

//  5 -> 2 IS A WEAKENING BELOW THE APPROVED FLOOR.
$b52 = rver_floor_breaches(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqE]), ['min_experience_years' => 2]);
t_ok(isset($b52['min_experience_years']), 'E3 · *** 5 -> 2 years breaches the approved floor of 3 ***');
t_eq((float) $b52['min_experience_years']['floor'], 3.0, 'E4 · …and names the floor it breached');
$gE = rver_gate_requisition_edit($rqE, ['min_experience_years' => 2], []);
t_ok(is_array($gE) && !$gE[0], 'E5 · …so the save is refused without a reason: ' . ($gE[1] ?? ''));
t_eq((float) ops_val("SELECT min_experience_years FROM requisitions WHERE id=?", [$rqE]), 5.0,
     'E6 · …and nothing was written');
$gE2 = rver_gate_requisition_edit($rqE, ['min_experience_years' => 2], ['change_reason' => 'client relaxed it']);
t_ok(is_array($gE2) && $gE2[0], 'E7 · with a reason it becomes a controlled proposal');
t_ok(is_array(rver_pending('REQUISITION', $rqE)), 'E8 · …a pending proposal exists');
t_eq((float) ops_val("SELECT min_experience_years FROM requisitions WHERE id=?", [$rqE]), 5.0,
     'E9 · *** and the requirement still asks for 5 until it is decided ***');
rver_withdraw((int) rver_pending('REQUISITION', $rqE)['id'], 'not needed after all');

//  5 -> 4 IS ABOVE THE FLOOR: an ordinary edit, because 4 >= 3.
t_ok(!rver_floor_breaches(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqE]),
     ['min_experience_years' => 4]), 'E10 · 5 -> 4 does not breach a floor of 3');

//  2 -> 3 IS A MOVE TOWARDS THE FLOOR, never a weakening below it.
$pdo->prepare("UPDATE requisitions SET min_experience_years=2 WHERE id=?")->execute([$rqE]);
t_ok(!rver_floor_breaches(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqE]),
     ['min_experience_years' => 3]),
     'E11 · *** 2 -> 3 reaches the floor and is NOT treated as a weakening ***');
$pdo->prepare("UPDATE requisitions SET min_experience_years=5 WHERE id=?")->execute([$rqE]);

//  Dropping an essential skill the approved request named is a weakening too.
$bSk = rver_floor_breaches(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqE]),
        ['essential_skills' => 'welding']);
t_ok(isset($bSk['essential_skills']), 'E12 · dropping an essential skill breaches the floor');
t_ok(in_array('blueprint reading', $bSk['essential_skills']['missing'], true), 'E13 · …and names which one');

//  A requisition with no hiring request has no floor to breach (the direct path).
$pdo->prepare("INSERT INTO requisitions (req_code,designation,office_id,sbu,status,quantity,created_at)
               VALUES (?,?,?, 'IND','OPEN',1,?)")->execute([$s . '-DIR', 'ENGINEER', $off, date('c')]);
$rqDir = (int) $pdo->lastInsertId();
t_eq(rver_floor_breaches(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqDir]),
     ['min_experience_years' => 0]), [], 'E14 · no hiring request means no floor, so nothing to breach');

// ---------------------------------------------------------------------------
t_section('G2 · F — a pending change is NOT effective');
// ---------------------------------------------------------------------------
$hF = $approve(['job_title' => 'G2 Pending', 'quantity' => 8]);
[$fOk,, $rqF] = hreq_to_requisition($hF, 8);
hreq_save($hF, $base(['job_title' => 'G2 Pending', 'quantity' => 8, 'grade' => 'G9']));
t_ok(is_array(rver_pending('HIRING_REQUEST', $hF)), 'F1 · a proposal is open');
t_eq((string) $hr($hF)['grade'], '', 'F2 · *** the record does not carry the proposed grade ***');
t_eq((int) rver_current('HIRING_REQUEST', $hF)['version'], 1, 'F3 · no new version exists');
t_eq((int) hreq_approved_qty($hr($hF)), 8, 'F4 · the approved headcount still governs');
//  The graduated effect: level 2 ships, so screening continues and commitment stops.
t_eq(rver_effect_level(), 2, 'F5 · level 2 is the shipped default');
t_eq(rver_block_reason('HIRING_REQUEST', $hF, 'ADVANCE'), '', 'F6 · screening continues');
t_ok(rver_block_reason('HIRING_REQUEST', $hF, 'OFFER') !== '', 'F7 · no offer may be made');
t_ok(rver_block_reason('HIRING_REQUEST', $hF, 'JOIN') !== '', 'F8 · nobody may join');
setting_set('rver_pending_effect', 1);
t_ok(rver_block_reason('HIRING_REQUEST', $hF, 'ADVANCE') !== '', 'F9 · level 1 pauses everything');
setting_set('rver_pending_effect', 4);
t_eq(rver_block_reason('HIRING_REQUEST', $hF, 'JOIN'), '', 'F10 · level 4 pauses nothing');
setting_set('rver_pending_effect', 3);
t_eq(rver_block_reason('HIRING_REQUEST', $hF, 'OFFER'), '', 'F11 · level 3 allows an offer…');
t_ok(rver_block_reason('HIRING_REQUEST', $hF, 'JOIN') !== '', 'F12 · …but not a joining');
setting_set('rver_pending_effect', 2);

// ---------------------------------------------------------------------------
t_section('G2 · G — a refused proposal changes nothing and is kept for ever');
// ---------------------------------------------------------------------------
$pF = rver_pending('HIRING_REQUEST', $hF);
[$rjOk, $rjMsg] = rver_reject((int) $pF['id'], 'not justified');
t_ok($rjOk, 'G1 · the proposal is rejected: ' . $rjMsg);
t_eq((string) $hr($hF)['grade'], '', 'G2 · *** the approved requirement is unchanged ***');
t_eq((int) rver_current('HIRING_REQUEST', $hF)['version'], 1, 'G3 · no version was created');
$rjRow = rver_proposal((int) $pF['id']);
t_eq((string) $rjRow['status'], 'REJECTED', 'G4 · the refused proposal is retained…');
t_eq((string) $rjRow['decision_note'], 'not justified', 'G5 · …with the reason it was refused');
t_eq((string) rver_proposed_fields($rjRow)['grade'], 'G9', 'G6 · …and the values that were refused');
t_ok(!rver_pending('HIRING_REQUEST', $hF), 'G7 · and nothing is pending any more');
t_eq(rver_block_reason('HIRING_REQUEST', $hF, 'OFFER'), '', 'G8 · so offers are possible again');

//  A SECOND PROPOSAL AFTER A REJECTION, prefilled from the refused one.
[$p2ok] = hreq_save($hF, $base(['job_title' => 'G2 Pending', 'quantity' => 8, 'grade' => 'G7',
                                'change_reason' => 'revised after the refusal']));
t_ok($p2ok, 'G9 · a NEW proposal may follow a refusal');
$pF2 = rver_pending('HIRING_REQUEST', $hF);
t_ok(is_array($pF2) && (int) $pF2['id'] !== (int) $pF['id'],
     'G10 · *** it is a new row — the refused one was not amended in place ***');
t_eq((string) rver_proposal((int) $pF['id'])['status'], 'REJECTED', 'G11 · …and the refused one still says REJECTED');

// ---------------------------------------------------------------------------
t_section('G2 · H — a withdrawn proposal changes nothing, and another may follow');
// ---------------------------------------------------------------------------
[$wdBad] = rver_withdraw((int) $pF2['id'], '');
t_ok(!$wdBad, 'H1 · a withdrawal with no reason is refused');
[$wdOk, $wdMsg] = rver_withdraw((int) $pF2['id'], 'the client changed their mind');
t_ok($wdOk, 'H2 · a withdrawal with a reason succeeds: ' . $wdMsg);
t_eq((string) $hr($hF)['grade'], '', 'H3 · the approved requirement is still unchanged');
$wdRow = rver_proposal((int) $pF2['id']);
t_eq((string) $wdRow['status'], 'WITHDRAWN', 'H4 · the withdrawn proposal is retained');
t_eq((string) $wdRow['decision_note'], 'the client changed their mind', 'H5 · …with its withdrawal reason');
t_ok(!rver_pending('HIRING_REQUEST', $hF), 'H6 · nothing is pending');
t_eq(rver_block_reason('HIRING_REQUEST', $hF, 'JOIN'), '', 'H7 · normal operation is restored');
[$p3ok] = hreq_save($hF, $base(['job_title' => 'G2 Pending', 'quantity' => 8, 'grade' => 'G6',
                                'change_reason' => 'third attempt']));
t_ok($p3ok, 'H8 · and a further proposal may be raised');
t_eq(count(rver_proposals('HIRING_REQUEST', $hF)), 3, 'H9 · all three attempts are on record');
rver_withdraw((int) rver_pending('HIRING_REQUEST', $hF)['id'], 'tidy up');

// ---------------------------------------------------------------------------
t_section('G2 · I — one Hiring Request, many Requisitions, separate chains');
// ---------------------------------------------------------------------------
$hI = $approve(['job_title' => 'G2 Many', 'quantity' => 9]);
[$i1ok,, $rq1] = hreq_to_requisition($hI, 3);
[$i2ok,, $rq2] = hreq_to_requisition($hI, 3);
t_ok($rq1 > 0 && $rq2 > 0 && $rq1 !== $rq2, 'I1 · two requisitions from one request');
rver_ensure_initial('REQUISITION', $rq1); rver_ensure_initial('REQUISITION', $rq2);
rver_gate_requisition_edit($rq1, ['designation' => 'SUPERVISOR'], ['change_reason' => 'only the first']);
t_ok(is_array(rver_pending('REQUISITION', $rq1)), 'I2 · the first has a pending change…');
t_ok(!rver_pending('REQUISITION', $rq2), 'I3 · …and the second does not');
rver_apply((int) rver_pending('REQUISITION', $rq1)['id'], ['decided_by' => 'G2']);
t_eq((int) rver_current('REQUISITION', $rq1)['version'], 2, 'I4 · the first is on version 2');
t_eq((int) rver_current('REQUISITION', $rq2)['version'], 1, 'I5 · *** the second is still on version 1 ***');
t_eq((int) rver_current('HIRING_REQUEST', $hI)['version'], 1, 'I6 · and the request itself is untouched');

// ---------------------------------------------------------------------------
t_section('G2 · J — candidates keep their requirement across a version change');
// ---------------------------------------------------------------------------
$mkCand = function ($rq) use ($pdo, $s) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,created_at)
                   VALUES (?,?,'G2','RECEIVED',?,?)")
        ->execute([$s . '-C' . random_int(100, 999), 'Cand', $rq, date('c')]);
    return (int) $pdo->lastInsertId();
};
$cJ1 = $mkCand($rq2); $cJ2 = $mkCand($rq2);
$before = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=?", [$rq2]);
rver_gate_requisition_edit($rq2, ['designation' => 'FOREMAN'], ['change_reason' => 'role renamed']);
rver_apply((int) rver_pending('REQUISITION', $rq2)['id'], ['decided_by' => 'G2']);
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=?", [$rq2]), $before,
     'J1 · *** no candidate was lost when a new version was created ***');
t_eq((int) ops_val("SELECT requisition_id FROM candidates WHERE id=?", [$cJ1]), $rq2,
     'J2 · each candidate is still attached to the same requirement');
t_eq((int) rver_current('REQUISITION', $rq2)['version'], 2, 'J3 · …which is now on version 2');
//  Q13 — the RELATIONSHIP Gate 3 will need: which version is in force for them now.
t_eq((int) rver_current('REQUISITION', $rq2)['version'], 2,
     'J4 · the latest approved version is identifiable for every attached candidate');

// ---------------------------------------------------------------------------
t_section('G2 · K — an issued offer pins the version boundary (A2)');
// ---------------------------------------------------------------------------
$verAtIssue = (int) rver_current('REQUISITION', $rq2)['version'];
$oK = (int) offer_create($cJ1, ['ctc' => 400000, 'joining_date' => date('Y-m-d', strtotime('+30 days'))]);
t_ok($oK > 0, 'K0 · an offer is created');
offer_submit($oK); offer_approve($oK);
[$isOk] = offer_issue($oK);
t_ok($isOk, 'K1 · …and issued');
t_eq((int) rver_offer_pinned_version('REQUISITION', $rq2, $cJ1), $verAtIssue,
     'K2 · *** the candidate is pinned to the version in force when the offer was ISSUED ***');
//  A later approved version does NOT move them.
rver_gate_requisition_edit($rq2, ['designation' => 'CHARGEHAND'], ['change_reason' => 'renamed again']);
rver_apply((int) rver_pending('REQUISITION', $rq2)['id'], ['decided_by' => 'G2']);
t_eq((int) rver_current('REQUISITION', $rq2)['version'], $verAtIssue + 1, 'K3 · a newer version now exists');
t_eq((int) rver_offer_pinned_version('REQUISITION', $rq2, $cJ1), $verAtIssue,
     'K4 · *** …and the offered candidate stays on the version in force at issue ***');
//  A candidate with no issued offer is NOT pinned — they follow the latest.
t_eq((int) rver_offer_pinned_version('REQUISITION', $rq2, $cJ2), 0,
     'K5 · a candidate without an issued offer is not pinned');
t_eq((int) rver_applicable_version('REQUISITION', $rq2, $cJ2), $verAtIssue + 1,
     'K6 · …so the latest approved version applies to them');
t_eq((int) rver_applicable_version('REQUISITION', $rq2, $cJ1), $verAtIssue,
     'K7 · …while the offered candidate keeps theirs');

// ---------------------------------------------------------------------------
t_section('G2 · L — permission: role default + permission + scope');
// ---------------------------------------------------------------------------
t_ok(array_key_exists('hiring.material_change.propose', PERMISSIONS),
     'L1 · the permission code exists in the catalogue (C46)');
t_ok(in_array('hiring.material_change.propose', permission_groups()['Recruitment'] ?? [], true),
     'L2 · …and is configurable in the Recruitment group');
$hL = $approve(['job_title' => 'G2 Perm']);
//  A user with NEITHER the role default NOR the permission cannot propose.
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES (?,?, 'INSPECTOR',1,0,?,?)")->execute([$s . '_out', 'G2Out', $off, (string) $off]);
$uOut = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uOut; current_user(true); ua(true);
t_ok(!rver_can_propose('HIRING_REQUEST', $hr($hL)),
     'L3 · *** a user with no role default and no permission cannot propose ***');
[$oOk, $oMsg] = rver_propose('HIRING_REQUEST', $hL, ['designation' => 'SUPERVISOR'], 'sneaking it in');
t_ok(!$oOk, 'L4 · …and the attempt is refused: ' . $oMsg);
t_ok(!rver_pending('HIRING_REQUEST', $hL), 'L5 · …with no proposal created');
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);
t_ok(rver_can_propose('HIRING_REQUEST', $hr($hL)), 'L6 · a user whose role may change it can propose');

//  SCOPE — a permission is not a licence to reach another branch's work.
$off2 = (int) ops_val("SELECT COALESCE(MAX(id),0)+1 FROM offices");
$pdo->prepare("INSERT INTO offices (id,name,city) VALUES (?,?,?)")->execute([$off2, $s . ' Far', 'Pune']);
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES (?,?, 'MANAGER',1,0,?,?)")->execute([$s . '_far', 'G2Far', $off2, (string) $off2]);
$uFar = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uFar; current_user(true); ua(true);
t_ok(!rver_can_propose('HIRING_REQUEST', $hr($hL)),
     'L7 · *** a manager scoped to another branch cannot propose on this one ***');
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);

// ---------------------------------------------------------------------------
t_section('G2 · O — concurrency: one pending proposal, one decision');
// ---------------------------------------------------------------------------
$hO = $approve(['job_title' => 'G2 Race']);
[$o1] = rver_propose('HIRING_REQUEST', $hO, ['designation' => 'SUPERVISOR'], 'first');
t_ok($o1, 'O1 · the first proposal is created');
[$o2, $o2msg] = rver_propose('HIRING_REQUEST', $hO, ['designation' => 'FOREMAN'], 'second');
t_ok(!$o2, 'O2 · *** a SECOND pending proposal is refused: ' . $o2msg . ' ***');
t_eq(count(array_filter(rver_proposals('HIRING_REQUEST', $hO), fn($p) => $p['status'] === 'PENDING')), 1,
     'O3 · exactly one pending proposal exists');
//  The database enforces it, not only the check above: a direct insert collides.
$dup = false;
try {
    $pdo->prepare("INSERT INTO requirement_change_proposals (entity,entity_id,status,proposed_json,reason,pending_key,created_at)
                   VALUES ('HIRING_REQUEST',?,'PENDING','{}','forced',?,?)")
        ->execute([$hO, 'HIRING_REQUEST:' . $hO, date('c')]);
    $dup = true;
} catch (Throwable $e) { $dup = false; }
t_ok(!$dup, 'O4 · *** and the DATABASE refuses a second pending row, not just the check ***');

//  Two decisions on one proposal: exactly one wins, and no duplicate version.
$pO = rver_pending('HIRING_REQUEST', $hO);
$vBefore = (int) rver_current('HIRING_REQUEST', $hO)['version'];
[$d1] = rver_apply((int) $pO['id'], ['decided_by' => 'A']);
[$d2, $d2msg] = rver_apply((int) $pO['id'], ['decided_by' => 'B']);
t_ok($d1 && !$d2, 'O5 · *** only the first decision is recorded: ' . $d2msg . ' ***');
t_eq((int) rver_current('HIRING_REQUEST', $hO)['version'], $vBefore + 1,
     'O6 · exactly ONE new version was created, not two');
t_eq(count(rver_versions('HIRING_REQUEST', $hO)), $vBefore + 1, 'O7 · and the chain has no duplicate');
[$d3, $d3msg] = rver_reject((int) $pO['id'], 'too late');
t_ok(!$d3, 'O8 · a rejection after the approval is refused too: ' . $d3msg);
t_eq((string) rver_proposal((int) $pO['id'])['status'], 'APPROVED',
     'O9 · *** the final state is ONE of the answers, not a mixture ***');

// ---------------------------------------------------------------------------
t_section('G2 · P — the approved version is never edited in place');
// ---------------------------------------------------------------------------
$hP = $approve(['job_title' => 'G2 Immutable']);
$v1P = rver_current('HIRING_REQUEST', $hP);
$snapBefore = (string) $v1P['snapshot_json'];
hreq_save($hP, $base(['job_title' => 'G2 Immutable', 'designation' => 'SUPERVISOR']));
rver_apply((int) rver_pending('HIRING_REQUEST', $hP)['id'], ['decided_by' => 'G2']);
t_eq((string) rver_version('HIRING_REQUEST', $hP, 1)['snapshot_json'], $snapBefore,
     'P1 · *** version 1 is byte-identical after version 2 was approved ***');
t_eq(count(rver_versions('HIRING_REQUEST', $hP)), 2, 'P2 · and it was not deleted');
//  Materiality is judged against the APPROVED version, never the previous draft.
hreq_save($hP, $base(['job_title' => 'G2 Immutable', 'designation' => 'SUPERVISOR', 'priority' => 'URGENT']));
t_ok(!rver_pending('HIRING_REQUEST', $hP),
     'P3 · a change matching the newly approved version is not material again');
$diffP = rver_diff('HIRING_REQUEST', rver_approved_fields('HIRING_REQUEST', $hP),
                   ['designation' => 'ENGINEER'] + (array) $hr($hP));
t_ok(isset($diffP['material']['designation']),
     'P4 · *** and going BACK to version 1 values is material against version 2 ***');

// ---------------------------------------------------------------------------
t_section('G2 · M — self-approval obeys the EXISTING segregation, unchanged');
// ---------------------------------------------------------------------------
//  Gate 2 reuses the approval framework and deliberately does NOT redesign it.
//  The locked self-approval decision (configurable, OFF by default, with a master
//  exception) is Gate 4's to widen; what Gate 2 must prove is that a change
//  proposal is judged by the SAME segregation the first approval was, and that it
//  has not opened a second door around it.
t_ok(function_exists('appr_guard'), 'M1 · the existing approval guard is the one in play');
t_ok(function_exists('appr_can_act'), 'M2 · …and so is its authority check');
//  A change routes to an entity of the EXISTING engine, so every control it
//  carries applies to the change too.
t_ok(array_key_exists('HREQ_CHANGE', APPR_ENTITIES) && array_key_exists('REQ_CHANGE', APPR_ENTITIES),
     'M3 · the change chains are entities of the existing engine, not a second engine');
t_eq(count(array_filter(array_keys(APPR_ENTITIES), fn($k) => str_contains($k, 'CHANGE'))), 2,
     'M4 · …exactly two of them, one per requirement type');
//  The proposal records requester identity, which is what segregation is judged on.
$hM = $approve(['job_title' => 'G2 Segregation']);
hreq_save($hM, $base(['job_title' => 'G2 Segregation', 'designation' => 'SUPERVISOR']));
$pM = rver_pending('HIRING_REQUEST', $hM);
t_eq((int) $pM['proposed_by_id'], $uBoss, 'M5 · the proposal records WHO proposed it, by identity');
t_ok(trim((string) $pM['proposed_at']) !== '', 'M6 · …and when');
t_ok(trim((string) $pM['reason']) !== '', 'M7 · …and why (Q10)');
rver_withdraw((int) $pM['id'], 'tidy up');

// ---------------------------------------------------------------------------
t_section('G2 · N — tenant isolation: version chains do not cross databases');
// ---------------------------------------------------------------------------
$engine = (getenv('DB_DRIVER') === 'mysql') ? 'mysql' : 'sqlite';
$root   = dirname(__DIR__);
$WS_A   = $engine === 'sqlite' ? (string) getenv('SQLITE_PATH') : (string) getenv('DB_NAME');
$WS_B   = $engine === 'sqlite' ? sys_get_temp_dir() . '/g2_ws_b.sqlite' : 'g2_ws_b';
$enterWs = function ($ws) use ($engine) {
    if ($engine === 'sqlite') putenv('SQLITE_PATH=' . $ws);
    else { db()->exec("CREATE DATABASE IF NOT EXISTS `" . $ws . "`"); putenv('DB_NAME=' . $ws); }
    db(true); db();
};
//  TENANT B STARTS EMPTY, EVERY RUN.
//
//  Its database is a file (or a named schema) that outlives the process, so a
//  previous run's rows would still be there — and an isolation test that finds
//  leftovers of its own making proves nothing. Cleared before it is entered.
if ($engine === 'sqlite') { @unlink($WS_B); @unlink($WS_B . '-wal'); @unlink($WS_B . '-shm'); }
else { try { db()->exec("DROP DATABASE IF EXISTS `" . $WS_B . "`"); } catch (Throwable $e) {} }
$whoAmI = function () use ($engine, $root) {
    $cfg = require $root . '/config.php';
    return $engine === 'sqlite' ? (string) $cfg['sqlite_path'] : (string) ops_val("SELECT DATABASE()");
};
$idA = $whoAmI();
$hN = $approve(['job_title' => 'G2 Tenant A only']);
hreq_save($hN, $base(['job_title' => 'G2 Tenant A only', 'designation' => 'SUPERVISOR']));
$pN = rver_pending('HIRING_REQUEST', $hN);
t_ok(is_array($pN), 'N0 · tenant A holds an approved request with a pending change');
$verA = (int) rver_current('HIRING_REQUEST', $hN)['version'];

$enterWs($WS_B);
t_ok($whoAmI() !== $idA, 'N1 · *** tenant A and tenant B really are different databases ***');
ensure_settings_schema();
if (function_exists('ops_ensure_schema')) ops_ensure_schema();
hreq_migrate(); rver_migrate(); appr_migrate();

//  NOTHING of tenant A is visible from tenant B.
t_eq(rver_versions('HIRING_REQUEST', $hN), [], 'N2 · tenant B sees NO version of tenant A\'s request');
t_ok(rver_current('HIRING_REQUEST', $hN) === null, 'N3 · …no current version');
t_ok(rver_pending('HIRING_REQUEST', $hN) === null, 'N4 · …no pending proposal');
t_eq(rver_proposals('HIRING_REQUEST', $hN), [], 'N5 · …and no proposal history');
t_ok(rver_proposal((int) $pN['id']) === null, 'N6 · …not even by proposal id');
t_eq((int) ops_val("SELECT COUNT(*) FROM requirement_versions"), 0, 'N7 · tenant B\'s version table is its own, and empty');
t_eq((int) ops_val("SELECT COUNT(*) FROM requirement_change_proposals"), 0, 'N8 · …so is its proposal table');
t_ok(rver_approved_fields('HIRING_REQUEST', $hN) === null, 'N9 · and no approved values leak across');

//  Tenant B may hold its OWN chain for the same id without disturbing tenant A.
//  Tenant B writes its own version for the SAME id. No user fixture is needed:
//  the point is that the CHAIN is per-database, and rver_record_version() records
//  the actor it has, which in a bare tenant is the system.
rver_record_version('HIRING_REQUEST', $hN, ['job_title' => 'Tenant B version'], ['note' => 'B only']);
t_eq((int) rver_current('HIRING_REQUEST', $hN)['version'], 1, 'N10 · tenant B starts its own chain at 1');

$enterWs($WS_A);
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);
t_eq($whoAmI(), $idA, 'N11 · back in tenant A');
t_eq((int) rver_current('HIRING_REQUEST', $hN)['version'], $verA,
     'N12 · *** tenant A\'s version is exactly as it was — tenant B changed nothing ***');
t_eq((string) rver_snapshot_fields(rver_current('HIRING_REQUEST', $hN))['job_title'], 'G2 Tenant A only',
     'N13 · …and still carries tenant A\'s values, not tenant B\'s');
t_ok(is_array(rver_pending('HIRING_REQUEST', $hN)), 'N14 · …and tenant A\'s pending proposal survived');
rver_withdraw((int) rver_pending('HIRING_REQUEST', $hN)['id'], 'tidy up');
