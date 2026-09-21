<?php
// ============================================================================
//  RB-3 §9 — THE EMPLOYEE NUMBER ACROSS TWO TENANTS, in its own process.
//
//  The locked rule is that an employee number is unique TENANT-WIDE and never
//  re-issued. "Tenant-wide" is the whole claim: EMP-00125 belonging to one
//  customer must say nothing at all about another customer's EMP-00125.
//
//  Tenancy here is STRUCTURAL — one database per tenant, no tenant column — so
//  this cannot be proved by a filter passing. It boots two real, separate
//  tenant databases and asks:
//
//    K1  does the SAME number succeed in tenant B after tenant A holds it?
//    K2  …while a second attempt INSIDE tenant B is still refused, so the
//        allowance is about the tenant boundary and not about the rule
//        being switched off over there?
//
//  It runs in its own process because it switches the live connection.
//
//    php tests/_rb3_tenant_emp.php <A-spec> <B-spec>
//  where a spec is  sqlite:/path/file  or  mysql:dbname
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'rb3-tenant-emp';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$specA = (string)($argv[1] ?? ''); $specB = (string)($argv[2] ?? '');

function tenant_use($spec) {
    [$kind, $where] = explode(':', $spec, 2);
    if ($kind === 'sqlite') { putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $where); }
    else { putenv('DB_DRIVER=mysql'); putenv('DB_NAME=' . $where); }
    db(true); db(); boot();
}
function as_admin() {
    $u = ops_one("SELECT * FROM users WHERE is_superuser=1 ORDER BY id LIMIT 1");
    if ($u) { $_SESSION['uid'] = (int)$u['id']; current_user(true); ua(true); }
    return (int)($u['id'] ?? 0);
}
//  A raw INSERT — no application code in the path, so what answers is the
//  DATABASE backstop and nothing else.
function raw_emp($code, $name) {
    try {
        db()->prepare("INSERT INTO inspectors (name,emp_code,status,created_at) VALUES (?,?,'ACTIVE',?)")
            ->execute([$name, $code, date('c')]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) { return 0; }
}

$CODE = 'RB3-TEN-01';
$out = ['ok' => false, 'steps' => []];
try {
    // ---- tenant A: takes the number ----------------------------------------
    tenant_use($specA); as_admin();
    if (function_exists('emp_code_migrate')) emp_code_migrate();
    $out['steps']['a_guard_on'] = in_array(EMP_CODE_KEY_IX, table_index_names('inspectors'), true);
    $out['steps']['a_first']    = raw_emp($CODE, 'Tenant A Holder') > 0;
    $out['steps']['a_second']   = raw_emp($CODE, 'Tenant A Twin')   > 0;   // must be FALSE

    // ---- tenant B: a DIFFERENT customer, same number ------------------------
    tenant_use($specB); as_admin();
    if (function_exists('emp_code_migrate')) emp_code_migrate();
    $out['steps']['b_guard_on'] = in_array(EMP_CODE_KEY_IX, table_index_names('inspectors'), true);
    $out['steps']['b_first']    = raw_emp($CODE, 'Tenant B Holder') > 0;   // must be TRUE
    $out['steps']['b_second']   = raw_emp($CODE, 'Tenant B Twin')   > 0;   // must be FALSE
    $out['steps']['b_count']    = (int)ops_val(
        "SELECT COUNT(*) FROM inspectors WHERE UPPER(TRIM(COALESCE(emp_code,'')))=?", [$CODE]);

    // ---- back to A: B's hire changed nothing over here -----------------------
    tenant_use($specA); as_admin();
    $out['steps']['a_count'] = (int)ops_val(
        "SELECT COUNT(*) FROM inspectors WHERE UPPER(TRIM(COALESCE(emp_code,'')))=?", [$CODE]);
    $out['steps']['a_holder'] = (string)ops_val(
        "SELECT name FROM inspectors WHERE UPPER(TRIM(COALESCE(emp_code,'')))=? ORDER BY id LIMIT 1", [$CODE]);
    $out['ok'] = true;
} catch (Throwable $e) { $out['error'] = $e->getMessage(); }
echo json_encode($out) . "\n";
