<?php
// ============================================================================
//  CRM / Marketing & Sales module — Phase 0 foundations.
//  The pre-operations funnel: inquiry → quotation (+ revisions) → approval →
//  send → follow-up → acceptance → client/contract registration → hand-off to
//  Operations → line-item revenue tracking.
//
//  This file owns the CRM DATA MODEL, constants and small helpers. The screens
//  (list/form/detail, template engine, approvals, follow-ups) are built in the
//  later phases; the tables below are created now so those phases have a stable
//  foundation. Everything is idempotent and portable (SQLite dev / MySQL prod).
// ============================================================================

// ---- Configurable value lists (also mirrored into editable lookup masters) --

// Researched drop-down of why a quotation was lost. "OTHER" opens a free-text
// box on the quote form (handled in the UI phase). Kept short and B2B/TIC-flavoured.
const QUOTE_LOST_REASONS = [
    'PRICE_HIGH'        => 'Price too high / lost on price',
    'COMPETITOR'        => 'Awarded to a competitor',
    'BUDGET_HOLD'       => 'Client budget withdrawn / on hold',
    'PROJECT_CANCELLED' => 'Project cancelled or postponed',
    'NO_RESPONSE'       => 'No response / went cold',
    'SCOPE_MISMATCH'    => 'Scope / technical mismatch',
    'TIMELINE'          => 'Could not meet timeline / availability',
    'CREDIT_TERMS'      => 'Payment / credit terms not agreed',
    'ACCREDITATION'     => 'Accreditation / approval not held',
    'IN_HOUSE'          => 'Client handled it in-house',
    'DUPLICATE'         => 'Duplicate / erroneous inquiry',
    'OTHER'             => 'Other (specify)',
];

// CRM service / job type (§18, §25) — a CONFIGURABLE master, kept separate from
// the operational jobs.job_type so mandays logic is untouched. Multi-select in
// the quote; each can carry sub-types from the inspection-type / deputation lists.
const CRM_SERVICE_TYPES = [
    'INSPECTION'      => 'Inspection',
    'PROJECT_DEP'     => 'Project deputation',
    'SUPPLY_CHAIN'    => 'Supply-chain deputation',
    'SITE_SUP'        => 'Site supervision',
    'COMMISSIONING'   => 'Commissioning & installation',
    'SITE_QAQC'       => 'Site QA / QC',
    'TECH_AUDIT'      => 'Technical audit',
    'TYPE_TEST'       => 'Type test',
    'VENDOR_ASSESS'   => 'Vendor assessment',
    'VENDOR_AUDIT'    => 'Vendor audit',
    'DOC_REVIEW'      => 'Document review',
    'TENDER_REVIEW'   => 'Tender review',
    'EXPEDITING'      => 'Expediting',
    'OTHER'           => 'Other',
];
// Project-deputation sub-categories (§18, multiple selection).
const CRM_DEPUTATION_SUBTYPES = [
    'SITE_QAQC'     => 'Site QA / QC',
    'COMMISSIONING' => 'Commissioning',
    'OANDM'         => 'O & M',
    'ERECTION'      => 'Erection',
    'SUPERVISION'   => 'Supervision',
];

// Where the work happens vs the client's registered/contract address (§19, §20).
const QUOTE_LOCATION_TYPES = [
    'REGISTERED' => 'Client registered / contract address',
    'AGREED'     => 'Agreed location',
    'PANINDIA'   => 'Pan-India (multiple / as directed)',
];

// Billing unit for a quote/order line (§17).
const QUOTE_UNITS = [
    'DAY'       => 'Man-day',
    'MONTH'     => 'Man-month',
    'VISIT'     => 'Per visit',
    'AUDIT_DAY' => 'Audit day',
    'LOT'       => 'Per lot / lump sum',
    'DOC'       => 'Per document',
];
// Order type (§17): OPEN = ARC / call-off with no fixed PO; LINE = fixed line items.
const ORDER_TYPES = ['OPEN' => 'Open order (ARC / call-off)', 'LINE' => 'Line-item order'];

// Inquiry funnel.
const INQUIRY_SOURCES = ['EMAIL'=>'Email','PHONE'=>'Phone','REFERRAL'=>'Referral','WEBSITE'=>'Website','EXISTING'=>'Existing client','TENDER'=>'Tender / portal','OTHER'=>'Other'];
const INQUIRY_STATUS  = ['OPEN'=>'Open','QUOTED'=>'Quoted','DROPPED'=>'Dropped'];

// Quotation lifecycle (§14 open/pending/closed, plus lost/expired for analytics).
const QUOTE_STATUS = [
    'DRAFT'            => 'Draft',
    'PENDING_APPROVAL' => 'Pending approval',
    'APPROVED'         => 'Approved (ready to send)',
    'SENT'             => 'Sent — awaiting reply',
    'ACCEPTED'         => 'Accepted / closed-won',
    'LOST'             => 'Lost / regretted',
    'EXPIRED'          => 'Expired',
];
// Which statuses count as OPEN / PENDING / CLOSED for the §14 views.
const QUOTE_OPEN_STATES    = ['DRAFT','PENDING_APPROVAL','APPROVED','SENT'];
const QUOTE_PENDING_STATES = ['PENDING_APPROVAL','SENT'];
const QUOTE_CLOSED_STATES  = ['ACCEPTED'];

// Follow-up cadence (§11): 3 / 6 / 9 days, fortnight, month after the quote is sent.
const FOLLOWUP_KINDS = ['D3'=>'Day 3','D6'=>'Day 6','D9'=>'Day 9','FORTNIGHT'=>'Fortnight','MONTH'=>'Month'];
const FOLLOWUP_OFFSETS = ['D3'=>3,'D6'=>6,'D9'=>9,'FORTNIGHT'=>15,'MONTH'=>30];

