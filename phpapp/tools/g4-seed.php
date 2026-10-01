<?php
// ---------------------------------------------------------------------------
//  GATE 4 — seed the browser scenario.
//
//  Builds what an approver actually meets: an organisation with self-approval OFF,
//  a hiring request raised by one person and waiting, and a second request raised by
//  the superuser so the master exception can be exercised from the screen.
//
//    php tools/g4-seed.php
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

appr_migrate(); hreq_migrate(); rver_migrate();
$admin = ops_one("SELECT * FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1");
if (!$admin) { fwrite(STDERR, "g4-seed: no active administrator to act as\n"); exit(2); }
$_SESSION['uid'] = (int) $admin['id']; current_user(true); ua(true);
$off = (int) ops_val("SELECT COALESCE(home_office_id,0) FROM users WHERE id=?", [(int) $admin['id']]);
if ($off <= 0) $off = (int) ops_val("SELECT MIN(id) FROM offices");

//  A SECOND PERSON, who raises a request and therefore may not decide it.
$uname = 'g4raiser';
$ex = ops_one("SELECT * FROM users WHERE username=?", [$uname]);
if (!$ex) {
    db()->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,?,'MANAGER',1,0,?,'')")->execute([$uname, 'G4 Raiser', $off]);
    $raiser = (int) db()->lastInsertId();
} else { $raiser = (int) $ex['id']; }

//  THE LOCKED DEFAULTS, so the screen shows the control switched on.
setting_set('appr_self_approval', '0');
setting_set('appr_self_master_exception', '0');

$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$mk = function ($title, $byId) use ($off, $eng, $qua) {
    [$ok,, $id] = hreq_save(0, [
        'requested_by_id' => $byId, 'requested_by_name' => 'G4',
        'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
        'job_title' => $title, 'designation' => 'ENGINEER', 'job_description' => 'Gate 4 scenario',
        'quantity' => 4, 'office_id' => $off, 'required_by' => date('Y-m-d', strtotime('+120 days')),
        'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL',
        'reason' => 'Gate 4 scenario.', 'change_reason' => 'seed',
    ]);
    if ($ok) hreq_submit($id);
    return $ok ? (int) $id : 0;
};
//  Raised by the OTHER person: the superuser may decide it (different people).
$hOther = $mk('G4 Raised by somebody else', $raiser);
//  Raised by the superuser themselves: with the exception off, they may NOT decide it.
$hOwn = $mk('G4 Raised by the superuser', (int) $admin['id']);

printf("OTHER=%d OWN=%d RAISER=%d SELF=%s MX=%s\n", $hOther, $hOwn, $raiser,
    setting_get('appr_self_approval', '?'), setting_get('appr_self_master_exception', '?'));
