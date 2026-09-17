<?php
// ============================================================================
//  PHASE 3 · M5 — DATA RECONCILIATION
//
//  A number on a screen is only true if the records underneath it say the same
//  thing, for the same population, within the same authorised scope. Every
//  number below is computed three ways — the dashboard, the workload counter and
//  raw SQL — and all three must agree. Where they cannot agree by definition,
//  the definition itself is asserted, so nobody can later change one of the
//  three and leave the other two behind.
// ============================================================================

t_section('Phase 3 · M5 — reconciliation: dashboard = records = scope');

$pdo = db(); rasg_migrate(); recruit_iv_migrate(); $m5rOrig = $_SESSION;
foreach ([[9561,'M5R Branch A'], [9562,'M5R Branch B']] as $o)
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); } catch (Throwable $e) {}
$mk = function ($un, $role, $super, $office, $scope) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,?,?,1,?,?,?)")->execute([$un, 'M5R', $role, $super ? 1 : 0, $office, $scope]);
    return (int) $pdo->lastInsertId();
};
$act = function ($u) { $_SESSION['uid'] = $u; current_user(true); ua(true); };
$uAll = $mk('m5r_all', 'MANAGER', 1, 9561, '');
$uA   = $mk('m5r_a',   'COORDINATOR', 0, 9561, '9561');
$uB   = $mk('m5r_b',   'COORDINATOR', 0, 9562, '9562');
$act($uAll);

//  A clean, known fixture. Every expected number below is derived from THESE
//  rows, never guessed — an assertion that guesses its fixture passes for the
//  wrong reason.
$mkReq = function ($office, $qty, $recruiter, $status = 'OPEN') use ($pdo) {
    $pdo->prepare("INSERT INTO requisitions (req_code,office_id,designation,status,quantity,recruiter_id,created_at)
                   VALUES (?,?,?,?,?,?,?)")->execute(['M5R-' . bin2hex(random_bytes(3)), $office, 'ENGINEER', $status, $qty, $recruiter, date('c')]);
    return (int) $pdo->lastInsertId();
};
$mkCand = function ($reqId, $recruiter, $stage, $received = null) use ($pdo) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,recruiter_id,cv_received_date,created_at)
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute(['M5RC-' . bin2hex(random_bytes(3)), 'M5R', 'C', $stage, $reqId, $recruiter, $received ?: date('Y-m-d'), date('c')]);
    return (int) $pdo->lastInsertId();
};

//  Recruiter A, branch A: one 10-seat requirement with 3 people joined, one
//  4-seat requirement with nobody, and one cancelled requirement that must count
//  for nothing.
$rBig  = $mkReq(9561, 10, $uA);
$rSml  = $mkReq(9561, 4,  $uA);
$rDead = $mkReq(9561, 6,  $uA, 'CANCELLED');
for ($i = 0; $i < 3; $i++) $mkCand($rBig, $uA, 'ACCEPTED');
$cOff  = $mkCand($rBig, $uA, 'OFFERED');
$cOld  = $mkCand($rSml, $uA, 'SHORTLISTED', date('Y-m-d', strtotime('-60 days')));
$cNew  = $mkCand($rSml, $uA, 'RECEIVED');
$cGone = $mkCand($rSml, $uA, 'REJECTED');
$mkCand($rDead, $uA, 'RECEIVED');
$pdo->prepare("INSERT INTO interviews (candidate_id,round,scheduled_at,created_at) VALUES (?,?,?,?)")
    ->execute([$cOld, 'L1', date('c'), date('c')]);
$pdo->prepare("INSERT INTO interviews (candidate_id,round,scheduled_at,created_at) VALUES (?,?,?,?)")
    ->execute([$cOld, 'L2', date('c'), date('c')]);
//  Recruiter B, branch B: work that must never appear in A's numbers.
$rB = $mkReq(9562, 7, $uB);
$mkCand($rB, $uB, 'ACCEPTED');
//  And work nobody owns.
$rNone = $mkReq(9561, 2, null);
$cNone = $mkCand($rNone, null, 'RECEIVED');

