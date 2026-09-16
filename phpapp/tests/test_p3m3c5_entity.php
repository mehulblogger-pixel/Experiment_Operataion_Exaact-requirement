<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #5 — UNRESOLVED SOURCE ENTITY MUST FAIL CLOSED
//
//  G1  appr_visible() asked whether the entity TYPE was known and then resolved
//      the RECORD for the hiring request alone. A step whose offer, salary
//      structure or requisition had been deleted still produced recipients.
//      C2's rule had its unknown-type half implemented for every entity and its
//      missing-record half for one.
//
//      SUPPORTED TYPE  ≠  RESOLVED RECORD
//
//  The matrix below has a row for EACH HALF of that rule, for each entity —
//  which is the narrower lesson from the last audit, where a matrix built to stop
//  sibling omissions was itself missing a column.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #5 — unresolved source entity fails closed');

$pdo = db(); hreq_migrate(); appr_migrate(); recruit_offer_migrate();
$mine = ['u'=>[], 'o'=>[], 'rule'=>[], 'rq'=>[], 'cand'=>[], 'h'=>[]];
$origSess = $_SESSION;
$offWas = function_exists('setting_get') ? (string) setting_get('modules_off', '') : '';

try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (891,'C5 A',1)")->execute(); $mine['o'][]=891; } catch (Throwable $e) {}
$mk = function ($un,$role,$su,$off,$p,$em,$fn,$ln) use ($pdo,&$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions,email)
                   VALUES (?,?,?,?,1,?,?,'891',?,?)")->execute([$un,$fn,$ln,$role,$su?1:0,$off,$p,$em]);
    $id=(int)$pdo->lastInsertId(); $mine['u'][]=$id; return $id;
};
$act = function ($u) { $_SESSION['uid']=$u; current_user(true); ua(true); };
$HR = 'mod.hiring.view,mod.hiring.edit';
$uRaise = $mk('c5_raise','BRANCH_MANAGER',0,891,$HR,'c5raise@t.test','C5','Raiser');
$uAppr  = $mk('c5_appr','SBU_HEAD',0,891,$HR,'c5appr@t.test','C5','Approver');
$uMast  = $mk('c5_mast','ADMIN',1,891,'','c5mast@t.test','C5','Master');
$cfg = function (callable $fn) use ($uMast) {
    $p=$_SESSION['uid']??null; $_SESSION['uid']=$uMast; current_user(true); ua(true);
    try { return $fn(); } finally { if($p===null) unset($_SESSION['uid']); else $_SESSION['uid']=$p; current_user(true); ua(true); }
};
$apprRow = null;
$mails = fn() => (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval'");
$acts  = fn() => (int) ops_val("SELECT COUNT(*) FROM activities");

//  One live chain per entity, each with the same named approver.
$mkRule = function ($entity,$dept) use ($cfg,&$mine,$uAppr) {
    $id = $cfg(fn() => appr_rule_save(0, ['name'=>'C5 '.$entity,'entity'=>$entity,'code'=>'C5'.$entity,'applies_department'=>$dept]));
    $mine['rule'][]=$id;
    $cfg(fn() => appr_level_save(['rule_id'=>$id,'seq'=>1,'label'=>'H','approver_user_id'=>$uAppr,'sla_days'=>2,'reminder_days'=>1]));
    return $id;
};
$act($uRaise);
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,created_at) VALUES ('C5-C','C5','C','OFFERED',?)")->execute([date('c')]);
$cid=(int)$pdo->lastInsertId(); $mine['cand'][]=$cid;

$E = [];
$mkRule('HIRING_REQUEST','');
[$okH,,$h] = hreq_save(0,['job_title'=>'C5 hiring','quantity'=>1,'office_id'=>891,'priority'=>'NORMAL','approval_required'=>1]);
$mine['h'][]=$h; hreq_submit($h); $apH = hreq_approval($h); $mine['rq'][]=(int)$apH['id'];
$E['HIRING_REQUEST'] = ['req'=>$apH, 'rec'=>(int)$h, 'table'=>'hiring_requests'];

