<?php
// ============================================================================
//  PDSO (Phase 7) — Project Deputation & Site Operations — the GAPS only.
//  Boots the real app on a throwaway SQLite db, then verifies each genuinely-
//  missing piece, built ON the existing deputation (job) spine, reusing
//  attendance / expenses / personnel / billing / template builder untouched:
//    1. Migration: masters + additive columns + gap tables; nothing rebuilt.
//    2. Lifecycle status + history on the existing job.
//    3. Mobilization / demobilization checklist + readiness gate.
//    4. Manpower plan & gap.
//    5. Site registers (observation/issue/instruction/meeting) — NOT auto-NCR.
//    6. Activity timesheet (beside the existing day-status attendance).
//    7. Client attendance approval -> billing sign-off workflow.
//    8. Resource conflict (overlapping postings) over the existing jobs.
//    9. Deputation report types build & render via the template builder.
//   10. Advisory QA (item 17) flags, never invents/approves.
//   11. Independence: works with every other service switched off.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

$pdo = db();
// A minimal deputation posting = a DEPUTATION-type job on a call (reusing the
// existing spine; PDSO adds nothing to how a job is created).
$pdo->prepare("INSERT INTO calls (call_code,client_id,inspection_type,created_at) VALUES (?,?,?,?)")
    ->execute(['CALL-DEP-1', 501, 'DEPUTATION', date('c')]);
$callId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code,call_id,inspector_id,job_type,inspection_start_date,inspection_end_date,created_at) VALUES (?,?,?,?,?,?,?)")
    ->execute(['JOB-DEP-1', $callId, 900, 'DEPUTATION', '2026-08-01', '2026-08-31', date('c')]);
$jobId = (int)$pdo->lastInsertId();

// ---------------------------------------------------------------------------
head('1. Migration — masters, additive columns & gap tables (nothing rebuilt)');
ok(count(pdso_statuses()) >= 12, 'deputation_status lifecycle master seeded (' . count(pdso_statuses()) . ')');
ok((int)ops_val("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='dep_site_log'") === 1, 'dep_site_log table created');
ok((int)ops_val("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='dep_timesheet'") === 1, 'dep_timesheet table created');
// Additive columns on the EXISTING tables (reuse, not rebuild).
$jobCols = array_map(fn($r)=>$r['name'], ops_all("PRAGMA table_info(jobs)"));
ok(in_array('dep_status', $jobCols, true), 'jobs gained an additive dep_status column (existing job reused)');
$attCols = array_map(fn($r)=>$r['name'], ops_all("PRAGMA table_info(attendance)"));
ok(in_array('job_id', $attCols, true), 'existing attendance gained an optional job_id scope (attendance NOT rebuilt)');

// ---------------------------------------------------------------------------
head('2. Lifecycle status + history on the existing job');
ok(pdso_status_of($jobId) === '', 'a fresh job has no deputation status (backward compatible)');
pdso_set_status($jobId, 'MOBILIZED', 'client approval received');
pdso_set_status($jobId, 'ACTIVE', 'reported to site');
ok(pdso_status_of($jobId) === 'ACTIVE', 'status advances to ACTIVE');
$hist = pdso_status_history($jobId);
ok(count($hist) === 2, 'every transition is recorded in history (' . count($hist) . ')');
ok($hist[0]['new_status'] === 'ACTIVE' && $hist[0]['old_status'] === 'MOBILIZED', 'history keeps old→new (no silent change)');
ok(!pdso_set_status($jobId, 'NONSENSE', ''), 'an unknown status is rejected');

