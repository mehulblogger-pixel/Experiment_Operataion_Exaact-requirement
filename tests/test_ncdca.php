<?php
// ============================================================================
//  NCDCA (Phase 8) — Universal Issue / NCR / CAPA / Deviation / Concession.
//  Boots the real app on a throwaway SQLite db and verifies the ELEVATION +
//  GAP-FILL over the existing NCR/CAPA spine (nothing rebuilt):
//    1. Migration: masters + additive columns on the EXISTING nonconformities /
//       capa; new departure/dispute/extension tables.
//    2. Issue-type dimension — an NCR is one type; an observation is another
//       (the §96 distinction), carried by ncr_create (§48 standalone).
//    3. Controlled departures — deviation / concession / waiver with their own
//       approval workflow & validity (NOT an NCR closure).
//    4. Dispute + response — original finding retained.
//    5. Due-date extension — original date never overwritten.
//    6. Recurrence — possible repeats suggested, never auto-labelled.
//    7. Issue report types build & render via the template builder.
//    8. Advisory QA (item 18) flags, never invents/approves/closes.
//    9. Independence — works with every other service off.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

$pdo = db();

// ---------------------------------------------------------------------------
head('1. Migration — elevate existing NCR/CAPA + gap tables (nothing rebuilt)');
ok(count(ncdca_issue_types()) >= 18, 'issue_type master seeded (' . count(ncdca_issue_types()) . ' types incl. NCR as one of them)');
$ncCols = array_map(fn($r)=>$r['name'], ops_all("PRAGMA table_info(nonconformities)"));
ok(in_array('issue_type', $ncCols, true) && in_array('visibility', $ncCols, true), 'existing nonconformities gained issue_type + visibility (elevated, not rebuilt)');
$capaCols = array_map(fn($r)=>$r['name'], ops_all("PRAGMA table_info(capa)"));
ok(in_array('eff_result', $capaCols, true), 'existing capa gained a richer effectiveness result');
foreach (['issue_departures','issue_disputes','issue_extensions'] as $t)
    ok((int)ops_val("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?", [$t]) === 1, "gap table $t created");

// ---------------------------------------------------------------------------
head('2. Issue-type dimension — NCR is one type, observation is another (§96)');
$ncrId = ncr_create(['title'=>'Weld undercut beyond limit','severity'=>'MAJOR','source'=>'INTERNAL',
    'issue_type'=>'NCR','issue_class'=>'MAJOR_NC','responsibility'=>'VENDOR','partner_id'=>701,'clause'=>'7.4']);
ok($ncrId > 0, 'a plain NCR is created through the existing universal creator');
$ncr = ncr_row($ncrId);
ok($ncr['issue_type'] === 'NCR' && $ncr['issue_class'] === 'MAJOR_NC' && $ncr['responsibility'] === 'VENDOR', 'the NCR carries type / classification / responsibility');
$obsId = ncr_create(['title'=>'Housekeeping could improve','severity'=>'OBSERVATION','source'=>'INTERNAL','issue_type'=>'OBSERVATION','issue_class'=>'OBSERVATION']);
ok(ncr_row($obsId)['issue_type'] === 'OBSERVATION', 'an observation is recorded as an OBSERVATION, not forced to be an NCR');
// Backward compatible: a caller that passes no type still gets a plain NCR.
$plainId = ncr_create(['title'=>'Legacy call','severity'=>'MINOR','source'=>'INTERNAL']);
ok(ncr_row($plainId)['issue_type'] === 'NCR', 'a caller that passes no type still gets a plain NCR (backward compatible)');
// Reclassify later (an authorised human action).
ncdca_classify($plainId, ['issue_type'=>'DOC_DISCREPANCY']);
ok(ncr_row($plainId)['issue_type'] === 'DOC_DISCREPANCY', 'an issue can be reclassified afterwards');

// ---------------------------------------------------------------------------
head('3. Controlled departures — deviation / concession / waiver (NOT NCR closure)');
$devId = ncdca_departure_create('DEVIATION', ['ncr_id'=>$ncrId,'client_id'=>501,'title'=>'Coating thickness','requirement'=>'DFT ≥ 250µm',
    'planned_condition'=>'250µm','actual_condition'=>'230µm','reason'=>'Applicator limit','valid_to'=>'2026-12-31']);
