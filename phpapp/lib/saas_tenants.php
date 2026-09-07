<?php
// ============================================================================
//  SaaS CONTROL PLANE — the central directory of companies (workspaces)
//
//  The product is sold to many companies on ONE web address. This file is the
//  cross-company directory that a single-URL login and the super-admin console
//  need: who the companies are, their plan, their purchased seats, the modules
//  they bought, and their status. It mirrors MGH Books' `tenants` table and its
//  per-seat model (extra_user_seats + a login/session cap), adapted to THIS
//  app's design, where each company keeps its OWN database (stronger isolation
//  than Books' shared row-level store).
//
//  ADDITIVE AND NON-DESTRUCTIVE. Nothing here changes how the app already
//  resolves a workspace by web address (config.php / tenants.php) or the
//  per-install Super Admin panel. It only adds a directory those features can
//  read from. On a plain single-company install these tables simply stay empty.
//
//  WHERE IT LIVES: the directory is meaningful in the control database — the
//  base install that serves the bare product domain. The boot chain creates the
//  (empty) tables in every database, which is harmless.
// ============================================================================

// Base logins a plan includes (owner + staff) BEFORE any purchased seats — the
// same idea as Books' plans.max_users. Kept beside superadmin_tiers()'s module
// map; a plan not listed falls back to a safe minimum.
function saas_plan_logins($plan) {
    $map = ['STARTER' => 2, 'RECRUITMENT' => 3, 'PRO' => 5, 'ENTERPRISE' => 9];
    return $map[strtoupper((string) $plan)] ?? 2;
}

// The modules a plan grants, reused from the existing tier catalogue so the two
// never disagree. Returns a list like ['admin','hr'].
function saas_plan_modules($plan) {
    $plan = strtoupper((string) $plan);
    $tiers = function_exists('superadmin_tiers') ? superadmin_tiers() : [];
    return array_values((array) ($tiers[$plan]['mods'] ?? ['admin']));
}

// ---------------------------------------------------------------------------
//  Schema. Registered in the boot chain (lib/db.php run_schema).
// ---------------------------------------------------------------------------
function saas_tenants_migrate() {
    $pdo = function_exists('db') ? db() : null;
    if (!$pdo) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS saas_tenants (
            id $pk,
            tenant_key VARCHAR(64) UNIQUE,
            company VARCHAR(200) DEFAULT '',
            owner_name VARCHAR(150) DEFAULT '',
            owner_email VARCHAR(190) DEFAULT '',
            plan VARCHAR(40) DEFAULT 'RECRUITMENT',
            plan_expiry VARCHAR(20) DEFAULT '',
            status VARCHAR(20) DEFAULT 'active',
            extra_user_seats INT DEFAULT 0,
            enabled_modules TEXT DEFAULT '[]',
            created_at VARCHAR(30) DEFAULT '',
            last_login_at VARCHAR(30) DEFAULT ''
        )");
    } catch (Throwable $e) { /* never block the rest of the boot chain */ }

    // Email -> company index, so a person typing only their email + password on
    // the one product URL can be routed to their company. One person can belong
    // to exactly one company here (their login company); the row is kept in step
    // whenever a company's users change.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS saas_logins (
            id $pk,
            email VARCHAR(190),
            tenant_key VARCHAR(64),
            is_active INT DEFAULT 1,
            updated_at VARCHAR(30) DEFAULT ''
        )");
    } catch (Throwable $e) { /* never block the rest of the boot chain */ }
}

// ---------------------------------------------------------------------------
//  Directory reads/writes. Every one guards its query so a control table that
//  does not exist yet (an older database mid-upgrade) never breaks a page.
// ---------------------------------------------------------------------------
function saas_tenant_get($key) {
    if (!function_exists('ops_one')) return null;
    try { return ops_one("SELECT * FROM saas_tenants WHERE tenant_key=?", [strtolower(trim((string) $key))]) ?: null; }
    catch (Throwable $e) { return null; }
}

