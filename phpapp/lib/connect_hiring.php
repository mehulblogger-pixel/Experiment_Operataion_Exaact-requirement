<?php
// ============================================================================
//  CONNECT — Hiring home for marketplace clients  (K0+, additive)
//
//  A company that came to HIRE technical manpower should land in a home built
//  around that job — search & post at the top, then the live state of their
//  hiring: open requirements and who is waiting for a decision, contact requests
//  they have sent, and saved searches they can re-run in one tap. This is the
//  buyer counterpart to the professional passport: it only READS the existing
//  marketplace engines (cx_requirements / cx_applications), the privacy reveal
//  ledger and one small saved-search table. No new permission — a client who can
//  hire (market.post) already owns all of this.
// ============================================================================

function connect_hiring_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_client_saved_search (
        id $pk, client_party_id INT DEFAULT 0, label VARCHAR(120) DEFAULT '',
        qs VARCHAR(400) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE INDEX ix_cx_saved_search ON cx_client_saved_search (client_party_id)"); } catch (Throwable $e) {}
}

// ---- Saved searches ---------------------------------------------------------

/** Save a search (its query string) under a label. De-duplicated by (party, qs). */
function connect_hiring_saved_search_save($clientPartyId, $label, $qs) {
    connect_hiring_migrate();
    $clientPartyId = (int)$clientPartyId; $qs = trim((string)$qs);
    $label = trim((string)$label); if ($label === '') $label = connect_hiring_search_label($qs);
    if ($clientPartyId <= 0 || $qs === '') return [false, 'Nothing to save yet — run a search first.'];
    $exists = ops_val("SELECT id FROM cx_client_saved_search WHERE client_party_id=? AND qs=?", [$clientPartyId, $qs]);
    if ($exists) { db()->prepare("UPDATE cx_client_saved_search SET label=? WHERE id=?")->execute([substr($label,0,120), (int)$exists]); return [true, 'Search updated.']; }
    db()->prepare("INSERT INTO cx_client_saved_search (client_party_id,label,qs,created_at) VALUES (?,?,?,?)")
        ->execute([$clientPartyId, substr($label,0,120), substr($qs,0,400), date('c')]);
    return [true, 'Search saved — you can re-run it any time from your hiring home.'];
}

function connect_hiring_saved_search_delete($id, $clientPartyId) {
    connect_hiring_migrate();
    db()->prepare("DELETE FROM cx_client_saved_search WHERE id=? AND client_party_id=?")->execute([(int)$id, (int)$clientPartyId]);
    return true;
}

function connect_hiring_saved_searches($clientPartyId) {
    connect_hiring_migrate();
    return ops_all("SELECT * FROM cx_client_saved_search WHERE client_party_id=? ORDER BY id DESC", [(int)$clientPartyId]) ?: [];
}

/** A readable label from a raw query string, e.g. "welding inspector · near Dahej". */
function connect_hiring_search_label($qs) {
    parse_str((string)$qs, $p);
    $bits = [];
    if (!empty($p['q']))        $bits[] = (string)$p['q'];
    if (!empty($p['discipline'])) $bits[] = (string)$p['discipline'];
    if (!empty($p['location']))  $bits[] = 'near ' . (string)$p['location'];
    if (!empty($p['available_only'])) $bits[] = 'available now';
    return $bits ? implode(' · ', $bits) : 'All professionals';
}

// ---- The hiring-home aggregate ---------------------------------------------

/**
 * Everything the hiring home renders, for one client party. Read-only over the
 * existing marketplace + privacy engines. Degrades gracefully if a helper is
 * missing so the home never fatals.
 */
function connect_hiring_home($clientPartyId) {
    $party = (int)$clientPartyId;
    $reqs = function_exists('cx_requirements_for_party') ? cx_requirements_for_party($party) : [];
    $open = []; $awaiting = 0;
    foreach ($reqs as $r) {
        $st = strtoupper((string)$r['status']);
        if (in_array($st, ['OPEN', 'SHORTLISTING'], true)) {
            $apps = function_exists('cx_applications_for') ? cx_applications_for((int)$r['id']) : [];
            $pending = 0;
            foreach ($apps as $a) if (in_array(strtoupper((string)$a['status']), ['APPLIED', 'SHORTLISTED', 'OFFERED'], true)) $pending++;
            $r['_apps'] = count($apps); $r['_pending'] = $pending; $awaiting += $pending;
            $open[] = $r;
        }
    }
    $counts = [
        'open_reqs'  => count($open),
        'awaiting'   => $awaiting,
        'total_reqs' => count($reqs),
        'awarded'    => count(array_filter($reqs, fn($r) => strtoupper((string)$r['status']) === 'AWARDED')),
    ];
    return [
        'open_reqs'        => $open,
        'all_reqs'         => $reqs,
        'counts'           => $counts,
        'saved_searches'   => connect_hiring_saved_searches($party),
        'contact_requests' => function_exists('connect_privacy_reveal_status_for_client') ? connect_privacy_reveal_status_for_client($party) : [],
        'pool_size'        => function_exists('connect_pro_pool_count') ? connect_pro_pool_count() : 0,
        'bench_count'      => function_exists('connect_client_bench_count') ? connect_client_bench_count($party) : 0,
    ];
}
