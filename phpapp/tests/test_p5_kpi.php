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
//  B4 — the aggregate must agree with PHASE 4 as well as with M3, requirement by
//  requirement. Section A checks one fixture; this checks every requirement the
//  suite has built, so a rule that is right for the fixture and wrong in general
//  cannot pass. The first cut of this engine was exactly that: right until an
//  allocation was closed.
$b4bad = [];
foreach (ops_all("SELECT id FROM requisitions ORDER BY id") ?: [] as $b4r) {
    $b4id = (int) $b4r['id'];
    $b4d = rkpi_demand(['no_scope' => true, 'where' => 'r.id=?', 'args' => [$b4id]]);
    if ((int) $b4d['requisitions'] !== 1) continue;          // not live demand; Phase 4 is not asked
    $b4s = rful_summary($b4id);
    foreach (['authorised', 'allocated', 'unallocated', 'over_committed'] as $b4k)
        if ((int) $b4d[$b4k] !== (int) $b4s[$b4k])
            $b4bad[] = "#$b4id $b4k engine=" . (int) $b4d[$b4k] . " phase4=" . (int) $b4s[$b4k];
}
t_eq(count($b4bad), 0, 'B4 · every live requirement agrees with Phase 4 on allocation'
     . ($b4bad ? ' — ' . implode(' · ', array_slice($b4bad, 0, 4)) : ''));
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

// ---- K · THE DEFENCES THE HAPPY PATH NEVER REACHES -------------------------
//
//  The first mutation battery caught 23 of 36. Every one of the twelve survivors
//  was a gap in THESE probes, not a protection in the product — the ordinary
//  paths simply cannot produce the states these controls exist to survive, and a
//  battery that only walks the ordinary paths proves only that they work.
//  Imported data, a half-finished migration and a corrupted row all can.
t_section('K · the states the ordinary paths cannot reach');

//  K1 — more people joined than the requirement has seats. Legacy imports do
//  this; so does a requirement edited DOWN after people had already joined.
$rqK1 = $p5req(2, ['job_title' => 'P5 Overfilled']);
for ($i = 0; $i < 5; $i++) $p5cand($rqK1, 'ACCEPTED', null, date('c'));
reqf_sync($rqK1);
$dK1 = rkpi_demand($only($rqK1));
t_eq((int) $dK1['filled'], 2, 'K1 · five people against two seats reports TWO filled — a seat cannot be filled twice');
t_eq((int) $dK1['remaining'], 0, 'K1b · …and nothing remains, rather than going negative');

