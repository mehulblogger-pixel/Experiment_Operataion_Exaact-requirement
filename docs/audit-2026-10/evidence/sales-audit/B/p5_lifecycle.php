<?php
// P5 — quote lifecycle bypasses, segregation, sequencing, edits after approval, reject/resubmit,
// retract, approver availability, delegation, inbox. All through ops_dispatch() as real users.
putenv('OPS_MAIL_ENABLED');
require __DIR__ . '/harness.php';
$admin = (int) ops_val("SELECT id FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1");
$mm  = mkuser('mm1', 'MARKETING_MANAGER');
$bd  = mkuser('bd1', 'BUSINESS_DIRECTOR');
$bdm = mkuser('bdm1', 'BUSINESS_DEV_MANAGER');
$fin = mkuser('fin1', 'FINANCE');
$bm  = mkuser('bm1', 'BRANCH_MANAGER');
$co  = mkuser('co1', 'COORDINATOR');
$gone = mkuser('gone1', 'MARKETING_MANAGER'); db()->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$gone]);
function reset_rules() { db()->exec("DELETE FROM quote_approval_rules"); }
function st($q) { $r = qrow($q); return $r['status'] . ($r['sent_at'] ? ' (sent_at set)' : ''); }
function stp($q) { return implode(', ', array_map(fn($s) => 'L' . $s['level'] . ':' . ($s['approver_role'] ?: ($s['approver_user_id'] ? 'user#' . $s['approver_user_id'] : 'ANY')) . '=' . $s['status'] . ($s['acted_by'] ? '(' . $s['acted_by'] . ')' : ''), steps($q))); }
show('can BD approve (role default)?', in_array('crm.quote.approve', role_perms('BUSINESS_DIRECTOR'), true) ? 'yes' : 'no');

echo "\n== A. SENT / ACCEPTED without approval ==\n";
reset_rules(); rule('L1 MM', 0, 0, 1, 'MARKETING_MANAGER');
$q = new_quote($bdm, [['Work', '1', '100000']]);
$r = status($bdm, $q, 'SENT'); show('BDM sends a DRAFT quote (rule requires MM approval)', flashes($r) . ' -> ' . st($q) . ' steps=' . count(steps($q)));
$q = new_quote($bdm, [['Work', '1', '100000']]); status($bdm, $q, 'PENDING_APPROVAL');
$r = status($bdm, $q, 'SENT'); show('BDM sends a PENDING_APPROVAL quote', flashes($r) . ' -> ' . st($q) . ' | ' . stp($q));
$s = steps($q); $r = approve_step($mm, $s[0]['id']); show('  then MM approves its still-pending step', flashes($r) . ' -> ' . st($q));
$q = new_quote($bdm, [['Work', '1', '100000']]); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
approve_step($mm, $s[0]['id'], 'reject', 'price too low');
$r = status($bdm, $q, 'SENT'); show('BDM sends a REJECTED quote', flashes($r) . ' -> ' . st($q));
$q = new_quote($bdm, [['Work', '1', '100000']]);
$r = status($fin, $q, 'ACCEPTED'); show('FINANCE (quotes: view only) marks a DRAFT quote ACCEPTED', flashes($r) . ' -> ' . st($q));
$q = new_quote($bdm, [['Work', '1', '100000']]);
$r = status($bdm, $q, 'ACCEPTED'); show('BDM marks own DRAFT quote ACCEPTED (never approved, never sent)', flashes($r) . ' -> ' . st($q));
$q = new_quote($bdm, [['Work', '1', '100000']]); status($bdm, $q, 'PENDING_APPROVAL');
foreach (['DRAFT', 'REJECTED', 'EXPIRED', 'LOST'] as $to) {
    $r = status($bm, $q, $to); show("BRANCH_MANAGER (view only) moves PENDING quote to $to", flashes($r) . ' -> ' . st($q));
    db()->prepare("UPDATE quotations SET status='PENDING_APPROVAL' WHERE id=?")->execute([$q]);
}
$r = status($co, $q, 'ACCEPTED'); show('COORDINATOR (no quotes module) tries ACCEPTED', flashes($r) . ' -> ' . st($q));

echo "\n== B. a quote moved back to DRAFT keeps live approval steps ==\n";
$q = new_quote($bdm, [['Work', '1', '100000']]); status($bdm, $q, 'PENDING_APPROVAL');
status($bm, $q, 'DRAFT');
$s = steps($q); $r = approve_step($mm, $s[0]['id']); show('MM approves a step of a quote that is now DRAFT', flashes($r) . ' -> ' . st($q));

