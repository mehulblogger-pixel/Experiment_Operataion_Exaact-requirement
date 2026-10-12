<?php
// ============================================================================
//  ONE REQUIREMENT PER REQUISITION                                      (R-28)
//
//  A requisition held its requirement TWICE:
//
//    free text, unprotected   qualification · skills · experience_min
//    Gate-2, change-controlled min_qualification · essential_skills ·
//                             min_experience_years
//
//  Only the second is inherited from the approved hiring request, only the
//  second needs re-approval when it changes, and only the second is read by the
//  candidate review. The FORM wrote the first. Two consequences, both measured:
//
//    · the floor a manager approved was never what a recruiter edited, so the
//      floor comparison had nothing of theirs to bite on;
//    · req_fit() scored a candidate on the free-text set while crev_evidence()
//      scored the same candidate on the controlled set — two screens, one
//      person, two different requirements.
//
//  And underneath it, two ranked qualification LADDERS: the requisition offered
//  the NSQF taxonomy while the floor ranked against a different list, so the
//  comparison could not read what a recruiter picked even in principle.
//
//  Nothing is deleted. The legacy columns keep their values as historical data,
//  exactly the posture the pipeline took with candidates.stage.
// ============================================================================

t_section('R-28 — one requirement, read by everybody');

$pdo = db();
rver_migrate();

// ---------------------------------------------------------------------------
//  1. THE FORM SAVES THE CONTROLLED COLUMNS.
// ---------------------------------------------------------------------------
$saved = req_extra_fields();
foreach (['min_qualification', 'min_experience_years', 'essential_skills'] as $c)
    t_ok(in_array($c, $saved, true), "*** the requisition form saves $c — the controlled column");
foreach (['qualification', 'skills', 'experience_min'] as $c)
    t_ok(!in_array($c, $saved, true), "*** …and no longer writes the unprotected twin $c");
//  relevant_experience has no controlled twin and is deliberately kept.
t_ok(in_array('relevant_experience', $saved, true),
     'relevant_experience is kept — it has no twin and means something else');

// ---------------------------------------------------------------------------
//  2. THE FORM OFFERS THE SAME LADDER THE FLOOR RANKS AGAINST.
// ---------------------------------------------------------------------------
$form = (string) @file_get_contents(__DIR__ . '/../views/ops/requisition_form.php');
t_ok(strpos($form, 'rver_qual_options') !== false,
     '*** the requisition offers the ladder the floor comparison uses');
t_ok(strpos($form, 'name="min_qualification"') !== false, '*** …and writes the controlled column');
t_ok(strpos($form, 'name="essential_skills"') !== false, '*** the skills picker writes the controlled column');
t_ok(strpos($form, 'name="min_experience_years"') !== false, '*** and so does the experience field');
//  Every code the form can offer must be one the comparison can rank, or the
//  floor switches off silently — the failure R-23 was built to prevent.
foreach (array_keys(rver_qual_options()) as $code)
    t_ok(rver_qual_rank($code) >= 0, "*** every offered qualification ranks: $code");

// ---------------------------------------------------------------------------
//  3. BOTH SCORERS NOW READ THE SAME FACT.
// ---------------------------------------------------------------------------
$lib = (string) @file_get_contents(__DIR__ . '/../lib/recruit.php');
$code = (string) preg_replace('~^\s*//.*$~m', '', (string) preg_replace('~/\*.*?\*/~s', '', $lib));
t_ok(strpos($code, "min_experience_years") !== false, '*** the fit score reads the controlled experience');
t_ok(strpos($code, "essential_skills") !== false,      '*** …and the controlled skills');
//  …with the old column kept as a fallback, so an un-migrated row still scores.
t_ok(strpos($code, "experience_min") !== false, '…and still falls back to the old column for an older record');

// ---------------------------------------------------------------------------
//  4. THE BACK-FILL IS CONSERVATIVE: FILLS A BLANK, NEVER OVERWRITES.
// ---------------------------------------------------------------------------
t_ok(function_exists('rver_backfill_spec'), 'the back-fill exists');
$mk = function ($cols) use ($pdo) {
    $k = array_keys($cols); $q = implode(',', array_fill(0, count($k), '?'));
    $pdo->prepare("INSERT INTO requisitions (req_code," . implode(',', $k) . ") VALUES (?,$q)")
        ->execute(array_merge(['R28-' . bin2hex(random_bytes(3))], array_values($cols)));
    return (int) $pdo->lastInsertId();
};
$keep = [];
//  (a) an old record with only the legacy values
$a = $mk(['experience_min' => 7, 'skills' => 'CSWIP 3.1', 'qualification' => 'DIPLOMA']); $keep[] = $a;
//  (b) a record that already carries an APPROVED floor — must not be touched
$b = $mk(['experience_min' => 2, 'skills' => 'Nothing much', 'qualification' => 'SCHOOL',
          'min_experience_years' => 9, 'essential_skills' => 'Approved skills', 'min_qualification' => 'DEGREE']); $keep[] = $b;
//  (c) an old qualification the ladder cannot rank — must be left alone
$c = $mk(['qualification' => 'B.E. Mechanical']); $keep[] = $c;

rver_backfill_spec();
$row = fn($id) => ops_one("SELECT * FROM requisitions WHERE id=?", [$id]);
$ra = $row($a);
t_eq((float) $ra['min_experience_years'], 7.0, '*** an empty controlled field is filled from the old one');
t_eq((string) $ra['essential_skills'], 'CSWIP 3.1', '*** …including the skills');
t_eq((string) $ra['min_qualification'], 'DIPLOMA', '*** …and a qualification the ladder can rank');
t_eq((string) $ra['experience_min'], '7', '*** the old column is NOT cleared — it stays as the historical record');

$rb = $row($b);
t_eq((float) $rb['min_experience_years'], 9.0, '*** an approved floor is NEVER overwritten by an older value');
t_eq((string) $rb['essential_skills'], 'Approved skills', '*** …nor are the approved skills');
t_eq((string) $rb['min_qualification'], 'DEGREE', '*** …nor the approved qualification');

$rc = $row($c);
t_eq((string) $rc['min_qualification'], '', '*** a qualification the comparison cannot rank is LEFT BLANK, not guessed');
t_ok(rver_qual_rank($rc['qualification']) < 0, '…because ranking it would answer -1, which silently disables the floor');

//  Running it twice changes nothing more.
rver_backfill_spec();
t_eq((float) $row($b)['min_experience_years'], 9.0, 'the back-fill is idempotent');

foreach ($keep as $id) { try { $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }

// ---------------------------------------------------------------------------
//  5. THE SCREEN PRINTS THE REQUIREMENT ONCE.
// ---------------------------------------------------------------------------
$detail = (string) @file_get_contents(__DIR__ . '/../views/ops/requisition_detail.php');
t_ok(strpos($detail, 'rver_spec_summary') !== false, 'the controlled requirement is printed');
t_ok(strpos($detail, 'older record') !== false,
     '*** an un-migrated legacy value is shown as the OLDER record, not as a second live requirement');
