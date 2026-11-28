<?php
// TEST PACK — group OPS-C (sheets 13–21 of the Operations test book). Guards:
//   · load makes every record the OPS-C map names, and no logins;
//   · the Release Note rule records (RN-01…RN-06) give the answer the book expects
//     from job_final_docs_missing() / job_final_docs_exceptions();
//   · a second load adds nothing; remove leaves no OPS-C record and every table
//     back where it was; the owner's own records — including a voucher the pack
//     found and reused — are untouched.
t_section('Test pack OPS-C — sheets 13–21, Release Note rule records, exact removal');

$prevFlag = $GLOBALS['APP_TEST_COPY'] ?? null; $prevEnv = getenv('OPS_TEST_COPY');
$GLOBALS['APP_TEST_COPY'] = true; putenv('OPS_TEST_COPY=1');
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
    $insp = fn($login) => (int) ops_val("SELECT inspector_id FROM users WHERE username=?", [$login]);
    //  The owner's own call, report and voucher — must survive the removal untouched.
    db()->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status, created_at) VALUES ('OWN-TBV','TB Valves Pvt Ltd','TB Valves Pvt Ltd',1,'ACTIVE',?)")->execute([date('c')]);
    $ownClient = (int) db()->lastInsertId();
    db()->prepare("INSERT INTO calls (call_code, client_id, vendor_id, status, created_at) VALUES ('OWN-CALL-C', ?, ?, 'OPEN', ?)")->execute([$ownClient, $ownClient, date('c')]);
    $ownCall = (int) db()->lastInsertId();
    db()->prepare("INSERT INTO report_docs (irn, type_code, call_id, status, deleted, data, created_at) VALUES ('OWN-IR-1','IR',?, 'DRAFT', 0, '{}', ?)")->execute([$ownCall, date('c')]);
    $ownReport = (int) db()->lastInsertId();
    //  Test Inspector2 already has a voucher for last month on this copy: the pack must reuse it.
    exp_migrate();
    $lastMonth = date('Y-m-05', strtotime('first day of last month'));
    $ownV = exp_claim_for($insp('test.inspector2'), $lastMonth, true);
    db()->prepare("UPDATE vouchers SET status='SUBMITTED', total=999, created_by='owner' WHERE id=?")->execute([(int) $ownV['id']]);

    $tables = ['business_partners', 'calls', 'jobs', 'job_visits', 'report_docs', 'site_visits', 'vouchers', 'call_po_chain',
               'billable_events', 'assignment_events', 'call_status_events', 'expenses', 'inspectors', 'users'];
    $snap = function () use ($tables) { $o = []; foreach ($tables as $t) { try { $o[$t] = (int) ops_val("SELECT COUNT(*) FROM $t"); } catch (Throwable $e) { $o[$t] = -1; } } return $o; };
    $before = $snap();

    // ---- 1. Load ----
    t_as_admin();
    $r = tp_load();
    t_ok(!empty($r['ok']) && empty($r['errors']), 'load succeeds with no group errors' . (!empty($r['errors']) ? ' (' . json_encode($r['errors']) . ')' : ''));
    t_ok(isset(tp_status()['groups']['OPS-C']), 'the OPS-C group is registered');
    t_eq((int) ops_val("SELECT COUNT(*) FROM users"), $before['users'], 'load makes no logins');
    $after = $snap();

    // ---- 2. Every OPS-C test is mapped, and every ready-made record exists ----
    $ids = ['OW-01','OW-02','OW-03','OW-04','OW-05','OW-06','OW-07','OW-08',
            'OX-01','OX-02','OX-03','OX-04','OX-05','OX-06',
            'OY-01','OY-02','OY-03','OY-04','OY-05','OY-06',
            'OZ-01','OZ-02','OZ-03','OZ-04','OZ-05','OZ-06','OZ-07','OZ-08','OZ-09','OZ-10'];
    for ($i = 1; $i <= 13; $i++) $ids[] = sprintf('OA22-%02d', $i);
    for ($i = 1; $i <= 18; $i++) $ids[] = sprintf('TP-%02d', $i);
    for ($i = 1; $i <= 12; $i++) $ids[] = sprintf('PC-%02d', $i);
    for ($i = 1; $i <= 4; $i++)  $ids[] = sprintf('20/OX-%02d', $i);
    for ($i = 1; $i <= 8; $i++)  $ids[] = sprintf('RN-%02d', $i);
    t_eq(count($ids), 85, 'the nine sheets hold 85 tests');
    $map = tp_map();
    $missing = array_values(array_diff($ids, array_keys($map)));
    t_ok(!$missing, 'every OPS-C test is in the map' . ($missing ? ' (missing: ' . implode(', ', $missing) . ')' : ''));
    $bad = []; $recs = 0;
    foreach ($ids as $id) {
        $row = $map[$id] ?? null; if (!$row) continue;
        if (!in_array($row['kind'] ?? '', ['record', 'none', 'type'], true) || trim((string) ($row['text'] ?? '')) === '') { $bad[] = "$id kind/text"; continue; }
        if ($row['kind'] !== 'record') continue;
        $recs++;
        if (empty($row['codes'])) $bad[] = "$id no code";
        foreach ((array) $row['codes'] as $code) {
            $t = str_starts_with($code, 'TP-J-') ? ['jobs', 'job_code', $code] : (str_starts_with($code, 'TP-V-') ? ['vouchers', 'id', (int) substr($code, 5)] : ['calls', 'call_code', $code]);
            if (!ops_val("SELECT id FROM {$t[0]} WHERE {$t[1]}=?", [$t[2]])) $bad[] = "$id → $code";
        }
    }
    t_ok(!$bad && $recs >= 55, "every ready-made record the OPS-C map names exists ($recs record tests)" . ($bad ? ' (bad: ' . implode(', ', $bad) . ')' : ''));

    // ---- 3. Sheet 21 — the Release Note rule gives the book's answer ----
    $J = fn($code) => ops_one("SELECT * FROM jobs WHERE job_code=?", [$code]) ?: ['id' => 0];
    $miss = fn($code) => job_final_docs_missing($J($code));
    $exc  = fn($code) => job_final_docs_exceptions($J($code));
    t_eq($miss('TP-J-2101'), ['a Release Note'], 'RN-01: Accepted / Released, no Release Note — close is refused for the Release Note');
    t_eq($exc('TP-J-2101')['rn'], null, 'RN-01: no "why no Release Note" box');
    t_eq($miss('TP-J-2102'), [], 'RN-02: Hold — nothing is missing');
    t_eq($exc('TP-J-2102')['rn']['auto'] ?? null, 'HOLD', 'RN-02: "Hold point not cleared" is chosen by itself');
    t_eq($exc('TP-J-2103')['rn']['auto'] ?? null, 'REJECTED', 'RN-03: "Rejected — not released" is chosen by itself');
    t_eq($miss('TP-J-2104'), [], 'RN-04: last day No inspection — neither final document is asked for');
    $e4 = $exc('TP-J-2104');
    t_ok(($e4['rn']['auto'] ?? '') === 'ABORTIVE' && ($e4['fir']['auto'] ?? '') === 'ABORTIVE'
        && str_contains((string) ($e4['rn']['detail'] ?? ''), 'Castings still at the foundry'), 'RN-04: "Abortive visit" chosen for both, with the reason and the note');
    $v4 = ops_all("SELECT status, reason_code FROM job_visits WHERE job_id=? ORDER BY visit_date", [$J('TP-J-2104')['id']]);
    t_eq(array_map(fn($v) => $v['status'] . '/' . ($v['reason_code'] ?? ''), $v4), ['DONE/', 'NO_INSPECTION/MATERIAL_NOT_READY'], 'RN-04: day 1 done with its report, last day No inspection (Material not ready)');
    t_ok($miss('TP-J-2105') === [] && ($exc('TP-J-2105')['rn']['auto'] ?? null) === '', 'RN-05: no Overall result — a reason must be picked (nothing chosen by itself)');
    t_eq($miss('TP-J-2106'), ['a Final Inspection Report'], 'RN-06: Hold with Final Report ticked — the Final Inspection Report is still required');
    t_eq($exc('TP-J-2106')['rn']['auto'] ?? null, 'HOLD', 'RN-06: the Release Note is not asked for');
    t_eq($exc('TP-J-2108')['rn']['auto'] ?? null, 'HOLD', 'RN-07 / RN-08: the second Hold job');
    foreach (['TP-J-2101', 'TP-J-2102', 'TP-J-2106'] as $c)
        t_eq(site_visit_close_missing($J($c)), [], "$c has both check-ins (a check-in setting cannot block the close test)");

    // ---- 4. The other sheets' key records ----
    $V = fn($id) => ops_one("SELECT * FROM vouchers WHERE id=?", [(int) substr((string) ($map[$id]['codes'][0] ?? 'TP-V-0'), 5)]) ?: [];
    t_ok(($V('OW-02')['status'] ?? '') === 'DRAFT' && (int) $V('OW-02')['inspector_id'] === $insp('test.inspector') && $V('OW-02')['month'] === date('Y-m'),
        'OW-02: Test Inspector\'s voucher for this month is DRAFT');
    t_eq((int) ($V('OW-03')['id'] ?? 0), (int) $ownV['id'], 'OW-03: the copy\'s existing SUBMITTED voucher is reused, not duplicated');
    t_ok(!in_array((int) $ownV['id'], tp_tracked('vouchers'), true) && str_contains($map['OW-03']['text'], 'existing'), '… it is not in the removal ledger and the map says so');
    t_ok(($V('OW-04')['status'] ?? '') === 'PAID' && (int) $V('OW-04')['inspector_id'] === $insp('test.srinspector'), 'OW-04: a PAID voucher (Test SrInspector)');
    $j1306 = $J('TP-J-1306');
    t_ok((int) $j1306['closed_flag'] === 1 && (string) ops_val("SELECT status FROM report_docs WHERE irn='TP-IR-1306'") === 'SUBMITTED', 'OW-06: a closed job whose report waits for approval');
    t_eq(site_visit_close_missing($J('TP-J-1308')), ['a departure check-in'], 'OW-08: the job lacks only the departure check-in');
    t_ok((string) ops_val("SELECT photo_data FROM site_visits WHERE job_id=?", [$J('TP-J-1403')['id']]) !== '', 'OX-03: the check-in carries a photo');
    t_eq((string) ops_val("SELECT status FROM calls WHERE call_code='TP-C-1506'"), 'CLOSED', 'OY-06: the call is CLOSED');
    t_eq((int) ops_val("SELECT COUNT(*) FROM calls WHERE call_code IN ('TP-C-1503','TP-C-1504') AND status='OPEN' AND client_id=vendor_id"), 2, 'OY-03: two open calls, same client and site');
    $c1708 = ops_one("SELECT * FROM calls WHERE call_code='TP-C-1708'");
    t_ok($c1708['call_received_date'] > $c1708['inspection_required_date'], 'OA22-08: the old call was received after its required date');
    t_eq((int) ops_val("SELECT COUNT(*) FROM calls WHERE client_call_ref='IC-TP-903'"), 2, 'TP-03: two calls carry client call no. IC-TP-903');
    t_ok((int) ops_val("SELECT COUNT(*) FROM job_visits WHERE job_id=? AND status='DONE'", [$J('TP-J-1803')['id']]) === 1, 'TP-03: its job has a done visit day with the report');
    $j1807 = $J('TP-J-1807');
    t_ok((int) ops_val("SELECT COUNT(*) FROM job_visits WHERE job_id=?", [$j1807['id']]) === 10 && $j1807['reporting_frequency'] === 'PER_DAY', 'TP-07: a 10-day job, one report per day');
    t_ok(job_report_on_file($J('TP-J-1809')) && count(job_visits_open_days($J('TP-J-1809'))) === 3, 'TP-09: a 3-day "No report" job, its three days open');
    t_eq((int) date('N', strtotime($J('TP-J-1810')['inspection_start_date'])), 1, 'TP-10: the weekly posting starts on a Monday');
    t_ok(count(job_visits_open_days($J('TP-J-1811'))) === 5, 'TP-11 / TP-12: five open days');
    t_ok((string) ops_val("SELECT status FROM report_docs WHERE irn='TP-IR-1813'") === 'APPROVED' && (int) $J('TP-J-1814')['inspector_id'] === $insp('test.inspector2')
        && (int) $J('TP-J-1814')['call_id'] === (int) $J('TP-J-1813')['call_id'], 'TP-13: X\'s report approved; Y allocated on the same call');
    t_eq((int) ops_val("SELECT COUNT(*) FROM report_docs WHERE irn IN ('TP-IR-1815','TP-IR-1816') AND status='ISSUED' AND rn_to_issue=1 AND po_ref='PO-TP-4590'"), 2, 'TP-15 / TP-16: both lots issued, Release Note ticked, same PO');
    t_ok($miss('TP-J-1817') === [] && $miss('TP-J-1818') === ['a Release Note'], 'TP-17: the IR-only job closes; the Release Note job is stopped');
    $chain = pochain_lines((int) ops_val("SELECT id FROM calls WHERE call_code='TP-C-1905'"));
    t_ok(count($chain) === 1 && $chain[0]['po_no'] === 'PO-IOC-1001', 'PC-05: the second call\'s order chain has PO-IOC-1001');
    t_eq(pochain_lines((int) ops_val("SELECT id FROM calls WHERE call_code='TP-C-1908'")), [], 'PC-08: a call with no chain');
    t_ok(!job_report_on_file($J('TP-J-2001')) && job_close_needs_report_reason($J('TP-J-2001')), '20/OX-01: a finished job with no report asks for a reason');
    t_ok(!job_close_needs_report_reason($J('TP-J-2003')) && !job_close_needs_report_reason($J('TP-J-2004'))
        && strtoupper((string) $J('TP-J-2004')['billing_stop_kind']) === 'TERMINATED', '20/OX-03: the "No report" and the terminated job ask no reason');

    // ---- 5. A second load adds nothing ----
    $r2 = tp_load();
    t_ok(!empty($r2['already']), 'a second load says it is already loaded');
    t_eq($snap(), $after, 'a second load adds nothing');

    // ---- 6. Remove: exactly the pack ----
    $r = tp_remove();
    t_ok(!empty($r['ok']), 'remove succeeds');
    t_eq((int) ops_val("SELECT COUNT(*) FROM calls WHERE call_code LIKE 'TP-%'") + (int) ops_val("SELECT COUNT(*) FROM jobs WHERE job_code LIKE 'TP-%'")
        + (int) ops_val("SELECT COUNT(*) FROM report_docs WHERE irn LIKE 'TP-%'"), 0, 'no TP work orders, jobs or reports remain');
    t_eq((int) ops_val("SELECT COUNT(*) FROM vouchers WHERE created_by=?", [TP_BY]), 0, 'no pack voucher remains');
    t_eq($snap(), $before, 'every table is back to exactly what it held before the load');
    t_ok((int) ops_val("SELECT COUNT(*) FROM calls WHERE id=?", [$ownCall]) === 1 && (int) ops_val("SELECT COUNT(*) FROM report_docs WHERE id=?", [$ownReport]) === 1,
        'the owner\'s own call and report are untouched');
    $vAfter = ops_one("SELECT status, total, created_by FROM vouchers WHERE id=?", [(int) $ownV['id']]);
    t_ok($vAfter && $vAfter['status'] === 'SUBMITTED' && (float) $vAfter['total'] === 999.0 && $vAfter['created_by'] === 'owner', 'the reused voucher is untouched');
    t_ok(tp_ledger() === [] && tp_map() === [], 'the ledger and the map are cleared');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    $GLOBALS['APP_TEST_COPY'] = $prevFlag; if ($prevFlag === null) unset($GLOBALS['APP_TEST_COPY']);
    putenv($prevEnv === false ? 'OPS_TEST_COPY' : 'OPS_TEST_COPY=' . $prevEnv);
    t_as_nobody();
}
