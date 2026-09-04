<?php
// ============================================================================
//  CONNECT — Organisation accounts & entitlements  (Phase B0, additive)
//
//  The hook for "one universal app for every organisation": an organisation
//  account carries an org_type, and the org_type maps to a module bundle drawn
//  from the EXISTING product packages (TPIA / STAFFING / RECRUITMENT / ENTERPRISE)
//  — so a TPIA org gets the full ops platform and a manpower agency gets the
//  marketplace, all from machinery EXAACT already has.
//
//  B0 is additive: a cx_organisations registry + read helpers + an admin screen.
//  It does NOT change any existing gate yet — wiring per-org gating for external
//  orgs is B2 (needs the topology decision + a security review). This slice makes
//  the org-account model real and provisionable. See docs/connect/04-phase-b-design.md.
// ============================================================================

/** All product-module keys (mirror of licence.php PRODUCT_MODULES order). */
function connect_all_modules() { return ['operations', 'admin', 'sales', 'reporting', 'money', 'hr']; }

/**
 * The organisation types and the package each maps to. 'package' references an
 * existing PRODUCT_PACKAGES key ('' = a portal-only audience, not a full package).
 */
function connect_org_types() {
    return [
        'TPIA'               => ['label' => 'TPIA / inspection body',      'package' => 'TPIA'],
        'MANPOWER_AGENCY'    => ['label' => 'Manpower / staffing agency',  'package' => 'STAFFING'],
        'RECRUITMENT_AGENCY' => ['label' => 'Recruitment agency',          'package' => 'RECRUITMENT'],
        'ENTERPRISE'         => ['label' => 'Enterprise (everything)',      'package' => 'ENTERPRISE'],
        'COMPANY'            => ['label' => 'Company / client (hire only)', 'package' => ''],
        'FREELANCER'         => ['label' => 'Individual freelancer',        'package' => ''],
    ];
}

/**
 * Derive a sensible primary org_type from the business capabilities a company
 * ticked, so a newcomer never has to decode a jargon radio — they just say what
 * they do and we set them up right. Additive: used only when no explicit type is
 * chosen; an explicit choice always wins (full back-compat). Falls back to COMPANY.
 */
function connect_org_type_from_caps(array $codes) {
    $codes = array_values(array_filter(array_map('strval', $codes)));
    if (!$codes) return 'COMPANY';
    $cat = function_exists('connect_cap_catalog') ? connect_cap_catalog() : [];
    $g = [];
    foreach ($codes as $c) if (isset($cat[$c])) $g[$cat[$c]['group']] = true;
    $insp   = isset($g['Inspection & Technical Services']);
    $supply = isset($g['Resource Supply']);
    $recr   = isset($g['Recruitment']);
    $proj   = isset($g['Project Services']);
    if ($supply && ($insp || $recr || $proj)) return 'ENTERPRISE';        // does work AND supplies people
    if ($supply)                              return 'MANPOWER_AGENCY';    // supplies people
    if ($recr && !$insp && !$proj)            return 'RECRUITMENT_AGENCY'; // places people
    if ($insp || $proj)                       return 'TPIA';              // runs operations/inspection
    return 'COMPANY';
}

/**
 * The module bundle an org type is entitled to. Full-package types get
 * (all modules − the package's 'off' list) plus the marketplace ('connect');
 * a company gets the marketplace only; a freelancer gets self-service ('pro').
 */
function connect_org_type_modules($orgType) {
    $types = connect_org_types();
    $t = $types[strtoupper((string)$orgType)] ?? null;
    if (!$t) return [];
    if (strtoupper((string)$orgType) === 'COMPANY')    return ['connect'];
    if (strtoupper((string)$orgType) === 'FREELANCER') return ['pro'];
    $pkg = (string)$t['package'];
    if ($pkg === '') return ['connect'];
    $off = (defined('PRODUCT_PACKAGES') && isset(PRODUCT_PACKAGES[$pkg])) ? (array)(PRODUCT_PACKAGES[$pkg]['off'] ?? []) : [];
    $mods = array_values(array_diff(connect_all_modules(), $off));
    $mods[] = 'connect'; // every organisation participates in the shared marketplace
    return $mods;
}

