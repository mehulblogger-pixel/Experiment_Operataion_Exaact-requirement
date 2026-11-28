<?php
// ============================================================================
//  TEST PACK — group OPS-B: Operations test book sheets "6 Ops home & My Work",
//  "7 Scheduling & resources", "8 Controls & contracts", "9 Vouchers,
//  attendance, time", "10 Report handover", "11 Commercial handover" and
//  "12 Quality & insights".
//  Loaded by lib/seed_testpack.php (tp_seeders() finds this file; OPS-B runs
//  after OPS-A, so the Part 1 calls and jobs OPS-A makes are reused here).
//
//  Every record is made the way the app's own screens make it, and the app's
//  own helpers finish the job: tosrm_recurring_create() for the schedule,
//  exp_period_for() / exp_insert_line() / exp_recalc_total() for the claims,
//  billable_on_job_closed() for the billable event, job_visits_sync() (inside
//  tp_job) for the visit days. Codes: work orders TP-C-B7xx, jobs TP-J-B7xx,
//  report TP-IR-B703. Names marked "(example)" are made-up test values.
//
//  Removal: every row made here is in the pack's ledger (tp_insert / tp_track),
//  so tp_remove() deletes exactly these. A row the app writes later ON a pack
//  record is removed with it where the framework already does so (by job_id /
//  call_id / the pack's own clients and people).
//
//  Map entries: kind 'record' (open this), 'none' (no record needed — why),
//  'type' (the test checks typing — type the value the book gives).
// ============================================================================

tp_register('OPS-B', 'Operations · Ops home, Scheduling & resources, Controls, Vouchers & time, Report & commercial handover, Quality', 'tp_seed_ops_b');

/** A tiny one-page PDF, so a credential "uploaded by an engineer" opens. */
function tp_b_pdf() {
    $s = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
       . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 120]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
       . "4 0 obj<</Length 58>>stream\nBT /F1 12 Tf 20 60 Td (Test pack - example certificate) Tj ET\nendstream endobj\n"
       . "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    return 'data:application/pdf;base64,' . base64_encode($s);
}

/** Close a pack job the way the close screen leaves it (no e-mail, no expense). */
function tp_b_close($jobId, $daysAgo, $approval) {
    $at = date('c', strtotime('-' . (int) $daysAgo . ' days'));
    db()->prepare("UPDATE jobs SET closed_flag=1, closed_at=?, report_upload_date=?, tat_days=0, report_approval=? WHERE id=?")
        ->execute([$at, substr($at, 0, 10), (string) $approval, (int) $jobId]);
    if (function_exists('job_cost_snapshot')) { try { job_cost_snapshot((int) $jobId); } catch (Throwable $e) {} }
    if (function_exists('touch_row_version')) touch_row_version('jobs', (int) $jobId);
}

/** A claim (voucher) for one person and one past day, with one expense line on a
 *  pack job — made with the app's own period rule and line writer. */
function tp_b_voucher($insId, $date, $jobId, $status, array $extra = []) {
    if (!$insId || !function_exists('exp_period_for')) return 0;
    if (function_exists('exp_migrate')) exp_migrate();
    [$ps, $pe] = exp_period_for($date);
    $office = ops_val("SELECT home_office_id FROM inspectors WHERE id=?", [(int) $insId]) ?: null;
    $vid = tp_insert('vouchers', array_merge(['inspector_id' => (int) $insId, 'office_id' => $office, 'month' => substr($date, 0, 7),
        'period_start' => $ps, 'period_end' => $pe, 'status' => $status, 'created_by' => TP_BY, 'created_at' => date('c')], $extra));
    if (!$vid) return 0;
    $head = (string) (ops_val("SELECT code FROM expense_heads WHERE active=1 AND head_type IN ('BILL','NOBILL') ORDER BY sort_order, id LIMIT 1") ?: 'FOOD');
    $job = function_exists('exp_job') ? exp_job((int) $jobId) : null;
    if (function_exists('exp_insert_line')) {
        $line = exp_insert_line(['id' => $vid], ['date' => $date, 'job' => $job ?: null, 'code' => $head, 'amount' => 450,
            'hours' => 8, 'note' => 'Test pack (TP) — example expense line', 'source' => 'MANUAL']);
        if ($line) tp_track('voucher_entries', $line);
        exp_recalc_total($vid);
    }
    return $vid;
}

