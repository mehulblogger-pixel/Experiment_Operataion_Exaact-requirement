<?php
// Owner, 8 Nov 2026: "even when marketplace is on it is not showing in the left
// navigation." The module was on; the platform-wide Marketplace switch was off.
// The Modules page now says so and offers the switch.
t_section('Modules page explains the Marketplace switch');
$v = (string) file_get_contents(__DIR__ . '/../views/ops/cockpit_modules.php');
t_ok(strpos($v, "\$key === 'connect' && \$m['on'] && function_exists('connect_enabled') && !connect_enabled()") !== false,
     'with the module on and the switch off, the page says why the marketplace is missing');
t_ok(strpos($v, 'action="/marketplace-toggle"') !== false && strpos($v, 'name="back" value="modules"') !== false,
     'the platform owner can switch it on from there and comes back to the same page');
t_ok(strpos($v, '/owner') !== false, 'on the main address (Super Admin locked elsewhere) it points to Owner home');
$o = (string) file_get_contents(__DIR__ . '/../lib/owner_home.php');
t_ok(strpos($o, "\$back === 'modules' ? '/workspace/setup/modules'") !== false, 'the switch returns to the Modules page');
t_ok(function_exists('connect_market_can'), 'the menu still asks the same gate (connect_market_can)');
