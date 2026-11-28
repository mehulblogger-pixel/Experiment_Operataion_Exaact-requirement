<?php
// TEST PACK — group OPS-B (Operations sheets 6–12). Guards:
//   · every OPS-B test of the seven sheets is in the map, with a valid kind, and
//     every ready-made record it names exists after load;
//   · each case's "Before you start" state is really there, read with the app's
//     own readers (eligibility, ID note, SLA targets, prior reports, evidence
//     flags, billable queue, report approver, claims, hold points);
//   · loading twice adds nothing; Remove leaves none of OPS-B's rows — nor what
//     the app then made on them (a generated call) — and keeps the owner's data.
t_section('Test pack OPS-B — sheets 6–12: ready-made state, idempotent load, exact removal');

$prevFlag = $GLOBALS['APP_TEST_COPY'] ?? null; $prevEnv = getenv('OPS_TEST_COPY');
$setCopy = function ($on) { $GLOBALS['APP_TEST_COPY'] = (bool) $on; putenv('OPS_TEST_COPY' . ($on ? '=1' : '=0')); };
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // ---- Fixture: the copy's own logins and records (a TEST makes these; the pack never does) ----
    $ahm = (int) (ops_val("SELECT id FROM offices WHERE LOWER(name) LIKE '%ahmedabad%' ORDER BY id LIMIT 1") ?: 0);
    if (!$ahm) { db()->prepare("INSERT INTO offices (code,name,city,is_ahmedabad,is_active) VALUES ('TAH','Ahmedabad','Ahmedabad',1,1)")->execute(); $ahm = (int) db()->lastInsertId(); }
    $mkUser = function ($login, $role, $emp, $name) use ($ahm) {
        if ($id = (int) (ops_val("SELECT id FROM users WHERE username=?", [$login]) ?: 0)) return $id;
        db()->prepare("INSERT INTO inspectors (name, emp_code, status, staff_kind, roll_type, home_office_id, created_at) VALUES (?,?, 'ACTIVE','ASSET','OWN',?,?)")
            ->execute([$name, $emp, $ahm, date('c')]);
        $iid = (int) db()->lastInsertId();
        //  '!' is not a password hash — this login cannot sign in. No password is made.
        db()->prepare("INSERT INTO users (username, password_hash, first_name, last_name, role, is_superuser, is_active, inspector_id, home_office_id) VALUES (?, '!', ?, '', ?, 0, 1, ?, ?)")
            ->execute([$login, $name, $role, $iid, $ahm]);
        return (int) db()->lastInsertId();
    };
    $mkUser('test.coord', 'COORDINATOR', 'EMP12', 'Test Coord');
    $mkUser('test.srinspector', 'SR_INSPECTOR', 'EMP17', 'Test SrInspector');
    $mkUser('test.inspector', 'INSPECTOR', 'EMP18', 'Test Inspector');
    $mkUser('test.inspector2', 'INSPECTOR', 'EMP19', 'Test Inspector2');
    $opsUid = $mkUser('test.opsmgr', 'OPS_MANAGER', 'EMP05', 'Test OpsMgr');
    $mkUser('test.director', 'DIRECTOR', 'EMP02', 'Test Director');
    $insOf = fn($login) => (int) ops_val("SELECT inspector_id FROM users WHERE username=?", [$login]);
    //  The owner's own records — must survive the pack's removal untouched.
    db()->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status, created_at) VALUES ('OWN-TBR','TB Refinery Ltd','TB Refinery Ltd',1,'ACTIVE',?)")->execute([date('c')]);
    $ownRefinery = (int) db()->lastInsertId();
    db()->prepare("INSERT INTO vouchers (inspector_id, month, status, created_by, created_at) VALUES (?, ?, 'DRAFT', 'owner', ?)")->execute([$insOf('test.inspector'), date('Y-m'), date('c')]);
    $ownVoucher = (int) db()->lastInsertId();
    db()->prepare("INSERT INTO sla_targets (client_id, stage, target_days, note) VALUES (0, 'RESPONSE', 1, 'owner')")->execute();
    $ownSla = (int) db()->lastInsertId();

    $tables = ['business_partners', 'inspectors', 'inspector_certs', 'person_documents', 'calls', 'jobs', 'job_visits', 'report_docs',
               'report_files', 'billable_events', 'vouchers', 'voucher_entries', 'recurring_services', 'sla_targets', 'hw_points', 'users'];
    $snap = function () use ($tables) { $o = []; foreach ($tables as $t) $o[$t] = (int) ops_val("SELECT COUNT(*) FROM $t"); return $o; };
    $setCopy(true); t_as_admin();
    tp_load(); tp_remove();          // every table the pack writes now exists; start from an unloaded copy
    $before = $snap();

    // ---- 1. Load: OPS-B is registered and loads with no group error ----
    t_ok(isset(tp_seeders()['OPS-B']), 'the OPS-B group is registered');
    $r = tp_load();
    t_ok(!empty($r['ok']) && empty($r['errors']), 'load succeeds with no group errors' . (!empty($r['errors']) ? ' (' . json_encode($r['errors']) . ')' : ''));
    $after = $snap();
    t_eq((int) ops_val("SELECT COUNT(*) FROM users"), $before['users'], 'load makes no logins');

    // ---- 2. The map: all 88 tests of sheets 6–12, each with a valid kind; every code exists ----
    $ids = ['OH0-01','OH0-02','OH0-03','OH0-04','OH0-05','OH0-06','OH0-07','OK-16','OK-17','OK-18',
            'OF-01','OF-02','OF-03','OF-04','OF-05','OF-06','OF-07','OF-08','OF-09','OF-10','OF-11','OF-12','OP-05','OP-06','OP-07','OP-08','OP-09','OP-10','OP-11','OP-12',
            'OG-01','OG-02','OG-03','OG-04','OG-05','OG-06','OG-07','OG-08','OG-09','OG-10','OG-11','OG-12','OK-20','OP-13','OP-14','OP-15',
            'OV-01','OV-02','OV-03','OV-04','OV-05','OV-06','OV-07','OV-08','OV-09','OV-10','OV-11','OV-12','OV-13','OV-14','OV-15','OV-16',
            'OR-01','OR-02','OR-03','OR-04','OR-05','OP-03','OP-04',
            'OM-01','OM-02','OM-03','OM-04','OM-05','OM-06','OM-07','OM-08','OP-16','OP-17',
            'OQ-01','OQ-02','OQ-03','OQ-04','OQ-05','OK-19','OP-18','OP-19','OP-20'];
    t_eq(count($ids), 88, 'the seven sheets hold 88 tests');
    $map = tp_map();
    $bad = [];
    foreach ($ids as $id) {
        $row = $map[$id] ?? null;
        if (!$row) { $bad[] = "$id missing"; continue; }
        if (!in_array($row['kind'] ?? '', ['record', 'none', 'type'], true) || trim((string) ($row['text'] ?? '')) === '') { $bad[] = "$id kind/text"; continue; }
        if ($row['kind'] !== 'record') continue;
        if (empty($row['codes'])) $bad[] = "$id no code";
        foreach ((array) $row['codes'] as $code) {
            $t = str_starts_with($code, 'TP-J-') ? ['jobs', 'job_code'] : ['calls', 'call_code'];
            if (!ops_val("SELECT id FROM {$t[0]} WHERE {$t[1]}=?", [$code])) $bad[] = "$id → $code";
        }
    }
    t_ok(!$bad, 'every OPS-B test is mapped and every record it names exists' . ($bad ? ' (' . implode(', ', $bad) . ')' : ''));
    t_ok(str_contains($map['OP-15']['text'], 'NOT MADE') && str_contains($map['OV-13']['text'], 'NOT MADE'), 'the two cases the pack cannot make say so, and why');

    // ---- 3. Each "Before you start" state, read with the app's own readers ----
    $job = fn($c) => ops_one("SELECT * FROM jobs WHERE job_code=?", [$c]);
    $cid = fn($c) => (int) ops_val("SELECT id FROM calls WHERE call_code=?", [$c]);
    t_eq((string) ops_val("SELECT hold_status FROM business_partners WHERE id=?", [$ownRefinery]) ?: '', '', 'OG-01: the owner\'s TB Refinery Ltd is reused (no hold), not duplicated');
    t_eq((int) ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name='TB Refinery Ltd'"), 1, 'OG-01: one TB Refinery Ltd');
    t_ok((int) ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name='TB Group Child' AND status='ACTIVE' AND COALESCE(hold_status,'')=''") === 1, 'OK-20: TB Group Child is Active, no hold');
    $re = ops_one("SELECT * FROM inspectors WHERE emp_code='TP-RE-01'");
    t_eq((int) $re['reports_to_id'], $opsUid, 'OP-04: TP Report Engineer reports to test.opsmgr');
    $cert = ops_one("SELECT * FROM inspector_certs WHERE inspector_id=?", [(int) $re['id']]);
    t_ok($cert && (string) $cert['verify_status'] === '' && str_starts_with((string) $cert['file_data'], 'data:application/pdf;base64,'), 'OP-06: his credential is uploaded and not yet verified');
    $ag2 = (int) ops_val("SELECT id FROM inspectors WHERE emp_code='TP-AG-02' AND roll_type='AGENCY'");
    t_eq(iddoc_note_for_person($ag2), 'Every identity document on file has expired or been redacted.', 'OP-09: the agency engineer\'s only ID has expired');
    t_ok((int) ops_val("SELECT COUNT(*) FROM jobs WHERE call_id=?", [$cid('TP-C-B709')]) === 0, 'OP-09: his work order is not yet allocated');
    $svc = (int) ops_val("SELECT id FROM business_partners WHERE legal_name='TP Service Schedules Pvt Ltd (example)'");
    $rec = ops_one("SELECT * FROM recurring_services WHERE client_id=?", [$svc]);
    t_ok($rec && (int) $rec['active'] === 1 && $rec['next_run'] === date('Y-m-d'), 'OP-13: an active recurring schedule, due today');
    t_eq(tosrm_sla_targets($svc)['SCHEDULING'] ?? null, 2, 'OP-14: the customer\'s own allocation target is 2 days');
    $c714 = ops_one("SELECT * FROM calls WHERE call_code='TP-C-B714'");
    t_ok($c714['call_received_date'] === tp_d(-5) && (int) ops_val("SELECT COUNT(*) FROM jobs WHERE call_id=?", [(int) $c714['id']]) === 0, 'OP-14: its work order was received 5 days ago and is not allocated');
    $j703 = $job('TP-J-B703');
    t_ok((int) $j703['closed_flag'] === 1, 'OP-03: job TP-J-B703 is closed');
    $doc = ops_one("SELECT * FROM report_docs WHERE irn='TP-IR-B703'");
    t_ok($doc && $doc['status'] === 'ISSUED' && (int) $doc['finalized'] === 1 && (int) $doc['job_id'] === (int) $j703['id'], 'OP-03: its report is issued');
    t_ok(in_array((int) $doc['id'], array_map('intval', array_column(job_prior_inspections($job('TP-J-B704')), 'id')), true), 'OP-03: the repeat job TP-J-B704 offers it to forward');
    $ph = ops_one("SELECT * FROM report_files WHERE report_doc_id=?", [(int) $doc['id']]);
    t_ok($ph && evidence_serious($ph) === ['FAR_FROM_SITE'] && (string) $ph['reviewed_at'] === '', 'OR-04: a flagged photo waits for review');
    $ev = ops_one("SELECT * FROM billable_events WHERE source_module='job' AND source_kind='JOB_CLOSED' AND source_id=?", [(int) $j703['id']]);
    t_ok($ev && $ev['status'] === 'PENDING' && (float) $ev['amount'] === 25000.0, 'OP-16: the closed job is queued as billable (₹25,000)');
    foreach (['TP-J-B705', 'TP-J-B706'] as $c) {
        $j = $job($c);
        t_ok((int) $j['closed_flag'] === 1 && $j['report_approval'] === 'PENDING' && report_approver_user_id($j) === $opsUid, "OP-04: $c is closed, its report waiting for test.opsmgr");
    }
    $v = fn($ins, $st) => ops_one("SELECT * FROM vouchers WHERE inspector_id=? AND status=? AND created_by=?", [$ins, $st, TP_BY]);
    $vd = $v($insOf('test.inspector'), 'DRAFT'); $va = $v($insOf('test.inspector2'), 'APPROVED'); $vs = $v($insOf('test.director'), 'SUBMITTED');
    $lines = fn($x) => $x ? (int) ops_val("SELECT COUNT(*) FROM voucher_entries WHERE voucher_id=?", [(int) $x['id']]) : 0;
    t_ok($vd && $lines($vd) === 1 && (float) $vd['total'] === 450.0 && $vd['month'] === date('Y-m', strtotime('first day of last month')), 'OV-05/07/12: Test Inspector\'s DRAFT claim of last month, one line, ₹450');
    t_ok($va && $lines($va) === 1, 'OV-08/OP-17: Test Inspector2\'s APPROVED claim with a line');
    t_ok($vs && $lines($vs) === 1, 'OV-06: the Business Director\'s own SUBMITTED claim');
    t_ok(str_contains($map['OV-07']['text'], '/voucher?id=' . (int) $vd['id']), 'the map gives the claim\'s address');
    t_eq((array) exp_claim_for($insOf('test.inspector'), date('Y-m-d'), false), (array) ops_one("SELECT * FROM vouchers WHERE id=?", [$ownVoucher]),
        'an expense the tester adds today goes to the owner\'s own claim, never into a pack claim');
    $hp = ops_one("SELECT * FROM hw_points WHERE job_id=?", [(int) $job('TP-J-B719')['id']]);
    t_ok($hp && $hp['point_type'] === 'HOLD' && $hp['status'] === 'OPEN', 'OP-19: job TP-J-B719 has an open hold point');
    t_eq((int) ops_val("SELECT COUNT(*) FROM hw_points WHERE job_id=?", [(int) $job('TP-J-0201')['id']]), 0, 'the Part 1 job TP-J-0201 gets no hold point (its close tests stay clean)');

    // ---- 4. Loading twice adds nothing ----
    $r2 = tp_load();
    t_ok(!empty($r2['already']), 'a second load says it is already loaded');
    t_eq($snap(), $after, 'a second load adds nothing');

    // ---- 5. Remove: OPS-B's rows and what the app then made on them; the owner's stay ----
    $n0 = (int) ops_val("SELECT COUNT(*) FROM calls");
    tosrm_recurring_generate($rec);                                             // the tester presses "Run due schedules now"
    t_eq((int) ops_val("SELECT COUNT(*) FROM calls"), $n0 + 1, 'the schedule generates a call on the pack customer');
    $r = tp_remove();
    t_ok(!empty($r['ok']), 'remove succeeds');
    t_eq((int) ops_val("SELECT COUNT(*) FROM calls WHERE call_code LIKE 'TP-C-B7%'") + (int) ops_val("SELECT COUNT(*) FROM jobs WHERE job_code LIKE 'TP-J-B7%'"), 0, 'no OPS-B work orders or jobs remain');
    t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE emp_code IN ('TP-RE-01','TP-AG-02')"), 0, 'OPS-B\'s own people are gone');
    t_eq((int) ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name IN ('TB Group Child','TP Service Schedules Pvt Ltd (example)')"), 0, 'OPS-B\'s own customers are gone');
    t_eq($snap(), $before, 'every table is back to exactly what it held before the load — the generated call included');
    t_ok((int) ops_val("SELECT COUNT(*) FROM business_partners WHERE id=?", [$ownRefinery]) === 1
        && (int) ops_val("SELECT COUNT(*) FROM vouchers WHERE id=?", [$ownVoucher]) === 1
        && (int) ops_val("SELECT COUNT(*) FROM sla_targets WHERE id=?", [$ownSla]) === 1, 'the owner\'s customer, claim and SLA target are untouched');

    // ---- 6. A copy whose test.director is not a staff record: OV-06 says so, nothing breaks ----
    db()->prepare("UPDATE users SET inspector_id=NULL WHERE username='test.director'")->execute();
    $r = tp_load();
    t_ok(!empty($r['ok']) && tp_map()['OV-06']['kind'] === 'none' && str_contains(tp_map()['OV-06']['text'], 'NOT MADE'), 'no director staff record: OV-06 is explained, the load still succeeds');
    tp_remove();
    t_eq($snap(), $before, 'and Remove is exact there too');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    $GLOBALS['APP_TEST_COPY'] = $prevFlag; if ($prevFlag === null) unset($GLOBALS['APP_TEST_COPY']);
    putenv($prevEnv === false ? 'OPS_TEST_COPY' : 'OPS_TEST_COPY=' . $prevEnv);
    t_as_nobody();
}
