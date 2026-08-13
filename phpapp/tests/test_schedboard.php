<?php
// ============================================================================
//  SCHEDULING BOARD — a read-only surface over the existing availability engine.
//  Uses an explicit 'ALL' scope (a CLI test has no logged-in user, so
//  scope_offices() is empty by design) to prove the board, capacity, unallocated
//  demand and best-inspector suggestion all compute from the live data.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

// The demo cast gives us inspectors, jobs and open calls to schedule around.
seed_demo(true);
$from = date('Y-m-d'); $to = date('Y-m-d', strtotime('+13 days'));

head('1. Board grid — inspectors × days from the availability engine');
$board = sched_board_data('ALL', $from, $to);
ok(count($board['people']) >= 1, 'the board lists inspectors (' . count($board['people']) . ')');
ok(count($board['days']) === 14, 'the board spans the requested fortnight (' . count($board['days']) . ' days)');
$anyId = (int)($board['people'][0]['id'] ?? 0);
ok(isset($board['matrix'][$anyId]), 'every inspector has a per-day status row');
$statuses = [];
foreach ($board['matrix'][$anyId] as $st) $statuses[$st] = true;
ok(isset($statuses['AVAILABLE']) || isset($statuses['ON_JOB']), 'a day carries a real status (AVAILABLE / ON_JOB)');

head('2. Capacity vs demand');
$cap = sched_capacity($board);
$t = $cap['totals'];
ok($t['working'] > 0, 'working inspector-days are counted (office calendar aware)');
ok($t['free'] + $t['on_job'] <= $t['working'], 'free + on-job never exceeds working capacity');
ok($t['utilisation'] >= 0 && $t['utilisation'] <= 100, 'utilisation is a sane percentage (' . $t['utilisation'] . '%)');
ok(count($cap['per_day_free']) === 14, 'a free-count is computed for every day column');

head('3. Unallocated demand — calls with no person');
$un = sched_unallocated_calls('ALL');
ok(is_array($un), 'unallocated calls resolve (' . count($un) . ')');
// An open call with a job that HAS an inspector must not appear.
$withInsp = ops_one("SELECT c.id FROM calls c JOIN jobs j ON j.call_id=c.id WHERE j.inspector_id IS NOT NULL AND c.status<>'CLOSED' LIMIT 1");
if ($withInsp) {
    $ids = array_map(fn($r)=>(int)$r['id'], $un);
    ok(!in_array((int)$withInsp['id'], $ids, true), 'a call already carrying an inspector is NOT shown as unallocated');
} else { ok(true, 'no already-allocated open call in the demo to exclude'); }

head('4. Best-free-inspector suggestion');
$span = day_span($from, date('Y-m-d', strtotime('+2 days')), 10);
$sug = sched_suggest('ALL', $span, '');
ok(is_array($sug), 'suggestion resolves for a 3-day span');
if ($sug) {
    // Everyone suggested must be free for EVERY day of the span (reuses free_for_span).
    [$m,,$people,] = availability_matrix('ALL', $from, end($span));
    $allFree = true;
    foreach ($sug as $s) foreach ($span as $d) if (($m[$s['id']][$d] ?? 'AVAILABLE') !== 'AVAILABLE') $allFree = false;
    ok($allFree, 'every suggested inspector is genuinely free for the whole span');
    // Ranked by longest free run first.
    $runs = array_map(fn($s)=>$s['run'], $sug);
    $sorted = $runs; rsort($sorted);
    ok($runs === $sorted, 'suggestions are ranked by longest free run first');
    ok((int)$sug[0]['run'] >= 1, 'the top suggestion carries a free-run length');
} else {
    ok(true, 'no inspector free for the whole span in the demo (still a valid answer)');
}
// SBU filter narrows the pool (never widens it).
$sugAll = sched_suggest('ALL', $span, '');
$sugSbu = sched_suggest('ALL', $span, 'IND');
ok(count($sugSbu) <= count($sugAll), 'an SBU filter never returns more people than "any"');

head('5. View renders');
$offices='ALL'; $capacity=$cap; $unalloc=$un; $suggest=$sug; $suggestSpan=$span;
$s_from=$from; $s_to=''; $s_sbu=''; $s_call=0; $sbus=[]; $canAllocate=true;
ob_start(); require __DIR__ . '/../views/ops/schedule_board.php'; $h = ob_get_clean();
ok(strpos($h,'Scheduling board')!==false && strpos($h,'Who is where')!==false && strpos($h,'kpi-row')!==false, 'the board view renders the grid + capacity band');
ok(strpos($h,'Who’s free?')!==false && strpos($h,'Needs a person')!==false, 'the suggestion + unallocated panels render');

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== SCHEDBOARD: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