// Template kinds (§5 quote doc, §11 follow-ups, §20 credential request).
const CRM_TEMPLATE_KINDS = [
    'QUOTE_DOC'       => 'Quotation document (.docx template)',
    'EMAIL_QUOTE'     => 'Email — quotation covering note',
    'EMAIL_FOLLOWUP'  => 'Email — follow-up reminder',
    'EMAIL_CREDENTIAL'=> 'Email — candidate credential request',
];

// Approval-rule scope: a rule may target an amount band and/or an SBU (§ owner:
// "by quote amount threshold aligned with respect to SBU … either or both").
const APPROVAL_MATCH = ['ANY'=>'Any SBU', 'SBU'=>'Specific SBU'];

// ---------------------------------------------------------------------------
//  Schema
// ---------------------------------------------------------------------------
function crm_ensure_schema() {
    $pdo = db(); $pk = pk_clause();
    $t = [
        // Customer inquiry captured from an email/phone (§1).
        "CREATE TABLE IF NOT EXISTS crm_inquiries (
            id $pk, inquiry_no VARCHAR(40), client_id INT NULL, client_name VARCHAR(200) DEFAULT '',
            contact_name VARCHAR(150) DEFAULT '', contact_email VARCHAR(200) DEFAULT '', contact_mobile VARCHAR(40) DEFAULT '',
            subject VARCHAR(255) DEFAULT '', service_requirement TEXT, sbu VARCHAR(20) DEFAULT '',
            source VARCHAR(20) DEFAULT 'EMAIL', received_date VARCHAR(20) DEFAULT '', assigned_to INT NULL,
            status VARCHAR(20) DEFAULT 'OPEN', notes TEXT, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",

        // Quotation header. Revisions are modelled with parent_id + rev (the newest
        // rev is is_current=1); the BOSS supersede pattern, reused for §23.
        "CREATE TABLE IF NOT EXISTS quotations (
            id $pk, quote_no VARCHAR(40), rev INT DEFAULT 0, parent_id INT NULL, is_current INT DEFAULT 1,
            inquiry_id INT NULL, client_id INT NULL, client_name VARCHAR(200) DEFAULT '',
            contact_name VARCHAR(150) DEFAULT '', contact_email VARCHAR(200) DEFAULT '', contact_mobile VARCHAR(40) DEFAULT '',
            sbu VARCHAR(20) DEFAULT '', office_id INT NULL, subject VARCHAR(255) DEFAULT '',
            site_location VARCHAR(255) DEFAULT '', location_type VARCHAR(20) DEFAULT 'REGISTERED',
            currency VARCHAR(8) DEFAULT 'INR', validity_days INT DEFAULT 30,
            payment_terms VARCHAR(255) DEFAULT '', advance_pct DECIMAL(6,2) DEFAULT 0,
            advance_required INT DEFAULT 0, report_vs_payment INT DEFAULT 0,
            subtotal DECIMAL(14,2) DEFAULT 0, gst_pct DECIMAL(6,2) DEFAULT 18, gst_amount DECIMAL(14,2) DEFAULT 0,
            total_amount DECIMAL(14,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'DRAFT', lost_reason VARCHAR(30) DEFAULT '', lost_reason_other VARCHAR(255) DEFAULT '',
            template_id INT NULL, sent_at VARCHAR(30) DEFAULT '', accepted_date VARCHAR(20) DEFAULT '',
            contract_id INT NULL, contract_number VARCHAR(60) DEFAULT '',
            owner_id INT NULL, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')",

        // Quote / order line items (§4 SBU per line, §16 revenue, §17 order lines).
        "CREATE TABLE IF NOT EXISTS quote_lines (
            id $pk, quote_id INT, line_no INT DEFAULT 0, sbu VARCHAR(20) DEFAULT '',
            service_type VARCHAR(30) DEFAULT '', subtypes VARCHAR(400) DEFAULT '', description VARCHAR(400) DEFAULT '',
            location VARCHAR(255) DEFAULT '', location_type VARCHAR(20) DEFAULT 'REGISTERED',
            order_type VARCHAR(10) DEFAULT 'LINE', qty DECIMAL(12,2) DEFAULT 0, unit VARCHAR(20) DEFAULT 'DAY',
            rate DECIMAL(14,2) DEFAULT 0, amount DECIMAL(14,2) DEFAULT 0,
            deliverables VARCHAR(500) DEFAULT '', notes VARCHAR(400) DEFAULT '')",

        // Full field-level history of each revision (§23).
        "CREATE TABLE IF NOT EXISTS quote_revisions (
            id $pk, quote_id INT, rev INT DEFAULT 0, changed_by VARCHAR(150) DEFAULT '',
            changed_at VARCHAR(30) DEFAULT '', summary TEXT, snapshot MEDIUMTEXT)",

        // Approval chain steps generated from the rules (§9).
        "CREATE TABLE IF NOT EXISTS quote_approvals (
            id $pk, quote_id INT, level INT DEFAULT 1, approver_role VARCHAR(40) DEFAULT '', approver_user_id INT NULL,
            status VARCHAR(20) DEFAULT 'PENDING', acted_by VARCHAR(150) DEFAULT '', acted_at VARCHAR(30) DEFAULT '',
            remarks VARCHAR(400) DEFAULT '')",

        // Configurable approval matrix (§ owner: amount threshold and/or SBU).
        "CREATE TABLE IF NOT EXISTS quote_approval_rules (
            id $pk, name VARCHAR(120) DEFAULT '', match_type VARCHAR(10) DEFAULT 'ANY', sbu VARCHAR(20) DEFAULT '',
            min_amount DECIMAL(14,2) DEFAULT 0, max_amount DECIMAL(14,2) DEFAULT 0,
            level INT DEFAULT 1, approver_role VARCHAR(40) DEFAULT '', approver_user_id INT NULL,
            active INT DEFAULT 1, created_at VARCHAR(30) DEFAULT '')",

        // Scheduled + sent follow-ups (§11).
        "CREATE TABLE IF NOT EXISTS quote_followups (
            id $pk, quote_id INT, kind VARCHAR(20) DEFAULT '', due_date VARCHAR(20) DEFAULT '',
            template_id INT NULL, status VARCHAR(20) DEFAULT 'PENDING', sent_at VARCHAR(30) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '')",

        // Quote-doc + email templates (§5, §11, §20). file_data holds a base64 .docx
        // for QUOTE_DOC; fields holds a JSON field map for the §6 field designer.
        "CREATE TABLE IF NOT EXISTS crm_templates (
            id $pk, kind VARCHAR(30) DEFAULT 'EMAIL_FOLLOWUP', name VARCHAR(150) DEFAULT '',
            subject VARCHAR(255) DEFAULT '', body MEDIUMTEXT, file_name VARCHAR(200) DEFAULT '', file_data MEDIUMTEXT,
            fields MEDIUMTEXT, document_number VARCHAR(80) DEFAULT '', format_number VARCHAR(80) DEFAULT '',
            doc_revision VARCHAR(40) DEFAULT '', issue_date VARCHAR(20) DEFAULT '',
            active INT DEFAULT 1, is_default INT DEFAULT 0, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",
    ];
    foreach ($t as $sql) $pdo->exec($sql);
}

