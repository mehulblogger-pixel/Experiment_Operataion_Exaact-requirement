<?php
// ============================================================================
//  PHASE 4 — MULTI-SOURCE FULFILMENT: THE INVARIANTS
//
//  ONE approved demand, fulfilled from several sources, staying ONE demand.
//  Every probe calls the PRODUCTION function the route calls. Fixtures are built
//  here and nowhere else, so no probe depends on another file having run first.
//
//  The invariants under test (docs/phase4/P4-BUSINESS-INVARIANTS.md):
//    I1  FULFILLED ≤ ALLOCATED ≤ AUTHORISED, always
//    I2  SUM(live source allocations) ≤ AUTHORISED
//    I3  remaining and unallocated can never be negative
//    I4  an allocation belongs to exactly one requirement
//    I5  a source is never credited with more people than it was promised
//    I6  closing an allocation keeps what it delivered and returns only the rest
//    I7  a refused operation writes nothing
// ============================================================================

t_section('Phase 4 — multi-source fulfilment: the invariants');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate(); rful_migrate();
$p4o = $_SESSION;
foreach ([[9641, 'P4 Branch A'], [9642, 'P4 Branch B']] as $o)
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); } catch (Throwable $e) {}
$p4mk = function ($un, $role, $super, $office, $scope) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,'P4',?,1,?,?,?)")->execute([$un, $role, $super ? 1 : 0, $office, $scope]);
    return (int) $pdo->lastInsertId(); };
$p4act  = function ($u) { $_SESSION['uid'] = $u; current_user(true); ua(true); };
$uBoss  = $p4mk('p4a_boss', 'MANAGER', 1, 9641, '');
$p4act($uBoss);

$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$p4base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uBoss, 'requested_by_name' => 'P4 Boss',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'P4 Engineer', 'designation' => 'ENGINEER', 'job_description' => 'p4',
    'quantity' => 10, 'office_id' => 9641, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x'], $x);

//  A live, approved, executable requirement for N people — the thing Phase 4
//  allocates against. Built through the production M4 path, never by raw SQL,
//  so no probe can pass against a requirement the product would refuse.
$p4req = function ($qty, array $x = []) use ($p4base) {
    [$ok,, $h] = hreq_save(0, $p4base(['quantity' => $qty] + $x)); if (!$ok) return 0;
    hreq_submit($h); hreq_apply_decision($h, 'APPROVED', 'P4 Approver', 'ok');
    [$okR,, $rq] = hreq_to_requisition($h, $qty);
    return $okR ? (int) $rq : 0; };

$p4cand = function ($req, $stage = 'RECEIVED', $alloc = null) use ($pdo) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,allocation_id,created_at)
                   VALUES (?,'P4','C',?,?,?,?)")
        ->execute(['P4C-' . bin2hex(random_bytes(3)), $stage, $req, $alloc, date('c')]);
    return (int) $pdo->lastInsertId(); };

// ---- A · THE SHAPE OF ONE DEMAND -------------------------------------------
t_section('A · twenty people from several sources is still ONE requirement');
$rqA = $p4req(20, ['job_title' => 'P4 Twenty']);
t_ok($rqA > 0, 'A1 · an approved twenty-person requirement exists');
$sA = rful_summary($rqA);
t_eq($sA['authorised'], 20, 'A2 · AUTHORISED is twenty');
t_eq($sA['allocated'], 0,  'A3 · nothing is allocated yet');
t_eq($sA['unallocated'], 20, 'A4 · all twenty are still to be sourced');

$a1 = rful_allocate($rqA, 'OWN_PAYROLL', 10);
t_eq($a1['code'], 'OK', 'A5 · ten from our own payroll');
$a2 = rful_allocate($rqA, 'MANPOWER_AGENCY', 5, ['label' => 'Sterling']);
t_eq($a2['code'], 'OK', 'A6 · five from a manpower agency');
$a3 = rful_allocate($rqA, 'FREELANCER', 5);
t_eq($a3['code'], 'OK', 'A7 · five freelancers');
$sA = rful_summary($rqA);
t_eq($sA['allocated'], 20, 'A8 · twenty allocated across three sources');
t_eq($sA['unallocated'], 0, 'A9 · nothing left to source');
t_eq(count($sA['sources']), 3, 'A10 · three sources, ONE requirement');
t_eq((int) ops_val("SELECT COUNT(*) FROM requisitions WHERE id=?", [$rqA]), 1,
     'A11 · three sources did NOT become three requirements');
