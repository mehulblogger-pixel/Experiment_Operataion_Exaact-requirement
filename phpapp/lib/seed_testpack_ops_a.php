<?php
// ============================================================================
//  TEST PACK — group OPS-A: Operations test book sheets "1 Intake",
//  "2 Allocation", "3 Assignment changes", "4 Field - My Jobs & punch",
//  "5 Close & lock", "Stage 1 (B0–B5)" and "Industries".
//  Loaded by lib/seed_testpack.php (tp_seeders() finds this file).
//
//  Every record is made the way the app's own screens make it (the same
//  columns, the call/job codes carry the TP- tag), and the app's own helpers
//  finish the job: job_visits_sync() for the visit days, tosrm_set_status() for
//  the cancelled work order, tosrm_assign_accept() for the declined assignment.
//  Names marked "(example)" are made-up test values, not real business data.
//
//  Map entries: kind 'record' (open this), 'none' (no record needed — why),
//  'type' (the test checks typing — type the value the book gives).
// ============================================================================

tp_register('OPS-A', 'Operations · Intake, Allocation, Assignment, Field, Close & lock, Stage 1, Industries', 'tp_seed_ops_a');

function tp_seed_ops_a(array $ctx) {
    $ahm = $ctx['ahm']; $mum = $ctx['mum'];
    $I = $ctx['ins']['inspector']; $I2 = $ctx['ins']['inspector2']; $SR = $ctx['ins']['srinspector'];

    // ---- Customers --------------------------------------------------------------
    //  The Ops book's own customers. Reused when the copy already has them (opstest
    //  does); otherwise made here with the exact state the book expects.
    $tbValves = tp_client('TB Valves Pvt Ltd', ['home_branch_id' => $ahm]);
    $tbPharma = tp_client('TB Pharma LLP', ['home_branch_id' => $ahm]);
    $tbPaused = tp_client('TB Paused Co', ['home_branch_id' => $ahm]);
    tp_client_state($tbPaused, 'HOLD', 'Credit review');
    $tbDefault = tp_client('TB Defaulter Co', ['home_branch_id' => $ahm]);
    tp_client_state($tbDefault, 'BLOCKED', 'Unpaid for 180 days');
    //  The Sales test customers (made-up GSTINs the Sales book already uses).
    $testline = tp_client('Testline Pumps Pvt Ltd', ['gstin' => '24AAKCT4821M1Z1', 'pan' => 'AAKCT4821M', 'state' => 'Gujarat', 'home_branch_id' => $ahm]);
    if (in_array($testline, tp_tracked('business_partners'), true))
        tp_insert('partner_addresses', ['partner_id' => $testline, 'address_type' => 'SITE', 'label' => 'Mumbai plant', 'city' => 'Mumbai',
            'state' => 'Maharashtra', 'country' => 'India', 'gstin' => '27AAKCT4821M1ZV', 'state_code' => '27']);
    tp_client('Bluewater Fittings Pvt Ltd', ['gstin' => '24AAFCB7315Q1ZD', 'pan' => 'AAFCB7315Q', 'state' => 'Gujarat', 'home_branch_id' => $ahm]);
    tp_client('Kaveri Valves Pvt Ltd', ['gstin' => '24AABCK2290L1ZI', 'pan' => 'AABCK2290L', 'state' => 'Gujarat', 'home_branch_id' => $ahm]);

    // OA-07 — a registered contract whose opening is still PENDING.
    if (!ops_val("SELECT id FROM partner_contracts WHERE contract_number='TB-CT-PEND'"))
        tp_insert('partner_contracts', ['partner_id' => $tbValves, 'contract_number' => 'TB-CT-PEND', 'title' => 'Test pack (TP) — contract awaiting opening',
            'start_date' => tp_d(-1), 'end_date' => tp_d(364), 'is_active' => 1, 'open_status' => 'PENDING', 'branch_id' => $ahm,
            'requested_by' => TP_BY, 'requested_at' => date('c'), 'sbu' => 'IND', 'notes' => 'Test pack (TP)']);

    // ---- People the book names that are not logins ------------------------------
    //  OB-02 / S1-B5-02 — "Mohan Rao (SC-001)" with a lapsed REQUIRED certificate.
    $mohan = (int) (ops_val("SELECT id FROM inspectors WHERE emp_code='SC-001' ORDER BY id LIMIT 1") ?: 0)
          ?: (int) (ops_val("SELECT id FROM inspectors WHERE LOWER(name)='mohan rao' ORDER BY id LIMIT 1") ?: 0);
    if (!$mohan) $mohan = tp_insert('inspectors', ['name' => 'Mohan Rao', 'first_name' => 'Mohan', 'last_name' => 'Rao', 'emp_code' => 'SC-001',
        'sbu' => 'IND', 'sbus' => 'IND', 'status' => 'ACTIVE', 'staff_kind' => 'ASSET', 'roll_type' => 'OWN', 'home_office_id' => $ahm,
        'designation' => 'Inspector', 'created_at' => date('c')]);
    tp_insert('inspector_certs', ['inspector_id' => $mohan, 'name' => 'Level II UT (example)', 'number' => 'TP-CERT-001',
        'issued_date' => tp_d(-740), 'valid_from' => tp_d(-740), 'valid_to' => tp_d(-10), 'status' => 'EXPIRED', 'is_mandatory' => 1,
        'updated_by' => TP_BY, 'created_at' => date('c')]);
    //  S1-B4-02 — a freelancer; S1-B4-04 — a person on agency payroll.
    $free = tp_insert('inspectors', ['name' => 'TP Freelance Engineer', 'first_name' => 'TP Freelance', 'last_name' => 'Engineer', 'emp_code' => 'TP-FL-01',
        'sbu' => 'IND', 'sbus' => 'IND', 'status' => 'ACTIVE', 'staff_kind' => 'FREELANCER', 'roll_type' => 'OWN', 'home_office_id' => $ahm,
        'designation' => 'Inspector', 'created_at' => date('c')]);
    tp_insert('inspectors', ['name' => 'TP Agency Engineer', 'first_name' => 'TP Agency', 'last_name' => 'Engineer', 'emp_code' => 'TP-AG-01',
        'sbu' => 'IND', 'sbus' => 'IND', 'status' => 'ACTIVE', 'staff_kind' => 'ASSET', 'roll_type' => 'AGENCY', 'agency_name' => 'TP Manpower Agency (example)',
        'home_office_id' => $ahm, 'designation' => 'Inspector', 'created_at' => date('c')]);
    //  S1-B4-03 — a sub-contractor firm.
    $subcon = tp_insert('subcons', ['agency' => 'TP Subcon Inspection Services (example)', 'inspector_name' => 'TP Subcon Engineer',
        'skill' => 'Visual and dimensional', 'experience_level' => 'MID', 'regions' => 'Gujarat', 'active' => 1, 'created_at' => date('c')]);

    // ---- Work orders (calls) and jobs -------------------------------------------
    $call = fn($code, $client, array $f = []) => tp_call($code, array_merge(['client_id' => $client, 'office_id' => $ahm], $f));

    //  "Part 1" — the Ahmedabad call of OA-01 with the job of OB-03 (Test Inspector, today).
    $c101 = $call('TP-C-0101', $tbValves);
    $j201 = tp_job('TP-J-0201', $c101, ['inspector_id' => $I ?: null, 'date' => tp_d(0),
        'site_lat' => 23.0225, 'site_lon' => 72.5714, 'site_label' => 'TB Valves works (example location)']);
    //  OK-14 — an expense line on it.
    tp_insert('expenses', ['job_id' => $j201, 'inspector_id' => $I ?: null, 'sbu' => 'IND', 'lodging' => 1500, 'food' => 300,
        'exp_date' => tp_d(0), 'notes' => 'Test pack (TP) — example expense line', 'created_at' => date('c')]);
    //  OB-04 / OB-05 — the second (TB Pharma) call, not yet allocated.
    $c102 = $call('TP-C-0102', $tbPharma);
    //  OA-09 — a spare call to delete.
    $call('TP-C-0103', $tbValves, ['notes' => 'Test pack (TP) — spare call for the delete test']);
    //  OC-01…06, OE-04 — the job of OB-05 (Test SrInspector, today).
    $c104 = $call('TP-C-0104', $tbPharma);
    tp_job('TP-J-0301', $c104, ['inspector_id' => $SR ?: null, 'date' => tp_d(0)]);
    //  OD-08 — a fresh job today the inspector has not punched on.
    $c105 = $call('TP-C-0105', $tbValves);
    tp_job('TP-J-0202', $c105, ['inspector_id' => $I ?: null, 'date' => tp_d(0)]);
    //  OD-10 — a job scheduled for tomorrow.
    $c106 = $call('TP-C-0106', $tbValves, ['date' => tp_d(1)]);
    tp_job('TP-J-0203', $c106, ['inspector_id' => $I ?: null, 'date' => tp_d(1)]);
    //  OE-05 — a job past its close-by date, already locked.
    $c107 = $call('TP-C-0107', $tbValves, ['date' => tp_d(-15), 'call_received_date' => tp_d(-16)]);
    $j204 = tp_job('TP-J-0204', $c107, ['inspector_id' => $I ?: null, 'date' => tp_d(-15)]);
    db()->prepare("UPDATE jobs SET locked_at=?, lock_alerted_at=? WHERE id=?")->execute([date('c', strtotime('-5 days')), date('c', strtotime('-5 days')), $j204]);
    //  OP-01 — the client pays the hotel (Lodging) on top of the fee; no bill filed yet.
    $c108 = $call('TP-C-0108', $tbValves, ['date' => tp_d(-1), 'call_received_date' => tp_d(-2), 'chargeable_heads' => 'LODGING']);
    tp_job('TP-J-0205', $c108, ['inspector_id' => $I ?: null, 'date' => tp_d(-1), 'chargeable_heads' => 'LODGING']);
    //  S1-B2-03 — an inspection that ended 3 days ago with no report.
    $c109 = $call('TP-C-0109', $tbValves, ['date' => tp_d(-3), 'call_received_date' => tp_d(-4)]);
    tp_job('TP-J-0206', $c109, ['inspector_id' => $I ?: null, 'date' => tp_d(-3)]);
    //  S1-B2-03 (future job with an engineer) + S1-B3-05 (report frequency "SINGLE", not on the list).
    $c110 = $call('TP-C-0110', $tbPharma, ['date' => tp_d(5)]);
    tp_job('TP-J-0302', $c110, ['inspector_id' => $SR ?: null, 'date' => tp_d(5), 'reporting_frequency' => 'SINGLE']);
    //  S1-B2-04 / S1-B4-01 — an engineer allocated today, then the assignment Declined.
    $c111 = $call('TP-C-0111', $tbPharma);
    $j208 = tp_job('TP-J-0401', $c111, ['inspector_id' => $I2 ?: null, 'date' => tp_d(0)]);
    if (function_exists('tosrm_assign_accept')) tosrm_assign_accept($j208, 'DECLINED', 'Not available on this date (test pack example)', TP_BY);
    //  S1-B4-03 — a job given to the sub-contractor firm, no engineer.
    $c112 = $call('TP-C-0112', $tbValves, ['date' => tp_d(2)]);
    tp_job('TP-J-0501', $c112, ['inspector_id' => null, 'subcon_id' => $subcon ?: null, 'date' => tp_d(2)]);
    //  S1-B3-03 — a deputation whose end date is in the future (on the freelancer).
    $c113 = $call('TP-C-0113', $tbValves, ['date' => tp_d(-10), 'call_received_date' => tp_d(-12), 'reporting_frequency' => 'WEEKLY']);
    tp_job('TP-J-0601', $c113, ['inspector_id' => $free ?: null, 'job_type' => 'DEPUTATION', 'dep_status' => 'ACTIVE', 'dep_site' => 'TB Valves works',
        'scheduled_date' => tp_d(-10), 'inspection_start_date' => tp_d(-10), 'inspection_end_date' => tp_d(20), 'inspection_dates' => tp_d(-10),
        'mandays' => 31, 'reporting_frequency' => 'WEEKLY']);
    //  S1-B3-01 / S1-B3-02 — a work order with no Deliverable.
    $call('TP-C-0114', $tbValves, ['deliverables' => '']);
    //  S1-B2-01 — a cancelled work order (with its reason), through the app's own status step.
    $c115 = $call('TP-C-0115', $tbValves);
    if (function_exists('tosrm_set_status')) tosrm_set_status($c115, 'CANCELLED', 'Client cancelled the order (test pack example)', TP_BY, true);

    // ---- Map: test id → what to open ------------------------------------------
    $rec  = fn($t, ...$codes) => ['kind' => 'record', 'text' => $t, 'codes' => $codes];
    $none = fn($t) => ['kind' => 'none', 'text' => $t];
    $type = fn($t) => ['kind' => 'type', 'text' => $t];
    $who = $I ? '' : ' (login test.inspector is missing on this copy — the job has no engineer)';
    $m = [
        // 1 Intake
        'OA-01' => $type('Type the call as the steps say. TB Valves Pvt Ltd is ready (Active, no hold).'),
        'OA-02' => $rec('Inspection calls → search TP-C-0101 (the Part 1 call).', 'TP-C-0101'),
        'OA-03' => $type('Type the call for TB Paused Co — it is ready on HOLD ("Credit review").'),
        'OA-04' => $type('Type the call for TB Defaulter Co — it is ready BLOCKED ("Unpaid for 180 days").'),
        'OA-05' => $type('Type the cross-office call as the steps say (Mumbai executes, credit 0).'),
        'OA-06' => $type('Type the call with Total credit 5000.'),
        'OA-07' => $type('Type the call with contract number TB-CT-PEND — that contract is ready, opening still PENDING.'),
        'OA-08' => $type('Type the long Notes text the steps give.'),
        'OA-09' => $rec('Open call TP-C-0103 (the spare call) and try Delete — first as test.coord, then as test.bam.', 'TP-C-0103'),
        'OK-01' => $type('Type the cross-office call with Total credit −5.'),
        'OK-02' => $type('Type the same date in Date 1 and Date 2.'),
        'OK-03' => $type('Type a required date of last week.'),
        'OK-04' => $type('Type the three unit rates the steps give.'),
        'OK-05' => $type('Type the unit rate −1000.'),
        'OK-06' => $type('Type the two searches the steps give.'),
        'OK-07' => $rec('As test.coord2 open /call-edit?id=<id of TP-C-0101> (the Ahmedabad call).', 'TP-C-0101'),
        'OK-08' => $rec('Open Edit on call TP-C-0101 in two windows.', 'TP-C-0101'),
        'OK-21' => $rec('Repeat on call TP-C-0101.', 'TP-C-0101'),
        // 2 Allocation
        'OB-01' => $rec('Open call TP-C-0101 → + Allocate Job.', 'TP-C-0101'),
        'OB-02' => $rec('Open call TP-C-0101 → + Allocate Job → Engineer picker: Mohan Rao (SC-001) has a lapsed required certificate (Level II UT, expired 10 days ago).', 'TP-C-0101'),
        'OB-03' => $rec('Job TP-J-0201 is the result of OB-03 (Test Inspector, today)' . $who . '. Because Test Inspector is now busy today, allocating Test Inspector again shows the OB-04 same-day message.', 'TP-J-0201'),
        'OB-04' => $rec('Open call TP-C-0102 (TB Pharma LLP, today) → + Allocate Job to Test Inspector for today.', 'TP-C-0102'),
        'OB-05' => $rec('Open call TP-C-0102 → allocate to Test SrInspector (EMP17) instead. Job TP-J-0301 shows the finished result.', 'TP-C-0102', 'TP-J-0301'),
        'OB-06' => $rec('As test.coord2 open /job-new?call=<id of TP-C-0101>.', 'TP-C-0101'),
        'OB-07' => $rec('As test.bdm open /job-new?call=<id of TP-C-0101>.', 'TP-C-0101'),
        'OK-09' => $rec('Operations → Jobs → search TP-J-0201 → Download CSV.', 'TP-J-0201'),
        'OK-10' => $rec('Job TP-J-0201 (Test Inspector\'s Part 1 job).', 'TP-J-0201'),
        'OK-11' => $rec('Job TP-J-0201 → Money → Invoice & payment.', 'TP-J-0201'),
        'OK-12' => $rec('Job TP-J-0201.', 'TP-J-0201'),
        'OK-13' => $rec('Job TP-J-0201 (Test Inspector\'s, not Test Inspector2\'s).', 'TP-J-0201'),
        'OK-14' => $rec('Job TP-J-0201 has an expense line (Lodging ₹1,500 + Food ₹300, today).', 'TP-J-0201'),
        'OK-15' => $rec('Job TP-J-0201.', 'TP-J-0201'),
        // 3 Assignment changes
        'OC-01' => $rec('Job TP-J-0301 → Assignment.', 'TP-J-0301'),
        'OC-02' => $rec('Job TP-J-0301 → Assignment.', 'TP-J-0301'),
        'OC-03' => $rec('Job TP-J-0301 (on Test SrInspector) → Assignment → Reassign.', 'TP-J-0301'),
        'OC-04' => $rec('Job TP-J-0301 → Assignment → Reschedule.', 'TP-J-0301'),
        'OC-05' => $rec('Job TP-J-0301 → Assignment → No-show.', 'TP-J-0301'),
        'OC-06' => $rec('Job TP-J-0301 → Assignment → Cancel (do this one last — it cancels the job).', 'TP-J-0301'),
        'OC-07' => $rec('As test.coord2 open job TP-J-0201 (Ahmedabad) and try Hold.', 'TP-J-0201'),
        'OC-08' => $rec('As test.inspector open job TP-J-0201 and try to reassign it.', 'TP-J-0201'),
        // 4 Field - My Jobs & punch
        'OD-01' => $rec('As test.inspector open My Jobs — TP-J-0201 (TB Valves, today) is there.', 'TP-J-0201'),
        'OD-02' => $rec('My Jobs → TP-J-0201, before any punch today.', 'TP-J-0201'),
        'OD-03' => $rec('My Jobs → TP-J-0201.', 'TP-J-0201'),
        'OD-04' => $rec('My Jobs → TP-J-0201 (TB Valves).', 'TP-J-0201'),
        'OD-05' => $rec('My Jobs → TP-J-0201.', 'TP-J-0201'),
        'OD-06' => $rec('As test.inspector2, post a punch for job TP-J-0201.', 'TP-J-0201'),
        'OD-07' => $rec('My Jobs → TP-J-0201.', 'TP-J-0201'),
        'OD-08' => $rec('Admin ticks "photo required" first. Then as test.inspector punch in on job TP-J-0202 (a fresh job today, no punch yet).', 'TP-J-0202'),
        'OD-09' => $rec('Admin turns the geofence on first. Job TP-J-0201 has a site location (an example point in Ahmedabad).', 'TP-J-0201'),
        'OD-10' => $rec('Admin turns on "attendance only on the scheduled date" first. Job TP-J-0203 is scheduled for tomorrow.', 'TP-J-0203'),
        'OD-11' => $rec('Job TP-J-0201 (Ahmedabad, on Test Inspector).', 'TP-J-0201'),
        // 5 Close & lock
        'OE-01' => $rec('Job TP-J-0201 → Close.', 'TP-J-0201'),
        'OE-02' => $rec('Job TP-J-0201 → Close (after the OD-04 and OD-07 punches).', 'TP-J-0201'),
        'OE-03' => $rec('Job TP-J-0201 → Close again.', 'TP-J-0201'),
        'OE-04' => $rec('As test.bdm open /job-close?id=<id of TP-J-0301>.', 'TP-J-0301'),
        'OE-05' => $rec('Job TP-J-0204 (inspection 15 days ago) is locked.', 'TP-J-0204'),
        'OP-01' => $rec('Job TP-J-0205 — its call TP-C-0108 says the client pays the hotel (Lodging); no bill filed yet.', 'TP-J-0205', 'TP-C-0108'),
        'OP-02' => $rec('Job TP-J-0201 → QAP / ITP file. (Use any small PDF of your own.)', 'TP-J-0201'),
        // Industries
        'OI-01' => $none('A wording setting the admin applies — any job and the call register show it. No record needed.'),
        'OI-02' => $none('A wording setting the admin applies — any job, the register and My Work show it. No record needed.'),
        // Stage 1 (B0–B5)
        'S1-B0-01' => $none('Checks the uploaded zip on /deploy-check.php. No record needed.'),
        'S1-B1-01' => $none('The card row counts whatever is open. The pack\'s calls and jobs give every card a number.'),
        'S1-B1-02' => $rec('"No engineer yet" counts TP-C-0102 and TP-C-0114 among others.', 'TP-C-0102', 'TP-C-0114'),
        'S1-B1-03' => $none('Each card opens its own filtered list. The pack\'s records give the cards numbers.'),
        'S1-B1-04' => $rec('Overdue cards include TP-J-0206 (ended 3 days ago).', 'TP-J-0206'),
        'S1-B1-05' => $none('Compares two counts on the same screen. No record needed.'),
        'S1-B1-06' => $none('Reads the chips on the register. No record needed.'),
        'S1-B1-07' => $none('Reads the MIS dashboard. No record needed.'),
        'S1-B1-08' => $rec('As test.inspector: TP-J-0201 needs a report; TP-J-0206 is overdue.', 'TP-J-0201', 'TP-J-0206'),
        'S1-B2-01' => $rec('Work order TP-C-0115 is Cancelled (reason "Client cancelled the order").', 'TP-C-0115'),
        'S1-B2-02' => $none('Clicks the "New requests" card. No record needed.'),
        'S1-B2-03' => $rec('Open TP-J-0302 (future, Test SrInspector) and TP-J-0206 (ended 3 days ago, no report). Read "Stage".', 'TP-J-0302', 'TP-J-0206'),
        'S1-B2-04' => $rec('Work order TP-C-0111 — its job TP-J-0401 was Declined by the engineer (with a reason).', 'TP-C-0111', 'TP-J-0401'),
        'S1-B3-01' => $rec('Work order TP-C-0114 has no Deliverable.', 'TP-C-0114'),
        'S1-B3-02' => $rec('Work order TP-C-0114 (same as S1-B3-01).', 'TP-C-0114'),
        'S1-B3-03' => $rec('Deputation TP-J-0601 ends in 20 days. NOTE: it needs a TB Valves client-portal login, which the pack does not create (no logins are made).', 'TP-J-0601'),
        'S1-B3-04' => $none('Reads the notes on two cards. No record needed.'),
        'S1-B3-05' => $rec('Job TP-J-0302 has report frequency "SINGLE" (not on the list).', 'TP-J-0302'),
        'S1-B4-01' => $rec('Availability today: Test Inspector2 was allocated TP-J-0401, then Declined.', 'TP-J-0401'),
        'S1-B4-02' => $rec('Availability: TP Freelance Engineer (freelancer) is there.', 'TP-J-0601'),
        'S1-B4-03' => $rec('Job TP-J-0501 is with the sub-contractor firm "TP Subcon Inspection Services (example)", no engineer.', 'TP-J-0501'),
        'S1-B4-04' => $none('Agency staff lists "TP Agency Engineer" (agency payroll, made by the pack). No job needed.'),
        'S1-B5-01' => $type('Type 30 days as the steps say.'),
        'S1-B5-02' => $none('Mohan Rao (SC-001) has the lapsed required certificate — ask "who\'s free" for any range including today.'),
        'S1-B5-03' => $none('Needs a company without the inspection pack — a different company, not a record in this one.'),
    ];
    return $m;
}
