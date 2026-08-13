<?php
// ============================================================================
//  IDEMS — Intelligent Inspection Documentation, Reporting & Endorsement engine.
//  PHASE 1 (foundation): report-type registry, configurable IRN numbering,
//  report-instance model with immutable finalize, the compliance audit log,
//  and the Document Register.  Later phases add the no-code builder, workflow,
//  auto-signatures/timestamps, client formats, endorsement, AI, etc.
//
//  Reuses the existing masters (offices / clients / vendors / calls), the
//  scope model, PDF engine, approval-chain and AI seam — nothing duplicated.
// ============================================================================

// ---- Standard TPIA report catalogue (Part 1). Seeded once; admin adds more. --
const IDEMS_REPORT_SEED = [
    // [code, name, category]
    ['DIR','Daily Inspection Report','TPIA_REPORT'],
    ['DVR','Daily Visit Report','TPIA_REPORT'],
    ['FLR','Flash Report','TPIA_REPORT'],
    ['IR','Inspection Report','TPIA_REPORT'],
    ['SIR','Stage Inspection Report','TPIA_REPORT'],
    ['FIR','Final Inspection Report','TPIA_REPORT'],
    ['RN','Release Note','TPIA_REPORT'],
    ['IRN','Inspection Release Note','TPIA_REPORT'],
    ['COC','Certificate of Conformity','TPIA_REPORT'],
    ['TCRV','Test Certificate Review','TPIA_REPORT'],
    ['PHOTO','Photographic Report','TPIA_REPORT'],
    ['DIM','Dimensional Report','TPIA_REPORT'],
    ['IC','Inspection Certificate','TPIA_REPORT'],
    ['WC','Witness Certificate','TPIA_REPORT'],
    ['HC','Hold Certificate','TPIA_REPORT'],
    ['SUR','Surveillance Report','TPIA_REPORT'],
    ['ER','Expediting Report','TPIA_REPORT'],
    ['MPR','Manufacturing Progress Report','TPIA_REPORT'],
    ['VAR','Vendor Audit Report','TPIA_REPORT'],
    ['VASR','Vendor Assessment Report','TPIA_REPORT'],
    ['FAR','Factory Assessment Report','TPIA_REPORT'],
    ['STIR','Site Inspection Report','TPIA_REPORT'],
    ['NCR','Non-Conformance Report','TPIA_REPORT'],
    ['OBR','Observation Report','TPIA_REPORT'],
    ['DEV','Deviation Report','TPIA_REPORT'],
    ['TCR','Technical Clarification Report','TPIA_REPORT'],
    ['CAVR','Corrective Action Verification Report','TPIA_REPORT'],
    ['PPR','Punch Point Report','TPIA_REPORT'],
    ['RIR','Re-Inspection Report','TPIA_REPORT'],
    ['TS','Time Sheet','ADMIN'],
    ['ATR','Attendance Report','ADMIN'],
    ['TVR','Travel Report','ADMIN'],
    ['EXP','Expense Statement','ADMIN'],
    ['WS','Weekly Summary','SUMMARY'],
    ['FNR','Fortnightly Progress Report','SUMMARY'],
    ['MPGR','Monthly Progress Report','SUMMARY'],
    ['CSR','Client Summary Report','SUMMARY'],
    ['PCR','Project Closure Report','SUMMARY'],
];
const IDEMS_CATEGORIES = ['TPIA_REPORT'=>'TPIA report','ENDORSEMENT'=>'Manufacturer endorsement','ADMIN'=>'Timesheet / admin','SUMMARY'=>'Summary / periodic'];
// Report-instance lifecycle.
const IDEMS_STATUS = ['DRAFT'=>'Draft','SUBMITTED'=>'Submitted','UNDER_REVIEW'=>'Under review','APPROVED'=>'Approved','ISSUED'=>'Issued','REJECTED'=>'Sent back','ARCHIVED'=>'Archived'];
// Technical vetting / debriefing states (distinct from the approval chain).
const IDEMS_VET_STATUS = ['VETTED'=>'Vetted','RETURNED'=>'Returned for correction','DEBRIEFED'=>'Debriefed'];
const IDEMS_OPEN_STATES = ['DRAFT','SUBMITTED','UNDER_REVIEW','REJECTED'];
const IDEMS_RESULTS = ['ACCEPTED'=>'Accepted','ACCEPTED_COND'=>'Accepted with observations','REJECTED'=>'Rejected','HOLD'=>'Hold','NA'=>'Not applicable'];
const IDEMS_RELEASE = ['RELEASED'=>'Released','RELEASED_COND'=>'Released with observations','NOT_RELEASED'=>'Not released','PENDING'=>'Pending'];

// §WO-6 — the completion deliverables. On the last day of an inspection the job
// needs a Final Inspection Report (FIR) and a Release Note (RN / IRN) before it
// can be closed. The daily reports during the run are handled per visit day
// (WO-8). Owner can turn this gate off if a job type does not conclude with a
// release note (Settings → Check-in / reporting).
function require_final_docs_on_close() {
    return (function_exists('setting_get') ? setting_get('require_final_docs_on_close', '1') : '1') === '1';
}
function job_final_docs_missing($job) {
    if (!require_final_docs_on_close()) return [];
    if (($job['reporting_frequency'] ?? 'NOREPORT') === 'NOREPORT') return [];
    $jobId = (int)($job['id'] ?? 0); if (!$jobId) return [];
    $fir = 0; $rn = 0;
    try {
        $fir = (int)ops_val("SELECT COUNT(*) FROM report_docs WHERE job_id=? AND COALESCE(deleted,0)=0 AND type_code='FIR'", [$jobId]);
        $rn  = (int)ops_val("SELECT COUNT(*) FROM report_docs WHERE job_id=? AND COALESCE(deleted,0)=0 AND type_code IN ('RN','IRN')", [$jobId]);
    } catch (Throwable $e) { return []; }   // reports not in use on this install
    $miss = [];
    if (!$fir) $miss[] = 'a Final Inspection Report';
    if (!$rn)  $miss[] = 'a Release Note';
    return $miss;
}

// ---------------------------------------------------------------------------
//  Schema
// ---------------------------------------------------------------------------
function idems_migrate() {
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_types (
        id $pk, code VARCHAR(16), name VARCHAR(150), category VARCHAR(20) DEFAULT 'TPIA_REPORT',
        active INT DEFAULT 1, is_system INT DEFAULT 0, sort_order INT DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Running-serial counters, one row per numbering scope (the IRN prefix before the serial).
    $pdo->exec("CREATE TABLE IF NOT EXISTS idems_counters (
        id $pk, scope_key VARCHAR(200), last_serial INT DEFAULT 0, updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('idems_counters', 'scope_key');
    // Report instances (documents in the register).
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_docs (
        id $pk, irn VARCHAR(120), report_type_id INT NULL, type_code VARCHAR(16) DEFAULT '', title VARCHAR(255) DEFAULT '',
        client_id INT NULL, vendor_id INT NULL, call_id INT NULL, job_id INT NULL,
        company_code VARCHAR(20) DEFAULT '', branch_code VARCHAR(20) DEFAULT '', client_code VARCHAR(20) DEFAULT '',
        project_code VARCHAR(40) DEFAULT '', project_name VARCHAR(200) DEFAULT '', fy_label VARCHAR(12) DEFAULT '', serial INT DEFAULT 0,
        office_id INT NULL, sbu VARCHAR(20) DEFAULT '',
        po_ref VARCHAR(80) DEFAULT '', drawing_no VARCHAR(80) DEFAULT '', drawing_rev VARCHAR(30) DEFAULT '',
        qap_rev VARCHAR(30) DEFAULT '', standards VARCHAR(255) DEFAULT '', location VARCHAR(200) DEFAULT '',
        product_category VARCHAR(80) DEFAULT '', material_grade VARCHAR(80) DEFAULT '',
        inspector_id INT NULL, approver_user_id INT NULL,
        inspection_date VARCHAR(20) DEFAULT '', issue_date VARCHAR(20) DEFAULT '',
        result VARCHAR(20) DEFAULT '', release_status VARCHAR(20) DEFAULT '',
        status VARCHAR(20) DEFAULT 'DRAFT', data MEDIUMTEXT, remarks TEXT,
        rev INT DEFAULT 0, finalized INT DEFAULT 0, finalized_at VARCHAR(30) DEFAULT '', finalized_by VARCHAR(150) DEFAULT '',
        submitted_at VARCHAR(30) DEFAULT '', approved_at VARCHAR(30) DEFAULT '', approved_by VARCHAR(150) DEFAULT '',
        deleted INT DEFAULT 0, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('report_docs', 'irn');
    // Revision lineage: a reissued report links back to the one it revises, and
    // the older one points forward to its successor. Added by upgrade.
    if (function_exists('ensure_column')) {
        ensure_column('report_docs', 'revises_id', "INT NULL");
        ensure_column('report_docs', 'revised_by_id', "INT NULL");
        // Content seal: a hash of the report's own content, frozen at issue, so a
        // later change to the report (not just its evidence) is detectable at /verify.
        ensure_column('report_docs', 'content_seal', "VARCHAR(64) DEFAULT ''");
        // §33 — presentation frozen at issue. So editing a form or a company Word
        // template later can NEVER change how an already-issued report looks. We
        // keep the exact form schema (sections+fields) and the exact template used.
        ensure_column('report_docs', 'frozen_schema', 'LONGTEXT');          // JSON {sections, fields}
        ensure_column('report_docs', 'frozen_template_json', 'LONGTEXT');   // the picked template row incl its .docx bytes
        ensure_column('report_docs', 'frozen_at', "VARCHAR(30) DEFAULT ''");
    }
    // Immutable compliance audit log (Parts 21/23/24). Never hard-deleted.
    $pdo->exec("CREATE TABLE IF NOT EXISTS idems_audit (
        id $pk, entity VARCHAR(30) DEFAULT 'report_doc', entity_id INT NULL, irn VARCHAR(120) DEFAULT '',
        action VARCHAR(40) DEFAULT '', field VARCHAR(60) DEFAULT '', old_value TEXT, new_value TEXT, reason VARCHAR(400) DEFAULT '',
        username VARCHAR(150) DEFAULT '', role VARCHAR(40) DEFAULT '', office_id INT NULL,
        ip VARCHAR(60) DEFAULT '', device VARCHAR(200) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Tamper-evidence: each entry is sealed to the one before it (like the
    // evidence photo chain in trust.php). Added by upgrade, so older rows carry
    // empty seals and are simply not checked — an honest "sealed from here".
    if (function_exists('ensure_column')) {
        ensure_column('idems_audit', 'prev_hash',  "VARCHAR(64) DEFAULT ''");
        ensure_column('idems_audit', 'entry_hash', "VARCHAR(64) DEFAULT ''");
    }
    // ---- Phase 2: no-code report builder (form schema per report type) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_sections (
        id $pk, report_type_id INT, title VARCHAR(200) DEFAULT '', help VARCHAR(400) DEFAULT '',
        cond_field VARCHAR(60) DEFAULT '', cond_op VARCHAR(10) DEFAULT '', cond_val VARCHAR(200) DEFAULT '',
        sort_order INT DEFAULT 0)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_fields (
        id $pk, report_type_id INT, section_id INT NULL, fkey VARCHAR(60), label VARCHAR(200) DEFAULT '',
        ftype VARCHAR(20) DEFAULT 'text', options TEXT, required INT DEFAULT 0, hidden INT DEFAULT 0,
        cond_field VARCHAR(60) DEFAULT '', cond_op VARCHAR(10) DEFAULT '', cond_val VARCHAR(200) DEFAULT '',
        calc_expr VARCHAR(400) DEFAULT '', placeholder VARCHAR(200) DEFAULT '', help VARCHAR(400) DEFAULT '',
        col_span INT DEFAULT 1, table_cols TEXT, sort_order INT DEFAULT 0)");
    // ---- Scoring layer (vendor assessments, audits, scored checklists) ----
    // A field can carry a weight (its share of its section's score) and either a
    // max_score (for number/rating inputs, normalised 0-100 against this ceiling)
    // or a score_map (JSON {option: points 0-100} for select/radio/yesno). Fields
    // with weight 0 are informational and never contribute to a score — so plain
    // report types are completely unaffected. Added by upgrade; old rows read 0.
    if (function_exists('ensure_column')) {
        ensure_column('report_fields', 'weight',    "REAL DEFAULT 0");
        ensure_column('report_fields', 'max_score', "REAL DEFAULT 0");
        ensure_column('report_fields', 'score_map', "TEXT");
    }
    // §R1-D — Quality Assurance Plans filed against a job/call. One PO can carry
    // several QAPs (one per line item), so this is many-per-job. Stored as-is
    // (usually a PDF) — NEVER parsed. The inspector sees them while writing the
    // report and they travel with the report for traceability.
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_qaps (
        id $pk, job_id INT, po_line VARCHAR(200) DEFAULT '', file_name VARCHAR(255) DEFAULT '',
        mime VARCHAR(100) DEFAULT '', data LONGTEXT, note VARCHAR(400) DEFAULT '',
        uploaded_by VARCHAR(150) DEFAULT '', uploaded_at VARCHAR(30) DEFAULT '')");
    // Evidence / attachments captured against a report's fields (photos, files, signatures).
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_files (
        id $pk, report_doc_id INT, field_key VARCHAR(60) DEFAULT '', kind VARCHAR(20) DEFAULT 'file',
        file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', data LONGTEXT, gps VARCHAR(60) DEFAULT '',
        note VARCHAR(400) DEFAULT '', created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // ---- Phase 3: workflow & approvals ----
    // Per-inspector approver mapping (individual or common; temp cover during leave).
    $pdo->exec("CREATE TABLE IF NOT EXISTS idems_approver_map (
        id $pk, inspector_id INT, approver_user_id INT NULL, temp_user_id INT NULL,
        temp_from VARCHAR(20) DEFAULT '', temp_to VARCHAR(20) DEFAULT '', active INT DEFAULT 1, updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('idems_approver_map', 'inspector_id');
    // Configurable approval chain rules (matched by report type / office / client / Business Unit).
    $pdo->exec("CREATE TABLE IF NOT EXISTS idems_approval_rules (
        id $pk, name VARCHAR(150) DEFAULT '', active INT DEFAULT 1,
        report_type_code VARCHAR(16) DEFAULT '', office_id INT NULL, client_id INT NULL, sbu VARCHAR(20) DEFAULT '',
        level INT DEFAULT 1, approver_kind VARCHAR(20) DEFAULT 'INSPECTOR_MAP', approver_user_id INT NULL, approver_role VARCHAR(40) DEFAULT '',
        sla_hours INT DEFAULT 24, sort_order INT DEFAULT 0, created_at VARCHAR(30) DEFAULT '')");
    // Generated approval steps for a report instance.
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_approvals (
        id $pk, report_doc_id INT, level INT DEFAULT 1, approver_kind VARCHAR(20) DEFAULT '', approver_role VARCHAR(40) DEFAULT '',
        approver_user_id INT NULL, resolved_user_id INT NULL, status VARCHAR(20) DEFAULT 'PENDING',
        acted_by VARCHAR(150) DEFAULT '', acted_at VARCHAR(30) DEFAULT '', remarks VARCHAR(600) DEFAULT '',
        delegated_to INT NULL, sla_due VARCHAR(30) DEFAULT '', escalated INT DEFAULT 0, created_at VARCHAR(30) DEFAULT '')");
    // ---- Phase 4: digital signatures on profiles ----
        // Per-document review state: applicable / not applicable / not available.
    db()->exec("CREATE TABLE IF NOT EXISTS report_doc_review (
        id " . pk_clause() . ", report_doc_id INT, doc_type VARCHAR(30) DEFAULT '',
        state VARCHAR(20) DEFAULT 'PENDING', note VARCHAR(400) DEFAULT '',
        reviewed_by VARCHAR(150) DEFAULT '', reviewed_at VARCHAR(30) DEFAULT '')");
    // Technical vetting & debriefing — a distinct review action a senior reviewer
    // performs on a report before it goes for approval: vet (cleared), return
    // (send back for correction) or record a debrief. One row per action; the
    // report carries the latest vetting status for a quick read.
    db()->exec("CREATE TABLE IF NOT EXISTS report_vetting (
        id " . pk_clause() . ", report_doc_id INT, stage VARCHAR(20) DEFAULT 'VET',
        action VARCHAR(20) DEFAULT '', note VARCHAR(600) DEFAULT '',
        acted_by VARCHAR(150) DEFAULT '', acted_at VARCHAR(30) DEFAULT '')");
    if (function_exists('ensure_column')) {
        ensure_column('report_docs', 'vet_status', "VARCHAR(20) DEFAULT ''");
        ensure_column('report_docs', 'vet_at', "VARCHAR(30) DEFAULT ''");
        ensure_column('report_docs', 'vet_by', "VARCHAR(150) DEFAULT ''");
    }
    ensure_column('users', 'signature', 'MEDIUMTEXT');          // base64 data-URL of the signature image
    ensure_column('inspectors', 'signature', 'MEDIUMTEXT');
    // ---- Phase 5: client-specific report templates (uploaded .docx, token-mapped) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_templates (
        id $pk, name VARCHAR(150) DEFAULT '', report_type_id INT NULL, client_id INT NULL, office_id INT NULL,
        file_name VARCHAR(200) DEFAULT '', file_data LONGTEXT,
        document_number VARCHAR(80) DEFAULT '', format_number VARCHAR(80) DEFAULT '', doc_revision VARCHAR(40) DEFAULT '', issue_date VARCHAR(20) DEFAULT '',
        active INT DEFAULT 1, is_default INT DEFAULT 0, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // §32 — template version & lifecycle (draft → published → superseded/archived).
    // Existing templates default to PUBLISHED so nothing that worked stops working.
    if (function_exists('ensure_column')) {
        // Per-report-type document-control identity that prints on the header of
        // the clean system layout (no uploaded Word file needed): the format
        // number (e.g. RE-IN-I&E-INS-011), a document-control number and revision.
        ensure_column('report_types', 'format_number', "VARCHAR(80) DEFAULT ''");
        ensure_column('report_types', 'doc_control_no', "VARCHAR(80) DEFAULT ''");
        ensure_column('report_types', 'revision', "VARCHAR(40) DEFAULT ''");
        ensure_column('report_templates', 'status', "VARCHAR(20) DEFAULT 'PUBLISHED'");
        ensure_column('report_templates', 'version', 'INT DEFAULT 1');
        ensure_column('report_templates', 'effective_date', "VARCHAR(20) DEFAULT ''");
        ensure_column('report_templates', 'superseded_by', 'INT NULL');
        ensure_column('report_templates', 'published_at', "VARCHAR(30) DEFAULT ''");
        ensure_column('report_templates', 'published_by', "VARCHAR(150) DEFAULT ''");
        // §44 — document-controller review trail (submit → approve/reject).
        ensure_column('report_templates', 'submitted_at', "VARCHAR(30) DEFAULT ''");
        ensure_column('report_templates', 'submitted_by', "VARCHAR(150) DEFAULT ''");
        ensure_column('report_templates', 'reviewed_at', "VARCHAR(30) DEFAULT ''");
        ensure_column('report_templates', 'reviewed_by', "VARCHAR(150) DEFAULT ''");
        ensure_column('report_templates', 'review_note', "TEXT");
        // Per-office / per-branch IRN numbering-pattern override (blank = global).
        ensure_column('offices', 'irn_format', "VARCHAR(200) DEFAULT ''");
        // Per-section print layout: start on a new page / keep the whole section
        // together (don't split across a page break) — for clean PDF & Word output.
        ensure_column('report_sections', 'page_break_before', 'INT DEFAULT 0');
        ensure_column('report_sections', 'keep_together', 'INT DEFAULT 0');
    }
    // ---- Phase 6: manufacturer document verification & endorsement ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS endorsements (
        id $pk, endorsement_no VARCHAR(120), doc_type VARCHAR(20) DEFAULT 'MTC', title VARCHAR(255) DEFAULT '',
        vendor_id INT NULL, client_id INT NULL, report_doc_id INT NULL,
        project_code VARCHAR(40) DEFAULT '', project_name VARCHAR(200) DEFAULT '', po_ref VARCHAR(80) DEFAULT '',
        drawing_no VARCHAR(80) DEFAULT '', drawing_rev VARCHAR(30) DEFAULT '', qap_rev VARCHAR(30) DEFAULT '',
        heat_no VARCHAR(80) DEFAULT '', item_desc VARCHAR(255) DEFAULT '', doc_version VARCHAR(40) DEFAULT '',
        company_code VARCHAR(20) DEFAULT '', branch_code VARCHAR(20) DEFAULT '', client_code VARCHAR(20) DEFAULT '', fy_label VARCHAR(12) DEFAULT '', serial INT DEFAULT 0,
        office_id INT NULL, sbu VARCHAR(20) DEFAULT '', inspector_id INT NULL, approver_user_id INT NULL,
        status VARCHAR(20) DEFAULT 'UPLOADED', decision VARCHAR(20) DEFAULT '', decision_remarks VARCHAR(600) DEFAULT '', review_remarks TEXT,
        submitted_at VARCHAR(30) DEFAULT '', endorsed_at VARCHAR(30) DEFAULT '', endorsed_by VARCHAR(150) DEFAULT '', finalized INT DEFAULT 0,
        deleted INT DEFAULT 0, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('endorsements', 'endorsement_no');
    // Original + supporting files + signature snapshots for an endorsement (original never altered).
    $pdo->exec("CREATE TABLE IF NOT EXISTS endorsement_files (
        id $pk, endorsement_id INT, kind VARCHAR(20) DEFAULT 'support', file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '',
        data LONGTEXT, note VARCHAR(400) DEFAULT '', created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // ---- Phase 10: evidence management (compression, dedupe, captions, GPS) ----
    ensure_column('report_files', 'sha1', "VARCHAR(40) DEFAULT ''");        // duplicate detection
    ensure_column('report_files', 'caption', "VARCHAR(400) DEFAULT ''");    // annotation / caption
    ensure_column('report_files', 'taken_at', "VARCHAR(30) DEFAULT ''");    // capture timestamp
    ensure_column('report_files', 'bytes', 'INT DEFAULT 0');                // stored size after compression
    ensure_column('report_files', 'orig_bytes', 'INT DEFAULT 0');           // size before compression
    // ---- Phase 13: self-learning — harvested suggestions from approved reports ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS learned_suggestions (
        id $pk, scope VARCHAR(20) DEFAULT 'FIELD', report_type_id INT NULL, client_id INT NULL,
        field_key VARCHAR(60) DEFAULT '', text_value TEXT, norm_key VARCHAR(190) DEFAULT '',
        uses INT DEFAULT 1, last_seen VARCHAR(30) DEFAULT '', muted INT DEFAULT 0,
        created_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('learned_suggestions', 'norm_key');
    // ---- Phase 7: technical writing assistant — standard phrase library ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS tech_phrases (
        id $pk, category VARCHAR(30) DEFAULT 'OBSERVATION', shorthand VARCHAR(120) DEFAULT '', phrase TEXT,
        discipline VARCHAR(40) DEFAULT '', active INT DEFAULT 1, is_system INT DEFAULT 0, usage_count INT DEFAULT 0,
        sort_order INT DEFAULT 0, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    idems_seed_report_types();
    idems_seed_phrases();
    // §activity — editable master lists for an inspection activity's disposition
    // and progress, so a company can tune the exact words it uses on reports.
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('inspection_disposition', 'Inspection result / disposition', INSPECTION_DISPOSITIONS, 'idems');
        lk_ensure_type_map('activity_progress', 'Activity progress', ACTIVITY_PROGRESS, 'idems');
        lk_ensure_type_map('measurement_units', 'Measurement units', MEASUREMENT_UNITS, 'idems');
        lk_ensure_type_map('itp_inspection_type', 'ITP inspection type (Witness/Review/Verify)', INSPECTION_TYPES_ITP, 'idems');
        lk_ensure_type_map('po_status', 'PO status', PO_STATUS_OPTS, 'idems');
    }
    // A deputation's deliverables are report types — rewrite the old codes.
    idems_migrate_deliverables();
    // Prebuild the ready-to-use company inspection report ONCE — a complete type
    // with every section wired. Guarded by a flag so a user who deletes it does
    // not get it back, and wrapped so a failure can never break login/migrate.
    if (function_exists('setting_get') && !setting_get('mgh_report_seeded', '')) {
        try { idems_build_mgh_report(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('mgh_report_seeded', '1');
    }
    // ONE-TIME rescue: an "IR" inspection report whose form was auto-imported from
    // a Word file ended up with garbled labels ("Client s p o no date", "Item as
    // percontract order table"…). Replace that garbled form with the clean layout,
    // and retire the duplicate prebuilt type so there is ONE inspection report.
    // Guarded so it fires at most once and NEVER touches a form built by hand.
    if (function_exists('setting_get') && !setting_get('ir_form_cleaned', '')) {
        try {
            $ir = ops_one("SELECT id FROM report_types WHERE code='IR'");
            if ($ir) {
                $irId = (int)$ir['id'];
                $alreadyClean = (int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=? AND title='Inspection details'", [$irId]);
                $garbled = (int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND (label LIKE '% s p o %' OR label LIKE '%percontract%' OR label LIKE '%actual impression%' OR label LIKE '%no revision%' OR label LIKE '%edition version year%' OR label LIKE '%seller manufacturer%')", [$irId]);
                $anyFields = (int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=?", [$irId]);
                if (!$alreadyClean && ($garbled > 0 || $anyFields === 0)) {
                    idems_reset_inspection_form($irId);
                }
                // one clean inspection report — hide the auto-seeded duplicate
                db()->prepare("UPDATE report_types SET active=0 WHERE code='MGHIR'")->execute();
            }
        } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('ir_form_cleaned', '1');
    }
    // The uploaded company template's {{tokens}} were built for the OLD garbled
    // field keys; after cleaning the form they no longer match, so the "your
    // format" Word download came out full of raw {{tokens}}. Detach the IR-type
    // template so the download uses the clean system layout instead. Re-uploading
    // a company format later maps its tokens to the clean fields.
    if (function_exists('setting_get') && !setting_get('ir_tpl_detached', '')) {
        try {
            $ir = ops_one("SELECT id FROM report_types WHERE code='IR'");
            if ($ir) db()->prepare("UPDATE report_templates SET active=0 WHERE report_type_id=?")->execute([(int)$ir['id']]);
        } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('ir_tpl_detached', '1');
    }
    // ONE-TIME repair of the staircase table bug on existing draft reports (see
    // idems_repair_staircase_tables). Guarded + failure-safe.
    if (function_exists('setting_get') && !setting_get('tables_repaired_v1', '')) {
        try { idems_repair_staircase_tables(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('tables_repaired_v1', '1');
    }
    // ONE-TIME: push each user's saved signature onto their inspector record (by
    // inspector_id link, else by matching email) so reports where they are the
    // inspector show the signature they set under "My signature".
    if (function_exists('setting_get') && !setting_get('sig_synced_v1', '')) {
        try {
            foreach (ops_all("SELECT inspector_id, email, signature FROM users WHERE COALESCE(signature,'')<>''") as $u) {
                $iid = (int)($u['inspector_id'] ?? 0);
                if (!$iid && trim((string)($u['email'] ?? '')) !== '') {
                    $iid = (int)ops_val("SELECT id FROM inspectors WHERE LOWER(email)=LOWER(?) LIMIT 1", [trim($u['email'])]);
                }
                if ($iid && trim((string)ops_val("SELECT signature FROM inspectors WHERE id=?", [$iid])) === '') {
                    db()->prepare("UPDATE inspectors SET signature=? WHERE id=?")->execute([$u['signature'], $iid]);
                }
            }
        } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('sig_synced_v1', '1');
    }
    // ONE-TIME: drop the item-level fields from any existing "Inspection details"
    // section — item description, material, drawing no./rev., QAP, heat no. and
    // quantity. They duplicated what the PO-items and Reference-documents tables
    // already capture per line, so they are removed from the form and the report.
    // Scoped to that section title + those exact keys so nothing else is touched;
    // any data already entered stays in the report record, just no longer printed.
    if (function_exists('setting_get') && !setting_get('ir_header_dedup_v1', '')) {
        try {
            $keys = ['item_description','material','drawing_no','drawing_rev','qap','heat_no','quantity_offered'];
            $secIds = array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM report_sections WHERE LOWER(title)='inspection details'"));
            if ($secIds) {
                $inSec = implode(',', array_fill(0, count($secIds), '?'));
                $inKey = implode(',', array_fill(0, count($keys), '?'));
                db()->prepare("DELETE FROM report_fields WHERE section_id IN ($inSec) AND LOWER(fkey) IN ($inKey)")
                    ->execute(array_merge($secIds, array_map('strtolower', $keys)));
            }
        } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('ir_header_dedup_v1', '1');
    }
    // ONE-TIME: give the "RN — Release Note" type its form so a Release Note is
    // designed like the inspection report and renders through the same engine.
    if (function_exists('setting_get') && !setting_get('rn_form_seeded_v1', '')) {
        try { idems_build_release_note(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('rn_form_seeded_v1', '1');
    }
    // ONE-TIME: give the "VASR — Vendor Assessment Report" type its scored form,
    // proving the weighted-scoring engine end to end.
    if (function_exists('setting_get') && !setting_get('vasr_form_seeded_v1', '')) {
        try { idems_build_vendor_assessment(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('vasr_form_seeded_v1', '1');
    }
    // ONE-TIME: give the "VAR — Vendor Audit Report" type its form (findings →
    // NCR/CAPA on issue).
    if (function_exists('setting_get') && !setting_get('var_form_seeded_v1', '')) {
        try { idems_build_vendor_audit(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('var_form_seeded_v1', '1');
    }
    // ONE-TIME: add the Audit checklist table to a VAR form seeded before it
    // existed. Safe — issued reports keep their frozen schema; drafts keep their
    // data (fkeys unchanged, one field added).
    if (function_exists('setting_get') && !setting_get('var_checklist_v1', '')) {
        try {
            $vid = idems_type_id_by_code('VAR');
            if ($vid && !(int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey='audit_checklist'", [$vid])) {
                $pdo->prepare("DELETE FROM report_fields WHERE report_type_id=?")->execute([$vid]);
                $pdo->prepare("DELETE FROM report_sections WHERE report_type_id=?")->execute([$vid]);
                idems_install_vendor_audit_sections($vid);
            }
        } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('var_checklist_v1', '1');
    }
    // ONE-TIME: add the "Customer complaints & resolution" section to VASR and VAR
    // forms seeded before it existed. Reseeds only when the field is absent
    // (issued reports keep their frozen schema; drafts keep data by fkey).
    if (function_exists('setting_get') && !setting_get('vendor_complaints_v1', '')) {
        try {
            foreach (['VASR' => 'idems_install_vendor_assessment_sections', 'VAR' => 'idems_install_vendor_audit_sections'] as $code => $installer) {
                $tid = idems_type_id_by_code($code);
                if ($tid && !(int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey='complaints_review'", [$tid])) {
                    $pdo->prepare("DELETE FROM report_fields WHERE report_type_id=?")->execute([$tid]);
                    $pdo->prepare("DELETE FROM report_sections WHERE report_type_id=?")->execute([$tid]);
                    $installer($tid);
                }
            }
        } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('vendor_complaints_v1', '1');
    }
    // ONE-TIME: add the "Evidence referred" + "Attached" (tick) columns to a VAR
    // checklist/findings seeded before they existed. Reseeds only when the
    // checklist lacks the Attached column.
    if (function_exists('setting_get') && !setting_get('var_evidence_v1', '')) {
        try {
            $tid = idems_type_id_by_code('VAR');
            if ($tid) {
                $cols = (string)ops_val("SELECT table_cols FROM report_fields WHERE report_type_id=? AND fkey='audit_checklist'", [$tid]);
                if (stripos($cols, 'Attached') === false) {
                    $pdo->prepare("DELETE FROM report_fields WHERE report_type_id=?")->execute([$tid]);
                    $pdo->prepare("DELETE FROM report_sections WHERE report_type_id=?")->execute([$tid]);
                    idems_install_vendor_audit_sections($tid);
                }
            }
        } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('var_evidence_v1', '1');
    }
    // ONE-TIME: add "Evidence referred" + "Attached" (tick) to every ITP /
    // activity table (scope_activities) that lacks them. Done as an in-place
    // table_cols edit — NOT a reseed — so any admin customisation of the form is
    // preserved. The columns are inserted just before the Disposition column.
    if (function_exists('setting_get') && !setting_get('ir_evidence_v1', '')) {
        try {
            foreach (ops_all("SELECT id, table_cols FROM report_fields WHERE fkey='scope_activities' AND ftype='table'") as $ff) {
                $tc = (string)($ff['table_cols'] ?? ''); if ($tc === '' || stripos($tc, 'Attached') !== false) continue;
                $lines = preg_split('/\r?\n/', $tc); $out = []; $done = false;
                foreach ($lines as $ln) {
                    if (!$done && stripos(trim($ln), 'Disposition') === 0) { $out[] = 'Evidence referred'; $out[] = 'Attached|select|Yes,No,N/A'; $done = true; }
                    $out[] = $ln;
                }
                if (!$done) { $out[] = 'Evidence referred'; $out[] = 'Attached|select|Yes,No,N/A'; }
                db()->prepare("UPDATE report_fields SET table_cols=? WHERE id=?")->execute([implode("\n", $out), (int)$ff['id']]);
            }
        } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('ir_evidence_v1', '1');
    }
    // ONE-TIME: give the "ER — Expediting Report" type its universal form.
    if (function_exists('setting_get') && !setting_get('er_form_seeded_v1', '')) {
        try { idems_build_expediting(); } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('er_form_seeded_v1', '1');
    }
    // ONE-TIME (P2): add the engineering/material/inspection/NCR sections to an ER
    // seeded before they existed. Reseeds only when the inspection table is absent.
    if (function_exists('setting_get') && !setting_get('er_p2_v1', '')) {
        try {
            $eid = idems_type_id_by_code('ER');
            if ($eid && !(int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey='inspection_status'", [$eid])) {
                $pdo->prepare("DELETE FROM report_fields WHERE report_type_id=?")->execute([$eid]);
                $pdo->prepare("DELETE FROM report_sections WHERE report_type_id=?")->execute([$eid]);
                idems_install_expediting_sections($eid);
            }
        } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('er_p2_v1', '1');
    }
    // ONE-TIME (P3): add capacity / sub-supplier / packing / dispatch-readiness /
    // logistics sections to an ER seeded before them. Reseeds only when the
    // dispatch-readiness table is absent.
    if (function_exists('setting_get') && !setting_get('er_p3_v1', '')) {
        try {
            $eid = idems_type_id_by_code('ER');
            if ($eid && !(int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey='dispatch_readiness'", [$eid])) {
                $pdo->prepare("DELETE FROM report_fields WHERE report_type_id=?")->execute([$eid]);
                $pdo->prepare("DELETE FROM report_sections WHERE report_type_id=?")->execute([$eid]);
                idems_install_expediting_sections($eid);
            }
        } catch (Throwable $e) {}
        if (function_exists('setting_set')) setting_set('er_p3_v1', '1');
    }
    // ---- Vendor qualification profile — one row per vendor partner, updated by
    // assessments. Kept separate from business_partners so the core directory CRUD
    // is untouched; joined by partner_id when a vendor is viewed or listed.
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendor_profiles (
        id $pk, partner_id INT, vendor_type VARCHAR(60) DEFAULT '', product_category VARCHAR(80) DEFAULT '',
        risk_class VARCHAR(40) DEFAULT '', approval_status VARCHAR(30) DEFAULT 'PROSPECT',
        vendor_rating REAL DEFAULT 0, last_score REAL NULL, last_band VARCHAR(30) DEFAULT '',
        last_assessment_id INT NULL, last_assessed_on VARCHAR(20) DEFAULT '',
        approved_on VARCHAR(20) DEFAULT '', valid_until VARCHAR(20) DEFAULT '', reassess_on VARCHAR(20) DEFAULT '',
        notes TEXT, updated_by VARCHAR(150) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('vendor_profiles', 'partner_id');
    // Seeded, fully editable vendor master lists (never hard-coded downstream).
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('vendor_type', 'Vendor / supplier type', [
            'MANUFACTURER'=>'Manufacturer','SERVICE'=>'Service provider','DISTRIBUTOR'=>'Distributor / trader',
            'CONTRACTOR'=>'Contractor','OEM'=>'OEM','LAB'=>'Testing laboratory','CONSULTANT'=>'Consultant'], 'Vendor');
        lk_ensure_type_map('vendor_product_category', 'Vendor product / service category', [
            'RAW_MATERIAL'=>'Raw material','COMPONENTS'=>'Components','EQUIPMENT'=>'Equipment / machinery',
            'FABRICATION'=>'Fabrication','ELECTRICAL'=>'Electrical / instrumentation','CIVIL'=>'Civil / construction',
            'SERVICES'=>'Services','CONSUMABLES'=>'Consumables','TESTING'=>'Testing / calibration'], 'Vendor');
        lk_ensure_type_map('vendor_risk_class', 'Vendor risk class', [
            'CRITICAL'=>'Critical','HIGH'=>'High','MEDIUM'=>'Medium','LOW'=>'Low'], 'Vendor');
        lk_ensure_type_map('vendor_approval_status', 'Vendor approval status', [
            'PROSPECT'=>'Prospect','UNDER_ASSESSMENT'=>'Under assessment','APPROVED'=>'Approved',
            'CONDITIONAL'=>'Approved with conditions','EXPIRED'=>'Approval expired','SUSPENDED'=>'Suspended','BLACKLISTED'=>'Blacklisted'], 'Vendor');
        // Ensure EXPIRED exists even where the type was seeded before it was added.
        if (function_exists('lk_ensure_value')) lk_ensure_value('vendor_approval_status', 'EXPIRED', 'Approval expired');
        // Expediting master lists (all editable; never hard-coded downstream).
        lk_ensure_type_map('expediting_type', 'Expediting type', [
            'PRE_AWARD'=>'Pre-award assessment','KICKOFF'=>'Post-PO kickoff','DESK'=>'Desk expediting','REMOTE'=>'Remote expediting',
            'SITE'=>'Site / factory expediting','ROUTINE'=>'Routine visit','WEEKLY'=>'Weekly','FORTNIGHTLY'=>'Fortnightly','MONTHLY'=>'Monthly',
            'CRITICAL'=>'Critical-item','RECOVERY'=>'Recovery / delay','PRE_DISPATCH'=>'Pre-dispatch','FINAL'=>'Final expediting'], 'Expediting');
        lk_ensure_type_map('expediting_method', 'Expediting method', [
            'DESK'=>'Desk','EMAIL'=>'Email','PHONE'=>'Phone','VIDEO'=>'Video conference','DOC_REVIEW'=>'Document review',
            'MEETING'=>'Supplier meeting','SITE'=>'Site visit','FACTORY'=>'Factory visit','TPI'=>'Third-party verification','HYBRID'=>'Hybrid'], 'Expediting');
        lk_ensure_type_map('expediting_status', 'Expediting overall status', [
            'ON_TRACK'=>'On track','ON_WATCH'=>'On watch','AT_RISK'=>'At risk','DELAYED'=>'Delayed','CRITICAL'=>'Critical','COMPLETED'=>'Completed','CLOSED'=>'Closed'], 'Expediting');
        lk_ensure_type_map('expediting_delay_category', 'Delay category', [
            'ENGINEERING'=>'Engineering','CLIENT_APPROVAL'=>'Client approval','VENDOR'=>'Vendor','PROCUREMENT'=>'Procurement','MATERIAL'=>'Material',
            'PRODUCTION'=>'Production','CAPACITY'=>'Capacity','MANPOWER'=>'Manpower','EQUIPMENT'=>'Equipment','QUALITY'=>'Quality','INSPECTION'=>'Inspection',
            'TESTING'=>'Testing','DOCUMENTATION'=>'Documentation','LOGISTICS'=>'Logistics','TRANSPORT'=>'Transport','CUSTOMS'=>'Customs','REGULATORY'=>'Regulatory',
            'WEATHER'=>'Weather','FORCE_MAJEURE'=>'Force majeure','COMMERCIAL'=>'Commercial','PAYMENT'=>'Payment','CHANGE_ORDER'=>'Change order','DESIGN_CHANGE'=>'Design change','OTHER'=>'Other'], 'Expediting');
    }
    // Vendor status timeline — every qualification change (by assessment, audit,
    // expiry or a manual edit) recorded with who, when, why and the score.
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendor_status_events (
        id $pk, partner_id INT, old_status VARCHAR(30) DEFAULT '', new_status VARCHAR(30) DEFAULT '',
        source VARCHAR(20) DEFAULT 'MANUAL', report_doc_id INT NULL, score REAL NULL,
        reason VARCHAR(500) DEFAULT '', actor VARCHAR(150) DEFAULT '', at VARCHAR(30) DEFAULT '')");
}
// ITP inspection type for a scope activity — how the point is covered.
const INSPECTION_TYPES_ITP = [
    'Witness'        => 'Witness',
    'Review'         => 'Review',
    'Verify'         => 'Verify',
    'Monitor'        => 'Monitor',
    'Hold'           => 'Hold',
    'Surveillance'   => 'Surveillance',
    'Record review'  => 'Record review',
    'Perform'        => 'Perform',
];
// PO status shown against the order on the report header.
const PO_STATUS_OPTS = [
    'Completed' => 'Completed',
    'Balance'   => 'Balance',
    'Hold'      => 'Hold',
];
// How an inspection activity / line item is dispositioned. value === label so it
// prints straight onto the report. Editable in Masters once seeded.
const INSPECTION_DISPOSITIONS = [
    'Acceptable'                => 'Acceptable',
    'Acceptable with comment'   => 'Acceptable with comment',
    'Rejected'                  => 'Rejected',
    'Client clearance required' => 'Client clearance required',
    'Hold'                      => 'Hold',
    'Balance'                   => 'Balance',
    'Witnessed'                 => 'Witnessed',
    'Reviewed'                  => 'Reviewed',
    'Not applicable'            => 'Not applicable',
];
const ACTIVITY_PROGRESS = [
    'Completed'  => 'Completed',
    'Partial'    => 'Partial',
    'In progress'=> 'In progress',
    'Pending'    => 'Pending',
    'Not done'   => 'Not done',
];
// Units offered by the "unit picker" field type. value === label so it prints
// as typed. Editable in Masters once seeded.
const MEASUREMENT_UNITS = [
    'mm'=>'mm', 'cm'=>'cm', 'm'=>'m', 'inch'=>'inch', 'ft'=>'ft',
    'kg'=>'kg', 'g'=>'g', 'ton'=>'ton', 'lb'=>'lb',
    'nos'=>'nos', 'set'=>'set', 'lot'=>'lot', 'mtr'=>'mtr', 'sq.m'=>'sq.m',
    '%'=>'%', '°C'=>'°C', 'bar'=>'bar', 'MPa'=>'MPa', 'psi'=>'psi',
    'micron'=>'micron', 'HRC'=>'HRC', 'HB'=>'HB', 'J'=>'J',
];
// Portable "add a unique index if missing" (ignores errors if it already exists).
function idems_unique_index($table, $col) {
    try { db()->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_{$table}_{$col} ON {$table}($col)"); } catch (Throwable $e) {}
}
function idems_seed_report_types() {
    $pdo = db();
    $have = [];
    foreach ($pdo->query("SELECT code FROM report_types")->fetchAll(PDO::FETCH_COLUMN) as $c) $have[strtoupper($c)] = 1;
    $ins = $pdo->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,created_at) VALUES (?,?,?,1,1,?,?)");
    $i = 0;
    foreach (IDEMS_REPORT_SEED as $r) { $i += 10; if (!isset($have[strtoupper($r[0])])) $ins->execute([$r[0], $r[1], $r[2], $i, date('c')]); }
}
// One-time repair for the "staircase" table bug: a table saved before the row-
// grouping fix stored each cell as its own one-cell row. This stitches those
// split rows back into whole rows. SAFE BY DESIGN — it only acts on a table where
// EVERY row has at most one filled cell (the staircase signature), so a table
// saved correctly (any row with 2+ filled cells) is left untouched, and a genuine
// single-column list (same column repeated) merges to itself (no change). Skips
// finalized/issued reports, whose data is immutable. Returns [docsFixed, tablesFixed].
function idems_repair_staircase_tables() {
    $pdo = db();
    $docs = ops_all("SELECT id, report_type_id, data FROM report_docs WHERE deleted=0 AND COALESCE(finalized,0)=0 AND data IS NOT NULL AND data<>'' AND data<>'[]'");
    $docsFixed = 0; $tablesFixed = 0;
    foreach ($docs as $d) {
        $data = json_decode($d['data'] ?: '[]', true); if (!is_array($data)) continue;
        $fields = idems_fields($d['report_type_id']);
        $changed = false;
        foreach ($fields as $f) {
            if (($f['ftype'] ?? '') !== 'table') continue;
            $k = $f['fkey'];
            if (empty($data[$k]) || !is_array($data[$k])) continue;
            $cols = array_keys(idems_table_cols($f));
            if (count($cols) < 2) continue;                 // single-column tables can't staircase
            $rows = array_values($data[$k]);
            if (count($rows) < 2) continue;
            // Guard: only repair when EVERY row has at most one filled cell.
            $maxFilled = 0;
            foreach ($rows as $r) { $r=(array)$r; $n=0; foreach ($cols as $c) if (trim((string)($r[$c] ?? '')) !== '') $n++; if ($n>$maxFilled) $maxFilled=$n; }
            if ($maxFilled > 1) continue;                   // already correct / not a staircase
            // Stitch: a new logical row begins when an incoming column is already
            // filled in the row being accumulated.
            $merged = []; $cur = [];
            foreach ($rows as $r) {
                $r = (array)$r;
                foreach ($cols as $c) {
                    $v = trim((string)($r[$c] ?? '')); if ($v === '') continue;
                    if (isset($cur[$c]) && $cur[$c] !== '') { $merged[] = $cur; $cur = []; }
                    $cur[$c] = $v;
                }
            }
            if ($cur) $merged[] = $cur;
            if (count($merged) < count($rows) && $merged) {  // only if it genuinely collapsed rows
                foreach ($merged as &$mr) { foreach ($cols as $c) if (!isset($mr[$c])) $mr[$c] = ''; } unset($mr);
                $data[$k] = array_values($merged);
                $changed = true; $tablesFixed++;
            }
        }
        if ($changed) { $pdo->prepare("UPDATE report_docs SET data=?, updated_at=? WHERE id=?")->execute([json_encode($data), date('c'), $d['id']]); $docsFixed++; }
    }
    return [$docsFixed, $tablesFixed];
}

// ---- Phase 2: field types for the no-code builder --------------------------
const IDEMS_FIELD_TYPES = [
    'text'=>'Short text', 'textarea'=>'Paragraph', 'number'=>'Number', 'date'=>'Date', 'time'=>'Time',
    'select'=>'Dropdown (single)', 'multiselect'=>'Dropdown (multiple)', 'checkbox'=>'Tick box', 'yesno'=>'Yes / No', 'radio'=>'Choice buttons',
    'calc'=>'Calculated', 'heading'=>'Section heading', 'note'=>'Info note', 'richtext'=>'Static text / disclaimer',
    'table'=>'Repeatable table', 'photo'=>'Photo', 'file'=>'Attachment', 'gps'=>'GPS location',
    'signature'=>'Signature', 'sigblock'=>'Sign-off block (per role)', 'qr'=>'QR / barcode value', 'unit'=>'Unit picker',
    // An inspection instrument. Offers the calibrated instruments from the
    // equipment master as a dropdown AND allows free typing — so a lab / NDT /
    // calibration report picks a registered, in-calibration instrument, while a
    // site inspector (who uses whatever is at the works) can just type it.
    'instrument'=>'Instrument (master + free-text)',
];
const IDEMS_COND_OPS = ['' => '(always show)', 'eq'=>'equals', 'ne'=>'not equals', 'in'=>'is one of', 'nonempty'=>'is filled', 'empty'=>'is empty'];

// ---------------------------------------------------------------------------
//  Prebuilt company inspection report — a complete, ready-to-use report type
//  wired with every section a real TPI inspection report carries: an autofilled
//  header, reference documents, PO line items, the ITP scope, master-linked
//  instruments, hold-point status, observations/conclusion, photographs, a
//  disclaimer and a per-role sign-off. Idempotent: returns the existing type if
//  it is already built. No third-party agency name is used anywhere.
// ---------------------------------------------------------------------------
function idems_build_mgh_report($code = 'MGHIR', $name = 'MGH Inspection Report') {
    $pdo = db();
    $existing = ops_one("SELECT id FROM report_types WHERE code=?", [$code]);
    if ($existing) return (int)$existing['id'];
    $sort = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_types");
    $pdo->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,created_at) VALUES (?,?, 'TPIA_REPORT',1,0,?,?)")
        ->execute([$code, $name, $sort, date('c')]);
    $typeId = (int)$pdo->lastInsertId();
    idems_install_inspection_sections($typeId);
    return $typeId;
}
// Wipe a report type's current form and install the clean inspection layout —
// used to rescue a type whose form was auto-imported into garbled fields.
function idems_reset_inspection_form($typeId) {
    $typeId = (int)$typeId; if (!$typeId) return;
    $pdo = db();
    $pdo->prepare("DELETE FROM report_fields WHERE report_type_id=?")->execute([$typeId]);
    $pdo->prepare("DELETE FROM report_sections WHERE report_type_id=?")->execute([$typeId]);
    idems_install_inspection_sections($typeId);
}
// Install the clean inspection-report sections/fields into an (empty) type.
function idems_install_inspection_sections($typeId) {
    $pdo = db();
    $so = 0; // running section sort
    $addSection = function($title, $help = '', $pgb = 0, $keep = 0) use ($pdo, $typeId, &$so) {
        $so += 10;
        $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,page_break_before,keep_together,sort_order) VALUES (?,?,?,?,?,?)")
            ->execute([$typeId, $title, $help, $pgb, $keep, $so]);
        return (int)$pdo->lastInsertId();
    };
    $fo = 0; // running field sort
    $addField = function($secId, $fkey, $label, $ftype, $opts = '', $tableCols = '', $span = 1) use ($pdo, $typeId, &$fo) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,table_cols,sort_order,col_span) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$typeId, $secId, $fkey, $label, $ftype, $opts, $tableCols, $fo, $span]);
    };

    // 1) Inspection details — autofills from the job/call (client, vendor, PO…).
    // Item-level particulars (item description, material, drawing, QAP, heat no.,
    // quantity) are NOT repeated here — they are captured per line in the PO items
    // and Reference documents tables below, so this section stays to the identifying
    // context only. §dedup
    $s = $addSection('Inspection details', 'Most of this carries forward from the job — Client, End user, Manufacturer, PO, Project and dates fill in automatically.', 0, 1);
    $addField($s, 'client', 'Client', 'text');
    $addField($s, 'end_user', 'End user', 'text');
    $addField($s, 'vendor', 'Manufacturer / Vendor', 'text');
    $addField($s, 'po_number', 'P.O. No.', 'text');
    $addField($s, 'project', 'Project', 'text');
    $addField($s, 'inspection_stage', 'Stage of inspection', 'select', "Stage inspection\nIn-process\nFinal\nPre-dispatch\nWitness\nReview");
    $addField($s, 'inspection_date', 'Date of inspection', 'date');
    $addField($s, 'location', 'Place of inspection', 'text');
    $addField($s, 'inspector', 'Inspector', 'text');

    // 2) Reference documents.
    $s = $addSection('Reference documents', 'The QAP/ITP, drawings, specifications and standards inspected against — one row per document.');
    $addField($s, 'reference_documents', 'Reference documents', 'table', '', "Document Name\nDocument Number\nRevision No.\nApproval Code\nDate of Approval|date", 2);

    // 3) PO line items & quantities (unit chosen once per line).
    $s = $addSection('PO items & quantities', 'One row per PO line item — quantities and the unit chosen once per line.');
    $addField($s, 'po_items', 'PO line items', 'table', '',
        "PO Sr. No.|merge\nDescription as per PO\nSize\nUnit|unit\nPO Qty|number\nOffered Qty|number\nPassed Qty|number\nRejected Qty|number\nHold Qty|number\nBalance Qty|number\nHeat No.\nProduct Sr. No.", 2);

    // 4) ITP / Inspection scope.
    $s = $addSection('ITP / Inspection scope', 'Each activity in the ITP — clause, quantum of check, inspection type, observation and disposition.');
    $addField($s, 'scope_activities', 'Scope of activities', 'table', '',
        "ITP / Clause No.|merge\nSub-clause\nDescription of activity\nQuantum of check\nInspection type|select|lookup:itp_inspection_type\nObservation\nRemarks\nEvidence referred\nAttached|select|Yes,No,N/A\nDisposition|select|lookup:inspection_disposition\nStatus|select|lookup:activity_progress", 2);

    // 5) Instruments & calibration — linked to the equipment register.
    $s = $addSection('Instruments & calibration', 'Pick an instrument from the equipment register — serial and calibration dates fill in automatically.');
    $addField($s, 'instruments', 'Instruments used', 'table', '',
        "Instrument|select|equip:instruments\nSr. No. / Identification number\nCalibrated on|date\nCalibrated due date|date\nNABL Traceable|select|Yes; No", 2);

    // 6) Order & hold-point status.
    $s = $addSection('Order & hold-point status', 'Status of the order and of any hold / deviation points from the previous visit.');
    $addField($s, 'po_status', 'P.O. status', 'select', 'lookup:po_status');
    $addField($s, 'prev_holdpoint_status', 'Status of previous hold points / deviations', 'textarea', '', '', 2);
    $addField($s, 'current_holdpoints', 'Current hold points / deviations', 'textarea', '', '', 2);

    // 7) Observations & conclusion.
    $s = $addSection('Observations & conclusion');
    $addField($s, 'observations', 'Details of inspection carried out / observations', 'textarea', '', '', 2);
    $addField($s, 'conclusion', 'Conclusion', 'textarea', '', '', 2);
    $addField($s, 'general_remarks', 'General remarks', 'textarea', '', '', 2);

    // 8) Photographs.
    $s = $addSection('Photographs', 'Take or upload photos — each auto-compressed and captioned; or mark photography denied.');
    $addField($s, 'photos', 'Photographs', 'photo', '', '', 2);

    // 9) Disclaimer (kept together, not split across a page).
    $s = $addSection('', '', 0, 1);
    $disc = "This report is issued on the basis of the inspection carried out at the time and place stated. It reflects the condition of the items inspected and does not relieve the manufacturer / supplier of their contractual obligations. Our liability is limited to the fee charged for this inspection.";
    $addField($s, 'disclaimer', 'Disclaimer', 'richtext', $disc, '', 2);

    // 10) Sign-off (Prepared / Reviewed / Approved), kept together.
    $s = $addSection('Sign-off', 'Prepared / Reviewed / Approved — name, designation and date auto-fill from the workflow.', 0, 1);
    $addField($s, 'signoff', 'For ' . app_name(), 'sigblock', "Prepared by\nReviewed by\nApproved by", '', 2);

    return $typeId;
}

// The Release Note is auto-generated from an Inspection Report and carries the
// SAME identifying details, the item table and the activity list forward, plus a
// release statement that refers to the inspection report number(s). It shares the
// IR's field keys so those values copy across directly, and it is form-driven so
// it renders through the same polished report engine (header, aligned sections,
// proportional tables, balanced pagination) — not the legacy plain block.
function idems_install_release_note_sections($typeId) {
    $pdo = db();
    $so = 0; $fo = 0;
    $addSection = function($title, $help = '', $pgb = 0, $keep = 0) use ($pdo, $typeId, &$so) {
        $so += 10;
        $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,page_break_before,keep_together,sort_order) VALUES (?,?,?,?,?,?)")
            ->execute([$typeId, $title, $help, $pgb, $keep, $so]);
        return (int)$pdo->lastInsertId();
    };
    $addField = function($secId, $fkey, $label, $ftype, $opts = '', $tableCols = '', $span = 1) use ($pdo, $typeId, &$fo) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,table_cols,sort_order,col_span) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$typeId, $secId, $fkey, $label, $ftype, $opts, $tableCols, $fo, $span]);
    };

    // Section I — reference details (carried from the inspection report).
    $s = $addSection('Reference', 'Client, end user, manufacturer, PO and project — carried from the inspection report.', 0, 1);
    $addField($s, 'client', 'Client', 'text');
    $addField($s, 'end_user', 'End user', 'text');
    $addField($s, 'vendor', 'Manufacturer / Vendor', 'text');
    $addField($s, 'po_number', 'P.O. No.', 'text');
    $addField($s, 'project', 'Project', 'text');

    // Section II — inspection details (carried from the inspection report).
    $s = $addSection('Inspection details', 'Stage, date, place and inspector — carried from the inspection report.', 0, 1);
    $addField($s, 'inspection_stage', 'Stage of inspection', 'select', "Stage inspection\nIn-process\nFinal\nPre-dispatch\nWitness\nReview");
    $addField($s, 'inspection_date', 'Date of inspection', 'date');
    $addField($s, 'location', 'Place of inspection', 'text');
    $addField($s, 'inspector', 'Inspector', 'text');

    // Items offered — the same PO item table carried from the report.
    $s = $addSection('Items offered / released', 'The PO line items released — carried from the inspection report.');
    $addField($s, 'po_items', 'PO line items', 'table', '',
        "PO Sr. No.|merge\nDescription as per PO\nSize\nUnit|unit\nPO Qty|number\nOffered Qty|number\nPassed Qty|number\nRejected Qty|number\nHold Qty|number\nBalance Qty|number\nHeat No.\nProduct Sr. No.", 2);

    // Activities carried out — the ITP scope carried from the report.
    $s = $addSection('Activities carried out', 'The ITP / inspection scope activities carried out — carried from the inspection report.');
    $addField($s, 'scope_activities', 'Scope of activities', 'table', '',
        "ITP / Clause No.|merge\nSub-clause\nDescription of activity\nQuantum of check\nInspection type|select|lookup:itp_inspection_type\nObservation\nRemarks\nEvidence referred\nAttached|select|Yes,No,N/A\nDisposition|select|lookup:inspection_disposition\nStatus|select|lookup:activity_progress", 2);

    // Release statement — the conclusion, with a field for the inspection report
    // number(s) entered by hand (kept together, not split across a page).
    $s = $addSection('Release statement', '', 0, 1);
    $addField($s, 'release_statement', 'Statement', 'textarea', '', '', 2);
    $addField($s, 'ir_numbers', 'Inspection Report No(s).', 'text', '', '', 2);

    // Disclaimer + Sign-off, matching the inspection report.
    $s = $addSection('', '', 0, 1);
    $disc = "This Release Note is issued on the basis of the inspection carried out and reported under the inspection report number(s) referred above. It does not relieve the manufacturer / supplier of their contractual obligations. Our liability is limited to the fee charged for this inspection.";
    $addField($s, 'disclaimer', 'Disclaimer', 'richtext', $disc, '', 2);
    $s = $addSection('Sign-off', 'Prepared / Reviewed / Approved — name, designation and date auto-fill from the workflow.', 0, 1);
    $addField($s, 'signoff', 'For ' . app_name(), 'sigblock', "Prepared by\nReviewed by\nApproved by", '', 2);

    return $typeId;
}

// The default release-statement wording (editable per Release Note). The report
// number(s) are entered by hand into the "Inspection Report No(s)." field, so the
// statement itself carries a placeholder rather than an auto-inserted number.
function idems_release_statement_default() {
    $t = function_exists('setting_get') ? trim((string)setting_get('release_statement_default', '')) : '';
    if ($t !== '') return $t;
    return "The subject inspection was carried out with respect to the approved documents and found meeting requirements. For further details please refer Inspection Report Number(s) noted below.";
}

// Resolve (creating/seeding once) the form-driven "RN — Release Note" report type
// so a Release Note is designed like the inspection report and renders the same.
function idems_build_release_note() {
    $pdo = db();
    $t = ops_one("SELECT id FROM report_types WHERE code='RN'");
    if ($t) { $typeId = (int)$t['id']; }
    else {
        $sort = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_types");
        $pdo->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,created_at) VALUES (?,?, 'TPIA_REPORT',1,0,?,?)")
            ->execute(['RN', 'Release Note', $sort, date('c')]);
        $typeId = (int)$pdo->lastInsertId();
    }
    $has = (int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=?", [$typeId]);
    if (!$has) idems_install_release_note_sections($typeId);
    return $typeId;
}

// Resolve (creating/seeding once) the scored "VASR — Vendor Assessment Report"
// report type. It is an ordinary form-driven report that additionally carries
// weighted, scored category sections, so it renders through the same engine and
// produces a scorecard. Industry-neutral: the categories fit any supplier.
function idems_build_vendor_assessment() {
    $pdo = db();
    $t = ops_one("SELECT id FROM report_types WHERE code='VASR'");
    if ($t) { $typeId = (int)$t['id']; }
    else {
        $sort = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_types");
        $pdo->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,created_at) VALUES (?,?, 'TPIA_REPORT',1,0,?,?)")
            ->execute(['VASR', 'Vendor Assessment Report', $sort, date('c')]);
        $typeId = (int)$pdo->lastInsertId();
    }
    $has = (int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=?", [$typeId]);
    if (!$has) idems_install_vendor_assessment_sections($typeId);
    return $typeId;
}

// Seed the Vendor Assessment form. Category sections carry weighted, scored
// fields on a shared 4-point rating scale; "Not applicable" is a valid answer
// that is simply excluded from the score (not counted as zero).
function idems_install_vendor_assessment_sections($typeId) {
    $pdo = db();
    $so = 0; $fo = 0;
    $addSection = function($title, $help = '', $pgb = 0, $keep = 0) use ($pdo, $typeId, &$so) {
        $so += 10;
        $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,page_break_before,keep_together,sort_order) VALUES (?,?,?,?,?,?)")
            ->execute([$typeId, $title, $help, $pgb, $keep, $so]);
        return (int)$pdo->lastInsertId();
    };
    // Plain (unscored) field.
    $addField = function($secId, $fkey, $label, $ftype, $opts = '', $span = 1, $help = '') use ($pdo, $typeId, &$fo) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,help,sort_order,col_span) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$typeId, $secId, $fkey, $label, $ftype, $opts, $help, $fo, $span]);
    };
    // Table field — columns go in table_cols, not options.
    $addTable = function($secId, $fkey, $label, $cols, $span = 2) use ($pdo, $typeId, &$fo) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,table_cols,sort_order,col_span) VALUES (?,?,?,?, 'table',?,?,?)")
            ->execute([$typeId, $secId, $fkey, $label, $cols, $fo, $span]);
    };
    // Shared 4-point rating scale + its score map. "Not applicable" is offered
    // but deliberately absent from the map, so it scores null (excluded).
    $scale = "Excellent\nGood\nFair\nPoor\nNot applicable";
    $map = json_encode(['Excellent' => 100, 'Good' => 75, 'Fair' => 50, 'Poor' => 25]);
    // Scored rating field (weighted).
    $addScored = function($secId, $fkey, $label, $weight, $help = '') use ($pdo, $typeId, &$fo, $scale, $map) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,score_map,weight,help,sort_order,col_span) VALUES (?,?,?,?, 'select',?,?,?,?,?,1)")
            ->execute([$typeId, $secId, $fkey, $label, $scale, $map, $weight, $help, $fo]);
    };

    // Section I — vendor identification (informational; not scored).
    $s = $addSection('Vendor identification', 'Who is being assessed, where and why.', 0, 1);
    $addField($s, 'vendor', 'Vendor / supplier name', 'text');
    $addField($s, 'vendor_code', 'Vendor code', 'text');
    $addField($s, 'location', 'Location / address', 'text', '', 2);
    $addField($s, 'category', 'Supply category / commodity', 'text');
    $addField($s, 'contact', 'Contact person', 'text');
    $addField($s, 'assessment_type', 'Assessment type', 'select', "New qualification\nRe-qualification\nPeriodic\nFor cause\nDesk review");
    $addField($s, 'assessment_date', 'Assessment date', 'date');
    $addField($s, 'assessor', 'Assessed by', 'text');

    // Scored categories. Weights are the category's share of the overall score.
    $s = $addSection('Quality management system', 'Documented system, certification, control of records and processes.');
    $addScored($s, 'q_qms_cert', 'Certified / documented QMS in place', 3, 'ISO 9001 or equivalent, current and appropriate to scope.');
    $addScored($s, 'q_qms_control', 'Document & record control', 2);
    $addScored($s, 'q_qms_ncr', 'Nonconformity & corrective-action handling', 2);
    $addScored($s, 'q_qms_calib', 'Calibration & measurement control', 2);

    $s = $addSection('Manufacturing / operational capability', 'Plant, equipment, capacity and process control.');
    $addScored($s, 'q_cap_equip', 'Adequacy of plant & equipment', 3);
    $addScored($s, 'q_cap_capacity', 'Capacity vs anticipated demand', 2);
    $addScored($s, 'q_cap_process', 'Process control & work instructions', 2);
    $addScored($s, 'q_cap_maint', 'Maintenance & housekeeping', 1);

    $s = $addSection('Technical competence & resources', 'People, skills and engineering capability.');
    $addScored($s, 'q_tech_people', 'Competence of key personnel', 3);
    $addScored($s, 'q_tech_eng', 'Engineering / design capability', 2);
    $addScored($s, 'q_tech_test', 'In-house testing / inspection ability', 2);

    $s = $addSection('Delivery & commercial performance', 'Track record on time, quantity and responsiveness.');
    $addScored($s, 'q_del_ontime', 'On-time delivery track record', 3);
    $addScored($s, 'q_del_quality', 'Delivered quality / rejection history', 3);
    $addScored($s, 'q_del_response', 'Responsiveness & communication', 1);

    $s = $addSection('HSE & compliance', 'Safety, environment, and legal / statutory compliance.');
    $addScored($s, 'q_hse_safety', 'Occupational health & safety', 2);
    $addScored($s, 'q_hse_env', 'Environmental controls', 1);
    $addScored($s, 'q_hse_legal', 'Statutory & regulatory compliance', 2);

    $s = $addSection('Financial & business stability', 'Ability to sustain supply.');
    $addScored($s, 'q_fin_stability', 'Financial stability', 2);
    $addScored($s, 'q_fin_continuity', 'Business continuity / single-source risk', 1);

    // Findings & outcome (informational; the scorecard drives the recommendation).
    $s = $addSection('Findings', 'Observations, strengths, gaps and any nonconformities raised.', 0, 1);
    $addField($s, 'strengths', 'Strengths', 'textarea', '', 2);
    $addField($s, 'gaps', 'Gaps / concerns', 'textarea', '', 2);
    $addTable($s, 'findings', 'Findings / observations',
        "Sr. No.|merge\nArea\nObservation\nSeverity|select|lookup:severity\nAction required\nTarget date|date");

    // Customer complaints & resolution — the vendor's complaint history and how
    // each was resolved. Auto-prefilled from the complaint register when the
    // report is created against a vendor (idems_vendor_prefill_complaints).
    $s = $addSection('Customer complaints & resolution', 'Complaints raised against this vendor and how they were resolved. Prefilled from the complaint register; add older or external complaints as needed.', 0, 1);
    $addField($s, 'complaints_summary', 'Complaint history & trend', 'textarea', '', 2);
    $addTable($s, 'complaints_review', 'Complaints & resolution',
        "Ref / date|merge\nNature of complaint\nRoot cause\nCorrective action / resolution\nStatus|select|Open,Closed\nEffectiveness verified|select|Yes,No,N/A");

    $s = $addSection('Recommendation', 'The overall recommendation, informed by the score.', 0, 1);
    $addField($s, 'recommendation', 'Recommendation', 'select',
        "Approved\nApproved with conditions\nConditional — re-assess after actions\nNot approved", 2);
    $addField($s, 'valid_until', 'Approval valid until', 'date');
    $addField($s, 'reassess_on', 'Next re-assessment due', 'date');
    $addField($s, 'conclusion', 'Conclusion / remarks', 'textarea', '', 2);

    // Sign-off, matching the other reports.
    $s = $addSection('Sign-off', 'Prepared / Reviewed / Approved — name, designation and date auto-fill from the workflow.', 0, 1);
    $addField($s, 'signoff', 'For ' . app_name(), 'sigblock', "Assessed by\nReviewed by\nApproved by", 2);

    return $typeId;
}

// Resolve (creating/seeding once) the "VAR — Vendor Audit Report" type. A
// vendor-scoped audit whose findings table feeds the NCR / CAPA engine on issue.
function idems_build_vendor_audit() {
    $pdo = db();
    $t = ops_one("SELECT id FROM report_types WHERE code='VAR'");
    if ($t) { $typeId = (int)$t['id']; }
    else {
        $sort = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_types");
        $pdo->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,created_at) VALUES (?,?, 'TPIA_REPORT',1,0,?,?)")
            ->execute(['VAR', 'Vendor Audit Report', $sort, date('c')]);
        $typeId = (int)$pdo->lastInsertId();
    }
    $has = (int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=?", [$typeId]);
    if (!$has) idems_install_vendor_audit_sections($typeId);
    return $typeId;
}

// Seed the Vendor Audit form. The Audit findings table is the one the NCR engine
// reads on issue: each Major / Minor row becomes a nonconformity against the
// vendor. Column headers are matched by keyword, so wording can be edited freely.
function idems_install_vendor_audit_sections($typeId) {
    $pdo = db();
    $so = 0; $fo = 0;
    $addSection = function($title, $help = '', $pgb = 0, $keep = 0) use ($pdo, $typeId, &$so) {
        $so += 10;
        $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,page_break_before,keep_together,sort_order) VALUES (?,?,?,?,?,?)")
            ->execute([$typeId, $title, $help, $pgb, $keep, $so]);
        return (int)$pdo->lastInsertId();
    };
    $addField = function($secId, $fkey, $label, $ftype, $opts = '', $span = 1, $help = '') use ($pdo, $typeId, &$fo) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,help,sort_order,col_span) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$typeId, $secId, $fkey, $label, $ftype, $opts, $help, $fo, $span]);
    };
    $addTable = function($secId, $fkey, $label, $cols, $span = 2, $help = '') use ($pdo, $typeId, &$fo) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,table_cols,help,sort_order,col_span) VALUES (?,?,?,?, 'table',?,?,?,?)")
            ->execute([$typeId, $secId, $fkey, $label, $cols, $help, $fo, $span]);
    };

    // Section I — audit identification.
    $s = $addSection('Audit identification', 'Who was audited, against what, when and by whom.', 0, 1);
    $addField($s, 'vendor', 'Vendor / supplier audited', 'text');
    $addField($s, 'vendor_code', 'Vendor code', 'text');
    $addField($s, 'location', 'Site / location', 'text', '', 2);
    $addField($s, 'audit_type', 'Audit type', 'select', "Adequacy (desktop)\nCompliance (on-site)\nSurveillance\nRe-audit / follow-up\nPre-award");
    $addField($s, 'audit_standard', 'Audit criteria / standard', 'text');
    $addField($s, 'audit_date', 'Audit date', 'date');
    $addField($s, 'auditor', 'Lead auditor', 'text');
    $addField($s, 'auditee', 'Auditee / vendor representative', 'text');
    $addField($s, 'prev_audit_ref', 'Previous audit reference', 'text');

    // Section II — scope & criteria.
    $s = $addSection('Scope & criteria', 'What the audit covered and the requirements audited against.', 0, 1);
    $addField($s, 'scope', 'Audit scope', 'textarea', '', 2);
    $addField($s, 'criteria', 'Criteria / clauses audited', 'textarea', '', 2);

    // Section III — the full audit checklist. Every check point with its result;
    // the exceptions are also captured in the findings table below.
    $s = $addSection('Audit checklist', 'Every check point examined during the audit, with its result. Non-conformities are also listed in the findings table.');
    $addTable($s, 'audit_checklist', 'Checklist',
        "Ref|merge\nCheck item\nRequirement / standard\nResult|select|Conforms,Minor NC,Major NC,Observation,N/A\nEvidence referred\nAttached|select|Yes,No,N/A",
        2, 'Result: Conforms · Minor NC · Major NC · Observation · N/A. "Evidence referred" lists the record(s) seen; "Attached" ticks whether that evidence is attached to this report. Only Major/Minor findings become nonconformities (from the findings table below).');

    // Section IV — audit findings. THIS is the table the NCR engine reads.
    $s = $addSection('Audit findings', 'Each finding graded Major / Minor / Observation. On issue, Major and Minor findings are raised as nonconformities against this vendor.');
    $addTable($s, 'audit_findings', 'Findings',
        "Sr. No.|merge\nClause / requirement\nFinding / observation\nCategory|select|Major,Minor,Observation\nObjective evidence\nAttached|select|Yes,No,N/A\nCorrective action required\nTarget date|date",
        2, 'Grade each finding. Major = a requirement not met / system gap; Minor = partial lapse; Observation = improvement opportunity (not raised as an NCR). "Attached" ticks whether the objective evidence is attached to this report.');

    // Section IV — conformance summary (lightly scored, reuses the scoring engine).
    $s = $addSection('Conformance summary', 'A quick rating per audited area — feeds an overall conformance score.');
    $scale = "Conforms\nMinor gaps\nMajor gaps\nNot applicable";
    $map = json_encode(['Conforms' => 100, 'Minor gaps' => 60, 'Major gaps' => 20]);
    $addScored = function($secId, $fkey, $label, $weight) use ($pdo, $typeId, &$fo, $scale, $map) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,score_map,weight,sort_order,col_span) VALUES (?,?,?,?, 'select',?,?,?,?,1)")
            ->execute([$typeId, $secId, $fkey, $label, $scale, $map, $weight, $fo]);
    };
    $addScored($s, 'c_qms', 'Quality management system', 3);
    $addScored($s, 'c_process', 'Process & production control', 3);
    $addScored($s, 'c_material', 'Material & incoming control', 2);
    $addScored($s, 'c_measure', 'Inspection, test & calibration', 2);
    $addScored($s, 'c_docs', 'Documentation & traceability', 2);
    $addScored($s, 'c_capa', 'Corrective-action effectiveness', 1);

    // Customer complaints & resolution reviewed during the audit. Auto-prefilled
    // from the complaint register when the audit is raised against a vendor.
    $s = $addSection('Customer complaints & resolution', 'Complaints raised against this vendor and how they were resolved, reviewed during the audit. Prefilled from the complaint register.');
    $addField($s, 'complaints_summary', 'Complaint history & effectiveness of resolution', 'textarea', '', 2);
    $addTable($s, 'complaints_review', 'Complaints & resolution',
        "Ref / date|merge\nNature of complaint\nRoot cause\nCorrective action / resolution\nStatus|select|Open,Closed\nEffectiveness verified|select|Yes,No,N/A");

    // Section V — conclusion.
    $s = $addSection('Conclusion', 'Overall audit outcome and the recommendation for this vendor.', 0, 1);
    $addField($s, 'summary', 'Audit summary', 'textarea', '', 2);
    $addField($s, 'recommendation', 'Recommendation', 'select',
        "Approved\nApproved with conditions\nConditional — re-audit after actions\nNot approved / suspend", 2);
    $addField($s, 'next_audit', 'Next audit due', 'date');
    $addField($s, 'nc_major', 'No. of Major NCs', 'number');
    $addField($s, 'nc_minor', 'No. of Minor NCs', 'number');

    // Sign-off.
    $s = $addSection('Sign-off', 'Audited / Reviewed / Approved — name, designation and date auto-fill from the workflow.', 0, 1);
    $addField($s, 'signoff', 'For ' . app_name(), 'sigblock', "Audited by\nReviewed by\nApproved by", 2);

    return $typeId;
}

// Raise nonconformities from an issued Vendor Audit's findings table. Each Major
// or Minor finding becomes an NCR against the vendor (source AUDIT), linked to
// this report. Observations are not raised. Idempotent: if any NCR already
// exists for this report, none are raised again. Returns the number raised.
function idems_raise_ncrs_from_audit($doc) {
    if (!function_exists('ncr_create')) return 0;
    $docId = (int)($doc['id'] ?? 0); if (!$docId) return 0;
    // Idempotency — never double-raise for the same report.
    try { if ((int)ops_val("SELECT COUNT(*) FROM nonconformities WHERE report_doc_id=?", [$docId]) > 0) return 0; } catch (Throwable $e) { return 0; }
    $fields = idems_fields((int)($doc['report_type_id'] ?? 0));
    $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = [];
    // Find the findings table (a table field with a "category"/"finding" column).
    $tbl = null;
    foreach ($fields as $f) {
        if (($f['ftype'] ?? '') !== 'table') continue;
        $defs = idems_table_col_defs($f);
        $hasCat = false; foreach ($defs as $d) { $l = strtolower((string)$d['label']); if (strpos($l,'category')!==false || strpos($l,'severity')!==false || strpos($l,'grade')!==false) { $hasCat = true; break; } }
        if ($hasCat) { $tbl = $f; break; }
    }
    if (!$tbl) return 0;
    $rows = $data[$tbl['fkey']] ?? null; if (!is_array($rows) || !$rows) return 0;
    $defs = idems_table_col_defs($tbl);
    $findKey = function($needles) use ($defs) { foreach ($defs as $ck => $d) { $l = strtolower((string)$d['label']); foreach ($needles as $n) if (strpos($l, $n) !== false) return $ck; } return null; };
    $catK    = $findKey(['category','severity','grade']);
    $clauseK = $findKey(['clause','requirement']);
    $findK   = $findKey(['finding','observation','description','nonconform']);
    $actK    = $findKey(['corrective','action']);
    $dateK   = $findKey(['target','due','date']);
    $evK     = $findKey(['evidence']);
    if ($catK === null || $findK === null) return 0;
    $auditDate = ''; foreach ($fields as $f) if (($f['fkey'] ?? '') === 'audit_date') { $auditDate = trim((string)($data['audit_date'] ?? '')); break; }
    $n = 0;
    foreach ($rows as $i => $r) {
        $rr = (array)$r;
        $cat = strtolower(trim((string)($rr[$catK] ?? '')));
        if ($cat === '') continue;
        if (strpos($cat, 'major') !== false) $sev = 'MAJOR';
        elseif (strpos($cat, 'minor') !== false) $sev = 'MINOR';
        else continue; // observations (and anything else) are not raised
        $finding = trim((string)($rr[$findK] ?? '')); if ($finding === '') continue;
        $clause = $clauseK !== null ? trim((string)($rr[$clauseK] ?? '')) : '';
        $action = $actK !== null ? trim((string)($rr[$actK] ?? '')) : '';
        $evid   = $evK !== null ? trim((string)($rr[$evK] ?? '')) : '';
        $target = $dateK !== null ? trim((string)($rr[$dateK] ?? '')) : '';
        $descParts = array_filter([$finding, $evid !== '' ? 'Evidence: ' . $evid : '', $action !== '' ? 'Action required: ' . $action : '']);
        ncr_create([
            'source' => 'AUDIT',
            'source_note' => 'Vendor audit ' . (string)($doc['irn'] ?? ''),
            'report_doc_id' => $docId,
            'partner_id' => (int)($doc['vendor_id'] ?? 0) ?: null,
            'office_id' => (int)($doc['office_id'] ?? 0) ?: null,
            'sbu' => (string)($doc['sbu'] ?? ''),
            'title' => substr(($clause !== '' ? $clause . ' — ' : '') . $finding, 0, 255),
            'description' => implode("\n", $descParts),
            'clause' => $clause,
            'severity' => $sev,
            'detected_on' => $auditDate,
            'due_on' => $target,
        ]);
        $n++;
    }
    if ($n > 0 && function_exists('idems_log')) idems_log('report_doc', $docId, 'NCRS_RAISED', ['irn'=>$doc['irn'] ?? '', 'count'=>$n]);
    return $n;
}
// ===========================================================================
//  Universal Expediting Report (ER) — supplier progress / schedule intelligence.
//  Industry-neutral: milestones + commitments + evidence + a three-date forecast
//  (required vs vendor vs expeditor) are the heart, NOT a single "% complete".
//  Weighted progress reuses the scoring engine; evidence ticks, the delay/NCR
//  links and the deterministic auditor are all reused.
// ===========================================================================
function idems_build_expediting() {
    $pdo = db();
    $t = ops_one("SELECT id FROM report_types WHERE code='ER'");
    if ($t) { $typeId = (int)$t['id']; }
    else {
        $sort = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_types");
        $pdo->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,created_at) VALUES (?,?, 'TPIA_REPORT',1,0,?,?)")
            ->execute(['ER', 'Expediting Report', $sort, date('c')]);
        $typeId = (int)$pdo->lastInsertId();
    }
    $has = (int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=?", [$typeId]);
    if (!$has) idems_install_expediting_sections($typeId);
    return $typeId;
}

function idems_install_expediting_sections($typeId) {
    $pdo = db();
    $so = 0; $fo = 0;
    $addSection = function($title, $help = '', $pgb = 0, $keep = 0) use ($pdo, $typeId, &$so) {
        $so += 10;
        $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,page_break_before,keep_together,sort_order) VALUES (?,?,?,?,?,?)")
            ->execute([$typeId, $title, $help, $pgb, $keep, $so]);
        return (int)$pdo->lastInsertId();
    };
    $addField = function($secId, $fkey, $label, $ftype, $opts = '', $span = 1, $help = '') use ($pdo, $typeId, &$fo) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,help,sort_order,col_span) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$typeId, $secId, $fkey, $label, $ftype, $opts, $help, $fo, $span]);
    };
    $addTable = function($secId, $fkey, $label, $cols, $span = 2, $help = '') use ($pdo, $typeId, &$fo) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,table_cols,help,sort_order,col_span) VALUES (?,?,?,?, 'table',?,?,?,?)")
            ->execute([$typeId, $secId, $fkey, $label, $cols, $help, $fo, $span]);
    };
    // Weighted progress stage — a number 0-100 with a configurable weight; the
    // scoring engine rolls these up into one weighted overall progress %.
    $addProgress = function($secId, $fkey, $label, $weight) use ($pdo, $typeId, &$fo) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,max_score,weight,placeholder,sort_order,col_span) VALUES (?,?,?,?, 'number',100,?, '0-100',?,1)")
            ->execute([$typeId, $secId, $fkey, $label, $weight, $fo]);
    };

    // Part 3-6 — PO / project / vendor / scope (baseline & identity).
    $s = $addSection('Project & purchase order', 'What is being expedited, for whom, against which order and by when.', 0, 1);
    $addField($s, 'vendor', 'Vendor / supplier', 'text');
    $addField($s, 'vendor_code', 'Vendor code', 'text');
    $addField($s, 'client', 'Client', 'text');
    $addField($s, 'project', 'Project', 'text');
    $addField($s, 'po_number', 'Purchase order no.', 'text');
    $addField($s, 'po_date', 'PO date', 'date');
    $addField($s, 'product', 'Product / equipment / service', 'text', '', 2);
    $addField($s, 'package', 'Package / tag / item', 'text');
    $addField($s, 'quantity', 'Quantity', 'text');
    $addField($s, 'location', 'Vendor location', 'text', '', 2);
    $addField($s, 'criticality', 'Criticality', 'select', "Critical\nHigh\nMedium\nLow");
    $addField($s, 'required_date', 'Required delivery date', 'date');

    // Part 5-6 — expediting scope, method, period.
    $s = $addSection('Expediting scope & method', 'How this is being expedited and for what reporting period.', 0, 1);
    $addField($s, 'expediting_type', 'Expediting type', 'select', "lookup:expediting_type");
    $addField($s, 'expediting_method', 'Method', 'select', "lookup:expediting_method");
    $addField($s, 'period_from', 'Reporting period from', 'date');
    $addField($s, 'period_to', 'Reporting period to', 'date');
    $addField($s, 'expeditor', 'Expeditor', 'text');
    $addField($s, 'prev_report', 'Previous report ref', 'text');

    // Part 7 — executive summary.
    $s = $addSection('Executive summary', 'The management-level headline: overall status and the story of this period.', 0, 1);
    $addField($s, 'overall_status', 'Overall status', 'select', "lookup:expediting_status", 1);
    $addField($s, 'summary', 'Summary', 'textarea', '', 2);

    // Part 8 — weighted progress (reuses the scoring engine). Weights are the
    // stage's share of overall progress and are fully editable in the builder.
    $s = $addSection('Overall progress (weighted)', 'Percent complete per stage; the overall figure is the weighted roll-up. Edit the weights to suit the scope — a service or software supplier weights different stages than a fabricator.');
    $addProgress($s, 'p_engineering', 'Engineering / design', 10);
    $addProgress($s, 'p_documents', 'Documentation / drawings', 10);
    $addProgress($s, 'p_procurement', 'Procurement / sub-orders', 15);
    $addProgress($s, 'p_material', 'Raw material received', 10);
    $addProgress($s, 'p_manufacture', 'Manufacturing / service', 35);
    $addProgress($s, 'p_inspection', 'Inspection / testing', 10);
    $addProgress($s, 'p_dispatch', 'Packing / dispatch readiness', 10);

    // Part 10 — MILESTONE STATUS — the heart. Baseline vs vendor commitment vs
    // latest forecast vs actual, with a traffic-light status.
    $s = $addSection('Milestone status', 'The heart of the report: what was planned, committed, forecast and actually achieved for each milestone.');
    $addTable($s, 'milestones', 'Milestones',
        "Milestone|merge\nBaseline|date\nVendor commitment|date\nLatest forecast|date\nActual|date\nStatus|select|Complete,On track,At risk,Delayed,Not started\nRemarks",
        2, 'Status is the traffic light: Complete (green) · On track (green) · At risk (amber) · Delayed (red) · Not started (grey).');

    // Part 11 — engineering / documentation expediting.
    $s = $addSection('Engineering & documentation', 'Deliverable documents — submission and approval are tracked separately (submission is not approval).');
    $addTable($s, 'doc_register', 'Documents',
        "Document|merge\nRev\nPlanned submission|date\nActual submission|date\nReview status|select|Not due,Submitted,Under review,Approved,Approved with comments,Rejected,Revise & resubmit,Overdue\nForecast approval|date\nDelay (days)|number");

    // Part 12-13 — procurement / raw material monitoring.
    $s = $addSection('Procurement & material', 'Long-lead and raw-material status — ordered vs expected vs received.');
    $addTable($s, 'material_register', 'Materials',
        "Material|merge\nSpec / grade\nQty\nRequired|date\nOrdered|date\nExpected|date\nReceived|date\nStatus|select|To order,Ordered,In transit,Received,Shortage,Rejected\nSupplier");

    // Part 14 — inspection / test status. Auto-prefilled from the Inspection
    // engine (reports raised against this vendor). Expediting never edits results.
    $s = $addSection('Inspection & test status', 'Inspection / test activity against this vendor — prefilled from the inspection reports. Expediting reflects, it does not change, inspection results.');
    $addTable($s, 'inspection_status', 'Inspection & tests',
        "Report / IRN|merge\nType\nDate|date\nResult|select|Accepted,Accepted (obs.),Rejected,Hold,Pending\nStatus\nRelease");

    // Part 15, 31 — quality / NCR summary. Auto-prefilled from the NCR register.
    $s = $addSection('Quality / NCR summary', 'Nonconformities against this vendor and whether they may affect delivery — prefilled from the NCR register.');
    $addField($s, 'ncr_summary', 'Quality / NCR narrative', 'textarea', '', 2);
    $addTable($s, 'ncr_register', 'Nonconformities',
        "NCR ref|merge\nDescription\nSeverity|select|Major,Minor,Observation\nStatus\nDue|date\nDelivery impact|select|Yes,No,Unknown");

    // Part 25 — commitment register (never overwrite the original; every revision
    // kept). Carries an evidence tick.
    $s = $addSection('Commitment register', 'Every material vendor commitment — the original due date is preserved; revisions and their reasons are recorded.');
    $addTable($s, 'commitments', 'Commitments',
        "Commitment|merge\nOriginal due|date\nRevised due|date\nForecast|date\nActual|date\nStatus|select|Not started,In progress,Completed,Delayed,At risk\nReason for change\nEvidence referred\nAttached|select|Yes,No,N/A", 2);

    // Part 21 — delay register.
    $s = $addSection('Delay register', 'Each delay with its cause, responsible party, impact and recovery action. Cause is not assumed to be the vendor.');
    $addTable($s, 'delays', 'Delays',
        "Ref|merge\nActivity\nOriginal due|date\nForecast|date\nDelay (days)|number\nCause\nCategory|select|lookup:expediting_delay_category\nResponsible|select|Vendor,Client,Sub-supplier,Third party,Internal,Shared,External,Unknown\nImpact\nRecovery action\nStatus|select|Open,In progress,Recovered,Closed", 2);

    // Part 16, 25-27 — capacity, resources & manpower.
    $s = $addSection('Capacity & resources', 'Whether the vendor has the capacity, manpower and equipment to meet the plan.');
    $addField($s, 'capacity_note', 'Capacity assessment', 'textarea', '', 2);
    $addTable($s, 'resources', 'Resources / manpower',
        "Resource / manpower|merge\nRequired\nAvailable\nShortage\nImpact");

    // Part 28-29 — sub-supplier monitoring (expediting goes beyond the vendor).
    $s = $addSection('Sub-supplier status', 'Sub-vendors, material suppliers, special processes, test labs and transporters — often the real constraint.');
    $addTable($s, 'subsuppliers', 'Sub-suppliers',
        "Sub-supplier|merge\nScope\nCommitment|date\nStatus|select|On track,At risk,Delayed,Complete\nProgress\nDelay (days)|number\nImpact");

    // Part 18, 33 — packing & preservation.
    $s = $addSection('Packing & preservation', 'Packaging, preservation, marking and protection readiness.');
    $addTable($s, 'packing', 'Packing & preservation',
        "Item|merge\nSpecification\nStatus|select|Not started,In progress,Complete,Pending approval,Rejected\nRemarks");

    // Part 34 — dispatch readiness checklist. Prefilled with the standard items on
    // creation; the deterministic readiness % + mandatory blockers drive QA.
    $s = $addSection('Dispatch readiness', 'The formal go/no-go checklist. Mandatory items that are not "Yes" are blockers, whatever the overall readiness %.', 0, 1);
    $addTable($s, 'dispatch_readiness', 'Dispatch readiness',
        "Readiness item|merge\nStatus|select|Yes,No,Pending,N/A\nMandatory|select|Yes,No\nRemarks");

    // Part 20, 35 — logistics & delivery.
    $s = $addSection('Logistics & delivery', 'Shipment mode, carrier, route and transit status through to arrival.');
    $addTable($s, 'logistics', 'Logistics',
        "Mode|select|Road,Rail,Sea,Air,Courier,Multimodal\nCarrier\nOrigin\nDestination\nDispatch|date\nExpected arrival|date\nActual arrival|date\nTracking no.\nStatus|select|Planned,Dispatched,In transit,Arrived,Delayed");

    // Part 28, 36-37, 73 — delivery forecast: the THREE dates that make a TPIA
    // expediting report worth more than the vendor's own statement.
    $s = $addSection('Delivery forecast', 'The required date, what the vendor commits/forecasts, and the expeditor\'s own evidence-based forecast — kept distinct on purpose.', 0, 1);
    $addField($s, 'f_required', 'Required date (contract)', 'date');
    $addField($s, 'f_vendor_commit', 'Original vendor commitment', 'date');
    $addField($s, 'f_vendor_forecast', 'Latest vendor forecast', 'date');
    $addField($s, 'f_expeditor', "Expeditor's forecast", 'date');
    $addField($s, 'f_confidence', 'Forecast confidence', 'select', "High\nMedium\nLow");
    $addField($s, 'f_basis', 'Basis of expeditor forecast', 'textarea', '', 2);

    // Part 29-31 — expeditor's independent assessment & recommendation.
    $s = $addSection("Expeditor's assessment & recommendation", 'The expeditor\'s independent professional view, not a restatement of the vendor\'s.', 0, 1);
    $addField($s, 'assessment', 'Independent assessment', 'textarea', '', 2);
    $addField($s, 'recommendation', 'Recommendation', 'select',
        "Continue monitoring\nIncrease frequency\nVendor meeting required\nManagement escalation\nRecovery plan required\nClient decision required\nPartial shipment recommended\nInspection priority required\nRe-baseline required\nNo action required", 2);
    $addField($s, 'next_followup', 'Next planned follow-up', 'date');

    // Sign-off.
    $s = $addSection('Sign-off', 'Prepared / Reviewed / Approved — name, designation and date auto-fill from the workflow.', 0, 1);
    $addField($s, 'signoff', 'For ' . app_name(), 'sigblock', "Prepared by\nReviewed by\nApproved by", 2);

    return $typeId;
}

// The standard dispatch-readiness checklist (item => mandatory). Editable in the
// report like any table; prefilled on a new ER so it is ready to complete.
function idems_dispatch_checklist_items() {
    return [
        ['Product / scope complete', 'Yes'], ['Inspection complete', 'Yes'], ['Testing complete', 'Yes'],
        ['NCRs resolved / dispositioned', 'Yes'], ['Documentation / dossier complete', 'Yes'],
        ['Packing complete', 'Yes'], ['Preservation complete', 'No'], ['Marking complete', 'Yes'],
        ['Client release / clearance available', 'Yes'], ['Transport arranged', 'No'], ['Dispatch documents ready', 'Yes'],
    ];
}
// Prefill rows for the dispatch-readiness table (status Pending, mandatory set).
function idems_dispatch_prefill($field) {
    if (!$field) return [];
    $keys = array_keys(idems_table_col_defs($field)); $rows = [];
    foreach (idems_dispatch_checklist_items() as $it) {
        $vals = [$it[0], 'Pending', $it[1], '']; $row = [];
        foreach ($keys as $i => $k) $row[$k] = $vals[$i] ?? ''; $rows[] = $row;
    }
    return $rows;
}
// Deterministic dispatch readiness: % of applicable items that are "Yes", plus
// the mandatory items that are NOT yet met (the blockers — a report can be 90%
// ready and still blocked). Returns null when there is no checklist.
function idems_expediting_dispatch_readiness($fields, $data) {
    foreach ($fields as $f) {
        if (($f['ftype'] ?? '') !== 'table' || ($f['fkey'] ?? '') !== 'dispatch_readiness') continue;
        $rows = $data[$f['fkey']] ?? null; if (!is_array($rows) || !$rows) return null;
        $defs = idems_table_col_defs($f); $itemK=null;$stK=null;$mandK=null;
        foreach ($defs as $ck=>$d){ $l=strtolower((string)$d['label']); if(strpos($l,'item')!==false)$itemK=$ck; elseif($l==='status')$stK=$ck; elseif(strpos($l,'mandator')!==false)$mandK=$ck; }
        if ($stK===null) return null;
        $applic=0;$yes=0;$blockers=[];
        foreach ($rows as $r){ $rr=(array)$r; $st=strtolower(trim((string)($rr[$stK]??''))); if($st===''||$st==='n/a'||$st==='na') continue;
            $applic++; if($st==='yes'){$yes++;} else { $mand=strtolower(trim((string)($rr[$mandK]??''))); if(in_array($mand,['yes','y','1','true'],true)) $blockers[]=trim((string)($rr[$itemK]??'Item')); }
        }
        $pct = $applic>0 ? round($yes/$applic*100,1) : null;
        return ['pct'=>$pct,'applicable'=>$applic,'yes'=>$yes,'blockers'=>$blockers];
    }
    return null;
}
// A plain-English band for a 0-100 progress figure (distinct from the assessment
// bands, which speak of approval rather than completion).
function idems_progress_band($p) {
    $p = (float)$p;
    if ($p >= 100) return 'Complete';
    if ($p >= 90)  return 'Near complete';
    if ($p >= 50)  return 'In progress';
    if ($p > 0)    return 'Early stage';
    return 'Not started';
}

// Deterministic expediting/schedule intelligence for an Expediting Report:
// the overall status, schedule variance and forecast picture, computed from the
// milestone table and the three forecast dates. No LLM — every value reproducible.
function idems_expediting_status($doc, $fields = null, $data = null) {
    if ($fields === null) $fields = idems_fields((int)($doc['report_type_id'] ?? 0));
    if ($data === null) { $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = []; }
    $pd = function($s) { $s = trim((string)$s); if ($s === '') return null; $t = strtotime($s); return $t ?: null; };
    // Milestone tallies from the milestone table.
    $mt = ['total'=>0,'complete'=>0,'delayed'=>0,'at_risk'=>0]; $worst = 0;
    foreach ($fields as $f) {
        if (($f['ftype'] ?? '') !== 'table') continue;
        $defs = idems_table_col_defs($f); $stK = null;
        foreach ($defs as $ck => $d) if (strtolower((string)$d['label']) === 'status') { $stK = $ck; break; }
        if ($stK === null || ($f['fkey'] ?? '') !== 'milestones') continue;
        foreach (($data[$f['fkey']] ?? []) as $r) {
            $rr = (array)$r; $st = strtolower(trim((string)($rr[$stK] ?? ''))); if ($st === '') continue;
            $mt['total']++;
            if (strpos($st,'complete') !== false) $mt['complete']++;
            elseif (strpos($st,'delay') !== false) { $mt['delayed']++; $worst = max($worst, 3); }
            elseif (strpos($st,'risk') !== false) { $mt['at_risk']++; $worst = max($worst, 2); }
        }
    }
    // Forecast picture — expeditor forecast vs the required date.
    $req = $pd($data['f_required'] ?? '') ?: $pd($data['required_date'] ?? '');
    $vend = $pd($data['f_vendor_forecast'] ?? '');
    $exp = $pd($data['f_expeditor'] ?? '');
    $expVar = ($req && $exp) ? (int)floor(($exp - $req) / 86400) : null;             // +ve = late
    $vendVsExp = ($vend && $exp) ? (int)floor(($exp - $vend) / 86400) : null;         // +ve = expeditor later than vendor
    if ($expVar !== null) { if ($expVar > 0) $worst = max($worst, 3); }
    // Overall status.
    if ($mt['total'] > 0 && $mt['complete'] === $mt['total'] && ($expVar === null || $expVar <= 0)) $status = 'COMPLETED';
    elseif ($worst >= 3) $status = ($expVar !== null && $expVar > 14) ? 'CRITICAL' : 'DELAYED';
    elseif ($worst >= 2) $status = 'AT RISK';
    else $status = 'ON TRACK';
    return ['status'=>$status, 'milestones'=>$mt, 'forecast'=>['required'=>$req?date('Y-m-d',$req):'','vendor'=>$vend?date('Y-m-d',$vend):'','expeditor'=>$exp?date('Y-m-d',$exp):'',
            'expeditor_variance_days'=>$expVar, 'vendor_vs_expeditor_days'=>$vendVsExp]];
}

// The immediately-preceding Expediting Report for the SAME vendor + PO, so the
// advisory layer can describe the trend (progress movement, forecast slip). Reads
// report_docs; matches on report type ER, same vendor and PO (from the data JSON
// or the linked columns), issued before this one, most recent first. Returns the
// row (with decoded data) or null. §expediting-trend
function idems_expediting_previous($doc) {
    $typeId = (int)($doc['report_type_id'] ?? 0);
    if (!$typeId) { $t = idems_type_id_by_code('ER'); $typeId = (int)$t; }
    if (!$typeId) return null;
    $data = is_array($doc['data'] ?? null) ? $doc['data'] : (json_decode($doc['data'] ?? '[]', true) ?: []);
    $vendor = strtolower(trim((string)($data['vendor'] ?? '')));
    $po     = strtolower(trim((string)($data['po_number'] ?? '')));
    if ($vendor === '' && $po === '' && empty($doc['vendor_id'])) return null;
    $selfId = (int)($doc['id'] ?? 0);
    $ord = ($doc['issue_date'] ?? '') !== '' ? $doc['issue_date'] : ($doc['created_at'] ?? '');
    // Pull recent ERs and pick the best earlier match in PHP (data lives in JSON).
    $rows = ops_all("SELECT * FROM report_docs WHERE report_type_id=? AND (id<>? OR ?=0) ORDER BY COALESCE(issue_date, created_at) DESC, id DESC LIMIT 60",
                    [$typeId, $selfId, $selfId]);
    foreach ($rows as $r) {
        if ($selfId && (int)$r['id'] === $selfId) continue;
        $ro = ($r['issue_date'] ?? '') !== '' ? $r['issue_date'] : ($r['created_at'] ?? '');
        if ($ord !== '' && $ro !== '' && strtotime($ro) >= strtotime($ord)) continue; // must be earlier
        $rd = json_decode($r['data'] ?? '[]', true); if (!is_array($rd)) $rd = [];
        $rv = strtolower(trim((string)($rd['vendor'] ?? '')));
        $rp = strtolower(trim((string)($rd['po_number'] ?? '')));
        $sameVendor = ($vendor !== '' && $rv === $vendor) || (!empty($doc['vendor_id']) && (int)($r['vendor_id'] ?? 0) === (int)$doc['vendor_id']);
        $samePo = ($po === '' || $rp === '' || $rp === $po);
        if ($sameVendor && $samePo) { $r['data'] = $rd; return $r; }
    }
    return null;
}

// Join a list into readable prose: "a", "a and b", "a, b and c".
function idems_list_join($items) {
    $items = array_values(array_filter(array_map(fn($x)=>trim((string)$x), (array)$items), fn($x)=>$x!==''));
    $n = count($items);
    if ($n === 0) return '';
    if ($n === 1) return $items[0];
    if ($n === 2) return $items[0].' and '.$items[1];
    return implode(', ', array_slice($items,0,-1)).' and '.end($items);
}

// §expediting-advisory (Phase 5) — a DETERMINISTIC advisory narrative for an
// Expediting Report: a draft executive summary, delay explanations, a forecast-
// plausibility read, progress anomalies and the trend vs the previous report.
// It is ADVISORY ONLY — it never changes stored values or gates issue; the
// expeditor reads it and may adopt the wording. No LLM: every line is a
// reproducible function of the report's own data (an optional AI provider can
// still add its separate suggestions elsewhere). Returns
// ['summary'=>string, 'items'=>[['kind','title','text','tone'], ...]].
function idems_expediting_advisory($doc, $fields = null, $data = null, $prev = null) {
    if ($fields === null) $fields = idems_fields((int)($doc['report_type_id'] ?? 0));
    if ($data === null) { $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = []; }
    $sections = idems_sections((int)($doc['report_type_id'] ?? 0));
    $num = function($v){ return rtrim(rtrim(number_format((float)$v,1),'0'),'.'); };
    $fmtD = function($s){ $s=trim((string)$s); if($s==='')return ''; $t=strtotime($s); return $t?date('d-M-Y',$t):$s; };
    // Locate a table field's rows + a label→column-key map for the columns we need.
    $tableRows = function($fkey) use ($fields,$data){
        foreach ($fields as $f) if (($f['fkey']??'')===$fkey && ($f['ftype']??'')==='table') {
            $rows = $data[$fkey] ?? null; if(!is_array($rows)) return [null,[]];
            $defs = idems_table_col_defs($f); $map=[];
            foreach ($defs as $ck=>$d) $map[strtolower((string)$d['label'])]=$ck;
            return [$rows,$map];
        }
        return [null,[]];
    };
    $col = function($map,$needles){ foreach($map as $lbl=>$ck){ foreach((array)$needles as $n){ if(strpos($lbl,$n)!==false) return $ck; } } return null; };

    $sc = function_exists('idems_score_doc') ? idems_score_doc($sections,$fields,$data) : null;
    $st = idems_expediting_status($doc,$fields,$data);
    $dr = function_exists('idems_expediting_dispatch_readiness') ? idems_expediting_dispatch_readiness($fields,$data) : null;
    $pct = ($sc && $sc['overall']!==null) ? (float)$sc['overall'] : null;
    $ms  = $st['milestones']; $fc = $st['forecast'];
    $items = [];

    // ---- NCR read (open majors + delivery impact) from the NCR register ----
    [$ncrRows,$ncrMap] = $tableRows('ncr_register');
    $ncrOpenMajor = 0; $ncrImpact = 0;
    if (is_array($ncrRows)) {
        $sevK=$col($ncrMap,['sever']); $stK=$col($ncrMap,['status']); $impK=$col($ncrMap,['impact','delivery']);
        foreach ($ncrRows as $r){ $rr=(array)$r;
            $sev=strtolower(trim((string)($rr[$sevK]??''))); $stt=strtolower(trim((string)($rr[$stK]??'')));
            $open = $stt!=='' && strpos($stt,'clos')===false;
            if ($open && strpos($sev,'major')!==false){ $ncrOpenMajor++;
                $imp=strtolower(trim((string)($rr[$impK]??''))); if(in_array($imp,['yes','y','1','true'],true)) $ncrImpact++; }
        }
    }

    // ---- Draft executive summary ---------------------------------------------
    $asOf = trim((string)($data['period_to'] ?? '')) ?: trim((string)($doc['issue_date'] ?? ''));
    $parts = [];
    if ($pct !== null) $parts[] = 'As at '.($asOf?$fmtD($asOf):'this report').', overall weighted progress is '.$num($pct).'% ('.strtolower(idems_progress_band($pct)).')';
    if ($ms['total']>0) {
        $seg=[]; if($ms['complete'])$seg[]=$ms['complete'].' complete'; if($ms['at_risk'])$seg[]=$ms['at_risk'].' at risk'; if($ms['delayed'])$seg[]=$ms['delayed'].' delayed';
        $parts[] = 'of '.$ms['total'].' tracked milestone'.($ms['total']==1?'':'s').($seg?', '.idems_list_join($seg):'');
    }
    $s1 = $parts ? (idems_list_join($parts).'.') : '';
    $s2 = '';
    if ($fc['expeditor']!=='' || $fc['vendor']!=='') {
        $bits=[];
        if ($fc['vendor']!=='')    $bits[]='the vendor forecasts delivery on '.$fmtD($fc['vendor']);
        if ($fc['expeditor']!=='') $bits[]='the expeditor independently forecasts '.$fmtD($fc['expeditor']);
        $s2 = ucfirst(idems_list_join($bits));
        if ($fc['required']!=='' && $fc['expeditor_variance_days']!==null) {
            $v=$fc['expeditor_variance_days'];
            $s2 .= ' against a required date of '.$fmtD($fc['required']).' — a variance of '.abs($v).' day'.(abs($v)==1?'':'s').' '.($v>0?'late':($v<0?'early':'(on time)'));
        }
        $s2 .= '.';
    }
    $s3 = '';
    if ($ncrOpenMajor>0) $s3 = $ncrOpenMajor.' open major NCR'.($ncrOpenMajor==1?'':'s').($ncrImpact>0?' ('.$ncrImpact.' flagged as delivery-impacting)':'').' remain'.($ncrOpenMajor==1?'s':'').' on the quality picture.';
    $s4 = '';
    if ($dr && $dr['pct']!==null) { $b=count($dr['blockers']); $s4='Dispatch readiness stands at '.$num($dr['pct']).'%'.($b>0?', with '.$b.' mandatory item'.($b==1?'':'s').' still open ('.idems_list_join(array_slice($dr['blockers'],0,3)).')':' with no mandatory blockers').'.'; }
    $summary = trim(implode(' ', array_filter([$s1,$s2,$s3,$s4])));

    // ---- Delay explanations ---------------------------------------------------
    [$dRows,$dMap] = $tableRows('delays');
    if (is_array($dRows) && $dRows) {
        $stgK=$col($dMap,['stage','activity','area']); $dayK=$col($dMap,['day','slip']); $causeK=$col($dMap,['cause','reason']);
        $respK=$col($dMap,['respons','owner','fault']); $impK=$col($dMap,['impact','effect']); $actK=$col($dMap,['action','recovery','mitig']); $stK2=$col($dMap,['status']);
        $lines=[];
        foreach ($dRows as $r){ $rr=(array)$r;
            $stg=trim((string)($rr[$stgK]??'')); if($stg==='') continue;
            $day=trim((string)($rr[$dayK]??'')); $cause=trim((string)($rr[$causeK]??''));
            $resp=trim((string)($rr[$respK]??'')); $imp=trim((string)($rr[$impK]??'')); $act=trim((string)($rr[$actK]??'')); $stt=trim((string)($rr[$stK2]??''));
            $t='The '.$stg.' activity'.($day!==''?' is delayed by ~'.$day.' day(s)':' is delayed');
            if($cause!=='') $t.=', attributed to '.rtrim($cause,'.').($resp!==''?' ('.$resp.')':'');
            $t.='.'; if($imp!=='') $t.=' Impact: '.rtrim($imp,'.').'.'; if($act!=='') $t.=' Agreed action: '.rtrim($act,'.').($stt!==''?' ('.strtolower($stt).')':'').'.';
            $lines[]=$t;
        }
        if ($lines) $items[]=['kind'=>'delay','title'=>'Delay explanation','tone'=>'warn','text'=>implode(' ', $lines)];
    }

    // ---- Forecast plausibility ------------------------------------------------
    $fp=''; $fpTone='neutral';
    $ve=$fc['vendor_vs_expeditor_days']; $ev=$fc['expeditor_variance_days'];
    if ($fc['vendor']!=='' && $fc['expeditor']!=='' && $ve!==null) {
        if ($ve>0) { $fp='The expeditor\'s forecast is '.$ve.' day'.($ve==1?'':'s').' later than the vendor\'s, indicating the vendor\'s '.$fmtD($fc['vendor']).' commitment is optimistic relative to the evidence on the ground'; $fpTone='warn'; }
        elseif ($ve<0) { $fp='The expeditor\'s forecast is '.abs($ve).' day'.(abs($ve)==1?'':'s').' earlier than the vendor\'s — an unusually conservative vendor position worth confirming'; }
        else $fp='The vendor and expeditor forecasts coincide';
    } elseif ($fc['expeditor']!=='') { $fp='Only the expeditor\'s forecast ('.$fmtD($fc['expeditor']).') is available for comparison'; }
    if ($fp!=='') {
        // Evidence backing: milestone completion + open majors + delayed count.
        $ev_bits=[];
        if ($ms['total']>0) $ev_bits[]=$num($ms['total']?round($ms['complete']/$ms['total']*100,0):0).'% of milestones complete';
        if ($ms['delayed']>0) $ev_bits[]=$ms['delayed'].' delayed';
        if ($ncrOpenMajor>0) $ev_bits[]=$ncrOpenMajor.' open major NCR'.($ncrOpenMajor==1?'':'s');
        if ($ev_bits) $fp.='. Evidence: '.idems_list_join($ev_bits);
        $fp=rtrim($fp,'.').'.';
        if ($ev!==null && $ev>0) { $fp.=' The expeditor forecast falls '.$ev.' day'.($ev==1?'':'s').' beyond the required date — recovery action is needed to protect the schedule.'; $fpTone='bad'; }
        elseif ($ev!==null && $ev<=0) { $fp.=' The expeditor forecast is within the required date; schedule risk is currently contained.'; }
        $items[]=['kind'=>'forecast','title'=>'Forecast plausibility','tone'=>$fpTone,'text'=>$fp];
    }

    // ---- Progress anomalies ---------------------------------------------------
    $an=[];
    if ($pct!==null && $pct>=80 && $ms['delayed']>0) $an[]='Headline progress reads '.$num($pct).'% yet '.$ms['delayed'].' milestone'.($ms['delayed']==1?' is':'s are').' delayed — the single percentage understates the schedule risk; read it against the milestone table, not on its own.';
    if ($pct!==null && $pct>=80 && $ev!==null && $ev>0) $an[]='Progress is high ('.$num($pct).'%) but the forecast still lands after the required date — the remaining scope is on the critical path.';
    if ($pct!==null && $pct>=80 && $dr && $dr['pct']!==null && count($dr['blockers'])>0) $an[]='Progress is high yet dispatch is blocked on '.count($dr['blockers']).' mandatory item(s) — completion percentage does not equal ready-to-ship.';
    if ($ncrImpact>0) $an[]=$ncrImpact.' open major NCR(s) are flagged delivery-impacting — these gate final inspection/dispatch regardless of the progress figure.';
    if ($an) $items[]=['kind'=>'anomaly','title'=>'Progress anomalies','tone'=>'warn','text'=>implode(' ', $an)];

    // ---- Trend vs the previous report -----------------------------------------
    if ($prev === null) $prev = idems_expediting_previous($doc);
    if ($prev) {
        $pd = is_array($prev['data']??null) ? $prev['data'] : (json_decode($prev['data']??'[]',true)?:[]);
        $pFields = idems_fields((int)$prev['report_type_id']); $pSec = idems_sections((int)$prev['report_type_id']);
        $pSc = function_exists('idems_score_doc') ? idems_score_doc($pSec,$pFields,$pd) : null;
        $pPct = ($pSc && $pSc['overall']!==null) ? (float)$pSc['overall'] : null;
        $pSt = idems_expediting_status($prev,$pFields,$pd);
        $tl=[]; $prevIRN=trim((string)($prev['irn']??'')); $prevDate=$fmtD($prev['issue_date']??($prev['created_at']??''));
        $ref = 'the last report'.($prevIRN!==''?' ('.$prevIRN.($prevDate!==''?', '.$prevDate:'').')':'');
        if ($pPct!==null && $pct!==null) {
            $d=$pct-$pPct;
            if (abs($d)<0.05) $tl[]='Progress is unchanged at '.$num($pct).'% since '.$ref.'.';
            else $tl[]='Progress moved from '.$num($pPct).'% to '.$num($pct).'% ('.($d>0?'+':'').$num($d).' pt) since '.$ref.'.';
        }
        $pExp=$pSt['forecast']['expeditor']; $cExp=$fc['expeditor'];
        if ($pExp!=='' && $cExp!=='') {
            $slip=(int)floor((strtotime($cExp)-strtotime($pExp))/86400);
            if ($slip>0) $tl[]='The expeditor delivery forecast has slipped '.$slip.' day'.($slip==1?'':'s').' (now '.$fmtD($cExp).').';
            elseif ($slip<0) $tl[]='The expeditor delivery forecast improved by '.abs($slip).' day'.(abs($slip)==1?'':'s').' (now '.$fmtD($cExp).').';
            else $tl[]='The expeditor delivery forecast is held at '.$fmtD($cExp).'.';
        }
        if (($pSt['status']??'')!=='' && $pSt['status']!==$st['status']) $tl[]='Status changed from '.ucwords(strtolower($pSt['status'])).' to '.ucwords(strtolower($st['status'])).'.';
        if ($tl) { $worse = ($pPct!==null && $pct!==null && $pct<$pPct-0.05) || (isset($slip) && $slip>0);
            $items[]=['kind'=>'trend','title'=>'Trend vs previous report','tone'=>($worse?'warn':'neutral'),'text'=>implode(' ', $tl)]; }
    }

    return ['summary'=>$summary, 'items'=>$items];
}

// §expediting-risk (Phase 6) — a DETERMINISTIC forward-looking delivery-risk read
// for one Expediting Report. It answers "how likely is this PO to miss its
// required date, and why", as a 0-100 risk score (higher = more risk), a band and
// the weighted contributing factors — plus a recovery read (how many days late,
// how much lead time is left, is a recovery action on record). No LLM: every
// point is a reproducible function of the report's own signals and the vendor's
// track record. Advisory — it never gates issue. Returns
// ['score','band','factors'=>[['label','detail','points','tone']],'recovery'=>[...]].
function idems_expediting_risk($doc, $fields = null, $data = null, $vperf = null, $today = null) {
    if ($fields === null) $fields = idems_fields((int)($doc['report_type_id'] ?? 0));
    if ($data === null) { $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = []; }
    $sections = idems_sections((int)($doc['report_type_id'] ?? 0));
    $today = $today ?: date('Y-m-d');
    $pd = function($s){ $s=trim((string)$s); if($s==='')return null; $t=strtotime($s); return $t?:null; };
    $tableRows = function($fkey) use ($fields,$data){
        foreach ($fields as $f) if (($f['fkey']??'')===$fkey && ($f['ftype']??'')==='table') {
            $rows=$data[$fkey]??null; if(!is_array($rows)) return [null,[]];
            $defs=idems_table_col_defs($f); $map=[]; foreach($defs as $ck=>$d) $map[strtolower((string)$d['label'])]=$ck;
            return [$rows,$map];
        } return [null,[]];
    };
    $col=function($map,$needles){ foreach($map as $lbl=>$ck){ foreach((array)$needles as $n) if(strpos($lbl,$n)!==false) return $ck; } return null; };

    $sc = function_exists('idems_score_doc') ? idems_score_doc($sections,$fields,$data) : null;
    $pct = ($sc && $sc['overall']!==null) ? (float)$sc['overall'] : null;
    $st = idems_expediting_status($doc,$fields,$data);
    $fc = $st['forecast']; $ms = $st['milestones'];
    $dr = function_exists('idems_expediting_dispatch_readiness') ? idems_expediting_dispatch_readiness($fields,$data) : null;

    $factors=[]; $score=0;
    $add=function($label,$detail,$points,$tone) use (&$factors,&$score){ $points=(int)round($points); if($points<=0) return; $score+=$points; $factors[]=['label'=>$label,'detail'=>$detail,'points'=>$points,'tone'=>$tone]; };

    // 1) Forecast beyond required — the strongest signal (2 pts/day, cap 30).
    $ev = $fc['expeditor_variance_days'];
    if ($ev !== null && $ev > 0) $add('Forecast beyond required', 'Expeditor forecasts delivery '.$ev.' day'.($ev==1?'':'s').' after the required date', min(30, $ev*2), 'bad');

    // 2) Milestone health — delayed (8 each, cap 24) + at-risk (4 each, cap 12).
    if ($ms['delayed']>0) $add('Delayed milestones', $ms['delayed'].' milestone'.($ms['delayed']==1?'':'s').' delayed', min(24, $ms['delayed']*8), 'bad');
    if ($ms['at_risk']>0) $add('At-risk milestones', $ms['at_risk'].' milestone'.($ms['at_risk']==1?'':'s').' at risk', min(12, $ms['at_risk']*4), 'warn');

    // 3) Open major NCRs — delivery-impacting weigh most.
    [$ncrRows,$ncrMap]=$tableRows('ncr_register');
    if (is_array($ncrRows)) {
        $sevK=$col($ncrMap,['sever']); $stK=$col($ncrMap,['status']); $impK=$col($ncrMap,['impact','delivery']);
        $maj=0;$impc=0; foreach($ncrRows as $r){ $rr=(array)$r; $sev=strtolower(trim((string)($rr[$sevK]??''))); $stt=strtolower(trim((string)($rr[$stK]??'')));
            if($stt!=='' && strpos($stt,'clos')===false && strpos($sev,'major')!==false){ $maj++; $imp=strtolower(trim((string)($rr[$impK]??''))); if(in_array($imp,['yes','y','1','true'],true))$impc++; } }
        if ($impc>0) $add('Delivery-impacting NCRs', $impc.' open major NCR'.($impc==1?'':'s').' flagged delivery-impacting', min(16,$impc*8), 'bad');
        if ($maj-$impc>0) $add('Open major NCRs', ($maj-$impc).' open major NCR'.(($maj-$impc)==1?'':'s'), min(9,($maj-$impc)*3), 'warn');
    }

    // 4) Dispatch blockers — mandatory open go/no-go items (6 each, cap 18).
    if ($dr && $dr['pct']!==null && count($dr['blockers'])>0) $add('Dispatch blockers', count($dr['blockers']).' mandatory dispatch item(s) still open', min(18,count($dr['blockers'])*6), 'bad');

    // 5) Vendor forecast optimism on THIS report (vendor earlier than expeditor).
    $veo = $fc['vendor_vs_expeditor_days'];
    if ($veo !== null && $veo > 0) $add('Vendor forecast optimism', "Vendor's date is ".$veo.' day'.($veo==1?'':'s').' earlier than the expeditor read', min(10, (int)ceil($veo/2)), 'warn');

    // 6) Time-vs-progress pressure — required date near while progress lags.
    $req = $fc['required']!=='' ? $pd($fc['required']) : null; $tnow = $pd($today);
    $daysToReq = ($req && $tnow) ? (int)floor(($req - $tnow)/86400) : null;
    if ($daysToReq !== null && $pct !== null && $daysToReq <= 30 && $daysToReq >= 0 && $pct < 80)
        $add('Schedule pressure', 'Only '.$daysToReq.' day'.($daysToReq==1?'':'s').' to the required date at '.rtrim(rtrim(number_format($pct,1),'0'),'.').'% progress', min(16, (int)round((80-$pct)/5) + (30-$daysToReq)/3), 'warn');
    elseif ($daysToReq !== null && $daysToReq < 0 && ($ms['total']===0 || $ms['complete']<$ms['total']))
        $add('Past required date', 'The required date has passed and work is not complete', 14, 'bad');

    // 7) Capacity / sub-supplier constraints.
    [$rsRows,$rsMap]=$tableRows('resources');
    if (is_array($rsRows)) { $gapK=$col($rsMap,['gap','short','deficit']);
        foreach($rsRows as $r){ $rr=(array)$r; $g=preg_replace('/[^0-9.\-]/','',(string)($rr[$gapK]??'')); if($g!=='' && (float)$g>0){ $add('Capacity constraint','A resource shortfall is recorded against capacity',6,'warn'); break; } } }
    [$ssRows,$ssMap]=$tableRows('subsuppliers');
    if (is_array($ssRows)) { $stK3=$col($ssMap,['status']); $n=0;
        foreach($ssRows as $r){ $rr=(array)$r; $s=strtolower(trim((string)($rr[$stK3]??''))); if($s!=='' && (strpos($s,'risk')!==false||strpos($s,'delay')!==false)) $n++; }
        if($n>0) $add('Sub-supplier risk', $n.' sub-supplier(s) at risk or delayed', min(10,$n*5), 'warn'); }

    // 8) Vendor track record (forward-looking) — from the vendor's expediting perf.
    if ($vperf === null && !empty($doc['vendor_id']) && function_exists('idems_vendor_expediting_perf'))
        $vperf = idems_vendor_expediting_perf((int)$doc['vendor_id']);
    if (is_array($vperf)) {
        $rel = $vperf['commitments']['reliability_pct'] ?? null;
        if ($rel !== null && $rel < 70) $add('Weak commitment track record', 'Vendor has met only '.rtrim(rtrim(number_format($rel,1),'0'),'.').'% of past commitments on time', min(12, (int)round((70-$rel)/5)), 'warn');
        $opt = $vperf['forecast']['optimism_pct'] ?? null;
        if ($opt !== null && $opt >= 50) $add('Chronically optimistic forecasts', 'Vendor forecasts ran optimistic on '.rtrim(rtrim(number_format($opt,1),'0'),'.').'% of past reports', min(8, (int)round($opt/12)), 'warn');
    }

    $score = min(100, $score);
    $band = $score>=70 ? 'CRITICAL' : ($score>=45 ? 'HIGH' : ($score>=20 ? 'MEDIUM' : 'LOW'));

    // Recovery intelligence — gap vs lead time, and whether a recovery action exists.
    $recovery = ['gap_days'=>($ev!==null&&$ev>0)?$ev:0, 'available_days'=>$daysToReq, 'feasible'=>null, 'note'=>''];
    [$dRows,$dMap]=$tableRows('delays');
    $recoveryAction=''; if (is_array($dRows)) { $actK=$col($dMap,['action','recovery','mitig']);
        foreach($dRows as $r){ $rr=(array)$r; $a=trim((string)($rr[$actK]??'')); if($a!==''){ $recoveryAction=$a; break; } } }
    if ($recovery['gap_days']>0) {
        $lead = $daysToReq; $gap=$recovery['gap_days'];
        if ($lead !== null) {
            if ($lead < 0) { $recovery['feasible']=false; $recovery['note']='The required date has already passed; the '.$gap.'-day slippage cannot be recovered — re-baseline with the client.'; }
            elseif ($lead >= $gap*2 && $recoveryAction!=='') { $recovery['feasible']=true; $recovery['note']='Delivery is forecast '.$gap.' day'.($gap==1?'':'s').' late, but ~'.$lead.' day(s) of lead time remain and a recovery action is on record ("'.$recoveryAction.'") — recovery is plausible if that action holds.'; }
            elseif ($recoveryAction!=='') { $recovery['feasible']=false; $recovery['note']='Delivery is forecast '.$gap.' day'.($gap==1?'':'s').' late with only ~'.$lead.' day(s) of lead time — recovery is tight; the action on record ("'.$recoveryAction.'") must be accelerated or the date re-baselined.'; }
            else { $recovery['feasible']=false; $recovery['note']='Delivery is forecast '.$gap.' day'.($gap==1?'':'s').' late and no recovery action is on record — raise one or re-baseline the date.'; }
        } else { $recovery['note']='Delivery is forecast '.$gap.' day'.($gap==1?'':'s').' late'.($recoveryAction!==''?' — recovery action on record: "'.$recoveryAction.'".':' and no recovery action is on record.'); }
    } else { $recovery['note']='No slippage against the required date is currently forecast.'; }

    // Sort factors by weight, heaviest first.
    usort($factors, fn($a,$b)=>$b['points']<=>$a['points']);
    return ['score'=>$score, 'band'=>$band, 'factors'=>$factors, 'recovery'=>$recovery];
}

// Vendor-level delivery risk (Phase 6) — aggregates the predictive risk across a
// vendor's OPEN expediting reports (latest per PO, excluding completed) and joins
// it to the commitment/forecast track record, for Vendor-360. Returns
// ['band','score','open_pos','worst'=>['irn','po','score','band'],'perf'=>...] or null.
function idems_vendor_delivery_risk($partnerId) {
    $partnerId=(int)$partnerId; if(!$partnerId) return null;
    try { $docs = ops_all("SELECT * FROM report_docs WHERE vendor_id=? AND deleted=0 AND type_code='ER' ORDER BY COALESCE(issue_date,created_at) DESC, id DESC LIMIT 60", [$partnerId]); }
    catch (Throwable $e) { return null; }
    if (!$docs) return null;
    $perf = function_exists('idems_vendor_expediting_perf') ? idems_vendor_expediting_perf($partnerId) : null;
    // Latest report per PO (data.po_number), skipping completed ones.
    $latest=[];
    foreach ($docs as $d) {
        $data=json_decode($d['data']??'[]',true); if(!is_array($data)) $data=[];
        $po=strtolower(trim((string)($data['po_number']??''))) ?: ('doc'.$d['id']);
        if (!isset($latest[$po])) $latest[$po]=['doc'=>$d,'data'=>$data]; // first seen = most recent (ordered desc)
    }
    $scores=[]; $worst=null; $open=0;
    foreach ($latest as $po=>$e) {
        $fields=idems_fields((int)$e['doc']['report_type_id']);
        $stt=idems_expediting_status($e['doc'],$fields,$e['data']);
        if (($stt['status']??'')==='COMPLETED') continue;
        $open++;
        $r=idems_expediting_risk($e['doc'],$fields,$e['data'],$perf);
        $scores[]=$r['score'];
        if ($worst===null || $r['score']>$worst['score']) $worst=['irn'=>$e['doc']['irn']??'','po'=>trim((string)($e['data']['po_number']??'')),'score'=>$r['score'],'band'=>$r['band'],'id'=>(int)$e['doc']['id']];
    }
    if (!$scores) return ['band'=>'LOW','score'=>0,'open_pos'=>0,'worst'=>null,'perf'=>$perf];
    // Vendor risk = the worst open PO weighted with the average (a single bad PO
    // matters, but breadth of risk matters too).
    $avg=array_sum($scores)/count($scores); $mx=max($scores);
    $score=(int)round($mx*0.6 + $avg*0.4);
    $band=$score>=70?'CRITICAL':($score>=45?'HIGH':($score>=20?'MEDIUM':'LOW'));
    return ['band'=>$band,'score'=>$score,'open_pos'=>$open,'worst'=>$worst,'perf'=>$perf];
}

// Severity rank for an expediting status (higher = worse) — so a project rolls up
// to its worst-off PO. COMPLETED is best; CRITICAL is worst.
function idems_expediting_status_rank($st) {
    switch (strtoupper(trim((string)$st))) {
        case 'CRITICAL': return 5; case 'DELAYED': return 4; case 'AT RISK': return 3;
        case 'ON TRACK': return 2; case 'COMPLETED': return 1; default: return 0;
    }
}

// §expediting-project-tree (Phase 4b) — consolidate many PO-level Expediting
// Reports into ONE project delivery view. Given a list of ER report_docs rows,
// group them by project, keep the LATEST report per PO/package, and roll up:
// progress (average), worst status, aggregate delivery risk (worst+average),
// the binding delivery date (the latest-finishing PO — a project is delivered
// only when its last PO is) and how many POs are forecast late. Deterministic;
// the risk reuses idems_expediting_risk. Returns a list of project nodes, each
// with its PO children, sorted worst-first (status, then risk). §rollup
function idems_expediting_projects($docs, $today = null) {
    $today = $today ?: date('Y-m-d');
    $pd = function($s){ $s=trim((string)$s); if($s==='')return null; $t=strtotime($s); return $t?:null; };
    $vperfCache = [];
    // Group docs by project, then keep the most-recent report per PO within each.
    $projects = [];
    foreach ($docs as $d) {
        $data = json_decode($d['data'] ?? '[]', true); if (!is_array($data)) $data = [];
        $proj = trim((string)($d['project_name'] ?? '')) ?: trim((string)($data['project'] ?? '')) ?: '(No project set)';
        $pkey = strtolower($proj);
        if (!isset($projects[$pkey])) $projects[$pkey] = ['project'=>$proj, 'code'=>trim((string)($d['project_code'] ?? '')), 'client'=>trim((string)($data['client'] ?? ($d['client_name'] ?? ''))), 'pos'=>[]];
        elseif ($projects[$pkey]['client']==='' ) $projects[$pkey]['client'] = trim((string)($data['client'] ?? ''));
        $po = strtolower(trim((string)($data['po_number'] ?? ''))) ?: ('doc'.$d['id']);
        // Docs arrive newest-first (caller orders DESC); first seen per PO wins.
        if (!isset($projects[$pkey]['pos'][$po])) $projects[$pkey]['pos'][$po] = ['doc'=>$d, 'data'=>$data];
    }
    $out = [];
    foreach ($projects as $pk => $proj) {
        $children = []; $scores=[]; $progs=[]; $worstRank=0; $worstStatus=''; $open=0; $late=0;
        $bindReq=null; $bindFc=null; $bindPo=null; $worstVar=null;
        foreach ($proj['pos'] as $po => $e) {
            $d = $e['doc']; $data = $e['data'];
            $fields = idems_fields((int)$d['report_type_id']);
            $es = idems_expediting_status($d, $fields, $data);
            $sc = function_exists('idems_score_doc') ? idems_score_doc(idems_sections((int)$d['report_type_id']), $fields, $data) : null;
            $vid = (int)($d['vendor_id'] ?? 0);
            if ($vid && !array_key_exists($vid,$vperfCache)) $vperfCache[$vid] = function_exists('idems_vendor_expediting_perf') ? idems_vendor_expediting_perf($vid) : null;
            $risk = function_exists('idems_expediting_risk') ? idems_expediting_risk($d, $fields, $data, $vid?$vperfCache[$vid]:null, $today) : null;
            $st = $es['status']; $ev = $es['forecast']['expeditor_variance_days'];
            $isOpen = ($st !== 'COMPLETED');
            if ($isOpen) { $open++; if ($risk) $scores[] = $risk['score']; }
            if ($sc && $sc['overall']!==null) $progs[] = (float)$sc['overall'];
            $r = idems_expediting_status_rank($st); if ($r > $worstRank) { $worstRank=$r; $worstStatus=$st; }
            if ($ev !== null && $ev > 0) { $late++; if ($worstVar===null || $ev>$worstVar) $worstVar=$ev; }
            // Binding delivery = the latest expeditor forecast among open POs.
            $fcT = $pd($es['forecast']['expeditor']); $reqT = $pd($es['forecast']['required']);
            if ($isOpen && $fcT && ($bindFc===null || $fcT > $bindFc)) { $bindFc=$fcT; $bindReq=$reqT; $bindPo=$po; }
            $children[] = ['po'=>trim((string)($data['po_number'] ?? '')), 'package'=>trim((string)($data['package'] ?? '')),
                'irn'=>$d['irn'], 'id'=>(int)$d['id'], 'vendor'=>trim((string)($data['vendor'] ?? ($d['vendor_name'] ?? ''))),
                'progress'=>($sc['overall'] ?? null), 'status'=>$st,
                'risk'=>($risk?$risk['band']:null), 'risk_score'=>($risk?(int)$risk['score']:null),
                'required'=>$es['forecast']['required'], 'forecast'=>$es['forecast']['expeditor'], 'variance'=>$ev,
                'date'=>($d['issue_date'] ?: ''), 'open'=>$isOpen];
        }
        // Sort PO children worst-first (status severity, then risk score).
        usort($children, fn($a,$b)=> (idems_expediting_status_rank($b['status'])<=>idems_expediting_status_rank($a['status'])) ?: (($b['risk_score']??0)<=>($a['risk_score']??0)));
        $riskScore = $scores ? (int)round(max($scores)*0.6 + (array_sum($scores)/count($scores))*0.4) : 0;
        $riskBand = $riskScore>=70?'CRITICAL':($riskScore>=45?'HIGH':($riskScore>=20?'MEDIUM':'LOW'));
        $out[] = [
            'project'=>$proj['project'], 'code'=>$proj['code'], 'client'=>$proj['client'],
            'total_pos'=>count($children), 'open_pos'=>$open, 'late_pos'=>$late,
            'progress'=>$progs ? round(array_sum($progs)/count($progs),1) : null,
            'status'=>$worstStatus ?: 'ON TRACK', 'risk'=>['band'=>$riskBand,'score'=>$riskScore],
            'required'=>$bindReq?date('Y-m-d',$bindReq):'', 'forecast'=>$bindFc?date('Y-m-d',$bindFc):'',
            'variance'=>($bindReq&&$bindFc)?(int)floor(($bindFc-$bindReq)/86400):null,
            'worst_variance'=>$worstVar, 'binding_po'=>($bindPo!==null?trim((string)($proj['pos'][$bindPo]['data']['po_number']??'')):''),
            'pos'=>$children,
        ];
    }
    // Projects sorted worst-first: status severity, then risk, then late count.
    usort($out, fn($a,$b)=> (idems_expediting_status_rank($b['status'])<=>idems_expediting_status_rank($a['status'])) ?: (($b['risk']['score']<=>$a['risk']['score']) ?: ($b['late_pos']<=>$a['late_pos'])));
    return $out;
}

// Keep only the RELEASED line items for a Release Note — rows that have a
// passed / cleared / released / accepted quantity greater than zero. Rows that
// were fully rejected or held are dropped. If no such column can be identified,
// or if the filter would leave nothing, the full list is kept (never hide data
// silently). §releasenote
function idems_released_line_items($rows) {
    if (!is_array($rows) || !$rows) return $rows;
    $first = (array)($rows[0] ?? []); $passKey = null;
    foreach ($first as $k => $_) { $lk = strtolower((string)$k); if (strpos($lk,'passed')!==false || strpos($lk,'cleared')!==false || strpos($lk,'released')!==false || strpos($lk,'accepted')!==false) { $passKey = $k; break; } }
    if ($passKey === null) return $rows;
    $out = array_values(array_filter($rows, function($r) use ($passKey) {
        $rr = (array)$r; $v = preg_replace('/[^0-9.\-]/', '', (string)($rr[$passKey] ?? ''));
        return $v !== '' && (float)$v > 0;
    }));
    return $out ?: $rows;
}
// ===========================================================================
//  Scoring engine — weighted per-field scores rolled up by section & overall
//  Any report type can carry scores: give a field a weight (>0) and a scale.
//  Fields with weight 0 never contribute, so ordinary reports are untouched.
//  Used by Vendor Assessment / Audit / scored-checklist report types.
// ===========================================================================

// Normalise one field's answer to 0-100 on its own scale, or null when the
// field is unanswered / not a scored input. Scales, in priority order:
//   score_map  JSON {answer: points} — points 0-100 (best for select/radio/yesno)
//   max_score  a numeric ceiling — the raw number is scaled value/max*100
//   ftype defaults — yesno/checkbox => 100 for yes/checked else 0; rating (1-5) => /5
function idems_field_score($f, $v) {
    $ft = $f['ftype'] ?? 'text';
    // A score_map maps the stored answer to points, case-insensitively.
    $mapRaw = trim((string)($f['score_map'] ?? ''));
    if ($mapRaw !== '') {
        $map = json_decode($mapRaw, true);
        if (is_array($map)) {
            $sv = is_array($v) ? implode(',', $v) : (string)$v;
            if (trim($sv) === '') return null;
            $lc = strtolower(trim($sv));
            foreach ($map as $key => $pts) {
                if (strtolower(trim((string)$key)) === $lc) { $p = (float)$pts; return max(0.0, min(100.0, $p)); }
            }
            return null; // answered but not a recognised option — don't guess
        }
    }
    $max = (float)($f['max_score'] ?? 0);
    if ($max > 0) {
        if (is_array($v) || trim((string)$v) === '') return null;
        $n = preg_replace('/[^0-9.\-]/', '', (string)$v);
        if ($n === '' || $n === '-') return null;
        return max(0.0, min(100.0, ((float)$n / $max) * 100));
    }
    if ($ft === 'yesno' || $ft === 'checkbox') {
        $sv = strtolower(trim(is_array($v) ? implode(',', $v) : (string)$v));
        if ($sv === '') return null;
        return in_array($sv, ['yes','y','1','true','ok','pass','conform','on','checked'], true) ? 100.0 : 0.0;
    }
    if ($ft === 'rating') {
        if (trim((string)$v) === '') return null;
        $n = (float)preg_replace('/[^0-9.\-]/', '', (string)$v);
        return $n <= 0 ? null : max(0.0, min(100.0, ($n / 5.0) * 100));
    }
    return null; // not a scored field
}

// Roll every weighted field up into per-section scores and one overall score.
// Returns null when the report carries no weighted fields at all (so callers
// can simply skip the scorecard for ordinary report types). Otherwise:
//   ['overall'=>0-100, 'answered'=>n, 'total'=>n, 'band'=>label,
//    'sections'=>[ ['id','title','score','weight','answered','total','fields'=>[...] ] ] ]
// A section's score is the weighted average of its answered fields; the overall
// score is the section scores weighted by each section's summed field weight.
function idems_score_doc($sections, $fields, $data) {
    $byId = []; $anyWeight = false;
    foreach ($fields as $f) {
        if ((float)($f['weight'] ?? 0) > 0) $anyWeight = true;
        $sid = (int)($f['section_id'] ?? 0);
        $byId[$sid][] = $f;
    }
    if (!$anyWeight) return null;
    $secOut = []; $ovNum = 0.0; $ovDen = 0.0; $ansTot = 0; $fldTot = 0;
    // Preserve section order; include an "id 0" bucket for section-less fields.
    $order = [];
    foreach ($sections as $s) $order[] = ['id' => (int)$s['id'], 'title' => (string)($s['title'] ?? '')];
    if (isset($byId[0])) $order[] = ['id' => 0, 'title' => 'Other'];
    foreach ($order as $so) {
        $sid = $so['id']; $fs = $byId[$sid] ?? []; if (!$fs) continue;
        $num = 0.0; $den = 0.0; $ans = 0; $cnt = 0; $frows = [];
        foreach ($fs as $f) {
            $w = (float)($f['weight'] ?? 0); if ($w <= 0) continue;
            $cnt++; $fldTot++;
            $sc = idems_field_score($f, $data[$f['fkey'] ?? ''] ?? null);
            if ($sc === null) { $frows[] = ['label' => $f['label'] ?? $f['fkey'], 'weight' => $w, 'score' => null]; continue; }
            $ans++; $ansTot++; $num += $sc * $w; $den += $w;
            $frows[] = ['label' => $f['label'] ?? $f['fkey'], 'weight' => $w, 'score' => round($sc, 1)];
        }
        if ($cnt === 0) continue;
        $secScore = $den > 0 ? $num / $den : null;
        $secWeight = 0.0; foreach ($fs as $f) $secWeight += (float)($f['weight'] ?? 0);
        if ($secScore !== null) { $ovNum += $secScore * $secWeight; $ovDen += $secWeight; }
        $secOut[] = ['id' => $sid, 'title' => $so['title'] ?: 'Section', 'score' => $secScore === null ? null : round($secScore, 1),
                     'weight' => $secWeight, 'answered' => $ans, 'total' => $cnt, 'fields' => $frows];
    }
    $overall = $ovDen > 0 ? round($ovNum / $ovDen, 1) : null;
    return ['overall' => $overall, 'answered' => $ansTot, 'total' => $fldTot,
            'band' => $overall === null ? '' : idems_score_band($overall), 'sections' => $secOut];
}

// Parse the builder's friendly score-map input into the stored JSON string.
// Accepts either raw JSON ({"Good":75}) or one "Option = points" per line
// (also tolerates "Option: points" and "Option, points"). Blank => '' (no map).
function idems_score_map_to_json($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    // Already JSON? keep it if it decodes to an object of numbers.
    if ($text[0] === '{') {
        $j = json_decode($text, true);
        if (is_array($j)) { $out = []; foreach ($j as $k => $v) { $k = trim((string)$k); if ($k !== '') $out[$k] = max(0.0, min(100.0, (float)$v)); } return $out ? json_encode($out) : ''; }
        return '';
    }
    $out = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = trim($line); if ($line === '') continue;
        if (!preg_match('/^(.*?)\s*[=:,]\s*(-?\d+(?:\.\d+)?)\s*$/', $line, $m)) continue;
        $k = trim($m[1]); if ($k === '') continue;
        $out[$k] = max(0.0, min(100.0, (float)$m[2]));
    }
    return $out ? json_encode($out) : '';
}
// Render a stored score-map JSON back as friendly "Option = points" lines for
// the builder's edit form.
function idems_score_map_to_text($json) {
    $json = trim((string)$json); if ($json === '') return '';
    $j = json_decode($json, true); if (!is_array($j)) return '';
    $lines = []; foreach ($j as $k => $v) $lines[] = $k . ' = ' . rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    return implode("\n", $lines);
}
// A plain-English band for a 0-100 score. Kept simple and stable so a buyer can
// relabel via a setting later without touching the maths.
function idems_score_band($score) {
    $score = (float)$score;
    if ($score >= 90) return 'Excellent';
    if ($score >= 75) return 'Approved';
    if ($score >= 60) return 'Conditional';
    if ($score >= 40) return 'Marginal';
    return 'Not approved';
}
// ===========================================================================
//  Vendor qualification profile — one row per vendor partner, kept current by
//  assessments. Read/created on demand; updated when a Vendor Assessment is
//  issued so the approved-vendor register reflects the latest score & status.
// ===========================================================================

// Fetch the vendor profile for a partner (or null). Never creates a row.
function idems_vendor_profile($partnerId) {
    $partnerId = (int)$partnerId; if (!$partnerId) return null;
    return ops_one("SELECT * FROM vendor_profiles WHERE partner_id=?", [$partnerId]) ?: null;
}
// Get-or-create the vendor profile row for a partner, returning its id.
function idems_vendor_profile_ensure($partnerId) {
    $partnerId = (int)$partnerId; if (!$partnerId) return 0;
    $row = idems_vendor_profile($partnerId);
    if ($row) return (int)$row['id'];
    db()->prepare("INSERT INTO vendor_profiles (partner_id, approval_status, updated_at) VALUES (?, 'PROSPECT', ?)")
        ->execute([$partnerId, date('c')]);
    return (int)db()->lastInsertId();
}
// Save the editable master attributes on a vendor profile (type, category, risk).
function idems_vendor_profile_save($partnerId, $attrs) {
    $partnerId = (int)$partnerId; if (!$partnerId) return;
    idems_vendor_profile_ensure($partnerId);
    $allowed = ['vendor_type','product_category','risk_class','approval_status','notes'];
    $sets = []; $args = [];
    foreach ($allowed as $k) { if (array_key_exists($k, $attrs)) { $sets[] = "$k=?"; $args[] = trim((string)$attrs[$k]); } }
    if (!$sets) return;
    $sets[] = 'updated_at=?'; $args[] = date('c');
    $sets[] = 'updated_by=?'; $args[] = (string)(current_user()['username'] ?? '');
    $args[] = $partnerId;
    db()->prepare("UPDATE vendor_profiles SET " . implode(', ', $sets) . " WHERE partner_id=?")->execute($args);
}
// Default re-qualification period, in months (editable via a setting).
function idems_vendor_requal_months() {
    $m = function_exists('setting_get') ? (int)setting_get('vendor_requal_months', '') : 0;
    return $m > 0 ? $m : 12;
}
// Map an assessment's recommendation + score to a vendor approval status.
// The recommendation (a human decision) leads; the score is the fallback.
function idems_vendor_status_from($recommendation, $score) {
    $r = strtolower(trim((string)$recommendation));
    if ($r !== '') {
        if (strpos($r, 'not approved') !== false) return 'SUSPENDED';
        if (strpos($r, 'condition') !== false)   return 'CONDITIONAL';
        if (strpos($r, 'approv') !== false)       return 'APPROVED';
    }
    if ($score === null) return 'UNDER_ASSESSMENT';
    $s = (float)$score;
    if ($s >= 75) return 'APPROVED';
    if ($s >= 60) return 'CONDITIONAL';
    return 'SUSPENDED';
}
// Apply an issued Vendor Assessment to the vendor's profile: record the score,
// band, approval status and the validity / re-assessment dates. Fail-open —
// never blocks issuing a report. Returns the new status, or '' if not applied.
function idems_vendor_apply_assessment($doc, $score = null) {
    try {
        $partnerId = (int)($doc['vendor_id'] ?? 0); if (!$partnerId) return '';
        $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = [];
        if ($score === null && function_exists('idems_score_doc')) {
            $secs = idems_sections((int)($doc['report_type_id'] ?? 0));
            $flds = idems_fields((int)($doc['report_type_id'] ?? 0));
            $score = idems_score_doc($secs, $flds, $data);
        }
        $overall = is_array($score) ? ($score['overall'] ?? null) : $score;
        $band    = is_array($score) ? (string)($score['band'] ?? '') : (($overall !== null) ? idems_score_band($overall) : '');
        $rec = (string)($data['recommendation'] ?? '');
        $status = idems_vendor_status_from($rec, $overall);
        $prev = idems_vendor_profile($partnerId);
        $oldStatus = $prev['approval_status'] ?? 'PROSPECT';
        idems_vendor_profile_ensure($partnerId);
        $today = date('Y-m-d');
        $months = idems_vendor_requal_months();
        $reassess = date('Y-m-d', strtotime("+$months months"));
        $approvedOn = in_array($status, ['APPROVED','CONDITIONAL'], true) ? $today : '';
        $validUntil = in_array($status, ['APPROVED','CONDITIONAL'], true) ? $reassess : '';
        db()->prepare("UPDATE vendor_profiles SET vendor_rating=?, last_score=?, last_band=?, last_assessment_id=?, last_assessed_on=?, approval_status=?, approved_on=CASE WHEN ?='' THEN approved_on ELSE ? END, valid_until=?, reassess_on=?, updated_at=?, updated_by=? WHERE partner_id=?")
            ->execute([
                $overall !== null ? (float)$overall : 0, $overall !== null ? (float)$overall : null, $band,
                (int)($doc['id'] ?? 0), $today, $status,
                $approvedOn, $approvedOn, $validUntil, $reassess, date('c'),
                (string)(current_user()['username'] ?? ''), $partnerId,
            ]);
        $src = ($doc['type_code'] ?? '') === 'VAR' ? 'AUDIT' : 'ASSESSMENT';
        idems_vendor_log_status($partnerId, $oldStatus, $status, $src, [
            'report_doc_id' => (int)($doc['id'] ?? 0) ?: null, 'score' => $overall,
            'reason' => trim(($rec !== '' ? $rec : 'Assessment issued') . ($doc['irn'] ?? '' ? ' — ' . $doc['irn'] : '')),
        ]);
        return $status;
    } catch (Throwable $e) { return ''; }
}

// Record a vendor status change on the timeline. Logs even when the status is
// unchanged only if a reason is supplied (e.g. a manual note); assessments that
// keep the same status still leave a dated trace.
function idems_vendor_log_status($partnerId, $oldStatus, $newStatus, $source = 'MANUAL', $opts = []) {
    $partnerId = (int)$partnerId; if (!$partnerId) return;
    try {
        db()->prepare("INSERT INTO vendor_status_events (partner_id, old_status, new_status, source, report_doc_id, score, reason, actor, at) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$partnerId, (string)$oldStatus, (string)$newStatus, (string)$source,
                isset($opts['report_doc_id']) ? (int)$opts['report_doc_id'] ?: null : null,
                isset($opts['score']) && $opts['score'] !== null ? (float)$opts['score'] : null,
                substr((string)($opts['reason'] ?? ''), 0, 500),
                (string)(current_user()['username'] ?? 'system'), date('c')]);
    } catch (Throwable $e) {}
}
// The vendor's status timeline (newest first).
function idems_vendor_status_events($partnerId) {
    return ops_all("SELECT * FROM vendor_status_events WHERE partner_id=? ORDER BY id DESC", [(int)$partnerId]);
}
// How many days before re-assessment is due to start reminding (setting).
function idems_vendor_reminder_days() {
    $d = function_exists('setting_get') ? (int)setting_get('vendor_reminder_days', '') : 0;
    return $d > 0 ? $d : 30;
}
// Daily maintenance for vendor qualification:
//   1. Expire — an APPROVED/CONDITIONAL vendor whose validity has passed is moved
//      to EXPIRED (logged), so a lapsed approval never silently reads as current.
//   2. Remind — a vendor whose re-assessment falls due within the reminder window
//      gets a notification to the configured address (vendor_reminder_email).
// Returns ['expired'=>n, 'reminded'=>m]. Safe to run daily from cron.
function idems_vendor_run_reminders($today = null) {
    $today = $today ?: date('Y-m-d');
    $expired = 0; $reminded = 0;
    try {
        // 1) Expire lapsed approvals.
        $lapsed = ops_all("SELECT * FROM vendor_profiles WHERE approval_status IN ('APPROVED','CONDITIONAL') AND valid_until <> '' AND valid_until < ?", [$today]);
        foreach ($lapsed as $vp) {
            db()->prepare("UPDATE vendor_profiles SET approval_status='EXPIRED', updated_at=?, updated_by='system' WHERE partner_id=?")
                ->execute([date('c'), (int)$vp['partner_id']]);
            idems_vendor_log_status((int)$vp['partner_id'], $vp['approval_status'], 'EXPIRED', 'EXPIRY', [
                'reason' => 'Approval validity lapsed on ' . $vp['valid_until'] . ' — re-assessment required.']);
            $expired++;
        }
        // 2) Remind on approaching re-assessment.
        $to = function_exists('setting_get') ? trim((string)setting_get('vendor_reminder_email', '')) : '';
        if ($to !== '' && function_exists('ops_mail')) {
            $win = date('Y-m-d', strtotime($today . ' +' . idems_vendor_reminder_days() . ' days'));
            $due = ops_all("SELECT vp.*, bp.display_name, bp.legal_name FROM vendor_profiles vp
                JOIN business_partners bp ON bp.id=vp.partner_id
                WHERE vp.approval_status IN ('APPROVED','CONDITIONAL') AND vp.reassess_on <> '' AND vp.reassess_on >= ? AND vp.reassess_on <= ?", [$today, $win]);
            foreach ($due as $vp) {
                $nm = $vp['display_name'] ?: $vp['legal_name'];
                ops_mail($to, "Vendor re-assessment due: $nm ({$vp['reassess_on']})",
                    "$nm is due for re-assessment on {$vp['reassess_on']}.\n\n"
                    . "Current status: " . (lk_options_or('vendor_approval_status', [])[$vp['approval_status']] ?? $vp['approval_status'])
                    . "\nLast score: " . ($vp['last_score'] !== null ? rtrim(rtrim(number_format((float)$vp['last_score'],1),'0'),'.') . '/100' : '—')
                    . "\n\nRaise a Vendor Assessment to re-qualify this vendor.");
                $reminded++;
            }
        }
    } catch (Throwable $e) {}
    return ['expired' => $expired, 'reminded' => $reminded];
}

// ===========================================================================
//  Vendor 360 — performance intelligence assembled from live operational data:
//  inspection results, nonconformities and complaints raised against the vendor,
//  plus the latest assessment. Produces one explainable performance score.
// ===========================================================================

// A live, deterministic performance score (0-100) for a vendor, with the
// breakdown that produced it. Returns null only when there is nothing to score.
function idems_vendor_performance($partnerId) {
    $partnerId = (int)$partnerId; if (!$partnerId) return null;
    $val = function($sql, $args = []) { try { return (int)ops_val($sql, $args); } catch (Throwable $e) { return 0; } };
    $today = date('Y-m-d');

    // 1) Inspection results against this vendor (issued reports only).
    $graded   = $val("SELECT COUNT(*) FROM report_docs WHERE vendor_id=? AND deleted=0 AND finalized=1 AND result IN ('ACCEPTED','ACCEPTED_COND','REJECTED')", [$partnerId]);
    $accepted = $val("SELECT COUNT(*) FROM report_docs WHERE vendor_id=? AND deleted=0 AND finalized=1 AND result IN ('ACCEPTED','ACCEPTED_COND')", [$partnerId]);
    $rejected = $val("SELECT COUNT(*) FROM report_docs WHERE vendor_id=? AND deleted=0 AND finalized=1 AND result='REJECTED'", [$partnerId]);
    $reportsTotal = $val("SELECT COUNT(*) FROM report_docs WHERE vendor_id=? AND deleted=0 AND finalized=1", [$partnerId]);
    $acceptRate = $graded > 0 ? ($accepted / $graded) * 100 : null;

    // 2) Nonconformities.
    $ncrTotal   = $val("SELECT COUNT(*) FROM nonconformities WHERE partner_id=?", [$partnerId]);
    $ncrOpen    = $val("SELECT COUNT(*) FROM nonconformities WHERE partner_id=? AND status <> 'CLOSED'", [$partnerId]);
    $ncrOverdue = $val("SELECT COUNT(*) FROM nonconformities WHERE partner_id=? AND status <> 'CLOSED' AND due_on <> '' AND due_on < ?", [$partnerId, $today]);
    $ncrMajor   = $val("SELECT COUNT(*) FROM nonconformities WHERE partner_id=? AND status <> 'CLOSED' AND severity='MAJOR'", [$partnerId]);

    // 3) Complaints.
    $cmpTotal = $val("SELECT COUNT(*) FROM complaints WHERE partner_id=?", [$partnerId]);
    $cmpOpen  = $val("SELECT COUNT(*) FROM complaints WHERE partner_id=? AND status <> 'CLOSED'", [$partnerId]);

    // 4) Latest assessment.
    $vp = idems_vendor_profile($partnerId);
    $assessScore = ($vp && $vp['last_score'] !== null && $vp['last_score'] !== '') ? (float)$vp['last_score'] : null;

    // Nothing to score at all.
    if ($acceptRate === null && $assessScore === null && $ncrTotal === 0 && $cmpTotal === 0) return null;

    // Base: blend acceptance rate and assessment score (whichever exist).
    $parts = []; if ($acceptRate !== null) $parts[] = $acceptRate; if ($assessScore !== null) $parts[] = $assessScore;
    $base = $parts ? array_sum($parts) / count($parts) : 100.0;

    // Penalties for live quality signals (open items only; capped so one bad month
    // can't zero a strong vendor).
    $pen = [];
    if ($ncrMajor)   $pen['Open major NCRs']   = min(24, $ncrMajor * 8);
    $minorOpen = max(0, $ncrOpen - $ncrMajor);
    if ($minorOpen)  $pen['Open minor NCRs']   = min(12, $minorOpen * 3);
    if ($ncrOverdue) $pen['Overdue NCRs']      = min(15, $ncrOverdue * 5);
    if ($cmpOpen)    $pen['Open complaints']   = min(16, $cmpOpen * 4);
    $penalty = min(45, array_sum($pen));
    $score = max(0.0, min(100.0, $base - $penalty));

    return [
        'score' => round($score, 1), 'band' => idems_score_band($score), 'base' => round($base, 1), 'penalty' => round($penalty, 1),
        'penalties' => $pen,
        'reports' => ['total' => $reportsTotal, 'graded' => $graded, 'accepted' => $accepted, 'rejected' => $rejected,
                      'acceptance_rate' => $acceptRate === null ? null : round($acceptRate, 1)],
        'ncr' => ['total' => $ncrTotal, 'open' => $ncrOpen, 'overdue' => $ncrOverdue, 'major_open' => $ncrMajor],
        'complaints' => ['total' => $cmpTotal, 'open' => $cmpOpen],
        'assessment' => ['score' => $assessScore, 'band' => $vp['last_band'] ?? '', 'on' => $vp['last_assessed_on'] ?? ''],
    ];
}

// Build "Customer complaints & resolution" table rows from the vendor's actual
// complaint register, mapped to the given complaints_review table field. Used to
// prefill a Vendor Assessment / Audit so complaints & their resolution are part
// of the evaluation rather than retyped. Returns [] if there are none.
function idems_vendor_prefill_complaints($partnerId, $field) {
    $partnerId = (int)$partnerId; if (!$partnerId || !$field) return [];
    try { $cs = ops_all("SELECT ref, subject, received_on, root_cause, decision_note, outcome, capa_ref, status FROM complaints WHERE partner_id=? ORDER BY id DESC LIMIT 30", [$partnerId]); }
    catch (Throwable $e) { return []; }
    if (!$cs) return [];
    $keys = array_keys(idems_table_col_defs($field));
    $rows = [];
    foreach ($cs as $c) {
        $refDate = trim((string)($c['ref'] ?? '') . (($c['received_on'] ?? '') !== '' ? ' · ' . $c['received_on'] : ''));
        $reso = trim((string)($c['decision_note'] ?? ''));
        if ($reso === '') { $o = strtoupper((string)($c['outcome'] ?? '')); if ($o !== '' && $o !== 'PENDING') $reso = ucfirst(strtolower($o)); }
        if (trim((string)($c['capa_ref'] ?? '')) !== '') $reso = trim($reso . ' (CAPA ' . $c['capa_ref'] . ')');
        $closed = strtoupper((string)($c['status'] ?? '')) === 'CLOSED';
        $vals = [$refDate, (string)($c['subject'] ?? ''), (string)($c['root_cause'] ?? ''), $reso,
                 $closed ? 'Closed' : 'Open', $closed ? ($reso !== '' ? 'Yes' : 'No') : 'N/A'];
        $r = []; foreach ($keys as $i => $k) $r[$k] = $vals[$i] ?? '';
        $rows[] = $r;
    }
    return $rows;
}
// Build "Inspection & test status" rows from the inspection reports raised
// against this vendor, mapped to the given table field. Used to prefill an
// Expediting Report so inspection status is reflected, never retyped.
function idems_vendor_prefill_inspections($partnerId, $field) {
    $partnerId = (int)$partnerId; if (!$partnerId || !$field) return [];
    try { $rs = ops_all("SELECT irn, type_code, issue_date, result, status, release_status FROM report_docs
        WHERE vendor_id=? AND deleted=0 AND type_code NOT IN ('ER','VASR','VAR','RN') ORDER BY id DESC LIMIT 20", [$partnerId]); }
    catch (Throwable $e) { return []; }
    if (!$rs) return [];
    $keys = array_keys(idems_table_col_defs($field));
    $resMap = ['ACCEPTED'=>'Accepted','ACCEPTED_COND'=>'Accepted (obs.)','REJECTED'=>'Rejected','HOLD'=>'Hold','NA'=>'Pending'];
    $rows = [];
    foreach ($rs as $r) {
        $rel = trim((string)($r['release_status'] ?? '')); $rel = $rel !== '' ? (lk_options_or('release_status', defined('IDEMS_RELEASE')?IDEMS_RELEASE:[])[$rel] ?? $rel) : '';
        $vals = [(string)$r['irn'], (string)$r['type_code'], (string)($r['issue_date'] ?? ''),
                 $resMap[strtoupper((string)($r['result'] ?? ''))] ?? '', (string)($r['status'] ?? ''), $rel];
        $row = []; foreach ($keys as $i => $k) $row[$k] = $vals[$i] ?? ''; $rows[] = $row;
    }
    return $rows;
}
// Build "Nonconformities" rows from the NCR register for this vendor, mapped to
// the given table field. Delivery impact defaults to Yes for open majors.
function idems_vendor_prefill_ncrs($partnerId, $field) {
    $partnerId = (int)$partnerId; if (!$partnerId || !$field) return [];
    try { $ns = ops_all("SELECT ref, title, severity, status, due_on FROM nonconformities WHERE partner_id=? ORDER BY id DESC LIMIT 20", [$partnerId]); }
    catch (Throwable $e) { return []; }
    if (!$ns) return [];
    $keys = array_keys(idems_table_col_defs($field));
    $sevMap = ['MAJOR'=>'Major','MINOR'=>'Minor','OBSERVATION'=>'Observation'];
    $rows = [];
    foreach ($ns as $n) {
        $open = strtoupper((string)($n['status'] ?? '')) !== 'CLOSED';
        $major = strtoupper((string)($n['severity'] ?? '')) === 'MAJOR';
        $impact = ($open && $major) ? 'Yes' : 'Unknown';
        $vals = [(string)$n['ref'], (string)($n['title'] ?? ''), $sevMap[strtoupper((string)($n['severity'] ?? ''))] ?? '',
                 (string)($n['status'] ?? ''), (string)($n['due_on'] ?? ''), $impact];
        $row = []; foreach ($keys as $i => $k) $row[$k] = $vals[$i] ?? ''; $rows[] = $row;
    }
    return $rows;
}
// The recent operational records behind the Vendor 360 view (each list capped).
function idems_vendor_360($partnerId) {
    $partnerId = (int)$partnerId; if (!$partnerId) return ['reports'=>[], 'ncrs'=>[], 'complaints'=>[]];
    $rows = function($sql, $args = []) { try { return ops_all($sql, $args); } catch (Throwable $e) { return []; } };
    return [
        'reports' => $rows("SELECT id, irn, type_code, result, status, issue_date FROM report_docs
                            WHERE vendor_id=? AND deleted=0 ORDER BY id DESC LIMIT 8", [$partnerId]),
        'ncrs' => $rows("SELECT id, ref, title, severity, status, due_on FROM nonconformities
                         WHERE partner_id=? ORDER BY id DESC LIMIT 8", [$partnerId]),
        'complaints' => $rows("SELECT id, ref, kind, subject, status, received_on FROM complaints
                               WHERE partner_id=? ORDER BY id DESC LIMIT 6", [$partnerId]),
    ];
}

// ===========================================================================
//  Vendor expediting performance — commitment reliability & forecast reliability
//  computed across all Expediting Reports raised against a vendor. Feeds the
//  Vendor-360, so schedule behaviour joins quality in the vendor picture.
// ===========================================================================
function idems_vendor_expediting_perf($partnerId) {
    $partnerId = (int)$partnerId; if (!$partnerId) return null;
    try { $docs = ops_all("SELECT id, data FROM report_docs WHERE vendor_id=? AND deleted=0 AND type_code='ER' ORDER BY id DESC LIMIT 60", [$partnerId]); }
    catch (Throwable $e) { return null; }
    if (!$docs) return null;
    $pd = function($s) { $s = trim((string)$s); if ($s === '') return null; $t = strtotime($s); return $t ?: null; };
    $cTotal=0;$cOnTime=0;$cLate=0;$cOpen=0;$cRevised=0;
    $fBoth=0;$fOptim=0;$fSlipSum=0;
    foreach ($docs as $d) {
        $data = json_decode($d['data'] ?? '[]', true); if (!is_array($data)) continue;
        // Commitment reliability — from each report's commitment register.
        foreach ((array)($data['commitments'] ?? []) as $r) {
            $rr = (array)$r; $vals = array_values($rr);
            // columns: commitment, original due, revised due, forecast, actual, status, ...
            $orig = $pd($vals[1] ?? ''); $revised = $pd($vals[2] ?? ''); $actual = $pd($vals[4] ?? '');
            $status = strtolower(trim((string)($vals[5] ?? '')));
            if ($orig === null && $actual === null && $status === '') continue;
            $cTotal++;
            if ($revised !== null && $orig !== null && $revised !== $orig) $cRevised++;
            if ($actual !== null) { if ($orig !== null && $actual <= $orig) $cOnTime++; else $cLate++; }
            elseif (strpos($status,'complete') !== false) $cOnTime++;    // completed, no date → count as met
            else $cOpen++;
        }
        // Forecast reliability — vendor's latest forecast vs the expeditor's.
        $vend = $pd($data['f_vendor_forecast'] ?? ''); $exp = $pd($data['f_expeditor'] ?? '');
        if ($vend !== null && $exp !== null) { $fBoth++; $slip = (int)floor(($exp - $vend) / 86400); if ($slip > 0) { $fOptim++; $fSlipSum += $slip; } }
    }
    $done = $cOnTime + $cLate;
    return [
        'reports' => count($docs),
        'commitments' => ['total'=>$cTotal, 'on_time'=>$cOnTime, 'late'=>$cLate, 'open'=>$cOpen, 'revised'=>$cRevised,
                          'reliability_pct' => $done > 0 ? round($cOnTime / $done * 100, 1) : null],
        'forecast' => ['compared'=>$fBoth, 'optimistic'=>$fOptim, 'optimism_pct' => $fBoth > 0 ? round($fOptim / $fBoth * 100, 1) : null,
                       'avg_optimism_days' => $fOptim > 0 ? round($fSlipSum / $fOptim, 1) : 0],
    ];
}
// ===========================================================================
//  AI Report Auditor — deterministic QA pass (Phase 1)
//  Runs the existing completeness + rule checks and adds a few high-value
//  deterministic checks (quantity reconciliation, result/release vs pending
//  contradiction, required-field enforcement), returning one severity-scored
//  result the UI can show as a traffic light with plain-English issue cards.
//  No LLM is involved — every finding is reproducible. AI advisory suggestions
//  are a later, separate layer. Facts are never changed; issues are only raised.
// ===========================================================================

// Signals that mandatory work still appears to be pending — an activity-table
// status column in a pending/partial state, or a narrative field that says so.
function idems_qa_pending_signals($fields, $data) {
    $out = [];
    $pend = '/\b(pending|in[\s\-]?process|in[\s\-]?progress|partial|not[\s\-]?done|to be offered|to be carried out|balance activit|remain(?:s|ing)?[^.]{0,20}pending|yet to be)\b/i';
    foreach ($fields as $f) {
        $ft = $f['ftype'] ?? ''; $k = $f['fkey'] ?? '';
        if ($ft === 'table') {
            $rows = $data[$k] ?? null; if (!is_array($rows) || !$rows) continue;
            $defs = idems_table_col_defs($f); $statusKey = null;
            foreach ($defs as $ck => $d) { $l = strtolower((string)$d['label']); if (strpos($l,'status')!==false || strpos($l,'progress')!==false || strpos($l,'disposition')!==false) { $statusKey = $ck; break; } }
            if ($statusKey === null) continue;
            foreach ($rows as $i => $r) { $rr = (array)$r; $v = trim((string)($rr[$statusKey] ?? '')); if ($v !== '' && preg_match('/\b(pending|in[\s\-]?process|in[\s\-]?progress|partial|not[\s\-]?done|open|hold|await)\b/i', $v)) $out[] = ['where' => ($f['label'] ?? 'Table') . ' — row ' . ($i+1), 'text' => 'status is "' . $v . '"']; }
        } elseif (in_array($ft, ['textarea','text'], true)) {
            $v = trim((string)($data[$k] ?? '')); if ($v !== '' && preg_match($pend, $v)) $out[] = ['where' => ($f['label'] ?? $k), 'text' => '"' . $v . '"'];
        }
    }
    return $out;
}

// Reconcile quantity columns in a table. Prefers the near-universal identity
// Offered = Passed + Rejected + Hold; falls back to PO = Passed + Rejected +
// Hold + Balance. Returns issue rows (arithmetic shown so a human can judge).
function idems_qa_quantity_checks($f, $rows) {
    $issues = [];
    if (!is_array($rows) || !$rows) return $issues;
    $defs = idems_table_col_defs($f);
    $find = function($needles) use ($defs) { foreach ($defs as $ck => $d) { $l = strtolower((string)$d['label']); foreach ($needles as $n) if (strpos($l, $n) !== false) return $ck; } return null; };
    $num = function($v) { $v = preg_replace('/[^0-9.\-]/', '', (string)$v); return $v === '' || $v === '-' ? null : (float)$v; };
    $poK = $find(['po qty','po quantity','ordered','order qty']);
    $offK = $find(['offered']);
    $passK = $find(['passed','accepted','cleared']);
    $rejK = $find(['rejected','reject']);
    $holdK = $find(['hold']);
    $balK = $find(['balance']);
    if ($passK === null || $rejK === null) return $issues;   // nothing to reconcile against
    foreach ($rows as $i => $r) {
        $rr = (array)$r;
        $pass = $num($rr[$passK] ?? '') ?? 0; $rej = $num($rr[$rejK] ?? '') ?? 0; $hold = $holdK !== null ? ($num($rr[$holdK] ?? '') ?? 0) : 0;
        $where = ($f['label'] ?? 'Table') . ' — row ' . ($i+1);
        if ($offK !== null && ($off = $num($rr[$offK] ?? '')) !== null) {
            $sum = $pass + $rej + $hold;
            if (abs($sum - $off) > 0.001) $issues[] = ['where' => $where, 'why' => 'Offered ' . rtrim(rtrim(number_format($off,2),'0'),'.') . ' ≠ Passed ' . rtrim(rtrim(number_format($pass,2),'0'),'.') . ' + Rejected ' . rtrim(rtrim(number_format($rej,2),'0'),'.') . ($holdK!==null?' + Hold ' . rtrim(rtrim(number_format($hold,2),'0'),'.'):'') . ' = ' . rtrim(rtrim(number_format($sum,2),'0'),'.') . ' (difference ' . rtrim(rtrim(number_format($sum-$off,2),'0'),'.') . ')'];
        } elseif ($poK !== null && ($po = $num($rr[$poK] ?? '')) !== null && $balK !== null) {
            $bal = $num($rr[$balK] ?? '') ?? 0; $sum = $pass + $rej + $hold + $bal;
            if (abs($sum - $po) > 0.001) $issues[] = ['where' => $where, 'why' => 'PO Qty ' . rtrim(rtrim(number_format($po,2),'0'),'.') . ' ≠ Passed + Rejected + Hold + Balance = ' . rtrim(rtrim(number_format($sum,2),'0'),'.') . ' (difference ' . rtrim(rtrim(number_format($sum-$po,2),'0'),'.') . ')'];
        }
    }
    return $issues;
}

// The unified deterministic QA pass. Returns
//   ['score'=>0-100, 'counts'=>[critical,high,medium,low,info], 'status'=>BLOCKED|REVIEW|READY, 'issues'=>[...]]
// Each issue: severity, category, title, location, why, source, suggestion, fixable.
function idems_qa_run($doc, $fields = null, $data = null, $srcDocs = null) {
    if ($fields === null) $fields = function_exists('idems_fields') ? idems_fields((int)($doc['report_type_id'] ?? 0)) : [];
    if ($data === null)   $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = [];
    $issues = [];
    $add = function($sev, $cat, $title, $loc, $why, $source = '', $suggestion = '') use (&$issues) {
        $issues[] = ['severity' => $sev, 'category' => $cat, 'title' => $title, 'location' => $loc, 'why' => $why, 'source' => $source ?: $loc, 'suggestion' => $suggestion];
    };

    // 1) Completeness — reuse the existing submission gate; FAILs become issues.
    if (function_exists('idems_completeness_check')) {
        try { $c = idems_completeness_check($doc);
            foreach (($c['checks'] ?? []) as $chk) if (($chk['status'] ?? '') === 'FAIL')
                $add('high', 'completeness', ($chk['label'] ?? 'A required detail') . ' is missing or incomplete', $chk['label'] ?? '', (string)($chk['detail'] ?? ''), $chk['label'] ?? '', 'Complete this before the report is issued.');
        } catch (Throwable $e) {}
    }
    // 2) Rule / conflict engine — reuse (traceability, revision, calibration…).
    if (function_exists('idems_rule_checks')) {
        try { foreach (idems_rule_checks($doc, is_array($srcDocs) ? $srcDocs : []) as $r) {
            $sev = ($r['severity'] ?? 'low') === 'high' ? 'high' : (($r['severity'] ?? '') === 'medium' ? 'medium' : 'low');
            $add($sev, strtolower(str_replace(' ', '-', (string)($r['kind'] ?? 'rule'))), (string)($r['kind'] ?? 'Check'), (string)($r['kind'] ?? ''), (string)($r['detail'] ?? ''));
        } } catch (Throwable $e) {}
    }
    // 3) Quantity reconciliation on every table.
    foreach ($fields as $f) {
        if (($f['ftype'] ?? '') !== 'table') continue;
        $rows = $data[$f['fkey']] ?? null; if (!is_array($rows) || !$rows) continue;
        foreach (idems_qa_quantity_checks($f, $rows) as $qi)
            $add('high', 'quantity', 'Quantity reconciliation error', $qi['where'], $qi['why'], $qi['where'], 'Check the quantities on this line.');
    }
    // 4) CRITICAL contradiction: a positive result/release while work is pending.
    $result  = strtoupper(trim((string)($doc['result'] ?? '')));
    $release = strtoupper(trim((string)($doc['release_status'] ?? '')));
    $positive = in_array($result, ['ACCEPTED','ACCEPTED_COND','PASS','PASSED'], true) || in_array($release, ['RELEASED','RELEASED_COND'], true);
    if ($positive) {
        $pend = idems_qa_pending_signals($fields, $data);
        foreach ($pend as $pg)
            $add('critical', 'contradiction', 'Result/Release is positive while activities appear pending', $pg['where'],
                 'The report result is "' . ($result ?: '—') . '" / release "' . ($release ?: '—') . '", but ' . $pg['where'] . ': ' . $pg['text'] . '.',
                 $pg['where'], 'Confirm whether acceptance/release applies only to the completed activities, or revise the report status.');
    }
    // 5) Required-field enforcement (text/select/textarea/number/date too).
    foreach ($fields as $f) {
        if (empty($f['required'])) continue;
        $ft = $f['ftype'] ?? ''; if (!in_array($ft, ['text','textarea','select','number','date','radio'], true)) continue;
        $v = $data[$f['fkey']] ?? ''; if (is_array($v)) continue;
        if (trim((string)$v) === '' || strtoupper(trim((string)$v)) === 'NA')
            $add('medium', 'completeness', ($f['label'] ?? 'A required field') . ' is required but blank', $f['label'] ?? '', 'This field is marked mandatory on the form.', $f['label'] ?? '', 'Enter a value for this field.');
    }
    // 6) Known domain spelling slips (deterministic — full grammar is the AI layer).
    if (function_exists('tech_spell_map')) {
        $map = tech_spell_map();
        if (is_array($map) && $map) {
            foreach ($fields as $f) {
                if (!in_array($f['ftype'] ?? '', ['textarea','text'], true)) continue;
                $v = (string)($data[$f['fkey']] ?? ''); if (trim($v) === '') continue;
                foreach ($map as $wrong => $right) {
                    if ($wrong === '' || strcasecmp($wrong, $right) === 0) continue;
                    if (preg_match('/\b' . preg_quote($wrong, '/') . '\b/i', $v))
                        $add('low', 'language', 'Possible spelling: "' . $wrong . '" → "' . $right . '"', $f['label'] ?? '', 'A known domain-term spelling was detected.', $f['label'] ?? '', 'Correct "' . $wrong . '" to "' . $right . '".');
                }
            }
        }
    }

    // 7) Evidence — an adverse finding with no photo / document attached.
    $adverse = '/\b(defect|crack|dent|leak|corros|pitt?ing|damage|scratch|gouge|porosit|undercut|non[\s\-]?conform|not acceptable|rejected|deviation|out of tolerance|blow ?hole)\b/i';
    $mention = null;
    foreach ($fields as $f) {
        if (!in_array($f['ftype'] ?? '', ['textarea','text'], true)) continue;
        $v = (string)($data[$f['fkey']] ?? ''); if (trim($v) === '') continue;
        if (preg_match($adverse, $v, $mm)) { $mention = ['where' => $f['label'] ?? '', 'term' => $mm[0]]; break; }
    }
    if ($mention) {
        $files = function_exists('idems_doc_files') ? idems_doc_files((int)($doc['id'] ?? 0)) : [];
        $hasImg = false; foreach ((array)$files as $fl) if (strpos((string)($fl['mime'] ?? ''), 'image/') === 0) { $hasImg = true; break; }
        $denied = false; foreach ($data as $dk => $dv) if (substr((string)$dk, -14) === '__photo_denied' && (string)$dv === '1') { $denied = true; break; }
        if (!$hasImg && !$denied)
            $add('medium', 'evidence', 'A finding mentions a possible defect but no evidence is attached', $mention['where'],
                 $mention['where'] . ' mentions “' . $mention['term'] . '”, but no photograph or supporting document is attached.', $mention['where'],
                 'Attach a photograph or document for this finding, or mark photography as not permitted.');
    }
    // 7b) Evidence-attached tick — a checklist/finding graded as a nonconformity
    // but marked with its evidence not attached to the report.
    foreach ($fields as $f) {
        if (($f['ftype'] ?? '') !== 'table') continue;
        $rows = $data[$f['fkey']] ?? null; if (!is_array($rows) || !$rows) continue;
        $defs = idems_table_col_defs($f); $attK = null; $resK = null;
        foreach ($defs as $ck => $d) { $l = strtolower((string)$d['label']); if (strpos($l,'attach') !== false) $attK = $ck; elseif (in_array($l, ['result','category','severity'], true)) $resK = $ck; }
        if ($attK === null || $resK === null) continue;
        $miss = 0;
        foreach ($rows as $r) { $rr = (array)$r; $att = strtolower(trim((string)($rr[$attK] ?? ''))); $res = strtolower(trim((string)($rr[$resK] ?? '')));
            $isFinding = strpos($res,'major') !== false || strpos($res,'minor') !== false;
            if ($isFinding && !in_array($att, ['yes','y','1','true','n/a','na'], true)) $miss++;
        }
        if ($miss > 0)
            $add('medium', 'evidence', 'Evidence not attached for ' . $miss . ' nonconformity finding(s)', $f['label'] ?? '',
                 $miss . ' finding(s) graded as a nonconformity are marked with evidence not attached to this report.', $f['label'] ?? '',
                 'Attach the referenced evidence for these findings, or mark it N/A with a note.');
    }
    // 8) Date sanity — issue before inspection, and out-of-range dates.
    $pd = function($s) { $s = trim((string)$s); if ($s === '') return null; $t = strtotime($s); return $t ?: null; };
    $insp = $pd($doc['inspection_date'] ?? ''); $iss = $pd($doc['issue_date'] ?? '');
    if ($insp && $iss && $iss < $insp - 86400)
        $add('medium', 'date', 'Issue date is before the inspection date', 'Dates', 'Issue date ' . date('d-M-Y', $iss) . ' is earlier than the inspection date ' . date('d-M-Y', $insp) . '.', 'Dates', 'Check the inspection and issue dates.');
    $yNow = (int)date('Y');
    foreach ($fields as $f) {
        if (($f['ftype'] ?? '') !== 'date') continue;
        $t = $pd($data[$f['fkey']] ?? ''); if (!$t) continue;
        $y = (int)date('Y', $t);
        if ($y < 2000 || $y > $yNow + 5)
            $add('low', 'date', ($f['label'] ?? 'A date') . ' looks out of range', $f['label'] ?? '', 'The date ' . date('d-M-Y', $t) . ' is outside the expected range — please confirm.', $f['label'] ?? '', 'Check this date.');
    }

    // 9) NCR intelligence — a nonconformity on this report closed without evidence,
    // or one left overdue. (Deterministic; the deeper root-cause quality read is AI.)
    if (!empty($doc['id'])) {
        try {
            $ncrs = ops_all("SELECT * FROM nonconformities WHERE report_doc_id=?", [(int)$doc['id']]);
            $today = date('Y-m-d');
            foreach ((array)$ncrs as $n) {
                $ref = trim((string)($n['ref'] ?? '')) ?: ('NCR #' . (int)$n['id']);
                $stt = strtoupper((string)($n['status'] ?? ''));
                if (strpos($stt, 'CLOS') !== false) {
                    $hasEv = trim((string)($n['close_note'] ?? '')) !== '' || (int)($n['capa_id'] ?? 0) > 0 || trim((string)($n['disposition'] ?? '')) !== '';
                    if (!$hasEv) $add('high', 'ncr', 'NCR closed without closure evidence', $ref, $ref . ' is marked closed but has no closure note, disposition or corrective-action link.', $ref, 'Record the closure evidence, or reopen the NCR.');
                } else {
                    $due = trim((string)($n['due_on'] ?? ''));
                    if ($due !== '' && $due < $today) $add('medium', 'ncr', 'NCR is overdue', $ref, $ref . ' was due on ' . $due . ' and is still ' . ($stt ? strtolower($stt) : 'open') . '.', $ref, 'Progress or re-date this NCR.');
                }
            }
        } catch (Throwable $e) {}
    }

    // 10) Scorecard vs recommendation — only for scored report types. If the
    // assessment recommends approval but the computed score is poor (or rejects a
    // strong vendor), that contradiction is surfaced for review (deterministic).
    if (function_exists('idems_score_doc')) {
        $sc = null; try { $secs = function_exists('idems_sections') ? idems_sections((int)($doc['report_type_id'] ?? 0)) : []; $sc = idems_score_doc($secs, $fields, $data); } catch (Throwable $e) {}
        if ($sc && $sc['overall'] !== null) {
            $ov = (float)$sc['overall'];
            $recRaw = ''; foreach ($fields as $f) { if (($f['fkey'] ?? '') === 'recommendation') { $recRaw = strtolower(trim((string)($data[$f['fkey']] ?? ''))); break; } }
            $approves = $recRaw !== '' && strpos($recRaw, 'not approved') === false && (strpos($recRaw, 'approv') !== false);
            $rejects  = $recRaw !== '' && strpos($recRaw, 'not approved') !== false;
            if ($approves && $ov < 50)
                $add('high', 'scorecard', 'Recommendation approves the vendor despite a low score', 'Recommendation',
                     'The assessment score is ' . rtrim(rtrim(number_format($ov,1),'0'),'.') . '/100 (' . $sc['band'] . '), but the recommendation is "' . $recRaw . '".',
                     'Recommendation', 'Reconcile the recommendation with the score, or record the justification.');
            if ($rejects && $ov >= 75)
                $add('medium', 'scorecard', 'Recommendation rejects a vendor with a strong score', 'Recommendation',
                     'The assessment score is ' . rtrim(rtrim(number_format($ov,1),'0'),'.') . '/100 (' . $sc['band'] . '), but the vendor is not approved.',
                     'Recommendation', 'Confirm the reason for not approving despite the score.');
        }
    }

    // 11) Vendor intelligence — for a Vendor Assessment / Audit that recommends
    // approval, cross-check the vendor's live record. Deterministic and
    // reproducible (the AI-advisory layer never gates; this is a real check).
    $tc = strtoupper((string)($doc['type_code'] ?? ''));
    $vidP = (int)($doc['vendor_id'] ?? 0);
    if ($vidP > 0 && in_array($tc, ['VASR','VAR'], true)) {
        $recRaw = strtolower(trim((string)($data['recommendation'] ?? '')));
        $approves = $recRaw !== '' && strpos($recRaw, 'not approved') === false && strpos($recRaw, 'suspend') === false && strpos($recRaw, 'approv') !== false;
        if ($approves) {
            // a) Open major / overdue nonconformities against the vendor.
            try {
                $today = date('Y-m-d');
                $openMajor = (int)ops_val("SELECT COUNT(*) FROM nonconformities WHERE partner_id=? AND status <> 'CLOSED' AND severity='MAJOR' AND (report_doc_id IS NULL OR report_doc_id <> ?)", [$vidP, (int)($doc['id'] ?? 0)]);
                $overdue   = (int)ops_val("SELECT COUNT(*) FROM nonconformities WHERE partner_id=? AND status <> 'CLOSED' AND due_on <> '' AND due_on < ?", [$vidP, $today]);
                if ($openMajor > 0)
                    $add('high', 'vendor', 'Approval recommended while open major nonconformities exist', 'Vendor record',
                         'This report recommends approval, but the vendor has ' . $openMajor . ' open major nonconformity(ies) in the register.', 'Vendor record',
                         'Close or disposition the major NCR(s), or record why approval stands regardless.');
                if ($overdue > 0)
                    $add('medium', 'vendor', 'Approval recommended while nonconformities are overdue', 'Vendor record',
                         'The vendor has ' . $overdue . ' overdue nonconformity(ies) still open.', 'Vendor record',
                         'Progress the overdue NCR(s) before confirming approval.');
                // Open customer complaints against the vendor.
                $openCmp = (int)ops_val("SELECT COUNT(*) FROM complaints WHERE partner_id=? AND status <> 'CLOSED'", [$vidP]);
                if ($openCmp > 0)
                    $add('medium', 'vendor', 'Approval recommended while customer complaints are open', 'Customer complaints',
                         'The vendor has ' . $openCmp . ' open customer complaint(s) that are not yet resolved.', 'Customer complaints',
                         'Review the complaints & their resolution before approving; record them in the report.');
            } catch (Throwable $e) {}
            // b) Live performance well below the assessment.
            if (function_exists('idems_vendor_performance')) {
                try {
                    $pf = idems_vendor_performance($vidP);
                    if ($pf && $pf['score'] !== null && (float)$pf['score'] < 50)
                        $add('medium', 'vendor', 'Live performance is poor despite the approval recommendation', 'Vendor 360',
                             'The vendor\'s live performance score is ' . rtrim(rtrim(number_format((float)$pf['score'],1),'0'),'.') . '/100 (' . $pf['band'] . '), driven by open quality issues.', 'Vendor 360',
                             'Review the open reports, NCRs and complaints on the vendor page before approving.');
                } catch (Throwable $e) {}
            }
            // c) A vendor registration / certification appears expired.
            try {
                $today = date('Y-m-d');
                $expCert = ops_one("SELECT doc_type, number, valid_to FROM partner_registrations WHERE partner_id=? AND valid_to <> '' AND valid_to < ? ORDER BY valid_to DESC LIMIT 1", [$vidP, $today]);
                if ($expCert)
                    $add('medium', 'vendor', 'A vendor certification appears expired', 'Vendor registrations',
                         trim((string)($expCert['doc_type'] ?? 'A registration')) . ' ' . trim((string)($expCert['number'] ?? '')) . ' expired on ' . $expCert['valid_to'] . '.', 'Vendor registrations',
                         'Obtain the current certificate before approving, or note the exception.');
            } catch (Throwable $e) {}
        }
    }

    // 12) Expediting intelligence — forecast plausibility & schedule contradictions
    // for an Expediting Report. Deterministic; surfaces what a management reader
    // needs to question. (AI plausibility analysis is a later, advisory layer.)
    if ($tc === 'ER' && function_exists('idems_expediting_status')) {
        try {
            $es = idems_expediting_status($doc, $fields, $data);
            $fc = $es['forecast'];
            // a) Expeditor's forecast is after the required date — delivery at risk.
            if ($fc['expeditor_variance_days'] !== null && $fc['expeditor_variance_days'] > 0)
                $add('high', 'expediting', "Forecast delivery is after the required date", 'Delivery forecast',
                     "The expeditor's forecast (" . $fc['expeditor'] . ") is " . $fc['expeditor_variance_days'] . " day(s) after the required date (" . $fc['required'] . ").", 'Delivery forecast',
                     'Confirm the recovery plan, or escalate the delivery risk to the client.');
            // b) Vendor forecast materially earlier than the expeditor's — optimism.
            if ($fc['vendor_vs_expeditor_days'] !== null && $fc['vendor_vs_expeditor_days'] >= 7)
                $add('medium', 'expediting', "Vendor forecast looks optimistic vs the expeditor's", 'Delivery forecast',
                     "The vendor's latest forecast is " . $fc['vendor_vs_expeditor_days'] . " day(s) earlier than the expeditor's evidence-based forecast.", 'Delivery forecast',
                     'Record the basis; treat the vendor forecast with the noted confidence.');
            // c) Overall progress high while a milestone is delayed — contradiction.
            $sc = function_exists('idems_score_doc') ? idems_score_doc($sections ?? idems_sections((int)($doc['report_type_id'] ?? 0)), $fields, $data) : null;
            if ($sc && ($sc['overall'] ?? null) !== null && (float)$sc['overall'] >= 80 && ($es['milestones']['delayed'] ?? 0) > 0)
                $add('medium', 'expediting', 'High overall progress despite a delayed milestone', 'Milestone status',
                     'Overall progress reads ' . rtrim(rtrim(number_format((float)$sc['overall'],1),'0'),'.') . '% but ' . $es['milestones']['delayed'] . ' milestone(s) are marked delayed.', 'Milestone status',
                     'Check that the stage weights and the milestone status are consistent.');
            // d) Open quality issues against the vendor that may affect delivery.
            $vp = (int)($doc['vendor_id'] ?? 0);
            if ($vp > 0) {
                $openMaj = (int)ops_val("SELECT COUNT(*) FROM nonconformities WHERE partner_id=? AND status <> 'CLOSED' AND severity='MAJOR'", [$vp]);
                if ($openMaj > 0)
                    $add('medium', 'expediting', 'Open major nonconformities may affect delivery', 'Quality / NCR',
                         'The vendor has ' . $openMaj . ' open major nonconformity(ies); confirm whether they impact this delivery.', 'Quality / NCR',
                         'Assess the delivery impact of the open NCR(s) and reflect it in the forecast.');
            }
            // e) Dispatch-readiness mandatory blockers — a report can read mostly
            // ready and still be blocked on a mandatory item.
            if (function_exists('idems_expediting_dispatch_readiness')) {
                $dr = idems_expediting_dispatch_readiness($fields, $data);
                if ($dr && !empty($dr['blockers']))
                    $add('high', 'expediting', 'Dispatch is blocked on ' . count($dr['blockers']) . ' mandatory item(s)', 'Dispatch readiness',
                         'Dispatch readiness is ' . ($dr['pct'] === null ? '—' : rtrim(rtrim(number_format((float)$dr['pct'],1),'0'),'.') . '%') . ', but mandatory item(s) are not met: ' . implode('; ', array_slice($dr['blockers'], 0, 6)) . '.', 'Dispatch readiness',
                         'Close the mandatory blocker(s) before dispatch, regardless of the overall %.');
            }
        } catch (Throwable $e) {}
    }

    // 13) Inspection data-integrity (UIRE) — measurement-vs-result, quantity
    // reconciliation and result-vs-checklist contradictions. ADVISORY: it flags,
    // it NEVER changes a measurement, a result or a finding (§61/§62). Self-gates
    // on the inspection library fields, so it is inert on non-inspection reports.
    if (function_exists('uire_qa_checks')) {
        try { foreach (uire_qa_checks($doc, $fields, $data) as $qi)
            $add($qi['sev'], $qi['cat'], $qi['title'], $qi['loc'], $qi['why'], $qi['loc'], $qi['fix'] ?? ''); }
        catch (Throwable $e) {}
    }

    // 14) Release data-integrity (URADE) — disposition-vs-eligibility, quantity
    // math, item-level and date contradictions. ADVISORY: it flags, it never
    // releases or changes a disposition (§61/§88). Self-gates on release fields.
    if (function_exists('urade_qa_checks')) {
        try { foreach (urade_qa_checks($doc, $fields, $data) as $qi)
            $add($qi['sev'], $qi['cat'], $qi['title'], $qi['loc'], $qi['why'], $qi['loc'], $qi['fix'] ?? ''); }
        catch (Throwable $e) {}
    }

    $counts = ['critical'=>0,'high'=>0,'medium'=>0,'low'=>0,'info'=>0];
    foreach ($issues as $it) { $s = $it['severity']; if (isset($counts[$s])) $counts[$s]++; }
    $score = 100 - ($counts['critical']*40 + $counts['high']*12 + $counts['medium']*5 + $counts['low']*1 + $counts['info']*0);
    $score = max(0, min(100, $score));
    $status = $counts['critical'] > 0 ? 'BLOCKED' : (($counts['high'] > 0 || $counts['medium'] > 0) ? 'REVIEW' : 'READY');
    // Order most-severe first for display.
    $rank = ['critical'=>0,'high'=>1,'medium'=>2,'low'=>3,'info'=>4];
    usort($issues, fn($a,$b) => ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9));
    return ['score'=>$score, 'counts'=>$counts, 'status'=>$status, 'issues'=>$issues];
}

function idems_sections($typeId) { return ops_all("SELECT * FROM report_sections WHERE report_type_id=? ORDER BY sort_order, id", [(int)$typeId]); }
// Resolve a report type's id from its code (cached per request), then its schema.
function idems_type_id_by_code($code) {
    static $cache = [];
    $code = (string)$code; if (isset($cache[$code])) return $cache[$code];
    return $cache[$code] = (int)ops_val("SELECT id FROM report_types WHERE code=?", [$code]);
}
function idems_sections_by_code($code) { $id = idems_type_id_by_code($code); return $id ? idems_sections($id) : []; }
function idems_fields_by_code($code) { $id = idems_type_id_by_code($code); return $id ? idems_fields($id) : []; }
function idems_fields($typeId, $sectionId = null) {
    if ($sectionId === null) return ops_all("SELECT * FROM report_fields WHERE report_type_id=? ORDER BY sort_order, id", [(int)$typeId]);
    return ops_all("SELECT * FROM report_fields WHERE report_type_id=? AND section_id=? ORDER BY sort_order, id", [(int)$typeId, (int)$sectionId]);
}
// Is there already a report that this one would merely repeat? Keyed on the
// inspection it belongs to, its type and the day it covers — the three things
// that make two reports the same report. With no date to key on, fall back to a
// short window, which is long enough to catch a resend and short enough never to
// stand in the way of real work.
function idems_existing_twin($f) {
    $typeId = (int)($f['report_type_id'] ?? 0);
    if (!$typeId) return null;
    $jobId  = (int)($f['job_id'] ?? 0);
    $callId = (int)($f['call_id'] ?? 0);
    $client = (int)($f['client_id'] ?? 0);
    $date   = trim((string)($f['inspection_date'] ?? ''));
    $where = "deleted=0 AND report_type_id=?"; $args = [$typeId];
    if ($jobId)       { $where .= " AND job_id=?";    $args[] = $jobId; }
    elseif ($callId)  { $where .= " AND call_id=?";   $args[] = $callId; }
    elseif ($client)  { $where .= " AND client_id=?"; $args[] = $client; }
    else return null;                       // nothing to anchor it to
    if ($date !== '') { $where .= " AND inspection_date=?"; $args[] = $date; }
    else              { $where .= " AND created_at >= ?";   $args[] = date('c', time() - 120); }
    return ops_one("SELECT id, irn FROM report_docs WHERE $where ORDER BY id DESC LIMIT 1", $args);
}
function idems_has_schema($typeId) { return (int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=?", [(int)$typeId]) > 0; }
// Parse a field's options ("A|B|C" or lookup:key) into [value=>label].
function idems_field_options($f, $doc = null) {
    $o = trim((string)($f['options'] ?? ''));
    // A unit picker: choices come from the field's own list if given, else the
    // editable "measurement units" master (mm, kg, nos, %, °C …).
    if (($f['ftype'] ?? '') === 'unit' && $o === '') return lk_options_or('measurement_units', defined('MEASUREMENT_UNITS') ? MEASUREMENT_UNITS : []);
    if ($o === '') return [];
    if (strpos($o, 'lookup:') === 0) return lk_options_or(substr($o, 7), []);
    // A dropdown whose choices come from THIS report's inspection call/job, e.g.
    // call:po_items lists every line item on the order behind the call.
    if (strpos($o, 'call:') === 0) return $doc ? idems_call_options($doc, substr($o, 5)) : [];
    $out = [];
    foreach (preg_split('/\r?\n|\|/', $o) as $line) { $line = trim($line); if ($line === '') continue; $parts = explode('=', $line, 2); $out[trim($parts[0])] = trim($parts[count($parts)>1?1:0]); }
    return $out;
}
// Should a conditional field/section be shown, given the report's data? Mirrors
// the show-when rule the builder stores (cond_field / cond_op / cond_val) and the
// live JS on the fill screen — but evaluated in PHP so the FINAL report (PDF)
// honours conditions too, not only the data-entry form. Empty rule = always show.
function idems_cond_visible($row, $data) {
    $cf = trim((string)($row['cond_field'] ?? '')); $op = (string)($row['cond_op'] ?? '');
    if ($cf === '' || $op === '') return true;
    $cur = $data[$cf] ?? '';
    if (is_array($cur)) $cur = implode(',', $cur);
    $cur = trim((string)$cur); $val = trim((string)($row['cond_val'] ?? ''));
    switch ($op) {
        case 'eq':       return $cur === $val;
        case 'ne':       return $cur !== $val;
        case 'in':       return in_array($cur, array_map('trim', preg_split('/[|,]/', $val)), true);
        case 'nonempty': return $cur !== '';
        case 'empty':    return $cur === '';
    }
    return true;
}
// Options pulled live from the inspection call/job this report belongs to.
// value === label (the stored value is the text itself), so the PDF and Word
// output simply print what was chosen without needing the call at print time.
function idems_call_options($doc, $key) {
    $key = strtolower(trim((string)$key));
    $callId = (int)($doc['call_id'] ?? 0);
    $jobId  = (int)($doc['job_id'] ?? 0);
    if (!$callId && $jobId) $callId = (int)ops_val("SELECT call_id FROM jobs WHERE id=?", [$jobId]);
    $out = [];
    try {
        if ($key === 'po_items') {
            // every line item on the same purchase order as the call's item. The
            // call's own line item pins the PO exactly, so prefer it; fall back to
            // the call's po_id.
            $call = $callId ? ops_one("SELECT po_id, po_line_item_id FROM calls WHERE id=?", [$callId]) : null;
            $poId = null;
            if (!empty($call['po_line_item_id'])) $poId = ops_val("SELECT purchase_order_id FROM po_line_items WHERE id=?", [(int)$call['po_line_item_id']]);
            if (!$poId && !empty($call['po_id'])) $poId = (int)$call['po_id'];
            if ($poId) foreach (ops_all("SELECT description FROM po_line_items WHERE purchase_order_id=? ORDER BY id", [(int)$poId]) as $r) {
                $d = trim((string)$r['description']); if ($d !== '') $out[$d] = $d;
            }
        } elseif ($key === 'inspection_types') {
            foreach ((defined('INSPECTION_TYPES') ? INSPECTION_TYPES : []) as $v) { $out[$v] = $v; }
        }
    } catch (Throwable $e) { return []; }
    return $out;
}
// Resolve a single column-option token to a flat list of choice strings.
// Understands three forms:
//   call:<key>      -> live, call-linked options (e.g. call:po_items)
//   lookup:<key>    -> an editable lookup master (e.g. lookup:inspection_disposition)
//   plain a;b;c     -> the literal choices, already split by the caller
// Returns a numerically-indexed array of labels ready for a <select>.
function idems_col_options($doc, $token) {
    $token = trim((string)$token);
    if ($token === '') return [];
    if (stripos($token, 'call:') === 0) {
        return array_values(idems_call_options($doc, substr($token, 5)));
    }
    if (stripos($token, 'lookup:') === 0) {
        $key = strtolower(trim(substr($token, 7)));
        // Fall back to the shipped constants so options exist even before the
        // lookup master has been seeded/edited.
        $fallback = [];
        if ($key === 'inspection_disposition' && defined('INSPECTION_DISPOSITIONS')) $fallback = array_values(INSPECTION_DISPOSITIONS);
        elseif ($key === 'activity_progress' && defined('ACTIVITY_PROGRESS'))       $fallback = array_values(ACTIVITY_PROGRESS);
        if (function_exists('lk_options_or')) {
            $opts = lk_options_or($key, $fallback);
            return array_values($opts);
        }
        return $fallback;
    }
    // The active instruments from the equipment register — used by the master-
    // linked instrument table so an inspector picks a real, calibrated device.
    if (stripos($token, 'equip:') === 0) {
        return array_keys(idems_equipment_active());
    }
    return [$token];
}
// The active equipment register, keyed by a display label, each carrying its
// latest calibration (serial, cal date, due date, cert, body, traceable). Drives
// the master-linked instrument table's dropdown and its client-side auto-fill.
function idems_equipment_active() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        $rows = db()->query("SELECT * FROM equipment WHERE COALESCE(status,'ACTIVE') NOT IN ('RETIRED','INACTIVE') ORDER BY name, code")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return $cache; }
    foreach ($rows as $r) {
        $cal = null;
        try { $st = db()->prepare("SELECT * FROM equipment_calibrations WHERE equipment_id=? ORDER BY cal_date DESC, id DESC LIMIT 1"); $st->execute([$r['id']]); $cal = $st->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $e) {}
        $label = trim((string)($r['name'] ?: $r['kind'] ?: 'Instrument'));
        $sn = trim((string)($r['serial_no'] ?? ''));
        if ($sn !== '') $label .= ' — SN ' . $sn;
        elseif (trim((string)($r['code'] ?? '')) !== '') $label .= ' (' . $r['code'] . ')';
        // avoid collisions
        $base = $label; $i = 2; while (isset($cache[$label])) { $label = $base . ' #' . $i; $i++; }
        $cache[$label] = [
            'serial'    => $sn ?: trim((string)($r['code'] ?? '')),
            'cal_on'    => $cal['cal_date'] ?? '',
            'cal_due'   => $cal['valid_to'] ?? '',
            'cert'      => $cal['cert_no'] ?? '',
            'body'      => $cal['cal_body'] ?? '',
            'traceable' => (trim((string)($cal['traceable_to'] ?? '')) !== '' ? 'Yes' : ''),
        ];
    }
    return $cache;
}
// A field's stored value from the report's data JSON.
function idems_val($data, $fkey, $default = '') { return $data[$fkey] ?? $default; }
// Attachments for a doc (optionally one field).
function idems_doc_files($docId, $fieldKey = null) {
    if ($fieldKey === null) return ops_all("SELECT id, field_key, kind, file_name, mime, gps, note, created_at FROM report_files WHERE report_doc_id=? ORDER BY id", [(int)$docId]);
    return ops_all("SELECT id, field_key, kind, file_name, mime, gps, note, created_at FROM report_files WHERE report_doc_id=? AND field_key=? ORDER BY id", [(int)$docId, $fieldKey]);
}

// ---------------------------------------------------------------------------
//  Audit log
// ---------------------------------------------------------------------------
function idems_log($entity, $entityId, $action, $opts = []) {
    $u = function_exists('current_user') ? current_user() : null;
    // Build the row once, so the seal is computed over exactly what is stored.
    $r = [
        'entity'    => $entity,
        'entity_id' => $entityId ?: null,
        'irn'       => $opts['irn'] ?? '',
        'action'    => $action,
        'field'     => $opts['field'] ?? '',
        'old_value' => isset($opts['old']) ? (is_scalar($opts['old']) ? (string)$opts['old'] : json_encode($opts['old'])) : '',
        'new_value' => isset($opts['new']) ? (is_scalar($opts['new']) ? (string)$opts['new'] : json_encode($opts['new'])) : '',
        'reason'    => $opts['reason'] ?? '',
        'username'  => $u ? user_name($u) : 'system',
        'role'      => $u['role'] ?? '',
        'office_id' => $u['home_office_id'] ?? null,
        'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
        'device'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        'created_at'=> date('c'),
    ];
    // Seal to the previous sealed entry. Reading it must never stop the log
    // being written, so it is wrapped; the worst case is the chain restarting,
    // which verify() reports honestly rather than hiding.
    $prev = '';
    try { $prev = (string)(ops_val("SELECT entry_hash FROM idems_audit WHERE entry_hash<>'' ORDER BY id DESC LIMIT 1") ?: ''); }
    catch (Throwable $e) { $prev = ''; }
    $entryHash = hash('sha256', $prev . '|' . idems_audit_payload($r));
    try {
        db()->prepare("INSERT INTO idems_audit (entity,entity_id,irn,action,field,old_value,new_value,reason,username,role,office_id,ip,device,created_at,prev_hash,entry_hash)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
            $r['entity'], $r['entity_id'], $r['irn'], $r['action'], $r['field'], $r['old_value'], $r['new_value'],
            $r['reason'], $r['username'], $r['role'], $r['office_id'], $r['ip'], $r['device'], $r['created_at'],
            $prev, $entryHash]);
    } catch (Throwable $e) {
        // The seal columns arrive by upgrade; before the migration lands, fall
        // back to the original unsealed insert so logging never breaks an action.
        db()->prepare("INSERT INTO idems_audit (entity,entity_id,irn,action,field,old_value,new_value,reason,username,role,office_id,ip,device,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
            $r['entity'], $r['entity_id'], $r['irn'], $r['action'], $r['field'], $r['old_value'], $r['new_value'],
            $r['reason'], $r['username'], $r['role'], $r['office_id'], $r['ip'], $r['device'], $r['created_at']]);
    }
}

// The exact string a row's seal is computed over — used identically when a row
// is written and when the chain is verified, so the two always agree.
function idems_audit_payload($r) {
    return implode('|', [
        (string)($r['entity'] ?? ''), (string)($r['entity_id'] ?? ''), (string)($r['irn'] ?? ''),
        (string)($r['action'] ?? ''), (string)($r['field'] ?? ''), (string)($r['old_value'] ?? ''),
        (string)($r['new_value'] ?? ''), (string)($r['reason'] ?? ''), (string)($r['username'] ?? ''),
        (string)($r['role'] ?? ''), (string)($r['office_id'] ?? ''), (string)($r['ip'] ?? ''),
        (string)($r['device'] ?? ''), (string)($r['created_at'] ?? ''),
    ]);
}

// Walk the sealed entries and report tampering. Two independent tests, both
// tolerant of several entries being written in the same instant:
//   content — recompute each row's seal from what it stores; a mismatch means
//             the row's own text was altered after the fact.
//   links   — every row's prev_hash must be the seal of some earlier row; if it
//             points at a seal no longer present, a row in between was deleted.
// Unsealed legacy rows (empty seal) are skipped, never failed.
function idems_audit_verify() {
    $rows = [];
    try { $rows = ops_all("SELECT * FROM idems_audit WHERE entry_hash<>'' ORDER BY id") ?: []; }
    catch (Throwable $e) {
        return ['ok' => true, 'skipped' => true, 'checked' => 0, 'broken' => 0,
                'content' => 0, 'links' => 0, 'first_break' => null];
    }
    $seen = []; $brokenContent = 0; $brokenLink = 0; $firstBreak = null;
    foreach ($rows as $r) {
        $calc = hash('sha256', (string)$r['prev_hash'] . '|' . idems_audit_payload($r));
        $bad = false;
        if (!hash_equals((string)$r['entry_hash'], $calc)) { $brokenContent++; $bad = true; }
        if ((string)$r['prev_hash'] !== '' && !isset($seen[(string)$r['prev_hash']])) { $brokenLink++; $bad = true; }
        if ($bad && $firstBreak === null) $firstBreak = (int)$r['id'];
        $seen[(string)$r['entry_hash']] = true;
    }
    $broken = $brokenContent + $brokenLink;
    return ['ok' => $broken === 0, 'skipped' => count($rows) === 0, 'checked' => count($rows),
            'broken' => $broken, 'content' => $brokenContent, 'links' => $brokenLink, 'first_break' => $firstBreak];
}

// ---------------------------------------------------------------------------
//  IRN engine (Part 19) — configurable, no-code, zero-duplicate.
//  Format is a token template, default: {COMPANY}/{BRANCH}/{YEAR}/{CLIENT}/{TYPE}/{SERIAL}
//  The running serial is scoped by everything that precedes it, so each
//  company/branch/year/client/type combination keeps its own sequence.
// ---------------------------------------------------------------------------
// The IRN numbering pattern. An executing office/branch may carry its own
// pattern (offices.irn_format) — its clients often demand a specific style — and
// that overrides the global default. Blank office pattern → the global one.
function idems_irn_format($doc = null) {
    if (is_array($doc) && !empty($doc['office_id'])) {
        $of = ops_one("SELECT irn_format FROM offices WHERE id=?", [(int)$doc['office_id']]);
        $p = trim((string)($of['irn_format'] ?? ''));
        if ($p !== '') return $p;
    }
    return setting_get('idems_irn_format', '') ?: '{COMPANY}/{BRANCH}/{YEAR}/{CLIENT}/{TYPE}/{SERIAL}';
}
function idems_company_code() { return strtoupper(setting_get('idems_company_code', '') ?: 'MGH'); }
function idems_serial_width() { $w = (int)setting_get('idems_serial_width', 6); return $w >= 3 && $w <= 10 ? $w : 6; }
function idems_available_tokens() {
    return ['{COMPANY}'=>'Company code','{BRANCH}'=>'Office / branch code','{YEAR}'=>'Calendar year','{FY}'=>'Financial year','{CLIENT}'=>'Client code','{PROJECT}'=>'Project code','{TYPE}'=>'Report-type code','{SERIAL}'=>'Running serial'];
}
// Strip a value to bare uppercase alphanumerics (safe for an IRN path segment).
function idems_clean_token($s) { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$s)); }
// Short code from a name: alphanumerics, uppercase, first up-to-6 chars.
function idems_code_from_name($name) {
    $s = idems_clean_token($name);
    return $s !== '' ? substr($s, 0, 6) : 'GEN';
}
// Resolve a client's short IRN code: a tidy existing code (≤8 alnum) wins, else derive from the name.
function idems_client_code($client) {
    if (!$client) return 'GEN';
    $clean = idems_clean_token($client['code'] ?? '');
    if ($clean !== '' && strlen($clean) <= 8) return $clean;
    return idems_code_from_name($client['display_name'] ?: $client['legal_name']);
}
// Resolve the token values for a report doc (client/branch codes resolved from masters).
function idems_tokens_for($doc) {
    $office = !empty($doc['office_id']) ? ops_one("SELECT code, name FROM offices WHERE id=?", [$doc['office_id']]) : null;
    $branch = $office ? (idems_clean_token($office['code']) ?: idems_code_from_name($office['name'])) : 'HO';
    $client = !empty($doc['client_id']) ? ops_one("SELECT legal_name, display_name, code FROM business_partners WHERE id=?", [$doc['client_id']]) : null;
    $clientCode = idems_clean_token($doc['client_code'] ?? '') ?: ($client ? idems_client_code($client) : 'GEN');
    $year = $doc['inspection_date'] ? date('Y', strtotime($doc['inspection_date'])) : date('Y');
    $fy   = function_exists('fy_of') ? fy_of($doc['inspection_date'] ?: date('Y-m-d')) : $year;
    return [
        '{COMPANY}' => idems_company_code(),
        '{BRANCH}'  => strtoupper($branch),
        '{YEAR}'    => $year,
        '{FY}'      => $fy,
        '{CLIENT}'  => $clientCode,
        '{PROJECT}' => idems_clean_token($doc['project_code'] ?? ''),
        '{TYPE}'    => idems_clean_token($doc['type_code'] ?? ''),
    ];
}
// Collapse empty path segments so an omitted {PROJECT} doesn't leave "//".
function idems_collapse($s) { return trim(preg_replace('#/{2,}#', '/', $s), '/'); }
// Preview the IRN prefix (everything before the serial) for a doc.
function idems_prefix_for($doc) {
    $tok = idems_tokens_for($doc);
    $fmt = idems_irn_format($doc);
    $s = strtr(str_replace('{SERIAL}', '', $fmt), $tok);
    return idems_collapse($s);
}
// Generate the next IRN for a doc. Atomically bumps the scope counter; the unique
// index on report_docs.irn is the final guarantee against duplicates.
function idems_generate_irn($doc, $maxTries = 5) {
    $tok = idems_tokens_for($doc);
    $fmt = idems_irn_format($doc);
    $prefix = idems_prefix_for($doc);
    $width = idems_serial_width();
    for ($try = 0; $try < $maxTries; $try++) {
        $serial = idems_next_serial($prefix);
        $irn = idems_collapse(strtr(str_replace('{SERIAL}', str_pad((string)$serial, $width, '0', STR_PAD_LEFT), $fmt), $tok));
        // ensure not already taken (defensive)
        if (!ops_val("SELECT COUNT(*) FROM report_docs WHERE irn=?", [$irn])) return [$irn, $serial];
    }
    // last resort: append a timestamp-ish suffix (keeps uniqueness even on collision)
    $serial = idems_next_serial($prefix);
    return [$prefix . '/' . str_pad((string)$serial, $width, '0', STR_PAD_LEFT) . '-' . substr(md5($prefix . $serial), 0, 4), $serial];
}
// Atomic-ish increment of a scope counter (transaction + unique index + retry).
function idems_next_serial($scopeKey) {
    $pdo = db();
    for ($i = 0; $i < 5; $i++) {
        try {
            $pdo->beginTransaction();
            $row = ops_one("SELECT id, last_serial FROM idems_counters WHERE scope_key=?", [$scopeKey]);
            if ($row) {
                $next = (int)$row['last_serial'] + 1;
                $pdo->prepare("UPDATE idems_counters SET last_serial=?, updated_at=? WHERE id=?")->execute([$next, date('c'), $row['id']]);
            } else {
                $next = 1;
                $pdo->prepare("INSERT INTO idems_counters (scope_key,last_serial,updated_at) VALUES (?,?,?)")->execute([$scopeKey, 1, date('c')]);
            }
            $pdo->commit();
            return $next;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            usleep(50000);
        }
    }
    return time() % 1000000;   // extreme fallback, still unique via irn index
}

// ---------------------------------------------------------------------------
//  Helpers
// ---------------------------------------------------------------------------
function idems_types($activeOnly = true) {
    return ops_all("SELECT * FROM report_types " . ($activeOnly ? "WHERE active=1 " : "") . "ORDER BY sort_order, name");
}
function idems_can_edit_doc($doc) {
    if (empty($doc)) return false;
    if (!empty($doc['finalized'])) return false;                 // finalized = immutable
    return is_master() || can('mod.idems.edit');
}
function idems_status_pill($s) {
    return ['DRAFT'=>'p-mut','SUBMITTED'=>'p-info','UNDER_REVIEW'=>'p-warn','APPROVED'=>'p-ok','ISSUED'=>'p-ok','REJECTED'=>'p-bad','ARCHIVED'=>'p-mut'][$s] ?? 'p-mut';
}

// ===========================================================================
//  Inspection Completeness Check — the pre-submission gate.
//  Distinct from template validation (which checks the FORMAT). This checks the
//  filled REPORT is complete before it can be submitted for approval: identity,
//  scope, activities, evidence, dispositions, signature. Each check is PASS /
//  FAIL / NA; a check becomes NA when it doesn't apply to this report's form
//  (e.g. no photo field → "Required photographs" is N/A). The gate opens only
//  when every applicable mandatory check passes. Identity/PO/location/spec flow
//  in from the call & job, so the inspector rarely re-types them.
// ===========================================================================
function idems_completeness_check($doc) {
    $docId = (int)($doc['id'] ?? 0);
    $data  = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = [];
    $fields = $docId ? idems_fields((int)$doc['report_type_id']) : [];
    // Linked call/job so values FLOW IN rather than being re-typed.
    $job  = !empty($doc['job_id'])  ? ops_one("SELECT * FROM jobs WHERE id=?",  [(int)$doc['job_id']])  : null;
    $call = !empty($doc['call_id']) ? ops_one("SELECT * FROM calls WHERE id=?", [(int)$doc['call_id']]) : ($job && !empty($job['call_id']) ? ops_one("SELECT * FROM calls WHERE id=?", [(int)$job['call_id']]) : null);
    // effective value: prefer the report, fall back to job then call
    $eff = function($keys) use ($doc, $job, $call) {
        foreach ((array)$keys as $src => $k) {
            $row = is_int($src) ? $doc : ($src === 'job' ? $job : ($src === 'call' ? $call : $doc));
            if ($row && trim((string)($row[$k] ?? '')) !== '') return trim((string)$row[$k]);
        }
        return '';
    };
    $files = $docId ? idems_doc_files($docId) : [];
    $filesByKind = []; foreach ($files as $fl) $filesByKind[$fl['kind']][] = $fl;
    $hasField = function($pred) use ($fields) { foreach ($fields as $f) if ($pred($f)) return $f; return null; };
    $tableFields = array_values(array_filter($fields, fn($f) => ($f['ftype'] ?? '') === 'table'));

    $checks = [];
    $add = function($key, $label, $status, $detail = '') use (&$checks) { $checks[] = ['key'=>$key,'label'=>$label,'status'=>$status,'detail'=>$detail]; };
    $yn = fn($ok, $okMsg='', $bad='Missing') => $ok ? ['PASS', $okMsg] : ['FAIL', $bad];

    // --- Identity & context (flows from the call / job) ---
    [$s,$d] = $yn(!empty($doc['inspector_id']), 'Assigned', 'Pick the inspector on the report'); $add('inspector','Inspector identified',$s,$d);
    [$s,$d] = $yn(!empty($doc['client_id']), '', 'No client — set it on the call'); $add('client','Client identified',$s,$d);
    $isVendorType = trim((string)$eff([0=>'vendor_id'])) !== '' || !empty($doc['vendor_id']);
    [$s,$d] = $yn(!empty($doc['vendor_id']), '', 'No vendor / manufacturer'); $add('vendor','Vendor / manufacturer identified',$s,$d);
    $po = $eff([0=>'po_ref', 'job'=>'po_ref', 'call'=>'po_ref']);
    [$s,$d] = $yn($po !== '', $po, 'No PO reference'); $add('po','PO identified',$s,$d);
    $loc = $eff([0=>'location', 'job'=>'site_label', 'call'=>'location']);
    [$s,$d] = $yn($loc !== '', $loc, 'No inspection location'); $add('location','Inspection location',$s,$d);
    [$s,$d] = $yn(trim((string)($doc['inspection_date'] ?? '')) !== '', $doc['inspection_date'] ?? '', 'No inspection date'); $add('date','Inspection date',$s,$d);

    // --- Scope, spec, QAP ---
    $scopeOk = false;
    foreach ($tableFields as $tf) { $rows = $data[$tf['fkey']] ?? null; if (is_array($rows) && $rows) { $scopeOk = true; break; } }
    if (!$scopeOk) foreach ($fields as $f) { $lbl = strtolower(($f['label'] ?? '').' '.($f['fkey'] ?? '')); if (strpos($lbl,'scope')!==false && trim((string)($data[$f['fkey']] ?? ''))!=='') { $scopeOk = true; break; } }
    [$s,$d] = $yn($scopeOk, '', 'No scope / activities recorded'); $add('scope','Scope of inspection',$s,$d);
    $spec = $eff([0=>'standards']);
    [$s,$d] = $yn($spec !== '', $spec, 'No applicable specification / standard'); $add('spec','Applicable specification',$s,$d);
    $qapRev = trim((string)($doc['qap_rev'] ?? ''));
    $qapFiles = (!empty($doc['job_id']) && function_exists('job_qaps')) ? count(job_qaps((int)$doc['job_id'])) : 0;
    [$s,$d] = ($qapRev !== '' || $qapFiles > 0) ? ['PASS', $qapRev !== '' ? 'Rev '.$qapRev : $qapFiles.' file(s)'] : ['FAIL','No QAP/ITP rev or attachment']; $add('qap','QAP / ITP identified',$s,$d);

    // --- Activities & measurements (form-driven) ---
    // All activities completed: any table with a status/progress column must have no incomplete rows.
    $incomplete = 0; $hasProgress = false;
    foreach ($tableFields as $tf) {
        $defs = idems_table_col_defs($tf); $progKey = null;
        foreach ($defs as $ck=>$dd) { $l = strtolower($dd['label'] ?? ''); if (strpos($l,'status')!==false || strpos($l,'progress')!==false) { $progKey=$ck; break; } }
        if (!$progKey) continue; $hasProgress = true;
        foreach (($data[$tf['fkey']] ?? []) as $r) { $v = strtolower(trim((string)((array)$r)[$progKey] ?? '')); if (in_array($v, ['pending','not done','in progress','partial'], true)) $incomplete++; }
    }
    if (!$hasProgress) $add('activities','All activities completed','NA','No activity-status column');
    else { [$s,$d] = $yn($incomplete===0, 'All done', $incomplete.' activity(ies) not completed'); $add('activities','All activities completed',$s,$d); }
    // Required measurements: required number fields must be filled.
    $reqNum = array_values(array_filter($fields, fn($f) => ($f['ftype']==='number') && !empty($f['required'])));
    if (!$reqNum) $add('measurements','All required measurements','NA','No mandatory measurement fields');
    else { $miss=0; foreach ($reqNum as $f) if (trim((string)($data[$f['fkey']] ?? ''))==='') $miss++; [$s,$d]=$yn($miss===0,'',"$miss measurement(s) missing"); $add('measurements','All required measurements',$s,$d); }

    // --- Evidence ---
    $photoField = $hasField(fn($f) => ($f['ftype'] ?? '')==='photo');
    if (!$photoField) $add('photos','Required photographs','NA','No photo field on this form');
    elseif (($data[$photoField['fkey'].'__photo_denied'] ?? '')==='1') $add('photos','Required photographs','PASS','Photography denied by client/vendor');
    else { $n = count($filesByKind['photo'] ?? []); [$s,$d]=$yn($n>0, "$n photo(s)", 'No photographs attached'); $add('photos','Required photographs',$s,$d); }
    // Instrument details + calibration validity
    $instField = $hasField(fn($f) => ($f['ftype'] ?? '')==='instrument');
    $instTable = null; foreach ($tableFields as $tf) { $l=strtolower(($tf['label']??'').' '.($tf['fkey']??'')); if (strpos($l,'instrument')!==false) { $instTable=$tf; break; } }
    if (!$instField && !$instTable) { $add('instruments','Instrument details','NA','No instrument field'); $add('calibration','Calibration validity','NA','No instrument field'); }
    else {
        $instFilled = $instField ? (trim((string)($data[$instField['fkey']] ?? ''))!=='') : (is_array($data[$instTable['fkey']] ?? null) && $data[$instTable['fkey']]);
        [$s,$d] = $yn($instFilled, '', 'Instrument not recorded'); $add('instruments','Instrument details',$s,$d);
        // calibration: any 'due' date column must be on/after the inspection date
        if ($instTable) {
            $defs = idems_table_col_defs($instTable); $dueKey=null;
            foreach ($defs as $ck=>$dd) { $l=strtolower($dd['label']??''); if (strpos($l,'due')!==false && strpos($l,'calib')!==false || (strpos($l,'due')!==false && $dd['type']==='date')) { $dueKey=$ck; break; } }
            $refDate = $doc['inspection_date'] ?: date('Y-m-d'); $expired=0; $checked=0;
            if ($dueKey) foreach (($data[$instTable['fkey']] ?? []) as $r) { $dv=trim((string)((array)$r)[$dueKey] ?? ''); if ($dv==='') continue; $checked++; if (strtotime($dv) < strtotime($refDate)) $expired++; }
            if (!$dueKey || $checked===0) $add('calibration','Calibration validity','NA','No calibration-due column filled');
            else { [$s,$d]=$yn($expired===0,'In calibration',"$expired instrument(s) out of calibration"); $add('calibration','Calibration validity',$s,$d); }
        } else $add('calibration','Calibration validity','NA','No instrument table');
    }

    // --- Dispositions & points ---
    $ncrs = $docId ? ops_all("SELECT id, disposition, status FROM nonconformities WHERE report_doc_id=?", [$docId]) : [];
    if (!$ncrs) $add('ncr','NCR disposition','NA','No NCR raised on this report');
    else { $noDisp=0; foreach ($ncrs as $n) if (trim((string)($n['disposition'] ?? ''))==='') $noDisp++; [$s,$d]=$yn($noDisp===0,count($ncrs).' NCR(s) dispositioned',"$noDisp NCR(s) without disposition"); $add('ncr','NCR disposition',$s,$d); }
    $hwAll = (!empty($doc['job_id']) && function_exists('hwp_for_job')) ? hwp_for_job((int)$doc['job_id']) : [];
    if (!$hwAll) $add('holdwitness','Hold / Witness status','NA','No hold / witness points');
    else { $open=0; foreach ($hwAll as $p) if (($p['status'] ?? '')==='OPEN') $open++; [$s,$d]=$yn($open===0,'All cleared/waived',"$open point(s) still open"); $add('holdwitness','Hold / Witness status',$s,$d); }

    // --- Declaration, signature, attachments ---
    $declField = $hasField(function($f){ $l=strtolower(($f['label']??'').' '.($f['fkey']??'')); return ($f['ftype']==='checkbox') && strpos($l,'declar')!==false; });
    if (!$declField) $add('declaration','Inspector declaration','NA','No declaration field on form');
    else { $v=(string)($data[$declField['fkey']] ?? ''); [$s,$d]=$yn($v==='1'||$v==='Yes','Confirmed','Declaration not confirmed'); $add('declaration','Inspector declaration',$s,$d); }
    $sigFieldFile = !empty($filesByKind['signature']) || !empty($filesByKind['sig_inspector']);
    $insSig = function_exists('inspector_signature') ? trim((string)inspector_signature((int)($doc['inspector_id'] ?? 0))) : '';
    [$s,$d] = ($sigFieldFile || $insSig!=='') ? ['PASS', $sigFieldFile ? 'Signed on report' : 'Inspector signature on file'] : ['FAIL','No inspector signature']; $add('signature','Signature',$s,$d);
    $reqFileFields = array_values(array_filter($fields, fn($f) => in_array($f['ftype'] ?? '', ['file','signature'], true) && !empty($f['required'])));
    if (!$reqFileFields) $add('attachments','Attachments','NA','No mandatory attachment fields');
    else { $miss=0; foreach ($reqFileFields as $f){ $has=false; foreach ($files as $fl) if (($fl['field_key']??'')===$f['fkey']) { $has=true; break; } if (!$has) $miss++; } [$s,$d]=$yn($miss===0,'',"$miss required attachment(s) missing"); $add('attachments','Attachments',$s,$d); }

    // Summary — the gate opens when no applicable check has failed.
    $applicable = array_values(array_filter($checks, fn($c) => $c['status'] !== 'NA'));
    $passed = count(array_filter($applicable, fn($c) => $c['status'] === 'PASS'));
    $failed = count(array_filter($applicable, fn($c) => $c['status'] === 'FAIL'));
    return ['checks'=>$checks, 'passed'=>$passed, 'applicable'=>count($applicable), 'total'=>count($checks),
            'na'=>count($checks)-count($applicable), 'failed'=>$failed, 'ok'=>($failed===0)];
}

// ---------------------------------------------------------------------------
//  Handlers
// ---------------------------------------------------------------------------
// Document Register + a single report instance (create / view / edit / submit / finalize).
// Vendor qualification register + per-vendor profile (view / edit attributes /
// assessment history). The scored Vendor Assessment writes the score & status
// here on issue; this is where a buyer sees their approved-vendor list.
// Expediting register + management KPIs — the "Register" and "Dashboard" views of
// the expediting engine (the formal "Report" is the PDF). One row per Expediting
// Report, with its live progress, status and forecast computed deterministically.
function ops_idems_expediting($route, $method) {
    ops_require(is_master() || can('mod.idems.view') || can('mod.idems.edit'), 'You cannot view the expediting register.');
    $q = trim($_GET['q'] ?? ''); $fs = $_GET['status'] ?? '';
    [$w, $a] = scope_clause('d.office_id', 'd.sbu');
    $where = "d.deleted=0 AND d.type_code='ER' AND $w"; $args = $a;
    if ($q) { $where .= " AND (d.irn LIKE ? OR d.title LIKE ? OR d.project_name LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%"); }
    $docs = ops_all("SELECT d.*, bp.display_name vendor_disp, bp.legal_name vendor_name
        FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.vendor_id
        WHERE $where ORDER BY d.id DESC", $args);
    $secs = idems_sections_by_code('ER'); $flds = idems_fields_by_code('ER');
    $today = date('Y-m-d');
    $rows = []; $k = ['total'=>0,'on_track'=>0,'at_risk'=>0,'delayed'=>0,'late_forecast'=>0,'high_risk'=>0];
    $vperfCache = [];   // vendor track record — computed once per vendor for the risk read
    foreach ($docs as $d) {
        $data = json_decode($d['data'] ?: '[]', true); if (!is_array($data)) $data = [];
        $es = idems_expediting_status($d, $flds, $data);
        $sc = idems_score_doc($secs, $flds, $data);
        $st = $es['status']; if ($fs && $st !== $fs) continue;
        $k['total']++;
        if ($st === 'ON TRACK' || $st === 'COMPLETED') $k['on_track']++;
        elseif ($st === 'AT RISK') $k['at_risk']++;
        else $k['delayed']++;
        $ev = $es['forecast']['expeditor_variance_days'];
        if ($ev !== null && $ev > 0) $k['late_forecast']++;
        // Predictive delivery risk (Phase 6). Vendor perf cached to avoid N+1.
        $vid = (int)($d['vendor_id'] ?? 0);
        if ($vid && !array_key_exists($vid, $vperfCache)) $vperfCache[$vid] = function_exists('idems_vendor_expediting_perf') ? idems_vendor_expediting_perf($vid) : null;
        $risk = function_exists('idems_expediting_risk') ? idems_expediting_risk($d, $flds, $data, $vid ? $vperfCache[$vid] : null, $today) : null;
        if ($risk && in_array($risk['band'], ['HIGH','CRITICAL'], true)) $k['high_risk']++;
        $rows[] = ['id'=>(int)$d['id'], 'irn'=>$d['irn'], 'vendor'=>($d['vendor_disp'] ?: $d['vendor_name'] ?: ($data['vendor'] ?? '')),
                   'project'=>($d['project_name'] ?: ($data['project'] ?? '')), 'po'=>($data['po_number'] ?? $d['po_ref'] ?? ''),
                   'progress'=>$sc['overall'] ?? null, 'status'=>$st,
                   'required'=>$es['forecast']['required'], 'forecast'=>$es['forecast']['expeditor'], 'variance'=>$ev,
                   'risk'=>$risk ? $risk['band'] : null, 'risk_score'=>$risk ? (int)$risk['score'] : null,
                   'date'=>$d['issue_date'] ?: '', 'finalized'=>(int)$d['finalized']];
    }
    view('ops/idems/expediting_register', ['rows'=>$rows, 'q'=>$q, 'fs'=>$fs, 'counts'=>$k, 'today'=>$today,
        'statusOpts'=>['ON TRACK'=>'On track','AT RISK'=>'At risk','DELAYED'=>'Delayed','CRITICAL'=>'Critical','COMPLETED'=>'Completed']]);
    return true;
}

// Expediting — project consolidation view (Phase 4b). Rolls every PO-level ER up
// into a per-project delivery tree: project progress, worst status, aggregate
// delivery risk, the binding delivery date and how many POs are forecast late,
// each expandable to its PO/package children. One project, many POs, one view.
function ops_idems_expediting_projects($route, $method) {
    ops_require(is_master() || can('mod.idems.view') || can('mod.idems.edit'), 'You cannot view the expediting register.');
    $q = trim($_GET['q'] ?? '');
    [$w, $a] = scope_clause('d.office_id', 'd.sbu');
    $where = "d.deleted=0 AND d.type_code='ER' AND $w"; $args = $a;
    if ($q) { $where .= " AND (d.irn LIKE ? OR d.title LIKE ? OR d.project_name LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%"); }
    $docs = ops_all("SELECT d.*, bp.display_name vendor_disp, bp.legal_name vendor_name
        FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.vendor_id
        WHERE $where ORDER BY COALESCE(d.issue_date,d.created_at) DESC, d.id DESC", $args);
    // Prefer the joined vendor display name when the data JSON has none.
    foreach ($docs as &$d) if (empty($d['vendor_name']) && !empty($d['vendor_disp'])) $d['vendor_name'] = $d['vendor_disp'];
    unset($d);
    $projects = function_exists('idems_expediting_projects') ? idems_expediting_projects($docs) : [];
    $k = ['projects'=>count($projects), 'pos'=>0, 'at_risk_projects'=>0, 'late_pos'=>0, 'high_risk_projects'=>0];
    foreach ($projects as $p) {
        $k['pos'] += (int)$p['total_pos']; $k['late_pos'] += (int)$p['late_pos'];
        if (in_array($p['status'], ['AT RISK','DELAYED','CRITICAL'], true)) $k['at_risk_projects']++;
        if (in_array($p['risk']['band'], ['HIGH','CRITICAL'], true)) $k['high_risk_projects']++;
    }
    view('ops/idems/expediting_projects', ['projects'=>$projects, 'q'=>$q, 'counts'=>$k]);
    return true;
}

function ops_idems_vendors($route, $method) {
    $pdo = db();
    $canView = is_master() || can('mod.idems.view') || can('mod.idems.edit') || can('mod.partners.view');
    ops_require($canView, 'You cannot view the vendor register.');
    $canEdit = is_master() || can('mod.idems.edit') || can('mod.partners.edit');

    if ($route === 'vendor-profile-save' && $method === 'POST') {
        ops_require($canEdit, 'You cannot edit vendor profiles.');
        $pid = (int)($_POST['partner_id'] ?? 0);
        if ($pid) {
            $before = idems_vendor_profile($pid);
            $oldStatus = $before['approval_status'] ?? 'PROSPECT';
            $newStatus = $_POST['approval_status'] ?? $oldStatus;
            idems_vendor_profile_save($pid, [
                'vendor_type' => $_POST['vendor_type'] ?? '', 'product_category' => $_POST['product_category'] ?? '',
                'risk_class' => $_POST['risk_class'] ?? '', 'approval_status' => $newStatus,
                'notes' => $_POST['notes'] ?? '',
            ]);
            // Record a status change (or any manual action carrying a reason) on
            // the qualification timeline.
            $reason = trim((string)($_POST['status_reason'] ?? ''));
            if ($newStatus !== $oldStatus || $reason !== '')
                idems_vendor_log_status($pid, $oldStatus, $newStatus, 'MANUAL', ['reason' => $reason ?: 'Manual update']);
            if (function_exists('idems_log')) idems_log('vendor_profile', $pid, 'EDIT', ['status'=>$newStatus]);
            flash('Vendor profile saved.');
        }
        redirect('/vendor-profile?id=' . $pid);
    }

    if ($route === 'vendor-profile') {
        $pid = (int)($_GET['id'] ?? 0);
        $partner = $pid ? ops_one("SELECT * FROM business_partners WHERE id=?", [$pid]) : null;
        if (!$partner) { flash('Vendor not found.', 'error'); redirect('/vendors'); }
        $profile = idems_vendor_profile($pid);
        // Assessment history — every Vendor Assessment / Audit against this vendor.
        $history = ops_all("SELECT id, irn, type_code, status, issue_date, result, data FROM report_docs
            WHERE deleted=0 AND vendor_id=? AND type_code IN ('VASR','VAR') ORDER BY id DESC", [$pid]);
        foreach ($history as &$h) {
            $dj = json_decode($h['data'] ?: '[]', true); if (!is_array($dj)) $dj = [];
            $tc = (string)$h['type_code'];
            $sc = function_exists('idems_score_doc') ? idems_score_doc(idems_sections_by_code($tc), idems_fields_by_code($tc), $dj) : null;
            $h['score'] = $sc['overall'] ?? null; $h['band'] = $sc['band'] ?? '';
            $h['recommendation'] = (string)($dj['recommendation'] ?? '');
            $h['kind'] = $tc === 'VAR' ? 'Audit' : 'Assessment';
        }
        unset($h);
        $events = function_exists('idems_vendor_status_events') ? idems_vendor_status_events($pid) : [];
        $perf = function_exists('idems_vendor_performance') ? idems_vendor_performance($pid) : null;
        $v360 = function_exists('idems_vendor_360') ? idems_vendor_360($pid) : ['reports'=>[],'ncrs'=>[],'complaints'=>[]];
        $xperf = function_exists('idems_vendor_expediting_perf') ? idems_vendor_expediting_perf($pid) : null;
        $xrisk = function_exists('idems_vendor_delivery_risk') ? idems_vendor_delivery_risk($pid) : null;
        view('ops/idems/vendor_detail', ['partner'=>$partner, 'profile'=>$profile, 'history'=>$history, 'events'=>$events,
            'perf'=>$perf, 'v360'=>$v360, 'xperf'=>$xperf, 'xrisk'=>$xrisk, 'canEdit'=>$canEdit,
            'typeOpts'=>lk_options_or('vendor_type', []), 'catOpts'=>lk_options_or('vendor_product_category', []),
            'riskOpts'=>lk_options_or('vendor_risk_class', []), 'statusOpts'=>lk_options_or('vendor_approval_status', [])]);
        return true;
    }

    // Register — every vendor partner, with its qualification profile (LEFT JOIN so
    // vendors never yet assessed still appear as prospects).
    $q = trim($_GET['q'] ?? ''); $fs = $_GET['status'] ?? ''; $fr = $_GET['risk'] ?? ''; $ftp = $_GET['vtype'] ?? '';
    $where = "bp.is_vendor=1"; $args = [];
    if ($q)   { $where .= " AND (bp.display_name LIKE ? OR bp.legal_name LIKE ? OR bp.code LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%"); }
    if ($fs)  { $where .= " AND COALESCE(vp.approval_status,'PROSPECT')=?"; $args[] = $fs; }
    if ($fr)  { $where .= " AND vp.risk_class=?"; $args[] = $fr; }
    if ($ftp) { $where .= " AND vp.vendor_type=?"; $args[] = $ftp; }
    $rows = ops_all("SELECT bp.id, bp.code, bp.display_name, bp.legal_name,
        vp.vendor_type, vp.product_category, vp.risk_class, COALESCE(vp.approval_status,'PROSPECT') approval_status,
        vp.vendor_rating, vp.last_score, vp.last_band, vp.last_assessed_on, vp.valid_until, vp.reassess_on
        FROM business_partners bp LEFT JOIN vendor_profiles vp ON vp.partner_id=bp.id
        WHERE $where ORDER BY bp.display_name, bp.legal_name", $args);
    $today = date('Y-m-d');
    $counts = ['total'=>0,'approved'=>0,'conditional'=>0,'pending'=>0,'due'=>0];
    foreach ($rows as $r) {
        $counts['total']++;
        $st = $r['approval_status'];
        if ($st === 'APPROVED') $counts['approved']++;
        elseif ($st === 'CONDITIONAL') $counts['conditional']++;
        elseif (in_array($st, ['PROSPECT','UNDER_ASSESSMENT'], true)) $counts['pending']++;
        if (!empty($r['reassess_on']) && $r['reassess_on'] < $today && in_array($st, ['APPROVED','CONDITIONAL'], true)) $counts['due']++;
    }
    view('ops/idems/vendor_register', ['rows'=>$rows, 'q'=>$q, 'fs'=>$fs, 'fr'=>$fr, 'ftp'=>$ftp, 'today'=>$today, 'counts'=>$counts,
        'statusOpts'=>lk_options_or('vendor_approval_status', []), 'riskOpts'=>lk_options_or('vendor_risk_class', []),
        'typeOpts'=>lk_options_or('vendor_type', [])]);
    return true;
}

function ops_idems_documents($route, $method) {
    $pdo = db();
    if ($route === 'documents') {
        $q = trim($_GET['q'] ?? ''); $ft = $_GET['type'] ?? ''; $fs = $_GET['status'] ?? '';
        [$w, $a] = scope_clause('d.office_id', 'd.sbu');
        $where = "d.deleted=0 AND $w"; $args = $a;
        if ($q)  { $where .= " AND (d.irn LIKE ? OR d.title LIKE ? OR d.project_name LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%"); }
        if ($ft) { $where .= " AND d.type_code=?"; $args[] = $ft; }
        if ($fs) { $where .= " AND d.status=?"; $args[] = $fs; }
        $rows = ops_all("SELECT d.*, bp.display_name client_disp, bp.legal_name client_name, i.name inspector_name
            FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.client_id LEFT JOIN inspectors i ON i.id=d.inspector_id
            WHERE $where ORDER BY d.id DESC", $args);
        $counts = ops_one("SELECT COUNT(*) total,
            SUM(CASE WHEN status IN ('DRAFT','SUBMITTED','UNDER_REVIEW','REJECTED') THEN 1 ELSE 0 END) open_n,
            SUM(CASE WHEN status IN ('APPROVED','ISSUED') THEN 1 ELSE 0 END) issued_n
            FROM report_docs d WHERE d.deleted=0 AND $w", $a) ?: [];
        view('ops/idems/register', ['rows'=>$rows, 'q'=>$q, 'types'=>idems_types(false), 'ft'=>$ft, 'fs'=>$fs, 'counts'=>$counts]);
        return true;
    }
    // Release Note register — Release Notes only, each showing the inspection
    // report it was raised against and its release status. §releasenote
    if ($route === 'release-notes') {
        $q = trim($_GET['q'] ?? ''); $fs = $_GET['status'] ?? '';
        [$w, $a] = scope_clause('d.office_id', 'd.sbu');
        $where = "d.deleted=0 AND d.type_code='RN' AND $w"; $args = $a;
        if ($q)  { $where .= " AND (d.irn LIKE ? OR d.title LIKE ? OR d.project_name LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%"); }
        if ($fs) { $where .= " AND d.status=?"; $args[] = $fs; }
        $rows = ops_all("SELECT d.*, bp.display_name client_disp, bp.legal_name client_name, v.display_name vendor_disp, v.legal_name vendor_name
            FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.client_id LEFT JOIN business_partners v ON v.id=d.vendor_id
            WHERE $where ORDER BY d.id DESC", $args);
        // Pull the linked inspection report number (and its id, so the row can link
        // back to it) out of each RN's data JSON.
        foreach ($rows as &$r) {
            $dj = json_decode($r['data'] ?: '[]', true); if (!is_array($dj)) $dj = [];
            $r['source_irn'] = trim((string)($dj['source_irn'] ?? '')) ?: trim((string)($dj['ir_numbers'] ?? ''));
            $r['source_report_id'] = (int)($dj['source_report_id'] ?? 0);
        }
        unset($r);
        $counts = ops_one("SELECT COUNT(*) total,
            SUM(CASE WHEN status IN ('DRAFT','SUBMITTED','UNDER_REVIEW','REJECTED') THEN 1 ELSE 0 END) open_n,
            SUM(CASE WHEN status IN ('APPROVED','ISSUED') THEN 1 ELSE 0 END) issued_n
            FROM report_docs d WHERE d.deleted=0 AND d.type_code='RN' AND $w", $a) ?: [];
        view('ops/idems/release_register', ['rows'=>$rows, 'q'=>$q, 'fs'=>$fs, 'counts'=>$counts]);
        return true;
    }
    if ($route === 'document-new' || $route === 'document-edit') {
        $doc = null;
        if ($route === 'document-edit') {
            $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_GET['id'] ?? 0)]);
            if (!$doc) { http_response_code(404); view('notfound'); return true; }
            ops_require(idems_can_edit_doc($doc), 'This report is finalized and can no longer be edited.');
        } else {
            ops_require(is_master() || can('mod.idems.edit'), 'You cannot create inspection reports.');
        }
        if ($method === 'POST') {
            $b = $_POST;
            $typeId = (int)($b['report_type_id'] ?? 0);
            $type = $typeId ? ops_one("SELECT * FROM report_types WHERE id=?", [$typeId]) : null;
            if (!$type) { flash('Please choose a report type.', 'error'); redirect($doc ? '/document-edit?id='.$doc['id'] : '/document-new'); }
            $clientId = ($b['client_id'] ?? '') !== '' ? (int)$b['client_id'] : null;
            $inspectorId = ($b['inspector_id'] ?? '') !== '' ? (int)$b['inspector_id'] : null;
            $officeId = ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : (current_user()['home_office_id'] ?? null);
            // resolve a stable client code once
            $client = $clientId ? ops_one("SELECT legal_name, display_name, code FROM business_partners WHERE id=?", [$clientId]) : null;
            $clientCode = $client ? idems_client_code($client) : '';
            $fields = [
                'report_type_id'=>$typeId, 'type_code'=>$type['code'], 'title'=>trim($b['title'] ?? '') ?: $type['name'],
                'client_id'=>$clientId, 'vendor_id'=>($b['vendor_id'] ?? '')!==''?(int)$b['vendor_id']:null,
                'call_id'=>($b['call_id'] ?? '')!==''?(int)$b['call_id']:null,
                'job_id'=>($b['job_id'] ?? '')!==''?(int)$b['job_id']:null,
                'client_code'=>$clientCode, 'project_code'=>trim($b['project_code'] ?? ''), 'project_name'=>trim($b['project_name'] ?? ''),
                'office_id'=>$officeId, 'sbu'=>$b['sbu'] ?? '',
                'po_ref'=>trim($b['po_ref'] ?? ''), 'drawing_no'=>trim($b['drawing_no'] ?? ''), 'drawing_rev'=>trim($b['drawing_rev'] ?? ''),
                'qap_rev'=>trim($b['qap_rev'] ?? ''), 'standards'=>trim($b['standards'] ?? ''), 'location'=>trim($b['location'] ?? ''),
                'product_category'=>trim($b['product_category'] ?? ''), 'material_grade'=>trim($b['material_grade'] ?? ''),
                'inspector_id'=>$inspectorId, 'approver_user_id'=>($b['approver_user_id'] ?? '')!==''?(int)$b['approver_user_id']:null,
                'inspection_date'=>$b['inspection_date'] ?? '', 'result'=>$b['result'] ?? '', 'release_status'=>$b['release_status'] ?? '',
                'remarks'=>trim($b['remarks'] ?? ''),
            ];
            if ($doc) {
                $set = implode('=?, ', array_keys($fields)) . '=?';
                $args = array_values($fields); $args[] = date('c'); $args[] = $doc['id'];
                $pdo->prepare("UPDATE report_docs SET $set, updated_at=? WHERE id=?")->execute($args);
                idems_log('report_doc', $doc['id'], 'EDIT', ['irn'=>$doc['irn']]);
                flash('Report updated.');
                redirect('/document?id=' . $doc['id']);
            } else {
                // A report of the same type, for the same inspection, on the same
                // day is the same report. Filing it twice burns two IRNs — and an
                // IRN is a permanent reference that goes to the client, so the
                // second one cannot simply be deleted afterwards. This catches the
                // second click and the resend; a genuine second visit carries a
                // different date and is unaffected.
                $twin = idems_existing_twin($fields);
                if ($twin) {
                    flash($twin['irn'] . ' already covers this — same ' . Tl('report') . ' type, same '
                        . ($fields['job_id'] ? Tl('job') : Tl('client'))
                        . ($fields['inspection_date'] ? ', same inspection date' : ', created moments ago')
                        . '. Nothing was created twice; open it below to carry on.', 'warning');
                    redirect('/document?id=' . (int)$twin['id']);
                }
                // generate the IRN from the resolved fields
                [$irn, $serial] = idems_generate_irn($fields + ['inspection_date'=>$fields['inspection_date']]);
                $tok = idems_tokens_for($fields);
                $cols = array_merge(['irn','company_code','branch_code','fy_label','serial','status','rev','created_by','created_at','updated_at'], array_keys($fields));
                $vals = array_merge([$irn, $tok['{COMPANY}'], $tok['{BRANCH}'], $tok['{FY}'], $serial, 'DRAFT', 0, user_name(current_user()), date('c'), date('c')], array_values($fields));
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO report_docs (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                $id = (int)$pdo->lastInsertId();
                idems_log('report_doc', $id, 'CREATE', ['irn'=>$irn]);
                idems_log('report_doc', $id, 'IRN_GEN', ['irn'=>$irn, 'new'=>$irn]);
                // For a Vendor Assessment / Audit raised against a vendor, prefill
                // the "Customer complaints & resolution" table from the complaint
                // register, so complaints & their resolutions are part of the report.
                try {
                    $vpid = (int)($fields['vendor_id'] ?? 0);
                    $tcode = (string)ops_val("SELECT code FROM report_types WHERE id=?", [(int)($fields['report_type_id'] ?? 0)]);
                    if ($vpid && in_array($tcode, ['VASR','VAR','ER'], true)) {
                        $flds = idems_fields((int)$fields['report_type_id']);
                        $findF = function($k) use ($flds) { foreach ($flds as $ff) if (($ff['fkey'] ?? '') === $k) return $ff; return null; };
                        // Prefill whichever live-data tables this report type carries,
                        // so complaints / inspections / NCRs are reflected, not retyped.
                        $pre = [];
                        if (($cf = $findF('complaints_review')) && function_exists('idems_vendor_prefill_complaints')) { $r = idems_vendor_prefill_complaints($vpid, $cf); if ($r) $pre['complaints_review'] = $r; }
                        if (($cf = $findF('inspection_status')) && function_exists('idems_vendor_prefill_inspections')) { $r = idems_vendor_prefill_inspections($vpid, $cf); if ($r) $pre['inspection_status'] = $r; }
                        if (($cf = $findF('ncr_register')) && function_exists('idems_vendor_prefill_ncrs')) { $r = idems_vendor_prefill_ncrs($vpid, $cf); if ($r) $pre['ncr_register'] = $r; }
                        if (($cf = $findF('dispatch_readiness')) && function_exists('idems_dispatch_prefill')) { $r = idems_dispatch_prefill($cf); if ($r) $pre['dispatch_readiness'] = $r; }
                        if ($pre) $pdo->prepare("UPDATE report_docs SET data=? WHERE id=?")->execute([json_encode($pre), $id]);
                    }
                } catch (Throwable $e) {}
                flash('Report created — IRN ' . $irn . '.');
                redirect('/document?id=' . $id);
            }
        }
        // Creating against a call / job? Pull everything already known into the form.
        $pre = [];
        if (!$doc) {
            $ctx = idems_context_for((int)($_GET['call'] ?? 0), (int)($_GET['job'] ?? 0));
            if ($ctx) $pre = $ctx;
        }
        // Narrow the report-type dropdown to the deliverables this inspection was
        // actually allocated. Offering the whole catalogue is how an engineer files
        // a Final Inspection Report against a job that only owes a daily one.
        $allTypes = idems_types();
        $wantCodes = $pre['deliverables'] ?? [];
        $types = $allTypes;
        if ($wantCodes) {
            $narrow = array_values(array_filter($allTypes, fn($t) => in_array($t['code'], $wantCodes, true)));
            if ($narrow) $types = $narrow;      // never narrow to nothing
        }
        view('ops/idems/doc_form', ['doc'=>$doc, 'pre'=>$pre, 'types'=>$types, 'allTypes'=>$allTypes,
            'clients'=>ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_client=1 ORDER BY nm"),
            'vendors'=>ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_vendor=1 ORDER BY nm"),
            'inspectors'=>ops_all("SELECT id, name FROM inspectors WHERE status='ACTIVE' ORDER BY name"),
            'approvers'=>ops_all("SELECT id, first_name, last_name, username, role FROM users WHERE is_active=1 ORDER BY first_name, last_name"),
            'offices'=>ops_all("SELECT id, name FROM offices ORDER BY is_ahmedabad DESC, name"),
            'calls'=>ops_all("SELECT c.id, c.call_code, COALESCE(bp.display_name,bp.legal_name) client_nm FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id ORDER BY c.id DESC"),
            // §R5 — inspector → approver, so the form fills the Approver box the moment
            // an inspector is chosen (map wins; reporting manager is the fallback).
            'approverMap'=>idems_approver_map_json(),
            'sbuOpts'=>lk_options_or('sbu', OPS_SBUS)]);
        return true;
    }
    if ($route === 'document') {
        $doc = ops_one("SELECT d.*, bp.display_name client_disp, bp.legal_name client_name, v.display_name vendor_disp, v.legal_name vendor_name,
                i.name inspector_name, o.name office_name, rt.name type_name, rt.category type_category
            FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.client_id LEFT JOIN business_partners v ON v.id=d.vendor_id
            LEFT JOIN inspectors i ON i.id=d.inspector_id LEFT JOIN offices o ON o.id=d.office_id LEFT JOIN report_types rt ON rt.id=d.report_type_id
            WHERE d.id=? AND d.deleted=0", [(int)($_GET['id'] ?? 0)]);
        if (!$doc) { http_response_code(404); view('notfound'); return true; }
        $approver = $doc['approver_user_id'] ? ops_one("SELECT first_name, last_name, username FROM users WHERE id=?", [$doc['approver_user_id']]) : null;
        $audit = ops_all("SELECT * FROM idems_audit WHERE entity='report_doc' AND entity_id=? ORDER BY id DESC", [$doc['id']]);
        $sections = idems_sections($doc['report_type_id']);
        $fields = idems_fields($doc['report_type_id']);
        $data = json_decode($doc['data'] ?: '[]', true); if (!is_array($data)) $data = [];
        $approvals = idems_report_approvals($doc['id']);
        $curStep = idems_current_step($doc['id']);
        // AI Report Auditor — a deterministic QA pass, run automatically so the
        // reviewer sees a traffic light and plain-English issues without any action.
        $qa = function_exists('idems_qa_run') ? idems_qa_run($doc, $fields, $data, []) : null;
        // Scorecard — non-null only for report types that carry weighted fields
        // (vendor assessments, audits, scored checklists); ordinary reports skip it.
        $scorecard = function_exists('idems_score_doc') ? idems_score_doc($sections, $fields, $data) : null;
        // Optional AI advisory (LLM) suggestions — shown only when a provider is
        // configured and the reviewer has run one; cached in the session so it does
        // not re-call the model on every page view.
        $aiText = $_SESSION['idems_ai_' . $doc['id']] ?? '';
        $aiSections = ($aiText && function_exists('idems_ai_sections')) ? idems_ai_sections($aiText) : [];
        $aiOn = function_exists('ai_enabled') && ai_enabled();
        // Expediting advisory (Phase 5) — deterministic draft summary, delay/
        // forecast reads, anomalies and trend vs the previous report. Advisory only.
        $expAdvisory = (($doc['type_code'] ?? '') === 'ER' && function_exists('idems_expediting_advisory'))
            ? idems_expediting_advisory($doc, $fields, $data) : null;
        // Predictive delivery risk (Phase 6) — forward-looking risk score + factors
        // + recovery read. Advisory only.
        $expRisk = (($doc['type_code'] ?? '') === 'ER' && function_exists('idems_expediting_risk'))
            ? idems_expediting_risk($doc, $fields, $data) : null;
        // Release readiness gate (URADE §10/§55) — shown on release-type reports.
        $relEligibility = null;
        $isReleaseDoc = false; foreach ($fields as $ff) if (($ff['fkey'] ?? '') === 'disposition') { $isReleaseDoc = true; break; }
        if (!$isReleaseDoc && in_array($doc['type_code'] ?? '', ['RN','IRN'], true)) $isReleaseDoc = true;
        if ($isReleaseDoc && function_exists('urade_release_eligibility')) {
            try { $relEligibility = urade_release_eligibility($doc); } catch (Throwable $e) {}
        }
        view('ops/idems/doc_detail', ['doc'=>$doc, 'approver'=>$approver, 'audit'=>$audit, 'qa'=>$qa,
            'scorecard'=>$scorecard, 'aiSections'=>$aiSections, 'aiOn'=>$aiOn, 'expAdvisory'=>$expAdvisory, 'expRisk'=>$expRisk, 'relEligibility'=>$relEligibility,
            'sections'=>$sections, 'fields'=>$fields, 'data'=>$data, 'files'=>idems_doc_files($doc['id']), 'hasSchema'=>!empty($fields),
            'approvals'=>$approvals, 'curStep'=>$curStep, 'canAct'=>idems_can_act_step($curStep),
            'vetting'=>idems_vetting_log($doc['id']), 'canVet'=>idems_can_vet(),
            'delegateUsers'=>($curStep && idems_can_act_step($curStep)) ? ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name") : []]);
        return true;
    }
    if ($route === 'document-ai-review' && $method === 'POST') {
        // Run the optional LLM advisory review from the report page itself and
        // come straight back to it, so the suggestions appear in the QA panel.
        $doc = ops_one("SELECT d.*, bp.display_name client_disp, bp.legal_name client_name, v.display_name vendor_disp, v.legal_name vendor_name, rt.name type_name
            FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.client_id LEFT JOIN business_partners v ON v.id=d.vendor_id LEFT JOIN report_types rt ON rt.id=d.report_type_id
            WHERE d.id=? AND d.deleted=0", [(int)($_POST['id'] ?? 0)]);
        if (!$doc) { http_response_code(404); view('notfound'); return true; }
        ops_require(is_master() || can('mod.idems.edit') || can('idems.finalize'), 'You cannot run a review on this report.');
        $fx = idems_fields($doc['report_type_id']);
        $dt = json_decode($doc['data'] ?: '[]', true) ?: [];
        [$text, $err] = idems_ai_review($doc, $fx, $dt, function_exists('idems_source_docs') ? idems_source_docs($doc['id']) : []);
        if ($err) flash('AI review unavailable: ' . $err, 'warning');
        else { $_SESSION['idems_ai_' . $doc['id']] = $text; idems_log('report_doc', (int)$doc['id'], 'AI_REVIEW', ['irn'=>$doc['irn']]); flash('AI review completed — the suggestions are advisory. You remain the approving authority.'); }
        redirect('/document?id=' . $doc['id']);
        return true;
    }
    if ($route === 'document-submit' && $method === 'POST') {
        $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
        if (!$doc) { http_response_code(404); view('notfound'); return true; }
        ops_require(idems_can_edit_doc($doc), 'This report can no longer be changed.');
        // §gate — Inspection Completeness Check. A report must pass every applicable
        // mandatory check before it goes for approval. A super-admin may override a
        // failing gate, but only with a recorded reason (kept in the audit trail).
        $comp = idems_completeness_check($doc);
        if (!$comp['ok']) {
            $override = (!empty($_POST['_force_submit']) && is_master() && trim((string)($_POST['override_reason'] ?? '')) !== '');
            if (!$override) {
                $fails = array_values(array_filter($comp['checks'], fn($c)=>$c['status']==='FAIL'));
                $names = implode(', ', array_map(fn($c)=>$c['label'], array_slice($fails, 0, 6)));
                flash('Completeness check failed (' . $comp['passed'] . '/' . $comp['applicable'] . ' passed). Resolve: ' . $names . '.', 'error');
                redirect('/document?id=' . $doc['id']);
            }
            idems_log('report_doc', $doc['id'], 'GATE_OVERRIDE', ['irn'=>$doc['irn'], 'reason'=>trim((string)$_POST['override_reason']), 'field'=>$comp['failed'].' check(s) overridden']);
        }
        // Build the approval chain. Every inspector must have at least one approver.
        $n = idems_build_approval_chain($doc);
        if ($n === 0) {
            $pdo->prepare("DELETE FROM report_approvals WHERE report_doc_id=?")->execute([$doc['id']]);
            $msg = empty($doc['inspector_id'])
                ? 'This report has no inspector selected, so its approver cannot be worked out. Pick the inspector (and, if needed, the approver) on the report, then submit.'
                : 'No approver could be resolved for this report. Set this inspector’s approver under Inspection Reports → Approver mapping, or pick an approver directly on the report, then submit.';
            flash($msg, 'error');
            redirect('/document?id=' . $doc['id']);
        }
        // T10 — a submitted report should carry no blank entries: every text-like
        // field left empty is recorded as "NA" (not applicable) automatically, so
        // nobody types it by hand and no field reads as simply forgotten. Drafts
        // are left untouched; this only stamps the version that goes for approval.
        $naText = function_exists('setting_get') ? (setting_get('report_blank_fill', '') ?: 'NA') : 'NA';
        $bodyData = json_decode($doc['data'] ?: '[]', true); if (!is_array($bodyData)) $bodyData = [];
        $naChanged = false;
        foreach (idems_fields($doc['report_type_id']) as $f) {
            if (!in_array($f['ftype'], ['text', 'textarea', 'select'], true)) continue;  // leave numbers, dates, tables, media
            $k = $f['fkey'];
            if (trim((string)($bodyData[$k] ?? '')) === '') { $bodyData[$k] = $naText; $naChanged = true; }
        }
        if ($naChanged) $pdo->prepare("UPDATE report_docs SET data=? WHERE id=?")->execute([json_encode($bodyData), $doc['id']]);
        $pdo->prepare("UPDATE report_docs SET status='UNDER_REVIEW', submitted_at=?, updated_at=? WHERE id=?")->execute([date('c'), date('c'), $doc['id']]);
        idems_log('report_doc', $doc['id'], 'SUBMIT', ['irn'=>$doc['irn'], 'old'=>$doc['status'], 'new'=>'UNDER_REVIEW']);
        $cur = idems_current_step($doc['id']);
        if ($cur) idems_notify_approver($doc, $cur);
        flash('Report submitted — routed to the approval chain (' . $n . ' level' . ($n > 1 ? 's' : '') . ').');
        redirect('/document?id=' . $doc['id']);
    }
    if ($route === 'document-finalize' && $method === 'POST') {
        $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
        if (!$doc) { http_response_code(404); view('notfound'); return true; }
        ops_require(is_master() || can('idems.finalize'), 'You are not permitted to finalize / issue reports.');
        if ($doc['finalized']) { flash('This report is already finalized.', 'warning'); redirect('/document?id=' . $doc['id']); }
        // If an approval chain exists, the report must be fully approved first (master may override).
        $hasChain = (int)ops_val("SELECT COUNT(*) FROM report_approvals WHERE report_doc_id=?", [$doc['id']]);
        if ($hasChain && $doc['status'] !== 'APPROVED' && !is_master()) {
            flash('This report must be fully approved through its approval chain before it can be finalized.', 'error');
            redirect('/document?id=' . $doc['id']);
        }
        // AI Report Auditor — a CRITICAL deterministic finding blocks issue. An
        // administrator may override, but only by supplying a reason, which is
        // written to the tamper-evident audit trail. §qa-gate
        if (function_exists('idems_qa_run')) {
            try { $qa = idems_qa_run($doc); } catch (Throwable $e) { $qa = null; }   // fail-open: a QA engine error must never block a legitimate issue
            $crit = $qa ? array_values(array_filter($qa['issues'] ?? [], fn($i) => ($i['severity'] ?? '') === 'critical')) : [];
            if ($crit) {
                $reason = trim((string)($_POST['qa_override_reason'] ?? ''));
                if (!is_master() || $reason === '') {
                    $m = 'The quality check found ' . count($crit) . ' critical issue' . (count($crit) === 1 ? '' : 's') . ' that block issuing this ' . Tl('report') . ' — e.g. “' . $crit[0]['title'] . '”.';
                    $m .= is_master() ? ' To issue anyway, an administrator must supply an override reason.' : ' It must be resolved before this report can be issued.';
                    flash($m, 'error');
                    redirect('/document?id=' . $doc['id']);
                }
                idems_log('report_doc', (int)$doc['id'], 'QA_CRITICAL_OVERRIDE', ['reason' => $reason, 'issues' => implode(' | ', array_map(fn($i) => (string)$i['title'], $crit))]);
                flash('Issued with an administrator override of ' . count($crit) . ' critical quality issue' . (count($crit) === 1 ? '' : 's') . ' — recorded in the audit trail.', 'warning');
            }
        }
        // The checks that gate issuing a report belong to the inspection pack —
        // a report must not go out claiming an instrument that was out of
        // calibration (a block, never overridable: no honest reading makes a
        // measurement from an uncalibrated instrument valid), and one signed by
        // somebody not authorised for the work is a warning that also raises a
        // nonconformity. They run through the document.issue hook so that a
        // business which is NOT an accredited inspection body — the pack switched
        // off — issues its reports without them. When the pack is on, nothing
        // changes.
        $issueFire = function_exists('pack_fire') ? pack_fire('document.issue', ['doc' => $doc]) : ['block' => [], 'warn' => []];
        $issueBlock = trim(implode(' ', $issueFire['block'] ?? []));
        if ($issueBlock !== '') {
            flash($issueBlock . ' Correct the calibration record, or take the instrument off this '
                  . Tl('report') . ', then issue it.', 'error');
            redirect('/document?id=' . $doc['id']);
        }
        foreach (($issueFire['warn'] ?? []) as $sw) {
            if ($sw === '') continue;
            flash($sw . ' The report has been issued and a nonconformity raised against it.', 'warning');
            if (function_exists('ncr_create')) {
                ncr_create([
                    'source' => 'REPORT', 'source_note' => 'Report ' . $doc['irn'],
                    'report_doc_id' => (int)$doc['id'], 'job_id' => (int)($doc['job_id'] ?? 0),
                    'partner_id' => (int)($doc['client_id'] ?? 0), 'office_id' => (int)($doc['office_id'] ?? 0),
                    'title' => 'Report ' . $doc['irn'] . ' signed by somebody not authorised for the work',
                    'clause' => '6.1', 'severity' => 'MAJOR', 'description' => $sw,
                ]);
            }
        }
        $issue = $doc['issue_date'] ?: date('Y-m-d');
        $pdo->prepare("UPDATE report_docs SET finalized=1, status='ISSUED', finalized_at=?, finalized_by=?, issue_date=?, updated_at=? WHERE id=?")
            ->execute([date('c'), user_name(current_user()), $issue, date('c'), $doc['id']]);
        idems_snapshot_signatures($doc);   // freeze inspector + approver signatures onto the report
        idems_seal_content($doc['id']);    // freeze a content hash so /verify can prove it is unaltered
        idems_freeze_presentation($doc['id']); // §33 freeze the form schema + company template used, so later edits never change this issued report
        // Vendor platform — when a scored Vendor Assessment is issued against a
        // vendor, roll its score / approval status / validity forward onto that
        // vendor's qualification profile. Fail-open; never blocks issue.
        if (function_exists('idems_vendor_apply_assessment') && (int)($doc['vendor_id'] ?? 0) > 0) {
            try {
                $fresh = ops_one("SELECT * FROM report_docs WHERE id=?", [$doc['id']]);
                $sc = function_exists('idems_score_doc') ? idems_score_doc(idems_sections((int)$fresh['report_type_id']), idems_fields((int)$fresh['report_type_id']), json_decode($fresh['data'] ?? '[]', true) ?: []) : null;
                if ($sc && ($sc['overall'] ?? null) !== null) {
                    $newStatus = idems_vendor_apply_assessment($fresh, $sc);
                    if ($newStatus !== '') idems_log('report_doc', $doc['id'], 'VENDOR_QUALIFIED', ['irn'=>$doc['irn'], 'vendor_id'=>(int)$fresh['vendor_id'], 'status'=>$newStatus, 'score'=>$sc['overall']]);
                }
            } catch (Throwable $e) {}
        }
        // Vendor Audit — raise nonconformities from the audit findings so each
        // Major / Minor finding lands in the NCR/CAPA register against the vendor.
        // Fail-open; never blocks issue.
        if (function_exists('idems_raise_ncrs_from_audit') && ($doc['type_code'] ?? '') === 'VAR') {
            try {
                $freshA = ops_one("SELECT * FROM report_docs WHERE id=?", [$doc['id']]);
                $raised = idems_raise_ncrs_from_audit($freshA);
                if ($raised > 0) flash($raised . ' nonconformit' . ($raised === 1 ? 'y' : 'ies') . ' raised from the audit findings — see the NCR register.');
            } catch (Throwable $e) {}
        }
        // Raise hold / witness / review / clearance points from any activity the
        // inspector dispositioned as such. Never blocks issue.
        if (function_exists('hwp_derive_from_doc')) { try { hwp_derive_from_doc(ops_one("SELECT * FROM report_docs WHERE id=?", [$doc['id']])); } catch (Throwable $e) {} }
        if (function_exists('act_log'))
            act_log('REPORT', (int)$doc['id'], 'SYSTEM', 'Report ' . $doc['irn'] . ' issued',
                    ['auto' => 1, 'direction' => 'OUT', 'partner_id' => (int)($doc['client_id'] ?? 0) ?: null]);
        // an issued report is the best source of "how we actually word things"
        if (function_exists('learn_from_report')) { try { learn_from_report(ops_one("SELECT * FROM report_docs WHERE id=?", [$doc['id']])); } catch (Throwable $e) {} }
        idems_log('report_doc', $doc['id'], 'FINALIZE', ['irn'=>$doc['irn'], 'old'=>$doc['status'], 'new'=>'ISSUED']);
        // P2 — tell the client it is ready. A report they can see on the portal
        // but are never told about might as well not be there. Never blocks issue.
        $notified = 0;
        try { $notified = idems_notify_client_issued(ops_one("SELECT * FROM report_docs WHERE id=?", [$doc['id']])); } catch (Throwable $e) {}
        flash('Report ' . $doc['irn'] . ' finalized & issued. It is now locked (immutable).'
            . ($notified ? ' The ' . Tl('client') . ' has been notified (' . $notified . ' recipient' . ($notified === 1 ? '' : 's') . ').' : ''));
        redirect('/document?id=' . $doc['id']);
    }
    if ($route === 'document-revise' && $method === 'POST') {
        $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_POST['id'] ?? 0)]);
        if (!$doc) { http_response_code(404); view('notfound'); return true; }
        ops_require(is_master() || can('mod.idems.edit'), 'You cannot create reports.');
        [$newId, $err] = idems_revise_doc($doc);
        if ($err === 'EXISTS') { flash('A revision of this report already exists.', 'warning'); redirect('/document?id=' . $newId); }
        if ($err) { flash($err, 'error'); redirect('/document?id=' . $doc['id']); }
        flash('Revision drafted as ' . ops_val("SELECT irn FROM report_docs WHERE id=?", [$newId])
            . '. Amend it, then submit and issue it — the original stays on file, unchanged.');
        redirect('/document?id=' . $newId);
    }
    if ($route === 'document-delete' && $method === 'POST') {
        $doc = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)($_POST['id'] ?? 0)]);
        if (!$doc) { http_response_code(404); view('notfound'); return true; }
        ops_require(is_master() || can('idems.finalize'), 'You cannot delete reports.');
        // soft-delete only (Part 23) — record stays for audit
        $pdo->prepare("UPDATE report_docs SET deleted=1, updated_at=? WHERE id=?")->execute([date('c'), $doc['id']]);
        idems_log('report_doc', $doc['id'], 'DELETE', ['irn'=>$doc['irn'], 'reason'=>trim($_POST['reason'] ?? '')]);
        flash('Report moved to the recycle (soft-deleted). It stays in the audit log.');
        redirect('/documents');
    }
    return false;
}

// Report-type registry (admin adds unlimited types — Part 1).
function ops_idems_report_types($route, $method) {
    ops_require(is_master() || can('idems.type.manage') || can('master.manage'), 'You cannot manage report types.');
    $pdo = db();
    if ($method === 'POST') {
        $do = $_POST['_do'] ?? 'save';
        // One click builds the complete, ready-to-use company inspection report
        // (all sections wired). If it already exists, jump straight to its builder.
        if ($do === 'build_mgh') {
            $tid = idems_build_mgh_report();
            flash('Ready inspection report is set up — every section is wired. Review or fine-tune it here.');
            redirect('/report-builder?type=' . $tid);
        }
        // Re-run the staircase-table repair on demand (safe; only merges tables
        // whose every row has a single filled cell; skips issued reports).
        if ($do === 'repair_tables') {
            [$dc, $tc] = idems_repair_staircase_tables();
            flash($dc ? "Repaired split table rows in $dc report(s) ($tc table(s))." : 'No split table rows found — everything is already whole.');
            redirect('/report-types');
        }
        if ($do === 'del') {
            $t = ops_one("SELECT * FROM report_types WHERE id=?", [(int)($_POST['id'] ?? 0)]);
            if ($t && !$t['is_system']) { $pdo->prepare("DELETE FROM report_types WHERE id=?")->execute([$t['id']]); flash('Report type removed.'); }
            elseif ($t) { $pdo->prepare("UPDATE report_types SET active=0 WHERE id=?")->execute([$t['id']]); flash('Built-in type deactivated (kept for history).'); }
            redirect('/report-types');
        }
        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? '')); $name = trim($_POST['name'] ?? '');
        $cat = isset(lk_options_or('report_category', IDEMS_CATEGORIES)[$_POST['category'] ?? '']) ? $_POST['category'] : 'TPIA_REPORT';
        $active = !empty($_POST['active']) ? 1 : 0;
        $fmtNo = trim((string)($_POST['format_number'] ?? '')); $docCtl = trim((string)($_POST['doc_control_no'] ?? '')); $rev = trim((string)($_POST['revision'] ?? ''));
        if ($code === '' || $name === '') { flash('Code and name are required.', 'error'); redirect('/report-types'); }
        if ($id) $pdo->prepare("UPDATE report_types SET code=?, name=?, category=?, active=?, format_number=?, doc_control_no=?, revision=? WHERE id=?")->execute([$code, $name, $cat, $active, $fmtNo, $docCtl, $rev, $id]);
        else $pdo->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,format_number,doc_control_no,revision,created_by,created_at) VALUES (?,?,?,?,0,?,?,?,?,?,?)")
            ->execute([$code, $name, $cat, $active, (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_types"), $fmtNo, $docCtl, $rev, user_name(current_user()), date('c')]);
        flash('Report type saved.');
        redirect('/report-types');
    }
    $edit = ($route === 'report-type-edit') ? ops_one("SELECT * FROM report_types WHERE id=?", [(int)($_GET['id'] ?? 0)]) : null;
    view('ops/idems/report_types', ['rows'=>idems_types(false), 'edit'=>$edit]);
    return true;
}

// IRN numbering rules (Part 19) + company/serial config.
function ops_idems_numbering($method) {
    ops_require(is_master() || can('idems.type.manage'), 'You cannot change numbering rules.');
    if ($method === 'POST') {
        // Per-office pattern override — an office/branch that must number the way
        // its own clients demand keeps its own pattern (blank = use the global).
        if (($_POST['_do'] ?? '') === 'office_pattern') {
            $oid = (int)($_POST['office_id'] ?? 0);
            $pat = trim((string)($_POST['irn_format'] ?? ''));
            if ($pat !== '' && strpos($pat, '{SERIAL}') === false) { flash('An office pattern must also include {SERIAL} (or clear it to use the global one).', 'error'); redirect('/irn-rules'); }
            if ($oid) { db()->prepare("UPDATE offices SET irn_format=? WHERE id=?")->execute([$pat, $oid]); flash($pat !== '' ? 'Office numbering pattern saved.' : 'Office pattern cleared — it now uses the global format.'); }
            redirect('/irn-rules');
        }
        $fmt = trim($_POST['idems_irn_format'] ?? '');
        if ($fmt === '' || strpos($fmt, '{SERIAL}') === false) { flash('The format must include {SERIAL}.', 'error'); redirect('/irn-rules'); }
        setting_set('idems_irn_format', $fmt);
        setting_set('idems_company_code', strtoupper(trim($_POST['idems_company_code'] ?? 'MGH')));
        $w = (int)($_POST['idems_serial_width'] ?? 6); setting_set('idems_serial_width', ($w >= 3 && $w <= 10) ? $w : 6);
        flash('Numbering rules saved.');
        redirect('/irn-rules');
    }
    // a live sample using the newest client/office for illustration
    $sampleDoc = ['office_id'=>ops_val("SELECT id FROM offices ORDER BY is_ahmedabad DESC, id LIMIT 1"),
        'client_id'=>ops_val("SELECT id FROM business_partners ORDER BY id LIMIT 1"),
        'type_code'=>'IR', 'project_code'=>'P001', 'inspection_date'=>date('Y-m-d')];
    $sample = idems_collapse(strtr(str_replace('{SERIAL}', str_pad('458', idems_serial_width(), '0', STR_PAD_LEFT), idems_irn_format()), idems_tokens_for($sampleDoc)));
    view('ops/idems/numbering', ['format'=>idems_irn_format(), 'company'=>idems_company_code(), 'width'=>idems_serial_width(),
        'tokens'=>idems_available_tokens(), 'sample'=>$sample,
        'offices'=>ops_all("SELECT id, name, code, COALESCE(irn_format,'') irn_format FROM offices WHERE COALESCE(is_active,1)=1 ORDER BY name")]);
    return true;
}

// ---------------------------------------------------------------------------
//  Phase 2: no-code report builder (design the form for a report type)
// ---------------------------------------------------------------------------
function ops_idems_builder($route, $method) {
    ops_require(is_master() || can('idems.type.manage') || can('master.manage'), 'You cannot design report forms.');
    $pdo = db();
    $typeId = (int)($_GET['type'] ?? $_POST['report_type_id'] ?? 0);
    $type = $typeId ? ops_one("SELECT * FROM report_types WHERE id=?", [$typeId]) : null;
    if (!$type) { flash('Choose a report type to design.', 'error'); redirect('/report-types'); }
    if ($method === 'POST') {
        $do = $_POST['_do'] ?? '';
        // --- sections ---
        // Replace a garbled / auto-imported form with the clean inspection layout.
        if ($do === 'clean_form') {
            idems_reset_inspection_form($typeId);
            flash('Replaced with the clean inspection layout — plain labels, no cryptic tables. Fine-tune below if you like.');
            redirect('/report-builder?type=' . $typeId);
        }
        if ($do === 'section_save') {
            $sid = (int)($_POST['section_id'] ?? 0); $title = trim($_POST['title'] ?? '');
            if ($title === '') { flash('Section title required.', 'error'); redirect('/report-builder?type=' . $typeId); }
            $pgb = !empty($_POST['page_break_before']) ? 1 : 0; $keep = !empty($_POST['keep_together']) ? 1 : 0;
            if ($sid) $pdo->prepare("UPDATE report_sections SET title=?, help=?, page_break_before=?, keep_together=? WHERE id=? AND report_type_id=?")->execute([$title, trim($_POST['help'] ?? ''), $pgb, $keep, $sid, $typeId]);
            else $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,page_break_before,keep_together,sort_order) VALUES (?,?,?,?,?,?)")
                ->execute([$typeId, $title, trim($_POST['help'] ?? ''), $pgb, $keep, (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            flash('Section saved.'); redirect('/report-builder?type=' . $typeId);
        }
        // One click adds a ready-made "Scope of activities" section — a repeatable
        // table (Activity / Status / Remark) — so a report can record each activity
        // in scope and what happened to it without designing it by hand (T13). The
        // Status column's suggested values are the editable document_status master.
        if ($do === 'add_scope') {
            $exists = (int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=? AND title=?", [$typeId, 'Scope of activities']);
            if ($exists) { flash('This report type already has a Scope of activities section.', 'warning'); redirect('/report-builder?type=' . $typeId); }
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, 'Scope of activities', 'Each activity in the scope, its status and a remark.',
                    (int)ops_val("SELECT COALESCE(MIN(sort_order),10)-5 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId();
            $dispo = implode(', ', array_values(function_exists('lk_options_or') ? lk_options_or('inspection_disposition', defined('INSPECTION_DISPOSITIONS') ? INSPECTION_DISPOSITIONS : []) : ['Acceptable','Rejected','Hold']));
            $prog  = implode(', ', array_values(function_exists('lk_options_or') ? lk_options_or('activity_progress', defined('ACTIVITY_PROGRESS') ? ACTIVITY_PROGRESS : []) : ['Completed','Partial','Pending']));
            // Full ITP scope row: ITP/clause ref, sub-clause, activity, quantum of
            // check, inspection type (Witness/Review/Verify…), observation, remark,
            // disposition and progress — matching a real ITP-driven report.
            $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,table_cols,help,sort_order,col_span)
                           VALUES (?,?,?,?,?,?,?,?,2)")
                ->execute([$typeId, $secId, 'scope_activities', 'Activities', 'table',
                    "ITP / Clause No.|merge\nSub-clause\nDescription of activity\nQuantum of check\nInspection type|select|lookup:itp_inspection_type\nObservation\nRemarks\nEvidence referred\nAttached|select|Yes,No,N/A\nDisposition|select|lookup:inspection_disposition\nStatus|select|lookup:activity_progress",
                    'Inspection type: Witness / Review / Verify… Disposition: ' . $dispo . '. Status: ' . $prog . '. (All editable in Masters.)',
                    (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId])]);
            flash('Added a “Scope of activities (ITP)” section — ITP clause, sub-clause, description, quantum of check, inspection type (Witness/Review/Verify), observation, remarks and disposition.');
            redirect('/report-builder?type=' . $typeId);
        }
        // One click adds a "Reference documents" table — Document Name / Number /
        // Revision / Approval code / Date of approval — repeatable for many docs.
        if ($do === 'add_refdocs') {
            $title = 'Reference documents';
            if ((int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=? AND title=?", [$typeId, $title])) {
                flash('This report type already has a Reference documents section.', 'warning'); redirect('/report-builder?type=' . $typeId);
            }
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, $title, 'The QAP/ITP, drawings, specifications and standards the inspection was carried out against — one row per document.',
                    (int)ops_val("SELECT COALESCE(MIN(sort_order),10)-3 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,table_cols,help,sort_order,col_span)
                           VALUES (?,?,?,?,?,?,?,?,2)")
                ->execute([$typeId, $secId, 'reference_documents', 'Reference documents', 'table',
                    "Document Name\nDocument Number\nRevision No.\nApproval Code\nDate of Approval|date",
                    'Add one row per controlling document — QAP/ITP, drawing, specification, standard, customer instruction.',
                    (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId])]);
            flash('Added a “Reference documents” section — a repeatable table: Document Name, Number, Revision, Approval code and Date of approval (date picker).');
            redirect('/report-builder?type=' . $typeId);
        }
        // One click adds the header fields for previous / current hold-point status
        // and the PO status dropdown that the company formats carry.
        if ($do === 'add_holdstatus') {
            $title = 'Order & hold-point status';
            if ((int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=? AND title=?", [$typeId, $title])) {
                flash('This report type already has the Order & hold-point status section.', 'warning'); redirect('/report-builder?type=' . $typeId);
            }
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, $title, 'Status of the order and of any hold / deviation points from the previous visit.',
                    (int)ops_val("SELECT COALESCE(MIN(sort_order),10)-2 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId(); $ord = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId]);
            $mk = function($fkey,$label,$ftype,$opts='',$span=1) use ($pdo,$typeId,$secId,&$ord){ $ord+=10;
                $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,sort_order,col_span) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$typeId,$secId,$fkey,$label,$ftype,$opts,$ord,$span]); };
            $mk('po_status','P.O. status','select','lookup:po_status',1);
            $mk('prev_holdpoint_status','Status of previous hold points / deviations','textarea','',2);
            $mk('current_holdpoints','Current hold points / deviations','textarea','',2);
            flash('Added an “Order & hold-point status” section — P.O. status dropdown (Completed / Balance / Hold) plus previous &amp; current hold-point status.');
            redirect('/report-builder?type=' . $typeId);
        }
        // "Add a section → Custom table": the user names the section and defines the
        // columns — how many, each with its own field type — then a repeatable
        // table is built. This is the from-scratch path when no ready section fits.
        if ($do === 'add_custom_table') {
            $secTitle = trim((string)($_POST['sec_title'] ?? '')) ?: 'Table';
            $names = (array)($_POST['col_name'] ?? []);
            $ctypes = (array)($_POST['col_type'] ?? []);
            $copts  = (array)($_POST['col_opts'] ?? []);
            $lines = [];
            foreach ($names as $i => $nm) {
                $nm = trim((string)$nm); if ($nm === '') continue;
                $t = strtolower(trim((string)($ctypes[$i] ?? 'text'))); if ($t === 'dropdown') $t = 'select';
                if (!in_array($t, ['text','number','date','select','textarea','unit'], true)) $t = 'text';
                if ($t === 'text') { $lines[] = $nm; }
                elseif ($t === 'select') { $o = trim((string)($copts[$i] ?? '')); $lines[] = $nm . '|select' . ($o !== '' ? '|' . $o : ''); }
                else { $lines[] = $nm . '|' . $t; }
            }
            if (!$lines) { flash('Add at least one column (a name and a type) for the table.', 'error'); redirect('/report-builder?type=' . $typeId); }
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, $secTitle, 'Custom table.', (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId();
            $fkey = idems_clean_key($secTitle) ?: ('table_' . $secId);
            if ((int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey=?", [$typeId, $fkey])) $fkey .= '_' . $secId;
            $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,table_cols,sort_order,col_span)
                           VALUES (?,?,?,?,?,?,?,2)")
                ->execute([$typeId, $secId, $fkey, $secTitle, 'table', implode("\n", $lines),
                    (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId])]);
            flash('Added the “' . $secTitle . '” table with ' . count($lines) . ' column(s). Add rows on the report, or open the table to fine-tune a column.');
            redirect('/report-builder?type=' . $typeId);
        }
        // One-click "Conclusion & remarks" narrative section.
        if ($do === 'add_conclusion') {
            if ((int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=? AND title=?", [$typeId, 'Conclusion & remarks'])) {
                flash('This report type already has a Conclusion & remarks section.', 'warning'); redirect('/report-builder?type=' . $typeId);
            }
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, 'Conclusion & remarks', '', (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId(); $o = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId]);
            foreach ([['observations','Details of inspection carried out / observations'],['conclusion','Conclusion'],['general_remarks','General remarks']] as $ff) {
                $o += 10; $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,sort_order,col_span) VALUES (?,?,?,?,'textarea',?,2)")->execute([$typeId,$secId,$ff[0],$ff[1],$o]);
            }
            flash('Added a “Conclusion & remarks” section (observations, conclusion, general remarks).'); redirect('/report-builder?type=' . $typeId);
        }
        // One-click "Photographs" evidence section.
        if ($do === 'add_photos') {
            if ((int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey='photos'", [$typeId])) {
                flash('This report type already has a Photographs field.', 'warning'); redirect('/report-builder?type=' . $typeId);
            }
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, 'Photographs', 'Take or upload photos — each auto-compressed and captioned; or mark photography denied.', (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,sort_order,col_span) VALUES (?,?,?,?,'photo',?,2)")
                ->execute([$typeId,$secId,'photos','Photographs',(int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId])]);
            flash('Added a “Photographs” section — take/upload (auto-compressed), name each photo, or mark photography denied.'); redirect('/report-builder?type=' . $typeId);
        }
        // One-click "Signatures" — inspector + vendor/client representative sign-off.
        if ($do === 'add_signatures') {
            if ((int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=? AND title=?", [$typeId, 'Signatures'])) {
                flash('This report type already has a Signatures section.', 'warning'); redirect('/report-builder?type=' . $typeId);
            }
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, 'Signatures', 'The inspector signature is applied automatically at issue; add representative sign-offs here.', (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId(); $o = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId]);
            foreach ([['sign_vendor','Signature — Manufacturer / Vendor representative'],['sign_client','Signature — Client / Witness representative']] as $ff) {
                $o += 10; $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,sort_order,col_span) VALUES (?,?,?,?,'signature',?,1)")->execute([$typeId,$secId,$ff[0],$ff[1],$o]);
            }
            flash('Added a “Signatures” section — manufacturer/vendor and client representative sign-offs. (The inspector & approver signatures are applied automatically at issue.)'); redirect('/report-builder?type=' . $typeId);
        }
        // One-click "Static text / disclaimer" — a fixed paragraph that prints on
        // the report (disclaimer, standing instruction). No field to fill; the
        // author edits the wording in the field's Content box.
        if ($do === 'add_disclaimer') {
            $secId = (int)ops_val("SELECT id FROM report_sections WHERE report_type_id=? ORDER BY sort_order DESC, id DESC LIMIT 1", [$typeId]);
            if (!$secId) {
                $pdo->prepare("INSERT INTO report_sections (report_type_id,title,sort_order) VALUES (?,?,?)")
                    ->execute([$typeId, '', (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
                $secId = (int)$pdo->lastInsertId();
            }
            $default = "This report is issued on the basis of the inspection carried out at the time and place stated. It reflects the condition of the items inspected and does not relieve the manufacturer / supplier of their contractual obligations. Our liability is limited to the fee charged for this inspection.";
            $o = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId]);
            $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,sort_order,col_span) VALUES (?,?,?,?,'richtext',?,?,2)")
                ->execute([$typeId, $secId, 'disclaimer_'.substr(md5((string)$o),0,4), 'Disclaimer', $default, $o]);
            flash('Added a static “Disclaimer” block — edit the wording in the field’s Content box, or add another for standing instructions.'); redirect('/report-builder?type=' . $typeId);
        }
        // One-click "Sign-off block" — a per-role signature panel (Prepared /
        // Reviewed / Approved). Name, designation and date auto-fill from the
        // workflow actors; the on-file signature image is stamped for the inspector
        // and approver. The author can rename or add roles in the field options.
        if ($do === 'add_sigblock') {
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, 'Sign-off', 'Prepared / Reviewed / Approved — name, designation and date auto-fill from the workflow; edit the roles in the field options.', (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId();
            $o = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId]);
            $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,sort_order,col_span) VALUES (?,?,?,?,'sigblock',?,?,2)")
                ->execute([$typeId, $secId, 'signoff', 'For '.app_name(), "Prepared by\nReviewed by\nApproved by", $o]);
            flash('Added a “Sign-off” block — Prepared / Reviewed / Approved, auto-filled from the workflow. Edit the roles under the field to add or rename.'); redirect('/report-builder?type=' . $typeId);
        }
        // One click adds the identification & traceability fields every inspection
        // report must carry under ISO/IEC 17020 clause 7.4 — so a report type is
        // "17020-ready" without designing each field by hand. Things the system
        // already stamps (issuing body, unique report number, dates, inspector &
        // authorised signatory) are noted, not re-asked; only the fields an
        // inspector actually fills are added.
        if ($do === 'add_iso17020') {
            $title = 'Identification & traceability (ISO 17020)';
            $exists = (int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=? AND title=?", [$typeId, $title]);
            if ($exists) { flash('This report type already has the ISO 17020 identification section.', 'warning'); redirect('/report-builder?type=' . $typeId); }
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, $title,
                    'Mandatory identification & traceability required by ISO/IEC 17020 (7.4). The issuing body, unique report number, dates, inspector and authorised signatory are added automatically by the system — fill the rest.',
                    (int)ops_val("SELECT COALESCE(MIN(sort_order),10)-3 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId();
            // fkey, label, ftype, options, required, help
            $isoFields = [
                ['iso_object',        'Item / object inspected',                'text',     '', 1, 'What was inspected — equipment, material or item description.'],
                ['iso_object_id',     'Item identification (tag / serial / heat no.)', 'text', '', 0, 'Unique identification of the inspected item so it is traceable.'],
                ['iso_location',      'Place of inspection',                     'text',     '', 0, 'Where the inspection was carried out (works / site / address).'],
                ['iso_method',        'Inspection method / standard applied',    'textarea', '', 1, 'The method, standard or specification used (e.g. IS/ASME/EN clause).'],
                ['iso_criteria',      'Acceptance criteria',                     'textarea', '', 1, 'The criteria the result is judged against.'],
                ['iso_extent',        'Extent of inspection / sampling',         'text',     '', 0, 'How much was inspected — 100%, sample size, sampling plan.'],
                ['iso_conformity',    'Statement of conformity',                 'select',   "Conforms\nDoes not conform\nConforms with observations\nNot applicable", 1, 'The overall judgement — required by ISO 17020 when a conformity statement is given.'],
                ['iso_limitations',   'Limitations, deviations & exclusions',    'textarea', '', 0, 'Anything outside scope, any deviation from the method, or points not covered.'],
            ];
            $ins = $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,required,help,sort_order,col_span)
                                  VALUES (?,?,?,?,?,?,?,?,?,?)");
            $base = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId]);
            foreach ($isoFields as $ix => $f) {
                if ((int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey=?", [$typeId, $f[0]])) continue;
                $span = in_array($f[2], ['textarea']) ? 2 : 1;
                $ins->execute([$typeId, $secId, $f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $base + ($ix * 10), $span]);
            }
            flash('Added the ISO 17020 identification & traceability section — every mandatory field is now on this report.');
            redirect('/report-builder?type=' . $typeId);
        }
        // One click adds the "Instruments & calibration" section the engineer
        // fills on site — a ready table (Instrument type, ID/serial, calibrated-on
        // and due dates as date pickers, NABL-traceable Yes/No dropdown) plus the
        // ISO 17020 traceability disclaimer inline. No hand-building.
        if ($do === 'add_instruments') {
            $title = 'Instruments & calibration';
            $exists = (int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=? AND title=?", [$typeId, $title]);
            if ($exists) { flash('This report type already has the Instruments & calibration section.', 'warning'); redirect('/report-builder?type=' . $typeId); }
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, $title, 'Pick an instrument from the equipment register — its serial, calibration dates and traceability fill in automatically. Add one row per instrument.',
                    (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId();
            $base = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId]);
            // repeatable instrument table. The first column is master-linked to the
            // equipment register (equip:instruments) — picking an instrument auto-
            // fills its serial, calibration dates and traceability from the register.
            $cols = "Instrument|select|equip:instruments\nSr. No. / Identification number\nCalibrated on|date\nCalibrated due date|date\nNABL Traceable|select|Yes; No";
            if (!(int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey=?", [$typeId, 'instruments'])) {
                $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,table_cols,help,sort_order,col_span)
                               VALUES (?,?,?,?,?,?,?,?,2)")
                    ->execute([$typeId, $secId, 'instruments', 'Instruments used', 'table', $cols,
                        'Add one row per instrument used during the inspection.', $base]);
            }
            // ISO 17020 traceability disclaimer, shown inline under the table (label ≤200 chars).
            $disc = "All instruments used were within calibration validity. NABL Traceable = Yes means traceable to national / international standards per ISO/IEC 17020. Results apply only to the items inspected.";
            if (!(int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey=?", [$typeId, 'instruments_disclaimer'])) {
                $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,sort_order,col_span)
                               VALUES (?,?,?,?,?,?,2)")
                    ->execute([$typeId, $secId, 'instruments_disclaimer', $disc, 'note', $base + 10]);
            }
            flash('Added the “Instruments & calibration” section — a ready table (type, ID, calibrated-on & due dates, NABL traceable Yes/No) with the ISO 17020 disclaimer. Add or reorder more sections around it as you like.');
            redirect('/report-builder?type=' . $typeId);
        }
        // One click adds the PO items & inspection-quantities section — a
        // repeatable table the engineer adds one row per PO line item to.
        // Every quantity column is present so Passed / Failed / Both all work in
        // one flat table (a table cannot hide columns per row): for Passed fill
        // Passed Qty, for Failed fill Failed Qty, for Both fill both.
        if ($do === 'add_po_items') {
            $title = 'PO items & inspection';
            $exists = (int)ops_val("SELECT COUNT(*) FROM report_sections WHERE report_type_id=? AND title=?", [$typeId, $title]);
            if ($exists) { flash('This report type already has the PO items & inspection section.', 'warning'); redirect('/report-builder?type=' . $typeId); }
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, $title, 'One row per PO line item — ordered vs offered vs passed/failed quantities, with heat and serial numbers for traceability.',
                    (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $secId = (int)$pdo->lastInsertId();
            $base = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId]);
            // Full PO line item: Sr.No / Description / Size / Unit (picked once per
            // line) / PO / Offered / Passed / Rejected / Hold / Balance qty, plus
            // heat & serial for traceability.
            $cols = "PO Sr. No.|merge\nDescription as per PO\nSize\nUnit|unit\nPO Qty|number\nOffered Qty|number\nPassed Qty|number\nRejected Qty|number\nHold Qty|number\nBalance Qty|number\nHeat No.\nProduct Sr. No.";
            if (!(int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey=?", [$typeId, 'po_items'])) {
                $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,table_cols,help,sort_order,col_span)
                               VALUES (?,?,?,?,?,?,?,?,2)")
                    ->execute([$typeId, $secId, 'po_items', 'PO items inspected', 'table', $cols,
                        'One row per PO line item. Pick the Unit once against the line (nos, mtr, kg…). Balance Qty = ordered − passed to date. Heat & serial give traceability.', $base]);
            }
            flash('Added the “PO items & inspection” section — a ready multi-row table: PO Sr.No, description, size, unit, and ordered / offered / passed / rejected / hold / balance quantities, with heat &amp; serial no. Tip: change “Description as per PO” to a dropdown of the order’s items with call:po_items.');
            redirect('/report-builder?type=' . $typeId);
        }
        if ($do === 'section_del') {
            $sid = (int)($_POST['section_id'] ?? 0);
            $pdo->prepare("DELETE FROM report_sections WHERE id=? AND report_type_id=?")->execute([$sid, $typeId]);
            $pdo->prepare("UPDATE report_fields SET section_id=NULL WHERE section_id=? AND report_type_id=?")->execute([$sid, $typeId]);
            flash('Section removed (its fields moved to “unsectioned”).'); redirect('/report-builder?type=' . $typeId);
        }
        if ($do === 'section_move') {
            idems_reorder('report_sections', (int)$_POST['section_id'], $_POST['dir'] ?? 'up', 'report_type_id', $typeId);
            redirect('/report-builder?type=' . $typeId);
        }
        // --- fields ---
        if ($do === 'field_save') {
            $fid = (int)($_POST['field_id'] ?? 0);
            $fkey = idems_clean_key($_POST['fkey'] ?? $_POST['label'] ?? '');
            if ($fkey === '') { flash('Field needs a name.', 'error'); redirect('/report-builder?type=' . $typeId); }
            $ftype = isset(IDEMS_FIELD_TYPES[$_POST['ftype'] ?? '']) ? $_POST['ftype'] : 'text';
            $vals = [
                'section_id' => ($_POST['section_id'] ?? '') !== '' ? (int)$_POST['section_id'] : null,
                'fkey' => $fkey, 'label' => trim($_POST['label'] ?? ''), 'ftype' => $ftype,
                'options' => trim($_POST['options'] ?? ''), 'required' => !empty($_POST['required']) ? 1 : 0, 'hidden' => !empty($_POST['hidden']) ? 1 : 0,
                'cond_field' => trim($_POST['cond_field'] ?? ''), 'cond_op' => $_POST['cond_op'] ?? '', 'cond_val' => trim($_POST['cond_val'] ?? ''),
                'calc_expr' => trim($_POST['calc_expr'] ?? ''), 'placeholder' => trim($_POST['placeholder'] ?? ''), 'help' => trim($_POST['help'] ?? ''),
                'col_span' => max(1, min(2, (int)($_POST['col_span'] ?? 1))), 'table_cols' => trim($_POST['table_cols'] ?? ''),
                // Scoring — a field with weight 0 never contributes to a score.
                'weight' => max(0, (float)($_POST['weight'] ?? 0)),
                'max_score' => max(0, (float)($_POST['max_score'] ?? 0)),
                'score_map' => idems_score_map_to_json($_POST['score_map'] ?? ''),
            ];
            if ($fid) {
                $set = implode('=?, ', array_keys($vals)) . '=?'; $args = array_values($vals); $args[] = $fid; $args[] = $typeId;
                $pdo->prepare("UPDATE report_fields SET $set WHERE id=? AND report_type_id=?")->execute($args);
            } else {
                $vals2 = ['report_type_id'=>$typeId] + $vals + ['sort_order'=>(int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId])];
                $cols = array_keys($vals2); $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO report_fields (" . implode(',', $cols) . ") VALUES ($ph)")->execute(array_values($vals2));
            }
            flash('Field saved.'); redirect('/report-builder?type=' . $typeId);
        }
        if ($do === 'field_del') {
            $pdo->prepare("DELETE FROM report_fields WHERE id=? AND report_type_id=?")->execute([(int)($_POST['field_id'] ?? 0), $typeId]);
            flash('Field removed.'); redirect('/report-builder?type=' . $typeId);
        }
        if ($do === 'field_move') {
            idems_reorder('report_fields', (int)$_POST['field_id'], $_POST['dir'] ?? 'up', 'report_type_id', $typeId);
            redirect('/report-builder?type=' . $typeId);
        }
        // --- drag-and-drop layout designer (AJAX) -------------------------
        // The visual designer posts the whole new order in one shot: fields carry
        // their (possibly new) section and position; sections carry their order.
        $ajax = !empty($_POST['_ajax']);
        $ajaxOk = function($extra = []) use ($ajax) {
            if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => true] + $extra); exit; }
        };
        if ($do === 'field_reorder') {
            // items = [{id, section}] in the exact visual order across all sections.
            $items = json_decode((string)($_POST['items'] ?? '[]'), true);
            if (is_array($items)) {
                $ord = 0;
                foreach ($items as $it) {
                    $fid = (int)($it['id'] ?? 0); if (!$fid) continue;
                    $sec = (int)($it['section'] ?? 0); $ord += 10;
                    $pdo->prepare("UPDATE report_fields SET section_id=?, sort_order=? WHERE id=? AND report_type_id=?")
                        ->execute([$sec ?: null, $ord, $fid, $typeId]);
                }
            }
            $ajaxOk(); redirect('/report-builder?type=' . $typeId);
        }
        if ($do === 'section_reorder') {
            $ids = json_decode((string)($_POST['ids'] ?? '[]'), true);
            if (is_array($ids)) { $ord = 0; foreach ($ids as $sid) { $ord += 10; $pdo->prepare("UPDATE report_sections SET sort_order=? WHERE id=? AND report_type_id=?")->execute([$ord, (int)$sid, $typeId]); } }
            $ajaxOk(); redirect('/report-builder?type=' . $typeId);
        }
        if ($do === 'field_width') {
            $span = (int)($_POST['col_span'] ?? 1) === 2 ? 2 : 1;
            $pdo->prepare("UPDATE report_fields SET col_span=? WHERE id=? AND report_type_id=?")->execute([$span, (int)($_POST['field_id'] ?? 0), $typeId]);
            $ajaxOk(['col_span' => $span]); redirect('/report-builder?type=' . $typeId);
        }
        redirect('/report-builder?type=' . $typeId);
    }
    view('ops/idems/builder', ['type'=>$type, 'sections'=>idems_sections($typeId), 'fields'=>idems_fields($typeId),
        'editField'=>($route==='report-field-edit') ? ops_one("SELECT * FROM report_fields WHERE id=?", [(int)($_GET['id'] ?? 0)]) : null,
        'fieldTypes'=>IDEMS_FIELD_TYPES, 'condOps'=>IDEMS_COND_OPS]);
    return true;
}
// Swap sort_order with the previous/next sibling.
function idems_reorder($table, $id, $dir, $scopeCol, $scopeVal) {
    $row = ops_one("SELECT id, sort_order FROM $table WHERE id=?", [$id]);
    if (!$row) return;
    $op = $dir === 'down' ? '>' : '<'; $ord = $dir === 'down' ? 'ASC' : 'DESC';
    $nb = ops_one("SELECT id, sort_order FROM $table WHERE $scopeCol=? AND sort_order $op ? ORDER BY sort_order $ord LIMIT 1", [$scopeVal, $row['sort_order']]);
    if (!$nb) return;
    db()->prepare("UPDATE $table SET sort_order=? WHERE id=?")->execute([$nb['sort_order'], $row['id']]);
    db()->prepare("UPDATE $table SET sort_order=? WHERE id=?")->execute([$row['sort_order'], $nb['id']]);
}
function idems_clean_key($s) {
    $k = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim((string)$s)));
    return trim(preg_replace('/_+/', '_', $k), '_');
}

// ---------------------------------------------------------------------------
//  Phase 2: fill the report body (render the designed form on an instance)
// ---------------------------------------------------------------------------
// Every report must be writable by hand, even if nobody designed a form for its
// type yet. This seeds a practical STARTER form the first time one is needed —
// a manual "Inspection activities" table (Activity / Method / Result / Finding),
// an observations + conclusion narrative, and a photographs block. The admin can
// edit or replace it later; it only ever fires when the type has zero fields.
function idems_ensure_default_form($typeId) {
    $typeId = (int)$typeId;
    if (!$typeId) return false;
    if ((int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=?", [$typeId]) > 0) return false;
    $pdo = db();
    $mkSec = function($title, $help, $ord) use ($pdo, $typeId) {
        $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")->execute([$typeId, $title, $help, $ord]);
        return (int)$pdo->lastInsertId();
    };
    $fo = 0;
    $mkF = function($sid, $fkey, $label, $ftype, $opts = '', $cols = '', $span = 1) use ($pdo, $typeId, &$fo) {
        $fo += 10;
        $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,options,table_cols,col_span,sort_order) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$typeId, $sid, $fkey, $label, $ftype, $opts, $cols, $span, $fo]);
    };
    $s1 = $mkSec('Inspection activities', 'Add one row per activity carried out — the QAP clause it maps to, what you did, your observation/finding, the disposition, whether it is a hold/deviation point, and its progress. Use “+ Add row”.', 10);
    $mkF($s1, 'activities', 'Activities carried out', 'table', '',
        "QAP Clause No.\nSub-clause\nInspection Activity\nObservation / Finding\nDisposition|select|lookup:inspection_disposition\nHold / Deviation Point|select|—; Hold Point; Deviation Point; Witness Point\nStatus|select|lookup:activity_progress", 2);
    $s2 = $mkSec('Observations & conclusion', '', 20);
    $mkF($s2, 'observations', 'Details of inspection carried out / observations', 'textarea', '', '', 2);
    $mkF($s2, 'conclusion', 'Conclusion', 'textarea', '', '', 2);
    $mkF($s2, 'general_remarks', 'General remarks', 'textarea', '', '', 2);
    $s3 = $mkSec('Photographs', '', 30);
    $mkF($s3, 'photos', 'Photographs', 'photo');
    if (function_exists('idems_log')) idems_log('report_type', $typeId, 'DEFAULT_FORM', ['field' => 'seeded starter form (activities + observations + photos)']);
    return true;
}
function ops_idems_fill($route, $method) {
    $pdo = db();
    $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
    if (!$doc) { http_response_code(404); view('notfound'); return true; }
    ops_require(idems_can_edit_doc($doc), 'This report is finalized and can no longer be edited.');
    // Nobody designed a form yet? Give a usable one so the inspector can always
    // write the report by hand (manual activities, observations, photos).
    if (empty($doc['finalized'])) idems_ensure_default_form($doc['report_type_id']);
    $fields = idems_fields($doc['report_type_id']);
    if ($method === 'POST') {
        $data = json_decode($doc['data'] ?: '[]', true); if (!is_array($data)) $data = [];
        // scalar / array field values
        foreach ($fields as $f) {
            $k = $f['fkey'];
            if (in_array($f['ftype'], ['photo','file','signature'], true)) continue;   // handled below
            if ($f['ftype'] === 'table') {
                $rows = [];
                $cols = idems_table_cols($f);
                $posted = $_POST['tbl'][$k] ?? [];
                if (is_array($posted)) foreach ($posted as $r) {
                    $r = (array)$r; $has = false; $row = [];
                    foreach ($cols as $ck=>$cl) { $row[$ck] = trim((string)($r[$ck] ?? '')); if ($row[$ck] !== '') $has = true; }
                    if ($has) $rows[] = $row;
                }
                $data[$k] = $rows;
            } elseif ($f['ftype'] === 'sigblock') {
                // Per-role sign-off: name / designation / date typed by the author.
                $posted = $_POST['sb'][$k] ?? [];
                $out = [];
                if (is_array($posted)) foreach ($posted as $role => $vals) {
                    $vals = (array)$vals;
                    $out[(string)$role] = [
                        'name'  => trim((string)($vals['name']  ?? '')),
                        'desig' => trim((string)($vals['desig'] ?? '')),
                        'date'  => trim((string)($vals['date']  ?? '')),
                    ];
                }
                $data[$k] = $out;
            } elseif ($f['ftype'] === 'multiselect') {
                $data[$k] = array_values((array)($_POST['f'][$k] ?? []));
            } else {
                $data[$k] = is_array($_POST['f'][$k] ?? '') ? $_POST['f'][$k] : trim((string)($_POST['f'][$k] ?? ''));
            }
        }
        // signatures captured as data-URLs (canvas) or GPS strings arrive in $_POST['sig']/['f']
        foreach ($fields as $f) {
            if ($f['ftype'] === 'signature') {
                $sig = $_POST['sig'][$f['fkey']] ?? '';
                if ($sig && strpos($sig, 'data:image') === 0) {
                    $pdo->prepare("DELETE FROM report_files WHERE report_doc_id=? AND field_key=? AND kind='signature'")->execute([$doc['id'], $f['fkey']]);
                    $pdo->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,created_by,created_at) VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([$doc['id'], $f['fkey'], 'signature', 'signature.png', 'image/png', $sig, user_name(current_user()), date('c')]);
                }
            }
        }
        // photo / file uploads
        idems_handle_uploads($doc, $fields);
        // Per-photo captions (name below each photo) — only for files on THIS report.
        if (!empty($_POST['cap']) && is_array($_POST['cap'])) {
            foreach ($_POST['cap'] as $fid => $cap) {
                $pdo->prepare("UPDATE report_files SET caption=? WHERE id=? AND report_doc_id=?")
                    ->execute([substr(trim((string)$cap), 0, 400), (int)$fid, $doc['id']]);
            }
        }
        // "Photography denied" flag + note, per photo field.
        foreach ($fields as $f) {
            if (($f['ftype'] ?? '') !== 'photo') continue; $k = $f['fkey'];
            $data[$k.'__photo_denied'] = !empty($_POST['pdenied'][$k]) ? '1' : '';
            $data[$k.'__photo_denied_note'] = substr(trim((string)($_POST['pdenied_note'][$k] ?? '')), 0, 300);
        }
        $pdo->prepare("UPDATE report_docs SET data=?, updated_at=? WHERE id=?")->execute([json_encode($data), date('c'), $doc['id']]);
        idems_log('report_doc', $doc['id'], 'EDIT', ['irn'=>$doc['irn'], 'field'=>'body']);
        flash('Report body saved.');
        redirect('/document?id=' . $doc['id']);
    }
    $data = json_decode($doc['data'] ?: '[]', true); if (!is_array($data)) $data = [];
    // Prefill: fields the system already knows from the call / job are filled in,
    // so the inspector only types what is genuinely new. Never overwrites entries.
    $ctxDoc = ops_one("SELECT d.*, bp.display_name client_disp, bp.legal_name client_name, v.display_name vendor_disp, v.legal_name vendor_name, i.name inspector_name
        FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.client_id LEFT JOIN business_partners v ON v.id=d.vendor_id LEFT JOIN inspectors i ON i.id=d.inspector_id
        WHERE d.id=?", [$doc['id']]) ?: $doc;
    $auto = idems_autofill($ctxDoc, $fields, $data);
    foreach ($auto as $k => $v) $data[$k] = $v;
    // learned suggestions per text field (Phase 13) — offered, never applied automatically
    $sugg = [];
    if (function_exists('learn_suggestions')) {
        foreach ($fields as $f) {
            if (!in_array($f['ftype'], ['text','textarea'], true)) continue;
            $s = learn_suggestions($doc['report_type_id'], $doc['client_id'] ?? 0, $f['fkey'], 5);
            if ($s) $sugg[$f['fkey']] = $s;
        }
    }
    // Submit + approval, shown ON this screen so the inspector does not bounce
    // between pages to see where the report stands (T13).
    $approvals = function_exists('idems_report_approvals') ? idems_report_approvals($doc['id']) : [];
    $curStep   = function_exists('idems_current_step') ? idems_current_step($doc['id']) : null;
    view('ops/idems/fill', ['doc'=>$doc, 'sections'=>idems_sections($doc['report_type_id']), 'fields'=>$fields, 'data'=>$data,
        'files'=>idems_doc_files($doc['id']), 'sugg'=>$sugg, 'auto'=>$auto,
        'qaps'=>(!empty($doc['job_id']) && function_exists('job_qaps')) ? job_qaps($doc['job_id']) : [],
        'approvals'=>$approvals, 'curStep'=>$curStep]);
    return true;
}
// A table field's columns, each with a data type and (for dropdowns) options.
// Returns key => ['label','type','options'=>[...]]. Two storage forms are read:
//   · NEW  — one column per line: "Label | type | opt1;opt2;opt3"
//            (type ∈ text/number/date/select/textarea; dropdown = select)
//   · LEGACY — plain column names split on newline OR pipe, all text.
// A line is only read as the new form when its second piece is a known type,
// so every table designed before this stays exactly as it was.
function idems_table_col_defs($f) {
    $raw = trim((string)($f['table_cols'] ?? ''));
    if ($raw === '') return ['col1' => ['label' => 'Column 1', 'type' => 'text', 'options' => []]];
    $types = ['text','number','date','select','textarea','unit'];
    $looksNew = false;
    foreach (preg_split('/\r?\n/', $raw) as $ln) {
        $p = explode('|', $ln);
        if (count($p) >= 2) { foreach (array_slice($p, 1) as $seg) { $t = strtolower(trim($seg)); if ($t === 'dropdown') $t = 'select'; if (in_array($t, $types, true) || $t === 'merge') { $looksNew = true; break 2; } } }
    }
    $out = []; $seen = [];
    $add = function ($label, $type, $opts, $merge = false) use (&$out, &$seen) {
        $label = trim($label); if ($label === '') return;
        $ck = idems_clean_key($label) ?: ('c' . (count($out) + 1));
        $b = $ck; $x = 2; while (isset($seen[$ck])) { $ck = $b . '_' . $x; $x++; }
        $seen[$ck] = 1; $out[$ck] = ['label' => $label, 'type' => $type, 'options' => $opts, 'merge' => (bool)$merge];
    };
    if ($looksNew) {
        foreach (preg_split('/\r?\n/', $raw) as $ln) {
            $ln = trim($ln); if ($ln === '') continue;
            $segs = array_map('trim', explode('|', $ln));
            $label = array_shift($segs);
            // "merge" is a column modifier (vertical merge of identical values) —
            // pull it out so it isn't mistaken for the type/options segment.
            $merge = false;
            $segs = array_values(array_filter($segs, function ($s) use (&$merge) { if (strtolower($s) === 'merge') { $merge = true; return false; } return true; }));
            $type = isset($segs[0]) && $segs[0] !== '' ? strtolower($segs[0]) : 'text'; if ($type === 'dropdown') $type = 'select';
            if (!in_array($type, $types, true)) $type = 'text';
            $opts = ($type === 'select' && isset($segs[1]) && $segs[1] !== '')
                ? array_values(array_filter(array_map('trim', preg_split('/[;,]/', $segs[1])), fn($x) => $x !== '')) : [];
            // A "unit" column is a dropdown fed from the editable measurement-units
            // master — resolve it to a select so every renderer treats it uniformly.
            if ($type === 'unit') {
                $type = 'select';
                $opts = function_exists('lk_options_or') ? array_values(lk_options_or('measurement_units', defined('MEASUREMENT_UNITS') ? MEASUREMENT_UNITS : []))
                                                          : (defined('MEASUREMENT_UNITS') ? array_values(MEASUREMENT_UNITS) : []);
            }
            $add($label, $type, $opts, $merge);
        }
    } else {
        foreach (preg_split('/\r?\n|\|/', $raw) as $c) { $add($c, 'text', [], false); }
    }
    if (!$out) $out = ['col1' => ['label' => 'Column 1', 'type' => 'text', 'options' => [], 'merge' => false]];
    return $out;
}
// Backward-compatible: key => label (used by the PDF and Word-fill paths).
function idems_table_cols($f) {
    $out = [];
    foreach (idems_table_col_defs($f) as $ck => $d) $out[$ck] = $d['label'];
    return $out;
}
// ---- Sign-off block (per-role signature component) -------------------------
// A "sigblock" field carries one or more sign-off roles (Prepared by / Reviewed
// by / Approved by …), each printed as a labelled signature box with name,
// designation and date. The role list lives in field_options (one per line); the
// filled-in name/designation/date live in the doc data as data[fkey][role].
function idems_sigblock_roles($f) {
    $raw = trim((string)($f['options'] ?? ''));
    $roles = [];
    foreach (preg_split('/\r?\n/', $raw) as $ln) { $ln = trim($ln); if ($ln !== '' && stripos($ln, '=') !== 0) $roles[] = $ln; }
    // strip an "opt=" style prefix if the author reused options syntax; keep plain labels
    $roles = array_values(array_filter(array_map(fn($r) => trim(preg_replace('/^[a-z_]+\s*=\s*/i', '', $r)), $roles)));
    return $roles ?: ['Prepared by', 'Reviewed by', 'Approved by'];
}
// Merge the stored per-role values with what the workflow already knows (the
// inspector for prepared/reviewed-type roles, the approver for approved-type
// roles) and the on-file signature image — so a sign-off block auto-fills.
function idems_sigblock_rows($f, $data, $sigs = []) {
    $stored = $data[$f['fkey']] ?? [];
    if (!is_array($stored)) $stored = [];
    $ins = $sigs['inspector'] ?? []; $ap = $sigs['approver'] ?? [];
    $rows = [];
    foreach (idems_sigblock_roles($f) as $role) {
        $s = is_array($stored[$role] ?? null) ? $stored[$role] : [];
        $lc = strtolower($role);
        $sys = null;
        if (strpos($lc, 'approv') !== false || strpos($lc, 'author') !== false || strpos($lc, 'endors') !== false) $sys = $ap;
        elseif (preg_match('/prepar|review|inspect|witness|carried|verif/', $lc)) $sys = $ins;
        $name  = trim((string)($s['name']  ?? '')) ?: trim((string)($sys['name']  ?? ''));
        $desig = trim((string)($s['desig'] ?? '')) ?: trim((string)($sys['desig'] ?? ''));
        $date  = trim((string)($s['date']  ?? ''));
        $rows[] = ['role'=>$role, 'name'=>$name, 'desig'=>$desig, 'date'=>$date, 'img'=>$sys['img'] ?? ''];
    }
    return $rows;
}
// Smart image compression: scale down oversized photos and re-encode as JPEG,
// preserving readable clarity. Returns [bytes, mime, note]. Non-images pass through.
function idems_compress_image($bytes, $mime, $maxDim = 1600, $quality = 82) {
    if (strpos((string)$mime, 'image/') !== 0) return [$bytes, $mime, ''];
    if (!function_exists('imagecreatefromstring')) return [$bytes, $mime, ''];
    $im = @imagecreatefromstring($bytes);
    if (!$im) return [$bytes, $mime, ''];
    $w = imagesx($im); $h = imagesy($im);
    $scale = ($w > $maxDim || $h > $maxDim) ? $maxDim / max($w, $h) : 1;
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);       // flatten transparency onto white
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagejpeg($dst, null, $quality); $out = ob_get_clean();
    imagedestroy($im); imagedestroy($dst);
    if ($out === false || $out === '') return [$bytes, $mime, ''];
    // keep the original if compression didn't actually help and no resize happened
    if ($scale === 1 && strlen($out) >= strlen($bytes)) return [$bytes, $mime, ''];
    return [$out, 'image/jpeg', $scale < 1 ? "resized to {$nw}×{$nh}" : 'recompressed'];
}
// Pull the capture time out of a JPEG's EXIF, if present.
function idems_exif_taken($bytes) {
    if (!function_exists('exif_read_data')) return '';
    $tmp = tempnam(sys_get_temp_dir(), 'ex');
    if ($tmp === false || file_put_contents($tmp, $bytes) === false) return '';
    $ex = @exif_read_data($tmp); @unlink($tmp);
    $d = $ex['DateTimeOriginal'] ?? ($ex['DateTime'] ?? '');
    if (!$d) return '';
    $ts = strtotime(preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $d));
    return $ts ? date('c', $ts) : '';
}
// Supporting documents attached straight to the evidence gallery, outside any
// field the report format defines. Same compression, duplicate guard and audit
// trail as a template upload — they simply file under their own heading.
// Write the trust facts onto a freshly-stored evidence row and add it to the
// hash chain. Kept here, beside the two upload paths, so neither can gain a
// third sibling that quietly skips it.
//
// The received time is OURS. The device's clock is attacker-controlled and, far
// more often, simply wrong — so it is recorded for comparison and never used as
// the time something happened.
function idems_stamp_trust($fileId, $facts) {
    if (!$fileId) return;
    if ($facts && function_exists('chain_append')) {
        db()->prepare("UPDATE report_files SET exif_lat=?, exif_lon=?, up_lat=?, up_lon=?, up_acc=?,
                       geo_source=?, received_at=?, clock_skew=?, flags=? WHERE id=?")
            ->execute([$facts['exif_lat'], $facts['exif_lon'], $facts['up_lat'], $facts['up_lon'], $facts['up_acc'],
                       $facts['geo_source'], date('c'), (int)$facts['clock_skew'], $facts['flags'], $fileId]);
    }
    if (function_exists('chain_append')) chain_append($fileId);
}

const EVIDENCE_SUPPORTING_KEY = '_supporting';
function idems_evidence_attach($doc, $caption = '') {
    if (empty($_FILES['doc']['name'])) { flash('Choose a file to attach.', 'error'); return; }
    $pdo = db();
    $names = (array)$_FILES['doc']['name']; $tmp = (array)$_FILES['doc']['tmp_name'];
    $types = (array)$_FILES['doc']['type']; $errs = (array)$_FILES['doc']['error'];
    $added = 0; $dupes = 0; $big = 0;
    for ($i = 0; $i < count($names); $i++) {
        if (($errs[$i] ?? 1) !== 0 || !is_uploaded_file($tmp[$i])) continue;
        $bytes = @file_get_contents($tmp[$i]);
        if ($bytes === false) continue;
        if (strlen($bytes) > 12 * 1024 * 1024) { $big++; continue; }
        $origLen = strlen($bytes);
        $mime = $types[$i] ?: 'application/octet-stream';
        $taken = (strpos($mime, 'image/') === 0) ? idems_exif_taken($bytes) : '';
        // Where the CAMERA was, read out of the file itself — which survives the
        // drive home and the evening spent writing the report. The browser's
        // location is kept too, separately, and is never mistaken for it.
        //
        // READ BEFORE COMPRESSING. idems_compress_image() re-encodes through GD,
        // and GD writes a clean JPEG — no EXIF, so no GPS. Doing this two lines
        // later silently threw away the location of every photograph, and the
        // screens went on saying "not in the photograph" as though the phone had
        // never recorded one.
        $gf = function_exists('evidence_geo_facts')
            ? evidence_geo_facts($bytes, $mime, (string)($_POST['gps_supporting'] ?? ''), (int)($doc['job_id'] ?? 0))
            : null;
        [$bytes, $mime, ] = idems_compress_image($bytes, $mime);
        $sha = sha1($bytes);
        if (ops_val("SELECT COUNT(*) FROM report_files WHERE report_doc_id=? AND sha1=?", [$doc['id'], $sha])) { $dupes++; continue; }
        $pdo->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,gps,sha1,taken_at,bytes,orig_bytes,caption,created_by,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$doc['id'], EVIDENCE_SUPPORTING_KEY, strpos($mime, 'image/') === 0 ? 'photo' : 'file',
                substr($names[$i], 0, 255), $mime, 'data:' . $mime . ';base64,' . base64_encode($bytes),
                '', $sha, ($gf && $gf['exif_at'] !== '') ? $gf['exif_at'] : ($taken ?: date('c')),
                strlen($bytes), $origLen, substr($caption, 0, 255),
                user_name(current_user()), date('c')]);
        idems_stamp_trust((int)$pdo->lastInsertId(), $gf);
        $added++;
    }
    if ($added) idems_log('report_doc', $doc['id'], 'EVIDENCE', ['irn'=>$doc['irn'], 'new'=>$added . ' supporting document(s)']);
    $notes = [];
    if ($added) $notes[] = $added . ' document(s) attached';
    if ($dupes) $notes[] = $dupes . ' already on this ' . Tl('report');
    if ($big)   $notes[] = $big . ' over the 12 MB limit';
    flash($notes ? ucfirst(implode(', ', $notes)) . '.' : 'Nothing was attached.', $added ? 'success' : 'error');
}
function idems_handle_uploads($doc, $fields) {
    if (empty($_FILES['upl'])) return;
    $pdo = db(); $added = 0; $dupes = 0; $saved = 0;
    foreach ($fields as $f) {
        if (!in_array($f['ftype'], ['photo','file'], true)) continue;
        $k = $f['fkey'];
        $names = $_FILES['upl']['name'][$k] ?? null;
        if (!$names) continue;
        $tmp = $_FILES['upl']['tmp_name'][$k]; $types = $_FILES['upl']['type'][$k]; $errs = $_FILES['upl']['error'][$k];
        $names = (array)$names; $tmp = (array)$tmp; $types = (array)$types; $errs = (array)$errs;
        for ($i = 0; $i < count($names); $i++) {
            if (($errs[$i] ?? 1) !== 0 || !is_uploaded_file($tmp[$i])) continue;
            $bytes = @file_get_contents($tmp[$i]); if ($bytes === false || strlen($bytes) > 12*1024*1024) continue;
            $origLen = strlen($bytes);
            $mime = $types[$i] ?: 'application/octet-stream';
            $taken = ($f['ftype'] === 'photo') ? idems_exif_taken($bytes) : '';
            // Same rule as the supporting-evidence path above: read the location
            // out of the ORIGINAL bytes, because compression strips EXIF.
            $gfPre = function_exists('evidence_geo_facts')
                ? evidence_geo_facts($bytes, $mime, trim($_POST['gps'][$k] ?? ''), (int)($doc['job_id'] ?? 0)) : null;
            [$bytes, $mime, ] = idems_compress_image($bytes, $mime);
            $sha = sha1($bytes);
            // duplicate guard — same content already on this report
            if (ops_val("SELECT COUNT(*) FROM report_files WHERE report_doc_id=? AND sha1=?", [$doc['id'], $sha])) { $dupes++; continue; }
            $b64 = 'data:' . $mime . ';base64,' . base64_encode($bytes);
            $gps = trim($_POST['gps'][$k] ?? '');
            $gf = $gfPre;
            $pdo->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,gps,sha1,taken_at,bytes,orig_bytes,created_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$doc['id'], $k, $f['ftype'], substr($names[$i], 0, 255), $mime, $b64, $gps, $sha,
                    ($gf && $gf['exif_at'] !== '') ? $gf['exif_at'] : ($taken ?: date('c')),
                    strlen($bytes), $origLen, user_name(current_user()), date('c')]);
            idems_stamp_trust((int)$pdo->lastInsertId(), $gf);
            $added++; $saved += max(0, $origLen - strlen($bytes));
        }
    }
    if ($added || $dupes) {
        idems_log('report_doc', $doc['id'], 'EVIDENCE', ['irn'=>$doc['irn'], 'new'=>$added . ' file(s)' . ($dupes ? ", $dupes duplicate(s) skipped" : '')]);
        $msg = $added . ' file(s) attached';
        if ($saved > 1024) $msg .= ' · ' . round($saved/1024) . ' KB saved by compression';
        if ($dupes) $msg .= ' · ' . $dupes . ' duplicate(s) skipped';
        flash($msg . '.');
    }
}
// Stream a stored attachment.
function ops_idems_file($method) {
    $f = ops_one("SELECT rf.*, d.deleted FROM report_files rf JOIN report_docs d ON d.id=rf.report_doc_id WHERE rf.id=?", [(int)($_GET['id'] ?? 0)]);
    if (!$f || $f['deleted']) { http_response_code(404); echo 'Not found'; return true; }
    $data = (string)$f['data'];
    if (strpos($data, 'base64,') !== false) $data = base64_decode(substr($data, strpos($data, 'base64,') + 7));
    send_uploaded_file($data, $f['file_name'] ?: 'file', $f['mime'] ?? '');
    return true;
}

// ===========================================================================
//  §R1-D  QAP (Quality Assurance Plan) documents attached to a job/call
//  One or many per PO line item. Attach only — never parsed. Visible to the
//  inspector while writing, and carried for traceability.
// ===========================================================================
// QAPs on a job (metadata only — no file bytes).
function job_qaps($jobId) {
    return ops_all("SELECT id, job_id, po_line, file_name, mime, note, uploaded_by, uploaded_at
                    FROM job_qaps WHERE job_id=? ORDER BY id", [(int)$jobId]);
}
// Upload one or more QAP files against a job.
function ops_job_qap_upload($method) {
    $jobId = (int)($_POST['job_id'] ?? $_GET['job_id'] ?? 0);
    $j = $jobId ? ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]) : null;
    if (!$j) { flash('Job not found.', 'error'); redirect('/jobs'); }
    ops_require((function_exists('can') && (can('ops.job.edit') || can('idems.report.write'))) || (function_exists('is_master') && is_master()),
        'You cannot attach QAP documents.');
    if ($method !== 'POST') redirect('/job?id=' . $jobId);
    $poLine = trim($_POST['po_line'] ?? '');
    $note   = trim($_POST['note'] ?? '');
    $names = $_FILES['qap']['name'] ?? [];
    if (!is_array($names)) { $names = [$names]; $_FILES['qap']['tmp_name'] = [$_FILES['qap']['tmp_name']]; $_FILES['qap']['type'] = [$_FILES['qap']['type'] ?? '']; }
    $n = 0; $skipped = [];
    $ins = db()->prepare("INSERT INTO job_qaps (job_id,po_line,file_name,mime,data,note,uploaded_by,uploaded_at) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($names as $i => $nm) {
        if ($nm === '' || empty($_FILES['qap']['tmp_name'][$i])) continue;
        $bytes = file_get_contents($_FILES['qap']['tmp_name'][$i]);
        if ($bytes === false) { $skipped[] = $nm . ' (unreadable)'; continue; }
        if (strlen($bytes) > 16 * 1024 * 1024) { $skipped[] = $nm . ' (over 16 MB)'; continue; }
        $mime = $_FILES['qap']['type'][$i] ?: 'application/octet-stream';
        $ins->execute([$jobId, $poLine, substr($nm, 0, 255), $mime,
            'data:' . $mime . ';base64,' . base64_encode($bytes), $note,
            function_exists('user_name') ? user_name(current_user()) : '', date('c')]);
        $n++;
    }
    flash($n ? ($n . ' QAP file(s) attached.' . ($skipped ? ' Skipped: ' . implode(', ', $skipped) : ''))
             : ('Nothing uploaded.' . ($skipped ? ' Skipped: ' . implode(', ', $skipped) : '')), $n ? 'success' : 'error');
    redirect('/job?id=' . $jobId);
}
// Stream a QAP file (inline so the inspector can read it while writing).
function ops_job_qap_download() {
    $f = ops_one("SELECT * FROM job_qaps WHERE id=?", [(int)($_GET['id'] ?? 0)]);
    if (!$f) { http_response_code(404); echo 'Not found'; return true; }
    $data = (string)$f['data'];
    if (strpos($data, 'base64,') !== false) $data = base64_decode(substr($data, strpos($data, 'base64,') + 7));
    send_uploaded_file($data, $f['file_name'] ?: 'qap', $f['mime'] ?? '');
    return true;
}
// Remove a QAP file from a job.
function ops_job_qap_del($method) {
    $f = ops_one("SELECT * FROM job_qaps WHERE id=?", [(int)($_POST['id'] ?? 0)]);
    if (!$f) { flash('QAP not found.', 'error'); redirect('/jobs'); }
    ops_require((function_exists('can') && can('ops.job.edit')) || (function_exists('is_master') && is_master()),
        'You cannot remove QAP documents.');
    db()->prepare("DELETE FROM job_qaps WHERE id=?")->execute([(int)$f['id']]);
    flash('QAP removed.');
    redirect('/job?id=' . (int)$f['job_id']);
}

// ===========================================================================
//  Phase 3: workflow & approvals
// ===========================================================================
const IDEMS_APPROVER_KINDS = ['INSPECTOR_MAP'=>"Inspector's mapped approver", 'REPORTS_TO'=>"Inspector's reporting manager", 'USER'=>'A specific person', 'ROLE'=>'Anyone with a role'];
const IDEMS_APPR_STATUS = ['PENDING'=>'Pending', 'APPROVED'=>'Approved', 'REJECTED'=>'Rejected', 'SENTBACK'=>'Sent back', 'DELEGATED'=>'Delegated'];

// The effective approver user for an inspector on a date (temp cover wins in its window).
function idems_inspector_approver($inspectorId, $onDate = null) {
    if (!$inspectorId) return null;
    $onDate = $onDate ?: date('Y-m-d');
    $m = ops_one("SELECT * FROM idems_approver_map WHERE inspector_id=? AND active=1", [(int)$inspectorId]);
    if (!$m) {
        // fall back to the inspector's reporting manager if no explicit map
        $rt = ops_val("SELECT reports_to_id FROM inspectors WHERE id=?", [(int)$inspectorId]);
        return $rt ? (int)$rt : null;
    }
    if (!empty($m['temp_user_id']) && $m['temp_from'] && $m['temp_to'] && $onDate >= $m['temp_from'] && $onDate <= $m['temp_to']) return (int)$m['temp_user_id'];
    return !empty($m['approver_user_id']) ? (int)$m['approver_user_id'] : null;
}
// §R5 — inspector_id => effective approver user id, for pre-filling the report's
// Approver box on the form (mapped approver first, reporting manager as fallback).
function idems_approver_map_json() {
    $out = [];
    try {
        foreach (ops_all("SELECT id FROM inspectors WHERE status='ACTIVE'") as $i) {
            $a = idems_inspector_approver((int)$i['id']);
            if ($a) $out[(int)$i['id']] = (int)$a;
        }
    } catch (Throwable $e) {}
    return $out;
}
// Resolve a rule/step to a concrete approver user id (null for ROLE / unresolved).
function idems_resolve_approver($kind, $ruleUserId, $doc) {
    switch ($kind) {
        case 'USER': return $ruleUserId ? (int)$ruleUserId : null;
        case 'INSPECTOR_MAP': return idems_inspector_approver($doc['inspector_id'] ?? 0, $doc['inspection_date'] ?? null);
        case 'REPORTS_TO':
            $rt = !empty($doc['inspector_id']) ? ops_val("SELECT reports_to_id FROM inspectors WHERE id=?", [(int)$doc['inspector_id']]) : null;
            return $rt ? (int)$rt : null;
        default: return null; // ROLE
    }
}
// Build the approval chain for a report. Returns the number of *actionable* steps
// (a step is actionable if it resolves to a user or targets a role). 0 → no approver.
function idems_build_approval_chain($doc) {
    $pdo = db();
    $pdo->prepare("DELETE FROM report_approvals WHERE report_doc_id=?")->execute([$doc['id']]);
    $rules = ops_all("SELECT * FROM idems_approval_rules WHERE active=1
        AND (report_type_code='' OR report_type_code=?)
        AND (office_id IS NULL OR office_id=?)
        AND (client_id IS NULL OR client_id=?)
        AND (sbu='' OR sbu=?)
        ORDER BY level, sort_order, id", [$doc['type_code'], $doc['office_id'] ?: 0, $doc['client_id'] ?: 0, $doc['sbu'] ?: '']);
    $steps = [];
    // An approver chosen directly on the report wins — it is an explicit, per-report
    // decision, so honouring it stops the "no approver, map it" refusal when one was
    // in fact picked on the form. Rules / the inspector map remain the fallback.
    if (!empty($doc['approver_user_id'])) {
        $steps[] = ['level'=>1, 'kind'=>'REPORT_PICK', 'role'=>'', 'user'=>(int)$doc['approver_user_id'], 'sla'=>24];
    } elseif ($rules) {
        foreach ($rules as $r) {
            $uid = idems_resolve_approver($r['approver_kind'], $r['approver_user_id'], $doc);
            $steps[] = ['level'=>(int)$r['level'], 'kind'=>$r['approver_kind'], 'role'=>$r['approver_role'] ?? '', 'user'=>$uid, 'sla'=>(int)$r['sla_hours']];
        }
    } else {
        // default: single-level to the inspector's mapped approver (or reporting manager)
        $uid = idems_inspector_approver($doc['inspector_id'] ?? 0, $doc['inspection_date'] ?? null);
        $steps[] = ['level'=>1, 'kind'=>'INSPECTOR_MAP', 'role'=>'', 'user'=>$uid, 'sla'=>24];
    }
    // §R5 — if no step resolved to a real person (a rule matched but pointed at
    // nobody, or the inspector had no map row yet), fall back to the inspector's
    // mapped approver, then their reporting manager, so a configured approver
    // always drives the chain instead of the "no approver" refusal.
    $hasUser = false;
    foreach ($steps as $s) { if ($s['user'] || $s['role']) { $hasUser = true; break; } }
    if (!$hasUser) {
        $fb = idems_inspector_approver($doc['inspector_id'] ?? 0, $doc['inspection_date'] ?? null);
        if ($fb) $steps = [['level'=>1, 'kind'=>'INSPECTOR_MAP', 'role'=>'', 'user'=>(int)$fb, 'sla'=>24]];
    }
    $actionable = 0;
    $ins = $pdo->prepare("INSERT INTO report_approvals (report_doc_id,level,approver_kind,approver_role,approver_user_id,resolved_user_id,status,sla_due,created_at) VALUES (?,?,?,?,?,?,'PENDING',?,?)");
    foreach ($steps as $s) {
        if ($s['user'] || $s['role']) $actionable++;
        $sla = $s['sla'] > 0 ? date('c', strtotime('+' . $s['sla'] . ' hours')) : '';
        $ins->execute([$doc['id'], $s['level'], $s['kind'], $s['role'], $s['user'] ?: null, $s['user'] ?: null, $sla, date('c')]);
    }
    return $actionable;
}
function idems_report_approvals($docId) {
    return ops_all("SELECT a.*, u.first_name, u.last_name, u.username FROM report_approvals a LEFT JOIN users u ON u.id=a.resolved_user_id WHERE a.report_doc_id=? ORDER BY a.level, a.id", [(int)$docId]);
}
function idems_current_step($docId) {
    return ops_one("SELECT * FROM report_approvals WHERE report_doc_id=? AND status='PENDING' ORDER BY level, id LIMIT 1", [(int)$docId]);
}
function idems_can_act_step($step) {
    if (!$step || ($step['status'] ?? '') !== 'PENDING') return false;
    if (is_master()) return true;
    $uid = (int)(current_user()['id'] ?? 0);
    if (!empty($step['delegated_to']) && (int)$step['delegated_to'] === $uid) return true;
    if (!empty($step['resolved_user_id']) && (int)$step['resolved_user_id'] === $uid) return true;
    if (!empty($step['approver_role']) && user_role() === $step['approver_role']) return true;
    if (empty($step['resolved_user_id']) && empty($step['approver_role']) && can('idems.finalize')) return true;
    return false;
}
function idems_approver_email($userId) { return $userId ? (string)ops_val("SELECT email FROM users WHERE id=? AND is_active=1", [(int)$userId]) : ''; }
function idems_notify_approver($doc, $step) {
    $to = idems_approver_email($step['resolved_user_id'] ?? 0);
    if (!$to && !empty($step['approver_role'])) {
        $rows = ops_all("SELECT email FROM users WHERE role=? AND email<>'' AND is_active=1", [$step['approver_role']]);
        $to = implode(',', array_filter(array_column($rows, 'email')));
    }
    if (!$to) return;
    $body = "A report awaits your approval.\n\nIRN: {$doc['irn']}\nType: {$doc['type_code']}\nTitle: " . ($doc['title'] ?: '—') . "\n\n"
        . "Open it in the system → Documents → {$doc['irn']} to approve, send back or reject.\n\n" . app_name();
    ops_mail($to, "Approval required: {$doc['irn']}", $body, '', 'idems_approval');
}

// ---- Handler: act on an approval step (approve / reject / send-back / delegate) ----
function ops_idems_approve($method) {
    if ($method !== 'POST') redirect('/documents');
    $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_POST['id'] ?? 0)]);
    if (!$doc) { http_response_code(404); view('notfound'); return true; }
    $step = idems_current_step($doc['id']);
    ops_require(idems_can_act_step($step), 'You are not the current approver for this report.');
    $decision = $_POST['decision'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    $pdo = db();
    if ($decision === 'approve') {
        $pdo->prepare("UPDATE report_approvals SET status='APPROVED', acted_by=?, acted_at=?, remarks=? WHERE id=?")
            ->execute([user_name(current_user()), date('c'), $remarks, $step['id']]);
        idems_log('report_doc', $doc['id'], 'APPROVE', ['irn'=>$doc['irn'], 'field'=>'level '.$step['level'], 'reason'=>$remarks]);
        $next = idems_current_step($doc['id']);
        if ($next) { db()->prepare("UPDATE report_docs SET updated_at=? WHERE id=?")->execute([date('c'), $doc['id']]); idems_notify_approver($doc, $next); flash('Approved. Routed to the next approver.'); }
        else {
            $pdo->prepare("UPDATE report_docs SET status='APPROVED', approved_at=?, approved_by=?, updated_at=? WHERE id=?")->execute([date('c'), user_name(current_user()), date('c'), $doc['id']]);
            // learn from the wording that made it through approval (suggestions only)
            if (function_exists('learn_from_report')) { try { learn_from_report(ops_one("SELECT * FROM report_docs WHERE id=?", [$doc['id']])); } catch (Throwable $e) {} }
            flash('Report fully approved — it can now be finalized & issued.');
        }
    } elseif ($decision === 'reject') {
        if ($remarks === '') { flash('A remark is mandatory when rejecting.', 'error'); redirect('/document?id=' . $doc['id']); }
        $pdo->prepare("UPDATE report_approvals SET status='REJECTED', acted_by=?, acted_at=?, remarks=? WHERE id=?")->execute([user_name(current_user()), date('c'), $remarks, $step['id']]);
        $pdo->prepare("UPDATE report_docs SET status='REJECTED', updated_at=? WHERE id=?")->execute([date('c'), $doc['id']]);
        idems_log('report_doc', $doc['id'], 'REJECT', ['irn'=>$doc['irn'], 'reason'=>$remarks]);
        flash('Report rejected and returned to the inspector.');
    } elseif ($decision === 'sendback') {
        if ($remarks === '') { flash('A remark is mandatory when sending back for correction.', 'error'); redirect('/document?id=' . $doc['id']); }
        $pdo->prepare("UPDATE report_approvals SET status='SENTBACK', acted_by=?, acted_at=?, remarks=? WHERE id=?")->execute([user_name(current_user()), date('c'), $remarks, $step['id']]);
        $pdo->prepare("UPDATE report_docs SET status='DRAFT', updated_at=? WHERE id=?")->execute([date('c'), $doc['id']]);
        idems_log('report_doc', $doc['id'], 'SENDBACK', ['irn'=>$doc['irn'], 'reason'=>$remarks]);
        flash('Sent back to the inspector for correction.');
    } elseif ($decision === 'delegate') {
        $to = (int)($_POST['delegate_to'] ?? 0);
        if (!$to) { flash('Choose a person to delegate to.', 'error'); redirect('/document?id=' . $doc['id']); }
        $pdo->prepare("UPDATE report_approvals SET delegated_to=?, remarks=? WHERE id=?")->execute([$to, $remarks, $step['id']]);
        idems_log('report_doc', $doc['id'], 'DELEGATE', ['irn'=>$doc['irn'], 'new'=>(string)$to, 'reason'=>$remarks]);
        idems_notify_approver($doc, ['resolved_user_id'=>$to, 'irn'=>$doc['irn']] + $doc);
        flash('Approval delegated.');
    }
    redirect('/document?id=' . $doc['id']);
    return true;
}

// The vetting / debriefing log for a report (newest first).
function idems_vetting_log($docId) {
    return ops_all("SELECT * FROM report_vetting WHERE report_doc_id=? ORDER BY id DESC", [(int)$docId]);
}
// Who may vet / debrief a report — a senior reviewer (finalizer) or a master.
function idems_can_vet() { return is_master() || can('idems.finalize'); }
// ---- Handler: technical vetting / debriefing (distinct from approval) ----
function ops_idems_vet($method) {
    if ($method !== 'POST') redirect('/documents');
    $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_POST['id'] ?? 0)]);
    if (!$doc) { http_response_code(404); view('notfound'); return true; }
    ops_require(idems_can_vet(), 'You are not permitted to vet or debrief reports.');
    if (!empty($doc['finalized'])) { flash('This report is already issued — vetting is closed.', 'warning'); redirect('/document?id=' . $doc['id']); }
    $action = strtoupper(trim((string)($_POST['vet_action'] ?? '')));
    if (!isset(IDEMS_VET_STATUS[$action])) { flash('Choose a vetting action.', 'error'); redirect('/document?id=' . $doc['id']); }
    $note = trim((string)($_POST['note'] ?? ''));
    if ($action === 'RETURNED' && $note === '') { flash('A note is mandatory when returning a report for correction.', 'error'); redirect('/document?id=' . $doc['id']); }
    $pdo = db();
    $stage = ($action === 'DEBRIEFED') ? 'DEBRIEF' : 'VET';
    $pdo->prepare("INSERT INTO report_vetting (report_doc_id,stage,action,note,acted_by,acted_at) VALUES (?,?,?,?,?,?)")
        ->execute([$doc['id'], $stage, $action, $note, user_name(current_user()), date('c')]);
    $pdo->prepare("UPDATE report_docs SET vet_status=?, vet_at=?, vet_by=?, updated_at=? WHERE id=?")
        ->execute([$action, date('c'), user_name(current_user()), date('c'), $doc['id']]);
    // Returning for correction routes the report back to the inspector as a draft.
    if ($action === 'RETURNED' && in_array($doc['status'], IDEMS_OPEN_STATES, true)) {
        $pdo->prepare("UPDATE report_docs SET status='DRAFT' WHERE id=?")->execute([$doc['id']]);
    }
    idems_log('report_doc', $doc['id'], 'VET_' . $action, ['irn'=>$doc['irn'], 'reason'=>$note]);
    flash(['VETTED'=>'Report vetted — cleared for approval.', 'RETURNED'=>'Report returned to the inspector for correction.', 'DEBRIEFED'=>'Debrief recorded.'][$action] ?? 'Vetting recorded.');
    redirect('/document?id=' . $doc['id']);
    return true;
}

// ---- Handler: per-inspector approver mapping ----
function ops_idems_approver_map($method) {
    ops_require(is_master() || can('idems.type.manage') || can('users.manage.global'), 'You cannot manage approver mapping.');
    $pdo = db();
    if ($method === 'POST') {
        $iid = (int)($_POST['inspector_id'] ?? 0);
        $ap = ($_POST['approver_user_id'] ?? '') !== '' ? (int)$_POST['approver_user_id'] : null;
        $tp = ($_POST['temp_user_id'] ?? '') !== '' ? (int)$_POST['temp_user_id'] : null;
        if ($iid) {
            $ex = ops_one("SELECT id FROM idems_approver_map WHERE inspector_id=?", [$iid]);
            if ($ex) $pdo->prepare("UPDATE idems_approver_map SET approver_user_id=?, temp_user_id=?, temp_from=?, temp_to=?, active=1, updated_at=? WHERE id=?")
                ->execute([$ap, $tp, $_POST['temp_from'] ?? '', $_POST['temp_to'] ?? '', date('c'), $ex['id']]);
            else $pdo->prepare("INSERT INTO idems_approver_map (inspector_id,approver_user_id,temp_user_id,temp_from,temp_to,active,updated_at) VALUES (?,?,?,?,?,1,?)")
                ->execute([$iid, $ap, $tp, $_POST['temp_from'] ?? '', $_POST['temp_to'] ?? '', date('c')]);
            flash('Approver mapping saved.');
        }
        redirect('/approver-map');
    }
    $rows = ops_all("SELECT i.id inspector_id, i.name inspector_name, i.emp_code, m.approver_user_id, m.temp_user_id, m.temp_from, m.temp_to,
            au.first_name af, au.last_name al, au.username au_name, tu.first_name tf, tu.last_name tl, tu.username tu_name
        FROM inspectors i LEFT JOIN idems_approver_map m ON m.inspector_id=i.id
        LEFT JOIN users au ON au.id=m.approver_user_id LEFT JOIN users tu ON tu.id=m.temp_user_id
        WHERE i.status='ACTIVE' ORDER BY i.name");
    view('ops/idems/approver_map', ['rows'=>$rows, 'users'=>ops_all("SELECT id, first_name, last_name, username, role FROM users WHERE is_active=1 ORDER BY first_name, last_name")]);
    return true;
}

// ---- Handler: approval rules (configurable chain) ----
function ops_idems_approval_rules($route, $method) {
    ops_require(is_master() || can('idems.type.manage'), 'You cannot manage approval rules.');
    $pdo = db();
    if ($method === 'POST') {
        if (($_POST['_do'] ?? '') === 'del') { $pdo->prepare("DELETE FROM idems_approval_rules WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]); flash('Rule removed.'); redirect('/idems-approval-rules'); }
        $id = (int)($_POST['id'] ?? 0);
        $vals = [
            'name'=>trim($_POST['name'] ?? ''), 'active'=>!empty($_POST['active'])?1:0,
            'report_type_code'=>trim($_POST['report_type_code'] ?? ''), 'office_id'=>($_POST['office_id'] ?? '')!==''?(int)$_POST['office_id']:null,
            'client_id'=>($_POST['client_id'] ?? '')!==''?(int)$_POST['client_id']:null, 'sbu'=>trim($_POST['sbu'] ?? ''),
            'level'=>max(1,(int)($_POST['level'] ?? 1)), 'approver_kind'=>isset(IDEMS_APPROVER_KINDS[$_POST['approver_kind'] ?? ''])?$_POST['approver_kind']:'INSPECTOR_MAP',
            'approver_user_id'=>($_POST['approver_user_id'] ?? '')!==''?(int)$_POST['approver_user_id']:null, 'approver_role'=>trim($_POST['approver_role'] ?? ''),
            'sla_hours'=>max(0,(int)($_POST['sla_hours'] ?? 24)),
        ];
        if ($id) { $set=implode('=?, ',array_keys($vals)).'=?'; $args=array_values($vals); $args[]=$id; $pdo->prepare("UPDATE idems_approval_rules SET $set WHERE id=?")->execute($args); }
        else { $vals2=$vals+['sort_order'=>(int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM idems_approval_rules"),'created_at'=>date('c')]; $cols=array_keys($vals2); $ph=implode(',',array_fill(0,count($cols),'?')); $pdo->prepare("INSERT INTO idems_approval_rules (".implode(',',$cols).") VALUES ($ph)")->execute(array_values($vals2)); }
        flash('Approval rule saved.'); redirect('/idems-approval-rules');
    }
    $edit = ($route === 'idems-approval-rule-edit') ? ops_one("SELECT * FROM idems_approval_rules WHERE id=?", [(int)($_GET['id'] ?? 0)]) : null;
    view('ops/idems/approval_rules', ['rows'=>ops_all("SELECT r.*, o.name office_name, bp.display_name client_name FROM idems_approval_rules r LEFT JOIN offices o ON o.id=r.office_id LEFT JOIN business_partners bp ON bp.id=r.client_id ORDER BY r.level, r.sort_order, r.id"),
        'edit'=>$edit, 'types'=>idems_types(false), 'offices'=>ops_all("SELECT id, name FROM offices ORDER BY name"),
        'clients'=>ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_client=1 ORDER BY nm"),
        'users'=>ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name"), 'kinds'=>IDEMS_APPROVER_KINDS, 'sbuOpts'=>lk_options_or('sbu', OPS_SBUS)]);
    return true;
}

// ---- Cron: SLA auto-escalation of pending approvals ----
function idems_run_sla_escalations() {
    $now = date('c'); $sent = 0;
    $steps = ops_all("SELECT a.*, d.irn, d.type_code, d.office_id, d.inspector_id FROM report_approvals a JOIN report_docs d ON d.id=a.report_doc_id
        WHERE a.status='PENDING' AND a.escalated=0 AND a.sla_due<>'' AND a.sla_due<? AND d.deleted=0 AND d.status='UNDER_REVIEW'", [$now]);
    foreach ($steps as $s) {
        // only escalate the *current* (lowest pending) step of each doc
        $cur = idems_current_step($s['report_doc_id']);
        if (!$cur || (int)$cur['id'] !== (int)$s['id']) continue;
        $approver = idems_approver_email($s['resolved_user_id'] ?? 0);
        $mgr = function_exists('manager_emails') ? manager_emails() : '';
        $to = $mgr ?: $approver;
        if (!$to) continue;
        $body = "Approval overdue (past SLA).\n\nIRN: {$s['irn']}\nType: {$s['type_code']}\nApproval level: {$s['level']}\n\n"
            . "This report has been waiting for approval beyond its SLA. Please act or reassign.\n\n" . app_name();
        ops_mail($to, "OVERDUE approval: {$s['irn']}", $body, $approver, 'idems_sla');
        db()->prepare("UPDATE report_approvals SET escalated=1 WHERE id=?")->execute([$s['id']]);
        $sent++;
    }
    return $sent;
}

// ===========================================================================
//  Phase 4: automatic signatures + report PDF + controlled timestamps
// ===========================================================================
// Decode a stored signature (data-URL or raw) to JPEG bytes for the PDF.
function idems_sig_jpeg($stored) {
    $stored = (string)$stored;
    if ($stored === '') return '';
    $raw = strpos($stored, 'base64,') !== false ? base64_decode(substr($stored, strpos($stored, 'base64,') + 7)) : $stored;
    [$jpg, ] = signature_to_jpeg($raw);
    return $jpg;
}
function user_signature($userId) { return $userId ? (string)ops_val("SELECT signature FROM users WHERE id=?", [(int)$userId]) : ''; }
function inspector_signature($inspectorId) {
    if (!$inspectorId) return '';
    $sig = (string)ops_val("SELECT signature FROM inspectors WHERE id=?", [(int)$inspectorId]);
    // The inspector record's signature is often blank because a person sets their
    // signature under "My signature", which saves to their USER profile. Bridge it:
    // fall back to the user linked to this inspector (by inspector_id, then email).
    if (trim($sig) === '') {
        $sig = (string)ops_val("SELECT signature FROM users WHERE inspector_id=? AND COALESCE(signature,'')<>'' ORDER BY id LIMIT 1", [(int)$inspectorId]);
    }
    if (trim($sig) === '') {
        $email = trim((string)ops_val("SELECT email FROM inspectors WHERE id=?", [(int)$inspectorId]));
        if ($email !== '') $sig = (string)ops_val("SELECT signature FROM users WHERE LOWER(email)=LOWER(?) AND COALESCE(signature,'')<>'' LIMIT 1", [$email]);
    }
    return $sig;
}

// ---- Self-service "My signature" (any logged-in user) ----
function ops_idems_my_signature($method) {
    ops_require((bool)current_user(), 'Please log in.');
    $u = current_user();
    if ($method === 'POST') {
        $sig = $_POST['sig'] ?? '';
        if (($_POST['_do'] ?? '') === 'clear') { db()->prepare("UPDATE users SET signature='' WHERE id=?")->execute([$u['id']]); flash('Signature cleared.'); redirect('/my-signature'); }
        // uploaded image wins over the canvas
        if (!empty($_FILES['sigfile']['tmp_name']) && is_uploaded_file($_FILES['sigfile']['tmp_name'])) {
            $bytes = file_get_contents($_FILES['sigfile']['tmp_name']);
            if ($bytes !== false && strlen($bytes) < 2*1024*1024) $sig = 'data:' . ($_FILES['sigfile']['type'] ?: 'image/png') . ';base64,' . base64_encode($bytes);
        }
        if ($sig && strpos($sig, 'data:image') === 0) {
            db()->prepare("UPDATE users SET signature=? WHERE id=?")->execute([$sig, $u['id']]);
            // If this user is linked to an inspector record, save it there too so it
            // appears on reports where they are the inspector (not just approver).
            if (!empty($u['inspector_id'])) db()->prepare("UPDATE inspectors SET signature=? WHERE id=?")->execute([$sig, (int)$u['inspector_id']]);
            idems_log('user', $u['id'], 'SIGNATURE_SET', ['field'=>'own signature']);
            flash('Your signature has been saved. It will be added automatically to reports you sign or approve.');
        } else flash('No signature captured — draw in the box or upload an image.', 'error');
        redirect('/my-signature');
    }
    view('ops/idems/my_signature', ['sig'=>$u['signature'] ?? '']);
    return true;
}

// ---------------------------------------------------------------------------
//  Release Note footer — the structured block a Release / Discrepancy Note
//  carries below the body: what kind of report it is, the despatch disposition,
//  any accepted deviation, the source inspection-report number(s), an
//  identification / location / inspected-by / signature grid, and the standing
//  liability disclaimer. The disclaimer and the note label are settings, so a
//  company words them its own way (the default mirrors the common TPI wording).
// ---------------------------------------------------------------------------
function idems_release_note_defaults() {
    $disc = function_exists('setting_get') ? setting_get('release_note_disclaimer', '') : '';
    if (!$disc) $disc = 'This inspection has been carried out to the best of our knowledge & our responsibility is limited to the exercise of reasonable care. This Release / Discrepancy Note reflects our findings at the time & place of inspection and is not intended to relieve the seller from their contractual obligations.';
    $label = function_exists('setting_get') ? setting_get('release_note_label', '') : '';
    if (!$label) $label = 'Release / Discrepancy Note';
    return ['disclaimer' => $disc, 'label' => $label];
}
// Pull the "items / products offered" table out of a source report's data, so a
// Release Note can carry the very same table. Prefers a table whose label/key
// looks like items/products/PO; otherwise the first table on the report.
function idems_rn_items_from_source($fields, $data) {
    $first = null;
    foreach ($fields as $f) {
        if (($f['ftype'] ?? '') !== 'table') continue;
        $rows = $data[$f['fkey']] ?? null; if (!is_array($rows) || !$rows) continue;
        $defs = function_exists('idems_table_col_defs') ? idems_table_col_defs($f) : [];
        $cols = []; foreach ($defs as $ck => $d) $cols[$ck] = $d['label'] ?? $ck;
        $out = ['cols' => array_values($cols), 'rows' => []];
        foreach ($rows as $r) { $r = (array)$r; $line = []; foreach ($cols as $ck => $lbl) $line[] = (string)($r[$ck] ?? ''); $out['rows'][] = $line; }
        if ($first === null) $first = $out;
        $lbl = strtolower(($f['label'] ?? '') . ' ' . ($f['fkey'] ?? ''));
        if (strpos($lbl, 'item') !== false || strpos($lbl, 'product') !== false || strpos($lbl, 'offered') !== false || strpos($lbl, 'po') !== false) return $out;
    }
    return $first;
}
// Render the Release Note block onto an open PDF. Fired for RN / IRN documents.
function idems_release_block($p, $doc, $lh, $band) {
    $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) $data = [];
    $ml = $p->ml; $right = $p->right(); $cw = $p->contentW();
    $def = idems_release_note_defaults();

    // --- Products / items offered (carried from the inspection report) ---
    $items = $data['rn_items'] ?? null;
    if (is_array($items) && !empty($items['rows']) && !empty($items['cols'])) {
        $cols = array_map('strval', $items['cols']); $nc = max(1, count($cols)); $colw = $cw / $nc;
        $p->gap(6); $p->needSpace(30);
        $p->line('Products / items offered', 10, true, 13, $band);
        $drawHead = function() use ($p, $ml, $cols, $colw) {
            $hy = $p->y; $p->rectFill($ml, $hy, $p->contentW(), 12, [235,238,245]); $ci = 0;
            foreach ($cols as $cl) { $p->y = $hy + 3; $p->text($ml + $ci*$colw + 2, $cl, 7.5, true, [60,60,60]); $ci++; }
            $p->y = $hy + 13;
        };
        $drawHead();
        foreach ($items['rows'] as $r) {
            $r = array_values((array)$r); $cells = []; $lines = 1;
            for ($i = 0; $i < $nc; $i++) { $w = $p->wrap((string)($r[$i] ?? ''), 8, $colw - 4); if (count($w) > 4) $w = array_slice($w, 0, 4); if (!$w) $w = ['']; $cells[] = $w; $lines = max($lines, count($w)); }
            $rowH = $lines*10 + 2;
            if ($p->needSpace($rowH)) $drawHead();
            $ry = $p->y; $ci = 0;
            foreach ($cells as $w) { for ($j = 0; $j < count($w); $j++) { $p->y = $ry + $j*10; $p->text($ml + $ci*$colw + 2, $w[$j], 8); } $ci++; }
            $p->y = $ry + $rowH; $p->lineAt($ml, $p->y, $right, $p->y, [235,235,235]);
        }
        $p->gap(4);
    }

    // --- Remarks / disposition lines ---
    $p->gap(4); $p->needSpace(60); $p->hr($band); $p->gap(4);
    $p->line('Remarks', 10.5, true, 13, $band);
    $kind = trim((string)($data['rn_kind'] ?? '')) ?: 'Stage / Final';
    $p->line('This is a ' . $kind . ' Inspection Report.', 9, false, 12);
    $disp = trim((string)($data['rn_disposition'] ?? '')) ?: 'Inspected Quantity is Rejected / Passed Quantity is Cleared for dispatch.';
    foreach ($p->wrap($disp, 9, $cw) as $ln) { $p->needSpace(11); $p->line($ln, 9, false, 12); }
    $dev = trim((string)($data['rn_deviation'] ?? ''));
    $p->line('Deviation accepted (if any): ' . ($dev !== '' ? $dev : '—'), 9, false, 12);
    $irns = trim((string)($data['rn_ir_numbers'] ?? ($data['source_irn'] ?? '')));
    $p->line('Inspection Report Number(s): ' . ($irns !== '' ? $irns : '—'), 9, false, 12);

    // --- Identification grid ---
    $repName = trim((string)($lh['name'] ?? '')) ?: 'Representative';
    $gcols = ['Inspection Identification', 'Location of Identification', 'Inspected By', 'Signature of ' . $repName];
    $gw = $cw / 4;
    $p->gap(4); $p->needSpace(40);
    $hy = $p->y; $p->rectFill($ml, $hy, $cw, 13, [235,238,245]); $ci = 0;
    foreach ($gcols as $cl) { foreach ($p->wrap($cl, 7.5, $gw - 4) as $j => $t) { $p->y = $hy + 2 + $j*8; $p->text($ml + $ci*$gw + 2, $t, 7.5, true, [60,60,60]); } $ci++; }
    $p->y = $hy + 20;
    $idrows = $data['rn_identification'] ?? [];
    if (!is_array($idrows) || !$idrows) $idrows = [['ident'=>'', 'location'=>'', 'inspected_by'=>'']];
    foreach ($idrows as $r) {
        $r = (array)$r;
        $vals = [(string)($r['ident'] ?? ''), (string)($r['location'] ?? ''), (string)($r['inspected_by'] ?? ''), ''];
        $cells = []; $lines = 1;
        foreach ($vals as $v) { $w = $p->wrap($v, 8, $gw - 4); if (!$w) $w = ['']; $cells[] = $w; $lines = max($lines, count($w)); }
        $rowH = max(24, $lines*10 + 8);   // keep room for a wet signature
        if ($p->needSpace($rowH)) { $p->y += 2; }
        $ry = $p->y; $ci = 0;
        foreach ($cells as $w) { for ($j = 0; $j < count($w); $j++) { $p->y = $ry + $j*10; $p->text($ml + $ci*$gw + 2, $w[$j], 8); } $ci++; }
        $p->y = $ry + $rowH; $p->lineAt($ml, $p->y, $right, $p->y, [220,220,220]);
    }
    // column separators for the grid look
    for ($i = 1; $i < 4; $i++) { $x = $ml + $i*$gw; }

    // --- Disclaimer ---
    $p->gap(6); $p->needSpace(24);
    foreach ($p->wrap($def['disclaimer'], 8, $cw) as $ln) { $p->needSpace(10); $p->line($ln, 8, false, 10, [90,90,90]); }
    $p->gap(2);
}

// Subtle status colour for a badge/pill: returns [background rgb, foreground rgb]
// for a status word. Restrained industrial tints — green for a good outcome,
// amber for a pending/hold one, red for a failed/rejected one, neutral grey for
// anything else. Not a bright dashboard palette. §status
function idems_status_palette($text) {
    $t = strtolower(trim((string)$text));
    $has = function($needles) use ($t) { foreach ($needles as $n) if (strpos($t, $n) !== false) return true; return false; };
    if ($t === 'n/a' || $t === 'na' || $t === 'not applicable') return [[237,238,240], [90,90,90]];
    if ($has(['not conform','does not conform','non-conform','nonconform','not released','reject','fail','denied','withheld','not ok','defect'])) return [[254,226,226], [153,27,27]];
    if ($has(['accept','released','release','clear','complete','conform','pass','satisfactory','approved','closed','ok','yes','done'])) return [[226,242,231], [21,101,52]];
    if ($has(['hold','pending','partial','balance','in-process','in process','open','await','offered','wip','review','progress','draft'])) return [[254,243,199], [146,64,14]];
    return [[237,238,240], [80,80,80]];
}

// Given a measured build (page count + where each section started/ended), decide
// the section index to force a page break before so a sparse final page is filled
// more evenly. Returns -1 to leave the natural pagination alone. Only a two-page
// report with a notably short second page is rebalanced; anything else is left as
// is (and the driver rejects a rebuild that would spill to a third page). §paginate
function idems_balance_break($r) {
    if (($r['pages'] ?? 1) !== 2) return -1;
    $secs = $r['secStarts'] ?? []; if (count($secs) < 2) return -1;
    $top = $r['top']; $usable = max(1, $r['usable']);
    $p1bottom = $top;
    foreach ($secs as $s) { if ((int)($s['endPage'] ?? ($s['startPage'] ?? 0)) === 0) $p1bottom = max($p1bottom, $s['end'] ?? $s['start']); }
    $h1 = $p1bottom - $top;
    $h2 = (($r['finalPage'] ?? 0) >= 1) ? ($r['finalY'] - $top) : 0;
    if ($h1 <= 0 || $h2 <= 0) return -1;
    if ($h2 >= 0.60 * $h1) return -1;                       // already reasonably balanced
    $target = $top + ($h1 + $h2) / 2;                       // page 1 should end near here
    $bestK = -1; $bestD = PHP_FLOAT_MAX;
    foreach ($secs as $i => $s) {
        if ($i === 0) continue;                             // never orphan the first section
        if ((int)($s['startPage'] ?? 0) !== 0) continue;    // only sections that begin on page 1
        if (($s['start'] - $top) < 0.30 * $usable) continue; // keep page 1 from becoming too empty
        $d = abs($s['start'] - $target);
        if ($d < $bestD) { $bestD = $d; $bestK = $i; }
    }
    return $bestK;
}

// §snapshot — a small set of at-a-glance KPI cards for the top of a report, so a
// reviewer reads the headline status without scanning the body. Each card is
// ['label','value','sub','tone'] with tone in ok/warn/bad/neutral/muted. The set
// is chosen by report type: an expediting report shows progress/status/forecast/
// dispatch; a scored report shows score/band/coverage/recommendation; an
// inspection report shows result/release. Returns [] when there is nothing
// worth snapshotting (so the row simply doesn't print). Deterministic — no LLM.
function idems_report_kpis($doc, $sections, $fields, $data) {
    $code = strtoupper((string)($doc['type_code'] ?? ''));
    $num  = function($v){ return rtrim(rtrim(number_format((float)$v,1),'0'),'.'); };
    // Pull the first non-empty value for any of the given field keys, mapped
    // through its options so a stored code reads as its label.
    $fieldVal = function($keys) use ($fields,$data) {
        foreach ($fields as $f) {
            if (!in_array(strtolower((string)$f['fkey']), $keys, true)) continue;
            $v = $data[$f['fkey']] ?? ''; if (is_array($v)) $v = implode(', ', $v);
            $v = trim((string)$v); if ($v === '') continue;
            if (in_array($f['ftype'] ?? '', ['select','radio','unit'], true)) { $o = idems_field_options($f); $v = (string)($o[$v] ?? $v); }
            return $v;
        }
        return '';
    };
    $recTone = function($t){ $t=strtolower(trim((string)$t));
        if (strpos($t,'not')!==false || strpos($t,'reject')!==false || strpos($t,'disqual')!==false || strpos($t,'fail')!==false) return 'bad';
        if (strpos($t,'condition')!==false || strpos($t,'hold')!==false || strpos($t,'provisional')!==false || strpos($t,'re-')!==false || strpos($t,'requal')!==false) return 'warn';
        if (strpos($t,'approv')!==false || strpos($t,'qualif')!==false || strpos($t,'accept')!==false || strpos($t,'recommend')!==false) return 'ok';
        return 'neutral';
    };
    $cards = [];

    // Expediting report — progress / status / forecast / dispatch snapshot.
    if ($code === 'ER') {
        $sc = function_exists('idems_score_doc') ? idems_score_doc($sections,$fields,$data) : null;
        $st = function_exists('idems_expediting_status') ? idems_expediting_status($doc,$fields,$data) : null;
        $dr = function_exists('idems_expediting_dispatch_readiness') ? idems_expediting_dispatch_readiness($fields,$data) : null;
        if ($sc && $sc['overall'] !== null) {
            $pct = (float)$sc['overall'];
            $cards[] = ['label'=>'Overall progress','value'=>$num($pct).'%','sub'=>idems_progress_band($pct),
                'tone'=>($pct>=90?'ok':($pct>=50?'neutral':($pct>0?'warn':'muted')))];
        }
        if ($st) {
            $stt = (string)$st['status']; $ms = $st['milestones'];
            $tone = in_array($stt,['ON TRACK','COMPLETED'],true) ? 'ok' : ($stt==='AT RISK' ? 'warn' : 'bad');
            $cards[] = ['label'=>'Status','value'=>ucwords(strtolower($stt)),
                'sub'=>($ms['total']>0 ? $ms['complete'].'/'.$ms['total'].' milestones' : ''),'tone'=>$tone];
            $ev = $st['forecast']['expeditor_variance_days'] ?? null;
            if ($ev !== null) {
                $cards[] = ['label'=>'Forecast vs need','value'=>($ev>0?'+'.$ev.' d':($ev<0?$ev.' d':'On time')),
                    'sub'=>($ev>0?'late':($ev<0?'early':'to plan')),'tone'=>($ev>0?'bad':'ok')];
            }
        }
        if ($dr && $dr['pct'] !== null) {
            $b = count($dr['blockers']);
            $cards[] = ['label'=>'Dispatch ready','value'=>$num($dr['pct']).'%','sub'=>$dr['yes'].'/'.$dr['applicable'].' cleared',
                'tone'=>($dr['pct']>=100?'ok':($b>0?'bad':'warn'))];
            if ($b>0) $cards[] = ['label'=>'Blockers','value'=>(string)$b,'sub'=>'mandatory open','tone'=>'bad'];
        }
        return $cards;
    }

    // Scored report (vendor assessment / audit / any scored checklist).
    $sc = function_exists('idems_score_doc') ? idems_score_doc($sections,$fields,$data) : null;
    if ($sc && $sc['overall'] !== null) {
        $ov = (float)$sc['overall'];
        $tone = $ov>=75 ? 'ok' : ($ov>=60 ? 'warn' : 'bad');
        $scoreLbl = ($code === 'VAR') ? 'Conformance' : 'Assessment score';
        $cards[] = ['label'=>$scoreLbl,'value'=>$num($ov),'sub'=>'out of 100','tone'=>$tone];
        if (trim((string)$sc['band']) !== '') $cards[] = ['label'=>'Rating band','value'=>(string)$sc['band'],'sub'=>'','tone'=>$tone];
        if ((int)($sc['total'] ?? 0) > 0) $cards[] = ['label'=>'Clauses scored','value'=>$sc['answered'].'/'.$sc['total'],'sub'=>'coverage','tone'=>'neutral'];
        $rec = $fieldVal(['recommendation','decision','overall_recommendation']);
        if ($rec !== '') $cards[] = ['label'=>'Recommendation','value'=>$rec,'sub'=>'','tone'=>$recTone($rec)];
        return $cards;
    }

    // Inspection-type report — result / release headline.
    $result  = lk_options_or('inspection_result', IDEMS_RESULTS)[$doc['result'] ?? ''] ?? '';
    $release = lk_options_or('release_status', IDEMS_RELEASE)[$doc['release_status'] ?? ''] ?? '';
    if (trim($result) !== '') $cards[] = ['label'=>'Result','value'=>$result,'sub'=>'','tone'=>$recTone($result)];
    if (trim($release) !== '') $cards[] = ['label'=>'Release status','value'=>$release,'sub'=>'','tone'=>$recTone($release)];
    return $cards;
}

// ---- Report PDF (letterhead + body + automatic signature block + timestamps) ----
function report_pdf_build($doc, $sections, $fields, $data, $files, $lh, $sigs, $copy = '') {
    // Company brand colour for the header band (from the letterhead), else default.
    $band = (!empty($lh['band']) && is_array($lh['band'])) ? $lh['band'] : [30, 64, 175];
    // Document-control identity (format no. / doc no. / revision) printed on the
    // header — from the report type, so a company's own format prints without any
    // uploaded Word file.
    $fmtNo = trim((string)($doc['format_number'] ?? '')); $docCtl = trim((string)($doc['doc_control_no'] ?? '')); $rev = trim((string)($doc['revision'] ?? ''));
    if ($fmtNo === '' && $docCtl === '' && $rev === '' && !empty($doc['report_type_id'])) {
        $rt = ops_one("SELECT format_number, doc_control_no, revision FROM report_types WHERE id=?", [(int)$doc['report_type_id']]);
        if ($rt) { $fmtNo = trim((string)($rt['format_number'] ?? '')); $docCtl = trim((string)($rt['doc_control_no'] ?? '')); $rev = trim((string)($rt['revision'] ?? '')); }
    }
    $copy = strtoupper(trim((string)$copy));
    // §paginate — the whole document is produced by this closure so it can be built
    // TWICE: once to measure how the sections fall, then again forcing a page break
    // before a chosen section so a short final page is not left mostly empty. It
    // returns the page object, where each section began/ended, and the final cursor.
    $build = function($forceBreakBefore) use ($doc, $sections, $fields, $data, $files, $lh, $sigs, $copy, $band, $fmtNo, $docCtl, $rev) {
    $p = new SimplePDF(); $ml = $p->ml; $right = $p->right();
    $secStarts = [];
    // A friendly report title — never a bare code. Uses the type's own name when
    // set, else maps a known code to a readable title, else the code itself.
    $friendlyType = function($doc) {
        $n = trim((string)($doc['type_name'] ?? ''));
        if ($n !== '' && strtoupper($n) !== strtoupper(trim((string)($doc['type_code'] ?? '')))) return $n;
        $map = ['ER'=>'Expediting Report','VASR'=>'Vendor Assessment','VAR'=>'Vendor Audit',
                'IR'=>'Inspection Report','RN'=>'Release Note','NCR'=>'Nonconformity Report'];
        $code = strtoupper(trim((string)($doc['type_code'] ?? '')));
        return $map[$code] ?? ($n !== '' ? $n : ($code !== '' ? $code : 'Inspection Report'));
    };
    $docTitle = $friendlyType($doc);
    // A draft carries a DRAFT watermark until it is approved and locked. Once
    // finalized, an Original / Duplicate / Triplicate copy carries that word.
    if (empty($doc['finalized'])) { $p->watermark = 'DRAFT'; }
    elseif (in_array($copy, ['DUPLICATE', 'TRIPLICATE'], true)) { $p->watermark = $copy; }
    // Running header on continuation pages + footer with "Page X of Y" on every
    // page — so a multi-page report is identifiable and paginated end to end.
    $p->runHead = ['name'=>(($lh['name'] ?? '') ?: app_name()), 'sub'=>trim('IRN '.($doc['irn'] ?? '').($rev!==''?'  ·  Rev '.$rev:'')), 'band'=>$band, 'skipFirst'=>true];
    $p->runFoot = ['left'=>(($lh['name'] ?? '') ?: app_name()).($doc['irn'] ? '  ·  '.$doc['irn'] : ''), 'pageNumbers'=>true, 'band'=>$band];
    $p->rectFill(0, 0, $p->pageW(), 6, $band);
    $p->y = $p->mt; $top = $p->y; $nameX = $ml;
    if (!empty($lh['logo'])) { $ln = $p->addJpeg($lh['logo']); if ($ln) { [$iw,$ih]=$p->imgDim($ln); $lw=80; $lhh=$ih>0?min(44,$lw*$ih/max(1,$iw)):36; $p->drawImage($ln,$ml,$top,$lw,$lhh); $nameX=$ml+$lw+12; } }
    $p->text($nameX, ($lh['name'] ?? '') ?: app_name(), 15, true, $band);
    $ly = $top + 18;
    foreach (preg_split('/\r?\n/', (string)($lh['address'] ?? '')) as $al) { $al=trim($al); if($al==='')continue; $p->y=$ly; $p->text($nameX,$al,8.5,false,[90,90,90]); $ly+=10; }
    if (!empty($lh['contact'])) { $p->y=$ly; $p->text($nameX,$lh['contact'],8.5,false,[90,90,90]); $ly+=10; }
    if (!empty($lh['gstin'])) { $p->y=$ly; $p->text($nameX,$lh['gstin'],8.5,false,[90,90,90]); $ly+=10; }
    // §header — document-control block on the right: the report type reads as a
    // clear title, then the IRN and the controlled-document identity (revision,
    // issue date, format & doc numbers), so the corner feels intentional.
    $yr=$top;
    $typeName = $docTitle;
    $issueF = trim((string)($doc['issue_date'] ?? '')); if ($issueF!==''){ $tt=strtotime($issueF); if($tt) $issueF=date('d-M-Y',$tt); }
    $p->y=$yr; $p->text($ml, strtoupper($typeName), 11, true, $band, $right, 'R'); $yr+=15;
    $p->y=$yr; $p->text($ml, 'IRN: '.$doc['irn'], 8.5, true, [70,70,70], $right, 'R'); $yr+=12;
    $ctl = [];
    if ($rev !== '')    $ctl[] = 'Rev. '.$rev;
    if ($issueF !== '') $ctl[] = 'Issue '.$issueF;
    if ($ctl) { $p->y=$yr; $p->text($ml, implode('   ·   ', $ctl), 8, false, [110,110,110], $right, 'R'); $yr+=11; }
    if ($fmtNo !== '') { $p->y=$yr; $p->text($ml,'Format No.: '.$fmtNo,7.5,false,[120,120,120],$right,'R'); $yr+=10; }
    if ($docCtl !== '') { $p->y=$yr; $p->text($ml,'Doc. No.: '.$docCtl,7.5,false,[120,120,120],$right,'R'); $yr+=10; }
    if (empty($doc['finalized'])) { $p->y=$yr; $p->text($ml, 'DRAFT — not yet issued', 8, true, [200,60,60], $right, 'R'); $yr+=11; }
    elseif ($copy !== '') { $p->y=$yr; $p->text($ml, $copy . ' COPY', 8.5, true, $band, $right, 'R'); $yr+=11; }
    $p->y = max($ly, $yr, $top + 50); $p->hr($band);
    $p->gap(6);
    // report title
    $p->line(strtoupper($docTitle), 13, true, 16, $band);

    // §snapshot — at-a-glance KPI cards so a reviewer reads the headline status in
    // one glance without scanning the body. Rendered as a tidy row of tinted cards
    // just under the title. When these cards already carry Result/Release (an
    // inspection report), the separate outcome badges below are suppressed. §dedup
    $kpiTone = function($t){ switch($t){
        case 'ok':   return [[236,253,245],[21,128,61]];
        case 'warn': return [[255,251,235],[180,83,9]];
        case 'bad':  return [[254,242,242],[185,45,45]];
        case 'muted':return [[248,249,251],[110,110,110]];
        default:     return [[239,246,255],[37,99,235]]; // neutral
    }; };
    $kpis = function_exists('idems_report_kpis') ? idems_report_kpis($doc, $sections, $fields, $data) : [];
    $kpiShown = false; $kpiHadOutcome = false;
    if ($kpis) {
        $kpis = array_slice($kpis, 0, 5); $n = count($kpis);
        // Divide the row evenly, but cap each card so one or two cards stay compact
        // and left-aligned instead of stretching across the whole page. §snapshot
        $gap = 6; $cardH = 46; $cw = min(($p->contentW() - $gap*($n-1)) / $n, 175);
        $p->gap(4); $p->needSpace($cardH + 6); $y0 = $p->y;
        foreach ($kpis as $i => $c) {
            [$bg,$fg] = $kpiTone($c['tone'] ?? 'neutral');
            $cx = $ml + $i*($cw+$gap);
            $p->rectFill($cx, $y0, $cw, $cardH, $bg);
            $p->rectFill($cx, $y0, 2.5, $cardH, $fg);
            $tx = $cx + 8; $iw = $cw - 12;
            // Label (uppercase, muted) at the top.
            $p->y = $y0 + 7; $p->text($tx, strtoupper($p->wrap((string)($c['label']??''),6.5,$iw,true)[0] ?? ''), 6.5, true, [120,120,120]);
            // Value — the headline. Shrink to fit the card width; wrap to two lines.
            $val = trim((string)($c['value']??'')); $vSize = 14;
            while ($vSize > 8 && $p->strWidth($val, $vSize, true) > $iw) $vSize -= 0.5;
            $vLines = $p->wrap($val, $vSize, $iw, true, true); $vLines = array_slice($vLines, 0, 2);
            $vy = $y0 + 18;
            foreach ($vLines as $vl) { $p->y = $vy; $p->text($tx, $vl, $vSize, true, $fg); $vy += $vSize + 1.5; }
            // Sub-caption at the foot.
            $sub = trim((string)($c['sub']??''));
            if ($sub !== '') { $p->y = $y0 + $cardH - 9; $p->text($tx, $p->wrap($sub,6.5,$iw)[0] ?? '', 6.5, false, [130,130,130]); }
            $l = strtolower((string)($c['label']??'')); if (strpos($l,'result')!==false || strpos($l,'release')!==false) $kpiHadOutcome = true;
        }
        $p->y = $y0 + $cardH; $p->gap(6); $kpiShown = true;
    }

    // §status — a small pill: coloured background + darker text, sized to the word.
    $badge = function($x, $y, $text, $size = 9.5) use ($p) {
        $text = trim((string)$text); if ($text === '') return 0;
        [$bg, $fg] = idems_status_palette($text);
        $up = strtoupper($text); $tw = $p->strWidth($up, $size, true); $padX = 7; $h = $size + 8; $w = $tw + 2*$padX;
        $p->rectFill($x, $y, $w, $h, $bg);
        $p->y = $y + 4; $p->text($x + $padX, $up, $size, true, $fg);
        return $w;
    };
    $fmtDate = function($d) { $d = trim((string)$d); if ($d === '') return ''; $t = strtotime($d); return $t ? date('d-M-Y', $t) : $d; };

    // Outcome status card — Result & Release shown as badges so the report's
    // disposition reads at a glance, with standards and issue date on a muted line.
    $result  = lk_options_or('inspection_result', IDEMS_RESULTS)[$doc['result'] ?? ''] ?? '';
    $release = lk_options_or('release_status', IDEMS_RELEASE)[$doc['release_status'] ?? ''] ?? '';
    $colW = $p->contentW()/2;
    // Skip these badges when the KPI snapshot above already carries the outcome —
    // otherwise Result/Release would print twice. §dedup
    if (!$kpiHadOutcome && (trim($result) !== '' || trim($release) !== '')) {
        $p->gap(3); $p->needSpace(30); $cardY = $p->y;
        $p->text($ml, 'RESULT', 7.5, true, [130,130,130]);
        if (trim($release) !== '') $p->text($ml + $colW, 'RELEASE STATUS', 7.5, true, [130,130,130]);
        $bY = $cardY + 10;
        if (trim($result) !== '')  $badge($ml, $bY, $result);
        if (trim($release) !== '') $badge($ml + $colW, $bY, $release);
        $p->y = $bY + 20;
    }
    $metaBits = array_filter([
        trim((string)($doc['standards'] ?? '')) !== '' ? 'Standards: ' . $doc['standards'] : '',
        trim((string)($doc['issue_date'] ?? '')) !== '' ? 'Issue date: ' . $fmtDate($doc['issue_date']) : '',
    ]);
    if ($metaBits) { $p->needSpace(13); $p->text($ml, implode('      ·      ', $metaBits), 8.5, false, [90,90,90]); $p->y += 14; }

    // §scorecard — for scored report types (vendor assessment, audit, scored
    // checklist) print a compact weighted scorecard: overall score + band and a
    // bar per category. Non-scored report types return null here and skip it.
    $scard = function_exists('idems_score_doc') ? idems_score_doc($sections, $fields, $data) : null;
    if ($scard && $scard['overall'] !== null) {
        $scoreCol = function($s) { $s=(float)$s; if($s>=90) return [21,128,61]; if($s>=75) return [37,99,235]; if($s>=60) return [180,83,9]; if($s>=40) return [194,65,12]; return [200,60,60]; };
        $bars = array_values(array_filter($scard['sections'], fn($x)=>$x['score']!==null));
        // When the KPI snapshot already showed the headline score/progress and there
        // is no per-category breakdown to add (0-1 bars), the scorecard block would
        // just repeat the number — skip it. Multi-category scorecards still print,
        // because their category bars are real added detail. §dedup
        if ($kpiShown && count($bars) <= 1) $bars = null;
        if ($bars !== null) {
        $need = 34 + count($bars)*12 + 12; $p->needSpace($need);
        $oc = $scoreCol($scard['overall']); $cardY = $p->y; $cardX = $ml;
        $p->rectFill($cardX, $cardY, $p->contentW(), $need-6, [248,249,251]);
        $p->rectFill($cardX, $cardY, 3, $need-6, $oc);
        $tx = $cardX + 12; $p->y = $cardY + 9;
        // An expediting report reads "OVERALL PROGRESS", others "ASSESSMENT SCORE".
        // But when the KPI snapshot already printed the headline number above, this
        // block is only the per-category breakdown — so it reads "SCORE BY CATEGORY"
        // and omits the repeated big number/band. §dedup
        $isER = ($doc['type_code'] ?? '') === 'ER';
        $cardBand = $isER && function_exists('idems_progress_band') ? idems_progress_band($scard['overall']) : (string)$scard['band'];
        if ($kpiShown) {
            $p->text($tx, 'SCORE BY CATEGORY', 8, true, [110,110,110]);
        } else {
            $cardLbl = $isER ? 'OVERALL PROGRESS' : 'ASSESSMENT SCORE';
            $p->text($tx, $cardLbl, 8, true, [110,110,110]);
            $ovTxt = rtrim(rtrim(number_format((float)$scard['overall'],1),'0'),'.');
            $p->y = $cardY + 9; $p->text($tx + 120, $ovTxt . ($isER ? '%' : ' / 100'), 13, true, $oc);
            $p->y = $cardY + 9; $p->text($ml, strtoupper($cardBand), 9, true, $oc, $right, 'R');
        }
        // Category bars.
        $barX = $tx + 175; $barW = ($ml + $p->contentW() - 40) - $barX;
        $by = $cardY + 28;
        foreach ($bars as $b) {
            $c = $scoreCol($b['score']);
            $p->y = $by; $p->text($tx, $p->wrap((string)$b['title'], 8, 168)[0] ?? (string)$b['title'], 8, false, [70,70,70]);
            $p->rectFill($barX, $by + 1, $barW, 5, [225,227,230]);
            $frac = max(0.02, min(1, ((float)$b['score'])/100));
            $p->rectFill($barX, $by + 1, $barW*$frac, 5, $c);
            $p->y = $by; $p->text($ml, rtrim(rtrim(number_format((float)$b['score'],1),'0'),'.'), 8, true, $c, $right, 'R');
            $by += 12;
        }
        $p->y = $cardY + $need - 6; $p->gap(6);
        } // §dedup — end scorecard body
    }

    // If the form does NOT itself carry the header fields (no "Inspection details"
    // section), show the client/vendor reference grid here — the outcome fields are
    // already in the status card above, so nothing prints twice. §dedup
    $hdrKeys = ['client','vendor','manufacturer','po','po_number','project','drawing','drawing_no','qap','qap_rev','standards','location','inspection_date','material','material_grade'];
    $formCovers = 0; foreach ($fields as $ff) if (in_array(strtolower((string)$ff['fkey']), $hdrKeys, true)) $formCovers++;
    $formHasHeader = $formCovers >= 3;
    if (!$formHasHeader) {
        $kv = [
            'Client' => ($doc['client_disp'] ?? '') ?: ($doc['client_name'] ?? ''), 'Vendor / Mfr' => ($doc['vendor_disp'] ?? '') ?: ($doc['vendor_name'] ?? ''),
            'Project' => trim(($doc['project_code'] ?? '').' '.($doc['project_name'] ?? '')), 'PO' => $doc['po_ref'] ?? '',
            'Drawing' => trim(($doc['drawing_no'] ?? '').' '.(($doc['drawing_rev'] ?? '')?'Rev '.$doc['drawing_rev']:'')), 'QAP rev' => $doc['qap_rev'] ?? '',
            'Location' => $doc['location'] ?? '', 'Inspection date' => $fmtDate($doc['inspection_date'] ?? ''),
        ];
        foreach (array_chunk(array_filter($kv, fn($v)=>trim((string)$v)!==''), 2, true) as $pair) {
            $p->needSpace(14); $yrow = $p->y; $i = 0;
            foreach ($pair as $k=>$v) { $x = $ml + $i*$colW; $p->y=$yrow; $p->text($x, $k.':', 8.5, true, [90,90,90]); $p->text($x+70, $p->wrap((string)$v,9,$colW-74)[0] ?? (string)$v, 9); $i++; }
            $p->y = $yrow + 13;
        }
    }
    $p->gap(4);
    // body sections
    $bySec = []; foreach ($fields as $f) $bySec[(int)$f['section_id']][] = $f;
    $filesBy = []; foreach ($files as $fl) $filesBy[$fl['field_key']][] = $fl;
    // Does a field carry anything worth printing on the ISSUED report? Static and
    // structural fields always do; a photo field always does (it prints either the
    // photos or a "no photographs" note); everything else must have a value. Empty
    // fields — and sections left entirely empty — are dropped so the report never
    // shows bare "Label:" lines or a heading with nothing under it. §empty
    $hasContent = function($f) use ($data, $filesBy) {
        $t = $f['ftype'] ?? ''; $k = $f['fkey'] ?? '';
        if (in_array($t, ['richtext','sigblock','heading','note','photo'], true)) return true;
        if ($t === 'table') { $v = $data[$k] ?? null; return is_array($v) && count($v) > 0; }
        if (in_array($t, ['file','signature'], true)) return !empty($filesBy[$k]);
        $v = $data[$k] ?? '';
        return is_array($v) ? count($v) > 0 : trim((string)$v) !== '';
    };
    $secList = $sections; $secList[] = ['id'=>0,'title'=>''];
    foreach ($secList as $s) {
        if (!idems_cond_visible($s, $data)) continue;   // §cond — honour a section's show-when rule in the output
        $fl = $bySec[(int)$s['id']] ?? [];
        $fl = array_values(array_filter($fl, fn($f) => idems_cond_visible($f, $data)));  // §cond — and each field's
        $fl = array_values(array_filter($fl, $hasContent));                             // §empty — drop empty fields
        if (!$fl) continue;                                                              // §empty — and empty sections
        // §paginate — force the balancing break before this section when asked, then
        // record where it begins so the driver can measure section heights.
        if (count($secStarts) === $forceBreakBefore && $p->y > $p->mt + 2) $p->addPage();
        $secStarts[] = ['start'=>$p->y, 'startPage'=>$p->curPageIndex()];
        // Per-section print layout. "Start on new page" forces a page break unless
        // we're already at the top; "Keep together" pushes a section that would be
        // orphaned near the bottom onto the next page.
        if (!empty($s['page_break_before']) && $p->y > $p->mt + 2) $p->addPage();
        elseif (!empty($s['keep_together']) && ($p->y > 620)) $p->addPage();
        $p->needSpace(20); $p->gap(4);
        if ($s['title']!=='') { $p->line($s['title'], 11, true, 14, $band); }
        // When a section holds a SINGLE table/photo/sign-off field, the section
        // title already names it — so don't print the field's own label too
        // ("Reference documents / Reference documents"). §dedup
        $solo = (count($fl) === 1 && trim((string)$s['title']) !== '');
        // Short header-style fields (File No, Client, Dates…) render as a clean
        // TWO-COLUMN grid, like a real inspection form. Paragraphs, tables and
        // attachments span the full width. §PDF-header-grid
        $pending = [];
        $valOf = function($f) use ($data) {
            $k=$f['fkey']; $v=$data[$k] ?? '';
            if ($f['ftype']==='multiselect' && is_array($v)) { $o=idems_field_options($f); return implode(', ', array_map(fn($x)=>$o[$x]??$x,$v)); }
            if (in_array($f['ftype'],['select','radio','unit'],true)) { $o=idems_field_options($f); return (string)($o[$v]??$v); }
            if ($f['ftype']==='checkbox') return ($v==='1'||$v===1)?'Yes':'No';
            return is_array($v)?'':(string)$v;
        };
        $flushGrid = function() use (&$pending,$p,$ml,$valOf) {
            if (!$pending) return;
            $colW=$p->contentW()/2;
            // Lay out one field inside its half-column with a FIXED label column, so
            // every value down a half aligns at the same left edge (professional
            // two-column look). Long labels wrap within the label column; the value
            // wraps in the remaining width. Both top-aligned. §align
            $lblW = 116; $gap = 8;
            $plan = function($f,$x) use ($p,$colW,$valOf,$lblW,$gap) {
                $lblLines = $p->wrap($f['label'].':',8.5,$lblW,true,true); if(!$lblLines)$lblLines=[''];
                $vx = $x + $lblW + $gap; $valW = $colW - $lblW - $gap - 4;
                $vl = $p->wrap($valOf($f),8.5,max(36,$valW),false,true); if(!$vl)$vl=[''];
                return ['x'=>$x,'lblLines'=>$lblLines,'vx'=>$vx,'vl'=>$vl,'n'=>max(count($lblLines),count($vl))];
            };
            for ($i=0;$i<count($pending);$i+=2) {
                $cells=[$plan($pending[$i],$ml)];
                if (isset($pending[$i+1])) $cells[]=$plan($pending[$i+1],$ml+$colW);
                $lines=1; foreach ($cells as $c) $lines=max($lines,$c['n']);
                $p->needSpace($lines*11+3); $y0=$p->y;
                foreach ($cells as $c) {
                    foreach ($c['lblLines'] as $j=>$ln) { $p->y=$y0+$j*11; $p->text($c['x'],$ln,8.5,true,[90,90,90]); }
                    for($j=0;$j<count($c['vl']);$j++){ $p->y=$y0+$j*11; $p->text($c['vx'],$c['vl'][$j],8.5,false,[40,40,40]); }
                }
                $p->y=$y0+$lines*11+2;
            }
            $pending=[];
        };
        $shortTypes=['text','number','date','time','select','unit','yesno','radio','checkbox','multiselect','instrument','calc','qr','gps'];
        // Does this section mix a full-width field (a paragraph / table / photo) in
        // with short fields? If so the short fields are laid out in the SAME aligned
        // label|value columns as the paragraphs (not the compact 2-up grid), so a
        // section like "Order & hold-point status" reads as one tidy column. §align
        $allShort = true; foreach ($fl as $ff) if (!in_array($ff['ftype'] ?? '', $shortTypes, true)) { $allShort = false; break; }
        // A single value printed as an aligned two-column row: the label wraps in a
        // fixed left column, the value wraps in the right column, both top-aligned —
        // so every value down the section lines up at the same left edge. §align
        $fullLbl = function($f,$v) use ($p,$ml) {
            $vv = is_array($v) ? '' : (string)$v;
            $lblW = 165; $gap = 10; $valX = $ml + $lblW + $gap; $valW = $p->contentW() - $lblW - $gap;
            $lblLines = $p->wrap($f['label'], 8.5, $lblW, true); if (!$lblLines) $lblLines = [''];
            $valLines = [];
            foreach (preg_split('/\r?\n/', $vv) as $para) {
                if (trim($para) === '') { $valLines[] = ''; continue; }
                foreach ($p->wrap($para, 9, $valW) as $wl) $valLines[] = $wl;
            }
            if (!$valLines) $valLines = [''];
            $rows = max(count($lblLines), count($valLines));
            $p->needSpace($rows * 11 + 4); $y0 = $p->y;
            foreach ($lblLines as $i => $ln) { $p->y = $y0 + $i*11; $p->text($ml, $ln, 8.5, true, [80,80,80]); }
            foreach ($valLines as $i => $ln) { if ($ln !== '') { $p->y = $y0 + $i*11; $p->text($valX, $ln, 9, false, [40,40,40]); } }
            $p->y = $y0 + $rows*11 + 4;
        };
        foreach ($fl as $f) {
            if ($f['ftype']==='richtext') {
                $flushGrid();
                if (trim((string)($f['label'] ?? '')) !== '') { $p->needSpace(12); $p->line($f['label'], 9.5, true, 12, [70,70,70]); }
                foreach (preg_split('/\r?\n/', (string)($f['options'] ?? '')) as $ln) {
                    if (trim($ln) === '') { $p->gap(4); continue; }
                    foreach ($p->wrap($ln, 8.5, $p->contentW()) as $wl) { $p->needSpace(11); $p->line($wl, 8.5, false, 11, [90,90,90]); }
                }
                $p->gap(2); continue;
            }
            if (in_array($f['ftype'],['heading','note'],true)) { $flushGrid(); $p->needSpace(12); $p->line($f['label'], 9.5, $f['ftype']==='heading', 12, [80,80,80]); continue; }
            $k=$f['fkey']; $v=$data[$k] ?? '';
            if ($f['ftype']==='table') {
                $flushGrid();
                if (!is_array($v)||!$v) continue;
                $defs = idems_table_col_defs($f);                 // ck => [label,type,options,merge]
                $cols = []; foreach ($defs as $ck=>$d) $cols[$ck]=$d['label'];
                $mergeCols=[]; foreach ($defs as $ck=>$d) if (!empty($d['merge'])) $mergeCols[$ck]=true;
                $prevVal=[];
                $ncol = max(1,count($cols));
                $cellFont = $ncol > 7 ? 7.5 : 8.5;   // shrink the type when a table has many columns
                // §tables — proportional column widths: a description / observation
                // column earns more room; a number / date / short one gets less (with a
                // sensible minimum), so wide tables read far better than equal columns.
                $statusLabels = ['disposition','status','result','conformity','conclusion','acceptance'];
                $weights=[];
                // A tick column (evidence attached?) is rendered as a checkbox, so it
                // needs only a narrow slot.
                $isTickCol = function($lbl) { $l=strtolower(trim((string)$lbl)); return strpos($l,'attach')!==false || $l==='evidence?' || $l==='attached'; };
                foreach ($defs as $ck=>$d) {
                    $l=strtolower(trim((string)$d['label'])); $t=$d['type'];
                    if ($isTickCol($d['label'])) $weights[$ck]=1.0;             // narrow — checkbox + fits the "Attached" header
                    elseif (in_array($l,$statusLabels,true)) $weights[$ck]=1.55;   // room for the status badge
                    elseif (preg_match('/desc|observ|remark|activit|scope|detail|particular|nature/', $l)) $weights[$ck]=2.2;
                    elseif (in_array($t,['number','date'],true)) $weights[$ck]=0.85;
                    elseif ($t==='select') $weights[$ck]=1.05;
                    else $weights[$ck]=1.3;
                }
                $sumW=array_sum($weights) ?: 1; $contentW=$p->contentW(); $minW=26;
                $wpx=[]; foreach ($weights as $ck=>$wt) $wpx[$ck]=max($minW,$contentW*$wt/$sumW);
                $scale=$contentW/max(1,array_sum($wpx)); foreach ($wpx as $ck=>$px) $wpx[$ck]=$px*$scale;
                $xpos=[]; $acc=$ml; foreach ($wpx as $ck=>$px){ $xpos[$ck]=$acc; $acc+=$px; }
                // A "status" column is rendered as a subtle badge instead of plain text.
                $isStatusCol = function($lbl) { $l=strtolower(trim((string)$lbl)); return in_array($l, ['disposition','status','result','conformity','conclusion','acceptance'], true); };
                $cellBadge = function($x,$y,$text) use ($p) {
                    $text=trim((string)$text); if($text==='') return;
                    [$bg,$fg]=idems_status_palette($text); $up=strtoupper($text); $size=6.5; $tw=$p->strWidth($up,$size,true);
                    $padX=4; $w=$tw+2*$padX; $h=$size+5; $p->rectFill($x,$y,$w,$h,$bg); $p->y=$y+2.5; $p->text($x+$padX,$up,$size,true,$fg);
                };
                // A checkbox with a green tick (attached), an empty box (not attached)
                // or a dash (N/A) — for an "Evidence attached?" style column. Drawn as
                // vectors so it prints reliably (the report font has no tick glyph).
                $cellTick = function($x,$y,$val) use ($p) {
                    $v=strtolower(trim((string)$val));
                    $bs=7.5; $bx=$x+1; $by=$y+1.5;
                    if (in_array($v,['n/a','na','-','',' '],true)) { $p->y=$by; $p->text($bx,'—',8,false,[150,150,150]); return; }
                    $on = in_array($v,['yes','y','1','true','attached','ok','tick','done'],true);
                    $box=[120,120,120];
                    $p->lineAt($bx,$by,$bx+$bs,$by,$box,0.6); $p->lineAt($bx,$by+$bs,$bx+$bs,$by+$bs,$box,0.6);
                    $p->lineAt($bx,$by,$bx,$by+$bs,$box,0.6); $p->lineAt($bx+$bs,$by,$bx+$bs,$by+$bs,$box,0.6);
                    if ($on) { $g=[21,128,61]; $p->lineAt($bx+1.4,$by+3.8,$bx+3,$by+5.6,$g,1.3); $p->lineAt($bx+3,$by+5.6,$bx+6.1,$by+1.7,$g,1.3); }
                    else { $rr=[200,60,60]; $p->lineAt($bx+1.6,$by+1.6,$bx+5.9,$by+5.9,$rr,1.1); $p->lineAt($bx+5.9,$by+1.6,$bx+1.6,$by+5.9,$rr,1.1); }
                };
                // Header cells are WRAPPED and sit at each column's own x/width. §layout
                // The header font shrinks on wide tables so single-word labels
                // (Disposition, Attached, …) fit their column instead of colliding.
                $hFont = $ncol > 8 ? 6.5 : 7.5; $hLh = $ncol > 8 ? 8 : 9;
                $drawHead = function() use ($p,$ml,$cols,$wpx,$xpos,$hFont,$hLh) {
                    $wrapped=[]; $maxL=1;
                    foreach ($cols as $ck=>$cl){ $w=$p->wrap((string)$cl,$hFont,$wpx[$ck]-4,true,true); if(count($w)>3)$w=array_slice($w,0,3); if(!$w)$w=['']; $wrapped[$ck]=$w; $maxL=max($maxL,count($w)); }
                    $hh=$maxL*$hLh+4; $hy=$p->y; $p->rectFill($ml,$hy,$p->contentW(),$hh,[235,238,245]);
                    foreach ($wrapped as $ck=>$w){ for($j=0;$j<count($w);$j++){ $p->y=$hy+3+$j*$hLh; $p->text($xpos[$ck]+3,$w[$j],$hFont,true,[60,60,60]); } }
                    $p->y=$hy+$hh+1;
                };
                $p->needSpace(34);   // keep the label with its header + a first row
                if (!$solo) { $p->text($ml, $f['label'], 9, true, [70,70,70]); $p->gap(11); }
                $drawHead();
                foreach ($v as $r){
                    $r=(array)$r;
                    $cells=[]; $badges=[]; $ticks=[]; $lines=1;
                    foreach ($defs as $ck=>$d){
                        $cellTxt=(string)($r[$ck]??'');
                        if (isset($mergeCols[$ck]) && $cellTxt!=='' && ($prevVal[$ck]??null)===$cellTxt) $cellTxt='';
                        elseif (isset($mergeCols[$ck])) $prevVal[$ck]=(string)($r[$ck]??'');
                        if ($isTickCol($d['label'])) { $ticks[$ck]=$cellTxt; $cells[$ck]=['']; continue; }
                        if ($isStatusCol($d['label']) && trim($cellTxt)!=='') { $badges[$ck]=$cellTxt; $cells[$ck]=['']; continue; }
                        $w=$p->wrap($cellTxt,$cellFont,$wpx[$ck]-4,false,true); if(count($w)>4)$w=array_slice($w,0,4); if(!$w)$w=['']; $cells[$ck]=$w; $lines=max($lines,count($w)); }
                    $rowH=$lines*10+2;
                    if ($p->needSpace($rowH)) { $drawHead(); $prevVal=[]; }   // spilled to a new page → repeat the header
                    $ry=$p->y;
                    foreach ($cells as $ck=>$w){ for($j=0;$j<count($w);$j++){ $p->y=$ry+$j*10; $p->text($xpos[$ck]+3,$w[$j],$cellFont); } }
                    foreach ($badges as $ck=>$txt){ $cellBadge($xpos[$ck]+2,$ry-1,$txt); }
                    foreach ($ticks as $ck=>$tv){ $cellTick($xpos[$ck]+2,$ry-1,$tv); }
                    $p->y=$ry+$rowH; $p->lineAt($ml,$p->y,$right,$p->y,[235,235,235]);
                }
                $p->gap(3); continue;
            }
            if ($f['ftype']==='sigblock') {
                $flushGrid();
                $rows = idems_sigblock_rows($f, $data, $sigs);
                $n = count($rows); if ($n === 0) { continue; }
                $perRow = min(3, $n); $boxW = $p->contentW()/$perRow;
                $p->needSpace(26);
                if (!$solo && trim((string)($f['label'] ?? '')) !== '') { $p->text($ml, $f['label'], 9, true, [70,70,70]); $p->gap(14); }
                for ($i=0;$i<$n;$i+=$perRow) {
                    $slice = array_slice($rows,$i,$perRow);
                    $p->needSpace(92); $rowY=$p->y; $x=$ml;
                    foreach ($slice as $r) {
                        // §sign — uppercase role header, signature line, then labelled
                        // Name / Designation / Date and a subtle "digitally signed" mark.
                        $p->y=$rowY; $p->text($x, strtoupper((string)$r['role']), 8, true, [110,110,110]);
                        $imgY=$rowY+15;
                        $signed = !empty($r['img']);
                        if ($signed) { $nm=$p->addJpeg($r['img']); if($nm) $p->drawImage($nm,$x,$imgY,110,34); }
                        $lineY=$imgY+36; $p->lineAt($x,$lineY,$x+$boxW-14,$lineY,[120,120,120]);
                        $ty=$lineY+5;
                        $put = function($lbl,$val) use (&$ty,$p,$x) { $val=trim((string)$val); if($val==='')return; $p->y=$ty; $p->text($x,$lbl,7,true,[145,145,145]); $p->text($x+38,$val,8,false,[60,60,60]); $ty+=10; };
                        $put('Name', $r['name']);
                        $put('Desig.', $r['desig']);
                        $put('Date', $r['date']);
                        if ($signed) { $p->y=$ty; $p->text($x, 'Digitally signed', 7, true, [21,101,52]); $ty+=10; }
                        $x+=$boxW;
                    }
                    $p->y=$rowY+96;
                }
                $p->gap(3); continue;
            }
            if (in_array($f['ftype'],['photo','file','signature'],true)) {
                $flushGrid();
                // Photography denied by the client/vendor → print the statement in
                // place of the photo block, so the report says so explicitly.
                $denied = ($f['ftype']==='photo') && (($data[$k.'__photo_denied'] ?? '') === '1' || ($data[$k.'__photo_denied'] ?? '') === 1);
                if ($denied) {
                    $p->needSpace(16); $p->text($ml, $f['label'].':', 9, true, [70,70,70]); $p->gap(11);
                    $note = trim((string)($data[$k.'__photo_denied_note'] ?? ''));
                    $stmt = 'Photographs were not permitted / were denied by the ' . (Tl('client')) . ' / ' . (Tl('vendor')) . ' at the time of inspection.' . ($note !== '' ? ' ' . $note : '');
                    foreach ($p->wrap($stmt, 9, $p->contentW()) as $ln){ $p->needSpace(11); $p->line($ln, 9, false, 11, [150,60,60]); }
                    $p->gap(3); continue;
                }
                // No photographs and not marked denied → state it explicitly rather
                // than leaving a bare heading, so the section still reads as complete. §empty
                if (empty($filesBy[$k])) {
                    $p->needSpace(13);
                    foreach ($p->wrap('No photographs are attached to this report.', 9, $p->contentW()) as $ln){ $p->needSpace(11); $p->line($ln, 9, false, 11, [120,120,120]); }
                    $p->gap(3); continue;
                }
                $p->needSpace(14);
                if (!$solo) { $p->text($ml, $f['label'].':', 9, true, [70,70,70]); $p->gap(11); }
                $imgs = array_values(array_filter($filesBy[$k], fn($x)=>strpos($x['mime'],'image/')===0));
                if ($imgs) {
                    // §photos — a 2-across evidence grid: each photo in a bordered
                    // cell, aspect-fitted (never stretched), with a "Photo NN" label
                    // and its caption beneath. Reads as deliberate photographic
                    // evidence, not a strip of thumbnails.
                    $box = function($x,$y,$w,$h,$rgb=[205,208,214]) use ($p){ $p->lineAt($x,$y,$x+$w,$y,$rgb); $p->lineAt($x,$y+$h,$x+$w,$y+$h,$rgb); $p->lineAt($x,$y,$x,$y+$h,$rgb); $p->lineAt($x+$w,$y,$x+$w,$y+$h,$rgb); };
                    $perRow=2; $gap2=14; $cellW=($p->contentW()-$gap2)/2; $imgH=120; $capH=24; $cellH=$imgH+$capH;
                    $i2=0;
                    while ($i2 < count($imgs)) {
                        $rowImgs = array_slice($imgs, $i2, $perRow);
                        $p->needSpace($cellH+10); $rowY=$p->y; $x=$ml;
                        foreach ($rowImgs as $n=>$im){
                            $photoNo = $i2 + $n + 1;
                            $p->rectFill($x,$rowY,$cellW,$imgH,[248,249,251]);
                            $jpg=idems_sig_jpeg($im['data']);
                            if($jpg){ $nm=$p->addJpeg($jpg); if($nm){ [$iw,$ih]=$p->imgDim($nm);
                                $availW=$cellW-8; $availH=$imgH-8; $r=min($availW/max(1,$iw),$availH/max(1,$ih));
                                $dw=$iw*$r; $dh=$ih*$r; $ix=$x+($cellW-$dw)/2; $iy=$rowY+($imgH-$dh)/2; $p->drawImage($nm,$ix,$iy,$dw,$dh); } }
                            $box($x,$rowY,$cellW,$imgH);
                            $cy=$rowY+$imgH+5;
                            $p->y=$cy; $p->text($x, 'Photo '.sprintf('%02d',$photoNo), 7.5, true, [90,90,90]);
                            $cap=trim((string)($im['caption'] ?? '')); if($cap==='') $cap=(string)($im['file_name'] ?? '');
                            if ($cap!==''){ $cy2=$cy+9; foreach (array_slice($p->wrap($cap,7.5,$cellW),0,2) as $cl){ $p->y=$cy2; $p->text($x,$cl,7.5,false,[115,115,115]); $cy2+=9; } }
                            $x+=$cellW+$gap2;
                        }
                        $p->y=$rowY+$cellH+10; $i2+=$perRow;
                    }
                }
                foreach ($filesBy[$k] as $fl2) if (strpos($fl2['mime'],'image/')!==0) { $p->text($ml,'• '.$fl2['file_name'].(trim((string)($fl2['caption']??''))!==''?' — '.$fl2['caption']:''),8.5,false,[90,90,90]); $p->gap(10); }
                continue;
            }
            if ($f['ftype']==='textarea') { $flushGrid(); $fullLbl($f,$v); continue; }
            if (in_array($f['ftype'],$shortTypes,true)) { if ($allShort) { $pending[]=$f; } else { $flushGrid(); $fullLbl($f,$v); } continue; }
            $flushGrid(); $fullLbl($f,$v);
        }
        $flushGrid();
        $__si = count($secStarts) - 1; if ($__si >= 0) { $secStarts[$__si]['end'] = $p->y; $secStarts[$__si]['endPage'] = $p->curPageIndex(); }
    }
    // remarks
    if (!empty($doc['remarks'])) { $p->gap(4); $p->needSpace(16); $p->line('Remarks', 10, true, 13, $band); foreach ($p->wrap($doc['remarks'],9,$p->contentW()) as $ln2){ $p->needSpace(11); $p->line($ln2,9,false,11); } }
    // ---- Release Note block (RN / IRN documents only) ----
    if (in_array(strtoupper((string)($doc['type_code'] ?? '')), ['RN','IRN'], true) && empty($fields)) { idems_release_block($p, $doc, $lh, $band); }   // legacy form-less RN only; a form-driven RN renders through the normal engine
    // ---- signature block ----
    // Skip this built-in Inspected-by / Approved-by block if the FORM already has
    // a Sign-off (sigblock) section — otherwise the signatures print twice. §dedup
    $hasSigblock = false; foreach ($fields as $ff) if (($ff['ftype'] ?? '') === 'sigblock') { $hasSigblock = true; break; }
    if (!$hasSigblock) {
        $p->gap(14); $p->needSpace(90); $p->hr($band); $p->gap(8);
        $colW2 = $p->contentW()/2; $sy = $p->y;
        $drawSig = function($x, $y0, $title, $s) use ($p) {
            $p->y=$y0; $p->text($x, $title, 8.5, true, [90,90,90]);
            $imgY=$y0+14;
            if (!empty($s['img'])) { $nm=$p->addJpeg($s['img']); if($nm){ $p->drawImage($nm,$x,$imgY,120,40); } }
            $lineY=$imgY+42; $p->lineAt($x,$lineY,$x+150,$lineY,[120,120,120]);
            $ty=$lineY+3;
            foreach (array_filter([$s['name'] ?? '', $s['desig'] ?? '', $s['meta'] ?? '', $s['time'] ?? '']) as $t){ $p->y=$ty; $p->text($x,$t,8,false,[70,70,70]); $ty+=10; }
            return $ty;
        };
        $y1 = $drawSig($ml, $sy, 'Inspected by', $sigs['inspector'] ?? []);
        $y2 = $drawSig($ml + $colW2, $sy, 'Approved by', $sigs['approver'] ?? []);
        $p->y = max($y1, $y2) + 6;
    }
    // Verification block on an issued report: the recipient can confirm it is
    // genuine and unaltered at the public /verify page using the code below, with
    // no account. Backed by verify_lookup() and the evidence hash chain. Printed
    // only once finalized, so a draft never carries a code that would "verify".
    if (!empty($doc['finalized']) && function_exists('verify_code_for')) {
        $vc = verify_code_for($doc);
        $vu = function_exists('verify_url') ? verify_url($doc) : '';
        // A scannable QR pointing straight at the verification page, so a
        // recipient confirms the report with a phone camera instead of typing a
        // twenty-character code. Encoded by our own lib/qr.php (no dependencies).
        $qr = ($vu !== '' && function_exists('qr_matrix')) ? qr_matrix($vu, 'M') : null;
        // §verify — a self-contained verification panel: a subtle tinted box with a
        // left accent stripe, the report's identity and code on the left, and the QR
        // on the right, so the "this document is genuine" block reads as deliberate.
        $qrBox = 48; $boxH = 66;
        $p->needSpace($boxH + 8); $p->gap(6);
        $bx = $ml; $by = $p->y; $bw = $p->contentW();
        $p->rectFill($bx, $by, $bw, $boxH, [244, 248, 246]);
        $p->rectFill($bx, $by, 3, $boxH, [40, 120, 80]);        // accent stripe
        $tx = $bx + 12; $ty = $by + 9;
        $p->y = $ty;      $p->text($tx, 'DOCUMENT VERIFICATION', 9, true, [30, 92, 60]);
        $p->y = $ty + 14; $p->text($tx, 'Digitally verified ' . Tl('report') . ' — evidence untampered, no account needed.', 7.5, false, [95, 95, 95]);
        $p->y = $ty + 27; $p->text($tx, 'IRN:', 7.5, true, [125, 125, 125]);  $p->text($tx + 30, (string)$doc['irn'], 8, true, [45, 45, 45]);
        $p->y = $ty + 38; $p->text($tx, 'Code:', 7.5, true, [125, 125, 125]); $p->text($tx + 30, $vc, 9, true, [25, 25, 25]);
        if ($vu !== '') { $p->y = $ty + 49; $p->text($tx, 'Scan the QR or visit ' . $vu, 7, false, [140, 140, 140]); }
        if ($qr) $p->qr($qr, $bx + $bw - $qrBox - 10, $by + ($boxH - $qrBox) / 2, $qrBox);
        $p->y = $by + $boxH + 4;
    }
    // footer note
    if (!empty($lh['footer'])) { $p->needSpace(14); $p->hr([220,220,220]); $p->gap(3); $p->line($lh['footer'], 7.5, false, 10, [130,130,130]); }
    $p->needSpace(12); $p->line('System-generated by ' . ((($lh['name'] ?? '') ?: app_name())) . ' · IRN ' . $doc['irn'] . ' · ' . date('d M Y H:i') . ($doc['finalized'] ? ' · Issued/locked' : ' · DRAFT'), 7, false, 9, [150,150,150]);
        return ['p'=>$p, 'secStarts'=>$secStarts, 'pages'=>$p->pageCount(), 'usable'=>($p->usableBottom()-$p->usableTop()), 'top'=>$p->usableTop(), 'finalY'=>$p->y, 'finalPage'=>$p->curPageIndex()];
    };

    // Build once to measure, then rebalance if the final page is left mostly empty.
    // A rebuilt layout is accepted only if it did not spill onto an extra page. §paginate
    $r = $build(-1);
    $p = $r['p'];
    $K = idems_balance_break($r);
    if ($K >= 0) { $r2 = $build($K); if (($r2['pages'] ?? 99) === ($r['pages'] ?? 0)) $p = $r2['p']; }
    return $p->output();
}

// ---------------------------------------------------------------------------
//  Report Word (.docx) built from scratch — the always-available Word export.
//  When a company .docx template is registered it is used (exact client layout);
//  when none is, this renders a clean, editable Word document straight from the
//  report's own sections/fields/tables, so every report downloads as Word too.
//  No external library — a .docx is a zip of OOXML parts assembled with
//  ZipArchive; docx_escape() (lib/crm.php) handles XML-safe text.
// ---------------------------------------------------------------------------
function report_docx_build($doc, $sections, $fields, $data, $lh) {
    if (!class_exists('ZipArchive')) return [null, 'The "zip" PHP extension is not enabled on this server.'];
    $esc = function($s) { return function_exists('docx_escape') ? docx_escape((string)$s) : htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8'); };
    // Company brand colour (hex, no #) for the Word accents, else default blue.
    $BRAND = (!empty($lh['band']) && is_array($lh['band'])) ? sprintf('%02X%02X%02X', (int)$lh['band'][0], (int)$lh['band'][1], (int)$lh['band'][2]) : '1E40AF';
    // Document-control identity from the report type, for the header.
    $fmtNo = trim((string)($doc['format_number'] ?? '')); $docCtl = trim((string)($doc['doc_control_no'] ?? '')); $rev = trim((string)($doc['revision'] ?? ''));
    if ($fmtNo === '' && $docCtl === '' && $rev === '' && !empty($doc['report_type_id'])) {
        $rt = ops_one("SELECT format_number, doc_control_no, revision FROM report_types WHERE id=?", [(int)$doc['report_type_id']]);
        if ($rt) { $fmtNo = trim((string)($rt['format_number'] ?? '')); $docCtl = trim((string)($rt['doc_control_no'] ?? '')); $rev = trim((string)($rt['revision'] ?? '')); }
    }
    // --- OOXML building blocks ---------------------------------------------
    $run = function($t, $bold = false, $sz = 20, $color = null, $italic = false) use ($esc) {
        $rpr = '';
        if ($bold) $rpr .= '<w:b/>';
        if ($italic) $rpr .= '<w:i/>';
        if ($sz) $rpr .= '<w:sz w:val="' . (int)$sz . '"/><w:szCs w:val="' . (int)$sz . '"/>';
        if ($color) $rpr .= '<w:color w:val="' . $color . '"/>';
        $rpr = $rpr ? '<w:rPr>' . $rpr . '</w:rPr>' : '';
        return '<w:r>' . $rpr . '<w:t xml:space="preserve">' . $esc($t) . '</w:t></w:r>';
    };
    $para = function($runsXml, $opts = []) {
        $ppr = '';
        $spacing = $opts['after'] ?? 60;
        $ppr .= '<w:spacing w:after="' . (int)$spacing . '"' . (isset($opts['before']) ? ' w:before="' . (int)$opts['before'] . '"' : '') . '/>';
        if (!empty($opts['shade'])) $ppr .= '<w:shd w:val="clear" w:fill="' . $opts['shade'] . '"/>';
        return '<w:p><w:pPr>' . $ppr . '</w:pPr>' . $runsXml . '</w:p>';
    };
    $body = '';
    $valOf = function($f) use ($data) {
        $k = $f['fkey']; $v = $data[$k] ?? '';
        if ($f['ftype'] === 'multiselect' && is_array($v)) { $o = idems_field_options($f); return implode(', ', array_map(fn($x) => $o[$x] ?? $x, $v)); }
        if (in_array($f['ftype'], ['select','radio','unit'], true)) { $o = idems_field_options($f); return (string)($o[$v] ?? $v); }
        if ($f['ftype'] === 'checkbox') return ($v === '1' || $v === 1) ? 'Yes' : 'No';
        return is_array($v) ? '' : (string)$v;
    };
    // A bordered table from a header row (labels) + body rows (arrays of text).
    // $mergeFlags[i]===true makes column i a true vertical-merge (rowspan) column:
    // consecutive identical values collapse into one merged Word cell.
    $mkTable = function($headers, $rows, $mergeFlags = []) use ($run, $esc) {
        $nc = max(1, count($headers));
        $borders = '<w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:color="BBBBBB"/><w:left w:val="single" w:sz="4" w:color="BBBBBB"/>'
            . '<w:bottom w:val="single" w:sz="4" w:color="BBBBBB"/><w:right w:val="single" w:sz="4" w:color="BBBBBB"/>'
            . '<w:insideH w:val="single" w:sz="4" w:color="CCCCCC"/><w:insideV w:val="single" w:sz="4" w:color="CCCCCC"/></w:tblBorders>';
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/>' . $borders . '</w:tblPr>';
        $cell = function($text, $bold, $shade, $vmerge = '') use ($run) {
            $tcpr = '<w:tcPr>' . ($shade ? '<w:shd w:val="clear" w:fill="' . $shade . '"/>' : '')
                  . ($vmerge === 'restart' ? '<w:vMerge w:val="restart"/>' : ($vmerge === 'cont' ? '<w:vMerge/>' : '')) . '</w:tcPr>';
            $content = $vmerge === 'cont' ? '' : $run($text, $bold, 17);
            return '<w:tc>' . $tcpr . '<w:p><w:pPr><w:spacing w:after="20"/></w:pPr>' . $content . '</w:p></w:tc>';
        };
        // header
        $xml .= '<w:tr>'; foreach ($headers as $h) $xml .= $cell((string)$h, true, 'EBEEF5'); $xml .= '</w:tr>';
        $prev = [];
        foreach ($rows as $r) {
            $r = array_values((array)$r);
            $xml .= '<w:tr>';
            for ($i = 0; $i < $nc; $i++) {
                $val = (string)($r[$i] ?? '');
                if (!empty($mergeFlags[$i])) {
                    if ($val !== '' && ($prev[$i] ?? null) === $val) { $xml .= $cell('', false, null, 'cont'); }
                    else { $prev[$i] = $val; $xml .= $cell($val, false, null, 'restart'); }
                } else {
                    $xml .= $cell($val, false, null);
                }
            }
            $xml .= '</w:tr>';
        }
        return $xml . '</w:tbl>' . '<w:p><w:pPr><w:spacing w:after="40"/></w:pPr></w:p>';
    };

    // --- Header block -------------------------------------------------------
    $body .= $para($run(($lh['name'] ?? '') ?: app_name(), true, 30, $BRAND));
    foreach (preg_split('/\r?\n/', (string)($lh['address'] ?? '')) as $al) { $al = trim($al); if ($al !== '') $body .= $para($run($al, false, 16, '5A5A5A'), ['after' => 20]); }
    if (!empty($lh['contact'])) $body .= $para($run($lh['contact'], false, 16, '5A5A5A'), ['after' => 20]);
    $body .= $para($run(strtoupper((string)($doc['type_name'] ?? $doc['type_code'] ?? 'INSPECTION REPORT')), true, 26, $BRAND), ['before' => 120]);
    $body .= $para($run('IRN: ' . ($doc['irn'] ?? ''), true, 18) . $run('    ' . ($doc['finalized'] ? '' : '(DRAFT — not yet issued)'), false, 16, 'C83C3C'));
    // Document-control identity (format / doc / revision), if set on the type.
    $dcBits = array_filter([$fmtNo!==''?'Format No.: '.$fmtNo:'', $docCtl!==''?'Doc. No.: '.$docCtl:'', $rev!==''?'Rev.: '.$rev:'']);
    if ($dcBits) $body .= $para($run(implode('     ', $dcBits), false, 15, '5A5A5A'), ['after' => 40]);

    // --- Key references grid (2-col table) ---------------------------------
    // If the form itself carries the header fields (an "Inspection details"
    // section with client, vendor, PO, drawing, QAP, date, location…), DON'T
    // repeat them here — show only the outcome fields the form doesn't hold, so
    // nothing prints twice (mirrors the PDF de-dup). §dedup
    $hdrKeys = ['client','vendor','manufacturer','po','po_number','project','drawing','drawing_no','qap','qap_rev','standards','location','inspection_date','material','material_grade'];
    $formCovers = 0; foreach ($fields as $ff) if (in_array(strtolower((string)$ff['fkey']), $hdrKeys, true)) $formCovers++;
    $formHasHeader = $formCovers >= 3;
    if ($formHasHeader) {
        $kv = [
            'Standards' => $doc['standards'] ?? '', 'Issue date' => $doc['issue_date'] ?? '',
            'Result' => lk_options_or('inspection_result', IDEMS_RESULTS)[$doc['result'] ?? ''] ?? '',
            'Release' => lk_options_or('release_status', IDEMS_RELEASE)[$doc['release_status'] ?? ''] ?? '',
        ];
    } else {
        $kv = [
            'Client' => $doc['client_disp'] ?? ($doc['client_name'] ?? ''), 'Vendor / Mfr' => $doc['vendor_disp'] ?? ($doc['vendor_name'] ?? ''),
            'Project' => trim(($doc['project_code'] ?? '') . ' ' . ($doc['project_name'] ?? '')), 'PO' => $doc['po_ref'] ?? '',
            'Drawing' => trim(($doc['drawing_no'] ?? '') . ' ' . (($doc['drawing_rev'] ?? '') ? 'Rev ' . $doc['drawing_rev'] : '')), 'QAP rev' => $doc['qap_rev'] ?? '',
            'Standards' => $doc['standards'] ?? '', 'Location' => $doc['location'] ?? '',
            'Inspection date' => $doc['inspection_date'] ?? '', 'Issue date' => $doc['issue_date'] ?? '',
        ];
    }
    $kvRows = [];
    $pairKeys = array_keys(array_filter($kv, fn($v) => trim((string)$v) !== ''));
    for ($i = 0; $i < count($pairKeys); $i += 2) {
        $k1 = $pairKeys[$i]; $k2 = $pairKeys[$i + 1] ?? null;
        $kvRows[] = [$k1 . ': ' . $kv[$k1], $k2 !== null ? ($k2 . ': ' . $kv[$k2]) : ''];
    }
    if ($kvRows) $body .= $mkTable(['Reference', 'Reference'], $kvRows);

    // --- Body sections ------------------------------------------------------
    $bySec = []; foreach ($fields as $f) $bySec[(int)$f['section_id']][] = $f;
    // Empty fields — and sections left entirely empty — are dropped from the
    // issued Word document too, so it matches the PDF (no bare labels / headings). §empty
    $hasContent = function($f) use ($data) {
        $t = $f['ftype'] ?? ''; $k = $f['fkey'] ?? '';
        if (in_array($t, ['richtext','sigblock','heading','note'], true)) return true;
        if ($t === 'photo') return ($data[$k.'__photo_denied'] ?? '') === '1';   // the Word export can't embed images; keep only a "denied" statement
        if ($t === 'table') { $v = $data[$k] ?? null; return is_array($v) && count($v) > 0; }
        $v = $data[$k] ?? '';
        return is_array($v) ? count($v) > 0 : trim((string)$v) !== '';
    };
    $secList = $sections; $secList[] = ['id' => 0, 'title' => ''];
    $sg = null;   // workflow signatures, resolved lazily if a sigblock needs them
    foreach ($secList as $s) {
        if (!idems_cond_visible($s, $data)) continue;
        $fl = array_values(array_filter($bySec[(int)$s['id']] ?? [], fn($f) => idems_cond_visible($f, $data)));
        $fl = array_values(array_filter($fl, $hasContent));
        if (!$fl) continue;
        // Per-section print layout: start-on-new-page and keep-with-next (the
        // section heading won't orphan at the bottom of a page).
        $secPb = !empty($s['page_break_before']) ? '<w:pageBreakBefore/>' : '';
        $secKt = !empty($s['keep_together']) ? '<w:keepNext/><w:keepLines/>' : '';
        if (($s['title'] ?? '') !== '') {
            $body .= '<w:p><w:pPr>' . $secPb . $secKt . '<w:spacing w:before="120" w:after="40"/></w:pPr>' . $run($s['title'], true, 22, $BRAND) . '</w:p>';
        } elseif ($secPb !== '') {
            $body .= '<w:p><w:pPr>' . $secPb . '</w:pPr></w:p>';
        }
        // When a section holds a SINGLE table/photo/sign-off field, its title
        // already names it — don't print the field's own label too. §dedup
        $solo = (count($fl) === 1 && trim((string)($s['title'] ?? '')) !== '');
        foreach ($fl as $f) {
            if (in_array($f['ftype'], ['heading','note'], true)) { $body .= $para($run($f['label'], $f['ftype'] === 'heading', 19, '505050')); continue; }
            if (($f['ftype'] ?? '') === 'richtext') {
                if (trim((string)($f['label'] ?? '')) !== '') $body .= $para($run($f['label'], true, 18, '464646'), ['after' => 20]);
                foreach (preg_split('/\r?\n/', (string)($f['options'] ?? '')) as $ln) {
                    if (trim($ln) === '') { $body .= $para($run('', false, 8), ['after' => 20]); continue; }
                    $body .= $para($run($ln, false, 17, '5A5A5A'), ['after' => 20]);
                }
                continue;
            }
            if (($f['ftype'] ?? '') === 'sigblock') {
                if ($sg === null) $sg = function_exists('idems_report_signatures') ? idems_report_signatures($doc) : [];
                $rows = idems_sigblock_rows($f, $data, $sg);
                if (!$solo && trim((string)($f['label'] ?? '')) !== '') $body .= $para($run($f['label'], true, 18, '464646'), ['after' => 20]);
                $tr = [];
                foreach ($rows as $r) $tr[] = [$r['role'], $r['name'], $r['desig'], $r['date'], !empty($r['img']) ? 'Signed (on file)' : ''];
                $body .= $mkTable(['Role', 'Name', 'Designation', 'Date', 'Signature'], $tr);
                continue;
            }
            if (($f['ftype'] ?? '') === 'photo' && ($data[$f['fkey'].'__photo_denied'] ?? '') === '1') {
                $note = trim((string)($data[$f['fkey'].'__photo_denied_note'] ?? ''));
                $stmt = 'Photographs were not permitted / were denied by the client / vendor at the time of inspection.' . ($note !== '' ? ' ' . $note : '');
                $body .= $para(($solo ? '' : $run($f['label'] . ': ', true, 18, '5A5A5A')) . $run($stmt, false, 18, '963C3C')); continue;
            }
            if (in_array($f['ftype'], ['photo','file','signature'], true)) continue;   // images not embedded in the plain Word export
            if ($f['ftype'] === 'table') {
                $v = $data[$f['fkey']] ?? null; if (!is_array($v) || !$v) continue;
                $defs = idems_table_col_defs($f); $cols = []; $mergeFlags = [];
                foreach ($defs as $ck => $d) { $cols[$ck] = $d['label']; $mergeFlags[] = !empty($d['merge']); }
                if (!$solo) $body .= $para($run($f['label'], true, 18, '464646'), ['after' => 20]);
                $rows = [];
                foreach ($v as $r) { $r = (array)$r; $line = []; foreach ($cols as $ck => $cl) $line[] = (string)($r[$ck] ?? ''); $rows[] = $line; }
                $body .= $mkTable(array_values($cols), $rows, $mergeFlags);
                continue;
            }
            $val = $valOf($f);
            if ($f['ftype'] === 'textarea') {
                $body .= $para($run($f['label'] . ':', true, 18, '5A5A5A'), ['after' => 10]);
                foreach (preg_split('/\r?\n/', (string)$val) as $ln) $body .= $para($run($ln, false, 18), ['after' => 20]);
            } else {
                $body .= $para($run($f['label'] . ':  ', true, 18, '5A5A5A') . $run((string)$val, false, 18));
            }
        }
    }
    // --- Remarks + Release Note block (RN/IRN) ------------------------------
    if (!empty($doc['remarks'])) {
        $body .= $para($run('Remarks', true, 20, $BRAND), ['before' => 100, 'after' => 30]);
        foreach (preg_split('/\r?\n/', (string)$doc['remarks']) as $ln) $body .= $para($run($ln, false, 18), ['after' => 20]);
    }
    if (in_array(strtoupper((string)($doc['type_code'] ?? '')), ['RN','IRN'], true) && empty($fields)) {
        $rd = is_array($data) ? $data : [];
        $items = $rd['rn_items'] ?? null;
        if (is_array($items) && !empty($items['rows']) && !empty($items['cols'])) {
            $body .= $para($run('Products / items offered', true, 20, $BRAND), ['before' => 100, 'after' => 30]);
            $body .= $mkTable(array_map('strval', $items['cols']), $items['rows']);
        }
        $def = idems_release_note_defaults();
        $body .= $para($run('Remarks', true, 20, $BRAND), ['before' => 80, 'after' => 30]);
        $kind = trim((string)($rd['rn_kind'] ?? '')) ?: 'Stage / Final';
        $body .= $para($run('This is a ' . $kind . ' Inspection Report.', false, 18));
        $disp = trim((string)($rd['rn_disposition'] ?? '')) ?: 'Inspected Quantity is Rejected / Passed Quantity is Cleared for dispatch.';
        $body .= $para($run($disp, false, 18));
        $body .= $para($run('Deviation accepted (if any): ' . (trim((string)($rd['rn_deviation'] ?? '')) ?: '—'), false, 18));
        $body .= $para($run('Inspection Report Number(s): ' . (trim((string)($rd['rn_ir_numbers'] ?? ($rd['source_irn'] ?? ''))) ?: '—'), false, 18));
        $repName = trim((string)($lh['name'] ?? '')) ?: 'Representative';
        $idrows = $rd['rn_identification'] ?? [];
        $irows = [];
        foreach ((is_array($idrows) ? $idrows : []) as $r) { $r = (array)$r; $irows[] = [(string)($r['ident'] ?? ''), (string)($r['location'] ?? ''), (string)($r['inspected_by'] ?? ''), '']; }
        if (!$irows) $irows[] = ['', '', '', ''];
        $body .= $para($run('', false, 8), ['after' => 20]);
        $body .= $mkTable(['Inspection Identification', 'Location of Identification', 'Inspected By', 'Signature of ' . $repName], $irows);
        $body .= $para($run($def['disclaimer'], false, 16, '5A5A5A', true), ['before' => 40]);
    }
    $body .= $para($run('System-generated by ' . ((($lh['name'] ?? '') ?: app_name())) . ' · IRN ' . ($doc['irn'] ?? '') . ' · ' . date('d M Y H:i') . ($doc['finalized'] ? ' · Issued/locked' : ' · DRAFT'), false, 14, '969696'), ['before' => 120]);

    // --- Running header + footer (auto page numbers) -----------------------
    // A real Word footer part with "Company · IRN" on the left and an automatic
    // "Page X of Y" (PAGE / NUMPAGES fields) on the right, plus a light header
    // carrying the company name and report title on every page.
    $wpml = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';
    $coName = ($lh['name'] ?? '') ?: app_name();
    $ftLeft = $coName . ($doc['irn'] ? '  ·  ' . $doc['irn'] : '');
    $footerXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:ftr ' . $wpml . '>'
        . '<w:p><w:pPr><w:pBdr><w:top w:val="single" w:sz="4" w:space="1" w:color="CCCCCC"/></w:pBdr>'
        . '<w:tabs><w:tab w:val="right" w:pos="9638"/></w:tabs><w:spacing w:after="0"/></w:pPr>'
        . '<w:r><w:rPr><w:sz w:val="15"/><w:color w:val="7D7D7D"/></w:rPr><w:t xml:space="preserve">' . $esc($ftLeft) . '</w:t></w:r>'
        . '<w:r><w:rPr><w:sz w:val="15"/><w:color w:val="7D7D7D"/></w:rPr><w:tab/><w:t xml:space="preserve">Page </w:t></w:r>'
        . '<w:r><w:rPr><w:sz w:val="15"/><w:color w:val="7D7D7D"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r>'
        . '<w:r><w:rPr><w:sz w:val="15"/><w:color w:val="7D7D7D"/></w:rPr><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>'
        . '<w:r><w:rPr><w:sz w:val="15"/><w:color w:val="7D7D7D"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r>'
        . '<w:r><w:rPr><w:sz w:val="15"/><w:color w:val="7D7D7D"/></w:rPr><w:t xml:space="preserve"> of </w:t></w:r>'
        . '<w:r><w:rPr><w:sz w:val="15"/><w:color w:val="7D7D7D"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r>'
        . '<w:r><w:rPr><w:sz w:val="15"/><w:color w:val="7D7D7D"/></w:rPr><w:instrText xml:space="preserve"> NUMPAGES </w:instrText></w:r>'
        . '<w:r><w:rPr><w:sz w:val="15"/><w:color w:val="7D7D7D"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r>'
        . '</w:p></w:ftr>';
    $hdTitle = strtoupper((string)($doc['type_name'] ?? $doc['type_code'] ?? 'INSPECTION REPORT'));
    $headerXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:hdr ' . $wpml . '>'
        . '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="4" w:space="1" w:color="CCCCCC"/></w:pBdr>'
        . '<w:tabs><w:tab w:val="right" w:pos="9638"/></w:tabs><w:spacing w:after="0"/></w:pPr>'
        . '<w:r><w:rPr><w:b/><w:sz w:val="16"/><w:color w:val="' . $BRAND . '"/></w:rPr><w:t xml:space="preserve">' . $esc($coName) . '</w:t></w:r>'
        . '<w:r><w:rPr><w:sz w:val="15"/><w:color w:val="7D7D7D"/></w:rPr><w:tab/><w:t xml:space="preserve">' . $esc($hdTitle) . '</w:t></w:r>'
        . '</w:p></w:hdr>';

    // --- Assemble the .docx package ----------------------------------------
    $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<w:body>' . $body
        . '<w:sectPr><w:headerReference w:type="default" r:id="rIdHdr1"/><w:footerReference w:type="default" r:id="rIdFtr1"/>'
        . '<w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
        . '</w:body></w:document>';
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
        . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
        . '</Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';
    $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rIdHdr1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
        . '<Relationship Id="rIdFtr1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
        . '</Relationships>';
    $tmp = tempnam(sys_get_temp_dir(), 'rdocx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) return [null, 'Could not create the Word document.'];
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->addFromString('word/header1.xml', $headerXml);
    $zip->addFromString('word/footer1.xml', $footerXml);
    $zip->addFromString('word/_rels/document.xml.rels', $docRels);
    $zip->close();
    $bin = file_get_contents($tmp);
    @unlink($tmp);
    return [$bin, null];
}
// Assemble the signature payload for a report (inspector + final approver), from
// snapshots taken at finalize where available, else live profile signatures.
function idems_report_signatures($doc) {
    $out = ['inspector'=>[], 'approver'=>[]];
    // inspector
    $ins = $doc['inspector_id'] ? ops_one("SELECT name, designation, emp_code FROM inspectors WHERE id=?", [$doc['inspector_id']]) : null;
    $snapI = ops_one("SELECT data, created_at FROM report_files WHERE report_doc_id=? AND kind='sig_inspector' ORDER BY id DESC LIMIT 1", [$doc['id']]);
    $out['inspector'] = [
        'img'   => idems_sig_jpeg($snapI['data'] ?? inspector_signature($doc['inspector_id'] ?? 0)),
        'name'  => $ins['name'] ?? '', 'desig' => $ins ? (DESIGNATIONS[$ins['designation']] ?? $ins['designation']) : '',
        'meta'  => trim(($ins['emp_code'] ?? '') . ($doc['branch_code'] ? ' · ' . $doc['branch_code'] : '')),
        'time'  => ($doc['finalized_at'] ?? '') ? 'Signed: ' . date('d M Y H:i', strtotime($doc['finalized_at'])) : '',
    ];
    // approver = last APPROVED step's actor (a user), else the finalizer
    $ap = ops_one("SELECT a.acted_by, a.acted_at, a.resolved_user_id FROM report_approvals a WHERE a.report_doc_id=? AND a.status='APPROVED' ORDER BY a.level DESC, a.id DESC LIMIT 1", [$doc['id']]);
    $apUser = $ap && $ap['resolved_user_id'] ? ops_one("SELECT first_name, last_name, position_title, role FROM users WHERE id=?", [$ap['resolved_user_id']]) : null;
    $snapA = ops_one("SELECT data FROM report_files WHERE report_doc_id=? AND kind='sig_approver' ORDER BY id DESC LIMIT 1", [$doc['id']]);
    if ($ap || $doc['approved_by']) {
        $out['approver'] = [
            'img'   => idems_sig_jpeg($snapA['data'] ?? user_signature($ap['resolved_user_id'] ?? 0)),
            'name'  => $apUser ? (trim(($apUser['first_name'] ?? '') . ' ' . ($apUser['last_name'] ?? '')) ?: '') : ($ap['acted_by'] ?? $doc['approved_by'] ?? ''),
            'desig' => $apUser ? ($apUser['position_title'] ?: (ORG_ROLES[$apUser['role']] ?? '')) : '',
            'meta'  => 'Approved',
            'time'  => ($ap['acted_at'] ?? $doc['approved_at'] ?? '') ? 'Approved: ' . date('d M Y H:i', strtotime($ap['acted_at'] ?? $doc['approved_at'])) : '',
        ];
    }
    return $out;
}
// Snapshot the inspector + approver signatures onto the report (called at finalize)
// so they are frozen even if a profile signature later changes.
function idems_snapshot_signatures($doc) {
    $pdo = db();
    $insSig = inspector_signature($doc['inspector_id'] ?? 0);
    if ($insSig) { $pdo->prepare("DELETE FROM report_files WHERE report_doc_id=? AND kind='sig_inspector'")->execute([$doc['id']]);
        $pdo->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,created_by,created_at) VALUES (?,?,?,?,?,?,?,?)")->execute([$doc['id'],'','sig_inspector','inspector_sign.png','image/png',$insSig,user_name(current_user()),date('c')]); }
    $ap = ops_one("SELECT resolved_user_id FROM report_approvals WHERE report_doc_id=? AND status='APPROVED' ORDER BY level DESC, id DESC LIMIT 1", [$doc['id']]);
    $apSig = user_signature($ap['resolved_user_id'] ?? 0);
    if ($apSig) { $pdo->prepare("DELETE FROM report_files WHERE report_doc_id=? AND kind='sig_approver'")->execute([$doc['id']]);
        $pdo->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,created_by,created_at) VALUES (?,?,?,?,?,?,?,?)")->execute([$doc['id'],'','sig_approver','approver_sign.png','image/png',$apSig,user_name(current_user()),date('c')]); }
}
// ---- Report PDF handler ----
function ops_idems_pdf($method) {
    $doc = ops_one("SELECT d.*, bp.display_name client_disp, bp.legal_name client_name, v.display_name vendor_disp, v.legal_name vendor_name, rt.name type_name
        FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.client_id LEFT JOIN business_partners v ON v.id=d.vendor_id LEFT JOIN report_types rt ON rt.id=d.report_type_id
        WHERE d.id=? AND d.deleted=0", [(int)($_GET['id'] ?? 0)]);
    if (!$doc) { http_response_code(404); echo 'Not found'; return true; }
    ops_require(is_master() || can('mod.idems.view'), 'You cannot view this report.');
    // Original / Duplicate / Triplicate — only meaningful once the report is
    // finalized; a draft always prints as DRAFT regardless.
    $copyMap = ['original' => 'ORIGINAL', 'duplicate' => 'DUPLICATE', 'triplicate' => 'TRIPLICATE'];
    $copy = !empty($doc['finalized']) ? ($copyMap[strtolower((string)($_GET['copy'] ?? ''))] ?? '') : '';
    $lh = function_exists('quote_letterhead') ? quote_letterhead() : ['name'=>app_name()];
    [$secs, $flds] = idems_render_schema($doc);   // §33 — frozen schema for an issued report, live for a draft
    $pdf = report_pdf_build($doc, $secs, $flds,
        json_decode($doc['data'] ?: '[]', true) ?: [], idems_doc_files($doc['id']), $lh, idems_report_signatures($doc), $copy);
    idems_log('report_doc', $doc['id'], 'PDF', ['irn'=>$doc['irn'], 'copy'=>$copy ?: 'draft']);
    $suffix = $copy !== '' ? '_' . $copy : (empty($doc['finalized']) ? '_DRAFT' : '');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/','_',$doc['irn']) . $suffix . '.pdf"');
    echo $pdf; return true;
}

// ===========================================================================
//  Template / form PREVIEW with sample data  (§spec-60)
//  A designer sees, in one click, how the finished report looks — filled with
//  realistic dummy data — before any real inspection exists. Two flavours:
//  the system PDF (report type), and the company .docx (a template).
// ===========================================================================
// Plausible sample value for one field, by its type.
function idems_sample_value($f) {
    $lbl = trim((string)($f['label'] ?? $f['fkey'] ?? 'Sample'));
    switch ($f['ftype']) {
        case 'number': return '10';
        case 'date':   return date('Y-m-d');
        case 'time':   return '10:30';
        case 'checkbox': return '1';
        case 'select': case 'radio': { $o = idems_field_options($f); return $o ? (string)array_key_first($o) : 'Yes'; }
        case 'multiselect': { $o = idems_field_options($f); return $o ? [(string)array_key_first($o)] : ['Sample']; }
        case 'textarea': return 'Sample ' . $lbl . ' — inspection carried out as per the applicable standard; results were within the acceptance criteria.';
        case 'instrument': return 'Vernier Caliper (VC-114)';
        case 'calc': return '';
        case 'table': {
            $defs = idems_table_col_defs($f); $rows = [];
            for ($r = 1; $r <= 2; $r++) {
                $row = [];
                foreach ($defs as $ck => $d) {
                    if ($d['type'] === 'number') $row[$ck] = (string)($r * 5);
                    elseif ($d['type'] === 'date') $row[$ck] = date('Y-m-d');
                    elseif ($d['type'] === 'select') {
                        $o0 = (string)($d['options'][0] ?? '');
                        if (stripos($o0, 'lookup:') === 0) { $opts = idems_col_options(null, $o0); $row[$ck] = $opts[0] ?? 'Acceptable'; }
                        elseif (stripos($o0, 'call:') === 0) { $row[$ck] = 'Sample ' . $d['label']; }
                        else $row[$ck] = $o0 !== '' ? $o0 : 'Yes';
                    }
                    else $row[$ck] = $d['label'] . ' ' . $r;
                }
                $rows[] = $row;
            }
            return $rows;
        }
        default: return 'Sample ' . $lbl;
    }
}
// Sample data map for every non-decorative field of a report type.
function idems_sample_data($fields) {
    $data = [];
    foreach ($fields as $f) {
        if (in_array($f['ftype'], ['heading','note','richtext','photo','file','signature','sigblock','gps','qr'], true)) continue;
        $data[$f['fkey']] = idems_sample_value($f);
    }
    return $data;
}
// A throwaway doc row for a preview render (never saved).
function idems_sample_doc($extra = []) {
    return array_merge([
        'id' => 0, 'irn' => 'SAMPLE / PREVIEW', 'report_type_id' => 0, 'type_code' => 'SAMPLE',
        'title' => 'Sample preview', 'client_name' => 'ABC Engineering Ltd', 'client_disp' => 'ABC Engineering Ltd',
        'vendor_name' => 'XYZ Manufacturing Pvt Ltd', 'vendor_disp' => 'XYZ Manufacturing Pvt Ltd',
        'inspection_date' => date('Y-m-d'), 'issue_date' => date('Y-m-d'), 'result' => 'ACCEPTED',
        'finalized' => 0, 'data' => '[]', 'remarks' => 'Sample remarks — this is a preview with dummy data, not a real inspection report.',
    ], $extra);
}
// GET /report-type-preview?type=ID — system-format PDF filled with sample data.
function ops_idems_type_preview() {
    ops_require(is_master() || can('idems.type.manage') || can('mod.idems.view'), 'You cannot preview report forms.');
    $typeId = (int)($_GET['type'] ?? 0);
    $type = $typeId ? ops_one("SELECT * FROM report_types WHERE id=?", [$typeId]) : null;
    if (!$type) { http_response_code(404); echo 'Report type not found'; return true; }
    $fields = idems_fields($typeId);
    $data = idems_sample_data($fields);
    $doc = idems_sample_doc(['report_type_id' => $typeId, 'type_code' => $type['code'], 'title' => $type['name'], 'data' => json_encode($data)]);
    $lh = function_exists('quote_letterhead') ? quote_letterhead() : ['name' => app_name()];
    $sigs = ['inspector' => ['name' => 'A. Inspector', 'desig' => 'Sr. Inspector', 'meta' => 'SAMPLE', 'time' => date('d M Y')],
             'approver'  => ['name' => 'B. Approver',  'desig' => 'Reviewer',      'meta' => 'SAMPLE', 'time' => date('d M Y')]];
    $pdf = report_pdf_build($doc, idems_sections($typeId), $fields, $data, [], $lh, $sigs, '');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="SAMPLE_' . preg_replace('/[^A-Za-z0-9._-]/','_',$type['code']) . '.pdf"');
    echo $pdf; return true;
}
// GET /report-template-preview?id=ID — the company .docx filled with sample data.
function ops_idems_template_preview() {
    ops_require(is_master() || can('idems.type.manage'), 'You cannot preview templates.');
    $tpl = ops_one("SELECT * FROM report_templates WHERE id=?", [(int)($_GET['id'] ?? 0)]);
    if (!$tpl) { flash('Template not found.', 'error'); redirect('/report-templates'); }
    if (empty($tpl['file_data'])) { flash('This template has no Word file to preview. Upload a .docx first.', 'error'); redirect('/report-template-edit?id=' . $tpl['id']); }
    if (!class_exists('ZipArchive')) { flash('The zip PHP extension is off here, so .docx preview cannot run.', 'error'); redirect('/report-templates'); }
    $typeId = (int)($tpl['report_type_id'] ?? 0);
    $fields = $typeId ? idems_fields($typeId) : [];
    $data = idems_sample_data($fields);
    $doc = idems_sample_doc(['report_type_id' => $typeId, 'data' => json_encode($data)]);
    [$map, $tables] = idems_doc_token_map($doc, $fields, $data, $tpl);
    // fill a few standard header tokens with sample values too
    $map = array_merge(['client' => 'ABC Engineering Ltd', 'project' => 'Sample Project', 'inspector' => 'A. Inspector',
        'approver' => 'B. Approver', 'irn' => 'SAMPLE/PREVIEW', 'result' => 'Accepted', 'inspection_date' => date('d-m-Y'),
        'issue_date' => date('d-m-Y')], $map);
    [$bin, $err] = report_docx_fill(base64_decode($tpl['file_data']), $map, $tables);
    if ($err) { flash('Preview could not be generated: ' . $err, 'error'); redirect('/report-templates'); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: inline; filename="SAMPLE_' . preg_replace('/[^A-Za-z0-9._-]/','_', $tpl['name'] ?: 'template') . '.docx"');
    echo $bin; return true;
}
// ---- Controlled timestamp / date edit (Branch App Manager only, Part 22) ----
function ops_idems_timestamp($method) {
    if ($method !== 'POST') redirect('/documents');
    $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_POST['id'] ?? 0)]);
    if (!$doc) { http_response_code(404); view('notfound'); return true; }
    ops_require(is_master() || can('idems.timestamp.edit'), 'Only the Branch Application Manager may adjust locked dates.');
    $field = $_POST['field'] ?? '';
    $allowed = ['inspection_date','issue_date'];
    if (!in_array($field, $allowed, true)) { flash('That field cannot be adjusted.', 'error'); redirect('/document?id=' . $doc['id']); }
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') { flash('A reason is mandatory to change a date.', 'error'); redirect('/document?id=' . $doc['id']); }
    $new = trim($_POST['value'] ?? '');
    $old = $doc[$field] ?? '';
    db()->prepare("UPDATE report_docs SET $field=?, updated_at=? WHERE id=?")->execute([$new, date('c'), $doc['id']]);
    idems_log('report_doc', $doc['id'], 'TIMESTAMP_EDIT', ['irn'=>$doc['irn'], 'field'=>$field, 'old'=>$old, 'new'=>$new, 'reason'=>$reason]);
    flash('Date adjusted and logged in the tamper-proof audit trail.');
    redirect('/document?id=' . $doc['id']);
    return true;
}

// ===========================================================================
//  Phase 5: client-specific formats — fill an uploaded .docx so output matches
//  the client's own approved template exactly (their fonts / headers / footers /
//  logo / tables). No client names are seeded; you upload your own templates.
// ===========================================================================
// Standard tokens available in every template, plus the report type's designed fields.
function idems_standard_tokens($doc) {
    $doc = (array)$doc;
    return [
        'irn'=>$doc['irn'] ?? '', 'report_type'=>$doc['type_name'] ?? ($doc['type_code'] ?? ''), 'type_code'=>$doc['type_code'] ?? '',
        'title'=>$doc['title'] ?? '', 'client'=>($doc['client_disp'] ?? '') ?: ($doc['client_name'] ?? ''), 'vendor'=>($doc['vendor_disp'] ?? '') ?: ($doc['vendor_name'] ?? ''),
        'project'=>trim(($doc['project_code'] ?? '').' '.($doc['project_name'] ?? '')), 'project_code'=>$doc['project_code'] ?? '', 'project_name'=>$doc['project_name'] ?? '',
        'po'=>$doc['po_ref'] ?? '', 'drawing'=>trim(($doc['drawing_no'] ?? '').' '.(!empty($doc['drawing_rev'])?'Rev '.$doc['drawing_rev']:'')),
        'drawing_no'=>$doc['drawing_no'] ?? '', 'drawing_rev'=>$doc['drawing_rev'] ?? '', 'qap_rev'=>$doc['qap_rev'] ?? '',
        'standards'=>$doc['standards'] ?? '', 'location'=>$doc['location'] ?? '', 'product_category'=>$doc['product_category'] ?? '', 'material_grade'=>$doc['material_grade'] ?? '',
        'inspection_date'=>$doc['inspection_date'] ?? '', 'issue_date'=>$doc['issue_date'] ?? '',
        'inspector'=>$doc['inspector_name'] ?? '', 'approver'=>$doc['approved_by'] ?? '',
        'result'=>lk_options_or('inspection_result', IDEMS_RESULTS)[$doc['result'] ?? ''] ?? '', 'release'=>lk_options_or('release_status', IDEMS_RELEASE)[$doc['release_status'] ?? ''] ?? '', 'remarks'=>$doc['remarks'] ?? '',
        'company'=>idems_company_code(), 'branch'=>$doc['branch_code'] ?? '', 'today'=>date('d M Y'),
        'status'=>lk_options_or('report_status', IDEMS_STATUS)[$doc['status'] ?? ''] ?? ($doc['status'] ?? ''),
    ];
}
// Build [scalarMap, tablesMap] for a report from standard tokens + its designed fields.
function idems_doc_token_map($doc, $fields, $data, $tpl = null) {
    $map = idems_standard_tokens($doc);
    if ($tpl) { $map['doc_number']=$tpl['document_number'] ?? ''; $map['format_number']=$tpl['format_number'] ?? ''; $map['doc_revision']=$tpl['doc_revision'] ?? ''; $map['format_issue_date']=$tpl['issue_date'] ?? ''; }
    $tables = [];
    foreach ($fields as $f) {
        $k = $f['fkey']; $v = $data[$k] ?? '';
        if ($f['ftype'] === 'table') { $tables[$k] = is_array($v) ? array_map(fn($r)=>(array)$r, $v) : []; continue; }
        if (in_array($f['ftype'], ['photo','file','signature','sigblock'], true)) continue;
        if ($f['ftype'] === 'multiselect' && is_array($v)) { $o=idems_field_options($f); $v=implode(', ', array_map(fn($x)=>$o[$x]??$x,$v)); }
        elseif (in_array($f['ftype'], ['select','radio'], true)) { $o=idems_field_options($f); $v=$o[$v]??$v; }
        elseif ($f['ftype'] === 'checkbox') $v = ($v==='1'||$v===1)?'Yes':'No';
        $map[$k] = is_array($v) ? '' : (string)$v;
    }
    return [$map, $tables];
}
// Generic repeatable-table row expansion: a <w:tr> containing {{fkey.col}} tokens
// is cloned once per data row (mirrors the quote line-row mechanism, generalised).
function report_docx_expand_tables($xml, $tables) {
    foreach ($tables as $fkey => $rows) {
        $q = preg_quote($fkey, '/');
        $pat = '/(<w:tr\b(?:(?!<w:tr\b).)*?\{\{' . $q . '\.[a-z0-9_]+\}\}.*?<\/w:tr>)/us';
        if (!preg_match($pat, $xml, $mm)) continue;
        $rowTpl = $mm[1]; $out = '';
        foreach ($rows as $r) {
            $row = preg_replace_callback('/\{\{' . $q . '\.([a-z0-9_]+)\}\}/u', function ($m) use ($r) { return docx_escape($r[$m[1]] ?? ''); }, $rowTpl);
            $out .= $row;
        }
        if ($out === '') $out = preg_replace('/\{\{' . $q . '\.[a-z0-9_]+\}\}/u', '', $rowTpl);
        $xml = str_replace($rowTpl, $out, $xml);
    }
    return $xml;
}
// Fill a .docx template with the report's scalar map + repeatable tables.
function report_docx_fill($binary, $map, $tables) {
    if (!class_exists('ZipArchive')) return [null, 'The "zip" PHP extension is not enabled on this server.'];
    $tmp = tempnam(sys_get_temp_dir(), 'idz');
    if ($tmp === false || file_put_contents($tmp, $binary) === false) return [null, 'Could not write a temporary file.'];
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) { @unlink($tmp); return [null, 'The template is not a valid .docx file.']; }
    $parts = [];
    for ($i = 0; $i < $zip->numFiles; $i++) { $n = $zip->getNameIndex($i); if (preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $n)) $parts[$n] = $zip->getFromName($n); }
    foreach ($parts as $n => $xml) {
        $xml = docx_repair_tokens($xml);
        if (strpos($n, 'document.xml') !== false) $xml = report_docx_expand_tables($xml, $tables);
        $xml = docx_replace($xml, $map);
        $zip->deleteName($n); $zip->addFromString($n, $xml);
    }
    $zip->close();
    $out = file_get_contents($tmp); @unlink($tmp);
    return [$out, null];
}
// Choose the most-specific active template for a report (client+type > type > client > office > any).
function idems_pick_template($doc) {
    $cands = ops_all("SELECT * FROM report_templates WHERE active=1 AND file_data<>''
        AND (report_type_id IS NULL OR report_type_id=?) AND (client_id IS NULL OR client_id=?) AND (office_id IS NULL OR office_id=?)",
        [$doc['report_type_id'] ?: 0, $doc['client_id'] ?: 0, $doc['office_id'] ?: 0]);
    if (!$cands) return null;
    $score = function($t) use ($doc) { $s=0; if($t['report_type_id'])$s+=4; if($t['client_id'])$s+=2; if($t['office_id'])$s+=1; if($t['is_default'])$s+=0.5; return $s; };
    usort($cands, fn($a,$b)=>$score($b)<=>$score($a));
    return $cands[0];
}
// §33 — freeze the presentation of a report at issue: the exact form schema and
// the exact company template (bytes included). Called once at finalize. After
// this, editing the form or the template can never change THIS issued report.
function idems_freeze_presentation($docId) {
    $doc = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$docId]);
    if (!$doc || !empty($doc['frozen_at'])) return;   // once only
    $schema = ['sections' => idems_sections($doc['report_type_id']), 'fields' => idems_fields($doc['report_type_id'])];
    $tpl = idems_pick_template($doc);
    db()->prepare("UPDATE report_docs SET frozen_schema=?, frozen_template_json=?, frozen_at=? WHERE id=?")
        ->execute([json_encode($schema), $tpl ? json_encode($tpl) : '', date('c'), (int)$docId]);
    if (function_exists('idems_log')) idems_log('report_doc', (int)$docId, 'FREEZE_PRESENTATION',
        ['irn'=>$doc['irn'], 'field'=>$tpl ? ('template ' . ($tpl['name'] ?: $tpl['file_name'])) : 'system format']);
}
// The sections+fields to RENDER a report with: its frozen snapshot once issued,
// otherwise the live schema (drafts, and reports issued before this existed).
function idems_render_schema($doc) {
    if (!empty($doc['finalized']) && !empty($doc['frozen_schema'])) {
        $fs = json_decode($doc['frozen_schema'], true);
        if (is_array($fs) && (isset($fs['sections']) || isset($fs['fields'])))
            return [$fs['sections'] ?? [], $fs['fields'] ?? []];
    }
    return [idems_sections($doc['report_type_id']), idems_fields($doc['report_type_id'])];
}
// The company template to RENDER a report with: its frozen copy once issued,
// otherwise the current best pick.
function idems_render_template($doc) {
    if (!empty($doc['finalized']) && !empty($doc['frozen_template_json'])) {
        $t = json_decode($doc['frozen_template_json'], true);
        if (is_array($t) && !empty($t['file_data'])) return $t;
    }
    return idems_pick_template($doc);
}

// §32 — template lifecycle.
const TEMPLATE_STATUS = ['DRAFT'=>'Draft','IN_REVIEW'=>'In review','PUBLISHED'=>'Published','SUPERSEDED'=>'Superseded','ARCHIVED'=>'Archived'];
function template_status_pill($s) { return ['DRAFT'=>'p-warn','IN_REVIEW'=>'p-info','PUBLISHED'=>'p-ok','SUPERSEDED'=>'p-mut','ARCHIVED'=>'p-mut'][$s] ?? 'p-mut'; }
// A document controller reviews and approves a report format before it goes
// live (ISO 17020 §documented-information control). Anyone may draft & submit;
// only a controller (this permission, or a master) may approve/reject/publish.
function idems_can_approve_template() { return is_master() || (function_exists('can') && can('idems.template.approve')); }

// §59 — validate a template before it is published/relied upon. Returns
// ['level'=>'PASS'|'WARNING'|'ERROR', 'issues'=>[['level'=>..,'msg'=>..],…]].
function idems_template_validate($tpl) {
    $issues = []; $hardErr = false;
    $add = function($lvl, $msg) use (&$issues, &$hardErr) { $issues[] = ['level'=>$lvl,'msg'=>$msg]; if ($lvl==='ERROR') $hardErr = true; };
    $data = (string)($tpl['file_data'] ?? '');
    if ($data === '') $add('ERROR', 'No Word (.docx) file has been uploaded.');
    else {
        $bin = base64_decode($data); $ok = false;
        if (class_exists('ZipArchive')) {
            $tmp = tempnam(sys_get_temp_dir(), 'tv'); file_put_contents($tmp, $bin);
            $z = new ZipArchive(); if ($z->open($tmp) === true) { $ok = $z->getFromName('word/document.xml') !== false; $z->close(); }
            @unlink($tmp);
        } else { $ok = strncmp($bin, 'PK', 2) === 0; }
        if (!$ok) $add('ERROR', 'The uploaded file is not a valid .docx (an old .doc or a corrupt file).');
    }
    $typeId = (int)($tpl['report_type_id'] ?? 0);
    if (!$typeId) $add('WARNING', 'No report type is set — tokens cannot be checked and auto-selection is weaker.');
    if ($typeId && $data !== '' && !$hardErr) {
        try {
            $text = report_docx_plain(base64_decode($data));
            preg_match_all('/\{\{([a-zA-Z0-9_.]+)\}\}/', $text, $m);
            $std = idems_type_tokens($typeId);
            $known = array_flip(array_merge($std['standard'], $std['fields']));
            $tableKeys = []; foreach ($std['tables'] as $t) { $tableKeys[explode('.', $t)[0]] = 1; }
            $used = []; $orphans = [];
            foreach (array_unique($m[1] ?? []) as $tk) {
                if (strpos($tk, '.') !== false) { [$b,] = explode('.', $tk, 2); $used[$b] = 1; if (!isset($tableKeys[$b])) $orphans[] = $tk; }
                elseif (isset($known[$tk])) $used[$tk] = 1;
                else $orphans[] = $tk;
            }
            if ($orphans) $add('WARNING', 'Placeholder(s) with no matching field — will stay blank: ' . implode(', ', array_slice(array_unique($orphans), 0, 8)) . (count($orphans) > 8 ? '…' : ''));
            foreach (idems_fields($typeId) as $f) {
                if (in_array($f['ftype'], ['heading','note'], true) || empty($f['required'])) continue;
                if (!isset($used[$f['fkey']])) $add('WARNING', 'Required field “' . $f['label'] . '” has no {{' . $f['fkey'] . '}} in the format.');
            }
        } catch (Throwable $e) { $add('WARNING', 'Placeholders could not be read: ' . $e->getMessage()); }
    }
    if (trim((string)($tpl['document_number'] ?? '')) === '') $add('WARNING', 'No document-control number is set for this format.');
    $level = 'PASS'; foreach ($issues as $i) { if ($i['level']==='ERROR') { $level='ERROR'; break; } if ($i['level']==='WARNING') $level='WARNING'; }
    return ['level'=>$level, 'issues'=>$issues];
}
// Publishing a template retires any other active template of the SAME scope
// (report type + client + office) — that is what "supersede" means (§32).
function idems_template_supersede_siblings($tpl) {
    $id = (int)$tpl['id']; $rt = (int)($tpl['report_type_id'] ?? 0); $cl = (int)($tpl['client_id'] ?? 0); $of = (int)($tpl['office_id'] ?? 0);
    // Filter scope in PHP — a NULL/blank column reads as 0, and this sidesteps
    // SQLite's affinity mismatch when a COALESCE() expression meets a bound param.
    $n = 0;
    foreach (ops_all("SELECT id, report_type_id, client_id, office_id FROM report_templates WHERE active=1 AND id<>?", [$id]) as $s) {
        if ((int)$s['report_type_id'] === $rt && (int)$s['client_id'] === $cl && (int)$s['office_id'] === $of) {
            db()->prepare("UPDATE report_templates SET status='SUPERSEDED', active=0, superseded_by=? WHERE id=?")->execute([$id, (int)$s['id']]);
            $n++;
        }
    }
    return $n;
}
// Available token names for a report type (for the reference panel).
function idems_type_tokens($typeId) {
    $std = ['irn','report_type','title','client','vendor','project','po','drawing','qap_rev','standards','location','inspection_date','issue_date','inspector','approver','result','release','remarks','company','branch','today','doc_number','format_number','doc_revision'];
    $fieldTokens = []; $tableTokens = [];
    foreach (idems_fields($typeId) as $f) {
        if (in_array($f['ftype'], ['heading','note','richtext','photo','file','signature','sigblock'], true)) continue;
        if ($f['ftype'] === 'table') { foreach (idems_table_cols($f) as $ck=>$cl) $tableTokens[] = $f['fkey'].'.'.$ck; }
        else $fieldTokens[] = $f['fkey'];
    }
    return ['standard'=>$std, 'fields'=>$fieldTokens, 'tables'=>$tableTokens];
}

// ---- Handler: generate the filled client-format .docx for a report ----
function ops_idems_docx($method) {
    $doc = ops_one("SELECT d.*, bp.display_name client_disp, bp.legal_name client_name, v.display_name vendor_disp, v.legal_name vendor_name, i.name inspector_name, rt.name type_name
        FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.client_id LEFT JOIN business_partners v ON v.id=d.vendor_id LEFT JOIN inspectors i ON i.id=d.inspector_id LEFT JOIN report_types rt ON rt.id=d.report_type_id
        WHERE d.id=? AND d.deleted=0", [(int)($_GET['id'] ?? 0)]);
    if (!$doc) { http_response_code(404); echo 'Not found'; return true; }
    ops_require(is_master() || can('mod.idems.view'), 'You cannot generate this report.');
    $tpl = idems_render_template($doc);   // §33 — the frozen template for an issued report, else the current pick
    [$secs, $fields] = idems_render_schema($doc);   // frozen once issued, live for a draft
    $data = json_decode($doc['data'] ?: '[]', true) ?: [];
    if ($tpl) {
        // A company .docx template is registered — fill it, so the client's exact
        // layout is preserved.
        [$map, $tables] = idems_doc_token_map($doc, $fields, $data, $tpl);
        [$bin, $err] = report_docx_fill(base64_decode($tpl['file_data']), $map, $tables);
    } else {
        // No template — build a clean, editable Word document from the report's
        // own layout, so every report still downloads as Word.
        $lh = function_exists('quote_letterhead') ? quote_letterhead() : ['name' => app_name()];
        [$bin, $err] = report_docx_build($doc, $secs, $fields, $data, $lh);
    }
    if ($err) { flash('Could not generate the document: ' . $err, 'error'); redirect('/document?id=' . $doc['id']); }
    idems_log('report_doc', $doc['id'], 'DOCX', ['irn'=>$doc['irn']]);
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/','_',$doc['irn']) . '.docx"');
    echo $bin; return true;
}

// ===========================================================================
//  Prefill: everything the system already knows about a call / job flows into
//  the report — the header AND the designed form fields — so the inspector
//  only types what is genuinely new.
// ===========================================================================
// Gather the known context for a call (and its job, if one is given).
function idems_context_for($callId, $jobId = 0) {
    $ctx = [];
    $call = $callId ? ops_one("SELECT c.*, bp.legal_name client_name, bp.display_name client_disp, bp.code client_code,
            v.legal_name vendor_name, v.display_name vendor_disp
        FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id LEFT JOIN business_partners v ON v.id=c.vendor_id
        WHERE c.id=?", [(int)$callId]) : null;
    $job = $jobId ? ops_one("SELECT j.*, i.name inspector_name FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id WHERE j.id=?", [(int)$jobId]) : null;
    if (!$call && $job && $job['call_id']) return idems_context_for($job['call_id'], $jobId);
    if (!$call && !$job) return $ctx;
    if ($call) {
        $ctx['call_id']      = (int)$call['id'];
        $ctx['call_code']    = $call['call_code'];
        $ctx['client_id']    = $call['client_id'];
        $ctx['client_name']  = $call['client_disp'] ?: $call['client_name'];
        $ctx['client_code']  = $call['client_code'] ?? '';
        $ctx['vendor_id']    = $call['vendor_id'];
        $ctx['vendor_name']  = $call['vendor_disp'] ?: $call['vendor_name'];
        $ctx['sbu']          = $call['sbu'];
        $ctx['office_id']    = $call['executing_office_id'] ?: $call['ibo_office_id'];
        // The product list is registered as 'product'. Asking for
        // 'product_category' always missed, so the report showed the raw code
        // (or nothing) instead of the same label the call shows.
        $prodMap = lk_options_or('product', PRODUCT_CATS);
        $ctx['product_category'] = $prodMap[$call['product_category']] ?? ($call['product_other'] ?: $call['product_category']);
        $ctx['project_name'] = trim((string)($call['project_name'] ?? '')) ?: trim((string)($call['notes'] ?? ''));
        $ctx['reporting_frequency'] = $call['reporting_frequency'] ?? '';
        $ctx['report_custom_days']  = $call['report_custom_days'] ?? null;
        $ctx['inspection_type']  = INSPECTION_TYPES[$call['inspection_type']] ?? ($call['inspection_type_other'] ?: '');
        $ctx['required_date']= $call['inspection_required_date'];
        $ctx['notes']        = $call['notes'];
        // purchase order + line item behind the call
        if (!empty($call['po_id'])) {
            $po = ops_one("SELECT po_number, title FROM partner_purchase_orders WHERE id=?", [$call['po_id']]);
            if ($po) { $ctx['po_ref'] = $po['po_number']; $ctx['po_title'] = $po['title']; }
        }
        if (!empty($call['po_line_item_id'])) {
            $li = ops_one("SELECT description, quantity, site FROM po_line_items WHERE id=?", [$call['po_line_item_id']]);
            if ($li) { $ctx['item_desc'] = $li['description']; $ctx['quantity'] = $li['quantity']; if (!empty($li['site'])) $ctx['location'] = $li['site']; }
        }
        // site address behind the call
        if (!empty($call['site_address_id'])) {
            $ad = ops_one("SELECT line1, line2, city, state, town_village, district FROM partner_addresses WHERE id=?", [$call['site_address_id']]);
            if ($ad) $ctx['location'] = trim(implode(', ', array_filter([$ad['line1'], $ad['line2'], $ad['town_village'], $ad['city'], $ad['state']])));
        }
        if (empty($ctx['location'])) {
            $ad = $call['vendor_id'] ? ops_one("SELECT line1, city, state FROM partner_addresses WHERE partner_id=? ORDER BY is_primary DESC, id LIMIT 1", [$call['vendor_id']]) : null;
            if ($ad) $ctx['location'] = trim(implode(', ', array_filter([$ad['line1'], $ad['city'], $ad['state']])));
        }
    }
    if ($job) {
        $ctx['job_id']         = (int)$job['id'];
        $ctx['job_code']       = $job['job_code'];
        $ctx['inspector_id']   = $job['inspector_id'];
        $ctx['inspector_name'] = $job['inspector_name'] ?? '';
        $ctx['inspection_date']= $job['inspection_start_date'] ?: ($job['scheduled_date'] ?: '');
        $ctx['inspection_end'] = $job['inspection_end_date'];
        if (!empty($job['sbu'])) $ctx['sbu'] = $job['sbu'];
        if (!empty($job['executing_office_id'])) $ctx['office_id'] = $job['executing_office_id'];
        if (!empty($job['boss_id'])) { $b = ops_one("SELECT boss_number FROM boss_numbers WHERE id=?", [$job['boss_id']]); if ($b) $ctx['boss_number'] = $b['boss_number']; }
        if (!empty($job['quotation_id'])) { $q = ops_one("SELECT quote_no, contract_number FROM quotations WHERE id=?", [$job['quotation_id']]); if ($q) { $ctx['quote_no'] = $q['quote_no']; $ctx['contract_number'] = $q['contract_number']; } }
        // The deliverables chosen at allocation ARE the reports this inspection
        // owes. Their codes are the report-type codes, so the type dropdown can
        // be narrowed to them instead of offering the whole catalogue.
        $ctx['deliverables'] = array_values(array_filter(array_map('trim', explode(',', (string)($job['deliverables'] ?? '')))));
        if (!empty($job['reporting_frequency'])) $ctx['reporting_frequency'] = $job['reporting_frequency'];
        if (($job['report_custom_days'] ?? null) !== null) $ctx['report_custom_days'] = $job['report_custom_days'];
    }
    return array_filter($ctx, fn($v) => $v !== null && $v !== '');
}
// Aliases: which field keys in a designed form mean which piece of known data.
// Lets a client's own naming ("dwg_no", "supplier", "date_of_inspection") still
// line up with the system's data automatically.
function idems_field_aliases() {
    return [
        'client_name'   => ['client','client_name','customer','customer_name','purchaser','buyer','client_ref'],
        'vendor_name'   => ['vendor','vendor_name','manufacturer','supplier','fabricator','maker','mfr','works'],
        'po_ref'        => ['po','po_no','po_number','po_ref','purchase_order','purchase_order_no','order_no','po_num'],
        'drawing_no'    => ['drawing','drawing_no','drg_no','dwg_no','drawing_number','dwg'],
        'drawing_rev'   => ['drawing_rev','drg_rev','dwg_rev','drawing_revision','rev','revision'],
        'qap_rev'       => ['qap','qap_rev','qap_no','qap_revision','itp','itp_no','itp_rev'],
        'standards'     => ['standard','standards','code','codes','specification','spec','applicable_standard','applicable_code'],
        'location'      => ['location','site','place','venue','works_location','site_address','address','inspection_location'],
        'material_grade'=> ['material','material_grade','grade','material_spec'],
        'product_category'=>['product','product_category','item','item_category','equipment','component'],
        'item_desc'     => ['item_desc','item_description','description_of_item','material_description','equipment_description'],
        'quantity'      => ['quantity','qty','quantity_offered','qty_offered','offered_qty','quantity_inspected'],
        'inspection_date'=>['inspection_date','date_of_inspection','insp_date','date','visit_date','inspected_on'],
        'inspector_name'=> ['inspector','inspector_name','inspected_by','surveyor','engineer'],
        'call_code'     => ['call_no','call_number','inspection_call','call_ref','call_code','ic_no'],
        'job_code'      => ['job_no','job_code','job_number','work_order'],
        'project_name'  => ['project','project_name','project_title'],
        'project_code'  => ['project_code','project_no','project_number'],
        'sbu'           => ['sbu','division','business_unit'],
        'boss_number'   => ['boss','boss_no','boss_number','contract_ref'],
        'contract_number'=>['contract','contract_no','contract_number'],
        'quote_no'      => ['quote','quote_no','quotation','quotation_no'],
        'inspection_type'=>['inspection_type','type_of_inspection','stage','inspection_stage'],
        'irn'           => ['irn','report_no','report_number','ref_no','reference_no','certificate_no'],
    ];
}
// Values available to prefill a form, drawn from the report header + its call/job.
function idems_prefill_values($doc) {
    $ctx = idems_context_for($doc['call_id'] ?? 0, $doc['job_id'] ?? 0);
    $sbuMap = lk_options_or('sbu', OPS_SBUS);
    // the report's own header always wins over the call/job
    $vals = array_merge($ctx, array_filter([
        'irn'             => $doc['irn'] ?? '',
        'client_name'     => $doc['client_disp'] ?? ($doc['client_name'] ?? ''),
        'vendor_name'     => $doc['vendor_disp'] ?? ($doc['vendor_name'] ?? ''),
        'po_ref'          => $doc['po_ref'] ?? '',
        'drawing_no'      => $doc['drawing_no'] ?? '',
        'drawing_rev'     => $doc['drawing_rev'] ?? '',
        'qap_rev'         => $doc['qap_rev'] ?? '',
        'standards'       => $doc['standards'] ?? '',
        'location'        => $doc['location'] ?? '',
        'material_grade'  => $doc['material_grade'] ?? '',
        'product_category'=> $doc['product_category'] ?? '',
        'project_name'    => $doc['project_name'] ?? '',
        'project_code'    => $doc['project_code'] ?? '',
        'inspection_date' => $doc['inspection_date'] ?? '',
        'inspector_name'  => $doc['inspector_name'] ?? '',
    ], fn($v) => $v !== null && $v !== ''));
    if (!empty($vals['sbu']) && isset($sbuMap[$vals['sbu']])) $vals['sbu'] = $sbuMap[$vals['sbu']];
    return $vals;
}
// For each designed field that is still empty, supply the known value (if any).
// Returns [fkey => value] — only for fields the inspector has not filled in.
function idems_autofill($doc, $fields, $data) {
    $vals = idems_prefill_values($doc);
    $alias = idems_field_aliases();
    $out = [];
    foreach ($fields as $f) {
        if (!in_array($f['ftype'], ['text','textarea','date','number','select'], true)) continue;
        $k = strtolower($f['fkey']);
        $cur = $data[$f['fkey']] ?? '';
        if (is_array($cur) || trim((string)$cur) !== '') continue;      // never overwrite the inspector
        foreach ($alias as $src => $keys) {
            if (!in_array($k, $keys, true)) continue;
            if (!empty($vals[$src])) { $out[$f['fkey']] = (string)$vals[$src]; }
            break;
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  Build the report FORM from an uploaded client format.
//  Reads the {{tokens}} the admin placed in the .docx, works out a sensible
//  label and field type for each, and creates the report type's fields — so the
//  form the inspector fills matches the client's format exactly.
// ---------------------------------------------------------------------------
// Pull the readable text of a .docx (paragraph breaks preserved) for label hints.
function report_docx_plain($binary) {
    if (!class_exists('ZipArchive')) return '';
    $tmp = tempnam(sys_get_temp_dir(), 'tp');
    if ($tmp === false || file_put_contents($tmp, $binary) === false) return '';
    $z = new ZipArchive(); $out = '';
    if ($z->open($tmp) === true) {
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = $z->getNameIndex($i);
            if (!preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $n)) continue;
            $xml = docx_repair_tokens($z->getFromName($n));
            $xml = preg_replace('/<\/w:(p|tr)>/', "\n", $xml);
            $xml = preg_replace('/<\/w:tc>/', "\t", $xml);
            $out .= html_entity_decode(strip_tags($xml), ENT_QUOTES, 'UTF-8') . "\n";
        }
        $z->close();
    }
    @unlink($tmp);
    return $out;
}
// Turn a token key into a readable label ("material_grade" → "Material grade").
function idems_label_from_key($k) { return ucfirst(trim(str_replace('_', ' ', (string)$k))); }
// Guess a field type from the token name and the label around it.
function idems_guess_type($key, $label) {
    $s = strtolower($key . ' ' . $label);
    // reference numbers stay plain text even though they contain "no." / "date of"
    if (preg_match('/\b(no|number|nos)\b\.?\s*$/', trim($label)) || preg_match('/_(no|number)$/', $key)) return 'text';
    if (preg_match('/\b(date|dated)\b/', $s)) return 'date';
    if (preg_match('/\b(time)\b/', $s)) return 'time';
    if (preg_match('/\b(qty|quantity|count|thickness|dia|diameter|length|width|height|pressure|temp|temperature|hardness|dft|reading|hours)\b/', $s)) return 'number';
    if (preg_match('/\b(remark|remarks|observation|observations|comment|comments|note|notes|description|scope|summary|conclusion|finding|findings|action|cause|reason|justification|detail|details)\b/', $s)) return 'textarea';
    if (preg_match('/\b(result|status|acceptable|acceptance|decision|disposition|compliance)\b/', $s)) return 'select';
    return 'text';
}
// Scan a template and produce a plan of what the form needs.
function idems_template_plan($tplBinary, $typeId) {
    $text = report_docx_plain($tplBinary);
    // every token in document order
    preg_match_all('/\{\{([a-zA-Z0-9_.]+)\}\}/', $text, $m, PREG_OFFSET_CAPTURE);
    $standard = array_flip(array_keys(idems_standard_tokens([])));
    foreach (['doc_number','format_number','doc_revision','format_issue_date'] as $s) $standard[$s] = 1;
    $existing = [];
    foreach (idems_fields($typeId) as $f) $existing[$f['fkey']] = $f;
    $plan = []; $tables = [];
    foreach ($m[1] as $i => $hit) {
        $tok = $hit[0]; $pos = $m[0][$i][1];
        if (strpos($tok, '.') !== false) {                       // table column token
            [$tk, $col] = explode('.', $tok, 2);
            $tables[$tk][$col] = idems_label_from_key($col);
            continue;
        }
        if (isset($standard[$tok]) || isset($plan[$tok])) continue;
        // label hint = the text immediately before the token on the same line/cell
        $before = substr($text, max(0, $pos - 90), min(90, $pos));
        $before = preg_replace('/\{\{[^}]*\}\}/', '', $before);
        $lineBits = preg_split('/[\n\t]/', $before);
        $hint = trim((string)end($lineBits));
        $hint = trim(preg_replace('/[:\-–>\s]+$/u', '', $hint));
        if (mb_strlen($hint) > 48 || mb_strlen($hint) < 2) $hint = '';
        $label = $hint !== '' ? $hint : idems_label_from_key($tok);
        $plan[$tok] = [
            'key'    => $tok,
            'label'  => $label,
            'type'   => idems_guess_type($tok, $label),
            'exists' => isset($existing[$tok]),
            'kind'   => 'field',
        ];
    }
    foreach ($tables as $tk => $cols) {
        if (isset($standard[$tk])) continue;
        $plan[$tk] = [
            'key'    => $tk,
            'label'  => idems_label_from_key($tk),
            'type'   => 'table',
            'cols'   => $cols,
            'exists' => isset($existing[$tk]),
            'kind'   => 'table',
        ];
    }
    return array_values($plan);
}
// ---- Handler: preview + create the form from a template ----
function ops_idems_form_from_template($method) {
    ops_require(is_master() || can('idems.type.manage'), 'You cannot design report forms.');
    $tpl = ops_one("SELECT * FROM report_templates WHERE id=?", [(int)($_GET['id'] ?? $_POST['tpl_id'] ?? 0)]);
    if (!$tpl) { flash('Template not found.', 'error'); redirect('/report-templates'); }
    $typeId = (int)($_POST['report_type_id'] ?? $tpl['report_type_id'] ?? 0);
    if (!$typeId) { flash('This template is not tied to a report type. Edit it and choose one first — the form is built for that type.', 'error'); redirect('/report-template-edit?id=' . $tpl['id']); }
    $type = ops_one("SELECT * FROM report_types WHERE id=?", [$typeId]);
    if (!$type) { flash('Report type not found.', 'error'); redirect('/report-templates'); }
    if (!$tpl['file_data']) { flash('Upload the Word format on this template first.', 'error'); redirect('/report-template-edit?id=' . $tpl['id']); }
    $binary = base64_decode($tpl['file_data']);
    if ($method === 'POST' && ($_POST['_do'] ?? '') === 'create') {
        $pdo = db();
        // one section named after the template, so generated fields stay together
        $secTitle = trim($_POST['section_title'] ?? '') ?: ('Report body — ' . ($tpl['name'] ?: $type['name']));
        $sectionId = ($_POST['section_id'] ?? '') !== '' ? (int)$_POST['section_id'] : 0;
        if (!$sectionId) {
            $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, $secTitle, 'Generated from the uploaded client format.', (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            $sectionId = (int)$pdo->lastInsertId();
        }
        $made = 0;
        foreach ((array)($_POST['use'] ?? []) as $key => $on) {
            $key = idems_clean_key($key);
            if ($key === '' || ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=? AND fkey=?", [$typeId, $key])) continue;
            $ftype = isset(IDEMS_FIELD_TYPES[$_POST['ftype'][$key] ?? '']) ? $_POST['ftype'][$key] : 'text';
            $label = trim($_POST['label'][$key] ?? '') ?: idems_label_from_key($key);
            $cols  = trim($_POST['cols'][$key] ?? '');
            $pdo->prepare("INSERT INTO report_fields (report_type_id,section_id,fkey,label,ftype,table_cols,col_span,sort_order)
                VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$typeId, $sectionId, $key, $label, $ftype, $cols,
                    in_array($ftype, ['textarea','table'], true) ? 2 : 1,
                    (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_fields WHERE report_type_id=?", [$typeId])]);
            $made++;
        }
        idems_log('report_type', $typeId, 'FORM_FROM_TEMPLATE', ['field'=>$type['code'], 'new'=>$made . ' field(s)']);
        flash($made . ' field(s) created. Your form is ready — press “Create a report with this form” to use it, or fine-tune the fields first.');
        redirect('/report-builder?type=' . $typeId);
    }
    $plan = idems_template_plan($binary, $typeId);
    view('ops/idems/form_from_template', ['tpl'=>$tpl, 'type'=>$type, 'plan'=>$plan,
        'fieldTypes'=>IDEMS_FIELD_TYPES, 'sections'=>idems_sections($typeId),
        'auto'=>!empty($_GET['auto']),
        'zipOk'=>class_exists('ZipArchive')]);
    return true;
}

// ---- Handler: auto-build a form from a PLAIN Word file (no {{tokens}}) -------
// Upload an ordinary report format; the app detects the fields, writes a
// tokenised copy as this type's client format, and hands off to the existing
// review-and-create screen. The person never inserts a placeholder.
function ops_idems_autoform($method) {
    ops_require(is_master() || can('idems.type.manage'), 'You cannot design report forms.');
    $typeId = (int)($_GET['type'] ?? $_POST['report_type_id'] ?? 0);
    $type = $typeId ? ops_one("SELECT * FROM report_types WHERE id=?", [$typeId]) : null;
    if (!$type) { flash('Choose a report type first, then build its form.', 'error'); redirect('/report-types'); }

    if ($method === 'POST' && !empty($_FILES['tpl']['tmp_name']) && (int)$_FILES['tpl']['error'] === 0) {
        $name = (string)($_FILES['tpl']['name'] ?? 'format.docx');
        if (!preg_match('/\.docx$/i', $name)) { flash('Please upload a Word .docx file. (Old .doc must be saved as .docx first.)', 'error'); redirect('/report-autoform?type=' . $typeId); }
        $bin = (string)file_get_contents($_FILES['tpl']['tmp_name']);
        $res = autoform_analyze_docx($bin);
        if (!empty($res['err'])) { flash($res['err'], 'error'); redirect('/report-autoform?type=' . $typeId); }
        if (empty($res['plan'])) {
            // We couldn't read fields from their file — but don't dead-end. Keep
            // the format as a template so it can still be filled "in your format",
            // and set up a usable starter form so a report can be written now.
            db()->prepare("INSERT INTO report_templates (name, report_type_id, file_name, file_data, active, is_default, status, version, created_by, created_at)
                           VALUES (?,?,?,?,?,?, 'PUBLISHED', 1, ?, ?)")
                ->execute(['Uploaded: ' . substr($name, 0, 55), $typeId, $name, base64_encode($bin), 1, 0, user_name(current_user()), date('c')]);
            $seeded = idems_ensure_default_form($typeId);
            idems_log('report_type', $typeId, 'AUTOFORM_EMPTY', ['file' => $name]);
            flash('We could not auto-detect fields in “' . $name . '”, so it is saved as a format you can fill by hand.'
                . ($seeded ? ' A standard inspection form (activities, observations, photos) has been set up — edit it in the Form builder, or place {{tokens}} in your file for exact placement.' : ''), 'warning');
            redirect('/report-builder?type=' . $typeId);
        }
        // Save the tokenised copy as this type's client format, then reuse the
        // normal review-and-create screen (it re-scans the tokens).
        db()->prepare("INSERT INTO report_templates (name, report_type_id, file_name, file_data, active, is_default, created_by, created_at)
                       VALUES (?,?,?,?,?,?,?,?)")
            ->execute(['Auto: ' . substr($name, 0, 60), $typeId, $name, base64_encode($res['tokenised']), 1, 0, user_name(current_user()), date('c')]);
        $tplId = (int)db()->lastInsertId();
        idems_log('report_type', $typeId, 'AUTOFORM', ['file' => $name, 'detected' => count($res['plan']) . ' field(s)']);
        flash(count($res['plan']) . ' field(s) detected from your file — review them below, then create the form.');
        redirect('/report-form-from-template?id=' . $tplId . '&auto=1');
    }

    view('ops/idems/autoform', ['type' => $type, 'zipOk' => autoform_supported()]);
    return true;
}

// ---- Handler: report template manager (upload / map client formats) ----
function ops_idems_templates($route, $method) {
    ops_require(is_master() || can('idems.type.manage') || can('crm.template.manage'), 'You cannot manage report templates.');
    $pdo = db();
    if ($route === 'report-template-download') {
        $t = ops_one("SELECT * FROM report_templates WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$t || !$t['file_data']) { http_response_code(404); echo 'Not found'; return true; }
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/','_',$t['file_name'] ?: 'template') . '"');
        echo base64_decode($t['file_data']); return true;
    }
    if ($method === 'POST') {
        if (($_POST['_do'] ?? '') === 'del') { $pdo->prepare("DELETE FROM report_templates WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]); flash('Template removed.'); redirect('/report-templates'); }
        // §32 — "New version": clone an existing format into a fresh editable DRAFT
        // with the next version number, so the live one keeps working untouched.
        if (($_POST['_do'] ?? '') === 'newversion') {
            $src = ops_one("SELECT * FROM report_templates WHERE id=?", [(int)($_POST['id'] ?? 0)]);
            if (!$src) { flash('Format not found.', 'error'); redirect('/report-templates'); }
            $ver = (int)($src['version'] ?? 1) + 1;
            $pdo->prepare("INSERT INTO report_templates (name,report_type_id,client_id,office_id,file_name,file_data,document_number,format_number,doc_revision,issue_date,is_default,active,status,version,created_by,created_at)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,0,'DRAFT',?,?,?)")
                ->execute([trim(($src['name'] ?: 'Format').' v'.$ver), $src['report_type_id'], $src['client_id'], $src['office_id'],
                    $src['file_name'], $src['file_data'], $src['document_number'], $src['format_number'], $src['doc_revision'], $src['issue_date'],
                    $src['is_default'], $ver, user_name(current_user()), date('c')]);
            flash('Created version ' . $ver . ' as a draft — edit it, then tick Active to publish (the live version keeps working until you do).');
            redirect('/report-template-edit?id=' . (int)$pdo->lastInsertId());
        }
        // §44 — document-controller review gate ------------------------------
        if (in_array(($_POST['_do'] ?? ''), ['submit_review','approve','reject','withdraw'], true)) {
            $tid = (int)($_POST['id'] ?? 0);
            $t = ops_one("SELECT * FROM report_templates WHERE id=?", [$tid]);
            if (!$t) { flash('Format not found.', 'error'); redirect('/report-templates'); }
            $do = $_POST['_do'];
            if ($do === 'submit_review') {
                $val = idems_template_validate($t);
                if ($val['level'] === 'ERROR') { flash('Cannot submit — fix the errors first: ' . implode(' ', array_map(fn($i)=>$i['msg'], array_filter($val['issues'], fn($i)=>$i['level']==='ERROR'))), 'error'); redirect('/report-template-edit?id=' . $tid); }
                $pdo->prepare("UPDATE report_templates SET status='IN_REVIEW', submitted_at=?, submitted_by=?, review_note='' WHERE id=?")->execute([date('c'), user_name(current_user()), $tid]);
                idems_log('report_template', $tid, 'SUBMIT_REVIEW', ['field'=>$t['name']]);
                flash('Format submitted to the document controller for review.');
                redirect('/report-templates');
            }
            if ($do === 'withdraw') {   // author pulls it back to draft
                $pdo->prepare("UPDATE report_templates SET status='DRAFT' WHERE id=? AND status='IN_REVIEW'")->execute([$tid]);
                flash('Withdrawn back to draft.'); redirect('/report-template-edit?id=' . $tid);
            }
            // approve / reject need the controller permission
            ops_require(idems_can_approve_template(), 'Only a document controller can approve or reject a report format.');
            if ($t['status'] !== 'IN_REVIEW' && !is_master()) { flash('This format is not awaiting review.', 'error'); redirect('/report-templates'); }
            if ($do === 'reject') {
                $note = trim((string)($_POST['review_note'] ?? ''));
                if ($note === '') { flash('Say why it is sent back, so the author can fix it.', 'error'); redirect('/report-templates'); }
                $pdo->prepare("UPDATE report_templates SET status='DRAFT', active=0, reviewed_at=?, reviewed_by=?, review_note=? WHERE id=?")->execute([date('c'), user_name(current_user()), $note, $tid]);
                idems_log('report_template', $tid, 'REVIEW_REJECT', ['field'=>$t['name'], 'reason'=>$note]);
                flash('Sent back to the author as a draft with your comment.');
                redirect('/report-templates');
            }
            // approve → publish
            $val = idems_template_validate($t);
            if ($val['level'] === 'ERROR') { flash('Cannot approve — the format still has errors.', 'error'); redirect('/report-templates'); }
            $pdo->prepare("UPDATE report_templates SET status='PUBLISHED', active=1, reviewed_at=?, reviewed_by=?, published_at=?, published_by=?, effective_date=COALESCE(NULLIF(effective_date,''),?) WHERE id=?")
                ->execute([date('c'), user_name(current_user()), date('c'), user_name(current_user()), date('Y-m-d'), $tid]);
            $n = idems_template_supersede_siblings(ops_one("SELECT * FROM report_templates WHERE id=?", [$tid]));
            idems_log('report_template', $tid, 'REVIEW_APPROVE', ['field'=>$t['name']]);
            flash('Format approved & published' . ($n ? " — $n older format(s) of the same scope superseded." : '') . '.');
            redirect('/report-templates');
        }
        $id = (int)($_POST['id'] ?? 0);
        $vals = [
            'name'=>trim($_POST['name'] ?? ''), 'report_type_id'=>($_POST['report_type_id'] ?? '')!==''?(int)$_POST['report_type_id']:null,
            'client_id'=>($_POST['client_id'] ?? '')!==''?(int)$_POST['client_id']:null, 'office_id'=>($_POST['office_id'] ?? '')!==''?(int)$_POST['office_id']:null,
            'document_number'=>trim($_POST['document_number'] ?? ''), 'format_number'=>trim($_POST['format_number'] ?? ''),
            'doc_revision'=>trim($_POST['doc_revision'] ?? ''), 'issue_date'=>trim($_POST['issue_date'] ?? ''),
            'active'=>!empty($_POST['active'])?1:0, 'is_default'=>!empty($_POST['is_default'])?1:0,
        ];
        // uploaded .docx
        if (!empty($_FILES['tpl']['tmp_name']) && is_uploaded_file($_FILES['tpl']['tmp_name'])) {
            $bytes = file_get_contents($_FILES['tpl']['tmp_name']);
            if ($bytes !== false && strlen($bytes) < 8*1024*1024) { $vals['file_name'] = substr($_FILES['tpl']['name'], 0, 200); $vals['file_data'] = base64_encode($bytes); }
        }
        if ($id) { $set=implode('=?, ',array_keys($vals)).'=?'; $args=array_values($vals); $args[]=$id; $pdo->prepare("UPDATE report_templates SET $set WHERE id=?")->execute($args); }
        else { $vals2=$vals+['created_by'=>user_name(current_user()),'created_at'=>date('c')]; $cols=array_keys($vals2); $ph=implode(',',array_fill(0,count($cols),'?')); $pdo->prepare("INSERT INTO report_templates (".implode(',',$cols).") VALUES ($ph)")->execute(array_values($vals2)); $id=(int)$pdo->lastInsertId(); }
        // §59 + §32 — validate before it goes live, and record the lifecycle status.
        $saved = ops_one("SELECT * FROM report_templates WHERE id=?", [$id]);
        $val = idems_template_validate($saved);
        $wantActive = !empty($_POST['active']);
        $extra = []; $note = 'Report template saved.';
        if ($wantActive && $val['level'] === 'ERROR') {
            // publish blocked — keep it a draft and tell them what to fix
            $extra = ['active'=>0, 'status'=>'DRAFT'];
            $msgs = array_map(fn($i)=>$i['msg'], array_filter($val['issues'], fn($i)=>$i['level']==='ERROR'));
            flash('Saved as a draft — it cannot be published yet: ' . implode(' ', $msgs), 'error');
        } elseif ($wantActive && !idems_can_approve_template()) {
            // Author asked to publish but isn't a document controller — route it
            // into review instead of going live, and keep it inactive until approved.
            $extra = ['status'=>'IN_REVIEW', 'active'=>0, 'submitted_at'=>date('c'), 'submitted_by'=>user_name(current_user())];
            idems_log('report_template', $id, 'SUBMIT_REVIEW', ['field'=>$saved['name']]);
            flash('Saved and submitted to the document controller for review — it goes live once approved.');
        } else {
            $status = $wantActive ? 'PUBLISHED' : 'DRAFT';
            $extra = ['status'=>$status];
            if ($status === 'PUBLISHED') { $extra['published_at']=date('c'); $extra['published_by']=user_name(current_user()); $extra['reviewed_at']=date('c'); $extra['reviewed_by']=user_name(current_user()); if (empty($saved['effective_date'])) $extra['effective_date']=date('Y-m-d'); }
            $warn = array_filter($val['issues'], fn($i)=>$i['level']==='WARNING');
            $note = ($status==='PUBLISHED'?'Format published.':'Saved as a draft.') . ($warn ? ' ' . count($warn) . ' warning(s) — review under Edit.' : '');
            flash($note, $warn ? 'warning' : 'success');
        }
        if ($extra) { $set=implode('=?, ',array_keys($extra)).'=?'; $a=array_values($extra); $a[]=$id; $pdo->prepare("UPDATE report_templates SET $set WHERE id=?")->execute($a); }
        // supersede same-scope siblings once this one is live
        if (($extra['status'] ?? '') === 'PUBLISHED') { $n = idems_template_supersede_siblings(ops_one("SELECT * FROM report_templates WHERE id=?", [$id])); if ($n) flash($n . ' older format(s) of the same scope were superseded.', 'warning'); }
        redirect('/report-templates');
    }
    $edit = ($route === 'report-template-edit') ? ops_one("SELECT * FROM report_templates WHERE id=?", [(int)($_GET['id'] ?? 0)]) : null;
    $tokRefType = (int)($_GET['tokens'] ?? ($edit['report_type_id'] ?? 0));
    view('ops/idems/templates', [
        'rows'=>ops_all("SELECT t.*, rt.name type_name, rt.code type_code, bp.display_name client_name, o.name office_name FROM report_templates t LEFT JOIN report_types rt ON rt.id=t.report_type_id LEFT JOIN business_partners bp ON bp.id=t.client_id LEFT JOIN offices o ON o.id=t.office_id ORDER BY t.id DESC"),
        'edit'=>$edit, 'validation'=>$edit ? idems_template_validate($edit) : null, 'statusMap'=>TEMPLATE_STATUS, 'types'=>idems_types(false),
        'clients'=>ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_client=1 ORDER BY nm"),
        'offices'=>ops_all("SELECT id, name FROM offices ORDER BY name"),
        'tokRefType'=>$tokRefType, 'tokens'=>$tokRefType ? idems_type_tokens($tokRefType) : null,
        'zipOk'=>class_exists('ZipArchive')]);
    return true;
}

// ===========================================================================
//  Phase 6: manufacturer document verification & endorsement
//  The uploaded original is stored and NEVER altered; the endorsement is a
//  separate record + certificate that references it, with a full audit trail.
// ===========================================================================
const ENDORSE_DOC_TYPES = ['MTC'=>'Material Test Certificate (MTC)','NDT'=>'NDT Report','HYDRO'=>'Hydrostatic Test Report','DIM'=>'Dimensional Report','PAINT'=>'Painting Report','PWHT'=>'PWHT Report','HARD'=>'Hardness Report','FAT'=>'FAT Report','CALIB'=>'Calibration Certificate','WELD'=>'Welding Record','HTR'=>'Heat Treatment Report','OTHER'=>'Other quality record'];
const ENDORSE_STATUS = ['UPLOADED'=>'Uploaded','UNDER_REVIEW'=>'Under review','ENDORSED'=>'Endorsed','REJECTED'=>'Rejected','ARCHIVED'=>'Archived'];
const ENDORSE_DECISION = ['ENDORSED'=>'Reviewed &amp; endorsed','ENDORSED_COND'=>'Endorsed with observations','REJECTED'=>'Rejected'];
function endorse_status_pill($s) { return ['UPLOADED'=>'p-mut','UNDER_REVIEW'=>'p-warn','ENDORSED'=>'p-ok','REJECTED'=>'p-bad','ARCHIVED'=>'p-mut'][$s] ?? 'p-mut'; }
function endorse_can_edit($e) { return $e && empty($e['finalized']) && (is_master() || can('mod.idems.edit')); }
function endorsement_files($eid, $kind = null) {
    if ($kind === null) return ops_all("SELECT id, kind, file_name, mime, note, created_by, created_at FROM endorsement_files WHERE endorsement_id=? ORDER BY id", [(int)$eid]);
    return ops_all("SELECT id, kind, file_name, mime, note, created_by, created_at FROM endorsement_files WHERE endorsement_id=? AND kind=? ORDER BY id", [(int)$eid, $kind]);
}
function endorse_can_act($e) {
    if (!$e || $e['status'] !== 'UNDER_REVIEW') return false;
    if (is_master()) return true;
    if (!empty($e['approver_user_id']) && (int)$e['approver_user_id'] === (int)(current_user()['id'] ?? 0)) return true;
    return can('idems.finalize');
}

function ops_idems_endorsements($route, $method) {
    $pdo = db();
    // ---- stream a file (original / supporting) ----
    if ($route === 'endorsement-file') {
        $f = ops_one("SELECT ef.*, e.deleted FROM endorsement_files ef JOIN endorsements e ON e.id=ef.endorsement_id WHERE ef.id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$f || $f['deleted']) { http_response_code(404); echo 'Not found'; return true; }
        ops_require(is_master() || can('mod.idems.view'), 'Access denied.');
        $data = (string)$f['data']; if (strpos($data,'base64,')!==false) $data = base64_decode(substr($data, strpos($data,'base64,')+7));
        send_uploaded_file($data, $f['file_name'] ?: 'file', $f['mime'] ?? '');
        return true;
    }
    // ---- register ----
    if ($route === 'endorsements') {
        $q = trim($_GET['q'] ?? ''); $fs = $_GET['status'] ?? ''; $ft = $_GET['type'] ?? '';
        [$w, $a] = scope_clause('e.office_id', 'e.sbu');
        $where = "e.deleted=0 AND $w"; $args = $a;
        if ($q) { $where .= " AND (e.endorsement_no LIKE ? OR e.title LIKE ? OR e.heat_no LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%"); }
        if ($fs) { $where .= " AND e.status=?"; $args[] = $fs; }
        if ($ft) { $where .= " AND e.doc_type=?"; $args[] = $ft; }
        $rows = ops_all("SELECT e.*, v.display_name vendor_disp, v.legal_name vendor_name, bp.display_name client_disp, i.name inspector_name
            FROM endorsements e LEFT JOIN business_partners v ON v.id=e.vendor_id LEFT JOIN business_partners bp ON bp.id=e.client_id LEFT JOIN inspectors i ON i.id=e.inspector_id
            WHERE $where ORDER BY e.id DESC", $args);
        $counts = ops_one("SELECT COUNT(*) total, SUM(CASE WHEN status IN ('UPLOADED','UNDER_REVIEW') THEN 1 ELSE 0 END) open_n,
            SUM(CASE WHEN status='ENDORSED' THEN 1 ELSE 0 END) endorsed_n, SUM(CASE WHEN status='REJECTED' THEN 1 ELSE 0 END) rejected_n
            FROM endorsements e WHERE e.deleted=0 AND $w", $a) ?: [];
        view('ops/idems/endorse_list', ['rows'=>$rows, 'q'=>$q, 'fs'=>$fs, 'ft'=>$ft, 'counts'=>$counts]);
        return true;
    }
    // ---- create / edit ----
    if ($route === 'endorsement-new' || $route === 'endorsement-edit') {
        $e = null;
        if ($route === 'endorsement-edit') { $e = ops_one("SELECT * FROM endorsements WHERE id=? AND deleted=0", [(int)($_GET['id'] ?? 0)]); if (!$e) { http_response_code(404); view('notfound'); return true; } ops_require(endorse_can_edit($e), 'This endorsement is locked.'); }
        else ops_require(is_master() || can('mod.idems.edit'), 'You cannot create endorsements.');
        if ($method === 'POST') {
            $b = $_POST;
            $clientId = ($b['client_id'] ?? '')!==''?(int)$b['client_id']:null;
            $client = $clientId ? ops_one("SELECT legal_name, display_name, code FROM business_partners WHERE id=?", [$clientId]) : null;
            $officeId = ($b['office_id'] ?? '')!==''?(int)$b['office_id']:(current_user()['home_office_id'] ?? null);
            $inspectorId = ($b['inspector_id'] ?? '')!==''?(int)$b['inspector_id']:null;
            $approver = ($b['approver_user_id'] ?? '')!==''?(int)$b['approver_user_id']:idems_inspector_approver($inspectorId);
            $fields = [
                'doc_type'=>isset(lk_options_or('endorse_doc_type', ENDORSE_DOC_TYPES)[$b['doc_type'] ?? ''])?$b['doc_type']:'MTC', 'title'=>trim($b['title'] ?? ''),
                'vendor_id'=>($b['vendor_id'] ?? '')!==''?(int)$b['vendor_id']:null, 'client_id'=>$clientId, 'report_doc_id'=>($b['report_doc_id'] ?? '')!==''?(int)$b['report_doc_id']:null,
                'project_code'=>trim($b['project_code'] ?? ''), 'project_name'=>trim($b['project_name'] ?? ''), 'po_ref'=>trim($b['po_ref'] ?? ''),
                'drawing_no'=>trim($b['drawing_no'] ?? ''), 'drawing_rev'=>trim($b['drawing_rev'] ?? ''), 'qap_rev'=>trim($b['qap_rev'] ?? ''),
                'heat_no'=>trim($b['heat_no'] ?? ''), 'item_desc'=>trim($b['item_desc'] ?? ''), 'doc_version'=>trim($b['doc_version'] ?? ''),
                'office_id'=>$officeId, 'sbu'=>$b['sbu'] ?? '', 'inspector_id'=>$inspectorId, 'approver_user_id'=>$approver ?: null,
                'client_code'=>$client ? idems_client_code($client) : '', 'review_remarks'=>trim($b['review_remarks'] ?? ''),
            ];
            if ($e) {
                $set = implode('=?, ', array_keys($fields)) . '=?'; $args = array_values($fields); $args[]=date('c'); $args[]=$e['id'];
                $pdo->prepare("UPDATE endorsements SET $set, updated_at=? WHERE id=?")->execute($args);
                idems_endorse_uploads($e['id']);
                idems_log('endorsement', $e['id'], 'EDIT', ['irn'=>$e['endorsement_no']]);
                flash('Endorsement updated.'); redirect('/endorsement?id=' . $e['id']);
            } else {
                // Same document, same vendor, same heat / drawing — endorsing it
                // twice burns a second endorsement number against one physical
                // certificate. Same reasoning as a report: the number is permanent.
                // Narrow in SQL on the things that are reliably typed, then compare
                // the rest in PHP: an "empty" id reaches the database as NULL on one
                // engine and as '' on another, and COALESCE(vendor_id,0)=0 quietly
                // matches neither. A comparison that depends on which engine you are
                // running is not a comparison.
                // Two comparators, deliberately. An id may arrive as NULL on one
                // engine and '' on another, so ids compare as numbers. Text must
                // NOT: (int)'A1' and (int)'B1' are both 0, which would have called
                // three different heat numbers the same document.
                $sameId  = fn($a, $b) => (int)($a ?? 0) === (int)($b ?? 0);
                $sameTxt = fn($a, $b) => trim((string)($a ?? '')) === trim((string)($b ?? ''));
                $twin = null;
                foreach (ops_all("SELECT * FROM endorsements WHERE deleted=0 AND doc_type=? AND title=? AND created_at >= ? ORDER BY id DESC",
                        [$fields['doc_type'], $fields['title'], date('c', time() - 120)]) as $cand) {
                    if (!$sameId($cand['vendor_id'], $fields['vendor_id'])) continue;
                    if (!$sameId($cand['client_id'], $fields['client_id'])) continue;
                    if (!$sameTxt($cand['heat_no'], $fields['heat_no'])) continue;
                    if (!$sameTxt($cand['drawing_no'], $fields['drawing_no'])) continue;
                    if (!$sameTxt($cand['item_desc'], $fields['item_desc'])) continue;
                    $twin = $cand; break;
                }
                if ($twin) {
                    flash($twin['endorsement_no'] . ' was just created for the same document. '
                        . 'Nothing was created twice; open it below to carry on.', 'warning');
                    redirect('/endorsement?id=' . (int)$twin['id']);
                }
                [$no, $serial] = idems_generate_irn($fields + ['type_code'=>'END', 'inspection_date'=>date('Y-m-d')]);
                $tok = idems_tokens_for($fields + ['type_code'=>'END']);
                $cols = array_merge(['endorsement_no','company_code','branch_code','fy_label','serial','status','created_by','created_at','updated_at'], array_keys($fields));
                $vals = array_merge([$no, $tok['{COMPANY}'], $tok['{BRANCH}'], $tok['{FY}'], $serial, 'UPLOADED', user_name(current_user()), date('c'), date('c')], array_values($fields));
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO endorsements (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                $id = (int)$pdo->lastInsertId();
                idems_endorse_uploads($id);
                idems_log('endorsement', $id, 'CREATE', ['irn'=>$no]);
                flash('Endorsement created — ' . $no . '. Upload the manufacturer document and any supporting evidence.');
                redirect('/endorsement?id=' . $id);
            }
        }
        view('ops/idems/endorse_form', ['e'=>$e, 'docTypes'=>ENDORSE_DOC_TYPES,
            'vendors'=>ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_vendor=1 ORDER BY nm"),
            'clients'=>ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_client=1 ORDER BY nm"),
            'inspectors'=>ops_all("SELECT id, name FROM inspectors WHERE status='ACTIVE' ORDER BY name"),
            'approvers'=>ops_all("SELECT id, first_name, last_name, username, role FROM users WHERE is_active=1 ORDER BY first_name"),
            'offices'=>ops_all("SELECT id, name FROM offices ORDER BY is_ahmedabad DESC, name"), 'sbuOpts'=>lk_options_or('sbu', OPS_SBUS),
            'reports'=>ops_all("SELECT id, irn FROM report_docs WHERE deleted=0 ORDER BY id DESC")]);
        return true;
    }
    // ---- detail ----
    if ($route === 'endorsement') {
        $e = ops_one("SELECT e.*, v.display_name vendor_disp, v.legal_name vendor_name, bp.display_name client_disp, bp.legal_name client_name,
                i.name inspector_name, o.name office_name, rd.irn linked_irn
            FROM endorsements e LEFT JOIN business_partners v ON v.id=e.vendor_id LEFT JOIN business_partners bp ON bp.id=e.client_id
            LEFT JOIN inspectors i ON i.id=e.inspector_id LEFT JOIN offices o ON o.id=e.office_id LEFT JOIN report_docs rd ON rd.id=e.report_doc_id
            WHERE e.id=? AND e.deleted=0", [(int)($_GET['id'] ?? 0)]);
        if (!$e) { http_response_code(404); view('notfound'); return true; }
        $approver = $e['approver_user_id'] ? ops_one("SELECT first_name, last_name, username FROM users WHERE id=?", [$e['approver_user_id']]) : null;
        view('ops/idems/endorse_detail', ['e'=>$e, 'approver'=>$approver,
            'orig'=>endorsement_files($e['id'], 'original'), 'support'=>endorsement_files($e['id'], 'support'),
            'canAct'=>endorse_can_act($e), 'audit'=>ops_all("SELECT * FROM idems_audit WHERE entity='endorsement' AND entity_id=? ORDER BY id DESC", [$e['id']])]);
        return true;
    }
    // ---- submit for endorsement ----
    if ($route === 'endorsement-submit' && $method === 'POST') {
        $e = ops_one("SELECT * FROM endorsements WHERE id=? AND deleted=0", [(int)($_POST['id'] ?? 0)]);
        if (!$e) { http_response_code(404); view('notfound'); return true; }
        ops_require(endorse_can_edit($e), 'This endorsement is locked.');
        if (!ops_val("SELECT COUNT(*) FROM endorsement_files WHERE endorsement_id=? AND kind='original'", [$e['id']])) { flash('Upload the manufacturer document (the original) before submitting.', 'error'); redirect('/endorsement?id=' . $e['id']); }
        if (!$e['approver_user_id']) { flash('Assign an approver before submitting (or map one for the inspector).', 'error'); redirect('/endorsement?id=' . $e['id']); }
        $pdo->prepare("UPDATE endorsements SET status='UNDER_REVIEW', submitted_at=?, updated_at=? WHERE id=?")->execute([date('c'), date('c'), $e['id']]);
        idems_log('endorsement', $e['id'], 'SUBMIT', ['irn'=>$e['endorsement_no'], 'new'=>'UNDER_REVIEW']);
        $to = idems_approver_email($e['approver_user_id']);
        if ($to) ops_mail($to, 'Document endorsement required: ' . $e['endorsement_no'], "A manufacturer document awaits your review & endorsement.\n\n" . $e['endorsement_no'] . " — " . (lk_options_or('endorse_doc_type', ENDORSE_DOC_TYPES)[$e['doc_type']] ?? $e['doc_type']) . "\n\nOpen it in the system to review the original and endorse or reject.\n\n" . app_name(), '', 'idems_endorse');
        flash('Submitted to the approver for review & endorsement.');
        redirect('/endorsement?id=' . $e['id']);
    }
    // ---- approve (endorse) / reject ----
    if ($route === 'endorsement-approve' && $method === 'POST') {
        $e = ops_one("SELECT * FROM endorsements WHERE id=? AND deleted=0", [(int)($_POST['id'] ?? 0)]);
        if (!$e) { http_response_code(404); view('notfound'); return true; }
        ops_require(endorse_can_act($e), 'You are not the assigned approver for this document.');
        $decision = $_POST['decision'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        if ($decision === 'reject') {
            if ($remarks === '') { flash('A remark is mandatory to reject.', 'error'); redirect('/endorsement?id=' . $e['id']); }
            $pdo->prepare("UPDATE endorsements SET status='REJECTED', decision='REJECTED', decision_remarks=?, updated_at=? WHERE id=?")->execute([$remarks, date('c'), $e['id']]);
            idems_log('endorsement', $e['id'], 'REJECT', ['irn'=>$e['endorsement_no'], 'reason'=>$remarks]);
            flash('Document rejected and returned.');
        } else {
            $dec = ($decision === 'endorse_cond') ? 'ENDORSED_COND' : 'ENDORSED';
            $pdo->prepare("UPDATE endorsements SET status='ENDORSED', decision=?, decision_remarks=?, endorsed_at=?, endorsed_by=?, finalized=1, updated_at=? WHERE id=?")
                ->execute([$dec, $remarks, date('c'), user_name(current_user()), date('c'), $e['id']]);
            idems_endorse_snapshot_signatures($e);
            idems_log('endorsement', $e['id'], 'ENDORSE', ['irn'=>$e['endorsement_no'], 'new'=>$dec, 'reason'=>$remarks]);
            flash('Document endorsed & locked. The endorsement certificate is ready.');
        }
        redirect('/endorsement?id=' . $e['id']);
    }
    // ---- archive / soft-delete ----
    if ($route === 'endorsement-delete' && $method === 'POST') {
        $e = ops_one("SELECT * FROM endorsements WHERE id=?", [(int)($_POST['id'] ?? 0)]);
        if (!$e) { http_response_code(404); view('notfound'); return true; }
        ops_require(is_master() || can('idems.finalize'), 'You cannot remove endorsements.');
        $pdo->prepare("UPDATE endorsements SET deleted=1, updated_at=? WHERE id=?")->execute([date('c'), $e['id']]);
        idems_log('endorsement', $e['id'], 'DELETE', ['irn'=>$e['endorsement_no'], 'reason'=>trim($_POST['reason'] ?? '')]);
        flash('Endorsement archived (soft-deleted; stays in the audit log).');
        redirect('/endorsements');
    }
    return false;
}
// Handle original + supporting uploads for an endorsement.
function idems_endorse_uploads($eid) {
    $pdo = db();
    $map = ['original'=>'orig', 'support'=>'support'];
    foreach ($map as $kind => $field) {
        if (empty($_FILES[$field]['name'])) continue;
        $names = (array)$_FILES[$field]['name']; $tmp = (array)$_FILES[$field]['tmp_name']; $types = (array)$_FILES[$field]['type']; $errs = (array)$_FILES[$field]['error'];
        for ($i = 0; $i < count($names); $i++) {
            if (($errs[$i] ?? 1) !== 0 || !is_uploaded_file($tmp[$i])) continue;
            $bytes = @file_get_contents($tmp[$i]); if ($bytes === false || strlen($bytes) > 12*1024*1024) continue;
            // an endorsement keeps ONE original; replace it
            if ($kind === 'original') $pdo->prepare("DELETE FROM endorsement_files WHERE endorsement_id=? AND kind='original'")->execute([$eid]);
            $b64 = 'data:' . ($types[$i] ?: 'application/octet-stream') . ';base64,' . base64_encode($bytes);
            $pdo->prepare("INSERT INTO endorsement_files (endorsement_id,kind,file_name,mime,data,created_by,created_at) VALUES (?,?,?,?,?,?,?)")
                ->execute([$eid, $kind, substr($names[$i],0,255), $types[$i] ?: '', $b64, user_name(current_user()), date('c')]);
        }
    }
}
// Freeze inspector + approver signatures onto the endorsement at endorse time.
function idems_endorse_snapshot_signatures($e) {
    $pdo = db();
    $insSig = inspector_signature($e['inspector_id'] ?? 0);
    if ($insSig) { $pdo->prepare("DELETE FROM endorsement_files WHERE endorsement_id=? AND kind='sig_inspector'")->execute([$e['id']]); $pdo->prepare("INSERT INTO endorsement_files (endorsement_id,kind,file_name,mime,data,created_by,created_at) VALUES (?,?,?,?,?,?,?)")->execute([$e['id'],'sig_inspector','inspector_sign.png','image/png',$insSig,user_name(current_user()),date('c')]); }
    $apSig = user_signature($e['approver_user_id'] ?? 0);
    if ($apSig) { $pdo->prepare("DELETE FROM endorsement_files WHERE endorsement_id=? AND kind='sig_approver'")->execute([$e['id']]); $pdo->prepare("INSERT INTO endorsement_files (endorsement_id,kind,file_name,mime,data,created_by,created_at) VALUES (?,?,?,?,?,?,?)")->execute([$e['id'],'sig_approver','approver_sign.png','image/png',$apSig,user_name(current_user()),date('c')]); }
}
// ---- Endorsement certificate PDF (separate document; original untouched) ----
function ops_idems_endorse_cert($method) {
    $e = ops_one("SELECT e.*, v.display_name vendor_disp, v.legal_name vendor_name, bp.display_name client_disp, bp.legal_name client_name, i.name inspector_name, i.designation insp_desig, i.emp_code insp_emp, rd.irn linked_irn
        FROM endorsements e LEFT JOIN business_partners v ON v.id=e.vendor_id LEFT JOIN business_partners bp ON bp.id=e.client_id LEFT JOIN inspectors i ON i.id=e.inspector_id LEFT JOIN report_docs rd ON rd.id=e.report_doc_id
        WHERE e.id=? AND e.deleted=0", [(int)($_GET['id'] ?? 0)]);
    if (!$e) { http_response_code(404); echo 'Not found'; return true; }
    ops_require(is_master() || can('mod.idems.view'), 'Access denied.');
    $lh = function_exists('quote_letterhead') ? quote_letterhead() : ['name'=>app_name()];
    // approver identity
    $ap = $e['approver_user_id'] ? ops_one("SELECT first_name, last_name, position_title, role FROM users WHERE id=?", [$e['approver_user_id']]) : null;
    $snapI = ops_one("SELECT data FROM endorsement_files WHERE endorsement_id=? AND kind='sig_inspector' ORDER BY id DESC LIMIT 1", [$e['id']]);
    $snapA = ops_one("SELECT data FROM endorsement_files WHERE endorsement_id=? AND kind='sig_approver' ORDER BY id DESC LIMIT 1", [$e['id']]);
    $sigs = [
        'inspector'=>['img'=>idems_sig_jpeg($snapI['data'] ?? inspector_signature($e['inspector_id'] ?? 0)), 'name'=>$e['inspector_name'] ?? '', 'desig'=>DESIGNATIONS[$e['insp_desig']] ?? ($e['insp_desig'] ?? ''), 'meta'=>trim(($e['insp_emp'] ?? '').($e['branch_code']?' · '.$e['branch_code']:'')), 'time'=>$e['endorsed_at']?'Reviewed: '.date('d M Y H:i', strtotime($e['endorsed_at'])):''],
        'approver'=>['img'=>idems_sig_jpeg($snapA['data'] ?? user_signature($e['approver_user_id'] ?? 0)), 'name'=>$ap?trim(($ap['first_name']??'').' '.($ap['last_name']??'')):($e['endorsed_by'] ?? ''), 'desig'=>$ap?($ap['position_title'] ?: (ORG_ROLES[$ap['role']] ?? '')):'', 'meta'=>'Endorsing authority', 'time'=>$e['endorsed_at']?'Endorsed: '.date('d M Y H:i', strtotime($e['endorsed_at'])):''],
    ];
    $pdf = endorsement_pdf_build($e, $lh, $sigs);
    idems_log('endorsement', $e['id'], 'CERT_PDF', ['irn'=>$e['endorsement_no']]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/','_',$e['endorsement_no']) . '_endorsement.pdf"');
    echo $pdf; return true;
}
function endorsement_pdf_build($e, $lh, $sigs) {
    $p = new SimplePDF(); $ml = $p->ml; $right = $p->right(); $band = [21, 128, 61];   // green endorsement band
    $p->runHead = ['name'=>(($lh['name'] ?? '') ?: app_name()), 'sub'=>'Endorsement '.($e['endorsement_no'] ?? ''), 'band'=>$band, 'skipFirst'=>true];
    $p->runFoot = ['left'=>(($lh['name'] ?? '') ?: app_name()).($e['endorsement_no'] ? '  ·  '.$e['endorsement_no'] : ''), 'pageNumbers'=>true, 'band'=>$band];
    $p->rectFill(0, 0, $p->pageW(), 6, $band); $p->y = $p->mt; $top = $p->y; $nameX = $ml;
    if (!empty($lh['logo'])) { $ln=$p->addJpeg($lh['logo']); if($ln){ [$iw,$ih]=$p->imgDim($ln); $lw=80; $lhh=$ih>0?min(44,$lw*$ih/max(1,$iw)):36; $p->drawImage($ln,$ml,$top,$lw,$lhh); $nameX=$ml+$lw+12; } }
    $p->text($nameX, ($lh['name'] ?? '') ?: app_name(), 15, true, $band);
    $p->y=$top; $p->text($ml, 'Endorsement No: ' . $e['endorsement_no'], 9, true, [60,60,60], $right, 'R');
    $p->y = $top + 34; $p->hr($band); $p->gap(6);
    $p->line('DOCUMENT REVIEW & ENDORSEMENT CERTIFICATE', 14, true, 18, $band);
    $dec = $e['decision']==='REJECTED' ? 'REJECTED' : (lk_options_or('endorse_decision', ENDORSE_DECISION)[$e['decision']] ?? 'Reviewed & Endorsed');
    $p->line('Decision: ' . strip_tags($dec), 11, true, 15, $e['decision']==='REJECTED'?[190,50,50]:[21,128,61]);
    $p->gap(4);
    $kv = ['Document type'=>lk_options_or('endorse_doc_type', ENDORSE_DOC_TYPES)[$e['doc_type']] ?? $e['doc_type'], 'Title / description'=>$e['title'] ?: $e['item_desc'],
        'Manufacturer / vendor'=>$e['vendor_disp'] ?: ($e['vendor_name'] ?? ''), 'Client'=>$e['client_disp'] ?: ($e['client_name'] ?? ''),
        'Project'=>trim(($e['project_code'] ?? '').' '.($e['project_name'] ?? '')), 'PO'=>$e['po_ref'] ?? '',
        'Drawing'=>trim(($e['drawing_no'] ?? '').' '.($e['drawing_rev']?'Rev '.$e['drawing_rev']:'')), 'QAP rev'=>$e['qap_rev'] ?? '',
        'Heat / lot no.'=>$e['heat_no'] ?? '', 'Document version'=>$e['doc_version'] ?? '',
        'Linked inspection report'=>$e['linked_irn'] ?? '', 'Endorsed on'=>$e['endorsed_at']?date('d M Y H:i', strtotime($e['endorsed_at'])):''];
    $colW = $p->contentW()/2;
    foreach (array_chunk(array_filter($kv, fn($v)=>trim((string)$v)!==''), 2, true) as $pair) {
        $p->needSpace(14); $yrow=$p->y; $i=0; foreach ($pair as $k=>$v){ $x=$ml+$i*$colW; $p->y=$yrow; $p->text($x,$k.':',8.5,true,[90,90,90]); $p->text($x+92,(string)$v,9); $i++; } $p->y=$yrow+13;
    }
    if (!empty($e['decision_remarks'])) { $p->gap(4); $p->needSpace(16); $p->line('Endorsement remarks', 10, true, 13, $band); foreach ($p->wrap($e['decision_remarks'],9,$p->contentW()) as $ln2){ $p->needSpace(11); $p->line($ln2,9,false,11); } }
    if (!empty($e['review_remarks'])) { $p->gap(2); $p->needSpace(16); $p->line('Reviewer notes', 10, true, 13, [90,90,90]); foreach ($p->wrap($e['review_remarks'],9,$p->contentW()) as $ln2){ $p->needSpace(11); $p->line($ln2,9,false,11); } }
    $p->gap(6); $p->needSpace(20); $p->line('This certificate endorses the manufacturer document identified above. The original record is retained unaltered as supporting evidence.', 8, false, 11, [110,110,110]);
    // signature block
    $p->gap(12); $p->needSpace(90); $p->hr($band); $p->gap(8); $sy=$p->y; $colW2=$p->contentW()/2;
    $drawSig = function($x,$y0,$title,$s) use ($p) {
        $p->y=$y0; $p->text($x,$title,8.5,true,[90,90,90]); $imgY=$y0+14;
        if(!empty($s['img'])){ $nm=$p->addJpeg($s['img']); if($nm) $p->drawImage($nm,$x,$imgY,120,40); }
        $lineY=$imgY+42; $p->lineAt($x,$lineY,$x+150,$lineY,[120,120,120]); $ty=$lineY+3;
        foreach(array_filter([$s['name']??'',$s['desig']??'',$s['meta']??'',$s['time']??'']) as $t){ $p->y=$ty; $p->text($x,$t,8,false,[70,70,70]); $ty+=10; }
        return $ty;
    };
    $y1=$drawSig($ml,$sy,'Reviewed by (inspector)',$sigs['inspector'] ?? []);
    $y2=$drawSig($ml+$colW2,$sy,'Endorsed by (approver)',$sigs['approver'] ?? []);
    $p->y=max($y1,$y2)+6;
    if (!empty($lh['footer'])) { $p->needSpace(12); $p->hr([220,220,220]); $p->gap(3); $p->line($lh['footer'],7.5,false,10,[130,130,130]); }
    $p->needSpace(12); $p->line('System-generated by ' . app_name() . ' · ' . $e['endorsement_no'] . ' · ' . date('d M Y H:i'), 7, false, 9, [150,150,150]);
    return $p->output();
}

// ===========================================================================
//  Phase 7: Technical Writing Assistant (works with NO AI)
//  Turns an inspector's shorthand ("dimension ok", "minor dent") into correct,
//  factual engineering language using a configurable phrase library + rules.
// ===========================================================================
const PHRASE_CATEGORIES = [
    'OBSERVATION'   => 'Observation',
    'ACCEPTANCE'    => 'Acceptance statement',
    'REJECTION'     => 'Rejection statement',
    'CONCLUSION'    => 'Conclusion',
    'RECOMMENDATION'=> 'Recommendation',
    'HOLD'          => 'Hold point',
    'WITNESS'       => 'Witness point',
    'DEVIATION'     => 'Deviation / NCR',
];
// Seeded standard phrases: [category, shorthand, phrase]. Shorthand drives the
// plain-language → engineering-language expansion. Admin may edit/add unlimited.
const TECH_PHRASE_SEED = [
    // --- observations ---
    ['OBSERVATION','dimension ok','The dimensions were verified against the approved drawing and found to be within the specified tolerances.'],
    ['OBSERVATION','dimension not ok','The dimensions were verified against the approved drawing and found to be outside the specified tolerances.'],
    ['OBSERVATION','surface clean','The inspected surface was found to be free from visible contamination, scale and foreign matter.'],
    ['OBSERVATION','minor dent','A minor dent was observed at the location indicated; the same has been recorded for the manufacturer’s attention.'],
    ['OBSERVATION','visual ok','Visual examination was carried out and no unacceptable surface defects were observed.'],
    ['OBSERVATION','witnessed hydro test','The hydrostatic pressure test was witnessed in accordance with the approved QAP and no leakage or pressure drop was observed.'],
    ['OBSERVATION','witnessed ndt','The non-destructive examination was witnessed and the results were found acceptable to the applicable acceptance criteria.'],
    ['OBSERVATION','material verified','The material identification and marking were verified against the material test certificate and found to be in order.'],
    ['OBSERVATION','mtc reviewed','The material test certificates were reviewed against the applicable specification and found acceptable.'],
    ['OBSERVATION','welding ok','The welding was carried out in accordance with the approved welding procedure specification by qualified welders.'],
    ['OBSERVATION','painting ok','The surface preparation and coating application were verified and found to comply with the approved painting specification.'],
    ['OBSERVATION','dft ok','The dry film thickness was measured at random locations and found to be within the specified range.'],
    ['OBSERVATION','calibration valid','The measuring and test equipment used was verified to be within its valid calibration period.'],
    ['OBSERVATION','calibration expired','The calibration certificate of the measuring equipment was found to have expired; the equipment was not accepted for use.'],
    ['OBSERVATION','marking ok','Identification marking and traceability were verified and found to be in accordance with the approved procedure.'],
    ['OBSERVATION','documents reviewed','The submitted quality records were reviewed and found to be complete and in line with the approved QAP.'],
    ['OBSERVATION','documents incomplete','The submitted quality records were found to be incomplete; the pending documents have been advised to the manufacturer.'],
    ['OBSERVATION','housekeeping poor','Housekeeping at the inspection location was found to be inadequate and was brought to the manufacturer’s attention.'],
    ['OBSERVATION','work in progress','The activity was found to be in progress at the time of the visit; inspection will continue in the subsequent stage.'],
    ['OBSERVATION','no activity','No inspection activity was in progress at the time of the visit.'],
    // --- acceptance / rejection ---
    ['ACCEPTANCE','accepted','The inspected item is found acceptable and is hereby cleared for despatch, subject to the approved documentation.'],
    ['ACCEPTANCE','accepted with conditions','The inspected item is accepted subject to satisfactory closure of the observations recorded in this report.'],
    ['ACCEPTANCE','stage cleared','The inspection stage is cleared; the manufacturer may proceed to the next stage of manufacture.'],
    ['REJECTION','rejected','The inspected item is not acceptable in its present condition and is rejected pending corrective action.'],
    ['REJECTION','rework required','The item requires rework; re-inspection shall be carried out after satisfactory completion of the corrective action.'],
    ['REJECTION','hold applied','A hold is applied on further processing until the recorded non-conformity is resolved to the satisfaction of the inspection agency.'],
    // --- conclusions ---
    ['CONCLUSION','satisfactory','Based on the inspection carried out and the documents reviewed, the workmanship and quality were found to be satisfactory.'],
    ['CONCLUSION','not satisfactory','Based on the inspection carried out, the workmanship and quality were not found to be satisfactory; refer to the observations recorded above.'],
    ['CONCLUSION','partial','The inspection was completed in part; the balance activities shall be covered during the subsequent visit.'],
    // --- recommendations ---
    ['RECOMMENDATION','close observations','It is recommended that the observations recorded in this report be closed prior to despatch.'],
    ['RECOMMENDATION','submit documents','It is recommended that the manufacturer submit the pending quality records for review and endorsement.'],
    ['RECOMMENDATION','re-inspection','It is recommended that a re-inspection be arranged after completion of the corrective action.'],
    // --- hold / witness / deviation ---
    ['HOLD','hold point','This activity is identified as a hold point; work shall not proceed beyond this stage without clearance from the inspection agency.'],
    ['WITNESS','witness point','This activity is identified as a witness point and was attended by the inspection agency.'],
    ['DEVIATION','deviation noted','A deviation from the approved documents was noted as recorded above; the same is raised for the manufacturer’s disposition.'],
];
function idems_seed_phrases() {
    $pdo = db();
    if ((int)$pdo->query("SELECT COUNT(*) FROM tech_phrases")->fetchColumn() > 0) return;
    $ins = $pdo->prepare("INSERT INTO tech_phrases (category,shorthand,phrase,active,is_system,sort_order,created_at) VALUES (?,?,?,1,1,?,?)");
    $i = 0;
    foreach (TECH_PHRASE_SEED as $r) { $i += 10; $ins->execute([$r[0], $r[1], $r[2], $i, date('c')]); }
}
function tech_phrases($category = null, $activeOnly = true) {
    $w = $activeOnly ? "active=1" : "1=1"; $args = [];
    if ($category) { $w .= " AND category=?"; $args[] = $category; }
    return ops_all("SELECT * FROM tech_phrases WHERE $w ORDER BY category, sort_order, id", $args);
}
// Common inspection abbreviations expanded on first use in a sentence.
function tech_abbrev_map() {
    return ['ok'=>'acceptable','n/a'=>'not applicable','nil'=>'nil','qap'=>'QAP','ndt'=>'NDT','mtc'=>'MTC','dft'=>'DFT',
        'wps'=>'WPS','pqr'=>'PQR','hydro'=>'hydrostatic test','ut'=>'UT','rt'=>'RT','mpt'=>'MPT','dpt'=>'DPT','pmi'=>'PMI',
        'poh'=>'PO','po'=>'PO','ncr'=>'NCR','irn'=>'IRN','fat'=>'FAT','pwht'=>'PWHT','tpi'=>'TPI','qc'=>'QC','qa'=>'QA'];
}
// Frequent inspector typos → correct spelling (a light, domain-specific check).
function tech_spell_map() {
    return ['dimention'=>'dimension','dimentions'=>'dimensions','tolerence'=>'tolerance','tolerences'=>'tolerances',
        'thicknes'=>'thickness','recieved'=>'received','maintainance'=>'maintenance','calibaration'=>'calibration',
        'certifcate'=>'certificate','certifiate'=>'certificate','inspetion'=>'inspection','inpsection'=>'inspection',
        'weldment'=>'weldment','satisfactry'=>'satisfactory','accepatble'=>'acceptable','acceptible'=>'acceptable',
        'surafce'=>'surface','presure'=>'pressure','matrial'=>'material','materal'=>'material','drawin'=>'drawing',
        'specifcation'=>'specification','speciication'=>'specification','witnesed'=>'witnessed','proceedure'=>'procedure'];
}
// Best-matching library phrase for a line of shorthand (exact, then contains, then word overlap).
function tech_match_phrase($line, $phrases = null) {
    $norm = strtolower(trim(preg_replace('/[^a-z0-9\s\/]+/i', ' ', $line)));
    $norm = trim(preg_replace('/\s+/', ' ', $norm));
    if ($norm === '') return null;
    $phrases = $phrases ?: tech_phrases();
    $best = null; $bestScore = 0;
    foreach ($phrases as $p) {
        $sh = strtolower(trim($p['shorthand']));
        if ($sh === '') continue;
        if ($norm === $sh) return $p;                                   // exact
        $score = 0;
        if (strpos($norm, $sh) !== false) $score = 80 + strlen($sh);    // shorthand appears in the line
        else {
            $a = array_filter(explode(' ', $sh)); $b = array_filter(explode(' ', $norm));
            $common = array_intersect($a, $b);
            if ($a && count($common) === count($a)) $score = 60 + count($a) * 2;      // all shorthand words present
            elseif ($a && count($common) >= 2) $score = 30 + count($common) * 2;      // partial overlap
        }
        if ($score > $bestScore) { $bestScore = $score; $best = $p; }
    }
    return $bestScore >= 30 ? $best : null;
}
// Tidy a free-text line that has no library match: spelling, abbreviations,
// capitalisation and terminal punctuation — never changes technical meaning.
function tech_tidy_line($line) {
    $s = trim(preg_replace('/\s+/', ' ', $line));
    if ($s === '') return '';
    $spell = tech_spell_map(); $abbr = tech_abbrev_map();
    $words = preg_split('/(\s+)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($words as $i => $w) {
        if (trim($w) === '') continue;
        $bare = strtolower(preg_replace('/[^a-z0-9\/]+/i', '', $w));
        if ($bare === '') continue;
        $tail = preg_replace('/^[a-z0-9\/]+/i', '', $w);
        if (isset($spell[$bare])) { $words[$i] = $spell[$bare] . $tail; continue; }
        if (isset($abbr[$bare]) && $abbr[$bare] !== $bare) { $words[$i] = $abbr[$bare] . $tail; continue; }
    }
    $s = implode('', $words);
    $s = ucfirst($s);
    if (!preg_match('/[.!?]$/', $s)) $s .= '.';
    return $s;
}
// Expand a whole block of shorthand into engineering language, line by line.
// Returns ['text'=>string, 'lines'=>[['in'=>..,'out'=>..,'matched'=>bool], ...]].
function tech_expand($input) {
    $phrases = tech_phrases();
    $out = []; $detail = [];
    foreach (preg_split('/\r?\n|;\s*/', (string)$input) as $raw) {
        $raw = trim($raw);
        if ($raw === '') continue;
        $m = tech_match_phrase($raw, $phrases);
        if ($m) { $out[] = $m['phrase']; $detail[] = ['in'=>$raw, 'out'=>$m['phrase'], 'matched'=>true, 'id'=>$m['id']]; }
        else { $t = tech_tidy_line($raw); $out[] = $t; $detail[] = ['in'=>$raw, 'out'=>$t, 'matched'=>false, 'id'=>null]; }
    }
    return ['text'=>implode(' ', $out), 'lines'=>$detail];
}
// Bump usage counters so the library learns what's used most (Part 16 groundwork).
function tech_note_usage($ids) {
    foreach (array_filter((array)$ids) as $id) db()->prepare("UPDATE tech_phrases SET usage_count=usage_count+1 WHERE id=?")->execute([(int)$id]);
}

// ---- Handler: writing assistant (standalone tool + AJAX expand) ----
function ops_idems_writing($method) {
    ops_require((bool)current_user(), 'Please log in.');
    // AJAX: expand text, used by the inline "Improve" button on report forms
    if ($method === 'POST' && !empty($_POST['ajax'])) {
        $r = tech_expand($_POST['text'] ?? '');
        tech_note_usage(array_column($r['lines'], 'id'));
        header('Content-Type: application/json'); echo json_encode($r); return true;
    }
    $input = $_POST['text'] ?? ''; $result = null;
    if ($method === 'POST') { $result = tech_expand($input); tech_note_usage(array_column($result['lines'], 'id')); }
    view('ops/idems/writing', ['input'=>$input, 'result'=>$result, 'cats'=>PHRASE_CATEGORIES, 'phrases'=>tech_phrases()]);
    return true;
}
// ---- Handler: phrase library master ----
function ops_idems_phrases($route, $method) {
    ops_require(is_master() || can('idems.type.manage') || can('master.manage'), 'You cannot manage the phrase library.');
    $pdo = db();
    if ($method === 'POST') {
        if (($_POST['_do'] ?? '') === 'del') {
            $p = ops_one("SELECT * FROM tech_phrases WHERE id=?", [(int)($_POST['id'] ?? 0)]);
            if ($p && !$p['is_system']) { $pdo->prepare("DELETE FROM tech_phrases WHERE id=?")->execute([$p['id']]); flash('Phrase removed.'); }
            elseif ($p) { $pdo->prepare("UPDATE tech_phrases SET active=0 WHERE id=?")->execute([$p['id']]); flash('Standard phrase deactivated.'); }
            redirect('/phrase-library');
        }
        $id = (int)($_POST['id'] ?? 0);
        $cat = isset(lk_options_or('phrase_category', PHRASE_CATEGORIES)[$_POST['category'] ?? '']) ? $_POST['category'] : 'OBSERVATION';
        $sh = trim($_POST['shorthand'] ?? ''); $ph = trim($_POST['phrase'] ?? '');
        if ($ph === '') { flash('The phrase text is required.', 'error'); redirect('/phrase-library'); }
        $act = !empty($_POST['active']) ? 1 : 0; $disc = trim($_POST['discipline'] ?? '');
        if ($id) $pdo->prepare("UPDATE tech_phrases SET category=?, shorthand=?, phrase=?, discipline=?, active=? WHERE id=?")->execute([$cat, $sh, $ph, $disc, $act, $id]);
        else $pdo->prepare("INSERT INTO tech_phrases (category,shorthand,phrase,discipline,active,is_system,sort_order,created_by,created_at) VALUES (?,?,?,?,?,0,?,?,?)")
            ->execute([$cat, $sh, $ph, $disc, $act, (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM tech_phrases"), user_name(current_user()), date('c')]);
        flash('Phrase saved.'); redirect('/phrase-library');
    }
    $cat = $_GET['cat'] ?? '';
    $rows = $cat ? tech_phrases($cat, false) : tech_phrases(null, false);
    $edit = ($route === 'phrase-edit') ? ops_one("SELECT * FROM tech_phrases WHERE id=?", [(int)($_GET['id'] ?? 0)]) : null;
    view('ops/idems/phrases', ['rows'=>$rows, 'edit'=>$edit, 'cats'=>PHRASE_CATEGORIES, 'cat'=>$cat]);
    return true;
}

// ===========================================================================
//  Phase 8: Smart remarks & conclusions + automatic Release Note
//  Rule-based (no AI needed): reads the report's own findings and the phrase
//  library to propose a summary, observations, deviations, recommendations,
//  acceptance status and release decision. The inspector always edits/approves.
// ===========================================================================
// Pull a standard phrase by its shorthand (falls back to a supplied default).
function tech_phrase_by($shorthand, $default = '') {
    $p = ops_one("SELECT phrase FROM tech_phrases WHERE active=1 AND LOWER(shorthand)=? LIMIT 1", [strtolower($shorthand)]);
    return $p ? $p['phrase'] : $default;
}
// Scan the filled body for signals: negative findings, hold/witness markers, and
// any free text the inspector already wrote.
function idems_scan_findings($doc, $fields, $data) {
    $neg = []; $pos = []; $holds = []; $witness = []; $notes = [];
    $negWords = ['not ok','not acceptable','reject','fail','failed','deviat','non-conform','nonconform','ncr','defect','crack','dent','leak','short','missing','expired','incomplete','inadequate','rework','out of tolerance','beyond tolerance'];
    foreach ($fields as $f) {
        $k = $f['fkey']; $v = $data[$k] ?? '';
        if ($f['ftype'] === 'table') {
            if (!is_array($v)) continue;
            foreach ($v as $row) {
                $line = strtolower(implode(' ', array_map('strval', (array)$row)));
                foreach ($negWords as $w) if (strpos($line, $w) !== false) { $neg[] = trim(implode(' · ', array_filter(array_map('strval', (array)$row)))); break; }
            }
            continue;
        }
        if (in_array($f['ftype'], ['photo','file','signature','sigblock','heading','note','richtext'], true)) continue;
        if (is_array($v)) $v = implode(', ', $v);
        $v = trim((string)$v);
        if ($v === '') continue;
        $lv = strtolower($v);
        $isNeg = false;
        foreach ($negWords as $w) if (strpos($lv, $w) !== false) { $isNeg = true; break; }
        $label = $f['label'] ?: $k;
        if ($isNeg) $neg[] = $label . ': ' . $v;
        elseif ($f['ftype'] === 'textarea') $notes[] = $v;
        elseif (in_array($lv, ['ok','yes','acceptable','accepted','pass','passed','satisfactory'], true)) $pos[] = $label;
        if (strpos($lv, 'hold') !== false) $holds[] = $label . ': ' . $v;
        if (strpos($lv, 'witness') !== false) $witness[] = $label . ': ' . $v;
    }
    return ['neg'=>$neg, 'pos'=>$pos, 'holds'=>$holds, 'witness'=>$witness, 'notes'=>$notes];
}
// Build the suggested blocks for a report. Returns a keyed array of proposals.
function idems_smart_remarks($doc, $fields, $data) {
    $s = idems_scan_findings($doc, $fields, $data);
    $clean = empty($s['neg']);
    $client = $doc['client_disp'] ?: ($doc['client_name'] ?? '');
    $vendor = $doc['vendor_disp'] ?: ($doc['vendor_name'] ?? '');
    $what = $doc['item_desc'] ?? ($doc['title'] ?: ($doc['type_name'] ?? 'the subject item'));
    // --- summary ---
    $sum = 'The ' . strtolower($doc['type_name'] ?: 'inspection') . ' was carried out'
        . ($vendor ? ' at ' . $vendor : '') . ($doc['location'] ? ', ' . $doc['location'] : '')
        . ($doc['inspection_date'] ? ' on ' . date('d M Y', strtotime($doc['inspection_date'])) : '')
        . ($client ? ' on behalf of ' . $client : '') . '.';
    if ($doc['po_ref'] || $doc['drawing_no'] || $doc['qap_rev']) {
        $refs = array_filter([$doc['po_ref'] ? 'PO ' . $doc['po_ref'] : '', $doc['drawing_no'] ? 'drawing ' . $doc['drawing_no'] . ($doc['drawing_rev'] ? ' Rev ' . $doc['drawing_rev'] : '') : '', $doc['qap_rev'] ? 'QAP Rev ' . $doc['qap_rev'] : '']);
        $sum .= ' The inspection was performed against ' . implode(', ', $refs) . ($doc['standards'] ? ' and ' . $doc['standards'] : '') . '.';
    }
    // --- observations ---
    $obs = [];
    foreach ($s['notes'] as $n) $obs[] = $n;
    if ($s['pos']) $obs[] = 'The following were verified and found acceptable: ' . implode(', ', array_slice($s['pos'], 0, 10)) . '.';
    if (!$obs) $obs[] = tech_phrase_by('visual ok', 'Visual examination was carried out and no unacceptable surface defects were observed.');
    // --- deviations ---
    $dev = [];
    foreach ($s['neg'] as $n) $dev[] = $n;
    // --- conclusion + acceptance ---
    $concl = $clean
        ? tech_phrase_by('satisfactory', 'Based on the inspection carried out and the documents reviewed, the workmanship and quality were found to be satisfactory.')
        : tech_phrase_by('not satisfactory', 'Based on the inspection carried out, the workmanship and quality were not found to be satisfactory; refer to the observations recorded above.');
    $accept = $clean ? tech_phrase_by('accepted', 'The inspected item is found acceptable and is hereby cleared for despatch, subject to the approved documentation.')
                     : tech_phrase_by('accepted with conditions', 'The inspected item is accepted subject to satisfactory closure of the observations recorded in this report.');
    // --- recommendations / follow-ups ---
    $recs = [];
    if (!$clean) { $recs[] = tech_phrase_by('close observations', 'It is recommended that the observations recorded in this report be closed prior to despatch.');
                   $recs[] = tech_phrase_by('re-inspection', 'It is recommended that a re-inspection be arranged after completion of the corrective action.'); }
    else $recs[] = tech_phrase_by('submit documents', 'It is recommended that the manufacturer submit the pending quality records for review and endorsement.');
    return [
        'clean'      => $clean,
        'summary'    => $sum,
        'observations' => implode(' ', $obs),
        'deviations' => $dev ? implode('; ', $dev) . '.' : '',
        'holds'      => $s['holds'] ? implode('; ', $s['holds']) . '.' : '',
        'witness'    => $s['witness'] ? implode('; ', $s['witness']) . '.' : '',
        'conclusion' => $concl,
        'acceptance' => $accept,
        'recommendations' => implode(' ', $recs),
        'result'     => $clean ? 'ACCEPTED' : 'ACCEPTED_COND',
        'release'    => $clean ? 'RELEASED' : 'RELEASED_COND',
    ];
}
// Assemble the proposed blocks into one remarks text.
function idems_smart_remarks_text($p) {
    $parts = array_filter([
        $p['summary'] ?? '', $p['observations'] ?? '',
        ($p['deviations'] ?? '') ? 'Deviations / observations: ' . $p['deviations'] : '',
        ($p['holds'] ?? '') ? 'Hold points: ' . $p['holds'] : '',
        ($p['witness'] ?? '') ? 'Witness points: ' . $p['witness'] : '',
        $p['conclusion'] ?? '', $p['acceptance'] ?? '', $p['recommendations'] ?? '',
    ]);
    return implode("\n\n", $parts);
}
// ---- Handler: preview / apply smart remarks ----
function ops_idems_smart($method) {
    $doc = ops_one("SELECT d.*, bp.display_name client_disp, bp.legal_name client_name, v.display_name vendor_disp, v.legal_name vendor_name, rt.name type_name
        FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.client_id LEFT JOIN business_partners v ON v.id=d.vendor_id LEFT JOIN report_types rt ON rt.id=d.report_type_id
        WHERE d.id=? AND d.deleted=0", [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
    if (!$doc) { http_response_code(404); view('notfound'); return true; }
    $fields = idems_fields($doc['report_type_id']);
    $data = json_decode($doc['data'] ?: '[]', true) ?: [];
    $p = idems_smart_remarks($doc, $fields, $data);
    if ($method === 'POST' && ($_POST['_do'] ?? '') === 'apply') {
        ops_require(idems_can_edit_doc($doc), 'This report is finalized and can no longer be edited.');
        $text = trim($_POST['remarks'] ?? '');
        $res = $_POST['result'] ?? $p['result']; $rel = $_POST['release_status'] ?? $p['release'];
        if (!isset(lk_options_or('inspection_result', IDEMS_RESULTS)[$res])) $res = $p['result'];
        if (!isset(lk_options_or('release_status', IDEMS_RELEASE)[$rel])) $rel = $p['release'];
        db()->prepare("UPDATE report_docs SET remarks=?, result=?, release_status=?, updated_at=? WHERE id=?")->execute([$text, $res, $rel, date('c'), $doc['id']]);
        idems_log('report_doc', $doc['id'], 'SMART_REMARKS', ['irn'=>$doc['irn'], 'field'=>'remarks']);
        flash('Suggested remarks applied. Review and edit as required.');
        redirect('/document?id=' . $doc['id']);
    }
    view('ops/idems/smart', ['doc'=>$doc, 'p'=>$p, 'text'=>idems_smart_remarks_text($p)]);
    return true;
}

// ---- Reissue: amend a finalized report as a new revision --------------------
// Clones an issued report's full content into a fresh DRAFT at rev+1, keeping the
// same report number marked "/R<rev>", and links the two so the lineage is
// intact. The original stays immutable; the revision runs the normal
// submit -> approve -> issue workflow again, so it is issued as a real Rev n.
// Returns [newId, error]; error 'EXISTS' means a revision already exists (newId
// points at it) so the caller can redirect rather than make a second one.
function idems_revise_doc($src) {
    if (!$src) return [0, 'Report not found.'];
    if ((int)($src['finalized'] ?? 0) !== 1 && !in_array($src['status'] ?? '', ['ISSUED', 'REJECTED'], true))
        return [0, 'Only an issued report can be reissued as a revision.'];
    if (!empty($src['revised_by_id'])) {
        $ex = ops_one("SELECT id FROM report_docs WHERE id=? AND deleted=0", [(int)$src['revised_by_id']]);
        if ($ex) return [(int)$ex['id'], 'EXISTS'];
    }
    $newRev = (int)($src['rev'] ?? 0) + 1;
    $base = (string)$src['irn'];
    $irn = $base . '/R' . $newRev; $i = $newRev;
    while (ops_val("SELECT COUNT(*) FROM report_docs WHERE irn=?", [$irn]) > 0) { $i++; $irn = $base . '/R' . $i; }
    // Copy the content; the whole lifecycle (approval, finalize, verify, client
    // decision) is reset so the revision earns its own issue.
    $copy = [
        'irn' => $irn, 'report_type_id' => $src['report_type_id'], 'type_code' => $src['type_code'],
        'title' => $src['title'], 'client_id' => $src['client_id'], 'vendor_id' => $src['vendor_id'],
        'call_id' => $src['call_id'], 'job_id' => $src['job_id'], 'company_code' => $src['company_code'],
        'branch_code' => $src['branch_code'], 'client_code' => $src['client_code'], 'project_code' => $src['project_code'],
        'project_name' => $src['project_name'], 'fy_label' => $src['fy_label'], 'serial' => $src['serial'],
        'office_id' => $src['office_id'], 'sbu' => $src['sbu'], 'po_ref' => $src['po_ref'], 'drawing_no' => $src['drawing_no'],
        'drawing_rev' => $src['drawing_rev'], 'qap_rev' => $src['qap_rev'], 'standards' => $src['standards'],
        'location' => $src['location'], 'product_category' => $src['product_category'], 'material_grade' => $src['material_grade'],
        'inspector_id' => $src['inspector_id'], 'approver_user_id' => $src['approver_user_id'],
        'inspection_date' => $src['inspection_date'], 'result' => $src['result'], 'release_status' => $src['release_status'],
        'data' => $src['data'], 'remarks' => $src['remarks'], 'status' => 'DRAFT', 'finalized' => 0, 'rev' => $newRev,
        'revises_id' => (int)$src['id'], 'created_by' => user_name(current_user()), 'created_at' => date('c'), 'updated_at' => date('c'),
    ];
    $keys = array_keys($copy);
    db()->prepare("INSERT INTO report_docs (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($copy));
    $newId = (int)db()->lastInsertId();
    db()->prepare("UPDATE report_docs SET revised_by_id=?, updated_at=? WHERE id=?")->execute([$newId, date('c'), (int)$src['id']]);
    idems_log('report_doc', $newId, 'REVISION_DRAFT', ['irn' => $irn, 'new' => 'Rev ' . $newRev . ' of ' . $base]);
    idems_log('report_doc', (int)$src['id'], 'REVISED', ['irn' => $base, 'new' => $irn]);
    return [$newId, ''];
}

// ---- Content seal: prove the report itself has not been altered -------------
// The canonical string an issued report's seal is computed over — its immutable
// content, so a later change to any of it is detectable. Used identically at
// issue and at verification, so the two always agree. Excludes the seal itself
// and the mutable lifecycle fields.
function idems_content_payload($doc) {
    $keys = ['irn', 'rev', 'report_type_id', 'type_code', 'title', 'client_id', 'vendor_id', 'call_id',
             'job_id', 'office_id', 'sbu', 'inspector_id', 'approver_user_id', 'inspection_date',
             'issue_date', 'result', 'release_status', 'remarks', 'data'];
    $parts = [];
    foreach ($keys as $k) $parts[] = (string)($doc[$k] ?? '');
    return implode('|', $parts);
}
function idems_content_seal_compute($doc) { return hash('sha256', idems_content_payload($doc)); }

// Freeze the seal at issue. Wrapped: sealing is defence-in-depth and must never
// be what stops a report being issued.
function idems_seal_content($docId) {
    try {
        $d = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$docId]);
        if ($d) db()->prepare("UPDATE report_docs SET content_seal=? WHERE id=?")
            ->execute([idems_content_seal_compute($d), (int)$docId]);
    } catch (Throwable $e) { /* never block issue */ }
}

// At verification: is the sealed content still intact? A report issued before
// this feature has no seal and cannot be judged, so it is reported unsealed
// rather than failed.
function idems_content_check($doc) {
    $seal = (string)($doc['content_seal'] ?? '');
    if ($seal === '') return ['sealed' => false, 'ok' => true];
    return ['sealed' => true, 'ok' => hash_equals($seal, idems_content_seal_compute($doc))];
}

// Who at the client should be told a report is ready. Portal users the client
// company invited come first (they have somewhere to sign in and read it);
// failing that, the primary contact on the partner record. Only people entitled
// to see reports — a portal user restricted to invoices is not told about a
// report they cannot open. Returns a de-duplicated list of e-mail addresses.
function idems_client_notify_emails($clientId) {
    $clientId = (int)$clientId;
    if ($clientId <= 0) return [];
    $emails = [];
    try {
        foreach (ops_all("SELECT email, perms FROM client_users WHERE partner_id=? AND is_active=1 AND email <> ''", [$clientId]) ?: [] as $u) {
            $perms = trim((string)($u['perms'] ?? ''));
            // Blank perms means everything (the original portal grant); otherwise
            // the person must actually be allowed to see reports.
            if ($perms === '' || strpos($perms, 'reports') !== false) $emails[] = strtolower(trim((string)$u['email']));
        }
    } catch (Throwable $e) { /* portal not installed — fall through to contacts */ }
    if (!$emails) {
        try {
            $c = ops_one("SELECT email FROM partner_contacts WHERE partner_id=? AND email <> '' ORDER BY is_primary DESC, id ASC LIMIT 1", [$clientId]);
            if ($c && trim((string)$c['email']) !== '') $emails[] = strtolower(trim((string)$c['email']));
        } catch (Throwable $e) { /* contacts not present */ }
    }
    return array_values(array_unique(array_filter($emails)));
}

// P2 — tell the client a report has been issued. It never carries the report
// itself or any finding: confidentiality is the whole point of the report, and
// the portal is where a client reads one behind their own sign-in. The e-mail
// says "it is ready, here is how to see it" and nothing more — the reference,
// the public verification code (safe by design), and, if the portal is on, a
// link to sign in. Gated by a setting so a customer can switch it off, and
// wrapped so a mail failure never blocks issuing. Returns the number notified.
function idems_notify_client_issued($doc) {
    if (!function_exists('setting_get') || (string)setting_get('notify_client_on_issue', '1') === '0') return 0;
    $clientId = (int)($doc['client_id'] ?? 0);
    if ($clientId <= 0) return 0;
    $to = idems_client_notify_emails($clientId);
    if (!$to) return 0;

    $irn = (string)($doc['irn'] ?? '');
    $rword = function_exists('Tl') ? Tl('report') : 'report';
    $lines = [
        'Your ' . $rword . ' ' . $irn . ' has been issued and is ready for you.',
        '',
    ];
    if (function_exists('portal_enabled') && portal_enabled() && function_exists('portal_base_url')) {
        $lines[] = 'Sign in to your portal to view and download it:';
        $lines[] = rtrim(portal_base_url(), '/') . '/portal';
        $lines[] = '';
    } else {
        $lines[] = 'Contact us for your copy, quoting the reference above.';
        $lines[] = '';
    }
    if (function_exists('verify_code_for')) {
        $code = verify_code_for($doc);
        if ($code) {
            $lines[] = 'To confirm it is genuine and unaltered, you can verify it — no account needed —';
            if (function_exists('verify_url')) $lines[] = 'at ' . verify_url($doc);
            $lines[] = 'using the code: ' . $code;
            $lines[] = '';
        }
    }
    $lines[] = function_exists('app_name') ? app_name() : '';
    $body = implode("\n", $lines);
    $subject = 'Your ' . $rword . ' ' . $irn . ' is ready';

    $sent = 0;
    foreach ($to as $addr) {
        try { if (ops_mail($addr, $subject, $body, '', 'report_issued')) $sent++; }
        catch (Throwable $e) { /* logged inside ops_mail; never block issue */ }
    }
    if (function_exists('idems_log')) try {
        idems_log('report_doc', (int)$doc['id'], 'CLIENT_NOTIFIED', ['irn' => $irn, 'to' => implode(',', $to)]);
    } catch (Throwable $e) {}
    return count($to);
}

// ---- Automatic Release Note drafted from a finalized/approved report ----
// Creates a new RN report instance, carries the source data across, links them.
function ops_idems_release_note($method) {
    if ($method !== 'POST') redirect('/documents');
    $src = ops_one("SELECT d.*, bp.display_name client_disp, bp.legal_name client_name, v.display_name vendor_disp, v.legal_name vendor_name, rt.name type_name
        FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.client_id LEFT JOIN business_partners v ON v.id=d.vendor_id LEFT JOIN report_types rt ON rt.id=d.report_type_id
        WHERE d.id=? AND d.deleted=0", [(int)($_POST['id'] ?? 0)]);
    if (!$src) { http_response_code(404); view('notfound'); return true; }
    ops_require(is_master() || can('mod.idems.edit'), 'You cannot create reports.');
    if (!in_array($src['status'], ['APPROVED','ISSUED'], true) && !is_master()) {
        flash('A Release Note can only be drafted from an approved or issued report.', 'error');
        redirect('/document?id=' . $src['id']);
    }
    $rnTypeId = idems_build_release_note();   // form-driven RN type, seeded once
    // don't duplicate — match on the numeric source id (JSON escapes slashes, so
    // matching the IRN text is unreliable).
    $exists = ops_one("SELECT id, irn FROM report_docs WHERE type_code='RN' AND deleted=0 AND data LIKE ?", ['%"source_report_id":' . (int)$src['id'] . '%']);
    if ($exists) { flash('A Release Note already exists for this report: ' . $exists['irn'] . '.', 'warning'); redirect('/document?id=' . $exists['id']); }
    $fields = idems_fields($src['report_type_id']);
    $data = json_decode($src['data'] ?: '[]', true) ?: [];
    $p = idems_smart_remarks($src, $fields, $data);
    $rel = $src['release_status'] ?: $p['release'];
    $body = "Release Note issued against inspection report " . $src['irn'] . ".\n\n"
        . ($p['summary'] ?? '') . "\n\n"
        . ($p['deviations'] ? "Observations carried forward: " . $p['deviations'] . "\n\n" : '')
        . ($rel === 'RELEASED'
            ? tech_phrase_by('accepted', 'The inspected item is found acceptable and is hereby cleared for despatch, subject to the approved documentation.')
            : tech_phrase_by('accepted with conditions', 'The inspected item is released subject to satisfactory closure of the observations recorded in the referenced report.'));
    $fieldsNew = [
        'report_type_id'=>$rnTypeId, 'type_code'=>'RN', 'title'=>'Release Note — ' . ($src['title'] ?: $src['irn']),
        'client_id'=>$src['client_id'], 'vendor_id'=>$src['vendor_id'], 'call_id'=>$src['call_id'], 'job_id'=>$src['job_id'],
        'client_code'=>$src['client_code'], 'project_code'=>$src['project_code'], 'project_name'=>$src['project_name'],
        'office_id'=>$src['office_id'], 'sbu'=>$src['sbu'], 'po_ref'=>$src['po_ref'], 'drawing_no'=>$src['drawing_no'],
        'drawing_rev'=>$src['drawing_rev'], 'qap_rev'=>$src['qap_rev'], 'standards'=>$src['standards'], 'location'=>$src['location'],
        'product_category'=>$src['product_category'], 'material_grade'=>$src['material_grade'],
        'inspector_id'=>$src['inspector_id'], 'approver_user_id'=>$src['approver_user_id'],
        'inspection_date'=>$src['inspection_date'], 'result'=>$src['result'] ?: $p['result'], 'release_status'=>$rel,
        'remarks'=>'',
    ];
    // §12/§63 — link the Release to the inspection it is generated from, so the
    // Phase-3 eligibility engine reads the inspection's result / accepted qty /
    // tests / documents / NCRs directly (no re-entry). Column added by URADE.
    if (function_exists('urade_migrate')) $fieldsNew['release_of_id'] = (int)$src['id'];
    [$irn, $serial] = idems_generate_irn($fieldsNew);
    $tok = idems_tokens_for($fieldsNew);
    // Carry the inspection report's own field values across — the identifying
    // details, the PO item table and the ITP activity list — so the Release Note
    // renders through the same form engine with the same content, under its own
    // number. The release statement is prefilled with the default wording; the
    // inspection report number(s) are left blank for manual entry. §releasenote
    $carry = ['client','end_user','vendor','po_number','project','inspection_stage','inspection_date','location','inspector','po_items','scope_activities',
        // UIRE (Phase 2) inspection fields — carried so a Release generated from a
        // universal inspection report keeps the same identifying + outcome details.
        'inspection_type','item_desc','tag_no','serial_no','heat_no','batch_no',
        'inspection_result','inspected_qty','accepted_qty','rejected_qty','inspection_items'];
    $rnArr = ['source_irn' => $src['irn'], 'source_report_id' => (int)$src['id']];
    foreach ($carry as $ck) if (isset($data[$ck]) && $data[$ck] !== '' && $data[$ck] !== 'NA') $rnArr[$ck] = $data[$ck];
    if (isset($rnArr['po_items'])) $rnArr['po_items'] = idems_released_line_items($rnArr['po_items']);   // §releasenote — show only released line items
    $rnArr['release_statement'] = idems_release_statement_default();
    $rnArr['ir_numbers']        = '';
    $rnData = json_encode($rnArr);
    $cols = array_merge(['irn','company_code','branch_code','fy_label','serial','status','rev','data','created_by','created_at','updated_at'], array_keys($fieldsNew));
    $vals = array_merge([$irn, $tok['{COMPANY}'], $tok['{BRANCH}'], $tok['{FY}'], $serial, 'DRAFT', 0, $rnData, user_name(current_user()), date('c'), date('c')], array_values($fieldsNew));
    $ph = implode(',', array_fill(0, count($cols), '?'));
    db()->prepare("INSERT INTO report_docs (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
    $id = (int)db()->lastInsertId();
    idems_log('report_doc', $id, 'RELEASE_NOTE_DRAFT', ['irn'=>$irn, 'new'=>'from ' . $src['irn']]);
    idems_log('report_doc', $src['id'], 'RELEASE_NOTE_CREATED', ['irn'=>$src['irn'], 'new'=>$irn]);
    flash('Release Note ' . $irn . ' drafted from ' . $src['irn'] . '. Review the wording, then submit/issue it.');
    redirect('/document?id=' . $id);
    return true;
}

// ===========================================================================
//  Phase 9: AI-assisted documentation & conflict detection
//  Source documents (PO / QAP / drawings / specs / MTCs / calibration certs …)
//  are uploaded against a report. A rule-based checker ALWAYS runs (no AI needed);
//  when an AI provider is configured it adds a deeper review. The inspector is
//  always the final authority — nothing is applied automatically.
// ===========================================================================
const SOURCE_DOC_TYPES = [
    'PO'=>'Purchase Order', 'CALL'=>'Inspection Call', 'QAP'=>'QAP / ITP', 'DRAWING'=>'Approved Drawing',
    'SPEC'=>'Technical Specification', 'STANDARD'=>'Code / Standard', 'MTC'=>'Material Test Certificate',
    'CALIB'=>'Calibration Certificate', 'PREV_REPORT'=>'Previous Report', 'CUST_INSTR'=>'Customer Instruction', 'OTHER'=>'Other',
];
// ---------------------------------------------------------------------------
//  Deliverables = report types.
//
//  A deputation promises the client certain documents, and those documents are
//  exactly the ones in the report-types register. There used to be two lists —
//  a 14-value DELIVERABLES constant on the deputation and the 37-row register
//  here — so IR, NCR, CoC and Release Note each existed twice. Deputations now
//  pick from the register, and the old codes are rewritten to match.
// ---------------------------------------------------------------------------
const DELIVERABLE_ALIASES = [
    'EXP_REP' => 'ER', 'VA_REP' => 'VASR', 'AUDIT_REP' => 'VAR', 'DPR' => 'DIR',
    'FINAL_REP' => 'FIR', 'PUNCH' => 'PPR', 'TC_REVIEW' => 'TCRV', 'DIM_REP' => 'DIM',
];
// code => name, straight from the register, so adding a report type adds a
// deliverable. Falls back to the shipped list if the register is unreachable.
function deliverable_options() {
    static $opts = null;
    if ($opts !== null) return $opts;
    try {
        $out = [];
        foreach (ops_all("SELECT code, name FROM report_types WHERE active=1 ORDER BY sort_order, code") as $r)
            $out[$r['code']] = $r['name'];
        return $opts = ($out ?: DELIVERABLES);
    } catch (Throwable $e) { return $opts = DELIVERABLES; }
}
// Deliverables are a CSV of codes, so the rewrite is done in PHP rather than in
// SQL — the string functions needed differ between MySQL and SQLite, and these
// are small tables. Idempotent: a second run finds nothing left to change.
function idems_migrate_deliverables() {
    foreach ([['jobs', 'deliverables'], ['quote_lines', 'deliverables']] as [$t, $c]) {
        try { $rows = ops_all("SELECT id, $c v FROM $t WHERE $c <> ''"); }
        catch (Throwable $e) { continue; }
        foreach ($rows as $row) {
            $codes = array_filter(array_map('trim', explode(',', (string)$row['v'])), fn($x) => $x !== '');
            $mapped = array_values(array_unique(array_map(
                fn($x) => DELIVERABLE_ALIASES[$x] ?? $x, $codes)));
            if ($mapped === array_values($codes)) continue;
            try { db()->prepare("UPDATE $t SET $c=? WHERE id=?")->execute([implode(',', $mapped), $row['id']]); }
            catch (Throwable $e) {}
        }
    }
}
function deliverables_pending() {
    foreach (array_keys(DELIVERABLE_ALIASES) as $old) {
        foreach ([['jobs', 'deliverables'], ['quote_lines', 'deliverables']] as [$t, $c]) {
            try { if ((int)ops_val("SELECT COUNT(*) FROM $t WHERE $c LIKE ?", ["%$old%"]) > 0) return true; }
            catch (Throwable $e) { return false; }
        }
    }
    return false;
}

// Which source documents a complete inspection pack should contain.
// Shipped default; overridden in Settings → Reporting controls.
const EXPECTED_SOURCE_DOCS = ['PO','QAP','DRAWING','SPEC'];
function expected_source_docs() {
    $s = (string)setting_get('expected_source_docs', '');
    if ($s === '') return EXPECTED_SOURCE_DOCS;
    $v = array_values(array_intersect(array_keys(SOURCE_DOC_TYPES), array_map('trim', explode(',', $s))));
    return $v ?: EXPECTED_SOURCE_DOCS;
}
// Extract readable text from an uploaded source document (best-effort, no libs).
function idems_source_text($mime, $raw, $limit = 20000) {
    $mime = strtolower((string)$mime);
    if (strpos($mime, 'text/') === 0 || strpos($mime, 'json') !== false || strpos($mime, 'csv') !== false) return substr($raw, 0, $limit);
    if (strpos($mime, 'wordprocessingml') !== false && class_exists('ZipArchive')) {
        $tmp = tempnam(sys_get_temp_dir(), 'sd'); file_put_contents($tmp, $raw);
        $z = new ZipArchive();
        if ($z->open($tmp) === true) { $xml = $z->getFromName('word/document.xml'); $z->close(); @unlink($tmp);
            if ($xml) { $t = preg_replace('/<w:p\b[^>]*>/', "\n", $xml); return substr(trim(html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8')), 0, $limit); } }
        @unlink($tmp);
    }
    if (strpos($mime, 'pdf') !== false) {
        // pull any uncompressed text operators; scanned PDFs yield nothing (that's fine)
        $out = '';
        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $raw, $m)) {
            foreach ($m[0] as $s) { $s = substr($s, 1, -1); $s = str_replace(['\\(','\\)','\\\\'], ['(',')','\\'], $s); if (preg_match('/[A-Za-z0-9]/', $s)) $out .= $s . ' '; }
        }
        return substr(trim(preg_replace('/\s+/', ' ', $out)), 0, $limit);
    }
    return '';
}
// How each expected document stands on this report. Uploading a file is only
// one of the answers — "not applicable to this scope" and "the vendor has not
// given it to us" are real, different answers, and a review that can only say
// "missing" forces the engineer to leave it blank and argue in the remarks.
const DOC_REVIEW_STATES = [
    'PENDING'        => 'Not reviewed yet',
    'RECEIVED'       => 'Received & acceptable',
    'RECEIVED_OBS'   => 'Received with observations',
    'NOT_APPLICABLE' => 'Not applicable to this scope',
    'NOT_AVAILABLE'  => 'Not available from the vendor',
    'AWAITED'        => 'Awaited',
];
function idems_doc_review($docId) {
    $out = [];
    foreach (ops_all("SELECT * FROM report_doc_review WHERE report_doc_id=?", [(int)$docId]) as $r)
        $out[$r['doc_type']] = $r;
    return $out;
}
function idems_doc_review_set($docId, $type, $state, $note) {
    if (!isset(DOC_REVIEW_STATES[$state])) $state = 'PENDING';
    $ex = ops_one("SELECT id FROM report_doc_review WHERE report_doc_id=? AND doc_type=?", [(int)$docId, $type]);
    if ($ex) db()->prepare("UPDATE report_doc_review SET state=?, note=?, reviewed_by=?, reviewed_at=? WHERE id=?")
        ->execute([$state, $note, user_name(current_user()), date('c'), (int)$ex['id']]);
    else db()->prepare("INSERT INTO report_doc_review (report_doc_id,doc_type,state,note,reviewed_by,reviewed_at) VALUES (?,?,?,?,?,?)")
        ->execute([(int)$docId, $type, $state, $note, user_name(current_user()), date('c')]);
}
// Source docs attached to a report (stored in report_files with kind='src_<TYPE>').
function idems_source_docs($docId) {
    $rows = ops_all("SELECT id, kind, field_key, file_name, mime, note, created_at FROM report_files WHERE report_doc_id=? AND kind LIKE 'src_%' ORDER BY id", [(int)$docId]);
    foreach ($rows as &$r) { $r['doc_type'] = substr($r['kind'], 4); $r['doc_label'] = lk_options_or('source_doc_type', SOURCE_DOC_TYPES)[$r['doc_type']] ?? $r['doc_type']; }
    return $rows;
}
// ---- Rule-based conflict checks (always available, no AI) ----
// Returns a list of ['severity'=>high|medium|low, 'kind'=>..., 'detail'=>...].
function idems_rule_checks($doc, $srcDocs) {
    $out = [];
    $have = [];
    foreach ($srcDocs as $s) $have[$s['doc_type']] = true;
    // A document the reviewer has marked not applicable, or recorded as not
    // available from the vendor, is a decision — not an omission. Nagging about
    // it is what teaches people to ignore the checks.
    $review = idems_doc_review($doc['id'] ?? 0);
    // 1. missing documents
    foreach (expected_source_docs() as $t) {
        $st = $review[$t]['state'] ?? '';
        if (in_array($st, ['NOT_APPLICABLE', 'NOT_AVAILABLE'], true)) continue;
        if (empty($have[$t])) $out[] = ['severity'=>'medium', 'kind'=>'Missing document', 'detail'=>(lk_options_or('source_doc_type', SOURCE_DOC_TYPES)[$t] ?? $t) . ' has not been uploaded against this report.'];
    }
    if (empty($have['MTC']) && empty($have['CALIB'])) $out[] = ['severity'=>'low', 'kind'=>'Missing document', 'detail'=>'No material test certificate or calibration certificate has been attached.'];
    // 2. header completeness / traceability
    if (trim((string)($doc['po_ref'] ?? '')) === '') $out[] = ['severity'=>'medium', 'kind'=>'Traceability', 'detail'=>'No purchase-order reference is recorded on the report.'];
    if (trim((string)($doc['drawing_no'] ?? '')) === '') $out[] = ['severity'=>'medium', 'kind'=>'Traceability', 'detail'=>'No drawing number is recorded on the report.'];
    elseif (trim((string)($doc['drawing_rev'] ?? '')) === '') $out[] = ['severity'=>'low', 'kind'=>'Revision', 'detail'=>'A drawing number is recorded but its revision is blank.'];
    if (trim((string)($doc['qap_rev'] ?? '')) === '') $out[] = ['severity'=>'low', 'kind'=>'Revision', 'detail'=>'No QAP revision is recorded on the report.'];
    if (trim((string)($doc['standards'] ?? '')) === '') $out[] = ['severity'=>'low', 'kind'=>'Applicable standard', 'detail'=>'No applicable code/standard is recorded on the report.'];
    // 3. revision mismatch between the report header and the uploaded documents
    $texts = idems_source_text_bundle($doc['id'], 4000);
    if ($texts !== '') {
        $dn = trim((string)($doc['drawing_no'] ?? ''));
        if ($dn !== '' && stripos($texts, $dn) === false)
            $out[] = ['severity'=>'medium', 'kind'=>'Revision mismatch', 'detail'=>'Drawing "' . $dn . '" from the report header was not found in the text of the uploaded documents — verify the correct drawing/revision is attached.'];
        $rev = trim((string)($doc['drawing_rev'] ?? ''));
        if ($dn !== '' && $rev !== '' && preg_match_all('/\brev\.?\s*([A-Za-z0-9]{1,4})\b/i', $texts, $mm)) {
            $found = array_unique(array_map('strtoupper', $mm[1]));
            if ($found && !in_array(strtoupper($rev), $found, true))
                $out[] = ['severity'=>'high', 'kind'=>'Revision mismatch', 'detail'=>'The report records drawing Rev ' . $rev . ', but the uploaded documents reference Rev ' . implode(' / ', array_slice($found, 0, 4)) . '.'];
        }
        // 4. expired calibration mentioned in an attached certificate
        if (preg_match_all('/valid\s*(?:up\s*to|till|until)\s*[:\-]?\s*(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}|\d{4}-\d{2}-\d{2})/i', $texts, $cm)) {
            foreach ($cm[1] as $d) { $ts = strtotime(str_replace(['/', '.'], '-', $d));
                if ($ts && $ts < strtotime($doc['inspection_date'] ?: date('Y-m-d')))
                    $out[] = ['severity'=>'high', 'kind'=>'Expired calibration', 'detail'=>'An attached certificate shows validity up to ' . $d . ', which is before the inspection date.']; }
        }
    } elseif ($srcDocs) {
        $out[] = ['severity'=>'low', 'kind'=>'Not readable', 'detail'=>'The uploaded documents contain no extractable text (they may be scans); automatic cross-checking is limited to the report header.'];
    }
    return $out;
}
// Concatenate the readable text of a report's source documents (for checks + AI).
function idems_source_text_bundle($docId, $perDoc = 6000) {
    $rows = ops_all("SELECT kind, file_name, mime, data FROM report_files WHERE report_doc_id=? AND kind LIKE 'src_%' ORDER BY id", [(int)$docId]);
    $parts = [];
    foreach ($rows as $r) {
        $raw = (string)$r['data'];
        if (strpos($raw, 'base64,') !== false) $raw = base64_decode(substr($raw, strpos($raw, 'base64,') + 7));
        $t = idems_source_text($r['mime'], $raw, $perDoc);
        if (trim($t) !== '') $parts[] = '--- ' . (lk_options_or('source_doc_type', SOURCE_DOC_TYPES)[substr($r['kind'],4)] ?? $r['kind']) . ': ' . $r['file_name'] . " ---\n" . $t;
    }
    return implode("\n\n", $parts);
}
// ---- AI review (optional layer on top of the rule checks) ----
function idems_ai_review($doc, $fields, $data, $srcDocs) {
    if (!function_exists('ai_enabled') || !ai_enabled()) return [null, 'No AI provider is enabled — the rule-based checks above still apply.'];
    $bundle = idems_source_text_bundle($doc['id'], 6000);
    $body = [];
    foreach ($fields as $f) {
        if (in_array($f['ftype'], ['photo','file','signature','sigblock','heading','note','richtext'], true)) continue;
        $v = $data[$f['fkey']] ?? ''; if (is_array($v)) $v = json_encode($v);
        if (trim((string)$v) !== '') $body[] = ($f['label'] ?: $f['fkey']) . ': ' . $v;
    }
    $ctx = "INSPECTION REPORT\n"
        . "IRN: {$doc['irn']}\nType: " . ($doc['type_name'] ?? $doc['type_code']) . "\n"
        . "Client: " . ($doc['client_disp'] ?: ($doc['client_name'] ?? '')) . "\nVendor: " . ($doc['vendor_disp'] ?: ($doc['vendor_name'] ?? '')) . "\n"
        . "PO: {$doc['po_ref']}\nDrawing: {$doc['drawing_no']} Rev {$doc['drawing_rev']}\nQAP Rev: {$doc['qap_rev']}\n"
        . "Standards: {$doc['standards']}\nInspection date: {$doc['inspection_date']}\n\n"
        . "REPORT FINDINGS\n" . (implode("\n", $body) ?: '(none recorded)') . "\n\n"
        . "ATTACHED DOCUMENTS (" . count($srcDocs) . ")\n" . ($bundle !== '' ? substr($bundle, 0, 20000) : '(no extractable text)');
    $system = "You are a senior third-party-inspection (TPIA) quality engineer assisting an inspector. "
        . "Review the inspection report and its attached documents. Be factual and conservative: only state what the provided text supports. "
        . "You are an assistant, not the approving authority. Reply in plain text using exactly these headings:\n"
        . "MISSING DOCUMENTS:\nREVISION / SPEC CONFLICTS:\nTRACEABILITY ISSUES:\nSUGGESTED HOLD POINTS:\nSUGGESTED WITNESS POINTS:\nSUGGESTED REMARKS:\n"
        . "Under each heading use short '- ' bullets, or write 'None identified'. Do not invent document numbers or results.";
    [$text, $err] = ai_chat($system, $ctx, 1400);
    if ($err) return [null, $err];
    return [$text, null];
}
// Split the AI reply into its sections for display.
function idems_ai_sections($text) {
    $heads = ['MISSING DOCUMENTS','REVISION / SPEC CONFLICTS','TRACEABILITY ISSUES','SUGGESTED HOLD POINTS','SUGGESTED WITNESS POINTS','SUGGESTED REMARKS'];
    $out = []; $cur = 'NOTES'; $out[$cur] = '';
    foreach (preg_split('/\r?\n/', (string)$text) as $line) {
        $t = trim($line);
        $matched = null;
        foreach ($heads as $h) if (stripos($t, $h) === 0) { $matched = $h; break; }
        if ($matched) { $cur = $matched; $out[$cur] = ''; continue; }
        if ($t !== '') $out[$cur] = trim(($out[$cur] ?? '') . "\n" . $t);
    }
    return array_filter($out, fn($v)=>trim((string)$v) !== '');
}

// ---- Handler: document review (upload sources, run checks, optional AI) ----
function ops_idems_review($route, $method) {
    $doc = ops_one("SELECT d.*, bp.display_name client_disp, bp.legal_name client_name, v.display_name vendor_disp, v.legal_name vendor_name, rt.name type_name
        FROM report_docs d LEFT JOIN business_partners bp ON bp.id=d.client_id LEFT JOIN business_partners v ON v.id=d.vendor_id LEFT JOIN report_types rt ON rt.id=d.report_type_id
        WHERE d.id=? AND d.deleted=0", [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
    if (!$doc) { http_response_code(404); view('notfound'); return true; }
    $pdo = db();
    if ($method === 'POST') {
        $do = $_POST['_do'] ?? '';
        if ($do === 'state') {
            ops_require(idems_can_edit_doc($doc), 'This report is finalized.');
            foreach ((array)($_POST['st'] ?? []) as $t => $v)
                idems_doc_review_set($doc['id'], $t, (string)$v, trim((string)($_POST['nt'][$t] ?? '')));
            flash('Document review saved.');
            redirect('/document-review?id=' . $doc['id']);
        }
        if ($do === 'upload') {
            ops_require(idems_can_edit_doc($doc), 'This report is finalized.');
            $type = isset(lk_options_or('source_doc_type', SOURCE_DOC_TYPES)[$_POST['doc_type'] ?? '']) ? $_POST['doc_type'] : 'OTHER';
            if (!empty($_FILES['src']['name'])) {
                $names=(array)$_FILES['src']['name']; $tmp=(array)$_FILES['src']['tmp_name']; $types=(array)$_FILES['src']['type']; $errs=(array)$_FILES['src']['error'];
                for ($i=0;$i<count($names);$i++) {
                    if (($errs[$i] ?? 1)!==0 || !is_uploaded_file($tmp[$i])) continue;
                    $bytes=@file_get_contents($tmp[$i]); if ($bytes===false || strlen($bytes)>12*1024*1024) continue;
                    $pdo->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,note,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?)")
                        ->execute([$doc['id'], '', 'src_'.$type, substr($names[$i],0,255), $types[$i] ?: '', 'data:'.($types[$i] ?: 'application/octet-stream').';base64,'.base64_encode($bytes), trim($_POST['note'] ?? ''), user_name(current_user()), date('c')]);
                }
                idems_log('report_doc', $doc['id'], 'SOURCE_DOC', ['irn'=>$doc['irn'], 'new'=>$type]);
                flash('Document(s) uploaded.');
            }
            redirect('/document-review?id=' . $doc['id']);
        }
        if ($do === 'del') {
            ops_require(idems_can_edit_doc($doc), 'This report is finalized.');
            $pdo->prepare("DELETE FROM report_files WHERE id=? AND report_doc_id=? AND kind LIKE 'src_%'")->execute([(int)($_POST['file_id'] ?? 0), $doc['id']]);
            flash('Document removed.'); redirect('/document-review?id=' . $doc['id']);
        }
        if ($do === 'ai') {
            $fields = idems_fields($doc['report_type_id']);
            $data = json_decode($doc['data'] ?: '[]', true) ?: [];
            [$text, $err] = idems_ai_review($doc, $fields, $data, idems_source_docs($doc['id']));
            if ($err) flash('AI review unavailable: ' . $err, 'warning');
            else { $_SESSION['idems_ai_' . $doc['id']] = $text; idems_log('report_doc', $doc['id'], 'AI_REVIEW', ['irn'=>$doc['irn']]); flash('AI review completed — suggestions below are for your consideration; you remain the approving authority.'); }
            redirect('/document-review?id=' . $doc['id']);
        }
    }
    $srcDocs = idems_source_docs($doc['id']);
    $aiText = $_SESSION['idems_ai_' . $doc['id']] ?? '';
    view('ops/idems/review', [
        'doc'=>$doc, 'srcDocs'=>$srcDocs, 'types'=>SOURCE_DOC_TYPES,
        'review'=>idems_doc_review($doc['id']), 'reviewStates'=>DOC_REVIEW_STATES,
        'expected'=>expected_source_docs(),
        'checks'=>idems_rule_checks($doc, $srcDocs),
        'aiText'=>$aiText, 'aiSections'=>$aiText ? idems_ai_sections($aiText) : [],
        'aiOn'=>function_exists('ai_enabled') && ai_enabled(),
    ]);
    return true;
}

// ===========================================================================
//  Phase 10: evidence gallery — captions, GPS, timestamps, dedupe, organised
//  by the report section/field each photo belongs to.
// ===========================================================================
function ops_idems_evidence($method) {
    $doc = ops_one("SELECT d.*, rt.name type_name FROM report_docs d LEFT JOIN report_types rt ON rt.id=d.report_type_id WHERE d.id=? AND d.deleted=0", [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
    if (!$doc) { http_response_code(404); view('notfound'); return true; }
    $pdo = db();
    if ($method === 'POST') {
        ops_require(idems_can_edit_doc($doc), 'This report is finalized — its evidence is locked.');
        $do = $_POST['_do'] ?? '';
        if ($do === 'caption') {
            $pdo->prepare("UPDATE report_files SET caption=? WHERE id=? AND report_doc_id=?")
                ->execute([trim($_POST['caption'] ?? ''), (int)($_POST['file_id'] ?? 0), $doc['id']]);
            idems_log('report_doc', $doc['id'], 'EVIDENCE_CAPTION', ['irn'=>$doc['irn']]);
            flash('Caption saved.');
        } elseif ($do === 'del') {
            $pdo->prepare("DELETE FROM report_files WHERE id=? AND report_doc_id=? AND kind IN ('photo','file')")->execute([(int)($_POST['file_id'] ?? 0), $doc['id']]);
            idems_log('report_doc', $doc['id'], 'EVIDENCE_DELETE', ['irn'=>$doc['irn'], 'reason'=>trim($_POST['reason'] ?? '')]);
            flash('Evidence removed.');
        } elseif ($do === 'move') {
            $to = trim($_POST['to_field'] ?? '');
            $pdo->prepare("UPDATE report_files SET field_key=? WHERE id=? AND report_doc_id=?")->execute([$to, (int)($_POST['file_id'] ?? 0), $doc['id']]);
            flash('Evidence re-linked.');
        } elseif ($do === 'upload') {
            // Supporting documents the report format never asked for — a mill
            // certificate, a signed gate pass, the client's own checklist. The
            // fill screen can only offer the file boxes the template defines, so
            // an inspector holding a paper nobody anticipated had nowhere to put
            // it. Anything can be attached here, under its own heading.
            idems_evidence_attach($doc, trim($_POST['caption'] ?? ''));
        }
        redirect('/document-evidence?id=' . $doc['id']);
    }
    $fields = idems_fields($doc['report_type_id']);
    $sections = idems_sections($doc['report_type_id']);
    // label + section for each evidence field
    $fieldMeta = [];
    $secName = []; foreach ($sections as $s) $secName[(int)$s['id']] = $s['title'];
    foreach ($fields as $f) if (in_array($f['ftype'], ['photo','file'], true))
        $fieldMeta[$f['fkey']] = ['label'=>$f['label'] ?: $f['fkey'], 'section'=>$secName[(int)$f['section_id']] ?? 'Unsectioned'];
    $fieldMeta[EVIDENCE_SUPPORTING_KEY] = ['label'=>'Attached here', 'section'=>'Supporting documents'];
    $files = ops_all("SELECT id, field_key, kind, file_name, mime, gps, caption, taken_at, bytes, orig_bytes, created_by, created_at
        FROM report_files WHERE report_doc_id=? AND kind IN ('photo','file') ORDER BY id", [$doc['id']]);
    $stats = ['n'=>count($files), 'bytes'=>0, 'orig'=>0, 'gps'=>0];
    foreach ($files as $f) { $stats['bytes'] += (int)$f['bytes']; $stats['orig'] += (int)($f['orig_bytes'] ?: $f['bytes']); if (trim((string)$f['gps']) !== '') $stats['gps']++; }
    view('ops/idems/evidence', ['doc'=>$doc, 'files'=>$files, 'fieldMeta'=>$fieldMeta, 'stats'=>$stats]);
    return true;
}

// Compliance audit log viewer (basic; full super-admin dashboard is a later phase).
// ===========================================================================
//  Phase 13: self-learning suggestions
//  When a report is approved/issued, the wording your team actually used is
//  harvested so it can be offered back as a suggestion next time. Learning only
//  ever ENHANCES suggestions — it never changes a technical conclusion or an
//  approval on its own.
// ===========================================================================
function learn_norm($s) {
    $s = strtolower(trim(preg_replace('/\s+/', ' ', (string)$s)));
    return substr(preg_replace('/[^a-z0-9 ]/', '', $s), 0, 120);
}
// Record one learned value (per report type + field, and optionally per client).
function learn_record($scope, $typeId, $clientId, $fieldKey, $text) {
    $text = trim((string)$text);
    if ($text === '' || mb_strlen($text) < 8 || mb_strlen($text) > 600) return;
    $norm = learn_norm($text);
    if ($norm === '') return;
    $key = substr($scope . '|' . (int)$typeId . '|' . (int)$clientId . '|' . $fieldKey . '|' . $norm, 0, 190);
    $ex = ops_one("SELECT id, uses FROM learned_suggestions WHERE norm_key=?", [$key]);
    if ($ex) db()->prepare("UPDATE learned_suggestions SET uses=uses+1, last_seen=? WHERE id=?")->execute([date('c'), $ex['id']]);
    else db()->prepare("INSERT INTO learned_suggestions (scope,report_type_id,client_id,field_key,text_value,norm_key,uses,last_seen,created_at) VALUES (?,?,?,?,?,?,1,?,?)")
        ->execute([$scope, $typeId ?: null, $clientId ?: null, $fieldKey, $text, $key, date('c'), date('c')]);
}
// Harvest a report's wording once it is approved/issued.
function learn_from_report($doc) {
    $fields = idems_fields($doc['report_type_id']);
    $data = json_decode($doc['data'] ?: '[]', true) ?: [];
    foreach ($fields as $f) {
        if (!in_array($f['ftype'], ['text','textarea'], true)) continue;
        $v = $data[$f['fkey']] ?? '';
        if (!is_string($v)) continue;
        learn_record('FIELD', $doc['report_type_id'], 0, $f['fkey'], $v);                       // by report type
        if (!empty($doc['client_id'])) learn_record('CLIENT', $doc['report_type_id'], $doc['client_id'], $f['fkey'], $v);  // client wording
    }
    // remarks are learned per report type, split into sentences
    foreach (preg_split('/(?<=[.!?])\s+/', (string)($doc['remarks'] ?? '')) as $sentence)
        learn_record('REMARK', $doc['report_type_id'], 0, '', $sentence);
    // an adverse result teaches us a common NCR cause
    if (in_array($doc['result'] ?? '', ['REJECTED','ACCEPTED_COND','HOLD'], true)) {
        foreach ($fields as $f) {
            if (!in_array($f['ftype'], ['text','textarea'], true)) continue;
            $v = $data[$f['fkey']] ?? ''; if (!is_string($v) || trim($v) === '') continue;
            $lv = strtolower($v);
            foreach (['not ok','reject','deviat','defect','crack','dent','leak','missing','expired','out of tolerance','incomplete'] as $w)
                if (strpos($lv, $w) !== false) { learn_record('NCR', $doc['report_type_id'], 0, $f['fkey'], $v); break; }
        }
    }
    idems_log('report_doc', $doc['id'], 'LEARNED', ['irn'=>$doc['irn']]);
}
// Ranked suggestions for one field (client-specific wording first, then type-wide).
function learn_suggestions($typeId, $clientId, $fieldKey, $limit = 6) {
    $out = []; $seen = [];
    $add = function($rows) use (&$out, &$seen, $limit) {
        foreach ($rows as $r) { $k = learn_norm($r['text_value']); if (isset($seen[$k]) || count($out) >= $limit) continue; $seen[$k] = 1; $out[] = $r; }
    };
    if ($clientId) $add(ops_all("SELECT * FROM learned_suggestions WHERE muted=0 AND scope='CLIENT' AND report_type_id=? AND client_id=? AND field_key=? ORDER BY uses DESC, last_seen DESC", [(int)$typeId, (int)$clientId, $fieldKey]));
    $add(ops_all("SELECT * FROM learned_suggestions WHERE muted=0 AND scope='FIELD' AND report_type_id=? AND field_key=? ORDER BY uses DESC, last_seen DESC", [(int)$typeId, $fieldKey]));
    return $out;
}
// Learned remark sentences for a report type (used by the smart-remarks screen).
function learn_remarks($typeId, $limit = 8) {
    return ops_all("SELECT * FROM learned_suggestions WHERE muted=0 AND scope='REMARK' AND report_type_id=? ORDER BY uses DESC, last_seen DESC", [(int)$typeId]);
}
// ---- Handler: learning insights (what the system has picked up) ----
function ops_idems_learning($method) {
    ops_require(is_master() || can('idems.type.manage') || can('mod.idems.view'), 'You cannot view the learning insights.');
    $pdo = db();
    if ($method === 'POST') {
        ops_require(is_master() || can('idems.type.manage'), 'You cannot change the learned library.');
        $do = $_POST['_do'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if ($do === 'mute')      { $pdo->prepare("UPDATE learned_suggestions SET muted=1 WHERE id=?")->execute([$id]); flash('Suggestion muted — it will no longer be offered.'); }
        elseif ($do === 'unmute'){ $pdo->prepare("UPDATE learned_suggestions SET muted=0 WHERE id=?")->execute([$id]); flash('Suggestion restored.'); }
        elseif ($do === 'promote') {
            $s = ops_one("SELECT * FROM learned_suggestions WHERE id=?", [$id]);
            if ($s) { $pdo->prepare("INSERT INTO tech_phrases (category,shorthand,phrase,active,is_system,sort_order,created_by,created_at) VALUES ('OBSERVATION',?,?,1,0,?,?,?)")
                ->execute([substr(learn_norm($s['text_value']), 0, 40), $s['text_value'], (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM tech_phrases"), user_name(current_user()), date('c')]);
                flash('Added to the standard phrase library.'); }
        } elseif ($do === 'purge') { $pdo->prepare("DELETE FROM learned_suggestions WHERE id=?")->execute([$id]); flash('Learned entry removed.'); }
        redirect('/learning');
    }
    $scope = $_GET['scope'] ?? '';
    $where = "1=1"; $args = [];
    if ($scope) { $where .= " AND scope=?"; $args[] = $scope; }
    $rows = ops_all("SELECT l.*, rt.code type_code, rt.name type_name, bp.display_name client_name
        FROM learned_suggestions l LEFT JOIN report_types rt ON rt.id=l.report_type_id LEFT JOIN business_partners bp ON bp.id=l.client_id
        WHERE $where ORDER BY l.uses DESC, l.last_seen DESC", $args);
    $stats = [
        'total'  => (int)ops_val("SELECT COUNT(*) FROM learned_suggestions"),
        'uses'   => (int)ops_val("SELECT COALESCE(SUM(uses),0) FROM learned_suggestions"),
        'ncr'    => (int)ops_val("SELECT COUNT(*) FROM learned_suggestions WHERE scope='NCR'"),
        'muted'  => (int)ops_val("SELECT COUNT(*) FROM learned_suggestions WHERE muted=1"),
        'reports'=> (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE action='LEARNED'"),
    ];
    $topPhrases = ops_all("SELECT category, shorthand, phrase, usage_count FROM tech_phrases WHERE usage_count>0 ORDER BY usage_count DESC");
    view('ops/idems/learning', ['rows'=>array_slice($rows, 0, 300), 'total'=>count($rows), 'scope'=>$scope,
        'stats'=>$stats, 'topPhrases'=>array_slice($topPhrases, 0, 10)]);
    return true;
}

// ---- Actions that a compliance reviewer should always look at ----
// Shipped default; the company can change the list in Settings → Reporting controls.
const AUDIT_HIGH_RISK = ['TIMESTAMP_EDIT','DELETE','EVIDENCE_DELETE','REJECT','SENDBACK','SIGNATURE_SET','LOGIN_FAILED','AI_REVIEW','DELEGATE'];
function audit_high_risk() {
    $s = (string)setting_get('audit_high_risk', '');
    if ($s === '') return AUDIT_HIGH_RISK;
    $v = array_values(array_intersect(AUDIT_ACTIONS_ALL, array_map('trim', explode(',', $s))));
    return $v ?: AUDIT_HIGH_RISK;
}
// Plain-English labels for the log. This map is also the master list of actions.
const AUDIT_ACTION_LABELS = [
    'CREATE'=>'Report created','EDIT'=>'Edited','IRN_GEN'=>'IRN generated','SUBMIT'=>'Submitted','APPROVE'=>'Approved',
    'REJECT'=>'Rejected','SENDBACK'=>'Sent back','DELEGATE'=>'Approval delegated','FINALIZE'=>'Finalized / issued',
    'DELETE'=>'Deleted (soft)','EVIDENCE'=>'Evidence added','EVIDENCE_CAPTION'=>'Evidence caption','EVIDENCE_DELETE'=>'Evidence removed',
    'PDF'=>'PDF generated','DOCX'=>'Client format generated','CERT_PDF'=>'Endorsement certificate','SOURCE_DOC'=>'Source document added',
    'AI_REVIEW'=>'AI review run','SMART_REMARKS'=>'Suggested remarks applied','TIMESTAMP_EDIT'=>'Date/timestamp changed',
    'SIGNATURE_SET'=>'Signature changed','ENDORSE'=>'Document endorsed','RELEASE_NOTE_DRAFT'=>'Release Note drafted',
    'RELEASE_NOTE_CREATED'=>'Release Note created from report','LOGIN'=>'Login','LOGOUT'=>'Logout','LOGIN_FAILED'=>'Failed login',
    'CSRF_REJECTED'=>'Save refused — not sent from this site',
    'PASSWORD_CHANGED'=>'Password changed','TWOFA_ON'=>'Two-step sign-in switched on',
    'TWOFA_OFF'=>'Two-step sign-in switched off','TWOFA_RESET'=>'Two-step sign-in reset by an administrator',
    'ACCOUNT_UNLOCKED'=>'Locked account released','PERSON_EXPORT'=>'Personal data exported',
    'PERSON_ERASED'=>'Personal data erased','INCIDENT'=>'Security incident recorded',
    'CONSENT'=>'Consent recorded','CONSENT_WITHDRAWN'=>'Consent withdrawn',
    'UPLOAD_REFUSED'=>'Attachment refused','USER_DEACTIVATED'=>'Person deactivated',
    'USER_REACTIVATED'=>'Person reactivated','USER_REMOVED'=>'Sign-in removed (work records kept)',
];
const AUDIT_ACTIONS_ALL = [
    'CREATE','EDIT','IRN_GEN','SUBMIT','APPROVE','REJECT','SENDBACK','DELEGATE','FINALIZE','DELETE','EVIDENCE',
    'EVIDENCE_CAPTION','EVIDENCE_DELETE','PDF','DOCX','CERT_PDF','SOURCE_DOC','AI_REVIEW','SMART_REMARKS',
    'TIMESTAMP_EDIT','SIGNATURE_SET','ENDORSE','RELEASE_NOTE_DRAFT','RELEASE_NOTE_CREATED','LOGIN','LOGOUT','LOGIN_FAILED',
    'CSRF_REJECTED','PASSWORD_CHANGED','TWOFA_ON','TWOFA_OFF','TWOFA_RESET','ACCOUNT_UNLOCKED',
    'PERSON_EXPORT','PERSON_ERASED','INCIDENT','CONSENT','CONSENT_WITHDRAWN','UPLOAD_REFUSED',
    'USER_DEACTIVATED','USER_REACTIVATED','USER_REMOVED',
];
function audit_action_label($a) { return AUDIT_ACTION_LABELS[$a] ?? $a; }
// Compliance health checks across the whole system (super-admin view).
function idems_compliance_checks() {
    $out = [];
    $n = fn($sql, $a = []) => (int)ops_val($sql, $a);
    $stuck = $n("SELECT COUNT(*) FROM report_docs WHERE deleted=0 AND status='UNDER_REVIEW' AND submitted_at<>'' AND submitted_at < ?", [date('c', strtotime('-7 days'))]);
    if ($stuck) $out[] = ['sev'=>'medium', 'text'=>$stuck . ' report(s) have been awaiting approval for more than 7 days.', 'link'=>'/documents?status=UNDER_REVIEW'];
    $noAppr = $n("SELECT COUNT(*) FROM inspectors i WHERE i.status='ACTIVE' AND i.reports_to_id IS NULL AND NOT EXISTS (SELECT 1 FROM idems_approver_map m WHERE m.inspector_id=i.id AND m.approver_user_id IS NOT NULL)");
    if ($noAppr) $out[] = ['sev'=>'high', 'text'=>$noAppr . ' active inspector(s) have no approver assigned — their reports cannot be submitted.', 'link'=>'/approver-map'];
    $tsEdits = $n("SELECT COUNT(*) FROM idems_audit WHERE action='TIMESTAMP_EDIT' AND created_at >= ?", [date('c', strtotime('-30 days'))]);
    if ($tsEdits) $out[] = ['sev'=>'high', 'text'=>$tsEdits . ' date/timestamp change(s) in the last 30 days — review the reasons recorded.', 'link'=>'/audit-log?action=TIMESTAMP_EDIT'];
    $noSig = $n("SELECT COUNT(*) FROM users WHERE is_active=1 AND (signature IS NULL OR signature='') AND role NOT IN ('INSPECTOR')");
    if ($noSig) $out[] = ['sev'=>'low', 'text'=>$noSig . ' active user(s) have no signature on file — their approvals will print without one.', 'link'=>'/users'];
    $fails = $n("SELECT COUNT(*) FROM idems_audit WHERE action='LOGIN_FAILED' AND created_at >= ?", [date('c', strtotime('-7 days'))]);
    if ($fails >= 5) $out[] = ['sev'=>'medium', 'text'=>$fails . ' failed login attempt(s) in the last 7 days.', 'link'=>'/audit-log?action=LOGIN_FAILED'];
    $del = $n("SELECT COUNT(*) FROM report_docs WHERE deleted=1");
    if ($del) $out[] = ['sev'=>'low', 'text'=>$del . ' report(s) are soft-deleted (retained for audit; never removed).', 'link'=>'/audit-log?action=DELETE'];
    return $out;
}
function ops_idems_audit($method) {
    ops_require(is_master() || can('idems.audit.view'), 'You cannot view the audit log.');
    // ---- filters (Part 23) ----
    $q       = trim($_GET['q'] ?? '');
    $act     = $_GET['action'] ?? '';
    $user    = trim($_GET['user'] ?? '');
    $office  = $_GET['office'] ?? '';
    $from    = trim($_GET['from'] ?? '');
    $to      = trim($_GET['to'] ?? '');
    $risk    = !empty($_GET['risk']);
    $where = "1=1"; $args = [];
    // The action is searchable too, so typing "CSRF" or "LOGIN_FAILED" finds
    // those entries without having to know which dropdown they live in.
    if ($q)      { $where .= " AND (irn LIKE ? OR username LIKE ? OR new_value LIKE ? OR reason LIKE ? OR action LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
    if ($act)    { $where .= " AND action=?"; $args[] = $act; }
    if ($user)   { $where .= " AND username LIKE ?"; $args[] = "%$user%"; }
    if ($office !== '') { $where .= " AND office_id=?"; $args[] = (int)$office; }
    if ($from)   { $where .= " AND created_at >= ?"; $args[] = $from; }
    if ($to)     { $where .= " AND created_at <= ?"; $args[] = $to . 'T23:59:59'; }
    if ($risk)   { $where .= " AND action IN ('" . implode("','", audit_high_risk()) . "')"; }
    $rows = ops_all("SELECT * FROM idems_audit WHERE $where ORDER BY id DESC", $args);
    // CSV export of exactly what is on screen
    if (function_exists('wants_csv') && wants_csv()) {
        $csv = [['When','Entity','IRN','Action','Field','Old value','New value','Reason','User','Role','Office','IP']];
        foreach ($rows as $r) $csv[] = [$r['created_at'], $r['entity'], $r['irn'], $r['action'], $r['field'], $r['old_value'], $r['new_value'], $r['reason'], $r['username'], $r['role'], $r['office_id'], $r['ip']];
        csv_download('audit-log-' . date('Ymd-His') . '.csv', $csv);
        return true;
    }
    // ---- summary for the dashboard ----
    $since30 = date('c', strtotime('-30 days'));
    $stats = [
        'total'    => (int)ops_val("SELECT COUNT(*) FROM idems_audit"),
        'today'    => (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE created_at >= ?", [date('Y-m-d')]),
        'risk30'   => (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE created_at >= ? AND action IN ('" . implode("','", audit_high_risk()) . "')", [$since30]),
        'users30'  => (int)ops_val("SELECT COUNT(DISTINCT username) FROM idems_audit WHERE created_at >= ?", [$since30]),
    ];
    $byAction = ops_all("SELECT action, COUNT(*) n FROM idems_audit WHERE created_at >= ? GROUP BY action ORDER BY n DESC", [$since30]);
    $byUser   = ops_all("SELECT username, COUNT(*) n FROM idems_audit WHERE created_at >= ? GROUP BY username ORDER BY n DESC", [$since30]);
    $byOffice = ops_all("SELECT a.office_id, o.name office_name, COUNT(*) n FROM idems_audit a LEFT JOIN offices o ON o.id=a.office_id WHERE a.created_at >= ? GROUP BY a.office_id, o.name ORDER BY n DESC", [$since30]);
    view('ops/idems/audit', [
        'rows'=>array_slice($rows, 0, 500), 'total'=>count($rows),
        'q'=>$q, 'act'=>$act, 'user'=>$user, 'office'=>$office, 'from'=>$from, 'to'=>$to, 'risk'=>$risk,
        'actions'=>array_column(ops_all("SELECT DISTINCT action FROM idems_audit ORDER BY action"), 'action'),
        'offices'=>ops_all("SELECT id, name FROM offices ORDER BY name"),
        'stats'=>$stats, 'byAction'=>$byAction, 'byUser'=>array_slice($byUser, 0, 8), 'byOffice'=>$byOffice,
        'checks'=>idems_compliance_checks(),
    ]);
    return true;
}
