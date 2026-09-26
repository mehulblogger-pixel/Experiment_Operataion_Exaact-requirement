<?php
// ============================================================================
//  THE CONFIGURED PROCESS DECIDES THE SCREEN.
//
//  The candidate screen carried TWO navigations for one journey: the workflow
//  strip across the top ("Stage 4 of 8 · Management Interview") and, directly
//  beneath it, a fixed row of eight tabs. Both described the same hiring and
//  neither knew about the other. They did not even agree on words — the stage
//  said "Management Interview", the tab said "Interviews", and the
//  "Compensation" stage had no tab at all.
//
//  Underneath was the real fault: the WORKFLOW is configurable and the SCREEN
//  was not. A company running a six-stage process still saw all eight tabs, in
//  an order its process does not follow, including ones it never uses.
//
//  These tests protect the fix in BOTH directions. Taking tabs away is the
//  dangerous half — a screen that hides something a person needs is worse than
//  one that shows too much — so most of the effort below is on what must still
//  be there.
// ============================================================================

t_section('Candidate screen — the configured stages decide what opens');

t_ok(function_exists('recruitpipe_screen_plan'), 'ARMING · the plan exists');
t_ok(function_exists('recruitpipe_wants_tab'), 'ARMING · the tab filter exists');
t_ok(function_exists('recruitpipe_tab_order'), 'ARMING · the ordering exists');

$ALL = ['Overview', 'Pipeline', 'Interviews', 'Documents', 'Offer', 'Recruitment', 'CV', 'Timeline'];

// ---------------------------------------------------------------------------
//  1 · NO CONFIGURATION, NO CHANGE. The safety net.
// ---------------------------------------------------------------------------
t_nothrow('a workspace with no workflow configured keeps every tab, in the original order', function () use ($ALL) {
    //  Honouring a configuration cannot mean taking things away from somebody
    //  who has not made one. If this ever fails, the change has become a
    //  regression for every customer who never configured a pipeline.
    $plan = ['has_pipeline' => false, 'needs' => [], 'focus' => ''];
    foreach ($ALL as $t)
        t_ok(recruitpipe_wants_tab($plan, $t), "'$t' is kept when nothing is configured");
    t_eq(recruitpipe_tab_order($plan, $ALL), $ALL, 'and the order is untouched');
});

t_nothrow('a candidate the engine knows nothing about produces an empty, harmless plan', function () use ($ALL) {
    foreach ([[], ['id' => 0], ['id' => -1], 'nonsense', null] as $bad) {
        $plan = recruitpipe_screen_plan($bad);
        t_ok(empty($plan['has_pipeline']), 'no pipeline claimed for a bad candidate');
        t_ok(recruitpipe_wants_tab($plan, 'Interviews'), 'and nothing is hidden');
        t_eq(recruitpipe_tab_order($plan, $ALL), $ALL, 'and nothing is reordered');
    }
});

t_nothrow('a legacy candidate never put on a process keeps every tab', function () use ($ALL) {
    //  THE ONE THAT NEARLY BIT. recruitpipe_cand_state() resolves a DEFAULT
    //  pipeline for a candidate that was never put on one, so an earlier draft
    //  of this would have stripped tabs from every legacy record on the strength
    //  of a fallback nobody chose. Hiding requires the process to have been
    //  locked in for THIS hire; ordering, which takes nothing away, does not.
    $cand = ['id' => 424242, 'stage' => 'RECEIVED'];          // no pipeline_id
    $plan = recruitpipe_screen_plan($cand);
    t_ok(empty($plan['has_pipeline']), 'an unlocked candidate grants no hiding right');
    foreach ($ALL as $t)
        t_ok(recruitpipe_wants_tab($plan, $t), "'$t' is kept for a legacy candidate");
});

