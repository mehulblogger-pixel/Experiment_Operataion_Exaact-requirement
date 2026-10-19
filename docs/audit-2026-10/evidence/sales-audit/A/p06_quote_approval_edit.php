<?php
// QUOTATION approval chain vs edits; segregation; optimistic locking.
require __DIR__ . '/plib.php';
$U = $GLOBALS['U'];
function rule($name, $level, $min, $max, $role = '', $uid = null) {
    db()->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?,'ANY','',?,?,?,?,?,1,?)")
        ->execute([$name, $min, $max, $level, $role, $uid, date('c')]);
    return lastid('quote_approval_rules');
}
function steps($id) { return json_encode(array_map(fn($s) => "L{$s['level']}:" . ($s['approver_user_id'] ? 'user' . $s['approver_user_id'] : ($s['approver_role'] ?: 'any')) . ":{$s['status']}", ops_all("SELECT * FROM quote_approvals WHERE quote_id=? ORDER BY level,id", [$id]))); }
function step($id, $level) { return (int)ops_val("SELECT id FROM quote_approvals WHERE quote_id=? AND level=? ORDER BY id LIMIT 1", [$id, $level]); }
function mv($who, $id, $to, $x = []) { $r = R($who, 'quote-status', 'POST', ['id' => $id], array_merge(['to' => $to], $x)); show("$who -> $to", $r); echo "      " . qline($id) . " steps=" . steps($id) . "\n"; }
function edit($who, $id, $rate) { $r = R($who, 'quote-edit', 'POST', ['id' => $id], quote_post(['l_rate' => [(string)$rate]])); show("$who quote-edit rate=$rate", $r); echo "      " . qline($id) . " steps=" . steps($id) . "\n"; }
function appr($who, $sid, $dec = 'approve', $rem = '') { $r = R($who, 'quote-approve', 'POST', [], ['step' => $sid, 'decision' => $dec, 'remarks' => $rem]); show("$who $dec step $sid", $r); }

$r1 = rule('Any amount: MM2 signs', 1, 0, 0, '', $U['MM2']);
$r2 = rule('Over 1 lakh: branch manager too', 2, 100000, 0, 'BRANCH_MANAGER');
echo "rules: L1 user MM2 (any amount), L2 role BRANCH_MANAGER (>= 1,00,000)\n";

H('A. rules configured, sender sends a DRAFT directly');
[$a] = new_quote('MM'); mv('MM', $a, 'SENT');
H('A2. rules configured, approver uses direct APPROVED (routed into chain) / master override');
[$a2] = new_quote('MM'); mv('MM', $a2, 'APPROVED');
[$a3] = new_quote('MM'); mv('ADMIN', $a3, 'APPROVED'); echo "      log: " . json_encode(array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=?", [$a3]), 'summary')) . "\n";

H('B. price raised 10x WHILE pending approval (chain was built for the old amount)');
[$b] = new_quote('MM'); mv('MM', $b, 'PENDING_APPROVAL');
edit('MM', $b, 50000);
appr('MM2', step($b, 1));
echo "      FINAL: " . qline($b) . " steps=" . steps($b) . " — needs BM for >=1,00,000? " . (crm_quote_needs_chain(q($b)) ? 'rule matches' : '-') . "\n";

H('C. price changed AFTER approval, before sending');
[$c] = new_quote('MM'); mv('MM', $c, 'PENDING_APPROVAL'); appr('MM2', step($c, 1));
echo "      approved at " . q($c)['total_amount'] . "\n";
edit('MM', $c, 900000);
mv('MM', $c, 'SENT');
echo "      log: " . json_encode(array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=? ORDER BY id", [$c]), 'summary')) . "\n";

H('D. level 1 REJECTS, level 2 then approves the remaining step');
[$d] = new_quote('MM', ['l_rate' => ['50000']]); mv('MM', $d, 'PENDING_APPROVAL');
appr('MM2', step($d, 1), 'reject', 'Rate too low');
echo "      " . qline($d) . " steps=" . steps($d) . "\n";
appr('BM', step($d, 2));
echo "      FINAL: " . qline($d) . " steps=" . steps($d) . "\n";

H('E. re-submit after rejection: what happens to the rejected step record');
[$e] = new_quote('MM'); mv('MM', $e, 'PENDING_APPROVAL'); appr('MM2', step($e, 1), 'reject', 'Wrong scope');
$before = ops_all("SELECT id, level, status, acted_by, remarks FROM quote_approvals WHERE quote_id=?", [$e]);
mv('MM', $e, 'PENDING_APPROVAL');
echo "      before: " . json_encode($before) . "\n      after : " . json_encode(ops_all("SELECT id, level, status, acted_by, remarks FROM quote_approvals WHERE quote_id=?", [$e])) . "\n";
echo "      rejection still in quote history? " . json_encode(array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=?", [$e]), 'summary')) . "\n";

H('F. self-approval through the chain: generic step (no rule matches), creator approves own quote');
db()->exec("UPDATE quote_approval_rules SET active=0");
[$f] = new_quote('MM'); mv('MM', $f, 'PENDING_APPROVAL'); appr('MM', step($f, 1));
echo "      " . qline($f) . " created_by=" . ops_val("SELECT created_by FROM quotations WHERE id=?", [$f]) . " approved_by=" . ops_val("SELECT acted_by FROM quote_approvals WHERE quote_id=?", [$f]) . "\n";
db()->prepare("INSERT INTO quote_approval_rules (name,match_type,min_amount,max_amount,level,approver_role,active,created_at) VALUES ('MM role',  'ANY', 0,0,1,'MARKETING_MANAGER',1,?)")->execute([date('c')]);
[$f2] = new_quote('MM'); mv('MM', $f2, 'PENDING_APPROVAL'); appr('MM', step($f2, 1));
echo "      role-routed: " . qline($f2) . "\n";
db()->exec("UPDATE quote_approval_rules SET active=0");

H('G. edit after SENT / after ACCEPTED');
[$g] = new_quote('MM'); setq($g, ['status' => 'SENT', 'sent_at' => date('c')]); edit('MM', $g, 1); edit('ADMIN', $g, 1);
[$g2] = new_quote('MM'); mv('MM', $g2, 'ACCEPTED'); edit('MM', $g2, 1); edit('ADMIN', $g2, 1);
[$g3] = new_quote('MM'); mv('MM', $g3, 'REJECTED'); edit('MM', $g3, 7);
[$g4] = new_quote('MM'); setq($g4, ['status' => 'EXPIRED', 'sent_at' => '']); edit('MM', $g4, 3);

H('H. two people edit the same DRAFT from the same starting copy (no version check)');
[$h] = new_quote('MM'); $v0 = q($h)['total_amount'];
edit('MM', $h, 6000); edit('BDM', $h, 4000);
echo "      start $v0 ; both saves accepted, last one wins: " . q($h)['total_amount'] . "\n";
echo "      version/updated_at check on quotations? stale_edit_block allow-list = calls, jobs only\n";
