<?php
// ============================================================================
//  RB-3 · THE EMPLOYEE NUMBER — owner decision 1, 2026-09-20
//
//  "An employee number must be permanently unique and never re-issued to
//   another person, even after the employee leaves."
//
//  What this replaces: `inspectors` carried NO index of any kind, and
//  next_emp_code() read the highest code and added one with nothing reserving
//  the answer. Four real processes hiring four DIFFERENT people at one
//  wall-clock microsecond each received EMP01 on MariaDB — four times out of
//  four, every one of them reporting success
//  (P6-BATCH2-PREIMPLEMENTATION-AUDIT-R2.md, findings N1 and N2).
//
//  X1 below is the acceptance test for this step. It failed 4/4 before it.
// ============================================================================
if (empty($GLOBALS['__test_db'])) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }

$rbRoot = dirname(__DIR__);
$rbEnv = function () {
    $e = '';
    foreach (['DB_DRIVER', 'SQLITE_PATH', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
        $v = getenv($k); if ($v !== false && $v !== '') $e .= $k . '=' . escapeshellarg($v) . ' ';
    }
    return $e;
};
// Launch several real processes at once, all aiming at the SAME microsecond.
$rbRace = function (array $specs, $leadMs = 1100) use ($rbRoot, $rbEnv) {
    $target = round(microtime(true) * 1000) + $leadMs; $procs = [];
    foreach ($specs as $s) {
        $cmd = $rbEnv() . 'php ' . escapeshellarg($rbRoot . '/tests/_rb3_worker.php') . ' '
             . escapeshellarg($s[0]) . ' ' . (int)($s[1] ?? 0) . ' ' . escapeshellarg((string)($s[2] ?? '')) . ' '
             . escapeshellarg((string)$target) . ' ' . (int)($s[3] ?? 0) . ' 2>&1';
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$p, $pipes];
    }
    $out = [];
    foreach ($procs as [$p, $pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]); proc_close($p);
        foreach (explode("\n", trim((string)$raw)) as $line) {
            $j = json_decode(trim($line), true); if (is_array($j)) { $out[] = $j; break; }
        }
    }
    return $out;
};
$rbRaw = function ($code, $name = 'RB3 Raw') {          // a raw INSERT, no guard at all
    try {
        db()->prepare("INSERT INTO inspectors (name,emp_code,status,created_at) VALUES (?,?, 'ACTIVE', ?)")
            ->execute([$name, $code, date('c')]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) { return 0; }
};
$rbIndexOn = fn() => in_array(EMP_CODE_KEY_IX, table_index_names('inspectors'), true);
$rbGuardRow = fn() => ops_one("SELECT * FROM schema_guards WHERE guard=?", [EMP_CODE_KEY_IX]);
$rbFindings = fn($kind) => count(array_filter(identity_state_findings(300), fn($f) => $f['kind'] === $kind));

t_as_admin();
emp_code_migrate();

// ---------------------------------------------------------------------------
t_section('RB3 · A — the rule exists, and the DATABASE is the one holding it');
// ---------------------------------------------------------------------------
t_ok(function_exists('emp_code_migrate'), 'A1 · the employee-number rule has an installer');
t_ok(in_array(EMP_CODE_KEY_COL, table_columns_incl_generated('inspectors'), true),
     'A2 · the key column exists on inspectors — and is read with table_xinfo, which lists generated columns');
$gA = $rbGuardRow();
t_ok((bool)$gA, 'A3 · the installation recorded itself in schema_guards, so "it is on" is never assumed');
t_eq((string)($gA['state'] ?? ''), 'OK', 'A4 · …and its recorded state is OK on ' . t_driver()
     . (($gA['state'] ?? '') === 'DIRTY' ? ' — DIRTY: ' . (string)$gA['detail'] : ''));
t_ok($rbIndexOn(), 'A5 · the UNIQUE index is really present (asked of the schema, not of "CREATE did not throw")');

$a6 = $rbRaw('RB3-A6');
t_ok($a6 > 0, 'A6 · a first raw INSERT carrying an employee number is accepted — the probe has a subject');
t_eq($rbRaw('RB3-A6', 'RB3 Raw Twin'), 0,
     'A7 · a SECOND raw INSERT with the same number is REFUSED — no application code in the path at all');

// MySQL ignores TRAILING spaces when comparing strings, so a trailing-space
// probe proves nothing on the authoritative engine. A LEADING space does.
t_eq($rbRaw(' RB3-A6', 'RB3 Raw Leading'), 0,
     'A8 · " RB3-A6" (LEADING space) is refused too — the key normalises, so whitespace cannot smuggle a twin in');
t_eq($rbRaw('rb3-a6', 'RB3 Raw Lower'), 0,
     'A9 · "rb3-a6" is refused — case cannot smuggle a twin in either');

// Decision 1 is about people who have LEFT as much as people who are here.
$a10 = $rbRaw('RB3-GONE', 'RB3 Departed');
t_ok($a10 > 0, 'A10 · a team member is created with a number — the probe has a subject');
db()->prepare("UPDATE inspectors SET status='INACTIVE' WHERE id=?")->execute([$a10]);
t_eq((string)ops_val("SELECT status FROM inspectors WHERE id=?", [$a10]), 'INACTIVE',
     'A11 · …and that person has now left — the probe has the state it is about');
t_eq($rbRaw('RB3-GONE', 'RB3 Newcomer'), 0,
     'A12 · a LEAVER\'s number cannot be re-issued — decision 1 is lifetime, not merely active-employee, uniqueness');

$blank1 = $rbRaw('', 'RB3 Blank One');
$blank2 = $rbRaw('', 'RB3 Blank Two');
t_ok($blank1 > 0 && $blank2 > 0,
     'A13 · two team members with NO number are both accepted — historical rows that never had one stay unconstrained');

// ---------------------------------------------------------------------------
t_section('RB3 · C — the number is CLAIMED, not guessed');
// ---------------------------------------------------------------------------
t_ok(function_exists('emp_code_claim'), 'C1 · there is a claim helper');
$c2a = next_emp_code('ASSET', 0); $c2b = next_emp_code('ASSET', 1);
t_ok($c2a !== '' && $c2b !== '' && $c2a !== $c2b,
     'C2 · the generator can be asked for the NEXT free number, not only the first guess (' . $c2a . ' then ' . $c2b . ')');

//  The RETRY has to be forced, and forcing it is not obvious.
//
//  An earlier version of this group simply let somebody take the number the
//  generator was about to issue, and asserted the next hire got a different one.
//  It passed — and proved nothing, because next_emp_code() re-reads the highest
//  code each time, so it had already moved past the squatter on its own and the
//  retry never ran. Mutant M9 ($tries = 1, i.e. never retry) survived that
//  version untouched. Recorded rather than quietly replaced.
//
//  To reach the retry the squatter must be INVISIBLE to the generator and
//  VISIBLE to the database. A LEADING space does both: ' EMP07' does not match
//  the generator's `emp_code LIKE 'EMP%'` scan, and the key normalises it to
//  EMP07, so the first attempt is refused and the claim must ask for the next.
$c3want  = next_emp_code('ASSET');
$c3block = $rbRaw(' ' . $c3want, 'RB3 Squatter');
t_ok($c3block > 0, 'C3 · a hidden squatter holds " ' . $c3want . '" — the probe has a subject');
t_eq(next_emp_code('ASSET'), $c3want,
     'C3b · the generator STILL offers ' . $c3want . ', because it cannot see the squatter — so the first attempt MUST be refused');
$c4id = 0; $c4threw = '';
try { $c4id = team_member_create('RB3 Claimer', 'FIELD', null, ''); }
catch (Throwable $e) { $c4threw = $e->getMessage(); }       // a give-up must FAIL this test, never kill the run
t_ok($c4id > 0, 'C4 · the hire still succeeds' . ($c4threw !== '' ? ' — instead it gave up: ' . $c4threw : ''));
$c4code = $c4id ? (string)ops_val("SELECT emp_code FROM inspectors WHERE id=?", [$c4id]) : '';
t_ok($c4code !== '' && strtoupper(trim($c4code)) !== strtoupper(trim($c3want)),
     'C5 · …with a DIFFERENT number (' . ($c4code ?: 'none') . ', not the refused ' . $c3want
     . ') — the claim was REFUSED once and asked again');

// Decision 5 — a rolled-back acceptance must consume no number.
$c6before = next_emp_code('ASSET');
db()->beginTransaction();
//  Guarded for the same reason C4 is: a claim that gives up THROWS, and an
//  unprotected call here would kill the whole run before the result line — which
//  a mutation harness reads as FATAL, and FATAL is not a catch. A test must be
//  able to FAIL, not only to die.
$c6id = 0;
try { $c6id = team_member_create('RB3 Rolled Back', 'FIELD', null, ''); } catch (Throwable $e) { $c6id = 0; }
t_ok($c6id > 0, 'C6 · a team member is created inside a transaction — the probe has a subject');
try { db()->rollBack(); } catch (Throwable $e) {}
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$c6id ?: -1]), 0, 'C7 · the rollback really removed it');
t_eq(next_emp_code('ASSET'), $c6before,
     'C8 · …and the number it had is free again — a refused acceptance consumes NO employee number (decision 5)');

