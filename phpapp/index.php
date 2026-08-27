<?php
// The session cookie is the only thing standing between a stolen browser tab and
// somebody else's account, so it is locked down before the session is opened:
// unreadable to JavaScript, not sent to other sites, and — once the site is on
// HTTPS, which it should be — never sent in the clear.
$httpsNow = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
         || (($_SERVER['SERVER_PORT'] ?? '') == 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $httpsNow,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');   // refuse a session id the server never issued
session_start();

// Tell the browser not to guess at content types, not to frame us, and not to
// leak our URLs to other sites.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
if ($httpsNow) header('Strict-Transport-Security: max-age=15552000');
// Nothing on any screen needs a camera beyond the evidence capture, and nothing
// needs a microphone, a payment sheet or the accelerometer at all.
header('Permissions-Policy: geolocation=(self), camera=(self), microphone=(), payment=(), usb=(), interest-cohort=()');
// A content policy is the net under the escaping in the views: if a script ever
// did get onto a page, this is what stops it fetching from or reporting to
// anywhere else. 'unsafe-inline' is stated honestly — the screens carry inline
// handlers and style, so script injected into a page could still run; what it
// could NOT do is load code from another site or send what it found there.
// Razorpay's checkout runs from its own domain in an iframe, so the payment
// page (and only that page) has to be allowed to load and talk to it. Every
// other page keeps the tight policy.
$onPay = (bool)preg_match('#^/(billing-order|buy)(\?|$)#', (string)($_SERVER['REQUEST_URI'] ?? ''));
$rzpScript = $onPay ? ' https://checkout.razorpay.com' : '';
$rzpFrame  = $onPay ? ' https://api.razorpay.com https://checkout.razorpay.com' : '';
$rzpConn   = $onPay ? ' https://api.razorpay.com https://lumberjack.razorpay.com' : '';
$csp = "default-src 'self'; "
     . "script-src 'self' 'unsafe-inline'" . $rzpScript . "; "
     . "style-src 'self' 'unsafe-inline'; "
     . "img-src 'self' data: blob:; "
     . "font-src 'self' data:; "
     . "connect-src 'self'" . $rzpConn . "; "
     . "media-src 'self' blob:; "
     . "object-src 'none'; "
     . "base-uri 'self'; "
     . "form-action 'self'" . ($onPay ? ' https://api.razorpay.com' : '') . "; "
     . "frame-src 'self'" . $rzpFrame . "; "
     . "frame-ancestors 'self'";
header('Content-Security-Policy: ' . $csp);

// --- Readable error page instead of a blank 500 (so problems are diagnosable) ---
//
// The technical detail — file paths, table names, the SQL that failed — is a map
// of the app, and it was being handed to anybody who could make a page break,
// signed in or not. It is still shown, because "screenshot this and send it
// over" is how problems get reported here; but only to somebody signed in, or
// during first-time setup when nobody can be signed in yet. A stranger gets the
// plain-English half and a reference to quote.
function ops_fatal($title, $hint, $detail = '', $showDetailToAnyone = false) {
    // ISO/IEC 17020:2026 §7.11 asks for failures of the information system to be
    // recorded. A log somebody has to remember to write records only the
    // failures they were in the mood to admit to, so this one writes itself —
    // wrapped, because the app is already broken by the time we get here and
    // the logging must never be what makes it worse.
    if (function_exists('failure_record_auto')) {
        try { failure_record_auto($title, $detail); } catch (Throwable $ignored) {}
    }
    if (!headers_sent()) http_response_code(500);
    $signedIn = !empty($_SESSION['uid']);
    $ref = strtoupper(substr(md5($detail . date('YmdH')), 0, 8));
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:660px;margin:50px auto;padding:26px;border:1px solid #e2ddd6;border-radius:12px">';
    echo '<h2 style="color:#b8480f;margin:0 0 10px">' . htmlspecialchars($title) . '</h2>';
    echo '<p style="color:#4d4d4d;font-size:15px">' . $hint . '</p>';
    if ($detail !== '' && ($signedIn || $showDetailToAnyone))
        echo '<pre style="background:#f7f6f4;border:1px solid #e2ddd6;padding:12px;border-radius:8px;overflow:auto;font-size:13px;white-space:pre-wrap">' . htmlspecialchars($detail) . '</pre>';
    elseif ($detail !== '')
        echo '<p style="color:#7a7a7a;font-size:13px">Reference <b>' . $ref . '</b> — quote this when reporting it. '
           . 'Sign in and open the page again to see the technical detail.</p>';
    echo '</div>';
    exit;
}
// Catch fatals that try/catch can't (missing require, parse errors) via shutdown.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        $msg = $e['message'];
        if (stripos($msg, 'ops.php') !== false || stripos($msg, 'failed to open') !== false || stripos($msg, 'No such file') !== false) {
            ops_fatal('A program file is missing', 'It looks like not all files uploaded. Re-upload the whole app, making sure the <b>lib/</b> folder (with <code>ops.php</code>) and the <b>views/ops/</b> folder are present.', $msg . "\n" . $e['file'] . ':' . $e['line']);
        }
        ops_fatal('The app hit an error', 'Please screenshot this and send it over.', $msg . "\n" . $e['file'] . ':' . $e['line']);
    }
});
set_exception_handler(function ($ex) {
    ops_fatal('The app hit an error', 'Please screenshot this and send it over.', $ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine());
});

try {
    require __DIR__ . '/lib/db.php';
    require __DIR__ . '/lib/indexes.php';
    require __DIR__ . '/lib/helpers.php';
    require __DIR__ . '/lib/ops.php';
    require __DIR__ . '/lib/lookups.php';
    require __DIR__ . '/lib/licence.php';
    require __DIR__ . '/lib/access.php';
    require __DIR__ . '/lib/numbering.php';
    require __DIR__ . '/lib/terms.php';
    require __DIR__ . '/lib/compose.php';
    require __DIR__ . '/lib/crm.php';
    require __DIR__ . '/lib/pdf.php';
    require __DIR__ . '/lib/qr.php';
    require __DIR__ . '/lib/ai.php';
    require __DIR__ . '/lib/workforce.php';
    require __DIR__ . '/lib/orgadmin.php';
    require __DIR__ . '/lib/contracts.php';
    require __DIR__ . '/lib/security.php';
    require __DIR__ . '/lib/compliance.php';
    require __DIR__ . '/lib/costing.php';
    require __DIR__ . '/lib/reset.php';
    require __DIR__ . '/lib/partnerimport.php';
    require __DIR__ . '/lib/mis.php';
    require __DIR__ . '/lib/dedupe.php';
    require __DIR__ . '/lib/joblock.php';
    require __DIR__ . '/lib/schedule.php';
    require __DIR__ . '/lib/callprofit.php';
    require __DIR__ . '/lib/bills.php';
    require __DIR__ . '/lib/competence.php';
    require __DIR__ . '/lib/preflight.php';
    require __DIR__ . '/lib/equipment.php';
    require __DIR__ . '/lib/samples.php';
    require __DIR__ . '/lib/methods.php';
    require __DIR__ . '/lib/decisionrules.php';
    require __DIR__ . '/lib/controldocs.php';
    require __DIR__ . '/lib/risks.php';
    require __DIR__ . '/lib/retention.php';
    require __DIR__ . '/lib/disclosure.php';
    require __DIR__ . '/lib/satisfaction.php';
    require __DIR__ . '/lib/impartiality.php';
    require __DIR__ . '/lib/identity.php';
    require __DIR__ . '/lib/assets.php';
    require __DIR__ . '/lib/complaints.php';
    require __DIR__ . '/lib/capa.php';
    require __DIR__ . '/lib/ncr.php';
    require __DIR__ . '/lib/confidentiality.php';
    require __DIR__ . '/lib/activity.php';
    require __DIR__ . '/lib/packs.php';
    require __DIR__ . '/lib/leads.php';
    require __DIR__ . '/lib/opportunities.php';
    require __DIR__ . '/lib/pipelines.php';
    require __DIR__ . '/lib/industry.php';
    require __DIR__ . '/lib/crmdash.php';
    require __DIR__ . '/lib/datatable.php';
    require __DIR__ . '/lib/search.php';
    require __DIR__ . '/lib/books.php';
    require __DIR__ . '/lib/booksui.php';
    require __DIR__ . '/lib/booksbridge.php';
    require __DIR__ . '/lib/chain.php';
    require __DIR__ . '/lib/customer360.php';
    require __DIR__ . '/lib/advisor.php';
    require __DIR__ . '/lib/stagegate.php';
    require __DIR__ . '/lib/mghsso.php';
    require __DIR__ . '/lib/adspro.php';
    require __DIR__ . '/lib/adssync.php';
    require __DIR__ . '/lib/licencekey.php';
    require __DIR__ . '/lib/licencesync.php';
    require __DIR__ . '/lib/licenceissue.php';
    require __DIR__ . '/lib/billing.php';
    require __DIR__ . '/lib/adsroi.php';
    require __DIR__ . '/lib/audits.php';
    require __DIR__ . '/lib/datacontrol.php';
    require __DIR__ . '/lib/trust.php';
    require __DIR__ . '/lib/portal.php';
    require __DIR__ . '/lib/reportreview.php';
    require __DIR__ . '/lib/tally.php';
    require __DIR__ . '/lib/receivables.php';
    require __DIR__ . '/lib/idems.php';
    require __DIR__ . '/lib/urfe.php';
    require __DIR__ . '/lib/uire.php';
    require __DIR__ . '/lib/urade.php';
    require __DIR__ . '/lib/uvae.php';
    require __DIR__ . '/lib/uvaae.php';
    require __DIR__ . '/lib/services.php';
    require __DIR__ . '/lib/pdso.php';
    require __DIR__ . '/lib/ncdca.php';
    require __DIR__ . '/lib/schedboard.php';
    require __DIR__ . '/lib/tosrm.php';
    require __DIR__ . '/lib/attend.php';
    require __DIR__ . '/lib/cvp.php';
    require __DIR__ . '/lib/tapi.php';
    require __DIR__ . '/lib/tapi_dash.php';
    require __DIR__ . '/lib/tapi_score.php';
    require __DIR__ . '/lib/tapi_gov.php';
    require __DIR__ . '/lib/projcosting.php';
    require __DIR__ . '/lib/areas.php';
    require __DIR__ . '/lib/navindex.php';
    require __DIR__ . '/lib/idems_autoform.php';
    require __DIR__ . '/lib/hwpoints.php';
    require __DIR__ . '/lib/seed_demo.php';
    require __DIR__ . '/lib/seed_demo_c.php';
    require __DIR__ . '/lib/seed_recruit_cc.php';
    require __DIR__ . '/lib/seed_costing.php';
    require __DIR__ . '/lib/trace_seed.php';
    require __DIR__ . '/lib/trace_audit.php';
    require __DIR__ . '/lib/setup.php';
    require __DIR__ . '/lib/tenants.php';
    require __DIR__ . '/lib/cpanel.php';
    require __DIR__ . '/lib/agreement.php';
    require __DIR__ . '/lib/company.php';
    require __DIR__ . '/lib/customforms.php';
    require __DIR__ . '/lib/geofence.php';
    require __DIR__ . '/lib/timesheet.php';
    require __DIR__ . '/lib/rating.php';
    require __DIR__ . '/lib/inspectorprofile.php';
    require __DIR__ . '/lib/recruit.php';
    require __DIR__ . '/lib/recruit_cc.php';
    require __DIR__ . '/lib/superadmin.php';
    require __DIR__ . '/lib/party.php';           // Phase 2 §23/24 — canonical person mapping layer
    require __DIR__ . '/lib/qualitycase.php';     // Phase 2 §39 — quality-case umbrella (read-only)
    require __DIR__ . '/lib/visibility.php';       // Phase 2 §72 — canonical visibility vocabulary + single-record gate
    require __DIR__ . '/lib/engagement.php';       // Phase 2 §25 — engagement grouping (read-only view over contract_number)
    require __DIR__ . '/lib/bulk.php';             // Phase 2 §48 — server-side preview/dry-run for bulk actions
    require __DIR__ . '/lib/revrecon.php';         // Phase 2 §29 — recognised-revenue reconciliation (read-only)
    require __DIR__ . '/lib/settingmeta.php';      // Phase 2 §47 — settings governance registry (read-only)
    require __DIR__ . '/lib/settlement.php';       // Phase 2 §32 — inter-office settlement matrix (read-only)
    require __DIR__ . '/lib/invready.php';         // Phase 2 §33 — invoice readiness (advisory; strict-gated)
    require __DIR__ . '/lib/tasks.php';            // Phase 3 §26 — canonical persisted task
    require __DIR__ . '/lib/finevent.php';         // Phase 3 §27 — financial-event stream (read-only projection)
    require __DIR__ . '/lib/webhookq.php';         // Phase 3 §50 — generic integration queue
    require __DIR__ . '/lib/vendor360.php';        // Phase 3 §16 — vendor-360 depth (contacts + party + history)
    require __DIR__ . '/lib/tmplpreview.php';      // Phase 3 §8 — report-template persona preview
    require __DIR__ . '/lib/entity360.php';        // Phase 3 §49 — uniform Entity-360 shell
    require __DIR__ . '/lib/billable.php';          // Revamp P4 — the Billable Event ledger (operational→commercial bridge)
    require __DIR__ . '/lib/attendreview.php';     // Phase 3 §35 — attendance review (anomaly + send-back)
} catch (Throwable $e) {
    // Setup-time: nobody can be signed in yet, so the detail has to be visible.
    ops_fatal('A program file is missing or has an error', 'Re-upload the app — make sure <b>lib/ops.php</b> and the <b>views/ops/</b> folder are present.', $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), true);
}

