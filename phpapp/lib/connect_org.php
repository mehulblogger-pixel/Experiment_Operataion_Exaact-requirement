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

/** The public onboarding page (dispatched before require_login). Always exits. */
function connect_org_join_route($method) {
    if (function_exists('connect_enabled') && !connect_enabled()) { http_response_code(404); echo 'Not available.'; exit; }
    connect_org_migrate();
    $done = false; $err = '';
    if ($method === 'POST') {
        $id = connect_org_apply($_POST);
        if ($id > 0) $done = true; else $err = 'Please give your organisation a name, a type, and a valid e-mail.';
    }
    $GLOBALS['__join_done'] = $done; $GLOBALS['__join_err'] = $err;
    $GLOBALS['__join_types'] = connect_org_types();
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
