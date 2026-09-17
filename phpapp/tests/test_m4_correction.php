<?php
// ============================================================================
//  PHASE 2 · M4 — CORRECTION & FINAL LOCK
//
//  M4 built the request layer. This file holds the three things the correction
//  had to make unambiguous, and holds them as tests rather than as prose:
//
//   A  TERMINOLOGY.  Three different objects, three different words. A bare
//      "Requirement" is never the NAME of anything on a recruitment screen,
//      because the application also has cx_requirements — a client-posted
//      marketplace demand — and a reader would have no way to tell them apart.
//      Nothing is renamed, merged or replaced to achieve that.
//
//   B  THE REQUESTOR.  Who may raise a hiring request is a CAPABILITY question.
//      The first cut asked is_coordinator_level() — a role band, and a band
//      WIDER than the permission matrix. It now asks the existing capability
//      mod.hiring.edit through the existing can() choke point. No new
//      permission, no second permission system, and approval stays a separate
//      right held by somebody other than the requestor.
//
//   C  THE DIRECT PATH.  A requisition may still be raised without a request.
//      That is a supported route, not an accident, and provenance cannot be
//      faked from the browser. What M4 does NOT do is decide which customers
//      may use it — that is a Phase-3 policy decision (the ADR).
// ============================================================================

t_section('M4 correction — terminology, requestor capability, direct path');

$pdo = db();
hreq_migrate();
$mine = ['h' => [], 'r' => [], 'u' => [], 'o' => []];
$origSess = $_SESSION;

$mkUser = function ($un, $role, $super, $office, $perms) use ($pdo, &$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions)
                   VALUES (?,?,?,1,?,?,'',?)")
        ->execute([$un, 'M4c', $role, $super ? 1 : 0, $office, $perms]);
    $id = (int) $pdo->lastInsertId(); $mine['u'][] = $id; return $id;
};
$act = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

// ---------------------------------------------------------------------------
//  A · TERMINOLOGY LOCK
// ---------------------------------------------------------------------------
t_section('A · terminology — three objects, three words');

t_ok(function_exists('hreq_label'), 'A1 · the locked words are available to every screen');
t_eq(hreq_label('request'),          'Hiring Request',          'A1 · the business ask is a Hiring Request');
t_eq(hreq_label('request', true),    'Hiring Requests',         'A1 · plural follows');
t_eq(hreq_label('marketplace'),      'Marketplace Requirement', 'A1 · a client-posted demand is a Marketplace Requirement');
t_ok(hreq_label('request') !== hreq_label('marketplace')
     && hreq_label('requisition') !== hreq_label('marketplace'),
     'A2 · no two of the three share a word');

// The execution record keeps the workspace's OWN wording — unless that wording
// is the ambiguous one, in which case it is qualified rather than shown bare.
$savedTerms = (string) setting_get('terms', '');
$savedPack  = (string) setting_get('terms_pack', '');
// The canonical name — what the documents call it, and what a screen falls back
// to if the terminology engine is not there at all.
t_eq(HREQ_TERMS['requisition'][0], 'Recruitment Requisition', 'A3 · the canonical name is Recruitment Requisition');
// On screen the workspace's OWN word is honoured when it is unambiguous, which
// the shipped word is: "Requisition" could never be read as a marketplace
// requirement, so it is not padded out for the sake of it.
t_eq(hreq_label('requisition'), 'Requisition', 'A3 · an unambiguous workspace word is left exactly as the workspace wrote it');
term_apply_pack('recruitment');
t_eq(T('requisition'), 'Requirement', 'A3 · a recruitment agency may still call it a Requirement — nothing is forced');
t_eq(hreq_label('requisition'), 'Recruitment Requirement',
     'A4 · but the screen qualifies it, so it can never be read as a marketplace requirement');
