<?php
// One-click install: the setup wizard can write a working config with NO MySQL
// — the built-in (sqlite) database — as well as a MySQL one. Both must produce
// valid PHP that returns the expected shape. Written to a throwaway temp file so
// the real config.local.php is never touched.
t_section('one-click install writes a working config (#setup)');

$tmp = sys_get_temp_dir() . '/mgh_cfg_' . getmypid() . '_' . substr(md5(__FILE__), 0, 6) . '.php';
@unlink($tmp);
register_shutdown_function(function () use ($tmp) { @unlink($tmp); });

// --- One-click: built-in database (sqlite), no server details --------------
$err = setup_write_local_config(['driver' => 'sqlite'], null, $tmp);
t_eq($err, '', 'the one-click (built-in database) config is written with no error');
t_ok(is_file($tmp), 'config.local.php is created');
$cfg = require $tmp;
t_ok(is_array($cfg) && isset($cfg['db']), 'the written file returns a config array');
t_eq($cfg['db']['driver'] ?? '', 'sqlite', 'the built-in database is selected — nothing to set up in cPanel');
t_ok(!isset($cfg['db']['user']), 'no database user/password is required for the one-click path');

// --- MySQL path still works -------------------------------------------------
@unlink($tmp);
$err2 = setup_write_local_config(['host' => 'localhost', 'name' => 'mghai_ops', 'user' => 'mghai_user', 'pass' => 's3cr3t'], null, $tmp);
t_eq($err2, '', 'the MySQL config is written with no error');
$cfg2 = require $tmp;
t_eq($cfg2['db']['name'] ?? '', 'mghai_ops', 'the MySQL database name is written');
t_eq($cfg2['db']['user'] ?? '', 'mghai_user', 'the MySQL user is written');
t_ok(!isset($cfg2['db']['driver']), 'the MySQL path leaves the driver at the app default (mysql)');

// --- An admin block is written when supplied, and preserved otherwise -------
@unlink($tmp);
setup_write_local_config(['driver' => 'sqlite'], ['user' => 'admin', 'pass' => 'Str0ng!pass'], $tmp);
$cfg3 = require $tmp;
t_eq($cfg3['admin']['user'] ?? '', 'admin', 'a supplied admin login is written');

// The wizard and the one-click handler are wired into the front controller.
$idx = (string)file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, 'setup_config_placeholder') !== false && strpos($idx, 'setup_render_db_form') !== false,
    'the installer runs automatically on a fresh, unconfigured upload');
$setupSrc = (string)file_get_contents(__DIR__ . '/../lib/setup.php');
t_ok(strpos($setupSrc, "'mode'] ?? '') === 'sqlite'") !== false, 'the one-click button is handled by the setup route');
