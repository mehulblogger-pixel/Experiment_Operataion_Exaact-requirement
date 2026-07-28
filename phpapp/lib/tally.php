<?php
// ============================================================================
//  Tally export — hand the books over, rather than rebuild them here
//
//  Roadmap 5.2 asked one of two things: build GST into the invoice side, or
//  export to Tally. The recommendation in ROADMAP.md was the export, and this
//  is it. The reasoning has not changed: a full GST engine is HSN/SAC, place
//  of supply, IGST vs CGST+SGST, e-invoice and IRN, credit notes, returns and
//  reconciliation — and the firm still keeps its books in Tally afterwards.
//  Two systems holding the same ledger is not an improvement on one.
//
//  So this file does the smaller, truer job: take the invoices this system
//  already knows about, work out the tax split, and write a file Tally will
//  import as Sales vouchers — with the party ledger, the bill reference and
//  the tax ledgers filled in. Payments received become Receipt vouchers that
//  knock off the bill they belong to.
//
//  Four decisions worth knowing about:
//
//   1. **Nothing is guessed.** If the client has no GSTIN and no state there
//      is no way to know whether the tax is IGST or CGST+SGST, so the row is
//      refused and says why. An export that silently picks one is worse than
//      an export that stops — the wrong one is a return that has to be
//      revised. Every refusal is listed on screen with its reason.
//   2. **The invoice amount is the invoice amount.** The money desk falls back
//      to the quoted value or the inter-office credit when the accountant has
//      not typed a figure yet. That fallback is right for a worklist and wrong
//      for a ledger, so here a row with no invoice amount is refused.
//   3. **Exporting is remembered.** Importing the same sales voucher into
//      Tally twice is a real and painful mistake, so each batch is recorded
//      against the job, the screen shows what has already gone, and a batch
//      can be undone if the import failed at the other end.
//   4. **No library.** Tally's import format is XML over a documented
//      envelope; it is written here by hand for the same reason the .docx and
//      PDF writers were — MilesWeb shared hosting has no Composer.
//
//  Deliberately NOT done: pulling anything back out of Tally, e-invoice/IRN
//  generation, credit notes, and TDS. Those need the accountant's ledger
//  structure, which is theirs, not ours.
// ============================================================================

// GST state codes. The first two digits of a GSTIN are the state, which is the
// only reliable way to decide inter-state versus intra-state; the state name on
// the client record is the fallback for an unregistered buyer.
const GST_STATE_CODES = [
    '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab',
    '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi',
    '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim',
    '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram',
    '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
    '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh',
    '24' => 'Gujarat', '25' => 'Daman and Diu', '26' => 'Dadra and Nagar Haveli and Daman and Diu',
    '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep',
    '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
    '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh',
    '38' => 'Ladakh', '97' => 'Other Territory',
];

function tally_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = pk_clause();
    // One row per voucher handed over. Kept so the screen can say "already
    // exported" and so a failed import can be undone and sent again.
    db()->exec("CREATE TABLE IF NOT EXISTS tally_exports (
        id $pk, batch_ref VARCHAR(40) DEFAULT '', kind VARCHAR(10) DEFAULT 'SALES',
        job_id INT, voucher_no VARCHAR(60) DEFAULT '', voucher_date VARCHAR(20) DEFAULT '',
        amount DECIMAL(14,2) DEFAULT 0,
        exported_by VARCHAR(150) DEFAULT '', exported_at VARCHAR(30) DEFAULT '')");
}

function tally_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}

// ---- Who may use it --------------------------------------------------------
// Same gate as the money desk: this reads the same rows and writes nothing to
// them. Changing the ledger names is a settings act and is gated harder.
function tally_can()        { return can('finance.reconcile') || can('data.credit') || is_master(); }
function tally_can_manage() { return can('finance.reconcile') || can('settings.manage') || is_master(); }

