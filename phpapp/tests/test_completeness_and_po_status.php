<?php
// Two fixes after decluttering the report header:
//  1. Location and Applicable specification no longer BLOCK the completeness gate
//     when they are not captured (they carry from the call, or are held in the
//     reference documents) — they PASS when a value flows in, else N/A.
//  2. The P.O. status dropdown always has its shipped options even when the
//     editable master was never seeded.
t_section('completeness gate + P.O. status options after the header declutter');

$pdo = db();
if (function_exists('idems_migrate')) { try { idems_migrate(); } catch (Throwable $e) {} }

// --- 1. completeness: location / spec are N/A (not FAIL) when absent ----------
$typeId = idems_build_fire_extinguisher_report();
$pdo->prepare("INSERT INTO report_docs (report_type_id, type_code, irn, status, data, created_at) VALUES (?,?,?,?,?,?)")
    ->execute([$typeId, 'FEXT', 'CMP-1', 'DRAFT', '[]', date('c')]);
$doc = ops_one("SELECT * FROM report_docs WHERE irn='CMP-1'");
$c = idems_completeness_check($doc);
$byKey = [];
foreach ($c['checks'] as $chk) $byKey[$chk['key']] = $chk['status'];
t_ok(($byKey['location'] ?? '') === 'NA', 'an uncaptured inspection location is N/A, not a failure');
t_ok(($byKey['spec'] ?? '') === 'NA', 'an uncaptured applicable specification is N/A, not a failure');
// N/A checks are excluded from the pass/fail tally.
t_ok($c['failed'] === count(array_filter($c['checks'], fn($x) => $x['status'] === 'FAIL')),
    'the failed count only counts real failures');

// When the values DO flow in (report header carries them), the checks PASS.
$pdo->prepare("UPDATE report_docs SET location=?, standards=? WHERE id=?")
    ->execute(['Plot 5, Ahmedabad', 'ASME Sec VIII Div 1', (int)$doc['id']]);
$doc2 = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$doc['id']]);
$c2 = idems_completeness_check($doc2);
$byKey2 = [];
foreach ($c2['checks'] as $chk) $byKey2[$chk['key']] = $chk['status'];
t_ok(($byKey2['location'] ?? '') === 'PASS', 'a carried location passes the check');
t_ok(($byKey2['spec'] ?? '') === 'PASS', 'a carried applicable specification passes the check');

// --- 2. P.O. status options fall back to the shipped constant -----------------
$opts = idems_field_options(['ftype' => 'select', 'options' => 'lookup:po_status']);
t_ok(!empty($opts), 'the P.O. status dropdown has options even without a seeded master');
t_ok(isset($opts['Completed']) && isset($opts['Balance']) && isset($opts['Hold']),
    'the shipped P.O. status values (Completed / Balance / Hold) are present');
