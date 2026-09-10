<?php
// ============================================================================
//  Cloud / SaaS — one codebase, many workspaces, one database each
//
//  The application already runs from a single folder against a single database.
//  Cloud mode adds a registry — tenants.php — that maps a subdomain to its own
//  database. config.php reads it on every request and, when the request arrives
//  on a workspace subdomain, points the whole app at that workspace's database.
//  Nothing here is shared between workspaces except the code, so one tenant can
//  never see another's records.
//
//  Deliberately NOT built here: creating the database or the subdomain itself.
//  On shared hosting that is two clicks in cPanel (MySQL Databases, then
//  Subdomains), and doing it from PHP means holding cPanel API credentials the
//  application has no other reason to hold. So provisioning is assisted, not
//  fully automatic: the operator makes the database and the subdomain, and this
//  screen registers them and lets the app build the schema on first visit.
//
//  Each workspace is a full, independent install — its own admin, its own
//  licence, its own Razorpay/settings — because it is its own database.
// ============================================================================

function saas_enabled() { return !empty(($GLOBALS['__tenant'] ?? [])['saas']); }

// The workspace subdomain this request resolved to, or '' on the base domain
// (the control install) and in a plain single-business install.
function current_tenant() { return (string)(($GLOBALS['__tenant'] ?? [])['key'] ?? ''); }
function current_tenant_company() { return (string)(($GLOBALS['__tenant'] ?? [])['company'] ?? ''); }
function tenant_base_domain() {
    $b = (string)(($GLOBALS['__tenant'] ?? [])['base'] ?? '');
    if ($b !== '') return $b;
    $r = tenant_registry();
    return (string)($r['base_domain'] ?? '');
}

// Who may manage the fleet: the Master Admin, and only on the control install
// (the base domain) — never from inside a tenant workspace, which must not be
// able to reach the other workspaces. Works before cloud is switched on too, so
// it can be switched on from here.
function can_manage_tenants() {
    return function_exists('is_master') && is_master() && current_tenant() === '';
}

function tenant_registry_file() { return __DIR__ . '/../tenants.php'; }

function tenant_registry() {
    $f = tenant_registry_file();
    if (!is_file($f)) return ['base_domain' => '', 'aliases' => [], 'tenants' => []];
    $r = @require $f;
    if (!is_array($r)) $r = [];
    $r += ['base_domain' => '', 'aliases' => [], 'tenants' => []];
    if (!is_array($r['tenants'])) $r['tenants'] = [];
    return $r;
}

// Write the registry back to tenants.php. Returns '' on success, or a reason
// (usually that the folder is not writable by the web server).
function tenant_registry_write(array $reg) {
    $reg += ['base_domain' => '', 'aliases' => [], 'tenants' => []];
    $body = "<" . "?php\n"
          . "// Cloud workspace registry — written by the app. Maps each subdomain to its\n"
          . "// own database. Kept out of every upload so it is never overwritten.\n"
          . "return " . var_export($reg, true) . ";\n";
    $ok = @file_put_contents(tenant_registry_file(), $body);
    if ($ok === false)
        return 'Could not write tenants.php — the app folder is not writable by the web server. '
             . 'Create it by hand in cPanel → File Manager instead (see tenants.sample.php).';
    @chmod(tenant_registry_file(), 0600);
    return '';
}

// Turn cloud mode on by recording the base domain. Idempotent; keeps any
// workspaces already listed.
function tenant_enable_cloud($baseDomain) {
    $baseDomain = strtolower(trim((string)$baseDomain));
    $baseDomain = preg_replace('/^https?:\/\//', '', $baseDomain);
    $baseDomain = preg_replace('/\/.*$/', '', $baseDomain);
    if ($baseDomain === '' || strpos($baseDomain, '.') === false)
        return 'Enter the base domain workspaces live under, e.g. operations.example.com.';
    $reg = tenant_registry();
    $reg['base_domain'] = $baseDomain;
    // Also remember it in the control database, which survives every upload. The
    // routing file (tenants.php) is rebuilt from here if an upload ever wipes it,
    // so cloud mode never silently "switches off" again.
    if (function_exists('setting_set')) { try { setting_set('saas_base_domain', $baseDomain); } catch (Throwable $e) {} }
    return tenant_registry_write($reg);
}

