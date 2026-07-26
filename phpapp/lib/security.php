<?php
// ===========================================================================
//  Security guards
//
//  Everything here defends the front door: how a password is chosen, how long
//  a session lasts, how many wrong guesses are allowed and from where, and the
//  second factor. It is kept in one file so that a person auditing this app —
//  and under the CERT-In rules somebody will, once a year — can read the whole
//  of it in one sitting rather than hunting through the modules.
//
//  Nothing in here is optional at runtime. The settings only choose how strict
//  it is, never whether it applies.
// ===========================================================================

// ---------------------------------------------------------------------------
//  Choosing a password
//
//  The old rule was eight characters and nothing else, which accepts
//  "password" and "12345678". A password that is only refused after somebody
//  has already used it for a year is no use, so this runs at the moment it is
//  set — on the change-password screen and when an administrator sets one for
//  somebody else.
// ---------------------------------------------------------------------------
function pwd_min_len() { $n = (int)setting_get('pwd_min_len', 10); return ($n >= 8 && $n <= 64) ? $n : 10; }

// The passwords that get tried first. Kept short deliberately: a long list
// belongs in a file nobody reads, and these are the ones that actually appear.
const PWD_COMMON = [
    'password','password1','password123','passw0rd','12345678','123456789','1234567890',
    'qwerty','qwerty123','abc12345','admin','admin123','admin1234','admin12345','administrator',
    'welcome','welcome1','welcome123','changeme','changeme123','letmein','iloveyou','monkey',
    'demo12345','demo1234','test1234','india123','company1','inspection','exaact123','1q2w3e4r',
];

// Returns the reasons a password cannot be used. Empty array means it is fine.
function password_problems($pw, $username = '', $name = '') {
    $pw = (string)$pw;
    $out = [];
    $min = pwd_min_len();
    if (strlen($pw) < $min) $out[] = 'be at least ' . $min . ' characters long';
    if (!preg_match('/[A-Za-z]/', $pw)) $out[] = 'contain at least one letter';
    if (!preg_match('/[0-9]/', $pw))    $out[] = 'contain at least one number';
    $low = strtolower($pw);
    if (in_array($low, PWD_COMMON, true)) $out[] = 'not be one of the passwords that get guessed first';
    // A password built out of the person's own name or login is public knowledge.
    foreach ([$username, $name] as $own) {
        $own = strtolower(trim((string)$own));
        if ($own !== '' && strlen($own) >= 4 && strpos($low, $own) !== false) {
            $out[] = 'not contain your own name or username'; break;
        }
    }
    if (preg_match('/^(.)\1+$/', $pw)) $out[] = 'not be the same character repeated';
    return array_values(array_unique($out));
}
// One sentence a person can act on, or '' when the password is acceptable.
function password_problem_text($pw, $username = '', $name = '') {
    $p = password_problems($pw, $username, $name);
    if (!$p) return '';
    return 'The password must ' . (count($p) === 1 ? $p[0] : implode(', ', array_slice($p, 0, -1)) . ' and ' . end($p)) . '.';
}

