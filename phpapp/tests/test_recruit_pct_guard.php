<?php
// The requirement cost build-up is correct maths — the trap is typing a rupee
// amount into a percentage box. These lock the sane result and the new guard.
t_section('requirement cost percentage guard');

// A sane 25% statutory on a 60k wage builds up correctly: 60000 + 15000 + 8000.
$ok = req_cost_buildup(['sourcing_model' => 'OWN_PAYROLL', 'cost_wage' => 60000,
                        'cost_statutory_pct' => 25, 'cost_reimburse' => 8000]);
t_ok(abs($ok['monthly'] - 83000) < 0.01, '25% statutory on a 60k wage builds up to 83,000 / month');

// Typing 8000 (rupees) into the % box is read as 8000% — the exact blow-up in
// the screenshot (statutory becomes 48 lakh). The maths is right; the input was
// wrong, which is why the guard exists.
$bad = req_cost_buildup(['sourcing_model' => 'OWN_PAYROLL', 'cost_wage' => 60000,
                         'cost_statutory_pct' => 8000, 'cost_reimburse' => 8000]);
t_ok($bad['monthly'] > 4000000, 'a statutory value of 8000 is read as 8000% and explodes the cost');

$ops = (string)file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, 'typed into a percentage box') !== false,
    'the requisition save refuses a rupee amount in a % field (over 100%)');

$rf = (string)file_get_contents(__DIR__ . '/../views/ops/requisition_form.php');
t_ok(strpos($rf, 'max="100" name="cost_statutory_pct"') !== false, 'the statutory % field is capped at 100');
t_ok(strpos($rf, '% of wage') !== false, 'the label makes clear it is a percent of the wage, not rupees');
