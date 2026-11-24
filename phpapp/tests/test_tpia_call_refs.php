<?php
// TPIA reporting Phase 1 — items 1–3 (owner, 25 Nov 2026: D1, D2, D3).
//   1. The client's inspection call number: optional, searchable; a repeat for
//      the same client WARNS and lists the other call(s) — never blocks or merges.
//   2. The inspection-history panel: every call with the same client call no.,
//      client PO on the manufacturer or manufacturer, oldest first, each row a
//      link, under the viewer's existing reach (an inspector reads only their own
//      reports).
//   3. "Client PO no. (placed on the manufacturer)" and "Lot / stage / quantity
//      offered" on the call, copied into every report started from it.
// Made-up data: contract C-100, ABC Engineering Works, PO-4587 with two lots.
t_section('TPIA Phase 1 — client call no., client PO on the manufacturer, lot, history');

foreach (['client_call_ref', 'supply_po_ref', 'offered_scope'] as $c)
    t_ok(in_array($c, t_columns('calls'), true), "calls.$c exists (empty on every older work order)");
foreach (['client_call_ref', 'supply_po_ref', 'offered_scope'] as $c)
    t_ok(in_array($c, call_save_fields(), true), "the call form saves $c");

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $pdo = db();
    $pdo->prepare("INSERT INTO offices (code, name) VALUES (?,?)")->execute(['TPA', 'TPIA office A']);
    $off = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO business_partners (legal_name, is_client, created_at) VALUES ('TP Client Power Ltd',1,?)")->execute([date('c')]);
    $cli = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO business_partners (legal_name, is_client, created_at) VALUES ('TP Other Client',1,?)")->execute([date('c')]);
    $cli2 = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO business_partners (legal_name, is_vendor, created_at) VALUES ('ABC Engineering Works',1,?)")->execute([date('c')]);
    $abc = (int)$pdo->lastInsertId();
    $mkCall = function ($code, $client, array $f = []) use ($pdo, $off, $abc) {
        $f += ['client_call_ref' => '', 'supply_po_ref' => '', 'offered_scope' => '', 'vendor_id' => $abc, 'call_received_date' => '2026-11-01'];
        $pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, executing_office_id, ibo_office_id, contract_number, client_call_ref, supply_po_ref, offered_scope, call_received_date, status, created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$code, $client, $f['vendor_id'], $off, $off, 'C-100', $f['client_call_ref'], $f['supply_po_ref'], $f['offered_scope'], $f['call_received_date'], 'OPEN', 'x']);
        return (int)$pdo->lastInsertId();
    };
    // Scenario 1 + 4: two calls for PO-4587, one per lot, each with its own text.
    $c1 = $mkCall('TP-C1', $cli, ['client_call_ref' => 'IC-778', 'supply_po_ref' => 'PO-4587', 'offered_scope' => 'Lot 1 — 40 valves', 'call_received_date' => '2026-11-01']);
    $c2 = $mkCall('TP-C2', $cli, ['client_call_ref' => 'IC-791', 'supply_po_ref' => 'PO-4587', 'offered_scope' => 'Lot 2 — 60 valves', 'call_received_date' => '2026-11-10']);
    $cOther = $mkCall('TP-C9', $cli2, ['client_call_ref' => 'IC-778']);

    // Scenario 2 — a second call typed with IC-778 for the same client: warned, both kept.
    t_as_admin();
    $d = call_client_ref_dups('IC-778', $cli, 0);
    t_eq(array_map('intval', array_column($d['rows'], 'id')), [$c1], 'a repeat of IC-778 for the same client lists the first call');
    t_eq(call_client_ref_dups('ic-778 ', $cli, 0)['rows'] ? 1 : 0, 1, 'the repeat is found whatever the case / spaces');
    t_eq(count(call_client_ref_dups('IC-778', $cli, $c1)['rows']), 0, 'editing the call itself is not a repeat of itself');
    t_eq(count(call_client_ref_dups('IC-778', $cli2, $cOther)['rows']), 0, "another client's IC-778 is not a repeat");
    t_eq(count(call_client_ref_dups('', $cli, 0)['rows']), 0, 'a blank number never warns (the field is optional)');
    $c3 = $mkCall('TP-C3', $cli, ['client_call_ref' => 'IC-778', 'supply_po_ref' => 'PO-4587', 'offered_scope' => 'Lot 1 — re-offer', 'call_received_date' => '2026-11-12']);
    t_eq((int)ops_val("SELECT COUNT(*) FROM calls WHERE client_call_ref='IC-778' AND client_id=?", [$cli]), 2, 'after "Save anyway" both calls are kept — nothing merged');

    // The warning box on the form (rendered through the real template).
    $tpl = __DIR__ . '/../views/ops/call_form.php';
    $vars = call_form_vars(['client_id' => $cli, 'client_call_ref' => 'IC-778'], ['client_call_ref' => 'IC-778']);
    $vars['dupRef'] = $d; $vars['cur'] = 'call-new';
    ob_start();
    try { (function () use ($tpl, $vars) { extract($vars); include $tpl; })(); } catch (Throwable $e) { echo 'THREW ' . $e->getMessage(); }
    $h = (string)ob_get_clean();
    t_ok(strpos($h, 'THREW') === false, 'the call form renders with the repeat warning');
    t_ok(strpos($h, 'name="client_ref_dup_ok"') !== false && strpos($h, 'TP-C1') !== false, 'the warning lists TP-C1 and offers "Save anyway"');
    t_ok(strpos($h, 'name="supply_po_ref"') !== false && strpos($h, 'placed on the') !== false, 'the form names the field "Client PO no. (placed on the manufacturer)"');
    t_ok(stripos($h, 'manufacturer raise') === false && stripos($h, 'manufacturer sets') === false, 'no wording says the manufacturer raises the call or sets the stage');

    // Scenario 1 — found by search (the calls register).
    if (!function_exists('view')) { function view($tpl, $data = []) { $GLOBALS['__nv'] = $data; $GLOBALS['__nv_tpl'] = $tpl; } }
    $GLOBALS['__nv'] = null; $_GET = ['q' => 'IC-778'];
    try { ops_calls('calls', 'GET'); } catch (Throwable $e) {}
    $ids = array_map('intval', array_column($GLOBALS['__nv']['rows'] ?? [], 'id'));
    t_ok(in_array($c1, $ids, true) && in_array($c3, $ids, true) && !in_array($c2, $ids, true), 'the calls register finds IC-778 (both calls), not IC-791');
    $_GET = ['q' => 'PO-4587']; $GLOBALS['__nv'] = null;
    try { ops_calls('calls', 'GET'); } catch (Throwable $e) {}
    $ids = array_map('intval', array_column($GLOBALS['__nv']['rows'] ?? [], 'id'));
    t_ok(in_array($c1, $ids, true) && in_array($c2, $ids, true), 'the calls register finds PO-4587 on both lots');
    $_GET = [];
    $src = file_get_contents(__DIR__ . '/../lib/search.php');
    t_ok(strpos($src, 'c.client_call_ref LIKE ?') !== false && strpos($src, 'c.supply_po_ref LIKE ?') !== false, 'global search looks in the client call no. and the client PO');

    // Item 3 — each report started from a call carries its PO and lot (scenario 4).
    $x1 = idems_context_for($c1); $x2 = idems_context_for($c2);
    t_eq($x1['po_ref'] ?? '', 'PO-4587', 'a report from the Lot 1 call carries P.O. PO-4587');
    t_eq($x1['offered_scope'] ?? '', 'Lot 1 — 40 valves', '… and its own lot text');
    t_eq($x2['offered_scope'] ?? '', 'Lot 2 — 60 valves', 'the Lot 2 call keeps its own lot text');
    t_eq($x1['client_call_ref'] ?? '', 'IC-778', "… and the client's call no.");
    $plain = $mkCall('TP-C4', $cli);
    t_ok(!isset(idems_context_for($plain)['offered_scope']) && !isset(idems_context_for($plain)['po_ref']), 'an older call with the fields empty reads exactly as before');
    $al = idems_field_aliases();
    t_ok(in_array('lot_no', $al['offered_scope'] ?? [], true) && in_array('client_call_no', $al['client_call_ref'] ?? [], true), 'a designed form field "lot_no" / "client_call_no" is filled from the call');

    // Item 2 / scenario 3 — the history panel for IC-778.
    $pdo->prepare("INSERT INTO inspectors (name, home_office_id) VALUES ('TP Inspector X', ?)")->execute([$off]);
    $insX = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO inspectors (name, home_office_id) VALUES ('TP Inspector Y', ?)")->execute([$off]);
    $insY = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, scheduled_date, reporting_frequency, created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute(['TP-J1', $c1, $off, $insX, '2026-11-03', 'PER_DAY', '2026-11-02T10:00:00']);
    $j1 = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?,'DONE')")->execute([$j1, '2026-11-03', $insX]);
    $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?,'PLANNED')")->execute([$j1, '2026-11-07', $insY]);
    $pdo->prepare("INSERT INTO report_docs (call_id, job_id, irn, type_code, status, inspector_id, inspection_date, office_id, deleted, data, created_at) VALUES (?,?,?,?,?,?,?,?,0,'[]',?)")
        ->execute([$c1, $j1, 'TP-IR-001', 'IR', 'APPROVED', $insX, '2026-11-03', $off, '2026-11-03T18:00:00']);
    $rX = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_docs (call_id, job_id, irn, type_code, status, inspector_id, inspection_date, office_id, deleted, release_of_id, data, created_at) VALUES (?,?,?,?,?,?,?,?,0,?,'[]',?)")
        ->execute([$c1, $j1, 'TP-RN-001', 'RN', 'ISSUED', $insX, '2026-11-04', $off, $rX, '2026-11-04T18:00:00']);

    $hist = chain_history_for_call($c1, 'ref');
    t_eq($hist['by'], 'ref', 'the panel opened from the client call no. matches on it');
    t_eq(array_map('intval', array_column($hist['calls'], 'id')), [$c1, $c3], 'IC-778: both calls of this client (not the other client\'s IC-778)');
    $dates = array_column($hist['events'], 'date');
    $sorted = $dates; sort($sorted);
    t_eq($dates, $sorted, 'entries are oldest first');
    $titles = implode(' | ', array_column($hist['events'], 'title'));
    t_ok(strpos($titles, 'TP-IR-001') !== false && strpos($titles, 'Release Note TP-RN-001') !== false, 'the panel lists the report and the release note');
    t_ok(substr_count($titles, 'Visit day') === 2, 'the panel lists every visit day');
    t_ok(count(array_filter($hist['events'], fn($x) => strpos($x['href'], '/') === 0)) === count($hist['events']), 'every entry is a link to the real record');
    $byPo = chain_history_for_call($c1, 'po');
    t_eq(array_map('intval', array_column($byPo['calls'], 'id')), [$c1, $c2, $c3], 'opened from the PO: every call on PO-4587 (both lots)');
    $byMfr = chain_history_for_call($c1, 'mfr');
    t_ok(in_array($plain, array_map('intval', array_column($byMfr['calls'], 'id')), true), 'opened from the manufacturer: every call of this client at ABC');
    t_eq(chain_history_for_call($plain)['by'], 'mfr', 'a call with no number or PO falls back to its manufacturer');

    // Scenario 14 reach — Inspector Y sees the calls but not X's reports.
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, home_office_id, inspector_id) VALUES (?,?,?,1,?,?)")
        ->execute(['tp_insp_y', 'Y', 'INSPECTOR', $off, $insY]);
    $uY = (int)$pdo->lastInsertId();
    $_SESSION['uid'] = $uY; current_user(true); if (function_exists('ua')) ua(true);
    $hy = chain_history_for_call($c1, 'ref');
    $ty = implode(' | ', array_column($hy['events'], 'title'));
    t_ok(strpos($ty, 'TP-IR-001') === false && $hy['hidden_reports'] >= 2, "Inspector Y's panel does not show X's reports (counted, not named)");
    $html = chain_history_panel(ops_one("SELECT * FROM calls WHERE id=?", [$c1]), 'ref');
    t_ok(strpos($html, 'TP-IR-001') === false && strpos($html, 'cannot open') !== false, '… and says some are not shown');
    t_as_admin();
    $html = chain_history_panel(ops_one("SELECT * FROM calls WHERE id=?", [$c1]), 'ref');
    t_ok(strpos($html, 'id="tpia-history"') !== false && strpos($html, 'IC-778') !== false && strpos($html, 'TP-IR-001') !== false, 'the panel renders with its anchor for the coordinator');
    t_as_nobody();
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    $_GET = [];
}
