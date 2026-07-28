<?php
// ============================================================================
//  Receivables ageing — how old the money is, not just whether it is late
//
//  Roadmap 5.3. Before this screen "overdue" was a yes/no on the money desk:
//  an invoice eleven days past its date and one eleven months past it wore the
//  same red pill. That is the wrong shape for the only two questions anybody
//  actually asks — *who* owes us, and *how long* have they owed it — because
//  the answer decides who gets a phone call and who gets a letter.
//
//  Three things worth knowing about how this counts:
//
//   1. **Age is measured from the due date, not the invoice date** — by
//      default. An invoice on 60-day terms is not 45 days old in any sense
//      that matters; it is not due yet. Where no due date was recorded the
//      invoice date is used instead, and the screen says so per row rather
//      than quietly mixing two different meanings in one column.
//   2. **Not-yet-due is its own column.** Rolling it into 0–30 makes the top
//      bucket meaningless, and dropping it makes the client totals disagree
//      with the outstanding figure on the money desk.
//   3. **It is the same number as everywhere else.** Outstanding is
//      `invoice_amount` on jobs that are raised and unpaid, scoped to the
//      user's offices — exactly what `ops_invoicing_counts()` sums. Two
//      screens quoting different figures for the same word is the bug this
//      codebase has had to fix more than once.
//
//  Deliberately NOT done: part-payments, credit notes and a promise-to-pay
//  diary. The data model records payment as a flag and one amount; ageing a
//  partly-paid invoice honestly needs a payments table, and inventing one to
//  serve a report would put a second version of the truth next to the first.
// ============================================================================

const AR_BUCKETS = [
    ['key' => 'notdue', 'label' => 'Not yet due', 'lo' => null, 'hi' => 0],
    ['key' => 'b30',    'label' => '0–30 days',   'lo' => 1,    'hi' => 30],
    ['key' => 'b60',    'label' => '31–60 days',  'lo' => 31,   'hi' => 60],
    ['key' => 'b90',    'label' => '61–90 days',  'lo' => 61,   'hi' => 90],
    ['key' => 'b90p',   'label' => '90+ days',    'lo' => 91,   'hi' => null],
];

function ar_can() { return can('finance.reconcile') || can('data.credit') || is_master(); }

function ar_bucket_of($days) {
    if ($days <= 0) return 'notdue';
    if ($days <= 30) return 'b30';
    if ($days <= 60) return 'b60';
    if ($days <= 90) return 'b90';
    return 'b90p';
}

