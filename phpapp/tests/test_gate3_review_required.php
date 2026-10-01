<?php
// ============================================================================
//  GATE 3 — REVIEW REQUIRED & REQUIREMENT-CHANGE IMPACT
//
//  The claims asserted below, in the order §31 asks for them:
//
//    A  the TRIGGER — stricter raises reviews, relaxed does not, budget does not
//    B  A1 — EVERY active candidate, whatever their suitability
//    C  A2 — the issued-offer boundary, and the draft-offer inclusion
//    D  relationship isolation — R1 never reaches R2
//    E  permissions — seeing is not deciding
//    F  reasons — mandatory, and a space is not a reason
//    G  outcomes — continue, reject, the closed stage, no second approval
//    H  reconsideration — never automatic, and back to the RIGHT stage
//    I  audit — append-only, with actor, time, reason and versions
//    J  concurrency — one resolution wins, the other is told
//    K  security — the rule is below the UI, on every path
//    L  tenant isolation
//    M  Gate 2 protection — nothing in Gate 2 moved
//
//  THE LOAD-BEARING ASSERTIONS are marked ***. If one of those passes while the
//  implementation is wrong, this file is not doing its job.
// ============================================================================

$s = 'G3-' . random_int(100000, 999999);
rver_migrate(); hreq_migrate(); appr_migrate(); act_migrate(); reqf_migrate();
recruitpipe_migrate(); rkpi_migrate(); crev_migrate(); person_migrate();
$pdo = db();

$off = (int) ops_val("SELECT COALESCE(MAX(id),0)+1 FROM offices");
$pdo->prepare("INSERT INTO offices (id,name,city) VALUES (?,?,?)")->execute([$off, $s . ' Office', 'Kolkata']);
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES (?,?, 'MANAGER',1,1,?,'')")->execute([$s . '_boss', 'G3', $off]);
$uBoss = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');

$base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uBoss, 'requested_by_name' => 'G3 Boss',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'G3 Engineer', 'designation' => 'ENGINEER', 'job_description' => 'g3',
    'quantity' => 10, 'office_id' => $off, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL',
    'reason' => 'Contract awarded.', 'change_reason' => 'G3 test change',
    //  A LOW floor on the request, so a requisition may be relaxed in the tests
    //  below without breaching A8 — which is a different rule, already proved in
    //  Gate 2, and not what this battery is measuring.
    'min_experience_years' => 2,
], $x);
$approve = function (array $x = []) use ($base) {
    [$ok,, $id] = hreq_save(0, $base($x)); if (!$ok) return 0;
    hreq_submit($id); hreq_apply_decision($id, 'APPROVED', 'G3 Approver', 'ok'); return (int) $id;
};

//  WHAT THIS FILE CREATED, so it can put it all back (see the end of the file).
$madeReqs = [];
//  AND THE ROLE CONFIGURATION AS IT WAS. Section E grants a permission to a ROLE,
//  which is a workspace-wide setting, not a fixture — leaving it changed would hand
//  every later test a different permission model.
$roleAccessBefore = (string) setting_get('role_access', '');

//  A REQUISITION ON VERSION 1, with a stated experience floor.
$mkReq = function ($hid, $qty = 5, $exp = 5) use (&$madeReqs) {
    [$ok,, $rq] = hreq_to_requisition($hid, $qty);
    if (!$ok || !$rq) return 0;
    db()->prepare("UPDATE requisitions SET min_experience_years=? WHERE id=?")->execute([$exp, (int) $rq]);
    rver_ensure_initial('REQUISITION', (int) $rq, null, ['note' => 'G3 baseline']);
    $madeReqs[] = (int) $rq;
    return (int) $rq;
};
//  A NEW APPROVED VERSION of a requisition, through Gate 2 and only through it.
$bump = function ($rq, array $fields) {
    rver_gate_requisition_edit((int) $rq, $fields, ['change_reason' => 'G3 ' . implode(',', array_keys($fields))]);
    $p = rver_pending('REQUISITION', (int) $rq);
    if (!$p) return [0, 'no proposal was created'];
    [$ok, $msg] = rver_apply((int) $p['id'], ['decided_by' => 'G3 Approver']);
    $cur = rver_current('REQUISITION', (int) $rq);
    return [$ok ? (int) $cur['version'] : 0, (string) $msg];
};
$mkCand = function ($rq, $exp = 10, $name = 'Cand') use ($pdo, $s) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,
                                           experience_years,created_at)
                   VALUES (?,?,'G3','RECEIVED',?,?,?)")
        ->execute([$s . '-C' . random_int(1000, 9999), $name, (int) $rq, $exp, date('c')]);
    return (int) $pdo->lastInsertId();
};
//  MOVE A CANDIDATE ON THE PIPELINE, by the stage's own stable key, with a proper
//  ledger entry on the PIPELINE track.
//
//  t_move_stage() speaks the LEGACY vocabulary, and for an early step that names no
//  single configured stage it deliberately writes the legacy column instead — which
//  is right for what it is for, and wrong here: these tests are about candidates
//  who are genuinely on the configured pipeline, and §19 needs the ledger to record
//  where they came from in that vocabulary.
$goto = function ($cid, $stageKey) {
    $c = ops_one("SELECT * FROM candidates WHERE id=?", [(int) $cid]);
    [$pipe, , ] = recruitpipe_cand_state($c);
    if (!$pipe) return 0;
    $req = ops_one("SELECT * FROM requisitions WHERE id=?", [(int) $c['requisition_id']]) ?: [];
    $st = rpipe_current_state($c);
    $from = $st ? (string) ($st['stage_name'] ?: $st['legacy_stage']) : '';
    $fromCode = $st ? (string) ($st['stage_key'] ?: $st['legacy_stage']) : '';
    foreach (recruitpipe_resolvable_stages((int) $pipe['id'], $req) as $sg) {
        if (strcasecmp((string) $sg['stage_key'], (string) $stageKey) !== 0) continue;
        db()->prepare("UPDATE candidates SET pipeline_id=?, pipeline_stage_id=? WHERE id=?")
            ->execute([(int) $sg['pipeline_id'], (int) $sg['id'], (int) $cid]);
        rkpi_stage_log((int) $cid, $from, (string) $sg['name'], [
            'from_code' => $fromCode, 'to_code' => (string) $sg['stage_key'],
            'track' => 'PIPELINE', 'kind' => 'MOVE', 'actor' => 'G3']);
        return (int) $sg['id'];
    }
    return 0;
};

