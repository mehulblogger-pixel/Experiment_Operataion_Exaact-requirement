<?php
// ===========================================================================
//  How many days, and which ones
//
//  An inspection is booked in one of five shapes, and they are genuinely
//  different jobs of work. Showing all the boxes for all five at once — five
//  date slots, a weekday grid and an end date, on every call — meant the
//  coordinator had to know which ones to ignore, and the register could not
//  say what had actually been asked for.
//
//    SINGLE      one visit, on one date.
//    CONTINUOUS  the engineer is there day after day: "five days", "ten days".
//                Sundays and the office's public holidays are not working days,
//                so five days from Thursday does not end on Monday.
//    MULTIPLE    the client names the days — 1st, 5th, 6th, 8th, 10th, 12th.
//                It starts on the 1st and ends on the 12th, and the days in
//                between are not inspection days at all. Each visit may be a
//                different engineer.
//    PATTERN     "every Monday and Thursday", "twice a week", "once a month"
//                until a given date. The dates are worked out, not typed.
//    MONTHLY     the engineer is posted to a works for a month or more — the
//                man-month basis.
//
//  Only the boxes that shape needs are ever shown. Everything else — the end
//  date, the count of visits, the working-day arithmetic — is worked out here,
//  on the server, once. The browser asks and displays; it never re-implements
//  the holiday rules, because two implementations of a rule is two rules.
// ===========================================================================

const ENGAGEMENT_TYPES = [
    'SINGLE'     => 'Single day',
    'CONTINUOUS' => 'Continuous days',
    'MULTIPLE'   => 'Multiple dates',
    'PATTERN'    => 'Repeating pattern',
    'MONTHLY'    => 'Monthly deputation',
];

// How a repeating pattern repeats. Everything the client actually asks for.
const PATTERN_KINDS = [
    'WEEKDAYS'   => 'On chosen weekdays',
    'PER_WEEK'   => 'A number of visits each week',
    'EVERY_N'    => 'Every N days',
    'FORTNIGHT'  => 'Once a fortnight',
    'MONTHLY_1'  => 'Once a month',
];