// ---------------------------------------------------------------------------
//  Passwords that were never changed
//
//  Every install starts with a known password, and the ones that are never
//  changed are how most small systems are actually broken into. These are
//  named on screen to whoever can do something about it.
// ---------------------------------------------------------------------------
const PWD_SHIPPED_DEFAULTS = ['admin12345', 'demo12345', 'changeme123', 'changeme'];
function user_on_default_password($u) {
    foreach (PWD_SHIPPED_DEFAULTS as $d)
        if (password_verify($d, (string)($u['password_hash'] ?? ''))) return true;
    return false;
}
function accounts_on_default_password() {
    $out = [];
    foreach (ops_all("SELECT id, username, first_name, last_name, role FROM users WHERE is_active=1") as $u) {
        $full = ops_one("SELECT password_hash FROM users WHERE id=?", [$u['id']]);
        $u['password_hash'] = $full['password_hash'] ?? '';
        if (user_on_default_password($u)) { unset($u['password_hash']); $out[] = $u; }
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  Password age
//
//  Off by default, because forced rotation makes people write passwords on
//  monitors. It is here because an auditor will ask for it and some client
//  contracts require it; set the number of days in Settings to switch it on.
// ---------------------------------------------------------------------------
function pwd_max_age_days() { $n = (int)setting_get('pwd_max_age_days', 0); return ($n >= 30 && $n <= 730) ? $n : 0; }
function pwd_expired($u) {
    $max = pwd_max_age_days();
    if (!$max) return false;
    $set = trim((string)($u['pwd_changed_at'] ?? ''));
    if ($set === '') return false;                    // never recorded: leave them alone
    return (time() - strtotime($set)) > $max * 86400;
}
function pwd_mark_changed($userId) {
    try { db()->prepare("UPDATE users SET pwd_changed_at=?, must_change_pwd=0 WHERE id=?")
              ->execute([date('c'), (int)$userId]); } catch (Throwable $e) {}
}

// ---------------------------------------------------------------------------
//  How long a session lasts
//
//  A laptop left open on a client's site is the realistic threat here, not a
//  clever attack. Idle time ends the session; the absolute limit ends it even
//  if somebody keeps it awake.
// ---------------------------------------------------------------------------
function session_idle_min()  { $n = (int)setting_get('session_idle_min', 60);  return ($n >= 5   && $n <= 1440) ? $n : 60; }
function session_max_hours() { $n = (int)setting_get('session_max_hours', 12); return ($n >= 1   && $n <= 168)  ? $n : 12; }

// Returns '' to carry on, or the reason the session has to end.
function session_expiry_reason() {
    $now = time();
    $started = (int)($_SESSION['started_at'] ?? 0);
    $seen    = (int)($_SESSION['last_seen'] ?? 0);
    if ($started && ($now - $started) > session_max_hours() * 3600)
        return 'You have been signed in for ' . session_max_hours() . ' hours. Please sign in again.';
    if ($seen && ($now - $seen) > session_idle_min() * 60)
        return 'You were away for more than ' . session_idle_min() . ' minutes, so you were signed out.';
    return '';
}
function session_touch() { $_SESSION['last_seen'] = time(); if (empty($_SESSION['started_at'])) $_SESSION['started_at'] = time(); }

// ---------------------------------------------------------------------------
//  Guessing from one place
//
//  The per-username limit stops one account being ground down. It does not
//  stop somebody working through a list of usernames from a single machine,
//  which is what a scanner actually does — so the source address is counted
//  too. The limit is deliberately loose, because a whole office often shares
//  one address and a bad afternoon should not lock all of them out.
// ---------------------------------------------------------------------------
const LOGIN_IP_MAX_TRIES = 30;
function client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // Behind the host's proxy the real address is in the forwarded header; the
    // first entry is the client, the rest are proxies.
    $fwd = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($fwd !== '') { $parts = explode(',', $fwd); $ip = trim($parts[0]); }
    return substr($ip, 0, 60);
}
function login_ip_key() { return 'ip:' . client_ip(); }
function login_ip_locked_for() {
    $r = login_row(login_ip_key());
    if (!$r || (int)$r['tries'] < LOGIN_IP_MAX_TRIES) return 0;
    $left = (int)ceil(LOGIN_LOCK_MIN - (time() - strtotime((string)$r['last_at'])) / 60);
    return $left > 0 ? $left : 0;
}

// ---------------------------------------------------------------------------
//  The second factor
//
//  A password can be shoulder-surfed on a shop floor, phished, or reused from
//  a site that has already been broken into. A six-digit code that changes
//  every thirty seconds is the one defence that survives all three.
//
//  This is plain TOTP (RFC 6238) — the same thing Google Authenticator,
//  Microsoft Authenticator and Authy speak. It is written out here rather than
//  pulled from a library because this app installs by copying files to a
//  shared host: there is no package manager on the far end, and a dependency
//  that cannot be updated is worse than sixty lines that can be read.
// ---------------------------------------------------------------------------
const B32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
function base32_encode_bytes($bytes) {
    $out = ''; $buf = 0; $bits = 0;
    for ($i = 0; $i < strlen($bytes); $i++) {
        $buf = ($buf << 8) | ord($bytes[$i]); $bits += 8;
        while ($bits >= 5) { $out .= B32_ALPHABET[($buf >> ($bits - 5)) & 31]; $bits -= 5; }
    }
    if ($bits > 0) $out .= B32_ALPHABET[($buf << (5 - $bits)) & 31];
    return $out;
}
function base32_decode_str($s) {
    $s = strtoupper(preg_replace('/[^A-Z2-7]/i', '', (string)$s));
    $out = ''; $buf = 0; $bits = 0;
    for ($i = 0; $i < strlen($s); $i++) {
        $v = strpos(B32_ALPHABET, $s[$i]); if ($v === false) continue;
        $buf = ($buf << 5) | $v; $bits += 5;
        if ($bits >= 8) { $out .= chr(($buf >> ($bits - 8)) & 255); $bits -= 8; }
    }
    return $out;
}
function totp_secret_new() { return base32_encode_bytes(random_bytes(20)); }

// The code an authenticator app would be showing at a given moment.
function totp_code($secretB32, $at = null, $step = 30, $digits = 6) {
    $key = base32_decode_str($secretB32);
    if ($key === '') return '';
    $counter = intdiv($at === null ? time() : $at, $step);
    $bin = pack('N*', 0, $counter);                    // 64-bit big-endian counter
    $hash = hash_hmac('sha1', $bin, $key, true);
    $off  = ord($hash[19]) & 0x0f;
    $num  = ((ord($hash[$off]) & 0x7f) << 24) | (ord($hash[$off+1]) << 16)
          | (ord($hash[$off+2]) << 8) | ord($hash[$off+3]);
    return str_pad((string)($num % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}
// A window of one step either side, because phone clocks drift and people type
// a code as it is about to roll over.
function totp_verify($secretB32, $code, $window = 1) {
    $code = preg_replace('/\D/', '', (string)$code);
    if (strlen($code) !== 6 || $secretB32 === '') return false;
    $now = time();
    for ($i = -$window; $i <= $window; $i++)
        if (hash_equals(totp_code($secretB32, $now + $i * 30), $code)) return true;
    return false;
}
// What gets typed into the authenticator app, or encoded in a QR if one is used.
function totp_uri($secretB32, $username) {
    $issuer = rawurlencode(app_name() ?: 'Exaact');
    $label  = $issuer . ':' . rawurlencode($username);
    return 'otpauth://totp/' . $label . '?secret=' . $secretB32 . '&issuer=' . $issuer . '&digits=6&period=30';
}

// --- Recovery codes -------------------------------------------------------
// A lost or wiped phone must not lock somebody out of their own quotations.
// Ten one-shot codes, stored only as hashes, printed once.
function recovery_codes_new($n = 10) {
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $raw = strtoupper(bin2hex(random_bytes(5)));            // 10 hex characters
        $out[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
    }
    return $out;
}
function recovery_codes_store($userId, array $codes) {
    $hashes = array_map(function ($c) { return password_hash($c, PASSWORD_DEFAULT); }, $codes);
    db()->prepare("UPDATE users SET recovery_codes=? WHERE id=?")
        ->execute([implode("\n", $hashes), (int)$userId]);
}
// True once, then that code is struck off the list.
function recovery_code_spend($user, $code) {
    $code = strtoupper(trim((string)$code));
    if ($code === '') return false;
    $lines = array_filter(explode("\n", (string)($user['recovery_codes'] ?? '')));
    foreach ($lines as $i => $h) {
        if (password_verify($code, $h)) {
            unset($lines[$i]);
            db()->prepare("UPDATE users SET recovery_codes=? WHERE id=?")
                ->execute([implode("\n", $lines), (int)$user['id']]);
            return true;
        }
    }
    return false;
}
function recovery_codes_left($user) { return count(array_filter(explode("\n", (string)($user['recovery_codes'] ?? '')))); }

// --- Who has to use it ----------------------------------------------------
// Set in Settings. The accounts that can move money, change permissions or
// issue a report in the company's name are the ones worth insisting on.
function twofa_required_roles() {
    $s = trim((string)setting_get('twofa_roles', ''));
    return $s === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $s))));
}
function twofa_required_for($u) {
    if (!$u) return false;
    $roles = twofa_required_roles();
    if (!$roles) return false;
    return in_array((string)($u['role'] ?? ''), $roles, true);
}
function twofa_on($u) { return !empty($u['totp_enabled']) && trim((string)($u['totp_secret'] ?? '')) !== ''; }
// Somebody whose role requires it but who has not set it up yet. They are
// nudged on every screen rather than locked out, because locking a coordinator
// out of the call register on a Monday morning helps nobody.
function twofa_owed($u) { return twofa_required_for($u) && !twofa_on($u); }