echo "\n== C. self-approval ==\n";
reset_rules(); rule('L1 MM', 0, 0, 1, 'MARKETING_MANAGER');
show('settings appr_self_approval / master_exception', [setting_get('appr_self_approval'), setting_get('appr_self_master_exception')]);
$q = new_quote($mm, [['Work', '1', '100000']]); status($mm, $q, 'PENDING_APPROVAL'); $s = steps($q);
$r = approve_step($mm, $s[0]['id']); show('MM creates, submits and approves own quote (chain)', flashes($r) . ' -> ' . st($q) . ' owner=' . qrow($q)['owner_id'] . " mm=$mm");
reset_rules();
$q = new_quote($mm, [['Work', '1', '100000']]);
$r = status($mm, $q, 'APPROVED'); show('MM creates and directly approves own quote (no rules)', flashes($r) . ' -> ' . st($q));
$r = status($mm, $q, 'SENT'); show('  ...and sends it', flashes($r) . ' -> ' . st($q));
setting_set('appr_self_approval', '0'); setting_set('appr_self_master_exception', '0');
reset_rules(); rule('L1 MM', 0, 0, 1, 'MARKETING_MANAGER');
$q = new_quote($mm, [['Work', '1', '100000']]); status($mm, $q, 'PENDING_APPROVAL'); $s = steps($q);
$r = approve_step($mm, $s[0]['id']); show('same, with org self-approval OFF and master exception OFF', flashes($r) . ' -> ' . st($q));
setting_set('appr_self_master_exception', '1');

echo "\n== D. sequencing of levels ==\n";
reset_rules(); rule('L1 MM', 0, 0, 1, 'MARKETING_MANAGER'); rule('L2 BD', 0, 0, 2, 'BUSINESS_DIRECTOR');
$q = new_quote($bdm, [['Work', '1', '100000']]); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
$r = approve_step($bd, $s[1]['id']); show('BD approves LEVEL 2 while level 1 is still pending', flashes($r) . ' -> ' . st($q) . ' | ' . stp($q));
$r = approve_step($mm, $s[0]['id']); show('  then MM approves level 1', flashes($r) . ' -> ' . st($q) . ' | ' . stp($q));

echo "\n== E. approver availability ==\n";
reset_rules(); rule('L1 named inactive user', 0, 0, 1, '', $gone);
$q = new_quote($bdm, [['Work', '1', '100000']]); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
show('step routed to an INACTIVE named user; pending with', crm_pending_with($q));
$r = approve_step($mm, $s[0]['id']); show('  another MM (has crm.quote.approve) tries', flashes($r) . ' -> ' . st($q));
$r = approve_step($bd, $s[0]['id']); show('  BD tries', flashes($r) . ' -> ' . st($q));
reset_rules(); rule('L1 COORDINATOR', 0, 0, 1, 'COORDINATOR');
$q = new_quote($bdm, [['Work', '1', '100000']]); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
$r = approve_step($co, $s[0]['id']); show('step routed to COORDINATOR role (no quotes module); coordinator approves', flashes($r) . ' -> ' . st($q));
reset_rules(); rule('L1 SBU_HEAD', 0, 0, 1, 'SBU_HEAD');
$q = new_quote($bdm, [['Work', '1', '100000']]); status($bdm, $q, 'PENDING_APPROVAL');
show('step routed to a role nobody holds (SBU_HEAD); pending with', crm_pending_with($q));
reset_rules(); rule('L1 FINANCE', 0, 0, 1, 'FINANCE');
$q = new_quote($bdm, [['Work', '1', '100000']]); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
$r = approve_step($fin, $s[0]['id']); show('step routed to FINANCE role (view only on quotes); finance approves', flashes($r) . ' -> ' . st($q));

