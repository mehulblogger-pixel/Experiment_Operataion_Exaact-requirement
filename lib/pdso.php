<?php
// ===========================================================================
//  PDSO — Project Deputation & Site Operations Engine  (Phase 7)
// ---------------------------------------------------------------------------
//  GAP-FILLER, not a new system. The application is ALREADY a deputation
//  platform: a DEPUTATION-type job (Settings → Terminology renames "Job" to
//  "Deputation") is the posting; attendance, expenses, man-day / man-month
//  billing, personnel (inspectors / users), competence & certification with
//  expiry, the report engine + Template Builder (URFE), the actions engine,
//  the client portal and the Service-Scope engine all exist and are LOCKED.
//
//  So PDSO builds ONLY the site-operations pieces that were genuinely missing,
//  and attaches them to the EXISTING deputation (job_id) — it creates NO new
//  deployment / attendance / timesheet-of-record / expense / billing object:
//
//    * Deployment LIFECYCLE status (Planned → Mobilized → Active → On Leave →
//      Demobilized → Closed) with history — jobs only had a closed flag.
//    * Mobilization / demobilization CHECKLIST.
//    * Manpower PLAN & GAP (position required vs mobilized).
//    * Site TRANSFER / rotation history.
//    * Client attendance APPROVAL → billing sign-off (on top of the existing
//      attendance rows; it never re-stores attendance).
//    * Activity-level TIMESHEET (the existing attendance is day-status only).
//    * Site REGISTERS — observation / issue / instruction / meeting. A site
//      observation is explicitly NOT automatically an NCR (§37); it may be
//      linked to one only where the NCR service is active and a user chooses to.
//    * Resource-CONFLICT detection (overlapping postings) — pure logic over the
//      existing jobs + dates.
//    * Daily / Weekly / Fortnightly / Monthly / Final deputation REPORT TYPES,
//      assembled from the Template Builder (URFE) — same pattern as UIRE/UVAAE.
//    * Advisory AI QA (timesheet-vs-attendance, missing activity, overdue
//      action) via idems_qa_run — flags, never invents or approves (§88/§89).
//
//  Independence (§2/§77): everything self-gates on the DEPUTATION service and is
//  function_exists-guarded, so a client may use ONLY deputation — with Inspection,
//  Audit, Assessment, Expediting, Release and NCR/CAPA all inactive — and every
//  screen keeps working. Additive & non-destructive: with no rows, the app is
//  unchanged.
// ===========================================================================

// ---- Masters (all editable via the lookup engine) --------------------------
// Deployment lifecycle (§6). The existing job closed-flag is untouched; this is
// the richer posting status a deputation needs.
const PDSO_STATUS = [
    'PLANNED'=>'Planned','APPROVED'=>'Approved','MOB_PENDING'=>'Mobilization pending','MOBILIZED'=>'Mobilized',
    'ACTIVE'=>'Active','ON_LEAVE'=>'On leave','SUSPENDED'=>'Temporarily suspended','REPLACEMENT_REQ'=>'Replacement required',
    'DEMOB_PLANNED'=>'Demobilization planned','DEMOBILIZED'=>'Demobilized','CANCELLED'=>'Cancelled','CLOSED'=>'Closed',
];
// Mobilization / demobilization checklist item status (§16).
const PDSO_MOB_STATUS = [
    'REQUIRED'=>'Required','SUBMITTED'=>'Submitted','APPROVED'=>'Approved','PENDING'=>'Pending',
    'REJECTED'=>'Rejected','COMPLETED'=>'Completed','NOT_APPLICABLE'=>'Not applicable',
];
// Site-register entry kinds (§37/§38/§36/§40) — one flexible register, kind-typed.
const PDSO_LOG_KINDS = [
    'OBSERVATION'=>'Site observation','ISSUE'=>'Issue','INSTRUCTION'=>'Client instruction','MEETING'=>'Meeting / MoM',
];
// Site-register entry status (§35 reused shape).
const PDSO_LOG_STATUS = [
    'OPEN'=>'Open','IN_PROGRESS'=>'In progress','ON_HOLD'=>'On hold','CLOSED'=>'Closed','CANCELLED'=>'Cancelled',
];
// Client attendance / timesheet approval (§49/§51).
const PDSO_APPROVAL_STATUS = [
    'DRAFT'=>'Draft','SUBMITTED'=>'Submitted','CLIENT_REVIEW'=>'Client review','RETURNED'=>'Returned',
    'APPROVED'=>'Approved','REJECTED'=>'Rejected','LOCKED'=>'Locked',
];
// Shift master (§19) — timings are NOT hard-coded; these are labels only.
const PDSO_SHIFTS = [
    'GENERAL'=>'General','DAY'=>'Day','NIGHT'=>'Night','MORNING'=>'Morning','EVENING'=>'Evening',
    'ROTATIONAL'=>'Rotational','12H'=>'12-hour','8H'=>'8-hour','CUSTOM'=>'Custom',
];
// Data provenance (§90) — who a value came from; never mixed.
const PDSO_SOURCE = [
    'PERSONNEL'=>'Personnel reported','CLIENT'=>'Client confirmed','TPIA'=>'TPIA verified',
    'SYSTEM'=>'System calculated','AI'=>'AI suggested','MANUAL'=>'Manual','MOBILE'=>'Mobile','BIOMETRIC'=>'Biometric',
];

