<?php
// ============================================================================
//  PHASE 4 — THE NEGATIVE SECURITY MATRIX
//
//  Hidden button ≠ security. Disabled button ≠ security. Locked screen ≠
//  security. Every probe here calls the PRODUCTION function a crafted POST would
//  reach, with no screen in the way, and asserts BOTH that the operation was
//  refused AND that nothing was written.
//
//  Covered: entitlement (§42), permission / RBAC (§41), branch scope (§44),
//  tenant boundary (§43), malformed input (§45), TOCTOU / stale save (§46),
//  duplicate-requirement attacks (§51–52).
// ============================================================================

t_section('Phase 4 — the negative security matrix');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate(); rful_migrate();
$s4o = $_SESSION;
foreach ([[9651, 'P4S Branch A'], [9652, 'P4S Branch B']] as $o)
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); } catch (Throwable $e) {}
$s4mk = function ($un, $role, $super, $office, $scope) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,'P4S',?,1,?,?,?)")->execute([$un, $role, $super ? 1 : 0, $office, $scope]);
    return (int) $pdo->lastInsertId(); };
$s4act = function ($u) { $_SESSION['uid'] = $u; current_user(true); ua(true); };
$uBoss = $s4mk('p4s_boss', 'MANAGER', 1, 9651, '');
$uCoA  = $s4mk('p4s_coord_a', 'COORDINATOR', 0, 9651, '9651');   // branch A only
$uCoB  = $s4mk('p4s_coord_b', 'COORDINATOR', 0, 9652, '9652');   // branch B only
$uInsp = $s4mk('p4s_insp', 'INSPECTOR', 0, 9651, '9651');        // no coordinator authority
$s4act($uBoss);

$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$s4base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uBoss, 'requested_by_name' => 'P4S Boss',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'P4S Engineer', 'designation' => 'ENGINEER', 'job_description' => 'p4s',
    'quantity' => 10, 'office_id' => 9651, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x'], $x);
$s4req = function ($qty, array $x = []) use ($s4base) {
    [$ok,, $h] = hreq_save(0, $s4base(['quantity' => $qty] + $x)); if (!$ok) return 0;
    hreq_submit($h); hreq_apply_decision($h, 'APPROVED', 'P4S Approver', 'ok');
    [$okR,, $rq] = hreq_to_requisition($h, $qty); return $okR ? (int) $rq : 0; };
$s4cand = function ($req, $stage = 'RECEIVED') use ($pdo) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,created_at)
                   VALUES (?,'P4S','C',?,?,?)")->execute(['P4SC-' . bin2hex(random_bytes(3)), $stage, $req, date('c')]);
    return (int) $pdo->lastInsertId(); };
$nAlloc = fn($rq) => (int) ops_val("SELECT COUNT(*) FROM requisition_allocations WHERE requisition_id=?", [$rq]);
$qtyOf  = fn($a) => (int) ops_val("SELECT allocated_qty FROM requisition_allocations WHERE id=?", [$a]);

$rqA = $s4req(10, ['job_title' => 'P4S Branch A ten', 'office_id' => 9651]);
$s4act($uBoss);
$aliveA = rful_allocate($rqA, 'OWN_PAYROLL', 4)['id'];

// ---- S1 · PERMISSION (§41) --------------------------------------------------
t_section('S1 · permission is decided at the write, not by the screen');
$s4act($uInsp);
$r = rful_allocate($rqA, 'MANPOWER_AGENCY', 1);
t_eq($r['code'], 'NO_PERMISSION', 'S1.1 · an inspector cannot promise seats to a source');
t_eq($nAlloc($rqA), 1, 'S1.2 · …and nothing was written');
t_eq(rful_reallocate($aliveA, 9)['code'], 'NO_PERMISSION', 'S1.3 · …nor resize one');
t_eq($qtyOf($aliveA), 4, 'S1.4 · …and it did not move');
t_eq(rful_close($aliveA, 'CANCELLED')['code'], 'NO_PERMISSION', 'S1.5 · …nor cancel one');
t_eq(strtoupper((string) rful_get($aliveA)['status']), 'PLANNED', 'S1.6 · …and it is still planned');
$icand = $s4cand($rqA, 'ACCEPTED');
t_eq(rful_attach($icand, $aliveA)['code'], 'NO_PERMISSION', 'S1.7 · …nor credit a source with somebody');
t_eq((int) ops_val("SELECT COALESCE(allocation_id,0) FROM candidates WHERE id=?", [$icand]), 0,
     'S1.8 · …and no credit was written');

