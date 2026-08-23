<?php
// Every permission the code checks with can('...') must exist in the permission
// catalogue — otherwise the gate is a "dead alias" that can never be granted and
// silently falls back to master/admin only. This catches the class of bug we just
// fixed (jobs.view, salary.view, partners.manage, …) before it can recur.
t_section('no code checks a permission that is not in the catalogue');

$defined = array_keys(all_permissions());
// Known, deliberately-outside-the-catalogue keys:
//  - deputation.approve / issues: CLIENT-PORTAL permissions, checked via a portal
//    closure ($can(...)), not the global catalogue;
//  - reports.decide: flagged for separate review, not remapped yet;
//  - mod.x.view: a documentation example inside a code comment.
$allow = ['deputation.approve', 'reports.decide', 'issues', 'mod.x.view'];

// Only the GLOBAL can('...') — not $can()/pcan()/->can(), which are portal or
// object checks with their own permission sets.
$rx = "/(?<![\\w$>])can\\('([a-z][a-z0-9._]+)'\\)/";
$used = [];
$scan = function ($f) use (&$used, $rx) {
    $src = file_get_contents($f);
    if (preg_match_all($rx, $src, $m)) foreach ($m[1] as $k) $used[$k][] = basename($f);
};
foreach (glob(__DIR__ . "/../lib/*.php") ?: [] as $f) $scan($f);
foreach (glob(__DIR__ . "/../views/*.php") ?: [] as $f) $scan($f);
foreach (glob(__DIR__ . "/../views/**/*.php") ?: [] as $f) $scan($f);

$dead = [];
foreach ($used as $k => $files) {
    if (in_array($k, $defined, true) || in_array($k, $allow, true)) continue;
    $dead[] = $k . ' (' . implode(', ', array_unique($files)) . ')';
}
t_ok(count($used) > 30, 'the scan found the permission checks in the code');
t_ok($dead === [], 'no can() checks a permission missing from the catalogue' . ($dead ? ' — dead: ' . implode('; ', $dead) : ''));

// The specific ones we remapped now resolve to real catalogue permissions.
foreach (['ops.job.close','mod.jobs.edit','mod.jobs.view','data.salary','master.manage'] as $k)
    t_ok(in_array($k, $defined, true), "the remap target $k is a real permission");
