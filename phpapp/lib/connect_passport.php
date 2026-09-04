<?php
// ============================================================================
//  CONNECT — Digital Professional Passport  (slice K1, additive)
//
//  The public, shareable, QR-verifiable professional identity for a technical
//  person on the marketplace — built ENTIRELY over what EXAACT already holds:
//  the P1 Credential Vault (inspector_certs + credential_status), the inspector
//  record, the rating engine, and the QR encoder we own (qr.php). Adopted from
//  the Inspect Connect blueprint (M3). See docs/connect/01-reuse-map.md.
//
//  ADDITIVE CONTRACT:
//   - One new column only: inspectors.passport_token (an unguessable share key).
//     Nothing else on the inspector record is touched.
//   - The public page shows ONLY non-confidential professional identity —
//     verified credentials with live status, reputation, disciplines. Never a
//     phone, email, salary, client name or cert number.
//   - The link is capability-gated by the unguessable token; regenerating it
//     revokes every old link. A person with no token has no public page.
//   - Changes no existing route, view, permission or status.
// ============================================================================

/** Additive: the share-key column on the existing inspectors table. */
function connect_passport_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (function_exists('ensure_column')) ensure_column('inspectors', 'passport_token', "VARCHAR(40) DEFAULT ''");
}

/** Get — or, on first use, mint — the unguessable share token for an inspector. */
function connect_passport_token($inspectorId, $create = true) {
    connect_passport_migrate();
    $inspectorId = (int)$inspectorId;
    if ($inspectorId <= 0) return '';
    $tok = (string)ops_val("SELECT passport_token FROM inspectors WHERE id=?", [$inspectorId]);
    if ($tok === '' && $create) {
        $tok = bin2hex(random_bytes(16));
        db()->prepare("UPDATE inspectors SET passport_token=? WHERE id=?")->execute([$tok, $inspectorId]);
    }
    return $tok;
}

/** Mint a fresh token (revokes any existing public link). Returns the new token. */
function connect_passport_regenerate($inspectorId) {
    connect_passport_migrate();
    $inspectorId = (int)$inspectorId;
    if ($inspectorId <= 0) return '';
    $tok = bin2hex(random_bytes(16));
    db()->prepare("UPDATE inspectors SET passport_token=? WHERE id=?")->execute([$tok, $inspectorId]);
    return $tok;
}

/**
 * The professional behind a token, or null. Spans BOTH talent pools (unify #1):
 * an internal inspector or a self-registered professional. The row is tagged
 * with `_kind` so the public renderer knows which shape it is.
 */
function connect_passport_lookup($token) {
    $token = trim((string)$token);
    if ($token === '' || !preg_match('/^[a-f0-9]{16,40}$/', $token)) return null;
    connect_passport_migrate();
    $insp = ops_one("SELECT * FROM inspectors WHERE passport_token=?", [$token]);
    if ($insp) { $insp['_kind'] = 'inspector'; return $insp; }
    try {
        $pro = ops_one("SELECT * FROM cx_professionals WHERE passport_token=?", [$token]);
        if ($pro) { $pro['_kind'] = 'professional'; return $pro; }
    } catch (Throwable $e) {}
    return null;
}

/** Absolute base URL, built defensively from the request. */
function connect_passport_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return $scheme . '://' . $host;
}

/** The public passport URL for a token. */
function connect_passport_url($token) {
    return connect_passport_base_url() . '/p/' . rawurlencode((string)$token);
}

/** Friendly label for staff_kind (asset / freelancer / subcon). */
function connect_passport_kind_label($kind) {
    switch (strtoupper((string)$kind)) {
        case 'FREELANCER': return 'Freelance professional';
        case 'SUBCON':     return 'Sub-contracted';
        case 'ASSET':      return 'Empanelled professional';
        default:           return 'Technical professional';
    }
}

/**
 * Assemble the PUBLIC-SAFE passport for an inspector row. The returned array is
 * deliberately whitelisted — it carries nothing confidential (no email, mobile,
 * salary, cert number, or client). This is the contract the public view renders.
 */
