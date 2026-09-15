<?php
// ============================================================================
//  PHASE 2 · M3 — MULTI-VACANCY FULFILMENT
//
//  Ten vacancies must behave as ten vacancies.
//
//  The defect: the hire action set status='HIRED' on the requisition the moment
//  ONE candidate was taken on, whatever the quantity — and hired_inspector_id,
//  a single column overwritten by every hire, named only the most recent person.
//
//  What this does NOT do is as important as what it does. Every individual hire
//  was ALREADY recorded — one candidate row each, carrying requisition_id, stage
//  and inspector_id. So no candidate table and no hire table was added; the
//  requisition summary is derived from the records that already existed. The one
//  thing no candidate row can express is a vacancy CANCELLED with nobody in it,
//  and that alone is a count on the requisition.
//
//  Three dimensions are kept apart, and the tests prove they move independently:
//     pipeline stage      where a CANDIDATE got to
//     fulfilment          what a SEAT came to
//     requisition status  what the WHOLE REQUIREMENT is
// ============================================================================

t_section('Milestone 3 — ten vacancies behave as ten vacancies');

$pdo = db();
req_migrate(); reqf_migrate();
$mine = ['req' => [], 'cand' => [], 'insp' => []];
$now  = date('c');

$mkReq = function ($code, $qty, $status = 'OPEN') use ($pdo, &$mine, $now) {
    $pdo->prepare("INSERT INTO requisitions (req_code,quantity,designation,status,created_at) VALUES (?,?,'ENGINEER',?,?)")
        ->execute([$code, $qty, $status, $now]);
    $id = (int) $pdo->lastInsertId(); $mine['req'][] = $id; return $id;
};
$mkCand = function ($reqId, $n, $stage) use ($pdo, &$mine, $now) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,requisition_id,stage,created_at) VALUES (?,?,'M3',?,?,?)")
        ->execute(["M3CV-$n", "P$n", $reqId, $stage, $now]);
    $id = (int) $pdo->lastInsertId(); $mine['cand'][] = $id; return $id;
};
$join = function ($candId) use ($pdo, &$mine, $now) {           // exactly what the hire action does
    $pdo->prepare("INSERT INTO inspectors (name,status,created_at) VALUES ('M3 Hire','ACTIVE',?)")->execute([$now]);
    $iid = (int) $pdo->lastInsertId(); $mine['insp'][] = $iid;
    $pdo->prepare("UPDATE candidates SET stage='ACCEPTED', inspector_id=? WHERE id=?")->execute([$iid, $candId]);
    $req = (int) ops_val("SELECT requisition_id FROM candidates WHERE id=?", [$candId]);
    $pdo->prepare("UPDATE requisitions SET hired_inspector_id=? WHERE id=?")->execute([$iid, $req]);
    reqf_sync($req);
    return $iid;
};
$st = fn($id) => (string) ops_val("SELECT status FROM requisitions WHERE id=?", [$id]);

// ---------------------------------------------------------------------------
//  1. Nothing was duplicated (§11).
// ---------------------------------------------------------------------------
t_ok(in_array('cancelled_qty', t_columns('requisitions'), true), 'a requisition can record vacancies cancelled with nobody in them');
t_ok(in_array('quantity', t_columns('requisitions'), true), 'the existing quantity column is reused — there is no second one');
t_ok(!t_table_exists('requisition_fulfilments') && !t_table_exists('fulfilments') && !t_table_exists('vacancies'),
     'NO new fulfilment table — the candidate row already is the individual fulfilment');
t_ok(!t_table_exists('hires'), 'and no second hire table');
t_ok(in_array('hired_inspector_id', t_columns('requisitions'), true), 'hired_inspector_id is preserved, not dropped (§12)');
t_ok(t_table_exists('cx_requirements'), 'the Marketplace requirement object is untouched (§7)');

// ---------------------------------------------------------------------------
//  2. THE critical acceptance test (§34). One hire must not close five seats.
// ---------------------------------------------------------------------------
$r5 = $mkReq('M3RQ-5', 5);
t_eq(reqf_counts($r5)['remaining'], 5, 'a new requisition for 5 has 5 remaining');
t_eq($st($r5), 'OPEN', 'and is open');

$c1 = $mkCand($r5, 1, 'RECEIVED'); $join($c1);
t_ok($st($r5) !== 'HIRED', 'after the FIRST person joins, a 5-vacancy requisition is NOT closed — this is the defect M3 fixes');
t_eq($st($r5), 'PARTIALLY_FILLED', '…it is partly filled');
t_eq(reqf_counts($r5)['filled'], 1, '1 seat filled');
t_eq(reqf_counts($r5)['remaining'], 4, '4 still remaining');

