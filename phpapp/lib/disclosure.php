<?php
// ============================================================================
//  Public-disclosure consent — ISO/IEC 17020 §4.2 (confidentiality)
//
//  §4.2 requires that when the body intends to place information about a client
//  into the public domain, the client is told in advance. The confidentiality
//  module covers undertakings, NDAs and breaches, but there was no record of
//  this advance consent. This is that record: for each intended disclosure — a
//  public register listing, a case study, a website reference — who consented,
//  to what, and when.
//
//  Lightweight single-screen register (list + add/edit), configuration-first in
//  that every row is editable and the scope is free text.
// ============================================================================

const DISCLOSURE_STATUSES = ['REQUESTED', 'CONSENTED', 'DECLINED', 'WITHDRAWN'];

function disclosure_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS disclosure_consents (
            id $pk, partner_id INT NULL, partner_name VARCHAR(200) DEFAULT '', subject VARCHAR(300) DEFAULT '',
            scope VARCHAR(200) DEFAULT '', status VARCHAR(20) DEFAULT 'REQUESTED', requested_on VARCHAR(20) DEFAULT '',
            consent_on VARCHAR(20) DEFAULT '', consent_by VARCHAR(150) DEFAULT '', note VARCHAR(400) DEFAULT '',
            created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) { /* never let a table add break boot */ }
}

function disclosure_can_view() {
    return is_master() || (function_exists('can') && (can('mod.confidentiality.view') || can('mod.datacontrol.view')
        || can('mod.clients.view')));
}
function disclosure_can_manage() {
    return is_master() || (function_exists('can') && (can('mod.confidentiality.view') || can('mod.datacontrol.view')));
}

function disclosure_all($filter = []) {
    disclosure_migrate();
    $where = '1=1'; $args = [];
    if (!empty($filter['q'])) { $where .= " AND (partner_name LIKE ? OR subject LIKE ?)";
        $like = '%' . $filter['q'] . '%'; array_push($args, $like, $like); }
    return ops_all("SELECT * FROM disclosure_consents WHERE $where ORDER BY id DESC", $args) ?: [];
}
function disclosure_get($id) { disclosure_migrate(); return ops_one("SELECT * FROM disclosure_consents WHERE id=?", [(int)$id]); }
function disclosure_counts() {
    disclosure_migrate();
    return ['pending' => (int)ops_val("SELECT COUNT(*) FROM disclosure_consents WHERE status='REQUESTED'")];
}

function disclosure_save($b, $id = 0) {
    disclosure_migrate();
    $subject = trim((string)($b['subject'] ?? ''));
    if ($subject === '') return [0, 'Say what information is to be disclosed.'];
    $pid = ($b['partner_id'] ?? '') !== '' ? (int)$b['partner_id'] : null;
    $pname = trim((string)($b['partner_name'] ?? ''));
    if ($pid && $pname === '') {
        $p = ops_one("SELECT COALESCE(display_name,legal_name) nm FROM business_partners WHERE id=?", [$pid]);
        $pname = $p['nm'] ?? '';
    }
    $status = in_array($b['status'] ?? '', DISCLOSURE_STATUSES, true) ? $b['status'] : 'REQUESTED';
    $cols = [
        'partner_id'   => $pid,
        'partner_name' => substr($pname, 0, 200),
        'subject'      => substr($subject, 0, 300),
        'scope'        => substr(trim((string)($b['scope'] ?? '')), 0, 200),
        'status'       => $status,
        'requested_on' => substr(trim((string)($b['requested_on'] ?? '')), 0, 20),
        'consent_on'   => $status === 'CONSENTED' ? (substr(trim((string)($b['consent_on'] ?? '')), 0, 20) ?: date('Y-m-d')) : substr(trim((string)($b['consent_on'] ?? '')), 0, 20),
        'consent_by'   => substr(trim((string)($b['consent_by'] ?? '')), 0, 150),
        'note'         => substr(trim((string)($b['note'] ?? '')), 0, 400),
    ];
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($cols)));
        db()->prepare("UPDATE disclosure_consents SET $set, updated_at=? WHERE id=?")
            ->execute(array_merge(array_values($cols), [date('c'), (int)$id]));
        return [(int)$id, ''];
    }
    $cols['created_by'] = user_name(current_user());
    $cols['created_at'] = date('c');
    $cols['updated_at'] = date('c');
    if ($cols['requested_on'] === '') $cols['requested_on'] = date('Y-m-d');
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO disclosure_consents (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    $newId = (int)db()->lastInsertId();
    if (function_exists('idems_log')) try { idems_log('disclosure', $newId, 'CREATED', ['reason' => $cols['subject']]); } catch (Throwable $e) {}
    return [$newId, ''];
}

function ops_disclosure($route, $method) {
    disclosure_migrate();
    ops_require(disclosure_can_view(), 'The disclosure-consent register is not available to you.');
    $canEdit = disclosure_can_manage();

    if ($route === 'disclosure-del' && $method === 'POST') {
        ops_require($canEdit, 'You cannot change this register.');
        db()->prepare("DELETE FROM disclosure_consents WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        flash('Entry removed.');
        redirect('/disclosure');
    }
    if (($route === 'disclosure-new' || $route === 'disclosure-edit') && $method === 'POST') {
        ops_require($canEdit, 'You cannot change this register.');
        $rid = $route === 'disclosure-edit' ? (int)($_GET['id'] ?? $_POST['id'] ?? 0) : 0;
        [$sid, $err] = disclosure_save($_POST, $rid);
        flash($err ?: 'Disclosure consent saved.', $err ? 'error' : 'success');
        redirect('/disclosure');
    }
    $editId = (int)($_GET['edit'] ?? 0);
    $clients = ops_all("SELECT id, COALESCE(display_name,legal_name) nm FROM business_partners WHERE is_client=1 AND status='ACTIVE' ORDER BY nm") ?: [];
    view('ops/disclosure', ['rows' => disclosure_all(['q' => trim((string)($_GET['q'] ?? ''))]),
        'canEdit' => $canEdit, 'edit' => $editId ? disclosure_get($editId) : null,
        'clients' => $clients, 'statuses' => DISCLOSURE_STATUSES, 'q' => trim((string)($_GET['q'] ?? ''))]);
    return true;
}
