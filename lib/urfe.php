<?php
// ===========================================================================
//  URFE — Universal Report Foundation Engine  (Phase 1)
// ---------------------------------------------------------------------------
//  A reusable, configurable, industry-neutral FOUNDATION layer that every
//  report engine (Inspection, Release, Vendor Assessment/Audit, Expediting,
//  NCR/CAPA, Document Review, Deputation …) consumes — so adding a report type
//  becomes CONFIGURATION, not new reporting infrastructure.
//
//  It is strictly ADDITIVE and NON-DESTRUCTIVE. It sits on top of the existing
//  IDEMS report engine (report_types → report_sections → report_fields →
//  report_docs) without changing it:
//    • a shared FIELD LIBRARY + SECTION LIBRARY (reuse across report types),
//    • report-type metadata (category/version/lifecycle/feature-flags),
//    • provenance columns so a cloned section/field remembers its Library origin
//      (for the "used-by" dependency view and drift detection),
//    • a Status master interface for later layers.
//
//  Issued reports are unaffected: report_docs already freezes frozen_schema at
//  issue, so Library changes can never alter a report that is already out.
// ===========================================================================

// ---------------------------------------------------------------------------
//  Default universal FIELD LIBRARY — reusable field definitions. Every entry:
//  [code, label, ftype, options|'', group, help|''] (+ optional table_cols via
//  URFE_LIB_TABLES). Codes are STABLE identifiers; labels may be re-worded
//  without breaking stored data. Industry-neutral throughout.
// ---------------------------------------------------------------------------
const URFE_LIB_FIELDS = [
    // — General / activity —
    ['subject',        'Subject / title',        'text',      '', 'General', 'What this report is about'],
    ['report_date',    'Report date',            'date',      '', 'General', ''],
    ['period_from',    'Period from',            'date',      '', 'General', ''],
    ['period_to',      'Period to',              'date',      '', 'General', ''],
    ['location',       'Location',               'text',      '', 'General', ''],
    ['remarks',        'Remarks',                'textarea',  '', 'General', ''],
    // — Parties —
    ['client_name',    'Client',                 'text',      '', 'Party', ''],
    ['vendor_name',    'Vendor / supplier',      'text',      '', 'Party', ''],
    ['vendor_type',    'Vendor type',            'select',    'Manufacturer,Service Provider,Trader / Stockist,Fabricator,Contractor', 'Party', 'Drives which capability sections apply'],
    ['project_name',   'Project',                'text',      '', 'Party', ''],
    ['po_number',      'Purchase order no.',     'text',      '', 'Party', ''],
    ['contract_no',    'Contract no.',           'text',      '', 'Party', ''],
    // — Document control —
    ['document_number','Document number',        'text',      '', 'Document control', ''],
    ['revision',       'Revision',               'text',      '', 'Document control', ''],
    ['issue_date',     'Issue date',             'date',      '', 'Document control', ''],
    ['prepared_by',    'Prepared by',            'text',      '', 'Document control', ''],
    ['reviewed_by',    'Reviewed by',            'text',      '', 'Document control', ''],
    ['approved_by',    'Approved by',            'text',      '', 'Document control', ''],
    ['confidentiality','Confidentiality',        'select',    'Public,Internal,Confidential,Restricted,Client Confidential', 'Document control', ''],
    // — Scope / requirements —
    ['scope',          'Scope',                  'textarea',  '', 'Scope', ''],
    ['objective',      'Objective',              'textarea',  '', 'Scope', ''],
    ['standards',      'Applicable standards',   'textarea',  '', 'Scope', 'Reference only — do not paste standard text'],
    ['requirements',   'Applicable requirements','textarea',  '', 'Scope', ''],
    // — Outcome —
    ['overall_status', 'Overall status',         'select',    'On track,At risk,Delayed,Complete,Not started', 'Outcome', ''],
    ['summary',        'Summary',                'textarea',  '', 'Outcome', ''],
    ['observations',   'Observations',           'textarea',  '', 'Outcome', ''],
    ['conclusion',     'Conclusion',             'textarea',  '', 'Outcome', ''],
    ['recommendation', 'Recommendation',         'select',    'Approved,Approved with conditions,Recovery plan required,Continue monitoring,Not approved', 'Outcome', ''],
    ['limitations',    'Limitations',            'textarea',  '', 'Outcome', ''],
    ['next_followup',  'Next follow-up',         'date',      '', 'Outcome', ''],
    // — Sign-off —
    ['signoff',        'Sign-off',               'sigblock',  '', 'Approval', 'Per-role prepared / reviewed / approved block'],
];