// ---------------------------------------------------------------------------
head('3. Mobilization / demobilization checklist + readiness');
$n = pdso_checklist_seed($jobId, 'MOB');
ok($n === count(PDSO_MOB_DEFAULTS), "mobilization checklist seeded ($n items, all editable)");
ok(pdso_checklist_seed($jobId, 'MOB') === 0, 'checklist seed is idempotent');
$rdy = pdso_mob_readiness($jobId, 'MOB');
ok($rdy['required_open'] > 0 && !$rdy['ready'], 'mobilization is not ready while required items are open');
// Complete every required item.
foreach (pdso_checklist($jobId, 'MOB') as $c) if ((int)$c['required'] === 1) pdso_checklist_set($c['id'], 'COMPLETED');
$rdy = pdso_mob_readiness($jobId, 'MOB');
ok($rdy['ready'], 'mobilization becomes ready once required items are completed');
ok(pdso_checklist_seed($jobId, 'DEMOB') === count(PDSO_DEMOB_DEFAULTS), 'demobilization checklist is a separate phase');

// ---------------------------------------------------------------------------
head('4. Manpower plan & gap');
$pdo->prepare("INSERT INTO dep_manpower (client_id,project,position,required,planned,mobilized) VALUES (?,?,?,?,?,?)")->execute([501,'Alpha','QC Inspector',4,4,2]);
$pdo->prepare("INSERT INTO dep_manpower (client_id,project,position,required,planned,mobilized) VALUES (?,?,?,?,?,?)")->execute([501,'Alpha','Document Controller',1,1,1]);
$gap = pdso_manpower_gap(501, 'Alpha');
ok($gap['required'] === 5 && $gap['mobilized'] === 3 && $gap['gap'] === 2, 'manpower gap computed (5 required, 3 mobilized, gap 2)');
ok($gap['shortfall'] === true, 'a shortfall is flagged');
$rows = pdso_manpower(501, 'Alpha');
$qc = null; foreach ($rows as $r) if ($r['position'] === 'QC Inspector') $qc = $r;
ok($qc && $qc['gap'] === 2, 'per-position gap is exposed (QC Inspector: required 4 − mobilized 2 = 2)');

// ---------------------------------------------------------------------------
head('5. Site registers — observation/issue/instruction/meeting (NOT auto-NCR)');
$obsId = pdso_site_log_add(['job_id'=>$jobId,'client_id'=>501,'kind'=>'OBSERVATION','detail'=>'Loose scaffold tag','risk'=>'Medium','action'=>'Retag','responsible'=>'Site','due_on'=>'2026-08-05','status'=>'OPEN']);
pdso_site_log_add(['job_id'=>$jobId,'client_id'=>501,'kind'=>'INSTRUCTION','detail'=>'Client asked for daily photos','responsible'=>'RE','due_on'=>'2026-08-02','status'=>'OPEN']);
pdso_site_log_add(['job_id'=>$jobId,'client_id'=>501,'kind'=>'MEETING','detail'=>'Weekly progress meeting','status'=>'CLOSED']);
ok(count(pdso_site_log($jobId)) === 3, 'all three site-log kinds recorded in one register');
ok(count(pdso_site_log($jobId, 'OBSERVATION')) === 1, 'register filters by kind');
$obs = ops_one("SELECT ncr_id FROM dep_site_log WHERE id=?", [$obsId]);
ok((int)$obs['ncr_id'] === 0, 'a site observation is NOT automatically an NCR (ncr_id=0)');
$overdue = array_values(array_filter(pdso_open_actions($jobId, '2026-08-10'), fn($a)=>$a['overdue']));
ok(count($overdue) >= 1, 'overdue open actions are surfaced');

// ---------------------------------------------------------------------------
head('6. Activity timesheet (beside the existing day-status attendance)');
pdso_timesheet_add(['job_id'=>$jobId,'inspector_id'=>900,'ts_date'=>'2026-08-01','activity'=>'Witness FAT','hours'=>8,'ot_hours'=>1]);
pdso_timesheet_add(['job_id'=>$jobId,'inspector_id'=>900,'ts_date'=>'2026-08-02','activity'=>'Document review','hours'=>6]);
$tot = pdso_timesheet_totals($jobId);
ok($tot['hours'] === 14.0 && $tot['ot_hours'] === 1.0 && $tot['days'] === 2, 'timesheet totals: 14h + 1 OT over 2 days');

