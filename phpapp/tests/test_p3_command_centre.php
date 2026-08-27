<?php
// Phase 3 §20 — the Command Centre. The management counterpart to §19: one board that COMPOSES the three
// aggregators that already exist — attention_summary (business), financial_rollup (§27 money) and
// system_status (platform health) — into distinct bands, keeping business and technical health separate
// (§20/§21). It computes nothing new. Self-contained.
t_section('Phase 3 §20 — Command Centre (composes attention + money + health)');

t_ok(function_exists('command_centre') && function_exists('ops_command_centre'), 'the command-centre helpers exist');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "case \$route === 'command-centre'") !== false, 'the /command-centre route is dispatched');
$nav = file_get_contents(__DIR__ . '/../lib/navindex.php');
t_ok(strpos($nav, "/command-centre") !== false, 'the Command Centre is in the navigation (for management)');
t_ok(strpos($nav, "'/tasks'") !== false, 'My tasks (§26) is in the navigation');

// It reads exactly the three existing aggregators — the bands are the composition, no new computation.
$c = command_centre();
t_ok(array_key_exists('business', $c) && array_key_exists('money', $c) && array_key_exists('health', $c),
     'the board carries the business, money and health bands');
t_ok($c['business'] === (function_exists('attention_summary') ? attention_summary() : []),
     'the business band IS attention_summary (composed, not recomputed)');
// (system_status re-queries live state, so it is not identical object-for-object across two calls —
// assert the band carries its shape, i.e. it is the system_status list, not a recomputation of our own.)
$healthShapeOk = is_array($c['health']) && (empty($c['health']) || (isset($c['health'][0]['severity']) && isset($c['health'][0]['label'])));
t_ok($healthShapeOk, 'the health band is the system_status list (severity + label rows)');
t_ok(is_array($c['money']) && array_key_exists('outstanding', $c['money']),
     'the money band is the §27 financial rollup');
t_ok(in_array($c['health_worst'], ['ok', 'warn', 'bad'], true), 'the board carries the worst health severity');

// The separation rule: business KPIs and platform health are distinct lists, never merged into one score.
$view = file_get_contents(__DIR__ . '/../views/ops/command_centre.php');
t_ok(strpos($view, 'Needs attention') !== false && strpos($view, 'Platform health') !== false,
     'the screen shows business and platform health as separate bands');
