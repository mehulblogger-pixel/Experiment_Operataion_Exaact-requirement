<?php
// ============================================================================
//  UVAAE (Phase 5) — Universal Vendor Audit / MS-Audit Engine — smoke + logic.
//  Boots the real app on a throwaway SQLite db, then exercises:
//    1. Migration: masters, criteria library, packs, conclusion rules seeded.
//    2. Dynamic criteria assembly by audit type (the §169 architectural test).
//    3. Checklist prefill from applicable criteria.
//    4. Audit conclusion engine (a Major NC cannot end in "Conforms").
//    5. Findings roll-up + findings→NCR raising (Major/Minor become NCRs).
//    6. Advisory QA (item 16) flags contradictions, changes nothing.
//    7. Every sample audit type builds and its PDF renders.
// ============================================================================
// Works standalone (`php tests/test_uvaae.php`) and under the runner
// (`php tests/run.php`), which boots once and requires every test file.
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }

if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

// ---------------------------------------------------------------------------
head('1. Migration — masters, criteria library, packs, conclusion rules');
ok(count(uvaae_audit_types()) >= 25, 'audit_type master seeded (' . count(uvaae_audit_types()) . ' types)');
$critN = (int)ops_val("SELECT COUNT(*) FROM audit_criteria");
ok($critN === count(UVAAE_CRITERIA), "audit_criteria seeded ($critN rows)");
$packN = (int)ops_val("SELECT COUNT(*) FROM audit_criteria_packs");
ok($packN === 7, "audit_criteria_packs seeded ($packN packs)");
$ruleN = (int)ops_val("SELECT COUNT(*) FROM audit_conclusion_rules");
ok($ruleN === count(UVAAE_CONCLUSION_RULES), "audit_conclusion_rules seeded ($ruleN rules)");
// Re-running migrate must NOT duplicate anything (idempotent seed).
uvaae_seed_criteria(); uvaae_seed_conclusion_rules();
ok((int)ops_val("SELECT COUNT(*) FROM audit_criteria") === $critN, 'criteria seed is idempotent');

// ---------------------------------------------------------------------------
head('2. Dynamic criteria assembly — the architectural test (§169)');
$general = uvaae_criteria('');                     // no type → universal core only
$qms     = uvaae_criteria('QMS');
$iso     = uvaae_criteria('ISO17020');
$proc    = uvaae_criteria('PROCESS');
$hse     = uvaae_criteria('HSE');
$special = uvaae_criteria('SPECIAL_PROCESS');
$codes = fn($set) => array_map(fn($c) => $c['code'], $set);
ok(count($general) === 10, 'blank audit type → 10 universal-core criteria only (' . count($general) . ')');
ok(in_array('Q9-01', $codes($qms)) && !in_array('Q9-01', $codes($iso)), 'QMS audit pulls QMS pack; ISO17020 audit does not');
ok(in_array('I20-01', $codes($iso)) && !in_array('I20-01', $codes($qms)), 'ISO17020 audit pulls inspection-body pack; QMS audit does not');
ok(in_array('PR-01', $codes($proc)) && !in_array('PR-01', $codes($hse)), 'Process audit pulls process pack; HSE audit does not');
ok(in_array('HSE-02', $codes($hse)) && !in_array('HSE-02', $codes($proc)), 'HSE audit pulls HSE pack; process audit does not');
ok(in_array('SP-01', $codes($special)), 'Special-process audit pulls special-process pack');
// Universal core is present in EVERY audit type.
ok(in_array('MS-01', $codes($qms)) && in_array('MS-01', $codes($iso)) && in_array('MS-01', $codes($hse)), 'universal core present in every audit type');
// Extra pack can be layered explicitly.
$layered = uvaae_criteria('QMS', null, null, ['SUPPLIER_QUALITY']);
ok(in_array('SQ-01', $codes($layered)), 'explicit extra pack (SUPPLIER_QUALITY) layers onto a QMS audit');

