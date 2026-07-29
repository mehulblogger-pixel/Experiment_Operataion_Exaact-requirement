<?php
// ============================================================================
//  MGH single sign-on — one login for every app in the portfolio
//
//  IMPLEMENTS SSO_CONTRACT.md from the Ads Pro repository, unchanged. The
//  contract is short and deliberately so: there is no shared user database,
//  because the apps are deployed separately. Instead every app trusts one shared
//  secret and a signed, short-lived token:
//
//      token = base64url(payload) . "." . base64url(HMAC_SHA256(payload, secret))
//      payload = {"email", "name", "exp"}
//
//  A user signing in at the login home reaches Books, BlogPro, Ads Pro and this
//  CRM without typing a password again. E-mail is the single identity key.
//
//  THIS APP IS A CONSUMER, NOT AN ISSUER, and that is a decision worth stating.
//  Ads Pro already implements both sides and can be the home. Minting tokens
//  from here would mean this system could assert any identity to every sibling
//  app — a much larger thing to get right than accepting one, and nobody asked
//  for it. Implementing only verify keeps the blast radius of a bug here to this
//  application.
//
//  WHAT IT WILL NOT DO, and these are refusals rather than omissions:
//
//    * It will not CREATE a user. The contract's find-or-create is wrong for
//      this product: a user here carries a role, an office, a business unit and
//      a scope, and inventing those from an e-mail address would hand somebody a
//      working login with permissions nobody chose. An unknown e-mail is told,
//      in plain words, to ask an administrator — and the attempt is logged so
//      the administrator can see it happened.
//    * It will not accept a token for a user who is inactive. Deactivating
//      somebody must lock every door, not merely the one with a password on it.
//    * It will not accept a token twice. The contract says one-time use in
//      practice; here it is enforced, so a token left in a browser history or a
//      referrer header cannot be replayed.
//    * It will not run at all unless MGH_SSO_SECRET is set. No secret, no
//      feature, no surprise back door on an install that never asked for one.
// ============================================================================

function sso_secret() {
    $s = getenv('MGH_SSO_SECRET');
    return ($s === false) ? '' : (string)$s;
}
function sso_on() { return strlen(sso_secret()) >= 32; }

function sso_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }

function sso_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    // Every attempt, accepted or refused. Doubles as the replay guard: a token
    // whose signature we have already seen is refused whatever it says.
    $pdo->exec("CREATE TABLE IF NOT EXISTS sso_attempts (
        id $pk, sig VARCHAR(64) DEFAULT '', email VARCHAR(200) DEFAULT '',
        result VARCHAR(30) DEFAULT '', detail VARCHAR(300) DEFAULT '',
        ip VARCHAR(60) DEFAULT '', at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) {
        act_index('sso_attempts', 'idx_sso_sig', '(sig)');
        act_index('sso_attempts', 'idx_sso_when', '(at)');
    }
}

// ---- The contract's own two functions, character for character in behaviour --
function sso_b64($s)   { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function sso_unb64($s) { return base64_decode(strtr($s, '-_', '+/')); }

function sso_verify($token) {
    if (!sso_on()) return ['ok' => false, 'error' => 'sso disabled'];
    $x = explode('.', (string)$token);
    if (count($x) !== 2) return ['ok' => false, 'error' => 'malformed'];
    // hash_equals, not ===. A timing-variable comparison on a signature is the
    // one place in this file where being clever would actually matter.
    if (!hash_equals(sso_b64(hash_hmac('sha256', $x[0], sso_secret(), true)), $x[1]))
        return ['ok' => false, 'error' => 'bad signature'];
    $c = json_decode(sso_unb64($x[0]), true);
    if (!is_array($c) || empty($c['email'])) return ['ok' => false, 'error' => 'no email'];
    if ((int)($c['exp'] ?? 0) < time())      return ['ok' => false, 'error' => 'expired'];
    return ['ok' => true, 'email' => strtolower(trim((string)$c['email'])),
            'name' => trim((string)($c['name'] ?? ''))];
}

function sso_note($sig, $email, $result, $detail = '') {
    sso_migrate();
    sso_try(fn() => db()->prepare(
        "INSERT INTO sso_attempts (sig, email, result, detail, ip, at) VALUES (?,?,?,?,?,?)")
        ->execute([substr((string)$sig, 0, 64), substr((string)$email, 0, 200),
                   substr((string)$result, 0, 30), substr((string)$detail, 0, 300),
                   substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 60), date('c')]));
}

