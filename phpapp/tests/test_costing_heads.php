<?php
// Owner, 9 Nov 2026 — flexible costing heads: each head an amount, a % of other
// heads, quantity × rate or a formula; per month / day / year / one time; with a
// remark; a library whose values change from a date; templates. "Suppose
// statutory requirement is of 12% of basic then it shall take the basic of the
// candidate."
t_section('Costing heads — the engine');
t_as_admin();
$items = function (array $rows) { return ch_items_clean($rows)[0]; };

// The calculator in an amount box.
t_eq(ch_calc_number('1500*2+300'), [3300.0, ''], 'an amount box is a calculator: 1500*2+300 = 3300');
t_eq(ch_calc_number('18,000'), [18000.0, ''], 'thousands commas are fine');
t_eq(ch_calc_number('(1000+200)/2'), [600.0, ''], 'brackets and division work');
t_ok(ch_calc_number('BASIC*2')[0] === null, 'a head name in an amount box is refused — that is what "Formula" is for');
t_ok(ch_calc_number('5/0')[0] === null && strpos(ch_calc_number('5/0')[1], 'zero') !== false, 'dividing by zero is refused in plain words');
t_ok(ch_calc_number('system("ls")')[0] === null, 'nothing but arithmetic can run');

// Formulas.
t_eq(ch_expr_eval('MIN(BASIC,15000)*12%', ['BASIC' => 20000], $e), 1800.0, 'MIN(BASIC,15000)*12% on a basic of 20,000 = 1,800');
t_eq(ch_expr_eval('IF(GROSS<=21000, GROSS*3.25%, 0)', ['GROSS' => 20000], $e), 650.0, 'IF works for an eligibility test');
t_eq(ch_expr_eval('IF(GROSS<=21000, GROSS*3.25%, 0)', ['GROSS' => 25000], $e), 0.0, '… and gives 0 above the limit');
t_eq(ch_expr_eval('round(1234.567, 1)', [], $e), 1234.6, 'ROUND takes decimals; function names are not case-sensitive');
t_ok(ch_expr_eval('BASIC +', ['BASIC' => 1], $e) === null && $e !== '', 'a broken formula is refused with a reason: ' . $e);
t_ok(ch_expr_eval('NOPE*2', [], $e) === null && strpos($e, 'NOPE') !== false, 'an unknown head name is named in the refusal');
t_eq(ch_expr_refs('MIN(BASIC,15000)+HRA*2'), ['BASIC', 'HRA'], 'the heads a formula uses are found (functions are not heads)');

