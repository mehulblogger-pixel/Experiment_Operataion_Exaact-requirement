<?php
// ============================================================================
//  Super-Admin — Plans, pricing & usage  (control install only)
//
//  One screen where the platform owner sets what each module and seat costs, the
//  default monthly AI allowance, and the AI top-up pack — then pushes those to
//  every live workspace, and sees each workspace's seat + AI usage.
//
//  Also here: the customer-facing AI TOP-UP purchase (Razorpay), because a
//  workspace buys extra AI actions when its monthly allowance runs out.
//
//  Reuse-first: prices are the same settings saas_price_book() and billing_config()
//  already read; the payment reuses the Razorpay helpers (rzp_create_order /
//  rzp_verify_signature); pushing to a workspace reuses saas_enter_tenant().
// ============================================================================

// The pricing / AI / GST settings this screen owns, as SAVED — a blank means
// "not set" and is shown blank (never a made-up figure that looks saved).
function pricing_settings() {
    $g = fn($k) => function_exists('setting_get') ? trim((string) setting_get($k, '')) : '';
    $mods = [];
    if (defined('PRODUCT_MODULES')) {
        foreach (PRODUCT_MODULES as $k => $m) {
            if (!empty($m[3])) continue;   // core admin is never priced
            $mods[$k] = ['label' => $m[0] ?? $k, 'month' => $g('saas_price_mod_' . $k . '_month'), 'year' => $g('saas_price_mod_' . $k . '_year')];
        }
    }
    $raw = [];
    foreach (array_keys(pricing_field_keys()) as $k) $raw[$k] = $g($k);
    return [
        'currency'   => $g('billing_currency') ?: 'INR',
        'seat_month' => $g('billing_price_user_month'),
        'seat_year'  => $g('billing_price_user_year'),
        'modules'    => $mods,
        'raw'        => $raw,
        'ai_cap'     => function_exists('ai_monthly_cap') ? ai_monthly_cap() : 0,
        'ai_pack_size'  => function_exists('ai_pack_size') ? ai_pack_size() : 0,
        'ai_pack_price' => $g('ai_pack_price'),
        'ai_platform'   => function_exists('platform_ai_available') && platform_ai_available(),
        'plans'      => (function () { $o = [];
            foreach ((function_exists('superadmin_tiers') ? superadmin_tiers() : []) as $k => $t)
                $o[$k] = ['label' => $t['label'] ?? $k, 'saved' => trim((string) setting_get('saas_plan_logins_' . $k, '')), 'now' => saas_plan_logins($k)];
            return $o; })(),
        'catalogue'  => function_exists('commercial_catalogue') ? commercial_catalogue() : [],
        'yearly'     => function_exists('saas_price_book') ? saas_price_book() : [],
    ];
}
// Form field => setting, for the fields beyond the module / login table.
function pricing_field_keys() {
    return ['saas_yearly_discount_pct' => 'pct', 'billing_price_field_month' => 'money', 'billing_price_portal_month' => 'money',
            'billing_portal_free' => 'int', 'plat_gst_enabled' => 'bool', 'plat_gst_rate' => 'pct', 'plat_gst_mode' => 'mode',
            'plat_gst_sac' => 'text', 'plat_legal_name' => 'text', 'plat_gstin' => 'gstin', 'plat_address' => 'text', 'plat_invoice_prefix' => 'text'];
}

