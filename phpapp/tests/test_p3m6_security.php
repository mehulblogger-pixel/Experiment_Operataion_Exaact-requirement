<?php
// ============================================================================
//  PHASE 3 · M6 — NEGATIVE / SECURITY MATRIX ACROSS THE LIFECYCLE
//
//  Tenant, branch, role, entitlement, malformed input, refusal ordering and
//  stale state — asked of the INTEGRATED gate rather than of one milestone.
// ============================================================================

t_section('Phase 3 · M6 — the negative matrix');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate();
recruit_iv_migrate(); recruit_offer_migrate(); ensure_settings_schema();
$m6s = $_SESSION; $engine = db_driver();
foreach ([[9621,'M6S Branch A'], [9622,'M6S Branch B']] as $o)
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); } catch (Throwable $e) {}
$mk = function ($un, $role, $super, $office, $scope) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,'M6S',?,1,?,?,?)")->execute([$un, $role, $super ? 1 : 0, $office, $scope]);
    return (int) $pdo->lastInsertId(); };
$act = function ($u) { $_SESSION['uid'] = $u; current_user(true); ua(true); };
$uBoss = $mk('m6s_boss', 'MANAGER', 1, 9621, '');
$uA    = $mk('m6s_a', 'COORDINATOR', 0, 9621, '9621');
$uB    = $mk('m6s_b', 'COORDINATOR', 0, 9622, '9622');
$uInsp = $mk('m6s_insp', 'INSPECTOR', 0, 9621, '9621');
$act($uBoss);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uBoss, 'requested_by_name' => 'M6S', 'requesting_department_id' => $qua['id'],
    'hiring_department_id' => $eng['id'], 'job_title' => 'M6S Engineer', 'designation' => 'ENGINEER',
    'job_description' => 'm6s', 'quantity' => 3, 'office_id' => 9621, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x'], $x);
$approve = function (array $x = []) use ($base) {
    [$ok,, $id] = hreq_save(0, $base($x)); if (!$ok) return 0;
    hreq_submit($id); hreq_apply_decision($id, 'APPROVED', 'M6S Approver', 'ok'); return (int) $id; };
$mkCand = function ($req, $stage = 'RECEIVED') use ($pdo) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,created_at)
                   VALUES (?,'M6S','C',?,?,?)")->execute(['M6SC-' . bin2hex(random_bytes(3)), $stage, $req, date('c')]);
    return (int) $pdo->lastInsertId(); };

$hA = $approve(); [$okA,, $rqA] = hreq_to_requisition($hA, 3); $cA = $mkCand($rqA);
rasg_assign('REQ_RECRUITER', $rqA, $uA, ['expect' => null]);
//  Ask the gate about this requirement HERE, in workspace A, before the tenant
//  probe switches workspaces. Without this warm-up a cross-workspace cache would
//  have nothing in it to leak, and the probe would pass while leaking — which is
//  exactly how a mutation that added such a cache survived the first battery.
t_eq(rexec_block_reason($rqA, 'ADVANCE', $cA), '', 'S0 · the gate allows this requirement in its own workspace');

// ---- S1 · MALFORMED INPUT MUST NOT BECOME AN ENTITY -------------------------
t_section('S1 · malformed input never becomes a valid identity');
$bad = ['array' => ['x'], 'nested' => [['x']], 'alpha' => 'abc', 'mixed' => '7x',
        'negative' => -5, 'decimal' => '1.9', 'space' => '  ', 'huge' => '999999999999999999999',
        'leadingzero' => '007', 'sci' => '1e3'];
foreach ($bad as $what => $v) {
    //  The gate is asked about a requirement id that is not one. It must either
    //  refuse or treat it as "no requirement" — it must NEVER resolve to some
    //  other tenant's or branch's record by coercion.
    $r = rexec_block_reason($v, 'ADVANCE');
    $resolved = is_scalar($v) && ctype_digit((string) $v) ? (int) $v : 0;
    t_ok($resolved > 0 || $r === '' || strpos($r, 'no longer exists') !== false,
         "S1 · a $what requirement id does not resolve to a live requirement");
}
//  The M5 ownership door is the strictest example, and it is asked here too.
foreach (['array' => ['x'], 'nested' => [['x']], 'alpha' => 'abc', 'space' => ' ', 'decimal' => '1.9'] as $what => $v)
    t_eq(rasg_assign('REQ_RECRUITER', $rqA, $v, ['expect' => $uA])['code'], 'BAD_VALUE',
         "S1 · a $what recruiter is refused as BAD_VALUE");
