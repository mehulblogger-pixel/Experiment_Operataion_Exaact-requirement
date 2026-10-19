<?php
// PROJECT COSTING lifecycle and its link to quotations.
require __DIR__ . '/plib.php';
function pc($id) { return json_encode(ops_one("SELECT id,code,status,quote_id,approved_by,reject_note,created_by,exp_revenue FROM project_costings WHERE id=?", [$id])); }
function mkpc($who, $extra = []) {
    $b = lastid('project_costings');
    $r = R($who, 'project-costing-new', 'POST', [], array_merge(['title' => 'Deputation costing', 'client_id' => (string)$GLOBALS['CLIENT'], 'office_id' => (string)$GLOBALS['OFF'], 'basis' => 'LONG'], $extra));
    $id = lastid('project_costings'); if ($id <= $b) { show("$who project-costing-new", $r); return 0; }
    R($who, 'project-costing-line-save', 'POST', [], ['costing_id' => $id, 'role_label' => 'Inspector', 'boq_qty' => '12', 'target_margin' => '20', 'head' => ['PERSONNEL' => '60000']]);
    return $id;
}
function act($who, $route, $id, $x = []) { $r = R($who, $route, 'POST', [], array_merge(['id' => $id], $x)); show("$who $route", $r); echo "      " . pc($id) . "\n"; }

H('1. who can create / approve');
foreach (['MM','BDM','MEX','FIN','BM','BD'] as $w) { $id = mkpc($w); echo "    $w create -> " . ($id ? pc($id) : 'refused') . "\n"; }

H('2. transitions without a from-state guard');
$a = mkpc('MM');
act('MM2', 'project-costing-approve', $a);                 // DRAFT -> APPROVED, never submitted
act('MM', 'project-costing-reject', $a);                   // APPROVED -> REJECTED, no note
act('MM', 'project-costing-approve', $a);                  // creator approves own (has crm.quote.approve)
act('MM', 'project-costing-reopen', $a);                   // APPROVED -> DRAFT by the editor
show('edit a line on the reopened (formerly approved) sheet', R('MM', 'project-costing-line-save', 'POST', [], ['costing_id' => $a, 'role_label' => 'Inspector', 'boq_qty' => '12', 'target_margin' => '-50', 'head' => ['PERSONNEL' => '60000']]));
echo "      " . pc($a) . "\n";
act('BDM', 'project-costing-submit', $a); act('BDM', 'project-costing-approve', $a); act('BM', 'project-costing-approve', $a);
echo "      history rows for costing (activities / any table): activities=" . cnt("SELECT COUNT(*) FROM activities WHERE subject LIKE '%PC-%'") . "\n";

H('3. a quotation from a costing that is DRAFT / REJECTED; twice from the same costing');
$b = mkpc('MM');
$q0 = lastid('quotations');
show('to-quote from DRAFT costing', R('MM', 'project-costing-to-quote', 'POST', [], ['id' => $b])); $qa = lastid('quotations');
echo "      quote " . qline($qa) . " ; costing " . pc($b) . "\n";
act('MM2', 'project-costing-reject', $b, ['reject_note' => 'margin too thin']);
show('to-quote again from the REJECTED costing', R('MM', 'project-costing-to-quote', 'POST', [], ['id' => $b])); $qb = lastid('quotations');
echo "      quote " . qline($qb) . " ; costing now links quote_id=" . ops_val("SELECT quote_id FROM project_costings WHERE id=?", [$b]) . " (first quote #$qa is orphaned from it: pc_for_quote=" . (pc_for_quote($qa)['code'] ?? 'none') . ")\n";
show('first quote submitted & approved while its costing is REJECTED', R('MM', 'quote-status', 'POST', ['id' => $qa], ['to' => 'PENDING_APPROVAL']));
$st = (int)ops_val("SELECT id FROM quote_approvals WHERE quote_id=? AND status='PENDING'", [$qa]);
show('MM2 approves', R('MM2', 'quote-approve', 'POST', [], ['step' => $st, 'decision' => 'approve'])); echo "      " . qline($qa) . "\n";

H('4. one costing attached to two quotations; two costings attached to one quotation');
$c = mkpc('MM'); [$qx] = new_quote('MM'); [$qy] = new_quote('MM');
R('MM', 'project-costing-attach', 'POST', [], ['costing_id' => $c, 'quote_id' => $qx]);
R('MM', 'project-costing-attach', 'POST', [], ['costing_id' => $c, 'quote_id' => $qy]);
echo "      after attaching to X then Y: pc_for_quote(X)=" . (pc_for_quote($qx)['code'] ?? 'none') . " pc_for_quote(Y)=" . (pc_for_quote($qy)['code'] ?? 'none') . "\n";
$d = mkpc('MM'); $e = mkpc('MM');
R('MM', 'project-costing-attach', 'POST', [], ['costing_id' => $d, 'quote_id' => $qx]);
R('MM', 'project-costing-attach', 'POST', [], ['costing_id' => $e, 'quote_id' => $qx]);
echo "      two costings on quote X: rows=" . cnt("SELECT COUNT(*) FROM project_costings WHERE quote_id=?", [$qx]) . " ; the quote screen shows only " . (pc_for_quote($qx)['code'] ?? 'none') . "\n";
show('attach to a quote that does not exist (quote_id=99999)', R('MM', 'project-costing-attach', 'POST', [], ['costing_id' => $e, 'quote_id' => 99999])); echo "      " . pc($e) . "\n";
show('edit costing header while it is linked to an ACCEPTED quote', (function () use ($d, $qx) { setq($qx, ['status' => 'ACCEPTED']); return R('MM', 'project-costing-save', 'POST', [], ['id' => $d, 'title' => 'changed after award', 'overhead_pct' => '0']); })());
echo "      " . pc($d) . "\n";