function saas_tenant_all() {
    if (!function_exists('ops_all')) return [];
    try { return ops_all("SELECT * FROM saas_tenants ORDER BY company, tenant_key"); }
    catch (Throwable $e) { return []; }
}

// Create or update a company in the directory. $key is the workspace key that
// matches the tenants.php routing registry. Only the fields present in $data are
// changed on an update, so a partial call (e.g. just the plan) is safe.
function saas_tenant_upsert($key, array $data) {
    $pdo = function_exists('db') ? db() : null;
    if (!$pdo) return false;
    $key = strtolower(trim((string) $key));
    if ($key === '') return false;
    $cols = ['company', 'owner_name', 'owner_email', 'plan', 'plan_expiry', 'status', 'extra_user_seats', 'enabled_modules'];
    $existing = saas_tenant_get($key);
    try {
        if ($existing) {
            $set = []; $vals = [];
            foreach ($cols as $c) if (array_key_exists($c, $data)) {
                $set[] = "$c=?";
                $vals[] = $c === 'enabled_modules' && is_array($data[$c]) ? json_encode(array_values($data[$c])) : $data[$c];
            }
            if (!$set) return true;
            $vals[] = $key;
            $pdo->prepare("UPDATE saas_tenants SET " . implode(',', $set) . " WHERE tenant_key=?")->execute($vals);
        } else {
            $row = array_merge([
                'company' => '', 'owner_name' => '', 'owner_email' => '', 'plan' => 'RECRUITMENT',
                'plan_expiry' => '', 'status' => 'active', 'extra_user_seats' => 0, 'enabled_modules' => '[]',
            ], array_intersect_key($data, array_flip($cols)));
            if (is_array($row['enabled_modules'])) $row['enabled_modules'] = json_encode(array_values($row['enabled_modules']));
            $pdo->prepare("INSERT INTO saas_tenants
                (tenant_key,company,owner_name,owner_email,plan,plan_expiry,status,extra_user_seats,enabled_modules,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$key, $row['company'], $row['owner_name'], $row['owner_email'], $row['plan'],
                    $row['plan_expiry'], $row['status'], (int) $row['extra_user_seats'], $row['enabled_modules'], date('c')]);
        }
        return true;
    } catch (Throwable $e) { return false; }
}

function saas_tenant_set_status($key, $status) {
    $status = in_array($status, ['active', 'pending', 'suspended'], true) ? $status : 'active';
    return saas_tenant_upsert($key, ['status' => $status]);
}
function saas_tenant_set_plan($key, $plan, $expiry = null) {
    $d = ['plan' => strtoupper((string) $plan), 'enabled_modules' => saas_plan_modules($plan)];
    if ($expiry !== null) $d['plan_expiry'] = (string) $expiry;
    return saas_tenant_upsert($key, $d);
}
// Add (or remove, with a negative number) purchased seats — seats STACK, exactly
// like Books' extra_user_seats add-on.
function saas_tenant_add_seats($key, $delta) {
    $t = saas_tenant_get($key);
    if (!$t) return false;
    $now = max(0, (int) ($t['extra_user_seats'] ?? 0) + (int) $delta);
    return saas_tenant_upsert($key, ['extra_user_seats' => $now]);
}
function saas_tenant_set_modules($key, array $mods) {
    return saas_tenant_upsert($key, ['enabled_modules' => array_values($mods)]);
}

// The Books seat formula: how many people this company may have signed in at
// once (and, by the same number, how many logins it may hold) =
//   base plan logins + purchased seats.
function saas_tenant_seat_limit($key) {
    $t = saas_tenant_get($key);
    if (!$t) return 0;
    return saas_plan_logins($t['plan'] ?? '') + (int) ($t['extra_user_seats'] ?? 0);
}

function saas_tenant_modules($key) {
    $t = saas_tenant_get($key);
    if (!$t) return [];
    $m = json_decode((string) ($t['enabled_modules'] ?? '[]'), true);
    return is_array($m) ? array_values($m) : [];
}

