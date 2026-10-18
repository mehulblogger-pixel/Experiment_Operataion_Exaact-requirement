<?php
// ---------------------------------------------------------------------------
//  UAT A7 — "Add candidate" saved from step 1 of 4.
//
//    "Clicking on Add Candidate it directly adds the candidate even when the
//     information is either complete or incorrect or not reviewed by the
//     recuirter. So this button shall only popup in last tab and activated when
//     minimum required information is completed."
//
//  The form opted into the engine's permissive mode (data-tabs-save="always"),
//  which keeps Save reachable from every panel. That was a deliberate choice —
//  a recruiter loading forty CVs should not walk four panels each time — and it
//  printed a line under the button saying what saving early left undone. It did
//  not survive contact: two green buttons side by side, one of which quietly
//  ends the task, is a trap however well the small print explains it.
// ---------------------------------------------------------------------------
t_section('A7 — the candidate wizard saves at the end, not from step one');

$v = (string) file_get_contents(__DIR__ . '/../views/ops/candidate_form.php');
t_ok(strpos($v, 'class="form-tabs"') !== false, 'the add-candidate form is still a wizard');
t_ok(strpos($v, 'data-tabs-save="always"') === false,
    '*** and no longer keeps Save reachable from every panel');
t_ok(strpos($v, 'data-tabs-key="candform"') !== false, 'it keeps its own panel key');
//  The reasoning for the change is recorded where the next person will look.
t_ok(stripos($v, 'UAT A7') !== false,
    'and the view says why the permissive mode was turned off');

//  THE ENGINE'S STRICT MODE is what now governs it, and that rule is one line.
$js = (string) file_get_contents(__DIR__ . '/../assets/js/app.js');
t_ok(strpos($js, "actions.style.display = (last || saveAlways) ? '' : 'none'") !== false,
    '*** Save is shown on the last panel, or when a form opts into save-always');

//  VALIDATION HAS TO KNOW ABOUT STEPS, or the fix above creates a worse bug:
//  a required field on a hidden panel cannot be reported by the browser (Chrome
//  refuses to focus it), so the form silently will not submit and the button
//  reads as dead.
t_ok(strpos($js, 'var firstInvalid = function (scope)') !== false,
    'the wizard can find the first unanswered required field');
t_ok(strpos($js, 'var panelOf = function (el)') !== false,
    'and which panel that field is on');
t_ok(strpos($js, 'if (p >= 0 && p !== curIdx) goto(p)') !== false,
    '*** so an invalid field OPENS its panel instead of failing silently');
t_ok(strpos($js, 'reportValidity') !== false,
    'and the browser is then allowed to say which answer is missing');
t_ok(preg_match('/nextBtn\.addEventListener\(\'click\'.{0,400}firstInvalid/s', $js) === 1,
    '*** Next checks the panel being left, so a gap is caught where it is asked');

//  Balanced braces — a crude guard, but this file is loaded by every screen and
//  a syntax error in it silently kills every piece of behaviour on the site.
t_eq(substr_count($js, '{'), substr_count($js, '}'), 'app.js braces balance');
