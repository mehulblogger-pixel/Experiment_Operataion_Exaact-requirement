<?php
// ============================================================================
//  EXAACT Recruitment — Phase 7: exports (CSV) for the recruitment desk.
//  Additive and non-destructive. One route (`recruit-export`) streams a chosen
//  dataset as a spreadsheet, honouring the SAME filters as the Recruitment
//  command centre (FY / month / department / source / recruiter) so what a
//  manager sees on screen is exactly what they download.
//
//  Reuses the platform CSV helper (`csv_download()`), the analytics engine
//  (`rcc_*`) and the existing hiring permission gates. No new permission:
//   • data.salary (`can_see_salary()`) still gates every money column — an
//     export can never leak a CTC to someone who cannot see it on screen.
// ============================================================================

// The datasets a user may export, in menu order.
function recruit_export_datasets() {
    return [
        'candidates'   => 'Candidates (with stage, source, recruiter)',
        'requisitions' => 'Requirements / requisitions',
        'offers'       => 'Offers (lifecycle & value)',
        'funnel'       => 'Funnel & KPI summary',
    ];
}

// A human filename stem for the current filter selection (kept ASCII-safe).
function recruit_export_filename($dataset, $f) {
    $bits = ['recruitment', $dataset];
    if (!empty($f['month'])) $bits[] = $f['month'];
    elseif (!empty($f['fy'])) $bits[] = str_replace(['/', ' '], '-', (string)$f['fy']);
    if (($f['dept'] ?? '') !== '') $bits[] = $f['dept'];
    $stem = preg_replace('/[^A-Za-z0-9._-]+/', '-', implode('_', $bits));
    return trim($stem, '-_') . '_' . date('Ymd') . '.csv';
}

// Build the rows (first row = header) for the chosen dataset under the filters.
function recruit_export_rows($dataset, $f) {
    rcc_migrate();
    $salOK = !function_exists('can_see_salary') || can_see_salary();
    switch ($dataset) {
        case 'requisitions': return _rx_requisitions($f);
        case 'offers':       return _rx_offers($f, $salOK);
        case 'funnel':       return _rx_funnel($f);
        case 'candidates':
        default:             return _rx_candidates($f);
    }
}

function _rx_full_name($r) {
    return trim(preg_replace('/\s+/', ' ', trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))));
}

