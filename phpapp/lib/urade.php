<?php
// ===========================================================================
//  URADE — Universal Release, Acceptance & Disposition Engine  (Phase 3)
// ---------------------------------------------------------------------------
//  A release is a CONTROLLED CONSEQUENCE of inspection/verification evidence,
//  not another report template. URADE consumes the locked Phase 1 (URFE) and
//  Phase 2 (UIRE) foundations and the app's existing Release Note (RN/IRN,
//  release_status, idems_released_line_items) — it adds only the genuinely-new
//  decision layer:
//
//    • a configurable RELEASE ELIGIBILITY ENGINE (rules → Ready / Conditional /
//      Blocked, with per-condition reasons) — the keystone;
//    • Release Type / Disposition / Basis / Restriction masters (lookup engine);
//    • release Library sections so release document types assemble from the
//      Library (Slice 2);
//    • release data-integrity checks feeding the single QA result (Slice 3).
//
//  It NEVER approves/blocks a release by itself and NEVER changes a technical
//  fact — it computes eligibility and surfaces reasons; authorized people decide.
//  Reuses: report_docs/RN, nonconformities (NCR), report_approvals (workflow),
//  signatures, numbering, audit, versioning (frozen at issue), UIRE inspection
//  data. No duplicate masters, no duplicate stores.
// ===========================================================================

// Configurable masters (§5/§26/§7/§28) via the existing lookup engine — reuses
// the Masters admin UI. Distinct from the doc-level release_status the app
// already has (RELEASED/…); disposition is the richer decision vocabulary.
const URADE_RELEASE_TYPES = [
    'INSPECTION'=>'Inspection Release','MATERIAL'=>'Material Release','PRODUCT'=>'Product Release',
    'EQUIPMENT'=>'Equipment Release','MANUFACTURING'=>'Manufacturing Release','PARTIAL'=>'Partial Release',
    'FINAL'=>'Final Release','CONDITIONAL'=>'Conditional Release','PROVISIONAL'=>'Provisional Release',
    'DISPATCH'=>'Dispatch Release','SHIPMENT'=>'Shipment Release','LOADING'=>'Loading Release',
    'SITE_ACCEPT'=>'Site Acceptance','SERVICE_ACCEPT'=>'Service Acceptance','INSTALL_ACCEPT'=>'Installation Acceptance',
    'TEST_ACCEPT'=>'Test Acceptance','FAT_ACCEPT'=>'FAT Acceptance','SAT_ACCEPT'=>'SAT Acceptance',
    'COMPLETION'=>'Completion Acceptance','DOCUMENT'=>'Document Release','PACKAGE'=>'Package Release',
    'LOT'=>'Lot Release','BATCH'=>'Batch Release','QUANTITY'=>'Quantity Release','RE_RELEASE'=>'Re-release','OTHER'=>'Other',
];
const URADE_DISPOSITIONS = [
    'RELEASED'=>'Released','ACCEPTED'=>'Accepted','COND_RELEASED'=>'Conditionally released','COND_ACCEPTED'=>'Conditionally accepted',
    'PARTIAL'=>'Partially released','HELD'=>'Held','REJECTED'=>'Rejected','PENDING'=>'Pending','NOT_ACCEPTED'=>'Not accepted','CANCELLED'=>'Cancelled',
];
const URADE_BASES = [
    'INSPECTION'=>'Inspection','TESTING'=>'Testing','DOC_REVIEW'=>'Document review','CONTRACT'=>'Contractual requirement',
    'CLIENT_ACCEPT'=>'Client acceptance','QUALITY'=>'Quality verification','SITE'=>'Site verification','QUANTITY'=>'Quantity verification',
    'COMPLETION'=>'Completion verification','SERVICE'=>'Service completion','COMBINED'=>'Combined verification','OTHER'=>'Other',
];
const URADE_RESTRICTIONS = [
    'NO_DISPATCH'=>'Not for dispatch','NO_INSTALL'=>'Not for installation','NO_USE'=>'Not for use','NO_PROCESS'=>'Not for further processing',
    'LTD_QTY'=>'Limited quantity','LTD_LOCATION'=>'Limited location','PEND_DOC'=>'Pending document submission','PEND_CAPA'=>'Pending corrective action',
    'CLIENT_APPROVAL'=>'Client approval required','OTHER'=>'Other',
];

