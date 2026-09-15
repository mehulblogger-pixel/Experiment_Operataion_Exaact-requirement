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

// ---- Canonical department resolution --------------------------------------
// One real department can reach the database spelled three ways, because three
// shipped screens write the same column differently:
//   Position master  (plain text box)          -> free text, e.g. "Quality"
//   Team member      (datalist of master labels)-> label,     e.g. "Quality"
//   Partner contact / back-office staff (select)-> CODE,      e.g. "QUALITY"
// Grouping on the raw value therefore splits one department into several rows.
//
// dept_canon() maps a stored value onto the ONE label the department master
// already defines for it. The mapping is taken from the master itself — a code
// is resolved to its own label — so this introduces no new opinion about which
// departments exist, and it rewrites nothing: it is a read-side resolver only.
// A value the master does not know (genuine free text) is returned untouched,
// so nothing can be lost.
function dept_canon_map() {
    static $map = null, $at = -1;
    if ($map !== null && $at === db_epoch()) return $map;
    $at = db_epoch(); $map = ['code' => [], 'label' => []];
    if (!function_exists('lk_options_or')) return $map;
    foreach (lk_options_or('department', defined('DEPARTMENTS') ? DEPARTMENTS : []) as $code => $label) {
        $label = trim((string) $label); if ($label === '') continue;
        $map['code'][strtoupper(trim((string) $code))] = $label;
        $map['label'][strtolower($label)] = $label;
    }
    return $map;
}

// Stored department value -> its canonical label. Unknown values pass through.
//
// M3: this now consults the approved-term layer first, so a customer's own
// wording and any legacy hiring-department code that has been APPROVED as a term
// group under the one department. An unapproved term still falls through to the
// M2 code/label map and then to the value as stored — so nothing is merged by
// guesswork and nothing typed before today is lost.
function dept_canon($stored) {
    $v = trim((string) $stored); if ($v === '') return '';
    static $memo = [], $at = -1;
    if ($at !== db_epoch()) { $memo = []; $at = db_epoch(); }
    if (isset($memo[$v])) return $memo[$v];

    if (function_exists('vocab_resolve')) {
        try {
            $row = dept_of($v);
            if ($row) return $memo[$v] = vocab_display($row);
        } catch (Throwable $e) {}
    }
    $m = dept_canon_map();
    if (isset($m['code'][strtoupper($v)]))  return $memo[$v] = $m['code'][strtoupper($v)];
    if (isset($m['label'][strtolower($v)])) return $memo[$v] = $m['label'][strtolower($v)];
    return $memo[$v] = $v;
}

// Every department name the workspace knows — the department master, plus any
// department already used on a position or a person (so nothing is missed).
function dept_names() {
    $set = [];
    if (function_exists('lk_options_or')) foreach (lk_options_or('department', []) as $l) { $l = trim((string) $l); if ($l !== '') $set[$l] = true; }
    foreach (['positions', 'users'] as $tbl) {
        try { foreach (ops_all("SELECT DISTINCT department d FROM $tbl WHERE COALESCE(department,'')<>''") as $r) { $d = dept_canon($r['d']); if ($d !== '') $set[$d] = true; } }
        catch (Throwable $e) {}
    }
    $names = array_keys($set); natcasesort($names); return array_values($names);
}

// The Department hub rollup: for each department, its designations, positions
// (with sanctioned/occupied/vacant headcount) and active people; plus the
// General (unassigned) designations so they can be filed.
// Is the establishment (positions + sanctioned headcount) this workspace's to
// see? The Position register lives behind the paid People & hiring module, and
// /departments is a core screen with no module gate of its own — so the hub must
// ask, rather than assume that reaching the screen means owning the data.
function dept_hub_shows_establishment() {
    if (!function_exists('licence_enabled')) return true;
    try { return (bool) licence_enabled('hr'); } catch (Throwable $e) { return true; }
}