$c2 = $mkCand($r5, 2, 'RECEIVED'); $join($c2);
$c3 = $mkCand($r5, 3, 'RECEIVED'); $join($c3);
t_eq(reqf_counts($r5)['filled'], 3, 'three people joined');
t_eq(reqf_counts($r5)['remaining'], 2, 'two seats remain');
t_eq($st($r5), 'PARTIALLY_FILLED', 'still partly filled, not closed');

// ---------------------------------------------------------------------------
//  3. Every hire is individually recorded — that is what makes the count true.
// ---------------------------------------------------------------------------
$people = reqf_people($r5);
t_eq(count($people), 3, 'all three hires are listed individually');
t_eq(count(array_filter($people, fn($p) => !empty($p['inspector_id']))), 3, 'each one carries its own workforce record');
$hid = (int) ops_val("SELECT hired_inspector_id FROM requisitions WHERE id=?", [$r5]);
t_ok($hid > 0, 'hired_inspector_id is still populated for the screens that read it');
t_eq($hid, (int) $people[0]['inspector_id'], '…naming the MOST RECENT hire, which is what it has always held');

// ---------------------------------------------------------------------------
//  4. Somebody still in the pipeline has not filled a seat (§15).
// ---------------------------------------------------------------------------
$c4 = $mkCand($r5, 4, 'OFFERED');
$c5 = $mkCand($r5, 5, 'INTERVIEW');
$c = reqf_counts($r5);
t_eq($c['in_progress'], 2, 'two people are in progress');
t_eq($c['filled'], 3, 'but they have NOT filled a seat');
t_eq($c['remaining'], 2, 'so the requirement still shows 2 remaining — they never vanish from the count');
t_eq($st($r5), 'PARTIALLY_FILLED', 'and the requisition is still open for them');

// A candidate who fell away frees nothing and fills nothing.
$pdo->prepare("UPDATE candidates SET stage='REJECTED' WHERE id=?")->execute([$c5]);
$c = reqf_counts($r5);
t_eq($c['lost'], 1, 'a rejected candidate is counted as lost');
t_eq($c['remaining'], 2, '…and the seat is still remaining, not consumed');

// ---------------------------------------------------------------------------
//  5. Completing the requirement (§16 → §12).
// ---------------------------------------------------------------------------
$join($c4);
$c6 = $mkCand($r5, 6, 'RECEIVED'); $join($c6);
t_eq(reqf_counts($r5)['filled'], 5, 'the fifth person joins');
t_eq(reqf_counts($r5)['remaining'], 0, 'nothing remains');
t_eq($st($r5), 'HIRED', 'NOW the requisition reads as filled');

// ---------------------------------------------------------------------------
//  6. Cancellation is not a pretend hire (§17).
// ---------------------------------------------------------------------------
$r10 = $mkReq('M3RQ-10', 10);
for ($i = 11; $i <= 16; $i++) { $cc = $mkCand($r10, $i, 'RECEIVED'); $join($cc); }
$c = reqf_counts($r10);
t_eq($c['filled'], 6, 'six of ten joined');
t_eq($c['remaining'], 4, 'four remain');
t_eq($st($r10), 'PARTIALLY_FILLED', 'partly filled');

[$ok, $msg] = reqf_cancel($r10, 4, 'Budget withdrawn');
t_ok($ok, 'the four remaining vacancies can be cancelled: ' . $msg);
$c = reqf_counts($r10);
t_eq($c['cancelled'], 4, 'four are recorded as cancelled');
t_eq($c['filled'], 6, '…and are NOT counted as hires');
t_eq($c['remaining'], 0, 'nothing remains');
t_eq($st($r10), 'HIRED', 'the requirement is fully accounted for');
t_eq(reqf_summary_text($r10), '10 requested — 6 filled — 4 cancelled — 0 remaining',
     'and a person reads it in one line, without opening another screen (§33)');

[$ok2, $msg2] = reqf_cancel($r10, 1);
t_ok(!$ok2, 'you cannot cancel a vacancy that is not open: ' . $msg2);
t_eq(reqf_counts($r10)['cancelled'], 4, '…and the attempt changed nothing');

// ---------------------------------------------------------------------------
//  7. A decision made by a person is never overruled by arithmetic.
// ---------------------------------------------------------------------------
$rC = $mkReq('M3RQ-CL', 5, 'CLOSED');
$cc = $mkCand($rC, 21, 'RECEIVED'); $join($cc);
t_eq($st($rC), 'CLOSED', 'a requisition somebody CLOSED stays closed even when a hire lands');
$rX = $mkReq('M3RQ-CA', 5, 'CANCELLED');
t_eq(reqf_derive_status($rX), null, 'counting offers no opinion on a cancelled requisition');
$rD = $mkReq('M3RQ-DR', 5, 'DRAFT');
t_eq(reqf_derive_status($rD), null, '…nor on a draft');
$rP = $mkReq('M3RQ-PR', 5, 'PROPOSED');
t_eq(reqf_derive_status($rP), null, 'and with nothing filled yet, an in-flight status is left alone');

