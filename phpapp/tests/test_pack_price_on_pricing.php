<?php
// Owner, 31 Oct 2026 (test book PLT-005): "there is GB pack price but under the
// Companies. I think that should be under the Pricing, a universal place to adjust
// the price." The pack price is now on Pricing with every other price; saving the
// Storage allowance panel on Companies never wipes it. Checked on a local copy.
t_section('Extra storage pack — priced on Pricing');
$m = pricing_settings_map(['pack_month' => '399', 'saas_storage_pack_price_year' => '3990', 'currency' => 'INR']);
t_eq([$m['saas_storage_pack_price_month'] ?? null, $m['saas_storage_pack_price_year'] ?? null], ['399', '3990'], 'Pricing saves the pack price, monthly and yearly');
$v = (string) file_get_contents(__DIR__ . '/../views/ops/pricing_usage.php');
t_ok(strpos($v, '<b>Extra storage pack</b>') !== false, 'Pricing has an "Extra storage pack" row');
$h = (string) file_get_contents(__DIR__ . '/../lib/saas_tenants.php');
t_ok(strpos($h, "if (array_key_exists('st_pack_month', \$_POST)) setting_set('saas_storage_pack_price_month'") !== false, 'saving Storage allowance never wipes the price');
$c = (string) file_get_contents(__DIR__ . '/../views/ops/saas_companies.php');
t_ok(strpos($c, 'name="st_pack_month"') === false && strpos($c, 'set on Pricing</a>') !== false, 'Companies shows the price and points to Pricing');
