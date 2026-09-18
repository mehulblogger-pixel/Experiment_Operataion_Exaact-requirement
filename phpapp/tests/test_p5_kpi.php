<?php
// ============================================================================
//  PHASE 5 — RECRUITMENT KPI, SLA & PERFORMANCE: THE BEHAVIOURAL BATTERY
//
//  Every probe calls the PRODUCTION function a screen calls, and then reads the
//  DATABASE. Nothing here trusts what a function says about itself.
//
//  The invariants under test (docs/phase5/P5-BUSINESS-INVARIANTS.md):
//    K1  every screen reads ONE calculation — the dashboard and the records
//        can never disagree
//    K2  a vacancy the business gave up is not open demand
//    K3  a figure that cannot be computed truthfully is NO DATA, never zero
//    K4  historical credit survives reassignment
//    K5  calendar, business and SLA ageing are never mixed
//    K6  a stage duration is never measured across a revert or a workflow switch
//    K7  a metric with no entitlement is withheld, not zeroed
//    K8  a branch sees only its own figures
// ============================================================================

t_section('Phase 5 — KPI engine: the invariants');

$pdo = db();
hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate(); rful_migrate(); rkpi_migrate();
if (function_exists('recruit_offer_migrate')) recruit_offer_migrate();
$p5sess = $_SESSION;

foreach ([[9841, 'P5 Branch A'], [9842, 'P5 Branch B']] as $o)
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); } catch (Throwable $e) {}

$p5mk = function ($un, $role, $super, $office, $scope) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,'P5',?,1,?,?,?)")->execute([$un, $role, $super ? 1 : 0, $office, $scope]);
    return (int) $pdo->lastInsertId(); };
$p5act = function ($u) { $_SESSION['uid'] = $u; current_user(true); ua(true); };

$uBoss = $p5mk('p5k_boss', 'MANAGER', 1, 9841, '');
$uAnn  = $p5mk('p5k_ann',  'MANAGER', 0, 9841, '9841');
$uBob  = $p5mk('p5k_bob',  'MANAGER', 0, 9841, '9841');
$uOnlyB = $p5mk('p5k_only_b', 'MANAGER', 0, 9842, '9842');
$p5act($uBoss);

$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$p5base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uBoss, 'requested_by_name' => 'P5 Boss',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'P5 Engineer', 'designation' => 'ENGINEER', 'job_description' => 'p5',
    'quantity' => 10, 'office_id' => 9841, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x'], $x);

//  An approved, executable requirement built through the production M4 path.
$p5req = function ($qty, array $x = []) use ($p5base) {
    [$ok,, $h] = hreq_save(0, $p5base(['quantity' => $qty] + $x)); if (!$ok) return 0;
    hreq_submit($h); hreq_apply_decision($h, 'APPROVED', 'P5 Approver', 'ok');
    [$okR,, $rq] = hreq_to_requisition($h, $qty);
    return $okR ? (int) $rq : 0; };
$p5cand = function ($req, $stage = 'RECEIVED', $alloc = null, $decided = '') use ($pdo) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,allocation_id,created_at,decided_at)
                   VALUES (?,'P5','C',?,?,?,?,?)")
        ->execute(['P5K-' . bin2hex(random_bytes(3)), $stage, $req, $alloc, date('c'), $decided]);
    return (int) $pdo->lastInsertId(); };
$only = fn($rq) => ['no_scope' => true, 'where' => 'r.id=?', 'args' => [(int) $rq]];

// ---- A · THE DEFECT THE AUDIT MEASURED, IN ONE FIXTURE ----------------------
t_section('A · the screen and the records now say the same thing');

//  Ten approved, four vacancies given up, three joined, two promised to a source.
$rqA = $p5req(10, ['job_title' => 'P5 Ten']);
t_ok($rqA > 0, 'A0 · an approved ten-person requirement exists');
reqf_cancel($rqA, 4, 'project trimmed');
for ($i = 0; $i < 3; $i++) $p5cand($rqA, 'ACCEPTED', null, date('c'));
reqf_sync($rqA);
$resAl = rful_allocate($rqA, 'MANPOWER_AGENCY', 2, ['source_label' => 'P5 Agency']);
t_ok(!empty($resAl['ok']), 'A0b · two seats are promised to an agency — ' . (string) ($resAl['reason'] ?? ''));

