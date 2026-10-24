<?php
// Five wrong passwords rest the account for 15 minutes. A try made DURING that
// rest must be refused without restarting it — otherwise a person who keeps
// trying (often with the right password, after a typo) is never let back in.
t_section('sign-in lock: tries during the lock do not restart it');

$who = 'lock-test-' . bin2hex(random_bytes(3));
login_clear($who);
for ($i = 1; $i < LOGIN_MAX_TRIES; $i++) login_fail($who);
t_eq(login_locked_for($who), 0, (LOGIN_MAX_TRIES - 1) . ' wrong passwords do not lock yet');
$wait = login_fail($who);
t_eq($wait, LOGIN_LOCK_MIN, LOGIN_MAX_TRIES . ' wrong passwords rest the account for ' . LOGIN_LOCK_MIN . ' minutes');

// Pretend the lock started 10 minutes ago, then try again three times.
$tenAgo = date('c', time() - 600);
db()->prepare("UPDATE login_attempts SET last_at=? WHERE username=?")->execute([$tenAgo, $who]);
for ($i = 0; $i < 3; $i++) $wait = login_fail($who);
t_eq($wait, LOGIN_LOCK_MIN - 10, 'trying again during the lock leaves ' . (LOGIN_LOCK_MIN - 10) . ' minutes, not a fresh ' . LOGIN_LOCK_MIN);
t_eq((string) ops_val("SELECT last_at FROM login_attempts WHERE username=?", [$who]), $tenAgo, '...and the lock start is unchanged');

// Once it has run its course, the count starts again from one.
db()->prepare("UPDATE login_attempts SET last_at=? WHERE username=?")->execute([date('c', time() - 16 * 60), $who]);
t_eq(login_locked_for($who), 0, 'after ' . LOGIN_LOCK_MIN . ' minutes the account may try again');
login_fail($who);
t_eq((int) ops_val("SELECT tries FROM login_attempts WHERE username=?", [$who]), 1, 'a wrong password after the rest counts as the first of five again');
login_clear($who);
