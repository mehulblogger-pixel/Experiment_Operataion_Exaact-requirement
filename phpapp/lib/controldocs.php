<?php
// ============================================================================
//  Controlled documents — ISO/IEC 17020 §8.3 (document control)
//
//  The register of the management-system documents themselves — policies,
//  procedures, forms, the quality manual — each under version control: a
//  revision, an approver and approval date, an effective date, a review-due
//  date, and who it is distributed to. An assessor asks for exactly this: the
//  document in use is the current, approved revision, and its history is intact.
//
//  Distinct from the §7.1 method library (which controls the technical methods
//  the body works to); this controls the quality-system paperwork.
//
//  Configuration-first: the document type is an editable Master; extra data via
//  custom fields (entity 'controlled_doc'); the register is pack-gated.
// ============================================================================

const CDOC_STATUSES = ['DRAFT', 'CURRENT', 'SUPERSEDED', 'WITHDRAWN'];

function cdocs_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS controlled_docs (
            id $pk, doc_code VARCHAR(30) DEFAULT '', title VARCHAR(240) DEFAULT '', doc_type VARCHAR(40) DEFAULT '',
            revision VARCHAR(30) DEFAULT '', status VARCHAR(20) DEFAULT 'DRAFT', owner VARCHAR(150) DEFAULT '',
            approved_by VARCHAR(150) DEFAULT '', approved_on VARCHAR(20) DEFAULT '', effective_date VARCHAR(20) DEFAULT '',
            review_due VARCHAR(20) DEFAULT '', distribution VARCHAR(400) DEFAULT '', description TEXT,
            file_name VARCHAR(255) DEFAULT '', mime VARCHAR(120) DEFAULT '', file_data MEDIUMTEXT,
            supersedes_id INT NULL, superseded_by_id INT NULL, created_by VARCHAR(150) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) { /* never let a table add break boot */ }

    if (function_exists('lk_ensure_type_map')) {
        try {
            lk_ensure_type_map('cdoc_type', 'Controlled-document type', [
                'POLICY' => 'Policy', 'MANUAL' => 'Quality manual', 'PROCEDURE' => 'Procedure',
                'WORK_INSTR' => 'Work instruction', 'FORM' => 'Form / template', 'PLAN' => 'Plan',
                'REGISTER' => 'Register / list', 'EXTERNAL' => 'External document', 'OTHER' => 'Other',
            ], 'Operations');
        } catch (Throwable $e) { /* masters are a convenience */ }
    }
}

function cdoc_pack_on() { return !function_exists('accredited_pack_on') || accredited_pack_on(); }
function cdoc_can_view() {
    if (!cdoc_pack_on()) return false;
    return is_master() || (function_exists('can') && (can('mod.datacontrol.view') || can('mod.audits.view')
        || can('settings.manage')));
}
function cdoc_can_manage() {
    if (!cdoc_pack_on()) return false;
    return is_master() || (function_exists('can') && (can('mod.datacontrol.view') || can('settings.manage')));
}

function cdoc_opts($key) {
    $t = function_exists('lk_type') ? lk_type($key) : null;
    if (!$t) return [];
    $out = [];
    foreach (ops_all("SELECT code, label FROM lookup_values WHERE type_id=? AND active=1 ORDER BY sort_order, label", [$t['id']]) ?: [] as $r)
        $out[$r['code']] = $r['label'];
    return $out;
}
function cdoc_type_label($code) { $o = cdoc_opts('cdoc_type'); return $o[$code] ?? ($code ?: '—'); }

