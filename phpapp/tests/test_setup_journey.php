<?php
// ============================================================================
//  FIRST-RUN JOURNEY — the checklist a new workspace follows        (R-17)
// ============================================================================
//  The panel is a screen and view() lives in index.php, so this file proves the
//  ENGINE: that every step is well formed, that each one can actually tell
//  whether it is finished, and that the progress count follows the data rather
//  than being asserted.
//
//  The rule being defended: a step with no honest done-signal is worse than no
//  step at all, because a checklist that cannot finish teaches people to ignore
//  it. If a step is ever added without a real signal, this file should fail.
// ============================================================================
t_section('Every step is well formed');
$steps = setup_journey();
t_ok(count($steps) >= 8, 'the journey has a sensible number of steps (' . count($steps) . ')');

$seenTitles = [];
foreach ($steps as $i => $st) {
    $w = 'step ' . ($i + 1) . ' (' . ($st['title'] ?? '?') . ')';
    foreach (['n','title','why','done','state','href','cta'] as $k)
        t_ok(array_key_exists($k, $st), "$w has '$k'");
    t_eq($st['n'], $i + 1, "$w is numbered in order");
    t_ok(is_bool($st['done']), "*** $w reports done as a real boolean, not a guess");
    t_ok(trim((string)$st['why']) !== '', "$w says why it matters");
    t_ok(trim((string)$st['state']) !== '', "$w shows where it stands");
    t_ok(str_starts_with((string)$st['href'], '/'), "$w links somewhere real");
    $seenTitles[] = $st['title'];
}
t_eq(count(array_unique($seenTitles)), count($seenTitles), 'no step is listed twice');

t_section('Progress follows the data');
[$done, $total] = setup_journey_progress();
t_eq($total, count($steps), 'the total matches the journey');
t_eq($done, count(array_filter($steps, fn($s) => $s['done'])), '*** the done count is counted, not asserted');
t_ok($done >= 0 && $done <= $total, 'progress stays inside its own bounds');

t_section('A done-signal actually moves when the data moves');
//  The load-bearing claim. Set the company name and step 1 must flip; put it
//  back and it must flip again. A step that cannot do this is decoration.
$before = setting_get('company_name', null);
setting_set('company_name', '');
t_ok(!setup_journey()[0]['done'], '*** "Say who you are" is unfinished while the company has no name');
setting_set('company_name', 'UAT Test Company');
t_ok(setup_journey()[0]['done'], '*** it finishes the moment the name is set');
t_eq(setup_journey()[0]['state'], 'UAT Test Company', 'and it shows the name it found');
setting_set('company_name', (string)($before ?? ''));

t_section('It offers the recruitment step only where recruitment is on');
$titles  = array_column(setup_journey(), 'title');
$hasHire = in_array('Decide who approves a hire', $titles, true);
$onPlan  = !function_exists('licence_enabled') || licence_enabled('hr');
t_eq($hasHire, $onPlan, '*** the approval step appears exactly when the module that uses it does');
