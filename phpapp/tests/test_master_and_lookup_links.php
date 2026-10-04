<?php
// ============================================================================
//  EVERY MASTER / LOOKUP LINK IN THE APP POINTS SOMEWHERE REAL      (UAT 1.5.1)
// ============================================================================
//  The Add-a-login screen offered "Manage the designation master" and "add it
//  to the SBU master" as /m/designation and /m/sbu. Neither is a master: both
//  lists live in the lookup engine, so both links answered "Not found. That
//  page or record doesn't exist." An administrator following the screen's own
//  advice hit a dead end.
//
//  A link is a promise the screen makes. This file checks every one of that
//  shape against the registry that actually serves it, so the next renamed
//  master breaks a test instead of a user's afternoon.
// ============================================================================
t_section('Every /m/<key> link resolves to a real master');

$files = array_merge(glob(__DIR__ . '/../views/*.php'), glob(__DIR__ . '/../views/*/*.php'),
                     glob(__DIR__ . '/../views/*/*/*.php'), glob(__DIR__ . '/../lib/*.php'));
$scan = function (string $re) use ($files) {
    $hits = [];
    foreach ($files as $f)
        foreach (file($f) as $n => $line)
            if (preg_match_all($re, $line, $m))
                foreach ($m[1] as $k)
                    $hits[$k][] = basename(dirname($f)) . '/' . basename($f) . ':' . ($n + 1);
    ksort($hits); return $hits;
};

$masters = array_keys(ops_masters());
t_ok(count($masters) > 5, 'the master registry loaded (' . count($masters) . ' lists)');

$mLinks = $scan('~/m/([a-z0-9_-]+)~');
t_ok(count($mLinks) > 0, 'found /m/ links to check (' . count($mLinks) . ' distinct keys)');
foreach ($mLinks as $key => $where)
    t_ok(in_array($key, $masters, true),
         "*** /m/$key is a real master — linked from " . $where[0]);

t_section('Every /lookup?key=<key> link resolves to a real list');
$lLinks = $scan('~/lookup\?key=([a-z0-9_]+)~');
t_ok(count($lLinks) > 0, 'found /lookup links to check (' . count($lLinks) . ' distinct keys)');
foreach ($lLinks as $key => $where)
    t_ok(lk_type($key) !== null && lk_type($key) !== false,
         "*** /lookup?key=$key is a real list — linked from " . $where[0]);

t_section('The two lists the Add-a-login screen points at exist');
//  These are the ones UAT 1.5.1 caught. Named explicitly so a future rename
//  cannot quietly drop them from the sweep above.
t_ok((bool)lk_type('designation'), '*** the designation list exists (was linked as /m/designation)');
t_ok((bool)lk_type('sbu'),         '*** the SBU list exists (was linked as /m/sbu)');
t_ok(!in_array('designation', $masters, true), 'designation is a lookup, not a master — the old link could never have worked');
t_ok(!in_array('sbu', $masters, true),         'SBU is a lookup, not a master — the old link could never have worked');
