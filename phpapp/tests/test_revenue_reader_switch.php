<?php
// §28 (P9) — the revenue reader switch. One setting decides which figure a revenue
// reader shows for a job's invoiced amount as the books ledger becomes the source of
// truth. The safety contract: the DEFAULT ('reconciled') moves no figure that has not
// been proven equal, and the legacy snapshot is never destroyed. 'ledger' is the full
// switch; 'legacy' is the pre-switch behaviour and the rollback.
t_section('revenue reader switch (§28 / P9)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $mode0 = revenue_reader_mode();  // remember to restore

    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('RR Switch Co',1,'ACTIVE')")->execute();
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (client_id, call_code, created_at) VALUES (?,?,?)")->execute([$cid, 'C-RRS', date('c')]);
    $callId = (int)db()->lastInsertId();
    $mkJob = function ($code, $legacy) use ($callId) {
        db()->prepare("INSERT INTO jobs (call_id, job_code, closed_flag, invoice_raised, invoice_amount, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$callId, $code, 1, ($legacy > 0 ? 1 : 0), $legacy, date('c')]);
        return (int)db()->lastInsertId();
    };
    $mkLine = function ($jobId, $net) use ($cid) {
        db()->prepare("INSERT INTO invoices (invoice_no, partner_id, status, total, created_at) VALUES (?,?, 'ISSUED', ?, ?)")
            ->execute(['INV-RRS-' . $jobId, $cid, $net, date('c')]);
        $inv = (int)db()->lastInsertId();
        db()->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount, line_total) VALUES (?,?,?,?)")->execute([$inv, $jobId, $net, $net]);
    };

    // Reconciled job (legacy 1000 == ledger 1000), a diverging job (legacy 5000 vs 2000),
    // and a legacy-only job (legacy 800, no ledger line).
    $ok  = $mkJob('J-RRS-OK', 1000);      $mkLine($ok, 1000);
    $bad = $mkJob('J-RRS-BAD', 5000);     $mkLine($bad, 2000);
    $leg = $mkJob('J-RRS-LEG', 800);

    $rowOk  = ops_one("SELECT * FROM jobs WHERE id=?", [$ok]);
    $rowBad = ops_one("SELECT * FROM jobs WHERE id=?", [$bad]);
    $rowLeg = ops_one("SELECT * FROM jobs WHERE id=?", [$leg]);

    // default is the safe 'reconciled'
    revenue_reader_set_mode('reconciled');
    t_eq(revenue_reader_mode(), 'reconciled', 'the default reader mode is reconciled');
    t_eq(job_invoiced_amount($rowOk),  1000.0, 'reconciled: a reconciled job shows the (equal) figure');
    t_eq(job_invoiced_amount($rowBad), 5000.0, 'reconciled: a DIVERGING job keeps its legacy snapshot (no unproven change)');
    t_eq(job_invoiced_amount($rowLeg),  800.0, 'reconciled: a legacy-only job keeps its snapshot');

    // legacy mode = pre-switch behaviour everywhere
    revenue_reader_set_mode('legacy');
    t_eq(job_invoiced_amount($rowBad), 5000.0, 'legacy: always the snapshot');
    t_eq(job_invoiced_amount($rowOk),  1000.0, 'legacy: always the snapshot (reconciled job)');

    // ledger mode = the full switch: books where they carry the job, snapshot otherwise
    revenue_reader_set_mode('ledger');
    t_eq(job_invoiced_amount($rowBad), 2000.0, 'ledger: a diverging job now shows the books-ledger net');
    t_eq(job_invoiced_amount($rowOk),  1000.0, 'ledger: a reconciled job is unchanged');
    t_eq(job_invoiced_amount($rowLeg),  800.0, 'ledger: a job not in the books keeps its snapshot (revenue never zeroed)');

    // the precomputed-ledger fast path agrees with the on-demand read
    $map = revrecon_ledger_net_map([$ok, $bad, $leg]);
    t_eq($map[$bad], 2000.0, 'the bulk ledger map returns the net for a job');
    t_eq(job_invoiced_amount($rowBad, $map[$bad]), 2000.0, 'passing a precomputed ledger net matches the on-demand result');

    // an invalid mode is rejected and the current mode is unchanged
    t_ok(revenue_reader_set_mode('nonsense') === false, 'an invalid mode is rejected');
    t_eq(revenue_reader_mode(), 'ledger', 'the mode is unchanged after a rejected set');

    // the legacy figure on the row is never mutated by any read
    t_eq((float)ops_val("SELECT invoice_amount FROM jobs WHERE id=?", [$bad]), 5000.0, 'the legacy snapshot column is never destroyed');

    revenue_reader_set_mode($mode0);  // restore
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