t_eq((int) reqf_counts($rqA)['requested'], 20, 'A12 · the approved quantity is untouched by sourcing');

// ---- B · THE CEILING (I1, I2) ----------------------------------------------
t_section('B · allocated can never exceed authorised');
$b1 = rful_allocate($rqA, 'SUPPLIER', 1);
t_eq($b1['code'], 'OVER_AUTHORISED', 'B1 · a twenty-first seat is refused');
t_eq(rful_summary($rqA)['allocated'], 20, 'B2 · …and nothing was written (I7)');
$rqB = $p4req(3, ['job_title' => 'P4 Three']);
t_eq(rful_allocate($rqB, 'OWN_PAYROLL', 4)['code'], 'OVER_AUTHORISED',
     'B3 · four cannot be promised against three approved');
t_eq(rful_summary($rqB)['allocated'], 0, 'B4 · …and nothing was written');
t_eq(rful_allocate($rqB, 'OWN_PAYROLL', 3)['code'], 'OK', 'B5 · exactly three is accepted');
t_eq(rful_allocate($rqB, 'SUPPLIER', 1)['code'], 'OVER_AUTHORISED', 'B6 · one more is not');

// ---- C · REBALANCING (§13) --------------------------------------------------
t_section('C · moving seats between sources');
$rqC = $p4req(10, ['job_title' => 'P4 Rebalance']);
$c1 = rful_allocate($rqC, 'MANPOWER_AGENCY', 6)['id'];
$c2 = rful_allocate($rqC, 'OWN_PAYROLL', 4)['id'];
t_eq(rful_summary($rqC)['unallocated'], 0, 'C1 · ten allocated, nothing spare');
t_eq(rful_reallocate($c1, 8)['code'], 'OVER_AUTHORISED', 'C2 · the agency cannot grow into seats it does not have');
t_eq(rful_reallocate($c1, 4)['code'], 'OK', 'C3 · the agency is cut to four');
t_eq(rful_summary($rqC)['unallocated'], 2, 'C4 · two seats came back to the requirement');
t_eq(rful_reallocate($c2, 6)['code'], 'OK', 'C5 · own payroll takes them');
t_eq(rful_summary($rqC)['allocated'], 10, 'C6 · still exactly ten (I2)');
t_eq(rful_reallocate($c2, 6)['code'], 'NO_CHANGE', 'C7 · setting the same number changes nothing');
t_eq(rful_reallocate($c2, 0)['code'], 'BAD_QUANTITY', 'C8 · an allocation of nobody is refused');
t_eq(rful_reallocate($c2, -2)['code'], 'BAD_QUANTITY', 'C9 · a negative allocation is refused');
t_eq(rful_reallocate($c2, '3.5')['code'], 'BAD_QUANTITY', 'C10 · half a person is refused');
t_eq(rful_reallocate($c2, 'five')['code'], 'BAD_QUANTITY', 'C11 · a word is refused, not coerced to zero');
t_eq(rful_reallocate($c2, [3])['code'], 'BAD_QUANTITY', 'C12 · an array is refused, not coerced to one');
t_eq(rful_summary($rqC)['allocated'], 10, 'C13 · every refusal above wrote nothing (I7)');