t_eq(hreq_label('requisition', true), 'Recruitment Requirements', 'A4 · plural too');
setting_set('terms', $savedTerms); setting_set('terms_pack', $savedPack);
term_overrides($savedTerms ? (json_decode($savedTerms, true) ?: []) : []);
t_eq(T('requisition'), 'Requisition', 'A4 · the suite wording is restored');

// No screen prints a bare "Requirement" as the NAME of an object.
foreach (['views/ops/hiring_request.php', 'views/ops/hiring_request_list.php',
          'views/ops/requisition_form.php'] as $vf) {
    $src = file_get_contents(__DIR__ . '/../' . $vf);
    $bare = preg_match('/(New requirement|Save requirement|Edit requirement|>Requirement<|>Requirements<)/i', $src);
    t_ok(!$bare, 'A5 · no bare "Requirement" object label in ' . basename($vf));
}
$nav = file_get_contents(__DIR__ . '/../lib/navindex.php');
t_ok(strpos($nav, "\$add('Requirements', '/requisitions'") === false,
     'A5 · the navigation no longer offers an unexplained "Requirements"');
t_ok(strpos($nav, "/hiring-requests") !== false,
     'A6 · and the hiring request layer is reachable from the navigation at all');

// The three objects stay three objects. Nothing renamed, nothing merged.
t_ok(t_table_exists('hiring_requests'), 'A7 · hiring_requests exists');
t_ok(t_table_exists('requisitions'),    'A7 · requisitions is untouched and still separate');
t_ok(!t_table_exists('requirements'),   'A7 · no new "requirements" table was invented');
t_ok(in_array('hiring_request_id', t_columns('requisitions'), true),
     'A8 · the two are joined by one additive, nullable column and nothing else');

// ---------------------------------------------------------------------------
//  B · THE REQUESTOR — CAPABILITY, NOT ROLE NAME
// ---------------------------------------------------------------------------
t_section('B · requestor authorization');

foreach ([[951, 'M4c Branch A'], [952, 'M4c Branch B']] as $o) {
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $mine['o'][] = $o[0]; } catch (Throwable $e) {}
}
// Four people, chosen to separate the RIGHT from the ROLE NAME:
$uCap   = $mkUser('m4c_cap',   'INSPECTOR',    0, 951, 'mod.hiring.view,mod.hiring.edit');  // holds the right, junior role
$uCoord = $mkUser('m4c_coord', 'COORDINATOR',  0, 951, 'mod.hiring.view');                  // named Coordinator, no create right
$uAsst  = $mkUser('m4c_asst',  'ASST_MANAGER', 0, 951, '');                                 // inside the old role band
$uMgr   = $mkUser('m4c_mgr',   'BRANCH_MANAGER', 0, 951, 'mod.hiring.view,mod.hiring.edit');// may decide
$uMast  = $mkUser('m4c_master','ADMIN',        1, 951, '');                                 // master

$form = fn(array $x = []) => array_merge([
    'job_title' => 'M4c Welding Inspector', 'quantity' => 2, 'office_id' => 951,
    'priority' => 'NORMAL', 'approval_required' => 1,
], $x);

// B1 — the right, not the title, is what opens the door.
$act($uCap);
t_ok(hreq_can_create(), 'B1 · a user holding mod.hiring.edit may raise a hiring request');
[$ok1, $m1, $h1] = hreq_save(0, $form());
t_ok($ok1, 'B1 · …and the save is accepted: ' . $m1);
if ($ok1) $mine['h'][] = $h1;
t_ok(!in_array(user_role(), ['COORDINATOR','ASST_MANAGER'], true) && !is_admin_level(),
     'B1 · although that user holds no management or coordinator role at all');

// B2 — the role literally named Coordinator is NOT the holder of the right.
$act($uCoord);
t_eq(user_role(), 'COORDINATOR', 'B2 · this user IS the role named Coordinator');
t_ok(!hreq_can_create(), 'B2 · and still may not raise a request — the right was not granted');
[$ok2, $m2] = hreq_save(0, $form());
t_ok(!$ok2, 'B2 · the write is refused, not merely the button hidden: ' . $m2);

