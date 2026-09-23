<?php
// ============================================================================
//  B6 — FORMS AND PROGRESSIVE DISCLOSURE
//
//  The audit finding this stage was given (F-A5-1) says Job, Test request,
//  Engineer and User "present every field at once — Disclosure: none", and
//  lists Candidate as "disclosed". Measured against the running application,
//  that is BACKWARDS for four of the six forms:
//
//    · Job, Test request and User have had a tab bar, Back/Next and
//      "Step N of M" since commit b963490 (27 Aug), which is an ANCESTOR of
//      the commit that first added the audit — so the audit was wrong when it
//      was written, not merely out of date.
//    · Candidate, listed as "disclosed", had no disclosure at all: 29 of 38
//      controls on one 1436px page.
//    · Engineer is the one target form the finding got right, and only on the
//      ADD screen: its tab panels are all $isEdit-only, so adding a person
//      showed 33 controls in one column while editing one showed 26 across
//      four tabs.
//
//  So B6 builds no new disclosure machinery. The application already owns two,
//  and both are reused exactly as they are:
//    · [data-tabs] / [data-tab]  — the panel engine in app.js (19 views)
//    · details.fold              — the inline collapse in app.css (46 views)
//
//  The rule that matters most here: FOLDING IS DISPLAY ONLY. A field that is
//  folded or on another panel is still in the DOM, still posts its value, and
//  is still checked by the server. Hiding is never a security boundary and
//  never a validation boundary.
// ============================================================================

t_section('B6 — forms and progressive disclosure');

$b6read = function ($rel) { return (string) @file_get_contents(__DIR__ . '/../' . $rel); };
$b6ins  = $b6read('views/ops/inspector_form.php');
$b6cand = $b6read('views/ops/candidate_form.php');
$b6js   = $b6read('assets/js/app.js');

// ---- A · no second engine was built --------------------------------------
foreach (['fd_progressive', 'form_wizard', 'wizard_render', 'disclosure_engine', 'fd_stepper'] as $b6new)
    t_ok(!function_exists($b6new), "A1 · no new form engine called $b6new was introduced");
t_ok(strpos($b6js, 'function initSectionTabs') !== false, 'A2 · the one panel engine is still the only one');
t_eq(substr_count($b6js, 'function initSectionTabs'), 1, 'A3 · defined exactly once');
t_eq(substr_count($b6js, 'function initFormGuard'), 1, 'A4 · the one submit guard is still the only one');
//  details.fold is a stylesheet component, not a script — B6 must not have
//  grown it a JavaScript twin.
t_ok(strpos($b6js, 'function initFolds') === false, 'A5 · no script was added to drive details.fold');

// ---- B · the team-member ADD screen is no longer one long column ----------
t_eq(substr_count($b6ins, '<details class="fold" style='), 4, 'B1 · the team-member form has exactly four folds');
//  Every fold must ship CLOSED. One shipped open is one the person still has
//  to scroll past, and it is the easy mistake to make when editing this file.
t_eq(substr_count($b6ins, '<details class="fold" open'), 0, 'B2 · none of them ships open');
//  The core block stays a plain grid ABOVE the folds, so the Form Designer's
//  own section-matching (which looks for the first .form-grid) is unchanged.
$b6firstFold = strpos($b6ins, '<details class="fold" style=');
$b6firstGrid = strpos($b6ins, '<div class="form-grid">');
t_ok($b6firstGrid !== false && $b6firstFold !== false && $b6firstGrid < $b6firstFold,
     'B3 · the core grid still comes before the first fold');
//  The boxes somebody adding a person MUST see cannot be inside a fold.
$b6core = substr($b6ins, 0, $b6firstFold);
foreach (['first_name', 'email', 'mobile', 'designation', 'staff_kind', 'team_role', 'trade_id', 'status'] as $b6c)
    t_ok(strpos($b6core, 'name="' . $b6c . '"') !== false, "B4 · \"$b6c\" is in the always-visible core");
//  …and the ones that were making it long must be.
$b6folded = substr($b6ins, $b6firstFold);
foreach (['agency_id', 'home_office_id', 'weekly_working_days', 'reports_to_id', 'sbus[]', 'cert_name'] as $b6f)
    t_ok(strpos($b6folded, 'name="' . $b6f . '"') !== false, "B5 · \"$b6f\" moved into a fold");

// ---- C · the candidate form now uses the existing panel engine ------------
t_eq(substr_count($b6cand, '<div data-tabs'), 1, 'C1 · the candidate form declares one panel container');
t_eq(substr_count($b6cand, '<section class="fs-pane" data-tab='), 4, 'C2 · four panels');
//  Save must NOT be folded into the wizard nav: the recommended treatment is
//  "named steps, save available from step one", and this form is marked
//  [data-tabs] WITHOUT .form-tabs precisely so Save stays put.
t_ok(strpos($b6cand, 'class="form-tabs"') === false, 'C3 · not wizard mode, so Save is not hidden until the last panel');
t_ok(strpos($b6cand, 'fs-actions') === false, 'C4 · and its action row is not folded into the panel nav');
$b6save = strrpos($b6cand, '</div>');
t_ok(strpos($b6cand, '</div>') < strpos($b6cand, 'type="submit"'), 'C5 · the Save button sits outside the panels');

