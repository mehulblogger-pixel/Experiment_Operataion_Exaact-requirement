<?php
// ============================================================================
//  GATE 0 · F7 — THE PIPELINE'S PER-STAGE CAPTURE HAD NO REACHABLE ROUTE
//
//  The dispatcher carried TWO cases for one route name, 'candidate-stage':
//  the candidate family's case (-> ops_candidates(), the legacy stage-move
//  handler and the single execution choke point) and, twenty-six lines later,
//  a case for ops_recruit_candidate_stage() — the Pipeline tab's per-stage
//  notes and documents. PHP matches switch(true) in order, so the second was
//  unreachable, and the Pipeline tab's three forms posted into the stage-move
//  handler, which read no `to_stage` and answered "Unknown stage."
//
//  Stage notes could not be saved, stage documents could not be uploaded, and
//  stage documents could not be deleted. Three user-facing functions, behind a
//  message that described none of them.
//
//  The legacy name was deliberately NOT moved. It is the execution choke point
//  and candidate_detail.php, four test workers and the browser UAT depend on
//  it. Per-stage capture took a distinct name — 'candidate-pipestage', chosen
//  so it does not even share the '/candidate-stage' prefix that the UAT's
//  selector matches.
//
//  This test reads the SOURCE, not a rendered page: the defect was a dispatch
//  ordering fact, and ordering is what has to stay correct. Driving the route
//  would prove one call; asserting the ordering proves the class of bug cannot
//  come back under a different handler name.
// ============================================================================
t_section('gate 0 · F7 — candidate stage route reachability');

$g0src  = (string) @file_get_contents(__DIR__ . '/../lib/ops.php');
$g0pipe = (string) @file_get_contents(__DIR__ . '/../lib/recruitpipe.php');
t_ok($g0src !== '' && $g0pipe !== '', 'both source files are readable');

//  1 — ONE case per route name. This is the defect itself: a second case for a
//      name an earlier case already claims can never run.
$g0famCase = "case \$route === 'candidates' || \$route === 'candidate-new'";
$g0legacy  = substr_count($g0src, "case \$route === 'candidate-stage':");
t_eq($g0legacy, 0, 'no dispatcher case claims the bare route name a second time');
t_ok(strpos($g0src, $g0famCase) !== false,
    'the candidate family case still owns candidate-stage (the execution choke point is not moved)');

//  2 — the legacy handler still answers the legacy name. Moving it would have
//      silently disabled rexec_block_reason(), the joining transaction and the
//      drop reason capture.
t_ok(strpos($g0src, "if (\$route === 'candidate-stage') {") !== false,
    'ops_candidates() still handles candidate-stage');

//  3 — per-stage capture is now reachable under its own name, and the handler
//      it names exists.
t_ok(strpos($g0src, "case \$route === 'candidate-pipestage':") !== false,
    'a dispatcher case exists for candidate-pipestage');
t_ok(function_exists('ops_recruit_candidate_stage'),
    'ops_recruit_candidate_stage() is defined');
$g0pos = strpos($g0src, "case \$route === 'candidate-pipestage':");
$g0fam = strpos($g0src, $g0famCase);
t_ok($g0pos !== false && $g0fam !== false && $g0pos > $g0fam,
    'candidate-pipestage sits after the family case and is no longer shadowed by it');

//  4 — the three forms post to the reachable name. This is what a user touches.
t_eq(substr_count($g0pipe, 'action="/candidate-pipestage?id='), 3,
    'all three Pipeline-tab forms (notes, upload, delete) post to candidate-pipestage');
t_eq(substr_count($g0pipe, 'action="/candidate-stage?id='), 0,
    'no Pipeline-tab form still posts to the stage-move route');

//  5 — the new name must not collide with the browser UAT's prefix selector,
//      which matches form[action^="/candidate-stage"] and takes the FIRST hit.
t_ok(strncmp('candidate-pipestage', 'candidate-stage', 15) !== 0,
    'the new route does not share the /candidate-stage prefix the UAT selector matches');

//  6 — module gating still resolves. An unmapped route inside a paid module's
//      family is still that module's, which is why candidate-flow needs no map
//      entry either; asserted rather than assumed.
if (function_exists('ops_module_family'))
    t_eq(ops_module_family('candidate-pipestage'), 'hiring',
        'candidate-pipestage resolves to the hiring module through its family');

//  7 — nothing else in the dispatcher moved. The candidate family case must
//      still list every route it listed before.
foreach (['candidates','candidate-new','candidate-edit','candidate','candidate-stage',
          'candidate-joined','candidate-cv','candidate-client','candidate-credential',
          'candidate-commercial','candidate-link-person','candidate-link-pro','candidate-unlink-pro'] as $g0r)
    t_ok(strpos($g0src, "\$route === '$g0r'") !== false, "family case still lists $g0r");
