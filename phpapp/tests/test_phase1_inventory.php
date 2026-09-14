<?php
// Phase 1 · Step 1 — the read-only entitlement inventory engine.
// The tool's whole value depends on two properties: it must compute entitlement
// exactly as lib/licence.php does, and it must be incapable of writing. Both are
// asserted here, the second against a real database.

require_once __DIR__ . '/../tools/phase1_inventory_engine.php';

t_section('Phase 1 Step 1 — read-only entitlement inventory');

if (!function_exists('p1_effective_modules') || !defined('PRODUCT_MODULES')) {
    t_ok(false, 'inventory engine or PRODUCT_MODULES missing — this must FAIL, not skip'); return;
}

// ---- 1. The read-only interlock -------------------------------------------
foreach (['SELECT 1', '  select * from settings', 'SHOW TABLES', 'PRAGMA table_info(x)',
          "-- note\nSELECT 1", "/* c */ SELECT 1"] as $ok)
    t_ok(p1_is_read_only_sql($ok), 'allows read: ' . str_replace("\n", ' ', substr($ok, 0, 28)));

foreach (['INSERT INTO settings VALUES(1)', 'UPDATE settings SET svalue=1', 'DELETE FROM settings',
          'DROP TABLE settings', 'ALTER TABLE settings ADD x INT', 'REPLACE INTO settings VALUES(1)',
          'TRUNCATE settings', 'CREATE TABLE x (a INT)', "-- hide\nUPDATE settings SET a=1",
          '/* hide */ DELETE FROM settings', 'SELECT 1; DROP TABLE settings', ''] as $bad)
    t_ok(!p1_is_read_only_sql($bad), 'refuses write: ' . str_replace("\n", ' ', substr($bad, 0, 30)) ?: 'refuses empty');

// Prove it against a REAL database: a write pushed through the guard must throw,
// and the data must be untouched afterwards.
$db = new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE settings (skey TEXT PRIMARY KEY, svalue TEXT)');
$db->exec("INSERT INTO settings VALUES ('saas_entitled_modules','hr'), ('modules_off','sales'), ('saas_provisioned','1')");
$threw = false;
try { p1_ro_query($db, "UPDATE settings SET svalue='EVERYTHING' WHERE skey='saas_entitled_modules'"); }
catch (Throwable $e) { $threw = true; }
t_ok($threw, 'a write pushed through the guard throws');
t_eq($db->query("SELECT svalue FROM settings WHERE skey='saas_entitled_modules'")->fetchColumn(), 'hr',
     'the data is provably unchanged after the refused write');

// ---- 2. Module parsing ------------------------------------------------------
t_eq(p1_parse_modules('hr, sales'), ['hr', 'sales'], 'parses a valid list');
t_eq(p1_parse_modules('hr,admin'), ['hr'], 'core keys are excluded from a ceiling, as licence.php does');
t_eq(p1_parse_modules('hr,notamodule,,hr'), ['hr'], 'invalid and duplicate keys are dropped');
t_eq(p1_parse_modules(''), [], 'empty parses to empty');

// ---- 3. Effective entitlement — today vs default-deny ----------------------
$cat = p1_module_catalogue();
t_ok(in_array('admin', $cat['core'], true), 'admin is core');
t_ok(in_array('hr', $cat['sellable'], true) && in_array('operations', $cat['sellable'], true), 'hr and operations are sellable');

$allNow = p1_effective_modules('', '', false);
t_ok(count($allNow) === count($cat['core']) + count($cat['sellable']),
     'TODAY: a blank ceiling grants every module (the fail-open defect, reproduced)');

$denyAll = p1_effective_modules('', '', true);
t_eq($denyAll, $cat['core'], 'DEFAULT-DENY: a blank ceiling grants core only');

t_eq(p1_effective_modules('hr', '', false), p1_effective_modules('hr', '', true),
     'a workspace WITH a ceiling is unaffected by the change');
t_ok(in_array('hr', p1_effective_modules('hr', '', true), true), 'its entitled module survives');
t_ok(!in_array('sales', p1_effective_modules('hr', '', true), true), 'a non-entitled module stays off');
t_ok(!in_array('sales', p1_effective_modules('', 'sales', false), true), 'the tenant\'s own modules_off choice is honoured');