// ---- D · nothing was deleted ---------------------------------------------
//  The brief's hardest rule. Every field these two forms asked for before B6
//  must still be asked for. Named here rather than counted, so that replacing
//  one field with another cannot pass.
$b6insFields = ['first_name','middle_name','last_name','emp_code','email','mobile','designation',
    'staff_kind','agency_id','team_role','trade_id','status','home_office_id','weekly_working_days',
    'reports_to_id','salary_ctc','agency_cost','sbus[]','dup_ack','cert_name','cert_number',
    'cert_valid_from','cert_valid_to','cert_file','cert_mandatory','signature','sigfile'];
foreach ($b6insFields as $b6f)
    t_ok(strpos($b6ins, 'name="' . $b6f . '"') !== false, "D1 · team-member form still asks for \"$b6f\"");
$b6candFields = ['requisition_id','allocation_id','first_name','middle_name','last_name','client_id',
    'call_id','proposed_site','group_id','trade_id','skill_id','designation','sbu','source',
    'recruiter_id','own_base_recruiter_id','department','drop_point','drop_reason','agency',
    'experience_years','email','mobile','expected_rate','rate_type','cv_received_date','cv_link',
    'remarks','dup_ack','cv_file','cv_text'];
foreach ($b6candFields as $b6f)
    t_ok(strpos($b6cand, 'name="' . $b6f . '"') !== false, "D2 · candidate form still asks for \"$b6f\"");

// ---- E · the validation contract did not move -----------------------------
//  Required stays required, and nowhere else became required. If disclosure
//  ever silently relaxed a rule this is the assertion that says so.
//  Five, measured on the file as it stood before B6 — the sixth "required"
//  a line count finds is the words "Required for work" in a checkbox label.
t_eq(substr_count($b6ins, ' required'), 5, 'E1 · the team-member form marks the same five controls required');
t_ok(strpos($b6cand, 'name="requisition_id" id="cand_req" required') !== false,
     'E2 · the candidate form still requires the requisition');
t_ok(strpos($b6cand, 'name="first_name" required') !== false, 'E3 · …and the first name');
t_eq(substr_count($b6cand, ' required'), 2, 'E4 · and nothing else was quietly made required');

// ---- F · a hidden box can still be pointed at -----------------------------
//  The submit guard skips a failing box it judges off screen and lets the
//  server refuse instead. With fields now folded away, that had to become
//  "bring it on screen, THEN judge" — otherwise a folded box would be ringed
//  red inside a fold nobody has opened, because a closed <details> still
//  reports a layout box.
t_ok(strpos($b6js, 'function revealField') !== false, 'F1 · the reveal helper exists');
t_ok(preg_match('/function revealField[\s\S]{0,1200}?closest\([\'"]details[\'"]\)/', $b6js) === 1,
     'F2 · …and it opens every <details> above the field');
t_ok(preg_match('/function revealField[\s\S]{0,1600}?\[data-tab\]/', $b6js) === 1,
     'F3 · …and brings the field\'s panel to the front');
t_ok(strpos($b6js, 'revealField(bad[0].el)') !== false,
     'F4 · the box named FIRST in the refusal is the one left on screen');
//  The guard must keep CALLING checkValidity(), because that call is what
//  fires the element's own invalid event, which is what triggers the reveal.
t_ok(strpos($b6js, 'if (!el.checkValidity || el.checkValidity()) return;') !== false,
     'F5 · the guard still calls checkValidity(), which is what fires the reveal');

// ---- G · the three forms B6 did NOT restructure are untouched --------------
//  The audit said these had no disclosure. They have had four to six panels
//  for months. B6 leaves them exactly as they are — this is the guard that
//  says so, and it is what would catch a later stage "tidying" them.
foreach ([['views/ops/job_form.php', 'jobform', 5],
          ['views/ops/call_form.php', 'callform', 6],
          ['views/ops/user_form.php', 'userform', 4]] as [$b6p, $b6k, $b6n]) {
    $b6s = $b6read($b6p);
    t_ok(strpos($b6s, 'data-tabs-key="' . $b6k . '"') !== false, "G1 · $b6p still declares its panel container");
    t_ok(strpos($b6s, 'class="form-tabs"') !== false, "G2 · $b6p is still walked through like a wizard");
    t_eq(substr_count($b6s, '<section class="fs-pane" data-tab='), $b6n, "G3 · $b6p still has $b6n panels");
}

// ---- H · every designable form still applies the company's own settings ----
//  The application already has ONE way to hide a field per company — the Form
//  Designer overlay. B6 must not have become a second one, and must not have
//  knocked the first out of any form.
foreach (['views/ops/job_form.php' => 'job', 'views/ops/call_form.php' => 'call',
          'views/ops/inspector_form.php' => 'inspector', 'views/ops/user_form.php' => 'user',
          'views/ops/candidate_form.php' => 'candidate', 'views/ops/requisition_form.php' => 'requisition'] as $b6p => $b6form) {
    $b6s = $b6read($b6p);
    $b6has = strpos($b6s, 'fd_overlay_html(\'' . $b6form . '\')') !== false
          || strpos($b6s, 'fd_overlay_html("' . $b6form . '")') !== false
          || strpos($b6s, 'fd_extra_fields(\'' . $b6form . '\'') !== false;
    t_ok($b6has, "H1 · $b6p still applies the company's Form Designer settings");
}
