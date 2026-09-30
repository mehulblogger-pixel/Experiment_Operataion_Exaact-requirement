<?php
// ============================================================================
//  GATE 1B — THE CONFIGURABLE PIPELINE IS THE AUTHORITY
//
//  D1/C14: a candidate's CURRENT recruitment state is their configured pipeline
//  stage; the stage/event ledger is their history; candidates.stage is
//  compatibility and migration data with no authority of its own.
//
//  The load-bearing claims, each asserted below:
//    A  the closed KIND exists, is distinct from terminal, and carries
//       configurable outcomes beneath one kind (C47)
//    B  classification comes from the kind, through ONE choke point, and its two
//       renderings (PHP and SQL) cannot disagree
//    C  AN ISSUED OFFER IS NOT A FILLED SEAT (G1A-2) — the business defect
//    D  the pipeline beats the legacy column, always, and the legacy column is
//       never silently substituted for a missing pipeline answer
//    E  no legacy writer is needed for current-state correctness any more
//    F  a malformed legacy value is still never repaired (G0-1)
//    G  a pipeline/legacy disagreement is still surfaced, not guessed (G0-2)
//    H  a candidate with no recruitment process is never given a pipeline (D3)
// ============================================================================

$s   = 'G1B-' . random_int(100000, 999999);
$off = (int) ops_val("SELECT COALESCE(MAX(id),0)+1 FROM offices");
db()->prepare("INSERT INTO offices (id,name,city) VALUES (?,?,?)")->execute([$off, $s . ' Office', 'Kolkata']);
recruitpipe_migrate();

