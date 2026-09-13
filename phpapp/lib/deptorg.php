<?php
// ============================================================================
//  EXAACT — Department ⇄ Designation masters, the Department hub, and the
//  org-driven helpers used by the org chart and the approval chain.
//
//  Design (per the product decision): a designation OPTIONALLY belongs to a
//  department; "General" (blank) is allowed, so a shared title (Manager, Intern)
//  is not duplicated across departments. The link is stored additively on the
//  designation lookup value (attr_department) — no existing dropdown changes.
//
//  From this the app gets: department-wise designation lookups, a Department hub
//  (designations + positions + headcount + people, per department), a
//  department-grouped org chart, and approvals that can route up the reporting
//  line. All additive & non-destructive.
// ============================================================================

function deptorg_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (function_exists('ensure_column')) {
        try { ensure_column('lookup_values', 'attr_department', "VARCHAR(160) DEFAULT ''"); } catch (Throwable $e) {}
    }
}

function desig_type_id() { $t = function_exists('lk_type') ? lk_type('designation') : null; return $t ? (int) $t['id'] : 0; }

// All active designations with their (optional) department. [{id,code,label,department}]
function desig_rows() {
    deptorg_migrate();
    $tid = desig_type_id(); if (!$tid) return [];
    try {
        $rows = ops_all("SELECT id, code, label, COALESCE(attr_department,'') attr_department
                         FROM lookup_values WHERE type_id=? AND active=1 ORDER BY label", [$tid]);
    } catch (Throwable $e) { return []; }
    return array_map(fn($r) => ['id' => (int) $r['id'], 'code' => (string) $r['code'], 'label' => (string) $r['label'], 'department' => trim((string) $r['attr_department'])], $rows);
}

// Designations available for a department: those tagged to it PLUS the General
// (untagged) ones. Empty $dept returns every designation.
function designations_by_department($dept) {
    $dept = trim((string) $dept); $out = [];
    foreach (desig_rows() as $r) {
        if ($dept === '' || $r['department'] === '' || strcasecmp($r['department'], $dept) === 0) $out[] = $r;
    }
    return $out;
}

// Assign (or clear, with '') the department of one designation value.
function desig_set_department($valueId, $dept) {
    deptorg_migrate();
    db()->prepare("UPDATE lookup_values SET attr_department=? WHERE id=?")->execute([trim((string) $dept), (int) $valueId]);
}
// Link a designation to a department BY LABEL, only if it is currently General
// (never overrides a manual assignment). Used by the organogram importer.
function desig_link_department($label, $dept) {
    $label = trim((string) $label); $dept = trim((string) $dept);
    if ($label === '' || $dept === '') return;
    foreach (desig_rows() as $r) {
        if (strcasecmp($r['label'], $label) === 0 && $r['department'] === '') { desig_set_department($r['id'], $dept); return; }
    }
}

// Every department name the workspace knows — the department master, plus any
// department already used on a position or a person (so nothing is missed).
function dept_names() {
    $set = [];
    if (function_exists('lk_options_or')) foreach (lk_options_or('department', []) as $l) { $l = trim((string) $l); if ($l !== '') $set[$l] = true; }
    foreach (['positions', 'users'] as $tbl) {
        try { foreach (ops_all("SELECT DISTINCT department d FROM $tbl WHERE COALESCE(department,'')<>''") as $r) { $d = trim((string) $r['d']); if ($d !== '') $set[$d] = true; } }
        catch (Throwable $e) {}
    }
    $names = array_keys($set); natcasesort($names); return array_values($names);
}

// The Department hub rollup: for each department, its designations, positions
// (with sanctioned/occupied/vacant headcount) and active people; plus the
// General (unassigned) designations so they can be filed.
function dept_hub() {
    $desig = desig_rows();
    $posByDept = [];
    if (function_exists('positions_all')) foreach (positions_all(false) as $p) $posByDept[trim((string) $p['department'])][] = $p;
    $usrByDept = [];
    try { foreach (ops_all("SELECT COALESCE(department,'') d, COUNT(*) n FROM users WHERE is_active=1 GROUP BY department") as $r) $usrByDept[trim((string) $r['d'])] = (int) $r['n']; }
    catch (Throwable $e) {}
    $rows = [];
    foreach (dept_names() as $d) {
        $pos = $posByDept[$d] ?? []; $sanc = 0; $occ = 0;
        foreach ($pos as $p) { $sanc += (int) ($p['sanctioned_headcount'] ?? 0); $occ += (int) ($p['occupied_headcount'] ?? 0); }
        $rows[] = [
            'department' => $d,
            'designations' => array_values(array_filter($desig, fn($x) => strcasecmp($x['department'], $d) === 0)),
            'positions' => $pos, 'sanctioned' => $sanc, 'occupied' => $occ, 'vacant' => max(0, $sanc - $occ),
            'people' => $usrByDept[$d] ?? 0,
        ];
    }
    $general = array_values(array_filter($desig, fn($x) => $x['department'] === ''));
    return ['depts' => $rows, 'general' => $general];
}

// ---- Org chart grouped by department --------------------------------------
// The flat position list bucketed by department, each bucket carrying its
// headcount totals — for a department-banded org chart.
function dept_org_groups() {
    $groups = [];
    if (!function_exists('positions_all')) return $groups;
    foreach (positions_all(false) as $p) {
        $d = trim((string) $p['department']); if ($d === '') $d = 'Unassigned';
        $groups[$d]['positions'][] = $p;
    }
    foreach ($groups as $d => &$g) {
        $g['department'] = $d; $g['sanctioned'] = 0; $g['occupied'] = 0;
        foreach ($g['positions'] as $p) { $g['sanctioned'] += (int) ($p['sanctioned_headcount'] ?? 0); $g['occupied'] += (int) ($p['occupied_headcount'] ?? 0); }
        $g['vacant'] = max(0, $g['sanctioned'] - $g['occupied']);
    }
    unset($g);
    uksort($groups, 'strcasecmp');
    return $groups;
}

// ---- Approvals that follow the org chart ----------------------------------
// Special approver tokens the approval matrix can use instead of a fixed role:
// route to the manager N levels up the reporting line from the requisition's
// position. Resolved to a person at run time.
const APPR_ORG_APPROVERS = [
    '__MGR1__' => 'Reporting manager (1 level up)',
    '__MGR2__' => 'Manager’s manager (2 levels up)',
    '__MGR3__' => 'Head of function (3 levels up)',
    '__HOD__'  => 'Head of the position’s department',
];
function appr_is_org_approver($role) { return isset(APPR_ORG_APPROVERS[(string) $role]); }

// Walk `reports_to_id` up from a position; return the position $levels up (or the
// topmost reached). Guarded against loops.
function position_manager_up($positionId, $levels) {
    $positionId = (int) $positionId; $levels = max(1, (int) $levels);
    $seen = [];
    for ($i = 0; $i < $levels; $i++) {
        if (!$positionId || isset($seen[$positionId])) break;
        $seen[$positionId] = true;
        $p = position_get($positionId);
        if (!$p) return null;
        $up = (int) ($p['reports_to_id'] ?? 0);
        if (!$up) return $p;                 // reached the top of the tree
        $positionId = $up;
    }
    return $positionId ? position_get($positionId) : null;
}

// Resolve an org-chart approver token to a user id (best-effort): find the
// position N levels up from $positionId, then the active user occupying it
// (matched by position_title, else the department's head). Returns 0 if it
// cannot be resolved — the caller then falls back to the rule's role.
function appr_resolve_org_approver($token, $positionId, $department = '') {
    $token = (string) $token;
    $pos = null;
    if ($token === '__HOD__') {
        // Head of the position's own department.
        $dept = $department !== '' ? $department : '';
        if ($dept === '' && $positionId) { $p0 = position_get($positionId); $dept = trim((string) ($p0['department'] ?? '')); }
        if ($dept === '') return 0;
        return appr_user_for_department_head($dept);
    }
    $levels = $token === '__MGR3__' ? 3 : ($token === '__MGR2__' ? 2 : 1);
    if (!$positionId) return 0;
    $pos = position_manager_up($positionId, $levels);
    if (!$pos) return 0;
    return appr_user_for_position($pos);
}

// The active user occupying a position: matched by position_title, else the
// position's HOD name, else the office head of the position's office.
function appr_user_for_position($pos) {
    if (!is_array($pos)) return 0;
    $title = trim((string) ($pos['name'] ?? ''));
    if ($title !== '') {
        try { $u = ops_one("SELECT id FROM users WHERE is_active=1 AND LOWER(TRIM(COALESCE(position_title,'')))=LOWER(?) LIMIT 1", [$title]); if ($u) return (int) $u['id']; }
        catch (Throwable $e) {}
    }
    $hod = trim((string) ($pos['hod_name'] ?? ''));
    if ($hod !== '') {
        try { $u = ops_one("SELECT id FROM users WHERE is_active=1 AND LOWER(TRIM(first_name||' '||last_name))=LOWER(?) LIMIT 1", [$hod]); if ($u) return (int) $u['id']; }
        catch (Throwable $e) {}
    }
    return 0;
}
function appr_user_for_department_head($dept) {
    $dept = trim((string) $dept); if ($dept === '') return 0;
    // A department head: an active user in that department whose title contains
    // Head/Manager/Director/Lead — the most senior match.
    try {
        $u = ops_one("SELECT id FROM users WHERE is_active=1 AND LOWER(TRIM(COALESCE(department,'')))=LOWER(?)
                      AND (LOWER(COALESCE(position_title,'')) LIKE '%head%' OR LOWER(COALESCE(position_title,'')) LIKE '%director%'
                        OR LOWER(COALESCE(position_title,'')) LIKE '%manager%' OR LOWER(COALESCE(position_title,'')) LIKE '%lead%')
                      ORDER BY CASE
                        WHEN LOWER(COALESCE(position_title,'')) LIKE '%head%' THEN 1
                        WHEN LOWER(COALESCE(position_title,'')) LIKE '%director%' THEN 2
                        WHEN LOWER(COALESCE(position_title,'')) LIKE '%manager%' THEN 3 ELSE 4 END LIMIT 1", [$dept]);
        if ($u) return (int) $u['id'];
    } catch (Throwable $e) {}
    return 0;
}

// ---- Screen: Department hub -------------------------------------------------
function ops_departments($route, $method) {
    ops_require(function_exists('is_coordinator_level') && is_coordinator_level(), 'Only coordinators / administrators can manage departments.');
    deptorg_migrate();
    if ($method === 'POST') {
        $do = (string) ($_POST['do'] ?? '');
        if ($do === 'assign') {
            desig_set_department((int) ($_POST['value_id'] ?? 0), (string) ($_POST['department'] ?? ''));
            flash('Designation filed under its department.');
            redirect('/departments'); return true;
        }
    }
    $hub = dept_hub();
    view('ops/departments', ['hub' => $hub, 'deptNames' => dept_names()]);
    return true;
}