// ---- 4. Risk classification — never guesses -------------------------------
$mk = fn(array $o) => p1_classify($o + ['would_lose' => [], 'control_modules' => [], 'error' => '', 'licence_key' => '']);
t_eq($mk([])['risk'], 'SAFE', 'nothing lost => SAFE');
// A workspace the control database records as already set up, whose data will
// not open, is a genuine fault.
t_eq($mk(['error' => 'refused', 'control_provisioned_at' => '2026-09-01T00:00:00+00:00'])['risk'],
     'ERROR', 'a workspace that WAS set up and will not open => ERROR');

// A company created but never signed in to has no database yet — lazy
// provisioning working as designed. Reporting it in red as an ERROR sends the
// operator hunting for a problem that does not exist, which is exactly what
// happened on the live server minutes after a new company was added.
$newco = $mk(['error' => 'workspace data file not found']);
t_eq($newco['risk'], 'NOT_YET_OPENED', 'a company never signed in to is NOT an error');
t_eq($newco['severity'], 'NONE', 'and carries no severity');
t_ok(stripos($newco['note'], 'Nothing is wrong') !== false, 'and says so plainly');
t_eq($mk(['licence_key' => 'present', 'would_lose' => ['hr']])['risk'], 'LICENCE_GOVERNED', 'a signed licence outranks the ceiling');
t_eq($mk(['would_lose' => ['hr'], 'control_modules' => ['hr']])['risk'], 'RECOVERABLE', 'loss + control record => RECOVERABLE');
$amb = $mk(['would_lose' => ['hr']]);
t_eq($amb['risk'], 'AMBIGUOUS', 'loss + no record anywhere => AMBIGUOUS');
t_eq($amb['severity'], 'CRITICAL', 'ambiguous is CRITICAL');
t_ok(strpos($amb['note'], 'do not guess') !== false, 'the ambiguous case explicitly says do not guess');

// ---- 5. Route resolution, without exposing credentials --------------------
$r = p1_resolve_route(json_encode(['host' => 'localhost', 'name' => 'acc_x', 'user' => 'u', 'pass' => 'SECRET']));
t_eq($r['kind'], 'mysql', 'a MySQL route resolves');
t_ok(strpos($r['label'], 'acc_x') !== false, 'the label names the database');
t_ok(strpos($r['label'], 'SECRET') === false, 'the label never exposes the password');
$rs = p1_resolve_route(json_encode(['sqlite' => '/a/b/tenant-x.sqlite']));
t_eq($rs['kind'], 'sqlite', 'a file route resolves');
t_eq($rs['path'], '/a/b/tenant-x.sqlite', 'the file path is carried so a missing file is never CREATED by connecting');
t_eq(p1_resolve_route('')['kind'], 'none', 'an unwired workspace resolves to none');
t_eq(p1_resolve_route('', ['db' => ['host' => 'h', 'name' => 'n']])['kind'], 'mysql', 'falls back to the routing registry');

// ---- 6. Reading settings from a real database (SELECT only) ---------------
$s = p1_read_settings($db);
t_eq($s['saas_entitled_modules'], 'hr', 'reads the ceiling');
t_eq($s['modules_off'], 'sales', 'reads the tenant choices');
t_eq($s['saas_provisioned'], '1', 'reads the provisioning flag');
t_eq($s['licence_key'], '', 'a missing setting reads as empty, not an error');

// ---- 7. End-to-end row + summary ------------------------------------------
$rowBlank = p1_build_row(['tenant_key' => 'acme', 'company' => 'Acme', 'status' => 'active', 'plan' => 'PRO',
                          'enabled_modules' => '["hr","operations"]'],
                         ['saas_entitled_modules' => '', 'modules_off' => '', 'saas_provisioned' => '1'], 'mysql: acc_acme');
t_ok($rowBlank['ceiling_blank'], 'blank ceiling detected');
t_ok(!empty($rowBlank['would_lose']), 'a blank-ceiling workspace is shown losing modules');
t_eq($rowBlank['risk'], 'RECOVERABLE', 'and is RECOVERABLE because the control DB recorded the purchase');

$rowAmb = p1_build_row(['tenant_key' => 'ghost', 'company' => 'Ghost', 'status' => 'active', 'plan' => '',
                        'enabled_modules' => '[]'],
                       ['saas_entitled_modules' => '', 'modules_off' => '', 'saas_provisioned' => ''], 'mysql: acc_ghost');
