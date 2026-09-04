<?php
// ============================================================================
//  CONNECT — Two-way Ratings  (slice K9, additive)
//
//  After a marketplace engagement is awarded/closed, both sides rate each other
//  (blueprint M13): the client rates the professional, the professional rates
//  the client. Structured, recency-friendly, one rating per direction per
//  engagement. Additive cx_ratings table; no new permission or lifecycle status.
// ============================================================================

const CX_RATING_DIRECTIONS = ['CLIENT_TO_PRO', 'PRO_TO_CLIENT'];

function connect_ratings_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_ratings (
        id $pk, requirement_id INT DEFAULT 0, application_id INT DEFAULT 0,
        direction VARCHAR(16) DEFAULT 'CLIENT_TO_PRO',
        rater_party_id INT DEFAULT 0, ratee_inspector_id INT DEFAULT 0, ratee_party_id INT DEFAULT 0,
        stars INT DEFAULT 0, competency INT DEFAULT 0, communication INT DEFAULT 0,
        punctuality INT DEFAULT 0, professionalism INT DEFAULT 0, would_rehire INT DEFAULT 0,
        comment TEXT, created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    // Reputation-integrity additions: a professional rating a CLIENT records payment
    // behaviour (the payer reputation), and a rating found unfair by the moderation desk
    // is hidden from summaries (never deleted — it keeps the audit trail).
    if (function_exists('ensure_column')) {
        ensure_column('cx_ratings', 'payment_status', "VARCHAR(12) DEFAULT ''");   // ON_TIME|DELAYED|PARTIAL|UNPAID (PRO_TO_CLIENT)
        ensure_column('cx_ratings', 'hidden', 'INT DEFAULT 0');                     // set when a rating dispute is upheld → REMOVED
        ensure_column('cx_ratings', 'moderation_note', "VARCHAR(400) DEFAULT ''");  // a public annotation from an upheld dispute
    }
}

/** How a client paid, as recorded by the professional on a PRO_TO_CLIENT rating. */
function cx_rating_payment_statuses() {
    return ['ON_TIME' => 'Paid on time', 'DELAYED' => 'Paid late', 'PARTIAL' => 'Partly paid', 'UNPAID' => 'Not paid'];
}
function cx_rating_get($id) { connect_ratings_migrate(); return ops_one("SELECT * FROM cx_ratings WHERE id=?", [(int)$id]) ?: null; }

/** A rating may be given once a requirement is AWARDED or CLOSED. */
function cx_rating_allowed($req) {
    return is_array($req) && in_array(strtoupper((string)($req['status'] ?? '')), ['AWARDED', 'CLOSED'], true);
}

/** True when this direction has already been rated for the engagement. */
function cx_rating_exists($requirementId, $direction) {
    return (int)ops_val("SELECT COUNT(*) FROM cx_ratings WHERE requirement_id=? AND direction=?",
        [(int)$requirementId, strtoupper((string)$direction)]) > 0;
}

/**
 * Record a rating. Returns the new id, or 0 if not allowed / a duplicate /
 * an invalid direction. Stars are clamped to 1..5.
 */
function cx_rating_add($requirementId, $direction, array $in) {
    connect_ratings_migrate();
    $requirementId = (int)$requirementId;
    $direction = strtoupper((string)$direction);
    if (!in_array($direction, CX_RATING_DIRECTIONS, true)) return 0;
    $req = function_exists('cx_requirement_get') ? cx_requirement_get($requirementId) : null;
    if (!cx_rating_allowed($req)) return 0;
    if (cx_rating_exists($requirementId, $direction)) return 0;

    $stars = max(1, min(5, (int)($in['stars'] ?? 0)));
    $clamp = fn($k) => max(0, min(5, (int)($in[$k] ?? 0)));
    // Payment behaviour is only meaningful when a professional rates a client.
    $pay = strtoupper((string)($in['payment_status'] ?? ''));
    if ($direction !== 'PRO_TO_CLIENT' || !isset(cx_rating_payment_statuses()[$pay])) $pay = '';
    db()->prepare("INSERT INTO cx_ratings
        (requirement_id,application_id,direction,rater_party_id,ratee_inspector_id,ratee_party_id,
         stars,competency,communication,punctuality,professionalism,would_rehire,payment_status,comment,created_by,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$requirementId, (int)($in['application_id'] ?? 0), $direction,
                   (int)($in['rater_party_id'] ?? 0), (int)($in['ratee_inspector_id'] ?? 0), (int)($in['ratee_party_id'] ?? 0),
                   $stars, $clamp('competency'), $clamp('communication'), $clamp('punctuality'), $clamp('professionalism'),
                   !empty($in['would_rehire']) ? 1 : 0, $pay, trim((string)($in['comment'] ?? '')),
                   function_exists('user_name') ? user_name(current_user()) : '', date('c')]);
    return (int)db()->lastInsertId();
}

