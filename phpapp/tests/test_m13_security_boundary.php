<?php
// ============================================================================
//  PHASE 1 · MILESTONE 13 — ATTACKING THE BOUNDARY
//
//  M5–M10 built the entitlement boundary. M11–M12 made it understandable. M13
//  attacks it from the outside and only changes code where something actually
//  broke. Three things did:
//
//   V1  A signed-in identity was NOT BOUND to the workspace that issued it.
//       $_SESSION['uid'] was resolved against whatever database happened to be
//       live, so user id 1 was Alice in one workspace and Bob in another. Any
//       path able to switch workspace therefore carried the session across.
//
//   V2  /reset?w=<workspace> — the public password-reset page took the workspace
//       key straight from the query string, so one unauthenticated request could
//       point a session at any workspace by name.
//
//   V3  Signing in with an e-mail belonging to another workspace switched the
//       live database BEFORE the password was checked, and a WRONG password did
//       not switch it back.
//
//   V4  The call detail fetched by id with no office/branch scope — the list
//       hid other branches' calls, the detail served them.
//
//  These tests are the attacks, kept. They fail if any of it comes back.
// ============================================================================

t_section('Milestone 13 — security boundary');

db();
$asTenant = function ($k) { $GLOBALS['__tenant'] = ['key' => $k, 'company' => strtoupper($k)]; };
$origTenant = $GLOBALS['__tenant'] ?? null;
$origSession = $_SESSION;

// ---- V1 · A signed-in identity belongs to ONE workspace --------------------
$pdo = db();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_superuser,is_active)
               VALUES ('m13_victim','Victim','ADMIN',1,1)")->execute();
$uid = (int) $pdo->lastInsertId();

t_ok(function_exists('auth_workspace_key'), 'V1 · the workspace of a session can be named');
t_ok(function_exists('auth_bind_workspace'), 'V1 · and an identity can be bound to it');

$asTenant('workspace-a');
$_SESSION['uid'] = $uid;
auth_bind_workspace();
t_eq($_SESSION['uid_ws'], 'workspace-a', 'V1 · signing in stamps the workspace that issued the identity');
t_ok(current_user(true) !== null, 'V1 · and the person resolves there');

$asTenant('workspace-b');                       // the attack: switch, session untouched
t_ok(current_user(true) === null,
     'V1 · ATTACK BLOCKED — the identity does not resolve in another workspace');
t_ok(!is_master(), 'V1 · and carries no authority there');
$asTenant('workspace-a');
t_ok(current_user(true) !== null, 'V1 · while it still works where it belongs');

// The control install is a workspace too, and a tenant identity is not valid on it.
$asTenant('');
t_ok(current_user(true) === null, 'V1 · a company identity is not valid on the control install');
$asTenant('workspace-a');
t_ok(current_user(true) !== null, 'V1 · and is restored on return');

// A session predating the fix carries no binding; it is not broken by it, and it
// acquires one the next time the person signs in.
unset($_SESSION['uid_ws']);
$asTenant('workspace-b');
t_ok(current_user(true) !== null, 'V1 · a session with no binding is left alone (documented gap)');
auth_bind_workspace();
t_eq($_SESSION['uid_ws'], 'workspace-b', 'V1 · and binds on the next sign-in');
$asTenant('workspace-a');
t_ok(current_user(true) === null, 'V1 · after which it is bound like any other');

// ---- V2 · The public reset page cannot move a signed-in session ------------
$src = file_get_contents(__DIR__ . '/../lib/pwreset.php');
$code = preg_replace('#^\s*//.*$#m', '', $src);
t_ok(strpos($code, "if (function_exists('current_user') && current_user()) return '';") !== false,
     'V2 · the reset page refuses to switch workspace for a signed-in session');
t_ok(strpos($code, 'saas_enter_tenant($wkey)') !== false,
     'V2 · it can still find the workspace a reset token lives in (the token is in that database)');

// ---- V3 · A failed sign-in must not leave you in someone else's workspace --
$idx = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../index.php'));
$loginFail = strpos($idx, "return render_login('Invalid username or password.');");
t_ok($loginFail !== false, 'V3 · the failed-login path exists');
$before = substr($idx, max(0, $loginFail - 400), 400);
t_ok(strpos($before, 'saas_leave_tenant()') !== false,
     'V3 · and it steps back out of the workspace it had switched into');

// ---- V1+V3 together: the whole chain is closed -----------------------------
// Even if a path did switch the workspace, the binding refuses the identity.
$_SESSION['uid'] = $uid; $asTenant('workspace-a'); auth_bind_workspace();
$asTenant('victim-workspace');                  // pretend any switcher succeeded
t_ok(current_user(true) === null, 'V1+V3 · a switched workspace cannot carry an identity, whatever switched it');
$asTenant('workspace-a');

// ---- V4 · Object-level scope on a fetch by id ------------------------------
$pdo = db();
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (91,'M13 A',1)")->execute(); } catch (Throwable $e) {}
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (92,'M13 B',1)")->execute(); } catch (Throwable $e) {}
$pdo->prepare("INSERT INTO calls (call_code,status,executing_office_id,sbu,created_at) VALUES ('M13-CALL-B','OPEN',92,'',?)")
    ->execute([date('c')]);