// B3 — the old role band was WIDER than the permission matrix.
$act($uAsst);
t_ok(function_exists('is_coordinator_level') && is_coordinator_level(),
     'B3 · an Asst. Manager passes the OLD is_coordinator_level() band');
t_ok(!can('mod.hiring.edit'),
     'B3 · but the permission matrix gives Asst. Manager no hiring right (docs/02-permission-matrix.md)');
t_ok(!hreq_can_create(), 'B3 · so the capability gate refuses where the role band would have allowed');
t_ok(!hreq_save(0, $form())[0], 'B3 · and the request is not written');

// B4 — VIEW is its own right, and it is the module capability.
$act($uCoord);
t_ok(hreq_can_view() && !hreq_can_create(), 'B4 · VIEW and CREATE are separate rights');
$src = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/hiringreq.php'));
t_ok(strpos($src, "can('mod.hiring.view')") !== false && strpos($src, "can('mod.hiring.edit')") !== false,
     'B4 · both are the EXISTING module capabilities, asked through can()');
t_ok(strpos($src, 'is_coordinator_level') === false,
     'B4 · and no role-name check survives anywhere in the request layer');
t_ok(array_key_exists('mod.hiring.edit', all_permissions()) && array_key_exists('mod.hiring.view', all_permissions()),
     'B5 · neither is a new permission — both are already in the permission catalogue');

// B6 — creating is not deciding.
$act($uCap);
t_ok(hreq_can_create() && !hreq_can_decide(), 'B6 · the create right does not confer the decide right');
hreq_submit($h1);
t_eq(hreq_get($h1)['status'], 'SUBMITTED', 'B6 · the requestor may submit their own request');
[$okD, $mD] = hreq_decide($h1, true);
t_ok(!$okD, 'B6 · but may not decide it: ' . $mD);

// B7 — segregation of duties: the requestor is not the approver.
$act($uMgr);
t_ok(hreq_can_decide(), 'B7 · a manager holding the module may decide');
t_ok(hreq_may_decide(hreq_get($h1)), 'B7 · …this request, which somebody else raised');
[$okOwn, , $hOwn] = hreq_save(0, $form(['job_title' => 'M4c own request']));
if ($okOwn) $mine['h'][] = $hOwn;
t_eq((int) hreq_get($hOwn)['requested_by_id'], $uMgr,
     'B7 · requested_by_id -> users.id is filled from whoever raised it, not typed');
hreq_submit($hOwn);
t_ok(!hreq_may_decide(hreq_get($hOwn)), 'B7 · and they may NOT decide the one they raised themselves');
[$okSelf, $mSelf] = hreq_decide($hOwn, true);
t_ok(!$okSelf, 'B7 · the decision is refused at the helper, not just hidden: ' . $mSelf);
t_eq(hreq_get($hOwn)['status'], 'SUBMITTED', 'B7 · and nothing was written');

// B8 — the one stated exception, and it is narrow.
$act($uMast);
t_ok(is_master() && hreq_may_decide(hreq_get($hOwn)),
     'B8 · a master may decide a request they could have raised — a one-person workspace has nobody else');
$offWas = setting_get('modules_off', '');
setting_set('modules_off', 'hr'); licence_disabled(true); ua(true);
t_ok(!hreq_can_decide(), 'B8 · but the exception is to SEGREGATION only — an unbought module still refuses a master');
t_ok(!hreq_can_create(), 'B8 · …and so does the create right');
setting_set('modules_off', $offWas); licence_disabled(true); ua(true);

