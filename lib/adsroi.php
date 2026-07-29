<?php
// ============================================================================
//  What the advertising actually earned
//
//  THE NUMBER EVERY OTHER TOOL GETS WRONG. Ask a Meta dashboard what a campaign
//  returned and it answers with conversion value — a figure assembled from
//  pixels, modelled attribution and a 7-day click window. Ask a CRM and it
//  answers with closed-won — a number a salesperson typed into a box on the day
//  they felt confident. Neither is money. Both are systematically optimistic,
//  and marketing budgets are set from them.
//
//  This screen follows one chain and refuses to stop early:
//
//      campaign  →  lead  →  opportunity  →  order  →  invoice  →  RECEIPT
//
//  and reports four separate figures per campaign, never blended:
//
//      SPENT        what Ads Pro paid the platform
//      IN PLAY      open pipeline from those leads — a forecast, labelled as one
//      INVOICED     what was actually billed, from the invoice register
//      COLLECTED    what actually arrived, from the receipt allocations
//
//  The gap between INVOICED and COLLECTED is the one nobody shows you. A campaign
//  that "returns 8x" on invoiced revenue and 2x on collected revenue is not a
//  good campaign; it is a campaign bringing in customers who do not pay, and the
//  correct response is to stop running it — which no advertising dashboard on
//  earth will ever tell you, because none of them can see a bank statement.
//
//  HONESTY RULES, because a return-on-spend figure is exactly the kind of number
//  people quote in board meetings:
//
//    * A campaign with no leads yet shows its spend and a dash — not a zero
//      return. Zero says "it failed"; a dash says "too early", and a two-week-old
//      campaign in a trade with a three-month sales cycle has not failed.
//    * Nothing is apportioned across touchpoints. A lead came from one campaign
//      and that campaign gets the whole of it. Fractional multi-touch models are
//      guesses dressed as arithmetic, and this file will not print a guess in the
//      same column as a receipt.
//    * Revenue counts only where the chain is unbroken and checkable. A won deal
//      with no order, or an order never invoiced, appears as a break — which is
//      itself the finding, and links to the screen that closes it.
// ============================================================================

function roi_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }

function roi_can() { return function_exists('ads_can_view') && ads_can_view(); }

// Is there anything to show at all? The screen and its nav item stay hidden on
// an install that does not advertise, rather than offering an empty report.
function roi_available() {
    if (!function_exists('ads_migrate')) return false;
    ads_migrate();
    $n = (int)(roi_try(fn() => ops_val("SELECT COUNT(*) FROM ads_leads"), 0) ?: 0);
    $s = (int)(roi_try(fn() => ops_val("SELECT COUNT(*) FROM ads_spend"), 0) ?: 0);
    return $n > 0 || $s > 0;
}