$cA = reqf_counts($rqA);
$sA = rful_summary($rqA);
$dA = rkpi_demand($only($rqA));

t_eq(10, (int) $cA['requested'],  'A1 · M3 · requested is ten');
t_eq(4,  (int) $cA['cancelled'],  'A2 · M3 · four vacancies were given up');
t_eq(3,  (int) $cA['filled'],     'A3 · M3 · three people joined');
t_eq(3,  (int) $cA['remaining'],  'A4 · M3 · three seats remain — the authoritative answer');
t_eq( (int) $sA['authorised'], 6, 'A5 · Phase 4 agrees the approved headcount is six');

//  K1 — the KPI engine reports the SAME figures, not figures of its own.
t_eq(10, (int) $dA['requested'],  'A6 · KPI · requested agrees with M3');
t_eq(4,  (int) $dA['cancelled'],  'A7 · KPI · cancelled agrees with M3');
t_eq( (int) $dA['authorised'], 6, 'A8 · KPI · authorised agrees with M4/Phase 4');
t_eq(3,  (int) $dA['filled'],     'A9 · KPI · filled agrees with M3');
t_eq(3,  (int) $dA['remaining'],  'A10 · KPI · open positions is THREE — not seven (K2)');
t_eq(2,  (int) $dA['allocated'],  'A11 · KPI · two seats are promised to a source');
//  authorised 6 − allocated 2 − direct arrivals 3 = 1 still to be sourced
t_eq( (int) $dA['unallocated'], 1, 'A12 · KPI · one seat is neither promised nor filled');
t_eq( (int) $dA['over_committed'], 0, 'A13 · KPI · nothing is over-promised');

// ---- B · RECONCILIATION: the aggregate IS the sum of the owners (§23) -------
t_section('B · the set-based aggregate equals the per-record owners, row by row');

$rqB1 = $p5req(4, ['job_title' => 'P5 Recon 1']);
$rqB2 = $p5req(7, ['job_title' => 'P5 Recon 2']);
reqf_cancel($rqB2, 2, 'trim');
for ($i = 0; $i < 2; $i++) $p5cand($rqB1, 'ACCEPTED', null, date('c'));
for ($i = 0; $i < 6; $i++) $p5cand($rqB2, 'ACCEPTED', null, date('c'));   // deliberately OVER-filled
$p5cand($rqB1, 'INTERVIEW');
$p5cand($rqB2, 'REJECTED', null, date('c'));
reqf_sync($rqB1); reqf_sync($rqB2);

$ids = [$rqA, $rqB1, $rqB2];
$sum = ['requested' => 0, 'cancelled' => 0, 'filled' => 0, 'remaining' => 0, 'in_progress' => 0, 'lost' => 0];
foreach ($ids as $rq) {
    $c = reqf_counts($rq);
    $q = max(1, (int) ($c['requested'] ?? 1));
    $sum['requested']   += (int) $c['requested'];
    $sum['cancelled']   += (int) $c['cancelled'];
    $sum['filled']      += min((int) $c['filled'], $q);
    $sum['remaining']   += (int) $c['remaining'];
    $sum['in_progress'] += (int) $c['in_progress'];
    $sum['lost']        += (int) $c['lost'];
}
$ph = implode(',', array_fill(0, count($ids), '?'));
$agg = rkpi_demand(['no_scope' => true, 'where' => "r.id IN ($ph)", 'args' => $ids]);
foreach ($sum as $k => $v) t_eq($v, (int) $agg[$k], "B1 · $k · aggregate equals the sum of reqf_counts()");
t_eq((int) $agg['requisitions'], 3, 'B2 · three requirements were counted');
//  The one that matters most: an over-filled requirement must not pay for a
//  short one. B2 is over-filled by one; A still has three seats open.
t_ok((int) $agg['remaining'] >= 3, 'B3 · an over-filled requirement never cancels out a short one (K2)');