// ---- S2 · BRANCH SCOPE (§44) ------------------------------------------------
t_section('S2 · a record id is not proof of authorisation');
$s4act($uCoB);                                    // a real coordinator, wrong branch
t_eq(rful_allocate($rqA, 'SUPPLIER', 2)['code'], 'OUT_OF_SCOPE',
     'S2.1 · branch B cannot source branch A’s requirement');
t_eq($nAlloc($rqA), 1, 'S2.2 · …and nothing was written');
t_eq(rful_reallocate($aliveA, 8)['code'], 'OUT_OF_SCOPE', 'S2.3 · …nor resize its allocation');
t_eq($qtyOf($aliveA), 4, 'S2.4 · …and it did not move');
t_eq(rful_close($aliveA, 'RELEASED')['code'], 'OUT_OF_SCOPE', 'S2.5 · …nor release it');
t_eq(rful_attach($icand, $aliveA)['code'], 'OUT_OF_SCOPE', 'S2.6 · …nor credit it with anybody');
$s4act($uCoA);
t_eq(rful_allocate($rqA, 'SUPPLIER', 2)['code'], 'OK', 'S2.7 · the coordinator who OWNS the branch can');
t_eq(rful_summary($rqA)['allocated'], 6, 'S2.8 · …and it landed');

// ---- S3 · ENTITLEMENT (§42) -------------------------------------------------
//  The module is switched off in the workspace's own PAID CEILING — the same
//  setting a real unpaid installation has — and the answer is read through the
//  product's own choke point. Nothing is stubbed; no function is replaced.
t_section('S3 · a workspace that has not bought hiring cannot source anything');
$s4act($uBoss);
$s4tenant = $GLOBALS['__tenant'] ?? null;
$s4ceil   = setting_get('saas_entitled_modules', '');
$s4off    = setting_get('modules_off', '');
$s4set = function ($csv) use ($uBoss) {
    setting_set('saas_entitled_modules', $csv);
    setting_set('modules_off', '');
    $GLOBALS['__tenant'] = ['key' => 'p4co', 'company' => 'P4 Co'];
    licence_disabled(true);
    $_SESSION['uid'] = $uBoss; current_user(true); ua(true); };

$s4set('operations,admin');                               // everything BUT people & hiring
t_ok(licence_blocks('mod.hiring.view'),
     'S3.1 · with hiring outside the paid ceiling, the module is blocked');
$offBefore = $nAlloc($rqA);
t_eq(rful_allocate($rqA, 'FREELANCER', 1)['code'], 'NO_ENTITLEMENT', 'S3.2 · sourcing is refused');
t_eq($nAlloc($rqA), $offBefore, 'S3.3 · …and nothing was written');
t_eq(rful_reallocate($aliveA, 9)['code'], 'NO_ENTITLEMENT', 'S3.4 · a resize is refused');
t_eq($qtyOf($aliveA), 4, 'S3.5 · …and it did not move');
t_eq(rful_close($aliveA, 'RELEASED')['code'], 'NO_ENTITLEMENT', 'S3.6 · a release is refused');
t_eq(rful_attach($icand, $aliveA)['code'], 'NO_ENTITLEMENT', 'S3.7 · a credit is refused');
t_eq((int) ops_val("SELECT COALESCE(allocation_id,0) FROM candidates WHERE id=?", [$icand]), 0,
     'S3.8 · …and none was written');
//  A superuser is not an exception. No master bypass of entitlement.
t_ok((bool) (current_user()['is_superuser'] ?? 0), 'S3.9 · the actor above IS a superuser…');
t_eq(rful_allocate($rqA, 'SUPPLIER', 1)['code'], 'NO_ENTITLEMENT',
     'S3.10 · …and is refused just the same — no master bypass of entitlement');

