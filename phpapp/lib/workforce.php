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
function ops_hierarchy($method) {
    ops_require(is_master() || can('users.manage.global') || can('users.manage.branch') || can('settings.manage'), 'You cannot view the organisation hierarchy.');
    view('ops/hierarchy', ['tree' => org_hierarchy_tree()]);
    return true;
}

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
function report_approval_notify($jobId) {
    $job = ops_one("SELECT j.*, i.name inspector_name FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id WHERE j.id=?", [$jobId]);
    if (!$job) return;
    $uid = report_approver_user_id($job);
    $to = $uid ? ops_val("SELECT email FROM users WHERE id=?", [$uid]) : '';
    if (!$to) $to = implode(',', manager_emails());
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
    foreach ($rows as $r) {
        if ((int)($r['reports_to_id'] ?? 0) === $me) { $out[] = $r; continue; }
        if (can('ops.job.close') && (is_admin_level() || is_coordinator_level())) $out[] = $r;
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