// ---- C · CANONICAL AGEING (§7, K5) -----------------------------------------
t_section('C · calendar, business and nonsense are three different answers');

t_eq(rkpi_age('2026-03-02', '2026-03-09', 'calendar'), 7, 'C1 · seven calendar days is seven');
//  Mon 2 Mar → Mon 9 Mar: one Sunday falls inside, so six working days.
t_eq(rkpi_age('2026-03-02', '2026-03-09', 'business'), 6, 'C2 · …and six working days — the Sunday is not ours to answer for');
t_ok(rkpi_age('2026-03-02', '2026-03-09', 'calendar') !== rkpi_age('2026-03-02', '2026-03-09', 'business'),
     'C3 · the two bases are never the same number by accident (K5)');
t_eq(rkpi_age('', '2026-03-09', 'calendar'), null, 'C4 · a missing start date is NO DATA, not zero (K3)');
t_eq(rkpi_age('not-a-date', '2026-03-09', 'calendar'), null, 'C5 · nor is a date that is not one');
t_eq(rkpi_age('2026-03-02', '2026-03-09', 'wishful'), null, 'C6 · an unknown basis fails CLOSED — no number at all');
t_eq(rkpi_age('2026-03-02', '2026-03-02', 'calendar'), 0, 'C7 · the same day really is zero days — a measurement, not an absence');
t_eq(rkpi_age('2026-03-09', '2026-03-06', 'calendar'), -3, 'C8 · a date in the past reads negative rather than being clamped away');

// ---- D · THE TARGET DATE (§8, K3) ------------------------------------------
t_section('D · a KPI measured against an invented target is worse than none');

$tA = rkpi_target($rqA);
t_eq($tA['date'], '2026-12-01', 'D1 · the requirement inherits the business "needed by" date from its request');
t_eq($tA['source'], 'hiring request — needed by', 'D2 · …and says exactly where it came from');
t_ok($tA['state'] !== 'NO_TARGET', 'D3 · so it has a target to be measured against');

