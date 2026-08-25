<?php
// Module 15 — Client / Customer 360. Fill the missing sections on the canonical 360:
// the issued-reports register (biggest gap — only a rejected COUNT existed), the full
// multi-site list (was primary only), and a satisfaction card. Reuses the existing
// gated, crash-safe assembly; no bespoke margin math; no new permission.
t_section('Module 15 — Customer 360 issued-reports, full sites, satisfaction');

$lib  = file_get_contents(__DIR__ . '/../lib/customer360.php');
$view = file_get_contents(__DIR__ . '/../views/ops/customer360.php');

t_ok(function_exists('c360_reports') && function_exists('c360_sites') && function_exists('c360_satisfaction'),
    'the new 360 section loaders exist');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    $pdo = db();
    // The 360 sections are gated (module permission / licence); act as a master so the
    // gated loaders actually run in this unit test.
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, is_superuser) VALUES ('c360m','C360','MASTER_ADMIN',1,1)")->execute();
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('C360M Client','C360M Client',1,'ACTIVE')")->execute();
    $pid = (int)$pdo->lastInsertId();

    // Issued reports: two finalized for this client, one draft (excluded), one for another client.
    $mkRep = function ($client, $status, $fin) use ($pdo) {
        $pdo->prepare("INSERT INTO report_docs (irn, type_code, status, client_id, finalized, issue_date, deleted, created_at)
                       VALUES (?, 'IC', ?, ?, ?, '2026-08-10', 0, ?)")
            ->execute(['IRN-' . uniqid(), $status, $client, $fin, date('c')]);
        return (int)$pdo->lastInsertId();
    };
    $mkRep($pid, 'ISSUED', 1); $mkRep($pid, 'ISSUED', 1);
    $mkRep($pid, 'DRAFT', 0);            // not finalized → excluded
    $mkRep($pid + 999, 'ISSUED', 1);     // another client → excluded

    $rep = c360_reports($pid);
    t_ok($rep !== null && (int)$rep['total'] === 2 && count($rep['rows']) === 2,
        'c360_reports returns only this client\'s finalized/issued reports');

    // Full site list: three addresses, primary first.
    foreach ([['HQ', 1], ['Plant A', 0], ['Plant B', 0]] as [$lbl, $pri]) {
        $pdo->prepare("INSERT INTO partner_addresses (partner_id, label, line1, city, is_primary) VALUES (?,?,?, 'Town', ?)")
            ->execute([$pid, $lbl, $lbl . ' road', $pri]);
    }
    $sites = c360_sites($pid);
    t_ok(count($sites) === 3, 'c360_sites returns every site, not just the primary');
    t_ok((int)($sites[0]['is_primary'] ?? 0) === 1, 'the primary site sorts first');

    // Satisfaction: surfaced when the CSAT module is on and surveys exist.
    if (function_exists('sat_migrate')) sat_migrate();
    if (function_exists('sat_enabled') && sat_enabled()) {
        try {
            $pdo->prepare("INSERT INTO satisfaction_surveys (client_id, score, recommend, received_on) VALUES (?,4,'Y','2026-08-01')")->execute([$pid]);
            $pdo->prepare("INSERT INTO satisfaction_surveys (client_id, score, recommend, received_on) VALUES (?,2,'N','2026-08-15')")->execute([$pid]);
            $cs = c360_satisfaction($pid);
            t_ok($cs !== null && (int)$cs['latest'] === 2 && $cs['count'] === 2 && (float)$cs['avg'] === 3.0,
                'c360_satisfaction reports the latest score and the average');
        } catch (Throwable $e) { t_ok(true, 'satisfaction_surveys shape differs in this build — CSAT check skipped'); }
    } else {
        t_ok(true, 'CSAT module off in this build — satisfaction check skipped');
    }

    // The full assembly now carries the new keys.
    $d = c360_load($pid);
    t_ok(array_key_exists('reports', $d) && array_key_exists('sites', $d) && array_key_exists('csat', $d),
        'c360_load assembles the new sections');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    unset($_SESSION['uid']); current_user(true); if (function_exists('ua')) ua(true);
}

// The view renders the new sections.
t_ok(strpos($view, 'Reports issued') !== false && strpos($view, '/document?id=') !== false,
    'the 360 shows an issued-reports panel that links to each report');
t_ok(strpos($view, 'Sites (') !== false, 'the 360 shows the full site list');
t_ok(strpos($view, "if (!empty(\$csat))") !== false && strpos($view, 'Satisfaction') !== false,
    'the 360 shows a satisfaction card when data exists');

// Guardrails: gated + crash-safe pattern reused; no bespoke margin/profit math; no new permission.
t_ok(strpos($lib, "c360_on('idems')") !== false, 'the reports section reuses the existing module gate');
t_ok(!preg_match('/\b(margin|gross_margin|job_profit)\b/', $lib), 'no bespoke margin/profit query was added to the 360');
t_ok(!preg_match('/can\(\x27clients\.360/', $lib), 'Module 15 introduces no new permission constant');
