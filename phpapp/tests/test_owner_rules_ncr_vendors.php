<?php
// ============================================================================
//  The owner's rules of 19 Oct 2026.
//    Q-B1  "Coordinator, Manager, Branch Manager or application manager -- in
//           short all equal to coordinator and above -- can record" a
//           nonconformity. Recording only: working and closing stay with quality.
//    Q-G   Finance sees the vendor register.
//  Both reach a role nobody customised (the defaults) AND an access grid an
//  administrator already saved (a one-time additive grant).  (t_eq is t_eq($got,$want).)
// ============================================================================
t_section('owner rules 2026-10-19 — who records a nonconformity; Finance sees vendors');
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$prevUid = $_SESSION['uid'] ?? null;
$ra0 = setting_get('role_access', ''); $ap0 = setting_get('access_owner_grants_applied', '');
try {
    // ---- the role defaults ---------------------------------------------------------
    $recorders = ['BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER','BRANCH_APP_MANAGER','OPERATION_MANAGER','ASST_MANAGER','COORDINATOR'];
    foreach ($recorders as $r) {
        $p = role_defaults($r)['perms'];
        t_ok(in_array('mod.ncr.add', $p, true) && in_array('mod.ncr.view', $p, true), "$r may record a nonconformity (and so read the register)");
        t_ok(!in_array('ncr.close', $p, true), "$r may not close one — closing stays with quality");
    }
    foreach (['INSPECTOR','SR_INSPECTOR','FINANCE','BUSINESS_DEV_MANAGER','MARKETING_EXECUTIVE'] as $r)
        t_ok(!in_array('mod.ncr.add', role_defaults($r)['perms'], true), "$r is below coordinator level and is not given it");
    t_ok(in_array('mod.ncr.edit', role_defaults('QUALITY_HEAD')['perms'], true) && in_array('ncr.close', role_defaults('QUALITY_HEAD')['perms'], true), 'the Quality Head still works and closes nonconformities');
    t_ok(in_array('mod.vendors.view', role_defaults('FINANCE')['perms'], true), 'Finance reads the vendor register');
    t_ok(!in_array('mod.vendors.edit', role_defaults('FINANCE')['perms'], true), '...read only');

    // ---- the right as the screens ask it ----------------------------------------------
    $as = function ($role) {
        db()->prepare("INSERT INTO users (username,first_name,email,role,is_superuser,is_active) VALUES (?,?,?,?,0,1)")
            ->execute(['t.or.' . strtolower($role) . '.' . mt_rand(), $role, strtolower($role) . mt_rand() . '@t.test', $role]);
        $_SESSION['uid'] = (int)db()->lastInsertId(); current_user(true); ua(true);
    };
    $as('COORDINATOR');
    t_ok(ncr_can_record(), 'a coordinator may record a nonconformity');
    t_ok(!ncr_can_raise(), '...but may not contain, dispose, assign or open the corrective action');
    t_ok(!ncr_can_close(), '...and may not close it');
    $as('INSPECTOR');
    t_ok(!ncr_can_record(), 'an inspector may not record one');
    $as('FINANCE');
    t_ok(can('mod.vendors.view') && !can('mod.vendors.edit'), 'a finance login opens the vendor register, read only');

    // ---- a grid an administrator already SAVED gets the rule once ------------------------------
    setting_set('role_access', json_encode([
        'COORDINATOR' => ['dash.operations', 'mod.calls.view'],
        'FINANCE'     => ['dash.financial', 'mod.invoicing.view'],
        'INSPECTOR'   => ['mod.idems.view'],
    ]));
    setting_set('access_owner_grants_applied', '');
    db()->prepare("INSERT INTO users (username,first_name,email,role,is_superuser,is_active,permissions) VALUES ('t.or.custom','Custom','custom.or@t.test','OPERATION_MANAGER',0,1,'mod.jobs.view,mod.calls.view')")->execute();
    $cu = (int)db()->lastInsertId();
    $done = access_apply_owner_grants(true);
    t_eq($done, ['Q-B1-2026-10-19', 'Q-G-2026-10-19'], 'both rules are applied to the saved access');
    $st = json_decode(setting_get('role_access', ''), true);
    t_ok(in_array('mod.ncr.add', $st['COORDINATOR'], true), 'the saved Coordinator grid now records nonconformities');
    t_ok(in_array('mod.vendors.view', $st['FINANCE'], true), 'the saved Finance grid now reads vendors');
    t_eq($st['INSPECTOR'], ['mod.idems.view'], 'the saved Inspector grid is untouched');
    t_ok(in_array('dash.operations', $st['COORDINATOR'], true), 'nothing the administrator saved was removed');
    $perm = (string)ops_val("SELECT permissions FROM users WHERE id=?", [$cu]);
    t_ok(strpos($perm, 'mod.ncr.add') !== false && strpos($perm, 'mod.jobs.view') !== false, 'a per-user custom set of an Operation Manager gains it too, keeping its own rights');

    // an administrator then unticks it on purpose: it must stay unticked
    $st['COORDINATOR'] = array_values(array_diff($st['COORDINATOR'], ['mod.ncr.add', 'mod.ncr.view']));
    setting_set('role_access', json_encode($st));
    t_eq(access_apply_owner_grants(true), [], 'a rule already applied is not applied again');
    t_ok(!in_array('mod.ncr.add', json_decode(setting_get('role_access', ''), true)['COORDINATOR'], true), '...so an administrator who unticks it on purpose is not overruled');

    // ---- the screens --------------------------------------------------------------
    $ncr = file_get_contents(__DIR__ . '/../lib/ncr.php');
    $a = strpos($ncr, "if (\$route === 'ncr-new')"); $g1 = strpos($ncr, 'ops_require(ncr_can_record()', $a);
    $g2 = strpos($ncr, 'ops_require(ncr_can_raise()', $a); $w = strpos($ncr, "\$route === 'ncr-contain'", $a);
    t_ok($a !== false && $g1 > $a && $g2 > $g1 && $g2 < $w, 'recording needs the record right; every working route after it still needs the work right');
    foreach (['views/ops/job_detail.php', 'views/ops/complaint_detail.php'] as $v)
        t_ok(strpos(file_get_contents(__DIR__ . '/../' . $v), 'ncr_can_record()') !== false, "$v offers \"Raise a nonconformity\" to those who may record one");
} finally {
    setting_set('role_access', $ra0); setting_set('access_owner_grants_applied', $ap0);
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    current_user(true); ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}
