<?php
// ============================================================================
//  CONNECT — Freelancer self-service pool  (slice A1/A2, additive)
//
//  The SHARED pool the owner described: an individual technical professional
//  signs up for THEMSELVES and uploads a rich profile ("I am ready to work").
//  This is a DISTINCT entity from an organisation's private staff (`inspectors`)
//  — an org's own employees are never in this pool; only people who list
//  themselves are. Its own table (cx_professionals), its own login (a fourth
//  audience beside staff / client portal / vendor portal), its own /pro address.
//
//  A professional owns their profile + Passport, browses open requirements and
//  applies. Any organisation with marketplace access can discover the pool
//  (talent search, A3) — but never another org's private employees.
// ============================================================================

/** M4 work-type vocabulary (blueprint M4 — how they want to work). */
function cx_pro_work_types() {
    return [
        'per_visit'       => 'Per-visit inspection',
        'day_rate'        => 'Day-rate',
        'manday'          => 'Man-day / man-month deputation',
        'long_deployment' => 'Long deployment',
        'shutdown'        => 'Shutdown / turnaround crew',
        'remote_review'   => 'Remote document review',
    ];
}

function connect_pro_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_professionals (
        id $pk, email VARCHAR(200) DEFAULT '', name VARCHAR(150) DEFAULT '', mobile VARCHAR(40) DEFAULT '',
        password_hash VARCHAR(255) DEFAULT '', is_active INT DEFAULT 1, verification_tier VARCHAR(20) DEFAULT 'registered',
        headline VARCHAR(160) DEFAULT '', disciplines VARCHAR(400) DEFAULT '', skills VARCHAR(600) DEFAULT '',
        work_types VARCHAR(240) DEFAULT '', base_city VARCHAR(120) DEFAULT '', preferred_locations VARCHAR(400) DEFAULT '',
        pan_india INT DEFAULT 0, overseas INT DEFAULT 0, travel_radius_km INT DEFAULT 0,
        availability VARCHAR(20) DEFAULT 'AVAILABLE', available_from VARCHAR(20) DEFAULT '', notice_days INT DEFAULT 0,
        day_rate_min REAL DEFAULT 0, day_rate_max REAL DEFAULT 0, per_visit_rate REAL DEFAULT 0,
        languages VARCHAR(240) DEFAULT '', passport_token VARCHAR(40) DEFAULT '',
        created_at VARCHAR(30) DEFAULT '', last_login_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE UNIQUE INDEX ux_cx_pro_email ON cx_professionals (email)"); } catch (Throwable $e) {}
    // A professional's own files — profile photo, CV, certificates. Lightweight
    // (base64), reusing the shared upload guards; distinct from the inspection
    // ID vault (which forces doc-kind/number/expiry that a CV does not have).
    db()->exec("CREATE TABLE IF NOT EXISTS cx_pro_files (
        id $pk, pro_id INT DEFAULT 0, kind VARCHAR(16) DEFAULT 'OTHER',
        file_name VARCHAR(255) DEFAULT '', mime VARCHAR(100) DEFAULT '', size INT DEFAULT 0,
        file_data LONGTEXT, created_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE INDEX ix_cx_pro_files ON cx_pro_files (pro_id, kind)"); } catch (Throwable $e) {}
    // The marketplace application table (K2) gains a professional applicant link,
    // so a self-listed professional applies as themselves and dedupes correctly.
    if (function_exists('ensure_column')) {
        ensure_column('cx_applications', 'applicant_professional_id', 'INT DEFAULT 0');
        // Forgot-password: a short-lived reset token (mirrors the client-portal invite pattern).
        ensure_column('cx_professionals', 'reset_token', "VARCHAR(64) DEFAULT ''");
        ensure_column('cx_professionals', 'reset_expires', "VARCHAR(30) DEFAULT ''");
    }
}

/**
 * Forgot-password step 1 — issue a reset token and e-mail the link. Always returns
 * the same way to the caller regardless of whether the e-mail exists (no account
 * enumeration). Returns the reset link ONLY for an existing account (the route
 * decides whether to surface it; the UI never reveals existence to the visitor).
 */
function connect_pro_reset_request($email) {
    connect_pro_migrate();
    $email = strtolower(trim((string)$email));
    if ($email === '') return '';
    $u = ops_one("SELECT * FROM cx_professionals WHERE email=? AND is_active=1", [$email]);
    if (!$u) return '';
    $tok = bin2hex(random_bytes(24));
    db()->prepare("UPDATE cx_professionals SET reset_token=?, reset_expires=? WHERE id=?")
        ->execute([$tok, date('c', time() + 3600), (int)$u['id']]);
    $base = function_exists('connect_passport_base_url') ? connect_passport_base_url() : '';
    $link = $base . '/pro/reset?t=' . $tok;
    if (function_exists('ops_mail')) {
        try {
            ops_mail($email, 'Reset your professional password',
                "Hi " . ($u['name'] ?: 'there') . ",\n\nUse this link to set a new password (valid for 1 hour):\n$link\n\nIf you did not ask for this, you can ignore this e-mail.",
                '', 'pro_reset');
        } catch (Throwable $e) {}
    }
    return $link;
}

/** Is a reset token usable? '' = ok, else a plain-language problem. */
function connect_pro_reset_problem($token) {
    connect_pro_migrate();
    $token = trim((string)$token);
    if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/', $token)) return 'This reset link is invalid.';
    $u = ops_one("SELECT id, reset_expires FROM cx_professionals WHERE reset_token=? AND is_active=1", [$token]);
    if (!$u) return 'This reset link is invalid or already used.';
    if ((string)$u['reset_expires'] === '' || strtotime((string)$u['reset_expires']) < time()) return 'This reset link has expired — request a new one.';
    return '';
}

/** Forgot-password step 2 — set the new password and burn the token. '' = ok. */
function connect_pro_reset_complete($token, $pw, $pw2) {
    $problem = connect_pro_reset_problem($token);
    if ($problem !== '') return $problem;
    if (strlen((string)$pw) < 8) return 'Choose a password of at least 8 characters.';
    if ((string)$pw !== (string)$pw2) return 'The two passwords do not match.';
    $u = ops_one("SELECT id FROM cx_professionals WHERE reset_token=?", [trim((string)$token)]);
    if (!$u) return 'This reset link is invalid or already used.';
    db()->prepare("UPDATE cx_professionals SET password_hash=?, reset_token='', reset_expires='' WHERE id=?")
        ->execute([password_hash((string)$pw, PASSWORD_DEFAULT), (int)$u['id']]);
    return '';
}

/** The pro-portal is part of Connect: it follows the same on/off switch. */
function connect_pro_portal_on() {
    return (function_exists('connect_enabled') ? connect_enabled() : true);
}

/** The logged-in professional, or null. */
function connect_pro_user() {
    if (empty($_SESSION['cxpid'])) return null;
    static $u = null, $for = 0;
    if ($u === null || $for !== (int)$_SESSION['cxpid']) {
        $for = (int)$_SESSION['cxpid'];
        try { $u = ops_one("SELECT * FROM cx_professionals WHERE id=? AND is_active=1", [$for]); }
        catch (Throwable $e) { $u = null; }
    }
    return $u ?: null;
}
function connect_pro_id() { $u = connect_pro_user(); return $u ? (int)$u['id'] : 0; }

function connect_pro_require() {
    if (!connect_pro_user()) redirect('/pro/login');
}

/** Self-registration. Returns '' on success (and logs in), else an error. */
function connect_pro_register(array $in) {
    connect_pro_migrate();
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $name  = trim((string)($in['name'] ?? ''));
    $pw    = (string)($in['password'] ?? '');
    if ($name === '') return 'Enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Enter a valid e-mail address.';
    if (strlen($pw) < 8) return 'Choose a password of at least 8 characters.';
    if (ops_val("SELECT id FROM cx_professionals WHERE email=?", [$email])) return 'That e-mail is already registered — sign in instead.';
    $tok = bin2hex(random_bytes(16));
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,password_hash,is_active,verification_tier,passport_token,created_at)
                   VALUES (?,?,?,?,1,'registered',?,?)")
        ->execute([$email, $name, trim((string)($in['mobile'] ?? '')), password_hash($pw, PASSWORD_DEFAULT), $tok, date('c')]);
    $_SESSION['cxpid'] = (int)db()->lastInsertId();
    return '';
}

