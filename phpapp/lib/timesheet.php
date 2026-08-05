<?php
// ============================================================================
//  Automatic timesheet — built from the punches, per inspector
//
//  No one types a timesheet. The site check-ins (ENTRY/EXIT, each with a time
//  and, once geofencing is on, proven to be at the site) already say when an
//  inspector arrived and left. This pairs them per day and per job into hours
//  on site, and lays the day's attendance status beside it. A month is one
//  screen and one CSV.
// ============================================================================

function timesheet_can() {
    return is_master() || (function_exists('is_coordinator_level') && is_coordinator_level())
        || (function_exists('can') && (can('mod.hiring.view') || can('mod.jobs.view')));
}

function timesheet_hhmm($mins) {
    $mins = max(0, (int)$mins);
    return sprintf('%dh %02dm', intdiv($mins, 60), $mins % 60);
}

// Build the daily timesheet for one inspector between two YYYY-MM-DD dates.
// Returns ['rows'=>[...], 'total_minutes','total_jobs','days_on_site','days_att'].
function timesheet_build($inspectorId, $from, $to) {
    $inspectorId = (int)$inspectorId;
    $visits = ops_all("SELECT sv.kind, sv.at, sv.job_id, j.job_code
                       FROM site_visits sv LEFT JOIN jobs j ON j.id=sv.job_id
                       WHERE sv.inspector_id=? ORDER BY sv.at", [$inspectorId]) ?: [];
    $byDay = [];
    foreach ($visits as $v) {
        $d = substr((string)$v['at'], 0, 10);
        if ($d === '' || $d < $from || $d > $to) continue;
        $j = (int)$v['job_id']; $k = $v['kind'] ?? 'ENTRY';
        if (!isset($byDay[$d][$j])) $byDay[$d][$j] = ['code' => $v['job_code'] ?: ('#' . $j), 'in' => null, 'out' => null];
        if ($k === 'ENTRY' && ($byDay[$d][$j]['in'] === null || $v['at'] < $byDay[$d][$j]['in'])) $byDay[$d][$j]['in'] = $v['at'];
        if ($k === 'EXIT'  && ($byDay[$d][$j]['out'] === null || $v['at'] > $byDay[$d][$j]['out'])) $byDay[$d][$j]['out'] = $v['at'];
    }
    $att = [];
    foreach (ops_all("SELECT att_date, status FROM attendance WHERE inspector_id=?", [$inspectorId]) ?: [] as $a) {
        $d = substr((string)$a['att_date'], 0, 10);
        if ($d >= $from && $d <= $to) $att[$d] = $a['status'];
    }
    $days = array_values(array_unique(array_merge(array_keys($byDay), array_keys($att))));
    sort($days);
    $rows = []; $totMin = 0; $totJobs = 0; $onSite = 0;
    foreach ($days as $d) {
        $jobs = $byDay[$d] ?? []; $mins = 0; $firstIn = null; $lastOut = null; $codes = [];
        foreach ($jobs as $j) {
            $codes[] = $j['code'];
            if ($j['in'] && $j['out']) { $m = (int)round((strtotime($j['out']) - strtotime($j['in'])) / 60); if ($m > 0) $mins += $m; }
            if ($j['in'] && ($firstIn === null || $j['in'] < $firstIn)) $firstIn = $j['in'];
            if ($j['out'] && ($lastOut === null || $j['out'] > $lastOut)) $lastOut = $j['out'];
        }
        $rows[] = ['date' => $d, 'jobs' => $codes, 'in' => $firstIn, 'out' => $lastOut, 'minutes' => $mins,
                   'status' => $att[$d] ?? ($jobs ? 'ONSITE' : ''), 'incomplete' => ($jobs && $mins === 0)];
        $totMin += $mins; $totJobs += count($jobs); if ($mins > 0) $onSite++;
    }
    return ['rows' => $rows, 'total_minutes' => $totMin, 'total_jobs' => $totJobs,
            'days_on_site' => $onSite, 'days_att' => count($att)];
}

function ops_timesheet($route, $method) {
    ops_require(timesheet_can(), 'You cannot view timesheets.');
    $inspectors = inspectors_list(false) ?: [];
    $insId = (int)($_GET['ins'] ?? 0);
    if (!$insId && $inspectors) $insId = (int)$inspectors[0]['id'];
    $ins = $insId ? ops_one("SELECT * FROM inspectors WHERE id=?", [$insId]) : null;

    // Month window (YYYY-MM); default the current month.
    $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
    $from = $month . '-01';
    $to   = date('Y-m-t', strtotime($from));
    $ts = $ins ? timesheet_build($insId, $from, $to) : ['rows' => [], 'total_minutes' => 0, 'total_jobs' => 0, 'days_on_site' => 0, 'days_att' => 0];

    if (($_GET['export'] ?? '') === 'csv' && $ins) {
        $csv = [['Date', 'Jobs', 'First in', 'Last out', 'Hours on site', 'Attendance']];
        foreach ($ts['rows'] as $r) {
            $csv[] = [$r['date'], implode(' ', $r['jobs']),
                      $r['in'] ? substr($r['in'], 11, 5) : '', $r['out'] ? substr($r['out'], 11, 5) : '',
                      timesheet_hhmm($r['minutes']),
                      $r['status'] === 'ONSITE' ? 'On site' : (ATT_STATUS[$r['status']] ?? $r['status'])];
        }
        $csv[] = ['TOTAL', $ts['total_jobs'] . ' visits', '', '', timesheet_hhmm($ts['total_minutes']), $ts['days_on_site'] . ' days on site'];
        csv_download('timesheet-' . ($ins['emp_code'] ?: $insId) . '-' . $month . '.csv', $csv);
    }

    view('ops/timesheet', ['inspectors' => $inspectors, 'ins' => $ins, 'insId' => $insId,
        'month' => $month, 'ts' => $ts, 'attStatus' => ATT_STATUS]);
    return true;
}