// Flatten the pricing form into [setting => value]. A field left blank is saved
// blank (= not set). Fields not on the form are left alone.
function pricing_settings_map(array $in) {
    $out = [];
    $money = fn($v) => trim((string) $v) === '' ? '' : (string) max(0, (int) $v);
    $out['billing_currency'] = strtoupper(trim((string) ($in['currency'] ?? 'INR'))) ?: 'INR';
    if (array_key_exists('seat_month', $in)) $out['billing_price_user_month'] = $money($in['seat_month']);
    if (array_key_exists('seat_year', $in))  $out['billing_price_user_year']  = $money($in['seat_year']);
    if (defined('PRODUCT_MODULES')) {
        foreach (PRODUCT_MODULES as $k => $m) {
            if (!empty($m[3])) continue;
            if (array_key_exists('mod_' . $k . '_month', $in)) $out['saas_price_mod_' . $k . '_month'] = $money($in['mod_' . $k . '_month']);
            if (array_key_exists('mod_' . $k . '_year', $in))  $out['saas_price_mod_' . $k . '_year']  = $money($in['mod_' . $k . '_year']);
        }
    }
    foreach ((function_exists('superadmin_tiers') ? array_keys(superadmin_tiers()) : []) as $plan)
        if (array_key_exists('plan_logins_' . $plan, $in)) $out['saas_plan_logins_' . $plan] = $money($in['plan_logins_' . $plan]);
    if (array_key_exists('ai_cap', $in))        $out['ai_monthly_cap'] = (string) max(1, (int) $in['ai_cap']);
    if (array_key_exists('ai_pack_size', $in))  $out['ai_pack_size']   = (string) max(1, (int) $in['ai_pack_size']);
    if (array_key_exists('ai_pack_price', $in)) $out['ai_pack_price']  = $money($in['ai_pack_price']);
    foreach (pricing_field_keys() as $k => $type) {
        if ($type === 'bool') { if (array_key_exists('_has_' . $k, $in) || array_key_exists($k, $in)) $out[$k] = !empty($in[$k]) ? '1' : ''; continue; }
        if (!array_key_exists($k, $in)) continue;
        $v = trim((string) $in[$k]);
        switch ($type) {
            case 'money': case 'int': $out[$k] = $money($v); break;
            case 'pct':   $out[$k] = $v === '' ? '' : (string) max(0, min(90, round((float) $v, 2))); break;
            case 'mode':  $out[$k] = $v === 'inclusive' ? 'inclusive' : ($v === '' ? '' : 'exclusive'); break;
            case 'gstin': $out[$k] = function_exists('clean_gstin') ? clean_gstin($v) : strtoupper($v); break;
            default:      $out[$k] = mb_substr($v, 0, 400);
        }
    }
    return $out;
}
// What is wrong with a map before it is saved: '' or a plain-language reason.
function pricing_settings_check(array $map) {
    $gstin = (string) ($map['plat_gstin'] ?? '');
    if ($gstin !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin))
        return 'That GSTIN does not look right — it is 15 characters, like 24ABCDE1234F1Z5.';
    if ($gstin !== '' && defined('GST_STATES') && !isset(GST_STATES[substr($gstin, 0, 2)]))
        return 'The GSTIN\'s first two digits are not an Indian state code.';
    if (($map['plat_gst_enabled'] ?? '') === '1') {
        $miss = [];
        foreach (['plat_gstin' => 'your GSTIN', 'plat_gst_rate' => 'the GST rate', 'plat_legal_name' => 'your legal name',
                  'plat_address' => 'your registered address', 'plat_gst_sac' => 'the SAC code'] as $k => $lab)
            if (trim((string) ($map[$k] ?? '')) === '' || (in_array($k, ['plat_gst_rate'], true) && (float) $map[$k] <= 0)) $miss[] = $lab;
        if ($miss) return 'To charge GST, fill in ' . implode(', ', $miss) . ' first. Nothing was saved.';
    }
    return '';
}

// Push a set of settings into ONE workspace's own database, then return to the
// control install. Mirrors saas_push_to_tenant_inproc()'s safe enter/restore.
function pricing_push_to_tenant($key, array $settings) {
    $key = strtolower(trim((string) $key));
    if ($key === '' || !function_exists('saas_enter_tenant')) return false;
    $ok = false;
    try {
        saas_enter_tenant($key);
        $rt = [];
        try { db(); $rt = $GLOBALS['__tenant'] ?? []; } catch (Throwable $e) {}
        if (strtolower((string) ($rt['key'] ?? '')) === $key && ($rt['error'] ?? '') === '') {
            if (function_exists('saas_tenant_ensure_ready')) saas_tenant_ensure_ready($key);
            foreach ($settings as $sk => $sv) { try { setting_set($sk, $sv); } catch (Throwable $e) {} }
            $ok = true;
        }
    } catch (Throwable $e) { $ok = false; }
    if (function_exists('saas_leave_tenant')) saas_leave_tenant();   // back to control
    return $ok;
}

// Read one workspace's usage (seats + AI this month), safely, from control.
function pricing_read_tenant_usage($key) {
    $key = strtolower(trim((string) $key));
    $row = ['seats_used' => 0, 'seat_cap' => 0, 'ai_used' => 0, 'ai_cap' => 0, 'ok' => false];
    if ($key === '' || !function_exists('saas_enter_tenant')) return $row;
    try {
        saas_enter_tenant($key);
        $rt = [];
        try { db(); $rt = $GLOBALS['__tenant'] ?? []; } catch (Throwable $e) {}
        if (strtolower((string) ($rt['key'] ?? '')) === $key && ($rt['error'] ?? '') === '') {
            $row['seats_used'] = (int) (function_exists('ops_val') ? ops_val("SELECT COUNT(*) FROM users WHERE COALESCE(is_active,1)=1") : 0);
            $row['seat_cap']   = (int) setting_get('saas_seat_limit', 0);
            $row['ai_used']    = function_exists('ai_usage_month') ? ai_usage_month() : 0;
            $row['ai_cap']     = function_exists('ai_effective_cap') ? ai_effective_cap() : 0;
            $row['ok'] = true;
        }
    } catch (Throwable $e) { /* leave defaults */ }
    if (function_exists('saas_leave_tenant')) saas_leave_tenant();
    return $row;
}

// Every hosted workspace's key => company name (from the routing registry).
function pricing_tenant_keys() {
    $reg = function_exists('tenant_registry') ? tenant_registry() : ['tenants' => []];
    $out = [];
    foreach (($reg['tenants'] ?? []) as $k => $t) $out[(string) $k] = (string) ($t['company'] ?? $k);
    return $out;
}

