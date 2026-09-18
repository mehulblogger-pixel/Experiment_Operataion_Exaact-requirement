<?php
// ============================================================================
//  PHASE 4 — RECONCILIATION AT SCALE (§48)
//
//  The arithmetic has to hold for 10, 20 and 100 seats across 1, 2, 3 and 5
//  sources, in every order — allocate, fill, rebalance, release, cancel — and
//  the totals must never disagree with the rows they are built from.
//
//  Nothing here is stored and re-read: every figure is derived, so the test is
//  asking whether the DERIVATION is right, not whether a cached total drifted.
// ============================================================================

t_section('Phase 4 — reconciliation at scale');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate(); rful_migrate();
$r4o = $_SESSION;
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute([9661, 'P4R Branch']); }
catch (Throwable $e) {}
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES ('p4r_boss','P4R','MANAGER',1,1,9661,'')")->execute();
$uR = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uR; current_user(true); ua(true);

$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$r4req = function ($qty, $title) use ($uR, $eng, $qua) {
    [$ok,, $h] = hreq_save(0, ['requested_by_id' => $uR, 'requested_by_name' => 'P4R',
        'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
        'job_title' => $title, 'designation' => 'ENGINEER', 'job_description' => 'p4r',
        'quantity' => $qty, 'office_id' => 9661, 'required_by' => '2026-12-01',
        'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x']);
    if (!$ok) return 0;
    hreq_submit($h); hreq_apply_decision($h, 'APPROVED', 'P4R', 'ok');
    [$okR,, $rq] = hreq_to_requisition($h, $qty); return $okR ? (int) $rq : 0; };
$r4cand = function ($req, $alloc) use ($pdo) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,created_at)
                   VALUES (?,'P4R','C','RECEIVED',?,?)")
        ->execute(['P4RC-' . bin2hex(random_bytes(4)), $req, date('c')]);
    $c = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $c]);
    return [$c, rful_attach($c, $alloc)];
};

//  THE ONE CHECK. Every figure the product reports about a requirement is
//  re-derived here straight from the rows, and the two must agree exactly. If
//  rful_summary() ever learns to cache, this is what catches the drift.
$r4check = function ($rq, $label) use ($pdo) {
    $s = rful_summary($rq);
    $rows = ops_all("SELECT * FROM requisition_allocations WHERE requisition_id=?", [$rq]);
    $allocRaw = 0; $sourcedRaw = 0;
    foreach ($rows as $a) {
        $allocRaw += (int) $a['allocated_qty'];
        $sourcedRaw += (int) ops_val("SELECT COUNT(*) FROM candidates WHERE allocation_id=? AND stage='ACCEPTED'", [(int) $a['id']]);
    }
    $directRaw = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND COALESCE(allocation_id,0)=0 AND stage='ACCEPTED'", [$rq]);
    $filledRaw = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rq]);
    t_eq($s['allocated'],         $allocRaw,   "$label · allocated matches the rows");
    t_eq($s['sourced_fulfilled'], $sourcedRaw, "$label · sourced arrivals match the rows");
    t_eq($s['direct_fulfilled'],  $directRaw,  "$label · direct arrivals match the rows");
    t_eq($s['fulfilled'],         $filledRaw,  "$label · total arrivals match the rows");
    t_eq($s['committed'],         $allocRaw + $directRaw, "$label · committed is allocated plus direct");
    t_ok($s['sourced_fulfilled'] <= $s['allocated'], "$label · SOURCED ≤ ALLOCATED (I1)");
    t_ok($s['allocated'] <= $s['authorised'],        "$label · ALLOCATED ≤ AUTHORISED (I1)");
    t_ok($s['fulfilled'] <= $s['authorised'],        "$label · nobody joined beyond the approved headcount");
    t_ok($s['unallocated'] >= 0 && $s['remaining'] >= 0, "$label · nothing went negative (I3)");
    return $s; };

// ---- R1 · EVERY SHAPE THE BUSINESS ASKED FOR (§12) --------------------------
t_section('R1 · 10, 20 and 100 seats across 1, 2, 3 and 5 sources');
$srcs = ['OWN_PAYROLL', 'MANPOWER_AGENCY', 'SUBCON_AGENCY', 'FREELANCER', 'SUPPLIER'];
foreach ([10, 20, 100] as $seats) {
    foreach ([1, 2, 3, 5] as $n) {
        $rq = $r4req($seats, "P4R $seats/$n");
        t_ok($rq > 0, "R1 · $seats seats, $n source(s) · the requirement exists");
        //  Split as evenly as the numbers allow; the remainder goes to the first,
        //  exactly as a coordinator would do it.
        $each = intdiv($seats, $n); $rem = $seats - $each * $n;
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            $q = $each + ($i === 0 ? $rem : 0);
            $r = rful_allocate($rq, $srcs[$i], $q);
            t_eq($r['code'], 'OK', "R1 · $seats/$n · source " . ($i + 1) . " took $q");
            $ids[] = [$r['id'], $q];
        }
        $s = $r4check($rq, "R1 · $seats/$n · after allocating");
        t_eq($s['allocated'], $seats, "R1 · $seats/$n · every seat is promised exactly once");
        t_eq($s['unallocated'], 0,    "R1 · $seats/$n · nothing is left over");
        t_eq(rful_allocate($rq, 'CLIENT_BENCH', 1)['code'], 'OVER_AUTHORISED',
             "R1 · $seats/$n · and not one more can be promised");
        //  Fill every source to the brim and confirm the last one over is refused.
        foreach ($ids as $pair) {
            for ($k = 0; $k < $pair[1]; $k++) {
                [$c, $att] = $r4cand($rq, $pair[0]);
                if ($att['code'] !== 'OK') { t_eq($att['code'], 'OK', "R1 · $seats/$n · arrival $k was credited"); break; }
            }
            [$c2, $att2] = $r4cand($rq, $pair[0]);
            t_eq($att2['code'], 'OVER_ALLOCATED', "R1 · $seats/$n · a source full to its promise takes nobody more");
            db()->prepare("DELETE FROM candidates WHERE id=?")->execute([$c2]);   // the over-flow arrival is not part of this shape
        }
        $s = $r4check($rq, "R1 · $seats/$n · after filling");
        t_eq($s['sourced_fulfilled'], $seats, "R1 · $seats/$n · all $seats arrived through their sources");
        t_eq($s['remaining'], 0, "R1 · $seats/$n · the requirement is full");
    }
}

