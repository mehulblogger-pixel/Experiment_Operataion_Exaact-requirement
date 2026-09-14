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
t_eq($mk(['error' => 'refused'])['risk'], 'ERROR', 'unreadable workspace => ERROR');
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
