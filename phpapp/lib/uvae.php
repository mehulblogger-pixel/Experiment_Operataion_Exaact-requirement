<?php
// ===========================================================================
//  UVAE — Universal Vendor Assessment, Qualification & Supplier Evaluation
//         Engine  (Phase 4)
// ---------------------------------------------------------------------------
//  Assesses whether an organization is suitable to be approved / qualified /
//  requalified / conditionally qualified / suspended / reinstated. Built on the
//  LOCKED Phase 1-3 foundations and the app's EXISTING vendor platform — it does
//  NOT rebuild any of it:
//    Vendor master / profile / status lifecycle → business_partners +
//      vendor_profiles + vendor_status_events (approval/expiry/reassess)
//    Assessment report + category scoring + recommendation → VASR (idems_build_
//      vendor_assessment) + the scoring engine (idems_score_doc)
//    Vendor-360 / performance → idems_vendor_360 / idems_vendor_performance /
//      idems_vendor_expediting_perf (consumes inspection/release/NCR/expediting)
//    Findings / risk / actions / evidence / workflow / approval / PDF / numbering
//      / versioning → Phase 1 engines
//    Inspection & release history → Phase 2 / Phase 3
//
//  What UVAE adds (genuinely new, reusable): a configurable Assessment Type
//  master and an Assessment CRITERIA LIBRARY whose applicability (vendor type /
//  industry / product / standard) makes each assessment DYNAMICALLY ASSEMBLED —
//  a manufacturer sees manufacturing criteria, a service provider does not, a
//  trader sees source/traceability. Plus (later slices) a per-product
//  qualification matrix, configurable disqualification rules, and consuming the
//  vendor's own performance. Assessment findings are NOT auto-NCRs (§59/§151).
// ===========================================================================

// Assessment Type master (§6) — configurable via the lookup engine (reuses the
// Masters admin UI). code => label.
const UVAE_ASSESSMENT_TYPES = [
    'NEW_PREQUAL'=>'New Vendor Prequalification','SUP_PREQUAL'=>'Supplier Prequalification','QUALIFICATION'=>'Vendor Qualification',
    'TECHNICAL'=>'Technical Assessment','QUALITY'=>'Quality Assessment','COMMERCIAL'=>'Commercial Assessment','HSE'=>'HSE Assessment',
    'CAPABILITY'=>'Capability Assessment','CAPACITY'=>'Capacity Assessment','MFG_CAPABILITY'=>'Manufacturing Capability Assessment',
    'SVC_CAPABILITY'=>'Service Capability Assessment','RISK'=>'Supplier Risk Assessment','FINANCIAL'=>'Financial / Business Stability Assessment',
    'ESG'=>'ESG / Sustainability Assessment','INFOSEC'=>'Information Security Assessment','REGULATORY'=>'Regulatory Compliance Assessment',
    'TRADE'=>'Import / Export Compliance Assessment','PRODUCT_QUAL'=>'Product Qualification Assessment','PROJECT'=>'Project-Specific Vendor Assessment',
    'TENDER'=>'Tender / Bidder Assessment','CRITICAL'=>'Critical Supplier Assessment','HIGH_RISK'=>'High-Risk Supplier Assessment',
    'REASSESS'=>'Existing Vendor Reassessment','REQUAL'=>'Periodic Vendor Requalification','PERFORMANCE'=>'Vendor Performance Assessment',
    'DEVELOPMENT'=>'Vendor Development Assessment','CONDITIONAL'=>'Conditional Qualification Assessment','REINSTATE'=>'Reinstatement Assessment',
    'CHANGE_SCOPE'=>'Change of Scope Assessment','OTHER'=>'Other',
];
// Assessment result master (§69) — the decision vocabulary (distinct from the
// existing vendor_approval_status; this is the per-assessment recommendation).
const UVAE_RESULTS = [
    'QUALIFIED'=>'Qualified','COND_QUALIFIED'=>'Conditionally qualified','PROV_QUALIFIED'=>'Provisionally qualified',
    'PENDING'=>'Pending','NOT_QUALIFIED'=>'Not qualified','REJECTED'=>'Rejected','DISQUALIFIED'=>'Disqualified',
    'UNDER_DEV'=>'Under development','SUSPENDED'=>'Suspended','REASSESS'=>'Reassessment required',
];
// Assessment finding classifications (§59) — NOT NCRs.
const UVAE_FINDING_TYPES = [
    'STRENGTH'=>'Strength','WEAKNESS'=>'Weakness','GAP'=>'Gap','OBSERVATION'=>'Observation','CONCERN'=>'Concern',
    'RISK'=>'Risk','CRITICAL_CONCERN'=>'Critical concern','MISSING_EVIDENCE'=>'Missing evidence','NON_COMPLIANCE'=>'Non-compliance','OPPORTUNITY'=>'Opportunity',
];
// Assessment method (§83).
const UVAE_METHODS = [
    'DESKTOP'=>'Desktop review','QUESTIONNAIRE'=>'Questionnaire','DOC_REVIEW'=>'Document review','REMOTE'=>'Remote assessment',
    'SITE'=>'Site assessment','INTERVIEW'=>'Interview','PERFORMANCE'=>'Performance review','HISTORICAL'=>'Historical data review','COMBINED'=>'Combined','OTHER'=>'Other',
];