// Cloud mode: an unknown or suspended workspace subdomain must be refused BEFORE
// anything opens a database — otherwise it would fall through to the control
// install's data. Read config once (this resolves the workspace but does not
// connect), then act on its verdict.
if (function_exists('db_driver')) { try { db_driver(); } catch (Throwable $e) {} }
$__t = $GLOBALS['__tenant'] ?? [];
if (!empty($__t['error']) && function_exists('tenant_error_page')) {
    tenant_error_page((string)$__t['error'], (string)($__t['key'] ?? ''));   // exits
}

// Industry packs register their hooks before any route runs.
if (function_exists('packs_boot')) packs_boot();

// Installation licence agreement — a self-hosted customer must read and accept
// it before the software will install (before even the database is configured).
// The provider's own issuing server and dev copies are exempt. Runs first so no
// step of installation happens without an accepted, recorded contract.
if (function_exists('agreement_gate')) agreement_gate();   // exits if not yet accepted

// Web setup wizard, Phase A — the database is not configured yet. Handle the
// wizard's own POST (it writes config.local.php without needing the database),
// and show the connect form instead of a dead error when config.php still holds
// the shipped placeholders. A real, configured server never reaches either
// branch; a genuine connection FAULT is still caught and explained further down.
if (function_exists('setup_handle_db_post')
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/setup-db') {
    setup_handle_db_post();   // exits
}
if (function_exists('setup_config_placeholder') && setup_config_placeholder()) {
    setup_render_db_form();   // exits
}

