<?php
// ============================================================================
//  Public workspace signup — a NEW operations company applies for its own
//  workspace, and it lands as PENDING for the Super-Admin to approve.
//
//  This is the operations-side mirror of Connect B1 (an organisation applies
//  for itself and waits for approval). The marketplace lets freelancers and
//  hiring companies self-register instantly; an OPERATIONS workspace is heavier
//  — it is a whole database + subdomain per company — so it is gated: a company
//  registers here, the Super-Admin sees the request on the Workspaces panel and
//  clicks Approve, and only then is the workspace provisioned.
//
//  Approve does the right thing automatically:
//    • cPanel API configured  → provisions the database + subdomain at once
//      (PROVISIONED), reusing cpanel_provision_workspace() + tenant_add().
//    • otherwise               → marks the request APPROVED and hands the
//      operator the two-click cPanel checklist to finish on the Workspaces
//      panel. Approve never fails or gets stuck.
//
//  Nothing here is shared between workspaces — provisioning only ever ADDS a
//  workspace; it never touches or deletes an existing one.
// ============================================================================

/** Additive migration — the request inbox. Wired into boot() run_schema and
 *  required in index.php (meta-test enforces both). */
function tenant_signup_migrate($pdo = null) {
    $pdo = $pdo ?: db();
    $pk  = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    // Never let this one table's creation abort the whole boot chain: a failure
    // here must not stop the ~50 migrations that follow (that is what left
    // candidate_docs and other later tables uncreated). Same guard every other
    // migration uses.
    try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_requests (
        id $pk,
        company        VARCHAR(150) NOT NULL DEFAULT '',
        contact_name   VARCHAR(120) NOT NULL DEFAULT '',
        email          VARCHAR(160) NOT NULL DEFAULT '',
        phone          VARCHAR(40)  NOT NULL DEFAULT '',
        sub            VARCHAR(48)  NOT NULL DEFAULT '',
        note           VARCHAR(500) NOT NULL DEFAULT '',
        status         VARCHAR(16)  NOT NULL DEFAULT 'PENDING',
        tenant_sub     VARCHAR(48)  NOT NULL DEFAULT '',
        admin_note     VARCHAR(500) NOT NULL DEFAULT '',
        decided_by     VARCHAR(120) NOT NULL DEFAULT '',
        decided_at     VARCHAR(40)  NOT NULL DEFAULT '',
        created_at     VARCHAR(40)  NOT NULL DEFAULT ''
    )");
    } catch (Throwable $e) { /* never block the rest of the boot chain */ }
    // Where a request came from, so one connection cannot flood the inbox.
    if (function_exists('ensure_column')) { try { ensure_column('tenant_requests', 'ip', "VARCHAR(60) DEFAULT ''"); } catch (Throwable $e) {} }
}

/** The public page is OFF by default — the operator turns it on from the
 *  Workspaces panel once they are ready to take online registrations. */
function tenant_signup_enabled() {
    return function_exists('setting_get') && (string)setting_get('workspace_signup_enabled', '0') === '1';
}
function tenant_signup_set_enabled($on) {
    if (function_exists('setting_set')) setting_set('workspace_signup_enabled', $on ? '1' : '0');
}

// HOW A NEW COMPANY GETS A WORKSPACE — one answer, not two switches.
//
// Two settings decide it: self sign-up (selfserve_signup_enabled, /start) and
// online registration (workspace_signup_enabled, /get-started). Shown as two
// switches on two screens, "Online registration: OFF" still let anyone open a
// workspace at /start, because self sign-up was on (owner, test book ONB-001:
// "the switch is off but still I can generate the account from /start").
// So the owner now makes ONE choice; both settings follow it.
const WORKSPACE_ENTRY_MODES = [
    'closed'  => 'Only you add companies (Companies → ＋ Add a company)',
    'apply'   => 'Companies apply at /get-started, and you approve each one here',
    'instant' => 'Companies sign themselves up at /start — straight in, on a free trial',
];
function workspace_entry_mode() {
    if (function_exists('selfserve_enabled') && selfserve_enabled()) return 'instant';
    return tenant_signup_enabled() ? 'apply' : 'closed';
}
function workspace_entry_set($mode) {
    if (!isset(WORKSPACE_ENTRY_MODES[$mode]) || !function_exists('setting_set')) return false;
    setting_set('selfserve_signup_enabled', $mode === 'instant' ? '1' : '0');
    tenant_signup_set_enabled($mode !== 'closed');   // with instant on, /get-started forwards to /start
    return true;
}

