<?php
// ============================================================================
//  Risks & opportunities — ISO/IEC 17020 §8.5 (actions to address risk)
//
//  A general register of the risks and opportunities to the management system —
//  distinct from the impartiality threat register (§4.1), which covers only
//  conflicts of interest. Each entry carries a likelihood, an impact, a derived
//  rating, the treatment (what is being done), an owner, and a status through to
//  closure, with a review date so it does not go stale.
//
//  Configuration-first: the category is an editable Master; extra data via
//  custom fields (entity 'risk'); the register is pack-gated.
// ============================================================================

const RISK_KINDS    = ['RISK', 'OPPORTUNITY'];
const RISK_STATUSES = ['OPEN', 'TREATING', 'MONITORING', 'CLOSED'];
const RISK_OPEN     = ['OPEN', 'TREATING', 'MONITORING'];
const RISK_LEVELS   = ['L' => 'Low', 'M' => 'Medium', 'H' => 'High'];

function risks_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS risk_items (
            id $pk, risk_code VARCHAR(30) DEFAULT '', kind VARCHAR(20) DEFAULT 'RISK', title VARCHAR(240) DEFAULT '',
            category VARCHAR(40) DEFAULT '', context VARCHAR(200) DEFAULT '', description TEXT,
            likelihood VARCHAR(2) DEFAULT '', impact VARCHAR(2) DEFAULT '', rating VARCHAR(20) DEFAULT '',
            treatment TEXT, owner VARCHAR(150) DEFAULT '', status VARCHAR(20) DEFAULT 'OPEN',
            review_due VARCHAR(20) DEFAULT '', closed_on VARCHAR(20) DEFAULT '', closed_by VARCHAR(150) DEFAULT '',
            closed_note VARCHAR(300) DEFAULT '', office_id INT NULL, sbu VARCHAR(40) DEFAULT '',
            created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) { /* never let a table add break boot */ }

    if (function_exists('lk_ensure_type_map')) {
        try {
            lk_ensure_type_map('risk_category', 'Risk / opportunity category', [
                'IMPARTIALITY' => 'Impartiality', 'COMPETENCE' => 'Competence / resource',
                'OPERATIONAL' => 'Operational / delivery', 'TECHNICAL' => 'Technical / method',
                'CLIENT' => 'Client / market', 'FINANCIAL' => 'Financial', 'LEGAL' => 'Legal / compliance',
                'INFOSEC' => 'Information / data', 'SAFETY' => 'Health & safety', 'OTHER' => 'Other',
            ], 'Operations');
        } catch (Throwable $e) { /* masters are a convenience */ }
    }
}

function risk_pack_on() { return !function_exists('accredited_pack_on') || accredited_pack_on(); }
function risk_can_view() {
    if (!risk_pack_on()) return false;
    return is_master() || (function_exists('can') && (can('mod.impartiality.view') || can('mod.audits.view')
        || can('mod.datacontrol.view')));
}
function risk_can_manage() {
    if (!risk_pack_on()) return false;
    return is_master() || (function_exists('can') && (can('mod.impartiality.view') || can('mod.audits.view')));
}

function risk_opts($key) {
    $t = function_exists('lk_type') ? lk_type($key) : null;
    if (!$t) return [];
    $out = [];
    foreach (ops_all("SELECT code, label FROM lookup_values WHERE type_id=? AND active=1 ORDER BY sort_order, label", [$t['id']]) ?: [] as $r)
        $out[$r['code']] = $r['label'];
    return $out;
}
function risk_cat_label($code) { $o = risk_opts('risk_category'); return $o[$code] ?? ($code ?: '—'); }

// Likelihood × impact → a plain rating band (3×3 grid).
function risk_rate($l, $i) {
    $n = ['L' => 1, 'M' => 2, 'H' => 3];
    $p = ($n[$l] ?? 0) * ($n[$i] ?? 0);
    if ($p <= 0) return '';
    if ($p <= 2) return 'LOW';
    if ($p <= 4) return 'MEDIUM';
    if ($p <= 6) return 'HIGH';
    return 'CRITICAL';
}

function risks_all($filter = []) {
    risks_migrate();
    $where = '1=1'; $args = [];
    if (!empty($filter['open']))   $where .= " AND status IN ('" . implode("','", RISK_OPEN) . "')";
    if (!empty($filter['kind']))   { $where .= " AND kind=?"; $args[] = $filter['kind']; }
    if (!empty($filter['q'])) { $where .= " AND (risk_code LIKE ? OR title LIKE ? OR context LIKE ?)";
        $like = '%' . $filter['q'] . '%'; array_push($args, $like, $like, $like); }
    return ops_all("SELECT * FROM risk_items WHERE $where ORDER BY id DESC", $args) ?: [];
}
function risk_get($id) { risks_migrate(); return ops_one("SELECT * FROM risk_items WHERE id=?", [(int)$id]); }
function risk_counts() {
    risks_migrate();
    return ['open' => (int)ops_val("SELECT COUNT(*) FROM risk_items WHERE status IN ('" . implode("','", RISK_OPEN) . "')"),
            'high' => (int)ops_val("SELECT COUNT(*) FROM risk_items WHERE status IN ('" . implode("','", RISK_OPEN) . "') AND rating IN ('HIGH','CRITICAL')")];
}
function risk_next_code() {
    $n = (int)ops_val("SELECT COUNT(*) FROM risk_items") + 1;
    do { $code = sprintf('RSK-%04d', $n); $n++; }
    while (ops_val("SELECT COUNT(*) FROM risk_items WHERE risk_code=?", [$code]) > 0);
    return $code;
}

