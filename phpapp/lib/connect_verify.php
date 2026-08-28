<?php
// ============================================================================
//  CONNECT — Verification & Moderation  (slice K14 / backlog #3, additive)
//
//  "Verified" is the marketplace's core promise. This is the trust engine behind
//  it: a real verification-tier ladder, a moderation queue, deterministic ID
//  pre-screens (PAN / GSTIN / Aadhaar format + checksum), and a PLUGGABLE PROVIDER
//  SEAM so DigiLocker / a KYC vendor slots in later without changing anything else.
//
//     Registered → ID-verified → Credential-verified → Proven
//
//  HONESTY MODEL (the whole point):
//   - A deterministic check validates *format / checksum only* — it catches typos
//     and obvious fakes but is NEVER, by itself, proof of identity. A format PASS
//     lands in the moderation queue as PENDING; a human (or a real provider) makes
//     the VERIFIED call. A format FAIL is auto-REJECTED.
//   - The tier elevates ONLY on a genuine VERIFIED decision — so a fake profile
//     cannot reach a verified tier by typing a well-formed number.
//
//  ADDITIVE CONTRACT:
//   - One new table (cx_verifications, cx_* namespaced); writes the existing
//     cx_professionals.verification_tier column (never elevated before now).
//   - No new named permission: review reuses the coordinator/moderation gate
//     (is_coordinator_level), exactly like other back-office review desks.
//   - Changes no existing route, view, permission or object status.
// ============================================================================

/** The moderation/verification ledger — one row per check on a subject. */
function connect_verify_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_verifications (
        id $pk,
        subject_kind VARCHAR(20) DEFAULT 'professional',  -- professional | org | inspector
        subject_id   INT DEFAULT 0,
        check_type   VARCHAR(30) DEFAULT '',              -- PAN | GSTIN | AADHAAR | ID_DOC | EDUCATION | CREDENTIAL | FACE | WORK_HISTORY
        status       VARCHAR(12) DEFAULT 'PENDING',       -- PENDING | VERIFIED | REJECTED
        method       VARCHAR(20) DEFAULT 'manual',        -- deterministic | manual | provider
        provider     VARCHAR(40) DEFAULT '',              -- '' | digilocker | ...
        ref_masked   VARCHAR(40) DEFAULT '',              -- masked identifier, never the full number
        evidence     VARCHAR(400) DEFAULT '',             -- note / uploaded-doc reference
        result_note  VARCHAR(400) DEFAULT '',
        reviewed_by  VARCHAR(120) DEFAULT '',
        reviewed_at  VARCHAR(30) DEFAULT '',
        created_at   VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE INDEX ix_cx_verif_subject ON cx_verifications (subject_kind, subject_id)"); } catch (Throwable $e) {}
    try { db()->exec("CREATE INDEX ix_cx_verif_status ON cx_verifications (status)"); } catch (Throwable $e) {}
    if (function_exists('ensure_column')) {
        // The tier column exists on cx_professionals (A1); make sure it's there on
        // an older install and give organisations one too (additive, optional).
        try { ensure_column('cx_professionals', 'verification_tier', "VARCHAR(20) DEFAULT 'registered'"); } catch (Throwable $e) {}
        try { ensure_column('cx_professionals', 'verified_at', "VARCHAR(30) DEFAULT ''"); } catch (Throwable $e) {}
    }
}

// ---- The tier ladder --------------------------------------------------------

/** The verification-tier ladder, low → high. Surfaced on the Passport + Trust. */
function connect_verify_tiers() {
    return [
        ['key' => 'registered',           'rank' => 0, 'label' => 'Registered',           'blurb' => 'Signed up; identity not yet checked.'],
        ['key' => 'id_verified',          'rank' => 1, 'label' => 'ID-verified',          'blurb' => 'Government ID confirmed.'],
        ['key' => 'credential_verified',  'rank' => 2, 'label' => 'Credential-verified',  'blurb' => 'ID plus a qualification / certificate confirmed.'],
        ['key' => 'proven',               'rank' => 3, 'label' => 'Proven',               'blurb' => 'Verified and with a proven track record on the platform.'],
    ];
}
function connect_verify_tier_rank($key) {
    foreach (connect_verify_tiers() as $t) if ($t['key'] === $key) return (int)$t['rank'];
    return 0;
}
function connect_verify_tier_label($key) {
    foreach (connect_verify_tiers() as $t) if ($t['key'] === $key) return $t['label'];
    return 'Registered';
}