// ---------------------------------------------------------------------------
//  2 · ONLY THE STAGE-SHAPED TABS CAN EVER BE HIDDEN
// ---------------------------------------------------------------------------
t_nothrow('the record\'s own sections are never hidden by a process', function () use ($ALL) {
    //  Overview, CV, Timeline, Documents and the rest are the record itself —
    //  its history and its files — not steps. A process that does not mention
    //  them is not a process that forbids them.
    $plan = ['has_pipeline' => true, 'needs' => ['Interviews' => false, 'Offer' => false], 'focus' => ''];
    foreach (['Overview', 'Pipeline', 'Documents', 'Recruitment', 'CV', 'Timeline'] as $t)
        t_ok(recruitpipe_wants_tab($plan, $t), "'$t' survives even the narrowest process");
    //  …and an unknown tab added later is kept by default, never silently dropped.
    t_ok(recruitpipe_wants_tab($plan, 'SomeFutureTab'), 'a tab the plan has never heard of is kept');
});

t_nothrow('a process with no interview stage does not offer an Interviews tab', function () {
    $plan = ['has_pipeline' => true, 'needs' => ['Interviews' => false, 'Offer' => true], 'focus' => ''];
    t_ok(!recruitpipe_wants_tab($plan, 'Interviews'), 'Interviews is dropped');
    t_ok(recruitpipe_wants_tab($plan, 'Offer'), 'but Offer stays, because the process has one');
});

t_nothrow('a process with no offer stage does not offer an Offer tab', function () {
    //  The manpower-supply case: people are deployed on a rate, not offered a
    //  salary, so an Offer tab is noise on every screen.
    $plan = ['has_pipeline' => true, 'needs' => ['Interviews' => true, 'Offer' => false], 'focus' => ''];
    t_ok(!recruitpipe_wants_tab($plan, 'Offer'), 'Offer is dropped');
    t_ok(recruitpipe_wants_tab($plan, 'Interviews'), 'Interviews stays');
});

// ---------------------------------------------------------------------------
//  3 · THE CURRENT STAGE DECIDES WHAT OPENS
// ---------------------------------------------------------------------------
t_nothrow('the focused panel is put first, which is what makes it open', function () use ($ALL) {
    $plan = ['has_pipeline' => true, 'needs' => ['Interviews' => true, 'Offer' => true], 'focus' => 'Interviews'];
    $order = recruitpipe_tab_order($plan, $ALL);
    t_eq($order[0], 'Interviews', 'the current stage\'s panel leads');
    //  Nothing may be lost or duplicated in the reshuffle.
    $a = $order; $b = $ALL; sort($a); sort($b);
    t_eq($a, $b, 'and every other tab is still there, exactly once');
});

t_nothrow('a focus on a tab this process does not have is ignored, not obeyed', function () use ($ALL) {
    //  Opening a tab that is not on the screen would leave the person on a blank
    //  page and teach them the screen is broken.
    $plan = ['has_pipeline' => true, 'needs' => ['Interviews' => false, 'Offer' => false], 'focus' => 'Interviews'];
    $present = array_values(array_filter($ALL, fn($t) => recruitpipe_wants_tab($plan, $t)));
    $order = recruitpipe_tab_order($plan, $present);
    t_ok(!in_array('Interviews', $order, true), 'ARMING · Interviews really is absent');
    t_eq($order[0], 'Overview', 'so the screen opens where it always did');
});

t_nothrow('a closed candidate is not forced anywhere', function () use ($ALL) {
    $plan = ['has_pipeline' => true, 'needs' => ['Interviews' => true, 'Offer' => true], 'focus' => ''];
    t_eq(recruitpipe_tab_order($plan, $ALL), $ALL, 'no focus, no reordering');
});

t_nothrow('INVARIANT: the focus is never a tab the screen will not draw', function () use ($ALL) {
    //  Asserted as a property over every shape a plan can take, rather than by
    //  poking one case. The guard inside recruitpipe_screen_plan() that upholds
    //  this is currently unreachable — a stage of kind 'interview' necessarily
    //  makes the Interviews tab wanted — so no mutation of it fails a test. The
    //  invariant is what actually matters, and it is what this pins.
    $checked = 0;
    foreach ([true, false] as $iv) foreach ([true, false] as $of) foreach (['Interviews', 'Offer', 'Pipeline', ''] as $focus) {
        $plan = ['has_pipeline' => true, 'needs' => ['Interviews' => $iv, 'Offer' => $of], 'focus' => $focus];
        $shown = array_values(array_filter($ALL, fn($t) => recruitpipe_wants_tab($plan, $t)));
        $order = recruitpipe_tab_order($plan, $shown);
        //  Whatever the plan says, the tab that ends up FIRST must be on screen.
        t_ok(in_array($order[0], $shown, true),
            "the opening tab is on screen (needs iv=" . (int) $iv . " of=" . (int) $of . " focus='$focus')");
        //  …and the reshuffle never invents or loses one.
        $a = $order; $b = $shown; sort($a); sort($b);
        t_eq($a, $b, "no tab gained or lost (focus='$focus')");
        $checked++;
    }
    t_ok($checked === 16, "ARMING · all $checked plan shapes were exercised");
});

