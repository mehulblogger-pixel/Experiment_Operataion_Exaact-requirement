<?php
// Posting-time cost estimate — inclusive vs fee-only (exclusive), the five
// reimbursable heads, client-set ceilings (never auto-capped), per-day vs
// per-deployment, and GST on top. This is the commercial heart of a posting, so
// it is tested to the rupee.
t_section('posting cost estimate (inclusive / exclusive / GST)');

// --- INCLUSIVE: fee × qty, GST on top, no reimbursables ---
$e = connect_reqterms_estimate(['rate_inclusive'=>'INCLUSIVE', 'est_rate'=>6000, 'est_qty'=>10, 'est_tax_pct'=>18]);
t_ok($e['inclusive'] === true, 'inclusive flag set');
t_eq((int)$e['fee_total'], 60000, 'inclusive fee = 6000 × 10');
t_eq((int)$e['reimb_total'], 0, 'inclusive has no reimbursables');
t_eq((int)$e['subtotal'], 60000, 'inclusive subtotal = fee');
t_eq((int)$e['tax'], 10800, 'GST 18% of 60000 = 10800');
t_eq((int)$e['grand'], 70800, 'inclusive grand total = 70800');
t_ok($e['has_estimate'] === true, 'estimate is present when rate & qty given');

// --- EXCLUSIVE: fee + ceilings, per-day and per-deployment, GST on top ---
$terms = json_encode([
    'allowance'  => ['mode'=>'CEILING', 'ceiling'=>800,  'per'=>'DAY'],         // food 800/day × 10 = 8000
    'lodging'    => ['mode'=>'CEILING', 'ceiling'=>3000, 'per'=>'DAY'],         // hotel 3000/day × 10 = 30000
    'travel'     => ['mode'=>'CEILING', 'ceiling'=>5000, 'per'=>'DEPLOYMENT'],  // travel 5000 once = 5000
    'conveyance' => ['mode'=>'ACTUALS'],                                        // at actuals — excluded from number
    'misc'       => ['mode'=>'IN_RATE'],                                        // in the fee — 0
]);
$x = connect_reqterms_estimate(['rate_inclusive'=>'EXCLUSIVE', 'est_rate'=>6000, 'est_qty'=>10, 'est_tax_pct'=>18, 'reimb_terms'=>$terms]);
t_eq((int)$x['fee_total'], 60000, 'exclusive fee = 6000 × 10');
t_eq((int)$x['reimb_total'], 43000, 'reimbursables = 8000 + 30000 + 5000 (conveyance actuals + misc in-rate excluded)');
t_eq((int)$x['subtotal'], 103000, 'exclusive subtotal = fee + reimbursables');
t_eq((int)$x['tax'], 18540, 'GST 18% of 103000');
t_eq((int)$x['grand'], 121540, 'exclusive grand total');
t_ok($x['has_actuals'] === true, 'at-actuals head raises the actuals flag');
t_ok((int)$x['lines']['conveyance']['amount'] === 0, 'an at-actuals head contributes 0 to the number');
t_ok((int)$x['lines']['travel']['amount'] === 5000, 'a per-deployment ceiling counts once, not per day');

// --- a ceiling is NEVER auto-capped: a huge client number flows straight through ---
$big = connect_reqterms_estimate(['rate_inclusive'=>'EXCLUSIVE','est_rate'=>1,'est_qty'=>1,'est_tax_pct'=>0,
    'reimb_terms'=>json_encode(['lodging'=>['mode'=>'CEILING','ceiling'=>999999,'per'=>'DEPLOYMENT']])]);
t_eq((int)$big['reimb_total'], 999999, 'the client’s ceiling is used verbatim — code never caps it');

// --- no numbers entered → no estimate shown (old postings stay silent) ---
$none = connect_reqterms_estimate(['rate_inclusive'=>'INCLUSIVE']);
t_ok($none['has_estimate'] === false, 'no rate/qty → no estimate');
t_eq((int)$none['grand'], 0, 'empty estimate totals zero');

// --- storage round-trip: what the form posts is what the estimate reads back ---
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    connect_market_migrate();
    db()->prepare("INSERT INTO business_partners (legal_name,is_client,status) VALUES ('Bapa Sitaram Enterprises',1,'ACTIVE')")->execute();
    $party = (int)db()->lastInsertId();
    $in = [
        'title'=>'Electrical Commissioning Engineer','poster_party_id'=>$party,'poster_name'=>'Bapa Sitaram Enterprises',
        'discipline_code'=>'ELEC','location'=>'Khavda, Kutch','work_type'=>'FREELANCE','positions'=>1,
        'start_date'=>date('Y-m-d'),'rate_unit'=>'day',
        'deputation_basis'=>'MAN_DAYS','rate_inclusive'=>'EXCLUSIVE','voucher_cadence'=>'PER_DAY',
        'est_rate'=>6000,'est_qty'=>10,'est_tax_pct'=>18,
        'reimb'=>[
            'allowance'=>['mode'=>'CEILING','ceiling'=>800,'per'=>'DAY'],
            'lodging'  =>['mode'=>'CEILING','ceiling'=>3000,'per'=>'DAY'],
            'travel'   =>['mode'=>'CEILING','ceiling'=>5000,'per'=>'DEPLOYMENT'],
            'conveyance'=>['mode'=>'ACTUALS'],
            'misc'     =>['mode'=>'IN_RATE'],
        ],
    ];
    $rid = (int)cx_requirement_create($in, true);
    $row = cx_requirement_get($rid);
    t_eq((string)$row['rate_inclusive'], 'EXCLUSIVE', 'rate model stored');
    t_eq((int)$row['est_rate'], 6000, 'base fee stored');
    t_eq((int)$row['est_qty'], 10, 'estimated qty stored');
    $back = connect_reqterms_estimate($row);
    t_eq((int)$back['grand'], 121540, 'the stored posting reproduces the same grand total end-to-end');
    $p = connect_reqterms_parse($row);
    t_eq((string)$p['lodging']['mode'], 'CEILING', 'lodging term round-tripped');
    t_eq((int)$p['lodging']['ceiling'], 3000, 'lodging ceiling round-tripped');
    t_eq((string)$p['travel']['per'], 'DEPLOYMENT', 'travel per-deployment round-tripped');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
