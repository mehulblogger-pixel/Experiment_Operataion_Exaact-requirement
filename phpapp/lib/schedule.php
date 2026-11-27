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
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
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
    //  TPIA Phase 1 / D8 (owner, 25 Nov 2026) — a planned day on which no
    //  inspection happened is closed as NO_INSPECTION with a reason from the
    //  editable list below and a note; the planned date stays as it was. A new
    //  date, when one is agreed, is a NEW planned day that records the day it
    //  was moved from (moved_from), so both stay in History.
    ensure_column('job_visits', 'reason_code', "VARCHAR(24) DEFAULT ''");
    ensure_column('job_visits', 'reason_note', "VARCHAR(400) DEFAULT ''");
    ensure_column('job_visits', 'moved_from', "VARCHAR(20) DEFAULT ''");
    if (function_exists('lk_ensure_type_map')) lk_ensure_type_map('visit_no_insp_reason', 'No-inspection reason (visit day)', VISIT_NO_INSP_REASONS, 'tosrm');
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
    static $epoch = -1;
    // Phase 3 · M3 — every cache in this application must be keyed on db_epoch().
    // One database per tenant means the connection under our feet changes between
    // requests, and a static array that does not notice is a cross-tenant leak —
    // exactly the class of defect proved in Phase 2 M2. This cache had no epoch,
    // so a cron run or a test process that touched two workspaces would answer the
    // second one with the first one's public holidays. M3 computes approval due
    // dates from this table, so M3 fixes it.
    if ($epoch !== db_epoch()) { $cache = []; $epoch = db_epoch(); }
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
                  . rtrim(rtrim(nf((float)($r['claimable'] ?? 0), 2), '0'), '.')
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
                          FROM jobs WHERE inspector_id=? AND id<>? AND COALESCE(closed_flag,0)=0"
                          . (function_exists('res_job_holds_sql') ? " AND " . res_job_holds_sql() : ''),
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
    //  Stage 1 B4 — the one classification (lib/workforce.php): field team + coordinators,
    //  company-controlled. An external person is confirmed with the agency, not offered as free.
    if (function_exists('res_company_sql')) { $w[] = res_company_sql(); $w[] = res_resource_sql(); }
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
        //  Stage 1 B5 — the same eligibility warning the allocate picker shows.
        $el = function_exists('inspector_eligibility') ? inspector_eligibility((int)$r['id'], ['on_date' => $date, 'sbu' => $sbu])['status'] : 'ELIGIBLE';
        $out[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'elig' => $el,
                  'elig_label' => $el !== 'ELIGIBLE' && function_exists('inspector_eligibility_pill') ? inspector_eligibility_pill($el)[0] : ''];
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
    // Dates that are no longer part of the engagement stop being visits — unless
    // the day was already worked and closed with its report: that is an earned,
    // billable man-day and evidence, and moving the schedule must not erase it.
    // Likewise a day closed as "No inspection" (its planned date, reason and
    // note are the record) and a day added in its place (moved_from) are kept.
    $done = array_map(function ($v) { return (int)$v['id']; }, array_filter(array_values($have),
        fn($v) => in_array(($v['status'] ?? ''), ['DONE', 'NO_INSPECTION'], true) || trim((string)($v['moved_from'] ?? '')) !== ''));
    $gone = array_diff(array_map(function ($v) { return (int)$v['id']; }, array_values($have)), $keep, $done);
    foreach ($gone as $id) db()->prepare("DELETE FROM job_visits WHERE id=?")->execute([$id]);
    return ops_all("SELECT * FROM job_visits WHERE job_id=? ORDER BY visit_date", [$jobId]);
}

