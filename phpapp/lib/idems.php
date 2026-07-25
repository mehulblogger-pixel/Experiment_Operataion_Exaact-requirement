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
    idems_seed_report_types();
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
        view('ops/idems/doc_form', ['doc'=>$doc, 'types'=>idems_types(),
            'clients'=>ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_client=1 ORDER BY nm"),
            'vendors'=>ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_vendor=1 ORDER BY nm"),
            'inspectors'=>ops_all("SELECT id, name FROM inspectors WHERE status='ACTIVE' ORDER BY name"),
            'approvers'=>ops_all("SELECT id, first_name, last_name, username, role FROM users WHERE is_active=1 ORDER BY first_name, last_name"),
            'offices'=>ops_all("SELECT id, name FROM offices ORDER BY is_ahmedabad DESC, name"),
            'sbuOpts'=>lk_options_or('sbu', OPS_SBUS), 'prefixPreview'=>$doc ? null : null]);
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
        view('ops/idems/doc_detail', ['doc'=>$doc, 'approver'=>$approver, 'audit'=>$audit,
            'sections'=>$sections, 'fields'=>$fields, 'data'=>$data, 'files'=>idems_doc_files($doc['id']), 'hasSchema'=>!empty($fields)]);
        return true;
    }
    if ($route === 'document-submit' && $method === 'POST') {
        $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
        if (!$doc) { http_response_code(404); view('notfound'); return true; }
        ops_require(idems_can_edit_doc($doc), 'This report can no longer be changed.');
        $pdo->prepare("UPDATE report_docs SET status='SUBMITTED', submitted_at=?, updated_at=? WHERE id=?")->execute([date('c'), date('c'), $doc['id']]);
        idems_log('report_doc', $doc['id'], 'SUBMIT', ['irn'=>$doc['irn'], 'old'=>$doc['status'], 'new'=>'SUBMITTED']);
        flash('Report submitted for review.');
        redirect('/document?id=' . $doc['id']);
    }
    if ($route === 'document-finalize' && $method === 'POST') {
        $doc = ops_one("SELECT * FROM report_docs WHERE id=? AND deleted=0", [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
        if (!$doc) { http_response_code(404); view('notfound'); return true; }
        ops_require(is_master() || can('idems.finalize'), 'You are not permitted to finalize / issue reports.');
        if ($doc['finalized']) { flash('This report is already finalized.', 'warning'); redirect('/document?id=' . $doc['id']); }
        $pdo->prepare("UPDATE report_docs SET finalized=1, status='ISSUED', finalized_at=?, finalized_by=?, issue_date=?, approved_at=?, approved_by=?, updated_at=? WHERE id=?")
            ->execute([date('c'), user_name(current_user()), date('Y-m-d'), date('c'), user_name(current_user()), date('c'), $doc['id']]);
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
    view('ops/idems/fill', ['doc'=>$doc, 'sections'=>idems_sections($doc['report_type_id']), 'fields'=>$fields, 'data'=>$data, 'files'=>idems_doc_files($doc['id'])]);
    return true;
}
function idems_table_cols($f) {
    $raw = trim((string)($f['table_cols'] ?? ''));
    $out = [];
    foreach (preg_split('/\r?\n|\|/', $raw) as $c) { $c = trim($c); if ($c === '') continue; $out[idems_clean_key($c)] = $c; }
    if (!$out) $out = ['col1'=>'Column 1'];
    return $out;
}
function idems_handle_uploads($doc, $fields) {
    if (empty($_FILES['upl'])) return;
    $pdo = db();
    foreach ($fields as $f) {
        if (!in_array($f['ftype'], ['photo','file'], true)) continue;
        $k = $f['fkey'];
        $names = $_FILES['upl']['name'][$k] ?? null;
        if (!$names) continue;
        $tmp = $_FILES['upl']['tmp_name'][$k]; $types = $_FILES['upl']['type'][$k]; $errs = $_FILES['upl']['error'][$k];
        $names = (array)$names; $tmp = (array)$tmp; $types = (array)$types; $errs = (array)$errs;
        for ($i = 0; $i < count($names); $i++) {
            if (($errs[$i] ?? 1) !== 0 || !is_uploaded_file($tmp[$i])) continue;
            $bytes = @file_get_contents($tmp[$i]); if ($bytes === false || strlen($bytes) > 6*1024*1024) continue;   // 6 MB cap
            $b64 = 'data:' . ($types[$i] ?: 'application/octet-stream') . ';base64,' . base64_encode($bytes);
            $gps = trim($_POST['gps'][$k] ?? '');
            $pdo->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,gps,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$doc['id'], $k, $f['ftype'], substr($names[$i], 0, 255), $types[$i] ?: '', $b64, $gps, user_name(current_user()), date('c')]);
        }
    }
    idems_log('report_doc', $doc['id'], 'EVIDENCE', ['irn'=>$doc['irn']]);
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

// Compliance audit log viewer (basic; full super-admin dashboard is a later phase).
function ops_idems_audit($method) {
    ops_require(is_master() || can('idems.audit.view'), 'You cannot view the audit log.');
    $q = trim($_GET['q'] ?? ''); $act = $_GET['action'] ?? '';
    $where = "1=1"; $args = [];
    if ($q)   { $where .= " AND (irn LIKE ? OR username LIKE ?)"; array_push($args, "%$q%", "%$q%"); }
    if ($act) { $where .= " AND action=?"; $args[] = $act; }
    $rows = ops_all("SELECT * FROM idems_audit WHERE $where ORDER BY id DESC", $args);
    $actions = ops_all("SELECT DISTINCT action FROM idems_audit ORDER BY action");
    view('ops/idems/audit', ['rows'=>array_slice($rows, 0, 500), 'q'=>$q, 'act'=>$act, 'actions'=>array_column($actions, 'action')]);
    return true;
}
