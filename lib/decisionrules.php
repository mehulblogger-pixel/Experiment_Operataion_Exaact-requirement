<?php
// ============================================================================
//  Decision rules & acceptance criteria — ISO/IEC 17020 §7.4 / 17025 §7.8.6
//
//  The last operational Clause-7 gap: a report's conclusion (accept / reject)
//  should rest on a STATED, controlled rule — what characteristic is judged,
//  the pass/fail criteria, and how measurement uncertainty is treated — not on
//  free-text opinion. This is the controlled register of those rules.
//
//  A rule links to the method it belongs to (from the §7.1 library) and, if
//  useful, to a report type. Reissue is versioned like the method library: a new
//  revision supersedes the old, history intact.
//
//  Configuration-first: the rule "basis" list is an editable Master; extra data
//  goes on as a custom field (entity 'decision_rule'); the register is pack-gated.
// ============================================================================

const DRULE_STATUSES = ['DRAFT', 'CURRENT', 'SUPERSEDED', 'WITHDRAWN'];

function drules_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS decision_rules (
            id $pk, rule_code VARCHAR(30) DEFAULT '', title VARCHAR(240) DEFAULT '', method_id INT NULL,
            report_type_id INT NULL, characteristic VARCHAR(200) DEFAULT '', accept_criteria TEXT,
            reject_criteria TEXT, uncertainty_rule VARCHAR(300) DEFAULT '', decision_basis VARCHAR(120) DEFAULT '',
            status VARCHAR(20) DEFAULT 'DRAFT', owner VARCHAR(150) DEFAULT '', notes TEXT,
            supersedes_id INT NULL, superseded_by_id INT NULL, created_by VARCHAR(150) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) { /* never let a table add break boot */ }

    if (function_exists('lk_ensure_type_map')) {
        try {
            lk_ensure_type_map('drule_basis', 'Decision-rule basis', [
                'SIMPLE' => 'Simple acceptance (no guard band)',
                'GUARD_ADD' => 'Guarded acceptance — w = U',
                'GUARD_REJ' => 'Guarded rejection — w = U',
                'CUSTOMER' => 'Per customer / spec statement',
                'STANDARD' => 'As stated in the standard',
            ], 'Operations');
        } catch (Throwable $e) { /* masters are a convenience, not load-bearing */ }
    }
}

function drule_pack_on() { return !function_exists('accredited_pack_on') || accredited_pack_on(); }
function drule_can_view() {
    if (!drule_pack_on()) return false;
    return is_master() || (function_exists('can') && (can('mod.equipment.view') || can('mod.competence.view')));
}
function drule_can_manage() {
    if (!drule_pack_on()) return false;
    return is_master() || (function_exists('can') && can('mod.competence.view'))
        || (function_exists('is_coordinator_level') && is_coordinator_level());
}

function drule_opts($key) {
    $t = function_exists('lk_type') ? lk_type($key) : null;
    if (!$t) return [];
    $out = [];
    foreach (ops_all("SELECT code, label FROM lookup_values WHERE type_id=? AND active=1 ORDER BY sort_order, label", [$t['id']]) ?: [] as $r)
        $out[$r['code']] = $r['label'];
    return $out;
}
function drule_basis_label($code) { $o = drule_opts('drule_basis'); return $o[$code] ?? ($code ?: '—'); }

// Current methods, for the "belongs to method" picker — reuses the §7.1 library.
function drule_method_choices() {
    if (!function_exists('methods_all')) return [];
    $out = [];
    foreach (methods_all(['current' => 1]) as $m) $out[(int)$m['id']] = $m['method_code'] . ' — ' . $m['title'];
    return $out;
}