$mkRule('OFFER','C5Off');
$oid = offer_create($cid,['ctc'=>500000]);
[, $rqO] = appr_start('OFFER',$oid,['department'=>'C5Off','amount'=>500000],'C5 offer',500000);
$mine['rq'][]=(int)$rqO;
$E['OFFER'] = ['req'=>appr_request((int)$rqO), 'rec'=>$oid, 'table'=>'job_offers'];

$mkRule('SALARY','C5Sal');
$sid = sal_save($cid,['candidate_expected'=>100000]);
[, $rqS] = appr_start('SALARY',$sid,['department'=>'C5Sal','amount'=>0],'C5 salary',0);
$mine['rq'][]=(int)$rqS;
$E['SALARY'] = ['req'=>appr_request((int)$rqS), 'rec'=>$sid, 'table'=>'salary_structures'];

$mkRule('REQUISITION','C5Req');
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,status,created_at) VALUES ('C5-RQ','F','C5Req','OPEN',?)")->execute([date('c')]);
$rid=(int)$pdo->lastInsertId();
[, $rqR] = appr_start('REQUISITION',$rid,['department'=>'C5Req','amount'=>0],'C5 requisition',0);
$mine['rq'][]=(int)$rqR;
$E['REQUISITION'] = ['req'=>appr_request((int)$rqR), 'rec'=>$rid, 'table'=>'requisitions'];

t_eq(count($E), 4, 'C5.0 · a live chain and a real source record exist for all four entities');
$apprRow = ops_one("SELECT * FROM users WHERE id=?", [$uAppr]);
$act($uAppr);

