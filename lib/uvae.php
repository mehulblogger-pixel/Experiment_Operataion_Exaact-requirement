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

    if (function_exists('setting_get') && !setting_get('uvae_criteria_seeded_v1', '')) {
        try { uvae_seed_criteria(); } catch (Throwable $e) { /* never break login/migrate */ }
        if (function_exists('setting_set')) setting_set('uvae_criteria_seeded_v1', '1');
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
