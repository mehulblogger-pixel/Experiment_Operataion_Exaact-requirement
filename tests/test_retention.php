<?php
// Record-retention schedule (ISO §8.4, Roadmap Step 27).
t_section('retention schedule (ISO 8.4)');

// Seeded with a sensible starting schedule on first boot.
$rows = retention_all();
t_ok(count($rows) >= 5, 'a starting schedule is seeded (' . count($rows) . ' rows)');

// Add a row.
[$id, $err] = retention_save([
    'record_type' => 'Site entry passes', 'retention_period' => '2 years',
    'basis' => 'Client site policy', 'disposal_action' => 'Secure delete',
]);
t_ok($id > 0 && $err === '', 'a row is added');

// A blank record type is refused.
[$bad, $e2] = retention_save(['record_type' => '  ']);
t_ok($bad === 0 && $e2 !== '', 'a row with no record type is refused');

// Edit it.
[$eid, $e3] = retention_save(['record_type' => 'Site entry passes', 'retention_period' => '3 years'], $id);
t_ok($eid === $id && $e3 === '', 'a row can be edited');
t_eq(retention_get($id)['retention_period'], '3 years', 'the edit is stored');

// Remove (deactivate) it — drops out of the active schedule, seed count restored.
db()->prepare("UPDATE retention_rules SET active=0 WHERE id=?")->execute([$id]);
$activeIds = array_column(retention_all(), 'id');
t_ok(!in_array($id, $activeIds, true), 'a removed row leaves the active schedule');