//  Close a candidate the way the product closes one: position AND ledger, so the
//  previous stage is recoverable. A fixture that moved the position without the
//  ledger would make §19 untestable and would not resemble the product.
$close = function ($cid, $toLegacy = 'REJECTED') {
    $c = ops_one("SELECT * FROM candidates WHERE id=?", [(int) $cid]);
    $st = rpipe_current_state($c);
    $from = $st ? (string) ($st['stage_name'] ?: $st['legacy_stage']) : '';
    $fromCode = $st ? (string) ($st['stage_key'] ?: $st['legacy_stage']) : '';
    $tgt = rpipe_stage_for_legacy_target($c, $toLegacy);
    if ($tgt) {
        db()->prepare("UPDATE candidates SET pipeline_id=?, pipeline_stage_id=?, decided_at=? WHERE id=?")
            ->execute([(int) $tgt['pipeline_id'], (int) $tgt['id'], date('c'), (int) $cid]);
        rkpi_stage_log((int) $cid, $from, (string) $tgt['name'], [
            'from_code' => $fromCode, 'to_code' => (string) ($tgt['stage_key'] ?? ''),
            'track' => 'PIPELINE', 'kind' => 'MOVE', 'actor' => 'G3']);
    }
    return $tgt ? (int) $tgt['id'] : 0;
};
$openCount = fn($cid) => (int) ops_val("SELECT COUNT(*) FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [(int) $cid]);

// ---------------------------------------------------------------------------
t_section('G3 · A — the trigger: direction decides, not materiality');
// ---------------------------------------------------------------------------
$hA = $approve(['job_title' => 'G3 Trigger']);
$rqA = $mkReq($hA, 5, 5);
t_ok($rqA > 0, 'A0 · a requisition on version 1 exists');
$cA = $mkCand($rqA, 12);

//  1 · A STRICTER REQUISITION VERSION.
[$vA] = $bump($rqA, ['min_experience_years' => 9]);
t_eq($vA, 2, 'A1 · a stricter change created version 2');
t_eq($openCount($cA), 1, 'A2 · *** a stricter requisition version raised a review ***');

//  3 · A RELAXED VERSION raises nothing (§18).
$hR = $approve(['job_title' => 'G3 Relax']);
$rqR = $mkReq($hR, 5, 8);
$cR = $mkCand($rqR, 12);
[$vR] = $bump($rqR, ['min_experience_years' => 4]);
t_eq($vR, 2, 'A3 · a relaxed change still creates a version (it is still a change)');
t_eq($openCount($cR), 0, 'A4 · *** …but raises NO review: lowering the bar reviews nobody ***');

//  4 · A MATERIAL BUT NON-STRICTER VERSION raises nothing. A budget rise stops
//  execution while it is pending (Gate 2) but does not change what a candidate
//  must BE, so forcing a review would teach people to click through reviews.
$hM = $approve(['job_title' => 'G3 Budget']);
$rqM = $mkReq($hM, 5, 5);
$cM = $mkCand($rqM, 12);
[$vM] = $bump($rqM, ['quantity' => 9]);
t_eq($vM, 2, 'A5 · more headcount is material, so it creates a version');
t_eq($openCount($cM), 0, 'A6 · *** …and raises NO review: headcount is not a bar a person clears ***');

//  2 · A STRICTER HIRING REQUEST version reaches candidates through its requisitions.
$hH = $approve(['job_title' => 'G3 HR Strict', 'min_experience_years' => 2]);
$rqH = $mkReq($hH, 4, 5);
$cH = $mkCand($rqH, 12);
hreq_save($hH, $base(['job_title' => 'G3 HR Strict', 'min_experience_years' => 7]));
$pH = rver_pending('HIRING_REQUEST', $hH);
t_ok(is_array($pH), 'A7 · a stricter hiring request change is a proposal');
rver_apply((int) $pH['id'], ['decided_by' => 'G3 Approver']);
t_eq((int) rver_current('HIRING_REQUEST', $hH)['version'], 2, 'A8 · the request is on version 2');
t_eq($openCount($cH), 1, 'A9 · *** a hiring request version reached a candidate on its requisition ***');
$rvH = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cH]);
t_eq((string) $rvH['entity'], 'HIRING_REQUEST', 'A10 · …and the review names the hiring request as what changed');
t_eq((int) $rvH['requisition_id'], $rqH, 'A11 · …while recording the process it is about');