// How long the trail is kept. The CERT-In directions put the floor at 180 days
// for logs of this kind, so the setting cannot go below it.
function audit_retain_days() { $n = (int)setting_get('audit_retain_days', 400); return ($n >= 180 && $n <= 3650) ? $n : 400; }

// ---------------------------------------------------------------------------
//  Finishing a sign-in
//
//  One place, so the session id, the failure counters, the token and the
//  "last seen here" record can never be updated in one path and forgotten in
//  another.
// ---------------------------------------------------------------------------
function complete_login($u) {
    session_regenerate_id(true);          // an id planted beforehand cannot become an authenticated one
    unset($_SESSION['csrf'], $_SESSION['pending_uid'], $_SESSION['pending_at']);
    login_clear($u['username']);
    login_clear(login_ip_key());
    $_SESSION['uid'] = $u['id'];
    $_SESSION['started_at'] = time();
    session_touch();
    try { db()->prepare("UPDATE users SET last_login_at=?, last_login_ip=? WHERE id=?")
              ->execute([date('c'), client_ip(), $u['id']]); } catch (Throwable $e) {}
    if (function_exists('idems_log')) idems_log('user', $u['id'], 'LOGIN', ['field'=>$u['username']]);
}
// True when this person must set a new password before doing anything else.
function must_change_password($u) {
    return !empty($u['must_change_pwd']) || pwd_expired($u);
}

