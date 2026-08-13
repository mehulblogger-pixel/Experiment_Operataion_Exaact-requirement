<?php
// ============================================================================
//  ATTEND — self-service daily attendance (Mark IN / Mark OUT) from the top of
//  the dashboard. Proves: location MANDATORY for In-office / On-site on both
//  the IN and OUT punch; check-out needs a check-in first; the mark flows to
//  the availability board; it links to that date's report by inspector+date.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

attend_migrate();
db()->prepare("INSERT INTO inspectors (name, status) VALUES ('Mira Attend','ACTIVE')")->execute();
$insp = (int)db()->lastInsertId();
$today = date('Y-m-d');
$loc = ['lat'=>'19.0760000', 'lon'=>'72.8777000', 'accuracy'=>12];

head('1. Additive columns on the existing attendance table');
$id = attend_row($insp, $today);
$row = ops_one("SELECT * FROM attendance WHERE id=?", [$id]);
ok(array_key_exists('check_in_at', $row) && array_key_exists('in_lat', $row) && array_key_exists('check_out_at', $row), 'attendance gains check_in/out + geo columns (table reused, not replaced)');
db()->exec("DELETE FROM attendance WHERE inspector_id=$insp");

head('2. Mark IN — location MANDATORY for In-office and On-site');
ok(attend_punch($insp, $today, 'IN', 'OFFICE', [])['ok'] === false, 'Mark IN as In-office WITHOUT a location is refused');
ok(attend_punch($insp, $today, 'IN', 'SITE', [])['ok'] === false, 'Mark IN as On-site WITHOUT a location is refused');
$r = attend_punch($insp, $today, 'IN', 'OFFICE', $loc);
ok($r['ok'] === true && $r['kind'] === 'IN', 'Mark IN as In-office WITH a location is accepted');
$m = attend_today($insp, $today);
ok($m['status'] === 'OFFICE' && $m['in_lat'] !== null && trim($m['check_in_at']) !== '', 'the check-in stamps status, coordinates and time');
ok(attend_loc_required('OFFICE') && attend_loc_required('SITE') && !attend_loc_required('WFH') && !attend_loc_required('LEAVE'), 'location is required for OFFICE+SITE only');

head('3. Mark OUT — needs a check-in, and location for an on-location day');
ok(attend_punch($insp, $today, 'OUT', '', [])['ok'] === false, 'Mark OUT of an on-location day WITHOUT a location is refused');
$r = attend_punch($insp, $today, 'OUT', '', $loc);
ok($r['ok'] === true && $r['kind'] === 'OUT', 'Mark OUT with a location is accepted');
ok(trim(attend_today($insp, $today)['check_out_at']) !== '', 'the check-out time is stored');

head('4. Check-out requires a prior check-in');
db()->prepare("INSERT INTO inspectors (name, status) VALUES ('Novi Nocheck','ACTIVE')")->execute();
$insp2 = (int)db()->lastInsertId();
ok(attend_punch($insp2, $today, 'OUT', '', $loc)['ok'] === false, 'Mark OUT before any Mark IN is refused');

head('5. Non-location statuses need no coordinates (WFH / leave)');
db()->prepare("INSERT INTO inspectors (name, status) VALUES ('Lena Leave','ACTIVE')")->execute();
$insp3 = (int)db()->lastInsertId();
ok(attend_punch($insp3, $today, 'IN', 'LEAVE', [])['ok'] === true, 'Mark IN as On-leave with no location is accepted');
ok(attend_today($insp3, $today)['status'] === 'LEAVE', 'the day reads LEAVE');
ok(attend_punch($insp3, $today, 'OUT', '', [])['ok'] === true, 'Mark OUT of a non-location day needs no location');

head('6. The mark flows onto the availability board (status shows accordingly)');
ok(ops_one("SELECT status FROM inspector_day_status WHERE inspector_id=? AND day=?", [$insp, $today])['status'] === 'OFFICE', 'In-office shows OFFICE on the availability board');
attend_punch($insp3, $today, 'IN', 'SITE', ['lat'=>'19.1','lon'=>'72.9']);
ok(ops_one("SELECT status FROM inspector_day_status WHERE inspector_id=? AND day=?", [$insp3, $today])['status'] === 'ON_JOB', 'On-site maps to ON_JOB on the board');
ok((int)ops_val("SELECT COUNT(*) FROM inspector_day_status WHERE inspector_id=? AND day=?", [$insp3, $today]) === 1, 'one day-status per inspector per day (no duplicates)');

head('7. Auto-links to that date’s report (inspector + date)');
db()->prepare("INSERT INTO jobs (inspector_id, scheduled_date, stage, created_at) VALUES (?,?, 'REPORT_PENDING', ?)")->execute([$insp, $today, date('c')]);
$jid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO report_docs (job_id, irn, status, inspector_id, inspection_date, created_at, updated_at) VALUES (?,?,?,?,?,?,?)")
    ->execute([$jid, 'ATT/26/1', 'DRAFT', $insp, $today, date('c'), date('c')]);
ok(attend_for_report($insp, $today) && attend_for_report($insp, $today)['status'] === 'OFFICE', 'the report’s inspector+date resolves the same-day attendance');
ok(attend_for_report($insp, date('Y-m-d', strtotime('-5 days'))) === null, 'a different date has no (wrong) attendance linked');

head('8. Options lookup-driven; unknown status / kind refused');
ok(isset(attend_status_options()['OFFICE']) && isset(attend_status_options()['SITE']), 'the self-mark options resolve from the lookup');
ok(attend_punch($insp, $today, 'IN', 'BOGUS', $loc)['ok'] === false, 'an unknown status is refused');
ok(attend_punch($insp, $today, 'SIDEWAYS', 'OFFICE', $loc)['ok'] === false, 'an unknown punch kind is refused');

head('9. The dashboard widget renders with Mark IN / Mark OUT');
ob_start(); attend_render_widget(['inspector_id'=>$insp2]); $h = ob_get_clean();
ok(strpos($h, 'Mark IN') !== false && strpos($h, 'Mark OUT') !== false, 'the widget shows both Mark IN and Mark OUT buttons');
ok(strpos($h, 'attPunch(') !== false && strpos($h, 'navigator.geolocation') !== false, 'it wires the geolocation capture');
ob_start(); attend_render_widget(['inspector_id'=>0]); $h0 = ob_get_clean();
ok(trim($h0) === '', 'a user with no inspector record sees no widget');

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== ATTEND: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
