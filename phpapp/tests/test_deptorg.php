<?php
// Department ⇄ designation masters, the department rollup, org-chart grouping and
// the org-driven approval helpers.

t_section('Department & designation masters');

if (!function_exists('designations_by_department') || !function_exists('desig_set_department')) {
    t_ok(true, 'deptorg engine not present — skipped'); return;
}
deptorg_migrate();

// Ensure the designation lookup exists, then seed a few values to work with.
if (function_exists('lk_type') && !lk_type('designation') && function_exists('lk_add_type')) lk_add_type('designation', 'Designation', null, 0, 50);
$tid = desig_type_id();
if ($tid && function_exists('lk_add_value')) {
    foreach ([['DT_MGR', 'DeptTest Manager'], ['DT_REC', 'DeptTest Recruiter'], ['DT_GEN', 'DeptTest General Role']] as $v)
        if (!ops_one("SELECT id FROM lookup_values WHERE type_id=? AND code=?", [$tid, $v[0]])) lk_add_value($tid, null, $v[0], $v[1], 90);
}
$mgr = ops_one("SELECT id FROM lookup_values WHERE code='DT_MGR'");
$rec = ops_one("SELECT id FROM lookup_values WHERE code='DT_REC'");
if ($mgr && $rec) {
    desig_set_department((int) $mgr['id'], 'DeptTest HR');
    desig_set_department((int) $rec['id'], 'DeptTest HR');

    // A department's designations = those filed under it PLUS the General ones.
    $hr = designations_by_department('DeptTest HR');
    $labels = array_map(fn($x) => $x['label'], $hr);
    t_ok(in_array('DeptTest Manager', $labels, true) && in_array('DeptTest Recruiter', $labels, true), 'department-filed designations appear for that department');
    t_ok(in_array('DeptTest General Role', $labels, true), 'a General (unfiled) designation shows for every department');

    // A different department gets the General one, not HR-specific ones.
    $fin = designations_by_department('DeptTest Finance');
    $finLabels = array_map(fn($x) => $x['label'], $fin);
    t_ok(!in_array('DeptTest Recruiter', $finLabels, true), 'an HR-only designation does NOT show for Finance');
    t_ok(in_array('DeptTest General Role', $finLabels, true), 'the General designation still shows for Finance');

    // Import-time linking only fills a General designation, never overrides.
    desig_link_department('DeptTest General Role', 'DeptTest Ops');
    $g = ops_one("SELECT attr_department FROM lookup_values WHERE code='DT_GEN'");
    t_ok(trim((string) $g['attr_department']) === 'DeptTest Ops', 'desig_link_department files a General designation');
    desig_link_department('DeptTest Manager', 'DeptTest Ops');   // already HR — must not change
    $m = ops_one("SELECT attr_department FROM lookup_values WHERE code='DT_MGR'");
    t_ok(trim((string) $m['attr_department']) === 'DeptTest HR', 'desig_link_department never overrides a manual department');
}

t_section('Org chart grouping & org-driven approvals');

if (function_exists('position_save') && function_exists('dept_org_groups')) {
    position_migrate();
    $md = position_save(0, ['name' => 'DeptTest MD', 'code' => 'DT-MD', 'department' => 'DeptTest Exec', 'sanctioned_headcount' => 1, 'occupied_headcount' => 1]);
    $hd = position_save(0, ['name' => 'DeptTest Head', 'code' => 'DT-HD', 'department' => 'DeptTest HR', 'reports_to_id' => $md, 'sanctioned_headcount' => 1, 'occupied_headcount' => 1]);
    $st = position_save(0, ['name' => 'DeptTest Staff', 'code' => 'DT-ST', 'department' => 'DeptTest HR', 'reports_to_id' => $hd, 'sanctioned_headcount' => 2, 'occupied_headcount' => 1]);

    $groups = dept_org_groups();
    t_ok(isset($groups['DeptTest HR']), 'positions are grouped by department');
    t_ok((int) $groups['DeptTest HR']['sanctioned'] >= 3 && (int) $groups['DeptTest HR']['occupied'] >= 2, 'a department group totals its headcount');

    // Walk up the reporting line.
    if (function_exists('position_manager_up')) {
        $up1 = position_manager_up($st, 1);
        $up2 = position_manager_up($st, 2);
        $up9 = position_manager_up($st, 9);   // past the top → clamps to the top
        t_ok($up1 && (int) $up1['id'] === (int) $hd, 'one level up from Staff is the Head');
        t_ok($up2 && (int) $up2['id'] === (int) $md, 'two levels up is the MD');
        t_ok($up9 && (int) $up9['id'] === (int) $md, 'walking past the top clamps to the topmost position');
    }
    // Org approver tokens are recognised.
    if (function_exists('appr_is_org_approver')) {
        t_ok(appr_is_org_approver('__MGR1__') && appr_is_org_approver('__HOD__'), 'org-chart approver tokens are recognised');
        t_ok(!appr_is_org_approver('COORDINATOR'), 'a normal role is not an org token');
    }

    db()->prepare("DELETE FROM positions WHERE code IN ('DT-MD','DT-HD','DT-ST')")->execute();
}