// Rebuild the routing file (tenants.php) from the control DATABASE, which
// survives every upload. The file is only a cache of what the database already
// knows — the saved base domain and each company's stored routing — so if an
// upload wipes it, cloud mode and every company come straight back on the next
// page load instead of appearing "switched off". Only ever reconciles TOWARDS
// the database: it adds the base domain and any company missing from the file,
// and never removes or overwrites an entry the file already has. Runs only on
// the control install. Returns true if it changed the file.
function tenant_registry_heal() {
    if (!function_exists('db') || !function_exists('setting_get')) return false;
    if (function_exists('current_tenant') && current_tenant() !== '') return false;   // never inside a workspace
    $base = (string) setting_get('saas_base_domain', '');
    if ($base === '') return false;                       // cloud was never turned on — nothing to heal
    $reg = tenant_registry();
    $changed = false;
    if (($reg['base_domain'] ?? '') === '') { $reg['base_domain'] = $base; $changed = true; }
    if (!is_array($reg['tenants'] ?? null)) $reg['tenants'] = [];
    $rows = function_exists('saas_tenant_all') ? saas_tenant_all() : [];
    foreach ($rows as $r) {
        $key = strtolower(trim((string) ($r['tenant_key'] ?? '')));
        if ($key === '' || isset($reg['tenants'][$key])) continue;   // already routed — leave it exactly as it is
        $entry = ['company' => (string) ($r['company'] ?? $key), 'status' => (string) ($r['status'] ?? 'active')];
        $route = json_decode((string) ($r['route_json'] ?? ''), true);
        if (is_array($route) && !empty($route['sqlite'])) {
            $entry['sqlite'] = (string) $route['sqlite'];
        } elseif (is_array($route) && !empty($route['name'])) {
            $entry['db'] = ['host' => (string) ($route['host'] ?? 'localhost'), 'name' => (string) $route['name'],
                            'user' => (string) ($route['user'] ?? ''), 'pass' => (string) ($route['pass'] ?? '')];
        } else {
            // A company created before durable routing existed: the default
            // storage is a file named after the workspace key, opened/rebuilt on
            // first use, so point at that.
            $entry['sqlite'] = __DIR__ . '/../tenant-' . $key . '.sqlite';
        }
        $reg['tenants'][$key] = $entry;
        $changed = true;
    }
    if (!$changed) return false;
    tenant_registry_write($reg);
    return true;
}

// Validate a subdomain label: lowercase letters, digits and hyphens only.
function tenant_valid_sub($sub) {
    return (bool)preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', (string)$sub);
}

// Add (or update) a workspace. $db is either ['sqlite'=>path] or
// ['host'=>,'name'=>,'user'=>,'pass'=>]. Returns '' on success or a reason.
function tenant_add($sub, $company, array $db) {
    $sub = strtolower(trim((string)$sub));
    if (!tenant_valid_sub($sub)) return 'The workspace name must be lowercase letters, digits or hyphens (e.g. acme).';
    $reg = tenant_registry();
    if (($reg['base_domain'] ?? '') === '') return 'Switch cloud mode on first by setting the base domain.';
    $entry = ['company' => substr(trim((string)$company), 0, 150) ?: $sub, 'status' => 'active'];
    if (!empty($db['sqlite'])) {
        $entry['sqlite'] = (string)$db['sqlite'];
    } else {
        $entry['db'] = [
            'host' => trim((string)($db['host'] ?? 'localhost')) ?: 'localhost',
            'name' => trim((string)($db['name'] ?? '')),
            'user' => trim((string)($db['user'] ?? '')),
            'pass' => (string)($db['pass'] ?? ''),
        ];
        if ($entry['db']['name'] === '' || $entry['db']['user'] === '')
            return 'The workspace database name and user are both needed.';
    }
    $reg['tenants'][$sub] = $entry;
    return tenant_registry_write($reg);
}

