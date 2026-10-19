<?php
// ============================================================================
//  A TEST COPY of the live site sends no e-mail and says so on every page.
//  The copy holds real customers' addresses and, after a database import, the
//  live site's mail-server settings, so this switch lives in the copy's own
//  config.local.php and is in force before the copy is first opened.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('test copy — no e-mail ever leaves it');
$prev = $GLOBALS['APP_TEST_COPY'] ?? null;
$h0 = setting_get('smtp_host', '');
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    unset($GLOBALS['APP_TEST_COPY']);
    t_ok(!app_is_test_copy(), 'a normal install is not a test copy');
    // A mail server configured, as the live data would carry it into the copy.
    setting_set('smtp_host', 'smtp.invalid.example');
    $GLOBALS['APP_TEST_COPY'] = true;
    t_ok(app_is_test_copy(), "config.local.php 'test_copy' => true marks the install as a test copy");
    $ok = ops_mail('customer@example.com', 'Your invoice is overdue', 'body', '', 'TEST');
    t_eq($ok, 0, 'ops_mail sends nothing on a test copy, even with a mail server configured');
    $log = ops_one("SELECT sent_ok, error FROM email_log WHERE to_addr='customer@example.com' ORDER BY id DESC LIMIT 1");
    t_ok($log && (int)$log['sent_ok'] === 0 && strpos((string)$log['error'], 'test copy') !== false, 'the attempt is logged as "not sent: this is a test copy"');
    $cfg = file_get_contents(__DIR__ . '/../config.php');
    t_ok(strpos($cfg, "\$local['test_copy']") !== false, 'config.php reads the switch from config.local.php');
    t_ok(strpos(file_get_contents(__DIR__ . '/../config.local.sample.php'), "'test_copy' => true") !== false, 'the sample settings file documents it, commented out');
    $lay = file_get_contents(__DIR__ . '/../views/layout_top.php');
    t_ok(strpos($lay, 'app_is_test_copy()') !== false && strpos($lay, 'TEST COPY — not the live system') !== false, 'every staff page carries a TEST COPY banner');
} finally {
    if ($prev === null) unset($GLOBALS['APP_TEST_COPY']); else $GLOBALS['APP_TEST_COPY'] = $prev;
    setting_set('smtp_host', $h0);
    if ($own && db()->inTransaction()) db()->rollBack();
}
