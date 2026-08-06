<?php
// ============================================================================
//  Inspection methods & standards — ISO/IEC 17020 §7.1 (17025 §7.2)
//
//  A controlled library of the methods, procedures and standards the body works
//  to — each with a revision, a status (draft / current / superseded / with-
//  drawn), a validation record, and the actual document on file. The point an
//  assessor checks: the method in use is the CURRENT, validated revision, and a
//  report can be traced to the exact revision it applied.
//
//  Reissue is versioned: superseding a method creates a NEW revision linked to
//  the old, and stamps the old one SUPERSEDED — the history is never overwritten.
//
//  Configuration-first (standing rule): the method category is an editable
//  Master list; anything else a body wants to capture goes on as a custom field
//  (entity 'method'); the whole library is gated behind the accreditation pack.
// ============================================================================

const METHOD_STATUSES = ['DRAFT', 'CURRENT', 'SUPERSEDED', 'WITHDRAWN'];

function methods_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS methods (
            id $pk, method_code VARCHAR(30) DEFAULT '', title VARCHAR(240) DEFAULT '',
            standard_ref VARCHAR(120) DEFAULT '', revision VARCHAR(30) DEFAULT '', category VARCHAR(40) DEFAULT '',
            discipline VARCHAR(40) DEFAULT '', status VARCHAR(20) DEFAULT 'DRAFT', effective_date VARCHAR(20) DEFAULT '',
            review_due VARCHAR(20) DEFAULT '', owner VARCHAR(150) DEFAULT '', description TEXT,
            is_validated INT DEFAULT 0, validated_on VARCHAR(20) DEFAULT '', validated_by VARCHAR(150) DEFAULT '',
            validation_note VARCHAR(400) DEFAULT '', file_name VARCHAR(255) DEFAULT '', mime VARCHAR(120) DEFAULT '',
            file_data MEDIUMTEXT, supersedes_id INT NULL, superseded_by_id INT NULL,
            created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) { /* never let a table add break boot */ }

    if (function_exists('lk_ensure_type_map')) {
        try {
            lk_ensure_type_map('method_category', 'Method / standard category', [
                'NDT' => 'NDT method', 'MECHANICAL' => 'Mechanical / dimensional', 'WELDING' => 'Welding / fabrication',
                'ELECTRICAL' => 'Electrical', 'CHEMICAL' => 'Chemical / material', 'VISUAL' => 'Visual inspection',
                'PROCEDURE' => 'In-house procedure', 'STANDARD' => 'Published standard', 'OTHER' => 'Other',
            ], 'Operations');
        } catch (Throwable $e) { /* masters are a convenience, not load-bearing */ }
    }
}

function method_pack_on() { return !function_exists('accredited_pack_on') || accredited_pack_on(); }
function method_can_view() {
    if (!method_pack_on()) return false;
    return is_master() || (function_exists('can') && (can('mod.equipment.view') || can('mod.competence.view')));
}
function method_can_manage() {
    if (!method_pack_on()) return false;
    return is_master() || (function_exists('can') && can('mod.competence.view'))
        || (function_exists('is_coordinator_level') && is_coordinator_level());
}

function method_opts($key) {
    $t = function_exists('lk_type') ? lk_type($key) : null;
    if (!$t) return [];
    $out = [];
    foreach (ops_all("SELECT code, label FROM lookup_values WHERE type_id=? AND active=1 ORDER BY sort_order, label", [$t['id']]) ?: [] as $r)
        $out[$r['code']] = $r['label'];
    return $out;
}
function method_label($key, $code) { $o = method_opts($key); return $o[$code] ?? ($code ?: '—'); }

function methods_all($filter = []) {
    methods_migrate();
    $where = '1=1'; $args = [];
    if (!empty($filter['current'])) $where .= " AND status='CURRENT'";
    if (!empty($filter['status'])) { $where .= " AND status=?"; $args[] = $filter['status']; }
    if (!empty($filter['q'])) { $where .= " AND (method_code LIKE ? OR title LIKE ? OR standard_ref LIKE ?)";
        $like = '%' . $filter['q'] . '%'; array_push($args, $like, $like, $like); }
    return ops_all("SELECT * FROM methods WHERE $where ORDER BY title, id DESC", $args) ?: [];
}
function method_get($id) { methods_migrate(); return ops_one("SELECT * FROM methods WHERE id=?", [(int)$id]); }
function method_counts() {
    methods_migrate();
    return ['current' => (int)ops_val("SELECT COUNT(*) FROM methods WHERE status='CURRENT'"),
            'unvalidated' => (int)ops_val("SELECT COUNT(*) FROM methods WHERE status='CURRENT' AND is_validated=0")];
}