$mkReq = function ($qty = 5) use ($s, $off) {
    db()->prepare("INSERT INTO requisitions (req_code,designation,office_id,sbu,status,quantity,created_at)
                   VALUES (?,?,?, 'IND','OPEN',?,?)")
        ->execute([$s . '-R' . random_int(1000, 9999), 'Inspector', $off, $qty, date('c')]);
    return (int) db()->lastInsertId();
};
$mkCand = function ($rq = null, $stage = 'RECEIVED') use ($s) {
    db()->prepare("INSERT INTO candidates (first_name,last_name,stage,sbu,requisition_id,created_at)
                   VALUES (?,'G1B',?, 'IND',?,?)")->execute([$s, $stage, $rq, date('c')]);
    return (int) db()->lastInsertId();
};
$row   = fn($id) => ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
$st    = fn($id) => rpipe_current_state($row($id));
$cls   = fn($id) => reqf_classify($row($id));
$put   = function ($id, $stageId) { db()->prepare("UPDATE candidates SET pipeline_id=(SELECT pipeline_id FROM recruit_stages WHERE id=?), pipeline_stage_id=? WHERE id=?")->execute([$stageId, $stageId, $id]); };

$rq    = $mkReq(5);
$req   = ops_one("SELECT * FROM requisitions WHERE id=?", [$rq]);
$pipe  = recruitpipe_for($req);
$prog  = recruitpipe_effective_stages((int) $pipe['id'], $req);
$offr  = recruitpipe_closed_stages((int) $pipe['id'], $req);
$byKind = function ($k) use ($prog) { foreach ($prog as $x) if ($x['kind'] === $k) return $x; return null; };
$stepS  = $byKind('step'); $ivS = $byKind('interview'); $offS = $byKind('offer'); $termS = $byKind('terminal');

// ---------------------------------------------------------------------------
t_section('G1B · A — the closed kind (C47)');
// ---------------------------------------------------------------------------
t_ok(array_key_exists('closed', RPIPE_STAGE_KINDS), 'A1 · a closed stage kind exists');
t_ok(array_key_exists('terminal', RPIPE_STAGE_KINDS), 'A2 · and terminal still exists beside it');
t_ok(rpipe_kind_is_closed('closed') && !rpipe_kind_is_closed('terminal'),
    'A3 · they are NOT interchangeable: terminal is a successful finish, closed is not proceeding');
t_ok(!rpipe_kind_is_terminal('closed'), 'A4 · …and the reverse also holds');
t_eq(rpipe_kind_class('terminal'), 'FILLED', 'A5 · a successful finish fills a seat');
t_eq(rpipe_kind_class('closed'), 'LOST', 'A6 · a closure loses it');
t_ok(!array_key_exists('rejected', RPIPE_STAGE_KINDS) && !array_key_exists('accepted', RPIPE_STAGE_KINDS),
    'A7 · "rejected" and "accepted" are NOT stage kinds — outcomes never became kinds');

//  CONFIGURING a closed stage must work, and an invented kind must not.
//  recruitpipe_stage_save() accepts only kinds in the vocabulary and falls back to
//  'step' for anything else — so this also fails if 'closed' is ever dropped from
//  RPIPE_STAGE_KINDS, which would silently turn every configured off-ramp into an
//  ordinary step and make closures uncountable.
recruitpipe_stage_save(['pipeline_id' => (int) $pipe['id'], 'seq' => 9500,
    'stage_key' => 'G1B_CLOSE', 'name' => 'Not proceeding — test', 'kind' => 'closed']);
$savedClosed = (int) ops_val("SELECT COUNT(*) FROM recruit_stages WHERE pipeline_id=? AND stage_key='G1B_CLOSE' AND kind='closed'", [(int) $pipe['id']]);
t_eq($savedClosed, 1, 'A7a · a stage may be CONFIGURED as closed through the ordinary save path');
recruitpipe_stage_save(['pipeline_id' => (int) $pipe['id'], 'seq' => 9510,
    'stage_key' => 'G1B_BOGUS', 'name' => 'Bogus', 'kind' => 'rejected']);
t_eq((string) ops_val("SELECT kind FROM recruit_stages WHERE pipeline_id=? AND stage_key='G1B_BOGUS'", [(int) $pipe['id']]), 'step',
    'A7b · …and an invented kind such as "rejected" is refused, falling back to step');
//  Cleaned up so the off-ramp count below is the seeded configuration.
db()->prepare("DELETE FROM recruit_stages WHERE pipeline_id=? AND stage_key IN ('G1B_CLOSE','G1B_BOGUS')")->execute([(int) $pipe['id']]);
$offr = recruitpipe_closed_stages((int) $pipe['id'], $req);

t_ok(count($offr) >= 3, 'A8 · the pipeline configures closed off-ramps: ' . count($offr));
$outcomes = array_map(fn($x) => strtoupper((string) $x['closed_outcome']), $offr);
foreach (['REJECTED', 'WITHDRAWN', 'OFFER_DECLINED'] as $o)
    t_ok(in_array($o, $outcomes, true), 'A9 · …including the ' . $o . ' outcome');
foreach ($offr as $o) t_eq($o['kind'], 'closed', 'A10 · every outcome is the SAME single kind: ' . $o['closed_outcome']);
t_ok(count(rpipe_closed_outcomes()) >= 10, 'A11 · the outcome vocabulary is configurable and ships 10+ values');
t_ok(!str_contains(implode('|', array_values(rpipe_closed_outcomes())), '{req}'),
    'A12 · …with the workspace\'s own word for the requirement resolved, not a raw placeholder');

//  THE OFF-RAMPS ARE NOT STEPS.
foreach ($prog as $x) t_ok($x['kind'] !== 'closed', 'A13 · the progression contains no closed stage: ' . $x['stage_key']);
$resolvable = recruitpipe_resolvable_stages((int) $pipe['id'], $req);
t_eq(count($resolvable), count($prog) + count($offr),
    'A14 · but a candidate can still BE on one — resolvable = progression + off-ramps');

// ---------------------------------------------------------------------------
t_section('G1B · B — one classification choke point, two renderings that agree');
// ---------------------------------------------------------------------------
foreach (RPIPE_KIND_CLASS as $k => $want) t_eq(rpipe_kind_class($k), $want, 'B1 · kind ' . $k . ' classifies as ' . $want);
t_eq(rpipe_kind_class('nonsense'), '', 'B2 · an unknown kind classifies as nothing, not as something plausible');

//  THE ANTI-DRIFT ASSERTION. The PHP rendering and the SQL rendering are built
//  from the same map; this proves they cannot disagree for any kind…
$probe = $mkReq(9);
foreach (recruitpipe_resolvable_stages((int) $pipe['id'], $req) as $stg) {
    $c = $mkCand($probe, '');
    $put($c, (int) $stg['id']);
    $sqlCls = (string) ops_val("SELECT " . reqf_class_expr('c') . " FROM candidates c" . reqf_class_join('c') . " WHERE c.id=?", [$c]);
    t_eq($sqlCls, reqf_classify($row($c)),
        'B3 · SQL and PHP agree for kind ' . $stg['kind'] . ' (' . $stg['stage_key'] . ')');
}
//  …and for every legacy value too, including the ones that translate to nothing.
foreach (array_merge(array_keys(CAND_STAGES), ['OFFER', ' RECEIVED', '']) as $lv) {
    $c = $mkCand($probe, $lv);
    $sqlCls = (string) ops_val("SELECT " . reqf_class_expr('c') . " FROM candidates c" . reqf_class_join('c') . " WHERE c.id=?", [$c]);
    t_eq($sqlCls, reqf_classify($row($c)), 'B4 · SQL and PHP agree for legacy value ' . var_export($lv, true));
}

// ---------------------------------------------------------------------------
t_section('G1B · C — AN ISSUED OFFER IS NOT A FILLED SEAT (G1A-2)');
// ---------------------------------------------------------------------------
//  Case A of the gate: five seats, five offers out, nobody accepted.
$rqA = $mkReq(5);
$reqA = ops_one("SELECT * FROM requisitions WHERE id=?", [$rqA]);
$aIds = [];
for ($i = 0; $i < 5; $i++) { $c = $mkCand($rqA, ''); $put($c, (int) $offS['id']); $aIds[] = $c; }
$cntA = reqf_counts($rqA);
t_eq($cntA['filled'], 0,       'C1 · five offers issued, none accepted → filled = 0');
t_eq($cntA['in_progress'], 5,  'C2 · …all five are still in progress');
t_eq($cntA['remaining'], 5,    'C3 · …and all five seats remain to be filled');
$hA = recruit_req_health($reqA);
t_eq($hA['filled'], 0,         'C4 · requirement health agrees: filled = 0');
t_eq($hA['vacancies'], 5,      'C5 · …five vacancies');
t_ok(!in_array('All positions filled', $hA['reasons'], true),
    'C6 · *** IT NO LONGER SAYS "All positions filled" — the G1A-2 defect is gone ***');

//  Case B: the same five accept.
foreach ($aIds as $c) $put($c, (int) $termS['id']);
$cntB = reqf_counts($rqA);
t_eq($cntB['filled'], 5,       'C7 · five acceptances → filled = 5');
t_eq($cntB['in_progress'], 0,  'C8 · …nobody still in progress');
t_eq($cntB['remaining'], 0,    'C9 · …and no seats left');
$hB = recruit_req_health(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqA]));
t_eq($hB['filled'], 5,         'C10 · requirement health agrees: filled = 5');
t_eq($hB['vacancies'], 0,      'C11 · …no vacancies');
t_ok(in_array('All positions filled', $hB['reasons'], true),
    'C12 · …and NOW it says all positions are filled, because they are');

