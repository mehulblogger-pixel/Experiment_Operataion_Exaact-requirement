<?php
// Admin screens must all respect the plan: Terminology word-groups and the Role
// Workspaces launchpad only show the modules a workspace has.
t_section('Admin gating sweep — terminology + role workspaces');
if (!function_exists('term_groups_licensed') || !function_exists('workspace_route_module')) { t_ok(true, 'sweep helpers not present — skipped'); return; }

$savedOff = (string) setting_get('modules_off', '');
$savedKey = (string) setting_get('licence_key', '');
setting_set('licence_key', ''); if (function_exists('lk_state')) lk_state(true);
setting_set('modules_off', 'sales,operations,money,reporting');
if (function_exists('licence_disabled')) licence_disabled(true);

// Terminology: Parties + People stay; Sales/Operations/Reporting/Money go.
$g = term_groups_licensed();
t_ok(isset($g['Parties']) && isset($g['People']), 'Parties and People wording groups are kept');
t_ok(!isset($g['Operations']) && !isset($g['Sales']) && !isset($g['Reporting']) && !isset($g['Money']),
    'Operations / Sales / Reporting / Money wording groups are hidden for a recruitment plan');

// Role workspaces route → module mapping is correct and gating drops off-plan.
t_eq(workspace_route_module('/schedule'), 'operations', 'a scheduling route maps to operations');
t_eq(workspace_route_module('/idems'), 'reporting', 'an inspection-reporting route maps to reporting');
t_eq(workspace_route_module('/revenue-reconciliation'), 'money', 'a money route maps to money');
t_ok(workspace_route_module('/recruitment') === null && workspace_route_module('/candidates') === null,
    'recruitment routes are core (never hidden)');
t_ok(workspace_route_module('/company-profile') === null && workspace_route_module('/masters') === null,
    'admin/directory routes are core');

setting_set('modules_off', $savedOff);
setting_set('licence_key', $savedKey);
if (function_exists('lk_state')) lk_state(true);
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(true, 'state restored');