function sched_migrate() {
    static $done = false; if ($done) return; $done = true;
    foreach (['calls', 'jobs'] as $t) {
        ensure_column($t, 'engagement_type', "VARCHAR(20) DEFAULT ''");
        ensure_column($t, 'days_count',   'INT DEFAULT 0');     // CONTINUOUS
        ensure_column($t, 'months_count', 'INT DEFAULT 0');     // MONTHLY
        ensure_column($t, 'pattern_kind', "VARCHAR(20) DEFAULT ''");
        ensure_column($t, 'pattern_n',    'INT DEFAULT 0');     // PER_WEEK / EVERY_N
    }
    // A holiday belongs to an office. Bombay does not shut for a Gujarat
    // holiday, and an end date that ignores that is wrong by a day for half the
    // company. A row with no office is a national holiday for everybody.
    ensure_column('holidays', 'office_id', 'INT NULL');
    // Saturday is a working day in some branches and not others.
    ensure_column('offices', 'weekly_working_days', 'DECIMAL(3,1) DEFAULT 6');
    // Multiple dates may be covered by different engineers. One row per visit.
    db()->exec("CREATE TABLE IF NOT EXISTS job_visits (
        id " . pk_clause() . ", job_id INT, visit_date VARCHAR(20),
        inspector_id INT NULL, status VARCHAR(20) DEFAULT 'PLANNED',
        note VARCHAR(255) DEFAULT '')");
    // Older records predate the type. Work out what they were from what they
    // carry, so no register row is left blank.
    try {
        db()->exec("UPDATE calls SET engagement_type='MULTIPLE'
                    WHERE COALESCE(engagement_type,'')='' AND inspection_dates LIKE '%,%'");
        db()->exec("UPDATE calls SET engagement_type='SINGLE' WHERE COALESCE(engagement_type,'')=''");
        db()->exec("UPDATE jobs SET engagement_type = COALESCE((SELECT c.engagement_type FROM calls c WHERE c.id=jobs.call_id), 'SINGLE')
                    WHERE COALESCE(engagement_type,'')=''");
    } catch (Throwable $e) { /* fresh database */ }
}

function engagement_label($code) {
    $opts = function_exists('lk_options_or') ? lk_options_or('engagement_type', ENGAGEMENT_TYPES) : ENGAGEMENT_TYPES;
    return $opts[$code] ?? ($code ?: '—');
}

// ---------------------------------------------------------------------------
//  Working days
// ---------------------------------------------------------------------------

// The public holidays that apply to an office: its own, plus the ones with no
// office named, which are everybody's.
function office_holidays($officeId, $from = null, $to = null) {
    static $cache = [];
    $officeId = (int)$officeId;
    $key = $officeId . '|' . (string)$from . '|' . (string)$to;
    if (isset($cache[$key])) return $cache[$key];
    $w = ["(office_id IS NULL OR office_id = 0" . ($officeId ? " OR office_id = " . $officeId : '') . ")"];
    $a = [];
    if ($from) { $w[] = "hol_date >= ?"; $a[] = $from; }
    if ($to)   { $w[] = "hol_date <= ?"; $a[] = $to; }
    $out = [];
    try {
        foreach (ops_all("SELECT hol_date, name FROM holidays WHERE " . implode(' AND ', $w), $a) as $r)
            $out[substr((string)$r['hol_date'], 0, 10)] = (string)$r['name'];
    } catch (Throwable $e) {}
    return $cache[$key] = $out;
}

// Saturday is a working day in most of these offices, but not all — the office
// carries its own weekly pattern and this reads it rather than assuming.
function office_weekly_days($officeId) {
    $v = null;
    try { $v = ops_val("SELECT weekly_working_days FROM offices WHERE id=?", [(int)$officeId]); } catch (Throwable $e) {}
    $v = (float)($v ?: 0);
    if (!$v) $v = (float)(function_exists('setting') ? (setting('weekly_working_days') ?: 6) : 6);
    return in_array($v, [5.0, 5.5, 6.0], true) ? $v : 6.0;
}

// Sunday is never a working day; Saturday depends on the office; a public
// holiday for that office is never one. This is the only place that decides.
function is_working_day($date, $officeId = null) {
    $ts = strtotime((string)$date);
    if ($ts === false) return false;
    $dow = (int)date('N', $ts);                       // 1 Mon .. 7 Sun
    if ($dow === 7) return false;
    if ($dow === 6 && office_weekly_days($officeId) == 5.0) return false;
    return !isset(office_holidays($officeId)[date('Y-m-d', $ts)]);
}

// Why a date is not a working day, in words, for the screen.
function non_working_reason($date, $officeId = null) {
    $ts = strtotime((string)$date);
    if ($ts === false) return 'not a date';
    $hol = office_holidays($officeId);
    $d = date('Y-m-d', $ts);
    if (isset($hol[$d])) return $hol[$d];
    $dow = (int)date('N', $ts);
    if ($dow === 7) return 'Sunday';
    if ($dow === 6 && office_weekly_days($officeId) == 5.0) return 'Saturday';
    return '';
}

function next_working_day($date, $officeId = null) {
    $ts = strtotime((string)$date);
    if ($ts === false) return '';
    for ($i = 0; $i < 400; $i++) {
        $d = date('Y-m-d', $ts + $i * 86400);
        if (is_working_day($d, $officeId)) return $d;
    }
    return date('Y-m-d', $ts);
}

// N working days starting at (or on the first working day after) a date.
// "Five days from Thursday" is Thu Fri Mon Tue Wed when Saturday is off — the
// arithmetic nobody should be doing in their head against a holiday list.
function sched_continuous($start, $days, $officeId = null) {
    $days = max(1, (int)$days);
    $ts = strtotime((string)$start);
    if ($ts === false) return [];
    $out = [];
    for ($i = 0; $i < 800 && count($out) < $days; $i++) {
        $d = date('Y-m-d', $ts + $i * 86400);
        if (is_working_day($d, $officeId)) $out[] = $d;
    }
    return $out;
}

// A repeating pattern between two dates. Non-working days are skipped, not
// moved — a Monday visit that lands on a holiday is simply not that week.
function sched_pattern($start, $end, $kind, $n, $weekdays, $officeId = null) {
    $s = strtotime((string)$start); $e = strtotime((string)$end);
    if ($s === false || $e === false || $e < $s) return [];
    if (($e - $s) / 86400 > 730) $e = $s + 730 * 86400;      // two years is plenty
    $wd = array_values(array_filter(array_map('intval', (array)$weekdays)));
    $n = max(1, (int)$n);
    $out = [];
    switch ($kind) {
        case 'WEEKDAYS':
            if (!$wd) return [];
            for ($t = $s; $t <= $e; $t += 86400) {
                $d = date('Y-m-d', $t);
                if (in_array((int)date('N', $t), $wd, true) && is_working_day($d, $officeId)) $out[] = $d;
            }
            break;
        case 'PER_WEEK':
            // n visits a week, spread evenly across the working days of each
            // week, starting the week the engagement starts.
            for ($t = $s; $t <= $e; ) {
                $weekEnd = min($e, $t + (7 - (int)date('N', $t)) * 86400);
                $days = [];
                for ($x = $t; $x <= $weekEnd; $x += 86400) {
                    $d = date('Y-m-d', $x);
                    if (is_working_day($d, $officeId)) $days[] = $d;
                }
                if ($days) {
                    $take = min($n, count($days));
                    // Evenly spaced picks: first, then across the rest.
                    for ($k = 0; $k < $take; $k++) {
                        $idx = $take === 1 ? 0 : (int)round($k * (count($days) - 1) / ($take - 1));
                        $out[] = $days[$idx];
                    }
                }
                $t = $weekEnd + 86400;
            }
            break;
        case 'EVERY_N':
            for ($t = $s; $t <= $e; $t += $n * 86400) {
                $d = next_working_day(date('Y-m-d', $t), $officeId);
                if (strtotime($d) <= $e) $out[] = $d;
            }
            break;
        case 'FORTNIGHT':
            for ($t = $s; $t <= $e; $t += 14 * 86400) {
                $d = next_working_day(date('Y-m-d', $t), $officeId);
                if (strtotime($d) <= $e) $out[] = $d;
            }
            break;
        case 'MONTHLY_1':
            for ($m = 0; $m < 60; $m++) {
                $t = strtotime("+$m month", $s);
                if ($t > $e) break;
                $d = next_working_day(date('Y-m-d', $t), $officeId);
                if (strtotime($d) <= $e) $out[] = $d;
            }
            break;
    }
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

// Every working day of a monthly posting: from the start, for N whole months.
function sched_monthly($start, $months, $officeId = null) {
    $s = strtotime((string)$start);
    if ($s === false) return ['dates' => [], 'end' => ''];
    $months = max(1, (int)$months);
    $endTs = strtotime('+' . $months . ' month -1 day', $s);
    $dates = [];
    for ($t = $s; $t <= $endTs; $t += 86400) {
        $d = date('Y-m-d', $t);
        if (is_working_day($d, $officeId)) $dates[] = $d;
    }
    return ['dates' => $dates, 'end' => date('Y-m-d', $endTs)];
}

// ---------------------------------------------------------------------------
//  One row in, its dates out
//
//  The single answer to "when is this happening", for a call or a job, whatever
//  shape it is. Every screen, every register column and every reminder reads
//  this — so they cannot disagree about what was booked.
// ---------------------------------------------------------------------------
function sched_resolve($row, $officeId = null, $startOverride = null) {
    $type  = (string)($row['engagement_type'] ?? '') ?: 'SINGLE';
    $office = $officeId ?: ($row['executing_office_id'] ?? null);
    $start = trim((string)($startOverride
        ?: ($row['scheduled_date'] ?? '')
        ?: ($row['inspection_start_date'] ?? '')
        ?: ($row['inspection_required_date'] ?? '')));
    $dates = [];
    $note  = '';

    switch ($type) {
        case 'CONTINUOUS':
            $n = (int)($row['days_count'] ?? 0);
            $dates = ($start !== '' && $n > 0) ? sched_continuous($start, $n, $office) : [];
            if ($dates) {
                $span = (int)round((strtotime(end($dates)) - strtotime($dates[0])) / 86400) + 1;
                $skipped = $span - count($dates);
                $note = $n . ' working day(s)'
                      . ($skipped > 0 ? ', stepping over ' . $skipped . ' non-working day(s)' : '');
            }
            break;
        case 'MULTIPLE':
            $dates = call_dates_parse((string)($row['inspection_dates'] ?? ''));
            $note = count($dates) . ' visit(s) named by the ' . (function_exists('Tl') ? Tl('client') : 'client');
            break;
        case 'PATTERN':
            $end = trim((string)($row['schedule_end_date'] ?? ''));
            $dates = ($start !== '' && $end !== '')
                ? sched_pattern($start, $end, (string)($row['pattern_kind'] ?? 'WEEKDAYS'),
                                (int)($row['pattern_n'] ?? 1),
                                array_filter(array_map('intval', explode(',', (string)($row['schedule_weekdays'] ?? '')))),
                                $office)
                : [];
            $note = count($dates) . ' visit(s) from the pattern';
            break;
        case 'MONTHLY':
            $m = (int)($row['months_count'] ?? 0) ?: 1;
            $r = $start !== '' ? sched_monthly($start, $m, $office) : ['dates' => [], 'end' => ''];
            $dates = $r['dates'];
            $note = $m . ' month(s) on site — ' . count($dates) . ' working day(s)';
            break;
        default: // SINGLE
            $dates = $start !== '' ? [$start] : [];
            $note = 'One visit';
    }

    return [
        'type'   => $type,
        'label'  => engagement_label($type),
        'dates'  => $dates,
        'start'  => $dates ? $dates[0] : $start,
        'end'    => $dates ? end($dates) : '',
        'count'  => count($dates),
        'note'   => $note,
    ];
}

// The register line: what was asked for, in one phrase.
function sched_summary($row, $officeId = null) {
    $s = sched_resolve($row, $officeId);
    if (!$s['count']) return $s['label'];
    if ($s['type'] === 'SINGLE') return $s['label'] . ' — ' . fdate($s['start']);
    return $s['label'] . ' — ' . fdate($s['start']) . ' to ' . fdate($s['end'])
         . ' (' . $s['count'] . ' day' . ($s['count'] === 1 ? '' : 's') . ')';
}

// ---------------------------------------------------------------------------
//  Is the engineer free?
// ---------------------------------------------------------------------------

// What the engineer is already doing on a date: another job, or a day marked
// off. Returns '' when the day is free.
function inspector_busy_on($inspectorId, $date, $exceptJobId = 0) {
    $inspectorId = (int)$inspectorId; $date = substr((string)$date, 0, 10);
    if (!$inspectorId || $date === '') return '';
    // A day the coordinator has marked — leave, training, anything not available.
    try {
        $st = ops_one("SELECT status, note FROM inspector_day_status WHERE inspector_id=? AND day=?",
                      [$inspectorId, $date]);
        if ($st && strtoupper((string)$st['status']) !== 'AVAILABLE')
            return trim((string)($st['note'] ?: $st['status']));
    } catch (Throwable $e) {}
    // A visit already booked on another job.
    try {
        $v = ops_one("SELECT j.job_code FROM job_visits v JOIN jobs j ON j.id=v.job_id
                      WHERE v.inspector_id=? AND v.visit_date=? AND j.id<>?",
                     [$inspectorId, $date, (int)$exceptJobId]);
        if ($v) return 'already on ' . $v['job_code'];
    } catch (Throwable $e) {}
    // A job whose own date list covers this day.
    try {
        foreach (ops_all("SELECT id, job_code, engagement_type, days_count, months_count, pattern_kind,
                                 pattern_n, schedule_weekdays, schedule_end_date, inspection_dates,
                                 scheduled_date, inspection_start_date, inspection_end_date, executing_office_id
                          FROM jobs WHERE inspector_id=? AND id<>? AND COALESCE(closed_flag,0)=0",
                         [$inspectorId, (int)$exceptJobId]) as $j) {
            $r = sched_resolve($j, $j['executing_office_id'] ?? null);
            if (in_array($date, $r['dates'], true)) return 'already on ' . $j['job_code'];
        }
    } catch (Throwable $e) {}
    return '';
}

// Every date of an engagement, with whoever is proposed for it and whether
// they can actually be there. This is what the allocate screen draws.
function sched_availability($dates, $inspectorId, $exceptJobId = 0, $officeId = null) {
    $out = [];
    foreach ((array)$dates as $d) {
        $busy = inspector_busy_on($inspectorId, $d, $exceptJobId);
        $out[] = [
            'date'    => $d,
            'pretty'  => fdate($d),
            'weekday' => date('D', strtotime($d)),
            'working' => is_working_day($d, $officeId),
            'why'     => non_working_reason($d, $officeId),
            'busy'    => $busy,
            'free'    => $busy === '',
        ];
    }
    return $out;
}

// Who else could take this date — same branch, same discipline, actually free.
// Offered rather than imposed: the coordinator knows things the system does not.
function sched_alternatives($date, $officeId = null, $sbu = '', $limit = 6) {
    $w = ["COALESCE(status,'ACTIVE') = 'ACTIVE'"];
    $a = [];
    if ($officeId) { $w[] = "home_office_id = ?"; $a[] = (int)$officeId; }
    $rows = [];
    try { $rows = ops_all("SELECT id, name, sbus, sbu FROM inspectors WHERE " . implode(' AND ', $w), $a); }
    catch (Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        if ($sbu !== '') {
            $his = array_filter(array_map('trim', explode(',', (string)($r['sbus'] ?: $r['sbu']))));
            if ($his && !in_array($sbu, $his, true)) continue;
        }
        if (inspector_busy_on($r['id'], $date) !== '') continue;
        $out[] = ['id' => (int)$r['id'], 'name' => $r['name']];
        if (count($out) >= $limit) break;
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  The visits a job actually consists of
// ---------------------------------------------------------------------------

// Write the visit rows for a job from its own dates. Anything already recorded
// against a date — a different engineer for that one visit — is kept.
function job_visits_sync($jobId, $defaultInspectorId = null, $perDate = []) {
    $jobId = (int)$jobId;
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
    if (!$job) return [];
    $r = sched_resolve($job, $job['executing_office_id'] ?? null);
    $have = [];
    foreach (ops_all("SELECT * FROM job_visits WHERE job_id=?", [$jobId]) as $v)
        $have[substr((string)$v['visit_date'], 0, 10)] = $v;
    $keep = [];
    $ins = db()->prepare("INSERT INTO job_visits (job_id,visit_date,inspector_id,status,note) VALUES (?,?,?,?,'')");
    $upd = db()->prepare("UPDATE job_visits SET inspector_id=? WHERE id=?");
    foreach ($r['dates'] as $d) {
        $who = array_key_exists($d, (array)$perDate) && (int)$perDate[$d]
             ? (int)$perDate[$d]
             : (isset($have[$d]) ? (int)$have[$d]['inspector_id'] : (int)$defaultInspectorId);
        if (isset($have[$d])) { $upd->execute([$who ?: null, $have[$d]['id']]); $keep[] = (int)$have[$d]['id']; }
        else { $ins->execute([$jobId, $d, $who ?: null, 'PLANNED']); $keep[] = (int)db()->lastInsertId(); }
    }
    // Dates that are no longer part of the engagement stop being visits.
    $gone = array_diff(array_map(function ($v) { return (int)$v['id']; }, array_values($have)), $keep);
    foreach ($gone as $id) db()->prepare("DELETE FROM job_visits WHERE id=?")->execute([$id]);
    return ops_all("SELECT * FROM job_visits WHERE job_id=? ORDER BY visit_date", [$jobId]);
}

function job_visits($jobId) {
    try { return ops_all("SELECT v.*, i.name inspector_name FROM job_visits v
                          LEFT JOIN inspectors i ON i.id=v.inspector_id
                          WHERE v.job_id=? ORDER BY v.visit_date", [(int)$jobId]); }
    catch (Throwable $e) { return []; }
}

// ---------------------------------------------------------------------------
//  What the browser asks for
//
//  The form posts what has been typed so far and gets back the dates, the end
//  date and the availability. The holiday rules stay in one place.
// ---------------------------------------------------------------------------
function ops_sched_preview() {
    header('Content-Type: application/json');
    $b = $_POST ?: $_GET;
    $row = [
        'engagement_type'          => (string)($b['engagement_type'] ?? 'SINGLE'),
        'days_count'               => (int)($b['days_count'] ?? 0),
        'months_count'             => (int)($b['months_count'] ?? 0),
        'pattern_kind'             => (string)($b['pattern_kind'] ?? ''),
        'pattern_n'                => (int)($b['pattern_n'] ?? 0),
        'schedule_weekdays'        => implode(',', array_map('intval', (array)($b['schedule_weekdays'] ?? []))),
        'schedule_end_date'        => (string)($b['schedule_end_date'] ?? ''),
        'inspection_dates'         => implode(',', (array)($b['inspection_dates'] ?? [])),
        'inspection_required_date' => (string)($b['start'] ?? ''),
        'scheduled_date'           => (string)($b['start'] ?? ''),
    ];
    $office = (int)($b['office'] ?? 0) ?: null;
    $r = sched_resolve($row, $office);
    $inspector = (int)($b['inspector'] ?? 0);
    $exceptJob = (int)($b['job'] ?? 0);
    $days = sched_availability($r['dates'], $inspector, $exceptJob, $office);
    // Only look for stand-ins where there is a clash — this walks every job.
    if ($inspector) {
        foreach ($days as $i => $d) {
            if ($d['free'] || !$d['working']) continue;
            $days[$i]['alternatives'] = sched_alternatives($d['date'], $office, (string)($b['sbu'] ?? ''));
        }
    }
    $clashes = count(array_filter($days, function ($d) { return $d['working'] && !$d['free']; }));
    echo json_encode([
        'type' => $r['type'], 'label' => $r['label'], 'count' => $r['count'],
        'start' => $r['start'], 'end' => $r['end'], 'note' => $r['note'],
        'startPretty' => $r['start'] ? fdate($r['start']) : '',
        'endPretty'   => $r['end'] ? fdate($r['end']) : '',
        'days' => $days, 'clashes' => $clashes,
    ]);
    return true;
}