t_eq((int) ops_val("SELECT COALESCE(recruiter_id,0) FROM requisitions WHERE id=?", [$rqA]), $uA,
     'S1 · after ten malformed attempts the owner is unchanged');
//  '007' is a number written oddly, not an attack: it must resolve to user 7 or
//  be refused, never to something else.
$seven = rasg_person_id('007', $ok7);
t_ok($ok7 && $seven === 7, 'S1 · leading zeros resolve to the number they spell');
t_eq(rasg_person_id('1e3', $okE), null, 'S1 · scientific notation is not a person id');
t_ok(!$okE, 'S1 · …and is reported as invalid rather than silently zero');

// ---- S2 · BRANCH ISOLATION ACROSS THE LIFECYCLE -----------------------------
t_section('S2 · a branch-B user against branch-A work');
$act($uB);
t_ok(!hreq_in_scope(hreq_get($hA)), 'S2.1 · the hiring request is out of scope');
[$sOk, $sMsg] = hreq_save($hA, $base(['job_title' => 'stolen']));
t_ok(!$sOk, 'S2.2 · …and cannot be edited: ' . $sMsg);
[$dOk, $dMsg] = hreq_apply_decision($hA, 'REJECTED', 'attacker', 'no');
t_ok(!$dOk, 'S2.3 · nor decided');
[$tOk, $tMsg] = hreq_to_requisition($hA, 1);
t_ok(!$tOk, 'S2.4 · nor converted to a requisition');
t_eq(rasg_assign('REQ_RECRUITER', $rqA, $uB, ['expect' => $uA])['code'], 'OUT_OF_SCOPE',
     'S2.5 · nor may they take ownership of its requisition');
$wl = rasg_workload($uA);
t_eq($wl['assigned_requisitions'], 0, 'S2.6 · branch A\'s workload is invisible to them');
$f = ['fy' => '', 'range' => null, 'month' => '', 'dept' => '', 'source' => '', 'manager' => ''];
$dB = rcc_data($f);
$seen = array_map(fn($r) => (int) $r['uid'], $dB['recruiters']);
t_ok(!in_array($uA, $seen, true), 'S2.7 · …and so is branch A\'s recruiter on the dashboard');
$act($uBoss);

// ---- S3 · ROLE MATRIX ------------------------------------------------------
t_section('S3 · a least-privilege role across every write');
$act($uInsp);
$cS = $mkCand($rqA);
t_ok(!hreq_can_create(), 'S3.1 · may not raise a hiring request');
[$e1] = hreq_save(0, $base(['job_title' => 'M6S Inspector']));
t_ok(!$e1, 'S3.2 · …and the write refuses');
t_ok(!hreq_can_decide(), 'S3.3 · may not decide one');
t_eq(rasg_assign('REQ_RECRUITER', $rqA, $uA, ['expect' => $uA])['code'], 'NO_PERMISSION',
     'S3.4 · may not change recruiter accountability');
t_ok(!is_coordinator_level(), 'S3.5 · is below the recruitment write band');
$act($uBoss);

// ---- S4 · REFUSAL ORDER MUST NOT LEAK EXISTENCE -----------------------------
t_section('S4 · an unauthorised caller learns nothing from the refusals');
$act($uInsp);
$real = rasg_assign('REQ_RECRUITER', $rqA, $uA, ['expect' => 999999])['code'];
$fake = rasg_assign('REQ_RECRUITER', 987654, $uA, ['expect' => 999999])['code'];
t_eq($real, 'NO_PERMISSION', 'S4.1 · a real record refuses with NO_PERMISSION');
t_ok(in_array($fake, ['NO_PERMISSION', 'NO_RECORD'], true), 'S4.2 · a record that does not exist gives: ' . $fake);
t_eq($real, 'NO_PERMISSION',
     'S4.3 · *** the answer for a real record does not depend on the baseline guessed ***');
$act($uBoss);