function dept_hub() {
    $desig = desig_rows();
    $posByDept = [];
    $establishment = dept_hub_shows_establishment();
    if ($establishment && function_exists('positions_all')) foreach (positions_all(false) as $p) $posByDept[dept_canon($p['department'])][] = $p;
    $usrByDept = [];
    // Summed, not assigned: two spellings of one department must add up, not overwrite.
    try { foreach (ops_all("SELECT COALESCE(department,'') d, COUNT(*) n FROM users WHERE is_active=1 GROUP BY department") as $r) { $d = dept_canon($r['d']); $usrByDept[$d] = ($usrByDept[$d] ?? 0) + (int) $r['n']; } }
    catch (Throwable $e) {}
    $rows = [];
    foreach (dept_names() as $d) {
        $pos = $posByDept[$d] ?? []; $sanc = 0; $occ = 0;
        foreach ($pos as $p) { $sanc += (int) ($p['sanctioned_headcount'] ?? 0); $occ += (int) ($p['occupied_headcount'] ?? 0); }
        $rows[] = [
            'department' => $d,
            'designations' => array_values(array_filter($desig, fn($x) => $x['department'] !== '' && strcasecmp(dept_canon($x['department']), $d) === 0)),
            'positions' => $pos, 'sanctioned' => $sanc, 'occupied' => $occ, 'vacant' => max(0, $sanc - $occ),
            'people' => $usrByDept[$d] ?? 0,
        ];
    }
    $general = array_values(array_filter($desig, fn($x) => $x['department'] === ''));
    return ['depts' => $rows, 'general' => $general, 'establishment' => $establishment];
}

