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

    // Release links on report_docs (§12/§63) — which inspection this release is
    // based on, and its aggregate readiness snapshot. Additive, nullable.
    if (function_exists('ensure_column')) {
        ensure_column('report_docs', 'release_of_id', 'INT NULL');       // primary inspection this release is based on
        ensure_column('report_docs', 'release_disposition', "VARCHAR(20) DEFAULT ''");
    }

    if (function_exists('setting_get') && !setting_get('urade_rules_seeded_v1', '')) {
        try { urade_seed_rules(); } catch (Throwable $e) { /* never break login/migrate */ }
        if (function_exists('setting_set')) setting_set('urade_rules_seeded_v1', '1');
    }
    // Release sections/fields into the URFE Library (needs urfe_migrate first).
    if (function_exists('setting_get') && !setting_get('urade_library_seeded_v1', '')) {
        try { urade_seed_release_library(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('urade_library_seeded_v1', '1');
    }
    if (function_exists('setting_get') && !setting_get('urade_samples_seeded_v1', '')) {
        try { urade_seed_sample_release_types(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('urade_samples_seeded_v1', '1');
    }
}

// ---------------------------------------------------------------------------
//  Release additions to the URFE LIBRARY — so release document types assemble
//  from the same Library. Select fields reference the release masters via
//  lookup:<key>. [code,label,ftype,options,grp] / tables [code,label,grp,cols].
// ---------------------------------------------------------------------------
const URADE_LIB_FIELDS = [
    ['release_type',      'Release type',        'select',      'lookup:release_type',        'Release'],
    ['release_date',      'Release date',        'date',        '',                            'Release'],
    ['disposition',       'Disposition',         'select',      'lookup:release_disposition', 'Release'],
    ['release_basis',     'Release basis',       'multiselect', 'lookup:release_basis',       'Release'],
    ['released_qty',      'Released quantity',   'number',      '',                            'Release'],
    ['restrictions',      'Restrictions',        'multiselect', 'lookup:release_restriction', 'Release'],
    ['release_statement', 'Release statement',   'textarea',    '',                            'Release'],
    ['authorized_by',     'Authorized by',       'text',        '',                            'Release'],
];
const URADE_LIB_TABLES = [
    ['release_items',    'Release items', 'Release',
        "Item / description\nSerial / tag\nQty presented\nQty accepted\nQty released\nStatus|select|lookup:release_disposition\nConditions\nRemarks"],
    ['evidence_matrix',  'Evidence matrix', 'Release',
        "Requirement\nReference\nEvidence type\nEvidence no.\nDate|date\nResult\nStatus\nRemarks"],
    ['release_checklist','Release readiness checklist', 'Release',
        "Check\nRequirement\nStatus|select|Completed,Pending,Failed,Waived,N/A\nEvidence\nRemarks"],
    ['rel_conditions',   'Release conditions', 'Release',
        "Condition\nReference\nReason\nResponsible\nDue|date\nStatus|select|Open,In Progress,Closed\nEvidence\nVerified by"],
    ['rel_exceptions',   'Release exceptions', 'Release',
        "Exception\nRequirement\nReason\nRisk|select|Low,Medium,High\nDisposition\nApproval\nResponsible\nDue|date\nStatus"],
];
const URADE_LIB_SECTIONS = [
    ['REL_DETAILS',     'Release details',        'FIELDS', '', 'Type, date, disposition and basis',
        ['release_type','release_date','disposition','release_basis'], []],
    ['REL_ITEMS',       'Release items',          'TABLE',  '', 'Item-level release with per-item disposition',
        ['release_items'], []],
    ['REL_EVIDENCE',    'Evidence matrix',        'TABLE',  '', 'What each release decision is based on',
        ['evidence_matrix'], []],
    ['REL_CHECKLIST',   'Release readiness checklist','TABLE','', 'The mandatory conditions and their status',
        ['release_checklist'], []],
    ['REL_CONDITIONS',  'Release conditions',     'TABLE',  '', 'What must still happen (conditional release)',
        ['rel_conditions'], []],
    ['REL_RESTRICTIONS','Restrictions',           'FIELDS', '', 'What the release does NOT authorize',
        ['restrictions'], []],
    ['REL_EXCEPTIONS',  'Exceptions',             'TABLE',  '', 'Accepted exceptions with risk & approval',
        ['rel_exceptions'], []],
    ['REL_DISPOSITION', 'Disposition & authorization','FIELDS','', 'The decision, released quantity and authority',
        ['disposition','released_qty','authorized_by','release_statement'], []],
];
function urade_seed_release_library() {
    $pdo = db(); $now = date('c');
    $hasF = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_fields WHERE code=?", [$c]) > 0;
    $insF = $pdo->prepare("INSERT INTO report_lib_fields (code,label,ftype,options,grp,table_cols,is_system,status,version,created_at) VALUES (?,?,?,?,?,?,1,'PUBLISHED',1,?)");
    foreach (URADE_LIB_FIELDS as $f) { if ($hasF($f[0])) continue; $insF->execute([$f[0],$f[1],$f[2],$f[3]??'',$f[4]??'','',$now]); }
    foreach (URADE_LIB_TABLES as $t) { if ($hasF($t[0])) continue; $insF->execute([$t[0],$t[1],'table','',$t[2]??'',$t[3]??'',$now]); }
    $hasS = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_sections WHERE code=?", [$c]) > 0;
    $insS = $pdo->prepare("INSERT INTO report_lib_sections (code,title,help,section_type,component,is_system,status,version,category,sort_order,created_at) VALUES (?,?,?,?,?,1,'PUBLISHED',1,'Release',?,?)");
    $insSF = $pdo->prepare("INSERT INTO report_lib_section_fields (section_code,field_code,sort_order) VALUES (?,?,?)");
    $ord = 400;
    foreach (URADE_LIB_SECTIONS as $s) { $ord+=10; if ($hasS($s[0])) continue;
        $insS->execute([$s[0],$s[1],$s[4]??'',$s[2]??'FIELDS',$s[3]??'',$ord,$now]);
        $fo=0; foreach (($s[5]??[]) as $fc){ $fo+=10; $insSF->execute([$s[0],$fc,$fo]); } }
}

// Assemble a release document type from the Library (§47 — one engine, many
// release document formats). Returns the report type id.
function urade_assemble_release_type($code, $name, $sectionCodes, $opts = []) {
    $opts = array_merge(['category' => 'RELEASE', 'finding_enabled' => 0, 'evidence_enabled' => 1], $opts);
    return urfe_assemble_report_type($code, $name, $sectionCodes, $opts);
}

// The §79 sample release configurations — each a Library assembly of the SAME
// engine, differing only by applicable sections. Removable by the admin.
const URADE_SAMPLE_TYPES = [
    ['URD_RN',   'Release Note (URADE)',
        ['DOC_CONTROL','CLIENT_INFO','VENDOR_INFO','PROJECT_INFO','REL_DETAILS','SCOPE','REFERENCES','REL_ITEMS','REL_EVIDENCE','REL_CHECKLIST','REL_RESTRICTIONS','REL_DISPOSITION','CONCLUSION','APPROVAL']],
    ['URD_RC',   'Release Certificate',
        ['DOC_CONTROL','CLIENT_INFO','VENDOR_INFO','PROJECT_INFO','REL_DETAILS','REL_ITEMS','REL_DISPOSITION','APPROVAL']],
    ['URD_PART', 'Partial Release Note',
        ['DOC_CONTROL','CLIENT_INFO','VENDOR_INFO','PROJECT_INFO','REL_DETAILS','SCOPE','REL_ITEMS','REL_EVIDENCE','REL_CHECKLIST','REL_CONDITIONS','REL_RESTRICTIONS','REL_DISPOSITION','CONCLUSION','APPROVAL']],
    ['URD_COND', 'Conditional Release Note',
        ['DOC_CONTROL','CLIENT_INFO','VENDOR_INFO','PROJECT_INFO','REL_DETAILS','SCOPE','REL_ITEMS','REL_EVIDENCE','REL_CHECKLIST','REL_CONDITIONS','REL_EXCEPTIONS','REL_RESTRICTIONS','REL_DISPOSITION','CONCLUSION','APPROVAL']],
    ['URD_FINAL','Final Acceptance Certificate',
        ['DOC_CONTROL','CLIENT_INFO','VENDOR_INFO','PROJECT_INFO','REL_DETAILS','SCOPE','REFERENCES','REL_ITEMS','REL_EVIDENCE','REL_DISPOSITION','CONCLUSION','APPROVAL']],
    ['URD_SITE', 'Site Acceptance Certificate',
        ['DOC_CONTROL','CLIENT_INFO','PROJECT_INFO','REL_DETAILS','SCOPE','REL_CHECKLIST','REL_CONDITIONS','REL_DISPOSITION','INSP_OUTSTANDING','CONCLUSION','APPROVAL']],
    ['URD_SERV', 'Service Acceptance Certificate',
        ['DOC_CONTROL','CLIENT_INFO','PROJECT_INFO','REL_DETAILS','SCOPE','REL_CHECKLIST','REL_DISPOSITION','CONCLUSION','APPROVAL']],
];
function urade_seed_sample_release_types() {
    foreach (URADE_SAMPLE_TYPES as $s) {
        if (ops_one("SELECT id FROM report_types WHERE code=?", [$s[0]])) continue;
        urade_assemble_release_type($s[0], $s[1], $s[2], ['subcategory'=>'URADE','description'=>'Sample release configuration (URADE) — editable & removable.']);
    }
}

// ---------------------------------------------------------------------------
//  Consume inspection data (§12/§22/§69). Build the eligibility context from a
//  linked Phase-2 inspection report WITHOUT re-entering data. Deterministic.
// ---------------------------------------------------------------------------
function urade_context_from_inspection($inspDoc) {
    if (!$inspDoc) return [];
    $fields = idems_fields((int)($inspDoc['report_type_id'] ?? 0));
    $data = is_array($inspDoc['data'] ?? null) ? $inspDoc['data'] : (json_decode($inspDoc['data'] ?? '[]', true) ?: []);
    $num = fn($x)=>($x===''||$x===null)?null:(float)preg_replace('/[^0-9.\-]/','',(string)$x);
    $ctx = [];
    $st = strtoupper((string)($inspDoc['status'] ?? ''));
    $ctx['inspection_complete'] = (!empty($inspDoc['finalized']) || in_array($st, ['ISSUED','APPROVED','COMPLETED'], true)) ? 1 : 0;
    $ctx['inspection_result'] = (string)($data['inspection_result'] ?? ($inspDoc['result'] ?? ''));
    $ctx['accepted_qty'] = $num($data['accepted_qty'] ?? null);
    // Failed tests from a test_results table; missing docs from documents_reviewed.
    $failish = fn($v)=>($v!=='' && (stripos($v,'fail')!==false || stripos($v,'reject')!==false));
    $tf=0; $tt=0; $dm=0;
    foreach ($fields as $f) {
        if (($f['ftype'] ?? '') !== 'table') continue;
        $rows = $data[$f['fkey']] ?? null; if (!is_array($rows)) continue;
        $defs = idems_table_col_defs($f);
        $col = function($needle) use ($defs){ foreach ($defs as $k=>$d) if (stripos((string)$d['label'],$needle)!==false) return $k; return null; };
        if (($f['fkey'] ?? '') === 'test_results' || $col('test')!==null && $col('result')!==null && stripos((string)($f['label']??''),'test')!==false) {
            $kr=$col('result'); foreach ($rows as $r){ $rr=(array)$r; $v=trim((string)($rr[$kr]??'')); if($v==='')continue; $tt++; if($failish($v))$tf++; }
        }
        if (($f['fkey'] ?? '') === 'documents_reviewed' || (stripos((string)($f['label']??''),'document')!==false && $col('status')!==null)) {
            $ks=$col('status'); foreach ($rows as $r){ $rr=(array)$r; $v=strtolower(trim((string)($rr[$ks]??''))); if($v==='')continue; if(strpos($v,'missing')!==false||strpos($v,'not submitted')!==false)$dm++; }
        }
    }
    $ctx['tests_failed']=$tf; $ctx['tests_total']=$tt; $ctx['docs_missing']=$dm;
    // Open NCRs linked to this inspection report (reuse the NCR register).
    $iid = (int)($inspDoc['id'] ?? 0);
    if ($iid) {
        try {
            $ctx['ncr_critical_open'] = (int)ops_val("SELECT COUNT(*) FROM nonconformities WHERE report_doc_id=? AND status<>'CLOSED' AND UPPER(severity)='CRITICAL'", [$iid]);
            $ctx['ncr_major_open']    = (int)ops_val("SELECT COUNT(*) FROM nonconformities WHERE report_doc_id=? AND status<>'CLOSED' AND UPPER(severity)='MAJOR'", [$iid]);
        } catch (Throwable $e) {}
    }
    return $ctx;
}

// Full eligibility for a release document — merges the linked inspection context
// with the release's own released_qty, then evaluates the rules. §12/§55/§63.
function urade_release_eligibility($releaseDoc) {
    $data = is_array($releaseDoc['data'] ?? null) ? $releaseDoc['data'] : (json_decode($releaseDoc['data'] ?? '[]', true) ?: []);
    $ctx = [];
    $insId = (int)($releaseDoc['release_of_id'] ?? 0);
    if ($insId) { $insp = ops_one("SELECT * FROM report_docs WHERE id=?", [$insId]); if ($insp) $ctx = urade_context_from_inspection($insp); }
    // Release data can override / supply context directly too.
    foreach (['inspection_result','accepted_qty','tests_failed','docs_missing','ncr_critical_open','ncr_major_open','inspection_complete'] as $k)
        if (array_key_exists($k, $data) && $data[$k] !== '') $ctx[$k] = $data[$k];
    if (array_key_exists('released_qty', $data) && $data['released_qty'] !== '') $ctx['released_qty'] = (float)preg_replace('/[^0-9.\-]/','',(string)$data['released_qty']);
    $rt = (string)($data['release_type'] ?? '');
    return urade_eligibility($ctx, $rt, (int)($releaseDoc['client_id'] ?? 0) ?: null);
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
