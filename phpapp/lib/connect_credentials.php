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
    // Verification is NEVER self-declared — it flows through the moderation ledger
    // (cx_verifications). These two columns link a cert to its pending/decided check.
    if (function_exists('ensure_column')) {
        try { ensure_column('cx_pro_certs', 'verify_status',   "VARCHAR(16) DEFAULT ''"); } catch (Throwable $e) {}
        try { ensure_column('cx_pro_certs', 'verify_check_id', "INT DEFAULT 0"); }          catch (Throwable $e) {}
    }
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

/**
 * The §15 verification-state ladder for one credential, derived from what the
 * data already carries (verified flag, a supporting file, expiry). Highest wins:
 *   EXPIRED    — valid-to has passed (whatever its verification was)
 *   VERIFIED   — checked by the platform/an authority (verified=1)
 *   DOCUMENTED — a supporting document is attached but not yet verified
 *   DECLARED   — self-declared, no document yet
 * Returns ['code','label','tone']. tone: ok | info | warn | bad.
 */
function connect_cred_verify_state($cert) {
    // Gap-5 — the ONE verification ladder, read across BOTH pools: a marketplace cert
    // (expiry_date / verified / file_id) OR an internal inspector cert (valid_to /
    // verify_status / file_name). A marketplace cert carries none of the inspector fields,
    // so its behaviour is exactly as before — the extension only adds inspector support.
    $vs = strtoupper(trim((string)($cert['verify_status'] ?? '')));
    if ($vs === 'REJECTED')   return ['code' => 'REJECTED',   'label' => 'Rejected',   'tone' => 'bad'];
    if ($vs === 'SUPERSEDED') return ['code' => 'SUPERSEDED', 'label' => 'Superseded', 'tone' => 'mut'];
    $expiry = trim((string)($cert['expiry_date'] ?? '')); if ($expiry === '') $expiry = trim((string)($cert['valid_to'] ?? ''));
    if ($expiry !== '' && (strtotime($expiry) - time()) < 0) return ['code' => 'EXPIRED', 'label' => 'Expired', 'tone' => 'bad'];
    if ((int)($cert['verified'] ?? 0) === 1 || $vs === 'VERIFIED') {
        $soon = $expiry !== '' && (strtotime($expiry) - time()) / 86400 <= 60;
        return $soon ? ['code' => 'VERIFIED', 'label' => 'Verified · expiring', 'tone' => 'warn']
                     : ['code' => 'VERIFIED', 'label' => 'Verified', 'tone' => 'ok'];
    }
    if ((int)($cert['file_id'] ?? 0) > 0 || trim((string)($cert['file_name'] ?? '')) !== '' || $vs === 'UNDER_VERIFICATION')
        return ['code' => 'DOCUMENTED', 'label' => 'Document attached', 'tone' => 'info'];
    return ['code' => 'DECLARED', 'label' => 'Self-declared', 'tone' => 'warn'];
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
    // NOTE: 'verified' is intentionally NOT taken from caller input. A cert becomes
    // verified only through connect_cred_cert_request_verify + a moderator decision
    // in the cx_verifications ledger (reconciled by connect_cred_reconcile).
    $cols = [$nodeId, $name, trim((string)($in['authority'] ?? '')), trim((string)($in['cert_number'] ?? '')),
             trim((string)($in['level'] ?? '')), trim((string)($in['discipline'] ?? '')),
             trim((string)($in['issue_date'] ?? '')), trim((string)($in['expiry_date'] ?? '')), (int)($in['file_id'] ?? 0)];
    $id = (int)($in['id'] ?? 0);
    if ($id > 0 && (int)ops_val("SELECT COUNT(*) FROM cx_pro_certs WHERE id=? AND pro_id=?", [$id, $proId])) {
        // Editing a cert's details never grants verification, but keeps any existing badge.
        db()->prepare("UPDATE cx_pro_certs SET node_id=?, name=?, authority=?, cert_number=?, level=?, discipline=?, issue_date=?, expiry_date=?, file_id=? WHERE id=? AND pro_id=?")
            ->execute(array_merge($cols, [$id, $proId]));
    } else {
        db()->prepare("INSERT INTO cx_pro_certs (node_id,name,authority,cert_number,level,discipline,issue_date,expiry_date,file_id,verified,pro_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,0,?,?)")
            ->execute(array_merge($cols, [$proId, date('c')]));
        $id = (int)db()->lastInsertId();
    }
    // Mirror into the taxonomy link so the one-keyword search + matching find it.
    // The mirror carries the cert's ACTUAL verified state (0 until a moderator confirms).
    if ($nodeId > 0 && function_exists('connect_profile_tax_attach')) {
        $vf = (int)ops_val("SELECT verified FROM cx_pro_certs WHERE id=?", [$id]);
        connect_profile_tax_attach($proId, $nodeId, 'CERTIFICATION', ['verified' => $vf, 'source' => 'cert']);
    }
    return [true, 'Saved.', $id];
}

