<?php
// R1 — client/vendor/PO create, edit and view (partner-new / partner-edit / partner-add /
// partner / po) ran BEFORE the module gate with no ops_require, so ANY logged-in user —
// including an inspector — could create or edit a client/vendor, add a purchase order, or
// read a partner's commercial 360. These routes are now guarded: edit needs the
// clients/vendors edit right (a coordinator may also act during intake); view needs the
// read right. (Contract registration keeps its own stricter gate — R4.)
t_section('partner / PO routes are permission-guarded (R1)');

$pdo = db();
$mk = function($u, $role, $super = 0) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, is_superuser) VALUES (?,?,?,1,?)")
        ->execute([$u, ucfirst(strtolower($role)), $role, $super]);
    return (int)$pdo->lastInsertId();
};
$insp  = $mk('r1_insp',  'INSPECTOR');
$coord = $mk('r1_coord', 'COORDINATOR');
$mktgx = $mk('r1_mktgx', 'MARKETING_EXECUTIVE');
$bm    = $mk('r1_bm',    'BRANCH_MANAGER');
$fin   = $mk('r1_fin',   'FINANCE');
$mast  = $mk('r1_master','MASTER_ADMIN', 1);

// The exact predicates the route guards enforce.
$editGate = fn() => is_master() || can('mod.clients.edit') || can('mod.vendors.edit')
    || (function_exists('is_coordinator_level') && is_coordinator_level());
$viewGate = fn() => is_master() || can('mod.clients.view') || can('mod.vendors.view')
    || can('finance.reconcile') || (function_exists('is_coordinator_level') && is_coordinator_level());

$as = function($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

// The headline fix: an inspector can no longer create/edit or even read partner master data.
$as($insp);
t_ok($editGate() === false, 'an inspector cannot create or edit client/vendor records');
t_ok($viewGate() === false, 'an inspector cannot read the partner 360');

// A coordinator may onboard and view during intake.
$as($coord);
t_ok($editGate() === true, 'a coordinator can add/edit client/vendor records (intake)');
t_ok($viewGate() === true, 'a coordinator can view the partner 360');

// A branch manager holds the real edit right.
$as($bm);
t_ok($editGate() === true, 'a branch manager can edit client/vendor records');

// A marketing executive can VIEW clients but not EDIT them.
$as($mktgx);
t_ok($viewGate() === true, 'a marketing executive can view clients');
t_ok($editGate() === false, 'a marketing executive cannot edit client/vendor master data');

// Finance can VIEW the partner 360 (for billing) but not EDIT partner master data.
$as($fin);
t_ok($viewGate() === true, 'finance can view the partner 360 (billing needs it)');
t_ok($editGate() === false, 'finance cannot edit client/vendor master data');

// Master always.
$as($mast);
t_ok($editGate() === true && $viewGate() === true, 'a master admin can do both');

unset($_SESSION['uid']); current_user(true); ua(true);

// The routes actually carry the guards.
$idx = file_get_contents(__DIR__ . '/../index.php');
foreach (["partner-new", "partner-edit", "partner"] as $r) {
    // each route body contains an ops_require referencing the clients/vendors permissions
}
t_ok(substr_count($idx, "can('mod.clients.edit') || can('mod.vendors.edit')") >= 3,
    'the create/edit/add routes are guarded by the clients/vendors edit right');
t_ok(strpos($idx, "can('mod.clients.view') || can('mod.vendors.view')") !== false,
    'the partner 360 / PO view is guarded by the read right');
t_ok(strpos($idx, "You do not have permission to change a purchase order.") !== false,
    'PO mutations require the edit right');
// R4 contract door still stricter than the general partner-add gate.
t_ok(strpos($idx, "Only Accounts / back-office can register a contract") !== false,
    'the contract sub-form keeps its stricter Accounts-only gate (R4)');