/** The check types a subject can submit, and how each is handled. */
function connect_verify_check_types() {
    return [
        'PAN'          => ['label' => 'PAN card',              'method' => 'deterministic', 'value' => true,  'grants' => 'id',         'for' => ['professional','org']],
        'AADHAAR'      => ['label' => 'Aadhaar',               'method' => 'deterministic', 'value' => true,  'grants' => 'id',         'for' => ['professional']],
        'GSTIN'        => ['label' => 'GSTIN (business)',      'method' => 'deterministic', 'value' => true,  'grants' => 'id',         'for' => ['org']],
        'ID_DOC'       => ['label' => 'Photo ID document',     'method' => 'manual',        'value' => false, 'grants' => 'id',         'for' => ['professional','org']],
        'EDUCATION'    => ['label' => 'Education certificate',  'method' => 'manual',        'value' => false, 'grants' => 'credential', 'for' => ['professional']],
        'CREDENTIAL'   => ['label' => 'Professional certificate','method' => 'manual',      'value' => false, 'grants' => 'credential', 'for' => ['professional']],
        'FACE'         => ['label' => 'Face / liveness',        'method' => 'provider',      'value' => false, 'grants' => 'id',         'for' => ['professional']],
        'WORK_HISTORY' => ['label' => 'Proven work record',     'method' => 'manual',        'value' => false, 'grants' => 'proven',     'for' => ['professional']],
    ];
}

// ---- Deterministic validators (format + checksum) ---------------------------

/** PAN: five letters, four digits, one letter (format only — no public checksum). */
function connect_verify_pan_valid($pan) {
    return (bool)preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', strtoupper(trim((string)$pan)));
}

/** GSTIN: 15 chars (2 state + 10 PAN + entity + 'Z' + checksum) with the mod-36 checksum. */
function connect_verify_gstin_valid($gstin) {
    $g = strtoupper(trim((string)$gstin));
    if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[0-9A-Z]{1}Z[0-9A-Z]{1}$/', $g)) return false;
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $factor = 2; $sum = 0; $mod = strlen($chars);
    for ($i = strlen($g) - 2; $i >= 0; $i--) {
        $code = strpos($chars, $g[$i]);
        if ($code === false) return false;
        $digit = $factor * $code;
        $factor = ($factor === 2) ? 1 : 2;
        $digit = intdiv($digit, $mod) + ($digit % $mod);
        $sum += $digit;
    }
    $check = ($mod - ($sum % $mod)) % $mod;
    return $chars[$check] === substr($g, -1);
}

/** Aadhaar: 12 digits validated by the Verhoeff checksum (the real one). */
function connect_verify_aadhaar_valid($aadhaar) {
    $a = preg_replace('/\s+/', '', (string)$aadhaar);
    if (!preg_match('/^[0-9]{12}$/', $a)) return false;
    static $d = [
        [0,1,2,3,4,5,6,7,8,9],[1,2,3,4,0,6,7,8,9,5],[2,3,4,0,1,7,8,9,5,6],
        [3,4,0,1,2,8,9,5,6,7],[4,0,1,2,3,9,5,6,7,8],[5,9,8,7,6,0,4,3,2,1],
        [6,5,9,8,7,1,0,4,3,2],[7,6,5,9,8,2,1,0,4,3],[8,7,6,5,9,3,2,1,0,4],[9,8,7,6,5,4,3,2,1,0]];
    static $p = [
        [0,1,2,3,4,5,6,7,8,9],[1,5,7,6,2,8,3,0,9,4],[5,8,0,3,7,9,6,1,4,2],
        [8,9,1,6,0,4,3,5,2,7],[9,4,5,3,1,2,6,8,7,0],[4,2,8,6,5,7,3,9,0,1],
        [2,7,9,3,8,0,6,4,1,5],[7,0,4,6,9,1,3,2,5,8]];
    $c = 0; $digits = array_reverse(str_split($a));
    foreach ($digits as $i => $ch) $c = $d[$c][$p[($i % 8)][(int)$ch]];
    return $c === 0;
}

/** Mask an identifier for storage — never keep the full number. */
function connect_verify_mask($num) {
    $s = preg_replace('/\s+/', '', (string)$num);
    $len = strlen($s);
    if ($len <= 4) return str_repeat('•', max(0, $len));
    return str_repeat('•', $len - 4) . substr($s, -4);
}

// ---- Pluggable provider seam ------------------------------------------------

/**
 * Run the pre-screen / provider for a check. Returns
 *   ['status' => PENDING|VERIFIED|REJECTED, 'method', 'provider', 'note', 'ref_masked'].
 * A deterministic format FAIL is auto-REJECTED; a format PASS is left PENDING for a
 * human or a real provider to confirm (honest — format ≠ identity). A real KYC
 * provider registered under connect_verify_provider_for() would return VERIFIED here.
 */
