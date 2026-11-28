<?php
// ============================================================================
//  TEST PACK — group OPS-C: Operations test book sheets "13 Statuses &
//  transitions", "14 Branch scope & security", "15 Dates, limits, settings",
//  "16 Needs a phone or person", "17 Owner answers 22 Nov", "18 TPIA reporting
//  (25 Nov)", "19 Order chain (25 Nov)", "20 Close no report (27 Nov)" and
//  "21 Release Note rule (28 Nov)".
//  Loaded by lib/seed_testpack.php (tp_seeders() finds this file).
//
//  Same rules as OPS-A: records are made with the columns the app's own screens
//  write, codes carry the TP- tag, and the app's own functions finish them —
//  job_visits_sync() (via tp_job) for the visit days, job_visit_close() and
//  job_visit_no_inspection() for closed days, pochain_save() for the order chain,
//  billunit_end_early() for the terminated job, exp_claim_for() for the vouchers.
//  No logins, no passwords, no e-mail addresses are made. Names and numbers
//  marked "(example)" are made-up test values.
//
//  Sheet 20 reuses the ids OX-01…OX-04 that sheet 14 also uses, so its map keys
//  are "20/OX-01" … "20/OX-04".
//
//  Removal (lib/seed_testpack.php tp_remove): everything below is in the ledger
//  or hangs off a pack call / job, so it goes. The TP1 keep-rule applies: money
//  documents a TESTER creates (invoices, vouchers, Tally exports), quality records
//  and the audit trail are kept; the pack's own vouchers and reports are removed.
// ============================================================================

tp_register('OPS-C', 'Operations · Statuses, Branch scope, Dates & settings, Phone, 22 Nov, TPIA, Order chain, Close no report, Release Note rule', 'tp_seed_ops_c');

// ---- Helpers (OPS-C only) -----------------------------------------------------

/** "28 Nov 2026" — the dates the map text names. */
function tp_c_fd($d) { return $d ? date('j M Y', strtotime($d)) : ''; }

/** $n working days of $office from $date onwards (holidays and weekly offs skipped). */
function tp_c_workdays_from($date, $n, $office) {
    $out = []; $d = $date; $guard = 0;
    while (count($out) < $n && $guard++ < 120) {
        if (!function_exists('is_working_day') || is_working_day($d, $office ?: null)) $out[] = $d;
        $d = date('Y-m-d', strtotime($d . ' +1 day'));
    }
    return $out;
}
/** The last $n working days before today, oldest first. */
function tp_c_past($n, $office) {
    $out = []; $d = tp_d(-1); $guard = 0;
    while (count($out) < $n && $guard++ < 120) {
        if (!function_exists('is_working_day') || is_working_day($d, $office ?: null)) array_unshift($out, $d);
        $d = date('Y-m-d', strtotime($d . ' -1 day'));
    }
    return $out;
}

/** A multi-day job's fields: the client named the visit days (engagement MULTIPLE). */
function tp_c_multi(array $dates, array $f = []) {
    return array_merge(['engagement_type' => 'MULTIPLE', 'inspection_dates' => implode(',', $dates), 'date' => $dates[0],
        'scheduled_date' => $dates[0], 'inspection_start_date' => $dates[0], 'inspection_end_date' => end($dates),
        'mandays' => count($dates)], $f);
}

/**
 * An inspection report on a pack job, with the columns the report screens write.
 * $f: status (DRAFT / SUBMITTED / APPROVED / ISSUED), result, release_status,
 * rn_to_issue, inspection_date, inspector_id. Reused when the number exists.
 */
function tp_c_report($irn, $jobId, $type, array $f = []) {
    if ($ex = (int) (ops_val("SELECT id FROM report_docs WHERE irn=?", [$irn]) ?: 0)) return $ex;
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [(int) $jobId]) ?: [];
    $call = ops_one("SELECT * FROM calls WHERE id=?", [(int) ($job['call_id'] ?? 0)]) ?: [];
    $typeId = (int) (ops_val("SELECT id FROM report_types WHERE code=?", [$type]) ?: 0);
    $status = $f['status'] ?? 'ISSUED';
    $date = $f['inspection_date'] ?? substr((string) ($job['scheduled_date'] ?? tp_d(0)), 0, 10);
    $now = date('c');
    $row = [
        'irn' => $irn, 'report_type_id' => $typeId ?: null, 'type_code' => $type,
        'title' => 'Test pack (TP) — ' . $type . ' for ' . ($job['job_code'] ?? ''),
        'client_id' => $call['client_id'] ?? null, 'vendor_id' => $call['vendor_id'] ?? null,
        'call_id' => $job['call_id'] ?? null, 'job_id' => (int) $jobId, 'office_id' => $job['executing_office_id'] ?? null,
        'sbu' => $job['sbu'] ?? 'IND', 'po_ref' => (string) ($call['supply_po_ref'] ?? ''),
        'inspector_id' => $f['inspector_id'] ?? ($job['inspector_id'] ?? null),
        'inspection_date' => $date, 'issue_date' => $status === 'ISSUED' ? $date : '',
        'result' => $f['result'] ?? '', 'release_status' => $f['release_status'] ?? '', 'status' => $status,
        'data' => '{}', 'remarks' => 'Test pack (TP) — example report', 'rev' => 0, 'deleted' => 0,
        'finalized' => $status === 'ISSUED' ? 1 : 0, 'finalized_at' => $status === 'ISSUED' ? $now : null,
        'finalized_by' => $status === 'ISSUED' ? TP_BY : null,
        'submitted_at' => in_array($status, ['SUBMITTED', 'APPROVED', 'ISSUED'], true) ? $now : null,
        'approved_at' => in_array($status, ['APPROVED', 'ISSUED'], true) ? $now : null,
        'approved_by' => in_array($status, ['APPROVED', 'ISSUED'], true) ? TP_BY : null,
        'rn_to_issue' => (int) ($f['rn_to_issue'] ?? 0), 'created_by' => TP_BY, 'created_at' => $now, 'updated_at' => $now,
    ];
    return tp_insert('report_docs', $row);
}

