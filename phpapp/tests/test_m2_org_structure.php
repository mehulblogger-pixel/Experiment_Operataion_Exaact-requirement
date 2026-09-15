<?php
// ============================================================================
//  PHASE 2 · MILESTONE 2 — ORGANISATION, DEPARTMENT & DESIGNATION STRUCTURE
//
//  M2 audited how EXAACT actually represents departments and job titles and
//  found ONE real problem worth fixing now, plus one that needs a business
//  decision (recorded, not improvised — see M2-COMPLETION-REPORT.md).
//
//  The problem fixed here: a single real department reaches the database
//  spelled two ways, because two shipped screens write the same column
//  differently —
//      Position master / team member -> the LABEL   ("Quality")
//      Partner contact / back-office -> the CODE    ("QUALITY")
//  Grouping on the raw value split one department into two rows in the
//  Department hub and the org chart: one row carried the positions and the
//  sanctioned headcount, the other carried the people. Neither was right.
//
//  dept_canon() resolves a stored value to the one label the department master
//  already defines for it. It is a READ-SIDE resolver: no stored value is
//  rewritten, and a department the master does not know passes through
//  untouched, so free text typed before today can never be lost.
//
//  These tests pin that behaviour, and pin the two bindings M2 corrected so a
//  designation or department added in Settings actually reaches the screens.
// ============================================================================

t_section('Milestone 2 — the department master is authoritative');

$pdo = db();
if (function_exists('position_migrate')) position_migrate();
if (function_exists('deptorg_migrate')) deptorg_migrate();

// ---------------------------------------------------------------------------
//  1. The masters exist and are reused — M2 must not have created new ones.
// ---------------------------------------------------------------------------
t_ok(function_exists('lk_options_or'), 'the lookup engine is present');
$deptMaster = lk_options_or('department', DEPARTMENTS);
$desigMaster = lk_options_or('designation', DESIGNATIONS);
t_ok(count($deptMaster) > 0, 'a department master is registered (' . count($deptMaster) . ' values)');
t_ok(count($desigMaster) > 0, 'a designation master is registered (' . count($desigMaster) . ' values)');
t_ok(!t_table_exists('departments'), 'no separate departments table was created — the lookup master is reused');
t_ok(!t_table_exists('designations'), 'no separate designations table was created — the lookup master is reused');

// ---------------------------------------------------------------------------
//  2. dept_canon() — code and label resolve to the SAME canonical name.
// ---------------------------------------------------------------------------
t_ok(function_exists('dept_canon'), 'dept_canon() exists');
$code = array_key_first($deptMaster);
$label = $deptMaster[$code];
t_eq(dept_canon($code), $label, "a stored CODE ('$code') resolves to its label");
t_eq(dept_canon($label), $label, "a stored LABEL ('$label') resolves to itself");
t_eq(dept_canon(strtolower($label)), $label, 'resolution is case-insensitive on the label');
t_eq(dept_canon('  ' . $code . ' '), $label, 'surrounding whitespace does not defeat resolution');
t_eq(dept_canon($code), dept_canon($label), 'code and label are the SAME department — this is the defect M2 fixed');

// ---------------------------------------------------------------------------
//  3. Nothing is lost: a department the master does not know passes through.
//     This is what protects free text already typed on the Position form.
// ---------------------------------------------------------------------------
t_eq(dept_canon('Tyre Retreading Cell'), 'Tyre Retreading Cell', 'unknown free text is preserved verbatim');
t_eq(dept_canon(''), '', 'blank stays blank');
t_eq(dept_canon(null), '', 'null is treated as blank, not as a department named "null"');

// ---------------------------------------------------------------------------
//  4. The real defect, reproduced end-to-end through the shipped functions:
//     one department, written both ways, must appear ONCE and carry BOTH sides.
// ---------------------------------------------------------------------------
$pdo->prepare("INSERT INTO positions (name,department,sanctioned_headcount,occupied_headcount,active,created_at)
               VALUES (?,?,?,?,1,?)")->execute(['M2 Probe Position', $label, 3, 1, date('c')]);
$posId = (int) $pdo->lastInsertId();
$made = [];
foreach ([['m2_lbl', $label], ['m2_code', $code]] as $u) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,department) VALUES (?,?, 'COORDINATOR',1,?)")
        ->execute([$u[0], 'M2', $u[1]]);
    $made[] = (int) $pdo->lastInsertId();
}

$names = dept_names();
$hits = array_values(array_filter($names, fn($n) => strcasecmp($n, $label) === 0 || strcasecmp($n, $code) === 0));
t_eq(count($hits), 1, "dept_names() lists '$label' once, not once per spelling");