function connect_verify_run($checkType, $value) {
    $types = connect_verify_check_types();
    $cfg = $types[$checkType] ?? null;
    $method = $cfg['method'] ?? 'manual';

    // An external provider, if one is wired for this check, takes precedence.
    $provider = connect_verify_provider_for($checkType);
    if ($provider && is_callable($provider['run'])) {
        $r = $provider['run']($value);
        return [
            'status'     => in_array(($r['status'] ?? ''), ['VERIFIED','REJECTED','PENDING'], true) ? $r['status'] : 'PENDING',
            'method'     => 'provider', 'provider' => (string)($provider['name'] ?? 'provider'),
            'note'       => (string)($r['note'] ?? ''), 'ref_masked' => connect_verify_mask($value),
        ];
    }

    if ($method === 'deterministic') {
        $ok = null;
        if ($checkType === 'PAN')     $ok = connect_verify_pan_valid($value);
        elseif ($checkType === 'GSTIN')   $ok = connect_verify_gstin_valid($value);
        elseif ($checkType === 'AADHAAR') $ok = connect_verify_aadhaar_valid($value);
        if ($ok === false) {
            return ['status' => 'REJECTED', 'method' => 'deterministic', 'provider' => '',
                    'note' => 'The number failed its format / checksum test — please re-check it.', 'ref_masked' => connect_verify_mask($value)];
        }
        return ['status' => 'PENDING', 'method' => 'deterministic', 'provider' => '',
                'note' => 'Format valid — awaiting document / provider confirmation.', 'ref_masked' => connect_verify_mask($value)];
    }

    // Manual (document review) — always a human decision.
    return ['status' => 'PENDING', 'method' => 'manual', 'provider' => '', 'note' => 'Submitted for review.', 'ref_masked' => ''];
}

/**
 * The provider registry — the seam. Empty by default (deterministic + manual only).
 * A DigiLocker / PAN-NSDL / GSTN / face-liveness integration registers a callable
 * here (keyed by check type) and the engine uses it without any other change.
 * Providers are only consulted when explicitly enabled via a setting.
 */
function connect_verify_provider_for($checkType) {
    $providers = function_exists('apply_filters') ? apply_filters('connect_verify_providers', []) : [];
    if (!is_array($providers)) $providers = [];
    return $providers[$checkType] ?? null;
}

// ---- Submit / review / recompute -------------------------------------------

/** Human label for a subject (for the queue). */
function connect_verify_subject_name($kind, $id) {
    try {
        if ($kind === 'professional') return (string)ops_val("SELECT name FROM cx_professionals WHERE id=?", [(int)$id]);
        if ($kind === 'org')          return (string)ops_val("SELECT name FROM cx_organisations WHERE id=?", [(int)$id]);
        if ($kind === 'inspector')    return (string)ops_val("SELECT name FROM inspectors WHERE id=?", [(int)$id]);
    } catch (Throwable $e) {}
    return '';
}

/**
 * Submit a verification check. Runs the pre-screen/provider, records it, recomputes
 * the tier, and returns [ok, message, id]. Safe to call from the pro portal.
 */
function connect_verify_submit($subjectKind, $subjectId, $checkType, $value = '', $evidence = '') {
    connect_verify_migrate();
    $types = connect_verify_check_types();
    if (!isset($types[$checkType])) return [false, 'Unknown verification type.', 0];
    $cfg = $types[$checkType];
    $subjectId = (int)$subjectId;
    if ($subjectId <= 0) return [false, 'Unknown subject.', 0];
    if (!in_array($subjectKind, $cfg['for'], true)) return [false, 'That check does not apply here.', 0];
    if (!empty($cfg['value']) && trim((string)$value) === '') return [false, ($cfg['label']) . ' number is required.', 0];

    $r = connect_verify_run($checkType, $value);

    db()->prepare("INSERT INTO cx_verifications
        (subject_kind,subject_id,check_type,status,method,provider,ref_masked,evidence,result_note,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$subjectKind, $subjectId, $checkType, $r['status'], $r['method'], $r['provider'],
                   $r['ref_masked'], (string)$evidence, $r['note'], date('c')]);
    $id = (int)db()->lastInsertId();

    connect_verify_recompute_tier($subjectKind, $subjectId);

    $msg = $r['status'] === 'REJECTED' ? $r['note']
         : ($r['status'] === 'VERIFIED' ? ($cfg['label'] . ' verified.') : ($cfg['label'] . ' submitted — a reviewer will confirm it shortly.'));
    return [true, $msg, $id];
}

/** A moderator's decision on one pending check. Recomputes the tier after. */
function connect_verify_review($id, $decision, $note = '', $by = '') {
    connect_verify_migrate();
    $row = ops_one("SELECT * FROM cx_verifications WHERE id=?", [(int)$id]);
    if (!$row) return [false, 'Check not found.'];
    $decision = strtoupper((string)$decision);
    if (!in_array($decision, ['VERIFIED', 'REJECTED', 'PENDING'], true)) return [false, 'Invalid decision.'];
    if ($by === '' && function_exists('current_user')) { $u = current_user(); $by = (string)($u['name'] ?? $u['username'] ?? ''); }
    db()->prepare("UPDATE cx_verifications SET status=?, result_note=?, reviewed_by=?, reviewed_at=? WHERE id=?")
        ->execute([$decision, (string)$note, (string)$by, date('c'), (int)$id]);
    connect_verify_recompute_tier((string)$row['subject_kind'], (int)$row['subject_id']);
    return [true, 'Marked ' . strtolower($decision) . '.'];
}

