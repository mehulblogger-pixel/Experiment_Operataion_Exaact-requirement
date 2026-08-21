<?php
// The two overlapping Operations screens are merged: the desk's registers now
// live on the Operations home, and /ops-desk redirects there.
t_section('operations screens merged into one');

$ops = (string)file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "redirect('/operations#backlog')") !== false,
    'the old /ops-desk route redirects into the Operations home');

$oh = (string)file_get_contents(__DIR__ . '/../views/ops/operations_home.php');
t_ok(strpos($oh, '_ops_registers.php') !== false,
    'the Operations home includes the backlog/schedule/assignment registers');
t_ok(strpos($oh, 'data-tab="Backlog &amp; registers"') !== false,
    'the registers are a tab on the Operations home');
t_ok(strpos($oh, '/ops-desk') === false, 'the Operations home no longer links to a separate desk');

$nav = (string)file_get_contents(__DIR__ . '/../lib/navindex.php');
t_ok(strpos($nav, "'/ops-desk'") === false, 'the Operations desk nav entry is removed');

$src = (string)file_get_contents(__DIR__ . '/../lib/tosrm.php');
t_ok(strpos($src, "'backlog' => tosrm_ops_backlog") !== false, 'the home handler now supplies the backlog register');
t_ok(is_file(__DIR__ . '/../views/ops/_ops_registers.php'), 'the shared registers partial exists');
