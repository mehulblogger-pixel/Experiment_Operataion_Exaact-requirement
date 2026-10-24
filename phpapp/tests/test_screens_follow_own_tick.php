<?php
// docs/02-permission-matrix.md 19l (owner, test book SAL-000, 24 Oct 2026):
// a screen follows its OWN tick. Removing a tick must close the screen;
// the Sales roles and Finance get no Quality screen through the client register;
// Finance holds Jobs = View with nothing implied, and still removes expense lines.
t_section('Each screen follows its own tick (SAL-000)');

$pdo = db(); $prevUid = $_SESSION['uid'] ?? null;
$as = function ($role, $perms = '') use ($pdo) {
    $pdo->exec("DELETE FROM users WHERE username='tick.probe'");
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,password_hash,permissions) VALUES ('tick.probe','T','P',?,1,'x',?)")->execute([$role, $perms]);
    $_SESSION['uid'] = (int) $pdo->lastInsertId(); current_user(true); ua(true);
};
$gates = fn() => [crmdash_can(), sat_can_view(), disclosure_can_view(), sitedocs_can_view()];
try {
    foreach (['BUSINESS_DEV_MANAGER', 'KEY_ACCOUNTS_MANAGER', 'MARKETING_MANAGER', 'MARKETING_EXECUTIVE'] as $r) {
        $as($r);
        [$dash, $sat, $dis, $site] = $gates();
        t_ok($dash && !$sat && !$dis && !$site, "$r: Sales dashboard yes; Customer satisfaction, Disclosure consent, Site entry documents no");
    }
    $as('FINANCE');
    [$dash, $sat, $dis, $site] = $gates();
    t_ok(!$sat && !$dis && !$site, 'Finance: no Quality screens through the client register / billing view of jobs');
    t_ok(can('mod.jobs.view') && !can('mod.jobs.edit') && !can('mod.jobs.delete'), 'Finance: Jobs = View, with no Edit or Delete implied');
    t_eq(perm_route_verb('expense-delete'), 'view', 'expense-delete is not verb-gated — its handler admits accounts (finance.reconcile)');
    t_ok(strpos((string) file_get_contents(__DIR__ . '/../lib/ops.php'), "ops_require(is_coordinator_level() || can('finance.reconcile') || is_master(),") !== false, '…and that handler check is still there');

    foreach (['COORDINATOR', 'OPERATION_MANAGER', 'BRANCH_MANAGER', 'SBU_HEAD', 'BUSINESS_DIRECTOR'] as $r) {
        $as($r); [, $sat, $dis, $site] = $gates();
        t_ok($sat && $dis && $site, "$r keeps Customer satisfaction, Disclosure consent and Site entry documents");
    }
    foreach (['QUALITY_HEAD', 'TECHNICAL_HEAD', 'BRANCH_APP_MANAGER'] as $r) { $as($r); t_ok(sat_can_view(), "$r keeps Customer satisfaction"); }

    // Removing the Sales reports tick closes the Sales dashboard — the owner's exact complaint.
    $as('MARKETING_EXECUTIVE');
    $own = array_values(array_diff(ua()['perms'], ['mod.crm_reports.view']));
    // stored the way the Permissions tab stores it
    $as('MARKETING_EXECUTIVE', user_perms_to_store($own, 'MARKETING_EXECUTIVE', array_keys(all_permissions()), ''));
    t_ok(can('mod.quotes.view') && !crmdash_can(), 'unticking Sales reports closes the Sales dashboard, though quotes are still ticked');
} finally {
    $pdo->exec("DELETE FROM users WHERE username='tick.probe'");
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    current_user(true); ua(true);
}

t_section('A person\'s unticked module stays unticked (SAL-000 root cause)');
// An install that never saved Roles & access has no global module stamp; the
// person's own set now carries one, so a module they had all ticks removed from
// is not "new" and is not given back.
$prevStamp = (string) setting_get('role_access_modules', '');
setting_set('role_access_modules', '');
try {
    t_ok(modules_at_last_save() === null, '(an install that never saved Roles & access)');
    $role = 'MARKETING_EXECUTIVE';
    $roleSet = role_perm_set($role);
    $chosen = array_values(array_diff($roleSet, ['mod.crm_reports.view', 'mod.inquiries.view', 'mod.inquiries.add', 'mod.inquiries.edit', PERM_VOCAB_TAG]));
    $stored = user_perms_to_store($chosen, $role, array_keys(all_permissions()), '');
    t_ok(strpos($stored, PERM_MODS_TAG) !== false, 'the saved set carries which modules existed when it was saved');
    $eff = user_effective_perms(['role' => $role, 'permissions' => $stored]);
    t_ok(!in_array('mod.crm_reports.view', $eff, true) && !in_array('mod.inquiries.view', $eff, true), 'Sales reports and Enquiries stay unticked — not handed back as "new modules"');
    t_ok(!preg_grep('/^@mods=/', $eff), 'the stamp is bookkeeping: it never appears as a right');
    $again = user_perms_to_store(array_values(array_diff($eff, [PERM_VOCAB_TAG])), $role, array_keys(all_permissions()), $stored);
    t_eq(substr_count($again, PERM_MODS_TAG), 1, 'saving again keeps exactly one stamp');
    // A set saved by the verb-era editor before the stamp existed: no NEW_MODULES re-grant either.
    $verbEra = implode(',', array_merge($chosen, [PERM_VOCAB_TAG]));
    t_ok(!in_array('mod.inquiries.view', user_effective_perms(['role' => $role, 'permissions' => $verbEra]), true), 'a set saved before the stamp (verb era) keeps its gaps too');
    // A genuinely new module still reaches a person (stamp lists only what existed).
    $old = str_replace('|crm_reports', '', $stored);
    t_ok(in_array('mod.crm_reports.view', user_effective_perms(['role' => $role, 'permissions' => $old]), true), 'a module missing from the stamp (added later) is still given its role default');
} finally { setting_set('role_access_modules', $prevStamp); }