// ---- The screen ------------------------------------------------------------
function ops_pricing_usage($method) {
    ops_require(function_exists('superadmin_can') ? superadmin_can() : (function_exists('is_master') && is_master() && (!function_exists('current_tenant') || current_tenant() === '')),
        'Only the platform owner (Super Admin) can manage pricing.');

    if ($method === 'POST') {
        $action = (string) ($_POST['action'] ?? 'save');
        $map = pricing_settings_map($_POST);
        $why = pricing_settings_check($map);
        if ($why !== '') { flash($why, 'error'); redirect('/pricing-usage'); }
        // Always save on the control install (the master price list + AI defaults).
        foreach ($map as $k => $v) { try { setting_set($k, $v); } catch (Throwable $e) {} }
        if ($action === 'apply_all') {
            // Everything a company needs to price and tax its own checkout.
            if (function_exists('commercial_push_keys')) foreach (commercial_push_keys() as $ck) if (!array_key_exists($ck, $map)) $map[$ck] = (string) setting_get($ck, '');
            $done = 0; $fail = 0;
            foreach (array_keys(pricing_tenant_keys()) as $k) {
                if (pricing_push_to_tenant($k, $map)) $done++; else $fail++;
            }
            flash('Saved. Pushed the prices, GST and AI allowance to ' . $done . ' workspace' . ($done === 1 ? '' : 's')
                . ($fail ? ' (' . $fail . ' could not be reached — try again).' : '.'), $fail ? 'warning' : 'success');
        } else {
            flash('Pricing saved. New workspaces use it immediately; use "Save & push to all workspaces" to update existing ones.');
        }
        redirect('/pricing-usage');
    }

    // Usage table (best-effort, per workspace).
    $usage = [];
    foreach (pricing_tenant_keys() as $k => $name) $usage[$k] = ['name' => $name] + pricing_read_tenant_usage($k);

    view('ops/pricing_usage', [
        'p'     => pricing_settings(),
        'usage' => $usage,
        'sym'   => function_exists('cur_sym') ? cur_sym() : '₹',
    ]);
    return true;   // handled — without this the router went on to append its "not found" page
}

// ---- Customer AI top-up purchase (Razorpay) --------------------------------
function ops_ai_topup($route, $method) {
    ops_require((function_exists('is_master') && is_master()) || (function_exists('can') && can('settings.manage')),
        'Only an administrator can buy AI actions.');

    // Step 1 → create the order and open the payment window.
    if ($route === 'ai-topup-order' && $method === 'POST') {
        if (!function_exists('billing_configured') || !billing_configured()) {
            flash('Online payment is not switched on for your workspace yet. Please contact your provider.', 'error'); redirect('/ai-forms');
        }
        $size  = function_exists('ai_pack_size') ? ai_pack_size() : 0;
        $price = function_exists('ai_pack_price') ? ai_pack_price() : 0;
        if ($price <= 0) { flash('The AI top-up pack has no price set — please contact your provider.', 'error'); redirect('/ai-forms'); }
        $ord = rzp_create_order($price * 100, 'aitop-' . date('ymdHis'), ['ai_actions' => $size]);
        if (empty($ord['ok'])) { flash('Could not start the payment: ' . $ord['error'], 'error'); redirect('/ai-forms'); }
        $cur = (string) (billing_config()['currency'] ?? 'INR');
        view('ops/ai_topup_pay', ['order' => $ord, 'cfg' => billing_config(), 'size' => $size, 'price' => $price, 'currency' => $cur]);
        return true;
    }

    // Step 2 → verify the payment, then add the actions to THIS month's allowance.
    if ($route === 'ai-topup-verify' && $method === 'POST') {
        $orderId   = (string) ($_POST['razorpay_order_id'] ?? '');
        $paymentId = (string) ($_POST['razorpay_payment_id'] ?? '');
        $sig       = (string) ($_POST['razorpay_signature'] ?? '');
        if (!rzp_verify_signature($orderId, $paymentId, $sig)) {
            flash('That payment could not be verified. If money was taken it is refunded automatically — nothing was changed.', 'error');
            redirect('/ai-forms');
        }
        $size = function_exists('ai_pack_size') ? ai_pack_size() : 100;
        if (function_exists('ai_topup_add')) ai_topup_add($size);
        if (function_exists('billing_record_line'))
            billing_record_line(0, 'once', $size . ' AI actions', $paymentId, $orderId, date('Y-m-d'));
        if (function_exists('idems_log')) idems_log('setting', null, 'AI_TOPUP_PURCHASED', ['field' => 'ai_topup', 'new' => $size]);
        flash('Payment received — ' . $size . ' more AI actions added for this month. You now have '
            . (function_exists('ai_pool_remaining') ? ai_pool_remaining() : $size) . ' left.', 'success');
        redirect('/ai-forms');
    }
    redirect('/ai-forms');
    return true;
}
