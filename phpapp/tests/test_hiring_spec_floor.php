<?php
// ============================================================================
//  R-23 — THE REQUIREMENT THE FORM NEVER ASKED FOR
//
//  The owner's finding, in their words: an approver approves a hiring request
//  with no stated requirement. It was not a design question. Three columns —
//  min_qualification, min_experience_years, essential_skills — had existed since
//  Gate 2; hreq_save() read them from the POST; the approved snapshot carried
//  them; rver_floor_breaches() measured a requisition against them; and
//  crev_evidence() showed a candidate against them. The form simply never put
//  them on the screen, so every save wrote them empty, and every protection
//  built on top of the floor was guarding nothing at all.
//
//  Two further defects fell out of fixing it, and both are locked down here:
//
//    · hreq_to_requisition() carried the approved BUDGET across but not the
//      approved SPECIFICATION. Since an absent value is the strongest possible
//      weakening, every requisition raised from a request that stated a minimum
//      breached its own parent's floor from the moment it was created.
//    · nothing ever CREATED the 'qualification_level' master list. rver_ensure_
//      vocab() only topped up a list it assumed somebody else had made, so the
//      ranking was never editable and a screen offering to take you there 404'd.
//
//  These tests are deliberately written against behaviour — a saved row, a
//  raised requisition, a breach verdict — and only two of them read a view file,
//  because the harness cannot render a view and "the form asks the question" is
//  the one claim that has no behavioural proxy.
// ============================================================================

t_section('R-23 — the core person specification');

$pdo = db();
hreq_migrate(); rver_migrate();
$keep = ['h' => [], 'r' => [], 'u' => [], 'o' => []];
$origSess = $_SESSION;

try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute([961, 'R23 Branch']); $keep['o'][] = 961; } catch (Throwable $e) {}
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices) VALUES (?,?,?,1,1,?,'')")
    ->execute(['r23mgr', 'R23', 'MANAGER', 961]);
$uid = (int) $pdo->lastInsertId(); $keep['u'][] = $uid;
$_SESSION['uid'] = $uid; current_user(true); ua(true);

$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uid, 'requested_by_name' => 'R23 Manager',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'R23 Welding Inspector', 'designation' => 'ENGINEER',
    'job_description' => 'Weld inspection on the R23 project.',
    'quantity' => 4, 'office_id' => 961, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'HIGH',
    'reason' => 'New contract awarded.',
], $x);

// ---------------------------------------------------------------------------
//  1. THE FORM ASKS. This is the whole defect, and it lives in a view the test
//     harness cannot render — so the source is read for the three field names.
//     A name-level check is enough: if the input is not on the form under the
//     name the handler reads, the value can never arrive, which is precisely
//     the state this test exists to prevent returning to.
// ---------------------------------------------------------------------------
$form = (string) @file_get_contents(__DIR__ . '/../views/ops/hiring_request.php');
t_ok($form !== '', 'the hiring request view is readable');
foreach (['min_qualification', 'min_experience_years', 'essential_skills'] as $f)
    t_ok(strpos($form, 'name="' . $f . '"') !== false,
         'the form asks for ' . $f . ' under the name the handler reads');
//  …and the approver's read-only summary describes it, through the one reader.
t_ok(substr_count($form, 'rver_spec_summary') >= 1,
     'the read-only summary an approver lands on describes the requirement');
$reqView = (string) @file_get_contents(__DIR__ . '/../views/ops/requisition_detail.php');
t_ok(strpos($reqView, 'rver_spec_summary') !== false,
     'and so does the requisition a recruiter works from');

// ---------------------------------------------------------------------------
//  2. IT ROUND-TRIPS. Saved, and read back as saved.
// ---------------------------------------------------------------------------
[$ok, $msg, $h1] = hreq_save(0, $base([
    'min_qualification'    => 'diploma',       // lower case on purpose
    'min_experience_years' => '5.5',
    'essential_skills'     => '  CSWIP 3.1, offshore experience  ',
]));
t_ok($ok, 'a request states its requirement: ' . $msg);
if ($ok) $keep['h'][] = $h1;
$row = hreq_get($h1);
t_eq((string) $row['min_qualification'], 'DIPLOMA', 'the qualification is stored as its code, upper case');
t_eq((float) $row['min_experience_years'], 5.5, 'half-years survive the decimal column');
t_eq((string) $row['essential_skills'], 'CSWIP 3.1, offshore experience', 'the skills are trimmed, not padded');