function connect_org_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_organisations (
        id $pk, name VARCHAR(200) DEFAULT '', org_type VARCHAR(30) DEFAULT 'COMPANY',
        package_key VARCHAR(20) DEFAULT '', party_id INT DEFAULT 0,
        status VARCHAR(16) DEFAULT 'ACTIVE', notes VARCHAR(300) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // B1 — self-service onboarding captures the applying contact. Additive.
    if (function_exists('ensure_column')) {
        ensure_column('cx_organisations', 'contact_name',  "VARCHAR(150) DEFAULT ''");
        ensure_column('cx_organisations', 'contact_email', "VARCHAR(200) DEFAULT ''");
        ensure_column('cx_organisations', 'contact_mobile', "VARCHAR(40) DEFAULT ''");
        ensure_column('cx_organisations', 'approved_by',   "VARCHAR(150) DEFAULT ''");
        ensure_column('cx_organisations', 'approved_at',   "VARCHAR(30) DEFAULT ''");
    }
}

/**
 * B1 — an organisation applies for itself (public onboarding). Lands as PENDING
 * for a platform admin to approve. Returns the id, or 0 on bad input.
 */
function connect_org_apply(array $in) {
    connect_org_migrate();
    $name = trim((string)($in['name'] ?? '')); $orgType = strtoupper((string)($in['org_type'] ?? ''));
    $email = strtolower(trim((string)($in['contact_email'] ?? '')));
    if ($name === '' || !isset(connect_org_types()[$orgType])) return 0;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 0;
    $pkg = connect_org_types()[$orgType]['package'];
    db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,status,contact_name,contact_email,contact_mobile,created_at)
                   VALUES (?,?,?, 'PENDING', ?,?,?,?)")
        ->execute([$name, $orgType, $pkg, trim((string)($in['contact_name'] ?? '')), $email, trim((string)($in['contact_mobile'] ?? '')), date('c')]);
    return (int)db()->lastInsertId();
}

/**
 * B1 (self-service) — an organisation registers ITSELF and gets a WORKING login
 * immediately (auto-approve, verify later). Creates: a business-partner party, an
 * ACTIVE cx_organisations record, and a client-portal login the person can sign
 * in with right away. Returns [ok, msg, ['email'=>.., 'login_url'=>..]].
 *
 *   COMPANY / TPIA / ENTERPRISE  → party is a client (can hire)
 *   MANPOWER_AGENCY / RECRUITMENT_AGENCY → party is a subcontractor (supplies people)
 * Every type gets a marketplace portal login (post work, review vouchers); the
 * full operations workspace for a TPIA/enterprise stays a controlled step.
 */