// ---- The handoff ------------------------------------------------------------
// Called from the front controller when ?sso= is present, BEFORE the login gate.
// Returns true when a session was started, in which case the caller redirects to
// a clean URL so the token never sits in an address bar, a bookmark or a
// Referer header on the next outbound request.
function sso_accept($token) {
    if (!sso_on()) return false;
    sso_migrate();
    $sig = substr(hash('sha256', (string)$token), 0, 64);

    $seen = (int)(sso_try(fn() => ops_val("SELECT COUNT(*) FROM sso_attempts WHERE sig=?", [$sig]), 0) ?: 0);
    if ($seen > 0) { sso_note($sig, '', 'REPLAY', 'This token had already been used.'); return false; }

    $v = sso_verify($token);
    if (!$v['ok']) { sso_note($sig, '', 'REFUSED', $v['error']); return false; }

    $email = $v['email'];
    $u = sso_try(fn() => ops_one("SELECT * FROM users WHERE LOWER(email)=? LIMIT 1", [$email]), null);
    if (!$u) {
        // Named refusal, not a shrug. The administrator needs to see that a real
        // person tried and was turned away, or the report will be "the link is
        // broken" rather than "I have no account".
        sso_note($sig, $email, 'NO_ACCOUNT', 'No user here carries that e-mail address.');
        $_SESSION['sso_msg'] = 'You are signed in to the MGH portfolio as ' . $email
            . ', but no account here uses that address. An administrator has to create one — this system will not '
            . 'invent a login, because it would have to invent your role, office and permissions with it.';
        return false;
    }
    if ((int)($u['is_active'] ?? 0) !== 1) {
        sso_note($sig, $email, 'INACTIVE', 'The account exists but is switched off.');
        $_SESSION['sso_msg'] = 'That account has been deactivated here. Ask an administrator to switch it back on.';
        return false;
    }

    // SECOND FACTOR IS NOT WAIVED. A sibling app proved who they are; it did not
    // prove possession of this person's phone, and that is the entire point of
    // the second factor. Somebody with 2FA on lands on the code screen exactly as
    // a password login does — half signed in, nothing authorised yet. Treating an
    // SSO handoff as sufficient would mean anybody who compromised the weakest
    // app in the portfolio walked straight past 2FA in the strongest.
    if (function_exists('twofa_on') && twofa_on($u)) {
        sso_note($sig, $email, 'OK_2FA', 'Accepted, now waiting for the second factor.');
        session_regenerate_id(true);
        $_SESSION['pending_uid'] = $u['id'];
        $_SESSION['pending_at']  = time();
        return false;   // the login screen takes it from here and asks for the code
    }

    sso_note($sig, $email, 'OK', 'Signed in via ' . ($v['name'] !== '' ? $v['name'] : 'MGH SSO'));
    sso_start_session($u);
    return true;
}

// One place knows how to log somebody in, and it is not this file. complete_login()
// regenerates the session id, clears the failure counters, drops the stale CSRF
// token and writes the audit row; a second implementation here would be a second
// place to forget one of those.
function sso_start_session(array $u) {
    if (function_exists('complete_login')) { complete_login($u); return; }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['started_at'] = time();
    sso_try(fn() => db()->prepare("UPDATE users SET last_login_at=?, last_login_ip=? WHERE id=?")
        ->execute([date('c'), substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), (int)$u['id']]));
}

// ---- The switcher -----------------------------------------------------------
// MGH_APPS as specified: {"key":{"name":…,"url":…}}. Used only to draw links to
// the siblings. Following one lands on that app's own login unless it received a
// token, which is correct — this app does not mint tokens, so it cannot pretend
// the user is already signed in over there.
function sso_apps() {
    $raw = getenv('MGH_APPS');
    if ($raw === false || trim((string)$raw) === '') return [];
    $j = json_decode((string)$raw, true);
    if (!is_array($j)) return [];
    $out = [];
    foreach ($j as $k => $a) {
        $url = (string)($a['url'] ?? '');
        // Only http(s), and never a javascript: or data: URL from an env value
        // that a deployment script wrote.
        if (!preg_match('~^https?://~i', $url)) continue;
        $out[(string)$k] = ['name' => (string)($a['name'] ?? $k), 'url' => rtrim($url, '/')];
    }
    return $out;
}

function sso_recent($n = 30) {
    sso_migrate();
    return sso_try(fn() => ops_all("SELECT * FROM sso_attempts ORDER BY id DESC LIMIT " . (int)$n), []) ?: [];
}

function ops_sso($route, $method) {
    ops_require(can('users.manage.global') || is_master(), 'You cannot see the sign-on log.');
    sso_migrate();
    view('ops/sso', ['on' => sso_on(), 'apps' => sso_apps(), 'rows' => sso_recent(40),
                     'secretSet' => sso_secret() !== '', 'shortSecret' => sso_secret() !== '' && !sso_on()]);
    return true;
}