function drules_all($filter = []) {
    drules_migrate();
    $where = '1=1'; $args = [];
    if (!empty($filter['current'])) $where .= " AND status='CURRENT'";
    if (!empty($filter['method_id'])) { $where .= " AND method_id=?"; $args[] = (int)$filter['method_id']; }
    if (!empty($filter['q'])) { $where .= " AND (rule_code LIKE ? OR title LIKE ? OR characteristic LIKE ?)";
        $like = '%' . $filter['q'] . '%'; array_push($args, $like, $like, $like); }
    return ops_all("SELECT * FROM decision_rules WHERE $where ORDER BY title, id DESC", $args) ?: [];
}
function drule_get($id) { drules_migrate(); return ops_one("SELECT * FROM decision_rules WHERE id=?", [(int)$id]); }
function drule_counts() { drules_migrate(); return ['current' => (int)ops_val("SELECT COUNT(*) FROM decision_rules WHERE status='CURRENT'")]; }

function drule_next_code() {
    $n = (int)ops_val("SELECT COUNT(*) FROM decision_rules WHERE supersedes_id IS NULL") + 1;
    do { $code = sprintf('DR-%04d', $n); $n++; }
    while (ops_val("SELECT COUNT(*) FROM decision_rules WHERE rule_code=?", [$code]) > 0);
    return $code;
}

function drule_save($b, $id = 0) {
    drules_migrate();
    $title = trim((string)($b['title'] ?? ''));
    if ($title === '') return [0, 'A title is required.'];
    $cols = [
        'title'            => substr($title, 0, 240),
        'method_id'        => ($b['method_id'] ?? '') !== '' ? (int)$b['method_id'] : null,
        'report_type_id'   => ($b['report_type_id'] ?? '') !== '' ? (int)$b['report_type_id'] : null,
        'characteristic'   => substr(trim((string)($b['characteristic'] ?? '')), 0, 200),
        'accept_criteria'  => trim((string)($b['accept_criteria'] ?? '')),
        'reject_criteria'  => trim((string)($b['reject_criteria'] ?? '')),
        'uncertainty_rule' => substr(trim((string)($b['uncertainty_rule'] ?? '')), 0, 300),
        'decision_basis'   => substr(trim((string)($b['decision_basis'] ?? '')), 0, 120),
        'owner'            => substr(trim((string)($b['owner'] ?? '')), 0, 150),
        'notes'            => trim((string)($b['notes'] ?? '')),
    ];
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($cols)));
        db()->prepare("UPDATE decision_rules SET $set, updated_at=? WHERE id=?")
            ->execute(array_merge(array_values($cols), [date('c'), (int)$id]));
        if (function_exists('custom_save')) custom_save('decision_rule', (int)$id, $b);
        return [(int)$id, ''];
    }
    $cols['rule_code']  = drule_next_code();
    $cols['status']     = 'DRAFT';
    $cols['created_by'] = user_name(current_user());
    $cols['created_at'] = date('c');
    $cols['updated_at'] = date('c');
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO decision_rules (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    $newId = (int)db()->lastInsertId();
    if (function_exists('custom_save')) custom_save('decision_rule', $newId, $b);
    if (function_exists('idems_log')) try { idems_log('decision_rule', $newId, 'CREATED', ['reason' => $cols['title']]); } catch (Throwable $e) {}
    return [$newId, ''];
}

function drule_set_status($id, $status) {
    $r = drule_get($id);
    if (!$r || !in_array($status, DRULE_STATUSES, true)) return 'That status is not valid.';
    db()->prepare("UPDATE decision_rules SET status=?, updated_at=? WHERE id=?")->execute([$status, date('c'), (int)$id]);
    if (function_exists('idems_log')) try { idems_log('decision_rule', (int)$id, 'STATUS_' . $status); } catch (Throwable $e) {}
    return '';
}