/** The states a request moves through. */
function tenant_request_statuses() {
    return ['PENDING' => 'Pending review', 'APPROVED' => 'Approved — awaiting setup',
            'PROVISIONED' => 'Workspace created', 'REJECTED' => 'Declined'];
}

/**
 * A company applies for a workspace. Returns [ok, message, id].
 * Validates the essentials and the preferred workspace name; a blank/invalid
 * name is normalised from the company name so the operator can still act on it.
 */
function tenant_signup_submit(array $in) {
    tenant_signup_migrate();
    $company = trim((string)($in['company'] ?? ''));
    $person  = trim((string)($in['contact_name'] ?? ''));
    $email   = strtolower(trim((string)($in['email'] ?? '')));
    $phone   = trim((string)($in['phone'] ?? ''));
    $note    = trim((string)($in['note'] ?? ''));
    $sub     = strtolower(trim((string)($in['sub'] ?? '')));

    if ($company === '')                              return [false, 'Please enter your company name.', 0];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))   return [false, 'Enter a valid work e-mail address.', 0];
    // Derive a workspace name from the company if the caller left it blank or invalid.
    if ($sub === '' || !(function_exists('tenant_valid_sub') && tenant_valid_sub($sub))) {
        $sub = preg_replace('/[^a-z0-9]+/', '', strtolower($company));
        $sub = substr($sub, 0, 40);
    }
    if ($sub === '') $sub = 'workspace';

    // Don't take a second live request for the same e-mail while one is open.
    $dupe = (int)ops_val("SELECT COUNT(*) FROM tenant_requests WHERE LOWER(email)=? AND status IN ('PENDING','APPROVED')", [$email]);
    if ($dupe > 0) return [false, 'We already have a request in progress for that e-mail — we will be in touch shortly.', 0];
    // One connection may not flood the inbox (a technical limit, not a business rule).
    $ip = function_exists('client_ip') ? client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $recent = (int)ops_val("SELECT COUNT(*) FROM tenant_requests WHERE ip=? AND created_at>=?", [$ip, date('c', time() - 3600)]);
    if ($recent >= 5) return [false, 'Too many requests from this connection. Please try again in an hour.', 0];

    db()->prepare("INSERT INTO tenant_requests (company,contact_name,email,phone,sub,note,status,ip,created_at)
                   VALUES (?,?,?,?,?,?, 'PENDING', ?, ?)")
        ->execute([mb_substr($company,0,150), mb_substr($person,0,120), mb_substr($email,0,160),
                   mb_substr($phone,0,40), mb_substr($sub,0,48), mb_substr($note,0,500), $ip, date('c')]);
    $id = (int)db()->lastInsertId();
    // The page promises an e-mail; this is it.
    if (function_exists('ops_mail')) {
        $app = function_exists('app_name') ? app_name() : 'Operations';
        ops_mail($email, 'We have your request for a ' . $app . ' workspace',
            "Hello " . ($person ?: 'there') . ",\n\nThank you — we have your request for a " . $app . " workspace for " . $company . ".\n"
            . "We review every request personally, usually within one working day, and will e-mail you here with your sign-in details.\n",
            '', 'tenant_request_ack');
    }
    return [true, 'Thanks — your request has been received. We will review it and set up your workspace shortly.', $id];
}