$s4set('hr,operations,admin');                            // bought again
t_eq(licence_blocks('mod.hiring.view'), false, 'S3.11 · with hiring paid for, the module is open');
t_eq(rful_allocate($rqA, 'FREELANCER', 1)['code'], 'OK', 'S3.12 · …and the very same call now succeeds');
rful_close((int) ops_val("SELECT id FROM requisition_allocations WHERE requisition_id=? ORDER BY id DESC", [$rqA]),
           'CANCELLED', ['reason' => 'S3 fixture']);
setting_set('saas_entitled_modules', $s4ceil);
setting_set('modules_off', $s4off);
$GLOBALS['__tenant'] = $s4tenant;
licence_disabled(true);
$s4act($uBoss);
t_eq(licence_blocks('mod.hiring.view'), false, 'S3.13 · the workspace was restored for the rest of the battery');

// ---- S4 · MALFORMED INPUT (§45) ---------------------------------------------
t_section('S4 · nothing is coerced into an identity or a quantity');
$s4act($uCoA);
$rqM = $s4req(6, ['job_title' => 'P4S Malformed', 'office_id' => 9651]);
$s4act($uCoA);
foreach ([
    ['an array',            [1]],
    ['a word',              'agency'],
    ['a boolean',           true],
    ['a negative number',   -3],
    ['scientific notation', '1e3'],
    ['null',                null],
    ['an empty string',     ''],
] as $bad) {
    $r = rful_allocate($rqM, $bad[1], 2);
    t_ok(in_array($r['code'], ['BAD_SOURCE', 'BAD_VALUE'], true),
         'S4 source · ' . $bad[0] . ' is refused, not coerced (' . $r['code'] . ')');
}
t_eq($nAlloc($rqM), 0, 'S4.8 · seven malformed sources wrote nothing');
foreach ([['an array', [2]], ['a word', 'two'], ['a boolean', true], ['a float', 2.5],
          ['a negative', -2], ['zero', 0], ['scientific notation', '2e1'], ['a padded string', ' 2 ']] as $bad) {
    $r = rful_allocate($rqM, 'SUPPLIER', $bad[1]);
    t_eq($r['code'], 'BAD_QUANTITY', 'S4 qty · ' . $bad[0] . ' is refused');
}
t_eq($nAlloc($rqM), 0, 'S4.17 · eight malformed quantities wrote nothing');
$mA = rful_allocate($rqM, 'SUPPLIER', 3)['id'];
foreach ([['an array', [1]], ['a word', 'abc'], ['a negative', -1], ['a float', 1.5]] as $bad) {
    $r = rful_attach($s4cand($rqM, 'ACCEPTED'), $bad[1]);
    t_eq($r['code'], 'BAD_VALUE', 'S4 link · ' . $bad[0] . ' is not an allocation id');
}
t_eq(rful_fulfilled($mA), 0, 'S4.22 · four malformed links credited nobody');
//  …and a malformed value must never be READ as "nothing", which in this engine
//  would mean "clear the link" — a silent deletion.
$mc = $s4cand($rqM, 'ACCEPTED'); rful_attach($mc, $mA);
t_eq(rful_fulfilled($mA), 1, 'S4.23 · one person is credited to the supplier');
t_eq(rful_attach($mc, 'abc')['code'], 'BAD_VALUE', 'S4.24 · a malformed link value is refused…');
t_eq(rful_fulfilled($mA), 1, 'S4.25 · …and did NOT quietly clear the credit');

// ---- S5 · A SOURCE ENTITY THAT IS NOT IN THIS WORKSPACE (§43) ---------------
t_section('S5 · the named supplier must be on file HERE');
//  Its OWN requirement. S4 deliberately exhausted $rqM, and a probe that inherits
//  another section's leftovers tests that section, not this one.
$rqE = $s4req(6, ['job_title' => 'P4S Entity', 'office_id' => 9651]);
$s4act($uCoA);
t_eq(rful_allocate($rqE, 'MANPOWER_AGENCY', 1, ['source_entity_id' => 987654])['code'],
     'SOURCE_ENTITY_UNKNOWN', 'S5.1 · a partner id from nowhere is refused');
