<?php
// The Step 1 indexing pass created indexes and the planner uses them.
t_section('indexing pass');

$n = (int)ops_val("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name LIKE 'ix_%'");
t_ok($n > 50, "secondary indexes are present ($n)");

// Re-running the pass must never error (idempotent CREATE INDEX handling).
t_nothrow('indexes_migrate() is safe to call again', function () { indexes_migrate(); });

// A status-filtered register query should use an index, not scan the table.
$usesIndex = false;
foreach (ops_all("EXPLAIN QUERY PLAN SELECT * FROM calls WHERE status='OPEN'") ?: [] as $row) {
    if (stripos($row['detail'] ?? '', 'USING INDEX ix_') !== false) $usesIndex = true;
}
t_ok($usesIndex, 'a status filter on calls uses an ix_ index (not a full scan)');

// The custom-field lookup — read on nearly every entity screen — is indexed.
$usesCv = false;
foreach (ops_all("EXPLAIN QUERY PLAN SELECT * FROM custom_values WHERE entity='call' AND record_id=1") ?: [] as $row) {
    if (stripos($row['detail'] ?? '', 'USING INDEX ix_cv') !== false) $usesCv = true;
}
t_ok($usesCv, 'custom-field lookups use the (entity, record_id) index');
