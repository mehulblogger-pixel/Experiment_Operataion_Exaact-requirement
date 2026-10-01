<?php
// ============================================================================
//  GATE 6 · D2 — HIRED BUT NOT YET JOINED IS NOT AN AVAILABLE RESOURCE
// ============================================================================
//  THE PATH THIS TESTS (traced, not assumed):
//
//    GET /availability
//      → lib/ops.php:4089            route case 'availability'
//      → ops_inspector_availability() lib/workforce.php — permission, day, scope
//      → availability_scope_offices() the offices this user may see
//      → inspector_availability()     lib/workforce.php — THE QUALIFICATION POINT
//                                     WHERE status='ACTIVE' AND staff_kind<>'SUBCON'
//      → UI filters, free-both-days, date check, month grid
//      → view('ops/availability')     the board a coordinator reads
//
//  inspector_availability() is where eligibility is decided, so that is what is
//  asserted here. The rendered board is asserted separately in the browser
//  battery (tools/g6-browser-check.js), because view() is defined in index.php
//  and is not reachable from the test harness — so a server-side test that
//  claimed to prove the SCREEN would be proving nothing.
//
//  D2 is distinct from A-F1: A-F1 was the assignment dropdown, this is the
//  availability/resource-selection path.
// ============================================================================
$D2 = 'D2-' . random_int(1000, 9999);
$pdo = db();
$made = ['ins' => [], 'off' => [], 'user' => []];
$offA = (int) ops_val("SELECT MIN(id) FROM offices");

//  A SECOND, EMPTY office, so "nobody eligible" can be tested without disturbing
//  the people other suites rely on in the first one.
$pdo->prepare("INSERT INTO offices (name, code, is_ahmedabad) VALUES (?,?,0)")
    ->execute([$D2 . ' Branch', substr($D2, 0, 8)]);
$offB = (int) $pdo->lastInsertId(); $made['off'][] = $offB;

