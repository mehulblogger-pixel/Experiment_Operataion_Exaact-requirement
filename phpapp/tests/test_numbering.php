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

// ---- Money documents (invoice / receipt / credit note) via books_next_number ----
// Uses the REAL invoices/credit_notes tables, because books_next_number keys the
// scheme off the table name.
t_section('configurable numbering — money documents');
books_migrate();
$mintInv = function ($series, $fy) { $c = books_next_number('invoices', 'invoice_no', $series, $fy);
    db()->prepare("INSERT INTO invoices (invoice_no, created_at) VALUES (?,?)")->execute([$c, date('c')]); return $c; };

// 1. Default reproduces the old SERIES/2627/0001 format exactly (backward compatible).
setting_set('numbering_INV', '');
db()->exec("DELETE FROM invoices");
t_eq($mintInv('INV', '2026-27'), 'INV/2627/0001', 'the default invoice format is unchanged');
t_eq($mintInv('INV', '2026-27'), 'INV/2627/0002', 'and increments within the year');

// 2. The series resets each financial year (a GST requirement).
t_eq($mintInv('INV', '2027-28'), 'INV/2728/0001', 'the number resets to 0001 in a new financial year');
t_eq($mintInv('INV', '2026-27'), 'INV/2627/0003', 'the previous year continues from where it was');

// 3. The office-specific invoice series is kept as the prefix.
t_eq($mintInv('AMD', '2026-27'), 'AMD/2627/0001', 'an office-specific series is honoured');

// 4. A configured format (separator, width, FY style) takes effect.
setting_set('numbering_INV', json_encode(['sep' => '-', 'pad' => 5, 'fy' => 1, 'fy_style' => 'YYYY-YY']));
db()->exec("DELETE FROM invoices");
t_eq($mintInv('INV', '2026-27'), 'INV-2026-27-00001', 'a custom separator, width and FY style are honoured on invoices');

// 5. Credit notes follow their own configurable scheme.
setting_set('numbering_CN', '');
db()->exec("DELETE FROM credit_notes");
$cn = books_next_number('credit_notes', 'cn_no', 'CN', '2026-27');
t_eq($cn, 'CN/2627/0001', 'credit notes number in their own FY series by default');

setting_set('numbering_INV', '');
