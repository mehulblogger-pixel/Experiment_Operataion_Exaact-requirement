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
    'MONTHLY'    => '',   // label built by engagement_label()
];

// Same reason as NCR_SOURCES: a constant cannot call T(), so the one entry that
// names a business noun is completed here.
function engagement_types() {
    $m = ENGAGEMENT_TYPES;
    $m['MONTHLY'] = 'Monthly ' . Tl('job');
    return $m;
}


// How a man-month is defined. This is a commercial term, not a fact about the
// calendar, and different clients mean different things by it.
//
//   CALENDAR   the 1st to the last day of the month is one man-month, whether
//              that month happened to hold 24 working days or 27. No pro-rata.
//   MIN_DAYS   a minimum number of working days — usually 26 — has to be put
//              in. Fall short (five Sundays, a run of holidays) and only the
//              proportion actually worked is claimable. Exceed it and it is
//              still exactly one man-month; the extra days are not billable.
const MANMONTH_BASES = [
    'CALENDAR' => 'Calendar month — one man-month whatever the working days',
    'MIN_DAYS' => 'Minimum working days — pro-rata below, capped at one above',
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
        // A pattern is chosen on the call and can be corrected at allocation, so
        // BOTH tables need to hold it. These two existed only on calls, and the
        // deputation save listed them without them existing — every allocation
        // of a patterned engagement died on the INSERT.
        ensure_column($t, 'schedule_weekdays', "VARCHAR(40) DEFAULT ''");   // CSV of 1..7
        ensure_column($t, 'schedule_end_date', "VARCHAR(20) DEFAULT ''");
        // Days the coordinator has decided will be worked even though they fall
        // on a Sunday or a holiday. The client asked, or the works is running a
        // shutdown — either way it is a decision, not an accident, so it is
        // recorded rather than inferred.
        ensure_column($t, 'force_dates', "VARCHAR(600) DEFAULT ''");
        // What a man-month means on THIS engagement, carried from the client.
        ensure_column($t, 'manmonth_basis', "VARCHAR(20) DEFAULT ''");
        ensure_column($t, 'manmonth_min_days', 'INT DEFAULT 0');
        // The inter-office credit is agreed per man-day, the same way the client
        // charge is. Only the total was ever stored, so a six-day deputation
        // carried one day's credit and nobody could see which figure was wrong.
        ensure_column($t, 'credit_rate', 'DECIMAL(14,2) DEFAULT 0');
        // Anything else this one cost that is not salary, a claimed expense or a
        // sub-contractor: a hired instrument, a permit, a courier.
        ensure_column($t, 'other_cost', 'DECIMAL(14,2) DEFAULT 0');
        ensure_column($t, 'other_cost_note', "VARCHAR(200) DEFAULT ''");
    }
    // The client's own definition, which is where it is usually agreed.
    ensure_column('business_partners', 'manmonth_basis', "VARCHAR(20) DEFAULT ''");
    ensure_column('business_partners', 'manmonth_min_days', 'INT DEFAULT 0');
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
    // §WO-8 — each day of a multi-day job can be closed on its own, and no day is
    // closed without its report. The completion is recorded per visit.
    ensure_column('job_visits', 'report_link', "VARCHAR(500) DEFAULT ''");
    ensure_column('job_visits', 'report_doc_id', 'INT NULL');
    ensure_column('job_visits', 'closed_by', "VARCHAR(150) DEFAULT ''");
    ensure_column('job_visits', 'closed_at', "VARCHAR(30) DEFAULT ''");
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
    $opts = function_exists('lk_options_or') ? lk_options_or('engagement_type', engagement_types()) : engagement_types();
    return $opts[$code] ?? ($code ?: '—');
}

// ---------------------------------------------------------------------------
//  Working days
// ---------------------------------------------------------------------------

// The public holidays that apply to an office: its own, plus the ones with no
// office named, which are everybody's.
// Adding a holiday has to take effect at once, not on the next page load —
// somebody adds one BECAUSE a date they are looking at is wrong.
function office_holidays_flush() { office_holidays(-1, null, null, true); }

