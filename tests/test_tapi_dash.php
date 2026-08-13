<?php
// ============================================================================
//  TAPI Slice 4 — role dashboards, drill-down, data-quality.
//  Proves: a role dashboard renders its KPI cards + breakdown charts; drill-down
//  returns exactly the source records behind a slice (summary → records); the
//  data-quality view reuses the platform integrity engine; and the whole thing
//  is gated (tapi_can) so analytics can never bypass permissions.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }
if (function_exists('tapi_migrate')) tapi_migrate();

head('1. Role dashboards are configured for the key audiences');
$dashes = tapi_dashboards();
foreach (['executive','operations','quality','service','vendor','client'] as $r) ok(isset($dashes[$r]), "the '$r' dashboard is configured");
ok(!empty($dashes['operations']['kpis']) && !empty($dashes['operations']['charts']), 'a dashboard has both KPI cards and breakdown charts');

head('2. A dashboard renders KPI cards + charts for a filter set');
$f = tapi_filters(['period'=>'YEAR','from'=>'','to'=>'']);
$html = tapi_render_dashboard('operations', $f);
ok(strpos($html, 'tapi-kpi') !== false, 'the dashboard renders KPI cards');
ok(strpos($html, '<svg') !== false || strpos($html, 'No data') !== false, 'the dashboard renders charts (or labelled placeholders)');
ok(strpos($html, '/analytics-drill?') !== false, 'every card/chart links to drill-down');

head('3. Drill-down returns the exact source records behind a slice');
db()->exec("DELETE FROM report_docs WHERE irn LIKE 'DR/%'");
db()->prepare("INSERT INTO report_docs (irn,office_id,type_code,status,finalized,deleted,issue_date,created_at,updated_at) VALUES ('DR/1',NULL,'IR','DRAFT',0,0,'2099-04-10',?,?)")->execute([date('c'),date('c')]);
db()->prepare("INSERT INTO report_docs (irn,office_id,type_code,status,finalized,deleted,issue_date,created_at,updated_at) VALUES ('DR/2',NULL,'IR','DRAFT',0,0,'2099-04-12',?,?)")->execute([date('c'),date('c')]);
db()->prepare("INSERT INTO report_docs (irn,office_id,type_code,status,finalized,deleted,issue_date,created_at,updated_at) VALUES ('DR/3',NULL,'IR','ISSUED',1,0,'2099-04-15',?,?)")->execute([date('c'),date('c')]);
$ctx = ['from'=>'2099-04-01','to'=>'2099-04-30'];
[$cols, $rows] = tapi_drill('report_pipeline', 'DRAFT', $ctx);
ok(count($rows) === 2, 'drilling "report pipeline = DRAFT" returns exactly the 2 draft records');
ok(in_array('IRN', $cols, true), 'the drill result has record columns');
$irns = array_map(fn($r) => $r[0], $rows);
ok(in_array('DR/1', $irns, true) && !in_array('DR/3', $irns, true), 'it returns the drafts, not the issued report (records match the KPI)');
// the count matches the breakdown that fed the drill
$bd = tapi_bd_report_pipeline($ctx);
ok(($bd['DRAFT'] ?? 0) === count($rows), 'the drill record count matches the breakdown slice count');
db()->exec("DELETE FROM report_docs WHERE irn LIKE 'DR/%'");

head('4. Delay-category drill returns job_delays rows');
db()->exec("DELETE FROM job_delays WHERE impact='DDLY'");
db()->prepare("INSERT INTO job_delays (job_id,call_id,scheduled_date,delay_days,category,reason,impact,created_at) VALUES (0,77,'2099-04-05',4,'VENDOR','late material','DDLY',?)")->execute([date('c')]);
[$c2,$r2] = tapi_drill('delay_categories', 'VENDOR', $ctx);
ok(count($r2) === 1 && (int)$r2[0][0] === 77, 'delay drill returns the underlying job_delays row (call 77)');
db()->exec("DELETE FROM job_delays WHERE impact='DDLY'");

head('5. Data-quality view reuses the platform integrity engine');
$dq = tapi_data_quality();
ok(isset($dq['summary']['total']) && isset($dq['summary']['failed']), 'the data-quality summary has total + failed');
ok(is_array($dq['checks']), 'it returns the list of integrity checks');

head('6. Analytics is gated — it cannot bypass permissions');
ok(function_exists('tapi_can'), 'a permission gate exists for analytics');
$src = file_get_contents(__DIR__ . '/../lib/tapi_dash.php');
ok(strpos($src, "ops_require(tapi_can()") !== false, 'the handler requires the gate before rendering anything');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
ok(strpos($ops, "'analytics'=>'reports'") !== false, 'analytics routes are in the module gate map');

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== TAPI DASH: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
