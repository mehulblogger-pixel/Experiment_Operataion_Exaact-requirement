<?php
// Web-first punch (top-of-app check-in/out): one arrival + one departure per
// person per calendar day, geofence applies to both, and a day once punched out
// is closed. All against the real boot()-migrated schema.
t_section('top-of-app punch in / out (#geofence-punch)');

$now = date('c');
$today = date('Y-m-d');

// An inspector, a client, a call and a job scheduled for TODAY.
db()->prepare("INSERT INTO inspectors (name, emp_code, staff_kind, status, created_at)
               VALUES (?,?,?,?,?)")->execute(['Punch Tester', 'EMP99', 'ASSET', 'ACTIVE', $now]);
$insp = (int)db()->lastInsertId();
db()->prepare("INSERT INTO business_partners (legal_name, display_name, code, is_client, status, created_at)
               VALUES (?,?,?,?,?,?)")->execute(['Punch Client', 'Punch Client', 'PUN', 1, 'ACTIVE', $now]);
$client = (int)db()->lastInsertId();
db()->prepare("INSERT INTO calls (call_code, client_id, status, inspection_required_date, inspection_dates, created_at)
               VALUES (?,?,?,?,?,?)")->execute(['CPUN', $client, 'ALLOCATED', $today, $today, $now]);
$callId = (int)db()->lastInsertId();
db()->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, scheduled_date, inspection_dates, closed_flag, created_at)
               VALUES (?,?,?,?,?,?,?)")->execute(['JPUN', $callId, $insp, $today, $today, 0, $now]);
$jobId = (int)db()->lastInsertId();

// Geofence OFF for this test so distance never blocks the logic under test.
setting_set('geofence_on', '0');
setting_set('checkin_photo_required', '0');

// The job shows up as one of the inspector's jobs for today.
$mine = punch_today_jobs($insp, $today);
t_ok(count($mine) === 1 && (int)$mine[0]['id'] === $jobId, 'today\'s scheduled job is offered to the inspector for punching');

// Fresh state: nobody punched in yet.
$st0 = punch_state($jobId, $insp, $today);
t_eq($st0['status'], 'OUT', 'before any punch the state is OUT');
t_eq($st0['next'], 'ENTRY', 'the next action is to punch in');

// A GPS fix the punch needs (any coords, since the fence is off).
$post = fn($kind) => ['kind' => $kind, 'gps' => '19.076,72.877,8', 'device_at' => $now];

// Punch in.
t_eq(site_checkin($jobId, $post('ENTRY'), null, $insp), '', 'punch-in is accepted');
$st1 = punch_state($jobId, $insp, $today);
t_eq($st1['status'], 'ON_SITE', 'after punch-in the state is ON_SITE');

// A second punch-in the same day is refused — one arrival per day.
$why = site_checkin($jobId, $post('ENTRY'), null, $insp);
t_ok($why !== '' && stripos($why, 'already punched in') !== false, 'a second punch-in the same day is refused');

// Punch out.
t_eq(site_checkin($jobId, $post('EXIT'), null, $insp), '', 'punch-out is accepted');
$st2 = punch_state($jobId, $insp, $today);
t_eq($st2['status'], 'DONE', 'after punch-out the day is DONE');

// A second punch-out is refused — the day is closed.
$why2 = site_checkin($jobId, $post('EXIT'), null, $insp);
t_ok($why2 !== '' && stripos($why2, 'day is closed') !== false, 'a second punch-out is refused — the day is locked');

// Punch out with no prior arrival (a different day / person) is refused.
$other = $insp + 9999;
$whyNoIn = site_checkin($jobId, $post('EXIT'), null, $other);
t_ok($whyNoIn !== '' && stripos($whyNoIn, 'punch in first') !== false, 'punch-out without an arrival is refused');

// The window helper sees the pair as a completed on-site span.
$win = site_visit_window($jobId);
t_ok($win['in'] !== null && $win['out'] !== null, 'the arrival/departure pair is recorded as an on-site window');

// Geofence applies to the punch-OUT the same as the punch-in — it is the same
// check on the same coordinates. Turn the fence on, set the job's site, and the
// verdict for a far-away point is a refusal; a point inside the fence passes.
setting_set('geofence_on', '1');
db()->prepare("UPDATE jobs SET site_lat=?, site_lon=?, site_geofence_m=? WHERE id=?")
    ->execute([19.0760, 72.8777, 200, $jobId]);
$job = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
$far = geofence_check($job, 19.2000, 72.9000, $dFar);   // ~13 km away
t_ok(empty($far['ok']), 'with the fence on, a punch far from the site is refused (applies to check-out too)');
$near = geofence_check($job, 19.0761, 72.8778, $dNear); // a few metres away
t_ok(!empty($near['ok']), 'a punch within the fence passes');
setting_set('geofence_on', '0');