//  Case C: a mixed requirement reads the same everywhere.
$rqC = $mkReq(6);
$mix = ['step' => $stepS, 'interview' => $ivS, 'offer' => $offS, 'terminal' => $termS];
foreach ($mix as $k => $stg) { $c = $mkCand($rqC, ''); $put($c, (int) $stg['id']); }
$cClosed = $mkCand($rqC, ''); $put($cClosed, (int) $offr[0]['id']);
$cntC = reqf_counts($rqC);
t_eq($cntC['filled'], 1,      'C13 · mixed requirement: one terminal = one filled');
t_eq($cntC['in_progress'], 3, 'C14 · …step + interview + offer = three in progress');
t_eq($cntC['lost'], 1,        'C15 · …and one closed = one lost');
$hC = recruit_req_health(ops_one("SELECT * FROM requisitions WHERE id=?", [$rqC]));
t_eq($hC['filled'], $cntC['filled'], 'C16 · requirement health and fulfilment give the SAME filled figure');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates c" . reqf_class_join('c')
     . " WHERE c.requisition_id=? AND " . reqf_class_expr('c') . "='FILLED'", [$rqC]), $cntC['filled'],
     'C17 · …and so does the reporting rendering used by the KPI and dashboard queries');

// ---------------------------------------------------------------------------
t_section('G1B · D — the pipeline wins, and legacy is never substituted');
// ---------------------------------------------------------------------------
$cWin = $mkCand($rq, 'ACCEPTED');          // legacy says hired…
$put($cWin, (int) $ivS['id']);             // …the pipeline says interview
t_eq($st($cWin)['source'], 'PIPELINE', 'D1 · the pipeline is named as the source');
t_eq($st($cWin)['kind'], 'interview',  'D2 · the position is the configured stage');
t_eq($cls($cWin), 'ACTIVE',            'D3 · *** the classification follows the PIPELINE, not the legacy ACCEPTED ***');

