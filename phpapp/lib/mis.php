<?php
// ===========================================================================
//  One set of numbers, one place they come from
//
//  Before this, three screens counted inspections three slightly different
//  ways — one by scheduled date, one by closure, one by invoice — and each was
//  defensible on its own. Put side by side they made the system look wrong.
//
//  So every figure below is built from ONE filtered set of jobs, chosen ONCE,
//  by ONE rule:
//
//    · a job belongs to the month its inspection FINISHED
//      (falling back to when it started, then to when it was scheduled)
//    · the INVOICE VALUE is what the client is charged — the bill if one has
//      been raised, otherwise the figure agreed on the order or quotation
//    · REVENUE is what a branch keeps out of that: the whole invoice value
//      where one branch holds the order and does the work, and invoice less
//      the credit passed over where two branches are involved. Filter to a
//      branch and you get that branch's share; leave it unfiltered and you get
//      the company's, which is the invoice value itself
//    · direct cost is the engineer's time on it, its expenses and its sub-con
//    · the shared cost of the branch is NOT pushed onto a job — that sits on
//      the Business Unit line, from the month-end run
//
//  Anything that wants a different rule has to say so on the screen.
// ===========================================================================

// What can be narrowed. Everything is optional; nothing here is a code list —
// the values come from the masters and from the records themselves.
function mis_filters() {
    $fyOpts = fy_options();
    $fy = (string)($_GET['fy'] ?? current_fy());
    if (!in_array($fy, $fyOpts, true)) $fy = in_array(current_fy(), $fyOpts, true) ? current_fy() : ($fyOpts[0] ?? current_fy());
    $months = fy_months($fy);
    $ym = (string)($_GET['m'] ?? '');
    if ($ym !== '' && !isset($months[$ym])) $ym = '';
    [$from, $to] = fy_month_range($fy, $ym);
    return [
        'fy' => $fy, 'm' => $ym, 'from' => $from, 'to' => $to,
        'months' => $months, 'fyOpts' => $fyOpts,
        'sbu'      => trim((string)($_GET['sbu'] ?? '')),
        'activity' => trim((string)($_GET['activity'] ?? '')),
        'office'   => (int)($_GET['office'] ?? 0),      // executing branch
        'ibo'      => (int)($_GET['ibo'] ?? 0),         // contracting / managing office
        'inspector'=> (int)($_GET['inspector'] ?? 0),
        'client'   => (int)($_GET['client'] ?? 0),
    ];
}