function cdocs_all($filter = []) {
    cdocs_migrate();
    $where = '1=1'; $args = [];
    if (!empty($filter['current'])) $where .= " AND status='CURRENT'";
    if (!empty($filter['q'])) { $where .= " AND (doc_code LIKE ? OR title LIKE ?)";
        $like = '%' . $filter['q'] . '%'; array_push($args, $like, $like); }
    return ops_all("SELECT * FROM controlled_docs WHERE $where ORDER BY title, id DESC", $args) ?: [];
}
function cdoc_get($id) { cdocs_migrate(); return ops_one("SELECT * FROM controlled_docs WHERE id=?", [(int)$id]); }
function cdoc_counts() {
    cdocs_migrate();
    return ['current' => (int)ops_val("SELECT COUNT(*) FROM controlled_docs WHERE status='CURRENT'"),
            'review_due' => (int)ops_val("SELECT COUNT(*) FROM controlled_docs WHERE status='CURRENT' AND review_due<>'' AND review_due < ?", [date('Y-m-d')])];
}
// Module 41 — the §8.3 readiness rollup, for the compliance board. Built on the
// same query cdoc_counts() already uses; adds a "never approved but current" count
// (a document in use that was never signed off). Pure read.
function cdoc_readiness($today = null) {
    cdocs_migrate();
    $today = $today ?: date('Y-m-d');
    return [
        'current'        => (int)ops_val("SELECT COUNT(*) FROM controlled_docs WHERE status='CURRENT'"),
        'review_overdue' => (int)ops_val("SELECT COUNT(*) FROM controlled_docs WHERE status='CURRENT' AND review_due<>'' AND review_due < ?", [$today]),
        'never_approved' => (int)ops_val("SELECT COUNT(*) FROM controlled_docs WHERE status='CURRENT' AND COALESCE(approved_on,'')=''"),
    ];
}

// Module 41 — chase a controlled document past its review date, the way every
// other accreditation register is chased. Reads only the readiness; e-mails the
// quality owner; returns a count. Notifies, never changes a document.
function cdoc_run_reminders($today = null) {
    cdocs_migrate();
    $r = cdoc_readiness($today);
    if ((int)$r['review_overdue'] === 0 && (int)$r['never_approved'] === 0) return 0;
    $to = getenv('QAC_EMAIL') ?: (function_exists('coordinator_emails') ? coordinator_emails() : '');
    if (trim((string)$to) === '') return 0;
    $body = "Controlled documents (§8.3) — attention needed.\n\n";
    if ((int)$r['review_overdue'] > 0) {
        $body .= (int)$r['review_overdue'] . " current document(s) are past their review date:\n";
        foreach (ops_all("SELECT doc_code, title, review_due FROM controlled_docs WHERE status='CURRENT' AND review_due<>'' AND review_due < ? ORDER BY review_due LIMIT 40", [$today ?: date('Y-m-d')]) ?: [] as $d)
            $body .= '  - ' . $d['doc_code'] . ' ' . $d['title'] . ' (review was due ' . fdate($d['review_due']) . ")\n";
        $body .= "\n";
    }
    if ((int)$r['never_approved'] > 0)
        $body .= (int)$r['never_approved'] . " current document(s) have no recorded approval.\n\n";
    $body .= "Open Controlled documents in " . app_name() . " to review or re-approve them.\n";
    try { ops_mail($to, 'Controlled documents: ' . (int)$r['review_overdue'] . ' past review date', $body, '', 'controldoc'); }
    catch (Throwable $e) { /* a failed reminder must never break the run */ }
    return 1;
}

function cdoc_next_code() {
    $n = (int)ops_val("SELECT COUNT(*) FROM controlled_docs WHERE supersedes_id IS NULL") + 1;
    do { $code = sprintf('DOC-%04d', $n); $n++; }
    while (ops_val("SELECT COUNT(*) FROM controlled_docs WHERE doc_code=?", [$code]) > 0);
    return $code;
}

