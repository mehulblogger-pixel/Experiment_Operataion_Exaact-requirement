<?php
// ===========================================================================
//  Hold / Witness Points — first-class inspection intervention points
//  --------------------------------------------------------------------------
//  A hold point stops the manufacturer proceeding until the inspection agency
//  clears it; a witness point must be attended; a review / clearance point
//  needs a document or client sign-off. Until now these lived only as words in
//  a report. Here each one is a tracked object with its own open→cleared
//  lifecycle, derived automatically from the disposition an inspector records
//  on an activity, or raised by hand. The register lives on the job so the
//  coordinator can see, at a glance, what is still holding despatch.
// ===========================================================================

// Point types. value === the disposition on an activity that raises this type.
const HW_POINT_TYPES = [
    'HOLD'      => 'Hold point',
    'WITNESS'   => 'Witness point',
    'REVIEW'    => 'Review point',
    'CLEARANCE' => 'Client clearance',
];
// Which activity dispositions raise which point type when a report is issued.
const HW_DISPOSITION_MAP = [
    'hold'                      => 'HOLD',
    'hold point'                => 'HOLD',
    'witnessed'                 => 'WITNESS',
    'witness'                   => 'WITNESS',
    'witness point'             => 'WITNESS',
    'reviewed'                  => 'REVIEW',
    'review'                    => 'REVIEW',
    'deviation'                 => 'REVIEW',
    'deviation point'           => 'REVIEW',
    'client clearance required' => 'CLEARANCE',
    'client clearance'          => 'CLEARANCE',
];
const HW_POINT_STATUS = [
    'OPEN'      => 'Open',
    'CLEARED'   => 'Cleared',
    'WAIVED'    => 'Waived',
    'CANCELLED' => 'Cancelled',
];

function hwp_migrate() {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS hw_points (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id INT NULL, call_id INT NULL, report_doc_id INT NULL, office_id INT NULL,
        irn VARCHAR(60) DEFAULT '',
        point_type VARCHAR(16) DEFAULT 'HOLD',
        qap_clause VARCHAR(60) DEFAULT '', sub_clause VARCHAR(60) DEFAULT '',
        activity VARCHAR(400) DEFAULT '', description TEXT,
        source VARCHAR(10) DEFAULT 'MANUAL',
        status VARCHAR(16) DEFAULT 'OPEN',
        dedupe_key VARCHAR(64) DEFAULT '',
        raised_by VARCHAR(150) DEFAULT '', raised_at VARCHAR(40) DEFAULT '',
        cleared_by VARCHAR(150) DEFAULT '', cleared_at VARCHAR(40) DEFAULT '',
        clearance_ref VARCHAR(120) DEFAULT '', remark TEXT,
        created_at VARCHAR(40) DEFAULT '', updated_at VARCHAR(40) DEFAULT ''
    )");
    // ensure_column is the app's forward-migration for existing installs.
    if (function_exists('ensure_column')) {
        ensure_column('hw_points', 'sub_clause', "VARCHAR(60) DEFAULT ''");
        ensure_column('hw_points', 'activity', "VARCHAR(400) DEFAULT ''");
        ensure_column('hw_points', 'dedupe_key', "VARCHAR(64) DEFAULT ''");
    }
    if (function_exists('idems_unique_index')) idems_unique_index('hw_points', 'dedupe_key');
}

// A missing table means an install that has not migrated yet — never fatal.
function hwp_missing_table(Throwable $e) {
    return stripos($e->getMessage(), 'hw_points') !== false
        && (stripos($e->getMessage(), 'no such table') !== false || stripos($e->getMessage(), "doesn't exist") !== false);
}

function hwp_can_view() { return function_exists('can') && (can('mod.idems.view') || can('ops.job.view') || is_master()); }
function hwp_can_edit() { return function_exists('can') && (can('mod.idems.edit') || can('ops.job.edit') || is_master()); }

function hwp_type_label($t)   { return HW_POINT_TYPES[$t] ?? $t; }
function hwp_status_label($s) { return HW_POINT_STATUS[$s] ?? $s; }