ok($devId > 0, 'a deviation is created with its own record');
$dev = ncdca_departure($devId);
ok(strpos((string)$dev['ref'], 'DEV-') === 0 && $dev['status'] === 'DRAFT', 'deviation has its own DEV- reference and starts DRAFT');
$conId = ncdca_departure_create('CONCESSION', ['client_id'=>501,'title'=>'Accept as-is','requirement'=>'Dim tol ±0.5']);
ok(strpos((string)ncdca_departure($conId)['ref'], 'CON-') === 0, 'a concession has its own CON- reference (separate register)');
$wavId = ncdca_departure_create('WAIVER', ['client_id'=>501,'title'=>'Hydro test waiver']);
ok(strpos((string)ncdca_departure($wavId)['ref'], 'WAV-') === 0, 'a waiver has its own WAV- reference');
// Approval workflow (§37 — never auto-accepted).
ncdca_departure_set_status($devId, 'SUBMITTED');
ncdca_departure_set_status($devId, 'APPROVED', 'Tech Authority', true);
$dev = ncdca_departure($devId);
ok($dev['status'] === 'APPROVED' && $dev['approver'] === 'Tech Authority' && (int)$dev['client_approval'] === 1 && $dev['approved_on'] !== '', 'a departure is approved by a named authority with client approval + date');
ok(count(ncdca_departures('DEVIATION')) >= 1 && count(ncdca_departures('CONCESSION')) >= 1, 'departures filter by kind (separate registers)');
// Expiry sweep (deterministic; never deletes).
$pdo->prepare("UPDATE issue_departures SET valid_to='2020-01-01' WHERE id=?")->execute([$devId]);
ok(ncdca_expire_departures('2026-08-15') >= 1 && ncdca_departure($devId)['status'] === 'EXPIRED', 'an out-of-validity approved departure expires (not deleted)');

// ---------------------------------------------------------------------------
head('4. Dispute + response — original finding retained (§53/§54)');
$dispId = ncdca_dispute_create($ncrId, ['party'=>'VENDOR','disputed_field'=>'severity','reason'=>'We believe it is minor','response_kind'=>'DISAGREE']);
ok($dispId > 0, 'a vendor can dispute a finding');
ok(ncr_row($ncrId)['severity'] === 'MAJOR', 'the original finding is unchanged by the dispute (retained)');
ncdca_dispute_decide($dispId, 'UPHELD', 'Objective evidence supports major');
ok(ncdca_disputes($ncrId)[0]['status'] === 'UPHELD', 'the dispute is resolved with a decision, keeping the record');

// ---------------------------------------------------------------------------
head('5. Due-date extension — original date preserved (§32)');
$origDue = ncr_row($ncrId)['due_on'];
$extId = ncdca_extension_request('NCR', $ncrId, $origDue, '2026-10-31', 'Awaiting material');
ncdca_extension_approve($extId, 'QA Manager');
$ext = ncdca_extensions('NCR', $ncrId)[0];
ok($ext['original_due'] === $origDue && $ext['new_due'] === '2026-10-31' && $ext['status'] === 'APPROVED', 'the extension keeps the ORIGINAL due date and records the new one');
ok(ncr_row($ncrId)['due_on'] === '2026-10-31', 'the live record moves to the new due date only after approval');

// ---------------------------------------------------------------------------
head('6. Recurrence — possible repeats suggested, never auto-labelled (§57/§58)');
$rep = ncr_create(['title'=>'Weld undercut again','severity'=>'MAJOR','source'=>'INTERNAL','partner_id'=>701,'clause'=>'7.4','issue_type'=>'NCR']);
$repeats = ncdca_possible_repeats(ncr_row($rep));
ok(count($repeats) >= 1, 'a same-vendor same-clause issue surfaces as a possible repeat');
$found = false; foreach ($repeats as $r) if ((int)$r['id'] === $ncrId) $found = true;
ok($found, 'the earlier NCR is offered as the possible repeat (human confirms; nothing auto-labelled)');

// ---------------------------------------------------------------------------
head('7. Issue report types build & render via the template builder');
$samples = array_map(fn($s)=>$s[0], NCDCA_SAMPLE_TYPES);
ok(count($samples) === 5, 'five issue report types (NCR / CAPA / Deviation / Concession / Closure)');
$rd = ['iss_type'=>'NCR','iss_requirement'=>'DFT ≥ 250µm','iss_observed'=>'230µm','iss_gap'=>'20µm short','iss_statement'=>'Coating below spec',
       'iss_evidence'=>[['Type'=>'Measurement','Description'=>'DFT gauge','Reference'=>'R1']]];
$rendered = 0;
foreach ($samples as $code) {
    $tid = (int)ops_val("SELECT id FROM report_types WHERE code=?", [$code]);
    if (!$tid) { echo "  FAIL $code did not build\n"; continue; }
    $pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,title,status,data,created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([$tid,$code,'REND-'.$code,'R '.$code,'DRAFT',json_encode($rd),date('c')]);
    $rid = (int)$pdo->lastInsertId(); $rdoc = ops_one("SELECT * FROM report_docs WHERE id=?", [$rid]);
    try { $bytes = report_pdf_build($rdoc, idems_sections($tid), idems_fields($tid), $rd, [], [], []);
        if (is_string($bytes) && strncmp($bytes,'%PDF',4)===0 && strlen($bytes)>800) $rendered++; } catch (Throwable $e) { echo "  FAIL $code: ".$e->getMessage()."\n"; }
}
ok($rendered === 5, "all $rendered/5 issue report PDFs rendered");

