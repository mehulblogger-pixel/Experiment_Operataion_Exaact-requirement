<?php
// ============================================================================
//  PHASE 7 — the three owner decisions, tested where they actually bite.
//
//  1. WHICH TEAM is decided at the requirement, confirmed at acceptance, and
//     never allowed to fall through to the FIELD default that
//     `inspectors.team_role` carries.
//  2. RB-1 — accepting somebody IS hiring them. Every accepted candidate has a
//     workforce record; no hidden checkbox decides whether a hired person
//     exists.
//  3. RB-2 — ACCEPTED (Hired) is not JOINED. The system reports what it knows
//     and stops calling paperwork an arrival.
//
//  Each rule is armed before it is asserted: a trap that never sprang is the
//  failure mode these batteries exist to catch.
// ============================================================================

t_as_admin();
if (function_exists('emp_code_migrate')) emp_code_migrate();
if (function_exists('reqf_migrate'))     reqf_migrate();

$p7Office = (int) ops_val("SELECT id FROM offices ORDER BY id LIMIT 1");
$p7me     = (int) (current_user()['id'] ?? 0);
try { db()->prepare("UPDATE users SET home_office_id=? WHERE id=?")->execute([$p7Office, $p7me]); } catch (Throwable $e) {}

$p7req = function ($qty = 1, $role = null) use ($p7Office) {
    db()->prepare("INSERT INTO requisitions (req_code,office_id,sbu,designation,quantity,status,team_role,created_by,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute(['P7-' . substr(md5((string)mt_rand()), 0, 6), $p7Office, 'IND', 'Inspector',
                   (int)$qty, 'OPEN', $role, 'p7', date('c')]);
    return (int) db()->lastInsertId();
};
$p7cand = function ($rq, $name = 'P7') {
    db()->prepare("INSERT INTO candidates (first_name,last_name,email,mobile,stage,sbu,requisition_id,created_at)
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$name, 'Person', strtolower($name) . mt_rand(1000, 9999) . '@p7.test',
                   '9' . mt_rand(100000000, 999999999), 'OFFERED', 'IND', (int)$rq, date('c')]);
    return (int) db()->lastInsertId();
};
$roleOf  = fn($iid) => (string) ops_val("SELECT COALESCE(team_role,'') FROM inspectors WHERE id=?", [(int)$iid]);
$inspOf  = fn($cid) => (int) ops_val("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [(int)$cid]);
$stageOf = fn($cid) => (string) ops_val("SELECT stage FROM candidates WHERE id=?", [(int)$cid]);

// ---------------------------------------------------------------------------
t_section('P7 · TR — which team, decided and never defaulted');
// ---------------------------------------------------------------------------
//  ARMING: the database default is still FIELD. That is the whole hazard — if
//  it were not there, nothing below would be worth testing.
$p7ddl = (string) ops_val("SELECT 1");
t_ok(function_exists('wf_team_role_resolve'), 'TR0 · the resolver exists');
t_eq(wf_team_role_normalise('field'), 'FIELD', 'TR0a · the vocabulary is the EXISTING one, case-insensitively');
t_eq(wf_team_role_normalise('MANAGER'), '', 'TR0b · …and nothing outside it is accepted');

//  TR1 — nobody decided: the acceptance is REFUSED, not quietly made FIELD.
$rqNone = $p7req(1, null);
$cNone  = $p7cand($rqNone, 'NoRole');
t_eq((string) ops_val("SELECT COALESCE(team_role,'') FROM requisitions WHERE id=?", [$rqNone]), '',
     'TR1a · the requirement carries no decision — armed');
$rNone = rcv_convert($cNone, ['actor_id' => $p7me]);
t_eq((string) ($rNone['code'] ?? ''), 'TEAM_ROLE',
     'TR1 · with nobody having decided, the hire is REFUSED — it is NOT silently made FIELD');
t_eq($inspOf($cNone), 0, 'TR1b · …and nothing was created');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE team_role='FIELD' AND name LIKE 'NoRole%'"), 0,
     'TR1c · …in particular, no FIELD team member appeared anywhere');