// Every unpaid raised invoice in scope, aged. $basis 'DUE' ages from the due
// date (falling back to the invoice date where none was recorded); 'INVOICE'
// ages everything from the invoice date, which is what a lender asks for.
function ar_rows($basis = 'DUE', $today = null) {
    $today = $today ?: date('Y-m-d');
    [$jw, $ja] = scope_clause('j.executing_office_id', 'j.sbu');
    $rows = ops_all(
        "SELECT j.id, j.job_code, j.invoice_number, j.invoice_date, j.invoice_due_date,
                j.invoice_amount, j.sbu, bp.id client_id, bp.display_name, bp.legal_name,
                o.name office_name
         FROM jobs j
         LEFT JOIN calls c ON c.id = j.call_id
         LEFT JOIN business_partners bp ON bp.id = c.client_id
         LEFT JOIN offices o ON o.id = j.executing_office_id
         WHERE $jw AND j.invoice_raised=1 AND j.payment_received=0
         ORDER BY j.invoice_due_date ASC, j.invoice_date ASC, j.id ASC", $ja);
    $out = [];
    foreach ($rows as $r) {
        $amount = (float)($r['invoice_amount'] ?? 0);
        if ($amount <= 0) continue;   // nothing to age; it shows on the money desk as "confirm the amount"
        $due  = trim((string)$r['invoice_due_date']);
        $inv  = trim((string)$r['invoice_date']);
        $from = ($basis === 'INVOICE') ? $inv : ($due !== '' ? $due : $inv);
        if ($from === '') continue;   // undateable — cannot honestly be put in a bucket
        $days = (int)floor((strtotime($today) - strtotime($from)) / 86400);
        $r['amount']   = $amount;
        $r['age_days'] = $days;
        $r['age_from'] = $from;
        // Said per row, because a register that mixes "45 days past due" with
        // "45 days since invoiced" in one column is not a register.
        $r['estimated'] = ($basis !== 'INVOICE' && $due === '');
        $r['bucket']   = ar_bucket_of($days);
        $out[] = $r;
    }
    return $out;
}

// Rolled up the way it is read: one line per client, one column per bucket.
function ar_by_client(array $rows) {
    $by = [];
    foreach ($rows as $r) {
        $key = (int)($r['client_id'] ?? 0);
        $name = trim((string)($r['display_name'] ?: $r['legal_name'])) ?: 'Unnamed client';
        if (!isset($by[$key])) {
            $by[$key] = ['client_id' => $key, 'name' => $name, 'n' => 0, 'total' => 0.0,
                         'oldest' => 0, 'oldest_inv' => ''];
            foreach (AR_BUCKETS as $b) $by[$key][$b['key']] = 0.0;
        }
        $by[$key]['n']++;
        $by[$key]['total'] += $r['amount'];
        $by[$key][$r['bucket']] += $r['amount'];
        if ($r['age_days'] > $by[$key]['oldest']) {
            $by[$key]['oldest'] = $r['age_days'];
            $by[$key]['oldest_inv'] = (string)($r['invoice_number'] ?: $r['job_code']);
        }
    }
    // Worst first: the biggest exposure at the top is what the call list is.
    uasort($by, fn($a, $b) => $b['total'] <=> $a['total']);
    return array_values($by);
}

function ar_totals(array $clients) {
    $t = ['n' => 0, 'total' => 0.0, 'overdue' => 0.0];
    foreach (AR_BUCKETS as $b) $t[$b['key']] = 0.0;
    foreach ($clients as $c) {
        $t['n'] += $c['n'];
        $t['total'] += $c['total'];
        foreach (AR_BUCKETS as $b) $t[$b['key']] += $c[$b['key']];
    }
    $t['overdue'] = $t['total'] - $t['notdue'];
    return $t;
}

function ops_receivables() {
    ops_require(ar_can(), 'You cannot open receivables ageing.');
    $basis  = ($_GET['basis'] ?? 'DUE') === 'INVOICE' ? 'INVOICE' : 'DUE';
    $client = (int)($_GET['client'] ?? 0);
    $today  = date('Y-m-d');
    $rows    = ar_rows($basis, $today);
    $clients = ar_by_client($rows);
    $totals  = ar_totals($clients);
    $detail  = $client ? array_values(array_filter($rows, fn($r) => (int)($r['client_id'] ?? 0) === $client)) : [];

    if (wants_csv()) {
        $csv = [['Client', 'Invoices', 'Not yet due', '0-30', '31-60', '61-90', '90+', 'Total', 'Oldest (days)']];
        foreach ($clients as $c)
            $csv[] = [$c['name'], $c['n'], $c['notdue'], $c['b30'], $c['b60'], $c['b90'], $c['b90p'], $c['total'], $c['oldest']];
        $csv[] = ['TOTAL', $totals['n'], $totals['notdue'], $totals['b30'], $totals['b60'],
                  $totals['b90'], $totals['b90p'], $totals['total'], ''];
        csv_download('receivables-ageing-' . $today . '.csv', $csv);
    }

    view('ops/receivables', [
        'basis' => $basis, 'clients' => $clients, 'totals' => $totals,
        'detail' => $detail, 'client' => $client, 'today' => $today,
        'clientName' => $client ? (current(array_filter($clients, fn($c) => $c['client_id'] === $client))['name'] ?? '') : '',
    ]);
}
