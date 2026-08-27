<?php
// Phase 3 §34 — the role-aware "at a glance" strip. Above the plain area-landing tiles there was no live
// state. dashboard_glance() composes what the viewer may see — their own next actions (§19), and, for a
// manager, a compact pulse (attention count, money outstanding, platform health) — reusing
// action_centre / attention_summary / financial_rollup / system_status_worst. Computes nothing new;
// role-aware. Self-contained.
t_section('Phase 3 §34 — role-aware dashboard "at a glance" strip');

t_ok(function_exists('dashboard_glance'), 'the glance aggregator exists');
$view = file_get_contents(__DIR__ . '/../views/ops/area_home.php');
t_ok(strpos($view, 'dashboard_glance()') !== false, 'the area landing renders the glance strip');
t_ok(strpos($view, 'Your next actions') !== false, 'the strip shows the personal next-actions band');
t_ok(strpos($view, '/command-centre') !== false, 'the management pulse links to the Command Centre');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$prevUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    tasks_migrate();
    $pdo = db();

    // --- a plain inspector: sees their own actions, but NO management pulse ---
    $pdo->prepare("INSERT INTO users (username, role, is_active, home_office_id) VALUES ('g34_insp','INSPECTOR',1,1)")->execute();
    $insp = (int)$pdo->lastInsertId();
    $_SESSION['uid'] = $insp; current_user(true); if (function_exists('ua')) ua(true);
    task_create('Chase the cert', ['due_on' => date('Y-m-d', strtotime('-1 day'))]);

    $gi = dashboard_glance();
    t_ok(count($gi['actions']) >= 1, 'the inspector sees their own next actions');
    t_ok($gi['mgmt'] === null, 'a non-manager sees NO management pulse (role-aware)');

    // --- a master: sees the management pulse (attention / outstanding / health) ---
    $pdo->prepare("INSERT INTO users (username, is_superuser, is_active) VALUES ('g34_boss',1,1)")->execute();
    $boss = (int)$pdo->lastInsertId();
    $_SESSION['uid'] = $boss; current_user(true); if (function_exists('ua')) ua(true);

    $gm = dashboard_glance();
    t_ok(is_array($gm['mgmt']), 'a manager sees the management pulse');
    t_ok(array_key_exists('attention', $gm['mgmt']) && array_key_exists('health', $gm['mgmt']), 'the pulse carries attention + health');
    t_ok($gm['mgmt']['outstanding'] !== null, 'a finance-capable manager sees money outstanding');
    t_ok(in_array($gm['mgmt']['health'], ['ok', 'warn', 'bad'], true), 'the platform health severity is carried');

    // The actions are the §19 aggregator, capped for a glance (<= 4).
    t_ok(count($gm['actions']) <= 4, 'the glance caps the action list (top few)');
} finally {
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}
