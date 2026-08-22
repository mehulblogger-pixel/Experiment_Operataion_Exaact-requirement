<?php
// Simplification, step 2 — dashboards consolidated under Insights. The Sales
// dashboard and the Management dashboard (MIS) move to Insights (their proper
// home); nothing deleted — the routes and screens are untouched.
t_section('dashboards consolidated under Insights (simplify step 2)');

$a = (string)file_get_contents(__DIR__ . '/../lib/areas.php');

// Both now appear in the Insights block.
$insights = substr($a, strpos($a, "case 'insights':"), 1500);
t_ok(strpos($insights, "'Sales dashboard', '/crm-dashboard'") !== false, 'the Sales dashboard now lives under Insights');
t_ok(strpos($insights, "'Management dashboard', '/mis'") !== false, 'the Management dashboard (MIS) now lives under Insights');

// And are gone from their old areas.
$sales = substr($a, strpos($a, "case 'sales':"), 1600);
t_ok(strpos($sales, "'Sales dashboard', '/crm-dashboard'") === false, 'the Sales dashboard tile is removed from Sales');
$admin = substr($a, strpos($a, "case 'admin':"), 3000);
t_ok(strpos($admin, "'Management dashboard', '/mis'") === false, 'the Management dashboard tile is removed from Admin');

// Rail-highlight routes moved with them.
t_ok(strpos($insights, "'crm-dashboard','mis'") !== false, 'the dashboard routes highlight Insights now');
