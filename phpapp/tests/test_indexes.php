<?php
// The Step 1 indexing pass created indexes and the planner uses them.
t_section('indexing pass');

$n = count(t_indexes('ix_%'));   // M15 — engine-independent (sqlite_master / information_schema)
t_ok($n > 50, "secondary indexes are present ($n)");

// Re-running the pass must never error (idempotent CREATE INDEX handling).
t_nothrow('indexes_migrate() is safe to call again', function () { indexes_migrate(); });

// A status-filtered register query should use an index, not scan the table.
$usesIndex = t_query_uses_index("SELECT * FROM calls WHERE status='OPEN'", 'ix_');
t_ok($usesIndex, 'a status filter on calls uses an ix_ index (not a full scan)');

// The custom-field lookup — read on nearly every entity screen — is indexed.
$usesCv = t_query_uses_index("SELECT * FROM custom_values WHERE entity='call' AND record_id=1", 'ix_cv');
t_ok($usesCv, 'custom-field lookups use the (entity, record_id) index');
