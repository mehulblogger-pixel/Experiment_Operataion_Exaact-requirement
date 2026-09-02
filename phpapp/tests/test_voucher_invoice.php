<?php
// Voucher → Invoice bridge: approving a marketplace engagement voucher must raise a
// DRAFT tax invoice in the books engine — fee + reimbursables as lines, carrying the
// posting's SAC and GST%, with TDS computed on the pre-GST value. It must be
// idempotent (one invoice per voucher) and must never build a parallel invoice.
t_section('voucher approval raises a GST + TDS invoice');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    connect_market_migrate(); connect_engage_migrate(); connect_engv_migrate(); books_migrate();

    // A client, and a fee-only requirement carrying SAC / GST / TDS.
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Bapa Sitaram Enterprises','Bapa Sitaram Enterprises',1,'ACTIVE')")->execute();
    $party = (int)db()->lastInsertId();
    $rid = (int)cx_requirement_create([
        'title'=>'Electrical Commissioning Engineer','poster_party_id'=>$party,'poster_name'=>'Bapa Sitaram Enterprises',
        'discipline_code'=>'ELEC','location'=>'Khavda','rate_inclusive'=>'EXCLUSIVE','voucher_cadence'=>'PER_DAY',
        'est_rate'=>6000,'est_qty'=>10,'est_tax_pct'=>18,'est_tds_pct'=>2,'est_sac'=>'998519',
        'reimb'=>['travel'=>['mode'=>'ACTUALS'],'lodging'=>['mode'=>'CEILING','ceiling'=>3000,'per'=>'DAY'],
                  'allowance'=>['mode'=>'IN_RATE'],'conveyance'=>['mode'=>'IN_RATE'],'misc'=>['mode'=>'IN_RATE']],
    ], true);

    // Award + book the engagement, open a voucher, claim fee + reimbursables.
    [$rok,,$pid] = connect_pro_register(['name'=>'Inv Pro','email'=>'inv_'.substr(md5(uniqid('',true)),0,6).'@ex.com','discipline_code'=>'ELEC']);
    $aid = cx_application_add($rid, ['applicant_professional_id'=>$pid,'applicant_name'=>'Inv Pro']);
    db()->prepare("UPDATE cx_applications SET status='ACCEPTED' WHERE id=?")->execute([$aid]);
    db()->prepare("UPDATE cx_requirements SET status='AWARDED', awarded_application_id=? WHERE id=?")->execute([$aid,$rid]);
    [$bok,,$eid] = connect_engage_save_for_requirement($rid, ['basis'=>'MAN_DAYS','quantity'=>10,'rate'=>6000,'rate_unit'=>'day','start_date'=>'2026-09-01']);
    t_ok($bok && $eid>0, 'engagement booked');

    [$vok,,$vid] = connect_engv_open_for_engagement($eid, ['period_label'=>'Sep 2026']);
    t_ok($vok && $vid>0, 'voucher opened');
    // 10 days @ 6000 = 60000 fee; claim travel 500 (actuals) + lodging 2500 (ceiling) = 3000 reimb.
    connect_engv_add_line($vid, ['work_date'=>'2026-09-01','units'=>10,'travel'=>500,'lodging'=>2500,'allowance'=>999,'conveyance'=>999,'misc'=>999]);
    $vr = connect_engv_get($vid);
    t_eq((int)$vr['fee_total'], 60000, 'voucher fee = 10 × 6000');
    t_eq((int)$vr['reimb_total'], 3000, 'voucher reimbursables = 500 travel + 2500 hotel (in-rate heads blocked to 0)');

    // No invoice before approval.
    t_eq(books_invoice_for_voucher($vid), 0, 'no invoice exists before approval');

    // Approve → the bridge raises the draft invoice automatically.
    connect_engv_set_status($vid, 'SUBMITTED', 'pro');
    connect_engv_set_status($vid, 'APPROVED', 'client');
    $invId = books_invoice_for_voucher($vid);
    t_ok($invId > 0, 'approving the voucher raised a books invoice');

    $inv = books_invoice($invId);
    t_eq((int)$inv['partner_id'], $party, 'the invoice is billed to the client who posted');
    t_eq((string)$inv['status'], 'DRAFT', 'it is a DRAFT — finance reviews & issues it');
    t_eq((int)$inv['voucher_id'], $vid, 'the invoice is tied back to the voucher');

    $lines = books_lines($invId);
    t_eq(count($lines), 2, 'two lines: fee + reimbursables');
    $amounts = array_map(fn($l)=>(int)round((float)$l['amount']), $lines);
    sort($amounts);
    t_eq($amounts, [3000, 60000], 'line amounts are the fee (60000) and reimbursables (3000)');
    t_ok((string)$lines[0]['hsn_sac'] === '998519', 'the SAC/HSN from the posting is on the invoice line');
    t_eq((int)round((float)$lines[0]['gst_pct']), 18, 'the GST% from the posting is on the invoice line');

    // Totals: subtotal 63000, GST 18% = 11340, total 74340; TDS 2% of 63000 = 1260; net 73080.
    t_eq((int)round((float)$inv['subtotal']), 63000, 'taxable value = fee + reimbursables');
    t_eq((int)round((float)$inv['cgst'] + (float)$inv['sgst'] + (float)$inv['igst']), 11340, 'GST 18% of 63000');
    t_eq((int)round((float)$inv['total']), 74340, 'invoice total incl. GST');
    t_eq((int)round((float)$inv['tds_amount']), 1260, 'TDS 2% on the 63000 PRE-GST value (not on 74340)');
    t_eq((int)round(books_net_payable($inv)), 73080, 'net payable = total 74340 − TDS 1260');

    // Idempotent — a second call does not create a duplicate invoice.
    $again = connect_voucher_invoice($vid);
    t_eq($again, $invId, 'the bridge is idempotent — same invoice, never a duplicate');
    t_eq((int)ops_val("SELECT COUNT(*) FROM invoices WHERE voucher_id=?", [$vid]), 1, 'exactly one invoice for the voucher');

    // An ordinary manual invoice is unaffected: no TDS, no voucher link.
    $plain = books_invoice_create(['partner_id'=>$party]);
    $pInv = books_invoice((int)$plain['id']);
    t_eq((int)round((float)$pInv['tds_amount']), 0, 'a normal invoice has no TDS (backward-compatible)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