// ---- Settings --------------------------------------------------------------
// The accountant's own ledger names. Defaults are the names Tally ships with,
// so a first export usually works without touching this panel at all.
const TALLY_DEFAULTS = [
    'tally_company'      => '',
    'tally_state'        => '',
    'tally_sales_ledger' => 'Sales Accounts',
    'tally_cgst_ledger'  => 'CGST',
    'tally_sgst_ledger'  => 'SGST',
    'tally_igst_ledger'  => 'IGST',
    'tally_bank_ledger'  => 'Bank Account',
    'tally_debtors_group'=> 'Sundry Debtors',
    'tally_voucher_type' => 'Sales',
    'tally_receipt_type' => 'Receipt',
    'tally_gst_pct'      => '18',
    'tally_amount_basis' => 'EXCL',   // is jobs.invoice_amount before or after tax?
    'tally_sac'          => '998346', // technical testing and analysis services
];

function tally_settings() {
    $cfg = [];
    foreach (TALLY_DEFAULTS as $k => $d) $cfg[$k] = (string)setting_get($k, $d);
    // An empty saved value means "not set", not "blank on purpose" — fall back
    // so a half-filled panel cannot produce a voucher with no ledger name.
    foreach (TALLY_DEFAULTS as $k => $d) if (trim($cfg[$k]) === '' && $d !== '') $cfg[$k] = $d;
    if (trim($cfg['tally_company']) === '') $cfg['tally_company'] = (string)setting_get('company_name', '');
    $cfg['state_code'] = tally_state_code($cfg['tally_state']);
    return $cfg;
}

function tally_settings_save(array $post) {
    foreach (array_keys(TALLY_DEFAULTS) as $k) {
        if (!array_key_exists($k, $post)) continue;
        $v = trim((string)$post[$k]);
        if ($k === 'tally_gst_pct')      $v = (string)max(0, min(100, (float)$v));
        if ($k === 'tally_amount_basis') $v = $v === 'INCL' ? 'INCL' : 'EXCL';
        setting_set($k, substr($v, 0, 120));
    }
}

// ---- Where the tax lives ---------------------------------------------------
// A state name typed by a human is not a code. Match it loosely — case, spaces
// and the "&" people write instead of "and" — and give up rather than guess.
// Always a two-character string, never an integer. PHP turns the array key
// '24' into the int 24 while leaving '01' a string, so reading a code straight
// out of GST_STATE_CODES gives back one of two types — and the strict compare
// that decides IGST then called a Gujarat-to-Gujarat sale inter-state, because
// 24 !== '24'. Every code in this file goes through here.
function tally_code_str($code) { return str_pad((string)$code, 2, '0', STR_PAD_LEFT); }

function tally_state_code($name) {
    $n = strtolower(trim((string)$name));
    if ($n === '') return '';
    if (preg_match('/^\d{2}$/', $n)) return isset(GST_STATE_CODES[$n]) ? tally_code_str($n) : '';
    $norm = fn($s) => preg_replace('/[^a-z]/', '', str_replace('&', 'and', strtolower($s)));
    $want = $norm($n);
    foreach (GST_STATE_CODES as $code => $label) if ($norm($label) === $want) return tally_code_str($code);
    // "Orissa", "Pondicherry", "Uttaranchal" and the like still appear on old
    // client records; treat the former names as the current ones.
    static $alias = ['orissa'=>'21','pondicherry'=>'34','uttaranchal'=>'05','newdelhi'=>'07',
                     'delhinct'=>'07','tamilnadu'=>'33','jammukashmir'=>'01','andaman'=>'35'];
    return $alias[$want] ?? '';
}

// The state code we are selling from, and the one we are selling to.
function tally_gstin_state($gstin) {
    $g = strtoupper(preg_replace('/\s+/', '', (string)$gstin));
    if (strlen($g) < 2) return '';
    $c = substr($g, 0, 2);
    return isset(GST_STATE_CODES[$c]) ? tally_code_str($c) : '';
}

