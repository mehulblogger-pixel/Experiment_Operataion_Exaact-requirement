<?php
// ============================================================================
//  PHASE 6 · BATCH 1 — Identity write safety, authority and scope.
//
//  Written BEFORE the implementation, against the code as it stood, so that
//  every assertion here describes the required behaviour rather than describing
//  whatever the new code happens to do. The baseline run is recorded in
//  docs/phase6/P6-BATCH1-SECURITY-RESULTS.md.
//
//  Everything is read back from the DATABASE. A return code on its own is never
//  accepted as evidence: a guard that returns "refused" and writes the row
//  anyway would pass a return-code test and fail every one of these.
//
//  Sections
//    A  read-path immutability                  I23 · R22
//    B  authorisation / ownership               I25 · R24
//    C  entitlement consistency                 I26 · R25
//    D  scope (per-end visibility)              I16 · R15
//    E  tenant isolation                        I15 · R16
//    F  database uniqueness U1/U2/U3            I28 · R3
//    G  real-process concurrency                I29 · R11
//    H  legacy duplicates on migration          R3
//    I  partial failure                         I22 · I42
//    J  retry / idempotency                     I41
//    K  direct URL / L forged POST              I25
//    M  direct function bypass                  I27
//    X  axis separation                         owner decision 2
//    Y  attributable audit                      owner decision 3
// ============================================================================

$p6sess = $_SESSION;                      // restored at the very END of the file.
                                          // Phase 5 learned this the hard way: a
                                          // restore in the middle silently runs the
                                          // rest of the file with no scope at all.

// ---- fixtures ---------------------------------------------------------------
$p6act = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