// A duplicate on somebody ELSE'S key must never be retried away.
t_ok(emp_code_is_taken(new RuntimeException('Duplicate entry \'X\' for key \'' . EMP_CODE_KEY_IX . '\'')),
     'C9 · our own key is recognised');
t_ok(!emp_code_is_taken(new RuntimeException('Duplicate entry \'X\' for key \'ux_cx_idlink_insp\'')),
     'C10 · a duplicate on the identity ledger\'s key is NOT ours — it is re-thrown, never retried');

// ---------------------------------------------------------------------------
t_section('RB3 · X1 — THE ACCEPTANCE TEST: four people hired at one microsecond');
// ---------------------------------------------------------------------------
$off = (int)ops_val("SELECT id FROM offices ORDER BY id LIMIT 1");
$me  = (int)(current_user()['id'] ?? 0);
db()->prepare("UPDATE users SET home_office_id=? WHERE id=?")->execute([$off, $me]);
$x1ids = [];
for ($i = 1; $i <= 4; $i++) {
    db()->prepare("INSERT INTO candidates (first_name,last_name,email,mobile,stage,sbu,created_at) VALUES (?,?,?,?, 'ACCEPTED','IND',?)")
        ->execute(["RB3Race$i", 'Person', "rb3race$i@p.test", '9700000' . $i, date('c')]);
    $x1ids[] = (int)db()->lastInsertId();
}
t_eq(count($x1ids), 4, 'X1a · four separate applications exist — the race has four different people to hire');
$x1res = $rbRace(array_map(fn($id) => ['convert', $id, '', $me], $x1ids));
$x1codes = [];
foreach ($x1ids as $id) {
    $c = (string)ops_val("SELECT i.emp_code FROM inspectors i JOIN candidates c ON c.inspector_id=i.id WHERE c.id=?", [$id]);
    if (trim($c) !== '') $x1codes[] = strtoupper(trim($c));
}
$x1won = count($x1codes);
$x1outcomes = implode(' ', array_map(fn($r) => (string)($r['code'] ?? '?'), $x1res));