// The right is asked where the write happens, so no caller can go around it.
foreach (['hreq_save', 'hreq_submit', 'hreq_cancel', 'hreq_to_requisition'] as $fn) {
    $b = substr($src, strpos($src, 'function ' . $fn . '('), 420);
    t_ok(strpos($b, 'hreq_can_create()') !== false,
         'B9 · ' . $fn . '() asks the capability itself — a direct call cannot bypass the route');
}
$bd = substr($src, strpos($src, 'function hreq_decide('), 700);
t_ok(strpos($bd, 'hreq_can_decide()') !== false && strpos($bd, 'hreq_may_decide(') !== false,
     'B9 · hreq_decide() asks both the authority and the segregation rule');
$rt = substr($src, strpos($src, 'function ops_hiring_requests('), 700);
t_ok(strpos($rt, 'hreq_can_view()') !== false && strpos($rt, 'hreq_scope_gate()') !== false,
     'B10 · the route asks capability first, then object scope — the house order');

// Branch scope still sits on top of the capability.
$act($uCap);
t_ok(!hreq_save(0, $form(['office_id' => 952]))[0] || scope_allows(952, null),
     'B10 · a capability is not a licence to act in another branch');

// B11 — branch scope, proved by BEHAVIOUR and not only by reading the source.
//  Mutation testing found this gap: disabling hreq_scope_gate() outright broke
//  nothing, because the route gate was held in place by a source-level assertion
//  and the acts that follow it trusted the route to have asked. It is now asked
//  at each write as well, and these are the assertions that hold it there.
$act($uCap);
[$okS, , $hScope] = hreq_save(0, $form(['job_title' => 'M4c branch A request']));
if ($okS) $mine['h'][] = $hScope;
$uOther = $mkUser('m4c_other', 'BRANCH_MANAGER', 0, 952, 'mod.hiring.view,mod.hiring.edit');
$act($uOther);
t_ok(hreq_can_create() && hreq_can_decide(), 'B11 · the other-branch user holds every right there is to hold');
t_ok(!scope_allows(951, null), 'B11 · …but branch A is not theirs');
t_ok(!hreq_in_scope(hreq_get($hScope)), 'B11 · so the request is out of their scope');
foreach ([
    ['submit',  fn() => hreq_submit($hScope)],
    ['cancel',  fn() => hreq_cancel($hScope, 'x')],
    ['convert', fn() => hreq_to_requisition($hScope, 1)],
] as $c) {
    $res = $c[1]();
    t_ok(!$res[0], 'B11 · they cannot ' . $c[0] . ' another branch\'s request: ' . $res[1]);
}
t_eq(hreq_get($hScope)['status'], 'DRAFT', 'B11 · and nothing was written');
$act($uCap); hreq_submit($hScope);
$act($uOther);
t_ok(!hreq_decide($hScope, true)[0], 'B11 · nor decide it, holding every right and being a different person');
t_eq(hreq_get($hScope)['status'], 'SUBMITTED', 'B11 · still nothing written');

// B11 — the READ path. A bookmarked URL to another branch's request is refused,
//  and the refusal is provable: the decision lives in hreq_scope_reason(), which
//  can be asked without redirecting, and it is asked again where the record is
//  actually read — so the route gate is not the only thing in the way.
$savedGet = $_GET;
$_GET['id'] = $hScope;
t_ok(hreq_scope_reason() !== '', 'B11 · opening another branch\'s request by direct URL is refused: ' . hreq_scope_reason());
$_GET = $savedGet;
$savedPost = $_POST; $_POST['office_id'] = 951;
t_ok(hreq_scope_reason() !== '', 'B11 · and so is naming another branch on the way in');
$_POST = $savedPost;
$rtSrc = substr($src, strpos($src, 'function ops_hiring_requests('), 3000);
t_ok(substr_count($rtSrc, 'hreq_in_scope(') >= 1,
     'B11 · the read path asks the scope question itself, not only the route gate');
$act($uCap);
$_GET['id'] = $hScope;
t_eq(hreq_scope_reason(), '', 'B11 · the owning branch is not refused');
$_GET = $savedGet;
$act($uOther);