// ---- D · PEOPLE ARRIVING THROUGH A SOURCE (I5) ------------------------------
t_section('D · a source is credited only for the people it delivered');
$rqD = $p4req(6, ['job_title' => 'P4 Arrivals']);
$d1 = rful_allocate($rqD, 'MANPOWER_AGENCY', 2, ['label' => 'Sterling'])['id'];
$d2 = rful_allocate($rqD, 'OWN_PAYROLL', 4)['id'];
$dc1 = $p4cand($rqD, 'ACCEPTED');
t_eq(rful_attach($dc1, $d1)['code'], 'OK', 'D1 · the first arrival is credited to the agency');
t_eq(rful_fulfilled($d1), 1, 'D2 · the agency has delivered one');
$dc2 = $p4cand($rqD, 'ACCEPTED');
t_eq(rful_attach($dc2, $d1)['code'], 'OK', 'D3 · the second arrival too');
t_eq(rful_fulfilled($d1), 2, 'D4 · the agency has delivered both of its two');
$dc3 = $p4cand($rqD, 'ACCEPTED');
t_eq(rful_attach($dc3, $d1)['code'], 'OVER_ALLOCATED', 'D5 · a third cannot be credited to a two-seat source');
t_eq(rful_fulfilled($d1), 2, 'D6 · …and the agency is still credited with exactly two (I5)');
t_eq((int) ops_val("SELECT COALESCE(allocation_id,0) FROM candidates WHERE id=?", [$dc3]), 0,
     'D7 · the refused person carries no link');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE id=? AND stage='ACCEPTED'", [$dc3]), 1,
     'D8 · …but is STILL in their seat — Phase 4 never removes anybody (§24)');
t_eq(rful_attach($dc3, $d2)['code'], 'OK', 'D9 · they can be credited to a source that has room');
$sD = rful_summary($rqD);
t_eq($sD['fulfilled'], 3, 'D10 · three people have joined the requirement');
t_eq($sD['allocated'], 6, 'D11 · six are promised');
t_ok($sD['sourced_fulfilled'] <= $sD['allocated'] && $sD['allocated'] <= $sD['authorised'],
     'D12 · SOURCED-FULFILLED ≤ ALLOCATED ≤ AUTHORISED (I1)');
t_ok($sD['fulfilled'] <= $sD['authorised'], 'D12b · and nobody joined beyond the approved headcount');
t_eq($sD['over_committed'], 0, 'D12c · nothing is promised twice');
t_eq(rful_sync_state($d1), 'FULFILLED', 'D13 · the agency allocation reports itself complete');

// ---- E · AN ALLOCATION BELONGS TO ONE REQUIREMENT (I4) ----------------------
t_section('E · one allocation, one requirement');
$rqE1 = $p4req(4, ['job_title' => 'P4 Alpha']); $rqE2 = $p4req(4, ['job_title' => 'P4 Beta']);
$e1 = rful_allocate($rqE1, 'SUPPLIER', 4)['id'];
$ec = $p4cand($rqE2, 'ACCEPTED');
t_eq(rful_attach($ec, $e1)['code'], 'NO_ALLOCATION',
     'E1 · a person on Beta cannot be credited to Alpha’s source');
t_eq(rful_fulfilled($e1), 0, 'E2 · …and Alpha’s source was not credited (I4)');
//  …and the same thing attempted by writing the row directly is undone.
$pdo->prepare("UPDATE candidates SET allocation_id=? WHERE id=?")->execute([$e1, $ec]);
t_ok(rful_enforce_candidate($ec) !== '', 'E3 · a link written straight to the table is caught');
t_eq((int) ops_val("SELECT COALESCE(allocation_id,0) FROM candidates WHERE id=?", [$ec]), 0,
     'E4 · …and removed (defence in depth, not only the door)');

