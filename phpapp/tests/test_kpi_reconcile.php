<?php
// ============================================================================
//  One month, every screen, the same numbers. (GAP-040 … GAP-043)
//
//  /mis, /analytics (TAPI), the contract view and the home-screen money rollup
//  must agree with each other and with the ledger for the same question. Found
//  by docs/audit-2026-10/evidence/kpi-reconcile-probe.php. (t_eq is t_eq($got,$want).)
// ============================================================================
t_section('dashboards reconcile — /mis, /analytics, contract view, home rollup, ledger');
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$mode0 = revenue_reader_mode();
try {
    books_migrate(); tapi_migrate();
    db()->prepare("INSERT INTO offices (name,is_active) VALUES ('Recon HQ',1)")->execute(); $off = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name,status,home_office_id,created_at) VALUES ('Recon Eng','ACTIVE',?,?)")->execute([$off, date('c')]); $ins = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Recon Co','Recon Co',1,'ACTIVE')")->execute(); $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO boss_numbers (client_id, boss_number, status) VALUES (?, 'CN-RECON', 'ACTIVE')")->execute([$cid]); $boss = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (client_id, call_code, executing_office_id, contracting_office_id, sbu, created_at) VALUES (?,?,?,?,?,?)")->execute([$cid, 'C-RECON', $off, $off, 'S1', date('c')]); $call = (int)db()->lastInsertId();
    $job = function ($code, $sched, $start, $end, $mandaysCol, $legacy, $closed) use ($call, $off, $ins, $boss) {
        db()->prepare("INSERT INTO jobs (call_id, boss_id, job_code, executing_office_id, contracting_office_id, sbu, inspector_id, scheduled_date, inspection_start_date, inspection_end_date, mandays, closed_flag, invoice_raised, invoice_amount, created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$call, $boss, $code, $off, $off, 'S1', $ins, $sched, $start, $end, $mandaysCol, $closed, $legacy > 0 ? 1 : 0, $legacy, '2098-06-01T00:00:00+00:00']);
        return (int)db()->lastInsertId();
    };
    // A far-future month no other test touches. J1: blank man-days column, 3-day span,
    // books say 2000 against a 5000 snapshot. J2: agrees. J3: scheduled in June, finished
    // in July, never in the books.
    $j1 = $job('J-R1', '2098-07-01', '2098-07-01', '2098-07-03', null, 5000, 1);
    $j2 = $job('J-R2', '2098-07-10', '2098-07-10', '2098-07-11', 2,    1000, 1);
    $j3 = $job('J-R3', '2098-06-30', '2098-06-30', '2098-07-02', 3,     700, 0);
    $invoice = function ($jobId, $net) use ($cid, $off) {
        db()->prepare("INSERT INTO invoices (invoice_no, partner_id, office_id, status, invoice_date, total, created_at) VALUES (?,?,?,'ISSUED',?,?,?)")
            ->execute(['INV-R-' . $jobId, $cid, $off, '2098-07-15', $net, date('c')]);
        $inv = (int)db()->lastInsertId();
        db()->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount, line_total) VALUES (?,?,?,?)")->execute([$inv, $jobId, $net, $net]);
    };
    $invoice($j1, 2000); $invoice($j2, 1000);
    db()->prepare("INSERT INTO receipts (receipt_no, partner_id, office_id, receipt_date, amount, created_at) VALUES ('RCP-R-1', ?, ?, '2098-07-20', 3000, ?)")->execute([$cid, $off, date('c')]);

    $F = ['from' => '2098-07-01', 'to' => '2098-07-31', 'sbu' => '', 'activity' => '', 'office' => $off, 'ibo' => 0, 'inspector' => 0, 'client' => 0];

    // ---- A. counts and man-days ------------------------------------------------
    $m = mis_summary($F)['tot'];
    t_eq([$m['jobs'], $m['closed'], (float)$m['mandays']], [3, 2, 8.0], '/mis: 3 jobs finishing in July, 2 closed, 8 man-days (blank column = date span)');
    $tapiJobs = (int)ops_val("SELECT COUNT(*) FROM jobs j WHERE j.executing_office_id=? AND " . tapi_job_date_expr() . " BETWEEN '2098-07-01' AND '2098-07-31'", [$off]);
    t_eq($tapiJobs, 3, '/analytics counts jobs by the SAME date as /mis (inspection end, else start, else scheduled)');
    t_ok(strpos(tapi_job_date_expr(), 'inspection_end_date') !== false && strpos(tapi_job_date_expr(), 'scheduled_date') !== false, 'the shared date expression names end date first and scheduled last');
    $md = 0.0;
    foreach (ops_all("SELECT j.id, j.mandays, j.inspection_start_date, j.inspection_end_date FROM jobs j WHERE j.executing_office_id=? AND " . tapi_job_date_expr() . " BETWEEN '2098-07-01' AND '2098-07-31'", [$off]) as $j) $md += job_mandays($j);
    t_eq($md, 8.0, 'man-days summed the way the TAPI resolver now does (job_mandays per row) = /mis');
    t_eq(tapi_metric_def('jobs.mandays')['method'], 'SUM(job_mandays(job)) in period — as /mis', 'the metric declares the shared method');
    t_ok(strpos(tapi_metric_def('revenue.invoiced')['method'], 'revenue reader switch') !== false, '...and the invoiced metric declares that it follows the switch');

    // ---- B. revenue / invoiced under every reader mode --------------------------
    foreach (['legacy' => 6700.0, 'reconciled' => 6700.0, 'ledger' => 3700.0] as $mode => $want) {
        revenue_reader_set_mode($mode);
        $m = mis_summary($F)['tot']; $bp = boss_profit($boss);
        t_eq([(float)$m['revenue'], (float)$m['invoiced']], [$want, $want], "mode=$mode: /mis revenue and invoiced read the same source ($want)");
        t_eq([(float)$bp['revenue'], (float)$bp['invoiced']], [$want, $want], "mode=$mode: the contract view agrees");
        $rows = ops_all("SELECT j.id, j.invoice_amount FROM jobs j WHERE j.executing_office_id=?", [$off]);
        $map = revrecon_ledger_net_map(array_map(fn($j) => (int)$j['id'], $rows)); $sum = 0.0;
        foreach ($rows as $j) $sum += (float)job_invoiced_amount($j, $map[(int)$j['id']] ?? 0.0);
        t_eq($sum, $want, "mode=$mode: the analytics figure (per-row job_invoiced_amount) agrees");
        $r1 = ops_one("SELECT * FROM jobs WHERE id=?", [$j1]);
        t_eq((float)job_money($r1)['invoice'], $mode === 'ledger' ? 2000.0 : 5000.0, "mode=$mode: job_money on the diverging job reads " . ($mode === 'ledger' ? 'the books' : 'the snapshot'));
        t_eq((float)job_money($r1 + ['_ledger_net' => 2000.0])['invoice'], $mode === 'ledger' ? 2000.0 : 5000.0, "mode=$mode: ...and the primed read agrees with the on-demand one");
    }
    revenue_reader_set_mode($mode0);

    // ---- C. outstanding: home rollup vs ledger ---------------------------------------
    $roll = financial_rollup(['partner_id' => $cid]); $led = books_outstanding($cid);
    t_eq((float)$roll['outstanding'], (float)$led['outstanding'], 'home rollup outstanding = ledger outstanding (3000, nothing matched yet)');
    t_eq((float)$roll['unmatched'], (float)$led['on_account'], 'the rollup shows the unmatched 3000 as its own figure = the ledger\'s on-account');
    t_eq((float)$roll['outstanding'], 3000.0, '...and it is 3000, not 0');

    // ---- D. the per-person denominator is the period reported ---------------------------
    t_eq(working_days_between('2098-07-01', '2098-07-31'), working_days_in_month(2098, 7), 'one month: working_days_between = that month');
    t_eq(working_days_between('2098-04-01', '2099-03-31'), array_sum(array_map(fn($m) => working_days_in_month($m >= 4 ? 2098 : 2099, $m), range(1, 12))), 'a financial year: the sum of its twelve months');
    t_ok(working_days_between('2098-04-01', '2099-03-31') > 2 * working_days_in_month(2098, 7), 'a year is many months, never one');
    t_eq(working_days_between('2098-07-31', '2098-07-01'), 0, 'an inverted window is 0, not negative');
    $src = file_get_contents(__DIR__ . '/../lib/ops.php');
    t_ok(preg_match('/UTILIZATION ----.{0,600}working_days_between\(\$fyFrom, \$fyTo\)/s', $src) === 1, '/reports divides per-person man-days by the PERIOD\'s working days, not today\'s month');

    // ---- E. margin has one definition ------------------------------------------------
    t_eq(mis_margin_pct(10000, 2500), 25.0, 'margin = profit / revenue × 100, one decimal');
    t_eq(mis_margin_pct(0, 500), null, 'no revenue → null (shown as "—"), never a divide-by-zero or a bogus 0%');
    t_eq(mis_margin_pct(3, 1), 33.3, 'rounded to one decimal');
    $view = file_get_contents(__DIR__ . '/../views/ops/mis.php');
    t_ok(substr_count($view, 'mis_margin_pct(') === 2 && strpos($view, "round(\$r['profit'] / \$r['revenue']") === false, 'the /mis screen computes margin through the named function, not inline');
} finally { revenue_reader_set_mode($mode0); if ($own && db()->inTransaction()) db()->rollBack(); }