//  M3 put a partly-filled requirement into its own status. Let the engine do it
//  rather than typing the status in, so this reconciles the REAL lifecycle.
if (function_exists('reqf_sync')) { reqf_sync($rBig); reqf_sync($rSml); }
$bigStatus = (string) ops_val("SELECT status FROM requisitions WHERE id=?", [$rBig]);
t_eq($bigStatus, 'PARTIALLY_FILLED', 'R0 · three hires against ten seats is PARTIALLY_FILLED (M3\'s lifecycle, unchanged)');

// ---- 1 · THE NINE NUMBERS, EACH COUNTED TWICE ------------------------------
t_section('R1 · every recruiter number equals the records underneath it');
$w = rasg_workload($uA, ['no_scope' => true]);
$sql = fn($q, $a = []) => (int) ops_val($q, $a);

$expAssigned = $sql("SELECT COUNT(*) FROM requisitions WHERE recruiter_id=?", [$uA]);
t_eq($w['assigned_requisitions'], $expAssigned, 'R1.1 · total assigned requisitions = the records');
t_eq($w['assigned_requisitions'], 3, 'R1.1b · …which is the three raised for this recruiter, cancelled one included');

$expActive = $sql("SELECT COUNT(*) FROM requisitions WHERE recruiter_id=? AND status IN ('OPEN','PROPOSED','OFFERED','PARTIALLY_FILLED','HIRED')", [$uA]);
t_eq($w['active_requisitions'], $expActive, 'R1.2 · active assigned = the records');
t_eq($w['active_requisitions'], 2, 'R1.2b · …the cancelled one is not live demand');

t_eq($w['vacancies'], 14, 'R1.3 · vacancies = 10 + 4, and nothing from the cancelled requirement');
t_eq($w['filled'], 3, 'R1.4 · joins against live requirements = 3');
t_eq($w['open_seats'], 11, 'R1.5 · open seats = 14 − 3');

$expCand = $sql("SELECT COUNT(*) FROM candidates WHERE recruiter_id=?", [$uA]);
t_eq($w['candidates'], $expCand, 'R1.6 · candidates = the records');
t_eq($w['active_candidates'], $expCand - 1, 'R1.7 · the rejected candidate is not active work');
t_eq($w['offers'], 1, 'R1.8 · offers = the one candidate at OFFERED');
t_eq($w['joins'], 3, 'R1.9 · joins = the three at ACCEPTED');
t_eq($w['interviews'], 2, 'R1.10 · interviews = the two rounds arranged, not the one candidate');
t_eq($w['overdue'], 1, 'R1.11 · overdue = the candidate waiting sixty days');

// ---- 2 · THE DASHBOARD SHOWS THE SAME NUMBERS ------------------------------
t_section('R2 · the command centre agrees with the records');
//  The dashboard's own data function, with every UI filter neutral so the numbers
//  are the whole picture rather than a slice of it.
$m5f = ['fy' => '', 'range' => null, 'month' => '', 'dept' => '', 'source' => '', 'manager' => ''];
$d = rcc_data($m5f);
$row = null; foreach ($d['recruiters'] as $r) if ((int) $r['uid'] === $uA) $row = $r;
t_ok($row !== null, 'R2.1 · *** the recruiter appears on the dashboard at all ***');
t_eq((int) $row['posted'], (int) $w['vacancies'], 'R2.2 · dashboard "posted" = the seats the records hold');
t_eq((int) $row['recruited'], (int) $w['filled'], 'R2.3 · dashboard "recruited" = the joins the records hold');
//  The defect this proves is gone: before M5 the dashboard's live-demand list was
//  written out as a literal that predated PARTIALLY_FILLED, so a ten-seat
//  requirement with three hires vanished from every number on this screen.
$demandIds = array_column($d['demand'], 'id');
t_ok(in_array($rBig, $demandIds, true),
     'R2.4 · *** a partly-filled requirement is still open demand on the dashboard ***');
