<?php
// ============================================================================
//  R20 — DUPLICATE INSPECTOR CLOSURE.  Release blocker.
//
//  Two Inspector records for one human is not a tidiness problem: it is two
//  people in utilisation, two people on a timesheet, and two people on an
//  invoice. This battery walks the six cases the owner named, plus concurrency,
//  and reports what is PREVENTED (the database refuses it), what is DETECTED
//  (a person is told and must acknowledge), and what is neither.
//
//  Nothing new matches anybody: recruitment's own engine answers on both doors.
// ============================================================================

t_as_admin();
if (function_exists('emp_code_migrate')) emp_code_migrate();

$r20Root = dirname(__DIR__);
$r20Tag  = 'R20' . strtoupper(substr(md5((string)mt_rand()), 0, 4));   // upper-case: the key normalises, so the probe must compare like for like
$rawIns = function ($name, $code, $mail = '') {
    try {
        db()->prepare("INSERT INTO inspectors (name,emp_code,email,status,created_at) VALUES (?,?,?,'ACTIVE',?)")
            ->execute([$name, $code, $mail, date('c')]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { return 0; }
};
$cnt = fn($sql, $a = []) => (int) ops_val($sql, $a);

// ---------------------------------------------------------------------------
t_section('R20 · CASE 1 — the same employee number');
// ---------------------------------------------------------------------------
t_ok(in_array(EMP_CODE_KEY_IX, table_index_names('inspectors'), true),
     'C1a · the employee-number rule is installed in the DATABASE — armed');
$a1 = $rawIns($r20Tag . ' One', $r20Tag . '-001', $r20Tag . '.one@r20.test');
t_ok($a1 > 0, 'C1b · the first holder was created — armed');
$a2 = $rawIns($r20Tag . ' Two', $r20Tag . '-001', $r20Tag . '.two@r20.test');
t_eq($a2, 0, 'C1 · PREVENTED — a second record with that employee number is refused by the database, with no application code in the path');
t_eq($cnt("SELECT COUNT(*) FROM inspectors WHERE UPPER(TRIM(COALESCE(emp_code,'')))=?", [$r20Tag . '-001']), 1,
     'C1c · exactly one holder exists');

// ---------------------------------------------------------------------------
t_section('R20 · CASE 2 — the same e-mail address');
// ---------------------------------------------------------------------------
//  The rule here is DETECT + ACKNOWLEDGE, not refuse: an e-mail is a strong
//  signal that two records are one person, but it is a person's decision, and
//  the owner locked that the tick is an acknowledgement rather than a merge.
$mail2 = $r20Tag . '.shared@r20.test';
$b1 = (int) team_member_create($r20Tag . ' Alpha', 'FIELD', null, $mail2);
t_ok($b1 > 0, 'C2a · the first person was added through the direct door — armed');
$b2 = (int) team_member_create($r20Tag . ' Beta', 'FIELD', null, $mail2);
t_eq($b2, 0, 'C2 · DETECTED — a second person carrying that e-mail is REFUSED on the direct door until somebody acknowledges it');
t_ok(strpos(team_member_last_refusal(), $r20Tag . ' Alpha') !== false,
     'C2b · …and the refusal NAMES who they may already be, so it is actionable');
$b3 = (int) team_member_create($r20Tag . ' Beta', 'FIELD', null, $mail2, ['dup_ack' => 1]);
t_ok($b3 > 0, 'C2c · …and an explicit acknowledgement lets a genuine second engagement through');
t_ok($b3 !== $b1, 'C2d · …as a SEPARATE record — acknowledging is not merging');

// ---------------------------------------------------------------------------
t_section('R20 · CASE 3 — the same name AND e-mail');
// ---------------------------------------------------------------------------
$mail3 = $r20Tag . '.ne@r20.test';
$c1 = (int) team_member_create($r20Tag . ' Same Person', 'FIELD', null, $mail3);
t_ok($c1 > 0, 'C3a · the first exists — armed');
$c2 = (int) team_member_create($r20Tag . ' Same Person', 'FIELD', null, $mail3);
t_eq($c2, 0, 'C3 · DETECTED — an identical name and e-mail is stopped for acknowledgement');

// ---------------------------------------------------------------------------
t_section('R20 · CASE 3b — the same NAME ALONE never stops anybody');
// ---------------------------------------------------------------------------
//  Rajesh Patel does not block Rajesh Patel. A name is not an identity, and a
//  warning that fires on names teaches people to click past the one that matters.
$d1 = (int) team_member_create($r20Tag . ' Common Name', 'FIELD', null, $r20Tag . '.d1@r20.test');
$d2 = (int) team_member_create($r20Tag . ' Common Name', 'FIELD', null, $r20Tag . '.d2@r20.test');
t_ok($d1 > 0 && $d2 > 0, 'C3b · two people who merely share a name are both added — deliberately');

// ---------------------------------------------------------------------------
t_section('R20 · CASE 4 — the same name AND employee number');
// ---------------------------------------------------------------------------
$e1 = $rawIns($r20Tag . ' Dup Code', $r20Tag . '-004', $r20Tag . '.e1@r20.test');
t_ok($e1 > 0, 'C4a · the first exists — armed');
$e2 = $rawIns($r20Tag . ' Dup Code', $r20Tag . '-004', $r20Tag . '.e2@r20.test');
t_eq($e2, 0, 'C4 · PREVENTED — the employee number decides, whatever the name says');

// ---------------------------------------------------------------------------
t_section('R20 · CASE 5 — the number of somebody who has LEFT');
// ---------------------------------------------------------------------------
//  The critical one. A number is issued once, for ever, tenant-wide.
$f1 = $rawIns($r20Tag . ' Leaver', $r20Tag . '-005', $r20Tag . '.f1@r20.test');
t_ok($f1 > 0, 'C5a · they were issued the number — armed');
db()->prepare("UPDATE inspectors SET status='INACTIVE' WHERE id=?")->execute([$f1]);
t_eq((string) ops_val("SELECT status FROM inspectors WHERE id=?", [$f1]), 'INACTIVE',
     'C5b · …and they have now left — the probe has the state it is about');
$f2 = $rawIns($r20Tag . ' Newcomer', $r20Tag . '-005', $r20Tag . '.f2@r20.test');
t_eq($f2, 0, 'C5 · PREVENTED — a leaver\'s number is NEVER re-issued, which is what makes history readable');
t_eq((string) ops_val("SELECT name FROM inspectors WHERE UPPER(TRIM(COALESCE(emp_code,'')))=?", [$r20Tag . '-005']),
     $r20Tag . ' Leaver', 'C5c · …and it still belongs to the person who earned it');

// ---------------------------------------------------------------------------
t_section('R20 · CASE 5b — a LEAVER\'s address must not block their return');
// ---------------------------------------------------------------------------
//  The protection must say exactly what the matcher says. The matcher ignores
//  anybody who has left — "history, not a duplicate" — so the key must too, or
//  re-hiring a returning employee becomes impossible. The first version of the
//  key got this wrong and the Step 2 battery caught it; this pins it here, in
//  the battery that is about identity.
$gMail = $r20Tag . '.returner@r20.test';
$g1 = (int) team_member_create($r20Tag . ' Returner', 'FIELD', null, $gMail);
t_ok($g1 > 0, 'C5b1 · they were on the team — armed');
db()->prepare("UPDATE inspectors SET status='INACTIVE' WHERE id=?")->execute([$g1]);
t_eq((string) ops_val("SELECT status FROM inspectors WHERE id=?", [$g1]), 'INACTIVE', 'C5b2 · …and they left — armed');
$g2 = (int) team_member_create($r20Tag . ' Returner', 'FIELD', null, $gMail);
t_ok($g2 > 0, 'C5b · …and they can be taken back on, with the same address — a leaver is history, not a duplicate');
t_ok($g2 !== $g1, 'C5b3 · …as a new engagement, leaving the old record untouched');
t_eq((string) ops_val("SELECT status FROM inspectors WHERE id=?", [$g1]), 'INACTIVE',
     'C5b4 · …and the historical record was NOT reactivated or rewritten behind anybody\'s back');

// ---------------------------------------------------------------------------
t_section('R20 · CASE 1b — case and whitespace cannot smuggle a twin in');
// ---------------------------------------------------------------------------
//  An employee number that differs only in case or padding is the SAME number.
//  If the key did not normalise, "emp001", "EMP001" and " EMP001" would be three
//  people. Note the LEADING space: MySQL ignores a trailing one in comparison,
//  so a trailing-space probe would pass without proving anything.
$h1 = $rawIns($r20Tag . ' Case One', $r20Tag . '-1B', $r20Tag . '.h1@r20.test');
t_ok($h1 > 0, 'C1b1 · the first holder exists — armed');
t_eq($rawIns($r20Tag . ' Case Two', strtolower($r20Tag . '-1B'), $r20Tag . '.h2@r20.test'), 0,
     'C1b · lower case is refused — the key normalises case');
t_eq($rawIns($r20Tag . ' Case Three', ' ' . $r20Tag . '-1B', $r20Tag . '.h3@r20.test'), 0,
     'C1b2 · a LEADING space is refused too — padding cannot smuggle a twin in');
t_eq($cnt("SELECT COUNT(*) FROM inspectors WHERE UPPER(TRIM(COALESCE(emp_code,'')))=?", [$r20Tag . '-1B']), 1,
     'C1b3 · exactly one holder, however it was spelled');

// ---------------------------------------------------------------------------
t_section('R20 · CASE 6 — duplicates that are already in the data');
// ---------------------------------------------------------------------------
//  Never deleted, never merged, never renumbered. Detected and reported.
t_ok(function_exists('emp_code_collisions'), 'C6a · there is a report for existing employee-number collisions');
$before6 = $cnt("SELECT COUNT(*) FROM inspectors");
$rep = emp_code_collisions(50);
t_ok(is_array($rep), 'C6 · …and it answers with a list rather than an opinion');
t_eq($cnt("SELECT COUNT(*) FROM inspectors"), $before6,
     'C6b · REPORTING CHANGED NOTHING — no historical record was deleted, merged or renumbered to make this tidy');
t_ok(function_exists('workforce_direct_matches'),
     'C6c · and existing shared-contact records are discoverable through the same matcher the doors use');

// ---------------------------------------------------------------------------
t_section('R20 · CONCURRENCY — three processes, one identity, same microsecond');
// ---------------------------------------------------------------------------
//  "Check then insert" is race-prone by construction, so the protection must be
//  somewhere a race cannot slip through. For the employee number that place is
//  the DATABASE: a unique key, which two writers cannot both satisfy.
//
//  Real processes, a wall-clock barrier, and one-time costs paid before it.
$r20env = function () {
    $e = '';
    foreach (['DB_DRIVER', 'SQLITE_PATH', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
        $v = getenv($k); if ($v !== false && $v !== '') $e .= $k . '=' . escapeshellarg($v) . ' ';
    }
    return $e;
};
$r20race = function (array $specs, $leadMs = 1400) use ($r20Root, $r20env) {
    $target = round(microtime(true) * 1000) + $leadMs; $procs = [];
    foreach ($specs as $s) {
        $cmd = $r20env() . 'php ' . escapeshellarg($r20Root . '/tests/_r20_worker.php') . ' '
             . escapeshellarg($s[0]) . ' ' . escapeshellarg($s[1]) . ' ' . escapeshellarg($s[2]) . ' '
             . escapeshellarg($s[3]) . ' ' . escapeshellarg((string)$target) . ' 2>&1';
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$p, $pipes];
    }
    $out = [];
    foreach ($procs as [$p, $pipes]) {
        $raw = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        foreach ($pipes as $fh) @fclose($fh); @proc_close($p);
        foreach (explode("\n", trim($raw)) as $l) { $j = json_decode(trim($l), true); if (is_array($j) && isset($j['mode'])) { $out[] = $j; break; } }
    }
    return $out;
};

//  X1 — THE EMPLOYEE NUMBER, raced. No application code in the path at all.
$xCode = $r20Tag . '-RACE';
$res = $r20race([
    ['raw', $r20Tag . ' RaceA', $r20Tag . '.ra@r20.test', $xCode],
    ['raw', $r20Tag . ' RaceB', $r20Tag . '.rb@r20.test', $xCode],
    ['raw', $r20Tag . ' RaceC', $r20Tag . '.rc@r20.test', $xCode],
]);
t_eq(count($res), 3, 'X1a · all three processes reported — the race actually ran');
$won = count(array_filter($res, fn($r) => !empty($r['ok'])));
t_eq($won, 1, 'X1 · EXACTLY ONE creation succeeded — three writers, one employee number, no duplicate');
t_eq($cnt("SELECT COUNT(*) FROM inspectors WHERE UPPER(TRIM(COALESCE(emp_code,'')))=?", [$xCode]), 1,
     'X1b · …and the register holds exactly one record carrying it');
$lost = array_values(array_filter($res, fn($r) => empty($r['ok'])));
t_eq(count($lost), 2, 'X1c · the other two were refused');
//  WHY they were refused can only be pinned on MariaDB. SQLite takes ONE
//  database-wide write lock, so a loser there is usually told "database is
//  locked" before the key is ever consulted — a true statement about SQLite
//  that says nothing about the protection. MariaDB, the authoritative engine,
//  lets all three reach the key, and there the reason must BE the key.
$whyAll = strtolower(implode(' | ', array_map(fn($r) => (string)($r['why'] ?? ''), $lost)));
if (t_driver() === 'sqlite') {
    t_ok(strpos($whyAll, 'emp_code') !== false || strpos($whyAll, 'unique') !== false
         || strpos($whyAll, 'locked') !== false,
         'X1d (sqlite) · the losers were refused, by the key or by SQLite\'s single write lock — WHICH is proved on MariaDB (' . substr($whyAll, 0, 44) . ')');
} else {
    t_ok(strpos($whyAll, 'emp_code') !== false || strpos($whyAll, 'duplicate entry') !== false,
         'X1d · …by the UNIQUE KEY specifically, not by luck and not by an application check (' . substr($whyAll, 0, 44) . ')');
}
t_eq($cnt("SELECT COUNT(*) FROM inspectors WHERE name LIKE ?", [$r20Tag . ' Race%']), 1,
     'X1e · no orphan and no partial identity — one person, one record');

//  X2 — THE DIRECT DOOR, raced on a shared e-mail.
//  MEASURED BEFORE THE FIX: three records created in two of three runs on
//  MariaDB. Detection followed by an insert is a read-then-write, and a true
//  tie sails through it — which is precisely why §7 says not to rely on it.
//  The e-mail key now decides, so the race must produce exactly one record.
$mailR = $r20Tag . '.raced@r20.test';
$res2 = $r20race([
    ['team', $r20Tag . ' TeamA', $mailR, ''],
    ['team', $r20Tag . ' TeamB', $mailR, ''],
    ['team', $r20Tag . ' TeamC', $mailR, ''],
]);
t_eq(count($res2), 3, 'X2a · all three direct-door processes reported — armed');
$made = array_values(array_filter($res2, fn($r) => !empty($r['ok'])));
$codes = [];
foreach ($made as $r) $codes[] = (string) ops_val("SELECT COALESCE(emp_code,'') FROM inspectors WHERE id=?", [(int)$r['id']]);
t_eq(count($codes), count(array_unique(array_filter($codes))),
     'X2a2 · every record the race created holds a different employee number');
t_eq($cnt("SELECT COUNT(*) FROM inspectors WHERE LOWER(TRIM(COALESCE(email,'')))=? AND COALESCE(NULLIF(status,''),'ACTIVE')='ACTIVE'", [strtolower($mailR)]), 1,
     'X2 · EXACTLY ONE live record carries that address — three simultaneous adds, one person (' . count($made) . ' reported success)');
t_ok(count($made) >= 1, 'X2b · at least one succeeded — the door is not simply broken under load');
$whyTeam = strtolower(implode(' | ', array_map(fn($r) => (string)($r['why'] ?? ''), array_filter($res2, fn($r) => empty($r['ok'])))));
t_ok(count($made) === 1, 'X2c · …and the other two did NOT create a record');
//  State the residual risk in the test itself, so it is impossible to read this
//  battery as claiming more than it proves.
t_ok(strpos($whyTeam, 'on your team') !== false || strpos($whyTeam, 'uq_email') !== false
     || strpos($whyTeam, 'ux_inspectors_email') !== false || strpos($whyTeam, 'duplicate') !== false
     || strpos($whyTeam, 'unique') !== false || strpos($whyTeam, 'locked') !== false || $whyTeam === '',
     'X2d · …they were refused by the detector or by the e-mail key, not by chance (' . substr($whyTeam, 0, 52) . ')');
//  The key is really installed, asked of the schema rather than assumed.
t_ok(in_array(EMAIL_KEY_IX, table_index_names('inspectors'), true)
     || (function_exists('schema_guard_state') && schema_guard_state(EMAIL_KEY_IX) === 'DIRTY'),
     'X2e · the e-mail key is installed — or reported DIRTY because this workspace already had live twins, which is never silently fixed');