function drule_supersede($id) {
    $r = drule_get($id);
    if (!$r) return [0, 'Rule not found.'];
    $cols = ['rule_code' => $r['rule_code'], 'title' => $r['title'], 'method_id' => $r['method_id'],
             'report_type_id' => $r['report_type_id'], 'characteristic' => $r['characteristic'],
             'accept_criteria' => $r['accept_criteria'], 'reject_criteria' => $r['reject_criteria'],
             'uncertainty_rule' => $r['uncertainty_rule'], 'decision_basis' => $r['decision_basis'],
             'status' => 'DRAFT', 'owner' => $r['owner'], 'notes' => $r['notes'], 'supersedes_id' => (int)$r['id'],
             'created_by' => user_name(current_user()), 'created_at' => date('c'), 'updated_at' => date('c')];
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO decision_rules (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    $newId = (int)db()->lastInsertId();
    db()->prepare("UPDATE decision_rules SET status='SUPERSEDED', superseded_by_id=?, updated_at=? WHERE id=?")
        ->execute([$newId, date('c'), (int)$r['id']]);
    return [$newId, ''];
}

// ---- Routing -------------------------------------------------------------
function ops_drules($route, $method) {
    drules_migrate();
    ops_require(drule_can_view(), 'The decision-rule register is not available to you.');
    $canEdit = drule_can_manage();

    if ($route === 'drules') {
        $filter = ['q' => trim((string)($_GET['q'] ?? ''))];
        if (($_GET['show'] ?? 'current') === 'current') $filter['current'] = 1;
        view('ops/drules_list', ['rows' => drules_all($filter), 'canEdit' => $canEdit,
            'showAll' => ($_GET['show'] ?? 'current') !== 'current', 'q' => $filter['q'], 'counts' => drule_counts()]);
        return true;
    }
    if ($route === 'drule') {
        $r = drule_get($_GET['id'] ?? 0);
        if (!$r) { http_response_code(404); view('notfound'); return true; }
        $meth = $r['method_id'] && function_exists('method_get') ? method_get($r['method_id']) : null;
        view('ops/drule_detail', ['r' => $r, 'canEdit' => $canEdit, 'method' => $meth,
            'supersededBy' => $r['superseded_by_id'] ? drule_get($r['superseded_by_id']) : null,
            'cvals' => function_exists('custom_display') ? custom_display('decision_rule', $r['id']) : []]);
        return true;
    }

    ops_require($canEdit, 'You cannot change the decision-rule register.');

    if ($route === 'drule-new' || $route === 'drule-edit') {
        $r = $route === 'drule-edit' ? drule_get($_GET['id'] ?? 0) : null;
        if ($route === 'drule-edit' && !$r) { http_response_code(404); view('notfound'); return true; }
        if ($method === 'POST') {
            [$rid, $err] = drule_save($_POST, $r['id'] ?? 0);
            if ($err) { flash($err, 'error'); redirect($r ? '/drule-edit?id=' . $r['id'] : '/drule-new'); }
            flash($r ? 'Rule updated.' : 'Rule added as a draft. Set it Current when in force.');
            redirect('/drule?id=' . $rid);
        }
        view('ops/drule_form', ['r' => $r, 'bases' => drule_opts('drule_basis'),
            'methods' => drule_method_choices(),
            'cfields' => function_exists('custom_fields_for') ? custom_fields_for('decision_rule') : [],
            'cvals' => $r && function_exists('custom_values_map') ? custom_values_map('decision_rule', $r['id']) : []]);
        return true;
    }
    if ($route === 'drule-status' && $method === 'POST') {
        $err = drule_set_status((int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''));
        flash($err ?: 'Status updated.', $err ? 'error' : 'success');
        redirect('/drule?id=' . (int)($_POST['id'] ?? 0));
    }
    if ($route === 'drule-supersede' && $method === 'POST') {
        [$newId, $err] = drule_supersede((int)($_POST['id'] ?? 0));
        if ($err) { flash($err, 'error'); redirect('/drule?id=' . (int)($_POST['id'] ?? 0)); }
        flash('New revision created as a draft.');
        redirect('/drule?id=' . $newId);
    }
    http_response_code(404); view('notfound'); return true;
}
