<?php
// ============================================================================
//  Custom (company-defined) roles. A workspace can name its own role from the
//  Add-person screen; the role COPIES an existing built-in role's permissions
//  (its base). The one rule that must never break: a custom role can grant no
//  more than the built-in role it was copied from — it must NEVER fall through
//  to full ADMIN access, and an unknown/stale role key keeps its old fallback.
// ============================================================================

t_section('Custom roles — copy a base, never escalate to admin');

if (!function_exists('custom_role_add') || !function_exists('role_effective_key')) { t_ok(true, 'custom roles not present — skipped'); return; }

$saved = (string) setting_get('custom_roles', '');
setting_set('custom_roles', '');

// --- Create one that behaves like Coordinator. ---
$key = custom_role_add('Sourcing Lead', 'COORDINATOR');
t_ok($key !== '' && $key !== 'COORDINATOR', 'a custom role gets its own key, distinct from the base');
$all = custom_roles_all();
t_ok(isset($all[$key]) && $all[$key]['base'] === 'COORDINATOR', 'it is stored with its base role');
t_ok(isset(roles_all()[$key]) && roles_all()[$key] === 'Sourcing Lead', 'it appears in the full role list with its friendly name');
t_eq(role_name($key), 'Sourcing Lead', 'its label resolves for display');

// --- The safety rule: resolves to its base, and grants exactly the base perms. ---
t_eq(role_effective_key($key), 'COORDINATOR', 'the custom role resolves to its base role');
$coordPerms = role_defaults('COORDINATOR')['perms'];
$customPerms = role_defaults($key)['perms'];
sort($coordPerms); sort($customPerms);
t_eq($customPerms, $coordPerms, 'the custom role grants EXACTLY the base role permissions — not more');
$allPerms = array_keys(all_permissions());
t_ok(count($customPerms) < count($allPerms), 'the custom role does NOT get every permission (no admin escalation)');

// --- A user carrying the custom role resolves to the base, never admin. ---
$u = ['role' => $key, 'is_superuser' => 0, 'permissions' => ''];
$eff = user_effective_perms($u);
sort($eff);
t_eq($eff, $coordPerms, 'a user with the custom role has the base role\'s effective permissions');
t_ok(!in_array('users.manage.global', $eff, true) || in_array('users.manage.global', $coordPerms, true),
    'the custom role cannot manage users unless its base could');

// --- A base that does not exist is pinned to Coordinator (never blank/all). ---
$key2 = custom_role_add('Odd Role', 'NON_EXISTENT_ROLE');
t_eq(custom_roles_all()[$key2]['base'] ?? '', 'COORDINATOR', 'an invalid base is pinned to Coordinator (least surprise, least privilege)');

// --- A totally unknown (non-custom) role key keeps the historic ADMIN fallback. ---
t_eq(role_effective_key('SOMETHING_STALE'), 'SOMETHING_STALE', 'an unknown key is left unchanged by the resolver (callers keep their own fallback)');

// Restore.
setting_set('custom_roles', $saved);
t_ok(true, 'custom-roles setting restored');