// ---- Org chart grouped by department --------------------------------------
// The flat position list bucketed by department, each bucket carrying its
// headcount totals — for a department-banded org chart.
function dept_org_groups() {
    $groups = [];
    if (!function_exists('positions_all')) return $groups;
    foreach (positions_all(false) as $p) {
        $d = dept_canon($p['department']); if ($d === '') $d = 'Unassigned';
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
    if (function_exists('dept_vocab_seed')) dept_vocab_seed();

    // Creating or renaming a department, or deciding what a word means, is a
    // change to the shape of the organisation — the same bar the master lists
    // already set. Viewing the hub stays at coordinator level.
    $mayEdit = function_exists('is_admin_level') ? is_admin_level() : false;

    if ($method === 'POST') {
        $do = (string) ($_POST['do'] ?? '');
        if ($do === 'assign') {
            desig_set_department((int) ($_POST['value_id'] ?? 0), (string) ($_POST['department'] ?? ''));
            flash('Designation filed under its department.');
            redirect('/departments'); return true;
        }
        ops_require($mayEdit, 'Only administrators can change the department list.');
        if ($do === 'dept-save') {
            [$ok, $msg, $id] = dept_save((int) ($_POST['id'] ?? 0), $_POST, !empty($_POST['confirm_new']));
            if (!$ok && $id < 0) {
                // A near-duplicate: show what exists and let them decide (§32).
                flash($msg, 'error');
                redirect('/departments?tab=manage&edit=' . (int) ($_POST['id'] ?? 0) . '&dupe=' . abs($id)); return true;
            }
            flash($msg, $ok ? 'success' : 'error');
            redirect('/departments?tab=manage' . ($ok ? '&edit=' . $id : '')); return true;
        }
        if ($do === 'dept-active') {
            dept_set_active((int) ($_POST['id'] ?? 0), (int) ($_POST['on'] ?? 0));
            flash((int) ($_POST['on'] ?? 0) ? 'Department switched back on.' : 'Department switched off. Nothing recorded against it has been lost.');
            redirect('/departments?tab=manage'); return true;
        }
        if ($do === 'term-add') {
            [$ok, $msg] = vocab_term_add('department', (int) ($_POST['id'] ?? 0), (string) ($_POST['term'] ?? ''),
                                         (string) ($_POST['term_type'] ?? 'SYNONYM'));
            flash($msg, $ok ? 'success' : 'error');
            redirect('/departments?tab=manage&edit=' . (int) ($_POST['id'] ?? 0)); return true;
        }
        if ($do === 'term-del')     { vocab_term_delete((int) ($_POST['term_id'] ?? 0)); flash('Term removed.'); redirect('/departments?tab=manage&edit=' . (int) ($_POST['id'] ?? 0)); return true; }
        if ($do === 'term-approve') { [$ok, $msg] = vocab_term_approve((int) ($_POST['term_id'] ?? 0), (int) ($_POST['value_id'] ?? 0)); flash($msg, $ok ? 'success' : 'error'); redirect('/departments?tab=review'); return true; }
        if ($do === 'term-reject')  { vocab_term_reject((int) ($_POST['term_id'] ?? 0)); flash('Term rejected.'); redirect('/departments?tab=review'); return true; }
    }

    $tab = (string) ($_GET['tab'] ?? 'hub');
    if ($tab === 'manage' || $tab === 'review') {
        $editId  = (int) ($_GET['edit'] ?? 0);
        $editing = $editId > 0 ? vocab_value($editId) : null;
        if ($editing && (int) $editing['type_id'] !== vocab_type_id('department')) $editing = null;
        view('ops/department_admin', [
            'tab'      => $tab,
            'mayEdit'  => $mayEdit,
            'tree'     => dept_tree(false),
            'editing'  => $editing,
            'terms'    => $editing ? vocab_terms('department', (int) $editing['id'], '') : [],
            'pending'  => vocab_terms('department', 0, 'PENDING'),
            'dupe'     => ((int) ($_GET['dupe'] ?? 0)) ? vocab_value((int) $_GET['dupe']) : null,
            'all'      => vocab_values('department', false),
        ]);
        return true;
    }
    $hub = dept_hub();
    view('ops/departments', ['hub' => $hub, 'deptNames' => dept_names(), 'mayEdit' => $mayEdit,
                             'pendingCount' => count(vocab_terms('department', 0, 'PENDING'))]);
    return true;
}

// ============================================================================
//  DEPARTMENT AS A CANONICAL VOCABULARY  (Phase 2 · M3)
//
//  The department master remains where it has always been: lookup_values of
//  type "department". lookup_values.id is the canonical Department identity and
//  never changes when the wording does. What M3 adds on top is the approved-term
//  layer from lib/vocab.php, so a customer's own terminology resolves to that
//  identity instead of creating another department.
//
//  Nothing stored is rewritten, and no value from the legacy hr_department list
//  is merged into a canonical department by guesswork — §16. Where the two lists
//  genuinely agree the terms already match; where they do not, the term is
//  recorded for a person to decide.
// ============================================================================

const DEPT_TYPES = [
    'CORPORATE'=>'Corporate', 'SUPPORT'=>'Support', 'COMMERCIAL'=>'Commercial',
    'OPERATIONAL'=>'Operational', 'TECHNICAL'=>'Technical', 'QUALITY'=>'Quality',
    'SAFETY'=>'Safety', 'PROJECT'=>'Project', 'SERVICE'=>'Service', 'OTHER'=>'Other',
];

// Register each department's own code and label as approved terms, and record
// every legacy hiring-department value that does NOT already resolve, as a
// PENDING term for an authorised person to decide on. Never merges.
function dept_vocab_seed() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (!function_exists('vocab_migrate')) return;
    vocab_migrate();
    $tid = vocab_type_id('department'); if (!$tid) return;
    // Self-healing, not first-run-only: a department can still be added through
    // Settings → Masters or the organogram importer, neither of which knows about
    // terms. Any department without a canonical term of its own would be listed
    // but unmatchable, and would show its raw code — the exact defect this
    // milestone exists to remove. So reconcile every time, on one query.
    try {
        $orphans = ops_all("SELECT v.id FROM lookup_values v
                            WHERE v.type_id=? AND NOT EXISTS (
                                SELECT 1 FROM lookup_terms t
                                WHERE t.type_id=v.type_id AND t.value_id=v.id AND t.term_type='CANONICAL')", [$tid]);
    } catch (Throwable $e) { return; }
    foreach ($orphans as $o) vocab_register_canonical('department', (int) $o['id']);

    // The legacy hiring-department list stays exactly where it is. Its values are
    // only offered up for a decision — INSPECTION and ENGINEERING already resolve
    // on their own, so only the genuinely unrecognised ones are recorded.
    // The decided reconciliation runs first, so a word that now HAS an answer is
    // never presented as a question.
    if (function_exists('dept_apply_legacy_decisions') && !dept_legacy_applied()) dept_apply_legacy_decisions();

    foreach (vocab_values('hr_department', false) as $v) {
        foreach ([(string) $v['code'], (string) $v['label']] as $term) {
            if (trim($term) === '') continue;
            if (vocab_resolve('department', $term)) continue;
            vocab_term_suggest('department', $term);
        }
    }
}

// Every stored department string — a code, a label, a legacy hiring code or free
// text — resolved to its canonical Department row, or null when nothing approved
// matches it. Read-only: it never writes, never creates and never merges.
// Memoised for the life of one request, and only for as long as the database it
// was read from is still the current one — the M2 rule. Without this, rendering a
// list of departments costs one query per row.
function dept_of($stored) {
    $s = trim((string) $stored); if ($s === '') return null;
    if (!function_exists('vocab_resolve')) return null;
    static $memo = [], $at = -1;
    if ($at !== db_epoch()) { $memo = []; $at = db_epoch(); }
    if (array_key_exists($s, $memo)) return $memo[$s];
    dept_vocab_seed();
    return $memo[$s] = vocab_resolve('department', $s);
}

// What to SHOW for a stored department: the customer's own wording where they
// have set one, else the canonical label, else the legacy list's label, else the
// value exactly as stored. Nothing is ever lost or silently renamed.
function dept_label($stored) {
    $s = trim((string) $stored); if ($s === '') return '';
    $v = dept_of($s);
    if ($v) return vocab_display($v);
    // Still readable for a value only the legacy hiring list knows.
    if (function_exists('lk_options_or')) {
        $hr = lk_options_or('hr_department', defined('RCC_DEPARTMENTS') ? RCC_DEPARTMENTS : []);
        if (isset($hr[$s])) return (string) $hr[$s];
        if (isset($hr[strtoupper($s)])) return (string) $hr[strtoupper($s)];
    }
    return $s;
}

// ---- Hierarchy -------------------------------------------------------------
// parent_value_id already existed on lookup_values; M3 only adds the guard that
// a department cannot be its own ancestor.
function dept_would_loop($valueId, $parentId) {
    $valueId = (int) $valueId; $parentId = (int) $parentId;
    if ($valueId <= 0 || $parentId <= 0) return false;
    if ($valueId === $parentId) return true;
    $seen = []; $cur = $parentId;
    while ($cur > 0 && !isset($seen[$cur])) {
        $seen[$cur] = true;
        if ($cur === $valueId) return true;
        $row = vocab_value($cur);
        $cur = $row ? (int) ($row['parent_value_id'] ?? 0) : 0;
    }
    return false;
}

function dept_tree($activeOnly = true) {
    $rows = vocab_values('department', $activeOnly);
    $byParent = [];
    foreach ($rows as $r) $byParent[(int) ($r['parent_value_id'] ?? 0)][] = $r;
    $out = [];
    $walk = function ($pid, $depth) use (&$walk, &$out, $byParent) {
        foreach ($byParent[$pid] ?? [] as $r) { $r['depth'] = $depth; $out[] = $r; $walk((int) $r['id'], $depth + 1); }
    };
    $walk(0, 0);
    return $out;
}

// ---- Canonical create / update ---------------------------------------------
// Returns [ok, message, valueId]. Refuses a duplicate code, a self-parent and a
// circular hierarchy. A near-duplicate NAME is reported to the caller as a
// warning, never silently blocked — a customer may legitimately need two
// similarly named departments (§32).
function dept_save($valueId, array $post, $allowNearDuplicate = false) {
    dept_vocab_seed();
    $tid = vocab_type_id('department');
    if (!$tid) return [false, 'The department master is not set up for this workspace.', 0];
    $valueId = (int) $valueId;
    $name = trim((string) ($post['label'] ?? ''));
    if ($name === '') return [false, 'Give the department a name.', 0];
    $code = strtoupper(trim((string) ($post['code'] ?? '')));
    if ($code === '') $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 12));
    if ($code === '') $code = 'DEPT' . substr(md5($name), 0, 4);

    $dupe = ops_one("SELECT * FROM lookup_values WHERE type_id=? AND UPPER(code)=? AND id<>?", [$tid, $code, $valueId]);
    if ($dupe) return [false, 'The code “' . $code . '” is already used by ' . vocab_display($dupe) . '.', 0];

    $parent = (int) ($post['parent_value_id'] ?? 0) ?: null;
    if ($parent && $valueId && dept_would_loop($valueId, $parent))
        return [false, 'That would put the department inside itself.', 0];
    if ($parent && $parent === $valueId) return [false, 'A department cannot report to itself.', 0];

    if (!$valueId && !$allowNearDuplicate) {
        $near = vocab_duplicate_check('department', $name, $code);
        if ($near) {
            $first = $near[0];
            return [false, 'This looks like ' . vocab_display($first['value']) . ' — ' . $first['why']
                . ' Use that one, or confirm you want a separate department.', -(int) $first['value']['id']];
        }
    }

    $cols = [
        'label'          => $name,
        'code'           => $code,
        'display_name'   => trim((string) ($post['display_name'] ?? '')),
        'description'    => trim((string) ($post['description'] ?? '')),
        'attr_type'      => strtoupper(trim((string) ($post['attr_type'] ?? ''))),
        'attr_owner_id'  => (int) ($post['attr_owner_id'] ?? 0) ?: null,
        'effective_from' => trim((string) ($post['effective_from'] ?? '')),
        'effective_to'   => trim((string) ($post['effective_to'] ?? '')),
        'external_ref'   => trim((string) ($post['external_ref'] ?? '')),
        'parent_value_id'=> $parent,
        'sort_order'     => (int) ($post['sort_order'] ?? 0),
        'active'         => isset($post['active']) ? (int) !!$post['active'] : 1,
    ];
    $now = vocab_now(); $who = vocab_who();
    if ($valueId > 0) {
        $set = implode(',', array_map(fn($c) => "$c=?", array_keys($cols)));
        db()->prepare("UPDATE lookup_values SET $set, updated_by=?, updated_at=? WHERE id=? AND type_id=?")
            ->execute([...array_values($cols), $who, $now, $valueId, $tid]);
    } else {
        $keys = array_keys($cols);
        $ph = implode(',', array_fill(0, count($keys) + 5, '?'));   // type_id + cols + source,created_at,updated_by,updated_at
        db()->prepare("INSERT INTO lookup_values (type_id," . implode(',', $keys) . ",source,created_at,updated_by,updated_at) VALUES ($ph)")
            ->execute([$tid, ...array_values($cols), 'CUSTOMER', $now, $who, $now]);
        $valueId = (int) db()->lastInsertId();
    }
    vocab_register_canonical('department', $valueId);
    return [true, $valueId ? 'Department saved.' : 'Department created.', $valueId];
}

