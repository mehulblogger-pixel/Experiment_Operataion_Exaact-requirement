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
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
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
}

/** The public page is OFF by default — the operator turns it on from the
 *  Workspaces panel once they are ready to take online registrations. */
function tenant_signup_enabled() {
    return function_exists('setting_get') && (string)setting_get('workspace_signup_enabled', '0') === '1';
}
function tenant_signup_set_enabled($on) {
    if (function_exists('setting_set')) setting_set('workspace_signup_enabled', $on ? '1' : '0');
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

    db()->prepare("INSERT INTO tenant_requests (company,contact_name,email,phone,sub,note,status,created_at)
                   VALUES (?,?,?,?,?,?, 'PENDING', ?)")
        ->execute([mb_substr($company,0,150), mb_substr($person,0,120), mb_substr($email,0,160),
                   mb_substr($phone,0,40), mb_substr($sub,0,48), mb_substr($note,0,500), date('c')]);
    return [true, 'Thanks — your request has been received. We will review it and set up your workspace shortly.', (int)db()->lastInsertId()];
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
function tenant_request_approve($id) {
    $r = tenant_request_get($id);
    if (!$r)                        return [false, 'That request no longer exists.'];
    if ($r['status'] !== 'PENDING') return [false, 'That request has already been dealt with.'];

    $sub = strtolower(trim((string)$r['sub']));
    if (!(function_exists('tenant_valid_sub') && tenant_valid_sub($sub)))
        return [false, 'The workspace name on this request is not usable — it must be lowercase letters, digits or hyphens.'];

    $reg = function_exists('tenant_registry') ? tenant_registry() : ['tenants' => []];
    if (($reg['base_domain'] ?? '') === '')
        return [false, 'Switch cloud mode on first (set the base domain on the Workspaces panel), then approve.'];
    if (isset($reg['tenants'][$sub]))
        return [false, 'A workspace named “' . $sub . '” already exists — pick a different name before approving.'];

    // Automatic path — cPanel makes the database (and maybe the subdomain).
    if (function_exists('cpanel_configured') && cpanel_configured()) {
        $prov = cpanel_provision_workspace($sub);
        if (empty($prov['ok']))
            return [false, 'cPanel could not create the workspace: ' . implode(' ', $prov['errors'] ?? [])];
        $err = tenant_add($sub, $r['company'], $prov['db']);
        if ($err !== '') return [false, $err];
        $note = !empty($prov['errors']) ? ' (' . implode(' ', $prov['errors']) . ')' : '';
        tenant_request_mark($id, 'PROVISIONED', $sub, 'Auto-provisioned via cPanel' . $note);
        return [true, 'Workspace “' . $sub . '” created. Their address: https://' . $sub . '.' . tenant_base_domain()
            . '/ — they finish their own first-time setup there.' . $note];
    }

    // Assisted path — approve now, finish provisioning by hand.
    tenant_request_mark($id, 'APPROVED', $sub,
        'Approved — create the database + subdomain in cPanel, then add “' . $sub . '” on the Workspaces panel.');
    return [true, 'Approved. Now create the database + subdomain in cPanel, then add the workspace “' . $sub
        . '” on the Workspaces panel below to finish. (Once its database is registered, mark this request as set up.)'];
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
    tenant_signup_migrate();
    $done = false; $err = ''; $open = tenant_signup_enabled();
    if ($method === 'POST' && $open) {
        [$ok, $msg] = tenant_signup_submit($_POST);
        if ($ok) $done = true; else $err = $msg;
    }
    $GLOBALS['__ws_done'] = $done;
    $GLOBALS['__ws_err']  = $err;
    $GLOBALS['__ws_open'] = $open;
    $GLOBALS['__ws_post'] = $_POST;
    require __DIR__ . '/../views/public/get_started.php';
    exit;
}