function connect_org_register(array $in) {
    connect_org_migrate();
    $name    = trim((string)($in['name'] ?? ''));
    $orgType = strtoupper((string)($in['org_type'] ?? ''));
    $email   = strtolower(trim((string)($in['contact_email'] ?? '')));
    $person  = trim((string)($in['contact_name'] ?? '')) ?: $name;
    $pass    = (string)($in['password'] ?? '');
    // Capabilities lead the flow now: a company simply ticks what it does, and we
    // set up the right primary type from that. An explicit org_type still wins (so
    // deep links and the old form keep working); otherwise we derive it, and a bare
    // sign-up with nothing ticked lands as a plain hire-only COMPANY.
    $capsIn = array_values(array_filter(array_map('strval', (array)($in['caps'] ?? []))));
    if (!isset(connect_org_types()[$orgType]) || $orgType === 'FREELANCER') $orgType = connect_org_type_from_caps($capsIn);
    if ($name === '') return [false, 'Please give your organisation a name.', null];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Enter a valid work e-mail.', null];
    if (strlen($pass) < 8) return [false, 'Choose a password of at least 8 characters.', null];
    // A login is one per e-mail across the client-portal world.
    if (function_exists('portal_migrate')) portal_migrate();
    if ((int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(email)=?", [$email]) > 0)
        return [false, 'That e-mail is already registered — sign in instead.', null];

    $isAgency = in_array($orgType, ['MANPOWER_AGENCY', 'RECRUITMENT_AGENCY'], true);
    $now = date('c');

    // 1) Party
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,is_vendor,is_subcontractor,status,created_at) VALUES (?,?,?,?,?, 'ACTIVE',?)")
        ->execute([$name, $name, $isAgency ? 0 : 1, 0, $isAgency ? 1 : 0, $now]);
    $partyId = (int)db()->lastInsertId();

    // 2) ACTIVE organisation (auto-approved — verify later)
    $pkg = connect_org_types()[$orgType]['package'];
    db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,party_id,status,contact_name,contact_email,contact_mobile,approved_by,approved_at,created_at)
                   VALUES (?,?,?,?, 'ACTIVE', ?,?,?, 'self-service', ?, ?)")
        ->execute([$name, $orgType, $pkg, $partyId, $person, $email, trim((string)($in['contact_mobile'] ?? '')), $now, $now]);

    // 3) A working client-portal login (blank perms = full marketplace access)
    db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,created_by,created_at)
                   VALUES (?,?,?,?,1,0,'', 'self-service', ?)")
        ->execute([$partyId, $email, $person, password_hash($pass, PASSWORD_DEFAULT), $now]);

    // 4) Multi-capability onboarding — persist the business capabilities the
    //    company ticked (additive; the single org_type above stays the primary
    //    audience). Optional: none ticked → behaves exactly as before.
    if (function_exists('connect_org_cap_bulk_set')) {
        $caps = array_values(array_filter(array_map('strval', (array)($in['caps'] ?? []))));
        if ($caps) connect_org_cap_bulk_set($partyId, $caps, 'self-service');
    }

    return [true, 'Your account is ready.', ['email' => $email, 'login_url' => $isAgency ? '/portal/login' : '/portal/login?for=hire', 'is_agency' => $isAgency]];
}

/** A platform admin approves a pending organisation → ACTIVE. */
function connect_org_approve($id) {
    connect_org_migrate();
    db()->prepare("UPDATE cx_organisations SET status='ACTIVE', approved_by=?, approved_at=? WHERE id=? AND status='PENDING'")
        ->execute([function_exists('user_name') ? user_name(current_user()) : '', date('c'), (int)$id]);
    return true;
}

function connect_org_pending_count() {
    connect_org_migrate();
    try { return (int)ops_val("SELECT COUNT(*) FROM cx_organisations WHERE status='PENDING'"); } catch (Throwable $e) { return 0; }
}

/** The ONE public front door (dispatched before require_login). One page where a
 *  professional, a company or an agency creates an account or signs in. Always exits. */
function connect_front_route($method) {
    // Public marketplace door — only where the Connect add-on is entitled (both cloud
    // and a licence that bought it). A private operations copy without it has no door.
    if (function_exists('install_marketplace_enabled') ? !install_marketplace_enabled()
        : (function_exists('connect_enabled') && !connect_enabled())) {
        if (function_exists('current_user') && !current_user()) redirect('/login');
        http_response_code(404); echo 'Not available.'; exit;
    }
    require __DIR__ . '/../views/ops/connect_front.php';
    exit;
}