// ---------------------------------------------------------------------------
head('3. Checklist prefill from applicable criteria');
$clField = [
    'ftype' => 'table', 'fkey' => 'audit_checklist',
    'table_cols' => "Criterion|merge\nCategory\nRequirement\nQuestion\nResult|select|Conforms,Minor NC,Major NC\nObjective evidence\nEvidence status\nRemarks",
];
$rows = uvaae_checklist_prefill_rows($clField, 'PROCESS');
ok(count($rows) === count($proc), 'process checklist prefilled with the process criteria set (' . count($rows) . ')');
$first = $rows[0] ?? [];
ok(in_array('MS-01', array_map('strval', $first)) || in_array('MS-01', array_values($first)), 'a criterion code is written into the first prefilled row');

// ---------------------------------------------------------------------------
head('4. Audit conclusion engine — controlled consequence of findings');
$c1 = uvaae_conclusion_decision(['major_nc'=>0,'minor_nc'=>0,'score'=>95]);
ok($c1['conclusion'] === 'CONFORMS', 'clean audit → CONFORMS');
$c2 = uvaae_conclusion_decision(['major_nc'=>1,'minor_nc'=>0]);
ok($c2['conclusion'] === 'MAJOR_ACTIONS', 'one Major NC → MAJOR_ACTIONS (cannot be "conforms")');
$c3 = uvaae_conclusion_decision(['major_nc'=>0,'minor_nc'=>1]);
ok($c3['conclusion'] === 'MINOR_ACTIONS', 'one Minor NC → MINOR_ACTIONS');
$c4 = uvaae_conclusion_decision(['major_nc'=>0,'minor_nc'=>5]);
ok($c4['conclusion'] === 'CONDITIONAL', 'many Minor NCs (>3) → CONDITIONAL');
$c5 = uvaae_conclusion_decision(['major_nc'=>0,'minor_nc'=>0,'critical_failed'=>1]);
ok($c5['conclusion'] === 'NOT_CONFORMS', 'a critical criterion failing → NOT_CONFORMS (worst wins)');
$c6 = uvaae_conclusion_decision(['major_nc'=>0,'minor_nc'=>0,'score'=>55]);
ok($c6['conclusion'] === 'CONDITIONAL', 'conformance score below threshold → CONDITIONAL');

// ---------------------------------------------------------------------------
head('5. Findings roll-up + findings → NCR raising');
$findField = [
    'ftype'=>'table','fkey'=>'audit_findings',
    'table_cols'=>"Sr. No.|merge\nClause / criteria\nFinding / observation\nGrade|select|Major nonconformity,Minor nonconformity,Observation\nObjective evidence\nAttached|select|Yes,No\nCorrective action required\nResponsible\nTarget date|date",
];
$defs = idems_table_col_defs($findField); $keys = array_keys($defs);
$mkRow = function($clause,$finding,$grade,$ev,$act) use($keys){ $r=array_fill_keys($keys,''); $i=0;
    $r[$keys[1]]=$clause; $r[$keys[2]]=$finding; $r[$keys[3]]=$grade; $r[$keys[4]]=$ev; $r[$keys[6]]=$act; return $r; };
$data = ['audit_findings'=>[
    $mkRow('MS-04','Corrective actions not tracked to closure','Major nonconformity','2 open NCRs > 90 days','Implement CAPA tracking'),
    $mkRow('MS-02','One obsolete drawing found at workstation','Minor nonconformity','Drg rev B in use, rev C current','Withdraw obsolete copy'),
    $mkRow('MS-08','Good use of trend charts','Observation','Noted on shop floor',''),
]];
$roll = uvaae_findings_rollup([$findField], $data);
ok($roll && $roll['major']===1 && $roll['minor']===1 && $roll['observation']===1, 'findings roll-up counts 1 Major / 1 Minor / 1 Observation');

// End-to-end: a real audit report doc, issued, raising NCRs from its findings.
$typeId = (int)ops_val("SELECT id FROM report_types WHERE code='UAUD_QMS'");
ok($typeId > 0, 'sample audit type UAUD_QMS exists');
$ncrBefore = (int)ops_val("SELECT COUNT(*) FROM nonconformities");
$pdo = db();
$pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,title,status,data,vendor_id,office_id,created_at)
    VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$typeId,'UAUD_QMS','UAUD-TEST-001','Test QMS audit','ISSUED', json_encode($data), 0, 0, date('c')]);
