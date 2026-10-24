<?php
// The platform owner enters the Razorpay keys on Platform setup itself (owner,
// 24 Oct 2026: "direct access to billing under Super Admin is not shown").
// Same rules as Users & billing: the Master Admin only, never inside a company
// workspace, and a blank secret keeps the saved one.
t_section('Platform setup — Razorpay keys entered right there');

$prevUid = $_SESSION['uid'] ?? null;
$kid0 = (string) setting_get('rzp_key_id', ''); $sec0 = (string) setting_get('rzp_key_secret', '');
try {
    $admin = (int) ops_val("SELECT id FROM users WHERE username='admin'");
    $_SESSION['uid'] = $admin; current_user(true); ua(true);
    t_eq(billing_keys_refusal(), '', 'the Master Admin may change the keys');

    billing_keys_store('rzp_test_AbC123', 'secret-one');
    t_eq((string) setting_get('rzp_key_id', ''), 'rzp_test_AbC123', 'the Key ID is saved');
    billing_keys_store('rzp_test_AbC123', '   ');
    t_eq((string) setting_get('rzp_key_secret', ''), 'secret-one', 'a blank secret keeps the saved one');
    t_ok(billing_configured(), 'and payment counts as set up');

    $other = (int) ops_val("SELECT u.id FROM users u WHERE u.username <> 'admin' AND u.is_active=1 ORDER BY u.id LIMIT 1");
    if ($other) {
        $_SESSION['uid'] = $other; current_user(true); ua(true);
        if (!is_master()) t_ok(billing_keys_refusal() !== '', 'anyone else is refused');
    }

    $view = file_get_contents(__DIR__ . '/../views/ops/platform_setup.php');
    t_ok(strpos($view, 'name="do" value="pay_keys"') !== false && strpos($view, 'name="rzp_key_id"') !== false
        && strpos($view, 'name="rzp_key_secret"') !== false, 'Platform setup has the Key ID and Key Secret boxes and a save button');
    t_ok(strpos($view, 'Enter them under <a href="/billing">') === false, 'and no longer sends the owner to another screen for them');
    $h = file_get_contents(__DIR__ . '/../lib/platform_mail.php');
    t_ok(strpos($h, "\$do === 'pay_keys'") !== false && strpos($h, 'billing_keys_refusal()') !== false,
        'its save goes through the same rules as Users & billing');
} finally {
    setting_set('rzp_key_id', $kid0); setting_set('rzp_key_secret', $sec0);
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    current_user(true); ua(true);
}
