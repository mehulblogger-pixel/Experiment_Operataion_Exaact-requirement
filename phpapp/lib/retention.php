<?php
// ============================================================================
//  Record-retention schedule — ISO/IEC 17020 §8.4 (control of records)
//
//  A first-class register of how long each kind of record is kept and what
//  happens to it at the end. The app already enforces some of this in code (the
//  audit-trail 180-day floor, identity-document redaction dates); this is the
//  human-readable schedule an assessor asks for, seeded with sensible defaults
//  and fully editable.
//
//  Deliberately lightweight: a single editable list, no per-row detail screen.
//  Configuration-first — every row is editable, and a body adds its own.
// ============================================================================

function retention_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS retention_rules (
            id $pk, record_type VARCHAR(200) DEFAULT '', retention_period VARCHAR(60) DEFAULT '',
            basis VARCHAR(200) DEFAULT '', disposal_action VARCHAR(120) DEFAULT '', owner VARCHAR(150) DEFAULT '',
            notes VARCHAR(400) DEFAULT '', active INT DEFAULT 1, sort_order INT DEFAULT 0,
            created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) { return; }

    // Seed a starting schedule once, matching what the app already does, so it is
    // useful on day one. Every row is editable and more can be added.
    try {
        if ((int)ops_val("SELECT COUNT(*) FROM retention_rules") === 0) {
            $seed = [
                ['Inspection reports & certificates', '7 years', 'Client / statutory expectation', 'Archive, then secure delete'],
                ['Technical records & evidence', '7 years', 'ISO/IEC 17020 §7.3', 'Archive, then secure delete'],
                ['Compliance audit trail', 'Min 180 days', 'CERT-In direction', 'Retained, never trimmed inside the floor'],
                ['Identity documents', 'Per stated purpose', 'DPDP purpose limitation', 'Redacted on the retention date'],
                ['Complaints & appeals', '5 years', 'ISO/IEC 17020 §7.5', 'Archive, then delete'],
                ['NCR / CAPA records', '5 years', 'ISO/IEC 17020 §8.7', 'Archive, then delete'],
                ['Internal audit & management review', '5 years', 'ISO/IEC 17020 §8.8 / §8.9', 'Archive, then delete'],
                ['Calibration certificates', 'Life of instrument + 3 years', 'ISO/IEC 17020 §6.2', 'Delete after disposal'],
                ['Financial records & invoices', '8 years', 'Income-tax / GST', 'Archive, then delete'],
            ];
            $i = 0;
            foreach ($seed as [$t, $p, $b, $d]) {
                db()->prepare("INSERT INTO retention_rules (record_type,retention_period,basis,disposal_action,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,1,?,?)")
                    ->execute([$t, $p, $b, $d, $i++, date('c'), date('c')]);
            }
        }
    } catch (Throwable $e) { /* seeding is best-effort */ }
}

function retention_can_view() {
    if (function_exists('accredited_pack_on') && !accredited_pack_on()) return false;
    return is_master() || (function_exists('can') && (can('mod.datacontrol.view') || can('mod.audits.view') || can('settings.manage')));
}
function retention_can_manage() {
    return is_master() || (function_exists('can') && (can('mod.datacontrol.view') || can('settings.manage')));
}

function retention_all($includeInactive = false) {
    retention_migrate();
    return ops_all("SELECT * FROM retention_rules " . ($includeInactive ? '' : 'WHERE active=1 ') . "ORDER BY sort_order, record_type") ?: [];
}
function retention_get($id) { retention_migrate(); return ops_one("SELECT * FROM retention_rules WHERE id=?", [(int)$id]); }

function retention_save($b, $id = 0) {
    retention_migrate();
    $rt = trim((string)($b['record_type'] ?? ''));
    if ($rt === '') return [0, 'A record type is required.'];
    $cols = [
        'record_type'     => substr($rt, 0, 200),
        'retention_period'=> substr(trim((string)($b['retention_period'] ?? '')), 0, 60),
        'basis'           => substr(trim((string)($b['basis'] ?? '')), 0, 200),
        'disposal_action' => substr(trim((string)($b['disposal_action'] ?? '')), 0, 120),
        'owner'           => substr(trim((string)($b['owner'] ?? '')), 0, 150),
        'notes'           => substr(trim((string)($b['notes'] ?? '')), 0, 400),
    ];
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k=?", array_keys($cols)));
        db()->prepare("UPDATE retention_rules SET $set, updated_at=? WHERE id=?")
            ->execute(array_merge(array_values($cols), [date('c'), (int)$id]));
        return [(int)$id, ''];
    }
    $cols['sort_order'] = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+1 FROM retention_rules");
    $cols['active']     = 1;
    $cols['created_at'] = date('c');
    $cols['updated_at'] = date('c');
    $keys = array_keys($cols);
    db()->prepare("INSERT INTO retention_rules (" . implode(',', $keys) . ") VALUES (" . implode(',', array_fill(0, count($keys), '?')) . ")")
        ->execute(array_values($cols));
    return [(int)db()->lastInsertId(), ''];
}

function ops_retention($route, $method) {
    retention_migrate();
    ops_require(retention_can_view(), 'The retention schedule is not available to you.');
    $canEdit = retention_can_manage();

    if ($route === 'retention-del' && $method === 'POST') {
        ops_require($canEdit, 'You cannot change the retention schedule.');
        db()->prepare("UPDATE retention_rules SET active=0, updated_at=? WHERE id=?")->execute([date('c'), (int)($_POST['id'] ?? 0)]);
        flash('Row removed.');
        redirect('/retention');
    }
    if (($route === 'retention-new' || $route === 'retention-edit') && $method === 'POST') {
        ops_require($canEdit, 'You cannot change the retention schedule.');
        $rid = $route === 'retention-edit' ? (int)($_GET['id'] ?? $_POST['id'] ?? 0) : 0;
        [$sid, $err] = retention_save($_POST, $rid);
        flash($err ?: 'Retention schedule saved.', $err ? 'error' : 'success');
        redirect('/retention');
    }
    // Single screen: the schedule plus an add/edit form.
    $editId = (int)($_GET['edit'] ?? 0);
    view('ops/retention', ['rows' => retention_all(), 'canEdit' => $canEdit,
        'edit' => $editId ? retention_get($editId) : null]);
    return true;
}
