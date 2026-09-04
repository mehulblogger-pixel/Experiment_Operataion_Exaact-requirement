<?php
// ============================================================================
//  CONNECT — Unified professional identity  (K0+, additive, NON-DESTRUCTIVE)
//
//  THE PROBLEM (from the integration audit): the same human can exist twice —
//  once as an internal `inspectors` row (the person the whole Operations / PDSO /
//  expense / voucher chain uses) and once as a marketplace `cx_professionals`
//  row (the self-registered pool). They were only ever bridged per-application
//  (cx_applications.inspector_id + applicant_professional_id). There is no stored
//  link on the person records, so the same person is two unlinked identities.
//
//  THE FIX — a RELATIONSHIP, never a merge (per the master brief §3, §48):
//  one small link ledger, cx_identity_link, records "this professional row and
//  this inspector row are the same person". Nothing is renamed, moved, merged or
//  deleted; both records keep working exactly as before. Everything else asks the
//  resolver "who is this, really?" and can then treat the two as ONE master
//  identity with many roles (marketplace pro · internal inspector · bench · …).
//
//  Reuses: act_log() for provenance (§42). Adds NO new permission — linking is a
//  talent-pool action gated by the existing coordinator/manager right.
// ============================================================================

function connect_identity_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_identity_link (
        id $pk,
        professional_id INT DEFAULT 0,          -- cx_professionals.id
        inspector_id    INT DEFAULT 0,          -- inspectors.id
        party_id        INT DEFAULT 0,          -- optional business_partners.id (external)
        method   VARCHAR(20) DEFAULT 'manual',  -- manual | email_match | mobile_match | self_claim
        status   VARCHAR(12) DEFAULT 'LINKED',  -- LINKED | UNLINKED
        note     VARCHAR(200) DEFAULT '',
        linked_by   VARCHAR(120) DEFAULT '',
        linked_at   VARCHAR(30) DEFAULT '',
        unlinked_at VARCHAR(30) DEFAULT '')");
    foreach (["CREATE INDEX ix_cx_idlink_pro ON cx_identity_link (professional_id)",
              "CREATE INDEX ix_cx_idlink_insp ON cx_identity_link (inspector_id)"] as $ix) { try { db()->exec($ix); } catch (Throwable $e) {} }
    // P11 — the same person can also be a recruitment candidate. A candidate↔professional
    // link is the same "one person across identities" concept, so it reuses this ledger
    // (additive column; a candidate-axis row carries inspector_id=0).
    if (function_exists('ensure_column')) ensure_column('cx_identity_link', 'candidate_id', 'INT DEFAULT 0');
    try { db()->exec("CREATE INDEX ix_cx_idlink_cand ON cx_identity_link (candidate_id)"); } catch (Throwable $e) {}
}

// ---- Resolvers — "who is this, really?" ------------------------------------

/** The active link row for a professional (→ inspector_id), or null. */
function connect_identity_of_professional($proId) {
    connect_identity_migrate();
    return ops_one("SELECT * FROM cx_identity_link WHERE professional_id=? AND status='LINKED' ORDER BY id DESC LIMIT 1", [(int)$proId]) ?: null;
}
/** The active link row for an inspector (→ professional_id), or null. */
function connect_identity_of_inspector($inspId) {
    connect_identity_migrate();
    return ops_one("SELECT * FROM cx_identity_link WHERE inspector_id=? AND status='LINKED' ORDER BY id DESC LIMIT 1", [(int)$inspId]) ?: null;
}

/**
 * The roles one person plays across the whole system — the payoff of the link.
 * $ref is ['professional_id'=>..] or ['inspector_id'=>..]. Returns a compact card:
 * whether they are a marketplace professional, an internal inspector, on any agency
 * bench, plus the resolved counterpart id. Read-only; safe to call widely.
 */
function connect_identity_roles(array $ref) {
    connect_identity_migrate();
    $proId = (int)($ref['professional_id'] ?? 0);
    $inspId = (int)($ref['inspector_id'] ?? 0);
    if ($proId && !$inspId) { $lk = connect_identity_of_professional($proId); if ($lk) $inspId = (int)$lk['inspector_id']; }
    if ($inspId && !$proId) { $lk = connect_identity_of_inspector($inspId); if ($lk) $proId = (int)$lk['professional_id']; }
    $name = '';
    $isPro = $proId ? (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE id=?", [$proId]) > 0 : false;
    $isInsp = $inspId ? (int)ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$inspId]) > 0 : false;
    if ($isPro)  $name = (string)ops_val("SELECT name FROM cx_professionals WHERE id=?", [$proId]);
    if (!$name && $isInsp) $name = (string)ops_val("SELECT name FROM inspectors WHERE id=?", [$inspId]);
    $onBench = 0;
    try { $onBench = (int)ops_val("SELECT COUNT(*) FROM cx_bench WHERE professional_id=?", [$proId]); } catch (Throwable $e) {}
    return [
        'name'            => $name,
        'professional_id' => $proId,
        'inspector_id'    => $inspId,
        'is_professional' => $isPro,
        'is_inspector'    => $isInsp,
        'linked'          => ($proId && $inspId),
        'bench_count'     => $onBench,
    ];
}