$docId = (int)$pdo->lastInsertId();
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$docId]);
$raised = idems_raise_ncrs_from_audit($doc);
ok($raised === 2, "findings→NCR raised exactly 2 NCRs (Major+Minor, not the Observation) — got $raised");
$ncrAfter = (int)ops_val("SELECT COUNT(*) FROM nonconformities");
ok($ncrAfter - $ncrBefore === 2, 'NCR register grew by 2');
// Idempotent — a second call must not double-raise.
ok(idems_raise_ncrs_from_audit($doc) === 0, 'findings→NCR is idempotent (no double-raise)');

// ---------------------------------------------------------------------------
head('6. Advisory QA (item 16) flags contradictions, changes nothing');
// A conclusion of "Conforms" while a Major NC is on the findings table.
$doc2 = $doc; $d2 = $data; $d2['audit_conclusion'] = 'CONFORMS';
$doc2['data'] = json_encode($d2);
$fields2 = idems_fields($typeId);
$qa = uvaae_qa_checks($doc2, $fields2, $d2);
$titles = array_map(fn($q)=>$q['title'], $qa);
$hasContradiction = false; foreach ($titles as $t) if (stripos($t,'conforms')!==false && stripos($t,'Major')!==false) $hasContradiction = true;
ok($hasContradiction, 'QA flags "conforms" conclusion despite a Major NC');
// The data is untouched by QA (advisory only).
ok($d2['audit_conclusion'] === 'CONFORMS' && count($d2['audit_findings']) === 3, 'QA changed no finding, grade or conclusion');
// Self-gate: QA is inert on a non-audit report.
ok(uvaae_qa_checks(['report_type_id'=>0], [], []) === [], 'QA self-gates (inert on a non-audit report)');
// The overall QA runner includes the UVAAE checks without error.
$run = idems_qa_run($doc2, $fields2, $d2);
ok(isset($run['status']) && in_array($run['status'],['BLOCKED','REVIEW','READY'],true), 'idems_qa_run integrates UVAAE checks (status=' . $run['status'] . ')');

// ---------------------------------------------------------------------------
head('7. Every sample audit type builds and renders a PDF');
$samples = array_map(fn($s)=>$s[0], UVAAE_SAMPLE_TYPES);
ok(count($samples) >= 10, 'at least 10 sample audit configs from ONE engine (' . count($samples) . ')');
$rendered = 0;
foreach ($samples as $code) {
    $tid = (int)ops_val("SELECT id FROM report_types WHERE code=?", [$code]);
    if (!$tid) { echo "  FAIL sample $code did not build\n"; $fail++; continue; }
    $secN = (int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=?", [$tid]);
    if ($secN < 3) { echo "  FAIL sample $code assembled with too few sections ($secN)\n"; $fail++; continue; }
    // Build a minimal doc and render its PDF.
    $pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,title,status,data,vendor_id,office_id,created_at)
        VALUES (?,?,?,?,?,?,?,?,?)")->execute([$tid,$code,'REND-'.$code,'Render '.$code,'DRAFT', json_encode($data),0,0,date('c')]);
    $rid = (int)$pdo->lastInsertId();
    $rdoc = ops_one("SELECT * FROM report_docs WHERE id=?", [$rid]);
    try {
        $bytes = report_pdf_build($rdoc, idems_sections($tid), idems_fields($tid), $data, [], [], []);
        if (is_string($bytes) && strlen($bytes) > 800 && strncmp($bytes,'%PDF',4)===0) { $rendered++; }
        else { echo "  FAIL sample $code rendered too-small / invalid PDF (" . (is_string($bytes)?strlen($bytes):'non-string') . ")\n"; $fail++; }
    } catch (Throwable $e) { echo "  FAIL sample $code PDF render threw: " . $e->getMessage() . "\n"; $fail++; }
}
ok($rendered === count($samples), "all $rendered/" . count($samples) . ' sample audit PDFs rendered');

// ---------------------------------------------------------------------------
if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== UVAAE: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
