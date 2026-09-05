<?php
// =========================================================================
//  MGH Hire — seat-based licensing.
//
//  A licence is a short signed key issued by the vendor (you). Because it is
//  signed with a secret the customer never has, a self-hosted customer cannot
//  give themselves more seats — editing the stored numbers fails the signature
//  check and the app falls back to the free tier. Seats are counted as ACTIVE
//  user logins; adding a user beyond the limit is blocked.
//
//  Issue a key with:  php tools/licence-issue.php "Customer" 25 2027-03-31
// =========================================================================

// The signing secret. Override in production via env so it is not in the repo
// copy a customer receives.  KEEP THIS PRIVATE — anyone with it can mint keys.
function licence_secret() {
    $env = getenv('MGHHIRE_LICENCE_SECRET');
    return $env !== false && $env !== '' ? $env : 'mgh-hire-default-signing-secret-change-me';
}

// Free tier used when there is no valid licence.
function licence_free_seats() { return 3; }

// Build the signed key text from claims.
function licence_make($claims) {
    $json = json_encode($claims, JSON_UNESCAPED_SLASHES);
    $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $sig  = licence_sign($body);
    return $body . '.' . $sig;
}
function licence_sign($body) {
    return rtrim(strtr(base64_encode(hash_hmac('sha256', $body, licence_secret(), true)), '+/', '-_'), '=');
}

// Verify + decode a key. Returns claims array, or null if invalid/tampered.
function licence_parse($key) {
    $key = trim((string)$key);
    if (strpos($key, '.') === false) return null;
    [$body, $sig] = explode('.', $key, 2);
    if (!hash_equals(licence_sign($body), $sig)) return null;    // tampered
    $json = base64_decode(strtr($body, '-_', '+/'));
    $c = json_decode($json, true);
    return is_array($c) ? $c : null;
}

// The active licence claims (from the stored key), or null.
function licence_claims() {
    static $c = false;
    if ($c === false) $c = licence_parse(setting('licence_key', ''));
    return $c;
}

// One tidy status object used by the UI and by enforcement.
function licence_status() {
    $claims = licence_claims();
    $limit  = $claims ? (int)($claims['seats'] ?? 0) : licence_free_seats();
    $plan   = $claims ? (string)($claims['plan'] ?? 'Licensed') : 'Free tier';
    $exp    = $claims ? (string)($claims['exp'] ?? '') : '';
    $cust   = $claims ? (string)($claims['cust'] ?? '') : '';
    $used   = (int)db()->query("SELECT COUNT(*) c FROM users WHERE active=1")->fetch()['c'];
    $expired = $exp && strtotime($exp) && strtotime($exp) < strtotime(date('Y-m-d'));
    return [
        'plan'      => $plan,
        'customer'  => $cust,
        'limit'     => $limit,               // 0 = unlimited
        'used'      => $used,
        'remaining' => $limit ? max(0, $limit - $used) : null,
        'exp'       => $exp,
        'expired'   => $expired,
        'licensed'  => (bool)$claims,
        'over'      => $limit > 0 && $used > $limit,
    ];
}

// Can one more active user be added right now?
function seats_available() {
    $s = licence_status();
    if ($s['expired']) return false;
    if ($s['limit'] === 0) return true;      // unlimited
    return $s['used'] < $s['limit'];
}

// Apply a pasted key. Returns [ok, message].
function licence_apply($key) {
    $c = licence_parse($key);
    if (!$c) return [false, 'That licence key is invalid or has been altered.'];
    setting_set('licence_key', trim($key));
    return [true, 'Licence applied: ' . ($c['plan'] ?? 'Licensed') . ' — ' . (int)($c['seats'] ?? 0) . ' seats.'];
}