/** Arrival and departure check-ins on a day, so a check-in setting never blocks a close test. */
function tp_c_checkins($jobId, $ins, $date, array $kinds = ['ENTRY', 'EXIT'], $photo = false) {
    $at = ['ENTRY' => '10:00:00', 'EXIT' => '17:00:00'];
    //  A 1×1 grey PNG — a stand-in "photo" (example), so the photo link has something to show.
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mN8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
    foreach ($kinds as $k) {
        if (ops_val("SELECT id FROM site_visits WHERE job_id=? AND kind=?", [(int) $jobId, $k])) continue;
        $t = date('c', strtotime($date . ' ' . $at[$k]));
        tp_insert('site_visits', ['job_id' => (int) $jobId, 'inspector_id' => $ins ?: null, 'kind' => $k, 'lat' => 23.0225, 'lon' => 72.5714,
            'accuracy' => 12, 'device_at' => $t, 'at' => $t, 'clock_skew' => 0, 'flags' => '', 'note' => 'Test pack (TP) — example check-in',
            'photo_name' => $photo ? 'tp-checkin.png' : '', 'photo_mime' => $photo ? 'image/png' : '',
            'photo_data' => $photo ? $png : '', 'photo_sha1' => $photo ? sha1(base64_decode($png)) : '',
            'by_user' => TP_BY, 'ip' => '', 'created_at' => $t]);
    }
}

/** Close a visit day with its report, through the app's own job_visit_close(). */
function tp_c_close_day($jobId, $date, $reportId) {
    if (!function_exists('job_visit_close')) return;
    job_visit_close((int) $jobId, $date, '', (int) $reportId);   // '' = closed; an already closed day is left alone
}

/**
 * A person's voucher (claim) for the period holding $date. When the copy already
 * has one for that person and period it is reused and NOT tracked (never removed);
 * else one is made by the app's exp_claim_for(), tracked, and put in $status.
 * Returns [id, status, reused?].
 */
function tp_c_voucher($ins, $date, $status, $total) {
    if (!$ins || !function_exists('exp_claim_for')) return [0, '', false];
    $v = exp_claim_for((int) $ins, $date, false);
    if ($v) return [(int) $v['id'], (string) $v['status'], !in_array((int) $v['id'], tp_tracked('vouchers'), true)];
    $v = exp_claim_for((int) $ins, $date, true);
    if (!$v) return [0, '', false];
    $id = (int) $v['id'];
    tp_track('vouchers', $id);
    $now = date('c');
    $set = ['status' => $status, 'total' => $total, 'created_by' => TP_BY];
    if (in_array($status, ['SUBMITTED', 'APPROVED', 'PAID'], true)) $set += ['submitted_at' => $now, 'submitted_by' => TP_BY];
    if (in_array($status, ['APPROVED', 'PAID'], true)) $set += ['approved_at' => $now, 'approved_by' => TP_BY];
    $set = array_intersect_key($set, table_columns('vouchers'));
    db()->prepare("UPDATE vouchers SET " . implode(',', array_map(fn($k) => "$k=?", array_keys($set))) . " WHERE id=?")
        ->execute(array_merge(array_values($set), [$id]));
    return [$id, $status, false];
}

// ---- The seeder ------------------------------------------------------------------

