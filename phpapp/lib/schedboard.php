<?php
// ===========================================================================
//  SCHEDULE BOARD — a visual dispatch board, a capacity view and a
//  best-free-inspector suggestion. Pure SURFACE over the existing availability
//  engine (lib/workforce.php + lib/schedule.php); it adds NO new scheduling
//  data and duplicates none:
//    availability_matrix()  → inspector × day status grid (AVAILABLE / ON_JOB /
//                             manual LEAVE etc.), already span-aware.
//    free_for_span() / free_window() → who is free for a run of days, for how long.
//    is_working_day() / non_working_reason() → the office calendar & holidays.
//    sched_alternatives()   → same-branch, same-discipline, actually-free people.
//  It only READS. The coordinator still allocates through the existing job flow.
// ===========================================================================

// The offices the current user may see — delegate to the same helper the proven
// availability board uses, so both screens show exactly the same people.
function sched_offices() {
    if (function_exists('availability_scope_offices')) return availability_scope_offices();
    return function_exists('scope_offices') ? scope_offices() : 'ALL';
}

// The board grid for a date span — reuses availability_matrix wholesale.
// Returns ['matrix','days','people','busy'].
function sched_board_data($offices, $from, $to) {
    [$matrix, $days, $people, $busy] = availability_matrix($offices, $from, $to);
    return ['matrix' => $matrix, 'days' => $days, 'people' => $people, 'busy' => $busy];
}

// Capacity vs demand across the span. A day counts as capacity only if it is a
// working day for that inspector's office. Utilisation = on-job ÷ (on-job+free)
// working days. Per-day free counts drive the column strip.
function sched_capacity($board) {
    $days = $board['days']; $people = $board['people']; $matrix = $board['matrix'];
    $tot = ['working' => 0, 'on_job' => 0, 'free' => 0, 'off' => 0];
    $perDayFree = array_fill_keys($days, 0);
    $perDayJob  = array_fill_keys($days, 0);
    $wdCache = [];
    foreach ($people as $p) {
        $id = (int)$p['id']; $office = (int)($p['home_office_id'] ?? 0) ?: null;
        foreach ($days as $d) {
            $key = $d . ':' . (int)$office;
            $working = $wdCache[$key] ?? ($wdCache[$key] = (function_exists('is_working_day') ? is_working_day($d, $office) : (date('N', strtotime($d)) < 6)));
            if (!$working) continue;
            $tot['working']++;
            $st = $matrix[$id][$d] ?? 'AVAILABLE';
            if ($st === 'ON_JOB') { $tot['on_job']++; $perDayJob[$d]++; }
            elseif ($st === 'AVAILABLE') { $tot['free']++; $perDayFree[$d]++; }
            else { $tot['off']++; }
        }
    }
    $den = $tot['on_job'] + $tot['free'];
    $tot['utilisation'] = $den > 0 ? round($tot['on_job'] * 100 / $den) : 0;
    return ['totals' => $tot, 'per_day_free' => $perDayFree, 'per_day_job' => $perDayJob, 'people' => count($people)];
}