t_eq($rowAmb['risk'], 'AMBIGUOUS', 'no ceiling and no control record => AMBIGUOUS');

$sum = p1_summarise([$rowBlank, $rowAmb]);
t_eq($sum['total'], 2, 'summary counts workspaces');
t_eq($sum['AMBIGUOUS'], 1, 'summary counts ambiguous workspaces');
t_eq($sum['blank_ceiling'], 2, 'summary counts blank ceilings');
t_ok($sum['safe_to_flip'] === false, 'the summary refuses to declare the flip safe while any workspace is ambiguous');
t_ok(p1_summarise([$rowBlank])['safe_to_flip'] === true, 'with no ambiguous/error workspaces it reports determinable');

// ---- 8. The rendered report states the important facts --------------------
$txt = p1_render_text(['generated_at' => 'now', 'tenants' => [$rowBlank, $rowAmb], 'summary' => $sum]);
t_ok(strpos($txt, 'no data was written') !== false, 'the report states that nothing was written');
t_ok(strpos($txt, 'DO NOT FLIP') !== false, 'the report warns against flipping while ambiguity remains');
t_ok(strpos($txt, 'WOULD LOSE') !== false, 'the report shows what each workspace would lose');

// ---- 9. Step 2A — storage resolution probe --------------------------------
t_section('Phase 1 Step 2A — storage resolution probe');

$dirs = p1_candidate_dirs('/srv/public_html');
t_eq($dirs['above web root (exaact_data)'], '/srv/exaact_data', 'probes the above-web-root location first');
t_eq($dirs['app folder root (legacy)'], '/srv/public_html', 'probes the legacy app-folder location');

t_eq(p1_route_path(json_encode(['sqlite' => '/x/tenant-a.sqlite']))['kind'], 'sqlite', 'reads a file route');
$mp = p1_route_path(json_encode(['host' => 'h', 'name' => 'db1', 'user' => 'u', 'pass' => 'SECRET']));
t_eq($mp['kind'], 'mysql', 'reads a MySQL route');
t_ok(strpos($mp['label'], 'SECRET') === false, 'the probe never exposes the password');
t_eq(p1_route_path('')['kind'], 'none', 'an empty route reads as none');

// A workspace whose file is really ABOVE the web root while the route points at
// the app folder — the exact case that produced the live ERROR.
$tmp = sys_get_temp_dir() . '/p1probe_' . bin2hex(random_bytes(4));
@mkdir($tmp . '/public_html', 0777, true); @mkdir($tmp . '/exaact_data', 0777, true);
file_put_contents($tmp . '/exaact_data/tenant-moved.sqlite', 'x');
$pr = p1_probe_tenant('moved', json_encode(['sqlite' => $tmp . '/public_html/tenant-moved.sqlite']), null, $tmp . '/public_html');
t_ok($pr['control_exists'] === false, 'the routed path is correctly reported as missing');
t_ok(count($pr['found_elsewhere']) === 1, 'the probe finds the file in another location');
t_eq($pr['found_elsewhere'][0]['where'], 'above web root (exaact_data)', 'and names that location as above the web root');
t_ok($pr['sources_agree'] === null, 'no routing-file entry means "not applicable", not a false conflict');
t_ok(!is_file($tmp . '/public_html/tenant-moved.sqlite'), 'the probe did NOT create the missing file');
@unlink($tmp . '/exaact_data/tenant-moved.sqlite'); @rmdir($tmp . '/exaact_data'); @rmdir($tmp . '/public_html'); @rmdir($tmp);

// A genuine conflict between the two routing sources must be reported.
$conf = p1_probe_tenant('c', json_encode(['sqlite' => '/a/t.sqlite']), ['sqlite' => '/b/t.sqlite'], '/app');
t_ok($conf['sources_agree'] === false, 'a real disagreement between control DB and routing file is flagged');

// ---- 10. Marketplace: purchased vs merely ON by default -------------------
t_section('Marketplace interpretation');
$ctrlNoMk = ['tenant_key' => 't', 'company' => 'T', 'status' => 'active', 'plan' => 'RECRUITMENT',
             'enabled_modules' => '["admin","hr"]'];