// --- reads --------------------------------------------------------------
function hwp_for_job($jobId) {
    try { return ops_all("SELECT * FROM hw_points WHERE job_id=? ORDER BY (status='OPEN') DESC, id", [(int)$jobId]); }
    catch (Throwable $e) { if (hwp_missing_table($e)) return []; throw $e; }
}
function hwp_open_count($jobId) {
    try { return (int)ops_val("SELECT COUNT(*) FROM hw_points WHERE job_id=? AND status='OPEN'", [(int)$jobId]); }
    catch (Throwable $e) { if (hwp_missing_table($e)) return 0; throw $e; }
}
function hwp_row($id) {
    try { return ops_one("SELECT * FROM hw_points WHERE id=?", [(int)$id]); }
    catch (Throwable $e) { if (hwp_missing_table($e)) return null; throw $e; }
}
// Every job that still has an open hold/witness point — for a manager overview.
function hwp_open_all($officeIds = null) {
    try {
        $sql = "SELECT h.*, j.job_code, o.name office_name
                FROM hw_points h LEFT JOIN jobs j ON j.id=h.job_id LEFT JOIN offices o ON o.id=h.office_id
                WHERE h.status='OPEN'";
        $args = [];
        if (is_array($officeIds) && $officeIds) { $sql .= " AND h.office_id IN (" . implode(',', array_fill(0, count($officeIds), '?')) . ")"; $args = array_map('intval', $officeIds); }
        $sql .= " ORDER BY h.point_type, h.id DESC";
        return ops_all($sql, $args);
    } catch (Throwable $e) { if (hwp_missing_table($e)) return []; throw $e; }
}

// --- writes -------------------------------------------------------------
function hwp_create(array $b) {
    hwp_migrate();
    $type = strtoupper(trim((string)($b['point_type'] ?? 'HOLD')));
    if (!isset(HW_POINT_TYPES[$type])) $type = 'HOLD';
    $now  = date('c');
    $key  = (string)($b['dedupe_key'] ?? '');
    // Idempotent when a dedupe key is given (auto-derivation re-runs safely).
    if ($key !== '') {
        $exists = ops_one("SELECT id FROM hw_points WHERE dedupe_key=?", [$key]);
        if ($exists) return (int)$exists['id'];
    }
    $sql = "INSERT INTO hw_points (job_id,call_id,report_doc_id,office_id,irn,point_type,qap_clause,sub_clause,activity,description,source,status,dedupe_key,raised_by,raised_at,remark,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?,?,?,?,?,?)";
    db()->prepare($sql)->execute([
        (int)($b['job_id'] ?? 0) ?: null, (int)($b['call_id'] ?? 0) ?: null,
        (int)($b['report_doc_id'] ?? 0) ?: null, (int)($b['office_id'] ?? 0) ?: null,
        substr((string)($b['irn'] ?? ''), 0, 60), $type,
        substr((string)($b['qap_clause'] ?? ''), 0, 60), substr((string)($b['sub_clause'] ?? ''), 0, 60),
        substr((string)($b['activity'] ?? ''), 0, 400), (string)($b['description'] ?? ''),
        in_array(($b['source'] ?? 'MANUAL'), ['AUTO','MANUAL'], true) ? $b['source'] : 'MANUAL',
        $key, (string)($b['raised_by'] ?? (function_exists('current_user') ? user_name(current_user()) : 'system')),
        $now, (string)($b['remark'] ?? ''), $now, $now,
    ]);
    $id = (int)db()->lastInsertId();
    if (function_exists('idems_log')) idems_log('hw_point', $id, 'RAISE', ['field' => hwp_type_label($type) . ($b['qap_clause'] ? ' · clause ' . $b['qap_clause'] : '')]);
    return $id;
}

// Close an open point — CLEARED (satisfied) or WAIVED (dropped with a reason).
function hwp_close($id, $status, $ref, $remark) {
    $status = strtoupper($status);
    if (!in_array($status, ['CLEARED','WAIVED','CANCELLED'], true)) return false;
    $h = hwp_row($id); if (!$h) return false;
    db()->prepare("UPDATE hw_points SET status=?, clearance_ref=?, remark=?, cleared_by=?, cleared_at=?, updated_at=? WHERE id=?")
        ->execute([$status, substr((string)$ref, 0, 120), (string)$remark,
                   function_exists('current_user') ? user_name(current_user()) : 'system',
                   date('c'), date('c'), (int)$id]);
    if (function_exists('idems_log')) idems_log('hw_point', (int)$id, $status, ['field' => ($ref ? 'ref ' . $ref : '') . ($remark ? ' — ' . $remark : '')]);
    return true;
}