function tp_seed_ops_c(array $ctx) {
    $ahm = $ctx['ahm'];
    $I = $ctx['ins']['inspector']; $I2 = $ctx['ins']['inspector2']; $SR = $ctx['ins']['srinspector'];
    if (function_exists('pochain_migrate')) pochain_migrate();
    if (function_exists('job_noreport_migrate')) job_noreport_migrate();
    if (function_exists('billunit_migrate')) billunit_migrate();

    //  The book's customers (OPS-A makes or reuses them; reused here the same way).
    $tbValves = tp_client('TB Valves Pvt Ltd', ['home_branch_id' => $ahm]);
    $testline = tp_client('Testline Pumps Pvt Ltd', ['gstin' => '24AAKCT4821M1Z1', 'pan' => 'AAKCT4821M', 'state' => 'Gujarat', 'home_branch_id' => $ahm]);
    $kaveri = tp_client('Kaveri Valves Pvt Ltd', ['gstin' => '24AABCK2290L1ZI', 'pan' => 'AABCK2290L', 'state' => 'Gujarat', 'home_branch_id' => $ahm]);

    $call = fn($code, $client, array $f = []) => tp_call($code, array_merge(['client_id' => $client, 'office_id' => $ahm], $f));
    //  A finished one-day job on a past working day, with both check-ins.
    $past1 = tp_c_past(1, $ahm)[0];
    $doneJob = function ($callCode, $jobCode, array $cf = [], array $jf = []) use ($call, $tbValves, $I, $past1) {
        $d = $jf['date'] ?? $past1;
        $c = $call($callCode, $cf['client_id'] ?? $tbValves, array_merge(['date' => $d, 'call_received_date' => tp_d(-12)], $cf));
        $j = tp_job($jobCode, $c, array_merge(['inspector_id' => $I ?: null, 'date' => $d], $jf));
        tp_c_checkins($j, $I, $d);
        return [$c, $j, $d];
    };
    //  The same, with its inspection report and the visit day closed with it.
    $reportedJob = function ($callCode, $jobCode, $irn, array $rf, array $cf = [], array $jf = []) use ($doneJob) {
        [$c, $j, $d] = $doneJob($callCode, $jobCode, $cf, $jf);
        $r = tp_c_report($irn, $j, $rf['type'] ?? 'IR', $rf + ['inspection_date' => $d]);
        tp_c_close_day($j, $d, $r);
        return [$c, $j, $d, $r];
    };

    // ======== 13 Statuses & transitions ============================================
    //  OW-02 — Test Inspector's voucher for this month, DRAFT.
    [$vDraft, $vDraftSt, $vDraftReused] = tp_c_voucher($I, tp_d(0), 'DRAFT', 0);
    //  OW-03 / OW-05 — a SUBMITTED voucher (Test Inspector2, last month).
    $lastMonth = date('Y-m-05', strtotime('first day of last month'));
    [$vSub, $vSubSt, $vSubReused] = tp_c_voucher($I2, $lastMonth, 'SUBMITTED', 2500);
    //  OW-04 — a PAID voucher (Test SrInspector, two months ago).
    $twoMonths = date('Y-m-05', strtotime('first day of -2 months'));
    [$vPaid, $vPaidSt, $vPaidReused] = tp_c_voucher($SR, $twoMonths, 'PAID', 4200);
    //  OW-06 — a closed job whose report is still waiting for approval (Test Inspector's own).
    [, $j1306, $d1306] = $doneJob('TP-C-1306', 'TP-J-1306');
    tp_c_report('TP-IR-1306', $j1306, 'IR', ['status' => 'SUBMITTED', 'inspection_date' => $d1306]);
    db()->prepare("UPDATE jobs SET closed_flag=1, closed_at=?, report_upload_date=?, report_approval='PENDING' WHERE id=? AND COALESCE(closed_flag,0)=0")
        ->execute([date('c'), tp_d(0), $j1306]);
    //  OW-08 — arrival check-in only (no departure), report on file: the manager closes with a reason.
    $c1308 = $call('TP-C-1308', $tbValves, ['date' => $past1, 'call_received_date' => tp_d(-12)]);
    $j1308 = tp_job('TP-J-1308', $c1308, ['inspector_id' => $I ?: null, 'date' => $past1]);
    tp_c_checkins($j1308, $I, $past1, ['ENTRY']);
    $r1308 = tp_c_report('TP-IR-1308', $j1308, 'IR', ['status' => 'ISSUED', 'result' => 'ACCEPTED', 'release_status' => 'RELEASED', 'inspection_date' => $past1]);
    tp_c_close_day($j1308, $past1, $r1308);

    // ======== 14 Branch scope & security ===========================================
    //  OX-03 — an Ahmedabad job with a check-in that carries a photo.
    $c1403 = $call('TP-C-1403', $tbValves);
    $j1403 = tp_job('TP-J-1403', $c1403, ['inspector_id' => $I ?: null, 'date' => tp_d(0)]);
    tp_c_checkins($j1403, $I, tp_d(0), ['ENTRY'], true);
    $photoId = (int) (ops_val("SELECT id FROM site_visits WHERE job_id=? AND kind='ENTRY'", [$j1403]) ?: 0);

    // ======== 15 Dates, limits, settings ===========================================
    //  OY-03 — two calls for the same client and site, both waiting to be allocated.
    $call('TP-C-1503', $kaveri, ['date' => tp_d(2), 'notes' => 'Test pack (TP) — same client & site as TP-C-1504']);
    $call('TP-C-1504', $kaveri, ['date' => tp_d(2), 'notes' => 'Test pack (TP) — same client & site as TP-C-1503']);
    //  OY-06 — a call whose status is CLOSED (seed data; the app never closes a call itself).
    $call('TP-C-1506', $tbValves, ['date' => tp_d(-20), 'call_received_date' => tp_d(-22), 'status' => 'CLOSED']);

    // ======== 16 Needs a phone or person ===========================================
    //  OZ-01 / OZ-02 / OA22-04 — a job today for Test Inspector, nobody has punched on it.
    $c1601 = $call('TP-C-1601', $tbValves);
    tp_job('TP-J-1601', $c1601, ['inspector_id' => $I ?: null, 'date' => tp_d(0)]);
    //  OZ-03 — a call to allocate (the engineer's mailbox gets the notice).
    $call('TP-C-1603', $tbValves, ['date' => tp_d(1)]);
    //  OZ-04 — a call to forward to Mumbai; Testline Pumps has a complete master (GSTIN, PAN, state).
    $call('TP-C-1604', $testline, ['date' => tp_d(1)]);

    // ======== 17 Owner answers 22 Nov ==============================================
    //  OA22-08 step 3 — an OLD call already received after its required date.
    $call('TP-C-1708', $tbValves, ['date' => tp_d(-25), 'call_received_date' => tp_d(-20),
        'created_at' => date('c', strtotime('-20 days')), 'subject' => 'Test pack (TP) — old call (example)']);
    //  OA22-10 — a call that has never been edited.
    $call('TP-C-1710', $tbValves, ['date' => tp_d(3)]);

    // ======== 18 TPIA reporting (25 Nov) ===========================================
    //  TP-03 — two calls with the same client call no. (IC-TP-903); one with a job, a done day and its report.
    [$c1803, $j1803] = $reportedJob('TP-C-1803', 'TP-J-1803', 'TP-IR-1803', ['status' => 'ISSUED', 'result' => 'ACCEPTED', 'release_status' => 'RELEASED'],
        ['client_call_ref' => 'IC-TP-903', 'supply_po_ref' => 'PO-TP-903', 'offered_scope' => 'Lot 1 — 12 valves (example)', 'call_received_date' => tp_d(-30)]);
    $call('TP-C-1804', $tbValves, ['client_call_ref' => 'IC-TP-903', 'supply_po_ref' => 'PO-TP-903', 'offered_scope' => 'Lot 2 — 8 valves (example)', 'date' => tp_d(2)]);
    //  TP-04 … TP-06 — PO-TP-4587 Lot 1 and Lot 2 (same client and manufacturer), each with a job today.
    $c1805 = $call('TP-C-1805', $tbValves, ['supply_po_ref' => 'PO-TP-4587', 'offered_scope' => 'Lot 1 — 40 valves', 'client_call_ref' => 'IC-TP-4587-1']);
    tp_job('TP-J-1805', $c1805, ['inspector_id' => $I ?: null, 'date' => tp_d(0)]);
    $c1806 = $call('TP-C-1806', $tbValves, ['supply_po_ref' => 'PO-TP-4587', 'offered_scope' => 'Lot 2 — 25 valves', 'client_call_ref' => 'IC-TP-4587-2']);
    tp_job('TP-J-1806', $c1806, ['inspector_id' => $I ?: null, 'date' => tp_d(0)]);
    //  TP-07 / TP-08 — a 10-day man-day job, one report per inspection day; day 1 was 5 working days ago.
    $d1807 = tp_c_workdays_from(tp_c_past(5, $ahm)[0], 10, $ahm);
    $c1807 = $call('TP-C-1807', $tbValves, ['engagement_type' => 'MULTIPLE', 'date' => $d1807[0], 'inspection_dates' => implode(',', $d1807),
        'reporting_frequency' => 'PER_DAY', 'call_received_date' => tp_d(-14)]);
    $j1807 = tp_job('TP-J-1807', $c1807, tp_c_multi($d1807, ['inspector_id' => $I ?: null, 'reporting_frequency' => 'PER_DAY']));
    //  TP-09 — a 3-day job agreed as "No report", over the last three working days.
    $d1809 = tp_c_past(3, $ahm);
    $c1809 = $call('TP-C-1809', $tbValves, ['engagement_type' => 'MULTIPLE', 'date' => $d1809[0], 'inspection_dates' => implode(',', $d1809),
        'reporting_frequency' => 'NOREPORT', 'deliverables' => '', 'call_received_date' => tp_d(-10)]);
    $j1809 = tp_job('TP-J-1809', $c1809, tp_c_multi($d1809, ['inspector_id' => $I ?: null, 'reporting_frequency' => 'NOREPORT', 'deliverables' => '']));
    //  TP-10 — a 12-week posting, weekly reports, started on a Monday at least 9 days ago.
    $mon = date('Y-m-d', strtotime('monday this week', strtotime(tp_d(-9))));
    $c1810 = $call('TP-C-1810', $tbValves, ['date' => $mon, 'call_received_date' => date('Y-m-d', strtotime($mon . ' -3 days')), 'reporting_frequency' => 'WEEKLY']);
    tp_job('TP-J-1810', $c1810, ['inspector_id' => $SR ?: null, 'job_type' => 'DEPUTATION', 'dep_status' => 'ACTIVE', 'dep_site' => 'TB Valves works',
        'date' => $mon, 'inspection_end_date' => date('Y-m-d', strtotime($mon . ' +83 days')), 'mandays' => 84, 'reporting_frequency' => 'WEEKLY']);
    //  TP-11 / TP-12 — a 5-day job with its days still open (day 1 two working days ago).
    $d1811 = tp_c_workdays_from(tp_c_past(2, $ahm)[0], 5, $ahm);
    $c1811 = $call('TP-C-1811', $tbValves, ['engagement_type' => 'MULTIPLE', 'date' => $d1811[0], 'inspection_dates' => implode(',', $d1811), 'call_received_date' => tp_d(-8)]);
    tp_job('TP-J-1811', $c1811, tp_c_multi($d1811, ['inspector_id' => $I ?: null]));
    //  TP-13 / TP-14 — X (Test Inspector) has an APPROVED report; Y (Test Inspector2) has a job on the same call.
    [$c1813, $j1813] = $doneJob('TP-C-1813', 'TP-J-1813');
    $r1813 = tp_c_report('TP-IR-1813', $j1813, 'IR', ['status' => 'APPROVED', 'result' => 'ACCEPTED', 'inspection_date' => $past1]);
    tp_c_close_day($j1813, $past1, $r1813);
    tp_job('TP-J-1814', $c1813, ['inspector_id' => $I2 ?: null, 'date' => tp_d(0)]);
    //  TP-15 / TP-16 — PO-TP-4590 Lot 1 and Lot 2, each report issued with "Release Note to be issued" ticked.
    $rnOk = ['status' => 'ISSUED', 'result' => 'ACCEPTED', 'release_status' => 'RELEASED', 'rn_to_issue' => 1];
    $reportedJob('TP-C-1815', 'TP-J-1815', 'TP-IR-1815', $rnOk, ['supply_po_ref' => 'PO-TP-4590', 'offered_scope' => 'Lot 1 — 30 valves (example)', 'deliverables' => 'IR,RN']);
    $reportedJob('TP-C-1816', 'TP-J-1816', 'TP-IR-1816', $rnOk, ['supply_po_ref' => 'PO-TP-4590', 'offered_scope' => 'Lot 2 — 20 valves (example)', 'deliverables' => 'IR,RN']);
    //  TP-17 — two finished jobs: one asked only for an Inspection Report, one also for a Release Note.
    $released = ['status' => 'ISSUED', 'result' => 'ACCEPTED', 'release_status' => 'RELEASED'];
    $reportedJob('TP-C-1817', 'TP-J-1817', 'TP-IR-1817', $released, ['deliverables' => 'IR']);
    $reportedJob('TP-C-1818', 'TP-J-1818', 'TP-IR-1818', $released, ['deliverables' => 'IR,RN']);

    // ======== 19 Order chain (25 Nov) ==============================================
    //  PC-01 … PC-07, PC-09, PC-10, PC-12 — an open call with Client PO no. PO-LT-2002, and its job.
    $c1901 = $call('TP-C-1901', $tbValves, ['supply_po_ref' => 'PO-LT-2002', 'offered_scope' => 'Lot 1 — 10 pumps (example)', 'date' => tp_d(1)]);
    tp_job('TP-J-1901', $c1901, ['inspector_id' => $I ?: null, 'date' => tp_d(1)]);
    //  PC-05 — a second call of the same client whose order chain also has PO-IOC-1001.
    $c1905 = $call('TP-C-1905', $tbValves, ['supply_po_ref' => 'PO-LT-2005', 'date' => tp_d(2), 'call_received_date' => tp_d(-3)]);
    if (function_exists('pochain_save') && !ops_val("SELECT id FROM call_po_chain WHERE call_id=?", [$c1905]))
        pochain_save($c1905, [['placed_by' => 'IOCL', 'placed_on' => 'L&T', 'po_no' => 'PO-IOC-1001', 'role_code' => 'EPC']]);
    //  PC-08 — a call with NO order chain, and a draft report on its job.
    $c1908 = $call('TP-C-1908', $tbValves, ['supply_po_ref' => 'PO-TP-1908']);
    $j1908 = tp_job('TP-J-1908', $c1908, ['inspector_id' => $I ?: null, 'date' => tp_d(0)]);
    tp_c_report('TP-IR-1908', $j1908, 'IR', ['status' => 'DRAFT', 'inspection_date' => tp_d(0)]);

    // ======== 20 Close no report (27 Nov) ==========================================
    //  OX-01 / OX-02 — a finished job with no report and no report link.
    $doneJob('TP-C-2001', 'TP-J-2001');
    //  OX-03 — a job agreed as "No report", and a terminated job.
    $doneJob('TP-C-2003', 'TP-J-2003', ['reporting_frequency' => 'NOREPORT', 'deliverables' => ''], ['reporting_frequency' => 'NOREPORT', 'deliverables' => '']);
    [, $j2004, $d2004] = $doneJob('TP-C-2004', 'TP-J-2004');
    if (function_exists('billunit_end_early') && !ops_val("SELECT billing_stop_kind FROM jobs WHERE id=?", [$j2004]))
        billunit_end_early($j2004, $d2004, 'TERMINATED', 'Client terminated the order (test pack example)');

    // ======== 21 Release Note rule (28 Nov) ========================================
    $rnRep = fn($res, $rel) => ['status' => 'ISSUED', 'result' => $res, 'release_status' => $rel];
    //  RN-01 — Release Note ticked; report Accepted / Released; no Release Note yet.
    $reportedJob('TP-C-2101', 'TP-J-2101', 'TP-IR-2101', $rnRep('ACCEPTED', 'RELEASED') + ['rn_to_issue' => 1], ['deliverables' => 'IR,RN']);
    //  RN-02 — Release Note ticked; report Hold.
    $reportedJob('TP-C-2102', 'TP-J-2102', 'TP-IR-2102', $rnRep('HOLD', ''), ['deliverables' => 'IR,RN']);
    //  RN-03 — Release Note ticked; report Rejected / Not released.
    $reportedJob('TP-C-2103', 'TP-J-2103', 'TP-IR-2103', $rnRep('REJECTED', 'NOT_RELEASED'), ['deliverables' => 'IR,RN']);
    //  RN-04 — two-day job (Final Report + Release Note); day 1 done with its report, the last day "No inspection".
    $d2104 = tp_c_past(2, $ahm);
    $c2104 = $call('TP-C-2104', $tbValves, ['engagement_type' => 'MULTIPLE', 'date' => $d2104[0], 'inspection_dates' => implode(',', $d2104),
        'deliverables' => 'FINAL_REP,RN', 'call_received_date' => tp_d(-10)]);
    $j2104 = tp_job('TP-J-2104', $c2104, tp_c_multi($d2104, ['inspector_id' => $I ?: null]));
    tp_c_checkins($j2104, $I, $d2104[0]);
    $r2104 = tp_c_report('TP-IR-2104', $j2104, 'IR', ['status' => 'ISSUED', 'inspection_date' => $d2104[0]]);
    tp_c_close_day($j2104, $d2104[0], $r2104);
    if (function_exists('job_visit_no_inspection'))
        job_visit_no_inspection($j2104, $d2104[1], 'MATERIAL_NOT_READY', 'Castings still at the foundry');
    //  RN-05 — Release Note ticked; report issued with no Overall result.
    $reportedJob('TP-C-2105', 'TP-J-2105', 'TP-IR-2105', $rnRep('', ''), ['deliverables' => 'IR,RN']);
    //  RN-06 — Final Report + Release Note ticked; report Hold; no Final Inspection Report written.
    $reportedJob('TP-C-2106', 'TP-J-2106', 'TP-IR-2106', $rnRep('HOLD', ''), ['deliverables' => 'FINAL_REP,RN']);
    //  RN-07 / RN-08 — another Hold job (for the renamed reason, and on the phone).
    $reportedJob('TP-C-2108', 'TP-J-2108', 'TP-IR-2108', $rnRep('HOLD', ''), ['deliverables' => 'IR,RN']);

    // ---- Map: test id → what to open ------------------------------------------
    $rec  = fn($t, ...$codes) => ['kind' => 'record', 'text' => $t, 'codes' => $codes];
    $none = fn($t) => ['kind' => 'none', 'text' => $t];
    $type = fn($t) => ['kind' => 'type', 'text' => $t];
    $who = $I ? '' : ' (login test.inspector is missing on this copy — the job has no engineer)';
    $vText = function ($id, $st, $reused, $whoName, $period, $want) {
        if (!$id) return 'The ' . $whoName . ' login is missing on this copy, so no voucher could be made.';
        return $reused ? 'Your existing ' . $whoName . ' voucher for ' . $period . ' is used (status ' . $st . '; the pack did not make another and will not remove it)'
                         . ($st !== $want ? ' — it needs to be ' . $want . ' for this test.' : '.')
                       : 'Ready: ' . $whoName . '\'s voucher for ' . $period . ', ' . $st . ' (made by the pack, removed with it).';
    };
    $mName = fn($d) => date('F Y', strtotime($d));
    $vc = fn($id) => $id ? ['TP-V-' . $id] : [];
    $vRec = fn($t, $id) => $id ? ['kind' => 'record', 'text' => $t, 'codes' => $vc($id)] : ['kind' => 'none', 'text' => $t];
    $keep = ' Remove keeps any invoice or voucher you raise on a pack job (numbered money documents are never deleted).';
    $days = fn(array $ds) => implode(', ', array_map('tp_c_fd', $ds));
    $m = [
        // 13 Statuses & transitions
        'OW-01' => $rec('Work order TP-C-0101 (the Part 1 call, allocated). Change its status.', 'TP-C-0101'),
        'OW-02' => $vRec($vText($vDraft, $vDraftSt, $vDraftReused, 'Test Inspector', $mName(tp_d(0)), 'DRAFT') . ' Expenses → Vouchers.', $vDraft),
        'OW-03' => $vRec($vText($vSub, $vSubSt, $vSubReused, 'Test Inspector2', $mName($lastMonth), 'SUBMITTED') . ' Send it back with an empty reason.', $vSub),
        'OW-04' => $vRec($vText($vPaid, $vPaidSt, $vPaidReused, 'Test SrInspector', $mName($twoMonths), 'PAID') . ' Press Reopen as finance, then as admin.', $vPaid),
        'OW-05' => $vRec($vText($vSub, $vSubSt, $vSubReused, 'Test Inspector2', $mName($lastMonth), 'SUBMITTED') . ' Press Reopen as test.opsmgr. (Do this before OW-03 sends it back.)', $vSub),
        'OW-06' => $rec('Job TP-J-1306 is closed; its report TP-IR-1306 (Test Inspector\'s own) waits for approval' . $who . '.', 'TP-J-1306'),
        'OW-07' => $rec('Job TP-J-0301 (open). Press Raise invoice as test.finance.' . $keep, 'TP-J-0301'),
        'OW-08' => $rec('Job TP-J-1308 has an arrival check-in but no departure, and its report on file. Close it as test.opsmgr with the reason.', 'TP-J-1308'),
        // 14 Branch scope & security
        'OX-01' => $rec('As test.coord2 open job TP-J-0201 (Ahmedabad) by its address.', 'TP-J-0201'),
        'OX-02' => $rec('As test.coord2 open call TP-C-0101 (Ahmedabad) by its address.', 'TP-C-0101'),
        'OX-03' => $rec('Job TP-J-1403 has a check-in with a photo (a grey example image)' . ($photoId ? ' — link /checkin-photo?id=' . $photoId : '') . '. Open it as test.coord, then as test.coord2.', 'TP-J-1403'),
        'OX-04' => $rec('As test.inspector post a credit agreement on call TP-C-0101.', 'TP-C-0101'),
        'OX-05' => $none('Downloads the call register. The pack\'s calls give it rows. No record needed.'),
        'OX-06' => $none('Needs an expired page or a wrong form token on any job. No record needed.'),
        // 15 Dates, limits, settings
        'OY-01' => $type('Type the call for TB Valves Pvt Ltd: Continuous days, 3 days, starting next Saturday.'),
        'OY-02' => $type('Type the call with "Inspection call received" next month.'),
        'OY-03' => $rec('Calls TP-C-1503 and TP-C-1504 (Kaveri Valves, same client and site) both wait to be allocated. Open Allocate on one.', 'TP-C-1503', 'TP-C-1504'),
        'OY-04' => $type('Type the geofence radius 5, then 99999.'),
        'OY-05' => $type('Leave the names empty as the steps say.'),
        'OY-06' => $rec('Call TP-C-1506 is CLOSED (seed data). Open /job-new?call=<id of TP-C-1506>.', 'TP-C-1506'),
        // 16 Needs a phone or person
        'OZ-01' => $rec('On a phone as test.inspector: job TP-J-1601 (today, no punch yet)' . $who . '.', 'TP-J-1601'),
        'OZ-02' => $rec('On a phone as test.inspector, clock 30 minutes wrong: job TP-J-1601.', 'TP-J-1601'),
        'OZ-03' => $rec('Allocate call TP-C-1603 to Test Inspector, then check that mailbox.', 'TP-C-1603'),
        'OZ-04' => $rec('Forward call TP-C-1604 (Testline Pumps — complete master) to Mumbai, then check the Mumbai coordinator\'s mailbox.', 'TP-C-1604'),
        'OZ-05' => $rec('Close job TP-J-1817 (finished, report on file). NOTE: the pack adds no e-mail addresses — give TB Valves a contact with your redirect inbox first.', 'TP-J-1817'),
        'OZ-06' => $vRec($vText($vDraft, $vDraftSt, $vDraftReused, 'Test Inspector', $mName(tp_d(0)), 'DRAFT') . ' Print it on A4.', $vDraft),
        'OZ-07' => $type('Create the monthly schedule for TB Valves as the steps say.'),
        'OZ-08' => $type('Contract TB-CT-PEND (TB Valves, opening PENDING) is ready from the pack. Endorse and approve it, then type a call against it.'),
        'OZ-09' => $type('Type the authorisation with an expiry next week, then allocate a job dated after it.'),
        'OZ-10' => $type('Type the instrument with calibration due yesterday.'),
        // 17 Owner answers 22 Nov
        'OA22-01' => $none('Reads the top bar for each person. No record needed.'),
        'OA22-02' => $none('The pack\'s calls and jobs give the cards and lists numbers. No record needed.'),
        'OA22-03' => $type('Pick the bottom-bar screens as the steps say.'),
        'OA22-04' => $rec('On a phone as test.inspector: job TP-J-1601 is scheduled today' . $who . '.', 'TP-J-1601'),
        'OA22-05' => $none('Types an address. No record needed.'),
        'OA22-06' => $rec('See OD-11: job TP-J-0201 (Test Inspector).', 'TP-J-0201'),
        'OA22-07' => $none('See OG-06. No record of its own needed.'),
        'OA22-08' => $rec('Steps 1–2: type the new calls. Step 3: call TP-C-1708 is an OLD call received ' . tp_c_fd(tp_d(-20)) . ', required ' . tp_c_fd(tp_d(-25)) . ' — change only the subject.', 'TP-C-1708'),
        'OA22-09' => $type('Type the rates the steps give.'),
        'OA22-10' => $rec('Call TP-C-1710 has never been edited. Open Edit in two windows.', 'TP-C-1710'),
        'OA22-11' => $rec('See OB-01 and OB-04: calls TP-C-0101 and TP-C-0102.', 'TP-C-0101', 'TP-C-0102'),
        'OA22-12' => $rec('See OK-11: job TP-J-0201 → Money.' . $keep, 'TP-J-0201'),
        'OA22-13' => $none('Marks attendance on the dashboard. No record needed.'),
        // 18 TPIA reporting (25 Nov)
        'TP-01' => $type('Type IC-778, PO-4587 and "Lot 1 — 40 valves" on a new call.'),
        'TP-02' => $type('Type IC-778 again for the same client.'),
        'TP-03' => $rec('Calls TP-C-1803 and TP-C-1804 share client call no. IC-TP-903; TP-C-1803 has job TP-J-1803 with a done day and report TP-IR-1803. Click IC-TP-903 on TP-C-1803.', 'TP-C-1803', 'TP-C-1804', 'TP-J-1803'),
        'TP-04' => $rec('Calls TP-C-1805 (PO-TP-4587, Lot 1 — 40 valves) and TP-C-1806 (Lot 2 — 25 valves), jobs TP-J-1805 / TP-J-1806 today. Start a report from each.', 'TP-C-1805', 'TP-C-1806'),
        'TP-05' => $rec('After TP-04: issue the Lot 1 report (TP-J-1805), then start one from Lot 2 (TP-J-1806).', 'TP-J-1805', 'TP-J-1806'),
        'TP-06' => $rec('After TP-05: start another report of the same type from Lot 1 (TP-J-1805, same date).', 'TP-J-1805'),
        'TP-07' => $rec('Job TP-J-1807: 10 days, one report per day; day 1 is ' . tp_c_fd($d1807[0]) . ', day 4 is ' . tp_c_fd($d1807[3]) . '.', 'TP-J-1807'),
        'TP-08' => $rec('After TP-07: read the reports line on job TP-J-1807 and call TP-C-1807.', 'TP-J-1807', 'TP-C-1807'),
        'TP-09' => $rec('Job TP-J-1809 is agreed as "No report"; its 3 days are ' . $days($d1809) . '.', 'TP-J-1809'),
        'TP-10' => $rec('Posting TP-J-1810: weekly reports, started Monday ' . tp_c_fd($mon) . '; day 9 is ' . tp_c_fd(date('Y-m-d', strtotime($mon . ' +8 days'))) . '.', 'TP-J-1810'),
        'TP-11' => $rec('Job TP-J-1811: 5 open days (' . $days($d1811) . '). Day 3 is ' . tp_c_fd($d1811[2]) . '.', 'TP-J-1811'),
        'TP-12' => $rec('Job TP-J-1811: pick any open day (e.g. ' . tp_c_fd($d1811[3]) . ').', 'TP-J-1811'),
        'TP-13' => $rec('Report TP-IR-1813 (Test Inspector = X) is approved on job TP-J-1813; Test Inspector2 (Y) has job TP-J-1814 on the same call TP-C-1813.', 'TP-J-1813', 'TP-J-1814'),
        'TP-14' => $rec('After TP-13: as test.inspector2 open report TP-IR-1813\'s link.', 'TP-J-1813'),
        'TP-15' => $rec('Job TP-J-1815 (PO-TP-4590 Lot 1): report TP-IR-1815 is issued with "Release Note to be issued" ticked. Raise the Release Note. (The example report has no item lines.)', 'TP-J-1815'),
        'TP-16' => $rec('Job TP-J-1816 (PO-TP-4590 Lot 2): report TP-IR-1816 issued, ticked. Raise a second Release Note on the same PO.', 'TP-J-1816'),
        'TP-17' => $rec('Close job TP-J-1817 (asked only for an Inspection Report) and job TP-J-1818 (also asked for a Release Note). Both are finished, report on file.', 'TP-J-1817', 'TP-J-1818'),
        'TP-18' => $none('Compares each count with its list after TP-07 to TP-12. No record of its own needed.'),
        // 19 Order chain (25 Nov)
        'PC-01' => $rec('Call TP-C-1901 (Client PO PO-LT-2002) → Edit → add the two chain lines.', 'TP-C-1901'),
        'PC-02' => $rec('After PC-01: call TP-C-1901 → History.', 'TP-C-1901'),
        'PC-03' => $rec('After PC-01: call TP-C-1901 → Edit.', 'TP-C-1901'),
        'PC-04' => $rec('After PC-01: search PO-KSB-3003 — finds TP-C-1901.', 'TP-C-1901'),
        'PC-05' => $rec('After PC-01: call TP-C-1905 (same client) already has PO-IOC-1001 on its chain. On TP-C-1901 click "PO PO-IOC-1001".', 'TP-C-1901', 'TP-C-1905'),
        'PC-06' => $rec('After PC-01: open job TP-J-1901.', 'TP-J-1901'),
        'PC-07' => $rec('After PC-01: start a new report from job TP-J-1901 (draft) and open its PDF.', 'TP-J-1901'),
        'PC-08' => $rec('Call TP-C-1908 has no order chain; its draft report TP-IR-1908 is on job TP-J-1908. Open the PDF.', 'TP-C-1908', 'TP-J-1908'),
        'PC-09' => $rec('After PC-07: issue that report on job TP-J-1901, change the chain on TP-C-1901, open the PDF again.', 'TP-J-1901', 'TP-C-1901'),
        'PC-10' => $rec('After PC-09: raise the Release Note from the TP-J-1901 report (tick "Release Note to be issued" first).', 'TP-J-1901'),
        'PC-11' => $type('Type the role "LSTK contractor" in Masters.'),
        'PC-12' => $rec('On a phone: call TP-C-1901 → Edit → Order chain.', 'TP-C-1901'),
        // 20 Close no report (27 Nov) — keys carry the sheet number (sheet 14 also has OX-01…)
        '20/OX-01' => $rec('Job TP-J-2001 finished ' . tp_c_fd($past1) . ' with no report and no link. Close it.', 'TP-J-2001'),
        '20/OX-02' => $rec('After 20/OX-01: add a report to the closed job TP-J-2001.', 'TP-J-2001'),
        '20/OX-03' => $rec('Close job TP-J-2003 (agreed "No report") and job TP-J-2004 (terminated ' . tp_c_fd($d2004) . ').', 'TP-J-2003', 'TP-J-2004'),
        '20/OX-04' => $rec('On a phone open job TP-J-0201 (a job page with tabs).', 'TP-J-0201'),
        // 21 Release Note rule (28 Nov)
        'RN-01' => $rec('Job TP-J-2101: Release Note ticked, report TP-IR-2101 Accepted / Released, no Release Note yet. Close it.', 'TP-J-2101'),
        'RN-02' => $rec('Job TP-J-2102: Release Note ticked, report TP-IR-2102 says Hold. Open Close.', 'TP-J-2102'),
        'RN-03' => $rec('Job TP-J-2103: Release Note ticked, report TP-IR-2103 Rejected / Not released. Open Close.', 'TP-J-2103'),
        'RN-04' => $rec('Job TP-J-2104 (Final Report + Release Note): day 1 ' . tp_c_fd($d2104[0]) . ' done; last day ' . tp_c_fd($d2104[1]) . ' closed as No inspection — Material not ready (Castings still at the foundry). Open Close.', 'TP-J-2104'),
        'RN-05' => $rec('Job TP-J-2105: Release Note ticked, report TP-IR-2105 issued with no Overall result. Open Close.', 'TP-J-2105'),
        'RN-06' => $rec('Job TP-J-2106 (Final Report + Release Note): report TP-IR-2106 Hold, no Final Inspection Report. Close it.', 'TP-J-2106'),
        'RN-07' => $rec('Masters → "Why no Release Note (job close)". Then repeat RN-02 on job TP-J-2108 (another Hold job).', 'TP-J-2108'),
        'RN-08' => $rec('On a phone open Close on job TP-J-2108 (Hold, Release Note ticked).', 'TP-J-2108'),
    ];
    return $m;
}
