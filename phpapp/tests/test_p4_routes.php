<?php
// ============================================================================
//  PHASE 4 — THE ROUTES THEMSELVES
//
//  The mutation battery found that eleven controls could be broken without a
//  single test noticing, and three of those live in `ops.php`'s candidate save
//  paths rather than in the engine. Every probe here therefore drives the REAL
//  ROUTE, in its own process (the route calls redirect(), which exits), and then
//  reads the DATABASE. Nothing the route says is believed; only what it wrote.
//
//  This is the difference between "the engine refuses" and "the application
//  refuses" — the recurring defect family in this programme has been a rule
//  applied where somebody remembered to apply it.
// ============================================================================

t_section('Phase 4 — the candidate routes, driven for real');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate(); reqf_migrate(); rful_migrate();
$engine = db_driver(); $root = dirname(__DIR__); $t4o = $_SESSION;
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (9681,'P4T Branch',1)")->execute(); } catch (Throwable $e) {}
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES ('p4t_boss','P4T','MANAGER',1,1,9681,'')")->execute();
$uT = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uT; current_user(true); ua(true);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$t4req = function ($qty, $title) use ($uT, $eng, $qua) {
    [$ok,, $h] = hreq_save(0, ['requested_by_id' => $uT, 'requested_by_name' => 'P4T',
        'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
        'job_title' => $title, 'designation' => 'ENGINEER', 'job_description' => 'p4t',
        'quantity' => $qty, 'office_id' => 9681, 'required_by' => '2026-12-01',
        'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x']);
    if (!$ok) return 0;
    hreq_submit($h); hreq_apply_decision($h, 'APPROVED', 'P4T', 'ok');
    [$okR,, $rq] = hreq_to_requisition($h, $qty); return $okR ? (int) $rq : 0; };
$t4cand = function ($req, $stage = 'RECEIVED') use ($pdo) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,created_at)
                   VALUES (?,'P4T','C',?,?,?)")->execute(['P4TC-' . bin2hex(random_bytes(4)), $stage, $req, date('c')]);
    return (int) $pdo->lastInsertId(); };
$linkOf = fn($c) => (int) ops_val("SELECT COALESCE(allocation_id,0) FROM candidates WHERE id=?", [$c]);
$stageOf = fn($c) => (string) ops_val("SELECT stage FROM candidates WHERE id=?", [$c]);

$drive = function ($op, $id, $arg, array $post) use ($root, $engine, $uT) {
    $env = $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
          . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
          . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_p4_worker.php') . ' '
         . escapeshellarg($op) . ' ' . (int) $id . ' ' . escapeshellarg((string) $arg) . ' '
         . escapeshellarg(json_encode($post)) . ' 0 ' . (int) $uT . ' 2>&1';
    $pipes = []; $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) return null;
    $raw = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    foreach (explode("\n", trim($raw)) as $line) { $d = json_decode(trim($line), true); if (is_array($d)) return $d; }
    return null; };

$rqA = $t4req(6, 'P4T Routes');
$aOK  = rful_allocate($rqA, 'MANPOWER_AGENCY', 2, ['label' => 'Sterling'])['id'];
$aBig = rful_allocate($rqA, 'OWN_PAYROLL', 4)['id'];
$rqB = $t4req(4, 'P4T Other');
$aOther = rful_allocate($rqB, 'SUPPLIER', 4)['id'];

// ---- RT1 · THE EDIT ROUTE SETS A CREDIT THROUGH THE DOOR --------------------
t_section('RT1 · the candidate EDIT route');
$c1 = $t4cand($rqA);
$r = $drive('route_cand_edit', $c1, '', ['requisition_id' => $rqA, 'allocation_id' => $aOK,
    'first_name' => 'P4T', 'last_name' => 'C']);
t_ok($r !== null, 'RT1.1 · the real route ran in its own process');
t_eq($linkOf($c1), (int) $aOK, 'RT1.2 · the credit was set through the route');
t_eq(rful_fulfilled($aOK), 0, 'RT1.3 · …and credits nobody yet, because nobody has joined');

//  A credit pointing at ANOTHER requirement's source must not survive the route.
$c2 = $t4cand($rqA);
$drive('route_cand_edit', $c2, '', ['requisition_id' => $rqA, 'allocation_id' => $aOther,
    'first_name' => 'P4T', 'last_name' => 'C']);
t_eq($linkOf($c2), 0, 'RT1.4 · a source belonging to a different requirement is refused by the route');

//  And a malformed value must not read as "clear the link".
$drive('route_cand_edit', $c1, '', ['requisition_id' => $rqA, 'allocation_id' => 'abc',
    'first_name' => 'P4T', 'last_name' => 'C']);
