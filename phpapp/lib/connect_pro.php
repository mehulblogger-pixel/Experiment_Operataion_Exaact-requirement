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
    // The marketplace application table (K2) gains a professional applicant link,
    // so a self-listed professional applies as themselves and dedupes correctly.
    if (function_exists('ensure_column')) ensure_column('cx_applications', 'applicant_professional_id', 'INT DEFAULT 0');
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

    connect_pro_require();
    $me = connect_pro_user();

    switch ($route) {
        case 'pro':
            connect_pro_view('dashboard', ['me' => $me, 'pct' => connect_pro_profile_pct($me)]); exit;
        case 'pro/profile':
            $saved = false;
            if ($method === 'POST') { connect_pro_profile_save((int)$me['id'], $_POST); $saved = true; $me = connect_pro_user(); }
            connect_pro_view('profile', ['me' => $me, 'saved' => $saved,
                'disciplines' => function_exists('connect_tx_rows') ? connect_tx_rows('cx_disciplines') : []]); exit;
        // A2 routes (jobs / apply / applications) are added in the next slice.
    }
    // Unknown /pro route.
    http_response_code(404); connect_pro_view('dashboard', ['me' => $me, 'pct' => connect_pro_profile_pct($me)]); exit;
}
