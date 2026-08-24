<?php
// A "possible duplicates" finder surfaces offices that share a name so an admin can
// merge them (new duplicates are already blocked at creation; this cleans up any that
// pre-date the guards). It flags which copy has work booked against it — the safe one
// to keep and merge the empties into.
t_section('possible-duplicate office finder');

$pdo = db();
// Two offices with the SAME name (a legacy duplicate), and one unique office.
$pdo->prepare("INSERT INTO offices (code, name) VALUES ('ZDF1','ZZ Dup Finder')")->execute();
$a = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (code, name) VALUES ('ZDF2','ZZ Dup Finder')")->execute();
$b = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (code, name) VALUES ('ZUF1','ZZ Unique Finder')")->execute();
$u = (int)$pdo->lastInsertId();

// Book work against the SECOND copy, so it should be the suggested "keep".
$pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, created_at) VALUES ('ZDF-JOB', ?, ?)")->execute([$b, date('c')]);

$groups = office_duplicate_groups();
$mine = null;
foreach ($groups as $g) if (strcasecmp($g['name'], 'ZZ Dup Finder') === 0) { $mine = $g; break; }
t_ok($mine !== null, 'the same-named offices are reported as a duplicate group');
t_ok(count($mine['offices']) === 2, 'the group lists both copies');

// The unique office is NOT reported.
$uniqueReported = false;
foreach ($groups as $g) if (strcasecmp($g['name'], 'ZZ Unique Finder') === 0) $uniqueReported = true;
t_ok(!$uniqueReported, 'a uniquely-named office is not reported as a duplicate');

// The copy with work booked is flagged in_use (the safe keep); the empty one is not.
$byId = [];
foreach ($mine['offices'] as $o) $byId[(int)$o['id']] = $o;
t_ok(!empty($byId[$b]['in_use']), 'the copy with a job booked is flagged as in use');
t_ok(empty($byId[$a]['in_use']), 'the empty copy is flagged as not in use');

// Merging the empty copy into the used one removes it and repoints nothing to break.
$r = office_merge($a, $b);
t_ok(!empty($r['ok']), 'the empty copy merges into the used one');
t_ok(ops_one("SELECT id FROM offices WHERE id=?", [$a]) === false || ops_one("SELECT id FROM offices WHERE id=?", [$a]) === null,
    'the merged-away office is gone');
// After the merge, the pair is no longer a duplicate group.
$still = false; foreach (office_duplicate_groups() as $g) if (strcasecmp($g['name'], 'ZZ Dup Finder') === 0) $still = true;
t_ok(!$still, 'the group clears once the duplicate is merged');

// The finder is surfaced on the offices screen with a one-click merge.
$view = file_get_contents(__DIR__ . '/../views/ops/hierarchy.php');
t_ok(strpos($view, 'office_duplicate_groups()') !== false && strpos($view, 'Possible duplicate') !== false,
    'the offices screen shows a possible-duplicates panel');
t_ok(strpos($view, 'suggested keep') !== false && strpos($view, "name=\"do\" value=\"office-merge\"") !== false,
    'each duplicate offers a one-click merge into the suggested keep');
