<?php
// ============================================================================
//  Workspace storage safety.
//
//  Two live workspaces were lost because their entire database was a single
//  file inside the folder the update method deletes, and because the
//  application would then cheerfully create a fresh empty one in its place.
//  These tests hold both halves of the fix closed:
//
//    1. a new workspace gets a REAL database whenever the server can make one
//       (cPanel API on shared hosting, or a db-admin credential on a VPS), and
//       a file OUTSIDE the app folder only when it cannot;
//    2. a workspace whose data file has gone missing is never silently
//       re-created — because an empty workspace sitting at the right path is
//       what turns a recoverable incident into permanent loss.
// ============================================================================

t_section('Workspace storage safety');

// ---- 1. How does this server create a database? ---------------------------
// The old code asked only about a db-admin credential, which shared hosting
// never has — so every workspace on cPanel hosting silently became a file.
$origAdmin = $GLOBALS['SAAS_DB_ADMIN'] ?? null;
$origHost  = setting_get('cpanel_host', '');
$origUser  = setting_get('cpanel_user', '');
$origToken = setting_get('cpanel_token', '');

$GLOBALS['SAAS_DB_ADMIN'] = null;
setting_set('cpanel_host', ''); setting_set('cpanel_user', ''); setting_set('cpanel_token', '');
t_eq(saas_db_autocreate_method(), '', 'with neither method configured, nothing can be created automatically');
t_ok(!saas_can_autocreate_db(), 'and the console is told so');

setting_set('cpanel_host', 'server.example.com'); setting_set('cpanel_user', 'acct'); setting_set('cpanel_token', 'TOKEN');
t_eq(saas_db_autocreate_method(), 'cpanel', 'a cPanel API token IS a way to create a database');
t_ok(saas_can_autocreate_db(), 'so shared hosting now offers automatic creation');

setting_set('cpanel_host', ''); setting_set('cpanel_user', ''); setting_set('cpanel_token', '');
$GLOBALS['SAAS_DB_ADMIN'] = ['host' => 'localhost', 'user' => 'root', 'pass' => 'x'];
t_eq(saas_db_autocreate_method(), 'admin', 'a database-admin credential still works on a VPS');

// cPanel wins when both exist: it is the method that matches the hosting.
setting_set('cpanel_host', 'server.example.com'); setting_set('cpanel_user', 'acct'); setting_set('cpanel_token', 'TOKEN');
t_eq(saas_db_autocreate_method(), 'cpanel', 'cPanel is preferred when both are configured');

setting_set('cpanel_host', (string) $origHost); setting_set('cpanel_user', (string) $origUser);
setting_set('cpanel_token', (string) $origToken);
$GLOBALS['SAAS_DB_ADMIN'] = $origAdmin;

t_ok(!saas_can_autocreate_db(), 'test settings restored');

// ---- 1b. The third way: the app's own database login ----------------------
// On a panel with no API at all, this is the difference between one click and a
// manual step per customer — so it is worth asking the server directly. It is
// only ever used once the probe has PROVED it works: attempting it on a host
// that forbids it would fail every single time a company is added.
setting_set('saas_db_selfcreate', '');
t_eq(saas_db_autocreate_method(), '', 'the app does not assume it may create databases');

setting_set('saas_db_selfcreate', '1');
t_eq(saas_db_autocreate_method(), 'self', 'once proved, the app can create them with its own login');

setting_set('cpanel_host', 'server.example.com'); setting_set('cpanel_user', 'acct'); setting_set('cpanel_token', 'TOKEN');
t_eq(saas_db_autocreate_method(), 'cpanel', 'a hosting API still outranks it');
setting_set('cpanel_host', ''); setting_set('cpanel_user', ''); setting_set('cpanel_token', '');
$GLOBALS['SAAS_DB_ADMIN'] = ['host' => 'localhost', 'user' => 'root', 'pass' => 'x'];
t_eq(saas_db_autocreate_method(), 'admin', 'and so does a database-admin credential');
$GLOBALS['SAAS_DB_ADMIN'] = null;
setting_set('saas_db_selfcreate', '');

// The probe must answer, never explode, and never touch anything on a database
// that cannot be tested this way.
$probe = saas_db_selfcreate_probe();
t_ok(is_array($probe) && array_key_exists('ok', $probe) && array_key_exists('msg', $probe),
     'the capability test always returns an answer');
t_ok($probe['ok'] === false, 'on this SQLite harness it correctly answers no');
t_ok(stripos($probe['msg'], 'not running on MySQL') !== false, 'and says why, in plain words');
t_eq((string) ($probe['leftover'] ?? ''), '', 'and leaves nothing behind');

t_eq(saas_db_account_prefix(), '', 'no account prefix can be derived without a MySQL database name');
$n = saas_db_names_for('xyz-recurit', 'mghaiapp');
t_eq($n['name'], 'mghaiapp_xyz_recurit', 'a new database is named inside the account prefix');
t_ok(strlen($n['user']) <= 32, "and the user name fits MySQL's 32-character limit");