function cdoc_save($b, $id = 0) {
    cdocs_migrate();
    $title = trim((string)($b['title'] ?? ''));
    if ($title === '') return [0, 'A title is required.'];
    $cols = [
        'title'        => substr($title, 0, 240),
        'doc_type'     => substr(trim((string)($b['doc_type'] ?? '')), 0, 40),
        'revision'     => substr(trim((string)($b['revision'] ?? '')), 0, 30),
        'owner'        => substr(trim((string)($b['owner'] ?? '')), 0, 150),
        'approved_by'  => substr(trim((string)($b['approved_by'] ?? '')), 0, 150),
        'approved_on'  => substr(trim((string)($b['approved_on'] ?? '')), 0, 20),
        'effective_date' => substr(trim((string)($b['effective_date'] ?? '')), 0, 20),
        'review_due'   => substr(trim((string)($b['review_due'] ?? '')), 0, 20),
        'distribution' => substr(trim((string)($b['distribution'] ?? '')), 0, 400),
        'description'  => trim((string)($b['description'] ?? '')),
    ];
    $file = $_FILES['cdoc_file'] ?? null;
    $bytes = ($file && ($file['tmp_name'] ?? '') !== '' && is_uploaded_file($file['tmp_name']))
        ? (string)file_get_contents($file['tmp_name']) : '';
    if ($bytes !== '' && function_exists('upload_reject_reason')
        && ($why = upload_reject_reason($bytes, $file['name'] ?? '', $file['type'] ?? '')) !== '')
        return [0, $why];

    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($cols)));
        db()->prepare("UPDATE controlled_docs SET $set, updated_at=? WHERE id=?")
            ->execute(array_merge(array_values($cols), [date('c'), (int)$id]));
        if ($bytes !== '') db()->prepare("UPDATE controlled_docs SET file_name=?, mime=?, file_data=? WHERE id=?")
            ->execute([substr((string)($file['name'] ?? ''), 0, 255), (string)($file['type'] ?? ''), base64_encode($bytes), (int)$id]);
        if (function_exists('custom_save')) custom_save('controlled_doc', (int)$id, $b);
        return [(int)$id, ''];
    }
    $cols['doc_code']   = cdoc_next_code();
    $cols['status']     = 'DRAFT';
    $cols['file_name']  = $bytes !== '' ? substr((string)($file['name'] ?? ''), 0, 255) : '';
    $cols['mime']       = $bytes !== '' ? (string)($file['type'] ?? '') : '';
    $cols['file_data']  = $bytes !== '' ? base64_encode($bytes) : '';
    $cols['created_by'] = user_name(current_user());
    $cols['created_at'] = date('c');
    $cols['updated_at'] = date('c');
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO controlled_docs (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    $newId = (int)db()->lastInsertId();
    if (function_exists('custom_save')) custom_save('controlled_doc', $newId, $b);
    if (function_exists('idems_log')) try { idems_log('controlled_doc', $newId, 'CREATED', ['reason' => $cols['title']]); } catch (Throwable $e) {}
    return [$newId, ''];
}

function cdoc_set_status($id, $status) {
    $d = cdoc_get($id);
    if (!$d || !in_array($status, CDOC_STATUSES, true)) return 'That status is not valid.';
    db()->prepare("UPDATE controlled_docs SET status=?, updated_at=? WHERE id=?")->execute([$status, date('c'), (int)$id]);
    if (function_exists('idems_log')) try { idems_log('controlled_doc', (int)$id, 'STATUS_' . $status); } catch (Throwable $e) {}
    return '';
}