// ---------------------------------------------------------------------------
//  Email -> company index (for single-URL, email-only login).
// ---------------------------------------------------------------------------
function saas_login_index_set($email, $key, $active = true) {
    $pdo = function_exists('db') ? db() : null;
    if (!$pdo) return false;
    $email = strtolower(trim((string) $email));
    $key = strtolower(trim((string) $key));
    if ($email === '' || $key === '') return false;
    try {
        $exists = ops_one("SELECT id FROM saas_logins WHERE email=?", [$email]);
        if ($exists) {
            $pdo->prepare("UPDATE saas_logins SET tenant_key=?, is_active=?, updated_at=? WHERE email=?")
                ->execute([$key, $active ? 1 : 0, date('c'), $email]);
        } else {
            $pdo->prepare("INSERT INTO saas_logins (email,tenant_key,is_active,updated_at) VALUES (?,?,?,?)")
                ->execute([$email, $key, $active ? 1 : 0, date('c')]);
        }
        return true;
    } catch (Throwable $e) { return false; }
}

// ---------------------------------------------------------------------------
//  Entering / leaving a company's workspace within a request (single-URL login).
//  These remember the chosen company in the session and switch the live database
//  to it, so config.php resolves to that company on this and every later request
//  until the person signs out. Additive: a plain install never sets this.
// ---------------------------------------------------------------------------
function saas_current_tenant() {
    return isset($_SESSION['saas_tenant']) ? (string) $_SESSION['saas_tenant'] : '';
}
function saas_enter_tenant($key) {
    $key = strtolower(trim((string) $key));
    if ($key === '') return false;
    $_SESSION['saas_tenant'] = $key;
    if (function_exists('db_reset')) db_reset();   // next db() opens THIS company's store
    return true;
}
function saas_leave_tenant() {
    // Only reconnect if we were actually inside a company. Resetting when we are
    // already on the control database would open a SECOND connection to the same
    // store while the first is still in use — on SQLite that is a lock deadlock.
    $had = isset($_SESSION['saas_tenant']) && $_SESSION['saas_tenant'] !== '';
    unset($_SESSION['saas_tenant']);
    if ($had && function_exists('db_reset')) db_reset();   // back to the control database
}

// Which company does this email sign in to? Returns a tenant_key or ''.
function saas_login_lookup($email) {
    if (!function_exists('ops_one')) return '';
    $email = strtolower(trim((string) $email));
    if ($email === '') return '';
    try {
        $r = ops_one("SELECT tenant_key FROM saas_logins WHERE email=? AND is_active=1", [$email]);
        return $r ? (string) $r['tenant_key'] : '';
    } catch (Throwable $e) { return ''; }
}

// ---------------------------------------------------------------------------
//  SUPER-ADMIN — the Companies console (route /companies)
//
//  Extends the existing Super Admin panel with a cross-company management
//  surface, mirroring the Books super-admin: every company in one list with its
//  plan, purchased seats, modules and status, plus the levers to change them and
//  to log in as a company. Additive — the per-install control panel is untouched.
//  Super-admin only.
// ---------------------------------------------------------------------------

// The plan catalogue the console offers, enriched with the base login count so
// the screen can show "plan base + purchased = total seats" at a glance.
function saas_console_plans() {
    $tiers = function_exists('superadmin_tiers') ? superadmin_tiers() : [];
    $out = [];
    foreach ($tiers as $key => $t) {
        $out[$key] = [
            'label' => $t['label'] ?? $key,
            'mods'  => array_values((array) ($t['mods'] ?? [])),
            'base'  => saas_plan_logins($key),
            'pitch' => $t['pitch'] ?? '',
        ];
    }
    return $out;
}