// ---- F · CLOSING A SOURCE (I6) ---------------------------------------------
t_section('F · giving seats back keeps what was delivered');
$rqF = $p4req(10, ['job_title' => 'P4 Close']);
$f1 = rful_allocate($rqF, 'MANPOWER_AGENCY', 6)['id'];
$f2 = rful_allocate($rqF, 'OWN_PAYROLL', 4)['id'];
$fc = $p4cand($rqF, 'ACCEPTED'); rful_attach($fc, $f1);
t_eq(rful_fulfilled($f1), 1, 'F1 · the agency delivered one of its six');
t_eq(rful_close($f1, 'RELEASED', ['reason' => 'they cannot find the rest'])['code'], 'OK', 'F2 · the agency gives the rest back');
t_eq(rful_fulfilled($f1), 1, 'F3 · the person it DID deliver is still credited to it (I6)');
t_eq((int) rful_get($f1)['allocated_qty'], 1, 'F4 · the allocation is pinned down to what it delivered');
$sF = rful_summary($rqF);
t_eq($sF['allocated'], 5, 'F5 · the one the agency DELIVERED plus own payroll’s four are accounted for');
t_eq($sF['unallocated'], 5, 'F6 · the agency’s five undelivered seats are sourceable again');
t_ok($sF['sourced_fulfilled'] <= $sF['allocated'] && $sF['allocated'] <= $sF['authorised'],
     'F6b · SOURCED-FULFILLED ≤ ALLOCATED ≤ AUTHORISED survives a release (I1)');
t_eq(rful_close($f1, 'RELEASED')['code'], 'BAD_STATE', 'F7 · a closed allocation cannot be closed twice');
t_eq(rful_reallocate($f1, 3)['code'], 'BAD_STATE', 'F8 · …nor resized');
t_eq(rful_attach($p4cand($rqF, 'ACCEPTED'), $f1)['code'], 'BAD_STATE', 'F9 · …nor credited with anybody new');
t_eq(rful_close($f2, 'CANCELLED', ['reason' => 'budget'])['code'], 'OK', 'F10 · own payroll is cancelled outright');
t_eq((int) rful_get($f2)['allocated_qty'], 0, 'F11 · it delivered nobody, so it keeps nothing');
$sF2 = rful_summary($rqF);
t_eq($sF2['fulfilled'], 2, 'F12 · two people have arrived against this requirement…');
t_eq($sF2['unallocated'], 8, 'F12a · …so eight of the ten are sourceable again, not nine');
t_eq($sF2['allocated'], 1, 'F12b · only the one the agency delivered still holds a promised seat');
t_ok($sF2['sourced_fulfilled'] <= $sF2['allocated'] && $sF2['allocated'] <= $sF2['authorised'],
     'F12c · SOURCED-FULFILLED ≤ ALLOCATED ≤ AUTHORISED survives a cancellation (I1)');
t_eq($sF2['committed'], $sF2['allocated'] + $sF2['direct_fulfilled'],
     'F12d · the person who arrived without a source still spent a seat');
t_eq(rful_close($f2, 'FULFILLED')['code'], 'BAD_STATE', 'F13 · "close" can only mean released or cancelled');
t_eq(rful_close($f2, 'PLANNED')['code'], 'BAD_STATE', 'F14 · a closed allocation cannot be re-opened by closing it');

// ---- G · CUTTING BELOW WHAT ARRIVED -----------------------------------------
t_section('G · an allocation can never be cut below what it delivered');
$rqG = $p4req(8, ['job_title' => 'P4 Cut']);
$g1 = rful_allocate($rqG, 'SUBCON_AGENCY', 5)['id'];
foreach ([1, 2, 3] as $i) rful_attach($p4cand($rqG, 'ACCEPTED'), $g1);
t_eq(rful_fulfilled($g1), 3, 'G1 · three have arrived through the sub-contractor');
t_eq(rful_reallocate($g1, 2)['code'], 'BELOW_FULFILLED', 'G2 · it cannot be cut to two');
t_eq((int) rful_get($g1)['allocated_qty'], 5, 'G3 · …and was not (I7)');
t_eq(rful_reallocate($g1, 3)['code'], 'OK', 'G4 · it can be cut to exactly what arrived');
t_eq(rful_summary($rqG)['unallocated'], 5, 'G5 · the two spare seats went back');
$sG = rful_summary($rqG);
t_ok($sG['remaining'] >= 0 && $sG['unallocated'] >= 0, 'G6 · nothing went negative (I3)');

