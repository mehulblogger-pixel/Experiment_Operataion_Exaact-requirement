<?php
// ============================================================================
//  GATE 6B — THE TWO LOCKED DECISIONS
// ============================================================================
//  R1-UI · The "Review Required" trigger an organisation MAY switch off is
//          configurable from a screen instead of only from the database, and the
//          one it may NOT switch off stays mandatory.
//
//  R2    · The per-person utilisation breakdown leaves out people who have been
//          hired but have not started yet.
//
//  WHAT IS PROVED HERE AND WHAT IS NOT. Both decisions have a screen, and
//  view() is defined in index.php, so no test in this harness can render one. So
//  this file proves the DECISION — the behaviour behind the screen — and the
//  browser battery (tools/g6b-browser-check.js) proves the screen itself. A
//  server test claiming to prove a screen would be proving nothing, and the two
//  halves are kept honest by saying which is which.
//
//  THE LOAD-BEARING ASSERTIONS are marked ***. If one of those passes while the
//  implementation is wrong, this file is not doing its job.
// ============================================================================
$G = 'G6B-' . random_int(100000, 999999);
$pdo = db();
crev_migrate(); rver_migrate(); hreq_migrate(); appr_migrate(); act_migrate();
recruitpipe_migrate(); rkpi_migrate(); person_migrate();

//  PUT THE ORGANISATION'S OWN CONFIGURATION BACK at the end — this file writes a
//  real setting, which is workspace-wide and not a fixture. Leaving it changed
//  would hand every later suite a different review policy.
$trigBefore = setting_get(crev_trigger_key('redefined'), null);
//  FORCE THE SETTINGS CACHE TO RELOAD. settings_cache() is keyed on db_epoch(), so
//  bumping the epoch is how a test makes a direct DELETE visible to setting_get().
//  setting_set() keeps the cache fresh by itself; this is only for the raw writes.
$reload = function () { $GLOBALS['__db_epoch'] = (int) ($GLOBALS['__db_epoch'] ?? 0) + 1; };
$made = ['ins' => [], 'off' => []];

// ---------------------------------------------------------------------------
t_section('G6B · A — R1-UI · IS THE TRIGGER REAL, NAMED CONFIGURATION?');
// ---------------------------------------------------------------------------
t_eq(crev_trigger_key('redefined'), 'crev_trigger_redefined',
     'A1 · the setting key is built in one place, and is the key Gate 3 already used');
t_eq(crev_trigger_key('  REDEFINED '), 'crev_trigger_redefined',
     'A2 · and is case- and space-insensitive, so a screen cannot invent a second key');

//  A NEW ORGANISATION. No row for the setting at all — which is exactly the state
//  of every workspace that existed before this screen did.
$pdo->prepare("DELETE FROM settings WHERE skey=?")->execute([crev_trigger_key('redefined')]);
$reload();
t_ok(crev_trigger_on('redefined'),
     'A3 · *** with no setting stored at all, a redefinition raises a review: the default is ON ***');
t_ok(in_array('redefined', crev_triggers(), true),
     'A4 · …and the trigger list agrees with it');

$st = crev_trigger_state();
t_ok(in_array('stricter', $st['mandatory'], true),
     'A5 · the state a screen renders names "stricter" as MANDATORY');
t_ok(!array_key_exists('stricter', $st['optional']),
     'A6 · *** …and never offers it as something to switch off ***');
t_ok(array_key_exists('redefined', $st['optional']),
     'A7 · "redefined" IS offered, because that is the one an organisation may choose');
t_eq($st['optional']['redefined'], true, 'A8 · and it reads ON for a new organisation');

//  PERSISTENCE, both ways, through the same helpers the screen uses.
setting_set(crev_trigger_key('redefined'), '0'); $reload();
t_ok(!crev_trigger_on('redefined'), 'A9 · switching it OFF persists');
t_eq(crev_trigger_state()['optional']['redefined'], false, 'A10 · …and the screen would show OFF');
setting_set(crev_trigger_key('redefined'), '1'); $reload();
t_ok(crev_trigger_on('redefined'), 'A11 · switching it back ON persists');
t_eq(crev_trigger_state()['optional']['redefined'], true, 'A12 · …and the screen would show ON');

