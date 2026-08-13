<?php
// ============================================================================
//  CVP — Client & Vendor Portal engine (Phase 10)
//
//  This file is the CONFIDENTIALITY SPINE the rest of Phase 10 is built on. Two
//  external audiences will read records that were written by staff — a client
//  and (from Slice 2) a vendor. The one rule that must never be got wrong is:
//
//      an internal note, an internal investigation, another party's response —
//      none of it is shown to an external audience unless it is EXPLICITLY
//      marked visible to THAT audience.
//
//  The engines already record the intent. A nonconformity carries a `visibility`
//  (INTERNAL / CLIENT_VISIBLE / VENDOR_VISIBLE / RESTRICTED / MGMT_ONLY —
//  ncdca.php NCDCA_VISIBILITY) and a site-log line carries `client_visible`
//  (pdso.php dep_site_log). Until now nothing filtered on either — the portal
//  simply never SELECTed the columns, which is safe only for as long as nobody
//  writes a new query. This turns that convention into an enforced gate that
//  every external read passes through, in the WHERE clause, the same way the
//  client portal already scopes by partner id.
//
//  Nothing here removes or rewrites an existing engine. It is a lens the portal
//  reads through; staff screens are unchanged.
// ============================================================================

// The three audiences a record can be read by. INTERNAL is staff — it sees
// everything, and is the audience of every existing staff screen. CLIENT and
// VENDOR are the two portal audiences.
const CVP_AUDIENCE = ['INTERNAL' => 'Internal', 'CLIENT' => 'Client', 'VENDOR' => 'Vendor'];

// Map each issue-visibility code (ncdca.php NCDCA_VISIBILITY) to the EXTERNAL
// audiences allowed to see a row carrying it. A code that maps to no external
// audience is internal-only. Unknown/blank codes are treated as INTERNAL — the
// safe default is "not shown", never "shown".
const CVP_VISIBILITY_AUDIENCE = [
    'INTERNAL'       => [],                    // staff only
    'RESTRICTED'     => [],                    // staff only, tighter handling
    'MGMT_ONLY'      => [],                    // staff only
    'CLIENT_VISIBLE' => ['CLIENT'],
    'VENDOR_VISIBLE' => ['VENDOR'],
    'SHARED'         => ['CLIENT', 'VENDOR'],  // reserved: visible to both parties
];

function cvp_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();

    // -- Slice 2: the vendor portal ------------------------------------------
    // A person at a VENDOR company. Deliberately its OWN table and its own
    // session key (vuid), exactly as client_users is kept apart from staff: a
    // vendor can never be returned by portal_user() or current_user(), so no
    // client screen and no staff screen can ever be reached by one, even if a
    // route is added carelessly later. Scope is the vendor's business_partner id.
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendor_users (
        id $pk, vendor_id INT, contact_id INT NULL,
        email VARCHAR(200) DEFAULT '', name VARCHAR(150) DEFAULT '',
        password_hash VARCHAR(255) DEFAULT '',
        is_active INT DEFAULT 1, must_change INT DEFAULT 1,
        perms VARCHAR(400) DEFAULT '',
        invite_token VARCHAR(64) DEFAULT '', invite_expires VARCHAR(30) DEFAULT '',
        last_login_at VARCHAR(30) DEFAULT '', last_login_ip VARCHAR(60) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Every page a vendor opened — the same cheap trail the client portal keeps.
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendor_audit (
        id $pk, vendor_user_id INT NULL, vendor_id INT NULL,
        action VARCHAR(40) DEFAULT '', detail VARCHAR(300) DEFAULT '',
        ip VARCHAR(60) DEFAULT '', at VARCHAR(30) DEFAULT '')");
    // A report reaches the vendor portal ONLY when staff explicitly share it.
    // Confidentiality first: a report naming a vendor is not automatically the
    // vendor's to read — a client's inspection of goods can name the vendor and
    // still be the client's alone. So nothing is shown until this flag is set.
    if (function_exists('ensure_column')) {
        ensure_column('report_docs', 'vendor_visible', 'INT DEFAULT 0');
    }

    // -- Slice 4: the portal notification feed --------------------------------
    // One store for BOTH audiences. A row is a thing that HAPPENED to this party
    // (a report issued/shared, a nonconformity raised, a response decided). The
    // (audience, partner_id, event, ref_kind, ref_id) tuple is the natural key —
    // the sync below inserts once and never duplicates. Confidentiality is
    // inherited: a notification is only ever created for a record the audience
    // may already see, so the feed can leak nothing the reads would not.
    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_notifications (
        id $pk, audience VARCHAR(10) DEFAULT 'CLIENT', partner_id INT,
        event VARCHAR(30) DEFAULT '', ref_kind VARCHAR(20) DEFAULT '', ref_id INT DEFAULT 0,
        title VARCHAR(255) DEFAULT '', url VARCHAR(200) DEFAULT '',
        is_read INT DEFAULT 0, created_at VARCHAR(30) DEFAULT '')");
}

// ============================================================================
//  VENDOR PORTAL — its own identity, its own session key (vuid), scoped to the
//  vendor's business_partner id. Mirrors the client portal's design rules.
// ============================================================================

// Minimal, extensible permission set. Blank perms = everything (so an existing
// vendor account is never locked out by a new key). Slice 3 adds 'issues'.
const VENDOR_PERMS = ['reports' => 'Their reports', 'issues' => 'Nonconformities raised to them'];

function cvp_vendor_enabled() { return setting_get('vendor_portal_enabled', '0') === '1'; }