//  K2 — more vacancies cancelled than were ever requested.
$pdo->prepare("INSERT INTO requisitions (req_code,office_id,designation,status,quantity,cancelled_qty,created_at)
               VALUES (?,?,?,?,?,?,?)")->execute(['P5K-OVERCANC', 9841, 'ENGINEER', 'OPEN', 3, 9, date('c')]);
$rqK2 = (int) $pdo->lastInsertId();
$dK2 = rkpi_demand($only($rqK2));
t_eq((int) $dK2['cancelled'], 3, 'K2 · nine cancelled against three requested reports THREE — the rest is not a number anybody can act on');
t_eq((int) $dK2['authorised'], 0, 'K2b · …and the approved headcount is zero, never negative');

//  K3 — A CLOSED PROMISE KEEPS WHAT IT DELIVERED, AND RETURNS ONLY THE REST.
//
//  The first cut of this engine counted only LIVE allocations, and the first
//  mutation battery could not catch the mutant that deleted that filter —
//  because deleting it was the correct behaviour. Closing an allocation pins it
//  down to exactly what it DELIVERED, and those people have arrived: their seats
//  are spent, not returned. Phase 4 had already found and fixed this, and this
//  aggregate quietly reintroduced it.
//
//  The probe that missed it released a promise that had delivered NOBODY, whose
//  pinned quantity is zero either way — so it compared two numbers that agree
//  under both the right rule and the wrong one. This one delivers somebody
//  first, which is the only version of the case that can tell them apart.
$rqK3 = $p5req(6, ['job_title' => 'P5 Released']);
$aK3a = rful_allocate($rqK3, 'MANPOWER_AGENCY', 3, ['source_label' => 'Kept']);
$aK3b = rful_allocate($rqK3, 'SUBCON_AGENCY', 2, ['source_label' => 'Delivered one, then released']);
t_ok(!empty($aK3a['ok']) && !empty($aK3b['ok']), 'K3 · two promises, five seats, against a six-person requirement');
t_eq((int) rkpi_demand($only($rqK3))['allocated'], 5, 'K3b · five seats are promised while both stand');
//  One person actually arrives through the second source.
$cK3 = $p5cand($rqK3, 'ACCEPTED', (int) $aK3b['id'], date('c'));
reqf_sync($rqK3);
$relK3 = rful_close((int) $aK3b['id'], 'RELEASED', []);
t_ok(!empty($relK3['ok']), 'K3c · that promise is released through the production path — ' . (string) ($relK3['reason'] ?? ''));
$dK3 = rkpi_demand($only($rqK3));
t_eq((int) $dK3['allocated'], 4,
     'K3d · the released promise KEEPS the one it delivered and returns the other — four, not five and not three');
//  And the decisive one: whatever the answer is, it must be Phase 4's answer.
$sK3 = rful_summary($rqK3);
t_eq((int) $dK3['allocated'], (int) $sK3['allocated'], 'K3e · the aggregate gives Phase 4\'s figure, not one of its own');
t_eq((int) $dK3['unallocated'], (int) $sK3['unallocated'], 'K3f · …and the same unallocated');
t_eq((int) $dK3['over_committed'], (int) $sK3['over_committed'], 'K3g · …and the same over-commitment');

//  K4 — THE DASHBOARD ITSELF. Every probe above asks the engine; this asks the
//  screen, because the whole point of this phase is that the two cannot differ.
$ccF = ['fy' => '', 'range' => null, 'month' => '', 'dept' => '', 'source' => '', 'manager' => ''];
$dCC = rcc_data($ccF);
$dEng = rkpi_demand([]);
t_eq((int) ($dCC['kpi']['open_positions'] ?? -1), (int) $dEng['remaining'],
     'K4 · the dashboard\'s open positions IS the engine\'s figure (K1) — not a second opinion');
t_eq((int) ($dCC['kpi']['ordered'] ?? -1), (int) $dEng['authorised'], 'K4b · and "ordered" is the APPROVED headcount');
t_eq((int) ($dCC['kpi']['filled'] ?? -1), (int) $dEng['filled'], 'K4c · and filled agrees');
t_eq((int) ($dCC['kpi']['allocated'] ?? -1), (int) $dEng['allocated'], 'K4d · and what is promised to sources agrees');

//  K5 — a recruiter row on that dashboard: carrying vs delivered.
$uCarl = $p5mk('p5k_carl', 'MANAGER', 0, 9841, '9841');
$rqK5 = $p5req(8, ['job_title' => 'P5 Carl']);
reqf_cancel($rqK5, 3, 'trimmed');
rasg_assign('REQ_RECRUITER', $rqK5, $uCarl, ['reason' => 'Carl carries this']);
$cK5 = $p5cand($rqK5, 'SHORTLISTED');
rasg_assign('CAND_RECRUITER', $cK5, $uCarl, ['reason' => 'Carl is chasing']);
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $cK5]);
//  …and a second joining on the same requirement that Carl never held.
$cK5b = $p5cand($rqK5, 'ACCEPTED', null, date('c'));
reqf_sync($rqK5);
//  Asked through the dashboard's OWN recruiter filter rather than by scanning the
//  whole table. The table is deliberately truncated to the eight busiest people,
//  so a probe that simply looks for a name in it passes or fails depending on how
//  many other fixtures the suite happened to build first — which is exactly what
//  it did: green on its own, red in the full run. That is a probe measuring the
//  suite, not the product.
$ccCarl = $ccF; $ccCarl['manager'] = (string) $uCarl;
$dCC2 = rcc_data($ccCarl);
$rowK5 = null; foreach ($dCC2['recruiters'] as $r) if ((int) $r['uid'] === $uCarl) $rowK5 = $r;
t_ok($rowK5 !== null, 'K5 · Carl appears on the recruiter table when it is filtered to him');
if ($rowK5) {
    t_eq((int) $rowK5['posted'], 5, 'K5b · he is CARRYING five — eight approved less the three given up, not eight');
    t_eq((int) $rowK5['recruited'], 1, 'K5c · and is credited with the ONE joining the ledger puts on him, not both');
}