function crm_migrate() {
    crm_ensure_schema();
    // Quote-template control fields added after P0 (safe on already-created tables).
    if (function_exists('ensure_column')) {
        ensure_column('crm_templates', 'document_number', "VARCHAR(80) DEFAULT ''");
        ensure_column('crm_templates', 'format_number', "VARCHAR(80) DEFAULT ''");
        ensure_column('crm_templates', 'doc_revision', "VARCHAR(40) DEFAULT ''");
        ensure_column('crm_templates', 'issue_date', "VARCHAR(20) DEFAULT ''");
    }
    // Editable master for lost reasons (§ owner: research a dropdown + "Other").
    if (function_exists('lk_ensure_type_map')) lk_ensure_type_map('quote_lost_reason', 'Quote lost reason', QUOTE_LOST_REASONS);
    // Editable master for CRM service / job types (§18, §25).
    if (function_exists('lk_ensure_type_map')) lk_ensure_type_map('crm_service_type', 'CRM service / job type', CRM_SERVICE_TYPES);
}

// ---- Small helpers ---------------------------------------------------------
function crm_next_inquiry_no() { return ops_next_code('crm_inquiries', 'inquiry_no', 'INQ'); }
// Quote number is per base document; revisions keep the same number and bump `rev`.
function crm_next_quote_no() { return ops_next_code('quotations', 'quote_no', 'Q'); }
// "Q-00042 Rev 01" style label.
function quote_label($q) { $n = $q['quote_no'] ?? ''; $r = (int)($q['rev'] ?? 0); return $r > 0 ? "$n Rev " . str_pad((string)$r, 2, '0', STR_PAD_LEFT) : $n; }
function crm_inquiry_get($id) { return ops_one("SELECT * FROM crm_inquiries WHERE id=?", [(int)$id]); }
function crm_quote_get($id) { return ops_one("SELECT * FROM quotations WHERE id=?", [(int)$id]); }
function crm_quote_lines($qid) { return ops_all("SELECT * FROM quote_lines WHERE quote_id=? ORDER BY line_no, id", [(int)$qid]); }
function crm_client_name($cid) { return $cid ? (ops_val("SELECT COALESCE(NULLIF(display_name,''), legal_name) FROM business_partners WHERE id=?", [(int)$cid]) ?: '') : ''; }

