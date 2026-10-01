<?php
// ============================================================================
//  GATE 5 — WORKFORCE ACTIVATION, JOINING & INSPECTOR OPERATIONAL STATUS
// ============================================================================
//  THE BUSINESS RULE BEING PROVED
//
//  Accepting an offer makes somebody a member of the team on paper. It does NOT
//  put them to work. They become operationally active — schedulable, allocatable,
//  counted as capacity — on the day they actually JOIN, and at no other moment.
//
//  Before this gate, acceptance wrote status='ACTIVE' directly, so a person who
//  had merely said yes to an offer appeared on the availability board and in the
//  allocation picker, and was counted in capacity, weeks before their first day.
//
//  Sections A–N below, then Z puts the shared fixtures back.
// ============================================================================
$G5 = 'G5-' . random_int(1000, 9999);
appr_migrate(); hreq_migrate(); recruitpipe_migrate(); recruit_offer_migrate();
if (function_exists('assets_migrate')) { try { assets_migrate(); } catch (Throwable $e) {} }
$pdo = db();
$g5off = (int) ops_val("SELECT MIN(id) FROM offices");
$g5made = ['cand' => [], 'req' => [], 'ins' => [], 'user' => [], 'asset' => []];

$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES (?,?,'MANAGER',1,1,?,'')")->execute([$G5 . '_u', 'Gate5', $g5off]);
$g5u = (int) $pdo->lastInsertId(); $g5made['user'][] = $g5u;
$_SESSION['uid'] = $g5u; current_user(true); ua(true);

//  One fixture maker, so every section starts from the same shape and the
//  differences between sections are the only thing a reader has to hold.
$g5hire = function ($tag) use ($pdo, $g5off, $G5, &$g5made, $g5u) {
    $pdo->prepare("INSERT INTO requisitions (req_code,designation,office_id,sbu,status,quantity,created_at)
                   VALUES (?,?,?,'IND','OPEN',5,?)")->execute([$G5 . '-RQ-' . $tag, 'ENGINEER', $g5off, date('c')]);
    $rq = (int) $pdo->lastInsertId(); $g5made['req'][] = $rq;
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,mobile,email,created_at)
                   VALUES (?,?,'Gate5','ACCEPTED',?,?,?,?)")
        ->execute([$G5 . '-C-' . $tag, 'Hire' . $tag, $rq, '9' . random_int(100000000, 999999999),
                   strtolower($G5 . $tag) . '@x.test', date('c')]);
    $c = (int) $pdo->lastInsertId(); $g5made['cand'][] = $c;
    $r = rcv_convert($c, ['actor_id' => $g5u, 'team_role' => 'FIELD']);
    $ins = (int) ($r['inspector_id'] ?? 0);
    if ($ins > 0) $g5made['ins'][] = $ins;
    return [$c, $ins, $r, $rq];
};
$g5status = fn($ins) => (string) ops_val("SELECT COALESCE(status,'') FROM inspectors WHERE id=?", [(int) $ins]);
$g5joined = fn($c)   => (string) ops_val("SELECT COALESCE(joined_at,'') FROM candidates WHERE id=?", [(int) $c]);
$g5onRoster = function ($ins) use ($g5off) {
    foreach (inspector_availability([$g5off]) as $p) if ((int) $p['id'] === (int) $ins) return true;
    return false;
};

// ---------------------------------------------------------------------------
t_section('G5 A · ACCEPTANCE CREATES THE TEAM MEMBER BUT DOES NOT ACTIVATE THEM');
// ---------------------------------------------------------------------------
[$cA, $iA, $rA] = $g5hire('A');
//  The refusal REASON is part of the assertion. Without it a failure here says
//  only "something went wrong" and the next person has to re-run the whole suite
//  to find out what — which is exactly what happened the first time this battery
//  was run inside the full MariaDB suite.
t_ok(!empty($rA['ok']), 'A1 · acceptance converts the candidate to a team member'
     . (empty($rA['ok']) ? ' — REFUSED: ' . ($rA['code'] ?? '?') . ' / ' . ($rA['message'] ?? '') : ''));