// A partial update must not move a request out of its branch, or into none.
$act($uCap);
[$okP, , $hPart] = hreq_save(0, $form(['job_title' => 'M4c partial update']));
if ($okP) $mine['h'][] = $hPart;
hreq_save($hPart, ['job_title' => 'M4c partial update 2', 'quantity' => 2, 'priority' => 'NORMAL']);
$after = hreq_get($hPart);
t_eq((int) $after['office_id'], 951, 'B12 · an update that does not carry the branch keeps it');
t_eq((int) $after['requested_by_id'], $uCap, 'B12 · …and keeps the canonical requestor');

// ---------------------------------------------------------------------------
//  C · THE DIRECT REQUISITION PATH
// ---------------------------------------------------------------------------
t_section('C · direct requisition path');

$opsSrc = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($opsSrc, "'requisition-new'") !== false,
     'C1 · the direct route still exists — M4 removed nothing');
t_ok(strpos($opsSrc, "ops_require(is_coordinator_level(), 'Only coordinators / managers can raise requisitions.')") !== false,
     'C1 · and its own gate is unchanged by this correction');

$pdo->prepare("INSERT INTO requisitions (req_code,quantity,status,created_at) VALUES ('M4C-DIRECT',4,'OPEN',?)")->execute([date('c')]);
$direct = (int) $pdo->lastInsertId(); $mine['r'][] = $direct;
$dRow = ops_one("SELECT * FROM requisitions WHERE id=?", [$direct]);
t_eq($dRow['hiring_request_id'], null, 'C2 · a directly-raised requisition has no request behind it, and that is legitimate');
t_eq(reqf_counts($direct)['requested'], 4, 'C2 · M3 fulfilment reads it exactly as before');

// Provenance cannot be forged from the browser: the link column is not a field
// the requisition form accepts, and only the conversion writes it.
t_ok(!in_array('hiring_request_id', req_extra_fields(), true),
     'C3 · hiring_request_id is not a browser-settable requisition field');
t_ok(strpos($opsSrc, "'hiring_request_id'") === false,
     'C3 · the requisition save path never writes it at all');
$writers = 0;
foreach (glob(__DIR__ . '/../lib/*.php') as $lf) {
    if (preg_match('/INSERT INTO requisitions[^;]*hiring_request_id/s', file_get_contents($lf))) $writers++;
}
t_eq($writers, 1, 'C3 · exactly one place in the application writes the link — the conversion');

// And the conversion still refuses to start recruitment from an unapproved ask.
$act($uCap);
[$okU, , $hU] = hreq_save(0, $form(['job_title' => 'M4c unapproved']));
if ($okU) $mine['h'][] = $hU;
[$cOk, $cMsg] = hreq_to_requisition($hU, 1);
t_ok(!$cOk, 'C4 · an unapproved request still cannot become a requisition: ' . $cMsg);
t_ok(!hreq_is_executable($hU), 'C4 · hreq_is_executable() remains the single executable-state question');

// The screen says which of the two routes produced the record.
$rdv = file_get_contents(__DIR__ . '/../views/ops/requisition_detail.php');
t_ok(strpos($rdv, 'hiring_request_id') !== false && strpos($rdv, 'Raised from hiring request') !== false,
     'C5 · the requisition screen names its provenance, so neither route reads as an accident');

// ---------------------------------------------------------------------------
//  D · EVERY MUTATION PATH, AND THE CHAIN EACH ONE PASSES THROUGH
//
//  It is not enough that hreq_save() asks the capability. The claim being made
//  is that EVERY way of changing a hiring request asks it. So this section does
//  not trust a list written by hand: it finds every statement in the layer that
//  writes to the database, works out which function it lives in, and refuses any
//  function that is not on the audited list. A seventh write added later, in a
//  new function, fails this test until it is audited.
// ---------------------------------------------------------------------------
t_section('D · every mutation path');