// Bootstrap / upgrade: this quick probe fails on a fresh install (no table) or
// when a new table/column is missing, which triggers the idempotent boot/migrate.
//
// ---- but only when the code has actually changed -------------------------
// The probe below reads ~155 tables and columns to catch a pending upgrade. It
// is correct, but running it on EVERY request means every page holds the one
// database connection open through ~155 round-trips before it does any real
// work — and a connection held that long, request after request, is exactly how
// a server runs out of connection slots. (A pooled app like the Node ones keeps
// long-lived connections and stays lean per request; this PHP app opens a fresh
// connection each time, so the long hold hurts it most.) So the whole probe now
// runs only when the code fingerprint has changed. That fingerprint is built
// from the modification time and size of index.php and every lib file, so ANY
// deploy that could alter the schema changes it automatically — there is
// nothing to remember to bump. When it matches what was last verified, the
// probe is skipped entirely and the request touches the database only for the
// page it came to draw.
$schemaSig = '';
try {
    $parts = [filemtime(__FILE__) . ':' . filesize(__FILE__)];
    foreach (glob(__DIR__ . '/lib/*.php') ?: [] as $f) $parts[] = basename($f) . ':' . @filemtime($f) . ':' . @filesize($f);
    // config.php too — editing the admin credentials there must re-run boot() so
    // ensure_admin() re-applies them. Without this, a password changed in config
    // on a running server was silently ignored (the code fingerprint had not
    // moved), and the old password kept working while the new one was refused.
    $parts[] = 'config:' . @filemtime(__DIR__ . '/config.php') . ':' . @filesize(__DIR__ . '/config.php');
    $schemaSig = md5(implode('|', $parts));
} catch (Throwable $e) { $schemaSig = ''; }
$schemaCurrent = false;
if ($schemaSig !== '') {
    // One tiny read instead of 155. A missing settings table (fresh install)
    // throws and is treated as "not current", so the full probe still runs.
    try { $schemaCurrent = ((string) db()->query("SELECT svalue FROM settings WHERE skey='schema_sig'")->fetchColumn() === $schemaSig); }
    catch (Throwable $e) { $schemaCurrent = false; }
}
if (!$schemaCurrent) {
try {
    // Probe the NEWEST additions too — a miss here triggers boot(), whose
    // idempotent migrate() then adds every pending table/column in one pass.
    db()->query("SELECT address_id FROM partner_contacts LIMIT 1");
    db()->query("SELECT id FROM offices LIMIT 1");
    db()->query("SELECT id FROM lookup_types LIMIT 1");
    db()->query("SELECT deliverables FROM jobs LIMIT 1");
    db()->query("SELECT inspection_types FROM business_partners LIMIT 1");
    db()->query("SELECT trade_id FROM inspectors LIMIT 1");
    db()->query("SELECT id FROM inspector_certs LIMIT 1");
    db()->query("SELECT scope_offices FROM users LIMIT 1");
    db()->query("SELECT skey FROM settings LIMIT 1");
    db()->query("SELECT home_branch_id FROM business_partners LIMIT 1");
    db()->query("SELECT stage FROM candidates LIMIT 1");
    db()->query("SELECT extra FROM expenses LIMIT 1");
    db()->query("SELECT id FROM expense_heads LIMIT 1");
    db()->query("SELECT id FROM travel_modes LIMIT 1");
    db()->query("SELECT id FROM inspector_allowances LIMIT 1");
    db()->query("SELECT id FROM voucher_entries LIMIT 1");
    db()->query("SELECT id FROM vendor_km_memory LIMIT 1");
    db()->query("SELECT agency_cost FROM inspectors LIMIT 1");
    // Newest columns — a miss here triggers the idempotent upgrade so live
    // databases set up on an older version gain every pending column at once.
    db()->query("SELECT overhead_pct FROM offices LIMIT 1");
    db()->query("SELECT invoice_raised FROM jobs LIMIT 1");
    db()->query("SELECT supersedes FROM boss_numbers LIMIT 1");
    db()->query("SELECT billable_value FROM calls LIMIT 1");
    db()->query("SELECT verify_status FROM inspector_certs LIMIT 1");   // Slice P1 — Credential Vault
    db()->query("SELECT id FROM billable_events LIMIT 1");               // Revamp P4 — Billable Event ledger
    db()->query("SELECT id FROM agencies LIMIT 1");
    db()->query("SELECT agency_id FROM inspectors LIMIT 1");
    db()->query("SELECT id FROM requisitions LIMIT 1");
    db()->query("SELECT fee_status FROM inspectors LIMIT 1");
    // CRM module tables (Phase 0) — a miss creates the whole CRM data model.
    db()->query("SELECT quote_no FROM quotations LIMIT 1");
    db()->query("SELECT id FROM crm_inquiries LIMIT 1");
    db()->query("SELECT id FROM quote_approval_rules LIMIT 1");
    db()->query("SELECT document_number FROM crm_templates LIMIT 1");
    db()->query("SELECT quotation_id FROM jobs LIMIT 1");
    db()->query("SELECT cv_keywords FROM candidates LIMIT 1");
    // Workforce pack — availability board, weekly working days, reporting manager.
    db()->query("SELECT weekly_working_days FROM inspectors LIMIT 1");
    db()->query("SELECT team_role FROM inspectors LIMIT 1");
    db()->query("SELECT id FROM inspector_day_status LIMIT 1");
    db()->query("SELECT reports_to_name FROM users LIMIT 1");
    db()->query("SELECT report_approval FROM jobs LIMIT 1");
    db()->query("SELECT id FROM work_norms LIMIT 1");
    // IDEMS report engine tables
    db()->query("SELECT irn FROM report_docs LIMIT 1");
    db()->query("SELECT id FROM report_types LIMIT 1");
    db()->query("SELECT id FROM idems_audit LIMIT 1");
    db()->query("SELECT id FROM report_sections LIMIT 1");
    db()->query("SELECT id FROM report_fields LIMIT 1");
    db()->query("SELECT id FROM report_files LIMIT 1");
    db()->query("SELECT id FROM report_approvals LIMIT 1");
    db()->query("SELECT id FROM idems_approval_rules LIMIT 1");
    db()->query("SELECT signature FROM users LIMIT 1");
    db()->query("SELECT id FROM report_templates LIMIT 1");
    db()->query("SELECT id FROM endorsements LIMIT 1");
    db()->query("SELECT id FROM tech_phrases LIMIT 1");
    db()->query("SELECT id FROM learned_suggestions LIMIT 1");
    // Organisation structure: offices nested under offices, with a head.
    db()->query("SELECT parent_office_id FROM offices LIMIT 1");
    // Contract validity: end-date and quantity gates on scheduling.
    db()->query("SELECT id FROM contract_overrides LIMIT 1");
    db()->query("SELECT id FROM report_doc_review LIMIT 1");
    db()->query("SELECT deliverables FROM calls LIMIT 1");
    db()->query("SELECT billable_rate FROM calls LIMIT 1");
    db()->query("SELECT token FROM form_tokens LIMIT 1");
    db()->query("SELECT qty_total FROM partner_contracts LIMIT 1");
    db()->query("SELECT totp_enabled FROM users LIMIT 1");
    db()->query("SELECT half_day_hours FROM users LIMIT 1");
    db()->query("SELECT monthly_ctc FROM users LIMIT 1");
    db()->query("SELECT sbus FROM offices LIMIT 1");
    db()->query("SELECT is_outstation FROM calls LIMIT 1");
    db()->query("SELECT id FROM office_expense_heads LIMIT 1");
    db()->query("SELECT id FROM cost_allocations LIMIT 1");
    db()->query("SELECT locked_at FROM jobs LIMIT 1");
    db()->query("SELECT invoice_value, contracting_office_id FROM jobs LIMIT 1");
    db()->query("SELECT engagement_type, days_count, months_count, pattern_kind FROM calls LIMIT 1");
    db()->query("SELECT engagement_type, schedule_weekdays, schedule_end_date, force_dates FROM jobs LIMIT 1");
    db()->query("SELECT office_id FROM holidays LIMIT 1");
    db()->query("SELECT visit_date FROM job_visits LIMIT 1");
    db()->query("SELECT force_dates, manmonth_basis, credit_rate FROM calls LIMIT 1");
    db()->query("SELECT credit_rate, other_cost, other_cost_note FROM jobs LIMIT 1");
    db()->query("SELECT manmonth_basis FROM business_partners LIMIT 1");
    db()->query("SELECT quotation_id FROM partner_purchase_orders LIMIT 1");
    db()->query("SELECT contract_number FROM cost_allocations LIMIT 1");
    db()->query("SELECT id FROM security_incidents LIMIT 1");
    db()->query("SELECT id FROM data_requests LIMIT 1");
    db()->query("SELECT id FROM data_consents LIMIT 1");
    db()->query("SELECT chargeable_heads FROM calls LIMIT 1");
    db()->query("SELECT chargeable_heads FROM jobs LIMIT 1");
    db()->query("SELECT head_code FROM job_bills LIMIT 1");
    db()->query("SELECT is_mandatory FROM inspector_certs LIMIT 1");
    db()->query("SELECT cert_override_note FROM jobs LIMIT 1");
    db()->query("SELECT id FROM equipment LIMIT 1");
    db()->query("SELECT id FROM equipment_calibrations LIMIT 1");
    db()->query("SELECT id FROM report_equipment LIMIT 1");
    db()->query("SELECT id FROM authorisations LIMIT 1");
    db()->query("SELECT id FROM witness_assessments LIMIT 1");
    db()->query("SELECT id FROM qualifications LIMIT 1");
    db()->query("SELECT id FROM impartiality_threats LIMIT 1");
    db()->query("SELECT id FROM impartiality_declarations LIMIT 1");
    db()->query("SELECT impartiality_ok FROM jobs LIMIT 1");
    db()->query("SELECT id FROM person_documents LIMIT 1");
    db()->query("SELECT id FROM person_document_access LIMIT 1");
    db()->query("SELECT id FROM complaints LIMIT 1");
    db()->query("SELECT id FROM complaint_events LIMIT 1");
    db()->query("SELECT id FROM capa LIMIT 1");
    db()->query("SELECT id FROM capa_events LIMIT 1");
    // Newest: a corrective action broken into its own tasks, the nonconformity
    // register that feeds it, and the client's answer to an issued report. A
    // miss on any of these upgrades a live database in one pass.
    db()->query("SELECT id FROM capa_actions LIMIT 1");
    db()->query("SELECT id FROM nonconformities LIMIT 1");
    db()->query("SELECT id FROM ncr_events LIMIT 1");
    db()->query("SELECT ncr_id FROM capa LIMIT 1");
    db()->query("SELECT id FROM report_client_reviews LIMIT 1");
    db()->query("SELECT client_decision FROM report_docs LIMIT 1");
    db()->query("SELECT id FROM tally_exports LIMIT 1");
    db()->query("SELECT perms FROM client_users LIMIT 1");
    db()->query("SELECT id FROM site_doc_requirements LIMIT 1");
    db()->query("SELECT id FROM confidentiality_undertakings LIMIT 1");
    db()->query("SELECT id FROM confidentiality_breaches LIMIT 1");
    db()->query("SELECT id FROM client_ndas LIMIT 1");
    db()->query("SELECT basis FROM authorisations LIMIT 1");
    db()->query("SELECT id FROM activities LIMIT 1");
    db()->query("SELECT id FROM leads LIMIT 1");
    db()->query("SELECT id FROM pipeline_stages LIMIT 1");
    db()->query("SELECT id FROM user_prefs LIMIT 1");
    db()->query("SELECT id FROM opportunities LIMIT 1");
    db()->query("SELECT id FROM opportunity_quotes LIMIT 1");
    db()->query("SELECT id FROM opportunity_stage_history LIMIT 1");
    db()->query("SELECT call_id FROM opportunities LIMIT 1");
    // The books. A miss here and a live database never gains an invoice table,
    // so the money screens 500 on a server that upgraded cleanly in every other
    // respect. Every table and the one column that was added after the fact.
    db()->query("SELECT id FROM invoices LIMIT 1");
    db()->query("SELECT id FROM invoice_lines LIMIT 1");
    db()->query("SELECT id FROM receipts LIMIT 1");
    db()->query("SELECT id FROM receipt_allocations LIMIT 1");
    db()->query("SELECT kind FROM receipt_allocations LIMIT 1");
    db()->query("SELECT id FROM credit_notes LIMIT 1");
    db()->query("SELECT sitedoc_override_note FROM jobs LIMIT 1");
    db()->query("SELECT id FROM internal_audits LIMIT 1");
    db()->query("SELECT id FROM audit_findings LIMIT 1");
    db()->query("SELECT id FROM mgmt_reviews LIMIT 1");
    db()->query("SELECT id FROM mr_inputs LIMIT 1");
    db()->query("SELECT id FROM mr_actions LIMIT 1");
    db()->query("SELECT id FROM sw_validations LIMIT 1");
    db()->query("SELECT id FROM data_check_runs LIMIT 1");
    db()->query("SELECT id FROM system_failures LIMIT 1");
    db()->query("SELECT id FROM site_visits LIMIT 1");
    db()->query("SELECT kind FROM site_visits LIMIT 1");
    db()->query("SELECT photo_sha1 FROM site_visits LIMIT 1");
    db()->query("SELECT id FROM evidence_chain LIMIT 1");
    db()->query("SELECT geo_source FROM report_files LIMIT 1");
    db()->query("SELECT verify_code FROM report_docs LIMIT 1");
    db()->query("SELECT id FROM client_users LIMIT 1");
    db()->query("SELECT id FROM portal_requests LIMIT 1");
    db()->query("SELECT id FROM portal_audit LIMIT 1");
    db()->query("SELECT id FROM stage_gates LIMIT 1");
    db()->query("SELECT id FROM stage_gate_requests LIMIT 1");
    db()->query("SELECT id FROM ads_leads LIMIT 1");
    db()->query("SELECT id FROM ads_spend LIMIT 1");
    db()->query("SELECT id FROM ads_sync_log LIMIT 1");
    db()->query("SELECT id FROM ads_outbox LIMIT 1");
    db()->query("SELECT local_kind FROM ads_leads LIMIT 1");
    db()->query("SELECT id FROM sso_attempts LIMIT 1");
    // Data-level upgrades can't be spotted by a missing table or column, so they
    // are asserted here instead: if the old shape is still present, throw, which
    // runs the same idempotent boot() and clears it. Each check is self-cancelling.
    if ((int)db()->query("SELECT COUNT(*) FROM lookup_types WHERE type_key='deputation_type'")->fetchColumn() > 0)
        throw new RuntimeException('pending upgrade: engagement-pattern list rename');
    if ((int)db()->query("SELECT COUNT(*) FROM inspectors WHERE roll_type NOT IN ('OWN','AGENCY')")->fetchColumn() > 0)
        throw new RuntimeException('pending upgrade: roll-type normalisation');
    // The acronyms are gone from the app; a live database set up on an older
    // version still carries them as list headings. Self-cancelling: boot() renames.
    if ((int)db()->query("SELECT COUNT(*) FROM lookup_types WHERE (type_key='sbu' AND label='SBU') OR (type_key='boss_status' AND label='BOSS status')")->fetchColumn() > 0)
        throw new RuntimeException('pending upgrade: list headings renamed off the old acronyms');
    if ((int)db()->query("SELECT COUNT(*) FROM lookup_values WHERE label='SBU Head' AND type_id IN (SELECT id FROM lookup_types WHERE type_key='designation')")->fetchColumn() > 0)
        throw new RuntimeException('pending upgrade: designation renamed off the old acronym');
    // A column that is merely too narrow is not a *missing* column, so nothing
    // else would ever notice. MEDIUMTEXT silently truncates a large upload.
    if (function_exists('file_columns_pending') && file_columns_pending())
        throw new RuntimeException('pending upgrade: upload columns widened to LONGTEXT');
    if (function_exists('charge_units_pending') && charge_units_pending())
        throw new RuntimeException('pending upgrade: shared charge units');
    if (function_exists('service_types_pending') && service_types_pending())
        throw new RuntimeException('pending upgrade: shared work-type list');
    if (function_exists('deliverables_pending') && deliverables_pending())
        throw new RuntimeException('pending upgrade: deliverables from the report register');
    // Every probe above passed, but the code fingerprint moved — a deploy has
    // happened. Run the schema migrations directly (idempotent DDL, and NEVER
    // re-seeds) so ANY table or column added in this build is created, even one
    // no probe above happens to name. This is what closes the gap that left
    // inspectors.team_role unmigrated: schema coverage no longer depends on
    // every column being individually listed in the probe (only ~148 of ~290
    // columns ever were). Cheap: runs once per deploy, then the fingerprint
    // matches and the whole block is skipped.
    if (function_exists('migrate_all')) migrate_all();
    // Nothing above threw: the schema matches this build. Remember the
    // fingerprint so the next request skips the whole probe.
    if ($schemaSig !== '') { try { setting_set('schema_sig', $schemaSig); } catch (Throwable $e) {} }
} catch (Throwable $ex) {
    try {
        boot();
        // The upgrade ran cleanly, so the schema now matches this build — record
        // the fingerprint so later requests do not probe again until it changes.
        if ($schemaSig !== '') { try { setting_set('schema_sig', $schemaSig); } catch (Throwable $e) {} }
    } catch (Throwable $e2) {
        $m = $e2->getMessage();

        // Say which of these it is. The old page answered every connection
        // failure with "open config.php and enter the database name, user and
        // password" — which is actively wrong for a server that has simply run
        // out of connection slots, and sends somebody to re-check credentials
        // that were never the problem. [1040] was reported from the live server
        // and that is exactly what it did.
        $title = 'Database not connected';
        $hint  = '';
        if (stripos($m, 'too many connections') !== false || strpos($m, '1040') !== false) {
            $title = 'The database server is full, just for a moment';
            $hint  = '<b>Nothing is wrong with this application and nothing is lost.</b> MySQL had no free connection '
                   . 'slots when this request arrived, so it refused one — and it stayed full through the automatic '
                   . 'retries too. Reload in a few seconds; it usually clears at once.<br><br>If it keeps happening, '
                   . 'the ceiling is simply too low for the number of apps sharing this MySQL server. Raise '
                   . '<code>max_connections</code> in the server\'s <code>my.cnf</code> (for example from 151 to 400) '
                   . 'and restart MySQL. If other apps hold large idle connection pools, trimming those frees slots too.';
        } elseif (stripos($m, 'access denied') !== false || strpos($m, '1045') !== false
                  || stripos($m, 'unknown database') !== false || strpos($m, '1049') !== false) {
            // Wrong user / password / database name is a setup problem, not a
            // fault to stare at: hand them the wizard, prefilled with the name
            // and host already tried, so they can correct it and save without
            // touching a file. (Skipped for the transient 1040 above, which is
            // not a setup problem at all.)
            if (function_exists('setup_render_db_form')) {
                $cfgNow = @require __DIR__ . '/config.php';
                setup_render_db_form([
                    'db_host' => $cfgNow['db']['host'] ?? 'localhost',
                    'db_name' => $cfgNow['db']['name'] ?? '',
                    'db_user' => $cfgNow['db']['user'] ?? '',
                ], stripos($m, 'unknown database') !== false || strpos($m, '1049') !== false
                    ? 'The user and password are accepted, but there is no database by that name on this server. On shared hosting the name usually carries an account prefix.'
                    : 'The database name is right but the user or password is not. Check them under cPanel → Databases.', true);
            }
        } elseif (stripos($m, 'connect') !== false || stripos($m, 'no such file') !== false || strpos($m, '2002') !== false) {
            $hint = 'The database server could not be reached at all. Check the <b>host</b> in <code>config.php</code> — '
                  . 'on most shared hosting it is <code>localhost</code>.';
        } else {
            $title = 'Database setup error';
            $hint  = 'The database is reachable but a table could not be set up. Send this message over and it will be '
                   . 'fixed quickly.';
        }
        ops_fatal($title, $hint, $m . "\n" . $e2->getFile() . ':' . $e2->getLine(), true);
    }
}
}   // end: the schema probe runs only when the code fingerprint has changed