t_eq(rful_allocate($rqE, 'MANPOWER_AGENCY', 1, ['source_entity_id' => [1]])['code'], 'BAD_VALUE',
     'S5.4 · an array is not an entity id');
//  The fixture is built with no try/catch: if a supplier cannot be put on file,
//  this battery must FAIL loudly rather than quietly drop its two best probes.
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_vendor,status) VALUES (?,?,1,'ACTIVE')")
    ->execute(['P4S Sterling Manpower', 'Sterling']);
$sBP = (int) $pdo->lastInsertId();
t_ok($sBP > 0, 'S5.2 · a supplier is on file in THIS workspace');
t_eq(rful_allocate($rqE, 'MANPOWER_AGENCY', 1, ['source_entity_id' => $sBP])['code'], 'OK',
     'S5.3 · …and can be named as the source');
t_eq(rful_allocate($rqE, 'OWN_PAYROLL', 1, ['source_entity_id' => $sBP])['code'],
     'SOURCE_ENTITY_UNKNOWN', 'S5.5 · own payroll has no supplier to point at, so naming one is refused');
t_eq(rful_allocate($rqE, 'MANPOWER_AGENCY', 1, ['source_entity_id' => $sBP + 500000])['code'],
     'SOURCE_ENTITY_UNKNOWN', 'S5.6 · a neighbouring id is not a licence to point at another workspace’s record');
//  A source whose entity lives behind a module the workspace has not bought is
//  refused outright — hiding the option from the dropdown is not a control.
$s4cx = $GLOBALS['__tenant'] ?? null;
$s4cOff = setting_get('saas_entitled_modules', '');
setting_set('saas_entitled_modules', 'hr,operations,admin');   // hiring yes, connect no
$GLOBALS['__tenant'] = ['key' => 'p4co', 'company' => 'P4 Co'];
licence_disabled(true); $s4act($uCoA);
if (function_exists('connect_enabled') && !connect_enabled()) {
    t_eq(rful_allocate($rqE, 'MARKETPLACE', 1, ['source_entity_id' => 1])['code'], 'NO_ENTITLEMENT',
         'S5.7 · a marketplace source is refused without the marketplace module');
} else {
    t_ok(false, 'S5.7 · INVALID PROBE — the marketplace module could not be switched off, so this claim was not tested');
}
setting_set('saas_entitled_modules', $s4cOff);
$GLOBALS['__tenant'] = $s4cx; licence_disabled(true); $s4act($uCoA);
// ---- S6 · STALE SAVE / TOCTOU (§46) -----------------------------------------
t_section('S6 · a screen left open cannot overwrite what happened meanwhile');
$rqT = $s4req(10, ['job_title' => 'P4S Stale', 'office_id' => 9651]);
$s4act($uCoA);
$seen = rful_summary($rqT)['allocated'];                       // the screen renders: 0 allocated
$t1 = rful_allocate($rqT, 'OWN_PAYROLL', 7)['id'];             // somebody else acts
$r = rful_allocate($rqT, 'SUPPLIER', 5, ['expect_allocated' => $seen]);
t_eq($r['code'], 'STALE', 'S6.1 · the stale form is refused, not added on top');
t_eq(rful_summary($rqT)['allocated'], 7, 'S6.2 · …and nothing was written');
t_eq(rful_allocate($rqT, 'SUPPLIER', 3, ['expect_allocated' => 7])['code'], 'OK',
     'S6.3 · a form that reloaded first is accepted');
t_eq(rful_reallocate($t1, 5, ['expect' => 9])['code'], 'STALE', 'S6.4 · a stale resize is refused');
t_eq($qtyOf($t1), 7, 'S6.5 · …and it did not move');
t_eq(rful_reallocate($t1, 5, ['expect' => 'seven'])['code'], 'STALE',
     'S6.6 · a MALFORMED expectation is stale, never ignored');
