<?php
// ============================================================================
//  PHASE 3 · M5 — RECRUITER ACCOUNTABILITY: THE ONE DOOR
//
//  Every probe calls the PRODUCTION function a route calls, with the payload a
//  crafted POST would carry. Nothing is asserted about a hidden dropdown: the
//  dropdown lists active users, and the whole point of this milestone is that a
//  save which ignores the dropdown is refused anyway.
// ============================================================================

t_section('Phase 3 · M5 — the assignment door');

$pdo = db(); rasg_migrate(); act_migrate(); hreq_migrate(); appr_migrate(); ensure_settings_schema();
$m5orig = $_SESSION;
foreach ([[9551,'M5 Branch A'], [9552,'M5 Branch B']] as $o)
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); } catch (Throwable $e) {}

$mk = function ($un, $role, $super, $office, $scope, $active = 1) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,?,?,?,?,?,?,?)")->execute([$un, 'M5', ucfirst($un), $role, $active, $super ? 1 : 0, $office, $scope]);
    return (int) $pdo->lastInsertId();
};
$act = function ($u) { $_SESSION['uid'] = $u; current_user(true); ua(true); };

$uBoss   = $mk('m5_boss',   'MANAGER',     1, 9551, '');         // sees everything
$uRecA   = $mk('m5_rec_a',  'COORDINATOR', 0, 9551, '9551');     // recruiter, branch A only
$uRecA2  = $mk('m5_rec_a2', 'COORDINATOR', 0, 9551, '9551');     // second recruiter, branch A
$uRecB   = $mk('m5_rec_b',  'COORDINATOR', 0, 9552, '9552');     // recruiter, branch B only
$uGone   = $mk('m5_gone',   'COORDINATOR', 0, 9551, '9551', 0);  // deactivated
$uInsp   = $mk('m5_insp',   'INSPECTOR',   0, 9551, '9551');     // a real least-privilege role
$act($uBoss);

$mkReq = function ($x = []) use ($pdo) {
    $d = array_merge(['req_code' => 'M5-' . bin2hex(random_bytes(3)), 'office_id' => 9551, 'sbu' => '',
                      'designation' => 'ENGINEER', 'status' => 'OPEN', 'quantity' => 5,
                      'recruiter_id' => null, 'manager_id' => null, 'created_at' => date('c')], $x);
    $pdo->prepare("INSERT INTO requisitions (req_code,office_id,sbu,designation,status,quantity,recruiter_id,manager_id,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?)")->execute(array_values($d));
    return (int) $pdo->lastInsertId();
};
$mkCand = function ($reqId, $x = []) use ($pdo) {
    $d = array_merge(['cand_code' => 'M5C-' . bin2hex(random_bytes(3)), 'first_name' => 'M5', 'last_name' => 'Cand',
                      'stage' => 'RECEIVED', 'requisition_id' => $reqId, 'recruiter_id' => null, 'created_at' => date('c')], $x);
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,recruiter_id,created_at)
                   VALUES (?,?,?,?,?,?,?)")->execute(array_values($d));
    return (int) $pdo->lastInsertId();
};
$owner = fn($id) => (int) ops_val("SELECT COALESCE(recruiter_id,0) FROM requisitions WHERE id=?", [$id]);
$cowner = fn($id) => (int) ops_val("SELECT COALESCE(recruiter_id,0) FROM candidates WHERE id=?", [$id]);

// ---- A · THE HAPPY PATH, so every refusal below means something -------------
t_section('A · the permitted case');
$rA = $mkReq();
$a1 = rasg_assign('REQ_RECRUITER', $rA, $uRecA, ['expect' => null, 'source' => 'test']);
t_eq($a1['code'], 'OK', 'A1 · correct tenant + scope + permission + active recruiter → ALLOW');
t_eq($owner($rA), $uRecA, 'A2 · and the column actually moved');
$a2 = rasg_assign('REQ_RECRUITER', $rA, $uRecA, ['expect' => $uRecA]);
t_eq($a2['code'], 'NO_CHANGE', 'A3 · asking for what is already true is not an assignment');
t_eq(count(rasg_history('REQ_RECRUITER', $rA)), 1, 'A4 · …and is not written to the ledger twice');