function dept_set_active($valueId, $on) {
    dept_vocab_seed();
    // Deactivating never deletes: historical records keep pointing at the same
    // canonical identity, exactly as §31 requires.
    db()->prepare("UPDATE lookup_values SET active=?, updated_by=?, updated_at=? WHERE id=?")
        ->execute([(int) !!$on, vocab_who(), vocab_now(), (int) $valueId]);
}

// ---- The canonical Department on a form ------------------------------------
// One picker for every screen that files something under a department. Offers
// the canonical master, indented by hierarchy, and — crucially — keeps showing
// a legacy value a record already holds, so opening an old requisition never
// silently blanks its department.
//
// Returns [value => label]; the value is the canonical CODE, which is what the
// existing free-text column has always held.
function dept_form_options($currentStored = '') {
    dept_vocab_seed();
    $out = [];
    foreach (dept_tree(true) as $d) {
        $out[(string) $d['code']] = str_repeat('— ', (int) ($d['depth'] ?? 0)) . vocab_display($d);
    }
    $cur = trim((string) $currentStored);
    if ($cur !== '' && !isset($out[$cur])) {
        // A value from before the canonical master, or from the legacy hiring
        // list. Keep it selectable and readable rather than losing it.
        $v = dept_of($cur);
        $out[$cur] = $v ? vocab_display($v) : dept_label($cur) . ' (existing)';
    }
    return $out;
}