// ---------------------------------------------------------------------------
//  4 · AGAINST A REAL CONFIGURED PIPELINE
// ---------------------------------------------------------------------------
t_nothrow('end to end: a real pipeline drives a real candidate\'s screen', function () {
    if (!function_exists('recruitpipe_create') || !function_exists('recruitpipe_cand_state')) {
        t_ok(true, 'the pipeline engine is absent on this build'); return;
    }
    $pdo = db();
    //  A deliberately SHORT process with no interview and no offer — the
    //  manpower-supply shape, and the one the fixed eight tabs served worst.
    $pid = recruitpipe_create('ZZSHORT', 'ZZ Short deploy', 'Rate agreed, person deployed.', false, [
        ['SCREEN',  'Screening',  'gate',     'RECRUITER'],
        ['DEPLOY',  'Deployment', 'terminal', 'HR_MANAGER'],
    ]);
    try {
        t_ok((int) $pid > 0, 'ARMING · a short pipeline was created');
        $pdo->prepare("INSERT INTO candidates (cand_code, first_name, last_name, stage, pipeline_id, created_at)
                       VALUES ('ZZSP1','ZZ','Short','RECEIVED',?,?)")->execute([(int) $pid, date('c')]);
        $cid = (int) $pdo->lastInsertId();
        $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
        t_ok(!empty($cand), 'ARMING · the candidate was created on that pipeline');

        $plan = recruitpipe_screen_plan($cand);
        t_ok(!empty($plan['has_pipeline']), 'the plan sees the configured process');
        t_ok(!recruitpipe_wants_tab($plan, 'Interviews'),
            'a process with no interview stage gives this candidate no Interviews tab');
        t_ok(!recruitpipe_wants_tab($plan, 'Offer'),
            'and no Offer tab');
        foreach (['Overview', 'CV', 'Timeline', 'Documents', 'Pipeline'] as $t)
            t_ok(recruitpipe_wants_tab($plan, $t), "but '$t' is still there");
        //  The current stage is a gate, so the screen opens on the stage panel.
        t_ok(in_array((string) $plan['focus'], ['Pipeline', ''], true),
            'and a gate stage opens the stage panel (' . $plan['focus'] . ')');

        $pdo->exec("DELETE FROM candidates WHERE id=$cid");
    } finally {
        $pdo->prepare("DELETE FROM recruit_stages WHERE pipeline_id=?")->execute([(int) $pid]);
        $pdo->prepare("DELETE FROM recruit_pipelines WHERE id=?")->execute([(int) $pid]);
        db()->exec("DELETE FROM candidates WHERE cand_code='ZZSP1'");
    }
    t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE cand_code='ZZSP1'"), 0, 'fixtures removed');
});

// ---------------------------------------------------------------------------
//  5 · THE SCREEN REALLY ASKS
// ---------------------------------------------------------------------------
t_nothrow('the candidate screen is wired to the plan, not to a fixed list', function () {
    $v = (string) @file_get_contents(dirname(__DIR__) . '/views/ops/candidate_detail.php');
    t_ok(strpos($v, 'recruitpipe_screen_plan') !== false, 'it asks for the plan');
    t_ok(strpos($v, 'recruitpipe_tab_order') !== false, 'and orders its tabs from it');
    //  The old hard-coded order string must be gone, or the plan is decorative.
    t_ok(strpos($v, 'data-tabs-order="Overview,Pipeline,Interviews') === false,
        'and the hard-coded tab order is gone');
    t_ok(preg_match('~data-tabs-order="<\?=~', $v) === 1, 'the order is rendered from the plan');
    //  And the person is told WHY the screen opened where it did.
    t_ok(strpos($v, 'because this hire is at') !== false,
        'and the screen explains why it opened where it did, rather than just moving under them');
});