/** Sign in. Returns '' on success, else an error. */
function connect_pro_login($email, $password) {
    connect_pro_migrate();
    $email = strtolower(trim((string)$email));
    if ($email === '') return 'Enter your e-mail address.';
    $u = ops_one("SELECT * FROM cx_professionals WHERE email=? AND is_active=1", [$email]);
    if (!$u || (string)$u['password_hash'] === '' || !password_verify((string)$password, (string)$u['password_hash']))
        return 'That e-mail address and password do not match.';
    $_SESSION['cxpid'] = (int)$u['id'];
    try { db()->prepare("UPDATE cx_professionals SET last_login_at=? WHERE id=?")->execute([date('c'), (int)$u['id']]); } catch (Throwable $e) {}
    return '';
}

// ---- A professional's files (photo / CV / certificates) ---------------------

function connect_pro_file_kinds() {
    return ['AVATAR' => 'Profile photo', 'CV' => 'CV / résumé', 'CERT' => 'Certificate', 'OTHER' => 'Other document'];
}

/** Store an uploaded file for a professional. Returns [ok, message]. */
function connect_pro_file_add($proId, $kind, $file) {
    connect_pro_migrate();
    $kind = isset(connect_pro_file_kinds()[$kind]) ? $kind : 'OTHER';
    if (!$file || ($file['tmp_name'] ?? '') === '' || !is_uploaded_file($file['tmp_name']))
        return [false, 'Choose a file to upload.'];
    $bytes = (string)@file_get_contents($file['tmp_name']);
    if ($bytes === '') return [false, 'That file looks empty.'];
    if (function_exists('upload_reject_reason')) {
        $why = upload_reject_reason($bytes, (string)($file['name'] ?? ''), (string)($file['type'] ?? ''));
        if ($why !== '') return [false, $why];
    }
    if ($kind === 'AVATAR') { // one photo per person — replace the old one
        try { db()->prepare("DELETE FROM cx_pro_files WHERE pro_id=? AND kind='AVATAR'")->execute([(int)$proId]); } catch (Throwable $e) {}
    }
    db()->prepare("INSERT INTO cx_pro_files (pro_id,kind,file_name,mime,size,file_data,created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([(int)$proId, $kind, substr((string)($file['name'] ?? 'file'), 0, 255),
                   (string)($file['type'] ?? ''), strlen($bytes), base64_encode($bytes), date('c')]);
    return [true, connect_pro_file_kinds()[$kind] . ' uploaded.'];
}