function office_holidays($officeId, $from = null, $to = null, $flush = false) {
    static $cache = [];
    if ($flush) { $cache = []; return []; }
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

// Saturday is a full working day for an inspection engineer. The 5 / 5.5 / 6
// pattern on an office is about office staff and their leave arithmetic; it has
// never applied to a man on a site, and applying it here made every end date a
// day late.
//
// So a day is a working day unless it is a Sunday or a public holiday for that
// branch — and unless the coordinator has said otherwise, which they can.
function is_working_day($date, $officeId = null, $forced = []) {
    $ts = strtotime((string)$date);
    if ($ts === false) return false;
    $d = date('Y-m-d', $ts);
    // A day the coordinator has decided will be worked anyway. The client asked
    // for the Sunday, or the works is on a shutdown run — their call, not ours.
    if ($forced && in_array($d, (array)$forced, true)) return true;
    if ((int)date('N', $ts) === 7) return false;
    return !isset(office_holidays($officeId)[$d]);
}

// Why a date is not a working day, in words, for the screen.
function non_working_reason($date, $officeId = null) {
    $ts = strtotime((string)$date);
    if ($ts === false) return 'not a date';
    $d = date('Y-m-d', $ts);
    $hol = office_holidays($officeId);
    if (isset($hol[$d])) return $hol[$d];
    if ((int)date('N', $ts) === 7) return 'Sunday';
    return '';
}

function next_working_day($date, $officeId = null, $forced = []) {
    $ts = strtotime((string)$date);
    if ($ts === false) return '';
    for ($i = 0; $i < 400; $i++) {
        $d = date('Y-m-d', $ts + $i * 86400);
        if (is_working_day($d, $officeId, $forced)) return $d;
    }
    return date('Y-m-d', $ts);
}

// The days inside a span that are NOT being worked, and why. These are what the
// screen offers to override: a Sunday followed by a Monday holiday pushes the
// visit to Tuesday on its own, and the coordinator can pull either back in.
function sched_skipped($from, $to, $officeId = null, $forced = []) {
    $a = strtotime((string)$from); $b = strtotime((string)$to);
    if ($a === false || $b === false || $b < $a) return [];
    if (($b - $a) / 86400 > 400) return [];
    $out = [];
    for ($t = $a; $t <= $b; $t += 86400) {
        $d = date('Y-m-d', $t);
        if (is_working_day($d, $officeId, $forced)) continue;
        $out[] = ['date' => $d, 'pretty' => fdate($d), 'weekday' => date('D', $t),
                  'why' => non_working_reason($d, $officeId)];
    }
    return $out;
}

// The dates a record says are worked regardless.
function sched_forced($row) {
    return call_dates_parse((string)($row['force_dates'] ?? ''));
}

// N working days starting at (or on the first working day after) a date.
// "Five days from Thursday" is Thu Fri Mon Tue Wed when Saturday is off — the
// arithmetic nobody should be doing in their head against a holiday list.
function sched_continuous($start, $days, $officeId = null, $forced = []) {
    $days = max(1, (int)$days);
    $ts = strtotime((string)$start);
    if ($ts === false) return [];
    $out = [];
    for ($i = 0; $i < 800 && count($out) < $days; $i++) {
        $d = date('Y-m-d', $ts + $i * 86400);
        if (is_working_day($d, $officeId, $forced)) $out[] = $d;
    }
    return $out;
}

// A repeating pattern between two dates. Non-working days are skipped, not
// moved — a Monday visit that lands on a holiday is simply not that week.
function sched_pattern($start, $end, $kind, $n, $weekdays, $officeId = null, $forced = []) {
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
                if (in_array((int)date('N', $t), $wd, true) && is_working_day($d, $officeId, $forced)) $out[] = $d;
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
                    if (is_working_day($d, $officeId, $forced)) $days[] = $d;
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
                $d = next_working_day(date('Y-m-d', $t), $officeId, $forced);
                if (strtotime($d) <= $e) $out[] = $d;
            }
            break;
        case 'FORTNIGHT':
            for ($t = $s; $t <= $e; $t += 14 * 86400) {
                $d = next_working_day(date('Y-m-d', $t), $officeId, $forced);
                if (strtotime($d) <= $e) $out[] = $d;
            }
            break;
        case 'MONTHLY_1':
            for ($m = 0; $m < 60; $m++) {
                $t = strtotime("+$m month", $s);
                if ($t > $e) break;
                $d = next_working_day(date('Y-m-d', $t), $officeId, $forced);
                if (strtotime($d) <= $e) $out[] = $d;
            }
            break;
    }
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