//  HOW MANY of the four can actually be in flight at once is an ENGINE fact, and
//  pretending otherwise is how a test passes while measuring nothing.
//
//  SQLite takes a single database-wide write lock and sets no busy timeout, so
//  the second, third and fourth writers are refused outright with
//  "database is locked" — they never reach the employee number at all. One
//  winner and three refusals is SQLite behaving as SQLite behaves; it is not
//  the collision this test exists to catch, and a one-of-one "all distinct"
//  would be a vacuous pass.
//
//  So the race is PROVED on MariaDB, which is the production engine and the
//  authoritative one. On SQLite the same four hires are run one after another,
//  which still proves the generator and the key issue four different numbers —
//  it simply does not prove them under contention, and says so.
if (t_driver() === 'sqlite') {
    t_eq(count(array_unique($x1codes)), $x1won,
         'X1b (sqlite) · of the ' . $x1won . ' hire(s) SQLite let through, every employee number is distinct [' . $x1outcomes . ']');
    $seq = [];
    foreach ($x1ids as $id) {
        if ((int)ops_val("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [$id]) > 0) continue;
        $r = rcv_convert($id, []);
        if (!empty($r['ok'])) $seq[] = strtoupper(trim((string)ops_val("SELECT emp_code FROM inspectors WHERE id=?", [(int)$r['inspector_id']])));
    }
    $all = array_merge($x1codes, $seq);
    t_eq(count($all), 4, 'X1c (sqlite) · all four people were hired once the writers stopped colliding — four numbers to compare');
    t_eq(count(array_unique($all)), 4,
         'X1 (sqlite) · FOUR PEOPLE HIRED GET FOUR DIFFERENT EMPLOYEE NUMBERS [' . implode(' ', $all)
         . '] — the race itself is proved on MariaDB, which is the authoritative engine');
} else {
    t_ok($x1won >= 2, 'X1b · at least two of the four really converted at the same instant — the race actually raced [' . $x1outcomes . ']');
    t_eq($x1won, 4, 'X1c · all four concurrent hires succeeded — four numbers to compare');
    t_eq(count(array_unique($x1codes)), $x1won,
         'X1 · FOUR PEOPLE HIRED AT ONE MICROSECOND GET FOUR DIFFERENT EMPLOYEE NUMBERS [' . implode(' ', $x1codes)
         . '] — this is defect N1, which produced EMP01 four times out of four before this change');
}

// ---------------------------------------------------------------------------
t_section('RB3 · X2 — Batch 2\'s guarantee is still intact');
// ---------------------------------------------------------------------------
db()->prepare("INSERT INTO candidates (first_name,last_name,email,mobile,stage,sbu,created_at) VALUES ('RB3Same','Human','rb3same@p.test','9711111111','ACCEPTED','IND',?)")
    ->execute([date('c')]);
$x2 = (int)db()->lastInsertId();
$x2res = $rbRace(array_fill(0, 4, ['convert', $x2, '', $me]));
$x2won = count(array_filter($x2res, fn($r) => !empty($r['ok'])));
$x2lost = count(array_filter($x2res, fn($r) => ($r['code'] ?? '') === 'RACE_LOST'));
t_eq($x2won, 1, 'X2a · exactly ONE of four simultaneous conversions of one application succeeded');
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE name LIKE 'RB3Same%'"), 1,
     'X2b · exactly one team member exists — no orphan (Batch 2\'s guarantee, unchanged)');
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors i WHERE i.name LIKE 'RB3Same%'
                    AND NOT EXISTS (SELECT 1 FROM candidates c WHERE c.inspector_id=i.id)"), 0,
     'X2c · and it belongs to the application — nothing was created that belongs to nobody');