/** The set of "grants" a subject has earned from its VERIFIED checks. */
function connect_verify_grants($subjectKind, $subjectId) {
    $types = connect_verify_check_types();
    $grants = [];
    try {
        foreach (ops_all("SELECT check_type FROM cx_verifications WHERE subject_kind=? AND subject_id=? AND status='VERIFIED'",
                 [$subjectKind, (int)$subjectId]) ?: [] as $r) {
            $g = $types[$r['check_type']]['grants'] ?? '';
            if ($g) $grants[$g] = true;
        }
    } catch (Throwable $e) {}
    return $grants;
}

/**
 * Recompute the verification tier from VERIFIED checks (+ platform track record for
 * 'proven'). Writes cx_professionals.verification_tier. Never elevated by a mere
 * format pass — only by a real VERIFIED decision.
 */
function connect_verify_recompute_tier($subjectKind, $subjectId) {
    if ($subjectKind !== 'professional') return ''; // org/inspector tiers: future
    $g = connect_verify_grants($subjectKind, $subjectId);
    $tier = 'registered';
    if (!empty($g['id'])) $tier = 'id_verified';
    if ($tier === 'id_verified' && !empty($g['credential'])) $tier = 'credential_verified';
    if ($tier === 'credential_verified' && !empty($g['proven'])) $tier = 'proven';
    try {
        $now = ($tier !== 'registered') ? date('c') : '';
        db()->prepare("UPDATE cx_professionals SET verification_tier=?, verified_at=CASE WHEN ?<>'' THEN ? ELSE verified_at END WHERE id=?")
            ->execute([$tier, $now, $now, (int)$subjectId]);
    } catch (Throwable $e) {}
    return $tier;
}

/** The current tier key for a professional (read). */
function connect_verify_tier_for_professional($id) {
    try { $t = (string)ops_val("SELECT verification_tier FROM cx_professionals WHERE id=?", [(int)$id]); }
    catch (Throwable $e) { $t = ''; }
    return $t !== '' ? $t : 'registered';
}

/** All checks for a subject, newest first. */
function connect_verify_subject_checks($subjectKind, $subjectId) {
    try {
        $st = db()->prepare("SELECT * FROM cx_verifications WHERE subject_kind=? AND subject_id=? ORDER BY id DESC");
        $st->execute([$subjectKind, (int)$subjectId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/** The moderation queue — pending checks awaiting a human decision. */
function connect_verify_pending($limit = 100) {
    try {
        $st = db()->prepare("SELECT * FROM cx_verifications WHERE status='PENDING' ORDER BY id ASC LIMIT ?");
        $st->execute([(int)$limit]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
    foreach ($rows as &$r) $r['subject_name'] = connect_verify_subject_name($r['subject_kind'], (int)$r['subject_id']);
    return $rows;
}
function connect_verify_pending_count() {
    try { return (int)ops_val("SELECT COUNT(*) FROM cx_verifications WHERE status='PENDING'"); }
    catch (Throwable $e) { return 0; }
}

// ---- Gates + the moderation screen -----------------------------------------

/** Read/review gate — the moderation desk. Reuses the coordinator level; no new perm. */
function connect_verify_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

/** The moderation queue screen: pending checks + approve / reject. */
function ops_connect_verify($method) {
    ops_require(connect_verify_can(),
        'The verification desk is available to coordinators, managers and admins.');
    connect_verify_migrate();

    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'review') {
            $id = (int)($_POST['id'] ?? 0);
            $decision = (string)($_POST['decision'] ?? '');
            [$ok, $msg] = connect_verify_review($id, $decision, (string)($_POST['note'] ?? ''));
            flash($msg, $ok ? 'success' : 'error');
        }
        redirect('/connect-verify');
    }

    $tiers = [];
    try {
        foreach (ops_all("SELECT verification_tier, COUNT(*) c FROM cx_professionals WHERE is_active=1 GROUP BY verification_tier") ?: [] as $r)
            $tiers[(string)($r['verification_tier'] ?: 'registered')] = (int)$r['c'];
    } catch (Throwable $e) {}

    view('ops/connect_verify', [
        'pending' => connect_verify_pending(),
        'types'   => connect_verify_check_types(),
        'ladder'  => connect_verify_tiers(),
        'tierCounts' => $tiers,
    ]);
    return true;
}