//  5 · SUCCESSIVE VERSIONS — one question, kept current.
[$vA2] = $bump($rqA, ['min_experience_years' => 14]);
t_eq($vA2, 3, 'A12 · a second stricter change created version 3');
t_eq($openCount($cA), 1, 'A13 · *** still exactly ONE open review, not two ***');
t_eq((int) ops_val("SELECT to_version FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cA]), 3,
     'A14 · …moved onto the newest version, so the reviewer judges the current bar');

//  THE PAIR OF VERSIONS THAT IS COMPARED IS THE IMMEDIATELY PRECEDING ONE.
//
//  Measured on a NON-MONOTONIC sequence, because a rising one cannot tell the
//  difference: if every version is stricter than the last, then comparing against
//  version 1 gives the same answer as comparing against the previous version, and
//  a bug that always compared against version 1 would be invisible.
//
//  Here the bar goes 10 → 4 (relaxed) → 6. Against the PREVIOUS version that is a
//  rise and every active candidate must be reviewed. Against version 1 it is still
//  a fall, and nobody would be. The second reading is wrong, and this is the only
//  shape of history that says so.
$hV = $approve(['job_title' => 'G3 Zigzag']);
$rqV = $mkReq($hV, 3, 10);
[$vV2] = $bump($rqV, ['min_experience_years' => 4]);
t_eq($vV2, 2, 'A24 · version 2 LOWERED the bar from 10 years to 4');
$cV = $mkCand($rqV, 5);
t_eq($openCount($cV), 0, 'A25 · a candidate added against version 2 has nothing to review');
[$vV3] = $bump($rqV, ['min_experience_years' => 6]);
t_eq($vV3, 3, 'A26 · version 3 raised it from 4 to 6 — still below the original 10');
t_eq($openCount($cV), 1,
     'A27 · *** reviewed: the comparison is against version 2, not against version 1 ***');
t_eq((int) ops_val("SELECT from_version FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cV]), 2,
     'A28 · *** …and the review records version 2 as what it came from ***');

//  THE DIRECTION CLASSIFIER itself, on each shape of change.
t_eq(rver_field_direction('min_experience_years', 3, 8), 'stricter', 'A15 · more experience = stricter');
t_eq(rver_field_direction('min_experience_years', 8, 3), 'relaxed',  'A16 · less experience = relaxed');
t_eq(rver_field_direction('min_experience_years', 5, 5), 'neither',  'A17 · the same = neither');
t_eq(rver_field_direction('essential_skills', 'welding', 'welding, rigging'), 'stricter', 'A18 · a skill added = stricter');
t_eq(rver_field_direction('essential_skills', 'welding, rigging', 'welding'), 'relaxed',  'A19 · a skill removed = relaxed');
t_eq(rver_field_direction('essential_skills', 'welding', 'rigging'), 'redefined', 'A20 · one swapped for another = redefined');
t_eq(rver_field_direction('designation', 'WELDER', 'ENGINEER'), 'redefined', 'A21 · a different role = redefined');
t_eq(rver_field_direction('quantity', 2, 9), 'neither', 'A22 · headcount has no direction a candidate clears');
t_eq(rver_field_direction('budgeted_cost', 1, 999999), 'neither', 'A23 · nor has cost');

// ---------------------------------------------------------------------------
t_section('G3 · B — A1: EVERY active candidate, whatever their suitability');
// ---------------------------------------------------------------------------
$hB = $approve(['job_title' => 'G3 All']);
$rqB = $mkReq($hB, 9, 4);
//  Five candidates spanning the whole spectrum against the NEW bar of 10 years:
//  one far above it, one just above, one just below, one far below, one with
//  nothing recorded at all. In a product with no candidate scoring engine, this
//  IS the suitability spectrum — and the point is that it changes nothing.
$cB = [
    'far above'   => $mkCand($rqB, 25, 'Strong'),
    'just above'  => $mkCand($rqB, 11, 'Above'),
    'just below'  => $mkCand($rqB, 9,  'Below'),
    'far below'   => $mkCand($rqB, 1,  'Weak'),
    'no evidence' => $mkCand($rqB, 0,  'Unknown'),
];
//  Spread them across the pipeline too: the rule is about being active, not about
//  being early.
$goto($cB['just above'], 'HOD_SHORTLIST');
$goto($cB['just below'], 'L1');
[$vB] = $bump($rqB, ['min_experience_years' => 10]);
t_eq($vB, 2, 'B0 · the bar rose to 10 years');
foreach ($cB as $what => $cid)
    t_eq($openCount($cid), 1, 'B · *** the candidate ' . $what . ' the new bar is in review ***');
t_eq(count(crev_open_on_process($rqB)), 5, 'B6 · *** all five, counted on the requirement ***');

//  Shortlisted and interviewed candidates are in review, which the two moves above
//  put beyond "everybody happened to be at the first stage".
t_eq($openCount($cB['just above']), 1, 'B7 · a shortlisted candidate is in review');
t_eq($openCount($cB['just below']), 1, 'B8 · an interviewed candidate is in review');

//  AND THE ONES IT MUST NOT REACH: already closed, one way or another.
$hB2 = $approve(['job_title' => 'G3 Closed']);
$rqB2 = $mkReq($hB2, 9, 4);
$cRej = $mkCand($rqB2, 20); $cWdr = $mkCand($rqB2, 20); $cDec = $mkCand($rqB2, 20);
$close($cRej, 'REJECTED'); $close($cWdr, 'WITHDRAWN'); $close($cDec, 'OFFER_DECLINED');
$cLive = $mkCand($rqB2, 20);
[$vB2] = $bump($rqB2, ['min_experience_years' => 10]);
t_eq($vB2, 2, 'B9 · the bar rose on the second requirement');
t_eq($openCount($cRej), 0, 'B10 · a rejected candidate is not reviewed — they already left');
t_eq($openCount($cWdr), 0, 'B11 · nor a withdrawn one');
t_eq($openCount($cDec), 0, 'B12 · nor one who declined an offer');
t_eq($openCount($cLive), 1, 'B13 · …while the live candidate beside them is reviewed');
t_eq(t_class($cRej), 'LOST', 'B14 · (and the closed ones really are classified LOST by Gate 1B)');

// ---------------------------------------------------------------------------
t_section('G3 · C — A2: the issued-offer boundary, and the draft-offer inclusion');
// ---------------------------------------------------------------------------
$hC = $approve(['job_title' => 'G3 Offer']);
$rqC = $mkReq($hC, 9, 4);
$cDraft = $mkCand($rqC, 20, 'Draft');
$cIssued = $mkCand($rqC, 20, 'Issued');
t_move_stage($cDraft, 'OFFERED'); t_move_stage($cIssued, 'OFFERED');

//  A DRAFT offer is a document, not a promise: still in scope (§8).
$oDraft = (int) offer_create($cDraft, ['ctc' => 400000, 'joining_date' => date('Y-m-d', strtotime('+30 days'))]);
t_ok($oDraft > 0, 'C0 · a draft offer exists');
t_eq(strtoupper((string) ops_val("SELECT status FROM job_offers WHERE id=?", [$oDraft])), 'DRAFT', 'C1 · …and it is DRAFT');

//  AN ISSUED offer is a promise to a human being: out of scope for later versions.
$oIss = (int) offer_create($cIssued, ['ctc' => 400000, 'joining_date' => date('Y-m-d', strtotime('+30 days'))]);
offer_submit($oIss); offer_approve($oIss, 'G3 Approver', 'ok');
[$isOk] = offer_issue($oIss);
t_ok($isOk, 'C2 · the second offer was issued');
$stamp = (int) ops_val("SELECT COALESCE(req_version_at_issue,0) FROM job_offers WHERE id=?", [$oIss]);
t_eq($stamp, 1, 'C3 · …stamped with the version in force at issue');

[$vC] = $bump($rqC, ['min_experience_years' => 10]);
t_eq($vC, 2, 'C4 · the requirement became stricter after the offer went out');
t_eq($openCount($cDraft), 1, 'C5 · *** a DRAFT offer does not protect anybody: still reviewed ***');
t_eq($openCount($cIssued), 0, 'C6 · *** an ISSUED offer does: no review from a later version ***');
t_eq((int) rver_applicable_version('REQUISITION', $rqC, $cIssued), 1,
     'C7 · …and they stay on the version in force when the offer was issued');
t_eq((int) rver_applicable_version('REQUISITION', $rqC, $cDraft), 2,
     'C8 · while the draft-offer candidate is on the new one');

//  ACCEPTED and JOINED are both beyond the boundary, and for the same reason:
//  the promise was already made. The boundary is Offer Issued, not Hired.
$pdo->prepare("UPDATE job_offers SET status='ACCEPTED' WHERE id=?")->execute([$oIss]);
[$vC2] = $bump($rqC, ['min_experience_years' => 16]);
t_eq($vC2, 3, 'C9 · a third version');
t_eq($openCount($cIssued), 0, 'C10 · *** an accepted offer is still beyond the boundary ***');

$cJoin = $mkCand($rqC, 20, 'Joined');
t_move_stage($cJoin, 'ACCEPTED');
$pdo->prepare("UPDATE candidates SET joined_at=? WHERE id=?")->execute([date('c'), $cJoin]);
[$vC3] = $bump($rqC, ['min_experience_years' => 18]);
t_eq($vC3, 4, 'C11 · a fourth version');
t_eq($openCount($cJoin), 0, 'C12 · *** somebody already in the workforce is never reviewed ***');
t_eq(t_class($cJoin), 'FILLED', 'C13 · (they are classified FILLED, which is why)');

// ---------------------------------------------------------------------------
t_section('G3 · D — relationship isolation: R1 never reaches R2');
// ---------------------------------------------------------------------------
//  ONE PERSON, THREE APPLICATIONS. In this product an application is a candidate
//  ROW, threaded to the others by person_ref — so this is what "the same candidate
//  on three requirements" actually is, and the rows must not speak for each other.
$hD = $approve(['job_title' => 'G3 Iso']);
$rqD1 = $mkReq($hD, 3, 4); $rqD2 = $mkReq($hD, 3, 4); $rqD3 = $mkReq($hD, 3, 4);
t_ok($rqD1 > 0 && $rqD2 > 0 && $rqD3 > 0, 'D-0 · three requisitions from one request exist');
$person = $s . '-PERSON';
$mkApp = function ($rq) use ($pdo, $s, $person) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,
                                           experience_years,person_ref,mobile,created_at)
                   VALUES (?,'Same','Person','RECEIVED',?,20,?,?,?)")
        ->execute([$s . '-A' . random_int(1000, 9999), (int) $rq, $person, '9000000001', date('c')]);
    return (int) $pdo->lastInsertId();
};
$a1 = $mkApp($rqD1); $a2 = $mkApp($rqD2); $a3 = $mkApp($rqD3);
$close($a3, 'REJECTED');
t_eq(count(person_applications(ops_one("SELECT * FROM candidates WHERE id=?", [$a1]))) >= 2, true,
     'D0 · the three rows are recognised as one person');

[$vD1] = $bump($rqD1, ['min_experience_years' => 10]);
t_eq($vD1, 2, 'D1 · only the FIRST requirement became stricter');
t_eq($openCount($a1), 1, 'D2 · *** the application on R1 is in review ***');
t_eq($openCount($a2), 0, 'D3 · *** the same person on R2 is NOT — a review is not a property of a person ***');
t_eq($openCount($a3), 0, 'D4 · and the closed application on R3 is untouched');
t_eq((int) rver_current('REQUISITION', $rqD2)['version'], 1, 'D5 · R2 is still on version 1');

//  THERE IS NO GLOBAL FLAG, and this is asserted against the schema rather than
//  taken on trust: a candidates.review_required column is exactly the thing §2
//  forbids, and it would be invisible in behaviour until two applications of one
//  person disagreed.
$candCols = array_map('strtolower', rver_table_fields('candidates'));
t_ok(!in_array('review_required', $candCols, true),
     'D6 · *** there is no candidates.review_required column — no global candidate state ***');

//  CLEARING ONE DOES NOT CLEAR THE OTHER.
[$vD2] = $bump($rqD2, ['min_experience_years' => 10]);
t_eq($openCount($a1) + $openCount($a2), 2, 'D7 · now both live applications are in review');
$rv1 = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$a1]);
[$dOk, $dMsg] = crev_continue((int) $rv1['id'], 'Checked against R1 — 20 years, well above 10.');
t_ok($dOk, 'D8 · the R1 review is cleared: ' . $dMsg);
t_eq($openCount($a1), 0, 'D9 · R1 is cleared…');
t_eq($openCount($a2), 1, 'D10 · *** …and R2 is still in review ***');

//  REJECTING ONE DOES NOT REJECT THE OTHER.
$rv2 = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$a2]);
[$dRej] = crev_reject((int) $rv2['id'], 'Not right for the R2 scope after the change.');
t_ok($dRej, 'D11 · the R2 review rejects that application');
t_eq(t_class($a2), 'LOST', 'D12 · …so R2 is closed');
t_ok(t_class($a1) !== 'LOST', 'D13 · *** …while the same person on R1 is still live ***');

