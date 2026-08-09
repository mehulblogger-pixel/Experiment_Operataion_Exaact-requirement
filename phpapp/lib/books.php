<?php
// ============================================================================
//  The books — where the work finally lands
//
//  THE PROBLEM THIS FIXES, stated plainly because it is the biggest structural
//  gap in the application. Until now there was no invoice. There were six
//  columns on a deputation — invoice_number, invoice_date, invoice_due_date,
//  invoice_amount, payment_received, payment_amount — typed in by hand, with no
//  numbering series, no lines, no tax breakdown, and a payment recorded as a
//  yes/no flag plus one figure.
//
//  Everything a set of books has to do was therefore impossible:
//
//    * A customer paying half. The flag is on or off, so ₹40,000 against a
//      ₹1,00,000 invoice either reads as fully paid or as nothing.
//    * TDS. An Indian customer settling a ₹1,00,000 invoice remits ₹90,000 and
//      deducts ₹10,000. Recorded as a plain receipt, the invoice looks ₹10,000
//      short for ever, and the ageing report chases a customer who paid in full.
//      This is the single most common reason a receivables report is not
//      believed, and it is why `tds_amount` is on the receipt.
//    * One invoice covering several deputations — normal for a monthly account.
//    * A credit note. A wrong invoice could only be edited, which in a GST
//      regime is not a correction, it is a second untraceable version.
//    * A customer ledger, and therefore a true outstanding figure.
//
//  THE SHAPE.
//
//    invoices ─┬─ invoice_lines ──── job_id (nullable: not every line is work)
//              ├─ receipt_allocations ──── receipts
//              └─ credit_notes
//
//  A receipt is money arriving. An ALLOCATION is the decision about which
//  invoice it settles. They are separate because a customer sends one payment
//  against four invoices, and because money can arrive before anybody knows
//  what it is for — an unallocated receipt is a real and normal state, not an
//  error, and the ledger shows it as a credit.
//
//  NUMBERING, and why it works the way it does. A GST invoice series may not
//  have gaps. So the number is allocated when the invoice is ISSUED, never when
//  a draft is created — a draft that is abandoned must not burn a number. And an
//  issued invoice is never deleted: cancelling sets a status and leaves the row,
//  so the series stays whole. Reversing VALUE is a credit note; cancellation is
//  only for an invoice that never should have existed.
//
//  WHAT STAYS BEHIND. `jobs.invoice_number` and its neighbours are not dropped.
//  Profitability, the MIS, the money desk and the reconciliation screens all
//  read them. Issuing an invoice writes back to every job it covers, so those
//  screens keep working unchanged. They are a MIRROR from today on: the invoice
//  is the truth, the job columns follow it. Anything new reads the invoice.
// ============================================================================

const INV_STATUS = [
    'DRAFT'     => 'Draft',
    'ISSUED'    => 'Issued',
    'PART_PAID' => 'Part paid',
    'PAID'      => 'Paid',
    'CANCELLED' => 'Cancelled',
];

const RECEIPT_MODES = [
    'NEFT'   => 'Bank transfer (NEFT/RTGS/IMPS)',
    'CHEQUE' => 'Cheque',
    'UPI'    => 'UPI',
    'CASH'   => 'Cash',
    'ADJUST' => 'Adjustment / set-off',
];

const CN_REASONS = [
    'RATE'      => 'Rate or amount was wrong',
    'QTY'       => 'Quantity or days were wrong',
    'TAX'       => 'Tax was wrong',
    'SERVICE'   => 'Work not carried out or not accepted',
    'GOODWILL'  => 'Agreed reduction',
    'CANCEL'    => 'Invoice withdrawn in full',
];

function books_can()        { return can('finance.reconcile') || can('data.credit') || is_master(); }
function books_can_issue()  { return can('finance.reconcile') || is_master(); }
function books_can_cancel() { return can('finance.reconcile') || is_master(); }

function books_missing(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false
        || stripos($m, 'no such column') !== false || stripos($m, 'Unknown column') !== false;
}
function books_try($fn, $fallback = []) {
    try { return $fn(); } catch (Throwable $e) { if (!books_missing($e)) throw $e; return $fallback; }
}

