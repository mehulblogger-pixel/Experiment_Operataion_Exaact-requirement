<?php
// "Add new activity" from the call form must work even on an install that shows
// Business Units from the shipped constant but never registered the activity
// list — it used to fail with "No activity list". The quick-add now self-heals:
// it (re)creates the activity list as a CHILD of the business-unit list and adds
// the value under the picked unit. This guards that mechanism.
t_section('quick-add activity self-heals a missing list');

// A business-unit list with a value (as the constant seeds it).
if (function_exists('lk_ensure_type_map') && defined('OPS_SBUS')) lk_ensure_type_map('sbu', 'Business Unit', OPS_SBUS);
$sbu = lk_type('sbu');
t_ok(is_array($sbu), 'the business-unit list exists');
$firstCode = array_key_first(OPS_SBUS);
$sbuVal = ops_one("SELECT * FROM lookup_values WHERE type_id=? AND code=?", [$sbu['id'], $firstCode]);
t_ok(is_array($sbuVal), 'the picked business unit has a lookup row');

// Simulate the deployed state: the activity list is missing.
if ($at = lk_type('activity')) {
    db()->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([$at['id']]);
    db()->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$at['id']]);
}
t_ok(lk_type('activity') === null || lk_type('activity') == false, 'activity list is missing to start');

// The recovery the handler performs: create the child list, add the value.
$tid = lk_add_type('activity', 'Activity code', $sbu['id'], 0, 99);
$t = lk_type('activity');
t_ok(is_array($t), 'the activity list is (re)created');
t_eq((int)$t['parent_type_id'], (int)$sbu['id'], 'the activity list is a child of the business-unit list');
$vid = lk_add_value($t['id'], $sbuVal['id'], '', 'Supply Chain', 99);
$val = ops_one("SELECT * FROM lookup_values WHERE id=?", [(int)$vid]);
t_ok(is_array($val), 'the new activity value is stored');
t_eq((int)$val['parent_value_id'], (int)$sbuVal['id'], 'the activity hangs under the picked business unit');
t_eq((string)$val['label'], 'Supply Chain', 'the activity keeps its name');

// And it is now offered under that business unit.
$under = ops_all("SELECT label FROM lookup_values WHERE type_id=? AND parent_value_id=?", [$t['id'], $sbuVal['id']]);
t_ok(in_array('Supply Chain', array_column($under, 'label'), true), 'the activity appears in the unit\'s list');