function method_next_code() {
    $n = (int)ops_val("SELECT COUNT(*) FROM methods WHERE supersedes_id IS NULL") + 1;
    do { $code = sprintf('MTH-%04d', $n); $n++; }
    while (ops_val("SELECT COUNT(*) FROM methods WHERE method_code=?", [$code]) > 0);
    return $code;
}

function method_save($b, $id = 0) {
    methods_migrate();
    $title = trim((string)($b['title'] ?? ''));
    if ($title === '') return [0, 'A title is required.'];
    $cols = [
        'title'        => substr($title, 0, 240),
        'standard_ref' => substr(trim((string)($b['standard_ref'] ?? '')), 0, 120),
        'revision'     => substr(trim((string)($b['revision'] ?? '')), 0, 30),
        'category'     => substr(trim((string)($b['category'] ?? '')), 0, 40),
        'discipline'   => substr(trim((string)($b['discipline'] ?? '')), 0, 40),
        'effective_date' => substr(trim((string)($b['effective_date'] ?? '')), 0, 20),
        'review_due'   => substr(trim((string)($b['review_due'] ?? '')), 0, 20),
        'owner'        => substr(trim((string)($b['owner'] ?? '')), 0, 150),
        'description'  => trim((string)($b['description'] ?? '')),
    ];
    // Optional method document.
    $file = $_FILES['method_file'] ?? null;
    $bytes = ($file && ($file['tmp_name'] ?? '') !== '' && is_uploaded_file($file['tmp_name']))
        ? (string)file_get_contents($file['tmp_name']) : '';
    if ($bytes !== '' && function_exists('upload_reject_reason')
        && ($why = upload_reject_reason($bytes, $file['name'] ?? '', $file['type'] ?? '')) !== '')
        return [0, $why];

    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($cols)));
        db()->prepare("UPDATE methods SET $set, updated_at=? WHERE id=?")
            ->execute(array_merge(array_values($cols), [date('c'), (int)$id]));
        if ($bytes !== '') db()->prepare("UPDATE methods SET file_name=?, mime=?, file_data=? WHERE id=?")
            ->execute([substr((string)($file['name'] ?? ''), 0, 255), (string)($file['type'] ?? ''), base64_encode($bytes), (int)$id]);
        if (function_exists('custom_save')) custom_save('method', (int)$id, $b);
        return [(int)$id, ''];
    }
    $cols['method_code'] = method_next_code();
    $cols['status']      = 'DRAFT';
    $cols['file_name']   = $bytes !== '' ? substr((string)($file['name'] ?? ''), 0, 255) : '';
    $cols['mime']        = $bytes !== '' ? (string)($file['type'] ?? '') : '';
    $cols['file_data']   = $bytes !== '' ? base64_encode($bytes) : '';
    $cols['created_by']  = user_name(current_user());
    $cols['created_at']  = date('c');
    $cols['updated_at']  = date('c');
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO methods (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    $newId = (int)db()->lastInsertId();
    if (function_exists('custom_save')) custom_save('method', $newId, $b);
    if (function_exists('idems_log')) try { idems_log('method', $newId, 'CREATED', ['reason' => $cols['title']]); } catch (Throwable $e) {}
    return [$newId, ''];
}

function method_set_status($id, $status) {
    $m = method_get($id);
    if (!$m || !in_array($status, METHOD_STATUSES, true)) return 'That status is not valid.';
    db()->prepare("UPDATE methods SET status=?, updated_at=? WHERE id=?")->execute([$status, date('c'), (int)$id]);
    if (function_exists('idems_log')) try { idems_log('method', (int)$id, 'STATUS_' . $status); } catch (Throwable $e) {}
    return '';
}

function method_validate($id, $note) {
    $m = method_get($id);
    if (!$m) return 'Method not found.';
    db()->prepare("UPDATE methods SET is_validated=1, validated_on=?, validated_by=?, validation_note=?, updated_at=? WHERE id=?")
        ->execute([date('Y-m-d'), user_name(current_user()), substr((string)$note, 0, 400), date('c'), (int)$id]);
    if (function_exists('idems_log')) try { idems_log('method', (int)$id, 'VALIDATED', ['reason' => $note]); } catch (Throwable $e) {}
    return '';
}

