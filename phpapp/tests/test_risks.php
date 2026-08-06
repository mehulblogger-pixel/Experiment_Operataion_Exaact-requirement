<?php
// Risk & opportunity register (ISO §8.5, Roadmap Step 28).
t_section('risks & opportunities (ISO 8.5)');

t_ok(count(risk_opts('risk_category')) > 0, 'risk-category master seeded');

// The likelihood x impact rating grid.
t_eq(risk_rate('L', 'L'), 'LOW', 'low x low = LOW');
t_eq(risk_rate('H', 'M'), 'HIGH', 'high x medium = HIGH');
t_eq(risk_rate('H', 'H'), 'CRITICAL', 'high x high = CRITICAL');
t_eq(risk_rate('', 'H'), '', 'missing a factor gives no rating');

[$id, $err] = risk_save([
    'kind' => 'RISK', 'title' => 'Single-point NDT inspector', 'category' => 'COMPETENCE',
    'likelihood' => 'M', 'impact' => 'H', 'treatment' => 'Cross-train a second inspector', 'owner' => 'Ops',
]);
t_ok($id > 0 && $err === '', 'a risk is added');
$r = risk_get($id);
t_ok(strpos($r['risk_code'], 'RSK-') === 0, "a code is assigned ({$r['risk_code']})");
t_eq($r['status'], 'OPEN', 'new entry starts OPEN');
t_eq($r['rating'], 'HIGH', 'the rating is derived from likelihood x impact');

[$bad, $e2] = risk_save(['title' => '']);
t_ok($bad === 0 && $e2 !== '', 'an entry with no title is refused');

// High/critical open items are counted for the badge.
t_ok(risk_counts()['high'] >= 1, 'high/critical open items are counted');

// Work it through to closure.
t_eq(risk_set_status($id, 'TREATING'), '', 'move to TREATING');
t_eq(risk_set_status($id, 'CLOSED', 'second inspector now qualified'), '', 'close it');
$r = risk_get($id);
t_eq($r['status'], 'CLOSED', 'status is CLOSED');
t_ok($r['closed_on'] !== '', 'closing details recorded');

$openIds = array_column(risks_all(['open' => 1]), 'id');
t_ok(!in_array($id, $openIds, true), 'a closed entry drops out of the open list');