// ---------------------------------------------------------------------------
//  Two-step sign-in: the screen where somebody switches it on
// ---------------------------------------------------------------------------
function ops_two_factor($method) {
    $pdo = db();
    $u = ops_one("SELECT * FROM users WHERE id=?", [current_user()['id']]);
    $fresh = null;                      // codes to show once, never stored in the clear

    if ($method === 'POST') {
        $do = $_POST['_do'] ?? '';

        if ($do === 'start') {
            // A new secret each time somebody begins, so an abandoned attempt
            // leaves nothing usable behind.
            $_SESSION['totp_pending'] = totp_secret_new();
            redirect('/two-factor');
        }

        if ($do === 'confirm') {
            $secret = (string)($_SESSION['totp_pending'] ?? '');
            if ($secret === '') { flash('Start again — that setup had expired.', 'error'); redirect('/two-factor'); }
            if (!totp_verify($secret, $_POST['code'] ?? '')) {
                flash('That code did not match. The phone clock may be out — check it and try the code showing now.', 'error');
                redirect('/two-factor');
            }
            $codes = recovery_codes_new();
            $pdo->prepare("UPDATE users SET totp_secret=?, totp_enabled=1, totp_confirmed_at=? WHERE id=?")
                ->execute([$secret, date('c'), $u['id']]);
            recovery_codes_store($u['id'], $codes);
            unset($_SESSION['totp_pending']);
            idems_log('user', $u['id'], 'TWOFA_ON', ['field'=>$u['username']]);
            $_SESSION['recovery_show'] = $codes;   // shown once on the next screen
            flash('Two-step sign-in is on. Save the recovery codes below — they are shown only this once.');
            redirect('/two-factor');
        }

        if ($do === 'regen') {
            if (!twofa_on($u)) { flash('Two-step sign-in is not on.', 'error'); redirect('/two-factor'); }
            $codes = recovery_codes_new();
            recovery_codes_store($u['id'], $codes);
            $_SESSION['recovery_show'] = $codes;
            flash('A new set of recovery codes has been issued. The old ones no longer work.');
            redirect('/two-factor');
        }

        if ($do === 'off') {
            // Switching it off is a change worth proving the password for.
            if (!password_verify($_POST['password'] ?? '', $u['password_hash'])) {
                flash('That password is not right, so nothing was changed.', 'error'); redirect('/two-factor');
            }
            if (twofa_required_for($u)) {
                flash('Your role requires two-step sign-in, so it cannot be switched off. Ask a Master Admin if this is wrong.', 'error');
                redirect('/two-factor');
            }
            $pdo->prepare("UPDATE users SET totp_enabled=0, totp_secret='', recovery_codes='' WHERE id=?")->execute([$u['id']]);
            idems_log('user', $u['id'], 'TWOFA_OFF', ['field'=>$u['username']]);
            flash('Two-step sign-in is off.', 'warning');
            redirect('/two-factor');
        }
        redirect('/two-factor');
    }

    if (!empty($_SESSION['recovery_show'])) { $fresh = $_SESSION['recovery_show']; unset($_SESSION['recovery_show']); }
    $pending = (string)($_SESSION['totp_pending'] ?? '');
    view('ops/two_factor', [
        'u'        => $u,
        'on'       => twofa_on($u),
        'required' => twofa_required_for($u),
        'pending'  => $pending,
        'uri'      => $pending ? totp_uri($pending, $u['username']) : '',
        'fresh'    => $fresh,
        'left'     => recovery_codes_left($u),
    ]);
}