//  WHICH refusal the three losers get is an engine fact. On MariaDB they lose
//  the conditional UPDATE and are told exactly that. On SQLite they are refused
//  the write lock before they get near it, so they are told the honest generic
//  thing instead. Both are deterministic refusals; only one of them can be
//  RACE_LOST, and asserting RACE_LOST on SQLite would be asserting a fiction.
if (t_driver() === 'sqlite') {
    t_eq(count(array_filter($x2res, fn($r) => empty($r['ok']))), 3,
         'X2d (sqlite) · the other three were all refused, none of them silently succeeding');
} else {
    t_eq($x2lost, 3, 'X2d · the other three were told RACE_LOST — the specific, deterministic refusal');
}

// ---------------------------------------------------------------------------
t_section('RB3 · B — an install that ALREADY carries a collision');
// ---------------------------------------------------------------------------
//  The guard is asked DIRECTLY rather than through emp_code_migrate(), whose
//  epoch marker is already warm: calling the migration here would do nothing at
//  all and this group would measure a no-op while reporting a pass.
try { db()->exec(t_driver() === 'sqlite' ? "DROP INDEX `" . EMP_CODE_KEY_IX . "`" : "DROP INDEX `" . EMP_CODE_KEY_IX . "` ON `inspectors`"); } catch (Throwable $e) {}
t_ok(!$rbIndexOn(), 'B1 · the protection is off, as it is on an install that predates it — the probe has a subject');
$b2a = $rbRaw('RB3-DIRTY', 'RB3 Dirty One');
$b2b = $rbRaw('RB3-DIRTY', 'RB3 Dirty Two');
t_ok($b2a > 0 && $b2b > 0, 'B2 · two team members now share one employee number — the exact state decision 1 forbids');
$b3 = ensure_unique_generated_index('inspectors', EMP_CODE_KEY_COL, EMP_CODE_KEY_EXPR, EMP_CODE_KEY_IX);
t_eq($b3, 'DIRTY', 'B3 · the installer reports DIRTY rather than failing the start-up');
t_ok(!$rbIndexOn(), 'B4 · …and does NOT build the index over data that already breaks the rule');
t_eq((string)($rbGuardRow()['state'] ?? ''), 'DIRTY', 'B5 · the state is recorded where an operator can read it');
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE emp_code='RB3-DIRTY'"), 2,
     'B6 · NOTHING WAS RENUMBERED — renumbering somebody is the historical ambiguity decision 1 exists to prevent');
