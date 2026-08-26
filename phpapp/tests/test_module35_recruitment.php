<?php
// Module 35 — Recruitment. Every interview query in the module filters interview_date >= today
// (upcoming only), so an interview whose date has PASSED with no outcome recorded was surfaced
// NOWHERE — the outcome never got chased. recruit_overdue_interviews() is the read-only worklist of
// exactly those: past interview date, no done-date, no outcome, candidate still in play.
t_section('Module 35 — overdue interviews (past date, no outcome)');

t_ok(function_exists('recruit_overdue_interviews'),       'recruit_overdue_interviews() exists');
t_ok(function_exists('recruit_overdue_interviews_count'), 'recruit_overdue_interviews_count() exists');

// Run schema/migrations OUTSIDE the transaction: req_migrate() adds columns via
// ALTER TABLE and guards itself with a static flag — if it first ran inside a
// rolled-back transaction the columns would vanish while the guard stayed set,
// breaking later tests. So migrate first, then wrap only the INSERTs.
if (function_exists('ops_ensure_schema')) ops_ensure_schema();
if (function_exists('req_migrate')) req_migrate();
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    $pdo = db();

    $mk = function ($code, $date, $done, $outcome, $stage) use ($pdo) {
        $pdo->prepare("INSERT INTO candidates (cand_code, first_name, last_name, stage, interview_required, interview_date, interview_done_date, interview_outcome)
                       VALUES (?,?,?,?,1,?,?,?)")
            ->execute([$code, $code, 'T', $stage, $date, $done, $outcome]);
        return (int)$pdo->lastInsertId();
    };
    $yst = date('Y-m-d', strtotime('-3 days'));
    $tmr = date('Y-m-d', strtotime('+3 days'));

    $base = recruit_overdue_interviews_count();
    $overdue  = $mk('IVX-OD', $yst, '', '', 'INTERVIEW');    // past, no outcome, in play → chased
    $mk('IVX-UP', $tmr, '', '', 'INTERVIEW');                // upcoming → not overdue
    $mk('IVX-DONE', $yst, date('Y-m-d'), '', 'INTERVIEW');   // past but done-date set → resolved
    $mk('IVX-OUT', $yst, '', 'SELECTED', 'OFFERED');         // past but outcome recorded → resolved
    $mk('IVX-TERM', $yst, '', '', 'REJECTED');               // past, no outcome, but terminal → not chased

    t_eq(recruit_overdue_interviews_count(), $base + 1, 'exactly the one past-date, no-outcome, in-play interview is counted');

    $rows = recruit_overdue_interviews(50);
    $codes = array_column($rows, 'cand_code');
    t_ok(in_array('IVX-OD', $codes, true),    'the overdue interview is on the worklist');
    t_ok(!in_array('IVX-UP', $codes, true),   'an upcoming interview is not overdue');
    t_ok(!in_array('IVX-DONE', $codes, true), 'an interview with a done-date is resolved, not chased');
    t_ok(!in_array('IVX-OUT', $codes, true),  'an interview with an outcome is resolved, not chased');
    t_ok(!in_array('IVX-TERM', $codes, true), 'a terminal-stage candidate is not chased');

    // The worklist carries the fields the risk card renders.
    $one = null; foreach ($rows as $r) if ($r['cand_code'] === 'IVX-OD') $one = $r;
    t_ok($one !== null, 'the overdue row is returned');
    foreach (['id','cand_code','nm','stage','interview_date'] as $k) t_ok($one && array_key_exists($k, $one), "the row has '$k'");
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$recruit = file_get_contents(__DIR__ . '/../lib/recruit.php');
$ops     = file_get_contents(__DIR__ . '/../lib/ops.php');
$view    = file_get_contents(__DIR__ . '/../views/ops/recruitment_home.php');
t_ok(strpos($recruit, "\$d['r_interviews'] = ") !== false, 'the recruitment risks board is fed the overdue interviews');
t_ok(strpos($view, 'Interviews awaiting an outcome') !== false, 'the risks board renders the overdue-interview group');
t_ok(strpos($ops, "recruit_overdue_interviews_count()") !== false && strpos($ops, "'iv_overdue'") !== false,
    'the home attention band surfaces overdue interviews (hiring-gated)');
// The existing upcoming-interview query is untouched (additive, not a rewrite).
t_ok(strpos($recruit, "c.interview_date>=? AND \$cw ORDER BY c.interview_date LIMIT 6") !== false,
    'the existing upcoming-interview query is preserved');
