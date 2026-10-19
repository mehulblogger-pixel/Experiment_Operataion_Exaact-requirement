<?php
// P3e — allocate a job on the work order, then close it, bill it, invoice it.
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true); $P = json_decode(file_get_contents(__DIR__ . '/p2_ids.json'), true);
$T = json_decode(file_get_contents(__DIR__ . '/p3_ids.json'), true);
$qa = (int)$P['qa']; $cid = (int)$P['cid']; $cno = $P['cno']; $co = $I['coordinator']; $call = (int)val("SELECT id FROM calls WHERE quotation_id=? ORDER BY id LIMIT 1", [$qa]);
db()->prepare("INSERT INTO inspectors (name, email, mobile, status, home_office_id, staff_kind, created_at) VALUES ('Probe Engineer','eng@probe.invalid','9811111111','ACTIVE',?, 'ASSET', ?)")->execute([$I['office'], date('c')]);
$insp = (int)db()->lastInsertId();
echo "[3e] allocate job on call #$call with inspector #$insp\n";
$o = req($co, 'job-new', 'POST', ['call' => $call], ['inspector_id' => $insp, 'executing_office_id' => $I['office'], 'scheduled_date' => '2026-11-02',
     'quotation_id' => $qa, 'impartiality_ok' => 1, 'mandays' => 3, 'cert_override_note' => 'probe override', 'sitedoc_override_note' => 'probe override', 'dblbook_override_note' => 'probe override']);
show('job-new POST', $o);
$job = (int)val("SELECT COALESCE(MAX(id),0) FROM jobs WHERE call_id=?", [$call]);
echo "  job: " . json_encode(row("SELECT id, job_code, call_id, quotation_id, contract_number, invoice_value, mandays, closed_flag, adv_required, report_hold FROM jobs WHERE id=?", [$job])) . "\n";
if (!$job) exit;
echo "  contract_state after allocation: " . json_encode(array_intersect_key(contract_state($qa), array_flip(['state','qty_total','qty_used','qty_left']))) . "\n";

echo "\n[3f] Close the job (closed_flag set directly; then the SAME hook the close handler calls)\n";
db()->prepare("UPDATE jobs SET closed_flag=1, closed_at=? WHERE id=?")->execute([date('c'), $job]);
as_user($I['finance']);
$ev = billable_on_job_closed($job);
echo "  billable event: " . json_encode(row("SELECT id, source_kind, party_id, contract_number, qty, rate, amount, derived_amount, status, calc_rule FROM billable_events WHERE id=?", [$ev])) . "\n";

echo "\n[3g] FINANCE drafts an invoice and adds the job (no rate typed) + a typed line\n";
$inv = books_invoice_create(['partner_id' => $I['p_active'], 'office_id' => $I['office'], 'contract_number' => $cno, 'quotation_id' => $qa]); $iid = (int)$inv['id'];
echo "  line_add(job): '" . books_line_add($iid, ['job_id' => $job]) . "'\n";
echo "  line_add(typed travel reimbursement): '" . books_line_add($iid, ['description' => 'Travel reimbursement (typed)', 'qty' => 1, 'rate' => 4200, 'gst_pct' => 18]) . "'\n";
foreach (books_lines($iid) as $ln) echo "    line: " . json_encode(array_intersect_key($ln, array_flip(['job_id','description','qty','rate','gst_pct','line_total','contract_number']))) . "\n";
$i = books_invoice($iid); echo "  invoice: subtotal {$i['subtotal']} total {$i['total']} quotation_id {$i['quotation_id']} contract {$i['contract_number']}\n";
echo "  quote line said MANDAY @ " . val("SELECT rate FROM quote_lines WHERE id=?", [(int)val("SELECT quote_line_id FROM calls WHERE id=?", [$call])]) . "; GST on quote " . val("SELECT gst_pct FROM quotations WHERE id=?", [$qa]) . "%\n";
echo "  client-chargeable bills on job (job_bills): " . (int)val("SELECT COUNT(*) FROM job_bills WHERE job_id=?", [$job]) . " — the invoice has no path that reads them (READ)\n";
echo "  issue: '" . books_issue($iid) . "'\n";

echo "\n[3h] Billable reconciliation after issue\n";
print_r(billable_events_sync());
echo "  event now: " . json_encode(row("SELECT status, invoice_id, amount, derived_amount, billed_amount FROM billable_events WHERE id=?", [$ev])) . "\n";
echo "  billable_mismatch(): " . json_encode(array_map(fn($r) => ['event' => $r['id'], 'earned' => $r['earned'], 'billed' => $r['billed'], 'variance' => $r['variance']], billable_mismatch())) . "\n";

