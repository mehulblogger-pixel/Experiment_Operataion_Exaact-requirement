<?php
// ============================================================================
//  CVP Slice 5 — governance: access expiry / auto-revoke, client self-admin,
//  and the scoped assistant.
//  Proves: an expired account is auto-revoked at the identity read with no cron;
//  a client org admin can manage ONLY their own organisation's users; and the
//  assistant is scoped by construction — its context is only the party's own
//  records, never another company's.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

if (function_exists('cvp_migrate')) cvp_migrate();

db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,created_at) VALUES ('Buyer Corp','Buyer Corp',1,'ACTIVE',?)")->execute([date('c')]);
$CLI = (int)db()->lastInsertId();
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,created_at) VALUES ('Rival Corp','Rival Corp',1,'ACTIVE',?)")->execute([date('c')]);
$OTHER = (int)db()->lastInsertId();

head('1. Additive columns for governance');
$probe = ops_one("SELECT access_expires, is_org_admin FROM client_users WHERE 1=0");
ok(true, 'client_users has access_expires + is_org_admin');
$vprobe = ops_all("SELECT access_expires FROM vendor_users WHERE 1=0");
ok(true, 'vendor_users has access_expires');

head('2. Access expiry auto-revokes at the identity read — no cron');
$mkUser = function($pid,$email,$expires,$admin=0) {
    db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,access_expires,is_org_admin,created_at)
        VALUES (?,?,?,?,1,0,?,?,?)")->execute([$pid,$email,$email,password_hash('x',PASSWORD_DEFAULT),$expires,$admin,date('c')]);
    return (int)db()->lastInsertId();
};
$live    = $mkUser($CLI, 'live@buyer.com',   '');                                   // never expires
$future  = $mkUser($CLI, 'future@buyer.com', date('Y-m-d', strtotime('+30 days'))); // still valid
$expired = $mkUser($CLI, 'past@buyer.com',   date('Y-m-d', strtotime('-1 day')));   // lapsed

$_SESSION = ['cuid' => $live];   $GLOBALS['__x']=null; // reset any static cache path
ok(portal_user() && (int)portal_user()['id'] === $live, 'an account with no expiry signs in');
$_SESSION = ['cuid' => $future];
ok(portal_user() && (int)portal_user()['id'] === $future, 'an account whose expiry is in the future signs in');
$_SESSION = ['cuid' => $expired];
ok(portal_user() === null, 'an EXPIRED account is not returned — auto-revoked, no job run');
ok(portal_login('past@buyer.com', 'x') !== '', 'an expired account is refused at login too');
$_SESSION = [];

head('3. A client org admin manages ONLY their own organisation');
db()->prepare("UPDATE client_users SET is_org_admin=1 WHERE id=?")->execute([$live]);
$_SESSION = ['cuid' => $live];
ok(cvp_client_is_admin() === true, 'the flagged user is recognised as an org admin');
// invite into own org
$inv = cvp_client_team_invite($CLI, 'colleague@buyer.com', 'New Colleague', '');
ok(empty($inv['err']) && !empty($inv['token']), 'the admin can invite a colleague into their own org');
$newId = (int)$inv['id'];
ok((int)ops_val("SELECT partner_id FROM client_users WHERE id=?", [$newId]) === $CLI, 'the invited colleague is in the admin\'s own company');
ok((int)ops_val("SELECT is_org_admin FROM client_users WHERE id=?", [$newId]) === 0, 'an admin cannot mint another admin (staff-only)');
// toggle within own org OK; self-toggle refused; cross-org refused
ok(cvp_client_team_toggle($CLI, $newId, $live) === '', 'the admin can withdraw a colleague');
ok((int)ops_val("SELECT is_active FROM client_users WHERE id=?", [$newId]) === 0, 'the colleague is withdrawn');
ok(cvp_client_team_toggle($CLI, $live, $live) !== '', 'the admin cannot change their OWN access here (no self-lockout)');
$rivalUser = $mkUser($OTHER, 'rival@rival.com', '');
ok(cvp_client_team_toggle($CLI, $rivalUser, $live) !== '', "the admin cannot touch another company's user");
ok((int)ops_val("SELECT is_active FROM client_users WHERE id=?", [$rivalUser]) === 1, "the other company's user is untouched");
// expiry set within own org
ok(cvp_client_team_expiry($CLI, $newId, date('Y-m-d', strtotime('+10 days'))) === '', 'the admin can set a colleague\'s access period');
ok(cvp_client_team_expiry($CLI, $rivalUser, '2020-01-01') !== '', "the admin cannot set another company's access period");
$_SESSION = [];

head('4. The team list is scoped to the org');
$team = cvp_client_team($CLI);
$ids = array_map(fn($r) => (int)$r['id'], $team);
ok(in_array($live, $ids, true) && in_array($newId, $ids, true), 'the team list shows the org\'s own users');
ok(!in_array($rivalUser, $ids, true), "the team list excludes another company's users");

head('5. The assistant context is scoped by construction');
db()->prepare("INSERT INTO report_docs (irn,client_id,title,result,release_status,inspection_date,finalized,deleted,created_at,updated_at)
    VALUES ('IR/MINE/1',?, 'My inspection','ACCEPTED','RELEASED','2026-08-01',1,0,?,?)")->execute([$CLI, date('c'), date('c')]);
db()->prepare("INSERT INTO report_docs (irn,client_id,title,result,release_status,inspection_date,finalized,deleted,created_at,updated_at)
    VALUES ('IR/RIVAL/9',?, 'Their inspection','REJECTED','NOT_RELEASED','2026-08-02',1,0,?,?)")->execute([$OTHER, date('c'), date('c')]);
$ctx = cvp_ai_scope_context('CLIENT', $CLI);
ok(strpos($ctx, 'IR/MINE/1') !== false, 'the context includes the party\'s own report');
ok(strpos($ctx, 'IR/RIVAL/9') === false, "the context NEVER includes another company's report");
// With AI unconfigured, the answer degrades gracefully (no crash, no data).
[$ans, $err] = cvp_ai_answer('CLIENT', $CLI, 'which reports are released?');
ok($ans === '' && $err !== '', 'with AI off the assistant returns a friendly message, not a leak or a crash');

head('6. Routes wired');
$psrc = file_get_contents(__DIR__ . '/../lib/portal.php');
ok(strpos($psrc, "case 'portal/team':") !== false && strpos($psrc, "case 'portal/assistant':") !== false, 'client team + assistant routes exist');
$csrc = file_get_contents(__DIR__ . '/../lib/cvp.php');
ok(strpos($csrc, "'vendor/assistant'") !== false, 'the vendor assistant route exists');

// cleanup
db()->exec("DELETE FROM report_docs WHERE client_id IN ($CLI,$OTHER)");
db()->exec("DELETE FROM client_users WHERE partner_id IN ($CLI,$OTHER)");
db()->exec("DELETE FROM business_partners WHERE id IN ($CLI,$OTHER)");
$_SESSION = [];

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== CVP GOVERNANCE: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