//  A requirement raised on the DIRECT path carries no hiring request at all.
$pdo->prepare("INSERT INTO requisitions (req_code,office_id,designation,status,quantity,created_at)
               VALUES (?,?,?,?,?,?)")->execute(['P5K-DIRECT', 9841, 'ENGINEER', 'OPEN', 2, date('c')]);
$rqD = (int) $pdo->lastInsertId();
$tD = rkpi_target($rqD);
t_eq($tD['state'], 'NO_TARGET', 'D4 · a directly-raised requirement has NO target date…');
t_eq($tD['days_late'], null, 'D5 · …so "days late" is NO DATA — never 0, which a manager would read as on time (K3)');
t_eq($tD['date'], null, 'D6 · and no date is invented for it');

// ---- E · THE STAGE LEDGER IS COMPLETE AND IN ORDER (§7 precondition) --------
t_section('E · every stage movement is recorded, in the order it happened');

$evs = function ($c) { $r = ops_all("SELECT * FROM candidate_events WHERE candidate_id=? ORDER BY id ASC", [(int) $c]); return $r ?: []; };

//  E1–E4 · issuing an offer used to move the stage and record NOTHING.
$rqE = $p5req(5, ['job_title' => 'P5 Offer']);
$cE  = $p5cand($rqE, 'SHORTLISTED');
$before = count($evs($cE));
$offId = (int) offer_create($cE, ['ctc' => 500000, 'joining_date' => '2026-11-01']);
t_ok($offId > 0, 'E0 · an offer is created through the production path');
offer_submit($offId); offer_approve($offId);
[$okE, $msgE] = offer_issue($offId);
t_ok($okE, 'E1 · the offer is issued — ' . (string) $msgE);
t_eq((string) ops_one("SELECT stage FROM candidates WHERE id=?", [$cE])['stage'], 'OFFERED', 'E2 · the candidate moved to OFFERED');
$afterE = $evs($cE);
t_ok(count($afterE) > $before, 'E3 · …and the ledger RECORDED it (it used to record nothing)');
$lastE = end($afterE);
t_eq((string) $lastE['to_code'], 'OFFERED', 'E4 · with the stage code, not just a display name');
t_eq('LEGACY',  (string) $lastE['track'],   'E5 · and the ladder it belongs to');
t_eq(   (string) $lastE['event_kind'], 'MOVE', 'E6 · recorded as a move');

//  E7–E11 · a reverted joining used to leave the ledger claiming a hire.
$rqF = $p5req(1, ['job_title' => 'P5 OneSeat']);
$cF1 = $p5cand($rqF, 'OFFERED');
$cF2 = $p5cand($rqF, 'OFFERED');
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $cF1]);
reqf_sync($rqF);
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $cF2]);
rkpi_stage_log($cF2, 'OFFERED', 'ACCEPTED', ['from_code' => 'OFFERED', 'to_code' => 'ACCEPTED', 'track' => 'LEGACY', 'kind' => 'MOVE']);
$noteF = rexec_join_enforce_after_write($cF2, 'OFFERED', '');
t_ok($noteF !== '', 'E7 · the execution gate reverts the second joining');
t_eq((string) ops_one("SELECT stage FROM candidates WHERE id=?", [$cF2])['stage'], 'OFFERED', 'E8 · the candidate is back at OFFERED');
$afterF = $evs($cF2); $lastF = end($afterF);
t_eq((string) $lastF['to_code'], 'OFFERED', 'E9 · the ledger now ENDS at OFFERED — it no longer claims a hire');
t_eq( (string) $lastF['event_kind'], 'REVERT', 'E10 · and says plainly that the joining was undone');
t_ok(count($afterF) >= 2, 'E11 · both the move and its undoing are on the record — neither is erased');

// ---- F · HISTORICAL CREDIT SURVIVES REASSIGNMENT (§5, K4) -------------------
t_section('F · who delivered this is read from the ledger, not from who holds it now');

$rqG = $p5req(3, ['job_title' => 'P5 Credit']);
$cG  = $p5cand($rqG, 'SHORTLISTED');
rasg_assign('CAND_RECRUITER', $cG, $uAnn, ['reason' => 'Ann is chasing this one']);
//  Ann delivers the hire.
$joinedAt = date('c');
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([$joinedAt, $cG]);
reqf_sync($rqG);
$credAnn = rkpi_recruiter_credit($uAnn, ['no_scope' => true]);
$credBob = rkpi_recruiter_credit($uBob, ['no_scope' => true]);
t_eq((int) $credAnn['hires'], 1, 'F1 · Ann is credited with the hire she made');
t_eq((int) $credBob['hires'], 0, 'F2 · Bob is credited with nothing');

//  Now the record is handed to Bob — as happens every time somebody leaves.
sleep(0); usleep(1100000);   // the ledger is keyed on time; make the handover LATER than the hire
rasg_assign('CAND_RECRUITER', $cG, $uBob, ['reason' => 'Ann left the business']);
t_eq($uBob, (int) ops_one("SELECT recruiter_id FROM candidates WHERE id=?", [$cG])['recruiter_id'],
     'F3 · the record now belongs to Bob — the current field says so');
$credAnn2 = rkpi_recruiter_credit($uAnn, ['no_scope' => true]);
$credBob2 = rkpi_recruiter_credit($uBob, ['no_scope' => true]);
t_eq((int) $credAnn2['hires'], 1, 'F4 · Ann KEEPS the hire she made (K4) — history did not move');
t_eq((int) $credBob2['hires'], 0, 'F5 · and Bob is not paid for work he did not do');
t_eq($uAnn, rkpi_owner_at('CAND_RECRUITER', $cG, $joinedAt), 'F6 · the ledger can still say who held it at the moment it was decided');