$cLost = $mkCand($rq, 'RECEIVED');         // legacy says live…
$put($cLost, (int) $offr[0]['id']);        // …the pipeline says closed
t_eq($cls($cLost), 'LOST', 'D4 · and the reverse: a closed pipeline stage beats a live legacy value');
t_ok($st($cLost)['closed'] === true, 'D5 · closed is answered from the kind');
t_eq($st($cLost)['closed_outcome'], 'REJECTED', 'D6 · …with the configured outcome, not a guessed label');

//  NEVER SUBSTITUTED: a stage id that does not belong to the candidate's pipeline
//  must not be read as a position, and must not fall back to a pipeline answer.
$cDang = $mkCand($rq, 'SHORTLISTED');
db()->prepare("UPDATE candidates SET pipeline_id=?, pipeline_stage_id=? WHERE id=?")
    ->execute([(int) $pipe['id'], 999999, $cDang]);
t_ok($st($cDang)['source'] !== 'PIPELINE', 'D7 · a dangling stage id yields NO pipeline answer');
t_eq($st($cDang)['stage_id'], 0, 'D8 · …and no borrowed stage id');

// ---------------------------------------------------------------------------
t_section('G1B · E — no legacy writer is needed for current-state correctness');
// ---------------------------------------------------------------------------
$cMv = $mkCand($rq, 'RECEIVED');
$before = (string) $row($cMv)['stage'];
recruitpipe_cand_goto($row($cMv), (int) $ivS['id'], 'moved', 'tester');
t_eq((string) $row($cMv)['stage'], $before, 'E1 · a pipeline move does NOT write the legacy column');
t_eq($cls($cMv), 'ACTIVE', 'E2 · …and the classification is still right without it');
recruitpipe_cand_goto($row($cMv), (int) $offS['id'], '', 'tester');
t_eq((string) $row($cMv)['stage'], $before, 'E3 · still no legacy write at the offer stage');
t_eq($st($cMv)['kind'], 'offer', 'E4 · …and the offer position is readable from the pipeline alone');
t_ok((int) ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=?", [$cMv]) >= 2,
    'E5 · every move is still recorded in the existing ledger — history is not lost');

//  The stage route translates a legacy TARGET into a pipeline stage.
$cRt = $mkCand($rq, 'SHORTLISTED');
t_eq((int) rpipe_stage_for_legacy_target($row($cRt), 'ACCEPTED')['id'], (int) $termS['id'],
    'E6 · a request to accept resolves to the pipeline\'s terminal stage');
t_eq((int) rpipe_stage_for_legacy_target($row($cRt), 'OFFERED')['id'], (int) $offS['id'],
    'E7 · a request to offer resolves to the offer stage');
$rjT = rpipe_stage_for_legacy_target($row($cRt), 'REJECTED');
t_eq(strtoupper((string) $rjT['closed_outcome']), 'REJECTED',
    'E8 · a rejection resolves to the off-ramp with the REJECTED outcome…');
$wdT = rpipe_stage_for_legacy_target($row($cRt), 'WITHDRAWN');
t_eq(strtoupper((string) $wdT['closed_outcome']), 'WITHDRAWN',
    'E9 · …and a withdrawal to its own, so two different outcomes do not collapse into one');
t_ok(rpipe_stage_for_legacy_target($row($cRt), 'SHORTLISTED') === null,
    'E10 · an early step resolves to NOTHING — six step stages means the value names none of them');
t_ok(rpipe_stage_for_legacy_target($row($cRt), 'NOT_A_STAGE') === null,
    'E11 · and an undefined value resolves to nothing at all');

// ---------------------------------------------------------------------------
t_section('G1B · F — a malformed legacy value is still never repaired (G0-1)');
// ---------------------------------------------------------------------------
$cBad = $mkCand($rq, 'OFFER');
t_ok(!rpipe_legacy_stage_valid('OFFER'), 'F1 · OFFER is still not a defined stage');
t_eq(rpipe_legacy_kind('OFFER'), '', 'F2 · …and translates to no kind, so nothing is inferred from it');
t_eq($cls($cBad), '', 'F3 · its classification is UNKNOWN — not quietly ACTIVE, not quietly LOST');
$cSp = $mkCand($rq, ' RECEIVED');
t_eq(rpipe_legacy_kind(' RECEIVED'), '', 'F4 · the leading-space value translates to nothing either');
rpipe_recon_scan(500);
rpipe_reconcile_run(500, false);
t_eq((string) $row($cBad)['stage'], 'OFFER',    'F5 · still OFFER after a scan AND a dry-run migration');
t_eq((string) $row($cSp)['stage'], ' RECEIVED', 'F6 · still byte-identical, space included');
$planBad = rpipe_migration_plan($row($cBad));
t_eq($planBad['action'], 'REVIEW', 'F7 · migration refuses to place it…');
t_ok(str_contains($planBad['reason'], 'never guessed'), 'F8 · …and says why: ' . $planBad['reason']);