// ---------------------------------------------------------------------------
//  Man-months
//
//  A monthly deputation runs the 1st to the last day of the month — that is
//  what a month means on these contracts, whatever day the engineer happens to
//  arrive. What is CLAIMABLE for that month depends on how the client defines a
//  man-month, and the two common definitions give different answers:
//
//    CALENDAR   the month is the month. 24 working days or 27, it is one
//               man-month and there is nothing to pro-rate.
//    MIN_DAYS   the contract says a minimum — usually 26 working days. Five
//               Sundays and a couple of holidays and the month only holds 24,
//               so 24/26 of a man-month is claimable. A month holding 27 is
//               still one man-month; the extra day is not billable.
//
//  Both are here because both are real, and which applies is a commercial term
//  agreed with the client, not something the calendar can decide.
// ---------------------------------------------------------------------------

// Which definition applies: what this engagement says, else what the client's
// record says, else the company default in Settings. Most specific wins.
function manmonth_rule($row, $clientId = null) {
    $basis = trim((string)($row['manmonth_basis'] ?? ''));
    $min   = (int)($row['manmonth_min_days'] ?? 0);
    if ($basis === '' && $clientId) {
        $c = ops_one("SELECT manmonth_basis, manmonth_min_days FROM business_partners WHERE id=?", [(int)$clientId]);
        if ($c) { $basis = trim((string)($c['manmonth_basis'] ?? '')); $min = $min ?: (int)($c['manmonth_min_days'] ?? 0); }
    }
    if ($basis === '' && function_exists('setting_get')) {
        $basis = (string)(setting_get('manmonth_basis', '') ?: '');
        $min = $min ?: (int)(setting_get('manmonth_min_days', 0) ?: 0);
    }
    if (!isset(MANMONTH_BASES[$basis])) $basis = 'CALENDAR';
    if ($min <= 0) $min = 26;
    return ['basis' => $basis, 'min_days' => $min];
}

// Where that definition came from, so nobody has to guess which of three places
// is in force.
function manmonth_source($row, $clientId = null) {
    if (trim((string)($row['manmonth_basis'] ?? '')) !== '') return 'this ' . Tl('job');
    if ($clientId) {
        $c = ops_val("SELECT manmonth_basis FROM business_partners WHERE id=?", [(int)$clientId]);
        if (trim((string)$c) !== '') return 'the ' . Tl('client') . ' record';
    }
    if (function_exists('setting_get') && (string)(setting_get('manmonth_basis', '') ?: '') !== '') return 'the company default';
    return 'the built-in default';
}