// Every company for the console: the directory row joined with its routing
// status (from tenants.php) so "suspended" is shown truthfully.
function saas_console_companies() {
    $rows = saas_tenant_all();
    $reg  = function_exists('tenant_registry') ? tenant_registry() : ['tenants' => []];
    $routes = (array) ($reg['tenants'] ?? []);
    foreach ($rows as &$r) {
        $k = (string) ($r['tenant_key'] ?? '');
        $r['routed']        = isset($routes[$k]);
        $r['route_status']  = $routes[$k]['status'] ?? '';
        $r['mods_list']     = saas_tenant_modules($k);
        $r['seat_limit']    = saas_tenant_seat_limit($k);
        $r['base_logins']   = saas_plan_logins($r['plan'] ?? '');
    }
    unset($r);
    return $rows;
}

function ops_saas_admin($route, $method) {
    ops_require(function_exists('superadmin_can') ? superadmin_can() : (function_exists('is_master') && is_master()),
        'Only the Super Admin can open the Companies console.');

    if ($method === 'POST') {
        $do  = (string) ($_POST['do'] ?? '');
        $key = strtolower(trim((string) ($_POST['key'] ?? '')));
        $back = '/companies' . ($key !== '' ? '?key=' . urlencode($key) : '');

        if ($do === 'company_plan' && $key !== '') {
            saas_tenant_set_plan($key, (string) ($_POST['plan'] ?? ''), trim((string) ($_POST['plan_expiry'] ?? '')) ?: null);
            flash('Plan updated for ' . $key . '.');
            redirect($back);
        }
        if ($do === 'company_seats' && $key !== '') {
            // Set an absolute purchased-seat count (over the plan base).
            $want = max(0, (int) ($_POST['extra_user_seats'] ?? 0));
            $t = saas_tenant_get($key);
            $cur = (int) ($t['extra_user_seats'] ?? 0);
            saas_tenant_add_seats($key, $want - $cur);
            flash('Seats updated for ' . $key . '.');
            redirect($back);
        }
        if ($do === 'company_modules' && $key !== '') {
            $valid = function_exists('licence_owner') && defined('PRODUCT_MODULES') ? array_keys(PRODUCT_MODULES) : ['admin', 'hr'];
            $picked = array_values(array_intersect($valid, (array) ($_POST['mods'] ?? [])));
            if (!in_array('admin', $picked, true)) $picked[] = 'admin';   // admin is core, always on
            saas_tenant_set_modules($key, $picked);
            flash('Modules updated for ' . $key . '.');
            redirect($back);
        }
        if ($do === 'company_status' && $key !== '') {
            $status = ($_POST['status'] ?? '') === 'suspended' ? 'suspended' : 'active';
            saas_tenant_set_status($key, $status);
            // Make it effective at the door: the routing registry decides login.
            if (function_exists('tenant_set_status')) tenant_set_status($key, $status);
            flash('Company ' . $key . ' ' . ($status === 'suspended' ? 'suspended' : 'reactivated') . '.');
            redirect('/companies');
        }
        if ($do === 'company_login_as' && $key !== '') {
            // Jump into a company as its admin. Super-admin only (guarded above).
            saas_enter_tenant($key);                       // switch the live DB to the company
            $admin = null;
            try { $admin = ops_one("SELECT * FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1"); }
            catch (Throwable $e) { $admin = null; }
            if (!$admin) {
                saas_leave_tenant();
                flash('That company has no active admin yet (its database may not be set up).', 'error');
                redirect('/companies');
            }
            $_SESSION['uid'] = (int) $admin['id'];
            $_SESSION['saas_impersonating'] = 1;           // a return-to-console breadcrumb for later
            flash('You are now signed in to ' . ($_POST['company'] ?? $key) . '. Log out to return.');
            redirect('/');
        }
        redirect('/companies');
    }

    $sel = strtolower(trim((string) ($_GET['key'] ?? '')));
    view('ops/saas_companies', [
        'companies' => saas_console_companies(),
        'plans'     => saas_console_plans(),
        'modules'   => defined('PRODUCT_MODULES') ? PRODUCT_MODULES : [],
        'sel'       => $sel !== '' ? saas_tenant_get($sel) : null,
        'base_domain' => function_exists('tenant_base_domain') ? tenant_base_domain() : '',
    ]);
    return true;
}
