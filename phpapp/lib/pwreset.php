<?php
// ============================================================================
//  FORGOT / RESET PASSWORD — self-service, e-mail based.
//
//  A person who forgets their password can recover on their own: they enter
//  their e-mail, receive a one-time link, and set a new password. No dependence
//  on an administrator, no shared secrets.
//
//  Multi-tenant aware. On the single product URL a person is identified by
//  e-mail; the request is routed to THEIR workspace, the token is stored in that
//  workspace's own database, and the reset link carries the workspace key so the
//  "set new password" page opens against the right database. On a plain single
//  install it simply uses the one database.
//
//  Safe by construction:
//   • tokens are random and stored only as a SHA-256 hash — the raw token lives
//     only in the e-mailed link;
//   • one-time use, and they expire (default 60 minutes);
//   • the "we sent a link" reply is identical whether or not the account exists,
//     so the page can never be used to discover who has an account.
// ============================================================================

const PWRESET_TTL = 3600;   // a reset link is valid for 60 minutes

function pwreset_migrate() {
    static $done = [];
    $k = function_exists('current_tenant') ? (string) current_tenant() : '';
    if (isset($done[$k])) return;
    $done[$k] = true;
    try {
        $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id $pk,
            user_id INTEGER,
            email VARCHAR(190),
            token_hash VARCHAR(64),
            created_at VARCHAR(40),
            expires_at INTEGER,
            used_at VARCHAR(40)
        )");
    } catch (Throwable $e) {}
}

// Route a public request to the right workspace by e-mail (single-URL SaaS), or
// stay on the one database. Returns the workspace key entered ('' for none).
function pwreset_enter_workspace_for($loginId) {
    if (function_exists('saas_leave_tenant')) saas_leave_tenant();
    $loginId = trim((string) $loginId);
    if ($loginId === '' || strpos($loginId, '@') === false) return '';
    if (!function_exists('saas_login_lookup')) return '';
    $tk = saas_login_lookup($loginId);
    if ($tk !== '' && function_exists('saas_enter_tenant')) {
        saas_enter_tenant($tk);
        if (function_exists('saas_tenant_ensure_ready') && !saas_tenant_ensure_ready($tk)) {
            if (function_exists('saas_leave_tenant')) saas_leave_tenant();
            return '';
        }
        try { db(); } catch (Throwable $e) {}
        return $tk;
    }
    return '';
}

// MILESTONE 13. This is handed a workspace key that arrives in the QUERY STRING
// (/reset?t=…&w=…), so it is attacker-controlled by definition. It has to stay —
// the reset token lives in the workspace's own database, and there is no way to
// find it without opening that database first.
//
// What it must never do is switch the workspace out from under somebody who is
// ALREADY SIGNED IN. Before M13 that carried their session identity into another
// company's database, because a uid was not bound to the workspace that issued
// it. The binding in current_user() now stops the identity resolving there at
// all; this refuses to make the switch in the first place, so a signed-in person
// cannot be moved by a link somebody sent them.
//
// Resetting a password is something you do when you are NOT signed in. If you
// are, the reset page has nothing to do with your session.
function pwreset_enter_workspace_key($wkey) {
    $wkey = strtolower(trim((string) $wkey));
    if (function_exists('current_user') && current_user()) return '';   // M13 — never move a signed-in session
    if (function_exists('saas_leave_tenant')) saas_leave_tenant();
    if ($wkey === '' || !function_exists('saas_enter_tenant')) return '';
    saas_enter_tenant($wkey);
    if (function_exists('saas_tenant_ensure_ready') && !saas_tenant_ensure_ready($wkey)) {
        if (function_exists('saas_leave_tenant')) saas_leave_tenant();
        return '';
    }
    try { db(); } catch (Throwable $e) {}
    return $wkey;
}