foreach ($d['demand'] as $dm) if ((int) $dm['id'] === $rBig)
    t_eq((int) $dm['open'], 7, 'R2.5 · …showing the seven seats still to fill, not zero');
t_ok($d['kpi']['open_positions'] >= 7, 'R2.6 · and those seats are inside the headline open-positions figure');

// ---- 3 · SCOPE: THE SAME QUESTION, ASKED AS SOMEBODY ELSE -------------------
t_section('R3 · every number is bounded by authorised scope');
$act($uB);
$wB = rasg_workload($uA);                                   // B asking about A's work
t_eq($wB['assigned_requisitions'], 0, 'R3.1 · *** a branch-B user sees none of branch A\'s assignments ***');
t_eq($wB['candidates'], 0, 'R3.2 · …nor any of its candidates');
$dB = rcc_data($m5f);
$seen = array_map(fn($r) => (int) $r['uid'], $dB['recruiters']);
t_ok(!in_array($uA, $seen, true), 'R3.3 · and branch A\'s recruiter is not on branch B\'s dashboard');
$wOwn = rasg_workload($uB);
t_eq($wOwn['vacancies'], 7, 'R3.4 · B sees its own seven seats');
t_eq($wOwn['joins'], 1, 'R3.5 · …and its own single join');
$act($uAll);
$wA = rasg_workload($uA);
t_eq($wA['vacancies'], 14, 'R3.6 · an unrestricted user sees the full picture again');

// ---- 4 · UNASSIGNED IS VISIBLE, NOT INVENTED -------------------------------
t_section('R4 · work nobody owns is counted as exactly that');
$un = rasg_unassigned(['no_scope' => true]);
t_ok($un['requisitions'] >= 1, 'R4.1 · the unowned requirement is counted as unassigned');
t_ok($un['candidates'] >= 1, 'R4.2 · …and so is the unowned candidate');
t_eq($sql("SELECT COUNT(*) FROM requisitions WHERE id=? AND recruiter_id IS NULL", [$rNone]), 1,
     'R4.3 · *** unassigned means NULL in the record — no owner was invented ***');
$totalLive = $sql("SELECT COUNT(*) FROM requisitions WHERE status IN ('OPEN','PROPOSED','OFFERED','PARTIALLY_FILLED','HIRED')");
$owned     = $sql("SELECT COUNT(*) FROM requisitions WHERE status IN ('OPEN','PROPOSED','OFFERED','PARTIALLY_FILLED','HIRED') AND COALESCE(recruiter_id,0)<>0");
t_eq($un['requisitions'], $totalLive - $owned,
     'R4.4 · assigned + unassigned = every live requirement, with nothing falling between the two');

// ---- 5 · REASSIGNMENT MOVES WORKLOAD, IT DOES NOT DUPLICATE IT -------------
t_section('R5 · reassignment moves the workload exactly once');
$before = [rasg_workload($uA)['assigned_requisitions'], rasg_workload($uAll)['assigned_requisitions']];
$mv = rasg_assign('REQ_RECRUITER', $rSml, $uAll, ['expect' => $uA, 'source' => 'test']);
t_eq($mv['code'], 'OK', 'R5.1 · the requirement is reassigned');
$after = [rasg_workload($uA)['assigned_requisitions'], rasg_workload($uAll)['assigned_requisitions']];
t_eq($after[0], $before[0] - 1, 'R5.2 · the previous owner\'s load goes down by one');
t_eq($after[1], $before[1] + 1, 'R5.3 · the new owner\'s goes up by one');
t_eq($after[0] + $after[1], $before[0] + $before[1], 'R5.4 · *** and the total is unchanged — no duplicated workload ***');
t_ok(rasg_ever_held('REQ_RECRUITER', $rSml, $uA),
     'R5.5 · the previous owner is still attributable for the period they carried it');

$_SESSION = $m5rOrig; current_user(true); ua(true);
