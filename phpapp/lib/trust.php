<?php
// ============================================================================
//  The trust layer — binding EVIDENCE to place and time
//
//  The owner corrected the design before it was built, and the correction is
//  the most important thing in this file:
//
//    "Geotagging on the report may not be correct, because practically many
//     inspectors will complete the activity on site and then go to another
//     place or home to prepare the report."
//
//  Exactly so. An engineer photographs a weld at 11:40 at the plant, drives
//  home, and writes the report at nine that evening on a laptop. Any system
//  that takes the location of the *report* — or even of the *upload* — and
//  presents it as where the inspection happened is not adding trust, it is
//  manufacturing a lie that will be caught the first time a client checks.
//
//  So this file keeps THREE facts strictly apart, and never lets one stand in
//  for another:
//
//   1. WHERE THE CAMERA WAS WHEN THE SHUTTER FIRED.
//      Read out of the photograph's own EXIF data. It was written by the phone
//      at the moment of capture and it travels inside the file, so it survives
//      the drive home, the laptop and the e-mail. THIS is the evidence.
//
//   2. WHERE THE BROWSER WAS WHEN THE FILE WAS UPLOADED.
//      Recorded, but labelled as exactly that, everywhere it appears. It is
//      never presented as the inspection location, because usually it is the
//      engineer's kitchen table. It earns its keep only as a cross-check.
//
//   3. WHERE THE REPORT WAS WRITTEN.
//      Not recorded at all. It has no evidential meaning and collecting it
//      would only tempt somebody to show it.
//
//  And because a photograph can lack EXIF entirely — many phones strip it, and
//  some corporate sites forbid cameras — there is a fourth, deliberate act:
//
//   4. THE SITE CHECK-IN. One tap, on site, at the time: "I am here, now."
//      It is the engineer stating a fact while standing in the place, rather
//      than the software inferring one from a file upload hours later.
//
//  A LATE UPLOAD IS NOT SUSPICIOUS AND IS NEVER FLAGGED. Uploading in the
//  evening is the normal working pattern the owner described. Flagging it
//  would train everybody to ignore the flags, which is how a review queue dies.
// ============================================================================

// What a location on a piece of evidence actually came from. The order matters:
// the first is proof, the second is a hint, the third is nothing.
const GEO_SOURCES = [
    'EXIF'   => 'From the photograph itself — where the camera was when it was taken',
    'UPLOAD' => 'From the browser at upload time — where the file was sent from, not where it was taken',
    'NONE'   => 'No location available',
];

// Signals worth a supervisor's attention. Flagged, never blocked: a blocked
// photo is a photo that never gets taken, and the standard fraud tool — a
// fake-GPS app — defeats blocking anyway. A flag a person reads beats a gate
// a person routes around.
const EV_FLAGS = [
    'NO_GEO'        => 'No location at all',
    'UPLOAD_ONLY'   => 'Location only from the upload, which is not where it was taken',
    'FAR_FROM_SITE' => 'Taken far from where the engineer checked in',
    'IMPOSSIBLE'    => 'Two captures too far apart to be the same person',
    'PERFECT_ACC'   => 'Accuracy suspiciously exact — the signature of a fake-GPS app',
    'ROUND_COORDS'  => 'Coordinates too round to have come from a real fix',
    'CLOCK_SKEW'    => 'The device clock disagrees with ours',
];
// Flags that ought to stop a report being issued without somebody looking.
const EV_SERIOUS = ['FAR_FROM_SITE', 'IMPOSSIBLE', 'PERFECT_ACC', 'ROUND_COORDS'];

// Arriving, leaving, and anything in between.
const VISIT_KINDS = [
    'ENTRY'   => 'Arrived on site',
    'INTERIM' => 'While on site',
    'EXIT'    => 'Left site',
];

// How far from the check-in a photograph may be before it is worth a look.
const GEO_SITE_RADIUS_M = 2000;
// Faster than this between two captures by one person is not travel.
const GEO_MAX_KMH = 900;
// Device clock further out than this is worth knowing about.
const CLOCK_SKEW_MIN = 30;

function trust_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();

    // Fact 1 — the camera. Fact 2 — the upload. Held in different columns on
    // purpose, so no query can ever quietly substitute one for the other.
    ensure_column('report_files', 'exif_lat', 'DECIMAL(10,7) NULL');
    ensure_column('report_files', 'exif_lon', 'DECIMAL(10,7) NULL');
    ensure_column('report_files', 'up_lat',  'DECIMAL(10,7) NULL');
    ensure_column('report_files', 'up_lon',  'DECIMAL(10,7) NULL');
    ensure_column('report_files', 'up_acc',  'DECIMAL(10,2) NULL');
    ensure_column('report_files', 'geo_source', "VARCHAR(10) DEFAULT 'NONE'");
    // Server time is the only time we trust. taken_at comes from the device.
    ensure_column('report_files', 'received_at', "VARCHAR(30) DEFAULT ''");
    ensure_column('report_files', 'clock_skew', 'INT DEFAULT 0');
    ensure_column('report_files', 'flags', "VARCHAR(200) DEFAULT ''");
    ensure_column('report_files', 'flag_note', "VARCHAR(600) DEFAULT ''");
    ensure_column('report_files', 'reviewed_by', "VARCHAR(150) DEFAULT ''");
    ensure_column('report_files', 'reviewed_at', "VARCHAR(30) DEFAULT ''");
    ensure_column('report_files', 'review_note', "VARCHAR(600) DEFAULT ''");

    // Fact 4 — the deliberate act, on site, at the time.
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_visits (
        id $pk, job_id INT, inspector_id INT NULL,
        lat DECIMAL(10,7) NULL, lon DECIMAL(10,7) NULL, accuracy DECIMAL(10,2) NULL,
        device_at VARCHAR(30) DEFAULT '', at VARCHAR(30) DEFAULT '',
        clock_skew INT DEFAULT 0, flags VARCHAR(200) DEFAULT '',
        note VARCHAR(400) DEFAULT '', by_user VARCHAR(150) DEFAULT '',
        ip VARCHAR(60) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Arriving and leaving are different facts and the pair is what a client
    // actually wants: somebody was there, from this time to that time.
    ensure_column('site_visits', 'kind', "VARCHAR(10) DEFAULT 'ENTRY'");
    // The photograph taken AT the check-in. Its location is the browser's fix
    // at that instant — which, unlike a file uploaded that evening, really is
    // contemporaneous, because the shutter and the fix happen together.
    ensure_column('site_visits', 'photo_name', "VARCHAR(255) DEFAULT ''");
    ensure_column('site_visits', 'photo_mime', "VARCHAR(100) DEFAULT ''");
    ensure_column('site_visits', 'photo_data', 'LONGTEXT');
    ensure_column('site_visits', 'photo_sha1', "VARCHAR(40) DEFAULT ''");

    // The chain. Append-only, never updated, one row per evidence item at the
    // moment it arrived. Kept apart from report_files precisely BECAUSE that
    // table can legitimately change later (a caption, a review note) — the
    // chain records what was captured, not what the row says today.
    $pdo->exec("CREATE TABLE IF NOT EXISTS evidence_chain (
        id $pk, seq INT, report_doc_id INT NULL, file_id INT NULL,
        irn VARCHAR(120) DEFAULT '', content_sha1 VARCHAR(40) DEFAULT '',
        payload TEXT, prev_hash VARCHAR(64) DEFAULT '', entry_hash VARCHAR(64) DEFAULT '',
        at VARCHAR(30) DEFAULT '')");

    // The public verification handle for a report.
    ensure_column('report_docs', 'verify_code', "VARCHAR(24) DEFAULT ''");
}