function cdoc_supersede($id, $newRevision) {
    $d = cdoc_get($id);
    if (!$d) return [0, 'Document not found.'];
    $cols = ['doc_code' => $d['doc_code'], 'title' => $d['title'], 'doc_type' => $d['doc_type'],
             'revision' => substr(trim((string)$newRevision), 0, 30) ?: ($d['revision'] . '+'), 'status' => 'DRAFT',
             'owner' => $d['owner'], 'distribution' => $d['distribution'], 'description' => $d['description'],
             'supersedes_id' => (int)$d['id'], 'created_by' => user_name(current_user()),
             'created_at' => date('c'), 'updated_at' => date('c')];
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO controlled_docs (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    $newId = (int)db()->lastInsertId();
    db()->prepare("UPDATE controlled_docs SET status='SUPERSEDED', superseded_by_id=?, updated_at=? WHERE id=?")
        ->execute([$newId, date('c'), (int)$d['id']]);
    // Module 41 — supersession is a controlled-document lifecycle event and belongs
    // on the sealed trail like CREATED / STATUS_CURRENT already are (it was the one
    // event that was not logged).
    if (function_exists('idems_log'))
        idems_log('controlled_doc', $newId, 'SUPERSEDED',
            ['field' => $d['doc_code'], 'old' => $d['revision'], 'new' => $cols['revision'],
             'reason' => 'supersedes ' . $d['doc_code'] . ' (#' . (int)$d['id'] . ')']);
    return [$newId, ''];
}

// ---- Routing -------------------------------------------------------------
function ops_cdocs($route, $method) {
    cdocs_migrate();
    ops_require(cdoc_can_view(), 'The controlled-document register is not available to you.');
    $canEdit = cdoc_can_manage();

    if ($route === 'cdoc-file') {
        $d = cdoc_get($_GET['id'] ?? 0);
        if (!$d || ($d['file_data'] ?? '') === '') { http_response_code(404); view('notfound'); return true; }
        if (function_exists('send_uploaded_file'))
            send_uploaded_file(base64_decode((string)$d['file_data']), $d['file_name'] ?: 'document', $d['mime'] ?: 'application/octet-stream');
        return true;
    }
    if ($route === 'cdocs') {
        $filter = ['q' => trim((string)($_GET['q'] ?? ''))];
        if (($_GET['show'] ?? 'current') === 'current') $filter['current'] = 1;
        view('ops/cdocs_list', ['rows' => cdocs_all($filter), 'canEdit' => $canEdit,
            'showAll' => ($_GET['show'] ?? 'current') !== 'current', 'q' => $filter['q'], 'counts' => cdoc_counts()]);
        return true;
    }
    if ($route === 'cdoc') {
        $d = cdoc_get($_GET['id'] ?? 0);
        if (!$d) { http_response_code(404); view('notfound'); return true; }
        view('ops/cdoc_detail', ['d' => $d, 'canEdit' => $canEdit,
            'supersedes' => $d['supersedes_id'] ? cdoc_get($d['supersedes_id']) : null,
            'supersededBy' => $d['superseded_by_id'] ? cdoc_get($d['superseded_by_id']) : null,
            'cvals' => function_exists('custom_display') ? custom_display('controlled_doc', $d['id']) : []]);
        return true;
    }

    ops_require($canEdit, 'You cannot change the controlled-document register.');

    if ($route === 'cdoc-new' || $route === 'cdoc-edit') {
        $d = $route === 'cdoc-edit' ? cdoc_get($_GET['id'] ?? 0) : null;
        if ($route === 'cdoc-edit' && !$d) { http_response_code(404); view('notfound'); return true; }
        if ($method === 'POST') {
            [$did, $err] = cdoc_save($_POST, $d['id'] ?? 0);
            if ($err) { flash($err, 'error'); redirect($d ? '/cdoc-edit?id=' . $d['id'] : '/cdoc-new'); }
            flash($d ? 'Document updated.' : 'Document added as a draft. Approve it, then set it Current.');
            redirect('/cdoc?id=' . $did);
        }
        view('ops/cdoc_form', ['d' => $d, 'types' => cdoc_opts('cdoc_type'),
            'cfields' => function_exists('custom_fields_for') ? custom_fields_for('controlled_doc') : [],
            'cvals' => $d && function_exists('custom_values_map') ? custom_values_map('controlled_doc', $d['id']) : []]);
        return true;
    }
    if ($route === 'cdoc-status' && $method === 'POST') {
        $err = cdoc_set_status((int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''));
        flash($err ?: 'Status updated.', $err ? 'error' : 'success');
        redirect('/cdoc?id=' . (int)($_POST['id'] ?? 0));
    }
    if ($route === 'cdoc-supersede' && $method === 'POST') {
        [$newId, $err] = cdoc_supersede((int)($_POST['id'] ?? 0), (string)($_POST['revision'] ?? ''));
        if ($err) { flash($err, 'error'); redirect('/cdoc?id=' . (int)($_POST['id'] ?? 0)); }
        flash('New revision created as a draft.');
        redirect('/cdoc?id=' . $newId);
    }
    http_response_code(404); view('notfound'); return true;
}
