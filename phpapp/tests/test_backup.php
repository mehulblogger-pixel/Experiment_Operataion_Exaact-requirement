<?php
// Backup & restore — a complete snapshot of a workspace's database, stored above
// the web root, that a workspace admin can download and restore from. Runs end to
// end against the throwaway test database, into a temp backup folder.

t_section('Backup & restore engine');

if (!function_exists('backup_create') || !function_exists('backup_restore')) {
    t_ok(true, 'backup engine not present — skipped'); return;
}

// Point backups at an isolated temp folder so the test never writes near the app.
$tmp = sys_get_temp_dir() . '/exaact_bk_' . bin2hex(random_bytes(4));
$savedDir = (string) setting_get('backup_dir', '');
setting_set('backup_dir', $tmp);

// A table we control, so counts are predictable regardless of what else the
// bootstrap seeded.
db()->exec('CREATE TABLE IF NOT EXISTS bk_probe (id INTEGER PRIMARY KEY, label TEXT)');
db()->exec('DELETE FROM bk_probe');
db()->exec("INSERT INTO bk_probe (id,label) VALUES (1,'alpha'),(2,'béta ₹'),(3,'gamma')");

// ---- Create -----------------------------------------------------------------
$r = backup_create('manual', true);
t_ok(empty($r['error']), 'a backup is created without error');
t_ok(!empty($r['id']) && str_ends_with((string) $r['id'], '.json.gz'), 'the snapshot is a compressed .json.gz file');
t_ok((int) $r['total_rows'] >= 3, 'the snapshot counted our probe rows');
t_ok(!empty($r['safe']), 'the backup folder we set is treated as a safe location');

// ---- List -------------------------------------------------------------------
$list = backup_list();
t_ok(is_array($list) && count($list) >= 1, 'the new backup shows in the list');
$backupId = $list[0]['id'];

// ---- Export bytes are valid JSON with our data ------------------------------
$bytes = backup_export($backupId);
$decoded = json_decode((string) $bytes, true);
t_ok(is_array($decoded) && isset($decoded['data']['bk_probe']), 'the downloaded backup is valid JSON containing the table');
t_eq(count($decoded['data']['bk_probe']), 3, 'all three rows are inside the backup');

// ---- Restore brings deleted data back ---------------------------------------
db()->exec('DELETE FROM bk_probe');                       // simulate data loss
t_eq((int) db()->query('SELECT COUNT(*) FROM bk_probe')->fetchColumn(), 0, 'rows are gone before restore');
$rr = backup_restore($backupId);
t_ok(empty($rr['error']), 'restore runs without error');
t_eq((int) db()->query('SELECT COUNT(*) FROM bk_probe')->fetchColumn(), 3, 'restore brought all three rows back');
t_eq(db()->query("SELECT label FROM bk_probe WHERE id=2")->fetchColumn(), 'béta ₹', 'unicode text survived the round-trip');

// ---- Restore first saved a pre-restore safety copy (reversible) -------------
$reasons = array_map(fn($b) => $b['reason'], backup_list());
t_ok(in_array('pre_restore', $reasons, true), 'a safety copy was taken before the restore (restore is reversible)');

// ---- Invalid ids are rejected ----------------------------------------------
t_ok(backup_export('../../etc/passwd') === null, 'a path-traversal id is refused');
t_ok(!empty(backup_restore('not-a-real-id')['error']), 'an unknown backup id is refused');

// ---- Retention keeps only the newest N --------------------------------------
for ($i = 0; $i < BACKUP_MAX_KEEP + 4; $i++) { usleep(1100); backup_create('manual', true); }
t_ok(count(backup_list()) <= BACKUP_MAX_KEEP, 'retention keeps at most the configured number of backups');

// ---- Clean up ---------------------------------------------------------------
foreach (glob($tmp . '/*/*') ?: [] as $f) @unlink($f);
foreach (glob($tmp . '/*') ?: [] as $d) { @unlink($d . '/.htaccess'); @rmdir($d); }
@unlink($tmp . '/.htaccess'); @rmdir($tmp);
setting_set('backup_dir', $savedDir);
try { db()->exec('DROP TABLE bk_probe'); } catch (Throwable $e) {}
t_ok(true, 'temp backup folder cleaned up');