function tenant_request_get($id) {
    tenant_signup_migrate();
    return ops_one("SELECT * FROM tenant_requests WHERE id=?", [(int)$id]) ?: null;
}
function tenant_requests_list($status = null) {
    tenant_signup_migrate();
    if ($status !== null)
        return ops_all("SELECT * FROM tenant_requests WHERE status=? ORDER BY id DESC", [(string)$status]) ?: [];
    return ops_all("SELECT * FROM tenant_requests ORDER BY (status='PENDING') DESC, id DESC") ?: [];
}
function tenant_requests_pending_count() {
    tenant_signup_migrate();
    try { return (int)ops_val("SELECT COUNT(*) FROM tenant_requests WHERE status='PENDING'"); }
    catch (Throwable $e) { return 0; }
}

/** Internal — stamp a decision on a request. */
function tenant_request_mark($id, $status, $tenantSub = '', $adminNote = '') {
    tenant_signup_migrate();
    db()->prepare("UPDATE tenant_requests SET status=?, tenant_sub=?, admin_note=?, decided_by=?, decided_at=? WHERE id=?")
        ->execute([(string)$status, (string)$tenantSub, mb_substr((string)$adminNote,0,500),
                   function_exists('user_name') ? user_name(current_user()) : '', date('c'), (int)$id]);
}

/**
 * Super-Admin approves a pending request. Returns [ok, message].
 * Auto-provisions via cPanel when configured; otherwise marks APPROVED and
 * leaves the two-click finish to the operator on the Workspaces panel.
 */
function tenant_request_approve($id, $subOverride = '') {
    $r = tenant_request_get($id);
    if (!$r)                        return [false, 'That request no longer exists.'];
    if ($r['status'] !== 'PENDING') return [false, 'That request has already been dealt with.'];

    // The operator may rename the workspace at approval time — this is how two
    // companies with the same name are kept apart (e.g. acme → acme-mumbai).
    $sub = strtolower(trim((string)($subOverride !== '' ? $subOverride : $r['sub'])));
    if (!(function_exists('tenant_valid_sub') && tenant_valid_sub($sub)))
        return [false, 'The workspace name must be lowercase letters, digits or hyphens (e.g. acme-mumbai).'];

    $reg = function_exists('tenant_registry') ? tenant_registry() : ['tenants' => []];
    if (($reg['base_domain'] ?? '') === '')
        return [false, 'Switch cloud mode on first (set the base domain on the Workspaces panel), then approve.'];
    if (isset($reg['tenants'][$sub]))
        return [false, 'A workspace named “' . $sub . '” already exists — type a different name in the box and approve again (e.g. ' . $sub . '-2 or ' . $sub . '-<city>).'];

    if (function_exists('saas_login_lookup') && saas_login_lookup((string)$r['email']) !== '')
        return [false, 'That e-mail already has a workspace — the owner can simply sign in.'];

    // Where its data lives: cPanel makes a database when it is connected;
    // otherwise storage is chosen automatically, so there is no hand-finishing.
    $db = null; $note = '';
    if (function_exists('cpanel_configured') && cpanel_configured()) {
        $prov = cpanel_provision_workspace($sub);
        if (empty($prov['ok']))
            return [false, 'cPanel could not create the workspace: ' . implode(' ', $prov['errors'] ?? [])];
        $db = $prov['db'];
        $note = !empty($prov['errors']) ? ' (' . implode(' ', $prov['errors']) . ')' : '';
    }
    if (!function_exists('saas_company_create')) return [false, 'The company builder is not available on this build.'];

    // The same company a self sign-up gets — owner login, free trial of every
    // module, the same rules after the trial — only with the platform owner's
    // approval in between (lib/selfserve.php).
    $cfg   = function_exists('selfserve_cfg') ? selfserve_cfg() : ['trial_days' => 30, 'storage_mb' => 0, 'trial_seats' => 0];
    $until = date('Y-m-d', strtotime('+' . (int)$cfg['trial_days'] . ' days'));
    $res = saas_company_create([
        'company' => (string)$r['company'], 'key' => $sub, 'owner_name' => (string)$r['contact_name'],
        'owner_email' => (string)$r['email'], 'owner_phone' => (string)$r['phone'], 'owner_pass' => '',
        'plan' => 'TRIAL', 'modules' => array_merge(['admin'], function_exists('selfserve_all_modules') ? selfserve_all_modules() : []),
        'source' => 'self', 'trial_until' => $until, 'db' => $db,
        'pending_extra' => ['billing_mode' => 'self', 'trial_until' => $until, 'storage_mb' => (int)$cfg['storage_mb'],
                            'seat_limit' => (int)$cfg['trial_seats']],
    ]);
    if (!$res['ok']) return [false, $res['error']];
    tenant_request_mark($id, 'PROVISIONED', $res['key'], 'Approved — workspace and owner login created' . $note);
    $mailed = function_exists('saas_mail_new_owner')
        ? saas_mail_new_owner((string)$r['email'], (string)$r['contact_name'], (string)$r['company'], $res['temp_pass']) : 0;
    return [true, 'Workspace for “' . $r['company'] . '” created on a ' . (int)$cfg['trial_days'] . '-day free trial. '
        . 'Its owner signs in with ' . $r['email'] . ' (temporary password: ' . $res['temp_pass'] . ').'
        . ($mailed ? ' These details have been e-mailed to them.' : ' E-mail is not set up, so please pass these details on yourself.') . $note];
}