//  K6 — the pipeline records the stage KEY, through its own production path.
recruitpipe_migrate(); recruitpipe_seed();
$cK6 = $p5cand($p5req(3, ['job_title' => 'P5 Pipe']), 'RECEIVED');
$candK6 = ops_one("SELECT * FROM candidates WHERE id=?", [$cK6]);
[$pipeK6, $effK6, $idxK6] = recruitpipe_cand_state($candK6);
t_ok((bool) $pipeK6 && count((array) $effK6) > 1, 'K6 · a configured workflow applies to this candidate');
if ($pipeK6 && count((array) $effK6) > 1) {
    $tgtK6 = $effK6[1];
    t_ok(recruitpipe_cand_goto($candK6, (int) $tgtK6['id'], 'k6', 'p5'), 'K6b · it is advanced through the production path');
    $evK6 = ops_one("SELECT * FROM candidate_events WHERE candidate_id=? ORDER BY id DESC", [$cK6]);
    t_eq((string) ($evK6['to_code'] ?? ''), (string) $tgtK6['stage_key'],
         'K6c · the ledger carries the stage KEY — so renaming the stage cannot rewrite what was measured');
    t_eq((string) ($evK6['track'] ?? ''), 'PIPELINE', 'K6d · on the pipeline ladder');
}

//  K7 — A RECORD THAT PREDATES THE LEDGER, REASSIGNED AFTERWARDS.
//
//  This is the case the ledger exists for, arriving by the back door: there is
//  no assignment history at the moment of the hire, and the only ledger entry
//  is a handover that happened LATER. Reading "who holds it now" would move the
//  hire to the new owner. The first recorded change names who held it before.
$rqK7 = $p5req(2, ['job_title' => 'P5 Predates']);
$cK7  = $p5cand($rqK7, 'OFFERED');
$pdo->prepare("UPDATE candidates SET recruiter_id=? WHERE id=?")->execute([$uAnn, $cK7]);   // set, as it was before M5
$joinK7 = date('c');
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([$joinK7, $cK7]);
reqf_sync($rqK7);
t_eq(rkpi_owner_at('CAND_RECRUITER', $cK7, $joinK7), $uAnn, 'K7 · with no history at all, the current holder is the only evidence there is');
usleep(1100000);
rasg_assign('CAND_RECRUITER', $cK7, $uBob, ['reason' => 'handed over long after the hire']);
t_eq(rkpi_owner_at('CAND_RECRUITER', $cK7, $joinK7), $uAnn,
     'K7b · after the handover the hire STILL belongs to Ann — the first recorded change names who held it before (K4)');
t_ok(rkpi_owner_at('CAND_RECRUITER', $cK7, $joinK7) !== $uBob, 'K7c · and never to Bob, who was given it afterwards');
[$whoK7, $basisK7] = rkpi_owner_basis('CAND_RECRUITER', $cK7, $joinK7);
t_eq($basisK7, 'ledger_before_first_change', 'K7d · and the answer states the evidence it rests on');

//  K8 — a settled outcome with no date, on a record that changed hands. There is
//  genuinely no way to know which side of the handover it falls on.
$rqK8 = $p5req(2, ['job_title' => 'P5 Ambiguous']);
$cK8  = $p5cand($rqK8, 'ACCEPTED');          // no decided_at at all
rasg_assign('CAND_RECRUITER', $cK8, $uAnn, ['reason' => 'first']);
rasg_assign('CAND_RECRUITER', $cK8, $uBob, ['reason' => 'second']);
[$whoK8, $basisK8] = rkpi_owner_basis('CAND_RECRUITER', $cK8, null);
t_eq($whoK8, null, 'K8 · no date and two owners: nobody is credited — the answer is not guessed');
t_eq($basisK8, 'ambiguous_no_date', 'K8b · …and it says why');
$unK8 = rkpi_unattributed(['where' => 'c.id=?', 'args' => [$cK8], 'no_scope' => true]);
t_ok((int) $unK8['ambiguous_no_date'] >= 1, 'K8c · and it is REPORTED, so the business can go and fix the record (K10)');