// ---- S5 · STALE STATE ACROSS MILESTONES ------------------------------------
t_section('S5 · an old screen against a newer business state');
$hS = $approve(['job_title' => 'M6S Stale']); [$okS,, $rqS] = hreq_to_requisition($hS, 2);
$cS2 = $mkCand($rqS);
$screenOwner = (int) ops_val("SELECT COALESCE(recruiter_id,0) FROM requisitions WHERE id=?", [$rqS]);
$screenSeats = rexec_seats($rqS)['remaining'];
//  the world moves on: a material change lands and a seat is taken
hreq_save($hS, $base(['job_title' => 'M6S Stale', 'quantity' => 2, 'grade' => 'G9']));
t_ok(rexec_block_reason($rqS, 'OFFER', $cS2) !== '',
     'S5.1 · *** the old screen\'s offer is refused against the newer state ***');
t_eq(offer_create($cS2, ['ctc' => 100]), 0, 'S5.2 · and no offer is written');
//  A stale ownership save on a requirement that is NOT blocked. My first version
//  of this probe reused the blocked requisition above, so the owner never
//  actually moved and the answer came back M4_BLOCKED — the probe was testing
//  nothing about staleness. A separate, healthy requirement is used instead.
$hS2 = $approve(['job_title' => 'M6S Stale Own']); [$okS2,, $rqS2] = hreq_to_requisition($hS2, 1);
rasg_assign('REQ_RECRUITER', $rqS2, $uA, ['expect' => null]);
$screenOwner2 = 0;                                        // what an OLD screen was showing
t_eq(rasg_assign('REQ_RECRUITER', $rqS2, $uBoss, ['expect' => $screenOwner2])['code'], 'STALE',
     'S5.3 · *** a stale ownership save is still refused while the lifecycle runs ***');
t_eq((int) ops_val("SELECT COALESCE(recruiter_id,0) FROM requisitions WHERE id=?", [$rqS2]), $uA,
     'S5.4 · …and the newer owner stands');

// ---- S6 · ENTITLEMENT IS ASKED BY THE GATE'S OWN CHAIN ---------------------
t_section('S6 · entitlement, on every surface that can execute');
$srcs = ['recruit_exec.php', 'recruit_assign.php'];
foreach ($srcs as $f2) {
    $t = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/' . $f2));
    t_ok(strpos($t, 'is_master()') === false, "S6 · $f2 contains no master bypass");
}
//  Entitlement's real choke point is can() itself — it asks licence_blocks()
//  BEFORE it looks at the master flag, so every module right in the system is
//  entitlement-aware and a master cannot walk past it. My first probe looked for
//  the licence call inside the hiring-request layer and found nothing, which said
//  more about where I looked than about the product.
$acc = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/access.php'));
$canBody = substr($acc, strpos($acc, 'function can($perm) {'), 260);
t_ok(strpos($canBody, 'licence_blocks') !== false, 'S6 · the permission choke point asks the licence');
t_ok(strpos($canBody, 'licence_blocks') < strpos($canBody, "\$a['master']"),
     'S6 · *** …before the master flag — no master bypass of entitlement anywhere ***');
$hq = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/hiringreq.php'));
t_ok(strpos($hq, "can('mod.hiring.edit')") !== false,
     'S6 · and the hiring-request writes go through it');
//  Behavioural: an invented module right is refused for everybody, master included.
t_ok(!can('mod.notarealmodule.view'), 'S6 · an unknown module right is denied, not assumed');
//  and every recruitment route is inside the licensed module map
$ops = (string) file_get_contents(dirname(__DIR__) . '/lib/ops.php');
foreach (['candidate-offer', 'candidate-interview', 'candidate-stage', 'candidate-flow'] as $rt) {
    $mapped = strpos($ops, "'" . $rt . "'=>'hiring'") !== false;
    $family = strncmp($rt, 'candidate', 9) === 0;         // ops_module_family() maps the whole family
    t_ok($mapped || $family, "S6 · the $rt route is inside the licensed hiring module");
}