// ---------------------------------------------------------------------------
t_section('G1B · G — a disagreement is surfaced, never guessed (G0-2)');
// ---------------------------------------------------------------------------
$cCf = $mkCand($rq, 'REJECTED');
$put($cCf, (int) $ivS['id']);
t_ok($st($cCf)['conflict'], 'G1 · legacy closed + live pipeline stage = a detected conflict');
t_eq($cls($cCf), 'ACTIVE', 'G2 · the PIPELINE still decides the classification');
t_eq(rpipe_recon_class($row($cCf))['recon_class'], 'E_CONFLICT', 'G3 · and it classifies as a conflict for a person');
$pCf = rpipe_migration_plan($row($cCf));
t_eq($pCf['action'], 'SKIP', 'G4 · migration does NOT touch it');
t_ok(str_contains($pCf['reason'], 'G0-2'), 'G5 · …and names the conflict rather than resolving it');
t_eq((string) $row($cCf)['stage'], 'REJECTED', 'G6 · the legacy evidence is preserved, not deleted');

// ---------------------------------------------------------------------------
t_section('G1B · H — a pool candidate is never enrolled in recruitment (D3)');
// ---------------------------------------------------------------------------
$cPool = $mkCand(null, 'RECEIVED');            // no requirement at all
t_ok(rpipe_stage_for_legacy_target($row($cPool), 'ACCEPTED') === null,
    'H1 · no recruitment process, so no pipeline target — even though a default pipeline exists');
t_eq((int) ops_val("SELECT COALESCE(pipeline_stage_id,0) FROM candidates WHERE id=?", [$cPool]), 0,
    'H2 · and they still have no pipeline position');
$pPool = rpipe_migration_plan($row($cPool));
t_eq($pPool['action'], 'SKIP', 'H3 · migration leaves them in the pool…');
t_ok(str_contains($pPool['reason'], 'D3'), 'H4 · …explicitly: ' . $pPool['reason']);
rpipe_reconcile_run(500, true, 'g1b-test');
t_eq((int) ops_val("SELECT COALESCE(pipeline_stage_id,0) FROM candidates WHERE id=?", [$cPool]), 0,
    'H5 · and an APPLIED migration pass still leaves them out of recruitment');

// ---------------------------------------------------------------------------
t_section('G1B · I — controlled migration: deterministic, idempotent, additive');
// ---------------------------------------------------------------------------
$rqM = $mkReq(9);
$cAcc = $mkCand($rqM, 'ACCEPTED');
$cOfd = $mkCand($rqM, 'OFFERED');
$cRej = $mkCand($rqM, 'REJECTED');
$cIv  = $mkCand($rqM, 'INTERVIEW');
$cShr = $mkCand($rqM, 'SHORTLISTED');

$pAcc = rpipe_migration_plan($row($cAcc));
t_eq($pAcc['action'], 'MIGRATE', 'I1 · ACCEPTED maps deterministically…');
t_eq((int) $pAcc['stage_id'], (int) $termS['id'], 'I2 · …to the one terminal stage');
t_eq((int) rpipe_migration_plan($row($cOfd))['stage_id'], (int) $offS['id'], 'I3 · OFFERED maps to the one offer stage');
t_eq(strtoupper((string) ops_val("SELECT closed_outcome FROM recruit_stages WHERE id=?",
     [(int) rpipe_migration_plan($row($cRej))['stage_id']])), 'REJECTED', 'I4 · REJECTED maps to its own off-ramp');

//  DETERMINISM IS A PROPERTY OF THE CONFIGURATION, NOT OF THE VALUE.
//
//  This requirement has no grade, so CORP18's L2 interview is conditional and does
//  not apply: exactly ONE interview stage exists and the position IS certain.
$pIv = rpipe_migration_plan($row($cIv));
t_eq($pIv['action'], 'MIGRATE', 'I5 · INTERVIEW maps when the pipeline has exactly one interview stage');
t_eq((int) $pIv['stage_id'], (int) $ivS['id'], 'I5b · …to that stage');

