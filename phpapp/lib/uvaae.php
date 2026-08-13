<?php
// ===========================================================================
//  UVAAE — Universal Vendor Audit, Compliance & Management-System Audit
//          Engine  (Phase 5)
// ---------------------------------------------------------------------------
//  Audits a vendor / supplier / process / management-system AGAINST A DEFINED
//  SET OF AUDIT CRITERIA and produces objective audit findings, nonconformities
//  and an audit conclusion. This is an AUDIT engine (ISO 19011 framework), NOT
//  the Phase-4 assessment engine:
//
//    * Phase 4 (UVAE) decides "is this vendor suitable to be qualified?" — its
//      findings are strengths / weaknesses / gaps and are NEVER auto-NCRs.
//    * Phase 5 (UVAAE) decides "does what the vendor DOES conform to the agreed
//      criteria / standard / process?" — its findings ARE graded conformances,
//      and Major / Minor nonconformities DO flow into the NCR / CAPA register.
//
//  Built entirely on the LOCKED Phase 1-4 foundations and the app's EXISTING
//  infrastructure — it does NOT rebuild any of it:
//    Audit report + findings-table→NCR engine   → VAR (idems_build_vendor_audit)
//                                                  + idems_raise_ncrs_from_audit
//    Nonconformity / corrective action           → nonconformities + capa
//    Risk register / actions / evidence / workflow/ approval / PDF / numbering /
//      versioning / QA                            → Phase 1 engines
//    Report field / section LIBRARY, metadata,
//      assembly                                   → Phase 1 (URFE)
//    Criteria LIBRARY architecture (applicability,
//      packs, dynamic assembly, prefill)          → Phase 2/4 pattern, EXTENDED
//    Vendor master / status lifecycle / 360       → business_partners +
//                                                  vendor_profiles + idems_vendor_*
//    Audit programme / schedule / internal audit  → lib/audits.php (§8.8) — the
//                                                  body's OWN audits; UVAAE is the
//                                                  vendor-facing complement.
//
//  What UVAAE adds (genuinely new, reusable): a configurable Audit Type master
//  and an Audit CRITERIA LIBRARY whose applicability (audit type / standard /
//  pack) makes each audit DYNAMICALLY ASSEMBLED — an ISO 9001 audit pulls the
//  QMS pack, an ISO 17020 audit pulls the inspection-body pack, a process audit
//  pulls the process pack, an HSE audit the HSE pack — from ONE engine, with NO
//  hard-coded standard text baked into logic (every criterion row is editable).
//  Plus a configurable audit-conclusion engine and an advisory data-integrity
//  QA layer that flags but NEVER changes a finding or a conclusion.
// ===========================================================================

// Audit Type master (§6/§93) — configurable via the lookup engine (reuses the
// Masters admin UI). code => label. One engine covers every row.
const UVAAE_AUDIT_TYPES = [
    'VENDOR'=>'Vendor / Supplier Audit','SUPPLIER_QUALITY'=>'Supplier Quality Audit','PROCESS'=>'Process Audit',
    'PRODUCT'=>'Product Audit','SYSTEM'=>'Management-System Audit','QMS'=>'Quality Management-System Audit (ISO 9001)',
    'ISO17020'=>'Inspection-Body Audit (ISO 17020)','ISO17025'=>'Laboratory Audit (ISO 17025)','EMS'=>'Environmental Management-System Audit (ISO 14001)',
    'OHS'=>'Occupational H&S Audit (ISO 45001)','HSE'=>'HSE / Safety Audit','TECHNICAL'=>'Technical Audit',
    'SPECIAL_PROCESS'=>'Special-Process Audit','MANUFACTURING'=>'Manufacturing Audit','PRE_AWARD'=>'Pre-Award Audit',
    'COMPLIANCE'=>'Compliance Audit','REGULATORY'=>'Regulatory Audit','CONTRACT'=>'Contract / Requirement Audit',
    'SURVEILLANCE'=>'Surveillance Audit','FOLLOWUP'=>'Follow-up / Verification Audit','REAUDIT'=>'Re-audit',
    'GAP'=>'Gap Audit','REMOTE'=>'Remote / Virtual Audit','DESKTOP'=>'Desktop / Document Audit',
    'FIRST_PARTY'=>'First-Party (Internal) Audit','SECOND_PARTY'=>'Second-Party (Supplier) Audit','SUBCONTRACTOR'=>'Subcontractor Audit',
    'ESG'=>'ESG / Sustainability Audit','INFOSEC'=>'Information-Security Audit','OTHER'=>'Other',
];
// Audit finding grade master (§42/§49) — the vocabulary that decides which
// findings become NCRs. Major & Minor flow to the NCR register; the rest do not.
const UVAAE_FINDING_GRADES = [
    'MAJOR'=>'Major nonconformity','MINOR'=>'Minor nonconformity','OBSERVATION'=>'Observation',
    'OFI'=>'Opportunity for improvement','CONFORMS'=>'Conforms','NOT_APPLICABLE'=>'Not applicable',
];
// Per-criterion conformance result master (§40) — used in the checklist.
const UVAAE_CONFORMANCE = [
    'CONFORMS'=>'Conforms','MINOR_NC'=>'Minor NC','MAJOR_NC'=>'Major NC','OBSERVATION'=>'Observation',
    'OFI'=>'Opportunity','NOT_VERIFIED'=>'Not verified','NOT_APPLICABLE'=>'N/A',
];
// Audit conclusion / recommendation master (§45/§120) — distinct from the
// vendor's approval status; this is the per-audit outcome.
const UVAAE_CONCLUSIONS = [
    'CONFORMS'=>'Conforms — no action','CERTIFIABLE'=>'Recommended (certifiable)','CONDITIONAL'=>'Conditional — actions required',
    'SURVEILLANCE'=>'Maintain — surveillance','MINOR_ACTIONS'=>'Acceptable with minor actions','MAJOR_ACTIONS'=>'Not acceptable — major actions required',
    'NOT_CONFORMS'=>'Does not conform','SUSPEND'=>'Suspend / withdraw','REAUDIT'=>'Re-audit required','PENDING'=>'Pending — follow-up',
];
// Audit method master (§30/§83).
const UVAAE_METHODS = [
    'ONSITE'=>'On-site audit','REMOTE'=>'Remote / virtual audit','DESKTOP'=>'Desktop / document audit','INTERVIEW'=>'Interviews',
    'OBSERVATION'=>'Direct observation','SAMPLING'=>'Record / sample review','WITNESS'=>'Process witnessing','COMBINED'=>'Combined','OTHER'=>'Other',
];

