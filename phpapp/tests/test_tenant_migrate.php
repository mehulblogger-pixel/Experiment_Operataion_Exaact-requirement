<?php
// Moving a file-backed (SQLite) workspace into MySQL. The MySQL write path is
// exercised in the browser against a real database; here we cover the pieces
// that decide whether that move is FAITHFUL and SAFE: the SQLite type mapping,
// the CREATE TABLE / CREATE INDEX generation, the routing-entry rewrite, and a
// real end-to-end table copy (SQLite -> SQLite stand-in) proving every row and
// column survives with no loss and no duplication on a re-run.

t_section('Move workspace storage to MySQL — engine');

if (!function_exists('_mig_my_type') || !function_exists('saas_tenant_migrate_to_mysql')) {
    t_ok(true, 'storage-move engine not present — skipped'); return;
}

// ---- SQLite affinity -> MySQL column type ---------------------------------
t_eq(_mig_my_type('INTEGER'), 'BIGINT', 'INTEGER maps to BIGINT');
t_eq(_mig_my_type('int'), 'BIGINT', 'lowercase int maps to BIGINT');
t_eq(_mig_my_type('TEXT'), 'LONGTEXT', 'TEXT maps to LONGTEXT');
t_eq(_mig_my_type('VARCHAR(200)'), 'LONGTEXT', 'VARCHAR maps to LONGTEXT (non-key)');
t_eq(_mig_my_type('REAL'), 'DOUBLE', 'REAL maps to DOUBLE');
t_eq(_mig_my_type('NUMERIC'), 'DECIMAL(20,6)', 'NUMERIC maps to DECIMAL');
t_eq(_mig_my_type(''), 'LONGTEXT', 'untyped column defaults to text-safe LONGTEXT');
t_eq(_mig_my_type('TEXT', true), 'VARCHAR(191)', 'a TEXT primary key becomes an indexable VARCHAR(191)');
t_eq(_mig_my_type('INTEGER', true), 'BIGINT', 'an INTEGER primary key stays BIGINT');

// ---- CREATE TABLE from column meta ----------------------------------------
$colsUsers = [
    ['name' => 'id',    'type' => 'INTEGER', 'notnull' => false, 'pk' => 1],
    ['name' => 'email', 'type' => 'TEXT',    'notnull' => true,  'pk' => 0],
    ['name' => 'score', 'type' => 'REAL',    'notnull' => false, 'pk' => 0],
];
$ddl = _mig_create_table_sql('users', $colsUsers);
t_ok(strpos($ddl, '`id` BIGINT NOT NULL') !== false, 'integer PK column is BIGINT NOT NULL');
t_ok(strpos($ddl, '`email` LONGTEXT NOT NULL') !== false, 'NOT NULL text column preserved');
t_ok(strpos($ddl, '`score` DOUBLE NULL') !== false, 'nullable real column preserved');
t_ok(strpos($ddl, 'PRIMARY KEY (`id`)') !== false, 'single integer PK has no length prefix');
t_ok(strpos($ddl, 'utf8mb4') !== false, 'table is created as utf8mb4');
t_ok(strpos($ddl, 'IF NOT EXISTS') !== false, 're-runnable: CREATE TABLE IF NOT EXISTS');

// A text primary key (like settings.skey) must get a (191) length prefix.
$colsSet = [
    ['name' => 'skey',   'type' => 'TEXT', 'notnull' => false, 'pk' => 1],
    ['name' => 'svalue', 'type' => 'TEXT', 'notnull' => false, 'pk' => 0],
];
$ddlSet = _mig_create_table_sql('settings', $colsSet);
t_ok(strpos($ddlSet, '`skey` VARCHAR(191) NOT NULL') !== false, 'text PK becomes VARCHAR(191) NOT NULL');
// VARCHAR(191) is already length-bounded, so the primary key needs no (191)
// prefix — only unbounded TEXT/BLOB columns do. The key is the bare column.
t_ok(strpos($ddlSet, 'PRIMARY KEY (`skey`)') !== false, 'a bounded VARCHAR PK needs no length prefix');