// ---------------------------------------------------------------------------
//  3. A VALUE THE COMPARISON CANNOT READ IS REFUSED, NOT ABSORBED.
//
//     rver_qual_rank() answers -1 for a code it does not know, and -1 reads as
//     "no minimum stated" — so a typo would silently switch the floor off. That
//     is a worse outcome than a rejected save, which is why it is refused here.
// ---------------------------------------------------------------------------
[$bad, $badMsg] = hreq_save(0, $base(['min_qualification' => 'MASTERS_OF_THE_UNIVERSE']));
t_ok(!$bad, 'a qualification outside the workspace vocabulary is refused: ' . $badMsg);
[$neg] = hreq_save(0, $base(['min_experience_years' => '-3']));
t_ok(!$neg, 'a negative experience floor is refused');
[$big] = hreq_save(0, $base(['min_experience_years' => '9999']));
t_ok(!$big, 'an experience floor the DECIMAL(5,2) column would truncate is refused');
[$blankOk, , $hBlank] = hreq_save(0, $base(['min_qualification' => '', 'min_experience_years' => '']));
t_ok($blankOk, 'stating no minimum is still allowed — an open requirement is legitimate');
if ($blankOk) {
    $keep['h'][] = $hBlank;
    t_ok(hreq_get($hBlank)['min_experience_years'] === null,
         'an unstated experience floor is NULL, never zero — zero would be a floor');
}

// ---------------------------------------------------------------------------
//  4. IT IS DESCRIBED THE SAME WAY EVERYWHERE, INCLUDING WHEN IT IS ABSENT.
// ---------------------------------------------------------------------------
$sum = rver_spec_summary(hreq_get($h1));
t_eq(count($sum), 3, 'the summary covers every floor field, and only those');
t_eq($sum['min_qualification']['value'], 'Diploma', 'the code is shown as its label, never the code');
t_eq($sum['min_experience_years']['value'], '5.5 years', 'the experience reads as years');
t_eq($sum['min_experience_years']['stated'], true, 'a stated floor is marked stated');
$sumBlank = rver_spec_summary(hreq_get($hBlank));
t_eq($sumBlank['min_experience_years']['stated'], false, 'an absent floor is returned, marked unstated');
t_eq($sumBlank['min_experience_years']['value'], '', '…with no value invented for it');
t_ok(!rver_spec_stated(hreq_get($hBlank)), 'a request that states nothing is recognised as stating nothing');
t_ok(rver_spec_stated(hreq_get($h1)), 'and one that states something is not');
//  One year reads as one year, not "1 years".
[$oneOk, , $hOne] = hreq_save(0, $base(['min_experience_years' => '1']));
if ($oneOk) { $keep['h'][] = $hOne;
    t_eq(rver_spec_summary(hreq_get($hOne))['min_experience_years']['value'], '1 year', 'one year is singular'); }

// ---------------------------------------------------------------------------
//  5. THE REQUISITION INHERITS THE APPROVED FLOOR — AND DOES NOT BREACH IT.
//
//     This is the defect that mattered most: the floor only works if the thing
//     being measured actually carries it.
// ---------------------------------------------------------------------------
hreq_submit($h1);
hreq_decide($h1, true, 'Approved with the stated minimum.');
t_eq(hreq_get($h1)['status'], 'APPROVED', 'the request with a stated minimum is approved');
[$rOk, $rMsg, $rq] = hreq_to_requisition($h1, 2);
t_ok($rOk, 'a requisition is raised from it: ' . $rMsg);
if ($rOk) {
    $keep['r'][] = $rq;
    $rr = ops_one("SELECT * FROM requisitions WHERE id=?", [$rq]);
    t_eq((string) $rr['min_qualification'], 'DIPLOMA', 'the approved qualification floor travels across');
    t_eq((float) $rr['min_experience_years'], 5.5, 'the approved experience floor travels across');
    t_eq((string) $rr['essential_skills'], 'CSWIP 3.1, offshore experience', 'the approved skills travel across');
    //  The verdict, not just the columns: an inherited floor is not a breach.
    t_eq(rver_floor_breaches($rr, []), [], 'a freshly raised requisition does not breach its own parent floor');
    //  And the protection still bites when somebody genuinely weakens it.
    $weaker = rver_floor_breaches($rr, ['min_experience_years' => 2,
                                        'min_qualification'    => 'SCHOOL',
                                        'essential_skills'     => 'offshore experience']);
    t_eq(count($weaker), 3, 'dropping below every floor is caught on all three');
    t_ok(isset($weaker['essential_skills']['missing']) && in_array('cswip 3.1', $weaker['essential_skills']['missing'], true),
         '…and the dropped skill is named');
    //  Asking for MORE is never a breach.
    t_eq(rver_floor_breaches($rr, ['min_experience_years' => 8, 'min_qualification' => 'DEGREE']), [],
         'asking for more than the floor is not a breach');
    //  Dropping the requirement entirely is the strongest weakening there is.
    t_ok(isset(rver_floor_breaches($rr, ['min_experience_years' => null])['min_experience_years']),
         'removing the experience floor altogether is a breach');
}
//  A request that stated nothing imposes no floor — an open requirement must not
//  become an accidental one.
hreq_submit($hBlank); hreq_decide($hBlank, true, 'Open requirement, approved.');
[$bOk, , $rqB] = hreq_to_requisition($hBlank, 1);
if ($bOk) {
    $keep['r'][] = $rqB;
    $rrB = ops_one("SELECT * FROM requisitions WHERE id=?", [$rqB]);
    t_ok($rrB['min_experience_years'] === null, 'no floor stated, no floor inherited');
    t_eq(rver_floor_breaches($rrB, ['min_experience_years' => 0]), [],
         'and nothing can breach a floor that was never set');
}