// Locked out of the admin login? Drop a plain text file named
// "reset-admin.txt" in this folder (cPanel File Manager → New File) with the
// new password on the first line, then load any page once. The password is set,
// and the file deletes itself so it cannot be used twice or left lying around.
// It is safe because it needs file access to the server — the same trust as
// config.php — and nothing about it appears in the browser.
if (function_exists('admin_recovery_from_file')) admin_recovery_from_file();

// Apply the admin password from config.php whenever it changes — reliably, and
// independent of the code-fingerprint gate above, so "set the password in
// config.php, upload it, sign in" always works. Does nothing (one settings
// read) when the config password has not changed since it was last applied.
if (function_exists('admin_sync_from_config')) admin_sync_from_config();

// --- Router (single-segment routes; ids/tabs via query string) ---
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = trim($path, '/');
if ($route === 'index.php' || $route === '') $route = '';
if (isset($_GET['r'])) $route = trim($_GET['r'], '/');
$method = $_SERVER['REQUEST_METHOD'];

$pdo = db();

function view($name, $vars = []) {
    extract($vars);
    // Buffered so every POST form on the page can be given its token on the way
    // out — one place, rather than 141 forms each relying on somebody remembering.
    ob_start();
    require __DIR__ . '/views/layout_top.php';
    // A missing view file must never white-screen the whole app with a fatal
    // "Failed opening required" — that almost always means a stale or partial
    // deployment (the code asked for a page whose file is not on the server).
    // Show a contained message with a way back instead of taking the app down.
    $viewFile = __DIR__ . "/views/$name.php";
    if (is_file($viewFile)) {
        require $viewFile;
    } else {
        http_response_code(500);
        echo '<div class="panel" style="border-left:4px solid var(--bad)">'
           . '<h2 style="margin-top:0">This screen could not be loaded</h2>'
           . '<p class="muted">The page <code>' . htmlspecialchars((string)$name, ENT_QUOTES) . '</code> is not available on the server. '
           . 'This usually means the app files are out of date — a full redeploy of the application usually fixes it.</p>'
           . '<p style="margin-bottom:0"><a class="btn" href="/">← Back to dashboard</a></p></div>';
    }
    require __DIR__ . '/views/layout_bottom.php';
    echo csrf_stamp_forms((string)ob_get_clean());
}

function find_partner($id) {
    $q = db()->prepare("SELECT * FROM business_partners WHERE id = ?");
    $q->execute([$id]);
    return $q->fetch();
}
function children($table, $pid, $order = 'id') {
    $q = db()->prepare("SELECT * FROM $table WHERE partner_id = ? ORDER BY $order");
    $q->execute([$pid]);
    return $q->fetchAll();
}

// --- PWA assets: served before the auth gate so field devices can install the
//     app and register the service worker. Also covers hosts whose rewrite rules
//     send every request here instead of serving the real file. ---
if ($route === 'sw.js' || $route === 'manifest.php' || strpos($route, 'assets/') === 0) {
    $file = __DIR__ . '/' . $route;
    if ($route === 'manifest.php') { require $file; exit; }
    if (is_file($file) && strpos(realpath($file), realpath(__DIR__)) === 0) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $types = ['js'=>'application/javascript', 'css'=>'text/css', 'png'=>'image/png', 'jpg'=>'image/jpeg',
                  'jpeg'=>'image/jpeg', 'svg'=>'image/svg+xml', 'woff'=>'font/woff', 'woff2'=>'font/woff2'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        if ($route === 'sw.js') { header('Service-Worker-Allowed: /'); header('Cache-Control: no-cache, max-age=0'); }
        else header('Cache-Control: public, max-age=86400');
        readfile($file); exit;
    }
    http_response_code(404); exit;
}

