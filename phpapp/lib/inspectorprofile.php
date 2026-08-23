<?php
// ============================================================================
//  Inspector profile — one page that says everything about a person
//
//  Pulls together what the other parts already hold: who they are and how to
//  reach them, their certifications and when each expires, their rating (with
//  the breakdown), their reporting accuracy, the jobs/projects they have done,
//  and this month's timesheet — with links out to the full timesheet and
//  rating. Nothing new is stored; it is a read of the existing records.
// ============================================================================

function inspector_profile_can() {
    return is_master() || (function_exists('is_coordinator_level') && is_coordinator_level())
        || (function_exists('can') && (can('mod.hiring.view') || can('mod.jobs.view')));
}

// Certificate status against today: expired / expiring soon / valid.
function inspector_cert_state($validTo, $soonDays = 45) {
    $validTo = substr((string)$validTo, 0, 10);
    if ($validTo === '') return ['label' => 'no expiry', 'pill' => 'p-mut'];
    $today = date('Y-m-d');
    if ($validTo < $today) return ['label' => 'expired', 'pill' => 'p-bad'];
    if ($validTo <= date('Y-m-d', strtotime('+' . $soonDays . ' days'))) return ['label' => 'expiring', 'pill' => 'p-warn'];
    return ['label' => 'valid', 'pill' => 'p-ok'];
}

function ops_inspector_profile($route, $method) {
    ops_require(inspector_profile_can(), 'You cannot view inspector profiles.');
    $id = (int)($_GET['id'] ?? 0);
    $ins = $id ? ops_one("SELECT * FROM inspectors WHERE id=?", [$id]) : null;
    if (!$ins) { flash('Inspector not found.', 'error'); redirect('/m/inspectors'); }

    $certs  = ops_all("SELECT * FROM inspector_certs WHERE inspector_id=? ORDER BY valid_to", [$id]) ?: [];
    $rating = function_exists('rating_for') ? rating_for($id) : null;

    // Jobs / projects the person has been on.
    $jobStats = ops_one("SELECT COUNT(*) total,
                           SUM(CASE WHEN COALESCE(closed_flag,0)=1 THEN 1 ELSE 0 END) done,
                           SUM(CASE WHEN COALESCE(closed_flag,0)=0 THEN 1 ELSE 0 END) open
                         FROM jobs WHERE inspector_id=?", [$id]) ?: ['total'=>0,'done'=>0,'open'=>0];
    $recentJobs = ops_all("SELECT j.job_code, j.scheduled_date, j.closed_flag, j.report_upload_date,
                             c.call_code, COALESCE(bp.display_name,bp.legal_name) client_name
                           FROM jobs j LEFT JOIN calls c ON c.id=j.call_id
                             LEFT JOIN business_partners bp ON bp.id=c.client_id
                           WHERE j.inspector_id=? ORDER BY j.id DESC LIMIT 10", [$id]) ?: [];
    $reportsDone = (int)ops_val("SELECT COUNT(*) FROM report_docs WHERE inspector_id=? AND deleted=0", [$id]);

    // This month's timesheet summary.
    $tsMonth = date('Y-m');
    $ts = function_exists('timesheet_build')
        ? timesheet_build($id, $tsMonth . '-01', date('Y-m-t'))
        : ['total_minutes'=>0,'days_on_site'=>0,'total_jobs'=>0];

    view('ops/inspector_profile', [
        'ins' => $ins, 'certs' => $certs, 'rating' => $rating,
        'jobStats' => $jobStats, 'recentJobs' => $recentJobs, 'reportsDone' => $reportsDone,
        'ts' => $ts, 'tsMonth' => $tsMonth,
        'canSalary' => is_master() || (function_exists('can') && can('data.salary')),
    ]);
    return true;
}