/** Mark an APPROVED request as PROVISIONED once its workspace has been added
 *  by hand (assisted path). */
function tenant_request_mark_provisioned($id) {
    $r = tenant_request_get($id);
    if (!$r) return [false, 'That request no longer exists.'];
    if ($r['status'] !== 'APPROVED') return [false, 'Only an approved request can be marked as set up.'];
    tenant_request_mark($id, 'PROVISIONED', (string)$r['sub'], 'Marked set up by the operator.');
    return [true, 'Marked as set up.'];
}

/** Super-Admin declines a request. */
function tenant_request_reject($id, $note = '') {
    $r = tenant_request_get($id);
    if (!$r) return [false, 'That request no longer exists.'];
    if (in_array($r['status'], ['PROVISIONED'], true))
        return [false, 'That workspace is already set up — it cannot be declined.'];
    tenant_request_mark($id, 'REJECTED', (string)$r['tenant_sub'], $note !== '' ? $note : 'Declined.');
    return [true, 'Request declined.'];
}

/** The public onboarding page (dispatched before require_login). Always exits.
 *  Only meaningful on the cloud control install; a licence copy has no fleet. */
function tenant_signup_route($method) {
    // A private licence copy is a single company — there is no fleet to join.
    if (function_exists('install_is_licence') && install_is_licence()) {
        if (function_exists('current_user') && !current_user()) redirect('/login');
        http_response_code(404); echo 'Not available.'; exit;
    }
    // Self-serve sign-up switched on: companies create their own workspace at
    // /start instead of applying and waiting (lib/selfserve.php).
    if (function_exists('selfserve_enabled') && selfserve_enabled() && function_exists('selfserve_ready') && selfserve_ready()) redirect('/start');
    tenant_signup_migrate();
    $done = false; $err = ''; $open = tenant_signup_enabled();
    if ($method === 'POST' && $open) {
        if (trim((string)($_POST['website'] ?? '')) !== '') {
            $done = true;                       // a robot filled the hidden field: same reply, nothing stored
        } elseif (function_exists('csrf_ok') && !csrf_ok($_POST['_csrf'] ?? '')) {
            $err = 'Your page had been open too long. Please press the button again.';
        } else {
            [$ok, $msg] = tenant_signup_submit($_POST);
            if ($ok) $done = true; else $err = $msg;
        }
    }
    $GLOBALS['__ws_csrf'] = function_exists('csrf_token') ? csrf_token() : '';
    $GLOBALS['__ws_done'] = $done;
    $GLOBALS['__ws_err']  = $err;
    $GLOBALS['__ws_open'] = $open;
    $GLOBALS['__ws_post'] = $_POST;
    require __DIR__ . '/../views/public/get_started.php';
    exit;
}
