<?php
// (a) "Raise GST invoice from this job" is idempotent: a job already on an invoice
//     opens that invoice instead of raising a second, empty one.
// (b) A voucher line can carry a note/remark (so an incidental / "Others" expense
//     can say what it was for).
t_section('invoice-from-job is idempotent; voucher lines carry a note');

// --- (a) books_job_invoiced is the guard the handler uses ----------------------
if (function_exists('books_invoice_create') && function_exists('books_line_add') && function_exists('books_job_invoiced')) {
    $pdo = db();
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name) VALUES ('Inv Client Ltd','Inv Client')")->execute();
    $clientId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, closed_flag, invoice_value, created_at) VALUES ('INV-J1', 1, 5000, ?)")->execute([date('c')]);
    $jobId = (int)$pdo->lastInsertId();

    $inv = books_invoice_create(['partner_id' => $clientId]);
    t_ok(empty($inv['err']) && !empty($inv['id']), 'a draft invoice is created for the job');
    $err = books_line_add((int)$inv['id'], ['job_id' => $jobId]);
    t_ok($err === '', 'the job line is added to the first invoice');
    t_ok(books_job_invoiced($jobId) === true, 'the job now reads as invoiced — the handler will open this invoice, not raise a new blank one');
    $pdo->prepare("INSERT INTO jobs (job_code, closed_flag, created_at) VALUES ('INV-J2', 1, ?)")->execute([date('c')]);
    t_ok(books_job_invoiced((int)$pdo->lastInsertId()) === false, 'a different, un-billed job is not treated as invoiced');
}

// The handler guards on an existing invoice before creating a new one.
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, 'already billed on invoice') !== false, 'the job-bill handler opens the existing invoice instead of a blank new one');

// --- (b) voucher note field ---------------------------------------------------
$vd = file_get_contents(__DIR__ . '/../views/ops/voucher_detail.php');
t_ok(preg_match('/name="[^"]*\[note\]"/', $vd) === 1, 'each voucher line has a note input');
t_ok(strpos($ops, 'notes=?') !== false && strpos($ops, "\$r['note']") !== false, 'the voucher save persists the per-line note');
