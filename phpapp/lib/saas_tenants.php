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

// A URL-safe workspace key from a company name (lowercase, hyphenated).
function saas_slug($s) {
    $s = strtolower(trim((string) $s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string) $s, '-');
}

// Apply a plan's module entitlement to the CURRENT database (the tenant's own
// store): switch off every non-core module the plan does not include, exactly
// the mechanism the licence gate already enforces. Reused at provisioning and,
// later, when a plan is changed from the console.
function saas_apply_plan_modules($plan) {
    if (!defined('PRODUCT_MODULES') || !function_exists('setting_set')) return;
    $mods = saas_plan_modules($plan);
    $off = [];
    foreach (PRODUCT_MODULES as $k => $m) {
        $core = !empty($m[3]);
        if (!$core && !in_array($k, $mods, true)) $off[] = $k;
    }
    setting_set('modules_off', implode(',', $off));
    setting_set('product_package', strtoupper((string) $plan));
    if (function_exists('licence_disabled')) licence_disabled(true);   // reload the off-list cache
}

// Apply an EXPLICIT module list to the current database — the à-la-carte path,
// where a company buys exactly the modules it wants rather than a fixed plan.
// Switches off every non-core module not in the list; admin is always on.
function saas_apply_modules_list(array $mods) {
    if (!defined('PRODUCT_MODULES') || !function_exists('setting_set')) return;
    $mods = array_map('strtolower', $mods);
    $off = [];
    foreach (PRODUCT_MODULES as $k => $m) {
        $core = !empty($m[3]);
        if (!$core && !in_array($k, $mods, true)) $off[] = $k;
    }
    setting_set('modules_off', implode(',', $off));
    setting_set('product_package', 'CUSTOM');
    if (function_exists('licence_disabled')) licence_disabled(true);
}

// ---------------------------------------------------------------------------
//  À-la-carte pricing — "pay only for what you use".
// ---------------------------------------------------------------------------

// Sensible default monthly prices (major currency units) per module, used until
// a super-admin sets their own on the Pricing panel.
function saas_price_defaults() {
    return ['operations' => 1500, 'sales' => 1200, 'reporting' => 1400, 'money' => 900, 'hr' => 1000];
}

// The price book: per-seat price (reuses the existing Billing per-seat price) and
// a per-module price, monthly and yearly, plus the currency. Settings override
// the defaults, so it is fully configurable without code.
function saas_price_book() {
    $get = function ($k, $d) {
        if (!function_exists('setting_get')) return (int) $d;
        $v = setting_get($k, '');
        return $v === '' ? (int) $d : (int) $v;
    };
    $bill  = function_exists('billing_config') ? billing_config() : [];
    $seatM = (int) ($bill['price_month'] ?? 0) ?: $get('billing_price_user_month', 1799);
    $seatY = (int) ($bill['price_year'] ?? 0)  ?: $get('billing_price_user_year', $seatM * 10);
    $defM  = saas_price_defaults();
    $mods  = [];
    if (defined('PRODUCT_MODULES')) {
        foreach (PRODUCT_MODULES as $k => $m) {
            if (!empty($m[3])) continue;   // core (admin) is included, never priced
            $mm = $get('saas_price_mod_' . $k . '_month', $defM[$k] ?? 1000);
            $my = $get('saas_price_mod_' . $k . '_year', $mm * 10);
            $mods[$k] = ['label' => $m[0] ?? $k, 'month' => $mm, 'year' => $my];
        }
    }
    return ['seat' => ['month' => $seatM, 'year' => $seatY], 'modules' => $mods,
            'currency' => (string) ($bill['currency'] ?? 'INR')];
}