/**
 * Submit a certification for verification. Requires an uploaded document (a claim
 * without evidence is not reviewable). Files a CREDENTIAL check in the shared
 * moderation ledger and links it back to this cert; the badge stays "pending"
 * until a moderator decides. Never elevates the cert itself.
 */
function connect_cred_cert_request_verify($proId, $id) {
    connect_cred_migrate();
    $proId = (int)$proId; $id = (int)$id;
    $c = ops_one("SELECT * FROM cx_pro_certs WHERE id=? AND pro_id=?", [$id, $proId]);
    if (!$c) return [false, 'Certificate not found.'];
    if ((int)$c['file_id'] <= 0) return [false, 'Attach the certificate document first — verification needs evidence.'];
    if (($c['verify_status'] ?? '') === 'PENDING') return [false, 'This certificate is already awaiting review.'];
    if (!function_exists('connect_verify_submit')) return [false, 'Verification is unavailable.'];
    $ev = 'Certificate: ' . $c['name'] . ($c['authority'] ? ' — ' . $c['authority'] : '') . ' (cert #' . $id . ')';
    [$ok, $msg, $checkId] = connect_verify_submit('professional', $proId, 'CREDENTIAL', '', $ev);
    if (!$ok) return [false, $msg];
    // Link the ledger check to this cert so a decision can be reconciled back.
    if (function_exists('ensure_column')) { try { ensure_column('cx_verifications', 'cert_id', "INT DEFAULT 0"); } catch (Throwable $e) {} }
    try { db()->prepare("UPDATE cx_verifications SET cert_id=? WHERE id=?")->execute([$id, (int)$checkId]); } catch (Throwable $e) {}
    db()->prepare("UPDATE cx_pro_certs SET verify_status='PENDING', verify_check_id=? WHERE id=? AND pro_id=?")
        ->execute([(int)$checkId, $id, $proId]);
    return [true, 'Submitted for verification — our team will review your certificate.'];
}

/**
 * Reconcile each cert's badge from its linked ledger check. Idempotent, cheap,
 * called on every read so a moderator's decision surfaces without a write path
 * into this module. VERIFIED -> verified=1; REJECTED/PENDING -> verified=0.
 */
function connect_cred_reconcile($proId) {
    $rows = ops_all("SELECT id, node_id, verify_check_id, verify_status, verified FROM cx_pro_certs WHERE pro_id=? AND verify_check_id>0", [(int)$proId]) ?: [];
    foreach ($rows as $r) {
        $chk = ops_one("SELECT status FROM cx_verifications WHERE id=?", [(int)$r['verify_check_id']]);
        if (!$chk) continue;
        $st  = strtoupper((string)$chk['status']);         // PENDING | VERIFIED | REJECTED
        $vf  = ($st === 'VERIFIED') ? 1 : 0;
        if ($st !== (string)$r['verify_status'] || (int)$vf !== (int)$r['verified']) {
            db()->prepare("UPDATE cx_pro_certs SET verify_status=?, verified=? WHERE id=?")->execute([$st, $vf, (int)$r['id']]);
            if ((int)$r['node_id'] > 0 && function_exists('connect_profile_tax_attach'))
                connect_profile_tax_attach((int)$proId, (int)$r['node_id'], 'CERTIFICATION', ['verified' => $vf, 'source' => 'cert']);
        }
    }
}
function connect_cred_cert_delete($id, $proId) {
    connect_cred_migrate();
    db()->prepare("DELETE FROM cx_pro_certs WHERE id=? AND pro_id=?")->execute([(int)$id, (int)$proId]);
    return true;
}
/** A professional's certifications, newest expiry-relevant first, with status. */
function connect_cred_certs($proId) {
    connect_cred_migrate();
    connect_cred_reconcile($proId);
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
