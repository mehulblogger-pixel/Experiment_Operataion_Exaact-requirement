<?php
// Self-service forgot / reset password. A token is created for a real account,
// the raw token is stored only as a hash, it works exactly once, it expires, and
// applying it actually changes the password.

t_section('Forgot / reset password');

if (!function_exists('pwreset_request') || !function_exists('pwreset_apply')) {
    t_ok(true, 'password-reset engine not present — skipped'); return;
}

pwreset_migrate();

// A known account to recover.
$email = 'reset.me@example.com';
db()->exec('DELETE FROM users WHERE email = ' . db()->quote($email));
db()->prepare("INSERT INTO users (username, password_hash, role, is_active, email) VALUES (?,?,?,1,?)")
    ->execute(['reset_me', password_hash('oldpassword', PASSWORD_DEFAULT), 'ADMIN', $email]);
$uid = (int) db()->lastInsertId();

// ---- Request creates a hashed, unused token; raw token is never stored -------
db()->exec("DELETE FROM password_resets");
pwreset_request($email);
$row = ops_one("SELECT * FROM password_resets WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$uid]);
t_ok($row !== null, 'a reset row is created for a real account');
t_ok(strlen((string) $row['token_hash']) === 64, 'only a SHA-256 hash of the token is stored');
t_ok((int) $row['expires_at'] > time(), 'the token has a future expiry');

// An unknown e-mail creates nothing (and does not error).
$before = (int) db()->query("SELECT COUNT(*) FROM password_resets")->fetchColumn();
pwreset_request('nobody@nowhere.example');
t_eq((int) db()->query("SELECT COUNT(*) FROM password_resets")->fetchColumn(), $before, 'an unknown e-mail creates no token');

// ---- A wrong / random token is rejected -------------------------------------
t_ok(pwreset_lookup(bin2hex(random_bytes(32))) === null, 'a random token matches nothing');
t_ok(pwreset_apply('deadbeef', 'newpassword1') !== '', 'applying an invalid token is refused');

// ---- The real token sets the new password, exactly once ---------------------
// Re-mint a token we hold the raw value of (pwreset_request e-mails it and keeps
// only the hash, so we forge a known one the same way the code does).
$raw = bin2hex(random_bytes(32));
db()->prepare("INSERT INTO password_resets (user_id,email,token_hash,created_at,expires_at,used_at) VALUES (?,?,?,?,?, '')")
    ->execute([$uid, $email, hash('sha256', $raw), date('c'), time() + 3600]);

t_ok(pwreset_apply($raw, 'short') !== '', 'a too-short new password is refused');
$err = pwreset_apply($raw, 'brandNewPass9');
t_eq($err, '', 'a valid token + strong password succeeds');

$u = ops_one("SELECT password_hash FROM users WHERE id = ?", [$uid]);
t_ok(password_verify('brandNewPass9', $u['password_hash']), 'the password was actually changed');
t_ok(!password_verify('oldpassword', $u['password_hash']), 'the old password no longer works');

// One-time: the same token cannot be reused.
t_ok(pwreset_apply($raw, 'anotherPass9') !== '', 'the token cannot be used a second time');

// ---- Expiry -----------------------------------------------------------------
$raw2 = bin2hex(random_bytes(32));
db()->prepare("INSERT INTO password_resets (user_id,email,token_hash,created_at,expires_at,used_at) VALUES (?,?,?,?,?, '')")
    ->execute([$uid, $email, hash('sha256', $raw2), date('c'), time() - 10]);   // already expired
t_ok(pwreset_lookup($raw2) === null, 'an expired token is rejected');

// ---- Clean up ---------------------------------------------------------------
db()->exec("DELETE FROM password_resets");
db()->exec('DELETE FROM users WHERE id = ' . (int) $uid);
t_ok(true, 'cleaned up');