// ---------------------------------------------------------------------------
//  What an administrator can do about somebody else's account
//
//  Both of these are ways back in for a person who is locked out, so both are
//  written to the audit trail with the name of whoever did it. Neither reveals
//  or sets a password.
// ---------------------------------------------------------------------------
function ops_user_unlock($method) {
    ops_require(can('users.manage.branch') || can('users.manage.global'), 'You cannot manage users.');
    if ($method !== 'POST') redirect('/users');
    $id = (int)($_POST['id'] ?? 0);
    $u = ops_one("SELECT * FROM users WHERE id=?", [$id]);
    if (!$u) { flash('That user no longer exists.', 'error'); redirect('/users'); }
    login_clear($u['username']);
    idems_log('user', $u['id'], 'ACCOUNT_UNLOCKED', ['field'=>$u['username']]);
    flash($u['username'] . ' can sign in again.');
    redirect('/users');
}
function ops_user_twofa_reset($method) {
    ops_require(can('users.manage.global') || is_master(), 'Only a Master Admin can reset two-step sign-in.');
    if ($method !== 'POST') redirect('/users');
    $id = (int)($_POST['id'] ?? 0);
    $u = ops_one("SELECT * FROM users WHERE id=?", [$id]);
    if (!$u) { flash('That user no longer exists.', 'error'); redirect('/users'); }
    db()->prepare("UPDATE users SET totp_enabled=0, totp_secret='', recovery_codes='' WHERE id=?")->execute([$u['id']]);
    idems_log('user', $u['id'], 'TWOFA_RESET', ['field'=>$u['username'], 'reason'=>'reset by ' . user_name(current_user())]);
    flash('Two-step sign-in has been cleared for ' . $u['username'] . '. They will be asked to set it up again at their next sign-in if their role requires it.', 'warning');
    redirect('/users');
}

// Locked accounts, for the users register. Read only — no side effects.
function accounts_locked_now() {
    $out = [];
    foreach (ops_all("SELECT id, username FROM users WHERE is_active=1") as $u) {
        $mins = login_locked_for($u['username']);
        if ($mins > 0) { $u['minutes'] = $mins; $out[] = $u; }
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  Checking a file before any handler sees it
//
//  There are thirteen places in this app where somebody can attach a file —
//  evidence photos, mill certificates, quotation PDFs, voucher supports, CVs,
//  letterheads, signatures, report templates, the staff import sheet. Guarding
//  each one separately means the fourteenth is unguarded, so this runs once,
//  over everything in the request, before routing.
//
//  Files are never written to disk here — they are held in the database — so an
//  uploaded .php cannot be executed. That is the strong defence and it was
//  already in place. This adds the second one: a file whose contents disagree
//  with what it claims to be, or which is script whatever it claims, is turned
//  away at the door rather than stored and served later.
// ---------------------------------------------------------------------------
function upload_max_mb() { $n = (int)setting_get('upload_max_mb', 12); return ($n >= 1 && $n <= 64) ? $n : 12; }

// Names that mean "run me" on almost any web server, whatever is inside.
const UPLOAD_BANNED_EXT = ['php','php3','php4','php5','php7','phps','phtml','phar','cgi','pl','py',
                           'sh','bash','exe','com','bat','cmd','scr','msi','dll','jar','htaccess','htpasswd'];

// What the first few bytes actually say this is.
function file_real_kind($bytes) {
    if (strlen($bytes) < 4) return 'unknown';
    if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0)              return 'image/jpeg';
    if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0)         return 'image/png';
    if (strncmp($bytes, 'GIF87a', 6) === 0
        || strncmp($bytes, 'GIF89a', 6) === 0)                 return 'image/gif';
    if (strncmp($bytes, 'RIFF', 4) === 0
        && substr($bytes, 8, 4) === 'WEBP')                    return 'image/webp';
    if (strncmp($bytes, 'BM', 2) === 0)                        return 'image/bmp';
    if (strncmp($bytes, '%PDF', 4) === 0)                      return 'application/pdf';
    if (strncmp($bytes, "PK\x03\x04", 4) === 0)                return 'zip';   // docx, xlsx, odt
    if (strncmp($bytes, "\xD0\xCF\x11\xE0", 4) === 0)          return 'ole';   // old .doc, .xls
    return 'unknown';
}

