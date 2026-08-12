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

    if (function_exists('setting_get') && !setting_get('uire_criteria_seeded_v1', '')) {
        try { uire_seed_criteria(); } catch (Throwable $e) { /* never break login/migrate */ }
        if (function_exists('setting_set')) setting_set('uire_criteria_seeded_v1', '1');
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
