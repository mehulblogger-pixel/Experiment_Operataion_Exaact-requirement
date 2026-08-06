<?php
// The app builds its whole schema and seeds an admin on a clean database.
t_section('boot & schema');

$names = array_column(ops_all("SELECT name FROM sqlite_master WHERE type='table'") ?: [], 'name');
t_ok(count($names) > 80, 'many tables created (' . count($names) . ')');
foreach (['users', 'calls', 'jobs', 'business_partners', 'quotations', 'quote_lines',
          'report_docs', 'idems_audit', 'lookup_types', 'lookup_values', 'custom_fields',
          'custom_values', 'invoices', 'partner_contracts'] as $tbl) {
    t_ok(in_array($tbl, $names, true), "table exists: $tbl");
}

$admin = ops_one("SELECT id, username FROM users WHERE username='admin' OR is_superuser=1 LIMIT 1");
t_ok(!empty($admin), 'an admin / superuser account was seeded');

// The secondary indexes from Step 1 must have been created during boot.
$ix = ops_all("SELECT name FROM sqlite_master WHERE type='index' AND name LIKE 'ix_%'") ?: [];
t_ok(count($ix) > 50, 'secondary indexes created during boot (' . count($ix) . ')');

// A second boot() must be a clean no-op (idempotent migrations).
t_nothrow('boot() is idempotent (safe to run twice)', function () { boot(); });