// What a form POST means for the department: the canonical identity AND the
// code to keep in the free-text column, so no existing reader breaks.
// Returns ['department' => code-or-original, 'department_id' => int|null].
function dept_form_save(array $post, $field = 'department') {
    $raw = trim((string) ($post[$field] ?? ''));
    if ($raw === '') return ['department' => '', 'department_id' => null];
    $v = dept_of($raw);
    if (!$v) return ['department' => $raw, 'department_id' => null];   // unknown wording is preserved, never invented
    return ['department' => (string) ($v['code'] !== '' ? $v['code'] : $raw), 'department_id' => (int) $v['id']];
}

// The department of a record that may carry the identity, the legacy text, or
// both. The identity wins; the text is the fallback for everything written
// before M3. Returns the canonical row or null.
function dept_of_row($row, $idField = 'department_id', $textField = 'department') {
    $id = (int) ($row[$idField] ?? 0);
    if ($id > 0) { $v = vocab_value($id); if ($v) return $v; }
    return dept_of((string) ($row[$textField] ?? ''));
}

// …and its display name, for a list, an export or a letter.
function dept_row_label($row, $idField = 'department_id', $textField = 'department') {
    $v = dept_of_row($row, $idField, $textField);
    if ($v) return vocab_display($v);
    return dept_label((string) ($row[$textField] ?? ''));
}