// Repeatable-table field definitions (table_cols in the engine's pipe format).
const URFE_LIB_TABLES = [
    ['revision_history',  'Revision history',    'Personnel',
        "Rev\nDate|date\nDescription\nPrepared by\nReviewed by\nApproved by\nReason"],
    ['personnel',         'Personnel',           'Personnel',
        "Name\nDesignation\nOrganisation\nRole\nRemarks"],
    ['documents_reviewed','Documents reviewed',  'Document control',
        "Document\nNumber\nRev\nStatus|select|Reviewed,Approved,Approved with comments,Not submitted,Not applicable\nRemarks"],
    ['action_tracker',    'Action tracker',      'Findings',
        "Action\nSource\nResponsible\nDue|date\nStatus|select|Open,In Progress,Completed,Verified,Closed,Overdue\nEvidence referred\nAttached|select|Yes,No,N/A"],
    ['references',        'References',          'Document control',
        "Type|select|PO,Contract,Drawing,Specification,Procedure,Standard,Certificate,NCR,Report,Other\nReference\nDescription\nApplicability"],
];

// ---------------------------------------------------------------------------
//  Default universal SECTION LIBRARY — reusable sections, each pointing at a
//  curated set of Library field codes. [code, title, section_type, component,
//  help, [field codes], flags]. component<>'' marks a first-class object block
//  (finding/action/risk/evidence/photo/progress/comparison) for later layers.
// ---------------------------------------------------------------------------
const URFE_LIB_SECTIONS = [
    ['DOC_CONTROL',   'Document control',            'FIELDS', '', 'Controlled-document identity',
        ['document_number','revision','issue_date','prepared_by','reviewed_by','approved_by','confidentiality'], []],
    ['REVISION_HIST', 'Revision history',            'TABLE',  '', 'Change history of this report',
        ['revision_history'], []],
    ['CLIENT_INFO',   'Client information',          'FIELDS', '', 'Who the report is for',
        ['client_name'], []],
    ['VENDOR_INFO',   'Vendor / supplier information','FIELDS', '', 'The supplier under review',
        ['vendor_name','vendor_type'], []],
    ['PROJECT_INFO',  'Project & order information',  'FIELDS', '', 'Project, PO and contract linkage',
        ['project_name','po_number','contract_no'], []],
    ['ACTIVITY_INFO', 'Report / activity information','FIELDS', '', 'Subject, dates and location',
        ['subject','report_date','period_from','period_to','location'], []],
    ['SCOPE',         'Scope & objective',           'FIELDS', '', 'What was covered and why',
        ['scope','objective'], []],
    ['STANDARDS',     'Applicable standards',        'FIELDS', '', 'Referenced standards & requirements',
        ['standards','requirements'], []],
    ['PERSONNEL',     'Personnel',                   'TABLE',  '', 'People involved',
        ['personnel'], []],
    ['DOCS_REVIEWED', 'Documents reviewed',          'TABLE',  '', 'Documents examined',
        ['documents_reviewed'], []],
    ['FINDINGS',      'Findings',                    'COMPONENT', 'finding', 'Findings / observations / non-conformities',
        [], ['finding_enabled']],
    ['ACTIONS',       'Action tracker',              'TABLE',  '', 'Actions arising and their status',
        ['action_tracker'], []],
    ['REFERENCES',    'References',                  'TABLE',  '', 'Referenced documents & standards',
        ['references'], []],
    ['SUMMARY',       'Summary',                     'FIELDS', '', 'Executive summary',
        ['summary'], []],
    ['CONCLUSION',    'Conclusion & recommendation', 'FIELDS', '', 'Overall conclusion, recommendation and limitations',
        ['conclusion','recommendation','limitations','next_followup'], []],
    ['APPROVAL',      'Approval & sign-off',         'FIELDS', '', 'Per-role sign-off block',
        ['signoff'], []],
];