// '' when the file may be stored, otherwise the reason it may not.
function upload_reject_reason($bytes, $name, $declaredMime) {
    $name = (string)$name;
    $len  = strlen($bytes);
    if ($len === 0) return '';                       // an empty slot, not an upload
    if ($len > upload_max_mb() * 1024 * 1024)
        return '"' . $name . '" is larger than the ' . upload_max_mb() . ' MB limit.';

    $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, UPLOAD_BANNED_EXT, true))
        return '"' . $name . '" is a program file. Attach a document or a picture instead.';
    // A second extension hidden before the visible one — report.php.jpg.
    foreach (explode('.', strtolower($name)) as $part)
        if (in_array($part, UPLOAD_BANNED_EXT, true))
            return '"' . $name . '" has a program-file extension inside its name. Rename it and try again.';

    $head = substr($bytes, 0, 1024);
    if (stripos($head, '<' . '?php') !== false || strncmp($head, '#!', 2) === 0)
        return '"' . $name . '" contains program code, so it was not stored.';

    $real = file_real_kind($bytes);
    $decl = strtolower(trim((string)$declaredMime));

    // Anything calling itself a picture must actually be one. This is what
    // stops a page of script arriving with a .jpg name and an image/jpeg label.
    if (strpos($decl, 'image/') === 0 && strpos($real, 'image/') !== 0)
        return '"' . $name . '" says it is a picture but its contents are not. It may be damaged, or renamed from something else.';

    // A drawing (SVG) is not a picture as far as a browser is concerned — it is
    // a document that can carry script. They are never displayed here, so there
    // is no reason to hold one.
    if ($decl === 'image/svg+xml' || $ext === 'svg')
        return 'SVG drawings are not accepted. Save it as a PNG or a JPG and attach that.';

    // Markup, from anywhere, under any name.
    if (in_array($ext, ['html','htm','xhtml','shtml','svgz'], true)
        || stripos($head, '<!doctype html') !== false
        || (stripos($head, '<html') !== false && stripos($head, '<script') !== false))
        return '"' . $name . '" is a web page. Print it to PDF and attach that instead.';

    return '';
}

// Walk everything in the request, however deeply the form nested it, and hand
// back the first reason to refuse — or '' when they are all fine.
function uploads_reject_reason() {
    if (empty($_FILES)) return '';
    $flat = function ($v) use (&$flat) { return is_array($v) ? array_merge(...array_map($flat, array_values($v))) : [$v]; };
    foreach ($_FILES as $f) {
        if (!isset($f['tmp_name'])) continue;
        $tmps  = $flat($f['tmp_name']);
        $names = $flat($f['name'] ?? '');
        $types = $flat($f['type'] ?? '');
        $errs  = $flat($f['error'] ?? 0);
        foreach ($tmps as $i => $tmp) {
            if ((int)($errs[$i] ?? 0) !== 0 || $tmp === '' || !is_uploaded_file($tmp)) continue;
            // Only the head is read for the check; the size is taken from disk,
            // so a 200 MB file is never pulled into memory to be refused.
            $size = (int)@filesize($tmp);
            if ($size > upload_max_mb() * 1024 * 1024)
                return '"' . ($names[$i] ?? 'that file') . '" is larger than the ' . upload_max_mb() . ' MB limit.';
            $head = (string)@file_get_contents($tmp, false, null, 0, 4096);
            $why  = upload_reject_reason($head, $names[$i] ?? 'file', $types[$i] ?? '');
            if ($why !== '') return $why;
        }
    }
    return '';
}

// ---------------------------------------------------------------------------
//  Where new columns are added
//
//  Called from the boot migration. Every column here is checked for on the
//  live database on the way in, so an upload-and-go deployment gains them
//  without anybody running anything.
// ---------------------------------------------------------------------------
function security_migrate() {
    static $done = false; if ($done) return; $done = true;
    ensure_column('users', 'pwd_changed_at',  "VARCHAR(30) DEFAULT ''");
    ensure_column('users', 'must_change_pwd', "INT DEFAULT 0");
    ensure_column('users', 'totp_secret',     "VARCHAR(64) DEFAULT ''");
    ensure_column('users', 'totp_enabled',    "INT DEFAULT 0");
    ensure_column('users', 'totp_confirmed_at', "VARCHAR(30) DEFAULT ''");
    ensure_column('users', 'recovery_codes',  "TEXT");
    ensure_column('users', 'last_login_at',   "VARCHAR(30) DEFAULT ''");
    ensure_column('users', 'last_login_ip',   "VARCHAR(60) DEFAULT ''");
}
