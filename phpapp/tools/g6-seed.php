<?php
// ---------------------------------------------------------------------------
//  GATE 6 — seed the D2–D5 browser scenario.
//
//  Three people in the signed-in user's own office, one per status, so the
//  availability board, the team register, its filter and its headline counts can
//  all be driven against a scenario whose right answer is known.
//
//    php tools/g6-seed.php
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

$admin = ops_one("SELECT * FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1");
if (!$admin) { fwrite(STDERR, "g6-seed: no active administrator\n"); exit(2); }
$_SESSION['uid'] = (int) $admin['id']; current_user(true); ua(true);
$off = (int) ops_val("SELECT COALESCE(home_office_id,0) FROM users WHERE id=?", [(int) $admin['id']]);
if ($off <= 0) $off = (int) ops_val("SELECT MIN(id) FROM offices");

$tag = 'G6B' . random_int(100, 999);
$mk = function ($name, $status) use ($tag, $off) {
    db()->prepare("INSERT INTO inspectors (name,emp_code,home_office_id,staff_kind,team_role,status,created_at,weekly_working_days)
                   VALUES (?,?,?,'ASSET','FIELD',?,?,5)")
        ->execute([$tag . $name, $tag . '-' . strtoupper(substr($name, 0, 4)), $off, $status, date('c')]);
    return (int) db()->lastInsertId();
};
$a = $mk('Activo',  WF_ST_ACTIVE);
$j = $mk('Joiner',  WF_ST_JOINING);
$l = $mk('Leaver',  WF_ST_INACTIVE);

//  Report what the screens must say, so the browser check asserts a KNOWN answer
//  rather than whatever it happens to find.
//
//  Counted with its OWN query, deliberately NOT through
//  wf_joining_pending_in_offices(): a fixture must never depend on the code it is
//  there to validate. The first version called that helper, so the mutation which
//  breaks it also broke the seed — the browser never ran, and the mutation was
//  recorded as "killed" by an aborted run rather than by any assertion. That is
//  not a kill, and this is why it is no longer possible here.
$pend = (int) ops_val(
    "SELECT COUNT(*) FROM inspectors
      WHERE UPPER(TRIM(COALESCE(status,'')))=? AND COALESCE(staff_kind,'ASSET')<>'SUBCON' AND home_office_id=?",
    [WF_ST_JOINING, $off]);
echo 'TAG=' . $tag . ' OFFICE=' . $off . ' ACTIVE=' . $a . ' JOINING=' . $j . ' LEFT=' . $l
   . ' PENDCOUNT=' . $pend . "\n";