t_eq($linkOf($c1), (int) $aOK, 'RT1.5 · a malformed value does NOT clear an existing credit');
//  …while a genuinely empty one does, because clearing is a real operation.
$drive('route_cand_edit', $c1, '', ['requisition_id' => $rqA, 'allocation_id' => '',
    'first_name' => 'P4T', 'last_name' => 'C']);
t_eq($linkOf($c1), 0, 'RT1.6 · an empty value DOES clear it — clearing is a real operation');

//  THE COMPENSATOR ON THE EDIT PATH. The door above refuses a bad link that is
//  POSTED. This is the other half: a bad link already sitting in the column,
//  which the next ordinary save must remove — whatever put it there. A mutation
//  proved nothing exercised it, because every probe had been calling the engine
//  directly rather than the route.
$c3 = $t4cand($rqA);
$pdo->prepare("UPDATE candidates SET allocation_id=? WHERE id=?")->execute([$aOther, $c3]);
t_eq($linkOf($c3), (int) $aOther, 'RT1.7 · a credit to another requirement’s source sits in the column');
$drive('route_cand_edit', $c3, '', ['requisition_id' => $rqA, 'first_name' => 'P4T', 'last_name' => 'C']);
t_eq($linkOf($c3), 0, 'RT1.8 · an ordinary save through the ROUTE removes it (mutant T10)');

//  …and the same for an over-credited source, where the established keep theirs.
$rqE = $t4req(6, 'P4T EditComp');
$aE = rful_allocate($rqE, 'SUPPLIER', 1)['id'];
$e1 = $t4cand($rqE, 'ACCEPTED'); rful_attach($e1, $aE);
$e2 = $t4cand($rqE, 'ACCEPTED');
$pdo->prepare("UPDATE candidates SET allocation_id=? WHERE id=?")->execute([$aE, $e2]);
t_eq(rful_fulfilled($aE), 2, 'RT1.9 · a one-seat source is credited with two');
$drive('route_cand_edit', $e2, '', ['requisition_id' => $rqE, 'first_name' => 'P4T', 'last_name' => 'C']);
t_eq(rful_fulfilled($aE), 1, 'RT1.10 · the route brings it back to its one');
t_eq($linkOf($e1), (int) $aE, 'RT1.11 · the ESTABLISHED credit is the one kept');
t_eq($linkOf($e2), 0, 'RT1.12 · the later one is dropped to the direct path');
t_eq($stageOf($e2), 'ACCEPTED', 'RT1.13 · …and that person is still in their seat (§24)');

// ---- RT2 · A LINK CANNOT BE SMUGGLED PAST THE ROUTE -------------------------
//  The mutation battery's T30: a create path that writes allocation_id straight
//  to the column. The route must never produce a link the door would refuse.
t_section('RT2 · the candidate CREATE route');
$before = (int) ops_val("SELECT COUNT(*) FROM candidates");
$drive('route_cand_new', 0, '', ['requisition_id' => $rqA, 'allocation_id' => $aOther,
    'first_name' => 'P4T', 'last_name' => 'Smuggle', 'dup_ack' => 1]);
$newId = (int) ops_val("SELECT id FROM candidates ORDER BY id DESC");
t_ok((int) ops_val("SELECT COUNT(*) FROM candidates") > $before, 'RT2.1 · the create route made a candidate');
t_eq($linkOf($newId), 0, 'RT2.2 · …carrying NO credit to another requirement’s source');
$drive('route_cand_new', 0, '', ['requisition_id' => $rqA, 'allocation_id' => $aOK,
    'first_name' => 'P4T', 'last_name' => 'Proper', 'dup_ack' => 1]);
$newId2 = (int) ops_val("SELECT id FROM candidates ORDER BY id DESC");
t_eq($linkOf($newId2), (int) $aOK, 'RT2.3 · a legitimate credit IS set by the create route');

//  …and the create route runs the SAME defence in depth the edit and stage routes
//  run: whatever wrote to the row, a link that cannot be held is removed. The
//  compensator used to run BEFORE the link was set, where it could only ever be a
//  no-op, so the create path was the one route with no second line behind it.
$rqN = $t4req(6, 'P4T CreateComp');
$aN  = rful_allocate($rqN, 'SUPPLIER', 1)['id'];
$n1  = $t4cand($rqN, 'ACCEPTED'); rful_attach($n1, $aN);
t_eq(rful_fulfilled($aN), 1, 'RT2.4 · a one-seat source is full');
$drive('route_cand_new', 0, '', ['requisition_id' => $rqN, 'allocation_id' => $aN,
    'first_name' => 'P4T', 'last_name' => 'Second', 'dup_ack' => 1]);