// Quote a company configuration: chosen modules + seats, monthly or yearly.
// Returns line items and a total, so the console and (later) a customer checkout
// can price exactly what was picked.
function saas_company_quote(array $mods, $seats, $period = 'month') {
    $period = $period === 'year' ? 'year' : 'month';
    $pb = saas_price_book();
    $mods = array_map('strtolower', $mods);
    $lines = []; $total = 0;
    foreach ($pb['modules'] as $k => $m) {
        if (in_array($k, $mods, true)) { $lines[] = ['label' => $m['label'], 'amount' => $m[$period]]; $total += $m[$period]; }
    }
    $seats = max(0, (int) $seats);
    $seatAmt = $seats * $pb['seat'][$period];
    $lines[] = ['label' => $seats . ' seat' . ($seats === 1 ? '' : 's'), 'amount' => $seatAmt];
    $total += $seatAmt;
    return ['period' => $period, 'currency' => $pb['currency'], 'lines' => $lines, 'total' => $total, 'seats' => $seats];
}

// Enforce the seat cap when a company adds a login. Reads the seat limit that
// was pushed into THIS company's own database (setting saas_seat_limit); returns
// a message to block, or '' to allow. Complements the licence seat check — a
// SaaS company has no signed licence, so that one lets everyone through.
function saas_seat_block($role = '') {
    if (!function_exists('setting_get')) return '';
    $limit = (int) setting_get('saas_seat_limit', 0);
    if ($limit <= 0) return '';                 // not a metered company / unlimited
    try { $used = (int) ops_val("SELECT COUNT(*) FROM users WHERE is_active=1"); }
    catch (Throwable $e) { return ''; }
    if ($used < $limit) return '';
    return 'This company is on ' . $limit . ' ' . ($limit === 1 ? 'seat' : 'seats')
         . ' and all ' . $limit . ' are in use. Buy more seats — or ask your provider to add them — before creating another login.';
}

// ---- Auto-provision a client's MySQL database (VPS one-click) ---------------
// On a self-managed server (a VPS) the app can create each new company's own
// database itself, so "Add a company" is genuinely one click — no manual cPanel
// step. It needs a database-admin credential allowed to CREATE DATABASE / CREATE
// USER, kept ONLY in the server's private config.local.php (never in the code,
// never in git). Without that credential the console simply offers "point at an
// existing database" instead — nothing breaks.

// The database-admin credential, if the server is configured for auto-create.
// Read from config.local.php (exposed by config.php as $SAAS_DB_ADMIN), with an
// environment fallback so it is testable. Returns null when not configured.
function saas_db_admin_config() {
    $cfg = $GLOBALS['SAAS_DB_ADMIN'] ?? null;
    if (!is_array($cfg)) {
        $u = getenv('SAAS_DB_ADMIN_USER');
        if ($u === false || $u === '') return null;
        $cfg = ['host' => getenv('SAAS_DB_ADMIN_HOST') ?: 'localhost', 'user' => $u,
                'pass' => (string) getenv('SAAS_DB_ADMIN_PASS'), 'prefix' => (string) getenv('SAAS_DB_ADMIN_PREFIX')];
    }
    if ((string) ($cfg['user'] ?? '') === '') return null;
    $cfg['host']   = (string) ($cfg['host'] ?? 'localhost') ?: 'localhost';
    $cfg['prefix'] = (string) ($cfg['prefix'] ?? '');
    return $cfg;
}

// True when this server can create a client database on its own.
function saas_can_autocreate_db() { return saas_db_admin_config() !== null; }

// A workspace key → a safe MySQL identifier fragment (letters, digits, underscore).
function saas_db_ident($s) {
    $s = strtolower((string) $s);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    $s = trim($s, '_');
    return $s === '' ? 'co' : $s;
}

// Derive the database name + user for a workspace, honouring an optional prefix
// (e.g. an account prefix). MySQL caps identifiers at 64 (database) and 32 (user)
// characters, so the user name is truncated to fit.
function saas_db_names_for($key, $prefix = '') {
    $frag = saas_db_ident($key);
    $p = $prefix !== '' ? rtrim(saas_db_ident($prefix), '_') . '_' : '';
    return ['name' => substr($p . $frag, 0, 64), 'user' => substr($p . $frag, 0, 32)];
}