// The owner's own example: statutory 12% of the candidate's basic.
$role = $items([
    ['label' => 'Basic salary', 'code' => 'BASIC', 'type' => 'AMOUNT', 'grp' => 'PAY', 'per' => 'MONTH', 'calc' => '18000'],
    ['label' => 'HRA', 'code' => 'HRA', 'type' => 'AMOUNT', 'grp' => 'PAY', 'per' => 'MONTH', 'calc' => '7200'],
    ['label' => 'PF employer', 'code' => 'PF', 'type' => 'PCT', 'grp' => 'STAT', 'pct' => 12, 'of' => ['BASIC']],
    ['label' => 'PF capped', 'code' => 'PFC', 'type' => 'PCT', 'grp' => 'STAT', 'pct' => 12, 'of' => ['BASIC'], 'cap' => 15000],
    ['label' => 'ESIC', 'code' => 'ESIC', 'type' => 'PCT', 'grp' => 'STAT', 'pct' => 3.25, 'of' => ['BASIC', 'HRA'], 'upto' => 21000],
    ['label' => 'Site allowance', 'code' => 'SITE', 'type' => 'AMOUNT', 'grp' => 'PAY', 'per' => 'DAY', 'calc' => '100', 'note' => 'paid only on site days'],
    ['label' => 'Travel', 'code' => 'TRAVEL', 'type' => 'QTYRATE', 'grp' => 'COST', 'per' => 'MONTH', 'qty' => 2, 'rate' => 1500],
    ['label' => 'Medical', 'code' => 'MED', 'type' => 'AMOUNT', 'grp' => 'COST', 'per' => 'YEAR', 'calc' => '1200'],
    ['label' => 'Mobilisation', 'code' => 'MOB', 'type' => 'AMOUNT', 'grp' => 'COST', 'per' => 'ONCE', 'calc' => '4000'],
    ['label' => 'Insurance', 'code' => 'INS', 'type' => 'FORMULA', 'grp' => 'COST', 'expr' => 'MAX((BASIC+HRA)*1%, 300)'],
]);
t_eq(count($role), 10, 'ten heads are kept');
$ev = ch_evaluate($role, 'MONTH', 26);
t_ok($ev['ok'], 'the role works out without errors');
t_eq($ev['rows']['PF']['unit'], 2160.0, '"12% of basic" takes the basic: 12% of 18,000 = 2,160');
t_eq($ev['rows']['PFC']['unit'], 1800.0, 'with "counted up to 15,000", 12% of 15,000 = 1,800');
t_ok(strpos($ev['rows']['PFC']['why'], 'counted up to') !== false, 'and it says why: ' . $ev['rows']['PFC']['why']);
t_eq($ev['rows']['ESIC']['unit'], 0.0, 'ESIC does not apply above its limit (gross 25,200 > 21,000)');
t_ok(strpos($ev['rows']['ESIC']['why'], 'Not applied') !== false, '… and says so');
t_eq($ev['rows']['SITE']['unit'], 2600.0, 'an allowance per day × 26 working days = 2,600 a month');
t_eq($ev['rows']['TRAVEL']['unit'], 3000.0, 'quantity × rate: 2 × 1,500 = 3,000');
t_eq($ev['rows']['MED']['unit'], 100.0, 'a yearly figure is spread monthly: 1,200 ÷ 12 = 100');
t_eq([$ev['rows']['MOB']['unit'], $ev['once']], [0.0, 4000.0], 'a one-time head is kept out of the monthly figure');
t_eq($ev['rows']['INS']['unit'], 300.0, 'a formula with a floor: MAX(1% of 25,200, 300) = 300');
t_eq($ev['pay'], 27800.0, 'salary = Basic + HRA + site allowance = 27,800');
t_eq($ev['direct'], 18000 + 7200 + 2160 + 1800 + 0 + 2600 + 3000 + 100 + 300.0, 'the monthly cost adds every recurring head');
$evD = ch_evaluate($role, 'DAY', 26);
t_eq($evD['rows']['SITE']['unit'], 100.0, 'on a daily sheet the same role shows per day: site allowance 100 a day');
t_eq($evD['rows']['BASIC']['unit'], round(18000 / 26, 2), '… and the monthly basic as a day rate (18,000 ÷ 26)');

// Rename a head: the % that uses it keeps working (it refers to the short name).
$ren = $role; $ren[0]['label'] = 'Basic pay (renamed)';
t_eq(ch_evaluate($ren, 'MONTH', 26)['rows']['PF']['unit'], 2160.0, 'renaming a head keeps the % of it working');

// Minimum and maximum.
$mm = $items([['label' => 'Basic', 'code' => 'BASIC', 'calc' => '10000', 'grp' => 'PAY'],
              ['label' => 'Bonus', 'code' => 'BON', 'type' => 'PCT', 'pct' => 8.33, 'of' => ['BASIC'], 'min' => 1000],
              ['label' => 'Cap', 'code' => 'CAPH', 'type' => 'PCT', 'pct' => 50, 'of' => ['BASIC'], 'max' => 2000]]);
$e2 = ch_evaluate($mm, 'MONTH', 26);
t_eq([$e2['rows']['BON']['unit'], $e2['rows']['CAPH']['unit']], [1000.0, 2000.0], 'a minimum lifts and a maximum caps the result');

// Errors.
$loop = $items([['label' => 'A', 'code' => 'A', 'type' => 'FORMULA', 'expr' => 'B*2'], ['label' => 'B', 'code' => 'B', 'type' => 'PCT', 'pct' => 10, 'of' => ['A']]]);
$e3 = ch_evaluate($loop, 'MONTH', 26);
t_ok(!$e3['ok'] && strpos(implode(' ', $e3['errors']), 'loop') !== false, 'heads that use each other in a loop are refused: ' . implode(' ', $e3['errors']));
$miss = $items([['label' => 'PF', 'code' => 'PF', 'type' => 'PCT', 'pct' => 12, 'of' => ['BASIC']]]);
t_ok(!ch_evaluate($miss, 'MONTH', 26)['ok'], 'a % of a head that is not on the role is refused');
$onceMix = $items([['label' => 'Mob', 'code' => 'MOB', 'per' => 'ONCE', 'calc' => '5000'], ['label' => 'Fee', 'code' => 'FEE', 'type' => 'PCT', 'pct' => 10, 'of' => ['MOB']]]);
t_ok(!ch_evaluate($onceMix, 'MONTH', 26)['ok'], 'a monthly % of a one-time head is refused (one-time % of it is fine)');
$onceOk = $items([['label' => 'Mob', 'code' => 'MOB', 'per' => 'ONCE', 'calc' => '5000'], ['label' => 'Fee', 'code' => 'FEE', 'type' => 'PCT', 'per' => 'ONCE', 'pct' => 10, 'of' => ['MOB']]]);
t_eq(ch_evaluate($onceOk, 'MONTH', 26)['once'], 5500.0, '… a one-time 10% of a one-time 5,000 adds 500');
[, $errs] = ch_items_clean([['label' => '', 'calc' => '5'], ['label' => 'X', 'type' => 'PCT', 'pct' => 5]]);
t_ok(count($errs) === 2, 'a head with no name, and a % of nothing, are both refused: ' . implode(' | ', $errs));
[$dup] = ch_items_clean([['label' => 'Food'], ['label' => 'Food']]);
t_ok($dup[0]['code'] !== $dup[1]['code'], 'two heads with the same name still get different short names');