function risk_save($b, $id = 0) {
    risks_migrate();
    $title = trim((string)($b['title'] ?? ''));
    if ($title === '') return [0, 'A title is required.'];
    $l = in_array($b['likelihood'] ?? '', ['L', 'M', 'H'], true) ? $b['likelihood'] : '';
    $i = in_array($b['impact'] ?? '', ['L', 'M', 'H'], true) ? $b['impact'] : '';
    $cols = [
        'kind'        => in_array($b['kind'] ?? '', RISK_KINDS, true) ? $b['kind'] : 'RISK',
        'title'       => substr($title, 0, 240),
        'category'    => substr(trim((string)($b['category'] ?? '')), 0, 40),
        'context'     => substr(trim((string)($b['context'] ?? '')), 0, 200),
        'description' => trim((string)($b['description'] ?? '')),
        'likelihood'  => $l,
        'impact'      => $i,
        'rating'      => risk_rate($l, $i),
        'treatment'   => trim((string)($b['treatment'] ?? '')),
        'owner'       => substr(trim((string)($b['owner'] ?? '')), 0, 150),
        'review_due'  => substr(trim((string)($b['review_due'] ?? '')), 0, 20),
    ];
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($cols)));
        db()->prepare("UPDATE risk_items SET $set, updated_at=? WHERE id=?")
            ->execute(array_merge(array_values($cols), [date('c'), (int)$id]));
        if (function_exists('custom_save')) custom_save('risk', (int)$id, $b);
        return [(int)$id, ''];
    }
    $cols['risk_code']  = risk_next_code();
    $cols['status']     = 'OPEN';
    $cols['created_by'] = user_name(current_user());
    $cols['created_at'] = date('c');
    $cols['updated_at'] = date('c');
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO risk_items (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    $newId = (int)db()->lastInsertId();
    if (function_exists('custom_save')) custom_save('risk', $newId, $b);
    if (function_exists('idems_log')) try { idems_log('risk', $newId, 'CREATED', ['reason' => $cols['title']]); } catch (Throwable $e) {}
    return [$newId, ''];
}

function risk_set_status($id, $status, $note = '') {
    $r = risk_get($id);
    if (!$r || !in_array($status, RISK_STATUSES, true)) return 'That status is not valid.';
    if ($status === 'CLOSED') {
        db()->prepare("UPDATE risk_items SET status=?, closed_on=?, closed_by=?, closed_note=?, updated_at=? WHERE id=?")
            ->execute([$status, date('Y-m-d'), user_name(current_user()), substr($note, 0, 300), date('c'), (int)$id]);
    } else {
        db()->prepare("UPDATE risk_items SET status=?, updated_at=? WHERE id=?")->execute([$status, date('c'), (int)$id]);
    }
    if (function_exists('idems_log')) try { idems_log('risk', (int)$id, 'STATUS_' . $status, ['reason' => $note]); } catch (Throwable $e) {}
    return '';
}

// ---- Routing -------------------------------------------------------------
function ops_risks($route, $method) {
    risks_migrate();
    ops_require(risk_can_view(), 'The risk register is not available to you.');
    $canEdit = risk_can_manage();

    if ($route === 'risks') {
        $filter = ['q' => trim((string)($_GET['q'] ?? ''))];
        if (($_GET['show'] ?? 'open') !== 'all') $filter['open'] = 1;
        if (in_array($_GET['kind'] ?? '', RISK_KINDS, true)) $filter['kind'] = $_GET['kind'];
        view('ops/risks_list', ['rows' => risks_all($filter), 'canEdit' => $canEdit,
            'showAll' => ($_GET['show'] ?? 'open') === 'all', 'kind' => $_GET['kind'] ?? '',
            'q' => $filter['q'], 'counts' => risk_counts()]);
        return true;
    }
    if ($route === 'risk') {
        $r = risk_get($_GET['id'] ?? 0);
        if (!$r) { http_response_code(404); view('notfound'); return true; }
        view('ops/risk_detail', ['r' => $r, 'canEdit' => $canEdit,
            'cvals' => function_exists('custom_display') ? custom_display('risk', $r['id']) : []]);
        return true;
    }

    ops_require($canEdit, 'You cannot change the risk register.');

    if ($route === 'risk-new' || $route === 'risk-edit') {
        $r = $route === 'risk-edit' ? risk_get($_GET['id'] ?? 0) : null;
        if ($route === 'risk-edit' && !$r) { http_response_code(404); view('notfound'); return true; }
        if ($method === 'POST') {
            [$rid, $err] = risk_save($_POST, $r['id'] ?? 0);
            if ($err) { flash($err, 'error'); redirect($r ? '/risk-edit?id=' . $r['id'] : '/risk-new'); }
            flash($r ? 'Entry updated.' : 'Entry added to the register.');
            redirect('/risk?id=' . $rid);
        }
        view('ops/risk_form', ['r' => $r, 'cats' => risk_opts('risk_category'), 'levels' => RISK_LEVELS,
            'cfields' => function_exists('custom_fields_for') ? custom_fields_for('risk') : [],
            'cvals' => $r && function_exists('custom_values_map') ? custom_values_map('risk', $r['id']) : []]);
        return true;
    }
    if ($route === 'risk-status' && $method === 'POST') {
        $err = risk_set_status((int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''), (string)($_POST['note'] ?? ''));
        flash($err ?: 'Status updated.', $err ? 'error' : 'success');
        redirect('/risk?id=' . (int)($_POST['id'] ?? 0));
    }
    http_response_code(404); view('notfound'); return true;
}
