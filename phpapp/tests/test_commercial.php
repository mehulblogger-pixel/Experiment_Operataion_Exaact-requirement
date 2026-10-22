<?php
// ============================================================================
//  The platform's commercial numbers (owner, 22 Oct 2026: "research this number
//  and let me know or keep it open to configure in admin panel, don't hardcode
//  it"). No built-in price; suggestions shown, never used by themselves; GST
//  configurable, applied to the amount charged, with a numbered tax invoice.
// ============================================================================

t_section('Commercial numbers — nothing built in, suggestions, GST, tax invoice');

$keys = array_keys(commercial_catalogue());
$keep = [];
foreach (array_merge($keys, ['billing_currency', 'plat_invoice_prefix', 'company_gstin', 'company_name', 'company_address']) as $k) $keep[$k] = (string) setting_get($k, '');
foreach ($keys as $k) setting_set($k, '');
$seriesFile = rtrim((string) tenant_safe_data_dir()[0], '/') . '/invoice-series.json';
$seriesBak = is_file($seriesFile) ? file_get_contents($seriesFile) : null;
@unlink($seriesFile);

try {
    // ---- Nothing built in ---------------------------------------------------------
    $pb = saas_price_book();
    t_eq($pb['seat']['month'], 0, 'with nothing saved, a login has no price (not an invented ₹1,799)');
    t_ok(!array_filter($pb['modules'], fn($m) => $m['month'] > 0 || $m['year'] > 0), 'and no module has a price either');
    $mods = array_slice(array_keys($pb['modules']), 0, 2);
    $q = saas_company_quote($mods, 2, 'month');
    t_ok(count($q['missing']) === 3 && $q['total'] === 0, 'a quote names what is not priced (2 modules + logins) and charges nothing');
    $pq = saas_plan_change_quote($mods, 2, 'month');
    t_ok(!$pq['ok'] && strpos($pq['msg'], 'Not for sale yet') === 0, 'choosing an unpriced plan is refused: "Not for sale yet …"');
    t_ok(!commercial_prices_ready(), 'the go-live checklist says prices are not set');
    $items = array_column(platform_checklist(), 'ok', 'key');
    t_ok(isset($items['prices']) && !$items['prices'] && isset($items['gst']), 'the checklist has the prices and GST lines');
    t_eq(ai_pack_price(), 0, 'an AI top-up has no built-in price');

    // ---- Suggestions: shown with their reason, never used by themselves -------------
    $cat = commercial_catalogue();
    t_eq($cat['billing_price_user_month'][3], '499', 'a suggested login price is offered (₹499)');
    t_eq($cat['saas_yearly_discount_pct'][3], '20', 'a suggested yearly discount is offered (20%)');
    t_eq($cat['plat_gst_rate'][3], '18', 'a suggested GST rate is offered (18%)');
    t_ok(!array_filter($cat, fn($c) => $c[3] !== '' && trim($c[4]) === ''), 'every suggestion says why (evidence or judgement)');
    t_ok(count(commercial_unset()) >= 10, 'the unsaved numbers that have a suggestion are listed for the owner');
    t_ok(strpos(commercial_suggest_attr('saas_trial_seats'), 'data-suggest="10"') !== false && strpos(commercial_hint('saas_trial_seats'), 'Suggested: <b>10</b>') !== false,
        'a form box carries its suggestion and shows "Suggested: … · why"');

    // ---- Prices once saved; yearly: own price, discount, or not offered -------------
    $mk = $mods[0];
    setting_set('saas_price_mod_' . $mk . '_month', '999'); setting_set('billing_price_user_month', '499');
    t_ok(commercial_prices_ready(), 'one login price and one module price make plans sellable');
    t_eq(saas_price_book()['modules'][$mk]['year'], 0, 'no yearly price and no discount: yearly is not offered');
    $yq = saas_plan_change_quote([$mk], 1, 'year');
    t_ok(!$yq['ok'] && strpos($yq['msg'], 'choose monthly') !== false, 'paying yearly then is refused, pointing to monthly');
    setting_set('saas_yearly_discount_pct', '20');
    t_eq(saas_price_book()['modules'][$mk]['year'], 9590, 'a 20% yearly discount: ₹999 × 12 − 20% = ₹9,590');
    t_eq(saas_price_book()['seat']['year'], 4790, 'and ₹499 × 12 − 20% = ₹4,790 for a login');
    setting_set('saas_price_mod_' . $mk . '_year', '9000');
    t_eq(saas_price_book()['modules'][$mk]['year'], 9000, 'a yearly price the owner types wins over the discount');
    t_eq(billing_config()['price_year'], 4790, 'the login yearly price follows the same rule everywhere');
    $q = saas_company_quote([$mk], 2, 'month');
    t_ok(!$q['missing'] && $q['total'] === 999 + 2 * 499, 'a priced selection quotes in full');

    // ---- The pricing form: blank stays blank, GST checked before saving -------------
    $map = pricing_settings_map(['currency' => 'inr', 'seat_month' => '', 'mod_' . $mk . '_month' => '1200', 'plat_gstin' => ' 24abcde1234f1z5 ', '_has_plat_gst_enabled' => '1']);
    t_eq($map['billing_price_user_month'], '', 'a price box left blank is saved blank (= not set), never 0 or a default');
    t_eq($map['saas_price_mod_' . $mk . '_month'], '1200', 'a typed price is saved');
    t_eq($map['plat_gstin'], '24ABCDE1234F1Z5', 'a GSTIN is cleaned and upper-cased');
    t_eq($map['plat_gst_enabled'], '', 'an unticked "Charge GST" saves off');
    t_ok(pricing_settings_check(['plat_gstin' => '24ABC']) !== '', 'a malformed GSTIN is refused in plain words');
    t_ok(pricing_settings_check(['plat_gstin' => '99ABCDE1234F1Z5']) !== '', 'a GSTIN with no Indian state code is refused');
    $why = pricing_settings_check(['plat_gst_enabled' => '1', 'plat_gstin' => '24ABCDE1234F1Z5', 'plat_gst_rate' => '18']);
    t_ok(strpos($why, 'legal name') !== false && strpos($why, 'address') !== false, 'GST cannot be switched on without the legal name, address and SAC');

    // ---- GST arithmetic ---------------------------------------------------------------
    t_ok(!commercial_tax(1000)['on'] && commercial_tax(1000)['gross'] === 1000, 'GST off: the price is what is charged');
    foreach (['plat_gst_enabled' => '1', 'plat_gst_rate' => '18', 'plat_gst_mode' => 'exclusive', 'plat_gst_sac' => '997331',
              'plat_gstin' => '24ABCDE1234F1Z5', 'plat_legal_name' => 'EXAACT Test Pvt Ltd', 'plat_address' => 'Ahmedabad'] as $k => $v) setting_set($k, $v);
    $t = commercial_tax(1000);
    t_ok($t['on'] && $t['tax'] === 180 && $t['gross'] === 1180 && $t['cgst'] === 90 && $t['sgst'] === 90 && $t['igst'] === 0,
        'GST before-tax prices, buyer without GSTIN: ₹1,000 + CGST ₹90 + SGST ₹90 = ₹1,180');
    $t = commercial_tax(1000, '27AAAAA0000A1Z5');
    t_ok($t['igst'] === 180 && $t['cgst'] === 0 && $t['pos'] === '27', 'a buyer registered in another state (Maharashtra) pays IGST ₹180');
    $t = commercial_tax(1000, '24AAAAA0000A1Z5');
    t_ok($t['cgst'] === 90 && $t['sgst'] === 90, 'a buyer in the same state pays CGST + SGST');
    t_eq(commercial_tax(999)['tax'], 180, 'tax is rounded to the rupee (₹179.82 → ₹180)');
    setting_set('plat_gst_mode', 'inclusive');
    $t = commercial_tax(1180);
    t_ok($t['gross'] === 1180 && $t['net'] === 1000 && $t['tax'] === 180, 'GST-inclusive prices: ₹1,180 charged is ₹1,000 + ₹180 GST');
    setting_set('plat_gst_mode', 'exclusive');
    setting_set('plat_gstin', '');
    t_ok(!commercial_tax(1000)['on'], 'without the platform\'s GSTIN no GST is charged, even if switched on');
    setting_set('plat_gstin', '24ABCDE1234F1Z5');

    // ---- Invoice numbers: one series, consecutive, per financial year -----------------
    t_eq(commercial_fy('2026-10-22'), '2026-27', 'October 2026 is in financial year 2026-27');
    t_eq(commercial_fy('2027-03-31'), '2026-27', '31 March still belongs to it');
    t_eq(commercial_fy('2027-04-01'), '2027-28', 'a new series starts on 1 April');
    $a = commercial_next_invoice_no('2026-10-22'); $b = commercial_next_invoice_no('2026-10-22');
    t_eq([$a, $b], ['EX/2026-27/0001', 'EX/2026-27/0002'], 'invoice numbers run consecutively');
    t_ok(strlen($a) <= 16, 'and fit the 16-character limit of Rule 46');
    t_eq(commercial_next_invoice_no('2027-04-02'), 'EX/2027-28/0001', 'and restart at 0001 each financial year');

    // ---- A paid order carries its GST and gets a tax invoice ---------------------------
    selfserve_migrate();
    setting_set('company_name', 'Buyer Co'); setting_set('company_address', 'Pune'); setting_set('company_gstin', '27AAAAA0000A1Z5');
    $buyer = saas_invoice_buyer();
    t_eq($buyer['gstin'], '27AAAAA0000A1Z5', 'the buyer on the invoice is the company\'s own profile, with its GSTIN');
    $tax = commercial_tax(1998, $buyer['gstin']);
    $oid = 'order_cm_' . getmypid();
    saas_order_create($oid, 'plan', [$mk], 2, 'month', $tax['gross'], [],
        $tax + ['buyer' => $buyer, 'supplier' => commercial_gst_cfg(), 'lines' => saas_company_quote([$mk], 2, 'month')['lines'], 'credit' => 0, 'price' => 1998]);
    $o = saas_order_claim($oid, 'pay_cm_' . getmypid());
    t_ok($o && (int) $o['amount'] === 2358, 'the order records what is charged: ₹1,998 + 18% = ₹2,358');
    t_ok($o && preg_match('#^EX/\d{4}-\d{2}/\d{4}$#', (string) $o['invoice_no']), 'a paid order with GST gets the next tax-invoice number (' . ($o['invoice_no'] ?? '') . ')');
    $t = json_decode((string) $o['tax_json'], true);
    ob_start(); include dirname(__DIR__) . '/views/ops/subscription_invoice.php'; $html = ob_get_clean();
    foreach (['Tax invoice', $o['invoice_no'], '24ABCDE1234F1Z5', '27AAAAA0000A1Z5', 'Maharashtra (27)', '997331', 'IGST @ 18%', '₹2,358', 'reverse charge: No'] as $needle)
        t_ok(strpos($html, (string) $needle) !== false, 'the tax invoice shows "' . $needle . '"');
    t_eq(saas_order_claim($oid, 'pay_again'), null, 'a replayed payment changes nothing and takes no second number');

    // ---- What every company receives -------------------------------------------------
    $push = selfserve_sell_settings();
    foreach (['plat_gst_enabled', 'plat_gst_rate', 'plat_gstin', 'saas_yearly_discount_pct', 'plat_invoice_prefix'] as $k)
        t_ok(array_key_exists($k, $push), 'companies are sent "' . $k . '" so their own checkout prices and taxes correctly');

    // ---- Console plans: logins editable; until saved, the long-standing numbers stay -----
    setting_set('saas_plan_logins_PRO', '');
    t_eq(saas_plan_logins('PRO'), 5, 'with nothing saved, a console plan keeps the logins it always had (no company changes overnight)');
    setting_set('saas_plan_logins_PRO', '7');
    t_eq(saas_plan_logins('PRO'), 7, 'a saved number takes over');
    setting_set('saas_plan_logins_PRO', '');
} finally {
    foreach ($keep as $k => $v) setting_set($k, $v);
    if ($seriesBak !== null) file_put_contents($seriesFile, $seriesBak); else @unlink($seriesFile);
}
