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
            $applied = connect_pro_applied_map((int)$me['id']);
            connect_pro_view('jobs', ['me' => $me, 'rows' => $rows, 'applied' => $applied]); exit;

        case 'pro/applications':   // A2 — track my applications
            connect_pro_view('applications', ['me' => $me, 'rows' => connect_pro_applications((int)$me['id'])]); exit;

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