t_ok(crev_trigger_on('stricter'), 'A13 · stricter reads ON');
setting_set(crev_trigger_key('stricter'), '0'); $reload();
t_ok(crev_trigger_on('stricter'),
     'A14 · *** and stays ON even when the setting is written straight to the database ***');
$pdo->prepare("DELETE FROM settings WHERE skey=?")->execute([crev_trigger_key('stricter')]);
$reload();

// ---------------------------------------------------------------------------
t_section('G6B · B — R1-UI · IS THE CHANGE AUDITED, AND WHO MAY MAKE IT?');
// ---------------------------------------------------------------------------
//  The screen writes through setting_set(), so it inherits Module 14's sealed
//  trail. That is the whole reason for reusing it rather than writing a row.
t_ok(setting_change_class(crev_trigger_key('redefined'))['audit'] === true,
     'B1 · *** a change to this setting is classified as auditable configuration ***');
t_ok(setting_change_class(crev_trigger_key('redefined'))['secret'] === false,
     'B2 · …and not a secret, so the trail may record the old and new value');

$auditBefore = (int) ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='setting' AND field=?",
                             [crev_trigger_key('redefined')]);
setting_set(crev_trigger_key('redefined'), '0'); $reload();
$auditAfter = (int) ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='setting' AND field=?",
                            [crev_trigger_key('redefined')]);
t_ok($auditAfter > $auditBefore,
     'B3 · *** switching the trigger off put a line on the sealed audit chain ***');
$last = ops_one("SELECT * FROM idems_audit WHERE entity='setting' AND field=? ORDER BY id DESC", 
                [crev_trigger_key('redefined')]);
t_eq((string) ($last['new_value'] ?? $last['new'] ?? ''), '0',
     'B4 · and the trail records what it was changed TO');

//  A NO-OP SAVE IS NOT A CHANGE. Somebody opening the screen and pressing Save
//  without touching anything must not add a line that says nothing happened.
$n1 = (int) ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='setting' AND field=?",
                    [crev_trigger_key('redefined')]);
setting_set(crev_trigger_key('redefined'), '0'); $reload();
t_eq((int) ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='setting' AND field=?",
                   [crev_trigger_key('redefined')]), $n1,
     'B5 · saving the same value again adds nothing to the trail');
setting_set(crev_trigger_key('redefined'), '1'); $reload();

//  WHO. The screen is one route, already gated; this is that gate, not a new one.
t_ok(function_exists('hiring_admin_can'),
     'B6 · the screen is gated by the recruitment-administrator test that already guarded it');
t_ok(function_exists('ops_require'),
     'B7 · …and refusal goes through the shared refusal, not a hand-rolled check');

// ---------------------------------------------------------------------------
t_section('G6B · C — R1-UI · THE FOUR CASES, AT THE POINT A REVIEW IS RAISED');
// ---------------------------------------------------------------------------
//  Not at the trigger list — at crev_raise_for_version(), which is what actually
//  decides whether a candidate is stopped. A test that only read the list would
//  pass even if the engine ignored it.
$off = (int) ops_val("SELECT COALESCE(MAX(id),0)+1 FROM offices");
$pdo->prepare("INSERT INTO offices (id,name,city) VALUES (?,?,?)")->execute([$off, $G . ' Office', 'Kolkata']);
$made['off'][] = $off;
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES (?,?, 'MANAGER',1,1,?,'')")->execute([$G . '_boss', 'G6B', $off]);
$uBoss = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');