function _rx_candidates($f) {
    [$cw, $ca] = rcc_cand_where($f, 'c');
    $stages = function_exists('rcc_stage_labels') ? rcc_stage_labels() : [];
    $users  = function_exists('rcc_users') ? rcc_users() : [];
    $rows = [];
    $rows[] = ['Code', 'Name', 'Designation', 'Department', 'Stage', 'Source', 'Recruiter',
               'Experience (yrs)', 'Email', 'Mobile', 'CV received', 'Requisition', 'Created'];
    try {
        $q = ops_all("SELECT c.*, r.req_code FROM candidates c
                      LEFT JOIN requisitions r ON r.id=c.requisition_id
                      WHERE $cw ORDER BY COALESCE(NULLIF(c.cv_received_date,''),c.created_at) DESC, c.id DESC", $ca);
    } catch (Throwable $e) { $q = []; }
    foreach ($q as $c) {
        $src = (string)($c['source_type'] ?? '') !== '' ? $c['source_type'] : (string)($c['source'] ?? '');
        $rows[] = [
            (string)($c['cand_code'] ?? ''), _rx_full_name($c), (string)($c['designation'] ?? ''),
            (string)($c['department'] ?? ''), (string)($stages[$c['stage']] ?? $c['stage'] ?? ''), $src,
            (string)($users[(int)($c['recruiter_id'] ?? 0)] ?? ''),
            (string)($c['experience_years'] ?? ''), (string)($c['email'] ?? ''), (string)($c['mobile'] ?? ''),
            fdate((string)($c['cv_received_date'] ?? '')), (string)($c['req_code'] ?? ''),
            fdate((string)($c['created_at'] ?? '')),
        ];
    }
    return $rows;
}

function _rx_requisitions($f) {
    [$rw, $ra] = rcc_req_where($f, 'r');
    $users = function_exists('rcc_users') ? rcc_users() : [];
    $rows = [];
    $rows[] = ['Code', 'Designation', 'Department', 'Business unit', 'Grade', 'Type', 'Status',
               'Recruiter', 'Manager', 'Candidates', 'Opened'];
    try {
        $q = ops_all("SELECT r.*,
                        (SELECT COUNT(*) FROM candidates c WHERE c.requisition_id=r.id) cand_n
                      FROM requisitions r WHERE $rw ORDER BY r.id DESC", $ra);
    } catch (Throwable $e) { $q = []; }
    foreach ($q as $r) {
        $rows[] = [
            (string)($r['req_code'] ?? ''), (string)($r['designation'] ?? ''), (string)($r['department'] ?? ''),
            (string)($r['sbu'] ?? ''), (string)($r['grade'] ?? ''), (string)($r['req_type'] ?? ''),
            (string)($r['status'] ?? ''), (string)($users[(int)($r['recruiter_id'] ?? 0)] ?? ''),
            (string)($users[(int)($r['manager_id'] ?? 0)] ?? ''), (string)($r['cand_n'] ?? '0'),
            fdate((string)($r['created_at'] ?? '')),
        ];
    }
    return $rows;
}

function _rx_offers($f, $salOK) {
    [$cw, $ca] = rcc_cand_where($f, 'c');
    $rows = [];
    $head = ['Candidate', 'Designation', 'Department', 'Status', 'Joining date', 'Approved by',
             'Issued', 'Accepted / Declined', 'Created'];
    if ($salOK) array_splice($head, 4, 0, ['CTC']);   // money column only when permitted
    $rows[] = $head;
    try {
        $q = ops_all("SELECT o.*, c.first_name, c.middle_name, c.last_name, c.designation, c.department
                      FROM job_offers o JOIN candidates c ON c.id=o.candidate_id
                      WHERE $cw ORDER BY o.id DESC", $ca);
    } catch (Throwable $e) { $q = []; }
    foreach ($q as $o) {
        $decided = (string)($o['accepted_at'] ?? '') !== '' ? 'Accepted ' . fdate($o['accepted_at'])
                 : ((string)($o['declined_at'] ?? '') !== '' ? 'Declined ' . fdate($o['declined_at']) : '');
        $row = [
            _rx_full_name($o), (string)($o['designation'] ?? ''), (string)($o['department'] ?? ''),
            (string)($o['status'] ?? ''), fdate((string)($o['joining_date'] ?? '')),
            (string)($o['approved_by'] ?? ''), fdate((string)($o['issued_at'] ?? '')), $decided,
            fdate((string)($o['created_at'] ?? '')),
        ];
        if ($salOK) array_splice($row, 4, 0, [number_format((float)($o['ctc'] ?? 0), 0)]);
        $rows[] = $row;
    }
    return $rows;
}

function _rx_funnel($f) {
    $d = rcc_data($f);
    $rows = [];
    $rows[] = ['Recruitment funnel — ' . (($f['month'] ?? '') !== '' ? ($f['opts']['month'][$f['month']] ?? $f['month']) : ('FY ' . ($f['fy'] ?? '')))];
    $rows[] = [];
    $rows[] = ['Stage', 'Reached'];
    foreach (($d['funnel'] ?? []) as $s) $rows[] = [(string)$s['label'], (int)$s['n']];
    $rows[] = [];
    $rows[] = ['Outcome', 'Count'];
    foreach (($d['status'] ?? []) as $s) $rows[] = [(string)$s['label'], (int)$s['n']];
    if (!empty($d['kpi']) && is_array($d['kpi'])) {
        $rows[] = [];
        $rows[] = ['KPI', 'Value'];
        foreach ($d['kpi'] as $k) {
            if (!is_array($k)) continue;
            $rows[] = [(string)($k['label'] ?? ''), (string)($k['value'] ?? ($k['n'] ?? ''))];
        }
    }
    return $rows;
}

// Route: stream the chosen dataset as CSV. Same gate as the recruitment desk.
function ops_recruit_export($route, $method) {
    ops_require(function_exists('recruit_home_can') ? recruit_home_can() : can('mod.hiring.view'),
        'You do not have access to Recruitment.');
    $sets = recruit_export_datasets();
    $dataset = (string)($_GET['dataset'] ?? 'candidates');
    if (!isset($sets[$dataset])) $dataset = 'candidates';
    $f = rcc_filters();
    $rows = recruit_export_rows($dataset, $f);
    csv_download(recruit_export_filename($dataset, $f), $rows);
    return true;
}
