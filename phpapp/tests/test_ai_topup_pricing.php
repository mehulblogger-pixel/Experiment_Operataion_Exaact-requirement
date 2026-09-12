<?php
// ============================================================================
//  AI top-up pack + super-admin pricing map. A workspace buys extra AI actions
//  for the current month (added on top of the plan cap), and the super-admin's
//  price form maps cleanly onto the settings saas_price_book()/billing read.
// ============================================================================

t_section('AI top-up + pricing map');

if (!function_exists('ai_effective_cap') || !function_exists('pricing_settings_map')) { t_ok(true, 'pricing/top-up not present — skipped'); return; }

// --- Top-up adds to THIS month's allowance and lifts the cap. ---
$saveCap = (string) setting_get('ai_monthly_cap', '');
$saveUse = (string) setting_get(ai_usage_setting(), '');
$saveTop = (string) setting_get(ai_topup_setting(), '');
setting_set('ai_monthly_cap', '10');
setting_set(ai_usage_setting(), '0');
setting_set(ai_topup_setting(), '0');

t_eq(ai_effective_cap(), 10, 'with no top-up, the effective cap is the plan cap');
t_eq(ai_pool_remaining(), 10, 'all actions are available');
// Use them all → over the cap.
setting_set(ai_usage_setting(), '10');
t_ok(ai_pool_over(), 'at the plan cap, the pool is closed');
// Buy a top-up → the same month re-opens with the extra actions.
ai_topup_add(25);
t_eq(ai_topup_month(), 25, 'the top-up is recorded for this month');
t_eq(ai_effective_cap(), 35, 'the effective cap = plan cap + top-up');
t_ok(!ai_pool_over(), 'after a top-up, the pool is open again');
t_eq(ai_pool_remaining(), 25, 'the remaining actions reflect the top-up');

// --- Pack config: defaults + overrides. ---
$savePS = (string) setting_get('ai_pack_size', '');
$savePP = (string) setting_get('ai_pack_price', '');
setting_set('ai_pack_size', ''); setting_set('ai_pack_price', '');
t_eq(ai_pack_size(), AI_DEFAULT_PACK_SIZE, 'the pack size falls back to the default');
t_ok(ai_pack_price() === AI_DEFAULT_PACK_PRICE, 'the pack price falls back to the default');
setting_set('ai_pack_size', '250'); setting_set('ai_pack_price', '399');
t_eq(ai_pack_size(), 250, 'a configured pack size wins');
t_eq(ai_pack_price(), 399, 'a configured pack price wins');

// --- Pricing form → settings map. ---
$map = pricing_settings_map([
    'currency' => 'inr', 'seat_month' => 1500, 'seat_year' => 15000,
    'mod_hr_month' => 800, 'mod_hr_year' => 8000, 'ai_cap' => 200, 'ai_pack_size' => 100, 'ai_pack_price' => 149,
]);
t_eq($map['billing_currency'], 'INR', 'currency is upper-cased');
t_eq($map['billing_price_user_month'], '1500', 'seat month price maps through');
t_eq($map['saas_price_mod_hr_month'] ?? '', '800', 'a module month price maps to its saas_price_mod key');
t_eq($map['ai_monthly_cap'], '200', 'the AI cap maps through');
t_eq($map['ai_pack_price'], '149', 'the pack price maps through');

// --- pricing_settings reads the module list for the editor. ---
$ps = pricing_settings();
t_ok(isset($ps['modules']) && is_array($ps['modules']), 'the editor gets the priceable module list');
t_ok(!isset($ps['modules']['admin']), 'the core Administration module is never priced');

// Restore.
setting_set('ai_monthly_cap', $saveCap);
setting_set(ai_usage_setting(), $saveUse);
setting_set(ai_topup_setting(), $saveTop);
setting_set('ai_pack_size', $savePS);
setting_set('ai_pack_price', $savePP);
t_ok(true, 'settings restored');