$callB = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username,first_name,role,permissions,scope_offices,home_office_id,is_active)
               VALUES ('m13_branch','BranchA','COORDINATOR','','91',91,1)")->execute();
$_SESSION['uid'] = (int) $pdo->lastInsertId();
$asTenant('workspace-a'); auth_bind_workspace(); current_user(true); ua(true);

t_eq(scope_offices(), [91], 'V4 · the user is scoped to one branch');
t_ok(!scope_allows(92, ''), 'V4 · and the other branch is outside their scope');
[$w, $a] = scope_clause('c.executing_office_id', 'c.sbu');
$list = ops_all("SELECT id FROM calls c WHERE $w AND c.id=?", array_merge($a, [$callB]));
t_ok(!$list, 'V4 · the LIST correctly hides the other branch call');
$ops = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/ops.php'));
$callRoute = strpos($ops, "if (\$route === 'call') {");
t_ok($callRoute !== false, 'V4 · the call detail route exists');
$body = substr($ops, $callRoute, 2000);
t_ok(strpos($body, 'scope_allows($call[') !== false,
     'V4 · ATTACK BLOCKED — the DETAIL now applies the same scope as the list');
// The job detail, which always had it, is untouched.
$jobRoute = strpos($ops, "if (\$route === 'job') {");
t_ok($jobRoute !== false && strpos(substr($ops, $jobRoute, 2000), 'scope_allows($job[') !== false,
     'V4 · and the job detail still has its own');

// ---- CSRF · every state-changing staff request is covered ------------------
t_ok(function_exists('csrf_ok') && function_exists('csrf_token'), 'CSRF · the machinery exists');
$_SESSION['csrf'] = 'a-known-token';
t_ok(csrf_ok('a-known-token'), 'CSRF · a correct token is accepted');
t_ok(!csrf_ok(''),              'CSRF · a MISSING token is refused');
t_ok(!csrf_ok('wrong'),         'CSRF · an INVALID token is refused');
t_ok(!csrf_ok(null),            'CSRF · a null token is refused');
unset($_SESSION['csrf']);
t_ok(!csrf_ok('a-known-token'), 'CSRF · a token with no session behind it is refused');
$helpers = file_get_contents(__DIR__ . '/../lib/helpers.php');
t_ok(strpos($helpers, 'hash_equals(') !== false, 'CSRF · the comparison is timing-safe');
t_ok(strpos($idx, "if (\$method === 'POST' && !csrf_ok(\$_POST['_csrf'] ?? ''))") !== false,
     'CSRF · and ONE global gate covers every staff POST, not each handler remembering');
t_ok(strpos($helpers, 'csrf_stamp_forms') !== false, 'CSRF · tokens are stamped into every POST form automatically');

// ---- Entitlement is still enforced at the boundary (M5–M12 unchanged) ------
$_SESSION['uid'] = $uid;
setting_set('saas_entitled_modules', 'operations,reporting'); setting_set('modules_off', '');
$asTenant('workspace-a'); auth_bind_workspace(); current_user(true); ua(true); licence_disabled(true);
t_ok(is_master(), 'S-1 · signed in as a master');
t_ok(ops_module_gate('jobs', true) && ops_module_gate('documents', true), 'S-1 · Operations and Reporting WORK');
foreach (['candidates' => 'HR', 'quotes' => 'Sales', 'invoices' => 'Money', 'connect-requirements' => 'Marketplace'] as $r => $n)
    t_ok(!ops_module_gate($r, true), "S-1 · $n DENIED to a master");
t_ok(!books_can() && !careers_enabled() && !connect_market_can(), 'S-1 · and at the gates behind them');

// ---- No technical leakage in what a refusal says ---------------------------
$a = access_state_for('hiring');
foreach (['SQLSTATE', 'SELECT ', 'users', 'sqlite', 'mysql', '/home/', '.php'] as $leak)
    t_ok(stripos($a['message'] . ' ' . $a['hint'], $leak) === false, "disclosure · a refusal never mentions '$leak'");

// ---- V5 · The fatal page shows internals to administrators only ------------
// It carries the exception message, the FILESYSTEM PATH and the line number, and
// the message routinely carries SQL and table names. An unauthenticated visitor
// always got only a reference; a signed-in member of staff got the lot.
$fatal = preg_replace('#^\s*//.*$#m', '', $idx);
$g = strpos($fatal, '$signedIn = !empty($_SESSION[' . "'uid'" . '])');
t_ok($g !== false, 'V5 · the fatal page decides who sees the detail');
$guard = substr($fatal, $g, 260);
t_ok(strpos($guard, 'is_master()') !== false || strpos($guard, 'is_admin_level()') !== false,
     'V5 · and shows it to administrators only, not to anyone merely signed in');
t_ok(strpos($fatal, 'Reference <b>') !== false,
     'V5 · while everyone else gets a reference to quote');
t_ok(strpos($fatal, '$ref = strtoupper(substr(md5(') !== false,
     'V5 · which is a digest, not the detail itself');

// ---- restore ----
$_SESSION = $origSession;
if ($origTenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $origTenant;
licence_disabled(true); current_user(true); ua(true);
t_ok(true, 'session and tenant restored');