// The one filtered set. Everything else on the screen is counted off this.
function mis_jobs(array $F) {
    $w = ["COALESCE(j.inspection_end_date, j.inspection_start_date, j.scheduled_date) >= ?",
          "COALESCE(j.inspection_end_date, j.inspection_start_date, j.scheduled_date) <= ?"];
    $a = [$F['from'], $F['to']];
    if ($F['sbu'] !== '')      { $w[] = "j.sbu = ?";                 $a[] = $F['sbu']; }
    if ($F['activity'] !== '') { $w[] = "j.activity_id = ?";         $a[] = $F['activity']; }
    if ($F['office'])          { $w[] = "j.executing_office_id = ?"; $a[] = $F['office']; }
    if ($F['inspector'])       { $w[] = "j.inspector_id = ?";        $a[] = $F['inspector']; }
    if ($F['ibo'])             { $w[] = "COALESCE(c.contracting_office_id, c.ibo_office_id) = ?"; $a[] = $F['ibo']; }
    if ($F['client'])          { $w[] = "c.client_id = ?";           $a[] = $F['client']; }
    // Scope: a branch user sees their own branch, exactly as every register does.
    $scope = function_exists('scope_offices') ? scope_offices() : 'ALL';
    if ($scope !== 'ALL' && is_array($scope) && $scope) {
        $w[] = "j.executing_office_id IN (" . implode(',', array_map('intval', $scope)) . ")";
    }
    return ops_all("SELECT j.*, c.client_id, c.call_received_date, c.inspection_required_date,
                           c.forwarded_at, c.allocated_at, c.ibo_office_id,
                           -- aliased: j.* already carries contracting_office_id, and an
                           -- unaliased second column of the same name would overwrite it
                           -- with the call's, which may be blank on an older record.
                           c.contracting_office_id call_contracting_office_id,
                           i.name inspector_name, o.name office_name, o.code office_code,
                           p.legal_name, p.display_name, b.boss_number
                    FROM jobs j
                    LEFT JOIN calls c ON c.id = j.call_id
                    LEFT JOIN inspectors i ON i.id = j.inspector_id
                    LEFT JOIN offices o ON o.id = j.executing_office_id
                    LEFT JOIN business_partners p ON p.id = c.client_id
                    LEFT JOIN boss_numbers b ON b.id = j.boss_id
                    WHERE " . implode(' AND ', $w) . "
                    ORDER BY COALESCE(j.inspection_end_date, j.inspection_start_date, j.scheduled_date)", $a);
}

// Days between the date the client asked for and the date it was actually
// scheduled for. Positive = late. Null when either date is missing, because a
// guessed zero would quietly improve the average.
function mis_schedule_delay($j) {
    $want = trim((string)($j['inspection_required_date'] ?? ''));
    $got  = trim((string)($j['scheduled_date'] ?? $j['inspection_start_date'] ?? ''));
    if ($want === '' || $got === '') return null;
    $a = strtotime($want); $b = strtotime($got);
    if ($a === false || $b === false) return null;
    return (int)round(($b - $a) / 86400);
}

// Module 32 — the profit-engine consistency check. job_profit() is the canonical
// per-job P&L; its 'profit'/'cost' include overhead, voucher, other, contingency
// and the client-recovered credit. Two dashboards (this MIS screen and the SBU
// P&L contract table) re-derive a PARTIAL profit inline — credit − labour −
// expenses − subcon — dropping the rest, so they overstate profit versus the
// canonical engine and the management-reports screen (which reads $p['profit']).
// This measures every job both ways over the same population and reports the gap.
// It changes no displayed figure — it surfaces and quantifies the drift, honestly,
// the way the audit-chain verify surfaces a broken seal. Read-only.
function profit_reconciliation($F = null) {
    if (!is_array($F)) $F = [];
    $F += ['from' => '2000-01-01', 'to' => '2100-12-31', 'sbu' => '', 'activity' => '',
           'office' => 0, 'inspector' => 0, 'ibo' => 0, 'client' => 0];
    $jobs  = function_exists('mis_jobs') ? mis_jobs($F) : [];
    $scope = (int)($F['office'] ?: 0) ?: null;
    $out = ['jobs' => 0, 'drifting' => 0,
            'canonical_profit' => 0.0, 'partial_profit' => 0.0,
            'canonical_cost' => 0.0, 'partial_cost' => 0.0,
            'omitted' => ['overhead' => 0.0, 'voucher' => 0.0, 'other' => 0.0,
                          'contingency' => 0.0, 'recovered' => 0.0]];
    foreach ($jobs as $j) {
        $p = job_profit($j, $scope);
        $partialCost   = $p['labour'] + $p['expenses'] + $p['subcon'];   // the MIS / SBU-PL formula
        $partialProfit = $p['credit'] - $partialCost;
        $out['jobs']++;
        $out['canonical_profit'] += $p['profit'];
        $out['partial_profit']   += $partialProfit;
        $out['canonical_cost']   += $p['cost'];
        $out['partial_cost']     += $partialCost;
        $out['omitted']['overhead']    += $p['overhead'];
        $out['omitted']['voucher']     += $p['voucher'];
        $out['omitted']['other']       += $p['other'];
        $out['omitted']['contingency'] += $p['contingency'];
        $out['omitted']['recovered']   += $p['recovered'];
        if (round($p['profit'], 2) !== round($partialProfit, 2)) $out['drifting']++;
    }
    // The partial formula overstates profit by exactly the omitted net cost.
    $out['overstatement'] = round($out['partial_profit'] - $out['canonical_profit'], 2);
    $out['consistent']    = $out['drifting'] === 0;
    return $out;
}

// Everything the dashboard shows, from the one set of jobs.
function mis_summary(array $F) {
    $jobs = mis_jobs($F);
    $blank = ['jobs'=>0, 'closed'=>0, 'open'=>0, 'mandays'=>0, 'revenue'=>0, 'labour'=>0,
              'expenses'=>0, 'subcon'=>0, 'cost'=>0, 'profit'=>0, 'late'=>0, 'delayDays'=>0,
              'delayCounted'=>0, 'invoiced'=>0, 'paid'=>0];
    $tot = $blank;
    $bySbu = []; $byActivity = []; $byOffice = []; $byInspector = []; $byIbo = [];
    $byClient = []; $byMonth = []; $byBoss = [];
    $seeSalary = function_exists('can_see_salary') ? can_see_salary() : false;
    // Phase 2 §28 — when the financial-truth switch is ON, cost/profit are the canonical engine's
    // (overhead, voucher, other, contingency and the client-recovered credit all included); when OFF
    // (default) they are the historical partial formula, unchanged. Only the two derived totals move —
    // the labour/expenses/subcon component columns stay the direct figures either way.
    $unified = function_exists('finance_truth_unified') ? finance_truth_unified() : false;
    // §28 (P9) — the invoiced figure honours the revenue reader switch. Ledger nets
    // for all these jobs are read once here so the per-job reader stays arithmetic.
    $ledgerMap = function_exists('revrecon_ledger_net_map') ? revrecon_ledger_net_map(array_map(fn($j) => (int)$j['id'], $jobs)) : [];
    $invOf = fn($j) => function_exists('job_invoiced_amount') ? job_invoiced_amount($j, $ledgerMap[(int)$j['id']] ?? 0) : (float)($j['invoice_amount'] ?? 0);

    $add = function (&$bucket, $key, $label, $j, $p, $delay) use ($blank, $unified, $invOf) {
        if ($key === '' || $key === null) { $key = '—'; $label = $label ?: '—'; }
        if (!isset($bucket[$key])) $bucket[$key] = $blank + ['label' => $label];
        $b = &$bucket[$key];
        $b['jobs']++;
        if (!empty($j['closed_flag'])) $b['closed']++; else $b['open']++;
        $b['mandays']  += $p['mandays'];
        $b['revenue']  += $p['credit'];
        $b['labour']   += $p['labour'];
        $b['expenses'] += $p['expenses'];
        $b['subcon']   += $p['subcon'];
        $b['cost']     += $unified ? $p['cost']   : ($p['labour'] + $p['expenses'] + $p['subcon']);
        $b['profit']   += $unified ? $p['profit'] : ($p['credit'] - $p['labour'] - $p['expenses'] - $p['subcon']);
        $b['invoiced'] += $invOf($j);
        $b['paid']     += (float)($j['payment_amount'] ?? 0);
        if ($delay !== null) { $b['delayCounted']++; $b['delayDays'] += $delay; if ($delay > 0) $b['late']++; }
    };

    // Narrowed to one branch, every figure is that branch's share; left wide,
    // it is the company's, and the two always add up.
    $scope = (int)($F['office'] ?? 0) ?: null;
    foreach ($jobs as $j) {
        $p = job_profit($j, $scope);
        $delay = mis_schedule_delay($j);
        $month = substr((string)($j['inspection_end_date'] ?: $j['inspection_start_date'] ?: $j['scheduled_date']), 0, 7);

        $tot['jobs']++;
        if (!empty($j['closed_flag'])) $tot['closed']++; else $tot['open']++;
        $tot['mandays']  += $p['mandays'];
        $tot['revenue']  += $p['credit'];
        $tot['labour']   += $p['labour'];
        $tot['expenses'] += $p['expenses'];
        $tot['subcon']   += $p['subcon'];
        $tot['cost']     += $unified ? $p['cost']   : ($p['labour'] + $p['expenses'] + $p['subcon']);
        $tot['profit']   += $unified ? $p['profit'] : ($p['credit'] - $p['labour'] - $p['expenses'] - $p['subcon']);
        $tot['invoiced'] += $invOf($j);
        $tot['paid']     += (float)($j['payment_amount'] ?? 0);
        if ($delay !== null) { $tot['delayCounted']++; $tot['delayDays'] += $delay; if ($delay > 0) $tot['late']++; }

        $add($bySbu,      (string)$j['sbu'], sbu_label($j['sbu']), $j, $p, $delay);
        $add($byActivity, (string)($j['activity_id'] ?? ''),
             $j['activity_id'] ? (lk_value_path($j['activity_id']) ?: (string)$j['activity_id']) : 'No activity code', $j, $p, $delay);
        $add($byOffice,   (string)($j['executing_office_id'] ?? ''), $j['office_name'] ?: 'No branch', $j, $p, $delay);
        $add($byInspector,(string)($j['inspector_id'] ?? ''), $j['inspector_name'] ?: 'Not allocated', $j, $p, $delay);
        $ibo = (int)($j['contracting_office_id'] ?: $j['ibo_office_id']);
        $add($byIbo,      (string)$ibo, $ibo ? (office_name($ibo) ?: '—') : 'No contracting ' . Tl('office'), $j, $p, $delay);
        $add($byClient,   (string)($j['client_id'] ?? ''),
             trim((string)($j['display_name'] ?: $j['legal_name'])) ?: 'No ' . Tl('client'), $j, $p, $delay);
        $add($byMonth,    $month, $month ? date('M Y', strtotime($month . '-01')) : '—', $j, $p, $delay);
        $add($byBoss,     (string)($j['boss_id'] ?? ''), $j['boss_number'] ?: 'No ' . T('boss') . ' number', $j, $p, $delay);
    }

    // The shared cost of the branch, from the month-end run. Not pushed onto a
    // job — shown alongside, so the Business Unit line can be read both ways.
    $shared = mis_shared_by_sbu($F);

    $sortByProfit = function ($x) { uasort($x, function ($a, $b) { return $b['profit'] <=> $a['profit']; }); return $x; };
    $sortByJobs   = function ($x) { uasort($x, function ($a, $b) { return $b['jobs'] <=> $a['jobs']; }); return $x; };
    ksort($byMonth);

    $avail = mis_available_days($F);
    return [
        'jobs' => $jobs, 'tot' => $tot, 'seeSalary' => $seeSalary,
        'available' => $avail,
        'utilisation' => $avail > 0 ? round($tot['mandays'] / $avail * 100, 1) : null,
        'lastYear' => mis_last_year($F),
        'bySbu' => $sortByProfit($bySbu), 'byActivity' => $sortByProfit($byActivity),
        'byOffice' => $sortByProfit($byOffice), 'byInspector' => $sortByJobs($byInspector),
        'byIbo' => $sortByJobs($byIbo), 'byClient' => $sortByProfit($byClient),
        'byMonth' => $byMonth, 'byBoss' => $sortByProfit($byBoss),
        'shared' => $shared,
        'sharedTotal' => array_sum(array_column($shared, 'amount')),
    ];
}

// How many days the engineers in this period actually had. Utilisation is
// man-days worked against days available — without the second half it is a
// count, not a percentage, and a count cannot tell you whether a branch is
// stretched or idle.
// Utilisation for a date range, using EXACTLY the definition the management
// dashboard already uses — billable engineer-days over available engineer-days.
// Written as its own function so the director's home screen and the MIS screen
// can never drift apart and quote two different numbers for the same thing.
function mis_utilisation($from, $to, $officeId = 0, $inspectorId = 0) {
    $F = ['from' => $from, 'to' => $to, 'office' => (int)$officeId, 'inspector' => (int)$inspectorId];
    $avail = mis_available_days($F);
    // The days actually worked, over the same window and the same scope. The
    // revenue date used elsewhere is the inspection end, so it is used here too.
    [$w, $a] = scope_clause('j.executing_office_id', 'j.sbu');
    $rd = "COALESCE(NULLIF(j.inspection_end_date,''), NULLIF(j.scheduled_date,''), j.created_at)";
    $sql = "SELECT * FROM jobs j WHERE $w AND $rd >= ? AND $rd <= ?";
    $args = array_merge($a, [$from, $to]);
    if ($officeId)    { $sql .= " AND j.executing_office_id = ?"; $args[] = (int)$officeId; }
    if ($inspectorId) { $sql .= " AND j.inspector_id = ?";        $args[] = (int)$inspectorId; }
    $worked = 0.0;
    foreach (ops_all($sql, $args) as $j) $worked += (float)job_mandays($j);
    return [
        'worked'    => round($worked, 1),
        'available' => $avail,
        'pct'       => $avail > 0 ? round($worked / $avail * 100, 1) : null,
    ];
}

function mis_available_days(array $F) {
    // The engineers who appear in the period, or every active one when the
    // filter does not name a person.
    $w = ["status='ACTIVE'"]; $a = [];
    if ($F['inspector']) { $w[] = 'id = ?'; $a[] = $F['inspector']; }
    if ($F['office'])    { $w[] = 'home_office_id = ?'; $a[] = $F['office']; }
    $n = (int)ops_val("SELECT COUNT(*) FROM inspectors WHERE " . implode(' AND ', $w), $a);
    if (!$n) return 0;
    // Working days across the months the filter covers.
    $days = 0;
    $ts = strtotime($F['from']); $end = strtotime($F['to']);
    while ($ts <= $end) {
        $days += working_days_in_month((int)date('Y', $ts), (int)date('n', $ts));
        $ts = strtotime(date('Y-m-01', $ts) . ' +1 month');
    }
    return $n * $days;
}

// The same period, one year earlier. Everything else about the filter is kept,
// so "up on last year" means up on the same slice of last year and not on some
// other set of work.
function mis_last_year(array $F) {
    $back = function ($d) { return date('Y-m-d', strtotime($d . ' -1 year')); };
    $P = $F;
    $P['from'] = $back($F['from']);
    $P['to']   = $back($F['to']);
    $jobs = mis_jobs($P);
    $out = ['jobs' => 0, 'revenue' => 0, 'cost' => 0, 'profit' => 0, 'mandays' => 0,
            'from' => $P['from'], 'to' => $P['to']];
    $scope = (int)($P['office'] ?? 0) ?: null;
    foreach ($jobs as $j) {
        $p = job_profit($j, $scope);
        $out['jobs']++;
        $out['mandays'] += $p['mandays'];
        $out['revenue'] += $p['credit'];
        $out['cost']    += $p['labour'] + $p['expenses'] + $p['subcon'];
        $out['profit']  += $p['credit'] - $p['labour'] - $p['expenses'] - $p['subcon'];
    }
    return $out;
}
// "+18.4%" / "−6%" / '' when there is nothing to compare against.
function mis_change($now, $then) {
    $then = (float)$then;
    if (abs($then) < 0.005) return null;
    return round((((float)$now - $then) / abs($then)) * 100, 1);
}

// What the month-end run allocated to each Business Unit over the filtered period. This
// is the branch manager, the coordinator, the rent — the costs that belong to
// a business unit but not to any one job.
function mis_shared_by_sbu(array $F) {
    $months = [];
    $ts = strtotime($F['from']); $end = strtotime($F['to']);
    while ($ts <= $end) { $months[] = [(int)date('Y', $ts), (int)date('n', $ts)]; $ts = strtotime(date('Y-m-01', $ts) . ' +1 month'); }
    if (!$months) return [];
    $ors = []; $args = [];
    foreach ($months as [$y, $m]) { $ors[] = '(yr=? AND mon=?)'; $args[] = $y; $args[] = $m; }
    $w = '(' . implode(' OR ', $ors) . ')';
    if ($F['office']) { $w .= ' AND office_id = ?'; $args[] = $F['office']; }
    if ($F['sbu'] !== '') { $w .= ' AND sbu = ?'; $args[] = $F['sbu']; }
    // Only the costs that are NOT already on a job. A worked day and a
    // sub-contractor are both in the direct cost above; adding them again here
    // is the double count this screen exists to avoid.
    $w .= " AND source_kind NOT IN ('INSPECTOR_WORKED', 'SUBCON')";
    $rows = ops_all("SELECT sbu, COALESCE(SUM(amount),0) amount FROM cost_allocations WHERE $w GROUP BY sbu", $args);
    $out = [];
    foreach ($rows as $r) if ((float)$r['amount'] != 0) $out[(string)$r['sbu']] = ['amount' => (float)$r['amount']];
    return $out;
}

function sbu_label($code) {
    static $opts = null;
    if ($opts === null) $opts = lk_options_or('sbu', OPS_SBUS);
    $code = (string)$code;
    return $code === '' ? 'No ' . Tl('sbu') : ($opts[$code] ?? $code);
}
function office_name($id) {
    static $cache = [];
    $id = (int)$id; if (!$id) return '';
    if (!array_key_exists($id, $cache)) $cache[$id] = (string)ops_val("SELECT name FROM offices WHERE id=?", [$id]);
    return $cache[$id];
}

// ---------------------------------------------------------------------------
//  The screen
// ---------------------------------------------------------------------------
function ops_mis($method) {
    ops_require(can('dash.operations') || can('dash.financial') || is_admin_level(),
                'You cannot see the management dashboard.');
    $F = mis_filters();
    $S = mis_summary($F);

    if (($_GET['export'] ?? '') !== '') {
        mis_export($_GET['export'], $F, $S);
        return;
    }

    view('ops/mis', [
        'F' => $F, 'S' => $S,
        'sbuOpts' => lk_options_or('sbu', OPS_SBUS),
        'activityOpts' => mis_activity_options(),
        'offices' => ops_all("SELECT id, name, code FROM offices WHERE COALESCE(is_active,1)=1 ORDER BY is_ahmedabad DESC, name"),
        'inspectors' => ops_all("SELECT id, name FROM inspectors ORDER BY name"),
        'clients' => ops_all("SELECT id, legal_name, display_name FROM business_partners WHERE is_client=1 ORDER BY legal_name"),
    ]);
}

// Activity codes that have actually been used, so the filter is not a list of
// every code ever invented.
function mis_activity_options() {
    $rows = ops_all("SELECT DISTINCT activity_id FROM jobs WHERE activity_id IS NOT NULL");
    $out = [];
    foreach ($rows as $r) {
        $lbl = lk_value_path($r['activity_id']);
        if ($lbl) $out[(string)$r['activity_id']] = $lbl;
    }
    asort($out);
    return $out;
}

// Any table on the screen, as a spreadsheet. Same numbers, same filters — a
// figure that cannot be exported is a figure somebody will retype by hand.
function mis_export($what, array $F, array $S) {
    $sets = [
        'sbu'       => ['bySbu', T('sbu')],
        'activity'  => ['byActivity', 'Activity code'],
        'office'    => ['byOffice', T('office')],
        'inspector' => ['byInspector', TH('engineer')],
        'ibo'       => ['byIbo', 'Contracting ' . Tl('office')],
        'client'    => ['byClient', T('client')],
        'month'     => ['byMonth', 'Month'],
        'boss'      => ['byBoss', T('boss')],
    ];
    if (!isset($sets[$what])) { flash('Nothing to export.', 'error'); redirect('/mis'); }
    [$key, $label] = $sets[$what];
    $seeSalary = $S['seeSalary'];
    $head = [$label, 'Inspections', 'Closed', 'Open', 'Man-days', 'Revenue', 'Late', 'Avg delay (days)'];
    if ($seeSalary) $head = array_merge($head, ['Engineer time', 'Expenses', 'Sub-contractor', 'Total direct cost', 'Profit', 'Margin %']);
    $rows = [$head];
    foreach ($S[$key] as $r) {
        $line = [$r['label'], $r['jobs'], $r['closed'], $r['open'], round($r['mandays'], 2), round($r['revenue'], 2),
                 $r['late'], $r['delayCounted'] ? round($r['delayDays'] / $r['delayCounted'], 1) : ''];
        if ($seeSalary) $line = array_merge($line, [round($r['labour'], 2), round($r['expenses'], 2),
            round($r['subcon'], 2), round($r['cost'], 2), round($r['profit'], 2),
            $r['revenue'] > 0 ? round($r['profit'] / $r['revenue'] * 100, 1) : '']);
        $rows[] = $line;
    }
    $name = 'mis-' . $what . '-' . $F['fy'] . ($F['m'] !== '' ? '-' . $F['m'] : '') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    echo "\xEF\xBB\xBF";
    $fh = fopen('php://output', 'w');
    foreach ($rows as $r) fputcsv($fh, $r);
    fclose($fh);
}