//  …and the SAME legacy value is refused on a requirement where two apply. A
//  SENIOR grade brings CORP18's L2 into the path, so "INTERVIEW" names neither.
$rqSr = $mkReq(4);
db()->prepare("UPDATE requisitions SET grade='SENIOR' WHERE id=?")->execute([$rqSr]);
$reqSr = ops_one("SELECT * FROM requisitions WHERE id=?", [$rqSr]);
$ivCount = 0;
foreach (recruitpipe_effective_stages((int) $pipe['id'], $reqSr) as $x) if ($x['kind'] === 'interview') $ivCount++;
t_eq($ivCount, 2, 'I6 · a senior requirement really does put two interview stages in the path — armed');
$cIvSr = $mkCand($rqSr, 'INTERVIEW');
$pIvSr = rpipe_migration_plan($row($cIvSr));
t_eq($pIvSr['action'], 'REVIEW', 'I6b · *** and there the SAME value is refused rather than guessed ***');
t_ok(str_contains($pIvSr['reason'], 'ambiguous'), 'I6c · …saying exactly why: ' . $pIvSr['reason']);
t_eq(rpipe_migration_plan($row($cShr))['action'], 'REVIEW',
    'I7 · SHORTLISTED never maps — six step stages apply, so it names none of them');

$dry = rpipe_reconcile_run(500, false);
t_ok($dry['migrated'] >= 3, 'I8 · a dry run reports what it WOULD move: ' . $dry['migrated']);
t_eq((int) ops_val("SELECT COALESCE(pipeline_stage_id,0) FROM candidates WHERE id=?", [$cAcc]), 0,
    'I9 · …and moves nothing at all');

$legacyBefore = [];
foreach ([$cAcc, $cOfd, $cRej, $cIv, $cShr, $cIvSr] as $c) $legacyBefore[$c] = (string) $row($c)['stage'];
$run1 = rpipe_reconcile_run(500, true, 'g1b-test');
t_ok($run1['migrated'] >= 3, 'I10 · an applied run moves the certain ones: ' . $run1['migrated']);
t_eq((int) $row($cAcc)['pipeline_stage_id'], (int) $termS['id'], 'I11 · the accepted candidate is on the terminal stage');
t_eq($cls($cAcc), 'FILLED', 'I12 · …and now classifies from the pipeline');
t_eq($cls($cOfd), 'ACTIVE', 'I13 · the offered candidate classifies as active — still not a filled seat');
t_eq($cls($cRej), 'LOST',   'I14 · the rejected candidate classifies as lost');
t_eq((int) ops_val("SELECT COALESCE(pipeline_stage_id,0) FROM candidates WHERE id=?", [$cShr]), 0,
    'I15 · *** the unmappable ones were NOT guessed at ***');
t_eq((int) ops_val("SELECT COALESCE(pipeline_stage_id,0) FROM candidates WHERE id=?", [$cIvSr]), 0,
    'I15b · …including the genuinely ambiguous interview on the senior requirement');

//  ADDITIVE — the historical value survives migration.
foreach ($legacyBefore as $c => $was)
    t_eq((string) $row($c)['stage'], $was, 'I16 · the legacy value is preserved as history for candidate ' . $c);

//  IDEMPOTENT — a second pass changes nothing.
$run2 = rpipe_reconcile_run(500, true, 'g1b-test');
t_eq($run2['migrated'], 0, 'I17 · a second applied pass migrates nobody — idempotent');
t_eq((int) $row($cAcc)['pipeline_stage_id'], (int) $termS['id'], 'I18 · …and leaves the first pass exactly as it was');

//  AUDITABLE, and never mistakable for a recruiter's own move.
$mig = ops_all("SELECT * FROM candidate_events WHERE candidate_id=? AND event_kind='MIGRATE'", [$cAcc]) ?: [];
t_ok(count($mig) >= 1, 'I19 · the reconciliation is on the stage ledger');
t_eq((string) $mig[0]['track'], 'MIGRATION',
    'I20 · …on its own track, so no stage duration is ever measured across a migration');

//  RESUMABLE — a bounded pass reports that more remain.
$rqR = $mkReq(9);
for ($i = 0; $i < 4; $i++) $mkCand($rqR, 'ACCEPTED');
$small = rpipe_reconcile_run(2, false);
t_ok($small['examined'] <= 2, 'I21 · a bounded pass examines at most its limit');
t_ok($small['more'], 'I22 · …and says more remain, so the next pass continues from there');

// ---------------------------------------------------------------------------
t_section('G1B · J — the two writers Gate 0 missed, driven end to end');
// ---------------------------------------------------------------------------
//  Gate 0 counted three legacy writers; Gate 1A found six, the two extra being
//  recruit_offer.php's offer-issued stamp and recruit_exec.php's joining revert.
//  Both were correct code doing a necessary job through the wrong column. These
//  assertions drive the REAL production paths, because a mutation that puts either
//  legacy write back must fail the suite rather than pass it quietly.

