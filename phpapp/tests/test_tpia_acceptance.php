<?php
// TPIA Phase 1 — the owner's acceptance list (assessment §8), the rows not already
// proved in test_tpia_call_refs / _visit_days / _report_obligations / _final_docs /
// test_po_lock. Made-up data: contract C-100, ABC Engineering Works and XYZ Forgings,
// PO-4587 with 2 lots, Inspector X on day 1 and Inspector Y on day 5, release for
// lot 1 only.
//   13  Y starts the next stage from X's approved report: a new draft, the next
//       number, Y as inspector; X's report unchanged; both Histories say so.
//   14  Y cannot open X's original report (inspectors read only their own).
//   15  A Release Note for Lot 1 lists only the released items; Lot 2 still open.
//   16  A second Release Note on the same PO, for Lot 2 (its own call) is allowed.
//   18  The "Reports to write" figure equals the rows of the list it opens, and a
//       separate call per manufacturer (ABC, XYZ) keeps each one's history apart.
t_section('TPIA Phase 1 — acceptance scenarios 13–16 and 18');

// Committed rows, removed at the end: starting a next-stage report takes the next
// report number through idems_next_serial(), which runs its own transaction and so
// cannot sit inside a test transaction.
$made = ['offices' => [], 'business_partners' => [], 'calls' => [], 'inspectors' => [], 'jobs' => [], 'report_docs' => [], 'users' => []];
$keep = function ($t) use (&$made) { $made[$t][] = (int)db()->lastInsertId(); return (int)db()->lastInsertId(); };
try {
    $pdo = db();
    t_as_admin();
    $typeId = idems_build_fire_extinguisher_report();
    $pdo->prepare("INSERT INTO offices (code, name) VALUES (?,?)")->execute(['TPAC', 'TPIA acceptance office']);
    $off = $keep('offices');
    $pdo->prepare("INSERT INTO business_partners (legal_name, is_client, created_at) VALUES ('TPAC Client Power',1,?)")->execute([date('c')]);
    $cli = $keep('business_partners');
    $vend = [];
    foreach (['ABC Engineering Works', 'XYZ Forgings'] as $n) { $pdo->prepare("INSERT INTO business_partners (legal_name, is_vendor, created_at) VALUES (?,1,?)")->execute([$n, date('c')]); $vend[$n] = $keep('business_partners'); }
    $call = function ($code, $vendor, $lot) use ($pdo, $cli, $off, $keep) {
        $pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, executing_office_id, ibo_office_id, contract_number, supply_po_ref, offered_scope, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$code, $cli, $vendor, $off, $off, 'C-100', 'PO-4587', $lot, 'OPEN', 'x']);
        return $keep('calls');
    };
    $cL1 = $call('TPAC-L1', $vend['ABC Engineering Works'], 'Lot 1');
    $cL2 = $call('TPAC-L2', $vend['ABC Engineering Works'], 'Lot 2');
    $cX  = $call('TPAC-X1', $vend['XYZ Forgings'], 'Lot 1');
    $pdo->prepare("INSERT INTO inspectors (name, home_office_id) VALUES ('TPAC Inspector X', ?)")->execute([$off]);
    $insX = $keep('inspectors');
    $pdo->prepare("INSERT INTO inspectors (name, home_office_id) VALUES ('TPAC Inspector Y', ?)")->execute([$off]);
    $insY = $keep('inspectors');
    $job = function ($code, $callId, $ins) use ($pdo, $off, $keep) {
        $pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, reporting_frequency, scheduled_date, closed_flag, created_at) VALUES (?,?,?,?, 'PER_DAY', ?, 0, ?)")
            ->execute([$code, $callId, $off, $ins, date('Y-m-d', strtotime('-3 days')), 'x']);
        return $keep('jobs');
    };
    $jX = $job('TPAC-JX', $cL1, $insX);
    $jY = $job('TPAC-JY', $cL1, $insY);
    $items = [['item' => 'Gate valve 2"', 'ordered' => '100', 'offered' => '40', 'passed_qty' => '40'],
              ['item' => 'Globe valve 3"', 'ordered' => '60', 'offered' => '0', 'passed_qty' => '0']];
    $pdo->prepare("INSERT INTO report_docs (report_type_id, type_code, title, client_id, vendor_id, call_id, job_id, po_ref, office_id, inspector_id, inspection_date, irn, status, finalized, rev, data, created_by, created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,0,?,?,?)")
        ->execute([$typeId, 'FEXT', 'Stage 1', $cli, $vend['ABC Engineering Works'], $cL1, $jX, 'PO-4587', $off, $insX, date('Y-m-d', strtotime('-3 days')),
                   'TPAC-IR-0001', 'APPROVED', json_encode(['po_items' => $items, 'observations' => 'Dimensions within tolerance']), 'X', 'x']);
    $rX = $keep('report_docs');
    $before = ops_one("SELECT * FROM report_docs WHERE id=?", [$rX]);

    // 13 — Y continues X's approved report.
    [$newId, $why] = idems_start_next_stage($before, $insY, $jY, 'the coordinator');
    $nd = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$newId]);
    if ($newId > 0) $made['report_docs'][] = (int)$newId;
    t_ok($newId > 0 && $why === '', 'scenario 13: a next-stage report is created for Inspector Y');
    t_ok($nd && $nd['status'] === 'DRAFT' && (int)$nd['inspector_id'] === $insY && (int)$nd['prev_stage_doc_id'] === $rX, '… a new draft, Y as inspector, continuing X\'s report');
    t_ok($nd && $nd['irn'] !== $before['irn'] && $nd['irn'] !== '', '… with its own (next) number');
    t_ok($nd && $nd['po_ref'] === 'PO-4587' && (int)$nd['call_id'] === $cL1, '… carrying the PO and the call');
    $after = ops_one("SELECT * FROM report_docs WHERE id=?", [$rX]);
    t_eq([$after['status'], $after['data'], $after['inspector_id'], $after['irn']], [$before['status'], $before['data'], $before['inspector_id'], $before['irn']], "… X's report is unchanged");
    t_ok((int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity_id=? AND action='CONTINUES_FROM'", [(int)$newId]) === 1
         && (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity_id=? AND action='NEXT_STAGE'", [$rX]) === 1, '… and both Histories say who and when');

    // 14 — Y cannot open X's original report.
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, home_office_id, inspector_id) VALUES (?,?,?,1,?,?)")->execute(['tpac_y', 'Y', 'INSPECTOR', $off, $insY]);
    $uY = $keep('users');
    $_SESSION['uid'] = $uY; current_user(true); if (function_exists('ua')) ua(true);
    t_eq(idems_doc_access($after), '', "scenario 14: Inspector Y cannot open X's original report");
    t_ok(idems_doc_access($nd) !== '', '… but opens their own next-stage report');
    t_as_admin();

    // 15 — a Release Note for Lot 1 lists only what was released.
    $rel = idems_released_line_items($items);
    t_eq(count($rel), 1, 'scenario 15: the Release Note carries only the released line (40 gate valves)');
    t_eq($rel[0]['item'] ?? '', 'Gate valve 2"', '… the globe valves (not offered) stay out — still open');

    // 16 — a second Release Note on PO-4587, for Lot 2 (its own call).
    $pdo->prepare("INSERT INTO report_docs (report_type_id, type_code, call_id, client_id, po_ref, irn, status, finalized, release_of_id, data, created_at) VALUES (?,?,?,?,?,?,?,1,?,'[]','x')")
        ->execute([$typeId, 'RN', $cL1, $cli, 'PO-4587', 'TPAC-RN-0001', 'ISSUED', $rX]);
    $rn1 = $keep('report_docs');
    $pdo->prepare("UPDATE report_docs SET report_type_id=? WHERE id=?")->execute([$typeId, $rn1]);
    t_ok(idems_po_locked(['report_type_id' => $typeId, 'client_id' => $cli, 'po_ref' => 'PO-4587', 'call_id' => $cL2]) === null,
         'scenario 16: a second Release Note (or report) on PO-4587 for Lot 2 — its own call — is allowed');
    $pdo->prepare("INSERT INTO report_docs (report_type_id, type_code, call_id, client_id, po_ref, irn, status, finalized, data, created_at) VALUES (?,?,?,?,?,?,?,1,'[]','x')")
        ->execute([$typeId, 'FEXT', $cL2, $cli, 'PO-4587', 'TPAC-IR-0002', 'ISSUED']);
    $rL2 = $keep('report_docs');
    t_ok(idems_release_note_for($rL2) === null && (int)(idems_release_note_for($rX)['id'] ?? 0) === $rn1, "… Lot 2's report shows no Release Note yet; Lot 1's shows its own");

    // 18 — the figure equals the list it opens, and the manufacturers stay apart.
    if (!function_exists('view')) { function view($tpl, $data = []) { $GLOBALS['__nv'] = $data; $GLOBALS['__nv_tpl'] = $tpl; } }
    $n = opk_count_jobs('to_write');
    $GLOBALS['__nv'] = null; $_GET = ['f' => 'to_write'];
    try { ops_jobs('jobs', 'GET'); } catch (Throwable $e) {}
    t_eq(count($GLOBALS['__nv']['rows'] ?? [-1]), $n, "scenario 18: \"Reports to write\" ($n) = rows in the list it opens");
    $ids = array_map('intval', array_column($GLOBALS['__nv']['rows'] ?? [], 'id'));
    t_ok(in_array($jY, $ids, true) && !in_array($jX, $ids, true), "… Y's job (its day's report still a draft) is in it; X's (its day reported) is not");
    $_GET = [];
    $hm = chain_history_for_call($cL1, 'mfr');
    t_eq(array_map('intval', array_column($hm['calls'], 'id')), [$cL1, $cL2], 'contract C-100 with ABC and XYZ: the ABC history holds only the ABC calls');
    $hp = chain_history_for_call($cL1, 'po');
    t_eq(count($hp['calls']), 3, '… while the PO history shows every call on PO-4587, side by side, never merged');
    t_as_nobody();
} finally {
    $_GET = [];
    t_as_nobody();
    if ($made['report_docs']) try { db()->exec("DELETE FROM idems_audit WHERE entity_id IN (" . implode(',', $made['report_docs']) . ")"); } catch (Throwable $e) {}
    foreach (array_reverse($made, true) as $t => $ids)
        if ($ids) try { db()->exec("DELETE FROM $t WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")"); } catch (Throwable $e) {}
}
t_ok(!ops_one("SELECT id FROM calls WHERE call_code='TPAC-L1'"), 'the scenario rows were removed again');