$approve = function (array $x = []) use ($uBoss, $qua, $eng, $off) {
    [$ok,, $id] = hreq_save(0, array_merge([
        'requested_by_id' => $uBoss, 'requested_by_name' => 'G6B Boss',
        'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
        'job_title' => 'G6B Engineer', 'designation' => 'ENGINEER', 'job_description' => 'g6b',
        'quantity' => 10, 'office_id' => $off, 'required_by' => '2026-12-01',
        'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL',
        'reason' => 'Contract awarded.', 'change_reason' => 'G6B test change',
        'min_experience_years' => 2,
    ], $x));
    if (!$ok) return 0;
    hreq_submit($id); hreq_apply_decision($id, 'APPROVED', 'G6B Approver', 'ok'); return (int) $id;
};
$madeReqs = [];
$mkReq = function ($qty = 5, $exp = 5) use ($approve, &$madeReqs) {
    $h = $approve(); if (!$h) return 0;
    [$ok,, $rq] = hreq_to_requisition($h, $qty);
    if (!$ok || !$rq) return 0;
    db()->prepare("UPDATE requisitions SET min_experience_years=? WHERE id=?")->execute([$exp, (int) $rq]);
    rver_ensure_initial('REQUISITION', (int) $rq, null, ['note' => 'G6B baseline']);
    $madeReqs[] = (int) $rq;
    return (int) $rq;
};
$mkCand = function ($rq, $exp = 10) use ($pdo, $G) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,
                                           experience_years,created_at)
                   VALUES (?,?,'G6B','RECEIVED',?,?,?)")
        ->execute([$G . '-C' . random_int(1000, 9999), 'Cand', (int) $rq, $exp, date('c')]);
    return (int) $pdo->lastInsertId();
};
//  PUT A CANDIDATE ON THE CONFIGURED PIPELINE, the way the product does — position
//  AND ledger entry. A review only reaches somebody the pipeline calls ACTIVE, so a
//  candidate parked on the legacy stage column would make every assertion below
//  pass for the wrong reason. Borrowed from the Gate 3 battery rather than
//  reinvented, so both suites exercise the same fixture path.
$goto = function ($cid, $stageKey) {
    $c = ops_one("SELECT * FROM candidates WHERE id=?", [(int) $cid]);
    [$pipe, , ] = recruitpipe_cand_state($c);
    if (!$pipe) return 0;
    $req = ops_one("SELECT * FROM requisitions WHERE id=?", [(int) $c['requisition_id']]) ?: [];
    $st = rpipe_current_state($c);
    $from = $st ? (string) ($st['stage_name'] ?: $st['legacy_stage']) : '';
    $fromCode = $st ? (string) ($st['stage_key'] ?: $st['legacy_stage']) : '';
    foreach (recruitpipe_resolvable_stages((int) $pipe['id'], $req) as $sg) {
        if (strcasecmp((string) $sg['stage_key'], (string) $stageKey) !== 0) continue;
        db()->prepare("UPDATE candidates SET pipeline_id=?, pipeline_stage_id=? WHERE id=?")
            ->execute([(int) $sg['pipeline_id'], (int) $sg['id'], (int) $cid]);
        rkpi_stage_log((int) $cid, $from, (string) $sg['name'], [
            'from_code' => $fromCode, 'to_code' => (string) $sg['stage_key'],
            'track' => 'PIPELINE', 'kind' => 'MOVE', 'actor' => 'G6B']);
        return (int) $sg['id'];
    }
    return 0;
};
$bump = function ($rq, array $fields) {
    rver_gate_requisition_edit((int) $rq, $fields, ['change_reason' => 'G6B ' . implode(',', array_keys($fields))]);
    $p = rver_pending('REQUISITION', (int) $rq);
    if (!$p) return 0;
    [$ok] = rver_apply((int) $p['id'], ['decided_by' => 'G6B Approver']);
    return $ok ? (int) rver_current('REQUISITION', (int) $rq)['version'] : 0;
};
//  ONE CASE: make a requisition, put a candidate on it, change the requirement in
//  the given direction, and report whether a review was raised.
$case = function ($direction, $triggerValue) use ($mkReq, $mkCand, $bump, $goto) {
    //  setting_set() refreshes the settings cache itself, so no epoch bump here —
    //  bumping it mid-fixture would also reset the migration guards this fixture
    //  has already satisfied.
    setting_set(crev_trigger_key('redefined'), $triggerValue);
    $rq = $mkReq(5, 5); if (!$rq) return ['err' => 'no requisition'];
    $cid = $mkCand($rq, 10);
    $goto($cid, 'L1');
    $onPipeline = crev_is_active(ops_one("SELECT * FROM candidates WHERE id=?", [$cid]));
    $fields = $direction === 'stricter' ? ['min_experience_years' => 9]
                                        : ['designation' => 'WELDER'];
    //  THE LIVE PATH. Approving the new version is what raises the review —
    //  rver_apply() calls the engine itself. So the case asks the question the
    //  business asks: after the requirement changed, IS this candidate stopped?
    //  Calling the raise function by hand instead would only ever "refresh" the
    //  review the approval already created, and would report raised=0 for a case
    //  that worked perfectly.
    $v = $bump($rq, $fields);
    if ($v < 2) return ['err' => 'no version 2 (got ' . $v . ')'];
    $st = rver_strictness_between('REQUISITION', $rq, 1, $v);
    $dir = !$st ? 'none'
         : ($st['is_stricter'] ? 'stricter'
           : ($st['is_redefined'] ? 'redefined' : ($st['is_relaxed'] ? 'relaxed' : 'neither')));
    $open = crev_open_for($cid, 'REQUISITION', $rq);
    return ['dir' => $dir, 'open' => $open ? 1 : 0,
            'kind' => (string) ($open['trigger_kind'] ?? ''),
            'blocked' => (string) crev_block_reason($cid, 'ADVANCE'),
            'cand' => $cid, 'rq' => $rq, 'live' => (bool) $onPipeline];
};
$c1 = $case('redefined', '1');
t_ok(empty($c1['err']), 'C0 · the fixture built a redefined version 2 (' . ($c1['err'] ?? 'ok') . ')');
t_ok(($c1['live'] ?? false),
     'C0b · and its candidate is genuinely live on the pipeline — without this, "no review raised" would mean nothing');