function tenant_set_status($sub, $status) {
    $sub = strtolower(trim((string)$sub));
    $reg = tenant_registry();
    if (!isset($reg['tenants'][$sub])) return 'No such workspace.';
    $reg['tenants'][$sub]['status'] = $status === 'suspended' ? 'suspended' : 'active';
    return tenant_registry_write($reg);
}

// Remove a workspace from the registry. Does NOT drop its database — the data
// stays until the operator deletes the database in cPanel, so a mistake here is
// recoverable by re-adding it.
function tenant_remove($sub) {
    $sub = strtolower(trim((string)$sub));
    $reg = tenant_registry();
    unset($reg['tenants'][$sub]);
    return tenant_registry_write($reg);
}

// ---------------------------------------------------------------------------
//  Deferred, exec-free provisioning ("first-boot stamp").
//
//  Managed hosting (cPanel/mPanel) very often disables PHP's exec(), so the
//  old provisioner — which ran a separate PHP process to build the new
//  company's database — silently did nothing there, leaving a company with no
//  database and no owner login. To work everywhere, we instead stash the
//  owner's details on the tenant's registry entry when the company is created,
//  and apply them the first time that company's OWN workspace is opened (a
//  clean request pointed only at its own database, so the per-process migration
//  guards are untouched). Nothing sensitive is exposed: tenants.php already
//  holds the database credentials and is kept off every upload, and only a
//  password HASH is stored here, never the plaintext.
// ---------------------------------------------------------------------------
function tenant_pending_set($sub, array $payload) {
    $sub = strtolower(trim((string)$sub));
    $reg = tenant_registry();
    if (!isset($reg['tenants'][$sub])) return 'No such workspace.';
    $reg['tenants'][$sub]['pending'] = $payload;
    return tenant_registry_write($reg);
}
function tenant_pending($sub) {
    $sub = strtolower(trim((string)$sub));
    $reg = tenant_registry();
    $p = $reg['tenants'][$sub]['pending'] ?? null;
    return is_array($p) ? $p : null;
}
function tenant_pending_clear($sub) {
    $sub = strtolower(trim((string)$sub));
    $reg = tenant_registry();
    if (!isset($reg['tenants'][$sub]['pending'])) return '';
    unset($reg['tenants'][$sub]['pending']);
    return tenant_registry_write($reg);
}