// ---- H · THE LEDGER (§28, §29) ----------------------------------------------
t_section('H · every change is on the record, and refusals are not');
$rqH = $p4req(5, ['job_title' => 'P4 Ledger']);
$h1 = rful_allocate($rqH, 'SUPPLIER', 3)['id'];
$evN = fn($a) => count(rful_events($a));
$hBefore = $evN($h1);
t_ok($hBefore >= 1, 'H1 · the allocation itself is on the record');
rful_reallocate($h1, 2);
t_eq($evN($h1), $hBefore + 1, 'H2 · the resize is on the record');
rful_reallocate($h1, 99);                                  // refused: 99 > the 5 authorised
t_eq($evN($h1), $hBefore + 1, 'H3 · a REFUSED resize wrote nothing (I7)');
rful_close($h1, 'CANCELLED', ['reason' => 'gone']);
t_eq($evN($h1), $hBefore + 2, 'H4 · the cancellation is on the record');
$hEv = rful_events($h1);
t_eq(strtoupper((string) end($hEv)['event']), 'CANCELLED', 'H5 · …and says what happened');
t_ok(trim((string) end($hEv)['reason']) !== '', 'H6 · …and why');

// ---- J/K · A SEAT SOMEBODY ALREADY FILLED CANNOT BE PROMISED AWAY -------------
//  The failure this phase exists to prevent, in its quietest form: ten approved,
//  three already walked in off the street, and a coordinator promises all ten to
//  an agency. The agency then believes it owes ten seats that only seven exist
//  for — one demand, promised twice.
t_section('J · people already found directly have spent their seats');
$rqJ = $p4req(10, ['job_title' => 'P4 Direct']);
foreach ([1, 2, 3] as $i) $p4cand($rqJ, 'ACCEPTED');          // found directly, no source
$sJ = rful_summary($rqJ);
t_eq($sJ['direct_fulfilled'], 3, 'J1 · three arrived without a source');
t_eq($sJ['unallocated'], 7, 'J2 · only seven seats are left to source, not ten');
t_eq(rful_allocate($rqJ, 'MANPOWER_AGENCY', 10)['code'], 'OVER_AUTHORISED',
     'J3 · all ten cannot be promised to an agency');
t_eq(rful_summary($rqJ)['allocated'], 0, 'J4 · …and nothing was written (I7)');
$jA = rful_allocate($rqJ, 'MANPOWER_AGENCY', 7);
t_eq($jA['code'], 'OK', 'J5 · seven can');
t_eq(rful_allocate($rqJ, 'SUPPLIER', 1)['code'], 'OVER_AUTHORISED', 'J6 · an eighth cannot');
t_eq(rful_summary($rqJ)['over_committed'], 0, 'J7 · nothing is promised twice');

//  …and the other order: the promise is made first, then somebody walks in.
//  Phase 4 must NOT throw them out — M6 owns joining and the seat is approved —
//  so the over-commitment is SHOWN and the coordinator trims it.
t_section('K · a direct arrival after the promise is shown, never silently undone');
$rqK = $p4req(4, ['job_title' => 'P4 Late']);
$k1 = rful_allocate($rqK, 'SUBCON_AGENCY', 4)['id'];
t_eq(rful_summary($rqK)['over_committed'], 0, 'J8 · four promised against four approved');
$kc = $p4cand($rqK, 'ACCEPTED');                               // walks in, no source
$sK = rful_summary($rqK);
t_eq($sK['over_committed'], 1, 'J9 · the requirement is now over-committed by one — and says so');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE id=? AND stage='ACCEPTED'", [$kc]), 1,
     'J10 · the person who walked in was NOT thrown out (§24)');
t_eq((int) rful_get($k1)['allocated_qty'], 4, 'J11 · …and the agency’s promise was not silently cut either');
t_eq(rful_reallocate($k1, 5)['code'], 'OVER_AUTHORISED', 'J12 · it cannot be made worse');
t_eq(rful_reallocate($k1, 3)['code'], 'OK', 'J13 · but the coordinator CAN trim it back');
t_eq(rful_summary($rqK)['over_committed'], 0, 'J14 · …and the requirement is square again');

$_SESSION = $p4o; current_user(true); ua(true);
