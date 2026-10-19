<?php
// ============================================================================
//  MGH Licence Server — the guards behind a well-built webhook.
//
//  The webhook itself (licence-server/api.php) verifies an HMAC over the raw
//  body with a constant-time compare before parsing anything. These are the
//  controls BEHIND that door, which had no test at all:
//
//   1. REPLAY. Razorpay retries, so one payment arrives twice. The guard was a
//      COUNT then an INSERT with nothing atomic between them and no UNIQUE on
//      ledger.reference; two overlapping deliveries could both pass and hand
//      out a free year. Now the ledger row carries a UNIQUE index, is written
//      FIRST, and shares a transaction with the expiry update -- because the
//      first version of the fix moved the expiry and THEN was refused the
//      ledger row, which is worse than no guard: a free year with no record.
//   2. TERM. months came from the payment's notes with no upper bound: Rs 1
//      with notes.months=1200 moved an expiry to the year 2126.
//   3. SIGN-IN. Unlimited password guesses against the console that mints
//      every customer's licence; and the session started with PHP's default
//      cookie flags in four separate places.
//
//  The library is the REAL one, run against a temp store. LICENCE_STORE is set
//  before the require because on the command line the library would otherwise
//  put its database one level above itself -- inside this repository.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('licence server — replay, term, sign-in');

$LS_TMP = sys_get_temp_dir() . '/ls-test-' . bin2hex(random_bytes(4));
mkdir($LS_TMP, 0700, true);
putenv('LICENCE_STORE=' . $LS_TMP);
require_once __DIR__ . '/../../licence-server/lib.php';

try {
    $db = ls_db();
    $db->exec("INSERT INTO customers (name, created_at) VALUES ('Test Co', '" . ls_now() . "')");
    $cid = (int)$db->lastInsertId();
    $mk = function ($expires) use ($db, $cid) {
        $iid = ls_uuid();
        $db->prepare("INSERT INTO installs (customer_id, install_id, expires_on, created_at) VALUES (?,?,?,?)")
           ->execute([$cid, $iid, $expires, ls_now()]);
        return $iid;
    };

    // ---- 1. replay -------------------------------------------------------
    $iid = $mk('2030-01-01');
    $r1 = ls_extend($iid, 12, 'PAYMENT', 1000, 'pay_REPLAY', 'first');
    t_eq($r1['now'] ?? '', '2031-01-01', 'the first delivery extends by a year');
    $threw = false; $dup = false;
    try { ls_extend($iid, 12, 'PAYMENT', 1000, 'pay_REPLAY', 'second'); }
    catch (Throwable $e) { $threw = true; $dup = ls_is_duplicate_payment($e); }
    t_ok($threw, 'the second delivery of the same reference is refused by the database');
    t_ok($dup, '...and ls_is_duplicate_payment() recognises that refusal, so api.php answers "duplicate"');
    t_eq((string)ls_install($iid)['expires_on'], '2031-01-01',
         'THE EXPIRY MOVED EXACTLY ONCE — the refused ledger row rolled the second extension back');
    t_eq((int)$db->query("SELECT COUNT(*) FROM ledger WHERE reference='pay_REPLAY'")->fetchColumn(), 1,
         'one ledger row for one payment');
    // MANUAL rows carry no reference and must not collide with each other
    ls_extend($iid, 1, 'MANUAL', 0, '', 'by hand');
    ls_extend($iid, 1, 'MANUAL', 0, '', 'by hand again');
    t_eq((int)$db->query("SELECT COUNT(*) FROM ledger WHERE kind='MANUAL' AND install_id='$iid'")->fetchColumn(), 2,
         'two manual extensions with a blank reference are both recorded — the index is partial');

    // ---- 2. term ---------------------------------------------------------
    $iid2 = $mk(date('Y-m-d'));
    $r = ls_extend($iid2, 1200, 'PAYMENT', 1, 'pay_TYPO', 'notes.months=1200');
    t_ok(!empty($r['err']), 'Rs 1 with months=1200 is refused: ' . ($r['err'] ?? ''));
    t_eq((string)ls_install($iid2)['expires_on'], date('Y-m-d'), '...and nothing moved');
    $r = ls_extend($iid2, LS_MAX_MONTHS, 'PAYMENT', 1, 'pay_MAX', 'at the cap');
    t_ok(empty($r['err']), 'exactly ' . LS_MAX_MONTHS . ' months is still allowed — the cap is a ceiling, not a trap');
    $r = ls_extend($iid2, 0, 'PAYMENT', 1, 'pay_ZERO', '');
    t_ok(!empty($r['err']), 'zero months is still refused');

    // ---- 3. sign-in throttle --------------------------------------------
    // Needs a config file to hold the counters; create a minimal one.
    ls_config_save(['admin_hash' => password_hash('x', PASSWORD_DEFAULT), 'razorpay_secret' => '']);
    ls_config_reset();
    t_eq(ls_login_locked_for(), 0, 'no lock before any failure');
    for ($i = 1; $i < LS_LOGIN_MAX_FAILS; $i++) ls_login_failed();
    t_eq(ls_login_locked_for(), 0, (LS_LOGIN_MAX_FAILS - 1) . ' failures do not lock yet');
    ls_login_failed();
    $wait = ls_login_locked_for();
    t_ok($wait > 0 && $wait <= LS_LOGIN_LOCK_SECS, LS_LOGIN_MAX_FAILS . ' failures lock the console for ' . $wait . 's');
    ls_login_succeeded();
    t_eq(ls_login_locked_for(), 0, 'a successful sign-in clears the lock and the counter');
    $c = ls_config();
    t_ok(empty($c['login_fails']) && empty($c['login_lock_until']), '...and leaves no counters behind in the config');

    // ---- 3b. cookie flags, and no stray session starts -------------------
    $lib = file_get_contents(__DIR__ . '/../../licence-server/lib.php');
    if (preg_match('/function ls_session\(\)\s*\{(.*?)\n\}/s', $lib, $m)) {
        foreach (["'httponly' => true", "'samesite' => 'Lax'", "'secure'"] as $flag)
            t_ok(strpos($m[1], $flag) !== false, 'ls_session() sets ' . $flag);
    } else t_ok(false, 'ls_session() exists');
    foreach (['index.php', 'api.php', 'lib.php'] as $f) {
        $src = file_get_contents(__DIR__ . '/../../licence-server/' . $f);
        $inline = preg_match_all("/session_name\('MGHLIC'\);\s*session_start\(\)/", $src);
        t_eq($inline, 0, $f . ' starts no session by hand — every start goes through ls_session()');
    }
    $idx = file_get_contents(__DIR__ . '/../../licence-server/index.php');
    t_ok(strpos($idx, 'ls_login_locked_for()') !== false && strpos($idx, 'ls_login_failed()') !== false,
         'the sign-in handler consults the throttle and records a miss');
} finally {
    foreach (array_merge(glob("$LS_TMP/*") ?: [], glob("$LS_TMP/.*") ?: []) as $f) if (is_file($f)) @unlink($f);
    @rmdir($LS_TMP);
}
