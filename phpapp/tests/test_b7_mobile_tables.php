<?php
// ============================================================================
//  B7 — MOBILE LISTS AND TABLES
//
//  The finding B7 was given (F-A4-3) says "249 of 251 table views scroll
//  sideways on a phone" with "any card fallback: 2". Measured on the running
//  application at 360x800, 390x844 and 412x915, crawling 320 pages:
//
//      pages whose BODY scrolls sideways ......... 0
//      tables already rendered as labelled cards . 100
//      pages with an INNER horizontal scroll ..... 11, in 5 families
//
//  The card engine initResponsiveTables() has existed since b963490 (27 Aug),
//  an ANCESTOR of the commit that added the audit. The audit quoted the
//  overflow-x:auto FALLBACK and missed the card rules ten lines below it in the
//  same media block — the same mistake B6 found in F-A5-1.
//
//  What was really wrong was one predicate. The engine's own comment says an
//  entry grid is left alone but "a data table with an inline action form is
//  fine to card"; the test did not implement that — it skipped ANY table
//  holding ANY field. That left four registers scrolling sideways with no hint
//  more columns existed: the availability board hid the status AND the
//  Set-status control, report review hid why a report came back, contract
//  openings hid the stage, and to-bill hid the value.
//
//  The rule that matters most here: ONE CONTROL PER ROW IS A REGISTER, NOT A
//  GRID. People type ACROSS an entry grid, so fields land in two or more
//  columns; a register has a single column of controls. That is the whole
//  distinction, and it is what fieldColumnCount() measures.
// ============================================================================

t_section('B7 — mobile lists and tables');

$b7js  = (string) @file_get_contents(__DIR__ . '/../assets/js/app.js');
$b7css = (string) @file_get_contents(__DIR__ . '/../assets/css/app.css');

// ---- A · one engine, corrected — not a second one -------------------------
t_eq(substr_count($b7js, 'function initResponsiveTables'), 1, 'A1 · the one card engine is still the only one');
t_eq(substr_count($b7js, 'function fieldColumnCount'), 1, 'A2 · the new helper is defined exactly once');
foreach (['initMobileCards', 'buildRecordCard', 'mobileTable', 'rt_engine', 'initCardList'] as $b7new)
    t_ok(strpos($b7js, 'function ' . $b7new) === false, "A3 · no second mobile engine called $b7new was introduced");
//  B7 was allowed ONE new presentation component. It did not need it: the card
//  layout already existed and only the gate in front of it was wrong.
t_ok(strpos($b7css, '.rtable') !== false, 'A4 · the existing card layout is untouched and still present');
//  Presence, not a count: this rule's selector is written twice in app.css
//  ("table.rtable td[data-label]::before,table.rtable td[data-label]::before"),
//  which is a pre-existing harmless duplication, not something B7 introduced.
t_ok(strpos($b7css, 'table.rtable td[data-label]::before') !== false,
     'A5 · …including the label it prints before each value');

// ---- B · the predicate now asks the question its comment asks -------------
$b7old = "if (t.querySelector('input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea')) return;";
t_ok(strpos($b7js, $b7old) === false, 'B1 · the old "any field at all" test is gone');
t_ok(strpos($b7js, 'if (fieldColumnCount(t) > 1) return;') !== false,
     'B2 · a table is skipped only when fields span MORE THAN ONE column');
//  The helper must count DISTINCT COLUMNS, not fields. Counting fields would
//  skip a 40-row register that has one control on every row.
t_ok(preg_match('/function fieldColumnCount[\s\S]{0,700}?seen\[i\]/', $b7js) === 1,
     'B3 · it counts distinct column indexes, not the number of fields');
t_ok(preg_match('/function fieldColumnCount[\s\S]{0,700}?if \(\+\+n > 1\) return n;/', $b7js) === 1,
     'B4 · and stops at two, so an 80-row board is not scanned in full');

// ---- C · the other two guards are untouched -------------------------------
//  These are what keep the /call status-history table a table: it is headerless
//  (<tbody> only — timestamp, transition, actor), so there are no headings to
//  label its cells with. Carding it would print values with no names.
t_ok(strpos($b7js, "var head = t.querySelector('thead tr');") !== false,
     'C1 · a table with no header row is still left alone');
t_ok(strpos($b7js, '// headerless layout table — leave it') !== false,
     'C2 · …and so is one whose header cells are all empty');
t_ok(strpos($b7js, "querySelectorAll('table.grid, table.dt, table.tbl')") !== false,
     'C3 · the set of tables considered did not widen');

// ---- D · nothing outside presentation moved -------------------------------
//  B7 is a presentation change. If it ever touches a query, a route, a
//  permission or a tenant scope, that is a different stage.
$b7git = trim((string) @shell_exec('cd ' . escapeshellarg(__DIR__ . '/..') . ' && git diff --name-only HEAD~1 2>/dev/null'));
if ($b7git !== '') {
    foreach (explode("\n", $b7git) as $b7f) {
        $b7f = trim($b7f);
        if ($b7f === '' || strpos($b7f, 'docs/') === 0 || strpos($b7f, 'phpapp/tests/') === 0) continue;
        $b7ok = in_array($b7f, ['phpapp/assets/js/app.js', 'phpapp/assets/css/app.css', 'phpapp/deploy-check.php'], true);
        t_ok($b7ok, "D1 · only presentation files changed — saw \"$b7f\"");
    }
}
//  Belt and braces: the engine must not have grown a data path.
foreach (['fetch(', 'XMLHttpRequest', 'ops_all', 'SELECT '] as $b7q)
    t_ok(preg_match('/function initResponsiveTables[\s\S]{0,2600}?' . preg_quote($b7q, '/') . '/', $b7js) !== 1,
         "D2 · the card engine still issues no data request ($b7q)");