//  K9 — the credit query obeys branch scope.
$p5act($uOnlyB);
$credScoped = rkpi_recruiter_credit($uAnn);
$p5act($uBoss);
$credAll = rkpi_recruiter_credit($uAnn, ['no_scope' => true]);
t_ok((int) $credAll['hires'] >= 1, 'K9 · Ann has hires on record in Branch A');
t_eq((int) $credScoped['hires'], 0, 'K9b · a Branch B user is credited none of them — scope reaches the credit query too (K8)');

//  K10 — THE M4 BOUNDARY. Only the hiring-request layer may touch its table;
//  the KPI engine asks through hreq_get(). Stated here as well as in M4's own
//  suite, because this engine is the one that would be tempted.
$kpiSrc = file_get_contents(__DIR__ . '/../lib/recruit_kpi.php');
t_ok(!preg_match('/(FROM|INTO|UPDATE|JOIN)\s+hiring_requests\b/i', $kpiSrc),
     'K10 · the KPI engine never reads the hiring-request table directly');
t_ok(strpos($kpiSrc, 'hreq_get(') !== false, 'K10b · …it asks the layer that owns it');

//  K11 — the settled set is never served from a cache that outlives a write.
$rowsBefore = count(rkpi_settled_rows(['no_scope' => true]));
$rqK11 = $p5req(1, ['job_title' => 'P5 Cache']);
$p5cand($rqK11, 'ACCEPTED', null, date('c'));
$rowsAfter = count(rkpi_settled_rows(['no_scope' => true]));
t_ok($rowsAfter > $rowsBefore, 'K11 · a read after a write sees the write — no stale cache between them');

// ---- L · WHAT AN ADVERSARIAL PASS FOUND ------------------------------------
//
//  These come from attacking the finished work rather than confirming it. Every
//  one is a state no screen can create and every real database eventually
//  contains: a corrupted row, a half-finished import, two servers whose clocks
//  disagree. A derived figure must be bounded by its own definition, not by
//  trust in the rows underneath it.
t_section('L · corrupt and impossible data cannot make a figure lie');