t_eq($qtyOf($t1), 7, 'S6.7 · …and it did not move either');
t_eq(rful_reallocate($t1, 5, ['expect' => 7])['code'], 'OK', 'S6.8 · a truthful expectation is accepted');

// ---- S7 · ONE DEMAND CANNOT BE PAID FOR TWICE (§51–52) ----------------------
t_section('S7 · sourcing never becomes a second requirement');
$rqD1 = $s4req(5, ['job_title' => 'P4S Dup', 'office_id' => 9651]);
$s4act($uCoA);
$d1 = rful_allocate($rqD1, 'MANPOWER_AGENCY', 3)['id'];
$d2 = rful_allocate($rqD1, 'SUBCON_AGENCY', 2)['id'];
t_eq((int) ops_val("SELECT COUNT(*) FROM requisitions WHERE id=?", [$rqD1]), 1,
     'S7.1 · two sources, still exactly one requisition');
t_eq((int) reqf_counts($rqD1)['requested'], 5, 'S7.2 · the approved headcount was not multiplied');
t_eq((int) ops_val("SELECT quantity FROM requisitions WHERE id=?", [$rqD1]), 5,
     'S7.3 · sourcing never writes to the approved quantity');
//  …and an allocation cannot be re-pointed at a different requirement to make
//  one approval pay for two.
try { $pdo->prepare("UPDATE requisition_allocations SET requisition_id=? WHERE id=?")->execute([$rqM, $d1]); }
catch (Throwable $e) {}
$dc = $s4cand($rqD1, 'ACCEPTED');
t_eq(rful_attach($dc, $d1)['code'], 'NO_ALLOCATION',
     'S7.4 · a moved allocation cannot be credited from its old requirement');
$pdo->prepare("UPDATE requisition_allocations SET requisition_id=? WHERE id=?")->execute([$rqD1, $d1]);

// ---- S8 · THE EXECUTION BOUNDARY (§24) --------------------------------------
t_section('S8 · a requirement that may not execute may not be sourced');
$rqX = $s4req(5, ['job_title' => 'P4S Cancelled', 'office_id' => 9651]);
$s4act($uCoA);
$x1 = rful_allocate($rqX, 'SUPPLIER', 2)['id'];
$pdo->prepare("UPDATE requisitions SET status='CANCELLED' WHERE id=?")->execute([$rqX]);
$r = rful_allocate($rqX, 'MANPOWER_AGENCY', 1);
t_eq($r['code'], 'EXECUTION_BLOCKED', 'S8.1 · a cancelled requirement cannot take a new source');
t_eq($nAlloc($rqX), 1, 'S8.2 · …and nothing was written');
t_eq(rful_reallocate($x1, 4)['code'], 'EXECUTION_BLOCKED', 'S8.3 · …nor grow an existing one');
t_eq($qtyOf($x1), 2, 'S8.4 · …and it did not move');
t_eq(rful_close($x1, 'RELEASED', ['reason' => 'requirement cancelled'])['code'], 'OK',
     'S8.5 · but its seats CAN still be given back — tidying up is not execution');

// ---- S9 · A REFUSAL NEVER DESCRIBES A RECORD YOU MAY NOT SEE ----------------
t_section('S9 · refusals do not leak');
$s4act($uCoB);                                                 // wrong branch
$outOfScope = rful_allocate($rqA, 'SUPPLIER', 99999);          // ALSO an impossible quantity
t_eq($outOfScope['code'], 'OUT_OF_SCOPE',
     'S9.1 · scope is answered BEFORE the quantity, so a refusal cannot be used to probe the ceiling');
$ghost = rful_allocate(99999999, 'SUPPLIER', 1);
t_eq($ghost['code'], 'NO_REQUISITION', 'S9.2 · a requirement that does not exist says only that');
$s4act($uInsp);
t_eq(rful_allocate($rqA, 'SUPPLIER', 99999)['code'], 'NO_PERMISSION',
     'S9.3 · permission is answered before scope, and scope before anything about the record');

$_SESSION = $s4o; current_user(true); ua(true);