// ---- The arithmetic --------------------------------------------------------
// Everything about one invoice: what it is worth, how the tax splits, and
// every reason it cannot be handed over. Rounding is done once, on the tax, and
// the halves are made to add back to it exactly — a rupee lost to rounding is a
// voucher Tally refuses.
function tally_compute(array $r, array $cfg) {
    $issues = [];
    $amount = (float)($r['invoice_amount'] ?? 0);
    $gstin  = trim((string)($r['gstin'] ?? ''));
    $pct    = $r['quote_gst_pct'] !== null && (float)$r['quote_gst_pct'] > 0
              ? (float)$r['quote_gst_pct'] : (float)$cfg['tally_gst_pct'];

    if (trim((string)($r['invoice_number'] ?? '')) === '') $issues[] = 'no invoice number';
    if (trim((string)($r['invoice_date'] ?? '')) === '')   $issues[] = 'no invoice date';
    // The money desk may show a quoted or credit figure here. A ledger may not.
    if ($amount <= 0) $issues[] = 'no invoice amount recorded';
    if (empty($r['client_id']))  $issues[] = 'the client is not on the party master';

    $theirState = tally_gstin_state($gstin) ?: tally_state_code($r['state'] ?? '');
    $ourState   = $cfg['state_code'];
    if ($ourState === '')  $issues[] = 'our own state is not set (Ledger names & tax below)';
    if ($theirState === '' && $pct > 0) $issues[] = 'the client has no GSTIN and no state — IGST or CGST+SGST cannot be decided';

    $interstate = ($ourState !== '' && $theirState !== '' && $ourState !== $theirState);

    if ($cfg['tally_amount_basis'] === 'INCL' && $pct > 0) {
        $total   = round($amount, 2);
        $taxable = round($total * 100 / (100 + $pct), 2);
        $tax     = round($total - $taxable, 2);
    } else {
        $taxable = round($amount, 2);
        $tax     = round($taxable * $pct / 100, 2);
        $total   = round($taxable + $tax, 2);
    }
    $cgst = $sgst = $igst = 0.0;
    if ($interstate) $igst = $tax;
    else { $cgst = round($tax / 2, 2); $sgst = round($tax - $cgst, 2); }

    return $r + [
        'gst_pct' => $pct, 'taxable' => $taxable, 'tax' => $tax, 'total' => $total,
        'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $igst,
        'interstate' => $interstate, 'their_state' => $theirState,
        'place_of_supply' => $theirState !== '' ? (GST_STATE_CODES[$theirState] ?? '') : '',
        'issues' => $issues, 'ok' => !$issues,
    ];
}