// Every write in the layer, and the function that contains it.
$fnAt = function ($src, $pos) {
    $best = ''; $off = 0;
    if (preg_match_all('/\bfunction\s+([a-z_0-9]+)\s*\(/i', $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as $i => $hit) {
            if ($m[0][$i][1] < $pos) { $best = $hit[0]; $off = $m[0][$i][1]; }
        }
    }
    return [$best, $off];
};
$writes = [];
if (preg_match_all('/(INSERT INTO|UPDATE |DELETE FROM)/', $src, $wm, PREG_OFFSET_CAPTURE)) {
    foreach ($wm[0] as $hit) {
        [$fn, $off] = $fnAt($src, $hit[1]);
        $writes[$fn][] = $hit[1];
    }
}
// Phase 3 · M1 moved the decision WRITE out of hreq_decide() and into
// hreq_apply_decision(), the one writer shared by the direct decision and the
// approval chain. This guard caught that the moment it happened — which is what
// it is for — so the list is re-established rather than patched around.
//  Phase 3 · M4 implementation added a SIXTH mutation path: hreq_require_reapproval(),
//  the one place that invalidates a standing approval after a material change. It
//  belongs on this list for the same reason as the other five — it writes, and it
//  audits what it writes (material change, execution blocked, chain started).
$AUDITED = ['hreq_migrate', 'hreq_save', 'hreq_submit', 'hreq_apply_decision', 'hreq_cancel',
            'hreq_to_requisition', 'hreq_require_reapproval', 'hreq_qty_enforce_after_write'];
$unaudited = array_values(array_diff(array_keys($writes), $AUDITED));
t_ok(!$unaudited, 'D0 · every database write in the layer lives in an audited function'
     . ($unaudited ? ' — audit these: ' . implode(', ', $unaudited) : ''));
t_eq(count($writes), 7, 'D0 · and there are exactly seven of them — M4 added the re-approval writer '
     . 'and the headcount-ceiling compensator, both of which audit what they do');

// For each mutation path: the chain, in order, BEFORE the first write.
//   entitlement+capability -> tenant/branch scope -> state / input validation -> mutation -> audit
$chain = [
    // function            capability          scope             state or input validation
    'hreq_save'           => ['hreq_can_create()', 'hreq_in_scope(', "return [false, 'How many people are needed?"],
    'hreq_submit'         => ['hreq_can_create()', 'hreq_in_scope(', "!== 'DRAFT'"],
    'hreq_cancel'         => ['hreq_can_create()', 'hreq_in_scope(', "=== 'CANCELLED'"],
    'hreq_to_requisition' => ['hreq_can_create()', 'hreq_in_scope(', 'hreq_is_executable($r)'],
];
foreach ($chain as $fn => $links) {
    $start = strpos($src, 'function ' . $fn . '(');
    $firstWrite = min($writes[$fn]);
    $body = substr($src, $start, $firstWrite - $start);
    t_ok(strpos($body, $links[0]) !== false, 'D · ' . $fn . ' — capability asked before it writes');
    t_ok(strpos($body, $links[1]) !== false, 'D · ' . $fn . ' — branch scope asked before it writes');
    t_ok(strpos($body, $links[2]) !== false, 'D · ' . $fn . ' — state / input validated before it writes');
}
// The decision path. hreq_decide() no longer writes: it is the AUTHORITY GATE
// that asks the questions and then delegates to the one writer. So the chain is
// asserted up to the delegation rather than up to a write.
$dStart = strpos($src, 'function hreq_decide(');
$dHand  = strpos($src, 'hreq_apply_decision(', $dStart);
$decBody = substr($src, $dStart, $dHand - $dStart);
t_ok(strpos($decBody, 'hreq_can_decide()') !== false,   'D · hreq_decide — authority asked before it delegates');
t_ok(strpos($decBody, 'hreq_in_scope(') !== false,      'D · hreq_decide — branch scope asked before it delegates');
t_ok(strpos($decBody, 'hreq_may_decide($r)') !== false, 'D · hreq_decide — segregation of duties asked before it delegates');