function trust_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false
        || stripos($m, 'no such column') !== false;
}
function trust_try($fn, $fallback = []) {
    try { return $fn(); } catch (Throwable $e) { if (trust_missing_table($e)) return $fallback; throw $e; }
}

// ============================================================================
//  Reading a location out of the photograph itself
// ============================================================================
// EXIF stores latitude as three rationals (degrees, minutes, seconds) plus a
// hemisphere letter. Returns a float, or null when the tag is absent — which is
// common and is not a fault.
function exif_coord($parts, $ref) {
    if (!is_array($parts) || count($parts) < 3) return null;
    $num = function ($r) {
        if (is_numeric($r)) return (float)$r;
        if (is_string($r) && strpos($r, '/') !== false) {
            [$a, $b] = array_map('trim', explode('/', $r, 2));
            return ((float)$b != 0.0) ? (float)$a / (float)$b : 0.0;
        }
        return 0.0;
    };
    $deg = $num($parts[0]) + $num($parts[1]) / 60 + $num($parts[2]) / 3600;
    $ref = strtoupper(trim((string)$ref));
    if ($ref === 'S' || $ref === 'W') $deg = -$deg;
    return round($deg, 7);
}

// Where the camera was, and when the shutter fired. Both come from inside the
// file, so both survive the drive home — which is the whole point.
function exif_geo($bytes) {
    $out = ['lat' => null, 'lon' => null, 'at' => ''];
    if (!function_exists('exif_read_data')) return $out;
    $tmp = tempnam(sys_get_temp_dir(), 'gx');
    if ($tmp === false || file_put_contents($tmp, $bytes) === false) return $out;
    $ex = @exif_read_data($tmp, 'ANY_TAG', true);
    @unlink($tmp);
    if (!is_array($ex)) return $out;
    $g = $ex['GPS'] ?? [];
    if (!empty($g['GPSLatitude']) && !empty($g['GPSLongitude'])) {
        $out['lat'] = exif_coord($g['GPSLatitude'], $g['GPSLatitudeRef'] ?? 'N');
        $out['lon'] = exif_coord($g['GPSLongitude'], $g['GPSLongitudeRef'] ?? 'E');
        // 0,0 is the Atlantic off Ghana. It means "the tag was written empty",
        // never "the inspection was there".
        if ((float)$out['lat'] === 0.0 && (float)$out['lon'] === 0.0) { $out['lat'] = null; $out['lon'] = null; }
    }
    $d = $ex['EXIF']['DateTimeOriginal'] ?? ($ex['IFD0']['DateTime'] ?? '');
    if ($d) {
        $ts = strtotime(preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $d));
        if ($ts) $out['at'] = date('c', $ts);
    }
    return $out;
}

// "12.9716,77.5946" or "12.9716,77.5946,8.5" from the browser.
function geo_parse($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    $p = array_map('trim', explode(',', $raw));
    if (count($p) < 2 || !is_numeric($p[0]) || !is_numeric($p[1])) return null;
    $lat = (float)$p[0]; $lon = (float)$p[1];
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) return null;
    return ['lat' => round($lat, 7), 'lon' => round($lon, 7),
            'acc' => (count($p) > 2 && is_numeric($p[2])) ? round((float)$p[2], 2) : null];
}

// Metres between two points.
function geo_distance_m($lat1, $lon1, $lat2, $lon2) {
    if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) return null;
    $R = 6371000.0;
    $p1 = deg2rad((float)$lat1); $p2 = deg2rad((float)$lat2);
    $dp = deg2rad((float)$lat2 - (float)$lat1); $dl = deg2rad((float)$lon2 - (float)$lon1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return (int)round($R * 2 * atan2(sqrt($a), sqrt(1 - $a)));
}
function geo_pretty($lat, $lon) {
    if ($lat === null || $lon === null) return '—';
    return number_format((float)$lat, 5) . ', ' . number_format((float)$lon, 5);
}
// A map link that needs no key and no third-party script on our pages.
function geo_map_url($lat, $lon) {
    return ($lat === null || $lon === null) ? ''
        : 'https://www.openstreetmap.org/?mlat=' . rawurlencode((string)$lat)
        . '&mlon=' . rawurlencode((string)$lon) . '#map=17/' . rawurlencode((string)$lat) . '/' . rawurlencode((string)$lon);
}

// ============================================================================
//  Site check-in — the deliberate act, on site, at the time
// ============================================================================
function site_visits($jobId) {
    return trust_try(fn() => ops_all("SELECT * FROM site_visits WHERE job_id=? ORDER BY id", [(int)$jobId]));
}
function site_visit_latest($jobId) {
    $v = site_visits($jobId);
    return $v ? end($v) : null;
}

