<?php
// Extra ready-made letters + the one-click "Recruitment-only company" preset.
t_section('extra letters + recruitment-only preset');

// --- Extra letter templates are seeded (idempotently) ---
doc_tpl_migrate();
foreach (['CONFIRMATION', 'RELIEVING', 'INTERNSHIP'] as $code)
    t_ok((int)ops_val("SELECT COUNT(*) FROM doc_templates WHERE code=?", [$code]) === 1, "the $code template exists exactly once");
// Offer + appointment are still present.
t_ok((int)ops_val("SELECT COUNT(*) FROM doc_templates WHERE code='OFFER'") === 1, 'the offer template is present');
t_ok((int)ops_val("SELECT COUNT(*) FROM doc_templates WHERE code='APPOINTMENT'") === 1, 'the appointment template is present');
// Re-running the seed does not duplicate.
doc_tpl_seed_extra();
t_ok((int)ops_val("SELECT COUNT(*) FROM doc_templates WHERE code='RELIEVING'") === 1, 're-seeding never duplicates a template');
// The relieving letter uses candidate tokens (auto-filled by the studio).
$rl = ops_one("SELECT body FROM doc_templates WHERE code='RELIEVING'");
t_ok(strpos((string)$rl['body'], '{name}') !== false && strpos((string)$rl['body'], '{company}') !== false, 'the relieving letter carries auto-fill tokens');

// --- role_grant_perm is non-destructive ---
$pdo = db();
$before = role_perms('COORDINATOR');
t_ok(role_grant_perm('COORDINATOR', 'hiring.admin'), 'granting a valid permission to a role succeeds');
$after = role_perms('COORDINATOR');
t_ok(in_array('hiring.admin', $after, true), 'the coordinator now holds hiring.admin');
t_eq(count(array_diff($before, $after)), 0, 'no existing permission was removed by the grant');
t_ok(!role_grant_perm('COORDINATOR', 'not.a.real.perm'), 'an unknown permission is refused');

// --- The one-click preset applies the package + the recruitment manager ---
$did = recruitment_only_provision();
t_ok(is_array($did) && count($did) >= 2, 'the preset reports the steps it performed');
t_eq(product_package_current(), 'RECRUITMENT_HR', 'the installation is now the Recruitment-only package');
t_ok(in_array('hiring.admin', role_perms('COORDINATOR'), true), 'the coordinator is a Recruitment Manager after the preset');
// A coordinator can now configure recruitment without being a system admin.
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active) VALUES ('co_recadmin','CO','COORDINATOR',1)")->execute();
$uid = (int)$pdo->lastInsertId();
$_SESSION['uid'] = $uid; current_user(true); ua(true);
t_ok(hiring_admin_can(), 'the coordinator may now configure the recruitment module');
t_ok(!can('settings.manage'), 'the coordinator still cannot manage system settings');

// Clean up: restore the enterprise package + drop the override + user.
$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
unset($_SESSION['uid']); current_user(true); ua(true);
if (function_exists('product_package_apply')) product_package_apply('ENTERPRISE');
setting_set('role_access', '');   // clear the test's role override