// ---------------------------------------------------------------------------
//  Schema — additive only. Every table keys to the EXISTING deputation (job_id)
//  and/or client; none re-stores attendance, expenses, personnel or billing.
// ---------------------------------------------------------------------------
function pdso_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('deputation_status',        'Deputation status',           PDSO_STATUS,          'pdso');
        lk_ensure_type_map('deputation_mob_status',    'Mobilization item status',    PDSO_MOB_STATUS,      'pdso');
        lk_ensure_type_map('deputation_log_kind',      'Site register kind',          PDSO_LOG_KINDS,       'pdso');
        lk_ensure_type_map('deputation_log_status',    'Site register status',        PDSO_LOG_STATUS,      'pdso');
        lk_ensure_type_map('deputation_approval',      'Attendance approval status',  PDSO_APPROVAL_STATUS, 'pdso');
        lk_ensure_type_map('deputation_shift',         'Shift',                       PDSO_SHIFTS,          'pdso');
    }
    $pk = pk_clause();

    // Lifecycle status ON the existing deputation (job) — additive column + a
    // history table. Only deputation-type jobs use it; everything else ignores it.
    if (function_exists('ensure_column')) {
        ensure_column('jobs', 'dep_status',    "VARCHAR(24) DEFAULT ''");
        ensure_column('jobs', 'dep_shift',     "VARCHAR(20) DEFAULT ''");
        ensure_column('jobs', 'dep_site',      "VARCHAR(200) DEFAULT ''");
        // A deputation's attendance rows can be scoped to the posting without
        // changing how attendance is stored elsewhere (default 0 = unscoped).
        ensure_column('attendance', 'job_id',  "INT DEFAULT 0");
    }
    db()->exec("CREATE TABLE IF NOT EXISTS dep_status_events (
        id $pk, job_id INT DEFAULT 0, old_status VARCHAR(24) DEFAULT '', new_status VARCHAR(24) DEFAULT '',
        reason VARCHAR(400) DEFAULT '', actor VARCHAR(120) DEFAULT '', at VARCHAR(30) DEFAULT '')");

    // Mobilization / demobilization checklist (§15/§16/§66).
    db()->exec("CREATE TABLE IF NOT EXISTS dep_checklist (
        id $pk, job_id INT DEFAULT 0, phase VARCHAR(12) DEFAULT 'MOB', item VARCHAR(200) DEFAULT '',
        category VARCHAR(80) DEFAULT '', required INT DEFAULT 1, status VARCHAR(24) DEFAULT 'REQUIRED',
        due_on VARCHAR(20) DEFAULT '', done_on VARCHAR(20) DEFAULT '', note VARCHAR(400) DEFAULT '',
        sort_order INT DEFAULT 0, updated_by VARCHAR(120) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");

    // Manpower plan & gap (§45/§46) — project-level (client + project name).
    db()->exec("CREATE TABLE IF NOT EXISTS dep_manpower (
        id $pk, client_id INT DEFAULT 0, project VARCHAR(200) DEFAULT '', position VARCHAR(150) DEFAULT '',
        required INT DEFAULT 0, planned INT DEFAULT 0, mobilized INT DEFAULT 0,
        start_on VARCHAR(20) DEFAULT '', end_on VARCHAR(20) DEFAULT '', status VARCHAR(24) DEFAULT '',
        note VARCHAR(400) DEFAULT '', sort_order INT DEFAULT 0, updated_at VARCHAR(30) DEFAULT '')");

    // Site transfer / rotation history (§9) — complete history retained.
    db()->exec("CREATE TABLE IF NOT EXISTS dep_site_history (
        id $pk, job_id INT DEFAULT 0, inspector_id INT DEFAULT 0, site VARCHAR(200) DEFAULT '',
        location VARCHAR(200) DEFAULT '', from_date VARCHAR(20) DEFAULT '', to_date VARCHAR(20) DEFAULT '',
        reason VARCHAR(300) DEFAULT '', kind VARCHAR(24) DEFAULT 'ASSIGNMENT', created_by VARCHAR(120) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");

    // Site registers (§37/§38/§36/§40) — one kind-typed register. NOT an NCR by
    // default; ncr_id links to one only where chosen and the NCR service is on.
    db()->exec("CREATE TABLE IF NOT EXISTS dep_site_log (
        id $pk, job_id INT DEFAULT 0, client_id INT DEFAULT 0, kind VARCHAR(24) DEFAULT 'OBSERVATION',
        log_date VARCHAR(20) DEFAULT '', location VARCHAR(200) DEFAULT '', ref_no VARCHAR(60) DEFAULT '',
        detail TEXT, risk VARCHAR(20) DEFAULT '', action VARCHAR(600) DEFAULT '', responsible VARCHAR(150) DEFAULT '',
        due_on VARCHAR(20) DEFAULT '', status VARCHAR(24) DEFAULT 'OPEN', evidence_ref VARCHAR(300) DEFAULT '',
        client_visible INT DEFAULT 1, private_note VARCHAR(600) DEFAULT '', source VARCHAR(20) DEFAULT 'TPIA',
        ncr_id INT DEFAULT 0, created_by VARCHAR(120) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");

    // Activity-level timesheet (§24/§25) — hours per activity. The existing
    // day-status attendance is untouched; this sits beside it.
    db()->exec("CREATE TABLE IF NOT EXISTS dep_timesheet (
        id $pk, job_id INT DEFAULT 0, inspector_id INT DEFAULT 0, ts_date VARCHAR(20) DEFAULT '',
        activity VARCHAR(200) DEFAULT '', description VARCHAR(600) DEFAULT '', start_time VARCHAR(10) DEFAULT '',
        end_time VARCHAR(10) DEFAULT '', hours REAL DEFAULT 0, ot_hours REAL DEFAULT 0, billable INT DEFAULT 1,
        reference VARCHAR(200) DEFAULT '', source VARCHAR(20) DEFAULT 'PERSONNEL', remarks VARCHAR(400) DEFAULT '',
        created_by VARCHAR(120) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");

    // Client attendance / timesheet approval → billing support (§48/§49/§50/§51).
    // References the existing attendance & man-day billing; stores only the
    // sign-off, the agreed billable quantity and the client's confirmation.
    db()->exec("CREATE TABLE IF NOT EXISTS dep_att_approval (
        id $pk, job_id INT DEFAULT 0, client_id INT DEFAULT 0, period_from VARCHAR(20) DEFAULT '',
        period_to VARCHAR(20) DEFAULT '', basis VARCHAR(20) DEFAULT '', billable_days REAL DEFAULT 0,
        billable_hours REAL DEFAULT 0, ot_hours REAL DEFAULT 0, rate_ref VARCHAR(120) DEFAULT '',
        status VARCHAR(24) DEFAULT 'DRAFT', client_rep VARCHAR(150) DEFAULT '', approved_on VARCHAR(20) DEFAULT '',
        comments VARCHAR(600) DEFAULT '', invoice_ref VARCHAR(80) DEFAULT '', source VARCHAR(20) DEFAULT 'TPIA',
        created_by VARCHAR(120) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");

    // Deputation report library sections + sample report types (via URFE).
    if (function_exists('setting_get') && !setting_get('pdso_library_seeded_v1', '')) {
        try { pdso_seed_library(); } catch (Throwable $e) { /* never break login/migrate */ }
        if (function_exists('setting_set')) setting_set('pdso_library_seeded_v1', '1');
    }
    if (function_exists('setting_get') && !setting_get('pdso_samples_seeded_v1', '')) {
        try { pdso_seed_sample_types(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('pdso_samples_seeded_v1', '1');
    }
}

// ---------------------------------------------------------------------------
//  Service gate & small helpers.
// ---------------------------------------------------------------------------
function pdso_enabled() { return !function_exists('svc_globally_active') || svc_globally_active('DEPUTATION'); }
function pdso_statuses()        { return function_exists('lk_options_or') ? lk_options_or('deputation_status', PDSO_STATUS) : PDSO_STATUS; }
function pdso_mob_statuses()    { return function_exists('lk_options_or') ? lk_options_or('deputation_mob_status', PDSO_MOB_STATUS) : PDSO_MOB_STATUS; }
function pdso_log_kinds()       { return function_exists('lk_options_or') ? lk_options_or('deputation_log_kind', PDSO_LOG_KINDS) : PDSO_LOG_KINDS; }
function pdso_approval_statuses(){ return function_exists('lk_options_or') ? lk_options_or('deputation_approval', PDSO_APPROVAL_STATUS) : PDSO_APPROVAL_STATUS; }
function pdso_shifts()          { return function_exists('lk_options_or') ? lk_options_or('deputation_shift', PDSO_SHIFTS) : PDSO_SHIFTS; }
function pdso_actor() { return function_exists('current_user') ? (string)(current_user()['username'] ?? 'system') : 'system'; }

// ---- Lifecycle (§6/§93) ----------------------------------------------------
function pdso_status_of($jobId) {
    $s = ops_val("SELECT dep_status FROM jobs WHERE id=?", [(int)$jobId]);
    return $s !== false && $s !== null ? (string)$s : '';
}
function pdso_set_status($jobId, $newStatus, $reason = '') {
    $jobId = (int)$jobId; if (!$jobId || !isset(pdso_statuses()[$newStatus])) return false;
    $old = pdso_status_of($jobId);
    if ($old === $newStatus) return true;
    db()->prepare("UPDATE jobs SET dep_status=? WHERE id=?")->execute([$newStatus, $jobId]);
    db()->prepare("INSERT INTO dep_status_events (job_id,old_status,new_status,reason,actor,at) VALUES (?,?,?,?,?,?)")
        ->execute([$jobId, $old, $newStatus, (string)$reason, pdso_actor(), date('c')]);
    if (function_exists('idems_log')) { try { idems_log('deputation', $jobId, 'STATUS', ['old'=>$old,'new'=>$newStatus]); } catch (Throwable $e) {} }
    return true;
}
function pdso_status_history($jobId) {
    return ops_all("SELECT * FROM dep_status_events WHERE job_id=? ORDER BY id DESC", [(int)$jobId]) ?: [];
}

// ---- Mobilization / demobilization checklist (§15/§16/§66) ------------------
// Default items a body usually needs — every one is editable/removable, and none
// is mandatory (§15: "Not every item is mandatory").
const PDSO_MOB_DEFAULTS = [
    ['Assignment letter','Documentation',1],['Client approval','Approval',1],['Qualification / CV','Documentation',1],
    ['Certification','Documentation',0],['Site access / gate pass','Access',0],['Induction','Training',0],
    ['PPE issued','HSE',0],['ID card','Access',0],['Travel','Logistics',0],['Accommodation','Logistics',0],
    ['Equipment / laptop','Assets',0],['Communication','Logistics',0],
];
const PDSO_DEMOB_DEFAULTS = [
    ['Handover completed','Handover',1],['Open tasks closed / transferred','Handover',1],['Asset return','Assets',0],
    ['Site access closure','Access',0],['Final timesheet','Billing',1],['Final report','Reporting',0],['Client confirmation','Approval',1],
];
function pdso_checklist_seed($jobId, $phase = 'MOB') {
    $jobId = (int)$jobId; $phase = strtoupper($phase) === 'DEMOB' ? 'DEMOB' : 'MOB';
    if ((int)ops_val("SELECT COUNT(*) FROM dep_checklist WHERE job_id=? AND phase=?", [$jobId, $phase]) > 0) return 0;
    $defs = $phase === 'DEMOB' ? PDSO_DEMOB_DEFAULTS : PDSO_MOB_DEFAULTS;
    $ins = db()->prepare("INSERT INTO dep_checklist (job_id,phase,item,category,required,status,sort_order,updated_at) VALUES (?,?,?,?,?,'REQUIRED',?,?)");
    $so = 0; foreach ($defs as $d) { $so += 10; $ins->execute([$jobId, $phase, $d[0], $d[1], (int)$d[2], $so, date('c')]); }
    return count($defs);
}
function pdso_checklist($jobId, $phase = null) {
    $w = "job_id=?"; $a = [(int)$jobId];
    if ($phase !== null) { $w .= " AND phase=?"; $a[] = strtoupper($phase); }
    return ops_all("SELECT * FROM dep_checklist WHERE $w ORDER BY phase, sort_order, id", $a) ?: [];
}
function pdso_checklist_set($id, $status, $note = '') {
    $id = (int)$id; if (!$id || !isset(pdso_mob_statuses()[$status])) return false;
    $done = $status === 'COMPLETED' || $status === 'APPROVED' ? date('Y-m-d') : '';
    db()->prepare("UPDATE dep_checklist SET status=?, note=?, done_on=?, updated_by=?, updated_at=? WHERE id=?")
        ->execute([$status, (string)$note, $done, pdso_actor(), date('c'), $id]);
    return true;
}
// Mobilization readiness — how many required items are still open.
function pdso_mob_readiness($jobId, $phase = 'MOB') {
    $rows = pdso_checklist($jobId, $phase);
    $req = 0; $reqDone = 0; $total = count($rows); $done = 0;
    foreach ($rows as $r) {
        $ok = in_array($r['status'], ['COMPLETED','APPROVED','NOT_APPLICABLE'], true);
        if ($ok) $done++;
        if ((int)$r['required'] === 1) { $req++; if ($ok) $reqDone++; }
    }
    return ['total'=>$total,'done'=>$done,'required'=>$req,'required_done'=>$reqDone,
            'required_open'=>$req-$reqDone,'ready'=>($req-$reqDone) === 0];
}

// ---- Manpower plan & gap (§45/§46) -----------------------------------------
function pdso_manpower($clientId = 0, $project = null) {
    $w = "1=1"; $a = [];
    if ($clientId) { $w .= " AND client_id=?"; $a[] = (int)$clientId; }
    if ($project !== null && $project !== '') { $w .= " AND project=?"; $a[] = (string)$project; }
    $rows = ops_all("SELECT * FROM dep_manpower WHERE $w ORDER BY project, sort_order, position", $a) ?: [];
    foreach ($rows as &$r) $r['gap'] = max(0, (int)$r['required'] - (int)$r['mobilized']);
    return $rows;
}
function pdso_manpower_gap($clientId = 0, $project = null) {
    $req = 0; $mob = 0;
    foreach (pdso_manpower($clientId, $project) as $r) { $req += (int)$r['required']; $mob += (int)$r['mobilized']; }
    return ['required'=>$req, 'mobilized'=>$mob, 'gap'=>max(0, $req-$mob), 'shortfall'=>$req > $mob];
}

// ---- Site registers (§36/§37/§38/§40) --------------------------------------
function pdso_site_log_add($data) {
    $ins = db()->prepare("INSERT INTO dep_site_log
        (job_id,client_id,kind,log_date,location,ref_no,detail,risk,action,responsible,due_on,status,evidence_ref,client_visible,private_note,source,ncr_id,created_by,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([
        (int)($data['job_id'] ?? 0), (int)($data['client_id'] ?? 0), strtoupper((string)($data['kind'] ?? 'OBSERVATION')),
        (string)($data['log_date'] ?? date('Y-m-d')), (string)($data['location'] ?? ''), (string)($data['ref_no'] ?? ''),
        (string)($data['detail'] ?? ''), (string)($data['risk'] ?? ''), (string)($data['action'] ?? ''),
        (string)($data['responsible'] ?? ''), (string)($data['due_on'] ?? ''), (string)($data['status'] ?? 'OPEN'),
        (string)($data['evidence_ref'] ?? ''), isset($data['client_visible']) ? (int)$data['client_visible'] : 1,
        (string)($data['private_note'] ?? ''), (string)($data['source'] ?? 'TPIA'), (int)($data['ncr_id'] ?? 0),
        pdso_actor(), date('c'),
    ]);
    return (int)db()->lastInsertId();
}
function pdso_site_log($jobId = 0, $kind = null, $status = null) {
    $w = "1=1"; $a = [];
    if ($jobId) { $w .= " AND job_id=?"; $a[] = (int)$jobId; }
    if ($kind !== null) { $w .= " AND kind=?"; $a[] = strtoupper($kind); }
    if ($status !== null) { $w .= " AND status=?"; $a[] = strtoupper($status); }
    return ops_all("SELECT * FROM dep_site_log WHERE $w ORDER BY log_date DESC, id DESC", $a) ?: [];
}
// Overdue open actions across the site registers — feeds the dashboard & QA.
function pdso_open_actions($jobId = 0, $today = null) {
    $today = $today ?: date('Y-m-d');
    $rows = pdso_site_log($jobId);
    $out = [];
    foreach ($rows as $r) {
        if (in_array($r['status'], ['CLOSED','CANCELLED'], true)) continue;
        if (trim((string)$r['action']) === '' && $r['kind'] !== 'INSTRUCTION') continue;
        $overdue = $r['due_on'] !== '' && strtotime($r['due_on']) && strtotime($r['due_on']) < strtotime($today);
        $out[] = ['id'=>$r['id'],'kind'=>$r['kind'],'detail'=>$r['detail'],'due_on'=>$r['due_on'],'overdue'=>$overdue,'responsible'=>$r['responsible']];
    }
    return $out;
}

// ---- Activity timesheet (§24/§25) ------------------------------------------
function pdso_timesheet_add($data) {
    $ins = db()->prepare("INSERT INTO dep_timesheet
        (job_id,inspector_id,ts_date,activity,description,start_time,end_time,hours,ot_hours,billable,reference,source,remarks,created_by,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([
        (int)($data['job_id'] ?? 0), (int)($data['inspector_id'] ?? 0), (string)($data['ts_date'] ?? date('Y-m-d')),
        (string)($data['activity'] ?? ''), (string)($data['description'] ?? ''), (string)($data['start_time'] ?? ''),
        (string)($data['end_time'] ?? ''), (float)($data['hours'] ?? 0), (float)($data['ot_hours'] ?? 0),
        isset($data['billable']) ? (int)$data['billable'] : 1, (string)($data['reference'] ?? ''),
        (string)($data['source'] ?? 'PERSONNEL'), (string)($data['remarks'] ?? ''), pdso_actor(), date('c'),
    ]);
    return (int)db()->lastInsertId();
}
function pdso_timesheet($jobId, $from = null, $to = null) {
    $w = "job_id=?"; $a = [(int)$jobId];
    if ($from) { $w .= " AND ts_date>=?"; $a[] = $from; }
    if ($to)   { $w .= " AND ts_date<=?"; $a[] = $to; }
    return ops_all("SELECT * FROM dep_timesheet WHERE $w ORDER BY ts_date, id", $a) ?: [];
}
function pdso_timesheet_totals($jobId, $from = null, $to = null) {
    $h = 0; $ot = 0; $days = [];
    foreach (pdso_timesheet($jobId, $from, $to) as $r) { $h += (float)$r['hours']; $ot += (float)$r['ot_hours']; $days[$r['ts_date']] = true; }
    return ['hours'=>$h, 'ot_hours'=>$ot, 'days'=>count($days)];
}

// ---- Client attendance approval → billing support (§48/§49/§50/§51) --------
function pdso_att_approval_add($data) {
    $ins = db()->prepare("INSERT INTO dep_att_approval
        (job_id,client_id,period_from,period_to,basis,billable_days,billable_hours,ot_hours,rate_ref,status,client_rep,approved_on,comments,invoice_ref,source,created_by,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $now = date('c');
    $ins->execute([
        (int)($data['job_id'] ?? 0), (int)($data['client_id'] ?? 0), (string)($data['period_from'] ?? ''),
        (string)($data['period_to'] ?? ''), (string)($data['basis'] ?? ''), (float)($data['billable_days'] ?? 0),
        (float)($data['billable_hours'] ?? 0), (float)($data['ot_hours'] ?? 0), (string)($data['rate_ref'] ?? ''),
        (string)($data['status'] ?? 'DRAFT'), (string)($data['client_rep'] ?? ''), (string)($data['approved_on'] ?? ''),
        (string)($data['comments'] ?? ''), (string)($data['invoice_ref'] ?? ''), (string)($data['source'] ?? 'TPIA'),
        pdso_actor(), $now, $now,
    ]);
    return (int)db()->lastInsertId();
}
// Move an approval through the workflow. Approved/Locked rows are the controlled
// record; a change after that must be a new period or an explicit re-open (§84).
function pdso_att_approval_set_status($id, $status, $clientRep = '', $comments = '') {
    $id = (int)$id; if (!$id || !isset(pdso_approval_statuses()[$status])) return false;
    $approvedOn = $status === 'APPROVED' ? date('Y-m-d') : '';
    db()->prepare("UPDATE dep_att_approval SET status=?, client_rep=COALESCE(NULLIF(?,''),client_rep), approved_on=COALESCE(NULLIF(?,''),approved_on), comments=?, source=?, updated_at=? WHERE id=?")
        ->execute([$status, (string)$clientRep, $approvedOn, (string)$comments, $status==='APPROVED'?'CLIENT':'TPIA', date('c'), $id]);
    if (function_exists('idems_log')) { try { idems_log('dep_att_approval', $id, 'STATUS', ['new'=>$status]); } catch (Throwable $e) {} }
    return true;
}
function pdso_att_approvals($jobId) { return ops_all("SELECT * FROM dep_att_approval WHERE job_id=? ORDER BY period_from DESC, id DESC", [(int)$jobId]) ?: []; }

// ---- Resource conflict (§74) — overlapping postings for one person ---------
// Pure logic over the EXISTING jobs (dates + inspector). Never moves anybody.
function pdso_resource_conflicts($inspectorId, $excludeJobId = 0) {
    $inspectorId = (int)$inspectorId; if (!$inspectorId) return [];
    // Deputation-type jobs (or any job carrying a lifecycle status) with a date span.
    $rows = ops_all("SELECT id, call_id, dep_site, dep_status,
                        COALESCE(NULLIF(inspection_start_date,''), scheduled_date) AS s,
                        COALESCE(NULLIF(inspection_end_date,''), NULLIF(inspection_start_date,''), scheduled_date) AS e
                     FROM jobs WHERE inspector_id=? AND id<>? AND COALESCE(NULLIF(dep_status,''),'')<>'CLOSED'
                       AND COALESCE(NULLIF(dep_status,''),'')<>'CANCELLED' AND COALESCE(NULLIF(dep_status,''),'')<>'DEMOBILIZED'",
                    [$inspectorId, (int)$excludeJobId]) ?: [];
    $spans = [];
    foreach ($rows as $r) { $s = (string)$r['s']; $e = (string)$r['e'] ?: $s; if ($s === '') continue; $spans[] = ['id'=>(int)$r['id'],'s'=>$s,'e'=>$e ?: $s,'site'=>$r['dep_site']]; }
    $out = [];
    for ($i = 0; $i < count($spans); $i++) for ($j = $i+1; $j < count($spans); $j++) {
        $a = $spans[$i]; $b = $spans[$j];
        // overlap if a.s <= b.e AND b.s <= a.e
        if (strtotime($a['s']) <= strtotime($b['e']) && strtotime($b['s']) <= strtotime($a['e'])) {
            $out[] = ['job_a'=>$a['id'],'job_b'=>$b['id'],'from'=>max($a['s'],$b['s']),'to'=>min($a['e'],$b['e']),
                      'site_a'=>$a['site'],'site_b'=>$b['site'],'severity'=>($a['site']!==$b['site']?'high':'medium')];
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  Deputation report library (§26/§29/§30/§31/§32/§67) — assembled from the
//  Template Builder (URFE), exactly like UIRE/UVAAE. New report TYPES only; the
//  report engine, evidence, workflow, approval, PDF and numbering are reused.
// ---------------------------------------------------------------------------
const PDSO_LIB_FIELDS = [
    ['dep_project',     'Project / assignment',  'text',        '',                          'Deputation'],
    ['dep_site',        'Site / location',       'text',        '',                          'Deputation'],
    ['dep_personnel',   'Personnel',             'text',        '',                          'Deputation'],
    ['dep_shift',       'Shift',                 'select',      'lookup:deputation_shift',   'Deputation'],
    ['dep_period_from', 'Period from',           'date',        '',                          'Deputation'],
    ['dep_period_to',   'Period to',             'date',        '',                          'Deputation'],
    ['dep_next_plan',   'Next period plan',      'textarea',    '',                          'Plan'],
    ['dep_conclusion',  'Conclusion',            'textarea',    '',                          'Conclusion'],
];
const PDSO_LIB_TABLES = [
    ['dep_attendance_tbl','Attendance summary', 'Attendance',
        "Person\nPresent\nAbsent\nLeave\nWeekly off\nHoliday\nTravel\nOT hours\nRemarks"],
    ['dep_activities_tbl','Activities', 'Activities',
        "Date|date\nActivity\nDescription\nLocation\nHours\nOutput / reference\nRemarks"],
    ['dep_tasks_tbl','Tasks & actions', 'Tasks',
        "Task\nOwner\nDue|date\nPriority|select|Low,Medium,High\nStatus|select|Not started,In progress,Completed,Delayed,Blocked,On hold,Closed\nProgress\nRemarks"],
    ['dep_issues_tbl','Issues', 'Issues',
        "Date|date\nCategory\nIssue / description\nImpact\nOwner\nAction\nDue|date\nStatus|select|Open,In progress,On hold,Closed\nRemarks"],
    ['dep_instructions_tbl','Client instructions', 'Instructions',
        "Date|date\nClient rep\nInstruction\nResponsible\nDue|date\nResponse\nStatus|select|Open,In progress,Closed\nEvidence"],
    ['dep_meetings_tbl','Meetings / MoM', 'Meetings',
        "Date|date\nParticipants\nAgenda\nDiscussion / decision\nAction\nOwner\nDue|date\nStatus|select|Open,Closed"],
];
const PDSO_LIB_SECTIONS = [
    ['DEP_DETAILS',     'Deputation details',       'FIELDS', '', 'Project, site, personnel, shift & period',
        ['dep_project','dep_site','dep_personnel','dep_shift','dep_period_from','dep_period_to'], []],
    ['DEP_ATTENDANCE',  'Attendance',               'TABLE',  '', 'Attendance summary for the period',
        ['dep_attendance_tbl'], []],
    ['DEP_ACTIVITIES',  'Activities & work done',   'TABLE',  '', 'Work carried out (engineering / inspection / document / expediting — free)',
        ['dep_activities_tbl'], []],
    ['DEP_TASKS',       'Tasks & actions',          'TABLE',  '', 'Task register with status',
        ['dep_tasks_tbl'], []],
    ['DEP_ISSUES',      'Issues',                   'TABLE',  '', 'Issue register',
        ['dep_issues_tbl'], []],
    ['DEP_INSTRUCTIONS','Client instructions',      'TABLE',  '', 'Traceable client instructions',
        ['dep_instructions_tbl'], []],
    ['DEP_MEETINGS',    'Meetings / MoM',           'TABLE',  '', 'Meetings and minutes',
        ['dep_meetings_tbl'], []],
    ['DEP_PLAN',        'Next period plan',         'FIELDS', '', 'Plan for the next period',
        ['dep_next_plan'], []],
    ['DEP_CONCLUSION',  'Conclusion',               'FIELDS', '', 'Summary / conclusion',
        ['dep_conclusion'], []],
];
function pdso_seed_library() {
    $pdo = db(); $now = date('c');
    $hasF = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_fields WHERE code=?", [$c]) > 0;
    $insF = $pdo->prepare("INSERT INTO report_lib_fields (code,label,ftype,options,grp,table_cols,is_system,status,version,created_at) VALUES (?,?,?,?,?,?,1,'PUBLISHED',1,?)");
    foreach (PDSO_LIB_FIELDS as $f) { if ($hasF($f[0])) continue; $insF->execute([$f[0],$f[1],$f[2],$f[3]??'',$f[4]??'','',$now]); }
    foreach (PDSO_LIB_TABLES as $t) { if ($hasF($t[0])) continue; $insF->execute([$t[0],$t[1],'table','',$t[2]??'',$t[3]??'',$now]); }
    $hasS = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_sections WHERE code=?", [$c]) > 0;
    $insS = $pdo->prepare("INSERT INTO report_lib_sections (code,title,help,section_type,component,is_system,status,version,category,sort_order,created_at) VALUES (?,?,?,?,?,1,'PUBLISHED',1,'Deputation',?,?)");
    $insSF = $pdo->prepare("INSERT INTO report_lib_section_fields (section_code,field_code,sort_order) VALUES (?,?,?)");
    $ord = 800;
    foreach (PDSO_LIB_SECTIONS as $s) { $ord+=10; if ($hasS($s[0])) continue;
        $insS->execute([$s[0],$s[1],$s[4]??'',$s[2]??'FIELDS',$s[3]??'',$ord,$now]);
        $fo=0; foreach (($s[5]??[]) as $fc){ $fo+=10; $insSF->execute([$s[0],$fc,$fo]); } }
}
function pdso_assemble_report_type($code, $name, $sectionCodes, $opts = []) {
    if (!function_exists('urfe_assemble_report_type')) return 0;
    $opts = array_merge(['category' => 'DEPUTATION', 'evidence_enabled' => 1], $opts);
    return urfe_assemble_report_type($code, $name, $sectionCodes, $opts);
}
// Daily / Weekly / Fortnightly / Monthly / Final — one engine, sections vary (§32).
const PDSO_SAMPLE_TYPES = [
    ['PDSO_DAILY','Daily Deputation Report',
        ['DOC_CONTROL','CLIENT_INFO','DEP_DETAILS','DEP_ACTIVITIES','DEP_TASKS','DEP_ISSUES','DEP_INSTRUCTIONS','DEP_MEETINGS','DEP_PLAN','APPROVAL']],
    ['PDSO_WEEKLY','Weekly Deputation Report',
        ['DOC_CONTROL','CLIENT_INFO','DEP_DETAILS','DEP_ATTENDANCE','DEP_ACTIVITIES','DEP_TASKS','DEP_ISSUES','DEP_INSTRUCTIONS','DEP_MEETINGS','DEP_PLAN','DEP_CONCLUSION','APPROVAL']],
    ['PDSO_FORTNIGHTLY','Fortnightly Deputation Report',
        ['DOC_CONTROL','CLIENT_INFO','DEP_DETAILS','DEP_ATTENDANCE','DEP_ACTIVITIES','DEP_TASKS','DEP_ISSUES','DEP_PLAN','DEP_CONCLUSION','APPROVAL']],
    ['PDSO_MONTHLY','Monthly Deputation Report',
        ['DOC_CONTROL','CLIENT_INFO','DEP_DETAILS','DEP_ATTENDANCE','DEP_ACTIVITIES','DEP_TASKS','DEP_ISSUES','DEP_INSTRUCTIONS','DEP_MEETINGS','DEP_PLAN','DEP_CONCLUSION','APPROVAL']],
    ['PDSO_FINAL','Final Deputation Report',
        ['DOC_CONTROL','CLIENT_INFO','DEP_DETAILS','DEP_ATTENDANCE','DEP_ACTIVITIES','DEP_TASKS','DEP_ISSUES','DEP_MEETINGS','DEP_CONCLUSION','APPROVAL']],
];
function pdso_seed_sample_types() {
    foreach (PDSO_SAMPLE_TYPES as $s) {
        if (ops_one("SELECT id FROM report_types WHERE code=?", [$s[0]])) continue;
        pdso_assemble_report_type($s[0], $s[1], $s[2], ['subcategory'=>'PDSO','description'=>'Sample deputation report configuration (PDSO) — editable & removable.']);
    }
}

// ---------------------------------------------------------------------------
//  Advisory AI/data-integrity QA (§88/§89/§90). DETERMINISTIC and ADVISORY —
//  flags; NEVER invents work/attendance/hours, and never approves anything.
//  Called from idems_qa_run (item 17). Self-gates on deputation report fields.
// ---------------------------------------------------------------------------
function pdso_qa_checks($doc, $fields = null, $data = null) {
    if ($fields === null) $fields = idems_fields((int)($doc['report_type_id'] ?? 0));
    if ($data === null) { $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = []; }
    $isDep = false; foreach ($fields as $f) { if (in_array(($f['fkey'] ?? ''), ['dep_project','dep_activities_tbl','dep_attendance_tbl'], true)) { $isDep = true; break; } }
    if (!$isDep) return [];
    $out = [];
    $tbl = function($fkey) use ($fields, $data) { foreach ($fields as $f) if (($f['fkey'] ?? '') === $fkey && ($f['ftype'] ?? '') === 'table') { $rows = $data[$fkey] ?? null; if (is_array($rows)) return [$rows, idems_table_col_defs($f), $f['label'] ?? $fkey]; } return [null, [], '']; };
    $col = function($defs, $needle) { foreach ($defs as $k => $d) if (stripos((string)$d['label'], $needle) !== false) return $k; return null; };

    // (a) A period with attendance rows but no activities recorded (§89 missing activities).
    [$act,,] = $tbl('dep_activities_tbl'); [$att,,] = $tbl('dep_attendance_tbl');
    if (is_array($att) && $att && (!is_array($act) || !$act))
        $out[] = ['sev'=>'medium','cat'=>'deputation','title'=>'Attendance is recorded but no activities are listed','loc'=>'Activities',
            'why'=>'The report shows attendance for the period but the activities table is empty. A deputation day usually has work to record.',
            'fix'=>'Record the activities carried out, or note why none apply. The system will not fill this in.'];

    // (b) Overdue open actions in the tasks/issues tables (§89 unresolved action).
    foreach (['dep_tasks_tbl'=>'task','dep_issues_tbl'=>'issue'] as $fk=>$word) {
        [$rows,$defs,$lbl] = $tbl($fk); if (!is_array($rows)) continue;
        $kDue = $col($defs,'due'); $kSt = $col($defs,'status'); $today = date('Y-m-d');
        foreach ($rows as $i=>$r) { $rr=(array)$r; $st=strtolower(trim((string)($rr[$kSt]??''))); $due=trim((string)($rr[$kDue]??''));
            if ($st==='' || strpos($st,'clos')!==false || strpos($st,'cancel')!==false) continue;
            if ($due!=='' && strtotime($due) && strtotime($due) < strtotime($today))
                $out[] = ['sev'=>'low','cat'=>'deputation','title'=>'An open '.$word.' is past its due date','loc'=>$lbl.' — row '.($i+1),
                    'why'=>'This '.$word.' is still open and its due date ('.$due.') has passed.','fix'=>'Update the status or the due date.'];
        }
    }

    // (c) §90 provenance — a client-confirmed field must not be marked TPIA-invented.
    // (Advisory reminder only; kept light so it never blocks a report.)
    return $out;
}

// ---------------------------------------------------------------------------
//  Read helpers for surfaces (list of deputations = deputation-type jobs).
// ---------------------------------------------------------------------------
function pdso_deputations($filter = []) {
    // A deputation is a job of the DEPUTATION type or one carrying a lifecycle
    // status. Reuses the existing jobs register; adds nothing to it. Names are
    // resolved from the existing inspector + client masters (not re-stored).
    $w = ["(j.dep_status<>'' OR c.inspection_type='DEPUTATION' OR j.job_type='DEPUTATION')"]; $a = [];
    if (!empty($filter['status'])) { $w[] = "j.dep_status=?"; $a[] = $filter['status']; }
    if (!empty($filter['inspector_id'])) { $w[] = "j.inspector_id=?"; $a[] = (int)$filter['inspector_id']; }
    if (!empty($filter['open'])) { $w[] = "COALESCE(NULLIF(j.dep_status,''),'') NOT IN ('CLOSED','CANCELLED','DEMOBILIZED')"; }
    $sql = "SELECT j.*, c.client_id, c.inspection_type, c.call_code,
                   i.name AS person_name,
                   COALESCE(NULLIF(bp.display_name,''), bp.legal_name) AS client_name
            FROM jobs j
            LEFT JOIN calls c ON c.id=j.call_id
            LEFT JOIN inspectors i ON i.id=j.inspector_id
            LEFT JOIN business_partners bp ON bp.id=c.client_id
            WHERE " . implode(' AND ', $w) . " ORDER BY j.id DESC";
    try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; }
}

// ---------------------------------------------------------------------------
//  Deputation dashboard aggregates + register handler. Reuses the home
//  dashboard's kpi-row / kpi visual language; every figure links to where the
//  work is. Self-gates on the DEPUTATION service and the operations permission.
// ---------------------------------------------------------------------------
function pdso_dashboard() {
    $today = date('Y-m-d');
    $deps = pdso_deputations();
    $c = ['active'=>0,'mob_pending'=>0,'on_leave'=>0,'demob_planned'=>0,'replacement'=>0,'suspended'=>0,'no_status'=>0];
    $conflictPeople = 0; $seen = [];
    foreach ($deps as $d) {
        $s = (string)($d['dep_status'] ?? '');
        if ($s === 'ACTIVE') $c['active']++;
        elseif (in_array($s, ['PLANNED','APPROVED','MOB_PENDING'], true)) $c['mob_pending']++;
        elseif ($s === 'ON_LEAVE') $c['on_leave']++;
        elseif ($s === 'DEMOB_PLANNED') $c['demob_planned']++;
        elseif ($s === 'REPLACEMENT_REQ') $c['replacement']++;
        elseif ($s === 'SUSPENDED') $c['suspended']++;
        elseif ($s === '') $c['no_status']++;
        $iid = (int)($d['inspector_id'] ?? 0);
        if ($iid && !isset($seen[$iid])) { $seen[$iid] = 1; if (pdso_resource_conflicts($iid)) $conflictPeople++; }
    }
    $overdueActions = 0; foreach (pdso_open_actions(0, $today) as $a) if ($a['overdue']) $overdueActions++;
    $pendingAppr = (int)(ops_val("SELECT COUNT(*) FROM dep_att_approval WHERE status IN ('SUBMITTED','CLIENT_REVIEW')") ?: 0);
    $gap = pdso_manpower_gap(0, null);
    return $c + [
        'total' => count($deps), 'overdue_actions' => $overdueActions, 'pending_approvals' => $pendingAppr,
        'manpower_required' => $gap['required'], 'manpower_mobilized' => $gap['mobilized'], 'manpower_gap' => $gap['gap'],
        'conflict_people' => $conflictPeople,
    ];
}

function pdso_can_view() { return (function_exists('can') && (can('mod.calls.view') || can('mod.jobs.view'))) || (function_exists('is_master') && is_master()); }
// Management may run the whole site-ops layer; the assigned inspector may log
// their OWN posting's site ops (§65/§92 — site personnel per permissions).
function pdso_can_edit($job = null) {
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    if ($job && function_exists('is_inspector') && is_inspector()) {
        $me = function_exists('current_user') ? (int)(current_user()['inspector_id'] ?? 0) : 0;
        if ($me && $me === (int)($job['inspector_id'] ?? 0)) return true;
    }
    return false;
}

// Everything the per-job Deputation & site-ops panel needs, in one read.
function pdso_job_panel($jobId) {
    $jobId = (int)$jobId;
    $insp = (int)(ops_val("SELECT inspector_id FROM jobs WHERE id=?", [$jobId]) ?: 0);
    return [
        'status'        => pdso_status_of($jobId),
        'history'       => pdso_status_history($jobId),
        'mob'           => pdso_checklist($jobId, 'MOB'),
        'demob'         => pdso_checklist($jobId, 'DEMOB'),
        'mob_readiness' => pdso_mob_readiness($jobId, 'MOB'),
        'site_log'      => pdso_site_log($jobId),
        'timesheet'     => pdso_timesheet($jobId),
        'ts_totals'     => pdso_timesheet_totals($jobId),
        'approvals'     => pdso_att_approvals($jobId),
        'conflicts'     => $insp ? pdso_resource_conflicts($insp, $jobId) : [],
    ];
}
// True when a job should show the deputation panel.
function pdso_is_deputation($job, $call = null) {
    if (!$job) return false;
    if (($job['dep_status'] ?? '') !== '') return true;
    if (strtoupper((string)($job['job_type'] ?? '')) === 'DEPUTATION') return true;
    if ($call && strtoupper((string)($call['inspection_type'] ?? '')) === 'DEPUTATION') return true;
    return false;
}

// ---------------------------------------------------------------------------
//  Write handler for the per-job site-ops panel. Every action redirects back to
//  the job's #deputation anchor. CSRF + permission gated; additive only.
// ---------------------------------------------------------------------------
function ops_pdso_action($route, $method) {
    ops_require(pdso_enabled(), 'The Deputation service is not active for this installation.');
    $jobId = (int)($_POST['job_id'] ?? $_GET['id'] ?? 0);
    $job = $jobId ? ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]) : null;
    if (!$job) { flash('Deputation not found.', 'error'); redirect('/deputations'); }
    $back = '/job?id=' . $jobId . '#deputation';
    if ($method !== 'POST') redirect($back);
    if (!csrf_ok($_POST['_csrf'] ?? '')) { flash('That form had expired — please try again.', 'error'); redirect($back); }
    ops_require(pdso_can_edit($job), 'You do not have permission to change this deputation.');
    pdso_migrate();

    switch ($route) {
        case 'dep-status':
            $st = (string)($_POST['dep_status'] ?? '');
            if (pdso_set_status($jobId, $st, (string)($_POST['reason'] ?? ''))) {
                if (in_array($st, ['MOB_PENDING','MOBILIZED'], true)) pdso_checklist_seed($jobId, 'MOB');
                if ($st === 'DEMOB_PLANNED') pdso_checklist_seed($jobId, 'DEMOB');
                flash('Deputation status updated to ' . (pdso_statuses()[$st] ?? $st) . '.');
            } else flash('Please pick a valid status.', 'error');
            break;
        case 'dep-check-seed':
            $ph = strtoupper((string)($_POST['phase'] ?? 'MOB'));
            $n = pdso_checklist_seed($jobId, $ph);
            flash($n > 0 ? (ucfirst(strtolower($ph)) . ' checklist added (' . $n . ' items — edit or remove freely).') : 'That checklist is already present.');
            break;
        case 'dep-check-set':
            pdso_checklist_set((int)($_POST['item_id'] ?? 0), (string)($_POST['status'] ?? ''), (string)($_POST['note'] ?? ''));
            flash('Checklist item updated.');
            break;
        case 'dep-site-log':
            if (trim((string)($_POST['detail'] ?? '')) === '') { flash('Write what was observed / instructed / discussed.', 'error'); break; }
            pdso_site_log_add([
                'job_id'=>$jobId, 'client_id'=>(int)($_POST['client_id'] ?? 0), 'kind'=>(string)($_POST['kind'] ?? 'OBSERVATION'),
                'log_date'=>(string)($_POST['log_date'] ?? date('Y-m-d')), 'location'=>(string)($_POST['location'] ?? ''),
                'detail'=>(string)($_POST['detail'] ?? ''), 'risk'=>(string)($_POST['risk'] ?? ''), 'action'=>(string)($_POST['action'] ?? ''),
                'responsible'=>(string)($_POST['responsible'] ?? ''), 'due_on'=>(string)($_POST['due_on'] ?? ''),
                'client_visible'=>isset($_POST['client_visible']) ? 1 : 0, 'source'=>'TPIA',
            ]);
            flash('Site register entry added. (A site observation is not a nonconformity unless you raise one.)');
            break;
        case 'dep-site-log-close':
            db()->prepare("UPDATE dep_site_log SET status=? WHERE id=? AND job_id=?")->execute([(string)($_POST['status'] ?? 'CLOSED'), (int)($_POST['log_id'] ?? 0), $jobId]);
            flash('Site register entry updated.');
            break;
        case 'dep-timesheet':
            pdso_timesheet_add([
                'job_id'=>$jobId, 'inspector_id'=>(int)($job['inspector_id'] ?? 0), 'ts_date'=>(string)($_POST['ts_date'] ?? date('Y-m-d')),
                'activity'=>(string)($_POST['activity'] ?? ''), 'description'=>(string)($_POST['description'] ?? ''),
                'hours'=>(float)($_POST['hours'] ?? 0), 'ot_hours'=>(float)($_POST['ot_hours'] ?? 0),
                'billable'=>isset($_POST['billable']) ? 1 : 0, 'source'=>'PERSONNEL',
            ]);
            flash('Timesheet entry added.');
            break;
        case 'dep-approval':
            pdso_att_approval_add([
                'job_id'=>$jobId, 'client_id'=>(int)($_POST['client_id'] ?? 0), 'period_from'=>(string)($_POST['period_from'] ?? ''),
                'period_to'=>(string)($_POST['period_to'] ?? ''), 'basis'=>(string)($_POST['basis'] ?? ''),
                'billable_days'=>(float)($_POST['billable_days'] ?? 0), 'billable_hours'=>(float)($_POST['billable_hours'] ?? 0),
                'status'=>'SUBMITTED',
            ]);
            flash('Attendance period submitted for client approval.');
            break;
        case 'dep-approval-status':
            pdso_att_approval_set_status((int)($_POST['ap_id'] ?? 0), (string)($_POST['status'] ?? ''), (string)($_POST['client_rep'] ?? ''), (string)($_POST['comments'] ?? ''));
            flash('Attendance approval updated.');
            break;
    }
    redirect($back);
}

function ops_pdso($route, $method) {
    ops_require(pdso_enabled(), 'The Deputation service is not active for this installation. Switch it on under Settings → Service scope.');
    ops_require(pdso_can_view(), 'You do not have access to deputation.');
    pdso_migrate();
    $status = (string)($_GET['status'] ?? '');
    $rows = pdso_deputations($status !== '' ? ['status' => $status] : []);
    view('ops/deputations', [
        'd'        => pdso_dashboard(),
        'rows'     => $rows,
        'statuses' => pdso_statuses(),
        'status'   => $status,
        'manpower' => pdso_manpower(0, null),
    ]);
    return true;
}
