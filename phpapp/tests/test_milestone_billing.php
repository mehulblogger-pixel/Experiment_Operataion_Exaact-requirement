<?php
// Owner, 12 Nov 2026 — "Milestone billing — yes, go ahead as proposed":
// the schedule is set on the quotation and carried to the contract; each
// milestone is Planned → Reached (confirmed, with what proves it) → billed by
// Finance through the existing billing-unit ledger; the coordinator or a
// manager (ops.job.close) marks it reached; retention is held from each bill
// and payable at a chosen milestone — never aged as overdue before that.
t_section('Milestones — the schedule on a quotation');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    t_as_admin();
    ms_migrate(); billunit_migrate(); books_migrate();
    t_eq(ms_problem([], 0), '', 'no schedule is allowed — the quotation bills as before');
    $row = fn($t, $p, $rel = 0) => ['title' => $t, 'pct' => $p, 'releases_retention' => $rel];
    t_ok(strpos(ms_problem([$row('Advance', 10), $row('Delivery', 80)], 0), 'add up to 90%') !== false, 'a schedule that adds up to 90% is refused, and says so');
    t_ok(strpos(ms_problem([$row('', 100)], 0), 'needs a name') !== false, 'every milestone needs a name');
    t_ok(strpos(ms_problem([$row('Advance', 0), $row('Delivery', 100)], 0), 'above 0%') !== false, 'a 0% milestone is refused');
    t_ok(strpos(ms_problem([$row('Advance', 50), $row('Delivery', 50)], 10), 'releases it') !== false, 'retention needs the milestone that releases it');
    t_ok(strpos(ms_problem([$row('Advance', 50), $row('Delivery', 50, 1)], 0), 'no retention %') !== false, 'a release with no retention % is refused');
    $sched = [$row('Advance — with the order', 10), $row('Design approved', 40), $row('Delivery', 40), $row('After the warranty period', 10, 1)];
    t_eq(ms_problem($sched, 10), '', 'the owner\'s example (10 / 40 / 40 / 10, retention 10%) is accepted');

    $pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state,gstin) VALUES ('MSB Infra Ltd','MSB Infra Ltd',1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute();
    $pid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO offices (name,is_active) VALUES ('MSB Vadodara',1)")->execute(); $off = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, status, subtotal, gst_pct, gst_amount, total_amount, office_id, created_at)
                   VALUES (?, 0, 1, ?, 'MSB Infra Ltd', 'DRAFT', 1000000, 18, 180000, 1180000, ?, ?)")->execute(['MSB-Q-' . getmypid(), $pid, $off, date('c')]);
    $qid = (int)$pdo->lastInsertId();
    $before = crm_quote_commercial_print($qid);
    ms_save_quote($qid, $sched, 10);
    t_ok(crm_quote_commercial_print($qid) !== $before, 'the schedule is part of what is approved — changing it changes the quotation\'s commercial fingerprint');
    $rows = ms_with_amounts(ms_rows_quote($qid), ms_quote_base(crm_quote_get($qid)));
    t_eq(array_map(fn($r) => (float)$r['amount'], $rows), [100000.0, 400000.0, 400000.0, 100000.0], 'amounts are shares of the price before GST (10,00,000)');
    $odd = ms_with_amounts([['pct' => 33.3333], ['pct' => 33.3333], ['pct' => 33.3334]], 1000);
    t_eq(round(array_sum(array_column($odd, 'amount')), 2), 1000.0, 'thirds still add up to the price exactly (the last share takes the rounding)');
    $pdf = implode("\n", ms_pdf_lines(crm_quote_get($qid)));
    t_ok(strpos($pdf, 'Design approved — 40% — 400,000.00 plus GST') !== false && strpos($pdf, 'Retention: 10%') !== false, 'the printed quotation lists the milestones and the retention');

    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, status, subtotal, created_at) VALUES (?, 1, 1, ?, 'DRAFT', 1000000, ?)")->execute(['MSB-Q-' . getmypid(), $pid, date('c')]);
    $rev = (int)$pdo->lastInsertId();
    ms_copy_quote($qid, $rev);
    t_eq(count(ms_rows_quote($rev)), 4, 'a revision carries the schedule, like its lines');

    t_section('Milestones — the contract takes the won quotation\'s schedule');
    $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, branch_id, quotation_id, open_status, is_active) VALUES (?, ?, 'Plant EPC', 1180000, ?, ?, 'PENDING', 0)")
        ->execute([$pid, 'MSB-C-' . getmypid(), $off, $qid]);
    $cid = (int)$pdo->lastInsertId();
    $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$cid]);
    t_ok(ms_contract_ensure($c), 'the contract takes the schedule');
    t_ok(!ms_contract_ensure($c), 'only once');
    $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$cid]);
    t_eq([ms_contract_base($c), (float)$c['ms_retention_pct']], [1000000.0, 10.0], 'with the price before GST (not the contract value with GST) and the retention');
    $ms = ms_rows_contract($cid);
    t_eq(count($ms), 4, 'all four milestones are on the contract');
    t_ok(strpos(ms_mark_reached((int)$ms[0]['id'], date('Y-m-d'), 'Order received with the PO'), 'not open yet') !== false, 'nothing can be marked reached before the contract is open');

    $pdo->prepare("UPDATE partner_contracts SET open_status='OPEN', is_active=1 WHERE id=?")->execute([$cid]);
    t_ok(strpos(ms_mark_reached((int)$ms[0]['id'], date('Y-m-d', strtotime('+2 day')), 'Order received with the PO'), 'future') !== false, 'a milestone cannot be reached in the future');
    t_ok(strpos(ms_mark_reached((int)$ms[0]['id'], date('Y-m-d'), 'ok'), 'what shows it') !== false, 'it needs a note of what proves it');
    t_eq(ms_mark_reached((int)$ms[0]['id'], date('Y-m-d'), 'Order received with the PO'), '', 'the advance milestone is marked reached');
    t_ok(strpos(ms_mark_reached((int)$ms[0]['id'], date('Y-m-d'), 'Order received with the PO'), 'already marked') !== false, 'pressing again does not reach it twice');
    $m0 = ops_one("SELECT * FROM billing_milestones WHERE id=?", [(int)$ms[0]['id']]);
    $u0 = ops_one("SELECT * FROM billable_events WHERE id=?", [(int)$m0['billable_event_id']]);
    t_eq([$m0['status'], (float)$u0['amount'], $u0['due_on'], (int)$u0['party_id']], ['ACHIEVED', 100000.0, date('Y-m-d'), $pid],
        'it becomes one billing unit of 1,00,000, due today, for this client');
    t_eq(ms_bill_state($m0)[0], 'Reached — ready to bill', 'the contract says it is ready to bill');
    t_ok(ms_ready_to_bill_count() >= 1, 'and it is on Finance\'s to-do');

    t_section('Milestones — billed once, through an invoice, with retention held');
    $inv = (int)books_invoice_create(['partner_id' => $pid, 'office_id' => $off])['id'];
    t_eq(billunit_line_add($inv, (int)$u0['id']), '', 'Finance puts the milestone on an invoice');
    t_ok(strpos(billunit_label($u0), 'Design') === false && strpos(billunit_label($u0), 'Advance — with the order (10%)') !== false, 'the invoice line names the contract and the milestone');
    t_eq(books_issue($inv), '', 'the invoice is issued');
    t_eq(ops_val("SELECT status FROM billable_events WHERE id=?", [(int)$u0['id']]), 'BILLED', 'the milestone is billed — by the invoice, never by hand');
    $i = books_invoice($inv);
    t_eq([(float)$i['subtotal'], (float)$i['total']], [100000.0, 118000.0], 'GST is charged on the full milestone value (retention is not a discount)');
    $rt = ms_invoice_retention([$inv])[$inv] ?? null;
    t_eq([(float)($rt['amount'] ?? 0), $rt['due_on'] ?? 'x'], [10000.0, ''], '10% of the milestone value is held as retention, not yet payable');
    t_ok(strpos(ms_undo((int)$ms[0]['id'], 'Marked by mistake'), 'already on an invoice') !== false, 'a billed milestone cannot be undone');

    $ar = array_values(array_filter(ar_rows_from_books('DUE'), fn($r) => (int)$r['id'] === $inv));
    t_eq((float)($ar[0]['amount'] ?? 0), 108000.0, 'Receivables ages only what is payable now (1,18,000 less 10,000 retention)');
    t_ok(strpos(file_get_contents(__DIR__ . '/../views/ops/invoice_print.php'), 'Payable now') !== false, 'the printed invoice shows the retention and what is payable now');

    t_section('Milestones — releasing retention, undoing a mistake, who may mark');
    $pdo->prepare("UPDATE billing_milestones SET status='ACHIEVED', achieved_on=? WHERE id=?")->execute([date('Y-m-d'), (int)$ms[3]['id']]);   // the warranty milestone, reached
    $rt = ms_invoice_retention([$inv])[$inv];
    t_eq($rt['due_on'], date('Y-m-d'), 'when the release milestone is reached, the retention becomes payable');
    $ar = array_values(array_filter(ar_rows_from_books('DUE'), fn($r) => (int)$r['id'] === $inv));
    t_eq((float)($ar[0]['amount'] ?? 0), 118000.0, 'and Receivables now counts it');
    $pdo->prepare("UPDATE billing_milestones SET status='PLANNED', achieved_on='' WHERE id=?")->execute([(int)$ms[3]['id']]);

    t_eq(ms_mark_reached((int)$ms[1]['id'], date('Y-m-d'), 'Design approved — client letter'), '', 'the design milestone is reached');
    t_eq(ms_undo((int)$ms[1]['id'], 'Marked by mistake — approval awaited'), '', 'a reached milestone on no invoice can be undone, with a reason');
    $u1 = ops_one("SELECT * FROM billable_events WHERE unit_key=?", [ms_unit_key($cid, (int)$ms[1]['id'])]);
    t_eq([ops_val("SELECT status FROM billing_milestones WHERE id=?", [(int)$ms[1]['id']]), (string)$u1['due_on']], ['PLANNED', ''], 'it is planned again and no longer due to bill');
    t_ok(strpos(billunit_line_add((int)books_invoice_create(['partner_id' => $pid, 'office_id' => $off])['id'], (int)$u1['id']), 'not yet due') !== false, 'so it cannot go on an invoice');

    $pdo->prepare("INSERT INTO users (username, role, is_superuser, is_active) VALUES (?, 'FINANCE', 0, 1)")->execute(['msb_fin_' . getmypid()]);
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);
    t_ok(!ms_can_mark($c), 'Finance cannot mark a milestone reached (it bills it)');
    $pdo->prepare("INSERT INTO users (username, role, is_superuser, is_active, home_office_id) VALUES (?, 'COORDINATOR', 0, 1, ?)")->execute(['msb_co_' . getmypid(), $off]);
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);
    t_eq(ms_can_mark($c), can('ops.job.close'), 'a coordinator may — exactly as they may close a job (ops.job.close)');
    t_as_admin();

    t_eq(ms_contract_other_billing($c), 0, 'a contract billed only by milestones has no other billing');
    billunit_upsert('MANDAY', 'MD|0|0|MSB-' . getmypid(), ['party_id' => $pid, 'contract_number' => $c['contract_number'], 'qty' => 1, 'unit' => 'man-day', 'rate' => 5000, 'amount' => 5000]);
    t_eq(ms_contract_other_billing($c), 1, 'a man-day billed on the same contract is noticed (it would be charged twice)');
    t_ok(strpos((string)file_get_contents(__DIR__ . '/../views/ops/_milestones.php'), 'charged twice') !== false, 'and the contract warns about it');

    t_section('Milestones — an open contract\'s schedule changes only by amendment');
    $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$cid]);
    $old = ms_contract_snapshot($c);
    $new = ['base' => 1000000, 'retention' => 10, 'rows' => [
        ['title' => 'Design approved', 'pct' => 30, 'releases_retention' => 0, 'status' => 'PLANNED'],
        ['title' => 'Delivery', 'pct' => 50, 'releases_retention' => 0, 'status' => 'PLANNED'],
        ['title' => 'After the warranty period', 'pct' => 10, 'releases_retention' => 1, 'status' => 'PLANNED']]];
    $pdo->prepare("INSERT INTO contract_amendments (contract_id,status,old_values,new_values,reason,requested_by,requested_at) VALUES (?, 'PENDING', ?, ?, 'split', 't', ?)")
        ->execute([$cid, json_encode(['milestones' => $old]), json_encode(['milestones' => $new]), date('c')]);
    $am = contract_pending_amendment($cid);
    t_ok(strpos(contract_amendment_summary($am), 'billing milestones') !== false, 'the amendment says it changes the billing milestones');
    t_eq(contract_amendment_apply($c, $am, 'Approver', null, 'ok'), '', 'once approved it is applied');
    $after = ms_rows_contract($cid);
    t_eq(array_map(fn($r) => [$r['title'], (float)$r['pct'], $r['status']], $after), [
        ['Advance — with the order', 10.0, 'ACHIEVED'], ['Design approved', 30.0, 'PLANNED'], ['Delivery', 50.0, 'PLANNED'], ['After the warranty period', 10.0, 'PLANNED']],
        'the reached advance stays exactly as it was; the planned milestones are replaced');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
    t_as_nobody();
}