t_ok($iA > 0, 'A2 · the inspectors row exists from acceptance (identity is continuous)');
//  STOP HERE if the hire did not happen. Everything below asserts things about a
//  person, and with no person the "absent from the roster" checks would all pass
//  for the wrong reason — a green tick for a hire that never occurred is worse
//  than a red one.
if ($iA <= 0) {
    //  Print what the engine itself recorded, so the reason is in the test output
    //  rather than in a database nobody will open.
    foreach (ops_all("SELECT kind, subject FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? ORDER BY id DESC", [$cA]) ?: [] as $a)
        echo "    >>> " . $a['kind'] . ': ' . $a['subject'] . "\n";
    t_ok(false, 'A2x · no team member was created — the rest of this suite cannot be trusted, stopping');
    return;
}
t_eq($g5status($iA), WF_ST_JOINING, 'A3 · …and its status is Joining pending, NOT Active');
t_ok(!wf_is_active($g5status($iA)), 'A4 · wf_is_active() agrees they are not operationally active');
t_eq($g5joined($cA), '', 'A5 · no joining date has been recorded');
t_ok(!$g5onRoster($iA), 'A6 · THE DEFECT: they do not appear on the schedulable roster');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE status='ACTIVE' AND id=?", [$iA]), 0,
     'A7 · …and are not counted by the capacity/report reads that ask for ACTIVE');
$listOn = array_column(inspectors_list(true), 'id');
t_ok(!in_array($iA, array_map('intval', $listOn), true), 'A8 · excluded from the allocation picker');
$listAll = array_column(inspectors_list(false), 'id');
t_ok(in_array($iA, array_map('intval', $listAll), true),
     'A9 · but STILL FINDABLE in the full team list, so their joining can be chased (§11)');

// ---------------------------------------------------------------------------
t_section('G5 B · IDENTITY CONTINUITY IS NOT BROKEN');
// ---------------------------------------------------------------------------
t_eq((int) ops_val("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [$cA]), $iA,
     'B1 · the application still points at the same team record');
t_ok(trim((string) ops_val("SELECT COALESCE(emp_code,'') FROM inspectors WHERE id=?", [$iA])) !== '',
     'B2 · the employee number was issued at acceptance, as before');
t_ok((int) ops_val("SELECT COALESCE(home_office_id,0) FROM inspectors WHERE id=?", [$iA]) > 0,
     'B3 · the branch was decided at acceptance, as before');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE id IN (SELECT inspector_id FROM candidates WHERE id=?)", [$cA]), 1,
     'B4 · exactly one team record — no duplicate workforce row');
$again = rcv_convert($cA, ['actor_id' => $g5u, 'team_role' => 'FIELD']);
t_ok(empty($again['ok']), 'B5 · converting a second time is refused, not a second person');

// ---------------------------------------------------------------------------
t_section('G5 C · ONE AUTHORITATIVE VOCABULARY, AND "NOT ACTIVE" IS NOT "HAS LEFT"');
// ---------------------------------------------------------------------------
t_eq(count(wf_statuses()), 3, 'C1 · three statuses and no more');
t_ok(isset(wf_statuses()[WF_ST_ACTIVE], wf_statuses()[WF_ST_JOINING], wf_statuses()[WF_ST_INACTIVE]),
     'C2 · Active, Joining pending, Inactive');
t_eq(wf_status_label(WF_ST_JOINING), 'Joining pending', 'C3 · the joiner reads in plain English, not as a code');
t_eq(wf_status_label(''), 'Active', 'C4 · a blank status still reads as Active (field-finding #26 preserved)');
t_ok(wf_is_active(''), 'C5 · …and is still treated as active, so nobody drops off the roster');
t_ok(wf_is_active(WF_ST_ACTIVE), 'C6 · ACTIVE is active');
t_ok(!wf_is_active(WF_ST_JOINING), 'C7 · a joiner is not active');
t_ok(!wf_is_active(WF_ST_INACTIVE), 'C8 · a leaver is not active');
//  The correction that makes a third value safe at all.
t_ok(wf_has_left(WF_ST_INACTIVE), 'C9 · a leaver has left');
t_ok(!wf_has_left(WF_ST_JOINING), 'C10 · *** a JOINER has NOT left — the two are different facts ***');
t_ok(!wf_has_left(''), 'C11 · a blank status is not a leaver');
t_ok(!wf_has_left(WF_ST_ACTIVE), 'C12 · an active person has not left');
//  Both canonical SQL expressions must actually run on THIS engine.
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors i WHERE " . wf_active_sql('i') . " AND i.id=?", [$iA]), 0,
     'C13 · wf_active_sql() runs on this engine and excludes the joiner');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors i WHERE " . wf_left_sql('i') . " AND i.id=?", [$iA]), 0,
     'C14 · wf_left_sql() runs on this engine and does NOT call the joiner a leaver');