// ---------------------------------------------------------------------------
//  Schema — additive only. Release Eligibility rule master.
// ---------------------------------------------------------------------------
function urade_migrate() {
    $pdo = db();
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('release_type',        'Release type',        URADE_RELEASE_TYPES, 'urade');
        lk_ensure_type_map('release_disposition', 'Release disposition', URADE_DISPOSITIONS,  'urade');
        lk_ensure_type_map('release_basis',       'Release basis',       URADE_BASES,         'urade');
        lk_ensure_type_map('release_restriction', 'Release restriction', URADE_RESTRICTIONS,  'urade');
    }
    // Release eligibility rules (§8-11) — the configurable gate. Each rule runs a
    // named deterministic CHECK against a release context; mandatory rules block,
    // rules flagged allow_conditional downgrade a fail to CONDITIONAL (needs a
    // condition + approval) instead of BLOCKED. Applicability by release type /
    // client (blank = all). Admins tune these — nothing is hard-coded in logic.
    $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS release_rules (
        id $pk, code VARCHAR(40), label VARCHAR(200) DEFAULT '', category VARCHAR(40) DEFAULT '',
        check_type VARCHAR(40) DEFAULT '', mandatory INT DEFAULT 1, allow_conditional INT DEFAULT 0,
        applies_types VARCHAR(300) DEFAULT '', client_id INT NULL, param VARCHAR(120) DEFAULT '',
        fail_message VARCHAR(300) DEFAULT '', active INT DEFAULT 1, is_system INT DEFAULT 0, sort_order INT DEFAULT 0,
        created_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('release_rules', 'code');

    if (function_exists('setting_get') && !setting_get('urade_rules_seeded_v1', '')) {
        try { urade_seed_rules(); } catch (Throwable $e) { /* never break login/migrate */ }
        if (function_exists('setting_set')) setting_set('urade_rules_seeded_v1', '1');
    }
}

// Default eligibility rules — sensible policy; the administrator can change every
// flag. [code,label,category,check_type,mandatory,allow_conditional,fail_message]
const URADE_DEFAULT_RULES = [
    ['INSP_COMPLETE', 'Inspection completed',            'Inspection', 'INSPECTION_COMPLETE', 1, 0, 'The inspection is not completed/issued.'],
    ['INSP_ACCEPTED', 'Inspection result acceptable',    'Inspection', 'INSPECTION_ACCEPTED', 1, 1, 'The inspection result is not acceptable.'],
    ['CRIT_NCR',      'No open critical nonconformity',  'Quality',    'CRITICAL_NCR_CLOSED', 1, 0, 'A critical nonconformity is open.'],
    ['MAJOR_NCR',     'No open major nonconformity',     'Quality',    'MAJOR_NCR_CLOSED',    1, 1, 'A major nonconformity is open.'],
    ['TESTS_PASSED',  'Mandatory tests passed',          'Testing',    'TESTS_PASSED',        1, 0, 'A mandatory test has failed or is incomplete.'],
    ['DOCS_COMPLETE', 'Mandatory documents complete',    'Documents',  'DOCS_COMPLETE',       1, 1, 'A mandatory document is missing.'],
    ['QTY_RECONCILE', 'Quantities reconcile',            'Quantity',   'QTY_RECONCILES',      1, 0, 'The release quantity does not reconcile with the accepted quantity.'],
];
function urade_seed_rules() {
    $pdo = db(); $now = date('c');
    $ins = $pdo->prepare("INSERT INTO release_rules (code,label,category,check_type,mandatory,allow_conditional,fail_message,is_system,active,sort_order,created_at) VALUES (?,?,?,?,?,?,?,1,1,?,?)");
    $so = 0;
    foreach (URADE_DEFAULT_RULES as $r) {
        $so += 10;
        if ((int)ops_val("SELECT COUNT(*) FROM release_rules WHERE code=?", [$r[0]]) > 0) continue;
        $ins->execute([$r[0], $r[1], $r[2], $r[3], (int)$r[4], (int)$r[5], $r[6], $so, $now]);
    }
}
function urade_rules($releaseType = null, $clientId = null) {
    $rows = ops_all("SELECT * FROM release_rules WHERE active=1 ORDER BY sort_order, code");
    $t = strtoupper(trim((string)$releaseType));
    return array_values(array_filter($rows, function($r) use ($t, $clientId) {
        $ap = trim((string)($r['applies_types'] ?? ''));
        if ($ap !== '' && $t !== '') { $codes = array_map(fn($x)=>strtoupper(trim($x)), explode(',', $ap)); if (!in_array($t, $codes, true)) return false; }
        if (!empty($r['client_id']) && $clientId !== null && (int)$r['client_id'] !== (int)$clientId) return false;
        return true;
    }));
}