// Create the empty database + a user scoped to just that database, using the
// admin credential. Returns the tenant db config ['host','name','user','pass']
// on success; throws on any failure. Identifier names are DERIVED and VALIDATED
// (never raw user input) because SQL identifiers cannot be bound as parameters.
function saas_mysql_provision_db($key, array $admin) {
    $names = saas_db_names_for($key, (string) ($admin['prefix'] ?? ''));
    $name = $names['name']; $user = $names['user'];
    if (!preg_match('/^[a-z0-9_]{1,64}$/', $name) || !preg_match('/^[a-z0-9_]{1,32}$/', $user))
        throw new RuntimeException('Could not derive a safe database name for this company.');
    $host = (string) ($admin['host'] ?? 'localhost') ?: 'localhost';
    $pass = bin2hex(random_bytes(12));
    $pdo  = new PDO("mysql:host={$host};charset=utf8mb4", (string) $admin['user'], (string) ($admin['pass'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE USER IF NOT EXISTS '{$user}'@'{$host}' IDENTIFIED BY " . $pdo->quote($pass));
    $pdo->exec("ALTER USER '{$user}'@'{$host}' IDENTIFIED BY " . $pdo->quote($pass));   // ensure the fresh password
    $pdo->exec("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$user}'@'{$host}'");
    $pdo->exec("FLUSH PRIVILEGES");
    return ['host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass];
}

// Push a company's plan / modules / seat limit into its OWN database so the
// change takes effect there. Light child process against the company's store
// (no reboot — it already exists). Returns true on success.
function saas_push_to_tenant($key) {
    $key = strtolower(trim((string) $key));
    $t = saas_tenant_get($key);
    if (!$t) return false;
    $reg = function_exists('tenant_registry') ? tenant_registry() : ['tenants' => []];
    $route = $reg['tenants'][$key] ?? null;
    if (!$route) return false;
    $php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $cli = __DIR__ . '/saas_sync_cli.php';
    $env = 'SAAS_PLAN=' . escapeshellarg(strtoupper((string) ($t['plan'] ?? 'RECRUITMENT')))
         . ' SAAS_SEAT_LIMIT=' . escapeshellarg((string) saas_tenant_seat_limit($key))
         . ' SAAS_MODULES=' . escapeshellarg(implode(',', saas_tenant_modules($key)));   // exact modules bought (à la carte)
    if (!empty($route['sqlite'])) {
        $env .= ' SAAS_SQLITE=' . escapeshellarg((string) $route['sqlite']);
    } elseif (!empty($route['db']) && is_array($route['db'])) {
        $d = $route['db'];
        $env .= ' DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) ($d['host'] ?? 'localhost'))
              . ' DB_NAME=' . escapeshellarg((string) ($d['name'] ?? '')) . ' DB_USER=' . escapeshellarg((string) ($d['user'] ?? ''))
              . ' DB_PASS=' . escapeshellarg((string) ($d['pass'] ?? ''));
    } else { return false; }
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    if (!function_exists('exec') || in_array('exec', $disabled, true)) return false;
    $out = []; $code = 1;
    @exec($env . ' ' . escapeshellarg($php) . ' ' . escapeshellarg($cli) . ' 2>&1', $out, $code);
    return $code === 0;
}

function ops_saas_admin($route, $method) {
    ops_require(function_exists('superadmin_can') ? superadmin_can() : (function_exists('is_master') && is_master()),
        'Only the Super Admin can open the Companies console.');

    if ($method === 'POST') {
        $do  = (string) ($_POST['do'] ?? '');
        $key = strtolower(trim((string) ($_POST['key'] ?? '')));
        $back = '/companies' . ($key !== '' ? '?key=' . urlencode($key) : '');

        // ---- Add a company in one form ------------------------------------
        // Registers routing + directory + login index, then hands off to
        // /company-init (a fresh request) to boot and set up the new company's
        // OWN database. Booting it here would collide with this request's
        // control-DB migrations (process-static guards), so we redirect and let
        // a clean request do it.
        if ($do === 'company_add') {
            $company = trim((string) ($_POST['company'] ?? ''));
            $nkey    = saas_slug($_POST['new_key'] ?? '') ?: saas_slug($company);
            $oname   = trim((string) ($_POST['owner_name'] ?? ''));
            $oemail  = strtolower(trim((string) ($_POST['owner_email'] ?? '')));
            $opass   = (string) ($_POST['owner_pass'] ?? '');
            $plan    = strtoupper((string) ($_POST['new_plan'] ?? 'RECRUITMENT'));
            $dbkind  = (string) ($_POST['db_kind'] ?? 'sqlite');

            if ($company === '' || $oemail === '' || !filter_var($oemail, FILTER_VALIDATE_EMAIL)) {
                flash('A company name and a valid owner email are required.', 'error'); redirect('/companies');
            }
            if ($nkey === '' || (function_exists('tenant_valid_sub') && !tenant_valid_sub($nkey))) {
                flash('The workspace key must be lowercase letters, digits or hyphens.', 'error'); redirect('/companies');
            }
            $reg = function_exists('tenant_registry') ? tenant_registry() : ['tenants' => []];
            if (saas_tenant_get($nkey) || isset($reg['tenants'][$nkey])) {
                flash('That workspace key is already taken — pick another.', 'error'); redirect('/companies');
            }
            if (saas_login_lookup($oemail) !== '') {
                flash('That owner email already belongs to a company.', 'error'); redirect('/companies');
            }
            if ($opass === '') $opass = bin2hex(random_bytes(4));   // a temp password to hand over

            if ($dbkind === 'auto') {
                // One-click on a VPS: the app creates the client's MySQL database
                // itself, using the server's database-admin credential.
                $admin = saas_db_admin_config();
                if (!$admin) {
                    flash('Automatic database creation is not set up on this server. Enter the database details instead, or ask your administrator to add the database-admin credential to config.local.php.', 'error');
                    redirect('/companies');
                }
                try { $db = saas_mysql_provision_db($nkey, $admin); }
                catch (Throwable $e) { flash('Could not create the database automatically: ' . $e->getMessage(), 'error'); redirect('/companies'); }
            } elseif ($dbkind === 'mysql') {
                $db = ['host' => trim((string) ($_POST['db_host'] ?? 'localhost')), 'name' => trim((string) ($_POST['db_name'] ?? '')),
                       'user' => trim((string) ($_POST['db_user'] ?? '')), 'pass' => (string) ($_POST['db_pass'] ?? '')];
                if ($db['name'] === '' || $db['user'] === '') { flash('A MySQL database name and user are required.', 'error'); redirect('/companies'); }
            } else {
                $db = ['sqlite' => dirname(__DIR__) . '/tenant-' . $nkey . '.sqlite'];
            }
            $err = function_exists('tenant_add') ? tenant_add($nkey, $company, $db) : 'Cloud mode is not enabled.';
            if ($err !== '') { flash($err, 'error'); redirect('/companies'); }

            saas_tenant_upsert($nkey, ['company' => $company, 'owner_name' => $oname, 'owner_email' => $oemail, 'plan' => $plan, 'status' => 'active']);
            saas_tenant_set_plan($nkey, $plan);
            saas_login_index_set($oemail, $nkey);

            // Build the company's OWN database in a clean child process (see
            // lib/saas_provision_cli.php for why a separate process).
            $php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
            $cli = __DIR__ . '/saas_provision_cli.php';
            $env = 'SAAS_COMPANY=' . escapeshellarg($company) . ' SAAS_EMAIL=' . escapeshellarg($oemail)
                 . ' SAAS_NAME=' . escapeshellarg($oname) . ' SAAS_PASS=' . escapeshellarg($opass)
                 . ' SAAS_PLAN=' . escapeshellarg($plan)
                 . ' SAAS_SEAT_LIMIT=' . escapeshellarg((string) saas_tenant_seat_limit($nkey));
            if (!empty($db['sqlite'])) {
                $env .= ' SAAS_SQLITE=' . escapeshellarg($db['sqlite']);
            } else {
                $env .= ' DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) ($db['host'] ?? 'localhost')) . ' DB_NAME=' . escapeshellarg((string) $db['name'])
                      . ' DB_USER=' . escapeshellarg((string) $db['user']) . ' DB_PASS=' . escapeshellarg((string) ($db['pass'] ?? ''));
            }
            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            $canExec = function_exists('exec') && !in_array('exec', $disabled, true);
            $out = []; $code = 1;
            if ($canExec) @exec($env . ' ' . escapeshellarg($php) . ' ' . escapeshellarg($cli) . ' 2>&1', $out, $code);

            if ($canExec && $code === 0) {
                flash('Company “' . $company . '” is ready. Its owner signs in at the one product URL with '
                    . $oemail . ' (temporary password: ' . $opass . '). On first sign-in they set their own password '
                    . 'and complete their company onboarding (business profile, financial year, currency).');
            } elseif ($canExec) {
                if (function_exists('tenant_remove')) tenant_remove($nkey);   // roll back a half-made company
                saas_login_index_set($oemail, $nkey, false);
                flash('Could not set up the company database: ' . trim(implode(' ', $out)), 'error');
            } else {
                flash('Company “' . $company . '” registered. This server cannot auto-build databases, so '
                    . $oemail . ' finishes a one-time setup on first login (temporary password: ' . $opass . ').', 'warning');
            }
            redirect('/companies');
        }

        // Apply a directory change to the company's live database, and word the
        // confirmation by whether that push actually took effect.
        $pushed = function ($key, $what) {
            $ok = saas_push_to_tenant($key);
            flash($what . ' updated for ' . $key . ($ok ? ' and applied to their live workspace.'
                : '. (Saved here; it will apply to their workspace on the next sync — automatic pushing is off on this server.)'),
                $ok ? 'success' : 'warning');
        };
        if ($do === 'price_save') {
            $g = fn($k) => max(0, (int) ($_POST[$k] ?? 0));
            if (defined('PRODUCT_MODULES')) foreach (PRODUCT_MODULES as $mk => $mm) {
                if (!empty($mm[3])) continue;   // core module, never priced
                setting_set('saas_price_mod_' . $mk . '_month', (string) $g('price_' . $mk . '_month'));
                setting_set('saas_price_mod_' . $mk . '_year',  (string) $g('price_' . $mk . '_year'));
            }
            if (isset($_POST['seat_month'])) setting_set('billing_price_user_month', (string) $g('seat_month'));
            if (isset($_POST['seat_year']))  setting_set('billing_price_user_year',  (string) $g('seat_year'));
            flash('Price book saved.');
            redirect('/companies');
        }
        if ($do === 'company_plan' && $key !== '') {
            saas_tenant_set_plan($key, (string) ($_POST['plan'] ?? ''), trim((string) ($_POST['plan_expiry'] ?? '')) ?: null);
            $pushed($key, 'Plan');
            redirect($back);
        }
        if ($do === 'company_seats' && $key !== '') {
            // Set an absolute purchased-seat count (over the plan base).
            $want = max(0, (int) ($_POST['extra_user_seats'] ?? 0));
            $t = saas_tenant_get($key);
            $cur = (int) ($t['extra_user_seats'] ?? 0);
            saas_tenant_add_seats($key, $want - $cur);
            $pushed($key, 'Seats');
            redirect($back);
        }
        if ($do === 'company_modules' && $key !== '') {
            $valid = function_exists('licence_owner') && defined('PRODUCT_MODULES') ? array_keys(PRODUCT_MODULES) : ['admin', 'hr'];
            $picked = array_values(array_intersect($valid, (array) ($_POST['mods'] ?? [])));
            if (!in_array('admin', $picked, true)) $picked[] = 'admin';   // admin is core, always on
            saas_tenant_set_modules($key, $picked);
            $pushed($key, 'Modules');
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
    $selRow = $sel !== '' ? saas_tenant_get($sel) : null;
    view('ops/saas_companies', [
        'companies' => saas_console_companies(),
        'plans'     => saas_console_plans(),
        'modules'   => defined('PRODUCT_MODULES') ? PRODUCT_MODULES : [],
        'sel'       => $selRow,
        'price_book' => saas_price_book(),
        'quote'     => $selRow ? saas_company_quote(saas_tenant_modules($sel), saas_tenant_seat_limit($sel), 'month') : null,
        'base_domain' => function_exists('tenant_base_domain') ? tenant_base_domain() : '',
        'can_autocreate' => saas_can_autocreate_db(),
    ]);
    return true;
}