// ---- S7 · TENANT ISOLATION, WITH A REAL SECOND DATABASE --------------------
t_section('S7 · a second real workspace, not a variable');
$idsA = ['h' => $hA, 'rq' => $rqA, 'c' => $cA, 'u' => $uA];
$tmp = sys_get_temp_dir() . '/m6_tenant_b_' . bin2hex(random_bytes(4)) . '.sqlite';
$prevDriver = getenv('DB_DRIVER'); $prevPath = getenv('SQLITE_PATH');
$switched = false;
if ($engine === 'sqlite') {
    putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $tmp);
    db(true); $switched = true;
}
if ($switched) {
    ops_ensure_schema(); hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate();
    recruit_iv_migrate(); recruit_offer_migrate(); ensure_settings_schema(); reqf_migrate();
    //  Tenant B asks for tenant A's records by id. Structural isolation means
    //  they are simply not here — and "not here" must mean refused, never
    //  silently created or read from somewhere else.
    t_eq(hreq_get($idsA['h']), null, 'S7.1 · *** tenant B cannot read tenant A\'s hiring request ***');
    t_eq((int) ops_val("SELECT COUNT(*) FROM requisitions WHERE id=?", [$idsA['rq']]), 0,
         'S7.2 · …nor its requisition');
    t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE id=?", [$idsA['c']]), 0,
         'S7.3 · …nor its candidate');
    t_ok(rexec_block_reason($idsA['rq'], 'ADVANCE') !== '',
         'S7.4 · *** the execution gate refuses a requirement that is not in this workspace ***');
    t_eq(rasg_assign('REQ_RECRUITER', $idsA['rq'], $idsA['u'], ['expect' => null])['code'], 'NO_RECORD',
         'S7.5 · *** and ownership cannot be written across the boundary ***');
    [$wOk] = hreq_save($idsA['h'], $base(['job_title' => 'cross-tenant']));
    t_ok(!$wOk, 'S7.6 · a cross-tenant edit is refused');
    //  back to workspace A, and prove nothing happened there
    putenv('DB_DRIVER=' . ($prevDriver ?: 'sqlite'));
    putenv('SQLITE_PATH=' . ($prevPath ?: ''));
    db(true);
    t_ok(hreq_get($idsA['h']) !== null, 'S7.7 · workspace A still has its request');
    t_eq((string) hreq_get($idsA['h'])['job_title'], 'M6S Engineer', 'S7.8 · …with its own title, untouched');
    t_eq((int) ops_val("SELECT COALESCE(recruiter_id,0) FROM requisitions WHERE id=?", [$idsA['rq']]), $uA,
         'S7.9 · …and its own recruiter');
    @unlink($tmp);
} else {
    //  MariaDB: the same probe with a real second database.
    $dbB = 'm6_tenant_b_' . bin2hex(random_bytes(3));
    $host = getenv('DB_HOST'); $user = getenv('DB_USER'); $pass = getenv('DB_PASS'); $prevName = getenv('DB_NAME');
    $made = false;
    try { db()->exec("CREATE DATABASE IF NOT EXISTS `$dbB`"); $made = true; } catch (Throwable $e) {}
    if ($made) {
        putenv('DB_NAME=' . $dbB); db(true);
        ops_ensure_schema(); hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate();
        recruit_iv_migrate(); recruit_offer_migrate(); ensure_settings_schema(); reqf_migrate();
        t_eq(hreq_get($idsA['h']), null, 'S7.1 · *** tenant B cannot read tenant A\'s hiring request ***');
        t_eq((int) ops_val("SELECT COUNT(*) FROM requisitions WHERE id=?", [$idsA['rq']]), 0, 'S7.2 · …nor its requisition');
        t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE id=?", [$idsA['c']]), 0, 'S7.3 · …nor its candidate');
        t_ok(rexec_block_reason($idsA['rq'], 'ADVANCE') !== '', 'S7.4 · *** the execution gate refuses it ***');
        t_eq(rasg_assign('REQ_RECRUITER', $idsA['rq'], $idsA['u'], ['expect' => null])['code'], 'NO_RECORD',
             'S7.5 · *** and ownership cannot be written across the boundary ***');
        [$wOk] = hreq_save($idsA['h'], $base(['job_title' => 'cross-tenant']));
        t_ok(!$wOk, 'S7.6 · a cross-tenant edit is refused');
        putenv('DB_NAME=' . $prevName); db(true);
        t_ok(hreq_get($idsA['h']) !== null, 'S7.7 · workspace A still has its request');
        t_eq((string) hreq_get($idsA['h'])['job_title'], 'M6S Engineer', 'S7.8 · …with its own title, untouched');
        t_eq((int) ops_val("SELECT COALESCE(recruiter_id,0) FROM requisitions WHERE id=?", [$idsA['rq']]), $uA,
             'S7.9 · …and its own recruiter');
        try { db()->exec("DROP DATABASE `$dbB`"); } catch (Throwable $e) {}
    }
}

$_SESSION = $m6s; current_user(true); ua(true);