function tp_seed_ops_b(array $ctx) {
    //  The tables the cases touch exist on every install; made sure of here so a
    //  copy that has never opened one of these screens still loads cleanly.
    foreach (['exp_migrate', 'vappr_migrate', 'hwp_migrate', 'tosrm_migrate_d', 'identity_migrate', 'idems_migrate',
              'billable_migrate', 'competence_migrate', 'trust_migrate'] as $fn)
        if (function_exists($fn)) { try { $fn(); } catch (Throwable $e) {} }

    $ahm = $ctx['ahm'];
    $I = $ctx['ins']['inspector']; $I2 = $ctx['ins']['inspector2'];
    $opsMgr = (int) ($ctx['users']['test.opsmgr']['id'] ?? 0);
    $dirIns = (int) ($ctx['users']['test.director']['inspector_id'] ?? 0);
    $lm = strtotime('first day of last month');
    $lm10 = date('Y-m-10', $lm); $lm12 = date('Y-m-12', $lm); $lm14 = date('Y-m-14', $lm);
    $monday = date('Y-m-d', strtotime('next monday'));

    // ---- Customers --------------------------------------------------------------
    $tbValves = tp_client('TB Valves Pvt Ltd', ['home_branch_id' => $ahm]);   // OPS-A's — reused
    $tbPharma = tp_client('TB Pharma LLP', ['home_branch_id' => $ahm]);
    //  OG-01 — "TB Refinery Ltd is Active with no hold"; OK-20 — "TB Group Child".
    tp_client('TB Refinery Ltd', ['home_branch_id' => $ahm]);
    tp_client('TB Group Child', ['home_branch_id' => $ahm]);
    //  OP-13 / OP-14 — a customer of the pack's own, so the schedule's generated
    //  calls and its own SLA target go with the pack.
    $svc = tp_client('TP Service Schedules Pvt Ltd (example)', ['home_branch_id' => $ahm]);

    // ---- People (not logins) ----------------------------------------------------
    //  OP-04 / OP-06 — an engineer whose reporting manager is the Operations Manager,
    //  holding a credential he uploaded that nobody has verified yet.
    $re = (int) (ops_val("SELECT id FROM inspectors WHERE emp_code='TP-RE-01' ORDER BY id LIMIT 1") ?: 0);
    if (!$re) {
        $re = tp_insert('inspectors', ['name' => 'TP Report Engineer', 'first_name' => 'TP Report', 'last_name' => 'Engineer', 'emp_code' => 'TP-RE-01',
            'sbu' => 'IND', 'sbus' => 'IND', 'status' => 'ACTIVE', 'staff_kind' => 'ASSET', 'roll_type' => 'OWN', 'home_office_id' => $ahm,
            'designation' => 'Inspector', 'reports_to_id' => $opsMgr ?: null, 'created_at' => date('c')]);
        tp_insert('inspector_certs', ['inspector_id' => $re, 'name' => 'Level II MT (example)', 'number' => 'TP-CERT-B06',
            'issued_date' => tp_d(-60), 'valid_from' => tp_d(-60), 'valid_to' => tp_d(670), 'status' => 'VALID', 'is_mandatory' => 0,
            'file_name' => 'tp-level2-mt-example.pdf', 'file_mime' => 'application/pdf', 'file_data' => tp_b_pdf(),
            'verify_status' => '', 'updated_by' => TP_BY, 'created_at' => date('c')]);
    }
    //  OP-09 — an agency engineer whose only identity document has expired.
    $ag2 = (int) (ops_val("SELECT id FROM inspectors WHERE emp_code='TP-AG-02' ORDER BY id LIMIT 1") ?: 0);
    if (!$ag2) {
        $ag2 = tp_insert('inspectors', ['name' => 'TP Agency Engineer Two', 'first_name' => 'TP Agency', 'last_name' => 'Engineer Two', 'emp_code' => 'TP-AG-02',
            'sbu' => 'IND', 'sbus' => 'IND', 'status' => 'ACTIVE', 'staff_kind' => 'ASSET', 'roll_type' => 'AGENCY', 'agency_name' => 'TP Manpower Agency (example)',
            'home_office_id' => $ahm, 'designation' => 'Inspector', 'created_at' => date('c')]);
        tp_insert('person_documents', ['person_kind' => 'INSPECTOR', 'person_id' => $ag2, 'doc_kind' => 'NATIONAL_ID', 'number_last4' => '0000',
            'issuing_authority' => 'Example authority', 'issuing_country' => 'India', 'issued_on' => tp_d(-3700), 'expires_on' => tp_d(-30),
            'purpose' => function_exists('iddoc_purpose') ? iddoc_purpose() : 'Site access', 'consent_on' => tp_d(-400),
            'note' => 'Test pack (TP) — example document, expired', 'uploaded_by' => TP_BY, 'uploaded_at' => date('c')]);
    }

    // ---- Work orders (calls) and jobs -------------------------------------------
    $call = fn($code, $client, array $f = []) => tp_call($code, array_merge(['client_id' => $client, 'office_id' => $ahm], $f));

    //  OP-03 / OP-16 / OR-04 — a closed job with an ISSUED report (and a flagged
    //  photo in it), queued for billing the moment it closed.
    //  OP-03 has its own example customer, so other groups' TB Valves reports never push this one
    //  out of "Previous inspections" (that list shows only the most recent).
    $repeatCo = tp_client('TP Repeat Customer Pvt Ltd (example)', ['home_branch_id' => $ahm]);
    $c703 = $call('TP-C-B703', $repeatCo, ['date' => tp_d(-4), 'call_received_date' => tp_d(-6), 'billable_value' => 25000]);
    $j703 = tp_job('TP-J-B703', $c703, ['inspector_id' => $I ?: null, 'date' => tp_d(-4), 'invoice_value' => 25000]);
    $closedNow = (int) ops_val("SELECT COALESCE(closed_flag,0) FROM jobs WHERE id=?", [$j703]) === 0;
    if ($closedNow) {
        tp_b_close($j703, 3, 'APPROVED');
        $doc = tp_insert('report_docs', ['irn' => 'TP-IR-B703', 'type_code' => 'IR', 'title' => 'Inspection report (test pack example)',
            'client_id' => $tbValves, 'vendor_id' => $tbValves, 'call_id' => $c703, 'job_id' => $j703, 'office_id' => $ahm, 'sbu' => 'IND',
            'inspector_id' => $I ?: null, 'inspection_date' => tp_d(-4), 'issue_date' => tp_d(-3), 'result' => 'ACCEPTED',
            'status' => 'ISSUED', 'finalized' => 1, 'finalized_at' => date('c', strtotime('-3 days')), 'finalized_by' => TP_BY,
            'approved_at' => date('c', strtotime('-3 days')), 'approved_by' => TP_BY, 'deleted' => 0, 'remarks' => 'Test pack (TP)',
            'created_by' => TP_BY, 'created_at' => date('c', strtotime('-4 days')), 'updated_at' => date('c')]);
        if ($doc) tp_insert('report_files', ['report_doc_id' => $doc, 'field_key' => defined('EVIDENCE_SUPPORTING_KEY') ? EVIDENCE_SUPPORTING_KEY : '_supporting',
            'kind' => 'photo', 'file_name' => 'tp-site-photo-example.png', 'mime' => 'image/png',
            'data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            'caption' => 'Name plate (example)', 'taken_at' => date('c', strtotime('-4 days')), 'geo_source' => 'EXIF',
            'flags' => 'FAR_FROM_SITE', 'flag_note' => 'Photo taken about 38 km from the site (example)',
            'created_by' => TP_BY, 'created_at' => date('c', strtotime('-4 days'))]);
        if (function_exists('billable_on_job_closed') && ($ev = (int) billable_on_job_closed($j703))) tp_track('billable_events', $ev);
    }
    //  OP-03 — the repeat inspection at the same customer / vendor / contract, where
    //  the coordinator forwards that report to the engineer.
    $c704 = $call('TP-C-B704', $repeatCo, ['date' => tp_d(2)]);
    tp_job('TP-J-B704', $c704, ['inspector_id' => $I ?: null, 'date' => tp_d(2)]);

    //  OP-04 — two closed jobs whose reports wait for the engineer's reporting manager.
    foreach (['TP-C-B705' => 'TP-J-B705', 'TP-C-B706' => 'TP-J-B706'] as $cc => $jc) {
        $c = $call($cc, $tbPharma, ['date' => tp_d(-3), 'call_received_date' => tp_d(-5)]);
        $jid = tp_job($jc, $c, ['inspector_id' => $re ?: null, 'date' => tp_d(-3)]);
        if ((int) ops_val("SELECT COALESCE(closed_flag,0) FROM jobs WHERE id=?", [$jid]) === 0) tp_b_close($jid, 2, 'PENDING');
    }

    //  OV-05 / OV-07 / OV-12 — Test Inspector's DRAFT claim; OV-08 / OP-17 — Test
    //  Inspector2's APPROVED claim; OV-06 — the Business Director's own SUBMITTED
    //  claim. Each carries one line on a closed pack job of last month. Last month
    //  on purpose: an expense the tester adds today then goes to a claim of the
    //  tester's own, never into a pack claim that Remove deletes.
    $c707 = $call('TP-C-B707', $tbValves, ['date' => $lm12, 'call_received_date' => $lm10]);
    $j707 = tp_job('TP-J-B707', $c707, ['inspector_id' => $I ?: null, 'date' => $lm12]);
    $c708 = $call('TP-C-B708', $tbPharma, ['date' => $lm14, 'call_received_date' => $lm12]);
    $j708 = tp_job('TP-J-B708', $c708, ['inspector_id' => $I2 ?: null, 'date' => $lm14]);
    $j710 = 0;
    if ($dirIns) {
        $c710 = $call('TP-C-B710', $tbValves, ['date' => $lm12, 'call_received_date' => $lm10]);
        $j710 = tp_job('TP-J-B710', $c710, ['inspector_id' => $dirIns, 'date' => $lm12]);
    }
    $vDraft = $vAppr = $vDir = 0;
    if (!tp_tracked('vouchers')) {
        foreach (array_filter([$j707, $j708, $j710]) as $jid) tp_b_close($jid, (int) round((time() - strtotime($lm14)) / 86400), 'APPROVED');
        $vDraft = tp_b_voucher($I, $lm12, $j707, 'DRAFT');
        $vAppr = tp_b_voucher($I2, $lm14, $j708, 'APPROVED', ['submitted_at' => date('c', strtotime($lm14 . ' +2 days')), 'submitted_by' => 'SELF',
            'appr_round' => 1, 'approved_by' => TP_BY, 'approved_at' => date('c', strtotime($lm14 . ' +4 days'))]);
        if ($dirIns) $vDir = tp_b_voucher($dirIns, $lm12, $j710, 'SUBMITTED', ['submitted_at' => date('c', strtotime($lm12 . ' +2 days')),
            'submitted_by' => 'SELF', 'appr_round' => 1]);
    } else {
        $ids = tp_tracked('vouchers'); $vDraft = $ids[0] ?? 0; $vAppr = $ids[1] ?? 0; $vDir = $ids[2] ?? 0;
    }

    //  OP-09 — a work order to allocate the agency engineer to.
    $call('TP-C-B709', $tbValves, ['date' => tp_d(3), 'notes' => 'Test pack (TP) — allocate TP Agency Engineer Two here (OP-09)']);
    //  OP-12 — a work order for next Monday, for the leave test.
    $call('TP-C-B712', $tbValves, ['date' => $monday, 'notes' => 'Test pack (TP) — allocate Test Inspector here for next Monday (OP-12)']);

    //  OP-13 — a recurring schedule, due today, on the pack's own customer.
    if (!tp_tracked('recurring_services') && function_exists('tosrm_recurring_create')) {
        $rid = tosrm_recurring_create(['client_id' => $svc, 'title' => 'TP Monthly calibration visit (example)', 'frequency' => 'MONTHLY',
            'interval_n' => 1, 'start_date' => tp_d(0), 'template' => ['inspection_type' => 'Third-party inspection', 'sbu' => 'IND',
            'deliverables' => 'IR', 'executing_office_id' => $ahm, 'vendor_id' => $svc, 'notes' => 'Test pack (TP) — generated by the schedule']]);
        if ($rid) tp_track('recurring_services', $rid);
    }
    //  OP-14 — this customer's own SLA target: allocation (Scheduling) within 2 days,
    //  and a work order received 5 days ago that nobody has allocated.
    if (!ops_val("SELECT id FROM sla_targets WHERE client_id=? AND stage='SCHEDULING'", [$svc]))
        tp_insert('sla_targets', ['client_id' => $svc, 'stage' => 'SCHEDULING', 'target_days' => 2, 'note' => 'Test pack (TP)',
            'updated_by' => TP_BY, 'updated_at' => date('c')]);
    $call('TP-C-B714', $svc, ['date' => tp_d(1), 'call_received_date' => tp_d(-5)]);

    //  OP-19 — a job with an open hold point (its own job, so the Part 1 close
    //  tests on TP-J-0201 are never held up by it).
    $c719 = $call('TP-C-B719', $tbPharma, ['date' => tp_d(1)]);
    $j719 = tp_job('TP-J-B719', $c719, ['inspector_id' => $I ?: null, 'date' => tp_d(1)]);
    if (!ops_val("SELECT id FROM hw_points WHERE job_id=?", [$j719]))
        tp_insert('hw_points', ['job_id' => $j719, 'call_id' => $c719, 'office_id' => $ahm, 'point_type' => 'HOLD', 'qap_clause' => '4.2',
            'activity' => 'Hydro test witness (example)', 'description' => 'Test pack (TP) — hold point to be witnessed before dispatch',
            'source' => 'MANUAL', 'status' => 'OPEN', 'dedupe_key' => 'tp-b-hold-' . $j719, 'raised_by' => TP_BY,
            'raised_at' => date('c'), 'created_at' => date('c'), 'updated_at' => date('c')]);

    // ---- Map: test id → what to open ------------------------------------------
    $rec  = fn($t, ...$codes) => ['kind' => 'record', 'text' => $t, 'codes' => $codes];
    $none = fn($t) => ['kind' => 'none', 'text' => $t];
    $type = fn($t) => ['kind' => 'type', 'text' => $t];
    $vurl = fn($id) => $id ? '/voucher?id=' . (int) $id : '(not made — see the note)';
    $seen = 'No record needed — the pack\'s calls, jobs, claims and reports give the screen something to show.';
    $open = 'An access check: open the page as each person listed. No record needed — the pack\'s records give it rows.';
    $m = [
        // 6 Ops home & My Work
        'OH0-01' => $none('Reads the Operations home cards. ' . $seen),
        'OH0-02' => $none('Counts the reassign / no-show / cancel YOU do in OC-03, OC-05 and OC-06 on job TP-J-0301 — do those first. No record is pre-made, so the count is yours.'),
        'OH0-03' => $none('Reads the home screen. No record needed.'),
        'OH0-04' => $none('Compares the home screen for five people. ' . $seen),
        'OH0-05' => $none('Reads the home screen. No record needed.'),
        'OH0-06' => $none('My Work after Part 1 — uses the Part 1 jobs (TP-J-0201 …). No extra record needed.'),
        'OH0-07' => $none('Reads My Work. No record needed.'),
        'OK-16' => $none('Opens "Raise inspection call". No record needed.'),
        'OK-17' => $none('Opens the call and job registers. ' . $seen),
        'OK-18' => $none('Opens /calls and /jobs as the inspector. ' . $seen),
        // 7 Scheduling & resources
        'OF-01' => $none($open),
        'OF-02' => $none($open),
        'OF-03' => $none($open),
        'OF-04' => $none($open),
        'OF-05' => $none('Test Inspector\'s profile on this copy is /inspector-profile?id=' . ($I ?: '?') . ' (the book\'s "id=18" is only an example).'
            . ($I ? '' : ' The test.inspector login is missing here.')),
        'OF-06' => $none($open),
        'OF-07' => $none($open),
        'OF-08' => $none($open),
        'OF-09' => $none($open),
        'OF-10' => $none($open),
        'OF-11' => $none($open),
        'OF-12' => $none($open),
        'OP-05' => $none('None of the pack\'s engineers has an authorisation. The first step expects NOBODY on this copy to have one — if an earlier test granted one, withdraw it first.'),
        'OP-06' => $none('TP Report Engineer (TP-RE-01) holds "Level II MT (example)", uploaded, not yet verified — Competence, or his 360 view → Certificates → Verification.'),
        'OP-07' => $type('Type the gauge the steps give; use any small PDF of your own as the calibration certificate.'),
        'OP-08' => $none('Use the agency engineer "TP Agency Engineer" (TP-AG-01) → Identity documents.'),
        'OP-09' => $rec('"TP Agency Engineer Two" (TP-AG-02, agency) has one ID — a National ID that expired 30 days ago. Allocate him on work order TP-C-B709.', 'TP-C-B709'),
        'OP-10' => $type('Change any rating weight as the steps say.'),
        'OP-11' => $type('Type the working norm the steps give (8 h/day, 6 days).'),
        'OP-12' => $rec('Mark Test Inspector on leave for next Monday (' . $monday . '), then allocate him on work order TP-C-B712 (required that Monday).', 'TP-C-B712'),
        // 8 Controls & contracts
        'OG-01' => $type('TB Refinery Ltd is ready (Active, no hold). Type the hold and reason as the steps say.'),
        'OG-02' => $none($open),
        'OG-03' => $type('Press Create with every box empty, as the steps say. No record needed.'),
        'OG-04' => $type('Admin types the 7-day target as the steps say.'),
        'OG-05' => $rec('Work order TP-C-0104 (its job TP-J-0301, the OB-05 job) → Timing & SLA → Record a delay.', 'TP-C-0104', 'TP-J-0301'),
        'OG-06' => $rec('Same work order TP-C-0104 → Record a delay with both lists left on "—".', 'TP-C-0104'),
        'OG-07' => $none($open),
        'OG-08' => $none($open . ' Contract TB-CT-PEND (opening PENDING) is listed.'),
        'OG-09' => $none($open),
        'OG-10' => $none($open),
        'OG-11' => $none($open),
        'OG-12' => $none($open),
        'OK-20' => $type('TB Group Child is ready (Active, no hold). Type the reason with the HTML exactly as the steps give it.'),
        'OP-13' => $rec('Recurring → "TP Monthly calibration visit (example)" for TP Service Schedules Pvt Ltd (example), due today. Work order TP-C-B714 is the same customer\'s.', 'TP-C-B714'),
        'OP-14' => $rec('Work order TP-C-B714 — received 5 days ago, not allocated; its customer (TP Service Schedules Pvt Ltd) has its own Scheduling target of 2 days.', 'TP-C-B714'),
        'OP-15' => $none('NOT MADE BY THE PACK — the contract gate reads a won Sales quotation whose contract has expired or is used up, and the test pack does not make Sales records. Use a quotation from the Sales test book.'),
        // 9 Vouchers, attendance, time
        'OV-01' => $none('Opens the inspector\'s Vouchers. No record needed.'),
        'OV-02' => $type('Type the expense as the steps say (no type, ₹500).'),
        'OV-03' => $type('Type "Auto / local conveyance" ₹0 as the steps say.'),
        'OV-04' => $type('Post the expense naming another person, as the steps say.'),
        'OV-05' => $rec('Test Inspector\'s claim is ' . $vurl($vDraft) . ' (DRAFT, last month — one line on job TP-J-B707). Open that address as test.inspector2.', 'TP-J-B707'),
        'OV-06' => $dirIns
            ? $rec('The Business Director\'s own claim is ' . $vurl($vDir) . ' (SUBMITTED, last month, line on job TP-J-B710). Open it as test.director and press Approve.', 'TP-J-B710')
            : $none('NOT MADE — the test.director login is not linked to a staff record on this copy, and the pack never changes a login. Link it in Users, then Remove and Load again.'),
        'OV-07' => $rec('Admin opens ' . $vurl($vDraft) . ' (Test Inspector\'s DRAFT claim) and presses Approve.', 'TP-J-B707'),
        'OV-08' => $rec('Test Inspector2\'s claim ' . $vurl($vAppr) . ' is APPROVED (last month, line on job TP-J-B708) — mark it paid.', 'TP-J-B708'),
        'OV-09' => $none($open),
        'OV-10' => $none($open),
        'OV-11' => $none($open),
        'OV-12' => $rec('As test.inspector open own claim ' . $vurl($vDraft) . ' → Print, then CSV.', 'TP-J-B707'),
        'OV-13' => $none('NOT MADE — needs the test.coord login linked to a staff record (Users → staff record). The pack never changes a login; do that link first if the copy lacks it.'),
        'OV-14' => $none($open),
        'OV-15' => $none($open),
        'OV-16' => $none($open),
        // 10 Report handover
        'OR-01' => $none($open),
        'OR-02' => $none($open . ' Report TP-IR-B703 (issued) is listed.'),
        'OR-03' => $none($open . ' A flagged photo is waiting on report TP-IR-B703.'),
        'OR-04' => $rec('Evidence review → the photo on report TP-IR-B703 (job TP-J-B703) is flagged "far from site". Mark it reviewed with the note left empty.', 'TP-J-B703'),
        'OR-05' => $none('Posts a settings change as the coordinator. No record needed.'),
        'OP-03' => $rec('Job TP-J-B703 is closed with ISSUED report TP-IR-B703. Open the repeat job TP-J-B704 (same customer) → Reports & QA → Previous inspections → Forward.', 'TP-J-B704', 'TP-J-B703'),
        'OP-04' => $rec('Jobs TP-J-B705 and TP-J-B706 (TP Report Engineer, closed) wait for report approval'
            . ($opsMgr ? ' by his reporting manager, test.opsmgr.' : ' — test.opsmgr is missing on this copy, so approve as a manager instead.') . ' Approve one; send the other back.', 'TP-J-B705', 'TP-J-B706'),
        // 11 Commercial handover
        'OM-01' => $none($open . ' The closed job TP-J-B703 is listed as billable.'),
        'OM-02' => $none($open),
        'OM-03' => $none($open),
        'OM-04' => $none($open),
        'OM-05' => $none($open),
        'OM-06' => $none($open),
        'OM-07' => $none($open),
        'OM-08' => $none($open),
        'OP-16' => $rec('Billable events → job TP-J-B703 (closed, ₹25,000) → raise its invoice. The invoice you raise is kept after Remove (money documents are never deleted).', 'TP-J-B703'),
        'OP-17' => $rec('Profitability → Cost run for last month (' . date('M Y', $lm) . '): claim ' . $vurl($vAppr) . ' is APPROVED with a line on job TP-J-B708.', 'TP-J-B708'),
        // 12 Quality & insights
        'OQ-01' => $none($open),
        'OQ-02' => $none($open . ' The open hold point on job TP-J-B719 is listed.'),
        'OQ-03' => $none($open),
        'OQ-04' => $none($open),
        'OQ-05' => $none($open),
        'OK-19' => $none('Opens the Insights pages. ' . $seen),
        'OP-18' => $rec('Job TP-J-0201 (Part 1) → raise an NCR. The NCR is kept after Remove (quality records are never deleted).', 'TP-J-0201'),
        'OP-19' => $rec('Job TP-J-B719 has an OPEN hold point "Hydro test witness (example)" — record it witnessed.', 'TP-J-B719'),
        'OP-20' => $rec('Job TP-J-0201 (Part 1) → Complaints → Record. The complaint is kept after Remove.', 'TP-J-0201'),
    ];
    return $m;
}
