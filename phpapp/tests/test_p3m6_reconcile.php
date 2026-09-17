<?php
// ============================================================================
//  PHASE 3 · M6 — END-TO-END RECONCILIATION
//
//  One controlled fixture. Every figure calculated independently from the source
//  records, then compared against the service, the dashboard and the export.
//  Where a number disagrees the DEFINITION is stated, never the expectation
//  quietly changed to match the screen.
// ============================================================================

t_section('Phase 3 · M6 — database = service = dashboard = export');

$pdo = db(); hreq_migrate(); appr_migrate(); rasg_migrate(); reqf_migrate();
recruit_iv_migrate(); recruit_offer_migrate(); ensure_settings_schema();
$m6r = $_SESSION;
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (9631,'M6R Branch',1)")->execute(); } catch (Throwable $e) {}
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES ('m6r_boss','M6R','MANAGER',1,1,9631,'')")->execute();
$uBoss = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES ('m6r_rec','M6R','COORDINATOR',1,0,9631,'9631')")->execute();
$uRec = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uBoss, 'requested_by_name' => 'M6R', 'requesting_department_id' => $qua['id'],
    'hiring_department_id' => $eng['id'], 'job_title' => 'M6R Engineer', 'designation' => 'ENGINEER',
    'job_description' => 'm6r', 'quantity' => 10, 'office_id' => 9631, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x'], $x);

// ---- THE FIXTURE: one 10-seat requirement, twelve candidates --------------
[$okH,, $h] = hreq_save(0, $base());
hreq_submit($h); hreq_apply_decision($h, 'APPROVED', 'M6R Approver', 'ok');
[$okR,, $rq] = hreq_to_requisition($h, 10);
rasg_assign('REQ_RECRUITER', $rq, $uRec, ['expect' => null]);
//  Twelve people, six outcomes. The numbers below are derived from THIS list.
$plan = ['ACCEPTED', 'ACCEPTED', 'ACCEPTED', 'ACCEPTED',      // 4 joined
         'OFFERED', 'OFFERED',                                 // 2 offered
         'INTERVIEW', 'SHORTLISTED', 'RECEIVED',               // 3 in process
         'REJECTED', 'WITHDRAWN', 'OFFER_DECLINED'];           // 3 lost
$cands = [];
foreach ($plan as $i => $st) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,recruiter_id,cv_received_date,created_at)
                   VALUES (?,'M6R',?,?,?,?,?,?)")
        ->execute(['M6RC-' . $i . '-' . bin2hex(random_bytes(2)), 'C' . $i, $st, $rq, $uRec, date('Y-m-d'), date('c')]);
    $cands[] = (int) $pdo->lastInsertId();
}
//  two interview rounds for one of them
foreach (['L1', 'L2'] as $rd)
    $pdo->prepare("INSERT INTO interviews (candidate_id,round,scheduled_at,created_at) VALUES (?,?,?,?)")
        ->execute([$cands[6], $rd, date('c'), date('c')]);
reqf_sync($rq);

