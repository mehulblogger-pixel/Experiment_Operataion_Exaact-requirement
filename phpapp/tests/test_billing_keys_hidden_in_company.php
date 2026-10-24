<?php
// Owner, 24 Oct 2026: "it should be with super admin only". Inside a company
// workspace its own Master Admin opens Users & billing and must not see the
// platform's "Pricing & payment keys" box or its Razorpay Key ID — the save was
// already refused there; now the box is not shown at all.
t_section('Users & billing — the payment keys box is the platform owner\'s only');

$prevUid = $_SESSION['uid'] ?? null; $prevT = $GLOBALS['__tenant'] ?? null;
$kid0 = (string) setting_get('rzp_key_id', ''); $sec0 = (string) setting_get('rzp_key_secret', '');
$render = function () {
    $cfg = billing_config(); $grant = []; $used = 0; $history = []; $self_managed = true; $lic = [];
    ob_start(); include __DIR__ . '/../views/ops/billing.php'; return (string) ob_get_clean();
};
try {
    setting_set('rzp_key_id', 'rzp_test_PlatformKey1'); setting_set('rzp_key_secret', 'platform-secret');
    $_SESSION['uid'] = (int) ops_val("SELECT id FROM users WHERE username='admin'"); current_user(true); ua(true);

    $GLOBALS['__tenant'] = ['key' => '', 'company' => '', 'error' => '', 'saas' => false, 'base' => ''];
    $h = $render();
    t_ok(strpos($h, 'name="rzp_key_id"') !== false, 'the platform owner sees the payment keys box');

    $GLOBALS['__tenant'] = ['key' => 'acme', 'company' => 'Acme', 'error' => '', 'saas' => true, 'base' => 'ops.example.test'];
    t_ok(is_master(), '(inside the company its owner is still its own Master Admin)');
    $h = $render();
    t_ok(strpos($h, 'name="rzp_key_id"') === false && strpos($h, 'Pricing &amp; payment keys') === false, 'a company\'s Master Admin does not see the box');
    t_ok(strpos($h, 'rzp_test_PlatformKey1') === false, '...nor the platform\'s Key ID anywhere on the page');
} finally {
    setting_set('rzp_key_id', $kid0); setting_set('rzp_key_secret', $sec0);
    if ($prevT === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $prevT;
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    current_user(true); ua(true);
}