// ---------------------------------------------------------------------------
//  Schema — additive only. New Library tables + provenance/metadata columns.
// ---------------------------------------------------------------------------
function urfe_migrate() {
    $pdo = db(); $pk = pk_clause();

    // Report-type metadata (URFE §4). All additive; sensible defaults so every
    // existing report type keeps working with no change.
    if (function_exists('ensure_column')) {
        ensure_column('report_types', 'description',   'TEXT');
        ensure_column('report_types', 'subcategory',   "VARCHAR(40) DEFAULT ''");
        ensure_column('report_types', 'urfe_version',  "VARCHAR(20) DEFAULT '1.0'");
        ensure_column('report_types', 'lifecycle',     "VARCHAR(20) DEFAULT 'ACTIVE'");   // DRAFT/ACTIVE/INACTIVE/ARCHIVED
        ensure_column('report_types', 'owner_user_id', 'INT NULL');
        ensure_column('report_types', 'active_from',   "VARCHAR(20) DEFAULT ''");
        ensure_column('report_types', 'active_until',  "VARCHAR(20) DEFAULT ''");
        ensure_column('report_types', 'ai_qa_enabled',            'INT DEFAULT 1');
        ensure_column('report_types', 'evidence_enabled',         'INT DEFAULT 1');
        ensure_column('report_types', 'finding_enabled',          'INT DEFAULT 0');
        ensure_column('report_types', 'signature_enabled',        'INT DEFAULT 1');
        ensure_column('report_types', 'revision_control_enabled', 'INT DEFAULT 1');
        // Provenance: a section/field cloned from the Library remembers its origin
        // (code + the Library version it was taken at) — powers "used-by" and drift.
        ensure_column('report_sections', 'lib_code',    "VARCHAR(60) DEFAULT ''");
        ensure_column('report_sections', 'lib_version', 'INT DEFAULT 0');
        ensure_column('report_sections', 'section_type',"VARCHAR(30) DEFAULT 'FIELDS'");
        ensure_column('report_sections', 'component',   "VARCHAR(30) DEFAULT ''");
        ensure_column('report_sections', 'repeatable',  'INT DEFAULT 0');
        ensure_column('report_fields',   'lib_code',    "VARCHAR(60) DEFAULT ''");
        ensure_column('report_fields',   'lib_version', 'INT DEFAULT 0');
        ensure_column('report_fields',   'grp',         "VARCHAR(60) DEFAULT ''");   // field group
    }

    // FIELD LIBRARY — reusable field definitions (URFE §7-10).
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_lib_fields (
        id $pk, code VARCHAR(60), label VARCHAR(200) DEFAULT '', ftype VARCHAR(20) DEFAULT 'text',
        options TEXT, help VARCHAR(400) DEFAULT '', placeholder VARCHAR(200) DEFAULT '',
        grp VARCHAR(60) DEFAULT '', calc_expr VARCHAR(400) DEFAULT '', col_span INT DEFAULT 1,
        table_cols TEXT, weight REAL DEFAULT 0, max_score REAL DEFAULT 0, score_map TEXT,
        description TEXT, version INT DEFAULT 1, status VARCHAR(20) DEFAULT 'PUBLISHED', is_system INT DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('report_lib_fields', 'code');

    // SECTION LIBRARY — reusable sections (URFE §13-16).
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_lib_sections (
        id $pk, code VARCHAR(60), title VARCHAR(200) DEFAULT '', help VARCHAR(400) DEFAULT '',
        section_type VARCHAR(30) DEFAULT 'FIELDS', component VARCHAR(30) DEFAULT '', icon VARCHAR(20) DEFAULT '',
        repeatable INT DEFAULT 0, page_break_before INT DEFAULT 0, keep_together INT DEFAULT 0,
        evidence_enabled INT DEFAULT 0, finding_enabled INT DEFAULT 0, ai_enabled INT DEFAULT 1,
        category VARCHAR(30) DEFAULT '', description TEXT, version INT DEFAULT 1, status VARCHAR(20) DEFAULT 'PUBLISHED',
        is_system INT DEFAULT 0, sort_order INT DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('report_lib_sections', 'code');

    // SECTION→FIELD placement (which Library fields a Library section carries).
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_lib_section_fields (
        id $pk, section_code VARCHAR(60), field_code VARCHAR(60), sort_order INT DEFAULT 0,
        required INT DEFAULT 0, col_span INT DEFAULT 0,
        cond_field VARCHAR(60) DEFAULT '', cond_op VARCHAR(10) DEFAULT '', cond_val VARCHAR(200) DEFAULT '')");

    // Configurable STATUS master (URFE §34) — interface for later workflow layers.
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_statuses (
        id $pk, module VARCHAR(30) DEFAULT 'report', code VARCHAR(30), name VARCHAR(60) DEFAULT '',
        category VARCHAR(20) DEFAULT '', color VARCHAR(20) DEFAULT '', sequence INT DEFAULT 0,
        is_terminal INT DEFAULT 0, next_codes VARCHAR(255) DEFAULT '', description VARCHAR(300) DEFAULT '',
        is_system INT DEFAULT 1, active INT DEFAULT 1)");

    // Seed the default universal Library ONCE (guarded). Never overwrites user edits.
    if (function_exists('setting_get') && !setting_get('urfe_library_seeded_v1', '')) {
        try { urfe_seed_library(); } catch (Throwable $e) { /* never break login/migrate */ }
        if (function_exists('setting_set')) setting_set('urfe_library_seeded_v1', '1');
    }
    if (function_exists('setting_get') && !setting_get('urfe_statuses_seeded_v1', '')) {
        try { urfe_seed_statuses(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('urfe_statuses_seeded_v1', '1');
    }
}

// Insert the default field + section library (idempotent per code).
function urfe_seed_library() {
    $pdo = db(); $now = date('c');
    $hasField = function($code) { return (int)ops_val("SELECT COUNT(*) FROM report_lib_fields WHERE code=?", [$code]) > 0; };
    $insF = $pdo->prepare("INSERT INTO report_lib_fields (code,label,ftype,options,grp,help,table_cols,is_system,status,version,created_at) VALUES (?,?,?,?,?,?,?,1,'PUBLISHED',1,?)");
    foreach (URFE_LIB_FIELDS as $f) {
        if ($hasField($f[0])) continue;
        $insF->execute([$f[0], $f[1], $f[2], $f[3] ?? '', $f[4] ?? '', $f[5] ?? '', '', $now]);
    }
    foreach (URFE_LIB_TABLES as $t) {
        if ($hasField($t[0])) continue;
        $insF->execute([$t[0], $t[1], 'table', '', $t[2] ?? '', '', $t[3] ?? '', $now]);
    }
    $hasSec = function($code) { return (int)ops_val("SELECT COUNT(*) FROM report_lib_sections WHERE code=?", [$code]) > 0; };
    $insS = $pdo->prepare("INSERT INTO report_lib_sections (code,title,help,section_type,component,finding_enabled,is_system,status,version,sort_order,created_at) VALUES (?,?,?,?,?,?,1,'PUBLISHED',1,?,?)");
    $insSF = $pdo->prepare("INSERT INTO report_lib_section_fields (section_code,field_code,sort_order) VALUES (?,?,?)");
    $ord = 0;
    foreach (URFE_LIB_SECTIONS as $s) {
        $ord += 10;
        if ($hasSec($s[0])) continue;
        $flags = $s[6] ?? [];
        $findingEnabled = in_array('finding_enabled', $flags, true) ? 1 : 0;
        $insS->execute([$s[0], $s[1], $s[4] ?? '', $s[2] ?? 'FIELDS', $s[3] ?? '', $findingEnabled, $ord, $now]);
        $fo = 0;
        foreach (($s[5] ?? []) as $fc) { $fo += 10; $insSF->execute([$s[0], $fc, $fo]); }
    }
}

// Seed the default report-status set (configurable master; used by later layers).
function urfe_seed_statuses() {
    $pdo = db();
    $rows = [
        ['DRAFT','Draft','open','#6b7280',10,0,'IN_PROGRESS,CANCELLED'],
        ['IN_PROGRESS','In Progress','open','#2563eb',20,0,'SUBMITTED,CANCELLED'],
        ['SUBMITTED','Submitted','review','#7c3aed',30,0,'UNDER_REVIEW,CHANGES_REQUIRED'],
        ['UNDER_REVIEW','Under Review','review','#b45309',40,0,'APPROVED,CHANGES_REQUIRED'],
        ['CHANGES_REQUIRED','Changes Required','open','#c2410c',50,0,'IN_PROGRESS'],
        ['APPROVED','Approved','done','#15803d',60,0,'ISSUED'],
        ['ISSUED','Issued','done','#15803d',70,0,'SUPERSEDED'],
        ['SUPERSEDED','Superseded','closed','#6b7280',80,1,''],
        ['CANCELLED','Cancelled','closed','#991b1b',90,1,''],
    ];
    $ins = $pdo->prepare("INSERT INTO report_statuses (module,code,name,category,color,sequence,is_terminal,next_codes,is_system,active) VALUES ('report',?,?,?,?,?,?,?,1,1)");
    foreach ($rows as $r) {
        if ((int)ops_val("SELECT COUNT(*) FROM report_statuses WHERE module='report' AND code=?", [$r[0]]) > 0) continue;
        $ins->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6]]);
    }
}

// ---------------------------------------------------------------------------
//  Read helpers (used by the builder + later layers).
// ---------------------------------------------------------------------------
function urfe_lib_fields($grp = null) {
    $w = "status<>'ARCHIVED'"; $a = [];
    if ($grp !== null) { $w .= " AND grp=?"; $a[] = $grp; }
    return ops_all("SELECT * FROM report_lib_fields WHERE $w ORDER BY grp, label", $a);
}
function urfe_lib_field($code) { return ops_one("SELECT * FROM report_lib_fields WHERE code=?", [$code]); }
function urfe_lib_sections() { return ops_all("SELECT * FROM report_lib_sections WHERE status<>'ARCHIVED' ORDER BY sort_order, title"); }
function urfe_lib_section($code) { return ops_one("SELECT * FROM report_lib_sections WHERE code=?", [$code]); }
function urfe_lib_section_field_codes($code) {
    return array_map(fn($r) => $r['field_code'], ops_all("SELECT field_code FROM report_lib_section_fields WHERE section_code=? ORDER BY sort_order", [$code]));
}
function urfe_report_statuses($module = 'report') {
    return ops_all("SELECT * FROM report_statuses WHERE module=? AND active=1 ORDER BY sequence", [$module]);
}