// ---------------------------------------------------------------------------
t_section('G3 · E — permissions: seeing is not deciding');
// ---------------------------------------------------------------------------
t_ok(array_key_exists('hiring.review.clear', PERMISSIONS), 'E1 · the permission exists in the catalogue');
t_ok(in_array('hiring.review.clear', permission_groups()['Recruitment'] ?? [], true),
     'E2 · …and is configurable in the Recruitment group');
t_eq(CREV_PERM_CLEAR, 'hiring.review.clear', 'E3 · the code and the constant agree');

$hE = $approve(['job_title' => 'G3 Perm']);
$rqE = $mkReq($hE, 9, 4);
$cE = $mkCand($rqE, 20);
$bump($rqE, ['min_experience_years' => 10]);
$rvE = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cE]);
t_ok(is_array($rvE), 'E4 · a review is open to decide');

//  A VIEWER WITHOUT THE PERMISSION — can see, cannot decide. This is the whole of
//  §13 in two assertions.
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES (?,?, 'COORDINATOR',1,0,?,?)")->execute([$s . '_view', 'G3View', $off, (string) $off]);
$uView = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uView; current_user(true); ua(true);
$candE = ops_one("SELECT * FROM candidates WHERE id=?", [$cE]);
t_ok(crev_can_see($candE), 'E5 · a coordinator can SEE the review');
t_ok(!can('hiring.review.clear'), 'E6 · …and does not hold the permission');
t_ok(!crev_can_clear($candE), 'E7 · *** …so cannot decide it ***');
[$eOk, $eMsg] = crev_continue((int) $rvE['id'], 'I will just clear this myself.');
t_ok(!$eOk, 'E8 · *** an attempt to continue is REFUSED: ' . $eMsg . ' ***');
[$eOk2] = crev_reject((int) $rvE['id'], 'Or reject it.');
t_ok(!$eOk2, 'E9 · *** …and so is an attempt to reject ***');
t_eq($openCount($cE), 1, 'E10 · …with the review still open and nothing changed');
t_ok(t_class($cE) !== 'LOST', 'E11 · …and the candidate not closed');

//  GRANT THE PERMISSION TO THE ROLE — the organisation decides, per role.
role_grant_perm('COORDINATOR', 'hiring.review.clear');
current_user(true); ua(true);
t_ok(can('hiring.review.clear'), 'E12 · the role now holds the permission');
t_ok(crev_can_clear($candE), 'E13 · …so this coordinator may now decide');

//  SCOPE — a permission is not a licence to reach another branch's work.
$off2 = (int) ops_val("SELECT COALESCE(MAX(id),0)+1 FROM offices");
$pdo->prepare("INSERT INTO offices (id,name,city) VALUES (?,?,?)")->execute([$off2, $s . ' Far', 'Pune']);
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES (?,?, 'COORDINATOR',1,0,?,?)")->execute([$s . '_far', 'G3Far', $off2, (string) $off2]);
$uFar = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uFar; current_user(true); ua(true);
t_ok(can('hiring.review.clear'), 'E14 · the out-of-scope user holds the same permission');
t_ok(!crev_can_clear($candE), 'E15 · *** …and still cannot decide another branch\'s review ***');
[$fOk, $fMsg] = crev_continue((int) $rvE['id'], 'Reaching across branches.');
t_ok(!$fOk, 'E16 · *** …the attempt is refused: ' . $fMsg . ' ***');

//  REMOVING THE PERMISSION TAKES THE AUTHORITY AWAY AGAIN.
$_SESSION['uid'] = $uView; current_user(true); ua(true);
$raw = json_decode((string) setting_get('role_access', ''), true);
$raw['COORDINATOR'] = array_values(array_diff($raw['COORDINATOR'] ?? [], ['hiring.review.clear']));
setting_set('role_access', json_encode($raw));
current_user(true); ua(true);
t_ok(!crev_can_clear($candE), 'E17 · *** removing the permission removes the authority ***');
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);

// ---------------------------------------------------------------------------
t_section('G3 · F — reasons: mandatory, and a space is not a reason');
// ---------------------------------------------------------------------------
t_eq(crev_reason_ok(''), '', 'F1 · an empty reason is not a reason');
t_eq(crev_reason_ok('   '), '', 'F2 · *** whitespace is not a reason ***');
t_eq(crev_reason_ok("\t\n "), '', 'F3 · nor a tab and a newline');
t_eq(crev_reason_ok('...'), '', 'F4 · *** nor punctuation standing in for one ***');
t_eq(crev_reason_ok('  Still  suitable  '), 'Still suitable', 'F5 · a real reason is kept, tidied');

[$f1Ok, $f1Msg] = crev_continue((int) $rvE['id'], '');
t_ok(!$f1Ok, 'F6 · *** continue with no reason is refused: ' . $f1Msg . ' ***');
[$f2Ok] = crev_continue((int) $rvE['id'], '    ');
t_ok(!$f2Ok, 'F7 · *** …and with whitespace ***');
[$f3Ok] = crev_reject((int) $rvE['id'], '');
t_ok(!$f3Ok, 'F8 · *** reject with no reason is refused ***');
[$f4Ok] = crev_reject((int) $rvE['id'], '   ');
t_ok(!$f4Ok, 'F9 · *** …and with whitespace ***');
t_eq($openCount($cE), 1, 'F10 · after four refused attempts the review is still open');

// ---------------------------------------------------------------------------
t_section('G3 · G — outcomes: continue, reject, the closed stage, no second approval');
// ---------------------------------------------------------------------------
[$gOk, $gMsg] = crev_continue((int) $rvE['id'], '20 years against a 10-year minimum.');
t_ok($gOk, 'G1 · continue, with a reason, succeeds: ' . $gMsg);
t_eq($openCount($cE), 0, 'G2 · …and closes the review');
$rvEdone = crev_review((int) $rvE['id']);
t_eq((string) $rvEdone['status'], 'CONTINUED', 'G3 · …as CONTINUED');
t_eq((string) $rvEdone['resolve_reason'], '20 years against a 10-year minimum.', 'G4 · …keeping the reason');
t_ok(t_class($cE) !== 'LOST', 'G5 · a continued candidate is still in the process');
t_ok((string) $rvEdone['open_key'] === '' || $rvEdone['open_key'] === null,
     'G6 · …and releases the one-open-review key');

//  REJECT lands on the organisation's own configured closed off-ramp.
$hG = $approve(['job_title' => 'G3 Reject']);
$rqG = $mkReq($hG, 9, 4);
$cG = $mkCand($rqG, 20);
$goto($cG, 'L1');
$bump($rqG, ['min_experience_years' => 10]);
$rvG = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cG]);
$apprBefore = (int) ops_val("SELECT COUNT(*) FROM recruit_approval_requests");
[$gRej, $gRejMsg] = crev_reject((int) $rvG['id'], 'Eight years short of the new minimum.');
t_ok($gRej, 'G7 · reject, with a reason, succeeds: ' . $gRejMsg);
t_eq(t_class($cG), 'LOST', 'G8 · *** the candidate is closed immediately ***');
$stG = rpipe_current_state(ops_one("SELECT * FROM candidates WHERE id=?", [$cG]));
t_ok(rpipe_kind_is_closed((string) $stG['kind']),
     'G9 · *** …on a stage of the CONFIGURED closed kind, not a new state ***');
t_ok((string) $stG['closed_outcome'] !== '', 'G10 · …with a configured closed outcome: ' . $stG['closed_outcome']);
t_eq((int) ops_val("SELECT COUNT(*) FROM recruit_approval_requests"), $apprBefore,
     'G11 · *** no approval was raised: a review rejection needs none (§11) ***');
t_ok(trim((string) ops_val("SELECT drop_reason FROM candidates WHERE id=?", [$cG])) !== '',
     'G12 · …and the existing drop-reason reporting was fed');

//  A DECIDED REVIEW CANNOT BE DECIDED AGAIN.
[$gAgain, $gAgainMsg] = crev_continue((int) $rvG['id'], 'Changed my mind.');
t_ok(!$gAgain, 'G13 · *** a decided review cannot be re-decided: ' . $gAgainMsg . ' ***');