// ---- Create / remove a link ------------------------------------------------

/**
 * Link a professional row and an inspector row as the same person. Guards:
 *  - both rows must exist;
 *  - neither may already be actively linked to a DIFFERENT counterpart
 *    (re-linking the same pair is a harmless success).
 * Records provenance in the activity spine. Returns [ok, message, id].
 */
function connect_identity_link_create($proId, $inspId, $method = 'manual', $by = '', $note = '') {
    connect_identity_migrate();
    $proId = (int)$proId; $inspId = (int)$inspId;
    if ($proId <= 0 || $inspId <= 0) return [false, 'A professional and an inspector are both required.', 0];
    if ((int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE id=?", [$proId]) === 0) return [false, 'That professional record does not exist.', 0];
    if ((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$inspId]) === 0) return [false, 'That inspector record does not exist.', 0];
    $ep = connect_identity_of_professional($proId);
    if ($ep && (int)$ep['inspector_id'] === $inspId) return [true, 'Already linked.', (int)$ep['id']];
    if ($ep) return [false, 'That professional is already linked to another inspector — unlink it first.', 0];
    $ei = connect_identity_of_inspector($inspId);
    if ($ei) return [false, 'That inspector is already linked to another professional — unlink it first.', 0];
    if ($by === '' && function_exists('current_user')) { $u = current_user(); $by = (string)($u['name'] ?? $u['username'] ?? ''); }
    db()->prepare("INSERT INTO cx_identity_link (professional_id,inspector_id,method,status,note,linked_by,linked_at) VALUES (?,?,?,'LINKED',?,?,?)")
        ->execute([$proId, $inspId, substr((string)$method,0,20), substr((string)$note,0,200), substr((string)$by,0,120), date('c')]);
    $id = (int)db()->lastInsertId();
    if (function_exists('act_log')) {
        $roles = connect_identity_roles(['professional_id' => $proId]);
        try { act_log('cx_identity_link', $id, 'IDENTITY_LINKED', 'Linked ' . ($roles['name'] ?: 'a professional') . ' (pro #' . $proId . ' ↔ inspector #' . $inspId . ')', ['auto' => ($method !== 'manual') ? 1 : 0]); } catch (Throwable $e) {}
    }
    return [true, 'Linked — this is now one person across the marketplace and Operations.', $id];
}

/** Remove an active link (by link id). Records provenance. */
function connect_identity_unlink($linkId, $by = '') {
    connect_identity_migrate();
    $row = ops_one("SELECT * FROM cx_identity_link WHERE id=? AND status='LINKED'", [(int)$linkId]);
    if (!$row) return [false, 'No such active link.'];
    if ($by === '' && function_exists('current_user')) { $u = current_user(); $by = (string)($u['name'] ?? $u['username'] ?? ''); }
    db()->prepare("UPDATE cx_identity_link SET status='UNLINKED', unlinked_at=? WHERE id=?")->execute([date('c'), (int)$linkId]);
    if (function_exists('act_log')) {
        $other = (int)($row['candidate_id'] ?? 0) > 0 ? 'candidate #' . (int)$row['candidate_id'] : 'inspector #' . (int)$row['inspector_id'];
        try { act_log('cx_identity_link', (int)$linkId, 'IDENTITY_UNLINKED', 'Unlinked pro #' . (int)$row['professional_id'] . ' ↔ ' . $other, ['auto' => 0]); } catch (Throwable $e) {}
    }
    return [true, 'Unlinked.'];
}

// ---- Candidate ↔ professional (P11) — the same person across the two pools -----

/** The active candidate↔professional link row for a candidate (→ professional_id), or null. */
function connect_identity_of_candidate($candId) {
    connect_identity_migrate();
    return ops_one("SELECT * FROM cx_identity_link WHERE candidate_id=? AND status='LINKED' ORDER BY id DESC LIMIT 1", [(int)$candId]) ?: null;
}

/**
 * Confirm that a recruitment candidate and a marketplace professional are the same
 * person, recording it as an additive, reversible link. It NEVER merges or deletes
 * either record — each pool keeps its own row; this only stamps the fact that they
 * are one person, so it can be unlinked with no data loss (P11's safe first step).
 */
function connect_identity_candidate_link_create($candId, $proId, $method = 'manual', $by = '', $note = '') {
    connect_identity_migrate();
    $candId = (int)$candId; $proId = (int)$proId;
    if ($candId <= 0 || $proId <= 0) return [false, 'A candidate and a professional are both required.', 0];
    if ((int)ops_val("SELECT COUNT(*) FROM candidates WHERE id=?", [$candId]) === 0) return [false, 'That candidate record does not exist.', 0];
    if ((int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE id=?", [$proId]) === 0) return [false, 'That professional record does not exist.', 0];
    $ex = connect_identity_of_candidate($candId);
    if ($ex && (int)$ex['professional_id'] === $proId) return [true, 'Already confirmed as the same person.', (int)$ex['id']];
    if ($ex) return [false, 'This candidate is already linked to another professional — unlink it first.', 0];
    if ($by === '' && function_exists('current_user')) { $u = current_user(); $by = (string)($u['name'] ?? $u['username'] ?? ''); }
    db()->prepare("INSERT INTO cx_identity_link (professional_id,inspector_id,candidate_id,method,status,note,linked_by,linked_at) VALUES (?,0,?,?,'LINKED',?,?,?)")
        ->execute([$proId, $candId, substr((string)$method, 0, 20), substr((string)$note, 0, 200), substr((string)$by, 0, 120), date('c')]);
    $id = (int)db()->lastInsertId();
    if (function_exists('act_log')) { try { act_log('cx_identity_link', $id, 'IDENTITY_LINKED', 'Linked candidate #' . $candId . ' ↔ professional #' . $proId . ' (same person)', ['auto' => ($method !== 'manual') ? 1 : 0]); } catch (Throwable $e) {} }
    return [true, 'Confirmed — this candidate and marketplace professional are recorded as one person (nothing merged; you can unlink any time).', $id];
}

// ---- Suggestions — the same person, not yet linked -------------------------

/**
 * Propose links from a STRONG deterministic signal: a professional and an
 * inspector that share an e-mail (primary) or a mobile number (secondary), and
 * are not already linked to anyone. Never links automatically — a person confirms.
 */
function connect_identity_suggestions($limit = 100) {
    connect_identity_migrate();
    $out = []; $seen = [];
    $push = function ($pro, $insp, $basis) use (&$out, &$seen) {
        $key = (int)$pro['id'] . ':' . (int)$insp['id'];
        if (isset($seen[$key])) return; $seen[$key] = true;
        if (connect_identity_of_professional((int)$pro['id']) || connect_identity_of_inspector((int)$insp['id'])) return;
        $out[] = ['professional_id' => (int)$pro['id'], 'pro_name' => (string)$pro['name'], 'pro_email' => (string)($pro['email'] ?? ''),
                  'inspector_id' => (int)$insp['id'], 'insp_name' => (string)$insp['name'], 'insp_email' => (string)($insp['email'] ?? ''),
                  'emp_code' => (string)($insp['emp_code'] ?? ''), 'basis' => $basis];
    };
    // Primary: exact e-mail match.
    try {
        $rows = ops_all(
            "SELECT p.id p_id, p.name p_name, p.email p_email, i.id i_id, i.name i_name, i.email i_email, i.emp_code
               FROM cx_professionals p JOIN inspectors i ON LOWER(p.email)=LOWER(i.email)
              WHERE p.is_active=1 AND COALESCE(i.status,'ACTIVE')='ACTIVE' AND COALESCE(p.email,'')<>''") ?: [];
        foreach ($rows as $r) $push(['id'=>$r['p_id'],'name'=>$r['p_name'],'email'=>$r['p_email']],
                                     ['id'=>$r['i_id'],'name'=>$r['i_name'],'email'=>$r['i_email'],'emp_code'=>$r['emp_code']], 'email');
    } catch (Throwable $e) {}
    // Secondary: exact mobile match (digits only).
    try {
        $rows = ops_all(
            "SELECT p.id p_id, p.name p_name, p.email p_email, i.id i_id, i.name i_name, i.email i_email, i.emp_code
               FROM cx_professionals p JOIN inspectors i
                 ON REPLACE(REPLACE(p.mobile,' ',''),'-','') = REPLACE(REPLACE(i.mobile,' ',''),'-','')
              WHERE p.is_active=1 AND COALESCE(i.status,'ACTIVE')='ACTIVE'
                AND LENGTH(REPLACE(REPLACE(COALESCE(p.mobile,''),' ',''),'-','')) >= 8") ?: [];
        foreach ($rows as $r) $push(['id'=>$r['p_id'],'name'=>$r['p_name'],'email'=>$r['p_email']],
                                     ['id'=>$r['i_id'],'name'=>$r['i_name'],'email'=>$r['i_email'],'emp_code'=>$r['emp_code']], 'mobile');
    } catch (Throwable $e) {}
    return array_slice($out, 0, max(1, (int)$limit));
}

/** All active links, joined to names, for the admin console. */
function connect_identity_links($limit = 300) {
    connect_identity_migrate();
    try {
        return ops_all(
            "SELECT l.*, p.name pro_name, p.email pro_email, i.name insp_name, i.emp_code
               FROM cx_identity_link l
               LEFT JOIN cx_professionals p ON p.id=l.professional_id
               LEFT JOIN inspectors i ON i.id=l.inspector_id
              WHERE l.status='LINKED' ORDER BY l.id DESC LIMIT " . max(1,(int)$limit)) ?: [];
    } catch (Throwable $e) { return []; }
}

// ---- Matcher dedupe — one person, once -------------------------------------

/**
 * Collapse matcher rows so a person linked as BOTH an internal inspector and a
 * self-registered professional appears once — keeping the higher-scoring row and
 * annotating it so the desk still knows the person wears both hats. Given rows
 * each carrying 'kind' ('inspector'|'professional') and 'id'. Order preserved
 * (the kept row stays where the stronger of the two was).
 */
function connect_identity_dedupe_rows(array $rows) {
    connect_identity_migrate();
    // Build inspector→professional map for the ids present.
    $byPair = [];
    foreach (connect_identity_links(1000) as $l) $byPair[(int)$l['inspector_id']] = (int)$l['professional_id'];
    if (!$byPair) return $rows;
    // Index rows for quick score lookup.
    $out = []; $dropId = [];   // "professional:{id}" or "inspector:{id}" keys to drop
    // First pass: decide, for each linked pair present twice, which to drop.
    $proRow = []; $inspRow = [];
    foreach ($rows as $i => $r) {
        if (($r['kind'] ?? '') === 'professional') $proRow[(int)$r['id']] = $i;
        elseif (($r['kind'] ?? '') === 'inspector') $inspRow[(int)$r['id']] = $i;
    }
    foreach ($byPair as $inspId => $proId) {
        if (!isset($inspRow[$inspId]) || !isset($proRow[$proId])) continue;   // not both present
        $ri = $rows[$inspRow[$inspId]]; $rp = $rows[$proRow[$proId]];
        // Keep the higher score; annotate it; drop the other.
        if ((int)($ri['score'] ?? 0) >= (int)($rp['score'] ?? 0)) { $dropId['professional:' . $proId] = true; $keep = 'inspector:' . $inspId; }
        else { $dropId['inspector:' . $inspId] = true; $keep = 'professional:' . $proId; }
        $GLOBALS['__cx_id_also'][$keep] = true;
    }
    foreach ($rows as $r) {
        $key = ($r['kind'] ?? '') . ':' . (int)($r['id'] ?? 0);
        if (!empty($dropId[$key])) continue;
        if (!empty($GLOBALS['__cx_id_also'][$key])) {
            $r['also_identity'] = ($r['kind'] === 'inspector') ? 'Also a marketplace professional' : 'Also on internal staff';
            $r['reasons'] = array_merge($r['reasons'] ?? [], ['✓ One verified person — staff & marketplace']);
        }
        $out[] = $r;
    }
    unset($GLOBALS['__cx_id_also']);
    return $out;
}

// ---- Access gate (reuses the talent-pool right; no new permission) ---------

function connect_identity_admin_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('connect_market_can')) return (bool)connect_market_can();
    if (function_exists('is_master') && is_master()) return true;
    return false;
}

/** The staff identity console — confirm suggested links, link/unlink by hand. */
function ops_connect_identity($method) {
    ops_require(connect_identity_admin_can(), 'Managing professional identity is for coordinators, managers and admins.');
    connect_identity_migrate();
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'link') {
            [$ok, $msg] = connect_identity_link_create((int)($_POST['professional_id'] ?? 0), (int)($_POST['inspector_id'] ?? 0), (string)($_POST['method'] ?? 'manual'));
            flash($msg, $ok ? 'success' : 'error');
        } elseif ($act === 'unlink') {
            [$ok, $msg] = connect_identity_unlink((int)($_POST['id'] ?? 0));
            flash($msg, $ok ? 'success' : 'error');
        }
        redirect('/connect-identity');
    }
    view('ops/connect_identity', [
        'links'       => connect_identity_links(),
        'suggestions' => connect_identity_suggestions(),
    ]);
    return true;
}
