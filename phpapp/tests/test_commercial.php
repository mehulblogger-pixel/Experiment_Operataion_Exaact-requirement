<?php
// ============================================================================
//  The platform's commercial numbers (owner, 22 Oct 2026: "research this number
//  and let me know or keep it open to configure in admin panel, don't hardcode
//  it"). No built-in price; suggestions shown, never used by themselves; GST
//  configurable, applied to the amount charged, with a numbered tax invoice.
// ============================================================================

t_section('Commercial numbers — nothing built in, suggestions, GST, tax invoice');

$gst = fn($first14) => $first14 . commercial_gstin_check_char($first14);   // a GSTIN with a correct check character
$PLAT = $gst('24AAACE1234F1Z'); $MH = $gst('27AAAAA0000A1Z'); $GJ = $gst('24AAAAA0000A1Z');
$keys = array_keys(commercial_catalogue());
$keep = [];
foreach (array_merge($keys, ['billing_currency', 'plat_invoice_prefix', 'company_gstin', 'company_name', 'company_address', 'company_legal_name', 'company_state', 'company_pan']) as $k) $keep[$k] = (string) setting_get($k, '');
foreach ($keys as $k) setting_set($k, '');
$seriesFile = rtrim((string) tenant_safe_data_dir()[0], '/') . '/invoice-series.json';
$seriesBak = is_file($seriesFile) ? file_get_contents($seriesFile) : null;
@unlink($seriesFile);
$regFile = commercial_register_file(); $regBak = is_file($regFile) ? file_get_contents($regFile) : null;
@unlink($regFile);

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
    $map = pricing_settings_map(['currency' => 'inr', 'seat_month' => '', 'mod_' . $mk . '_month' => '1200', 'plat_gstin' => ' ' . strtolower($PLAT) . ' ', '_has_plat_gst_enabled' => '1']);
    t_eq($map['billing_price_user_month'], '', 'a price box left blank is saved blank (= not set), never 0 or a default');
    t_eq($map['saas_price_mod_' . $mk . '_month'], '1200', 'a typed price is saved');
    t_eq($map['plat_gstin'], $PLAT, 'a GSTIN is cleaned and upper-cased');
    t_eq(commercial_gstin_problem('27AAPFU0939F1ZV'), '', 'a published sample GSTIN passes the check-character test');
    t_ok(strpos(commercial_gstin_problem('27AAPFU0939F1ZW'), 'typing mistake') !== false, 'one wrong character is caught as a typing mistake');
    t_ok(!commercial_sac_ok('99731') && commercial_sac_ok('997331'), 'a SAC must be six digits beginning 99: "99731" is refused, 997331 accepted');
    t_ok(strpos(pricing_settings_check(['plat_gst_sac' => '99731']), 'six digits') !== false, 'the Pricing screen refuses a 5-digit SAC in plain words');
    t_eq($map['plat_gst_enabled'], '', 'an unticked "Charge GST" saves off');
    t_ok(pricing_settings_check(['plat_gstin' => '24ABC']) !== '', 'a malformed GSTIN is refused in plain words');
    t_ok(pricing_settings_check(['plat_gstin' => '99ABCDE1234F1Z5']) !== '', 'a GSTIN with no Indian state code is refused');
    $why = pricing_settings_check(['plat_gst_enabled' => '1', 'plat_gstin' => $PLAT, 'plat_gst_rate' => '18']);
    t_ok(strpos($why, 'legal name') !== false && strpos($why, 'address') !== false, 'GST cannot be switched on without the legal name, address and SAC');

    // ---- GST arithmetic ---------------------------------------------------------------
    t_ok(!commercial_tax(1000)['on'] && commercial_tax(1000)['gross'] === 1000, 'GST off: the price is what is charged');
    foreach (['plat_gst_enabled' => '1', 'plat_gst_rate' => '18', 'plat_gst_mode' => 'exclusive', 'plat_gst_sac' => '997331',
              'plat_gstin' => $PLAT, 'plat_legal_name' => 'EXAACT Test Pvt Ltd', 'plat_address' => 'Ahmedabad'] as $k => $v) setting_set($k, $v);
    $t = commercial_tax(1000);
    t_ok($t['on'] && $t['tax'] === 180 && $t['gross'] === 1180 && $t['cgst'] === 90 && $t['sgst'] === 90 && $t['igst'] === 0,
        'GST before-tax prices, buyer without GSTIN: ₹1,000 + CGST ₹90 + SGST ₹90 = ₹1,180');
    $t = commercial_tax(1000, $MH);
    t_ok($t['igst'] === 180 && $t['cgst'] === 0 && $t['pos'] === '27', 'a buyer registered in another state (Maharashtra) pays IGST ₹180');
    $t = commercial_tax(1000, $GJ);
    t_ok($t['cgst'] === 90 && $t['sgst'] === 90, 'a buyer in the same state pays CGST + SGST');
    t_eq(commercial_tax(999)['tax'], 180, 'tax is rounded to the rupee (₹179.82 → ₹180)');
    setting_set('plat_gst_mode', 'inclusive');
    $t = commercial_tax(1180);
    t_ok($t['gross'] === 1180 && $t['net'] === 1000 && $t['tax'] === 180, 'GST-inclusive prices: ₹1,180 charged is ₹1,000 + ₹180 GST');
    setting_set('plat_gst_mode', 'exclusive');
    setting_set('plat_gstin', '');
    t_ok(!commercial_tax(1000)['on'], 'without the platform\'s GSTIN no GST is charged, even if switched on');
    setting_set('plat_gstin', $PLAT);

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
    setting_set('company_name', 'Buyer Co'); setting_set('company_address', 'Pune'); setting_set('company_gstin', $MH);
    $buyer = saas_invoice_buyer();
    t_eq($buyer['gstin'], $MH, 'the buyer on the invoice is the company\'s own profile, with its GSTIN');
    $tax = commercial_tax(1998, $buyer['gstin']);
    $oid = 'order_cm_' . getmypid();
    saas_order_create($oid, 'plan', [$mk], 2, 'month', $tax['gross'], [],
        $tax + ['buyer' => $buyer, 'supplier' => commercial_gst_cfg(), 'lines' => saas_company_quote([$mk], 2, 'month')['lines'], 'credit' => 0, 'price' => 1998]);
    $o = saas_order_claim($oid, 'pay_cm_' . getmypid());
    t_ok($o && (int) $o['amount'] === 2358, 'the order records what is charged: ₹1,998 + 18% = ₹2,358');
    t_ok($o && preg_match('#^EX/\d{4}-\d{2}/\d{4}$#', (string) $o['invoice_no']), 'a paid order with GST gets the next tax-invoice number (' . ($o['invoice_no'] ?? '') . ')');
    $inv = commercial_register_get($o['invoice_no']);
    ob_start(); include dirname(__DIR__) . '/views/ops/subscription_invoice.php'; $html = ob_get_clean();
    t_ok($inv && $inv['buyer']['gstin'] === $MH && $inv['company_key'] === (string) current_tenant(), 'the invoice is in the platform\'s GST register, with the buyer\'s GSTIN');
    foreach (['Tax invoice', $o['invoice_no'], $PLAT, $MH, 'Maharashtra (27)', '997331', 'IGST @ 18%', '₹2,358', 'reverse charge: No',
              'Two Thousand Three Hundred Fifty Eight Rupees Only', 'Paid in full via <b>Razorpay</b>', 'pay_cm_', '✓ PAID'] as $needle)
        t_ok(strpos($html, (string) $needle) !== false, 'the tax invoice shows "' . $needle . '"');
    t_eq(saas_order_claim($oid, 'pay_again'), null, 'a replayed payment changes nothing and takes no second number');

    // ---- The logo on the invoice --------------------------------------------------------
    $logoFile = commercial_logo_file(); $logoBak = is_file($logoFile) ? file_get_contents($logoFile) : null;
    t_ok(strpos($html, 'class="logo"') === ($logoBak === null ? false : strpos($html, 'class="logo"')), 'with no logo uploaded, the invoice simply has none');
    t_ok(commercial_logo_save('not an image') !== '', 'a file that is not an image is refused in plain words');
    if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor(400, 120); imagefill($im, 0, 0, imagecolorallocate($im, 30, 64, 175));
        ob_start(); imagepng($im); $png = ob_get_clean();
        t_eq(commercial_logo_save($png), '', 'a PNG logo is accepted (kept as a small JPEG)');
        t_ok(strpos(commercial_logo_data_uri(), 'data:image/jpeg;base64,') === 0, 'and is ready to print on every invoice');
        ob_start(); include dirname(__DIR__) . '/views/ops/subscription_invoice.php'; $html2 = ob_get_clean();
        t_ok(strpos($html2, '<img class="logo" src="data:image/jpeg;base64,') !== false, 'the invoice now shows the logo at the top');
    }
    if ($logoBak !== null) file_put_contents($logoFile, $logoBak); else @unlink($logoFile);
    t_ok(strlen((string) commercial_logo_save(str_repeat('x', 2 * 1048576 + 1))) > 0, 'a logo over 2 MB is refused');

    // ---- Bill to: what the customer types at checkout --------------------------------
    $bt = commercial_billto_clean(['name' => 'Buyer Co', 'gstin' => substr($MH, 0, 14) . 'X', 'state' => '24']);
    t_ok(strpos($bt['error'], 'typing mistake') !== false, 'a mistyped customer GSTIN is refused before anything is charged');
    $bt = commercial_billto_clean(['name' => 'Buyer Co', 'gstin' => strtolower($MH), 'state' => '24', 'address' => 'Pune']);
    t_ok($bt['error'] === '' && $bt['buyer']['gstin'] === $MH && $bt['buyer']['state'] === '27', 'a valid GSTIN is accepted, and its state (27) wins over the state picked');
    t_ok(commercial_billto_clean(['name' => '', 'gstin' => ''])['error'] !== '', 'a name to bill is required');
    $bt = commercial_billto_clean(['name' => 'Small Shop', 'gstin' => '', 'state' => 'Karnataka']);
    t_ok($bt['error'] === '' && $bt['buyer']['state'] === '29', 'no GSTIN is fine; the state can be given by name');
    t_ok(commercial_tax(1000, '', '29')['igst'] === 180, 'an unregistered buyer in another state pays IGST (place of supply = its state)');
    t_ok(commercial_tax(1000, '', '')['cgst'] === 90, 'with no GSTIN and no state, the platform\'s own state is used (CGST + SGST)');
    $_POST = ['billto_name' => 'Buyer Co Pvt Ltd', 'billto_gstin' => $GJ, 'billto_state' => '', 'billto_address' => 'Ahmedabad', 'billto_save' => '1'];
    $buyer = saas_billto_from_post(); $_POST = [];
    t_ok($buyer['gstin'] === $GJ && $buyer['state'] === '24', 'checkout reads the "Bill to" the customer typed');
    t_ok(setting_get('company_gstin', '') === $GJ && setting_get('company_legal_name', '') === 'Buyer Co Pvt Ltd' && setting_get('company_state', '') === 'Gujarat',
        '"Save to our company profile" fills it in for next time (GSTIN, name, state)');

    // ---- A customer who paid without its GSTIN, then asks for it on the bill ----------
    t_ok(commercial_register_amend($o['invoice_no'], ['name' => 'Buyer Co', 'gstin' => $GJ], '', 'owner') !== '', 'a correction needs a reason');
    $err = commercial_register_amend($o['invoice_no'], ['name' => 'Buyer Co Pvt Ltd', 'gstin' => $GJ, 'address' => 'Ahmedabad'], 'customer sent its GSTIN after paying', 'owner');
    $inv = commercial_register_get($o['invoice_no']);
    t_ok($err === '' && $inv['buyer']['gstin'] === $GJ, 'the owner corrects the buyer on an issued invoice');
    t_ok($inv['total'] === 2358 && $inv['tax'] === 360 && $inv['cgst'] === 180 && $inv['sgst'] === 180 && $inv['igst'] === 0,
        'the amount and tax stay as charged; the split follows the new place of supply (Gujarat → CGST + SGST)');
    t_ok(count($inv['amendments']) === 1 && $inv['amendments'][0]['was']['gstin'] === $MH && $inv['pos'] === '24', 'what it was, who, when and why are kept with the invoice');
    ob_start(); include dirname(__DIR__) . '/views/ops/subscription_invoice.php'; $html = ob_get_clean();
    t_ok(strpos($html, $GJ) !== false && strpos($html, 'Revised') !== false && strpos($html, 'CGST @ 9%') !== false, 'the invoice now shows the GSTIN, "Revised", and CGST + SGST');
    t_ok(commercial_register_amend('EX/1999-00/9999', ['name' => 'x'], 'y', 'owner') !== '', 'an unknown invoice number cannot be corrected');

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
    if ($regBak !== null) file_put_contents($regFile, $regBak); else @unlink($regFile);
}
