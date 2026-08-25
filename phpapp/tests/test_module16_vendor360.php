<?php
// Module 16 — Vendor 360. A consolidated scorecard assembled from the signals that
// ALREADY exist (performance, delivery risk, expediting, qualification, open quality
// items) — no new scoring math — plus the missing CAPA section (CAPAs linked via the
// vendor's NCRs/complaints). Reuses the engines; no schema change; no new permission.
t_section('Module 16 — vendor scorecard (reused engines) + CAPA section');

$lib  = file_get_contents(__DIR__ . '/../lib/idems.php');
$view = file_get_contents(__DIR__ . '/../views/ops/idems/vendor_detail.php');

t_ok(function_exists('idems_vendor_scorecard') && function_exists('idems_vendor_capas'),
    'the scorecard + vendor-CAPA helpers exist');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    if (function_exists('ncr_migrate')) ncr_migrate();
    if (function_exists('capa_migrate')) capa_migrate();
    $pdo = db();
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_vendor, status) VALUES ('V360 Vendor','V360 Vendor',1,'ACTIVE')")->execute();
    $vid = (int)$pdo->lastInsertId();

    // An open NCR and a linked CAPA; an open complaint.
    $pdo->prepare("INSERT INTO nonconformities (ref, source, partner_id, severity, status, title, created_at) VALUES ('NCR-V1','JOB',?, 'MAJOR','OPEN','Weld defect', ?)")->execute([$vid, date('c')]);
    $ncrId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO capa (ref, source, ncr_id, title, severity, status, created_at) VALUES ('CAPA-V1','NCR',?, 'Fix welding QC','MAJOR','OPEN', ?)")->execute([$ncrId, date('c')]);
    $pdo->prepare("INSERT INTO complaints (ref, source, partner_id, subject, status, created_at) VALUES ('CMP-V1','VENDOR',?, 'Late delivery','OPEN', ?)")->execute([$vid, date('c')]);

    // Scorecard assembles the domains without inventing a new number.
    $sc = idems_vendor_scorecard($vid);
    t_ok($sc !== null && array_key_exists('performance', $sc) && array_key_exists('delivery', $sc)
        && array_key_exists('expediting', $sc) && array_key_exists('qualification', $sc) && array_key_exists('quality_open', $sc),
        'the scorecard carries the five domains');
    t_ok((int)$sc['quality_open']['ncr_open'] === 1 && (int)$sc['quality_open']['complaints_open'] === 1,
        'open quality items come straight from the existing counts');
    // No new score: the headline equals the existing performance engine's score.
    $perf = idems_vendor_performance($vid);
    t_ok(($sc['performance']['score'] ?? null) === ($perf['score'] ?? null),
        'the headline score IS the existing performance score (no new formula)');

    // Vendor CAPAs: the CAPA linked via the vendor's NCR is returned; de-duplicated.
    $capas = idems_vendor_capas($vid);
    t_ok(count($capas) === 1 && $capas[0]['ref'] === 'CAPA-V1', 'the vendor\'s linked CAPA is surfaced');

    // A different vendor with nothing → empty CAPA list, scorecard still assembles.
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_vendor, status) VALUES ('V360 Clean','V360 Clean',1,'ACTIVE')")->execute();
    $vid2 = (int)$pdo->lastInsertId();
    t_ok(idems_vendor_capas($vid2) === [], 'a vendor with no corrective actions returns an empty list');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The view renders the scorecard card and the CAPA section.
t_ok(strpos($view, '$scorecard') !== false && strpos($view, 'Scorecard') !== false,
    'the Vendor 360 renders the consolidated scorecard card');
t_ok(strpos($view, 'Corrective actions') !== false && strpos($view, '/capa-item?id=') !== false,
    'the Vendor 360 renders a corrective-actions (CAPA) section');

// Guardrails: no new scoring formula; the existing engines are only READ; no new permission.
t_ok(strpos($lib, 'function idems_vendor_scorecard') !== false
  && strpos($lib, "idems_vendor_performance', \$partnerId") !== false,
    'the scorecard reuses idems_vendor_performance rather than recomputing a score');
t_ok(!preg_match('/can\(\x27vendors\.(score|scorecard)/', $lib), 'Module 16 introduces no new permission constant');
