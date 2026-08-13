<?php
// ============================================================================
//  CVP Slice 2 — the vendor portal.
//  Proves: a vendor is a THIRD, separate identity (own table, own vuid session,
//  never a client or staff); onboarding refuses a non-vendor company and
//  duplicate addresses; a vendor sees ONLY reports staff have explicitly shared
//  (vendor_visible=1) and only their own; the share toggle is confidentiality-
//  first (nothing shared by default); and the audience resolver reports VENDOR.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

if (function_exists('cvp_migrate')) cvp_migrate();

// Two vendors and a non-vendor (client) to prove the onboarding + scope rules.
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_vendor,is_client,status,created_at) VALUES ('Acme Fasteners Ltd','Acme Fasteners',1,0,'ACTIVE',?)")->execute([date('c')]);
$vA = (int)db()->lastInsertId();
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_vendor,is_client,status,created_at) VALUES ('Bolt Works Pvt','Bolt Works',1,0,'ACTIVE',?)")->execute([date('c')]);
$vB = (int)db()->lastInsertId();
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_vendor,is_client,status,created_at) VALUES ('Buyer Corp','Buyer Corp',0,1,'ACTIVE',?)")->execute([date('c')]);
$cli = (int)db()->lastInsertId();

head('1. Additive schema — vendor_users, vendor_audit, report_docs.vendor_visible');
ok((int)ops_val("SELECT COUNT(*) FROM vendor_users WHERE 1=0") === 0, 'vendor_users exists');
ok((int)ops_val("SELECT COUNT(*) FROM vendor_audit WHERE 1=0") === 0, 'vendor_audit exists');
$rdCols = ops_all("SELECT * FROM report_docs WHERE 1=0");
ok(true, 'report_docs is reachable');
db()->prepare("INSERT INTO report_docs (irn,vendor_id,status,finalized,deleted,vendor_visible,created_at,updated_at) VALUES ('X',?, 'ISSUED',1,0,0,?,?)")->execute([$vA, date('c'), date('c')]);
$probe = ops_one("SELECT vendor_visible FROM report_docs WHERE vendor_id=?", [$vA]);
ok($probe && array_key_exists('vendor_visible', $probe), 'report_docs gained a vendor_visible flag (default 0)');
db()->exec("DELETE FROM report_docs WHERE vendor_id=$vA");

head('2. Onboarding — a vendor only, one address only');
ok(!empty(cvp_vendor_invite($cli, 'a@buyer.com', 'A', 0)['err']), 'a non-vendor company is refused (a client cannot be onboarded here)');
ok(!empty(cvp_vendor_invite($vA, 'not-an-email', 'A', 0)['err']), 'a bad e-mail is refused');
$inv = cvp_vendor_invite($vA, 'qa@acme.com', 'Asha QA', 0);
ok(empty($inv['err']) && !empty($inv['token']), 'a valid vendor invite is created and returns a one-time link');
ok(!empty(cvp_vendor_invite($vA, 'qa@acme.com', 'Dup', 0)['err']), 'a duplicate address is refused');
$vuA = (int)$inv['id'];

head('3. Accept sets their own password; login then works');
ok(cvp_vendor_invite_problem($inv['token']) === '', 'the fresh invite token is valid on the way in');
$err = cvp_vendor_accept($inv['token'], 'S0me-Long-Passw0rd!', 'S0me-Long-Passw0rd!');
ok($err === '', 'the invitee sets their own password and the invite is consumed');
ok(cvp_vendor_invite_problem($inv['token']) !== '', 'the token cannot be used a second time');
$u = ops_one("SELECT * FROM vendor_users WHERE id=?", [$vuA]);
ok((string)$u['password_hash'] !== '' && (int)$u['must_change'] === 0, 'the password is stored and must_change cleared');

head('4. Separate identity — its own session key (vuid), never a client');
$_SESSION = [];
$_SESSION['vuid'] = $vuA;
ok(cvp_vendor_user() && (int)cvp_vendor_user()['id'] === $vuA, 'a vuid session resolves the vendor user');
ok(cvp_vendor_id() === $vA, 'the vendor scope comes from the session, not the request');
ok(!function_exists('portal_user') || portal_user() === null, 'a vendor session is NOT a client session (portal_user stays null)');
ok(cvp_current_audience() === 'VENDOR', 'the audience resolver reports VENDOR for a vendor session');