function books_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();

    // What was agreed with this customer, held once on the customer instead of
    // retyped onto every invoice. Without these two the invoice form had to ask
    // for the terms and the credit period as empty boxes on every single bill,
    // which is how one customer ends up with 30 days on one invoice and 45 on
    // the next and an argument at the end of the quarter.
    ensure_column('business_partners', 'payment_terms', "VARCHAR(120) DEFAULT ''");
    ensure_column('business_partners', 'credit_days', 'INT DEFAULT 0');

    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id $pk,
        invoice_no VARCHAR(40) DEFAULT '', series VARCHAR(20) DEFAULT '', fy VARCHAR(12) DEFAULT '',
        kind VARCHAR(20) DEFAULT 'TAX',
        partner_id INT NULL, partner_name VARCHAR(200) DEFAULT '',
        office_id INT NULL, sbu VARCHAR(20) DEFAULT '',
        invoice_date VARCHAR(20) DEFAULT '', due_date VARCHAR(20) DEFAULT '',
        payment_terms VARCHAR(120) DEFAULT '', credit_days INT DEFAULT 0,
        place_of_supply VARCHAR(4) DEFAULT '', supplier_state VARCHAR(4) DEFAULT '',
        gstin VARCHAR(20) DEFAULT '', is_igst INT DEFAULT 0, is_rcm INT DEFAULT 0,
        po_number VARCHAR(80) DEFAULT '', contract_number VARCHAR(80) DEFAULT '',
        quotation_id INT NULL,
        subtotal DECIMAL(16,2) DEFAULT 0, cgst DECIMAL(16,2) DEFAULT 0, sgst DECIMAL(16,2) DEFAULT 0,
        igst DECIMAL(16,2) DEFAULT 0, round_off DECIMAL(16,2) DEFAULT 0, total DECIMAL(16,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'DRAFT',
        issued_at VARCHAR(30) DEFAULT '', issued_by VARCHAR(150) DEFAULT '',
        cancelled_at VARCHAR(30) DEFAULT '', cancelled_by VARCHAR(150) DEFAULT '',
        cancel_reason VARCHAR(400) DEFAULT '',
        notes TEXT, tally_ref VARCHAR(40) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
        updated_at VARCHAR(30) DEFAULT '')");

    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_lines (
        id $pk, invoice_id INT, seq INT DEFAULT 0,
        job_id INT NULL, call_id INT NULL,
        description VARCHAR(500) DEFAULT '', hsn_sac VARCHAR(20) DEFAULT '',
        qty DECIMAL(16,3) DEFAULT 1, unit VARCHAR(20) DEFAULT '', rate DECIMAL(16,2) DEFAULT 0,
        amount DECIMAL(16,2) DEFAULT 0,
        gst_pct DECIMAL(6,2) DEFAULT 0,
        cgst DECIMAL(16,2) DEFAULT 0, sgst DECIMAL(16,2) DEFAULT 0, igst DECIMAL(16,2) DEFAULT 0,
        line_total DECIMAL(16,2) DEFAULT 0)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS receipts (
        id $pk, receipt_no VARCHAR(40) DEFAULT '',
        partner_id INT NULL, partner_name VARCHAR(200) DEFAULT '',
        office_id INT NULL,
        receipt_date VARCHAR(20) DEFAULT '', mode VARCHAR(20) DEFAULT 'NEFT',
        reference VARCHAR(120) DEFAULT '', bank VARCHAR(120) DEFAULT '',
        amount DECIMAL(16,2) DEFAULT 0,
        tds_amount DECIMAL(16,2) DEFAULT 0, bank_charges DECIMAL(16,2) DEFAULT 0,
        notes VARCHAR(500) DEFAULT '', tally_ref VARCHAR(40) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");

    // The decision about what a payment settles, kept apart from the payment
    // itself. `kind` says whether this line is cash or the TDS the customer
    // withheld — both settle the invoice, only one is money we received.
    $pdo->exec("CREATE TABLE IF NOT EXISTS receipt_allocations (
        id $pk, receipt_id INT, invoice_id INT,
        kind VARCHAR(10) DEFAULT 'CASH',
        amount DECIMAL(16,2) DEFAULT 0,
        allocated_by VARCHAR(150) DEFAULT '', allocated_at VARCHAR(30) DEFAULT '')");

    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_notes (
        id $pk, cn_no VARCHAR(40) DEFAULT '', fy VARCHAR(12) DEFAULT '',
        invoice_id INT NULL, partner_id INT NULL, partner_name VARCHAR(200) DEFAULT '',
        office_id INT NULL, cn_date VARCHAR(20) DEFAULT '',
        reason VARCHAR(20) DEFAULT '', reason_note VARCHAR(500) DEFAULT '',
        subtotal DECIMAL(16,2) DEFAULT 0, cgst DECIMAL(16,2) DEFAULT 0, sgst DECIMAL(16,2) DEFAULT 0,
        igst DECIMAL(16,2) DEFAULT 0, total DECIMAL(16,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'ISSUED', tally_ref VARCHAR(40) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");

    if (function_exists('act_index')) {
        act_index('invoices', 'idx_inv_partner', '(partner_id, status)');
        act_index('invoices', 'idx_inv_date',    '(invoice_date)');
        act_index('invoices', 'idx_inv_no',      '(invoice_no)');
        act_index('invoice_lines', 'idx_invl_inv', '(invoice_id)');
        act_index('invoice_lines', 'idx_invl_job', '(job_id)');
        act_index('receipts', 'idx_rcp_partner', '(partner_id)');
        act_index('receipt_allocations', 'idx_ra_rcp', '(receipt_id)');
        act_index('receipt_allocations', 'idx_ra_inv', '(invoice_id)');
        act_index('credit_notes', 'idx_cn_inv', '(invoice_id)');
    }
}

// ---- Numbering --------------------------------------------------------------
// Per series, per financial year. Allocated at ISSUE, so an abandoned draft
// leaves no gap. Read-then-write is not safe under two accountants pressing
// Issue at the same instant, so the uniqueness is checked and retried rather
// than assumed — on shared hosting there is no sequence object to lean on.
function books_series($officeId = null) {
    $code = '';
    if ($officeId) $code = (string)books_try(fn() => ops_val("SELECT code FROM offices WHERE id=?", [(int)$officeId]), '');
    if (trim($code) === '') $code = (string)setting_get('invoice_series', 'INV');
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)) ?: 'INV';
}

function books_next_number($table, $col, $series, $fy) {
    // Configurable format: invoices, receipts and credit notes route through the
    // shared numbering engine (separator, digits, financial-year style), keeping
    // the office-specific invoice series as the prefix and resetting each FY.
    // Defaults reproduce the old SERIES/2627/0001 exactly.
    if (function_exists('numbering_next')) {
        $key = ['invoices' => 'INV', 'receipts' => 'RCP', 'credit_notes' => 'CN'][$table] ?? '';
        if ($key !== '' && numbering_has($key)) {
            $startYear = (int)substr((string)$fy, 0, 4) ?: null;
            $prefixOverride = $table === 'invoices' ? $series : null;   // keep the office series
            return numbering_next($key, $table, $col, $startYear, $prefixOverride);
        }
    }
    $fyShort = str_replace('-', '', substr((string)$fy, 2)); // 2026-27 -> 2627
    $prefix  = $series . '/' . $fyShort . '/';
    for ($try = 0; $try < 30; $try++) {
        $last = books_try(fn() => ops_val(
            "SELECT $col FROM $table WHERE $col LIKE ? ORDER BY $col DESC LIMIT 1", [$prefix . '%']), '');
        $seq = $last ? ((int)substr((string)$last, strrpos((string)$last, '/') + 1)) + 1 : 1;
        $no  = $prefix . sprintf('%04d', $seq + $try);
        if (!books_try(fn() => ops_val("SELECT id FROM $table WHERE $col=?", [$no]), null)) return $no;
    }
    // Never silently reuse a number. Refusing is recoverable; a duplicate
    // invoice number in a GST return is not.
    throw new RuntimeException('Could not allocate a free number in series ' . $series . '. Try again.');
}

// ---- Tax --------------------------------------------------------------------
// Whether this is one state or two decides CGST+SGST against IGST, and getting
// it wrong is a filing correction, not a typo. The decision is made once, when
// the invoice is created, and stored — so a customer who later moves state does
// not silently rewrite last year's tax.
// Our own state comes from the SAME setting the Tally export reads. Two places
// deciding IGST from two different settings is how an invoice and the ledger
// entry made from it end up disagreeing, and that disagreement is only found
// when the return is filed.
function books_our_state() {
    if (!function_exists('tally_settings')) return '';
    $cfg = tally_settings();
    return (string)($cfg['state_code'] ?? '');
}

function books_their_state($partnerId) {
    $p = books_try(fn() => ops_one("SELECT gstin, state FROM business_partners WHERE id=?", [(int)$partnerId]), null);
    if (!$p || !function_exists('tally_state_code')) return '';
    $s = function_exists('tally_gstin_state') ? (string)tally_gstin_state((string)$p['gstin']) : '';
    return $s !== '' ? $s : (string)tally_state_code((string)$p['state']);
}

function books_is_igst($partnerId, $officeId = null) {
    $sup  = books_our_state();
    $cust = books_their_state($partnerId);
    // Neither known: do not guess. 0 means CGST+SGST, the local case, and
    // books_issue_missing() refuses to issue until somebody sets the state — so
    // the guess never reaches a customer.
    if ($sup === '' || $cust === '') return 0;
    return $sup === $cust ? 0 : 1;
}

