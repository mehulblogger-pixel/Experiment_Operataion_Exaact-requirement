<?php
// ============================================================================
//  CONNECT — Disputes & Mediation  (slice K9b, additive)
//
//  Either side can raise a concern on a marketplace engagement (blueprint M14):
//  process, commercial, conduct — or the FINDING itself. The finding case is
//  special and the blueprint is emphatic: a dispute about whether the material
//  passed or was rejected is settled by review, and NEVER by withholding the
//  professional's fee. That rule is encoded here (cx_dispute_affects_fee()).
//
//  Additive cx_disputes table; the dispute lifecycle is documented in
//  docs/03-object-lifecycles.md. No new permission.
// ============================================================================

const CX_DISPUTE_CATEGORIES = ['PROCESS', 'COMMERCIAL', 'CONDUCT', 'FINDING'];
const CX_DISPUTE_STATUSES   = ['OPEN', 'UNDER_REVIEW', 'RESOLVED', 'WITHDRAWN'];
const CX_DISPUTE_TRANSITIONS = [
    'OPEN'         => ['UNDER_REVIEW', 'RESOLVED', 'WITHDRAWN'],
    'UNDER_REVIEW' => ['RESOLVED', 'WITHDRAWN'],
    'RESOLVED'     => [], 'WITHDRAWN' => [],
];

function cx_dispute_can_transition($from, $to) {
    return in_array(strtoupper((string)$to), CX_DISPUTE_TRANSITIONS[strtoupper((string)$from)] ?? [], true);
}

/** The whole point of M14: a FINDING dispute never touches the fee. */
function cx_dispute_affects_fee($category) {
    return strtoupper((string)$category) !== 'FINDING';
}

function connect_disputes_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_disputes (
        id $pk, requirement_id INT DEFAULT 0, ref_code VARCHAR(24) DEFAULT '',
        raised_by_side VARCHAR(10) DEFAULT 'CLIENT', category VARCHAR(16) DEFAULT 'PROCESS',
        subject VARCHAR(200) DEFAULT '', detail TEXT,
        status VARCHAR(16) DEFAULT 'OPEN', resolution TEXT,
        affects_fee INT DEFAULT 1,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '',
        resolved_by VARCHAR(150) DEFAULT '', resolved_at VARCHAR(30) DEFAULT '',
        updated_at VARCHAR(30) DEFAULT '')");
}

function cx_dispute_next_code() {
    $n = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM cx_disputes") + 1;
    return 'CX-DIS-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/** Raise a dispute on a requirement. Returns the new id, or 0 if invalid. */
function cx_dispute_raise($requirementId, array $in) {
    connect_disputes_migrate();
    $requirementId = (int)$requirementId;
    if ($requirementId <= 0) return 0;
    $cat = strtoupper((string)($in['category'] ?? 'PROCESS'));
    if (!in_array($cat, CX_DISPUTE_CATEGORIES, true)) $cat = 'PROCESS';
    $side = strtoupper((string)($in['raised_by_side'] ?? 'CLIENT'));
    if (!in_array($side, ['CLIENT', 'PRO'], true)) $side = 'CLIENT';
    if (trim((string)($in['subject'] ?? '')) === '') return 0;
    db()->prepare("INSERT INTO cx_disputes (requirement_id,ref_code,raised_by_side,category,subject,detail,status,affects_fee,created_by,created_at,updated_at)
                   VALUES (?,?,?,?,?,?, 'OPEN', ?, ?,?,?)")
        ->execute([$requirementId, cx_dispute_next_code(), $side, $cat,
                   trim((string)$in['subject']), trim((string)($in['detail'] ?? '')),
                   cx_dispute_affects_fee($cat) ? 1 : 0,
                   function_exists('user_name') ? user_name(current_user()) : '', date('c'), date('c')]);
    return (int)db()->lastInsertId();
}

function cx_dispute_get($id) { return ops_one("SELECT * FROM cx_disputes WHERE id=?", [(int)$id]) ?: null; }

/** Move a dispute to a new status if legal; a RESOLVED move records the outcome. */
function cx_dispute_transition($id, $to, $resolution = '') {
    $d = cx_dispute_get($id); if (!$d) return false;
    $to = strtoupper((string)$to);
    if (!cx_dispute_can_transition($d['status'], $to)) return false;
    if ($to === 'RESOLVED') {
        db()->prepare("UPDATE cx_disputes SET status='RESOLVED', resolution=?, resolved_by=?, resolved_at=?, updated_at=? WHERE id=?")
            ->execute([trim((string)$resolution), function_exists('user_name') ? user_name(current_user()) : '', date('c'), date('c'), (int)$id]);
    } else {
        db()->prepare("UPDATE cx_disputes SET status=?, updated_at=? WHERE id=?")->execute([$to, date('c'), (int)$id]);
    }
    return true;
}

function cx_disputes_for_requirement($requirementId) {
    connect_disputes_migrate();
    return ops_all("SELECT * FROM cx_disputes WHERE requirement_id=? ORDER BY id DESC", [(int)$requirementId]) ?: [];
}
function cx_disputes_open_count() {
    try { return (int)ops_val("SELECT COUNT(*) FROM cx_disputes WHERE status IN ('OPEN','UNDER_REVIEW')"); }
    catch (Throwable $e) { return 0; }
}