// ---- The chain --------------------------------------------------------------
// One row per Ads Pro lead, carrying everything downstream of it. Written as a
// single query per stage rather than a loop of queries, and deliberately LEFT
// JOINed the whole way: a lead that stopped at stage one must still appear, or
// the denominator quietly shrinks and every ratio flatters itself.
function roi_chain($fromDate = '', $toDate = '') {
    ads_migrate();
    $w = []; $a = [];
    if ($fromDate !== '') { $w[] = "al.imported_at >= ?"; $a[] = $fromDate; }
    if ($toDate !== '')   { $w[] = "al.imported_at <= ?"; $a[] = $toDate . 'T23:59:59'; }
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';

    $rows = roi_try(fn() => ops_all(
        "SELECT al.id, al.campaign_id, al.campaign_name, al.platform, al.lead_id,
                al.contact_name, al.imported_at, al.pushed_paid_at,
                l.ref lead_ref, l.company_name, l.status lead_status, l.partner_id
         FROM ads_leads al
         LEFT JOIN leads l ON l.id = al.lead_id
         $where
         ORDER BY al.id DESC LIMIT 2000", $a), []) ?: [];
    if (!$rows) return [];

    // Opportunities converted from those leads.
    $opps = [];
    foreach (roi_try(fn() => ops_all(
        "SELECT o.id, o.lead_id, o.value, o.status, o.call_id FROM opportunities o
         WHERE o.lead_id IS NOT NULL"), []) ?: [] as $o) $opps[(int)$o['lead_id']][] = $o;

    // Invoices, and what has actually been received against them. books_settled()
    // is the same function the ledger and the ageing use, so the three cannot
    // disagree about what a customer owes.
    $invByJob = [];
    if (function_exists('books_migrate')) {
        books_migrate();
        foreach (roi_try(fn() => ops_all(
            "SELECT il.job_id, j.call_id, i.id inv_id, i.invoice_no, i.total, i.status
             FROM invoice_lines il
             JOIN invoices i ON i.id = il.invoice_id
             LEFT JOIN jobs j ON j.id = il.job_id
             WHERE i.status <> 'CANCELLED'"), []) ?: [] as $r)
            if ((int)$r['call_id'] > 0) $invByJob[(int)$r['call_id']][(int)$r['inv_id']] = $r;
    }

    $out = [];
    foreach ($rows as $r) {
        $r['opp_open'] = 0.0; $r['opp_won'] = 0.0; $r['invoiced'] = 0.0; $r['collected'] = 0.0;
        $r['n_opp'] = 0; $r['n_won'] = 0; $r['break'] = '';
        foreach ($opps[(int)$r['lead_id']] ?? [] as $o) {
            $r['n_opp']++;
            if ($o['status'] === 'OPEN') { $r['opp_open'] += (float)$o['value']; continue; }
            if ($o['status'] !== 'WON') continue;
            $r['n_won']++; $r['opp_won'] += (float)$o['value'];
            $call = (int)($o['call_id'] ?? 0);
            if (!$call) { $r['break'] = 'won-no-order'; continue; }
            $invs = $invByJob[$call] ?? [];
            if (!$invs) { $r['break'] = 'order-no-invoice'; continue; }
            foreach ($invs as $inv) {
                $r['invoiced'] += (float)$inv['total'];
                $s = function_exists('books_settled') ? roi_try(fn() => books_settled((int)$inv['inv_id']), null) : null;
                // settled = total less what is still outstanding. Falling back to
                // "nothing received" is the safe direction to be wrong in: it
                // understates the return rather than inventing money.
                $r['collected'] += $s ? max(0.0, (float)$inv['total'] - (float)$s['outstanding']) : 0.0;
            }
            if ($r['invoiced'] > 0 && $r['collected'] <= 0) $r['break'] = 'invoiced-unpaid';
        }
        $out[] = $r;
    }
    return $out;
}

// ---- Rolled up per campaign -------------------------------------------------
function roi_by_campaign($fromDate = '', $toDate = '') {
    ads_migrate();
    $chain = roi_chain($fromDate, $toDate);

    $spend = [];
    $sw = []; $sa = [];
    if ($fromDate !== '') { $sw[] = "metric_date >= ?"; $sa[] = substr($fromDate, 0, 10); }
    if ($toDate !== '')   { $sw[] = "metric_date <= ?"; $sa[] = substr($toDate, 0, 10); }
    $swhere = $sw ? 'WHERE ' . implode(' AND ', $sw) : '';
    foreach (roi_try(fn() => ops_all(
        "SELECT campaign_id, MAX(campaign_name) campaign_name, MAX(platform) platform,
                SUM(spend) spend, SUM(clicks) clicks, SUM(impressions) impressions
         FROM ads_spend $swhere GROUP BY campaign_id", $sa), []) ?: [] as $s)
        $spend[(string)$s['campaign_id']] = $s;

    $by = [];
    $blank = ['campaign_id' => '', 'name' => '', 'platform' => '', 'spend' => 0.0,
              'clicks' => 0, 'leads' => 0, 'opps' => 0, 'won' => 0,
              'in_play' => 0.0, 'won_value' => 0.0, 'invoiced' => 0.0, 'collected' => 0.0,
              'breaks' => []];
    foreach ($chain as $r) {
        $k = (string)$r['campaign_id'];
        if (!isset($by[$k])) {
            $by[$k] = $blank;
            $by[$k]['campaign_id'] = $k;
            $by[$k]['name'] = (string)($r['campaign_name'] ?: ($spend[$k]['campaign_name'] ?? '')) ?: 'No campaign recorded';
            $by[$k]['platform'] = (string)($r['platform'] ?: ($spend[$k]['platform'] ?? ''));
        }
        $by[$k]['leads']++;
        $by[$k]['opps']      += (int)$r['n_opp'];
        $by[$k]['won']       += (int)$r['n_won'];
        $by[$k]['in_play']   += (float)$r['opp_open'];
        $by[$k]['won_value'] += (float)$r['opp_won'];
        $by[$k]['invoiced']  += (float)$r['invoiced'];
        $by[$k]['collected'] += (float)$r['collected'];
        if ($r['break'] !== '') $by[$k]['breaks'][$r['break']] = ($by[$k]['breaks'][$r['break']] ?? 0) + 1;
    }
    // A campaign that cost money and produced no lead at all is the most important
    // row on the screen, so it must not be dropped for having nothing to join to.
    foreach ($spend as $k => $s) {
        if (!isset($by[$k])) {
            $by[$k] = $blank;
            $by[$k]['campaign_id'] = (string)$k;
            $by[$k]['name'] = (string)($s['campaign_name'] ?: 'Campaign ' . $k);
            $by[$k]['platform'] = (string)$s['platform'];
        }
        $by[$k]['spend']  = (float)$s['spend'];
        $by[$k]['clicks'] = (int)$s['clicks'];
    }
    foreach ($by as &$b) {
        $b['cpl']       = $b['leads'] > 0 && $b['spend'] > 0 ? $b['spend'] / $b['leads'] : null;
        // Null, not zero. "No return yet" and "no return" are different claims,
        // and only one of them is grounds for stopping a campaign.
        $b['roi_inv']   = $b['spend'] > 0 && ($b['invoiced'] > 0 || $b['won'] > 0) ? $b['invoiced'] / $b['spend'] : null;
        $b['roi_cash']  = $b['spend'] > 0 && ($b['collected'] > 0 || $b['invoiced'] > 0) ? $b['collected'] / $b['spend'] : null;
        $b['uncollected'] = max(0.0, $b['invoiced'] - $b['collected']);
        $b['too_early'] = $b['spend'] > 0 && $b['leads'] === 0;
    }
    unset($b);
    usort($by, fn($x, $y) => ($y['collected'] <=> $x['collected']) ?: ($y['spend'] <=> $x['spend']));
    return $by;
}

function roi_totals(array $by) {
    $t = ['spend' => 0.0, 'leads' => 0, 'won' => 0, 'in_play' => 0.0,
          'invoiced' => 0.0, 'collected' => 0.0, 'uncollected' => 0.0, 'campaigns' => count($by)];
    foreach ($by as $b) {
        $t['spend']       += $b['spend'];
        $t['leads']       += $b['leads'];
        $t['won']         += $b['won'];
        $t['in_play']     += $b['in_play'];
        $t['invoiced']    += $b['invoiced'];
        $t['collected']   += $b['collected'];
        $t['uncollected'] += $b['uncollected'];
    }
    // The same rule as a single campaign, and it has to be the same rule. Dividing
    // as soon as spend exists prints "0.0× returned" over a month of advertising
    // whose deals are still open — which reads as "it failed" when the honest
    // answer is "nothing has closed yet". Null until something has.
    $closed = $t['invoiced'] > 0 || $t['collected'] > 0 || $t['won'] > 0;
    $t['roi_cash'] = $t['spend'] > 0 && $closed ? $t['collected'] / $t['spend'] : null;
    $t['roi_inv']  = $t['spend'] > 0 && $closed ? $t['invoiced'] / $t['spend'] : null;
    $t['cpl']      = $t['leads'] > 0 && $t['spend'] > 0 ? $t['spend'] / $t['leads'] : null;
    $t['nothing_closed'] = !$closed;
    return $t;
}

// The breaks, named in the words of the screen that fixes each one.
const ROI_BREAKS = [
    'won-no-order'     => ['Won, but no order was ever raised', '/opportunities?v=list'],
    'order-no-invoice' => ['Ordered, but never invoiced', '/to-bill'],
    'invoiced-unpaid'  => ['Invoiced, but nothing has been received', '/receivables'],
];

function ops_adsroi($route, $method) {
    ops_require(roi_can(), 'You cannot open the advertising return.');
    ads_migrate();
    $from = substr((string)($_GET['from'] ?? ''), 0, 10);
    $to   = substr((string)($_GET['to'] ?? ''), 0, 10);
    $by   = roi_by_campaign($from, $to);
    $tot  = roi_totals($by);

    if (wants_csv()) {
        $csv = [['Campaign', 'Platform', 'Spent', 'Leads', 'Cost per lead', 'Deals won',
                 'Open pipeline', 'Invoiced', 'Collected', 'Not yet collected',
                 'Return on invoiced', 'Return on cash']];
        foreach ($by as $b)
            $csv[] = [$b['name'], $b['platform'], $b['spend'], $b['leads'],
                      $b['cpl'] === null ? '' : round($b['cpl'], 2), $b['won'],
                      $b['in_play'], $b['invoiced'], $b['collected'], $b['uncollected'],
                      $b['roi_inv'] === null ? '' : round($b['roi_inv'], 2),
                      $b['roi_cash'] === null ? '' : round($b['roi_cash'], 2)];
        csv_download('advertising-return-' . date('Y-m-d') . '.csv', $csv);
    }

    view('ops/adsroi', ['by' => $by, 'tot' => $tot, 'from' => $from, 'to' => $to,
                        'breaks' => ROI_BREAKS, 'connected' => function_exists('ads_on') && ads_on()]);
    return true;
}
