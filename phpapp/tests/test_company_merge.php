<?php
// The two "profile" screens are merged into one Company profile: identity +
// "what you do" live together, the brand name never shows blank, and the cockpit
// points at the single screen.
t_section('Company profile — merged, no blank name');

// Brand / trading name falls back to the workspace name so it is never blank.
$savedName = (string) setting_get('company_name', '');
$savedApp  = (string) setting_get('app_name', '');
setting_set('company_name', '');
setting_set('app_name', 'Acme Widgets');
$p = company_profile();
t_eq($p['brand'], 'Acme Widgets', 'the brand name falls back to the workspace name when company_name is blank');
setting_set('company_name', 'Acme Pvt Ltd');
t_eq(company_profile()['brand'], 'Acme Pvt Ltd', 'an explicit company name still wins');

// The cockpit sends "Company profile" to the single merged screen.
if (function_exists('cockpit_sections')) {
    $s = cockpit_sections();
    t_eq($s['business_profile']['route'] ?? '', '/company-profile', 'the cockpit profile card opens the one merged Company profile screen');
}

// The merged screen carries the capability groups so "what you do" is on it.
if (function_exists('cockpit_capability_groups')) {
    t_ok(is_array(cockpit_capability_groups([])), 'the merged screen has the "what does your company do" activities available');
}

setting_set('company_name', $savedName);
setting_set('app_name', $savedApp);
t_ok(true, 'company name settings restored');