// ---- R2 · THE SAME TOTAL, REACHED BY A DIFFERENT ROUTE ----------------------
t_section('R2 · rebalancing, releasing and cancelling reconcile too');
$rq2 = $r4req(20, 'P4R Route');
$b1 = rful_allocate($rq2, 'MANPOWER_AGENCY', 12)['id'];
$b2 = rful_allocate($rq2, 'OWN_PAYROLL', 8)['id'];
$r4check($rq2, 'R2 · 12 + 8');
for ($i = 0; $i < 5; $i++) $r4cand($rq2, $b1);
$r4check($rq2, 'R2 · five arrived from the agency');
t_eq(rful_reallocate($b1, 5)['code'], 'OK', 'R2.1 · the agency is cut to what it delivered');
$s = $r4check($rq2, 'R2 · after the cut');
t_eq($s['unallocated'], 7, 'R2.2 · seven seats came back');
t_eq(rful_allocate($rq2, 'SUBCON_AGENCY', 7)['code'], 'OK', 'R2.3 · a sub-contractor takes them');
$s = $r4check($rq2, 'R2 · after re-sourcing');
t_eq($s['allocated'], 20, 'R2.4 · twenty again, from three sources instead of two');
t_eq(rful_close($b2, 'CANCELLED', ['reason' => 'budget'])['code'], 'OK', 'R2.5 · own payroll is cancelled');
$s = $r4check($rq2, 'R2 · after the cancellation');
t_eq($s['allocated'], 12, 'R2.6 · its eight undelivered seats went back');
t_eq($s['unallocated'], 8, 'R2.7 · …and are sourceable again');

// ---- R3 · A HUNDRED SEATS, FIVE SOURCES, HALF FILLED, THEN REORGANISED ------
t_section('R3 · a hundred seats reorganised mid-flight');
$rq3 = $r4req(100, 'P4R Hundred');
$h = [];
foreach ([['OWN_PAYROLL', 30], ['MANPOWER_AGENCY', 25], ['SUBCON_AGENCY', 20],
          ['FREELANCER', 15], ['SUPPLIER', 10]] as $pair)
    $h[] = [rful_allocate($rq3, $pair[0], $pair[1])['id'], $pair[1]];
$s = $r4check($rq3, 'R3 · a hundred across five');
t_eq($s['allocated'], 100, 'R3.1 · a hundred promised');
foreach ($h as $pair) for ($k = 0; $k < intdiv($pair[1], 2); $k++) $r4cand($rq3, $pair[0]);
$s = $r4check($rq3, 'R3 · roughly half arrived');
t_eq($s['sourced_fulfilled'], 15 + 12 + 10 + 7 + 5, 'R3.2 · forty-nine have arrived');
//  The supplier walks away; the agency picks up what they were owed.
t_eq(rful_close($h[4][0], 'CANCELLED', ['reason' => 'walked away'])['code'], 'OK', 'R3.3 · the supplier is cancelled');
$s = $r4check($rq3, 'R3 · after the supplier leaves');
t_eq($s['unallocated'], 5, 'R3.4 · its five undelivered seats are back');
t_eq(rful_reallocate($h[1][0], 30)['code'], 'OK', 'R3.5 · the agency takes them on');
$s = $r4check($rq3, 'R3 · after the agency grows');
t_eq($s['allocated'], 100, 'R3.6 · a hundred again');
t_eq($s['unallocated'], 0, 'R3.7 · nothing spare');
t_eq(rful_allocate($rq3, 'CONSULTANT', 1)['code'], 'OVER_AUTHORISED', 'R3.8 · and not one more');
//  The five the supplier DID deliver are still credited to it and still in seats.
t_eq(rful_fulfilled($h[4][0]), 5, 'R3.9 · the supplier keeps credit for the five it delivered (I6)');
t_eq((int) rful_get($h[4][0])['allocated_qty'], 5, 'R3.10 · …and is pinned to exactly those five');

// ---- R4 · THE SWEEP FINDS NOTHING BROKEN ------------------------------------
t_section('R4 · a whole-workspace sweep of every link');
$bad = [];
foreach (rful_bad_links() as $cid) {
    $c = ops_one("SELECT id, requisition_id, allocation_id FROM candidates WHERE id=?", [$cid]);
    $a = rful_get((int) $c['allocation_id']);
    if (!$a || (int) $a['requisition_id'] !== (int) $c['requisition_id']) { $bad[] = $cid; continue; }
    if (rful_over_allocated((int) $a['id'])) $bad[] = $cid;
}
t_eq(count($bad), 0, 'R4.1 · not one candidate is credited to a source that cannot hold them');
$over = ops_all("SELECT id FROM requisition_allocations");
$badA = [];
foreach ($over as $a) if (rful_over_allocated((int) $a['id'])) $badA[] = (int) $a['id'];
t_eq(count($badA), 0, 'R4.2 · not one source is credited beyond its promise (I5)');

$_SESSION = $r4o; current_user(true); ua(true);
