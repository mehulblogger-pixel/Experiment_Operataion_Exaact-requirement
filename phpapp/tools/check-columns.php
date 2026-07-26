<?php
// ===========================================================================
//  Every column a save writes must actually exist
//
//  A form gained two boxes, the save was told to write them, and nobody added
//  the columns to the table. Nothing complained until somebody pressed Allocate
//  on the live site and got a database error in their face:
//
//      Unknown column 'schedule_weekdays' in 'INSERT INTO'
//
//  PHP cannot catch that and neither can `php -l`. This can: boot a throwaway
//  database so every migration has run, then check each save's field list
//  against the columns that really exist. Run from tools/lint.sh, so it is
//  checked on the way out rather than by a customer.
// ===========================================================================

$root = dirname(__DIR__);
putenv('DB_DRIVER=sqlite');
$tmp = sys_get_temp_dir() . '/exaact-colcheck-' . getmypid() . '.sqlite';
@unlink($tmp);
putenv('SQLITE_PATH=' . $tmp);      // a throwaway database, never the real one

// A bare CLI context — enough for the migrations, which is all this needs.
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
if (session_status() === PHP_SESSION_NONE) @session_start();

foreach (['db','helpers','ops','lookups','access','terms','compose','crm','pdf','ai','workforce',
          'orgadmin','contracts','security','compliance','costing','reset','partnerimport','mis',
          'dedupe','joblock','schedule','idems','seed_demo'] as $m) {
    $f = "$root/lib/$m.php";
    if (file_exists($f)) require_once $f;
}

try {
    boot();
} catch (Throwable $e) {
    echo "FAIL - could not build a database to check against: " . $e->getMessage() . "\n";
    @unlink($tmp);
    exit(1);
}

// Every list of columns a save writes, and the table it writes them to.
$checks = [
    ['calls', 'call_save_fields'],
    ['jobs',  'job_save_fields'],
];

$bad = 0; $checked = 0;
foreach ($checks as [$table, $fn]) {
    if (!function_exists($fn)) { echo "FAIL - $fn() is missing.\n"; $bad++; continue; }
    $have = [];
    foreach (db_columns($table) as $c) $have[strtolower($c)] = true;
    if (!$have) { echo "FAIL - table $table has no columns (did the migration run?).\n"; $bad++; continue; }
    foreach ($fn() as $col) {
        $checked++;
        if (!isset($have[strtolower($col)])) {
            echo "FAIL - $fn() writes '$col' but $table has no such column.\n";
            $bad++;
        }
    }
}

@unlink($tmp);
if ($bad) {
    echo "\n$bad column(s) named by a save do not exist. Add them in the matching\n"
       . "migrate() — and add them to the boot probe in index.php, or a live site\n"
       . "that already has the table will never gain them.\n";
    exit(1);
}
echo "OK - $checked columns written by saves all exist in the schema.\n";