$defaultOn = p1_build_row($ctrlNoMk, ['saas_entitled_modules' => 'hr', 'modules_off' => '',
                                      'marketplace_addon' => '', 'connect_enabled' => ''], 'x');
t_ok($defaultOn['marketplace_currently_on'], 'with the setting untouched Marketplace is reachable (cloud default)');
t_ok(!$defaultOn['marketplace_purchased'], 'but it is NOT in the purchased record');
t_ok($defaultOn['marketplace_default_only'], 'and is classified as default-only');
t_ok(!$defaultOn['marketplace_needs_backfill'], 'so it must NOT be backfilled — a default is not a purchase');
t_ok(strpos($defaultOn['marketplace_note'], 'NOT purchased') !== false, 'the note says plainly it was not purchased');

$bought = p1_build_row(['tenant_key' => 't', 'company' => 'T', 'status' => 'active', 'plan' => 'PRO',
                        'enabled_modules' => '["admin","hr","marketplace"]'],
                       ['saas_entitled_modules' => 'hr', 'modules_off' => ''], 'x');
t_ok($bought['marketplace_purchased'] && $bought['marketplace_needs_backfill'], 'a purchased Marketplace IS preserved');

$explicit = p1_build_row($ctrlNoMk, ['saas_entitled_modules' => 'hr', 'modules_off' => '',
                                     'marketplace_addon' => '1'], 'x');
t_ok($explicit['marketplace_needs_backfill'], 'an explicitly switched-on Marketplace is flagged for a commercial decision');

$off = p1_build_row($ctrlNoMk, ['saas_entitled_modules' => 'hr', 'modules_off' => '',
                                'marketplace_addon' => '0'], 'x');
t_ok(!$off['marketplace_currently_on'] && !$off['marketplace_needs_backfill'], 'an explicitly disabled Marketplace stays off');

// ---- 11. Step 2B — identity verification and hypothesis classification ----
t_section('Phase 1 Step 2B — live storage diagnostic');

// The strongest safety guarantee: a READ-ONLY open refuses to create.
$missing = sys_get_temp_dir() . '/p1_absent_' . bin2hex(random_bytes(4)) . '.sqlite';
$threw = false;
try { p1_ro_sqlite($missing); } catch (Throwable $e) { $threw = true; }
t_ok($threw, 'opening a missing database is refused, not created');
t_ok(!file_exists($missing), 'and no file was created by the attempt');

// Build a real workspace database to identify.
$dir = sys_get_temp_dir() . '/p1_2b_' . bin2hex(random_bytes(4));
@mkdir($dir, 0777, true);
$mk = function ($path, $appName, $prov, $ceil, $cands = 2) {
    $d = new PDO('sqlite:' . $path); $d->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach (['settings (skey TEXT PRIMARY KEY, svalue TEXT)', 'users (id INTEGER PRIMARY KEY)',
              'candidates (id INTEGER PRIMARY KEY)', 'requisitions (id INTEGER PRIMARY KEY)'] as $t) $d->exec("CREATE TABLE $t");
    $s = $d->prepare('INSERT INTO settings VALUES (?,?)');
    $s->execute(['app_name', $appName]); $s->execute(['saas_provisioned', $prov]); $s->execute(['saas_entitled_modules', $ceil]);
    for ($i = 1; $i <= $cands; $i++) $d->exec("INSERT INTO candidates (id) VALUES ($i)");
    $d = null;
};
$real = $dir . '/tenant-acme-corp.sqlite';
$mk($real, 'Acme Corp', '1', 'hr', 5);

t_ok(p1_is_sqlite_file($real), 'a real database is recognised by its file header');
file_put_contents($dir . '/tenant-fake.sqlite', 'not a database at all');
t_ok(!p1_is_sqlite_file($dir . '/tenant-fake.sqlite'), 'a file that is not a database is rejected by header check');

$id = p1_identify_db($real, 'acme-corp', 'Acme Corp');
t_eq($id['verdict'], 'MATCHES_EXPECTED_WORKSPACE', 'the database is identified as the expected workspace');
t_eq($id['app_name'], 'Acme Corp', 'the workspace name is read');
t_eq($id['saas_provisioned'], '1', 'the provisioning flag is read');
t_eq($id['saas_entitled_modules'], 'hr', 'the entitlement ceiling is read');
t_eq($id['counts']['candidates'], 5, 'record counts prove real data is present');
t_ok(count($id['exaact_tables']) >= 3, 'it is recognised as an EXAACT workspace');

