<?php
// ---------------------------------------------------------------------------
//  UAT A9 — THREE DEFECTS ON THE INTERVIEW PANEL.
//
//  (a) "No filter on the interviewer panel as all the people without
//       recruitment module also shows in the interview panel list"
//  (b) "once L1 interview if completed and candidate has been rated it allows
//       to schedule L1 interview second, third and as many times it requires.
//       Yes there must be option to retry or reassess ... but to have
//       scheduling multiple times same level interview is a bug."
//  (c) "Even the panel people are not reflected any details of the today
//       scheduled interviews on there dashboard or daily task."
// ---------------------------------------------------------------------------
t_section('A9 — the interview panel');
t_as_admin();
recruit_iv_migrate();

//  ---- a cleanroom candidate, so these assertions never depend on seed data --
db()->prepare("INSERT INTO candidates (first_name,last_name,stage,department,created_at) VALUES (?,?,?,?,?)")
    ->execute(['ZZ', 'Panel Test', 'INTERVIEW', 'Quality', date('c')]);
$ivCand = (int) db()->lastInsertId();
t_ok($ivCand > 0, 'ARMING · a test candidate exists');

// ---- (b) A ROUND CANNOT BE BOOKED TWICE WHILE ONE IS STILL OPEN -----------
$p = ['round' => 'L1', 'mode' => 'In person', 'scheduled_at' => '2026-11-02T10:00', 'panel_users' => [1]];
$iv1 = iv_schedule($ivCand, $p);
t_ok($iv1 > 0, 'the first L1 is scheduled');

$iv2 = iv_schedule($ivCand, $p);
t_eq($iv2, 0, '*** a second L1 is REFUSED while the first has no outcome');
t_ok(strpos(iv_last_error(), 'already scheduled') !== false,
    '*** and the refusal says so, naming the one that exists');
t_ok(strpos(iv_last_error(), '2026-11-02T10:00') !== false,
    '*** including when it is, so the person can go and change that one instead');

// ---- a finished round MAY be repeated, but only deliberately --------------
iv_record($iv1, ['result' => 'FAIL', 'rating' => 2, 'recommendation' => 'No hire']);
$iv3 = iv_schedule($ivCand, $p);
t_eq($iv3, 0, '*** repeating an ASSESSED round is refused without the re-interview tick');
t_ok(stripos(iv_last_error(), 're-interview') !== false,
    '*** and the refusal says what to tick, and why');

$iv4 = iv_schedule($ivCand, $p + ['reinterview' => 1]);
t_ok($iv4 > 0, '*** with the tick, the re-interview is allowed');
$r4 = iv_get($iv4);
t_eq((int) $r4['attempt'], 2, '*** and is recorded as attempt 2, not as another identical L1');
//  The first result must survive. Hiding a failed round is how a candidate gets
//  re-run until they pass with nobody able to see it happened.
$r1 = iv_get($iv1);
t_eq((string) $r1['result'], 'FAIL', '*** and the first outcome is still there, unchanged');

//  A DIFFERENT round is never blocked by this.
$l2 = iv_schedule($ivCand, ['round' => 'L2', 'scheduled_at' => '2026-11-05T10:00', 'panel_users' => [1]]);
t_ok($l2 > 0, '*** a different round is unaffected — this is not a lock on scheduling');

// ---- (c) WHO IS ON THE PANEL IS STORED AS IDS ------------------------------
//  The panel was a comma-joined string of NAMES and the ids were thrown away,
//  so "which interviews is this person on" had no answer that was safe to give.
t_ok(function_exists('iv_panel_user_ids'), 'the panel can be read back as ids');
t_eq(iv_panel_user_ids($r4), [1], '*** the ids chosen at scheduling are kept');
t_ok(function_exists('iv_my_upcoming'), 'and "which interviews am I on" is answerable');

//  Scoped to the person asking — never a window onto anybody else's panels.
$mine   = iv_my_upcoming(1, 60);
$theirs = iv_my_upcoming(99999, 60);
$ids    = array_map(fn($r) => (int) $r['id'], $mine);
t_ok(in_array($iv4, $ids, true), '*** an interviewer sees the interview they are on');
t_eq($theirs, [], '*** and somebody not on any panel sees nothing');

//  Only ones still to happen: a finished interview is not a task.
foreach ($mine as $r) t_eq((string) $r['result'], 'SCHEDULED', 'only interviews still to be held are listed');

// ---- the screen behind the count actually exists --------------------------
//  A count on My Work that links nowhere is worse than no count.
t_ok(function_exists('ops_my_interviews'), 'the diary screen has a handler');
t_ok(is_file(__DIR__ . '/../views/ops/my_interviews.php'), 'and a view');
$ops = (string) file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "\$route === 'my-interviews'") !== false, 'and a route');
t_ok(strpos($ops, "'/my-interviews'") !== false, 'and My Work links to it');

// ---- (a) THE PANEL LIST IS FILTERED, NOT GATED -----------------------------
//  Deliberate: the right technical interviewer for a role very often holds no
//  recruitment access at all — they are the senior person in that discipline.
//  Hiding them would swap a cluttered list for one missing the people you need.
$iv = (string) file_get_contents(__DIR__ . '/../lib/recruit_iv.php');
t_ok(strpos($iv, 'ivp-dept') !== false, 'the panel picker has a department filter');
t_ok(strpos($iv, 'ivp-find') !== false, 'and a search box');
t_ok(strpos($iv, 'All departments') !== false,
    '*** and everybody stays one click away — it filters, it does not gate');
t_ok(strpos($iv, 'is_active=1') !== false, 'inactive people are still excluded');

$js = (string) file_get_contents(__DIR__ . '/../assets/js/app.js');
t_ok(strpos($js, 'ivp-row') !== false, 'the filter is wired up');
t_ok(strpos($js, 'if (box && box.checked) hit = true;') !== false,
    '*** somebody already chosen is never hidden by the filter');
t_eq(substr_count($js, '{'), substr_count($js, '}'), 'app.js braces balance');
t_as_nobody();