function site_checkin($jobId, $post, $file = null, $actorInspectorId = 0) {
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$jobId]);
    if (!$job) return 'That ' . Tl('job') . ' could not be found.';
    $kind = isset(VISIT_KINDS[$post['kind'] ?? '']) ? $post['kind'] : 'ENTRY';
    // Who is actually punching. On a job with different engineers on different
    // days, the person tapping the button — not the job's main inspector — is
    // the one stamped, so the day's attendance is against the right name.
    $actor = (int)$actorInspectorId ?: (int)($job['inspector_id'] ?? 0);
    // One arrival and one departure PER CALENDAR DAY: once punched out, that day
    // is closed for this person. "While on site" stays repeatable. This is what
    // makes the punch final for that particular day.
    if (($kind === 'ENTRY' || $kind === 'EXIT') && $actor) {
        $st = punch_state((int)$jobId, $actor, date('Y-m-d'));
        if ($kind === 'ENTRY' && $st['in'])
            return 'You already punched in for this ' . Tl('job') . ' today at '
                 . substr((string)$st['in']['at'], 11, 5) . '. A day has one arrival.';
        if ($kind === 'EXIT' && !$st['in'])
            return 'Punch in first — no arrival is recorded for this ' . Tl('job') . ' today.';
        if ($kind === 'EXIT' && $st['out'])
            return 'You already punched out for this ' . Tl('job') . ' today at '
                 . substr((string)$st['out']['at'], 11, 5) . '. The day is closed.';
    }
    // A photograph may be required, and when it is, it has to be a real one.
    $bytes = ($file && ($file['tmp_name'] ?? '') !== '' && is_uploaded_file($file['tmp_name']))
        ? (string)file_get_contents($file['tmp_name']) : '';
    if ($bytes !== '' && function_exists('upload_reject_reason')
        && ($why = upload_reject_reason($bytes, $file['name'] ?? '', $file['type'] ?? '')) !== '') return $why;
    if ($bytes === '' && (function_exists('geofence_photo_required') ? geofence_photo_required() : checkin_photo_required()))
        return 'A photograph is required when you check in. On a phone the button opens the camera; '
             . 'on a laptop choose a file. If the site does not allow cameras, ask a manager to turn the '
             . 'requirement off for this kind of work rather than photographing the car park.';
    $g = geo_parse((string)($post['gps'] ?? ''));
    if (!$g) return 'Your device did not give a location. Allow location for this site in the browser and try again — '
                  . 'or, if there is no signal, say so in the note and record it when you are back in coverage.';
    // Geofence: outside the site's fence, the punch is refused (when enforced).
    if (function_exists('geofence_check')) {
        $gf = geofence_check($job, $g['lat'], $g['lon'], $dist);
        if (empty($gf['ok'])) return $gf['msg'];
    }
    // The device's own clock is attacker-controlled and, far more often, simply
    // wrong. It is recorded for comparison and never used as the time.
    $deviceAt = trim((string)($post['device_at'] ?? ''));
    $skew = 0;
    if ($deviceAt !== '' && strtotime($deviceAt)) $skew = (int)round((strtotime($deviceAt) - time()) / 60);
    $flags = [];
    if ($g['acc'] !== null && $g['acc'] > 0 && $g['acc'] < 1) $flags[] = 'PERFECT_ACC';
    if (geo_looks_round($g['lat'], $g['lon'])) $flags[] = 'ROUND_COORDS';
    if (abs($skew) > CLOCK_SKEW_MIN) $flags[] = 'CLOCK_SKEW';
    $mime = $bytes !== '' ? (string)($file['type'] ?? 'image/jpeg') : '';
    if ($bytes !== '' && function_exists('idems_compress_image')) {
        // Compress AFTER the location is settled — this photograph's location
        // comes from the browser fix beside it, not from EXIF, so nothing is
        // lost here; but the ordering is kept deliberate so it stays that way.
        [$bytes, $mime, ] = idems_compress_image($bytes, $mime);
    }
    db()->prepare("INSERT INTO site_visits (job_id,inspector_id,kind,lat,lon,accuracy,device_at,at,clock_skew,flags,note,
                   photo_name,photo_mime,photo_data,photo_sha1,by_user,ip,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$jobId, $actor ?: ($job['inspector_id'] ?: null), $kind, $g['lat'], $g['lon'], $g['acc'],
                   $deviceAt, date('c'), $skew, implode(',', $flags),
                   substr(trim((string)($post['note'] ?? '')), 0, 400),
                   $bytes !== '' ? substr((string)($file['name'] ?? 'checkin.jpg'), 0, 255) : '',
                   $mime, $bytes !== '' ? base64_encode($bytes) : '',
                   $bytes !== '' ? sha1($bytes) : '',
                   user_name(current_user()), function_exists('client_ip') ? client_ip() : '', date('c')]);
    return '';
}

// ---- Arriving and leaving --------------------------------------------------
function checkin_photo_required() { return setting_get('checkin_photo_required', '0') === '1'; }
function checkin_entry_exit_required() { return setting_get('checkin_entry_exit_required', '0') === '1'; }

function site_visit_kinds_done($jobId) {
    $out = [];
    foreach (site_visits($jobId) as $v) $out[$v['kind'] ?? 'ENTRY'] = true;
    return array_keys($out);
}

// What is still owed before a deputation may be closed. Empty when nothing is.
// Off by default: a body that inspects inside a refinery where phones are
// confiscated at the gate cannot comply with it, and switching it on for
// everybody by default would make the software wrong for them on day one.
function site_visit_close_missing($job) {
    if (empty($job['inspector_id'])) return [];
    $done = site_visit_kinds_done((int)($job['id'] ?? 0));
    // Attendance is "expected" for this job when the company requires it, the
    // site is geofenced, or at least one check-in already exists — i.e. they
    // started marking attendance, so the pair (arrival AND departure) must be
    // completed. A job with no attendance in use at all is left alone.
    $expected = checkin_entry_exit_required()
        || (float)($job['site_geofence_m'] ?? 0) > 0
        || !empty($done);
    if (!$expected) return [];
    $miss = [];
    if (!in_array('ENTRY', $done, true)) $miss[] = 'an arrival check-in';
    if (!in_array('EXIT', $done, true))  $miss[] = 'a departure check-in';
    return $miss;
}
function site_visit_close_block($job) {
    $miss = site_visit_close_missing($job);
    if (!$miss) return '';
    return 'This ' . Tl('job') . ' is missing ' . implode(' and ', $miss)
         . ', so a client cannot be shown that somebody was on site and for how long.';
}
// §WO-9 — only a manager may close a job when a site check-in is missing:
// the operations manager, the branch manager, or this inspector's own reporting
// manager. The Master Admin always can.
function can_close_attendance_missing($job) {
    if (function_exists('is_master') && is_master()) return true;
    $u = current_user();
    if (in_array($u['role'] ?? '', ['OPERATION_MANAGER', 'BRANCH_MANAGER', 'ADMIN', 'MASTER_ADMIN'], true)) return true;
    $rm = (int)ops_val("SELECT reports_to_id FROM inspectors WHERE id=?", [(int)($job['inspector_id'] ?? 0)]);
    return $rm && $rm === (int)($u['id'] ?? 0);
}