// ---- The candidates --------------------------------------------------------
// Raised invoices in the window, scoped to the user's offices exactly as the
// money desk is. $kind 'SALES' looks at the invoice date, 'RECEIPT' at the
// payment date — a payment in July against a June invoice belongs in July.
function tally_rows($from, $to, $kind = 'SALES', $includeDone = false) {
    tally_migrate();
    [$jw, $ja] = scope_clause('j.executing_office_id', 'j.sbu');
    $cfg = tally_settings();
    $dateCol = $kind === 'RECEIPT' ? 'j.payment_date' : 'j.invoice_date';
    $where = $kind === 'RECEIPT'
        ? "j.invoice_raised=1 AND j.payment_received=1"
        : "j.invoice_raised=1";
    // Placeholder order follows the SQL, not the logic: the one in the LEFT JOIN
    // is bound before anything in the WHERE. Getting this backwards silently
    // compared the invoice date against the word "SALES" and returned nothing.
    $args = [$kind];
    foreach ($ja as $a) $args[] = $a;
    if ($from !== '') { $where .= " AND $dateCol <> '' AND $dateCol >= ?"; $args[] = $from; }
    if ($to   !== '') { $where .= " AND $dateCol <> '' AND $dateCol <= ?"; $args[] = $to; }
    try {
        $rows = ops_all(
            "SELECT j.id, j.job_code, j.invoice_number, j.invoice_date, j.invoice_due_date,
                    j.invoice_amount, j.payment_date, j.payment_amount, j.sbu, j.quotation_id,
                    bp.id client_id, bp.display_name, bp.legal_name, bp.gstin, bp.state,
                    q.gst_pct quote_gst_pct, o.name office_name,
                    te.batch_ref done_batch, te.exported_at done_at
             FROM jobs j
             LEFT JOIN calls c ON c.id = j.call_id
             LEFT JOIN business_partners bp ON bp.id = c.client_id
             LEFT JOIN quotations q ON q.id = j.quotation_id
             LEFT JOIN offices o ON o.id = j.executing_office_id
             LEFT JOIN tally_exports te ON te.job_id = j.id AND te.kind = ?
             WHERE $jw AND ($where)
             ORDER BY $dateCol ASC, j.id ASC", $args);
    } catch (Throwable $e) {
        if (!tally_missing_table($e)) throw $e;
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        if (!$includeDone && $r['done_batch']) continue;
        $c = tally_compute($r, $cfg);
        // A receipt carries the money that actually arrived, which is not
        // always the invoice total — a short payment must not be rounded up.
        if ($kind === 'RECEIPT') {
            $paid = (float)($r['payment_amount'] ?? 0);
            $c['receipt_amount'] = $paid > 0 ? round($paid, 2) : $c['total'];
            if ($paid <= 0 && $c['total'] <= 0) $c['issues'][] = 'no payment amount recorded';
            if (trim((string)$r['payment_date']) === '') $c['issues'][] = 'no payment date';
            $c['ok'] = !$c['issues'];
        }
        $out[] = $c;
    }
    return $out;
}

// ---- The file ---------------------------------------------------------------
function tally_x($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
function tally_date($d) { $t = strtotime((string)$d); return $t ? date('Ymd', $t) : ''; }
function tally_party(array $r) { return trim((string)($r['display_name'] ?: $r['legal_name'])) ?: 'Unknown party'; }
function tally_amt($v) { return number_format((float)$v, 2, '.', ''); }

// One ledger entry. In Tally's import XML a negative amount is a debit and a
// positive one a credit; ISDEEMEDPOSITIVE says which side the entry sits on.
function tally_entry($ledger, $isDebit, $amount, $bill = null, $billType = 'New Ref') {
    $signed = tally_amt($isDebit ? -abs($amount) : abs($amount));
    $x  = "   <ALLLEDGERENTRIES.LIST>\n";
    $x .= "    <LEDGERNAME>" . tally_x($ledger) . "</LEDGERNAME>\n";
    $x .= "    <ISDEEMEDPOSITIVE>" . ($isDebit ? 'Yes' : 'No') . "</ISDEEMEDPOSITIVE>\n";
    $x .= "    <AMOUNT>$signed</AMOUNT>\n";
    if ($bill !== null && $bill !== '') {
        // Bill-wise detail is what turns the voucher into an outstanding item,
        // and what lets a receipt knock the right invoice off the ageing.
        $x .= "    <BILLALLOCATIONS.LIST>\n";
        $x .= "     <NAME>" . tally_x($bill) . "</NAME>\n";
        $x .= "     <BILLTYPE>" . tally_x($billType) . "</BILLTYPE>\n";
        $x .= "     <AMOUNT>$signed</AMOUNT>\n";
        $x .= "    </BILLALLOCATIONS.LIST>\n";
    }
    $x .= "   </ALLLEDGERENTRIES.LIST>\n";
    return $x;
}

// Party ledger masters, so a first import into a fresh company does not fail on
// every unknown name. Opt-in: on an established company they are unnecessary,
// and Tally reports a duplicate rather than silently skipping it.
function tally_ledger_masters(array $rows, array $cfg) {
    $seen = []; $x = '';
    foreach ($rows as $r) {
        $name = tally_party($r);
        if (isset($seen[$name])) continue;
        $seen[$name] = true;
        $state = $r['place_of_supply'] ?: (string)($r['state'] ?? '');
        $x .= "  <TALLYMESSAGE xmlns:UDF=\"TallyUDF\">\n";
        $x .= "   <LEDGER NAME=\"" . tally_x($name) . "\" ACTION=\"Create\">\n";
        $x .= "    <NAME>" . tally_x($name) . "</NAME>\n";
        $x .= "    <PARENT>" . tally_x($cfg['tally_debtors_group']) . "</PARENT>\n";
        $x .= "    <ISBILLWISEON>Yes</ISBILLWISEON>\n";
        $x .= "    <COUNTRYNAME>India</COUNTRYNAME>\n";
        if (trim((string)$r['gstin']) !== '') {
            $x .= "    <PARTYGSTIN>" . tally_x(strtoupper(trim((string)$r['gstin']))) . "</PARTYGSTIN>\n";
            $x .= "    <GSTREGISTRATIONTYPE>Regular</GSTREGISTRATIONTYPE>\n";
        }
        if ($state !== '') $x .= "    <LEDSTATENAME>" . tally_x($state) . "</LEDSTATENAME>\n";
        $x .= "   </LEDGER>\n  </TALLYMESSAGE>\n";
    }
    return $x;
}

function tally_sales_voucher(array $r, array $cfg) {
    $party = tally_party($r);
    $vno   = (string)$r['invoice_number'];
    $x  = "  <TALLYMESSAGE xmlns:UDF=\"TallyUDF\">\n";
    $x .= "   <VOUCHER VCHTYPE=\"" . tally_x($cfg['tally_voucher_type']) . "\" ACTION=\"Create\" OBJVIEW=\"Invoice Voucher View\">\n";
    $x .= "    <DATE>" . tally_date($r['invoice_date']) . "</DATE>\n";
    $x .= "    <EFFECTIVEDATE>" . tally_date($r['invoice_date']) . "</EFFECTIVEDATE>\n";
    $x .= "    <VOUCHERTYPENAME>" . tally_x($cfg['tally_voucher_type']) . "</VOUCHERTYPENAME>\n";
    $x .= "    <VOUCHERNUMBER>" . tally_x($vno) . "</VOUCHERNUMBER>\n";
    $x .= "    <REFERENCE>" . tally_x($vno) . "</REFERENCE>\n";
    $x .= "    <REFERENCEDATE>" . tally_date($r['invoice_date']) . "</REFERENCEDATE>\n";
    $x .= "    <PARTYLEDGERNAME>" . tally_x($party) . "</PARTYLEDGERNAME>\n";
    $x .= "    <PARTYNAME>" . tally_x($party) . "</PARTYNAME>\n";
    $x .= "    <BASICBUYERNAME>" . tally_x($party) . "</BASICBUYERNAME>\n";
    if (trim((string)$r['gstin']) !== '')
        $x .= "    <PARTYGSTIN>" . tally_x(strtoupper(trim((string)$r['gstin']))) . "</PARTYGSTIN>\n";
    if ($r['place_of_supply'] !== '')
        $x .= "    <PLACEOFSUPPLY>" . tally_x($r['place_of_supply']) . "</PLACEOFSUPPLY>\n";
    $x .= "    <PERSISTEDVIEW>Invoice Voucher View</PERSISTEDVIEW>\n";
    $x .= "    <ISINVOICE>Yes</ISINVOICE>\n";
    $x .= "    <NARRATION>" . tally_x('Inspection services — job ' . $r['job_code']
            . ($r['office_name'] ? ' (' . $r['office_name'] . ')' : '')) . "</NARRATION>\n";
    // Party first: debited with the gross, carrying the bill reference.
    $x .= tally_entry($party, true, $r['total'], $vno, 'New Ref');
    $x .= tally_entry($cfg['tally_sales_ledger'], false, $r['taxable']);
    if ($r['igst'] > 0) $x .= tally_entry($cfg['tally_igst_ledger'], false, $r['igst']);
    if ($r['cgst'] > 0) $x .= tally_entry($cfg['tally_cgst_ledger'], false, $r['cgst']);
    if ($r['sgst'] > 0) $x .= tally_entry($cfg['tally_sgst_ledger'], false, $r['sgst']);
    $x .= "   </VOUCHER>\n  </TALLYMESSAGE>\n";
    return $x;
}

function tally_receipt_voucher(array $r, array $cfg) {
    $party  = tally_party($r);
    $amount = (float)$r['receipt_amount'];
    $vno    = 'RCPT-' . $r['job_code'];
    $x  = "  <TALLYMESSAGE xmlns:UDF=\"TallyUDF\">\n";
    $x .= "   <VOUCHER VCHTYPE=\"" . tally_x($cfg['tally_receipt_type']) . "\" ACTION=\"Create\">\n";
    $x .= "    <DATE>" . tally_date($r['payment_date']) . "</DATE>\n";
    $x .= "    <VOUCHERTYPENAME>" . tally_x($cfg['tally_receipt_type']) . "</VOUCHERTYPENAME>\n";
    $x .= "    <VOUCHERNUMBER>" . tally_x($vno) . "</VOUCHERNUMBER>\n";
    $x .= "    <PARTYLEDGERNAME>" . tally_x($party) . "</PARTYLEDGERNAME>\n";
    $x .= "    <NARRATION>" . tally_x('Payment received against invoice ' . $r['invoice_number']) . "</NARRATION>\n";
    $x .= tally_entry($cfg['tally_bank_ledger'], true, $amount);
    // "Agst Ref" against the invoice number is what clears it from the ageing.
    $x .= tally_entry($party, false, $amount, (string)$r['invoice_number'], 'Agst Ref');
    $x .= "   </VOUCHER>\n  </TALLYMESSAGE>\n";
    return $x;
}

function tally_xml(array $rows, array $cfg, $kind = 'SALES', $withMasters = false) {
    $x  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $x .= "<ENVELOPE>\n <HEADER>\n  <TALLYREQUEST>Import Data</TALLYREQUEST>\n </HEADER>\n";
    $x .= " <BODY>\n  <IMPORTDATA>\n   <REQUESTDESC>\n    <REPORTNAME>Vouchers</REPORTNAME>\n";
    if (trim($cfg['tally_company']) !== '')
        $x .= "    <STATICVARIABLES><SVCURRENTCOMPANY>" . tally_x($cfg['tally_company']) . "</SVCURRENTCOMPANY></STATICVARIABLES>\n";
    $x .= "   </REQUESTDESC>\n   <REQUESTDATA>\n";
    if ($withMasters) $x .= tally_ledger_masters($rows, $cfg);
    foreach ($rows as $r)
        $x .= $kind === 'RECEIPT' ? tally_receipt_voucher($r, $cfg) : tally_sales_voucher($r, $cfg);
    $x .= "   </REQUESTDATA>\n  </IMPORTDATA>\n </BODY>\n</ENVELOPE>\n";
    return $x;
}

// The same figures as a spreadsheet, for the accountant who wants to look
// before importing, or whose Tally is on somebody else's desk.
function tally_csv_rows(array $rows, $kind = 'SALES') {
    if ($kind === 'RECEIPT') {
        $csv = [['Date','Voucher','Party','GSTIN','Against invoice','Amount','Job']];
        foreach ($rows as $r)
            $csv[] = [$r['payment_date'], 'RCPT-' . $r['job_code'], tally_party($r), $r['gstin'],
                      $r['invoice_number'], tally_amt($r['receipt_amount']), $r['job_code']];
        return $csv;
    }
    $csv = [['Invoice date','Invoice no','Party','GSTIN','Place of supply','Taxable','GST %','CGST','SGST','IGST','Total','Job','Office']];
    foreach ($rows as $r)
        $csv[] = [$r['invoice_date'], $r['invoice_number'], tally_party($r), $r['gstin'],
                  $r['place_of_supply'], tally_amt($r['taxable']), $r['gst_pct'],
                  tally_amt($r['cgst']), tally_amt($r['sgst']), tally_amt($r['igst']),
                  tally_amt($r['total']), $r['job_code'], $r['office_name']];
    return $csv;
}

// ---- Remembering what went -------------------------------------------------
function tally_record(array $rows, $kind, $ref) {
    tally_migrate();
    $now = date('c'); $by = user_name(current_user());
    $st = db()->prepare("INSERT INTO tally_exports (batch_ref,kind,job_id,voucher_no,voucher_date,amount,exported_by,exported_at)
                         VALUES (?,?,?,?,?,?,?,?)");
    foreach ($rows as $r) {
        $st->execute([$ref, $kind, (int)$r['id'],
            $kind === 'RECEIPT' ? 'RCPT-' . $r['job_code'] : (string)$r['invoice_number'],
            $kind === 'RECEIPT' ? (string)$r['payment_date'] : (string)$r['invoice_date'],
            $kind === 'RECEIPT' ? (float)$r['receipt_amount'] : (float)$r['total'],
            $by, $now]);
    }
}

function tally_batches($limit = 12) {
    tally_migrate();
    try {
        return ops_all("SELECT batch_ref, kind, COUNT(*) n, SUM(amount) total,
                               MIN(exported_by) exported_by, MIN(exported_at) exported_at
                        FROM tally_exports GROUP BY batch_ref, kind
                        ORDER BY MIN(exported_at) DESC, batch_ref DESC
                        LIMIT " . (int)$limit);
    } catch (Throwable $e) { if (!tally_missing_table($e)) throw $e; return []; }
}

function tally_undo($ref) {
    tally_migrate();
    $n = (int)ops_val("SELECT COUNT(*) FROM tally_exports WHERE batch_ref=?", [$ref]);
    db()->prepare("DELETE FROM tally_exports WHERE batch_ref=?")->execute([$ref]);
    return $n;
}

// A reference somebody can read out over the phone, and which cannot collide
// with the one made a second earlier by the person at the next desk.
function tally_new_ref($kind) {
    return ($kind === 'RECEIPT' ? 'RCP' : 'SAL') . '-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}

// ---- The screen ------------------------------------------------------------
function ops_tally($route, $method) {
    ops_require(tally_can(), 'You cannot open the Tally export.');
    tally_migrate();

    if ($route === 'tally-settings') {
        ops_require(tally_can_manage(), 'Only Accounts or an administrator can change the ledger names.');
        if ($method === 'POST') { tally_settings_save($_POST); flash('Ledger names and tax defaults saved.'); }
        redirect('/tally');
    }
    if ($route === 'tally-undo') {
        ops_require(tally_can_manage(), 'Only Accounts or an administrator can undo an export.');
        if ($method === 'POST') {
            $n = tally_undo(trim((string)($_POST['batch_ref'] ?? '')));
            flash($n
                ? "Batch undone — $n voucher(s) are offered for export again. Make sure they were not imported into Tally."
                : 'That batch was not found.', $n ? 'success' : 'error');
        }
        redirect('/tally');
    }

    $kind = ($_GET['kind'] ?? $_POST['kind'] ?? 'SALES') === 'RECEIPT' ? 'RECEIPT' : 'SALES';
    $from = trim((string)($_GET['from'] ?? $_POST['from'] ?? ''));
    $to   = trim((string)($_GET['to']   ?? $_POST['to']   ?? ''));
    if ($from === '' && $to === '') {          // default to the month just gone
        $from = date('Y-m-01', strtotime('first day of last month'));
        $to   = date('Y-m-t',  strtotime('last day of last month'));
    }
    $showDone = !empty($_GET['done']) || !empty($_POST['done']);
    $cfg  = tally_settings();
    $rows = tally_rows($from, $to, $kind, $showDone);
    $ready   = array_values(array_filter($rows, fn($r) => $r['ok'] && !$r['done_batch']));
    $blocked = array_values(array_filter($rows, fn($r) => !$r['ok']));

    if ($route === 'tally-export' && $method === 'POST') {
        if (!$ready) { flash('Nothing in that window is ready to export.', 'error'); redirect('/tally?' . http_build_query(['kind'=>$kind,'from'=>$from,'to'=>$to])); }
        $fmt = ($_POST['format'] ?? 'xml') === 'csv' ? 'csv' : 'xml';
        $ref = tally_new_ref($kind);
        // Recorded before the bytes go out: a file that reached the browser and
        // was not recorded is the double-import this table exists to prevent,
        // and undoing a batch is one click.
        tally_record($ready, $kind, $ref);
        if ($fmt === 'csv') csv_download('tally-' . strtolower($kind) . '-' . $ref . '.csv', tally_csv_rows($ready, $kind));
        $xml = tally_xml($ready, $cfg, $kind, !empty($_POST['masters']));
        send_uploaded_file($xml, 'tally-' . strtolower($kind) . '-' . $ref . '.xml', 'application/xml');
        return true;
    }

    view('ops/tally_export', [
        'cfg' => $cfg, 'kind' => $kind, 'from' => $from, 'to' => $to,
        'rows' => $rows, 'ready' => $ready, 'blocked' => $blocked,
        'showDone' => $showDone, 'batches' => tally_batches(),
        'canManage' => tally_can_manage(),
        'states' => GST_STATE_CODES,
    ]);
    return true;
}
