<?php
// ===========================================================================
//  UIRE — Universal Inspection Reporting Engine  (Phase 2)
// ---------------------------------------------------------------------------
//  An industry-NEUTRAL inspection execution + reporting engine that CONSUMES
//  the locked Phase 1 foundation (URFE) and the app's existing modules. It adds
//  ONLY what is genuinely inspection-specific and reusable; it never duplicates
//  infrastructure that already exists:
//
//    Inspection request      → existing `calls` (call registrar)
//    Assignment / scheduling  → existing `jobs` + scheduling
//    Vendor / client / PO      → existing masters
//    Report type / sections    → URFE library + IDEMS report engine
//    Evidence / photos          → report_files
//    Findings                    → nonconformities (NCR)
//    Actions / CAPA               → capa / capa_actions
//    Risk                          → risk_items
//    Hold / witness points          → hw_points
//    Instruments / calibration       → equipment / report_equipment
//    Sampling                          → sample_items
//    Acceptance criteria / methods      → decision_rules / methods
//    Workflow / approval / signature     → report_approvals / signatures
//    PDF / numbering / audit / versioning → IDEMS engine (frozen at issue)
//
//  What UIRE adds (new + reusable): a configurable Inspection Type master and a
//  reusable Inspection Criteria Library (industry-neutral, applicability-driven,
//  the seed of future criteria packs) that generates checklists into any report
//  type through the URFE library.
// ===========================================================================

// Reference list of universal inspection types (§5). NOTE: the app already ships
// a configurable `inspection_type` master — UIRE reuses that. This constant is
// only a fallback when the master is somehow empty, and documents the universal
// vocabulary UIRE is designed around. code => label.
const UIRE_INSPECTION_TYPES = [
    'PRE'          => 'Pre-Inspection',
    'INCOMING'     => 'Incoming Inspection',
    'MATERIAL'     => 'Material Inspection',
    'IN_PROCESS'   => 'In-Process Inspection',
    'MANUFACTURING'=> 'Manufacturing Inspection',
    'PRODUCT'      => 'Product Inspection',
    'EQUIPMENT'    => 'Equipment Inspection',
    'FINAL'        => 'Final Inspection',
    'PRE_DISPATCH' => 'Pre-Dispatch Inspection',
    'LOADING'      => 'Loading Inspection',
    'SITE'         => 'Site Inspection',
    'INSTALLATION' => 'Installation Inspection',
    'CONSTRUCTION' => 'Construction Inspection',
    'SERVICE'      => 'Service Inspection',
    'PROCESS'      => 'Process Inspection',
    'SURVEILLANCE' => 'Surveillance Inspection',
    'WITNESS'      => 'Witness Inspection',
    'HOLD_POINT'   => 'Hold Point Inspection',
    'REVIEW_POINT' => 'Review Point Inspection',
    'TEST_WITNESS' => 'Test Witness',
    'FAT'          => 'Factory Acceptance Test (FAT)',
    'SAT'          => 'Site Acceptance Test (SAT)',
    'PERFORMANCE'  => 'Performance Test',
    'FUNCTIONAL'   => 'Functional Test',
    'DIMENSIONAL'  => 'Dimensional Inspection',
    'VISUAL'       => 'Visual Inspection',
    'DOCUMENTATION'=> 'Documentation Inspection',
    'QUANTITY'     => 'Quantity Verification',
    'CONDITION'    => 'Condition Inspection',
    'PERIODIC'     => 'Periodic Inspection',
    'REINSPECTION' => 'Re-inspection',
    'FOLLOWUP'     => 'Follow-up Inspection',
    'FINAL_VERIF'  => 'Final Verification',
    'OTHER'        => 'Other',
];

// Inspection checklist response set (§15) and result set (§16) — configurable
// masters, reusing the lookup engine. Result reuses IDEMS' inspection_result.
const UIRE_RESPONSES = [
    'PASS' => 'Pass', 'FAIL' => 'Fail', 'NA' => 'N/A', 'PARTIAL' => 'Partial',
    'OBSERVED' => 'Observed', 'NOT_OBSERVED' => 'Not observed',
];
// Inspection objectives (§11) — multiple selectable per inspection.
const UIRE_OBJECTIVES = [
    'CONFORMITY' => 'Verify conformity', 'QUANTITY' => 'Verify quantity',
    'WORKMANSHIP' => 'Verify workmanship', 'DIMENSIONS' => 'Verify dimensions',
    'DOCUMENTATION' => 'Verify documentation', 'WITNESS_TEST' => 'Witness test',
    'INSTALLATION' => 'Verify installation', 'PROCESS' => 'Verify process',
    'CONDITION' => 'Verify condition', 'IDENTIFICATION' => 'Verify identification',
    'TRACEABILITY' => 'Verify traceability', 'OTHER' => 'Other',
];
// Inspection-point status (§39) — for hold/witness/review points.
const UIRE_POINT_STATUS = [
    'PLANNED' => 'Planned', 'REQUESTED' => 'Requested', 'SCHEDULED' => 'Scheduled',
    'READY' => 'Ready', 'WITNESSED' => 'Witnessed', 'COMPLETED' => 'Completed',
    'ACCEPTED' => 'Accepted', 'REJECTED' => 'Rejected', 'WAIVED' => 'Waived',
    'CANCELLED' => 'Cancelled', 'NA' => 'Not applicable',
];