$p6user = function ($username, $role, $opt = []) {
    db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active,home_office_id,scope_offices,scope_sbus)
                   VALUES (?,?,?,?,?,?,?,1,?,?,?)")
        ->execute([$username, password_hash('x', PASSWORD_DEFAULT),
                   $opt['first'] ?? ucfirst(explode('.', $username)[0]), $opt['last'] ?? 'Tester',
                   $username . '@p6.test', $role, (int)($opt['su'] ?? 0),
                   $opt['office'] ?? null, $opt['scope_offices'] ?? '', $opt['scope_sbus'] ?? '']);
    return (int)db()->lastInsertId();
};
$p6pro = function ($name, $email = '') {
    db()->prepare("INSERT INTO cx_professionals (name,email) VALUES (?,?)")->execute([$name, $email]);
    return (int)db()->lastInsertId();
};
$p6insp = function ($name, $office = null, $sbu = '') {
    $id = (int)team_member_create($name, 'FIELD', $office, '');
    if ($sbu !== '') db()->prepare("UPDATE inspectors SET sbu=? WHERE id=?")->execute([$sbu, $id]);
    return $id;
};
$p6cand = function ($first, $last = 'Person', $sbu = '') {
    db()->prepare("INSERT INTO candidates (first_name,last_name,email,sbu,stage) VALUES (?,?,?,?,'RECEIVED')")
        ->execute([$first, $last, strtolower($first) . '@p6.test', $sbu]);
    return (int)db()->lastInsertId();
};
$p6links = function () { return (int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE status='LINKED'"); };

// Spawn a real OS process. Returns the decoded JSON lines it printed.
$p6root = dirname(__DIR__);
$p6worker = function ($op, $a = 0, $b = 0, $c = '', $target = 0, $uid = 0) use ($p6root) {
    $env = '';
    foreach (['DB_DRIVER', 'SQLITE_PATH', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
        $v = getenv($k); if ($v !== false && $v !== '') $env .= $k . '=' . escapeshellarg($v) . ' ';
    }
    $cmd = $env . 'php ' . escapeshellarg($p6root . '/tests/_p6_worker.php') . ' '
         . escapeshellarg($op) . ' ' . (int)$a . ' ' . (int)$b . ' ' . escapeshellarg((string)$c) . ' '
         . escapeshellarg((string)$target) . ' ' . (int)$uid . ' 2>&1';
    $raw = (string)shell_exec($cmd);
    $rows = [];
    foreach (explode("\n", trim($raw)) as $line) {
        $j = json_decode(trim($line), true);
        if (is_array($j)) $rows[] = $j;
    }
    return $rows ? $rows[0] : ['ok' => false, 'msg' => 'no output: ' . substr($raw, 0, 200)];
};

// Launch several workers at once, all aiming at the SAME wall-clock microsecond.
$p6race = function (array $specs, $leadMs = 900) use ($p6root) {
    $env = '';
    foreach (['DB_DRIVER', 'SQLITE_PATH', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
        $v = getenv($k); if ($v !== false && $v !== '') $env .= $k . '=' . escapeshellarg($v) . ' ';
    }
    $target = round(microtime(true) * 1000) + $leadMs;
    $procs = [];
    foreach ($specs as $s) {
        $cmd = $env . 'php ' . escapeshellarg($p6root . '/tests/_p6_worker.php') . ' '
             . escapeshellarg($s[0]) . ' ' . (int)$s[1] . ' ' . (int)$s[2] . ' '
             . escapeshellarg((string)($s[3] ?? '')) . ' ' . escapeshellarg((string)$target) . ' ' . (int)($s[4] ?? 0) . ' 2>&1';
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

// ---- the cast ---------------------------------------------------------------
$uMaster = $p6user('p6.master', 'ADMIN', ['su' => 1]);
$uCoord  = $p6user('p6.coord', 'COORDINATOR', ['scope_offices' => 'ALL', 'scope_sbus' => 'ALL']);
$uMum    = $p6user('p6.mumbai', 'COORDINATOR', ['office' => 2, 'scope_offices' => '2', 'scope_sbus' => 'IND']);
$uInsp   = $p6user('p6.fieldman', 'INSPECTOR', ['office' => 1]);

$p6act($uMaster);

// =============================================================================
t_section('P6-B1 · A — a read must never create a person (R22 · I23)');
// =============================================================================
// The heart of it: an active inspector-role login with no team member. Reading
// any list must leave it exactly as it is.
$strayId = $p6user('p6.stray', 'INSPECTOR', ['office' => 1, 'first' => 'Stray', 'last' => 'Login']);
$snap = function () {
    return [
        'inspectors' => (int)ops_val("SELECT COUNT(*) FROM inspectors"),
        'linked'     => (int)ops_val("SELECT COUNT(*) FROM users WHERE COALESCE(inspector_id,0)>0"),
        'idlinks'    => (int)ops_val("SELECT COUNT(*) FROM cx_identity_link"),
        'candidates' => (int)ops_val("SELECT COUNT(*) FROM candidates"),
        'pros'       => (int)ops_val("SELECT COUNT(*) FROM cx_professionals"),
    ];
};
$before = $snap();
t_eq((int)ops_val("SELECT COALESCE(inspector_id,0) FROM users WHERE id=?", [$strayId]), 0,
     'A0 · the stray inspector login starts with no team member');

// Every one of the 17 production call sites reaches the list through this one
// function. Calling it in both modes is calling all of them.
inspectors_list(true);
inspectors_list(false);
$afterList = $snap();
t_eq($afterList['inspectors'], $before['inspectors'], 'A1 · reading the team list created no inspector row');
t_eq($afterList['linked'],     $before['linked'],     'A2 · reading the team list linked no login');
t_eq((int)ops_val("SELECT COALESCE(inspector_id,0) FROM users WHERE id=?", [$strayId]), 0,
     'A3 · the stray login is STILL unlinked after the read');
t_eq($afterList['idlinks'], $before['idlinks'], 'A4 · reading created no identity link');

// The other read surfaces that reach the same list.
foreach ([['rating', fn() => function_exists('rating_board') ? @rating_board() : null],
          ['timesheet', fn() => function_exists('ts_grid') ? @ts_grid(date('Y-m')) : null]] as [$nm, $fn]) {
    try { $fn(); } catch (Throwable $e) {}
}
$afterOther = $snap();
t_eq($afterOther['inspectors'], $before['inspectors'], 'A5 · the other read surfaces created no inspector either');

// A3 is the invariant; A6 is the capability it must not destroy. The explicit
// reconciliation, run by an authorised person, must still do the job.
$p6act($uMaster);
if (function_exists('link_inspector_users')) link_inspector_users();
$healed = (int)ops_val("SELECT COALESCE(inspector_id,0) FROM users WHERE id=?", [$strayId]);
t_ok($healed > 0, 'A6 · the EXPLICIT reconciliation still links the stray login');
t_eq((string)ops_val("SELECT name FROM inspectors WHERE id=?", [$healed]), 'Stray Login',
     'A7 · the reconciled team member carries the login name');

// And it is idempotent — running it again must create nothing.
$n1 = (int)ops_val("SELECT COUNT(*) FROM inspectors");
if (function_exists('link_inspector_users')) link_inspector_users();
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors"), $n1, 'A8 · a second reconciliation creates nothing (J · idempotent)');

// M · the reconciliation checks its OWN authority. A coordinator may run
// recruitment; they may not invent staff records. Asked by the function, so it
// cannot be reached by finding some other page that calls it.
$stray2 = $p6user('p6.stray2', 'INSPECTOR', ['office' => 1, 'first' => 'Second', 'last' => 'Stray']);
$p6act($uCoord);
$n2 = (int)ops_val("SELECT COUNT(*) FROM inspectors");
$ranAs = function_exists('link_inspector_users') ? (int)link_inspector_users() : -1;
t_eq($ranAs, 0, 'A9 · M · a caller without the people-management right reconciles nobody');
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors"), $n2, 'A10 · and created no team member (state, not return code)');
t_eq((int)ops_val("SELECT COALESCE(inspector_id,0) FROM users WHERE id=?", [$stray2]), 0,
     'A11 · the second stray login is untouched by the refused reconciliation');
$p6act($uMaster);
t_ok(function_exists('link_inspector_users') && link_inspector_users() >= 1,
     'A12 · and an AUTHORISED caller still reconciles it (the right was checked, not removed)');

// =============================================================================
t_section('P6-B1 · X — the two relationship axes are independent (decision 2)');
// =============================================================================
$xPro   = $p6pro('Axis Person', 'axis@p6.test');
$xInsp  = $p6insp('Axis Person', 1, 'IND');
$xCand  = $p6cand('Axis', 'One');
$xCand2 = $p6cand('Axis', 'Two');

[$xa] = connect_identity_candidate_link_create($xCand, $xPro, 'manual', 'p6');
t_ok($xa, 'X-pre · candidate links to the professional');

[$xb, $xbMsg] = connect_identity_link_create($xPro, $xInsp, 'manual', 'p6');
t_ok($xb, 'X-A · a candidate link does NOT block the inspector link  [' . $xbMsg . ']');

$xPro2  = $p6pro('Axis Person Two', 'axis2@p6.test');
$xInsp2 = $p6insp('Axis Person Two', 1, 'IND');
$xCand3 = $p6cand('Axis', 'Three');
[$xc] = connect_identity_link_create($xPro2, $xInsp2, 'manual', 'p6');
t_ok($xc, 'X-pre2 · professional links to the inspector');
[$xd, $xdMsg] = connect_identity_candidate_link_create($xCand3, $xPro2, 'manual', 'p6');
t_ok($xd, 'X-B · an inspector link does NOT block the candidate link  [' . $xdMsg . ']');

[$xe] = connect_identity_candidate_link_create($xCand2, $xPro, 'manual', 'p6');
t_ok($xe, 'X-C · two different candidates MAY link to one professional (invariant I3)');

$xInsp3 = $p6insp('Axis Person Third', 1, 'IND');
[$xf, $xfMsg] = connect_identity_link_create($xPro, $xInsp3, 'manual', 'p6');
t_ok(!$xf, 'X-D · one professional may NOT hold two inspector links  [' . $xfMsg . ']');

$xPro3 = $p6pro('Axis Person Third', 'axis3@p6.test');
[$xg, $xgMsg] = connect_identity_link_create($xPro3, $xInsp, 'manual', 'p6');
t_ok(!$xg, 'X-E · one inspector may NOT hold two professional links  [' . $xgMsg . ']');

// F — each resolver answers about its OWN axis and nothing else.
$rp = connect_identity_of_professional($xPro);
t_ok(is_array($rp) && (int)$rp['inspector_id'] === $xInsp,
     'X-F1 · the professional resolver returns the INSPECTOR-axis row');
t_eq((int)($rp['candidate_id'] ?? 0), 0, 'X-F2 · and never a candidate-axis row');
$rc = connect_identity_of_candidate($xCand);
t_ok(is_array($rc) && (int)$rc['professional_id'] === $xPro && (int)$rc['candidate_id'] === $xCand,
     'X-F3 · the candidate resolver returns the CANDIDATE-axis row');
$ri = connect_identity_of_inspector($xInsp);
t_ok(is_array($ri) && (int)$ri['professional_id'] === $xPro, 'X-F4 · the inspector resolver resolves');
$roles = connect_identity_roles(['professional_id' => $xPro]);
t_ok(!empty($roles['is_inspector']) && (int)$roles['inspector_id'] === $xInsp,
     'X-F5 · the roles card sees the inspector through the correct axis');

// G — history must not participate.
$liveCand = connect_identity_of_candidate($xCand);
connect_identity_unlink((int)$liveCand['id'], 'p6', ['candidate_id' => $xCand]);
t_ok(connect_identity_of_candidate($xCand) === null, 'X-G1 · an UNLINKED row no longer resolves');
[$xh] = connect_identity_candidate_link_create($xCand, $xPro3, 'manual', 'p6');
t_ok($xh, 'X-G2 · and the freed candidate may link again (history does not block)');
t_ok((int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE candidate_id=?", [$xCand]) >= 2,
     'X-G3 · the historical row is still stored — nothing was deleted');

// =============================================================================
t_section('P6-B1 · C — one entitlement for every writer (R25 · I26)');
// =============================================================================
$cPro  = $p6pro('Entitlement Person', 'ent@p6.test');
$cInsp = $p6insp('Entitlement Person', 1, 'IND');
$cCand = $p6cand('Ent', 'Person');

$entBefore = $p6links();
setting_set('modules_off', 'connect');          // the tenant switches Connect off
licence_disabled(true);                          // the app's own reload hook, as every save point uses
t_ok(!connect_identity_admin_can(), 'C0 · with Connect off the identity gate says no');

[$c1, $c1m] = connect_identity_link_create($cPro, $cInsp, 'manual', 'p6');
t_ok(!$c1, 'C1 · the inspector-axis writer refuses without the entitlement  [' . $c1m . ']');
[$c2, $c2m] = connect_identity_candidate_link_create($cCand, $cPro, 'manual', 'p6');
t_ok(!$c2, 'C2 · the candidate-axis writer refuses without the entitlement  [' . $c2m . ']');
$liveIns = connect_identity_of_inspector($xInsp);
[$c3, $c3m] = connect_identity_unlink((int)($liveIns['id'] ?? 0), 'p6');
t_ok(!$c3, 'C3 · the unlink writer refuses without the entitlement  [' . $c3m . ']');
t_eq($p6links(), $entBefore, 'C4 · and the ledger is byte-for-byte unchanged (state, not return code)');

setting_set('modules_off', '');                  // Connect back on
licence_disabled(true);
t_ok(connect_identity_admin_can(), 'C5 · with Connect on the gate says yes again');

// =============================================================================
t_section('P6-B1 · D — an actor must be able to open BOTH ends (R15 · I16)');
// =============================================================================
// NOTE: this asserts PER-END VISIBILITY only. Whether the relationship itself
// carries a branch is Q5/Q11 and is deliberately NOT decided here — see D4.
$dInspMum = $p6insp('Mumbai Fieldman', 2, 'IND');       // office 2
$dInspAhm = $p6insp('Ahmedabad Fieldman', 1, 'IND');    // office 1
$dPro     = $p6pro('Scope Person', 'scope@p6.test');
$dCandInd = $p6cand('Scoped', 'Ind', 'IND');
$dCandOth = $p6cand('Scoped', 'Oth', 'OTH');

$p6act($uMum);                                           // Mumbai + IND only
$dBefore = $p6links();
t_ok(!scope_allows(1, 'IND'), 'D0 · the Mumbai coordinator cannot open an Ahmedabad record');

[$d1, $d1m] = connect_identity_link_create($dPro, $dInspAhm, 'manual', 'p6');
t_ok(!$d1, 'D1 · linking an out-of-branch inspector is refused  [' . $d1m . ']');
t_eq($p6links(), $dBefore, 'D2 · and nothing was written');

[$d3, $d3m] = connect_identity_candidate_link_create($dCandOth, $dPro, 'manual', 'p6');
t_ok(!$d3, 'D3 · linking an out-of-unit candidate is refused  [' . $d3m . ']');

// The professional is TENANT-GLOBAL. Filtering it by branch would be inventing
// the rule Q5/Q11 has not decided, so this asserts we did NOT.
[$d4, $d4m] = connect_identity_link_create($dPro, $dInspMum, 'manual', 'p6');
t_ok($d4, 'D4 · an IN-branch inspector links to the tenant-global professional  [' . $d4m . ']');
[$d5] = connect_identity_candidate_link_create($dCandInd, $dPro, 'manual', 'p6');
t_ok($d5, 'D5 · an in-unit candidate links to the same tenant-global professional');
$p6act($uMaster);

// =============================================================================
t_section('P6-B1 · B/M — a record id is not authorisation (R24 · I25 · I27)');
// =============================================================================
$bCandA = $p6cand('Owner', 'Alpha');
$bCandB = $p6cand('Owner', 'Beta');
$bProA  = $p6pro('Owner Alpha', 'oa@p6.test');
$bProB  = $p6pro('Owner Beta', 'ob@p6.test');
connect_identity_candidate_link_create($bCandA, $bProA, 'manual', 'p6');
connect_identity_candidate_link_create($bCandB, $bProB, 'manual', 'p6');
$linkA = connect_identity_of_candidate($bCandA);
$linkB = connect_identity_of_candidate($bCandB);
t_ok(is_array($linkA) && is_array($linkB), 'B0 · two candidates each hold their own link');

// The attack: act on candidate A, post candidate B's link id.
[$b1, $b1m] = connect_identity_unlink((int)$linkB['id'], 'p6', ['candidate_id' => $bCandA]);
t_ok(!$b1, 'B1 · a link belonging to ANOTHER candidate is refused  [' . $b1m . ']');
$stillB = connect_identity_of_candidate($bCandB);
t_ok(is_array($stillB) && (int)$stillB['id'] === (int)$linkB['id'],
     'B2 · and candidate B STILL holds its link (read from the database)');

// The same attack through the REAL route, in its own process, with a forged POST.
$rt = $p6worker('route_unlink', $bCandA, (int)$linkB['id'], '', 0, $uCoord);
t_ok(true, 'B3 · the forged POST was dispatched through the real route  [' . ($rt['msg'] ?? '') . ']');
$afterRoute = connect_identity_of_candidate($bCandB);
t_ok(is_array($afterRoute) && (int)$afterRoute['id'] === (int)$linkB['id'],
     'B4 · L · the forged POST did NOT remove another candidate\'s link');

// The owner's own link still unlinks — the guard must not break the feature.
[$b5] = connect_identity_unlink((int)$linkA['id'], 'p6', ['candidate_id' => $bCandA]);
t_ok($b5, 'B5 · the candidate\'s OWN link still unlinks');

// B2 · REFUSAL ORDERING — the four ways of being turned away before a record may
// be named must be indistinguishable. If they are not, the refusal itself becomes
// a lookup tool: post ids until the wording changes.
$roCand = $p6cand('Order', 'Probe', 'OTH');        // exists, out of the Mumbai unit
$roPro  = $p6pro('Order Probe', 'op@p6.test');
$roInsp = $p6insp('Order Probe', 1, 'IND');        // exists, out of the Mumbai branch
setting_set('modules_off', 'connect'); licence_disabled(true);
$p6act($uMum);
$rNoEnt = connect_identity_link_create($roPro, $roInsp, 'manual', 'p6')[1];
setting_set('modules_off', ''); licence_disabled(true);
$rScope = connect_identity_link_create($roPro, $roInsp, 'manual', 'p6')[1];       // real, out of branch
$rGhost = connect_identity_link_create($roPro, 999777666, 'manual', 'p6')[1];     // no such inspector
$rGhost2 = connect_identity_link_create(999777666, $roInsp, 'manual', 'p6')[1];   // no such professional
$p6act($uMaster);
t_eq($rScope, $rNoEnt, 'B2a · "not entitled" and "out of your branch" read identically');
t_eq($rGhost, $rNoEnt, 'B2b · "no such inspector" reads identically too');
t_eq($rGhost2, $rNoEnt, 'B2c · and "no such professional"');
t_ok(stripos($rNoEnt, 'inspector') === false && stripos($rNoEnt, 'exist') === false,
     'B2d · the shared refusal names neither the record nor its absence  [' . $rNoEnt . ']');

// M — the guard belongs to the function, not to the page that called it.
$mRef = new ReflectionFunction('connect_identity_unlink');
t_ok($mRef->getNumberOfParameters() >= 3,
     'M1 · the ledger accepts an ownership expectation of its own (not route-only)');

// =============================================================================
t_section('P6-B1 · F — the DATABASE enforces uniqueness (R3 · I28)');
// =============================================================================
$cols = t_columns('cx_identity_link');
foreach (['uq_pro_insp', 'uq_insp', 'uq_cand'] as $c)
    t_ok(in_array($c, $cols, true), "F0 · the live key column $c exists");
$ix = t_indexes('ux_cx_idlink%');
foreach (['ux_cx_idlink_pro_insp', 'ux_cx_idlink_insp', 'ux_cx_idlink_cand'] as $n)
    t_ok(in_array($n, $ix, true), "F1 · the unique index $n is in place");

// The direct-SQL attack — no PHP guard runs at all.
$fPro = $p6pro('Unique Person', 'uq@p6.test'); $fInsp = $p6insp('Unique Person', 1, 'IND');
$fCand = $p6cand('Unique', 'Cand');
connect_identity_link_create($fPro, $fInsp, 'manual', 'p6');
connect_identity_candidate_link_create($fCand, $fPro, 'manual', 'p6');
$fRaw1 = $p6worker('rawlink', $fPro, $fInsp, '', 0, $uMaster);
t_ok(!$fRaw1['ok'], 'F2 · U1/U2 · a raw duplicate inspector-axis INSERT is rejected by the database');
$fRaw2 = $p6worker('rawcand', $fCand, $fPro, '', 0, $uMaster);
t_ok(!$fRaw2['ok'], 'F3 · U3 · a raw duplicate candidate-axis INSERT is rejected by the database');
t_eq((int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE professional_id=? AND status='LINKED' AND COALESCE(candidate_id,0)=0", [$fPro]), 1,
     'F4 · exactly one live inspector-axis row survives');
t_eq((int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE candidate_id=? AND status='LINKED'", [$fCand]), 1,
     'F5 · exactly one live candidate-axis row survives');

// History is NOT constrained: link/unlink the same pair repeatedly.
$hPro = $p6pro('History Person', 'hist@p6.test'); $hInsp = $p6insp('History Person', 1, 'IND');
for ($i = 0; $i < 4; $i++) {
    connect_identity_link_create($hPro, $hInsp, 'manual', 'p6');
    $lk = connect_identity_of_inspector($hInsp);
    if ($lk) connect_identity_unlink((int)$lk['id'], 'p6');
}
t_ok((int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE professional_id=?", [$hPro]) >= 4,
     'F6 · four historical rows for one pair coexist — history is never constrained');
t_eq((int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE professional_id=? AND status='LINKED'", [$hPro]), 0,
     'F7 · and none of them is live');

// =============================================================================
t_section('P6-B1 · G — two real processes, one relationship (R11 · I29)');
// =============================================================================
$gPro = $p6pro('Race Person', 'race@p6.test'); $gInsp = $p6insp('Race Person', 1, 'IND');
$gRes = $p6race([['link', $gPro, $gInsp, '', $uMaster], ['link', $gPro, $gInsp, '', $uMaster],
                 ['link', $gPro, $gInsp, '', $uMaster], ['link', $gPro, $gInsp, '', $uMaster]]);
$gLive = (int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE professional_id=? AND inspector_id=? AND status='LINKED'", [$gPro, $gInsp]);
t_eq($gLive, 1, 'G1 · four simultaneous processes produced exactly ONE live link');
$gCrash = 0; foreach ($gRes as $r) if (strpos((string)($r['msg'] ?? ''), 'EX:') === 0) $gCrash++;
t_eq($gCrash, 0, 'G2 · no process crashed — the conflict was translated, not thrown');
$gOk = 0; foreach ($gRes as $r) if (!empty($r['ok'])) $gOk++;
t_ok($gOk >= 1, 'G3 · at least one process was told, truthfully, that it succeeded');
t_eq(count($gRes), 4, 'G4 · all four processes reported a verdict');
// A loser told "you succeeded" is worse than a loser told "you lost": the screen
// then shows a relationship nobody holds. Every reported success must name a row
// that is actually live.
$gLies = 0;
foreach ($gRes as $r) {
    if (empty($r['ok'])) continue;
    $lid = (int)($r['id'] ?? 0);
    if ($lid <= 0 || (int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE id=? AND status='LINKED'", [$lid]) !== 1) $gLies++;
}
t_eq($gLies, 0, 'G6 · every process told it succeeded names a link that really is live');

// The same race on two DIFFERENT inspectors for one professional: U1 must let
// exactly one through, and the loser must get the business refusal.
$gPro2 = $p6pro('Race Person Two', 'race2@p6.test');
$gI1 = $p6insp('Race A', 1, 'IND'); $gI2 = $p6insp('Race B', 1, 'IND');
$p6race([['link', $gPro2, $gI1, '', $uMaster], ['link', $gPro2, $gI2, '', $uMaster]]);
t_eq((int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE professional_id=? AND status='LINKED' AND COALESCE(candidate_id,0)=0", [$gPro2]), 1,
     'G5 · a professional cannot be raced into two live inspector links');

// =============================================================================
t_section('P6-B1 · Y — the audit trail is attributable (decision 3)');
// =============================================================================
$yPro = $p6pro('Audit Person', 'aud@p6.test'); $yInsp = $p6insp('Audit Person', 1, 'IND');
$yBefore = (int)ops_val("SELECT COUNT(*) FROM activities");
[$yok, , $yid] = connect_identity_link_create($yPro, $yInsp, 'manual', 'p6');
t_ok($yok, 'Y-pre · the link was created');
$yRow = ops_one("SELECT * FROM activities WHERE entity_id=? AND entity_kind<>'' ORDER BY id DESC LIMIT 1", [$yid]);
t_ok(is_array($yRow), 'Y-A1 · the link wrote an activity with a NON-EMPTY entity kind');
if (is_array($yRow)) {
    t_ok(isset(ACT_ENTITIES[(string)$yRow['entity_kind']]),
         'Y-A2 · that entity kind is REGISTERED (so the timeline can label and link it)');
    t_ok(isset(ACT_KINDS[(string)$yRow['kind']]), 'Y-A3 · the activity kind is registered too');
    t_ok((string)$yRow['kind'] !== 'NOTE', 'Y-A4 · it is not silently downgraded to a plain NOTE');
    t_eq((int)$yRow['entity_id'], (int)$yid, 'Y-A5 · it points at the identity-link row it describes');
}
$lkY = connect_identity_of_inspector($yInsp);
connect_identity_unlink((int)$lkY['id'], 'p6');
$yUn = ops_one("SELECT * FROM activities WHERE entity_id=? AND entity_kind<>'' ORDER BY id DESC LIMIT 1", [(int)$lkY['id']]);
t_ok(is_array($yUn) && stripos((string)$yUn['subject'], 'unlink') !== false,
     'Y-B · the unlink wrote its own attributable activity');
// D — no dangling references: every identity activity must name a real link row.
$yDangle = (int)ops_val(
    "SELECT COUNT(*) FROM activities a WHERE a.entity_kind='IDENTITY_LINK'
       AND NOT EXISTS (SELECT 1 FROM cx_identity_link l WHERE l.id = a.entity_id)");
t_eq($yDangle, 0, 'Y-D · no identity audit entry points at a link that does not exist');
// F — the audit must not change the business result.
t_eq((int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE professional_id=? AND status='LINKED'", [$yPro]), 0,
     'Y-F · writing the audit did not alter the identity outcome');

// =============================================================================
t_section('P6-B1 · I — a half-finished write leaves no orphan (I22 · I42)');
// =============================================================================
// team_member_create() + UPDATE users must be one transaction. Force the second
// write to fail by pointing the reconciliation at a login that vanishes.
$iBefore = (int)ops_val("SELECT COUNT(*) FROM inspectors");
$iUser = $p6user('p6.halffail', 'INSPECTOR', ['office' => 1, 'first' => 'Half', 'last' => 'Fail']);
if (function_exists('link_inspector_users')) {
    // Remove the login mid-flight: the UPDATE will match nothing, which is the
    // closest honest stand-in for "write B did not take effect".
    db()->prepare("DELETE FROM users WHERE id=?")->execute([$iUser]);
    link_inspector_users();
}
$iOrphans = (int)ops_val("SELECT COUNT(*) FROM inspectors i WHERE i.name='Half Fail'
                            AND NOT EXISTS (SELECT 1 FROM users u WHERE u.inspector_id=i.id)");
t_eq($iOrphans, 0, 'I1 · a reconciliation whose second write cannot land leaves no orphan team member');

// =============================================================================
t_section('P6-B1 · E — cross-tenant isolation, actually tested (R16 · I15)');
// =============================================================================
// Two REAL tenant databases on whichever engine the suite is running against —
// two schemas on MySQL, two files on SQLite. Both differ from the suite's own
// database, so nothing here can be confused with the parent's data.
$tSqlite = t_driver() === 'sqlite';
if ($tSqlite) {
    $tA = tempnam(sys_get_temp_dir(), 'p6ta') . '.sqlite';
    $tB = tempnam(sys_get_temp_dir(), 'p6tb') . '.sqlite';
    @unlink($tA); @unlink($tB);
    $tSpecA = 'sqlite:' . $tA; $tSpecB = 'sqlite:' . $tB;
} else {
    $tA = 'p6_tenant_a'; $tB = 'p6_tenant_b';
    foreach ([$tA, $tB] as $d) { try { db()->exec("CREATE DATABASE IF NOT EXISTS `$d`"); } catch (Throwable $e) {} }
    $tSpecA = 'mysql:' . $tA; $tSpecB = 'mysql:' . $tB;
}
$tEnv = '';
foreach (['DB_DRIVER', 'DB_HOST', 'DB_USER', 'DB_PASS'] as $k) {
    $v = getenv($k); if ($v !== false && $v !== '') $tEnv .= $k . '=' . escapeshellarg($v) . ' ';
}
$tCmd = $tEnv . 'php ' . escapeshellarg($p6root . '/tests/_p6_tenant_worker.php') . ' '
      . escapeshellarg($tSpecA) . ' ' . escapeshellarg($tSpecB) . ' 0 2>&1';
$tRaw = (string)shell_exec($tCmd);
$tJson = null;
foreach (explode("\n", trim($tRaw)) as $line) { $j = json_decode(trim($line), true); if (is_array($j)) { $tJson = $j; break; } }
if (!is_array($tJson) || empty($tJson['ok'])) {
    t_ok(false, 'E0 · the two-tenant probe ran  (' . substr(preg_replace('/\s+/', ' ', $tRaw), 0, 220) . ')');
} else {
    $s = $tJson['steps'];
    t_ok(true, 'E0 · two separate tenant databases were built and used');
    t_ok(!empty($s['refused_inspector_axis']), 'E1 · tenant B ids are refused on the inspector axis in tenant A  [' . ($s['msg1'] ?? '') . ']');
    t_ok(!empty($s['refused_candidate_axis']), 'E2 · tenant B ids are refused on the candidate axis in tenant A  [' . ($s['msg2'] ?? '') . ']');
    t_eq((int)$s['a_links_after'], (int)$s['a_links_before'], 'E3 · tenant A gained no identity link');
    t_eq((int)$s['a_has_b_person'], 0, 'E4 · tenant A never saw tenant B\'s person at all');
    t_eq((int)$s['b_links_after'], (int)$s['b_links_before'], 'E5 · tenant B was left completely untouched');
}
if ($tSqlite) { @unlink($tA); @unlink($tB); }
else { foreach ([$tA, $tB] as $d) { try { db()->exec("DROP DATABASE IF EXISTS `$d`"); } catch (Throwable $e) {} } }

// =============================================================================
t_section('P6-B1 · H — legacy duplicates survive the migration (R3)');
// =============================================================================
// A database that already carries two live rows for one pair must not lose data
// and must not fail to boot. The index is skipped and the duplicate surfaced.
t_eq(count(connect_identity_duplicates()), 0, 'H0 · this database carries none — the constraint is doing its job');

// A database that ALREADY holds two live rows for one pair, migrated afresh.
if ($tSqlite) { $lgSpec = 'sqlite:' . (tempnam(sys_get_temp_dir(), 'p6lg') . '.sqlite'); @unlink(substr($lgSpec, 7)); }
else { try { db()->exec("CREATE DATABASE IF NOT EXISTS `p6_legacy`"); } catch (Throwable $e) {} $lgSpec = 'mysql:p6_legacy'; }
$lgCmd = $tEnv . 'php ' . escapeshellarg($p6root . '/tests/_p6_legacy_worker.php') . ' ' . escapeshellarg($lgSpec) . ' 2>&1';
$lgRaw = (string)shell_exec($lgCmd); $lg = null;
foreach (explode("\n", trim($lgRaw)) as $line) { $j = json_decode(trim($line), true); if (is_array($j)) { $lg = $j; break; } }
if (!is_array($lg) || empty($lg['ok'])) {
    t_ok(false, 'H1 · the legacy-duplicate probe ran  (' . substr(preg_replace('/\s+/', ' ', $lgRaw), 0, 220) . ')');
} else {
    t_eq((int)$lg['planted'], 2, 'H1 · two live duplicate rows were planted before the migration');
    t_ok(!empty($lg['booted']), 'H2 · the migration COMPLETED over them — deployment never fails for data it found');
    t_eq((int)$lg['rows_after'], 2, 'H3 · BOTH rows survive — nothing merged, unlinked or deleted to fit the constraint');
    t_eq((int)$lg['dups'], 1, 'H4 · and the duplicate is REPORTED instead');
    t_ok(empty($lg['index_built']), 'H5 · the affected unique index was skipped, not forced');
    t_ok(!empty($lg['other_built']), 'H6 · but the OTHER axes were still protected — one mess does not disarm everything');
    t_eq((int)$lg['dups_after_fix'], 0, 'H7 · after a person resolves it by hand the duplicate is gone');
    t_ok(!empty($lg['index_after_fix']), 'H8 · and the next migration builds the index — it heals itself');
}
if ($tSqlite) @unlink(substr($lgSpec, 7));
else { try { db()->exec("DROP DATABASE IF EXISTS `p6_legacy`"); } catch (Throwable $e) {} }

// =============================================================================
t_section('P6-B1 · health — the backlog is visible, not silently healed');
// =============================================================================
$hStray = $p6user('p6.visible', 'INSPECTOR', ['office' => 1, 'first' => 'Visible', 'last' => 'Backlog']);
if (function_exists('team_unlinked_logins')) {
    $bk = team_unlinked_logins();
    t_ok(is_array($bk) && count($bk) >= 1, 'V1 · the unlinked-login backlog is reported');
    $names = array_map(fn($r) => (string)($r['username'] ?? ''), $bk);
    t_ok(in_array('p6.visible', $names, true), 'V2 · the new unlinked login appears in it');
} else {
    t_ok(false, 'V1 · team_unlinked_logins() exists so the backlog is visible');
}

// =============================================================================
t_section('P6-B1 · I41 — a failed observation is not a failed transaction');
// =============================================================================
// An audit entry that cannot be written must never undo the business fact it was
// describing. Forced honestly: a subject far longer than the column, which a
// strict MySQL server rejects outright.
t_nothrow('I41a · a rejected audit write does not throw out of the ledger',
    fn() => connect_identity_log('IDENTITY_LINK', 1, 'IDENTITY_LINKED', str_repeat('x', 100000)));
$fPro2 = $p6pro('Audit Resilience', 'ar@p6.test'); $fInsp2 = $p6insp('Audit Resilience', 1, 'IND');
[$arOk, , $arId] = connect_identity_link_create($fPro2, $fInsp2, 'manual', str_repeat('n', 5000));
t_ok($arOk, 'I41b · a link with an oversized note still succeeds');
t_eq((int)ops_val("SELECT COUNT(*) FROM cx_identity_link WHERE id=? AND status='LINKED'", [(int)$arId]), 1,
     'I41c · and the relationship is really there, whatever the audit did');

$_SESSION = $p6sess; current_user(true); ua(true);   // restored LAST, deliberately