t_ok($rbFindings('EMP_CODE_SHARED') >= 1, 'B7 · the collision is REPORTED, so it stops being invisible');
t_ok($rbFindings('EMP_CODE_UNPROTECTED') >= 1, 'B8 · …and so is the fact that the rule is not yet switched on here');

//  Put it back the way a person would: give the NEWER record a fresh number,
//  never re-issuing the old one. Then the rule installs itself.
db()->prepare("UPDATE inspectors SET emp_code=? WHERE id=?")->execute(['RB3-DIRTY-FIXED', $b2b]);
$b9 = ensure_unique_generated_index('inspectors', EMP_CODE_KEY_COL, EMP_CODE_KEY_EXPR, EMP_CODE_KEY_IX);
t_eq($b9, 'OK', 'B9 · once a person has resolved it, the rule installs on the next run');
t_ok($rbIndexOn(), 'B10 · …and the index is really there again');
t_eq($rbFindings('EMP_CODE_SHARED'), 0, 'B11 · the warning clears when the thing it warned about is gone');

// ---------------------------------------------------------------------------
t_section('RB3 · X3 — three processes installing the rule at the same instant');
// ---------------------------------------------------------------------------
try { db()->exec(t_driver() === 'sqlite' ? "DROP INDEX `" . EMP_CODE_KEY_IX . "`" : "DROP INDEX `" . EMP_CODE_KEY_IX . "` ON `inspectors`"); } catch (Throwable $e) {}
t_ok(!$rbIndexOn(), 'X3a · the index is absent before the race — the racers have real work to do');
$x3 = $rbRace([['guard'], ['guard'], ['guard']]);
t_eq(count(array_filter($x3, fn($r) => ($r['code'] ?? '') === 'OK')), 3,
     'X3b · all three report OK — none of them fails merely because it lost the ALTER');
t_ok(count(array_filter($x3, fn($r) => ($r['msg'] ?? '') === 'index-was-absent-before-me')) >= 1,
     'X3c · at least one of them genuinely saw the index missing — the race was not three no-ops');
t_ok($rbIndexOn(), 'X3d · exactly one index exists afterwards and the rule is on');

// ---------------------------------------------------------------------------
t_section('RB3 · X7 — a typed employee number that somebody already holds');
// ---------------------------------------------------------------------------
$x7 = $rbRaw('RB3-TYPED', 'RB3 Holder');
t_ok($x7 > 0, 'X7a · somebody holds RB3-TYPED — the probe has a subject');
t_ok(function_exists('emp_code_taken_by'), 'X7b · the form can ask who holds a number');
t_ok(strpos(emp_code_taken_by('RB3-TYPED', 0), 'RB3 Holder') !== false,
     'X7c · …and is told WHO, so the refusal is a sentence rather than a stack trace');
t_ok(strpos(emp_code_taken_by(' rb3-typed ', 0), 'RB3 Holder') !== false,
     'X7d · the question is asked with the same normalising rule the database uses, so the two cannot disagree');
t_eq(emp_code_taken_by('RB3-TYPED', $x7), '', 'X7e · a record does not clash with itself when it is edited');
db()->prepare("UPDATE inspectors SET status='INACTIVE' WHERE id=?")->execute([$x7]);
t_ok(strpos(emp_code_taken_by('RB3-TYPED', 0), 'no longer active') !== false,
     'X7f · a number held by somebody who has LEFT says so — otherwise the refusal looks like a bug');

// ---------------------------------------------------------------------------
t_section('RB3 · J — nothing else moved');
// ---------------------------------------------------------------------------
t_ok((int)ops_val("SELECT COUNT(*) FROM inspectors") > 0, 'J1 · the team-member register is still readable');
t_ok(count(inspectors_list(false)) > 0, 'J2 · the allocate picker still lists people (Operations consumer)');
t_ok(in_array('ux_cx_idlink_insp', table_index_names('cx_identity_link'), true)
  || in_array('ux_cx_idlink_cand', table_index_names('cx_identity_link'), true),
     'J3 · the identity ledger\'s own unique keys are untouched');