// A monthly posting, month by month: the working days each month actually
// holds, and what is claimable for it.
function sched_monthly($start, $months, $officeId = null, $rule = null, $forced = []) {
    $s = strtotime((string)$start);
    if ($s === false) return ['dates' => [], 'end' => '', 'months' => [], 'claimable' => 0];
    $months = max(1, (int)$months);
    $rule = $rule ?: ['basis' => 'CALENDAR', 'min_days' => 26];
    // The 1st to the last day of the month, always.
    $first = date('Y-m-01', $s);
    $endTs = strtotime(date('Y-m-t', strtotime('+' . ($months - 1) . ' month', strtotime($first))));
    $dates = []; $per = []; $claim = 0;
    for ($m = 0; $m < $months; $m++) {
        $mStart = strtotime('+' . $m . ' month', strtotime($first));
        $mFrom = date('Y-m-01', $mStart);
        $mTo   = date('Y-m-t', $mStart);
        $work = [];
        for ($t = strtotime($mFrom); $t <= strtotime($mTo); $t += 86400) {
            $d = date('Y-m-d', $t);
            if (is_working_day($d, $officeId, $forced)) $work[] = $d;
        }
        $n = count($work);
        $units = ($rule['basis'] === 'MIN_DAYS')
            ? min(1.0, $n / max(1, (int)$rule['min_days']))     // short month pro-rata, long month capped
            : 1.0;
        $units = round($units, 4);
        $claim += $units;
        $per[] = ['month' => date('Y-m', $mStart), 'label' => date('F Y', $mStart),
                  'from' => $mFrom, 'to' => $mTo, 'working_days' => $n, 'units' => $units,
                  'short' => ($rule['basis'] === 'MIN_DAYS' && $n < (int)$rule['min_days'])];
        $dates = array_merge($dates, $work);
    }
    return ['dates' => $dates, 'end' => date('Y-m-d', $endTs), 'start' => $first,
            'months' => $per, 'claimable' => round($claim, 4),
            'basis' => $rule['basis'], 'min_days' => (int)$rule['min_days']];
}

// ---------------------------------------------------------------------------
//  One row in, its dates out
//
//  The single answer to "when is this happening", for a call or a job, whatever
//  shape it is. Every screen, every register column and every reminder reads
//  this — so they cannot disagree about what was booked.
// ---------------------------------------------------------------------------
function sched_resolve($row, $officeId = null, $startOverride = null, $clientId = null) {
    $type  = (string)($row['engagement_type'] ?? '') ?: 'SINGLE';
    $office = $officeId ?: ($row['executing_office_id'] ?? null);
    $forced = sched_forced($row);
    $start = trim((string)($startOverride
        ?: ($row['scheduled_date'] ?? '')
        ?: ($row['inspection_start_date'] ?? '')
        ?: ($row['inspection_required_date'] ?? '')));
    $dates = [];
    $note  = '';
    $extra = [];

    switch ($type) {
        case 'CONTINUOUS':
            $n = (int)($row['days_count'] ?? 0);
            $dates = ($start !== '' && $n > 0) ? sched_continuous($start, $n, $office, $forced) : [];
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
                                $office, $forced)
                : [];
            $note = count($dates) . ' visit(s) from the pattern';
            break;
        case 'MONTHLY':
            $m = (int)($row['months_count'] ?? 0) ?: 1;
            $rule = manmonth_rule($row, $clientId);
            $r = $start !== ''
                ? sched_monthly($start, $m, $office, $rule, $forced)
                : ['dates' => [], 'end' => '', 'months' => [], 'claimable' => 0];
            $dates = $r['dates'];
            $extra['manmonths'] = $r['months'] ?? [];
            $extra['claimable'] = $r['claimable'] ?? 0;
            $extra['basis'] = $rule['basis'];
            $extra['min_days'] = $rule['min_days'];
            $note = $m . ' month(s), 1st to the last day — ' . count($dates) . ' working day(s); '
                  . rtrim(rtrim(number_format((float)($r['claimable'] ?? 0), 2), '0'), '.')
                  . ' man-month(s) claimable'
                  . ($rule['basis'] === 'MIN_DAYS' ? ' on a ' . (int)$rule['min_days'] . '-day basis' : ' on a calendar basis');
            break;
        default: // SINGLE
            $dates = $start !== '' ? [$start] : [];
            $note = 'One visit';
    }

    // A Sunday, or a holiday, sitting inside the span. The run steps over them
    // by itself; these are offered so the coordinator can pull one back in when
    // the client has asked for it.
    $skippable = ($dates && count($dates) > 1)
        ? sched_skipped($dates[0], end($dates), $office, $forced) : [];

    return array_merge([
        'type'    => $type,
        'label'   => engagement_label($type),
        'dates'   => $dates,
        'start'   => $dates ? $dates[0] : $start,
        'end'     => $dates ? end($dates) : '',
        'count'   => count($dates),
        'note'    => $note,
        'forced'  => $forced,
        'skipped' => $skippable,
    ], $extra);
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

