<?php
// Phase 2 §4 — the report role model. On screen all four roles (Prepared/Vetted/Approved/Issued)
// showed, but the Prepared role had no timestamp and the printed PDF signed off only two roles
// (Inspected by / Approved by). Now Prepared carries a timestamp, and the signature payload +
// PDF sign-off include Vetted-by and Issued-by (rendered only when those roles apply).
t_section('Phase 2 §4 — all four report roles are captured + printed');

t_ok(function_exists('idems_provenance') && function_exists('idems_report_signatures'), 'provenance + signature builders exist');

// Prepared now carries a timestamp (submitted, else created).
$doc = ['status' => 'ISSUED', 'inspector_id' => 0, 'inspector_name' => 'Ravi', 'submitted_at' => '2026-08-02T10:00:00',
        'created_at' => '2026-08-01T09:00:00', 'vet_status' => 'VETTED', 'vet_by' => 'QA Manager', 'vet_at' => '2026-08-03T11:00:00',
        'approved_by' => 'Branch Head', 'approved_at' => '2026-08-04T12:00:00',
        'finalized' => 1, 'finalized_by' => 'Issuer Person', 'finalized_at' => '2026-08-05T13:00:00', 'id' => 0];
$prov = idems_provenance($doc);
$byRole = []; foreach ($prov as $r) $byRole[$r['role']] = $r;
t_ok(isset($byRole['Prepared']) && $byRole['Prepared']['at'] === '2026-08-02T10:00:00', 'the Prepared role now carries a timestamp (submitted_at)');
$doc2 = $doc; unset($doc2['submitted_at']);
$prov2 = idems_provenance($doc2); $prep2 = null; foreach ($prov2 as $r) if ($r['role'] === 'Prepared') $prep2 = $r;
t_ok($prep2['at'] === '2026-08-01T09:00:00', 'with no submitted_at it falls back to created_at');

// The signature payload now carries vetter + issuer (when those roles happened).
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    $sigs = idems_report_signatures($doc);
    t_ok(array_key_exists('vetter', $sigs) && array_key_exists('issuer', $sigs), 'the signature payload has vetter + issuer keys');
    t_ok(!empty($sigs['vetter']) && $sigs['vetter']['name'] === 'QA Manager', 'the Vetted-by block names the vetter');
    t_ok(strpos((string)$sigs['vetter']['time'], 'Vetted:') === 0, 'the Vetted-by block carries the vetting time');
    t_ok(!empty($sigs['issuer']) && $sigs['issuer']['name'] === 'Issuer Person', 'the Issued-by block names the issuer');
    t_ok(strpos((string)$sigs['issuer']['time'], 'Issued:') === 0, 'the Issued-by block carries the issue time');

    // An un-vetted, un-issued draft produces NO vetter/issuer blocks (no empty sign-offs on a draft).
    $draft = ['status' => 'DRAFT', 'inspector_id' => 0, 'vet_status' => '', 'finalized' => 0, 'id' => 0];
    $ds = idems_report_signatures($draft);
    t_ok(empty($ds['vetter']) && empty($ds['issuer']), 'a draft has no Vetted-by / Issued-by blocks');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The PDF renders the second sign-off row conditionally.
$idm = file_get_contents(__DIR__ . '/../lib/idems.php');
t_ok(strpos($idm, "\$drawSig(\$ml, \$sy2, 'Vetted by'") !== false, 'the PDF draws a Vetted-by sign-off block');
t_ok(strpos($idm, "'Issued by', \$sigs['issuer']") !== false, 'the PDF draws an Issued-by sign-off block');
t_ok(strpos($idm, "!empty(\$sigs['vetter']) || !empty(\$sigs['issuer'])") !== false, 'the second sign-off row renders only when those roles apply');
