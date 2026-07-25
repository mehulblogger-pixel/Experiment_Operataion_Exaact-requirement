<?php
// ============================================================================
//  Workforce — daily inspector availability board, weekly-working-day config,
//  and the 8.5-hour daily working-hours cap.
//  Reuses the existing inspectors / jobs / voucher_entries tables.
// ============================================================================

function avail_status_options() { return lk_options_or('avail_status', AVAIL_STATUS); }
function avail_label($code) { $o = avail_status_options(); return $o[$code] ?? ($code ?: '—'); }
// Tone for the pill / card colour.
function avail_tone($code) {
    switch ($code) {
        case 'AVAILABLE': return 'ok';
        case 'ON_JOB':    return 'info';
        case 'LEAVE':     return 'bad';
        case 'HALF_DAY':  return 'warn';
        case 'TRAINING': case 'WFH': case 'TRAVEL': case 'OFFICE': return 'warn';
    }
    return 'mut';
}

// Weekly working days for a person: 5 (Mon–Fri), 5.5 (alternate Saturdays off),
// or 6 (Mon–Sat). Returns working days in the given month after Sundays + holidays,
// then removes Saturdays (or half of them) to match the person's pattern.
function working_days_for($weekly, $year = null, $month = null) {
    $year = $year ?: (int)date('Y'); $month = $month ?: (int)date('n');
    $base = working_days_in_month($year, $month);   // days − Sundays − holidays (Mon–Sat)
    $weekly = (float)$weekly;
    if ($weekly >= 6 || $weekly <= 0) return $base;
    // count Saturdays in the month
    $days = (int)date('t', mktime(0, 0, 0, $month, 1, $year)); $sat = 0;
    for ($d = 1; $d <= $days; $d++) if ((int)date('w', mktime(0, 0, 0, $month, $d, $year)) === 6) $sat++;
    $remove = $weekly <= 5 ? $sat : (int)floor($sat / 2);   // 5 → all Sat off; 5.5 → alternate Sat off
    $wd = $base - $remove;
    return $wd > 0 ? $wd : $base;
}
// The standard working norm (weekly days + hours) for a designation at an office.
// Resolution is most-specific first: (designation, office) → (designation, any) →
// (office default, i.e. designation '') → (global '' + any) → hard default 6d/48h.
function work_norm($designation, $officeId = null) {
    $designation = (string)$designation; $officeId = $officeId ? (int)$officeId : null;
    $tries = [];
    if ($designation !== '' && $officeId) $tries[] = ['designation' => $designation, 'office_id' => $officeId];
    if ($designation !== '')              $tries[] = ['designation' => $designation, 'office_id' => null];
    if ($officeId)                        $tries[] = ['designation' => '',           'office_id' => $officeId];
    $tries[]                                        = ['designation' => '',           'office_id' => null];
    foreach ($tries as $t) {
        if ($t['office_id'] === null)
            $r = ops_one("SELECT weekly_days, weekly_hours FROM work_norms WHERE designation=? AND office_id IS NULL", [$t['designation']]);
        else
            $r = ops_one("SELECT weekly_days, weekly_hours FROM work_norms WHERE designation=? AND office_id=?", [$t['designation'], $t['office_id']]);
        if ($r) return ['weekly_days' => (float)$r['weekly_days'], 'weekly_hours' => (float)$r['weekly_hours']];
    }
    return ['weekly_days' => 6.0, 'weekly_hours' => 48.0];
}
// Effective weekly working days for an inspector: their own value if explicitly set
// (non-default), otherwise the designation×office norm.
function inspector_weekly_days($ins) {
    $w = (float)($ins['weekly_working_days'] ?? 0);
    if ($w > 0 && abs($w - 6) > 0.001) return $w;   // person explicitly chose 5 or 5.5
    $norm = work_norm($ins['designation'] ?? '', $ins['home_office_id'] ?? null);
    return $norm['weekly_days'] ?: ($w > 0 ? $w : 6);
}

