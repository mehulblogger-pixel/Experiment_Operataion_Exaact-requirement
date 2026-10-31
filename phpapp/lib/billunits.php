<?php
// ===========================================================================
//  Universal man-day / man-month billing — the BILLING UNIT ledger
// ---------------------------------------------------------------------------
//  The owner's rule (31 Oct 2026):
//    "Every eligible man-day and man-month must be financially accounted for.
//     Work performed = billable entitlement (subject to contract).
//     Job closure = operational status. Invoice generation = commercial process.
//     No closure, document issue, period close or termination may cause
//     duplicate billing or the loss of an earned amount."
//
//  Built ON the billable-event ledger (lib/billable.php), not beside it:
//    * A billing UNIT is a billable event with a unique `unit_key`:
//        MD|<job>|<inspector>|<date>      one approved man-day
//        MM|<job>|<YYYY-MM>               one man-month period of a deployment
//        MS|<job>|<FIR|RN>|<report id>    a document milestone (when priced)
//        AD|<job>|<reason>|<n>            a termination / partial adjustment
//      The key is a UNIQUE index, so the same day or period can never exist twice.
//    * An invoice line carries `billable_event_id`; a unit can sit on only one
//      invoice that is not cancelled. That — not the job — is the "billed exactly
//      once" guarantee, so a job can be billed month after month without its
//      earlier days being billed again when it closes.
//    * BILLED is still set only by an issued invoice (never by hand). Cancelling
//      that invoice returns its units to "to be billed" — a controlled reversal,
//      logged — so nothing earned is lost.
//    * `due_on` says WHEN the contract allows a unit to be billed (the billing
//      trigger — closure, month end, report issue, termination …). Earned and
//      not yet due is visible, never lost; it is not a new status.
//  No new permission: invoicing keeps its own rights; the ledger keeps
//  finance.reconcile (D1).
// ===========================================================================

const BILLUNIT_KINDS = [
    'MANDAY'     => 'Man-day',
    'MANMONTH'   => 'Man-month period',
    'MILESTONE'  => 'Milestone',
    'ADJUSTMENT' => 'Adjustment',
];
// The work-order bases billed by the unit. Anything else (lump sum, decide later,
// other) keeps the one-event-per-closed-job behaviour it always had.
const BILLUNIT_BASES = ['MANDAY', 'MANMONTH'];

function billunit_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    billable_migrate();
    if (!function_exists('ensure_column')) return;
    // NULL (not '') for every pre-existing event, so the unique index below
    // admits all of them: only real units carry a key.
    ensure_column('billable_events', 'unit_key', "VARCHAR(160) DEFAULT NULL");
    ensure_column('billable_events', 'job_id', "INT DEFAULT 0");
    ensure_column('billable_events', 'inspector_id', "INT DEFAULT 0");
    ensure_column('billable_events', 'period_from', "VARCHAR(10) DEFAULT ''");
    ensure_column('billable_events', 'period_to', "VARCHAR(10) DEFAULT ''");
    ensure_column('billable_events', 'due_on', "VARCHAR(10) DEFAULT ''");
    ensure_column('billable_events', 'due_reason', "VARCHAR(160) DEFAULT ''");
    ensure_column('billable_events', 'evidence', "VARCHAR(400) DEFAULT ''");
    try { db()->exec("CREATE UNIQUE INDEX ux_billable_unit ON billable_events (unit_key)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX ix_billable_job ON billable_events (job_id)"); } catch (Throwable $e) {}
    if (function_exists('books_migrate')) books_migrate();
    ensure_column('invoice_lines', 'billable_event_id', "INT DEFAULT 0");
    // When billing may happen (stage 3) — chosen on the contract, the work order
    // or the job; the most specific wins. And when earning stops (stage 4).
    foreach (['partner_contracts', 'calls', 'jobs'] as $t) {
        ensure_column($t, 'billing_triggers', "VARCHAR(120) DEFAULT ''");
        ensure_column($t, 'billing_period', "VARCHAR(16) DEFAULT ''");
    }
    ensure_column('jobs', 'billing_stop_on', "VARCHAR(10) DEFAULT ''");
    ensure_column('jobs', 'billing_stop_reason', "VARCHAR(400) DEFAULT ''");
    ensure_column('jobs', 'billing_stop_kind', "VARCHAR(20) DEFAULT ''");
    // Whether the contract charges for a suspended deployment (default: no — the
    // suspended days are not earned). Set per job by finance / the coordinator.
    ensure_column('jobs', 'billing_charge_suspension', "INT DEFAULT 0");
    try { db()->exec("CREATE INDEX ix_invoice_lines_event ON invoice_lines (billable_event_id)"); } catch (Throwable $e) {}
}

