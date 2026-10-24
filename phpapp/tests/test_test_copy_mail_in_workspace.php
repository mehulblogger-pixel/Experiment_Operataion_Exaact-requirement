<?php
// Owner, 24 Oct 2026: "Password reset for workspace does not send the reset link".
// On a test copy every e-mail goes to ONE inbox. That inbox was stored only in the
// main site's settings, so an e-mail sent from inside a company workspace (which
// has its own database) found no inbox and was BLOCKED. It now lives in the
// platform file every workspace reads.
t_section('Test copy — e-mail sent from inside a company still reaches the test inbox');

$f = platform_mail_file(); $had = is_file($f); $bak = $had ? file_get_contents($f) : null;
$prevCopy = $GLOBALS['APP_TEST_COPY'] ?? null; $prevSet = (string) setting_get('test_mail_redirect', '');
$prevT = $GLOBALS['__tenant'] ?? null;
try {
    $GLOBALS['APP_TEST_COPY'] = true;
    $main = ['key' => '', 'company' => '', 'error' => '', 'saas' => false, 'base' => ''];
    $acme = ['key' => 'acme', 'company' => 'Acme', 'error' => '', 'saas' => true, 'base' => 'ops.example.test'];

    // 1. An inbox saved on the main site (also one saved before this change) is
    //    copied to the platform file the first time the main site looks at it.
    $GLOBALS['__tenant'] = $main;
    platform_mail_set_test_redirect('');
    setting_set('test_mail_redirect', 'Owner@Inbox.example');
    t_eq(app_test_mail_redirect(), 'owner@inbox.example', 'the main site uses its own saved inbox');
    t_eq((string) (platform_mail_get()['test_redirect'] ?? ''), 'owner@inbox.example', '…and keeps a copy in the platform file');

    // 2. Inside a company: its own settings do not have it — the file answers.
    $GLOBALS['__tenant'] = $acme;
    t_eq(app_test_mail_redirect(), 'owner@inbox.example', 'inside a company workspace the test inbox is still found');
    $got = null;
    $GLOBALS['__mail_capture'] = function ($to, $subject) use (&$got) { $got = [$to, $subject]; };
    ops_mail('person@acme.example', 'Acme — reset your password', 'link', '', 'password_reset');
    unset($GLOBALS['__mail_capture']);
    t_ok(is_array($got) && $got[0] === 'owner@inbox.example' && strpos($got[1], '[TEST COPY → person@acme.example]') === 0,
         'a password-reset e-mail from inside the company goes to the test inbox, saying who it was for');

    // 3. Clearing it on the main site clears it for the companies too.
    $GLOBALS['__tenant'] = $main;
    setting_set('test_mail_redirect', '');
    t_eq(app_test_mail_redirect(), '', 'cleared on the main site');
    $GLOBALS['__tenant'] = $acme;
    t_eq(app_test_mail_redirect(), '', '…so a company no longer has an inbox either (nothing is sent)');
    $GLOBALS['__tenant'] = $main;

    // The Platform setup save writes the file at once.
    t_ok(strpos((string) file_get_contents(__DIR__ . '/../lib/platform_mail.php'), 'platform_mail_set_test_redirect($a);') !== false,
         'saving the inbox on Platform setup also writes the platform file');

    // 4. Never on a live site.
    unset($GLOBALS['APP_TEST_COPY']);
    t_eq(app_test_mail_redirect(), '', 'on a live site there is no redirect at all');
} finally {
    if ($had) file_put_contents($f, $bak); else @unlink($f);
    setting_set('test_mail_redirect', $prevSet);
    if ($prevCopy === null) unset($GLOBALS['APP_TEST_COPY']); else $GLOBALS['APP_TEST_COPY'] = $prevCopy;
    if ($prevT === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $prevT;
}