// --- Public routes ---
// $stage is 'password' for the ordinary form and 'code' once the password has
// been accepted and a second factor is still owed.
function render_login($error, $stage = 'password', $note = '') {
    require __DIR__ . '/views/login_page.php';
    exit;
}
if ($route === 'login') {
    if ($method === 'POST') {
        // The sign-in form carries a token too, so another site cannot quietly
        // sign somebody into an account of its choosing and then watch what
        // they file under it. The token is minted when the page is drawn, so a
        // missing one means the page is stale rather than that anything is wrong.
        if (!csrf_ok($_POST['_csrf'] ?? ''))
            return render_login('This sign-in page has been open a while. Please reload it and try again.');
        // ---- second stage: the six-digit code, or a recovery code ----------
        if (($_POST['stage'] ?? '') === 'code') {
            $pid = (int)($_SESSION['pending_uid'] ?? 0);
            $age = time() - (int)($_SESSION['pending_at'] ?? 0);
            $u   = $pid ? ops_one("SELECT * FROM users WHERE id=?", [$pid]) : null;
            // Five minutes to reach for a phone is generous; after that the
            // half-finished sign-in is thrown away rather than left lying open.
            if (!$u || $age > 300) {
                unset($_SESSION['pending_uid'], $_SESSION['pending_at']);
                return render_login('That took too long. Please sign in again.');
            }
            $code = trim((string)($_POST['code'] ?? ''));
            if (totp_verify($u['totp_secret'], $code) || recovery_code_spend($u, $code)) {
                $left = recovery_codes_left($u);
                complete_login($u);
                flash('Welcome, ' . user_name($u) . '.');
                if (strpos($code, '-') !== false)
                    flash('You signed in with a recovery code. ' . $left . ' left — generate a new set from Two-step sign-in.', 'warning');
                redirect('/');
            }
            idems_log('user', $u['id'], 'LOGIN_FAILED', ['field'=>$u['username'] . ' (wrong code)']);
            $wait = login_fail($u['username']); login_fail(login_ip_key());
            if ($wait > 0) { unset($_SESSION['pending_uid']); return render_login('Too many failed attempts. Try again in ' . $wait . ' minute(s).'); }
            return render_login('That code is not right. Check the app and try the current one.', 'code');
        }

        // ---- first stage: username and password ----------------------------
        // The address is counted as well as the username: one machine working
        // through a list of logins never trips a per-username limit.
        $ipWait = login_ip_locked_for();
        if ($ipWait > 0) {
            idems_log('user', null, 'LOGIN_FAILED', ['field'=>'blocked source ' . client_ip()]);
            return render_login('Too many failed attempts from this connection. Try again in ' . $ipWait . ' minute(s).');
        }
        $q = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $q->execute([$_POST['username'] ?? '']);
        $u = $q->fetch();
        if ($u && $u['is_active'] && login_allowed($u['username'])
            && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            if (twofa_on($u)) {
                // Nothing is signed in yet. The session only remembers who is
                // half-way through, and for how long.
                session_regenerate_id(true);
                $_SESSION['pending_uid'] = $u['id'];
                $_SESSION['pending_at']  = time();
                return render_login(null, 'code');
            }
            complete_login($u);
            flash('Welcome, ' . user_name($u) . '.');
            redirect('/');
        }
        // record failed attempts too — they matter for a compliance review
        if (function_exists('idems_log')) idems_log('user', $u['id'] ?? null, 'LOGIN_FAILED', ['field'=>substr((string)($_POST['username'] ?? ''), 0, 60)]);
        $wait = login_fail((string)($_POST['username'] ?? ''));
        login_fail(login_ip_key());
        if ($wait > 0) return render_login('Too many failed attempts. Try again in ' . $wait . ' minute(s).');
        return render_login('Invalid username or password.');
    }
    if (current_user()) redirect('/');
    // A refused single sign-on handoff has a reason, and the person needs it.
    // "Invalid username or password" on a screen they never typed into is the
    // most confusing message this application could give them.
    if (!empty($_SESSION['sso_msg'])) {
        $ssoMsg = (string)$_SESSION['sso_msg'];
        unset($_SESSION['sso_msg']);
        return render_login($ssoMsg);
    }
    // Half-way through a handoff and a second factor is still owed.
    if (!empty($_SESSION['pending_uid'])) return render_login(null, 'code');
    return render_login(null);
}
if ($route === 'logout') {
    if (function_exists('idems_log') && current_user()) idems_log('user', current_user()['id'], 'LOGOUT', []);
    session_destroy(); redirect('/login');
}

// How we handle complaints has to be readable by anybody who wants to complain
// — ISO/IEC 17020 §7.5.1 asks for the description to be available to any
// interested party, and a page behind a password is not available to them. So
// this one page sits in front of the gate. It is read-only and takes nothing in.
if ($route === 'complaints-policy') {
    require __DIR__ . '/views/ops/complaints_policy.php';
    exit;
}

// "Verify it yourself" is the whole point, so it cannot sit behind a password.
// A client holding a report checks it here without an account and without
// asking us. It shows whether the report is genuine and unaltered — and
// nothing confidential.
if ($route === 'verify') {
    require __DIR__ . '/views/ops/verify.php';
    exit;
}

// Self-hosted self-service: a customer on their own server pays for users on
// THIS licence server. Public, because the caller has no account here — they are
// identified by their install id, and the only thing they can walk away with is
// a signed key for that id, issued only after a Razorpay payment we verify.
if (($route === 'buy' || $route === 'buy-verify') && function_exists('ops_buy')) {
    ops_buy($route, $method);   // renders a standalone page and exits
    exit;
}

// The client portal has its own sign-in, its own session key and its own table.
// It is dispatched HERE, in front of require_login(), because require_login()
// means a STAFF account — a client has none and must never be pushed towards
// one. Everything past this point is the staff application; nothing a client
// does ever reaches it. portal_route() always exits.
if ($route === 'portal' || strncmp($route, 'portal/', 7) === 0) {
    portal_route($route, $method);
    exit;
}

// The vendor portal — a THIRD audience, kept as separate from the client portal
// as the client portal is from staff: its own table (vendor_users), its own
// session key (vuid) and its own /vendor addresses. Dispatched here, in front of
// require_login(), for the same reason: a vendor has no staff account and must
// never be pushed towards one. cvp_vendor_route() always exits.
if (function_exists('cvp_vendor_route') && ($route === 'vendor' || strncmp($route, 'vendor/', 7) === 0)) {
    cvp_vendor_route($route, $method);
    exit;
}

// A signed handoff from a sibling MGH application (Books, BlogPro, Ads Pro).
// It sits HERE, in front of require_login(), because its whole job is to satisfy
// that gate — and behind the portal dispatch, because a client is not a staff
// member and must never be handed a staff session. Accepted or refused, the
// token is stripped from the URL by redirecting: a token left in the address bar
// ends up in a bookmark, a browser history and the next Referer header.
if (function_exists('sso_accept') && isset($_GET['sso']) && $_GET['sso'] !== '') {
    $ssoOk = sso_accept((string)$_GET['sso']);
    $clean = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    if ($ssoOk) { flash('Welcome, ' . user_name(current_user()) . '.'); redirect($clean ?: '/'); }
    redirect('/login');
}

// --- Everything below requires login ---
require_login();

// Once a day, on the first page somebody opens: lock the jobs that ran out of
// time and tell the four people who need to know. The rule bites on screen
// whether or not this runs; this is what sends the message when the host's cron
// has not been set up yet.
if (function_exists('joblock_sweep_daily')) joblock_sweep_daily();

// A laptop left open on a client's site is the realistic risk, not a clever
// attack. An idle session, and a session that has simply run long enough, both
// end here — and the person is told which it was rather than being dumped at a
// blank sign-in page for no stated reason.
$expired = session_expiry_reason();
if ($expired !== '') {
    idems_log('user', current_user()['id'] ?? null, 'LOGOUT', ['field'=>'session expired']);
    $_SESSION = []; session_destroy();
    session_start();
    flash($expired, 'warning');
    redirect('/login');
}
session_touch();

// Somebody still on the password they were handed, or on one that has aged out
// of the company's own rule, is sent to change it and can go nowhere else. The
// sign-out and the change screen itself stay reachable, or they would be stuck.
if (must_change_password(current_user())
    && !in_array($route, ['change-password', 'logout'], true)
    && strpos($route, 'assets/') !== 0) {
    flash(!empty(current_user()['must_change_pwd'])
        ? 'Please choose your own password before going any further.'
        : 'Your password has reached the age limit set for this company. Please choose a new one.', 'warning');
    redirect('/change-password');
}

// An expired subscription makes the system READ-ONLY, never locked. Every
// screen, export and PDF keeps working; only new records are refused. Locking a
// business out of its own invoices earns a chargeback and a bad review, and it
// has never once made anybody renew faster.
//
// This sits on the POST path only, so nothing that merely reads is touched, and
// the licence screen itself is exempt — a rule that blocks the screen you need
// in order to unlock the system is a support call, not a control.
if ($method === 'POST' && function_exists('lk_blocks_write') && lk_blocks_write($route)) {
    $st = lk_state();
    flash(($st['err'] ?: 'This subscription has ended.')
        . ' The system is read-only: everything can be opened, printed and exported, but nothing new can be saved. '
        . 'Paste a current licence key on the Licence screen to carry on.', 'error');
    redirect('/licence');
}

// Refuse any state-changing request that did not come from one of our own
// pages. Without this, a page somebody visits in another tab can make their
// browser approve, delete or create things here using their own session, and
// nothing afterwards would look wrong. The one-shot ticket below is a different
// thing entirely — it is minted in the browser and only stops double-posting.
if ($method === 'POST' && !csrf_ok($_POST['_csrf'] ?? '')) {
    idems_log('user', current_user()['id'] ?? null, 'CSRF_REJECTED', ['field' => substr($route, 0, 60)]);
    // Keep what they typed. The old message said "nothing was changed", which was
    // true about the database and a lie about the twenty fields somebody had just
    // filled in — they came back to an empty form.
    form_stash($route, $_POST);
    flash('Your session had timed out, so that was not saved. '
        . 'Everything you typed is still here — press the button again.', 'warning');
    redirect('/' . $route);
}

// First-run setup wizard (Phase B). The database is up but the Master Admin has
// never been through setup: gather the few things only they can decide, then
// never show it again. Sits after the CSRF gate so its own POST is protected,
// and lets change-password / logout through so nobody can be trapped.
if ($route === 'setup' || $route === 'setup-save') { ops_setup($route, $method); return; }
if (function_exists('setup_needed') && setup_needed() && is_master()
    && !in_array($route, ['logout', 'change-password'], true) && strpos($route, 'assets/') !== 0) {
    redirect('/setup');
}

// Every file attached anywhere in this app passes through here before a handler
// sees it. Uploads are held in the database and never written to disk, so one
// can't be executed; this is the second guard — a file whose contents disagree
// with what it claims to be, or which is script under any name, is refused at
// the door rather than stored and served back to a colleague later.
if ($method === 'POST' && !empty($_FILES)) {
    $badUpload = uploads_reject_reason();
    if ($badUpload !== '') {
        idems_log('user', current_user()['id'] ?? null, 'UPLOAD_REFUSED', ['field'=>substr($badUpload, 0, 200)]);
        flash($badUpload . ' Nothing else on the form was saved either — please attach a different file and submit again.', 'error');
        redirect_back($route);
    }
}

// One submission, one record. Every form carries a one-shot ticket; the first
// POST spends it and any replay of that same POST is turned away here, before a
// handler can write a second row. This is the guard that stops an inspector's
// expenses appearing twice when the offline queue re-sends an entry the server
// had already saved, and it costs nothing on a normal submission.
if ($method === 'POST' && isset($_POST['_ft'])) {
    if (!form_token_spend($_POST['_ft'])) {
        flash('That was already saved — the form was sent twice, so the second copy was ignored.', 'warning');
        // Back to the record they were on. The referrer arrives as a full
        // address, so it has to be parsed rather than tested for a leading "/";
        // done the naive way, the id is dropped and they land on an empty list.
        redirect_back($route);
    }
}

