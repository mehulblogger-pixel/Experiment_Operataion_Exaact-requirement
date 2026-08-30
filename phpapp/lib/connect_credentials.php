<?php
// ============================================================================
//  CONNECT — Passport credentials & project experience  (K0+, additive)
//
//  Structured certifications (type/authority/number/level/discipline/issue/
//  expiry/document/verification + expiry status) and first-class project
//  experience (role/client/industry/location/equipment/scope/dates) on the
//  professional passport. Certifications link to the taxonomy's CERTIFICATION
//  nodes so search + matching benefit; the cert record stays the authoritative
//  single source of truth and mirrors into cx_profile_tax for discovery.
//
//  STRICTLY ADDITIVE: two new cx_ tables. The free-text skills / cert_codes and
//  every existing screen keep working.
// ============================================================================

function connect_cred_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_pro_certs (
        id $pk, pro_id INT DEFAULT 0, node_id INT DEFAULT 0,
        name VARCHAR(200) DEFAULT '', authority VARCHAR(160) DEFAULT '', cert_number VARCHAR(120) DEFAULT '',
        level VARCHAR(60) DEFAULT '', discipline VARCHAR(80) DEFAULT '',
        issue_date VARCHAR(20) DEFAULT '', expiry_date VARCHAR(20) DEFAULT '',
        file_id INT DEFAULT 0, verified INT DEFAULT 0, created_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_pro_projects (
        id $pk, pro_id INT DEFAULT 0,
        title VARCHAR(200) DEFAULT '', role VARCHAR(160) DEFAULT '', client VARCHAR(160) DEFAULT '',
        industry VARCHAR(120) DEFAULT '', location VARCHAR(160) DEFAULT '', equipment VARCHAR(400) DEFAULT '',
        scope TEXT, start_date VARCHAR(20) DEFAULT '', end_date VARCHAR(20) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    foreach (["CREATE INDEX ix_cx_procerts ON cx_pro_certs (pro_id)",
              "CREATE INDEX ix_cx_proproj ON cx_pro_projects (pro_id)"] as $ix) { try { db()->exec($ix); } catch (Throwable $e) {} }
}

/** Certificate expiry state from a date. '' when no expiry recorded. */
function connect_cred_cert_status($expiry) {
    $e = trim((string)$expiry); if ($e === '') return '';
    $days = (strtotime($e) - time()) / 86400;
    if ($days < 0) return 'EXPIRED';
    if ($days <= 60) return 'EXPIRING';
    return 'VALID';
}

// ---- Certifications ---------------------------------------------------------

/** Add/update a certification. Links to a CERTIFICATION taxonomy node (given or
 *  resolved by name) and mirrors into cx_profile_tax so search finds it. */
function connect_cred_cert_save($proId, array $in) {
    connect_cred_migrate();
    $proId = (int)$proId; $name = trim((string)($in['name'] ?? '')); if ($proId <= 0 || $name === '') return [false, 'A certification name is required.'];
    $nodeId = (int)($in['node_id'] ?? 0);
    if ($nodeId <= 0 && function_exists('connect_tax_resolve')) {
        $hit = connect_tax_resolve($name, ['CERTIFICATION'])[0] ?? null; $nodeId = $hit ? (int)$hit['id'] : 0;
    }
    $verified = !empty($in['verified']) ? 1 : 0;
    $cols = [$nodeId, $name, trim((string)($in['authority'] ?? '')), trim((string)($in['cert_number'] ?? '')),
             trim((string)($in['level'] ?? '')), trim((string)($in['discipline'] ?? '')),
             trim((string)($in['issue_date'] ?? '')), trim((string)($in['expiry_date'] ?? '')), (int)($in['file_id'] ?? 0), $verified];
    $id = (int)($in['id'] ?? 0);
    if ($id > 0 && (int)ops_val("SELECT COUNT(*) FROM cx_pro_certs WHERE id=? AND pro_id=?", [$id, $proId])) {
        db()->prepare("UPDATE cx_pro_certs SET node_id=?, name=?, authority=?, cert_number=?, level=?, discipline=?, issue_date=?, expiry_date=?, file_id=?, verified=? WHERE id=? AND pro_id=?")
            ->execute(array_merge($cols, [$id, $proId]));
    } else {
        db()->prepare("INSERT INTO cx_pro_certs (node_id,name,authority,cert_number,level,discipline,issue_date,expiry_date,file_id,verified,pro_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute(array_merge($cols, [$proId, date('c')]));
        $id = (int)db()->lastInsertId();
    }
    // Mirror into the taxonomy link so the one-keyword search + matching find it.
    if ($nodeId > 0 && function_exists('connect_profile_tax_attach'))
        connect_profile_tax_attach($proId, $nodeId, 'CERTIFICATION', ['verified' => $verified, 'source' => 'cert']);
    return [true, 'Saved.', $id];
}
function connect_cred_cert_delete($id, $proId) {
    connect_cred_migrate();
    db()->prepare("DELETE FROM cx_pro_certs WHERE id=? AND pro_id=?")->execute([(int)$id, (int)$proId]);
    return true;
}
/** A professional's certifications, newest expiry-relevant first, with status. */
function connect_cred_certs($proId) {
    connect_cred_migrate();
    $rows = ops_all("SELECT * FROM cx_pro_certs WHERE pro_id=? ORDER BY (expiry_date=''), expiry_date, name", [(int)$proId]) ?: [];
    foreach ($rows as &$r) $r['status'] = connect_cred_cert_status($r['expiry_date']); unset($r);
    return $rows;
}
/** Certs expiring/expired across the pool (for an alerts panel later). */
function connect_cred_expiring($proId) {
    return array_values(array_filter(connect_cred_certs($proId), fn($c) => in_array($c['status'], ['EXPIRING', 'EXPIRED'], true)));
}

// ---- Project experience -----------------------------------------------------

function connect_cred_project_save($proId, array $in) {
    connect_cred_migrate();
    $proId = (int)$proId; $title = trim((string)($in['title'] ?? '')); if ($proId <= 0 || $title === '') return [false, 'A project title is required.'];
    $cols = [$title, trim((string)($in['role'] ?? '')), trim((string)($in['client'] ?? '')), trim((string)($in['industry'] ?? '')),
             trim((string)($in['location'] ?? '')), trim((string)($in['equipment'] ?? '')), trim((string)($in['scope'] ?? '')),
             trim((string)($in['start_date'] ?? '')), trim((string)($in['end_date'] ?? ''))];
    $id = (int)($in['id'] ?? 0);
    if ($id > 0 && (int)ops_val("SELECT COUNT(*) FROM cx_pro_projects WHERE id=? AND pro_id=?", [$id, $proId])) {
        db()->prepare("UPDATE cx_pro_projects SET title=?, role=?, client=?, industry=?, location=?, equipment=?, scope=?, start_date=?, end_date=? WHERE id=? AND pro_id=?")
            ->execute(array_merge($cols, [$id, $proId]));
    } else {
        db()->prepare("INSERT INTO cx_pro_projects (title,role,client,industry,location,equipment,scope,start_date,end_date,pro_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute(array_merge($cols, [$proId, date('c')]));
        $id = (int)db()->lastInsertId();
    }
    return [true, 'Saved.', $id];
}
function connect_cred_project_delete($id, $proId) {
    connect_cred_migrate();
    db()->prepare("DELETE FROM cx_pro_projects WHERE id=? AND pro_id=?")->execute([(int)$id, (int)$proId]);
    return true;
}
function connect_cred_projects($proId) {
    connect_cred_migrate();
    return ops_all("SELECT * FROM cx_pro_projects WHERE pro_id=? ORDER BY (start_date=''), start_date DESC, id DESC", [(int)$proId]) ?: [];
}
