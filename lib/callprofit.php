<?php
// ===========================================================================
//  What each inspection actually made
//
//  A branch manager is judged on the branch, and cannot run it from a monthly
//  total. The question they ask is about one job: this one took six days, we
//  billed the client, the engineer claimed for a hotel — did it make money?
//
//  So the whole sum, one line at a time, for every deputation their branch
//  executed:
//
//      Revenue            what this branch keeps — the invoice, less any
//                         credit passed to the branch that did the work
//    − Engineer's salary  the unloaded daily cost × the days actually worked
//    − Overhead           the branch's percentage on that salary
//    − Expenses at close   what was booked against the job when it closed
//    − Voucher claims      what the engineer claimed on the monthly voucher
//    − Sub-contractor      somebody else's engineer
//    − Anything else       a hired instrument, a permit, a courier
//    − Contingency         the branch's percentage on all of the above
//    ─────────────────────────────────────────────────────────────────────
//      = Profit, and the margin it is as a percentage of revenue
//
//  Scoped: a manager sees the branches they are scoped to and no others. The
//  figures come from job_profit(), the same function the dashboards use, so
//  this screen can never quietly disagree with them.
// ===========================================================================

// Every deputation in scope for the filters given, with its money worked out.
function callprofit_rows(array $F) {
    $w = ["COALESCE(j.inspection_end_date, j.inspection_start_date, j.scheduled_date) >= ?",
          "COALESCE(j.inspection_end_date, j.inspection_start_date, j.scheduled_date) <= ?"];
    $a = [$F['from'], $F['to']];
    if (!empty($F['office']))    { $w[] = "j.executing_office_id = ?"; $a[] = (int)$F['office']; }
    if (!empty($F['inspector'])) { $w[] = "j.inspector_id = ?";        $a[] = (int)$F['inspector']; }
    if (!empty($F['sbu']))       { $w[] = "j.sbu = ?";                 $a[] = $F['sbu']; }
    if (!empty($F['client']))    { $w[] = "c.client_id = ?";           $a[] = (int)$F['client']; }
    // A manager sees their own branches. This is the same scope every register
    // uses, so nobody sees on this screen what they cannot see anywhere else.
    $scope = function_exists('scope_offices') ? scope_offices() : 'ALL';
    if ($scope !== 'ALL' && is_array($scope) && $scope)
        $w[] = "j.executing_office_id IN (" . implode(',', array_map('intval', $scope)) . ")";

    return ops_all("SELECT j.*, c.call_code, c.client_id, c.call_received_date, c.inspection_required_date,
                           i.name inspector_name, o.name office_name,
                           p.legal_name, p.display_name, b.boss_number
                    FROM jobs j
                    LEFT JOIN calls c ON c.id = j.call_id
                    LEFT JOIN inspectors i ON i.id = j.inspector_id
                    LEFT JOIN offices o ON o.id = j.executing_office_id
                    LEFT JOIN business_partners p ON p.id = c.client_id
                    LEFT JOIN boss_numbers b ON b.id = j.boss_id
                    WHERE " . implode(' AND ', $w) . "
                    ORDER BY COALESCE(j.inspection_end_date, j.inspection_start_date, j.scheduled_date) DESC,
                             j.id DESC", $a);
}

// The screen. One row per deputation, every cost line, and the totals.
function ops_call_profit() {
    // The branch manager's own figure, and their managers'. Anybody without it
    // is turned away rather than shown an empty page.
    ops_require(can('data.profitability') || can('data.revenue') || is_admin_level(),
        'Profit on a ' . Tl('call') . ' is not shown to your role. Ask your administrator for '
        . 'the "' . (PERMISSIONS['data.profitability'] ?? 'profitability') . '" permission.');

    $F = mis_filters();
    $F['inspector'] = (int)($_GET['inspector'] ?? 0);
    $F['client']    = (int)($_GET['client'] ?? 0);
    $rows = callprofit_rows($F);

    // Scoped to one branch, the figures are that branch's share. Left wide, they
    // are the company's — and the two add up, because they read the same rule.
    $scopeOffice = (int)($F['office'] ?? 0) ?: null;
    $out = []; $tot = ['revenue'=>0,'labour'=>0,'overhead'=>0,'expenses'=>0,'voucher'=>0,
                       'subcon'=>0,'other'=>0,'contingency'=>0,'cost'=>0,'profit'=>0,'mandays'=>0,'jobs'=>0];
    foreach ($rows as $j) {
        $p = job_profit($j, $scopeOffice);
        $out[] = ['job' => $j, 'p' => $p];
        foreach (['revenue','labour','overhead','expenses','voucher','subcon','other','contingency','cost','profit','mandays'] as $k)
            $tot[$k] += (float)$p[$k];
        $tot['jobs']++;
    }
    $tot['margin'] = $tot['revenue'] > 0 ? round($tot['profit'] / $tot['revenue'] * 100, 1) : null;

    // The worst few, because that is what a manager acts on. A job with no
    // revenue recorded is not a loss — it is a gap — so it is not ranked.
    $losses = array_values(array_filter($out, function ($r) {
        return $r['p']['revenue'] > 0 && $r['p']['profit'] < 0;
    }));
    usort($losses, function ($x, $y) { return $x['p']['profit'] <=> $y['p']['profit']; });

    view('ops/call_profit', [
        'rows' => $out, 'tot' => $tot, 'F' => $F, 'losses' => array_slice($losses, 0, 5),
        'offices' => offices_list(), 'inspectors' => inspectors_list(),
        'scopeOffice' => $scopeOffice,
    ]);
}
