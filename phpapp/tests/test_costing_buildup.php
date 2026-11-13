<?php
// Owner, 13 Nov 2026 — looking at the box of loading percentages on a costing:
// "is that considered in our costing or not? If yes then where, and why costing of
// manpower is not included in it". The loadings were in every figure, but the box
// showed only percentages. "How this price is built" lays the same sum out in
// rupees, starting from the manpower cost — and must agree with the costing's own
// totals to the rupee.
t_section('Costing — how the price is built, in rupees');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    t_as_admin();
    if (function_exists('pc_migrate')) pc_migrate(); ch_migrate();
    $mk = function (array $h) use ($pdo) {
        $h += ['basis' => 'LONG', 'load_on' => 'COST', 'overhead_pct' => 8, 'insurance_pct' => 0.5, 'baddebt_pct' => 0, 'contingency_pct' => 0.5, 'negotiation_pct' => 5];
        $pdo->prepare("INSERT INTO project_costings (code, title, basis, load_on, status, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct, created_at) VALUES (?, 'Build-up probe', ?, ?, 'DRAFT', ?,?,?,?,?, ?)")
            ->execute(['BU-' . mt_rand(1000, 999999), $h['basis'], $h['load_on'], $h['overhead_pct'], $h['insurance_pct'], $h['baddebt_pct'], $h['contingency_pct'], $h['negotiation_pct'], date('c')]);
        return (int)$pdo->lastInsertId();
    };
    $role = function ($cid, $name, $personnel, $boq, $tm, $oneoff = 0) use ($pdo) {
        $pdo->prepare("INSERT INTO project_costing_lines (costing_id, role_label, headcount, boq_qty, target_margin, heads, oneoff) VALUES (?,?,?,?,?,?,?)")
            ->execute([$cid, $name, 1, $boq, $tm, json_encode(['PERSONNEL' => $personnel]), $oneoff]);
    };
    $cid = $mk([]);
    t_eq(pc_buildup($cid), null, 'a costing with no role yet has nothing to build from — the screen says "add a role"');
    $role($cid, 'Site Engineer', 45000, 12, 22);
    $bu = pc_buildup($cid); $roll = pc_rollup($cid);
    $by = []; foreach ($bu['rows'] as $r) $by[$r['label']] = $r;
    t_eq(round($by['Manpower cost — all roles above']['amount'], 2), 540000.0, 'the first line IS the manpower cost: 45,000 × 12 man-months');
    t_eq(round($by['Overhead 8%']['amount'], 2), 43200.0, 'Overhead 8% of the manpower cost = 43,200');
    t_eq([round($by['Insurance 0.5%']['amount'], 2), round($by['Contingency 0.5%']['amount'], 2)], [2700.0, 2700.0], 'Insurance and Contingency 0.5% = 2,700 each');
    t_ok(!isset($by['Bad debt 0%']) && !array_filter(array_keys($by), fn($k) => strpos($k, 'Bad debt') === 0), 'a loading of 0% adds no line');
    t_ok(abs($by['Your cost']['amount'] - (float)$roll['totals']['cost']) < 0.01 && abs($by['Your cost']['amount'] - 588600) < 0.01, '"Your cost" is the costing\'s own Project cost (5,88,600)');
    $sum = $by['Manpower cost — all roles above']['amount'] + $by['Overhead 8%']['amount'] + $by['Insurance 0.5%']['amount'] + $by['Contingency 0.5%']['amount'];
    t_ok(abs($sum - $by['Your cost']['amount']) < 0.05, 'the lines above it add up to it');
    t_ok(abs($by['Your cost']['amount'] + $by['Your margin']['amount'] - $by['Price before negotiation']['amount']) < 0.05, 'cost + margin = price before negotiation');
    t_ok(strpos($by['Your margin']['note'], '22% of the price before negotiation') === 0, 'the margin line says what it is a % of: ' . $by['Your margin']['note']);
    t_ok(abs($by['Price before negotiation']['amount'] * 0.05 - $by['Negotiation padding 5%']['amount']) < 0.2, 'negotiation padding is 5% on top of that price');
    $last = end($bu['rows']);
    t_ok($last['kind'] === 'grand' && abs($last['amount'] - (float)$roll['totals']['revenue']) < 0.01, 'the last line is the costing\'s own Project revenue — the ladder and the totals cannot disagree');

    //  Two roles, a one-time cost, loadings applied on the RATE, no negotiation.
    $c2 = $mk(['load_on' => 'RATE', 'negotiation_pct' => 0]);
    $role($c2, 'Engineer', 60000, 6, 20, 5000); $role($c2, 'Helper', 20000, 6, 15);
    $b2 = pc_buildup($c2); $r2 = pc_rollup($c2); $y = []; foreach ($b2['rows'] as $r) $y[$r['label']] = $r;
    t_eq(round($y['Manpower cost — all roles above']['amount'], 2), 480000.0, 'two roles: the manpower cost is both of them (3,60,000 + 1,20,000)');
    t_eq(round($y['One-time costs']['amount'], 2), 5000.0, 'a one-time cost has its own line');
    t_ok($b2['on_rate'] && $y['Overhead 8%']['note'] === 'of the rate', 'when loadings are applied on the rate, each line says "of the rate"');
    $s2 = 0; foreach ($b2['rows'] as $r) { if ($r['kind'] === 'total') break; $s2 += $r['amount']; }
    t_ok(abs($s2 - (float)$r2['totals']['cost']) < 0.5, 'manpower + one-time + loadings = Project cost, to the rupee (' . round($s2, 2) . ' vs ' . $r2['totals']['cost'] . ')');
    t_ok(!isset($y['Price before negotiation']) && end($b2['rows'])['kind'] === 'grand' && abs(end($b2['rows'])['amount'] - (float)$r2['totals']['revenue']) < 0.01,
        'with no negotiation padding there is no padding line — cost + margin is the price quoted');

    $v = (string)file_get_contents(__DIR__ . '/../views/ops/project_costing.php');
    t_ok(strpos($v, 'How this price is built') !== false && strpos($v, 'pc_buildup((int)$h[\'id\'])') !== false, 'the ladder sits at the top of "Loadings, basis & details"');
    t_ok(strpos($v, 'The percentages below are added on top of the <b>manpower cost</b>') !== false, 'and, before any role exists, it says where the manpower cost comes from');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