// ---------------------------------------------------------------------------
t_section('G3 · H — reconsideration: never automatic, and back to the RIGHT stage');
// ---------------------------------------------------------------------------
//  45 · A RELAXED REQUIREMENT REOPENS NOBODY.
$hH2 = $approve(['job_title' => 'G3 Reopen']);
$rqH2 = $mkReq($hH2, 9, 10);
$cH2 = $mkCand($rqH2, 3);
$goto($cH2, 'L1');
$close($cH2, 'REJECTED');
t_eq(t_class($cH2), 'LOST', 'H1 · a candidate was rejected at interview');
$stageBefore = (int) ops_val("SELECT pipeline_stage_id FROM candidates WHERE id=?", [$cH2]);
[$vH2] = $bump($rqH2, ['min_experience_years' => 2]);
t_eq($vH2, 2, 'H2 · the requirement was then LOWERED to 2 years, which they meet');
t_eq($openCount($cH2), 0, 'H3 · *** they are not put into review ***');
t_eq((int) ops_val("SELECT pipeline_stage_id FROM candidates WHERE id=?", [$cH2]), $stageBefore,
     'H4 · *** …not moved ***');
t_eq(t_class($cH2), 'LOST', 'H5 · *** …and still rejected. A lowered bar reopens nobody ***');

//  46-48 · DELIBERATE RECONSIDERATION, back to the stage before the rejection.
[$hRec, $hRecMsg] = crev_reconsider($cH2, 'Requirement lowered and no other candidate is available.');
t_ok($hRec, 'H6 · an authorised user can deliberately reconsider them: ' . $hRecMsg);
$stH = rpipe_current_state(ops_one("SELECT * FROM candidates WHERE id=?", [$cH2]));
t_eq((string) $stH['kind'], 'interview',
     'H7 · *** they return to the INTERVIEW stage they were rejected from, not to the start ***');
t_eq(t_class($cH2), 'ACTIVE', 'H8 · …and are live again');
t_ok(strpos(strtolower((string) ops_val(
        "SELECT remark FROM candidate_events WHERE candidate_id=? ORDER BY id DESC LIMIT 1", [$cH2])),
     'no other candidate is available') !== false,
     'H9 · …with the reconsideration reason in the ledger');

//  49 · THE ORIGINAL REJECTION IS STILL THERE. Reconsidering appends; it does not
//  rewrite what happened.
$rejEvents = (int) ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=? AND to_code<>'' AND id < (
                            SELECT MAX(id) FROM candidate_events WHERE candidate_id=?)", [$cH2, $cH2]);
t_ok($rejEvents >= 1, 'H10 · *** the rejection remains in the history ***');

//  40 · RECONSIDERATION WITHOUT A REASON IS REFUSED.
$cH3 = $mkCand($rqH2, 3); $goto($cH3, 'CV_SCREEN'); $close($cH3, 'REJECTED');
[$hNo, $hNoMsg] = crev_reconsider($cH3, '   ');
t_ok(!$hNo, 'H11 · *** reconsideration with no reason is refused: ' . $hNoMsg . ' ***');
t_eq(t_class($cH3), 'LOST', 'H12 · …and they stay closed');
[$hYes] = crev_reconsider($cH3, 'Deliberately brought back for a second look.');
t_ok($hYes, 'H13 · with a reason it succeeds');
t_eq((string) rpipe_current_state(ops_one("SELECT * FROM candidates WHERE id=?", [$cH3]))['stage_key'], 'CV_SCREEN',
     'H14 · …returning to the exact screening stage they were closed from');

//  A LIVE CANDIDATE CANNOT BE "RECONSIDERED" — there is nothing to reconsider.
[$hLive, $hLiveMsg] = crev_reconsider($cH3, 'Again?');
t_ok(!$hLive, 'H15 · a live candidate cannot be reconsidered: ' . $hLiveMsg);

// ---------------------------------------------------------------------------
t_section('G3 · I — audit: append-only, with actor, time, reason and versions');
// ---------------------------------------------------------------------------
$hI = $approve(['job_title' => 'G3 Audit']);
$rqI = $mkReq($hI, 9, 4);
$cI = $mkCand($rqI, 20);
$evBefore = (int) ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=?", [$cI]);
$bump($rqI, ['min_experience_years' => 10]);
$rvI = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cI]);
t_ok((int) ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=?", [$cI]) > $evBefore,
     'I1 · raising a review wrote to the EXISTING candidate ledger');
t_eq((int) $rvI['from_version'], 1, 'I2 · the review records the version it came from');
t_eq((int) $rvI['to_version'], 2, 'I3 · …and the one now in force');
t_ok(trim((string) $rvI['raised_at']) !== '', 'I4 · …when it was raised');
t_ok(trim((string) $rvI['raised_by']) !== '', 'I5 · …and by whom');
$tj = json_decode((string) $rvI['trigger_json'], true);
t_ok(in_array('min_experience_years', $tj['stricter'] ?? [], true),
     'I6 · …and exactly WHICH field got stricter');

$evMid = (int) ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=?", [$cI]);
crev_continue((int) $rvI['id'], 'Comfortably clears the new minimum.');
$rvId = crev_review((int) $rvI['id']);
t_ok((int) ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=?", [$cI]) > $evMid,
     'I7 · the decision wrote its own ledger entry');
t_ok(trim((string) $rvId['resolved_by']) !== '', 'I8 · the decision records who made it');
t_ok(trim((string) $rvId['resolved_at']) !== '', 'I9 · …when');
t_eq((string) $rvId['resolve_reason'], 'Comfortably clears the new minimum.', 'I10 · …and why');
//  APPEND-ONLY: the raise record is not overwritten by the decision.
t_eq((int) $rvId['from_version'], 1, 'I11 · *** the trigger record survives the decision ***');
t_eq((int) $rvId['to_version'], 2, 'I12 · …intact');
t_eq(count(crev_history($cI)), 1, 'I13 · and the history keeps the decided review');

// ---------------------------------------------------------------------------
t_section('G3 · J — concurrency: one resolution wins, the other is told');
// ---------------------------------------------------------------------------
$hJ = $approve(['job_title' => 'G3 Race']);
$rqJ = $mkReq($hJ, 9, 4);

//  55 · CONTINUE versus REJECT on the same review.
$cJ = $mkCand($rqJ, 20);
$bump($rqJ, ['min_experience_years' => 10]);
$rvJ = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cJ]);
[$jA, $jAMsg] = crev_continue((int) $rvJ['id'], 'User A says continue.');
[$jB, $jBMsg] = crev_reject((int) $rvJ['id'], 'User B says reject.');
t_ok($jA && !$jB, 'J1 · *** exactly one of continue/reject succeeds ***');
t_ok(stripos($jBMsg, 'already been decided') !== false || stripos($jBMsg, 'somebody else') !== false,
     'J2 · *** …and the loser is told plainly: ' . $jBMsg . ' ***');
t_eq((string) crev_review((int) $rvJ['id'])['status'], 'CONTINUED', 'J3 · one outcome, not two');
t_ok(t_class($cJ) !== 'LOST', 'J4 · …and the losing REJECT did not close the candidate');

//  56/57 · THE SAME DECISION TWICE.
$cJ2 = $mkCand($rqJ, 20);
$bump($rqJ, ['min_experience_years' => 11]);
$rvJ2 = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cJ2]);
[$j2a] = crev_continue((int) $rvJ2['id'], 'First continue.');
[$j2b] = crev_continue((int) $rvJ2['id'], 'Second continue.');
t_ok($j2a && !$j2b, 'J5 · a duplicate CONTINUE is refused');
$cJ3 = $mkCand($rqJ, 20);
$bump($rqJ, ['min_experience_years' => 12]);
$rvJ3 = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cJ3]);
[$j3a] = crev_reject((int) $rvJ3['id'], 'First reject.');
[$j3b] = crev_reject((int) $rvJ3['id'], 'Second reject.');
t_ok($j3a && !$j3b, 'J6 · a duplicate REJECT is refused');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidate_reviews WHERE candidate_id=? AND status='REJECTED'", [$cJ3]), 1,
     'J7 · …with no duplicate history');

