<?php
// ============================================================================
//  CONNECT — Client private bench / roster  (K0+, additive)
//
//  The DEMAND-side bench: a client's own private roster of professionals they
//  know and want to reuse. This is distinct from the agency bench
//  (lib/connect_bench.php, the SUPPLY side — people an agency employs). Here a
//  client saves professionals from three sources (§16):
//    A) a marketplace professional  → relationship to cx_professionals.id
//    B) a previous professional      (applied / shortlisted / worked for them)
//    C) a manual entry               (name/role/city/rate) — later linkable to a
//                                     real marketplace profile, never duplicated.
//
//  RELATIONSHIP, NOT DUPLICATION (§18, §48): one row per (client, professional)
//  in cx_client_bench — the SAME cx_professionals record can sit on many clients'
//  benches without copying the person. Private notes, the client's own rating,
//  preferred status and preferred rate live on THIS relationship and are
//  CLIENT-ONLY (§17) — the marketplace, the professional and other clients never
//  read them (a separate, client_party_id-scoped table; no professional-facing
//  read ever touches it).
//
//  Rehire (§49): a bench person can be invited straight onto one of the client's
//  own open requirements, reusing the existing application engine.
// ============================================================================

function connect_client_bench_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_client_bench (
        id $pk, client_party_id INT DEFAULT 0,
        professional_id INT DEFAULT 0,               -- 0 for a manual-only entry (source C)
        source VARCHAR(16) DEFAULT 'marketplace',    -- marketplace | previous | manual
        -- manual person fields (used only until linked to a real professional):
        manual_name VARCHAR(150) DEFAULT '', manual_role VARCHAR(160) DEFAULT '',
        manual_discipline VARCHAR(80) DEFAULT '', manual_city VARCHAR(120) DEFAULT '',
        manual_email VARCHAR(200) DEFAULT '', manual_mobile VARCHAR(40) DEFAULT '',
        -- the client's PRIVATE relationship data (never exposed outside this client):
        private_note TEXT, client_rating INT DEFAULT 0, preferred INT DEFAULT 0,
        preferred_rate REAL DEFAULT 0,
        added_by VARCHAR(120) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE INDEX ix_cx_cbench ON cx_client_bench (client_party_id)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE UNIQUE INDEX ux_cx_cbench_pro ON cx_client_bench (client_party_id, professional_id)"); } catch (Throwable $e) {}
}

/** Is this professional already on this client's bench? */
function connect_client_bench_has($clientPartyId, $proId) {
    connect_client_bench_migrate();
    if ((int)$proId <= 0) return false;
    return (bool)ops_val("SELECT COUNT(*) FROM cx_client_bench WHERE client_party_id=? AND professional_id=?", [(int)$clientPartyId, (int)$proId]);
}

/**
 * Add a professional to the client's bench. For a marketplace/previous save pass
 * ['professional_id'=>N]; for a manual entry pass the manual_* fields. Idempotent
 * for a marketplace professional (updates the note/rating if given). Returns
 * [ok, message, id].
 */