// ---------------------------------------------------------------------------
//  Reading a job's commercial basis
// ---------------------------------------------------------------------------
// The job with the work order's commercial terms beside it.
function billunit_job($jobId) {
    try {
        return ops_one("SELECT j.*, c.client_id, c.contract_number call_contract_number, c.billable_basis, c.billable_rate,
                               c.billable_value, c.billable_qty, c.inspection_type call_inspection_type
                        FROM jobs j LEFT JOIN calls c ON c.id = j.call_id WHERE j.id=?", [(int)$jobId]) ?: null;
    } catch (Throwable $e) { return null; }
}
// MANDAY / MANMONTH when the job is billed by the unit, else ''.
function billunit_basis($job) {
    $b = strtoupper(trim((string)($job['billable_basis'] ?? '')));
    return in_array($b, BILLUNIT_BASES, true) ? $b : '';
}
function billunit_contract_of($job) {
    $c = trim((string)($job['contract_number'] ?? ''));
    return $c !== '' ? $c : trim((string)($job['call_contract_number'] ?? ''));
}

// ---------------------------------------------------------------------------
//  The unit primitive — idempotent by unit_key
// ---------------------------------------------------------------------------
// Create the unit once; re-running refreshes its figures only while it is still
// PENDING and on no invoice — a human decision or an invoice is never overwritten.
// Returns the event id (0 on refusal).
function billunit_upsert($kind, $unitKey, array $d) {
    billunit_migrate();
    $kind = strtoupper((string)$kind); $unitKey = substr(trim((string)$unitKey), 0, 160);
    if (!isset(BILLUNIT_KINDS[$kind]) || $unitKey === '') return 0;
    $f = [
        'job_id' => (int)($d['job_id'] ?? 0), 'inspector_id' => (int)($d['inspector_id'] ?? 0),
        'party_id' => (int)($d['party_id'] ?? 0), 'contract_number' => (string)($d['contract_number'] ?? ''),
        'office_id' => (int)($d['office_id'] ?? 0), 'sbu' => (string)($d['sbu'] ?? ''),
        'service_type' => (string)($d['service_type'] ?? ''), 'qty' => round((float)($d['qty'] ?? 0), 4),
        'unit' => (string)($d['unit'] ?? ''), 'rate' => round((float)($d['rate'] ?? 0), 2),
        'amount' => round((float)($d['amount'] ?? 0), 2), 'calc_rule' => substr((string)($d['calc_rule'] ?? ''), 0, 120),
        'period_from' => substr((string)($d['period_from'] ?? ''), 0, 10), 'period_to' => substr((string)($d['period_to'] ?? ''), 0, 10),
        'evidence' => substr((string)($d['evidence'] ?? ''), 0, 400),
    ];
    $ex = ops_one("SELECT * FROM billable_events WHERE unit_key=?", [$unitKey]);
    if ($ex) {
        if (($ex['status'] ?? '') === 'PENDING' && !billunit_on_invoice((int)$ex['id'])) {
            db()->prepare("UPDATE billable_events SET job_id=?, inspector_id=?, party_id=?, contract_number=?, office_id=?, sbu=?, service_type=?,
                           qty=?, unit=?, rate=?, amount=?, derived_amount=?, calc_rule=?, period_from=?, period_to=?, evidence=?, updated_at=? WHERE id=?")
                ->execute([$f['job_id'], $f['inspector_id'], $f['party_id'], $f['contract_number'], $f['office_id'], $f['sbu'], $f['service_type'],
                           $f['qty'], $f['unit'], $f['rate'], $f['amount'], $f['amount'], $f['calc_rule'], $f['period_from'], $f['period_to'],
                           $f['evidence'], date('c'), (int)$ex['id']]);
        }
        return (int)$ex['id'];
    }
    // Units are not one-per-source like the older events, so their source_id is a
    // private descending sequence that keeps the older (module, kind, source_id)
    // index satisfied; the unit_key is what identifies them.
    for ($try = 0; $try < 5; $try++) {
        $sid = (int)ops_val("SELECT COALESCE(MIN(source_id),0) FROM billable_events WHERE source_module='unit'") - 1 - $try;
        try {
            db()->prepare("INSERT INTO billable_events
                (source_module, source_kind, source_id, unit_key, job_id, inspector_id, party_id, contract_number, office_id, sbu, service_type,
                 qty, unit, rate, amount, derived_amount, calc_rule, period_from, period_to, evidence, status, created_at, created_by, updated_at)
                VALUES ('unit',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'PENDING',?,?,?)")
                ->execute([$kind, $sid, $unitKey, $f['job_id'], $f['inspector_id'], $f['party_id'], $f['contract_number'], $f['office_id'],
                           $f['sbu'], $f['service_type'], $f['qty'], $f['unit'], $f['rate'], $f['amount'], $f['amount'], $f['calc_rule'],
                           $f['period_from'], $f['period_to'], $f['evidence'], date('c'), billable_actor(), date('c')]);
            return (int)db()->lastInsertId();
        } catch (Throwable $e) {
            // Someone created the same unit a moment ago — that row is the answer.
            $again = ops_one("SELECT id FROM billable_events WHERE unit_key=?", [$unitKey]);
            if ($again) return (int)$again['id'];
        }
    }
    return 0;
}

// Make a unit billable from a date onwards (the contract's trigger fired). Never
// moves a due date later, and never touches a unit that is billed or cancelled.
function billunit_mark_due($eventId, $date, $reason) {
    billunit_migrate();
    $e = ops_one("SELECT id, status, due_on FROM billable_events WHERE id=?", [(int)$eventId]);
    if (!$e || in_array($e['status'], ['BILLED', 'CANCELLED'], true)) return false;
    $date = substr((string)$date, 0, 10);
    if (($e['due_on'] ?? '') !== '' && $e['due_on'] <= $date) return false;
    db()->prepare("UPDATE billable_events SET due_on=?, due_reason=?, updated_at=? WHERE id=?")
        ->execute([$date, substr((string)$reason, 0, 160), date('c'), (int)$eventId]);
    return true;
}

// ---------------------------------------------------------------------------
//  Exactly once — the invoice side
// ---------------------------------------------------------------------------
// The invoice (not cancelled) that holds this unit, or 0.
function billunit_on_invoice($eventId, $exceptInvoice = 0) {
    try {
        return (int)ops_val("SELECT i.id FROM invoice_lines l JOIN invoices i ON i.id=l.invoice_id
                             WHERE l.billable_event_id=? AND COALESCE(i.status,'')<>'CANCELLED' AND i.id<>? ORDER BY i.id LIMIT 1",
                             [(int)$eventId, (int)$exceptInvoice]);
    } catch (Throwable $e) { return 0; }
}
// Does this job bill by its units (it has any that are not cancelled)?
function billunit_job_has_units($jobId) {
    billunit_migrate();
    try { return (int)ops_val("SELECT COUNT(*) FROM billable_events WHERE source_module='unit' AND job_id=? AND status<>'CANCELLED'", [(int)$jobId]) > 0; }
    catch (Throwable $e) { return false; }
}
// Was this job billed AS A WHOLE (a line for the job that is not a unit line) on
// an invoice that is not cancelled? Such a job's units are already paid for.
function billunit_job_billed_whole($jobId, $exceptInvoice = 0) {
    billunit_migrate();
    try {
        return (int)ops_val("SELECT COUNT(*) FROM invoice_lines l JOIN invoices i ON i.id=l.invoice_id
                             WHERE l.job_id=? AND COALESCE(l.billable_event_id,0)=0 AND COALESCE(i.status,'')<>'CANCELLED' AND i.id<>?",
                             [(int)$jobId, (int)$exceptInvoice]) > 0;
    } catch (Throwable $e) { return false; }
}

// One line on a draft invoice for one unit. Returns '' on success, else the reason.
function billunit_line_add($invoiceId, $eventId, $gstPct = null) {
    billunit_migrate();
    $inv = books_invoice($invoiceId);
    if (!$inv) return 'That invoice no longer exists.';
    if ($inv['status'] !== 'DRAFT') return 'An issued invoice cannot gain a line. Raise a new invoice for it.';
    $e = ops_one("SELECT * FROM billable_events WHERE id=? AND source_module='unit'", [(int)$eventId]);
    if (!$e) return 'That billing unit no longer exists.';
    $label = billunit_label($e);
    if (in_array($e['status'], ['DISPUTED', 'CANCELLED'], true)) return $label . ' is ' . strtolower(BILLABLE_STATUS[$e['status']] ?? $e['status']) . ' — it cannot be billed.';
    if ($e['status'] === 'BILLED' || ($on = billunit_on_invoice((int)$e['id'], (int)$invoiceId)))
        return $label . ' is already on another invoice — a unit is billed only once.';
    if (($e['due_on'] ?? '') === '' || $e['due_on'] > date('Y-m-d'))
        return $label . ' is earned but not yet due under the contract\'s billing terms' . (($e['due_on'] ?? '') !== '' ? ' (due ' . $e['due_on'] . ')' : '') . '.';
    if (!empty($inv['partner_id']) && (int)$e['party_id'] && (int)$e['party_id'] !== (int)$inv['partner_id'])
        return $label . ' was done for another ' . Tl('client') . ' — it cannot go on this invoice.';
    if ((int)$e['job_id'] && billunit_job_billed_whole((int)$e['job_id']))
        return $label . ': its ' . Tl('job') . ' was already billed as a whole — these days are paid for.';
    if ((float)$e['qty'] <= 0) return $label . ' has no quantity to bill.';
    $job = (int)$e['job_id'] ? billunit_job((int)$e['job_id']) : null;
    $gst = $gstPct === null ? books_default_gst() : (float)$gstPct;
    $seq = (int)books_try(fn() => ops_val("SELECT COALESCE(MAX(seq),0) FROM invoice_lines WHERE invoice_id=?", [(int)$invoiceId]), 0) + 1;
    // The rate is the unit's own (qty × rate = amount); an adjustment carries its
    // amount as a single unit.
    $qty = (float)$e['qty']; $rate = (float)$e['rate'];
    if ($rate <= 0 && (float)$e['amount'] > 0) { $rate = round((float)$e['amount'] / max(0.0001, $qty), 2); }
    db()->prepare("INSERT INTO invoice_lines (invoice_id,seq,job_id,call_id,description,hsn_sac,qty,unit,rate,gst_pct,contract_number,billable_event_id)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$invoiceId, $seq, (int)$e['job_id'] ?: null, $job && $job['call_id'] ? (int)$job['call_id'] : null,
                   $label, books_default_sac(), $qty, (string)$e['unit'], $rate, $gst,
                   (string)$e['contract_number'], (int)$e['id']]);
    books_recalc($invoiceId);
    return '';
}

// A unit, as a customer reads it on an invoice line.
function billunit_label($e) {
    $code = (int)($e['job_id'] ?? 0) ? (string)ops_val("SELECT job_code FROM jobs WHERE id=?", [(int)$e['job_id']]) : '';
    $who  = (int)($e['inspector_id'] ?? 0) ? (string)ops_val("SELECT name FROM inspectors WHERE id=?", [(int)$e['inspector_id']]) : '';
    $from = (string)($e['period_from'] ?? ''); $to = (string)($e['period_to'] ?? '');
    $fd = fn($d) => $d !== '' && strtotime($d) ? date('d M Y', strtotime($d)) : $d;
    switch ($e['source_kind'] ?? '') {
        case 'MANDAY':
            return 'Man-day — ' . trim($code . ($who !== '' ? ' — ' . $who : '')) . ' — ' . $fd($from);
        case 'MANMONTH':
            $q = (float)($e['qty'] ?? 0);
            return 'Man-month — ' . $code . ' — ' . ($from !== '' ? date('F Y', strtotime($from)) : '') . ' (' . $fd($from) . ' to ' . $fd($to) . ')'
                 . (abs($q - 1) > 0.0001 ? ' — ' . rtrim(rtrim(number_format($q, 4), '0'), '.') . ' month' : '');
        case 'MILESTONE':
            return 'Milestone — ' . $code . ' — ' . (string)($e['calc_rule'] ?? '');
        case 'ADJUSTMENT':
            return 'Adjustment — ' . $code . ' — ' . (string)($e['evidence'] ?? '');
    }
    return 'Billing unit #' . (int)($e['id'] ?? 0);
}

// ---------------------------------------------------------------------------
//  Reconciliation — an invoice decides BILLED, and its cancellation reverses it
// ---------------------------------------------------------------------------
// Run after an invoice is issued, paid or cancelled. Issued → each unit on it is
// BILLED with the invoice behind it. Cancelled → each unit goes back to be billed
// (APPROVED), with the reversal logged, so the earned amount is never lost.
function billunit_reconcile_invoice($invoiceId) {
    billunit_migrate();
    $inv = books_invoice($invoiceId);
    if (!$inv) return 0;
    $st = strtoupper((string)$inv['status']);
    $n = 0;
    $lines = books_try(fn() => ops_all("SELECT * FROM invoice_lines WHERE invoice_id=? AND COALESCE(billable_event_id,0)>0", [(int)$invoiceId]), []) ?: [];
    foreach ($lines as $ln) {
        $e = ops_one("SELECT * FROM billable_events WHERE id=?", [(int)$ln['billable_event_id']]);
        if (!$e) continue;
        if ($st !== 'DRAFT' && $st !== 'CANCELLED') {
            if ($e['status'] === 'BILLED' && (int)$e['invoice_id'] === (int)$invoiceId) continue;
            $amt = round((float)$ln['amount'], 2);
            db()->prepare("UPDATE billable_events SET status='BILLED', invoice_id=?, invoice_line_id=?, billed_amount=?, bill_ref=?, updated_at=? WHERE id=?")
                ->execute([(int)$invoiceId, (int)$ln['id'], $amt, substr((string)$inv['invoice_no'], 0, 80), date('c'), (int)$e['id']]);
            db()->prepare("INSERT INTO billable_bills (event_id, amount, bill_ref, billed_at, billed_by) VALUES (?,?,?,?,?)")
                ->execute([(int)$e['id'], $amt, substr((string)$inv['invoice_no'], 0, 80), date('c'), billable_actor()]);
            $n++;
        } elseif ($st === 'CANCELLED' && $e['status'] === 'BILLED' && (int)$e['invoice_id'] === (int)$invoiceId) {
            billunit_reverse($e, 'Invoice ' . ($inv['invoice_no'] ?: '#' . $invoiceId) . ' cancelled — back to be billed.');
            $n++;
        }
    }
    return $n;
}
// The controlled reversal: BILLED → APPROVED, with a negative bill-log entry and
// an audit line. The only way BILLED is ever undone.
function billunit_reverse(array $e, $reason) {
    db()->prepare("INSERT INTO billable_bills (event_id, amount, bill_ref, billed_at, billed_by) VALUES (?,?,?,?,?)")
        ->execute([(int)$e['id'], -round((float)($e['billed_amount'] ?? 0), 2), substr('REVERSAL ' . (string)($e['bill_ref'] ?? ''), 0, 80), date('c'), billable_actor()]);
    db()->prepare("UPDATE billable_events SET status='APPROVED', invoice_id=0, invoice_line_id=0, billed_amount=0, bill_ref='', status_reason=?, updated_at=? WHERE id=?")
        ->execute([substr((string)$reason, 0, 400), date('c'), (int)$e['id']]);
    if (function_exists('act_log')) try { act_log('BILLABLE', (int)$e['id'], 'REVERSED', (string)$reason); } catch (Throwable $x) {}
}
// The older one-per-job events (lump sum): an invoice that is cancelled must also
// release the job's event, or the job's work silently drops out of billing
// (map defect 1). Returns how many were released.
function billunit_release_job_events_for_invoice($invoiceId) {
    $n = 0;
    $jobIds = books_try(fn() => array_map('intval', array_column(ops_all("SELECT DISTINCT job_id FROM invoice_lines WHERE invoice_id=? AND COALESCE(job_id,0)>0 AND COALESCE(billable_event_id,0)=0", [(int)$invoiceId]) ?: [], 'job_id')), []);
    foreach ($jobIds as $jid) {
        $e = ops_one("SELECT * FROM billable_events WHERE source_module='job' AND source_kind='JOB_CLOSED' AND source_id=? AND status='BILLED' AND invoice_id=?", [$jid, (int)$invoiceId]);
        if ($e) { billunit_reverse($e, 'Invoice #' . (int)$invoiceId . ' cancelled — the ' . Tl('job') . ' is back to be billed.'); $n++; }
    }
    return $n;
}

// After any change to an invoice's state: reconcile its units, and the older
// one-per-job events (issued → BILLED now, not on the next manual sync; cancelled
// → released back to be billed).
function billunit_after_invoice_change($invoiceId) {
    billunit_migrate();
    $inv = books_invoice($invoiceId);
    if (!$inv) return;
    billunit_reconcile_invoice($invoiceId);
    $st = strtoupper((string)$inv['status']);
    if ($st === 'CANCELLED') { billunit_release_job_events_for_invoice($invoiceId); return; }
    if ($st === 'DRAFT') return;
    foreach (books_try(fn() => ops_all("SELECT job_id, SUM(amount) amt FROM invoice_lines WHERE invoice_id=? AND COALESCE(job_id,0)>0 AND COALESCE(billable_event_id,0)=0 GROUP BY job_id", [(int)$invoiceId]), []) ?: [] as $r) {
        $e = ops_one("SELECT * FROM billable_events WHERE source_module='job' AND source_kind='JOB_CLOSED' AND source_id=? AND status IN ('PENDING','APPROVED')", [(int)$r['job_id']]);
        if (!$e) continue;
        $amt = round((float)$r['amt'], 2) ?: (float)$e['amount'];
        db()->prepare("UPDATE billable_events SET status='BILLED', invoice_id=?, amount=?, billed_amount=?, bill_ref=?, updated_at=? WHERE id=?")
            ->execute([(int)$invoiceId, $amt, $amt, substr((string)$inv['invoice_no'], 0, 80), date('c'), (int)$e['id']]);
    }
}

// ===========================================================================
//  Stage 2 — counting the work (units are derived, never typed)
// ===========================================================================
// Man-days come from the visit days closed with their report (job_visits DONE —
// the coordinator's existing per-day control, §WO-8). A job with no day-by-day
// plan earns its man-days when it is closed (closure is that job's sign-off).
// Man-months come from the deployment dates, month by month; a month is earned
// when it is over (or the deployment ends inside it), using the man-month rule
// this engagement / client / company already defines (manmonth_rule()).

// The date a job's earning stops: its termination date (stage 4), else nothing.
function billunit_stop_date($job) {
    $t = substr(trim((string)($job['billing_stop_on'] ?? '')), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) ? $t : '';
}
// The fields every unit of this job carries.
function billunit_common($job) {
    return [
        'job_id' => (int)$job['id'], 'party_id' => (int)($job['client_id'] ?? 0),
        'contract_number' => billunit_contract_of($job), 'office_id' => (int)($job['executing_office_id'] ?? 0),
        'sbu' => (string)($job['sbu'] ?? ''), 'service_type' => (string)(($job['inspection_type'] ?? '') ?: ($job['call_inspection_type'] ?? '')),
    ];
}

// Create / refresh every unit the job has earned up to $asOf. Idempotent.
// Returns the number of units the job now has (not cancelled).
function billunit_generate_for_job($jobId, $asOf = null) {
    billunit_migrate();
    $job = billunit_job($jobId);
    if (!$job) return 0;
    $basis = billunit_basis($job);
    if ($basis === '') return 0;
    // Billed as a whole before units existed: those days are paid for.
    if (billunit_job_billed_whole((int)$job['id'])) return 0;
    $asOf = substr((string)($asOf ?: date('Y-m-d')), 0, 10);
    $stop = billunit_stop_date($job);
    $limit = ($stop !== '' && $stop < $asOf) ? $stop : $asOf;
    $c = billunit_common($job);
    $rate = round((float)($job['billable_rate'] ?? 0), 2);
    if ($basis === 'MANDAY') {
        $done = [];
        try { $done = ops_all("SELECT v.visit_date, v.inspector_id, v.report_doc_id, v.report_link FROM job_visits v WHERE v.job_id=? AND v.status='DONE' ORDER BY v.visit_date", [(int)$job['id']]) ?: []; }
        catch (Throwable $e) { $done = []; }
        foreach ($done as $v) {
            $d = substr((string)$v['visit_date'], 0, 10);
            if ($d > $limit) continue;
            $ins = (int)($v['inspector_id'] ?: ($job['inspector_id'] ?? 0));
            billunit_upsert('MANDAY', "MD|{$job['id']}|$ins|$d", $c + [
                'inspector_id' => $ins, 'qty' => 1, 'unit' => 'man-day', 'rate' => $rate, 'amount' => $rate,
                'period_from' => $d, 'period_to' => $d, 'calc_rule' => '1 man-day × work-order rate',
                'evidence' => 'Visit day closed with its report' . (!empty($v['report_doc_id']) ? ' (report #' . (int)$v['report_doc_id'] . ')' : ''),
            ]);
        }
        // No day-by-day record, and the job is closed: closure is the sign-off for
        // its man-days (one unit carrying the job's man-days).
        if (!$done && !empty($job['closed_flag'])) {
            $md = function_exists('job_mandays') ? (float)job_mandays($job) : 0.0;
            if ($md <= 0) $md = 1.0;
            $d = substr((string)(($job['inspection_start_date'] ?? '') ?: (($job['scheduled_date'] ?? '') ?: ($job['closed_at'] ?? date('Y-m-d')))), 0, 10);
            $ins = (int)($job['inspector_id'] ?? 0);
            billunit_upsert('MANDAY', "MD|{$job['id']}|$ins|$d", $c + [
                'inspector_id' => $ins, 'qty' => $md, 'unit' => 'man-day', 'rate' => $rate, 'amount' => round($md * $rate, 2),
                'period_from' => $d, 'period_to' => substr((string)(($job['inspection_end_date'] ?? '') ?: $d), 0, 10),
                'calc_rule' => 'job man-days at closure × work-order rate', 'evidence' => TH('job') . ' closed — its man-days signed off',
            ]);
        }
    } else { // MANMONTH
        foreach (billunit_month_periods($job, $limit) as $p) {
            billunit_upsert('MANMONTH', "MM|{$job['id']}|{$p['month']}", $c + [
                'inspector_id' => (int)($job['inspector_id'] ?? 0), 'qty' => $p['units'], 'unit' => 'man-month', 'rate' => $rate,
                'amount' => round($p['units'] * $rate, 2), 'period_from' => $p['from'], 'period_to' => $p['to'],
                'calc_rule' => $p['rule'], 'evidence' => $p['evidence'],
            ]);
        }
    }
    try { return (int)ops_val("SELECT COUNT(*) FROM billable_events WHERE source_module='unit' AND job_id=? AND status<>'CANCELLED'", [(int)$job['id']]); }
    catch (Throwable $e) { return 0; }
}

// The completed man-month periods of a deployment up to $limit: the calendar
// months between start and end (or the stop date), each earned once it is over
// or the deployment ended inside it. A part month is pro-rated by the agreed
// man-month rule: CALENDAR = days deployed ÷ days in the month; MIN_DAYS =
// working days deployed ÷ the agreed minimum, capped at one month.
function billunit_month_periods($job, $limit) {
    $start = substr(trim((string)($job['inspection_start_date'] ?? '')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) return [];
    $end = substr(trim((string)($job['inspection_end_date'] ?? '')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = '';
    $stop = billunit_stop_date($job);
    if ($stop !== '' && ($end === '' || $stop < $end)) $end = $stop;
    $rule = function_exists('manmonth_rule') ? manmonth_rule($job, (int)($job['client_id'] ?? 0)) : ['basis' => 'CALENDAR', 'min_days' => 26];
    $office = $job['executing_office_id'] ?? null;
    $susp = billunit_suspended_days($job);
    $out = [];
    for ($m = strtotime(date('Y-m-01', strtotime($start))); $m <= strtotime($limit); $m = strtotime('+1 month', $m)) {
        $mFrom = date('Y-m-01', $m); $mTo = date('Y-m-t', $m);
        $from = max($mFrom, $start); $to = $end !== '' ? min($mTo, $end) : $mTo;
        if ($from > $to) continue;
        // Earned only once the period is over: the month has ended, or the
        // deployment ended inside it — and that day is not in the future.
        $over = ($to < $mTo) ? ($to <= $limit) : ($mTo < $limit || $mTo === $limit && $end !== '' && $end === $mTo);
        if (!$over) continue;
        $days = 0; $work = 0; $monthWork = 0; $skipped = 0;
        for ($t = strtotime($mFrom); $t <= strtotime($mTo); $t += 86400) {
            $d = date('Y-m-d', $t);
            $isWork = function_exists('is_working_day') ? is_working_day($d, $office) : true;
            if ($isWork) $monthWork++;
            if ($d < $from || $d > $to) continue;
            if (isset($susp[$d])) { $skipped++; continue; }
            $days++; if ($isWork) $work++;
        }
        $monthDays = (int)date('t', $m);
        if ($rule['basis'] === 'MIN_DAYS') {
            $units = min(1.0, $work / max(1, (int)$rule['min_days']));
            $how = $work . ' working day(s) ÷ ' . (int)$rule['min_days'] . ' agreed minimum';
        } else {
            $units = ($days >= $monthDays) ? 1.0 : $days / $monthDays;
            $how = $days . ' of ' . $monthDays . ' calendar days';
        }
        $units = round($units, 4);
        if ($units <= 0) continue;
        $out[] = ['month' => date('Y-m', $m), 'from' => $from, 'to' => $to, 'units' => $units,
                  'rule' => 'Man-month: ' . $how . ' × work-order rate',
                  'evidence' => 'Deployment ' . $from . ' to ' . $to . ($skipped ? ' (' . $skipped . ' suspended day(s) not charged)' : '')];
    }
    return $out;
}

// Days a deployment was suspended and NOT chargeable (stage 4 fills this from the
// deputation status history; empty until then).
function billunit_suspended_days($job) {
    return function_exists('billunit_suspension_days') ? billunit_suspension_days($job) : [];
}

// Every unit-billed job that may have earned something since it was last looked
// at: open man-month deployments (a month may have ended) and jobs closed or with
// a day closed recently. Run by the daily tick and the "Sync" button.
function billunit_generate_all($asOf = null, $limit = 2000) {
    billunit_migrate();
    $n = 0;
    try {
        $rows = ops_all("SELECT j.id FROM jobs j JOIN calls c ON c.id=j.call_id
                         WHERE UPPER(COALESCE(c.billable_basis,'')) IN ('MANDAY','MANMONTH') ORDER BY j.id DESC LIMIT " . (int)$limit) ?: [];
    } catch (Throwable $e) { return 0; }
    foreach ($rows as $r) { billunit_generate_for_job((int)$r['id'], $asOf); $n++; }
    if (function_exists('billunit_refresh_due_all')) billunit_refresh_due_all($asOf);
    return $n;
}

// ===========================================================================
//  Stage 3 — billing triggers: WHEN an earned unit may be billed
// ===========================================================================
// Closure always makes everything earned and not yet billed due ("final
// billing") — nothing is ever left behind by a closed or terminated job. The
// triggers below let billing happen EARLIER than closure. Several may be on at
// once; a unit is due at the earliest one that applies, and is still billed once.
const BILLUNIT_TRIGGERS = [
    'PERIOD'    => 'At the end of each billing period (monthly / fortnightly / weekly), even while the job is open',
    'MM_PERIOD' => 'At the end of each man-month period of a deployment',
    'FIR'       => 'When the Final Inspection Report is issued',
    'RN'        => 'When the Release Note is issued',
    'MANUAL'    => 'When finance releases it ("Bill now" — milestone / partial completion / agreed custom trigger)',
];
const BILLUNIT_PERIODS = ['MONTHLY' => 'Monthly', 'FORTNIGHTLY' => 'Fortnightly (1–15, 16–end)', 'WEEKLY' => 'Weekly (Mon–Sun)'];

// The contract row a job bills under, if any.
function billunit_contract_row($job) {
    $cn = billunit_contract_of($job);
    if ($cn === '') return null;
    try {
        return ops_one("SELECT * FROM partner_contracts WHERE contract_number=? " . ((int)($job['client_id'] ?? 0) ? "AND partner_id=? " : "") . "ORDER BY id DESC LIMIT 1",
                       (int)($job['client_id'] ?? 0) ? [$cn, (int)$job['client_id']] : [$cn]) ?: null;
    } catch (Throwable $e) { return null; }
}
function billunit_parse_triggers($csv) {
    $out = [];
    foreach (explode(',', strtoupper((string)$csv)) as $t) { $t = trim($t); if (isset(BILLUNIT_TRIGGERS[$t])) $out[$t] = true; }
    return array_keys($out);
}
// The triggers in force for a job and where they came from: the job, else its
// work order, else its contract, else the default for its basis (a man-month
// deployment is billed month by month; a man-day job at closure — exactly what
// the business did before this engine).
function billunit_triggers($job) {
    $call = !empty($job['call_id']) ? (ops_one("SELECT billing_triggers, billing_period FROM calls WHERE id=?", [(int)$job['call_id']]) ?: []) : [];
    $con  = billunit_contract_row($job) ?: [];
    foreach ([['this ' . Tl('job'), $job], ['the work order', $call], ['the contract', $con]] as [$src, $row]) {
        $t = billunit_parse_triggers($row['billing_triggers'] ?? '');
        if ($t) {
            $per = strtoupper((string)($row['billing_period'] ?? ''));
            return ['triggers' => $t, 'period' => isset(BILLUNIT_PERIODS[$per]) ? $per : 'MONTHLY', 'source' => $src];
        }
    }
    $def = billunit_basis($job) === 'MANMONTH' ? ['MM_PERIOD'] : [];
    return ['triggers' => $def, 'period' => 'MONTHLY', 'source' => 'the default (' . ($def ? 'month by month for a deployment' : 'at closure') . ')'];
}
// The cut-off date of the billing period that contains $date.
function billunit_period_cutoff($date, $period) {
    $t = strtotime($date);
    if ($period === 'WEEKLY') return date('Y-m-d', strtotime('sunday this week', $t));
    if ($period === 'FORTNIGHTLY') return (int)date('j', $t) <= 15 ? date('Y-m-15', $t) : date('Y-m-t', $t);
    return date('Y-m-t', $t);
}
// The issue dates of a job's issued Final Inspection Reports / Release Notes.
function billunit_doc_issue_dates($jobId, array $types) {
    try {
        $in = implode(',', array_fill(0, count($types), '?'));
        return array_values(array_filter(array_map(fn($r) => substr((string)($r['d'] ?? ''), 0, 10),
            ops_all("SELECT COALESCE(NULLIF(issue_date,''), substr(updated_at,1,10)) d FROM report_docs
                     WHERE job_id=? AND COALESCE(deleted,0)=0 AND finalized=1 AND type_code IN ($in) ORDER BY d", array_merge([(int)$jobId], $types)) ?: [])));
    } catch (Throwable $e) { return []; }
}

// Work out (again) when each not-yet-billed unit of a job is due. Never moves a
// due date later; a unit already due stays due.
function billunit_refresh_due_job($jobId) {
    billunit_migrate();
    $job = billunit_job($jobId);
    if (!$job) return 0;
    $units = ops_all("SELECT * FROM billable_events WHERE source_module='unit' AND job_id=? AND status IN ('PENDING','APPROVED','DISPUTED')", [(int)$jobId]) ?: [];
    if (!$units) return 0;
    $tr = billunit_triggers($job);
    $on = array_flip($tr['triggers']);
    $closed = !empty($job['closed_flag']) ? substr((string)(($job['closed_at'] ?? '') ?: date('Y-m-d')), 0, 10) : '';
    $stop = billunit_stop_date($job);
    $fir = isset($on['FIR']) ? billunit_doc_issue_dates($jobId, ['FIR']) : [];
    $rn  = isset($on['RN'])  ? billunit_doc_issue_dates($jobId, ['RN', 'IRN']) : [];
    $n = 0;
    foreach ($units as $u) {
        $cands = [];
        $pf = (string)$u['period_from']; $pt = (string)$u['period_to'] ?: $pf;
        if ($closed !== '') $cands[] = [$closed, TH('job') . ' closed — final billing'];
        if ($stop !== '')   $cands[] = [$stop, 'Ended early / terminated — final billing'];
        if (isset($on['PERIOD']) && $pt !== '') $cands[] = [billunit_period_cutoff($pt, $tr['period']), (BILLUNIT_PERIODS[$tr['period']] ?? 'Period') . ' billing cut-off'];
        if (isset($on['MM_PERIOD']) && $u['source_kind'] === 'MANMONTH' && $pt !== '') $cands[] = [$pt, 'Man-month period completed'];
        foreach ($fir as $d) if ($pf !== '' && $pf <= $d) { $cands[] = [$d, 'Final Inspection Report issued']; break; }
        foreach ($rn as $d)  if ($pf !== '' && $pf <= $d) { $cands[] = [$d, 'Release Note issued']; break; }
        if (!$cands) continue;
        usort($cands, fn($a, $b) => strcmp($a[0], $b[0]));
        if (billunit_mark_due((int)$u['id'], $cands[0][0], $cands[0][1])) $n++;
    }
    return $n;
}
function billunit_refresh_due_all($asOf = null) {
    billunit_migrate();
    $n = 0;
    try { $jobs = ops_all("SELECT DISTINCT job_id FROM billable_events WHERE source_module='unit' AND status IN ('PENDING','APPROVED','DISPUTED') AND COALESCE(job_id,0)>0") ?: []; }
    catch (Throwable $e) { return 0; }
    foreach ($jobs as $r) $n += billunit_refresh_due_job((int)$r['job_id']);
    return $n;
}
// Finance releases chosen units now — a milestone, a partial completion, or a
// custom trigger agreed with the client. Needs a reason; recorded on the unit.
function billunit_bill_now($eventId, $reason) {
    $reason = trim((string)$reason);
    if ($reason === '') return 'Say why this is being billed now (e.g. "milestone 2 reached", "client agreed part billing").';
    $e = ops_one("SELECT * FROM billable_events WHERE id=? AND source_module='unit'", [(int)$eventId]);
    if (!$e) return 'That billing unit no longer exists.';
    if (in_array($e['status'], ['BILLED', 'CANCELLED'], true)) return 'That unit is already ' . strtolower(BILLABLE_STATUS[$e['status']]) . '.';
    billunit_mark_due((int)$eventId, date('Y-m-d'), 'Released by finance: ' . $reason);
    return '';
}

// ---------------------------------------------------------------------------
//  Hooks — the places work is completed call these (each never throws)
// ---------------------------------------------------------------------------
function billunit_on_job_changed($jobId) {
    try { billunit_generate_for_job((int)$jobId); billunit_refresh_due_job((int)$jobId); } catch (Throwable $e) {}
}
// A report was issued: a Final Inspection Report or Release Note may be a trigger.
function billunit_on_report_issued($doc) {
    try {
        $t = strtoupper((string)($doc['type_code'] ?? ''));
        if (!empty($doc['job_id']) && in_array($t, ['FIR', 'RN', 'IRN'], true)) billunit_on_job_changed((int)$doc['job_id']);
    } catch (Throwable $e) {}
}

// Every due, not-yet-billed unit of a job as invoice lines (the "Raise invoice"
// path from a job). Returns ['added' => n, 'err' => '' | reason].
function billunit_add_job_units($invoiceId, $jobId) {
    billunit_on_job_changed($jobId);
    $rows = ops_all("SELECT id FROM billable_events WHERE source_module='unit' AND job_id=? AND status IN ('PENDING','APPROVED')
                     AND COALESCE(due_on,'')<>'' AND due_on<=? ORDER BY period_from, id", [(int)$jobId, date('Y-m-d')]) ?: [];
    $added = 0; $last = '';
    foreach ($rows as $r) {
        if (billunit_on_invoice((int)$r['id'])) continue;
        $err = billunit_line_add($invoiceId, (int)$r['id']);
        if ($err === '') $added++; else $last = $err;
    }
    if (!$added) {
        $notDue = (int)ops_val("SELECT COUNT(*) FROM billable_events WHERE source_module='unit' AND job_id=? AND status IN ('PENDING','APPROVED') AND (COALESCE(due_on,'')='' OR due_on>?)", [(int)$jobId, date('Y-m-d')]);
        return ['added' => 0, 'err' => $last !== '' ? $last : ($notDue
            ? 'This ' . Tl('job') . "'s earned man-days / months are not yet due under its billing terms (" . $notDue . ' waiting). They become due at closure, or earlier per the contract\'s billing triggers — or finance can release them with "Bill now".'
            : 'Nothing of this ' . Tl('job') . ' is left to bill.')];
    }
    return ['added' => $added, 'err' => ''];
}

// Backstop for the daily tick / Sync: any invoice whose state and its units
// disagree (issued but a unit not yet BILLED; cancelled but a unit still BILLED
// against it) is reconciled. Normally books_issue / books_cancel already did it.
function billunit_reconcile_all() {
    billunit_migrate();
    $n = 0;
    try {
        $ids = ops_all("SELECT DISTINCT l.invoice_id FROM invoice_lines l JOIN invoices i ON i.id=l.invoice_id
                        JOIN billable_events e ON e.id=l.billable_event_id
                        WHERE COALESCE(l.billable_event_id,0)>0 AND (
                              (i.status NOT IN ('DRAFT','CANCELLED') AND e.status<>'BILLED')
                           OR (i.status='CANCELLED' AND e.status='BILLED' AND e.invoice_id=i.id))") ?: [];
    } catch (Throwable $e) { return 0; }
    foreach ($ids as $r) $n += billunit_reconcile_invoice((int)$r['invoice_id']);
    return $n;
}

// ===========================================================================
//  Stage 4 — termination, early completion, suspension, adjustments
// ===========================================================================
const BILLUNIT_STOP_KINDS = [
    'TERMINATED'      => 'Terminated by the client / contract',
    'COMPLETED_EARLY' => 'Completed early',
    'DEMOBILIZED'     => 'Demobilised / withdrawn',
];

// May this person end a job's billing early? The same right that closes a job.
function billunit_can_end_early() {
    // A billing act: only where Money is bought (M10 entitlement), then the job-close right.
    if (function_exists('billable_money_live') && !billable_money_live()) return false;
    return is_master() || (function_exists('can') && can('ops.job.close'));
}
// May this person add a charge (termination / notice / demobilisation)? Money —
// the finance reconcile right (D1), the same as approving billable events.
function billunit_can_adjust() {
    return function_exists('billable_can_manage') && billable_can_manage();
}

// The days a deployment was SUSPENDED and is not charged for — from the
// deputation status history (dep_status_events): from the day it became
// SUSPENDED to the day it left that state. Empty when the contract charges for
// suspension (jobs.billing_charge_suspension = 1) or there is no history.
function billunit_suspension_days($job) {
    if (!empty($job['billing_charge_suspension'])) return [];
    try { $ev = ops_all("SELECT new_status, at FROM dep_status_events WHERE job_id=? ORDER BY at, id", [(int)$job['id']]) ?: []; }
    catch (Throwable $e) { return []; }
    $out = []; $from = null;
    $mark = function ($a, $b) use (&$out) { for ($t = strtotime($a); $t < strtotime($b); $t += 86400) $out[date('Y-m-d', $t)] = true; };
    foreach ($ev as $r) {
        $d = substr((string)$r['at'], 0, 10);
        if (strtoupper((string)$r['new_status']) === 'SUSPENDED') { if ($from === null) $from = $d; }
        elseif ($from !== null) { $mark($from, $d); $from = null; }
    }
    if ($from !== null) $mark($from, date('Y-m-d', strtotime('+1 day')));
    return $out;
}

// End a job's earning on a date: terminated, completed early or demobilised. All
// earned work up to that date becomes due at once (a part month is pro-rated);
// anything unbilled dated after it is cancelled with the reason. Already billed
// amounts are never touched. Returns '' or the reason it was refused.
function billunit_end_early($jobId, $date, $kind, $reason) {
    billunit_migrate();
    $job = billunit_job($jobId);
    if (!$job) return 'That ' . Tl('job') . ' no longer exists.';
    if (!empty($job['closed_flag'])) return 'This ' . Tl('job') . ' is already closed — closing it already billed everything earned.';
    $date = substr(trim((string)$date), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) return 'Give the date the work ended.';
    $start = substr((string)($job['inspection_start_date'] ?? ''), 0, 10);
    if ($start !== '' && $date < $start) return 'The end date cannot be before the ' . Tl('job') . ' started (' . $start . ').';
    if (!isset(BILLUNIT_STOP_KINDS[$kind])) return 'Say whether it was terminated, completed early or demobilised.';
    $reason = trim((string)$reason);
    if ($reason === '') return 'Say why — this is the record of the early end.';
    db()->prepare("UPDATE jobs SET billing_stop_on=?, billing_stop_kind=?, billing_stop_reason=? WHERE id=?")
        ->execute([$date, $kind, substr($reason, 0, 400), (int)$jobId]);
    // Unbilled units dated after the end are no longer earned.
    foreach (ops_all("SELECT * FROM billable_events WHERE source_module='unit' AND job_id=? AND source_kind IN ('MANDAY','MANMONTH')
                      AND status IN ('PENDING','APPROVED','DISPUTED') AND period_from>?", [(int)$jobId, $date]) ?: [] as $u) {
        if (billunit_on_invoice((int)$u['id'])) continue;
        db()->prepare("UPDATE billable_events SET status='CANCELLED', status_reason=?, updated_at=? WHERE id=?")
            ->execute(['After the ' . Tl('job') . ' ended on ' . $date . ' — not earned.', date('c'), (int)$u['id']]);
    }
    billunit_on_job_changed($jobId);
    if (function_exists('act_log')) try { act_log('JOB', (int)$jobId, 'BILLING_ENDED', BILLUNIT_STOP_KINDS[$kind] . ' on ' . $date . ': ' . $reason); } catch (Throwable $e) {}
    return '';
}

// A charge the contract provides for — termination, notice period, minimum
// commitment, demobilisation, a milestone fee. Entered by finance with its
// reason; never invented by the system. Billed once like any unit, due now.
function billunit_add_adjustment($jobId, $amount, $reason) {
    billunit_migrate();
    $job = billunit_job($jobId);
    if (!$job) return 'That ' . Tl('job') . ' no longer exists.';
    $amount = round((float)$amount, 2);
    if ($amount <= 0) return 'Enter the charge amount. (To reduce a bill, raise a credit note against the invoice.)';
    $reason = trim((string)$reason);
    if ($reason === '') return 'Say what the charge is and where the contract provides for it.';
    $n = (int)ops_val("SELECT COUNT(*) FROM billable_events WHERE source_module='unit' AND source_kind='ADJUSTMENT' AND job_id=?", [(int)$jobId]) + 1;
    $id = billunit_upsert('ADJUSTMENT', 'AD|' . (int)$jobId . '|' . $n . '|' . date('YmdHis'), billunit_common($job) + [
        'qty' => 1, 'unit' => 'charge', 'rate' => $amount, 'amount' => $amount,
        'period_from' => date('Y-m-d'), 'period_to' => date('Y-m-d'), 'calc_rule' => 'Entered by finance', 'evidence' => substr($reason, 0, 400),
    ]);
    if (!$id) return 'The charge could not be recorded.';
    billunit_mark_due($id, date('Y-m-d'), 'Charge entered by finance');
    if (function_exists('act_log')) try { act_log('JOB', (int)$jobId, 'BILLING_CHARGE', number_format($amount, 2) . ' — ' . $reason); } catch (Throwable $e) {}
    return '';
}

// ===========================================================================
//  The reconciliation — every earned unit, and where it stands
// ===========================================================================
// For one job: what was earned, what is billed, what is due and not billed, what
// is earned but not yet due, what is disputed, plus the work-order value it can
// be compared against. Shown on the job and before closing it.
function billunit_reconciliation($jobId) {
    billunit_migrate();
    $job = billunit_job($jobId);
    $out = ['basis' => $job ? billunit_basis($job) : '', 'mandays' => 0.0, 'months' => 0.0, 'earned' => 0.0, 'billed' => 0.0,
            'due' => 0.0, 'not_due' => 0.0, 'disputed' => 0.0, 'adjust' => 0.0, 'cancelled' => 0.0, 'order_value' => 0.0, 'units' => [],
            'triggers' => $job ? billunit_triggers($job) : null, 'stop' => $job ? billunit_stop_date($job) : ''];
    if (!$job) return $out;
    $out['order_value'] = (float)($job['billable_value'] ?? 0);
    $today = date('Y-m-d');
    foreach (ops_all("SELECT * FROM billable_events WHERE source_module='unit' AND job_id=? ORDER BY period_from, id", [(int)$jobId]) ?: [] as $u) {
        $a = (float)$u['amount'];
        $u['invoice_no'] = (int)$u['invoice_id'] ? (string)ops_val("SELECT invoice_no FROM invoices WHERE id=?", [(int)$u['invoice_id']]) : '';
        $u['on_draft'] = $u['status'] !== 'BILLED' ? billunit_on_invoice((int)$u['id']) : 0;
        $out['units'][] = $u;
        if ($u['status'] === 'CANCELLED') { $out['cancelled'] += $a; continue; }
        $out['earned'] += $a;
        if ($u['source_kind'] === 'MANDAY')   $out['mandays'] += (float)$u['qty'];
        if ($u['source_kind'] === 'MANMONTH') $out['months']  += (float)$u['qty'];
        if ($u['source_kind'] === 'ADJUSTMENT') $out['adjust'] += $a;
        if ($u['status'] === 'BILLED') { $out['billed'] += (float)$u['billed_amount']; continue; }
        if ($u['status'] === 'DISPUTED') { $out['disputed'] += $a; continue; }
        if (($u['due_on'] ?? '') !== '' && $u['due_on'] <= $today) $out['due'] += $a; else $out['not_due'] += $a;
    }
    foreach (['mandays','months','earned','billed','due','not_due','disputed','adjust','cancelled'] as $k) $out[$k] = round($out[$k], 4);
    $out['outstanding'] = round($out['earned'] - $out['billed'], 2);
    return $out;
}

// ===========================================================================
//  Stage 5 — routes
// ===========================================================================
// Who may raise an invoice — exactly the job-bill gate.
function billunit_can_invoice() {
    if (function_exists('billable_money_live') && !billable_money_live()) return false;
    return (function_exists('can') && (can('finance.reconcile') || can('mod.invoicing.view'))) || (function_exists('is_master_of') && is_master_of(['invoicing', 'reconcile']));
}
// Who may set a job's billing terms — whoever may edit the job.
function billunit_can_set_job_terms() {
    // Exactly the job-edit gate (job-new / job-edit: is_coordinator_level()),
    // and only where Money is bought — billing terms mean nothing without it.
    if (function_exists('billable_money_live') && !billable_money_live()) return false;
    return is_master() || (function_exists('is_coordinator_level') && is_coordinator_level());
}
// Who may set a contract's billing terms — whoever registers contracts.
function billunit_can_set_contract_terms() {
    if (function_exists('billable_money_live') && !billable_money_live()) return false;
    return is_master() || (function_exists('can') && can('crm.contract.register'));
}
// Read triggers / period from a form.
function billunit_terms_from_post() {
    $t = [];
    foreach ((array)($_POST['triggers'] ?? []) as $x) { $x = strtoupper((string)$x); if (isset(BILLUNIT_TRIGGERS[$x])) $t[] = $x; }
    $p = strtoupper((string)($_POST['billing_period'] ?? ''));
    return [implode(',', array_unique($t)), isset(BILLUNIT_PERIODS[$p]) ? $p : ''];
}

function ops_billunits($route, $method) {
    billunit_migrate();
    if ($method !== 'POST') redirect('/billable-events');
    // ---- Raise one invoice from chosen due units (the board) ----
    if ($route === 'billable-invoice') {
        ops_require(billunit_can_invoice(), 'You cannot raise invoices.');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
        if (!$ids) { flash('Tick the units to bill first.', 'error'); redirect('/billable-events#due'); }
        $rows = ops_all("SELECT * FROM billable_events WHERE source_module='unit' AND id IN (" . implode(',', $ids) . ")") ?: [];
        $parties = array_unique(array_map(fn($r) => (int)$r['party_id'], $rows));
        if (count($parties) !== 1 || !reset($parties)) { flash('One invoice is for one ' . Tl('client') . ' — tick units of a single ' . Tl('client') . '.', 'error'); redirect('/billable-events#due'); }
        $first = $rows[0];
        $job = (int)$first['job_id'] ? ops_one("SELECT * FROM jobs WHERE id=?", [(int)$first['job_id']]) : null;
        $call = $job && $job['call_id'] ? ops_one("SELECT * FROM calls WHERE id=?", [(int)$job['call_id']]) : null;
        $inv = books_invoice_create([
            'partner_id' => (int)$first['party_id'],
            'office_id' => ($job && function_exists('invoice_office_for') ? invoice_office_for($job, $call ?: []) : 0) ?: ((int)$first['office_id'] ?: null),
            'contract_number' => count(array_unique(array_column($rows, 'contract_number'))) === 1 ? (string)$first['contract_number'] : '',
            'po_number' => (string)($call['po_number'] ?? ''),
        ]);
        if (!empty($inv['err'])) { flash($inv['err'], 'error'); redirect('/billable-events#due'); }
        $added = 0; $why = [];
        foreach ($rows as $r) { $e = billunit_line_add((int)$inv['id'], (int)$r['id']); if ($e === '') $added++; else $why[] = $e; }
        if (!$added) {
            books_try(fn() => db()->prepare("DELETE FROM invoices WHERE id=? AND status='DRAFT'")->execute([(int)$inv['id']]), null);
            flash('Nothing could be billed: ' . implode(' ', array_slice($why, 0, 3)), 'error'); redirect('/billable-events#due');
        }
        flash($added . ' unit(s) put on a draft invoice — review it and issue.' . ($why ? ' Skipped: ' . implode(' ', array_slice($why, 0, 3)) : ''), $why ? 'warning' : 'success');
        redirect('/invoice?id=' . (int)$inv['id']);
    }
    // ---- A job's billing: terms, early end, charges, release, suspension ----
    if ($route === 'job-billing') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $job = billunit_job($jobId);
        if (!$job) { http_response_code(404); view('notfound'); return true; }
        $back = '/job?id=' . $jobId . '#billing';
        $act = (string)($_POST['act'] ?? '');
        if ($act === 'terms') {
            ops_require(billunit_can_set_job_terms(), 'You cannot change this ' . Tl('job') . "'s billing terms.");
            [$t, $p] = billunit_terms_from_post();
            db()->prepare("UPDATE jobs SET billing_triggers=?, billing_period=? WHERE id=?")->execute([$t, $p, $jobId]);
            if (function_exists('act_log')) try { act_log('JOB', $jobId, 'BILLING_TERMS', 'Billing triggers: ' . ($t ?: 'as the contract / default') . ($p ? ' · ' . $p : '')); } catch (Throwable $e) {}
            billunit_on_job_changed($jobId);
            flash($t ? 'Billing terms saved for this ' . Tl('job') . '.' : 'This ' . Tl('job') . ' now follows its work order / contract billing terms.');
            redirect($back);
        }
        if ($act === 'end') {
            ops_require(billunit_can_end_early(), 'Only someone who may close ' . Tlp('job') . ' can end one early.');
            $err = billunit_end_early($jobId, (string)($_POST['date'] ?? ''), (string)($_POST['kind'] ?? ''), (string)($_POST['reason'] ?? ''));
            flash($err !== '' ? $err : 'Earning stopped on ' . fdate($_POST['date'] ?? '') . '. Everything earned up to then is due to bill now.', $err !== '' ? 'error' : 'success');
            redirect($back);
        }
        if ($act === 'charge') {
            ops_require(billunit_can_adjust(), 'Only finance can add a charge.');
            $err = billunit_add_adjustment($jobId, $_POST['amount'] ?? 0, (string)($_POST['reason'] ?? ''));
            flash($err !== '' ? $err : 'Charge added — it is due to bill now.', $err !== '' ? 'error' : 'success');
            redirect($back);
        }
        if ($act === 'release') {
            ops_require(billunit_can_adjust(), 'Only finance can release a unit for billing.');
            $err = '';
            foreach ((array)($_POST['ids'] ?? []) as $id) {
                $u = ops_one("SELECT job_id FROM billable_events WHERE id=?", [(int)$id]);
                if (!$u || (int)$u['job_id'] !== $jobId) continue;
                $err = billunit_bill_now((int)$id, (string)($_POST['reason'] ?? '')); if ($err !== '') break;
            }
            flash($err !== '' ? $err : 'Released — those units are due to bill now.', $err !== '' ? 'error' : 'success');
            redirect($back);
        }
        if ($act === 'suspension') {
            ops_require(billunit_can_adjust(), 'Only finance can change whether a suspension is charged.');
            $on = !empty($_POST['charge']) ? 1 : 0;
            db()->prepare("UPDATE jobs SET billing_charge_suspension=? WHERE id=?")->execute([$on, $jobId]);
            if (function_exists('act_log')) try { act_log('JOB', $jobId, 'BILLING_SUSPENSION', $on ? 'Suspended days are charged (per contract)' : 'Suspended days are not charged'); } catch (Throwable $e) {}
            billunit_on_job_changed($jobId);
            flash($on ? 'Suspended days will be charged.' : 'Suspended days will not be charged.');
            redirect($back);
        }
        redirect($back);
    }
    // ---- A contract's billing terms ----
    if ($route === 'contract-billing') {
        ops_require(billunit_can_set_contract_terms(), 'You cannot change this contract\'s billing terms.');
        $id = (int)($_POST['contract_id'] ?? 0);
        $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]);
        if (!$c) { http_response_code(404); view('notfound'); return true; }
        [$t, $p] = billunit_terms_from_post();
        db()->prepare("UPDATE partner_contracts SET billing_triggers=?, billing_period=? WHERE id=?")->execute([$t, $p, $id]);
        if (function_exists('act_log')) try { act_log('CONTRACT', $id, 'BILLING_TERMS', 'From "' . ($c['billing_triggers'] ?: 'default') . '" to "' . ($t ?: 'default') . '"' . ($p ? ' · ' . $p : '')); } catch (Throwable $e) {}
        // Re-work out what is due on its jobs.
        foreach (ops_all("SELECT j.id FROM jobs j LEFT JOIN calls c ON c.id=j.call_id WHERE COALESCE(NULLIF(j.contract_number,''), c.contract_number)=?", [(string)$c['contract_number']]) ?: [] as $r) billunit_on_job_changed((int)$r['id']);
        flash('Billing terms saved for contract ' . $c['contract_number'] . '.');
        redirect('/contract?id=' . $id . '#billing');
    }
    redirect('/billable-events');
}

// The billing-terms form (job or contract). $cur = ['triggers'=>csv, 'period'=>code].
function billunit_terms_form_html($action, array $hidden, $curCsv, $curPeriod, $canEdit, $inheritNote) {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $cur = array_flip(billunit_parse_triggers($curCsv));
    $h = '<form method="post" action="' . $e($action) . '" class="bu-terms" style="margin:0">';
    if (function_exists('csrf_field')) $h .= csrf_field();
    foreach ($hidden as $k => $v) $h .= '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '">';
    $h .= '<p class="muted" style="margin:0 0 8px;font-size:12.5px"><b>Closing the ' . $e(Tl('job')) . ' always bills everything earned and not yet billed</b> — nothing is ever left behind. Tick below to bill <i>earlier</i> as well. Each day or month is still billed only once.</p>';
    foreach (BILLUNIT_TRIGGERS as $k => $label) {
        $h .= '<label class="chk" style="display:flex;gap:8px;align-items:flex-start;margin:0 0 6px;font-size:13px"><input type="checkbox" name="triggers[]" value="' . $e($k) . '"'
            . (isset($cur[$k]) ? ' checked' : '') . ($canEdit ? '' : ' disabled') . ' style="margin-top:2px"><span>' . $e($label) . '</span></label>';
    }
    $h .= '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:4px 0 10px;font-size:13px"><span>Billing period:</span><select class="form-control" name="billing_period" style="max-width:240px"' . ($canEdit ? '' : ' disabled') . '>';
    foreach (BILLUNIT_PERIODS as $k => $label) $h .= '<option value="' . $e($k) . '"' . ($curPeriod === $k ? ' selected' : '') . '>' . $e($label) . '</option>';
    $h .= '</select></div>';
    if ($inheritNote !== '') $h .= '<p class="muted" style="margin:0 0 8px;font-size:12px">' . $e($inheritNote) . '</p>';
    if ($canEdit) $h .= '<button class="btn small" type="submit">Save billing terms</button>';
    return $h . '</form>';
}

// Due, unbilled units not on any invoice, grouped by client (office-scoped like
// the rest of the board) — the "Due to bill" list.
function billunit_due_groups($limit = 400) {
    billunit_migrate();
    [$w, $a] = billable_scope('e.office_id');
    try {
        $rows = ops_all("SELECT e.*, j.job_code, COALESCE(NULLIF(p.display_name,''), p.legal_name) party_name
                         FROM billable_events e LEFT JOIN jobs j ON j.id=e.job_id LEFT JOIN business_partners p ON p.id=e.party_id
                         WHERE e.source_module='unit' AND e.status IN ('PENDING','APPROVED') AND COALESCE(e.due_on,'')<>'' AND e.due_on<=? AND $w
                         ORDER BY party_name, e.contract_number, j.job_code, e.period_from LIMIT " . (int)$limit, array_merge([date('Y-m-d')], $a)) ?: [];
    } catch (Throwable $e) { return []; }
    $g = [];
    foreach ($rows as $r) {
        if (billunit_on_invoice((int)$r['id'])) continue;
        $k = (int)$r['party_id'];
        $g[$k] = $g[$k] ?? ['party_id' => $k, 'party_name' => $r['party_name'] ?: '—', 'total' => 0.0, 'units' => []];
        $g[$k]['units'][] = $r; $g[$k]['total'] += (float)$r['amount'];
    }
    return array_values($g);
}