$mk = function ($tag, $status, $office) use ($pdo, $D2, &$made) {
    $pdo->prepare("INSERT INTO inspectors (name,emp_code,home_office_id,staff_kind,team_role,status,created_at)
                   VALUES (?,?,?,'ASSET','FIELD',?,?)")
        ->execute([$D2 . ' ' . $tag, $D2 . '-' . $tag, $office, $status, date('c')]);
    $id = (int) $pdo->lastInsertId(); $made['ins'][] = $id;
    return $id;
};
$onBoard = function ($rows, $id) { foreach ($rows as $r) if ((int) $r['id'] === (int) $id) return true; return false; };

// ---------------------------------------------------------------------------
t_section('G6 D2 · WHO THE AVAILABILITY PATH TREATS AS AN AVAILABLE RESOURCE');
// ---------------------------------------------------------------------------
$iActive  = $mk('D2active',  WF_ST_ACTIVE,   $offB);
$iJoining = $mk('D2joining', WF_ST_JOINING,  $offB);
$iLeft    = $mk('D2left',    WF_ST_INACTIVE, $offB);

$rows = inspector_availability([$offB]);
//  Guard against a vacuous pass: if the board came back empty for everybody then
//  every "is absent" assertion below would be true for the wrong reason.
t_ok(count($rows) >= 1, 'D2-0 · the availability path returned somebody (without this, nothing below means anything)');
t_ok($onBoard($rows, $iActive),
     'D2-1 · an ACTIVE person IS an available resource — the board still works');
t_ok(!$onBoard($rows, $iJoining),
     'D2-2 · *** a HIRED-BUT-NOT-JOINED person is NOT an available resource ***');
t_ok(!$onBoard($rows, $iLeft),
     'D2-3 · somebody who has LEFT is not an available resource');
t_eq(count($rows), 1, 'D2-4 · exactly one of the three qualifies');

// ---------------------------------------------------------------------------
t_section('G6 D2 · NOBODY ELIGIBLE MEANS ZERO, NOT A FALLBACK');
// ---------------------------------------------------------------------------
//  An office whose only people are a joiner and a leaver must report NO available
//  resource — not "everybody", which is how a negative filter fails.
$pdo->prepare("UPDATE inspectors SET status=? WHERE id=?")->execute([WF_ST_JOINING, $iActive]);
$none = inspector_availability([$offB]);
t_eq(count($none), 0, 'D2-5 · *** with only joiners and leavers, the board is EMPTY ***');
$pdo->prepare("UPDATE inspectors SET status=? WHERE id=?")->execute([WF_ST_ACTIVE, $iActive]);

// ---------------------------------------------------------------------------
t_section('G6 D2 · THE COUNT THE BOARD USES TO EXPLAIN THE ABSENCE');
// ---------------------------------------------------------------------------
//  The message must be drawn from the same population as the board, or it will
//  contradict it. Same office set, same non-subcontractor rule.
t_ok(function_exists('wf_joining_pending_in_offices'), 'D2-6 · the helper exists');
t_eq(wf_joining_pending_in_offices([$offB]), 1, 'D2-7 · it counts the one joiner in this office');
t_eq(wf_joining_pending_in_offices([$offA]), (int) ops_val(
        "SELECT COUNT(*) FROM inspectors WHERE UPPER(TRIM(COALESCE(status,'')))=? AND COALESCE(staff_kind,'ASSET')<>'SUBCON' AND home_office_id=?",
        [WF_ST_JOINING, $offA]),
     'D2-8 · and agrees with the database for another office');
t_eq(wf_joining_pending_in_offices([]), 0, 'D2-9 · no offices in scope means nothing to report');
//  A subcontractor is not part of this board's population, so must not be counted.
$iSub = $mk('D2sub', WF_ST_JOINING, $offB);
$pdo->prepare("UPDATE inspectors SET staff_kind='SUBCON' WHERE id=?")->execute([$iSub]);
t_eq(wf_joining_pending_in_offices([$offB]), 1,
     'D2-10 · a sub-contractor is not counted, because the board never shows them');

// ---------------------------------------------------------------------------
t_section('G6 D2/D3 · LISTING SOMEBODY NEVER MAKES THEM AVAILABLE');
// ---------------------------------------------------------------------------
//  D3 adds a status filter to the TEAM REGISTER. The one thing it must never do
//  is leak into operational qualification. Proved by measurement: the register's
//  own filter conditions select the joiner, and the availability path still does
//  not.
$asRegister = function ($status) use ($pdo) {
    //  The same conditions lib/ops.php builds for /m/inspectors?status=…
    if ($status === WF_ST_ACTIVE) $cond = wf_active_sql();
    else { $cond = "UPPER(TRIM(COALESCE(status,'')))='" . $status . "'"; }
    return array_map('intval', array_column(
        ops_all("SELECT id FROM inspectors WHERE $cond ORDER BY id") ?: [], 'id'));
};
$regJoin = $asRegister(WF_ST_JOINING);
t_ok(in_array($iJoining, $regJoin, true),
     'D2-11 · the team register CAN list the joiner — HR must be able to find them (§11)');
$after = inspector_availability([$offB]);
t_ok(!$onBoard($after, $iJoining),
     'D2-12 · *** …and the availability path still refuses them — listing is not qualifying ***');
t_ok(in_array($iActive, $asRegister(WF_ST_ACTIVE), true),
     'D2-13 · the register lists an active person under Active');
t_ok(in_array($iLeft, $asRegister(WF_ST_INACTIVE), true),
     'D2-14 · and a leaver under Inactive');

// ---------------------------------------------------------------------------
t_section('G6 D2 · Z · PUT THE FIXTURES BACK');
// ---------------------------------------------------------------------------
try {
    foreach ($made['ins'] as $i) $pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$i]);
    foreach ($made['off'] as $o) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$o]);
    foreach ($made['user'] as $u) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$u]);
} catch (Throwable $e) {}
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE emp_code LIKE ?", [$D2 . '%']), 0,
     'D2-Z1 · the people this suite created are gone');
t_eq((int) ops_val("SELECT COUNT(*) FROM offices WHERE code = ?", [substr($D2, 0, 8)]), 0,
     'D2-Z2 · and so is its branch');
