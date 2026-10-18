<?php
// ---------------------------------------------------------------------------
//  UAT A1 — "FOR HOW LONG" HAS NO ANSWER FOR A PERMANENT ROLE.
//
//    "When the employee is considered as permanant the period shall not be
//     required and yearly costing shall be considered. When it is fixed term
//     contract then months or something has to be entered."
//
//  The form asked everybody the same question, so a permanent hire was either
//  left blank — and the approver was shown ONE MONTH of salary as the cost of
//  hiring somebody for ever — or given an invented number, which showed a
//  commitment nobody intends to end.
// ---------------------------------------------------------------------------
t_section('A1 — a permanent role is costed as an annual run-rate');

t_ok(function_exists('hreq_is_openended'), 'the open-ended employment types are named in one place');
t_ok(hreq_is_openended('PERMANENT'), 'permanent is open-ended');
foreach (['CONTRACT', 'TEMPORARY', 'PART_TIME', 'CONSULTANT', 'INTERN', ''] as $t)
    t_ok(!hreq_is_openended($t), "'$t' is not open-ended, so it is still asked for a duration");

$base = ['est_cost_per_person' => 75000, 'est_cost_basis' => 'MONTHLY', 'quantity' => 2];

// ---- fixed term: what was asked for is what is used ----------------------
$fixed = hreq_commitment($base + ['employment_type' => 'CONTRACT', 'est_duration_months' => 6]);
t_eq((float) $fixed['total'], 900000.0, 'a 6-month contract for 2 people at 75,000 is 9,00,000');
t_ok(empty($fixed['open_ended']), 'and is not flagged open-ended');

// ---- permanent: twelve months, whatever the form holds -------------------
$perm = hreq_commitment($base + ['employment_type' => 'PERMANENT', 'est_duration_months' => 6]);
t_eq((float) $perm['total'], 1800000.0,
    '*** a PERMANENT role is costed over 12 months — 18,00,000 — even though the row says 6');
t_ok(!empty($perm['open_ended']), '*** and is flagged open-ended, so screens can say "a year"');
t_ok(strpos((string) $perm['period_note'], 'annual run-rate') !== false,
    '*** with wording that stops the figure being read as the whole liability');

//  COMPUTED, NOT TRUSTED. A duration saved before the type was changed, or a
//  crafted post, must not be able to show the approver a different number from
//  the one the screen showed.
$stale = hreq_commitment($base + ['employment_type' => 'PERMANENT', 'est_duration_months' => 240]);
t_eq((float) $stale['total'], 1800000.0,
    '*** a stale or crafted duration cannot inflate a permanent request');

// ---- a permanent role with no duration is NOT "incomplete" ---------------
$blank = hreq_commitment($base + ['employment_type' => 'PERMANENT']);
t_eq((float) $blank['total'], 1800000.0, 'a blank duration on a permanent role still costs a year');
t_ok(empty($blank['partial']),
    '*** and is not flagged as missing an answer — twelve months is the definition, not a gap');
//  The same blank on a fixed-term request IS incomplete, and must still say so.
$blankFixed = hreq_commitment($base + ['employment_type' => 'CONTRACT']);
t_ok(!empty($blankFixed['partial']),
    '*** while a fixed-term request with no duration is still called out');

// ---- the form stops asking -----------------------------------------------
$v = (string) file_get_contents(__DIR__ . '/../views/ops/hiring_request.php');
t_ok(strpos($v, 'id="hq_months_wrap"') !== false, 'the duration field can be hidden');
t_ok(strpos($v, "var OPEN_ENDED = ['PERMANENT']") !== false,
    'and the form knows which types have no end date');
t_ok(strpos($v, "monWrap.style.display  = open ? 'none' : ''") !== false,
    '*** so a permanent role is not asked "for how long"');
t_ok(strpos($v, "money(total) + (open ? ' a year' : '')") !== false,
    '*** and the live total reads "a year" rather than a bare figure');