// ---------------------------------------------------------------------------
//  Audit CRITERIA LIBRARY (§14/§93-96) — the keystone, EXTENDING the Phase 2/4
//  criteria-library architecture (NOT a new one). Applicability by audit type /
//  standard / pack makes each audit DYNAMICALLY ASSEMBLED. Every row is editable
//  seed data — there is NO hard-coded standard text in the logic; the criteria
//  are audit-area headings a body configures, not reproduced standard clauses.
//  Row: [code, family, category, requirement, question, evidence, response_type,
//        mandatory, critical, weight, applies_audit_types(csv|''=all), pack, clause]
//  Universal core (management-system fundamentals) applies to ALL audits; packs
//  (QMS / ISO17020 / PROCESS / SPECIAL_PROCESS / HSE / SUPPLIER_QUALITY) apply to
//  their audit types only. Codes are stable; nothing is hard-coded downstream.
// ---------------------------------------------------------------------------
const UVAAE_CRITERIA = [
    // ---- UNIVERSAL CORE — management-system fundamentals (every audit) ----
    ['MS-01','System','Management system','Documented management system appropriate to scope','Is a documented management system defined and appropriate to the scope?','MS manual / procedures','conformance',1,0,3,'','GENERAL','4'],
    ['MS-02','System','Document & record control','Documents and records controlled, current and retrievable','Are documents and records controlled, current and retrievable?','Document control procedure + samples','conformance',1,0,2,'','GENERAL','7.5'],
    ['MS-03','System','Responsibility','Responsibilities, authorities and competence defined','Are responsibilities, authorities and competence defined and met?','Org chart / competence records','conformance',1,0,2,'','GENERAL','5.3'],
    ['MS-04','System','Nonconformity','Nonconformity & corrective-action process effective','Is the nonconformity / corrective-action process defined and effective?','NCR / CAPA procedure + records','conformance',1,0,3,'','GENERAL','10.2'],
    ['MS-05','System','Internal audit','Internal audit programme implemented','Is an internal audit programme planned and implemented?','Audit schedule + reports','conformance',0,0,2,'','GENERAL','9.2'],
    ['MS-06','System','Management review','Management review conducted with actions','Is management review conducted with inputs, outputs and actions?','Review minutes','conformance',0,0,1,'','GENERAL','9.3'],
    ['MS-07','System','Risk & opportunity','Risks and opportunities identified and actioned','Are risks and opportunities identified and actioned?','Risk register','conformance',0,0,2,'','GENERAL','6.1'],
    ['MS-08','System','Improvement','Continual improvement demonstrated','Is continual improvement demonstrated with evidence?','Improvement records / trends','conformance',0,0,1,'','GENERAL','10.3'],
    ['MS-09','System','External providers','Externally provided processes/products controlled','Are externally provided processes and products controlled?','Supplier control procedure','conformance',0,0,2,'','GENERAL','8.4'],
    ['MS-10','System','Change control','Changes planned and controlled','Are changes to processes / products planned and controlled?','Change records','conformance',0,0,1,'','GENERAL','6.3'],
    // ---- QMS pack (ISO 9001 / QMS / SYSTEM audits) ----
    ['Q9-01','Quality','Context & leadership','Context, interested parties and quality policy established','Are context, interested parties and quality policy established?','Context analysis / policy','conformance',1,0,2,'QMS,SYSTEM,PRE_AWARD','QMS','4-5'],
    ['Q9-02','Quality','Planning','Quality objectives planned and monitored','Are measurable quality objectives planned and monitored?','Objectives + KPIs','conformance',0,0,2,'QMS,SYSTEM','QMS','6.2'],
    ['Q9-03','Quality','Operation','Operational planning and control of production/service','Is operational planning and control of production / service adequate?','Process controls / plans','conformance',1,0,3,'QMS,SYSTEM,MANUFACTURING','QMS','8.1'],
    ['Q9-04','Quality','Design','Design & development controlled (where applicable)','Is design & development controlled where applicable?','Design records / DFMEA','conformance',0,0,2,'QMS,SYSTEM','QMS','8.3'],
    ['Q9-05','Quality','Release','Product / service conformity verified before release','Is product / service conformity verified before release?','Release records / ITP','conformance',1,0,3,'QMS,SYSTEM,MANUFACTURING','QMS','8.6'],
    ['Q9-06','Quality','Monitoring','Monitoring, measurement, analysis and evaluation','Are monitoring, measurement, analysis and evaluation performed?','Data / analysis records','conformance',0,0,2,'QMS,SYSTEM','QMS','9.1'],
    ['Q9-07','Quality','Customer','Customer requirements and satisfaction managed','Are customer requirements and satisfaction managed?','Contract review / feedback','conformance',0,0,2,'QMS,SYSTEM','QMS','8.2'],
    // ---- ISO17020 pack (inspection-body audits) ----
    ['I20-01','Inspection body','Impartiality','Impartiality and independence safeguarded','Are impartiality and independence identified and safeguarded?','Impartiality analysis','conformance',1,1,3,'ISO17020','ISO17020','4.1'],
    ['I20-02','Inspection body','Confidentiality','Confidentiality of client information maintained','Is confidentiality of client information maintained?','Confidentiality undertakings','conformance',1,0,2,'ISO17020','ISO17020','4.2'],
    ['I20-03','Inspection body','Personnel','Inspector competence, authorisation and monitoring','Is inspector competence authorised and monitored?','Competence / authorisation records','conformance',1,0,3,'ISO17020','ISO17020','6.1'],
    ['I20-04','Inspection body','Equipment','Facilities and equipment adequate and calibrated','Are facilities and equipment adequate and calibrated?','Equipment / calibration register','conformance',1,0,2,'ISO17020','ISO17020','6.2'],
    ['I20-05','Inspection body','Methods','Inspection methods and procedures defined and used','Are inspection methods and procedures defined and used?','Method / procedure library','conformance',1,0,3,'ISO17020','ISO17020','7.1'],
    ['I20-06','Inspection body','Handling','Inspection items and samples handled correctly','Are inspection items and samples handled and identified correctly?','Handling procedure','conformance',0,0,2,'ISO17020','ISO17020','7.2'],
    ['I20-07','Inspection body','Records','Inspection records and reports controlled','Are inspection records and reports complete and controlled?','Report samples','conformance',1,0,2,'ISO17020','ISO17020','7.3-7.4'],
    ['I20-08','Inspection body','Complaints','Complaints and appeals handled','Are complaints and appeals recorded and handled?','Complaint register','conformance',0,0,1,'ISO17020','ISO17020','7.5-7.6'],
    // ---- PROCESS pack (process / manufacturing / product audits) ----
    ['PR-01','Process','Process definition','Process defined with flow, inputs and outputs','Is the process defined with flow, inputs and outputs?','Process flow / control plan','conformance',1,0,3,'PROCESS,MANUFACTURING,PRODUCT,TECHNICAL','PROCESS','8.5'],
    ['PR-02','Process','Process control','Key process parameters controlled and monitored','Are key process parameters controlled and monitored?','Setup sheets / SPC','conformance',1,0,3,'PROCESS,MANUFACTURING,TECHNICAL','PROCESS','8.5.1'],
    ['PR-03','Process','Equipment','Equipment capable, maintained and calibrated','Is equipment capable, maintained and calibrated?','Maintenance / calibration records','conformance',0,0,2,'PROCESS,MANUFACTURING,TECHNICAL','PROCESS','7.1.3'],
    ['PR-04','Process','In-process control','In-process inspection and controls applied','Are in-process inspection and controls applied per plan?','Inspection records / ITP','conformance',1,0,2,'PROCESS,MANUFACTURING,PRODUCT','PROCESS','8.6'],
    ['PR-05','Process','Traceability','Material and product traceability maintained','Is material and product traceability maintained?','Traceability records','conformance',0,0,2,'PROCESS,MANUFACTURING,PRODUCT','PROCESS','8.5.2'],
    ['PR-06','Process','Nonconforming output','Nonconforming output identified and controlled','Is nonconforming output identified and controlled?','NC / segregation records','conformance',1,0,2,'PROCESS,MANUFACTURING,PRODUCT','PROCESS','8.7'],
    ['PR-07','Process','Performance','Process performance / capability data reviewed','Is process performance / capability data reviewed?','Cpk / yield / trend data','conformance',0,0,1,'PROCESS,MANUFACTURING,TECHNICAL','PROCESS','9.1.3'],
    // ---- SPECIAL_PROCESS pack (welding / heat-treat / NDT / coating …) ----
    ['SP-01','Special process','Qualification','Special process qualified (procedure qualification)','Is the special process qualified (procedure qualification current)?','WPS/PQR / procedure qualification','conformance',1,1,3,'SPECIAL_PROCESS,TECHNICAL','SPECIAL_PROCESS','8.5.1.2'],
    ['SP-02','Special process','Personnel','Operators / personnel qualified and certified','Are operators / personnel qualified and certified for the process?','Operator / NDT / welder certs','conformance',1,0,3,'SPECIAL_PROCESS,TECHNICAL','SPECIAL_PROCESS','7.2'],
    ['SP-03','Special process','Equipment','Process equipment calibrated and monitored','Is process equipment calibrated and continuously monitored?','Calibration / recorder charts','conformance',0,0,2,'SPECIAL_PROCESS,TECHNICAL','SPECIAL_PROCESS','7.1.5'],
    ['SP-04','Special process','Records','Process records and parameters traceable','Are process records and parameters traceable to the product?','Process record sheets','conformance',1,0,2,'SPECIAL_PROCESS,TECHNICAL','SPECIAL_PROCESS','7.5.3'],
    // ---- HSE pack (HSE / safety / OHS / EMS audits) ----
    ['HSE-01','HSE','Policy & legal','HSE policy and legal compliance in place','Is an HSE policy in place with legal-compliance evaluation?','HSE policy / legal register','conformance',1,0,2,'HSE,OHS,EMS','HSE','5.2'],
    ['HSE-02','HSE','Risk','Hazard identification and risk assessment done','Is hazard identification and risk assessment done and current?','HIRA / risk register','conformance',1,0,3,'HSE,OHS','HSE','6.1.2'],
    ['HSE-03','HSE','Controls','PPE, permits and safe systems of work applied','Are PPE, permits and safe systems of work applied?','PTW / PPE / SSoW records','conformance',1,0,2,'HSE,OHS','HSE','8.1.2'],
    ['HSE-04','HSE','Incident','Incident reporting and investigation effective','Is incident reporting and investigation effective?','Incident register / investigations','conformance',0,0,2,'HSE,OHS','HSE','10.2'],
    ['HSE-05','HSE','Emergency','Emergency preparedness and response arranged','Is emergency preparedness and response arranged and tested?','ERP / drill records','conformance',0,0,1,'HSE,OHS','HSE','8.2'],
    ['HSE-06','HSE','Environment','Environmental aspects and controls managed','Are significant environmental aspects and controls managed?','Aspect-impact register','conformance',0,0,1,'EMS','HSE','6.1.2'],
    // ---- SUPPLIER_QUALITY pack (supplier-quality / second-party audits) ----
    ['SQ-01','Supplier quality','Incoming','Incoming material control and verification','Is incoming material controlled and verified before use?','IQC records / MTC review','conformance',1,0,2,'SUPPLIER_QUALITY,SECOND_PARTY,VENDOR','SUPPLIER_QUALITY','8.4.2'],
    ['SQ-02','Supplier quality','Approval','First-article / part approval before production','Is first-article / part approval completed before production?','FAI / PPAP records','conformance',0,0,2,'SUPPLIER_QUALITY,SECOND_PARTY','SUPPLIER_QUALITY','8.5.1'],
    ['SQ-03','Supplier quality','Sub-tier','Sub-tier supplier control and flow-down','Are sub-tier suppliers controlled with requirement flow-down?','Sub-tier approvals / POs','conformance',0,0,2,'SUPPLIER_QUALITY,SECOND_PARTY,VENDOR','SUPPLIER_QUALITY','8.4.3'],
    ['SQ-04','Supplier quality','Measurement','Measurement system and calibration adequate','Is the measurement system adequate and calibration current?','Gauge / calibration register','conformance',1,0,2,'SUPPLIER_QUALITY,SECOND_PARTY,VENDOR','SUPPLIER_QUALITY','7.1.5'],
    ['SQ-05','Supplier quality','Preservation','Product identification, handling and preservation','Are product identification, handling and preservation adequate?','Storage / packing records','conformance',0,0,1,'SUPPLIER_QUALITY,SECOND_PARTY,VENDOR','SUPPLIER_QUALITY','8.5.4'],
];