// ---------------------------------------------------------------------------
//  Assessment CRITERIA LIBRARY (§116) — the keystone. Applicability by vendor
//  type / industry / product / standard makes assessments dynamically assembled.
//  Row: [code, family, category, requirement, question, evidence, response_type,
//        mandatory, critical, weight, applies_vendor_types(csv|''=all), pack]
//  Universal core applies to ALL; packs (MANUFACTURING/SERVICE/TRADER/…) apply
//  to their vendor types only. Codes are stable; nothing is hard-coded downstream.
// ---------------------------------------------------------------------------
const UVAE_CRITERIA = [
    // ---- UNIVERSAL CORE (applies to every vendor type) ----
    ['LEGAL-01','Legal','Legal & corporate','Valid legal registration & business authorisation','Is the organisation a legally registered, authorised business?','Registration / incorporation certificate','pass_fail',1,1,3,'','GENERAL'],
    ['LEGAL-02','Legal','Legal & corporate','Statutory / tax registrations valid','Are the applicable statutory & tax registrations valid?','GST/VAT/tax registration','pass_fail',1,0,2,'','GENERAL'],
    ['ORG-01','Organization','Organization & management','Defined organisation structure & responsibilities','Is there a clear organisation structure with defined responsibilities?','Org chart','rating',1,0,2,'','GENERAL'],
    ['ORG-02','Organization','Organization & management','Relevant experience & management capability','Does management have relevant industry experience?','Company profile / CVs','rating',0,0,2,'','GENERAL'],
    ['QMS-01','Quality','Quality management','A quality management system is in place','Is a documented quality management system in place?','QMS manual / procedures','rating',1,0,3,'','GENERAL'],
    ['QMS-02','Quality','Quality management','Quality certification (where claimed) is valid','Is the claimed quality certification valid and in scope?','ISO 9001 / other certificate','pass_fail',0,0,2,'','GENERAL'],
    ['QMS-03','Quality','Quality management','Nonconformity & corrective-action process exists','Is there an effective NCR / corrective-action process?','Procedure + records','rating',0,0,2,'','GENERAL'],
    ['DEL-01','Delivery','Delivery capability','Demonstrated on-time delivery capability','Can the vendor demonstrate reliable on-time delivery?','Delivery records / references','rating',0,0,2,'','GENERAL'],
    ['COM-01','Commercial','Commercial capability','Commercial responsiveness & contract understanding','Is the vendor commercially responsive and contract-aware?','Quotation / contract history','rating',0,0,1,'','GENERAL'],
    ['HSE-01','HSE','HSE','HSE policy & legal compliance (where applicable)','Is there an HSE policy and legal compliance?','HSE policy','rating',0,0,1,'','GENERAL'],
    ['EXP-01','Experience','Experience & references','Relevant experience & verifiable references','Does the vendor have relevant, verifiable experience?','Reference projects / contacts','rating',0,0,2,'','GENERAL'],
    ['DOC-01','Documentation','Documentation','Document control & timely submission','Is document control adequate and submission timely?','Sample documentation','rating',0,0,1,'','GENERAL'],
    // ---- MANUFACTURING pack (manufacturers / OEMs only) ----
    ['MFG-01','Manufacturing','Manufacturing capability','Owned/controlled manufacturing facilities & lines','Does the vendor operate the manufacturing facilities & lines?','Facility list / photos','rating',1,0,3,'MANUFACTURER,OEM','MANUFACTURING'],
    ['MFG-02','Manufacturing','Manufacturing capability','Adequate installed & available capacity','Is installed/available capacity adequate for the intended volume?','Capacity data','rating',1,0,3,'MANUFACTURER,OEM','MANUFACTURING'],
    ['MFG-03','Manufacturing','Manufacturing capability','Process control & special-process qualification','Are processes (incl. special processes) controlled & qualified?','WPS/PQR, process records','rating',0,0,2,'MANUFACTURER,OEM','MANUFACTURING'],
    ['MFG-04','Manufacturing','Inspection & testing','In-house inspection & test capability','Is there adequate in-house inspection & test capability?','Equipment list / calibration','rating',0,0,2,'MANUFACTURER,OEM','MANUFACTURING'],
    ['MFG-05','Manufacturing','Traceability','Material & product traceability','Is material & product traceability maintained?','Traceability procedure','rating',0,0,2,'MANUFACTURER,OEM','MANUFACTURING'],
    // ---- SERVICE pack (service providers / contractors / consultants) ----
    ['SVC-01','Service','Service capability','Competent personnel for the service scope','Are personnel competent for the intended service scope?','CVs / certifications','rating',1,0,3,'SERVICE,CONTRACTOR,CONSULTANT','SERVICE'],
    ['SVC-02','Service','Service capability','Methods, procedures & equipment for the service','Are service methods, procedures & equipment adequate?','Method statements','rating',1,0,2,'SERVICE,CONTRACTOR,CONSULTANT','SERVICE'],
    ['SVC-03','Service','Service capability','Service capacity & geographic coverage','Is service capacity & coverage adequate & responsive?','Resource / coverage data','rating',0,0,2,'SERVICE,CONTRACTOR,CONSULTANT','SERVICE'],
    ['SVC-04','Service','Service capability','Subcontractor control (where used)','Are subcontractors controlled and qualified?','Subcontractor procedure','rating',0,0,1,'SERVICE,CONTRACTOR,CONSULTANT','SERVICE'],
    // ---- TRADER pack (distributors / traders / stockists) ----
    ['TRD-01','Trader','Sourcing & traceability','Authorised source / OEM authorisation','Is the vendor an authorised source with OEM traceability?','Authorisation letter','pass_fail',1,1,3,'DISTRIBUTOR','TRADER'],
    ['TRD-02','Trader','Sourcing & traceability','Product documentation & certificates pass-through','Are product documents & certificates provided with traceability?','MTC / test certs','rating',1,0,2,'DISTRIBUTOR','TRADER'],
    ['TRD-03','Trader','Storage & handling','Adequate storage, preservation & handling','Are storage, preservation & handling conditions adequate?','Warehouse details','rating',0,0,2,'DISTRIBUTOR','TRADER'],
    ['TRD-04','Trader','Supply chain','Supplier network & supply continuity','Is the supplier network reliable with supply continuity?','Supplier list','rating',0,0,1,'DISTRIBUTOR','TRADER'],
];