function cvp_vendor_user() {
    if (empty($_SESSION['vuid'])) return null;
    static $u = null, $for = 0;
    if ($u === null || $for !== (int)$_SESSION['vuid']) {
        $for = (int)$_SESSION['vuid'];
        $u = portal_try(fn() => ops_one(
            "SELECT vu.*, p.legal_name, p.display_name
             FROM vendor_users vu JOIN business_partners p ON p.id = vu.vendor_id
             WHERE vu.id = ? AND vu.is_active = 1", [$for]), null);
    }
    return $u ?: null;
}
function cvp_vendor_reload() {
    $id = (int)($_SESSION['vuid'] ?? 0);
    if (!$id) return null;
    return portal_try(fn() => ops_one(
        "SELECT vu.*, p.legal_name, p.display_name
         FROM vendor_users vu JOIN business_partners p ON p.id = vu.vendor_id
         WHERE vu.id = ? AND vu.is_active = 1", [$id]), null) ?: null;
}
// The ONLY source of vendor scope — from the session, never from the request.
function cvp_vendor_id() { $u = cvp_vendor_user(); return $u ? (int)$u['vendor_id'] : 0; }
function cvp_vendor_name() {
    $u = cvp_vendor_user();
    return $u ? (string)(($u['display_name'] ?? '') ?: ($u['legal_name'] ?? '')) : '';
}
function cvp_vendor_perms() {
    $u = cvp_vendor_user();
    if (!$u) return [];
    $p = trim((string)($u['perms'] ?? ''));
    return $p === '' ? array_keys(VENDOR_PERMS) : array_values(array_filter(explode(',', $p)));
}
function vcan($key) { return in_array($key, cvp_vendor_perms(), true); }

function cvp_vendor_off() {
    http_response_code(404);
    require __DIR__ . '/../views/vendor/off.php';
    exit;
}
function cvp_vendor_require() {
    if (!cvp_vendor_enabled()) cvp_vendor_off();
    if (!cvp_vendor_user()) redirect('/vendor/login');
    $u = cvp_vendor_user();
    if (!empty($u['must_change']) && strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '/vendor/password') === false)
        redirect('/vendor/password');
}
function cvp_vendor_log($action, $detail = '') {
    $u = cvp_vendor_user();
    try {
        db()->prepare("INSERT INTO vendor_audit (vendor_user_id,vendor_id,action,detail,ip,at) VALUES (?,?,?,?,?,?)")
            ->execute([$u ? (int)$u['id'] : null, $u ? (int)$u['vendor_id'] : null,
                       $action, substr((string)$detail, 0, 300),
                       function_exists('client_ip') ? client_ip() : '', date('c')]);
    } catch (Throwable $e) { /* the trail must never break the screen */ }
}

function cvp_vendor_login($email, $password) {
    $email = strtolower(trim((string)$email));
    if ($email === '') return 'Enter your e-mail address.';
    $wait = function_exists('login_locked_for') ? login_locked_for('vendor:' . $email) : 0;
    if ($wait > 0) return 'Too many attempts. Try again in ' . $wait . ' minute(s).';
    $u = portal_try(fn() => ops_one("SELECT * FROM vendor_users WHERE LOWER(email)=? AND is_active=1", [$email]), null);
    if (!$u || (string)$u['password_hash'] === '' || !password_verify((string)$password, (string)$u['password_hash'])) {
        if (function_exists('login_fail')) login_fail('vendor:' . $email);
        return 'That e-mail address and password do not match.';
    }
    cvp_vendor_start_session((int)$u['id']);
    if (function_exists('login_clear')) login_clear('vendor:' . $email);
    try { db()->prepare("UPDATE vendor_users SET last_login_at=?, last_login_ip=? WHERE id=?")
              ->execute([date('c'), function_exists('client_ip') ? client_ip() : '', (int)$u['id']]); }
    catch (Throwable $e) {}
    cvp_vendor_log('LOGIN', $email);
    return '';
}
// A fresh session id with no client OR staff identity left in it. A vendor,
// a client and a staff member are three different people and must never share
// a session — hence the unset of both cuid and uid, not a merge.
function cvp_vendor_start_session($vendorUserId) {
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    unset($_SESSION['csrf'], $_SESSION['uid'], $_SESSION['pending_uid'], $_SESSION['cuid']);
    $_SESSION['vuid'] = (int)$vendorUserId;
}

// ---- Vendor reads — every one filters by the session's vendor id -----------
// Only reports staff have SHARED (vendor_visible=1) and finalized. No cost, no
// margin, no client commercial columns — the same omission discipline as the
// client portal.
function cvp_vendor_reports($limit = 200) {
    $vid = cvp_vendor_id();
    if (!$vid) return [];
    return portal_try(fn() => ops_all(
        "SELECT d.id, d.irn, d.title, d.type_code, d.result, d.release_status,
                d.inspection_date, d.finalized_at, d.issue_date, d.verify_code, d.rev
         FROM report_docs d
         WHERE d.vendor_id = ? AND d.finalized = 1 AND COALESCE(d.deleted,0) = 0
           AND COALESCE(d.vendor_visible,0) = 1
         ORDER BY d.finalized_at DESC, d.id DESC LIMIT " . max(1, (int)$limit), [$vid]));
}
function cvp_vendor_report($id) {
    $vid = cvp_vendor_id();
    if (!$vid) return null;
    // The vendor id is in the WHERE clause, and so is the share flag — a report
    // that is not theirs, or not shared, simply does not exist here.
    return portal_try(fn() => ops_one(
        "SELECT d.*, v.display_name vendor_disp, v.legal_name vendor_name, rt.name type_name
         FROM report_docs d
         LEFT JOIN business_partners v ON v.id = d.vendor_id
         LEFT JOIN report_types rt     ON rt.id = d.report_type_id
         WHERE d.id = ? AND d.vendor_id = ? AND d.finalized = 1
           AND COALESCE(d.deleted,0) = 0 AND COALESCE(d.vendor_visible,0) = 1",
        [(int)$id, $vid]), null) ?: null;
}
function cvp_vendor_report_pdf($doc) {
    $lh = function_exists('quote_letterhead') ? quote_letterhead() : ['name' => app_name()];
    $pdf = report_pdf_build($doc, idems_sections($doc['report_type_id']), idems_fields($doc['report_type_id']),
        json_decode($doc['data'] ?: '[]', true) ?: [], idems_doc_files($doc['id']), $lh,
        idems_report_signatures($doc));
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="'
         . preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$doc['irn']) . '.pdf"');
    echo $pdf;
}
function cvp_vendor_dashboard() {
    $vid = cvp_vendor_id();
    $reports = (int)(portal_try(fn() => ops_val(
        "SELECT COUNT(*) FROM report_docs WHERE vendor_id=? AND finalized=1 AND COALESCE(deleted,0)=0 AND COALESCE(vendor_visible,0)=1", [$vid]), 0));
    $recent = cvp_vendor_reports(5);
    // Open nonconformities raised to this vendor and marked vendor-visible — the
    // count is safe to show now; Slice 3 wires the respond loop behind it.
    $issues = 0;
    if (function_exists('cvp_visibility_sql')) {
        [$vw, $va] = cvp_visibility_sql('n.visibility', 'VENDOR');
        $issues = (int)(portal_try(fn() => ops_val(
            "SELECT COUNT(*) FROM nonconformities n WHERE n.partner_id=? AND n.status <> 'CLOSED' AND $vw",
            array_merge([$vid], $va)), 0));
    }
    return ['reports' => $reports, 'recent' => $recent, 'issues_open' => $issues,
            'actions' => cvp_action_centre('VENDOR', $vid, 'vcan'),
            'unread'  => cvp_notify_count('VENDOR', $vid)];
}

// ---- Vendor onboarding (staff invite; vendor sets own password) ------------
function cvp_vendor_users_for($vendorId) {
    return portal_try(fn() => ops_all("SELECT * FROM vendor_users WHERE vendor_id=? ORDER BY name, id", [(int)$vendorId]));
}
function cvp_vendor_users_all() {
    return portal_try(fn() => ops_all(
        "SELECT vu.*, COALESCE(p.display_name, p.legal_name) partner_name
         FROM vendor_users vu LEFT JOIN business_partners p ON p.id = vu.vendor_id
         ORDER BY partner_name, vu.name"));
}
function cvp_vendor_invite($vendorId, $email, $name, $contactId = 0) {
    $vendorId = (int)$vendorId; $contactId = (int)$contactId;
    if ($contactId > 0) {
        $c = portal_try(fn() => ops_one("SELECT * FROM partner_contacts WHERE id=?", [$contactId]), null);
        if (!$c) return ['err' => 'That contact no longer exists.'];
        $vendorId = (int)$c['partner_id'];
        if (trim((string)($c['email'] ?? '')) === '') return ['err' => 'That contact has no e-mail on the vendor record — add one first, or type the address.'];
        $email = strtolower(trim((string)$c['email']));
        if (trim((string)$name) === '') $name = (string)$c['name'];
    } else {
        $email = strtolower(trim((string)$email));
    }
    if (!$vendorId) return ['err' => 'Choose the vendor company.'];
    // The partner must actually be a vendor — a client id must not be onboarded
    // into the vendor portal by mistake.
    $isVendor = portal_try(fn() => ops_val("SELECT COALESCE(is_vendor,0) FROM business_partners WHERE id=?", [$vendorId]), 0);
    if (!(int)$isVendor) return ['err' => 'That company is not marked as a vendor. Mark it as a vendor in the directory first.'];
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['err' => 'A valid e-mail address is needed.'];
    $exists = portal_try(fn() => ops_val("SELECT COUNT(*) FROM vendor_users WHERE LOWER(email)=?", [$email]), 0);
    if ((int)$exists > 0) return ['err' => 'That address already has vendor portal access.'];
    if ($contactId === 0) {
        $match = portal_try(fn() => ops_one("SELECT id FROM partner_contacts WHERE partner_id=? AND LOWER(email)=? ORDER BY is_primary DESC, id LIMIT 1", [$vendorId, $email]), null);
        if ($match) $contactId = (int)$match['id'];
    }
    $token = bin2hex(random_bytes(24));
    db()->prepare("INSERT INTO vendor_users (vendor_id,contact_id,email,name,password_hash,is_active,must_change,
                   invite_token,invite_expires,created_by,created_at) VALUES (?,?,?,?,'',1,1,?,?,?,?)")
        ->execute([$vendorId, $contactId ?: null, $email, substr(trim((string)$name), 0, 150), $token,
                   date('c', time() + 7 * 86400), function_exists('user_name') ? user_name(current_user()) : '', date('c')]);
    return ['id' => (int)db()->lastInsertId(), 'token' => $token,
            'link' => portal_base_url() . '/vendor/accept?t=' . $token];
}
function cvp_vendor_invite_problem($token) {
    $token = trim((string)$token);
    $u = $token === '' ? null : portal_try(fn() => ops_one(
        "SELECT * FROM vendor_users WHERE invite_token=? AND invite_token<>'' AND is_active=1", [$token]), null);
    if (!$u) return 'That invitation is not valid — it may already have been used. '
                  . 'Ask your contact at ' . app_name() . ' to send a new one.';
    if ($u['invite_expires'] !== '' && $u['invite_expires'] < date('c'))
        return 'That invitation has expired. Ask your contact for a new one.';
    return '';
}
function cvp_vendor_accept($token, $password, $confirm) {
    $why = cvp_vendor_invite_problem($token);
    if ($why !== '') return $why;
    $u = ops_one("SELECT * FROM vendor_users WHERE invite_token=? AND is_active=1", [trim((string)$token)]);
    if ((string)$password !== (string)$confirm) return 'The two passwords do not match.';
    $why = function_exists('password_problem_text') ? password_problem_text((string)$password, (string)$u['email'], (string)$u['name']) : '';
    if ($why !== '') return $why;
    db()->prepare("UPDATE vendor_users SET password_hash=?, must_change=0, invite_token='', invite_expires='' WHERE id=?")
        ->execute([password_hash((string)$password, PASSWORD_DEFAULT), (int)$u['id']]);
    cvp_vendor_start_session((int)$u['id']);
    cvp_vendor_log('ACCEPTED_INVITE', $u['email']);
    if (function_exists('consent_record')) {
        consent_record([
            'subject_kind' => 'VENDOR_PORTAL', 'subject_id' => (int)$u['id'],
            'subject_name' => (string)($u['name'] ?: $u['email']),
            'purpose' => 'Vendor portal access to their own reports and nonconformities',
            'basis' => 'CONSENT', 'given_at' => date('c'),
            'note' => 'Recorded automatically when the invitee accepted the invitation and set their own password.',
            'recorded_by' => 'The person themselves (vendor invitation accepted)',
        ]);
    }
    return '';
}

// Staff control: share (or un-share) a finalized report with its vendor's
// portal. Returns true on a change. Only a report that actually names a vendor
// can be shared — there is nobody to share it with otherwise.
function cvp_report_set_vendor_visible($docId, $on) {
    $d = ops_one("SELECT id, vendor_id, finalized FROM report_docs WHERE id=? AND COALESCE(deleted,0)=0", [(int)$docId]);
    if (!$d || !(int)$d['vendor_id']) return false;
    db()->prepare("UPDATE report_docs SET vendor_visible=? WHERE id=?")->execute([$on ? 1 : 0, (int)$d['id']]);
    return true;
}

// ---- Vendor screens & routing ----------------------------------------------
function cvp_vendor_view($name, $vars = [], $nav = true) {
    extract($vars);
    $u = cvp_vendor_user();
    ob_start();
    require __DIR__ . '/../views/vendor/top.php';
    require __DIR__ . "/../views/vendor/$name.php";
    require __DIR__ . '/../views/vendor/bottom.php';
    echo csrf_stamp_forms((string)ob_get_clean());
}
function cvp_vendor_csrf_or_die($method) {
    if ($method !== 'POST') return;
    if (csrf_ok($_POST['_csrf'] ?? '')) return;
    http_response_code(400);
    $err = 'That form was not sent from this page, or it had expired. Open the page again and retype it — nothing was changed.';
    cvp_vendor_view('message', ['title' => 'Please try that again', 'body' => $err], (bool)cvp_vendor_user());
    exit;
}
function cvp_vendor_need($key, $what = 'that') {
    if (vcan($key)) return;
    cvp_vendor_view('message', ['title' => 'Not available to you',
        'body' => 'Your access here does not include ' . $what . '. Ask your main contact at ' . app_name() . ' if you need it.']);
    exit;
}

function cvp_vendor_route($route, $method) {
    cvp_vendor_csrf_or_die($method);

    if ($route === 'vendor/login') {
        if (!cvp_vendor_enabled()) cvp_vendor_off();
        if (cvp_vendor_user()) redirect('/vendor');
        $err = '';
        if ($method === 'POST') {
            $err = cvp_vendor_login($_POST['email'] ?? '', $_POST['password'] ?? '');
            if ($err === '') redirect('/vendor');
        }
        cvp_vendor_view('login', ['err' => $err], false);
        exit;
    }
    if ($route === 'vendor/accept') {
        if (!cvp_vendor_enabled()) cvp_vendor_off();
        $token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
        $err = cvp_vendor_invite_problem($token);
        $dead = $err !== '';
        if (!$dead && $method === 'POST') {
            $err = cvp_vendor_accept($token, $_POST['password'] ?? '', $_POST['confirm'] ?? '');
            if ($err === '') redirect('/vendor');
        }
        cvp_vendor_view('accept', ['err' => $err, 'token' => $token, 'dead' => $dead], false);
        exit;
    }
    if ($route === 'vendor/logout') {
        cvp_vendor_log('LOGOUT');
        unset($_SESSION['vuid']);
        session_destroy();
        redirect('/vendor/login');
    }

    cvp_vendor_require();
    $u = cvp_vendor_user();

    switch ($route) {
        case 'vendor':
            cvp_vendor_log('DASHBOARD');
            cvp_notify_sync('VENDOR', cvp_vendor_id());
            cvp_vendor_view('dashboard', ['d' => cvp_vendor_dashboard()]);
            exit;

        case 'vendor/alerts':
            cvp_notify_sync('VENDOR', cvp_vendor_id());
            if ($method === 'POST') {
                if (!empty($_POST['read_all'])) cvp_notify_read_all('VENDOR', cvp_vendor_id());
                elseif (!empty($_POST['id']))   cvp_notify_read('VENDOR', cvp_vendor_id(), (int)$_POST['id']);
                redirect('/vendor/alerts');
            }
            cvp_vendor_view('alerts', ['rows' => cvp_notifications('VENDOR', cvp_vendor_id()), 'events' => CVP_EVENTS]);
            exit;

        case 'vendor/reports':
            cvp_vendor_need('reports', 'reports');
            cvp_vendor_view('reports', ['rows' => cvp_vendor_reports()]);
            exit;

        case 'vendor/report':
            cvp_vendor_need('reports', 'reports');
            $d = cvp_vendor_report((int)($_GET['id'] ?? 0));
            if (!$d) { http_response_code(404); cvp_vendor_view('notfound'); exit; }
            cvp_vendor_log('REPORT_OPENED', $d['irn']);
            cvp_vendor_report_pdf($d);
            exit;

        case 'vendor/password':
            $err = ''; $done = false;
            if ($method === 'POST') {
                $new = (string)($_POST['password'] ?? '');
                if ($new !== (string)($_POST['confirm'] ?? '')) $err = 'The two passwords do not match.';
                else {
                    $why = function_exists('password_problem_text')
                         ? password_problem_text($new, (string)$u['email'], (string)$u['name']) : '';
                    if ($why !== '') $err = $why;
                    else {
                        db()->prepare("UPDATE vendor_users SET password_hash=?, must_change=0 WHERE id=?")
                            ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$u['id']]);
                        cvp_vendor_log('PASSWORD_CHANGED');
                        $done = true;
                        $u = cvp_vendor_reload();
                    }
                }
            }
            cvp_vendor_view('password', ['err' => $err, 'done' => $done]);
            exit;
    }

    // Slice 3 will add vendor/issues + vendor/issue here.
    if (function_exists('cvp_vendor_route_issues') && cvp_vendor_route_issues($route, $method, $u)) exit;

    http_response_code(404);
    cvp_vendor_view('notfound');
    exit;
}

// ============================================================================
//  SLICE 3 — the external nonconformity loop, shared by BOTH portals.
//
//  A client and a vendor can now VIEW nonconformities raised to them and
//  RESPOND (accept / partially accept / disagree / clarify / propose an action),
//  with a root-cause note, a proposed corrective action and an evidence
//  reference. The response feeds the SAME engine staff use: it is written as an
//  issue_disputes row (the party-response register, ncdca.php) and stamped on the
//  NCR's event trail (ncr_log). Nothing is shown that is not marked visible to
//  that audience — the read passes through the Slice-1 visibility gate, in the
//  WHERE clause, on top of an ownership match on partner_id.
// ============================================================================

// Nonconformities raised to $partnerId that $audience ('CLIENT'|'VENDOR') may
// see. Ownership (partner_id) AND visibility are both required — belt and braces.
function cvp_issues_for($audience, $partnerId, $openOnly = false) {
    $partnerId = (int)$partnerId;
    if (!$partnerId) return [];
    [$vw, $va] = cvp_visibility_sql('n.visibility', $audience);
    $extra = $openOnly ? " AND n.status <> 'CLOSED'" : '';
    return portal_try(fn() => ops_all(
        "SELECT n.id, n.ref, n.title, n.description, n.severity, n.status, n.issue_type,
                n.detected_on, n.due_on, n.disposition, n.clause,
                (SELECT COUNT(*) FROM issue_disputes d WHERE d.ncr_id = n.id AND d.party = ?) my_responses
         FROM nonconformities n
         WHERE n.partner_id = ? AND $vw $extra
         ORDER BY (n.status <> 'CLOSED') DESC, n.id DESC",
        array_merge([strtoupper((string)$audience), $partnerId], $va)), []);
}

// A single nonconformity, ownership + visibility gated in the WHERE clause — an
// issue that is not theirs, or not marked visible to them, simply does not exist.
function cvp_issue($audience, $partnerId, $ncrId) {
    $partnerId = (int)$partnerId;
    if (!$partnerId) return null;
    [$vw, $va] = cvp_visibility_sql('n.visibility', $audience);
    return portal_try(fn() => ops_one(
        "SELECT n.* FROM nonconformities n WHERE n.id = ? AND n.partner_id = ? AND $vw",
        array_merge([(int)$ncrId, $partnerId], $va)), null) ?: null;
}

// The party's own responses on an issue, for the thread on the detail screen.
function cvp_issue_responses($audience, $ncrId) {
    return portal_try(fn() => ops_all(
        "SELECT * FROM issue_disputes WHERE ncr_id = ? AND party = ? ORDER BY id DESC",
        [(int)$ncrId, strtoupper((string)$audience)]), []);
}

// Record a party's response to an issue. Validates ownership+visibility again
// (never trusts the caller), and that the response kind is a real one. Writes
// through the SAME engine staff read (issue_disputes + ncr_log). Returns '' on
// success, or a message.
function cvp_issue_respond($audience, $partnerId, $ncrId, $data, $actorName = '') {
    $n = cvp_issue($audience, $partnerId, $ncrId);
    if (!$n) return 'That item is not available to you.';
    if ((string)$n['status'] === 'CLOSED') return 'This item is closed. If you need to reopen it, contact us.';
    $kind = strtoupper(trim((string)($data['response_kind'] ?? '')));
    if (!isset(NCDCA_RESPONSE_KINDS[$kind])) return 'Choose how you are responding.';
    $response = trim((string)($data['response'] ?? ''));
    if ($response === '') return 'Write your response — a bare acknowledgement is not enough for the record.';
    ncdca_dispute_create((int)$n['id'], [
        'party'           => strtoupper((string)$audience),
        'disputed_field'  => (string)($data['disputed_field'] ?? ''),
        'reason'          => (string)($data['reason'] ?? ''),           // root cause
        'response_kind'   => $kind,
        'response'        => $response,
        'proposed_action' => (string)($data['proposed_action'] ?? ''), // corrective action
        'evidence_ref'    => (string)($data['evidence_ref'] ?? ''),
    ]);
    if (function_exists('ncr_log')) {
        try { ncr_log((int)$n['id'], 'PARTY_RESPONSE',
            ucfirst(strtolower((string)$audience)) . ' response (' . (NCDCA_RESPONSE_KINDS[$kind] ?? $kind) . ')'
            . ($actorName !== '' ? ' by ' . $actorName : '')); } catch (Throwable $e) {}
    }
    return '';
}

// Marker used by the vendor nav to decide whether to show the issues link
// (present = Slice 3 loaded). Kept tiny and side-effect-free.
function cvp_vendor_issue_links() { return true; }

// The vendor portal's issue routes, called from cvp_vendor_route(). Returns true
// when it handled the route (and has emitted a page), false otherwise.
function cvp_vendor_route_issues($route, $method, $u) {
    if ($route === 'vendor/issues') {
        cvp_vendor_need('issues', 'nonconformities');
        cvp_vendor_view('issues', ['rows' => cvp_issues_for('VENDOR', cvp_vendor_id())]);
        return true;
    }
    if ($route === 'vendor/issue') {
        cvp_vendor_need('issues', 'nonconformities');
        $n = cvp_issue('VENDOR', cvp_vendor_id(), (int)($_GET['id'] ?? $_POST['id'] ?? 0));
        if (!$n) { http_response_code(404); cvp_vendor_view('notfound'); return true; }
        $err = '';
        if ($method === 'POST') {
            $err = cvp_issue_respond('VENDOR', cvp_vendor_id(), (int)$n['id'], $_POST, (string)($u['name'] ?? ''));
            if ($err === '') {
                cvp_vendor_log('ISSUE_RESPONSE', (string)$n['ref']);
                $_SESSION['vendor_flash'] = 'Thank you — your response to ' . $n['ref'] . ' is recorded and with our team.';
                redirect('/vendor/issues');
            }
        }
        cvp_vendor_view('issue', ['n' => $n, 'err' => $err,
            'responses' => cvp_issue_responses('VENDOR', (int)$n['id']),
            'kinds' => NCDCA_RESPONSE_KINDS]);
        return true;
    }
    return false;
}

// ============================================================================
//  SLICE 4 — the portal notification feed + the "awaiting you" action centre.
//
//  The feed is state-derived, not event-hooked: cvp_notify_sync() reconciles a
//  party's feed with what already exists for them (issued/shared reports,
//  visible nonconformities, decided responses) and inserts a row the first time
//  it sees each. That means it needs NO edits to the report, NCR or CAPA engines
//  — it is a lens over their output, self-healing and impossible to leave in a
//  half-wired state. It runs cheaply on each portal load. Every notification is
//  for a record the audience can already open, so it can leak nothing.
//
//  The action centre is purely live: "these N things are waiting for YOU right
//  now" — reports to decide, nonconformities to respond to, attendance to
//  approve. No storage; it is always the truth.
// ============================================================================

const CVP_EVENTS = [
    'REPORT_ISSUED'  => 'A report was issued to you',
    'REPORT_SHARED'  => 'A report was shared with you',
    'NCR_RAISED'     => 'A nonconformity needs your response',
    'NCR_DECIDED'    => 'We reviewed your response',
];

// Insert a notification once. The natural key stops the sync duplicating it.
function cvp_notify_once($audience, $partnerId, $event, $refKind, $refId, $title, $url) {
    $audience = strtoupper((string)$audience); $partnerId = (int)$partnerId;
    if (!$partnerId) return false;
    $exists = portal_try(fn() => ops_val(
        "SELECT COUNT(*) FROM portal_notifications WHERE audience=? AND partner_id=? AND event=? AND ref_kind=? AND ref_id=?",
        [$audience, $partnerId, $event, $refKind, (int)$refId]), 0);
    if ((int)$exists > 0) return false;
    db()->prepare("INSERT INTO portal_notifications (audience,partner_id,event,ref_kind,ref_id,title,url,is_read,created_at)
                   VALUES (?,?,?,?,?,?,?,0,?)")
        ->execute([$audience, $partnerId, $event, $refKind, (int)$refId, substr((string)$title, 0, 255), (string)$url, date('c')]);
    return true;
}

// Reconcile a party's feed with current state. Cheap, idempotent, no engine edits.
function cvp_notify_sync($audience, $partnerId) {
    $audience = strtoupper((string)$audience); $partnerId = (int)$partnerId;
    if (!$partnerId) return;
    try {
        if ($audience === 'CLIENT') {
            foreach (portal_try(fn() => ops_all(
                "SELECT id, irn FROM report_docs WHERE client_id=? AND finalized=1 AND COALESCE(deleted,0)=0
                 ORDER BY id DESC LIMIT 100", [$partnerId]), []) as $r)
                cvp_notify_once('CLIENT', $partnerId, 'REPORT_ISSUED', 'report', (int)$r['id'],
                    'Report ' . $r['irn'] . ' issued to you', '/portal/report?id=' . (int)$r['id']);
        } elseif ($audience === 'VENDOR') {
            foreach (portal_try(fn() => ops_all(
                "SELECT id, irn FROM report_docs WHERE vendor_id=? AND finalized=1 AND COALESCE(deleted,0)=0
                 AND COALESCE(vendor_visible,0)=1 ORDER BY id DESC LIMIT 100", [$partnerId]), []) as $r)
                cvp_notify_once('VENDOR', $partnerId, 'REPORT_SHARED', 'report', (int)$r['id'],
                    'Report ' . $r['irn'] . ' shared with you', '/vendor/report?id=' . (int)$r['id']);
        }
        // Nonconformities visible to this audience (open ones need a response).
        $issueUrl = $audience === 'VENDOR' ? '/vendor/issue?id=' : '/portal/issue?id=';
        foreach (cvp_issues_for($audience, $partnerId, true) as $n)
            cvp_notify_once($audience, $partnerId, 'NCR_RAISED', 'ncr', (int)$n['id'],
                'Nonconformity ' . ($n['ref'] ?: ('#' . (int)$n['id'])) . ' needs your response', $issueUrl . (int)$n['id']);
        // Responses we have since decided.
        foreach (portal_try(fn() => ops_all(
            "SELECT id, ncr_id, decision FROM issue_disputes WHERE party=? AND COALESCE(decision,'')<>''
             ORDER BY id DESC LIMIT 100", [$audience]), []) as $d) {
            // Only for issues that belong to this partner (ownership check).
            if (!cvp_issue($audience, $partnerId, (int)$d['ncr_id'])) continue;
            cvp_notify_once($audience, $partnerId, 'NCR_DECIDED', 'dispute', (int)$d['id'],
                'We reviewed your response (' . $d['decision'] . ')', $issueUrl . (int)$d['ncr_id']);
        }
    } catch (Throwable $e) { /* the feed must never break the page */ }
}

function cvp_notifications($audience, $partnerId, $unreadOnly = false, $limit = 100) {
    $partnerId = (int)$partnerId; if (!$partnerId) return [];
    $w = $unreadOnly ? ' AND is_read=0' : '';
    return portal_try(fn() => ops_all(
        "SELECT * FROM portal_notifications WHERE audience=? AND partner_id=?$w
         ORDER BY is_read ASC, id DESC LIMIT " . max(1, (int)$limit),
        [strtoupper((string)$audience), $partnerId]), []);
}
function cvp_notify_count($audience, $partnerId) {
    $partnerId = (int)$partnerId; if (!$partnerId) return 0;
    return (int)(portal_try(fn() => ops_val(
        "SELECT COUNT(*) FROM portal_notifications WHERE audience=? AND partner_id=? AND is_read=0",
        [strtoupper((string)$audience), $partnerId]), 0));
}
function cvp_notify_read($audience, $partnerId, $id) {
    db()->prepare("UPDATE portal_notifications SET is_read=1 WHERE id=? AND audience=? AND partner_id=?")
        ->execute([(int)$id, strtoupper((string)$audience), (int)$partnerId]);
}
function cvp_notify_read_all($audience, $partnerId) {
    db()->prepare("UPDATE portal_notifications SET is_read=1 WHERE audience=? AND partner_id=? AND is_read=0")
        ->execute([strtoupper((string)$audience), (int)$partnerId]);
}

// The live "awaiting you" list — always the current truth, never stored.
// Returns [['label'=>..,'count'=>N,'url'=>..], ...] for the things this party
// must act on. $can is a permission checker (pcan for client, vcan for vendor).
function cvp_action_centre($audience, $partnerId, $can) {
    $audience = strtoupper((string)$audience); $partnerId = (int)$partnerId;
    $out = [];
    if (!$partnerId) return $out;

    if ($audience === 'CLIENT') {
        if ($can('reports.decide')) {
            // Finalized reports with no client decision recorded for the current rev.
            $n = (int)(portal_try(fn() => ops_val(
                "SELECT COUNT(*) FROM report_docs d WHERE d.client_id=? AND d.finalized=1 AND COALESCE(d.deleted,0)=0
                 AND NOT EXISTS (SELECT 1 FROM report_client_reviews r WHERE r.report_doc_id=d.id AND r.rev=d.rev)",
                [$partnerId]), 0));
            if ($n > 0) $out[] = ['label' => $n . ' report' . ($n > 1 ? 's' : '') . ' to accept or reject', 'count' => $n, 'url' => '/portal/reports'];
        }
        if ($can('deputation.approve') && function_exists('pdso_portal_approvals')) {
            $ap = portal_try(fn() => pdso_portal_approvals($partnerId), []);
            $n = 0; foreach ((array)$ap as $a) if (in_array(($a['status'] ?? ''), ['SUBMITTED','CLIENT_REVIEW'], true)) $n++;
            if ($n > 0) $out[] = ['label' => $n . ' attendance period' . ($n > 1 ? 's' : '') . ' to approve', 'count' => $n, 'url' => '/portal/deputations'];
        }
    }
    // Open nonconformities awaiting a response — both audiences.
    if ($can('issues') && function_exists('cvp_issues_for')) {
        $open = cvp_issues_for($audience, $partnerId, true);
        $n = count($open);
        if ($n > 0) $out[] = ['label' => $n . ' nonconformity' . ($n > 1 ? '(ies)' : '') . ' to respond to', 'count' => $n,
            'url' => $audience === 'VENDOR' ? '/vendor/issues' : '/portal/issues'];
    }
    return $out;
}

// ============================================================================
//  STAFF SIDE — invite vendor users, switch the portal on, share reports.
//  Mirrors ops_portal_admin. Gated by the same portal module permission, so
//  whoever manages the client portal manages the vendor portal too.
// ============================================================================
function cvp_vendor_can_manage() { return is_master() || (function_exists('can') && can('mod.portal.edit')); }

// Finalized reports that name a vendor, with their current share state — the
// list staff tick to share a report into the vendor portal.
function cvp_vendor_shareable_reports($limit = 300) {
    return portal_try(fn() => ops_all(
        "SELECT d.id, d.irn, d.title, d.type_code, d.finalized_at, d.vendor_visible,
                COALESCE(v.display_name, v.legal_name) vendor_name
         FROM report_docs d JOIN business_partners v ON v.id = d.vendor_id
         WHERE d.finalized = 1 AND COALESCE(d.deleted,0) = 0 AND COALESCE(v.is_vendor,0) = 1
         ORDER BY d.finalized_at DESC, d.id DESC LIMIT " . max(1, (int)$limit)), []);
}

function ops_cvp_vendor_admin($route, $method) {
    ops_require(cvp_vendor_can_manage(), 'Only somebody who manages the portal can open the vendor register.');

    if ($route === 'vendor-users') {
        $invite = null;
        if ($method === 'POST') {
            $r = cvp_vendor_invite((int)($_POST['vendor_id'] ?? 0), $_POST['email'] ?? '', $_POST['name'] ?? '', (int)($_POST['contact_id'] ?? 0));
            if (!empty($r['err'])) flash($r['err'], 'error');
            else { $invite = $r; flash('Invitation created. Send them the link below — we never choose or e-mail a password.'); }
        }
        view('ops/vendor_users', [
            'rows'    => cvp_vendor_users_all(),
            'vendors' => vendors_list(),
            'invite'  => $invite,
            'enabled' => cvp_vendor_enabled(),
            'base'    => portal_base_url(),
            'reports' => cvp_vendor_shareable_reports(),
            'contactsByPartner' => portal_contacts_by_partner(),
        ]);
        return true;
    }

    if ($route === 'vendor-user-toggle' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $u = portal_try(fn() => ops_one("SELECT * FROM vendor_users WHERE id=?", [$id]), null);
        if ($u) {
            db()->prepare("UPDATE vendor_users SET is_active=? WHERE id=?")->execute([empty($u['is_active']) ? 1 : 0, $id]);
            flash(empty($u['is_active']) ? 'Access restored for ' . $u['email'] . '.'
                : 'Access withdrawn from ' . $u['email'] . '. They cannot sign in again.');
        }
        redirect('/vendor-users');
    }

    if ($route === 'vendor-user-reinvite' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $u = portal_try(fn() => ops_one("SELECT * FROM vendor_users WHERE id=?", [$id]), null);
        if ($u) {
            $token = bin2hex(random_bytes(24));
            db()->prepare("UPDATE vendor_users SET invite_token=?, invite_expires=?, password_hash='', must_change=1 WHERE id=?")
                ->execute([$token, date('c', time() + 7 * 86400), $id]);
            $_SESSION['vendor_invite_link'] = portal_base_url() . '/vendor/accept?t=' . $token;
            flash('A new invitation link has been made for ' . $u['email'] . '. Their old password no longer works.');
        }
        redirect('/vendor-users');
    }

    if ($route === 'vendor-settings' && $method === 'POST') {
        ops_require(is_master() || is_admin_level(), 'Only an administrator can switch the vendor portal on.');
        setting_set('vendor_portal_enabled', !empty($_POST['vendor_portal_enabled']) ? '1' : '0');
        flash(cvp_vendor_enabled()
            ? 'The vendor portal is on. Nobody can reach it until you invite them.'
            : 'The vendor portal is off. Every /vendor address now answers "not found".');
        redirect('/vendor-users');
    }

    if ($route === 'vendor-share' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $on = !empty($_POST['on']);
        if (cvp_report_set_vendor_visible($id, $on)) {
            cvp_vendor_log('REPORT_SHARE', $id . ':' . ($on ? 'on' : 'off'));
            flash($on ? 'Report shared with the vendor portal.' : 'Report removed from the vendor portal.');
        } else {
            flash('That report cannot be shared — it must be finalized and name a vendor.', 'error');
        }
        redirect('/vendor-users');
    }

    redirect('/vendor-users');
    return true;
}

// ---------------------------------------------------------------------------
//  Row-level gate — "may this audience see a row with this visibility?"
// ---------------------------------------------------------------------------
// INTERNAL (staff) sees everything. An external audience sees a row only if the
// row's visibility code lists that audience. Used to double-check a single row
// after it is fetched; the SQL builder below is what keeps a whole query clean.
function cvp_can_see($visibility, $audience) {
    $audience = strtoupper((string)$audience);
    if ($audience === 'INTERNAL') return true;
    $code = strtoupper(trim((string)$visibility));
    $allowed = CVP_VISIBILITY_AUDIENCE[$code] ?? [];
    return in_array($audience, $allowed, true);
}

// The set of visibility codes an external audience is allowed to read.
function cvp_visible_codes($audience) {
    $audience = strtoupper((string)$audience);
    $codes = [];
    foreach (CVP_VISIBILITY_AUDIENCE as $code => $aud) {
        if (in_array($audience, $aud, true)) $codes[] = $code;
    }
    return $codes;
}

// Build a WHERE fragment restricting a query to rows the audience may see, on a
// `visibility` column. Returns [sqlFragment, args] so it drops straight into an
// existing prepared query. INTERNAL returns a pass-through. An external audience
// with no visible codes returns a fragment that matches nothing — an empty
// register is the correct answer, never "everything".
//
//   [$vw, $va] = cvp_visibility_sql('n.visibility', 'CLIENT');
//   $rows = ops_all("SELECT ... WHERE n.vendor_id=? AND $vw", array_merge([$vid], $va));
function cvp_visibility_sql($col, $audience) {
    if (strtoupper((string)$audience) === 'INTERNAL') return ['1=1', []];
    $codes = cvp_visible_codes($audience);
    if (!$codes) return ['1=0', []];
    $ph = implode(',', array_fill(0, count($codes), '?'));
    return ["$col IN ($ph)", $codes];
}

// The int-flag mechanism used by dep_site_log (client_visible = 1). Only the
// CLIENT audience is gated by it today; anything else that is not INTERNAL sees
// nothing through this flag. Returns [sqlFragment, args].
function cvp_client_flag_sql($col = 'client_visible', $audience = 'CLIENT') {
    if (strtoupper((string)$audience) === 'INTERNAL') return ['1=1', []];
    if (strtoupper((string)$audience) === 'CLIENT')   return ["COALESCE($col,0) = 1", []];
    return ['1=0', []];
}

// Which audience is the CURRENT reader? Staff (a real current_user) are INTERNAL.
// A signed-in portal person is CLIENT today; Slice 2 adds VENDOR here when a
// vendor session key exists. Kept in one place so every surface agrees.
function cvp_current_audience() {
    if (function_exists('cvp_vendor_user') && cvp_vendor_user()) return 'VENDOR';
    if (function_exists('portal_user') && portal_user())         return 'CLIENT';
    return 'INTERNAL';
}
