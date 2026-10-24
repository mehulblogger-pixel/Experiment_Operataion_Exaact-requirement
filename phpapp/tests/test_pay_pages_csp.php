<?php
// Every page that opens the Razorpay window must be on index.php's $payRoutes
// list, or the browser blocks Razorpay's script and the customer sees "Could
// not open the payment window. Please reload." (owner, test book PLT-005,
// 24 Oct 2026: the company's own plan checkout was missing).
t_section('Payment pages — the Razorpay window is allowed on every one of them');

$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(preg_match('/\$payRoutes\s*=\s*\[([^\]]*)\]/', $idx, $m) === 1, 'index.php lists the payment pages in one place');
preg_match_all("/'([^']+)'/", $m[1] ?? '', $mm);
$listed = $mm[1] ?? [];

// The routes whose handler shows a checkout (an order is created, then the
// Razorpay window is opened). Found by reading where each checkout is drawn.
$mustBe = [
    'billing-order'      => ['lib/billing.php', "view('ops/billing_pay'"],
    'subscription-order' => ['lib/saas_tenants.php', "view('ops/billing_pay'"],
    'ai-topup-order'     => ['lib/pricing_admin.php', "view('ops/ai_topup_pay'"],
    'buy'                => ['lib/licenceissue.php', 'buy_pay_shell($a'],
    'portal/pay'         => ['lib/portal.php', "mkt_pay_render_checkout(\$row, '/portal/pay'"],
    'pro/pay'            => ['lib/connect_pro.php', "mkt_pay_render_checkout(\$row, '/pro/pay'"],
];
foreach ($mustBe as $route => [$file, $needle]) {
    t_ok(strpos((string) file_get_contents(__DIR__ . '/../' . $file), $needle) !== false, "$route draws a checkout ($file)");
    t_ok(in_array($route, $listed, true), "...and is on the payment-pages list");
}

// No other place draws a checkout without being accounted for above.
$draws = 0;
foreach (glob(__DIR__ . '/../lib/*.php') as $f) {
    $src = (string) file_get_contents($f);
    $draws += substr_count($src, "view('ops/billing_pay'") + substr_count($src, "view('ops/ai_topup_pay'")
            + preg_match_all('/mkt_pay_render_checkout\(\$row/', $src) + preg_match_all('/(?<!function )buy_pay_shell\(\$a/', $src);
}
t_eq($draws, count($mustBe), 'every checkout drawn anywhere is one of the pages above');
t_ok(strpos($idx, "'subscription-order'") !== false, "the company's own plan checkout (subscription-order) is allowed");