// One default rate, read from the Tally settings for the same reason.
function books_default_gst() {
    if (function_exists('tally_settings')) { $c = tally_settings(); return (float)($c['tally_gst_pct'] ?? 18); }
    return 18.0;
}
function books_default_sac() {
    if (function_exists('tally_settings')) { $c = tally_settings(); return (string)($c['tally_sac'] ?? ''); }
    return '';
}

// Recompute an invoice's lines and totals from its lines. Called after every
// change to a line, so the header can never drift from what it is made of.
function books_recalc($invoiceId) {
    books_migrate();
    $inv = books_invoice($invoiceId);
    if (!$inv) return;
    $igst = (int)$inv['is_igst'] === 1;
    $sub = $c = $s = $i = 0.0;
    foreach (books_lines($invoiceId) as $ln) {
        $amt = round((float)$ln['qty'] * (float)$ln['rate'], 2);
        $pct = (float)$ln['gst_pct'];
        $tax = round($amt * $pct / 100, 2);
        $lc = $ls = $li = 0.0;
        if ($igst) $li = $tax; else { $lc = round($tax / 2, 2); $ls = $tax - $lc; }
        db()->prepare("UPDATE invoice_lines SET amount=?, cgst=?, sgst=?, igst=?, line_total=? WHERE id=?")
           ->execute([$amt, $lc, $ls, $li, $amt + $lc + $ls + $li, (int)$ln['id']]);
        $sub += $amt; $c += $lc; $s += $ls; $i += $li;
    }
    $gross = $sub + $c + $s + $i;
    // Rounding to the rupee is what appears on the printed invoice, and the
    // difference has to be a real figure in the books rather than a silent shave.
    $rounded = round($gross);
    $round   = round($rounded - $gross, 2);
    db()->prepare("UPDATE invoices SET subtotal=?, cgst=?, sgst=?, igst=?, round_off=?, total=?, updated_at=? WHERE id=?")
       ->execute([$sub, $c, $s, $i, $round, $rounded, date('c'), (int)$invoiceId]);
}