// Derive hold/witness/review/clearance points from a report's activity tables.
// Runs when a report is issued: any activity row whose disposition is Hold /
// Witnessed / Reviewed / Client clearance required becomes an open point. Safe
// to run repeatedly — each row maps to a stable dedupe key.
function hwp_derive_from_doc($doc) {
    if (!$doc || empty($doc['id'])) return 0;
    try { hwp_migrate(); } catch (Throwable $e) { return 0; }
    $data = json_decode($doc['data'] ?? '[]', true); if (!is_array($data)) return 0;
    $fields = function_exists('idems_fields') ? idems_fields((int)$doc['report_type_id']) : [];
    if (!$fields) return 0;
    $made = 0;
    foreach ($fields as $f) {
        if (($f['ftype'] ?? '') !== 'table') continue;
        $rows = $data[$f['fkey']] ?? null; if (!is_array($rows)) continue;
        $defs = function_exists('idems_table_col_defs') ? idems_table_col_defs($f) : [];
        // Classify this table's columns once by their label.
        $roleOf = [];
        foreach ($defs as $ck => $d) {
            $lbl = strtolower((string)($d['label'] ?? $ck));
            if (strpos($lbl, 'sub') !== false && strpos($lbl, 'clause') !== false) $roleOf[$ck] = 'sub_clause';
            elseif (strpos($lbl, 'clause') !== false)                               $roleOf[$ck] = 'qap_clause';
            elseif (strpos($lbl, 'sub-clause') !== false)                           $roleOf[$ck] = 'sub_clause';
            elseif (strpos($lbl, 'activity') !== false)                             $roleOf[$ck] = 'activity';
            elseif (strpos($lbl, 'observation') !== false || strpos($lbl, 'finding') !== false || strpos($lbl, 'remark') !== false) $roleOf[$ck] = 'description';
        }
        foreach ($rows as $i => $r) {
            $r = (array)$r;
            // Find the disposition cell: any value matching a mapped disposition.
            $type = '';
            foreach ($r as $v) {
                $norm = strtolower(trim((string)$v));
                if (isset(HW_DISPOSITION_MAP[$norm])) { $type = HW_DISPOSITION_MAP[$norm]; break; }
            }
            if ($type === '') continue;
            $clause = ''; $sub = ''; $act = ''; $desc = '';
            foreach ($roleOf as $ck => $role) {
                $val = trim((string)($r[$ck] ?? ''));
                if ($role === 'qap_clause') $clause = $val;
                elseif ($role === 'sub_clause') $sub = $val;
                elseif ($role === 'activity') $act = $val;
                elseif ($role === 'description') $desc = $val;
            }
            if ($desc === '') $desc = implode(' · ', array_filter(array_map('strval', $r), fn($x) => trim($x) !== ''));
            $key = substr(md5(($doc['id']) . '|' . $f['fkey'] . '|' . $i . '|' . $type . '|' . $clause), 0, 64);
            $before = (int)ops_val("SELECT COUNT(*) FROM hw_points WHERE dedupe_key=?", [$key]);
            hwp_create([
                'job_id' => (int)($doc['job_id'] ?? 0), 'call_id' => (int)($doc['call_id'] ?? 0),
                'report_doc_id' => (int)$doc['id'], 'office_id' => (int)($doc['office_id'] ?? 0),
                'irn' => (string)($doc['irn'] ?? ''), 'point_type' => $type,
                'qap_clause' => $clause, 'sub_clause' => $sub, 'activity' => $act, 'description' => $desc,
                'source' => 'AUTO', 'dedupe_key' => $key,
            ]);
            if ($before === 0) $made++;
        }
    }
    return $made;
}

