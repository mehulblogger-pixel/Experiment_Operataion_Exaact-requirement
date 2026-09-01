<?php
// Resource conflict & availability (Stage 7 pt.1) — a professional must never be
// silently committed to overlapping work, and a lapsed mandatory credential
// blocks. Reads existing engagements/identity/schedule/competence; adds nothing.
t_section('resource conflict & availability');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    if (function_exists('connect_engage_migrate')) connect_engage_migrate();

    // date helpers
    $ov = connect_conflict_days('2026-10-01', '2026-10-05');
    t_eq(count($ov), 5, 'connect_conflict_days enumerates an inclusive window');
    t_eq(count(connect_conflict_days('2026-10-01', '2027-12-31')), 62, 'a very long window is capped (bounded scan)');
    t_eq(count(connect_conflict_days('', '2026-10-05')), 0, 'a missing start yields no days');

    // a professional with one BOOKED engagement 10–20 Oct
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('conf.pro@demo.test','Conflict Pro',1,?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_engagements (subject_kind,subject_id,subject_name,poster_name,start_date,end_date,status,created_at) VALUES ('professional',?, 'Conflict Pro','Client A','2026-10-10','2026-10-20','BOOKED',?)")->execute([$pro, date('c')]);

    // CLEAR before, CONFLICT overlapping, CLEAR after
    t_eq(connect_conflict_check($pro, '2026-10-01', '2026-10-05')['status'], 'CLEAR', 'a window before the booking is CLEAR');
    $mid = connect_conflict_check($pro, '2026-10-15', '2026-10-18');
    t_eq($mid['status'], 'CONFLICT', 'an overlapping window is a CONFLICT');
    t_ok(!$mid['available'] && count($mid['conflicts']) === 1, 'the clashing booking is reported and marks unavailable');
    t_eq(connect_conflict_check($pro, '2026-10-25', '2026-10-30')['status'], 'CLEAR', 'a window after the booking is CLEAR');

    // touching boundary (ends exactly when the other starts) still overlaps on that day
    t_eq(connect_conflict_check($pro, '2026-10-20', '2026-10-22')['status'], 'CONFLICT', 'a boundary-touching window overlaps');

    // a CANCELLED booking never conflicts
    db()->prepare("UPDATE cx_engagements SET status='CANCELLED' WHERE subject_id=? AND subject_kind='professional'")->execute([$pro]);
    t_eq(connect_conflict_check($pro, '2026-10-15', '2026-10-18')['status'], 'CLEAR', 'a cancelled booking does not conflict');

    // editing the same booking excludes itself
    db()->prepare("UPDATE cx_engagements SET status='BOOKED' WHERE subject_id=? AND subject_kind='professional'")->execute([$pro]);
    $eid = (int)ops_val("SELECT id FROM cx_engagements WHERE subject_id=? AND subject_kind='professional' ORDER BY id DESC LIMIT 1", [$pro]);
    t_eq(connect_conflict_check($pro, '2026-10-15', '2026-10-18', ['except_engagement_id' => $eid])['status'], 'CLEAR',
        'excluding the booking being edited clears its own overlap');

    // engagement-overlap helper directly
    t_eq(count(connect_conflict_engagements('professional', $pro, '2026-10-12', '2026-10-14')), 1, 'engagement overlap helper finds the clash');
    t_eq(count(connect_conflict_engagements('professional', $pro, '2026-11-01', '2026-11-03')), 0, 'no clash outside the window');

    // badge
    t_eq(connect_conflict_badge(['status' => 'BLOCKED'])['tone'], 'bad', 'a blocked verdict badges red');
    t_eq(connect_conflict_badge(['status' => 'CLEAR'])['tone'], 'ok', 'a clear verdict badges ok');

    // empty / unknown professional never errors
    t_ok(connect_conflict_check(0, '2026-10-15', '2026-10-18')['available'], 'an unknown professional degrades to available (no error)');

    // ---- availability status model (§24) ----
    t_ok(count(connect_availability_states()) === 10, 'the availability vocabulary has all ten states');
    // fresh professional with nothing on → AVAILABLE
    db()->prepare("INSERT INTO cx_professionals (email,name,availability,is_active,created_at) VALUES ('avail.pro@demo.test','Avail Pro','AVAILABLE',1,?)")->execute([date('c')]);
    $ap = (int)db()->lastInsertId();
    t_eq(connect_availability_status($ap, '2026-10-10')['code'], 'AVAILABLE', 'a free professional is AVAILABLE');
    // a BOOKED engagement covering the date → BOOKED
    db()->prepare("INSERT INTO cx_engagements (subject_kind,subject_id,start_date,end_date,status,created_at) VALUES ('professional',?,'2026-10-05','2026-10-15','BOOKED',?)")->execute([$ap, date('c')]);
    t_eq(connect_availability_status($ap, '2026-10-10')['code'], 'BOOKED', 'a booked engagement on the date reads BOOKED');
    t_eq(connect_availability_status($ap, '2026-10-20')['code'], 'AVAILABLE', 'outside the engagement the professional is AVAILABLE again');
    // an ACTIVE engagement wins as IN_PROGRESS
    db()->prepare("UPDATE cx_engagements SET status='ACTIVE' WHERE subject_id=? AND subject_kind='professional'")->execute([$ap]);
    t_eq(connect_availability_status($ap, '2026-10-10')['code'], 'IN_PROGRESS', 'an active engagement reads IN_PROGRESS');
    // the professional's own OFF flag → UNAVAILABLE (on a date with no engagement)
    db()->prepare("UPDATE cx_professionals SET availability='OFF' WHERE id=?")->execute([$ap]);
    t_eq(connect_availability_status($ap, '2026-10-20')['code'], 'UNAVAILABLE', 'an OFF availability flag reads UNAVAILABLE');
    // a shortlisted application (no engagement/flag) → SHORTLISTED
    db()->prepare("UPDATE cx_professionals SET availability='AVAILABLE' WHERE id=?")->execute([$ap]);
    db()->prepare("INSERT INTO cx_applications (requirement_id,applicant_professional_id,applicant_name,status,created_at) VALUES (0,?,'Avail Pro','SHORTLISTED',?)")->execute([$ap, date('c')]);
    t_eq(connect_availability_status($ap, '2026-10-25')['code'], 'SHORTLISTED', 'a live shortlisting reads SHORTLISTED');
    t_eq(connect_availability_status(0)['code'], 'AVAILABLE', 'an unknown professional is AVAILABLE (no error)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