/** A professional's files (metadata only — no bytes). */
function connect_pro_files($proId) {
    connect_pro_migrate();
    try { return ops_all("SELECT id,pro_id,kind,file_name,mime,size,created_at FROM cx_pro_files WHERE pro_id=? ORDER BY id DESC", [(int)$proId]) ?: []; }
    catch (Throwable $e) { return []; }
}

/** The id of a professional's current avatar file, or 0. */
function connect_pro_avatar_id($proId) {
    try { return (int)ops_val("SELECT id FROM cx_pro_files WHERE pro_id=? AND kind='AVATAR' ORDER BY id DESC LIMIT 1", [(int)$proId]); }
    catch (Throwable $e) { return 0; }
}

/** One file row (with bytes), scoped to its owner. */
function connect_pro_file_row($id, $proId) {
    try { return ops_one("SELECT * FROM cx_pro_files WHERE id=? AND pro_id=?", [(int)$id, (int)$proId]) ?: null; }
    catch (Throwable $e) { return null; }
}

function connect_pro_file_delete($id, $proId) {
    db()->prepare("DELETE FROM cx_pro_files WHERE id=? AND pro_id=?")->execute([(int)$id, (int)$proId]);
    return [true, 'Removed.'];
}

/** Save the M4 profile for the logged-in professional. */
function connect_pro_profile_save($id, array $in) {
    connect_pro_migrate();
    $id = (int)$id; if ($id <= 0) return false;
    $wt = array_values(array_intersect(array_keys(cx_pro_work_types()), (array)($in['work_types'] ?? [])));
    $disc = array_values(array_filter(array_map('trim', (array)($in['disciplines'] ?? []))));
    db()->prepare("UPDATE cx_professionals SET name=?, mobile=?, headline=?, disciplines=?, skills=?, work_types=?,
                     base_city=?, preferred_locations=?, pan_india=?, overseas=?, travel_radius_km=?,
                     availability=?, available_from=?, notice_days=?, day_rate_min=?, day_rate_max=?, per_visit_rate=?, languages=?
                   WHERE id=?")
        ->execute([
            trim((string)($in['name'] ?? '')), trim((string)($in['mobile'] ?? '')), trim((string)($in['headline'] ?? '')),
            implode(',', $disc), trim((string)($in['skills'] ?? '')), implode(',', $wt),
            trim((string)($in['base_city'] ?? '')), trim((string)($in['preferred_locations'] ?? '')),
            !empty($in['pan_india']) ? 1 : 0, !empty($in['overseas']) ? 1 : 0, (int)($in['travel_radius_km'] ?? 0),
            (string)($in['availability'] ?? 'AVAILABLE'), (string)($in['available_from'] ?? ''), (int)($in['notice_days'] ?? 0),
            (float)($in['day_rate_min'] ?? 0), (float)($in['day_rate_max'] ?? 0), (float)($in['per_visit_rate'] ?? 0),
            trim((string)($in['languages'] ?? '')), $id,
        ]);
    return true;
}

/** How complete is the profile (drives a nudge on the dashboard). */
function connect_pro_profile_pct($u) {
    if (!is_array($u)) return 0;
    $keys = ['headline','disciplines','skills','work_types','base_city','availability','day_rate_min','languages'];
    $done = 0; foreach ($keys as $k) if (trim((string)($u[$k] ?? '')) !== '' && (string)($u[$k] ?? '') !== '0') $done++;
    return (int)round($done / count($keys) * 100);
}

/**
 * A3 — search the SHARED pool (self-listed professionals only). Filters on the
 * M4 profile: discipline, work type, location, availability, and free text.
 * Read-only; returns professional cards. Never touches an org's private staff.
 */
function connect_pro_search(array $f = [], $limit = 60) {
    connect_pro_migrate();
    $w = ['is_active=1']; $a = [];
    $disc = trim((string)($f['discipline'] ?? ''));
    if ($disc !== '') { $w[] = 'disciplines LIKE ?'; $a[] = '%' . $disc . '%'; }
    $wt = trim((string)($f['work_type'] ?? ''));
    if ($wt !== '') { $w[] = 'work_types LIKE ?'; $a[] = '%' . $wt . '%'; }
    $loc = trim((string)($f['location'] ?? ''));
    if ($loc !== '') { $w[] = '(base_city LIKE ? OR preferred_locations LIKE ? OR pan_india=1)'; $a[] = '%' . $loc . '%'; $a[] = '%' . $loc . '%'; }
    if (!empty($f['available_only'])) { $w[] = "availability='AVAILABLE'"; }
    $q = trim((string)($f['q'] ?? ''));
    if ($q !== '') { $w[] = '(name LIKE ? OR headline LIKE ? OR skills LIKE ?)'; $a[] = '%' . $q . '%'; $a[] = '%' . $q . '%'; $a[] = '%' . $q . '%'; }
    $sql = "SELECT * FROM cx_professionals WHERE " . implode(' AND ', $w)
         . " ORDER BY CASE WHEN availability='AVAILABLE' THEN 0 ELSE 1 END, name LIMIT " . max(1, (int)$limit);
    try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; }
}