function connect_passport_public_data($insp) {
    if (!is_array($insp)) return null;
    // A self-registered professional (unify #1) has no credential vault, job
    // history or Trust Score yet — render an honest "new professional" passport.
    if (($insp['_kind'] ?? '') === 'professional') {
        $name = trim((string)($insp['name'] ?? '')) ?: 'Technical professional';
        // Verification tier (unify #1 + #3) — an honest green badge only when a
        // real check has been VERIFIED; a new profile shows "Registered".
        $tierKey = (string)($insp['verification_tier'] ?? 'registered'); if ($tierKey === '') $tierKey = 'registered';
        $verification = null;
        if (function_exists('connect_verify_tier_label')) {
            $rank = function_exists('connect_verify_tier_rank') ? connect_verify_tier_rank($tierKey) : 0;
            $verification = ['tier' => $tierKey, 'label' => connect_verify_tier_label($tierKey),
                             'rank' => $rank, 'verified' => $rank >= 1];
        }
        return [
            'id' => (int)($insp['id'] ?? 0), 'name' => $name,
            'designation' => (string)($insp['headline'] ?? ''),
            'kind_label' => 'Freelance professional',
            'skills' => (string)($insp['skills'] ?? ''), 'sbu' => '',
            'credentials' => [], 'verified_count' => 0, 'live_count' => 0, 'cred_total' => 0,
            'reputation' => null, 'trust' => null, 'verification' => $verification,
            'token' => (string)($insp['passport_token'] ?? ''),
        ];
    }
    $id = (int)($insp['id'] ?? 0);

    // Verified credentials, live status via the P1 vault vocabulary.
    $certs = ops_all("SELECT * FROM inspector_certs WHERE inspector_id=? ORDER BY valid_to DESC", [$id]) ?: [];
    $creds = []; $verifiedCount = 0; $liveCount = 0;
    foreach ($certs as $c) {
        $status = function_exists('credential_status') ? credential_status($c) : (string)($c['status'] ?? '');
        $isLive = in_array(strtoupper((string)$status), ['VALID', 'VERIFIED', 'CURRENT', 'ACTIVE'], true);
        if ($isLive) $liveCount++;
        $vs = strtoupper((string)($c['verify_status'] ?? ''));
        if ($vs === 'VERIFIED') $verifiedCount++;
        $creds[] = [
            'name'         => (string)($c['name'] ?? ''),
            'status'       => (string)$status,
            'verify_status'=> (string)($c['verify_status'] ?? ''),
            'valid_to'     => substr((string)($c['valid_to'] ?? ''), 0, 10),
        ];
    }

    // Reputation — reuse the rating engine, with the blueprint's confidence
    // banding: too little history is stated honestly, never a misleading score.
    $rep = null;
    if (function_exists('rating_for')) {
        $r = rating_for($id);
        $history = (int)($r['done'] ?? 0);
        $rep = [
            'stars'   => $r['stars'] ?? null,
            'overall' => $r['overall'] ?? null,
            'jobs'    => $history,
            'limited' => $history < 10,   // "Limited history" band (M6)
        ];
    }

    $name = trim((string)($insp['name'] ?? ''));
    if ($name === '') $name = trim(($insp['first_name'] ?? '') . ' ' . ($insp['last_name'] ?? ''));

    return [
        'id'        => $id,
        'name'      => $name !== '' ? $name : 'Technical professional',
        'designation' => (string)($insp['designation'] ?? ''),
        'kind_label'  => connect_passport_kind_label($insp['staff_kind'] ?? ''),
        'skills'      => (string)($insp['skills'] ?? ''),
        'sbu'         => (string)($insp['sbu'] ?? ''),
        'credentials' => $creds,
        'verified_count' => $verifiedCount,
        'live_count'     => $liveCount,
        'cred_total'     => count($creds),
        'reputation'  => $rep,
        'trust'       => function_exists('connect_trust_score') ? connect_trust_score($id) : null,   // K5
        'token'       => (string)($insp['passport_token'] ?? ''),
    ];
}

/** Render the standalone PUBLIC passport page and exit (dispatched pre-login). */
function connect_passport_route($token) {
    if (function_exists('connect_enabled') && !connect_enabled()) { http_response_code(404); echo 'Not available.'; exit; }
    $insp = connect_passport_lookup($token);
    $data = $insp ? connect_passport_public_data($insp) : null;
    // Only ACTIVE professionals expose a public page (inspectors use `status`,
    // self-registered professionals use `is_active`).
    if ($insp && $data) {
        $active = ($insp['_kind'] ?? '') === 'professional'
            ? (int)($insp['is_active'] ?? 1) === 1
            : strtoupper((string)($insp['status'] ?? 'ACTIVE')) === 'ACTIVE';
        if (!$active) $data = null;
    }
    http_response_code($data ? 200 : 404);
    $GLOBALS['__passport'] = $data;
    $GLOBALS['__passport_url'] = $data ? connect_passport_url($token) : '';
    require __DIR__ . '/../views/ops/connect_passport_public.php';
    exit;
}

/** Staff read-gate for the share screen — reuses existing helpers, no new perm. */
function connect_passport_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('inspector_profile_can') && inspector_profile_can()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

/** Staff screen: get/copy an inspector's public passport link + QR + preview. */
function ops_connect_passport_share($method) {
    ops_require(connect_passport_can(), 'You cannot manage passport links.');
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($method === 'POST' && $id > 0 && ($_POST['action'] ?? '') === 'regenerate') {
        connect_passport_regenerate($id);
        flash('A new passport link was generated — any previously shared link no longer works.');
        redirect('/passport-share?id=' . $id);
    }
    $insp = $id > 0 ? ops_one("SELECT * FROM inspectors WHERE id=?", [$id]) : null;
    $token = $insp ? connect_passport_token($id) : '';
    view('ops/connect_passport_share', [
        'inspector' => $insp,
        'token'     => $token,
        'url'       => $token !== '' ? connect_passport_url($token) : '',
        'data'      => $insp ? connect_passport_public_data(array_merge($insp, ['passport_token' => $token])) : null,
        'inspectors'=> ops_all("SELECT id, name FROM inspectors WHERE COALESCE(status,'ACTIVE')='ACTIVE' ORDER BY name") ?: [],
    ]);
    return true;
}
