<?php
// ============================================================================
//  Inspector rating — an honest score, from what actually happened
//
//  Three factors, each 0–100, combined by weights you can change:
//    1. Timely reporting  — of the reportable jobs the inspector closed, the
//       share whose report was uploaded within the turnaround (TAT).
//    2. Inspections done  — volume against a monthly target (productivity).
//    3. Complaints        — starts at 100, a fixed penalty per complaint
//       registered against the inspector in the period.
//  The screen shows every number that goes in, so the score is never a
//  black box. Weights, the TAT, the target and the penalty are all settings.
// ============================================================================

function rating_config() {
    $g = fn($k, $d) => (int)(function_exists('setting_get') ? setting_get($k, $d) : $d);
    return [
        'w_report'    => max(0, $g('rating_w_report', 34)),
        'w_volume'    => max(0, $g('rating_w_volume', 33)),
        'w_complaint' => max(0, $g('rating_w_complaint', 33)),
        'tat_days'    => max(0, $g('rating_tat_days', 3)),        // grace to upload a report after the inspection
        'target_mo'   => max(1, $g('rating_target_month', 8)),    // inspections/month that scores 100 on volume
        'penalty'     => max(1, $g('rating_complaint_penalty', 20)),
        'months'      => max(1, $g('rating_window_months', 12)),
    ];
}

function rating_can() {
    return is_master() || (function_exists('is_coordinator_level') && is_coordinator_level())
        || (function_exists('can') && can('mod.jobs.view'));
}

// Compute the rating for one inspector over the last N months. Returns each
// component score, the raw counts behind it, and the weighted overall.
function rating_for($inspectorId, $cfg = null) {
    $cfg = $cfg ?: rating_config();
    $inspectorId = (int)$inspectorId;
    $since = date('Y-m-d', strtotime('-' . $cfg['months'] . ' months'));

    // --- 1. Timely reporting -------------------------------------------------
    // Reportable, closed jobs in the window: did the report go up within TAT?
    $jobs = ops_all("SELECT inspection_end_date, closed_at, report_upload_date, tat_days, reporting_frequency
                     FROM jobs WHERE inspector_id=? AND COALESCE(closed_flag,0)=1
                       AND COALESCE(reporting_frequency,'NOREPORT') <> 'NOREPORT'
                       AND substr(COALESCE(closed_at, inspection_end_date, ''),1,10) >= ?", [$inspectorId, $since]) ?: [];
    $reportable = 0; $onTime = 0;
    foreach ($jobs as $j) {
        $reportable++;
        $up = substr((string)($j['report_upload_date'] ?? ''), 0, 10);
        if ($up === '') continue;                            // never reported → not on time
        $base = substr((string)($j['inspection_end_date'] ?: $j['closed_at']), 0, 10);
        if ($base === '') { $onTime++; continue; }           // no basis to judge → give benefit
        $tat = (int)($j['tat_days'] ?? 0) ?: $cfg['tat_days'];
        $due = date('Y-m-d', strtotime($base . ' +' . $tat . ' days'));
        if ($up <= $due) $onTime++;
    }
    $reportScore = $reportable > 0 ? (int)round($onTime / $reportable * 100) : null;   // null = no data yet

    // --- 2. Inspections done -------------------------------------------------
    $done = (int)ops_val("SELECT COUNT(*) FROM jobs WHERE inspector_id=? AND COALESCE(closed_flag,0)=1
                          AND substr(COALESCE(closed_at, inspection_end_date, ''),1,10) >= ?", [$inspectorId, $since]);
    $target = $cfg['target_mo'] * $cfg['months'];
    $volScore = min(100, (int)round($done / max(1, $target) * 100));

    // --- 3. Complaints -------------------------------------------------------
    $complaints = 0;
    try {
        $complaints = (int)ops_val("SELECT COUNT(*) FROM complaints WHERE inspector_id=?
                                    AND substr(COALESCE(created_at,''),1,10) >= ?", [$inspectorId, $since]);
    } catch (Throwable $e) { $complaints = 0; }
    $compScore = max(0, 100 - $complaints * $cfg['penalty']);

    // --- Weighted overall ----------------------------------------------------
    // A component with no data (only reporting can be null) drops out of the
    // average so a new inspector is not punished for silence.
    $parts = [];
    if ($reportScore !== null) $parts[] = [$cfg['w_report'], $reportScore];
    $parts[] = [$cfg['w_volume'], $volScore];
    $parts[] = [$cfg['w_complaint'], $compScore];
    $wsum = array_sum(array_column($parts, 0)) ?: 1;
    $overall = (int)round(array_sum(array_map(fn($p) => $p[0] * $p[1], $parts)) / $wsum);

    return [
        'overall'      => $overall,
        'stars'        => round($overall / 20, 1),           // 0–5
        'report_score' => $reportScore, 'reportable' => $reportable, 'on_time' => $onTime,
        'volume_score' => $volScore, 'done' => $done, 'target' => $target,
        'comp_score'   => $compScore, 'complaints' => $complaints,
        'months'       => $cfg['months'],
    ];
}

// Every active inspector, rated, best first — the leaderboard.
function rating_all($cfg = null) {
    $cfg = $cfg ?: rating_config();
    $out = [];
    foreach (inspectors_list(true) ?: [] as $i) {
        $r = rating_for((int)$i['id'], $cfg);
        $out[] = ['inspector' => $i, 'r' => $r];
    }
    usort($out, fn($a, $b) => $b['r']['overall'] <=> $a['r']['overall']);
    return $out;
}

function ops_ratings($route, $method) {
    ops_require(rating_can(), 'You cannot view inspector ratings.');
    $pdo = db();
    if ($route === 'ratings-config' && $method === 'POST') {
        ops_require(is_master() || is_admin_level(), 'Only an administrator can change the rating weights.');
        foreach (['rating_w_report','rating_w_volume','rating_w_complaint','rating_tat_days','rating_target_month','rating_complaint_penalty','rating_window_months'] as $k)
            if (isset($_POST[$k])) setting_set($k, (string)max(0, (int)$_POST[$k]));
        flash('Rating settings saved.');
        redirect('/ratings');
    }
    view('ops/ratings', ['list' => rating_all(), 'cfg' => rating_config(),
        'canConfig' => is_master() || (function_exists('is_admin_level') && is_admin_level())]);
    return true;
}