//  TR2 — the requirement decided: that decision is what gets written.
foreach (['COORD', 'OFFICE', 'FIELD'] as $want) {
    $rq = $p7req(1, $want);
    $c  = $p7cand($rq, 'Req' . $want);
    t_eq((string) ops_val("SELECT team_role FROM requisitions WHERE id=?", [$rq]), $want,
         'TR2a(' . $want . ') · the requirement carries the decision — armed');
    $r = rcv_convert($c, ['actor_id' => $p7me]);
    t_ok(!empty($r['ok']), 'TR2b(' . $want . ') · the hire went through');
    t_eq($roleOf($inspOf($c)), $want,
         'TR2(' . $want . ') · the team member carries the REQUIREMENT\'s decision, not the database default');
}

//  TR3 — the confirmation at acceptance wins over the requirement.
$rqF = $p7req(1, 'FIELD');
$cF  = $p7cand($rqF, 'Confirmed');
$rF  = rcv_convert($cF, ['actor_id' => $p7me, 'team_role' => 'OFFICE']);
t_ok(!empty($rF['ok']), 'TR3a · the hire went through');
t_eq($roleOf($inspOf($cF)), 'OFFICE',
     'TR3 · what was CONFIRMED at acceptance wins — the decision is visible and correctable, per the owner');

//  TR4 — a nonsense role is not smuggled through, and does not become FIELD.
$rqJ = $p7req(1, 'COORD');
$cJ  = $p7cand($rqJ, 'Junk');
$rJ  = rcv_convert($cJ, ['actor_id' => $p7me, 'team_role' => 'SUPERVISOR']);
t_ok(!empty($rJ['ok']), 'TR4a · the hire still went through on the requirement\'s decision');
t_eq($roleOf($inspOf($cJ)), 'COORD',
     'TR4 · an unrecognised role is ignored in favour of the real decision — never coerced to FIELD');

// ---------------------------------------------------------------------------
t_section('P7 · CAP — what the workspace does decides whether Inspector applies');
// ---------------------------------------------------------------------------
//  THE FIRST VERSION OF THIS SECTION BRANCHED ON whatever state the workspace
//  happened to be in — so a mutation that CHANGED that state simply sent the
//  test down its other branch, and the test passed either way. It adapted to
//  the code instead of pinning it, and mutant Q7 walked straight through.
//
//  Each of the three states is now CONSTRUCTED and asserted in turn, and the
//  workspace is put back exactly as it was found.
$capModsWere = function_exists('setting_get') ? (string) setting_get('modules_off', '') : '';
$capCapsWere = function_exists('cockpit_capabilities') ? cockpit_capabilities() : [];
$capSet = function ($opsOn, array $caps) use ($capModsWere) {
    $off = array_values(array_filter(array_map('trim', explode(',', $capModsWere))));
    $off = array_values(array_diff($off, ['operations']));
    if (!$opsOn) $off[] = 'operations';
    setting_set('modules_off', implode(',', $off));
    if (function_exists('licence_disabled')) licence_disabled(true);
    if (function_exists('cockpit_capabilities_set')) cockpit_capabilities_set($caps);
};

t_ok(function_exists('wf_ops_capability'),   'CAP0 · the workspace is ASKED what it does, never assumed');
t_ok(function_exists('wf_inspector_applies'),'CAP0a · and applicability is asked too');

//  STATE 1 — Operations on, but the company has never said what it does.
$capSet(true, []);
t_eq(wf_ops_capability(), 'UNCONFIGURED',
     'CAP1 · a workspace that has never chosen its activities reports UNCONFIGURED — armed');
t_ok(!wf_inspector_applies('FIELD'),
     'CAP1a · …and silence is NOT read as "yes": not even a FIELD hire is made a deployable inspector (owner decision 4)');
$rqU = $p7req(1, null);
$cU  = $p7cand($rqU, 'Unconf');
t_eq((string) (rcv_convert($cU, ['actor_id' => $p7me])['code'] ?? ''), 'TEAM_ROLE',
     'CAP1b · …and an undecided hire is refused there rather than guessed at');