// §WO-8 — close one day of a multi-day job. A report (a link or an IDEMS report
// on the job) is required; without it the day cannot be marked done. Creates the
// visit row lazily if the schedule was never synced.
function job_visit_close($jobId, $date, $reportLink = '', $reportDocId = 0) {
    $jobId = (int)$jobId; $date = substr((string)$date, 0, 10);
    if (!$jobId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return 'That is not a valid visit date.';
    $reportLink = trim((string)$reportLink);
    if ($reportLink === '' && !$reportDocId) {
        // accept an IDEMS report already filed against this job as the evidence
        try { $reportDocId = (int)ops_val("SELECT id FROM report_docs WHERE job_id=? AND deleted=0 ORDER BY id DESC LIMIT 1", [$jobId]); } catch (Throwable $e) {}
    }
    if ($reportLink === '' && !$reportDocId) return 'A report (a link, or an inspection report on this ' . Tl('job') . ') is required before a day can be closed.';
    $row = ops_one("SELECT * FROM job_visits WHERE job_id=? AND substr(visit_date,1,10)=?", [$jobId, $date]);
    $me = function_exists('user_name') ? user_name(current_user()) : '';
    if ($row) {
        db()->prepare("UPDATE job_visits SET status='DONE', report_link=?, report_doc_id=?, closed_by=?, closed_at=? WHERE id=?")
            ->execute([$reportLink, $reportDocId ?: null, $me, date('c'), (int)$row['id']]);
    } else {
        $insp = (int)ops_val("SELECT inspector_id FROM jobs WHERE id=?", [$jobId]);
        db()->prepare("INSERT INTO job_visits (job_id,visit_date,inspector_id,status,report_link,report_doc_id,closed_by,closed_at,note) VALUES (?,?,?, 'DONE', ?,?,?,?, '')")
            ->execute([$jobId, $date, $insp ?: null, $reportLink, $reportDocId ?: null, $me, date('c')]);
    }
    return '';
}

// Working visit days still open — a multi-day job cannot close while any remain.
function job_visits_open_days($job) {
    $jobId = (int)($job['id'] ?? 0);
    $visits = job_visits($jobId);
    if (count($visits) < 2) return [];                     // single-day handled by the normal close gate
    $open = [];
    foreach ($visits as $v) {
        $d = substr((string)$v['visit_date'], 0, 10);
        $working = function_exists('is_working_day') ? is_working_day($d, $job['executing_office_id'] ?? null) : true;
        if ($working && ($v['status'] ?? 'PLANNED') !== 'DONE') $open[] = $d;
    }
    return $open;
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
        'force_dates'              => implode(',', (array)($b['force_dates'] ?? [])),
        'manmonth_basis'           => (string)($b['manmonth_basis'] ?? ''),
        'manmonth_min_days'        => (int)($b['manmonth_min_days'] ?? 0),
    ];
    $office = (int)($b['office'] ?? 0) ?: null;
    $client = (int)($b['client'] ?? 0) ?: null;
    $r = sched_resolve($row, $office, null, $client);
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
        // The Sundays and holidays the run stepped over, so any of them can be
        // pulled back in, and the man-month working for a posting.
        'skipped'   => $r['skipped'] ?? [],
        'forced'    => $r['forced'] ?? [],
        'manmonths' => $r['manmonths'] ?? [],
        'claimable' => $r['claimable'] ?? null,
        'basis'     => $r['basis'] ?? '',
        'minDays'   => $r['min_days'] ?? 0,
        'basisFrom' => ($r['type'] === 'MONTHLY') ? manmonth_source($row, $client) : '',
    ]);
    return true;
}
