<?php
// Simplification, step 9 — the scheduling family (board, capacity outlook,
// availability) becomes ONE tabbed workspace via a shared sched_tabs() strip.
// These tiles live on the Operations home + command index, not areas.php.
// Nothing deleted: every route resolves and each screen keeps its handler/gate.
t_section('scheduling folded into one tabbed workspace (simplify step 9)');

// The shared tab strip helper exists and covers the three tabs.
$sb = (string)file_get_contents(__DIR__ . '/../lib/schedboard.php');
t_ok(strpos($sb, 'function sched_tabs(') !== false, 'the sched_tabs() tab strip helper exists');
foreach ([['schedule','/schedule'], ['capacity-outlook','/capacity-outlook'],
          ['availability','/availability']] as [$key, $href]) {
    t_ok(strpos($sb, "'$key'") !== false && strpos($sb, "'$href'") !== false,
        "the tab strip includes the $key tab");
}
// Each tab is gated by its own permission.
t_ok(strpos($sb, 'sched_board_can()') !== false, 'the Board tab is gated by sched_board_can()');
t_ok(strpos($sb, 'tosrm_ops_desk_can()') !== false, 'the Capacity tab is gated by tosrm_ops_desk_can()');
t_ok(strpos($sb, 'can_manage_availability()') !== false, 'the Availability tab is gated by can_manage_availability()');

// Each of the three views renders the tab strip, marking its own tab active.
$views = ['schedule_board' => 'schedule', 'capacity_outlook' => 'capacity-outlook',
          'availability' => 'availability'];
foreach ($views as $file => $active) {
    $v = (string)file_get_contents(__DIR__ . "/../views/ops/$file.php");
    t_ok(strpos($v, "sched_tabs('$active')") !== false, "views/ops/$file.php shows the tab strip (active: $active)");
}

// Operations home: three tiles collapse into one Scheduling board tile.
$home = (string)file_get_contents(__DIR__ . '/../views/ops/operations_home.php');
t_ok(strpos($home, "'/schedule', '🗓️', 'Scheduling board'") !== false, 'the Scheduling board tile stays');
t_ok(strpos($home, "\$tile('/capacity-outlook'") === false, 'the separate Capacity outlook tile is gone from the home');
t_ok(strpos($home, "\$tile('/availability'") === false, 'the separate Availability tile is gone from the home');

// Command index: the routes stay findable (nothing becomes unsearchable).
$nav = (string)file_get_contents(__DIR__ . '/../lib/navindex.php');
foreach (['/schedule', '/capacity-outlook', '/availability'] as $rt) {
    t_ok(strpos($nav, "'$rt'") !== false, "$rt is still findable in the command index");
}
t_ok(strpos($nav, 'a tab of the scheduling board') !== false,
    'the index tells you Capacity/Availability are tabs of the board');
