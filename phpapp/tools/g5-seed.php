<?php
// ---------------------------------------------------------------------------
//  GATE 5 — seed the browser scenario.
//
//  Builds what a recruiter and a coordinator actually meet: an accepted hire who
//  has a team record but has NOT joined, so the browser can prove that the person
//  is visible for follow-up and absent from the availability board, and then watch
//  both facts change when the joining is recorded.
//
//    php tools/g5-seed.php
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

appr_migrate(); hreq_migrate(); recruitpipe_migrate(); recruit_offer_migrate();
$admin = ops_one("SELECT * FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1");
if (!$admin) { fwrite(STDERR, "g5-seed: no active administrator to act as\n"); exit(2); }
$_SESSION['uid'] = (int) $admin['id']; current_user(true); ua(true);
$off = (int) ops_val("SELECT COALESCE(home_office_id,0) FROM users WHERE id=?", [(int) $admin['id']]);
if ($off <= 0) $off = (int) ops_val("SELECT MIN(id) FROM offices");

$tag = 'G5B' . random_int(100, 999);
db()->prepare("INSERT INTO requisitions (req_code,designation,office_id,sbu,status,quantity,created_at)
               VALUES (?,?,?,'IND','OPEN',3,?)")->execute([$tag . '-RQ', 'ENGINEER', $off, date('c')]);
$rq = (int) db()->lastInsertId();
db()->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,mobile,email,created_at)
               VALUES (?,?,?,'ACCEPTED',?,?,?,?)")
    ->execute([$tag . '-C', 'Priya', 'Joiner', $rq, '9' . random_int(100000000, 999999999),
               strtolower($tag) . '@x.test', date('c')]);
$c = (int) db()->lastInsertId();

$r = rcv_convert($c, ['actor_id' => (int) $admin['id'], 'team_role' => 'FIELD']);
if (empty($r['ok'])) { fwrite(STDERR, "g5-seed: conversion failed — " . ($r['message'] ?? '') . "\n"); exit(3); }
$ins = (int) $r['inspector_id'];
$st  = (string) ops_val("SELECT COALESCE(status,'') FROM inspectors WHERE id=?", [$ins]);

//  A SECOND person who HAS joined, so the browser can see both states side by side
//  and a wrong badge cannot pass by matching everything.
db()->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,mobile,email,created_at)
               VALUES (?,?,?,'ACCEPTED',?,?,?,?)")
    ->execute([$tag . '-C2', 'Arjun', 'Working', $rq, '9' . random_int(100000000, 999999999),
               strtolower($tag) . '2@x.test', date('c')]);
$c2 = (int) db()->lastInsertId();
$r2 = rcv_convert($c2, ['actor_id' => (int) $admin['id'], 'team_role' => 'FIELD']);
$ins2 = (int) ($r2['inspector_id'] ?? 0);
if ($ins2 > 0) {
    db()->prepare("UPDATE candidates SET joined_at=? WHERE id=?")->execute([date('Y-m-d', strtotime('-30 days')), $c2]);
    wf_join_activate($ins2, 'seeded as already working');
}

echo 'CAND=' . $c . ' INS=' . $ins . ' STATUS=' . $st
   . ' CAND2=' . $c2 . ' INS2=' . $ins2 . ' OFFICE=' . $off . ' TAG=' . $tag . "\n";
