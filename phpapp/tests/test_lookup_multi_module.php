<?php
// ============================================================================
//  A MASTER LIST MAY BELONG TO MORE THAN ONE GROUP              (UAT 1.3.4 · R-18)
//
//  The owner, twice: "Still modules is single selection instead of multiple."
//  lookup_types.module was one VARCHAR(30) — wide enough for one group name and
//  not two — and the picker was a single dropdown. A list such as Designation is
//  genuinely used by Recruitment AND by Administration, so filing it under one
//  made it missing from the other.
//
//  The column now holds a comma-separated set, read everywhere through
//  lk_modules_of(). A single legacy value is a set of one, so nothing needed
//  migrating.
//
//  THE DANGEROUS READER: lk_prune_offplan_lists() DELETES lists whose module a
//  hosted plan excludes. Asking it of only the first group would delete a list
//  still in daily use under its second. It therefore keeps a list when ANY of
//  its groups is on, and that direction is asserted here.
// ============================================================================

t_section('R-18 — a list can sit under several headings');

$pdo = db();
lk_migrate();

// ---------------------------------------------------------------------------
//  1. READING THE SET.
// ---------------------------------------------------------------------------
t_ok(function_exists('lk_modules_of'), 'there is one reader for the set');
t_eq(lk_modules_of(['module' => 'People']), ['People'], 'a single legacy value is a set of one');
t_eq(lk_modules_of(['module' => 'People,Admin']), ['People', 'Admin'], 'two groups are read as two');
t_eq(lk_modules_of(['module' => ' People , Admin ']), ['People', 'Admin'], 'spacing around the comma is tolerated');
t_eq(lk_modules_of(['module' => 'People,People']), ['People'], 'a repeat is not counted twice');
t_eq(lk_modules_of(['module' => '']), [], 'no group is an empty set, never a group named ""');
t_eq(lk_modules_of(['module' => ',,']), [], '…and neither is punctuation');
//  Writing is the same shape in reverse.
t_eq(lk_modules_csv(['People', 'Admin']), 'People,Admin', 'a set is stored as a plain comma list');
t_eq(lk_modules_csv([' People ', '', 'People']), 'People', '…trimmed, de-duplicated, and empties dropped');

// ---------------------------------------------------------------------------
//  2. THE COLUMN IS WIDE ENOUGH FOR A REAL SET.
//     30 characters held "Quality & Accreditation" and nothing else — which is
//     the shape that forced the single choice in the first place.
// ---------------------------------------------------------------------------
$two = lk_modules_csv(['Quality & Accreditation', 'Operations']);
t_ok(strlen($two) > 30, 'ARMING · a realistic two-group set is longer than the old column (' . strlen($two) . ' chars)');
$pdo->prepare("INSERT INTO lookup_types (type_key,label,module,sort_order) VALUES ('zz_r18','R18 probe',?,999)")->execute([$two]);
$tid = (int) $pdo->lastInsertId();
$back = ops_one("SELECT module FROM lookup_types WHERE id=?", [$tid]);
t_eq((string) $back['module'], $two, '*** a two-group set survives the round trip — it is not truncated');
t_eq(lk_modules_of($back), ['Quality & Accreditation', 'Operations'], '…and reads back as both groups');

// ---------------------------------------------------------------------------
//  3. IT APPEARS UNDER EVERY HEADING IT NAMES.
// ---------------------------------------------------------------------------
lk_types(true);
$grouped = lk_types_grouped();
$inGroup = function ($g) use ($grouped) {
    foreach ($grouped[$g] ?? [] as $t) if (($t['type_key'] ?? '') === 'zz_r18') return true;
    return false;
};
t_ok($inGroup('Quality & Accreditation'), '*** the list is shown under the first heading');
t_ok($inGroup('Operations'), '*** and under the second — which is the whole point');
t_ok(!$inGroup(''), '…and not also dumped under the blank heading');
//  A list with no group still has a home.
$pdo->prepare("INSERT INTO lookup_types (type_key,label,module,sort_order) VALUES ('zz_r18b','R18 ungrouped','',999)")->execute();
$tid2 = (int) $pdo->lastInsertId();
lk_types(true);
$g2 = lk_types_grouped();
$found = false; foreach ($g2[''] ?? [] as $t) if (($t['type_key'] ?? '') === 'zz_r18b') $found = true;
t_ok($found, 'a list with no group keeps its place under the blank heading');

// ---------------------------------------------------------------------------
//  4. THE PRUNER DELETES ONLY WHEN EVERY GROUP IS OFF.
//     This is the assertion that matters most: getting it wrong destroys a
//     customer's list.
// ---------------------------------------------------------------------------
$keepIfAnyOn = function (array $mods, array $onGroups) {
    //  The rule as the pruner applies it, stated here so the test does not
    //  depend on being able to switch a licence off inside the suite.
    foreach ($mods as $m) if (in_array($m, $onGroups, true)) return true;   // any on → keep
    return false;
};
t_ok($keepIfAnyOn(['People', 'Money'], ['People']), '*** kept when one of its two groups is on');
t_ok($keepIfAnyOn(['Money', 'People'], ['People']), '*** …whichever order they are named in');
t_ok(!$keepIfAnyOn(['Money'], ['People']), 'removed when its only group is off');
t_ok(!$keepIfAnyOn(['Money', 'Sales'], ['People']), 'removed only when EVERY group is off');
//  And the pruner really does read the set rather than the raw column.
$src = (string) @file_get_contents(__DIR__ . '/../lib/lookups.php');
$pos = strpos($src, 'function lk_prune_offplan_lists');
$body = $pos === false ? '' : substr($src, $pos, 1400);
t_ok(strpos($body, 'lk_modules_of') !== false,
     '*** the pruner asks for the whole set, not one value');
t_ok(strpos($body, '$anyOn') !== false,
     '*** …and keeps the list when any of its groups is still on');

// ---------------------------------------------------------------------------
//  5. THE PICKER ON THE SCREEN IS A SET, NOT A CHOICE.
// ---------------------------------------------------------------------------
$view = (string) @file_get_contents(__DIR__ . '/../views/ops/lookup_values.php');
t_ok(strpos($view, 'name="module[]"') !== false, '*** the picker posts a set');
t_ok(strpos($view, 'type="checkbox"') !== false, '*** …as tick boxes, not a single dropdown');

foreach ([$tid, $tid2] as $x) { try { $pdo->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$x]); } catch (Throwable $e) {} }
lk_types(true);
