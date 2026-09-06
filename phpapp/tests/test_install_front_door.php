<?php
// A private on-premise copy must open on the STAFF LOGIN, not the hosted
// marketplace door. The desktop launchers declare this by setting
// INSTALL_MODE=licence, which must outrank the stored 'cloud' default. This
// guards the fix for the "app opens only /connect" report.
t_section('on-premise install opens on the staff login, not the marketplace');

t_ok(function_exists('install_front_route'), 'the front-route helper exists');

// The launcher's declaration wins.
putenv('INSTALL_MODE=licence');
t_eq(install_mode(), 'licence',            'INSTALL_MODE=licence is honoured');
t_eq(install_front_route(), '/login',      'a licence install sends the front door to /login');

putenv('INSTALL_MODE=cloud');
t_eq(install_mode(), 'cloud',              'INSTALL_MODE=cloud is honoured too');

// A junk value is ignored (falls back to the stored setting / default).
putenv('INSTALL_MODE=nonsense');
t_ok(in_array(install_mode(), ['cloud', 'licence'], true), 'an invalid override is ignored, not obeyed');

putenv('INSTALL_MODE');                    // unset — restore normal resolution for later tests
t_ok(in_array(install_mode(), ['cloud', 'licence'], true), 'without the env, mode resolves from settings/default');
