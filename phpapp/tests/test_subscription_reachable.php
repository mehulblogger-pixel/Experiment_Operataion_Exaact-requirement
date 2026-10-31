<?php
// Owner, 31 Oct 2026 (test book ONB-046, ONB-047): "there is no way to access the
// subscription directly" and "the company has no option to check which
// subscription is active … and how many users are left". The Admin tile needed
// a seat limit, which a free-trial company does not have yet.
t_section('Your subscription — reachable from Admin, and it says what the plan is');
$keep = [(string) setting_get('saas_seat_limit', ''), (string) setting_get('saas_billing_mode', '')];
$prevUid = $_SESSION['uid'] ?? null;
try {
    $_SESSION['uid'] = (int) ops_val("SELECT id FROM users WHERE username='admin'"); current_user(true); ua(true);
    setting_set('saas_seat_limit', '0');
    $tiles = function () { $o = []; foreach (ops_area_def('admin')['sections'] as $sec) foreach ($sec['tiles'] as $t) $o[$t['route']] = $t['label']; return $o; };
    t_ok(!isset($tiles()['/subscription']), 'a plain install (no plan, not a sign-up) has no subscription tile');
    setting_set('saas_billing_mode', 'self');
    t_ok(function_exists('saas_is_selfserve') && saas_is_selfserve(), '(this install now looks like a company that signed itself up)');
    t_eq($tiles()['/subscription'] ?? '', 'Your subscription', 'a self sign-up company on its trial (no seat limit yet) has Admin → "Your subscription"');
    $labels = array_column(nav_trail('subscription'), 'label');
    t_eq($labels, ['Home', 'Admin', 'Your subscription'], 'its trail is Home › Admin › Your subscription');
    $v = (string) file_get_contents(__DIR__ . '/../views/ops/subscription.php');
    t_ok(strpos($v, ' used\' : $used . \' in use\'') !== false && strpos($v, "' left' : 'no limit during the trial'") !== false, 'the page shows logins used and left');
    t_ok(strpos($v, 'Every module in it ends on that same day.') !== false, 'and, on a paid plan, its modules, logins, period and the one end date');
} finally {
    setting_set('saas_seat_limit', $keep[0]); setting_set('saas_billing_mode', $keep[1]);
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    current_user(true); ua(true);
}