// ---- 2. A new workspace never lands inside the app folder ------------------
[$db, $kind] = tenant_auto_storage('brandnewco');
t_eq($kind, 'sqlite', 'with no way to create a database, a file is used');
$appDir = realpath(dirname(__DIR__));
$fileDir = realpath(dirname((string) $db['sqlite'])) ?: dirname((string) $db['sqlite']);
t_ok(strpos((string) $db['sqlite'], 'tenant-brandnewco.sqlite') !== false, 'named after the workspace');
t_ok(strncmp($fileDir, $appDir . '/', strlen($appDir) + 1) !== 0 || basename($fileDir) === 'data',
     'and placed outside the app folder (or in data/), never loose in the folder an upload clears');
t_ok(!is_file((string) $db['sqlite']), 'choosing a path does not create the file');

// ---- 3. The guard: a missing data file is never re-created -----------------
$tmp = sys_get_temp_dir() . '/p1safe_' . bin2hex(random_bytes(4));
@mkdir($tmp, 0777, true);
$live = $tmp . '/tenant-guarded.sqlite';
file_put_contents($live, 'x');

saas_tenant_upsert('guarded', ['company' => 'Guarded Co']);
saas_tenant_upsert('guarded', ['route_json' => json_encode(['sqlite' => $live])]);

t_eq(saas_tenant_data_missing('guarded'), '', 'a workspace whose file is present opens normally');

// Never set up + no file = genuinely new. Creating it is the CORRECT behaviour.
@unlink($live);
t_eq(saas_tenant_data_missing('guarded'), '',
     'a workspace that was never set up is still allowed to be created');

// Now the control database knows it existed. A missing file is now a MISSING FILE.
t_ok(saas_tenant_mark_provisioned('guarded'), 'the control database can record that it was set up');
$msg = saas_tenant_data_missing('guarded');
t_ok($msg !== '', 'once it has been set up, a missing file refuses entry');
t_ok(stripos($msg, 'not been re-created') !== false, 'and says plainly that nothing was created');
t_ok(!is_file($live), 'and checking really did not create the file');

// A zero-byte file counts as missing: that is exactly what a half-made
// connection leaves behind, and it must not be mistaken for real data.
file_put_contents($live, '');
t_ok(saas_tenant_data_missing('guarded') !== '', 'an empty file is treated as missing, not as data');
file_put_contents($live, 'x');
t_eq(saas_tenant_data_missing('guarded'), '', 'and a real file clears the refusal again');

// A MySQL workspace is out of scope — there is no file to lose.
saas_tenant_upsert('guarded', ['route_json' => json_encode(['name' => 'acct_guarded', 'user' => 'acct_guarded'])]);
t_eq(saas_tenant_data_missing('guarded'), '', 'a MySQL workspace is never blocked by the file guard');

// ---- 4. Entry is refused at the chokepoint, before the connection switches --
saas_tenant_upsert('guarded', ['route_json' => json_encode(['sqlite' => $live])]);
@unlink($live);
$before = $_SESSION['saas_tenant'] ?? null;
unset($_SESSION['saas_tenant']);
t_ok(saas_enter_tenant('guarded') === false, 'entering a workspace with no data file is refused');
t_ok(!isset($_SESSION['saas_tenant']), 'and the session is NOT switched into it');
t_ok(!is_file($live), 'and still nothing was created');

file_put_contents($live, 'x');
t_ok(saas_enter_tenant('guarded') === true, 'a healthy workspace is entered as before');
saas_leave_tenant();
if ($before !== null) $_SESSION['saas_tenant'] = $before; else unset($_SESSION['saas_tenant']);

// ---- 5. The stamp maintains itself ----------------------------------------
saas_tenant_upsert('seenco', ['company' => 'Seen Co']);
$seen = $tmp . '/tenant-seenco.sqlite';
saas_tenant_upsert('seenco', ['route_json' => json_encode(['sqlite' => $seen])]);
t_eq((string) (saas_tenant_get('seenco')['provisioned_at'] ?? ''), '', 'a new workspace starts unstamped');

saas_tenant_seen_alive_sweep();
t_eq((string) (saas_tenant_get('seenco')['provisioned_at'] ?? ''), '',
     'a workspace with no file on disk is not stamped');

file_put_contents($seen, 'data');
saas_tenant_seen_alive_sweep();
$stamp = (string) (saas_tenant_get('seenco')['provisioned_at'] ?? '');
t_ok($stamp !== '', 'seeing the file once is enough — no login or migration needed');

// Stamped once, never rewritten, so it keeps the FIRST time the data was seen.
saas_tenant_seen_alive_sweep();
t_eq((string) (saas_tenant_get('seenco')['provisioned_at'] ?? ''), $stamp, 'and the stamp is written once, not on every page load');

// Which means deleting the file now refuses entry, instead of making a new one.
@unlink($seen);
t_ok(saas_tenant_data_missing('seenco') !== '', 'so a later deletion is caught, not silently papered over');

@unlink($live); @unlink($seen); @rmdir($tmp);
t_ok(!is_dir($tmp), 'fixtures cleaned up');