// ---------------------------------------------------------------------------
//  Default GENERAL criteria pack — industry-NEUTRAL inspection criteria that
//  apply to almost any inspection. Future packs (Welding, NDT, Coating, Civil…)
//  are added as more rows with their own `pack` and applicability — no code.
//  [code, category, requirement, question, acceptance, evidence?, measure?, mandatory?]
// ---------------------------------------------------------------------------
const UIRE_GENERAL_CRITERIA = [
    ['GEN-IDENT',  'Identification',  'Item identification & marking match the order',      'Do markings / tags identify the item per PO & drawing?', 'Marking legible and traceable to the order', 1, 0, 1],
    ['GEN-QTY',    'Quantity',        'Presented quantity agrees with the order',           'Does the presented quantity match the PO / release?',    'Quantity reconciles with the order', 0, 1, 1],
    ['GEN-DOC',    'Documentation',   'Required documents are available and current',       'Are the applicable documents present and of correct revision?', 'All applicable documents available and approved', 1, 0, 1],
    ['GEN-VIS',    'Visual',          'Workmanship & surface condition are acceptable',     'Is the visual condition free of defects/damage?',        'No unacceptable defects, damage or corrosion', 1, 0, 1],
    ['GEN-DIM',    'Dimensional',     'Key dimensions are within drawing tolerance',        'Do measured dimensions meet the drawing tolerance?',     'All checked dimensions within tolerance', 1, 1, 0],
    ['GEN-MTC',    'Traceability',    'Material / test certificates support the item',      'Do MTC / test certificates cover the supplied item?',    'Certificates valid and traceable to the item', 1, 0, 0],
    ['GEN-FUNC',   'Function',        'Functional / operational check is satisfactory',     'Does the item function as required (where testable)?',   'Function verified as required', 1, 0, 0],
    ['GEN-PACK',   'Packing',         'Packing & preservation are fit for purpose',         'Is packing / preservation adequate for handling & transit?', 'Packing meets the specified requirement', 1, 0, 0],
    ['GEN-MARK',   'Marking',         'Shipping / handling marking is complete',            'Are shipping and handling marks complete and correct?',  'Marking complete per requirement', 1, 0, 0],
    ['GEN-SAFE',   'Safety',          'Safety & regulatory requirements are met',           'Are applicable safety / regulatory requirements satisfied?', 'Applicable safety / regulatory requirements met', 0, 0, 0],
    ['GEN-CLEAN',  'Cleanliness',     'Cleanliness is acceptable for the item',             'Is the item clean and free of foreign matter?',          'Cleanliness acceptable', 1, 0, 0],
    ['GEN-COMPLETE','Completeness',   'Scope of supply is complete',                        'Is the scope of supply complete (no missing parts)?',    'Scope complete; no shortages', 0, 0, 0],
];