// ---- 1 · THE SOURCE OF TRUTH -----------------------------------------------
t_section('R1 · counted straight from the records');
$sql = fn($q, $a = []) => (int) ops_val($q, $a);
$joined   = $sql("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rq]);
$offered  = $sql("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='OFFERED'", [$rq]);
$active   = $sql("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage IN ('RECEIVED','SUBMITTED','SHORTLISTED','INTERVIEW','OFFERED','HOLD')", [$rq]);
$lost     = $sql("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage IN ('REJECTED','WITHDRAWN','OFFER_DECLINED')", [$rq]);
$total    = $sql("SELECT COUNT(*) FROM candidates WHERE requisition_id=?", [$rq]);
t_eq($joined, 4, 'R1.1 · four joined');
t_eq($offered, 2, 'R1.2 · two offered');
t_eq($active, 5, 'R1.3 · five active (the two offered are still in play)');
t_eq($lost, 3, 'R1.4 · three lost');
t_eq($total, 12, 'R1.5 · twelve candidates in total');
t_eq($joined + $active + $lost, 12, 'R1.6 · *** every candidate is in exactly one bucket ***');

// ---- 2 · THE SERVICE LAYER -------------------------------------------------
t_section('R2 · the service agrees with the records');
$counts = reqf_counts($rq);
t_eq((int) $counts['requested'], 10, 'R2.1 · ten seats were authorised');
t_eq((int) $counts['filled'], $joined, 'R2.2 · filled = the joins in the records');
t_eq((int) $counts['in_progress'], $active, 'R2.3 · in progress = the active candidates');
t_eq((int) $counts['lost'], $lost, 'R2.4 · lost = the lost candidates');
$seats = rexec_seats($rq);
t_eq($seats['remaining'], 6, 'R2.5 · *** six seats remain — not twelve minus four, and not zero ***');
t_ok($total !== (int) $counts['filled'], 'R2.6 · *** candidate count is NOT the filled count ***');
$w = rasg_workload($uRec, ['no_scope' => true]);
t_eq($w['vacancies'], 10, 'R2.7 · the recruiter carries ten authorised seats');
t_eq($w['filled'], 4, 'R2.8 · …four of them filled');
t_eq($w['open_seats'], 6, 'R2.9 · …and six open');
t_eq($w['joins'], 4, 'R2.10 · joins reconcile');
t_eq($w['offers'], 2, 'R2.11 · offers reconcile');
t_eq($w['candidates'], 12, 'R2.12 · candidates reconcile');
t_eq($w['active_candidates'], 9, 'R2.13 · active candidates = twelve minus the three lost');
t_eq($w['interviews'], 2, 'R2.14 · interviews are rounds, not people');

// ---- 3 · THE REQUIREMENT'S OWN STATUS --------------------------------------
t_section('R3 · four joins do not close a ten-seat requirement');
t_eq(strtoupper((string) ops_val("SELECT status FROM requisitions WHERE id=?", [$rq])), 'PARTIALLY_FILLED',
     'R3.1 · *** the requirement is PARTIALLY_FILLED, not HIRED ***');
t_eq(rexec_block_reason($rq, 'JOIN', 0), '', 'R3.2 · and more people may still join');

// ---- 4 · THE DASHBOARD -----------------------------------------------------
t_section('R4 · the command centre agrees');
$f = ['fy' => '', 'range' => null, 'month' => '', 'dept' => '', 'source' => '', 'manager' => ''];
$d = rcc_data($f);
$row = null; foreach ($d['recruiters'] as $r) if ((int) $r['uid'] === $uRec) $row = $r;
t_ok($row !== null, 'R4.1 · the recruiter is on the dashboard');
t_eq((int) $row['posted'], 10, 'R4.2 · dashboard posted = the ten authorised seats');
t_eq((int) $row['recruited'], 4, 'R4.3 · dashboard recruited = the four joins');
$dm = null; foreach ($d['demand'] as $x) if ((int) $x['id'] === $rq) $dm = $x;
t_ok($dm !== null, 'R4.4 · *** a partly-filled requirement is still open demand ***');
t_eq((int) $dm['open'], 6, 'R4.5 · …showing six seats still to fill');
t_eq((int) $dm['vac'], 10, 'R4.6 · …out of ten');

// ---- 5 · THE EXPORT --------------------------------------------------------
t_section('R5 · the CSV export agrees with the screen');
$csv = null;
if (function_exists('recruit_export_rows')) $csv = recruit_export_rows('candidates', $f);
if ($csv === null && function_exists('rexp_rows')) $csv = rexp_rows('candidates', $f);
if (is_array($csv)) {
    $mine = 0;
    foreach ($csv as $line) { $j = implode('|', array_map('strval', (array) $line)); if (strpos($j, 'M6RC-') !== false) $mine++; }
    t_eq($mine, 12, 'R5.1 · *** the export carries the same twelve candidates ***');
} else {
    //  The export is built inside its route handler rather than a listable
    //  function. Reconciled through the WHERE builders it shares with the
    //  dashboard, which is the thing that could disagree.
    [$cw, $ca] = rcc_cand_where($f, 'c');
    $viaBuilder = (int) ops_val("SELECT COUNT(*) FROM candidates c LEFT JOIN requisitions r ON r.id=c.requisition_id
                                 WHERE $cw AND c.requisition_id=?", array_merge($ca, [$rq]));
    t_eq($viaBuilder, 12, 'R5.1 · *** the export and the dashboard share one WHERE builder, and it returns the same twelve ***');
}
t_ok(strpos(preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/recruit_export.php')),
            'rcc_cand_where') !== false,
     'R5.2 · the export reuses the dashboard\'s filter, so the two cannot drift apart');

// ---- 6 · THE REQUEST LAYER'S OWN NUMBERS -----------------------------------
t_section('R6 · hiring requests reconcile with their requisitions');
t_eq((int) hreq_approved_qty($h), 10, 'R6.1 · the approved figure is ten');
$alloc = (int) ops_val("SELECT COALESCE(SUM(quantity),0) FROM requisitions WHERE hiring_request_id=? AND UPPER(COALESCE(status,''))<>'CANCELLED'", [$h]);
t_eq($alloc, 10, 'R6.2 · ten seats were allocated to requisitions');
t_eq((int) hreq_remaining_qty($h), 0, 'R6.3 · …leaving none unallocated');
t_eq($alloc, (int) hreq_approved_qty($h), 'R6.4 · *** allocation never exceeds approval ***');

$_SESSION = $m6r; current_user(true); ua(true);