// ---- The decided reconciliation of the legacy hiring list ------------------
//  EXAACT ships two department lists: the canonical master, and an older
//  "hiring department" list that the requisition and candidate forms used to
//  offer. Five of its values had no equivalent in the master, and the vocabulary
//  work deliberately refused to guess what they meant — each was recorded as a
//  word awaiting a decision.
//
//  Those decisions have now been taken:
//
//      QA / QC   is  Quality
//      HSE       is  Safety / HSE
//      Finance   is  Commercial / Finance
//      NDT       is  a department in its own right
//      HR        is  a department in its own right
//
//  This belongs in the product rather than in one workspace's data, because BOTH
//  lists ship with the product — it reconciles EXAACT's own vocabulary with
//  itself. A word a CUSTOMER has added is still theirs to decide.
//
//  Three rules this obeys without exception:
//    · it runs ONCE per workspace, and never again once a person has touched it;
//    · it only ever acts on a word still AWAITING a decision — a mapping the
//      customer has already approved, rejected or changed is left exactly alone;
//    · it rewrites no stored department value. Every requisition and candidate
//      keeps the text it has; the mapping is what makes that text resolve.
//
//  Undoing any of it is removing one term, or switching one department off.
const DEPT_LEGACY_DECISIONS = [
    // legacy hr_department code => where it goes
    'QAQC'    => ['to' => 'QUALITY'],
    'HSE'     => ['to' => 'SAFETY'],
    'FINANCE' => ['to' => 'COMMERCIAL'],
    'NDT'     => ['create' => ['code' => 'NDT', 'label' => 'NDT',              'attr_type' => 'TECHNICAL',
                               'description' => 'Non-destructive testing']],
    'HR'      => ['create' => ['code' => 'HR',  'label' => 'Human Resources',  'attr_type' => 'SUPPORT',
                               'description' => 'People, hiring and employment']],
];

function dept_legacy_applied() {
    return function_exists('setting_get') && (string) setting_get('dept_legacy_decisions_applied', '') !== '';
}

// Apply the decisions. Returns a per-word report: ['QAQC' => 'mapped to Quality', …].
function dept_apply_legacy_decisions($force = false) {
    dept_vocab_seed();
    $report = [];
    if (!$force && dept_legacy_applied()) return $report;
    if (!function_exists('vocab_term_add')) return $report;

    foreach (DEPT_LEGACY_DECISIONS as $legacyCode => $rule) {
        // Which canonical department does this word belong to?
        $target = null;
        if (!empty($rule['to'])) {
            $target = vocab_resolve('department', $rule['to']);
            if (!$target) { $report[$legacyCode] = 'skipped — ' . $rule['to'] . ' is not in this workspace'; continue; }
        } else {
            $spec = $rule['create'];
            // Never create a second department for a code that already exists.
            $target = vocab_resolve('department', $spec['code']) ?: vocab_resolve('department', $spec['label']);
            if (!$target) {
                [$ok, $msg, $id] = dept_save(0, $spec, true);
                if (!$ok) { $report[$legacyCode] = 'could not be created — ' . $msg; continue; }
                $target = vocab_value($id);
                $report[$legacyCode] = 'created as a department';
            } else {
                $report[$legacyCode] = 'already a department';
            }
        }

        // Every wording this legacy value is known by — its code and its label.
        $words = [$legacyCode];
        if (function_exists('lk_options_or')) {
            $hr = lk_options_or('hr_department', defined('RCC_DEPARTMENTS') ? RCC_DEPARTMENTS : []);
            if (isset($hr[$legacyCode])) $words[] = (string) $hr[$legacyCode];
        }
        $done = [];
        foreach (array_unique($words) as $w) {
            if (trim($w) === '') continue;
            // Already decided by a person? Leave it exactly as it is.
            $already = vocab_resolve('department', $w);
            if ($already) { $done[] = (int) $already['id'] === (int) $target['id'] ? 'already mapped' : 'left as the customer set it'; continue; }
            [$ok, $msg] = vocab_term_add('department', (int) $target['id'], $w,
                                         strtoupper($w) === $w ? 'ABBREVIATION' : 'LEGACY');
            $done[] = $ok ? 'mapped' : ('refused — ' . $msg);
            // Clear the pending question now it is answered.
            if ($ok) {
                try {
                    db()->prepare("DELETE FROM lookup_terms WHERE type_id=? AND term_norm=? AND status='PENDING'")
                        ->execute([vocab_type_id('department'), vocab_norm($w)]);
                } catch (Throwable $e) {}
            }
        }
        $summary = 'to ' . vocab_display($target) . ' (' . implode(', ', $done) . ')';
        $report[$legacyCode] = isset($report[$legacyCode]) ? $report[$legacyCode] . ', ' . $summary : $summary;
    }
    if (function_exists('setting_set')) setting_set('dept_legacy_decisions_applied', vocab_now());
    return $report;
}