$idWrong = p1_identify_db($real, 'sachee-hr', 'Sachee HR Recruitment Services');
t_eq($idWrong['verdict'], 'BELONGS_TO_ANOTHER_WORKSPACE', 'a database belonging to someone else is NOT claimed');
t_eq(p1_identify_db($dir . '/tenant-fake.sqlite', 'x', 'X')['verdict'], 'NOT_A_DATABASE', 'a non-database is classified as such');

// Filename variants — the transposition that produced two xyz files.
$vars = p1_key_variants('xyz-recurit');
t_ok(in_array('xyz-recuirt', $vars, true), 'an adjacent-character transposition is treated as a candidate variant');
t_ok(in_array('xyz-recurit', $vars, true), 'the exact key is always a variant of itself');

// Candidate discovery finds the variant and flags it as not the routed name.
$mk($dir . '/tenant-acme-corpo.sqlite', 'Someone Else Ltd', '1', 'operations', 1);
$cands = p1_find_candidates('acme-corp', ['test dir' => $dir]);
t_ok(count($cands) >= 1, 'candidate discovery finds the workspace file');
$exact = array_values(array_filter($cands, fn($c) => $c['exact_name']));
t_ok(count($exact) === 1 && $exact[0]['path'] === $real, 'the exactly-named file is identified as such');

// MySQL storage detection, without exposing the password.
$my = p1_mysql_storage_check(json_encode(['host' => 'h', 'name' => 'acc_db', 'user' => 'u', 'pass' => 'TOPSECRET']), null);
t_ok($my['configured'], 'a MySQL route is detected as configured storage');
t_eq($my['database'], 'acc_db', 'the database name is reported');
t_ok(!in_array('TOPSECRET', $my, true), 'the password is never returned');

t_ok(!p1_mysql_storage_check(json_encode(['sqlite' => '/x/a.sqlite']), null)['configured'],
     'a file-backed workspace is not reported as MySQL storage');

// Hypothesis classification end to end.
$h1 = p1_probe_tenant('nowhere-co', json_encode(['sqlite' => $dir . '/tenant-nowhere-co.sqlite']), null, $dir);
t_ok(strpos($h1['classification'], 'H1') === 0, 'no file anywhere classifies as H1');
t_ok(!file_exists($dir . '/tenant-nowhere-co.sqlite'), 'H1 classification did not create the missing file');

$h2 = p1_probe_tenant('acme-corp', json_encode(['sqlite' => '/nonexistent/tenant-acme-corp.sqlite']), null, $dir, 'Acme Corp');
t_ok(strpos($h2['classification'], 'H2') === 0, 'file present elsewhere while the route is stale classifies as H2');

$h4 = p1_probe_tenant('my-co', json_encode(['host' => 'h', 'name' => 'acc_x', 'user' => 'u', 'pass' => 'p']), null, $dir);
t_ok(strpos($h4['classification'], 'H4') === 0, 'a configured MySQL route classifies as H4');

// Path facts.
$f = p1_path_facts($real);
t_ok($f['exists'] && $f['absolute'] && $f['readable'] && $f['bytes'] > 0 && $f['modified'] !== '',
     'path facts report existence, absoluteness, readability, size and modification time');
t_ok(p1_path_facts('/definitely/not/here.sqlite')['exists'] === false, 'a missing path is reported as absent');

foreach (glob($dir . '/*') ?: [] as $f2) @unlink($f2); @rmdir($dir);
t_ok(true, 'temporary diagnostic fixtures cleaned up');

// ---- 12. Recovery scan — does the data still exist anywhere? --------------
t_section('Recovery scan');

$rd = sys_get_temp_dir() . '/p1rec_' . bin2hex(random_bytes(4));
@mkdir($rd . '/app/tools', 0777, true);
@mkdir($rd . '/exaact_backups/acme', 0777, true);
file_put_contents($rd . '/exaact_backups/acme/20260101_010000_daily.json.gz', gzencode('a'));
file_put_contents($rd . '/exaact_backups/acme/20260914_020000_manual.json.gz', gzencode('bb'));

