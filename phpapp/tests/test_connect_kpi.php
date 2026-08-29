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
    // The renderer uses the app's ONE universal KPI-card design (.kpi-row / .kpi
    // / .tone-* / .pill) so it IS the design-system component wherever app.css
    // is loaded, and an identical :where() fallback covers the self-contained portals.
    t_ok(strpos($html, 'class="kpi-row"') !== false, 'the shared renderer emits the universal .kpi-row');
    t_ok(strpos($html, 'class="kpi') !== false, 'the shared renderer emits the universal .kpi card');
    t_ok(strpos($html, 'tone-') !== false, 'the board applies a semantic .tone-* rail');
    t_ok(strpos($html, 'Inspections') !== false, 'the rendered board shows the Inspections tile');

    // --- the SAME engine at 'pro' scope builds a freelancer cockpit -----------
    // A professional with a completed man-days booking, an offered application,
    // and a client rating on that booking.
    db()->prepare("INSERT INTO cx_professionals (name, email, created_at) VALUES ('KPI Pro', 'kpipro@example.test', ?)")->execute([date('c')]);
    $proId = (int)db()->lastInsertId();
    // a requirement + awarded application belonging to this pro
    db()->prepare("INSERT INTO cx_requirements (ref_code, title, status, created_at) VALUES ('R-KPI','Weld QA','AWARDED',?)")->execute([date('c')]);
    $rid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_applications (requirement_id, applicant_professional_id, applicant_name, status) VALUES (?,?,'KPI Pro','AWARDED')")->execute([$rid, $proId]);
    $aid = (int)db()->lastInsertId();
    // a second, still-open OFFERED application (attention item)
    db()->prepare("INSERT INTO cx_requirements (ref_code, title, status, created_at) VALUES ('R-KP2','NDT','OPEN',?)")->execute([date('c')]);
    $rid2 = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_applications (requirement_id, applicant_professional_id, applicant_name, status) VALUES (?,?,'KPI Pro','OFFERED')")->execute([$rid2, $proId]);
    // a completed man-days engagement: 10 days × ₹4000 = ₹40,000 booked value
    db()->prepare("INSERT INTO cx_engagements (requirement_id, application_id, subject_kind, subject_id, basis, rate, rate_unit, quantity, status, created_at)
                   VALUES (?,?, 'professional', ?, 'MAN_DAYS', 4000, 'day', 10, 'COMPLETED', ?)")->execute([$rid, $aid, $proId, date('c')]);
    // a CONTINUOUS engagement contributes NO deterministic value (open-ended)
    db()->prepare("INSERT INTO cx_engagements (requirement_id, application_id, subject_kind, subject_id, basis, rate, rate_unit, status, created_at)
                   VALUES (?,?, 'professional', ?, 'CONTINUOUS', 90000, 'month', 'ACTIVE', ?)")->execute([$rid, $aid, $proId, date('c')]);
    // a client-to-pro rating on the awarded booking
    db()->prepare("INSERT INTO cx_ratings (requirement_id, application_id, direction, stars, created_at) VALUES (?,?, 'CLIENT_TO_PRO', 5, ?)")->execute([$rid, $aid, date('c')]);

    $p = connect_kpi_board(['audience' => 'pro', 'party_id' => $proId]);
    t_eq($p['audience'], 'pro', 'the pro board knows its audience');
    t_eq($p['raw']['assignments_done'], 1, 'pro assignments done counts completed engagements');
    t_eq($p['raw']['assignments_active'], 1, 'pro assignments active counts active engagements');
    t_eq($p['raw']['booked_value'], 40000.0, 'booked value sums only deterministic man-days/months totals');
    t_eq($p['raw']['apps_offered'], 1, 'pro applications counts the offered application');
    t_ok($p['raw']['apps_live'] >= 1, 'pro live applications includes the offer');
    t_eq($p['raw']['rating_avg'], 5.0, 'pro rating averages client-to-pro ratings on its own awards');
    t_eq(count($p['tiles']), 5, 'the pro board has five tiles');
    $pkeys = array_map(fn($t) => $t['key'], $p['tiles']);
    foreach (['assignments', 'value', 'applications', 'ratings', 'verification'] as $k)
        t_ok(in_array($k, $pkeys, true), "the pro board has the $k tile");
    $palabels = array_map(fn($a) => $a['label'], $p['actions']);
    t_ok(in_array('Offers to respond', $palabels, true), 'the pro board surfaces offers to respond');
    // A stranger pro with nothing sees an honest, empty board — no invented value.
    db()->prepare("INSERT INTO cx_professionals (name, email, created_at) VALUES ('Empty Pro', 'emptypro@example.test', ?)")->execute([date('c')]);
    $proId2 = (int)db()->lastInsertId();
    $p2 = connect_kpi_board(['audience' => 'pro', 'party_id' => $proId2]);
    t_eq($p2['raw']['assignments_done'], 0, 'an empty pro shows zero assignments');
    t_eq($p2['raw']['booked_value'], 0.0, 'an empty pro shows zero booked value');
    t_ok($p2['raw']['rating_avg'] === null, 'an empty pro has no rating average');
    // The shared renderer handles the pro board too.
    ob_start(); connect_kpi_render($p); $phtml = ob_get_clean();
    t_ok(strpos($phtml, 'Booked value') !== false, 'the rendered pro board shows the Booked value tile');

    // --- the SAME engine at 'inspector' scope builds a field cockpit ----------
    // Jobs are scoped by the inspector's own inspector_id; ratings via the
    // inspector rating summary. No money figure appears (least-privilege).
    $insp = 90177; $yst = date('Y-m-d', strtotime('-1 day'));
    // open + report pending (has a report frequency, not yet uploaded)
    db()->prepare("INSERT INTO jobs (inspector_id, closed_flag, reporting_frequency, report_upload_date, created_at) VALUES (?,0,'PERIODIC','',?)")->execute([$insp, date('c')]);
    // open + overdue (end date in the past) + NOREPORT (so not a pending report)
    db()->prepare("INSERT INTO jobs (inspector_id, closed_flag, inspection_end_date, reporting_frequency, created_at) VALUES (?,0,?, 'NOREPORT', ?)")->execute([$insp, $yst, date('c')]);
    // completed
    db()->prepare("INSERT INTO jobs (inspector_id, closed_flag, created_at) VALUES (?,1,?)")->execute([$insp, date('c')]);
    // two client→inspector ratings: avg 4.5
    db()->prepare("INSERT INTO cx_ratings (ratee_inspector_id, direction, stars, created_at) VALUES (?, 'CLIENT_TO_PRO', 4, ?)")->execute([$insp, date('c')]);
    db()->prepare("INSERT INTO cx_ratings (ratee_inspector_id, direction, stars, created_at) VALUES (?, 'CLIENT_TO_PRO', 5, ?)")->execute([$insp, date('c')]);

    $ins = connect_kpi_board(['audience' => 'inspector', 'party_id' => $insp]);
    t_eq($ins['audience'], 'inspector', 'the inspector board knows its audience');
    t_eq($ins['raw']['active'], 2, 'inspector active jobs counts this inspector\'s open jobs only');
    t_eq($ins['raw']['completed'], 1, 'inspector completed counts this inspector\'s closed jobs');
    t_eq($ins['raw']['overdue'], 1, 'inspector overdue counts past-due open jobs');
    t_eq($ins['raw']['reports_pending'], 1, 'inspector reports-pending excludes NOREPORT jobs');
    t_eq($ins['raw']['rating_avg'], 4.5, 'inspector rating averages client-to-inspector ratings');
    t_eq(count($ins['tiles']), 5, 'the inspector board has five tiles');
    $ikeys = array_map(fn($t) => $t['key'], $ins['tiles']);
    foreach (['active', 'reports', 'overdue', 'ratings', 'completed'] as $k)
        t_ok(in_array($k, $ikeys, true), "the inspector board has the $k tile");
    // No money key is ever exposed to the inspector.
    t_ok(!isset($ins['raw']['billed']) && !isset($ins['raw']['booked_value']), 'the inspector board carries no money figure');
    ob_start(); connect_kpi_render($ins); $ihtml = ob_get_clean();
    t_ok(strpos($ihtml, 'Overdue') !== false, 'the rendered inspector board shows the Overdue tile');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
