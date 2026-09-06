<?php
// When an admin adds a new dropdown list, they can now file it under a module
// so it lands in the right group on the Masters screen (instead of "Other").
// This checks the create-with-module path the /lookups form uses.
t_section('Masters — a new list can be filed under a module');

// Create a list and file it under People (recruitment).
lk_add_type('test_source_channel', 'Source channel', null, 0, 99);
lk_set_module('test_source_channel', 'People');

$t = lk_type('test_source_channel');
t_ok((bool)$t, 'the new list was created');
t_eq($t['module'], 'People', 'it is filed under the chosen module');

// It shows in the People group, not Other.
$grouped = lk_types_grouped();
$inPeople = false;
foreach ($grouped['People'] ?? [] as $row) if ($row['type_key'] === 'test_source_channel') $inPeople = true;
t_ok($inPeople, 'it appears under the Recruitment & people group on the Masters screen');

// Clean up so the shared DB is left as found.
$id = (int)$t['id'];
db()->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([$id]);
db()->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$id]);
t_ok(!lk_type('test_source_channel'), 'test list removed');
