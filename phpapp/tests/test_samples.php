<?php
// Inspection item / sample handling register (ISO §7.2, Roadmap Step 23).
t_section('items & samples (ISO 7.2)');

// The editable master lists were seeded (configuration-first).
t_ok(count(sample_opts('sample_type')) > 0, 'item-type master seeded');
t_ok(count(sample_opts('sample_condition')) > 0, 'condition master seeded');
t_ok(count(sample_opts('sample_storage')) > 0, 'storage-location master seeded');

// Receive an item.
[$id, $err] = sample_save([
    'description' => '6in CS pipe spool, weld WS-14', 'item_type' => 'COUPON',
    'condition_code' => 'INTACT', 'storage_code' => 'LAB', 'received_by' => 'Store',
]);
t_ok($id > 0 && $err === '', 'an item is received and saved');
$s = sample_get($id);
t_ok(!empty($s['item_code']) && strpos($s['item_code'], 'SMP-') === 0, "a readable code is assigned ({$s['item_code']})");
t_eq($s['status'], 'RECEIVED', 'new item starts RECEIVED');

// Receipt writes the first custody entry automatically.
$cust = sample_custody_all($id);
t_ok(count($cust) >= 1 && $cust[0]['action'] === 'RECEIVED', 'receipt logs a custody entry');

// A blank description is refused.
[$bad, $err2] = sample_save(['description' => '   ']);
t_ok($bad === 0 && $err2 !== '', 'an item with no description is refused');

// Move it through testing, then close it as returned.
t_eq(sample_set_status($id, 'IN_TESTING', 'sent to lab'), '', 'move to IN_TESTING');
t_eq(sample_set_status($id, 'RETURNED', 'handed back to client'), '', 'close as RETURNED');
$s = sample_get($id);
t_eq($s['status'], 'RETURNED', 'status is RETURNED after closing');
t_ok($s['closed_on'] !== '' && $s['disposition'] === 'RETURN', 'closing details recorded');
t_ok(count(sample_custody_all($id)) >= 3, 'each transition added a custody entry');

// A closed item drops out of the default "open" list.
$openIds = array_column(samples_all(['open' => 1]), 'id');
t_ok(!in_array($id, $openIds, true), 'a closed item is not in the open list');