// ---------------------------------------------------------------------------
//  Eligibility CHECK registry — each returns 'PASS' | 'FAIL' | 'NA' from a plain
//  release context $ctx (built from linked inspection + release data). Pure &
//  deterministic; add a check here to extend policy without touching the engine.
//  $ctx keys: inspection_complete(bool), inspection_result(str), tests_failed(int),
//  tests_total(int), docs_missing(int), ncr_critical_open(int), ncr_major_open(int),
//  accepted_qty(num|null), released_qty(num|null), approvals_ok(bool|null).
// ---------------------------------------------------------------------------
function urade_check($type, $ctx) {
    $has = fn($k) => array_key_exists($k, $ctx) && $ctx[$k] !== null && $ctx[$k] !== '';
    switch ($type) {
        case 'INSPECTION_COMPLETE':
            if (!$has('inspection_complete')) return 'NA';
            return !empty($ctx['inspection_complete']) ? 'PASS' : 'FAIL';
        case 'INSPECTION_ACCEPTED':
            if (!$has('inspection_result')) return 'NA';
            $r = strtolower((string)$ctx['inspection_result']);
            if ($r === '') return 'NA';
            $ok = (strpos($r,'accept')!==false || strpos($r,'pass')!==false || strpos($r,'releas')!==false) && strpos($r,'not')===false && strpos($r,'reject')===false;
            return $ok ? 'PASS' : 'FAIL';
        case 'TESTS_PASSED':
            if (!$has('tests_failed') && !$has('tests_total')) return 'NA';
            return ((int)($ctx['tests_failed'] ?? 0) === 0) ? 'PASS' : 'FAIL';
        case 'DOCS_COMPLETE':
            if (!$has('docs_missing')) return 'NA';
            return ((int)$ctx['docs_missing'] === 0) ? 'PASS' : 'FAIL';
        case 'CRITICAL_NCR_CLOSED':
            if (!$has('ncr_critical_open')) return 'NA';
            return ((int)$ctx['ncr_critical_open'] === 0) ? 'PASS' : 'FAIL';
        case 'MAJOR_NCR_CLOSED':
            if (!$has('ncr_major_open')) return 'NA';
            return ((int)$ctx['ncr_major_open'] === 0) ? 'PASS' : 'FAIL';
        case 'QTY_RECONCILES':
            if (!$has('accepted_qty') || !$has('released_qty')) return 'NA';
            return ((float)$ctx['released_qty'] <= (float)$ctx['accepted_qty'] + 0.001) ? 'PASS' : 'FAIL';
        case 'APPROVALS_OBTAINED':
            if (!$has('approvals_ok')) return 'NA';
            return !empty($ctx['approvals_ok']) ? 'PASS' : 'FAIL';
        default:
            return 'NA';
    }
}

// The Eligibility Engine (§8-11, §55-56). Evaluates the applicable rules against a
// release context and returns the overall status + per-condition breakdown + the
// blocking reasons. NEVER decides the release — it computes readiness for a human.
//   status: 'READY' (all mandatory pass), 'CONDITIONAL' (a mandatory fail that
//   policy allows to proceed with a condition + approval), 'BLOCKED' (a mandatory
//   fail with no conditional path).
function urade_eligibility($ctx, $releaseType = null, $clientId = null, $rules = null) {
    if ($rules === null) $rules = urade_rules($releaseType, $clientId);
    $conds = []; $blocked = false; $conditional = false; $counts = ['pass'=>0,'fail'=>0,'na'=>0,'cond'=>0];
    foreach ($rules as $r) {
        $res = urade_check($r['check_type'], $ctx);
        $mand = (int)$r['mandatory'] === 1; $allowCond = (int)$r['allow_conditional'] === 1;
        $state = $res; $reason = '';
        if ($res === 'FAIL') {
            if ($mand && !$allowCond) { $blocked = true; $state = 'BLOCKED'; $reason = (string)$r['fail_message']; }
            elseif ($mand && $allowCond) { $conditional = true; $state = 'CONDITIONAL'; $reason = (string)$r['fail_message'] . ' Conditional release allowed with a recorded condition and approval.'; }
            else { $state = 'FAIL'; $reason = (string)$r['fail_message']; }
        }
        if ($res === 'PASS') $counts['pass']++;
        elseif ($res === 'NA') $counts['na']++;
        elseif ($state === 'CONDITIONAL') $counts['cond']++;
        else $counts['fail']++;
        $conds[] = ['code'=>$r['code'], 'label'=>$r['label'], 'category'=>$r['category'], 'check'=>$r['check_type'],
                    'result'=>$res, 'state'=>$state, 'mandatory'=>$mand, 'allow_conditional'=>$allowCond, 'reason'=>$reason];
    }
    $status = $blocked ? 'BLOCKED' : ($conditional ? 'CONDITIONAL' : 'READY');
    $blockers = array_values(array_filter($conds, fn($c)=>$c['state']==='BLOCKED'));
    $conditions = array_values(array_filter($conds, fn($c)=>$c['state']==='CONDITIONAL'));
    return ['status'=>$status, 'conditions_list'=>$conds, 'blockers'=>$blockers, 'conditional_items'=>$conditions, 'counts'=>$counts];
}
