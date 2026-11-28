<?php
// TEST PACK (owner, 28 Nov 2026) — the ready-made test-book data. Guards:
//   · refused when the site is not the test copy, and for anyone but the Master Admin;
//   · load makes the records the map names; loading twice adds nothing;
//   · remove leaves no pack record behind and touches nothing else;
//   · every "Ready-made record" in the map exists after load.
t_section('Test pack — test copy only, Master Admin only, exact removal');

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
    $coordUid = $mkUser('test.coord', 'COORDINATOR', 'EMP12', 'Test Coord');
    $mkUser('test.srinspector', 'SR_INSPECTOR', 'EMP17', 'Test SrInspector');
    $mkUser('test.inspector', 'INSPECTOR', 'EMP18', 'Test Inspector');
    $mkUser('test.inspector2', 'INSPECTOR', 'EMP19', 'Test Inspector2');
    //  The owner's own customer and call — must survive the pack's removal untouched.
    db()->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status, created_at) VALUES ('OWN-TBV','TB Valves Pvt Ltd','TB Valves Pvt Ltd',1,'ACTIVE',?)")->execute([date('c')]);
    $ownClient = (int) db()->lastInsertId();
    db()->prepare("INSERT INTO calls (call_code, client_id, vendor_id, status, created_at) VALUES ('OWN-CALL-1', ?, ?, 'OPEN', ?)")->execute([$ownClient, $ownClient, date('c')]);
    $ownCall = (int) db()->lastInsertId();

    $tables = ['business_partners', 'partner_contracts', 'partner_addresses', 'inspectors', 'inspector_certs', 'subcons', 'calls', 'jobs',
               'job_visits', 'expenses', 'assignment_events', 'call_status_events', 'users'];
    $snap = function () use ($tables) { $o = []; foreach ($tables as $t) $o[$t] = (int) ops_val("SELECT COUNT(*) FROM $t"); return $o; };
    $before = $snap();

    // ---- 1. Refused off the test copy — function, and nothing is written ----
    $setCopy(false); t_as_admin();
    $r = tp_load();
    t_ok(empty($r['ok']) && stripos((string) ($r['error'] ?? ''), 'test copy') !== false, 'off the test copy: load is refused with the reason');
    $r = tp_remove();
    t_ok(empty($r['ok']), 'off the test copy: remove is refused too');
    t_eq($snap(), $before, 'a refused load writes nothing');
    t_as_nobody();

    // ---- 2. Refused for anyone but the Master Admin ----
    $setCopy(true);
    $_SESSION['uid'] = $coordUid; current_user(true); ua(true);
    $r = tp_load();
    t_ok(empty($r['ok']) && stripos((string) ($r['error'] ?? ''), 'master admin') !== false, 'a coordinator on the test copy is refused');
    $_SESSION = []; current_user(true); ua(true);
    t_ok(!empty(tp_load()['error']), 'no one signed in: refused');
    t_eq($snap(), $before, 'refused loads write nothing');

    // ---- 3. Master on the test copy: status names the missing logins ----
    t_as_admin();
    $s = tp_status();
    t_ok($s['allowed'] === true && $s['loaded'] === false, 'master on the test copy: allowed, nothing loaded yet');
    t_ok(in_array('test.bdm', $s['missing_logins'], true) && !in_array('test.inspector', $s['missing_logins'], true),
        'status lists the logins this copy lacks (test.bdm) and not those it has (test.inspector)');
    t_ok(isset($s['groups']['OPS-A']), 'the OPS-A group is registered');

    // ---- 4. Load ----
    $r = tp_load();
    t_ok(!empty($r['ok']) && empty($r['errors']), 'load succeeds with no group errors' . (!empty($r['errors']) ? ' (' . json_encode($r['errors']) . ')' : ''));
    $after = $snap();
    t_ok($after['calls'] - $before['calls'] === 15 && $after['jobs'] - $before['jobs'] === 11, 'load makes 15 work orders and 11 jobs');
    t_eq((int) ops_val("SELECT COUNT(*) FROM users"), $before['users'], 'load makes no logins');
    t_eq((int) ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name='TB Valves Pvt Ltd'"), 1, 'the existing TB Valves customer is reused, not duplicated');
    t_ok(!in_array($ownClient, tp_tracked('business_partners'), true), 'a reused customer is not in the removal ledger');
    $jI = ops_one("SELECT * FROM jobs WHERE job_code='TP-J-0201'");
    t_eq((int) $jI['inspector_id'], (int) ops_val("SELECT inspector_id FROM users WHERE username='test.inspector'"), 'the Part 1 job is on the inspector linked to the test.inspector login');
    t_ok((int) ops_val("SELECT COUNT(*) FROM job_visits WHERE job_id=?", [$jI['id']]) >= 1, 'its visit day was made by job_visits_sync');
    $mohan = (int) ops_val("SELECT id FROM inspectors WHERE emp_code='SC-001'");
    t_eq(inspector_eligibility($mohan)['status'], 'BLOCKED', 'Mohan Rao (SC-001) is blocked by the lapsed required certificate');
    t_ok(job_lock_state(ops_one("SELECT * FROM jobs WHERE job_code='TP-J-0204'"))['locked'], 'the OE-05 job is locked');
    t_eq(tosrm_call_status(ops_one("SELECT * FROM calls WHERE call_code='TP-C-0115'")), 'CANCELLED', 'the S1-B2-01 work order is Cancelled');
    t_ok((int) ops_val("SELECT COUNT(*) FROM call_status_events WHERE call_id=(SELECT id FROM calls WHERE call_code='TP-C-0115')") === 1, 'the cancellation has its reasoned status event');
    t_eq((string) ops_val("SELECT accept_state FROM jobs WHERE job_code='TP-J-0401'"), 'DECLINED', 'the S1-B2-04 assignment is Declined');
    t_eq(tosrm_call_ready(ops_one("SELECT * FROM calls WHERE call_code='TP-C-0114'"))['missing'], ['Deliverable'], 'the S1-B3-01 work order lacks only a Deliverable');
    t_ok((int) ops_val("SELECT COUNT(*) FROM partner_contracts WHERE contract_number='TB-CT-PEND' AND open_status='PENDING'") === 1, 'contract TB-CT-PEND is registered, opening PENDING');

    // ---- 5. Every mapped "ready-made record" exists ----
    $map = tp_map();
    t_ok(count($map) >= 85, 'the map covers the 85 tests of the seven sheets (' . count($map) . ')');
    $bad = [];
    foreach ($map as $id => $row) {
        if (!in_array($row['kind'] ?? '', ['record', 'none', 'type'], true)) { $bad[] = "$id kind"; continue; }
        if ($row['kind'] !== 'record') continue;
        if (empty($row['codes'])) $bad[] = "$id no code";
        foreach ((array) ($row['codes'] ?? []) as $code) {
            $t = str_starts_with($code, 'TP-J-') ? ['jobs', 'job_code'] : ['calls', 'call_code'];
            if (!ops_val("SELECT id FROM {$t[0]} WHERE {$t[1]}=?", [$code])) $bad[] = "$id → $code";
        }
    }
    t_ok(!$bad, 'every ready-made record the map names exists' . ($bad ? ' (missing: ' . implode(', ', $bad) . ')' : ''));

    // ---- 6. Loading twice adds nothing ----
    $r2 = tp_load();
    t_ok(!empty($r2['already']), 'a second load says it is already loaded');
    t_eq($snap(), $after, 'a second load adds nothing');

    // ---- 7. Remove: exactly the pack, plus what was done on its own records ----
    $tpCall = (int) ops_val("SELECT id FROM calls WHERE call_code='TP-C-0102'");
    db()->prepare("INSERT INTO jobs (job_code, call_id, created_at) VALUES ('JOB-OWNER-ON-TP', ?, ?)")->execute([$tpCall, date('c')]);   // the owner allocates OB-04
    db()->prepare("INSERT INTO expenses (job_id, food, exp_date, created_at) VALUES ((SELECT id FROM jobs WHERE job_code='TP-J-0202'), 120, ?, ?)")->execute([date('Y-m-d'), date('c')]);   // and books an expense on a pack job
    $r = tp_remove();
    t_ok(!empty($r['ok']) && $r['deleted'] > 0, 'remove succeeds');
    t_eq((int) ops_val("SELECT COUNT(*) FROM calls WHERE call_code LIKE 'TP-%'"), 0, 'no TP work orders remain');
    t_eq((int) ops_val("SELECT COUNT(*) FROM jobs WHERE job_code LIKE 'TP-%' OR job_code='JOB-OWNER-ON-TP'"), 0, 'no TP jobs remain — nor the job made on a TP call');
    t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE emp_code IN ('SC-001','TP-FL-01','TP-AG-01')"), 0, 'the pack\'s own people are gone');
    t_eq($snap(), $before, 'every table is back to exactly what it held before the load');
    t_ok((int) ops_val("SELECT COUNT(*) FROM calls WHERE id=? AND call_code='OWN-CALL-1'", [$ownCall]) === 1, 'the owner\'s own call is untouched');
    t_ok((int) ops_val("SELECT COUNT(*) FROM business_partners WHERE id=?", [$ownClient]) === 1, 'the reused TB Valves customer is untouched');
    t_ok(tp_ledger() === [] && tp_map() === [], 'the ledger and the map are cleared');
    t_ok(!empty(tp_load()['ok']) && (int) ops_val("SELECT COUNT(*) FROM calls WHERE call_code LIKE 'TP-%'") === 15, 'it can be loaded again after removal');
    tp_remove();

    // ---- 8. The route and the screen ask the same questions ----
    $src = file_get_contents(__DIR__ . '/../lib/ops.php');
    t_ok((bool) preg_match("/'testpack-load'.*?ops_require\(is_master\(\).*?ops_require\(function_exists\('app_is_test_copy'\) && app_is_test_copy\(\)/s", $src), 'the routes require Master Admin and the test copy');
    $view = file_get_contents(__DIR__ . '/../views/ops/settings.php');
    t_ok(str_contains($view, "is_master() && function_exists('app_is_test_copy') && app_is_test_copy()"), 'the panel is shown only to the Master Admin on the test copy');
    $cli = file_get_contents(__DIR__ . '/../tools/seed-testpack.php');
    t_ok(str_contains($cli, 'app_is_test_copy()') && str_contains($cli, 'exit(2)'), 'the command line refuses off the test copy');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    $GLOBALS['APP_TEST_COPY'] = $prevFlag; if ($prevFlag === null) unset($GLOBALS['APP_TEST_COPY']);
    putenv($prevEnv === false ? 'OPS_TEST_COPY' : 'OPS_TEST_COPY=' . $prevEnv);
    t_as_nobody();
}
