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
// Retired: Sales and Operations share the one "Type of inspection" master
// (INSPECTION_TYPES in lib/ops.php). Kept only as the fallback label map for
// rows written before the two lists were merged.
const CRM_SERVICE_TYPES = INSPECTION_TYPES;
// Resident-posting sub-categories (multiple selection).
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
// One charge unit for the whole app. A quote line, a rate card and a PO line
// all mean the same thing by "man-day", so they now share one master list and
// one set of codes. QUOTE_UNITS is kept as the name the CRM code already uses.
const CHARGE_UNITS = [
    'MANDAY'    => 'Man-day',
    'MANMONTH'  => 'Man-month',
    'VISIT'     => 'Per visit',
    'AUDIT_DAY' => 'Audit day',
    'LOT'       => 'Per lot / lump sum',
    'DOC'       => 'Per document',
    'PER_KM'    => 'Per km',
    'OTHER'     => 'Other',
];
const QUOTE_UNITS = CHARGE_UNITS;
// Old per-module codes -> the shared ones. Used by the idempotent migration.
const CHARGE_UNIT_ALIASES = [
    'DAY' => 'MANDAY', 'MONTH' => 'MANMONTH', 'MANDAYS' => 'MANDAY',
    'MONTHS' => 'MANMONTH', 'AUDIT_DAYS' => 'AUDIT_DAY', 'VISITS' => 'VISIT',
    'LUMP' => 'LOT',
];
// Rewrite the old per-module codes to the shared ones, wherever they are stored.
// Idempotent: once a column holds only shared codes, every UPDATE matches nothing.
function crm_migrate_charge_units() {
    $cols = [['quote_lines', 'unit'], ['po_line_items', 'item_type']];
    foreach ($cols as [$table, $col]) {
        foreach (CHARGE_UNIT_ALIASES as $old => $new) {
            try { db()->prepare("UPDATE $table SET $col=? WHERE $col=?")->execute([$new, $old]); }
            catch (Throwable $e) { /* table not created yet on a partial upgrade */ }
        }
    }
}
// Sales used to keep its own list of work types. It now shares the one
// "Type of inspection" master, so a quote, a call and a deputation all say the
// same thing. Only two codes differed in meaning; the rest were already equal.
const SERVICE_TYPE_ALIASES = ['PROJECT_DEP' => 'DEPUTATION', 'DOC_REVIEW' => 'DESKTOP'];
function crm_migrate_service_types() {
    foreach (SERVICE_TYPE_ALIASES as $old => $new) {
        try { db()->prepare("UPDATE quote_lines SET service_type=? WHERE service_type=?")->execute([$new, $old]); }
        catch (Throwable $e) { /* table not created yet on a partial upgrade */ }
    }
    // The old Sales-only list is retired; its values now live in inspection_type.
    if (function_exists('lk_type') && lk_type('crm_service_type')) {
        try {
            $t = lk_type('crm_service_type');
            db()->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([$t['id']]);
            db()->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$t['id']]);
        } catch (Throwable $e) {}
    }
}
function service_types_pending() {
    $in = "'" . implode("','", array_keys(SERVICE_TYPE_ALIASES)) . "'";
    try {
        if ((int)db()->query("SELECT COUNT(*) FROM quote_lines WHERE service_type IN ($in)")->fetchColumn() > 0) return true;
        return (int)db()->query("SELECT COUNT(*) FROM lookup_types WHERE type_key='crm_service_type'")->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}
function charge_units_pending() {
    $in = "'" . implode("','", array_keys(CHARGE_UNIT_ALIASES)) . "'";
    foreach ([['quote_lines', 'unit'], ['po_line_items', 'item_type']] as [$t, $c]) {
        try { if ((int)db()->query("SELECT COUNT(*) FROM $t WHERE $c IN ($in)")->fetchColumn() > 0) return true; }
        catch (Throwable $e) { return false; }
    }
    return false;
}
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
    'REJECTED'         => 'Rejected by approver',
    'ACCEPTED'         => 'Accepted / closed-won',
    'LOST'             => 'Lost / regretted',
    'EXPIRED'          => 'Expired',
];
// Which statuses count as OPEN / PENDING / CLOSED for the §14 views.
const QUOTE_OPEN_STATES    = ['DRAFT','PENDING_APPROVAL','APPROVED','SENT','REJECTED'];
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

// Approval-rule scope: a rule may target an amount band and/or a business unit (§ owner:
// "by quote amount threshold aligned with respect to Business Unit … either or both").
const APPROVAL_MATCH = ['ANY'=>'Any Business Unit', 'Business Unit'=>'Specific Business Unit'];

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
        // rev is is_current=1); the contract supersede pattern, reused for §23.
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

        // Quote / order line items (§4 Business Unit per line, §16 revenue, §17 order lines).
        "CREATE TABLE IF NOT EXISTS quote_lines (
            id $pk, quote_id INT, line_no INT DEFAULT 0, sbu VARCHAR(20) DEFAULT '',
            service_type VARCHAR(30) DEFAULT '', subtypes VARCHAR(400) DEFAULT '', description VARCHAR(400) DEFAULT '',
            location VARCHAR(255) DEFAULT '', location_type VARCHAR(20) DEFAULT 'REGISTERED',
            order_type VARCHAR(10) DEFAULT 'LINE', qty DECIMAL(12,2) DEFAULT 0, unit VARCHAR(20) DEFAULT 'MANDAY',
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

        // Configurable approval matrix (§ owner: amount threshold and/or Business Unit).
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
            subject VARCHAR(255) DEFAULT '', body MEDIUMTEXT, file_name VARCHAR(200) DEFAULT '', file_data LONGTEXT,
            fields MEDIUMTEXT, document_number VARCHAR(80) DEFAULT '', format_number VARCHAR(80) DEFAULT '',
            doc_revision VARCHAR(40) DEFAULT '', issue_date VARCHAR(20) DEFAULT '',
            active INT DEFAULT 1, is_default INT DEFAULT 0, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",

        // A quote can cover several sites. Each is a full address, not a text box,
        // and each line item points at one of them.
        "CREATE TABLE IF NOT EXISTS quote_locations (
            id $pk, quote_id INT, label VARCHAR(150) DEFAULT '', location_type VARCHAR(20) DEFAULT 'SITE',
            line1 VARCHAR(255) DEFAULT '', line2 VARCHAR(255) DEFAULT '', city VARCHAR(120) DEFAULT '',
            state VARCHAR(120) DEFAULT '', pincode VARCHAR(15) DEFAULT '', country VARCHAR(80) DEFAULT 'India',
            contact_name VARCHAR(150) DEFAULT '', contact_mobile VARCHAR(40) DEFAULT '',
            office_id INT NULL, sort_order INT DEFAULT 0)",

        // Everything filed against a quote: our own format, general attachments,
        // the client's PO and any inspection documents that must reach the engineer.
        "CREATE TABLE IF NOT EXISTS quote_files (
            id $pk, quote_id INT, kind VARCHAR(20) DEFAULT 'ATTACHMENT', file_name VARCHAR(200) DEFAULT '',
            mime VARCHAR(100) DEFAULT '', file_data LONGTEXT, note VARCHAR(255) DEFAULT '',
            share_with_inspector INT DEFAULT 1, uploaded_by VARCHAR(150) DEFAULT '', uploaded_at VARCHAR(30) DEFAULT '')",

        // A closed quote is locked. Re-opening it is a request the Super Admin grants.
        "CREATE TABLE IF NOT EXISTS quote_edit_requests (
            id $pk, quote_id INT, requested_by VARCHAR(150) DEFAULT '', requested_by_id INT NULL,
            reason VARCHAR(500) DEFAULT '', status VARCHAR(20) DEFAULT 'PENDING',
            decided_by VARCHAR(150) DEFAULT '', decided_at VARCHAR(30) DEFAULT '', decision_note VARCHAR(400) DEFAULT '',
            expires_at VARCHAR(30) DEFAULT '', created_at VARCHAR(30) DEFAULT '')",
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
    // Sales and Operations now pick the type of work from ONE list, so what is
    // quoted is the same thing that later appears on the call and the
    // deputation. The two Sales-only codes are rewritten to their equivalents.
    crm_migrate_service_types();
    // A quote line, a rate card and a PO line all charge in the same units, so
    // they now share one list — rewrite the old per-module codes.
    crm_migrate_charge_units();

    if (function_exists('ensure_column')) {
        // The lead an inquiry was converted from, so its origin is a real link
        // (and shows on the register) rather than only a line of notes.
        ensure_column('crm_inquiries', 'lead_id', 'INT NULL');
        // Where the quote came from: our own, or a client / tender portal that we
        // still want in the register (§xv).
        ensure_column('quotations', 'origin', "VARCHAR(20) DEFAULT 'OWN'");
        ensure_column('quotations', 'origin_portal', "VARCHAR(150) DEFAULT ''");
        ensure_column('quotations', 'origin_ref', "VARCHAR(120) DEFAULT ''");
        // Types of inspection requested — the same master the call uses.
        ensure_column('quotations', 'inspection_types', "VARCHAR(600) DEFAULT ''");
        ensure_column('quotations', 'product_category', "VARCHAR(150) DEFAULT ''");
        // Terms & conditions: seeded from the company default, editable per quote.
        ensure_column('quotations', 'terms_conditions', 'TEXT');
        // Work can be executed by more than one office (CSV of office ids); the
        // header office stays as the primary/owning one.
        ensure_column('quotations', 'exec_office_ids', "VARCHAR(200) DEFAULT ''");
        ensure_column('quotations', 'vendor_id', 'INT NULL');   // manufacturer / inspection site, carried to the work-order
        // A quote may cover several sites at DIFFERENT vendors, so each location
        // can name its own vendor (from the master) — or be left "to be confirmed".
        ensure_column('quote_locations', 'vendor_id', 'INT NULL');
        // Rejection is a first-class outcome, with who and why.
        ensure_column('quotations', 'rejected_by', "VARCHAR(150) DEFAULT ''");
        ensure_column('quotations', 'rejected_at', "VARCHAR(30) DEFAULT ''");
        ensure_column('quotations', 'reject_remarks', "VARCHAR(500) DEFAULT ''");
        // Closed quotes are locked; a granted edit request unlocks one.
        ensure_column('quotations', 'locked', 'INT DEFAULT 0');
        ensure_column('quotations', 'unlocked_until', "VARCHAR(30) DEFAULT ''");
        // The signatory whose stored signature is stamped on the PDF.
        ensure_column('quotations', 'signatory_user_id', 'INT NULL');
        // Client PO captured at acceptance.
        ensure_column('quotations', 'po_number', "VARCHAR(80) DEFAULT ''");
        ensure_column('quotations', 'po_date', "VARCHAR(20) DEFAULT ''");
        ensure_column('quotations', 'submitted_at', "VARCHAR(30) DEFAULT ''");
        // The lead this quotation was raised from, so a salesperson can quote
        // straight off a lead and see every quotation back on it — without
        // first converting the lead or hunting through the register.
        ensure_column('quotations', 'lead_id', 'INT NULL');
        // Pre-order review checklist ticks for this quotation (JSON: item=>1). Only
        // used when the configurable pre-order checklist is switched on.
        ensure_column('quotations', 'preorder_checklist', 'MEDIUMTEXT');
        // Per-line: which office executes, which site, and the activity under the Business Unit.
        ensure_column('quote_lines', 'office_id', 'INT NULL');
        ensure_column('quote_lines', 'location_id', 'INT NULL');
        ensure_column('quote_lines', 'activity_id', 'INT NULL');
        // Follow-ups become editable and can carry a note of what happened.
        ensure_column('quote_followups', 'done_date', "VARCHAR(20) DEFAULT ''");
        ensure_column('quote_followups', 'note', "VARCHAR(500) DEFAULT ''");
        ensure_column('quote_followups', 'owner_id', 'INT NULL');
    }
    // Payment terms are a master, not free text (§vi).
    if (function_exists('lk_ensure_type_map')) {
        lk_ensure_type_map('payment_term', 'Payment term', PAYMENT_TERMS, 'Sales');
        lk_ensure_values_from_map('payment_term', PAYMENT_TERMS);
        lk_ensure_type_map('quote_origin', 'Quote origin', QUOTE_ORIGINS, 'Sales');
        lk_ensure_type_map('site_location_type', 'Site location type', SITE_LOCATION_TYPES, 'Sales');
    }
    crm_backfill_locations();
    crm_canon_product_categories();
    crm_unique_number_indexes();
}

// §3 — the same rule as the screens, enforced by the database, so two people
// saving in the same second cannot both get through. Best-effort: if the table
// already holds duplicates the index will not build, and the screen-level checks
// (which give a readable message anyway) remain the guard.
function crm_unique_number_indexes() {
    $idx = [
        ['uq_quotations_no_rev', 'quotations',        '(quote_no, rev)'],
        ['uq_contract_number',   'partner_contracts', '(contract_number)'],
    ];
    foreach ($idx as [$name, $table, $cols]) {
        try { db()->exec("CREATE UNIQUE INDEX $name ON $table $cols"); }
        catch (Throwable $e) { /* already there, or existing duplicates — the app-level check stands */ }
    }
}

// Quotations used to store the product category as the words on the master list
// ("Transformers") while inspection calls stored its code ("TRANSFORMER"), so the
// two never matched and nothing carried across. Fold the stored words onto the
// code, once, per quotation.
function crm_canon_product_categories() {
    try {
        $rows = ops_all("SELECT id, product_category FROM quotations WHERE product_category <> ''");
    } catch (Throwable $e) { return; }
    if (!$rows) return;
    $master = lk_options_or('product', PRODUCT_CATS);
    $byLabel = [];
    foreach ($master as $code => $label) $byLabel[strtolower(preg_replace('/[^a-z0-9]+/i', '', $label))] = $code;
    $upd = db()->prepare("UPDATE quotations SET product_category=? WHERE id=?");
    foreach ($rows as $r) {
        $v = (string)$r['product_category'];
        if (isset($master[$v])) continue;                       // already a code
        $code = $byLabel[strtolower(preg_replace('/[^a-z0-9]+/i', '', $v))] ?? null;
        if ($code) { try { $upd->execute([$code, (int)$r['id']]); } catch (Throwable $e) {} }
    }
}