//  TWO REAL PROCESSES, DECIDING AT THE SAME INSTANT.
//
//  Everything above runs in one process, where the PHP status pre-check catches a
//  duplicate before the write is even attempted — so a single-process test cannot
//  tell a working guard from a missing one. These are two separate PHP processes on
//  the same database, released together: both read the review as OPEN, and only the
//  conditional UPDATE can stop them both succeeding.
$root6 = dirname(__DIR__);
$engine6 = (getenv('DB_DRIVER') === 'mysql') ? 'mysql' : 'sqlite';
$race = function (array $ops) use ($root6, $engine6, $uBoss) {
    $env = $engine6 === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
          . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
          . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $procs = [];
    foreach ($ops as [$op, $rid, $reason]) {
        $cmd = $env . ' php ' . escapeshellarg($root6 . '/tests/_g3_worker.php') . ' '
             . escapeshellarg($op) . ' ' . (int) $rid . ' ' . escapeshellarg((string) $reason)
             . ' 400 ' . (int) $uBoss . ' 2>&1';
        $pipes = []; $pr = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($pr)) $procs[] = [$pr, $pipes];
    }
    $res = [];
    foreach ($procs as [$pr, $pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($pr);
        foreach (explode("\n", trim($raw)) as $line) { $d = json_decode(trim($line), true); if (is_array($d)) $res[] = $d; }
    }
    return $res;
};

$cR1 = $mkCand($rqJ, 20);
$bump($rqJ, ['min_experience_years' => 21]);
$rvR1 = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cR1]);
$r1 = $race([['continue', (int) $rvR1['id'], 'Process A continues.'],
             ['reject',   (int) $rvR1['id'], 'Process B rejects.']]);
$wins = count(array_filter($r1, fn($x) => !empty($x['ok'])));
t_eq(count($r1), 2, 'J10 · two real processes both ran');
t_eq($wins, 1, 'J11 · *** exactly ONE of two simultaneous processes won the decision ***');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidate_reviews WHERE id=? AND status='OPEN'", [(int) $rvR1['id']]), 0,
     'J12 · …and the review is decided exactly once');
$loser = '';
foreach ($r1 as $x) if (empty($x['ok'])) $loser = (string) $x['msg'];
t_ok(stripos($loser, 'already been decided') !== false || stripos($loser, 'somebody else') !== false,
     'J13 · *** …while the loser got a deterministic, honest answer: ' . $loser . ' ***');

//  TWO SIMULTANEOUS CONTINUES — the same guard, from the other direction.
$cR2 = $mkCand($rqJ, 20);
$bump($rqJ, ['min_experience_years' => 22]);
$rvR2 = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cR2]);
$r2 = $race([['continue', (int) $rvR2['id'], 'A continues.'],
             ['continue', (int) $rvR2['id'], 'B continues.']]);
t_eq(count(array_filter($r2, fn($x) => !empty($x['ok']))), 1,
     'J14 · *** two simultaneous CONTINUES resolve the review once, not twice ***');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidate_reviews WHERE candidate_id=? AND status='CONTINUED'", [$cR2]), 1,
     'J15 · …with one outcome row, not two');

//  THE GUARD THAT MAKES THE RACE SAFE, ASSERTED DIRECTLY.
//
//  The two-process races above prove the OUTCOME is right. They cannot prove WHY,
//  and that matters here. Both resolvers check the review's status in PHP before
//  they write, and that check answers first whenever the two processes do not
//  actually overlap — which, for an operation this short, is most of the time. So a
//  behavioural test passes whether the real protection is present or not.
//
//  The real protection is that the write itself is CONDITIONAL on the review still
//  being open, and that a write which matched no row is reported as a loss. That is
//  the only thing standing between two approvers who both read OPEN in the same
//  instant and both decide. It is asserted here against the source, the same way
//  the M4 suite asserts its single-owner boundary, because no amount of timing can
//  assert it reliably from the outside.
$crevSrc = (string) @file_get_contents(dirname(__DIR__) . '/lib/candreview.php');
t_ok($crevSrc !== '', 'J16 · the engine source can be read');
//  Found by splitting on the write itself rather than by a regex, so what is
//  being asserted stays readable: each resolving UPDATE, up to the end of its
//  statement, must carry the open-status condition.
$ups = [];
foreach (["SET status='CONTINUED'", "SET status='REJECTED'"] as $marker) {
    $at = strpos($crevSrc, 'UPDATE candidate_reviews' . "\n" . '                             ' . $marker);
    if ($at === false) $at = strpos($crevSrc, $marker);
    if ($at !== false) $ups[] = substr($crevSrc, $at, 420);
}
t_eq(count($ups), 2, 'J17 · both resolvers write through an UPDATE on the review');
$allGuarded = count($ups) === 2;
foreach ($ups as $tail) if (strpos($tail, "WHERE id=? AND status='OPEN'") === false) $allGuarded = false;
t_ok($allGuarded,
     'J18 · *** every resolving write is CONDITIONAL on the review still being open ***');
t_eq(substr_count($crevSrc, "if (\$st->rowCount() < 1) return [false, 'That review was decided by somebody else a moment ago.']"), 2,
     'J19 · *** …and a write that matched nothing is reported as a loss, in both resolvers ***');

//  58 · THE DATABASE itself refuses a second OPEN review for one relationship —
//  asserted by trying the insert directly, below every line of application code.
$cJ4 = $mkCand($rqJ, 20);
//  23, because the bar on this requirement has already been raised to 22 by the
//  race above and a review is only raised by a change that goes UP.
$bump($rqJ, ['min_experience_years' => 23]);
t_eq($openCount($cJ4), 1, 'J7b · a review is open to try to duplicate');
$dupBlocked = false;
try {
    $pdo->prepare("INSERT INTO candidate_reviews (candidate_id,requisition_id,entity,entity_id,
                       from_version,to_version,status,open_key,created_at)
                   VALUES (?,?,'REQUISITION',?,1,2,'OPEN',?,?)")
        ->execute([$cJ4, $rqJ, $rqJ, crev_open_key($cJ4, 'REQUISITION', $rqJ), date('c')]);
} catch (Throwable $e) { $dupBlocked = true; }
t_ok($dupBlocked, 'J8 · *** the DATABASE refuses a second open review for one relationship ***');
t_eq($openCount($cJ4), 1, 'J9 · …so there is exactly one');

// ---------------------------------------------------------------------------
t_section('G3 · K — security: the rule is below the UI, on every path');
// ---------------------------------------------------------------------------
$hK = $approve(['job_title' => 'G3 Bypass']);
$rqK = $mkReq($hK, 3, 4);
$cK = $mkCand($rqK, 20);
$goto($cK, 'HOD_SHORTLIST');
$bump($rqK, ['min_experience_years' => 10]);
t_eq($openCount($cK), 1, 'K0 · a review is open on a shortlisted candidate');

//  EVERY ACTION the execution vocabulary knows, asked of the owning service.
foreach (['ADVANCE' => 'advancing', 'INTERVIEW' => 'interviewing',
          'OFFER' => 'making an offer', 'JOIN' => 'recording a joining'] as $act => $what)
    t_ok(rexec_cand_block_reason($cK, $act) !== '',
         'K · *** ' . $what . ' is refused while the review is open ***');

//  THE PIPELINE ROUTE — a stage move that does not go through the legacy stage
//  vocabulary at all.
$pipe = recruitpipe_for(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqK]));
$eff = recruitpipe_effective_stages((int) $pipe['id'], ops_one("SELECT * FROM requisitions WHERE id=?", [$rqK]));
$ivStage = null; foreach ($eff as $st2) if ((string) $st2['kind'] === 'interview') { $ivStage = $st2; break; }
$stageBeforeK = (int) ops_val("SELECT pipeline_stage_id FROM candidates WHERE id=?", [$cK]);
t_ok($ivStage && !recruitpipe_cand_goto(ops_one("SELECT * FROM candidates WHERE id=?", [$cK]),
     (int) $ivStage['id'], 'pushing past the review', 'G3'),
     'K5 · *** the pipeline stage route refuses the move ***');
