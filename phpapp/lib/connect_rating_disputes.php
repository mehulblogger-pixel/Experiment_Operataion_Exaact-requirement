<?php
// ============================================================================
//  RATING-INTEGRITY DISPUTES  (Review & Reputation module — additive)
//
//  Genuine reviews are the marketplace's trust. So any party who believes a rating
//  about them is wrong — unfair, factually incorrect, retaliatory or fraudulent — can
//  report it, and the moderation desk INVESTIGATES and decides. This is distinct from
//  the work/fee dispute (cx_disputes, K9b): that is about the job; this is about the
//  RATING itself.
//
//  New object cx_rating_disputes; no existing object touched. Lifecycle mirrors the
//  marketplace dispute (OPEN → UNDER_REVIEW → RESOLVED, + WITHDRAWN). The staff gate
//  reuses the moderation level (connect_market_can) — NO new permission. On RESOLVED the
//  desk records an OUTCOME: UPHELD (the rating stands), ANNOTATED (a public note is
//  attached), or REMOVED (the rating is hidden from summaries — never deleted).
// ============================================================================

const CX_RDISPUTE_TRANSITIONS = [
    'OPEN'         => ['UNDER_REVIEW', 'RESOLVED', 'WITHDRAWN'],
    'UNDER_REVIEW' => ['RESOLVED', 'WITHDRAWN'],
    'RESOLVED'     => [],
    'WITHDRAWN'    => [],
];

function connect_rating_disputes_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_rating_disputes (
        id $pk, ref_code VARCHAR(24) DEFAULT '', rating_id INT DEFAULT 0,
        raised_by_kind VARCHAR(8) DEFAULT '', raised_by_id INT DEFAULT 0, raised_by_name VARCHAR(200) DEFAULT '',
        category VARCHAR(16) DEFAULT 'UNFAIR', subject VARCHAR(200) DEFAULT '', detail TEXT DEFAULT '',
        status VARCHAR(14) DEFAULT 'OPEN', outcome VARCHAR(12) DEFAULT '',
        resolution TEXT DEFAULT '', resolved_by VARCHAR(150) DEFAULT '', resolved_at VARCHAR(30) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) act_index('cx_rating_disputes', 'ix_rdisp', '(status, rating_id)');
}

function cx_rating_dispute_categories() {
    return ['UNFAIR' => 'Unfair / not deserved', 'FACTUAL' => 'Factually wrong', 'RETALIATORY' => 'Retaliatory', 'FRAUDULENT' => 'Fake / fraudulent', 'OTHER' => 'Other'];
}
function cx_rating_dispute_outcomes() {
    return ['UPHELD' => 'Rating stands', 'ANNOTATED' => 'Note attached to the rating', 'REMOVED' => 'Rating removed from scores'];
}
function cx_rating_dispute_can_transition($from, $to) {
    return in_array(strtoupper((string)$to), CX_RDISPUTE_TRANSITIONS[strtoupper((string)$from)] ?? [], true);
}

function cx_rating_dispute_next_code() {
    $n = (int) ops_val("SELECT COALESCE(MAX(id),0) FROM cx_rating_disputes") + 1;
    return 'RD-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
}

/**
 * Raise a dispute about a rating. Any affected party (or staff on their behalf) may do
 * so, once per rating while an open one exists. Returns the new id, or 0 if invalid.
 */
function cx_rating_dispute_raise($ratingId, array $in) {
    connect_rating_disputes_migrate();
    $ratingId = (int)$ratingId;
    if (!function_exists('cx_rating_get') || !cx_rating_get($ratingId)) return 0;
    // One live dispute per rating.
    if (ops_val("SELECT id FROM cx_rating_disputes WHERE rating_id=? AND status IN ('OPEN','UNDER_REVIEW') LIMIT 1", [$ratingId])) return 0;
    $cat = strtoupper((string)($in['category'] ?? 'UNFAIR'));
    if (!isset(cx_rating_dispute_categories()[$cat])) $cat = 'UNFAIR';
    $kind = strtoupper((string)($in['raised_by_kind'] ?? '')); if (!in_array($kind, ['CLIENT', 'PRO', 'STAFF'], true)) $kind = 'STAFF';
    $now = date('c');
    db()->prepare("INSERT INTO cx_rating_disputes (ref_code,rating_id,raised_by_kind,raised_by_id,raised_by_name,category,subject,detail,status,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?,?, 'OPEN', ?, ?)")
        ->execute([cx_rating_dispute_next_code(), $ratingId, $kind, (int)($in['raised_by_id'] ?? 0), (string)($in['raised_by_name'] ?? ''),
                   $cat, (string)($in['subject'] ?? ''), (string)($in['detail'] ?? ''), $now, $now]);
    return (int)db()->lastInsertId();
}