function job_visits($jobId) {
    try { return ops_all("SELECT v.*, i.name inspector_name FROM job_visits v
                          LEFT JOIN inspectors i ON i.id=v.inspector_id
                          WHERE v.job_id=? ORDER BY v.visit_date", [(int)$jobId]); }
    catch (Throwable $e) { return []; }
}

// Reasons a planned day saw no inspection (TPIA D8). STARTING values only — the
// list is the "No-inspection reason (visit day)" master (Masters → lookups), so a
// company renames, adds or removes entries without a code change.
const VISIT_NO_INSP_REASONS = ['MATERIAL_NOT_READY' => 'Material not ready', 'POSTPONED_MFR' => 'Postponed by manufacturer',
    'POSTPONED_CLIENT' => 'Postponed by client', 'CANCELLED_CLIENT' => 'Cancelled by client',
    'ACCESS_REFUSED' => 'Access refused', 'OTHER' => 'Other'];
function visit_no_insp_reasons() {
    return function_exists('lk_options_or') ? lk_options_or('visit_no_insp_reason', VISIT_NO_INSP_REASONS) : VISIT_NO_INSP_REASONS;
}
// A visit day's status in words.
function visit_status_label($v) {
    $st = strtoupper((string)($v['status'] ?? 'PLANNED'));
    if ($st === 'DONE') return 'Done';
    if ($st === 'NO_INSPECTION') {
        $r = visit_no_insp_reasons()[$v['reason_code'] ?? ''] ?? (string)($v['reason_code'] ?? '');
        return 'No inspection' . ($r !== '' ? ' — ' . $r : '') . (trim((string)($v['reason_note'] ?? '')) !== '' ? ' (' . trim((string)$v['reason_note']) . ')' : '');
    }
    return trim((string)($v['moved_from'] ?? '')) !== '' ? 'Planned (moved from ' . (function_exists('fdate') ? fdate($v['moved_from']) : $v['moved_from']) . ')' : 'Planned';
}

// The report that closes one visit day (TPIA Phase 1 item 5 — the day's OWN
// report, not "the latest report on the job" as before). Returns [docId, why]:
// docId 0 with a plain reason when there is none.
//   One report per inspection day / daily: a report on this job dated that day
//     (and, when the day has its own inspector, that inspector's or unnamed).
//   Alternate / weekly / fortnightly / monthly / every N days: the report of the
//     fixed period the day falls in (periods from the job start date).
//   Anything else (one report for the job): any report on the job, as before.
function job_visit_day_report(array $job, $date, $visitInspector = 0) {
    $jobId = (int)$job['id'];
    try { $reps = ops_all("SELECT id, irn, inspection_date, issue_date, created_at, inspector_id, type_code FROM report_docs
                           WHERE job_id=? AND COALESCE(deleted,0)=0 AND UPPER(COALESCE(type_code,'')) NOT IN ('RN','IRN') ORDER BY id DESC", [$jobId]) ?: []; }
    catch (Throwable $e) { return [0, 'Reports are not in use here.']; }
    $freq = strtoupper(trim((string)($job['reporting_frequency'] ?? '')));
    $fd = function ($d) { return function_exists('fdate') ? fdate($d) : $d; };
    if (in_array($freq, ['PER_DAY', 'DAILY'], true)) {
        foreach ($reps as $r) {
            if (report_date_of($r) !== $date) continue;
            if ($visitInspector && (int)($r['inspector_id'] ?? 0) && (int)$r['inspector_id'] !== (int)$visitInspector) continue;
            return [(int)$r['id'], ''];
        }
        $n = 0; $k = 0;
        foreach (job_visits($jobId) as $v) { if (strtoupper((string)($v['status'] ?? '')) === 'NO_INSPECTION') continue; $k++; if (substr((string)$v['visit_date'], 0, 10) === $date) { $n = $k; break; } }
        return [0, ($n ? 'Day ' . $n . ' (' . $fd($date) . ')' : $fd($date)) . ' needs its own report: a ' . Tl('report') . ' on this ' . Tl('job')
                 . ' dated ' . $fd($date) . ($visitInspector ? ' by that day\'s ' . Tl('engineer') : '') . ', or paste the report link. Another day\'s report does not close it.'];
    }
    $start = substr(trim((string)($job['inspection_start_date'] ?? '')) ?: trim((string)($job['scheduled_date'] ?? '')), 0, 10);
    $periodic = isset(REPORT_PERIOD_DAYS[$freq]) || $freq === 'MONTHLY' || ($freq === 'CUSTOM' && (int)($job['report_custom_days'] ?? 0) > 0);
    if ($periodic && $start !== '' && $date >= $start) {
        foreach (report_periods($freq, $start, $date, (int)($job['report_custom_days'] ?? 0)) as [$from, $to]) {
            if ($date < $from || $date > $to) continue;
            foreach ($reps as $r) { $d = report_date_of($r); if ($d >= $from && $d <= $to) return [(int)$r['id'], '']; }
            // The period's report may have been filed late (week 1's report on day 9):
            // the one reading of what the job owes says whether this period is covered.
            $ob = ops_report_obligations($job);
            foreach ($ob['items'] as $it) {
                if (!$it['met'] || $date < $it['from'] || $date > $it['to']) continue;
                foreach ($reps as $r) if ((string)($r['irn'] ?? '') !== '' && (string)$r['irn'] === (string)$it['by']) return [(int)$r['id'], ''];
                return [-1, ''];                                   // covered by a link / the upload date
            }
            return [0, 'The report for ' . $fd($from) . ($to !== $from ? ' to ' . $fd($to) : '') . ' (the period this day falls in) is not on this '
                     . Tl('job') . ' yet. File it first, or paste the report link.'];
        }
    }
    if ($reps) return [(int)$reps[0]['id'], ''];
    return [0, 'A report (a link, or an inspection report on this ' . Tl('job') . ') is required before a day can be closed.'];
}

// §WO-8 — close one day of a multi-day job with ITS report (TPIA item 5): a link,
// or the day's own report on the job. A job that owes no reports ("No report",
// TPIA item 6) closes its days without one. Creates the visit row lazily if the
// schedule was never synced. A day already closed (done or "No inspection") is
// never overwritten.
function job_visit_close($jobId, $date, $reportLink = '', $reportDocId = 0) {
    $jobId = (int)$jobId; $date = substr((string)$date, 0, 10);
    if (!$jobId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return 'That is not a valid visit date.';
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
    if (!$job) return 'That ' . Tl('job') . ' no longer exists.';
    $reportLink = trim((string)$reportLink);
    $row = ops_one("SELECT * FROM job_visits WHERE job_id=? AND substr(visit_date,1,10)=?", [$jobId, $date]);
    $st = strtoupper((string)($row['status'] ?? ''));
    if ($st === 'DONE') return 'That day is already closed.';
    if ($st === 'NO_INSPECTION') return 'That day was already closed as "No inspection".';
    $noReport = in_array(strtoupper(trim((string)($job['reporting_frequency'] ?? ''))), ['NOREPORT'], true);
    if (!$noReport && $reportLink === '' && !$reportDocId) {
        [$reportDocId, $why] = job_visit_day_report($job, $date, (int)($row['inspector_id'] ?? 0));
        if (!$reportDocId) return $why;
    }
    $me = function_exists('user_name') ? user_name(current_user()) : '';
    $reportDocId = (int)$reportDocId > 0 ? (int)$reportDocId : 0;    // -1 = covered by a link / upload date
    if ($row) {
        db()->prepare("UPDATE job_visits SET status='DONE', report_link=?, report_doc_id=?, closed_by=?, closed_at=? WHERE id=?")
            ->execute([$reportLink, $reportDocId ?: null, $me, date('c'), (int)$row['id']]);
    } else {
        $insp = (int)($job['inspector_id'] ?? 0);
        db()->prepare("INSERT INTO job_visits (job_id,visit_date,inspector_id,status,report_link,report_doc_id,closed_by,closed_at,note) VALUES (?,?,?, 'DONE', ?,?,?,?, '')")
            ->execute([$jobId, $date, $insp ?: null, $reportLink, $reportDocId ?: null, $me, date('c')]);
    }
    return '';
}

// TPIA Phase 1 / D8 — close a planned day as "No inspection": the reason (from the
// editable list), a note (required for "Other"), and optionally the new date the
// visit moves to. The planned day keeps its date; the new date is a NEW planned
// day carrying moved_from, and the move is written to the assignment history.
// No report is owed for the day and it is never billed (only DONE days earn).
function job_visit_no_inspection($jobId, $date, $reason, $note = '', $newDate = '') {
    $jobId = (int)$jobId; $date = substr((string)$date, 0, 10);
    $reason = strtoupper(trim((string)$reason)); $note = trim((string)$note); $newDate = substr(trim((string)$newDate), 0, 10);
    if (!$jobId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return 'That is not a valid visit date.';
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
    if (!$job) return 'That ' . Tl('job') . ' no longer exists.';
    $reasons = visit_no_insp_reasons();
    if ($reason === '' || !isset($reasons[$reason])) return 'Pick why no inspection happened.';
    if ($reason === 'OTHER' && mb_strlen($note) < 3) return 'Say what happened (a short note is needed for "Other").';
    if (mb_strlen($note) > 400) $note = mb_substr($note, 0, 400);
    $row = ops_one("SELECT * FROM job_visits WHERE job_id=? AND substr(visit_date,1,10)=?", [$jobId, $date]);
    $st = strtoupper((string)($row['status'] ?? 'PLANNED'));
    if ($st === 'DONE') return 'That day is already closed with its report.';
    if ($st === 'NO_INSPECTION') return 'That day was already closed as "No inspection".';
    if ($newDate !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) return 'The new date is not a valid date.';
        if ($newDate <= $date) return 'The new date must be after ' . (function_exists('fdate') ? fdate($date) : $date) . '.';
        if (ops_one("SELECT id FROM job_visits WHERE job_id=? AND substr(visit_date,1,10)=?", [$jobId, $newDate]))
            return (function_exists('fdate') ? fdate($newDate) : $newDate) . ' is already a visit day on this ' . Tl('job') . '.';
    }
    $me = function_exists('user_name') ? user_name(current_user()) : '';
    $insp = (int)($row['inspector_id'] ?? 0) ?: (int)($job['inspector_id'] ?? 0);
    if ($row) {
        db()->prepare("UPDATE job_visits SET status='NO_INSPECTION', reason_code=?, reason_note=?, closed_by=?, closed_at=? WHERE id=?")
            ->execute([$reason, $note, $me, date('c'), (int)$row['id']]);
    } else {
        db()->prepare("INSERT INTO job_visits (job_id,visit_date,inspector_id,status,reason_code,reason_note,closed_by,closed_at,note) VALUES (?,?,?, 'NO_INSPECTION', ?,?,?,?, '')")
            ->execute([$jobId, $date, $insp ?: null, $reason, $note, $me, date('c')]);
    }
    $fd = function ($d) { return function_exists('fdate') ? fdate($d) : $d; };
    $words = 'Visit day ' . $fd($date) . ' closed as No inspection — ' . $reasons[$reason] . ($note !== '' ? ': ' . $note : '');
    if ($newDate !== '') {
        db()->prepare("INSERT INTO job_visits (job_id,visit_date,inspector_id,status,moved_from,note) VALUES (?,?,?, 'PLANNED', ?, ?)")
            ->execute([$jobId, $newDate, $insp ?: null, $date, mb_substr('Moved from ' . $fd($date) . ' — ' . $reasons[$reason], 0, 255)]);
        $words .= '. New date ' . $fd($newDate) . ' added.';
        if (function_exists('tosrm_assign_event'))
            try { tosrm_assign_event($jobId, 'RESCHEDULE', ['old_date' => $date, 'new_date' => $newDate, 'reason' => $reasons[$reason] . ($note !== '' ? ' — ' . $note : '')]); } catch (Throwable $e) {}
    }
    if (function_exists('act_log')) try { act_log('JOB', $jobId, 'SYSTEM', $words . ' (by ' . $me . ')'); } catch (Throwable $e) {}
    return '';
}

// Working visit days still open — a multi-day job cannot close while any remain.
function job_visits_open_days($job) {
    $jobId = (int)($job['id'] ?? 0);
    $visits = job_visits($jobId);
    if (count($visits) < 2) return [];                     // single-day handled by the normal close gate
    $open = [];
    // After an early end (terminated, completed early, demobilised) the days
    // past the end date are never worked — they cannot hold the job open.
    $stop = substr((string)($job['billing_stop_on'] ?? ''), 0, 10);
    foreach ($visits as $v) {
        $d = substr((string)$v['visit_date'], 0, 10);
        if ($stop !== '' && $d > $stop) continue;
        $working = function_exists('is_working_day') ? is_working_day($d, $job['executing_office_id'] ?? null) : true;
        // A day closed as "No inspection" (TPIA D8) is closed: it owes no report.
        if ($working && !in_array(($v['status'] ?? 'PLANNED'), ['DONE', 'NO_INSPECTION'], true)) $open[] = $d;
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

// ---------------------------------------------------------------------------
//  Report obligations — what a job owes, day by day or period by period
//
//  TPIA reporting Phase 1 (owner, 25 Nov 2026 — items 7, 8, 9, 10; D5, D6).
//  ONE reading of "which reports does this job owe, and which are missing",
//  used by the job and call pages ("Day reports 7 of 10", "next due"), the
//  "Reports to write" figure (lib/opskpi.php) and the reminders — so the card,
//  the list it opens and the e-mail can never disagree. Read-only; nothing here
//  writes, and no report or visit day is ever changed by it.
//
//    No report / blank     owes nothing.
//    One report per        one report for EACH visit day, dated that day (the
//    inspection day        day's own report: a day-1 report never covers day 4).
//                          A "No inspection" day owes none.
//    Daily, alternate day, fixed periods counted from the job START date —
//    weekly, fortnightly,  1, 2, 7, 14 or N days; monthly by the start date's
//    custom (every N days) day of the month — up to the end date (or the early
//    monthly               end). A late report counts for the oldest period
//                          still open, and the next period's due date never
//                          moves (no drift). Daily skips non-working days.
//    Anything else         (an older value such as SINGLE / ONCE) owes one
//                          report, as before.
//
//  A report counts once it has been SUBMITTED (or is further on: at vetting,
//  under review, approved, issued). A draft or a report sent back is still to
//  write. A Release Note is not a report for this purpose, and a revision that
//  was replaced by a later one is counted once (the latest). A day closed with
//  a report link, and the job's own "report uploaded" date, count too.
// ---------------------------------------------------------------------------
const REPORT_PERIOD_DAYS = ['DAILY' => 1, 'ALTERNATE' => 2, 'WEEKLY' => 7, 'FORTNIGHTLY' => 14];
const REPORT_WRITTEN_STATES = ['SUBMITTED', 'VETTING', 'UNDER_REVIEW', 'APPROVED', 'ISSUED'];

function report_written($r) {
    return !empty($r['finalized']) || in_array(strtoupper((string)($r['status'] ?? '')), REPORT_WRITTEN_STATES, true);
}

// Reports and visit days of many jobs in three queries — the figure is worked
// out for every open job at once, so this must not be one query per job.
function report_evidence_batch(array $jobIds) {
    $jobIds = array_values(array_unique(array_filter(array_map('intval', $jobIds))));
    $out = [];
    foreach ($jobIds as $id) $out[$id] = ['reports' => [], 'visits' => []];
    foreach (array_chunk($jobIds, 400) as $chunk) {
        $in = implode(',', $chunk);
        try {
            foreach (ops_all("SELECT id, job_id, irn, type_code, status, finalized, inspection_date, issue_date, created_at, inspector_id, revised_by_id
                              FROM report_docs WHERE COALESCE(deleted,0)=0 AND job_id IN ($in) ORDER BY id") ?: [] as $r)
                $out[(int)$r['job_id']]['reports'][] = $r;
        } catch (Throwable $e) { /* reports not in use on this install */ }
        try {
            foreach (ops_all("SELECT * FROM job_visits WHERE job_id IN ($in) ORDER BY visit_date, id") ?: [] as $v)
                $out[(int)$v['job_id']]['visits'][] = $v;
        } catch (Throwable $e) {}
    }
    return $out;
}

function report_date_of($r) {
    $d = trim((string)($r['inspection_date'] ?? '')) ?: trim((string)($r['issue_date'] ?? '')) ?: substr((string)($r['created_at'] ?? ''), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}/', $d) ? substr($d, 0, 10) : '';
}

// The periods of a periodic frequency: [[from, to], …] from $start to $end.
function report_periods($freq, $start, $end, $customDays = 0, $officeId = null, array $skipDays = []) {
    $freq = strtoupper((string)$freq);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$end) || $end < $start) return [];
    $len = REPORT_PERIOD_DAYS[$freq] ?? ($freq === 'CUSTOM' ? max(0, (int)$customDays) : 0);
    $out = [];
    if ($freq === 'MONTHLY') {
        // By the start date's day of the month (owner D6: "Start date"): a posting
        // that starts on the 10th reports 10 Jan–9 Feb, 10 Feb–9 Mar, … A start on
        // the 31st runs to the last day of a shorter month.
        $sd = (int)substr($start, 8, 2); $y = (int)substr($start, 0, 4); $m = (int)substr($start, 5, 2);
        $at = function ($k) use ($sd, $y, $m) {
            $mm = $m + $k; $yy = $y + intdiv($mm - 1, 12); $mm = (($mm - 1) % 12) + 1;
            $dim = (int)date('t', mktime(0, 0, 0, $mm, 1, $yy));
            return sprintf('%04d-%02d-%02d', $yy, $mm, min($sd, $dim));
        };
        for ($k = 0; $k < 400; $k++) {
            $from = $at($k); if ($from > $end) break;
            $to = date('Y-m-d', strtotime($at($k + 1) . ' -1 day'));
            $out[] = [$from, min($to, $end)];
        }
        return $out;
    }
    if ($len <= 0) return [];
    $t = strtotime($start); $endT = strtotime($end);
    for ($k = 0; $k < 2000 && $t <= $endT; $k++) {
        $from = date('Y-m-d', $t);
        $to = date('Y-m-d', min($endT, strtotime("+" . ($len - 1) . " day", $t)));
        $t = strtotime("+$len day", $t);
        if ($len === 1) {
            if (isset($skipDays[$from])) continue;
            if (function_exists('is_working_day') && !is_working_day($from, $officeId)) continue;
        }
        $out[] = [$from, $to];
    }
    return $out;
}

// What the job owes. $ev = this job's entry from report_evidence_batch() (fetched
// when not given). Returns:
//   kind       NONE | DAY (one per inspection day) | PERIOD | LEGACY (one report)
//   items      [from, to, met, by] — `to` is the due date
//   total, due (to ≤ today), met, missing (due and not met), overdue (missing
//   with to < today), next_due (the first unmet item's due date), oldest_missing,
//   last_report (date of the latest report counted)
function ops_report_obligations(array $job, $today = null, $ev = null) {
    $today = $today ?: date('Y-m-d');
    $freq = strtoupper(trim((string)($job['reporting_frequency'] ?? '')));
    $res = ['kind' => 'NONE', 'freq' => $freq, 'items' => [], 'total' => 0, 'due' => 0, 'met' => 0, 'missing' => 0,
            'overdue' => 0, 'next_due' => '', 'oldest_missing' => '', 'last_report' => ''];
    if ($freq === '' || $freq === 'NOREPORT') return $res;
    $jid = (int)($job['id'] ?? 0);
    if ($ev === null) { $b = report_evidence_batch([$jid]); $ev = $b[$jid] ?? ['reports' => [], 'visits' => []]; }
    $office = $job['executing_office_id'] ?? null;
    $stop = substr(trim((string)($job['billing_stop_on'] ?? '')), 0, 10);
    // The reports that count, oldest first.
    $written = []; $undated = '';
    foreach ($ev['reports'] as $r) {
        if (in_array(strtoupper((string)($r['type_code'] ?? '')), ['RN', 'IRN'], true)) continue;
        if ((int)($r['revised_by_id'] ?? 0) > 0) continue;            // replaced by a later revision
        if (!report_written($r)) continue;
        $d = report_date_of($r);
        if ($d === '') { $undated = (string)($r['irn'] ?? '') ?: 'report'; continue; }   // counts only for a one-report job
        $written[] = ['date' => $d, 'by' => (string)($r['irn'] ?? ''), 'inspector_id' => (int)($r['inspector_id'] ?? 0)];
    }
    usort($written, fn($a, $b) => strcmp($a['date'], $b['date']));
    $visits = []; $noInsp = [];
    foreach ($ev['visits'] as $v) {
        $d = substr((string)($v['visit_date'] ?? ''), 0, 10); if ($d === '') continue;
        if (strtoupper((string)($v['status'] ?? '')) === 'NO_INSPECTION') { $noInsp[$d] = true; continue; }
        $visits[$d] = $v;
    }
    $linkDays = [];
    foreach ($visits as $d => $v) if (strtoupper((string)($v['status'] ?? '')) === 'DONE' && trim((string)($v['report_link'] ?? '')) !== '') $linkDays[$d] = true;
    $upload = substr(trim((string)($job['report_upload_date'] ?? '')), 0, 10);
    $start = substr(trim((string)($job['inspection_start_date'] ?? '')) ?: trim((string)($job['scheduled_date'] ?? '')), 0, 10);
    if ($start === '' && $visits) $start = array_key_first($visits);
    $items = [];
    $periodic = isset(REPORT_PERIOD_DAYS[$freq]) || $freq === 'MONTHLY' || ($freq === 'CUSTOM' && (int)($job['report_custom_days'] ?? 0) > 0);
    if ($periodic && $start === '') $periodic = false;          // no start date: one report, as before
    if ($freq === 'PER_DAY') {
        $res['kind'] = 'DAY';
        $days = array_keys($visits);
        if (!$days && !$noInsp) {
            $r = function_exists('sched_resolve') ? sched_resolve($job, $office) : ['dates' => []];
            $days = (array)($r['dates'] ?? []);
            if (!$days && $start !== '') $days = [$start];
        }
        foreach ($days as $d) {
            if ($stop !== '' && $d > $stop) continue;
            if (count($visits) > 1 && function_exists('is_working_day') && !is_working_day($d, $office)) continue;
            $who = (int)($visits[$d]['inspector_id'] ?? 0);
            $by = '';
            foreach ($written as $w) if ($w['date'] === $d && (!$who || !$w['inspector_id'] || $w['inspector_id'] === $who)) { $by = $w['by']; break; }
            if ($by === '' && isset($linkDays[$d])) $by = 'report link';
            if ($by === '' && $upload !== '' && $upload === $d) $by = 'report uploaded';
            $items[] = ['from' => $d, 'to' => $d, 'met' => $by !== '', 'by' => $by];
        }
    } elseif ($periodic) {
        $res['kind'] = 'PERIOD';
        $end = substr(trim((string)($job['inspection_end_date'] ?? '')), 0, 10);
        if ($end === '' && $visits) $end = array_key_last($visits);
        if ($end === '' || $end < $start) $end = $start;
        if ($stop !== '' && $stop < $end) $end = max($start, $stop);
        $openEnded = substr(trim((string)($job['inspection_end_date'] ?? '')), 0, 10) === '' && !$visits;
        $periods = report_periods($freq, $start, $end, (int)($job['report_custom_days'] ?? 0), $office, $noInsp);
        //  An open-ended job (no end date, no visit days yet) owes its first day's report. When that
        //  first day is a day off (e.g. a Sunday), the first WORKING day on or after it is owed — not
        //  nothing (found 27 Nov 2026: a daily job starting on a Sunday never showed in Reports to write).
        if (!$periods && $openEnded && $end === $start && function_exists('is_working_day')) {
            for ($k = 1, $d = $start; $k <= 14; $k++) {
                $d = date('Y-m-d', strtotime($start . " +$k day"));
                if ($d > $today) break;
                if (!isset($noInsp[$d]) && is_working_day($d, $office)) {
                    $periods = report_periods($freq, $d, $d, (int)($job['report_custom_days'] ?? 0), $office, $noInsp);
                    break;
                }
            }
        }
        foreach ($periods as [$from, $to]) {
            // A period whose every planned visit day was "No inspection" owes no report.
            $hasV = false; $hasNo = false;
            foreach ($visits as $d => $v) if ($d >= $from && $d <= $to) { $hasV = true; break; }
            foreach ($noInsp as $d => $x) if ($d >= $from && $d <= $to) { $hasNo = true; break; }
            if ($hasNo && !$hasV) continue;
            $items[] = ['from' => $from, 'to' => $to, 'met' => false, 'by' => ''];
        }
        // Each report (or link / upload) fills the OLDEST period still open that
        // has begun by its date — so week 1's report filed on day 9 is week 1's,
        // and week 2 stays due on day 14.
        $evd = $written;
        foreach (array_keys($linkDays) as $d) $evd[] = ['date' => $d, 'by' => 'report link'];
        if ($upload !== '') $evd[] = ['date' => $upload, 'by' => 'report uploaded'];
        usort($evd, fn($a, $b) => strcmp($a['date'], $b['date']));
        foreach ($evd as $w) {
            foreach ($items as $i => $it) {
                if ($it['met']) continue;
                if ($it['from'] <= $w['date'] || $i === 0) { $items[$i]['met'] = true; $items[$i]['by'] = $w['by']; }
                break;
            }
        }
    } else {
        $res['kind'] = 'LEGACY';
        $by = $written ? $written[count($written) - 1]['by'] : ($undated !== '' ? $undated : ($upload !== '' ? 'report uploaded' : ($linkDays ? 'report link' : '')));
        $items[] = ['from' => $start, 'to' => $start !== '' && $start < $today ? $start : $today, 'met' => $by !== '', 'by' => $by];
    }
    $res['items'] = $items;
    foreach ($items as $it) {
        $res['total']++;
        if ($it['met']) $res['met']++;
        if ($it['to'] <= $today) {
            $res['due']++;
            if (!$it['met']) {
                $res['missing']++;
                if ($res['oldest_missing'] === '') $res['oldest_missing'] = $it['to'];
                if ($it['to'] < $today) $res['overdue']++;
            }
        }
        if (!$it['met'] && $res['next_due'] === '') $res['next_due'] = $it['to'];
    }
    if ($written) $res['last_report'] = $written[count($written) - 1]['date'];
    return $res;
}

// The jobs, among the given ones, that have a report missing today.
function ops_jobs_missing_reports(array $jobs, $today = null) {
    $ev = report_evidence_batch(array_column($jobs, 'id'));
    $out = [];
    foreach ($jobs as $j) {
        $o = ops_report_obligations($j, $today, $ev[(int)$j['id']] ?? ['reports' => [], 'visits' => []]);
        if ($o['missing'] > 0) $out[(int)$j['id']] = $o;
    }
    return $out;
}

// One plain line for the job and call pages.
function ops_report_progress_line(array $job, $today = null) {
    if (!empty($job['id']) && !array_key_exists('reporting_frequency', $job))
        $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)$job['id']]) ?: $job;
    $o = ops_report_obligations($job, $today);
    if ($o['kind'] === 'NONE') return '';
    $f = function ($d) { return $d !== '' && function_exists('fdate') ? fdate($d) : $d; };
    if ($o['kind'] === 'DAY') {
        $s = 'Day reports: ' . $o['met'] . ' of ' . $o['total'];
        if ($o['missing']) $s .= ' · ' . $o['missing'] . ' day' . ($o['missing'] === 1 ? '' : 's') . ' still without a report (oldest ' . $f($o['oldest_missing']) . ')';
        return $s;
    }
    if ($o['kind'] === 'PERIOD') {
        $lab = (defined('REPORT_FREQ') ? (REPORT_FREQ[$o['freq']] ?? $o['freq']) : $o['freq']);
        $s = $lab . ' reports: ' . $o['met'] . ' of ' . $o['total'];
        if ($o['last_report'] !== '') $s .= ' · last ' . $f($o['last_report']);
        if ($o['next_due'] !== '') $s .= ' · next due ' . $f($o['next_due']);
        if ($o['overdue']) $s .= ' · OVERDUE since ' . $f($o['oldest_missing']);
        return $s;
    }
    return $o['met'] ? 'Report: written' : 'Report: still to write';
}
