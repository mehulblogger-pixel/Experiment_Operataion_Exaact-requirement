<?php
// Configurable document numbering (owner requirement: numbering must be highly
// configurable per the company). ops_next_code delegates to the scheme engine.
t_section('configurable numbering');

// A throwaway table to allocate codes against.
db()->exec("CREATE TABLE IF NOT EXISTS num_probe (id INTEGER PRIMARY KEY AUTOINCREMENT, code VARCHAR(60))");
$mint = function () { $c = ops_next_code('num_probe', 'code', 'CALL');
    db()->prepare("INSERT INTO num_probe (code) VALUES (?)")->execute([$c]); return $c; };
$reset = function () { db()->exec("DELETE FROM num_probe"); };

// 1. Default scheme reproduces the old PREFIX-00001 format exactly.
setting_set('numbering_CALL', '');
$reset();
t_eq($mint(), 'CALL-00001', 'the default format is unchanged (backward compatible)');
t_eq($mint(), 'CALL-00002', 'the next number follows, gap-free');

// 2. A configured prefix, separator and digit width take effect.
setting_set('numbering_CALL', json_encode(['prefix' => 'IC', 'sep' => '/', 'pad' => 4, 'fy' => 0]));
$reset();
t_eq($mint(), 'IC/0001', 'a custom prefix, separator and width are honoured');
t_eq(numbering_preview('CALL'), 'IC/0042', 'the preview reflects the scheme');

// 3. Financial year in the number, YYYY-YY style. (FY derived from fy_start_month.)
setting_set('fy_start_month', 4);
setting_set('numbering_CALL', json_encode(['prefix' => 'TIR', 'sep' => '/', 'pad' => 4, 'fy' => 1, 'fy_style' => 'YYYY-YY']));
$reset();
$code = $mint();
t_ok(preg_match('#^TIR/\d{4}-\d{2}/0001$#', $code) === 1, "the financial year is embedded ($code)");

// 4. A "start from" floor is respected, but never issues below the real maximum.
setting_set('numbering_CALL', json_encode(['prefix' => 'JOB', 'sep' => '-', 'pad' => 5, 'start' => 1000]));
$reset();
t_eq($mint(), 'JOB-01000', 'numbering starts from the configured floor');
t_eq($mint(), 'JOB-01001', 'and increments from there');

// 5. Gap-safe against an out-of-shape imported code (lettered tail is ignored).
setting_set('numbering_CALL', '');
$reset();
db()->prepare("INSERT INTO num_probe (code) VALUES (?)")->execute(['CALL-E0149']); // an import
db()->prepare("INSERT INTO num_probe (code) VALUES (?)")->execute(['CALL-00007']);
t_eq($mint(), 'CALL-00008', 'a lettered imported code cannot poison the next number');

// 6. Collision-safe: if the computed code already exists, it steps past it.
setting_set('numbering_CALL', '');
$reset();
db()->prepare("INSERT INTO num_probe (code) VALUES (?)")->execute(['CALL-00001']);
t_eq($mint(), 'CALL-00002', 'an already-taken number is stepped past');

// 7. An unknown prefix (no scheme) still works via the original path.
$reset();
db()->exec("CREATE TABLE IF NOT EXISTS num_probe2 (id INTEGER PRIMARY KEY AUTOINCREMENT, x VARCHAR(60))");
$c = ops_next_code('num_probe2', 'x', 'ZZZ');
t_eq($c, 'ZZZ-00001', 'a prefix with no configured scheme falls back to the default format');

// tidy the setting so other tests see defaults
setting_set('numbering_CALL', '');
