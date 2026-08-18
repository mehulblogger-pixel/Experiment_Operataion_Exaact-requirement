<?php
// Pre-order review checklist (part of B): configurable, on/off, and — when set to
// require all — a gate before a quotation can be approved. Guards the helpers and
// the progress/complete calculation the approval gate uses.
t_section('pre-order review checklist');

// Off by default.
setting_set('preorder_checklist_on', ''); setting_set('preorder_checklist_items', '');
t_ok(preorder_checklist_enabled() === false, 'off by default');
t_ok(preorder_checklist_items() === [], 'no items by default');
t_ok(count(preg_split('/\n/', preorder_checklist_default())) >= 4, 'a starter list is offered');

// Configure three points, switch on.
setting_set('preorder_checklist_on', '1');
setting_set('preorder_checklist_items', "Scope clear\nClient not blocked\nCommercials checked");
t_ok(preorder_checklist_enabled(), 'can be switched on');
$items = preorder_checklist_items();
t_eq(count($items), 3, 'three points parse');

// Progress off a quotation row's stored ticks.
$k0 = preorder_item_key($items[0]); $k1 = preorder_item_key($items[1]);
$q = ['preorder_checklist' => json_encode([$k0 => 1])];
$p = preorder_checklist_progress($q);
t_eq($p['done'], 1, 'one of three ticked');
t_eq($p['total'], 3, 'total is three');
t_ok($p['complete'] === false, 'not complete with one ticked');

$qFull = ['preorder_checklist' => json_encode([$k0=>1, $k1=>1, preorder_item_key($items[2])=>1])];
t_ok(preorder_checklist_progress($qFull)['complete'] === true, 'complete when all ticked');

// Item keys stable and distinct.
t_eq($k0, preorder_item_key('Scope clear'), 'item key stable for same text');
t_ok($k0 !== $k1, 'different points get different keys');

// require-all is opt-in.
setting_set('preorder_checklist_require', '');
t_ok(preorder_checklist_require_all() === false, 'require-all off unless configured');
setting_set('preorder_checklist_require', '1');
t_ok(preorder_checklist_require_all() === true, 'require-all can be switched on');

// A quote with no stored ticks reads as zero progress, without error.
t_eq(preorder_checklist_progress(['preorder_checklist'=>''])['done'], 0, 'empty ticks read as zero');

// tidy up
setting_set('preorder_checklist_on', ''); setting_set('preorder_checklist_require', ''); setting_set('preorder_checklist_items', '');