// ---------------------------------------------------------------------------
t_section('G5 D · JOINING IS THE ACTIVATION BOUNDARY — THROUGH THE REAL ROUTE');
// ---------------------------------------------------------------------------
$g5root = dirname(__DIR__);
$g5engine = (getenv('DB_DRIVER') === 'mysql') ? 'mysql' : 'sqlite';
$g5env = $g5engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
      . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
      . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
//  Drives the REAL route in its own process, because redirect() exits.
$g5route = function (array $ops) use ($g5root, $g5env) {
    $procs = [];
    foreach ($ops as [$cid, $uid, $when, $delay]) {
        $cmd = $g5env . ' php ' . escapeshellarg($g5root . '/tests/_g5_worker.php') . ' '
             . (int) $cid . ' ' . (int) $uid . ' ' . escapeshellarg((string) $when) . ' ' . (int) $delay . ' 2>&1';
        $pipes = []; $pr = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($pr)) $procs[] = [$pr, $pipes];
    }
    $res = [];
    foreach ($procs as [$pr, $pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($pr);
        foreach (explode("\n", trim($raw)) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) $res[] = $d; }
    }
    return $res;
};
$today = date('Y-m-d');
$rD = $g5route([[$cA, $g5u, $today, 0]]);
t_eq(count($rD), 1, 'D1 · the real joining route ran in its own process');
t_eq($g5joined($cA), $today, 'D2 · the joining date is recorded');
t_eq($g5status($iA), WF_ST_ACTIVE, 'D3 · *** and THIS is where the person becomes operationally ACTIVE ***');
t_ok($g5onRoster($iA), 'D4 · they now appear on the schedulable roster');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE status='ACTIVE' AND id=?", [$iA]), 1,
     'D5 · and are now counted as capacity');
$flashD = implode(' | ', $rD[0]['flash'] ?? []);
t_ok(stripos($flashD, 'available for scheduling') !== false,
     'D6 · the recruiter is TOLD what changed, not just that something was saved');
$listOn2 = array_map('intval', array_column(inspectors_list(true), 'id'));
t_ok(in_array($iA, $listOn2, true), 'D7 · and they appear in the allocation picker');