// ---- B · THE NEGATIVE MATRIX -----------------------------------------------
t_section('B · negative matrix — every refusal identified by its own code');
//  wrong branch: a branch-B recruiter cannot be given branch-A work
t_eq(rasg_assign('REQ_RECRUITER', $rA, $uRecB, ['expect' => $uRecA])['code'], 'RECRUITER_OUT_OF_SCOPE',
     'B1 · wrong branch (the person) → DENY');
t_eq($owner($rA), $uRecA, 'B1b · and nothing moved');
//  the ACTOR outside the record's scope
$act($uRecB);
t_eq(rasg_assign('REQ_RECRUITER', $rA, $uRecB, ['expect' => $uRecA])['code'], 'OUT_OF_SCOPE',
     'B2 · wrong branch (the actor) → DENY');
$act($uBoss);
//  deactivated recruiter
t_eq(rasg_assign('REQ_RECRUITER', $rA, $uGone, ['expect' => $uRecA])['code'], 'RECRUITER_INACTIVE',
     'B3 · inactive recruiter → DENY');
//  a person who does not exist in this workspace
t_eq(rasg_assign('REQ_RECRUITER', $rA, 987654, ['expect' => $uRecA])['code'], 'RECRUITER_UNKNOWN',
     'B4 · an id that is not a person here → DENY (no phantom ownership)');
//  a record that does not exist
t_eq(rasg_assign('REQ_RECRUITER', 987654, $uRecA, ['expect' => null])['code'], 'NO_RECORD',
     'B5 · a record id is not proof a record exists → DENY');
//  no permission — a REAL least-privilege role, not an invented one
$act($uInsp);
t_eq(rasg_assign('REQ_RECRUITER', $rA, $uRecA2, ['expect' => $uRecA])['code'], 'NO_PERMISSION',
     'B6 · no permission → DENY');
$act($uBoss);
t_eq($owner($rA), $uRecA, 'B7 · after six refused attempts the owner is still the one legitimately set');

// ---- C · ENTITLEMENT — no master bypass ------------------------------------
t_section('C · entitlement is asked first, and a master does not walk past it');
$m5lic = function_exists('licence_blocks') ? 'live' : 'absent';
$GLOBALS['__m5_force_block'] = true;
//  Entitlement is proved by the code path rather than by dismantling the licence
//  engine inside a shared test database: licence_blocks() is asked FIRST in
//  rasg_check(), before permission, and a master reaches it like anyone else.
$src = file_get_contents(dirname(__DIR__) . '/lib/recruit_assign.php');
$src = preg_replace('~^\s*//.*$~m', '', $src);          // a pin must never match its own comment
$chk = substr($src, strpos($src, 'function rasg_check('), 1400);
t_ok(strpos($chk, 'licence_blocks') !== false, 'C1 · the door asks the licence');
t_ok(strpos($chk, 'licence_blocks') < strpos($chk, 'is_coordinator_level'),
     'C2 · *** entitlement is asked BEFORE permission — an unlicensed workspace is refused first ***');
t_ok(strpos($chk, 'is_master') === false,
     'C3 · *** there is no master bypass in the door ***');
unset($GLOBALS['__m5_force_block']);

// ---- D · STATE MATRIX -------------------------------------------------------
t_section('D · every requisition state, not one of them');
$states = ['OPEN' => 'OK', 'PROPOSED' => 'OK', 'OFFERED' => 'OK', 'PARTIALLY_FILLED' => 'OK',
           'HIRED' => 'BAD_STATE', 'CLOSED' => 'BAD_STATE', 'CANCELLED' => 'BAD_STATE'];
