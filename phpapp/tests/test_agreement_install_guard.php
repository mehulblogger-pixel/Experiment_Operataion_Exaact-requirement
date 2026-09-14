<?php
// ============================================================================
//  The "stop, you are already installed" warning must fire in exactly one place.
//
//  It exists because renaming a config file pointed the CONTROL installation at
//  an empty database, and the application offered to install itself over a live
//  system. But the same screen is entirely correct inside a company workspace:
//  every workspace is a full independent install, and its owner accepts the
//  agreement once, on first sign-in. Warning there tells every new customer to
//  stop doing the one thing they must do.
//
//  config.local.php belongs to the whole server, so the file's existence alone
//  cannot tell the two situations apart. Being on the control install is what
//  separates them.
// ============================================================================

t_section('Installation warning is scoped to the control install');

$src = (string) file_get_contents(dirname(__DIR__) . '/lib/agreement.php');

t_ok(strpos($src, "Stop if you already have this application running") !== false,
     'the warning exists');
t_ok(preg_match('/\$onControl\s*=.*current_tenant\(\)\s*===\s*\x27\x27/', $src) === 1,
     'it computes whether this is the control installation');
t_ok(strpos($src, "if (\$onControl && is_file(dirname(__DIR__) . '/config.local.php'))") !== false,
     'and shows the warning only there, never inside a workspace');

// The function that decides it must be the app's own, not a copy.
t_ok(function_exists('current_tenant'), 'current_tenant() is the application\'s own answer');
t_eq(current_tenant(), '', 'and on a plain install it reports the control site');