t_eq((int) ops_val("SELECT pipeline_stage_id FROM candidates WHERE id=?", [$cK]), $stageBeforeK,
     'K6 · *** …and the candidate did not move ***');

//  THE OFFER PATH — creating an offer is its own service, with its own entry point.
$offerBefore = (int) ops_val("SELECT COUNT(*) FROM job_offers WHERE candidate_id=?", [$cK]);
$oK = offer_create($cK, ['ctc' => 400000, 'joining_date' => date('Y-m-d', strtotime('+30 days'))]);
t_eq((int) ops_val("SELECT COUNT(*) FROM job_offers WHERE candidate_id=?", [$cK]), $offerBefore,
     'K7 · *** no offer can be created while the review is open ***');

//  THE INTERVIEW PATH.
recruit_iv_migrate();
$ivBefore = (int) ops_val("SELECT COUNT(*) FROM interviews WHERE candidate_id=?", [$cK]);
iv_schedule($cK, ['round' => 'L1', 'mode' => 'In person', 'scheduled_at' => date('c')]);
t_eq((int) ops_val("SELECT COUNT(*) FROM interviews WHERE candidate_id=?", [$cK]), $ivBefore,
     'K8 · *** no interview can be scheduled while the review is open ***');

//  REALLOCATION — the one path that passes 0 as the seat-exclusion id, and so the
//  one most likely to have skipped the review question.
$rqK2 = $mkReq($hK, 3, 4);
t_ok(rexec_block_reason($rqK2, 'ADVANCE', 0, $cK) !== '',
     'K9 · *** reallocating a candidate under review is refused ***');
t_eq(rexec_block_reason($rqK2, 'ADVANCE', 0, 0), '',
     'K10 · …while the same question with no candidate named is about the requirement only');

//  AND IT ALL OPENS AGAIN once a human has actually decided.
$rvK = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cK]);
crev_continue((int) $rvK['id'], '20 years — clears the new 10-year minimum.');
t_eq(rexec_cand_block_reason($cK, 'ADVANCE'), '', 'K11 · *** once reviewed, advancing is allowed again ***');
t_eq(rexec_cand_block_reason($cK, 'OFFER'), '', 'K12 · …and so is an offer');

//  A REVIEW IS NOT A REQUIREMENT-WIDE BLOCK. One candidate under review must not
//  stop recruitment on everybody else, which would be a different bug wearing the
//  same clothes.
$cK3 = $mkCand($rqK, 20); $cK4 = $mkCand($rqK, 20);
$bump($rqK, ['min_experience_years' => 15]);
$rvK3 = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cK3]);
crev_continue((int) $rvK3['id'], 'Checked — fine.');
t_eq(rexec_cand_block_reason($cK3, 'ADVANCE'), '', 'K13 · the reviewed candidate may advance');
t_ok(rexec_cand_block_reason($cK4, 'ADVANCE') !== '', 'K14 · …while their unreviewed colleague may not');

// ---------------------------------------------------------------------------
t_section('G3 · L — tenant isolation');
// ---------------------------------------------------------------------------
//  One database per tenant, so isolation is structural. What is asserted here is
//  that Gate 3's own table and reads live inside that boundary — a review, its
//  reason and its versions must be invisible AND unresolvable from another tenant.
$hL = $approve(['job_title' => 'G3 Tenant']);
$rqL = $mkReq($hL, 3, 4);
$cL = $mkCand($rqL, 20);
$bump($rqL, ['min_experience_years' => 10]);
$rvL = ops_one("SELECT * FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cL]);
t_ok(is_array($rvL), 'L0 · tenant A has an open review');
$aCount = (int) ops_val("SELECT COUNT(*) FROM candidate_reviews");

$engine = (getenv('DB_DRIVER') === 'mysql') ? 'mysql' : 'sqlite';
$root   = dirname(__DIR__);
$WS_A   = $engine === 'sqlite' ? (string) getenv('SQLITE_PATH') : (string) getenv('DB_NAME');
$WS_B   = $engine === 'sqlite' ? sys_get_temp_dir() . '/g3_ws_b.sqlite' : 'g3_ws_b';
$enterWs = function ($ws) use ($engine) {
    if ($engine === 'sqlite') putenv('SQLITE_PATH=' . $ws);
    else { db()->exec("CREATE DATABASE IF NOT EXISTS `" . $ws . "`"); putenv('DB_NAME=' . $ws); }
    db(true); db();
};
//  TENANT B STARTS EMPTY, EVERY RUN. Its database outlives the process, so a
//  previous run's rows would still be there — and an isolation test that finds
//  leftovers of its own making proves nothing at all.
if ($engine === 'sqlite') { @unlink($WS_B); @unlink($WS_B . '-wal'); @unlink($WS_B . '-shm'); }
else { try { db()->exec("DROP DATABASE IF EXISTS `" . $WS_B . "`"); } catch (Throwable $e) {} }
$whoAmI = function () use ($engine, $root) {
    $cfg = require $root . '/config.php';
    return $engine === 'sqlite' ? (string) $cfg['sqlite_path'] : (string) ops_val("SELECT DATABASE()");
};
$idA = $whoAmI();

$enterWs($WS_B);
t_ok($whoAmI() !== $idA, 'L1 · *** tenant A and tenant B really are different databases ***');
ensure_settings_schema();
if (function_exists('ops_ensure_schema')) ops_ensure_schema();
hreq_migrate(); rver_migrate(); appr_migrate(); recruitpipe_migrate(); rkpi_migrate(); crev_migrate();

t_eq((int) ops_val("SELECT COUNT(*) FROM candidate_reviews"), 0,
     'L2 · *** tenant B sees NONE of tenant A\'s reviews ***');
t_ok(crev_review((int) $rvL['id']) === null, 'L3 · *** …and cannot read one by its id ***');
t_eq(crev_open_all($cL), [], 'L4 · …nor by the candidate it belongs to');
[$lOk, $lMsg] = crev_continue((int) $rvL['id'], 'Reaching into another tenant.');
t_ok(!$lOk, 'L5 · *** …nor resolve it: ' . $lMsg . ' ***');
[$lrOk] = crev_reject((int) $rvL['id'], 'Or reject it from here.');
t_ok(!$lrOk, 'L6 · *** …nor reject through it ***');
t_eq((int) ops_val("SELECT COUNT(*) FROM requirement_versions WHERE entity_id=?", [$rqL]), 0,
     'L7 · *** …nor read the requirement versions behind it ***');
t_eq(crev_open_on_process($rqL), [], 'L8 · …nor the open reviews on its requirement');

$enterWs($WS_A);
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);
t_eq($whoAmI(), $idA, 'L9 · back in tenant A');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidate_reviews"), $aCount,
     'L10 · *** tenant A\'s reviews are exactly as they were — tenant B changed nothing ***');
t_eq($openCount($cL), 1, 'L11 · …and the review is still open, still unresolved');
t_eq((string) crev_review((int) $rvL['id'])['status'], 'OPEN', 'L12 · …with its status untouched');

// ---------------------------------------------------------------------------
t_section('G3 · M — Gate 2 protection: nothing in Gate 2 moved');
// ---------------------------------------------------------------------------
$hM2 = $approve(['job_title' => 'G3 G2Check', 'quantity' => 6]);
$v1M = rver_current('HIRING_REQUEST', $hM2);
t_eq((int) $v1M['version'], 1, 'M1 · an approved requirement still has version 1');
hreq_save($hM2, $base(['job_title' => 'G3 G2Check', 'quantity' => 6, 'grade' => 'G7']));
t_ok(is_array(rver_pending('HIRING_REQUEST', $hM2)), 'M2 · a material change is still a proposal');
t_eq((string) hreq_get($hM2)['grade'], '', 'M3 · *** the approved record still does not carry it ***');
t_eq((int) rver_current('HIRING_REQUEST', $hM2)['version'], 1, 'M4 · …and no version was created');
[$mOk] = hreq_save($hM2, $base(['job_title' => 'G3 G2Check', 'quantity' => 6, 'grade' => 'G8']));
t_ok(!$mOk, 'M5 · one pending proposal at a time still holds');
$pM = rver_pending('HIRING_REQUEST', $hM2);
[$mrOk] = rver_reject((int) $pM['id'], 'not now');
t_ok($mrOk, 'M6 · a refusal still works');
t_eq((string) rver_proposal((int) $pM['id'])['status'], 'REJECTED', 'M7 · …and is kept for ever');
//  G7, not G8: the G8 attempt was REFUSED above as a second pending change, so it
//  never became a proposal. The refused proposal is the first one, intact.
t_eq((string) rver_proposed_fields(rver_proposal((int) $pM['id']))['grade'], 'G7',
     'M8 · …with the values that were actually refused, kept intact');
