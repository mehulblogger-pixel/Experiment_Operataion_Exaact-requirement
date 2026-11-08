<?php
// Owner, 8 Nov 2026: "every time we update any company profile ... all the
// features get disabled and we need to activate it again and again." Saving the
// profile re-applied the activities from scratch and switched off every module
// they did not imply. Now only a CHANGE of activities changes modules, and only
// by the difference.
t_section('Saving the company profile keeps the modules the company chose');
$cat = cockpit_capability_catalogue();
$byMods = [];
foreach ($cat as $code => $c) { $m = (array) ($c['modules'] ?? []); sort($m); if ($m) $byMods[implode(',', $m)] = $code; }
$codes = array_values($byMods);
t_ok(count($codes) >= 2, 'the activity list has activities that need different modules');
[$c1, $c2] = [$codes[0], $codes[1]];
$m1 = cockpit_capability_modules([$c1]); $m2 = cockpit_capability_modules([$c2]);
$all = array_keys(array_filter(PRODUCT_MODULES, fn($m) => empty($m[3])));
$yes = fn($k) => true;

// Same activities, every module on (some turned on by hand): nothing changes.
$p = cockpit_capability_change_plan([$c1], [$c1], $all, $yes);
t_eq($p['on'], (function ($a) { sort($a); return $a; })($all), 'the same activities keep every module exactly as it is');
t_eq([$p['added'], $p['removed']], [[], []], 'and nothing is reported as changed');

// An activity added: its modules come on; nothing else is touched.
$on = $m1;
$p = cockpit_capability_change_plan([$c1], [$c1, $c2], $on, $yes);
t_ok(!array_diff($m2, $p['on']) && !array_diff($m1, $p['on']), 'adding an activity switches on what it needs and keeps the rest');
// …but never a module outside the plan.
$p = cockpit_capability_change_plan([$c1], [$c1, $c2], $on, fn($k) => in_array($k, $m1, true));
t_ok(!array_diff($p['on'], $m1), 'a module the plan does not include is never switched on');

// An activity removed: only modules no remaining activity needs go off; a
// module the company turned on by hand stays on.
$hand = array_values(array_diff($all, $m1, $m2));
$on = array_values(array_unique(array_merge($m1, $m2, $hand)));
$p = cockpit_capability_change_plan([$c1, $c2], [$c1], $on, $yes);
t_ok(!array_diff($m1, $p['on']), 'the remaining activity keeps its modules');
t_ok(!array_diff($hand, $p['on']), 'modules turned on by hand under Features stay on');
t_ok(!array_intersect(array_diff($m2, $m1), $p['on']), 'only what the removed activity alone needed goes off');

$src = (string) file_get_contents(__DIR__ . '/../lib/company.php');
t_ok(strpos($src, '$a !== $b ? cockpit_apply_capability_change(') !== false, 'the profile applies only a change of activities');