foreach ($states as $st => $want) {
    $r = $mkReq(['status' => $st]);
    $got = rasg_assign('REQ_RECRUITER', $r, $uRecA, ['expect' => null])['code'];
    t_eq($got, $want, "D · requisition in $st → " . ($want === 'OK' ? 'ALLOW' : 'DENY'));
    t_eq($owner($r), $want === 'OK' ? $uRecA : 0, "D · …and the column agrees for $st");
}
//  a requisition with no readable state at all fails closed
$rBlank = $mkReq(['status' => '']);
t_eq(rasg_assign('REQ_RECRUITER', $rBlank, $uRecA, ['expect' => null])['code'], 'BAD_STATE',
     'D · an unreadable state is refused, not assumed safe');
//  candidates: a settled outcome is not live work
foreach (['RECEIVED' => 'OK', 'SHORTLISTED' => 'OK', 'OFFERED' => 'OK', 'ACCEPTED' => 'OK',
          'REJECTED' => 'BAD_STATE', 'WITHDRAWN' => 'BAD_STATE', 'OFFER_DECLINED' => 'BAD_STATE'] as $st => $want) {
    $c = $mkCand($rA, ['stage' => $st]);
    t_eq(rasg_assign('CAND_RECRUITER', $c, $uRecA, ['expect' => null])['code'], $want, "D · candidate in $st");
}