echo "\n== F. edits while pending / after approval ==\n";
reset_rules(); rule('L1 MM <=1L', 0, 100000, 1, 'MARKETING_MANAGER'); rule('L2 BD >1L', 100000.01, 0, 2, 'BUSINESS_DIRECTOR');
function edit_rate($uid, $q, $rate) {
    return route($uid, 'quote-edit', 'POST', ['id' => $q], ['office_id' => (string) probe_office(), 'client_name' => 'Probe Client Pvt Ltd', 'subject' => 'Probe', 'gst_pct' => '0',
        'validity_days' => '30', 'currency' => 'INR', 'advance_pct' => '0', 'l_desc' => ['Work'], 'l_qty' => ['1'], 'l_rate' => [(string) $rate],
        'l_unit' => ['MANDAY'], 'l_service' => [''], 'l_sbu' => [''], 'l_order' => ['LINE'], 'l_location' => ['']]);
}
$q = new_quote($bdm, [['Work', '1', '50000']], ['gst_pct' => '0']); status($bdm, $q, 'PENDING_APPROVAL');
$r = edit_rate($bdm, $q, 5000000); show('BDM edits a PENDING quote from 50,000 to 50,00,000', flashes($r) . ' -> ' . st($q) . ' total=' . qrow($q)['total_amount'] . ' | ' . stp($q));
$s = steps($q); $r = approve_step($mm, $s[0]['id']); show('  MM (band <=1L) approves -> ', flashes($r) . ' -> ' . st($q) . ' total=' . qrow($q)['total_amount']);
$q = new_quote($bdm, [['Work', '1', '50000']], ['gst_pct' => '0']); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q); approve_step($mm, $s[0]['id']);
$r = edit_rate($bdm, $q, 1); show('BDM edits an APPROVED quote from 50,000 to 1 rupee', flashes($r) . ' -> ' . st($q) . ' total=' . qrow($q)['total_amount'] . ' | ' . stp($q));
$r = status($bdm, $q, 'SENT'); show('  BDM sends it', flashes($r) . ' -> ' . st($q));
$log = ops_all("SELECT summary FROM quote_revisions WHERE quote_id=? ORDER BY id", [$q]);
show('  change log rows for that quote (crm_log_change)', array_column($log, 'summary'));

echo "\n== G. reject, resubmit, retract ==\n";
$q = new_quote($bdm, [['Work', '1', '50000']], ['gst_pct' => '0']); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
$r = approve_step($mm, $s[0]['id'], 'reject', ''); show('MM rejects with no comment', flashes($r) . ' -> ' . st($q));
$r = approve_step($mm, $s[0]['id'], 'reject', 'too cheap'); show('MM rejects with a comment', flashes($r) . ' -> ' . st($q));
$r = status($bdm, $q, 'PENDING_APPROVAL'); show('BDM resubmits unchanged', flashes($r) . ' -> ' . st($q) . ' | ' . stp($q));
$s = steps($q); approve_step($mm, $s[0]['id']);
$r = route($bdm, 'quote-unapprove', 'POST', ['id' => $q], ['reason' => 'x']); show('BDM tries to take back MM\'s approval', flashes($r) . ' -> ' . st($q));
$r = route($mm, 'quote-unapprove', 'POST', ['id' => $q], ['reason' => 'pressed wrong button']); show('MM takes back own approval', flashes($r) . ' -> ' . st($q) . ' | ' . stp($q));
$r = status($bdm, $q, 'DRAFT'); show('BDM withdraws a PENDING quote to DRAFT (generic status path)', flashes($r) . ' -> ' . st($q) . ' | ' . stp($q));

echo "\n== H. master bypass ==\n";
$q = new_quote($bdm, [['Work', '1', '50000']], ['gst_pct' => '0']);
$r = status($admin, $q, 'APPROVED'); show('master approves directly although a rule matches', flashes($r) . ' -> ' . st($q));
show('  logged?', array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=? ORDER BY id", [$q]), 'summary'));

echo "\n== I. delegation and the inbox ==\n";
reset_rules(); rule('L1 named MM', 0, 0, 1, '', $mm);
$q = new_quote($bdm, [['Work', '1', '50000']], ['gst_pct' => '0']); status($bdm, $q, 'PENDING_APPROVAL'); $s = steps($q);
as_user($admin);
show('APPR_ENTITIES (what delegation may name)', array_keys(APPR_ENTITIES));
show('appr_delegation_save entity=QUOTE', appr_delegation_save(0, ['delegator_user_id' => $mm, 'delegate_user_id' => $bd, 'entity' => 'QUOTE', 'reason' => 'leave']));
show('appr_delegation_save entity="" (everything)', appr_delegation_save(0, ['delegator_user_id' => $mm, 'delegate_user_id' => $bdm, 'entity' => '', 'reason' => 'leave']));
$r = approve_step($bdm, $s[0]['id']); show('  delegate BDM acts on a step named to MM', flashes($r) . ' -> ' . st($q));
as_user($mm);
show('MM: crm_quotes_awaiting_me()', crm_quotes_awaiting_me());
show('MM: approvals_hub_sections() keys (the /approvals inbox)', array_column(approvals_hub_sections(), 'key'));
