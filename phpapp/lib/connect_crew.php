<?php
// ============================================================================
//  CONNECT — Crew / bulk booking  (slice M10, additive)
//
//  A single requirement can be a CREW: a manifest of positions (role × quantity
//  × rate), for shutdown / turnaround-scale hiring (blueprint M10). A single-role
//  requirement is simply a manifest of one — so nothing changes for the common
//  case; a crew adds positions. Additive cx_positions table; the award→invoice
//  bridge bills the whole manifest.
// ============================================================================

function connect_crew_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_positions (
        id $pk, requirement_id INT DEFAULT 0, seq INT DEFAULT 0,
        role VARCHAR(160) DEFAULT '', discipline_code VARCHAR(40) DEFAULT '',
        quantity INT DEFAULT 1, rate REAL DEFAULT 0, unit VARCHAR(20) DEFAULT 'day',
        shift_pattern VARCHAR(60) DEFAULT '', notes VARCHAR(300) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '')");
}

/** Add a position to a requirement's crew manifest. Returns the new id. */
function cx_position_add($requirementId, array $in) {
    connect_crew_migrate();
    $requirementId = (int)$requirementId;
    if ($requirementId <= 0 || trim((string)($in['role'] ?? '')) === '') return 0;
    $seq = (int)ops_val("SELECT COALESCE(MAX(seq),0) FROM cx_positions WHERE requirement_id=?", [$requirementId]) + 1;
    db()->prepare("INSERT INTO cx_positions (requirement_id,seq,role,discipline_code,quantity,rate,unit,shift_pattern,notes,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$requirementId, $seq, trim((string)$in['role']), (string)($in['discipline_code'] ?? ''),
                   max(1, (int)($in['quantity'] ?? 1)), (float)($in['rate'] ?? 0), (string)($in['unit'] ?? 'day'),
                   trim((string)($in['shift_pattern'] ?? '')), trim((string)($in['notes'] ?? '')), date('c')]);
    return (int)db()->lastInsertId();
}

function cx_position_delete($id, $requirementId) {
    connect_crew_migrate();
    db()->prepare("DELETE FROM cx_positions WHERE id=? AND requirement_id=?")->execute([(int)$id, (int)$requirementId]);
    return true;
}

function cx_positions_for($requirementId) {
    connect_crew_migrate();
    return ops_all("SELECT * FROM cx_positions WHERE requirement_id=? ORDER BY seq, id", [(int)$requirementId]) ?: [];
}

/** True when a requirement is a crew (has an explicit position manifest). */
function cx_is_crew($requirementId) {
    connect_crew_migrate();
    return (int)ops_val("SELECT COUNT(*) FROM cx_positions WHERE requirement_id=?", [(int)$requirementId]) > 0;
}

/** Crew rollup: [positions, headcount, value] summed across the manifest. */
function cx_crew_summary($requirementId) {
    $rows = cx_positions_for($requirementId);
    $head = 0; $value = 0.0;
    foreach ($rows as $p) { $head += (int)$p['quantity']; $value += (int)$p['quantity'] * (float)$p['rate']; }
    return ['positions' => count($rows), 'headcount' => $head, 'value' => round($value, 2)];
}