// ---------------------------------------------------------------------------
//  C5.1 · A — valid type + valid record → existing behaviour (§12 protected)
// ---------------------------------------------------------------------------
t_section('C5.1 · A · a real record behaves exactly as before');
foreach ($E as $name => $x) {
    $step = appr_step_context(appr_current_step($x['req']), $x['req']);
    t_ok(appr_entity_record($name, $x['rec']) !== null, "C5.1 A · $name · the source record resolves");
    t_eq(appr_told_reason($x['req'], $apprRow), '', "C5.1 A · $name · the informational level allows it");
    t_ok(appr_may_be_asked($step, $x['req'], $apprRow), "C5.1 A · $name · the actionable level allows it");
    $to = appr_step_recipients($step, $x['req']);
    t_eq(count($to), 1, "C5.1 A · $name · exactly one recipient");
    t_eq($to[0] ?? '', 'c5appr@t.test', "C5.1 A · $name · and it is the right one");
    //  §12 — the decision notification still reaches the canonical raiser.
    $b = (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr='c5raise@t.test'");
    appr_email_requester($x['req'], 'approved', '');
    t_eq((int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr='c5raise@t.test'"), $b + 1,
         "C5.1 A · $name · §12 · a VALID entity still notifies its raiser — INVALID→DENY did not become VALID→DENY");
}

// ---------------------------------------------------------------------------
//  C5.2 · B/C/D/E/F — every way an entity can fail to resolve
// ---------------------------------------------------------------------------
t_section('C5.2 · B–F · unresolved, invalid, cross-tenant, unknown, malformed');
$foreign = 99300000 + random_int(1,999);
foreach ($E as $name => $x) {
    $step = appr_step_context(appr_current_step($x['req']), $x['req']);
    $cases = [
        ['C · id 0',        ['entity'=>$name, 'entity_id'=>0]],
        ['C · negative id', ['entity'=>$name, 'entity_id'=>-7]],
        ['D · cross-tenant id', ['entity'=>$name, 'entity_id'=>$foreign]],
        ['E · unknown type',    ['entity'=>'NOT_A_THING', 'entity_id'=>$x['rec']]],
        ['F · blank type',      ['entity'=>'', 'entity_id'=>$x['rec']]],
        ['F · malformed ref',   ['entity'=>$name]],
    ];
    foreach ($cases as [$label,$bad]) {
        $m0 = $mails(); $a0 = $acts();
        t_ok(appr_told_reason($bad, $apprRow) !== '', "C5.2 $label · $name · informational DENIES");
        t_ok(!appr_may_be_asked($step, $bad, $apprRow),  "C5.2 $label · $name · actionable DENIES");
        t_eq(count(appr_step_recipients($step, $bad)), 0, "C5.2 $label · $name · no recipient");
        t_eq($mails(), $m0, "C5.2 $label · $name · nothing sent, nothing logged as sent");
    }
    //  D · a cross-tenant id names a record that does not exist in THIS database —
    //  isolation here is structural, one database per tenant, and it is asserted.
    t_ok(!ops_one("SELECT id FROM " . $x['table'] . " WHERE id=?", [$foreign]),
         "C5.2 D · $name · the foreign id does not exist in this workspace's " . $x['table']);
    t_ok(appr_entity_record($name, $foreign) === null, "C5.2 D · $name · so it cannot resolve");
}

// ---------------------------------------------------------------------------
//  C5.3 · B — THE DEFECT: the record is deleted, the chain survives
// ---------------------------------------------------------------------------
t_section('C5.3 · B · a deleted source record denies, for every entity');
foreach ($E as $name => $x) {
    $step = appr_step_context(appr_current_step($x['req']), $x['req']);
    t_ok(appr_may_be_asked($step, $x['req'], $apprRow), "C5.3 · $name · eligible while the record exists");
    $pdo->prepare("DELETE FROM " . $x['table'] . " WHERE id=?")->execute([(int)$x['rec']]);
    $m0 = $mails(); $st0 = (string) ops_val("SELECT status FROM recruit_approval_steps WHERE request_id=?", [(int)$x['req']['id']]);
    t_ok(appr_entity_record($name, $x['rec']) === null,  "C5.3 B · $name · the record no longer resolves");
    t_eq(appr_told_reason($x['req'], $apprRow), 'ENTITY_UNRESOLVED',
         "C5.3 B · $name · informational DENIES with ENTITY_UNRESOLVED — not IDENTITY_UNRESOLVED");
    t_ok(!appr_may_be_asked($step, $x['req'], $apprRow), "C5.3 B · $name · actionable DENIES");
    t_eq(count(appr_step_recipients($step, $x['req'])), 0, "C5.3 B · $name · NO RECIPIENT — the defect is closed");
    t_eq(appr_email_requester($x['req'],'approved',''), 'ENTITY_UNRESOLVED', "C5.3 B · $name · the decision notifier denies too");
    t_eq($mails(), $m0, "C5.3 B · $name · nothing was sent");
    t_eq((string) ops_val("SELECT status FROM recruit_approval_steps WHERE request_id=?", [(int)$x['req']['id']]), $st0,
         "C5.3 B · $name · and no approval state changed");
    //  the scheduler is quiet about it too
    $pdo->prepare("UPDATE recruit_approval_steps SET reminder_at=?, sla_due=? WHERE request_id=?")
        ->execute([date('c',time()-86400), date('c',time()-86400), (int)$x['req']['id']]);
    $_SESSION = $origSess; current_user(true); ua(true);
    $m1 = $mails(); appr_tick();
    t_eq($mails(), $m1, "C5.3 B · $name · and the scheduler sends nothing about it either");
    $act($uAppr);
}

// ---------------------------------------------------------------------------
//  C5.4 · the appr_can_act() rescue path is gone
// ---------------------------------------------------------------------------
t_section('C5.4 · a missing record never falls through to appr_can_act()');
foreach ($E as $name => $x) {
    $step = appr_step_context(appr_current_step($x['req']), $x['req']);
    t_ok(appr_can_act($step, $apprRow), "C5.4 · $name · the approver still genuinely passes appr_can_act()");
    t_ok(!appr_visible($step, $x['req'], $apprRow), "C5.4 · $name · yet visibility DENIES — no generic rescue");
}

// ---------------------------------------------------------------------------
//  C5.5 · D2/D3 — reasons stay distinct, audit stays clean
// ---------------------------------------------------------------------------
t_section('C5.5 · reasons and audit integrity');
t_eq(appr_told_reason(['entity'=>'HIRING_REQUEST','entity_id'=>$foreign], $apprRow), 'ENTITY_UNRESOLVED',
     'C5.5 · an unresolved ENTITY is ENTITY_UNRESOLVED');
$dangling = (int) ops_val("SELECT COUNT(*) FROM activities WHERE (entity_kind IS NULL OR entity_kind='') AND subject LIKE '%Decision not notified%'");
t_eq($dangling, 0, 'C5.5 · and no dangling audit reference was created along the way');

// ---------------------------------------------------------------------------
//  C5.6 · a resolution that ERRORS is a resolution that FAILED
// ---------------------------------------------------------------------------
//  The resolver's catch denies, and nothing in the suite was making it throw — so
//  the condition is CONSTRUCTED rather than assumed: the source table is renamed
//  out from under it, which is what a half-applied migration or a partial restore
//  looks like. Renamed back in a finally, so a failure here cannot damage the
//  files that run after this one.
t_section('C5.6 · a resolver that errors denies');
$act($uAppr);
$salReq = ['entity' => 'SALARY', 'entity_id' => 4242, 'subject' => 'x'];
try {
    $pdo->exec("ALTER TABLE salary_structures RENAME TO salary_structures_c5tmp");
    t_ok(appr_entity_record('SALARY', 4242) === null,
         'C5.6 · with its source table gone, the resolver returns nothing rather than raising');
    t_eq(appr_told_reason($salReq, $apprRow), 'ENTITY_UNRESOLVED',
         'C5.6 · and the notification DENIES — an error is never an eligibility');
} finally {
    try { $pdo->exec("ALTER TABLE salary_structures_c5tmp RENAME TO salary_structures"); } catch (Throwable $e) {}
}
t_ok(t_table_exists('salary_structures'), 'C5.6 · the table is put back');

// ---------------------------------------------------------------------------
//  C5.7 · D — A GENUINE SECOND TENANT
//
//  C5.2 D proved that a foreign id does not resolve. It could not prove WHY:
//  an id that exists nowhere is refused by every code path, correct or not.
//  The real §4 question is narrower and harder —
//
//      the SAME id is a live, resolvable record in tenant B.
//      Does tenant A still refuse it?
//
//  Isolation here is structural (one database per tenant, no tenant_id column),
//  so the only honest way to ask is to stand up a second workspace. Tenant B is
//  booted in a CLEAN CHILD PROCESS — exactly what a company's first web request
//  is in production — so it cannot borrow this process's connection, its
//  migration guards or its settings cache. It reports back over JSON.
// ---------------------------------------------------------------------------
t_section('C5.7 · D · a genuine second tenant — the same id, a different workspace');
$act($uAppr);
$c5root  = dirname(__DIR__);
$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
$canExec  = function_exists('exec') && !in_array('exec', $disabled, true);
t_ok($canExec, 'C5.7 · a second workspace can be booted in a clean process');

$shared = [
    'HIRING_REQUEST' => [99400001, 'hiring_requests'],
    'OFFER'          => [99400002, 'job_offers'],
    'SALARY'         => [99400003, 'salary_structures'],
    'REQUISITION'    => [99400004, 'requisitions'],
];
//  The proof only means something if these ids are absent HERE to begin with.
foreach ($shared as $name => [$sid2, $tbl]) {
    t_ok(!ops_one("SELECT id FROM " . $tbl . " WHERE id=?", [$sid2]),
         "C5.7 · $name · id $sid2 does not exist in tenant A's " . $tbl);
}

if ($canExec) {
    $bFile = ''; $bName = ''; $drv = db_driver();
    $env = 'APP_ROOT=' . escapeshellarg($c5root)
         . ' C5_IDS=' . escapeshellarg('99400001,99400002,99400003,99400004')
         . ' C5_CHAINS=' . escapeshellarg(implode(',', array_map('intval', array_unique($mine['rq']))));
    if ($drv === 'sqlite') {
        $bFile = sys_get_temp_dir() . '/mgh_c5b_' . getmypid() . '.sqlite';
        @unlink($bFile);
        $env .= ' DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg($bFile);
    } else {
        $bName = 'exaact_c5b_' . getmypid();
        $pdo->exec("DROP DATABASE IF EXISTS `" . $bName . "`");
        $pdo->exec("CREATE DATABASE `" . $bName . "` CHARACTER SET utf8mb4");
        $host = (string) getenv('DB_HOST'); if ($host === '') $host = '127.0.0.1';
        $env .= ' DB_DRIVER=mysql DB_HOST=' . escapeshellarg($host)
              . ' DB_NAME=' . escapeshellarg($bName)
              . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
              . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    }

    $runner = sys_get_temp_dir() . '/mgh_c5b_runner_' . getmypid() . '.php';
    file_put_contents($runner, <<<'CHILD'
<?php
//  TENANT B. A clean process, its own database, nothing shared with tenant A
//  but the code itself.
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = getenv('APP_ROOT');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'c5b';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = '';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \\. '(/lib/[a-z0-9_]+\\.php)';#i", $idx, $mm);
foreach ($mm[1] as $rel) { require_once $root . $rel; }
boot();
hreq_migrate(); recruit_offer_migrate(); appr_migrate();
[$h, $o, $s, $r] = array_map('intval', explode(',', (string) getenv('C5_IDS')));
$pdo = db();
$pdo->prepare("INSERT INTO hiring_requests (id,job_title,status) VALUES (?,?,'SUBMITTED')")->execute([$h, 'TENANT B hiring']);
$pdo->prepare("INSERT INTO job_offers (id,candidate_id,ctc,status) VALUES (?,0,1,'DRAFT')")->execute([$o]);
$pdo->prepare("INSERT INTO salary_structures (id,candidate_id) VALUES (?,0)")->execute([$s]);
$pdo->prepare("INSERT INTO requisitions (id,req_code,designation,status) VALUES (?,'TB-RQ','F','OPEN')")->execute([$r]);
$chains = preg_replace('/[^0-9,]/', '', (string) getenv('C5_CHAINS'));
if ($chains === '' ) $chains = '0';
$cfg = require $root . '/config.php';
echo json_encode([
    //  J2 — the runtime database this process is ACTUALLY connected to, read
    //  from the live connection rather than inferred from what it was handed.
    'db'       => (db_driver() === 'sqlite') ? (string) $cfg['sqlite_path'] : (string) ops_val("SELECT DATABASE()"),
    'resolved' => [
        'HIRING_REQUEST' => appr_entity_record('HIRING_REQUEST', $h) !== null,
        'OFFER'          => appr_entity_record('OFFER', $o)          !== null,
        'SALARY'         => appr_entity_record('SALARY', $s)         !== null,
        'REQUISITION'    => appr_entity_record('REQUISITION', $r)    !== null,
    ],
    'title'    => (string) (appr_entity_record('HIRING_REQUEST', $h)['job_title'] ?? ''),
    'a_chains' => (int) ops_val("SELECT COUNT(*) FROM recruit_approval_requests WHERE id IN ($chains)"),
    'a_people' => (int) ops_val("SELECT COUNT(*) FROM users WHERE email IN ('c5raise@t.test','c5appr@t.test')"),
]);
CHILD);

    $php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $out = []; $rc = 1;
    try {
        @exec($env . ' ' . escapeshellarg($php) . ' ' . escapeshellarg($runner) . ' 2>&1', $out, $rc);
        $res = json_decode((string) end($out), true);
        t_ok(is_array($res), 'C5.7 · tenant B booted its own database and reported back');

        if (is_array($res)) {
            //  J2 — ESTABLISH THE MAPPING FIRST. Before a single security claim,
            //  prove tenant A and tenant B are connected to different databases,
            //  each read from its own live connection. A teardown observed after
            //  DROP DATABASE proves nothing about runtime isolation; this does.
            $cfgA = require $c5root . '/config.php';
            $dbA  = (db_driver() === 'sqlite') ? (string) $cfgA['sqlite_path'] : (string) ops_val("SELECT DATABASE()");
            $dbB  = (string) ($res['db'] ?? '');
            t_ok($dbA !== '', 'C5.7 · J2 · tenant A names the database it is really connected to');
            t_ok($dbB !== '', 'C5.7 · J2 · tenant B names the database it is really connected to');
            t_ok($dbA !== $dbB, 'C5.7 · J2 · THEY ARE DIFFERENT DATABASES — checked before any security assertion');
            t_eq($dbB, $drv === 'sqlite' ? $bFile : $bName, 'C5.7 · J2 · and tenant B is on the workspace this test created');

            t_eq((string) $res['title'], 'TENANT B hiring', 'C5.7 · tenant B\'s record is its own, not a copy of A\'s');
            //  The other direction: tenant A's chains and tenant A's PEOPLE are
            //  not in tenant B either. Identity is per-workspace too.
            t_eq((int) $res['a_chains'], 0, 'C5.7 · tenant A\'s approval chains do not exist in tenant B');
            t_eq((int) $res['a_people'], 0, 'C5.7 · tenant A\'s approver and raiser do not exist in tenant B');

            foreach ($shared as $name => [$sid2, $tbl]) {
                $step = appr_step_context(appr_current_step($E[$name]['req']), $E[$name]['req']);
                $bad  = ['id' => 0, 'entity' => $name, 'entity_id' => $sid2, 'requester_id' => $uRaise, 'subject' => 'cross'];
                $m0 = $mails(); $a0 = $acts();

                //  (i) the id is REAL somewhere — this is isolation, not a bad id
                t_ok(!empty($res['resolved'][$name]), "C5.7 · $name · id $sid2 IS a live, resolvable record in tenant B");
                //  (ii) and tenant A still refuses it
                t_ok(appr_entity_record($name, $sid2) === null, "C5.7 · $name · tenant A cannot resolve it");
                t_eq(appr_told_reason($bad, $apprRow), 'ENTITY_UNRESOLVED',
                     "C5.7 · $name · informational DENIES — ENTITY_UNRESOLVED, not a leak");
                t_ok(!appr_may_be_asked($step, $bad, $apprRow), "C5.7 · $name · actionable DENIES");
                t_eq(count(appr_step_recipients($step, $bad)), 0, "C5.7 · $name · nobody is asked about another workspace's record");
                t_eq(appr_email_requester($bad, 'approved', ''), 'ENTITY_UNRESOLVED',
                     "C5.7 · $name · the decision notifier denies too");
                t_eq($mails(), $m0, "C5.7 · $name · nothing was sent");
                //  J1 (correction #6) — this synthetic chain carries no rule_id,
                //  so NO openable subject remains and the correct outcome is no
                //  row at all. Until correction #6 a HIRING_REQUEST or REQUISITION
                //  refusal wrote a permanent audit row pointing at ANOTHER
                //  WORKSPACE'S id — a supported type with an unopenable target.
                t_eq($acts(), $a0, "C5.7 · $name · no audit row — nothing openable remains to file it under");
                t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind=? AND entity_id=?", [$name, $sid2]),
                     0, "C5.7 · $name · and NOTHING references another workspace's id");
            }
        }
    } finally {
        @unlink($runner);
        if ($bFile !== '') @unlink($bFile);
        if ($bName !== '') { try { $pdo->exec("DROP DATABASE IF EXISTS `" . $bName . "`"); } catch (Throwable $e) {} }
    }
    //  J2 — the old form read `$bFile === '' || !is_file($bFile)`, which on
    //  MariaDB (where tenant B is a DATABASE, not a file) is `t_ok(true)`: a pass
    //  reported for something never checked, on the engine that matters. Each
    //  engine now answers for itself.
    if ($drv === 'sqlite') {
        t_ok(!is_file($bFile), 'C5.7 · tenant B\'s database FILE is gone');
    } else {
        t_eq((int) ops_val("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?", [$bName]), 0,
             'C5.7 · tenant B\'s DATABASE is gone from information_schema');
    }
}

// ---------------------------------------------------------------------------
//  C5.8 · J3 — WHICH LAYER ACTUALLY DENIED?
//
//  The last audit showed that for HIRING_REQUEST the missing-record denial is
//  ALSO covered by the branch-scope rule, so a boolean "denied" could not say
//  which layer fired. The two protections are separated here, each proved on a
//  case where the other cannot be the cause. Neither layer is removed.
// ---------------------------------------------------------------------------
t_section('C5.8 · J3 · entity-resolution and branch-scope, proved separately');
$act($uAppr);
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (892,'C5 B',1)")->execute(); $mine['o'][]=892; } catch (Throwable $e) {}
$mkHR = function ($office) use ($pdo, $uRaise, &$mine) {
    //  req_no is UNIQUE — a direct insert must carry its own, or the second one
    //  collides with the first on the blank default.
    $pdo->prepare("INSERT INTO hiring_requests (req_no,job_title,status,office_id,requested_by_id,quantity,created_at)
                   VALUES (?,'C5 J3',?,?,?,1,?)")
        ->execute(['C5J3-' . $office . '-' . random_int(100000, 999999), 'SUBMITTED', (int)$office, (int)$uRaise, date('c')]);
    $id = (int) $pdo->lastInsertId(); $mine['h'][] = $id; return $id;
};
//  (B) SCOPE, isolated: the record EXISTS, so entity resolution cannot be the
//      cause. Only the branch rule can deny — and it names itself.
$far = $mkHR(892);
t_ok(appr_entity_record('HIRING_REQUEST', $far) !== null, 'C5.8 B · the far-branch record genuinely exists');
t_eq(appr_told_reason(['entity'=>'HIRING_REQUEST','entity_id'=>$far], $apprRow), 'RECIPIENT_OUT_OF_SCOPE',
     'C5.8 B · SCOPE denies it, and says so — not ENTITY_UNRESOLVED');

//  (A) ENTITY RESOLUTION, isolated: the same approver, a record in their OWN
//      branch — scope is first shown to ALLOW it, so when the record is deleted
//      the only remaining cause of denial is that it no longer resolves.
$near = $mkHR(891);
$stepJ3 = appr_step_context(appr_current_step($E['HIRING_REQUEST']['req']), $E['HIRING_REQUEST']['req']);
$reqNear = ['entity'=>'HIRING_REQUEST','entity_id'=>$near];
t_eq(appr_told_reason($reqNear, $apprRow), '', 'C5.8 A · scope ALLOWS this record — the branch rule is not in play');
t_ok(appr_may_be_asked($stepJ3, $reqNear, $apprRow), 'C5.8 A · and the approver may be asked about it');
$pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([$near]);
t_ok(appr_entity_record('HIRING_REQUEST', $near) === null, 'C5.8 A · now the record is gone');
t_eq(appr_told_reason($reqNear, $apprRow), 'ENTITY_UNRESOLVED',
     'C5.8 A · ENTITY RESOLUTION denies it — scope had just allowed the very same subject');
t_ok(!appr_may_be_asked($stepJ3, $reqNear, $apprRow),
     'C5.8 A · the actionable path denies for the entity reason alone — no scope protection behind it');
t_ok(appr_told_reason(['entity'=>'HIRING_REQUEST','entity_id'=>$far], $apprRow)
     !== appr_told_reason($reqNear, $apprRow),
     'C5.8 · the two layers give DIFFERENT answers — the reason discriminates, the boolean did not');

// ---------------------------------------------------------------------------
//  Clean up
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
if (function_exists('setting_set')) { setting_set('modules_off', $offWas); licence_disabled(true); ua(true); }
foreach (array_unique($mine['rq']) as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['rule'] as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['h'] as $x) $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int)$x]);
foreach ($mine['cand'] as $x) $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([(int)$x]);
foreach ($mine['u'] as $x) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$x]);
foreach ($mine['o'] as $x) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([(int)$x]);
$pdo->exec("DELETE FROM email_log WHERE kind='recruit_approval'");
//  J4 — this suite writes to the activity spine (audit rows for refused
//  notifications, SLA events). They were left behind for every later file in the
//  same process to see. Remove exactly the ones belonging to this suite's
//  fixtures, by id — never a LIKE sweep over somebody else's history.
foreach (array_unique(array_merge($mine['h'], [99400001])) as $x)
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?")->execute([(int)$x]);
foreach (array_unique([$rid, 99400004]) as $x)
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='REQUISITION' AND entity_id=?")->execute([(int)$x]);
foreach (array_unique($mine['rule']) as $x)
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?")->execute([(int)$x]);
$leftC5 = (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id IN (99400001)")
        + (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='REQUISITION' AND entity_id IN (99400004)");
t_eq($leftC5, 0, 'C5 · J4 · the cross-tenant audit rows this suite created are cleaned up');
t_ok(true, 'M3 correction #5 fixtures removed');
