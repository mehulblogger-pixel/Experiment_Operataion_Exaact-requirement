<?php
// P6 — the pre-order review checklist: what enabled / require_all actually gate, and where.
putenv('OPS_MAIL_ENABLED');
require __DIR__ . '/harness.php';
$mm  = mkuser('mm1', 'MARKETING_MANAGER');
$bdm = mkuser('bdm1', 'BUSINESS_DEV_MANAGER');
$co  = mkuser('co1', 'COORDINATOR');
function st($q) { $r = qrow($q); return $r['status'] . ($r['sent_at'] ? ' (sent_at set)' : ''); }
$items = "Enquiry reviewed\nClient not blocked\nCommercials within delegation";
$r = route($mm, 'preorder-checklist', 'POST', [], ['enabled' => '1', 'require_all' => '1', 'items' => $items]);
show('MM (an approver) configures the checklist: enabled + require_all', flashes($r));
show('  settings now', [setting_get('preorder_checklist_on'), setting_get('preorder_checklist_require'), preorder_checklist_items()]);
// parent cache refresh
foreach (['preorder_checklist_on', 'preorder_checklist_require', 'preorder_checklist_items'] as $k) $GLOBALS['__x'] = 1;
setting_set('preorder_checklist_on', '1'); setting_set('preorder_checklist_require', '1'); setting_set('preorder_checklist_items', $items);

echo "\n== A. chain approval with an unticked item ==\n";
db()->exec("DELETE FROM quote_approval_rules"); rule('L1 MM', 0, 0, 1, 'MARKETING_MANAGER');
$q = new_quote($bdm, [['Work', '1', '50000']]); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
$r = approve_step($mm, $s[0]['id']); show('MM approves with 0/3 ticked', flashes($r) . ' -> ' . st($q));
$r = approve_step($mm, $s[0]['id'], 'reject', 'incomplete review'); show('MM rejects with 0/3 ticked', flashes($r) . ' -> ' . st($q));
status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
$keys = array_map('preorder_item_key', preorder_checklist_items());
$r = route($bdm, 'quote-preorder-save', 'POST', [], ['id' => $q, 'pc' => [$keys[0] => '1', $keys[1] => '1']]);
show('BDM (the quote author) ticks 2/3', flashes($r) . ' progress=' . json_encode(preorder_checklist_progress(crm_quote_get($q))));
$r = approve_step($mm, $s[0]['id']); show('MM approves with 2/3', flashes($r) . ' -> ' . st($q));
$r = route($bdm, 'quote-preorder-save', 'POST', [], ['id' => $q, 'pc' => array_fill_keys($keys, '1')]);
show('BDM ticks 3/3 himself', flashes($r));
show('  what is stored about the ticks (who/when?)', ops_val("SELECT preorder_checklist FROM quotations WHERE id=?", [$q]));
$r = approve_step($mm, $s[0]['id']); show('MM approves with 3/3', flashes($r) . ' -> ' . st($q));

echo "\n== B. paths the checklist does not gate ==\n";
db()->exec("DELETE FROM quote_approval_rules");
$q = new_quote($bdm, [['Work', '1', '50000']]);
$r = status($mm, $q, 'APPROVED'); show('no rule matches: MM approves directly with 0/3 ticked', flashes($r) . ' -> ' . st($q));
$q = new_quote($bdm, [['Work', '1', '50000']]);
$r = status($bdm, $q, 'SENT'); show('BDM sends a DRAFT with 0/3 ticked', flashes($r) . ' -> ' . st($q));
$q = new_quote($bdm, [['Work', '1', '50000']]);
$r = status($bdm, $q, 'ACCEPTED'); show('BDM marks a DRAFT ACCEPTED with 0/3 ticked', flashes($r) . ' -> ' . st($q));

echo "\n== C. editing the checklist text after ticks ==\n";
db()->exec("DELETE FROM quote_approval_rules"); rule('L1 MM', 0, 0, 1, 'MARKETING_MANAGER');
$q = new_quote($bdm, [['Work', '1', '50000']]); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
route($bdm, 'quote-preorder-save', 'POST', [], ['id' => $q, 'pc' => array_fill_keys($keys, '1')]);
show('3/3 ticked before the wording change', json_encode(preorder_checklist_progress(crm_quote_get($q))));
setting_set('preorder_checklist_items', "Enquiry reviewed.\nClient not blocked\nCommercials within delegation");
show('after adding a full stop to item 1', json_encode(preorder_checklist_progress(crm_quote_get($q))));

echo "\n== D. revision carries the ticks ==\n";
setting_set('preorder_checklist_items', $items);
$q = new_quote($bdm, [['Work', '1', '50000']]);
route($bdm, 'quote-preorder-save', 'POST', [], ['id' => $q, 'pc' => array_fill_keys($keys, '1')]);
status($bdm, $q, 'SENT');
$r = route($bdm, 'quote-revise', 'POST', ['id' => $q], ['summary' => 'price change']);
$nid = (int) ops_val("SELECT MAX(id) FROM quotations");
show('new revision status / checklist column', ops_one("SELECT rev,status,preorder_checklist FROM quotations WHERE id=?", [$nid]));

echo "\n== E. empty item list with require_all on ==\n";
setting_set('preorder_checklist_items', '');
$q = new_quote($bdm, [['Work', '1', '50000']]); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
$r = approve_step($mm, $s[0]['id']); show('enabled+require_all but no items saved: MM approves', flashes($r) . ' -> ' . st($q));

echo "\n== F. who can switch the control off ==\n";
$r = route($bdm, 'preorder-checklist', 'POST', [], ['enabled' => '', 'require_all' => '', 'items' => $items]);
show('BDM tries to switch it off', flashes($r));
$r = route($co, 'preorder-checklist', 'POST', [], ['enabled' => '', 'require_all' => '', 'items' => $items]);
show('COORDINATOR tries to switch it off', flashes($r) . ' / out: ' . trim(substr($r['out'], 0, 120)));
$r = route($mm, 'preorder-checklist', 'POST', [], ['enabled' => '', 'require_all' => '', 'items' => $items]);
show('MM (the approver it gates) switches it off', flashes($r) . ' on=' . ops_val("SELECT svalue FROM settings WHERE skey='preorder_checklist_on'"));
