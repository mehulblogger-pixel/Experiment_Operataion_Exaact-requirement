<?php
/**
 * EXAACT audit, Phase F / D7 — trace every headline number to its source.
 *
 * The test database holds no operational data (0 work orders, 0 jobs), so two
 * dashboards cannot be compared by reading their figures. What CAN be
 * established, and is arguably more durable, is whether they would agree BY
 * CONSTRUCTION:
 *
 *   A. Which functions compute a headline number, and how many there are.
 *   B. For the numbers that appear on more than one screen (revenue, profit,
 *      margin, outstanding, man-days), do the screens call the SAME function
 *      or each roll their own?
 *   C. Does anything RECOMPUTE a figure that MGH Books owns? The Books
 *      contract says "The ERP never recomputes any of it" — this checks it.
 *   D. Is the kpi_* framework (defs, targets, snapshots, versions, alerts)
 *      read or written by anything?
 *
 * Read-only: boots the app, reflects over its own functions, and greps the
 * source for call sites. Prints a report.
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';

$LIB = glob($root . '/lib/*.php');
$VIEWS = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/views',
        FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) if (substr($f, -4) === '.php') $VIEWS[] = (string)$f;
$ALL = array_merge($LIB, $VIEWS);

function src_all(array $files) {
    static $cache = null;
    if ($cache !== null) return $cache;
    $out = [];
    foreach ($files as $f) $out[$f] = (string)file_get_contents($f);
    return $cache = $out;
}
$SRC = src_all($ALL);

function callers($fn, array $src) {
    $hits = [];
    foreach ($src as $file => $code) {
        // a call, not the definition
        if (preg_match_all('/(?<!function )\b' . preg_quote($fn, '/') . '\s*\(/', $code, $m)) {
            $n = count($m[0]);
            // discount the definition itself
            if (preg_match('/function\s+' . preg_quote($fn, '/') . '\s*\(/', $code)) $n--;
            if ($n > 0) $hits[basename($file)] = $n;
        }
    }
    return $hits;
}

echo "=========== A. HOW MANY HEADLINE-NUMBER FUNCTIONS ===========\n";
$fns = get_defined_functions()['user'];
$metric = array_values(array_filter($fns, fn($f) =>
    preg_match('/(_counts?|_total|_sum|_kpi|_stats?|_summary|_readiness|_pending_count|_ageing|_aging|_margin|_profit|_revenue)$/', $f)));
sort($metric);
echo count($metric) . " functions whose name says they compute a headline number.\n";

echo "\n=========== B. ONE NUMBER, HOW MANY IMPLEMENTATIONS ===========\n";
//  For each business figure, which functions claim to produce it, and who
//  calls each. A figure produced by one function used everywhere cannot
//  disagree with itself. A figure produced by several can.
$figures = [
    'revenue'     => '/revenue/i',
    'profit'      => '/profit/i',
    'margin'      => '/margin/i',
    'outstanding' => '/outstand|receivab|ageing|aging/i',
    'man-days'    => '/manday|man_day|person_day|utilis|utiliz/i',
    'cost'        => '/_cost|cost_/i',
];
foreach ($figures as $label => $pat) {
    $cands = array_values(array_filter($fns, fn($f) => preg_match($pat, $f)));
    sort($cands);
    printf("\n-- %s: %d function(s) mention it\n", strtoupper($label), count($cands));
    foreach (array_slice($cands, 0, 14) as $f) {
        $c = callers($f, $SRC);
        $where = $c ? implode(', ', array_map(fn($k, $v) => "$k×$v", array_keys($c), $c)) : 'NO CALLERS FOUND';
        printf("   %-34s %s\n", $f, substr($where, 0, 86));
    }
    if (count($cands) > 14) echo "   … and " . (count($cands) - 14) . " more\n";
}

echo "\n=========== C. DOES ANYTHING RECOMPUTE WHAT BOOKS OWNS? ===========\n";
//  mgh_books_receiver/README.md: "The ERP never recomputes any of it."
//  Books owns paid, outstanding and IRN. So a SQL SUM over receipts or
//  allocations inside phpapp would be a violation.
$suspect = 0;
foreach ($SRC as $file => $code) {
    if (!preg_match_all('/SUM\s*\(\s*[^)]*\)/i', $code, $m)) continue;
    foreach ($m[0] as $expr) {
        if (!preg_match('/amount|paid|settl|outstand|alloc|tds/i', $expr)) continue;
        // find the surrounding statement for context
        $pos = strpos($code, $expr);
        $ctx = substr($code, max(0, $pos - 160), 320);
        if (!preg_match('/receipt|invoice|alloc|credit_note/i', $ctx)) continue;
        printf("   %-22s %s\n", basename($file), preg_replace('/\s+/', ' ', substr($expr, 0, 70)));
        $suspect++;
    }
}
echo $suspect === 0
    ? "   none found — no SQL SUM over receipt/invoice amounts in phpapp\n"
    : "   $suspect place(s) to read: each must be a DISPLAY of EXAACT's own records,\n"
    . "   not a recomputation of what Books reports back.\n";

echo "\n=========== D. IS THE KPI FRAMEWORK USED? ===========\n";
foreach (['kpi_defs', 'kpi_targets', 'kpi_snapshots', 'kpi_versions', 'kpi_alerts'] as $t) {
    $readers = [];
    foreach ($SRC as $file => $code)
        if (strpos($code, $t) !== false) $readers[] = basename($file);
    $rows = -1;
    try { $rows = (int) ops_val("SELECT COUNT(*) FROM \"$t\""); } catch (Throwable $e) {}
    printf("   %-16s rows=%-4s referenced in: %s\n", $t, $rows < 0 ? '?' : $rows,
        $readers ? implode(', ', array_slice($readers, 0, 6)) : 'NOTHING');
}

echo "\n=========== E. THE DISAGREEMENT COUNTERS ===========\n";
//  The system has functions that COUNT its own disagreements. What they
//  compare is the most useful thing in this audit.
foreach (['billable_mismatch_count', 'calls_status_disagreement_count',
          'revrecon_count', 'costrecon_count', 'reimb_dedup_count'] as $f) {
    if (!function_exists($f)) { printf("   %-34s (not defined)\n", $f); continue; }
    $c = callers($f, $SRC);
    printf("   %-34s called by: %s\n", $f,
        $c ? implode(', ', array_keys($c)) : 'NO CALLERS');
}