// Arrival, departure, and how long between them — the pair a client actually
// asks about.
function site_visit_window($jobId) {
    $in = null; $out = null;
    foreach (site_visits($jobId) as $v) {
        $k = $v['kind'] ?? 'ENTRY';
        if ($k === 'ENTRY' && ($in === null || $v['at'] < $in['at'])) $in = $v;
        if ($k === 'EXIT'  && ($out === null || $v['at'] > $out['at'])) $out = $v;
    }
    $mins = ($in && $out && $in['at'] && $out['at'])
        ? (int)round((strtotime($out['at']) - strtotime($in['at'])) / 60) : null;
    return ['in' => $in, 'out' => $out, 'minutes' => $mins];
}

// ============================================================================
//  Today's punch — a web-first, one-tap check-in/out on the dashboard
//
//  The field engineer opens the app on any phone or laptop and punches in when
//  they reach the site and out when they leave — from a bar at the top of the
//  application, without opening a job screen. No Android app needed: the camera
//  and the GPS come from the browser. The geofence (Settings → Evidence &
//  check-in) applies to BOTH the in and the out. One arrival + one departure
//  per day, so a day, once punched out, is closed.
// ============================================================================

// The inspector identity of the signed-in user, or 0 if they are not one.
function punch_user_inspector_id() {
    $u = function_exists('current_user') ? current_user() : null;
    return $u ? (int)($u['inspector_id'] ?? 0) : 0;
}

// This person's arrival / departure for a job on a given day (default today).
function punch_state($jobId, $inspectorId, $day = null) {
    $day = $day ?: date('Y-m-d');
    $in = null; $out = null;
    foreach (site_visits($jobId) as $v) {
        if ((int)($v['inspector_id'] ?? 0) !== (int)$inspectorId) continue;
        if (substr((string)$v['at'], 0, 10) !== $day) continue;
        $k = $v['kind'] ?? 'ENTRY';
        if ($k === 'ENTRY') $in = $v;
        elseif ($k === 'EXIT') $out = $v;
    }
    return ['in' => $in, 'out' => $out,
            'status' => $out ? 'DONE' : ($in ? 'ON_SITE' : 'OUT'),
            'next'   => $out ? '' : ($in ? 'EXIT' : 'ENTRY')];
}