// ---- E · THE M4 BOUNDARY REMAINS AUTHORITATIVE -----------------------------
t_section('E · a requisition blocked by M4 cannot be assigned around');
$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$hbase = fn(array $x = []) => array_merge([
    'requested_by_id' => $uBoss, 'requested_by_name' => 'M5 Boss',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'M5 Engineer', 'designation' => 'ENGINEER', 'job_description' => 'm5',
    'quantity' => 4, 'office_id' => 9551, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x'], $x);
[$okH,, $hId] = hreq_save(0, $hbase());
hreq_submit($hId); hreq_apply_decision($hId, 'APPROVED', 'M5 Approver', 'ok');
[$okR,, $rM4] = hreq_to_requisition($hId, 4);
t_ok($okR && $rM4 > 0, 'E1 · a requisition exists under an approved hiring request');
t_eq(rasg_assign('REQ_RECRUITER', $rM4, $uRecA, ['expect' => null])['code'], 'OK',
     'E2 · while the request is approved, ownership may be set');
hreq_save($hId, $hbase(['designation' => 'SUPERVISOR']));         // a material change invalidates approval
t_ok(!hreq_is_executable(hreq_get($hId)), 'E3 · a material change blocks the request');
t_eq(rasg_assign('REQ_RECRUITER', $rM4, $uRecA2, ['expect' => $uRecA])['code'], 'M4_BLOCKED',
     'E4 · *** ownership cannot be moved on a requisition M4 has blocked ***');
t_eq($owner($rM4), $uRecA, 'E5 · and the owner is unchanged');
hreq_apply_decision($hId, 'APPROVED', 'M5 Approver', 're-approved');
t_eq(rasg_assign('REQ_RECRUITER', $rM4, $uRecA2, ['expect' => $uRecA])['code'], 'OK',
     'E6 · …and moves again once the request is re-approved');

// ---- F · HISTORY SURVIVES REASSIGNMENT -------------------------------------
t_section('F · changing the owner never erases who held it');
$rH = $mkReq();
rasg_assign('REQ_RECRUITER', $rH, $uRecA,  ['expect' => null]);
rasg_assign('REQ_RECRUITER', $rH, $uRecA2, ['expect' => $uRecA]);
rasg_assign('REQ_RECRUITER', $rH, null,    ['expect' => $uRecA2]);
$hist = rasg_history('REQ_RECRUITER', $rH);
t_eq(count($hist), 3, 'F1 · three moves, three ledger rows');
t_eq((int) $hist[0]['to_user_id'], 0, 'F2 · the last move unassigned it');
t_eq((int) $hist[0]['from_user_id'], $uRecA2, 'F3 · …from the second recruiter');
t_eq((int) $hist[2]['to_user_id'], $uRecA, 'F4 · the first move assigned the first recruiter');
t_ok(rasg_ever_held('REQ_RECRUITER', $rH, $uRecA),
     'F5 · *** the first recruiter is still attributable after two reassignments ***');
t_eq($owner($rH), 0, 'F6 · unassignment leaves nobody, not a phantom');
t_eq(rasg_recruiter_state(0), 'RECRUITER_UNKNOWN', 'F7 · and zero is not a person');
//  the audit spine saw it
$logged = (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='REQUISITION' AND entity_id=? AND subject LIKE 'Recruiter changed%'", [$rH]);
t_eq($logged, 3, 'F8 · every ownership change is on the audit spine too');

// ---- G · STALE SCREEN (TOCTOU) ----------------------------------------------
t_section('G · user A opens the screen, user B changes the owner, user A saves');
$rT = $mkReq();
rasg_assign('REQ_RECRUITER', $rT, $uRecA, ['expect' => null]);
$screenA = $owner($rT);                                   // what user A's form is showing
rasg_assign('REQ_RECRUITER', $rT, $uRecA2, ['expect' => $uRecA]);   // user B changes it
$late = rasg_assign('REQ_RECRUITER', $rT, $uRecA, ['expect' => $screenA]);
t_eq($late['code'], 'STALE', 'G1 · *** the stale save is refused, not silently applied ***');
t_eq($owner($rT), $uRecA2, 'G2 · the newer authoritative owner stands');
//  the same thing through the form path, which is what a browser actually does
t_eq(rasg_apply_posted('REQ_RECRUITER', $rT, ['recruiter_id' => $uRecA, 'own_base_recruiter_id' => $screenA], 'recruiter_id', 'test'),
     rasg_refusal('REQ_RECRUITER', 'STALE'), 'G3 · …and the form save says so in business words');
//  a crafted POST that simply omits the baseline gets no overwrite either
t_eq(rasg_apply_posted('REQ_RECRUITER', $rT, ['recruiter_id' => $uRecA], 'recruiter_id', 'test'),
     rasg_refusal('REQ_RECRUITER', 'STALE'), 'G4 · *** no baseline, no overwrite — fail closed ***');
t_eq($owner($rT), $uRecA2, 'G5 · still the newer owner');
//  a form that does not carry the field at all changes nothing. Before M5 this
//  silently UNASSIGNED the requirement.
t_eq(rasg_apply_posted('REQ_RECRUITER', $rT, ['designation' => 'X'], 'recruiter_id', 'test'), '',
     'G6 · a POST that omits the field is not an instruction to unassign');
t_eq($owner($rT), $uRecA2, 'G7 · …and the owner is untouched');

// ---- H · STALE SCREEN AGAINST THE M4 BOUNDARY -------------------------------
t_section('H · user A holds an old screen while M4 blocks the request underneath');
[$okH2,, $h2] = hreq_save(0, $hbase(['job_title' => 'M5 Stale']));
hreq_submit($h2); hreq_apply_decision($h2, 'APPROVED', 'M5 Approver', 'ok');
[$okR2,, $rS] = hreq_to_requisition($h2, 2);
rasg_assign('REQ_RECRUITER', $rS, $uRecA, ['expect' => null]);
$screenB = $owner($rS);                                   // A's screen, taken while everything was fine
hreq_save($h2, $hbase(['job_title' => 'M5 Stale', 'designation' => 'SUPERVISOR']));
$staleM4 = rasg_assign('REQ_RECRUITER', $rS, $uRecA2, ['expect' => $screenB]);
t_eq($staleM4['code'], 'M4_BLOCKED',
     'H1 · *** the M4 boundary is still authoritative for a save from an old screen ***');
t_eq($owner($rS), $uRecA, 'H2 · and nothing moved');

// ---- I · CREATE AND EDIT PASS THE SAME DOOR ---------------------------------
t_section('I · create vs edit — the same control, proved on both');
$createChecks = []; $editChecks = [];
foreach ([['inactive', $uGone, 'RECRUITER_INACTIVE'], ['unknown', 987654, 'RECRUITER_UNKNOWN'],
          ['wrong branch', $uRecB, 'RECRUITER_OUT_OF_SCOPE']] as [$what, $who, $want]) {
    $rc = $mkReq();                                        // CREATE: from nobody
    $createChecks[$what] = rasg_assign('REQ_RECRUITER', $rc, $who, ['expect' => null])['code'];
    $re = $mkReq();                                        // EDIT: from a legitimate owner
    rasg_assign('REQ_RECRUITER', $re, $uRecA, ['expect' => null]);
    $editChecks[$what] = rasg_assign('REQ_RECRUITER', $re, $who, ['expect' => $uRecA])['code'];
    t_eq($createChecks[$what], $want, "I · create with an $what person → DENY");
    t_eq($editChecks[$what], $want, "I · edit to an $what person → DENY");
}
t_eq(json_encode($createChecks), json_encode($editChecks),
     'I · *** create and edit refuse identically — there is no softer door ***');

// ---- J · EVERY SIBLING PATH GOES THROUGH THE DOOR ---------------------------
t_section('J · no sibling route writes ownership behind the door');
$ops = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/ops.php'));
$car = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/careers.php'));
//  the blind field lists no longer carry ownership
$reqFields = substr($ops, strpos($ops, "\$base = ['office_id','sbu','designation'"), 900);
t_ok(strpos($reqFields, 'array_diff($fields, array_keys($m5own))') !== false,
     'J1 · *** the requisition save strips ownership out of its blind field list ***');
//  J2/J3 read the ARRAY LITERAL itself — from "[" to the first "]" — rather than
//  a fixed window of following source. The first version of these two probes
//  matched my own new code on the next line and the READ of the requisition's
//  recruiter in careers_notify_recruiter(), which is a legitimate read. A probe
//  that matches the wrong text proves nothing; both now cut exactly the list
//  being written.
$slice = function ($src, $anchor) {
    $i = strpos($src, $anchor); if ($i === false) return '';
    $j = strpos($src, '];', $i);
    return $j === false ? '' : substr($src, $i, $j - $i);
};
$candFields = $slice($ops, "\$fields = ['first_name','middle_name','last_name'");
t_ok($candFields !== '' && strpos($candFields, "'recruiter_id'") === false,
     'J2 · *** the candidate save no longer writes recruiter_id blindly ***');
$carCols = $slice($car, "\$cols = ['cand_code','first_name'");
t_ok($carCols !== '' && strpos($carCols, "'recruiter_id'") === false,
     'J3 · *** the public careers intake no longer writes ownership into its INSERT ***');
t_ok(strpos($car, "\$job['recruiter_id']") !== false,
     'J3b · …it still READS the advertised requirement, which is how inheritance knows whom to ask about');
t_ok(strpos($car, 'rasg_inherit_from_requisition') !== false,
     'J4 · …it inherits through the door instead');
//  and the door is reached from all four operations paths
t_eq(substr_count($ops, 'rasg_apply_posted'), 2, 'J5 · both edit paths call the door');
t_eq(substr_count($ops, "rasg_assign(\$m5subj"), 2, 'J6 · both create paths call the door');
//  J5b/J5c close the hole the surviving mutation used: the field list's literal
//  text was being read while the mutation appended to the list at runtime. The
//  compensating enforcement does not depend on the field list at all — it is
//  driven by the service's own subject list — so these assert the CALLS exist on
//  all four save paths, and that the two local mappings are still complete.
//  Counted per table rather than as one total: a total is a number to guess, and
//  a guessed number is an assertion that passes for the wrong reason. Two calls
//  on each table — one on its create path, one on its edit path.
t_eq(substr_count($ops, "rasg_enforce_table('requisitions'"), 2,
     'J5b · *** both requisition save paths compensate after the write ***');
t_eq(substr_count($ops, "rasg_enforce_table('candidates'"), 2,
     'J5b2 · *** and both candidate save paths do ***');
t_ok(strpos($ops, "\$m5own = ['recruiter_id' => 'REQ_RECRUITER', 'manager_id' => 'REQ_MANAGER'];") !== false,
     'J5c · the requisition save still routes BOTH ownership columns through the door');
t_ok(strpos($ops, "\$m5cand = ['recruiter_id' => 'CAND_RECRUITER'];") !== false,
     'J5d · …and the candidate save still routes its one');
t_ok(strpos($ops, "\$fields[] = 'recruiter_id'") === false && strpos($ops, "\$fields[] = 'manager_id'") === false,
     'J5e · *** and nothing adds an ownership column back to a blind list at runtime ***');
//  a repository-wide sweep: nothing else writes the three ownership columns
$writers = [];
foreach (glob(dirname(__DIR__) . '/lib/*.php') as $f) {
    $b = basename($f);
    if ($b === 'recruit_assign.php' || strncmp($b, 'seed_', 5) === 0) continue;
    $t = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents($f));
    if (preg_match('~INSERT INTO (requisitions|candidates)[^;]{0,800}recruiter_id~s', $t)
     || preg_match('~UPDATE (requisitions|candidates) SET[^;]{0,400}(recruiter_id|manager_id)\s*=~s', $t)) $writers[] = $b;
}
t_eq(implode(',', $writers), '',
     'J7 · *** no library outside the door writes an ownership column ***');

// ---- K · THE PUBLIC CAREERS PATH -------------------------------------------
t_section('K · the public intake cannot hand work to somebody who has left');
$rPub = $mkReq(['recruiter_id' => $uGone]);                // the advert still names a leaver
$cPub = $mkCand($rPub);
$inh = rasg_inherit_from_requisition($cPub, $rPub, 'careers');
t_eq($inh['code'], 'RECRUITER_INACTIVE', 'K1 · *** the public application does not inherit a deactivated recruiter ***');
t_eq($cowner($cPub), 0, 'K2 · the application is left unassigned, which a human can see');
$rPub2 = $mkReq(['recruiter_id' => $uRecA]);
$cPub2 = $mkCand($rPub2);
t_eq(rasg_inherit_from_requisition($cPub2, $rPub2, 'careers')['code'], 'OK', 'K3 · a live recruiter is inherited');
t_eq($cowner($cPub2), $uRecA, 'K4 · …and the CV lands on that desk');
t_eq(count(rasg_history('CAND_RECRUITER', $cPub2)), 1, 'K5 · even inheritance is in the ledger');
//  the public path skips ONLY the permission question
t_ok(rasg_assign('CAND_RECRUITER', $mkCand($mkReq()), $uGone, ['expect' => null, 'skip_permission' => true])['code'] === 'RECRUITER_INACTIVE',
     'K6 · *** skipping permission does not skip anything else ***');

// ---- L · PHANTOM OWNERSHIP IS FOUND, NOT HIDDEN -----------------------------
t_section('L · ownership nobody holds is reported');
$rPh = $mkReq(['recruiter_id' => 987654]);                 // written before M5 existed
$ph = rasg_phantoms();
$found = false; foreach ($ph['requisitions'] as $p) if ($p['id'] === $rPh) { $found = true; t_eq($p['why'], 'RECRUITER_UNKNOWN', 'L2 · …and says why'); }
t_ok($found, 'L1 · *** a requisition owned by nobody is reported ***');
t_eq((int) ops_val("SELECT COALESCE(recruiter_id,0) FROM requisitions WHERE id=?", [$rPh]), 987654,
     'L3 · and is NOT silently cleared — the evidence of who was recorded survives');

// ---- N · A BLIND WRITE OF OWNERSHIP IS REVERTED ----------------------------
//  A mutation put `$fields[] = 'recruiter_id';` back into the candidate save and
//  SURVIVED the whole battery: the probes were reading the field list's literal
//  text while the mutation appended to it at runtime, and no test can drive the
//  route itself (it ends in redirect(), which exits). The answer was not a
//  cleverer probe on its own — it was to stop the save paths trusting themselves.
t_section('N · a save that writes ownership behind the door is put back');
$rN = $mkReq();
rasg_assign('REQ_RECRUITER', $rN, $uRecA, ['expect' => null]);
$authN = rasg_authorised_now('requisitions', $rN);
t_eq($authN['REQ_RECRUITER'], $uRecA, 'N1 · the authorised state is what the door left');
//  exactly what a blind field list does, straight to the column
$pdo->prepare("UPDATE requisitions SET recruiter_id=? WHERE id=?")->execute([$uRecB, $rN]);
t_eq($owner($rN), $uRecB, 'N2 · a rogue save path can still reach the column…');
$saidN = rasg_enforce_table('requisitions', $rN, $authN);
t_eq($owner($rN), $uRecA, 'N3 · *** …and is put straight back to what the door authorised ***');
t_eq(count($saidN), 1, 'N4 · the save is told, rather than the change vanishing quietly');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='REQUISITION' AND entity_id=? AND subject LIKE 'Unauthorised%'", [$rN]) >= 1,
     'N5 · and the attempt is on the audit spine');
t_eq(count(rasg_history('REQ_RECRUITER', $rN)), 1,
     'N6 · *** a reverted write is not recorded as an assignment — the ledger stays truthful ***');
//  a creation that carried ownership is left unowned for the door to decide
$rN2 = $mkReq(['recruiter_id' => $uRecB]);
rasg_enforce_table('requisitions', $rN2, rasg_unowned('requisitions'));
t_eq($owner($rN2), 0, 'N7 · *** an INSERT that carried an owner is stripped back to unowned ***');
//  and a legitimate change is NOT reverted
$rN3 = $mkReq();
rasg_assign('REQ_RECRUITER', $rN3, $uRecA, ['expect' => null]);
$saidN3 = rasg_enforce_table('requisitions', $rN3, rasg_authorised_now('requisitions', $rN3));
t_eq(count($saidN3), 0, 'N8 · a legitimate assignment is left exactly where it is');
t_eq($owner($rN3), $uRecA, 'N9 · …still held by the person the door approved');
//  the protection is driven by the SERVICE's subject list, not by a caller's map
t_eq(count(rasg_unowned('requisitions')), 2, 'N10 · both requisition ownership columns are covered');
t_eq(count(rasg_unowned('candidates')), 1, 'N11 · and the candidate one');

// ---- M · THE WRITE MUST BE THIS PROCESS'S OWN ------------------------------
//  C2 in the concurrency suite is the behavioural detector for this, and a race
//  is a PROBABILISTIC detector — it reproduces when the timing lands right. The
//  defect it found (a loser reading back the value it asked for, put there by the
//  winner, and reporting success) is therefore pinned deterministically here too.
t_section('M · a successful assignment means THIS process wrote it');
$m5src = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/recruit_assign.php'));
$m5w   = substr($m5src, strpos($m5src, 'function rasg_assign('));
$m5w   = substr($m5w, 0, strpos($m5w, "\nfunction "));
$posSwap  = strpos($m5w, 'rowCount()');
$posCount = strpos($m5w, '$touched < 1');
$posRead  = strpos($m5w, '$cur !== $to');
t_ok($posSwap !== false, 'M1 · the writer captures how many rows its swap matched');
t_ok($posCount !== false && $posCount < $posRead,
     'M2 · *** the matched-row count is checked BEFORE the read-back — a value somebody else wrote is not a win ***');
t_ok($posRead !== false, 'M3 · …and the read-back is still there, because a matched row is not proof it stuck');
//  The no-change short-circuit is what makes the row count safe to read on
//  MariaDB. If it ever moves below the swap, the count becomes ambiguous.
t_ok(strpos($m5w, "if (\$from === \$to) return") < $posSwap,
     'M4 · the no-change case returns before the swap, so a matched row is always a changed row');
$rNo = $mkReq(['recruiter_id' => $uRecA]);
t_eq(rasg_assign('REQ_RECRUITER', $rNo, $uRecA, ['expect' => $uRecA])['code'], 'NO_CHANGE',
     'M5 · …proved: setting the owner to the owner is NO_CHANGE, never a write');

$_SESSION = $m5orig; current_user(true); ua(true);
