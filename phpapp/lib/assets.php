<?php
// ============================================================================
//  Asset issuance register
//
//  What is given to an engineer — hard stamps, soft stamps, diaries, safety
//  gear, an ID card, a laptop — recorded against the person, acknowledged by
//  them, and tracked until it comes back. On joining a store-keeper issues the
//  kit; the register is what answers "who is holding our second hard stamp"
//  and "what is still out with somebody who has left".
//
//  Deliberately simple and generic (any TPIA, any kit): the list of asset kinds
//  is an editable lookup, acknowledgement is a name + date (+ an optional scan
//  of the signed slip), and return is its own step so nothing is quietly
//  written off.
// ============================================================================

// Default kinds. Editable under the 'asset_type' list; this is only the seed.
const ASSET_TYPES = [
    'HARD_STAMP'  => 'Hard stamp',
    'SOFT_STAMP'  => 'Soft stamp',
    'DIARY'       => 'Diary / logbook',
    'ID_CARD'     => 'ID card',
    'SAFETY_HELMET' => 'Safety helmet',
    'SAFETY_SHOES'  => 'Safety shoes',
    'HARNESS'     => 'Safety harness',
    'HI_VIS'      => 'Hi-vis jacket',
    'PPE_KIT'     => 'PPE kit (other)',
    'UNIFORM'     => 'Uniform',
    'LAPTOP'      => 'Laptop',
    'PHONE_SIM'   => 'Phone / SIM',
    'INSTRUMENT'  => 'Measuring instrument',
    'OTHER'       => 'Other',
];
const ASSET_STATUS = [
    'ISSUED'   => 'Issued',
    'RETURNED' => 'Returned',
    'LOST'     => 'Lost',
    'DAMAGED'  => 'Damaged / written off',
];
const ASSET_CONDITIONS = ['NEW' => 'New', 'GOOD' => 'Good', 'USED' => 'Used', 'DAMAGED' => 'Damaged'];

function asset_types()      { return lk_options_or('asset_type', ASSET_TYPES); }
function asset_can_view()   { return is_coordinator_level() || is_master(); }
function asset_can_manage() { return is_coordinator_level() || is_master(); }

function assets_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = pk_clause();
    db()->exec("CREATE TABLE IF NOT EXISTS asset_issues (
        id $pk,
        asset_type VARCHAR(30) DEFAULT 'OTHER', asset_name VARCHAR(200) DEFAULT '',
        identifier VARCHAR(120) DEFAULT '', quantity INT DEFAULT 1,
        person_kind VARCHAR(20) DEFAULT 'INSPECTOR', person_id INT,
        issued_on VARCHAR(20) DEFAULT '', issued_by VARCHAR(150) DEFAULT '',
        condition_issued VARCHAR(20) DEFAULT 'GOOD',
        ack_on VARCHAR(20) DEFAULT '', ack_by VARCHAR(150) DEFAULT '', ack_note VARCHAR(400) DEFAULT '',
        ack_file_name VARCHAR(255) DEFAULT '', ack_mime VARCHAR(100) DEFAULT '', ack_file_data LONGTEXT,
        returned_on VARCHAR(20) DEFAULT '', returned_by VARCHAR(150) DEFAULT '',
        condition_returned VARCHAR(20) DEFAULT '', return_note VARCHAR(400) DEFAULT '',
        status VARCHAR(20) DEFAULT 'ISSUED', notes VARCHAR(400) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
}

function asset_missing_table(Throwable $e) {
    $m = $e->getMessage();
    return stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false;
}

// ---- Reads -----------------------------------------------------------------
function asset_row($id) {
    assets_migrate();
    try { return ops_one("SELECT * FROM asset_issues WHERE id=?", [(int)$id]); }
    catch (Throwable $e) { if (asset_missing_table($e)) return null; throw $e; }
}

// Every asset held by (or historically issued to) one person.
function person_assets($personId, $openOnly = false) {
    assets_migrate();
    $w = "person_kind='INSPECTOR' AND person_id=?";
    if ($openOnly) $w .= " AND status='ISSUED'";
    try {
        return ops_all("SELECT * FROM asset_issues WHERE $w ORDER BY (status='ISSUED') DESC, id DESC", [(int)$personId]);
    } catch (Throwable $e) { if (asset_missing_table($e)) return []; throw $e; }
}

// A one-line summary for the person's record card.
function person_assets_summary($personId) {
    $rows = person_assets($personId);
    $out  = 0; $noack = 0;
    foreach ($rows as $r) {
        if ($r['status'] === 'ISSUED') { $out++; if (trim((string)$r['ack_on']) === '') $noack++; }
    }
    return ['total' => count($rows), 'out' => $out, 'noack' => $noack];
}

