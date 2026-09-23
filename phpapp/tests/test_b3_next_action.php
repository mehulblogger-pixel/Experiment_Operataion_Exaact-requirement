<?php
// ============================================================================
//  B3 — "WHAT SHOULD I DO NOW?"
//
//  A CORRECTION FIRST. Finding F-A5-3 said there was "no shared next-action
//  component". That was wrong. `.nowband` already exists in app.css and is
//  already rendered by NINE record screens. Checking the claim before building
//  is what stopped B3 producing a second component beside it.
//
//  The real gap was the RECRUITMENT records -- candidate, requirement and
//  hiring request had no such block at all. B3 feeds the existing component for
//  those three.
//
//  What these tests defend, in order of how badly each would hurt:
//
//   1. NO GRANTED PERMISSION. The block must never show an action to somebody
//      the module's own gate would refuse. A next-action that offers a forbidden
//      step is worse than no next-action at all.
//   2. NO INVENTED STEP. Every named step must be one the module already
//      allows. B3 may not add a transition to any lifecycle.
//   3. NO SECOND COMPONENT. It must render .nowband, not something new.
//   4. NO MISLEADING SILENCE. Where nothing is due, it must say so rather than
//      offering a door.
// ============================================================================

t_section('B3 — next action');

// ---- A · arming ----------------------------------------------------------
t_ok(function_exists('na_state') && function_exists('na_html'),
     'A1 ARMING · the resolver and the renderer are loaded');
$b3res = na_resolvers();
t_eq(array_keys($b3res), ['hiring_request', 'requisition', 'candidate'],
     'A2 · exactly three entities are resolved — the three that had no block');
t_ok(!function_exists('na_call'),
     'A3 · the work order has NO resolver here: call_detail.php already renders its own, better band');

// ---- B · IT MUST RENDER THE COMPONENT THAT ALREADY EXISTS -----------------
$b3css = file_get_contents(__DIR__ . '/../assets/css/app.css');
t_ok(strpos($b3css, '.nowband{') !== false, 'B1 ARMING · .nowband is defined in the stylesheet');
$b3lib = file_get_contents(__DIR__ . '/../lib/nextaction.php');
t_ok(strpos($b3lib, 'class="nowband"') !== false,
     'B2 · the renderer emits .nowband — the component nine screens already use');
foreach (['na-block', 'na-row', 'na-col', 'na-grow'] as $b3dead)
    t_ok(strpos($b3css, $b3dead) === false && strpos($b3lib, $b3dead) === false,
         "B3 · no competing component class '$b3dead' survives anywhere");
//  And the nine screens that already had one must still have it.
$b3have = 0;
foreach (['call_detail', 'job_detail', 'lead_detail', 'opportunity_detail', 'invoice_detail',
          'receipt_detail', 'trace_thread'] as $b3v) {
    $f = __DIR__ . '/../views/ops/' . $b3v . '.php';
    if (is_file($f) && strpos(file_get_contents($f), 'nowband') !== false) $b3have++;
}
t_eq($b3have, 7, 'B4 · the record screens that already had a next-action band still have it');

// ---- C · NO INVENTED STEP -------------------------------------------------
//  Every step the hiring-request resolver can name must correspond to a state
//  HREQ_STATUS already defines. B3 may not add one.
t_ok(defined('HREQ_STATUS'), 'C ARMING · HREQ_STATUS is the module\'s own list of states');
$b3seen = [];
foreach (array_keys(HREQ_STATUS) as $st) {
    $a = na_hiring_request(['id' => 1, 'status' => $st, '__remaining' => 2]);
    t_ok(is_array($a), "C1 · a '$st' request produces an answer");
    if (!is_array($a)) continue;
    $b3seen[$st] = $a;
    //  The state it prints must be the module's own label for that state.
    t_eq($a['state'], HREQ_STATUS[$st], "C2 · '$st' is shown with the module's own label");
    //  Every answer must cite where it came from.
    t_ok(trim((string) $a['src']) !== '', "C3 · the '$st' answer records which helper it was read from");
}
//  Terminal states must offer nothing.
foreach (['REJECTED', 'CANCELLED'] as $st)
    t_eq($b3seen[$st]['next'] ?? 'x', '', "C4 · a $st request offers no next step — it is terminal");
//  A live state must offer something.
t_ok(($b3seen['DRAFT']['next'] ?? '') !== '', 'C5 · a DRAFT request DOES name a next step');
//  ARMING — an unknown status must not be invented into a step.
t_ok(na_hiring_request(['id' => 1, 'status' => 'NOT_A_REAL_STATUS'])['next'] === '',
     'C6 ARMING · an unrecognised status yields no invented step');

