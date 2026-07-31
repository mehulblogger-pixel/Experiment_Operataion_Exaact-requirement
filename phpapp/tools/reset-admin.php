<?php
// ============================================================================
//  Reset the admin password — command line.
//
//  For when the admin login is refused. Sets the password on the admin account
//  named in config.php, so you can sign in again, and makes sure that account
//  is active and a super-admin.
//
//  Usage, from the phpapp directory (cPanel → Terminal):
//      php tools/reset-admin.php "NewPass123"   set the admin password to this
//      php tools/reset-admin.php                reset it to the value in config.php
//
//  Only somebody with server access can run this, which is exactly who is
//  allowed to hold the master key — so it needs no in-app permission.
// ============================================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }

chdir(__DIR__ . '/..');
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/helpers.php';

$cfg = require __DIR__ . '/../config.php';
$user = (string)($cfg['admin']['user'] ?? 'admin');
$new  = $argv[1] ?? (string)($cfg['admin']['pass'] ?? '');

if ($new === '') { fwrite(STDERR, "No password given and none in config.php.\n"); exit(1); }

try { $pdo = db(); }
catch (Throwable $e) { fwrite(STDERR, "Cannot reach the database: " . $e->getMessage() . "\n"); exit(1); }

$hash = password_hash($new, PASSWORD_DEFAULT);
$row  = $pdo->prepare("SELECT id FROM users WHERE username=?");
$row->execute([$user]);
$id = $row->fetchColumn();

// pwd_changed_at / must_change_pwd exist on newer installs; set them when present
// so this counts as a real change and no "you must change your password" gate
// fires on the way in. Wrapped, because an older schema may not have them.
if ($id) {
    $pdo->prepare("UPDATE users SET password_hash=?, is_superuser=1, is_active=1 WHERE id=?")->execute([$hash, $id]);
    try { $pdo->prepare("UPDATE users SET must_change_pwd=0, pwd_changed_at=? WHERE id=?")->execute([date('c'), $id]); } catch (Throwable $e) {}
    echo "Admin password reset for '$user'. You can sign in now.\n";
} else {
    $pdo->prepare("INSERT INTO users (username,password_hash,role,is_superuser,is_active,email) VALUES (?,?, 'ADMIN', 1,1,?)")
        ->execute([$user, $hash, 'admin@example.com']);
    echo "Admin account '$user' did not exist — created it. You can sign in now.\n";
}
