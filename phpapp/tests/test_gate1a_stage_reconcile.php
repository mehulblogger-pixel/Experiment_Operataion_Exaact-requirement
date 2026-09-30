<?php
// ============================================================================
//  GATE 1A — ONE ANSWER TO "WHERE IS THIS CANDIDATE?"
//
//  D1 makes the configurable pipeline authoritative for a candidate's current
//  recruitment position; C14 leaves candidates.stage as history/compatibility.
//  Nine library files read that column today and six production sites write it,
//  so the product can currently give two different answers to one question.
//
//  Gate 1A does NOT move authority — Gate 1B does. Gate 1A builds the single
//  derived answer the readers will be switched onto, and the diagnostic that
//  makes today's disagreements visible BEFORE anything is migrated.
//
//  These tests therefore assert two things above all:
//    1. the derived state never invents a pipeline answer it does not have, and
//    2. the diagnostic changes nothing it looks at.
// ============================================================================

$s = 'G1A-' . random_int(100000, 999999);
$off = (int) ops_val("SELECT COALESCE(MAX(id),0)+1 FROM offices");
db()->prepare("INSERT INTO offices (id,name,city) VALUES (?,?,?)")
    ->execute([$off, $s . ' Office', 'Kolkata']);

$mkReq = function () use ($s, $off) {
    db()->prepare("INSERT INTO requisitions (req_code,designation,office_id,sbu,status,quantity,created_at)
                   VALUES (?,?,?, 'IND','OPEN',5,?)")
        ->execute([$s . '-R' . random_int(1000, 9999), 'Inspector', $off, date('c')]);
    return (int) db()->lastInsertId();
};
$mkCand = function ($stage, $rq = null, $pipe = null, $pstage = null) use ($s) {
    db()->prepare("INSERT INTO candidates (first_name,last_name,stage,sbu,requisition_id,pipeline_id,pipeline_stage_id,created_at)
                   VALUES (?,'G1A',?, 'IND',?,?,?,?)")
        ->execute([$s, $stage, $rq, $pipe, $pstage, date('c')]);
    return (int) db()->lastInsertId();
};
$get = fn($id) => ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
$st  = fn($id) => rpipe_current_state(ops_one("SELECT * FROM candidates WHERE id=?", [$id]));

recruitpipe_migrate();

//  A fingerprint of every candidate stage in the database, taken BEFORE this
//  test calls a single diagnostic. Plain aggregates, so it reads identically on
//  SQLite and MariaDB. LENGTH is in the sum on purpose: it is what catches a
//  "helpful" trim that silently turns ' RECEIVED' into 'RECEIVED'.
$fingerprint = fn() => (int) ops_val("SELECT COUNT(*) FROM candidates")
    . ':' . (int) ops_val("SELECT COALESCE(SUM(LENGTH(stage)),0) FROM candidates")
    . ':' . (int) ops_val("SELECT COALESCE(SUM(COALESCE(pipeline_stage_id,0)),0) FROM candidates");
$pristine = $fingerprint();

// ---------------------------------------------------------------------------
t_section('G1A · A — the legacy value is judged against the real vocabulary');
// ---------------------------------------------------------------------------
t_ok(rpipe_legacy_stage_valid('RECEIVED'),  'A1 · a defined stage is valid');
t_ok(rpipe_legacy_stage_valid('ACCEPTED'),  'A2 · a defined terminal stage is valid');
t_ok(!rpipe_legacy_stage_valid('OFFER'),    'A3 · OFFER is NOT a stage — the key is OFFERED (G0-1, 9 rows)');
t_ok(!rpipe_legacy_stage_valid(' RECEIVED'),'A4 · a leading space makes it a different value (G0-1, 1 row)');
t_ok(!rpipe_legacy_stage_valid(''),         'A5 · empty is not a stage');
t_ok(!rpipe_legacy_stage_valid('received'), 'A6 · comparison is exact, not case-folded');

t_eq(rpipe_legacy_stage_class('ACCEPTED'), 'FILLED', 'A7 · ACCEPTED counts as filled');
t_eq(rpipe_legacy_stage_class('INTERVIEW'), 'ACTIVE', 'A8 · INTERVIEW counts as active');
t_eq(rpipe_legacy_stage_class('REJECTED'), 'LOST',   'A9 · REJECTED counts as lost');
t_eq(rpipe_legacy_stage_class('OFFER'),    '',
    'A10 · THE POINT OF G0-1: a malformed value is in NO classification set, so it is invisible to funnel arithmetic');
t_eq(rpipe_legacy_stage_class(' RECEIVED'), '',
    'A11 · and so is the leading-space value');

// ---------------------------------------------------------------------------
t_section('G1A · B — legacy-only candidates: it says legacy, it does not guess');
// ---------------------------------------------------------------------------
$rq = $mkReq();
$cLegacy = $mkCand('SHORTLISTED', $rq);
$b = $st($cLegacy);
t_eq($b['source'], 'LEGACY_ONLY', 'B1 · no pipeline position, so the source is named LEGACY_ONLY');
t_eq($b['stage_id'], 0,           'B2 · no stage id is invented');
t_eq($b['kind'], '',              'B3 · no stage kind is invented');
t_ok($b['closed'] === null,       'B4 · closed is NULL — unknowable from the authority, not guessed as false');
t_eq($b['legacy_stage'], 'SHORTLISTED', 'B5 · the legacy value travels as DATA');
t_ok($b['legacy_valid'],          'B6 · and is reported valid');
t_ok(!$b['conflict'],             'B7 · nothing to conflict with');

$cNone = $mkCand('', $rq);
$n = $st($cNone);
t_eq($n['source'], 'NONE', 'B8 · neither pipeline nor legacy — it says NONE rather than assuming RECEIVED');
t_ok($n['closed'] === null, 'B9 · and still refuses to answer closed');

// ---------------------------------------------------------------------------
t_section('G1A · C — a real pipeline position is the authority');
// ---------------------------------------------------------------------------
[$pipe, $eff, ] = recruitpipe_cand_state($get($cLegacy));
t_ok(is_array($pipe) && !empty($eff), 'C0 · the fixture resolves a pipeline (precondition)');
$first = $eff[0];
$cPipe = $mkCand('SHORTLISTED', $rq, (int) $pipe['id'], (int) $first['id']);
$c = $st($cPipe);
t_eq($c['source'], 'PIPELINE', 'C1 · with a resolved stage the source is the PIPELINE');
t_eq($c['stage_id'], (int) $first['id'], 'C2 · and it is the stage the candidate actually sits on');
t_eq($c['kind'], (string) $first['kind'], 'C3 · the KIND comes from configuration, never from a name');
t_ok($c['closed'] === false, 'C4 · closed is a real false now — the kind was asked and it is not closed');
t_eq($c['legacy_stage'], 'SHORTLISTED', 'C5 · legacy still travels alongside, still as data');

// ---------------------------------------------------------------------------
t_section('G1A · D — G0-2: pipeline live, legacy closed. SURFACED, NOT SOLVED.');
// ---------------------------------------------------------------------------
$cConf = $mkCand('REJECTED', $rq, (int) $pipe['id'], (int) $first['id']);
$d = $st($cConf);
t_ok($d['conflict'], 'D1 · the disagreement is detected');
t_eq($d['source'], 'PIPELINE', 'D2 · the pipeline is still named as the authority');
t_ok($d['legacy_closed'], 'D3 · and the legacy claim is reported too, not discarded');
t_eq(rpipe_recon_class($get($cConf))['recon_class'], 'E_CONFLICT',
    'D4 · it classifies as E_CONFLICT — for a person to reconcile');

//  THE LOAD-BEARING ASSERTION OF THIS GATE.
$before = $get($cConf);
rpipe_recon_class($get($cConf));
rpipe_current_state($get($cConf));
rpipe_recon_scan(500);
$after = $get($cConf);
t_eq((string) $after['stage'], (string) $before['stage'],
    'D5 · READ-ONLY: after classification AND a full scan the legacy stage is untouched');
t_eq((int) $after['pipeline_stage_id'], (int) $before['pipeline_stage_id'],
    'D6 · READ-ONLY: the pipeline position is untouched');
t_eq((int) $after['pipeline_id'], (int) $before['pipeline_id'],
    'D7 · READ-ONLY: the pipeline is untouched');

// ---------------------------------------------------------------------------
t_section('G1A · E — a malformed legacy value is preserved and marked, never mapped');
// ---------------------------------------------------------------------------
$cBad = $mkCand('OFFER', $rq);
$e = $st($cBad);
t_ok(!$e['legacy_valid'], 'E1 · the value is reported invalid');
t_eq($e['legacy_stage'], 'OFFER', 'E2 · and PRESERVED EXACTLY — not corrected to OFFERED');
t_eq($e['legacy_class'], '', 'E3 · it belongs to no classification set');
t_eq(rpipe_recon_class($get($cBad))['recon_class'], 'F_INVALID_LEGACY',
    'E4 · classified F_INVALID_LEGACY — unmapped, awaiting a decision');
t_ok($e['closed'] === null, 'E5 · and NO outcome is inferred from it (locked rule: never infer)');
rpipe_recon_scan(500);
t_eq((string) $get($cBad)['stage'], 'OFFER', 'E6 · still OFFER after a full scan — nothing was repaired behind our back');

$cSpace = $mkCand(' RECEIVED', $rq);
$sp = $st($cSpace);
t_ok(!$sp['legacy_valid'], 'E7 · the leading-space value is invalid too');
t_eq($sp['legacy_stage'], ' RECEIVED', 'E8 · preserved byte for byte, space included');
t_ok($sp['legacy_reader_split'],
    'E9 · G1A-1: nextaction.php trims+uppercases this column and reqfulfil does not, so the readers disagree — flagged');
t_eq((string) $get($cSpace)['stage'], ' RECEIVED', 'E10 · and untouched');
//  Scanned HERE, after this row exists — the scan above ran before it was
//  created and so could not have said anything about it. A diagnostic that
//  normalised as it counted would strip the space on this line.
rpipe_recon_scan(500);
t_eq((string) $get($cSpace)['stage'], ' RECEIVED',
    'E11 · STILL byte-identical after a scan that DID see this row — no normalisation on read');
t_eq(strlen((string) $get($cSpace)['stage']), 9,
    'E12 · nine characters, the leading space among them');
t_eq((string) $get($cBad)['stage'], 'OFFER',
    'E13 · and the OFFER row was not quietly promoted to OFFERED');

// ---------------------------------------------------------------------------
t_section('G1A · F — the pipeline fallback must not fake a position');
// ---------------------------------------------------------------------------
//  recruitpipe_cand_state() falls back to recruitpipe_for($req) when the locked
//  pipeline is inactive. The candidate's stage id then belongs to a DIFFERENT
//  pipeline, is not found in the effective list, and $idx stays 0 — which would
//  read as "sitting on stage one of a pipeline they were never on".
db()->prepare("INSERT INTO recruit_pipelines (code,name,is_default,active,sort,created_at) VALUES (?,?,0,0,0,?)")
    ->execute([$s . '-RET', $s . ' Retired', date('c')]);
$deadPipe = (int) db()->lastInsertId();
db()->prepare("INSERT INTO recruit_stages (pipeline_id,name,stage_key,kind,seq,active) VALUES (?,?,?,?,1,1)")
    ->execute([$deadPipe, 'Ghost', 'GHOST', 'step']);
$ghost = (int) db()->lastInsertId();
$cGhost = $mkCand('SHORTLISTED', $rq, $deadPipe, $ghost);
$g = $st($cGhost);
t_ok($g['source'] !== 'PIPELINE',
    'F1 · an inactive pipeline does NOT yield a pipeline answer — the stage id must match where they really are');
t_eq($g['stage_id'], 0, 'F2 · no borrowed stage id from the fallback pipeline');
t_ok($g['closed'] === null, 'F3 · and no closed answer from a position that was never theirs');

//  The retired pipeline is removed the moment it has served its purpose:
//  test_recruit_pipeline.php counts recruit_pipelines GLOBALLY, so a fixture
//  left lying about here fails an unrelated test in a different file. The
//  candidate keeps its dangling pipeline_id on purpose — that is now an even
//  better specimen of "a pipeline position that cannot be resolved".
db()->prepare("DELETE FROM recruit_stages WHERE pipeline_id=?")->execute([$deadPipe]);
db()->prepare("DELETE FROM recruit_pipelines WHERE id=?")->execute([$deadPipe]);
$g2 = $st($cGhost);
t_ok($g2['source'] !== 'PIPELINE',
    'F4 · and with the pipeline gone entirely it STILL refuses to invent a position');
t_eq($g2['stage_id'], 0, 'F5 · no stage id from a pipeline that no longer exists');

// ---------------------------------------------------------------------------
t_section('G1A · G — the diagnostic reports, and only reports');
// ---------------------------------------------------------------------------
$scan = rpipe_recon_scan(500);
t_ok(isset($scan['counts'], $scan['rows'], $scan['total']), 'G1 · the scan returns counts, rows and a total');
t_ok($scan['total'] >= 6, 'G2 · it saw at least the six candidates this test created');
t_ok($scan['counts']['E_CONFLICT'] >= 1, 'G3 · the conflict is counted');
t_ok($scan['counts']['F_INVALID_LEGACY'] >= 2, 'G4 · both malformed values are counted');
t_ok($scan['counts']['C_LEGACY_MAPPABLE'] >= 1, 'G5 · the mappable legacy-only candidate is counted');
t_ok($scan['counts']['B_PIPELINE_OK'] >= 1, 'G6 · the healthy pipeline candidate is counted');

$ids = array_column($scan['rows'], 'candidate_id');
t_ok(in_array($cConf, $ids, true), 'G7 · the conflict is named, so a person can act on it');
t_ok(in_array($cBad, $ids, true), 'G8 · so is the malformed row');
t_ok(!in_array($cPipe, $ids, true), 'G9 · the healthy candidate is a count, not a row — the report stays readable');
foreach (array_keys($scan['counts']) as $k)
    t_ok(array_key_exists($k, RPIPE_RECON_CLASSES), 'G10 · every counted class is a declared class: ' . $k);

//  THE GATE PROMISE, measured over the WHOLE test rather than over two adjacent
//  lines. $pristine was taken before the first diagnostic call; every candidate
//  this test added since is accounted for by adding their stage lengths to it.
//  Measuring only from here to the next line would compare damage with damage:
//  a scan that repaired rows would already have repaired them, twice over.
//  EVERY column the diagnostic could be tempted to write is accounted for, not
//  just the legacy stage: a scan that "helpfully" mapped a legacy-only candidate
//  onto a pipeline stage would be a migration hiding inside a report.
$mine = [$cLegacy, $cNone, $cPipe, $cConf, $cBad, $cSpace, $cGhost];
$addLen = 0; $addStage = 0; $addPipe = 0;
foreach ($mine as $cid) {
    $r = $get($cid);
    $addLen   += strlen((string) $r['stage']);
    $addStage += (int) ($r['pipeline_stage_id'] ?? 0);
    $addPipe  += (int) ($r['pipeline_id'] ?? 0);
}
[$pCount, $pLen, $pStage] = array_map('intval', explode(':', $pristine));
t_eq((int) ops_val("SELECT COALESCE(SUM(LENGTH(stage)),0) FROM candidates"), $pLen + $addLen,
    'G11 · THE GATE PROMISE: across every scan in this test, not one stage byte changed anywhere in the database');
t_eq((int) ops_val("SELECT COALESCE(SUM(COALESCE(pipeline_stage_id,0)),0) FROM candidates"), $pStage + $addStage,
    'G11a · and no candidate was moved ONTO a pipeline stage by a diagnostic — a report never migrates');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates"), $pCount + count($mine),
    'G11c · and no candidate row was created or removed');

//  Each of this test's own rows re-checked individually, so a compensating pair
//  of writes cannot net out to a clean total.
foreach ([[$cLegacy, 'SHORTLISTED'], [$cNone, ''], [$cConf, 'REJECTED'],
          [$cBad, 'OFFER'], [$cSpace, ' RECEIVED']] as [$cid, $want])
    t_eq((string) $get($cid)['stage'], $want, 'G11d · candidate ' . $cid . ' still reads exactly ' . var_export($want, true));
t_eq((int) $get($cLegacy)['pipeline_stage_id'], 0,
    'G11e · the legacy-only candidate was NOT quietly given a pipeline stage');
t_eq((int) $get($cNone)['pipeline_stage_id'], 0,
    'G11f · nor was the candidate with no stage at all');

$sumBefore = $fingerprint();
rpipe_recon_scan(500);
rpipe_recon_scan(1);
$sumAfter = $fingerprint();
t_eq($sumAfter, $sumBefore, 'G11b · and two further scans change nothing either');

$one = rpipe_recon_scan(1);
t_ok(count($one['rows']) <= 1, 'G12 · the row limit is honoured');
t_ok($one['total'] >= 6, 'G13 · but the COUNTS still cover everyone — a limit truncates the list, never the arithmetic');

// ---------------------------------------------------------------------------
t_section('G1A · H — it answers for an id as well as a row, and refuses nonsense');
// ---------------------------------------------------------------------------
t_eq(rpipe_current_state($cPipe)['source'], 'PIPELINE', 'H1 · an integer id is accepted');
t_ok(rpipe_current_state(999999999) === null, 'H2 · an unknown candidate returns null, not a fabricated state');
t_ok(rpipe_current_state([]) === null, 'H3 · an empty row returns null');
t_ok(rpipe_current_state(['stage' => 'ACCEPTED']) === null, 'H4 · a row with no id returns null');
