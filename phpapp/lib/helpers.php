<?php
// Shared helpers: escaping, auth, GST logic, choice lists, flash messages.

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect($path) { header("Location: $path"); exit; }

// Back to the screen they were on, when a submission is turned away.
//
// The browser sends the referrer as a full address — http://host/page?id=7 —
// not as a path, so a test for a leading "/" never matches it and the id is
// dropped, landing the person on an empty register instead of the record they
// were working on. Only our own host is honoured, so the header cannot be used
// to bounce somebody off the site.
function redirect_back($fallbackRoute = '') {
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $to  = '/' . ltrim($fallbackRoute, '/');
    if ($ref !== '') {
        $p = parse_url($ref);
        // parse_url hands back the host without the port, while HTTP_HOST keeps
        // it — "127.0.0.1" against "127.0.0.1:8801" — so the port is stripped
        // from both before they are compared. Getting this wrong silently sends
        // everybody to the fallback and loses the record they were on.
        $here = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
        $sameHost = empty($p['host']) || strcasecmp($p['host'], $here) === 0;
        if ($sameHost && !empty($p['path']))
            $to = $p['path'] . (isset($p['query']) && $p['query'] !== '' ? '?' . $p['query'] : '');
    }
    redirect($to);
}

// --- Flash messages ---
function flash($text, $tag = 'success') {
    $_SESSION['flash'][] = ['text' => $text, 'tag' => $tag];
}
function take_flash() {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// --- Auth ---
function current_user() {
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $q = db()->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $q->execute([$_SESSION['uid']]);
        $u = $q->fetch() ?: null;
    }
    return $u;
}
function require_login() { if (!current_user()) redirect('login'); }
function user_name($u) {
    // Nobody is signed in when this runs from cron or a month-end job, so a
    // null must give a usable word rather than a warning in the middle of a
    // calculation nobody is watching.
    if (!is_array($u)) return 'System';
    $n = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    return $n !== '' ? $n : ($u['username'] ?? 'System');
}