// ---------------------------------------------------------------------------
//  Routes
// ---------------------------------------------------------------------------
function ops_hwpoints($route, $method) {
    ops_require(hwp_can_view(), 'You cannot open the hold / witness register.');
    hwp_migrate();

    // Manager-wide list of everything still holding.
    if ($route === 'hold-points') {
        $scope = function_exists('scope_offices') ? scope_offices() : 'ALL';
        $rows = hwp_open_all(is_array($scope) ? $scope : null);
        view('ops/hwpoints', ['mode' => 'all', 'rows' => $rows, 'canEdit' => hwp_can_edit()]);
        return true;
    }

    // Everything else needs edit rights.
    ops_require(hwp_can_edit(), 'You cannot change a hold / witness point.');

    if ($route === 'hw-point-new') {
        $jobId = (int)($_POST['job_id'] ?? $_GET['job'] ?? 0);
        $back  = $jobId ? '/job?id=' . $jobId . '#holdpoints' : '/hold-points';
        if ($method === 'POST') {
            $job = $jobId ? ops_one("SELECT id, call_id, office_id FROM jobs WHERE id=?", [$jobId]) : null;
            if (trim((string)($_POST['description'] ?? '')) === '' && trim((string)($_POST['qap_clause'] ?? '')) === '') {
                flash('Give the point a QAP clause or a short description.', 'error'); redirect($back);
            }
            hwp_create([
                'job_id' => $jobId, 'call_id' => (int)($job['call_id'] ?? 0), 'office_id' => (int)($job['office_id'] ?? 0),
                'point_type' => $_POST['point_type'] ?? 'HOLD',
                'qap_clause' => $_POST['qap_clause'] ?? '', 'sub_clause' => $_POST['sub_clause'] ?? '',
                'activity' => $_POST['activity'] ?? '', 'description' => $_POST['description'] ?? '',
                'source' => 'MANUAL',
            ]);
            flash('Point raised. It stays open until you clear or waive it.');
        }
        redirect($back);
    }

    if ($route === 'hw-point-derive') {
        $jobId = (int)($_POST['job_id'] ?? $_GET['job'] ?? 0);
        $n = 0;
        foreach (ops_all("SELECT * FROM report_docs WHERE job_id=? AND finalized=1 AND deleted=0", [$jobId]) as $d) $n += hwp_derive_from_doc($d);
        flash($n > 0 ? "Scanned issued reports — $n new hold/witness point" . ($n === 1 ? '' : 's') . " raised." : 'Scanned issued reports — nothing new to raise.');
        redirect('/job?id=' . $jobId . '#holdpoints');
    }

    // clear / waive / cancel / reopen a single point.
    $h = hwp_row($_POST['id'] ?? $_GET['id'] ?? 0);
    if (!$h) { http_response_code(404); view('notfound'); return true; }
    $back = $h['job_id'] ? '/job?id=' . (int)$h['job_id'] . '#holdpoints' : '/hold-points';
    if ($method !== 'POST') redirect($back);

    if ($route === 'hw-point-clear' || $route === 'hw-point-waive' || $route === 'hw-point-cancel') {
        $status = ['hw-point-clear' => 'CLEARED', 'hw-point-waive' => 'WAIVED', 'hw-point-cancel' => 'CANCELLED'][$route];
        $remark = trim((string)($_POST['remark'] ?? ''));
        if ($status === 'WAIVED' && $remark === '') { flash('Waiving a point needs a reason.', 'error'); redirect($back); }
        hwp_close((int)$h['id'], $status, $_POST['clearance_ref'] ?? '', $remark);
        flash('Point ' . strtolower(hwp_status_label($status)) . '.');
        redirect($back);
    }

    if ($route === 'hw-point-reopen') {
        db()->prepare("UPDATE hw_points SET status='OPEN', cleared_by='', cleared_at='', updated_at=? WHERE id=?")
            ->execute([date('c'), (int)$h['id']]);
        if (function_exists('idems_log')) idems_log('hw_point', (int)$h['id'], 'REOPEN', ['field' => trim((string)($_POST['remark'] ?? ''))]);
        flash('Point reopened.');
        redirect($back);
    }

    redirect($back);
}