// ---- G · STAGE DURATIONS NEVER CROSS A REVERT OR A SWITCH (§7, K6) ----------
t_section('G · a duration is never measured across an undoing');

$cH = $p5cand($p5req(2, ['job_title' => 'P5 Durations']), 'RECEIVED');
rkpi_stage_log($cH, '', 'Screening',  ['to_code' => 'SCREEN', 'track' => 'PIPELINE', 'kind' => 'MOVE']);
rkpi_stage_log($cH, 'Screening', 'Interview', ['from_code' => 'SCREEN', 'to_code' => 'IV', 'track' => 'PIPELINE', 'kind' => 'MOVE']);
rkpi_stage_log($cH, 'Interview', 'Offer', ['from_code' => 'IV', 'to_code' => 'OFF', 'track' => 'PIPELINE', 'kind' => 'MOVE']);
$durH = rkpi_stage_durations($cH, 'PIPELINE', 'calendar');
t_eq(count($durH['steps']), 2, 'G1 · three moves close two steps — the step in progress is not one of them');
t_eq((string) $durH['steps'][0]['code'], 'SCREEN', 'G2 · measured against the stage KEY, so renaming a stage cannot rewrite history');
t_eq((string) ($durH['current']['code'] ?? ''), 'OFF', 'G3 · the step still in progress is reported separately, never as completed');

rkpi_stage_log($cH, 'Offer', 'Interview', ['from_code' => 'OFF', 'to_code' => 'IV', 'track' => 'PIPELINE', 'kind' => 'REVERT']);
$durH2 = rkpi_stage_durations($cH, 'PIPELINE', 'calendar');
t_eq(count($durH2['steps']), 2, 'G4 · a REVERT closes no step — it is not a transition (K6)');
t_eq((int) $durH2['reverted'], 1, 'G5 · …and is counted, not hidden');

rkpi_stage_log($cH, 'Interview', 'Workflow: Other', ['from_code' => 'IV', 'to_code' => 'S1', 'track' => 'PIPELINE', 'kind' => 'SWITCH']);
rkpi_stage_log($cH, 'Start', 'Second', ['from_code' => 'S1', 'to_code' => 'S2', 'track' => 'PIPELINE', 'kind' => 'MOVE']);
$durH3 = rkpi_stage_durations($cH, 'PIPELINE', 'calendar');
t_eq(count($durH3['steps']), 2, 'G6 · a workflow SWITCH closes no step either — the two workflows are not one ladder');

//  The two ladders are never averaged together.
rkpi_stage_log($cH, 'x', 'y', ['from_code' => 'RECEIVED', 'to_code' => 'SHORTLISTED', 'track' => 'LEGACY', 'kind' => 'MOVE']);
$durLegacy = rkpi_stage_durations($cH, 'LEGACY', 'calendar');
t_eq(count($durLegacy['steps']), 0, 'G7 · asking for the legacy ladder returns the legacy ladder only (K6)');

// ---- H · ENTITLEMENT AND SCOPE (§15, §16, K7, K8) ---------------------------
t_section('H · a figure nobody is entitled to is withheld, not zeroed');

$reg = tapi_metrics();
t_ok(isset($reg['hiring.demand.open']), 'H1 · recruitment metrics are registered in the ONE analytics layer');
t_eq((string) $reg['hiring.demand.open']['source'], 'recruit/requisitions', 'H2 · …and declare their lineage');
t_eq(tapi_metric_module('hiring.demand.open'), 'hiring', 'H3 · which maps to the People & hiring module');
t_ok(($reg['hiring.demand.open']['method'] ?? '') !== '', 'H4 · every recruitment metric documents how it is calculated');
foreach (['hiring.demand.open','hiring.demand.authorised','hiring.hires','hiring.tth_avg_days','hiring.late_vs_target'] as $mk)
    t_ok(($reg[$mk]['method'] ?? '') !== '' && ($reg[$mk]['source'] ?? '') !== '', "H5 · $mk declares both a method and a source");