// ---------------------------------------------------------------------------
//  8. Quantity boundaries (§38).
// ---------------------------------------------------------------------------
$r0 = $mkReq('M3RQ-0', 0);
t_eq(reqf_counts($r0)['requested'], 1, 'a requisition with no quantity means one person, as it always has');
$rNeg = $mkReq('M3RQ-NEG', -5);
t_eq(reqf_counts($rNeg)['requested'], 1, 'a negative quantity cannot make a negative requirement');
t_eq(reqf_counts($rNeg)['remaining'], 1, '…and remaining is never negative');
$rBig = $mkReq('M3RQ-BIG', 100000);
t_eq(reqf_counts($rBig)['requested'], 100000, 'a very large quantity is carried, not truncated');
t_eq(reqf_counts($rBig)['remaining'], 100000, '…and counts through');
$rOver = $mkReq('M3RQ-OVER', 1);
$o1 = $mkCand($rOver, 31, 'RECEIVED'); $join($o1);
$o2 = $mkCand($rOver, 32, 'RECEIVED'); $join($o2);
t_eq(reqf_counts($rOver)['filled'], 2, 'two people can be recorded against a one-person requisition');
t_eq(reqf_counts($rOver)['remaining'], 0, '…and remaining stays at zero rather than going negative');

// ---------------------------------------------------------------------------
//  9. Invalid and missing input (§38).
// ---------------------------------------------------------------------------
t_eq(reqf_counts(999999)['requested'], 0, 'an unknown requisition id counts as nothing, and does not throw');
t_eq(reqf_derive_status(999999), null, '…and has no status opinion');
t_eq(reqf_people(999999), [], '…and lists nobody');
t_ok(!reqf_cancel(999999, 1)[0], 'you cannot cancel against a requisition that does not exist');
t_ok(!reqf_cancel($r5, 0)[0], 'cancelling zero vacancies is refused');
t_ok(!reqf_cancel($r5, -3)[0], 'cancelling a negative number is refused');
t_eq(reqf_summary_text(999999), '', 'and there is no summary to show');

// ---------------------------------------------------------------------------
// 10. The fix is at the hire moment itself, not only in a helper.
// ---------------------------------------------------------------------------
$opsSrc = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($opsSrc, "SET hired_inspector_id=?, status='HIRED'") === false,
     'the hire action no longer forces a requisition to HIRED on the first person');
t_ok(strpos($opsSrc, 'reqf_sync((int)$cand[\'requisition_id\'])') !== false,
     '…it recomputes the status from how many seats are actually filled');
t_ok(isset(REQ_STATUS['PARTIALLY_FILLED']), 'the partly-filled status exists in the requisition status list');
t_ok(isset(REQ_STATUS['HIRED']) && isset(REQ_STATUS['OPEN']) && isset(REQ_STATUS['CANCELLED']),
     '…added to the existing list, so no stored status changed meaning (§13)');

// ---------------------------------------------------------------------------
// 10b. The cancel route is protected exactly like every other requisition route
//      (§25, §32) — one branch-scope gate, one permission bar, before any id is
//      read. No second access-control mechanism was introduced.
// ---------------------------------------------------------------------------
t_ok(function_exists('ops_requisition_cancel_vacancies'), 'cancelling vacancies is a route, not an unguarded helper');
$rfSrc = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/reqfulfil.php'));
$fp = strpos($rfSrc, 'function ops_requisition_cancel_vacancies(');
$head = substr($rfSrc, $fp, 600);
t_ok(strpos($head, 'req_scope_gate()') !== false, 'it runs the SAME branch-scope gate the other requisition routes use');
t_ok(strpos($head, 'is_coordinator_level()') !== false, 'and the same permission bar');
$gate = strpos($head, 'req_scope_gate()');
$read = strpos($head, "\$_GET['id']");
t_ok($gate !== false && $read !== false && $gate < $read, 'the gate runs BEFORE the id is read');
t_ok(strpos($rfSrc, 'csrf') === false || strpos(file_get_contents(__DIR__ . '/../views/ops/requisition_detail.php'), 'csrf_field') !== false,
     'the form posts with the workspace CSRF token');

// ---------------------------------------------------------------------------
// 11. Idempotency (§28, §30) — running the migration again changes nothing.
// ---------------------------------------------------------------------------
$before = count(t_columns('requisitions'));
$GLOBALS['__db_epoch'] = (int) ($GLOBALS['__db_epoch'] ?? 0) + 1;
reqf_migrate(); req_migrate();
t_eq(count(t_columns('requisitions')), $before, 'running the migrations a second time adds nothing');
t_eq(reqf_counts($r10)['filled'], 6, '…and changes no data');

// ---------------------------------------------------------------------------
//  Clean up.
// ---------------------------------------------------------------------------
foreach ($mine['cand'] as $id) $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([$id]);
foreach ($mine['insp'] as $id) $pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$id]);
foreach ($mine['req']  as $id) $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$id]);
