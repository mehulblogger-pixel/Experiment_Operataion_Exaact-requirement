<?php
// On a test copy with one inbox set on Platform setup, the red bar and every
// "Test e-mail sent to …" message name THAT inbox — never "No e-mail is sent"
// and never the address the message was meant for, which did not receive it.
t_section('test copy — the bar and "sent to" name the inbox that really gets the mail');
$prevCopy = $GLOBALS['APP_TEST_COPY'] ?? null;
$prevList = $GLOBALS['APP_TEST_MAIL_TO'] ?? null;
$prevRedir = setting_get('test_mail_redirect', '');
try {
    $GLOBALS['APP_TEST_COPY'] = true; unset($GLOBALS['APP_TEST_MAIL_TO']);
    setting_set('test_mail_redirect', '');
    t_eq(app_test_mail_banner(), 'No e-mail is sent from here.', 'no inbox set: the bar says nothing is sent');
    t_eq(app_test_mail_lands('admin@example.test'), 'admin@example.test', '...and "sent to" names the address itself');

    $GLOBALS['APP_TEST_MAIL_TO'] = ['*@myco.example'];
    t_eq(app_test_mail_banner(), "E-mail goes only to the testers' own addresses.", 'a testers list: the bar says so');
    unset($GLOBALS['APP_TEST_MAIL_TO']);

    setting_set('test_mail_redirect', 'Owner@Inbox.example');
    t_eq(app_test_mail_banner(), 'Every e-mail goes only to owner@inbox.example.', 'one inbox set: the bar names it');
    t_eq(app_test_mail_lands('admin@example.test'), 'owner@inbox.example (meant for admin@example.test)', '...and "sent to" names it, with who it was meant for');

    unset($GLOBALS['APP_TEST_COPY']);
    t_eq(app_test_mail_lands('admin@example.test'), 'admin@example.test', 'on a normal install the setting is ignored');

    $src = file_get_contents(__DIR__ . '/../views/layout_top.php') . file_get_contents(__DIR__ . '/../lib/platform_mail.php');
    t_ok(strpos($src, 'app_test_mail_banner()') !== false && strpos($src, "'sent to ' . app_test_mail_lands(\$to)") !== false,
        'the red bar and the Platform setup test both use these');
} finally {
    setting_set('test_mail_redirect', (string) $prevRedir);
    if ($prevCopy === null) unset($GLOBALS['APP_TEST_COPY']); else $GLOBALS['APP_TEST_COPY'] = $prevCopy;
    if ($prevList === null) unset($GLOBALS['APP_TEST_MAIL_TO']); else $GLOBALS['APP_TEST_MAIL_TO'] = $prevList;
}