// --- GST helpers ---
const GST_STATES = [
    '01'=>'Jammu & Kashmir','02'=>'Himachal Pradesh','03'=>'Punjab','04'=>'Chandigarh',
    '05'=>'Uttarakhand','06'=>'Haryana','07'=>'Delhi','08'=>'Rajasthan','09'=>'Uttar Pradesh',
    '10'=>'Bihar','11'=>'Sikkim','12'=>'Arunachal Pradesh','13'=>'Nagaland','14'=>'Manipur',
    '15'=>'Mizoram','16'=>'Tripura','17'=>'Meghalaya','18'=>'Assam','19'=>'West Bengal',
    '20'=>'Jharkhand','21'=>'Odisha','22'=>'Chhattisgarh','23'=>'Madhya Pradesh','24'=>'Gujarat',
    '25'=>'Daman & Diu','26'=>'Dadra & Nagar Haveli','27'=>'Maharashtra','28'=>'Andhra Pradesh (Old)',
    '29'=>'Karnataka','30'=>'Goa','31'=>'Lakshadweep','32'=>'Kerala','33'=>'Tamil Nadu',
    '34'=>'Puducherry','35'=>'Andaman & Nicobar','36'=>'Telangana','37'=>'Andhra Pradesh','38'=>'Ladakh',
];
function clean_gstin($g) { return strtoupper(preg_replace('/\s+/', '', trim((string)$g))); }
function pan_from_gstin($g) {
    $g = clean_gstin($g);
    if (strlen($g) >= 12) {
        $pan = substr($g, 2, 10);
        if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) return $pan;
    }
    return '';
}
function state_from_gstin($g) { $g = clean_gstin($g); return lk_options_or('gst_state', GST_STATES)[substr($g, 0, 2)] ?? ''; }
function is_valid_gstin($g) { return (bool)preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', clean_gstin($g)); }
function normalize_name($name) {
    $n = strtolower((string)$name);
    $n = preg_replace('/^m\/?s\.?\s+/', '', $n);
    $n = preg_replace('/[^a-z0-9 ]/', ' ', $n);
    $n = preg_replace('/\b(private|pvt|limited|ltd|llp|company|co)\b/', ' ', $n);
    return trim(preg_replace('/\s+/', ' ', $n));
}
function short_token($name) {
    $first = explode(' ', normalize_name($name))[0] ?? 'PARTNER';
    $t = preg_replace('/[^A-Z0-9]/', '', strtoupper($first));
    return substr($t, 0, 8) ?: 'PARTNER';
}

// --- Choice lists (labels) ---
const CLIENT_TYPES = ['OWNER'=>'Owner / End Client','EPC'=>'EPC Contractor','PMC'=>'PMC / Project Management','CONSULTANT'=>'Consultant','MANUFACTURER'=>'Manufacturer / Supplier','TRADER'=>'Trader / Distributor','SUBVENDOR'=>'Sub-vendor','OTHER'=>'Other'];
const INDUSTRIES = ['POWER'=>'Power / Transmission','RENEWABLE'=>'Renewable (Solar / Wind)','OIL_GAS'=>'Oil & Gas','WATER'=>'Water & Infrastructure','MINING'=>'Mining & Metals','STEEL'=>'Steel','CEMENT'=>'Cement','MANUFACTURING'=>'Manufacturing','INFRA'=>'Infrastructure / Construction','RAIL'=>'Railways','CHEMICAL'=>'Chemical / Fertiliser','OTHER'=>'Other'];
const OWNERSHIP = ['PVT_LTD'=>'Private Limited','PUB_LTD'=>'Public Limited','LLP'=>'LLP','PARTNERSHIP'=>'Partnership','PROPRIETOR'=>'Proprietorship','PSU'=>'Government / PSU','TRUST'=>'Trust / Society','MNC'=>'MNC / Foreign','OTHER'=>'Other'];
const STATUSES = ['ACTIVE'=>'Active','INACTIVE'=>'Inactive','ON_HOLD'=>'On hold','BLACKLISTED'=>'Blacklisted','PROSPECT'=>'Prospect'];
const ADDRESS_TYPES = ['REGISTERED'=>'Registered Office','CORPORATE'=>'Corporate Office','BRANCH'=>'Branch Office','PURCHASE'=>'Purchase Office','BILLING'=>'Billing Address','PLANT'=>'Plant','FACTORY'=>'Factory','WAREHOUSE'=>'Warehouse','PROJECT_SITE'=>'Project Site','SITE_OFFICE'=>'Site Office'];
const REG_TYPES = ['GSTIN'=>'GSTIN','PAN'=>'PAN','TAN'=>'TAN','CIN'=>'CIN','MSME'=>'MSME / Udyam','ISO'=>'ISO Certificate','PQ'=>'Pre-Qualification','OTHER'=>'Other'];
const PO_TYPES = ['REGULAR'=>'Regular (fixed value)','OPEN'=>'Open order (no PO / ARC)','ARC'=>'ARC / Rate contract'];
// A PO line is charged in the same units as everything else — see CHARGE_UNITS.
const PO_ITEM_TYPES = ['MANDAY'=>'Man-day','MANMONTH'=>'Man-month','AUDIT_DAY'=>'Audit day','VISIT'=>'Per visit','LOT'=>'Per lot / lump sum','DOC'=>'Per document','OTHER'=>'Other'];
const REL_TYPES = ['SUBSIDIARY'=>'Subsidiary of','JV'=>'Joint Venture with','CONSORTIUM'=>'Consortium with','EPC_FOR'=>'EPC Contractor for','CONSULTANT_FOR'=>'Consultant for','SUPPLIER_TO'=>'Supplier to','OTHER'=>'Other'];

function partner_name($p) { return $p['display_name'] !== '' ? $p['display_name'] : $p['legal_name']; }
function roles_label($p) {
    $r = [];
    if ($p['is_client']) $r[] = 'Client';
    if ($p['is_vendor']) $r[] = 'Vendor';
    if ($p['is_subcontractor']) $r[] = 'Sub-contractor';
    return $r ? implode(' / ', $r) : '—';
}

// ---------------------------------------------------------------------------
//  One submission, one record
//
//  A form submitted once was being recorded twice. The POST is replayed for
//  reasons that have nothing to do with the person: a double-click, a browser
//  retry when a response is slow, the back button and a refresh, and — the one
//  that caused this — the offline queue re-sending an entry whose reply never
//  arrived even though the server had already saved it. An inspector's expenses
//  appeared as two identical lines.
//
//  So each form carries a one-shot ticket. The first POST spends it; any replay
//  presents a ticket that is already spent and is turned away before the handler
//  runs. A form with no ticket at all — an older page, or a browser with no
//  JavaScript — is let through exactly as before, so nothing that worked stops
//  working.
// ---------------------------------------------------------------------------
function form_tokens_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS form_tokens (
            token VARCHAR(64) PRIMARY KEY, used_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) {}
}
// True the first time a ticket is presented, false for every replay of it.
function form_token_spend($token) {
    $token = trim((string)$token);
    if ($token === '' || strlen($token) > 64) return true;   // no ticket: behave as before
    try {
        db()->prepare("INSERT INTO form_tokens (token, used_at) VALUES (?, ?)")
            ->execute([$token, date('c')]);
    } catch (Throwable $e) {
        return false;   // primary-key clash: this exact submission has been through already
    }
    // Keep the table from growing without bound; a ticket older than a day
    // cannot be replayed by anything we care about.
    try {
        if (random_int(1, 200) === 1)
            db()->prepare("DELETE FROM form_tokens WHERE used_at < ?")
                ->execute([date('c', time() - 86400)]);
    } catch (Throwable $e) {}
    return true;
}

