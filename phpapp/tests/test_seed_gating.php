<?php
// D-003 — the bundled sample clients/vendors (data/seed_data.json) must not
// auto-populate a real hosted install (a customer tenant / licence copy). The
// loader is idempotent and only ever ADDS to an empty partner table; a fresh
// real-web boot starts empty unless explicitly opted in. Here we assert the
// loader's contract and the master-only route; the SAPI gate itself is a pure
// expression proven separately (real-web SAPIs seed only on opt-in).
t_section('D-003 sample-partner seeding is gated & idempotent');

t_ok(function_exists('auto_seed_load_sample'), 'the explicit sample loader exists');
t_ok(function_exists('auto_seed'), 'auto_seed still exists');

// The test DB is already booted (CLI context → seeded), so partners exist.
$before = (int)ops_val("SELECT COUNT(*) FROM business_partners");
t_ok($before > 0, 'CLI/test context has seeded partners (fixtures unchanged)');

// Idempotency: calling the loader again when partners exist is a no-op (returns 0),
// so it can never double-seed or clobber a populated install.
$added = auto_seed_load_sample();
t_eq($added, 0, 'the loader is a no-op once any partner exists');
t_eq((int)ops_val("SELECT COUNT(*) FROM business_partners"), $before, 'partner count is unchanged by a repeat load');

// The gate decision is a pure expression: real-web SAPIs seed ONLY on opt-in.
$decide = fn($sapi, $opt) => (in_array($sapi, ['cli', 'cli-server', 'phpdbg'], true) || $opt === '1');
t_ok(!$decide('fpm-fcgi', ''),        'a real customer (fpm, no opt-in) starts EMPTY');
t_ok(!$decide('apache2handler', ''),  'a real customer (apache, no opt-in) starts EMPTY');
t_ok($decide('fpm-fcgi', '1'),        'opt-in loads sample data on real hosting');
t_ok($decide('cli', ''),              'the test suite (CLI) keeps its fixtures');