echo "\n[3i] Contract value ceiling: invoice far beyond the contract value (500000) and the PO\n";
$inv2 = books_invoice_create(['partner_id' => $I['p_active'], 'office_id' => $I['office'], 'contract_number' => $cno, 'quotation_id' => $qa]); $i2 = (int)$inv2['id'];
echo "  line_add 1 x 900000 under contract $cno: '" . books_line_add($i2, ['description' => 'Over-ceiling line', 'qty' => 1, 'rate' => 900000, 'gst_pct' => 18, 'contract_number' => $cno]) . "'\n";
echo "  issue: '" . books_issue($i2) . "'\n";
$sum = (float)val("SELECT COALESCE(SUM(total),0) FROM invoices WHERE contract_number=? AND status<>'CANCELLED'", [$cno]);
echo "  invoiced under contract $cno: $sum vs contract value " . val("SELECT value FROM partner_contracts WHERE id=?", [$cid]) . " vs quote total " . val("SELECT total_amount FROM quotations WHERE id=?", [$qa]) . "\n";

echo "\n[3j] Two jobs on ONE invoice: does each event get the whole invoice total?\n";
as_user($co);
$o = req($co, 'job-new', 'POST', ['call' => $call], ['inspector_id' => $insp, 'executing_office_id' => $I['office'], 'scheduled_date' => '2026-11-09',
     'quotation_id' => $qa, 'impartiality_ok' => 1, 'mandays' => 2, 'cert_override_note' => 'probe override', 'sitedoc_override_note' => 'probe override', 'dblbook_override_note' => 'probe override']);
$job2 = (int)val("SELECT COALESCE(MAX(id),0) FROM jobs WHERE call_id=?", [$call]);
$c3 = (int)$T['c3']; // the direct/typo call
$o = req($co, 'job-new', 'POST', ['call' => $c3], ['inspector_id' => $insp, 'executing_office_id' => $I['office'], 'scheduled_date' => '2026-11-12',
     'impartiality_ok' => 1, 'mandays' => 1, 'cert_override_note' => 'probe override', 'sitedoc_override_note' => 'probe override', 'dblbook_override_note' => 'probe override']);
$job3 = (int)val("SELECT COALESCE(MAX(id),0) FROM jobs WHERE call_id=?", [$c3]);
echo "  jobs: $job2 (call $call), $job3 (call $c3 typo contract)\n";
db()->prepare("UPDATE jobs SET closed_flag=1 WHERE id IN (?,?)")->execute([$job2, $job3]);
as_user($I['finance']);
$e2 = billable_on_job_closed($job2); $e3 = billable_on_job_closed($job3);
$inv3 = books_invoice_create(['partner_id' => $I['p_active'], 'office_id' => $I['office']]); $i3 = (int)$inv3['id'];
echo "  add job2: '" . books_line_add($i3, ['job_id' => $job2]) . "'  add job3: '" . books_line_add($i3, ['job_id' => $job3]) . "'\n";
echo "  issue: '" . books_issue($i3) . "'\n"; $iv3 = books_invoice($i3);
echo "  invoice #$i3 subtotal {$iv3['subtotal']} total {$iv3['total']}, contract_number on header '{$iv3['contract_number']}'\n";
billable_events_sync();
foreach (rows("SELECT id, source_id, contract_number, amount, derived_amount, status, invoice_id FROM billable_events WHERE id IN (?,?)", [$e2, $e3]) as $r) echo "    event " . json_encode($r) . "\n";
echo "  sum of event amounts for invoice #$i3 = " . val("SELECT SUM(amount) FROM billable_events WHERE invoice_id=?", [$i3]) . " vs invoice total {$iv3['total']}\n";
echo "  billable_mismatch(): " . json_encode(array_map(fn($r) => ['event' => $r['id'], 'earned' => $r['earned'], 'billed' => $r['billed']], billable_mismatch())) . "\n";
file_put_contents(__DIR__ . '/p3e_ids.json', json_encode(['job' => $job, 'job2' => $job2, 'job3' => $job3, 'inv' => $iid, 'inv2' => $i2, 'inv3' => $i3, 'insp' => $insp, 'call' => $call]));
