<?php
// Module 36 — Licensing / SaaS Admin. lk_state()/lk_summary() already compute the licence state,
// days-left and seat pressure, but nothing surfaced them ambiently — a GRACE-state install got no
// warning until it hit read-only. licence_health() normalizes that into one attention verdict that a
// dashboard banner and a weekly cron reminder both read. Also: the super-admin health KPI keyed on
// state names lk_state() never emits (ACTIVE/EXPIRED/READ_ONLY), so a healthy licence never showed
// green — corrected to the real names. Advisory only; nothing here blocks.
t_section('Module 36 — licence health surfaced + super-admin state mapping fixed');

t_ok(function_exists('licence_health'),        'licence_health() exists');
t_ok(function_exists('licence_run_reminders'), 'licence_run_reminders() exists');

// The verdict has the fields the banner and reminder consume.
$h = licence_health();
foreach (['needs_attention','severity','state','headline','detail','url','days_left','used','seats'] as $k)
    t_ok(array_key_exists($k, $h), "the health verdict has '$k'");
t_ok(in_array($h['severity'], ['ok','warn','bad'], true), 'severity is one of ok/warn/bad');
t_ok(is_bool($h['needs_attention']), 'needs_attention is a boolean');
t_ok($h['needs_attention'] === ($h['severity'] !== 'ok'), 'needs_attention is true exactly when severity is not ok');

// The default dev install is OPEN (unlicensed, everything on) — nothing to chase.
t_ok($h['state'] === 'OPEN' ? ($h['needs_attention'] === false) : true, 'an OPEN (unlicensed) install needs no attention');

// The weekly reminder is a no-op when the licence is healthy (OPEN) and returns 0.
if ($h['state'] === 'OPEN') t_eq(licence_run_reminders(), 0, 'no reminder is sent for a healthy/open licence');

// ---- the super-admin state-tone mapping is corrected (Gap G) ----
$sa = file_get_contents(__DIR__ . '/../views/ops/super_admin.php');
t_ok(strpos($sa, "'VALID'=>'p-ok'") !== false,     'the super-admin panel maps the real VALID state to green');
t_ok(strpos($sa, "'READONLY'=>'p-bad'") !== false, 'the real READONLY state maps to red');
t_ok(strpos($sa, "'ACTIVE'=>'p-ok'") === false,    'the never-emitted ACTIVE state name is gone');
t_ok(strpos($sa, "READ_ONLY") === false,           'the never-emitted READ_ONLY state name is gone');
t_ok(strpos($sa, "==='VALID' ? 'ok'") !== false,   'the health KPI class keys on VALID, not ACTIVE');

// ---- the dashboard banner + cron are wired ----
$dash = file_get_contents(__DIR__ . '/../views/dashboard.php');
$cron = file_get_contents(__DIR__ . '/../cron.php');
t_ok(strpos($dash, 'licence_health()') !== false && strpos($dash, 'lk_can_manage()') !== false,
    'the home dashboard shows the licence banner to admins only');
t_ok(strpos($cron, 'licence_run_reminders()') !== false && strpos($cron, 'licence_reminder_week') !== false,
    'the weekly licence reminder is wired into cron with a per-week guard');

// ---- the cron week-marker is not audit noise (Module 14 skip list extended) ----
t_ok(!setting_change_class('licence_reminder_week')['audit'], 'a *_week cron marker is not written to the audit chain');
t_ok(!setting_change_class('audit_reminder_week')['audit'],   'the existing audit_reminder_week marker is also skipped');
t_ok(!setting_change_class('mis_last_weekly')['audit'],       'a *_last_weekly cron marker is skipped');
// A real setting is still audited.
t_ok(setting_change_class('pwd_min_len')['audit'], 'a genuine setting is still audited (skip list not over-broad)');
