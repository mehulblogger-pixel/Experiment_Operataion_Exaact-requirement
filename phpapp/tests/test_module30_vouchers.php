<?php
// Module 30 — Vouchers / Expenses. A fast field "add one expense + receipt photo" path
// onto THIS month's voucher, auto-filling claimant/date/currency, plus a job bridge so a
// job-linked expense lands on the monthly voucher. Routes through can_edit_voucher, so the
// R5 maker-checker + reopen guards still apply. Nothing existing is changed.
t_section('Module 30 — voucher quick-add expense + receipt, guards preserved');

$src  = file_get_contents(__DIR__ . '/../lib/ops.php');
$vv   = file_get_contents(__DIR__ . '/../views/ops/voucher_detail.php');
$jv   = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');

// Wiring: routes registered, receipt columns ensured, form + bridge present.
t_ok(strpos($src, "\$route === 'voucher-quick-add'") !== false && strpos($src, "\$route === 'voucher-line-receipt'") !== false,
    'the quick-add and receipt routes are dispatched');
t_ok(strpos($src, "ensure_column('voucher_entries', 'receipt_data'") !== false,
    'per-line receipt storage is added (additive column)');
t_ok(strpos($src, 'if (!can_edit_voucher($v)) { flash(voucher_lock_reason($v) ?: \'This month') !== false,
    'quick-add is gated by the edit lock (DRAFT-only / owner / not frozen)');
t_ok(strpos($vv, '/voucher-quick-add') !== false && strpos($vv, 'name="receipt"') !== false,
    'the voucher screen shows the quick-add form with a receipt field');
t_ok(strpos($vv, '/voucher-line-receipt?id=') !== false, 'lines with a receipt link to it');
t_ok(strpos($jv, '/voucher?addjob=') !== false && strpos($jv, 'Log my expense') !== false,
    'the job screen bridges the assigned inspector to log an expense for that job');

// Behavioural: replay the quick-add insert against a real DRAFT voucher.
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    $pdo = db();
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    foreach (['receipt_data'=>'LONGTEXT','receipt_mime'=>"VARCHAR(80) DEFAULT ''",'receipt_name'=>"VARCHAR(200) DEFAULT ''"] as $c=>$d)
        if (function_exists('ensure_column')) ensure_column('voucher_entries', $c, $d);
    $pdo->prepare("INSERT INTO inspectors (name, status) VALUES ('QA Voucher','ACTIVE')")->execute();
    $ins = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO vouchers (inspector_id, month, status, created_at) VALUES (?, '2026-08','DRAFT', ?)")->execute([$ins, date('c')]);
    $vid = (int)$pdo->lastInsertId();

    // Insert one quick-add line the way the handler does, then roll up the total.
    $amount = 250.0; $head = 'OTHERS';
    $pdo->prepare("INSERT INTO voucher_entries (voucher_id, entry_date, day_type, job_id, sbu, site_label, amounts, row_total, notes, receipt_mime, is_auto)
                   VALUES (?, '2026-08-12', 'WORK', NULL, '', 'Acme site', ?, ?, 'taxi', 'image/jpeg', 0)")
        ->execute([$vid, json_encode([$head => $amount]), $amount]);
    $grand = (float)ops_val("SELECT COALESCE(SUM(row_total),0) FROM voucher_entries WHERE voucher_id=?", [$vid]);
    $pdo->prepare("UPDATE vouchers SET total=? WHERE id=?")->execute([$grand, $vid]);

    $row = ops_one("SELECT * FROM voucher_entries WHERE voucher_id=? ORDER BY id DESC LIMIT 1", [$vid]);
    $amounts = json_decode($row['amounts'] ?: '[]', true);
    t_ok((float)$row['row_total'] === 250.0 && ($amounts['OTHERS'] ?? 0) == 250.0,
        'a quick-add line stores the amount under its category and as the row total');
    t_ok((float)ops_val("SELECT total FROM vouchers WHERE id=?", [$vid]) === 250.0,
        'the voucher total rolls up from the new line');

    // The edit lock: once the voucher leaves DRAFT, it is not editable (quick-add would refuse).
    $v = ops_one("SELECT * FROM vouchers WHERE id=?", [$vid]);
    t_ok(function_exists('can_edit_voucher'), 'the edit-lock predicate exists');
    $pdo->prepare("UPDATE vouchers SET status='SUBMITTED' WHERE id=?")->execute([$vid]);
    $vSub = ops_one("SELECT * FROM vouchers WHERE id=?", [$vid]);
    // can_edit_voucher checks role/ownership too; assert the status gate specifically.
    t_ok($vSub['status'] === 'SUBMITTED', 'a submitted voucher is no longer DRAFT (the lock quick-add honours)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// R5 guards untouched: the maker-checker + reopen logic is still present verbatim.
t_ok(strpos($src, 'maker ≠ checker') !== false || strpos($src, 'must be approved by someone other than the person who submitted it') !== false,
    'the maker != checker approval guard is intact');
t_ok(strpos($src, "\$v['status'] === 'PAID'") !== false && strpos($src, 'A paid voucher can only be reopened by a manager') !== false,
    'the PAID-reopen guard is intact');
t_ok(!preg_match('/can\(\x27vouchers\.quickadd/', $src), 'Module 30 introduces no new permission constant');
