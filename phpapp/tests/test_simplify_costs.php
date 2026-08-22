<?php
// Simplification, step 4 — the cost/profit screens move out of Admin into Money,
// so all money lives together. Outright home-move; routes and screens untouched.
t_section('cost screens moved to Money (simplify step 4)');

$a = (string)file_get_contents(__DIR__ . '/../lib/areas.php');
$money = substr($a, strpos($a, "case 'money':"), 2600);
$admin = substr($a, strpos($a, "case 'admin':"), 2200);

foreach ([['/office-finance'], ['/cost-run'], ['/sbu-pl'], ['/call-profit']] as [$rt]) {
    t_ok(strpos($money, "'$rt'") !== false, "$rt now lives under Money");
    t_ok(strpos($admin, ", '$rt',") === false && strpos($admin, "'$rt', '") === false, "$rt is removed from Admin's tiles");
}
t_ok(strpos($money, "'Costs & margins'") !== false, 'Money has a Costs & margins section');
t_ok(strpos($admin, "'Masters & costs'") === false && strpos($admin, "\$sec('Masters')") !== false,
    "Admin's section is renamed to just Masters");
t_ok(strpos($money, "office-finance','cost-run','call-profit") !== false, 'the cost routes highlight Money now');
t_ok(strpos($admin, "'sbu-pl','call-profit'") === false, 'the cost routes are gone from Admin');