// Sum lines → subtotal, GST, grand total (kept on the header for fast lists).
function crm_quote_recalc($qid) {
    $q = crm_quote_get($qid); if (!$q) return;
    $sub = (float)ops_val("SELECT COALESCE(SUM(amount),0) FROM quote_lines WHERE quote_id=?", [(int)$qid]);
    $gst = round($sub * (float)$q['gst_pct'] / 100, 2);
    db()->prepare("UPDATE quotations SET subtotal=?, gst_amount=?, total_amount=?, updated_at=? WHERE id=?")
        ->execute([$sub, $gst, $sub + $gst, date('c'), (int)$qid]);
}
// Replace all line items from the posted arrays (parallel field arrays per row).
function crm_save_lines($qid, $b) {
    db()->prepare("DELETE FROM quote_lines WHERE quote_id=?")->execute([(int)$qid]);
    $desc = (array)($b['l_desc'] ?? []); $svc = (array)($b['l_service'] ?? []); $sbu = (array)($b['l_sbu'] ?? []);
    $loc = (array)($b['l_loc'] ?? []); $lt = (array)($b['l_loctype'] ?? []); $ot = (array)($b['l_order'] ?? []);
    $qty = (array)($b['l_qty'] ?? []); $unit = (array)($b['l_unit'] ?? []); $rate = (array)($b['l_rate'] ?? []);
    $sub = (array)($b['l_subtypes'] ?? []); $del = (array)($b['l_deliv'] ?? []);
    $ins = db()->prepare("INSERT INTO quote_lines (quote_id,line_no,sbu,service_type,subtypes,description,location,location_type,order_type,qty,unit,rate,amount,deliverables) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $n = 0;
    foreach ($desc as $i => $d) {
        $d = trim((string)$d); $sv = trim((string)($svc[$i] ?? ''));
        if ($d === '' && $sv === '') continue;                         // skip blank rows
        $qv = (float)($qty[$i] ?? 0); $rv = (float)($rate[$i] ?? 0);
        $ins->execute([(int)$qid, $n++, $sbu[$i] ?? '', $sv, trim((string)($sub[$i] ?? '')), $d,
            trim((string)($loc[$i] ?? '')), $lt[$i] ?? 'REGISTERED', $ot[$i] ?? 'LINE', $qv, $unit[$i] ?? 'DAY', $rv, round($qv * $rv, 2), trim((string)($del[$i] ?? ''))]);
    }
}
// Schedule the §11 follow-up cadence (3/6/9 days, fortnight, month) from the sent date.
function crm_schedule_followups($qid) {
    $q = crm_quote_get($qid); if (!$q) return;
    $base = $q['sent_at'] ? substr($q['sent_at'], 0, 10) : date('Y-m-d');
    db()->prepare("DELETE FROM quote_followups WHERE quote_id=? AND status='PENDING'")->execute([(int)$qid]);
    $ins = db()->prepare("INSERT INTO quote_followups (quote_id,kind,due_date,status,created_at) VALUES (?,?,?, 'PENDING', ?)");
    foreach (FOLLOWUP_OFFSETS as $k => $days) $ins->execute([(int)$qid, $k, date('Y-m-d', strtotime("$base +$days days")), date('c')]);
}

// ---------------------------------------------------------------------------
//  Handlers — Inquiries (§1)
// ---------------------------------------------------------------------------
function ops_crm_inquiries($route, $method) {
    $pdo = db();
    if ($route === 'inquiries') {
        $q = trim($_GET['q'] ?? ''); $st = $_GET['st'] ?? '';
        $w = ['1=1']; $args = [];
        if ($st !== '' && isset(INQUIRY_STATUS[$st])) { $w[] = 'i.status=?'; $args[] = $st; }
        if ($q !== '') { $w[] = '(i.inquiry_no LIKE ? OR i.subject LIKE ? OR i.client_name LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
        $sb = scope_sbus();
        if ($sb !== 'ALL' && is_array($sb) && $sb) { $ph = implode(',', array_fill(0, count($sb), '?')); $w[] = "(i.sbu IN ($ph) OR i.sbu='')"; foreach ($sb as $s) $args[] = $s; }
        $rows = ops_all("SELECT i.*, bp.display_name client_disp FROM crm_inquiries i LEFT JOIN business_partners bp ON bp.id=i.client_id WHERE " . implode(' AND ', $w) . " ORDER BY i.id DESC", $args);
        view('ops/crm/inquiry_list', ['rows' => $rows, 'q' => $q, 'st' => $st]); return;
    }
    if ($route === 'inquiry-new' || $route === 'inquiry-edit') {
        ops_require(can('mod.inquiries.edit'), 'You cannot add or edit inquiries.');
        $inq = null;
        if ($route === 'inquiry-edit') { $inq = crm_inquiry_get((int)($_GET['id'] ?? 0)); if (!$inq) { http_response_code(404); view('notfound'); return; } }
        if ($method === 'POST') {
            $b = $_POST;
            $cid = ($b['client_id'] ?? '') !== '' ? (int)$b['client_id'] : null;
            $cname = $cid ? crm_client_name($cid) : trim($b['client_name'] ?? '');
            $vals = [$cid, $cname, trim($b['contact_name'] ?? ''), trim($b['contact_email'] ?? ''), trim($b['contact_mobile'] ?? ''),
                trim($b['subject'] ?? ''), trim($b['service_requirement'] ?? ''), $b['sbu'] ?? '', $b['source'] ?? 'EMAIL',
                $b['received_date'] ?? '', ($b['assigned_to'] ?? '') !== '' ? (int)$b['assigned_to'] : null, $b['status'] ?? 'OPEN', trim($b['notes'] ?? '')];
            if ($inq) {
                $pdo->prepare("UPDATE crm_inquiries SET client_id=?,client_name=?,contact_name=?,contact_email=?,contact_mobile=?,subject=?,service_requirement=?,sbu=?,source=?,received_date=?,assigned_to=?,status=?,notes=? WHERE id=?")
                    ->execute(array_merge($vals, [$inq['id']]));
                flash('Inquiry updated.'); redirect('/inquiries');
            } else {
                $no = crm_next_inquiry_no();
                $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no,client_id,client_name,contact_name,contact_email,contact_mobile,subject,service_requirement,sbu,source,received_date,assigned_to,status,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute(array_merge([$no], $vals, [user_name(current_user()), date('c')]));
                flash("$no created. You can now raise a quotation against it.");
                redirect('/inquiries');
            }
        }
        view('ops/crm/inquiry_form', ['inq' => $inq, 'clients' => clients_list(), 'sbuOpts' => lk_options_or('sbu', OPS_SBUS),
            'sourceOpts' => INQUIRY_SOURCES, 'statusOpts' => INQUIRY_STATUS,
            'users' => ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name, username")]);
        return;
    }
}

// ---------------------------------------------------------------------------
//  Handlers — Quotations (§2, §3, §4, §14, §23)
// ---------------------------------------------------------------------------
function ops_crm_quotes($route, $method) {
    $pdo = db();
    if ($route === 'quotes') {
        $view = $_GET['v'] ?? 'all';   // all | open | pending | closed | lost
        $stateSets = ['open' => QUOTE_OPEN_STATES, 'pending' => QUOTE_PENDING_STATES, 'closed' => QUOTE_CLOSED_STATES, 'lost' => ['LOST', 'EXPIRED']];
        [$scopeW, $args] = scope_clause('q.office_id', 'q.sbu');
        $w = [$scopeW, 'q.is_current=1'];
        if (isset($stateSets[$view])) { $set = $stateSets[$view]; $ph = implode(',', array_fill(0, count($set), '?')); $w[] = "q.status IN ($ph)"; foreach ($set as $s) $args[] = $s; }
        $qq = trim($_GET['q'] ?? '');
        if ($qq !== '') { $w[] = '(q.quote_no LIKE ? OR q.subject LIKE ? OR q.client_name LIKE ?)'; array_push($args, "%$qq%", "%$qq%", "%$qq%"); }
        $rows = ops_all("SELECT q.*, bp.display_name client_disp, o.name office_name FROM quotations q
            LEFT JOIN business_partners bp ON bp.id=q.client_id LEFT JOIN offices o ON o.id=q.office_id
            WHERE " . implode(' AND ', $w) . " ORDER BY q.id DESC", $args);
        // headline counts (scope-respecting)
        $counts = [];
        foreach (['open', 'pending', 'closed', 'lost'] as $k) {
            [$sw, $sa] = scope_clause('q.office_id', 'q.sbu'); $set = $stateSets[$k]; $ph = implode(',', array_fill(0, count($set), '?'));
            foreach ($set as $s) $sa[] = $s;
            $counts[$k] = (int)ops_val("SELECT COUNT(*) FROM quotations q WHERE $sw AND q.is_current=1 AND q.status IN ($ph)", $sa);
        }
        view('ops/crm/quote_list', ['rows' => $rows, 'view' => $view, 'q' => $qq, 'counts' => $counts]); return;
    }
    if ($route === 'quote-new' || $route === 'quote-edit') {
        ops_require(can('crm.quote.create') || can('mod.quotes.edit') || is_master(), 'You cannot create or edit quotations.');
        $q = null;
        if ($route === 'quote-edit') { $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; } }
        if ($method === 'POST') {
            $b = $_POST;
            $cid = ($b['client_id'] ?? '') !== '' ? (int)$b['client_id'] : null;
            $hdr = [
                'client_id' => $cid, 'client_name' => $cid ? crm_client_name($cid) : trim($b['client_name'] ?? ''),
                'contact_name' => trim($b['contact_name'] ?? ''), 'contact_email' => trim($b['contact_email'] ?? ''), 'contact_mobile' => trim($b['contact_mobile'] ?? ''),
                'sbu' => $b['sbu'] ?? '', 'office_id' => ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : null, 'subject' => trim($b['subject'] ?? ''),
                'site_location' => trim($b['site_location'] ?? ''), 'location_type' => $b['location_type'] ?? 'REGISTERED',
                'currency' => $b['currency'] ?? 'INR', 'validity_days' => (int)($b['validity_days'] ?? 30),
                'payment_terms' => trim($b['payment_terms'] ?? ''), 'advance_pct' => (float)($b['advance_pct'] ?? 0),
                'advance_required' => !empty($b['advance_required']) ? 1 : 0, 'report_vs_payment' => !empty($b['report_vs_payment']) ? 1 : 0,
                'gst_pct' => (float)($b['gst_pct'] ?? 18),
            ];
            if ($q) {
                $set = implode(',', array_map(fn($k) => "$k=?", array_keys($hdr)));
                $pdo->prepare("UPDATE quotations SET $set, updated_at=? WHERE id=?")->execute(array_merge(array_values($hdr), [date('c'), $q['id']]));
                crm_save_lines($q['id'], $b); crm_quote_recalc($q['id']);
                flash('Quotation saved.'); redirect('/quote?id=' . $q['id']);
            } else {
                $no = crm_next_quote_no();
                $inqId = ($b['inquiry_id'] ?? '') !== '' ? (int)$b['inquiry_id'] : null;
                $cols = array_merge(['quote_no', 'rev', 'is_current', 'inquiry_id', 'status', 'owner_id', 'created_by', 'created_at'], array_keys($hdr));
                $vals = array_merge([$no, 0, 1, $inqId, 'DRAFT', current_user()['id'] ?? null, user_name(current_user()), date('c')], array_values($hdr));
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO quotations (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                $id = $pdo->lastInsertId();
                crm_save_lines($id, $b); crm_quote_recalc($id);
                if ($inqId) $pdo->prepare("UPDATE crm_inquiries SET status='QUOTED' WHERE id=?")->execute([$inqId]);
                flash("$no created."); redirect('/quote?id=' . $id);
            }
        }
        $preInq = (!$q && ($_GET['inquiry'] ?? '') !== '') ? crm_inquiry_get((int)$_GET['inquiry']) : null;
        view('ops/crm/quote_form', ['q' => $q, 'lines' => $q ? crm_quote_lines($q['id']) : [], 'preInq' => $preInq,
            'clients' => clients_list(), 'offices' => offices_list(), 'sbuOpts' => lk_options_or('sbu', OPS_SBUS),
            'svcOpts' => lk_options_or('crm_service_type', CRM_SERVICE_TYPES), 'unitOpts' => QUOTE_UNITS,
            'orderOpts' => ORDER_TYPES, 'locTypes' => QUOTE_LOCATION_TYPES, 'delivOpts' => lk_options_or('deliverable', DELIVERABLES)]);
        return;
    }
    if ($route === 'quote') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        $base = (int)($q['parent_id'] ?: $q['id']);
        view('ops/crm/quote_detail', [
            'q' => $q, 'lines' => crm_quote_lines($q['id']),
            'revs' => ops_all("SELECT id, rev, status, total_amount, is_current, created_at FROM quotations WHERE quote_no=? ORDER BY rev", [$q['quote_no']]),
            'hist' => ops_all("SELECT * FROM quote_revisions WHERE quote_id=? ORDER BY rev DESC, id DESC", [$base]),
            'followups' => ops_all("SELECT * FROM quote_followups WHERE quote_id=? ORDER BY due_date", [$q['id']]),
            'lostReasons' => lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS),
            'canApprove' => can('crm.quote.approve') || is_master(), 'canSend' => can('crm.quote.send') || is_master(),
            'canEdit' => can('crm.quote.create') || can('mod.quotes.edit') || is_master()]);
        return;
    }
    if ($route === 'quote-status' && $method === 'POST') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        $to = $_POST['to'] ?? '';
        if (!isset(QUOTE_STATUS[$to])) { flash('Unknown status.', 'error'); redirect('/quote?id=' . $q['id']); }
        if ($to === 'APPROVED') ops_require(can('crm.quote.approve') || is_master(), 'You cannot approve quotations.');
        if ($to === 'SENT') ops_require(can('crm.quote.send') || is_master(), 'You cannot send quotations.');
        if ($to === 'LOST') {
            $pdo->prepare("UPDATE quotations SET status='LOST', lost_reason=?, lost_reason_other=? WHERE id=?")
                ->execute([$_POST['lost_reason'] ?? '', trim($_POST['lost_reason_other'] ?? ''), $q['id']]);
            $pdo->prepare("UPDATE quote_followups SET status='SKIPPED' WHERE quote_id=? AND status='PENDING'")->execute([$q['id']]);
        } elseif ($to === 'SENT') {
            $pdo->prepare("UPDATE quotations SET status='SENT', sent_at=? WHERE id=?")->execute([date('c'), $q['id']]);
            crm_schedule_followups($q['id']);
        } elseif ($to === 'ACCEPTED') {
            $pdo->prepare("UPDATE quotations SET status='ACCEPTED', accepted_date=? WHERE id=?")->execute([date('Y-m-d'), $q['id']]);
            $pdo->prepare("UPDATE quote_followups SET status='SKIPPED' WHERE quote_id=? AND status='PENDING'")->execute([$q['id']]);
            if ($q['inquiry_id']) $pdo->prepare("UPDATE crm_inquiries SET status='QUOTED' WHERE id=?")->execute([$q['inquiry_id']]);
        } else {
            $pdo->prepare("UPDATE quotations SET status=? WHERE id=?")->execute([$to, $q['id']]);
        }
        flash('Quotation moved to ' . (QUOTE_STATUS[$to] ?? $to) . '.');
        redirect('/quote?id=' . $q['id']);
    }
    if ($route === 'quote-revise' && $method === 'POST') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        ops_require(can('crm.quote.create') || can('mod.quotes.edit') || is_master(), 'You cannot revise quotations.');
        $base = (int)($q['parent_id'] ?: $q['id']);
        $summary = trim($_POST['summary'] ?? '') ?: 'Revised';
        $snap = ['header' => $q, 'lines' => crm_quote_lines($q['id'])];
        $newRev = (int)$q['rev'] + 1;
        // Snapshot the source first (fully-drained reads), then INSERT the new revision
        // BEFORE flipping the old ones — doing the same-table UPDATE first was observed
        // to corrupt the following wide INSERT under SQLite + the php built-in server.
        $srcRows = ops_all("SELECT * FROM quotations WHERE id=?", [$q['id']]);
        $src = $srcRows[0];
        $srcLines = crm_quote_lines($q['id']);
        $carryCols = ['inquiry_id','client_id','client_name','contact_name','contact_email','contact_mobile','sbu','office_id','subject',
            'site_location','location_type','currency','validity_days','payment_terms','advance_pct','advance_required',
            'report_vs_payment','subtotal','gst_pct','gst_amount','total_amount','template_id','owner_id'];
        $cols = array_merge(['quote_no','rev','parent_id','is_current','status','created_by','created_at','updated_at'], $carryCols);
        $vals = array_merge([$src['quote_no'], $newRev, $base, 1, 'DRAFT', user_name(current_user()), date('c'), date('c')],
            array_map(fn($c) => $src[$c], $carryCols));
        $pdo->prepare("INSERT INTO quotations (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute($vals);
        $nid = (int)$pdo->lastInsertId();
        $lcols = ['line_no','sbu','service_type','subtypes','description','location','location_type','order_type','qty','unit','rate','amount','deliverables','notes'];
        $lins = $pdo->prepare("INSERT INTO quote_lines (quote_id," . implode(',', $lcols) . ") VALUES (?," . implode(',', array_fill(0, count($lcols), '?')) . ")");
        foreach ($srcLines as $l) $lins->execute(array_merge([$nid], array_map(fn($c) => $l[$c], $lcols)));
        // Now demote every other revision of this quote.
        $pdo->prepare("UPDATE quotations SET is_current=0 WHERE quote_no=? AND id<>?")->execute([$q['quote_no'], $nid]);
        $pdo->prepare("INSERT INTO quote_revisions (quote_id,rev,changed_by,changed_at,summary,snapshot) VALUES (?,?,?,?,?,?)")
            ->execute([$base, $newRev, user_name(current_user()), date('c'), $summary, json_encode($snap)]);
        flash('Created revision ' . str_pad((string)$newRev, 2, '0', STR_PAD_LEFT) . '. Edit it below.');
        redirect('/quote-edit?id=' . $nid);
    }
    if ($route === 'quote-doc') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        $tplId = (int)($_GET['tpl'] ?? 0);
        $tpl = $tplId ? ops_one("SELECT * FROM crm_templates WHERE id=? AND kind='QUOTE_DOC'", [$tplId])
                      : ops_one("SELECT * FROM crm_templates WHERE kind='QUOTE_DOC' AND active=1 ORDER BY is_default DESC, id DESC");
        if (!$tpl || ($tpl['file_data'] ?? '') === '') { flash('No quotation Word template is uploaded yet. Add one under CRM → Quote templates.', 'error'); redirect('/quote?id=' . $q['id']); }
        [$bin, $err] = docx_fill(base64_decode($tpl['file_data']), quote_doc_map($q, $tpl), crm_quote_lines($q['id']));
        if ($err) { flash('Could not generate the Word file: ' . $err, 'error'); redirect('/quote?id=' . $q['id']); }
        $fname = 'Quote-' . preg_replace('/[^A-Za-z0-9_.-]/', '', $q['quote_no'] . ($q['rev'] > 0 ? '-R' . $q['rev'] : '')) . '.docx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        header('Content-Length: ' . strlen($bin));
        echo $bin; exit;
    }
}

// ---------------------------------------------------------------------------
//  .docx template engine (no external library — ZipArchive + token replace)
// ---------------------------------------------------------------------------
// Escape a value for inclusion inside a <w:t> text node; newlines → spaces so
// the resulting XML stays valid.
function docx_escape($s) {
    $s = str_replace(["\r\n", "\r", "\n"], ' ', (string)$s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
// Word often splits a typed token across several <w:r>/<w:t> runs. Rejoin the two
// braces of {{ and }} when tags sit between them, then strip any tags INSIDE a
// token so its name is contiguous and can be matched.
function docx_repair_tokens($xml) {
    $xml = preg_replace('/\{(?:<[^>]+>)+\{/u', '{{', $xml);
    $xml = preg_replace('/\}(?:<[^>]+>)+\}/u', '}}', $xml);
    $xml = preg_replace_callback('/\{\{(.*?)\}\}/us', function ($m) { return '{{' . preg_replace('/<[^>]+>/u', '', $m[1]) . '}}'; }, $xml);
    return $xml;
}
// Per-line token map for a repeated table row.
function docx_line_map($l, $i) {
    return [
        'l_no' => $i, 'l_sbu' => lk_options_or('sbu', OPS_SBUS)[$l['sbu']] ?? $l['sbu'],
        'l_service' => lk_options_or('crm_service_type', CRM_SERVICE_TYPES)[$l['service_type']] ?? $l['service_type'],
        'l_subtypes' => $l['subtypes'], 'l_desc' => $l['description'], 'l_location' => $l['location'],
        'l_order' => ORDER_TYPES[$l['order_type']] ?? $l['order_type'],
        'l_qty' => rtrim(rtrim(number_format((float)$l['qty'], 2), '0'), '.'),
        'l_unit' => QUOTE_UNITS[$l['unit']] ?? $l['unit'],
        'l_rate' => number_format((float)$l['rate'], 2), 'l_amount' => number_format((float)$l['amount'], 2),
    ];
}
// If a table row contains {{l_desc}}, clone that row once per line item.
function docx_expand_line_rows($xml, $lines) {
    if (!preg_match('/(<w:tr\b(?:(?!<w:tr\b).)*?\{\{l_desc\}\}.*?<\/w:tr>)/us', $xml, $mm)) return $xml;
    $rowTpl = $mm[1]; $out = '';
    $i = 0;
    foreach ($lines as $l) {
        $i++; $row = $rowTpl;
        foreach (docx_line_map($l, $i) as $k => $v) $row = str_replace('{{' . $k . '}}', docx_escape($v), $row);
        $out .= $row;
    }
    if ($out === '') $out = preg_replace('/\{\{l_[a-z_]+\}\}/u', '', $rowTpl);   // no lines → blank the row
    return str_replace($rowTpl, $out, $xml);
}
function docx_replace($xml, $map) {
    foreach ($map as $k => $v) $xml = str_replace('{{' . $k . '}}', docx_escape($v), $xml);
    return $xml;
}
// Fill a .docx (binary) with $map + repeated line rows. Returns [binary, error].
function docx_fill($binary, $map, $lines) {
    if (!class_exists('ZipArchive')) return [null, 'The "zip" PHP extension is not enabled on this server.'];
    $tmp = tempnam(sys_get_temp_dir(), 'qz');
    if ($tmp === false || file_put_contents($tmp, $binary) === false) return [null, 'Could not write a temporary file.'];
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) { @unlink($tmp); return [null, 'The template is not a valid .docx file.']; }
    $parts = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        if (preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $n)) $parts[$n] = $zip->getFromName($n);
    }
    foreach ($parts as $n => $xml) {
        $xml = docx_repair_tokens($xml);
        if (strpos($n, 'document.xml') !== false) $xml = docx_expand_line_rows($xml, $lines);
        $xml = docx_replace($xml, $map);
        $zip->deleteName($n); $zip->addFromString($n, $xml);
    }
    $zip->close();
    $out = file_get_contents($tmp); @unlink($tmp);
    return [$out, null];
}
// Header token map from a quotation + the template's control numbers.
function quote_doc_map($q, $tpl) {
    $off = $q['office_id'] ? ops_val("SELECT name FROM offices WHERE id=?", [$q['office_id']]) : '';
    return [
        'quote_no' => $q['quote_no'], 'quote_label' => quote_label($q),
        'quote_rev' => (int)$q['rev'] > 0 ? 'Rev ' . str_pad((string)$q['rev'], 2, '0', STR_PAD_LEFT) : '',
        'quote_date' => date('d M Y', strtotime($q['created_at'] ?: 'now')),
        'client_name' => $q['client_name'], 'contact_name' => $q['contact_name'],
        'contact_email' => $q['contact_email'], 'contact_mobile' => $q['contact_mobile'],
        'sbu' => lk_options_or('sbu', OPS_SBUS)[$q['sbu']] ?? $q['sbu'], 'subject' => $q['subject'],
        'site_location' => $q['site_location'], 'location_type' => QUOTE_LOCATION_TYPES[$q['location_type']] ?? $q['location_type'],
        'validity_days' => $q['validity_days'], 'payment_terms' => $q['payment_terms'],
        'advance_pct' => rtrim(rtrim(number_format((float)$q['advance_pct'], 2), '0'), '.'),
        'currency' => $q['currency'], 'office_name' => $off ?: '',
        'subtotal' => number_format((float)$q['subtotal'], 2), 'gst_pct' => rtrim(rtrim(number_format((float)$q['gst_pct'], 2), '0'), '.'),
        'gst_amount' => number_format((float)$q['gst_amount'], 2), 'total_amount' => number_format((float)$q['total_amount'], 2),
        'total_in_words' => amount_in_words_inr((float)$q['total_amount']),
        // Controlled-document identity carried from the uploaded FORMAT
        'doc_number' => $tpl['document_number'] ?? '', 'format_number' => $tpl['format_number'] ?? '',
        'doc_rev' => $tpl['doc_revision'] ?? '', 'doc_date' => $tpl['issue_date'] ?? '',
    ];
}
// Indian-format amount in words (rupees). Compact; good enough for a quote footer.
function amount_in_words_inr($n) {
    $n = (int)round($n); if ($n === 0) return 'Zero Rupees Only';
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = function ($x) use ($ones, $tens) { if ($x < 20) return $ones[$x]; return trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]); };
    $three = function ($x) use ($ones, $two) { $h = intdiv($x, 100); $r = $x % 100; return trim(($h ? $ones[$h] . ' Hundred' . ($r ? ' ' : '') : '') . ($r ? $two($r) : '')); };
    $parts = [];
    $crore = intdiv($n, 10000000); $n %= 10000000;
    $lakh = intdiv($n, 100000); $n %= 100000;
    $thou = intdiv($n, 1000); $n %= 1000;
    if ($crore) $parts[] = $three($crore) . ' Crore';
    if ($lakh) $parts[] = $three($lakh) . ' Lakh';
    if ($thou) $parts[] = $three($thou) . ' Thousand';
    if ($n) $parts[] = $three($n);
    return trim(implode(' ', $parts)) . ' Rupees Only';
}