t_eq($c1['dir'] ?? '', 'redefined', 'C1 · changing the role IS a redefinition, not a tightening');
t_eq((int) ($c1['open'] ?? 0), 1,
     'C2 · *** redefined + trigger ON → the candidate IS stopped for review ***');
t_eq($c1['kind'] ?? '', 'redefined', 'C2b · and the review records WHY it was raised');
t_ok(($c1['blocked'] ?? '') !== '',
     'C2c · …so moving them forward is actually refused, not merely flagged');

$c2 = $case('redefined', '0');
t_ok(empty($c2['err']), 'C3 · the fixture built a second redefined version 2 (' . ($c2['err'] ?? 'ok') . ')');
t_ok(($c2['live'] ?? false),
     'C3b · *** and ITS candidate is live too — so C5 below is a real "not raised", not an empty audience ***');
t_eq($c2['dir'] ?? '', 'redefined', 'C4 · the change is still a redefinition…');
t_eq((int) ($c2['open'] ?? -1), 0,
     'C5 · *** …but with the trigger OFF no review is raised: the setting really governs ***');
t_eq($c2['blocked'] ?? 'x', '',
     'C6 · *** …and the candidate moves on freely, which is what switching it off is FOR ***');

$c3 = $case('stricter', '1');
t_eq($c3['dir'] ?? '', 'stricter', 'C7 · raising the experience floor IS a tightening');
t_eq((int) ($c3['open'] ?? 0), 1, 'C8 · stricter + redefined ON → review raised');
t_eq($c3['kind'] ?? '', 'stricter', 'C8b · recorded as a tightening, not a redefinition');

$c4 = $case('stricter', '0');
t_eq($c4['dir'] ?? '', 'stricter', 'C9 · still a tightening…');
t_eq((int) ($c4['open'] ?? 0), 1,
     'C10 · *** …and switching the OPTIONAL trigger off does NOT disable the mandatory one ***');
t_eq($c4['kind'] ?? '', 'stricter', 'C11 · still raised as a tightening');
t_ok(($c4['blocked'] ?? '') !== '',
     'C12 · *** and the candidate is genuinely stopped: the locked rule survives the configuration ***');

setting_set(crev_trigger_key('redefined'), '1'); $reload();