// Payment terms, elaborated and fully editable under Masters.
const PAYMENT_TERMS = [
    'ADV100'    => '100% advance',
    'ADV50'     => '50% advance, balance against report',
    'ADV25'     => '25% advance, balance against report',
    'AGAINST_REPORT' => 'Against submission of report',
    'AGAINST_INV'    => 'Against invoice',
    'NET7'      => '7 days from invoice',
    'NET10'     => '10 days from invoice',
    'NET15'     => '15 days from invoice',
    'NET21'     => '21 days from invoice',
    'NET30'     => '30 days from invoice',
    'NET45'     => '45 days from invoice',
    'NET60'     => '60 days from invoice',
    'NET90'     => '90 days from invoice',
    'MILESTONE' => 'Milestone-linked',
    'PROFORMA'  => 'Against proforma invoice',
    'LC'        => 'Letter of credit',
    'OTHER'     => 'Other (see terms)',
];
// Where a quote came from. Not every quote is ours — some are submitted straight
// into a client or tender portal and still belong in the register (§xv).
const QUOTE_ORIGINS = [
    'OWN'           => 'Our quotation',
    'CLIENT_PORTAL' => "Client's portal",
    'TENDER_PORTAL' => 'Tender / e-procurement portal',
    'EMAIL_ONLY'    => 'Submitted by e-mail only',
    'OTHER'         => 'Other',
];
const SITE_LOCATION_TYPES = [
    'TBD'        => 'Site to be confirmed',
    'SITE'       => 'Project site',
    'WORKS'      => 'Manufacturer works',
    'REGISTERED' => 'Registered office',
    'CORPORATE'  => 'Corporate office',
    'PLANT'      => 'Plant',
    'WAREHOUSE'  => 'Warehouse',
    'PORT'       => 'Port / yard',
    'LAB'        => 'Laboratory',
    'OTHER'      => 'Other',
];
// Quotes written before the address book existed kept a single text location.
// Carry it across so nothing is lost, once, per quote.
function crm_backfill_locations() {
    try {
        $rows = ops_all("SELECT id, site_location, location_type FROM quotations
                         WHERE site_location <> '' AND id NOT IN (SELECT quote_id FROM quote_locations)");
    } catch (Throwable $e) { return; }
    foreach ($rows as $r) {
        try {
            db()->prepare("INSERT INTO quote_locations (quote_id,label,location_type,line1,sort_order) VALUES (?,?,?,?,0)")
                ->execute([$r['id'], 'Site', $r['location_type'] ?: 'SITE', $r['site_location']]);
        } catch (Throwable $e) {}
    }
}

// ---- Small helpers ---------------------------------------------------------
function crm_next_inquiry_no() { return ops_next_code('crm_inquiries', 'inquiry_no', 'INQ'); }
// Quote number is per base document; revisions keep the same number and bump `rev`.
function crm_next_quote_no() {
    // Two people creating a quotation in the same second would otherwise be
    // handed the same number. Take the next free one, and if it is already gone
    // by the time we look again, step past it.
    $no = ops_next_code('quotations', 'quote_no', 'Q');
    for ($i = 0; $i < 50 && quote_no_taken($no, 0); $i++) {
        // Step to the next sequence number in whatever numbering scheme is
        // configured for quotations, rather than assuming the Q-00001 default.
        if (preg_match('/(\d+)$/', $no, $m) && function_exists('numbering_format_seq'))
            $no = numbering_format_seq('Q', (int)$m[1] + 1);
        else { $n = (int)substr($no, strrpos($no, '-') + 1); $no = sprintf('Q-%05d', $n + 1); }
    }
    return $no;
}
// ---------------------------------------------------------------------------
//  §3 — a number identifies one thing, or it identifies nothing
//
//  Two quotations sharing a number, or two contracts sharing a contract number,
//  make every downstream figure ambiguous: which order did this call draw on,
//  which contract expired, whose quantity was used up. Both are refused at the
//  point of entry, and the database carries the same rule so a race between two
//  users cannot slip one through.
// ---------------------------------------------------------------------------
// Is this quotation number (at this revision) already used by another record?
function quote_no_taken($quoteNo, $rev = 0, $exceptId = null) {
    $quoteNo = trim((string)$quoteNo);
    if ($quoteNo === '') return false;
    $sql = "SELECT id FROM quotations WHERE quote_no=? AND rev=?";
    $args = [$quoteNo, (int)$rev];
    if ($exceptId) { $sql .= " AND id<>?"; $args[] = (int)$exceptId; }
    return (bool)ops_val($sql . " LIMIT 1", $args);
}
// A contract number belongs to exactly one client. Returns the contract row it
// is already on when that row belongs to somebody else, else null. The same
// number on the SAME client is not a duplicate — that is a rate contract being
// drawn down again, which is the normal case.
function contract_no_clash($contractNo, $clientId, $exceptId = null) {
    $contractNo = trim((string)$contractNo);
    if ($contractNo === '') return null;
    $sql = "SELECT c.*, COALESCE(NULLIF(bp.display_name,''), bp.legal_name) owner_name
            FROM partner_contracts c LEFT JOIN business_partners bp ON bp.id=c.partner_id
            WHERE c.contract_number=? AND (c.partner_id IS NULL OR c.partner_id<>?)";
    $args = [$contractNo, (int)$clientId];
    if ($exceptId) { $sql .= " AND c.id<>?"; $args[] = (int)$exceptId; }
    return ops_one($sql . " LIMIT 1", $args);
}
// "Q-00042 Rev 01" style label.
function quote_label($q) { $n = $q['quote_no'] ?? ''; $r = (int)($q['rev'] ?? 0); return $r > 0 ? "$n Rev " . str_pad((string)$r, 2, '0', STR_PAD_LEFT) : $n; }

// ---------------------------------------------------------------------------
//  Quotation playbook — the sales forward path, the third sibling of the call
//  and job playbooks so Quote -> Order -> Job all read the same. Pure, testable
//  computation; one 'do this now' step; a rejection is a real blocker.
//    build & submit -> approved -> sent -> client's answer -> register contract
//    -> raise the order (hand to operations)
// ---------------------------------------------------------------------------
function crm_quote_playbook($q) {
    $qid = (int)($q['id'] ?? 0);
    $st  = (string)($q['status'] ?? 'DRAFT');
    $cnum = trim((string)($q['contract_number'] ?? ''));
    $hasContract = $cnum !== '';
    // Calls can only be raised once the contract is OPEN (endorsed + approved).
    $contractOpen = false;
    if ($hasContract) {
        try { $contractOpen = strtoupper((string)(ops_val("SELECT open_status FROM partner_contracts WHERE contract_number=? ORDER BY id DESC LIMIT 1", [$cnum]) ?? 'OPEN')) === 'OPEN'; }
        catch (Throwable $e) { $contractOpen = true; }
    }
    $raised = $qid ? ((int) ops_val("SELECT COUNT(*) FROM calls WHERE quotation_id=?", [$qid]) > 0) : false;
    // How far along the happy path the status sits (rejected sits at the approval step).
    $rankMap = ['DRAFT'=>0,'PENDING_APPROVAL'=>1,'REJECTED'=>1,'APPROVED'=>2,'SENT'=>3,'ACCEPTED'=>4,'LOST'=>4,'EXPIRED'=>4];
    $rank = $rankMap[$st] ?? 0;
    $lost     = in_array($st, ['LOST','EXPIRED'], true);
    $rejected = $st === 'REJECTED';
    $pending  = $st === 'PENDING_APPROVAL';
    $accepted = $st === 'ACCEPTED';
    $cl = function_exists('Tl') ? Tl('client') : 'client';

    $steps = [];
    $steps[] = ['key'=>'draft', 'label'=>'Build & submit the quotation', 'actionable'=>true,
        'done'=>$rank >= 1,
        'line'=>$rank >= 1 ? 'Drafted and submitted.' : 'Add the line items and terms, then submit it for approval.',
        'href'=>'/quote-edit?id=' . $qid, 'cta'=>'Edit the quote'];
    $steps[] = ['key'=>'approve', 'label'=>'Get it approved', 'actionable'=>true,
        'done'=>$rank >= 2, 'blocker'=>$rejected,
        'line'=>$rank >= 2 ? 'Approved and ready to go out.'
              : ($rejected ? 'Sent back by the approver — correct it and submit again, or raise a revision.'
                 : ($pending ? 'With the approver now — they approve it, or send it back with a comment.'
                    : 'Submit it so the approver can sign off.')),
        'href'=>'', 'cta'=>''];
    $steps[] = ['key'=>'send', 'label'=>'Send it to the ' . $cl, 'actionable'=>true,
        'done'=>$rank >= 3,
        'line'=>$rank >= 3 ? 'Sent — awaiting their reply.' : 'Once approved, send it out (buttons at the top).',
        'href'=>'', 'cta'=>''];
    $steps[] = ['key'=>'answer', 'label'=>'The ' . $cl . '’s answer', 'actionable'=>true,
        'done'=>$accepted,
        'line'=>$accepted ? 'Accepted — they said yes.'
              : ($rank >= 3 ? 'Waiting for their reply — mark it accepted, or lost.' : 'Comes after it is sent.'),
        'href'=>'', 'cta'=>''];
    $steps[] = ['key'=>'contract', 'label'=>'Register the contract number', 'actionable'=>true,
        'done'=>$accepted && $hasContract,
        'line'=>($accepted && $hasContract) ? ('Contract ' . $q['contract_number'] . ' registered.')
              : ($accepted ? 'Register the ' . $cl . ' and enter the contract number.' : 'Comes after it is accepted.'),
        'href'=>'#contract', 'cta'=>'Register contract'];
    // The quotation's journey ENDS at the contract number. Inspection / deputation
    // calls are a separate, repeatable step raised FROM the contract — often on a
    // later day, because acceptance is given to the quote, not to a call. So this
    // step points at the contract, never straight at a fresh call off the quote.
    // The "raise" button only shows to someone who actually raises calls
    // (operations/coordinators) — never to Finance or Sales, who just see the
    // status. ($callWord is already "inspection call", so we do not prefix it.)
    $callWord  = function_exists('Tl') ? Tl('call') : 'call';
    $callWordP = function_exists('Tlp') ? Tlp('call') : 'calls';
    $canRaiseCall = (function_exists('can') && can('ops.call.create')) || (function_exists('is_master') && is_master());
    $steps[] = ['key'=>'order', 'label'=>'Raise ' . $callWordP . ' from the contract', 'actionable'=>true,
        'done'=>$raised,
        'line'=>$raised ? ('The ' . $callWordP . ' are being raised against the contract — operations has it.')
              : ($contractOpen ? ('Operations raise the ' . $callWordP . ' from the contract — as many as the work needs, whenever they come in.')
                 : ($hasContract ? ('The contract must be endorsed and approved (opened) before ' . $callWordP . ' can be raised.')
                    : ($accepted ? ('Register the contract number first; the ' . $callWordP . ' are then raised once it is opened.')
                       : 'Comes after it is accepted.'))),
        'href'=>($contractOpen && $canRaiseCall) ? '/call-new?contract=' . urlencode($cnum) : '',
        'cta'=>($contractOpen && $canRaiseCall) ? ('Raise ' . $callWord) : ''];

    $firstOpen = -1;
    foreach ($steps as $i => $s) if (!empty($s['actionable']) && empty($s['done'])) { $firstOpen = $i; break; }
    foreach ($steps as $i => &$s) {
        if (empty($s['actionable'])) { $s['state'] = 'info'; continue; }
        if (!empty($s['done']))      { $s['state'] = 'done'; continue; }
        if ($i === $firstOpen)       { $s['state'] = !empty($s['blocker']) ? 'blocked' : 'now'; }
        else                         { $s['state'] = 'todo'; }
    }
    unset($s);
    $open = 0; foreach ($steps as $s) if (!empty($s['actionable']) && empty($s['done'])) $open++;

    return ['steps'=>$steps, 'open'=>$open, 'all_done'=>($open === 0), 'lost'=>$lost,
            'lost_label'=>($st === 'EXPIRED' ? 'expired' : 'not accepted'), 'rejected'=>$rejected,
            'accepted'=>$accepted, 'raised'=>$raised];
}

// Renders the quotation playbook card at the top of the quote Overview tab.
function crm_render_quote_playbook($q) {
    if (!$q) return;
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $pb  = crm_quote_playbook($q);
    $cl  = function_exists('Tl') ? Tl('client') : 'client';
    ob_start();
    if (function_exists('tosrm_playbook_css')) echo tosrm_playbook_css(); ?>
    <div class="card tosrm-pb" style="margin-top:16px">
      <h3>Quotation playbook — this <?= $esc(function_exists('Tl') ? Tl('quote') : 'quotation') ?></h3>
      <p class="pb-sub">From draft to a live order: build &amp; submit, get it approved, send it, capture the <?= $esc($cl) ?>’s answer, register the contract, then raise the order. The panels below carry the specifics.</p>
      <?php if ($pb['lost']): ?>
        <div class="pb-done-banner" style="border-color:var(--bad);background:color-mix(in srgb,var(--bad) 7%,var(--card))"><b style="color:var(--bad)">Closed — <?= $esc($pb['lost_label']) ?>.</b> Raise a revision to re-issue it if the <?= $esc($cl) ?> comes back.</div>
      <?php elseif ($pb['all_done']): ?>
        <div class="pb-done-banner"><b style="color:var(--ok)">✓ Won and handed over.</b> Accepted, contract registered and the order raised — operations has it now.</div>
      <?php else: ?>
      <ol class="pb-steps">
        <?php $n = 0; foreach ($pb['steps'] as $s): $n++; $stp = $s['state']; $num = $stp === 'done' ? '✓' : $n; ?>
          <li class="pb-step is-<?= $stp ?>">
            <span class="pb-num"><?= $esc($num) ?></span>
            <div class="pb-body">
              <div class="pb-label"><?= $esc($s['label']) ?>
                <?php if ($stp === 'now'): ?><span class="pb-tag t-now">Do this now</span>
                <?php elseif ($stp === 'blocked'): ?><span class="pb-tag t-blocked">Needs a fix</span>
                <?php elseif ($stp === 'done'): ?><span class="pb-tag t-done">Done</span><?php endif; ?>
              </div>
              <div class="pb-line"><?= $esc($s['line']) ?></div>
            </div>
            <?php if (!empty($s['href']) && !empty($s['cta']) && in_array($stp, ['now','blocked','todo'], true)): ?>
              <a class="btn small <?= ($stp === 'now' || $stp === 'blocked') ? '' : 'secondary' ?> pb-cta" href="<?= $esc($s['href']) ?>"><?= $esc($s['cta']) ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
}
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
    $ot = (array)($b['l_order'] ?? []);
    $qty = (array)($b['l_qty'] ?? []); $unit = (array)($b['l_unit'] ?? []); $rate = (array)($b['l_rate'] ?? []);
    // The sub-type free-text box is gone from the form: the Service dropdown is
    // now restricted to the header's agreed types, which is what it was really
    // for. The column stays so older rows keep printing.
    $sub = (array)($b['l_subtypes'] ?? []); $del = (array)($b['l_deliv'] ?? []);
    $act = (array)($b['l_activity'] ?? []); $off = (array)($b['l_office'] ?? []); $lid = (array)($b['l_location'] ?? []);
    // Site is chosen from the quote's address book; the text copy is kept so the
    // Word/PDF output and older reports still have something to print.
    $locs = crm_quote_locations((int)$qid);
    $byId = []; foreach ($locs as $L) $byId[(int)$L['id']] = $L;
    $ins = db()->prepare("INSERT INTO quote_lines (quote_id,line_no,sbu,service_type,subtypes,description,location,location_type,order_type,qty,unit,rate,amount,deliverables,office_id,location_id,activity_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $n = 0;
    foreach ($desc as $i => $d) {
        $d = trim((string)$d); $sv = trim((string)($svc[$i] ?? ''));
        if ($d === '' && $sv === '') continue;                         // skip blank rows
        $qv = (float)($qty[$i] ?? 0); $rv = (float)($rate[$i] ?? 0);
        $locId = (int)($lid[$i] ?? 0) ?: null;
        $L = $locId && isset($byId[$locId]) ? $byId[$locId] : null;
        $ins->execute([(int)$qid, $n++, $sbu[$i] ?? '', $sv, trim((string)($sub[$i] ?? '')), $d,
            $L ? quote_location_line($L) : '', $L ? $L['location_type'] : 'SITE',
            $ot[$i] ?? 'LINE', $qv, $unit[$i] ?? 'MANDAY', $rv, round($qv * $rv, 2), trim((string)($del[$i] ?? '')),
            (int)($off[$i] ?? 0) ?: null, $locId, (int)($act[$i] ?? 0) ?: null]);
    }
}

// ---------------------------------------------------------------------------
//  Product category — typed freely, but unified automatically (§xxv).
//
//  People type "transformer", "Transformers", "trasnformer". All three must land
//  on the same category, or the office-wise analysis is meaningless. Matching is
//  in three passes: exact (case/space-insensitive), singular/plural, then edit
//  distance. Only a close match wins; anything else becomes a new category, so a
//  genuinely new product is never silently merged into the wrong one.
// ---------------------------------------------------------------------------
//  A match against the master list resolves to that list's CODE — the same code
//  an inspection call stores — so a quotation and the call raised against it name
//  the product the same way. Anything genuinely new keeps the words typed.
function product_category_canon($raw) {
    $raw = trim(preg_replace('/\s+/', ' ', (string)$raw));
    if ($raw === '') return '';
    $known = product_categories_all();          // code => label
    $norm = fn($s) => strtolower(preg_replace('/[^a-z0-9]+/i', '', $s));
    $n = $norm($raw);
    // A code typed (or posted) verbatim is already canonical.
    foreach ($known as $code => $label) if ($norm($code) === $n || $norm($label) === $n) return $code;
    $sing = fn($s) => preg_replace('/(ies|es|s)$/i', '', $s);
    $ns = $norm($sing($raw));
    foreach ($known as $code => $label) if ($norm($sing($label)) === $ns) return $code;
    $best = null; $bestD = PHP_INT_MAX;
    foreach ($known as $code => $label) {
        $d = levenshtein($n, $norm($label));
        if ($d < $bestD) { $bestD = $d; $best = $code; }
    }
    // Allow roughly one typo per five characters, and never on very short words.
    $tol = (int)floor(max(strlen($n), 1) / 5);
    if ($best !== null && strlen($n) >= 4 && $bestD <= max(1, $tol)) return $best;
    return ucfirst($raw);   // genuinely new — keep what they typed
}
// Every category already in use, as code => label: the Operations master list
// first, then anything people have typed on a quotation that is not in it (those
// stand for themselves). Office-scoped for the people who work in one office;
// cumulative for anyone whose access spans offices (Business Unit heads, directors) — §xxv.
function product_categories_all() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $out = lk_options_or('product', PRODUCT_CATS);
    $norm = fn($s) => strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)$s));
    $seen = [];
    foreach ($out as $code => $label) { $seen[$norm($code)] = 1; $seen[$norm($label)] = 1; }
    try {
        $where = ''; $args = [];
        if (function_exists('scope_clause')) {
            [$w, $a] = scope_clause('office_id', 'sbu');
            if ($w && $w !== '1=1') { $where = " AND ($w)"; $args = $a; }
        }
        foreach (ops_all("SELECT DISTINCT product_category FROM quotations WHERE product_category <> ''$where", $args) as $r) {
            $v = trim((string)$r['product_category']); $k = $norm($v);
            if ($v === '' || isset($seen[$k])) continue;
            $seen[$k] = 1; $out[$v] = $v;
        }
    } catch (Throwable $e) {}
    return $cache = $out;
}
// The words to show for a stored category — the master label for a code, and the
// text itself for a category somebody typed that is not on the list.
function product_cat_label($v) {
    $v = (string)$v;
    if ($v === '') return '';
    return product_categories_all()[$v] ?? $v;
}

