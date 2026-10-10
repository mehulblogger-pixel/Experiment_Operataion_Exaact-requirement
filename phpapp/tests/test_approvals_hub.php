<?php
// ============================================================================
//  ONE ANSWER TO "WHAT NEEDS ME?"                                      (R-27)
// ============================================================================
//  There were two inboxes — /approvals for stage gates, /my-approvals for
//  recruitment chains — so a manager had to know which KIND of thing they were
//  approving before they knew where to look. The structure audit counted twelve
//  approval routes across four subsystems and ten files.
//
//  The two systems are NOT merged, and that is deliberate: they have different
//  records, different rules and different audiences. Recruitment chains gate on
//  the LICENCE rather than a permission, so a Finance approver can sit on an
//  offer chain while holding no recruitment module at all. Forcing one
//  permission model on both would quietly remove approvers.
//
//  What is asserted here is the thing that changed: one front door, and nobody
//  seeing a queue they could not see before.
// ============================================================================

t_section('The single inbox is reachable by EITHER audience');

//  /approvals used to require gate_can_view() alone. That refused every
//  recruitment approver who held no stage-gate rights, which is most of them —
//  so making this screen the one front door meant relaxing the guard to "either
//  queue", not to "anyone".
$src = file_get_contents(__DIR__ . '/../lib/stagegate.php');
t_ok(strpos($src, 'approvals_hub_can_view()') !== false,
     '*** ops_approvals() asks whether EITHER queue is visible, not just the gate one');
t_ok(strpos($src, "ops_require(gate_can_view(), 'You cannot open the approvals queue.')") === false,
     '*** the old gate-only guard is gone');

t_section('Each queue still decides its own visibility');

//  The whole safety of merging the front door rests on this: the sections are
//  built from the SAME questions each screen already asked.
$hub = file_get_contents(__DIR__ . '/../lib/approvals_hub.php');
t_ok(strpos($hub, "licence_blocks('mod.hiring.view')") !== false,
     '*** recruitment still asks the licence question ops_my_approvals asks');
t_ok(strpos($hub, 'gate_can_view()') !== false,
     '*** stage gates still ask gate_can_view()');
t_ok(strpos($hub, 'appr_inbox()') !== false && strpos($hub, "gate_queue('PENDING')") !== false,
     '*** and the rows come from the existing queues, not from a second query');

t_section('An empty queue is left out, not shown empty');

//  Journey H6 found a Finance user opening a recruitment approvals screen and
//  wondering why they had one. An empty section on a screen whose job is to say
//  "here is what needs you" is noise.
t_ok(strpos($hub, 'if ($items) $out[]') !== false,
     '*** the recruitment section appears only when it holds something');
t_ok(strpos($hub, "if (!empty(\$q['act'])) \$out[]") !== false,
     '*** so does the stage-gate section');

t_section('Both kinds of row render the same way');

//  Recruitment steps and stage gates share no column names at all, so the
//  screen needs one shape. These are the real column names from appr_inbox()
//  and gate_queue().
$r = approvals_hub_row('recruit', [
    'subject' => 'Offer — Senior Inspector', 'entity' => 'OFFER',
    'requester' => 'R Nair', 'sla_due' => '2026-10-14', 'rule_name' => 'Offers over 12L',
]);
t_eq($r['what'], 'Offer — Senior Inspector', '*** a recruitment row shows what is being decided');
t_eq($r['who'],  'R Nair',                   '*** and who raised it');
t_eq($r['url'],  '/my-approvals',            '*** and sends you where that decision is actually made');

$g = approvals_hub_row('gates', [
    'deal_name' => 'Pipeline inspection — BPCL', 'from_name' => 'Qualified',
    'to_name' => 'Negotiation', 'partner_name' => 'BPCL', 'requested_at' => '2026-10-09',
]);
t_eq($g['what'], 'Pipeline inspection — BPCL', '*** a stage-gate row shows the deal');
t_eq($g['kind'], 'Qualified → Negotiation',    '*** and the move it is held at');
t_eq($g['url'],  '/approvals',                 '*** and acts in place');

//  A row with nothing in it must not crash the screen — these come from live
//  tables where a column can be null.
$empty = approvals_hub_row('recruit', []);
t_ok($empty['what'] !== '' && isset($empty['url']),
     '*** a row with every field missing still renders something sensible');

t_section('The view route is guarded by its handler, the action by its own check');

//  /approvals is no longer mapped to a module: its audience spans modules, and
//  one module gate cannot express "either of two queues". That is only safe
//  because the handler guards viewing and gate_act() guards deciding — the same
//  pattern as /client-holds. If either guard goes, this fails.
t_eq(nav_module_of('approvals'), null,
     '*** /approvals is deliberately not module-gated (its audience spans modules)');
t_eq(nav_module_of('approval-act'), 'leads',
     '*** but the POST that decides a deal is still gated on the deals module');
t_ok(strpos($src, 'gate_can_act($req)') !== false,
     '*** and gate_act() still refuses anybody who may not act on that request');