/** The public onboarding page (dispatched before require_login). Always exits. */
function connect_org_join_route($method) {
    if (function_exists('install_marketplace_enabled') ? !install_marketplace_enabled()
        : (function_exists('connect_enabled') && !connect_enabled())) {
        if (function_exists('current_user') && !current_user()) redirect('/login');
        http_response_code(404); echo 'Not available.'; exit;
    }
    connect_org_migrate();
    $done = false; $err = ''; $acct = null;
    if ($method === 'POST') {
        [$ok, $msg, $acct] = connect_org_register($_POST);
        if ($ok) $done = true; else $err = $msg;
    }
    $GLOBALS['__join_done'] = $done; $GLOBALS['__join_err'] = $err; $GLOBALS['__join_acct'] = $acct;
    $GLOBALS['__join_types'] = connect_org_types();
    // Multi-capability onboarding: a company declares the full mix of what it does.
    $GLOBALS['__join_cap_catalog'] = function_exists('connect_cap_catalog') ? connect_cap_catalog() : [];
    $GLOBALS['__join_cap_groups']  = function_exists('connect_cap_groups')  ? connect_cap_groups()  : [];
    $GLOBALS['__join_caps_posted'] = array_map('strval', (array)($_POST['caps'] ?? []));
    require __DIR__ . '/../views/ops/connect_join.php';
    exit;
}

/** Register an organisation. Returns its id, or 0 on bad input. */
function connect_org_add($name, $orgType, array $in = []) {
    connect_org_migrate();
    $name = trim((string)$name); $orgType = strtoupper((string)$orgType);
    if ($name === '' || !isset(connect_org_types()[$orgType])) return 0;
    $pkg = connect_org_types()[$orgType]['package'];
    db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,party_id,status,notes,created_by,created_at)
                   VALUES (?,?,?,?, 'ACTIVE', ?, ?, ?)")
        ->execute([$name, $orgType, $pkg, (int)($in['party_id'] ?? 0), trim((string)($in['notes'] ?? '')),
                   function_exists('user_name') ? user_name(current_user()) : '', date('c')]);
    return (int)db()->lastInsertId();
}

function connect_org_get($id) { connect_org_migrate(); return ops_one("SELECT * FROM cx_organisations WHERE id=?", [(int)$id]) ?: null; }
function connect_org_list() { connect_org_migrate(); return ops_all("SELECT * FROM cx_organisations ORDER BY name, id") ?: []; }

/** Change an organisation's type (and re-derive its package). */
function connect_org_set_type($id, $orgType) {
    connect_org_migrate();
    $orgType = strtoupper((string)$orgType);
    if (!isset(connect_org_types()[$orgType])) return false;
    $pkg = connect_org_types()[$orgType]['package'];
    db()->prepare("UPDATE cx_organisations SET org_type=?, package_key=? WHERE id=?")->execute([$orgType, $pkg, (int)$id]);
    return true;
}

/** Whether an organisation's bundle includes a module (the future gate helper). */
function connect_org_can_module($orgId, $moduleKey) {
    $o = connect_org_get($orgId);
    if (!$o) return false;
    return in_array((string)$moduleKey, connect_org_type_modules((string)$o['org_type']), true);
}

/** Master-only admin screen: register organisations and see their entitlements. */
function ops_connect_orgs($method) {
    ops_require(function_exists('is_master') && is_master(), 'Only a master admin can manage organisations.');
    connect_org_migrate();
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? 'add');
        if ($act === 'add') {
            connect_org_add((string)($_POST['name'] ?? ''), (string)($_POST['org_type'] ?? 'COMPANY'), $_POST)
                ? flash('Organisation registered.') : flash('Give the organisation a name and a type.', 'error');
        } elseif ($act === 'set_type') {
            connect_org_set_type((int)($_POST['id'] ?? 0), (string)($_POST['org_type'] ?? '')) ? flash('Organisation updated.') : flash('Unknown type.', 'error');
        } elseif ($act === 'approve') {
            connect_org_approve((int)($_POST['id'] ?? 0)); flash('Organisation approved.');
        }
        redirect('/connect-orgs');
    }
    view('ops/connect_orgs', ['orgs' => connect_org_list(), 'types' => connect_org_types()]);
    return true;
}
