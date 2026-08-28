<?php
// Connect — the module on/off switch. The marketplace is dissolved into EXAACT
// but cleanly optional: connect_enabled() (default ON) gates every entry point,
// and the 'Marketplace' nav area disappears when it is off.
t_section('connect module switch (nav + toggle)');

// Default is ON.
t_ok(connect_enabled(), 'the marketplace is enabled by default');

// The marketplace area is a real, registered area.
$def = ops_area_def('marketplace');
t_ok(is_array($def) && ($def['title'] ?? '') === 'Marketplace', 'a Marketplace area is defined');
t_ok(in_array('connect-requirements', $def['routes'] ?? [], true), 'the area claims the marketplace routes');

// Turning it off disables the gates and hides the area.
setting_set('connect_enabled', '0');
t_ok(!connect_enabled(), 'the switch turns the marketplace off');
t_ok(!connect_market_can(), 'staff marketplace access is denied when off');
t_ok(!connect_taxonomy_can(), 'taxonomy access is denied when off');
t_ok(ops_area_tile_count('marketplace') === 0, 'the Marketplace area shows no tiles when off');

// Turning it back on restores it.
setting_set('connect_enabled', '1');
t_ok(connect_enabled(), 'the switch turns the marketplace back on');

// Leave the setting ON for any following tests.
setting_set('connect_enabled', '1');