$mkRaw = function ($q, $canc = 0) use ($pdo) {
    $pdo->prepare("INSERT INTO requisitions (req_code,office_id,designation,status,quantity,cancelled_qty,created_at)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute(['P5L-' . bin2hex(random_bytes(3)), 9841, 'ENGINEER', 'OPEN', $q, $canc, date('c')]);
    return (int) $pdo->lastInsertId(); };

//  L1 — a negative quantity, and a negative cancellation.
$dL1 = rkpi_demand($only($mkRaw(-5)));
t_ok((int) $dL1['authorised'] >= 0 && (int) $dL1['remaining'] >= 0, 'L1 · a negative quantity cannot produce a negative figure');
$dL2 = rkpi_demand($only($mkRaw(5, -4)));
t_eq((int) $dL2['cancelled'], 0, 'L2 · a negative cancellation cancels nothing…');
t_eq((int) $dL2['authorised'], 5, 'L2b · …and cannot inflate the approved headcount');

//  L3 — a corrupt negative promise must not invent capacity. Phase 4 fixed
//  exactly this on its own path; the aggregate must hold the same line.
$rqL3 = $mkRaw(5);
$pdo->prepare("INSERT INTO requisition_allocations (requisition_id,source,source_label,allocated_qty,status,created_at)
               VALUES (?,?,?,?,?,?)")->execute([$rqL3, 'MANPOWER_AGENCY', 'Corrupt', -9, 'ACTIVE', date('c')]);
$dL3 = rkpi_demand($only($rqL3));
t_ok((int) $dL3['allocated'] >= 0 && (int) $dL3['unallocated'] <= 5, 'L3 · a negative promise cannot invent capacity');
t_eq((int) $dL3['allocated'], (int) rful_summary($rqL3)['allocated'], 'L3b · …and Phase 4 is still agreed with');

//  L4 — an allocation belonging to ANOTHER requirement lends no seats here.
$rqL4a = $mkRaw(4); $rqL4b = $mkRaw(4);
$pdo->prepare("INSERT INTO requisition_allocations (requisition_id,source,source_label,allocated_qty,status,created_at)
               VALUES (?,?,?,?,?,?)")->execute([$rqL4b, 'MANPOWER_AGENCY', 'Elsewhere', 2, 'ACTIVE', date('c')]);
$alL4 = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,allocation_id,created_at,decided_at)
               VALUES (?,'P5','L','ACCEPTED',?,?,?,?)")
    ->execute(['P5LC-' . bin2hex(random_bytes(3)), $rqL4a, $alL4, date('c'), date('c')]);
t_eq((int) rkpi_demand($only($rqL4a))['allocated'], 0, 'L4 · a foreign allocation lends no seats to this requirement');

//  L5 — A LEDGER WHOSE TIMESTAMPS RUN BACKWARDS.
//
//  Clock skew between two servers, an import, or a manual fix all produce this.
//  A negative duration is not a fast stage, it is a broken record: left in, it
//  drags an average down and can make a stage look instantaneous. It is excluded
//  and COUNTED — not clamped to zero, which would turn a data fault into a
//  plausible measurement.
$cL5 = $p5cand($mkRaw(2), 'RECEIVED');
$insL5 = "INSERT INTO candidate_events (candidate_id,from_stage,to_stage,created_at,from_code,to_code,track,event_kind)
          VALUES (?,?,?,?,?,?,?,?)";
$pdo->prepare($insL5)->execute([$cL5, '', 'One', '2026-06-10T00:00:00+00:00', '', 'S1', 'PIPELINE', 'MOVE']);
$pdo->prepare($insL5)->execute([$cL5, 'One', 'Two', '2026-06-01T00:00:00+00:00', 'S1', 'S2', 'PIPELINE', 'MOVE']);
$pdo->prepare($insL5)->execute([$cL5, 'Two', 'Three', '2026-06-20T00:00:00+00:00', 'S2', 'S3', 'PIPELINE', 'MOVE']);
$durL5 = rkpi_stage_durations($cL5, 'PIPELINE', 'calendar');
$negL5 = 0; foreach ($durL5['steps'] as $stL5) if ((int) $stL5['days'] < 0) $negL5++;
t_eq($negL5, 0, 'L5 · a ledger running backwards yields NO negative step duration');
t_ok((int) $durL5['out_of_order'] >= 1, 'L5b · …and the broken step is counted, so somebody can go and fix the record');
t_eq(count($durL5['steps']), 1, 'L5c · the sound step is still measured — one bad row does not discard the rest');

//  L6 — ageing at the edges.
t_ok(rkpi_age('2026-12-28', '2027-01-04', 'business') !== null, 'L6 · working days cross a year boundary');
t_eq(rkpi_age('1990-01-01', '2026-01-01', 'business'), null, 'L6b · an absurd span is refused rather than counted day by day');
t_ok(rkpi_age('2026-03-09', '2026-03-02', 'business') < 0, 'L6c · a backwards span reads negative rather than silently zero');

//  L7 — dangling and missing references.
$rqL7 = $mkRaw(3);
$pdo->prepare("UPDATE requisitions SET hiring_request_id=999999 WHERE id=?")->execute([$rqL7]);
t_eq(rkpi_target($rqL7)['state'], 'NO_TARGET', 'L7 · a dangling hiring-request link yields NO TARGET — not a crash, not a date');
t_eq(rkpi_target(999999)['state'], 'NO_TARGET', 'L7b · an unknown requirement has no target either');
t_eq((int) rkpi_demand(['no_scope' => true, 'where' => 'r.id=?', 'args' => [999999]])['requisitions'], 0, 'L7c · …and counts as nothing');

//  L8 — a metric that does not exist, and one that could not be entitled.
t_eq(tapi_metric_value('hiring.does.not.exist', []), null, 'L8 · an unknown metric is NO DATA — not an error and not a zero');
$leakL8 = 0;
foreach (tapi_metrics() as $kL8 => $vL8)
    if (strpos($kL8, 'hiring.') === 0 && (($vL8['source'] ?? '') === '' || tapi_metric_module($kL8) === null)) $leakL8++;
t_eq($leakL8, 0, 'L8b · no recruitment metric can be published without a lineage to be entitled by (K7)');

//  L9 — a dead heat in the assignment ledger still yields ONE answer.
$cL9 = $p5cand($mkRaw(2), 'ACCEPTED', null, '2026-05-05T10:00:00+00:00');
$liL9 = "INSERT INTO recruiter_assignments (subject,entity_id,from_user_id,to_user_id,actor,created_at) VALUES (?,?,?,?,?,?)";
$pdo->prepare($liL9)->execute(['CAND_RECRUITER', $cL9, null, 77771, 'x', '2026-05-05T10:00:00+00:00']);
$pdo->prepare($liL9)->execute(['CAND_RECRUITER', $cL9, 77771, 77772, 'x', '2026-05-05T10:00:00+00:00']);
$whoL9 = rkpi_owner_at('CAND_RECRUITER', $cL9, '2026-05-05T10:00:00+00:00');
t_ok($whoL9 === 77771 || $whoL9 === 77772, 'L9 · two ledger entries at the same instant still give one answer, not a crash');

// ---- M · THE SCREEN A PERSON ACTUALLY RECEIVES -----------------------------
//
//  Every probe above asks a function. This one renders the Command Centre and
//  reads the HTML, because a figure that is right in the engine and absent from
//  the page has not been delivered — and because the repository's UI rule is
//  that a screen must be understandable without training, which is a claim about
//  the words on it, not about the data behind it.
t_section('M · the corrected figures reach the page, in business words');

$rqM = $p5req(12, ['job_title' => 'P5 Screen']);
reqf_cancel($rqM, 5, 'scope reduced');
for ($i = 0; $i < 2; $i++) $p5cand($rqM, 'ACCEPTED', null, date('c'));
reqf_sync($rqM);
rful_allocate($rqM, 'MANPOWER_AGENCY', 3, ['source_label' => 'Screen agency']);

$dM = rcc_data(['fy' => '', 'range' => null, 'month' => '', 'dept' => '', 'source' => '', 'manager' => '']);
ob_start();
try { $d = $dM; include dirname(__DIR__) . '/views/ops/recruitment_cc.php'; }
catch (Throwable $e) { /* the page is read-only; a partial render is enough */ }
$htmlM = (string) ob_get_clean();

t_ok(strlen($htmlM) > 2000, 'M0 · the Command Centre renders');
t_ok(strpos($htmlM, 'Approved headcount') !== false, 'M1 · the page names the APPROVED headcount…');
t_ok(strpos($htmlM, 'Promised to a source') !== false, 'M2 · …what has been promised to a source…');
t_ok(strpos($htmlM, 'Nobody looking yet') !== false, 'M3 · …and what nobody has been asked to find yet');
//  Plain words, not the engine's vocabulary. "Unallocated" and "over-committed"
//  are accurate and useless to a coordinator reading a screen in a hurry.
t_ok(stripos($htmlM, 'unallocated') === false && stripos($htmlM, 'over-committed') === false,
     'M4 · and says so without using the engine\'s vocabulary on the page');
//  The sentence that explains why the approved number is lower than the ask.
t_ok(strpos($htmlM, 'given up') !== false && strpos($htmlM, 'originally requested') !== false,
     'M5 · when vacancies were given up, the page EXPLAINS why the two numbers differ');
t_ok(strpos($htmlM, 'not the original ask') !== false,
     'M6 · …and states which of the two every other figure counts');
//  The recruiter table distinguishes the two questions in the column headings.
t_ok(strpos($htmlM, '>Carrying<') !== false && strpos($htmlM, '>Recruited<') !== false,
     'M7 · the recruiter table separates what someone is carrying from what they delivered');
t_ok(strpos($htmlM, 'responsible for today') !== false && strpos($htmlM, 'what they delivered') !== false,
     'M8 · …and says which is which, so nobody has to be told');
t_ok(strpos($htmlM, "last month's results") !== false,
     'M9 · …and that handing work over does not move the credit (K4)');

$_SESSION = $p5sess; current_user(true); ua(true);