t_section('Costing heads — on a costing role, and what recruitment gets');
pc_migrate(); pc_link_migrate(); ch_migrate();
$tag = 'CHD' . getmypid();
db()->prepare("INSERT INTO project_costings (code, title, basis, status, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct, created_at) VALUES (?, 'Heads probe', 'LONG', 'DRAFT', 10, 0, 0, 0, 0, ?)")
    ->execute([$tag, date('c')]);
$cid = (int) db()->lastInsertId(); $h = pc_get($cid);
$post = ['costing_id' => $cid, 'role_label' => 'Technician', 'headcount' => 2, 'boq_qty' => 24, 'target_margin' => 20, 'dpm' => 26,
         'items_json' => json_encode(array_map(fn($i) => $i + ['calc' => isset($i['amount']) ? (string) $i['amount'] : ''], $role))];
[$line, $errs] = pc_line_from_post($post, $h);
t_eq($errs, [], 'the editor\'s post is accepted');
$c = pc_line_calc($line, $h);
t_eq($c['direct'], $ev['direct'], 'a costing role uses the heads engine for its direct cost');
t_eq($c['loaded'], round($ev['direct'] * 1.1, 2), 'the overhead loading is applied on top, as before');
t_eq($c['pay'], 27800.0, 'its salary heads are kept apart — the requisition\'s wage');
t_eq([$c['oneoff'], $c['oneoff_total']], [4000.0, 8000.0], 'a one-time head is per person: 2 people × 4,000');
t_eq($c['cost'], round($c['loaded'] * 24 + 8000, 2), 'cost = loaded × man-months + one-time for each person');
db()->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin, rate_manual, oneoff, headcount, spec, created_at) VALUES (?,1,?,?,?,?,?,?,?,?,?)")
    ->execute([$cid, 'Technician', json_encode($c['heads']), 24, 20, 0, $c['oneoff'], 2, $line['spec'], date('c')]);
$lid = (int) db()->lastInsertId();
$roll = pc_rollup($cid);
t_eq($roll['labels']['SITE'] ?? '', 'Site allowance', 'the sheet\'s column names come from the role\'s own head names');
t_eq((float) $roll['by_head']['PF'], 2160.0, 'and its totals by head use the same short names');
db()->prepare("UPDATE project_costings SET basis='DAILY' WHERE id=?")->execute([$cid]);
pc_restamp($cid);
$ln = ops_one("SELECT * FROM project_costing_lines WHERE id=?", [$lid]);
t_eq((float) (json_decode($ln['heads'], true)['SITE'] ?? 0), 100.0, 'switching the sheet to daily turns the stored figures into day rates');
db()->prepare("UPDATE project_costings SET basis='LONG' WHERE id=?")->execute([$cid]); pc_restamp($cid);

// A role saved before the heads became flexible keeps its figures to the rupee.
db()->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin, rate_manual, oneoff, headcount, created_at) VALUES (?,2,'Old role',?,12,0,0,1000,1,?)")
    ->execute([$cid, json_encode(['PERSONNEL' => 30000, 'FOOD' => 5000]), date('c')]);
$old = ops_one("SELECT * FROM project_costing_lines WHERE id=?", [(int) db()->lastInsertId()]);
$co = pc_line_calc($old, pc_get($cid));
t_eq([$co['direct'], $co['pay'], $co['oneoff']], [35000.0, 30000.0, 1000.0], 'an older role is read as before: direct 35,000, salary 30,000, one-time 1,000');
$sp = ch_spec_of($old, 'LONG');
$cv = ch_evaluate($sp['items'], 'MONTH', $sp['dpm']);
t_eq([$cv['direct'], $cv['pay'], $cv['once']], [35000.0, 30000.0, 1000.0], 'opened in the editor, it becomes the same heads with the same figures');