// ---------------------------------------------------------------------------
//  Schema — additive only. Masters + Criteria Library + Conclusion rules.
// ---------------------------------------------------------------------------
function uvaae_migrate() {
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('audit_type',            'Audit type',              UVAAE_AUDIT_TYPES,     'uvaae');
        lk_ensure_type_map('audit_finding_grade',   'Audit finding grade',     UVAAE_FINDING_GRADES,  'uvaae');
        lk_ensure_type_map('audit_conformance',     'Audit conformance result',UVAAE_CONFORMANCE,     'uvaae');
        lk_ensure_type_map('audit_conclusion',      'Audit conclusion',        UVAAE_CONCLUSIONS,     'uvaae');
        lk_ensure_type_map('audit_method',          'Audit method',            UVAAE_METHODS,         'uvaae');
    }
    $pk = pk_clause();
    // Audit criteria library — same shape as assessment_criteria, EXTENDED with
    // applies_audit_types. Nothing hard-coded downstream reads standard text.
    db()->exec("CREATE TABLE IF NOT EXISTS audit_criteria (
        id $pk, code VARCHAR(40), family VARCHAR(60) DEFAULT '', category VARCHAR(80) DEFAULT '', subcategory VARCHAR(80) DEFAULT '',
        requirement VARCHAR(400) DEFAULT '', question VARCHAR(400) DEFAULT '', evidence_expected VARCHAR(300) DEFAULT '',
        response_type VARCHAR(30) DEFAULT 'conformance', mandatory INT DEFAULT 0, critical INT DEFAULT 0, weight REAL DEFAULT 1,
        risk_category VARCHAR(40) DEFAULT '', applies_audit_types VARCHAR(300) DEFAULT '', applies_industries VARCHAR(300) DEFAULT '',
        applies_products VARCHAR(300) DEFAULT '', standard VARCHAR(120) DEFAULT '', clause VARCHAR(60) DEFAULT '', pack VARCHAR(40) DEFAULT 'GENERAL',
        version INT DEFAULT 1, status VARCHAR(20) DEFAULT 'PUBLISHED', is_system INT DEFAULT 0, sort_order INT DEFAULT 0,
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('audit_criteria', 'code');
    db()->exec("CREATE TABLE IF NOT EXISTS audit_criteria_packs (
        id $pk, code VARCHAR(40), name VARCHAR(120) DEFAULT '', description VARCHAR(400) DEFAULT '',
        kind VARCHAR(30) DEFAULT 'STANDARD', is_system INT DEFAULT 0, active INT DEFAULT 1, sort_order INT DEFAULT 0)");
    idems_unique_index('audit_criteria_packs', 'code');
    // Configurable audit-conclusion rules (§45/§120) — a conclusion is a CONTROLLED
    // consequence of the findings, not a free-text pick. Worst-effect wins.
    db()->exec("CREATE TABLE IF NOT EXISTS audit_conclusion_rules (
        id $pk, code VARCHAR(40), label VARCHAR(200) DEFAULT '', check_type VARCHAR(40) DEFAULT '',
        effect VARCHAR(30) DEFAULT 'CONDITIONAL', param VARCHAR(120) DEFAULT '', applies_types VARCHAR(300) DEFAULT '',
        client_id INT NULL, active INT DEFAULT 1, is_system INT DEFAULT 0, sort_order INT DEFAULT 0)");
    idems_unique_index('audit_conclusion_rules', 'code');

    if (function_exists('setting_get') && !setting_get('uvaae_criteria_seeded_v1', '')) {
        try { uvaae_seed_criteria(); } catch (Throwable $e) { /* never break login/migrate */ }
        if (function_exists('setting_set')) setting_set('uvaae_criteria_seeded_v1', '1');
    }
    if (function_exists('setting_get') && !setting_get('uvaae_rules_seeded_v1', '')) {
        try { uvaae_seed_conclusion_rules(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('uvaae_rules_seeded_v1', '1');
    }
    if (function_exists('setting_get') && !setting_get('uvaae_library_seeded_v1', '')) {
        try { uvaae_seed_library(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('uvaae_library_seeded_v1', '1');
    }
    if (function_exists('setting_get') && !setting_get('uvaae_samples_seeded_v1', '')) {
        try { uvaae_seed_sample_types(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('uvaae_samples_seeded_v1', '1');
    }
}

// ---------------------------------------------------------------------------
//  Audit additions to the URFE LIBRARY — so audit report types assemble from the
//  same Library. Select fields reference masters via lookup:.
// ---------------------------------------------------------------------------
const UVAAE_LIB_FIELDS = [
    ['audit_type',       'Audit type',           'select',      'lookup:audit_type',        'Audit'],
    ['audit_method',     'Audit method',         'multiselect', 'lookup:audit_method',      'Audit'],
    ['audit_standard',   'Audit criteria / standard','text',    '',                          'Audit'],
    ['audit_objective',  'Audit objective',      'textarea',    '',                          'Audit'],
    ['auditee_rep',      'Auditee representative','text',       '',                          'Audit'],
    ['lead_auditor',     'Lead auditor',         'text',        '',                          'Audit'],
    ['audit_team',       'Audit team',           'text',        '',                          'Audit'],
    ['opening_meeting',  'Opening meeting',      'datetime',    '',                          'Audit'],
    ['closing_meeting',  'Closing meeting',      'datetime',    '',                          'Audit'],
    ['prev_audit_ref',   'Previous audit reference','text',     '',                          'Audit'],
    ['audit_conclusion', 'Audit conclusion',     'select',      'lookup:audit_conclusion',  'Conclusion'],
    ['nc_major',         'Major nonconformities','number',      '',                          'Conclusion'],
    ['nc_minor',         'Minor nonconformities','number',      '',                          'Conclusion'],
    ['nc_observations',  'Observations',         'number',      '',                          'Conclusion'],
    ['next_audit_due',   'Next audit due',       'date',        '',                          'Conclusion'],
];
const UVAAE_LIB_TABLES = [
    ['audit_plan',        'Audit plan / agenda', 'Audit',
        "Time / session\nArea / process\nCriteria / clause\nAuditor\nAuditee\nRemarks"],
    ['audit_checklist',   'Audit checklist', 'Audit',
        "Criterion|merge\nCategory\nRequirement\nQuestion\nResult|select|lookup:audit_conformance\nObjective evidence\nEvidence status|select|Not required,Missing,Seen,Verified,Rejected\nRemarks"],
    ['audit_findings',    'Audit findings / nonconformities', 'Findings',
        "Sr. No.|merge\nClause / criteria\nFinding / observation\nGrade|select|lookup:audit_finding_grade\nObjective evidence\nAttached|select|Yes,No,N/A\nCorrective action required\nResponsible\nTarget date|date"],
    ['audit_trail',       'Documents & records examined', 'Evidence',
        "Document / record\nReference / no.\nStatus|select|Seen,Verified,Missing,Not applicable\nRemarks"],
    ['conformance_summary','Conformance summary', 'Conclusion',
        "Area / clause\nRating|select|Conforms,Minor gaps,Major gaps,Not applicable\nMajor NC\nMinor NC\nRemarks"],
];
const UVAAE_LIB_SECTIONS = [
    ['AUD_DETAILS',   'Audit details',              'FIELDS', '', 'Type, method, criteria and objective',
        ['audit_type','audit_method','audit_standard','audit_objective'], []],
    ['AUD_TEAM',      'Audit team & meetings',      'FIELDS', '', 'Lead auditor, team, auditee and meeting times',
        ['lead_auditor','audit_team','auditee_rep','opening_meeting','closing_meeting','prev_audit_ref'], []],
    ['AUD_PLAN',      'Audit plan / agenda',        'TABLE',  '', 'Sessions, areas, criteria and auditors',
        ['audit_plan'], []],
    ['AUD_CHECKLIST', 'Audit checklist',            'TABLE',  '', 'Applicable criteria, conformance result & evidence',
        ['audit_checklist'], []],
    ['AUD_TRAIL',     'Documents & records examined','TABLE', '', 'Objective evidence trail',
        ['audit_trail'], []],
    ['AUD_FINDINGS',  'Audit findings & nonconformities','TABLE','', 'Graded findings — Major/Minor become NCRs on issue',
        ['audit_findings'], []],
    ['AUD_CONFORM',   'Conformance summary',        'TABLE',  '', 'Per-area rating and NC counts',
        ['conformance_summary'], []],
    ['AUD_CONCLUSION','Audit conclusion',           'FIELDS', '', 'Conclusion, NC counts and next audit',
        ['audit_conclusion','nc_major','nc_minor','nc_observations','next_audit_due'], []],
];
function uvaae_seed_library() {
    $pdo = db(); $now = date('c');
    $hasF = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_fields WHERE code=?", [$c]) > 0;
    $insF = $pdo->prepare("INSERT INTO report_lib_fields (code,label,ftype,options,grp,table_cols,is_system,status,version,created_at) VALUES (?,?,?,?,?,?,1,'PUBLISHED',1,?)");
    foreach (UVAAE_LIB_FIELDS as $f) { if ($hasF($f[0])) continue; $insF->execute([$f[0],$f[1],$f[2],$f[3]??'',$f[4]??'','',$now]); }
    foreach (UVAAE_LIB_TABLES as $t) { if ($hasF($t[0])) continue; $insF->execute([$t[0],$t[1],'table','',$t[2]??'',$t[3]??'',$now]); }
    $hasS = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_sections WHERE code=?", [$c]) > 0;
    $insS = $pdo->prepare("INSERT INTO report_lib_sections (code,title,help,section_type,component,is_system,status,version,category,sort_order,created_at) VALUES (?,?,?,?,?,1,'PUBLISHED',1,'Audit',?,?)");
    $insSF = $pdo->prepare("INSERT INTO report_lib_section_fields (section_code,field_code,sort_order) VALUES (?,?,?)");
    $ord = 700;
    foreach (UVAAE_LIB_SECTIONS as $s) { $ord+=10; if ($hasS($s[0])) continue;
        $insS->execute([$s[0],$s[1],$s[4]??'',$s[2]??'FIELDS',$s[3]??'',$ord,$now]);
        $fo=0; foreach (($s[5]??[]) as $fc){ $fo+=10; $insSF->execute([$s[0],$fc,$fo]); } }
}

// Fill an audit's checklist table from the applicable criteria for an audit type
// (+ any extra packs) — the audit is DYNAMICALLY ASSEMBLED at fill time. Rows
// keyed to the checklist field's actual columns (label-matched, order-free).
function uvaae_checklist_prefill_rows($clField, $auditType = null, $extraPacks = null) {
    if (!$clField || !function_exists('idems_table_col_defs')) return [];
    $defs = idems_table_col_defs($clField); $keys = array_keys($defs);
    $col = function($needle) use ($defs){ foreach ($defs as $k=>$d) if (stripos((string)$d['label'],$needle)!==false) return $k; return null; };
    $kC=$col('criterion'); $kCat=$col('category'); $kReq=$col('requirement'); $kQ=$col('question'); $kEv=$col('objective evidence');
    $rows=[];
    foreach (uvaae_criteria($auditType, null, null, $extraPacks) as $c) {
        $r=array_fill_keys($keys,'');
        if($kC)  $r[$kC]=$c['code'];
        if($kCat)$r[$kCat]=(string)$c['category'];
        if($kReq)$r[$kReq]=(string)$c['requirement'];
        if($kQ)  $r[$kQ]=(string)$c['question'];
        if($kEv) $r[$kEv]=(string)$c['evidence_expected'];
        $rows[]=$r;
    }
    return $rows;
}

// Assemble a vendor-audit report type from the Library (§150 — one engine).
function uvaae_assemble_audit_type($code, $name, $sectionCodes, $opts = []) {
    $opts = array_merge(['category' => 'VENDOR', 'finding_enabled' => 1, 'evidence_enabled' => 1], $opts);
    return urfe_assemble_report_type($code, $name, $sectionCodes, $opts);
}

// §147 sample audit configurations — TEN+ configs from ONE engine, sections vary
// by need. This is the §169 critical architectural test made concrete.
const UVAAE_SAMPLE_TYPES = [
    ['UAUD_VENDOR','Vendor / Supplier Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','AUD_PLAN','AUD_CHECKLIST','AUD_TRAIL','AUD_FINDINGS','FINDINGS','AUD_CONFORM','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
    ['UAUD_QMS','ISO 9001 Management-System Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','STANDARDS','AUD_PLAN','AUD_CHECKLIST','AUD_TRAIL','AUD_FINDINGS','AUD_CONFORM','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
    ['UAUD_ISO17020','ISO 17020 Inspection-Body Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','STANDARDS','AUD_CHECKLIST','AUD_TRAIL','AUD_FINDINGS','AUD_CONFORM','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
    ['UAUD_SUPPLIER_Q','Supplier Quality Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','AUD_CHECKLIST','AUD_TRAIL','AUD_FINDINGS','FINDINGS','AUD_CONFORM','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
    ['UAUD_PROCESS','Process Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','AUD_CHECKLIST','AUD_TRAIL','AUD_FINDINGS','AUD_CONFORM','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
    ['UAUD_TECHNICAL','Technical Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','STANDARDS','AUD_CHECKLIST','AUD_TRAIL','AUD_FINDINGS','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
    ['UAUD_HSE','HSE / Safety Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','AUD_CHECKLIST','AUD_TRAIL','AUD_FINDINGS','FINDINGS','AUD_CONFORM','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
    ['UAUD_SPECIAL','Special-Process Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','AUD_CHECKLIST','AUD_TRAIL','AUD_FINDINGS','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
    ['UAUD_REMOTE','Remote / Virtual Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','AUD_PLAN','AUD_CHECKLIST','AUD_TRAIL','AUD_FINDINGS','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
    ['UAUD_FOLLOWUP','Follow-up / Verification Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','AUD_FINDINGS','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
    ['UAUD_SURV','Surveillance Audit',
        ['DOC_CONTROL','VENDOR_INFO','AUD_DETAILS','AUD_TEAM','SCOPE','AUD_CHECKLIST','AUD_FINDINGS','AUD_CONFORM','AUD_CONCLUSION','CONCLUSION','APPROVAL']],
];
function uvaae_seed_sample_types() {
    foreach (UVAAE_SAMPLE_TYPES as $s) {
        if (ops_one("SELECT id FROM report_types WHERE code=?", [$s[0]])) continue;
        uvaae_assemble_audit_type($s[0], $s[1], $s[2], ['subcategory'=>'UVAAE','description'=>'Sample vendor / management-system audit configuration (UVAAE) — editable & removable.']);
    }
}

function uvaae_seed_criteria() {
    $pdo = db(); $now = date('c');
    $packs = [['GENERAL','Universal core','Management-system fundamentals — every audit','CORE',10],
              ['QMS','Quality (ISO 9001)','Quality management-system audits','STANDARD',20],
              ['ISO17020','Inspection body (ISO 17020)','Inspection-body audits','STANDARD',30],
              ['PROCESS','Process','Process / manufacturing / product audits','PROCESS',40],
              ['SPECIAL_PROCESS','Special process','Welding / heat-treat / NDT / coating audits','PROCESS',50],
              ['HSE','HSE','HSE / safety / OHS / EMS audits','HSE',60],
              ['SUPPLIER_QUALITY','Supplier quality','Supplier / second-party quality audits','VENDOR',70]];
    $ip = $pdo->prepare("INSERT INTO audit_criteria_packs (code,name,description,kind,is_system,active,sort_order) VALUES (?,?,?,?,1,1,?)");
    foreach ($packs as $p) if ((int)ops_val("SELECT COUNT(*) FROM audit_criteria_packs WHERE code=?", [$p[0]]) === 0) $ip->execute([$p[0],$p[1],$p[2],$p[3],$p[4]]);
    $ins = $pdo->prepare("INSERT INTO audit_criteria
        (code,family,category,requirement,question,evidence_expected,response_type,mandatory,critical,weight,applies_audit_types,pack,clause,is_system,status,version,sort_order,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,'PUBLISHED',1,?,?)");
    $so = 0;
    foreach (UVAAE_CRITERIA as $c) {
        $so += 10;
        if ((int)ops_val("SELECT COUNT(*) FROM audit_criteria WHERE code=?", [$c[0]]) > 0) continue;
        $ins->execute([$c[0],$c[1],$c[2],$c[3],$c[4],$c[5],$c[6],(int)$c[7],(int)$c[8],(float)$c[9],$c[10],$c[11],$c[12]??'',$so,$now]);
    }
}

// ---------------------------------------------------------------------------
//  Audit CONCLUSION engine (§45/§120). A conclusion is a CONTROLLED consequence
//  of the findings — a Major NC cannot end in "Conforms". Configurable rules
//  gate the conclusion; the engine RECOMMENDS, an authorized person approves.
//  Worst effect wins: NOT_CONFORMS > MAJOR_ACTIONS > CONDITIONAL > MINOR_ACTIONS
//  > SURVEILLANCE > CONFORMS.
// ---------------------------------------------------------------------------
const UVAAE_CONCLUSION_RULES = [
    // [code,label,check_type,effect,param]
    ['CR-MAJOR', 'Major nonconformity raised',          'MAJOR_NC',        'MAJOR_ACTIONS', ''],
    ['CR-CRIT',  'Critical criterion not met',          'CRITICAL_FAIL',   'NOT_CONFORMS',  ''],
    ['CR-MINORS','Minor nonconformities above threshold','MINOR_NC_OVER',  'CONDITIONAL',   '3'],
    ['CR-MINOR', 'Minor nonconformity raised',          'MINOR_NC',        'MINOR_ACTIONS', ''],
    ['CR-MAND',  'Mandatory criterion not verified',    'MANDATORY_MISSING','CONDITIONAL',  ''],
    ['CR-SCORE', 'Conformance score below threshold',   'SCORE_BELOW',     'CONDITIONAL',   '70'],
];
function uvaae_seed_conclusion_rules() {
    $pdo = db();
    $ins = $pdo->prepare("INSERT INTO audit_conclusion_rules (code,label,check_type,effect,param,active,is_system,sort_order) VALUES (?,?,?,?,?,1,1,?)");
    $so=0; foreach (UVAAE_CONCLUSION_RULES as $r){ $so+=10;
        if ((int)ops_val("SELECT COUNT(*) FROM audit_conclusion_rules WHERE code=?", [$r[0]])>0) continue;
        $ins->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$so]); }
}
function uvaae_conclusion_rules() { return ops_all("SELECT * FROM audit_conclusion_rules WHERE active=1 ORDER BY sort_order, code"); }

// Evaluate the audit conclusion from a context. $ctx keys: major_nc(int),
// minor_nc(int), critical_failed(int), mandatory_missing(int), score(0-100|null),
// score_threshold(num). Returns the recommended conclusion + what fired.
function uvaae_conclusion_decision($ctx, $rules = null) {
    if ($rules === null) $rules = uvaae_conclusion_rules();
    $rank = ['NOT_CONFORMS'=>6,'MAJOR_ACTIONS'=>5,'CONDITIONAL'=>4,'MINOR_ACTIONS'=>3,'SURVEILLANCE'=>2,'CONFORMS'=>1];
    $conclusion = 'CONFORMS'; $triggered = [];
    $set = function($eff, $why) use (&$conclusion, &$triggered, $rank) {
        if (($rank[$eff] ?? 0) > ($rank[$conclusion] ?? 0)) $conclusion = $eff;
        $triggered[] = ['effect'=>$eff, 'reason'=>$why];
    };
    foreach ($rules as $r) {
        $ct = $r['check_type']; $fired = false; $p = (float)($r['param']!==''?$r['param']:0);
        if ($ct==='MAJOR_NC'          && (int)($ctx['major_nc']??0) > 0)          $fired = true;
        if ($ct==='CRITICAL_FAIL'     && (int)($ctx['critical_failed']??0) > 0)   $fired = true;
        if ($ct==='MINOR_NC'          && (int)($ctx['minor_nc']??0) > 0)          $fired = true;
        if ($ct==='MINOR_NC_OVER'     && (int)($ctx['minor_nc']??0) > ($p?:3))    $fired = true;
        if ($ct==='MANDATORY_MISSING' && (int)($ctx['mandatory_missing']??0) > 0) $fired = true;
        if ($ct==='SCORE_BELOW'       && ($ctx['score']??null)!==null && (float)$ctx['score'] < ($p?:70)) $fired = true;
        if ($fired) $set($r['effect'], (string)$r['label']);
    }
    return ['conclusion'=>$conclusion, 'triggered'=>$triggered,
            'major_nc'=>(int)($ctx['major_nc']??0), 'minor_nc'=>(int)($ctx['minor_nc']??0), 'score'=>($ctx['score']??null)];
}

// Roll up findings from the audit-findings table into NC counts — the input to
// the conclusion engine and the conformance KPIs. Label-matched, order-free.
function uvaae_findings_rollup($fields, $data) {
    foreach ($fields as $f) {
        if (($f['ftype']??'')!=='table' || ($f['fkey']??'')!=='audit_findings') continue;
        $rows=$data[$f['fkey']]??null; if(!is_array($rows)) return null;
        $defs=idems_table_col_defs($f);
        $kG=null; foreach($defs as $k=>$d){ $l=strtolower((string)$d['label']); if(strpos($l,'grade')!==false||strpos($l,'category')!==false||strpos($l,'severity')!==false){$kG=$k;break;} }
        if($kG===null) return null;
        $c=['major'=>0,'minor'=>0,'observation'=>0,'ofi'=>0,'conforms'=>0];
        foreach($rows as $r){ $rr=(array)$r; $g=strtolower(trim((string)($rr[$kG]??''))); if($g===''||strpos($g,'n/a')!==false||strpos($g,'not applicable')!==false)continue;
            if(strpos($g,'major')!==false)$c['major']++;
            elseif(strpos($g,'minor')!==false)$c['minor']++;
            elseif(strpos($g,'observ')!==false)$c['observation']++;
            elseif(strpos($g,'ofi')!==false||strpos($g,'improv')!==false||strpos($g,'opportun')!==false)$c['ofi']++;
            elseif(strpos($g,'conform')!==false)$c['conforms']++; }
        return $c;
    }
    return null;
}

// The audit context assembled from a report's data (for the conclusion engine).
function uvaae_context_from_audit($doc, $fields = null, $data = null) {
    if ($fields === null) $fields = idems_fields((int)($doc['report_type_id'] ?? 0));
    if ($data === null) { $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = []; }
    $roll = uvaae_findings_rollup($fields, $data) ?: ['major'=>0,'minor'=>0];
    // Critical / mandatory not-met, and a conformance score, from the checklist.
    $criticalFail=0; $mandMissing=0; $scored=0; $scoreSum=0;
    $critByCode=[]; $mandByCode=[];
    foreach (uvaae_criteria((string)($data['audit_type']??'')) as $cr) {
        if(!empty($cr['critical'])) $critByCode[strtoupper((string)$cr['code'])]=true;
        if(!empty($cr['mandatory'])) $mandByCode[strtoupper((string)$cr['code'])]=true;
    }
    foreach ($fields as $f) {
        if (($f['ftype']??'')!=='table' || ($f['fkey']??'')!=='audit_checklist') continue;
        $rows=$data[$f['fkey']]??null; if(!is_array($rows)) break;
        $defs=idems_table_col_defs($f);
        $kC=null;$kR=null; foreach($defs as $k=>$d){ $l=strtolower((string)$d['label']); if($kC===null&&strpos($l,'criterion')!==false)$kC=$k; if($kR===null&&strpos($l,'result')!==false)$kR=$k; }
        if($kR===null) break;
        foreach($rows as $r){ $rr=(array)$r; $res=strtolower(trim((string)($rr[$kR]??''))); if($res===''||strpos($res,'n/a')!==false)continue;
            $code=$kC!==null?strtoupper(trim((string)($rr[$kC]??''))):'';
            $isFail = strpos($res,'major')!==false || (strpos($res,'nc')!==false && strpos($res,'minor')===false ? false : strpos($res,'minor')!==false);
            $isMajor = strpos($res,'major')!==false;
            $notVerified = strpos($res,'not verified')!==false;
            if($isMajor && $code!=='' && isset($critByCode[$code])) $criticalFail++;
            if($notVerified && $code!=='' && isset($mandByCode[$code])) $mandMissing++;
            // conformance score: conforms=100, minor=60, major=20, observation/ofi=80
            $v = strpos($res,'conform')!==false?100:(strpos($res,'major')!==false?20:(strpos($res,'minor')!==false?60:(strpos($res,'not verified')!==false?0:80)));
            $scoreSum+=$v; $scored++;
        }
        break;
    }
    $score = $scored>0 ? round($scoreSum/$scored) : null;
    return ['major_nc'=>(int)$roll['major'],'minor_nc'=>(int)$roll['minor'],
            'critical_failed'=>$criticalFail,'mandatory_missing'=>$mandMissing,'score'=>$score];
}

// The recommended conclusion for a report (thin wrapper used by views / QA).
function uvaae_audit_conclusion($doc, $fields = null, $data = null) {
    $ctx = uvaae_context_from_audit($doc, $fields, $data);
    return array_merge(uvaae_conclusion_decision($ctx), ['context'=>$ctx]);
}

// ---------------------------------------------------------------------------
//  Audit data-integrity QA (§105-type). DETERMINISTIC and ADVISORY — flags
//  contradictions; NEVER changes a finding, grade or conclusion. Called from
//  idems_qa_run (item 16). Self-gates on the audit library fields.
// ---------------------------------------------------------------------------
function uvaae_qa_checks($doc, $fields = null, $data = null) {
    if ($fields === null) $fields = idems_fields((int)($doc['report_type_id'] ?? 0));
    if ($data === null) { $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = []; }
    // Self-gate: only audit reports (have an audit checklist/findings or conclusion).
    $isAudit=false; foreach($fields as $f){ if(in_array(($f['fkey']??''),['audit_checklist','audit_findings','audit_conclusion'],true)){$isAudit=true;break;} }
    if(!$isAudit) return [];
    $out=[];
    $tblRows=function($fkey) use($fields,$data){ foreach($fields as $f) if(($f['fkey']??'')===$fkey && ($f['ftype']??'')==='table'){ $rows=$data[$fkey]??null; if(is_array($rows)) return [$rows,idems_table_col_defs($f),$f['label']??$fkey]; } return [null,[],'']; };
    $col=function($defs,$needle){ foreach($defs as $k=>$d) if(stripos((string)$d['label'],$needle)!==false) return $k; return null; };

    $roll = uvaae_findings_rollup($fields,$data);
    $ctx  = uvaae_context_from_audit($doc,$fields,$data);
    $rec  = uvaae_conclusion_decision($ctx);
    $stated = strtoupper(trim((string)($data['audit_conclusion']??'')));

    // (a) §120 conclusion-vs-findings contradiction — a Major NC cannot end in a
    // "Conforms" conclusion. Advisory: the engine will not change the pick.
    if ($stated!=='' && $roll) {
        $statedConforms = strpos($stated,'CONFORM')!==false && strpos($stated,'NOT')===false && strpos($stated,'MAJOR')===false;
        if ((int)$roll['major']>0 && $statedConforms) {
            $out[]=['sev'=>'high','cat'=>'audit','title'=>'Conclusion says "conforms" but Major nonconformities were raised','loc'=>'Audit conclusion',
                'why'=>(int)$roll['major'].' Major nonconformity(ies) are recorded, so a "conforms" conclusion is contradictory. The recommended conclusion is "'.(UVAAE_CONCLUSIONS[$rec['conclusion']]??$rec['conclusion']).'".',
                'fix'=>'Reconcile the conclusion with the findings, or reclassify the finding. The system will not change either.'];
        }
    }

    // (b) evidence contradiction — a "Conforms" checklist result with its evidence
    // marked missing or rejected.
    [$crows,$cdefs,$clbl]=$tblRows('audit_checklist');
    if (is_array($crows)) {
        $kRes=$col($cdefs,'result'); $kEvSt=$col($cdefs,'evidence status'); $kCrit=array_key_first($cdefs);
        foreach($crows as $i=>$r){ $rr=(array)$r; $res=strtolower(trim((string)($rr[$kRes]??''))); $ev=strtolower(trim((string)($rr[$kEvSt]??'')));
            if(strpos($res,'conform')!==false && in_array($ev,['missing','rejected'],true))
                $out[]=['sev'=>'medium','cat'=>'audit','title'=>'Checklist marked "conforms" but evidence is '.$ev,'loc'=>$clbl.' — row '.($i+1),
                    'why'=>'Criterion "'.trim((string)($rr[$kCrit]??'')).'" is marked conforming but its evidence status is "'.$ev.'". Objective evidence is what makes a conformance defensible.',
                    'fix'=>'Record the objective evidence seen, or change the result. The system will not change it.'];
        }
    }

    // (c) §49 findings-vs-checklist reconciliation — a Major/Minor NC result in the
    // checklist with nothing raised in the findings table (so it will not become an NCR).
    if (is_array($crows) && $roll) {
        $kRes=$col($cdefs,'result'); $clMajor=0;$clMinor=0;
        foreach($crows as $r){ $rr=(array)$r; $res=strtolower(trim((string)($rr[$kRes]??''))); if(strpos($res,'major')!==false)$clMajor++; elseif(strpos($res,'minor')!==false)$clMinor++; }
        if (($clMajor>(int)$roll['major']) || ($clMinor>(int)$roll['minor']))
            $out[]=['sev'=>'medium','cat'=>'audit','title'=>'Checklist shows more nonconformities than the findings table','loc'=>'Audit findings',
                'why'=>'The checklist records '.$clMajor.' Major / '.$clMinor.' Minor NC results but the findings table lists '.(int)$roll['major'].' Major / '.(int)$roll['minor'].' Minor. Only findings-table entries become NCRs on issue.',
                'fix'=>'Raise every nonconformity in the findings table so it flows to the NCR / CAPA register.'];
    }

    // (d) §42 conclusion consistency — stated conclusion milder than the rules
    // recommend (advisory nudge, never blocks).
    if ($stated!=='' && $stated!==$rec['conclusion']) {
        $rank = ['NOT_CONFORMS'=>6,'MAJOR_ACTIONS'=>5,'CONDITIONAL'=>4,'MINOR_ACTIONS'=>3,'SURVEILLANCE'=>2,'CONFORMS'=>1];
        if (($rank[$stated]??9) < ($rank[$rec['conclusion']]??0))
            $out[]=['sev'=>'low','cat'=>'audit','title'=>'Stated conclusion is milder than the findings suggest','loc'=>'Audit conclusion',
                'why'=>'Based on the findings the recommended conclusion is "'.(UVAAE_CONCLUSIONS[$rec['conclusion']]??$rec['conclusion']).'". The stated conclusion is milder. This is advisory only.',
                'fix'=>'Confirm the conclusion is justified by the objective evidence.'];
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  Read / filter helpers.
// ---------------------------------------------------------------------------
function uvaae_audit_types() { return function_exists('lk_options_or') ? lk_options_or('audit_type', UVAAE_AUDIT_TYPES) : UVAAE_AUDIT_TYPES; }
function uvaae_criteria_packs() { return ops_all("SELECT * FROM audit_criteria_packs WHERE active=1 ORDER BY sort_order, name"); }

// The DYNAMICALLY-ASSEMBLED criteria set for an audit (§14/§93-96): the universal
// core PLUS any pack whose applicability matches the audit type. A criterion with
// empty applies_* applies to all. This is what makes an ISO 9001 audit pull the
// QMS pack, a process audit the process pack, an HSE audit the HSE pack — from
// ONE engine, with no hard-coded standard text in the logic.
function uvaae_criteria($auditType = null, $industry = null, $product = null, $extraPacks = null) {
    $rows = ops_all("SELECT * FROM audit_criteria WHERE status<>'ARCHIVED' ORDER BY sort_order, code");
    $at = strtoupper(trim((string)$auditType)); $ind = strtoupper(trim((string)$industry)); $prod = strtoupper(trim((string)$product));
    $match = function($csv, $val) { $csv = trim((string)$csv); if ($csv === '') return true; if ($val === '') return false;
        foreach (explode(',', $csv) as $c) if (strtoupper(trim($c)) === $val) return true; return false; };
    $extra = array_map('strtoupper', (array)($extraPacks ?? []));
    return array_values(array_filter($rows, function($r) use ($match,$at,$ind,$prod,$extra) {
        if ($extra && in_array(strtoupper((string)$r['pack']), $extra, true)) return true;
        return $match($r['applies_audit_types'], $at) && $match($r['applies_industries'], $ind) && $match($r['applies_products'], $prod);
    }));
}

// Prior audits of a vendor (§102/§142) — reuse report_docs (UAUD_* + legacy VAR),
// most recent first, for the comparison / trend / follow-up view.
function uvaae_audit_history($partnerId, $limit = 12) {
    $partnerId=(int)$partnerId; if(!$partnerId) return [];
    return ops_all("SELECT d.id, d.irn, d.type_code, d.issue_date, d.created_at, d.result, d.status
                    FROM report_docs d JOIN report_types rt ON rt.id=d.report_type_id
                    WHERE d.vendor_id=? AND d.deleted=0 AND (d.type_code LIKE 'UAUD\\_%' ESCAPE '\\' OR d.type_code='VAR')
                    ORDER BY COALESCE(d.issue_date,d.created_at) DESC, d.id DESC LIMIT ?", [$partnerId, (int)$limit]);
}