// ---- Terms & conditions (§xviii) -------------------------------------------
// One company default, carried onto every new quote and editable there.
function crm_default_terms() {
    $t = setting_get('quote_terms', '');
    if ($t !== '' && $t !== null) return $t;
    return "1. This quotation is valid for the period stated above.\n"
         . "2. Rates are exclusive of taxes; GST is charged as applicable.\n"
         . "3. " . TH('manday') . " means one " . Tl('engineer') . " for one working day of up to " . (function_exists('hours_cap_disp') ? hours_cap_disp() : '8.5') . " hours.\n"
         . "4. Travel, boarding and lodging at actuals unless stated otherwise.\n"
         . "5. Waiting or idle time at the works is chargeable at the quoted man-day rate.\n"
         . "6. Cancellation within 24 hours of the scheduled visit is chargeable in full.\n"
         . "7. Reports are issued after receipt of payment where so agreed.\n"
         . "8. Inspection is carried out to the agreed scope, specification and standard only.\n"
         . "9. Any statutory approval or third-party endorsement is not included unless stated.\n"
         . "10. Jurisdiction for any dispute is the location of our registered office.";
}
// People whose stored signature may be stamped on a quote PDF (§xx).
function crm_signatories() {
    try {
        return ops_all("SELECT id, username, first_name, last_name, role FROM users
                        WHERE is_active=1 AND signature <> '' ORDER BY first_name, username");
    } catch (Throwable $e) { return []; }
}
// The types of inspection this client actually buys — used to narrow the picker
// the same way the call screen does (§v).
function crm_client_inspection_types() {
    $out = [];
    try {
        foreach (ops_all("SELECT id, inspection_types FROM business_partners WHERE is_client=1 AND inspection_types <> ''") as $r)
            $out[(int)$r['id']] = array_values(array_filter(explode(',', $r['inspection_types'])));
    } catch (Throwable $e) {}
    return $out;
}
// Contact details to prefill when a client is chosen (§ii).
function crm_client_contacts($cid) {
    if (!$cid) return [];
    try {
        return ops_all("SELECT name, designation, email, mobile, phone, is_primary FROM partner_contacts
                        WHERE partner_id=? ORDER BY is_primary DESC, name", [(int)$cid]);
    } catch (Throwable $e) { return []; }
}
// Addresses on file for a client, offered as a starting point for the site list.
function crm_client_addresses($cid) {
    if (!$cid) return [];
    try {
        return ops_all("SELECT address_type, label, line1, line2, city, state, pincode, country
                        FROM partner_addresses WHERE partner_id=? ORDER BY is_primary DESC, id", [(int)$cid]);
    } catch (Throwable $e) { return []; }
}

// A partner's primary contact + address as JSON, so that picking a customer on
// ANY form (a lead, an opportunity, …) fills the contact/address in by itself,
// exactly the way the quote form does. Deliberately light — it returns only what
// is already on the client card, and is reachable by any signed-in user (route
// is not behind a module gate) so a sales person working only leads can use it.
function ops_partner_contact() {
    header('Content-Type: application/json');
    $pid = (int)($_GET['id'] ?? 0);
    $contacts = function_exists('crm_client_contacts') ? crm_client_contacts($pid) : [];
    $c = $contacts[0] ?? null;
    $addrs = function_exists('crm_client_addresses') ? crm_client_addresses($pid) : [];
    $a = $addrs[0] ?? null;
    echo json_encode([
        'contact' => $c ? [
            'name'   => (string)($c['name'] ?? ''),
            'email'  => (string)($c['email'] ?? ''),
            'mobile' => (string)(($c['mobile'] ?? '') ?: ($c['phone'] ?? '')),
        ] : null,
        'address' => $a ? [
            'label'   => (string)($a['label'] ?? ''),
            'line1'   => (string)($a['line1'] ?? ''),
            'line2'   => (string)($a['line2'] ?? ''),
            'city'    => (string)($a['city'] ?? ''),
            'state'   => (string)($a['state'] ?? ''),
            'pincode' => (string)($a['pincode'] ?? ''),
        ] : null,
        'contacts' => $contacts,
    ]);
}

// ---- The customer's group, for "what have we quoted them before" -----------
// A customer can belong to a group (business_partners.parent_id). When you are
// about to quote one company, the useful history is the whole group's — the
// parent's, the sibling divisions', the subsidiaries' — so you price with the
// full picture and do not undercut or contradict an offer already out there.
// Walks up to the top of the tree, then collects everything under it, with a
// hop cap so a bad parent-of-itself loop cannot spin.
function partner_group_ids($pid) {
    $pid = (int)$pid; if (!$pid) return [];
    try {
        $root = $pid; $seen = [$pid => 1];
        for ($i = 0; $i < 6; $i++) {
            $par = (int)ops_val("SELECT parent_id FROM business_partners WHERE id=?", [$root]);
            if (!$par || isset($seen[$par])) break;
            $seen[$par] = 1; $root = $par;
        }
        $ids = [$root => 1]; $frontier = [$root];
        for ($d = 0; $d < 6 && $frontier; $d++) {
            $in = implode(',', array_fill(0, count($frontier), '?'));
            $kids = ops_all("SELECT id FROM business_partners WHERE parent_id IN ($in)", $frontier);
            $frontier = [];
            foreach ($kids as $k) { $k = (int)$k['id']; if (!isset($ids[$k])) { $ids[$k] = 1; $frontier[] = $k; } }
        }
        $ids[$pid] = 1;
        return array_map('intval', array_keys($ids));
    } catch (Throwable $e) { return [$pid]; }
}

// Every current quotation across that group. is_current only, so it is one row
// per quotation and not one per revision. Newest first.
function quotes_for_group($pid, $excludeQuoteId = 0) {
    $ids = partner_group_ids($pid);
    if (!$ids) return [];
    try {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $args = $ids;
        $sql = "SELECT q.id, q.quote_no, q.rev, q.status, q.total_amount, q.created_at, q.subject, q.client_id,
                       bp.display_name, bp.legal_name
                FROM quotations q LEFT JOIN business_partners bp ON bp.id = q.client_id
                WHERE q.client_id IN ($in) AND q.is_current=1";
        if ($excludeQuoteId) { $sql .= " AND q.id<>?"; $args[] = (int)$excludeQuoteId; }
        $sql .= " ORDER BY q.id DESC LIMIT 50";
        return ops_all($sql, $args);
    } catch (Throwable $e) { return []; }
}

// ---- One rate quotation → contracts for several group companies -------------
//  A rate quotation is agreed once with a group; each group company (a subsidiary
//  with its own GST / billing) may then need ITS OWN contract number at the SAME
//  rates. So beyond the quote's primary contract, additional contracts can be
//  registered under the same quotation for RELATED companies (the group tree). Each
//  is a normal PENDING → endorse → approve → OPEN contract; the quote's primary
//  contract_id is untouched, and every contract points back via quotation_id.

// Every contract registered under a quotation (the primary and any group ones).
function contracts_for_quote($quoteId) {
    return ops_all("SELECT pc.*, b.legal_name partner_legal, b.display_name partner_disp
                    FROM partner_contracts pc LEFT JOIN business_partners b ON b.id=pc.partner_id
                    WHERE pc.quotation_id=? ORDER BY pc.id", [(int)$quoteId]) ?: [];
}
// The group companies eligible for an additional contract under this quote: the
// quote's client and its group tree that are clients and don't already hold a
// contract under this quotation.
function quote_group_contract_candidates($quoteId) {
    $q = ops_one("SELECT client_id FROM quotations WHERE id=?", [(int)$quoteId]);
    if (!$q || empty($q['client_id'])) return [];
    $group = partner_group_ids((int)$q['client_id']);
    if (!$group) return [];
    $have = array_map(fn($c) => (int)$c['partner_id'], contracts_for_quote($quoteId));
    $ids  = array_values(array_diff(array_map('intval', $group), $have));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    return ops_all("SELECT id, legal_name, display_name FROM business_partners
                    WHERE id IN ($in) AND is_client=1 ORDER BY display_name, legal_name", $ids) ?: [];
}
// Register an ADDITIONAL contract under a quote for a related group company. The
// primary contract must already be registered; the company must be in the client's
// group (deliberate — not any client). Mirrors the quote→contract lifecycle.
function crm_add_group_contract($quoteId, $partnerId, array $b = []) {
    $q = ops_one("SELECT * FROM quotations WHERE id=?", [(int)$quoteId]);
    if (!$q) return ['err' => 'That quotation no longer exists.'];
    if (strtoupper((string)($q['status'] ?? '')) !== 'ACCEPTED') return ['err' => 'Additional contracts are registered on a won quotation.'];
    if (empty($q['contract_id'])) return ['err' => 'Register the main contract first, then add group-company contracts under it.'];
    $partnerId = (int)$partnerId;
    if (!$partnerId) return ['err' => 'Pick the group company the contract is for.'];
    $group = array_map('intval', partner_group_ids((int)($q['client_id'] ?? 0)));
    if (!in_array($partnerId, $group, true)) return ['err' => 'That company is not in this client’s group. Link it as a subsidiary / related company on the client record first.'];
    $branchId = (int)($b['branch_id'] ?? 0) ?: (($q['office_id'] ?? null) ?: null);
    $no = trim((string)($b['contract_number'] ?? ''));
    if (!empty($b['auto_contract']) || $no === '') $no = function_exists('gen_contract_number') ? gen_contract_number($branchId) : $no;
    if ($no === '') return ['err' => 'Enter the contract number, or tick auto-generate.'];
    $clash = function_exists('contract_no_clash') ? contract_no_clash($no, $partnerId) : null;
    if ($clash) return ['err' => 'Contract number ' . $no . ' is already registered against ' . ($clash['owner_name'] ?: 'another party') . '.'];
    $ex = ops_one("SELECT id FROM partner_contracts WHERE partner_id=? AND contract_number=?", [$partnerId, $no]);
    if ($ex) { $cid = (int)$ex['id']; }
    else {
        $me = user_name(current_user()); $meId = (int)(current_user()['id'] ?? 0);
        db()->prepare("INSERT INTO partner_contracts (partner_id,contract_number,title,value,start_date,end_date,notes,branch_id,quotation_id,open_status,requested_by,requested_by_id,requested_at,is_active) VALUES (?,?,?,?,?,?,?,?,?, 'PENDING', ?,?,?, 0)")
            ->execute([$partnerId, $no, (string)($q['subject'] ?? ''), (float)($b['value'] ?? 0), (string)($b['start_date'] ?? ''), (string)($b['end_date'] ?? ''),
                       'Group contract under quotation ' . $q['quote_no'] . ' — same rates', $branchId, (int)$quoteId, $me, $meId, date('c')]);
        $cid = (int)db()->lastInsertId();
    }
    if (function_exists('crm_log_change')) crm_log_change((int)$quoteId, 'Group contract ' . $no . ' registered for a related company under this quotation.');
    return ['ok' => true, 'contract_id' => $cid, 'contract_no' => $no];
}

// The group history shaped for JSON — what the quote form draws. The company
// name is blank when the row is the same customer, and named when it is a group
// company, so a reader sees at a glance which are the subsidiaries'.
function quote_history_json($cid, $excludeQuoteId = 0) {
    $cid = (int)$cid;
    $out = [];
    foreach (quotes_for_group($cid, $excludeQuoteId) as $h) {
        $out[] = [
            'id'      => (int)$h['id'],
            'no'      => $h['quote_no'] . ((int)$h['rev'] ? ' r' . (int)$h['rev'] : ''),
            'subject' => (string)$h['subject'],
            'value'   => function_exists('fmoney') ? fmoney($h['total_amount']) : (string)$h['total_amount'],
            'status'  => (string)$h['status'],
            'when'    => function_exists('fdate') ? fdate(substr((string)$h['created_at'], 0, 10)) : substr((string)$h['created_at'], 0, 10),
            'company' => ((int)$h['client_id'] === $cid) ? '' : (string)($h['display_name'] ?: $h['legal_name']),
        ];
    }
    return $out;
}

// ---- Change log (§xxi) ------------------------------------------------------
// Every save appends to the same history the revisions use, so "what changed"
// and "the final copy" are answered from one place.
function crm_log_change($qid, $summary, $detail = null) {
    $q = crm_quote_get((int)$qid); if (!$q) return;
    $base = (int)($q['parent_id'] ?: $q['id']);
    $snap = $detail ?? ['header' => $q, 'lines' => crm_quote_lines($q['id']), 'locations' => crm_quote_locations($q['id'])];
    try {
        db()->prepare("INSERT INTO quote_revisions (quote_id,rev,changed_by,changed_at,summary,snapshot) VALUES (?,?,?,?,?,?)")
            ->execute([$base, (int)$q['rev'], user_name(current_user()), date('c'), $summary, json_encode($snap)]);
    } catch (Throwable $e) {}
}
// Field-by-field difference between two snapshots, in plain English.
function crm_diff_snapshots($oldSnap, $newSnap) {
    $o = is_array($oldSnap) ? ($oldSnap['header'] ?? []) : [];
    $n = is_array($newSnap) ? ($newSnap['header'] ?? []) : [];
    $skip = ['updated_at' => 1, 'created_at' => 1, 'id' => 1, 'subtotal' => 1, 'gst_amount' => 1];
    $out = [];
    foreach ($n as $k => $v) {
        if (isset($skip[$k])) continue;
        $ov = $o[$k] ?? null;
        if ((string)$ov === (string)$v) continue;
        $out[] = ['field' => $k, 'from' => (string)$ov, 'to' => (string)$v];
    }
    return $out;
}

// ---- Locking a closed quote (§xix) -----------------------------------------
// Accepted or lost quotes are read-only. The Super Admin can grant a time-boxed
// unlock, and only in answer to a request raised in the system.
function quote_is_locked($q) {
    if (!$q) return false;
    $status = (string)($q['status'] ?? '');
    // Out of the building. Whatever the customer is holding in their inbox is
    // what this quotation says, and no amount of seniority makes editing it
    // afterwards honest. Not even the Master Admin — the way back is a revision,
    // which is a new attempt with its own number and its own approval.
    if ($status === 'SENT' || trim((string)($q['sent_at'] ?? '')) !== '') return true;
    if (is_master()) return false;
    $closed = in_array($status, ['ACCEPTED', 'LOST'], true);
    if (!$closed) return false;
    $until = $q['unlocked_until'] ?? '';
    if ($until !== '' && strtotime($until) > time()) return false;   // an unlock is live
    return true;
}
// Why it is locked, in the words the person needs.
function quote_lock_reason($q) {
    if (!quote_is_locked($q)) return '';
    if (($q['status'] ?? '') === 'SENT' || trim((string)($q['sent_at'] ?? '')) !== '')
        return 'This ' . Tl('quote') . ' has gone to the ' . Tl('client')
             . (trim((string)($q['sent_at'] ?? '')) !== '' ? ' on ' . fdate(substr((string)$q['sent_at'], 0, 10)) : '')
             . ', so nothing on it can be changed. To change the price or the scope, raise a revision: '
             . 'it takes a new revision number, goes through approval again, and is sent again.';
    return 'This ' . Tl('quote') . ' is closed (' . ($q['status'] ?? '') . ') and is locked. '
         . 'Raise a re-edit request — only the Super Admin can grant it.';
}

// ---- Taking an approval back (§ approver pressed the wrong button) ---------
// Everybody approves the wrong thing once. While the quotation is still inside
// the building that is a mistake, not an event: the approver takes it back and
// it goes to whoever it was waiting for. The moment it has gone to the customer
// it stops being a mistake and becomes history, and history is not edited.
function quote_can_retract($q) {
    if (!$q) return false;
    if (quote_is_locked($q)) return false;                       // gone out, or closed
    return in_array((string)($q['status'] ?? ''), ['APPROVED', 'PENDING_APPROVAL'], true);
}
// This approver's own approved step, if they have one to take back.
function quote_my_approved_step($qid) {
    $u = current_user();
    $me = user_name($u);
    foreach (ops_all("SELECT * FROM quote_approvals WHERE quote_id=? AND status='APPROVED' ORDER BY level DESC", [(int)$qid]) as $st) {
        if (is_master()) return $st;
        if (!empty($st['approver_user_id']) && (int)$st['approver_user_id'] === (int)($u['id'] ?? 0)) return $st;
        if (trim((string)($st['acted_by'] ?? '')) !== '' && $st['acted_by'] === $me) return $st;
    }
    return null;
}
function quote_edit_request_open($qid) {
    return ops_one("SELECT * FROM quote_edit_requests WHERE quote_id=? AND status='PENDING' ORDER BY id DESC", [(int)$qid]);
}

// ---- Sites (§iv, §viii, §xi) ----------------------------------------------
function crm_quote_locations($qid) {
    return $qid ? ops_all("SELECT * FROM quote_locations WHERE quote_id=? ORDER BY sort_order, id", [(int)$qid]) : [];
}
// One-line rendering of an address, for tables, the PDF and the ops packet.
function quote_location_line($L) {
    $parts = array_filter([$L['line1'] ?? '', $L['line2'] ?? '', $L['city'] ?? '',
        trim(($L['state'] ?? '') . ' ' . ($L['pincode'] ?? '')), $L['country'] ?? ''], fn($x) => trim((string)$x) !== '');
    return implode(', ', array_map('trim', $parts));
}
function quote_location_label($L) {
    $t = lk_options_or('site_location_type', SITE_LOCATION_TYPES)[$L['location_type'] ?? ''] ?? ($L['location_type'] ?? '');
    return trim(($L['label'] ?: $t) . ($L['city'] ? ' — ' . $L['city'] : ''));
}
function crm_save_locations($qid, $b) {
    $qid = (int)$qid;
    $ids = (array)($b['loc_id'] ?? []); $lab = (array)($b['loc_label'] ?? []);
    $lt = (array)($b['loc_type'] ?? []); $l1 = (array)($b['loc_line1'] ?? []); $l2 = (array)($b['loc_line2'] ?? []);
    $city = (array)($b['loc_city'] ?? []); $st = (array)($b['loc_state'] ?? []); $pin = (array)($b['loc_pin'] ?? []);
    $ctry = (array)($b['loc_country'] ?? []); $cn = (array)($b['loc_contact'] ?? []); $cm = (array)($b['loc_mobile'] ?? []);
    $off = (array)($b['loc_office'] ?? []);
    $ven = (array)($b['loc_vendor'] ?? []);   // the vendor whose site this is (optional)
    $keep = [];
    $upd = db()->prepare("UPDATE quote_locations SET label=?,location_type=?,line1=?,line2=?,city=?,state=?,pincode=?,country=?,contact_name=?,contact_mobile=?,office_id=?,vendor_id=?,sort_order=? WHERE id=? AND quote_id=?");
    $ins = db()->prepare("INSERT INTO quote_locations (quote_id,label,location_type,line1,line2,city,state,pincode,country,contact_name,contact_mobile,office_id,vendor_id,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $n = 0;
    foreach ($l1 as $i => $line1) {
        $line1 = trim((string)$line1); $c = trim((string)($city[$i] ?? ''));
        $vid = (int)($ven[$i] ?? 0) ?: null;
        // Keep a row that has an address, a label, OR a chosen vendor — the last
        // lets a "site to be confirmed" row (a known vendor, address still open)
        // be saved so the quote can list every vendor even before sites are fixed.
        if ($line1 === '' && $c === '' && trim((string)($lab[$i] ?? '')) === '' && !$vid) continue;
        $args = [trim((string)($lab[$i] ?? '')), $lt[$i] ?? 'SITE', $line1, trim((string)($l2[$i] ?? '')), $c,
            trim((string)($st[$i] ?? '')), trim((string)($pin[$i] ?? '')), trim((string)($ctry[$i] ?? '')) ?: 'India',
            trim((string)($cn[$i] ?? '')), trim((string)($cm[$i] ?? '')), (int)($off[$i] ?? 0) ?: null, $vid, $n++];
        $existing = (int)($ids[$i] ?? 0);
        if ($existing) { $upd->execute(array_merge($args, [$existing, $qid])); $keep[] = $existing; }
        else { $ins->execute(array_merge([$qid], $args)); $keep[] = (int)db()->lastInsertId(); }
    }
    // Anything the user removed from the form goes, and lines pointing at it are freed.
    $all = ops_all("SELECT id FROM quote_locations WHERE quote_id=?", [$qid]);
    foreach ($all as $r) {
        if (in_array((int)$r['id'], $keep, true)) continue;
        db()->prepare("UPDATE quote_lines SET location_id=NULL WHERE location_id=?")->execute([(int)$r['id']]);
        db()->prepare("DELETE FROM quote_locations WHERE id=?")->execute([(int)$r['id']]);
    }
    return $keep;
}
// Schedule the §11 follow-up cadence (3/6/9 days, fortnight, month) from the sent date.
// Save the `l_*[]` line-item rows a form posted. Blank rows are dropped, so a
// form can offer more rows than anyone fills. Returns how many were written.
function crm_save_lines_from_post($quoteId, $b) {
    $desc = (array)($b['l_desc'] ?? []);
    if (!$desc) return 0;
    $svc  = (array)($b['l_service'] ?? []);
    $qty  = (array)($b['l_qty'] ?? []);
    $unit = (array)($b['l_unit'] ?? []);
    $rate = (array)($b['l_rate'] ?? []);
    $amt  = (array)($b['l_amount'] ?? []);
    $ins = db()->prepare("INSERT INTO quote_lines (quote_id,line_no,sbu,service_type,description,qty,unit,rate,amount)
                          VALUES (?,?,?,?,?,?,?,?,?)");
    $n = 0;
    foreach ($desc as $i => $d) {
        $d = trim((string)$d);
        $q = (float)($qty[$i] ?? 0);
        $r = (float)($rate[$i] ?? 0);
        if ($d === '' && $q == 0 && $r == 0) continue;      // an untouched row
        $a = (float)($amt[$i] ?? 0) ?: ($q * $r);
        $n++;
        $ins->execute([(int)$quoteId, $n, trim((string)($b['sbu'] ?? '')), trim((string)($svc[$i] ?? '')),
                       $d, $q, trim((string)($unit[$i] ?? '')), $r, $a]);
    }
    return $n;
}

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
        if ($st !== '' && isset(lk_options_or('inquiry_status', INQUIRY_STATUS)[$st])) { $w[] = 'i.status=?'; $args[] = $st; }
        if ($q !== '') { $w[] = '(i.inquiry_no LIKE ? OR i.subject LIKE ? OR i.client_name LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
        $sb = scope_sbus();
        if ($sb !== 'ALL' && is_array($sb) && $sb) { $ph = implode(',', array_fill(0, count($sb), '?')); $w[] = "(i.sbu IN ($ph) OR i.sbu='')"; foreach ($sb as $s) $args[] = $s; }
        // Financial-year window (defaults to the current FY; "All years" removes it).
        $fy = fy_filter();
        if (!$fy['all']) { $w[] = 'i.created_at >= ?'; $args[] = $fy['from']; $w[] = 'i.created_at < ?'; $args[] = $fy['to_excl']; }
        // origin_ref = the lead this inquiry was converted from (if any), so the
        // register shows where it came from.
        $rows = ops_all("SELECT i.*, bp.display_name client_disp, l.ref origin_ref
                         FROM crm_inquiries i
                         LEFT JOIN business_partners bp ON bp.id=i.client_id
                         LEFT JOIN leads l ON l.id=i.lead_id
                         WHERE " . implode(' AND ', $w) . " ORDER BY i.id DESC", $args);
        view('ops/crm/inquiry_list', ['rows' => $rows, 'q' => $q, 'st' => $st, 'fy' => $fy['fy']]); return;
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
            // An inquiry with no client, no contact and no subject is not a
            // record of anything — it is a blank row somebody has to chase later.
            // So the few things that make it a real enquiry are required. Email
            // OR mobile satisfies "how do we reach them", so a genuine phone
            // enquiry with no e-mail yet is not blocked.
            $need = [];
            if (!$cid && trim($b['client_name'] ?? '') === '') $need[] = 'a ' . Tl('client') . ' (pick one, or type a name)';
            if (trim($b['contact_name'] ?? '') === '')          $need[] = 'the contact person';
            if (trim($b['contact_email'] ?? '') === '' && trim($b['contact_mobile'] ?? '') === '')
                                                                $need[] = 'a contact e-mail or mobile';
            if (($b['sbu'] ?? '') === '')                        $need[] = 'the ' . Tl('sbu') . ' (business unit)';
            if (trim($b['subject'] ?? '') === '')               $need[] = 'a subject';
            if ($need) {
                view('ops/crm/inquiry_form', ['inq' => ($inq ? array_merge($inq, $b) : $b), 'error' => 'Please add ' . implode('; ', $need) . '.',
                    'clients' => clients_list(), 'sbuOpts' => lk_options_or('sbu', OPS_SBUS),
                    'sourceOpts' => INQUIRY_SOURCES, 'statusOpts' => INQUIRY_STATUS,
                    'users' => ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name, username")]);
                return;
            }
            if ($inq) {
                $pdo->prepare("UPDATE crm_inquiries SET client_id=?,client_name=?,contact_name=?,contact_email=?,contact_mobile=?,subject=?,service_requirement=?,sbu=?,source=?,received_date=?,assigned_to=?,status=?,notes=? WHERE id=?")
                    ->execute(array_merge($vals, [$inq['id']]));
                if (function_exists('ads_queue_inquiry')) ads_queue_inquiry((int)$inq['id'], 'Enquiry edited');
                flash('Inquiry updated.'); redirect('/inquiries');
            } else {
                $no = crm_next_inquiry_no();
                $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no,client_id,client_name,contact_name,contact_email,contact_mobile,subject,service_requirement,sbu,source,received_date,assigned_to,status,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute(array_merge([$no], $vals, [user_name(current_user()), date('c')]));
                // Ads Pro is told an enquiry exists so it can stop advertising to
                // somebody who has already asked us for a price. Queued, not sent.
                if (function_exists('ads_queue_inquiry')) ads_queue_inquiry((int)$pdo->lastInsertId(), 'Enquiry raised');
                flash("$no created. You can now raise a quotation against it.");
                redirect('/inquiries');
            }
        }
        $originRef = ($inq && !empty($inq['lead_id'])) ? (string)ops_val("SELECT ref FROM leads WHERE id=?", [(int)$inq['lead_id']]) : '';
        view('ops/crm/inquiry_form', ['inq' => $inq, 'originRef' => $originRef, 'clients' => clients_list(), 'sbuOpts' => lk_options_or('sbu', OPS_SBUS),
            'sourceOpts' => INQUIRY_SOURCES, 'statusOpts' => INQUIRY_STATUS,
            'users' => ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name, username")]);
        return;
    }
    // Deleting an inquiry — administrators only, and never once a quotation has
    // been raised against it (that would orphan the quote and lose the trail).
    if ($route === 'inquiry-delete' && $method === 'POST') {
        ops_require(is_admin_level() || is_master(), 'Only an administrator can delete inquiries.');
        $id  = (int)($_POST['id'] ?? 0);
        $inq = $id ? crm_inquiry_get($id) : null;
        if (!$inq) { flash('That inquiry no longer exists.', 'error'); redirect('/inquiries'); }
        $quotes = (int)ops_val("SELECT COUNT(*) FROM quotations WHERE inquiry_id=?", [$id]);
        if ($quotes) {
            flash('A ' . Tl('quote') . ' has been raised against ' . $inq['inquiry_no'] . ', so it is part of the record and cannot be deleted. Set its status to Dropped instead.', 'error');
            redirect('/inquiries');
        }
        if (function_exists('act_log')) act_log('INQUIRY', $id, 'DELETED', 'Inquiry ' . $inq['inquiry_no'] . ' deleted.');
        db()->prepare("DELETE FROM crm_inquiries WHERE id=?")->execute([$id]);
        flash('Inquiry ' . $inq['inquiry_no'] . ' deleted.');
        redirect('/inquiries');
    }
}

// ---------------------------------------------------------------------------
//  Handlers — Quotations (§2, §3, §4, §14, §23)
// ---------------------------------------------------------------------------
function ops_crm_quotes($route, $method) {
    $pdo = db();
    if ($route === 'quotes') {
        // "Awaiting my approval" — the exact set the dashboard's "quotes to
        // approve" counts. Routed to me (by name, role, or a generic step I may
        // sign); NO office/FY scope, because the routing is the authority — so
        // the list can never disagree with the dashboard count.
        if (($_GET['mine'] ?? '') === 'approve') {
            $u = current_user(); $me = (int)($u['id'] ?? 0); $role = (string)user_role();
            $canApprove = can('crm.quote.approve') || is_master();
            $rows = ops_all(
                "SELECT q.*, bp.display_name client_disp, o.name office_name
                 FROM quotations q
                 LEFT JOIN business_partners bp ON bp.id=q.client_id
                 LEFT JOIN offices o ON o.id=q.office_id
                 JOIN quote_approvals a ON a.quote_id=q.id AND a.status='PENDING'
                      AND a.level=(SELECT MIN(a2.level) FROM quote_approvals a2 WHERE a2.quote_id=q.id AND a2.status='PENDING')
                 WHERE q.is_current=1 AND q.status='PENDING_APPROVAL'
                   AND ( a.approver_user_id=?
                         OR (a.approver_role=? AND (a.approver_user_id IS NULL OR a.approver_user_id=0))
                         OR ((a.approver_role='' OR a.approver_role IS NULL)
                             AND (a.approver_user_id IS NULL OR a.approver_user_id=0) AND ?) )
                 GROUP BY q.id ORDER BY q.id DESC", [$me, $role, $canApprove ? 1 : 0]);
            view('ops/crm/quote_list', ['rows' => $rows, 'view' => 'pending', 'q' => '',
                'counts' => ['open' => 0, 'pending' => count($rows), 'closed' => 0, 'lost' => 0],
                'fy' => current_fy(), 'mineApprove' => true]);
            return;
        }
        // "Awaiting a contract number" — accepted quotes Accounts still has to
        // register. Same scope as quotes_awaiting_contract_count(), so the list
        // matches the dashboard task.
        if (($_GET['mine'] ?? '') === 'contract') {
            [$cw, $ca] = scope_clause('q.office_id', 'q.sbu');
            $rows = ops_all(
                "SELECT q.*, bp.display_name client_disp, o.name office_name FROM quotations q
                 LEFT JOIN business_partners bp ON bp.id=q.client_id
                 LEFT JOIN offices o ON o.id=q.office_id
                 WHERE q.is_current=1 AND q.status='ACCEPTED'
                   AND (q.contract_number IS NULL OR q.contract_number='') AND $cw
                 ORDER BY q.accepted_date, q.id", $ca);
            view('ops/crm/quote_list', ['rows' => $rows, 'view' => 'closed', 'q' => '',
                'counts' => ['open' => 0, 'pending' => 0, 'closed' => count($rows), 'lost' => 0],
                'fy' => current_fy(), 'mineContract' => true]);
            return;
        }
        $view = $_GET['v'] ?? 'all';   // all | open | pending | closed | lost
        $stateSets = ['open' => QUOTE_OPEN_STATES, 'pending' => QUOTE_PENDING_STATES, 'closed' => QUOTE_CLOSED_STATES, 'lost' => ['LOST', 'EXPIRED']];
        [$scopeW, $args] = scope_clause('q.office_id', 'q.sbu');
        $w = [$scopeW, 'q.is_current=1'];
        if (isset($stateSets[$view])) { $set = $stateSets[$view]; $ph = implode(',', array_fill(0, count($set), '?')); $w[] = "q.status IN ($ph)"; foreach ($set as $s) $args[] = $s; }
        $qq = trim($_GET['q'] ?? '');
        if ($qq !== '') { $w[] = '(q.quote_no LIKE ? OR q.subject LIKE ? OR q.client_name LIKE ?)'; array_push($args, "%$qq%", "%$qq%", "%$qq%"); }
        // Financial-year window (defaults to the current FY; "All years" removes it).
        $fy = fy_filter();
        [$fyW, $fyA] = fy_sql('q.created_at', $fy);
        $rows = ops_all("SELECT q.*, bp.display_name client_disp, o.name office_name FROM quotations q
            LEFT JOIN business_partners bp ON bp.id=q.client_id LEFT JOIN offices o ON o.id=q.office_id
            WHERE " . implode(' AND ', $w) . $fyW . " ORDER BY q.id DESC", array_merge($args, $fyA));
        // headline counts (scope-respecting, and within the same FY window)
        $counts = [];
        foreach (['open', 'pending', 'closed', 'lost'] as $k) {
            [$sw, $sa] = scope_clause('q.office_id', 'q.sbu'); $set = $stateSets[$k]; $ph = implode(',', array_fill(0, count($set), '?'));
            foreach ($set as $s) $sa[] = $s;
            $counts[$k] = (int)ops_val("SELECT COUNT(*) FROM quotations q WHERE $sw AND q.is_current=1 AND q.status IN ($ph)" . $fyW, array_merge($sa, $fyA));
        }
        // §xxii — the same rows the screen shows, as a spreadsheet.
        if (($_GET['export'] ?? '') === '1') { csv_download('quotes-' . date('Y-m-d') . '.csv', crm_quotes_export($rows)); return; }
        view('ops/crm/quote_list', ['rows' => $rows, 'view' => $view, 'q' => $qq, 'counts' => $counts, 'fy' => $fy['fy']]); return;
    }
    // §xv — a quote we did not write (client / tender portal) still belongs in the
    // register. Same record, marked with where it came from, so the win/loss and
    // follow-up numbers stay honest.
    if ($route === 'quote-external') {
        ops_require(can('crm.quote.create') || is_master(), 'You cannot add ' . Tlp('quote') . '.');
        if ($method === 'POST') {
            $b = $_POST;
            $cid = ($b['client_id'] ?? '') !== '' ? (int)$b['client_id'] : null;
            $no = trim($b['quote_no'] ?? '') ?: crm_next_quote_no();
            // §3 — an external quotation carries the number the client or the
            // portal gave it, so it is the one place a duplicate can be typed in.
            if (quote_no_taken($no, 0)) {
                flash(ucfirst(Tl('quote')) . ' number ' . $no . ' is already on the register. '
                    . 'Every ' . Tl('quote') . ' number must be unique — check the register, or leave the box empty '
                    . 'and one will be allotted.', 'error');
                redirect('/quote-external');
            }
            $cols = ['quote_no','rev','is_current','status','origin','origin_portal','origin_ref','client_id','client_name',
                'contact_name','contact_email','contact_mobile','sbu','office_id','subject','product_category',
                'inspection_types','total_amount','subtotal','gst_pct','payment_terms','sent_at','owner_id','created_by','created_at'];
            $sub = (float)($b['total_amount'] ?? 0);
            $vals = [$no, 0, 1, $b['status'] ?? 'SENT', $b['origin'] ?? 'CLIENT_PORTAL', trim($b['origin_portal'] ?? ''), trim($b['origin_ref'] ?? ''),
                $cid, $cid ? crm_client_name($cid) : trim($b['client_name'] ?? ''),
                trim($b['contact_name'] ?? ''), trim($b['contact_email'] ?? ''), trim($b['contact_mobile'] ?? ''),
                $b['sbu'] ?? '', ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : null, trim($b['subject'] ?? ''),
                product_category_canon($b['product_category'] ?? ''),
                implode(',', array_filter((array)($b['inspection_types'] ?? []))),
                $sub, $sub, 0, trim($b['payment_terms'] ?? ''),
                trim($b['submitted_on'] ?? '') ?: date('c'), current_user()['id'] ?? null, user_name(current_user()), date('c')];
            $pdo->prepare("INSERT INTO quotations (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute($vals);
            $id = (int)$pdo->lastInsertId();
            // Line items on an external quote too. Without them the quantities an
            // open order is drawn down against do not exist, so nothing can tell
            // that an ARC won through a portal has been served out.
            $nLines = crm_save_lines_from_post($id, $b);
            crm_log_change($id, 'Registered from ' . (lk_options_or('quote_origin', QUOTE_ORIGINS)[$b['origin'] ?? ''] ?? 'an external portal')
                . ($nLines ? ' with ' . $nLines . ' line item(s)' : ''));
            if (($b['status'] ?? '') === 'SENT') crm_schedule_followups($id);
            flash("$no registered.");
            redirect('/quote?id=' . $id);
        }
        view('ops/crm/quote_external', [
            'clients' => clients_list(), 'offices' => offices_list(),
            'sbuOpts' => lk_options_or('sbu', OPS_SBUS), 'origins' => lk_options_or('quote_origin', QUOTE_ORIGINS),
            'svcOpts' => lk_options_or('inspection_type', INSPECTION_TYPES),
            'payTerms' => lk_options_or('payment_term', PAYMENT_TERMS),
            'statuses' => lk_options_or('quote_status', QUOTE_STATUS), 'prodCats' => product_categories_all(),
            'unitOpts' => lk_options_or('charge_unit', CHARGE_UNITS),
        ]);
        return;
    }
    // §ii — contact + addresses for the client just picked on the quote form.
    if ($route === 'quote-client') {
        header('Content-Type: application/json');
        $cid = (int)($_GET['id'] ?? 0);
        $contacts = crm_client_contacts($cid);
        $primary = $contacts[0] ?? null;
        // The commercial terms agreed with this customer on the master, so the
        // quote starts from what was already settled instead of being re-typed.
        $cli = ops_one("SELECT payment_terms, credit_days, gstin, state, home_branch_id FROM business_partners WHERE id=?", [$cid]);
        echo json_encode([
            'contact' => $primary ? [
                'name'   => $primary['name'] ?? '',
                'email'  => $primary['email'] ?? '',
                'mobile' => ($primary['mobile'] ?? '') ?: ($primary['phone'] ?? ''),
            ] : null,
            'contacts'  => $contacts,
            // The client's base/home office — line items default to it.
            'home_office' => $cli && $cli['home_branch_id'] !== null ? (string)$cli['home_branch_id'] : '',
            'commercials' => $cli ? [
                'payment_terms' => (string)($cli['payment_terms'] ?? ''),
                'credit_days'   => (string)($cli['credit_days'] ?? ''),
                'gstin'         => (string)($cli['gstin'] ?? ''),
                'state'         => (string)($cli['state'] ?? ''),
            ] : null,
            'addresses' => crm_client_addresses($cid),
            // What we have already quoted this customer's whole group. The one
            // being edited, if any, is left out so it does not list itself.
            'history'   => quote_history_json($cid, (int)($_GET['exclude'] ?? 0)),
        ]);
        return;
    }
    if ($route === 'partner-address') {
        // The registered address and a contact for a single partner (a vendor
        // picked on a quote site row, a manufacturer, etc.), so its site details
        // fill in by themselves instead of being copied by hand.
        header('Content-Type: application/json');
        $pid = (int)($_GET['id'] ?? 0);
        $addrs = crm_client_addresses($pid);
        $primary = $addrs[0] ?? null;
        $contacts = function_exists('crm_client_contacts') ? crm_client_contacts($pid) : [];
        $c = $contacts[0] ?? null;
        echo json_encode([
            'address' => $primary ? [
                'label'   => (string)($primary['label'] ?? ''),
                'line1'   => (string)($primary['line1'] ?? ''),
                'line2'   => (string)($primary['line2'] ?? ''),
                'city'    => (string)($primary['city'] ?? ''),
                'state'   => (string)($primary['state'] ?? ''),
                'pincode' => (string)($primary['pincode'] ?? ''),
                'country' => (string)($primary['country'] ?? 'India'),
            ] : null,
            'contact' => $c ? [
                'name'   => (string)($c['name'] ?? ''),
                'mobile' => (string)(($c['mobile'] ?? '') ?: ($c['phone'] ?? '')),
            ] : null,
        ]);
        return;
    }
    if ($route === 'quote-new' || $route === 'quote-edit') {
        ops_require(can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes'), 'You cannot create or edit quotations.');
        $q = null;
        if ($route === 'quote-edit') {
            $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
            // §xix — an accepted or lost quote is read-only until an unlock is granted.
            if (quote_is_locked($q)) {
                flash('This ' . Tl('quote') . ' is ' . strtolower(lk_options_or('quote_status', QUOTE_STATUS)[$q['status']] ?? $q['status'])
                    . ' and is locked. Raise a re-edit request from the ' . Tl('quote') . ' page — only the Super Admin can grant it.', 'error');
                redirect('/quote?id=' . $q['id']);
            }
        }
        if ($method === 'POST') {
            $b = $_POST;
            $cid = ($b['client_id'] ?? '') !== '' ? (int)$b['client_id'] : null;
            // Pre-order control: a BLOCKED client stops a non-manager from quoting
            // (a manager may proceed but is warned); HOLD always warns, never stops.
            if ($cid && function_exists('partner_hold')) {
                $ph = partner_hold($cid);
                if ($ph && partner_hold_blocks($cid)) {
                    flash('This ' . Tl('client') . ' is BLOCKED (' . $ph['reason'] . '). A ' . Tl('quote') . ' cannot be raised for them until a manager clears the block under Client holds.', 'error');
                    redirect($q ? '/quote-edit?id=' . $q['id'] : '/quote-new');
                }
                if ($ph) flash('Note: this ' . Tl('client') . ' is ' . $ph['label'] . ' — ' . $ph['reason'], 'warning');
            }
            // §i — when a client is picked, the free-text name is ignored entirely.
            $hdr = [
                'client_id' => $cid, 'client_name' => $cid ? crm_client_name($cid) : trim($b['client_name'] ?? ''),
                'contact_name' => trim($b['contact_name'] ?? ''), 'contact_email' => trim($b['contact_email'] ?? ''), 'contact_mobile' => trim($b['contact_mobile'] ?? ''),
                'sbu' => $b['sbu'] ?? '', 'office_id' => ($b['office_id'] ?? '') !== '' ? (int)$b['office_id'] : null, 'subject' => trim($b['subject'] ?? ''),
                // Executing offices: the header office is primary, plus any others (§iii).
                'exec_office_ids' => implode(',', array_map('intval', array_filter((array)($b['exec_office_ids'] ?? [])))),
                'inspection_types' => implode(',', array_filter((array)($b['inspection_types'] ?? []))),
                'product_category' => product_category_canon($b['product_category'] ?? ''),
                'origin' => $b['origin'] ?? 'OWN', 'origin_portal' => trim($b['origin_portal'] ?? ''),
                'origin_ref' => trim($b['origin_ref'] ?? ''),
                'terms_conditions' => (string)($b['terms_conditions'] ?? ''),
                'signatory_user_id' => ($b['signatory_user_id'] ?? '') !== '' ? (int)$b['signatory_user_id'] : null,
                'site_location' => trim($b['site_location'] ?? ''), 'location_type' => $b['location_type'] ?? 'REGISTERED',
                // The manufacturer / vendor site the inspection is at. Captured on
                // the quote so raising the work-order carries it straight through,
                // instead of the site being re-chosen (or lost) at the call.
                'vendor_id' => ($b['vendor_id'] ?? '') !== '' ? (int)$b['vendor_id'] : null,
                'currency' => $b['currency'] ?? 'INR', 'validity_days' => (int)($b['validity_days'] ?? 30),
                'payment_terms' => trim($b['payment_terms'] ?? ''), 'advance_pct' => (float)($b['advance_pct'] ?? 0),
                'advance_required' => !empty($b['advance_required']) ? 1 : 0, 'report_vs_payment' => !empty($b['report_vs_payment']) ? 1 : 0,
                'gst_pct' => (float)($b['gst_pct'] ?? 18),
            ];
            if ($q) {
                $set = implode(',', array_map(fn($k) => "$k=?", array_keys($hdr)));
                $pdo->prepare("UPDATE quotations SET $set, updated_at=? WHERE id=?")->execute(array_merge(array_values($hdr), [date('c'), $q['id']]));
                // Sites first — line items point at them by id.
                crm_save_locations($q['id'], $b);
                crm_save_lines($q['id'], $b); crm_quote_recalc($q['id']);
                crm_log_change($q['id'], 'Edited');
                flash(ucfirst(Tl('quote')) . ' saved.'); redirect('/quote?id=' . $q['id']);
            } else {
                $no = crm_next_quote_no();
                $inqId = ($b['inquiry_id'] ?? '') !== '' ? (int)$b['inquiry_id'] : null;
                // The lead this was raised from, so it shows back on that lead.
                $leadId = ($b['lead_id'] ?? '') !== '' ? (int)$b['lead_id'] : null;
                $cols = array_merge(['quote_no', 'rev', 'is_current', 'inquiry_id', 'lead_id', 'status', 'owner_id', 'created_by', 'created_at'], array_keys($hdr));
                $vals = array_merge([$no, 0, 1, $inqId, $leadId, 'DRAFT', current_user()['id'] ?? null, user_name(current_user()), date('c')], array_values($hdr));
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO quotations (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
                $id = $pdo->lastInsertId();
                crm_save_locations($id, $b);
                crm_save_lines($id, $b); crm_quote_recalc($id);
                if ($inqId) $pdo->prepare("UPDATE crm_inquiries SET status='QUOTED' WHERE id=?")->execute([$inqId]);
                // Carry the lead's attachments onto the quote, so the customer's
                // RFQ, specs and correspondence stay for future reference.
                if ($leadId && function_exists('lead_files_carry_to_quote')) {
                    $moved = lead_files_carry_to_quote($leadId, (int)$id);
                    if ($moved) crm_log_change((int)$id, $moved . ' file(s) carried over from the lead');
                }
                flash("$no created."); redirect('/quote?id=' . $id);
            }
        }
        $preInq = (!$q && ($_GET['inquiry'] ?? '') !== '') ? crm_inquiry_get((int)$_GET['inquiry']) : null;
        // Quoting straight off a lead. The lead carries the company, the contact
        // and the requirement, so none of it is re-typed; if the lead is linked
        // to a customer on the master, that customer is pre-selected.
        $preLead = null;
        if (!$q && ($_GET['lead'] ?? '') !== '' && function_exists('lead_row')) {
            $ld = lead_row((int)$_GET['lead']);
            if ($ld) {
                $subj = trim(preg_replace('/\s+/', ' ', (string)$ld['requirement']));
                if (mb_strlen($subj) > 180) $subj = mb_substr($subj, 0, 177) . '...';
                $preLead = [
                    'id' => (int)$ld['id'], 'ref' => (string)$ld['ref'],
                    'client_id' => (int)($ld['partner_id'] ?? 0) ?: '',
                    'client_name' => (int)($ld['partner_id'] ?? 0) ? '' : (string)$ld['company_name'],
                    'contact_name' => (string)$ld['contact_name'], 'contact_email' => (string)$ld['contact_email'],
                    'contact_mobile' => (string)$ld['contact_phone'], 'sbu' => (string)($ld['sbu'] ?? ''),
                    'subject' => $subj !== '' ? $subj : ('From lead ' . $ld['ref']),
                ];
            }
        }
        view('ops/crm/quote_form', ['q' => $q, 'lines' => $q ? crm_quote_lines($q['id']) : [], 'preInq' => $preInq, 'preLead' => $preLead,
            'locations' => $q ? crm_quote_locations($q['id']) : [],
            'clients' => clients_list(), 'vendors' => vendors_list(), 'offices' => offices_list(), 'sbuOpts' => lk_options_or('sbu', OPS_SBUS),
            'svcOpts' => lk_options_or('inspection_type', INSPECTION_TYPES), 'unitOpts' => lk_options_or('charge_unit', CHARGE_UNITS),
            'orderOpts' => lk_options_or('order_type', ORDER_TYPES), 'locTypes' => lk_options_or('site_location_type', SITE_LOCATION_TYPES),
            'payTerms' => lk_options_or('payment_term', PAYMENT_TERMS), 'origins' => lk_options_or('quote_origin', QUOTE_ORIGINS),
            'actBySbu' => activity_options_by_sbu(), 'prodCats' => product_categories_all(),
            'signatories' => crm_signatories(), 'defaultTerms' => crm_default_terms(),
            'clientTypes' => crm_client_inspection_types(),
            'delivOpts' => deliverable_options()]);
        return;
    }
    if ($route === 'quote') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        $base = (int)($q['parent_id'] ?: $q['id']);
        view('ops/crm/quote_detail', [
            'q' => $q, 'lines' => crm_quote_lines($q['id']),
            'preorderOn' => preorder_checklist_enabled(), 'preorderItems' => preorder_checklist_items(),
            'preorderState' => preorder_checklist_state($q), 'preorderRequireAll' => preorder_checklist_require_all(),
            'revs' => ops_all("SELECT id, rev, status, total_amount, is_current, created_at FROM quotations WHERE quote_no=? ORDER BY rev", [$q['quote_no']]),
            'hist' => ops_all("SELECT * FROM quote_revisions WHERE quote_id=? ORDER BY rev DESC, id DESC", [$base]),
            'followups' => ops_all("SELECT * FROM quote_followups WHERE quote_id=? ORDER BY due_date", [$q['id']]),
            'approvals' => crm_approvals($q['id']),
            'lostReasons' => lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS),
            'canApprove' => can('crm.quote.approve') || is_master(), 'canSend' => can('crm.quote.send') || is_master(),
            'canEdit' => (can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes')) && !quote_is_locked($q),
            // Revising is the way FORWARD from a sent quotation, so it must not
            // be gated on the lock that sending puts in place — that would leave
            // the only correct action greyed out.
            'canRevise' => can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes'),
            'canRetract' => quote_can_retract($q) && quote_my_approved_step($q['id']) !== null,
            'lockReason' => quote_lock_reason($q),
            'locked' => quote_is_locked($q), 'editReq' => quote_edit_request_open($q['id']),
            'canContract' => can('crm.contract.register') || is_master(),
            // §xii — who the approval is actually sitting with, right now.
            'pendingWith' => crm_pending_with($q['id']), 'pendingStep' => crm_pending_step($q['id']),
            'stepPeople' => array_map(fn($s) => crm_step_approvers($s), crm_approvals($q['id'])),
            'locs' => crm_quote_locations($q['id']),
            'files' => crm_quote_files($q['id']),
            'fileKinds' => QUOTE_FILE_KINDS,
            // Project costing linked to this quotation (attach / create from here).
            'costing' => function_exists('pc_for_quote') ? pc_for_quote($q['id']) : null,
            'costingUnlinked' => function_exists('pc_unlinked') ? pc_unlinked() : [],
            'costingCan' => function_exists('pc_can') && pc_can(),
            'payTerms' => lk_options_or('payment_term', PAYMENT_TERMS),
            'offAll' => offices_list(),
            'clientReg' => !empty($q['client_id']) ? ops_one("SELECT code, legal_name FROM business_partners WHERE id=?", [$q['client_id']]) : null,
            // §6 — the contract row and who may act on its opening approval.
            'contractRow'  => !empty($q['contract_id']) ? ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)$q['contract_id']]) : null,
            // One rate quotation → contracts for group companies: all contracts under
            // this quote, and the related companies still eligible for one.
            'quoteContracts' => function_exists('contracts_for_quote') ? contracts_for_quote((int)$q['id']) : [],
            'groupCandidates' => function_exists('quote_group_contract_candidates') ? quote_group_contract_candidates((int)$q['id']) : [],
            'canEndorseContract' => function_exists('can_endorse_contract_open') && can_endorse_contract_open(),
            'canApproveContract' => function_exists('can_approve_contract_open') && can_approve_contract_open(),
            'orderJobs' => ops_all("SELECT j.id, j.job_code, j.stage, j.closed_flag, j.invoice_raised, j.invoice_amount, j.payment_received, j.payment_amount, i.name inspector_name FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id WHERE j.quotation_id=? ORDER BY j.id", [$q['id']]),
            // Every inspection call raised under this quotation or its contract, with
            // the jobs and the money on each — so the whole order is checkable here.
            'orderCalls' => ops_all(
                "SELECT c.id, c.call_code, c.created_at, c.status, c.contract_number,
                        (SELECT COUNT(*) FROM jobs j WHERE j.call_id=c.id) job_count,
                        (SELECT COALESCE(SUM(j.invoice_amount),0) FROM jobs j WHERE j.call_id=c.id AND j.invoice_raised=1) invoiced,
                        (SELECT COALESCE(SUM(CASE WHEN j.payment_received=1 THEN j.payment_amount ELSE 0 END),0) FROM jobs j WHERE j.call_id=c.id) received
                 FROM calls c
                 WHERE c.quotation_id=? OR (COALESCE(c.contract_number,'')<>'' AND c.contract_number=?)
                 ORDER BY c.id DESC",
                [$q['id'], (string)($q['contract_number'] ?? '')])]);
        return;
    }
    if ($route === 'quote-status' && $method === 'POST') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        $to = $_POST['to'] ?? '';
        // Once it is with the customer the only honest moves are the customer's
        // own answer — accepted, or lost. Everything else needs a revision.
        if (quote_is_locked($q) && !in_array($to, ['ACCEPTED', 'LOST'], true)) {
            flash(quote_lock_reason($q), 'error');
            redirect('/quote?id=' . $q['id']);
        }
        if (!isset(lk_options_or('quote_status', QUOTE_STATUS)[$to])) { flash('Unknown status.', 'error'); redirect('/quote?id=' . $q['id']); }
        if ($to === 'APPROVED') ops_require(can('crm.quote.approve') || is_master(), 'You cannot approve quotations.');
        if ($to === 'SENT') ops_require(can('crm.quote.send') || is_master(), 'You cannot send quotations.');
        if ($to === 'PENDING_APPROVAL') {
            $n = crm_build_approvals($q);   // build the approval chain from the rules
            $pdo->prepare("UPDATE quotations SET status='PENDING_APPROVAL', submitted_at=?, rejected_by='', rejected_at='', reject_remarks='' WHERE id=?")
                ->execute([date('c'), $q['id']]);
            crm_log_change($q['id'], 'Submitted for approval');
            // §xii — say who it is now sitting with, and tell them.
            $with = crm_pending_with($q['id']);
            $step = crm_pending_step($q['id']);
            foreach (crm_step_approvers($step ?: []) as $ap) {
                if (trim((string)($ap['email'] ?? '')) === '') continue;
                ops_mail($ap['email'], 'Approval needed: ' . quote_label($q),
                    "Hello,\n\n" . quote_label($q) . " ({$q['client_name']}, " . fmoney($q['total_amount']) . ") is waiting for your approval.\n\n"
                    . "Open it in " . app_name() . " to approve or reject it with a comment.\n", '', 'QUOTE_APPROVAL');
            }
            flash('Submitted for approval — ' . $n . ' step(s). Now with ' . ($with ?: 'an approver') . '.');
            redirect('/quote?id=' . $q['id']);
        } elseif ($to === 'LOST') {
            $pdo->prepare("UPDATE quotations SET status='LOST', lost_reason=?, lost_reason_other=? WHERE id=?")
                ->execute([$_POST['lost_reason'] ?? '', trim($_POST['lost_reason_other'] ?? ''), $q['id']]);
            $pdo->prepare("UPDATE quote_followups SET status='SKIPPED' WHERE quote_id=? AND status='PENDING'")->execute([$q['id']]);
            // SYNC — a lost quotation closes its deal too, carrying the reason, so
            // the pipeline does not keep counting a deal that is already gone.
            try {
                if (function_exists('opp_move') && function_exists('opp_row') && function_exists('pipeline_stages')) {
                    $oid = quote_linked_deal_id($q);
                    if ($oid) {
                        $o = opp_row($oid);
                        if ($o && ($o['status'] ?? '') === 'OPEN') {
                            $lostStage = 0;
                            foreach (pipeline_stages((int)$o['pipeline_id']) as $st)
                                if (($st['kind'] ?? '') === 'LOST') { $lostStage = (int)$st['id']; break; }
                            if ($lostStage) opp_move($oid, $lostStage, [
                                'lost_reason' => ($_POST['lost_reason'] ?? '') ?: 'Quotation not accepted',
                                'lost_note'   => trim($_POST['lost_reason_other'] ?? ''),
                            ], true);
                        }
                    }
                }
            } catch (Throwable $e) { /* never block marking the quote lost */ }
        } elseif ($to === 'SENT') {
            [$sent, $msg] = crm_send_quote_email($q);
            $pdo->prepare("UPDATE quotations SET status='SENT', sent_at=? WHERE id=?")->execute([date('c'), $q['id']]);
            crm_schedule_followups($q['id']);
            // SYNC — sending the quotation nudges the deal to its "quotation sent"
            // step, but only FORWARD: a deal already further along is never dragged
            // back, and a deal with no such step is left where it is.
            try {
                if (function_exists('opp_move') && function_exists('opp_row') && function_exists('pipeline_stages')) {
                    $oid = quote_linked_deal_id($q);
                    if ($oid) {
                        $o = opp_row($oid);
                        if ($o && ($o['status'] ?? '') === 'OPEN') {
                            $stages = pipeline_stages((int)$o['pipeline_id']);
                            $curIdx = -1; $sentId = 0; $sentIdx = -1;
                            foreach ($stages as $i => $st) {
                                if ((int)$st['id'] === (int)$o['stage_id']) $curIdx = $i;
                                if ($sentIdx < 0 && ($st['kind'] ?? '') === 'OPEN'
                                    && preg_match('/quot|proposal|sent/i', (string)$st['name'])) { $sentIdx = $i; $sentId = (int)$st['id']; }
                            }
                            if ($sentId && $sentIdx > $curIdx) opp_move($oid, $sentId, [], true);
                        }
                    }
                }
            } catch (Throwable $e) { /* never block sending */ }
            flash($sent ? ('Quotation e-mailed to ' . $q['contact_email'] . ' and marked sent — follow-ups scheduled.') : ('Marked sent and follow-ups scheduled; ' . $msg), $sent ? 'success' : 'warning');
            redirect('/quote?id=' . $q['id']);
        } elseif ($to === 'ACCEPTED') {
            $pdo->prepare("UPDATE quotations SET status='ACCEPTED', accepted_date=? WHERE id=?")->execute([date('Y-m-d'), $q['id']]);
            $pdo->prepare("UPDATE quote_followups SET status='SKIPPED' WHERE quote_id=? AND status='PENDING'")->execute([$q['id']]);
            if ($q['inquiry_id']) $pdo->prepare("UPDATE crm_inquiries SET status='QUOTED' WHERE id=?")->execute([$q['inquiry_id']]);
            // A won order that exists only as a typed name is the point at which
            // the sales side and the operations side stop being the same system:
            // the client master never hears about it, so the order tab has no
            // quotation to offer, the line items are typed again by hand, and the
            // two versions drift. Winning it is the moment to put the company on
            // file — incomplete is fine and expected, because the tax numbers and
            // the addresses arrive later. What must not wait is the link.
            $res = quote_land_on_client($q);

            // SYNC — the quote's answer travels back to the deal. Accepting a
            // quotation IS the moment the deal is won, so the sales pipeline
            // should say so on its own, without anyone opening the deal to move
            // it by hand. That one-way link (the deal knew its quote, but the
            // quote's answer never came back) was the "to and fro" reported.
            // Best-effort: a hiccup here must never stop the quotation being
            // accepted — the acceptance is the fact, the propagation is a
            // convenience on top of it.
            $dealWon = false;
            try {
                if (function_exists('opp_move') && function_exists('opp_row') && function_exists('pipeline_stages')) {
                    $qid = (int)$q['id'];
                    $oid = quote_linked_deal_id($q);
                    if ($oid) {
                        // Link the quote to the deal both ways. A quote raised
                        // off a lead was never attached to the deal, so raising
                        // the order would carry the old estimate and not THIS
                        // accepted quotation — its rate, its number. One row fixes
                        // it, and every existing screen then finds the right quote.
                        try {
                            if (!(int) ops_val("SELECT COUNT(*) FROM opportunity_quotes WHERE opportunity_id=? AND quotation_id=?", [$oid, $qid]))
                                db()->prepare("INSERT INTO opportunity_quotes (opportunity_id, quotation_id) VALUES (?,?)")->execute([$oid, $qid]);
                        } catch (Throwable $e) {}
                        $o = opp_row($oid);
                        if ($o) {
                            // The acceptance just put the company on the customer
                            // master. Carry that onto the deal too, or a deal born
                            // from a lead (which starts with no customer) would be
                            // won but unable to raise its order — the customer sits
                            // on the quote and never reaches operations.
                            if (empty($o['partner_id']) && !empty($res['client_id'])) {
                                $pn = (string) ops_val("SELECT COALESCE(display_name, legal_name) FROM business_partners WHERE id=?", [(int)$res['client_id']]);
                                db()->prepare("UPDATE opportunities SET partner_id=?, partner_name=? WHERE id=?")
                                   ->execute([(int)$res['client_id'], $pn, $oid]);
                            }
                            if (($o['status'] ?? '') === 'OPEN') {
                                $wonStage = 0;
                                foreach (pipeline_stages((int)$o['pipeline_id']) as $st)
                                    if (($st['kind'] ?? '') === 'WON') { $wonStage = (int)$st['id']; break; }
                                if ($wonStage) { $rw = opp_move($oid, $wonStage, [], true); $dealWon = !empty($rw['ok']); }
                            }
                        }
                    }
                }
            } catch (Throwable $e) { /* never block the acceptance */ }

            // If MGH Books is connected, the accepted quotation (and its client)
            // flow across so Books can convert it — best-effort, never blocking the
            // acceptance, a clean no-op when Books is not connected.
            if (function_exists('books_bridge_on_quote_accepted')) {
                try { books_bridge_on_quote_accepted((int)$q['id']); } catch (Throwable $e) {}
            }

            flash(quote_landed_text($q, $res)
                . ($dealWon ? ' The deal is now won in the sales pipeline.' : ''), 'success');
            redirect('/quote?id=' . $q['id']);
        } else {
            $pdo->prepare("UPDATE quotations SET status=? WHERE id=?")->execute([$to, $q['id']]);
        }
        flash('Quotation moved to ' . (lk_options_or('quote_status', QUOTE_STATUS)[$to] ?? $to) . '.');
        redirect('/quote?id=' . $q['id']);
    }
    if ($route === 'quote-revise' && $method === 'POST') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        ops_require(can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes'), 'You cannot revise quotations.');
        $base = (int)($q['parent_id'] ?: $q['id']);
        $summary = trim($_POST['summary'] ?? '') ?: 'Revised';
        $newRev = (int)$q['rev'] + 1;
        // Snapshot the source first (fully-drained reads), then INSERT the new revision
        // BEFORE flipping the old ones — doing the same-table UPDATE first was observed
        // to corrupt the following wide INSERT under SQLite + the php built-in server.
        $srcRows = ops_all("SELECT * FROM quotations WHERE id=?", [$q['id']]);
        $src = $srcRows[0];
        $srcLines = crm_quote_lines($q['id']);
        $srcLocs  = crm_quote_locations($q['id']);
        $snap = ['header' => $q, 'lines' => $srcLines, 'locations' => $srcLocs];

        // A revision is the SAME quotation, priced again. So it carries every
        // column except the handful that identify this attempt and its progress.
        //
        // This list used to be written out by hand, and it silently lost whatever
        // was added to the table afterwards: the sites, the types of inspection,
        // the product category, the executing offices and the terms all vanished
        // the moment somebody revised. Working the other way round — everything
        // except what must be new — means a column added next year is carried
        // without anybody remembering to come back here.
        $skip = ['id',                                          // a new row
                 'quote_no','rev','parent_id','is_current',     // identity of the revision
                 'status','submitted_at','sent_at','accepted_date',
                 'lost_reason','lost_reason_other',
                 'rejected_by','rejected_at','reject_remarks',  // this attempt's history
                 'locked','unlocked_until',
                 'created_by','created_at','updated_at'];
        $carryCols = array_values(array_diff(array_keys($src), $skip));
        $cols = array_merge(['quote_no','rev','parent_id','is_current','status','created_by','created_at','updated_at'], $carryCols);
        $vals = array_merge([$src['quote_no'], $newRev, $base, 1, 'DRAFT', user_name(current_user()), date('c'), date('c')],
            array_map(function ($c) use ($src) { return $src[$c]; }, $carryCols));
        $pdo->prepare("INSERT INTO quotations (" . implode(',', $cols) . ") VALUES ("
            . implode(',', array_fill(0, count($cols), '?')) . ")")->execute($vals);
        $nid = (int)$pdo->lastInsertId();

        // The sites. These were not copied at all, so every location typed into a
        // quotation disappeared on the first revision — the single most annoying
        // thing about revising, and the hardest to notice until the PDF is out.
        $locMap = [];   // old location id => new, so the lines keep pointing at the right site
        if ($srcLocs) {
            $lcCols = array_values(array_diff(array_keys($srcLocs[0]), ['id', 'quote_id']));
            $li = $pdo->prepare("INSERT INTO quote_locations (quote_id," . implode(',', $lcCols) . ") VALUES (?,"
                . implode(',', array_fill(0, count($lcCols), '?')) . ")");
            foreach ($srcLocs as $L) {
                $li->execute(array_merge([$nid], array_map(function ($c) use ($L) { return $L[$c]; }, $lcCols)));
                $locMap[(int)$L['id']] = (int)$pdo->lastInsertId();
            }
        }

        // The lines, with every column they have — the office, the site and the
        // activity code were being dropped as well.
        if ($srcLines) {
            $liCols = array_values(array_diff(array_keys($srcLines[0]), ['id', 'quote_id']));
            $lins = $pdo->prepare("INSERT INTO quote_lines (quote_id," . implode(',', $liCols) . ") VALUES (?,"
                . implode(',', array_fill(0, count($liCols), '?')) . ")");
            foreach ($srcLines as $l) {
                $row = [];
                foreach ($liCols as $c) {
                    $v = $l[$c];
                    // a line pointing at a site must point at the NEW copy of it
                    if ($c === 'location_id' && $v !== null && isset($locMap[(int)$v])) $v = $locMap[(int)$v];
                    $row[] = $v;
                }
                $lins->execute(array_merge([$nid], $row));
            }
        }

        // Now demote every other revision of this quote.
        $pdo->prepare("UPDATE quotations SET is_current=0 WHERE quote_no=? AND id<>?")->execute([$q['quote_no'], $nid]);
        $pdo->prepare("INSERT INTO quote_revisions (quote_id,rev,changed_by,changed_at,summary,snapshot) VALUES (?,?,?,?,?,?)")
            ->execute([$base, $newRev, user_name(current_user()), date('c'), $summary, json_encode($snap)]);
        crm_log_change($nid, 'Revision ' . $newRev . ' created from rev ' . (int)$q['rev'] . ' — ' . $summary);
        flash('Created revision ' . str_pad((string)$newRev, 2, '0', STR_PAD_LEFT) . ' with '
            . count($srcLines) . ' line(s) and ' . count($srcLocs) . ' site(s) carried over. Edit it below.');
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
    if ($route === 'quote-pdf') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        $tpl = ops_one("SELECT * FROM crm_templates WHERE kind='QUOTE_DOC' ORDER BY is_default DESC, id DESC");   // for doc/format numbers
        $pdf = quote_pdf_build($q, crm_quote_lines($q["id"]), $tpl ?: [], quote_signature($q), quote_letterhead());
        $fname = 'Quote-' . preg_replace('/[^A-Za-z0-9_.-]/', '', $q['quote_no'] . ($q['rev'] > 0 ? '-R' . $q['rev'] : '')) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf; exit;
    }
    // Save the pre-order review checklist ticks for a quotation.
    if ($route === 'quote-preorder-save' && $method === 'POST') {
        $q = crm_quote_get((int)($_POST['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        // The routed approver must be able to tick the pre-order checklist too —
        // it is a condition of THEIR approval, and a branch manager assigned the
        // quote often holds none of the blanket create/edit/approve permissions
        // (their authority is the assignment). Without this the save is refused
        // and bounces to the dashboard, so the checklist can never be completed.
        ops_require(crm_can_act_on_quote((int)$q['id']) || can('crm.quote.create') || can('mod.quotes.edit') || can('crm.quote.approve') || is_master(),
            'You cannot update this ' . Tl('quote') . '.');
        $ticked = (array)($_POST['pc'] ?? []); $vc = [];
        foreach (preorder_checklist_items() as $it) { $k = preorder_item_key($it); if (!empty($ticked[$k])) $vc[$k] = 1; }
        $pdo->prepare("UPDATE quotations SET preorder_checklist=? WHERE id=?")->execute([json_encode($vc), $q['id']]);
        flash('Pre-order checklist saved.');
        redirect('/quote?id=' . $q['id']);
    }
    if ($route === 'quote-approve' && $method === 'POST') {
        $step = ops_one("SELECT * FROM quote_approvals WHERE id=?", [(int)($_POST['step'] ?? 0)]);
        if (!$step) { flash('Approval step not found.', 'error'); redirect('/quotes'); }
        $q = crm_quote_get($step['quote_id']); if (!$q) { http_response_code(404); view('notfound'); return; }
        ops_require(crm_can_act_approval($step), 'You are not an approver for this step.');
        // Pre-order control: when the checklist is on and every point is required,
        // an approval cannot be given until the review checklist is complete. Only
        // gates approval — a rejection is always allowed.
        if (($_POST['decision'] ?? '') !== 'reject' && preorder_checklist_enabled() && preorder_checklist_require_all()) {
            $pg = preorder_checklist_progress($q);
            if (!$pg['complete']) {
                flash('Complete the pre-order review checklist before approving (' . $pg['done'] . '/' . $pg['total'] . ' done).', 'error');
                redirect('/quote?id=' . $q['id']);
            }
        }
        $remarks = trim($_POST['remarks'] ?? '');
        $me = user_name(current_user());
        if (($_POST['decision'] ?? '') === 'reject') {
            // §xiii — a rejection must carry a reason.
            if ($remarks === '') { flash('Enter a comment saying why you are rejecting it.', 'error'); redirect('/quote?id=' . $q['id']); }
            $pdo->prepare("UPDATE quote_approvals SET status='REJECTED', acted_by=?, acted_at=?, remarks=? WHERE id=?")
                ->execute([$me, date('c'), $remarks, $step['id']]);
            // §xvi — the quote itself shows as rejected, by whom and why, rather than
            // quietly reverting to draft with no trace.
            $pdo->prepare("UPDATE quotations SET status='REJECTED', rejected_by=?, rejected_at=?, reject_remarks=? WHERE id=?")
                ->execute([$me, date('c'), $remarks, $q['id']]);
            crm_log_change($q['id'], 'Rejected by ' . $me . ' — ' . $remarks);
            crm_notify_owner($q, 'rejected by ' . $me, $remarks);
            flash('Rejected. The ' . Tl('quote') . ' now shows as rejected by you, with your comment.');
        } else {
            $pdo->prepare("UPDATE quote_approvals SET status='APPROVED', acted_by=?, acted_at=?, remarks=? WHERE id=?")
                ->execute([$me, date('c'), $remarks, $step['id']]);
            $pending = (int)ops_val("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=? AND status='PENDING'", [$q['id']]);
            if ($pending === 0) {
                $pdo->prepare("UPDATE quotations SET status='APPROVED' WHERE id=?")->execute([$q['id']]);
                crm_log_change($q['id'], 'Approved by ' . $me . ($remarks !== '' ? ' — ' . $remarks : ''));
                crm_notify_owner($q, 'approved', $remarks);
                flash('All approvals complete — ' . Tl('quote') . ' approved and ready to send.');
            } else {
                crm_log_change($q['id'], 'Approved at level ' . (int)$step['level'] . ' by ' . $me);
                $next = crm_pending_with($q['id']);
                flash('Your approval is recorded. Now with ' . ($next ?: 'the next approver') . '.');
            }
        }
        redirect('/quote?id=' . $q['id']);
    }
    // Take an approval back, before it leaves the building.
    if ($route === 'quote-unapprove' && $method === 'POST') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        if (!quote_can_retract($q)) {
            flash(quote_lock_reason($q) ?: 'There is nothing to take back on this ' . Tl('quote') . '.', 'error');
            redirect('/quote?id=' . $q['id']);
        }
        $step = quote_my_approved_step($q['id']);
        if (!$step) {
            flash('You have no approval on this ' . Tl('quote') . ' to take back.', 'error');
            redirect('/quote?id=' . $q['id']);
        }
        $why = trim((string)($_POST['reason'] ?? ''));
        $me = user_name(current_user());
        $pdo->prepare("UPDATE quote_approvals SET status='PENDING', acted_by='', acted_at='', remarks=? WHERE id=?")
            ->execute([$why, $step['id']]);
        // Any level approved AFTER this one is void too — an approval given on
        // the strength of a withdrawn one cannot stand on its own.
        $after = ops_all("SELECT * FROM quote_approvals WHERE quote_id=? AND status='APPROVED' AND level > ?",
                         [$q['id'], (int)$step['level']]);
        foreach ($after as $a)
            $pdo->prepare("UPDATE quote_approvals SET status='PENDING', acted_by='', acted_at='', remarks=? WHERE id=?")
                ->execute(['Reset — level ' . (int)$step['level'] . ' was taken back by ' . $me, $a['id']]);
        $pdo->prepare("UPDATE quotations SET status='PENDING_APPROVAL' WHERE id=?")->execute([$q['id']]);
        crm_log_change($q['id'], 'Approval at level ' . (int)$step['level'] . ' taken back by ' . $me
            . ($why !== '' ? ' — ' . $why : '')
            . ($after ? ' (' . count($after) . ' later level(s) reset)' : ''));
        crm_notify_owner($q, 'approval taken back by ' . $me, $why);
        flash('Your approval has been taken back. The ' . Tl('quote') . ' is waiting for approval again'
            . ($after ? ', and ' . count($after) . ' later level(s) were reset with it' : '') . '.');
        redirect('/quote?id=' . $q['id']);
    }

    // §12/§13 — Accounts registers the client + contract number, which floats the
    // Operations packet (client, quote/contract no, contacts, service req, TC proposal).
    if ($route === 'quote-contract' && $method === 'POST') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        ops_require(can('crm.contract.register') || is_master(), 'Only Accounts / back-office can register the contract.');
        $cid = crm_register_client_for_quote($q);
        // The office is chosen once here and carries to every call, job and invoice.
        // Accounts may change it at registration; it defaults to the quote's office.
        $branchId = (int)($_POST['branch_id'] ?? 0) ?: ((int)($q['office_id'] ?? 0) ?: null);
        // §6b — a structured number can be generated instead of typed: BRANCH/C/FY/NNNNN.
        $contractNo = trim($_POST['contract_number'] ?? '');
        if (!empty($_POST['auto_contract']) || $contractNo === '') {
            $contractNo = gen_contract_number($branchId);
        }
        $start = $_POST['start_date'] ?? ''; $end = $_POST['end_date'] ?? '';
        $contractId = $q['contract_id'] ?: null;
        // A won deal gets ONE contract. If this quotation already carries a LIVE
        // contract (open or awaiting approval) and a DIFFERENT number is being
        // registered, that would orphan the first and re-forward the same deal to
        // Finance under a second number. Refuse, and point to the right door.
        // A prior contract that was REJECTED or CLOSED may be registered afresh —
        // that is a genuine restart, not a duplicate.
        if ($contractId) {
            $prev = ops_one("SELECT id, contract_number, open_status FROM partner_contracts WHERE id=?", [(int)$contractId]);
            if ($prev && in_array((string)($prev['open_status'] ?? 'OPEN'), ['OPEN','PENDING'], true)
                && $contractNo !== '' && $contractNo !== (string)$prev['contract_number']) {
                flash('This ' . Tl('quote') . ' is already registered under contract ' . $prev['contract_number']
                    . ' (' . strtolower(CONTRACT_OPEN_STATES[$prev['open_status']] ?? (string)$prev['open_status']) . '). '
                    . 'A won deal gets one contract — it cannot be forwarded to Finance again under a new number. '
                    . 'For a related group company, add a group-company contract below; to revive a closed one, reopen it.', 'error');
                redirect('/quote?id=' . $q['id']);
            }
        }
        // §3 — the same contract number on the same client is a rate contract
        // being drawn down again, which is normal. The same number on a DIFFERENT
        // client is a duplicate, and every expiry and quantity figure downstream
        // would then be reading the wrong contract.
        if ($contractNo !== '' && $cid) {
            $clash = contract_no_clash($contractNo, $cid);
            if ($clash) {
                flash('Contract number ' . $contractNo . ' is already registered against '
                    . ($clash['owner_name'] ?: 'another ' . Tl('client'))
                    . '. A contract number must identify one contract — use a different number, '
                    . 'or correct the ' . Tl('client') . ' on this ' . Tl('quote') . '.', 'error');
                redirect('/quote?id=' . $q['id']);
            }
        }
        // §6d — opening a NEW contract number needs two signatures (a manager
        // endorses, the branch manager approves). It is registered as PENDING and
        // the order is held back until it is OPEN. Re-using an existing OPEN
        // contract for the same client (rate-contract draw-down) floats at once.
        $isNew = false; $openStatus = 'OPEN';
        if ($contractNo !== '' && $cid) {
            $ex = ops_one("SELECT id, open_status FROM partner_contracts WHERE partner_id=? AND contract_number=?", [$cid, $contractNo]);
            if ($ex) { $contractId = (int)$ex['id']; $openStatus = $ex['open_status'] ?: 'OPEN'; }
            else {
                $me = user_name(current_user()); $meId = (int)(current_user()['id'] ?? 0);
                $pdo->prepare("INSERT INTO partner_contracts (partner_id,contract_number,title,value,start_date,end_date,notes,branch_id,open_status,requested_by,requested_by_id,requested_at,is_active) VALUES (?,?,?,?,?,?,?,?, 'PENDING', ?,?,?, 0)")
                    ->execute([$cid, $contractNo, $q['subject'], (float)$q['total_amount'], $start, $end, 'From quotation ' . $q['quote_no'], $branchId, $me, $meId, date('c')]);
                $contractId = (int)$pdo->lastInsertId();
                $isNew = true; $openStatus = 'PENDING';
            }
        }
        $pdo->prepare("UPDATE quotations SET client_id=?, contract_number=?, contract_id=? WHERE id=?")->execute([$cid, $contractNo, $contractId, $q['id']]);
        if ($contractId && function_exists('contract_link_quotation')) contract_link_quotation($contractId, (int)$q['id']);
        if ($isNew) {
            // Held for approval — record it in the quotation thread, do not float yet.
            crm_log_change($q['id'], 'Contract ' . $contractNo . ' requested by ' . user_name(current_user())
                . ' — pending manager & branch-manager approval before the order floats to operations.');
            flash('Contract ' . $contractNo . ' registered and awaiting approval — a manager must endorse it and the branch manager approve it before it opens and floats to operations.', 'warning');
            redirect('/quote?id=' . $q['id']);
        }
        // Existing OPEN contract (rate-contract draw-down) — float straight away.
        $ok = $openStatus === 'OPEN' ? crm_float_ops_packet(crm_quote_get($q['id'])) : false;
        if ($openStatus === 'OPEN') {
            flash($ok ? 'Client & contract registered — the operations packet has been e-mailed to the team.' : 'Client & contract registered. The ops packet was logged (configure SMTP in Settings to e-mail it).', $ok ? 'success' : 'warning');
        } else {
            flash('Registered against contract ' . $contractNo . ', which is still ' . strtolower(CONTRACT_OPEN_STATES[$openStatus] ?? $openStatus) . '.', 'warning');
        }
        redirect('/quote?id=' . $q['id']);
    }
    // One rate quotation → an additional contract for a related GROUP company.
    if ($route === 'quote-add-contract' && $method === 'POST') {
        ops_require(can('crm.contract.register') || is_master(), 'Only Accounts / back-office can register a contract.');
        $qid = (int)($_GET['id'] ?? 0);
        $r = crm_add_group_contract($qid, (int)($_POST['partner_id'] ?? 0), $_POST);
        if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/quote?id=' . $qid . '#contract'); }
        flash('Group contract ' . $r['contract_no'] . ' registered and awaiting approval — it opens after a manager endorses and the branch manager approves, and then its ' . Tlp('call') . ' are raised from it.', 'warning');
        redirect('/quote?id=' . $qid . '#contract');
    }
    if ($route === 'quote-float' && $method === 'POST') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        ops_require(can('crm.contract.register') || can('crm.quote.send') || is_master(), 'You cannot float this to operations.');
        $ok = crm_float_ops_packet($q);
        flash($ok ? 'Handover to operations re-sent.'
                  : 'Handover to operations saved and logged. It will be e-mailed once SMTP is configured in Settings.', $ok ? 'success' : 'warning');
        redirect('/quote?id=' . $q['id']);
    }

    // ---- Send it yourself, from your own mailbox ---------------------------
    // Builds the finished message and hands it to the user's mail client. The
    // .eml form carries the quotation PDF and opens in Outlook as a draft with
    // a Send button, so the client receives it from the person, not the robot.
    if ($route === 'quote-compose') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        ops_require(can('crm.quote.send') || can('crm.quote.create') || is_master(), 'You cannot send ' . Tlp('quote') . '.');
        [$subject, $body] = crm_quote_message($q);
        [$fromEmail, $fromName] = compose_from();
        $to = (string)($q['contact_email'] ?? '');
        $mode = $_GET['mode'] ?? 'eml';
        if ($mode === 'mailto') { redirect(compose_mailto($to, $subject, $body)); }
        if ($mode === 'owa')    { redirect(compose_owa($to, $subject, $body)); }
        // Default: a real draft, with the quotation PDF and anything marked to
        // go out with it already attached.
        $att = [];
        $tpl = ops_one("SELECT * FROM crm_templates WHERE kind='QUOTE_DOC' ORDER BY is_default DESC, id DESC");
        $pdf = quote_pdf_build($q, crm_quote_lines($q['id']), $tpl ?: [], quote_signature($q), quote_letterhead());
        if ($pdf) $att[] = ['name' => 'Quote-' . preg_replace('/[^A-Za-z0-9_.-]/', '', $q['quote_no'] . ($q['rev'] > 0 ? '-R' . $q['rev'] : '')) . '.pdf',
                            'data' => $pdf, 'type' => 'application/pdf'];
        foreach (crm_quote_files($q['id']) as $f) {
            if ((int)$f['share_with_inspector'] !== 1 && $f['kind'] !== 'ATTACHMENT') continue;
            if (!in_array($f['kind'], ['ATTACHMENT', 'QUOTE_DOC'], true)) continue;   // only what goes to the client
            $row = ops_one("SELECT file_data, mime, file_name FROM quote_files WHERE id=?", [(int)$f['id']]);
            if ($row) $att[] = ['name' => $row['file_name'], 'data' => base64_decode((string)$row['file_data']), 'type' => $row['mime'] ?: 'application/octet-stream'];
        }
        crm_log_change($q['id'], 'Opened in ' . ($fromName ?: 'the sender') . "'s mailbox to send by hand");
        compose_eml_download('Quote-' . $q['quote_no'], compose_eml($to, $subject, $body, '', $att, $fromEmail, $fromName));
        return;
    }
    // The same, for chasing a follow-up that is due.
    if ($route === 'followup-compose') {
        $f = ops_one("SELECT * FROM quote_followups WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$f) { http_response_code(404); view('notfound'); return; }
        $q = crm_quote_get((int)$f['quote_id']); if (!$q) { http_response_code(404); view('notfound'); return; }
        [$fromEmail, $fromName] = compose_from();
        $et = ops_one("SELECT * FROM crm_templates WHERE kind='EMAIL_FOLLOWUP' AND active=1 ORDER BY is_default DESC, id DESC");
        $subject = ($et && $et['subject']) ? crm_fill_email($et['subject'], $q) : ('Following up: ' . quote_label($q));
        $body    = ($et && $et['body'])    ? crm_fill_email($et['body'], $q)    : crm_default_followup_email($q);
        if (($_GET['mode'] ?? '') === 'mailto') redirect(compose_mailto((string)$q['contact_email'], $subject, $body));
        compose_eml_download('Followup-' . $q['quote_no'],
            compose_eml((string)$q['contact_email'], $subject, $body, '', [], $fromEmail, $fromName));
        return;
    }

    // ---- §xiv / §xxiv — files against a quote -----------------------------
    if ($route === 'quote-files' && $method === 'POST') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        ops_require(can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes'), 'You cannot attach files to ' . Tlp('quote') . '.');
        $kind  = in_array($_POST['kind'] ?? '', array_keys(QUOTE_FILE_KINDS), true) ? $_POST['kind'] : 'ATTACHMENT';
        $note  = trim($_POST['note'] ?? '');
        $share = !empty($_POST['share_with_inspector']) ? 1 : 0;
        $n = 0; $skipped = [];
        $files = $_FILES['files'] ?? null;
        if ($files && is_array($files['tmp_name'])) {
            foreach ($files['tmp_name'] as $i => $tmp) {
                if (!$tmp || !is_uploaded_file($tmp)) continue;
                $size = (int)($files['size'][$i] ?? 0);
                if ($size <= 0 || $size > QUOTE_FILE_MAX) { $skipped[] = $files['name'][$i] . ' (over ' . round(QUOTE_FILE_MAX / 1048576) . ' MB)'; continue; }
                $pdo->prepare("INSERT INTO quote_files (quote_id,kind,file_name,mime,file_data,note,share_with_inspector,uploaded_by,uploaded_at) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$q['id'], $kind, substr((string)$files['name'][$i], 0, 200),
                        (string)($files['type'][$i] ?? ''), base64_encode((string)file_get_contents($tmp)),
                        $note, $share, user_name(current_user()), date('c')]);
                $n++;
            }
        }
        // The client's PO number and date live on the quote itself.
        if ($kind === 'PO') {
            $po = trim($_POST['po_number'] ?? ''); $pd = trim($_POST['po_date'] ?? '');
            if ($po !== '' || $pd !== '') $pdo->prepare("UPDATE quotations SET po_number=?, po_date=? WHERE id=?")->execute([$po, $pd, $q['id']]);
        }
        if ($n) crm_log_change($q['id'], $n . ' ' . strtolower(QUOTE_FILE_KINDS[$kind]) . ' file(s) attached');
        flash($n ? ($n . ' file(s) attached.' . ($skipped ? ' Skipped: ' . implode(', ', $skipped) : '')) : ('Nothing uploaded.' . ($skipped ? ' Skipped: ' . implode(', ', $skipped) : '')), $n ? 'success' : 'error');
        redirect('/quote?id=' . $q['id']);
    }
    if ($route === 'quote-file') {
        $f = ops_one("SELECT * FROM quote_files WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$f) { http_response_code(404); echo 'Not found'; return; }
        $bin = base64_decode((string)$f['file_data']);
        header('Content-Type: ' . ($f['mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $f['file_name']) . '"');
        header('Content-Length: ' . strlen($bin));
        echo $bin; return;
    }
    if ($route === 'quote-file-delete' && $method === 'POST') {
        $f = ops_one("SELECT * FROM quote_files WHERE id=?", [(int)($_GET['id'] ?? 0)]);
        if (!$f) { flash('File not found.', 'error'); redirect('/quotes'); }
        ops_require(can('crm.quote.create') || is_master(), 'You cannot remove attachments.');
        $pdo->prepare("DELETE FROM quote_files WHERE id=?")->execute([(int)$f['id']]);
        crm_log_change((int)$f['quote_id'], 'Removed attachment ' . $f['file_name']);
        flash('Attachment removed.');
        redirect('/quote?id=' . (int)$f['quote_id']);
    }

    // ---- §xix — ask the Super Admin to re-open a closed quote ---------------
    if ($route === 'quote-unlock' && $method === 'POST') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        $do = $_POST['do'] ?? 'request';
        if ($do === 'request') {
            $reason = trim($_POST['reason'] ?? '');
            if ($reason === '') { flash('Say why it needs to be re-opened.', 'error'); redirect('/quote?id=' . $q['id']); }
            if (quote_edit_request_open($q['id'])) { flash('A request is already pending for this ' . Tl('quote') . '.', 'error'); redirect('/quote?id=' . $q['id']); }
            $pdo->prepare("INSERT INTO quote_edit_requests (quote_id,requested_by,requested_by_id,reason,status,created_at) VALUES (?,?,?,?, 'PENDING', ?)")
                ->execute([$q['id'], user_name(current_user()), current_user()['id'] ?? null, $reason, date('c')]);
            crm_log_change($q['id'], 'Re-edit requested — ' . $reason);
            foreach (leadership_emails() as $to)
                ops_mail($to, 'Re-edit requested: ' . quote_label($q),
                    user_name(current_user()) . " has asked to re-open " . quote_label($q) . " ({$q['client_name']}).\n\nReason: $reason\n\n" . app_name(), '', 'QUOTE_UNLOCK');
            flash('Request raised. Only the Super Admin can grant it.');
        } else {
            ops_require(is_master(), 'Only the Super Admin can decide a re-edit request.');
            $req = ops_one("SELECT * FROM quote_edit_requests WHERE id=?", [(int)($_POST['req'] ?? 0)]);
            if (!$req) { flash('Request not found.', 'error'); redirect('/quote?id=' . $q['id']); }
            $note = trim($_POST['note'] ?? '');
            if ($do === 'grant') {
                $hours = max(1, min(168, (int)($_POST['hours'] ?? 24)));
                $until = date('c', time() + $hours * 3600);
                $pdo->prepare("UPDATE quote_edit_requests SET status='GRANTED', decided_by=?, decided_at=?, decision_note=?, expires_at=? WHERE id=?")
                    ->execute([user_name(current_user()), date('c'), $note, $until, $req['id']]);
                $pdo->prepare("UPDATE quotations SET unlocked_until=? WHERE id=?")->execute([$until, $q['id']]);
                crm_log_change($q['id'], 'Re-edit granted for ' . $hours . 'h by ' . user_name(current_user()));
                flash('Unlocked for ' . $hours . ' hour(s). It re-locks itself afterwards.');
            } else {
                $pdo->prepare("UPDATE quote_edit_requests SET status='REFUSED', decided_by=?, decided_at=?, decision_note=? WHERE id=?")
                    ->execute([user_name(current_user()), date('c'), $note, $req['id']]);
                crm_log_change($q['id'], 'Re-edit refused by ' . user_name(current_user()));
                flash('Request refused.');
            }
        }
        redirect('/quote?id=' . $q['id']);
    }

    // ---- §xxvi — follow-ups are editable, one row at a time -----------------
    if ($route === 'quote-followup' && $method === 'POST') {
        $q = crm_quote_get((int)($_GET['id'] ?? 0)); if (!$q) { http_response_code(404); view('notfound'); return; }
        ops_require(can('crm.quote.send') || can('crm.quote.create') || is_master(), 'You cannot change follow-ups.');
        $do = $_POST['do'] ?? 'save';
        if ($do === 'add') {
            $due = trim($_POST['due_date'] ?? '') ?: date('Y-m-d');
            $pdo->prepare("INSERT INTO quote_followups (quote_id,kind,due_date,status,note,created_at) VALUES (?,?,?, 'PENDING', ?, ?)")
                ->execute([$q['id'], trim($_POST['kind'] ?? 'MANUAL') ?: 'MANUAL', $due, trim($_POST['note'] ?? ''), date('c')]);
            flash('Follow-up added.');
        } elseif ($do === 'delete') {
            $pdo->prepare("DELETE FROM quote_followups WHERE id=? AND quote_id=?")->execute([(int)($_POST['fid'] ?? 0), $q['id']]);
            flash('Follow-up removed.');
        } else {
            foreach ((array)($_POST['f_id'] ?? []) as $i => $fid) {
                $pdo->prepare("UPDATE quote_followups SET due_date=?, status=?, done_date=?, note=? WHERE id=? AND quote_id=?")
                    ->execute([trim((string)($_POST['f_due'][$i] ?? '')), (string)($_POST['f_status'][$i] ?? 'PENDING'),
                        trim((string)($_POST['f_done'][$i] ?? '')), trim((string)($_POST['f_note'][$i] ?? '')), (int)$fid, $q['id']]);
            }
            flash('Follow-ups saved.');
        }
        redirect('/quote?id=' . $q['id']);
    }
}

// ---------------------------------------------------------------------------
//  §xxii — the register as a spreadsheet. Everything the sales desk asks for:
//  when it went out, when it was accepted, who the contact is, and every
//  follow-up date, so chasing can be audited outside the app.
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
//  Contract number pending
//
//  Winning a quotation and registering the contract are two jobs done by two
//  people. Accounts creates the contract number; the coordinator raises calls
//  against it. Neither used to know the other was waiting, so a won order could
//  sit for a week with nobody realising anything was outstanding — and calls
//  raised in the meantime carried no contract reference at all.
//
//  Scheduling is deliberately NOT blocked. Work does not wait for paperwork;
//  it just has to be visible that the paperwork is outstanding.
// ---------------------------------------------------------------------------
function quotes_awaiting_contract($limit = 100) {
    [$w, $a] = scope_clause('q.office_id', 'q.sbu');
    return ops_all("SELECT q.id, q.quote_no, q.rev, q.client_name, q.subject, q.total_amount, q.accepted_date,
                           q.client_id, q.office_id
                    FROM quotations q
                    WHERE q.is_current=1 AND q.status='ACCEPTED'
                      AND (q.contract_number IS NULL OR q.contract_number='')
                      AND $w
                    ORDER BY q.accepted_date, q.id", $a);
}
function quotes_awaiting_contract_count() { return count(quotes_awaiting_contract()); }
// Days since a won quotation has been waiting for its contract number.
function contract_wait_days($q) {
    $d = trim((string)($q['accepted_date'] ?? ''));
    if ($d === '') return null;
    return days_between($d, date('Y-m-d'));
}
// Is this call's commercial paperwork incomplete? Used by the call screens to
// keep saying so until somebody fixes it.
function call_contract_gap($call) {
    if (trim((string)($call['contract_number'] ?? '')) !== '') return null;
    $qid = (int)($call['quotation_id'] ?? 0);
    if (!$qid) {
        return ['kind' => 'DIRECT',
                'text' => 'No contract number on this ' . Tl('call') . ', and no ' . Tl('quote') . ' behind it. '
                        . 'If it draws on an existing contract, type the number on the ' . Tl('call') . ' so the '
                        . 'draw-down stays visible against it.'];
    }
    $q = ops_one("SELECT quote_no, rev, status, client_name FROM quotations WHERE id=?", [$qid]);
    if (!$q) return null;
    return ['kind' => 'PENDING', 'quote_id' => $qid, 'quote_no' => $q['quote_no'],
            'text' => 'Contract number not yet created for ' . quote_label($q) . '. '
                    . 'Accounts have been notified; this ' . Tl('call') . ' can go ahead without it, '
                    . 'but it will keep showing as outstanding until the number is registered.'];
}

function crm_quotes_export($rows) {
    $out = [[
        ucfirst(Tl('quote')) . ' no', 'Rev', 'Status', 'Origin', ucfirst(Tl('client')), 'Contact', 'Contact e-mail',
        'Contact mobile', T('sbu'), 'Primary ' . T('office'), 'Executing ' . TP('office'), 'Product category',
        'Subject', 'Sites', 'Created', 'Submitted for approval', 'Approved', 'Sent', 'Accepted',
        'Lost / rejected on', 'Lost reason', 'Rejected by', 'Reject comment',
        'Contract number', 'PO number', 'PO date', 'Payment terms',
        'Subtotal', 'GST %', 'Total', 'Follow-ups done', 'Follow-up dates', 'Owner',
    ]];
    $offAll = []; foreach (offices_list() as $o) $offAll[(int)$o['id']] = $o['name'];
    foreach ($rows as $q) {
        $fu = ops_all("SELECT kind, due_date, status, done_date FROM quote_followups WHERE quote_id=? ORDER BY due_date", [(int)$q['id']]);
        $done = array_filter($fu, fn($f) => ($f['status'] ?? '') === 'DONE' || ($f['done_date'] ?? '') !== '');
        $fuTxt = implode(' | ', array_map(fn($f) =>
            ($f['kind'] ?: '?') . ' due ' . fdate($f['due_date'], '—')
            . (($f['done_date'] ?? '') !== '' ? ' done ' . fdate($f['done_date']) : ' (' . strtolower($f['status'] ?: 'pending') . ')'), $fu));
        $sites = implode(' | ', array_map(fn($L) => quote_location_label($L) . ': ' . quote_location_line($L), crm_quote_locations((int)$q['id'])));
        $exec = array_filter(array_map('intval', explode(',', (string)($q['exec_office_ids'] ?? ''))));
        $approved = ops_val("SELECT MAX(acted_at) FROM quote_approvals WHERE quote_id=? AND status='APPROVED'", [(int)$q['id']]);
        $out[] = [
            $q['quote_no'], (int)$q['rev'], lk_options_or('quote_status', QUOTE_STATUS)[$q['status']] ?? $q['status'],
            lk_options_or('quote_origin', QUOTE_ORIGINS)[$q['origin'] ?? 'OWN'] ?? ($q['origin'] ?? ''),
            $q['client_name'], $q['contact_name'], $q['contact_email'], $q['contact_mobile'],
            lk_options_or('sbu', OPS_SBUS)[$q['sbu']] ?? $q['sbu'],
            $offAll[(int)($q['office_id'] ?? 0)] ?? '',
            implode(' + ', array_map(fn($i) => $offAll[$i] ?? $i, $exec)),
            product_cat_label($q['product_category'] ?? ''), $q['subject'], $sites,
            fdate(substr((string)$q['created_at'], 0, 10), ''),
            fdate(substr((string)($q['submitted_at'] ?? ''), 0, 10), ''),
            fdate(substr((string)$approved, 0, 10), ''),
            fdate(substr((string)$q['sent_at'], 0, 10), ''),
            fdate($q['accepted_date'], ''),
            fdate(substr((string)($q['rejected_at'] ?? ''), 0, 10), ''),
            lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS)[$q['lost_reason'] ?? ''] ?? ($q['lost_reason_other'] ?? ''),
            $q['rejected_by'] ?? '', $q['reject_remarks'] ?? '',
            $q['contract_number'] ?? '', $q['po_number'] ?? '', fdate($q['po_date'] ?? '', ''),
            $q['payment_terms'] ?? '',
            (float)$q['subtotal'], (float)$q['gst_pct'], (float)$q['total_amount'],
            count($done) . ' of ' . count($fu), $fuTxt, $q['created_by'] ?? '',
        ];
    }
    return $out;
}

// What can be filed against a quote (§xiv, §xxiv).
const QUOTE_FILE_KINDS = [
    'QUOTE_DOC'  => 'Our quotation (the copy sent)',
    'ATTACHMENT' => 'General attachment',
    'CLIENT_DOC' => "Client's document / enquiry",
    'PO'         => 'Purchase order',
    'INSP_DOC'   => 'Inspection document (spec, drawing, QAP)',
];
const QUOTE_FILE_MAX = 8388608;   // 8 MB per file
// §xxiv — the documents filed against the quote that the field engineer needs:
// the PO, the specification, the drawing, the QAP. Reached from the job (which
// carries quotation_id) and from the call behind it, so whoever the call is
// allocated to sees exactly what the client sent.
function quote_docs_for_job($jobId) {
    $qid = (int)ops_val("SELECT quotation_id FROM jobs WHERE id=?", [(int)$jobId]);
    return $qid ? quote_docs_shared($qid) : [];
}
function quote_docs_for_call($callId) {
    $qid = (int)ops_val("SELECT COALESCE(MAX(quotation_id),0) FROM jobs WHERE call_id=? AND quotation_id IS NOT NULL", [(int)$callId]);
    return $qid ? quote_docs_shared($qid) : [];
}
function quote_docs_shared($qid) {
    return ops_all("SELECT id, kind, file_name, mime, note, uploaded_at, LENGTH(file_data) AS b64len
                    FROM quote_files WHERE quote_id=? AND share_with_inspector=1
                      AND kind IN ('PO','INSP_DOC','CLIENT_DOC') ORDER BY kind, id", [(int)$qid]);
}
function crm_quote_files($qid, $kind = null) {
    $sql = "SELECT id, quote_id, kind, file_name, mime, note, share_with_inspector, uploaded_by, uploaded_at,
                   LENGTH(file_data) AS b64len FROM quote_files WHERE quote_id=?";
    $args = [(int)$qid];
    if ($kind) { $sql .= " AND kind=?"; $args[] = $kind; }
    return ops_all($sql . " ORDER BY id DESC", $args);
}

// ---------------------------------------------------------------------------
//  Operations link (§16 revenue, §21/§22 HOLD, §24 deliverables)
// ---------------------------------------------------------------------------
// Accepted / in-flight quotes a job can be booked against (client-scoped).
function job_linkable_quotes($clientId) {
    $clientId = (int)$clientId;
    if ($clientId) return ops_all("SELECT id, quote_no, rev, contract_number, total_amount FROM quotations WHERE is_current=1 AND status IN ('ACCEPTED','APPROVED','SENT') AND client_id=? ORDER BY (status='ACCEPTED') DESC, id DESC", [$clientId]);
    return ops_all("SELECT id, quote_no, rev, contract_number, total_amount FROM quotations WHERE is_current=1 AND status='ACCEPTED' ORDER BY id DESC LIMIT 50");
}
// Inherit the advance / report-vs-payment conditions (and blank deliverables) from
// the linked quotation onto the job, so the inspector sees the right HOLD (§21,§22,§24).
function crm_apply_quote_to_job($jobId) {
    $j = ops_one("SELECT quotation_id, deliverables FROM jobs WHERE id=?", [(int)$jobId]);
    if (!$j || empty($j['quotation_id'])) return;
    $q = ops_one("SELECT advance_required, advance_pct, report_vs_payment FROM quotations WHERE id=?", [(int)$j['quotation_id']]);
    if (!$q) return;
    db()->prepare("UPDATE jobs SET adv_required=?, adv_pct=?, report_hold=? WHERE id=?")
        ->execute([(int)$q['advance_required'], (float)$q['advance_pct'], (int)$q['report_vs_payment'], (int)$jobId]);
    // §24: if the job has no deliverables yet, pull any listed on the quote's lines.
    if (trim((string)($j['deliverables'] ?? '')) === '') {
        $dl = [];
        foreach (ops_all("SELECT deliverables FROM quote_lines WHERE quote_id=? AND deliverables<>''", [(int)$j['quotation_id']]) as $l)
            foreach (explode(',', $l['deliverables']) as $d) { $d = trim($d); if ($d !== '') $dl[$d] = 1; }
        if ($dl) db()->prepare("UPDATE jobs SET deliverables=? WHERE id=?")->execute([implode(',', array_keys($dl)), (int)$jobId]);
    }
}
// HOLD reasons an inspector must not ignore before issuing the deliverable.
function job_hold_reasons($j) {
    $r = [];
    if (!empty($j['adv_required']) && empty($j['adv_received'])) $r[] = 'advance payment not yet received';
    if (!empty($j['report_hold']) && empty($j['payment_received'])) $r[] = 'payment pending — report only against payment';
    return $r;
}

// ---------------------------------------------------------------------------
//  Acceptance → client/contract registration → Operations hand-off (§12,§13)
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
//  What the sales conversation already knew
//
//  A client usually starts life as a name typed into an inquiry or a quotation,
//  with a person's name, an e-mail, a mobile, a business unit and a list of the
//  inspections they are asking for. When somebody finally opens the master and
//  completes it, all of that is sitting three screens away and gets typed again
//  — or, more often, not typed at all.
//
//  So the moment a client record exists, it takes what the sales side already
//  recorded: the types of inspection wanted, the first contact, and the Business Unit.
//  Nothing is overwritten — an inspection type already ticked stays ticked, and
//  a contact of the same name is not added twice.
// ---------------------------------------------------------------------------
function partner_carry_from_crm($partnerId, $name = '') {
    $partnerId = (int)$partnerId;
    $p = $partnerId ? ops_one("SELECT * FROM business_partners WHERE id=?", [$partnerId]) : null;
    if (!$p) return ['linked' => 0, 'types' => 0, 'contacts' => 0];
    $name = trim((string)$name) ?: trim((string)($p['legal_name'] ?? ''));
    $norm = normalize_name($name);
    $linked = 0; $types = 0; $contacts = 0;

    // Everything the sales side holds for this company, whether it was linked
    // to the record or only ever carried the name.
    $rows = [];
    foreach ([['crm_inquiries', 'sbu', ''], ['quotations', 'sbu', 'inspection_types']] as [$t, $sbuCol, $typeCol]) {
        try {
            foreach (ops_all("SELECT * FROM $t WHERE client_id = ? OR client_id IS NULL OR client_id = 0", [$partnerId]) as $r) {
                $rn = normalize_name((string)($r['client_name'] ?? ''));
                if ((int)($r['client_id'] ?? 0) !== $partnerId && ($rn === '' || $rn !== $norm)) continue;
                $r['_table'] = $t; $r['_types'] = $typeCol;
                $rows[] = $r;
                // Same company, so say so — the register stops showing it as a
                // loose name and every report can count it.
                if ((int)($r['client_id'] ?? 0) !== $partnerId) {
                    db()->prepare("UPDATE $t SET client_id=? WHERE id=?")->execute([$partnerId, $r['id']]);
                    $linked++;
                }
            }
        } catch (Throwable $e) { continue; }
    }
    if (!$rows) return ['linked' => 0, 'types' => 0, 'contacts' => 0];

    // The inspections they have been asking for, merged with anything already ticked.
    $have = array_values(array_filter(array_map('trim', explode(',', (string)($p['inspection_types'] ?? '')))));
    $before = count($have);
    foreach ($rows as $r) {
        if (($r['_types'] ?? '') === '') continue;
        foreach (array_filter(array_map('trim', explode(',', (string)($r[$r['_types']] ?? '')))) as $t)
            if ($t !== '' && !in_array($t, $have, true)) $have[] = $t;
    }
    if (count($have) > $before) {
        db()->prepare("UPDATE business_partners SET inspection_types=? WHERE id=?")
            ->execute([implode(',', $have), $partnerId]);
        $types = count($have) - $before;
    }

    // The person they have been talking to. A contact of the same name is left
    // alone — the master's version is the one somebody checked.
    foreach ($rows as $r) {
        $cn = trim((string)($r['contact_name'] ?? ''));
        if ($cn === '') continue;
        $exists = ops_val("SELECT id FROM partner_contacts WHERE partner_id=? AND LOWER(name)=?",
                          [$partnerId, strtolower($cn)]);
        if ($exists) continue;
        $isFirst = !ops_val("SELECT id FROM partner_contacts WHERE partner_id=?", [$partnerId]);
        db()->prepare("INSERT INTO partner_contacts (partner_id,name,email,mobile,is_primary,department)
                       VALUES (?,?,?,?,?,?)")
            ->execute([$partnerId, $cn, trim((string)($r['contact_email'] ?? '')),
                       trim((string)($r['contact_mobile'] ?? '')), $isFirst ? 1 : 0,
                       $r['_table'] === 'quotations' ? 'From ' . T('quote') : 'From ' . T('inquiry')]);
        $contacts++;
    }
    return ['linked' => $linked, 'types' => $types, 'contacts' => $contacts];
}
// One sentence for the flash message, or '' when nothing came across.
function partner_carry_text($res) {
    $bits = [];
    if ($res['types'])    $bits[] = $res['types'] . ' type(s) of inspection';
    if ($res['contacts']) $bits[] = $res['contacts'] . ' contact(s)';
    if ($res['linked'])   $bits[] = $res['linked'] . ' ' . Tl('inquiry') . '/' . Tl('quote') . ' linked to this record';
    if (!$bits) return '';
    $last = array_pop($bits);
    return 'Carried across from the sales side: ' . ($bits ? implode(', ', $bits) . ' and ' . $last : $last) . '.';
}

// Ensure the quote's customer is a registered client; create one if only a name
// was typed. Returns the business_partner id (or null if there is nothing to use).
function crm_register_client_for_quote($q) {
    if (!empty($q['client_id'])) return (int)$q['client_id'];
    $name = trim($q['client_name'] ?? ''); if ($name === '') return null;
    $ex = ops_val("SELECT id FROM business_partners WHERE is_client=1 AND (legal_name=? OR display_name=?) LIMIT 1", [$name, $name]);
    if ($ex) return (int)$ex;
    $token = function_exists('short_token') ? short_token($name) : strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 4));
    $last = ops_val("SELECT code FROM business_partners WHERE code LIKE ? ORDER BY code DESC LIMIT 1", ["GEN-$token-%"]);
    $seq = $last ? ((int)substr($last, strrpos($last, '-') + 1)) + 1 : 1;
    $code = sprintf("GEN-%s-%04d", $token, $seq);
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute([$code, $name, $name, date('c')]);
    $pid = (int)db()->lastInsertId();
    // Bring the sales conversation with it rather than leaving a bare name.
    partner_carry_from_crm($pid, $name);
    return $pid;
}

// ---------------------------------------------------------------------------
//  Winning it puts the company on file
//
//  Up to acceptance a quotation may name a company that exists nowhere but in
//  that one box — which is right, because most of them never come to anything
//  and a master full of hopefuls is worse than useless. The moment it is won
//  that stops being true: somebody is going to raise an order against it, book
//  inspections against it and invoice it, and every one of those screens reads
//  the client master, not the quotation.
//
//  So acceptance registers the company whether or not anybody has the GSTIN
//  yet. Deliberately incomplete: the record exists, the quotation points at it,
//  the types of inspection and the contact come across, and the missing tax and
//  address details are filled in when they arrive. A record waiting to be
//  completed is a job somebody can finish; a name that was never written down
//  is a job nobody knows about.
// ---------------------------------------------------------------------------
// The deal a quotation belongs to — attached to it explicitly, or the deal that
// grew from the same lead. One place, so a quote's answer (sent, accepted, lost)
// can move its deal without every branch re-deriving the link.
function quote_linked_deal_id($q) {
    try {
        $qid = (int)$q['id']; $base = (int)(($q['parent_id'] ?? 0) ?: $qid);
        $oid = (int)(ops_val("SELECT opportunity_id FROM opportunity_quotes WHERE quotation_id IN (?,?) ORDER BY id DESC LIMIT 1", [$qid, $base]) ?: 0);
        if (!$oid && !empty($q['lead_id']))
            $oid = (int)(ops_val("SELECT id FROM opportunities WHERE lead_id=? ORDER BY id DESC LIMIT 1", [(int)$q['lead_id']]) ?: 0);
        return $oid;
    } catch (Throwable $e) { return 0; }
}

function quote_land_on_client($q) {
    $out = ['client_id' => 0, 'created' => false, 'chain' => 0, 'carry' => null, 'name' => ''];
    $had = (int)($q['client_id'] ?? 0);
    $name = trim((string)($q['client_name'] ?? ''));
    $out['name'] = $name;
    $cid = crm_register_client_for_quote($q);
    if (!$cid) return $out;                       // no name at all — nothing to register
    $out['client_id'] = (int)$cid;
    $out['created'] = !$had && !ops_val(
        "SELECT id FROM quotations WHERE client_id=? AND id<>?", [$cid, (int)$q['id']]);

    // Every revision of this quotation is the same conversation with the same
    // company, so the whole chain points at the record — otherwise R00 shows as
    // a loose name for ever and the registers double-count.
    $base = (int)(($q['parent_id'] ?? 0) ?: $q['id']);
    $st = db()->prepare("UPDATE quotations SET client_id=? WHERE (id=? OR id=? OR parent_id=?)
                          AND (client_id IS NULL OR client_id=0)");
    $st->execute([$cid, (int)$q['id'], $base, $base]);
    $out['chain'] = $st->rowCount();

    // The inquiry it grew out of, and any other loose row carrying the same name.
    if (!empty($q['inquiry_id']))
        db()->prepare("UPDATE crm_inquiries SET client_id=? WHERE id=? AND (client_id IS NULL OR client_id=0)")
            ->execute([$cid, (int)$q['inquiry_id']]);

    // Types of inspection and the person we have been talking to.
    $out['carry'] = partner_carry_from_crm($cid, $name);
    idems_log('partner', $cid, 'UPDATE', ['field' => 'from ' . Tl('quote'),
        'new' => (string)$q['quote_no'] . ' accepted']);
    return $out;
}

// What to tell the person who just marked it won — including the part they have
// to finish, because a master that is missing its tax details will refuse an
// inspection call later and that is much better said now than then.
function quote_landed_text($q, $res) {
    $head = Tl('quote') . ' marked accepted.';
    if (!$res['client_id'])
        return $head . ' No ' . Tl('client') . ' name was on it, so nothing could be put on file — '
             . 'add the name and mark it accepted again.';
    $link = '/partner?id=' . (int)$res['client_id'];
    $bits = [];
    $bits[] = $res['created']
        ? $res['name'] . ' has been created in the ' . Tl('client') . ' master'
        : $res['name'] . ' is now linked to the ' . Tl('client') . ' master';
    if (($res['chain'] ?? 0) > 1) $bits[] = 'all ' . (int)$res['chain'] . ' revisions with it';
    $c = $res['carry'] ?? null;
    if ($c && ($c['types'] ?? 0)) $bits[] = (int)$c['types'] . ' type(s) of inspection carried across';
    if ($c && ($c['contacts'] ?? 0)) $bits[] = (int)$c['contacts'] . ' contact(s) carried across';
    $msg = $head . ' ' . implode(', ', $bits) . '.';
    // The order tab can now offer this quotation, which is the whole point.
    $msg .= ' Its line items can now be pulled straight onto a purchase order from the '
          . Tl('client') . ' master.';
    $miss = function_exists('partner_missing_text') ? partner_missing_text($res['client_id'], 'client') : '';
    if ($miss !== '') $msg .= ' The record is deliberately incomplete — it still needs ' . $miss
                     . '. Complete it before the first ' . Tl('call') . ' is raised against it.';
    return $msg;
}

// Build + send the Operations packet: client, quote/contract no, contacts, service
// requirement, order lines (open vs line-item), advance/payment flags, TC proposal.
function crm_float_ops_packet($q) {
    $lines = crm_quote_lines($q['id']);
    $svc = $q['subject'] ?: '';
    if (!empty($q['inquiry_id'])) { $ir = ops_one("SELECT service_requirement FROM crm_inquiries WHERE id=?", [$q['inquiry_id']]); if ($ir && trim($ir['service_requirement'] ?? '') !== '') $svc = $ir['service_requirement']; }
    $b = "New confirmed order — please action.\n\n"
        . "Client: " . $q['client_name'] . "\n"
        . "Quotation: " . quote_label($q) . "\n"
        . "Contract: " . ($q['contract_number'] ?: '(pending)') . "\n"
        . "Contact: " . trim($q['contact_name'] . ' · ' . $q['contact_email'] . ' · ' . $q['contact_mobile'], ' ·') . "\n"
        . "Business Unit: " . (lk_options_or('sbu', OPS_SBUS)[$q['sbu']] ?? $q['sbu']) . "\n"
        . "Location: " . ($q['site_location'] ?: '—') . " (" . (lk_options_or('quote_location_type', QUOTE_LOCATION_TYPES)[$q['location_type']] ?? $q['location_type']) . ")\n"
        . "Value: " . cur_sym() . number_format((float)$q['total_amount'], 0) . "\n"
        . ($q['advance_required'] ? "** ADVANCE REQUIRED before scheduling (" . rtrim(rtrim(number_format((float)$q['advance_pct'], 2), '0'), '.') . "%) **\n" : "")
        . ($q['report_vs_payment'] ? "** Deliverable/report only against payment **\n" : "")
        . "\nService requirement:\n" . $svc . "\n\nOrder lines:\n";
    foreach ($lines as $i => $l) {
        $b .= ($i + 1) . ". [" . (lk_options_or('order_type', ORDER_TYPES)[$l['order_type']] ?? $l['order_type']) . "] " . $l['description']
            . " — " . rtrim(rtrim(number_format((float)$l['qty'], 2), '0'), '.') . " " . (lk_options_or('charge_unit', CHARGE_UNITS)[$l['unit']] ?? $l['unit'])
            . " x " . cur_sym() . number_format((float)$l['rate'], 0) . " = " . cur_sym() . number_format((float)$l['amount'], 0) . "\n";
    }
    $att = [];
    $tpl = ops_one("SELECT * FROM crm_templates WHERE kind='QUOTE_DOC' AND active=1 ORDER BY is_default DESC, id DESC");
    if ($tpl && ($tpl['file_data'] ?? '') !== '') {
        [$bin, $err] = docx_fill(base64_decode($tpl['file_data']), quote_doc_map($q, $tpl), $lines);
        if (!$err && $bin) $att[] = ['name' => 'Quote-' . preg_replace('/[^A-Za-z0-9_.-]/', '', $q['quote_no']) . '.docx', 'data' => $bin, 'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    }
    $to = implode(',', array_filter([coordinator_emails(), manager_emails()]));
    $subject = "Order confirmed: " . $q['client_name'] . " — " . quote_label($q) . ($q['contract_number'] ? " (" . $q['contract_number'] . ")" : "");
    return ops_mail($to, $subject, $b, '', 'ops_packet', $att);
}

// ---------------------------------------------------------------------------
//  Approvals (§9) — configurable matrix by amount band and/or Business Unit
// ---------------------------------------------------------------------------
function crm_approvals($qid) { return ops_all("SELECT * FROM quote_approvals WHERE quote_id=? ORDER BY level, id", [(int)$qid]); }

// ---- Configurable pre-order review checklist (Enquiry / Tender / Contract) ----
// A list of points reviewed before a quotation is approved — enquiry/tender/
// contract review, blocked-client and commercial checks. Entirely admin-configured
// and switchable on/off; off by default, so nothing changes until it is enabled.
function preorder_checklist_enabled() {
    return function_exists('setting_get') && (string)setting_get('preorder_checklist_on', '') === '1';
}
function preorder_checklist_require_all() {
    return function_exists('setting_get') && (string)setting_get('preorder_checklist_require', '') === '1';
}
function preorder_checklist_items() {
    $raw = function_exists('setting_get') ? (string)setting_get('preorder_checklist_items', '') : '';
    $out = [];
    foreach (preg_split('/\r?\n/', $raw) as $ln) { $ln = trim($ln); if ($ln !== '') $out[] = $ln; }
    return $out;
}
// A starter list, offered on the settings screen for the admin to edit/replace.
function preorder_checklist_default() {
    return implode("\n", [
        'Enquiry / tender reviewed — scope, standards and deliverables are clear',
        'Client is not on hold or blocked',
        'Commercials (rates, taxes, currency) checked and within delegation',
        'Payment terms, advance and validity agreed',
        'Executing office(s) and capability confirmed',
        'Any EMD / bank guarantee / security deposit noted',
        'Impartiality / conflict of interest considered',
    ]);
}
function preorder_item_key($item) { return 'c' . substr(md5(trim((string)$item)), 0, 10); }
function preorder_checklist_state($q) {
    $j = json_decode((string)($q['preorder_checklist'] ?? ''), true);
    return is_array($j) ? $j : [];
}
// How many of the configured points are ticked on this quotation.
function preorder_checklist_progress($q) {
    $items = preorder_checklist_items(); $state = preorder_checklist_state($q);
    $done = 0; foreach ($items as $it) if (!empty($state[preorder_item_key($it)])) $done++;
    return ['done' => $done, 'total' => count($items), 'complete' => $done >= count($items)];
}
// Build the approval steps for a quote from the active rules. A rule matches when
// its Business Unit matches (or it is "any Business Unit") and the quote total falls in its amount band
// (max 0 = no upper limit). If nothing matches, one generic step (any approver).
function crm_build_approvals($q) {
    $pdo = db();
    $pdo->prepare("DELETE FROM quote_approvals WHERE quote_id=?")->execute([$q['id']]);
    $amt = (float)$q['total_amount']; $sbu = $q['sbu'];
    $steps = [];
    foreach (ops_all("SELECT * FROM quote_approval_rules WHERE active=1 ORDER BY level, id") as $r) {
        if (($r['match_type'] ?? 'ANY') === 'Business Unit' && ($r['sbu'] ?? '') !== '' && $r['sbu'] !== $sbu) continue;
        if ($amt < (float)$r['min_amount']) continue;
        if ((float)$r['max_amount'] > 0 && $amt > (float)$r['max_amount']) continue;
        $steps[] = $r;
    }
    if (!$steps) {
        $pdo->prepare("INSERT INTO quote_approvals (quote_id,level,approver_role,approver_user_id,status) VALUES (?,1,'',NULL,'PENDING')")->execute([$q['id']]);
        return 1;
    }
    $ownerId = crm_quote_owner_user_id($q);
    $ins = $pdo->prepare("INSERT INTO quote_approvals (quote_id,level,approver_role,approver_user_id,status) VALUES (?,?,?,?,'PENDING')");
    foreach ($steps as $s) {
        $role = $s['approver_role'] ?? '';
        $uid  = ($s['approver_user_id'] ?? null) ?: null;
        // A "REPORTS_TO" rule routes to the sales owner's reporting manager N levels up
        // (rule level = how far up the chain). Resolves to a concrete user at build time.
        if ($role === 'REPORTS_TO') {
            $resolved = $ownerId && function_exists('reporting_manager_at') ? reporting_manager_at($ownerId, (int)$s['level']) : null;
            $role = ''; $uid = $resolved ?: null;   // unresolved → generic step (any approver)
        }
        $ins->execute([$q['id'], (int)$s['level'], $role, $uid]);
    }
    return count($steps);
}
// The system-user id who owns a quote (for reporting-chain approvals): owner_id if
// set, else the user whose username or full name matches created_by. Matched in PHP
// so it works identically on MySQL and SQLite (no DB-specific string concat).
function crm_quote_owner_user_id($q) {
    if (!empty($q['owner_id'])) return (int)$q['owner_id'];
    $cb = trim((string)($q['created_by'] ?? ''));
    if ($cb === '') return null;
    $u = ops_one("SELECT id FROM users WHERE username=? LIMIT 1", [$cb]);
    if ($u) return (int)$u['id'];
    foreach (ops_all("SELECT id, first_name, last_name FROM users WHERE is_active=1") as $r) {
        if (strcasecmp(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')), $cb) === 0) return (int)$r['id'];
    }
    return null;
}
// May the current user act on this approval step?
// ---- Who is it sitting with? (§xii) ----------------------------------------
// Turn an approval step into the actual people who can act on it, so the quote
// screen can say "waiting on <name>" instead of "pending".
function crm_step_approvers($step) {
    if (!empty($step['approver_user_id'])) {
        $u = ops_one("SELECT id, username, first_name, last_name, email, role FROM users WHERE id=?", [(int)$step['approver_user_id']]);
        return $u ? [$u] : [];
    }
    if (($step['approver_role'] ?? '') !== '') {
        return ops_all("SELECT id, username, first_name, last_name, email, role FROM users WHERE role=? AND is_active=1 ORDER BY first_name, username", [$step['approver_role']]);
    }
    // A generic step is open to anyone who may approve quotes.
    $roles = [];
    foreach (ORG_ROLES as $rk => $rl) if (role_perms($rk) && in_array('crm.quote.approve', role_perms($rk), true)) $roles[] = $rk;
    if (!$roles) return [];
    $ph = implode(',', array_fill(0, count($roles), '?'));
    return ops_all("SELECT id, username, first_name, last_name, email, role FROM users WHERE role IN ($ph) AND is_active=1 ORDER BY first_name, username", $roles);
}
function crm_person_label($u) {
    $nm = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['username'] ?? '');
    $role = ORG_ROLES[$u['role'] ?? ''] ?? ($u['role'] ?? '');
    return $role !== '' ? "$nm ($role)" : $nm;
}
// The lowest still-pending level, and who it is with — as a readable phrase.
function crm_pending_with($qid) {
    $step = ops_one("SELECT * FROM quote_approvals WHERE quote_id=? AND status='PENDING' ORDER BY level, id LIMIT 1", [(int)$qid]);
    if (!$step) return '';
    $people = crm_step_approvers($step);
    if (!$people) return ($step['approver_role'] ?? '') !== ''
        ? ('anyone with the role ' . (ORG_ROLES[$step['approver_role']] ?? $step['approver_role']))
        : 'any approver';
    $names = array_map('crm_person_label', array_slice($people, 0, 3));
    $more = count($people) - count($names);
    return implode(', ', $names) . ($more > 0 ? " and $more other(s)" : '');
}
function crm_pending_step($qid) {
    return ops_one("SELECT * FROM quote_approvals WHERE quote_id=? AND status='PENDING' ORDER BY level, id LIMIT 1", [(int)$qid]);
}
// How many quotations are sitting on ME right now for approval — used by the
// dashboard "Your pending tasks" list. A quote counts when its CURRENT step (the
// lowest still-pending level) is one I may sign: named to me, addressed to my
// role, or a generic step and I hold the approve permission. Scope-respecting;
// returns 0 quietly if the tables are not present.
function crm_quotes_awaiting_me() {
    $u = function_exists('current_user') ? current_user() : null;
    if (!$u) return 0;
    $me   = (int)($u['id'] ?? 0);
    $role = function_exists('user_role') ? (string)user_role() : (string)($u['role'] ?? '');
    $canApprove = (function_exists('can') && can('crm.quote.approve')) || (function_exists('is_master') && is_master());
    try {
        // NO office scope here: a quote routed to this person for approval must
        // reach them even if it belongs to another office. The routing IS the
        // authority — office scope would strand the very approval assigned to them
        // (e.g. a quote with no office, or another branch's, waiting on this BM).
        $sql = "SELECT COUNT(DISTINCT q.id)
                FROM quotations q
                JOIN quote_approvals a ON a.quote_id=q.id AND a.status='PENDING'
                     AND a.level = (SELECT MIN(a2.level) FROM quote_approvals a2 WHERE a2.quote_id=q.id AND a2.status='PENDING')
                WHERE q.is_current=1 AND q.status='PENDING_APPROVAL'
                  AND ( a.approver_user_id = ?
                        OR (a.approver_role = ? AND (a.approver_user_id IS NULL OR a.approver_user_id=0))
                        OR ((a.approver_role='' OR a.approver_role IS NULL)
                            AND (a.approver_user_id IS NULL OR a.approver_user_id=0) AND ?) )";
        return (int) ops_val($sql, [$me, $role, $canApprove ? 1 : 0]);
    } catch (Throwable $e) { return 0; }
}
// Tell the sales owner what the approver decided.
function crm_notify_owner($q, $what, $remarks = '') {
    $uid = $q['owner_id'] ?? null;
    if (!$uid) return;
    $u = ops_one("SELECT email, first_name, username FROM users WHERE id=?", [(int)$uid]);
    if (!$u || trim((string)$u['email']) === '') return;
    $label = quote_label($q);
    $body = "Hello " . (trim($u['first_name'] ?? '') ?: $u['username']) . ",\n\n"
          . "$label ({$q['client_name']}) has been $what.\n"
          . ($remarks !== '' ? "\nComment: $remarks\n" : '')
          . "\n" . app_name();
    ops_mail($u["email"], "$label — $what", $body, "", "QUOTE_DECISION");
}

function crm_can_act_approval($step) {
    if (($step['status'] ?? '') !== 'PENDING') return false;
    if (is_master()) return true;
    $u = current_user();
    // Being named on the step IS the authority to act on it: the approval matrix
    // routed this quote here on purpose, so a person addressed by name — or by
    // their role — may approve it even if their role does not carry the blanket
    // crm.quote.approve permission. (That is exactly the branch-manager case: the
    // step is routed to BRANCH_MANAGER, so the branch manager can sign it.)
    if (!empty($step['approver_user_id'])) return (int)$step['approver_user_id'] === (int)($u['id'] ?? 0);
    if (($step['approver_role'] ?? '') !== '') return user_role() === $step['approver_role'];
    // A generic "any approver" step is open to anyone who holds the permission.
    return can('crm.quote.approve');
}
// Can the current user act on any still-pending approval step of this quote? True
// for the routed approver — used so they can also tick the pre-order checklist
// that gates their own approval, even without a blanket quote permission.
function crm_can_act_on_quote($qid) {
    foreach (crm_approvals((int)$qid) as $st) if (crm_can_act_approval($st)) return true;
    return false;
}

// ---------------------------------------------------------------------------
//  E-mail: send the quote to the customer (§10) + follow-ups (§11)
// ---------------------------------------------------------------------------
// Fill {{token}}s in an e-mail subject/body from the quotation.
function crm_fill_email($text, $q) {
    $m = [
        'quote_no' => $q['quote_no'], 'quote_label' => quote_label($q), 'subject' => $q['subject'],
        'client_name' => $q['client_name'], 'contact_name' => $q['contact_name'],
        'total_amount' => cur_sym() . number_format((float)$q['total_amount'], 0), 'validity_days' => $q['validity_days'],
        'sbu' => lk_options_or('sbu', OPS_SBUS)[$q['sbu']] ?? $q['sbu'], 'app_name' => app_name(),
    ];
    foreach ($m as $k => $v) $text = str_replace('{{' . $k . '}}', (string)$v, $text);
    return $text;
}
function crm_default_quote_email($q) {
    return "Dear " . ($q['contact_name'] ?: 'Sir/Madam') . ",\n\n"
        . "Please find attached our quotation " . quote_label($q) . " for " . ($q['subject'] ?: 'your requirement') . ".\n"
        . "Total value: " . cur_sym() . number_format((float)$q['total_amount'], 0) . " (valid " . (int)$q['validity_days'] . " days).\n\n"
        . "We look forward to your confirmation.\n\nBest regards,\n" . app_name();
}
function crm_default_followup_email($q, $kind = '') {
    return "Dear " . ($q['contact_name'] ?: 'Sir/Madam') . ",\n\n"
        . "Gentle follow-up on our quotation " . quote_label($q) . " for " . ($q['subject'] ?: 'your requirement')
        . " (" . cur_sym() . number_format((float)$q['total_amount'], 0) . ").\n"
        . "We would be glad to address any questions or revise as needed.\n\nBest regards,\n" . app_name();
}
// Subject + body for a quotation e-mail, from the template if one is set up.
// Shared by the automatic send and by the "open in my mailbox" draft, so the
// customer gets the same wording either way. When the person sends it
// themselves the sign-off is theirs, not the app's.
function crm_quote_message($q, $personal = true) {
    $et = ops_one("SELECT * FROM crm_templates WHERE kind='EMAIL_QUOTE' AND active=1 ORDER BY is_default DESC, id DESC");
    $subject = ($et && $et['subject']) ? crm_fill_email($et['subject'], $q)
                                       : (ucfirst(Tl('quote')) . ' ' . quote_label($q) . ' — ' . app_name());
    $body = ($et && $et['body']) ? crm_fill_email($et['body'], $q) : crm_default_quote_email($q);
    if ($personal) {
        $u = current_user();
        $nm = $u ? user_name($u) : '';
        if ($nm !== '') {
            // Sign off as the person actually sending it.
            $body = preg_replace('/Best regards,\s*\n' . preg_quote(app_name(), '/') . '\s*$/u',
                "Best regards,\n" . $nm . "\n" . app_name(), $body);
        }
    }
    return [$subject, $body];
}

// §xx — the signature stamped on a quote PDF. If the quote names a signatory,
// their signature stored in the system is used, so the PDF is signed by the
// person who actually authorised it. Otherwise the company signature applies.
function quote_signature($q = null) {
    if ($q && !empty($q['signatory_user_id'])) {
        $u = ops_one("SELECT first_name, last_name, username, role, signature FROM users WHERE id=?", [(int)$q['signatory_user_id']]);
        if ($u && trim((string)$u['signature']) !== '') {
            $raw = (string)$u['signature'];
            // stored as a data URL; take the payload
            if (strpos($raw, 'base64,') !== false) $raw = substr($raw, strpos($raw, 'base64,') + 7);
            $img = base64_decode($raw, true);
            if ($img !== false && $img !== '') {
                return [
                    'img'   => $img,
                    'name'  => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username'],
                    'desig' => ORG_ROLES[$u['role'] ?? ''] ?? ($u['role'] ?? ''),
                ];
            }
        }
    }
    $img = setting_get('quote_sig_img', '');
    return ['img' => $img ? base64_decode($img) : '', 'name' => setting_get('quote_sig_name', ''), 'desig' => setting_get('quote_sig_desig', '')];
}
// Customisable letterhead for the client PDF (company logo / name / address / contact).
function quote_letterhead() {
    $logo = setting_get('quote_lh_logo', '');
    // GSTIN comes from the single "Your company" profile so it prints on every
    // document without being typed here as well.
    $gstin = setting_get('company_gstin', '');
    // The company brand colour for the header band / accents (hex, e.g. #1E40AF).
    $band = brand_color_rgb(setting_get('report_brand_color', ''));
    return [
        'logo' => $logo ? base64_decode($logo) : '',
        'name' => setting_get('quote_lh_name', '') ?: app_name(),
        'address' => setting_get('quote_lh_address', ''),
        'contact' => setting_get('quote_lh_contact', ''),
        'gstin' => $gstin ? ('GSTIN: ' . $gstin) : '',
        'footer' => setting_get('quote_lh_footer', ''),
        'band' => $band,   // [r,g,b] or null → renderer uses its default
    ];
}
// Parse a #RRGGBB (or RRGGBB) brand colour to [r,g,b], or null if blank/invalid.
function brand_color_rgb($hex) {
    $hex = trim((string)$hex);
    if (preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $m)) {
        return [hexdec(substr($m[1], 0, 2)), hexdec(substr($m[1], 2, 2)), hexdec(substr($m[1], 4, 2))];
    }
    return null;
}
// Generate the signed PDF and e-mail it to the customer. Returns [sentBool, message].
function crm_send_quote_email($q) {
    if (($q['contact_email'] ?? '') === '') return [false, 'no customer e-mail on the quotation (it was not e-mailed).'];
    $att = [];
    $tpl = ops_one("SELECT * FROM crm_templates WHERE kind='QUOTE_DOC' ORDER BY is_default DESC, id DESC");
    $pdf = quote_pdf_build($q, crm_quote_lines($q["id"]), $tpl ?: [], quote_signature($q), quote_letterhead());
    if ($pdf) $att[] = ['name' => 'Quote-' . preg_replace('/[^A-Za-z0-9_.-]/', '', $q['quote_no']) . '.pdf', 'data' => $pdf, 'type' => 'application/pdf'];
    $et = ops_one("SELECT * FROM crm_templates WHERE kind='EMAIL_QUOTE' AND active=1 ORDER BY is_default DESC, id DESC");
    $subject = ($et && $et['subject']) ? crm_fill_email($et['subject'], $q) : ('Quotation ' . $q['quote_no'] . ' — ' . app_name());
    $body = ($et && $et['body']) ? crm_fill_email($et['body'], $q) : crm_default_quote_email($q);
    $ok = ops_mail($q['contact_email'], $subject, $body, '', 'quote_send', $att);
    return [(bool)$ok, $ok ? '' : 'e-mail could not be sent now (check Settings → SMTP) — it has been logged.'];
}
// Cron: send any due follow-ups whose quote is still awaiting a reply (§11).
function crm_run_followups($today = null) {
    $today = $today ?: date('Y-m-d'); $sent = 0; $pdo = db();
    $due = ops_all("SELECT f.id fid, f.kind, q.id qid, q.status qstatus, q.contact_email
        FROM quote_followups f JOIN quotations q ON q.id=f.quote_id
        WHERE f.status='PENDING' AND f.due_date<=?", [$today]);
    $et = ops_one("SELECT * FROM crm_templates WHERE kind='EMAIL_FOLLOWUP' AND active=1 ORDER BY is_default DESC, id DESC");
    foreach ($due as $f) {
        if ($f['qstatus'] !== 'SENT' || ($f['contact_email'] ?? '') === '') { $pdo->prepare("UPDATE quote_followups SET status='SKIPPED' WHERE id=?")->execute([$f['fid']]); continue; }
        $q = crm_quote_get($f['qid']);
        $subject = ($et && $et['subject']) ? crm_fill_email($et['subject'], $q) : ('Following up — quotation ' . $q['quote_no']);
        $body = ($et && $et['body']) ? crm_fill_email($et['body'], $q) : crm_default_followup_email($q, $f['kind']);
        ops_mail($q['contact_email'], $subject, $body, '', 'quote_followup');
        $pdo->prepare("UPDATE quote_followups SET status='SENT', sent_at=? WHERE id=?")->execute([date('c'), $f['fid']]);
        $sent++;
    }
    return $sent;
}

// ---------------------------------------------------------------------------
//  Handlers — approval-rule matrix admin
// ---------------------------------------------------------------------------
function ops_crm_approval_rules($route, $method) {
    ops_require(can('crm.quote.approve') || is_master(), 'You cannot manage approval rules.');
    $pdo = db();
    if ($route === 'quote-approval-rules') {
        $rows = ops_all("SELECT r.*, u.username, u.first_name, u.last_name FROM quote_approval_rules r LEFT JOIN users u ON u.id=r.approver_user_id ORDER BY r.level, r.id");
        view('ops/crm/approval_rule_list', ['rows' => $rows]); return;
    }
    if ($route === 'quote-approval-rule-delete' && $method === 'POST') {
        $pdo->prepare("DELETE FROM quote_approval_rules WHERE id=?")->execute([(int)($_GET['id'] ?? 0)]);
        flash('Approval rule deleted.'); redirect('/quote-approval-rules');
    }
    if ($route === 'quote-approval-rule-new' || $route === 'quote-approval-rule-edit') {
        $r = null;
        if ($route === 'quote-approval-rule-edit') { $r = ops_one("SELECT * FROM quote_approval_rules WHERE id=?", [(int)($_GET['id'] ?? 0)]); if (!$r) { http_response_code(404); view('notfound'); return; } }
        if ($method === 'POST') {
            $b = $_POST;
            $mt = ($b['match_type'] ?? 'ANY') === 'Business Unit' ? 'Business Unit' : 'ANY';
            $vals = [trim($b['name'] ?? ''), $mt, $mt === 'Business Unit' ? ($b['sbu'] ?? '') : '', (float)($b['min_amount'] ?? 0), (float)($b['max_amount'] ?? 0),
                max(1, (int)($b['level'] ?? 1)), $b['approver_role'] ?? '', ($b['approver_user_id'] ?? '') !== '' ? (int)$b['approver_user_id'] : null, !empty($b['active']) ? 1 : 0];
            if ($r) {
                $pdo->prepare("UPDATE quote_approval_rules SET name=?,match_type=?,sbu=?,min_amount=?,max_amount=?,level=?,approver_role=?,approver_user_id=?,active=? WHERE id=?")
                    ->execute(array_merge($vals, [$r['id']]));
                flash('Approval rule saved.');
            } else {
                $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute(array_merge($vals, [date('c')]));
                flash('Approval rule added.');
            }
            redirect('/quote-approval-rules');
        }
        view('ops/crm/approval_rule_form', ['r' => $r, 'sbuOpts' => lk_options_or('sbu', OPS_SBUS), 'roles' => ORG_ROLES,
            'users' => ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name, username")]); return;
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
        'l_service' => lk_options_or('inspection_type', INSPECTION_TYPES)[$l['service_type']] ?? $l['service_type'],
        'l_subtypes' => $l['subtypes'], 'l_desc' => $l['description'], 'l_location' => $l['location'],
        'l_order' => lk_options_or('order_type', ORDER_TYPES)[$l['order_type']] ?? $l['order_type'],
        'l_qty' => rtrim(rtrim(number_format((float)$l['qty'], 2), '0'), '.'),
        'l_unit' => lk_options_or('charge_unit', CHARGE_UNITS)[$l['unit']] ?? $l['unit'],
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
        'site_location' => $q['site_location'], 'location_type' => lk_options_or('quote_location_type', QUOTE_LOCATION_TYPES)[$q['location_type']] ?? $q['location_type'],
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
    if ($route === 'crm-signature' && $method === 'POST') {
        if (!empty($_FILES['sig']['tmp_name']) && (int)$_FILES['sig']['error'] === 0) {
            [$jpg, $err] = signature_to_jpeg(file_get_contents($_FILES['sig']['tmp_name']));
            if ($err) { flash('Signature: ' . $err, 'error'); redirect('/crm-templates'); }
            setting_set('quote_sig_img', base64_encode($jpg));
        }
        if (!empty($_POST['clear_sig'])) setting_set('quote_sig_img', '');
        setting_set('quote_sig_name', trim($_POST['sig_name'] ?? ''));
        setting_set('quote_sig_desig', trim($_POST['sig_desig'] ?? ''));
        flash('Signature settings saved — they will appear on generated quote PDFs.');
        redirect('/crm-templates');
    }
    if ($route === 'crm-letterhead' && $method === 'POST') {
        if (!empty($_FILES['logo']['tmp_name']) && (int)$_FILES['logo']['error'] === 0) {
            [$jpg, $err] = signature_to_jpeg(file_get_contents($_FILES['logo']['tmp_name']));
            if ($err) { flash('Logo: ' . $err, 'error'); redirect('/crm-templates'); }
            setting_set('quote_lh_logo', base64_encode($jpg));
        }
        if (!empty($_POST['clear_logo'])) setting_set('quote_lh_logo', '');
        setting_set('quote_lh_name', trim($_POST['lh_name'] ?? ''));
        setting_set('quote_lh_address', trim($_POST['lh_address'] ?? ''));
        setting_set('quote_lh_contact', trim($_POST['lh_contact'] ?? ''));
        setting_set('quote_lh_footer', trim($_POST['lh_footer'] ?? ''));
        flash('Letterhead saved — it appears on the client PDF.');
        redirect('/crm-templates');
    }
    if ($route === 'crm-templates') {
        $rows = ops_all("SELECT id, kind, name, document_number, format_number, doc_revision, issue_date, file_name, active, is_default FROM crm_templates ORDER BY kind, name");
        view('ops/crm/template_list', ['rows' => $rows,
            'sigSet' => setting_get('quote_sig_img', '') !== '', 'sigName' => setting_get('quote_sig_name', ''), 'sigDesig' => setting_get('quote_sig_desig', ''),
            'lhLogo' => setting_get('quote_lh_logo', '') !== '', 'lhName' => setting_get('quote_lh_name', ''), 'lhAddress' => setting_get('quote_lh_address', ''),
            'lhContact' => setting_get('quote_lh_contact', ''), 'lhFooter' => setting_get('quote_lh_footer', '')]);
        return;
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
            $kind = isset(lk_options_or('template_kind', CRM_TEMPLATE_KINDS)[$b['kind'] ?? '']) ? $b['kind'] : 'QUOTE_DOC';
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
// ---------------------------------------------------------------------------
//  Sales / CRM dashboard + monthly report + win/loss analytics (§14,§15)
// ---------------------------------------------------------------------------
function ops_crm_reports() {
    ops_require(can('mod.crm_reports.view') || is_master_of('crm_reports'), 'You do not have access to sales reports.');
    $fy = $_GET['fy'] ?? current_fy();
    [$from, $to] = fy_range($fy);
    [$sw, $sa] = scope_clause('q.office_id', 'q.sbu');
    $rows = ops_all("SELECT q.*, bp.display_name client_disp FROM quotations q LEFT JOIN business_partners bp ON bp.id=q.client_id WHERE q.is_current=1 AND $sw", $sa);
    // keep FY by created date
    $rows = array_values(array_filter($rows, function ($r) use ($from, $to) { $d = substr((string)$r['created_at'], 0, 10); return $d >= $from && $d <= $to; }));

    $sbuLbl = lk_options_or('sbu', OPS_SBUS); $lostLbl = lk_options_or('quote_lost_reason', QUOTE_LOST_REASONS);
    $byStatus = []; $bySbu = []; $byClient = []; $wonByClient = []; $lost = []; $months = [];
    $totVal = 0; $wonVal = 0; $openVal = 0; $nWon = 0; $nLost = 0;
    foreach ($rows as $r) {
        $st = $r['status']; $v = (float)$r['total_amount'];
        $byStatus[lk_options_or('quote_status', QUOTE_STATUS)[$st] ?? $st] = ($byStatus[lk_options_or('quote_status', QUOTE_STATUS)[$st] ?? $st] ?? 0) + 1;
        $sk = $sbuLbl[$r['sbu']] ?? ($r['sbu'] ?: '—'); $bySbu[$sk] = ($bySbu[$sk] ?? 0) + $v;
        $ck = $r['client_disp'] ?: ($r['client_name'] ?: '—'); $byClient[$ck] = ($byClient[$ck] ?? 0) + $v;
        $totVal += $v;
        $mo = substr((string)$r['created_at'], 0, 7);
        if (!isset($months[$mo])) $months[$mo] = ['raised' => 0, 'won' => 0, 'lost' => 0, 'wonVal' => 0];
        $months[$mo]['raised']++;
        if ($st === 'ACCEPTED') { $nWon++; $wonVal += $v; $wonByClient[$ck] = ($wonByClient[$ck] ?? 0) + $v; $months[$mo]['won']++; $months[$mo]['wonVal'] += $v; }
        elseif (in_array($st, ['LOST', 'EXPIRED'], true)) { $nLost++; $lr = $r['lost_reason'] ?: '—'; $lbl = ($lr === 'OTHER' && $r['lost_reason_other']) ? $r['lost_reason_other'] : ($lostLbl[$lr] ?? $lr); $lost[$lbl] = ($lost[$lbl] ?? 0) + 1; $months[$mo]['lost']++; }
        elseif (in_array($st, QUOTE_OPEN_STATES, true)) $openVal += $v;
    }
    if (wants_csv()) {
        $csv = [['Quote', 'Client', 'Business Unit', 'Status', 'Total', 'Created', 'Accepted', 'Lost reason', 'Contract']];
        foreach ($rows as $r) $csv[] = [quote_label($r), $r['client_disp'] ?: $r['client_name'], $sbuLbl[$r['sbu']] ?? $r['sbu'],
            lk_options_or('quote_status', QUOTE_STATUS)[$r['status']] ?? $r['status'], (float)$r['total_amount'], substr((string)$r['created_at'], 0, 10),
            $r['accepted_date'], ($r['lost_reason'] === 'OTHER' && $r['lost_reason_other']) ? $r['lost_reason_other'] : ($lostLbl[$r['lost_reason']] ?? $r['lost_reason']), $r['contract_number']];
        csv_download('sales-quotes-' . $fy . '.csv', $csv);
    }
    arsort($byClient); arsort($wonByClient); arsort($lost); krsort($months);
    view('ops/crm/reports', [
        'fy' => $fy, 'fyOpts' => fy_options(6), 'total' => count($rows), 'totVal' => $totVal, 'wonVal' => $wonVal, 'openVal' => $openVal,
        'nWon' => $nWon, 'nLost' => $nLost, 'winRate' => ($nWon + $nLost) > 0 ? round($nWon / ($nWon + $nLost) * 100) : 0,
        'byStatus' => $byStatus, 'bySbu' => $bySbu, 'topClients' => array_slice($byClient, 0, 10, true),
        'wonClients' => array_slice($wonByClient, 0, 10, true), 'lost' => $lost, 'months' => $months]);
}

// ===========================================================================
//  CV analysis — dependency-free keyword extraction (§20 + owner's ask)
//  Internal engine now (works offline on shared hosting). AI-ready: when the
//  AI-keys feature lands, cv_extract_keywords() can defer to a provider.
// ===========================================================================
// Curated inspection / QA-QC / TIC domain vocabulary. Multi-word phrases first.
function cv_domain_terms() {
    static $t = null;
    if ($t !== null) return $t;
    $t = [
        // certifications / codes
        'CSWIP 3.1','CSWIP 3.2','CSWIP','BGAS','PCN','ASNT Level II','ASNT Level III','ASNT','NACE','AMPP','AWS CWI','API 510','API 570','API 571','API 653','API 580','API',
        'ASME','ASME IX','ASME VIII','ASME B31.3','AWS D1.1','AWS','ISO 9001','ISO 45001','ISO 14001','ISO 17020','ISO 17025','IRCA','PMP','Six Sigma',
        // NDT methods
        'RT','UT','MT','PT','VT','ET','PAUT','TOFD','LRUT','phased array','radiography','ultrasonic','magnetic particle','dye penetrant','eddy current','hardness testing',
        // welding
        'welding inspection','welding','WPS','PQR','WPQ','welder qualification','weld visual','GTAW','SMAW','GMAW','FCAW','SAW','brazing',
        // coating / painting
        'painting inspection','coating inspection','coating','painting','blasting','sandblasting','DFT','holiday test','galvanizing','NACE coating',
        // disciplines
        'piping','structural','mechanical','electrical','instrumentation','civil','rotating equipment','static equipment','pressure vessel','heat exchanger','storage tank','pipeline','boiler',
        // activities
        'third party inspection','TPI','vendor inspection','vendor assessment','vendor audit','expediting','stage inspection','final inspection','pre-shipment inspection','source inspection','witness inspection','FAT','SAT','surveillance','dimensional inspection','PMI','positive material identification','material verification',
        // project / deputation
        'QA/QC','QAQC','QA','QC','commissioning','erection','O&M','site supervision','resident engineer','construction','fabrication','shutdown','turnaround','HSE','HSE officer','safety',
        // sectors
        'oil and gas','oil & gas','refinery','petrochemical','power plant','solar','wind','renewable','steel plant','cement','marine','shipbuilding','mining','automotive','pharma','food',
        // education
        'B.E.','B.Tech','BE Mechanical','Diploma','ITI','M.Tech','MBA','degree','diploma',
    ];
    return $t;
}
// Whether an AI provider could enhance extraction (the AI-keys feature is not built
// yet; kept as a seam so the engine can be upgraded without touching callers).
function cv_ai_available() { return function_exists('ai_enabled') && ai_enabled(); }
// Extract keywords from CV text: (1) domain-vocabulary hits, (2) trade/skill master
// hits, (3) top frequent significant terms. Returns a de-duplicated CSV.
function cv_extract_keywords($text) {
    $text = (string)$text;
    if (trim($text) === '') return '';
    $lc = ' ' . mb_strtolower($text) . ' ';
    $found = [];   // display term => 1 (preserve nice casing)
    foreach (cv_domain_terms() as $term) {
        $needle = mb_strtolower($term);
        // word-ish boundary match (terms may contain . / & spaces)
        if (strpos($lc, ' ' . $needle . ' ') !== false || strpos($lc, $needle) !== false && preg_match('/(?<![a-z0-9])' . preg_quote($needle, '/') . '(?![a-z0-9])/u', $lc)) {
            $found[$term] = 1;
        }
    }
    // trade + skill masters (so the org's own vocabulary is recognised)
    foreach (['trade', 'skill'] as $tk) {
        $lt = function_exists('lk_type') ? lk_type($tk) : null;
        if ($lt) foreach (lk_all_values($lt['id']) as $v) {
            $lab = trim($v['label'] ?? ''); if (mb_strlen($lab) < 3) continue;
            if (preg_match('/(?<![a-z0-9])' . preg_quote(mb_strtolower($lab), '/') . '(?![a-z0-9])/u', $lc)) $found[$lab] = 1;
        }
    }
    // years of experience, if stated
    if (preg_match('/(\d{1,2})\s*\+?\s*(?:years|yrs)\b/i', $text, $m)) $found[$m[1] . ' yrs exp'] = 1;
    // top frequent significant terms (fallback / augment)
    $stop = array_flip(['the','and','for','with','from','that','this','have','has','are','was','will','your','you','our','their','not','all','any','can','per','via','etc','was','were','been','into','over','under','also','such','used','using','work','worked','working','experience','project','projects','company','ltd','pvt','india','name','date','email','mobile','phone','address','responsible','responsibilities','role','job','team','years','year','yrs']);
    $words = preg_split('/[^a-z0-9]+/', mb_strtolower($text));
    $freq = [];
    foreach ($words as $w) { if (mb_strlen($w) < 4 || isset($stop[$w]) || ctype_digit($w)) continue; $freq[$w] = ($freq[$w] ?? 0) + 1; }
    arsort($freq);
    $i = 0;
    foreach ($freq as $w => $n) { if ($n < 2) break; if (isset($found[$w])) continue; $found[ucfirst($w)] = 1; if (++$i >= 12) break; }
    return implode(', ', array_keys($found));
}
// Pull plain text from an uploaded CV. .txt direct; .docx via ZipArchive; .pdf is
// best-effort (advise pasting text). Returns [text, note].
function cv_text_from_upload($tmp, $name) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $raw = @file_get_contents($tmp);
    if ($raw === false) return ['', 'could not read the file'];
    if ($ext === 'txt') return [$raw, ''];
    if ($ext === 'docx') {
        if (!class_exists('ZipArchive')) return ['', 'zip extension not available'];
        $tf = tempnam(sys_get_temp_dir(), 'cv'); file_put_contents($tf, $raw);
        $zip = new ZipArchive(); $txt = '';
        if ($zip->open($tf) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) { $n = $zip->getNameIndex($i); if (preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $n)) { $x = $zip->getFromName($n); $x = preg_replace('/<\/w:p>/', "\n", $x); $txt .= ' ' . strip_tags($x); } }
            $zip->close();
        }
        @unlink($tf);
        $txt = html_entity_decode(preg_replace('/[ \t]+/', ' ', $txt), ENT_QUOTES | ENT_XML1, 'UTF-8');
        return [trim($txt), $txt === '' ? 'no text found in the .docx' : ''];
    }
    if ($ext === 'pdf') {
        // crude: pull readable ASCII runs; scanned PDFs won't yield text.
        $t = preg_replace('/[^\x20-\x7E\n]/', ' ', $raw);
        $t = preg_match_all('/\(([^)]{2,})\)/', $t, $mm) ? implode(' ', $mm[1]) : '';
        return [trim($t), 'PDF text extraction is limited — paste the CV text for best results.'];
    }
    return ['', 'unsupported file type — upload .docx or .txt, or paste the text'];
}
// Credential-request e-mail to a selected candidate (§20). Uses EMAIL_CREDENTIAL
// template if present, else a default; logs like every other mail.
function cv_send_credential_request($cand) {
    $to = trim($cand['email'] ?? ''); if ($to === '') return [false, 'no candidate e-mail on file.'];
    $name = candidate_name($cand);
    $et = ops_one("SELECT * FROM crm_templates WHERE kind='EMAIL_CREDENTIAL' AND active=1 ORDER BY is_default DESC, id DESC");
    if ($et && trim($et['body'] ?? '') !== '') {
        $subject = str_replace(['{{candidate_name}}', '{{app_name}}'], [$name, app_name()], $et['subject'] ?: 'Documents required');
        $body = str_replace(['{{candidate_name}}', '{{app_name}}'], [$name, app_name()], $et['body']);
    } else {
        $subject = 'Congratulations — documents required to proceed';
        $body = "Dear " . $name . ",\n\nWe are pleased to inform you that you have been selected. To proceed with onboarding, please share the following at the earliest:\n\n"
            . "1. Updated CV\n2. Latest salary slips (last 3 months)\n3. Government photo ID (Aadhaar / PAN / Passport)\n4. Education & experience certificates\n5. Relevant technical certifications (CSWIP / NACE / ASNT etc.)\n6. Two references\n\nReply to this e-mail with the documents attached.\n\nBest regards,\n" . app_name();
    }
    $ok = ops_mail($to, $subject, $body, '', 'cv_credential');
    return [(bool)$ok, $ok ? '' : 'e-mail could not be sent now (check SMTP) — it has been logged.'];
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