if ($route === '') {
    $clients = (int)$pdo->query("SELECT COUNT(*) FROM business_partners WHERE is_client=1")->fetchColumn();
    $vendors = (int)$pdo->query("SELECT COUNT(*) FROM business_partners WHERE is_vendor=1")->fetchColumn();
    return view('dashboard', ['clients' => $clients, 'vendors' => $vendors]);
}

if ($route === 'clients' || $route === 'vendors') {
    ops_require(can('mod.' . $route . '.view'), 'You don’t have access to the ' . ucfirst($route) . ' module. Ask your administrator.');
    $roleField = $route === 'clients' ? 'is_client' : 'is_vendor';
    $q = trim($_GET['q'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 40;
    $where = "$roleField = 1";
    $args = [];
    if ($status) { $where .= " AND status = ?"; $args[] = $status; }
    if ($q) {
        $where .= " AND (legal_name LIKE ? OR display_name LIKE ? OR code LIKE ? OR gstin LIKE ? OR pan LIKE ?)";
        for ($i = 0; $i < 5; $i++) $args[] = "%$q%";
    }
    $total = (int)(function() use ($pdo, $where, $args) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM business_partners WHERE $where"); $s->execute($args); return $s->fetchColumn();
    })();
    $s = $pdo->prepare("SELECT * FROM business_partners WHERE $where ORDER BY legal_name LIMIT $per OFFSET " . (($page - 1) * $per));
    $s->execute($args);
    return view('list', [
        'rows' => $s->fetchAll(), 'total' => $total, 'roleField' => $roleField,
        'title' => $route === 'clients' ? T_REG('client') : T_REG('vendor'),
        'subtitle' => $route === 'clients'
            ? Tlp('client') . ', contacts, contracts and billing references'
            : Tlp('manufacturer') . ', ' . Tlp('supplier') . ', plants and their contacts',
        'q' => $q, 'status' => $status, 'page' => $page, 'pages' => (int)ceil($total / $per),
    ]);
}

if ($route === 'partner-new') {
    // R1 — client/vendor create runs before the module gate, so guard it here.
    // Editing commercial master data needs the clients/vendors edit right; a
    // coordinator may also onboard a party while raising a call (intake).
    ops_require(is_master() || can('mod.clients.edit') || can('mod.vendors.edit')
        || (function_exists('is_coordinator_level') && is_coordinator_level()),
        'You do not have permission to add ' . Tl('client') . ' / ' . Tl('vendor') . ' records.');
    // Picker mode: the form was opened in a small window from a "+ Add new" link
    // beside a dropdown (quote, call, candidate…). On save we hand the new record
    // back to that window instead of redirecting, so it is selected there.
    $picker = !empty($_POST['picker']) || !empty($_GET['picker']);
    if ($method === 'POST') {
        $b = $_POST;
        $formVars = ['partner' => null, 'defaultRole' => 'is_client', 'offices' => offices_list(), 'picker' => $picker];
        if (!empty($b['gstin']) && !is_valid_gstin($b['gstin'])) {
            return view('form', $formVars + ['error' => 'GSTIN should be 15 characters, e.g. 24ADUPL3517E2ZJ.']);
        }
        // sub-contractor is a manpower vendor → force the vendor role on
        if (!empty($b['is_subcontractor'])) $b['is_vendor'] = 1;
        if (empty($b['is_client']) && empty($b['is_vendor']) && empty($b['is_subcontractor'])) {
            return view('form', $formVars + ['error' => 'Select at least one role (Client / Vendor / Both).']);
        }
        // §WO-1 — commercial terms are required for a client (or a client+vendor),
        // because their invoices carry agreed terms. A vendor-only record needs
        // none, so adding a vendor while raising a call stays quick.
        if (!empty($b['is_client']) && trim((string)($b['payment_terms'] ?? '')) === '') {
            return view('form', $formVars + ['error' => 'Payment terms are required for a ' . Tl('client') . ' (used on their invoices). A vendor-only record does not need them.']);
        }
        $gstin = clean_gstin($b['gstin'] ?? '');
        $pan = $gstin ? pan_from_gstin($gstin) : '';
        $state = $gstin ? state_from_gstin($gstin) : '';
        // duplicate guard — by GSTIN / PAN / TAN / name
        $dup = find_duplicate_partner($b['legal_name'] ?? '', $gstin, $pan, $b['tan'] ?? '', 0);
        if ($dup) {
            return view('form', $formVars + ['error' => "This company already exists as {$dup['row']['code']} — {$dup['row']['legal_name']} (matched by {$dup['by']}). Open it from the Clients/Vendors list and tick the extra role instead of creating a duplicate."]);
        }
        $b['client_type'] = resolve_new_lookup('client_type', $b['client_type'] ?? '', $b['client_type_new'] ?? '');
        $b['industry'] = resolve_new_lookup('industry', $b['industry'] ?? '', $b['industry_new'] ?? '');
        $branchId = ($b['home_branch_id'] ?? '') !== '' ? (int)$b['home_branch_id'] : null;
        $code = gen_partner_code($branchId, ($b['display_name'] ?? '') ?: ($b['legal_name'] ?? ''));
        $ins = $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,is_subcontractor,client_type,industry,ownership_type,status,gstin,pan,cin,tan,msme_udyam,state,website,description,payment_terms,credit_days,home_branch_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $ins->execute([$code, $b['legal_name'], $b['display_name'] ?? '', !empty($b['is_client'])?1:0, !empty($b['is_vendor'])?1:0, !empty($b['is_subcontractor'])?1:0, $b['client_type'] ?? '', $b['industry'] ?? '', $b['ownership_type'] ?? '', $b['status'] ?? 'ACTIVE', $gstin, $pan, $b['cin'] ?? '', $b['tan'] ?? '', $b['msme_udyam'] ?? '', $state, $b['website'] ?? '', $b['description'] ?? '', $b['payment_terms'] ?? '', (int)($b['credit_days'] ?? 0), $branchId, date('c')]);
        $id = $pdo->lastInsertId();
        $pdo->prepare("UPDATE business_partners SET inspection_types=? WHERE id=?")
            ->execute([implode(',', array_filter((array)($b['inspection_types'] ?? []))), $id]);
        // What a man-month means for this client, when their contract differs
        // from the company default. Blank falls back, which is the common case.
        $mmB = (string)($b['manmonth_basis'] ?? '');
        if (!isset(MANMONTH_BASES[$mmB])) $mmB = '';
        $pdo->prepare("UPDATE business_partners SET manmonth_basis=?, manmonth_min_days=? WHERE id=?")
            ->execute([$mmB, (int)($b['manmonth_min_days'] ?? 0), $id]);
        partner_save_geofence($pdo, $id, $b);
        custom_save('partner', $id, $b);
        // Primary contact carried in from the lead / inquiry / quote this client
        // was added against — so the person we already know lands on the client
        // master, not re-keyed later. Only when a name was actually provided.
        $pcName = trim((string)($b['contact_name'] ?? ''));
        if ($pcName !== '') {
            $pcMobile = trim((string)($b['contact_mobile'] ?? '')) ?: trim((string)($b['contact_phone'] ?? ''));
            $pdo->prepare("INSERT INTO partner_contacts (partner_id,name,email,mobile,is_primary) VALUES (?,?,?,?,1)")
                ->execute([$id, substr($pcName, 0, 150), substr(trim((string)($b['contact_email'] ?? '')), 0, 200), substr($pcMobile, 0, 40)]);
        }
        // Whatever sales already knew about this company: the inspections they
        // asked for, the person they were talking to, and the inquiries and
        // quotations that only ever carried the name.
        $carry = function_exists('partner_carry_from_crm') ? partner_carry_from_crm($id, $b['legal_name']) : null;
        $note = $carry ? partner_carry_text($carry) : '';
        if ($picker) {
            // Hand the new partner back to the window that opened this form, so the
            // dropdown it was launched from selects it — no minimal duplicate record.
            $nm = ($b['display_name'] ?? '') !== '' ? $b['display_name'] : ($b['legal_name'] ?? $code);
            $payload = json_encode([
                'type'  => 'exaact:partner-added',
                'id'    => (int)$id,
                'name'  => $nm,
                'code'  => $code,
                'roles' => [
                    'client' => !empty($b['is_client']),
                    'vendor' => !empty($b['is_vendor']) || !empty($b['is_subcontractor']),
                ],
            ], JSON_UNESCAPED_UNICODE);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8"><title>Saved</title>'
               . '<body style="font:15px/1.5 system-ui,Segoe UI,Arial;padding:2rem;color:#1e293b">'
               . '<p><strong>' . htmlspecialchars($code . ' — ' . $nm, ENT_QUOTES) . '</strong> created and selected on the form.</p>'
               . '<p style="color:#64748b">You can close this tab if it does not close by itself.</p>'
               . '<script>try{(window.opener||window.parent).postMessage(' . $payload . ',location.origin);}catch(e){}'
               . 'setTimeout(function(){try{window.close();}catch(e){}},200);</script>';
            exit;
        }
        flash("$code created." . ($note !== '' ? ' ' . $note : ''));
        redirect("/partner?id=$id" . ($note !== '' ? '&tab=contacts' : ''));
    }
    return view('form', ['partner' => null, 'defaultRole' => $_GET['role'] ?? 'is_client', 'error' => null, 'offices' => offices_list(), 'pcfvals' => [], 'picker' => $picker]);
}

if ($route === 'partner-edit') {
    // R1 — client/vendor edit runs before the module gate; guard it here.
    ops_require(is_master() || can('mod.clients.edit') || can('mod.vendors.edit')
        || (function_exists('is_coordinator_level') && is_coordinator_level()),
        'You do not have permission to edit ' . Tl('client') . ' / ' . Tl('vendor') . ' records.');
    $p = find_partner((int)($_GET['id'] ?? 0));
    if (!$p) { http_response_code(404); return view('notfound'); }
    if ($method === 'POST') {
        $b = $_POST;
        if (!empty($b['is_subcontractor'])) $b['is_vendor'] = 1; // sub-contractor ⇒ vendor
        $b['client_type'] = resolve_new_lookup('client_type', $b['client_type'] ?? '', $b['client_type_new'] ?? '');
        $b['industry'] = resolve_new_lookup('industry', $b['industry'] ?? '', $b['industry_new'] ?? '');
        $gstin = clean_gstin($b['gstin'] ?? '');
        $pan = $gstin ? pan_from_gstin($gstin) : $p['pan'];
        $state = $gstin ? state_from_gstin($gstin) : $p['state'];
        $dup = find_duplicate_partner($b['legal_name'] ?? '', $gstin, $pan ?: ($p['pan'] ?? ''), $b['tan'] ?? '', $p['id']);
        if ($dup) {
            return view('form', ['partner' => $p, 'defaultRole' => 'is_client', 'offices' => offices_list(),
                'error' => "Another company already uses these details: {$dup['row']['code']} — {$dup['row']['legal_name']} (matched by {$dup['by']})."]);
        }
        $pdo->prepare("UPDATE business_partners SET legal_name=?,display_name=?,is_client=?,is_vendor=?,is_subcontractor=?,client_type=?,industry=?,ownership_type=?,status=?,gstin=?,pan=?,cin=?,tan=?,msme_udyam=?,state=?,website=?,description=?,payment_terms=?,credit_days=? WHERE id=?")
            ->execute([$b['legal_name'], $b['display_name'] ?? '', !empty($b['is_client'])?1:0, !empty($b['is_vendor'])?1:0, !empty($b['is_subcontractor'])?1:0, $b['client_type'] ?? '', $b['industry'] ?? '', $b['ownership_type'] ?? '', $b['status'] ?? 'ACTIVE', $gstin, $pan, $b['cin'] ?? '', $b['tan'] ?? '', $b['msme_udyam'] ?? '', $state, $b['website'] ?? '', $b['description'] ?? '', $b['payment_terms'] ?? '', (int)($b['credit_days'] ?? 0), $p['id']]);
        $pdo->prepare("UPDATE business_partners SET inspection_types=? WHERE id=?")
            ->execute([implode(',', array_filter((array)($b['inspection_types'] ?? []))), $p['id']]);
        // What a man-month means for this client, when their contract differs
        // from the company default. Blank falls back, which is the common case.
        $mmB = (string)($b['manmonth_basis'] ?? '');
        if (!isset(MANMONTH_BASES[$mmB])) $mmB = '';
        $pdo->prepare("UPDATE business_partners SET manmonth_basis=?, manmonth_min_days=? WHERE id=?")
            ->execute([$mmB, (int)($b['manmonth_min_days'] ?? 0), $p['id']]);
        partner_save_geofence($pdo, $p['id'], $b);
        custom_save('partner', $p['id'], $b);
        flash('Updated.');
        redirect("/partner?id={$p['id']}");
    }
    return view('form', ['partner' => $p, 'defaultRole' => 'is_client', 'error' => null, 'offices' => offices_list(), 'pcfvals' => custom_values_map('partner', $p['id'])]);
}

if ($route === 'partner-add' && $method === 'POST') {
    $p = find_partner((int)($_GET['id'] ?? 0));
    if (!$p) { http_response_code(404); return view('notfound'); }
    $kind = $_GET['kind'] ?? '';
    $b = $_POST;
    // R1 — every partner-add sub-form (contact / address / registration / relationship /
    // note / PO) writes commercial master data and ran with no permission check. Guard
    // the whole route with the clients/vendors edit right (a coordinator may also add
    // during intake). The contract sub-form has its OWN stricter gate below (R4).
    ops_require(is_master() || can('mod.clients.edit') || can('mod.vendors.edit')
        || (function_exists('is_coordinator_level') && is_coordinator_level()),
        'You do not have permission to change ' . Tl('client') . ' / ' . Tl('vendor') . ' records.');
    // Registering a contract is Accounts/back-office work — the same gate the CRM
    // "register contract" path uses (crm.php:1830). This partner-screen door used
    // to create a partner_contracts row with no permission check at all, so a
    // salesperson, coordinator or even an inspector could register a contract
    // off-book, bypassing the won-quote → Finance handoff. A contract is created
    // from the won quotation by Accounts, not here by anyone who reaches this form.
    if ($kind === 'contract') {
        ops_require(can('crm.contract.register') || is_master(),
            'Only Accounts / back-office can register a contract. It is created from the won quotation, not here.');
    }
    $map = [
        'contact' => ['partner_contacts', ['name','designation','department','project','email','mobile','phone','address_id'], 'contacts'],
        'address' => ['partner_addresses', ['address_type','label','line1','line2','town_village','district','city','state','pincode','country'], 'addresses'],
        'registration' => ['partner_registrations', ['doc_type','number','valid_to','notes'], 'registration'],
        'contract' => ['partner_contracts', ['contract_number','title','sbu','value','start_date','end_date','notes','qty_total'], 'contracts'],
        'relationship' => ['partner_relationships', ['relation_type','related_id','notes'], 'relationships'],
    ];
    if ($kind === 'note') {
        $pdo->prepare("INSERT INTO partner_notes (partner_id,note,author_name,created_at) VALUES (?,?,?,?)")
            ->execute([$p['id'], $b['note'] ?? '', user_name(current_user()), date('c')]);
        flash('Note added.');
        redirect("/partner?id={$p['id']}&tab=notes");
    }
    if ($kind === 'po') {
        // The purchase order the client sends is the answer to a quotation we
        // sent them. Naming that quotation is what makes the rest of this form
        // unnecessary: the contract, the business unit, the value and every line item are
        // already written down, and retyping them is how the two drift apart.
        $qid = (int)($b['quotation_id'] ?? 0);
        $quote = $qid ? ops_one("SELECT * FROM quotations WHERE id=?", [$qid]) : null;
        $qLines = $quote ? ops_all("SELECT * FROM quote_lines WHERE quote_id=? ORDER BY line_no, id", [$qid]) : [];

        $poSbu = isset($b['po_sbu']) ? implode(',', array_filter((array)$b['po_sbu'])) : ($b['sbu'] ?? '');
        if ($poSbu === '' && $quote) $poSbu = (string)($quote['sbu'] ?? '');
        $contractId = ($b['contract_id'] ?? '') !== '' ? (int)$b['contract_id'] : null;
        if (!$contractId && $quote && !empty($quote['contract_id'])) $contractId = (int)$quote['contract_id'];
        $title = trim((string)($b['title'] ?? '')) ?: (string)($quote['subject'] ?? '');
        $value = ($b['value'] ?? '') !== '' ? $b['value'] : ($quote ? $quote['total_amount'] : null);

        $pdo->prepare("INSERT INTO partner_purchase_orders (partner_id,contract_id,quotation_id,sbu,po_number,po_type,title,value,start_date,end_date,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$p['id'], $contractId, $qid ?: null, $poSbu, $b['po_number'] ?? '', $b['po_type'] ?? 'REGULAR',
                       $title, $value, $b['start_date'] ?? '', $b['end_date'] ?? '', $b['notes'] ?? '']);
        $poId = (int)$pdo->lastInsertId();
        if ($qid) $pdo->prepare("UPDATE partner_purchase_orders SET lines_synced_at=? WHERE id=?")->execute([date('c'), $poId]);

        // Every line of the quotation becomes a line of the order, with the
        // quantity and the rate it was quoted at. Consumption is tracked against
        // these, so getting them from the quotation rather than from memory is
        // what makes "how much of this order is left" a real number.
        $n = 0;
        if ($qLines) {
            $ins = $pdo->prepare("INSERT INTO po_line_items (purchase_order_id,description,item_type,quantity,rate,consumed) VALUES (?,?,?,?,?,0)");
            foreach ($qLines as $l) {
                $desc = trim((string)($l['description'] ?? ''));
                if ($desc === '') $desc = trim((string)($l['service_type'] ?? '')) ?: 'Line ' . (int)$l['line_no'];
                if (trim((string)($l['location'] ?? '')) !== '') $desc .= ' — ' . $l['location'];
                $ins->execute([$poId, mb_substr($desc, 0, 200), (string)($l['unit'] ?? 'MANDAY'),
                               (float)($l['qty'] ?? 0), ($l['rate'] === null || $l['rate'] === '') ? null : (float)$l['rate']]);
                $n++;
            }
        }
        flash('Purchase order added.' . ($quote ? ' Taken from ' . $quote['quote_no']
            . ((int)$quote['rev'] ? ' R' . (int)$quote['rev'] : '')
            . ($n ? ' with ' . $n . ' line item(s)' : '') . '.' : ''));
        redirect('/po?id=' . $poId);
    }
    if (isset($map[$kind])) {
        [$table, $fields, $tab] = $map[$kind];
        if ($kind === 'address' && !empty($b['city'])) $b['city'] = normalise_city($b['city']); // light spell-normalise
        // §3 — a contract number identifies one contract. The same number under
        // this same client is a rate contract being extended; under a different
        // client it is a duplicate, and every expiry and quantity check
        // downstream would then be reading somebody else's contract.
        if ($kind === 'contract' && function_exists('contract_no_clash')) {
            $cn = trim((string)($b['contract_number'] ?? ''));
            $clash = contract_no_clash($cn, $p['id']);
            if ($clash) {
                flash('Contract number ' . $cn . ' is already registered against '
                    . ($clash['owner_name'] ?: 'another party') . '. Contract numbers must be unique.', 'error');
                redirect("/partner?id={$p['id']}&tab=contracts");
            }
            if ($cn !== '' && ops_val("SELECT id FROM partner_contracts WHERE partner_id=? AND contract_number=? LIMIT 1", [$p['id'], $cn])) {
                flash('This ' . Tl('client') . ' already has contract ' . $cn . '. Open it to change its dates or value.', 'error');
                redirect("/partner?id={$p['id']}&tab=contracts");
            }
            // Adding a client's contract here is fine for a separate agreement, but
            // this door must not re-forward a deal that Finance already contracted.
            // If it names a quotation that already carries a live contract, stop and
            // point to the group-company path (a REJECTED/CLOSED prior may proceed).
            $linkQid = (int)($b['quotation_id'] ?? 0);
            if ($linkQid) {
                $lq = ops_one("SELECT contract_id FROM quotations WHERE id=?", [$linkQid]);
                $prevC = !empty($lq['contract_id']) ? ops_one("SELECT contract_number, open_status FROM partner_contracts WHERE id=?", [(int)$lq['contract_id']]) : null;
                if ($prevC && in_array((string)($prevC['open_status'] ?? 'OPEN'), ['OPEN','PENDING'], true)) {
                    flash('That ' . Tl('quote') . ' is already registered under contract ' . $prevC['contract_number']
                        . '. A won deal gets one contract — for a related group company, add its contract from the '
                        . Tl('quote') . ' screen instead.', 'error');
                    redirect("/partner?id={$p['id']}&tab=contracts");
                }
            }
        }
        $cols = array_merge(['partner_id'], $fields);
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $vals = [$p['id']];
        foreach ($fields as $f) {
            $v = $b[$f] ?? '';
            if (($f === 'address_id' || $f === 'related_id' || $f === 'value' || $f === 'qty_total') && $v === '') $v = null;
            $vals[] = $v;
        }
        $pdo->prepare("INSERT INTO $table (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
        $newId = (int)$pdo->lastInsertId();
        // A contract recorded here against one of our own quotations is the same
        // agreement seen from the other end: write the number back onto that
        // quotation so it stops reading "contract number pending", and point the
        // contract at the order it came from. Same rule the accounts screen uses.
        if ($kind === 'contract' && function_exists('contract_link_quotation')) {
            $qid = (int)($b['quotation_id'] ?? 0);
            if ($qid && contract_link_quotation($newId, $qid)) {
                $qn = ops_val("SELECT quote_no FROM quotations WHERE id=?", [$qid]);
                flash('Contract added, and ' . ($qn ?: Tl('quote')) . ' now carries this contract number.');
                redirect("/partner?id={$p['id']}&tab=contracts");
            }
        }
        flash('Added.');
        redirect("/partner?id={$p['id']}&tab=$tab");
    }
    redirect("/partner?id={$p['id']}");
}

if ($route === 'partner') {
    // R1 — the partner 360 (contacts, contracts, POs, commercial data) ran with no
    // permission check; require at least read on clients or vendors (finance needs it
    // for billing; a coordinator during intake).
    ops_require(is_master() || can('mod.clients.view') || can('mod.vendors.view')
        || can('finance.reconcile') || (function_exists('is_coordinator_level') && is_coordinator_level()),
        'You do not have permission to view this ' . Tl('client') . ' / ' . Tl('vendor') . ' record.');
    $p = find_partner((int)($_GET['id'] ?? 0));
    if (!$p) { http_response_code(404); return view('notfound'); }
    $subs = $pdo->prepare("SELECT * FROM business_partners WHERE parent_id = ?"); $subs->execute([$p['id']]);
    $parent = $p['parent_id'] ? find_partner($p['parent_id']) : null;
    return view('detail', [
        'p' => $p, 'tab' => $_GET['tab'] ?? 'overview', 'parent' => $parent,
        'subsidiaries' => $subs->fetchAll(),
        'addresses' => children('partner_addresses', $p['id']),
        'contacts' => children('partner_contacts', $p['id']),
        'registrations' => children('partner_registrations', $p['id']),
        'notes' => children('partner_notes', $p['id'], 'id DESC'),
        'contracts' => children('partner_contracts', $p['id']),
        'pos' => children('partner_purchase_orders', $p['id']),
        'rels' => (function() use ($pdo, $p) { $s = $pdo->prepare("SELECT r.*, b.legal_name rn, b.display_name rd, b.id rid FROM partner_relationships r LEFT JOIN business_partners b ON b.id=r.related_id WHERE r.partner_id=?"); $s->execute([$p['id']]); return $s->fetchAll(); })(),
        'all_partners' => (function() use ($pdo, $p) { $s = $pdo->prepare("SELECT id, legal_name, display_name FROM business_partners WHERE id <> ? ORDER BY legal_name"); $s->execute([$p['id']]); return $s->fetchAll(); })(),
        'cityList' => array_values(array_filter(array_column($pdo->query("SELECT DISTINCT city FROM partner_addresses WHERE city <> '' ORDER BY city")->fetchAll(), 'city'))),
        'linkedCalls' => (function() use ($pdo, $p) {
            try { $s = $pdo->prepare("SELECT id, call_code, inspection_type, status, call_received_date, inspection_required_date FROM calls WHERE client_id=? OR vendor_id=? ORDER BY id DESC"); $s->execute([$p['id'], $p['id']]); return $s->fetchAll(); }
            catch (Throwable $e) { return []; }
        })(),
    ]);
}

if ($route === 'po') {
    // R1 — the purchase-order screen (a client's PO, its line items and value roll-up)
    // ran with no permission check. Reading needs clients/vendors view; any change
    // (pull-quote, add line) needs the edit right (or coordinator/finance at intake).
    ops_require(is_master() || can('mod.clients.view') || can('mod.vendors.view')
        || can('finance.reconcile') || (function_exists('is_coordinator_level') && is_coordinator_level()),
        'You do not have permission to view purchase orders.');
    if ($method === 'POST') {
        ops_require(is_master() || can('mod.clients.edit') || can('mod.vendors.edit')
            || can('finance.reconcile') || (function_exists('is_coordinator_level') && is_coordinator_level()),
            'You do not have permission to change a purchase order.');
    }
    $s = $pdo->prepare("SELECT po.*, b.legal_name pn, b.display_name pdn FROM partner_purchase_orders po LEFT JOIN business_partners b ON b.id=po.partner_id WHERE po.id=?");
    $s->execute([(int)($_GET['id'] ?? 0)]);
    $po = $s->fetch();
    if (!$po) { http_response_code(404); return view('notfound'); }
    if ($method === 'POST' && ($_POST['do'] ?? '') === 'pull-quote') {
        // The quotation has been revised since this order was raised. Pull the
        // lines through again rather than making somebody re-key twelve of them.
        $qid = (int)($_POST['quotation_id'] ?? 0) ?: (int)($po['quotation_id'] ?? 0);
        $res = function_exists('po_pull_quote_lines') ? po_pull_quote_lines($po['id'], $qid) : ['ok' => false, 'error' => 'Not available.'];
        if (!$res['ok']) { flash($res['error'], 'error'); redirect('/po?id=' . $po['id']); }
        $sum = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM po_line_items WHERE purchase_order_id=" . (int)$po['id'])->fetchColumn();
        $pdo->prepare("UPDATE partner_purchase_orders SET value=? WHERE id=?")->execute([$sum ?: null, $po['id']]);
        flash($res['count'] . ' line item(s) taken from the quotation. Anything typed here before has been replaced.');
        redirect('/po?id=' . $po['id']);
    }
    if ($method === 'POST') {
        $b = $_POST;
        $qty = (float)($b['quantity'] ?? 0); $rate = (float)($b['rate'] ?? 0); $gst = (float)($b['gst_pct'] ?? 0);
        $base = $qty * $rate; $tax = round($base * $gst / 100, 2); $total = $base + $tax;
        $pdo->prepare("INSERT INTO po_line_items (purchase_order_id,description,item_type,trade_id,skill_id,activity_id,site,manpower,quantity,rate,consumed,gst_pct,base_amount,tax_amount,total_amount)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$po['id'], $b['description'], $b['item_type'] ?? 'MANDAYS',
                ($b['trade_id'] ?? '') !== '' ? (int)$b['trade_id'] : null,
                ($b['skill_id'] ?? '') !== '' ? (int)$b['skill_id'] : null,
                ($b['activity_id'] ?? '') !== '' ? (int)$b['activity_id'] : null,
                $b['site'] ?? '', (int)(($b['manpower'] ?? 0) ?: 0), $qty, $rate ?: null, (float)(($b['consumed'] ?? 0) ?: 0), $gst, $base, $tax, $total]);
        // roll the PO value up to the sum of its line-item totals
        $sum = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM po_line_items WHERE purchase_order_id=" . (int)$po['id'])->fetchColumn();
        $pdo->prepare("UPDATE partner_purchase_orders SET value=? WHERE id=?")->execute([$sum, $po['id']]);
        // if the PO is against a contract, roll the contract value up too
        if ($po['contract_id']) {
            $cSum = (float)$pdo->query("SELECT COALESCE(SUM(value),0) FROM partner_purchase_orders WHERE contract_id=" . (int)$po['contract_id'])->fetchColumn();
            $pdo->prepare("UPDATE partner_contracts SET value=? WHERE id=?")->execute([$cSum, $po['contract_id']]);
        }
        flash('Line item added. PO value updated to ₹' . number_format($sum, 0) . '.');
        redirect('/po?id=' . $po['id']);
    }
    $li = $pdo->prepare("SELECT l.*, t.label trade_label, s.label skill_label, a.label activity_label
        FROM po_line_items l LEFT JOIN lookup_values t ON t.id=l.trade_id LEFT JOIN lookup_values s ON s.id=l.skill_id
        LEFT JOIN lookup_values a ON a.id=l.activity_id WHERE l.purchase_order_id = ?");
    $li->execute([$po['id']]);
    // activities available for this PO's business unit(s)
    $poSbus = array_filter(explode(',', $po['sbu'] ?? ''));
    $actBySbu = activity_options_by_sbu();
    $poActivities = [];
    foreach ($poSbus as $sc) foreach (($actBySbu[$sc] ?? []) as $a) $poActivities[] = $a;
    if (!$poActivities) foreach ($actBySbu as $list) foreach ($list as $a) $poActivities[] = $a; // fallback: all
    return view('po_detail', ['po' => $po, 'items' => $li->fetchAll(), 'skillsByTrade' => skills_by_trade(),
        'quoteState' => function_exists('po_quote_status') ? po_quote_status($po) : null,
        // Every quotation this client has open, so lines can be taken from one
        // even when the order was typed in without naming it.
        'poQuotes' => function_exists('quotations_for_po') ? quotations_for_po($po['partner_id']) : [],
        'trades' => lk_type('trade') ? lk_root_values(lk_type('trade')['id']) : [], 'poActivities' => $poActivities]);
}

// --- Operations & Finance modules (Calls, Jobs, masters, reports, users) ---
if (ops_dispatch($route, $method)) return;

http_response_code(404);
return view('notfound');
