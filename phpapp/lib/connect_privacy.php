<?php
// ============================================================================
//  CONNECT — Professional privacy states & contact-reveal  (K0+, additive)
//
//  A professional owns their passport. Before a working relationship exists a
//  client should discover WHAT a person can do without harvesting WHO they are
//  and how to reach them off-platform. This engine gives every professional
//  three plain-language controls and a single resolver the whole marketplace
//  (search, result cards, matching, the public passport) consumes so the same
//  privacy rules are enforced everywhere — never re-decided per screen.
//
//    • contact   — hidden | on_request | public   (mobile / email)
//    • rate      — hidden | band       | public   (day rate)
//    • identity  — full   | first_initial          (display name before reveal)
//    • listed    — 1 | 0  (appear in client discovery at all)
//
//  A "contact reveal" is an explicit, logged grant from a professional's own
//  contact rules being satisfied for one client party (an existing engagement /
//  award relationship, or a professional-approved reveal request). Contact is
//  NEVER shown just because a client asks — that is the whole point.
//
//  STRICTLY ADDITIVE: four columns on cx_professionals (with safe defaults that
//  preserve today's behaviour) + one grants table. No permission-matrix change:
//  a professional controlling the visibility of their own data needs no new
//  grant, and the reveal is consumed — not created — by client screens here.
// ============================================================================

/** Column defaults chosen so existing rows behave exactly as before this engine. */
function connect_privacy_defaults() {
    return ['contact' => 'on_request', 'rate' => 'band', 'identity' => 'full', 'listed' => 1];
}