$rqJ = $mkReq(3);
$cJ  = $mkCand($rqJ, 'SHORTLISTED');
$legacyJ = (string) $row($cJ)['stage'];

$offId = (int) offer_create($cJ, ['ctc' => 600000, 'joining_date' => date('Y-m-d', strtotime('+30 days'))]);
t_ok($offId > 0, 'J1 · an offer is created through the production path');
offer_submit($offId); offer_approve($offId);
[$okJ, $msgJ] = offer_issue($offId);
t_ok($okJ, 'J2 · …and issued: ' . (string) $msgJ);

//  THE KILL FOR "the offer-issued legacy write is restored".
t_eq((int) $row($cJ)['pipeline_stage_id'], (int) $offS['id'],
    'J3 · *** issuing the offer moved the PIPELINE to the offer stage ***');
t_eq($st($cJ)['kind'], 'offer', 'J4 · …so the current state reads offer from the configuration');
t_eq((string) $row($cJ)['stage'], $legacyJ,
    'J5 · *** and the legacy column was NOT written — no second authority ***');
t_eq($cls($cJ), 'ACTIVE', 'J6 · an issued offer is ACTIVE work, not a filled seat');
t_ok((int) ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=? AND track='PIPELINE'", [$cJ]) >= 1,
    'J7 · the move is on the ledger, on the pipeline track — the Phase 5 measurement still works');

//  THE KILL FOR "the joining-revert legacy write is restored".
//
//  Two candidates, one seat. The second is written past the gate and the
//  compensator puts them back. What must go back is the PIPELINE POSITION: a
//  revert that only restored the legacy value would leave them on the terminal
//  stage, still counted against a seat they were refused.
$rqK = $mkReq(1);
$kWin = $mkCand($rqK, ''); $put($kWin, (int) $termS['id']);         // the seat is taken
$kLos = $mkCand($rqK, 'OFFERED'); $put($kLos, (int) $offS['id']);   // …and they were at offer
$kPrior = (int) $row($kLos)['pipeline_stage_id'];
t_eq($cls($kWin), 'FILLED', 'J8 · the one seat really is taken — the trap is armed');

$put($kLos, (int) $termS['id']);                                     // written past the gate
t_eq($cls($kLos), 'FILLED', 'J9 · …and the second joining really was written');
$revK = rexec_join_enforce_after_write($kLos, 'OFFERED', '', $kPrior);
t_ok($revK !== '', 'J10 · the compensator refused it: ' . $revK);
t_eq((int) $row($kLos)['pipeline_stage_id'], $kPrior,
    'J11 · *** they are back on the exact pipeline stage they were on ***');
t_ok($cls($kLos) !== 'FILLED', 'J12 · …so they no longer hold a seat');
t_eq(reqf_counts($rqK)['filled'], 1, 'J13 · and the requirement counts ONE filled seat, not two');

//  The revert without a known prior position must not leave them on a terminal
//  stage either — that was the defect this parameter exists to close.
$rqL = $mkReq(1);
$lWin = $mkCand($rqL, ''); $put($lWin, (int) $termS['id']);
$lLos = $mkCand($rqL, 'OFFERED');                                    // no pipeline position at all
$put($lLos, (int) $termS['id']);                                     // …then written past the gate
$revL = rexec_join_enforce_after_write($lLos, 'OFFERED', '', 0);      // 0 = they had none
t_ok($revL !== '', 'J14 · the compensator refused this one too');
t_eq((int) ops_val("SELECT COALESCE(pipeline_stage_id,0) FROM candidates WHERE id=?", [$lLos]), 0,
    'J15 · *** "they had no position" is restored as NO position, not left on the terminal stage ***');
t_ok($cls($lLos) !== 'FILLED', 'J16 · …so they do not hold a seat either');

// ---------------------------------------------------------------------------
t_section('G1B · K — the REAL route, and the closure reason it must keep');
// ---------------------------------------------------------------------------
//  Driven through tests/_p4_worker.php, which dispatches the actual
//  candidate-stage route in its own process — because redirect() exits, and
//  because a route is exactly where a control gets forgotten. Nothing below is
//  believed: the database is read afterwards.
$root = dirname(__DIR__);
//  The route is gated on is_coordinator_level(), so the worker is handed a real
//  user to act as. Without one the route refuses and nothing happens — which is
//  itself correct behaviour, but it is not what these assertions are about.
db()->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES (?,?, 'MANAGER',1,1,?,'')")->execute(['g1b_boss_' . random_int(1000, 9999), 'G1B', $off]);
$uG = (int) db()->lastInsertId();
$_SESSION['uid'] = $uG; current_user(true); ua(true);
t_ok(is_coordinator_level(), 'K0 · the acting user may move a candidate — the route will not refuse');