// ---------------------------------------------------------------------------
//  Cross-site request forgery
//
//  Every screen in this app changes something real: approving a quotation,
//  allotting a contract number, creating a user. Without a token, any page a
//  signed-in person happens to visit can make their own browser do those things
//  — the browser sends the session cookie whatever site asked. Nothing on the
//  screen would look wrong afterwards, because as far as the app is concerned
//  the right person did it.
//
//  The one-shot ticket already on every form is NOT this. That is minted in the
//  browser to stop a submission being recorded twice; anyone can mint one. This
//  token is issued by the server, kept in the session and never leaves it.
//
//  It is stamped into every POST form as the page is written out, rather than
//  by hand in 141 places, so a form added tomorrow is covered without anybody
//  remembering to do anything.
// ---------------------------------------------------------------------------
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
// ---------------------------------------------------------------------------
//  Keeping what somebody typed when a submit is refused
//
//  Reported from a real test: a lead form was filled in, the submit was refused
//  because the session had expired, and the screen came back EMPTY. Everything
//  typed was gone. The message even said "nothing was changed", which was true
//  about the database and a lie about the person's afternoon.
//
//  Losing somebody's typing is the rudest thing software does. So a refused POST
//  is stashed for exactly one page load and the form fills itself back in.
//  One load only, so a later visit to the same form is genuinely blank.
// ---------------------------------------------------------------------------
function form_stash($route, array $post) {
    if (session_status() === PHP_SESSION_NONE) return;
    unset($post['_csrf'], $post['_ticket'], $post['password'], $post['password2'], $post['new_password']);
    $_SESSION['form_retry'] = ['route' => trim((string)$route, '/'), 'at' => time(), 'data' => $post];
}

// Loaded once per page and cleared from the session immediately, so a refresh
// does not resurrect old typing. Both readers below go through this.
function form_retry_data() {
    static $mine = null;
    if ($mine !== null) return $mine;
    $mine = [];
    $r = $_SESSION['form_retry'] ?? null;
    if (is_array($r) && (time() - (int)($r['at'] ?? 0)) < 900) {
        $here = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        if ($here === (string)($r['route'] ?? '')) $mine = (array)($r['data'] ?? []);
    }
    unset($_SESSION['form_retry']);
    return $mine;
}

// What was typed, if this page is the one we were just bounced from.
function form_old($name, $default = '') {
    $d = form_retry_data();
    if (!array_key_exists($name, $d)) return $default;
    return is_array($d[$name]) ? $d[$name] : (string)$d[$name];
}

// True when this page is a retry, so the screen can say so rather than leaving
// somebody wondering why the boxes are already full.
function form_is_retry() { return form_retry_data() !== []; }

function csrf_ok($token) {
    $have = $_SESSION['csrf'] ?? '';
    return $have !== '' && is_string($token) && hash_equals($have, $token);
}
// Add the hidden field to every POST form in a rendered page.
function csrf_stamp_forms($html) {
    $field = '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    $out = preg_replace_callback(
        '/<form\b[^>]*>/i',
        function ($m) use ($field) {
            // GET forms (search, filters) change nothing and need no token.
            if (!preg_match('/method\s*=\s*["\']?post["\']?/i', $m[0])) return $m[0];
            return $m[0] . $field;
        },
        $html
    );
    // On a very large page the matcher can give up and return null. A page that
    // saves nothing is bad; a blank page is worse, so the original is sent.
    return $out === null ? $html : $out;
}

