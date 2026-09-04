<?php
// Gap-3 (CONFIGURE) — engagement statuses must follow a defined transition machine, so there
// are no silent invalid status changes (the governance framework's explicit rule). Before this,
// connect_engage_set_status accepted any status in the set with no from→to check, so an
// engagement could jump COMPLETED→BOOKED or be revived from CANCELLED. This proves the machine.
t_section('engagement status transition machine (Gap 3)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_engage_migrate();
    $mk = function ($status) {
        db()->prepare("INSERT INTO cx_engagements (subject_kind, subject_id, subject_name, status, created_at) VALUES ('professional',1,'T',?,?)")
            ->execute([$status, date('c')]);
        return (int)db()->lastInsertId();
    };

    // the transition table itself
    t_ok(connect_engage_can_transition('BOOKED', 'ACTIVE'), 'BOOKED → ACTIVE allowed');
    t_ok(connect_engage_can_transition('BOOKED', 'COMPLETED'), 'BOOKED → COMPLETED allowed (direct complete)');
    t_ok(connect_engage_can_transition('ACTIVE', 'COMPLETED'), 'ACTIVE → COMPLETED allowed');
    t_ok(connect_engage_can_transition('BOOKED', 'CANCELLED'), 'BOOKED → CANCELLED allowed');
    t_ok(!connect_engage_can_transition('COMPLETED', 'BOOKED'), 'COMPLETED → BOOKED refused (terminal)');
    t_ok(!connect_engage_can_transition('COMPLETED', 'ACTIVE'), 'COMPLETED → ACTIVE refused (terminal)');
    t_ok(!connect_engage_can_transition('CANCELLED', 'ACTIVE'), 'CANCELLED → ACTIVE refused (no revival)');
    t_ok(connect_engage_can_transition('ACTIVE', 'ACTIVE'), 'same → same is a no-op (allowed)');
    t_eq(connect_engage_allowed_next('COMPLETED'), [], 'COMPLETED is a final state');

    // the setter enforces it and reports why
    $e = $mk('BOOKED');
    [$ok1] = connect_engage_set_status($e, 'ACTIVE');
    t_ok($ok1 === true, 'setter allows BOOKED → ACTIVE');
    t_eq((string)ops_val("SELECT status FROM cx_engagements WHERE id=?", [$e]), 'ACTIVE', 'status advanced to ACTIVE');
    [$ok2] = connect_engage_set_status($e, 'COMPLETED');
    t_ok($ok2 === true && (string)ops_val("SELECT status FROM cx_engagements WHERE id=?", [$e]) === 'COMPLETED', 'setter allows ACTIVE → COMPLETED');

    // a rejected transition changes nothing and explains
    [$bad, $why] = connect_engage_set_status($e, 'BOOKED');
    t_ok($bad === false, 'setter refuses COMPLETED → BOOKED');
    t_ok(strpos(strtolower($why), 'final state') !== false || strpos(strtolower($why), 'cannot move') !== false, 'the refusal explains why');
    t_eq((string)ops_val("SELECT status FROM cx_engagements WHERE id=?", [$e]), 'COMPLETED', 'a refused transition leaves the status unchanged');

    // cancelled cannot be revived
    $c = $mk('CANCELLED');
    t_ok(connect_engage_set_status($c, 'ACTIVE')[0] === false, 'setter refuses to revive a CANCELLED engagement');
    t_eq((string)ops_val("SELECT status FROM cx_engagements WHERE id=?", [$c]), 'CANCELLED', 'the cancelled engagement stays cancelled');

    // same → same is an accepted no-op
    [$noop, $nmsg] = connect_engage_set_status($c, 'CANCELLED');
    t_ok($noop === true && strpos(strtolower($nmsg), 'already') !== false, 'same-status set is an accepted no-op');

    // an unknown status value is still rejected
    t_ok(connect_engage_set_status($e, 'BANANA')[0] === false, 'an unknown status value is rejected');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