//  K3 again, through the registry: no measurable data must produce null.
$ctxEmpty = ['from' => '1999-01-01', 'to' => '1999-12-31'];
t_eq(tapi_metric_value('hiring.tth_avg_days', $ctxEmpty), null, 'H6 · average time to hire over a period with no joinings is NO DATA, not 0.0 (K3)');

//  K8 — a person who can only see Branch B must not be shown Branch A's demand.
$p5act($uOnlyB);
$demB = rkpi_demand([]);
$p5act($uBoss);
$demAll = rkpi_demand([]);
t_ok((int) $demAll['authorised'] > (int) $demB['authorised'],
     'H7 · a user scoped to another branch sees LESS approved headcount — scope is applied, not decorative (K8)');
t_eq((int) $demB['allocated'], 0, 'H8 · …and none of Branch A\'s source promises leak into Branch B');

// ---- J · THE REAL ROUTE, NOT THE ENGINE (M3's claim, proved behaviourally) --
//
//  test_m3_multi_vacancy.php asserts structurally that every stage move
//  recomputes the requisition standing. Phase 5 moved the write that probe
//  anchored on, so the claim is additionally proved here the only way that
//  cannot drift: by driving the REAL candidate-stage route in its own process
//  (it calls redirect(), which exits) and then reading the DATABASE.
t_section('J · a stage move through the real route recomputes the standing');

$p5root = dirname(__DIR__); $p5engine = db_driver();
$p5drive = function ($op, $id, $arg, array $post = []) use ($p5root, $p5engine, $uBoss) {
    $env = $p5engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
          . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
          . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $cmd = $env . ' php ' . escapeshellarg($p5root . '/tests/_p4_worker.php') . ' '
         . escapeshellarg($op) . ' ' . (int) $id . ' ' . escapeshellarg((string) $arg) . ' '
         . escapeshellarg(json_encode($post)) . ' 0 ' . (int) $uBoss . ' 2>&1';
    $pipes = []; $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) return null;
    $raw = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    foreach (explode("\n", trim($raw)) as $line) { $d = json_decode(trim($line), true); if (is_array($d)) return $d; }
    return null; };

$rqJ = $p5req(2, ['job_title' => 'P5 Route']);
$cJ  = $p5cand($rqJ, 'OFFERED');
t_eq((string) ops_val("SELECT status FROM requisitions WHERE id=?", [$rqJ]), 'OPEN', 'J0 · the requirement starts OPEN with two seats');
$evJ0 = (int) ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=?", [$cJ]);

$p5drive('route_cand_stage', $cJ, 'ACCEPTED');

t_eq((string) ops_val("SELECT stage FROM candidates WHERE id=?", [$cJ]), 'ACCEPTED', 'J1 · the route really moved the candidate');
//  The claim the M3 probe stands for: the standing was recomputed, not left.
t_eq('PARTIALLY_FILLED', (string) ops_val("SELECT status FROM requisitions WHERE id=?", [$rqJ]),
     'J2 · one of two seats filled — the requirement is recomputed to PARTIALLY_FILLED, not left OPEN and not forced to HIRED');
$dJ = rkpi_demand($only($rqJ));
t_eq(1, (int) $dJ['filled'],    'J3 · the KPI engine sees the joining');
t_eq((int) $dJ['remaining'], 1, 'J4 · …and one seat still to fill');
//  And the ledger recorded it — through the route, not through a test helper.
$evJ1 = (int) ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=?", [$cJ]);
t_ok($evJ1 > $evJ0, 'J5 · the stage route wrote to the ledger');
$lastJ = ops_one("SELECT * FROM candidate_events WHERE candidate_id=? ORDER BY id DESC", [$cJ]);
t_eq((string) ($lastJ['to_code'] ?? ''), 'ACCEPTED', 'J6 · with the stage code');
t_eq(    (string) ($lastJ['event_kind'] ?? ''), 'MOVE', 'J7 · recorded as a move');

$_SESSION = $p5sess; current_user(true); ua(true);