/** Count of active professionals in the shared pool. */
function connect_pro_pool_count() {
    connect_pro_migrate();
    try { return (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE is_active=1"); } catch (Throwable $e) { return 0; }
}

/** Staff/org talent-search screen over the shared pool. */
function ops_connect_talent($method) {
    ops_require(function_exists('connect_market_can') && connect_market_can(),
        'The talent pool is available to coordinators, managers and admins.');
    $f = [
        'q'              => (string)($_GET['q'] ?? ''),
        'discipline'     => (string)($_GET['discipline'] ?? ''),
        'work_type'      => (string)($_GET['work_type'] ?? ''),
        'location'       => (string)($_GET['location'] ?? ''),
        'available_only' => !empty($_GET['available_only']),
    ];
    // Invite a professional onto an open requirement (records an application).
    if ($method === 'POST' && ($_POST['action'] ?? '') === 'invite' && function_exists('cx_application_add')) {
        $rid = (int)($_POST['requirement_id'] ?? 0); $pid = (int)($_POST['professional_id'] ?? 0);
        if ($rid && $pid) {
            $nm = (string)ops_val("SELECT name FROM cx_professionals WHERE id=?", [$pid]);
            $newId = cx_application_add($rid, ['applicant_professional_id' => $pid, 'applicant_name' => $nm]);
            flash($newId ? ($nm . ' added to the requirement.') : ($nm . ' has already applied to that requirement.'), $newId ? 'success' : 'error');
        }
        redirect('/connect-talent' . (($_POST['qs'] ?? '') !== '' ? '?' . $_POST['qs'] : ''));
    }
    view('ops/connect_talent', [
        'f'           => $f,
        'rows'        => connect_pro_search($f),
        'pool'        => connect_pro_pool_count(),
        'disciplines' => function_exists('connect_tx_rows') ? connect_tx_rows('cx_disciplines') : [],
        'work_types'  => cx_pro_work_types(),
        'open_reqs'   => function_exists('cx_open_requirements') ? cx_open_requirements() : [],
    ]);
    return true;
}

/** requirement_id => true for the requirements this professional already applied to. */
function connect_pro_applied_map($proId) {
    $out = [];
    try {
        foreach (ops_all("SELECT requirement_id FROM cx_applications WHERE applicant_professional_id=?", [(int)$proId]) ?: [] as $r)
            $out[(int)$r['requirement_id']] = true;
    } catch (Throwable $e) {}
    return $out;
}

/** A professional's own applications, newest first, with the requirement joined. */
function connect_pro_applications($proId) {
    try {
        return ops_all(
            "SELECT a.*, r.ref_code, r.title, r.location, r.status AS req_status
             FROM cx_applications a JOIN cx_requirements r ON r.id = a.requirement_id
             WHERE a.applicant_professional_id=? ORDER BY a.id DESC", [(int)$proId]) ?: [];
    } catch (Throwable $e) { return []; }
}

/**
 * A professional withdraws their OWN application — only while it is still in
 * play (APPLIED / SHORTLISTED / OFFERED). Scoped to the caller so no one can
 * withdraw another person's application. Returns [ok, message].
 */
function connect_pro_withdraw($proId, $applicationId) {
    $proId = (int)$proId; $applicationId = (int)$applicationId;
    try { $a = ops_one("SELECT * FROM cx_applications WHERE id=? AND applicant_professional_id=?", [$applicationId, $proId]); }
    catch (Throwable $e) { $a = null; }
    if (!$a) return [false, 'That application is not yours.'];
    $st = strtoupper((string)$a['status']);
    if (!in_array($st, ['APPLIED', 'SHORTLISTED', 'OFFERED'], true))
        return [false, 'This application can no longer be withdrawn.'];
    db()->prepare("UPDATE cx_applications SET status='WITHDRAWN', updated_at=? WHERE id=? AND applicant_professional_id=?")
        ->execute([date('c'), $applicationId, $proId]);
    return [true, 'Application withdrawn.'];
}

function connect_pro_view($name, $vars = []) {
    extract($vars);
    $u = connect_pro_user();
    require __DIR__ . '/../views/pro/top.php';
    require __DIR__ . "/../views/pro/$name.php";
    require __DIR__ . '/../views/pro/bottom.php';
}

/** The /pro portal router. Always exits. Dispatched in front of require_login(). */
function connect_pro_route($route, $method) {
    if (!connect_pro_portal_on()) { http_response_code(404); echo 'Not available.'; exit; }
    connect_pro_migrate();

    if ($route === 'pro/login') {
        if (connect_pro_user()) redirect('/pro');
        $err = '';
        if ($method === 'POST') { $err = connect_pro_login($_POST['email'] ?? '', $_POST['password'] ?? ''); if ($err === '') redirect('/pro'); }
        connect_pro_view('login', ['err' => $err]); exit;
    }
    if ($route === 'pro/register') {
        if (connect_pro_user()) redirect('/pro');
        $err = '';
        if ($method === 'POST') { $err = connect_pro_register($_POST); if ($err === '') redirect('/pro/profile'); }
        connect_pro_view('register', ['err' => $err]); exit;
    }
    if ($route === 'pro/logout') { unset($_SESSION['cxpid']); redirect('/pro/login'); }

    if ($route === 'pro/forgot') {   // forgot-password — request a reset link
        if (connect_pro_user()) redirect('/pro');
        $sent = false;
        if ($method === 'POST') { connect_pro_reset_request($_POST['email'] ?? ''); $sent = true; }
        connect_pro_view('forgot', ['sent' => $sent]); exit;
    }
    if ($route === 'pro/reset') {    // forgot-password — set a new password from the link
        if (connect_pro_user()) redirect('/pro');
        $token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
        $problem = connect_pro_reset_problem($token);
        $done = false; $err = '';
        if ($method === 'POST' && $problem === '') {
            $err = connect_pro_reset_complete($token, $_POST['password'] ?? '', $_POST['password2'] ?? '');
            if ($err === '') $done = true;
        }
        connect_pro_view('reset', ['token' => $token, 'problem' => $problem, 'done' => $done, 'err' => $err]); exit;
    }

    connect_pro_require();
    $me = connect_pro_user();

    switch ($route) {
        case 'pro':
            connect_pro_view('dashboard', [
                'me'    => $me,
                'pct'   => connect_pro_profile_pct($me),
                'tier'  => function_exists('connect_verify_tier_for_professional') ? connect_verify_tier_for_professional((int)$me['id']) : 'registered',
                'trust' => function_exists('connect_trust_score_pro') ? connect_trust_score_pro((int)$me['id']) : null,
                'apps'  => connect_pro_applications((int)$me['id']),
                'unread'=> function_exists('connect_msg_pro_unread') ? connect_msg_pro_unread((int)$me['id']) : 0,
                'openjobs' => function_exists('cx_open_requirements') ? count(cx_open_requirements()) : 0,
                'bookings' => function_exists('connect_engage_summary_pro') ? connect_engage_summary_pro((int)$me['id']) : ['total' => 0],
                'prefs' => function_exists('connect_channel_prefs') ? connect_channel_prefs((int)$me['id']) : [],
                'passport_url' => (function_exists('connect_passport_url') && !empty($me['passport_token']))
                    ? connect_passport_url((string)$me['passport_token']) : '',
                'avatar_id' => function_exists('connect_pro_avatar_id') ? connect_pro_avatar_id((int)$me['id']) : 0,
            ]); exit;
        case 'pro/profile':
            $saved = false;
            if ($method === 'POST') {
                connect_pro_profile_save((int)$me['id'], $_POST);
                if (function_exists('connect_channel_set_consent')) connect_channel_set_consent((int)$me['id'], $_POST); // #5 — channel opt-ins
                $saved = true; $me = connect_pro_user();
            }
            connect_pro_view('profile', ['me' => $me, 'saved' => $saved,
                'prefs' => function_exists('connect_channel_prefs') ? connect_channel_prefs((int)$me['id']) : [],
                'disciplines' => function_exists('connect_tx_rows') ? connect_tx_rows('cx_disciplines') : []]); exit;

        case 'pro/jobs':   // A2 — browse open requirements + apply
            if ($method === 'POST' && (int)($_POST['requirement_id'] ?? 0) > 0 && function_exists('cx_requirement_get')) {
                $rq = cx_requirement_get((int)$_POST['requirement_id']);
                if ($rq && in_array(strtoupper((string)$rq['status']), ['OPEN', 'SHORTLISTING'], true)) {
                    cx_application_add((int)$rq['id'], [
                        'applicant_professional_id' => (int)$me['id'],
                        'applicant_name' => (string)$me['name'],
                        'proposed_rate'  => (float)($_POST['proposed_rate'] ?? 0),
                        'cover_note'     => (string)($_POST['cover_note'] ?? ''),
                    ]);
                }
                redirect('/pro/jobs');
            }
            $rows = function_exists('cx_open_requirements') ? cx_open_requirements() : [];
            // A search filter over the open board — title / description / discipline / location.
            $q = trim((string)($_GET['q'] ?? ''));
            if ($q !== '') {
                $needle = mb_strtolower($q);
                $rows = array_values(array_filter($rows, function ($r) use ($needle) {
                    $hay = mb_strtolower(implode(' ', [$r['title'] ?? '', $r['description'] ?? '', $r['discipline_code'] ?? '', $r['location'] ?? '', $r['ref_code'] ?? '']));
                    return strpos($hay, $needle) !== false;
                }));
            }
            $applied = connect_pro_applied_map((int)$me['id']);
            connect_pro_view('jobs', ['me' => $me, 'rows' => $rows, 'applied' => $applied, 'q' => $q]); exit;

        case 'pro/applications':   // A2 — track my applications (+ withdraw)
            if ($method === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
                connect_pro_withdraw((int)$me['id'], (int)($_POST['application_id'] ?? 0));
                redirect('/pro/applications');
            }
            connect_pro_view('applications', ['me' => $me, 'rows' => connect_pro_applications((int)$me['id'])]); exit;

        case 'pro/bookings':   // K20 — my engagements once a job is booked
            connect_pro_view('bookings', [
                'me'   => $me,
                'rows' => function_exists('connect_engage_for_professional') ? connect_engage_for_professional((int)$me['id']) : [],
            ]); exit;

        case 'pro/documents':   // photo / CV / certificates
            if ($method === 'POST') {
                if (($_POST['action'] ?? '') === 'delete') connect_pro_file_delete((int)($_POST['file_id'] ?? 0), (int)$me['id']);
                elseif (!empty($_FILES['file'])) connect_pro_file_add((int)$me['id'], (string)($_POST['kind'] ?? 'OTHER'), $_FILES['file']);
                redirect('/pro/documents');
            }
            connect_pro_view('documents', ['me' => $me, 'files' => connect_pro_files((int)$me['id']), 'kinds' => connect_pro_file_kinds()]); exit;

        case 'pro/file':   // serve one of my own files (ownership-scoped)
            $row = connect_pro_file_row((int)($_GET['id'] ?? 0), (int)$me['id']);
            if (!$row) { http_response_code(404); echo 'Not found.'; exit; }
            $bytes = base64_decode((string)$row['file_data']);
            if (function_exists('send_uploaded_file')) { send_uploaded_file($bytes, (string)$row['file_name'], (string)$row['mime']); exit; }
            header('Content-Type: ' . ((string)$row['mime'] ?: 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . rawurlencode((string)$row['file_name']) . '"');
            echo $bytes; exit;

        case 'pro/messages':   // #4 — two-way messaging on my own engagements
            if (!function_exists('connect_msg_post')) { http_response_code(404); connect_pro_view('dashboard', ['me' => $me, 'pct' => connect_pro_profile_pct($me)]); exit; }
            $pid = (int)$me['id'];
            if ($method === 'POST') {
                $aid = (int)($_POST['application_id'] ?? 0);
                if (connect_msg_pro_owns($aid, $pid)) {
                    connect_msg_post($aid, 'professional', $pid, (string)$me['name'], (string)($_POST['body'] ?? ''));
                }
                redirect('/pro/messages?a=' . $aid);
            }
            $openId = (int)($_GET['a'] ?? 0);
            $open = null;
            if ($openId > 0 && connect_msg_pro_owns($openId, $pid)) {
                connect_msg_mark_read($openId, 'professional', $pid);
                $app = connect_msg_app($openId);
                $open = ['app' => $app, 'thread' => connect_msg_thread($openId)];
            }
            connect_pro_view('messages', [
                'me' => $me,
                'summaries' => connect_msg_summaries(connect_msg_professional_apps($pid), 'professional', $pid),
                'open' => $open, 'openId' => $openId,
            ]); exit;

        case 'pro/verify':   // #3 — get verified: submit identity / credential checks
            if (!function_exists('connect_verify_submit')) { http_response_code(404); connect_pro_view('dashboard', ['me' => $me, 'pct' => connect_pro_profile_pct($me)]); exit; }
            $msg = ''; $msgOk = true;
            if ($method === 'POST') {
                $ct = strtoupper((string)($_POST['check_type'] ?? ''));
                [$msgOk, $msg] = connect_verify_submit('professional', (int)$me['id'], $ct,
                    (string)($_POST['value'] ?? ''), (string)($_POST['evidence'] ?? ''));
                $me = connect_pro_user(); // tier may have changed
            }
            $tierKey = function_exists('connect_verify_tier_for_professional') ? connect_verify_tier_for_professional((int)$me['id']) : 'registered';
            connect_pro_view('verify', [
                'me' => $me, 'msg' => $msg, 'msgOk' => $msgOk,
                'tierKey' => $tierKey,
                'ladder'  => connect_verify_tiers(),
                'types'   => connect_verify_check_types(),
                'checks'  => connect_verify_subject_checks('professional', (int)$me['id']),
            ]); exit;
    }
    // Unknown /pro route.
    http_response_code(404); connect_pro_view('dashboard', ['me' => $me, 'pct' => connect_pro_profile_pct($me)]); exit;
}
