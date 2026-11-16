<?php
// ============================================================================
//  Branch batch (owner, 16 Nov 2026 — QA-REPORTS/V51/27-Branch-Batch-Change-Plan.md)
//
//  R3 — DEF-QUA-006 (P1). A Branch Manager or Operation Manager could work a
//       nonconformity: record containment, decide the disposition (including
//       "Accept as it stands") and reopen a closed one. docs/02 gives them View ·
//       Record (owner rule Q-B1, 19 Oct 2026). Containment, disposition and
//       reopening now go to whoever may CLOSE it (Quality / Technical Head,
//       administrators). Recording is unchanged.
//  X1 — DEF-QUA-002 (P1). Hold & witness points were refused to everybody, the
//       Master Admin included: the routes named a module "hold-points" that does
//       not exist. They are now on the Reporting module (idems), the first right
//       the screen's own checks ask, and the register and actions work.
// ============================================================================
require_once __DIR__ . '/_branch_batch_helpers.php';
$prevSession = $_SESSION ?? [];
$ncrIds = []; $hwIds = [];
try {
    if (function_exists('ncr_migrate')) ncr_migrate();
    if (function_exists('hwp_migrate')) hwp_migrate();
    $A = bb_office('Ahmedabad'); $M = bb_office('Mumbai');
    $t = strtoupper(bb_tag());
    $mkNcr = function ($status, $title) use ($A, &$ncrIds, $t) {
        db()->prepare("INSERT INTO nonconformities (ref, source, office_id, title, description, severity, detected_on, status, raised_by, created_at, disposition, containment)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute(["$t-" . count($ncrIds), 'INTERNAL', $A, $title, 'probe', 'MINOR', date('Y-m-d'), $status, 'probe', date('c'),
                       $status === 'CLOSED' ? 'REWORK' : '', $status === 'CLOSED' ? 'contained' : '']);
        $id = (int) db()->lastInsertId(); $ncrIds[] = $id; return $id;
    };
    $ncr = fn($id) => ops_one("SELECT status, containment, disposition FROM nonconformities WHERE id=?", [$id]);

    // ------------------------------------------------------------------------
    t_section('R3 — Branch / Operation Manager record a nonconformity but do not work it (DEF-QUA-006)');
    $bm = bb_user('BRANCH_MANAGER', $A, ''); $om = bb_user('OPERATION_MANAGER', $A, '');
    $qh = bb_user('QUALITY_HEAD', $A, ''); $th = bb_user('TECHNICAL_HEAD', $A, '');
    foreach (['Branch Manager' => $bm, 'Operation Manager' => $om] as $who => $uid) {
        bb_as($uid);
        t_ok(ncr_can_record(), "the $who may still RECORD a nonconformity (owner rule Q-B1)");
        t_ok(!ncr_can_decide(), "*** the $who may not record containment or decide the disposition");
        t_ok(!ncr_can_close(), "*** …nor reopen or close one");
    }
    foreach (['Quality Head' => $qh, 'Technical Head' => $th] as $who => $uid) {
        bb_as($uid);
        t_ok(ncr_can_decide() && ncr_can_close(), "the $who contains, decides, closes and reopens");
    }

    $open = $mkNcr('OPEN', "$t open");
    $r = bb_drive($bm, 'ncr-contain', 'POST', [], ['id' => $open, 'containment' => 'BM contains']);
    t_eq($ncr($open)['containment'], '', '*** a Branch Manager\'s containment is NOT saved (' . bb_said($r) . ')');
    $r = bb_drive($om, 'ncr-contain', 'POST', [], ['id' => $open, 'containment' => 'OM contains']);
    t_eq($ncr($open)['containment'], '', '*** an Operation Manager\'s containment is NOT saved (' . bb_said($r) . ')');
    $r = bb_drive($bm, 'ncr-disposition', 'POST', [], ['id' => $open, 'disposition' => 'ACCEPT', 'disposition_note' => 'convenient']);
    t_eq($ncr($open)['disposition'], '', '*** a Branch Manager cannot "Accept as it stands" (' . bb_said($r) . ')');
    t_ok(strpos(bb_said($r), 'working it is for quality') !== false, '…and is told who works it');

    $closed = $mkNcr('CLOSED', "$t closed");
    $r = bb_drive($bm, 'ncr-reopen', 'POST', [], ['id' => $closed, 'why' => 'probe']);
    t_eq($ncr($closed)['status'], 'CLOSED', '*** a Branch Manager cannot reopen a closed nonconformity by a direct request (' . bb_said($r) . ')');

    $r = bb_drive($qh, 'ncr-contain', 'POST', [], ['id' => $open, 'containment' => 'QH contains']);
    t_eq($ncr($open)['containment'], 'QH contains', 'the Quality Head records containment (' . bb_said($r) . ')');
    $r = bb_drive($qh, 'ncr-disposition', 'POST', [], ['id' => $open, 'disposition' => 'ACCEPT', 'disposition_note' => 'within tolerance per client']);
    t_eq($ncr($open)['disposition'], 'ACCEPT', 'the Quality Head may accept it as it stands, with a reason');
    $r = bb_drive($th, 'ncr-reopen', 'POST', [], ['id' => $closed, 'why' => 'new evidence']);
    t_eq($ncr($closed)['status'], 'DISPOSITIONED', 'the Technical Head may reopen a closed one (' . bb_said($r) . ')');

    $before = (int) ops_val("SELECT COUNT(*) FROM nonconformities");
    $r = bb_drive($bm, 'ncr-new', 'POST', [], ['title' => "$t raised by BM", 'severity' => 'MINOR', 'office_id' => $A]);
    $new = (int) ops_val("SELECT id FROM nonconformities WHERE title=?", ["$t raised by BM"]);
    if ($new) $ncrIds[] = $new;
    t_ok($new > 0, 'recording stays as it is: the Branch Manager still raises one (' . bb_said($r) . ')');

    //  The screen offers each form only to whom its save accepts.
    $r = bb_drive($bm, 'ncr-item', 'GET', ['id' => $open], [], ['action="/ncr-contain"', 'action="/ncr-disposition"']);
    t_ok(empty($r['has']['action="/ncr-contain"']) && empty($r['has']['action="/ncr-disposition"']), 'the Branch Manager is not offered the containment or disposition forms');

    // ------------------------------------------------------------------------
    t_section('X1 — Hold & witness points work again (DEF-QUA-002)');
    $map = ops_route_module_map();
    foreach (['hold-points', 'hw-point-new', 'hw-point-clear', 'hw-point-waive', 'hw-point-cancel', 'hw-point-reopen', 'hw-point-derive'] as $rt) {
        t_eq($map[$rt] ?? null, 'idems', "*** $rt is gated on a module that exists (Reporting)");
    }
    t_ok(isset(ACCESS_MODULES['idems']), 'idems is a real access module');

    $cA = bb_call("$t-HC", $A, $A); $jA = bb_job("$t-HJ", $cA, $A);
    $amd = bb_user('COORDINATOR', $A, ''); $mum = bb_user('COORDINATOR', $M, ''); $lost = bb_user('COORDINATOR', null, '');
    $master = (int) ops_val("SELECT id FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1");
    $r = bb_drive($master, 'hold-points', 'GET', [], [], ['Every open intervention point across your jobs']);
    t_ok(!empty($r['has']['Every open intervention point across your jobs']), '*** the Master Admin opens the register (it answered "not available") (' . bb_said($r) . ')');
    $r = bb_drive($amd, 'hold-points', 'GET', [], [], ['Every open intervention point across your jobs']);
    t_ok(!empty($r['has']['Every open intervention point across your jobs']), '*** a Coordinator opens the register');
    $r = bb_drive($qh, 'hold-points', 'GET', [], [], ['Every open intervention point across your jobs']);
    t_ok(!empty($r['has']['Every open intervention point across your jobs']), '*** the Quality Head opens the register');

    $desc = "$t witness the hydro test";
    $r = bb_drive($amd, 'hw-point-new', 'POST', [], ['job_id' => $jA, 'point_type' => 'WITNESS', 'qap_clause' => '4.2', 'description' => $desc]);
    $hp = ops_one("SELECT * FROM hw_points WHERE description=?", [$desc]);
    if ($hp) $hwIds[] = (int) $hp['id'];
    t_ok($hp && $hp['status'] === 'OPEN', '*** a Coordinator raises a point on a job (' . bb_said($r) . ')');
    t_eq((int) ($hp['office_id'] ?? 0), $A, '…and it carries the job\'s office, so the register can scope it');

    $r = bb_drive($amd, 'hold-points', 'GET', [], [], [$desc]);
    t_ok(!empty($r['has'][$desc]), 'the Ahmedabad register lists it');
    $r = bb_drive($mum, 'hold-points', 'GET', [], [], [$desc]);
    t_ok(empty($r['has'][$desc]), 'the Mumbai register does not');
    $r = bb_drive($lost, 'hold-points', 'GET', [], [], [$desc]);
    t_ok(empty($r['has'][$desc]), 'nor does the register of a person with no branch');

    if ($hp) {
        bb_drive($mum, 'hw-point-clear', 'POST', [], ['id' => (int) $hp['id'], 'clearance_ref' => 'X', 'remark' => 'probe']);
        t_eq((string) ops_val("SELECT status FROM hw_points WHERE id=?", [(int) $hp['id']]), 'OPEN', 'another branch cannot clear it');
        $r = bb_drive($amd, 'hw-point-clear', 'POST', [], ['id' => (int) $hp['id'], 'clearance_ref' => 'MAIL-1', 'remark' => 'witnessed']);
        t_eq((string) ops_val("SELECT status FROM hw_points WHERE id=?", [(int) $hp['id']]), 'CLEARED', '*** the Ahmedabad Coordinator clears it (' . bb_said($r) . ')');
        $r = bb_drive($amd, 'hw-point-reopen', 'POST', [], ['id' => (int) $hp['id'], 'remark' => 'again']);
        t_eq((string) ops_val("SELECT status FROM hw_points WHERE id=?", [(int) $hp['id']]), 'OPEN', '…and can reopen it');
        $r = bb_drive($amd, 'hw-point-waive', 'POST', [], ['id' => (int) $hp['id'], 'remark' => 'client waived in writing']);
        t_eq((string) ops_val("SELECT status FROM hw_points WHERE id=?", [(int) $hp['id']]), 'WAIVED', '…or waive it with a reason');
    }
    $fin = bb_user('FINANCE', $A, 'ALL');
    $r = bb_drive($fin, 'hold-points', 'GET', [], [], [$desc]);
    t_ok(empty($r['has'][$desc]), 'Finance, which reads no inspection findings (D-07), is not shown the register');
} finally {
    foreach ($ncrIds as $i) { try { db()->prepare("DELETE FROM nonconformities WHERE id=?")->execute([$i]); db()->prepare("DELETE FROM ncr_events WHERE ncr_id=?")->execute([$i]); } catch (Throwable $e) {} }
    foreach ($hwIds as $i) { try { db()->prepare("DELETE FROM hw_points WHERE id=?")->execute([$i]); } catch (Throwable $e) {} }
    bb_cleanup();
    $_SESSION = $prevSession; current_user(true); ua(true);
}