// Errors on the save keep what was typed.
$bad = $post; $bad['items_json'] = json_encode([['label' => 'PF', 'type' => 'PCT', 'pct' => 12, 'of' => ['NOTHERE']]]);
[, $errs] = pc_line_from_post($bad, pc_get($cid));
t_ok($errs !== [], 'a role whose % points at a missing head is not saved: ' . implode(' ', $errs));
$bad2 = $post; $bad2['target_margin'] = 100;
t_ok(pc_line_from_post($bad2, pc_get($cid))[1] !== [], 'a 100% target margin is refused');

t_section('Costing heads — the library changes from a date; templates');
$lib = ch_library();
t_ok(isset($lib['BASIC'], $lib['PF_ER'], $lib['ESIC_ER'], $lib['BONUS'], $lib['GRATUITY']), 'the library starts with salary and statutory heads');
t_ok(strpos((string) ($lib['PF_ER']['note'] ?? ''), 'accountant') !== false, 'every starting statutory value says to confirm it with the accountant');
t_eq([(float) $lib['PF_ER']['pct'], (float) $lib['PF_ER']['cap']], [12.0, 15000.0], 'PF starts as 12% of Basic + DA, counted up to 15,000');
$code = 'PFT' . substr((string) getmypid(), -4);
[$ok] = ch_library_save(['label' => 'Test PF', 'code' => $code, 'type' => 'PCT', 'grp' => 'STAT', 'pct' => 12, 'of' => ['BASIC'], 'cap' => 15000], '2020-01-01', 'Probe');
t_ok($ok, 'a head is saved to the library');
[$ok] = ch_library_save(['label' => 'Test PF', 'code' => $code, 'type' => 'PCT', 'grp' => 'STAT', 'pct' => 12, 'of' => ['BASIC'], 'cap' => 25000], '2030-04-01', 'Probe');
t_ok($ok, 'a new value is added from a later date (the law changes)');
t_eq((float) ch_library('2029-12-31')[$code]['cap'], 15000.0, 'before that date the old value applies');
t_eq((float) ch_library('2030-04-01')[$code]['cap'], 25000.0, 'from that date the new one');
t_eq(count(ch_library_versions($code)), 2, 'both values are kept — the history shows where a figure came from');
$copy = ch_library_item($code, '2029-01-01');
t_eq([$copy['cap'], $copy['lib_from']], [15000.0, '2020-01-01'], 'a head added to a role is a copy of the value on that date');
t_ok(ch_library_save(['label' => 'X', 'type' => 'AMOUNT'], 'not-a-date', 'Probe')[0] === false, 'a value needs the date it takes effect from');
ch_library_set_active($code, false, 'Probe');
t_ok(!isset(ch_library('2031-01-01')[$code]), 'a retired head is no longer offered');
ch_library_set_active($code, true, 'Probe');

$tpls = ch_templates();
t_ok((bool) array_filter($tpls, fn($t) => stripos($t['name'], 'statutory') !== false), 'a starting template for a payroll role is offered');
$first = array_values(array_filter($tpls, fn($t) => stripos($t['name'], 'statutory') !== false))[0];
$tItems = ch_template_items((int) $first['id']);
$tItems[0]['amount'] = 15000; // Basic
$te = ch_evaluate($tItems, 'MONTH', 26);
t_ok($te['ok'] && (float) $te['rows']['PF_ER']['unit'] === 1800.0, 'the template works out on its own: Basic 15,000 → PF 1,800');
[$ok, $tid] = ch_template_save(0, 'Probe template ' . $tag, 'note', $role, 'Probe');
t_ok($ok && $tid > 0, 'a role\'s heads can be saved as a template');
t_ok(ch_template_save(0, 'Probe template ' . $tag, '', $role, 'Probe')[0] === false, 'two templates cannot share a name');
t_eq(count(ch_template_items($tid)), 10, 'the template keeps every head');

t_section('Costing heads — who may change the library');
t_ok(ch_can_manage(), 'an administrator may change the library (the Masters gate, docs/02 row 12)');
// A real signed-in inspector (t_as_nobody() would only put back whoever the
// previous test left signed in, which in the full suite is an administrator).
db()->prepare("INSERT INTO users (username, password_hash, first_name, role, is_active) VALUES (?, 'x', 'Probe', 'INSPECTOR', 1)")->execute([strtolower($tag) . '-ins']);
$insUid = (int) db()->lastInsertId();
$keepSess = $_SESSION; $_SESSION['uid'] = $insUid; current_user(true); ua(true);
t_ok(!ch_can_manage(), 'someone without administrator rights may not');
$_SESSION = $keepSess; current_user(true); ua(true);
db()->prepare("DELETE FROM users WHERE id=?")->execute([$insUid]);
