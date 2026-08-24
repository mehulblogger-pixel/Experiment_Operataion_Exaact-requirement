<?php
// Every permission on the access editor carries a plain-English "what it grants /
// who needs it" explanation (the ⓘ tooltip). Guards that none is left as a bare key.
t_section('every permission has an explanation for the access editor');

$all = all_permissions();               // fine-grained + every module view/edit
t_ok(count($all) > 50, 'the permission catalogue is populated');

$bare = [];
foreach (array_keys($all) as $k) {
    $help = perm_help($k);
    // A real explanation is a sentence, not the raw key echoed back.
    if (!is_string($help) || $help === $k || strlen(trim($help)) < 12) $bare[] = $k;
}
t_ok($bare === [], 'every permission returns a real explanation (' . (count($bare) ? 'missing: ' . implode(', ', array_slice($bare, 0, 8)) : 'all covered') . ')');

// Spot-check a few of the sensitive ones read sensibly.
t_ok(stripos(perm_help('data.profitability'), 'managers') !== false, 'profitability help names who should have it');
t_ok(stripos(perm_help('users.manage.global'), 'administrator') !== false, 'global user management is flagged as admin-only');
t_ok(stripos(perm_help('mod.identity.view'), 'read-only') !== false, 'a module view permission explains it is read-only');
t_ok(stripos(perm_help('mod.jobs.edit'), 'change') !== false || stripos(perm_help('mod.jobs.edit'), 'add') !== false, 'a module edit permission explains it can change records');

// The help must be reachable by TAP (phones have no hover): each ⓘ carries its text
// in data-help and a click popover shows it without toggling the checkbox.
$form = file_get_contents(__DIR__ . '/../views/ops/user_form.php');
t_ok(strpos($form, 'data-help="') !== false, 'each permission ⓘ carries its help in data-help (tap-readable)');
t_ok(strpos($form, 'perm-tip') !== false && strpos($form, "closest('.perm-i')") !== false, 'a tap/click popover renders the help on touch devices');
t_ok(strpos($form, 'e.preventDefault(); e.stopPropagation();') !== false, 'tapping the ⓘ does not toggle the permission checkbox');
