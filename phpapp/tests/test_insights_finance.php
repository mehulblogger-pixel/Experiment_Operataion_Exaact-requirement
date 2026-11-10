<?php
// Owner, 10 Nov 2026 (screenshots): a Finance login whose Insights rights are
// "Dashboards / reports: View" and only the Financial dashboard was shown every
// dashboard. Each screen now asks for the dashboard right that covers it.
t_section('Insights — a Finance login sees only what its dashboard ticks allow');
t_as_admin();
$tag = 'INF' . getmypid();
db()->prepare("INSERT INTO users (username, password_hash, first_name, role, is_active) VALUES (?, 'x', 'Fin', 'FINANCE', 1)")->execute([strtolower($tag)]);
$uid = (int) db()->lastInsertId();
$keep = $_SESSION; $_SESSION['uid'] = $uid; current_user(true); ua(true);

t_ok(can('dash.financial') && !can('dash.operations'), 'ARMING · Finance holds the Financial dashboard and not the Operations one');
t_eq(array_keys(tapi_dashboards_for_me()), [], 'no analytics view is theirs — each shows operations or quality figures');
t_ok(!tapi_can(), 'so "Analytics & performance" does not open for them');
t_ok(!mis_can(), 'nor the Management dashboard, which puts operations and money side by side');
$g = dashboard_glance();
t_ok($g['mgmt'] === null || $g['mgmt']['health'] === null, 'and the platform health light is not shown to them');
$ins = json_encode(ops_area_def('insights'));
t_ok(strpos($ins, 'Analytics') === false && strpos($ins, 'Management dashboard') === false, 'the Insights page offers neither tile');

$_SESSION = $keep; current_user(true); ua(true);
t_ok(count(tapi_dashboards_for_me()) === count(tapi_dashboards()), 'an administrator still sees every analytics view');
db()->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
