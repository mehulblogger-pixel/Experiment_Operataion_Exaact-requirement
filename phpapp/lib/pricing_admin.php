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

// The pricing / AI settings this screen owns, with their current control values.
function pricing_settings() {
    $g = fn($k, $d = '') => function_exists('setting_get') ? (string) setting_get($k, $d) : (string) $d;
    $mods = [];
    if (defined('PRODUCT_MODULES')) {
        $def = function_exists('saas_price_defaults') ? saas_price_defaults() : [];
        foreach (PRODUCT_MODULES as $k => $m) {
            if (!empty($m[3])) continue;   // core admin is never priced
            $mods[$k] = [
                'label' => $m[0] ?? $k,
                'month' => (int) ($g('saas_price_mod_' . $k . '_month') ?: ($def[$k] ?? 1000)),
                'year'  => (int) ($g('saas_price_mod_' . $k . '_year')  ?: (($def[$k] ?? 1000) * 10)),
            ];
        }
    }
    return [
        'currency'   => $g('billing_currency', 'INR') ?: 'INR',
        'seat_month' => (int) ($g('billing_price_user_month') ?: 1799),
        'seat_year'  => (int) ($g('billing_price_user_year')  ?: 17990),
        'modules'    => $mods,
        'ai_cap'     => function_exists('ai_monthly_cap') ? ai_monthly_cap() : 100,
        'ai_pack_size'  => function_exists('ai_pack_size') ? ai_pack_size() : 100,
        'ai_pack_price' => function_exists('ai_pack_price') ? ai_pack_price() : 199,
        'ai_platform'   => function_exists('platform_ai_available') && platform_ai_available(),
    ];
}

// Flatten the editable pricing/AI settings into a plain [key => value] map — the
// single source both "save on control" and "push to a workspace" write from.
function pricing_settings_map(array $in) {
    $out = [];
    $cur = strtoupper(trim((string) ($in['currency'] ?? 'INR'))) ?: 'INR';
    $out['billing_currency'] = $cur;
    $out['billing_price_user_month'] = (string) max(0, (int) ($in['seat_month'] ?? 0));
    $out['billing_price_user_year']  = (string) max(0, (int) ($in['seat_year'] ?? 0));
    if (defined('PRODUCT_MODULES')) {
        foreach (PRODUCT_MODULES as $k => $m) {
            if (!empty($m[3])) continue;
            if (isset($in['mod_' . $k . '_month'])) $out['saas_price_mod_' . $k . '_month'] = (string) max(0, (int) $in['mod_' . $k . '_month']);
            if (isset($in['mod_' . $k . '_year']))  $out['saas_price_mod_' . $k . '_year']  = (string) max(0, (int) $in['mod_' . $k . '_year']);
        }
    }
    $out['ai_monthly_cap'] = (string) max(1, (int) ($in['ai_cap'] ?? 100));
    $out['ai_pack_size']   = (string) max(1, (int) ($in['ai_pack_size'] ?? 100));
    $out['ai_pack_price']  = (string) max(0, (int) ($in['ai_pack_price'] ?? 0));
    return $out;
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
    ops_require(function_exists('is_master') && is_master() && (!function_exists('current_tenant') || current_tenant() === ''),
        'Only the platform owner (Super Admin) can manage pricing.');

    if ($method === 'POST') {
        $action = (string) ($_POST['action'] ?? 'save');
        $map = pricing_settings_map($_POST);
        // Always save on the control install (the master price list + AI defaults).
        foreach ($map as $k => $v) { try { setting_set($k, $v); } catch (Throwable $e) {} }
        if ($action === 'apply_all') {
            $done = 0; $fail = 0;
            foreach (array_keys(pricing_tenant_keys()) as $k) {
                if (pricing_push_to_tenant($k, $map)) $done++; else $fail++;
            }
            flash('Saved. Pushed the prices and AI allowance to ' . $done . ' workspace' . ($done === 1 ? '' : 's')
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
        $size  = function_exists('ai_pack_size') ? ai_pack_size() : 100;
        $price = function_exists('ai_pack_price') ? ai_pack_price() : 199;
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