// The open deputations this inspector is due on a given day — whether they are
// the job's main engineer, or assigned that day through the per-day plan.
function punch_today_jobs($inspectorId = 0, $day = null) {
    $inspectorId = $inspectorId ?: punch_user_inspector_id();
    if (!$inspectorId) return [];
    $day = $day ?: date('Y-m-d');
    $seen = []; $out = [];
    $add = function ($j) use (&$seen, &$out) { if (empty($seen[$j['id']])) { $seen[$j['id']] = true; $out[] = $j; } };
    $sel = "j.*, c.call_code, bp.legal_name client_name, bp.display_name client_disp";
    try {
        foreach (ops_all("SELECT $sel FROM jobs j LEFT JOIN calls c ON c.id=j.call_id
                          LEFT JOIN business_partners bp ON bp.id=c.client_id
                          WHERE j.inspector_id=? AND COALESCE(j.closed_flag,0)=0", [$inspectorId]) as $j) {
            $dates = function_exists('job_call_required_dates') ? job_call_required_dates($j, []) : [];
            if (in_array($day, $dates, true)) $add($j);
        }
    } catch (Throwable $e) {}
    // Days handed to me through the per-day plan on a job somebody else leads.
    try {
        foreach (ops_all("SELECT $sel FROM job_visits v JOIN jobs j ON j.id=v.job_id
                          LEFT JOIN calls c ON c.id=j.call_id LEFT JOIN business_partners bp ON bp.id=c.client_id
                          WHERE v.inspector_id=? AND v.visit_date=? AND COALESCE(j.closed_flag,0)=0",
                         [$inspectorId, $day]) as $j) $add($j);
    } catch (Throwable $e) {}
    return $out;
}

// The bar rendered at the top of every page for a field engineer. Self-contained
// (its own form, styles inline, and script), so it can be dropped into the
// layout without touching anything else.
function punch_bar() {
    if (!function_exists('site_checkin')) return '';
    $insp = punch_user_inspector_id();
    if (!$insp) return '';
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $today = date('Y-m-d');
    $jobs = punch_today_jobs($insp, $today);
    $photoReq = function_exists('geofence_photo_required') ? geofence_photo_required()
              : (function_exists('checkin_photo_required') && checkin_photo_required());
    $fenceOn = function_exists('geofence_on') && geofence_on();
    $back = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($back === '' || $back[0] !== '/') $back = '/';
    $csrf = function_exists('csrf_token') ? csrf_token() : '';

    ob_start();
    if (!$jobs) {
        echo '<div class="punchbar pb-none">🟢 <span>No site visit scheduled for you today.'
           . ' When you are due on site, punch-in appears here.</span></div>';
        return ob_get_clean();
    }
    // Options carry each job's today-state, so the status line and the buttons
    // update the instant a different job is picked, with no round-trip.
    $opts = '';
    $first = null;
    foreach ($jobs as $j) {
        $st = punch_state((int)$j['id'], $insp, $today);
        $nm = ($j['client_disp'] ?: $j['client_name'] ?: $j['job_code']);
        $label = $j['job_code'] . ' · ' . $nm;
        if ($first === null) $first = $st;
        $opts .= '<option value="' . (int)$j['id'] . '"'
              . ' data-status="' . $e($st['status']) . '"'
              . ' data-in="' . $e($st['in']['at'] ?? '') . '"'
              . ' data-out="' . $e($st['out']['at'] ?? '') . '">' . $e($label) . '</option>';
    }
    ?>
    <div class="punchbar" id="punchBar">
      <form method="post" action="/punch" enctype="multipart/form-data" id="punchForm" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;width:100%">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="return" value="<?= $e($back) ?>">
        <input type="hidden" name="kind" id="pbKind" value="ENTRY">
        <input type="hidden" name="gps" id="pbGps">
        <input type="hidden" name="device_at" id="pbAt">
        <span class="pb-badge" id="pbBadge">●</span>
        <span class="pb-label">On site today:</span>
        <?php if (count($jobs) > 1): ?>
          <select class="form-control" name="job_id" id="pbJob" style="max-width:280px"><?= $opts ?></select>
        <?php else: ?>
          <input type="hidden" name="job_id" id="pbJob" value="<?= (int)$jobs[0]['id'] ?>">
          <span class="pb-job"><?= $e($jobs[0]['job_code'] . ' · ' . ($jobs[0]['client_disp'] ?: $jobs[0]['client_name'])) ?></span>
        <?php endif; ?>
        <span class="pb-status" id="pbStatus"></span>
        <?php if ($photoReq): ?>
          <label class="pb-photo">📷 <input type="file" name="photo" accept="image/*" capture="environment" style="max-width:150px"></label>
        <?php endif; ?>
        <button class="btn small" type="button" id="pbIn">📍 Punch in</button>
        <button class="btn small secondary" type="button" id="pbOut">🏁 Punch out</button>
        <span class="pb-msg muted" id="pbMsg"></span>
      </form>
    </div>
    <?php // Data for the JS: the single-job state when there is no picker. ?>
    <script>
    (function(){
      var bar=document.getElementById('punchBar'); if(!bar) return;
      var job=document.getElementById('pbJob'), status=document.getElementById('pbStatus'),
          badge=document.getElementById('pbBadge'), bIn=document.getElementById('pbIn'),
          bOut=document.getElementById('pbOut'), msg=document.getElementById('pbMsg'),
          kind=document.getElementById('pbKind'), form=document.getElementById('pbForm')||document.getElementById('punchForm');
      var single = <?= count($jobs) > 1 ? 'null' : json_encode(['status'=>$first['status'] ?? 'OUT','in'=>$first['in']['at'] ?? '','out'=>$first['out']['at'] ?? '']) ?>;
      function state(){
        if (job && job.tagName === 'SELECT') {
          var o=job.options[job.selectedIndex];
          return {status:o.getAttribute('data-status'), in:o.getAttribute('data-in'), out:o.getAttribute('data-out')};
        }
        return single || {status:'OUT', in:'', out:''};
      }
      function hm(s){ return s ? s.substr(11,5) : ''; }
      function render(){
        var s=state();
        if (s.status==='DONE'){ badge.style.color='#8a8f98'; status.innerHTML='<b>Done for today</b> — in '+hm(s.in)+', out '+hm(s.out);
          bIn.disabled=true; bOut.disabled=true; bIn.style.opacity=bOut.style.opacity=.5; }
        else if (s.status==='ON_SITE'){ badge.style.color='#2ecc71'; status.innerHTML='<b>On site</b> since '+hm(s.in);
          bIn.disabled=true; bIn.style.opacity=.5; bOut.disabled=false; bOut.style.opacity=1; }
        else { badge.style.color='#e0a800'; status.innerHTML='Not punched in yet';
          bOut.disabled=true; bOut.style.opacity=.5; bIn.disabled=false; bIn.style.opacity=1; }
      }
      if (job && job.tagName==='SELECT') job.addEventListener('change', render);
      render();
      function punch(k){
        if(!navigator.geolocation){ msg.textContent='This browser cannot give a location.'; return; }
        kind.value=k; bIn.disabled=bOut.disabled=true; msg.textContent='Getting your location…';
        navigator.geolocation.getCurrentPosition(function(p){
          document.getElementById('pbGps').value=[p.coords.latitude,p.coords.longitude,p.coords.accuracy].join(',');
          document.getElementById('pbAt').value=new Date().toISOString();
          form.submit();
        }, function(err){ render(); msg.textContent='No location: '+((err&&err.message)||'permission refused')+'.'; },
        {enableHighAccuracy:true, timeout:15000, maximumAge:0});
      }
      bIn.addEventListener('click', function(){ punch('ENTRY'); });
      bOut.addEventListener('click', function(){ punch('EXIT'); });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// Coordinates a real fix never produces: whole degrees, or two decimals, which
// is a 1 km square. Fake-GPS apps and typed-in values look like this.
function geo_looks_round($lat, $lon) {
    foreach ([$lat, $lon] as $v) {
        if ($v === null) return false;
        $s = rtrim(rtrim(number_format((float)$v, 7, '.', ''), '0'), '.');
        $dec = strpos($s, '.') === false ? 0 : strlen(substr($s, strpos($s, '.') + 1));
        if ($dec > 2) return false;
    }
    return true;
}

// ============================================================================
//  Working out what to record about one piece of evidence
//
//  Called at upload. Everything here is decided from the file and the server
//  clock; nothing is taken on the device's word.
// ============================================================================
function evidence_geo_facts($bytes, $mime, $postedGps, $jobId = 0) {
    $isImage = strpos((string)$mime, 'image/') === 0;
    $ex  = $isImage ? exif_geo($bytes) : ['lat' => null, 'lon' => null, 'at' => ''];
    $up  = geo_parse($postedGps);
    $now = time();

    $source = $ex['lat'] !== null ? 'EXIF' : ($up ? 'UPLOAD' : 'NONE');
    $skew = 0;
    if ($ex['at'] !== '' && strtotime($ex['at'])) $skew = (int)round((strtotime($ex['at']) - $now) / 60);

    $flags = [];
    if ($source === 'NONE' && $isImage) $flags[] = 'NO_GEO';
    if ($source === 'UPLOAD') $flags[] = 'UPLOAD_ONLY';
    if ($up && $up['acc'] !== null && $up['acc'] > 0 && $up['acc'] < 1) $flags[] = 'PERFECT_ACC';
    if ($ex['lat'] !== null && geo_looks_round($ex['lat'], $ex['lon'])) $flags[] = 'ROUND_COORDS';
    // Only the EXIF time is compared. The upload time being hours later is the
    // normal working pattern, not a discrepancy.
    if ($ex['at'] !== '' && abs($skew) > CLOCK_SKEW_MIN * 24 * 60) $flags[] = 'CLOCK_SKEW';

    // Against the deliberate check-in, where there is one. This is the only
    // comparison that means anything, because both ends are on-site facts.
    if ($jobId && $ex['lat'] !== null) {
        $v = site_visit_latest($jobId);
        if ($v && $v['lat'] !== null) {
            $d = geo_distance_m($ex['lat'], $ex['lon'], $v['lat'], $v['lon']);
            if ($d !== null && $d > GEO_SITE_RADIUS_M) $flags[] = 'FAR_FROM_SITE';
        }
    }

    return [
        'exif_lat' => $ex['lat'], 'exif_lon' => $ex['lon'], 'exif_at' => $ex['at'],
        'up_lat' => $up['lat'] ?? null, 'up_lon' => $up['lon'] ?? null, 'up_acc' => $up['acc'] ?? null,
        'geo_source' => $source, 'clock_skew' => $skew,
        'flags' => implode(',', array_unique($flags)),
    ];
}

// Two captures by one person, too far apart, too close together in time. Run
// after the fact because it needs the neighbours, not just the new row.
function evidence_impossible_travel($docId) {
    $rows = trust_try(fn() => ops_all(
        "SELECT id, exif_lat, exif_lon, taken_at, flags FROM report_files
         WHERE report_doc_id=? AND exif_lat IS NOT NULL AND COALESCE(taken_at,'')<>''
         ORDER BY taken_at", [(int)$docId]));
    $hit = 0;
    for ($i = 1; $i < count($rows); $i++) {
        $a = $rows[$i - 1]; $b = $rows[$i];
        $secs = strtotime((string)$b['taken_at']) - strtotime((string)$a['taken_at']);
        if ($secs <= 0) continue;
        $d = geo_distance_m($a['exif_lat'], $a['exif_lon'], $b['exif_lat'], $b['exif_lon']);
        if ($d === null) continue;
        $kmh = ($d / 1000) / ($secs / 3600);
        if ($kmh > GEO_MAX_KMH) {
            foreach ([$a, $b] as $r) {
                $f = array_filter(explode(',', (string)$r['flags']));
                if (!in_array('IMPOSSIBLE', $f, true)) {
                    $f[] = 'IMPOSSIBLE';
                    db()->prepare("UPDATE report_files SET flags=? WHERE id=?")
                        ->execute([implode(',', $f), (int)$r['id']]);
                    $hit++;
                }
            }
        }
    }
    return $hit;
}

function evidence_flags_of($row) {
    return array_values(array_filter(array_map('trim', explode(',', (string)($row['flags'] ?? '')))));
}
function evidence_serious($row) {
    return array_values(array_intersect(evidence_flags_of($row), EV_SERIOUS));
}

// ============================================================================
//  The hash chain — §8.4 in spirit, and what makes alteration detectable
//
//  Each entry hashes the one before it, so changing any earlier entry — or
//  removing one — breaks every hash after it. No blockchain, no third party:
//  an append-only table and one sha256 per row, which is a thing that can be
//  explained to an assessor in a sentence and re-verified in a second.
// ============================================================================
function chain_last() {
    return trust_try(fn() => ops_one("SELECT * FROM evidence_chain ORDER BY seq DESC, id DESC LIMIT 1"), null);
}

// The facts as they were at capture, in a fixed order. Any change to this
// function changes every hash, so it is written once and left alone.
function chain_payload($f, $doc) {
    return json_encode([
        'irn'    => (string)($doc['irn'] ?? ''),
        'file'   => (int)($f['id'] ?? 0),
        'name'   => (string)($f['file_name'] ?? ''),
        'sha1'   => (string)($f['sha1'] ?? ''),
        'bytes'  => (int)($f['bytes'] ?? 0),
        'taken'  => (string)($f['taken_at'] ?? ''),
        'recv'   => (string)($f['received_at'] ?? ''),
        'src'    => (string)($f['geo_source'] ?? 'NONE'),
        'lat'    => $f['exif_lat'] !== null ? (float)$f['exif_lat'] : null,
        'lon'    => $f['exif_lon'] !== null ? (float)$f['exif_lon'] : null,
        'by'     => (string)($f['created_by'] ?? ''),
    ], JSON_UNESCAPED_SLASHES);
}

function chain_append($fileId) {
    $f = trust_try(fn() => ops_one("SELECT * FROM report_files WHERE id=?", [(int)$fileId]), null);
    if (!$f) return 0;
    $doc = ops_one("SELECT id, irn FROM report_docs WHERE id=?", [(int)$f['report_doc_id']]) ?: ['irn' => ''];
    $prev = chain_last();
    $prevHash = $prev ? (string)$prev['entry_hash'] : str_repeat('0', 64);
    $seq = $prev ? (int)$prev['seq'] + 1 : 1;
    $payload = chain_payload($f, $doc);
    $hash = hash('sha256', $prevHash . '|' . $seq . '|' . $payload);
    db()->prepare("INSERT INTO evidence_chain (seq,report_doc_id,file_id,irn,content_sha1,payload,prev_hash,entry_hash,at)
                   VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$seq, (int)$f['report_doc_id'], (int)$f['id'], (string)$doc['irn'],
                   (string)$f['sha1'], $payload, $prevHash, $hash, date('c')]);
    return $seq;
}

// Walk the chain and report the first thing that does not add up. Three
// separate failures, because they mean different things:
//   BROKEN_LINK — an entry was altered or removed
//   FILE_GONE   — the evidence itself was deleted
//   CONTENT     — the bytes changed under a chain entry that still stands
function chain_verify($docId = 0) {
    $rows = trust_try(fn() => $docId
        ? ops_all("SELECT * FROM evidence_chain WHERE report_doc_id=? ORDER BY seq", [(int)$docId])
        : ops_all("SELECT * FROM evidence_chain ORDER BY seq"));
    $problems = []; $checked = 0;
    // Link checking only means anything over the whole chain, because a
    // per-report slice legitimately skips sequence numbers.
    if (!$docId) {
        $prevHash = str_repeat('0', 64); $expectSeq = 1;
        foreach ($rows as $r) {
            $checked++;
            if ((int)$r['seq'] !== $expectSeq)
                $problems[] = ['kind' => 'BROKEN_LINK', 'seq' => (int)$r['seq'],
                               'what' => 'Entry ' . $expectSeq . ' is missing from the chain.'];
            if ((string)$r['prev_hash'] !== $prevHash)
                $problems[] = ['kind' => 'BROKEN_LINK', 'seq' => (int)$r['seq'],
                               'what' => 'Entry ' . $r['seq'] . ' does not follow the one before it.'];
            $recomputed = hash('sha256', (string)$r['prev_hash'] . '|' . (int)$r['seq'] . '|' . (string)$r['payload']);
            if ($recomputed !== (string)$r['entry_hash'])
                $problems[] = ['kind' => 'BROKEN_LINK', 'seq' => (int)$r['seq'],
                               'what' => 'Entry ' . $r['seq'] . ' has been altered since it was written.'];
            $prevHash = (string)$r['entry_hash'];
            $expectSeq = (int)$r['seq'] + 1;
        }
    } else {
        foreach ($rows as $r) {
            $checked++;
            $recomputed = hash('sha256', (string)$r['prev_hash'] . '|' . (int)$r['seq'] . '|' . (string)$r['payload']);
            if ($recomputed !== (string)$r['entry_hash'])
                $problems[] = ['kind' => 'BROKEN_LINK', 'seq' => (int)$r['seq'],
                               'what' => 'Entry ' . $r['seq'] . ' has been altered since it was written.'];
        }
    }
    // And the evidence itself.
    foreach ($rows as $r) {
        $f = trust_try(fn() => ops_one("SELECT id, sha1 FROM report_files WHERE id=?", [(int)$r['file_id']]), null);
        if (!$f) {
            $problems[] = ['kind' => 'FILE_GONE', 'seq' => (int)$r['seq'],
                           'what' => 'The evidence recorded at entry ' . $r['seq'] . ' is no longer on file.'];
            continue;
        }
        if ((string)$f['sha1'] !== (string)$r['content_sha1'])
            $problems[] = ['kind' => 'CONTENT', 'seq' => (int)$r['seq'],
                           'what' => 'The evidence at entry ' . $r['seq'] . ' is not the file that was recorded.'];
    }
    return ['entries' => $checked, 'ok' => !$problems, 'problems' => $problems];
}

// ============================================================================
//  The client-verifiable report
//
//  "Verify it yourself" wins contracts; "trust us" does not. The code below is
//  readable over the telephone and carries no information about the report —
//  it is a handle, not a key to anything.
// ============================================================================
// Cached per document for the life of the request. Without that cache this was
// a real bug rather than an optimisation: the report screen calls this once for
// the printed code and again inside verify_url(), both times with the same
// in-memory row whose verify_code is still empty — so the second call minted a
// SECOND code and overwrote the first. The code on the paper and the code in
// the link disagreed, and only one of them worked.
function verify_code_for($doc) {
    static $seen = [];
    $id = (int)($doc['id'] ?? 0);
    if ($id && isset($seen[$id])) return $seen[$id];
    if (!empty($doc['verify_code'])) return $seen[$id] = $doc['verify_code'];
    // Re-read before minting: another request may have set one since this row
    // was loaded, and two codes for one report is worse than none.
    $existing = $id ? trust_try(fn() => ops_val("SELECT verify_code FROM report_docs WHERE id=?", [$id]), '') : '';
    if ($existing) return $seen[$id] = (string)$existing;
    // Four groups of four, from the alphabet without the characters people
    // mis-read aloud: no O/0, no I/1, no S/5.
    $abc = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';
    do {
        $c = '';
        for ($i = 0; $i < 16; $i++) {
            $c .= $abc[random_int(0, strlen($abc) - 1)];
            if ($i % 4 === 3 && $i < 15) $c .= '-';
        }
        $taken = trust_try(fn() => ops_val("SELECT COUNT(*) FROM report_docs WHERE verify_code=?", [$c]), 0);
    } while ($taken);
    db()->prepare("UPDATE report_docs SET verify_code=? WHERE id=?")->execute([$c, $id]);
    return $seen[$id] = $c;
}

function verify_url($doc) {
    $base = rtrim((string)setting_get('public_base_url', ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }
    return $base . '/verify?c=' . rawurlencode(verify_code_for($doc));
}

// What a stranger holding the code is told. Deliberately narrow: enough to
// answer "is this real and has it been changed", and nothing a competitor
// could mine. No client name, no findings, no prices.
// Nothing inside this may be allowed to take the page down. A client typing a
// code from a printed report is the least forgiving audience this application
// has, and "the app hit an error" on that page destroys exactly the trust the
// page exists to create — so the whole lookup is wrapped, and a failure reads
// as "we cannot answer right now" rather than a stack trace.
function verify_lookup($code) {
    try { return verify_lookup_inner($code); }
    catch (Throwable $e) {
        if (function_exists('failure_record_auto')) failure_record_auto('Verification page failed', $e->getMessage());
        return ['state' => 'error'];
    }
}

function verify_lookup_inner($code) {
    $code = strtoupper(trim((string)$code));
    if ($code === '') return null;
    $doc = trust_try(fn() => ops_one("SELECT * FROM report_docs WHERE verify_code=? AND COALESCE(deleted,0)=0", [$code]), null);
    if (!$doc) return null;
    if (empty($doc['finalized'])) return ['state' => 'not_issued', 'irn' => $doc['irn']];

    $files = trust_try(fn() => ops_all("SELECT geo_source, flags, taken_at FROM report_files WHERE report_doc_id=?", [(int)$doc['id']]));
    $photos = 0; $onSite = 0; $flagged = 0;
    foreach ($files as $f) {
        $photos++;
        if ($f['geo_source'] === 'EXIF') $onSite++;
        if (evidence_serious($f)) $flagged++;
    }
    $chain = chain_verify((int)$doc['id']);
    $insp = $doc['inspector_id']
        ? ops_val("SELECT name FROM inspectors WHERE id=?", [(int)$doc['inspector_id']]) : '';
    // Was the engineer authorised for anything at all? auth_live() judges ONE
    // authorisation row — it does not take a person — so the rows are fetched
    // and counted here. Getting that wrong put a fatal error on the one page in
    // this application that strangers are invited to open, which is the worst
    // possible place for one.
    $authorised = null;
    if (function_exists('authorisations_for') && function_exists('auth_live') && $doc['inspector_id']) {
        $live = 0;
        foreach (authorisations_for((int)$doc['inspector_id']) as $a) if (auth_live($a)) $live++;
        $authorised = $live > 0;
    }

    $content = function_exists('idems_content_check') ? idems_content_check($doc) : ['sealed' => false, 'ok' => true];

    // Module 44 — the strongest piece of evidence the verify page omitted: was
    // somebody actually on site? Read the job's arrival/departure check-in window.
    $checkin = null;
    if (!empty($doc['job_id']) && function_exists('site_visit_window')) {
        $w = site_visit_window((int)$doc['job_id']);
        if (($w['in'] ?? null) || ($w['out'] ?? null))
            $checkin = ['entry' => !empty($w['in']), 'exit' => !empty($w['out']), 'minutes' => $w['minutes']];
    }

    return [
        'state' => 'issued',
        'irn' => $doc['irn'],
        'issued_on' => $doc['finalized_at'] ?: $doc['issue_date'],
        'issued_by' => $doc['finalized_by'] ?: $doc['approved_by'],
        'inspector' => $insp,
        'authorised' => $authorised,
        'result' => $doc['result'],
        'evidence' => $photos, 'on_site' => $onSite, 'flagged' => $flagged,
        'checkin' => $checkin,
        'chain' => $chain,
        'content' => $content,
        'body' => app_name(),
        'ib_type' => function_exists('ib_type_label') ? ib_type_label() : '',
    ];
}

// ============================================================================
//  Screens
// ============================================================================
function trust_can_review() { return can('mod.idems.edit') || (is_admin_level() && licence_enabled('reporting')) || is_master_of('idems'); }

function ops_trust($route, $method) {
    if ($route === 'checkin-photo') {
        $v = trust_try(fn() => ops_one("SELECT * FROM site_visits WHERE id=?", [(int)($_GET['id'] ?? 0)]), null);
        if (!$v || empty($v['photo_data'])) { http_response_code(404); view('notfound'); return true; }
        // Phase 2 §51 — a check-in photo carries no office of its own; scope it to the
        // office/SBU of the job it belongs to (closes the long-flagged /checkin-photo IDOR).
        if (function_exists('scope_allows') && !empty($v['job_id'])) {
            $vj = ops_one("SELECT executing_office_id, sbu FROM jobs WHERE id=?", [(int)$v['job_id']]);
            ops_require(!$vj || scope_allows($vj['executing_office_id'] ?? null, $vj['sbu'] ?? null),
                'This check-in photo is outside your office / branch scope.');
        }
        send_uploaded_file(base64_decode((string)$v['photo_data']),
                           $v['photo_name'] ?: 'checkin.jpg', $v['photo_mime'] ?: 'image/jpeg');
        return true;
    }
    if ($route === 'site-checkin' && $method === 'POST') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $why = site_checkin($jobId, $_POST, $_FILES['photo'] ?? null);
        flash($why !== '' ? $why : 'Checked in. That is a fact recorded on site, at the time — which is what the '
            . 'photographs are later compared against.', $why !== '' ? 'error' : 'success');
        redirect('/job?id=' . $jobId);
    }
    // The top-of-app quick punch: the signed-in engineer punches in/out for one
    // of TODAY'S deputations, then lands back where they were.
    if ($route === 'punch' && $method === 'POST') {
        $back = (string)($_POST['return'] ?? '/');
        if ($back === '' || $back[0] !== '/') $back = '/';
        $insp = punch_user_inspector_id();
        if (!$insp) { flash('Only a field ' . Tl('engineer') . ' can punch in or out.', 'error'); redirect($back); }
        $jobId = (int)($_POST['job_id'] ?? 0);
        $mine = false;
        foreach (punch_today_jobs($insp) as $j) if ((int)$j['id'] === $jobId) { $mine = true; break; }
        if (!$mine) { flash('That is not one of your site visits for today.', 'error'); redirect($back); }
        $why = site_checkin($jobId, $_POST, $_FILES['photo'] ?? null, $insp);
        if ($why !== '') { flash($why, 'error'); redirect($back); }
        $lab = ($_POST['kind'] ?? '') === 'EXIT' ? 'Punched out' : (($_POST['kind'] ?? '') === 'ENTRY' ? 'Punched in' : 'Recorded');
        flash($lab . ' — recorded on site, at the time. The time is the server\'s; the location is your device fix at the tap.', 'success');
        redirect($back);
    }

    ops_require(trust_can_review(), 'Only somebody who works on reports can open the evidence review.');

    if ($route === 'evidence-review') {
        $docId = (int)($_GET['doc'] ?? 0);
        $rows = trust_try(fn() => $docId
            ? ops_all("SELECT f.*, d.irn FROM report_files f LEFT JOIN report_docs d ON d.id=f.report_doc_id
                       WHERE f.report_doc_id=? ORDER BY f.id", [$docId])
            : ops_all("SELECT f.*, d.irn FROM report_files f LEFT JOIN report_docs d ON d.id=f.report_doc_id
                       WHERE COALESCE(f.flags,'')<>'' AND COALESCE(f.reviewed_at,'')=''
                       ORDER BY f.id DESC LIMIT 200"));
        view('ops/evidence_review', [
            'rows' => $rows, 'docId' => $docId,
            'chain' => chain_verify($docId),
            'summary' => trust_readiness(),
        ]);
        return true;
    }

    if ($route === 'checkin-settings' && $method === 'POST') {
        ops_require(is_admin_level() || is_master(), 'Only an administrator can change these.');
        setting_set('checkin_entry_exit_required', !empty($_POST['entry_exit']) ? '1' : '0');
        setting_set('checkin_photo_required', !empty($_POST['photo']) ? '1' : '0');
        // Geofence: only punch in/out within range of the site, photo forced on.
        setting_set('geofence_on', !empty($_POST['geofence_on']) ? '1' : '0');
        if (isset($_POST['geofence_radius_m'])) setting_set('geofence_radius_m', (string)max(20, min(20000, (int)$_POST['geofence_radius_m'])));
        flash(checkin_entry_exit_required()
            ? 'Arrival and departure are now required before a ' . Tl('job') . ' can be closed.'
            : 'Arrival and departure are no longer required.');
        redirect('/evidence-review');
    }

    if ($route === 'evidence-reviewed' && $method === 'POST') {
        $note = trim((string)($_POST['review_note'] ?? ''));
        if ($note === '') {
            flash('Say what you checked. A flag cleared with nothing written against it is a flag nobody cleared.', 'error');
            redirect('/evidence-review' . ((int)($_POST['doc'] ?? 0) ? '?doc=' . (int)$_POST['doc'] : ''));
        }
        db()->prepare("UPDATE report_files SET reviewed_by=?, reviewed_at=?, review_note=? WHERE id=?")
            ->execute([user_name(current_user()), date('c'), substr($note, 0, 600), (int)($_POST['id'] ?? 0)]);
        flash('Recorded.');
        redirect('/evidence-review' . ((int)($_POST['doc'] ?? 0) ? '?doc=' . (int)$_POST['doc'] : ''));
    }

    redirect('/evidence-review');
    return true;
}

function trust_readiness() {
    $tot = (int)trust_try(fn() => ops_val("SELECT COUNT(*) FROM report_files WHERE kind='photo'"), 0);
    $exif = (int)trust_try(fn() => ops_val("SELECT COUNT(*) FROM report_files WHERE geo_source='EXIF'"), 0);
    $pending = (int)trust_try(fn() => ops_val("SELECT COUNT(*) FROM report_files WHERE COALESCE(flags,'')<>'' AND COALESCE(reviewed_at,'')=''"), 0);
    $chain = chain_verify();
    $visits = (int)trust_try(fn() => ops_val("SELECT COUNT(*) FROM site_visits"), 0);
    return ['photos' => $tot, 'on_site' => $exif, 'pending' => $pending,
            'chain' => $chain, 'visits' => $visits,
            'pct' => $tot ? (int)round($exif / $tot * 100) : 0];
}