function asset_counts() {
    assets_migrate();
    try {
        return [
            'out'    => (int)ops_val("SELECT COUNT(*) FROM asset_issues WHERE status='ISSUED'"),
            'noack'  => (int)ops_val("SELECT COUNT(*) FROM asset_issues WHERE status='ISSUED' AND (ack_on='' OR ack_on IS NULL)"),
            'people' => (int)ops_val("SELECT COUNT(DISTINCT person_id) FROM asset_issues WHERE status='ISSUED'"),
        ];
    } catch (Throwable $e) { if (asset_missing_table($e)) return ['out'=>0,'noack'=>0,'people'=>0]; throw $e; }
}

// ---- Writes ----------------------------------------------------------------
function asset_issue_add($post, $file = null) {
    assets_migrate();
    $personId = (int)($post['person_id'] ?? 0);
    if (!$personId) return 'Choose who the asset is issued to.';
    $type = (string)($post['asset_type'] ?? '');
    if (!isset(asset_types()[$type])) return 'Choose the kind of asset.';
    $name = trim((string)($post['asset_name'] ?? ''));
    if ($name === '') $name = asset_types()[$type];   // fall back to the kind's label
    $qty = max(1, (int)($post['quantity'] ?? 1));
    // Optional signed-slip scan.
    [$fn, $mime, $data] = asset_file_capture($file);
    $ackOn = trim((string)($post['ack_on'] ?? ''));
    $ackBy = trim((string)($post['ack_by'] ?? ''));
    db()->prepare("INSERT INTO asset_issues
        (asset_type,asset_name,identifier,quantity,person_kind,person_id,issued_on,issued_by,condition_issued,
         ack_on,ack_by,ack_note,ack_file_name,ack_mime,ack_file_data,status,notes,created_by,created_at)
        VALUES (?,?,?,?, 'INSPECTOR', ?,?,?,?,?,?,?,?,?,?, 'ISSUED', ?,?,?)")
        ->execute([$type, substr($name, 0, 200), substr(trim((string)($post['identifier'] ?? '')), 0, 120), $qty,
                   $personId,
                   (string)($post['issued_on'] ?? date('Y-m-d')), user_name(current_user()),
                   isset(ASSET_CONDITIONS[$post['condition_issued'] ?? '']) ? $post['condition_issued'] : 'GOOD',
                   $ackOn, $ackBy, substr(trim((string)($post['ack_note'] ?? '')), 0, 400),
                   $fn, $mime, $data,
                   substr(trim((string)($post['notes'] ?? '')), 0, 400),
                   user_name(current_user()), date('c')]);
    return '';
}

function asset_ack($id, $post, $file = null) {
    $a = asset_row($id); if (!$a) return 'That entry no longer exists.';
    $by = trim((string)($post['ack_by'] ?? ''));
    if ($by === '') $by = (string)ops_val("SELECT name FROM inspectors WHERE id=?", [(int)$a['person_id']]);
    [$fn, $mime, $data] = asset_file_capture($file);
    $set = "ack_on=?, ack_by=?, ack_note=?";
    $args = [trim((string)($post['ack_on'] ?? date('Y-m-d'))), $by, substr(trim((string)($post['ack_note'] ?? '')), 0, 400)];
    if ($data !== null) { $set .= ", ack_file_name=?, ack_mime=?, ack_file_data=?"; array_push($args, $fn, $mime, $data); }
    $args[] = (int)$id;
    db()->prepare("UPDATE asset_issues SET $set WHERE id=?")->execute($args);
    return '';
}

function asset_return($id, $post) {
    $a = asset_row($id); if (!$a) return 'That entry no longer exists.';
    $status = in_array($post['status'] ?? '', ['RETURNED', 'LOST', 'DAMAGED'], true) ? $post['status'] : 'RETURNED';
    db()->prepare("UPDATE asset_issues SET status=?, returned_on=?, returned_by=?, condition_returned=?, return_note=? WHERE id=?")
        ->execute([$status, trim((string)($post['returned_on'] ?? date('Y-m-d'))), user_name(current_user()),
                   isset(ASSET_CONDITIONS[$post['condition_returned'] ?? '']) ? $post['condition_returned'] : '',
                   substr(trim((string)($post['return_note'] ?? '')), 0, 400), (int)$id]);
    return '';
}

// Shared file capture (signed slip). Returns [name, mime, base64|null].
function asset_file_capture($file) {
    if (!$file || ($file['tmp_name'] ?? '') === '' || !is_uploaded_file($file['tmp_name'])) return ['', '', null];
    $bytes = (string)file_get_contents($file['tmp_name']);
    if ($bytes === '') return ['', '', null];
    if (function_exists('upload_reject_reason') && upload_reject_reason($bytes, $file['name'] ?? '', $file['type'] ?? '') !== '')
        return ['', '', null];
    return [substr((string)($file['name'] ?? 'slip'), 0, 255), (string)($file['type'] ?? ''), base64_encode($bytes)];
}

// ---- Screens ---------------------------------------------------------------
function ops_assets($route, $method) {
    ops_require(asset_can_view(), 'The asset register is for coordinators and managers.');
    assets_migrate();

    if ($route === 'asset-file') {
        $a = asset_row((int)($_GET['id'] ?? 0));
        if (!$a || empty($a['ack_file_data'])) { http_response_code(404); view('notfound'); return true; }
        send_uploaded_file(base64_decode((string)$a['ack_file_data']), $a['ack_file_name'] ?: 'slip', $a['ack_mime'] ?: 'application/octet-stream');
        return true;
    }

    if ($route === 'asset-issue-new' && $method === 'POST') {
        ops_require(asset_can_manage(), 'You cannot issue assets.');
        $err = asset_issue_add($_POST, $_FILES['ack_file'] ?? null);
        flash($err !== '' ? $err : 'Asset issued and recorded.', $err !== '' ? 'error' : 'success');
        redirect('/asset-register' . (!empty($_POST['person_id']) ? '?person=' . (int)$_POST['person_id'] : ''));
    }
    if ($route === 'asset-ack' && $method === 'POST') {
        ops_require(asset_can_manage(), 'You cannot record acknowledgements.');
        $err = asset_ack((int)($_POST['id'] ?? 0), $_POST, $_FILES['ack_file'] ?? null);
        flash($err !== '' ? $err : 'Acknowledgement recorded.', $err !== '' ? 'error' : 'success');
        redirect('/asset-register' . (!empty($_POST['person']) ? '?person=' . (int)$_POST['person'] : ''));
    }
    if ($route === 'asset-return' && $method === 'POST') {
        ops_require(asset_can_manage(), 'You cannot record returns.');
        $err = asset_return((int)($_POST['id'] ?? 0), $_POST);
        flash($err !== '' ? $err : 'Return recorded.', $err !== '' ? 'error' : 'success');
        redirect('/asset-register' . (!empty($_POST['person']) ? '?person=' . (int)$_POST['person'] : ''));
    }
    if ($route === 'asset-issue-delete' && $method === 'POST') {
        ops_require(is_admin_level() || is_master(), 'Only an administrator can delete an asset record.');
        $a = asset_row((int)($_POST['id'] ?? 0));
        if ($a) { db()->prepare("DELETE FROM asset_issues WHERE id=?")->execute([(int)$a['id']]); flash('Asset record removed.'); }
        redirect('/asset-register');
    }

    // The register.
    $fPerson = (int)($_GET['person'] ?? 0);
    $fType   = (string)($_GET['type'] ?? '');
    $fStatus = (string)($_GET['status'] ?? '');
    $q       = trim((string)($_GET['q'] ?? ''));
    $w = ['1=1']; $args = [];
    if ($fPerson) { $w[] = 'a.person_id=?'; $args[] = $fPerson; }
    if ($fType !== '' && isset(asset_types()[$fType])) { $w[] = 'a.asset_type=?'; $args[] = $fType; }
    if ($fStatus !== '' && isset(ASSET_STATUS[$fStatus])) { $w[] = 'a.status=?'; $args[] = $fStatus; }
    if ($q !== '') { $w[] = '(a.asset_name LIKE ? OR a.identifier LIKE ? OR i.name LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
    $rows = [];
    try {
        $rows = ops_all("SELECT a.*, i.name person_name, i.emp_code
                         FROM asset_issues a LEFT JOIN inspectors i ON i.id=a.person_id
                         WHERE " . implode(' AND ', $w) . " ORDER BY (a.status='ISSUED') DESC, a.id DESC", $args);
    } catch (Throwable $e) { if (!asset_missing_table($e)) throw $e; }

    view('ops/asset_register', [
        'rows' => $rows, 'counts' => asset_counts(),
        'people' => ops_all("SELECT id, name, emp_code FROM inspectors WHERE status='ACTIVE' ORDER BY name"),
        'types' => asset_types(), 'statuses' => ASSET_STATUS, 'conditions' => ASSET_CONDITIONS,
        'fPerson' => $fPerson, 'fType' => $fType, 'fStatus' => $fStatus, 'q' => $q,
        'canManage' => asset_can_manage(),
    ]);
    return true;
}