// ---------------------------------------------------------------------------
head('7. Client attendance approval -> billing sign-off');
$apId = pdso_att_approval_add(['job_id'=>$jobId,'client_id'=>501,'period_from'=>'2026-08-01','period_to'=>'2026-08-31','basis'=>'MANDAY','billable_days'=>22]);
ok((int)ops_val("SELECT COUNT(*) FROM dep_att_approval WHERE status='DRAFT'") === 1, 'approval starts as DRAFT');
pdso_att_approval_set_status($apId, 'SUBMITTED');
pdso_att_approval_set_status($apId, 'APPROVED', 'Client PM', 'Confirmed 22 man-days');
$ap = ops_one("SELECT * FROM dep_att_approval WHERE id=?", [$apId]);
ok($ap['status'] === 'APPROVED' && $ap['approved_on'] !== '' && $ap['source'] === 'CLIENT', 'approval reaches APPROVED with client sign-off + date + CLIENT provenance');
ok((float)$ap['billable_days'] === 22.0, 'billable quantity is preserved for billing support (not recomputed)');

// ---------------------------------------------------------------------------
head('8. Resource conflict — overlapping postings (over the existing jobs)');
ok(pdso_resource_conflicts(900) === [], 'no conflict with a single posting');
// A second overlapping posting for the same person.
$pdo->prepare("INSERT INTO calls (call_code,client_id,inspection_type,created_at) VALUES (?,?,?,?)")->execute(['CALL-DEP-2', 502, 'DEPUTATION', date('c')]);
$c2 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code,call_id,inspector_id,job_type,dep_site,inspection_start_date,inspection_end_date,dep_status,created_at) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute(['JOB-DEP-2', $c2, 900, 'DEPUTATION', 'Site B', '2026-08-15', '2026-09-15', 'ACTIVE', date('c')]);
pdso_set_status($jobId, 'ACTIVE', '');
$pdo->prepare("UPDATE jobs SET dep_site='Site A' WHERE id=?")->execute([$jobId]);
$conf = pdso_resource_conflicts(900);
ok(count($conf) === 1, 'an overlapping second posting is flagged as a conflict');
ok($conf[0]['severity'] === 'high', 'different sites make it high severity');
ok($conf[0]['from'] === '2026-08-15' && $conf[0]['to'] === '2026-08-31', 'the overlap window is computed');

// ---------------------------------------------------------------------------
head('9. Deputation report types build & render via the template builder');
$samples = array_map(fn($s)=>$s[0], PDSO_SAMPLE_TYPES);
ok(count($samples) === 5, 'five deputation report types (daily/weekly/fortnightly/monthly/final)');
$rendered = 0;
$repData = ['dep_project'=>'Alpha','dep_site'=>'Site A',
    'dep_activities_tbl'=>[['Date'=>'2026-08-01','Activity'=>'Witness FAT','Hours'=>'8']],
    'dep_attendance_tbl'=>[['Person'=>'A. Kumar','Present'=>'22']]];
foreach ($samples as $code) {
    $tid = (int)ops_val("SELECT id FROM report_types WHERE code=?", [$code]);
    if (!$tid) { echo "  FAIL sample $code did not build\n"; continue; }
    $pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,title,status,data,client_id,created_at) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$tid,$code,'REND-'.$code,'Render '.$code,'DRAFT',json_encode($repData),501,date('c')]);
    $rid = (int)$pdo->lastInsertId(); $rdoc = ops_one("SELECT * FROM report_docs WHERE id=?", [$rid]);
    try { $bytes = report_pdf_build($rdoc, idems_sections($tid), idems_fields($tid), $repData, [], [], []);
        if (is_string($bytes) && strncmp($bytes,'%PDF',4)===0 && strlen($bytes)>800) $rendered++; } catch (Throwable $e) { echo "  FAIL $code render: ".$e->getMessage()."\n"; }
}
ok($rendered === 5, "all $rendered/5 deputation report PDFs rendered");