// ---------------------------------------------------------------------------
//  Handlers — quote / e-mail templates (§5, §6)
// ---------------------------------------------------------------------------
function ops_crm_templates($route, $method) {
    ops_require(can('crm.template.manage') || is_master(), 'You cannot manage CRM templates.');
    $pdo = db();
    if ($route === 'crm-templates') {
        $rows = ops_all("SELECT id, kind, name, document_number, format_number, doc_revision, issue_date, file_name, active, is_default FROM crm_templates ORDER BY kind, name");
        view('ops/crm/template_list', ['rows' => $rows]); return;
    }
    if ($route === 'crm-template-download') {
        $t = ops_one("SELECT * FROM crm_templates WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$t || ($t['file_data'] ?? '') === '') { flash('No file on this template.', 'error'); redirect('/crm-templates'); }
        $bin = base64_decode($t['file_data']);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $t['file_name'] ?: 'template.docx') . '"');
        header('Content-Length: ' . strlen($bin)); echo $bin; exit;
    }
    if ($route === 'crm-template-delete' && $method === 'POST') {
        $pdo->prepare("DELETE FROM crm_templates WHERE id=?")->execute([(int)($_GET['id'] ?? 0)]);
        flash('Template deleted.'); redirect('/crm-templates');
    }
    if ($route === 'crm-template-new' || $route === 'crm-template-edit') {
        $t = null;
        if ($route === 'crm-template-edit') { $t = ops_one("SELECT * FROM crm_templates WHERE id=?", [(int)($_GET['id'] ?? 0)]); if (!$t) { http_response_code(404); view('notfound'); return; } }
        if ($method === 'POST') {
            $b = $_POST;
            $kind = isset(CRM_TEMPLATE_KINDS[$b['kind'] ?? '']) ? $b['kind'] : 'QUOTE_DOC';
            $isDef = !empty($b['is_default']) ? 1 : 0;
            $active = !empty($b['active']) ? 1 : 0;
            // optional new file upload (.docx)
            $fileName = $t['file_name'] ?? ''; $fileData = $t['file_data'] ?? '';
            if (!empty($_FILES['file']['tmp_name']) && (int)$_FILES['file']['error'] === 0) {
                $raw = file_get_contents($_FILES['file']['tmp_name']);
                if (strncmp($raw, "PK", 2) !== 0) { flash('The template must be a .docx (Word) file.', 'error'); redirect($t ? '/crm-template-edit?id=' . $t['id'] : '/crm-template-new'); }
                $fileName = $_FILES['file']['name']; $fileData = base64_encode($raw);
            }
            $fields = [$kind, trim($b['name'] ?? ''), trim($b['subject'] ?? ''), $b['body'] ?? '', $fileName, $fileData,
                trim($b['document_number'] ?? ''), trim($b['format_number'] ?? ''), trim($b['doc_revision'] ?? ''), trim($b['issue_date'] ?? ''), $active, $isDef];
            if ($t) {
                $pdo->prepare("UPDATE crm_templates SET kind=?,name=?,subject=?,body=?,file_name=?,file_data=?,document_number=?,format_number=?,doc_revision=?,issue_date=?,active=?,is_default=? WHERE id=?")
                    ->execute(array_merge($fields, [$t['id']]));
                $newId = $t['id']; flash('Template saved.');
            } else {
                $pdo->prepare("INSERT INTO crm_templates (kind,name,subject,body,file_name,file_data,document_number,format_number,doc_revision,issue_date,active,is_default,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute(array_merge($fields, [user_name(current_user()), date('c')]));
                $newId = (int)$pdo->lastInsertId(); flash('Template added.');
            }
            // only one default per kind
            if ($isDef) $pdo->prepare("UPDATE crm_templates SET is_default=0 WHERE kind=? AND id<>?")->execute([$kind, $newId]);
            redirect('/crm-templates');
        }
        view('ops/crm/template_form', ['t' => $t, 'kinds' => CRM_TEMPLATE_KINDS]); return;
    }
}
// The tokens a quote-doc template may use (shown on the template form).
function quote_doc_tokens() {
    return [
        'Document control (from this format)' => ['doc_number', 'format_number', 'doc_rev', 'doc_date'],
        'Quotation' => ['quote_no', 'quote_label', 'quote_rev', 'quote_date', 'subject', 'sbu', 'office_name', 'validity_days'],
        'Customer' => ['client_name', 'contact_name', 'contact_email', 'contact_mobile', 'site_location', 'location_type'],
        'Commercials' => ['currency', 'payment_terms', 'advance_pct', 'subtotal', 'gst_pct', 'gst_amount', 'total_amount', 'total_in_words'],
        'Line-item row (put inside ONE table row)' => ['l_no', 'l_sbu', 'l_service', 'l_subtypes', 'l_desc', 'l_location', 'l_order', 'l_qty', 'l_unit', 'l_rate', 'l_amount'],
    ];
}
