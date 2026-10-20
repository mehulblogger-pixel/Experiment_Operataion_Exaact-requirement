<?php
// Set up Sales edits the quotation approval levels in place (owner, SAL-002:
// "in Sales setup we must have option to set the applicable rules from that
// screen directly"), says where every other Sales approval is set, and a level
// for ONE business unit finally applies to that unit only — the form posted
// 'SBU' while the save and the matcher compared against 'Business Unit', so
// every such level was stored as "any" and applied to every quotation.
t_section('Set up Sales: approval levels in place; business-unit levels work');

$pdo = db();
if (function_exists('crm_migrate')) crm_migrate();

// --- the business-unit check reads every spelling a row may carry ------------
t_ok(crm_rule_is_unit(['match_type' => 'SBU', 'sbu' => 'IND']), "'SBU' with a unit is unit-only");
t_ok(crm_rule_is_unit(['match_type' => 'Business Unit', 'sbu' => 'IND']), "an older 'Business Unit' row is unit-only");
t_ok(!crm_rule_is_unit(['match_type' => 'SBU', 'sbu' => '']), 'no unit named = any unit');
t_ok(!crm_rule_is_unit(['match_type' => 'ANY', 'sbu' => 'IND']), "'ANY' = any unit");

// --- the matcher honours it --------------------------------------------------
$saved = ops_all("SELECT * FROM quote_approval_rules") ?: [];
$pdo->exec("DELETE FROM quote_approval_rules");
$ins = $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
$ins->execute(['Any unit', 'ANY', '', 0, 0, 1, 'BRANCH_MANAGER', null, 1, date('c')]);
$ins->execute(['IND only', 'SBU', 'IND', 0, 0, 2, 'SBU_HEAD', null, 1, date('c')]);
$names = fn($q) => array_map(fn($r) => $r['name'], crm_quote_matching_rules($q));
t_ok($names(['subtotal' => 100000, 'sbu' => 'IND']) === ['Any unit', 'IND only'], 'an IND quotation meets both levels');
t_ok($names(['subtotal' => 100000, 'sbu' => 'OGC']) === ['Any unit'], 'an OGC quotation does not meet the IND-only level');

// --- the screen --------------------------------------------------------------
$set = setupqa_sales_set();
$titles = array_column($set['sections'], 'title');
t_ok(in_array('quote_levels', array_column($set['sections'], 'editor'), true), 'the quotation section carries the in-place level editor');
t_ok((bool) array_filter($titles, fn($t) => stripos($t, 'deal') !== false), 'deal-stage approvals have their own section');
t_ok(in_array('Other approvals in Sales', $titles, true), 'every other Sales approval is stated, with who gives it');
$other = implode(' ', setupqa_sales_other_approvals());
foreach (['Leads', 'Project costing', 'contract'] as $w) t_ok(stripos($other, $w) !== false, "the other approvals mention $w");
$unitName = lk_options_or('sbu', OPS_SBUS)['IND'] ?? 'IND';
t_ok(strpos(implode(' ', setupqa_sales_level_facts()), '(' . $unitName . ' only)') !== false, 'the sentence summary names the unit a unit-only level is for');
$view = file_get_contents(__DIR__ . '/../views/ops/crm/approval_levels_inline.php');
t_ok(strpos($view, 'back=sales-setup') !== false && strpos($view, 'form="') !== false, 'level cards post their own forms and come back to Set up Sales');
$crm = file_get_contents(__DIR__ . '/../lib/crm.php');
t_ok(strpos($crm, "=== 'sales-setup') ? '/sales-setup#levels'") !== false, 'back= is honoured for Set up Sales only — never a free redirect');
t_ok(strpos($crm, "must be above the \"from\" value") !== false, 'a band whose "up to" is not above "from" is refused');
$sg = file_get_contents(__DIR__ . '/../views/ops/stage_gates.php');
t_ok(strpos($sg, '<select class="form-control" name="entity_kind">') === false && strpos($sg, 'Deals (opportunities)') !== false,
    'deal-stage rules no longer show a one-choice "Applies to" drop-down');

$pdo->exec("DELETE FROM quote_approval_rules");
foreach ($saved as $s) { $cols = array_keys($s); $pdo->prepare('INSERT INTO quote_approval_rules (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($s)); }