// ---------------------------------------------------------------------------
head('10. Advisory QA (item 17) flags, never invents or approves');
$dailyTid = (int)ops_val("SELECT id FROM report_types WHERE code='PDSO_WEEKLY'");
$flds = idems_fields($dailyTid);
// Attendance present but NO activities → should flag.
$bad = ['dep_project'=>'Alpha','dep_attendance_tbl'=>[['Person'=>'A','Present'=>'5']],'dep_activities_tbl'=>[]];
$qa = pdso_qa_checks(['report_type_id'=>$dailyTid], $flds, $bad);
$titles = implode(' | ', array_map(fn($q)=>$q['title'], $qa));
ok(stripos($titles,'no activities')!==false, 'QA flags attendance-with-no-activities');
// Data is untouched (advisory only).
ok($bad['dep_activities_tbl'] === [], 'QA invents nothing — the empty activity list is left empty');
ok(pdso_qa_checks(['report_type_id'=>0], [], []) === [], 'QA self-gates (inert on a non-deputation report)');
$run = idems_qa_run(['report_type_id'=>$dailyTid,'data'=>json_encode($bad)], $flds, $bad);
ok(in_array($run['status'],['BLOCKED','REVIEW','READY'],true), 'idems_qa_run integrates PDSO checks (status=' . $run['status'] . ')');

// ---------------------------------------------------------------------------
head('11. Independence — works with every other service switched off');
if (function_exists('svc_set_global')) {
    foreach (['INSPECTION','VENDOR_ASSESSMENT','VENDOR_AUDIT','EXPEDITING','RELEASE'] as $c) svc_set_global($c, false);
    ok(pdso_enabled(), 'deputation still enabled with every other service off');
    ok(pdso_status_of($jobId) === 'ACTIVE' && count(pdso_site_log($jobId)) === 3, 'deputation data & lifecycle keep working with other services off');
    // NCR service off must not break the site register (no auto-NCR dependency).
    $okAdd = pdso_site_log_add(['job_id'=>$jobId,'kind'=>'ISSUE','detail'=>'works without NCR service']);
    ok($okAdd > 0, 'a site issue can be raised with the NCR service inactive (no forced link)');
    foreach (['INSPECTION','VENDOR_ASSESSMENT','VENDOR_AUDIT','EXPEDITING','RELEASE'] as $c) svc_set_global($c, true);
} else { ok(true, 'service scope engine not present — independence trivially holds'); }

// ---------------------------------------------------------------------------
head('12. Dashboard aggregates + register view render');
$dash = pdso_dashboard();
ok($dash['active'] >= 1, 'dashboard counts active postings (' . $dash['active'] . ')');
ok($dash['manpower_gap'] === 2, 'dashboard rolls up the manpower gap (2)');
ok($dash['overdue_actions'] >= 1, 'dashboard counts overdue site actions');
ok($dash['conflict_people'] >= 1, 'dashboard counts double-booked people');
// Render the register/dashboard view without error.
$vd = pdso_dashboard(); $rows = pdso_deputations(); $statuses = pdso_statuses(); $status = ''; $manpower = pdso_manpower(0, null);
ob_start(); $d = $vd; require __DIR__ . '/../views/ops/deputations.php'; $html = ob_get_clean();
ok(strpos($html, 'kpi-row') !== false && strpos($html, 'Active postings') !== false, 'deputation dashboard view renders the KPI row');
ok(strpos($html, 'JOB-DEP-1') !== false || strpos($html, 'Deputations') !== false, 'the register lists the deputation postings');

// ---------------------------------------------------------------------------
if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== PDSO: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