$row = null;
foreach (dept_hub()['depts'] as $r) if ($r['department'] === $label) $row = $r;
t_ok($row !== null, "the Department hub has a row for '$label'");
t_ok($row && count($row['positions']) >= 1, 'that row carries the position written with the LABEL');
t_ok($row && $row['sanctioned'] >= 3, 'that row carries the sanctioned headcount (' . ($row['sanctioned'] ?? 0) . ')');
t_ok($row && $row['people'] >= 2, 'that row counts BOTH people — the one stored by label and the one stored by code');

$groups = dept_org_groups();
t_ok(isset($groups[$label]), "the org chart groups that position under '$label'");

// ---------------------------------------------------------------------------
//  5. The bindings M2 corrected: screens must read the LIVE master, so a value
//     added in Settings is actually offered and actually renders as its label.
// ---------------------------------------------------------------------------
$probeCode = 'M2_PROBE_DESIG';
$probeLbl  = 'M2 Probe Designation';
lk_ensure_value('designation', $probeCode, $probeLbl);

// An admin adds a value in Settings, then a screen renders it — that is the NEXT
// request. The master list is cached for the life of one request (correctly, and
// since M2 only for as long as the database it was read from is still current),
// so the test advances the epoch to stand where the next request stands. It does
// not bypass the cache; it uses the same door a new request comes through.
$GLOBALS['__db_epoch'] = (int) ($GLOBALS['__db_epoch'] ?? 0) + 1;

$fresh = lk_options_or('designation', DESIGNATIONS);
t_ok(isset($fresh[$probeCode]), 'a designation added to the master is visible through the live accessor');

t_ok(function_exists('rcc_designations'), 'the Command Centre has a live designation accessor');
if (function_exists('rcc_designations'))
    t_eq(rcc_designations()[$probeCode] ?? null, $probeLbl,
         'the Command Centre renders a newly added designation as its LABEL, not a raw code');

$masters = function_exists('ops_masters') ? ops_masters() : [];
$bo = $masters['back-office']['fields'] ?? [];
$boDesig = null; $boDept = null;
foreach ($bo as $f) { if ($f[0] === 'designation') $boDesig = $f[3]['opts'] ?? []; if ($f[0] === 'department') $boDept = $f[3]['opts'] ?? []; }
t_ok(is_array($boDesig) && isset($boDesig[$probeCode]),
     'the back-office staff form offers a designation added in Settings (it read the frozen constant before M2)');
t_ok(is_array($boDept) && count($boDept) === count($deptMaster),
     'the back-office staff form offers the live department master');

// ---------------------------------------------------------------------------
//  5b. The master accessor must not outlive the database it read from.
//      One workspace per database, and the database is swapped inside a request
//      when another company is entered — a cache that survived that swap served
//      the previous workspace's list. Proven, then fixed, in M2.
// ---------------------------------------------------------------------------
$before = lk_options_or('designation', DESIGNATIONS);
$dtx = lk_type('designation');
$pdo->prepare("UPDATE lookup_values SET label=? WHERE type_id=? AND code=?")
    ->execute(['M2 Renamed Probe', (int) $dtx['id'], $probeCode]);
t_eq(lk_options_or('designation', DESIGNATIONS)[$probeCode] ?? null, $probeLbl,
     'within ONE request the list stays cached (the cache still does its job)');
$GLOBALS['__db_epoch'] = (int) ($GLOBALS['__db_epoch'] ?? 0) + 1;
t_eq(lk_options_or('designation', DESIGNATIONS)[$probeCode] ?? null, 'M2 Renamed Probe',
     'after the database changes underneath it, the cache is rebuilt — no stale workspace values');

// ---------------------------------------------------------------------------
//  6. Boundary — M2 changed none of the hiring semantics it was told not to.
// ---------------------------------------------------------------------------
t_ok(in_array('hired_inspector_id', t_columns('requisitions'), true), 'requisitions.hired_inspector_id is untouched');
t_ok(in_array('position_id', t_columns('requisitions'), true), 'requisitions.position_id (Position link) is untouched');
t_ok(t_table_exists('recruit_pipelines'), 'the Recruitment pipeline engine is untouched');
t_ok(t_table_exists('pipelines'), 'the Sales pipeline engine is untouched');

// ---------------------------------------------------------------------------
//  Clean up — a test must leave the shared database as it found it.
// ---------------------------------------------------------------------------
$pdo->prepare("DELETE FROM positions WHERE id=?")->execute([$posId]);
foreach ($made as $uid) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
$dt = lk_type('designation');
if ($dt) $pdo->prepare("DELETE FROM lookup_values WHERE type_id=? AND code=?")->execute([(int) $dt['id'], $probeCode]);