function connect_privacy_migrate() {
    static $done = false; if ($done) return; $done = true;
    // Columns live on the professional master (one record, reused everywhere).
    if (function_exists('ensure_column')) {
        try { ensure_column('cx_professionals', 'privacy_contact',  "VARCHAR(16) DEFAULT 'on_request'"); } catch (Throwable $e) {}
        try { ensure_column('cx_professionals', 'privacy_rate',     "VARCHAR(16) DEFAULT 'band'"); }       catch (Throwable $e) {}
        try { ensure_column('cx_professionals', 'privacy_identity', "VARCHAR(16) DEFAULT 'full'"); }       catch (Throwable $e) {}
        try { ensure_column('cx_professionals', 'privacy_listed',   "INT DEFAULT 1"); }                    catch (Throwable $e) {}
    }
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    // Explicit, logged contact-reveal grants: one row per (pro, client party).
    db()->exec("CREATE TABLE IF NOT EXISTS cx_pro_contact_reveals (
        id $pk, pro_id INT DEFAULT 0, client_party_id INT DEFAULT 0,
        via VARCHAR(24) DEFAULT '', note VARCHAR(200) DEFAULT '',
        granted_at VARCHAR(30) DEFAULT '', revoked_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE UNIQUE INDEX ux_cx_reveal ON cx_pro_contact_reveals (pro_id, client_party_id)"); } catch (Throwable $e) {}
}

// ---- The professional's own settings ---------------------------------------

/** Read the normalised privacy settings for a professional (defaults applied). */
function connect_privacy_get($proId) {
    connect_privacy_migrate();
    $d = connect_privacy_defaults();
    $row = ops_one("SELECT privacy_contact, privacy_rate, privacy_identity, privacy_listed FROM cx_professionals WHERE id=?", [(int)$proId]);
    if (!$row) return $d;
    return [
        'contact'  => connect_privacy_norm('contact',  $row['privacy_contact']  ?? '', $d['contact']),
        'rate'     => connect_privacy_norm('rate',     $row['privacy_rate']     ?? '', $d['rate']),
        'identity' => connect_privacy_norm('identity', $row['privacy_identity'] ?? '', $d['identity']),
        'listed'   => ($row['privacy_listed'] === null) ? 1 : (int)$row['privacy_listed'],
    ];
}

/** Whitelist a value against the allowed set for one control; fall back to default. */
function connect_privacy_norm($which, $val, $default = null) {
    $allowed = [
        'contact'  => ['hidden', 'on_request', 'public'],
        'rate'     => ['hidden', 'band', 'public'],
        'identity' => ['full', 'first_initial'],
    ];
    $val = strtolower(trim((string)$val));
    $set = $allowed[$which] ?? [];
    if (in_array($val, $set, true)) return $val;
    return $default ?? ($set[0] ?? '');
}

/** Save a professional's privacy settings (only known keys, whitelisted). */
function connect_privacy_save($proId, array $in) {
    connect_privacy_migrate();
    $proId = (int)$proId; if ($proId <= 0) return [false, 'Unknown professional.'];
    $d = connect_privacy_defaults();
    $contact  = connect_privacy_norm('contact',  $in['contact']  ?? '', $d['contact']);
    $rate     = connect_privacy_norm('rate',     $in['rate']     ?? '', $d['rate']);
    $identity = connect_privacy_norm('identity', $in['identity'] ?? '', $d['identity']);
    $listed   = !empty($in['listed']) ? 1 : 0;
    db()->prepare("UPDATE cx_professionals SET privacy_contact=?, privacy_rate=?, privacy_identity=?, privacy_listed=? WHERE id=?")
        ->execute([$contact, $rate, $identity, $listed, $proId]);
    return [true, 'Privacy settings saved.'];
}

// ---- Contact-reveal grants (consumed by client screens) --------------------

/** Has this client party earned contact for this professional? */
function connect_privacy_contact_revealed($proId, $clientPartyId) {
    connect_privacy_migrate();
    if ((int)$clientPartyId <= 0) return false;
    return (bool)ops_val(
        "SELECT COUNT(*) FROM cx_pro_contact_reveals WHERE pro_id=? AND client_party_id=? AND revoked_at=''",
        [(int)$proId, (int)$clientPartyId]);
}

/** Record a contact-reveal grant (idempotent). `via` explains how it was earned. */
function connect_privacy_reveal_grant($proId, $clientPartyId, $via = 'engagement', $note = '') {
    connect_privacy_migrate();
    $proId = (int)$proId; $clientPartyId = (int)$clientPartyId;
    if ($proId <= 0 || $clientPartyId <= 0) return false;
    if (connect_privacy_contact_revealed($proId, $clientPartyId)) return true;
    db()->prepare("INSERT INTO cx_pro_contact_reveals (pro_id, client_party_id, via, note, granted_at) VALUES (?,?,?,?,?)")
        ->execute([$proId, $clientPartyId, substr((string)$via, 0, 24), substr((string)$note, 0, 200), date('c')]);
    return true;
}

function connect_privacy_reveal_revoke($proId, $clientPartyId) {
    connect_privacy_migrate();
    db()->prepare("UPDATE cx_pro_contact_reveals SET revoked_at=? WHERE pro_id=? AND client_party_id=? AND revoked_at=''")
        ->execute([date('c'), (int)$proId, (int)$clientPartyId]);
    return true;
}

// ---- The single resolver every screen consumes -----------------------------

/**
 * Given a professional row and a viewer context, return exactly what this viewer
 * may see — the ONE place privacy is decided.
 *
 *   $ctx = [
 *     'is_owner'   => bool,   // the professional viewing their own passport
 *     'is_staff'   => bool,   // platform coordinator/manager (moderation)
 *     'party_id'   => int,    // the viewing client's party id (for reveal lookup)
 *     'engaged'    => bool,   // an award/engagement relationship already exists
 *   ]
 *
 * Returns:
 *   display_name, identity_masked (bool),
 *   contact_visible (bool), contact_reason ('owner'|'staff'|'engaged'|'revealed'|'public'|''),
 *   mobile, email  (masked to '' unless visible),
 *   rate_mode ('public'|'band'|'hidden'), listed (bool), settings (raw).
 */
function connect_privacy_resolve(array $pro, array $ctx = []) {
    $proId    = (int)($pro['id'] ?? 0);
    $s        = connect_privacy_get($proId);
    $isOwner  = !empty($ctx['is_owner']);
    $isStaff  = !empty($ctx['is_staff']);
    $engaged  = !empty($ctx['engaged']);
    $partyId  = (int)($ctx['party_id'] ?? 0);
    $revealed = $engaged || ($partyId > 0 && connect_privacy_contact_revealed($proId, $partyId));
    $privileged = $isOwner || $isStaff;

    // --- Identity -----------------------------------------------------------
    $full = trim((string)($pro['name'] ?? '')) ?: 'Technical professional';
    $maskIdentity = ($s['identity'] === 'first_initial') && !$privileged && !$revealed;
    $display = $maskIdentity ? connect_privacy_first_initial($full) : $full;

    // --- Contact ------------------------------------------------------------
    $reason = '';
    if ($isOwner)                       $reason = 'owner';
    elseif ($isStaff)                   $reason = 'staff';
    elseif ($s['contact'] === 'public') $reason = 'public';
    elseif ($revealed)                  $reason = $engaged ? 'engaged' : 'revealed';
    // 'on_request' with no reveal, or 'hidden', => not visible.
    $contactVisible = ($reason !== '') && !($s['contact'] === 'hidden' && !$privileged && !$revealed);
    // hidden overrides everything except owner/staff/an explicit reveal:
    if ($s['contact'] === 'hidden' && !$privileged && !$revealed) { $contactVisible = false; $reason = ''; }

    return [
        'display_name'    => $display,
        'identity_masked' => $maskIdentity,
        'contact_visible' => $contactVisible,
        'contact_reason'  => $reason,
        'mobile'          => $contactVisible ? (string)($pro['mobile'] ?? '') : '',
        'email'           => $contactVisible ? (string)($pro['email'] ?? '') : '',
        'mobile_masked'   => connect_privacy_mask_phone((string)($pro['mobile'] ?? '')),
        'rate_mode'       => $privileged ? 'public' : $s['rate'],
        'listed'          => (bool)$s['listed'],
        'settings'        => $s,
    ];
}

/** "Rajesh Kumar" -> "Rajesh K." ; single word left whole. */
function connect_privacy_first_initial($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    if (!$parts || count($parts) < 2) return (string)$name;
    $first = array_shift($parts);
    $init = '';
    foreach ($parts as $p) { if ($p !== '') $init .= strtoupper($p[0]) . '.'; }
    return trim($first . ' ' . $init);
}

/** A privacy-safe teaser of a phone number for "on_request" cards (last 2 digits). */
function connect_privacy_mask_phone($num) {
    $s = preg_replace('/[^0-9]/', '', (string)$num);
    $len = strlen($s);
    if ($len < 4) return '';
    return '+•• ••••• ••' . substr($s, -2);
}

/** Human labels for the three controls (UI + tests share one vocabulary). */
function connect_privacy_labels() {
    return [
        'contact' => [
            'hidden'     => ['Hidden',      'No one sees your phone or e-mail. Clients reach you only through platform messages.'],
            'on_request' => ['On request',  'Clients see you can be reached; your number unlocks when you approve, or once you are engaged.'],
            'public'     => ['Public',      'Any signed-in client can see your phone and e-mail.'],
        ],
        'rate' => [
            'hidden' => ['Hidden',   'Your day rate is never shown; you quote per enquiry.'],
            'band'   => ['Show band','Clients see a rate range, not the exact figure.'],
            'public' => ['Public',   'Your exact day rate is shown on your card.'],
        ],
        'identity' => [
            'full'          => ['Full name',      'Your full name is shown in search results.'],
            'first_initial' => ['First name only','Search shows "Rajesh K." until a client is engaged or you reveal contact.'],
        ],
    ];
}