$newId3 = (int) ops_val("SELECT id FROM candidates ORDER BY id DESC");
t_eq($linkOf($newId3), 0, 'RT2.5 · a second person cannot be created onto it');
t_eq(rful_fulfilled($aN), 1, 'RT2.6 · and the source is still credited with exactly one');
t_eq($linkOf($n1), (int) $aN, 'RT2.7 · the established credit is untouched');

// ---- RT3 · THE STAGE ROUTE SETTLES THE SOURCE'S CEILING ---------------------
//  The mutation battery's T27. Two people are credited to a two-seat source and
//  join; a third is credited by a raw write and then joins through the route.
//  The route must settle the source's ceiling, not just M6's seat.
t_section('RT3 · the candidate STAGE route');
$rqC = $t4req(6, 'P4T Stage');
$aC = rful_allocate($rqC, 'SUBCON_AGENCY', 2)['id'];
$j1 = $t4cand($rqC, 'OFFERED'); rful_attach($j1, $aC);
$j2 = $t4cand($rqC, 'OFFERED'); rful_attach($j2, $aC);
$drive('route_cand_stage', $j1, 'ACCEPTED', ['team_role' => 'FIELD']);
$drive('route_cand_stage', $j2, 'ACCEPTED', ['team_role' => 'FIELD']);
t_eq($stageOf($j1), 'ACCEPTED', 'RT3.1 · the first joined through the route');
t_eq($stageOf($j2), 'ACCEPTED', 'RT3.2 · the second too');
t_eq(rful_fulfilled($aC), 2, 'RT3.3 · the sub-contractor is credited with both of its two');

$j3 = $t4cand($rqC, 'OFFERED');
$pdo->prepare("UPDATE candidates SET allocation_id=? WHERE id=?")->execute([$aC, $j3]);   // past the door
t_eq($linkOf($j3), (int) $aC, 'RT3.4 · a third carries a smuggled credit to the same two-seat source');
$drive('route_cand_stage', $j3, 'ACCEPTED', ['team_role' => 'FIELD']);
t_eq($stageOf($j3), 'ACCEPTED', 'RT3.5 · they DO join — there is an approved seat, and Phase 4 never blocks that');
t_eq(rful_fulfilled($aC), 2, 'RT3.6 · but the source is STILL credited with exactly two (mutant T27)');
t_eq($linkOf($j3), 0, 'RT3.7 · the smuggled credit was dropped to the direct path');
t_eq($linkOf($j1), (int) $aC, 'RT3.8 · the first ESTABLISHED credit is untouched');
t_eq($linkOf($j2), (int) $aC, 'RT3.9 · …and so is the second (never displace)');

// ---- RT4 · THE ESTABLISHED HOLDERS ARE THE ONES WHO KEEP THEIR CREDIT -------
//  The mutation battery's T23: the compensator's keep-list must be the EARLIEST
//  credited, never the latest. Built by writing four links straight past the
//  door so the compensator has to choose.
t_section('RT4 · the compensator keeps the established, not the newest');
$rqD = $t4req(8, 'P4T Choose');
$aD = rful_allocate($rqD, 'FREELANCER', 2)['id'];
$who = [];
for ($i = 0; $i < 4; $i++) {
    $who[] = $c = $t4cand($rqD, 'ACCEPTED');
    $pdo->prepare("UPDATE candidates SET allocation_id=? WHERE id=?")->execute([$aD, $c]);
}
t_eq(rful_fulfilled($aD), 4, 'RT4.1 · four are credited to a two-seat source (written past the door)');
//  Run the compensator over each, newest FIRST, so a keep-list built the wrong
//  way round would visibly keep the newcomers.
foreach (array_reverse($who) as $c) rful_enforce_candidate($c);
t_eq(rful_fulfilled($aD), 2, 'RT4.2 · the source is brought back to exactly its two');
t_eq($linkOf($who[0]), (int) $aD, 'RT4.3 · the FIRST credited keeps it');
t_eq($linkOf($who[1]), (int) $aD, 'RT4.4 · the second keeps it');
t_eq($linkOf($who[2]), 0, 'RT4.5 · the third loses it');
t_eq($linkOf($who[3]), 0, 'RT4.6 · the fourth loses it');
foreach ($who as $c) t_eq($stageOf($c), 'ACCEPTED', 'RT4.7 · and all four are STILL in their seats (§24)');

$_SESSION = $t4o; current_user(true); ua(true);