// ---- Reading ----------------------------------------------------------------
function books_invoice($id) {
    books_migrate();
    return books_try(fn() => ops_one(
        "SELECT i.*, o.name office_name, COALESCE(bp.display_name, bp.legal_name) client_name, bp.gstin client_gstin
         FROM invoices i
         LEFT JOIN offices o ON o.id = i.office_id
         LEFT JOIN business_partners bp ON bp.id = i.partner_id
         WHERE i.id=?", [(int)$id]), null) ?: null;
}

function books_lines($invoiceId) {
    books_migrate();
    return books_try(fn() => ops_all(
        "SELECT l.*, j.job_code FROM invoice_lines l
         LEFT JOIN jobs j ON j.id = l.job_id
         WHERE l.invoice_id=? ORDER BY l.seq, l.id", [(int)$invoiceId]));
}

// What has actually settled this invoice: cash allocated, TDS withheld, and
// credit notes against it. Kept in one place so ageing, the ledger and the
// invoice screen can never disagree about what is outstanding.
function books_settled($invoiceId) {
    books_migrate();
    $cash = (float)books_try(fn() => ops_val(
        "SELECT COALESCE(SUM(amount),0) FROM receipt_allocations WHERE invoice_id=? AND kind='CASH'", [(int)$invoiceId]), 0);
    $tds  = (float)books_try(fn() => ops_val(
        "SELECT COALESCE(SUM(amount),0) FROM receipt_allocations WHERE invoice_id=? AND kind='TDS'", [(int)$invoiceId]), 0);
    $cn   = (float)books_try(fn() => ops_val(
        "SELECT COALESCE(SUM(total),0) FROM credit_notes WHERE invoice_id=? AND status='ISSUED'", [(int)$invoiceId]), 0);
    $inv  = books_invoice($invoiceId);
    $total = $inv ? (float)$inv['total'] : 0.0;
    $out = round($total - $cash - $tds - $cn, 2);
    return ['total' => $total, 'cash' => $cash, 'tds' => $tds, 'credit' => $cn,
            'settled' => round($cash + $tds + $cn, 2), 'outstanding' => $out];
}

// Status follows the money rather than being typed. A rupee of tolerance,
// because a customer rounding a transfer must not leave an invoice for ever
// "part paid" by 40 paise.
function books_restatus($invoiceId) {
    $inv = books_invoice($invoiceId);
    if (!$inv || in_array($inv['status'], ['DRAFT', 'CANCELLED'], true)) return;
    $s = books_settled($invoiceId);
    $status = $s['outstanding'] <= 1.0 ? 'PAID' : ($s['settled'] > 0 ? 'PART_PAID' : 'ISSUED');
    if ($status !== $inv['status'])
        db()->prepare("UPDATE invoices SET status=?, updated_at=? WHERE id=?")
           ->execute([$status, date('c'), (int)$invoiceId]);
    books_mirror_to_jobs($invoiceId);
}

// ---- Writing ----------------------------------------------------------------
// ---------------------------------------------------------------------------
//  What the invoice already knows before anybody types
//
//  The complaint that produced this: the invoice form asked for the PO number,
//  the contract number, the payment terms and the credit days as four empty
//  boxes — on an invoice raised against work that already carries all four.
//  Somebody looks them up, types them again, and gets one of them wrong; the
//  customer's accounts department then disputes the invoice over a PO number
//  that was correct in the system all along.
//
//  So: derive them, show where each came from, and let a person overrule any of
//  it. Deliberately conservative — a value is only offered when the work AGREES
//  on it. Three jobs quoting three different contract numbers is a real fact
//  about the month, and silently picking the first would be a lie.
// ---------------------------------------------------------------------------
function books_carry_for($partnerId, array $jobIds = []) {
    books_migrate();
    $out = ['contract_number'=>'', 'po_number'=>'', 'payment_terms'=>'', 'credit_days'=>'',
            'from'=>[], 'conflict'=>[]];
    $pid = (int)$partnerId;
    if (!$pid) return $out;

    // Client-level agreement comes first: it is what was negotiated, and it
    // applies whether or not any work has been done yet.
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [$pid]);
    if ($p) {
        if (trim((string)($p['payment_terms'] ?? '')) !== '') {
            $out['payment_terms'] = (string)$p['payment_terms'];
            $out['from']['payment_terms'] = 'this customer\'s agreed terms';
        }
        if ((int)($p['credit_days'] ?? 0) > 0) {
            $out['credit_days'] = (int)$p['credit_days'];
            $out['from']['credit_days'] = 'this customer\'s agreed credit period';
        }
    }

    // Then the work itself. Restricted to the jobs actually going on the
    // invoice when the caller knows them, so ticking a different set changes
    // the answer rather than quietly keeping the old one.
    $jobs = books_billable_jobs($pid, 300);
    if ($jobIds) {
        $want = array_map('intval', $jobIds);
        $jobs = array_values(array_filter($jobs, fn($j) => in_array((int)$j['id'], $want, true)));
    }
    if (!$jobs) return $out;

    $ids = implode(',', array_map(fn($j) => (int)$j['id'], $jobs));
    $rows = books_try(fn() => ops_all(
        "SELECT j.contract_number AS j_contract, c.contract_number AS c_contract, c.po_id
         FROM jobs j LEFT JOIN calls c ON c.id = j.call_id WHERE j.id IN ($ids)"), []);

    $contracts = $pos = [];
    foreach ($rows as $r) {
        $cn = trim((string)($r['j_contract'] ?: $r['c_contract']));
        if ($cn !== '') $contracts[$cn] = true;
        $po = (int)($r['po_id'] ?? 0);
        if ($po) $pos[$po] = true;
    }

    if (count($contracts) === 1) {
        $out['contract_number'] = array_key_first($contracts);
        $out['from']['contract_number'] = 'the work on this invoice';
    } elseif (count($contracts) > 1) {
        // Say so rather than choose. This usually means the tick list spans two
        // contracts, which is a decision for a person and often two invoices.
        $out['conflict']['contract_number'] = array_keys($contracts);
    }

    if (count($pos) === 1) {
        $poId = (int)array_key_first($pos);
        $num = books_try(fn() => ops_val("SELECT po_number FROM partner_purchase_orders WHERE id=?", [$poId]), '');
        if (trim((string)$num) !== '') {
            $out['po_number'] = (string)$num;
            $out['from']['po_number'] = 'the purchase order the work was raised against';
        }
    } elseif (count($pos) > 1) {
        $out['conflict']['po_number'] = ['more than one purchase order'];
    }
    return $out;
}

function books_invoice_create(array $b) {
    books_migrate();
    $pid = (int)($b['partner_id'] ?? 0);
    if (!$pid) return ['err' => 'Choose the customer this invoice is for.'];
    $off = (int)($b['office_id'] ?? 0) ?: null;
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [$pid]);
    if (!$p) return ['err' => 'That customer no longer exists.'];

    $date = trim((string)($b['invoice_date'] ?? '')) ?: date('Y-m-d');
    $days = (int)($b['credit_days'] ?? 0);
    $due  = trim((string)($b['due_date'] ?? ''));
    if ($due === '') $due = $days > 0 ? date('Y-m-d', strtotime($date . " +$days days")) : $date;

    $igst = books_is_igst($pid, $off);
    $u = current_user();
    db()->prepare("INSERT INTO invoices
        (invoice_no,series,fy,kind,partner_id,partner_name,office_id,sbu,invoice_date,due_date,
         payment_terms,credit_days,place_of_supply,supplier_state,gstin,is_igst,
         po_number,contract_number,quotation_id,status,notes,created_by,created_at,updated_at)
        VALUES ('',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'DRAFT',?,?,?,?)")
      ->execute([
        books_series($off), current_fy(), (string)($b['kind'] ?? 'TAX'), $pid,
        (string)($p['display_name'] ?: $p['legal_name']), $off, (string)($b['sbu'] ?? ''),
        $date, $due, (string)($b['payment_terms'] ?? ''), $days,
        books_their_state($pid), books_our_state(), (string)$p['gstin'], $igst,
        (string)($b['po_number'] ?? ''), (string)($b['contract_number'] ?? ''),
        (int)($b['quotation_id'] ?? 0) ?: null,
        (string)($b['notes'] ?? ''), $u ? user_name($u) : '', date('c'), date('c')]);
    $id = (int)db()->lastInsertId();
    if (function_exists('act_log')) act_log('INVOICE', $id, 'CREATED', 'Draft invoice started for ' . ($p['display_name'] ?: $p['legal_name']));
    return ['id' => $id];
}

function books_line_add($invoiceId, array $b) {
    books_migrate();
    $inv = books_invoice($invoiceId);
    if (!$inv) return 'That invoice no longer exists.';
    if ($inv['status'] !== 'DRAFT') return 'An issued invoice cannot gain a line. Raise a credit note, or cancel it if nothing has happened against it.';
    $desc = trim((string)($b['description'] ?? ''));
    $job  = (int)($b['job_id'] ?? 0) ?: null;
    if ($desc === '' && !$job) return 'Say what the line is for, or pick the ' . Tl('job') . ' it bills.';

    // Billing a deputation: take its figures rather than asking somebody to
    // re-key them, which is where the two versions of a number come from.
    $callId = null;
    if ($job) {
        $j = ops_one("SELECT j.*, c.id call_id, c.billable_value, c.billable_rate, c.billable_qty, c.billable_basis
                      FROM jobs j LEFT JOIN calls c ON c.id = j.call_id WHERE j.id=?", [$job]);
        if (!$j) return 'That ' . Tl('job') . ' no longer exists.';
        if (books_job_invoiced($job, (int)$invoiceId)) return TH('job') . ' ' . $j['job_code'] . ' is already on another invoice.';
        $callId = $j['call_id'] ? (int)$j['call_id'] : null;
        if ($desc === '') $desc = 'Inspection services — ' . $j['job_code'];
        if (($b['rate'] ?? '') === '') $b['rate'] = (float)($j['billable_rate'] ?: $j['invoice_value'] ?: $j['billable_value'] ?: 0);
        if (($b['qty'] ?? '') === '')  $b['qty']  = (float)($j['billable_qty'] ?: 1);
    }
    $seq = (int)books_try(fn() => ops_val("SELECT COALESCE(MAX(seq),0) FROM invoice_lines WHERE invoice_id=?", [(int)$invoiceId]), 0) + 1;
    $gst = ($b['gst_pct'] ?? '') === '' ? books_default_gst() : (float)$b['gst_pct'];
    db()->prepare("INSERT INTO invoice_lines (invoice_id,seq,job_id,call_id,description,hsn_sac,qty,unit,rate,gst_pct)
                   VALUES (?,?,?,?,?,?,?,?,?,?)")
       ->execute([(int)$invoiceId, $seq, $job, $callId, $desc,
                  trim((string)($b['hsn_sac'] ?? '')) ?: books_default_sac(), (float)($b['qty'] ?? 1),
                  (string)($b['unit'] ?? ''), (float)($b['rate'] ?? 0), $gst]);
    books_recalc($invoiceId);
    return '';
}

function books_line_delete($lineId) {
    books_migrate();
    $ln = books_try(fn() => ops_one("SELECT * FROM invoice_lines WHERE id=?", [(int)$lineId]), null);
    if (!$ln) return 'That line has already gone.';
    $inv = books_invoice((int)$ln['invoice_id']);
    if (!$inv || $inv['status'] !== 'DRAFT') return 'An issued invoice cannot lose a line. Raise a credit note instead.';
    db()->prepare("DELETE FROM invoice_lines WHERE id=?")->execute([(int)$lineId]);
    books_recalc((int)$ln['invoice_id']);
    return '';
}

// Is this deputation already billed? Checked before a line is added, because
// billing the same work twice is the mistake a customer notices.
function books_job_invoiced($jobId, $exceptInvoice = 0) {
    books_migrate();
    return (int)books_try(fn() => ops_val(
        "SELECT COUNT(*) FROM invoice_lines l JOIN invoices i ON i.id = l.invoice_id
         WHERE l.job_id=? AND i.status <> 'CANCELLED' AND i.id <> ?",
        [(int)$jobId, (int)$exceptInvoice]), 0) > 0;
}
// §INV-1 — the real (books) invoices raised against a job, drafts included, so a
// raised invoice shows on the job at once instead of looking like nothing happened.
function books_invoices_for_job($jobId) {
    books_migrate();
    return books_try(fn() => ops_all(
        "SELECT DISTINCT i.id, i.invoice_no, i.status, i.total, i.invoice_date
         FROM invoices i JOIN invoice_lines l ON l.invoice_id=i.id
         WHERE l.job_id=? AND COALESCE(i.status,'')<>'CANCELLED' ORDER BY i.id DESC",
        [(int)$jobId]), []);
}

// What stops an invoice being issued. A list, because telling somebody one
// problem at a time is how a five-minute job takes half an hour.
function books_issue_missing(array $inv) {
    $why = [];
    if ($inv['status'] !== 'DRAFT') $why[] = 'It is already ' . strtolower(INV_STATUS[$inv['status']] ?? $inv['status']) . '.';
    $lines = books_lines((int)$inv['id']);
    if (!$lines) $why[] = 'It has no lines, so there is nothing to charge for.';
    if ((float)$inv['total'] <= 0 && $lines) $why[] = 'It totals nothing. Check the rates.';
    if (trim((string)$inv['invoice_date']) === '') $why[] = 'It has no date.';
    if (trim((string)$inv['place_of_supply']) === '' && trim((string)$inv['client_gstin'] ?? '') === '')
        $why[] = 'The place of supply is unknown, so CGST/SGST against IGST is a guess. Set the customer\'s GSTIN or state.';
    if (!$inv['office_id']) $why[] = 'No branch is set, so it has no numbering series.';
    return $why;
}

function books_issue($invoiceId) {
    books_migrate();
    $inv = books_invoice($invoiceId);
    if (!$inv) return 'That invoice no longer exists.';
    $why = books_issue_missing($inv);
    if ($why) return implode(' ', $why);

    $no = books_next_number('invoices', 'invoice_no', (string)$inv['series'] ?: books_series((int)$inv['office_id']), (string)$inv['fy'] ?: current_fy());
    $u = current_user();
    db()->prepare("UPDATE invoices SET invoice_no=?, status='ISSUED', issued_at=?, issued_by=?, updated_at=? WHERE id=?")
       ->execute([$no, date('c'), $u ? user_name($u) : '', date('c'), (int)$invoiceId]);
    books_mirror_to_jobs($invoiceId);
    // If the customer has connected MGH Books, the issued invoice is the billable
    // event that flows across. A no-op when not connected — the ERP keeps its own.
    if (function_exists('books_bridge_on_invoice_issued')) try { books_bridge_on_invoice_issued((int)$invoiceId); } catch (Throwable $e) {}
    if (function_exists('act_log')) act_log('INVOICE', (int)$invoiceId, 'ISSUED', 'Invoice ' . $no . ' issued for ' . fmoney($inv['total']));
    return '';
}

// Cancelling keeps the row and the number, so the series has no hole. It is
// refused once money or a credit note has touched it — at that point the
// correction is a credit note, which is a record rather than an erasure.
function books_cancel($invoiceId, $reason) {
    books_migrate();
    $inv = books_invoice($invoiceId);
    if (!$inv) return 'That invoice no longer exists.';
    if ($inv['status'] === 'CANCELLED') return 'It is already cancelled.';
    if (trim((string)$reason) === '') return 'Say why it is being cancelled — this is the only record of it.';
    $s = books_settled($invoiceId);
    if ($s['settled'] > 0)
        return 'Money or a credit note has already been recorded against this invoice. Raise a credit note instead — cancelling would erase a settled entry.';
    $u = current_user();
    db()->prepare("UPDATE invoices SET status='CANCELLED', cancelled_at=?, cancelled_by=?, cancel_reason=?, updated_at=? WHERE id=?")
       ->execute([date('c'), $u ? user_name($u) : '', substr(trim((string)$reason), 0, 400), date('c'), (int)$invoiceId]);
    books_unmirror_jobs($invoiceId);
    if (function_exists('act_log')) act_log('INVOICE', (int)$invoiceId, 'CANCELLED', 'Cancelled: ' . $reason);
    return '';
}

// ---- Keeping the old job columns true --------------------------------------
// Profitability, the MIS and the money desk all read jobs.invoice_*. They keep
// working because issuing writes back. This is a mirror, not a second truth:
// nothing else ever writes these from today on.
function books_mirror_to_jobs($invoiceId) {
    $inv = books_invoice($invoiceId);
    if (!$inv || $inv['status'] === 'DRAFT') return;
    $s = books_settled($invoiceId);
    $paid = $s['outstanding'] <= 1.0;
    foreach (books_lines($invoiceId) as $ln) {
        if (empty($ln['job_id'])) continue;
        books_try(fn() => db()->prepare(
            "UPDATE jobs SET invoice_raised=1, invoice_number=?, invoice_date=?, invoice_due_date=?,
             invoice_amount=?, payment_received=?, payment_amount=?, payment_date=? WHERE id=?")
           ->execute([(string)$inv['invoice_no'], (string)$inv['invoice_date'], (string)$inv['due_date'],
                      (float)$ln['line_total'], $paid ? 1 : 0,
                      $paid ? (float)$ln['line_total'] : 0.0,
                      $paid ? (string)books_last_receipt_date($invoiceId) : '',
                      (int)$ln['job_id']]), null);
    }
}

function books_unmirror_jobs($invoiceId) {
    foreach (books_lines($invoiceId) as $ln) {
        if (empty($ln['job_id'])) continue;
        books_try(fn() => db()->prepare(
            "UPDATE jobs SET invoice_raised=0, invoice_number='', invoice_date='', invoice_due_date='',
             invoice_amount=0, payment_received=0, payment_amount=0, payment_date='' WHERE id=?")
           ->execute([(int)$ln['job_id']]), null);
    }
}

function books_last_receipt_date($invoiceId) {
    return (string)books_try(fn() => ops_val(
        "SELECT MAX(r.receipt_date) FROM receipt_allocations a JOIN receipts r ON r.id = a.receipt_id
         WHERE a.invoice_id=?", [(int)$invoiceId]), '');
}

// ---- Receipts ---------------------------------------------------------------
function books_receipt_create(array $b) {
    books_migrate();
    $pid = (int)($b['partner_id'] ?? 0);
    if (!$pid) return ['err' => 'Say who the money came from.'];
    $amt = (float)($b['amount'] ?? 0);
    $tds = (float)($b['tds_amount'] ?? 0);
    if ($amt <= 0 && $tds <= 0) return ['err' => 'A receipt of nothing is not a receipt.'];
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [$pid]);
    if (!$p) return ['err' => 'That customer no longer exists.'];
    $u = current_user();
    $no = books_next_number('receipts', 'receipt_no', 'RCP', current_fy());
    db()->prepare("INSERT INTO receipts (receipt_no,partner_id,partner_name,office_id,receipt_date,mode,reference,bank,
                   amount,tds_amount,bank_charges,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$no, $pid, (string)($p['display_name'] ?: $p['legal_name']),
                  (int)($b['office_id'] ?? 0) ?: null,
                  trim((string)($b['receipt_date'] ?? '')) ?: date('Y-m-d'),
                  (string)($b['mode'] ?? 'NEFT'), (string)($b['reference'] ?? ''), (string)($b['bank'] ?? ''),
                  $amt, $tds, (float)($b['bank_charges'] ?? 0), (string)($b['notes'] ?? ''),
                  $u ? user_name($u) : '', date('c')]);
    $id = (int)db()->lastInsertId();
    if (function_exists('act_log')) act_log('RECEIPT', $id, 'CREATED', 'Receipt ' . $no . ' — ' . fmoney($amt + $tds));
    // Flow the receipt to MGH Books when connected, so it matches the payment
    // against the invoice there. No-op when Books is off.
    if (function_exists('books_bridge_on_receipt')) { try { books_bridge_on_receipt($id); } catch (Throwable $e) {} }
    return ['id' => $id, 'no' => $no];
}

// Spread a receipt across invoices. Refuses to allocate more than arrived and
// more than the invoice owes — both are how a ledger stops balancing.
function books_allocate($receiptId, array $rows) {
    books_migrate();
    $r = books_try(fn() => ops_one("SELECT * FROM receipts WHERE id=?", [(int)$receiptId]), null);
    if (!$r) return 'That receipt no longer exists.';
    $u = current_user(); $now = date('c');

    $freeCash = round((float)$r['amount'] - books_allocated($receiptId, 'CASH'), 2);
    $freeTds  = round((float)$r['tds_amount'] - books_allocated($receiptId, 'TDS'), 2);
    $done = 0; $msg = [];

    foreach ($rows as $invId => $vals) {
        $invId = (int)$invId;
        foreach (['CASH', 'TDS'] as $kind) {
            $amt = round((float)($vals[strtolower($kind)] ?? 0), 2);
            if ($amt <= 0) continue;
            $inv = books_invoice($invId);
            if (!$inv) { $msg[] = 'One invoice had gone.'; continue; }
            if ($inv['status'] === 'CANCELLED') { $msg[] = $inv['invoice_no'] . ' is cancelled.'; continue; }
            if ($inv['status'] === 'DRAFT')     { $msg[] = 'A draft invoice cannot take a payment.'; continue; }
            if ((int)$inv['partner_id'] !== (int)$r['partner_id']) { $msg[] = $inv['invoice_no'] . ' belongs to another customer.'; continue; }

            $free = $kind === 'CASH' ? $freeCash : $freeTds;
            if ($amt > $free + 0.01) { $msg[] = 'More was allocated than arrived on the receipt.'; continue; }
            $owed = books_settled($invId)['outstanding'];
            if ($amt > $owed + 0.01) { $msg[] = $inv['invoice_no'] . ' only owes ' . fmoney($owed) . '.'; continue; }

            db()->prepare("INSERT INTO receipt_allocations (receipt_id,invoice_id,kind,amount,allocated_by,allocated_at)
                           VALUES (?,?,?,?,?,?)")
               ->execute([(int)$receiptId, $invId, $kind, $amt, $u ? user_name($u) : '', $now]);
            if ($kind === 'CASH') $freeCash = round($freeCash - $amt, 2); else $freeTds = round($freeTds - $amt, 2);
            books_restatus($invId);
            $done++;
        }
    }
    if (!$done) return $msg ? implode(' ', array_unique($msg)) : 'Nothing was allocated — every figure was blank or zero.';
    return $msg ? implode(' ', array_unique($msg)) : '';
}

function books_allocated($receiptId, $kind = null) {
    $sql = "SELECT COALESCE(SUM(amount),0) FROM receipt_allocations WHERE receipt_id=?";
    $a = [(int)$receiptId];
    if ($kind) { $sql .= " AND kind=?"; $a[] = $kind; }
    return (float)books_try(fn() => ops_val($sql, $a), 0);
}

function books_unallocate($allocId) {
    books_migrate();
    $a = books_try(fn() => ops_one("SELECT * FROM receipt_allocations WHERE id=?", [(int)$allocId]), null);
    if (!$a) return 'That allocation has already gone.';
    db()->prepare("DELETE FROM receipt_allocations WHERE id=?")->execute([(int)$allocId]);
    books_restatus((int)$a['invoice_id']);
    return '';
}

// ---- Credit notes -----------------------------------------------------------
function books_credit_note(array $b) {
    books_migrate();
    $invId = (int)($b['invoice_id'] ?? 0);
    $inv = $invId ? books_invoice($invId) : null;
    if (!$inv) return ['err' => 'A credit note has to say which invoice it reduces.'];
    if ($inv['status'] === 'DRAFT') return ['err' => 'That invoice was never issued. Change the draft, or delete it.'];
    if ($inv['status'] === 'CANCELLED') return ['err' => 'That invoice is cancelled — there is nothing to credit.'];
    if (trim((string)($b['reason'] ?? '')) === '') return ['err' => 'Choose why the invoice is being reduced.'];

    $sub = (float)($b['subtotal'] ?? 0);
    if ($sub <= 0) return ['err' => 'Say how much is being credited, before tax.'];
    $owed = books_settled($invId)['outstanding'];

    $pct = ($b['gst_pct'] ?? '') === '' ? books_default_gst() : (float)$b['gst_pct'];
    $tax = round($sub * $pct / 100, 2);
    $c = $s = $i = 0.0;
    if ((int)$inv['is_igst'] === 1) $i = $tax; else { $c = round($tax / 2, 2); $s = $tax - $c; }
    $total = round($sub + $c + $s + $i, 2);
    // A credit note may not exceed the invoice — a customer cannot end up owed
    // money by a document that only ever charged them.
    if ($total > (float)$inv['total'] + 0.01)
        return ['err' => 'That is more than the invoice was for (' . fmoney($inv['total']) . ').'];
    if ($total > $owed + books_settled($invId)['credit'] + 0.01)
        return ['err' => 'Only ' . fmoney($owed) . ' is still outstanding on that invoice.'];

    $u = current_user();
    $no = books_next_number('credit_notes', 'cn_no', 'CN', current_fy());
    db()->prepare("INSERT INTO credit_notes (cn_no,fy,invoice_id,partner_id,partner_name,office_id,cn_date,reason,reason_note,
                   subtotal,cgst,sgst,igst,total,status,created_by,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'ISSUED',?,?)")
       ->execute([$no, current_fy(), $invId, (int)$inv['partner_id'], (string)$inv['partner_name'],
                  $inv['office_id'] ? (int)$inv['office_id'] : null,
                  trim((string)($b['cn_date'] ?? '')) ?: date('Y-m-d'),
                  (string)$b['reason'], (string)($b['reason_note'] ?? ''),
                  $sub, $c, $s, $i, $total, $u ? user_name($u) : '', date('c')]);
    $id = (int)db()->lastInsertId();
    books_restatus($invId);
    if (function_exists('act_log')) act_log('INVOICE', $invId, 'CREDIT', 'Credit note ' . $no . ' for ' . fmoney($total));
    // Flow the credit note to MGH Books when connected, so it reverses the invoice
    // there too. No-op when Books is off.
    if (function_exists('books_bridge_on_creditnote')) { try { books_bridge_on_creditnote($id); } catch (Throwable $e) {} }
    return ['id' => $id, 'no' => $no];
}

// ---- The customer ledger ----------------------------------------------------
// Every entry against one customer, in date order, with a running balance. This
// is the answer to "what do they actually owe us", and until now the
// application could not produce it at all.
function books_ledger($partnerId, $from = '', $to = '') {
    books_migrate();
    $pid = (int)$partnerId;
    $rows = [];
    $range = function ($col) use ($from, $to) {
        $w = ''; $a = [];
        if ($from !== '') { $w .= " AND $col >= ?"; $a[] = $from; }
        if ($to !== '')   { $w .= " AND $col <= ?"; $a[] = $to; }
        return [$w, $a];
    };

    [$w, $a] = $range('invoice_date');
    foreach (books_try(fn() => ops_all(
        "SELECT id, invoice_no, invoice_date, total, status FROM invoices
         WHERE partner_id=? AND status <> 'DRAFT' AND status <> 'CANCELLED' $w", array_merge([$pid], $a))) as $r)
        $rows[] = ['date' => $r['invoice_date'], 'kind' => 'INVOICE', 'ref' => $r['invoice_no'],
                   'url' => '/invoice?id=' . (int)$r['id'], 'debit' => (float)$r['total'], 'credit' => 0.0,
                   'note' => 'Invoice'];

    [$w, $a] = $range('r.receipt_date');
    foreach (books_try(fn() => ops_all(
        "SELECT r.id, r.receipt_no, r.receipt_date, r.amount, r.tds_amount, r.mode FROM receipts r
         WHERE r.partner_id=? $w", array_merge([$pid], $a))) as $r) {
        if ((float)$r['amount'] > 0)
            $rows[] = ['date' => $r['receipt_date'], 'kind' => 'RECEIPT', 'ref' => $r['receipt_no'],
                       'url' => '/receipt?id=' . (int)$r['id'], 'debit' => 0.0, 'credit' => (float)$r['amount'],
                       'note' => RECEIPT_MODES[$r['mode']] ?? 'Receipt'];
        if ((float)$r['tds_amount'] > 0)
            $rows[] = ['date' => $r['receipt_date'], 'kind' => 'TDS', 'ref' => $r['receipt_no'],
                       'url' => '/receipt?id=' . (int)$r['id'], 'debit' => 0.0, 'credit' => (float)$r['tds_amount'],
                       'note' => 'Tax deducted at source'];
    }

    [$w, $a] = $range('cn_date');
    foreach (books_try(fn() => ops_all(
        "SELECT id, cn_no, cn_date, total FROM credit_notes WHERE partner_id=? AND status='ISSUED' $w",
        array_merge([$pid], $a))) as $r)
        $rows[] = ['date' => $r['cn_date'], 'kind' => 'CREDIT', 'ref' => $r['cn_no'],
                   'url' => '/credit-note?id=' . (int)$r['id'], 'debit' => 0.0, 'credit' => (float)$r['total'],
                   'note' => 'Credit note'];

    usort($rows, fn($x, $y) => [$x['date'], $x['kind']] <=> [$y['date'], $y['kind']]);
    $bal = 0.0;
    foreach ($rows as $k => $r) { $bal += $r['debit'] - $r['credit']; $rows[$k]['balance'] = round($bal, 2); }
    return ['rows' => $rows, 'closing' => round($bal, 2)];
}

// One figure, used by Customer 360 and the customer screen.
function books_outstanding($partnerId) {
    books_migrate();
    $inv = (float)books_try(fn() => ops_val(
        "SELECT COALESCE(SUM(total),0) FROM invoices WHERE partner_id=? AND status IN ('ISSUED','PART_PAID')", [(int)$partnerId]), 0);
    $set = (float)books_try(fn() => ops_val(
        "SELECT COALESCE(SUM(a.amount),0) FROM receipt_allocations a JOIN invoices i ON i.id = a.invoice_id
         WHERE i.partner_id=? AND i.status IN ('ISSUED','PART_PAID')", [(int)$partnerId]), 0);
    $cn = (float)books_try(fn() => ops_val(
        "SELECT COALESCE(SUM(c.total),0) FROM credit_notes c JOIN invoices i ON i.id = c.invoice_id
         WHERE i.partner_id=? AND c.status='ISSUED' AND i.status IN ('ISSUED','PART_PAID')", [(int)$partnerId]), 0);
    $unalloc = (float)books_try(fn() => ops_val(
        "SELECT COALESCE(SUM(r.amount + r.tds_amount),0) - COALESCE((
             SELECT SUM(a.amount) FROM receipt_allocations a JOIN receipts r2 ON r2.id = a.receipt_id
             WHERE r2.partner_id=?),0)
         FROM receipts r WHERE r.partner_id=?", [(int)$partnerId, (int)$partnerId]), 0);
    return ['billed' => $inv, 'settled' => round($set + $cn, 2),
            'outstanding' => round($inv - $set - $cn, 2), 'on_account' => round(max(0, $unalloc), 2)];
}

// ---- Registers --------------------------------------------------------------
function books_invoice_where(array $f) {
    [$w, $a] = scope_clause('i.office_id', 'i.sbu');
    $wh = [$w];
    if (!empty($f['status'])) { $wh[] = 'i.status = ?'; $a[] = $f['status']; }
    if (!empty($f['open']))   { $wh[] = "i.status IN ('ISSUED','PART_PAID')"; }
    if (!empty($f['partner'])) { $wh[] = 'i.partner_id = ?'; $a[] = (int)$f['partner']; }
    if (!empty($f['q'])) {
        $wh[] = '(i.invoice_no LIKE ? OR i.partner_name LIKE ? OR i.po_number LIKE ? OR i.contract_number LIKE ?)';
        $like = '%' . $f['q'] . '%'; array_push($a, $like, $like, $like, $like);
    }
    return [implode(' AND ', $wh), $a];
}

const BOOKS_INV_FROM = "FROM invoices i
         LEFT JOIN offices o ON o.id = i.office_id
         LEFT JOIN business_partners bp ON bp.id = i.partner_id";

function books_invoices(array $f = [], $tail = '') {
    books_migrate();
    [$w, $a] = books_invoice_where($f);
    return books_try(fn() => ops_all(
        "SELECT i.*, o.name office_name,
                (SELECT COALESCE(SUM(x.amount),0) FROM receipt_allocations x WHERE x.invoice_id = i.id) settled_cash,
                (SELECT COALESCE(SUM(c.total),0) FROM credit_notes c WHERE c.invoice_id = i.id AND c.status='ISSUED') credited
         " . BOOKS_INV_FROM . " WHERE $w "
        . ($tail ?: "ORDER BY (i.status IN ('ISSUED','PART_PAID')) DESC, i.invoice_date DESC, i.id DESC"), $a));
}

function books_invoices_count(array $f = []) {
    books_migrate();
    [$w, $a] = books_invoice_where($f);
    return (int)books_try(fn() => ops_val("SELECT COUNT(*) " . BOOKS_INV_FROM . " WHERE $w", $a), 0);
}

function books_counts() {
    books_migrate();
    [$w, $a] = scope_clause('i.office_id', 'i.sbu');
    $n = fn($extra, $args = []) => (int)books_try(fn() => ops_val(
        "SELECT COUNT(*) FROM invoices i WHERE $w AND ($extra)", array_merge($a, $args)), 0);
    $sum = fn($extra) => (float)books_try(fn() => ops_val(
        "SELECT COALESCE(SUM(i.total),0) FROM invoices i WHERE $w AND ($extra)", $a), 0);
    $today = date('Y-m-d');
    return [
        'draft'   => $n("i.status='DRAFT'"),
        'open'    => $n("i.status IN ('ISSUED','PART_PAID')"),
        'overdue' => $n("i.status IN ('ISSUED','PART_PAID') AND i.due_date <> '' AND i.due_date < ?", [$today]),
        'open_value' => $sum("i.status IN ('ISSUED','PART_PAID')"),
    ];
}

function books_receipts(array $f = [], $limit = 200) {
    books_migrate();
    [$w, $a] = scope_office_clause('r.office_id');
    $wh = [$w];
    if (!empty($f['partner'])) { $wh[] = 'r.partner_id = ?'; $a[] = (int)$f['partner']; }
    if (!empty($f['q'])) {
        $wh[] = '(r.receipt_no LIKE ? OR r.partner_name LIKE ? OR r.reference LIKE ?)';
        $like = '%' . $f['q'] . '%'; array_push($a, $like, $like, $like);
    }
    if (!empty($f['unallocated'])) $wh[] = "(r.amount + r.tds_amount) > COALESCE((SELECT SUM(x.amount) FROM receipt_allocations x WHERE x.receipt_id = r.id),0) + 0.01";
    return books_try(fn() => ops_all(
        "SELECT r.*, COALESCE((SELECT SUM(x.amount) FROM receipt_allocations x WHERE x.receipt_id = r.id),0) allocated
         FROM receipts r WHERE " . implode(' AND ', $wh) . "
         ORDER BY r.receipt_date DESC, r.id DESC LIMIT " . max(1, (int)$limit), $a));
}

function books_receipt($id) {
    books_migrate();
    return books_try(fn() => ops_one("SELECT * FROM receipts WHERE id=?", [(int)$id]), null) ?: null;
}

function books_receipt_allocations($receiptId) {
    books_migrate();
    return books_try(fn() => ops_all(
        "SELECT a.*, i.invoice_no, i.invoice_date, i.total FROM receipt_allocations a
         LEFT JOIN invoices i ON i.id = a.invoice_id WHERE a.receipt_id=? ORDER BY a.id", [(int)$receiptId]));
}

function books_invoice_receipts($invoiceId) {
    books_migrate();
    return books_try(fn() => ops_all(
        "SELECT a.*, r.receipt_no, r.receipt_date, r.mode, r.reference FROM receipt_allocations a
         LEFT JOIN receipts r ON r.id = a.receipt_id WHERE a.invoice_id=? ORDER BY r.receipt_date, a.id", [(int)$invoiceId]));
}

function books_invoice_credit_notes($invoiceId) {
    books_migrate();
    return books_try(fn() => ops_all(
        "SELECT * FROM credit_notes WHERE invoice_id=? ORDER BY cn_date, id", [(int)$invoiceId]));
}

// Invoices this customer still owes on — what the allocation screen offers.
function books_open_invoices($partnerId) {
    books_migrate();
    $out = [];
    foreach (books_try(fn() => ops_all(
        "SELECT id, invoice_no, invoice_date, due_date, total FROM invoices
         WHERE partner_id=? AND status IN ('ISSUED','PART_PAID') ORDER BY invoice_date, id", [(int)$partnerId])) as $r) {
        $s = books_settled((int)$r['id']);
        if ($s['outstanding'] <= 0.01) continue;
        $r['outstanding'] = $s['outstanding'];
        $out[] = $r;
    }
    return $out;
}

// Deputations finished and not yet on any invoice — the operations-to-books
// handover, and the list nobody could produce before.
function books_billable_jobs($partnerId = 0, $limit = 300) {
    books_migrate();
    [$w, $a] = scope_clause('j.executing_office_id', 'j.sbu');
    $extra = '';
    if ($partnerId) { $extra = ' AND c.client_id = ?'; $a[] = (int)$partnerId; }
    return books_try(fn() => ops_all(
        "SELECT j.id, j.job_code, j.closed_at, j.invoice_value, j.executing_office_id,
                c.id call_id, c.billable_value, c.billable_rate, c.billable_qty, c.client_id,
                COALESCE(bp.display_name, bp.legal_name) client_name
         FROM jobs j
         LEFT JOIN calls c ON c.id = j.call_id
         LEFT JOIN business_partners bp ON bp.id = c.client_id
         WHERE $w AND j.closed_flag = 1 $extra
           AND NOT EXISTS (SELECT 1 FROM invoice_lines l JOIN invoices i2 ON i2.id = l.invoice_id
                           WHERE l.job_id = j.id AND i2.status <> 'CANCELLED')
         ORDER BY c.client_id, j.closed_at DESC, j.id DESC LIMIT " . max(1, (int)$limit), $a));
}
