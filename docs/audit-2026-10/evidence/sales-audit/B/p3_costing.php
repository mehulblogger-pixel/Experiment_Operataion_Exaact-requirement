<?php
// P3 — project costing formula, margin, floors, approval of the costing, costing -> quote.
putenv('OPS_MAIL_ENABLED');
require __DIR__ . '/harness.php';
$mm  = mkuser('mm1', 'MARKETING_MANAGER');
$bdm = mkuser('bdm1', 'BUSINESS_DEV_MANAGER');
show('pc_defaults() (settings fallback)', pc_defaults());
show('cost heads catalogue', array_keys(pc_heads()));

function mkcost($uid, $basis, $title) {
    $before = (int) ops_val("SELECT COALESCE(MAX(id),0) FROM project_costings");
    $r = route($uid, 'project-costing-new', 'POST', [], ['title' => $title, 'basis' => $basis, 'office_id' => (string) probe_office()]);
    $id = (int) ops_val("SELECT COALESCE(MAX(id),0) FROM project_costings");
    if ($id <= $before) { echo "  create refused: " . flashes($r) . "\n"; return 0; }
    return $id;
}
function addline($uid, $cid, array $heads, $boq, $tm, $manual = 0, $oneoff = 0, $role = 'Engineer') {
    return route($uid, 'project-costing-line-save', 'POST', [], ['costing_id' => $cid, 'role_label' => $role,
        'head' => $heads, 'boq_qty' => $boq, 'target_margin' => $tm, 'rate_manual' => $manual, 'oneoff' => $oneoff]);
}
function hdr($uid, $cid, array $over) {
    $h = pc_get($cid);
    $post = array_merge(['id' => $cid, 'title' => $h['title'], 'basis' => $h['basis'], 'load_on' => $h['load_on'],
        'office_id' => (string) probe_office(),
        'overhead_pct' => $h['overhead_pct'], 'insurance_pct' => $h['insurance_pct'], 'baddebt_pct' => $h['baddebt_pct'],
        'contingency_pct' => $h['contingency_pct'], 'negotiation_pct' => $h['negotiation_pct']], $over);
    return route($uid, 'project-costing-save', 'POST', [], $post);
}
function roll($cid) { $r = pc_rollup($cid); $out = [];
    foreach ($r['lines'] as $ln) { $c = pc_line_calc($ln, $r['header']); unset($c['heads']); $out[] = $c; }
    return ['lines' => $out, 'totals' => $r['totals'], 'status' => $r['header']['status']]; }

echo "\n== A. COST-loaded, man-month, target margin 20% ==\n";
$c1 = mkcost($bdm, 'LONG', 'A');
addline($bdm, $c1, ['PERSONNEL' => 50000, 'TRANSPORT' => 5000], 12, 20);
show('header %', ops_one("SELECT basis,load_on,overhead_pct,insurance_pct,baddebt_pct,contingency_pct,negotiation_pct FROM project_costings WHERE id=?", [$c1]));
show('rollup', roll($c1));
$direct = 55000; $load = 8 + 0.5 + 0 + 0.5; $loaded = $direct * (1 + $load / 100); $rate = $loaded / (1 - 0.20); $prop = $rate * 1.05;
show('hand calc loaded / rate / proposed / margin', [round($loaded, 2), round($rate, 2), round($prop, 2), round(($prop - $loaded) / $prop * 100, 2)]);

echo "\n== B. load on RATE (client sheet mode) ==\n";
hdr($bdm, $c1, ['load_on' => 'RATE']);
show('rollup load_on=RATE', roll($c1));

echo "\n== C. costs exceed price (manual rate below cost) ==\n";
$c2 = mkcost($bdm, 'DAILY', 'C');
addline($bdm, $c2, ['PERSONNEL' => 3000, 'FOOD' => 250], 20, 0, 2000);
show('rollup (manual rate 2000 vs direct 3250/day)', roll($c2));
$r = route($bdm, 'project-costing-submit', 'POST', [], ['id' => $c2]); show('BDM submits loss-making costing', flashes($r));
$r = route($mm, 'project-costing-approve', 'POST', [], ['id' => $c2]); show('MM approves loss-making costing', flashes($r) . ' status=' . pc_get($c2)['status']);