$drive = function ($id, $to, array $post = []) use ($root, $uG) {
    $env = (getenv('DB_DRIVER') === 'mysql')
        ? 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
          . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
          . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'))
        : 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'));
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_p4_worker.php') . ' route_cand_stage '
         . (int) $id . ' ' . escapeshellarg((string) $to) . ' ' . escapeshellarg(json_encode($post))
         . ' 0 ' . (int) $uG . ' 2>&1';
    $pipes = []; $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) return null;
    $raw = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    return $raw;
};

$rqN = $mkReq(4);
$cRj = $mkCand($rqN, ''); $put($cRj, (int) $ivS['id']);
$legacyRj = (string) $row($cRj)['stage'];
$drive($cRj, 'REJECTED', ['remark' => 'gate 1b route walk',
                          'drop_point' => 'INTERVIEW', 'drop_reason' => 'NOT_SUITABLE']);
$rjRow = $row($cRj);
$rjSt  = rpipe_current_state($rjRow);

t_eq($rjSt['kind'], 'closed', 'K1 · the route put the candidate on a CLOSED pipeline stage');
t_eq($rjSt['closed_outcome'], 'REJECTED', 'K2 · …on the off-ramp whose outcome is rejected');
t_eq($rjSt['class'], 'LOST', 'K3 · …so they classify as lost');
t_eq((string) $rjRow['stage'], $legacyRj,
    'K4 · *** and the legacy column was NOT written by the route ***');

//  THE CLOSURE REASON AND ITS AUDIT MUST SURVIVE — C47 requires it, and the
//  pipeline branch of the route had to carry these columns across explicitly.
t_eq((string) $rjRow['drop_point'], 'INTERVIEW', 'K5 · the drop POINT was recorded');
t_eq((string) $rjRow['drop_reason'], 'NOT_SUITABLE', 'K6 · the drop REASON was recorded');
t_ok(trim((string) $rjRow['decided_at']) !== '', 'K7 · …and the decision was stamped');
$rjEv = ops_all("SELECT * FROM candidate_events WHERE candidate_id=? ORDER BY id DESC", [$cRj]) ?: [];
t_ok(count($rjEv) >= 1, 'K8 · the closure is on the stage ledger');
t_ok(str_contains((string) ($rjEv[0]['remark'] ?? ''), 'gate 1b route walk'),
    'K9 · …carrying the remark the person typed');

//  An ACCEPTANCE through the same route, for the other half of the vocabulary.
$cAc = $mkCand($rqN, ''); $put($cAc, (int) $offS['id']);
$legacyAc = (string) $row($cAc)['stage'];
$drive($cAc, 'ACCEPTED', ['remark' => 'gate 1b join walk', 'team_role' => 'FIELD']);
$acSt = rpipe_current_state($row($cAc));
t_eq($acSt['kind'], 'terminal', 'K10 · an acceptance lands on the TERMINAL stage, not a closed one');
t_eq($acSt['class'], 'FILLED',  'K11 · …and fills a seat');
t_eq((string) $row($cAc)['stage'], $legacyAc, 'K12 · still no legacy write');
t_eq(reqf_counts($rqN)['filled'], 1, 'K13 · the requirement counts exactly one filled seat');
t_eq(reqf_counts($rqN)['lost'], 1,   'K14 · …and exactly one lost');

//  An EARLY STEP through the route has no single pipeline stage to name, so the
//  legacy column stays the record — and the pipeline position is left alone
//  rather than moved somewhere nobody asked for.
$cSt = $mkCand($rqN, ''); $put($cSt, (int) $ivS['id']);
$ivWas = (int) $row($cSt)['pipeline_stage_id'];
$drive($cSt, 'SHORTLISTED', ['remark' => 'early step']);
t_eq((int) $row($cSt)['pipeline_stage_id'], $ivWas,
    'K15 · an early-step target does NOT move the pipeline to a guessed stage');
t_eq((string) $row($cSt)['stage'], 'SHORTLISTED',
    'K16 · …it is recorded on the legacy column, which is the only place that can hold it');