//  STATE 2 — Operations on and the company does inspection work.
$capSet(true, ['TPIA']);
t_eq(wf_ops_capability(), 'YES', 'CAP2 · once the company says it does inspection work, the concept exists — armed');
t_ok(wf_inspector_applies('FIELD'),   'CAP2a · a FIELD hire IS a deployable inspector there');
t_ok(!wf_inspector_applies('COORD'),  'CAP2b · …a coordinator is workforce, not a deployable inspector');
t_ok(!wf_inspector_applies('OFFICE'), 'CAP2c · …and back office is not either');

//  STATE 3 — a recruitment-only workspace: no site work at all.
$capSet(false, ['TECH_RECRUITMENT']);
t_eq(wf_ops_capability(), 'NO', 'CAP3 · a recruitment-only workspace reports NO — armed');
t_ok(!wf_inspector_applies('FIELD'),
     'CAP3a · …so Inspector functionality is not invented merely because somebody was hired');
//  And there the classification is recorded EXPLICITLY as office rather than
//  left to the database default — the same thing the Add-a-person form does.
$rqN2 = $p7req(1, null);
$cN2  = $p7cand($rqN2, 'RecOnly');
$rN2  = rcv_convert($cN2, ['actor_id' => $p7me]);
t_ok(!empty($rN2['ok']), 'CAP3b · a hire there goes through without anybody being asked which team — armed');
t_eq($roleOf($inspOf($cN2)), 'OFFICE',
     'CAP3 · …and is recorded as OFFICE explicitly, never as the database\'s FIELD default');

//  Put the workspace back exactly as it was.
$capSet(!in_array('operations', array_map('trim', explode(',', $capModsWere)), true), $capCapsWere);
setting_set('modules_off', $capModsWere);
if (function_exists('licence_disabled')) licence_disabled(true);
t_eq((string) setting_get('modules_off', ''), $capModsWere,
     'CAP4 · the workspace was restored to how this battery found it');

// ---------------------------------------------------------------------------
t_section('P7 · CAPTX — asking what the workspace does must not end a transaction');
// ---------------------------------------------------------------------------
//  A DEFECT FOUND BY THE REGRESSION, recorded so it cannot come back.
//
//  wf_ops_capability() consults the capability catalogue, and that catalogue
//  installs its own table on first use. On MariaDB ANY DDL commits the open
//  transaction implicitly — so asking the question from inside a caller's
//  transaction committed that caller's half-written work and left them with
//  nothing to roll back. A conversion running inside a borrowed transaction hit
//  exactly that, and the caller's rollBack() then failed outright.
//
//  Inside a transaction the question is now answered by READING, never by
//  migrating.
$capTxOk = false;
try {
    db()->beginTransaction();
    t_ok(db()->inTransaction(), 'CAPTX0 · a caller\'s transaction is open — armed');
    $capAns = wf_ops_capability();
    t_ok(in_array($capAns, ['YES', 'NO', 'UNCONFIGURED'], true),
         'CAPTX1 · the question is still answered from inside a transaction (' . $capAns . ')');
    $capTxOk = db()->inTransaction();
    t_ok($capTxOk, 'CAPTX · …and THE TRANSACTION IS STILL OPEN — nothing migrated, so nothing was silently committed');
    db()->rollBack();
    t_ok(true, 'CAPTX2 · …so the caller can still unwind its own work');
} catch (Throwable $e) {
    t_ok(false, 'CAPTX · asking the capability question inside a transaction broke it (' . $e->getMessage() . ')');
    try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $e2) {}
}
//  …and the migration is warmed BEFORE the transaction, where DDL is safe.
t_ok(defined('RCV_ACCEPT_MIGRATIONS') && in_array('cockpit_migrate', RCV_ACCEPT_MIGRATIONS, true),
     'CAPTX3 · the capability migration is warmed before acceptance opens its transaction');