// ---- The management screen (control install, Master Admin only) -----------
function ops_tenants($route, $method) {
    ops_require(can_manage_tenants(),
        'Only the Master Admin, on the main site, can manage cloud workspaces — a workspace cannot reach the others.');

    if ($route === 'tenant-enable' && $method === 'POST') {
        $err = tenant_enable_cloud((string)($_POST['base_domain'] ?? ''));
        flash($err === '' ? 'Cloud mode is on. Add your first workspace below.' : $err, $err === '' ? 'success' : 'error');
        redirect('/tenants');
    }
    if ($route === 'cpanel-save' && $method === 'POST') {
        setting_set('cpanel_host', trim((string)($_POST['cpanel_host'] ?? '')));
        setting_set('cpanel_port', (string)(int)($_POST['cpanel_port'] ?? 2083) ?: '2083');
        setting_set('cpanel_user', trim((string)($_POST['cpanel_user'] ?? '')));
        if (trim((string)($_POST['cpanel_token'] ?? '')) !== '')   // blank = keep existing
            setting_set('cpanel_token', trim((string)$_POST['cpanel_token']));
        setting_set('cpanel_rootdomain', trim((string)($_POST['cpanel_rootdomain'] ?? '')));
        setting_set('cpanel_docroot', trim((string)($_POST['cpanel_docroot'] ?? '')));
        setting_set('cpanel_make_subdomain', !empty($_POST['cpanel_make_subdomain']) ? '1' : '0');
        setting_set('cpanel_verify_ssl', !empty($_POST['cpanel_verify_ssl']) ? '1' : '0');
        flash('cPanel settings saved.');
        redirect('/tenants');
    }
    if ($route === 'cpanel-test' && $method === 'POST') {
        $r = function_exists('cpanel_test') ? cpanel_test() : ['ok' => false, 'msg' => 'cPanel support is not installed.'];
        flash($r['msg'], $r['ok'] ? 'success' : 'error');
        redirect('/tenants');
    }

    if ($route === 'tenant-add' && $method === 'POST') {
        $sub = strtolower(trim((string)($_POST['sub'] ?? '')));
        $company = (string)($_POST['company'] ?? '');
        $kind = (string)($_POST['db_kind'] ?? 'mysql');
        if ($kind === 'auto') {
            // Create the database (and maybe the subdomain) through cPanel, then
            // register the workspace with the credentials cPanel handed back.
            if (!function_exists('cpanel_configured') || !cpanel_configured()) {
                flash('Automatic setup needs the cPanel API configured first (below).', 'error');
                redirect('/tenants');
            }
            if (!tenant_valid_sub($sub)) { flash('The workspace name must be lowercase letters, digits or hyphens.', 'error'); redirect('/tenants'); }
            $prov = cpanel_provision_workspace($sub);
            if (empty($prov['ok'])) { flash('cPanel could not create the workspace: ' . implode(' ', $prov['errors'] ?? []), 'error'); redirect('/tenants'); }
            $db = $prov['db'];
            $note = $prov['errors'] ? ' (' . implode(' ', $prov['errors']) . ')' : '';
            $err = tenant_add($sub, $company, $db);
            if ($err !== '') { flash($err, 'error'); redirect('/tenants'); }
            flash('Workspace “' . $sub . '” created via cPanel — database ' . $db['name'] . ' is ready' . $note
                . '. Open https://' . $sub . '.' . tenant_base_domain() . '/ to finish its first-time setup.',
                $prov['errors'] ? 'warning' : 'success');
            redirect('/tenants');
        }
        if ($kind === 'sqlite') {
            $safe = preg_replace('/[^a-z0-9-]/', '', $sub);
            $db = ['sqlite' => __DIR__ . '/../tenant-' . $safe . '.sqlite'];
        } else {
            $db = ['host' => (string)($_POST['db_host'] ?? 'localhost'), 'name' => (string)($_POST['db_name'] ?? ''),
                   'user' => (string)($_POST['db_user'] ?? ''), 'pass' => (string)($_POST['db_pass'] ?? '')];
            // Prove the workspace database connects before we route anyone to it.
            if (function_exists('setup_test_db')) {
                $terr = setup_test_db($db);
                if ($terr !== '') { flash('That workspace database did not connect: ' . $terr, 'error'); redirect('/tenants'); }
            }
        }
        $err = tenant_add($sub, $company, $db);
        if ($err !== '') { flash($err, 'error'); redirect('/tenants'); }
        flash('Workspace “' . $sub . '” added. Open https://' . $sub . '.' . tenant_base_domain()
            . '/ to finish its first-time setup.');
        redirect('/tenants');
    }
    if ($route === 'tenant-status' && $method === 'POST') {
        $err = tenant_set_status((string)($_POST['sub'] ?? ''), (string)($_POST['status'] ?? 'active'));
        flash($err === '' ? 'Workspace updated.' : $err, $err === '' ? 'success' : 'error');
        redirect('/tenants');
    }
    if ($route === 'tenant-remove' && $method === 'POST') {
        tenant_remove((string)($_POST['sub'] ?? ''));
        flash('Workspace removed from the list. Its database was NOT deleted — delete it in cPanel if you want it gone.', 'warning');
        redirect('/tenants');
    }

    // --- Public workspace-signup inbox (a new company applied for itself) ---
    if ($route === 'workspace-signup-toggle' && $method === 'POST') {
        if (function_exists('tenant_signup_set_enabled'))
            tenant_signup_set_enabled(!empty($_POST['on']));
        flash(!empty($_POST['on'])
            ? 'Online workspace registration is ON — companies can now apply at /get-started.'
            : 'Online workspace registration is OFF.');
        redirect('/tenants');
    }
    if ($route === 'tenant-request-approve' && $method === 'POST') {
        [$ok, $msg] = function_exists('tenant_request_approve')
            ? tenant_request_approve((int)($_POST['id'] ?? 0), (string)($_POST['sub'] ?? '')) : [false, 'Signup module missing.'];
        flash($msg, $ok ? 'success' : 'error');
        redirect('/tenants');
    }
    if ($route === 'tenant-request-provisioned' && $method === 'POST') {
        [$ok, $msg] = function_exists('tenant_request_mark_provisioned')
            ? tenant_request_mark_provisioned((int)($_POST['id'] ?? 0)) : [false, 'Signup module missing.'];
        flash($msg, $ok ? 'success' : 'error');
        redirect('/tenants');
    }
    if ($route === 'tenant-request-reject' && $method === 'POST') {
        [$ok, $msg] = function_exists('tenant_request_reject')
            ? tenant_request_reject((int)($_POST['id'] ?? 0), (string)($_POST['note'] ?? '')) : [false, 'Signup module missing.'];
        flash($msg, $ok ? 'success' : 'error');
        redirect('/tenants');
    }

    view('ops/tenants', [
        'reg'           => tenant_registry(),
        'requests'      => function_exists('tenant_requests_list') ? tenant_requests_list() : [],
        'signup_on'     => function_exists('tenant_signup_enabled') && tenant_signup_enabled(),
        'req_statuses'  => function_exists('tenant_request_statuses') ? tenant_request_statuses() : [],
    ]);
}

