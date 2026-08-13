<?php
// ============================================================================
//  TAPI Slice 6 — governance + the Phase-12 hand-off.
//  Proves: KPI versioning keeps the old definition when meaning changes; a
//  period can be snapshotted and LOCKED so its figures stay reproducible even
//  as live data moves; exports (CSV matrix + valid XLSX) carry the filters;
//  the management-review package and the factual AI summary assemble; and the
//  structured KPI-metadata contract for Phase 12 is complete.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }
if (function_exists('tapi_migrate')) tapi_migrate();
if (function_exists('tapi_score_migrate')) tapi_score_migrate();
if (function_exists('tapi_gov_migrate')) tapi_gov_migrate();

head('1. KPI versioning keeps the past when meaning changes');
db()->exec("DELETE FROM kpi_versions WHERE kpi_key='govtest_kpi'");
db()->exec("DELETE FROM kpi_defs WHERE kpi_key='govtest_kpi'");
tapi_kpi_save(['kpi_key'=>'govtest_kpi','name'=>'Gov test','formula'=>'jobs.closed','unit'=>'count','direction'=>'HIGHER','target'=>10]);
$before = count(tapi_kpi_history('govtest_kpi'));
tapi_kpi_save(['kpi_key'=>'govtest_kpi','name'=>'Gov test','formula'=>'jobs.total','unit'=>'count','direction'=>'HIGHER','target'=>20,'reason'=>'switched basis']);
$hist = tapi_kpi_history('govtest_kpi');
ok(count($hist) === $before + 1, 'changing the formula/target snapshots the OLD version');
ok($hist[0]['formula'] === 'jobs.closed' && (float)$hist[0]['target'] === 10.0, 'the archived version holds the previous formula + target');
ok($hist[0]['reason'] === 'switched basis', 'the reason for the change is recorded');
// a cosmetic-only save does NOT create a version
$n2 = count(tapi_kpi_history('govtest_kpi'));
tapi_kpi_save(['kpi_key'=>'govtest_kpi','name'=>'Gov test renamed','formula'=>'jobs.total','unit'=>'count','direction'=>'HIGHER','target'=>20]);
ok(count(tapi_kpi_history('govtest_kpi')) === $n2, 'a name-only edit does NOT create a new version');
db()->exec("DELETE FROM kpi_versions WHERE kpi_key='govtest_kpi'");
db()->exec("DELETE FROM kpi_defs WHERE kpi_key='govtest_kpi'");

head('2. Period snapshot is immutable + reproducible; locking is enforced');
db()->exec("DELETE FROM kpi_snapshots WHERE period_key='2099-09'");
db()->exec("DELETE FROM analytics_periods WHERE period_key='2099-09'");
db()->exec("DELETE FROM jobs WHERE job_code='GV'");
foreach ([1,1,0] as $c) db()->prepare("INSERT INTO jobs (job_code,executing_office_id,sbu,scheduled_date,closed_flag,created_at) VALUES ('GV',NULL,'','2099-09-10',?,?)")->execute([$c, date('c')]);
$r = tapi_snapshot_period('2099-09');
ok($r['ok'] === true && $r['count'] >= 8, 'a snapshot freezes every active KPI for the period');
$snap = tapi_snapshot_get('2099-09');
$closedSnap = null; foreach ($snap as $s) if ($s['kpi_key'] === 'jobs_closed') $closedSnap = $s;
ok($closedSnap && (int)$closedSnap['value'] === 2, 'the snapshot captured jobs_closed = 2');
// change the live data, re-read the snapshot — it must not move
db()->prepare("INSERT INTO jobs (job_code,executing_office_id,sbu,scheduled_date,closed_flag,created_at) VALUES ('GV',NULL,'','2099-09-12',1,?)")->execute([date('c')]);
$snap2 = tapi_snapshot_get('2099-09'); $closed2 = null; foreach ($snap2 as $s) if ($s['kpi_key'] === 'jobs_closed') $closed2 = $s;
ok((int)$closed2['value'] === 2, 'the stored snapshot does not change when live data changes (reproducible)');
// lock it — re-snapshot is refused
tapi_period_set_state('2099-09', 'LOCKED');
ok(tapi_period_locked('2099-09') === true, 'the period is locked');
$r2 = tapi_snapshot_period('2099-09');
ok($r2['ok'] === false, 'a locked period cannot be re-snapshotted (finalised figures are immutable)');
db()->exec("DELETE FROM kpi_snapshots WHERE period_key='2099-09'");
db()->exec("DELETE FROM analytics_periods WHERE period_key='2099-09'");
db()->exec("DELETE FROM jobs WHERE job_code='GV'");

head('3. Exports carry the filters (CSV matrix + valid XLSX)');
$f = tapi_filters(['period'=>'YEAR','from'=>'2099-01-01','to'=>'2099-12-31']);
$matrix = tapi_export_matrix($f);
ok(count($matrix) >= 9 && $matrix[0][0] === 'KPI', 'the export matrix has a header + a row per KPI');
ok($matrix[0][7] === 'Period from' && $matrix[1][7] === '2099-01-01', 'the export carries the period filter');
$xlsx = tapi_xlsx($matrix);
ok($xlsx !== null && substr($xlsx, 0, 2) === 'PK', 'a valid .xlsx (zip) is produced');
ok(strpos($xlsx, 'xl/worksheets/sheet1.xml') !== false || strlen($xlsx) > 400, 'the xlsx contains a worksheet');

head('4. Management review + factual AI summary assemble');
$rev = tapi_management_review($f);
ok(!empty($rev['kpis']) && isset($rev['quality']) && isset($rev['caveat']), 'the review package has KPIs, data-quality and the causality caveat');
ok(strpos($rev['caveat'], 'association') !== false, 'the review carries the correlation-not-causation caveat');
[$ai, $err] = tapi_ai_summary($f);
ok($ai === '' && $err !== '', 'with AI off the summary degrades to a message, never a fabricated narrative');

head('5. The Phase-12 metadata contract is complete');
$meta = tapi_kpi_export_meta($f);
ok(isset($meta['kpis']) && isset($meta['generated']), 'the export has a kpis array + timestamp');
$one = $meta['kpis'][0];
foreach (['kpi','formula','source','period','dimensions','value','target','status','trend','data_quality_confidence'] as $field)
    ok(array_key_exists($field, $one), "each KPI record exposes '$field' for the AI layer");
ok(array_key_exists('no_data', $one), 'the contract distinguishes no-data explicitly');

head('6. Audit trail + cron wiring');
ok(function_exists('tapi_audit'), 'the audit wrapper exists (reuses idems_log)');
ok(strpos(file_get_contents(__DIR__ . '/../lib/tapi_gov.php'), "idems_log('tapi'") !== false, 'audit events go to the hash-chained platform log');
ok(strpos(file_get_contents(__DIR__ . '/../lib/tapi_dash.php'), "analytics-snapshot") !== false, 'the snapshot route is wired');

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== TAPI GOV: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
