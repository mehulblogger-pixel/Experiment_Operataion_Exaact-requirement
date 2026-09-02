<?php
// The reimbursement ceilings a client sets at POSTING must survive the award into
// the ENGAGEMENT, so the professional sees their limits at voucher time. A posting
// estimate that dies at award is useless — this guards the whole carry-through.
t_section('reimbursement terms carry posting → engagement');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    connect_market_migrate(); connect_engage_migrate();

    // A fee-only posting with real ceilings the client typed.
    $terms = json_encode([
        'allowance' => ['mode' => 'CEILING', 'ceiling' => 800,  'per' => 'DAY'],
        'lodging'   => ['mode' => 'CEILING', 'ceiling' => 3000, 'per' => 'DAY'],
        'travel'    => ['mode' => 'CEILING', 'ceiling' => 5000, 'per' => 'DEPLOYMENT'],
        'conveyance'=> ['mode' => 'ACTUALS'],
        'misc'      => ['mode' => 'IN_RATE'],
    ]);
    $rid = (int)cx_requirement_create([
        'title' => 'Electrical Commissioning Engineer', 'discipline_code' => 'ELEC', 'poster_name' => 'Bapa Sitaram Enterprises',
        'location' => 'Khavda, Kutch', 'rate_inclusive' => 'EXCLUSIVE', 'voucher_cadence' => 'PER_DAY',
        'est_rate' => 6000, 'est_qty' => 10, 'est_tax_pct' => 18,
        'reimb' => ['allowance'=>['mode'=>'CEILING','ceiling'=>800,'per'=>'DAY'],
                    'lodging'=>['mode'=>'CEILING','ceiling'=>3000,'per'=>'DAY'],
                    'travel'=>['mode'=>'CEILING','ceiling'=>5000,'per'=>'DEPLOYMENT'],
                    'conveyance'=>['mode'=>'ACTUALS'], 'misc'=>['mode'=>'IN_RATE']],
    ], true);
    t_ok(connect_reqterms_parse(cx_requirement_get($rid))['lodging']['ceiling'] == 3000, 'posting stored the hotel ceiling');

    // Award it to a professional, then book the engagement (real path, no override).
    $pid = (int)connect_pro_register(['name' => 'Carry Pro', 'email' => 'carry_' . substr(md5(uniqid('', true)),0,6) . '@ex.com', 'discipline_code' => 'ELEC'])[2] ?? 0;
    if (!$pid) { // fall back to whatever the register returns
        [$rok, , $pid] = connect_pro_register(['name' => 'Carry Pro2', 'email' => 'carry2_' . substr(md5(uniqid('', true)),0,6) . '@ex.com', 'discipline_code' => 'ELEC']);
    }
    $aid = cx_application_add($rid, ['applicant_professional_id' => $pid, 'applicant_name' => 'Carry Pro']);
    db()->prepare("UPDATE cx_applications SET status='ACCEPTED' WHERE id=?")->execute([$aid]);
    db()->prepare("UPDATE cx_requirements SET status='AWARDED', awarded_application_id=? WHERE id=?")->execute([$aid, $rid]);

    [$ok, , $eid] = connect_engage_save_for_requirement($rid, ['basis' => 'MAN_DAYS', 'quantity' => 10, 'rate' => 6000, 'rate_unit' => 'day', 'start_date' => '2026-09-01']);
    t_ok($ok && $eid > 0, 'the engagement books once awarded');

    $eng = connect_engage_for_requirement($rid);
    t_eq((string)$eng['rate_inclusive'], 'EXCLUSIVE', 'the engagement inherits the fee-only rate model');
    t_ok(trim((string)($eng['reimb_terms'] ?? '')) !== '', 'the engagement carries the reimbursement terms (not blank)');
    $et = connect_reqterms_parse($eng);
    t_eq((string)$et['lodging']['mode'], 'CEILING', 'hotel term survived to the engagement');
    t_eq((int)$et['lodging']['ceiling'], 3000, 'hotel ceiling survived verbatim');
    t_eq((string)$et['travel']['per'], 'DEPLOYMENT', 'travel per-deployment survived');
    t_eq((string)$et['conveyance']['mode'], 'ACTUALS', 'at-actuals conveyance survived');

    // A re-book (upsert) keeps the terms too.
    connect_engage_save_for_requirement($rid, ['basis' => 'MAN_DAYS', 'quantity' => 12, 'rate' => 6000, 'rate_unit' => 'day', 'start_date' => '2026-09-01']);
    t_ok(trim((string)(connect_engage_for_requirement($rid)['reimb_terms'] ?? '')) !== '', 're-booking keeps the terms');

    // --- ENFORCEMENT: a head the client "provides" / put "in the rate" cannot be
    //     claimed on the voucher, however hard the professional tries. misc is IN_RATE. ---
    [$vok, , $vid] = connect_engv_open_for_engagement($eid, ['period_label' => 'TEST']);
    t_ok($vok && $vid > 0, 'a voucher opens for the fee-only engagement');
    connect_engv_add_line($vid, ['work_date' => '2026-09-01', 'units' => 1,
        'travel' => 500, 'lodging' => 2500, 'conveyance' => 300, 'allowance' => 800, 'misc' => 999]);
    $ln = connect_engv_lines($vid)[0] ?? [];
    t_eq((int)($ln['allowance'] ?? -1), 800,  'a ceiling head (food) is claimable — kept');
    t_eq((int)($ln['lodging'] ?? -1),  2500, 'a ceiling head (hotel) is claimable — kept');
    t_eq((int)($ln['conveyance'] ?? -1), 300, 'an at-actuals head (conveyance) is claimable — kept');
    t_eq((int)($ln['misc'] ?? -1), 0, 'an IN_RATE head (misc) is NOT claimable — forced to 0 despite a 999 attempt');
    // claim set matches: exactly the four non-in-rate heads
    $claim = connect_reqterms_claimable_heads(ops_one("SELECT reimb_terms FROM cx_engagements WHERE id=?", [$eid]) ?: []);
    t_ok(!in_array('misc', $claim, true) && count($claim) === 4, 'the voucher offers exactly the four claimable heads');

    // LEGACY SAFETY: an engagement with NO terms keeps every head claimable (unchanged).
    $rid2 = (int)cx_requirement_create(['title'=>'Legacy excl','poster_name'=>'X','rate_inclusive'=>'EXCLUSIVE'], true);
    db()->prepare("UPDATE cx_engagements SET reimb_terms='' WHERE id=?")->execute([$eid]); // simulate legacy
    t_eq(count(connect_reqterms_claimable_heads(ops_one("SELECT reimb_terms FROM cx_engagements WHERE id=?", [$eid]) ?: [])), 5, 'no terms → all five heads stay claimable (no break for existing data)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
