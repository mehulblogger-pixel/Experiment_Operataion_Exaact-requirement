<?php
// Gap-4 (EXTEND) — the conflict engine was day-granular, so two same-day bookings on
// DIFFERENT shifts (DAY vs NIGHT) false-conflicted. An additive `shift` on the engagement
// + a shift-aware overlap now narrows a date clash to a time-of-day clash. Default (no shift
// = full day) preserves the exact legacy behaviour: a full-day booking still clashes with any.
t_section('shift-aware conflict detection (Gap 4)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_engage_migrate();

    // the shift-overlap primitive
    t_ok(connect_conflict_shift_overlap('', 'NIGHT') === true, 'a full-day booking clashes with any shift');
    t_ok(connect_conflict_shift_overlap('DAY', '') === true, 'any shift clashes with a full-day booking');
    t_ok(connect_conflict_shift_overlap('DAY', 'DAY') === true, 'the same named shift clashes');
    t_ok(connect_conflict_shift_overlap('DAY', 'NIGHT') === false, 'DAY and NIGHT do not clash');

    $d = date('Y-m-d', strtotime('+10 days'));
    $mk = function ($proId, $shift) use ($d) {
        db()->prepare("INSERT INTO cx_engagements (subject_kind, subject_id, subject_name, poster_name, start_date, end_date, status, shift, created_at)
                       VALUES ('professional',?,'T','A Client',?,?,'BOOKED',?,?)")->execute([(int)$proId, $d, $d, $shift, date('c')]);
    };

    // Professional P already has a DAY-shift booking on date $d (no inspector link → path 1 only).
    $P = 900001; $mk($P, 'DAY');
    t_eq(connect_conflict_check($P, $d, $d, ['shift' => 'NIGHT'])['status'], 'CLEAR', 'a NIGHT booking does not conflict with a DAY booking on the same day');
    t_eq(connect_conflict_check($P, $d, $d, ['shift' => 'DAY'])['status'], 'CONFLICT', 'a DAY booking conflicts with the existing DAY booking');
    t_eq(connect_conflict_check($P, $d, $d, [])['status'], 'CONFLICT', 'a full-day query (no shift) still conflicts — legacy behaviour preserved');

    // Professional Q has a FULL-DAY booking → it blocks any shift.
    $Q = 900002; $mk($Q, '');
    t_eq(connect_conflict_check($Q, $d, $d, ['shift' => 'NIGHT'])['status'], 'CONFLICT', 'an existing full-day booking blocks a NIGHT booking too');

    // the engagement fetch itself filters by shift
    $mkR = fn($shift) => connect_conflict_engagements('professional', $P, $d, $d, 0, $shift);
    t_eq(count($mkR('NIGHT')), 0, 'the DAY booking is filtered out for a NIGHT query');
    t_eq(count($mkR('DAY')), 1, 'the DAY booking is returned for a DAY query');
    t_eq(count($mkR('')), 1, 'a full-day query returns the DAY booking (clashes with everything)');

    // the shift round-trips through the booking engine (additive column, not mutating anything else)
    t_eq((string)ops_val("SELECT shift FROM cx_engagements WHERE subject_id=? LIMIT 1", [$P]), 'DAY', 'the shift is stored on the engagement');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