// Issue a new revision: clone the method's descriptive fields into a fresh DRAFT
// row, link the two, and mark the old one SUPERSEDED. The old revision and its
// history are kept intact.
function method_supersede($id, $newRevision) {
    $m = method_get($id);
    if (!$m) return [0, 'Method not found.'];
    $cols = ['method_code' => $m['method_code'], 'title' => $m['title'], 'standard_ref' => $m['standard_ref'],
             'revision' => substr(trim((string)$newRevision), 0, 30) ?: ($m['revision'] . '+'), 'category' => $m['category'],
             'discipline' => $m['discipline'], 'status' => 'DRAFT', 'owner' => $m['owner'],
             'description' => $m['description'], 'supersedes_id' => (int)$m['id'],
             'created_by' => user_name(current_user()), 'created_at' => date('c'), 'updated_at' => date('c')];
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO methods (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    $newId = (int)db()->lastInsertId();
    db()->prepare("UPDATE methods SET status='SUPERSEDED', superseded_by_id=?, updated_at=? WHERE id=?")
        ->execute([$newId, date('c'), (int)$m['id']]);
    if (function_exists('idems_log')) try { idems_log('method', $newId, 'REVISION_OF', ['reason' => $m['method_code']]); } catch (Throwable $e) {}
    return [$newId, ''];
}

// ---- Routing -------------------------------------------------------------
function ops_methods($route, $method) {
    methods_migrate();
    ops_require(method_can_view(), 'The method library is not available to you.');
    $canEdit = method_can_manage();

    if ($route === 'method-file') {
        $m = method_get($_GET['id'] ?? 0);
        if (!$m || ($m['file_data'] ?? '') === '') { http_response_code(404); view('notfound'); return true; }
        if (function_exists('send_uploaded_file'))
            send_uploaded_file(base64_decode((string)$m['file_data']), $m['file_name'] ?: 'method', $m['mime'] ?: 'application/octet-stream');
        return true;
    }
    if ($route === 'methods') {
        $filter = ['q' => trim((string)($_GET['q'] ?? ''))];
        if (($_GET['show'] ?? 'current') === 'current') $filter['current'] = 1;
        view('ops/methods_list', ['rows' => methods_all($filter), 'canEdit' => $canEdit,
            'showAll' => ($_GET['show'] ?? 'current') !== 'current', 'q' => $filter['q'], 'counts' => method_counts()]);
        return true;
    }
    if ($route === 'method') {
        $m = method_get($_GET['id'] ?? 0);
        if (!$m) { http_response_code(404); view('notfound'); return true; }
        view('ops/method_detail', ['m' => $m, 'canEdit' => $canEdit,
            'supersedes' => $m['supersedes_id'] ? method_get($m['supersedes_id']) : null,
            'supersededBy' => $m['superseded_by_id'] ? method_get($m['superseded_by_id']) : null,
            'cvals' => function_exists('custom_display') ? custom_display('method', $m['id']) : []]);
        return true;
    }

    ops_require($canEdit, 'You cannot change the method library.');

    if ($route === 'method-new' || $route === 'method-edit') {
        $m = $route === 'method-edit' ? method_get($_GET['id'] ?? 0) : null;
        if ($route === 'method-edit' && !$m) { http_response_code(404); view('notfound'); return true; }
        if ($method === 'POST') {
            [$mid, $err] = method_save($_POST, $m['id'] ?? 0);
            if ($err) { flash($err, 'error'); redirect($m ? '/method-edit?id=' . $m['id'] : '/method-new'); }
            flash($m ? 'Method updated.' : 'Method added as a draft. Validate it, then set it Current.');
            redirect('/method?id=' . $mid);
        }
        view('ops/method_form', ['m' => $m, 'categories' => method_opts('method_category'),
            'cfields' => function_exists('custom_fields_for') ? custom_fields_for('method') : [],
            'cvals' => $m && function_exists('custom_values_map') ? custom_values_map('method', $m['id']) : []]);
        return true;
    }
    if ($route === 'method-status' && $method === 'POST') {
        $err = method_set_status((int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''));
        flash($err ?: 'Status updated.', $err ? 'error' : 'success');
        redirect('/method?id=' . (int)($_POST['id'] ?? 0));
    }
    if ($route === 'method-validate' && $method === 'POST') {
        $err = method_validate((int)($_POST['id'] ?? 0), (string)($_POST['note'] ?? ''));
        flash($err ?: 'Method recorded as validated.', $err ? 'error' : 'success');
        redirect('/method?id=' . (int)($_POST['id'] ?? 0));
    }
    if ($route === 'method-supersede' && $method === 'POST') {
        [$newId, $err] = method_supersede((int)($_POST['id'] ?? 0), (string)($_POST['revision'] ?? ''));
        if ($err) { flash($err, 'error'); redirect('/method?id=' . (int)($_POST['id'] ?? 0)); }
        flash('New revision created as a draft — update it and set it Current when ready.');
        redirect('/method?id=' . $newId);
    }
    http_response_code(404); view('notfound'); return true;
}
