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
    ['MPGR','Monthly Progress Report','SUMMARY'],
    ['CSR','Client Summary Report','SUMMARY'],
    ['PCR','Project Closure Report','SUMMARY'],
];
const IDEMS_CATEGORIES = ['TPIA_REPORT'=>'TPIA report','ENDORSEMENT'=>'Manufacturer endorsement','ADMIN'=>'Timesheet / admin','SUMMARY'=>'Summary / periodic'];
// Report-instance lifecycle.
const IDEMS_STATUS = ['DRAFT'=>'Draft','SUBMITTED'=>'Submitted','UNDER_REVIEW'=>'Under review','APPROVED'=>'Approved','ISSUED'=>'Issued','REJECTED'=>'Returned','ARCHIVED'=>'Archived'];
const IDEMS_OPEN_STATES = ['DRAFT','SUBMITTED','UNDER_REVIEW','REJECTED'];
const IDEMS_RESULTS = ['ACCEPTED'=>'Accepted','ACCEPTED_COND'=>'Accepted with conditions','REJECTED'=>'Rejected','HOLD'=>'Hold','NA'=>'Not applicable'];
const IDEMS_RELEASE = ['RELEASED'=>'Released','RELEASED_COND'=>'Released with conditions','NOT_RELEASED'=>'Not released','PENDING'=>'Pending'];

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
    // Immutable compliance audit log (Parts 21/23/24). Never hard-deleted.
    $pdo->exec("CREATE TABLE IF NOT EXISTS idems_audit (
        id $pk, entity VARCHAR(30) DEFAULT 'report_doc', entity_id INT NULL, irn VARCHAR(120) DEFAULT '',
        action VARCHAR(40) DEFAULT '', field VARCHAR(60) DEFAULT '', old_value TEXT, new_value TEXT, reason VARCHAR(400) DEFAULT '',
        username VARCHAR(150) DEFAULT '', role VARCHAR(40) DEFAULT '', office_id INT NULL,
        ip VARCHAR(60) DEFAULT '', device VARCHAR(200) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
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
    // Evidence / attachments captured against a report's fields (photos, files, signatures).
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_files (
        id $pk, report_doc_id INT, field_key VARCHAR(60) DEFAULT '', kind VARCHAR(20) DEFAULT 'file',
        file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', data MEDIUMTEXT, gps VARCHAR(60) DEFAULT '',
        note VARCHAR(400) DEFAULT '', created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // ---- Phase 3: workflow & approvals ----
    // Per-inspector approver mapping (individual or common; temp cover during leave).
    $pdo->exec("CREATE TABLE IF NOT EXISTS idems_approver_map (
        id $pk, inspector_id INT, approver_user_id INT NULL, temp_user_id INT NULL,
        temp_from VARCHAR(20) DEFAULT '', temp_to VARCHAR(20) DEFAULT '', active INT DEFAULT 1, updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('idems_approver_map', 'inspector_id');
    // Configurable approval chain rules (matched by report type / office / client / SBU).
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
    ensure_column('users', 'signature', 'MEDIUMTEXT');          // base64 data-URL of the signature image
    ensure_column('inspectors', 'signature', 'MEDIUMTEXT');
    // ---- Phase 5: client-specific report templates (uploaded .docx, token-mapped) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_templates (
        id $pk, name VARCHAR(150) DEFAULT '', report_type_id INT NULL, client_id INT NULL, office_id INT NULL,
        file_name VARCHAR(200) DEFAULT '', file_data MEDIUMTEXT,
        document_number VARCHAR(80) DEFAULT '', format_number VARCHAR(80) DEFAULT '', doc_revision VARCHAR(40) DEFAULT '', issue_date VARCHAR(20) DEFAULT '',
        active INT DEFAULT 1, is_default INT DEFAULT 0, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
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
        data MEDIUMTEXT, note VARCHAR(400) DEFAULT '', created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
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
}
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

// ---- Phase 2: field types for the no-code builder --------------------------
const IDEMS_FIELD_TYPES = [
    'text'=>'Short text', 'textarea'=>'Paragraph', 'number'=>'Number', 'date'=>'Date', 'time'=>'Time',
    'select'=>'Dropdown (single)', 'multiselect'=>'Dropdown (multiple)', 'checkbox'=>'Yes / no', 'radio'=>'Choice buttons',
    'calc'=>'Calculated', 'heading'=>'Section heading', 'note'=>'Info note',
    'table'=>'Repeatable table', 'photo'=>'Photo', 'file'=>'Attachment', 'gps'=>'GPS location',
    'signature'=>'Signature', 'qr'=>'QR / barcode value',
];
const IDEMS_COND_OPS = ['' => '(always show)', 'eq'=>'equals', 'ne'=>'not equals', 'in'=>'is one of', 'nonempty'=>'is filled', 'empty'=>'is empty'];
function idems_sections($typeId) { return ops_all("SELECT * FROM report_sections WHERE report_type_id=? ORDER BY sort_order, id", [(int)$typeId]); }
function idems_fields($typeId, $sectionId = null) {
    if ($sectionId === null) return ops_all("SELECT * FROM report_fields WHERE report_type_id=? ORDER BY sort_order, id", [(int)$typeId]);
    return ops_all("SELECT * FROM report_fields WHERE report_type_id=? AND section_id=? ORDER BY sort_order, id", [(int)$typeId, (int)$sectionId]);
}
function idems_has_schema($typeId) { return (int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=?", [(int)$typeId]) > 0; }
// Parse a field's options ("A|B|C" or lookup:key) into [value=>label].
function idems_field_options($f) {
    $o = trim((string)($f['options'] ?? ''));
    if ($o === '') return [];
    if (strpos($o, 'lookup:') === 0) return lk_options_or(substr($o, 7), []);
    $out = [];
    foreach (preg_split('/\r?\n|\|/', $o) as $line) { $line = trim($line); if ($line === '') continue; $parts = explode('=', $line, 2); $out[trim($parts[0])] = trim($parts[count($parts)>1?1:0]); }
    return $out;
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
    db()->prepare("INSERT INTO idems_audit (entity,entity_id,irn,action,field,old_value,new_value,reason,username,role,office_id,ip,device,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
        $entity, $entityId ?: null, $opts['irn'] ?? '', $action, $opts['field'] ?? '',
        isset($opts['old']) ? (is_scalar($opts['old']) ? (string)$opts['old'] : json_encode($opts['old'])) : '',
        isset($opts['new']) ? (is_scalar($opts['new']) ? (string)$opts['new'] : json_encode($opts['new'])) : '',
        $opts['reason'] ?? '', $u ? user_name($u) : 'system', $u['role'] ?? '', $u['home_office_id'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200), date('c')]);
}

// ---------------------------------------------------------------------------
//  IRN engine (Part 19) — configurable, no-code, zero-duplicate.
//  Format is a token template, default: {COMPANY}/{BRANCH}/{YEAR}/{CLIENT}/{TYPE}/{SERIAL}
//  The running serial is scoped by everything that precedes it, so each
//  company/branch/year/client/type combination keeps its own sequence.
// ---------------------------------------------------------------------------
function idems_irn_format() { return setting_get('idems_irn_format', '') ?: '{COMPANY}/{BRANCH}/{YEAR}/{CLIENT}/{TYPE}/{SERIAL}'; }
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
    $fmt = idems_irn_format();
    $s = strtr(str_replace('{SERIAL}', '', $fmt), $tok);
    return idems_collapse($s);
}
// Generate the next IRN for a doc. Atomically bumps the scope counter; the unique
// index on report_docs.irn is the final guarantee against duplicates.
function idems_generate_irn($doc, $maxTries = 5) {
    $tok = idems_tokens_for($doc);
    $fmt = idems_irn_format();
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

// ---------------------------------------------------------------------------
//  Handlers
// ---------------------------------------------------------------------------
// Document Register + a single report instance (create / view / edit / submit / finalize).
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
        view('ops/idems/doc_form', ['doc'=>$doc, 'pre'=>$pre, 'types'=>idems_types(),
            'clients'=>ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_client=1 ORDER BY nm"),
            'vendors'=>ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_vendor=1 ORDER BY nm"),
            'inspectors'=>ops_all("SELECT id, name FROM inspectors WHERE status='ACTIVE' ORDER BY name"),
            'approvers'=>ops_all("SELECT id, first_name, last_name, username, role FROM users WHERE is_active=1 ORDER BY first_name, last_name"),
            'offices'=>ops_all("SELECT id, name FROM offices ORDER BY is_ahmedabad DESC, name"),
            'calls'=>ops_all("SELECT c.id, c.call_code, COALESCE(bp.display_name,bp.legal_name) client_nm FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id ORDER BY c.id DESC"),
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
        view('ops/idems/doc_detail', ['doc'=>$doc, 'approver'=>$approver, 'audit'=>$audit,
            'sections'=>$sections, 'fields'=>$fields, 'data'=>$data, 'files'=>idems_doc_files($doc['id']), 'hasSchema'=>!empty($fields),
            'approvals'=>$approvals, 'curStep'=>$curStep, 'canAct'=>idems_can_act_step($curStep),
            'delegateUsers'=>($curStep && idems_can_act_step($curStep)) ? ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name") : []]);
        return true;
    }
    if ($route === 'document-submit' && $method === 'POST') {
        $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
        if (!$doc) { http_response_code(404); view('notfound'); return true; }
        ops_require(idems_can_edit_doc($doc), 'This report can no longer be changed.');
        // Build the approval chain. Every inspector must have at least one approver.
        $n = idems_build_approval_chain($doc);
        if ($n === 0) {
            $pdo->prepare("DELETE FROM report_approvals WHERE report_doc_id=?")->execute([$doc['id']]);
            flash('No approver is assigned for this report. Set the inspector’s approver under Inspection Reports → Approver mapping (or add an approval rule) before submitting.', 'error');
            redirect('/document?id=' . $doc['id']);
        }
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
        $issue = $doc['issue_date'] ?: date('Y-m-d');
        $pdo->prepare("UPDATE report_docs SET finalized=1, status='ISSUED', finalized_at=?, finalized_by=?, issue_date=?, updated_at=? WHERE id=?")
            ->execute([date('c'), user_name(current_user()), $issue, date('c'), $doc['id']]);
        idems_snapshot_signatures($doc);   // freeze inspector + approver signatures onto the report
        // an issued report is the best source of "how we actually word things"
        if (function_exists('learn_from_report')) { try { learn_from_report(ops_one("SELECT * FROM report_docs WHERE id=?", [$doc['id']])); } catch (Throwable $e) {} }
        idems_log('report_doc', $doc['id'], 'FINALIZE', ['irn'=>$doc['irn'], 'old'=>$doc['status'], 'new'=>'ISSUED']);
        flash('Report ' . $doc['irn'] . ' finalized & issued. It is now locked (immutable).');
        redirect('/document?id=' . $doc['id']);
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
        if ($do === 'del') {
            $t = ops_one("SELECT * FROM report_types WHERE id=?", [(int)($_POST['id'] ?? 0)]);
            if ($t && !$t['is_system']) { $pdo->prepare("DELETE FROM report_types WHERE id=?")->execute([$t['id']]); flash('Report type removed.'); }
            elseif ($t) { $pdo->prepare("UPDATE report_types SET active=0 WHERE id=?")->execute([$t['id']]); flash('Built-in type deactivated (kept for history).'); }
            redirect('/report-types');
        }
        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? '')); $name = trim($_POST['name'] ?? '');
        $cat = isset(IDEMS_CATEGORIES[$_POST['category'] ?? '']) ? $_POST['category'] : 'TPIA_REPORT';
        $active = !empty($_POST['active']) ? 1 : 0;
        if ($code === '' || $name === '') { flash('Code and name are required.', 'error'); redirect('/report-types'); }
        if ($id) $pdo->prepare("UPDATE report_types SET code=?, name=?, category=?, active=? WHERE id=?")->execute([$code, $name, $cat, $active, $id]);
        else $pdo->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,created_by,created_at) VALUES (?,?,?,?,0,?,?,?)")
            ->execute([$code, $name, $cat, $active, (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_types"), user_name(current_user()), date('c')]);
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
        'tokens'=>idems_available_tokens(), 'sample'=>$sample]);
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
        if ($do === 'section_save') {
            $sid = (int)($_POST['section_id'] ?? 0); $title = trim($_POST['title'] ?? '');
            if ($title === '') { flash('Section title required.', 'error'); redirect('/report-builder?type=' . $typeId); }
            if ($sid) $pdo->prepare("UPDATE report_sections SET title=?, help=? WHERE id=? AND report_type_id=?")->execute([$title, trim($_POST['help'] ?? ''), $sid, $typeId]);
            else $pdo->prepare("INSERT INTO report_sections (report_type_id,title,help,sort_order) VALUES (?,?,?,?)")
                ->execute([$typeId, $title, trim($_POST['help'] ?? ''), (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+10 FROM report_sections WHERE report_type_id=?", [$typeId])]);
            flash('Section saved.'); redirect('/report-builder?type=' . $typeId);
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
function ops_idems_fill($route, $method) {
    $pdo = db();
    $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
    if (!$doc) { http_response_code(404); view('notfound'); return true; }
    ops_require(idems_can_edit_doc($doc), 'This report is finalized and can no longer be edited.');
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
    view('ops/idems/fill', ['doc'=>$doc, 'sections'=>idems_sections($doc['report_type_id']), 'fields'=>$fields, 'data'=>$data,
        'files'=>idems_doc_files($doc['id']), 'sugg'=>$sugg, 'auto'=>$auto]);
    return true;
}
function idems_table_cols($f) {
    $raw = trim((string)($f['table_cols'] ?? ''));
    $out = [];
    foreach (preg_split('/\r?\n|\|/', $raw) as $c) { $c = trim($c); if ($c === '') continue; $out[idems_clean_key($c)] = $c; }
    if (!$out) $out = ['col1'=>'Column 1'];
    return $out;
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
            [$bytes, $mime, ] = idems_compress_image($bytes, $mime);
            $sha = sha1($bytes);
            // duplicate guard — same content already on this report
            if (ops_val("SELECT COUNT(*) FROM report_files WHERE report_doc_id=? AND sha1=?", [$doc['id'], $sha])) { $dupes++; continue; }
            $b64 = 'data:' . $mime . ';base64,' . base64_encode($bytes);
            $gps = trim($_POST['gps'][$k] ?? '');
            $pdo->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,gps,sha1,taken_at,bytes,orig_bytes,created_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$doc['id'], $k, $f['ftype'], substr($names[$i], 0, 255), $mime, $b64, $gps, $sha,
                    $taken ?: date('c'), strlen($bytes), $origLen, user_name(current_user()), date('c')]);
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
    header('Content-Type: ' . ($f['mime'] ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $f['file_name'] ?: 'file') . '"');
    echo $data; return true;
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
    if ($rules) {
        foreach ($rules as $r) {
            $uid = idems_resolve_approver($r['approver_kind'], $r['approver_user_id'], $doc);
            $steps[] = ['level'=>(int)$r['level'], 'kind'=>$r['approver_kind'], 'role'=>$r['approver_role'] ?? '', 'user'=>$uid, 'sla'=>(int)$r['sla_hours']];
        }
    } else {
        // default: single-level to the inspector's mapped approver (or reporting manager)
        $uid = idems_inspector_approver($doc['inspector_id'] ?? 0, $doc['inspection_date'] ?? null);
        $steps[] = ['level'=>1, 'kind'=>'INSPECTOR_MAP', 'role'=>'', 'user'=>$uid, 'sla'=>24];
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
function inspector_signature($inspectorId) { return $inspectorId ? (string)ops_val("SELECT signature FROM inspectors WHERE id=?", [(int)$inspectorId]) : ''; }

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
            idems_log('user', $u['id'], 'SIGNATURE_SET', ['field'=>'own signature']);
            flash('Your signature has been saved. It will be added automatically to reports you approve.');
        } else flash('No signature captured — draw in the box or upload an image.', 'error');
        redirect('/my-signature');
    }
    view('ops/idems/my_signature', ['sig'=>$u['signature'] ?? '']);
    return true;
}

// ---- Report PDF (letterhead + body + automatic signature block + timestamps) ----
function report_pdf_build($doc, $sections, $fields, $data, $files, $lh, $sigs) {
    $p = new SimplePDF(); $ml = $p->ml; $right = $p->right();
    $band = [30, 64, 175];
    $p->rectFill(0, 0, $p->pageW(), 6, $band);
    $p->y = $p->mt; $top = $p->y; $nameX = $ml;
    if (!empty($lh['logo'])) { $ln = $p->addJpeg($lh['logo']); if ($ln) { [$iw,$ih]=$p->imgDim($ln); $lw=80; $lhh=$ih>0?min(44,$lw*$ih/max(1,$iw)):36; $p->drawImage($ln,$ml,$top,$lw,$lhh); $nameX=$ml+$lw+12; } }
    $p->text($nameX, ($lh['name'] ?? '') ?: app_name(), 15, true, $band);
    $ly = $top + 18;
    foreach (preg_split('/\r?\n/', (string)($lh['address'] ?? '')) as $al) { $al=trim($al); if($al==='')continue; $p->y=$ly; $p->text($nameX,$al,8.5,false,[90,90,90]); $ly+=10; }
    if (!empty($lh['contact'])) { $p->y=$ly; $p->text($nameX,$lh['contact'],8.5,false,[90,90,90]); $ly+=10; }
    // IRN + status on the right
    $p->y=$top; $p->text($ml, 'IRN: '.$doc['irn'], 9, true, [60,60,60], $right, 'R');
    $p->y=$top+12; $p->text($ml, ($doc['type_code'].' — '.($doc['title'] ?: '')), 8, false, [110,110,110], $right, 'R');
    if (empty($doc['finalized'])) { $p->y=$top+24; $p->text($ml, 'DRAFT — not yet issued', 8, true, [200,60,60], $right, 'R'); }
    $p->y = max($ly, $top + 50); $p->hr($band);
    $p->gap(6);
    // report title
    $p->line(strtoupper($doc['type_name'] ?? $doc['type_code'] ?? 'INSPECTION REPORT'), 13, true, 16, $band);
    // key references grid
    $kv = [
        'Client' => $doc['client_disp'] ?: ($doc['client_name'] ?? ''), 'Vendor / Mfr' => $doc['vendor_disp'] ?: ($doc['vendor_name'] ?? ''),
        'Project' => trim(($doc['project_code'] ?? '').' '.($doc['project_name'] ?? '')), 'PO' => $doc['po_ref'] ?? '',
        'Drawing' => trim(($doc['drawing_no'] ?? '').' '.($doc['drawing_rev']?'Rev '.$doc['drawing_rev']:'')), 'QAP rev' => $doc['qap_rev'] ?? '',
        'Standards' => $doc['standards'] ?? '', 'Location' => $doc['location'] ?? '',
        'Inspection date' => $doc['inspection_date'] ?? '', 'Issue date' => $doc['issue_date'] ?? '',
        'Result' => IDEMS_RESULTS[$doc['result']] ?? '', 'Release' => IDEMS_RELEASE[$doc['release_status']] ?? '',
    ];
    $colW = $p->contentW()/2;
    foreach (array_chunk(array_filter($kv, fn($v)=>trim((string)$v)!==''), 2, true) as $pair) {
        $p->needSpace(14); $yrow = $p->y; $i = 0;
        foreach ($pair as $k=>$v) { $x = $ml + $i*$colW; $p->y=$yrow; $p->text($x, $k.':', 8.5, true, [90,90,90]); $p->text($x+70, $p->wrap((string)$v,9,$colW-74)[0] ?? (string)$v, 9); $i++; }
        $p->y = $yrow + 13;
    }
    $p->gap(4);
    // body sections
    $bySec = []; foreach ($fields as $f) $bySec[(int)$f['section_id']][] = $f;
    $filesBy = []; foreach ($files as $fl) $filesBy[$fl['field_key']][] = $fl;
    $secList = $sections; $secList[] = ['id'=>0,'title'=>''];
    foreach ($secList as $s) {
        $fl = $bySec[(int)$s['id']] ?? []; if (!$fl) continue;
        $p->needSpace(20); $p->gap(4);
        if ($s['title']!=='') { $p->line($s['title'], 11, true, 14, $band); }
        foreach ($fl as $f) {
            if (in_array($f['ftype'],['heading','note'],true)) { $p->needSpace(12); $p->line($f['label'], 9.5, $f['ftype']==='heading', 12, [80,80,80]); continue; }
            $k=$f['fkey']; $v=$data[$k] ?? '';
            if ($f['ftype']==='table') {
                if (!is_array($v)||!$v) continue; $cols=idems_table_cols($f); $p->needSpace(16);
                $p->text($ml, $f['label'], 9, true, [70,70,70]); $p->gap(11);
                $cw = $p->contentW()/max(1,count($cols)); $hy=$p->y;
                $p->rectFill($ml,$hy,$p->contentW(),12,[235,238,245]); $ci=0;
                foreach ($cols as $cl){ $p->y=$hy+3; $p->text($ml+$ci*$cw+2,(string)$cl,8,true,[60,60,60]); $ci++; }
                $p->y=$hy+13;
                foreach ($v as $r){ $r=(array)$r; $p->needSpace(12); $ry=$p->y; $ci=0; foreach($cols as $ck=>$cl){ $p->text($ml+$ci*$cw+2,(string)($r[$ck]??''),8.5); $ci++; } $p->y=$ry+11; $p->lineAt($ml,$p->y,$right,$p->y,[235,235,235]); }
                $p->gap(3); continue;
            }
            if (in_array($f['ftype'],['photo','file','signature'],true)) {
                if (empty($filesBy[$k])) continue; $p->needSpace(14);
                $p->text($ml, $f['label'].':', 9, true, [70,70,70]); $p->gap(11);
                $imgs = array_filter($filesBy[$k], fn($x)=>strpos($x['mime'],'image/')===0);
                if ($imgs) { $x=$ml; $p->needSpace(60); $rowY=$p->y;
                    foreach (array_slice($imgs,0,4) as $im){ $jpg=idems_sig_jpeg($im['data']); if(!$jpg)continue; $nm=$p->addJpeg($jpg); if($nm){ if($x+80>$right){$x=$ml;$rowY+=64;} $p->drawImage($nm,$x,$rowY,72,54); $x+=80; } }
                    $p->y=$rowY+60;
                }
                foreach ($filesBy[$k] as $fl2) if (strpos($fl2['mime'],'image/')!==0) { $p->text($ml,'• '.$fl2['file_name'],8.5,false,[90,90,90]); $p->gap(10); }
                continue;
            }
            $label = $f['label'];
            if ($f['ftype']==='multiselect' && is_array($v)) { $o=idems_field_options($f); $v=implode(', ', array_map(fn($x)=>$o[$x]??$x,$v)); }
            elseif (in_array($f['ftype'],['select','radio'],true)) { $o=idems_field_options($f); $v=$o[$v]??$v; }
            elseif ($f['ftype']==='checkbox') $v=($v==='1'||$v===1)?'Yes':'No';
            if (is_array($v)) $v='';
            $p->needSpace(12);
            $wrapped = $p->wrap((string)$v, 9, $p->contentW()-90);
            $p->text($ml, $label.':', 8.5, true, [90,90,90]);
            $p->text($ml+88, $wrapped ? $wrapped[0] : '', 9);
            $p->gap(11);
            for ($i=1;$i<count($wrapped);$i++){ $p->needSpace(11); $p->text($ml+88,$wrapped[$i],9); $p->gap(11); }
        }
    }
    // remarks
    if (!empty($doc['remarks'])) { $p->gap(4); $p->needSpace(16); $p->line('Remarks', 10, true, 13, $band); foreach ($p->wrap($doc['remarks'],9,$p->contentW()) as $ln2){ $p->needSpace(11); $p->line($ln2,9,false,11); } }
    // ---- signature block ----
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
    // footer note
    if (!empty($lh['footer'])) { $p->needSpace(14); $p->hr([220,220,220]); $p->gap(3); $p->line($lh['footer'], 7.5, false, 10, [130,130,130]); }
    $p->needSpace(12); $p->line('System-generated by ' . app_name() . ' · IRN ' . $doc['irn'] . ' · ' . date('d M Y H:i') . ($doc['finalized'] ? ' · Issued/locked' : ' · DRAFT'), 7, false, 9, [150,150,150]);
    return $p->output();
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
    $lh = function_exists('quote_letterhead') ? quote_letterhead() : ['name'=>app_name()];
    $pdf = report_pdf_build($doc, idems_sections($doc['report_type_id']), idems_fields($doc['report_type_id']),
        json_decode($doc['data'] ?: '[]', true) ?: [], idems_doc_files($doc['id']), $lh, idems_report_signatures($doc));
    idems_log('report_doc', $doc['id'], 'PDF', ['irn'=>$doc['irn']]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/','_',$doc['irn']) . '.pdf"');
    echo $pdf; return true;
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
        'result'=>IDEMS_RESULTS[$doc['result'] ?? ''] ?? '', 'release'=>IDEMS_RELEASE[$doc['release_status'] ?? ''] ?? '', 'remarks'=>$doc['remarks'] ?? '',
        'company'=>idems_company_code(), 'branch'=>$doc['branch_code'] ?? '', 'today'=>date('d M Y'),
        'status'=>IDEMS_STATUS[$doc['status'] ?? ''] ?? ($doc['status'] ?? ''),
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
        if (in_array($f['ftype'], ['photo','file','signature'], true)) continue;
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
// Available token names for a report type (for the reference panel).
function idems_type_tokens($typeId) {
    $std = ['irn','report_type','title','client','vendor','project','po','drawing','qap_rev','standards','location','inspection_date','issue_date','inspector','approver','result','release','remarks','company','branch','today','doc_number','format_number','doc_revision'];
    $fieldTokens = []; $tableTokens = [];
    foreach (idems_fields($typeId) as $f) {
        if (in_array($f['ftype'], ['heading','note','photo','file','signature'], true)) continue;
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
    $tpl = idems_pick_template($doc);
    if (!$tpl) { flash('No client-format template is set for this report type/client. Add one under Report templates, or use the PDF.', 'error'); redirect('/document?id=' . $doc['id']); }
    $fields = idems_fields($doc['report_type_id']);
    $data = json_decode($doc['data'] ?: '[]', true) ?: [];
    [$map, $tables] = idems_doc_token_map($doc, $fields, $data, $tpl);
    [$bin, $err] = report_docx_fill(base64_decode($tpl['file_data']), $map, $tables);
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
        $ctx['product_category'] = lk_options_or('product_category', [])[$call['product_category']] ?? ($call['product_other'] ?: $call['product_category']);
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
        flash($made . ' field(s) created from the format. Review them in the form builder — you can reorder, mark fields mandatory or add conditions.');
        redirect('/report-builder?type=' . $typeId);
    }
    $plan = idems_template_plan($binary, $typeId);
    view('ops/idems/form_from_template', ['tpl'=>$tpl, 'type'=>$type, 'plan'=>$plan,
        'fieldTypes'=>IDEMS_FIELD_TYPES, 'sections'=>idems_sections($typeId),
        'zipOk'=>class_exists('ZipArchive')]);
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
        else { $vals2=$vals+['created_by'=>user_name(current_user()),'created_at'=>date('c')]; $cols=array_keys($vals2); $ph=implode(',',array_fill(0,count($cols),'?')); $pdo->prepare("INSERT INTO report_templates (".implode(',',$cols).") VALUES ($ph)")->execute(array_values($vals2)); }
        flash('Report template saved.'); redirect('/report-templates');
    }
    $edit = ($route === 'report-template-edit') ? ops_one("SELECT * FROM report_templates WHERE id=?", [(int)($_GET['id'] ?? 0)]) : null;
    $tokRefType = (int)($_GET['tokens'] ?? ($edit['report_type_id'] ?? 0));
    view('ops/idems/templates', [
        'rows'=>ops_all("SELECT t.*, rt.name type_name, rt.code type_code, bp.display_name client_name, o.name office_name FROM report_templates t LEFT JOIN report_types rt ON rt.id=t.report_type_id LEFT JOIN business_partners bp ON bp.id=t.client_id LEFT JOIN offices o ON o.id=t.office_id ORDER BY t.id DESC"),
        'edit'=>$edit, 'types'=>idems_types(false),
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
const ENDORSE_DECISION = ['ENDORSED'=>'Reviewed &amp; Endorsed','ENDORSED_COND'=>'Endorsed with comments','REJECTED'=>'Rejected'];
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
        header('Content-Type: ' . ($f['mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/','_',$f['file_name'] ?: 'file') . '"');
        echo $data; return true;
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
                'doc_type'=>isset(ENDORSE_DOC_TYPES[$b['doc_type'] ?? ''])?$b['doc_type']:'MTC', 'title'=>trim($b['title'] ?? ''),
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
        if ($to) ops_mail($to, 'Document endorsement required: ' . $e['endorsement_no'], "A manufacturer document awaits your review & endorsement.\n\n" . $e['endorsement_no'] . " — " . (ENDORSE_DOC_TYPES[$e['doc_type']] ?? $e['doc_type']) . "\n\nOpen it in the system to review the original and endorse or reject.\n\n" . app_name(), '', 'idems_endorse');
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
    $p->rectFill(0, 0, $p->pageW(), 6, $band); $p->y = $p->mt; $top = $p->y; $nameX = $ml;
    if (!empty($lh['logo'])) { $ln=$p->addJpeg($lh['logo']); if($ln){ [$iw,$ih]=$p->imgDim($ln); $lw=80; $lhh=$ih>0?min(44,$lw*$ih/max(1,$iw)):36; $p->drawImage($ln,$ml,$top,$lw,$lhh); $nameX=$ml+$lw+12; } }
    $p->text($nameX, ($lh['name'] ?? '') ?: app_name(), 15, true, $band);
    $p->y=$top; $p->text($ml, 'Endorsement No: ' . $e['endorsement_no'], 9, true, [60,60,60], $right, 'R');
    $p->y = $top + 34; $p->hr($band); $p->gap(6);
    $p->line('DOCUMENT REVIEW & ENDORSEMENT CERTIFICATE', 14, true, 18, $band);
    $dec = $e['decision']==='REJECTED' ? 'REJECTED' : (ENDORSE_DECISION[$e['decision']] ?? 'Reviewed & Endorsed');
    $p->line('Decision: ' . strip_tags($dec), 11, true, 15, $e['decision']==='REJECTED'?[190,50,50]:[21,128,61]);
    $p->gap(4);
    $kv = ['Document type'=>ENDORSE_DOC_TYPES[$e['doc_type']] ?? $e['doc_type'], 'Title / description'=>$e['title'] ?: $e['item_desc'],
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
        $cat = isset(PHRASE_CATEGORIES[$_POST['category'] ?? '']) ? $_POST['category'] : 'OBSERVATION';
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
        if (in_array($f['ftype'], ['photo','file','signature','heading','note'], true)) continue;
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
        if (!isset(IDEMS_RESULTS[$res])) $res = $p['result'];
        if (!isset(IDEMS_RELEASE[$rel])) $rel = $p['release'];
        db()->prepare("UPDATE report_docs SET remarks=?, result=?, release_status=?, updated_at=? WHERE id=?")->execute([$text, $res, $rel, date('c'), $doc['id']]);
        idems_log('report_doc', $doc['id'], 'SMART_REMARKS', ['irn'=>$doc['irn'], 'field'=>'remarks']);
        flash('Suggested remarks applied. Review and edit as required.');
        redirect('/document?id=' . $doc['id']);
    }
    view('ops/idems/smart', ['doc'=>$doc, 'p'=>$p, 'text'=>idems_smart_remarks_text($p)]);
    return true;
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
    $rnType = ops_one("SELECT * FROM report_types WHERE code='RN' AND active=1");
    if (!$rnType) { flash('No active "RN — Release Note" report type found. Add one under Report types.', 'error'); redirect('/document?id=' . $src['id']); }
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
        'report_type_id'=>$rnType['id'], 'type_code'=>'RN', 'title'=>'Release Note — ' . ($src['title'] ?: $src['irn']),
        'client_id'=>$src['client_id'], 'vendor_id'=>$src['vendor_id'], 'call_id'=>$src['call_id'], 'job_id'=>$src['job_id'],
        'client_code'=>$src['client_code'], 'project_code'=>$src['project_code'], 'project_name'=>$src['project_name'],
        'office_id'=>$src['office_id'], 'sbu'=>$src['sbu'], 'po_ref'=>$src['po_ref'], 'drawing_no'=>$src['drawing_no'],
        'drawing_rev'=>$src['drawing_rev'], 'qap_rev'=>$src['qap_rev'], 'standards'=>$src['standards'], 'location'=>$src['location'],
        'product_category'=>$src['product_category'], 'material_grade'=>$src['material_grade'],
        'inspector_id'=>$src['inspector_id'], 'approver_user_id'=>$src['approver_user_id'],
        'inspection_date'=>$src['inspection_date'], 'result'=>$src['result'] ?: $p['result'], 'release_status'=>$rel,
        'remarks'=>$body,
    ];
    [$irn, $serial] = idems_generate_irn($fieldsNew);
    $tok = idems_tokens_for($fieldsNew);
    $rnData = json_encode(['source_irn'=>$src['irn'], 'source_report_id'=>(int)$src['id']]);
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
// Source docs attached to a report (stored in report_files with kind='src_<TYPE>').
function idems_source_docs($docId) {
    $rows = ops_all("SELECT id, kind, field_key, file_name, mime, note, created_at FROM report_files WHERE report_doc_id=? AND kind LIKE 'src_%' ORDER BY id", [(int)$docId]);
    foreach ($rows as &$r) { $r['doc_type'] = substr($r['kind'], 4); $r['doc_label'] = SOURCE_DOC_TYPES[$r['doc_type']] ?? $r['doc_type']; }
    return $rows;
}
// ---- Rule-based conflict checks (always available, no AI) ----
// Returns a list of ['severity'=>high|medium|low, 'kind'=>..., 'detail'=>...].
function idems_rule_checks($doc, $srcDocs) {
    $out = [];
    $have = [];
    foreach ($srcDocs as $s) $have[$s['doc_type']] = true;
    // 1. missing documents
    foreach (expected_source_docs() as $t) {
        if (empty($have[$t])) $out[] = ['severity'=>'medium', 'kind'=>'Missing document', 'detail'=>(SOURCE_DOC_TYPES[$t] ?? $t) . ' has not been uploaded against this report.'];
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
        if (trim($t) !== '') $parts[] = '--- ' . (SOURCE_DOC_TYPES[substr($r['kind'],4)] ?? $r['kind']) . ': ' . $r['file_name'] . " ---\n" . $t;
    }
    return implode("\n\n", $parts);
}
// ---- AI review (optional layer on top of the rule checks) ----
function idems_ai_review($doc, $fields, $data, $srcDocs) {
    if (!function_exists('ai_enabled') || !ai_enabled()) return [null, 'No AI provider is enabled — the rule-based checks above still apply.'];
    $bundle = idems_source_text_bundle($doc['id'], 6000);
    $body = [];
    foreach ($fields as $f) {
        if (in_array($f['ftype'], ['photo','file','signature','heading','note'], true)) continue;
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
        if ($do === 'upload') {
            ops_require(idems_can_edit_doc($doc), 'This report is finalized.');
            $type = isset(SOURCE_DOC_TYPES[$_POST['doc_type'] ?? '']) ? $_POST['doc_type'] : 'OTHER';
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
];
const AUDIT_ACTIONS_ALL = [
    'CREATE','EDIT','IRN_GEN','SUBMIT','APPROVE','REJECT','SENDBACK','DELEGATE','FINALIZE','DELETE','EVIDENCE',
    'EVIDENCE_CAPTION','EVIDENCE_DELETE','PDF','DOCX','CERT_PDF','SOURCE_DOC','AI_REVIEW','SMART_REMARKS',
    'TIMESTAMP_EDIT','SIGNATURE_SET','ENDORSE','RELEASE_NOTE_DRAFT','RELEASE_NOTE_CREATED','LOGIN','LOGOUT','LOGIN_FAILED',
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
    if ($q)      { $where .= " AND (irn LIKE ? OR username LIKE ? OR new_value LIKE ? OR reason LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%", "%$q%"); }
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