// ---------------------------------------------------------------------------
t_section('P7 · RB1 — accepting somebody IS hiring them');
// ---------------------------------------------------------------------------
//  ARMING: the old behaviour was driven by a checkbox in the POST. This drives
//  the real route with NO checkbox at all.
$rq1 = $p7req(2, 'FIELD');
$c1  = $p7cand($rq1, 'Rb1A');
$_POST = ['to_stage' => 'ACCEPTED'];            // deliberately no make_inspector
$_GET  = ['id' => $c1];
t_ok(!isset($_POST['make_inspector']), 'RB1a · nothing in the request asks for a workforce record — armed');
$before = (int) ops_val("SELECT COUNT(*) FROM inspectors");
$rRb1 = rcv_convert($c1, ['actor_id' => $p7me]);
t_ok(!empty($rRb1['ok']), 'RB1b · the hire went through');
t_ok($inspOf($c1) > 0, 'RB1 · a workforce record exists although nobody ticked anything');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors"), $before + 1, 'RB1c · exactly one was created');
t_ok((string) ops_val("SELECT COALESCE(emp_code,'') FROM inspectors WHERE id=?", [$inspOf($c1)]) !== '',
     'RB1d · …with an employee number — a real record, not a stub');
//  The route no longer reads the flag at all: a POST that explicitly says "no"
//  cannot suppress the record either.
$src = (string) @file_get_contents(dirname(__DIR__) . '/lib/ops.php');
t_ok(strpos($src, "!empty(\$_POST['make_inspector'])") === false,
     'RB1e · the route no longer lets a checkbox decide whether a hired person exists');
$view = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/candidate_detail.php');
t_ok(strpos($view, 'name="make_inspector"') === false,
     'RB1f · …and the screen no longer offers one');
t_ok(strpos($view, 'name="dup_ack"') !== false,
     'RB1g · but the DUPLICATE acknowledgement is still there — it is a different control, and the owner requires it');

// ---------------------------------------------------------------------------
t_section('P7 · RB2 — hired is not joined');
// ---------------------------------------------------------------------------
$rq2 = $p7req(3, 'FIELD');
$h1  = $p7cand($rq2, 'Hire1');
$h2  = $p7cand($rq2, 'Hire2');
foreach ([$h1, $h2] as $h) {
    db()->prepare("UPDATE candidates SET stage='ACCEPTED' WHERE id=?")->execute([$h]);
    rcv_convert($h, ['actor_id' => $p7me]);
}
$cnt = reqf_counts($rq2);
t_eq((int) $cnt['requested'], 3, 'RB2a · three were asked for — armed');
t_eq((int) $cnt['filled'], 2,   'RB2b · two are hired');
t_ok($inspOf($h1) > 0 && $inspOf($h2) > 0, 'RB2c · …and both have workforce records, which is what USED to be counted as "joined" — armed');
t_eq((int) $cnt['joined'], 0,
     'RB2 · JOINED IS ZERO — two people are hired and nobody has said either of them turned up');

//  Now somebody says one of them did.
db()->prepare("UPDATE candidates SET joined_at=? WHERE id=?")->execute([date('Y-m-d'), $h1]);
$cnt2 = reqf_counts($rq2);
t_eq((int) $cnt2['joined'], 1, 'RB2d · one joined once somebody recorded it');
t_eq((int) $cnt2['filled'], 2, 'RB2e · …and "hired" is still two — the two numbers are independent');
t_ok($cnt2['joined'] < $cnt2['filled'],
     'RB2f · the system can now say "2 hired, 1 joined" instead of claiming they are the same thing');

//  The requirement's own status must not be disturbed by any of this: two of
//  three seats filled is still PARTIALLY_FILLED, exactly as before.
reqf_sync($rq2);
t_eq((string) ops_val("SELECT status FROM requisitions WHERE id=?", [$rq2]), 'PARTIALLY_FILLED',
     'RB2g · partly-filled behaviour is unchanged — RB-2 corrects a COUNT, it does not touch the lifecycle');

//  And the count is driven by the record, not by the stage: clearing it takes
//  the person back out of "joined" without touching their hire.
db()->prepare("UPDATE candidates SET joined_at=NULL WHERE id=?")->execute([$h1]);
t_eq((int) reqf_counts($rq2)['joined'], 0, 'RB2h · removing the joining record removes them from the count');
t_eq($stageOf($h1), 'ACCEPTED', 'RB2i · …and they are still hired — joining is a separate fact, reversibly recorded');

$_POST = []; $_GET = [];