echo "\n== D. odd inputs ==\n";
$c3 = mkcost($bdm, 'LONG', 'D');
addline($bdm, $c3, ['PERSONNEL' => 40000, 'TRANSPORT' => -60000], 6, 10);
show('negative cost head (-60000 transport)', roll($c3)['lines'][0]);
addline($bdm, $c3, ['PERSONNEL' => 40000], 6, 150, 0, 0, 'tm150');
addline($bdm, $c3, ['PERSONNEL' => 40000], 6, -30, 0, 0, 'tm-30');
addline($bdm, $c3, ['PERSONNEL' => 40000], -6, 10, 0, 0, 'boq-6');
addline($bdm, $c3, ['PERSONNEL' => 40000], 6, 10, -5000, 0, 'manual-5000');
addline($bdm, $c3, ['PERSONNEL' => 40000], 6, 10, 0, -100000, 'oneoff-100000');
foreach (roll($c3)['lines'] as $i => $l) if ($i) show('  line ' . $i, $l);
$r = hdr($bdm, $c3, ['overhead_pct' => '-50', 'negotiation_pct' => '-40']);
show('header overhead -50%, negotiation -40% accepted?', flashes($r) . ' ' . json_encode(ops_one("SELECT overhead_pct,negotiation_pct FROM project_costings WHERE id=?", [$c3])));
$c4 = mkcost($bdm, 'LONG', 'E');
hdr($bdm, $c4, ['load_on' => 'RATE', 'overhead_pct' => '8', 'insurance_pct' => '0.5', 'contingency_pct' => '0.5', 'negotiation_pct' => '0']);
addline($bdm, $c4, ['PERSONNEL' => 50000], 12, 95);
show('RATE mode, target 95% + loadings 9% (denominator <0)', roll($c4)['lines'][0]);

echo "\n== E. costing approval controls ==\n";
$c5 = mkcost($mm, 'LONG', 'F');
addline($mm, $c5, ['PERSONNEL' => 50000], 12, 20);
$r = route($mm, 'project-costing-approve', 'POST', [], ['id' => $c5]);
show('MM approves own DRAFT costing without submitting', flashes($r) . ' status=' . pc_get($c5)['status'] . ' approved_by=' . pc_get($c5)['approved_by']);
$r = route($bdm, 'project-costing-approve', 'POST', [], ['id' => $c5]);
show('BDM tries to approve', flashes($r));
$r = route($bdm, 'project-costing-reopen', 'POST', [], ['id' => $c5]);
$h = pc_get($c5); show('BDM reopens the APPROVED costing', flashes($r) . ' status=' . $h['status'] . ' approved_by still=' . $h['approved_by']);
addline($bdm, $c5, ['PERSONNEL' => 50000], 12, 0, 1000, 0, 'cheap');
show('  BDM then adds a loss line; rollup', roll($c5)['totals']);

echo "\n== F. costing -> quote ==\n";
$c6 = mkcost($bdm, 'LONG', 'G-draft');
addline($bdm, $c6, ['PERSONNEL' => 50000, 'TRANSPORT' => 5000], 12, 20);
$r = route($bdm, 'project-costing-to-quote', 'POST', [], ['id' => $c6]);
$qid = (int) pc_get($c6)['quote_id'];
show('quote from a DRAFT (unapproved) costing', flashes($r));
show('  quote header', ops_one("SELECT id,status,sbu,owner_id,office_id,gst_pct,subtotal,total_amount FROM quotations WHERE id=?", [$qid]));
show('  quote lines', ops_all("SELECT qty,unit,rate,amount FROM quote_lines WHERE quote_id=?", [$qid]));
show('  costing figures', ops_one("SELECT exp_revenue,exp_cost,exp_profit FROM project_costings WHERE id=?", [$c6]));
// Now cut the quote rate to below cost through the normal edit route.
$post = ['office_id' => (string) probe_office(), 'client_name' => 'Probe', 'subject' => 'G', 'gst_pct' => '18', 'validity_days' => '30', 'currency' => 'INR', 'advance_pct' => '0',
    'l_desc' => ['Engineer'], 'l_qty' => ['12'], 'l_rate' => ['10000'], 'l_unit' => ['MANMONTH'], 'l_service' => [''], 'l_sbu' => [''], 'l_order' => ['LINE'], 'l_location' => ['']];
$r = route($bdm, 'quote-edit', 'POST', ['id' => $qid], $post);
show('BDM cuts the quote rate to 10,000/month (cost ~59,950/month)', flashes($r));
show('  quote now', ops_one("SELECT status,subtotal,total_amount FROM quotations WHERE id=?", [$qid]));
show('  costing panel on the quote shows (exp_* unchanged)', ops_one("SELECT exp_revenue,exp_profit, ROUND(exp_profit*100.0/exp_revenue,1) margin_pct FROM project_costings WHERE id=?", [$qid ? $c6 : 0]));
$r = status($mm, $qid, 'APPROVED');
show('MM approves the below-cost quote directly (no rules)', flashes($r) . ' status=' . qrow($qid)['status']);
