<?php
// ============================================================================
//  A TEST COPY of the live site sends no e-mail and says so on every page.
//  The copy holds real customers' addresses and, after a database import, the
//  live site's mail-server settings, so this switch lives in the copy's own
//  config.local.php and is in force before the copy is first opened.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('test copy — no e-mail to customers, its own data folder, no real money');
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
    // The testers' own inboxes — and only those — may receive mail from a test copy.
    $prevList = $GLOBALS['APP_TEST_MAIL_TO'] ?? null;
    $GLOBALS['APP_TEST_MAIL_TO'] = ['owner@mytest.example', '*@myco.example'];
    t_ok(app_test_mail_allowed('owner@mytest.example') && app_test_mail_allowed('Priya@MyCo.example'), 'an address on the test_mail_to list (exact or *@domain) may receive mail');
    t_ok(!app_test_mail_allowed('customer@example.com'), 'a customer address may not');
    t_ok(!app_test_mail_allowed('owner@mytest.example', 'customer@example.com'), 'nor a message that also copies a customer');
    ops_mail('customer@example.com', 'Reminder', 'body', '', 'TEST');
    $log = ops_one("SELECT sent_ok, error FROM email_log WHERE to_addr='customer@example.com' ORDER BY id DESC LIMIT 1");
    t_ok($log && (int) $log['sent_ok'] === 0 && strpos((string) $log['error'], 'test_mail_to') !== false, 'a blocked one says it is not on the list');
    $GLOBALS['__mail_capture'] = []; $cap = &$GLOBALS['__mail_capture'];
    ops_mail('owner@mytest.example', 'Your sign-up code', 'body', '', 'TEST');
    $log = ops_one("SELECT subject, error FROM email_log WHERE to_addr='owner@mytest.example' ORDER BY id DESC LIMIT 1");
    t_ok($log && strpos((string) $log['subject'], '[TEST COPY] ') === 0 && strpos((string) $log['error'], 'not sent: this is a test copy') === false,
        'an allowed one goes to the mail server, its subject marked [TEST COPY]');
    unset($GLOBALS['__mail_capture']);
    if ($prevList === null) unset($GLOBALS['APP_TEST_MAIL_TO']); else $GLOBALS['APP_TEST_MAIL_TO'] = $prevList;

    // One inbox for everything (Platform setup on a test copy): no settings file to edit.
    $keepR = (string) setting_get('test_mail_redirect', '');
    setting_set('test_mail_redirect', 'tester@mytest.example');
    t_eq(app_test_mail_redirect(), 'tester@mytest.example', 'a test copy can send every e-mail to one tester inbox');
    ops_mail('customer@example.com', 'Your sign-up code', 'body', 'boss@example.com', 'TEST');
    $log = ops_one("SELECT to_addr, subject FROM email_log ORDER BY id DESC LIMIT 1");
    t_ok($log && $log['to_addr'] === 'tester@mytest.example' && strpos((string) $log['subject'], '[TEST COPY → customer@example.com] ') === 0,
        'it goes to the tester, never the customer, and says who it was meant for');
    unset($GLOBALS['APP_TEST_COPY']);
    t_eq(app_test_mail_redirect(), '', 'on a live site the setting does nothing');
    $GLOBALS['APP_TEST_COPY'] = true;
    setting_set('test_mail_redirect', $keepR);

    // Its own data folder: never the live site's invoice numbers, platform e-mail, backups or company files.
    $prevDir = $GLOBALS['APP_DATA_DIR'] ?? null; unset($GLOBALS['APP_DATA_DIR']);
    [$d] = tenant_safe_data_dir();
    t_ok(basename($d) === 'exaact_data_test' || basename($d) === 'data_test', 'a test copy keeps its own data folder (' . basename($d) . '), not the live "exaact_data"');
    $GLOBALS['APP_DATA_DIR'] = sys_get_temp_dir() . '/exaact_tc_' . getmypid();
    t_eq(tenant_safe_data_dir()[0], $GLOBALS['APP_DATA_DIR'], "config.local.php 'data_dir' chooses the folder exactly");
    @unlink($GLOBALS['APP_DATA_DIR'] . '/.htaccess'); @rmdir($GLOBALS['APP_DATA_DIR']);
    if ($prevDir === null) unset($GLOBALS['APP_DATA_DIR']); else $GLOBALS['APP_DATA_DIR'] = $prevDir;
    if (basename($d) === 'exaact_data_test') { @unlink($d . '/.htaccess'); @rmdir($d); }

    // …and its own backups folder, never the live site's.
    $keepBd = (string) setting_get('backup_dir', ''); setting_set('backup_dir', '');
    unset($GLOBALS['APP_DATA_DIR']);
    [$bd] = backup_root();
    t_ok(basename($bd) === 'exaact_backups_test' || $bd === '', 'a test copy keeps its own backups folder (exaact_backups_test), never the live "exaact_backups"');
    if (basename($bd) === 'exaact_backups_test') { @unlink($bd . '/.htaccess'); @rmdir($bd); }
    $GLOBALS['APP_DATA_DIR'] = sys_get_temp_dir() . '/exaact_tcb_' . getmypid();
    t_eq(backup_root()[0], $GLOBALS['APP_DATA_DIR'] . '/backups', "with config.local.php 'data_dir', backups go inside that folder");
    @unlink($GLOBALS['APP_DATA_DIR'] . '/backups/.htaccess'); @rmdir($GLOBALS['APP_DATA_DIR'] . '/backups'); @rmdir($GLOBALS['APP_DATA_DIR']);
    if ($prevDir === null) unset($GLOBALS['APP_DATA_DIR']); else $GLOBALS['APP_DATA_DIR'] = $prevDir;
    setting_set('backup_dir', $keepBd);

    // No real money on a test copy.
    $kid = (string) setting_get('rzp_key_id', ''); $ks = (string) setting_get('rzp_key_secret', '');
    setting_set('rzp_key_id', 'rzp_live_XXXXXXXX'); setting_set('rzp_key_secret', 'x');
    $r = rzp_create_order(10000, 'tc');
    t_ok(empty($r['ok']) && strpos((string) $r['error'], 'TEST keys') !== false, 'with LIVE Razorpay keys a test copy refuses to start a payment');
    setting_set('rzp_key_id', $kid); setting_set('rzp_key_secret', $ks);

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
