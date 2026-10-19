<?php
// ============================================================================
//  Restore refuses what is not this workspace's own backup (A-F3, GAP-048).
//  A real restore replaces the database, so it is driven by
//  docs/audit-2026-10/evidence/backup-restore-probe.php in its own process;
//  this pins the rules and the refusal paths, which write nothing.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('backup — foreign, invalid and damaged files are refused');
$prevUid = $_SESSION['uid'] ?? null; $bd0 = setting_get('backup_dir', '');
$scratch = sys_get_temp_dir() . '/exaact-bk-guard-' . getmypid(); @mkdir($scratch, 0700, true);
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    setting_set('backup_dir', $scratch);
    $here = backup_tenant_id();
    $mk = fn($tenant, $extra = []) => array_merge(['app' => 'EXAACT', 'version' => 1, 'tenant' => $tenant, 'company' => 'Acme Ltd', 'data' => ['business_partners' => []]], $extra);
    $as = function ($role, $super) {
        db()->prepare("INSERT INTO users (username,first_name,email,role,is_superuser,is_active) VALUES (?,?,?,?,?,1)")->execute(['bk.' . strtolower($role) . mt_rand(), $role, mt_rand() . '@t.test', $role, $super]);
        $_SESSION['uid'] = (int)db()->lastInsertId(); current_user(true); ua(true);
    };
    $as('ADMIN', 0);
    t_eq(backup_check($mk($here)), [true, false, ''], "this workspace's own backup is accepted");
    [$ok, $foreign, $msg] = backup_check($mk('acme'));
    t_ok(!$ok && $foreign && strpos($msg, 'Only a master administrator') !== false, 'another workspace\'s backup is refused to an ordinary administrator, and says so');
    t_ok(strpos($msg, '"acme"') !== false && strpos($msg, 'user accounts') !== false, '...naming where it came from and what it would replace');
    $as('MASTER_ADMIN', 1);
    t_eq(backup_check($mk('acme'))[0], false, 'a master without confirmation is refused');
    t_eq(backup_check($mk('acme'), 'acme')[0], false, '...and with the wrong name');
    t_eq(backup_check($mk('acme'), $here), [true, true, ''], "...and allowed only by typing this workspace's name");
    t_eq(backup_check($mk(''), $here)[1], true, 'a backup with no workspace name counts as foreign');
    t_eq(backup_check(['data' => []])[0], false, 'a file without app=EXAACT is refused');
    t_eq(backup_check($mk($here, ['version' => 2]))[0], false, 'an unknown format version is refused');
    t_eq(backup_check($mk($here, ['data' => ['business_partners' => 'oops']]))[0], false, 'a damaged table is refused');
    t_eq(backup_check($mk($here, ['data' => 'oops']))[0], false, 'data that is not a list of tables is refused');

    // the refusal paths write nothing: no table emptied, no file stored
    $n = (int)ops_val("SELECT COUNT(*) FROM business_partners");
    $r = backup_import_json(json_encode(['data' => ['business_partners' => []]]));
    t_ok(!empty($r['error']), 'uploading a non-backup is refused');
    t_eq((int)ops_val("SELECT COUNT(*) FROM business_partners"), $n, '...and the partner table is untouched (it used to be emptied)');
    $r = backup_import_json(json_encode($mk('acme')));
    t_ok(!empty($r['error']) && !empty($r['foreign']), 'uploading another workspace\'s backup without confirmation is refused');
    t_eq(glob($scratch . '/*/*_clientimport.json*') ?: [], [], '...and a refused upload leaves no file behind');
    t_ok(!empty(backup_import_json('not json at all')['error']), 'garbage is refused');

    // the restore keeps the restorer and logs itself (driven in the probe)
    $src = file_get_contents(__DIR__ . '/../lib/backup.php');
    t_ok(strpos($src, 'Keep the restorer able to sign in') !== false && strpos($src, "\$kept = true;") !== false, 'restore keeps the account of the person restoring');
    t_ok(strpos($src, "'Workspace restored from backup '") !== false, 'every restore is written to the activity log');
    t_ok(strpos(file_get_contents(__DIR__ . '/../views/ops/backup.php'), 'name="confirm_workspace"') !== false, 'the upload form offers the confirmation box to a master');
} finally {
    setting_set('backup_dir', $bd0);
    foreach (glob($scratch . '/*/*') ?: [] as $f) @unlink($f);
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    current_user(true); ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}
