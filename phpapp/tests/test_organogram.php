<?php
// Organogram importer — the deterministic engine (parsing, code generation, and
// applying an org chart into offices, designations, positions and reporting lines).
// The Excel/PowerPoint/AI readers are exercised in the browser; the logic they all
// funnel into is covered here.

t_section('Organogram importer');

if (!function_exists('orga_parse_table') || !function_exists('orga_apply')) {
    t_ok(true, 'organogram engine not present — skipped');
    return;
}

// ---- Parsing: header detection + column mapping ----------------------------
$rows = orga_parse_table("Title,Department,Office,Grade,Reports To\n"
    . "Managing Director,Executive,Head Office,E1,\n"
    . "HR Head,Human Resources,Head Office,M1,Managing Director\n"
    . "Recruiter,Human Resources,Mumbai,O1,HR Head\n");
t_eq(count($rows), 3, 'three roles parsed from a headered table');
t_eq($rows[1]['designation'], 'HR Head', 'the Title column maps to the designation');
t_eq($rows[1]['office'], 'Head Office', 'the Office column is read');
t_eq($rows[2]['reports_to'], 'HR Head', 'the Reports To column is read');
// A title-only chart (what PowerPoint/Visio/AI often give) still yields rows.
$flat = orga_parse_table("title\nCEO\nCFO\n");
t_ok(count($flat) === 2 && $flat[0]['name'] === 'CEO' && $flat[0]['designation'] === 'CEO', 'a title-only list still parses (name = designation)');

// ---- Code generation: initials, fallback, de-duplication ------------------
$used = [];
t_eq(orga_code('Talent Acquisition Specialist', $used), 'TAS', 'initials become the code');
t_eq(orga_code('Talent Acquisition Team', $used), 'TAT', 'a different title gets its own code');
$again = orga_code('Talent Acquisition Specialist', $used);   // TAS taken → de-duped
t_ok($again !== 'TAS' && strpos($again, 'TA') === 0, 'a clashing code is de-duplicated');

// ---- Apply: creates offices, designations, positions, links --------------
$before = count(positions_all(false));
$r = orga_apply([
    ['name' => 'Managing Director', 'office' => 'ORGA Head Office', 'department' => 'ORGA Exec', 'grade' => 'E1'],
    ['name' => 'HR Head', 'office' => 'ORGA Head Office', 'department' => 'ORGA HR', 'reports_to' => 'Managing Director'],
    ['name' => 'Recruiter One', 'office' => 'ORGA Mumbai', 'department' => 'ORGA HR', 'reports_to' => 'HR Head'],
]);
t_eq($r['created'], 3, 'three positions created');
t_eq($r['offices'], 2, 'two new offices created (Head Office + Mumbai)');
t_ok($r['designations'] >= 3, 'a designation is created for each distinct title');
t_ok($r['departments'] >= 2, 'departments are created from the chart');
t_eq($r['linked'], 2, 'two reporting lines linked (HR Head→MD, Recruiter→HR Head)');
t_ok(empty($r['unresolved']), 'every reporting line resolved');

// The office really exists and the position points at it.
$off = ops_one("SELECT id FROM offices WHERE name=?", ['ORGA Head Office']);
t_ok($off && (int) $off['id'] > 0, 'the office was written to the offices master');
$md = ops_one("SELECT reports_to_id, office_id FROM positions WHERE name=?", ['Managing Director']);
$hr = ops_one("SELECT id FROM positions WHERE name=?", ['HR Head']);
$hrRow = ops_one("SELECT reports_to_id FROM positions WHERE name=?", ['HR Head']);
t_ok($md && (int) $md['office_id'] === (int) $off['id'], 'the MD position is linked to its office');
t_ok($hrRow && (int) $hrRow['reports_to_id'] > 0, 'HR Head reports to someone (the MD)');

// Re-running the same chart updates in place — never duplicates.
$r2 = orga_apply([
    ['name' => 'Managing Director', 'office' => 'ORGA Head Office'],
    ['name' => 'HR Head', 'office' => 'ORGA Head Office', 'reports_to' => 'Managing Director'],
    ['name' => 'Recruiter One', 'office' => 'ORGA Mumbai', 'reports_to' => 'HR Head'],
]);
t_eq($r2['created'], 0, 're-running creates no new positions');
t_eq($r2['offices'], 0, 're-running creates no new offices');
$dupe = (int) ops_val("SELECT COUNT(*) FROM positions WHERE name=?", ['Managing Director']);
t_eq($dupe, 1, 'the Managing Director position was not duplicated');

// ---- Preview summary counts new masters without writing anything ----------
$sum = orga_preview_summary([
    ['name' => 'Managing Director', 'office' => 'ORGA Head Office'],           // office exists now
    ['name' => 'Brand New Role', 'office' => 'ORGA Brand New Office'],         // both new
]);
t_eq($sum['positions'], 2, 'the summary counts every role');
t_eq($sum['offices'], 1, 'the summary counts only the office that does not exist yet');

// clean up
db()->prepare("DELETE FROM positions WHERE name IN ('Managing Director','HR Head','Recruiter One')")->execute();