$bd = p1_backup_dirs($rd . '/app');
t_eq($bd['exaact_backups (above web root)'], $rd . '/exaact_backups', 'backups are looked for above the web root');

$scan = p1_scan_backups($rd . '/exaact_backups');
t_ok($scan['exists'] && count($scan['workspaces']) === 1, 'a workspace backup folder is found');
t_eq($scan['workspaces'][0]['workspace'], 'acme', 'the workspace is named');
t_eq($scan['workspaces'][0]['snapshots'], 2, 'every snapshot is counted');
t_eq($scan['workspaces'][0]['newest'], '20260914_020000_manual.json.gz',
     'the newest snapshot is chosen by timestamped filename, not by file mtime');

t_ok(p1_scan_backups($rd . '/nope')['exists'] === false, 'a missing backup folder is reported, not created');
t_ok(!is_dir($rd . '/nope'), 'and scanning did not create it');

// The sweep finds a live file that has moved above the web root.
@mkdir($rd . '/exaact_data', 0777, true);
$moved = $rd . '/exaact_data/tenant-acme.sqlite';
$d = new PDO('sqlite:' . $moved); $d->exec('CREATE TABLE settings (skey TEXT, svalue TEXT)'); $d = null;
$sweep = p1_sweep_tenant_files($rd . '/app');
$hit = array_values(array_filter($sweep, fn($f) => $f['name'] === 'tenant-acme.sqlite'));
t_ok(count($hit) === 1, 'the sweep finds a workspace file that has moved');
t_ok($hit[0]['is_sqlite'], 'and confirms it is a real database');

// The sweep must ALSO report every folder it looked in, so a "nothing found"
// answer can be audited rather than taken on trust.
$full = p1_sweep($rd . '/app');
t_ok(isset($full['searched']) && count($full['searched']) >= 5, 'the sweep reports every folder it searched');
$missLabel = 'above web root (exaact_data)';
t_ok(array_key_exists($missLabel, $full['searched']), 'the above-web-root location is among them');
t_eq($full['searched'][$missLabel]['matches'], 1, 'and the count of matching files is reported per folder');

// A copy that was RENAMED or given a suffix must still be found, because a
// hand-made safety copy is exactly what an operator makes before deleting.
file_put_contents($rd . '/exaact_data/tenant-acme.sqlite.bak', 'x');
$renamed = p1_sweep_tenant_files($rd . '/app');
t_ok((bool) array_filter($renamed, fn($f) => $f['name'] === 'tenant-acme.sqlite.bak'),
     'a renamed/suffixed copy is found too');
t_ok(!array_filter($renamed, fn($f) => $f['name'] === 'tenant-acme.sqlite.bak' && $f['is_sqlite']),
     'and is correctly reported as NOT a valid database');
@unlink($rd . '/exaact_data/tenant-acme.sqlite.bak');

// A file MOVED one level deeper under the shared account root is found.
@mkdir($rd . '/otherapp/data', 0777, true);
file_put_contents($rd . '/otherapp/data/kv_store.sqlite3', 'not ours');
file_put_contents($rd . '/otherapp/tenant-acme.sqlite', 'moved');
$deep = p1_sweep_tenant_files($rd . '/app');
t_ok((bool) array_filter($deep, fn($f) => $f['name'] === 'tenant-acme.sqlite' && strpos($f['where'], 'otherapp') !== false),
     'a file moved into a neighbouring folder is found');
t_ok(!array_filter($deep, fn($f) => $f['name'] === 'kv_store.sqlite3'),
     "another application's database is never listed");
@unlink($rd . '/otherapp/data/kv_store.sqlite3'); @unlink($rd . '/otherapp/tenant-acme.sqlite');
@rmdir($rd . '/otherapp/data'); @rmdir($rd . '/otherapp');

