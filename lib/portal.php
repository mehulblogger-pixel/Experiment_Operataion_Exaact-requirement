<?php
// ============================================================================
//  The client portal
//
//  The single biggest thing competitors sell on, and the thing that stops the
//  daily "where is my report?" e-mail. It pairs with /verify from Phase 4: that
//  page proves ONE report is genuine to anybody holding it; this one lets a
//  named person at the client see everything that is theirs.
//
//  ---------------------------------------------------------------------------
//  THE DESIGN RULE, AND WHY IT IS NOT NEGOTIABLE
//
//  A client user is NOT a staff user with fewer permissions. That is how client
//  portals leak, every time: somebody adds a screen, forgets one gate, and a
//  competitor reads another competitor's inspection reports. So:
//
//   · Client identity lives in its own table and its own session key. A client
//     can never be returned by current_user(), so no staff screen can ever be
//     reached by one, even if a route is added carelessly tomorrow.
//   · Every read goes through a function that takes the partner id from the
//     SESSION and puts it in the WHERE clause. There is no portal query that
//     does not filter, because there is no portal query written by hand.
//   · The queries do not SELECT the columns a client must not see. Cost, credit,
//     margin, salary and internal notes are not fetched at all — the same
//     approach as the identity module. What is never loaded cannot leak through
//     a template somebody writes in a hurry.
//
//  ---------------------------------------------------------------------------
//  WHAT A CLIENT SEES
//    their inspection calls and where each one has got to; the deputations and
//    who is going; the reports we have ISSUED, downloadable; their invoices and
//    what is outstanding; and a form to ask for the next inspection.
//
//  WHAT A CLIENT NEVER SEES
//    what the work cost us, what we make on it, what we pay anybody, another
//    client's anything, an unissued draft report, or an internal note.
// ============================================================================

const PORTAL_REQ_STATUS = [
    'NEW'       => 'Received — not yet acted on',
    'ACCEPTED'  => 'Accepted — being arranged',
    'DECLINED'  => 'Declined',
    'CONVERTED' => 'Arranged',
];

function portal_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    // A person at the client company. Deliberately its own table: a row here
    // can never be mistaken for staff by any query anywhere in the app.
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_users (
        id $pk, partner_id INT, contact_id INT NULL,
        email VARCHAR(200) DEFAULT '', name VARCHAR(150) DEFAULT '',
        password_hash VARCHAR(255) DEFAULT '',
        is_active INT DEFAULT 1, must_change INT DEFAULT 1,
        invite_token VARCHAR(64) DEFAULT '', invite_expires VARCHAR(30) DEFAULT '',
        last_login_at VARCHAR(30) DEFAULT '', last_login_ip VARCHAR(60) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // What this particular person may see and do, and at which sites. Blank
    // perms means everything, so existing client users keep their access.
    ensure_column('client_users', 'perms', "VARCHAR(400) DEFAULT ''");
    ensure_column('client_users', 'site_ids', "VARCHAR(400) DEFAULT ''");
    ensure_column('client_users', 'role_preset', "VARCHAR(30) DEFAULT ''");
    // What they asked for. Kept apart from the inspection-call register until a
    // coordinator accepts it — a client cannot create work in our system, only
    // ask for it.
    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_requests (
        id $pk, partner_id INT, client_user_id INT NULL,
        subject VARCHAR(255) DEFAULT '', detail TEXT,
        site VARCHAR(255) DEFAULT '', wanted_on VARCHAR(20) DEFAULT '',
        contact_name VARCHAR(150) DEFAULT '', contact_phone VARCHAR(60) DEFAULT '',
        status VARCHAR(20) DEFAULT 'NEW', handled_by VARCHAR(150) DEFAULT '',
        handled_at VARCHAR(30) DEFAULT '', reply VARCHAR(1000) DEFAULT '',
        call_id INT NULL, created_at VARCHAR(30) DEFAULT '')");
    // Every page a client opened. Cheap, and it answers "who downloaded that
    // report" without anybody having to remember.
    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_audit (
        id $pk, client_user_id INT NULL, partner_id INT NULL,
        action VARCHAR(40) DEFAULT '', detail VARCHAR(300) DEFAULT '',
        ip VARCHAR(60) DEFAULT '', at VARCHAR(30) DEFAULT '')");
    portal_backfill_contact_links();
}

// Link portal accounts back to the contacts on the client record wherever the
// e-mail already matches one, so the two lists of the same people stop drifting
// apart. Only touches accounts not yet linked, so it is a cheap no-op once
// everything is linked; runs in both SQLite and MySQL.
function portal_backfill_contact_links() {
    try {
        db()->exec("UPDATE client_users SET contact_id = (
                SELECT pc.id FROM partner_contacts pc
                WHERE pc.partner_id = client_users.partner_id
                  AND LOWER(pc.email) = LOWER(client_users.email) AND pc.email <> ''
                ORDER BY pc.is_primary DESC, pc.id LIMIT 1)
            WHERE (contact_id IS NULL OR contact_id = 0)
              AND EXISTS (SELECT 1 FROM partner_contacts pc
                WHERE pc.partner_id = client_users.partner_id
                  AND LOWER(pc.email) = LOWER(client_users.email) AND pc.email <> '')");
    } catch (Throwable $e) { /* contacts table may not exist yet on a bare install */ }
}