// ---------------------------------------------------------------------------
//  Slowing down password guessing
//
//  Failed sign-ins were recorded but never limited, so a password could be
//  guessed as fast as the server would answer. Five wrong tries and that
//  username rests for fifteen minutes. Counted per username rather than per
//  browser, because the browser is the attacker's to change.
// ---------------------------------------------------------------------------
const LOGIN_MAX_TRIES = 5;
const LOGIN_LOCK_MIN  = 15;
function login_attempts_migrate() {
    static $done = false; if ($done) return; $done = true;
    try { db()->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        username VARCHAR(150) PRIMARY KEY, tries INT DEFAULT 0, last_at VARCHAR(30) DEFAULT '')"); }
    catch (Throwable $e) {}
}
function login_row($username) {
    login_attempts_migrate();
    try { return ops_one("SELECT * FROM login_attempts WHERE username=?", [$username]); }
    catch (Throwable $e) { return null; }
}
// Minutes still to wait, or 0 when the account may try again.
function login_locked_for($username) {
    $r = login_row($username);
    if (!$r || (int)$r['tries'] < LOGIN_MAX_TRIES) return 0;
    $since = (time() - strtotime((string)$r['last_at'])) / 60;
    $left  = (int)ceil(LOGIN_LOCK_MIN - $since);
    return $left > 0 ? $left : 0;
}
function login_allowed($username) { return login_locked_for($username) === 0; }
// Record a failure; returns the minutes the account is now resting for, if any.
function login_fail($username) {
    $username = substr(trim($username), 0, 150);
    if ($username === '') return 0;
    login_attempts_migrate();
    $r = login_row($username);
    try {
        if (!$r) db()->prepare("INSERT INTO login_attempts (username,tries,last_at) VALUES (?,1,?)")
                     ->execute([$username, date('c')]);
        else {
            // A lock that has already run its course starts the count again.
            $tries = login_locked_for($username) === 0 && (int)$r['tries'] >= LOGIN_MAX_TRIES ? 1 : (int)$r['tries'] + 1;
            db()->prepare("UPDATE login_attempts SET tries=?, last_at=? WHERE username=?")
                ->execute([$tries, date('c'), $username]);
        }
    } catch (Throwable $e) {}
    return login_locked_for($username);
}
function login_clear($username) {
    login_attempts_migrate();
    try { db()->prepare("DELETE FROM login_attempts WHERE username=?")->execute([$username]); }
    catch (Throwable $e) {}
}

// ---------------------------------------------------------------------------
//  Serving a file somebody uploaded
//
//  Uploads never touch the disk — they are held in the database — so an uploaded
//  .php can never be executed. But they were being served back with whatever
//  content type the uploader's browser claimed, and shown inline. Upload an HTML
//  page or an SVG and it would render inside this site, able to act as whoever
//  opened it. So: a short list of types that are safe to display, everything
//  else downloaded rather than rendered, and never the uploader's word for it.
// ---------------------------------------------------------------------------
const SAFE_INLINE_MIME = [
    'image/jpeg' => 1, 'image/png' => 1, 'image/gif' => 1, 'image/webp' => 1,
    'image/bmp'  => 1, 'application/pdf' => 1, 'text/plain' => 1,
];
function send_uploaded_file($bytes, $name, $mime) {
    $mime = strtolower(trim((string)$mime));
    $safe = isset(SAFE_INLINE_MIME[$mime]);
    $name = preg_replace('/[^A-Za-z0-9._\- ]/', '_', (string)$name) ?: 'file';
    // nosniff matters most here: without it a browser may decide a "text/plain"
    // file full of markup is really HTML and run it.
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . ($safe ? $mime : 'application/octet-stream'));
    header('Content-Disposition: ' . ($safe ? 'inline' : 'attachment') . '; filename="' . $name . '"');
    header('Content-Security-Policy: default-src \'none\'; sandbox');
    echo $bytes;
}
