<?php
// ---------------------------------------------------------------------------
//  GATE 6B — seed the R1-UI / R2 browser scenario.
//
//  Three people, one per operational status, in the signed-in administrator's own
//  office, plus one ORDINARY coordinator login so the permission boundary on the
//  configuration screen can be driven by a real refused request rather than
//  asserted from a function name.
//
//    php tools/g6b-seed.php
//
//  Never touches a real database: the browser runner points this at a throwaway
//  SQLite file it created seconds earlier.
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

$admin = ops_one("SELECT * FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1");
if (!$admin) { fwrite(STDERR, "g6b-seed: no active administrator\n"); exit(2); }
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
$a = $mk('Activo', WF_ST_ACTIVE);
$j = $mk('Joiner', WF_ST_JOINING);
$l = $mk('Leaver', WF_ST_INACTIVE);

//  AN ORDINARY USER — a coordinator, not an administrator and with no hiring
//  permission. The configuration screen must refuse them.
$plainUser = strtolower($tag) . '_coord';
$plainPass = 'plain12345';
$plainId = 0;
try {
    db()->prepare("INSERT INTO users (username,password_hash,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,?,?, 'COORDINATOR',1,0,?,'')")
        ->execute([$plainUser, password_hash($plainPass, PASSWORD_DEFAULT), 'Coordinator', $off]);
    $plainId = (int) db()->lastInsertId();
    try { db()->prepare("UPDATE users SET must_change_pwd=0, pwd_changed_at=? WHERE id=?")->execute([date('c'), $plainId]); }
    catch (Throwable $e) {}
} catch (Throwable $e) { fwrite(STDERR, 'g6b-seed: could not create the ordinary user: ' . $e->getMessage() . "\n"); }

//  WHAT THE SCREENS MUST SAY, computed with this file's OWN queries and never
//  through the helpers under test. A seed that calls the code a mutation breaks
//  aborts under that mutation, no assertion runs, and the mutation is recorded as
//  "killed" by an aborted run rather than by any assertion. That is not a kill,
//  and it is why the count below is its own SQL.
$pend = (int) ops_val(
    "SELECT COUNT(*) FROM inspectors
      WHERE UPPER(TRIM(COALESCE(status,'')))=? AND COALESCE(staff_kind,'ASSET')<>'SUBCON' AND home_office_id=?",
    [WF_ST_JOINING, $off]);
//  The trigger must start from the state a pre-existing organisation is in: no row
//  stored at all, so the screen is showing the DEFAULT rather than something seeded.
try { db()->prepare("DELETE FROM settings WHERE skey=?")->execute(['crev_trigger_redefined']); } catch (Throwable $e) {}
try { db()->prepare("DELETE FROM settings WHERE skey=?")->execute(['crev_trigger_stricter']); } catch (Throwable $e) {}

echo 'TAG=' . $tag . ' OFFICE=' . $off . ' ACTIVE=' . $a . ' JOINING=' . $j . ' LEFT=' . $l
   . ' PENDCOUNT=' . $pend . ' PLAINUSER=' . $plainUser . ' PLAINPASS=' . $plainPass
   . ' PLAINID=' . $plainId . "\n";
