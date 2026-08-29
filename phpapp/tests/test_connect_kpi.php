<?php
// Connect — the reusable KPI board. One engine, two audiences (staff + client).
// Asserts each metric reuses the real tables and scopes correctly by client party.
// (t_eq is t_eq($got, $want).)
t_section('connect reusable KPI board');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // A client party.
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, created_at) VALUES ('KPI Client', 1, ?)")->execute([date('c')]);
    $cid = (int)db()->lastInsertId();
    // A second client, to prove scoping keeps them apart.
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, created_at) VALUES ('Other Client', 1, ?)")->execute([date('c')]);
    $cid2 = (int)db()->lastInsertId();

    // --- inspections: jobs link to a client via their call --------------------
    db()->prepare("INSERT INTO calls (client_id, status, created_at) VALUES (?, 'OPEN', ?)")->execute([$cid, date('c')]);
    $call = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (client_id, status, created_at) VALUES (?, 'OPEN', ?)")->execute([$cid2, date('c')]);
    $call2 = (int)db()->lastInsertId();
    // client 1: two closed + one open job
    db()->prepare("INSERT INTO jobs (call_id, closed_flag, created_at) VALUES (?,1,?)")->execute([$call, date('c')]);
    db()->prepare("INSERT INTO jobs (call_id, closed_flag, created_at) VALUES (?,1,?)")->execute([$call, date('c')]);
    db()->prepare("INSERT INTO jobs (call_id, closed_flag, created_at) VALUES (?,0,?)")->execute([$call, date('c')]);
    // client 2: one closed job
    db()->prepare("INSERT INTO jobs (call_id, closed_flag, created_at) VALUES (?,1,?)")->execute([$call2, date('c')]);

    // --- open concerns: complaints scoped by partner_id -----------------------
    db()->prepare("INSERT INTO complaints (partner_id, status, created_at) VALUES (?, 'OPEN', ?)")->execute([$cid, date('c')]);
    db()->prepare("INSERT INTO complaints (partner_id, status, created_at) VALUES (?, 'OPEN', ?)")->execute([$cid, date('c')]);
    db()->prepare("INSERT INTO complaints (partner_id, status, created_at) VALUES (?, 'CLOSED', ?)")->execute([$cid, date('c')]);
    db()->prepare("INSERT INTO complaints (partner_id, status, created_at) VALUES (?, 'OPEN', ?)")->execute([$cid2, date('c')]);

    // --- reports pending: report_docs scoped by client_id ---------------------
    db()->prepare("INSERT INTO report_docs (client_id, status, deleted) VALUES (?, 'DRAFT', 0)")->execute([$cid]);
    db()->prepare("INSERT INTO report_docs (client_id, status, deleted) VALUES (?, 'SUBMITTED', 0)")->execute([$cid]);
    db()->prepare("INSERT INTO report_docs (client_id, status, deleted) VALUES (?, 'ISSUED', 0)")->execute([$cid]); // issued = not pending

    // --- build the client board ----------------------------------------------
    $b = connect_kpi_board(['audience' => 'client', 'party_id' => $cid]);
    t_eq($b['audience'], 'client', 'the board knows its audience');
    t_eq($b['raw']['inspections_done'], 2, 'inspections done counts this client\'s closed jobs only');
    t_eq($b['raw']['inspections_open'], 1, 'inspections open counts this client\'s open jobs only');
    t_eq($b['raw']['concerns'], 2, 'open concerns counts this client\'s OPEN complaints only');
    t_eq($b['raw']['reports_pending'], 2, 'reports pending counts this client\'s non-issued reports only');

    // The five tiles + actions are present and shaped.
    t_eq(count($b['tiles']), 5, 'the board has five KPI tiles');
    $keys = array_map(fn($t) => $t['key'], $b['tiles']);
    foreach (['inspections', 'revenue', 'concerns', 'ratings', 'reports'] as $k)
        t_ok(in_array($k, $keys, true), "the board has the $k tile");
    // Actions surface report-pending and open-concern.
    $alabels = array_map(fn($a) => $a['label'], $b['actions']);
    t_ok(in_array('Reports pending', $alabels, true), 'an action calls out reports pending');
    t_ok(in_array('Open concerns', $alabels, true), 'an action calls out open concerns');

    // --- scoping: the other client sees only its own numbers ------------------
    $b2 = connect_kpi_board(['audience' => 'client', 'party_id' => $cid2]);
    t_eq($b2['raw']['inspections_done'], 1, 'the other client sees only its own closed job');
    t_eq($b2['raw']['concerns'], 1, 'the other client sees only its own open concern');
    t_eq($b2['raw']['reports_pending'], 0, 'the other client has no pending reports');

    // --- the SAME engine at staff scope aggregates across everyone ------------
    $s = connect_kpi_board(['audience' => 'staff']);
    t_eq($s['audience'], 'staff', 'the staff board knows its audience');
    t_ok($s['raw']['inspections_done'] >= 3, 'staff inspections aggregate across clients');
    t_ok($s['raw']['concerns'] >= 3, 'staff concerns aggregate across clients');
    t_ok($s['raw']['reports_pending'] >= 2, 'staff reports-pending aggregate across clients');

    // The renderer runs without error and emits the board markup.
    ob_start(); connect_kpi_render($b); $html = ob_get_clean();
    t_ok(strpos($html, 'kpiq-row') !== false, 'the shared renderer emits the KPI board markup');
    t_ok(strpos($html, 'Inspections') !== false, 'the rendered board shows the Inspections tile');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