// ---------------------------------------------------------------------------
//  6. THE VOCABULARY IS A REAL, EDITABLE MASTER LIST.
//
//     The ORDER of this list is the ranking, which is why it must be the same
//     list the form offers and the comparison reads.
// ---------------------------------------------------------------------------
t_ok(function_exists('rver_qual_options'), 'there is one reader for the qualification list');
$opts = rver_qual_options();
t_ok(isset($opts['DIPLOMA']) && isset($opts['DEGREE']), 'the shipped codes the comparison relies on are present');
t_ok(rver_qual_rank('DEGREE') > rver_qual_rank('DIPLOMA'), 'the list order IS the ranking');
t_eq(rver_qual_rank('NOT_A_LEVEL'), -1, 'an unknown code ranks as unspecified');
$registered = false;
foreach (lk_module_lists() as [$k, , , ]) if ($k === 'qualification_level') $registered = true;
t_ok($registered, 'the qualification list is registered as an editable master, so Masters can reach it');
t_ok(lk_type('qualification_level') !== null, '…and the list really exists in this workspace');

// ---------------------------------------------------------------------------
//  6b. AND IT REACHES AN INSTALL THAT IS ONLY UPGRADED.
//
//  Registering a list creates it on a fresh install and on any install whose
//  boot() runs. A live workspace that is merely upgraded never re-seeds:
//  index.php finds no missing table or column and runs migrate_all(), which is
//  DDL only. The list silently never arrived — absent from Masters, not
//  editable, and a 404 from the screen that linked to it. Proven in a browser
//  against a database dumped before the change, which is the only place this
//  could be seen at all.
// ---------------------------------------------------------------------------
t_ok(function_exists('lk_lists_pending'), 'the upgrade gap is asked about, not assumed');
t_ok(!lk_lists_pending(), 'a workspace that has every registered list reports nothing pending');
//  THE LOOP THIS MUST NOT CAUSE. index.php acts on this by throwing, which runs
//  boot() once. If the check could answer true for a list boot() will not create,
//  every single request would re-boot for ever. The two cases where boot()
//  declines are a pre-seed install and a module the plan excludes.
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    //  (a) the list is genuinely missing → pending, so the upgrade actually runs.
    $qt = lk_type('qualification_level');
    $pdo->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([(int) $qt['id']]);
    $pdo->prepare("DELETE FROM lookup_types WHERE id=?")->execute([(int) $qt['id']]);
    lk_types(true);
    t_ok(lk_lists_pending(), 'a missing registered list IS reported as a pending upgrade');
    //  (b) before any list exists this is a fresh install, which lk_seed() owns —
    //      answering "pending" here is what would spin for ever.
    $pdo->exec("DELETE FROM lookup_values");
    $pdo->exec("DELETE FROM lookup_types");
    lk_types(true);
    t_ok(!lk_lists_pending(), 'a pre-seed install is NOT reported as pending — this is the loop guard');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    lk_types(true);
}
t_ok(lk_type('qualification_level') !== null, 'the rollback put the workspace back as it was');
t_ok(!lk_lists_pending(), '…and nothing is pending again');
//  The probe is wired into the one place that acts on it.
$boot = (string) @file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($boot, 'lk_lists_pending') !== false, 'the boot probe actually calls it');

// ---------------------------------------------------------------------------
//  7. A CHANGE TO AN APPROVED REQUIREMENT IS STILL A MATERIAL CHANGE.
//     The three fields were already on the material list; adding the inputs
//     must not have taken them off it.
// ---------------------------------------------------------------------------
//  The list is a map of field => why it matters, so it is read by key.
foreach (['HIRING_REQUEST' => 'request', 'REQUISITION' => 'requisition'] as $ent => $word) {
    $mat = rver_material_fields($ent);
    foreach (['min_qualification', 'min_experience_years', 'essential_skills'] as $f)
        t_ok(array_key_exists($f, $mat),
             $f . ' is still a material change on an approved ' . $word);
}

// ---------------------------------------------------------------------------
//  Clean up — this suite shares a database with every other one.
// ---------------------------------------------------------------------------
foreach ($keep['r'] as $i) { try { $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$i]); } catch (Throwable $e) {} }
foreach ($keep['h'] as $i) { try { $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([$i]); } catch (Throwable $e) {} }
foreach ($keep['u'] as $i) { try { $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$i]); } catch (Throwable $e) {} }
foreach ($keep['o'] as $i) { try { $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$i]); } catch (Throwable $e) {} }
$_SESSION = $origSess; current_user(true); ua(true);
