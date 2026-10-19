<?php
/**
 * EXAACT audit — Phase F, dashboard & KPI reconciliation (07-DASHBOARD §9 steps 1-2).
 *
 * One known month is written, then the SAME business figures are read the way each
 * screen reads them: /mis (mis_summary), /analytics (TAPI metrics), the contract
 * view (boss_profit), the home-screen money rollup (financial_rollup) and the books
 * ledger (books_outstanding). Any two that differ for the same question is a finding.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/kpi-reconcile-probe.php
 *
 * BEFORE (19 Oct 2026, first run) — four disagreements for the same July:
 *   A  /mis 3 jobs, 8 man-days;  /analytics 2 jobs, 2 man-days  (TAPI counted by
 *      scheduled date and summed the raw man-days column)            → GAP-041
 *   B  ledger mode: /mis revenue 6700 beside invoiced 3700 on ONE screen; /analytics
 *      6000 and the contract view 6700 in every mode (neither read the switch) → GAP-040
 *   C  home rollup outstanding 0; ledger outstanding 3000 (the rollup subtracted
 *      money received, the ledger money matched)                        → GAP-043
 *   D  /reports per-person denominator = TODAY's month's working days, not the
 *      period's (equal by coincidence in this run: both 27)             → GAP-042
 * AFTER (same day): A, B, C read identically on every screen in every mode; D's
 *   denominator is the period's. Guard rail: tests/test_kpi_reconcile.php.
 * The 3700 vs 3000 that remains in ledger mode is BY DESIGN: J3 was never invoiced
 * through the books, and the reader never zeroes a snapshot the books do not carry.
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';
books_migrate(); tapi_migrate();
db()->beginTransaction();
$mode0 = revenue_reader_mode();
try {
    // ---- the month: July 2099, one office, one SBU, one engineer, one client ------
    db()->prepare("INSERT INTO offices (name,is_active) VALUES ('Recon HQ',1)")->execute(); $off = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name,status,home_office_id,created_at) VALUES ('Recon Eng','ACTIVE',?,?)")->execute([$off, date('c')]); $ins = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state,gstin) VALUES ('Recon Co','Recon Co',1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute(); $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO boss_numbers (client_id, boss_number, status) VALUES (?, 'CN-RECON', 'ACTIVE')")->execute([$cid]); $boss = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (client_id, call_code, executing_office_id, contracting_office_id, sbu, created_at) VALUES (?,?,?,?,?,?)")->execute([$cid, 'C-RECON', $off, $off, 'S1', date('c')]); $call = (int)db()->lastInsertId();
    $job = function ($code, $sched, $start, $end, $mandaysCol, $legacy, $closed) use ($call, $off, $ins, $boss) {
        db()->prepare("INSERT INTO jobs (call_id, boss_id, job_code, executing_office_id, contracting_office_id, sbu, inspector_id, scheduled_date, inspection_start_date, inspection_end_date, mandays, closed_flag, invoice_raised, invoice_amount, created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$call, $boss, $code, $off, $off, 'S1', $ins, $sched, $start, $end, $mandaysCol, $closed, $legacy > 0 ? 1 : 0, $legacy, '2099-06-01T00:00:00+00:00']);
        return (int)db()->lastInsertId();
    };
    //            code    scheduled    start        end          mandays legacy closed
    $j1 = $job('J-R1', '2099-07-01', '2099-07-01', '2099-07-03', null,   5000,  1);   // 3-day span, mandays column BLANK; ledger says 2000
    $j2 = $job('J-R2', '2099-07-10', '2099-07-10', '2099-07-11', 2,      1000,  1);   // ledger agrees (1000)
    $j3 = $job('J-R3', '2099-06-30', '2099-06-30', '2099-07-02', 3,       700,  0);   // scheduled in JUNE, finished in JULY; not in the books
    $invoice = function ($jobId, $net) use ($cid, $off) {
        db()->prepare("INSERT INTO invoices (invoice_no, partner_id, office_id, status, invoice_date, total, created_at) VALUES (?,?,?,'ISSUED',?,?,?)")
            ->execute(['INV-R-' . $jobId, $cid, $off, '2099-07-15', $net, date('c')]);
        $inv = (int)db()->lastInsertId();
        db()->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount, line_total) VALUES (?,?,?,?)")->execute([$inv, $jobId, $net, $net]);
    };
    $invoice($j1, 2000); $invoice($j2, 1000);
    // Money arrived, not yet matched to an invoice.
    db()->prepare("INSERT INTO receipts (receipt_no, partner_id, office_id, receipt_date, amount, created_at) VALUES ('RCP-R-1', ?, ?, '2099-07-20', 3000, ?)")->execute([$cid, $off, date('c')]);

    // ---- the hand-computed truth for July 2099 -------------------------------------
    echo "HAND  jobs finishing in July: 3 (J1, J2, J3)   closed: 2   man-days: 3 + 2 + 3 = 8\n";
    echo "HAND  billed per the books (what customers were actually invoiced): 2000 + 1000 = 3000; legacy job snapshots: 5000 + 1000 + 700 = 6700\n";
    echo "HAND  ledger: billed 3000, received 3000 but UNALLOCATED → outstanding 3000, on account 3000\n\n";

    $F = ['from' => '2099-07-01', 'to' => '2099-07-31', 'sbu' => '', 'activity' => '', 'office' => 0, 'ibo' => 0, 'inspector' => 0, 'client' => 0];
    $ctx = ['from' => '2099-07-01', 'to' => '2099-07-31'];
    $row = function ($label, array $cells) { printf("  %-34s %s\n", $label, implode('   ', array_map(fn($k, $v) => "$k=" . (is_null($v) ? 'null' : $v), array_keys($cells), $cells))); };

    echo "A. COUNTS AND MAN-DAYS — /mis vs /analytics for the same month\n";
    $m = mis_summary($F)['tot'];
    $row('/mis (mis_summary)',   ['jobs' => $m['jobs'], 'closed' => $m['closed'], 'mandays' => $m['mandays']]);
    $row('/analytics (TAPI metrics)', ['jobs' => tapi_metric_value('jobs.total', $ctx), 'closed' => tapi_metric_value('jobs.closed', $ctx), 'mandays' => tapi_metric_value('jobs.mandays', $ctx)]);
    echo "  (J3 is scheduled in June and finished in July; J1's man-days column is blank with a 3-day span.)\n\n";

    echo "B. REVENUE / INVOICED — every reader, under each revenue reader mode\n";
    foreach (['legacy', 'reconciled', 'ledger'] as $mode) {
        revenue_reader_set_mode($mode);
        $m = mis_summary($F)['tot']; $bp = boss_profit($boss);
        $row("mode=$mode  /mis", ['revenue' => $m['revenue'], 'invoiced' => $m['invoiced']]);
        $row("mode=$mode  /analytics", ['revenue.invoiced' => tapi_metric_value('revenue.invoiced', $ctx)]);
        $row("mode=$mode  contract view (boss_profit)", ['revenue' => $bp['revenue'], 'invoiced' => $bp['invoiced']]);
    }
    revenue_reader_set_mode($mode0);
    echo "  (The books carry 3000; J3's 700 was never invoiced through the books and the reader keeps its snapshot by design.)\n\n";

    echo "C. OUTSTANDING — home screen rollup vs the ledger\n";
    $roll = financial_rollup([]); $led = books_outstanding($cid);
    $row('home (financial_rollup)', ['billed' => $roll['net_billed'], 'received' => $roll['received'], 'outstanding' => $roll['outstanding']]);
    $row('ledger (books_outstanding)', ['billed' => $led['billed'], 'settled' => $led['settled'], 'outstanding' => $led['outstanding'], 'on_account' => $led['on_account']]);
    printf("  (unmatched on the home rollup: %s)\n\n", $roll['unmatched']);

    echo "D. UTILISATION — three implementations, same month\n";
    $s = mis_summary($F);
    $u = mis_utilisation('2099-07-01', '2099-07-31');
    $jobsForReports = array_map(fn($j) => $j + ['ins_id' => $j['inspector_id']], mis_jobs($F));
    $wdNow = working_days_in_month((int)date('Y'), (int)date('n'));
    $wdJul = working_days_in_month(2099, 7);
    $pu = mis_person_utilisation($jobsForReports, $wdNow);
    $me = null; foreach ($pu as $p) if ($p['name'] === 'Recon Eng') $me = $p;
    $row('/mis (mis_summary)', ['mandays' => $s['tot']['mandays'], 'available' => $s['available'], 'pct' => $s['utilisation']]);
    $row('/reports company (mis_utilisation)', ['worked' => $u['worked'], 'available' => $u['available'], 'pct' => $u['pct']]);
    $row('/reports per-person (mis_person_utilisation)', ['mandays' => $me['mandays'] ?? null, 'working' => $me['working'] ?? null, 'pct' => $me['pct'] ?? null]);
    printf("  (today's month has %d working days, July 2099 has %d; /reports now divides by the period reported — working_days_between)\n", $wdNow, $wdJul);
} finally {
    revenue_reader_set_mode($mode0);
    db()->rollBack();
}