// ---------------------------------------------------------------------------
head('8. Advisory QA (item 18) flags, never invents / approves / closes');
$tid = (int)ops_val("SELECT id FROM report_types WHERE code='NCDCA_NCR'");
$flds = idems_fields($tid);
// A finding statement with no requirement / observed / evidence → should flag.
$bad = ['iss_statement'=>'Something is wrong','iss_class'=>'MAJOR_NC','iss_closure'=>'Closed','iss_evidence'=>[]];
$qa = ncdca_qa_checks(['report_type_id'=>$tid], $flds, $bad);
$titles = implode(' | ', array_map(fn($q)=>$q['title'], $qa));
ok(stripos($titles,'missing part of its structure')!==false, 'QA flags a finding missing its structure');
ok(stripos($titles,'without recording effectiveness')!==false, 'QA flags closing a major NC without effectiveness');
ok($bad['iss_evidence'] === [] && $bad['iss_statement'] === 'Something is wrong', 'QA invents nothing — the record is untouched');
ok(ncdca_qa_checks(['report_type_id'=>0], [], []) === [], 'QA self-gates (inert on a non-issue report)');
$run = idems_qa_run(['report_type_id'=>$tid,'data'=>json_encode($bad)], $flds, $bad);
ok(in_array($run['status'],['BLOCKED','REVIEW','READY'],true), 'idems_qa_run integrates NCDCA checks (status=' . $run['status'] . ')');

// ---------------------------------------------------------------------------
head('9. Independence — works with every other service off');
if (function_exists('svc_set_global')) {
    foreach (['INSPECTION','VENDOR_ASSESSMENT','VENDOR_AUDIT','EXPEDITING','RELEASE','DEPUTATION'] as $c) svc_set_global($c, false);
    ok(ncdca_enabled(), 'the NCR/CAPA service stays enabled with every other service off');
    $sid = ncr_create(['title'=>'Standalone supplier issue','severity'=>'MINOR','source'=>'CLIENT','issue_type'=>'NCR']);
    ok($sid > 0 && ncdca_departure_create('CONCESSION', ['client_id'=>501,'title'=>'x']) > 0, 'a standalone issue + concession can be raised with no other service active');
    foreach (['INSPECTION','VENDOR_ASSESSMENT','VENDOR_AUDIT','EXPEDITING','RELEASE','DEPUTATION'] as $c) svc_set_global($c, true);
} else { ok(true, 'service scope engine not present — independence trivially holds'); }

// ---------------------------------------------------------------------------
head('10. Surfaces — issue dashboard/register, departures register, issue panel');
$stats = ncdca_issue_stats();
ok($stats['total'] >= 3 && $stats['deviation'] >= 0, 'issue stats aggregate over the existing NCR register + departures');
ok(count(ncdca_issues('NCR','all','')) >= 1, 'the unified register filters by issue type (NCR)');
ok(count(ncdca_issues('OBSERVATION','all','')) >= 1, 'the unified register shows observations as a distinct type');
$bd = ncdca_type_breakdown();
ok(isset($bd['NCR']) && isset($bd['OBSERVATION']), 'the type breakdown counts each type separately');
// Render the three surfaces.
$rows = ncdca_issues('','all',''); $breakdown = $bd; $types = ncdca_issue_types(); $type=''; $status='all'; $q=''; $canEdit=true;
ob_start(); require __DIR__ . '/../views/ops/issues.php'; $hv = ob_get_clean();
ok(strpos($hv,'kpi-row')!==false && strpos($hv,'/ncr-item?id=')!==false, 'issue dashboard/register renders and links to the existing NCR detail');
$kind=''; $rows=ncdca_departures(null); $kinds=NCDCA_DEPARTURE_KINDS; $statuses=ncdca_departure_statuses(); $clients=[]; $canEdit=true;
ob_start(); require __DIR__ . '/../views/ops/departures.php'; $hd = ob_get_clean();
ok(strpos($hd,'/departure-new')!==false, 'departures register renders with the raise form');
$n = ncr_row($ncrId); $closed=false; $issue=ncdca_issue_panel($ncrId);
$issueTypes=ncdca_issue_types(); $issueClasses=ncdca_classes(); $issueResp=ncdca_responsibilities(); $issueVis=ncdca_visibilities();
$depKinds=NCDCA_DEPARTURE_KINDS; $depStatuses=ncdca_departure_statuses(); $disputeStatus=NCDCA_DISPUTE_STATUS; $issueCanEdit=true;
ob_start(); require __DIR__ . '/../views/ops/_issue_panel.php'; $hp = ob_get_clean();
ok(strpos($hp,'/issue-classify')!==false && strpos($hp,'/dispute-new')!==false && strpos($hp,'id="issue"')!==false, 'per-issue panel renders classification / departure / dispute / extension actions');
// Read-only view hides the write forms.
$issueCanEdit=false; ob_start(); require __DIR__ . '/../views/ops/_issue_panel.php'; $hp2 = ob_get_clean();
ok(strpos($hp2,'/issue-classify')===false, 'read-only issue panel hides the write forms (permission-gated)');

// ---------------------------------------------------------------------------
if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== NCDCA: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
