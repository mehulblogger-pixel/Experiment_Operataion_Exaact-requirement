<?php
// Branch offices must be unique by name. Duplicates were being created because two
// insert paths had no dedup: the "+ Add new" AJAX quick-add (ops.php kind=office) and
// the Masters office-save (orgadmin.php office-save) both INSERTed blindly. Both now
// reject / return-existing on a name that already exists; office_quick_create() already did.
t_section('branch offices are unique by name (no duplicates)');

$pdo = db();
// Use distinctive names so the assertions do not depend on (or disturb) seeded offices.
$nameA = 'ZZ Dedup Office A';
$nameB = 'ZZ Dedup Office B';
$first = office_quick_create($nameA);
t_ok($first > 0, 'a distinctive office is created');

// office_quick_create returns the EXISTING office for a duplicate name (any case), no insert.
$again = office_quick_create(strtolower($nameA));
t_ok($again === $first, 'office_quick_create returns the existing office for a duplicate name (case-insensitive)');
t_ok((int)ops_val("SELECT COUNT(*) FROM offices WHERE LOWER(name)=LOWER(?)", [$nameA]) === 1, 'no second row was created for the same name');

// A genuinely new name is created as a distinct office.
$newId = office_quick_create($nameB);
t_ok($newId > 0 && $newId !== $first, 'a genuinely new office name is created as a distinct office');

// The two previously-unguarded insert paths now carry a dedup guard (source).
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "SELECT id FROM offices WHERE LOWER(name)=LOWER(?) LIMIT 1") !== false
    && strpos($ops, "'existing' => true") !== false,
    'the "+ Add new" office quick-add returns an existing office instead of duplicating');
$org = file_get_contents(__DIR__ . '/../lib/orgadmin.php');
t_ok(strpos($org, 'SELECT id FROM offices WHERE LOWER(name)=LOWER(?)') !== false
    && strpos($org, 'names must be unique') !== false,
    'the Masters office-save rejects a duplicate name on create or rename');