function cx_ratings_for_requirement($requirementId) {
    connect_ratings_migrate();
    return ops_all("SELECT * FROM cx_ratings WHERE requirement_id=? ORDER BY id", [(int)$requirementId]) ?: [];
}

/** Marketplace rating summary for a professional: [count, avg_stars, rehire_pct]. Hidden (disputed-and-removed) ratings are excluded. */
function cx_rating_summary_for_inspector($inspectorId) {
    connect_ratings_migrate();
    $inspectorId = (int)$inspectorId;
    $rows = ops_all("SELECT stars, would_rehire FROM cx_ratings WHERE ratee_inspector_id=? AND direction='CLIENT_TO_PRO' AND COALESCE(hidden,0)=0", [$inspectorId]) ?: [];
    $n = count($rows);
    if ($n === 0) return ['count' => 0, 'avg_stars' => null, 'rehire_pct' => null];
    $sum = array_sum(array_column($rows, 'stars'));
    $rehire = array_sum(array_map(fn($r) => (int)$r['would_rehire'], $rows));
    return ['count' => $n, 'avg_stars' => round($sum / $n, 1), 'rehire_pct' => (int)round($rehire / $n * 100)];
}

/**
 * Payer reputation for a CLIENT — how professionals who worked for them rate being paid.
 * A freelancer checks this BEFORE accepting a job. Returns count, avg_stars, a payment
 * breakdown (on-time / late / partial / unpaid), a % paid-fairly, and would-work-again.
 * Hidden (disputed-and-removed) ratings are excluded.
 */
function cx_rating_summary_for_client($partyId) {
    connect_ratings_migrate();
    $rows = ops_all("SELECT stars, would_rehire, payment_status FROM cx_ratings WHERE ratee_party_id=? AND direction='PRO_TO_CLIENT' AND COALESCE(hidden,0)=0", [(int)$partyId]) ?: [];
    $n = count($rows);
    $pay = ['ON_TIME' => 0, 'DELAYED' => 0, 'PARTIAL' => 0, 'UNPAID' => 0];
    if ($n === 0) return ['count' => 0, 'avg_stars' => null, 'pay' => $pay, 'paid_fair_pct' => null, 'rehire_pct' => null];
    $sum = 0; $rated = 0; $rehire = 0;
    foreach ($rows as $r) {
        $sum += (int)$r['stars']; $rehire += (int)$r['would_rehire'];
        $ps = strtoupper((string)$r['payment_status']); if (isset($pay[$ps])) { $pay[$ps]++; $rated++; }
    }
    $fair = $rated > 0 ? (int)round($pay['ON_TIME'] / $rated * 100) : null;
    return ['count' => $n, 'avg_stars' => round($sum / $n, 1), 'pay' => $pay, 'pay_rated' => $rated,
            'paid_fair_pct' => $fair, 'rehire_pct' => (int)round($rehire / $n * 100)];
}

/** One reputation card for either side. kind 'PRO' → inspector id; 'CLIENT' → party id. */
function connect_reputation_card($kind, $id) {
    return strtoupper((string)$kind) === 'CLIENT'
        ? ['kind' => 'CLIENT'] + cx_rating_summary_for_client($id)
        : ['kind' => 'PRO'] + cx_rating_summary_for_inspector($id);
}
