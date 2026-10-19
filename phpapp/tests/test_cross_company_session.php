<?php
// ============================================================================
//  A session issued by one company is never honoured by another (GAP-049).
//  Every company's sessions share one store on the server, so the identity in a
//  session must carry the company that issued it. Driven live in
//  docs/audit-2026-10/evidence/live-cross-company-session/; this pins the rule
//  for all four identities.  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('sessions are bound to the company that issued them');
$t0 = $GLOBALS['__tenant'] ?? null; $s0 = $_SESSION ?? [];
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$at = function ($key) { $GLOBALS['__tenant'] = ['key' => $key, 'company' => $key, 'error' => '', 'saas' => $key !== '', 'base' => 'exaact.test']; };
try {
    portal_migrate(); connect_pro_migrate();
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,is_vendor,status) VALUES ('Bind Co','Bind Co',1,1,'ACTIVE')")->execute(); $pid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO client_users (partner_id,email,name,is_active,must_change,created_at) VALUES (?,?,?,1,0,?)")->execute([$pid, 'bind.c@t.test', 'Bind Client', date('c')]); $cu = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES (?,?,1,?)")->execute(['bind.p@t.test', 'Bind Pro', date('c')]); $px = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO users (username,first_name,email,role,is_superuser,is_active) VALUES ('bind.staff','Bind','bind.s@t.test','COORDINATOR',0,1)")->execute(); $su = (int)db()->lastInsertId();

    // ---- client portal ---------------------------------------------------------
    $at('acme'); $_SESSION = []; portal_start_session($cu);
    t_eq($_SESSION['cuid_ws'] ?? null, 'acme', 'a client-portal sign-in is stamped with its company');
    t_ok(portal_user() !== null, '...and is honoured there');
    $at('beta');
    t_eq(portal_user(), null, 'the same session at ANOTHER company is not signed in (it used to be taken as that company\'s user)');
    $at('acme'); unset($_SESSION['cuid_ws']);
    t_eq(portal_user(), null, 'an unstamped client session is refused on a company workspace');
    $at(''); 
    t_ok(portal_user() !== null, '...but a single-company install, where an unstamped session can only be its own, is unaffected');

    // ---- professional portal ---------------------------------------------------
    $at('acme'); $_SESSION = ['cxpid' => $px]; auth_bind_identity('cxpid');
    t_ok(connect_pro_user() !== null, 'a professional session is honoured at its own company');
    $at('beta'); t_eq(connect_pro_user(), null, '...and not at another');
    $pro = file_get_contents(__DIR__ . '/../lib/connect_pro.php');
    t_eq(substr_count($pro, "auth_bind_identity('cxpid')"), 2, 'both professional sign-in points (register, log in) stamp the session');
    t_eq(substr_count($pro, "session_regenerate_id(true);   // a planted id"), 2, '...and both now issue a fresh session id, as the other portals do');

    // ---- vendor portal ---------------------------------------------------------
    $cvp = file_get_contents(__DIR__ . '/../lib/cvp.php');
    t_ok(strpos($cvp, "auth_bind_identity('vuid')") !== false && strpos($cvp, "auth_identity_here('vuid')") !== false, 'the vendor portal stamps at sign-in and checks on every read');

    // ---- staff ---------------------------------------------------------------
    $at('acme'); $_SESSION = ['uid' => $su]; auth_bind_workspace(); current_user(true);
    t_ok(current_user(true) !== null, 'a staff session is honoured at its own company');
    $at('beta'); t_eq(current_user(true), null, '...and not at another');
    // An unstamped STAFF session keeps the documented M13 exception: no sign-in path
    // creates one, and the scheduled jobs act as staff inside a company with one.
    $at('beta'); $_SESSION = ['uid' => $su]; current_user(true);
    t_ok(current_user(true) !== null, 'an unstamped staff session (cron acting as a person) is still honoured — the M13 exception');
    $src = file_get_contents(__DIR__ . '/../lib/security.php');
    t_ok(strpos($src, 'auth_bind_workspace()') !== false, '...because every interactive staff sign-in stamps its session');
} finally {
    if ($t0 === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $t0;
    $_SESSION = $s0; current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}
