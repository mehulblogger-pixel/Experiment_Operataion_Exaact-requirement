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
function inspector_weekly_days($ins) {
    $w = (float)($ins['weekly_working_days'] ?? 0);
    return $w > 0 ? $w : 6;
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
    return is_master() || is_coordinator_level() || is_admin_level() || can('ops.job.allocate');
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
    view('ops/availability', ['rows' => $rows, 'day' => $day, 'offices' => $offices]);
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
    $total = $existing + (float)$addHours;
    return [$total <= DAILY_HOURS_CAP + 0.001, $existing, DAILY_HOURS_CAP, $total];
}