// ---- CREATE INDEX generation ----------------------------------------------
$typeOf = ['email' => 'LONGTEXT', 'id' => 'BIGINT'];
$uniqSql = _mig_index_sql('users', ['name' => 'ux_email', 'unique' => true, 'cols' => ['email']], $typeOf);
t_ok(strpos($uniqSql, 'CREATE UNIQUE INDEX') !== false, 'unique index kept as UNIQUE (upserts behave the same)');
t_ok(strpos($uniqSql, '`email`(191)') !== false, 'text column in an index is length-prefixed');
$intSql = _mig_index_sql('users', ['name' => 'ix_id', 'unique' => false, 'cols' => ['id']], $typeOf);
t_ok(strpos($intSql, '`id`)') !== false && strpos($intSql, '`id`(191)') === false, 'integer index column has no prefix');

// ---- Routing entry rewrite (sqlite -> mysql, preserving identity) ---------
$entry = _mig_route_entry(['company' => 'Xyz Recruit', 'status' => 'active', 'sqlite' => '/app/tenant-xyz.sqlite', 'pending' => ['x' => 1]],
    ['host' => 'localhost', 'name' => 'acc_xyz', 'user' => 'acc_u', 'pass' => 'secret']);
t_ok(!isset($entry['sqlite']), 'the file route is removed');
t_ok(($entry['db']['name'] ?? '') === 'acc_xyz' && ($entry['db']['user'] ?? '') === 'acc_u', 'the MySQL route is written');
t_eq($entry['company'], 'Xyz Recruit', 'the company name is preserved');
t_eq($entry['status'], 'active', 'the status is preserved');
t_ok(($entry['pending']['x'] ?? null) === 1, 'the pending owner details are preserved');

// ---- End-to-end copy: every row & column survives, re-run is idempotent ----
// A SQLite "target" stands in for MySQL so the copy loop runs for real. The
// INSERT SQL (backtick-quoted, positional placeholders) is valid on both.
$src = new PDO('sqlite::memory:'); $src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dst = new PDO('sqlite::memory:'); $dst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$src->exec('CREATE TABLE "people" (id INTEGER PRIMARY KEY, name TEXT, note TEXT, amt REAL)');
$dst->exec('CREATE TABLE `people` (id INTEGER PRIMARY KEY, name TEXT, note TEXT, amt REAL)');
$rows = [[1, 'Aï Shah', 'first ₹ row', 12.5], [2, "O'Brien", null, 0.0], [3, 'line
break', 'tab	here', 99.9]];
foreach ($rows as $r) { $st = $src->prepare('INSERT INTO people VALUES (?,?,?,?)'); $st->execute($r); }

$cols = array_map(fn($c) => $c['name'], _mig_sqlite_columns($src, 'people'));
t_eq($cols, ['id', 'name', 'note', 'amt'], 'column list is read from the source in order');
$n = _mig_copy_table($src, $dst, 'people', $cols);
t_eq($n, 3, 'all three rows are copied');
$back = $dst->query('SELECT id,name,note,amt FROM `people` ORDER BY id')->fetchAll(PDO::FETCH_NUM);
t_eq($back[0][1], 'Aï Shah', 'unicode text survives the copy');
t_eq($back[1][1], "O'Brien", 'a quote in the data survives the copy');
t_ok($back[1][2] === null, 'a NULL stays NULL (not turned into an empty string)');
t_eq($back[2][2], "tab\there", 'embedded whitespace survives the copy');

// Re-running the copy replaces rather than duplicates (idempotent).
$n2 = _mig_copy_table($src, $dst, 'people', $cols);
t_eq($n2, 3, 're-running copies the same rows');
t_eq((int) $dst->query('SELECT COUNT(*) FROM `people`')->fetchColumn(), 3, 'a second run leaves 3 rows, not 6 (no duplication)');

// ---- Storage info refuses to call a non-file workspace "at risk" ----------
$info = saas_tenant_storage_info('');
t_ok(($info['at_risk'] ?? true) === false, 'an unknown/empty workspace is never flagged at risk');
