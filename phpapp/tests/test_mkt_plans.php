<?php
// Slice 2 — the Super-Admin marketplace plans & limits engine: a configurable store
// for subscription plans (client + freelancer), prices, limits, the annual discount
// and the freelancer launch-promo. Nothing hard-coded; all editable.
t_section('marketplace plans & limits (super-admin)');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    // Migrate seeds a sensible default set once.
    mkt_plans_migrate();
    $all = mkt_plans_all();
    t_ok(count($all) >= 6, 'default plans are seeded (client + freelancer)');
    t_ok(count(mkt_plans_all('CLIENT')) >= 3 && count(mkt_plans_all('PRO')) >= 3, 'both audiences have plans');

    // Annual = pay N months (default 10) — a seeded plan's yearly = monthly × 10.
    t_eq(mkt_annual_months(), 10, 'annual months defaults to 10');
    $growth = null; foreach (mkt_plans_all('CLIENT') as $p) if ($p['code'] === 'CL_GROWTH') $growth = $p;
    t_ok($growth && (int)$growth['price_annual'] === (int)round($growth['price_month'] * 10), 'seeded annual price = month × 10');

    // Add a plan with limits — round-trips.
    [$ok, $msg] = mkt_plan_save(['audience' => 'CLIENT', 'name' => 'Test Enterprise', 'price_month' => 7999,
        'lim_posts' => 100, 'lim_unlocks' => 200, 'lim_reports' => 500, 'is_active' => 1]);
    t_ok($ok, 'a new plan saves: ' . $msg);
    $new = null; foreach (mkt_plans_all('CLIENT') as $p) if ($p['name'] === 'Test Enterprise') $new = $p;
    t_ok($new !== null, 'the new plan is listed');
    $lim = mkt_plan_limits($new);
    t_eq((int)$lim['posts'], 100, 'posts limit stored');
    t_eq((int)$lim['reports'], 500, 'reports limit stored');
    t_eq((int)$new['price_annual'], 79990, 'blank annual auto-fills month × 10');

    // Edit it — price + a limit change.
    [$ok2] = mkt_plan_save(['id' => $new['id'], 'audience' => 'CLIENT', 'name' => 'Test Enterprise', 'price_month' => 8999, 'lim_posts' => 150]);
    t_ok($ok2, 'the plan updates');
    $upd = mkt_plan_get($new['id']);
    t_eq((int)$upd['price_month'], 8999, 'price updated');
    t_eq((int)mkt_plan_limits($upd)['posts'], 150, 'limit updated');

    // Delete it.
    mkt_plan_delete($new['id']);
    t_ok(mkt_plan_get($new['id']) === null, 'the plan is deleted');

    // Global knobs save + the freelancer free-promo date logic.
    mkt_settings_save(['mkt_annual_months' => 11, 'mkt_pro_free_until' => '2027-01-31', 'mkt_currency' => '₹']);
    t_eq(mkt_annual_months(), 11, 'annual months is editable');
    t_ok(mkt_pro_is_free('2026-12-01') === true, 'a professional is free before the promo end');
    t_ok(mkt_pro_is_free('2027-06-01') === false, 'a professional is paid after the promo end');
    // The "+6 months from today" shortcut.
    mkt_settings_save(['mkt_pro_free_until' => '+6']);
    t_ok(mkt_pro_free_until() >= date('Y-m-d', strtotime('+5 months')), 'the +6-months shortcut sets a future date');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
