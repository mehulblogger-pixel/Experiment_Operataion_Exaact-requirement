<?php
// ============================================================================
//  Per-database migration guard (db_epoch).
//
//  The app's ~125 schema migrations each run once, guarded by a static flag.
//  That flag used to be per-PROCESS, so once the control database had booted in
//  a PHP worker, opening a SECOND database in the same worker (a new company's
//  first request, or "Log in as") built NOTHING — the guards were already
//  "spent". On multi-worker hosting right after a deploy this left a company's
//  own database empty and its login broken ("workspace is still being set up").
//
//  The guards now key off db_epoch(), a counter bumped whenever the live
//  connection is switched (db(true)). So the SAME migration runs again for the
//  next database opened in the same process — the company gets its full schema
//  no matter what was booted before it. This proves that mechanism on the
//  control database (no second database needed, nothing left polluted).
// ============================================================================

t_section('Schema guards run once PER DATABASE, not once per process');

t_ok(function_exists('db_epoch'), 'the database-epoch counter exists');
t_ok(function_exists('form_tokens_migrate'), 'a representative guarded migration exists');

// Make sure the representative table exists, then note the current epoch.
form_tokens_migrate();
$haveBefore = true;
try { db()->query("SELECT token FROM form_tokens LIMIT 1"); } catch (Throwable $e) { $haveBefore = false; }
t_ok($haveBefore, 'the guarded migration created its table');

$epoch0 = db_epoch();

// Drop the table, then call the migration again WITHOUT switching databases.
// The guard is spent for this epoch, so it must NOT rebuild — this proves the
// guard is genuinely active (the test is meaningful).
db()->exec("DROP TABLE form_tokens");
form_tokens_migrate();                       // same epoch → guard skips
$rebuiltSameEpoch = true;
try { db()->query("SELECT token FROM form_tokens LIMIT 1"); } catch (Throwable $e) { $rebuiltSameEpoch = false; }
t_ok(!$rebuiltSameEpoch, 'within one database the migration runs only once (guard is active)');

// Now switch the live connection (as choosing/provisioning a company does). The
// epoch advances, so the very same migration runs again and rebuilds the table
// — exactly what lets a second database get its full schema in one process.
db(true);                                    // drop the connection → epoch++
db();                                        // reopen (still the control DB here)
t_ok(db_epoch() > $epoch0, 'switching the live connection advances the database epoch');

form_tokens_migrate();                        // new epoch → guard runs again
$rebuiltNewEpoch = true;
try { db()->query("SELECT token FROM form_tokens LIMIT 1"); } catch (Throwable $e) { $rebuiltNewEpoch = false; }
t_ok($rebuiltNewEpoch, 'after a connection switch the migration rebuilds the schema for the new database');