// ---- Work-norms master editor -------------------------------------------
function ops_work_norms($method) {
    ops_require(is_master() || can('master.manage'), 'You cannot edit working norms.');
    $pdo = db();
    if ($method === 'POST') {
        $do = $_POST['_do'] ?? 'save';
        if ($do === 'del') {
            $pdo->prepare("DELETE FROM work_norms WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
            flash('Working norm removed.');
            redirect('/work-norms');
        }
        $desig = trim($_POST['designation'] ?? '');
        $office = ($_POST['office_id'] ?? '') !== '' ? (int)$_POST['office_id'] : null;
        $wd = (float)($_POST['weekly_days'] ?? 6); if ($wd <= 0 || $wd > 7) $wd = 6;
        $wh = (float)($_POST['weekly_hours'] ?? 48); if ($wh <= 0 || $wh > 84) $wh = 48;
        // upsert on (designation, office)
        if ($office === null)
            $ex = ops_one("SELECT id FROM work_norms WHERE designation=? AND office_id IS NULL", [$desig]);
        else
            $ex = ops_one("SELECT id FROM work_norms WHERE designation=? AND office_id=?", [$desig, $office]);
        if ($ex) $pdo->prepare("UPDATE work_norms SET weekly_days=?, weekly_hours=?, updated_by=?, updated_at=? WHERE id=?")
            ->execute([$wd, $wh, user_name(current_user()), date('c'), $ex['id']]);
        else $pdo->prepare("INSERT INTO work_norms (designation,office_id,weekly_days,weekly_hours,updated_by,updated_at) VALUES (?,?,?,?,?,?)")
            ->execute([$desig, $office, $wd, $wh, user_name(current_user()), date('c')]);
        flash('Working norm saved.');
        redirect('/work-norms');
    }
    $rows = ops_all("SELECT w.*, o.name office_name FROM work_norms w LEFT JOIN offices o ON o.id=w.office_id ORDER BY w.designation, o.name");
    view('ops/work_norms', ['rows' => $rows,
        'offices' => ops_all("SELECT id, name FROM offices ORDER BY is_ahmedabad DESC, name"),
        'designations' => lk_options_or('designation', DESIGNATIONS)]);
    return true;
}

// -------------------------------------------------------------------------
//  Availability board
// -------------------------------------------------------------------------
// Inspector ids that are occupied by an active (open) job on $day.
function inspectors_on_job($day) {
    $rows = ops_all("SELECT DISTINCT inspector_id FROM jobs
        WHERE inspector_id IS NOT NULL AND closed_flag=0 AND (
            scheduled_date = ?
            OR (inspection_start_date <> '' AND inspection_start_date <= ? AND (inspection_end_date='' OR inspection_end_date >= ?))
            OR (scheduled_date <> '' AND scheduled_date <= ? AND inspection_end_date <> '' AND inspection_end_date >= ?)
        )", [$day, $day, $day, $day, $day]);
    $ids = [];
    foreach ($rows as $r) $ids[(int)$r['inspector_id']] = true;
    return $ids;
}
// Manual per-day status overrides for a set of inspectors on $day.
function inspector_day_overrides($day) {
    $out = [];
    foreach (ops_all("SELECT inspector_id, status, note FROM inspector_day_status WHERE day=?", [$day]) as $r)
        $out[(int)$r['inspector_id']] = ['status' => $r['status'], 'note' => $r['note']];
    return $out;
}
// Build the availability list for the given offices (int[] | 'ALL') on $day.
// Each row: inspector + effective status (manual override wins, else ON_JOB if on a
// job today, else AVAILABLE) + note + today's job code(s).
function inspector_availability($offices, $day = null) {
    $day = $day ?: date('Y-m-d');
    $where = "status='ACTIVE' AND COALESCE(staff_kind,'ASSET')<>'SUBCON'";
    $args = [];
    if (is_array($offices)) {
        if (!$offices) return [];
        $in = implode(',', array_map('intval', $offices));
        $where .= " AND (home_office_id IN ($in))";
    }
    $ins = ops_all("SELECT id, name, emp_code, home_office_id, sbu, weekly_working_days, mobile, email
        FROM inspectors WHERE $where ORDER BY name", $args);
    if (!$ins) return [];
    $onJob = inspectors_on_job($day);
    $ov = inspector_day_overrides($day);
    // today's job code(s) per inspector (for the "on job" detail)
    $jobs = [];
    foreach (ops_all("SELECT inspector_id, job_code, scheduled_date FROM jobs
        WHERE inspector_id IS NOT NULL AND closed_flag=0 AND (
            scheduled_date=? OR (inspection_start_date<>'' AND inspection_start_date<=? AND (inspection_end_date='' OR inspection_end_date>=?))
        )", [$day, $day, $day]) as $j) $jobs[(int)$j['inspector_id']][] = $j['job_code'];
    $out = [];
    foreach ($ins as $i) {
        $id = (int)$i['id'];
        $manual = $ov[$id]['status'] ?? '';
        $eff = $manual ?: (isset($onJob[$id]) ? 'ON_JOB' : 'AVAILABLE');
        $out[] = $i + [
            'eff_status' => $eff,
            'manual'     => $manual,
            'note'       => $ov[$id]['note'] ?? '',
            'job_codes'  => $jobs[$id] ?? [],
        ];
    }
    return $out;
}
// Summary counts for the dashboard chip.
// ---------------------------------------------------------------------------
//  Looking ahead, not just at today
//
//  A call arrives now and asks for three days next week. The board has to answer
//  two questions the day view cannot: who is free on that date, and for how long
//  are they free from there. These read the same two sources as the day view —
//  allocated deputations, and the coordinator's manual day statuses — so a
//  person is never shown free on a day the board itself calls busy.
// ---------------------------------------------------------------------------

// Every day between two dates, capped so a careless range cannot run away.
function day_span($from, $to, $cap = 120) {
    $a = strtotime((string)$from); $b = strtotime((string)$to);
    if (!$a || !$b || $b < $a) return [];
    $out = []; $t = $a;
    while ($t <= $b && count($out) < $cap) { $out[] = date('Y-m-d', $t); $t = strtotime('+1 day', $t); }
    return $out;
}

// status per inspector per day across a span: [inspectorId][day] => STATUS.
// One query per source for the whole span rather than one per day.
function availability_matrix($offices, $from, $to) {
    $days = day_span($from, $to);
    if (!$days) return [[], [], [], []];
    $people = inspector_availability($offices, $from);       // roster + today's detail
    if (!$people) return [[], $days, [], []];
    $ids = array_map(fn($p) => (int)$p['id'], $people);
    $inIds = implode(',', $ids);
    $first = $days[0]; $last = end($days);

    // Manual statuses the coordinator has set anywhere in the span.
    $manual = [];
    foreach (ops_all("SELECT inspector_id, day, status FROM inspector_day_status
                      WHERE day>=? AND day<=? AND inspector_id IN ($inIds)", [$first, $last]) as $r)
        $manual[(int)$r['inspector_id']][$r['day']] = $r['status'];

    // Days taken by an open deputation — a single scheduled day, a start/end
    // period, or any of the visit dates now held on the deputation.
    $busy = [];
    foreach (ops_all("SELECT inspector_id, job_code, scheduled_date, inspection_start_date, inspection_end_date, inspection_dates
                      FROM jobs WHERE inspector_id IS NOT NULL AND closed_flag=0 AND inspector_id IN ($inIds)") as $j) {
        $iid = (int)$j['inspector_id'];
        $mark = function ($d) use (&$busy, $iid, $j, $first, $last) {
            if ($d === '' || $d < $first || $d > $last) return;
            $busy[$iid][$d] = $j['job_code'];
        };
        $mark((string)$j['scheduled_date']);
        foreach (call_dates_parse($j['inspection_dates'] ?? '') as $d) $mark($d);
        $s = (string)$j['inspection_start_date']; $e = (string)$j['inspection_end_date'];
        if ($s !== '') foreach (day_span(max($s, $first), min($e !== '' ? $e : $s, $last)) as $d) $mark($d);
    }

    $matrix = [];
    foreach ($people as $p) {
        $id = (int)$p['id'];
        foreach ($days as $d) {
            $matrix[$id][$d] = $manual[$id][$d] ?? (isset($busy[$id][$d]) ? 'ON_JOB' : 'AVAILABLE');
        }
    }
    return [$matrix, $days, $people, $busy];
}

// From $start, how many consecutive days is this person free, and what stops
// them? Answers "he is free from the 3rd for four days, then he is on JOB-0142".
function free_window($matrix, $inspectorId, $start, $days) {
    $id = (int)$inspectorId;
    $i = array_search($start, $days, true);
    if ($i === false) return ['free' => false, 'run' => 0, 'until' => '', 'next_busy' => '', 'next_reason' => ''];
    if (($matrix[$id][$start] ?? 'AVAILABLE') !== 'AVAILABLE')
        return ['free' => false, 'run' => 0, 'until' => '', 'next_busy' => $start, 'next_reason' => $matrix[$id][$start] ?? ''];
    $run = 0; $until = $start; $nextBusy = ''; $reason = '';
    for ($k = $i; $k < count($days); $k++) {
        $d = $days[$k];
        if (($matrix[$id][$d] ?? 'AVAILABLE') === 'AVAILABLE') { $run++; $until = $d; }
        else { $nextBusy = $d; $reason = $matrix[$id][$d]; break; }
    }
    return ['free' => true, 'run' => $run, 'until' => $until, 'next_busy' => $nextBusy, 'next_reason' => $reason];
}

// Who is free for EVERY day of a span — the question a coordinator asks when a
// call needs three days from the 10th.
function free_for_span($matrix, $people, array $span) {
    $out = [];
    foreach ($people as $p) {
        $id = (int)$p['id']; $ok = true;
        foreach ($span as $d) if (($matrix[$id][$d] ?? 'AVAILABLE') !== 'AVAILABLE') { $ok = false; break; }
        if ($ok) $out[] = $p;
    }
    return $out;
}

function inspector_availability_summary($offices, $day = null) {
    $rows = inspector_availability($offices, $day);
    $c = ['total' => count($rows), 'AVAILABLE' => 0, 'ON_JOB' => 0, 'LEAVE' => 0, 'OTHER' => 0];
    foreach ($rows as $r) {
        $s = $r['eff_status'];
        if (isset($c[$s])) $c[$s]++;
        elseif ($s === 'AVAILABLE' || $s === 'ON_JOB' || $s === 'LEAVE') $c[$s]++;
        else $c['OTHER']++;
    }
    return $c;
}
// Can the current user manage the availability board? (coordinator / manager level)
function can_manage_availability() {
    return is_master() || can('workforce.availability') || is_coordinator_level() || is_admin_level() || can('ops.job.allocate');
}
// Offices in the viewer's scope (int[] | 'ALL').
function availability_scope_offices() {
    $s = scope_offices();
    return $s;
}

// -------------------------------------------------------------------------
//  Handler: set one inspector's status (AJAX / form post) + full board page
// -------------------------------------------------------------------------
function ops_inspector_availability($method) {
    ops_require(can_manage_availability(), 'You cannot manage inspector availability.');
    $day = $_REQUEST['day'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = date('Y-m-d');
    if ($method === 'POST') {
        $id = (int)($_POST['inspector_id'] ?? 0);
        $status = $_POST['status'] ?? 'AVAILABLE';
        $note = trim($_POST['note'] ?? '');
        if (!isset(avail_status_options()[$status])) $status = 'AVAILABLE';
        if ($id) {
            $pdo = db();
            // AVAILABLE / ON_JOB clear the manual override (system derives these);
            // any other status stores an explicit row.
            $pdo->prepare("DELETE FROM inspector_day_status WHERE inspector_id=? AND day=?")->execute([$id, $day]);
            if ($status !== 'AVAILABLE' && $status !== 'ON_JOB') {
                $pdo->prepare("INSERT INTO inspector_day_status (inspector_id,day,status,note,set_by,created_at) VALUES (?,?,?,?,?,?)")
                    ->execute([$id, $day, $status, $note, user_name(current_user()), date('c')]);
            }
        }
        if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'status' => $status, 'label' => avail_label($status)]); return true; }
        flash('Availability updated.');
        redirect('/availability?day=' . urlencode($day));
    }
    $offices = availability_scope_offices();
    $rows = inspector_availability($offices, $day);

    // §d — narrow the board the way a coordinator looks at it.
    $fOffice = (int)($_GET['office'] ?? 0);
    $fSbu    = trim($_GET['sbu'] ?? '');
    $fStatus = trim($_GET['status'] ?? '');
    $fq      = trim($_GET['q'] ?? '');
    $filtered = array_values(array_filter($rows, function ($r) use ($fOffice, $fSbu, $fStatus, $fq) {
        if ($fOffice && (int)($r['home_office_id'] ?? 0) !== $fOffice) return false;
        if ($fSbu !== '' && strpos(',' . ($r['sbu'] ?? '') . ',', ',' . $fSbu . ',') === false) return false;
        if ($fStatus !== '' && ($r['eff_status'] ?? '') !== $fStatus) return false;
        if ($fq !== '' && stripos(($r['name'] ?? '') . ' ' . ($r['emp_code'] ?? ''), $fq) === false) return false;
        return true;
    }));

    // §b — "free to allocate" means free today AND tomorrow, so the coordinator
    // is not handed somebody who is gone in the morning.
    $tomorrow = date('Y-m-d', strtotime($day . ' +1 day'));
    [$m2, $d2] = availability_matrix($offices, $day, $tomorrow);
    $freeBoth = [];
    foreach ($filtered as $r) {
        $id = (int)$r['id'];
        if (($m2[$id][$day] ?? '') === 'AVAILABLE' && ($m2[$id][$tomorrow] ?? '') === 'AVAILABLE') $freeBoth[] = $r;
    }

    // §c — check a date, and for how long each person is free from it. Optionally
    // require a run of N days, which answers "who can take three days from the 10th".
    $chkFrom = trim($_GET['check_from'] ?? '');
    $chkDays = max(1, min(30, (int)($_GET['check_days'] ?? 1)));
    $check = null;
    if ($chkFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $chkFrom)) {
        $horizon = date('Y-m-d', strtotime($chkFrom . ' +45 days'));
        [$mC, $dC, $peopleC] = availability_matrix($offices, $chkFrom, $horizon);
        $span = array_slice($dC, 0, $chkDays);
        $peopleC = array_values(array_filter($peopleC, function ($p) use ($fOffice, $fSbu, $fq) {
            if ($fOffice && (int)($p['home_office_id'] ?? 0) !== $fOffice) return false;
            if ($fSbu !== '' && strpos(',' . ($p['sbu'] ?? '') . ',', ',' . $fSbu . ',') === false) return false;
            if ($fq !== '' && stripos(($p['name'] ?? '') . ' ' . ($p['emp_code'] ?? ''), $fq) === false) return false;
            return true;
        }));
        $coverIds = [];
        foreach (free_for_span($mC, $peopleC, $span) as $c) $coverIds[(int)$c['id']] = true;
        $rowsC = [];
        foreach ($peopleC as $p) {
            $w = free_window($mC, (int)$p['id'], $chkFrom, $dC);
            $rowsC[] = $p + ['win' => $w, 'covers' => isset($coverIds[(int)$p['id']])];
        }
        // longest free run first — the most useful person at the top
        usort($rowsC, fn($a, $b) => ($b['win']['run'] ?? 0) <=> ($a['win']['run'] ?? 0));
        $check = ['from' => $chkFrom, 'days' => $chkDays, 'span' => $span, 'rows' => $rowsC,
                  'fit' => count(array_filter($rowsC, fn($r) => ($r['win']['run'] ?? 0) >= $chkDays))];
    }

    // §d — a month grid, so a whole month's cover can be seen at once.
    $month = trim($_GET['month'] ?? '');
    $grid = null;
    if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
        $mFrom = $month . '-01';
        $mTo   = date('Y-m-t', strtotime($mFrom));
        [$mG, $dG, $peopleG] = availability_matrix($offices, $mFrom, $mTo);
        $peopleG = array_values(array_filter($peopleG, function ($p) use ($fOffice, $fSbu, $fq) {
            if ($fOffice && (int)($p['home_office_id'] ?? 0) !== $fOffice) return false;
            if ($fSbu !== '' && strpos(',' . ($p['sbu'] ?? '') . ',', ',' . $fSbu . ',') === false) return false;
            if ($fq !== '' && stripos(($p['name'] ?? '') . ' ' . ($p['emp_code'] ?? ''), $fq) === false) return false;
            return true;
        }));
        $grid = ['month' => $month, 'days' => $dG, 'people' => $peopleG, 'matrix' => $mG];
    }

    view('ops/availability', [
        'rows' => $filtered, 'allRows' => $rows, 'day' => $day, 'offices' => $offices,
        'freeBoth' => $freeBoth, 'tomorrow' => $tomorrow,
        'check' => $check, 'grid' => $grid, 'month' => $month,
        'officeList' => offices_list(), 'sbuOpts' => lk_options_or('sbu', OPS_SBUS),
        'fOffice' => $fOffice, 'fSbu' => $fSbu, 'fStatus' => $fStatus, 'fq' => $fq,
        'chkFrom' => $chkFrom, 'chkDays' => $chkDays,
    ]);
    return true;
}

// -------------------------------------------------------------------------
//  8.5-hour daily cap — sum a person's logged working hours on a date.
//  Hours live on voucher_entries.hours. Returns the total EXCLUDING $exceptId.
// -------------------------------------------------------------------------
function inspector_hours_on($inspectorId, $day, $exceptEntryId = 0) {
    if (!$inspectorId || !$day) return 0.0;
    // voucher_entries has no inspector_id; it hangs off vouchers → inspector.
    $sql = "SELECT COALESCE(SUM(e.hours),0) FROM voucher_entries e
            JOIN vouchers v ON v.id=e.voucher_id
            WHERE v.inspector_id=? AND e.entry_date=?";
    $args = [$inspectorId, $day];
    if ($exceptEntryId) { $sql .= " AND e.id<>?"; $args[] = $exceptEntryId; }
    return (float)ops_val($sql, $args);
}
// True if adding $addHours to $day would exceed the cap; returns [ok, existing, cap].
function hours_within_cap($inspectorId, $day, $addHours, $exceptEntryId = 0) {
    $existing = inspector_hours_on($inspectorId, $day, $exceptEntryId);
    $total = $existing + (float)$addHours; $cap = hours_cap();
    return [$total <= $cap + 0.001, $existing, $cap, $total];
}

// -------------------------------------------------------------------------
//  Automated MIS digest — a periodic ops + sales summary e-mailed to leadership.
//  Called from cron.php (weekly on Mondays, monthly on the 1st).
// -------------------------------------------------------------------------
function leadership_emails() {
    $rows = ops_all("SELECT email FROM users WHERE role IN ('BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER','MASTER_ADMIN','ADMIN') AND email<>'' AND is_active=1");
    return implode(',', array_filter(array_column($rows, 'email')));
}
function ops_run_mis_digest($period = 'weekly') {
    $to = leadership_emails();
    if (!$to) return 0;
    $today = date('Y-m-d');
    $since = $period === 'monthly' ? date('Y-m-d', strtotime('-1 month')) : date('Y-m-d', strtotime('-7 days'));
    $v = fn($sql, $a = []) => (float)ops_val($sql, $a);
    $openJobs   = (int)$v("SELECT COUNT(*) FROM jobs WHERE closed_flag=0");
    $overdue    = (int)$v("SELECT COUNT(*) FROM jobs WHERE closed_flag=0 AND ((inspection_end_date<>'' AND inspection_end_date<?) OR (inspection_end_date='' AND scheduled_date<>'' AND scheduled_date<?))", [$today, $today]);
    $closed     = (int)$v("SELECT COUNT(*) FROM jobs WHERE closed_flag=1 AND closed_at>=?", [$since]);
    $repPending = (int)$v("SELECT COUNT(*) FROM jobs WHERE report_approval='PENDING'");
    $newCalls   = (int)$v("SELECT COUNT(*) FROM calls WHERE call_received_date>=?", [$since]);
    // sales (guard if CRM not present)
    $hasQuotes  = (int)$v("SELECT COUNT(*) FROM quotations");
    $qOpen = $qWon = $qLost = 0; $wonVal = 0.0;
    if ($hasQuotes >= 0) {
        $qOpen = (int)$v("SELECT COUNT(*) FROM quotations WHERE is_current=1 AND status IN ('DRAFT','PENDING_APPROVAL','APPROVED','SENT')");
        $qWon  = (int)$v("SELECT COUNT(*) FROM quotations WHERE is_current=1 AND status='ACCEPTED' AND updated_at>=?", [$since]);
        $qLost = (int)$v("SELECT COUNT(*) FROM quotations WHERE is_current=1 AND status IN ('LOST','EXPIRED') AND updated_at>=?", [$since]);
        $wonVal= $v("SELECT COALESCE(SUM(total_amount),0) FROM quotations WHERE is_current=1 AND status='ACCEPTED' AND updated_at>=?", [$since]);
    }
    $unbilled    = $v("SELECT COALESCE(SUM(expected_credit),0) FROM jobs WHERE closed_flag=1 AND invoice_raised=0");
    $label = $period === 'monthly' ? 'Monthly' : 'Weekly';
    $body = "$label management summary — " . app_name() . "\nPeriod: since $since\n\n"
        . "OPERATIONS\n"
        . "  Open jobs: $openJobs  (overdue: $overdue)\n"
        . "  Jobs closed in period: $closed\n"
        . "  New calls in period: $newCalls\n"
        . "  Reports awaiting approval: $repPending\n\n"
        . "SALES / CRM\n"
        . "  Open quotations: $qOpen\n"
        . "  Won in period: $qWon  (₹" . number_format($wonVal, 0) . ")\n"
        . "  Lost/expired in period: $qLost\n\n"
        . "MONEY\n"
        . "  Unbilled (closed, not invoiced): ₹" . number_format($unbilled, 0) . "\n\n"
        . "Open the dashboard for details.\n\n" . app_name();
    ops_mail($to, "$label MIS summary — " . app_name() . " ($today)", $body, '', 'mis_digest');
    return 1;
}

// -------------------------------------------------------------------------
//  Reporting-manager chain + organisation hierarchy (N+1 automatic)
// -------------------------------------------------------------------------
function user_display_name($u) {
    $n = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    return $n !== '' ? $n : ($u['username'] ?? '—');
}
// The direct reporting manager of a user, as a normalised entry:
//   ['user_id'=>?, 'name'=>, 'position'=>, 'email'=>, 'role'=>]
// A linked system user wins; otherwise the manual name/position/email is used.
function user_manager_entry($u) {
    if (!empty($u['reports_to_id'])) {
        $m = ops_one("SELECT * FROM users WHERE id=?", [(int)$u['reports_to_id']]);
        if ($m) return [
            'user_id' => (int)$m['id'], 'name' => user_display_name($m),
            'position' => $m['position_title'] ?: (ORG_ROLES[$m['role']] ?? $m['role']),
            'email' => $m['email'] ?? '', 'role' => $m['role'] ?? '',
        ];
    }
    if (trim((string)($u['reports_to_name'] ?? '')) !== '' || trim((string)($u['reports_to_email'] ?? '')) !== '') {
        return ['user_id' => null, 'name' => $u['reports_to_name'] ?: '(manager)',
            'position' => $u['reports_to_position'] ?? '', 'email' => $u['reports_to_email'] ?? '', 'role' => ''];
    }
    return null;
}
// Ordered chain of managers above a user (direct manager first), cycle-guarded.
function reporting_chain($userId, $maxDepth = 12) {
    $chain = []; $seen = [(int)$userId => true];
    $u = ops_one("SELECT * FROM users WHERE id=?", [(int)$userId]);
    while ($u && $maxDepth-- > 0) {
        $m = user_manager_entry($u);
        if (!$m) break;
        $chain[] = $m;
        if (empty($m['user_id']) || isset($seen[(int)$m['user_id']])) break;   // manual manager ends the chain; cycle stops it
        $seen[(int)$m['user_id']] = true;
        $u = ops_one("SELECT * FROM users WHERE id=?", [(int)$m['user_id']]);
    }
    return $chain;
}
// The system-user id of the manager N levels above $userId (1 = direct), or null.
function reporting_manager_at($userId, $level = 1) {
    $chain = reporting_chain($userId);
    $idx = max(1, (int)$level) - 1;
    return isset($chain[$idx]) ? ($chain[$idx]['user_id'] ?? null) : null;
}
// Build the org tree (roots = users with no manager). Returns [rootNodes], each
// node = user row + 'children'=>[]. Users are matched by reports_to_id only
// (manual managers without a login are shown as a label on the child).
function org_hierarchy_tree() {
    $users = ops_all("SELECT id, first_name, last_name, username, role, position_title, home_office_id, email, reports_to_id, reports_to_name, reports_to_position, is_active FROM users WHERE is_active=1 ORDER BY role, first_name");
    $byId = []; $children = [];
    foreach ($users as $u) { $u['children'] = []; $byId[(int)$u['id']] = $u; }
    $roots = [];
    foreach ($byId as $id => $u) {
        $pid = (int)($u['reports_to_id'] ?? 0);
        if ($pid && isset($byId[$pid])) $children[$pid][] = $id;
        else $roots[] = $id;
    }
    $build = function($id) use (&$build, &$byId, &$children) {
        $node = $byId[$id];
        foreach ($children[$id] ?? [] as $cid) $node['children'][] = $build($cid);
        return $node;
    };
    // order roots: directors first
    $out = [];
    foreach ($roots as $id) $out[] = $build($id);
    return $out;
}

// -------------------------------------------------------------------------
//  Handler: organisation hierarchy (view / print the N+1 chart)
// -------------------------------------------------------------------------
// ---------------------------------------------------------------------------
//  Placing people in the chain
//
//  The chart can only be a tree if people actually have a reporting manager. On
//  a fresh install nobody does, so every person is a root and the chart is a
//  flat row of boxes. This proposes the obvious chain from the role ladder —
//  each role reports to the one above it, preferring somebody in the same
//  office — so the org can be arranged in one click and then corrected by hand.
// ---------------------------------------------------------------------------
function org_role_ladder() {
    return [
        'SBU_HEAD'             => ['BUSINESS_DIRECTOR'],
        'BRANCH_MANAGER'       => ['SBU_HEAD', 'BUSINESS_DIRECTOR'],
        'BRANCH_APP_MANAGER'   => ['BRANCH_MANAGER', 'SBU_HEAD'],
        'OPERATION_MANAGER'    => ['BRANCH_MANAGER', 'SBU_HEAD'],
        'ASST_MANAGER'         => ['OPERATION_MANAGER', 'BRANCH_MANAGER'],
        'COORDINATOR'          => ['ASST_MANAGER', 'OPERATION_MANAGER', 'BRANCH_MANAGER'],
        'INSPECTOR'            => ['COORDINATOR', 'OPERATION_MANAGER', 'BRANCH_MANAGER'],
        'BUSINESS_DEV_MANAGER' => ['BUSINESS_DIRECTOR'],
        'KEY_ACCOUNTS_MANAGER' => ['BUSINESS_DEV_MANAGER', 'BUSINESS_DIRECTOR'],
        'MARKETING_MANAGER'    => ['BUSINESS_DEV_MANAGER', 'BUSINESS_DIRECTOR'],
        'MARKETING_EXECUTIVE'  => ['MARKETING_MANAGER', 'BUSINESS_DEV_MANAGER'],
        'FINANCE'              => ['BUSINESS_DIRECTOR'],
    ];
}
// Work out who each unplaced person should report to. Returns the proposals;
// nothing is written unless $apply is true, so it can be previewed first.
function org_auto_arrange($apply = false, $onlyUnplaced = true) {
    $users = ops_all("SELECT id, first_name, last_name, username, role, home_office_id, reports_to_id FROM users WHERE is_active=1");
    $byRole = [];
    foreach ($users as $u) $byRole[$u['role']][] = $u;
    $ladder = org_role_ladder();
    $name = fn($u) => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username'];
    $out = [];
    foreach ($users as $u) {
        if ($onlyUnplaced && (int)($u['reports_to_id'] ?? 0)) continue;
        $cands = $ladder[$u['role']] ?? [];
        $pick = null;
        foreach ($cands as $r) {
            $pool = $byRole[$r] ?? [];
            if (!$pool) continue;
            // same office first — a coordinator reports to their own branch manager
            foreach ($pool as $p) {
                if ((int)$p['id'] === (int)$u['id']) continue;
                if ((int)($p['home_office_id'] ?? 0) && (int)$p['home_office_id'] === (int)($u['home_office_id'] ?? 0)) { $pick = $p; break 2; }
            }
            foreach ($pool as $p) { if ((int)$p['id'] !== (int)$u['id']) { $pick = $p; break 2; } }
        }
        if (!$pick) continue;   // nobody above them exists — they stay a root
        $out[] = ['user_id' => (int)$u['id'], 'user' => $name($u), 'role' => $u['role'],
                  'manager_id' => (int)$pick['id'], 'manager' => $name($pick), 'manager_role' => $pick['role']];
    }
    if ($apply) {
        $st = db()->prepare("UPDATE users SET reports_to_id=?, reports_to_name=?, reports_to_position=? WHERE id=?");
        foreach ($out as $p)
            $st->execute([$p['manager_id'], $p['manager'], ORG_ROLES[$p['manager_role']] ?? $p['manager_role'], $p['user_id']]);
    }
    return $out;
}
// Count everyone underneath a node, so a card can say "4 direct · 17 total".
function org_count_below(&$node) {
    $total = 0;
    foreach ($node['children'] as &$c) { $total += 1 + org_count_below($c); }
    unset($c);
    $node['direct_n'] = count($node['children']);
    $node['total_n']  = $total;
    return $total;
}

// The organisation screen itself now lives in lib/orgadmin.php as
// ops_hierarchy_screen(): the chart, the office tree, the bulk people editor
// and the spreadsheet round-trip are tabs over one set of data, so what you
// look at is what you edit. The tree/ladder helpers above are shared by both.

// -------------------------------------------------------------------------
//  Inspection report approval — routes a closed job's report to the
//  inspector's reporting manager (falls back to any manager in scope).
// -------------------------------------------------------------------------
// The system-user id who should approve this job's report (inspector's manager).
function report_approver_user_id($job) {
    $insId = (int)($job['inspector_id'] ?? 0);
    if (!$insId) return null;
    $rt = ops_val("SELECT reports_to_id FROM inspectors WHERE id=?", [$insId]);
    return $rt ? (int)$rt : null;
}
// E-mail of an inspector's reporting manager (for overdue-report escalation).
function inspector_manager_email($inspectorId) {
    $rt = $inspectorId ? ops_val("SELECT reports_to_id FROM inspectors WHERE id=?", [(int)$inspectorId]) : null;
    if (!$rt) return '';
    return (string)ops_val("SELECT email FROM users WHERE id=? AND is_active=1", [(int)$rt]);
}
function report_approval_notify($jobId) {
    $job = ops_one("SELECT j.*, i.name inspector_name FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id WHERE j.id=?", [$jobId]);
    if (!$job) return;
    $uid = report_approver_user_id($job);
    $to = $uid ? ops_val("SELECT email FROM users WHERE id=?", [$uid]) : '';
    // manager_emails() already returns a comma-separated STRING, not a list.
    // imploding it crashed the whole job-closure flow the moment an inspector's
    // approver had no e-mail address on file.
    if (!$to) $to = (string)manager_emails();
    if (!$to) return;
    $subj = 'Report approval: ' . $job['job_code'];
    $body = "Inspector " . ($job['inspector_name'] ?: '—') . " has closed job " . $job['job_code']
        . " and uploaded the report (" . ($job['report_upload_date'] ?: '—') . ").\n\n"
        . "Please review and approve it in the system → Jobs → " . $job['job_code'] . ".\n\n" . app_name();
    ops_mail($to, $subj, $body, '', 'report_approval');
}
// Can the current user approve this job's report?
function can_approve_report($job) {
    if (is_master()) return true;
    if (($job['report_approval'] ?? '') !== 'PENDING') return false;
    $uid = report_approver_user_id($job);
    if ($uid && (int)$uid === (int)(current_user()['id'] ?? 0)) return true;
    if (can('workforce.report.approve')) return true;
    // otherwise a manager who can allocate/close jobs in scope may approve
    return can('ops.job.close') && (is_admin_level() || is_coordinator_level());
}
// Jobs whose report awaits the current user's approval (in scope).
function jobs_awaiting_report_approval($limit = 50) {
    [$where, $args] = scope_clause('j.executing_office_id', 'j.sbu');
    $me = (int)(current_user()['id'] ?? 0);
    $rows = ops_all("SELECT j.id, j.job_code, j.report_upload_date, j.sbu, j.inspector_id,
            i.name inspector_name, i.reports_to_id
        FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id
        WHERE j.report_approval='PENDING' AND $where ORDER BY j.report_upload_date DESC", $args);
    if (is_master()) return array_slice($rows, 0, $limit);
    // keep only those I can approve (my direct reports, or manager fallback)
    $out = [];
    $blanket = can('workforce.report.approve') || (can('ops.job.close') && (is_admin_level() || is_coordinator_level()));
    foreach ($rows as $r) {
        if ((int)($r['reports_to_id'] ?? 0) === $me) { $out[] = $r; continue; }
        if ($blanket) $out[] = $r;
    }
    return array_slice($out, 0, $limit);
}
function ops_report_approve($method) {
    if ($method !== 'POST') { redirect('/jobs'); }
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int)($_POST['id'] ?? $_GET['id'] ?? 0)]);
    if (!$job) { http_response_code(404); view('notfound'); return true; }
    ops_require(can_approve_report($job), 'You are not the approver for this report.');
    $decision = ($_POST['decision'] ?? '') === 'reject' ? 'REJECTED' : 'APPROVED';
    $note = trim($_POST['note'] ?? '');
    db()->prepare("UPDATE jobs SET report_approval=?, report_approved_by=?, report_approved_at=?, report_approval_note=? WHERE id=?")
        ->execute([$decision, user_name(current_user()), date('c'), $note, $job['id']]);
    flash('Report ' . ($decision === 'APPROVED' ? 'approved' : 'sent back') . ' for ' . $job['job_code'] . '.');
    redirect('/job?id=' . $job['id']);
    return true;
}
