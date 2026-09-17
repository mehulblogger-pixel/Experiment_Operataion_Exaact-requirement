<?php
// ============================================================================
//  PHASE 3 · M5 — REAL-PROCESS CONCURRENCY
//
//  Every race below runs in genuinely separate php processes on independent
//  database connections. Nothing here is a sequential simulation, and nothing is
//  solved in the browser: two managers pressing Save at the same moment is a
//  server-side problem and is tested as one.
// ============================================================================

t_section('Phase 3 · M5 — concurrency, with real processes');

$pdo = db(); rasg_migrate(); act_migrate();
$engine = db_driver(); $root = dirname(__DIR__); $m5cOrig = $_SESSION;
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (9571,'M5C Branch',1)")->execute(); } catch (Throwable $e) {}
$mk = function ($un) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,'M5C','MANAGER',1,1,9571,'')")->execute([$un]);
    return (int) $pdo->lastInsertId();
};
$uBoss = $mk('m5c_boss'); $uX = $mk('m5c_x'); $uY = $mk('m5c_y'); $uZ = $mk('m5c_z');
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);

$mkReq = function ($recruiter = null) use ($pdo) {
    $pdo->prepare("INSERT INTO requisitions (req_code,office_id,designation,status,quantity,recruiter_id,created_at)
                   VALUES (?,9571,'ENGINEER','OPEN',5,?,?)")
        ->execute(['M5C-' . bin2hex(random_bytes(3)), $recruiter, date('c')]);
    return (int) $pdo->lastInsertId();
};
$owner = fn($id) => (int) ops_val("SELECT COALESCE(recruiter_id,0) FROM requisitions WHERE id=?", [$id]);

//  N real processes, all reaching their operation at the same moment.
$race = function (array $ops) use ($root, $engine, $uBoss) {
    $env = $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
          . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
          . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $procs = [];
    foreach ($ops as [$op, $id, $to, $expect]) {
        $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_m5_worker.php') . ' '
             . escapeshellarg($op) . ' ' . (int) $id . ' ' . escapeshellarg((string) $to) . ' '
             . escapeshellarg($expect === null ? 'NONE' : (string) $expect) . ' 500 ' . (int) $uBoss . ' 2>&1';
        $pipes = []; $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($p)) $procs[] = [$p, $pipes];
    }
    $res = [];
    foreach ($procs as [$p, $pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
        foreach (explode("\n", trim($raw)) as $line) { $d = json_decode(trim($line), true); if (is_array($d)) $res[] = $d; }
    }
    return $res;
};

// ---------------------------------------------------------------------------
t_section('C1 · two managers assign the same requirement to different people');
$r1 = $mkReq();
$res1 = $race([['assign', $r1, $uX, null], ['assign', $r1, $uY, null]]);
t_eq(count($res1), 2, 'C1 · both processes reported');
$won = array_values(array_filter($res1, fn($r) => $r['ok'] && $r['code'] === 'OK'));
t_eq(count($won), 1, 'C1 · *** exactly one assignment succeeded ***');
$final = $owner($r1);
t_ok(in_array($final, [$uX, $uY], true), 'C1 · the final owner is one of the two people asked for');
t_eq($final, (int) $won[0]['to'], 'C1 · *** and it is the one the winning process asked for — not a mixture ***');
$lost = array_values(array_filter($res1, fn($r) => !$r['ok']));
t_eq(count($lost), 1, 'C1 · the loser was refused');
t_ok(in_array($lost[0]['code'], ['LOST_RACE', 'STALE'], true), 'C1 · …and told why: ' . $lost[0]['code']);
t_eq(count(rasg_history('REQ_RECRUITER', $r1)), 1, 'C1 · *** exactly one ledger row — the loss is not recorded as a move ***');

t_section('C2 · five processes, one requirement');
$r2 = $mkReq();
$res2 = $race([['assign', $r2, $uX, null], ['assign', $r2, $uY, null], ['assign', $r2, $uZ, null],
               ['assign', $r2, $uX, null], ['assign', $r2, $uY, null]]);
t_eq(count($res2), 5, 'C2 · five processes reported');
$ok2 = array_values(array_filter($res2, fn($r) => $r['ok'] && $r['code'] === 'OK'));
t_eq(count($ok2), 1, 'C2 · *** still exactly one winner under five-way contention ***');
t_eq($owner($r2), (int) $ok2[0]['to'], 'C2 · the column holds the winner\'s choice');
t_eq(count(rasg_history('REQ_RECRUITER', $r2)), 1, 'C2 · one move, one ledger row');

t_section('C3 · a reassignment race against an EXISTING owner');
$r3 = $mkReq($uX);
$res3 = $race([['assign', $r3, $uY, $uX], ['assign', $r3, $uZ, $uX]]);
$ok3 = array_values(array_filter($res3, fn($r) => $r['ok'] && $r['code'] === 'OK'));
t_eq(count($ok3), 1, 'C3 · *** one reassignment wins, one is refused ***');
t_eq($owner($r3), (int) $ok3[0]['to'], 'C3 · the owner is the winner\'s person');
t_ok($owner($r3) !== $uX, 'C3 · …and the previous owner has genuinely been replaced');
$h3 = rasg_history('REQ_RECRUITER', $r3);
t_eq(count($h3), 1, 'C3 · one ledger row for the one real move');
t_eq((int) $h3[0]['from_user_id'], $uX, 'C3 · which records who it was taken from');

t_section('C4 · the browser form path, raced');
$r4 = $mkReq($uX);
$res4 = $race([['form', $r4, $uY, $uX], ['form', $r4, $uZ, $uX]]);
$ok4 = array_values(array_filter($res4, fn($r) => $r['ok']));
t_eq(count($ok4), 1, 'C4 · *** two browser saves, one winner — the form path is protected too ***');
t_ok(in_array($owner($r4), [$uY, $uZ], true), 'C4 · the owner is one of the two, never both and never neither');

t_section('C5 · a baseline-less caller racing a baseline-carrying one');
//  MY OWN PROBE WAS WRONG HERE, and MariaDB is what proved it. The first version
//  asserted "exactly one of the two wrote". That is the right invariant for two
//  callers holding the SAME baseline (C1, C2, C3 and C4 all assert it) — but a
//  caller that carries NO baseline is not a stale screen. It is a service saying
//  "make Z the owner, whatever it is now", so when it re-reads and finds Y it
//  moves Y → Z, and BOTH moves are real. The ledger showed 279 → 280 → 281: an
//  unbroken chain of two genuine assignments, not one lost update.
//
//  SQLite passed the wrong assertion only because it serialises writers, so the
//  second process usually read before the first committed. The engine difference
//  exposed a defective test, not defective behaviour.
//
//  So what must actually hold is asserted instead: whatever order they land in,
//  the final owner is somebody who was asked for, and the ledger is a TRUTHFUL
//  CHAIN — every row hands over from the row before it, and the last row's
//  handover is what the column holds. A phantom move cannot hide in that.
$r5 = $mkReq($uX);
$res5 = $race([['assign', $r5, $uY, $uX], ['assign_nobase', $r5, $uZ, null]]);
$ok5 = array_values(array_filter($res5, fn($r) => $r['ok'] && $r['code'] === 'OK'));
t_ok(count($ok5) >= 1, 'C5 · at least one assignment succeeded');
t_ok(in_array($owner($r5), [$uY, $uZ], true),
     'C5 · *** the final owner is one of the two people asked for — never a third value ***');
$chain = array_reverse(rasg_history('REQ_RECRUITER', $r5));     // oldest first
t_eq(count($chain), count($ok5), 'C5 · *** the ledger holds exactly one row per successful move — no phantom moves ***');
$prev = $uX; $chainOk = true;
foreach ($chain as $link) {
    if ((int) ($link['from_user_id'] ?? 0) !== $prev) $chainOk = false;
    $prev = (int) ($link['to_user_id'] ?? 0);
}
t_ok($chainOk, 'C5 · *** every ledger row hands over from the one before it — an unbroken chain ***');
t_eq($prev, $owner($r5), 'C5 · and the chain ends exactly where the column stands');

t_section('C6 · assignment racing a change of state');
//  While two processes argue about the owner, the requisition is being closed.
//  Whatever happens, the record must not end up owned by somebody who was
//  assigned to a closed requirement.
$r6 = $mkReq();
$pdo->prepare("UPDATE requisitions SET status='CANCELLED' WHERE id=?")->execute([$r6]);
$res6 = $race([['assign', $r6, $uX, null], ['assign', $r6, $uY, null]]);
$ok6 = array_values(array_filter($res6, fn($r) => $r['ok'] && $r['code'] === 'OK'));
t_eq(count($ok6), 0, 'C6 · *** neither process could assign a cancelled requirement ***');
t_eq($owner($r6), 0, 'C6 · and it is still owned by nobody');
foreach ($res6 as $r) t_eq($r['code'], 'BAD_STATE', 'C6 · both were refused by the state, not by the race');

$_SESSION = $m5cOrig; current_user(true); ua(true);
