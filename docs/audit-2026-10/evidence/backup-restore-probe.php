<?php
/**
 * EXAACT audit — backup & restore (roadmap 1.5 / 2.4, finding A-F3).
 *
 * Throwaway database; backups written to a scratch folder (setting backup_dir),
 * never next to the app.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/backup-restore-probe.php
 *
 * BEFORE (19 Oct 2026, commit 7fcac52), as run:
 *   A. another workspace's backup uploaded -> ACCEPTED: users became "acme.boss"
 *      only; Acme's confidential client present here; Acme's boss could sign in at
 *      THIS workspace's address with Acme's password; the person who pressed
 *      Restore was deleted -- locked out of their own workspace; nothing in the
 *      activity log.
 *   B. a file that is not a backup ({"data":{"business_partners":[]}}) ->
 *      ACCEPTED: the partner table was emptied (0 rows left).
 * AFTER: run this script.
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';
$scratch = sys_get_temp_dir() . '/exaact-backup-probe-' . getmypid();
@mkdir($scratch, 0700, true);
setting_set('backup_dir', $scratch);
$here = backup_tenant_id();
$users = fn() => implode(', ', array_map(fn($r) => $r['username'], ops_all("SELECT username FROM users ORDER BY id")));
$acmeIn = fn() => (int)ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name='ACME CONFIDENTIAL CLIENT'") > 0;
$as = function ($username, $role, $super) {
    if (!ops_val("SELECT id FROM users WHERE username=?", [$username]))
        db()->prepare("INSERT INTO users (username,first_name,email,role,is_superuser,is_active) VALUES (?,?,?,?,?,1)")->execute([$username, $username, "$username@t.test", $role, $super]);
    $_SESSION['uid'] = (int)ops_val("SELECT id FROM users WHERE username=?", [$username]); current_user(true); ua(true);
};
$say = fn($label, $r) => printf("  %-58s %s\n", $label, !empty($r['error']) ? 'REFUSED — ' . $r['error'] : 'ACCEPTED — ' . (int)($r['restored'] ?? 0) . ' records' . (!empty($r['kept_me']) ? ', restorer kept' : ''));
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Our Own Client','Our Own Client',1,'ACTIVE')")->execute();

$foreign = json_encode(['app' => 'EXAACT', 'version' => 1, 'created_at' => '2026-10-01 10:00:00', 'tenant' => 'acme', 'company' => 'Acme Ltd',
    'data' => [
        'users' => [['id' => 9001, 'username' => 'acme.boss', 'first_name' => 'Acme', 'email' => 'boss@acme.test', 'role' => 'MASTER_ADMIN',
                     'is_superuser' => 1, 'is_active' => 1, 'password_hash' => password_hash('acme-pass', PASSWORD_DEFAULT)]],
        'business_partners' => [['id' => 9001, 'legal_name' => 'ACME CONFIDENTIAL CLIENT', 'display_name' => 'ACME CONFIDENTIAL CLIENT', 'is_client' => 1, 'status' => 'ACTIVE']]]]);

echo "workspace here = '$here'\n\nA. another workspace's backup\n";
$as('here.admin', 'ADMIN', 0);
$say('an administrator (not master) uploads it', backup_import_json($foreign));
$as('here.master', 'MASTER_ADMIN', 1);
$say('a master administrator uploads it, no confirmation', backup_import_json($foreign));
$say('a master types the WRONG workspace name', backup_import_json($foreign, 'acme'));
printf("  after the refusals — users: %s · Acme's client here: %s\n", $users(), $acmeIn() ? 'YES' : 'no');
$say("a master types this workspace's name ('$here')", backup_import_json($foreign, $here));
printf("  users now: %s · Acme's client here: %s\n", $users(), $acmeIn() ? 'yes (as confirmed)' : 'no');
$log = ops_one("SELECT subject FROM activities WHERE subject LIKE 'Workspace restored from backup%' ORDER BY id DESC LIMIT 1");
printf("  activity log: %s\n", $log ? $log['subject'] : '(nothing)');

echo "\nB. a file that is not an EXAACT backup\n";
$before = (int)ops_val("SELECT COUNT(*) FROM business_partners");
$say('{"data":{"business_partners":[]}}', backup_import_json(json_encode(['data' => ['business_partners' => []]])));
$say('an EXAACT backup with a damaged table', backup_import_json(json_encode(['app' => 'EXAACT', 'version' => 1, 'tenant' => $here, 'data' => ['business_partners' => 'oops']])));
printf("  partner rows before %d, after %d\n", $before, (int)ops_val("SELECT COUNT(*) FROM business_partners"));

echo "\nC. this workspace's OWN backup, taken before the restorer's account existed\n";
$own = backup_create('manual', true);
$as('late.admin', 'ADMIN', 0);   // joins after the backup
$r = backup_restore($own['id']);
$say('late.admin restores it', $r);
printf("  late.admin can still sign in: %s\n", ops_val("SELECT COUNT(*) FROM users WHERE username='late.admin'") ? 'yes' : 'NO — locked out');
foreach (glob("$scratch/*/*") ?: [] as $f) @unlink($f);
