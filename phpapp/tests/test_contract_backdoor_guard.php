<?php
// A contract is registered by Accounts/back-office from a WON quotation — the CRM
// "register contract" path already gates that on crm.contract.register (crm.php).
// The partner screen carried a SECOND door: partner-add?kind=contract created a
// partner_contracts row with no permission check at all, so a salesperson,
// coordinator or even an inspector could register a contract off-book, bypassing
// the won-quote → Finance handoff. That door is now gated by the same permission.
t_section('the partner-screen contract door is gated to Accounts (no off-book contracts)');

$src = file_get_contents(__DIR__ . '/../index.php');

// The guard exists in the partner-add route, and uses the same permission as the CRM path.
t_ok(strpos($src, "if (\$kind === 'contract') {") !== false, 'partner-add branches on the contract kind');
t_ok(preg_match("/if \(\\\$kind === 'contract'\) \{\s*ops_require\(can\('crm\.contract\.register'\) \|\| is_master\(\)/s", $src) === 1,
    'the contract door is guarded by crm.contract.register (or master)');

// The guard runs BEFORE the row is inserted (so denial happens before any write).
$guardPos  = strpos($src, "if (\$kind === 'contract') {");
$insertPos = strpos($src, 'INSERT INTO $table');
t_ok($guardPos !== false && $insertPos !== false && $guardPos < $insertPos,
    'the permission check precedes the contract INSERT');

// The permission logic per role: only Finance / master may register a contract;
// sales, coordinator and inspector may not. (Same gate the route enforces.)
$pdo = db();
$gate = fn() => can('crm.contract.register') || is_master();

$mk = function($u, $role, $super = 0) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, is_superuser) VALUES (?,?,?,1,?)")
        ->execute([$u, ucfirst($role), $role, $super]);
    return (int)$pdo->lastInsertId();
};
$fin   = $mk('cbg_fin',   'FINANCE');
$sales = $mk('cbg_sales', 'BUSINESS_DEV_MANAGER');
$mkt   = $mk('cbg_mkt',   'MARKETING_EXECUTIVE');
$coord = $mk('cbg_coord', 'COORDINATOR');
$insp  = $mk('cbg_insp',  'INSPECTOR');
$mast  = $mk('cbg_master','MASTER_ADMIN', 1);

foreach ([
    [$fin,   true,  'Finance may register a contract'],
    [$mast,  true,  'a master admin may register a contract'],
    [$sales, false, 'a sales / BD manager may NOT register a contract (owns only till the quote is won)'],
    [$mkt,   false, 'a marketing executive may NOT register a contract'],
    [$coord, false, 'a coordinator may NOT register a contract'],
    [$insp,  false, 'an inspector may NOT register a contract'],
] as [$uid, $expect, $label]) {
    $_SESSION['uid'] = $uid; current_user(true); ua(true);
    t_ok($gate() === $expect, $label);
}

unset($_SESSION['uid']); current_user(true); ua(true);