function cx_rating_dispute_get($id) { connect_rating_disputes_migrate(); return ops_one("SELECT * FROM cx_rating_disputes WHERE id=?", [(int)$id]) ?: null; }
function cx_rating_disputes_open() {
    connect_rating_disputes_migrate();
    return ops_all("SELECT * FROM cx_rating_disputes WHERE status IN ('OPEN','UNDER_REVIEW') ORDER BY id DESC") ?: [];
}
function cx_rating_disputes_all($limit = 200) {
    connect_rating_disputes_migrate();
    return ops_all("SELECT * FROM cx_rating_disputes ORDER BY id DESC LIMIT " . (int)$limit) ?: [];
}
function cx_rating_disputes_open_count() { connect_rating_disputes_migrate(); return (int) ops_val("SELECT COUNT(*) FROM cx_rating_disputes WHERE status IN ('OPEN','UNDER_REVIEW')"); }

/** Move a dispute to UNDER_REVIEW (start investigating). */
function cx_rating_dispute_investigate($id, $by = '') {
    $d = cx_rating_dispute_get($id); if (!$d) return [false, 'Not found.'];
    if (!cx_rating_dispute_can_transition($d['status'], 'UNDER_REVIEW')) return [false, 'Cannot investigate from ' . strtolower((string)$d['status']) . '.'];
    db()->prepare("UPDATE cx_rating_disputes SET status='UNDER_REVIEW', updated_at=? WHERE id=?")->execute([date('c'), (int)$id]);
    return [true, 'Investigation started.'];
}

/**
 * Resolve a dispute with an outcome. UPHELD → the rating stands; ANNOTATED → a public
 * note is attached to the rating; REMOVED → the rating is hidden from all scores (never
 * deleted). Returns [ok, message].
 */
function cx_rating_dispute_resolve($id, $outcome, $note = '', $by = '') {
    $d = cx_rating_dispute_get($id); if (!$d) return [false, 'Not found.'];
    if (!cx_rating_dispute_can_transition($d['status'], 'RESOLVED')) return [false, 'Cannot resolve from ' . strtolower((string)$d['status']) . '.'];
    $outcome = strtoupper((string)$outcome); if (!isset(cx_rating_dispute_outcomes()[$outcome])) return [false, 'Choose a valid outcome.'];
    // Apply the outcome to the rating.
    if ($outcome === 'REMOVED') {
        db()->prepare("UPDATE cx_ratings SET hidden=1, moderation_note=? WHERE id=?")->execute([mb_substr((string)$note, 0, 400), (int)$d['rating_id']]);
    } elseif ($outcome === 'ANNOTATED') {
        db()->prepare("UPDATE cx_ratings SET moderation_note=? WHERE id=?")->execute([mb_substr((string)$note, 0, 400), (int)$d['rating_id']]);
    }
    db()->prepare("UPDATE cx_rating_disputes SET status='RESOLVED', outcome=?, resolution=?, resolved_by=?, resolved_at=?, updated_at=? WHERE id=?")
        ->execute([$outcome, (string)$note, (string)$by, date('c'), date('c'), (int)$id]);
    return [true, 'Resolved — ' . strtolower((string)(cx_rating_dispute_outcomes()[$outcome] ?? $outcome)) . '.'];
}

/** Withdraw a dispute (the raiser changed their mind). */
function cx_rating_dispute_withdraw($id) {
    $d = cx_rating_dispute_get($id); if (!$d) return [false, 'Not found.'];
    if (!cx_rating_dispute_can_transition($d['status'], 'WITHDRAWN')) return [false, 'Cannot withdraw now.'];
    db()->prepare("UPDATE cx_rating_disputes SET status='WITHDRAWN', updated_at=? WHERE id=?")->execute([date('c'), (int)$id]);
    return [true, 'Withdrawn.'];
}

/** Route handler — the rating-integrity moderation desk (coordinator/master, no new permission). */
function ops_rating_disputes($method) {
    ops_require(function_exists('connect_market_can') && connect_market_can(), 'The rating-integrity desk is for the marketplace moderation role.');
    connect_rating_disputes_migrate();
    if ($method === 'POST') {
        $id = (int)($_POST['id'] ?? 0); $act = (string)($_POST['action'] ?? '');
        $by = function_exists('user_name') && function_exists('current_user') ? (string)user_name(current_user()) : '';
        if ($act === 'investigate') { [$ok, $m] = cx_rating_dispute_investigate($id, $by); flash($m, $ok ? 'success' : 'error'); }
        elseif ($act === 'resolve') { [$ok, $m] = cx_rating_dispute_resolve($id, (string)($_POST['outcome'] ?? ''), (string)($_POST['resolution'] ?? ''), $by); flash($m, $ok ? 'success' : 'error'); }
        elseif ($act === 'withdraw'){ [$ok, $m] = cx_rating_dispute_withdraw($id); flash($m, $ok ? 'success' : 'error'); }
        redirect('/rating-disputes');
    }
    // Join each dispute to the rating it concerns, for context.
    $rows = cx_rating_disputes_all();
    foreach ($rows as &$r) { $r['_rating'] = function_exists('cx_rating_get') ? cx_rating_get((int)$r['rating_id']) : null; }
    unset($r);
    view('ops/rating_disputes', [
        'rows'       => $rows,
        'categories' => cx_rating_dispute_categories(),
        'outcomes'   => cx_rating_dispute_outcomes(),
    ]);
    return true;
}
