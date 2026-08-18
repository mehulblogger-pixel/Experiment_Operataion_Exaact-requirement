<?php
// The vetting authority's checklist is fully configurable and switchable on/off,
// nothing hardcoded into the workflow. Guards: off by default; items parse from
// the setting; a stable per-item key; tick state round-trips; and the require-all
// gate only bites when configured.
t_section('configurable vetting checklist');

// Off by default — an untouched install shows no checklist.
setting_set('vetting_checklist_on', '');
setting_set('vetting_checklist_items', '');
t_ok(idems_vetting_checklist_enabled() === false, 'the checklist is off by default');
t_ok(idems_vetting_checklist_items() === [], 'no items configured by default');

// A starter list is offered (as editable configuration, not applied behaviour).
t_ok(count(preg_split('/\n/', idems_vetting_checklist_default())) >= 5, 'a starter checklist is offered to edit');

// Configure three points and switch it on.
setting_set('vetting_checklist_on', '1');
setting_set('vetting_checklist_items', "Details correct\n\n  Scope covered  \nPhotos attached\n");
t_ok(idems_vetting_checklist_enabled() === true, 'the checklist can be switched on');
$items = idems_vetting_checklist_items();
t_eq(count($items), 3, 'blank lines are ignored; three points remain');
t_eq($items[1], 'Scope covered', 'each point is trimmed');

// Item keys are stable and distinct.
$k0 = idems_vetting_item_key($items[0]);
t_eq($k0, idems_vetting_item_key('Details correct'), 'the item key is stable for the same text');
t_ok($k0 !== idems_vetting_item_key($items[2]), 'different points get different keys');

// Tick state round-trips off a report row.
$state = idems_vetting_checklist_state(['vet_checklist' => json_encode([$k0 => 1])]);
t_ok(!empty($state[$k0]), 'a saved tick is read back for the report');
t_ok(idems_vetting_checklist_state(['vet_checklist' => '']) === [], 'no saved ticks reads as empty');

// The require-all gate is opt-in.
setting_set('vetting_checklist_require', '');
t_ok(idems_vetting_checklist_require_all() === false, 'require-all is off unless configured');
setting_set('vetting_checklist_require', '1');
t_ok(idems_vetting_checklist_require_all() === true, 'require-all can be switched on');

// tidy up so other tests see a clean setting
setting_set('vetting_checklist_on', '');
setting_set('vetting_checklist_require', '');
setting_set('vetting_checklist_items', '');