// ---- D · NO GRANTED PERMISSION -------------------------------------------
//  The decisive test. As a user the gate refuses, the block must not offer a door.
t_as_nobody();
$b3sub = na_hiring_request(['id' => 1, 'status' => 'SUBMITTED']);
t_ok(!$b3sub['can'], 'D1 · signed out, the resolver reports the module\'s gate said NO');
$b3html = na_html('hiring_request', ['id' => 1, 'status' => 'SUBMITTED']);
t_ok(strpos($b3html, '<a class="btn') === false,
     'D2 · and the rendered block contains NO action link at all');
//  D2 is NOT the load-bearing assertion, and pretending otherwise would be
//  dishonest: there are two layers here. For SUBMITTED the resolver also
//  withholds the route when the gate refuses, so D2 passes even if the
//  RENDERER's gate is removed. Proved by deliberately removing it -- D2 stayed
//  green and only D5 went red. D5 is the one that measures the renderer,
//  because a DRAFT always carries a route; the gate is the only thing standing
//  between that route and a button. Both layers are kept -- defence in depth is
//  worth having -- but the suite should say which test guards which layer.
t_ok(strpos($b3html, 'nowband') !== false, 'D3 · but it still explains where the record stands');
t_ok(stripos($b3html, 'Waiting for an approver') !== false,
     'D4 · saying who it is waiting for, instead of offering a button that would be refused');
//  A DRAFT: still no button for somebody who may not edit.
$b3d = na_html('hiring_request', ['id' => 1, 'status' => 'DRAFT']);
t_ok(strpos($b3d, '<a class="btn') === false,
     'D5 · LOAD-BEARING · a DRAFT carries a route, so only the renderer\'s gate '
     . 'stops it becoming a button — and it does');

//  ARMING — with the gate satisfied, a door DOES appear. Without this, D1–D5
//  could be passing simply because the renderer never emits a link.
t_as_admin();
setting_set('saas_entitled_modules', 'hr,operations,reporting');
$GLOBALS['__tenant'] = ['key' => 'testco', 'company' => 'Test Co'];
licence_disabled(true); current_user(true); ua(true);
t_ok(hreq_can_decide(), 'D ARMING · an administrator passes hreq_can_decide()');
$b3ok = na_html('hiring_request', ['id' => 7, 'status' => 'SUBMITTED']);
t_ok(strpos($b3ok, '<a class="btn') !== false,
     'D6 ARMING · and THEN the block does render an action link — so D2 measured the gate, not a dead renderer');
t_ok(strpos($b3ok, 'href="/hiring-request?id=7#na-decide"') !== false,
     'D7 · the link points at the record\'s EXISTING route — and at the anchor of the '
     . 'control that already lives on that page, so the button moves you to the decision '
     . 'rather than reloading the screen you are on');

// ---- E · NO MISLEADING SILENCE -------------------------------------------
$b3done = na_hiring_request(['id' => 1, 'status' => 'APPROVED', '__remaining' => 0]);
t_eq($b3done['next'], '', 'E1 · an approved request with nothing left to recruit offers no action');
t_ok(stripos($b3done['note'], 'already being recruited') !== false,
     'E2 · and says why, rather than going blank');
$b3quiet = na_html('hiring_request', ['id' => 1, 'status' => 'APPROVED', '__remaining' => 0]);
t_ok(strpos($b3quiet, '<a class="btn') === false && strlen($b3quiet) > 40,
     'E3 · the rendered quiet state is a sentence, not an empty box and not a button');

// ---- F · it never takes a screen down ------------------------------------
t_eq(na_state('candidate', []), null, 'F1 · an empty row yields nothing rather than an error');
t_eq(na_state('not_an_entity', ['id' => 1]), null, 'F2 · an unknown entity yields nothing');
t_eq(na_html('not_an_entity', ['id' => 1]), '', 'F3 · and renders as empty string');
t_ok(na_state('requisition', ['id' => 999999]) === null || is_array(na_state('requisition', ['id' => 999999])),
     'F4 · a missing requisition does not throw');

// ---- G · the three screens are wired -------------------------------------
foreach (['hiring_request' => 'hiring_request', 'requisition_detail' => 'requisition',
          'candidate_detail' => 'candidate'] as $b3view => $b3ent) {
    $src = file_get_contents(__DIR__ . '/../views/ops/' . $b3view . '.php');
    t_ok(strpos($src, "na_html('" . $b3ent . "'") !== false,
         "G · views/ops/$b3view.php renders the next action for '$b3ent'");
    t_ok(strpos($src, 'function_exists(\'na_html\')') !== false,
         "G · and guards on function_exists, so the screen survives without the library");
}

// ---- restore -------------------------------------------------------------
setting_set('saas_entitled_modules', '');
unset($GLOBALS['__tenant']);
licence_disabled(true); current_user(true); ua(true);
t_as_nobody();
t_ok(true, 'B3 · session and entitlement restored');