function connect_client_bench_add($clientPartyId, array $in, $by = '') {
    connect_client_bench_migrate();
    $party = (int)$clientPartyId; if ($party <= 0) return [false, 'Sign in as a client.', 0];
    $proId = (int)($in['professional_id'] ?? 0);
    $source = in_array(($in['source'] ?? ''), ['marketplace','previous','manual'], true) ? $in['source'] : ($proId > 0 ? 'marketplace' : 'manual');
    if ($proId <= 0 && trim((string)($in['manual_name'] ?? '')) === '') return [false, 'Add a professional from the pool, or enter a name.', 0];
    if ($proId > 0 && (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE id=?", [$proId]) === 0) return [false, 'That professional does not exist.', 0];

    if ($proId > 0) {
        $existing = ops_one("SELECT id FROM cx_client_bench WHERE client_party_id=? AND professional_id=?", [$party, $proId]);
        if ($existing) { connect_client_bench_update((int)$existing['id'], $party, $in); return [true, 'Already on your bench — updated.', (int)$existing['id']]; }
    }
    db()->prepare("INSERT INTO cx_client_bench (client_party_id,professional_id,source,manual_name,manual_role,manual_discipline,manual_city,manual_email,manual_mobile,private_note,client_rating,preferred,preferred_rate,added_by,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$party, $proId, $source,
            trim((string)($in['manual_name'] ?? '')), trim((string)($in['manual_role'] ?? '')), trim((string)($in['manual_discipline'] ?? '')),
            trim((string)($in['manual_city'] ?? '')), trim((string)($in['manual_email'] ?? '')), trim((string)($in['manual_mobile'] ?? '')),
            trim((string)($in['private_note'] ?? '')), (int)($in['client_rating'] ?? 0), !empty($in['preferred']) ? 1 : 0, (float)($in['preferred_rate'] ?? 0),
            substr((string)$by, 0, 120), date('c'), date('c')]);
    return [true, 'Added to your bench.', (int)db()->lastInsertId()];
}

/** Update the client's PRIVATE relationship data on a bench row (ownership-scoped). */
function connect_client_bench_update($id, $clientPartyId, array $in) {
    connect_client_bench_migrate();
    $row = ops_one("SELECT * FROM cx_client_bench WHERE id=? AND client_party_id=?", [(int)$id, (int)$clientPartyId]);
    if (!$row) return [false, 'Not on your bench.'];
    $note   = array_key_exists('private_note', $in) ? trim((string)$in['private_note']) : $row['private_note'];
    $rating = array_key_exists('client_rating', $in) ? max(0, min(5, (int)$in['client_rating'])) : (int)$row['client_rating'];
    $pref   = array_key_exists('preferred', $in) ? (!empty($in['preferred']) ? 1 : 0) : (int)$row['preferred'];
    $prate  = array_key_exists('preferred_rate', $in) ? (float)$in['preferred_rate'] : (float)$row['preferred_rate'];
    db()->prepare("UPDATE cx_client_bench SET private_note=?, client_rating=?, preferred=?, preferred_rate=?, updated_at=? WHERE id=? AND client_party_id=?")
        ->execute([$note, $rating, $pref, $prate, date('c'), (int)$id, (int)$clientPartyId]);
    return [true, 'Saved.'];
}

/** Remove a professional from the client's bench (ownership-scoped). */
function connect_client_bench_remove($id, $clientPartyId) {
    connect_client_bench_migrate();
    db()->prepare("DELETE FROM cx_client_bench WHERE id=? AND client_party_id=?")->execute([(int)$id, (int)$clientPartyId]);
    return true;
}

/** Link a manual entry (source C) to a real marketplace professional — no duplicate. */
function connect_client_bench_link($id, $clientPartyId, $proId) {
    connect_client_bench_migrate();
    $proId = (int)$proId;
    if ($proId <= 0 || (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE id=?", [$proId]) === 0) return [false, 'That professional does not exist.'];
    $row = ops_one("SELECT * FROM cx_client_bench WHERE id=? AND client_party_id=?", [(int)$id, (int)$clientPartyId]);
    if (!$row) return [false, 'Not on your bench.'];
    if (connect_client_bench_has($clientPartyId, $proId)) return [false, 'That professional is already on your bench.'];
    db()->prepare("UPDATE cx_client_bench SET professional_id=?, source='marketplace', updated_at=? WHERE id=? AND client_party_id=?")
        ->execute([$proId, date('c'), (int)$id, (int)$clientPartyId]);
    return [true, 'Linked to the marketplace profile.'];
}

/**
 * The client's bench, newest first, preferred pinned to the top. Each row carries
 * the client's PRIVATE fields plus — for a linked professional — a privacy-safe
 * card built by the shared resolver (so contact still follows the reveal rules).
 */
function connect_client_bench_list($clientPartyId, $limit = 200) {
    connect_client_bench_migrate();
    $rows = ops_all("SELECT * FROM cx_client_bench WHERE client_party_id=? ORDER BY preferred DESC, id DESC LIMIT " . max(1,(int)$limit), [(int)$clientPartyId]) ?: [];
    foreach ($rows as &$r) {
        $r['card'] = null;
        if ((int)$r['professional_id'] > 0) {
            $pro = ops_one("SELECT * FROM cx_professionals WHERE id=?", [(int)$r['professional_id']]);
            if ($pro && function_exists('connect_client_card')) $r['card'] = connect_client_card($pro, (int)$clientPartyId);
        }
        // Display fallback for a manual-only entry.
        $r['display_name'] = $r['card']['display_name'] ?? ($r['manual_name'] ?: 'Professional');
        $r['display_role'] = $r['card']['headline']    ?? $r['manual_role'];
        $r['display_city'] = $r['card']['base_city']   ?? $r['manual_city'];
    }
    unset($r);
    return $rows;
}

function connect_client_bench_count($clientPartyId) {
    connect_client_bench_migrate();
    return (int)ops_val("SELECT COUNT(*) FROM cx_client_bench WHERE client_party_id=?", [(int)$clientPartyId]);
}

/**
 * Source B — professionals who previously engaged with this client (applied to,
 * were shortlisted/awarded on this client's requirements) and are NOT yet on the
 * bench. The "add previous professionals" picker + the basis for rehire.
 */
function connect_client_bench_previous($clientPartyId, $limit = 50) {
    connect_client_bench_migrate();
    $party = (int)$clientPartyId;
    try {
        return ops_all(
            "SELECT p.id professional_id, p.name, p.headline, p.base_city,
                    MAX(a.status) last_status, COUNT(DISTINCT a.requirement_id) reqs
               FROM cx_applications a
               JOIN cx_requirements r ON r.id = a.requirement_id AND r.poster_party_id = ?
               JOIN cx_professionals p ON p.id = a.applicant_professional_id
              WHERE a.applicant_professional_id > 0
                AND p.id NOT IN (SELECT professional_id FROM cx_client_bench WHERE client_party_id=? AND professional_id>0)
              GROUP BY p.id, p.name, p.headline, p.base_city
              ORDER BY reqs DESC, p.name LIMIT " . max(1,(int)$limit), [$party, $party]) ?: [];
    } catch (Throwable $e) { return []; }
}
