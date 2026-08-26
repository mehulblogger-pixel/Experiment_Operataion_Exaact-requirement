<?php
// Module 02 — Users / Access / Roles. Observability (a permission-change audit on the sealed
// chain, a full effective-access view, and a toxic-combination detector) plus two hard guards
// (only a master mints/changes a master; the last master can't be demoted on the edit form).
t_section('Module 02 — access audit, review surface + guards');

$ops = file_get_contents(__DIR__ . '/../lib/ops.php');

t_ok(function_exists('access_diff'), 'access_diff() exists');
t_ok(function_exists('access_toxic_combos'), 'access_toxic_combos() exists');
t_ok(function_exists('access_effective_all'), 'access_effective_all() exists');

// ---- access_diff: only real authorization changes are reported ----
$base = ['role' => 'COORDINATOR', 'permissions' => '', 'is_superuser' => 0, 'is_active' => 1, 'scope_offices' => '', 'scope_sbus' => ''];
t_ok(access_diff($base, $base) === [], 'a save that changes nothing about access produces an empty diff');
$roleChg = access_diff($base, array_merge($base, ['role' => 'OPERATION_MANAGER']));
t_ok(isset($roleChg['role']) && ($roleChg['granted'] ?? null) !== null, 'a role change is diffed with the granted permissions it brings');
$scopeChg = access_diff($base, array_merge($base, ['scope_offices' => 'ALL']));
t_ok(isset($scopeChg['scope_offices']), 'widening the office scope is captured in the diff');
$masterChg = access_diff($base, array_merge($base, ['is_superuser' => 1]));
t_ok(isset($masterChg['master']), 'promoting to master is captured in the diff');

// ---- toxic combos ----
t_ok(access_toxic_combos(['crm.quote.create', 'crm.quote.approve']) !== [], 'creating AND approving quotations is flagged as a maker/checker conflict');
t_ok(access_toxic_combos(['workforce.report.approve', 'idems.finalize']) !== [], 'approving AND issuing reports is flagged');
t_ok(access_toxic_combos(['users.manage.global']) !== [], 'holding global user management (self-grant) is flagged');
t_ok(access_toxic_combos(['ops.job.close', 'data.credit']) === [], 'an ordinary single-hatted set trips no flag');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();

    // A user holding both sides of a maker/checker pair shows in the effective-access review.
    $pdo->prepare("INSERT INTO users (username, role, is_superuser, is_active, permissions) VALUES ('sodtest','COORDINATOR',0,1,?)")
        ->execute(['crm.quote.create,crm.quote.approve,mod.quotes.view']);
    $rows = access_effective_all();
    $mine = null; foreach ($rows as $r) if ($r['username'] === 'sodtest') $mine = $r;
    t_ok($mine !== null, 'the effective-access review lists the user');
    t_ok(($mine['perm_count'] ?? 0) >= 2, 'the review shows the FULL resolved permission set, not just 8 powers');
    t_ok(!empty($mine['toxic']), 'the review flags the user\'s segregation-of-duties conflict');

    // A master is never listed as an anomaly (holds everything by design).
    $pdo->prepare("INSERT INTO users (username, role, is_superuser, is_active) VALUES ('mastertest','MASTER_ADMIN',1,1)")->execute();
    $rows2 = access_effective_all();
    $m = null; foreach ($rows2 as $r) if ($r['username'] === 'mastertest') $m = $r;
    t_ok($m !== null && $m['toxic'] === [], 'a master is not listed as a toxic-combination anomaly');

    // ---- the audit fires on a real access change ----
    if (function_exists('idems_migrate')) idems_migrate();
    $before = (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='user' AND action='ACCESS_CHANGED'");
    // Simulate what the save handler logs: a diff exists, so an entry is written.
    $u = ops_one("SELECT * FROM users WHERE username='sodtest'");
    $diff = access_diff($u, array_merge((array)$u, ['role' => 'OPERATION_MANAGER', 'permissions' => '']));
    if ($diff) idems_log('user', (int)$u['id'], 'ACCESS_CHANGED', ['field' => $u['username'], 'reason' => 'test', 'new' => $diff]);
    $after = (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='user' AND action='ACCESS_CHANGED'");
    t_ok($after === $before + 1, 'an access change writes one ACCESS_CHANGED entry to the sealed audit chain');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- hard guards present in the save handler (B) ----
t_ok(strpos($ops, 'Only a Master Admin can create or change a Master Admin account') !== false,
     'B1: only a master may mint or change a master (privilege-escalation guard)');
t_ok(strpos($ops, 'This is the last active Master Admin') !== false,
     'B2: the last active master cannot be demoted on the edit form');
t_ok(strpos($ops, 'cannot remove your own user-management access') !== false,
     'B2: a user cannot strip their own user-management access');
// ---- audit wired into both save handlers (A) ----
t_ok(preg_match("/ACCESS_CHANGED'.*access_diff|access_diff.*ACCESS_CHANGED/s", $ops) === 1
     || (strpos($ops, "'ACCESS_CHANGED'") !== false && strpos($ops, 'access_diff($user') !== false),
     'the per-user save logs ACCESS_CHANGED from the resolved diff');
t_ok(strpos($ops, "'ROLE_DEFAULTS_CHANGED'") !== false, 'the role-default editor logs ROLE_DEFAULTS_CHANGED');