// ---------------------------------------------------------------------------
//  Schema — additive only. Inspection Type master (lookup) + Criteria Library.
// ---------------------------------------------------------------------------
function uire_migrate() {
    $pdo = db();
    // Inspection Type master (§5): an `inspection_type` master ALREADY EXISTS in
    // the app (used by Calls / CRM / quotes / competence). We REUSE it — we do NOT
    // register a competing list or pollute it. Only the genuinely-new masters
    // (response / objective / point-status) are added, through the same lookup
    // engine so they get the standard Masters admin UI for free.
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('inspection_response',     'Inspection response',        UIRE_RESPONSES,    'uire');
        lk_ensure_type_map('inspection_objective',    'Inspection objective',       UIRE_OBJECTIVES,   'uire');
        lk_ensure_type_map('inspection_point_status', 'Inspection point status',    UIRE_POINT_STATUS, 'uire');
    }
    // Inspection Criteria Library (§13-15) — genuinely new, reusable, packs-ready.
    $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS inspection_criteria (
        id $pk, code VARCHAR(40), pack VARCHAR(40) DEFAULT 'GENERAL', category VARCHAR(60) DEFAULT '',
        requirement VARCHAR(400) DEFAULT '', question VARCHAR(400) DEFAULT '', acceptance VARCHAR(400) DEFAULT '',
        reference VARCHAR(200) DEFAULT '', standard VARCHAR(120) DEFAULT '', clause VARCHAR(60) DEFAULT '',
        applies_types VARCHAR(300) DEFAULT '', applies_vendor_type VARCHAR(120) DEFAULT '', applies_product VARCHAR(120) DEFAULT '',
        evidence_required INT DEFAULT 0, measurement_required INT DEFAULT 0, mandatory INT DEFAULT 0, weight REAL DEFAULT 0,
        response_set VARCHAR(60) DEFAULT 'inspection_response', version INT DEFAULT 1, status VARCHAR(20) DEFAULT 'PUBLISHED',
        is_system INT DEFAULT 0, sort_order INT DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('inspection_criteria', 'code');
    // Criteria pack registry (name/description) — the pack is otherwise just a tag.
    $pdo->exec("CREATE TABLE IF NOT EXISTS inspection_criteria_packs (
        id $pk, code VARCHAR(40), name VARCHAR(120) DEFAULT '', description VARCHAR(400) DEFAULT '',
        discipline VARCHAR(60) DEFAULT '', is_system INT DEFAULT 0, active INT DEFAULT 1, sort_order INT DEFAULT 0)");
    idems_unique_index('inspection_criteria_packs', 'code');

    // Re-inspection linkage (§53) — a NEW inspection that verifies a prior one
    // (distinct from a report revision, which report_docs.revises_id already
    // covers). Additive, nullable — nothing existing is affected.
    if (function_exists('ensure_column')) ensure_column('report_docs', 'reinspects_id', 'INT NULL');

    if (function_exists('setting_get') && !setting_get('uire_criteria_seeded_v1', '')) {
        try { uire_seed_criteria(); } catch (Throwable $e) { /* never break login/migrate */ }
        if (function_exists('setting_set')) setting_set('uire_criteria_seeded_v1', '1');
    }
    // Extend the URFE Library with inspection sections/fields (needs urfe_migrate
    // to have created the library tables first — it runs before us in run_schema).
    if (function_exists('setting_get') && !setting_get('uire_library_seeded_v1', '')) {
        try { uire_seed_inspection_library(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('uire_library_seeded_v1', '1');
    }
    // Seed the sample inspection report configurations (§98) — ready, removable
    // types assembled from the Library. Guarded, so deleting them keeps them gone.
    if (function_exists('setting_get') && !setting_get('uire_samples_seeded_v1', '')) {
        try { uire_seed_sample_inspection_types(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('uire_samples_seeded_v1', '1');
    }
}

// The §98 sample inspection report configurations — each a configuration of the
// SAME engine (proof of §107). Sections differ by applicability, so a Site
// inspection carries no dimensional/quantity sections while Manufacturing does.
// [code, name, [library section codes]]. Removable by the admin.
const UIRE_SAMPLE_TYPES = [
    ['UIR_GEN',  'General Inspection Report',
        ['DOC_CONTROL','CLIENT_INFO','VENDOR_INFO','PROJECT_INFO','INSP_DETAILS','INSP_OBJECTIVES','SCOPE','DOCS_REVIEWED','INSP_ITEMS','INSP_CHECKLIST','FINDINGS','INSP_RESULT','CONCLUSION','INSP_OUTSTANDING','APPROVAL']],
    ['UIR_MATL', 'Material Inspection Report',
        ['DOC_CONTROL','VENDOR_INFO','PROJECT_INFO','INSP_DETAILS','DOCS_REVIEWED','INSP_ITEMS','INSP_QTY','INSP_CHECKLIST','INSP_MEASURE','FINDINGS','INSP_RESULT','CONCLUSION','APPROVAL']],
    ['UIR_MFG',  'Manufacturing Inspection Report',
        ['DOC_CONTROL','VENDOR_INFO','PROJECT_INFO','INSP_DETAILS','INSP_OBJECTIVES','STANDARDS','DOCS_REVIEWED','INSP_ITEMS','INSP_QTY','INSP_CHECKLIST','INSP_MEASURE','INSP_TESTS','INSP_HOLDWITNESS','FINDINGS','INSP_RESULT','CONCLUSION','INSP_OUTSTANDING','APPROVAL']],
    ['UIR_SITE', 'Site Inspection Report',
        ['DOC_CONTROL','CLIENT_INFO','PROJECT_INFO','INSP_DETAILS','INSP_OBJECTIVES','SCOPE','INSP_CHECKLIST','FINDINGS','INSP_RESULT','INSP_OUTSTANDING','CONCLUSION','APPROVAL']],
    ['UIR_FINAL','Final Inspection Report',
        ['DOC_CONTROL','CLIENT_INFO','VENDOR_INFO','PROJECT_INFO','INSP_DETAILS','SCOPE','STANDARDS','DOCS_REVIEWED','INSP_ITEMS','INSP_QTY','INSP_CHECKLIST','INSP_MEASURE','INSP_TESTS','FINDINGS','INSP_RESULT','CONCLUSION','INSP_OUTSTANDING','APPROVAL']],
    ['UIR_TW',   'Test Witness Report',
        ['DOC_CONTROL','VENDOR_INFO','PROJECT_INFO','INSP_DETAILS','STANDARDS','INSP_TESTS','FINDINGS','INSP_RESULT','CONCLUSION','APPROVAL']],
    ['UIR_PDI',  'Pre-Dispatch Inspection Report',
        ['DOC_CONTROL','VENDOR_INFO','PROJECT_INFO','INSP_DETAILS','INSP_ITEMS','INSP_QTY','INSP_CHECKLIST','FINDINGS','INSP_RESULT','CONCLUSION','APPROVAL']],
    ['UIR_SURV', 'Surveillance Report',
        ['DOC_CONTROL','VENDOR_INFO','PROJECT_INFO','INSP_DETAILS','SCOPE','INSP_CHECKLIST','FINDINGS','INSP_OUTSTANDING','CONCLUSION','APPROVAL']],
];
function uire_seed_sample_inspection_types() {
    foreach (UIRE_SAMPLE_TYPES as $s) {
        if (ops_one("SELECT id FROM report_types WHERE code=?", [$s[0]])) continue;
        uire_assemble_inspection_report($s[0], $s[1], $s[2], ['category' => 'INSPECTION', 'subcategory' => 'UIRE', 'description' => 'Sample inspection configuration (URFE/UIRE) — editable & removable.']);
    }
}

// Seed the GENERAL criteria pack (industry-neutral). Idempotent per criterion code.
function uire_seed_criteria() {
    $pdo = db(); $now = date('c');
    if ((int)ops_val("SELECT COUNT(*) FROM inspection_criteria_packs WHERE code='GENERAL'") === 0)
        $pdo->prepare("INSERT INTO inspection_criteria_packs (code,name,description,discipline,is_system,active,sort_order) VALUES ('GENERAL','General inspection','Industry-neutral criteria applicable to almost any inspection','General',1,1,10)")->execute();
    $ins = $pdo->prepare("INSERT INTO inspection_criteria
        (code,pack,category,requirement,question,acceptance,evidence_required,measurement_required,mandatory,weight,is_system,status,version,sort_order,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,1,'PUBLISHED',1,?,?)");
    $so = 0;
    foreach (UIRE_GENERAL_CRITERIA as $c) {
        $so += 10;
        if ((int)ops_val("SELECT COUNT(*) FROM inspection_criteria WHERE code=?", [$c[0]]) > 0) continue;
        $ins->execute([$c[0], 'GENERAL', $c[1], $c[2], $c[3], $c[4], (int)$c[5], (int)$c[6], (int)$c[7], 0, $so, $now]);
    }
}

// ---------------------------------------------------------------------------
//  Inspection additions to the URFE LIBRARY (reuse/extend the locked foundation,
//  never redesign it). Inspection-specific fields, tables and sections become
//  ordinary Library entries — so inspection report types are ASSEMBLED from the
//  same Library that every other report uses. Select fields reference the real
//  masters via `lookup:<key>` (reuse), never a copied list.
//  Field row: [code,label,ftype,options,grp]. Table row: [code,label,grp,cols].
// ---------------------------------------------------------------------------
const UIRE_LIB_FIELDS = [
    ['inspection_type',   'Inspection type',      'select', 'lookup:inspection_type',   'Inspection'],
    ['inspection_date',   'Inspection date',      'date',   '',                          'Inspection'],
    ['inspector',         'Inspector',            'text',   '',                          'Inspection'],
    ['inspection_stage',  'Inspection stage',     'text',   '',                          'Inspection'],
    ['item_desc',         'Item / description',   'text',   '',                          'Inspection'],
    ['tag_no',            'Tag no.',              'text',   '',                          'Inspection'],
    ['serial_no',         'Serial no.',           'text',   '',                          'Inspection'],
    ['heat_no',           'Heat no.',             'text',   '',                          'Inspection'],
    ['batch_no',          'Batch / lot no.',      'text',   '',                          'Inspection'],
    ['inspection_objectives','Objectives',        'multiselect','lookup:inspection_objective','Inspection'],
    ['inspection_result', 'Inspection result',    'select', 'lookup:inspection_result',  'Outcome'],
    // Quantity verification (§20). Balance is a calculated field (configurable).
    ['ordered_qty',       'Ordered qty',          'number', '',                          'Quantity'],
    ['presented_qty',     'Presented qty',        'number', '',                          'Quantity'],
    ['inspected_qty',     'Inspected qty',        'number', '',                          'Quantity'],
    ['accepted_qty',      'Accepted qty',         'number', '',                          'Quantity'],
    ['rejected_qty',      'Rejected qty',         'number', '',                          'Quantity'],
];
const UIRE_LIB_CALCS = [
    // code => [label, calc_expr, grp]
    ['balance_qty', 'Balance qty', 'presented_qty - accepted_qty - rejected_qty', 'Quantity'],
];
const UIRE_LIB_TABLES = [
    ['inspection_items', 'Inspection items', 'Inspection',
        "Item / description\nTag no.\nSerial no.\nBatch / lot\nQuantity\nResult|select|lookup:inspection_result\nRemarks"],
    ['checklist',        'Inspection checklist', 'Inspection',
        "Criterion|merge\nRequirement\nReference\nAcceptance criterion\nResponse|select|lookup:inspection_response\nObservation\nEvidence referred\nAttached|select|Yes,No,N/A\nResult|select|lookup:inspection_result\nRemarks"],
    ['measurements',     'Measurements', 'Measurement',
        "Parameter\nRequirement\nNominal\nMin\nMax\nActual\nUnit|unit\nInstrument\nResult|select|lookup:inspection_response\nRemarks"],
    ['test_results',     'Test results', 'Testing',
        "Test\nProcedure\nRequirement\nExpected\nActual\nResult|select|lookup:inspection_response\nEquipment\nWitness\nRemarks"],
    ['hold_witness',     'Hold / witness points', 'Inspection',
        "Point type|select|Hold,Witness,Review,Surveillance\nRequirement\nNotified|date\nScheduled|date\nActual|date\nWitnessed by\nStatus|select|lookup:inspection_point_status\nResult|select|lookup:inspection_result\nRelease"],
    ['outstanding_items','Outstanding items', 'Outcome',
        "Item\nDescription\nReason\nResponsible\nDue|date\nStatus\nAction"],
];
// Inspection sections added to the Library. [code,title,type,component,help,[field codes],flags]
const UIRE_LIB_SECTIONS = [
    ['INSP_DETAILS',   'Inspection details',    'FIELDS', '', 'Type, date, inspector, item identification',
        ['inspection_type','inspection_date','inspector','inspection_stage','item_desc','tag_no','serial_no'], []],
    ['INSP_OBJECTIVES','Inspection objectives', 'FIELDS', '', 'What the inspection set out to verify',
        ['inspection_objectives'], []],
    ['INSP_ITEMS',     'Inspection items',      'TABLE',  '', 'The items covered by this inspection',
        ['inspection_items'], []],
    ['INSP_QTY',       'Quantity verification', 'FIELDS', '', 'Ordered / presented / inspected / accepted / rejected / balance',
        ['ordered_qty','presented_qty','inspected_qty','accepted_qty','rejected_qty','balance_qty'], []],
    ['INSP_CHECKLIST', 'Inspection checklist',  'TABLE',  '', 'Criteria checked, with response, evidence and result',
        ['checklist'], []],
    ['INSP_MEASURE',   'Measurements',          'TABLE',  '', 'Dimensional / parameter measurements vs tolerance',
        ['measurements'], []],
    ['INSP_TESTS',     'Test results',          'TABLE',  '', 'Witnessed / reviewed test results',
        ['test_results'], []],
    ['INSP_HOLDWITNESS','Hold / witness points','TABLE',  '', 'Hold, witness, review and surveillance points',
        ['hold_witness'], []],
    ['INSP_RESULT',    'Inspection result',     'FIELDS', '', 'Overall inspection disposition',
        ['inspection_result'], []],
    ['INSP_OUTSTANDING','Outstanding items',    'TABLE',  '', 'Items still open after the inspection',
        ['outstanding_items'], []],
];

// Add the inspection fields/tables/sections into the URFE Library (idempotent).
function uire_seed_inspection_library() {
    $pdo = db(); $now = date('c');
    $hasF = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_fields WHERE code=?", [$c]) > 0;
    $insF = $pdo->prepare("INSERT INTO report_lib_fields (code,label,ftype,options,grp,table_cols,calc_expr,is_system,status,version,created_at) VALUES (?,?,?,?,?,?,?,1,'PUBLISHED',1,?)");
    foreach (UIRE_LIB_FIELDS as $f) { if ($hasF($f[0])) continue; $insF->execute([$f[0],$f[1],$f[2],$f[3]??'',$f[4]??'','','',$now]); }
    foreach (UIRE_LIB_CALCS as $c)  { if ($hasF($c[0])) continue; $insF->execute([$c[0],$c[1],'calc','',$c[3]??'','',$c[2]??'',$now]); }
    foreach (UIRE_LIB_TABLES as $t) { if ($hasF($t[0])) continue; $insF->execute([$t[0],$t[1],'table','',$t[2]??'',$t[3]??'','',$now]); }
    $hasS = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_sections WHERE code=?", [$c]) > 0;
    $insS = $pdo->prepare("INSERT INTO report_lib_sections (code,title,help,section_type,component,is_system,status,version,category,sort_order,created_at) VALUES (?,?,?,?,?,1,'PUBLISHED',1,'Inspection',?,?)");
    $insSF = $pdo->prepare("INSERT INTO report_lib_section_fields (section_code,field_code,sort_order) VALUES (?,?,?)");
    $ord = 200;
    foreach (UIRE_LIB_SECTIONS as $s) {
        $ord += 10; if ($hasS($s[0])) continue;
        $insS->execute([$s[0],$s[1],$s[4]??'',$s[2]??'FIELDS',$s[3]??'',$ord,$now]);
        $fo=0; foreach (($s[5]??[]) as $fc) { $fo+=10; $insSF->execute([$s[0],$fc,$fo]); }
    }
}

// Turn a criteria pack (filtered by inspection type) into checklist rows keyed to
// the given checklist field's actual column keys — so a report created from a type
// with an INSP_CHECKLIST section can be PRE-FILLED with the applicable criteria.
function uire_criteria_prefill_rows($checklistField, $pack = 'GENERAL', $inspType = null) {
    if (!$checklistField || !function_exists('idems_table_col_defs')) return [];
    $defs = idems_table_col_defs($checklistField); $keys = array_keys($defs);
    // Map by label so column reordering never breaks the prefill.
    $col = function($needle) use ($defs) { foreach ($defs as $k=>$d) if (stripos((string)$d['label'],$needle)!==false) return $k; return null; };
    $kCrit=$col('criterion'); $kReq=$col('requirement'); $kRef=$col('reference'); $kAcc=$col('acceptance');
    $rows = [];
    foreach (uire_criteria($pack, $inspType) as $c) {
        $r = array_fill_keys($keys, '');
        if ($kCrit) $r[$kCrit] = trim(($c['category']?$c['category'].' — ':'').$c['requirement']) ?: $c['code'];
        if ($kReq)  $r[$kReq]  = (string)$c['requirement'];
        if ($kRef)  $r[$kRef]  = trim((string)($c['reference'] ?: ($c['standard'].($c['clause']?' '.$c['clause']:''))));
        if ($kAcc)  $r[$kAcc]  = (string)$c['acceptance'];
        $rows[] = $r;
    }
    return $rows;
}

// Assemble a complete inspection report TYPE from the Library (URFE + inspection
// sections). This is the §107 proof: many inspection report types, one engine,
// zero report-specific code. Returns the report type id.
function uire_assemble_inspection_report($code, $name, $sectionCodes, $opts = []) {
    $opts = array_merge(['category' => 'TPIA_REPORT', 'finding_enabled' => 1, 'evidence_enabled' => 1], $opts);
    return urfe_assemble_report_type($code, $name, $sectionCodes, $opts);
}

// ---------------------------------------------------------------------------
//  Inspection data-integrity checks (§57-62). DETERMINISTIC and ADVISORY — every
//  issue is a flag for a human; nothing here ever changes a measurement, a
//  result, an acceptance criterion or a finding. Called from idems_qa_run.
//  Returns [ ['sev','cat','title','loc','why','fix'], … ].
// ---------------------------------------------------------------------------
function uire_qa_checks($doc, $fields = null, $data = null) {
    if ($fields === null) $fields = idems_fields((int)($doc['report_type_id'] ?? 0));
    if ($data === null) { $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = []; }
    $out = [];
    $numOf = function($v){ $v = preg_replace('/[^0-9.\-]/','',(string)$v); return ($v===''||$v==='-') ? null : (float)$v; };
    $passish = function($v){ $v = strtolower(trim((string)$v)); return in_array($v, ['pass','passed','ok','accept','accepted','conform','yes','satisfactory'], true); };
    $failish = function($v){ $v = strtolower(trim((string)$v)); return $v!=='' && (strpos($v,'fail')!==false || strpos($v,'reject')!==false || strpos($v,'not conform')!==false || strpos($v,'nonconform')!==false); };

    // (a) Measurement vs stated result (§62): Actual outside Min/Max but marked
    // pass — flag, DO NOT flip the result.
    foreach ($fields as $f) {
        if (($f['ftype'] ?? '') !== 'table') continue;
        $rows = $data[$f['fkey']] ?? null; if (!is_array($rows) || !$rows) continue;
        $defs = idems_table_col_defs($f); $labels = array_map(fn($d)=>strtolower((string)$d['label']), $defs);
        $col = function($needle) use ($defs) { foreach ($defs as $k=>$d) if (stripos((string)$d['label'],$needle)!==false) return $k; return null; };
        $kMin=$col('min'); $kMax=$col('max'); $kAct=$col('actual'); $kRes=$col('result'); $kParam=$col('parameter')??$col('dimension')??$col('test');
        if ($kAct===null || ($kMin===null && $kMax===null)) continue;   // not a measurement-style table
        foreach ($rows as $i => $r) {
            $rr = (array)$r; $act = $numOf($rr[$kAct] ?? ''); if ($act === null) continue;
            $min = $kMin!==null ? $numOf($rr[$kMin] ?? '') : null; $max = $kMax!==null ? $numOf($rr[$kMax] ?? '') : null;
            $oor = ($min !== null && $act < $min) || ($max !== null && $act > $max);
            if (!$oor) continue;
            $res = $kRes!==null ? (string)($rr[$kRes] ?? '') : '';
            $param = $kParam!==null ? trim((string)($rr[$kParam] ?? '')) : ''; $where = ($f['label'] ?? 'Measurement') . ' — row ' . ($i+1) . ($param!==''?' ('.$param.')':'');
            $range = trim(($min!==null?('min '.$min):'').(($min!==null&&$max!==null)?' / ':'').($max!==null?('max '.$max):''));
            if ($passish($res))
                $out[] = ['sev'=>'high','cat'=>'measurement','title'=>'Measurement appears outside the acceptance range but is marked pass',
                    'loc'=>$where, 'why'=>'Recorded value '.$act.' is outside the stated range ('.$range.'), yet the result reads "'.$res.'". Please verify the measurement or the result.',
                    'fix'=>'Re-check the reading and the acceptance range; correct whichever is wrong. The system will not change it for you.'];
            elseif ($res === '')
                $out[] = ['sev'=>'medium','cat'=>'measurement','title'=>'Out-of-range measurement has no result recorded',
                    'loc'=>$where, 'why'=>'Recorded value '.$act.' is outside the stated range ('.$range.') and no result is entered.',
                    'fix'=>'Record the result (and a finding if it is a nonconformity).'];
        }
    }

    // (b) Field-level quantity reconciliation (§20/§57) — inspection library qty.
    $q = function($k) use ($data,$numOf){ return array_key_exists($k,$data) ? $numOf($data[$k]) : null; };
    $pres=$q('presented_qty'); $insp=$q('inspected_qty'); $acc=$q('accepted_qty'); $rej=$q('rejected_qty'); $bal=$q('balance_qty');
    $base = $insp !== null ? $insp : $pres;
    if ($acc !== null && $rej !== null && $base !== null && ($acc + $rej) > $base + 0.001)
        $out[] = ['sev'=>'high','cat'=>'quantity','title'=>'Accepted + rejected exceeds the inspected quantity','loc'=>'Quantity verification',
            'why'=>'Accepted ('.$acc.') + rejected ('.$rej.') = '.($acc+$rej).' exceeds the '.($insp!==null?'inspected':'presented').' quantity ('.$base.').',
            'fix'=>'Correct the quantities so they reconcile.'];
    if ($acc !== null && $pres !== null && $acc > $pres + 0.001)
        $out[] = ['sev'=>'high','cat'=>'quantity','title'=>'Accepted quantity exceeds the presented quantity','loc'=>'Quantity verification',
            'why'=>'Accepted ('.$acc.') is greater than presented ('.$pres.').','fix'=>'Correct the quantities.'];
    if ($bal !== null && $pres !== null && $acc !== null && $rej !== null && abs($bal - ($pres - $acc - $rej)) > 0.001)
        $out[] = ['sev'=>'medium','cat'=>'quantity','title'=>'Balance quantity does not reconcile','loc'=>'Quantity verification',
            'why'=>'Balance ('.$bal.') ≠ presented − accepted − rejected ('.($pres-$acc-$rej).').','fix'=>'Recompute the balance (presented − accepted − rejected).'];

    // (c) Overall result vs the checklist (§57 last bullet): a positive inspection
    // result while a checklist criterion is failed — contradiction to reconcile.
    $ir = strtolower(trim((string)($data['inspection_result'] ?? '')));
    $resultPositive = $ir !== '' && (strpos($ir,'accept')!==false || strpos($ir,'pass')!==false) && strpos($ir,'not')===false && strpos($ir,'reject')===false;
    if ($resultPositive) {
        foreach ($fields as $f) {
            if (($f['ftype'] ?? '') !== 'table' || ($f['fkey'] ?? '') !== 'checklist') continue;
            $rows = $data[$f['fkey']] ?? null; if (!is_array($rows)) continue;
            $defs = idems_table_col_defs($f); $kRes=null; foreach ($defs as $k=>$d){ $l=strtolower((string)$d['label']); if ($l==='result'||strpos($l,'result')!==false){$kRes=$k;break;} }
            if ($kRes===null) continue;
            $failed = 0; foreach ($rows as $r){ if ($failish((string)((array)$r)[$kRes] ?? '')) $failed++; }
            if ($failed > 0)
                $out[] = ['sev'=>'high','cat'=>'contradiction','title'=>'Overall result is positive while a checklist item is failed','loc'=>'Inspection checklist',
                    'why'=>'The inspection result is "'.$data['inspection_result'].'" but '.$failed.' checklist item(s) are marked fail/reject.',
                    'fix'=>'Reconcile the overall result with the failed item(s), or raise a finding/NCR and set the result accordingly.'];
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  Inspection history, comparison, re-inspection & consolidation (§51-56).
//  All reuse the existing report_docs — no new report store. An "inspection
//  report type" is one that carries an inspection Library section (lib_code
//  INSP_*) or an inspection_result field, or the built-in IR family.
// ---------------------------------------------------------------------------
function uire_is_inspection_type($typeId) {
    $typeId = (int)$typeId; if (!$typeId) return false;
    if ((int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=? AND lib_code LIKE 'INSP\\_%' ESCAPE '\\'", [$typeId]) > 0) return true;
    if ((int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey='inspection_result'", [$typeId]) > 0) return true;
    $code = (string)ops_val("SELECT code FROM report_types WHERE id=?", [$typeId]);
    return in_array($code, ['IR','MGHIR'], true);
}
// The set of inspection report_type ids (cached per request).
function uire_inspection_type_ids() {
    static $ids = null; if ($ids !== null) return $ids;
    $ids = [];
    foreach (ops_all("SELECT id FROM report_types") as $t) if (uire_is_inspection_type((int)$t['id'])) $ids[] = (int)$t['id'];
    return $ids;
}
// Prior inspection reports for the same vendor + PO (optionally item / serial),
// most recent first, excluding this document (§52/§92). Snapshot-safe: reads the
// stored data. Returns rows with a compact result/qty summary.
function uire_inspection_history($doc, $limit = 12) {
    $ids = uire_inspection_type_ids(); if (!$ids) return [];
    $data = is_array($doc['data'] ?? null) ? $doc['data'] : (json_decode($doc['data'] ?? '[]', true) ?: []);
    $vid = (int)($doc['vendor_id'] ?? 0);
    $po = strtolower(trim((string)($data['po_number'] ?? ($doc['po_ref'] ?? ''))));
    $item = strtolower(trim((string)($data['item_desc'] ?? ''))); $serial = strtolower(trim((string)($data['serial_no'] ?? '')));
    if (!$vid && $po === '' && $serial === '') return [];
    $selfId = (int)($doc['id'] ?? 0);
    $in = implode(',', array_map('intval', $ids));
    $rows = ops_all("SELECT * FROM report_docs WHERE report_type_id IN ($in) AND deleted=0 AND (id<>? OR ?=0)
                     ORDER BY COALESCE(issue_date,created_at) DESC, id DESC LIMIT 200", [$selfId, $selfId]);
    $out = [];
    foreach ($rows as $r) {
        if ($selfId && (int)$r['id'] === $selfId) continue;
        $rd = json_decode($r['data'] ?? '[]', true); if (!is_array($rd)) $rd = [];
        $rpo = strtolower(trim((string)($rd['po_number'] ?? ($r['po_ref'] ?? ''))));
        $sameVendor = $vid && (int)($r['vendor_id'] ?? 0) === $vid;
        $samePo = $po !== '' && $rpo === $po;
        $sameSerial = $serial !== '' && strtolower(trim((string)($rd['serial_no'] ?? ''))) === $serial;
        if (!($sameVendor || $samePo || $sameSerial)) continue;
        $out[] = [
            'id' => (int)$r['id'], 'irn' => $r['irn'], 'date' => ($r['issue_date'] ?: $r['created_at']),
            'type_code' => $r['type_code'], 'result' => trim((string)($rd['inspection_result'] ?? ($r['result'] ?? ''))),
            'item' => trim((string)($rd['item_desc'] ?? '')), 'serial' => trim((string)($rd['serial_no'] ?? '')),
            'accepted' => ($rd['accepted_qty'] ?? ''), 'rejected' => ($rd['rejected_qty'] ?? ''),
            'status' => $r['status'], 'finalized' => (int)$r['finalized'],
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}
// Compare this inspection to a prior one (§54) — result / accepted / rejected
// deltas, in plain terms, for a follow-up or re-inspection.
function uire_inspection_compare($doc, $prev) {
    $d = is_array($doc['data'] ?? null) ? $doc['data'] : (json_decode($doc['data'] ?? '[]', true) ?: []);
    $pd = is_array($prev['data'] ?? null) ? $prev['data'] : (json_decode($prev['data'] ?? '[]', true) ?: []);
    $n = fn($x)=>($x===''||$x===null)?null:(float)preg_replace('/[^0-9.\-]/','',(string)$x);
    $lines = [];
    $r0 = trim((string)($pd['inspection_result'] ?? ($prev['result'] ?? ''))); $r1 = trim((string)($d['inspection_result'] ?? ($doc['result'] ?? '')));
    if ($r0 !== '' || $r1 !== '') $lines[] = 'Result: ' . ($r0 ?: '—') . ' → ' . ($r1 ?: '—') . (($r0 && $r1 && $r0 !== $r1) ? ' (changed)' : '');
    foreach (['accepted_qty'=>'Accepted','rejected_qty'=>'Rejected'] as $k=>$lab) {
        $a=$n($pd[$k] ?? ''); $b=$n($d[$k] ?? '');
        if ($a !== null || $b !== null) { $delta = ($a!==null&&$b!==null)?($b-$a):null; $lines[] = "$lab: ".($a??'—')." → ".($b??'—').($delta!==null&&$delta!=0?' ('.($delta>0?'+':'').rtrim(rtrim(number_format($delta,2),'0'),'.').')':''); }
    }
    return ['prev_irn'=>$prev['irn'] ?? '', 'prev_date'=>($prev['issue_date'] ?? ''), 'lines'=>$lines];
}
// Consolidated inspection view (§51) — aggregate the linked inspection reports
// for a project into totals + a per-report list, without re-entering data.
function uire_consolidated_inspection($projectName, $vendorId = null) {
    $ids = uire_inspection_type_ids(); if (!$ids) return ['reports'=>[], 'totals'=>[]];
    $in = implode(',', array_map('intval', $ids));
    $args = ["%$projectName%"]; $w = "report_type_id IN ($in) AND deleted=0 AND project_name LIKE ?";
    if ($vendorId) { $w .= " AND vendor_id=?"; $args[] = (int)$vendorId; }
    $rows = ops_all("SELECT * FROM report_docs WHERE $w ORDER BY COALESCE(issue_date,created_at), id", $args);
    $reports = []; $tot = ['reports'=>0,'inspected'=>0,'accepted'=>0,'rejected'=>0,'rejected_reports'=>0];
    $n = fn($x)=>(float)preg_replace('/[^0-9.\-]/','',(string)$x);
    foreach ($rows as $r) {
        $rd = json_decode($r['data'] ?? '[]', true); if (!is_array($rd)) $rd = [];
        $res = strtolower(trim((string)($rd['inspection_result'] ?? ($r['result'] ?? ''))));
        $tot['reports']++; $tot['inspected'] += $n($rd['inspected_qty'] ?? 0); $tot['accepted'] += $n($rd['accepted_qty'] ?? 0); $tot['rejected'] += $n($rd['rejected_qty'] ?? 0);
        if (strpos($res,'reject')!==false) $tot['rejected_reports']++;
        $reports[] = ['id'=>(int)$r['id'],'irn'=>$r['irn'],'date'=>($r['issue_date']?:$r['created_at']),'vendor_id'=>(int)$r['vendor_id'],'result'=>$res,'item'=>trim((string)($rd['item_desc']??''))];
    }
    return ['reports'=>$reports, 'totals'=>$tot];
}

// ---------------------------------------------------------------------------
//  AI metadata exposure (§60-64, §85). Phase 2 does NOT implement the AI engine;
//  it exposes a CONSERVATIVE per-field policy the future AI layer must obey. The
//  hard safety rule (§61): AI may analyze and suggest, but NEVER auto-modifies a
//  factual/technical field (measurement, result, quantity, date, severity). It
//  may draft wording for narrative fields — a human still commits it. This is the
//  metadata contract; the existing three-layer QA already enforces "advisory
//  only" at runtime, and uire_qa_checks() flags without ever changing a value.
// ---------------------------------------------------------------------------
function uire_ai_field_policy($field) {
    $ft = strtolower((string)($field['ftype'] ?? 'text'));
    $key = strtolower((string)($field['fkey'] ?? ''));
    $narrative = in_array($ft, ['textarea','richtext','text'], true);
    // Factual / technical fields the AI must never rewrite (§61).
    $factual = in_array($ft, ['number','date','time','calc','select','radio','yesno','checkbox','multiselect','unit','table','instrument','qr','gps','signature','sigblock'], true)
        || in_array($key, ['inspection_result','accepted_qty','rejected_qty','presented_qty','inspected_qty','balance_qty'], true);
    return [
        'analyze' => true,                         // AI may always read & analyze
        'suggest' => $narrative,                    // may suggest wording for narrative fields
        'modify'  => false,                          // NEVER auto-modifies — always human-committed
        'class'   => $factual ? 'factual' : ($narrative ? 'narrative' : 'other'),
    ];
}
// Per-field AI policy map for a report type (for the future AI layer / API §84).
function uire_ai_report_metadata($fields) {
    $out = [];
    foreach ($fields as $f) $out[(string)$f['fkey']] = uire_ai_field_policy($f);
    return $out;
}

// ---------------------------------------------------------------------------
//  Read helpers.
// ---------------------------------------------------------------------------
function uire_inspection_types() {
    return function_exists('lk_options_or') ? lk_options_or('inspection_type', UIRE_INSPECTION_TYPES) : UIRE_INSPECTION_TYPES;
}
function uire_criteria_packs() {
    return ops_all("SELECT * FROM inspection_criteria_packs WHERE active=1 ORDER BY sort_order, name");
}
// Criteria filtered by pack and/or inspection type. A criterion with an empty
// applies_types applies to ALL types; otherwise the type code must be listed.
function uire_criteria($pack = null, $inspType = null) {
    $w = "status<>'ARCHIVED'"; $a = [];
    if ($pack !== null && $pack !== '') { $w .= " AND pack=?"; $a[] = $pack; }
    $rows = ops_all("SELECT * FROM inspection_criteria WHERE $w ORDER BY sort_order, code", $a);
    if ($inspType === null || $inspType === '') return $rows;
    $t = strtoupper(trim((string)$inspType));
    return array_values(array_filter($rows, function($r) use ($t) {
        $ap = trim((string)($r['applies_types'] ?? ''));
        if ($ap === '') return true;
        $codes = array_map(fn($x)=>strtoupper(trim($x)), explode(',', $ap));
        return in_array($t, $codes, true);
    }));
}