// Saved contacts for a client, for the invite picker — only those with an
// e-mail, since a portal account needs one.
function portal_contacts_for($partnerId) {
    return portal_try(fn() => ops_all(
        "SELECT id, name, email, designation FROM partner_contacts
         WHERE partner_id=? AND email <> '' ORDER BY is_primary DESC, name", [(int)$partnerId]), []);
}
// All client contacts with an e-mail, grouped by partner id, for the JS picker.
function portal_contacts_by_partner() {
    $out = [];
    foreach (portal_try(fn() => ops_all(
        "SELECT pc.id, pc.partner_id, pc.name, pc.email, pc.designation
         FROM partner_contacts pc JOIN business_partners p ON p.id = pc.partner_id
         WHERE p.is_client=1 AND pc.email <> '' ORDER BY pc.is_primary DESC, pc.name"), []) as $c) {
        $out[(int)$c['partner_id']][] = [
            'id' => (int)$c['id'], 'name' => (string)$c['name'],
            'email' => (string)$c['email'], 'desig' => (string)($c['designation'] ?? ''),
        ];
    }
    return $out;
}

function portal_missing(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false
        || stripos($m, 'no such column') !== false || stripos($m, 'unknown column') !== false;
}
function portal_try($fn, $fallback = []) {
    try { return $fn(); } catch (Throwable $e) { if (portal_missing($e)) return $fallback; throw $e; }
}

function portal_enabled() { return setting_get('portal_enabled', '0') === '1'; }

// ============================================================================
//  Identity — its own session key, never current_user()
// ============================================================================
function portal_user() {
    if (empty($_SESSION['cuid'])) return null;
    static $u = null, $for = 0;
    if ($u === null || $for !== (int)$_SESSION['cuid']) {
        $for = (int)$_SESSION['cuid'];
        $u = portal_try(fn() => ops_one(
            "SELECT cu.*, p.legal_name, p.display_name
             FROM client_users cu JOIN business_partners p ON p.id = cu.partner_id
             WHERE cu.id = ? AND cu.is_active = 1", [$for]), null);
    }
    return $u ?: null;
}

// The company name to put at the top of the client's own screens.
function portal_client_name() {
    $u = portal_user();
    if (!$u) return '';
    return (string)(($u['display_name'] ?? '') ?: ($u['legal_name'] ?? ''));
}

// The ONLY source of scope. Every read below takes it from here — never from a
// query string, a hidden field or anything else a browser can put its hands on.
function portal_partner_id() {
    $u = portal_user();
    return $u ? (int)$u['partner_id'] : 0;
}

function portal_off() {
    http_response_code(404);
    require __DIR__ . '/../views/portal/off.php';
    exit;
}

function portal_require() {
    if (!portal_enabled()) portal_off();
    if (!portal_user()) redirect('/portal/login');
    // A client who has not set their own password does nothing else first.
    $u = portal_user();
    if (!empty($u['must_change']) && strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '/portal/password') === false)
        redirect('/portal/password');
}

function portal_log($action, $detail = '') {
    $u = portal_user();
    try {
        db()->prepare("INSERT INTO portal_audit (client_user_id,partner_id,action,detail,ip,at) VALUES (?,?,?,?,?,?)")
            ->execute([$u ? (int)$u['id'] : null, $u ? (int)$u['partner_id'] : null,
                       $action, substr((string)$detail, 0, 300),
                       function_exists('client_ip') ? client_ip() : '', date('c')]);
    } catch (Throwable $e) { /* the trail must never break the screen */ }
}

function portal_login($email, $password) {
    $email = strtolower(trim((string)$email));
    if ($email === '') return 'Enter your e-mail address.';
    $wait = function_exists('login_locked_for') ? login_locked_for('portal:' . $email) : 0;
    if ($wait > 0) return 'Too many attempts. Try again in ' . $wait . ' minute(s).';
    $u = portal_try(fn() => ops_one("SELECT * FROM client_users WHERE LOWER(email)=? AND is_active=1", [$email]), null);
    if (!$u || (string)$u['password_hash'] === ''
            || !password_verify((string)$password, (string)$u['password_hash'])) {
        if (function_exists('login_fail')) login_fail('portal:' . $email);
        // The same sentence either way: telling a stranger that an address is
        // registered tells them who our clients are.
        return 'That e-mail address and password do not match.';
    }
    portal_start_session((int)$u['id']);
    if (function_exists('login_clear')) login_clear('portal:' . $email);
    try { db()->prepare("UPDATE client_users SET last_login_at=?, last_login_ip=? WHERE id=?")
              ->execute([date('c'), function_exists('client_ip') ? client_ip() : '', (int)$u['id']]); }
    catch (Throwable $e) {}
    portal_log('LOGIN', $email);
    return '';
}

// A fresh session id, no staff identity left in it, and a fresh CSRF token.
// Signing in as a client and being signed in as staff must never be the same
// session — hence the unset, not a merge.
function portal_start_session($clientUserId) {
    // Guarded: the rule tests drive these functions with no session open, and a
    // warning from a security primitive is the kind of noise that gets ignored
    // right up until it is the real thing.
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    unset($_SESSION['csrf'], $_SESSION['uid'], $_SESSION['pending_uid']);
    $_SESSION['cuid'] = (int)$clientUserId;
}

// ============================================================================
//  Reading — every one of these filters by the session's partner
//
//  Note what is NOT selected. No credit, no revenue, no cost, no margin, no
//  salary, no internal note. A column that is never fetched cannot be shown by
//  a template written in a hurry six months from now.
// ============================================================================
function portal_calls($limit = 200) {
    $pid = portal_partner_id();
    if (!$pid) return [];
    return portal_try(fn() => ops_all(
        "SELECT c.id, c.call_code, c.call_received_date, c.inspection_required_date, c.status,
                c.inspection_type, c.inspection_type_other, c.product_category, c.contract_number,
                c.allocated_at
         FROM calls c WHERE c.client_id = ? AND " . portal_site_sql('c') . "
         ORDER BY c.id DESC LIMIT " . max(1, (int)$limit), [$pid]));
}

function portal_call($id) {
    $pid = portal_partner_id();
    if (!$pid) return null;
    // The partner id is in the WHERE clause, not checked afterwards — an id
    // belonging to somebody else simply does not exist as far as this is
    // concerned, which is the only version of this that cannot be got wrong.
    return portal_try(fn() => ops_one(
        "SELECT id, call_code, call_received_date, inspection_required_date, status,
                inspection_type, inspection_type_other, product_category, contract_number,
                inspection_dates, allocated_at, client_id
         FROM calls WHERE id = ? AND client_id = ?", [(int)$id, $pid]), null) ?: null;
}

function portal_jobs($callId) {
    $c = portal_call($callId);
    if (!$c) return [];
    // The engineer's name, because the client is entitled to know who is coming
    // to their site. Nothing else off the inspector record — not the pay, not
    // the employment type, not whether they are ours or a sub-contractor's.
    return portal_try(fn() => ops_all(
        "SELECT j.id, j.job_code, j.scheduled_date, j.inspection_start_date, j.inspection_end_date,
                j.closed_flag, j.stage, i.name inspector_name
         FROM jobs j LEFT JOIN inspectors i ON i.id = j.inspector_id
         WHERE j.call_id = ? ORDER BY j.id", [(int)$callId]));
}

// Only reports that have actually been ISSUED. A draft is not a report, and
// showing one to a client is how a finding gets quoted back before it is final.
// "Issued" here means exactly what /verify means by it — finalized — so the two
// client-facing surfaces can never disagree about whether a report exists.
function portal_reports($limit = 200) {
    $pid = portal_partner_id();
    if (!$pid) return [];
    return portal_try(fn() => ops_all(
        "SELECT d.id, d.irn, d.title, d.type_code, d.result, d.release_status,
                d.inspection_date, d.finalized_at, d.issue_date, d.verify_code, d.rev,
                (SELECT r.decision FROM report_client_reviews r
                  WHERE r.report_doc_id = d.id AND r.rev = d.rev
                  ORDER BY r.id DESC LIMIT 1) client_decision
         FROM report_docs d
         LEFT JOIN calls c ON c.id = d.call_id
         WHERE d.client_id = ? AND d.finalized = 1 AND COALESCE(d.deleted,0) = 0
           AND (d.call_id IS NULL OR " . portal_site_sql('c') . ")
         ORDER BY d.finalized_at DESC, d.id DESC LIMIT " . max(1, (int)$limit), [$pid]));
}

function portal_report($id) {
    $pid = portal_partner_id();
    if (!$pid) return null;
    return portal_try(fn() => ops_one(
        "SELECT d.*, bp.display_name client_disp, bp.legal_name client_name,
                v.display_name vendor_disp, v.legal_name vendor_name, rt.name type_name
         FROM report_docs d
         LEFT JOIN business_partners bp ON bp.id = d.client_id
         LEFT JOIN business_partners v  ON v.id  = d.vendor_id
         LEFT JOIN report_types rt      ON rt.id = d.report_type_id
         WHERE d.id = ? AND d.client_id = ? AND d.finalized = 1 AND COALESCE(d.deleted,0) = 0",
        [(int)$id, $pid]), null) ?: null;
}

// The same PDF the office sends, built the same way — so what a client
// downloads here is what they would have been e-mailed, not a second rendering
// that could drift away from it.
function portal_report_pdf($doc) {
    $lh = function_exists('quote_letterhead') ? quote_letterhead() : ['name' => app_name()];
    $pdf = report_pdf_build($doc, idems_sections($doc['report_type_id']), idems_fields($doc['report_type_id']),
        json_decode($doc['data'] ?: '[]', true) ?: [], idems_doc_files($doc['id']), $lh,
        idems_report_signatures($doc));
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="'
         . preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$doc['irn']) . '.pdf"');
    echo $pdf;
}

// Invoices live on the deputation, and the deputation reaches the client
// through its call — so the join is what scopes this, and the client id still
// sits in the WHERE clause.
function portal_invoices() {
    $pid = portal_partner_id();
    if (!$pid) return [];
    // Amount and dates only. What the branch keeps on it is our business.
    return portal_try(fn() => ops_all(
        "SELECT j.id, j.job_code, j.invoice_number, j.invoice_date, j.invoice_due_date,
                j.invoice_amount, j.payment_received, j.payment_date, c.call_code
         FROM jobs j JOIN calls c ON c.id = j.call_id
         WHERE c.client_id = ? AND COALESCE(j.invoice_raised,0) = 1
           AND COALESCE(j.invoice_number,'') <> '' AND " . portal_site_sql('c') . "
         ORDER BY j.invoice_date DESC, j.id DESC", [$pid]));
}

// Our internal stage names are ours. ALLOCATED, REPORT_PENDING and the rest
// mean something precise to a coordinator and nothing at all to the client, who
// wants to know one thing: has somebody been sent, and is it finished. So the
// portal says that in words, and never shows the code.
function portal_call_state($c) {
    if (strtoupper((string)($c['status'] ?? '')) === 'CLOSED') return 'Completed';
    if (empty($c['allocated_at'])) return 'With our coordinators';
    return 'Engineer assigned';
}
function portal_job_state($j) {
    if (!empty($j['closed_flag']))          return 'Completed';
    if (!empty($j['inspection_end_date']))  return 'Inspection finished — report being prepared';
    if (!empty($j['inspection_start_date']))return 'Inspection under way';
    if (!empty($j['scheduled_date']))       return 'Scheduled';
    return 'Being arranged';
}

function portal_requests_mine() {
    $pid = portal_partner_id();
    if (!$pid) return [];
    return portal_try(fn() => ops_all(
        "SELECT * FROM portal_requests WHERE partner_id = ? ORDER BY id DESC", [$pid]));
}

// An invoice is overdue when its own due date has passed. Falling back to
// thirty days from the invoice date only when no due date was entered, because
// inventing a term the client never agreed to is worse than saying nothing.
function portal_invoice_overdue($i, $today = null) {
    if (!empty($i['payment_received'])) return false;
    $today = $today ?: date('Y-m-d');
    $due = (string)($i['invoice_due_date'] ?? '');
    if ($due === '') {
        $d = (string)($i['invoice_date'] ?? '');
        if ($d === '') return false;
        $due = date('Y-m-d', strtotime($d) + 30 * 86400);
    }
    return $due < $today;
}

function portal_dashboard() {
    $calls = portal_calls(500);
    $open = 0;
    foreach ($calls as $c) if (strtoupper((string)$c['status']) !== 'CLOSED') $open++;
    $reports = portal_reports(500);
    $inv = portal_invoices();
    $outstanding = 0.0; $overdue = 0;
    foreach ($inv as $i) {
        if (!empty($i['payment_received'])) continue;
        $outstanding += (float)$i['invoice_amount'];
        if (portal_invoice_overdue($i)) $overdue++;
    }
    $reqOpen = 0;
    foreach (portal_requests_mine() as $r) if ($r['status'] === 'NEW') $reqOpen++;
    return [
        'calls' => count($calls), 'open_calls' => $open,
        'reports' => count($reports),
        'recent' => array_slice($reports, 0, 5),
        'upcoming' => array_slice(array_values(array_filter($calls,
                        fn($c) => strtoupper((string)$c['status']) !== 'CLOSED')), 0, 5),
        'outstanding' => round($outstanding, 2), 'overdue' => $overdue, 'invoices' => count($inv),
        'requests_open' => $reqOpen,
    ];
}

// ============================================================================
//  Asking for the next inspection
//
//  A client may ASK. They cannot create work in our register — a coordinator
//  accepts it and raises the call, because scheduling, pricing and who goes are
//  ours to decide.
// ============================================================================
function portal_request_create($post) {
    $pid = portal_partner_id();
    $u = portal_user();
    if (!$pid || !$u) return 'Sign in again.';
    $subject = trim((string)($post['subject'] ?? ''));
    $detail  = trim((string)($post['detail'] ?? ''));
    if ($subject === '' || $detail === '') return 'Tell us what you need and where — a line each is enough.';
    db()->prepare("INSERT INTO portal_requests (partner_id,client_user_id,subject,detail,site,wanted_on,
                   contact_name,contact_phone,status,created_at) VALUES (?,?,?,?,?,?,?,?,'NEW',?)")
        ->execute([$pid, (int)$u['id'], substr($subject, 0, 255), $detail,
                   substr(trim((string)($post['site'] ?? '')), 0, 255),
                   (string)($post['wanted_on'] ?? ''),
                   substr(trim((string)($post['contact_name'] ?? '')) ?: (string)$u['name'], 0, 150),
                   substr(trim((string)($post['contact_phone'] ?? '')), 0, 60), date('c')]);
    portal_log('REQUEST', $subject);
    // Tell the desk. A request nobody sees is worse than no portal at all — the
    // client believes they have asked, and nothing happens for a week.
    $to = function_exists('coordinator_emails') ? coordinator_emails() : '';
    if ($to !== '' && function_exists('ops_mail'))
        ops_mail($to, 'Inspection request from ' . portal_client_name(),
            "A client has asked for an inspection through the portal.\n\n"
            . "From: " . $u['name'] . " (" . $u['email'] . ")\n"
            . "Subject: " . $subject . "\n"
            . "Site: " . ((string)($post['site'] ?? '') ?: '-') . "\n"
            . "Wanted by: " . ((string)($post['wanted_on'] ?? '') ?: '-') . "\n\n"
            . $detail
            . "\n\nOpen the portal register to accept or decline it.\n\n" . app_name(),
            '', 'portal');
    return '';
}

// Turn an accepted request into an actual inspection call, and link the two so
// the client's ask and the resulting work are the same thread. Deliberately
// minimal: it carries across only what the client gave us — who they are, what
// and where, and when they wanted it — folded into the call's notes, and leaves
// it OPEN so a coordinator sets scope, price, the executing office and who goes,
// which are ours to decide. Idempotent: a request already linked to a call is
// never converted twice. Returns [call_id, error].
function portal_request_to_call($r) {
    if (!$r) return [0, 'That request no longer exists.'];
    if (!empty($r['call_id'])) return [(int)$r['call_id'], '']; // already raised — reuse it
    if (!function_exists('ops_next_code')) return [0, 'The call register is not available on this install.'];

    // Fold everything the client told us into a readable brief on the call.
    $lines = [];
    if (trim((string)($r['subject'] ?? '')) !== '') $lines[] = trim((string)$r['subject']);
    if (trim((string)($r['detail'] ?? '')) !== '')  $lines[] = trim((string)$r['detail']);
    if (trim((string)($r['site'] ?? '')) !== '')    $lines[] = 'Site: ' . trim((string)$r['site']);
    $contact = trim(($r['contact_name'] ?? '') . ' ' . ($r['contact_phone'] ?? ''));
    if ($contact !== '') $lines[] = 'Client contact: ' . $contact;
    $note = "Raised from a portal request.\n" . implode("\n", $lines);

    $code = ops_next_code('calls', 'call_code', 'CALL');
    try {
        db()->prepare("INSERT INTO calls (call_code, client_id, call_received_date, inspection_required_date,
                       notes, status, created_by, created_at) VALUES (?,?,?,?,?,'OPEN',?,?)")
            ->execute([$code, (int)$r['partner_id'], date('Y-m-d'),
                       (string)($r['wanted_on'] ?? ''), $note,
                       function_exists('user_name') ? user_name(current_user()) : 'Portal', date('c')]);
    } catch (Throwable $e) { return [0, 'The call could not be created: ' . $e->getMessage()]; }
    $callId = (int)db()->lastInsertId();
    if (function_exists('idems_log')) try { idems_log('call', $callId, 'FROM_PORTAL', ['reason' => 'request #' . $r['id']]); } catch (Throwable $e) {}
    return [$callId, ''];
}

// ============================================================================
//  Staff side — inviting a client, and answering requests
// ============================================================================
function portal_users_for($partnerId) {
    return portal_try(fn() => ops_all(
        "SELECT * FROM client_users WHERE partner_id=? ORDER BY name, id", [(int)$partnerId]));
}
function portal_users_all() {
    return portal_try(fn() => ops_all(
        "SELECT cu.*, COALESCE(p.display_name, p.legal_name) partner_name,
                pc.name contact_name, pc.designation contact_desig
         FROM client_users cu
         LEFT JOIN business_partners p ON p.id = cu.partner_id
         LEFT JOIN partner_contacts pc ON pc.id = cu.contact_id
         ORDER BY partner_name, cu.name"));
}

// Creates the account and returns the one-time link. The password is never
// chosen by us and never e-mailed — the invitee sets their own, so nobody here
// has ever known it.
function portal_invite($partnerId, $email, $name, $contactId = 0) {
    $partnerId = (int)$partnerId; $contactId = (int)$contactId;
    // If a saved contact was chosen, it is the source of truth for who this is —
    // its company, name and address win, so the portal account and the contact
    // on the client record are the same person, not two versions of them.
    if ($contactId > 0) {
        $c = portal_try(fn() => ops_one("SELECT * FROM partner_contacts WHERE id=?", [$contactId]), null);
        if (!$c) return ['err' => 'That contact no longer exists.'];
        $partnerId = (int)$c['partner_id'];
        if (trim((string)($c['email'] ?? '')) === '') return ['err' => 'That contact has no e-mail on the client record — add one first, or type the address.'];
        $email = strtolower(trim((string)$c['email']));
        if (trim((string)$name) === '') $name = (string)$c['name'];
    } else {
        $email = strtolower(trim((string)$email));
    }
    if (!$partnerId) return ['err' => 'Choose the client company.'];
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['err' => 'A valid e-mail address is needed.'];
    $exists = portal_try(fn() => ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(email)=?", [$email]), 0);
    if ((int)$exists > 0) return ['err' => 'That address already has portal access.'];
    // Even a typed address is linked back to the client record when it matches a
    // saved contact, so the two lists never drift apart.
    if ($contactId === 0) {
        $match = portal_try(fn() => ops_one("SELECT id FROM partner_contacts WHERE partner_id=? AND LOWER(email)=? ORDER BY is_primary DESC, id LIMIT 1", [$partnerId, $email]), null);
        if ($match) $contactId = (int)$match['id'];
    }
    $token = bin2hex(random_bytes(24));
    db()->prepare("INSERT INTO client_users (partner_id,contact_id,email,name,password_hash,is_active,must_change,
                   invite_token,invite_expires,created_by,created_at) VALUES (?,?,?,?,'',1,1,?,?,?,?)")
        ->execute([$partnerId, $contactId ?: null, $email, substr(trim((string)$name), 0, 150), $token,
                   date('c', time() + 7 * 86400), user_name(current_user()), date('c')]);
    return ['id' => (int)db()->lastInsertId(), 'token' => $token,
            'link' => portal_base_url() . '/portal/accept?t=' . $token];
}

function portal_base_url() {
    $base = rtrim((string)setting_get('public_base_url', ''), '/');
    if ($base !== '') return $base;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// Why a token cannot be used — '' when it can. Split out from portal_accept()
// so the page can say so on the way IN. An invitation that has already been
// used, or has expired, used to show a perfectly good form that only refused
// once somebody had chosen a password and typed it twice.
function portal_invite_problem($token) {
    $token = trim((string)$token);
    $u = $token === '' ? null : portal_try(fn() => ops_one(
        "SELECT * FROM client_users WHERE invite_token=? AND invite_token<>'' AND is_active=1", [$token]), null);
    if (!$u) return 'That invitation is not valid — it may already have been used. '
                  . 'Ask your contact at ' . app_name() . ' to send a new one.';
    if ($u['invite_expires'] !== '' && $u['invite_expires'] < date('c'))
        return 'That invitation has expired. Ask your contact for a new one.';
    return '';
}

function portal_accept($token, $password, $confirm) {
    $why = portal_invite_problem($token);
    if ($why !== '') return $why;
    $u = ops_one("SELECT * FROM client_users WHERE invite_token=? AND is_active=1", [trim((string)$token)]);
    if ((string)$password !== (string)$confirm) return 'The two passwords do not match.';
    $why = function_exists('password_problem_text') ? password_problem_text((string)$password, (string)$u['email'], (string)$u['name']) : '';
    if ($why !== '') return $why;
    db()->prepare("UPDATE client_users SET password_hash=?, must_change=0, invite_token='', invite_expires='' WHERE id=?")
        ->execute([password_hash((string)$password, PASSWORD_DEFAULT), (int)$u['id']]);
    portal_start_session((int)$u['id']);
    portal_log('ACCEPTED_INVITE', $u['email']);
    // The one point where the data principal themselves acts — they set their
    // own password and take up the invitation. Record it in the consent
    // register so the DPDP lawful-basis question can be answered from the system
    // rather than from memory.
    if (function_exists('consent_record')) {
        consent_record([
            'subject_kind' => 'PORTAL',
            'subject_id'   => (int)$u['id'],
            'subject_name' => (string)($u['name'] ?: $u['email']),
            'purpose'      => 'Client portal access to their own ' . (function_exists('Tlp') ? Tlp('report') : 'reports'),
            'basis'        => 'CONSENT',
            'given_at'     => date('c'),
            'note'         => 'Recorded automatically when the invitee accepted the invitation and set their own password.',
            'recorded_by'  => 'The person themselves (portal invitation accepted)',
        ]);
    }
    return '';
}

function portal_requests_all($status = '') {
    $w = $status !== '' ? "WHERE r.status = ?" : '';
    $a = $status !== '' ? [$status] : [];
    return portal_try(fn() => ops_all(
        "SELECT r.*, COALESCE(p.display_name, p.legal_name) partner_name
         FROM portal_requests r LEFT JOIN business_partners p ON p.id = r.partner_id
         $w ORDER BY r.id DESC", $a));
}

function portal_can_manage() { return is_master() || can('mod.portal.edit'); }

// ============================================================================
//  Screens — the client's side
//
//  These do NOT go through view(). view() draws the staff layout: the sidebar,
//  the name of whoever is signed in, the module menu. A client must not be
//  within reach of any of that, so the portal renders its own shell — and
//  stamps its own CSRF tokens, which is what view() was doing for the rest of
//  the app.
// ============================================================================
// $nav is false for the pages somebody who is not signed in reaches — there is
// nothing to navigate to yet, and a menu on a sign-in page only advertises what
// is behind it.
function portal_view($name, $vars = [], $nav = true) {
    extract($vars);
    $u = portal_user();
    ob_start();
    require __DIR__ . '/../views/portal/top.php';
    require __DIR__ . "/../views/portal/$name.php";
    require __DIR__ . '/../views/portal/bottom.php';
    echo csrf_stamp_forms((string)ob_get_clean());
}

// The portal is dispatched BEFORE require_login(), which means it is also
// before the application's own CSRF gate. So it carries its own — same token,
// same check, just applied here because nothing downstream will do it.
function portal_csrf_or_die($method) {
    if ($method !== 'POST') return;
    if (csrf_ok($_POST['_csrf'] ?? '')) return;
    http_response_code(400);
    $err = 'That form was not sent from this page, or you had been away long enough for it to expire. '
         . 'Open the page again and retype it — nothing was changed.';
    portal_view('message', ['title' => 'Please try that again', 'body' => $err], (bool)portal_user());
    exit;
}

function portal_route($route, $method) {
    portal_csrf_or_die($method);

    // Everything before the gate: the pages somebody who is NOT signed in needs.
    if ($route === 'portal/login') {
        if (!portal_enabled()) portal_off();
        if (portal_user()) redirect('/portal');
        $err = '';
        if ($method === 'POST') {
            $err = portal_login($_POST['email'] ?? '', $_POST['password'] ?? '');
            if ($err === '') redirect('/portal');
        }
        portal_view('login', ['err' => $err], false);
        exit;
    }
    if ($route === 'portal/accept') {
        if (!portal_enabled()) portal_off();
        $token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
        // Checked on the way in as well as on the way out, so a used or expired
        // link says so straight away rather than after somebody has chosen a
        // password and typed it twice.
        $err = portal_invite_problem($token);
        $dead = $err !== '';
        if (!$dead && $method === 'POST') {
            $err = portal_accept($token, $_POST['password'] ?? '', $_POST['confirm'] ?? '');
            if ($err === '') redirect('/portal');
        }
        portal_view('accept', ['err' => $err, 'token' => $token, 'dead' => $dead], false);
        exit;
    }
    if ($route === 'portal/logout') {
        portal_log('LOGOUT');
        unset($_SESSION['cuid']);
        session_destroy();
        redirect('/portal/login');
    }

    portal_require();
    $u = portal_user();

    switch ($route) {
        case 'portal':
            portal_log('DASHBOARD');
            portal_view('dashboard', ['d' => portal_dashboard()]);
            exit;

        case 'portal/calls':
            portal_need('calls', Tlp('call'));
            portal_view('calls', ['rows' => portal_calls()]);
            exit;

        case 'portal/call':
            portal_need('calls', Tlp('call'));
            $c = portal_call((int)($_GET['id'] ?? 0));
            if (!$c) { http_response_code(404); portal_view('notfound'); exit; }
            portal_view('call', ['c' => $c, 'jobs' => portal_jobs((int)$c['id'])]);
            exit;

        case 'portal/reports':
            portal_need('reports', 'reports');
            portal_view('reports', ['rows' => portal_reports(), 'err' => '']);
            exit;

        // Phase 7 (PDSO): the client sees their deputed personnel, attendance
        // periods and — where permitted — approves the man-day quantity that
        // supports our billing. Read helpers filter to this client's partner id.
        case 'portal/deputations':
            portal_need('deputation', 'deputations');
            if (!function_exists('pdso_portal_deputations')) { portal_view('message', ['title' => 'Deputations', 'body' => 'Deputation is not enabled.']); exit; }
            portal_view('deputations', [
                'rows'      => pdso_portal_deputations(portal_partner_id()),
                'approvals' => pdso_portal_approvals(portal_partner_id()),
                'canApprove'=> pcan('deputation.approve'),
                'statuses'  => function_exists('pdso_statuses') ? pdso_statuses() : [],
            ]);
            exit;

        case 'portal/dep-approve':
            portal_need('deputation.approve', 'approving attendance');
            $ap = pdso_portal_approval((int)($_POST['id'] ?? $_GET['id'] ?? 0), portal_partner_id());
            if (!$ap) { http_response_code(404); portal_view('notfound'); exit; }
            if ($method === 'POST' && in_array($ap['status'], ['SUBMITTED','CLIENT_REVIEW'], true)) {
                $decision = ($_POST['decision'] ?? '') === 'APPROVED' ? 'APPROVED' : 'RETURNED';
                $rep = trim((string)($_POST['client_rep'] ?? '')) !== '' ? (string)$_POST['client_rep'] : (string)(portal_user()['name'] ?? 'Client');
                pdso_att_approval_set_status((int)$ap['id'], $decision, $rep, (string)($_POST['comments'] ?? ''));
                portal_log('DEP_APPROVAL', $ap['job_id'] . ':' . $decision);
                $_SESSION['portal_flash'] = $decision === 'APPROVED'
                    ? 'Thank you — the attendance period is approved.'
                    : 'Recorded — the period has been returned to our team with your note.';
            }
            redirect('/portal/deputations');

        // The client's answer to a report we issued. portal_report() is what
        // proves the report belongs to them — rcr_decide() trusts that and does
        // not re-check, so nothing else may call it with an unchecked row.
        case 'portal/report-decision':
            portal_need('reports.decide', 'accepting or rejecting a report');
            $d = portal_report((int)($_POST['id'] ?? $_GET['id'] ?? 0));
            if (!$d) { http_response_code(404); portal_view('notfound'); exit; }
            $err = '';
            if ($method === 'POST') {
                $err = rcr_decide($d, $_POST, portal_user());
                if ($err === '') {
                    $_SESSION['portal_flash'] = ($_POST['decision'] ?? '') === 'ACCEPTED'
                        ? 'Thank you — your acceptance of ' . $d['irn'] . ' is recorded.'
                        : 'Recorded. ' . $d['irn'] . ' is with our team, and they have been told what needs to change.';
                    redirect('/portal/reports');
                }
            }
            portal_view('report_decide', ['d' => $d, 'err' => $err,
                                          'current' => rcr_current($d), 'history' => rcr_history((int)$d['id'])]);
            exit;

        case 'portal/report':
            portal_need('reports', 'reports');
            $d = portal_report((int)($_GET['id'] ?? 0));
            if (!$d) { http_response_code(404); portal_view('notfound'); exit; }
            portal_log('REPORT_OPENED', $d['irn']);
            portal_report_pdf($d);
            exit;

        // ISO/IEC 17020 7.5 and 7.6. The register and the published policy
        // both existed; the client had no way to actually lodge one.
        case 'portal/complaints':
            portal_need('complaint', 'complaints and appeals');
            portal_view('complaints', ['rows' => pcmp_mine()]);
            exit;

        case 'portal/complaint-new':
            portal_need('complaint', 'raising a complaint');
            $err = '';
            if ($method === 'POST') {
                $err = pcmp_create($_POST, portal_user());
                if ($err === '') {
                    $_SESSION['portal_flash'] = 'Thank you — it is recorded and our office has been told. '
                        . 'You will be written to when it has been looked into.';
                    redirect('/portal/complaints');
                }
            }
            portal_view('complaint_new', [
                'err' => $err,
                'appealable' => pcmp_appealable(),
                'jobs' => portal_try(fn() => ops_all(
                    "SELECT j.id, j.job_code, j.scheduled_date FROM jobs j
                     LEFT JOIN calls c ON c.id = j.call_id
                     WHERE c.client_id = ? ORDER BY j.id DESC LIMIT 100", [portal_partner_id()])),
                'prefill' => $_GET,
            ]);
            exit;

        case 'portal/invoices':
            portal_need('invoices', 'invoices');
            portal_view('invoices', ['rows' => portal_invoices()]);
            exit;

        case 'portal/request':
            $err = '';
            if ($method === 'POST') {
                $err = portal_request_create($_POST);
                if ($err === '') {
                    $_SESSION['portal_flash'] = 'Thank you — your request is with our coordinators.';
                    redirect('/portal/request');
                }
            }
            portal_view('request', ['rows' => portal_requests_mine(), 'err' => $err]);
            exit;

        // Phase 10 (CVP) Slice 3: the client sees nonconformities raised to them
        // (marked client-visible) and responds — the same engine loop the vendor
        // portal uses. cvp_issue*/cvp_issues_for are the shared, gated engine.
        case 'portal/issues':
            if (!function_exists('cvp_issues_for')) { http_response_code(404); portal_view('notfound'); exit; }
            portal_need('issues', 'nonconformities');
            portal_view('issues', ['rows' => cvp_issues_for('CLIENT', portal_partner_id())]);
            exit;

        case 'portal/issue':
            if (!function_exists('cvp_issue')) { http_response_code(404); portal_view('notfound'); exit; }
            portal_need('issues', 'nonconformities');
            $n = cvp_issue('CLIENT', portal_partner_id(), (int)($_GET['id'] ?? $_POST['id'] ?? 0));
            if (!$n) { http_response_code(404); portal_view('notfound'); exit; }
            $err = '';
            if ($method === 'POST') {
                $err = cvp_issue_respond('CLIENT', portal_partner_id(), (int)$n['id'], $_POST, (string)(portal_user()['name'] ?? ''));
                if ($err === '') {
                    portal_log('ISSUE_RESPONSE', (string)$n['ref']);
                    $_SESSION['portal_flash'] = 'Thank you — your response to ' . $n['ref'] . ' is recorded and with our team.';
                    redirect('/portal/issues');
                }
            }
            portal_view('issue', ['n' => $n, 'err' => $err,
                'responses' => cvp_issue_responses('CLIENT', (int)$n['id']), 'kinds' => NCDCA_RESPONSE_KINDS]);
            exit;

        case 'portal/password':
            $err = ''; $done = false;
            if ($method === 'POST') {
                $new = (string)($_POST['password'] ?? '');
                if ($new !== (string)($_POST['confirm'] ?? '')) $err = 'The two passwords do not match.';
                else {
                    $why = function_exists('password_problem_text')
                         ? password_problem_text($new, (string)$u['email'], (string)$u['name']) : '';
                    if ($why !== '') $err = $why;
                    else {
                        db()->prepare("UPDATE client_users SET password_hash=?, must_change=0 WHERE id=?")
                            ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$u['id']]);
                        portal_log('PASSWORD_CHANGED');
                        $done = true;
                        $u = portal_user_reload();   // must_change is 0 now; the cache still says 1
                    }
                }
            }
            portal_view('password', ['err' => $err, 'done' => $done]);
            exit;
    }

    http_response_code(404);
    portal_view('notfound');
    exit;
}

// portal_user() caches for the request, which is right everywhere except
// immediately after we have changed the row ourselves.
function portal_user_reload() {
    $id = (int)($_SESSION['cuid'] ?? 0);
    if (!$id) return null;
    return portal_try(fn() => ops_one(
        "SELECT cu.*, p.legal_name, p.display_name
         FROM client_users cu JOIN business_partners p ON p.id = cu.partner_id
         WHERE cu.id = ? AND cu.is_active = 1", [$id]), null) ?: null;
}

// ============================================================================
//  Screens — the staff side
// ============================================================================
function ops_portal_admin($route, $method) {
    ops_require(portal_can_manage(), 'Only somebody who manages clients can open the portal register.');

    if ($route === 'portal-users') {
        $invite = null;
        if ($method === 'POST') {
            $r = portal_invite((int)($_POST['partner_id'] ?? 0), $_POST['email'] ?? '', $_POST['name'] ?? '', (int)($_POST['contact_id'] ?? 0));
            if (!empty($r['err'])) flash($r['err'], 'error');
            else {
                $invite = $r;
                flash('Invitation created. Send them the link below — we never choose or e-mail a password.');
            }
        }
        view('ops/portal_users', [
            'rows' => portal_users_all(), 'clients' => clients_list(),
            'invite' => $invite, 'enabled' => portal_enabled(),
            'requests' => portal_requests_all(), 'base' => portal_base_url(),
            'contactsByPartner' => portal_contacts_by_partner(),
        ]);
        return true;
    }

    if ($route === 'portal-user-perms') {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $u = portal_try(fn() => ops_one(
            "SELECT cu.*, p.display_name partner_name, p.legal_name
             FROM client_users cu JOIN business_partners p ON p.id = cu.partner_id
             WHERE cu.id = ?", [$id]), null);
        if (!$u) { http_response_code(404); view('notfound'); return true; }
        if ($method === 'POST') {
            portal_perms_save($id, $_POST);
            flash('Access saved for ' . ($u['name'] ?: $u['email']) . '.');
            redirect('/portal-users');
        }
        view('ops/portal_user_perms', ['u' => $u, 'sites' => portal_sites_for((int)$u['partner_id'])]);
        return true;
    }

    if ($route === 'portal-user-toggle' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $u = portal_try(fn() => ops_one("SELECT * FROM client_users WHERE id=?", [$id]), null);
        if ($u) {
            db()->prepare("UPDATE client_users SET is_active=? WHERE id=?")
                ->execute([empty($u['is_active']) ? 1 : 0, $id]);
            flash(empty($u['is_active'])
                ? 'Access restored for ' . $u['email'] . '.'
                : 'Access withdrawn from ' . $u['email'] . '. They cannot sign in again.');
        }
        redirect('/portal-users');
    }

    if ($route === 'portal-user-reinvite' && $method === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $u = portal_try(fn() => ops_one("SELECT * FROM client_users WHERE id=?", [$id]), null);
        if ($u) {
            $token = bin2hex(random_bytes(24));
            db()->prepare("UPDATE client_users SET invite_token=?, invite_expires=?, password_hash='', must_change=1 WHERE id=?")
                ->execute([$token, date('c', time() + 7 * 86400), $id]);
            $_SESSION['portal_invite_link'] = portal_base_url() . '/portal/accept?t=' . $token;
            flash('A new invitation link has been made for ' . $u['email']
                . '. Their old password no longer works.');
        }
        redirect('/portal-users');
    }

    if ($route === 'portal-settings' && $method === 'POST') {
        ops_require(is_master() || is_admin_level(), 'Only an administrator can switch the portal on.');
        setting_set('portal_enabled', !empty($_POST['portal_enabled']) ? '1' : '0');
        flash(portal_enabled()
            ? 'The client portal is on. Nobody can reach it until you invite them.'
            : 'The client portal is off. Every portal address now answers "not found".');
        redirect('/portal-users');
    }

    if ($route === 'portal-request' && $method === 'POST') {
        $r = portal_try(fn() => ops_one("SELECT * FROM portal_requests WHERE id=?", [(int)($_POST['id'] ?? 0)]), null);
        $to = (string)($_POST['status'] ?? '');
        if ($r && isset(PORTAL_REQ_STATUS[$to]) && $to !== 'NEW') {
            $reply = trim((string)($_POST['reply'] ?? ''));
            // Declining without a word is the thing that makes a client stop
            // using the portal and go back to telephoning.
            if ($to === 'DECLINED' && $reply === '') {
                flash('Say why. A request declined in silence is why clients stop using a portal.', 'error');
                redirect('/portal-users');
            }
            // "Arranged" now raises the actual call instead of merely recording a
            // status — the coordinator lands on the call ready to complete scope,
            // price and who goes. Raising work needs the calls permission; without
            // it, fall back to Accepted so nothing is lost.
            if ($to === 'CONVERTED') {
                $mayRaise = is_master() || (function_exists('can') && can('mod.calls.view'));
                if (!$mayRaise) {
                    flash('Recorded as accepted — ask a coordinator to raise the ' . Tl('call') . '.', 'error');
                    db()->prepare("UPDATE portal_requests SET status='ACCEPTED', reply=?, handled_by=?, handled_at=? WHERE id=?")
                        ->execute([substr($reply, 0, 1000), user_name(current_user()), date('c'), (int)$r['id']]);
                    redirect('/portal-users');
                }
                [$callId, $err] = portal_request_to_call($r);
                if ($err) { flash($err, 'error'); redirect('/portal-users'); }
                db()->prepare("UPDATE portal_requests SET status='CONVERTED', call_id=?, reply=?, handled_by=?, handled_at=? WHERE id=?")
                    ->execute([$callId, substr($reply, 0, 1000), user_name(current_user()), date('c'), (int)$r['id']]);
                flash('Request accepted and ' . Tl('call') . ' raised — complete the scheduling below.');
                redirect('/call-edit?id=' . $callId);
            }
            db()->prepare("UPDATE portal_requests SET status=?, reply=?, handled_by=?, handled_at=? WHERE id=?")
                ->execute([$to, substr($reply, 0, 1000), user_name(current_user()), date('c'), (int)$r['id']]);
            flash('Recorded: ' . PORTAL_REQ_STATUS[$to] . '.');
        }
        redirect('/portal-users');
    }

    redirect('/portal-users');
    return true;
}

// ============================================================================
//  What a particular person at the client may see and do
//
//  Until now client_users had is_active and nothing else: every named contact
//  at a company saw the same screens. That is wrong in both directions. The
//  purchasing clerk who chases invoices has no business accepting an
//  inspection report on the company's behalf, and the plant QA engineer who
//  should be accepting reports often must not see what anything cost.
//
//  Two axes, because clients ask for two different things:
//
//   1. WHAT they can do — a small set of permissions, not a fixed role. Roles
//      differ too much between a two-person fabricator and a refinery to be
//      hard-coded, so the presets below just tick boxes and every one of them
//      can be adjusted afterwards.
//   2. WHICH SITES it applies to — a plant manager at one works should not see
//      another plant's inspections. Blank means every site, which is what a
//      head-office contact needs.
//
//  Backwards compatibility matters here: an existing client user has no
//  permissions stored, and must NOT silently lose access on upgrade. A blank
//  permission list therefore means "everything", exactly as before, and the
//  admin screen says so rather than showing a row of empty boxes.
// ============================================================================

const PORTAL_PERMS = [
    'calls'              => 'See work orders and visits',
    'reports'            => 'See issued reports and download them',
    'reports.decide'     => 'Accept or reject a report on the company’s behalf',
    'invoices'           => 'See invoices and what is outstanding',
    'request'            => 'Ask for a new job',
    'complaint'          => 'Raise a complaint or an appeal',
    'deputation'         => 'See deputed personnel, attendance and site reports',
    'deputation.approve' => 'Approve or return attendance / timesheet periods',
    'issues'             => 'See nonconformities raised to them and respond',
];

// A constant cannot call T(), so the labels that name a business noun are
// re-worded here on the way to the screen. What is in the constant is only the
// fallback, and is kept neutral for that reason.
function portal_perm_labels() {
    $m = PORTAL_PERMS;
    $m['calls']          = 'See ' . Tlp('call') . ' and visits';
    $m['reports']        = 'See issued ' . Tlp('report') . ' and download them';
    $m['reports.decide'] = 'Accept or reject a ' . Tl('report') . ' on the company’s behalf';
    $m['request']        = 'Ask for a new ' . Tl('call');
    return $m;
}

// Starting points, not a cage — every one is editable after it is applied.
const PORTAL_PRESETS = [
    'FULL'       => ['label' => 'Full access — a main contact',
                     'perms' => ['calls','reports','reports.decide','invoices','request','complaint','deputation','deputation.approve']],
    'QUALITY'    => ['label' => 'Quality / technical — accepts reports, sees no money',
                     'perms' => ['calls','reports','reports.decide','request','complaint','deputation']],
    'COMMERCIAL' => ['label' => 'Commercial / purchasing — sees reports and invoices, does not accept reports',
                     'perms' => ['calls','reports','invoices','request','complaint','deputation','deputation.approve']],
    'ACCOUNTS'   => ['label' => 'Accounts — invoices only',
                     'perms' => ['invoices','complaint']],
    'READONLY'   => ['label' => 'Read only — sees everything, changes nothing',
                     'perms' => ['calls','reports','invoices','deputation']],
];

// The permissions this signed-in client user holds. A blank stored value means
// everything, so nobody invited before this existed loses access on upgrade.
function portal_perms() {
    $u = portal_user();
    if (!$u) return [];
    $raw = trim((string)($u['perms'] ?? ''));
    if ($raw === '') return array_keys(PORTAL_PERMS);
    return array_values(array_intersect(array_map('trim', explode(',', $raw)), array_keys(PORTAL_PERMS)));
}

function pcan($key) { return in_array($key, portal_perms(), true); }

// Refuse rather than hide: hiding a link but leaving the address reachable is
// the bug this whole file exists to avoid. (portal_require() above is the
// sign-in gate; this is the permission gate that runs after it.)
function portal_need($key, $what = 'that') {
    if (pcan($key)) return;
    $_SESSION['portal_flash'] = 'Your access here does not include ' . $what . '. '
        . 'Ask your main contact at ' . portal_client_name() . ' to have it added.';
    redirect('/portal');
}

// Which sites this person is limited to. Empty = all of them.
function portal_site_ids() {
    $u = portal_user();
    if (!$u) return [];
    $raw = trim((string)($u['site_ids'] ?? ''));
    if ($raw === '') return [];
    return array_values(array_filter(array_map('intval', explode(',', $raw))));
}

// SQL fragment limiting a query on `calls c` to this person's sites. Returns
// '1=1' when they are not limited, so callers do not need two code paths.
function portal_site_sql($alias = 'c') {
    $ids = portal_site_ids();
    if (!$ids) return '1=1';
    // A call with no site recorded stays visible: an unlocated call must not
    // vanish for everybody who has any site restriction at all.
    return "($alias.site_address_id IS NULL OR $alias.site_address_id IN (" . implode(',', $ids) . '))';
}

// The sites we could offer, for the admin screen.
function portal_sites_for($partnerId) {
    return portal_try(fn() => ops_all(
        "SELECT id, label, line1, city FROM partner_addresses
         WHERE partner_id = ? ORDER BY is_primary DESC, id", [(int)$partnerId]));
}

function portal_perms_save($clientUserId, array $b) {
    $keys = [];
    foreach (array_keys(PORTAL_PERMS) as $k) if (!empty($b['p_' . str_replace('.', '_', $k)])) $keys[] = $k;
    $sites = array_values(array_filter(array_map('intval', (array)($b['site_ids'] ?? []))));
    db()->prepare("UPDATE client_users SET perms=?, site_ids=?, role_preset=? WHERE id=?")
        ->execute([implode(',', $keys), implode(',', $sites),
                   substr(trim((string)($b['role_preset'] ?? '')), 0, 30), (int)$clientUserId]);
}
