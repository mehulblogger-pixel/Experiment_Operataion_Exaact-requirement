<?php
// Configurable role workspaces — per-role landing + curated launchpad + personal
// start page. Everything stays permission-safe: a workspace can never point a
// user at a screen their role cannot open. Additive.
t_section('configurable role workspaces');

workspace_migrate();
$pdo = db();

// Sign in as a master (sees the whole menu) so ops_nav_index reflects a real user.
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser) VALUES ('ws_master','WS','MASTER_ADMIN',1,1)")->execute();
$uid = (int)$pdo->lastInsertId();
$_SESSION['uid'] = $uid; current_user(true); ua(true);
ops_nav_index(true);   // prime the per-session nav cache for this user

// --- Config round-trip ---
workspace_config_save('COORDINATOR', '/candidates', ['/candidates', '/requisitions', '/recruitment-cc']);
$cfg = workspace_role_config('COORDINATOR');
t_eq($cfg['landing'], '/candidates', 'a role landing page is saved');
t_eq(count($cfg['tiles']), 3, 'the launchpad tiles are saved');
t_ok(in_array('/requisitions', $cfg['tiles'], true), 'a chosen tile is stored');

// Saving empty clears the role's config (no orphan rows).
workspace_config_save('COORDINATOR', '/candidates', ['/candidates', '/requisitions', '/recruitment-cc']);
workspace_config_save('ASST_MANAGER', '', []);
t_ok(!array_key_exists('ASST_MANAGER', workspace_config()), 'an empty workspace is not stored');

// --- Landing resolution (permission-safe) ---
$allowed = workspace_allowed_routes();
$pickA = null; foreach (['/candidates', '/requisitions', '/recruitment-cc'] as $r) if (isset($allowed[$r])) { $pickA = $r; break; }
t_ok($pickA !== null, 'the master menu contains at least one recruitment route to test with');

// A role landing to an allowed route resolves for a user of that role.
workspace_config_save('MASTER_ADMIN', $pickA, [$pickA]);
$land = workspace_landing_for(['id' => $uid, 'role' => 'MASTER_ADMIN', 'start_route' => '']);
t_eq($land, $pickA, 'a configured, allowed role landing resolves');

// A landing to a route NOT in the menu is refused (never sent somewhere unopenable).
workspace_config_save('MASTER_ADMIN', '/zzz-not-a-real-route', ['/zzz-not-a-real-route']);
$land2 = workspace_landing_for(['id' => $uid, 'role' => 'MASTER_ADMIN', 'start_route' => '']);
t_eq($land2, '', 'a landing to an unopenable screen is ignored (falls back to dashboard)');

// A personal start page overrides the role landing.
workspace_config_save('MASTER_ADMIN', $pickA, [$pickA]);
$land3 = workspace_landing_for(['id' => $uid, 'role' => 'MASTER_ADMIN', 'start_route' => $pickA]);
t_eq($land3, $pickA, 'a personal start page is honoured');
$land4 = workspace_landing_for(['id' => $uid, 'role' => 'MASTER_ADMIN', 'start_route' => '']);
t_eq($land4, $pickA, 'with no personal page, the role landing applies');

// --- Launchpad rendering ---
$u = current_user();
$html = workspace_launchpad_html(['id' => $uid, 'role' => 'MASTER_ADMIN', 'start_route' => '']);
t_ok(strpos($html, 'Your workspace') !== false, 'the launchpad renders when tiles are configured');
$emptyHtml = workspace_launchpad_html(['id' => $uid, 'role' => 'BRANCH_APP_MANAGER', 'start_route' => '']);
t_eq($emptyHtml, '', 'the launchpad renders nothing when no tiles are configured for the role');

// --- Personal start page: validation + persistence ---
[$ok1, $m1] = workspace_set_user_start(['id' => $uid], $pickA);
t_ok($ok1, 'a valid start page is accepted');
t_eq(ops_val("SELECT start_route FROM users WHERE id=?", [$uid]), $pickA, 'the start page is persisted on the user');
[$ok2, $m2] = workspace_set_user_start(['id' => $uid], '/zzz-not-a-real-route');
t_ok(!$ok2, 'a start page the user cannot open is rejected');
[$ok3, $m3] = workspace_set_user_start(['id' => $uid], '/');
t_ok($ok3, 'choosing the dashboard resets the start page');
t_eq((string)ops_val("SELECT start_route FROM users WHERE id=?", [$uid]), '', 'the reset clears the stored start route');

// Clean up: remove config + the throwaway user, restore no-session.
workspace_config_save('MASTER_ADMIN', '', []);
workspace_config_save('COORDINATOR', '', []);
$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
unset($_SESSION['uid']); current_user(true); ua(true); ops_nav_index(true);
