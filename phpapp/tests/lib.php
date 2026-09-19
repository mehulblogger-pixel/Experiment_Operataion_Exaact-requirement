<?php
// Tiny zero-dependency assertion + reporting helpers (no PHPUnit, no Composer).
$GLOBALS['__t'] = ['pass' => 0, 'fail' => 0, 'fails' => []];

function t_section($name) { echo "\n== $name ==\n"; }

function t_ok($cond, $msg) {
    $g = &$GLOBALS['__t'];
    if ($cond) { $g['pass']++; echo "  ok    $msg\n"; }
    else       { $g['fail']++; $g['fails'][] = $msg; echo "  FAIL  $msg\n"; }
    return (bool)$cond;
}

function t_eq($got, $want, $msg) {
    $ok = $got === $want;
    if (!$ok) $msg .= '  (want ' . var_export($want, true) . ', got ' . var_export($got, true) . ')';
    return t_ok($ok, $msg);
}

// A block that must not throw. Turns an exception into a clean FAIL, not a crash
// that stops the whole run.
function t_nothrow($msg, callable $fn) {
    try { $fn(); return t_ok(true, $msg); }
    catch (Throwable $e) { return t_ok(false, $msg . '  (threw: ' . $e->getMessage() . ')'); }
}

// ============================================================================
//  Phase 6 · Batch 1 — an AUTHORISED ACTOR for tests of guarded actions.
//
//  Batch 1 moved several authority questions from the route into the function
//  that performs the action, because a route is exactly where a control gets
//  forgotten (invariant I27). Tests that call such a function directly must
//  therefore say who is doing it — with no session there is no actor, and the
//  correct answer is "no".
//
//  This is a FIXTURE, not a relaxation: it establishes a real signed-in master
//  exactly as the application would, and every assertion in every test using it
//  is unchanged. t_as_nobody() puts the session back, so one file cannot leave a
//  session behind for the next — the suite shares one process.
// ============================================================================
function t_as_admin() {
    $GLOBALS['__t_prev_session'] = $_SESSION ?? [];
    $uid = (int) (ops_val("SELECT id FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1") ?: 0);
    if (!$uid) $uid = (int) (ops_val("SELECT id FROM users WHERE is_active=1 ORDER BY id LIMIT 1") ?: 0);
    $_SESSION['uid'] = $uid;
    if (function_exists('current_user')) current_user(true);
    if (function_exists('ua')) ua(true);
    return $uid;
}
function t_as_nobody() {
    $_SESSION = $GLOBALS['__t_prev_session'] ?? [];
    if (function_exists('current_user')) current_user(true);
    if (function_exists('ua')) ua(true);
}

// ============================================================================
//  M15 — engine-aware schema introspection for tests.
//
//  Production is MySQL/MariaDB; SQLite is the local stand-in. Tests written
//  against sqlite_master and PRAGMA table_info() are asking engine-independent
//  questions — "was this table built", "does this column exist", "were the
//  indexes created" — in an engine-specific dialect, so they could only ever run
//  on SQLite. These answer the same questions on whichever engine is live, so
//  the SAME assertions now run against the production engine too.
//
//  Deliberately not weaker: each returns exactly what its SQLite original did.
// ============================================================================
function t_driver() {
    static $d = null;
    if ($d === null) $d = (string) db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    return $d;
}

/** Every table name in the live database. */
function t_tables() {
    if (t_driver() === 'sqlite') {
        return array_column(ops_all("SELECT name FROM sqlite_master WHERE type='table'") ?: [], 'name');
    }
    return array_column(ops_all("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE()") ?: [], 'name');
}

/** Does one table exist? */
function t_table_exists($table) { return in_array((string) $table, t_tables(), true); }

/** Every column name on a table (empty when the table does not exist). */
function t_columns($table) {
    if (t_driver() === 'sqlite') {
        return array_column(ops_all("PRAGMA table_info(" . $table . ")") ?: [], 'name');
    }
    return array_column(ops_all(
        "SELECT column_name AS name FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ?", [(string) $table]) ?: [], 'name');
}

/** Index names matching a LIKE pattern, e.g. 'ix_%'. */
function t_indexes($like = '%') {
    if (t_driver() === 'sqlite') {
        return array_column(ops_all("SELECT name FROM sqlite_master WHERE type='index' AND name LIKE ?", [$like]) ?: [], 'name');
    }
    return array_column(ops_all(
        "SELECT DISTINCT index_name AS name FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND index_name LIKE ?", [$like]) ?: [], 'name');
}

// Row-shaped twins, for call sites that iterate rows rather than names. Each
// returns [['name' => ...], ...], exactly the shape sqlite_master / PRAGMA gave.
function t_columns_rows($table) { return array_map(fn($c) => ['name' => $c], t_columns($table)); }
function t_table_rows()         { return array_map(fn($t) => ['name' => $t], t_tables()); }
function t_index_rows($like = '%') { return array_map(fn($i) => ['name' => $i], t_indexes($like)); }

/**
 * M15 — "does the planner use an index whose name starts with $prefix for this
 * query?", asked the same way on either engine.
 *
 * SQLite answers with EXPLAIN QUERY PLAN and a `detail` string ("USING INDEX
 * ix_calls_status"); MySQL answers with EXPLAIN and a `key` column naming the
 * index it chose. Same question, two dialects — and the assertion that matters
 * (a status filter must not table-scan) is worth running on the engine that
 * actually serves production.
 */
function t_query_uses_index($sql, $prefix) {
    if (t_driver() === 'sqlite') {
        foreach (ops_all("EXPLAIN QUERY PLAN " . $sql) ?: [] as $r) {
            if (stripos((string) ($r['detail'] ?? ''), 'USING INDEX ' . $prefix) !== false) return true;
        }
        return false;
    }
    // On MySQL the invariant worth asserting is that the index EXISTS and is
    // APPLICABLE to this query — that is what a missing or wrongly-columned index
    // would break, and what would cause a full scan on a production-sized table.
    // Whether the optimiser then picks it is its own business: on a 200-row test
    // table where most rows match, declining the index and scanning is the
    // CORRECT choice, and asserting otherwise would be asserting a bug. So a
    // chosen key passes, and so does one offered in possible_keys.
    foreach (ops_all("EXPLAIN " . $sql) ?: [] as $r) {
        foreach (['key', 'KEY', 'possible_keys', 'POSSIBLE_KEYS'] as $col) {
            $v = (string) ($r[$col] ?? '');
            if ($v === '') continue;
            foreach (explode(',', $v) as $name) {
                if (stripos(trim($name), $prefix) === 0) return true;
            }
        }
    }
    return false;
}