// The plain page an unknown or suspended workspace sees, before any database is
// touched. Standalone — the workspace's own branding lives in a database this
// request must not open. Always exits.
function tenant_error_page($kind, $sub = '') {
    if (!headers_sent()) {
        http_response_code($kind === 'suspended' ? 403 : ($kind === 'unconfigured' ? 503 : 404));
    }
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $title = $kind === 'suspended' ? 'Workspace paused'
           : ($kind === 'unconfigured' ? 'Workspace being set up' : 'Workspace not found');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . $title . '</title>';
    echo '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:70px auto;padding:26px;text-align:center;color:#333">';
    if ($kind === 'suspended') {
        echo '<h2 style="color:#b8480f">This workspace is paused</h2>';
        echo '<p style="color:#555;font-size:15px">The workspace <b>' . $e($sub) . '</b> is currently suspended. '
           . 'Please contact your administrator to have it restored.</p>';
    } elseif ($kind === 'unconfigured') {
        // The company exists in the registry but its own database is not wired
        // up yet. We refuse rather than showing another workspace's data.
        echo '<h2 style="color:#1e40af">This workspace is still being set up</h2>';
        echo '<p style="color:#555;font-size:15px">The workspace <b>' . $e($sub) . '</b> does not have its own '
           . 'database connected yet, so we can\'t open it safely. Please ask your administrator to finish '
           . 'setting it up, then sign in again.</p>';
    } else {
        echo '<h2 style="color:#1e40af">No workspace here</h2>';
        echo '<p style="color:#555;font-size:15px">There is no workspace at this address'
           . ($sub ? ' (<b>' . $e($sub) . '</b>)' : '') . '. Check the web address, or ask your administrator to create it.</p>';
    }
    echo '</div>';
    exit;
}