[$mnOk] = hreq_save($hM2, $base(['job_title' => 'G3 G2Check', 'quantity' => 6, 'grade' => 'G9']));
t_ok($mnOk, 'M9 · …and another proposal may follow a refusal');
//  Budget materiality and the A8 floor, untouched.
$cmt = rver_commitment_material('REQUISITION', ['est_cost_per_person' => 100, 'quantity' => 1], ['est_cost_per_person' => 100, 'quantity' => 1]);
t_ok(!$cmt['material'], 'M10 · an unchanged commitment is still not material');
t_eq(rver_floor_breaches(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqA]),
     ['min_experience_years' => 0]) === [], false,
     'M11 · *** the A8 floor still catches a weakening below the approved minimum ***');
//  The offer version stamp, and the pre-Gate-2 fallback behind it.
t_eq((int) rver_offer_pinned_version('REQUISITION', $rqC, $cIssued), 1,
     'M12 · the issued-offer version stamp still answers');
$pdo->prepare("UPDATE job_offers SET req_version_at_issue=0 WHERE id=?")->execute([$oIss]);
t_ok((int) rver_offer_pinned_version('REQUISITION', $rqC, $cIssued) > 0,
     'M13 · *** …and an offer with no stamp still falls back to its timestamp ***');
$pdo->prepare("UPDATE job_offers SET req_version_at_issue=1 WHERE id=?")->execute([$oIss]);

// ---------------------------------------------------------------------------
t_section('G3 · N — the configuration, and what cannot be configured away');
// ---------------------------------------------------------------------------
t_ok(in_array('stricter', crev_triggers(), true), 'N1 · stricter raises reviews by default');
t_ok(in_array('redefined', crev_triggers(), true), 'N2 · …and so does a redefinition, by default');
setting_set('crev_trigger_redefined', '0');
t_ok(!in_array('redefined', crev_triggers(), true), 'N3 · a redefinition can be switched off');
t_ok(in_array('stricter', crev_triggers(), true),
     'N4 · *** …but STRICTER can never be switched off: A1 is not configurable ***');
setting_set('crev_trigger_stricter', '0');
t_ok(in_array('stricter', crev_triggers(), true),
     'N5 · *** …not even by writing the setting directly ***');
setting_set('crev_trigger_redefined', '1'); setting_set('crev_trigger_stricter', '');

//  WHAT A REVIEW STOPS, and the floor under it.
$hN = $approve(['job_title' => 'G3 Effect']);
$rqN = $mkReq($hN, 9, 4);
$cN = $mkCand($rqN, 20);
$bump($rqN, ['min_experience_years' => 10]);
t_ok(crev_block_reason($cN, 'ADVANCE') !== '', 'N6 · by default a review stops advancing');
setting_set('crev_allow_screening', '1');
t_eq(crev_block_reason($cN, 'ADVANCE'), '', 'N7 · an organisation may let screening continue');
t_eq(crev_block_reason($cN, 'INTERVIEW'), '', 'N8 · …and interviewing');
t_ok(crev_block_reason($cN, 'OFFER') !== '',
     'N9 · *** an OFFER can never be unblocked: it is a promise to a person ***');
t_ok(crev_block_reason($cN, 'JOIN') !== '', 'N10 · *** nor a JOINING ***');
setting_set('crev_allow_screening', '0');

//  A REDEFINITION, end to end.
$hN2 = $approve(['job_title' => 'G3 Redefine']);
$rqN2 = $mkReq($hN2, 9, 4);
$cN2 = $mkCand($rqN2, 20);
[$vN2] = $bump($rqN2, ['designation' => 'SUPERVISOR']);
t_eq($vN2, 2, 'N11 · the role itself was changed');
t_eq($openCount($cN2), 1, 'N12 · *** a candidate sourced for the old role is reviewed ***');
t_eq((string) ops_val("SELECT trigger_kind FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cN2]),
     'redefined', 'N13 · …recorded as a redefinition, not as a raise');

//  THE EVIDENCE TABLE is evidence. It reports, and it decides nothing.
$ev = crev_evidence(ops_one("SELECT * FROM candidates WHERE id=?", [$cB['far below']]), 'REQUISITION', $rqB);
t_ok(!empty($ev['rows']), 'N14 · the evidence table has something to say about a weak candidate');
$expRow = null; foreach ($ev['rows'] as $r2) if ($r2['label'] === 'Minimum experience') $expRow = $r2;
t_eq($expRow['meets'], false, 'N15 · …and says plainly that they are below the bar');
t_eq($openCount($cB['far below']), 1, 'N16 · *** …and they are STILL only in review, not rejected ***');
$evTop = crev_evidence(ops_one("SELECT * FROM candidates WHERE id=?", [$cB['far above']]), 'REQUISITION', $rqB);
$expTop = null; foreach ($evTop['rows'] as $r2) if ($r2['label'] === 'Minimum experience') $expTop = $r2;
t_eq($expTop['meets'], true, 'N17 · it says the strong candidate clears it');
t_eq($openCount($cB['far above']), 1, 'N18 · *** …and they are STILL in review, not cleared ***');

// ---------------------------------------------------------------------------
t_section('G3 · Z — this file puts back what it changed');
// ---------------------------------------------------------------------------
//  WHY A TEST FILE HAS TO TIDY UP HERE.
//
//  The whole suite runs in ONE process against ONE database, so anything left
//  behind is handed to every test that follows. Two things this file changes are
//  not fixtures but shared state, and both were found by a later test failing for
//  a reason that had nothing to do with it:
//
//    · LIVE DEMAND. The command centre shows the EIGHT biggest open requirements.
//      This file raises well over eight, so an existing reconciliation test — which
//      quite reasonably expects its own six-seat requirement on that screen — was
//      pushed off the list by fixtures. The product was right; the leftovers were
//      not. Closing them returns the screen to what the next test expects.
//
//    · THE ROLE PERMISSION MODEL. Section E grants hiring.review.clear to a role
//      to prove an organisation can, which writes a workspace-wide override of that
//      role's whole permission set. Left behind, every later test runs against a
//      permission model this file invented.
//
//  Nothing is deleted: the requirements, candidates, reviews and versions all stay
//  exactly as they are, as the evidence for everything asserted above. They simply
//  stop being LIVE.
$closedBack = 0;
foreach (array_unique($madeReqs) as $rqid) {
    try {
        db()->prepare("UPDATE requisitions SET status='CANCELLED' WHERE id=?")->execute([(int) $rqid]);
        $closedBack++;
    } catch (Throwable $e) {}
}
t_ok($closedBack === count(array_unique($madeReqs)) && $closedBack > 0,
     'Z1 · every requirement this file raised is closed again (' . $closedBack . ')');
t_eq((int) ops_val("SELECT COUNT(*) FROM requisitions WHERE id IN ("
     . implode(',', array_map('intval', array_unique($madeReqs))) . ") AND status<>'CANCELLED'"), 0,
     'Z2 · *** …so none of them is live demand for the next test ***');
t_ok((int) ops_val("SELECT COUNT(*) FROM candidate_reviews") > 0,
     'Z3 · …while every review raised above is still on the record');

setting_set('role_access', $roleAccessBefore);
t_eq((string) setting_get('role_access', ''), $roleAccessBefore,
     'Z4 · *** the role permission model is exactly as this file found it ***');
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);
t_ok(!can('hiring.review.clear') || is_master(),
     'Z5 · …and no role was left holding a permission it did not start with');