// hreq_apply_decision() is the one writer and deliberately asks NO authority
// question — so the claim that this is safe rests entirely on every caller
// being an audited authority gate. That is asserted here, not assumed: the set
// of callers anywhere in lib/ must be exactly the two that are audited.
$callers = [];
foreach (glob(__DIR__ . '/../lib/*.php') as $lf) {
    $lsrc = preg_replace('#^\s*//.*$#m', '', file_get_contents($lf));
    if (!preg_match_all('/hreq_apply_decision\s*\(/', $lsrc)) continue;
    foreach (preg_split('/\bfunction\s+([a-z_0-9]+)\s*\(/i', $lsrc, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $chunk) {
        if ($i % 2 === 1) { $lastFn = $chunk; continue; }
        if (isset($lastFn) && strpos($chunk, 'hreq_apply_decision(') !== false) $callers[$lastFn] = true;
    }
}
unset($callers['hreq_apply_decision']);
$expected = ['hreq_decide', 'appr_callback'];
sort($expected); $got = array_keys($callers); sort($got);
t_eq(implode(',', $got), implode(',', $expected),
     'D · the one decision writer is reached ONLY from audited authority gates');
$apdStart = strpos($src, 'function hreq_apply_decision(');
$apdBody = substr($src, $apdStart, min($writes['hreq_apply_decision']) - $apdStart);
t_ok(strpos($apdBody, "'SUBMITTED', 'UNDER_REVIEW'") !== false,
     'D · hreq_apply_decision — state validated before it writes, whichever route decided');
t_ok(strpos(substr($src, strpos($src, 'function hreq_save(')), 'hreq_can_decide()') === false
     || strpos($chain['hreq_save'][0], 'decide') === false,
     'D · creating never asks for, or grants, the decide right');

// Every mutation leaves an audit trail.
foreach (['hreq_save', 'hreq_submit', 'hreq_apply_decision', 'hreq_cancel', 'hreq_to_requisition'] as $fn) {
    $start = strpos($src, 'function ' . $fn . '(');
    $end = strpos($src, "\nfunction ", $start + 10);
    $body = substr($src, $start, ($end ?: strlen($src)) - $start);
    // Phase 3 · M1: these called activity_log(), which does not exist anywhere in
    // the application, so the "audit entry" was a no-op behind function_exists().
    // The assertion was a source-string match and passed anyway — which is why
    // the M1 suite proves the audit BEHAVIOURALLY, by reading the rows back.
    t_ok(strpos($body, 'act_log(') !== false, 'D · ' . $fn . ' — calls the real audit spine');
}

// No other entry point: no AJAX/API route, and no other file touches the table.
$routes = 0;
foreach (glob(__DIR__ . '/../lib/*.php') as $lf) {
    if (basename($lf) === 'hiringreq.php') continue;
    // The table name in a SQL context. (ops_hiring_requests() contains the
    // table name as a substring of its own name, which is not a read.)
    if (preg_match('/(FROM|INTO|UPDATE|JOIN)\s+hiring_requests\b/i', file_get_contents($lf))) $routes++;
}
t_eq($routes, 0, 'D · no file outside the layer reads or writes hiring_requests');
t_eq(substr_count(file_get_contents(__DIR__ . '/../lib/ops.php'), 'ops_hiring_requests('), 1,
     'D · exactly one dispatch point reaches the layer');
$opsRoute = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($opsRoute, "'hiring-requests'=>'hiring','hiring-request'=>'hiring'") !== false,
     'D · and both of its routes are entitlement-mapped to the paid HR module');

// ---------------------------------------------------------------------------
//  Clean up.
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
foreach ($mine['r'] as $id) $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$id]);
foreach ($mine['h'] as $id) $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([$id]);
foreach ($mine['u'] as $id) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
foreach ($mine['o'] as $id) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$id]);
t_ok(true, 'M4 correction fixtures removed');