// ---------------------------------------------------------------------------
//  Schema — additive only. Masters + Criteria Library.
// ---------------------------------------------------------------------------
function uvae_migrate() {
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('assessment_type',          'Assessment type',           UVAE_ASSESSMENT_TYPES, 'uvae');
        lk_ensure_type_map('assessment_result',        'Assessment result',         UVAE_RESULTS,          'uvae');
        lk_ensure_type_map('assessment_finding_type',  'Assessment finding type',   UVAE_FINDING_TYPES,    'uvae');
        lk_ensure_type_map('assessment_method',        'Assessment method',         UVAE_METHODS,          'uvae');
    }
    $pk = pk_clause();
    db()->exec("CREATE TABLE IF NOT EXISTS assessment_criteria (
        id $pk, code VARCHAR(40), family VARCHAR(60) DEFAULT '', category VARCHAR(80) DEFAULT '', subcategory VARCHAR(80) DEFAULT '',
        requirement VARCHAR(400) DEFAULT '', question VARCHAR(400) DEFAULT '', evidence_expected VARCHAR(300) DEFAULT '',
        response_type VARCHAR(30) DEFAULT 'rating', mandatory INT DEFAULT 0, critical INT DEFAULT 0, weight REAL DEFAULT 1,
        risk_category VARCHAR(40) DEFAULT '', applies_vendor_types VARCHAR(300) DEFAULT '', applies_industries VARCHAR(300) DEFAULT '',
        applies_products VARCHAR(300) DEFAULT '', standard VARCHAR(120) DEFAULT '', clause VARCHAR(60) DEFAULT '', pack VARCHAR(40) DEFAULT 'GENERAL',
        version INT DEFAULT 1, status VARCHAR(20) DEFAULT 'PUBLISHED', is_system INT DEFAULT 0, sort_order INT DEFAULT 0,
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('assessment_criteria', 'code');
    db()->exec("CREATE TABLE IF NOT EXISTS assessment_criteria_packs (
        id $pk, code VARCHAR(40), name VARCHAR(120) DEFAULT '', description VARCHAR(400) DEFAULT '',
        kind VARCHAR(30) DEFAULT 'VENDOR_TYPE', is_system INT DEFAULT 0, active INT DEFAULT 1, sort_order INT DEFAULT 0)");
    idems_unique_index('assessment_criteria_packs', 'code');
    // Disqualification / decision rules (§68) — configurable gate on the decision.
    db()->exec("CREATE TABLE IF NOT EXISTS assessment_disqual_rules (
        id $pk, code VARCHAR(40), label VARCHAR(200) DEFAULT '', check_type VARCHAR(40) DEFAULT '',
        effect VARCHAR(30) DEFAULT 'PENDING', param VARCHAR(120) DEFAULT '', applies_types VARCHAR(300) DEFAULT '',
        client_id INT NULL, active INT DEFAULT 1, is_system INT DEFAULT 0, sort_order INT DEFAULT 0)");
    idems_unique_index('assessment_disqual_rules', 'code');
    // Per-vendor, per-product qualification register (§74/§125) — the qualification
    // matrix persisted to the vendor so scope survives beyond the report.
    db()->exec("CREATE TABLE IF NOT EXISTS vendor_qualifications (
        id $pk, partner_id INT, product VARCHAR(200) DEFAULT '', category VARCHAR(80) DEFAULT '',
        status VARCHAR(30) DEFAULT '', scope VARCHAR(300) DEFAULT '', conditions VARCHAR(400) DEFAULT '',
        effective_on VARCHAR(20) DEFAULT '', expiry_on VARCHAR(20) DEFAULT '', risk VARCHAR(20) DEFAULT '',
        assessment_id INT NULL, updated_at VARCHAR(30) DEFAULT '')");

    if (function_exists('setting_get') && !setting_get('uvae_criteria_seeded_v1', '')) {
        try { uvae_seed_criteria(); } catch (Throwable $e) { /* never break login/migrate */ }
        if (function_exists('setting_set')) setting_set('uvae_criteria_seeded_v1', '1');
    }
    if (function_exists('setting_get') && !setting_get('uvae_disqual_seeded_v1', '')) {
        try { uvae_seed_disqual_rules(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('uvae_disqual_seeded_v1', '1');
    }
    if (function_exists('setting_get') && !setting_get('uvae_library_seeded_v1', '')) {
        try { uvae_seed_library(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('uvae_library_seeded_v1', '1');
    }
    if (function_exists('setting_get') && !setting_get('uvae_samples_seeded_v1', '')) {
        try { uvae_seed_sample_types(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('uvae_samples_seeded_v1', '1');
    }
}

// ---------------------------------------------------------------------------
//  Assessment additions to the URFE LIBRARY — so vendor-assessment report types
//  assemble from the same Library. Select fields reference masters via lookup:.
// ---------------------------------------------------------------------------
const UVAE_LIB_FIELDS = [
    ['assessment_type',    'Assessment type',      'select',      'lookup:assessment_type',        'Assessment'],
    ['assessment_method',  'Assessment method',    'multiselect', 'lookup:assessment_method',      'Assessment'],
    ['assessment_purpose', 'Assessment purpose',   'textarea',    '',                               'Assessment'],
    ['assessment_result',  'Assessment result',    'select',      'lookup:assessment_result',      'Outcome'],
    ['qualification_decision','Qualification decision','textarea', '',                               'Outcome'],
];
const UVAE_LIB_TABLES = [
    ['questionnaire',      'Assessment questionnaire', 'Assessment',
        "Criterion|merge\nCategory\nRequirement\nQuestion\nResponse|select|Yes,No,Partial,Pass,Fail,N/A\nScore\nEvidence referred\nEvidence status|select|Not required,Missing,Submitted,Accepted,Rejected,Expired\nRemarks"],
    ['cert_matrix',        'Certification matrix', 'Certifications',
        "Certification\nStandard\nCertificate no.\nScope\nIssuer\nIssue date|date\nExpiry|date\nStatus|select|Verified,Not verified,Expired,Invalid,Scope mismatch,Pending\nEvidence referred"],
    ['license_matrix',     'License matrix', 'Certifications',
        "License\nAuthority\nNumber\nScope\nIssue date|date\nExpiry|date\nStatus|select|Valid,Expired,Pending,Not verified\nEvidence referred"],
    ['reference_projects', 'Reference projects', 'Experience',
        "Customer\nProject\nProduct / service\nScope\nYear\nRole\nPerformance\nReference contact\nVerification|select|Verified,Not verified,Pending"],
    ['assessment_findings','Assessment findings', 'Findings',
        "Finding\nType|select|lookup:assessment_finding_type\nCategory\nRequirement\nRisk|select|Low,Medium,High\nAction\nResponsible\nDue|date\nStatus|select|Open,In Progress,Closed"],
    ['qualification_matrix','Qualification matrix', 'Outcome',
        "Product / service\nCategory\nQualification|select|lookup:assessment_result\nScope\nConditions\nEffective|date\nExpiry|date\nRisk|select|Low,Medium,High,Critical\nRemarks"],
];
const UVAE_LIB_SECTIONS = [
    ['VA_DETAILS',    'Assessment details',        'FIELDS', '', 'Type, method and purpose',
        ['assessment_type','assessment_method','assessment_purpose'], []],
    ['VA_CLASSIFY',   'Vendor classification',     'FIELDS', '', 'Vendor type and category',
        ['vendor_type'], []],
    ['VA_QUESTIONS',  'Assessment questionnaire',  'TABLE',  '', 'Applicable criteria, responses, score & evidence',
        ['questionnaire'], []],
    ['VA_CERTS',      'Certifications',            'TABLE',  '', 'Certification matrix with verification',
        ['cert_matrix'], []],
    ['VA_LICENSES',   'Licenses',                  'TABLE',  '', 'License / permit matrix',
        ['license_matrix'], []],
    ['VA_EXPERIENCE', 'Experience & references',   'TABLE',  '', 'Reference projects',
        ['reference_projects'], []],
    ['VA_FINDINGS',   'Assessment findings & gaps','TABLE',  '', 'Strengths, weaknesses, gaps (NOT NCRs)',
        ['assessment_findings'], []],
    ['VA_QUALMATRIX', 'Qualification matrix',      'TABLE',  '', 'Per-product / service qualification scope',
        ['qualification_matrix'], []],
    ['VA_DECISION',   'Recommendation & decision', 'FIELDS', '', 'Result and qualification decision',
        ['assessment_result','qualification_decision'], []],
];
function uvae_seed_library() {
    $pdo = db(); $now = date('c');
    $hasF = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_fields WHERE code=?", [$c]) > 0;
    $insF = $pdo->prepare("INSERT INTO report_lib_fields (code,label,ftype,options,grp,table_cols,is_system,status,version,created_at) VALUES (?,?,?,?,?,?,1,'PUBLISHED',1,?)");
    foreach (UVAE_LIB_FIELDS as $f) { if ($hasF($f[0])) continue; $insF->execute([$f[0],$f[1],$f[2],$f[3]??'',$f[4]??'','',$now]); }
    foreach (UVAE_LIB_TABLES as $t) { if ($hasF($t[0])) continue; $insF->execute([$t[0],$t[1],'table','',$t[2]??'',$t[3]??'',$now]); }
    $hasS = fn($c) => (int)ops_val("SELECT COUNT(*) FROM report_lib_sections WHERE code=?", [$c]) > 0;
    $insS = $pdo->prepare("INSERT INTO report_lib_sections (code,title,help,section_type,component,is_system,status,version,category,sort_order,created_at) VALUES (?,?,?,?,?,1,'PUBLISHED',1,'Vendor',?,?)");
    $insSF = $pdo->prepare("INSERT INTO report_lib_section_fields (section_code,field_code,sort_order) VALUES (?,?,?)");
    $ord = 600;
    foreach (UVAE_LIB_SECTIONS as $s) { $ord+=10; if ($hasS($s[0])) continue;
        $insS->execute([$s[0],$s[1],$s[4]??'',$s[2]??'FIELDS',$s[3]??'',$ord,$now]);
        $fo=0; foreach (($s[5]??[]) as $fc){ $fo+=10; $insSF->execute([$s[0],$fc,$fo]); } }
}

// Fill a report's questionnaire table from the applicable criteria for a vendor
// type — the assessment is DYNAMICALLY ASSEMBLED at fill time. Rows keyed to the
// questionnaire field's actual columns (label-matched, order-independent).
function uvae_questionnaire_prefill_rows($qField, $vendorType = null, $industry = null, $product = null) {
    if (!$qField || !function_exists('idems_table_col_defs')) return [];
    $defs = idems_table_col_defs($qField); $keys = array_keys($defs);
    $col = function($needle) use ($defs){ foreach ($defs as $k=>$d) if (stripos((string)$d['label'],$needle)!==false) return $k; return null; };
    $kC=$col('criterion'); $kCat=$col('category'); $kReq=$col('requirement'); $kQ=$col('question'); $kEv=$col('evidence referred');
    $rows=[];
    foreach (uvae_criteria($vendorType,$industry,$product) as $c) {
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

// Assemble a vendor-assessment report type from the Library (§150 — one engine).
function uvae_assemble_assessment_type($code, $name, $sectionCodes, $opts = []) {
    $opts = array_merge(['category' => 'VENDOR', 'finding_enabled' => 1, 'evidence_enabled' => 1], $opts);
    return urfe_assemble_report_type($code, $name, $sectionCodes, $opts);
}

// §134 sample assessment configurations — one engine, sections vary by need.
const UVAE_SAMPLE_TYPES = [
    ['UVA_PREQUAL','Supplier Prequalification',
        ['DOC_CONTROL','VENDOR_INFO','VA_CLASSIFY','VA_DETAILS','SCOPE','VA_QUESTIONS','VA_CERTS','VA_LICENSES','VA_EXPERIENCE','VA_FINDINGS','FINDINGS','VA_QUALMATRIX','VA_DECISION','CONCLUSION','APPROVAL']],
    ['UVA_MFG','Manufacturer Capability Assessment',
        ['DOC_CONTROL','VENDOR_INFO','VA_CLASSIFY','VA_DETAILS','SCOPE','VA_QUESTIONS','VA_CERTS','VA_EXPERIENCE','VA_FINDINGS','VA_QUALMATRIX','VA_DECISION','CONCLUSION','APPROVAL']],
    ['UVA_SVC','Service Provider Assessment',
        ['DOC_CONTROL','VENDOR_INFO','VA_CLASSIFY','VA_DETAILS','SCOPE','VA_QUESTIONS','VA_CERTS','VA_EXPERIENCE','VA_FINDINGS','VA_QUALMATRIX','VA_DECISION','CONCLUSION','APPROVAL']],
    ['UVA_TRADER','Trader / Distributor Assessment',
        ['DOC_CONTROL','VENDOR_INFO','VA_CLASSIFY','VA_DETAILS','VA_QUESTIONS','VA_CERTS','VA_FINDINGS','VA_QUALMATRIX','VA_DECISION','CONCLUSION','APPROVAL']],
    ['UVA_REASSESS','Existing Vendor Reassessment',
        ['DOC_CONTROL','VENDOR_INFO','VA_CLASSIFY','VA_DETAILS','VA_QUESTIONS','VA_FINDINGS','VA_QUALMATRIX','VA_DECISION','CONCLUSION','APPROVAL']],
];
function uvae_seed_sample_types() {
    foreach (UVAE_SAMPLE_TYPES as $s) {
        if (ops_one("SELECT id FROM report_types WHERE code=?", [$s[0]])) continue;
        uvae_assemble_assessment_type($s[0], $s[1], $s[2], ['subcategory'=>'UVAE','description'=>'Sample vendor-assessment configuration (UVAE) — editable & removable.']);
    }
}

function uvae_seed_criteria() {
    $pdo = db(); $now = date('c');
    $packs = [['GENERAL','Universal core','Applies to every vendor type','CORE',10],
              ['MANUFACTURING','Manufacturing','Manufacturers / OEMs','VENDOR_TYPE',20],
              ['SERVICE','Service','Service providers / contractors','VENDOR_TYPE',30],
              ['TRADER','Trader / distributor','Traders / distributors / stockists','VENDOR_TYPE',40]];
    $ip = $pdo->prepare("INSERT INTO assessment_criteria_packs (code,name,description,kind,is_system,active,sort_order) VALUES (?,?,?,?,1,1,?)");
    foreach ($packs as $p) if ((int)ops_val("SELECT COUNT(*) FROM assessment_criteria_packs WHERE code=?", [$p[0]]) === 0) $ip->execute([$p[0],$p[1],$p[2],$p[3],$p[4]]);
    $ins = $pdo->prepare("INSERT INTO assessment_criteria
        (code,family,category,requirement,question,evidence_expected,response_type,mandatory,critical,weight,applies_vendor_types,pack,is_system,status,version,sort_order,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,'PUBLISHED',1,?,?)");
    $so = 0;
    foreach (UVAE_CRITERIA as $c) {
        $so += 10;
        if ((int)ops_val("SELECT COUNT(*) FROM assessment_criteria WHERE code=?", [$c[0]]) > 0) continue;
        $ins->execute([$c[0],$c[1],$c[2],$c[3],$c[4],$c[5],$c[6],(int)$c[7],(int)$c[8],(float)$c[9],$c[10],$c[11],$so,$now]);
    }
}

// ---------------------------------------------------------------------------
//  Qualification DECISION engine (§67/§68/§70). A score does NOT automatically
//  equal qualification — configurable disqualification rules gate the decision,
//  and a single critical failure can override the score if policy says so. The
//  engine RECOMMENDS; an authorized person approves (reuses the existing vendor
//  status lifecycle idems_vendor_status_from / idems_vendor_apply_assessment).
// ---------------------------------------------------------------------------
const UVAE_DISQUAL_RULES = [
    // [code,label,check_type,effect,param]  effect: NOT_QUALIFIED|PENDING|CONDITIONAL|MGMT_REVIEW
    ['DQ-CRIT',   'Critical criterion failed',        'CRITICAL_FAIL',    'NOT_QUALIFIED', ''],
    ['DQ-CERT',   'Mandatory certificate expired/invalid','CERT_INVALID', 'PENDING',       ''],
    ['DQ-MAND',   'Mandatory item missing',           'MANDATORY_MISSING','CONDITIONAL',   ''],
    ['DQ-RISK',   'Critical risk identified',         'CRITICAL_RISK',    'MGMT_REVIEW',   ''],
    ['DQ-SCORE',  'Score below qualification threshold','SCORE_BELOW',    'NOT_QUALIFIED', '60'],
];
function uvae_seed_disqual_rules() {
    $pdo = db();
    $ins = $pdo->prepare("INSERT INTO assessment_disqual_rules (code,label,check_type,effect,param,active,is_system,sort_order) VALUES (?,?,?,?,?,1,1,?)");
    $so=0; foreach (UVAE_DISQUAL_RULES as $r){ $so+=10;
        if ((int)ops_val("SELECT COUNT(*) FROM assessment_disqual_rules WHERE code=?", [$r[0]])>0) continue;
        $ins->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$so]); }
}
function uvae_disqual_rules() { return ops_all("SELECT * FROM assessment_disqual_rules WHERE active=1 ORDER BY sort_order, code"); }

// Evaluate the qualification decision from a context. $ctx keys: score(0-100|null),
// critical_failed(int), mandatory_missing(int), cert_invalid(int), critical_risk(int),
// allow_conditional(bool), qualify_threshold(num), conditional_threshold(num).
// Effect severity (worst wins): NOT_QUALIFIED > PENDING > MGMT_REVIEW > CONDITIONAL > QUALIFIED.
function uvae_qualification_decision($ctx, $rules = null) {
    if ($rules === null) $rules = uvae_disqual_rules();
    $rank = ['NOT_QUALIFIED'=>5,'PENDING'=>4,'MGMT_REVIEW'=>3,'CONDITIONAL'=>2,'QUALIFIED'=>1];
    $allowCond = !empty($ctx['allow_conditional']);
    $decision = 'QUALIFIED'; $triggered = [];
    $set = function($eff, $why) use (&$decision, &$triggered, $rank) {
        if (($rank[$eff] ?? 0) > ($rank[$decision] ?? 0)) $decision = $eff;
        $triggered[] = ['effect'=>$eff, 'reason'=>$why];
    };
    foreach ($rules as $r) {
        $ct = $r['check_type']; $fired = false;
        if ($ct==='CRITICAL_FAIL'     && (int)($ctx['critical_failed']??0) > 0) $fired = true;
        if ($ct==='CERT_INVALID'      && (int)($ctx['cert_invalid']??0) > 0)    $fired = true;
        if ($ct==='MANDATORY_MISSING' && (int)($ctx['mandatory_missing']??0) > 0) $fired = true;
        if ($ct==='CRITICAL_RISK'     && (int)($ctx['critical_risk']??0) > 0)   $fired = true;
        if ($ct==='SCORE_BELOW'       && ($ctx['score']??null)!==null && (float)$ctx['score'] < (float)($r['param']!==''?$r['param']:60)) $fired = true;
        if (!$fired) continue;
        $eff = $r['effect'];
        // A missing-mandatory item downgrades to CONDITIONAL only if policy allows;
        // otherwise it is PENDING (§20/§140).
        if ($eff==='CONDITIONAL' && !$allowCond) $eff = 'PENDING';
        $set($eff, (string)$r['label']);
    }
    // Score-based baseline (only if nothing worse fired and a score exists).
    if ($decision==='QUALIFIED' && ($ctx['score']??null)!==null) {
        $qt=(float)($ctx['qualify_threshold']??75); $ctL=(float)($ctx['conditional_threshold']??60);
        $s=(float)$ctx['score'];
        if ($s < $ctL) $set('NOT_QUALIFIED','Score '.$s.' below conditional threshold');
        elseif ($s < $qt) $set($allowCond?'CONDITIONAL':'PENDING','Score '.$s.' below qualification threshold');
    }
    return ['decision'=>$decision, 'triggered'=>$triggered, 'score'=>($ctx['score']??null)];
}

// Per-product qualification rollup (§73/§74/§146) from the qualification_matrix
// table. Keeps each product's scope separate; the overall is never "all qualified"
// if any product is not.
function uvae_qualification_rollup($fields, $data) {
    foreach ($fields as $f) {
        if (($f['ftype']??'')!=='table' || ($f['fkey']??'')!=='qualification_matrix') continue;
        $rows=$data[$f['fkey']]??null; if(!is_array($rows)||!$rows) return null;
        $defs=idems_table_col_defs($f);
        $kProd=null;$kQ=null; foreach($defs as $k=>$d){ $l=strtolower((string)$d['label']); if(strpos($l,'product')!==false||strpos($l,'service')!==false)$kProd=$k; if(strpos($l,'qualif')!==false&&$kQ===null)$kQ=$k; }
        if($kQ===null) return null;
        $items=[]; $c=['qualified'=>0,'conditional'=>0,'not'=>0,'other'=>0];
        foreach($rows as $r){ $rr=(array)$r; $q=strtolower(trim((string)($rr[$kQ]??''))); if($q==='')continue;
            $st = strpos($q,'not')!==false||strpos($q,'reject')!==false||strpos($q,'disqual')!==false ? 'not'
                : (strpos($q,'condition')!==false||strpos($q,'provision')!==false ? 'conditional'
                : (strpos($q,'qualif')!==false||strpos($q,'accept')!==false ? 'qualified' : 'other'));
            $c[$st]++; $items[]=['product'=>trim((string)($rr[$kProd]??'')),'status'=>$rr[$kQ]??'']; }
        $overall = $c['not']>0 ? 'PARTIAL' : ($c['conditional']>0 ? 'COND_QUALIFIED' : ($c['qualified']>0 ? 'QUALIFIED' : 'PENDING'));
        return ['counts'=>$c,'overall'=>$overall,'items'=>$items];
    }
    return null;
}

// Suspension / reinstatement (§99/§100) — reuse the existing vendor status
// lifecycle (vendor_profiles + vendor_status_events); never delete history.
function uvae_vendor_set_status($partnerId, $newStatus, $reason, $source = 'ASSESSMENT', $reportDocId = null) {
    $partnerId=(int)$partnerId; if(!$partnerId) return;
    if (function_exists('idems_vendor_profile_ensure')) idems_vendor_profile_ensure($partnerId);
    $old = (string)ops_val("SELECT approval_status FROM vendor_profiles WHERE partner_id=?", [$partnerId]);
    db()->prepare("UPDATE vendor_profiles SET approval_status=?, updated_at=?, updated_by=? WHERE partner_id=?")
        ->execute([$newStatus, date('c'), (function_exists('current_user')?(string)(current_user()['username']??'system'):'system'), $partnerId]);
    try { db()->prepare("INSERT INTO vendor_status_events (partner_id, old_status, new_status, source, report_doc_id, score, reason, actor, at) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$partnerId,$old,$newStatus,$source,$reportDocId,null,$reason,(function_exists('current_user')?(string)(current_user()['username']??'system'):'system'),date('c')]); } catch (Throwable $e) {}
}
function uvae_vendor_suspend($partnerId, $reason, $reportDocId=null){ uvae_vendor_set_status($partnerId,'SUSPENDED',$reason,'SUSPENSION',$reportDocId); }
function uvae_vendor_reinstate($partnerId, $reason, $newStatus='APPROVED', $reportDocId=null){ uvae_vendor_set_status($partnerId,$newStatus,$reason,'REINSTATEMENT',$reportDocId); }

// Prior vendor assessments (§102/§142) — reuse report_docs (VASR + UVA_* types),
// most recent first, for the comparison/trend view.
function uvae_assessment_history($partnerId, $limit = 12) {
    $partnerId=(int)$partnerId; if(!$partnerId) return [];
    return ops_all("SELECT d.id, d.irn, d.type_code, d.issue_date, d.created_at, d.result, d.status
                    FROM report_docs d JOIN report_types rt ON rt.id=d.report_type_id
                    WHERE d.vendor_id=? AND d.deleted=0 AND (d.type_code LIKE 'UVA\\_%' ESCAPE '\\' OR d.type_code='VASR')
                    ORDER BY COALESCE(d.issue_date,d.created_at) DESC, d.id DESC LIMIT ?", [$partnerId, (int)$limit]);
}

// Consume the vendor's OWN performance (§38/§90/§143) — reuse the existing
// Vendor-360 / performance engines; do NOT re-enter or duplicate the data.
function uvae_vendor_performance_context($partnerId) {
    $partnerId=(int)$partnerId; if(!$partnerId) return [];
    $ctx=[];
    if (function_exists('idems_vendor_performance')) { try { $p=idems_vendor_performance($partnerId); if(is_array($p)) $ctx['performance']=$p; } catch(Throwable $e){} }
    if (function_exists('idems_vendor_expediting_perf')) { try { $x=idems_vendor_expediting_perf($partnerId); if(is_array($x)) $ctx['expediting']=$x; } catch(Throwable $e){} }
    if (function_exists('idems_vendor_360')) { try { $v=idems_vendor_360($partnerId); if(is_array($v)) { $ctx['reports']=count($v['reports']??[]); $ctx['ncrs']=count($v['ncrs']??[]); $ctx['complaints']=count($v['complaints']??[]); } } catch(Throwable $e){} }
    return $ctx;
}

// ---------------------------------------------------------------------------
//  Assessment data-integrity / AI-cross-check (§105-109). DETERMINISTIC and
//  ADVISORY — flags contradictions; NEVER changes an answer, a score or the
//  qualification decision (§109/§151). Called from idems_qa_run. Self-gates.
// ---------------------------------------------------------------------------
function uvae_qa_checks($doc, $fields = null, $data = null) {
    if ($fields === null) $fields = idems_fields((int)($doc['report_type_id'] ?? 0));
    if ($data === null) { $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = []; }
    // Self-gate: only assessment reports (have a questionnaire or result field).
    $isAssess=false; foreach($fields as $f){ if(in_array(($f['fkey']??''),['questionnaire','assessment_result'],true)){$isAssess=true;break;} }
    if(!$isAssess) return [];
    $out=[]; $today=date('Y-m-d');
    $tblRows=function($fkey) use($fields,$data){ foreach($fields as $f) if(($f['fkey']??'')===$fkey && ($f['ftype']??'')==='table'){ $rows=$data[$fkey]??null; if(is_array($rows)) return [$rows,idems_table_col_defs($f),$f['label']??$fkey]; } return [null,[],'']; };
    $col=function($defs,$needle){ foreach($defs as $k=>$d) if(stripos((string)$d['label'],$needle)!==false) return $k; return null; };

    // (a) §85 expired / invalid certificates & licenses (deterministic from dates/status).
    foreach (['cert_matrix'=>'certificate','license_matrix'=>'license'] as $fk=>$word) {
        [$rows,$defs,$lbl]=$tblRows($fk); if(!is_array($rows)) continue;
        $kExp=$col($defs,'expiry'); $kSt=$col($defs,'status'); $kName=array_key_first($defs);
        foreach($rows as $i=>$r){ $rr=(array)$r; $nm=trim((string)($rr[$kName]??'')); if($nm==='')continue;
            $st=strtolower(trim((string)($rr[$kSt]??''))); $exp=trim((string)($rr[$kExp]??''));
            $expired = ($exp!=='' && strtotime($exp) && strtotime($exp) < strtotime($today)) || strpos($st,'expired')!==false || strpos($st,'invalid')!==false || strpos($st,'mismatch')!==false;
            if($expired) $out[]=['sev'=>'high','cat'=>'assessment','title'=>'A '.$word.' appears expired or invalid','loc'=>$lbl.' — row '.($i+1),
                'why'=>ucfirst($word).' "'.$nm.'"'.($exp!==''?' expiry '.$exp:'').($st!==''?' (status: '.$st.')':'').'. Verify current validity before qualifying.',
                'fix'=>'Request a current '.$word.' or record a clarification; do not treat an expired '.$word.' as valid.'];
        }
    }

    // (b) §106/§144 evidence contradiction — a positive questionnaire response with
    // its evidence marked missing/rejected/expired.
    [$qrows,$qdefs,$qlbl]=$tblRows('questionnaire');
    if (is_array($qrows)) {
        $kResp=$col($qdefs,'response'); $kEvSt=$col($qdefs,'evidence status'); $kCrit=array_key_first($qdefs);
        foreach($qrows as $i=>$r){ $rr=(array)$r; $resp=strtolower(trim((string)($rr[$kResp]??''))); $ev=strtolower(trim((string)($rr[$kEvSt]??'')));
            $positive = in_array($resp,['yes','pass'],true);
            $badEv = in_array($ev,['missing','rejected','expired'],true);
            if($positive && $badEv) $out[]=['sev'=>'high','cat'=>'assessment','title'=>'Questionnaire response is positive but its evidence is '.$ev,'loc'=>$qlbl.' — row '.($i+1),
                'why'=>'Criterion "'.trim((string)($rr[$kCrit]??'')).'" is answered "'.$resp.'" but the evidence status is "'.$ev.'". Verify before relying on it.',
                'fix'=>'Obtain valid evidence or raise a clarification. The system will not change the response.'];
        }
    }

    // (c) §107/§145 performance contradiction — a strong delivery/quality claim vs
    // the vendor's OWN recorded performance (reused, not re-entered).
    $vid=(int)($doc['vendor_id']??0);
    if ($vid) { $pc=uvae_vendor_performance_context($vid);
        $rel=$pc['expediting']['commitments']['reliability_pct']??null;
        if ($rel!==null && $rel<50 && is_array($qrows)) {
            $kResp=$col($qdefs,'response'); $kCat=$col($qdefs,'category');
            foreach($qrows as $r){ $rr=(array)$r; $cat=strtolower(trim((string)($rr[$kCat]??''))); $resp=strtolower(trim((string)($rr[$kResp]??'')));
                if(strpos($cat,'delivery')!==false && in_array($resp,['yes','pass'],true)){
                    $out[]=['sev'=>'medium','cat'=>'assessment','title'=>'Delivery claim differs from recorded performance','loc'=>'Assessment questionnaire',
                        'why'=>'The delivery capability is rated positively, but the vendor\'s recorded commitment reliability is '.rtrim(rtrim(number_format($rel,1),'0'),'.').'%. Consider verification.',
                        'fix'=>'Reconcile the assessment with the vendor\'s recorded performance.']; break; }
            }
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  Read / filter helpers.
// ---------------------------------------------------------------------------
function uvae_assessment_types() { return function_exists('lk_options_or') ? lk_options_or('assessment_type', UVAE_ASSESSMENT_TYPES) : UVAE_ASSESSMENT_TYPES; }
function uvae_criteria_packs() { return ops_all("SELECT * FROM assessment_criteria_packs WHERE active=1 ORDER BY sort_order, name"); }

// The DYNAMICALLY-ASSEMBLED criteria set for a vendor (§3/§135-138): the universal
// core PLUS any pack whose applicability matches the vendor type / industry /
// product. A criterion with empty applies_* applies to all. This is what makes a
// manufacturer see manufacturing criteria and a service provider not.
function uvae_criteria($vendorType = null, $industry = null, $product = null, $extraPacks = null) {
    $rows = ops_all("SELECT * FROM assessment_criteria WHERE status<>'ARCHIVED' ORDER BY sort_order, code");
    $vt = strtoupper(trim((string)$vendorType)); $ind = strtoupper(trim((string)$industry)); $prod = strtoupper(trim((string)$product));
    $match = function($csv, $val) { $csv = trim((string)$csv); if ($csv === '') return true; if ($val === '') return false;
        foreach (explode(',', $csv) as $c) if (strtoupper(trim($c)) === $val) return true; return false; };
    $extra = array_map('strtoupper', (array)($extraPacks ?? []));
    return array_values(array_filter($rows, function($r) use ($match,$vt,$ind,$prod,$extra) {
        // An explicitly-requested extra pack is always included.
        if ($extra && in_array(strtoupper((string)$r['pack']), $extra, true)) return true;
        return $match($r['applies_vendor_types'], $vt) && $match($r['applies_industries'], $ind) && $match($r['applies_products'], $prod);
    }));
}
