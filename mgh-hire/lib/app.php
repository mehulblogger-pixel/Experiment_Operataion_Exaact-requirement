<?php
// =========================================================================
//  MGH Hire — application helpers: sessions, auth, roles, CSRF, small utils.
// =========================================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/licence.php';
require_once __DIR__ . '/cv.php';
require_once __DIR__ . '/tasks.php';

// ---- Output / request utilities ------------------------------------------
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k, $d = '') { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function get($k, $d = '')  { return isset($_GET[$k])  ? trim((string)$_GET[$k])  : $d; }
function redirect($to) { header('Location: ' . $to); exit; }
function now() { return date('c'); }

function fdate($v, $withTime = false) {
    if (!$v) return '—';
    $t = strtotime($v);
    if (!$t) return e($v);
    return date($withTime ? 'd M Y, H:i' : 'd M Y', $t);
}

// ---- Sessions -------------------------------------------------------------
function session_boot() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,   // marked Secure only over HTTPS (see INSTALL §HTTPS)
    ]);
    session_start();
}

// ---- CSRF -----------------------------------------------------------------
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!hash_equals($_SESSION['csrf'] ?? '', post('_csrf'))) {
        http_response_code(400);
        exit('Security check failed. Please go back and try again.');
    }
}

// ---- Flash messages -------------------------------------------------------
function flash($msg, $type = 'ok') { $_SESSION['flash'][] = ['m' => $msg, 't' => $type]; }
function flash_take() { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ---- Authentication -------------------------------------------------------
function current_user() {
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $s = db()->prepare("SELECT * FROM users WHERE id=? AND active=1");
        $s->execute([$_SESSION['uid']]);
        $u = $s->fetch() ?: null;
    }
    return $u;
}
function require_login() {
    if (!current_user()) redirect('?p=login');
}
function login_attempt($username, $password) {
    $s = db()->prepare("SELECT * FROM users WHERE username=? AND active=1");
    $s->execute([$username]);
    $u = $s->fetch();
    if ($u && password_verify($password, $u['pass_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id'];
        return true;
    }
    return false;
}
function logout() { $_SESSION = []; session_destroy(); }

// =========================================================================
//  Roles & permissions — the standalone product's OWN role model.
//  (Independent of the inspection app's permission matrix.)
//
//    admin          — everything, incl. settings, users, pipeline
//    recruiter       — run the pipeline, add candidates, move stages
//    hiring_manager  — review, shortlist, approve gates (no settings)
//    interviewer     — record interview feedback only
//    viewer          — read-only
// =========================================================================
function ROLES() {
    return [
        'admin'          => 'Administrator',
        'recruiter'      => 'Recruiter',
        'hiring_manager' => 'Hiring Manager',
        'interviewer'    => 'Interviewer',
        'viewer'         => 'Viewer',
    ];
}
function PERMS() {
    // permission => roles that hold it
    return [
        'settings'       => ['admin'],
        'users'          => ['admin'],
        'pipeline.edit'  => ['admin'],
        'req.manage'     => ['admin','recruiter'],
        'req.approve'    => ['admin','hiring_manager'],
        'cand.manage'    => ['admin','recruiter'],
        'cand.move'      => ['admin','recruiter','hiring_manager'],
        'gate.decide'    => ['admin','recruiter','hiring_manager'],
        'interview.log'  => ['admin','recruiter','hiring_manager','interviewer'],
        'offer.manage'   => ['admin','recruiter'],
        'view'           => ['admin','recruiter','hiring_manager','interviewer','viewer'],
    ];
}
function can($perm) {
    $u = current_user();
    if (!$u) return false;
    $map = PERMS();
    if (!isset($map[$perm])) return false;
    return in_array($u['role'], $map[$perm], true);
}
function require_can($perm) {
    if (!can($perm)) { http_response_code(403); exit('You do not have permission for this action.'); }
}

// =========================================================================
//  Pipeline helpers
// =========================================================================
function stages_all($activeOnly = true) {
    $sql = "SELECT * FROM stages" . ($activeOnly ? " WHERE active=1" : "") . " ORDER BY seq";
    return db()->query($sql)->fetchAll();
}
function stage($id) {
    $s = db()->prepare("SELECT * FROM stages WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
function first_stage() {
    $r = db()->query("SELECT * FROM stages WHERE active=1 ORDER BY seq LIMIT 1")->fetch();
    return $r ?: null;
}
function next_stage($stageId) {
    $cur = stage($stageId);
    if (!$cur) return first_stage();
    $s = db()->prepare("SELECT * FROM stages WHERE active=1 AND seq>? ORDER BY seq LIMIT 1");
    $s->execute([$cur['seq']]);
    return $s->fetch() ?: null;
}

// Record an event on the candidate timeline.
function cand_log($candidateId, $action, $from = 0, $to = 0, $decision = '', $remarks = '') {
    $u = current_user();
    db()->prepare("INSERT INTO candidate_events
        (candidate_id,from_stage,to_stage,action,decision,remarks,actor,created_at)
        VALUES (?,?,?,?,?,?,?,?)")
      ->execute([$candidateId,$from,$to,$action,$decision,$remarks,$u['name'] ?? 'system', now()]);
}
function cand_events($candidateId) {
    $s = db()->prepare("SELECT * FROM candidate_events WHERE candidate_id=? ORDER BY id DESC");
    $s->execute([$candidateId]);
    return $s->fetchAll();
}

// Move a candidate to a specific stage (with audit).
function cand_move($cand, $toStageId, $decision = '', $remarks = '') {
    db()->prepare("UPDATE candidates SET stage_id=? WHERE id=?")->execute([$toStageId, $cand['id']]);
    $to = stage($toStageId);
    if ($to && $to['kind'] === 'terminal') {
        db()->prepare("UPDATE candidates SET status='hired' WHERE id=?")->execute([$cand['id']]);
    }
    cand_log($cand['id'], 'move', (int)$cand['stage_id'], $toStageId, $decision, $remarks);
}

// ---- Branding -------------------------------------------------------------
function product_name() { return setting('product_name', 'MGH Hire'); }
function brand_color()  { return setting('brand_color', '#4f46e5'); }
function accent_color() { return setting('accent_color', '#0ea5e9'); }
function currency()     { return setting('currency', '₹'); }
