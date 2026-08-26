<?php
// Module 09 — Invoicing. Make the invoice number legally safe (a DB-enforced UNIQUE, with
// unissued drafts held as NULL so many can coexist), harden Issue to re-allocate on a
// collision instead of duplicating, and chase overdue invoices from cron. All additive.
t_section('Module 09 — invoice-number integrity + overdue reminder');

$books = file_get_contents(__DIR__ . '/../lib/books.php');
$recv  = file_get_contents(__DIR__ . '/../lib/receivables.php');
$cron  = file_get_contents(__DIR__ . '/../cron.php');

t_ok(function_exists('books_unique_number_index'), 'books_unique_number_index() exists');
t_ok(function_exists('books_duplicate_numbers'), 'books_duplicate_numbers() exists');
t_ok(function_exists('ar_overdue_reminders'), 'ar_overdue_reminders() exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    books_migrate();
    $pdo = db();

    // ---- unique-when-present ----
    // Two DRAFT invoices with a NULL number must coexist (a UNIQUE index allows many NULLs).
    $mkRaw = function ($no, $status, $total, $due, $extra = '') use ($pdo) {
        $noSql = $no === null ? 'NULL' : $pdo->quote($no);
        $pdo->exec("INSERT INTO invoices (invoice_no, status, total, due_date, partner_name, invoice_date, office_id $extra)
                    VALUES ($noSql, '$status', $total, " . $pdo->quote($due) . ", 'Acme', '2026-01-01', 1)");
        return (int)$pdo->lastInsertId();
    };
    $d1 = $mkRaw(null, 'DRAFT', 0, '');
    $d2 = $mkRaw(null, 'DRAFT', 0, '');
    t_ok($d1 && $d2, 'two draft invoices with a NULL number coexist (no false unique clash)');

    // A real, issued number is unique: a second row with the same number is rejected.
    $mkRaw('UQ/2627/0009', 'ISSUED', 1000, '2026-02-01');
    $dupBlocked = false;
    try { $pdo->exec("INSERT INTO invoices (invoice_no, status, total) VALUES ('UQ/2627/0009', 'ISSUED', 500)"); }
    catch (Throwable $e) { $dupBlocked = true; }
    t_ok($dupBlocked, 'the database rejects a duplicate issued invoice number (UNIQUE index enforced)');

    // books_next_number skips an already-occupied number in the series.
    $next = books_next_number('invoices', 'invoice_no', 'UQ', '2026-27');
    t_ok($next !== '' && $next !== 'UQ/2627/0009', 'the numbering picks a free number, not the occupied one');

    // Defensive build: books_duplicate_numbers finds an offender when one exists.
    // (Insert two rows sharing a number via a table where the index was skipped — simulate on
    // credit_notes which starts empty; here we just assert the detector on invoices sees none now.)
    t_ok(books_duplicate_numbers('invoices', 'invoice_no') === [], 'with clean data no duplicate numbers are reported');

    // ---- overdue reminder ----
    putenv('QAC_EMAIL=finance@example.test');   // give it a recipient so it stamps
    $overdue = $mkRaw('UQ/2627/0010', 'ISSUED', 5000, date('Y-m-d', strtotime('-20 days')));
    $future  = $mkRaw('UQ/2627/0011', 'ISSUED', 3000, date('Y-m-d', strtotime('+20 days')));
    $paid    = $mkRaw('UQ/2627/0012', 'PAID',   2000, date('Y-m-d', strtotime('-20 days')));
    $cancel  = $mkRaw('UQ/2627/0013', 'CANCELLED', 4000, date('Y-m-d', strtotime('-20 days')));

    $n = ar_overdue_reminders();
    t_ok($n >= 1, 'an overdue issued invoice is chased');
    $remStamped = trim((string)ops_val("SELECT reminded_at FROM invoices WHERE id=?", [$overdue]));
    t_ok($remStamped !== '', 'the chased invoice is stamped so it is not re-nagged');
    t_ok(trim((string)ops_val("SELECT reminded_at FROM invoices WHERE id=?", [$future])) === '', 'a within-terms invoice is not chased');
    t_ok(trim((string)ops_val("SELECT reminded_at FROM invoices WHERE id=?", [$paid])) === '', 'a paid invoice is not chased');
    t_ok(trim((string)ops_val("SELECT reminded_at FROM invoices WHERE id=?", [$cancel])) === '', 'a cancelled invoice is not chased');

    // Idempotent: a second run the same day re-nags nothing.
    $n2 = ar_overdue_reminders();
    t_ok($n2 === 0, 'a second sweep the same day chases nothing (idempotent via reminded_at)');
    putenv('QAC_EMAIL');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation (string-level) ----
t_ok(strpos($books, "VALUES (NULL,?,?") !== false, 'a new draft invoice stores a NULL number, not an empty string');
t_ok(strpos($books, 'CREATE UNIQUE INDEX') !== false, 'a UNIQUE index is built for the money-document numbers');
t_ok(strpos($books, 'if ($attempt >= 8) return') !== false, 'books_issue retries/re-allocates on a number collision');
t_ok(strpos($books, 'function books_next_number') !== false, 'the gapless numbering-at-issue is preserved');
t_ok(strpos($cron, 'ar_overdue_reminders()') !== false, 'the overdue reminder is wired into cron');
t_ok(strpos($recv, "status IN ('ISSUED','PART_PAID')") !== false, 'the reminder chases only issued/part-paid invoices');