// Calls that still need a person — OPEN, not cancelled, with no job carrying an
// inspector. This is the "demand" side of the capacity picture.
function sched_unallocated_calls($offices, $limit = 40) {
    $w = ["c.status NOT IN ('CLOSED','CANCELLED')",
          "NOT EXISTS (SELECT 1 FROM jobs j WHERE j.call_id=c.id AND j.inspector_id IS NOT NULL)"];
    $a = [];
    if (is_array($offices)) {
        if (!$offices) return [];
        $in = implode(',', array_map('intval', $offices));
        $w[] = "(c.executing_office_id IN ($in) OR c.executing_office_id IS NULL)";
    }
    $a[] = (int)$limit;
    try {
        return ops_all("SELECT c.id, c.call_code, c.inspection_required_date, c.sbu, c.inspection_type,
                               c.executing_office_id, bp.display_name, bp.legal_name
                        FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id
                        WHERE " . implode(' AND ', $w) . "
                        ORDER BY CASE WHEN c.inspection_required_date='' THEN 1 ELSE 0 END, c.inspection_required_date, c.id DESC
                        LIMIT ?", $a) ?: [];
    } catch (Throwable $e) { return []; }
}

// Best free & qualified inspectors for a date span. Reuses the availability
// matrix + free_for_span (free EVERY day of the span) + free_window (how long
// the run lasts) and matches SBU/discipline the way sched_alternatives does.
// RANK: SBU match, then a longer continuous free run, then fewer jobs in view.
// It SUGGESTS; the coordinator decides (nobody is auto-assigned).
function sched_suggest($offices, $span, $sbu = '', $limit = 8) {
    $span = array_values(array_filter((array)$span));
    if (!$span) return [];
    $from = $span[0]; $to = end($span);
    [$matrix, $days, $people, $busy] = availability_matrix($offices, $from, $to);
    if (!$people) return [];
    // Qualified for the SBU (empty SBU = everyone). Inspectors carry sbu (+ sbus).
    $sbu = trim((string)$sbu);
    $skillOf = [];
    foreach (ops_all("SELECT id, sbu, COALESCE(sbus,'') sbus, COALESCE(skills,'') skills FROM inspectors") as $r)
        $skillOf[(int)$r['id']] = $r;
    $free = free_for_span($matrix, $people, $span);
    $out = [];
    foreach ($free as $p) {
        $id = (int)$p['id'];
        $sk = $skillOf[$id] ?? [];
        $match = true;
        if ($sbu !== '') {
            $his = array_filter(array_map('trim', explode(',', (string)(($sk['sbus'] ?? '') ?: ($sk['sbu'] ?? '')))));
            $match = !$his || in_array($sbu, $his, true);
        }
        if (!$match) continue;
        // How long is this person free from the span start (the "…then on JOB-x").
        $win = free_window($matrix, $id, $from, $days);
        // Jobs they already carry in the view — a tie-breaker toward the least loaded.
        $load = 0; foreach ($days as $d) if (($matrix[$id][$d] ?? '') === 'ON_JOB') $load++;
        $out[] = [
            'id' => $id, 'name' => $p['name'], 'emp_code' => $p['emp_code'] ?? '',
            'office_id' => (int)($p['home_office_id'] ?? 0), 'sbu' => $sk['sbu'] ?? ($p['sbu'] ?? ''),
            'skills' => $sk['skills'] ?? '', 'run' => (int)$win['run'], 'until' => $win['until'],
            'next_busy' => $win['next_busy'], 'load' => $load,
        ];
    }
    // Rank: longest free run first, then least loaded, then name.
    usort($out, fn($a, $b) => ($b['run'] <=> $a['run']) ?: ($a['load'] <=> $b['load']) ?: strcasecmp($a['name'], $b['name']));
    return array_slice($out, 0, $limit);
}

// ---------------------------------------------------------------------------
//  Handler — Operations → Scheduling board.
// ---------------------------------------------------------------------------
function sched_board_can() {
    return (function_exists('can') && (can('ops.job.allocate') || can('mod.jobs.view')))
        || (function_exists('is_coordinator_level') && is_coordinator_level())
        || (function_exists('is_master') && is_master());
}

function ops_schedule_board($method) {
    ops_require(sched_board_can(), 'You do not have access to the scheduling board.');
    $offices = sched_offices();
    // Default window: today → +13 days (a fortnight). Clamped by day_span's cap.
    $from = trim((string)($_GET['from'] ?? '')) ?: date('Y-m-d');
    $to   = trim((string)($_GET['to'] ?? '')) ?: date('Y-m-d', strtotime($from . ' +13 days'));
    if (strtotime($to) < strtotime($from)) $to = date('Y-m-d', strtotime($from . ' +13 days'));

    $board = sched_board_data($offices, $from, $to);
    $capacity = sched_capacity($board);
    $unalloc = sched_unallocated_calls($offices);

    // Optional "who's free" suggestion — driven by a date range (+ SBU).
    $sFrom = trim((string)($_GET['s_from'] ?? '')); $sTo = trim((string)($_GET['s_to'] ?? ''));
    $sSbu  = trim((string)($_GET['s_sbu'] ?? '')); $sCall = (int)($_GET['s_call'] ?? 0);
    $suggest = null; $suggestSpan = [];
    if ($sFrom !== '') {
        $sTo = $sTo ?: $sFrom;
        $suggestSpan = day_span($sFrom, $sTo, 60);
        if ($suggestSpan) $suggest = sched_suggest($offices, $suggestSpan, $sSbu);
    }
    $sbus = (function_exists('lk_options_or') && defined('OPS_SBUS')) ? lk_options_or('sbu', OPS_SBUS) : [];

    view('ops/schedule_board', [
        'from' => $from, 'to' => $to, 'board' => $board, 'capacity' => $capacity,
        'unalloc' => $unalloc, 'suggest' => $suggest, 'suggestSpan' => $suggestSpan,
        's_from' => $sFrom, 's_to' => $sTo, 's_sbu' => $sSbu, 's_call' => $sCall, 'sbus' => $sbus,
        'canAllocate' => function_exists('can') ? (can('ops.job.allocate') || is_coordinator_level()) : true,
    ]);
    return true;
}