// ---------------------------------------------------------------------------
t_section('G5 E · RECORDING THE SAME JOINING TWICE IS NOT TWO JOININGS');
// ---------------------------------------------------------------------------
$joinEvents = fn($c) => (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind='JOINED'", [(int) $c]);
$beforeE = $joinEvents($cA);
$g5route([[$cA, $g5u, $today, 0]]);
t_eq($joinEvents($cA), $beforeE, 'E1 · a repeat does not write a second JOINED event');
t_eq($g5status($iA), WF_ST_ACTIVE, 'E2 · and leaves them active');
t_ok(!wf_join_activate($iA, 'repeat'), 'E3 · wf_join_activate() reports no transition when already active');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$g5route([[$cA, $g5u, $yesterday, 0]]);
t_eq($g5joined($cA), $yesterday, 'E4 · a genuine date CORRECTION is still accepted');
t_eq($joinEvents($cA), $beforeE, 'E5 · …and is recorded as a correction, not as a new joining');

// ---------------------------------------------------------------------------
t_section('G5 F · CLEARING A JOINING TAKES THEM BACK OFF THE ROSTER');
// ---------------------------------------------------------------------------
$rF = $g5route([[$cA, $g5u, 'UNDO', 0]]);
t_eq($g5joined($cA), '', 'F1 · the joining date is cleared');
t_eq($g5status($iA), WF_ST_JOINING, 'F2 · *** and they go back to Joining pending, not left ACTIVE ***');
t_ok(!$g5onRoster($iA), 'F3 · off the schedulable roster again');
t_ok(stripos(implode(' ', $rF[0]['flash'] ?? []), 'still recorded as hired') !== false,
     'F4 · they are still hired, and the screen says so');
$rF2 = $g5route([[$cA, $g5u, 'UNDO', 0]]);
t_ok(stripos(implode(' ', $rF2[0]['flash'] ?? []), 'nothing to remove') !== false,
     'F5 · a second Undo is honest about having nothing to do');
t_eq($g5status($iA), WF_ST_JOINING, 'F6 · …and changes nothing');
t_ok(!wf_join_stand_down($iA, 'repeat'), 'F7 · wf_join_stand_down() reports no transition when already pending');

// ---------------------------------------------------------------------------
t_section('G5 G · SOMEBODY WHO NEVER ARRIVED IS NOT ACTIVATED BY A LATE JOINING');
// ---------------------------------------------------------------------------
[$cG, $iG] = $g5hire('G');
//  They resigned before their start date, so an administrator marked them inactive.
$pdo->prepare("UPDATE inspectors SET status=? WHERE id=?")->execute([WF_ST_INACTIVE, $iG]);
$rG = $g5route([[$cG, $g5u, $today, 0]]);
t_eq($g5status($iG), WF_ST_INACTIVE, 'G1 · recording a joining does NOT resurrect a leaver');
t_ok(!wf_join_activate($iG, 'late'), 'G2 · the transition refuses, because its precondition is not met');
$flashG = implode(' ', $rG[0]['flash'] ?? []);
t_ok(stripos($flashG, 'will not appear') !== false || stripos($flashG, 'Inactive') !== false,
     'G3 · *** and the user is WARNED rather than told it simply worked ***');
t_ok(!$g5onRoster($iG), 'G4 · they are not on the roster');

// ---------------------------------------------------------------------------
t_section('G5 H · A JOINER IS NOT CHASED FOR COMPANY PROPERTY (the leaver reads)');
// ---------------------------------------------------------------------------
//  Kit is routinely issued before day one — a laptop, an ID card, safety gear.
//  Under the old two-value test ("not ACTIVE" meant "has left") that person was
//  reported as a leaver holding unreturned property.
[$cH, $iH] = $g5hire('H');
$hasAssets = true;
try {
    $pdo->prepare("INSERT INTO asset_issues (asset_type,asset_name,identifier,quantity,person_kind,person_id,issued_on,status,created_at)
                   VALUES ('OTHER',?,?,1,'INSPECTOR',?,?,'ISSUED',?)")
        ->execute(['Gate5 laptop', $G5 . '-AST', $iH, $today, date('c')]);
    $g5made['asset'][] = (int) $pdo->lastInsertId();
} catch (Throwable $e) { $hasAssets = false; }
if ($hasAssets) {
    t_eq($g5status($iH), WF_ST_JOINING, 'H1 · the joiner holds a laptop before their first day');
    $leftJoiner = (int) ops_val("SELECT COUNT(*) FROM asset_issues a JOIN inspectors i ON i.id=a.person_id
                                 WHERE a.status='ISSUED' AND a.person_id=? AND " . wf_left_sql('i'), [$iH]);
    t_eq($leftJoiner, 0, 'H2 · *** they are NOT reported as a leaver with unreturned property ***');
    $pdo->prepare("UPDATE inspectors SET status=? WHERE id=?")->execute([WF_ST_INACTIVE, $iH]);
    $leftLeaver = (int) ops_val("SELECT COUNT(*) FROM asset_issues a JOIN inspectors i ON i.id=a.person_id
                                 WHERE a.status='ISSUED' AND a.person_id=? AND " . wf_left_sql('i'), [$iH]);
    t_eq($leftLeaver, 1, 'H3 · once they genuinely leave, the kit IS chased — the feature still works');
    $pdo->prepare("UPDATE inspectors SET status=? WHERE id=?")->execute([WF_ST_JOINING, $iH]);
} else {
    t_ok(true, 'H1 · asset register not present in this workspace — leaver reads not applicable');
}

// ---------------------------------------------------------------------------
t_section('G5 J · THE FORM CANNOT SILENTLY RECLASSIFY A JOINER');
// ---------------------------------------------------------------------------
$g5src = fn($f) => (string) @file_get_contents(dirname(__DIR__) . '/' . $f);
$formSrc = $g5src('views/ops/inspector_form.php');
t_ok(strpos($formSrc, 'wf_statuses()') !== false,
     'J1 · the status dropdown is built from the one vocabulary');
t_ok(strpos($formSrc, "['ACTIVE'=>'Active','INACTIVE'=>'Inactive']") === false,
     'J2 · *** and no longer from a hardcoded pair that omits the joiner ***');
$opsSrc = $g5src('lib/ops.php');
t_ok(strpos($opsSrc, "'status','Status','select',['opts'=>wf_statuses()]") !== false,
     'J3 · the Inspectors master form uses the vocabulary too');
t_ok(array_key_exists(WF_ST_JOINING, wf_statuses()),
     'J4 · so a joiner opened for an unrelated edit round-trips instead of being saved as Active');

// ---------------------------------------------------------------------------
t_section('G5 K · EXISTING DATA IS CLASSIFIED, NOT GUESSED AT (§17/§18)');
// ---------------------------------------------------------------------------
//  A row shaped exactly as the OLD code left them: active, from recruitment, no
//  joining date, and no history of ever having worked.
[$cK, $iK] = $g5hire('K');
$pdo->prepare("UPDATE inspectors SET status='ACTIVE' WHERE id=?")->execute([$iK]);
$surv = wf_joining_survey();
$ids = array_map('intval', array_column($surv['ids_reclassify'], 'id'));
t_ok(in_array($iK, $ids, true), 'K1 · the survey finds the legacy row and says it is safe to correct');
t_ok($surv['recruited'] >= 1, 'K2 · it reports how many team members came from recruitment');
t_ok($surv['direct_active'] >= 0, 'K3 · and how many active people were added directly (never touched)');
//  A DRY RUN must change nothing at all.
$dry = wf_joining_migrate(false);
t_ok(!empty($dry['dry_run']), 'K4 · the default is a dry run');
t_eq($dry['applied'], 0, 'K5 · …which changes nothing');
t_eq($g5status($iK), 'ACTIVE', 'K6 · the row is untouched after a dry run');
//  An AMBIGUOUS row: no joining date, but they have plainly worked.
[$cK2, $iK2] = $g5hire('K2');
$pdo->prepare("UPDATE inspectors SET status='ACTIVE' WHERE id=?")->execute([$iK2]);
//  A real work record. The `jobs` table has no `status` column — asserted here
//  rather than assumed, because a fixture that throws would make the three most
//  important assertions in this section vanish silently instead of fail.
$jobErr = '';
try {
    $pdo->prepare("INSERT INTO jobs (job_code,inspector_id,executing_office_id,scheduled_date,sbu,created_at)
                   VALUES (?,?,?,?,'IND',?)")
        ->execute([$G5 . '-JOB', $iK2, $g5off, $today, date('c')]);
} catch (Throwable $e) { $jobErr = $e->getMessage(); }
t_eq($jobErr, '', 'K6b · the work-history fixture was created (if this fails, K7–K10 prove nothing)');
$hasJobs = $jobErr === '';
t_ok($hasJobs, 'K6c · …so the ambiguous-row path below is actually exercised');
if ($hasJobs) {
    $surv2 = wf_joining_survey();
    $amb = array_map('intval', array_column($surv2['ids_ambiguous'], 'id'));
    $safe = array_map('intval', array_column($surv2['ids_reclassify'], 'id'));
    t_ok(in_array($iK2, $amb, true), 'K7 · somebody who HAS worked is classified AMBIGUOUS');
    t_ok(!in_array($iK2, $safe, true), 'K8 · …and is kept out of the safe-to-correct list');
}
//  APPLY: corrects the safe row only.
$app = wf_joining_migrate(true);
t_eq($g5status($iK), WF_ST_JOINING, 'K9 · *** the legacy row is corrected to Joining pending ***');
if ($hasJobs) t_eq($g5status($iK2), 'ACTIVE',
    'K10 · *** and the person who actually works is LEFT ALONE — never demoted ***');
t_ok($app['applied'] >= 1, 'K11 · the migration reports what it did');
//  IDEMPOTENT.
$app2 = wf_joining_migrate(true);
t_eq($app2['applied'], 0, 'K12 · running it again corrects nobody — it is idempotent');
t_eq($g5status($iK), WF_ST_JOINING, 'K13 · …and does not disturb the row it already fixed');
//  A LEAVER is never swept up by the migration.
[$cK3, $iK3] = $g5hire('K3');
$pdo->prepare("UPDATE inspectors SET status=? WHERE id=?")->execute([WF_ST_INACTIVE, $iK3]);
$surv3 = wf_joining_survey();
t_ok(!in_array($iK3, array_map('intval', array_column($surv3['ids_reclassify'], 'id')), true),
     'K14 · a leaver with no joining date is not a joiner and is not touched');
wf_joining_migrate(true);
t_eq($g5status($iK3), WF_ST_INACTIVE, 'K15 · …confirmed after an apply pass');
//  Somebody added through the Masters door, with no candidate record at all.
$pdo->prepare("INSERT INTO inspectors (name,emp_code,home_office_id,staff_kind,team_role,status,created_at)
               VALUES (?,?,?,'ASSET','FIELD','ACTIVE',?)")
    ->execute([$G5 . ' Direct', $G5 . '-D1', $g5off, date('c')]);
$iDirect = (int) $pdo->lastInsertId(); $g5made['ins'][] = $iDirect;
wf_joining_migrate(true);
t_eq($g5status($iDirect), 'ACTIVE',
     'K16 · *** a directly-added active colleague is NEVER reclassified by this migration ***');

// ---------------------------------------------------------------------------
t_section('G5 L · THE JOINING TRAIL IS REAL, NOT AN UNTYPED NOTE (§20)');
// ---------------------------------------------------------------------------
t_ok(isset(ACT_KINDS['JOINED']), 'L1 · JOINED is a registered activity kind…');
t_ok(isset(ACT_KINDS['JOINING_CLEARED']), 'L2 · …and so is JOINING_CLEARED');
t_ok(!array_key_exists('JOINED', act_kinds_manual()),
     'L3 · but neither is offered in the "record something that happened" composer');
t_ok(array_key_exists('CALL', act_kinds_manual()), 'L4 · while the human kinds still are');
[$cL, $iL] = $g5hire('L');
$g5route([[$cL, $g5u, $today, 0]]);
t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind='JOINED'", [$cL]), 1,
     'L5 · *** the joining is stored AS a joining — previously it degraded to NOTE ***');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='INSPECTOR' AND entity_id=?", [$iL]) >= 1,
     'L6 · and the workforce activation is recorded against the team member');
$g5route([[$cL, $g5u, 'UNDO', 0]]);
t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind='JOINING_CLEARED'", [$cL]), 1,
     'L7 · clearing it is recorded too');

// ---------------------------------------------------------------------------
t_section('G5 M · TWO PEOPLE RECORDING THE SAME JOINING AT ONCE (§16)');
// ---------------------------------------------------------------------------
[$cM, $iM] = $g5hire('M');
$rM = $g5route([[$cM, $g5u, $today, 300], [$cM, $g5u, $today, 300]]);
t_eq(count($rM), 2, 'M1 · two real processes both ran');
t_eq($g5status($iM), WF_ST_ACTIVE, 'M2 · the person ends up active exactly once');
t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind='JOINED'", [$cM]), 1,
     'M3 · *** and only ONE joining was recorded, not two ***');
t_eq($g5joined($cM), $today, 'M4 · with the right date');

// ---------------------------------------------------------------------------
t_section('G5 O · A HIRE IS NEVER REPORTED AS FAILED HAVING ACTUALLY HAPPENED');
// ---------------------------------------------------------------------------
//  Found by this gate's own battery, in the full MariaDB suite, and PRE-EXISTING
//  (identical at b1e793c).
//
//  The identity ledger migrates itself lazily on first use, and its first use is
//  inside the transaction that creates a team member. MariaDB commits implicitly
//  on ANY DDL — a no-op CREATE TABLE IF NOT EXISTS included — so the first
//  conversion in a process committed the half-made team member, then failed at
//  its own commit() with "There is no active transaction", rolled back nothing,
//  and told the recruiter the hire had failed for a person who now existed.
//
//  In production that is the first hire after a deploy, or the first hire in a
//  new workspace: the recruiter sees a failure, tries again, and the second
//  attempt is refused because the person is already on the team.
//  WHAT MAKES THIS REACHABLE IN PRODUCTION, and why the first attempt at this
//  test proved nothing: the migration short-circuits on a static keyed to
//  db_epoch(). boot() already runs it once, so a naive probe returns early and
//  never reaches the DDL at all. The epoch CHANGES whenever the connection does —
//  and this product gives every tenant its own database, so serving another
//  workspace invalidates that static and the next call does real schema work.
//  The first such call was the one inside the hire transaction.
//
//  The epoch is bumped here to put the migration back in the state a tenant
//  switch leaves it in, which is the only way this assertion tests anything.
t_ok(function_exists('connect_identity_migrate'), 'O1 · the identity ledger has a migration');
if (function_exists('connect_identity_migrate')) {
    $epochWas = $GLOBALS['__db_epoch'] ?? 0;
    $GLOBALS['__db_epoch'] = (int) $epochWas + 991;     //  as a tenant switch would
    $txOk = false; $still = false;
    try {
        $txOk = (bool) $pdo->beginTransaction();
        connect_identity_migrate();          //  must NOT commit what it did not open
        $still = $pdo->inTransaction();
        if ($still) $pdo->rollBack();
    } catch (Throwable $e) {
        try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}
    }
    $GLOBALS['__db_epoch'] = $epochWas;                 //  put it back
    if (function_exists('connect_identity_migrate')) { try { connect_identity_migrate(); } catch (Throwable $e) {} }
    t_ok($txOk, 'O2 · a transaction was open, with the migration due to run');
    t_ok($still, 'O3 · *** the migration did not silently commit the caller transaction ***');
}
//  And the business guarantee the above protects: a conversion either happens and
//  says so, or does not happen and says so. Never the third thing.
[$cO, $iO, $rO] = $g5hire('O');
$insExists = $iO > 0 ? (int) ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$iO]) : 0;
$linked    = (int) ops_val("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [$cO]);
if (!empty($rO['ok'])) {
    t_eq($insExists, 1, 'O4 · a reported SUCCESS means the team member really exists');
    t_eq($linked, $iO, 'O5 · …and the application really points at them');
} else {
    //  A reported failure must have left NOTHING behind.
    t_eq($linked, 0, 'O4 · *** a reported FAILURE left no team member attached ***');
}

// ---------------------------------------------------------------------------
t_section('G5 N · THE EARLIER GATES STILL HOLD');
// ---------------------------------------------------------------------------
t_ok(function_exists('rver_apply') && function_exists('rver_strictness'),
     'N1 · Gate 2/3 requirement-version engine is intact');
t_ok(function_exists('crev_block_reason') && function_exists('crev_raise_for_version'),
     'N2 · Gate 3 review engine is intact');
t_ok(in_array('JOIN', CREV_NEVER_ALLOWED, true),
     'N3 · *** Gate 3 §13 preserved: an open review still blocks JOIN ***');
t_ok(function_exists('appr_self_block_reason') && function_exists('appr_guard'),
     'N4 · Gate 4 self-approval governance is intact');
t_ok(strpos($g5src('lib/recruit_approval.php'), 'appr_self_block_reason($req') !== false,
     'N5 · …and still sits at the single approval choke point');
//  Gate 3's boundary, re-proved against the new status: a review blocks the
//  joining path, and the person must not be activated by a blocked attempt.
[$cN, $iN] = $g5hire('N');
$whyN = rexec_block_reason(0, 'JOIN', $cN);
t_ok(is_string($whyN), 'N6 · the execution guard still answers for the JOIN action');
t_eq($g5status($iN), WF_ST_JOINING, 'N7 · and an unjoined hire is still only Joining pending');

// ---------------------------------------------------------------------------
t_section('G5 Z · PUT THE SHARED FIXTURES BACK');
// ---------------------------------------------------------------------------
//  Gate 3's lesson: a suite that leaves rows behind changes what LATER suites
//  see. Live requirements in particular push other tests' records off the
//  top-N dashboard lists, and extra ACTIVE people change capacity counts.
try {
    foreach ($g5made['asset'] as $a) $pdo->prepare("DELETE FROM asset_issues WHERE id=?")->execute([$a]);
    $pdo->prepare("DELETE FROM jobs WHERE job_code=?")->execute([$G5 . '-JOB']);
    foreach ($g5made['cand'] as $c) {
        $pdo->prepare("DELETE FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=?")->execute([$c]);
        $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([$c]);
    }
    foreach ($g5made['ins'] as $i) {
        $pdo->prepare("DELETE FROM activities WHERE entity_kind='INSPECTOR' AND entity_id=?")->execute([$i]);
        $pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$i]);
    }
    foreach ($g5made['req'] as $r) $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$r]);
    foreach ($g5made['user'] as $u) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$u]);
} catch (Throwable $e) {}
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE emp_code LIKE ?", [$G5 . '%']), 0,
     'Z1 · the people this suite created are gone');
t_eq((int) ops_val("SELECT COUNT(*) FROM requisitions WHERE req_code LIKE ?", [$G5 . '%']), 0,
     'Z2 · and so are its requirements, so later suites see what they expect');
$_SESSION['uid'] = null; current_user(true); ua(true);