// Create a reset token for a matching, active account and e-mail the link.
// Returns true if a link was actually sent (used only for internal logging;
// the user always sees the same neutral message).
function pwreset_request($loginId) {
    $loginId = trim((string) $loginId);
    if ($loginId === '') return false;
    $wkey = pwreset_enter_workspace_for($loginId);
    pwreset_migrate();

    try {
        $u = (strpos($loginId, '@') !== false)
            ? ops_one("SELECT * FROM users WHERE (email = ? OR username = ?) AND is_active = 1 ORDER BY id LIMIT 1", [$loginId, $loginId])
            : ops_one("SELECT * FROM users WHERE username = ? AND is_active = 1 ORDER BY id LIMIT 1", [$loginId]);
    } catch (Throwable $e) { $u = null; }
    if (!$u) return false;
    $email = trim((string) ($u['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;   // nowhere to send it

    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    try {
        db()->prepare("INSERT INTO password_resets (user_id, email, token_hash, created_at, expires_at, used_at)
                       VALUES (?,?,?,?,?, '')")
            ->execute([(int) $u['id'], $email, $hash, date('c'), time() + PWRESET_TTL]);
    } catch (Throwable $e) { return false; }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $link   = $scheme . '://' . $host . '/reset?t=' . rawurlencode($token) . ($wkey !== '' ? '&w=' . rawurlencode($wkey) : '');
    $name   = function_exists('user_name') ? user_name($u) : (string) ($u['username'] ?? '');
    $app    = function_exists('app_name') ? app_name() : 'Your workspace';
    $body   = "Hello " . $name . ",\n\n"
            . "We received a request to reset your password for " . $app . ".\n\n"
            . "Open this link to set a new password (valid for 60 minutes):\n"
            . $link . "\n\n"
            . "If you did not ask for this, you can ignore this e-mail — your password stays the same.\n\n"
            . "— " . $app;
    try { if (function_exists('ops_mail')) ops_mail($email, $app . ' — reset your password', $body, '', 'password_reset'); }
    catch (Throwable $e) {}
    return true;
}

// Look up a valid, unused, unexpired reset by raw token in the CURRENT database.
function pwreset_lookup($token) {
    $token = (string) $token;
    if ($token === '') return null;
    pwreset_migrate();
    $hash = hash('sha256', $token);
    try {
        $r = ops_one("SELECT * FROM password_resets WHERE token_hash = ? AND (used_at = '' OR used_at IS NULL) ORDER BY id DESC LIMIT 1", [$hash]);
    } catch (Throwable $e) { return null; }
    if (!$r) return null;
    if ((int) ($r['expires_at'] ?? 0) < time()) return null;
    return $r;
}

// Apply a new password for a valid token. Returns '' on success or a reason.
function pwreset_apply($token, $newpass) {
    $r = pwreset_lookup($token);
    if (!$r) return 'This reset link is invalid or has expired. Please request a new one.';
    $newpass = (string) $newpass;
    if (strlen($newpass) < 8) return 'Please choose a password of at least 8 characters.';
    try {
        $hash = password_hash($newpass, PASSWORD_DEFAULT);
        db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, (int) $r['user_id']]);
        db()->prepare("UPDATE password_resets SET used_at = ? WHERE id = ?")->execute([date('c'), (int) $r['id']]);
        // Clear any sign-in lockout so they can use the new password at once.
        if (function_exists('login_clear')) { $u = ops_one("SELECT username FROM users WHERE id=?", [(int) $r['user_id']]); if ($u) @login_clear((string) $u['username']); }
    } catch (Throwable $e) { return 'Could not set the new password. Please try again.'; }
    return '';
}

// ---- Public routes (dispatched in front of the login gate) -----------------
function pwreset_forgot_route($method) {
    if (function_exists('current_user') && current_user()) redirect('/change-password');
    $sent = false; $emailShown = '';
    if ($method === 'POST' && csrf_ok($_POST['_csrf'] ?? '')) {
        $emailShown = trim((string) ($_POST['email'] ?? ''));
        if ($emailShown !== '') { try { pwreset_request($emailShown); } catch (Throwable $e) {} $sent = true; }
    }
    require __DIR__ . '/../views/forgot_password.php';
    exit;
}

function pwreset_reset_route($method) {
    $token = (string) ($_GET['t'] ?? ($_POST['t'] ?? ''));
    $wkey  = (string) ($_GET['w'] ?? ($_POST['w'] ?? ''));
    if ($wkey !== '') pwreset_enter_workspace_key($wkey);
    $error = ''; $done = false;
    $r = pwreset_lookup($token);
    if (!$r) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif ($method === 'POST' && csrf_ok($_POST['_csrf'] ?? '')) {
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');
        if ($p1 !== $p2) $error = 'The two passwords do not match.';
        else {
            $error = pwreset_apply($token, $p1);
            if ($error === '') $done = true;
        }
    }
    require __DIR__ . '/../views/reset_password.php';
    exit;
}