// THE DEFECT THIS SECTION EXISTS FOR.
// An earlier version answered "recovery is possible" whenever ANY backup folder
// held snapshots. On the live server the only folder was '__control' — the
// routing directory, which contains no workspace records at all — so two
// workspaces with no data and no backup were reported as recoverable. The
// verdict is now computed from each workspace's OWN evidence.
$recCtl = ['app_dir' => $rd . '/app', 'live_files' => [], 'backups' => [
    'exaact_backups (above web root)' => ['path' => '', 'exists' => true, 'readable' => true,
        'workspaces' => [['workspace' => '__control', 'snapshots' => 2, 'total_bytes' => 7220972,
                          'newest' => '20260914_005754_daily.json.gz', 'newest_at' => '2026-09-14 00:57:55']]],
]];
$used = fn($k) => ['tenant' => $k, 'control_provisioned_at' => '2026-09-11T00:00:00+00:00'];
$per = p1_recovery_per_workspace($recCtl, [$used('xyz-recurit'), $used('sachee-hr')]);
t_ok(!$per['xyz-recurit']['recoverable'], 'a __control snapshot does NOT make a workspace recoverable');
t_ok(!$per['sachee-hr']['recoverable'], 'for any workspace');
t_ok(strpos($per['xyz-recurit']['verdict'], 'NOT RECOVERABLE') === 0, 'and the verdict says so plainly');
t_ok(strpos(p1_render_recovery_text($recCtl + ['per_workspace' => $per]),
            'NO WORKSPACE CAN BE RECOVERED FROM THIS SERVER') !== false,
     'the rendered report states it without hedging');
t_ok(strpos(p1_render_recovery_text($recCtl + ['per_workspace' => $per]),
            'the routing directory, NOT any workspace') !== false,
     "and labels the __control folder for what it is");

// A workspace that DOES have its own snapshot is recoverable from backup.
$recOwn = $recCtl;
$recOwn['backups']['exaact_backups (above web root)']['workspaces'][] =
    ['workspace' => 'xyz-recurit', 'snapshots' => 3, 'total_bytes' => 100,
     'newest' => '20260914_010000_daily.json.gz', 'newest_at' => '2026-09-14 01:00:00'];
$per2 = p1_recovery_per_workspace($recOwn, [$used('xyz-recurit'), $used('sachee-hr')]);
t_ok($per2['xyz-recurit']['recoverable'], 'its own snapshot does make it recoverable');
t_eq($per2['xyz-recurit']['snapshots'], 3, 'and the snapshot count is its own, not the total');
t_ok(!$per2['sachee-hr']['recoverable'], 'without affecting the other workspace');
t_ok(strpos(p1_render_recovery_text($recOwn + ['per_workspace' => $per2]), 'PARTIAL') !== false,
     'a mixed outcome is reported as partial, not as success');

// A company created moments ago and never opened has nothing to lose. Calling
// that "NOT RECOVERABLE" is alarming and wrong — it is what the live report said
// about a company created four minutes earlier.
$perNew = p1_recovery_per_workspace($recCtl, [['tenant' => 'acme-fire-safety']]);
t_ok(!$perNew['acme-fire-safety']['recoverable'], 'a never-opened company has no data to recover');
t_ok(empty($perNew['acme-fire-safety']['lost']), 'but it has lost nothing');
t_ok(strpos($perNew['acme-fire-safety']['verdict'], 'NOTHING TO RECOVER') === 0,
     'and the verdict says nothing to recover, not not-recoverable');
t_ok(strpos(p1_render_recovery_text($recCtl + ['per_workspace' => $perNew]),
            'never been opened') !== false,
     'the report does not raise an alarm about a new company');

// A live file beats everything: the data exists, only the routing is stale.
$recLive = $recCtl;
$recLive['live_files'] = [['name' => 'tenant-xyz-recurit.sqlite', 'bytes' => 40960,
                           'modified' => '2026-09-14 00:50:00', 'is_sqlite' => true, 'where' => "sibling 'data' folder"]];
$per3 = p1_recovery_per_workspace($recLive, [$used('xyz-recurit')]);
t_ok($per3['xyz-recurit']['recoverable'] && count($per3['xyz-recurit']['live_files']) === 1,
     'a live file is matched to its workspace by key');

foreach (['/exaact_backups/acme/20260101_010000_daily.json.gz', '/exaact_backups/acme/20260914_020000_manual.json.gz',
          '/exaact_data/tenant-acme.sqlite'] as $f) @unlink($rd . $f);
@rmdir($rd . '/exaact_backups/acme'); @rmdir($rd . '/exaact_backups'); @rmdir($rd . '/exaact_data');
@rmdir($rd . '/app/tools'); @rmdir($rd . '/app'); @rmdir($rd);
t_ok(true, 'recovery fixtures cleaned up');