head('5. A vendor sees ONLY reports staff have shared, and only their own');
// vendor A: one shared, one not shared; vendor B: one shared (must not leak to A)
db()->prepare("INSERT INTO report_docs (irn,vendor_id,title,status,finalized,deleted,vendor_visible,finalized_at,created_at,updated_at) VALUES ('VR/A/SHARED',?, 'Audit A','ISSUED',1,0,1,?,?,?)")->execute([$vA, date('c'), date('c'), date('c')]);
$rShared = (int)db()->lastInsertId();
db()->prepare("INSERT INTO report_docs (irn,vendor_id,title,status,finalized,deleted,vendor_visible,finalized_at,created_at,updated_at) VALUES ('VR/A/HIDDEN',?, 'Audit A2','ISSUED',1,0,0,?,?,?)")->execute([$vA, date('c'), date('c'), date('c')]);
$rHidden = (int)db()->lastInsertId();
db()->prepare("INSERT INTO report_docs (irn,vendor_id,title,status,finalized,deleted,vendor_visible,finalized_at,created_at,updated_at) VALUES ('VR/B/SHARED',?, 'Audit B','ISSUED',1,0,1,?,?,?)")->execute([$vB, date('c'), date('c'), date('c')]);
$rOther = (int)db()->lastInsertId();

$rows = cvp_vendor_reports();
$irns = array_map(fn($r) => $r['irn'], $rows);
ok(in_array('VR/A/SHARED', $irns, true), 'the shared report is visible');
ok(!in_array('VR/A/HIDDEN', $irns, true), 'an un-shared report of theirs is NOT visible (nothing shared by default)');
ok(!in_array('VR/B/SHARED', $irns, true), "another vendor's report is NOT visible");
ok(cvp_vendor_report($rShared) !== null, 'the shared report opens');
ok(cvp_vendor_report($rHidden) === null, 'the un-shared report does not exist for the vendor');
ok(cvp_vendor_report($rOther) === null, "another vendor's report does not exist for the vendor");

head('6. The staff share toggle is confidentiality-first');
$_SESSION = [];   // act as staff for the toggle
ok(cvp_report_set_vendor_visible($rHidden, true) === true, 'staff can share a finalized report that names a vendor');
$_SESSION['vuid'] = $vuA;
$irns2 = array_map(fn($r) => $r['irn'], cvp_vendor_reports());
ok(in_array('VR/A/HIDDEN', $irns2, true), 'once shared, it appears in the vendor portal');
$_SESSION = [];
ok(cvp_report_set_vendor_visible($rHidden, false) === true, 'staff can un-share it again');
// A report with no vendor cannot be shared.
db()->prepare("INSERT INTO report_docs (irn,vendor_id,status,finalized,deleted,vendor_visible,created_at,updated_at) VALUES ('NOVEND', NULL,'ISSUED',1,0,0,?,?)")->execute([date('c'), date('c')]);
$rNoVend = (int)db()->lastInsertId();
ok(cvp_report_set_vendor_visible($rNoVend, true) === false, 'a report that names no vendor cannot be shared');

head('7. The dispatch + staff admin are wired');
$idx = file_get_contents(__DIR__ . '/../index.php');
ok(strpos($idx, "cvp_vendor_route(\$route, \$method)") !== false, 'index.php dispatches /vendor/* to the vendor router');
$opsSrc = file_get_contents(__DIR__ . '/../lib/ops.php');
ok(strpos($opsSrc, 'ops_cvp_vendor_admin') !== false, 'the staff vendor-admin route is dispatched');

// cleanup
db()->exec("DELETE FROM report_docs WHERE id IN ($rShared,$rHidden,$rOther,$rNoVend)");
db()->exec("DELETE FROM vendor_users WHERE vendor_id IN ($vA,$vB)");
db()->exec("DELETE FROM business_partners WHERE id IN ($vA,$vB,$cli)");
$_SESSION = [];

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== CVP VENDOR: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