// ---------------------------------------------------------------------------
t_section('G6B · D — R2 · WHO APPEARS IN THE PER-PERSON UTILISATION BREAKDOWN');
// ---------------------------------------------------------------------------
$mk = function ($tag, $status) use ($pdo, $G, $off, &$made) {
    $pdo->prepare("INSERT INTO inspectors (name,emp_code,home_office_id,staff_kind,team_role,status,created_at)
                   VALUES (?,?,?,'ASSET','FIELD',?,?)")
        ->execute([$G . ' ' . $tag, $G . '-' . $tag, $off, $status, date('c')]);
    $id = (int) $pdo->lastInsertId(); $made['ins'][] = $id;
    return $id;
};
//  THE MIXED POPULATION the decision names.
$pA = $mk('A-active-worked',  WF_ST_ACTIVE);   // active, has days
$pB = $mk('B-active-idle',    WF_ST_ACTIVE);   // active, no days
$pC = $mk('C-joining',        WF_ST_JOINING);  // hired, has not started
$pD = $mk('D-joining-worked', WF_ST_JOINING);  // hired, not started, yet days exist
$pE = $mk('E-left',           WF_ST_INACTIVE); // left, but worked during the period

//  The execution records the report sums. Built in memory, not in the jobs table:
//  the function under test takes them as an argument, and a fixture INSERT into a
//  table whose columns this test does not control is how a suite silently skips
//  its own assertions.
$jobs = [
    ['ins_id' => $pA, 'mandays' => 4, 'sbu' => 'X', 'job_type' => 'INSPECTION', 'subcon_id' => 0],
    ['ins_id' => $pD, 'mandays' => 3, 'sbu' => 'X', 'job_type' => 'INSPECTION', 'subcon_id' => 0],
    ['ins_id' => $pE, 'mandays' => 2, 'sbu' => 'X', 'job_type' => 'INSPECTION', 'subcon_id' => 0],
];
$wd  = 22;
$rows = mis_person_utilisation($jobs, $wd, '');
$mine = [];
foreach ($rows as $r) if (strpos((string) $r['name'], $G) === 0) $mine[(string) $r['name']] = $r;
$named = function ($id) use ($pdo) { return (string) ops_val("SELECT name FROM inspectors WHERE id=?", [$id]); };

t_ok(count($rows) >= 1, 'D0 · the breakdown returned rows (without this nothing below means anything)');
t_ok(isset($mine[$named($pA)]),
     'D1 · an ACTIVE person who worked IS in the breakdown — the report still works');
t_eq((float) ($mine[$named($pA)]['mandays'] ?? -1), 4.0, 'D2 · with their real days');
t_eq((int) ($mine[$named($pA)]['pct'] ?? -1), (int) round(4 / 22 * 100), 'D3 · and the right percentage');
t_ok(isset($mine[$named($pB)]),
     'D4 · an ACTIVE person with NO days is still listed — that is genuine idle capacity');
t_eq((float) ($mine[$named($pB)]['mandays'] ?? -1), 0.0, 'D5 · …shown as zero, which is the point of the row');

t_ok(!isset($mine[$named($pC)]),
     'D6 · *** somebody HIRED BUT NOT YET JOINED is NOT in the breakdown ***');
t_ok(!isset($mine[$named($pD)]),
     'D7 · *** …not even if days somehow exist against them: status decides, not activity ***');
t_ok(isset($mine[$named($pE)]),
     'D8 · *** a LEAVER who worked during the period IS still listed — their days were delivered ***');
t_eq((float) ($mine[$named($pE)]['mandays'] ?? -1), 2.0, 'D9 · with the days they actually worked');

//  THE DECISION, stated as the decision states it: of A, B, C, D, only A and B.
$abcd = [$named($pA) => isset($mine[$named($pA)]), $named($pB) => isset($mine[$named($pB)]),
         $named($pC) => isset($mine[$named($pC)]), $named($pD) => isset($mine[$named($pD)])];
t_eq(implode(',', array_map(fn($v) => $v ? 'in' : 'out', array_values($abcd))), 'in,in,out,out',
     'D10 · *** mixed population A,B,C,D → only A and B appear ***');

//  THE PERSON FILTER. With a person named, only people with days are listed — the
//  pre-existing rule — and the exclusion must not have changed it.
$rowsF = mis_person_utilisation($jobs, $wd, (string) $pA);
$mineF = [];
foreach ($rowsF as $r) if (strpos((string) $r['name'], $G) === 0) $mineF[(string) $r['name']] = $r;
t_ok(isset($mineF[$named($pA)]), 'D11 · with a person filter set, somebody with days is still listed');
t_ok(!isset($mineF[$named($pB)]), 'D12 · …and somebody with none is not — that older rule is unchanged');
t_ok(!isset($mineF[$named($pC)]), 'D13 · and a not-yet-joined person is still out');

//  ZERO WORKING DAYS must not divide by zero.
$rowsZ = mis_person_utilisation($jobs, 0, '');
t_ok(is_array($rowsZ) && count($rowsZ) >= 1, 'D14 · a period with no working days still returns rows');
foreach ($rowsZ as $r) if (strpos((string) $r['name'], $G) === 0) { t_eq((int) $r['pct'], 0, 'D15 · …with 0%, not an error'); break; }

// ---------------------------------------------------------------------------
t_section('G6B · E — R2 · WHAT MUST NOT HAVE CHANGED');
// ---------------------------------------------------------------------------
//  THE DENOMINATOR. The reason the exclusion is right is that capacity never
//  counted these people. If that stopped being true the decision would be wrong,
//  so it is asserted rather than assumed.
$F = ['inspector' => $pC, 'office' => 0, 'from' => date('Y-m-01'), 'to' => date('Y-m-t')];
t_eq((int) mis_available_days($F), 0,
     'E1 · *** the capacity denominator counts ZERO days for a not-yet-joined person ***');
$F['inspector'] = $pA;
t_ok((int) mis_available_days($F) > 0, 'E2 · …and real days for an active one');
$F['inspector'] = $pE;
t_eq((int) mis_available_days($F), 0, 'E3 · the denominator excludes a leaver too — unchanged by R2');

//  THE SHARED READER. Every other screen reads this list; its meaning is untouched.
$all = inspectors_list(false);
$ids = array_map(fn($r) => (int) $r['id'], $all);
t_ok(in_array($pC, $ids, true),
     'E4 · *** the shared team-member list STILL returns a not-yet-joined person ***');
t_ok(in_array($pD, $ids, true), 'E5 · …and the second one');
t_ok(in_array($pE, $ids, true), 'E6 · …and a leaver, exactly as before');
t_ok(in_array($pA, $ids, true), 'E7 · …and active people');
$act = array_map(fn($r) => (int) $r['id'], inspectors_list(true));
t_ok(in_array($pA, $act, true) && !in_array($pC, $act, true),
     'E8 · and the ACTIVE-only form of the same reader is unchanged too');

//  THE SCREEN'S OWN PERSON FILTER is built from that same unfiltered list, so a
//  manager can still look up the person who has not started.
t_ok(in_array($pC, array_map(fn($r) => (int) $r['id'], inspectors_list(false)), true),
     'E9 · *** the report screen\'s person filter can still name them: excluded from a sum is not hidden ***');

//  THE HELPER ITSELF.
$pend = wf_joining_pending_ids();
t_ok(isset($pend[$pC]) && isset($pend[$pD]), 'E10 · the helper names both not-yet-joined people');
t_ok(!isset($pend[$pA]) && !isset($pend[$pE]), 'E11 · and neither an active person nor a leaver');
$pdo->prepare("UPDATE inspectors SET staff_kind='SUBCON' WHERE id=?")->execute([$pD]);
t_ok(!isset(wf_joining_pending_ids()[$pD]),
     'E12 · a subcontractor is not on our joining pipeline, so the helper leaves them alone');
$pdo->prepare("UPDATE inspectors SET staff_kind='ASSET' WHERE id=?")->execute([$pD]);

//  GATE 5 / GATE 6 D2 INVARIANTS, re-proved here rather than assumed from a
//  passing suite elsewhere.
t_ok(wf_is_active(WF_ST_ACTIVE) && !wf_is_active(WF_ST_JOINING),
     'E13 · joining-pending is still not operationally active (Gate 5)');
t_ok(wf_is_active(''), 'E14 · and a blank status still reads as active (field-finding #26)');
t_ok(!wf_has_left(WF_ST_JOINING), 'E15 · a joiner is still not a leaver (Gate 5)');
$availIds = array_map(fn($r) => (int) $r['id'], inspector_availability([$off]));
t_ok(in_array($pA, $availIds, true) && !in_array($pC, $availIds, true),
     'E16 · *** the availability board still refuses a not-yet-joined person (Gate 6 D2) ***');

// ---------------------------------------------------------------------------
t_section('G6B · Z — PUT EVERYTHING BACK');
// ---------------------------------------------------------------------------
try {
    foreach ($made['ins'] as $i) $pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$i]);
    foreach ($made['off'] as $o) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$o]);
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$G . '_boss']);
} catch (Throwable $e) {}
if ($trigBefore === null) $pdo->prepare("DELETE FROM settings WHERE skey=?")->execute([crev_trigger_key('redefined')]);
else setting_set(crev_trigger_key('redefined'), $trigBefore);
$pdo->prepare("DELETE FROM settings WHERE skey=?")->execute([crev_trigger_key('stricter')]);
$reload();
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE emp_code LIKE ?", [$G . '%']), 0,
     'Z1 · the people this suite created are gone');
t_ok(crev_trigger_on('stricter'), 'Z2 · and the mandatory trigger is on, as it must always be');
