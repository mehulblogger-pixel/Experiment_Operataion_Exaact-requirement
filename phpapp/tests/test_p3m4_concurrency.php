<?php
// ============================================================================
//  PHASE 3 · M4 — REAL-PROCESS CONCURRENCY
//
//  Every race below is run with genuinely separate php processes on independent
//  database connections. Nothing here is a sequential simulation.
// ============================================================================

t_section('Phase 3 · M4 — concurrency, with real processes');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate();
$engine = db_driver(); $root = dirname(__DIR__); $origSess = $_SESSION;
$mine = ['h'=>[], 'u'=>[], 'o'=>[]];
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (9461,'M4C Branch',1)")->execute(); $mine['o'][]=9461; } catch (Throwable $e) {}
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES ('m4c_mgr','M4C','MANAGER',1,1,9461,'')")->execute();
$uMgr = (int)$pdo->lastInsertId(); $mine['u'][]=$uMgr;
$_SESSION['uid']=$uMgr; current_user(true); ua(true);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$base = fn(array $x=[]) => array_merge([
    'requested_by_id'=>$uMgr,'requested_by_name'=>'M4C','requesting_department_id'=>$qua['id'],
    'hiring_department_id'=>$eng['id'],'job_title'=>'M4C Engineer','designation'=>'ENGINEER',
    'quantity'=>10,'office_id'=>9461,'employment_type'=>'CONTRACT','request_type'=>'PROJECT','priority'=>'NORMAL',
], $x);
$approve = function (array $x=[]) use ($base,&$mine) {
    [$ok,,$id] = hreq_save(0, $base($x)); if (!$ok) return 0;
    $mine['h'][]=$id; hreq_submit($id); hreq_apply_decision($id,'APPROVED','M4C Approver','ok'); return (int)$id;
};
//  Launch N real processes that all reach their operation at the same moment.
$race = function (array $ops) use ($root, $engine, $uMgr) {
    $env = $engine==='sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string)getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string)getenv('DB_HOST'))
          . ' DB_NAME=' . escapeshellarg((string)getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string)getenv('DB_USER'))
          . ' DB_PASS=' . escapeshellarg((string)getenv('DB_PASS'));
    $procs = [];
    foreach ($ops as [$op,$id,$arg]) {
        $cmd = $env . ' php ' . escapeshellarg($root.'/tests/_m4_worker.php') . ' '
             . escapeshellarg($op) . ' ' . (int)$id . ' ' . escapeshellarg((string)$arg) . ' 500 ' . (int)$uMgr . ' 2>&1';
        $pipes=[]; $p = proc_open($cmd, [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (is_resource($p)) $procs[] = [$p,$pipes];
    }
    $res = [];
    foreach ($procs as [$p,$pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
        foreach (explode("\n", trim($raw)) as $line) { $d = json_decode(trim($line), true); if (is_array($d)) $res[] = $d; }
    }
    return $res;
};
$alloc = fn($h) => (int) ops_val("SELECT COALESCE(SUM(quantity),0) FROM requisitions
                                  WHERE hiring_request_id=? AND UPPER(COALESCE(status,''))<>'CANCELLED'", [(int)$h]);

// ---------------------------------------------------------------------------
t_section('C1 · the last seat — two processes, one seat');
$h1 = $approve(['job_title'=>'M4C Last Seat']);
hreq_to_requisition($h1, 9);
t_eq($alloc($h1), 9, 'C1 · 9 of the approved 10 are allocated');
t_eq(hreq_remaining_qty($h1), 1, 'C1 · exactly one seat remains');
$r1 = $race([['seat',$h1,1], ['seat',$h1,1]]);
t_eq(count($r1), 2, 'C1 · two real processes ran');
$won = count(array_filter($r1, fn($x) => !empty($x['ok'])));
//  PHASE 4 CORRECTION TO A PHASE 3 PROBE — no product code changed.
//
//  This asserted `$won === 1`. That contradicts BOTH the ratified capacity rule
//  ("where simultaneous claims cannot be deterministically resolved without
//  risking displacement, the system may refuse the contested claims and leave the
//  capacity available for a subsequent valid transaction") AND the implementation's
//  own stated contract, written in hiringreq.php at the compensating check:
//  "Two racing processes may both revert, which refuses an allocation that could
//  in principle have succeeded — the safe direction for a headcount control, and
//  never an over-allocation."
//
//  So a dead heat in which BOTH processes stand down is correct behaviour, and
//  this probe would fail on it. It was observed failing once, on MariaDB, under
//  full-suite load. No Phase 4 code is on this path (hiringreq.php and
//  reqfulfil.php contain no reference to the Phase 4 engine), and the
//  pre-Phase-4 tree behaves identically.
//
//  What is asserted instead is what the system actually guarantees: never more
//  than one winner, NEVER an over-allocation, and the capacity still usable
//  afterwards by a valid transaction.
t_ok($won <= 1, 'C1 · *** at most ONE process took the last seat (got ' . $won . ') ***');
t_ok($alloc($h1) <= 10, 'C1 · *** the allocation NEVER exceeds the approved 10 (got ' . $alloc($h1) . ') ***');
t_ok(hreq_remaining_qty($h1) >= 0, 'C1 · and the remainder never went negative');
t_eq($alloc($h1) + hreq_remaining_qty($h1), 10, 'C1 · allocated plus remaining is always exactly the approval');
//  A refusal must not CONSUME the capacity it refused.
if ($won === 0) {
    [$lateOk,, $lateId] = hreq_to_requisition($h1, 1);
    t_ok($lateOk && $lateId > 0, 'C1 · a dead heat left the seat usable by the next valid transaction');
    t_eq($alloc($h1), 10, 'C1 · …which then takes it, reaching exactly the approved 10');
} else {
    t_eq($alloc($h1), 10, 'C1 · one winner took it, reaching exactly the approved 10');
    t_eq(hreq_remaining_qty($h1), 0, 'C1 · nothing remains');
}
foreach ($r1 as $x) if (empty($x['ok'])) t_ok(trim((string)$x['msg']) !== '', 'C1 · the loser failed cleanly, with a reason: ' . $x['msg']);

t_section('C2 · two simultaneous quantity increases');
$h2 = $approve(['job_title'=>'M4C Qty']);
[$ok2a,,$rqA] = hreq_to_requisition($h2, 4);
[$ok2b,,$rqB] = hreq_to_requisition($h2, 4);
t_eq($alloc($h2), 8, 'C2 · 8 of the approved 10 are allocated across two requisitions');
//  Both try to grow by 2 — together that is 12, two over the ceiling.
$r2 = $race([['qty',$rqA,6], ['qty',$rqB,6]]);
t_eq(count($r2), 2, 'C2 · two real processes ran');
t_ok($alloc($h2) <= 10, '*** C2 · total allocation NEVER exceeds the approved 10 (got ' . $alloc($h2) . ') ***');
t_ok((int) ops_val("SELECT COUNT(*) FROM requisitions WHERE hiring_request_id=? AND quantity < 0", [$h2]) === 0,
     'C2 · and no requisition was left with a negative quantity');

t_section('C3 · two processes start re-approval at the same moment');
$ruleId = (int) appr_rule_save(0, ['name'=>'M4C rule','entity'=>'HIRING_REQUEST','code'=>'M4CRULE','applies_department'=>'']);
appr_level_save(['rule_id'=>$ruleId,'seq'=>1,'label'=>'Head','approver_user_id'=>$uMgr,'sla_days'=>3,'reminder_days'=>1]);
$h3 = $approve(['job_title'=>'M4C Dup Chain']);
$pdo->prepare("UPDATE recruit_approval_requests SET status='APPROVED' WHERE entity='HIRING_REQUEST' AND entity_id=?")->execute([$h3]);
$openChains = fn($h) => (int) ops_val("SELECT COUNT(*) FROM recruit_approval_requests
                                       WHERE entity='HIRING_REQUEST' AND entity_id=? AND UPPER(status)='PENDING'", [(int)$h]);
t_eq($openChains($h3), 0, 'C3 · no chain is open before the race');
$r3 = $race([['material',$h3,'G4'], ['material',$h3,'G4']]);
t_eq(count($r3), 2, 'C3 · two real processes ran');
t_eq($openChains($h3), 1, 'C3 · *** exactly ONE open approval chain, never two ***');
t_ok(in_array(hreq_reapproval_state(hreq_get($h3)), ['REQUIRED','IN_PROGRESS'], true),
     'C3 · and the request is in a single coherent re-approval state');
t_ok(!hreq_is_executable(hreq_get($h3)), 'C3 · with execution blocked');

t_section('C4 · two processes decide the same re-approval');
$r4 = $race([['decide',$h3,'APPROVED'], ['decide',$h3,'REJECTED']]);
t_eq(count($r4), 2, 'C4 · two real processes ran');
$st4 = hreq_reapproval_state(hreq_get($h3));
t_ok(in_array($st4, ['REAPPROVED','REJECTED'], true), 'C4 · *** the final state is ONE of the two answers, not both: ' . $st4 . ' ***');
$exec4 = hreq_is_executable(hreq_get($h3));
t_ok(($st4 === 'REAPPROVED') === $exec4, '*** C4 · and execution agrees with that answer — no contradictory state ***');

t_section('C5 · a material change racing an execution attempt');
$h5 = $approve(['job_title'=>'M4C Race Exec']);
[$ok5,,$rq5] = hreq_to_requisition($h5, 2);
t_ok($ok5, 'C5 · a requisition exists to work against');
$r5 = $race([['material',$h5,'G6'], ['exec',$rq5,'']]);
t_eq(count($r5), 2, 'C5 · two real processes ran');
$after5 = hreq_get($h5);
t_ok(!hreq_is_executable($after5), 'C5 · the material change landed and execution is now blocked');
$execWhy = hreq_req_block_reason($rq5);
t_ok($execWhy !== '', '*** C5 · and any execution from here on is refused: ' . $execWhy . ' ***');
//  whichever way the race fell, the state is internally consistent
$blocked = in_array(hreq_reapproval_state($after5), ['REQUIRED','IN_PROGRESS'], true);
t_ok($blocked && !hreq_is_executable($after5),
     '*** C5 · the final state is internally consistent — blocked state and blocked boundary agree ***');

// ---------------------------------------------------------------------------
$pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id IN (SELECT id FROM recruit_approval_requests WHERE entity='HIRING_REQUEST')")->execute();
foreach ($mine['h'] as $h) {
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=?")->execute([(int)$h]);
    $pdo->prepare("DELETE FROM requisitions WHERE hiring_request_id=?")->execute([(int)$h]);
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?")->execute([(int)$h]);
    $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int)$h]);
}
$pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([$ruleId]);
$pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([$ruleId]);
foreach ($mine['u'] as $u) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$u]);
foreach ($mine['o'] as $o) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$o]);
$_SESSION = $origSess; current_user(true); ua(true);