t_eq((string)ops_val("SELECT COALESCE(team_role,'') FROM inspectors WHERE id=?", [$c4id]), 'FIELD',
     'J4 · team_role still behaves exactly as before — RB-3 changes the NUMBER, not the classification');

// ---------------------------------------------------------------------------
t_section('RB3 · K — the number is unique per TENANT, not across the world');
// ---------------------------------------------------------------------------
//  Decision 1 says an employee number is unique TENANT-WIDE. The word matters
//  in both directions: one customer may never re-use it, and another customer
//  must be free to use the same number for somebody else entirely. EXAACT gives
//  every tenant its own database, so the second half is not a filter that could
//  be forgotten — it is structural. That is exactly why it is worth proving:
//  a claim nobody tests is a claim nobody notices breaking.
//
//  The probe uses RAW INSERTs on both sides, so what answers is the database
//  backstop rather than any application validation.
$kA = sys_get_temp_dir() . '/rb3_ten_a_' . getmypid() . '.sqlite';
$kB = sys_get_temp_dir() . '/rb3_ten_b_' . getmypid() . '.sqlite';
@unlink($kA); @unlink($kB);
if (t_driver() === 'sqlite') { $kSpecA = 'sqlite:' . $kA; $kSpecB = 'sqlite:' . $kB; }
else {
    $kSpecA = 'mysql:rb3_ten_a_' . getmypid(); $kSpecB = 'mysql:rb3_ten_b_' . getmypid();
    foreach ([$kSpecA, $kSpecB] as $sp) { $n = explode(':', $sp, 2)[1];
        try { db()->exec("DROP DATABASE IF EXISTS `$n`"); db()->exec("CREATE DATABASE `$n`"); } catch (Throwable $e) {} }
}
$kEnv = '';
foreach (['DB_HOST', 'DB_USER', 'DB_PASS'] as $k) { $v = getenv($k); if ($v !== false && $v !== '') $kEnv .= $k . '=' . escapeshellarg($v) . ' '; }
$kRaw = (string)shell_exec($kEnv . 'php ' . escapeshellarg($rbRoot . '/tests/_rb3_tenant_emp.php') . ' '
      . escapeshellarg($kSpecA) . ' ' . escapeshellarg($kSpecB) . ' 2>&1');
$kJ = null; foreach (explode("\n", trim($kRaw)) as $l) { $j = json_decode(trim($l), true); if (is_array($j)) { $kJ = $j; break; } }
if (!is_array($kJ) || empty($kJ['ok'])) {
    t_ok(false, 'K · the two-tenant employee-number probe ran (' . substr(preg_replace('/\s+/', ' ', $kRaw), 0, 200) . ')');
} else {
    $ks = $kJ['steps'];
    //  Arming: the rule must actually be switched on in BOTH tenants, or the
    //  allowance below would only mean the guard was missing over there.
    t_ok(!empty($ks['a_guard_on']), 'K1a · the rule is installed in tenant A — armed');
    t_ok(!empty($ks['b_guard_on']), 'K1b · …and independently in tenant B — armed');
    t_ok(!empty($ks['a_first']),    'K1c · tenant A took the number');
    t_ok(empty($ks['a_second']),    'K1d · …and tenant A cannot hand the same number to a second person');
    t_ok(!empty($ks['b_first']),    'K1 · tenant B may use THE SAME number for somebody else — uniqueness is tenant-wide, not global');
    t_ok(empty($ks['b_second']),    'K2 · …yet a second holder inside tenant B is still refused, so B is protected too, not exempt');
    t_eq((int)$ks['b_count'], 1,    'K2a · exactly one holder in tenant B');
    t_eq((int)$ks['a_count'], 1,    'K3 · tenant A still has exactly one holder — B\'s hire changed nothing here');
    t_eq((string)$ks['a_holder'], 'Tenant A Holder',
         'K3a · …and it is still A\'s own person, by name — no bleed between customers');
}
@unlink($kA); @unlink($kB);
if (t_driver() !== 'sqlite') foreach ([$kSpecA, $kSpecB] as $sp) {
    try { db()->exec("DROP DATABASE IF EXISTS `" . explode(':', $sp, 2)[1] . "`"); } catch (Throwable $e) {}
}
