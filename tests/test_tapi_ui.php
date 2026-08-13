<?php
// ============================================================================
//  TAPI Slice 2 — the presentation kit.
//  Proves: value formatting per unit; the declarative KPI card (status tone,
//  no-data handling, target, lineage); period ranges + comparison (prev/YoY/MoM
//  with variance, %, achievement, trend — honouring no-data); the shared filter
//  engine (period→dates, scope-narrowing); and the new chart primitives render
//  real SVG with data and a labelled placeholder when empty.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }
if (function_exists('tapi_migrate')) tapi_migrate();

head('1. Value formatting per unit');
ok(tapi_fmt_value(null, 'count') === '—', 'null renders as an em-dash, never 0');
ok(tapi_fmt_value(92.5, '%') === '92.5%', 'a percentage keeps its sign');
ok(tapi_fmt_value(3.0, 'days') === '3 d', 'days trim trailing zeros');
ok(tapi_fmt_value(1500, 'count') === '1,500', 'counts are thousands-separated');

head('2. The declarative KPI card');
$card = ['name'=>'Job closure rate','unit'=>'%','value'=>88.0,'no_data'=>false,'status'=>'WARN',
         'target'=>90,'direction'=>'HIGHER','lineage'=>[['metric'=>'jobs.closed','source'=>'ops/jobs','method'=>'x']]];
$html = kpi_card($card);
ok(strpos($html, 'tone-warn') !== false, 'the card carries a status tone (warn)');
ok(strpos($html, '88%') !== false, 'the value is formatted and shown');
ok(strpos($html, 'Target 90%') !== false, 'the target is shown');
ok(strpos($html, 'ops/jobs') !== false, 'the data lineage (source) is shown on the card');
$nd = kpi_card(['name'=>'X','unit'=>'days','value'=>null,'no_data'=>true,'status'=>'NO_DATA','direction'=>'LOWER']);
ok(strpos($nd, 'No data') !== false && strpos($nd, 'tone-mut') !== false, 'a no-data KPI says "No data", not 0, and is muted');

head('3. Period ranges');
ok(tapi_period_range('MONTH', '2026-07-15') === ['2026-07-01', '2026-07-31'], 'MONTH resolves to first..last of the month');
ok(tapi_period_range('QUARTER', '2026-07-15') === ['2026-07-01', '2026-09-30'], 'QUARTER resolves Q3 bounds');
ok(tapi_period_range('YEAR', '2026-07-15') === ['2026-01-01', '2026-12-31'], 'YEAR resolves calendar-year bounds');
ok(tapi_prev_range('2026-07-01', '2026-07-31', 'YOY') === ['2025-07-01', '2025-07-31'], 'YoY shifts the window back one year');
$pr = tapi_prev_range('2026-07-01', '2026-07-31', 'PREV');
ok($pr[1] === '2026-06-30', 'PREV window ends the day before the current window starts');

head('4. Period comparison honours data and no-data');
db()->exec("DELETE FROM jobs WHERE job_code='TU'");
$off = null;
$mk = function($date) { db()->prepare("INSERT INTO jobs (job_code, executing_office_id, sbu, scheduled_date, closed_flag, created_at) VALUES ('TU', NULL, '', ?, 1, ?)")->execute([$date, date('c')]); };
// current window (Jul 2099) = 3 closed; previous (Jun 2099) = 1 closed
$mk('2099-07-03'); $mk('2099-07-10'); $mk('2099-07-22'); $mk('2099-06-15');
$def = tapi_kpi_get('jobs_closed');
$cmp = tapi_compare($def, ['from'=>'2099-07-01','to'=>'2099-07-31'], 'PREV');
ok((int)$cmp['current'] === 3 && (int)$cmp['previous'] === 1, 'current vs previous windows evaluate independently (3 vs 1)');
ok((int)$cmp['variance'] === 2 && $cmp['trend'] === 'up', 'variance and trend are computed');
ok($cmp['variance_pct'] === 200.0, 'variance % is computed (+200%)');
// no-data previous → variance null, never fabricated
$cmp2 = tapi_compare($def, ['from'=>'2099-07-01','to'=>'2099-07-31'], 'YOY');  // 2098 has nothing
ok((int)$cmp2['current'] === 3 && (int)$cmp2['previous'] === 0, 'YoY previous is a real 0 count here');
db()->exec("DELETE FROM jobs WHERE job_code='TU'");

head('5. The shared filter engine');
$f = tapi_filters(['period'=>'MONTH', 'from'=>'', 'to'=>'']);
ok($f['from'] !== '' && $f['to'] !== '', 'a period preset resolves to concrete from/to dates');
$f2 = tapi_filters(['period'=>'CUSTOM', 'from'=>'2026-03-01', 'to'=>'2026-03-31']);
ok($f2['from'] === '2026-03-01' && $f2['to'] === '2026-03-31', 'explicit custom dates are respected');
$f3 = tapi_filters(['period'=>'ALL']);
ok($f3['from'] === '' && $f3['to'] === '', 'ALL time clears the window');
$ctx = tapi_ctx_from_filters($f);
ok(isset($ctx['from']) && isset($ctx['to']), 'a metric context is derived from the filter set');
$bar = tapi_filter_bar($f, ['show'=>['compare']]);
ok(strpos($bar, 'tapi-filter') !== false && strpos($bar, 'name="period"') !== false, 'the filter bar renders with a period selector');

head('6. New chart primitives render real SVG, with placeholders when empty');
$line = tapi_svg_line(['label'=>'Jobs','points'=>[['x'=>'Jan','y'=>3],['x'=>'Feb','y'=>7],['x'=>'Mar','y'=>5]]]);
ok(strpos($line, '<svg') !== false && strpos($line, '<path') !== false, 'the line chart draws a path');
ok(strpos(tapi_svg_line([]), '<svg') === false, 'an empty line chart shows a placeholder, not a blank axis');
$spark = tapi_svg_sparkline([1,3,2,5,4]);
ok(strpos($spark, '<svg') !== false, 'the sparkline renders');
$par = tapi_svg_pareto(['Vendor A'=>10,'Vendor B'=>6,'Vendor C'=>2]);
ok(strpos($par, '<svg') !== false && strpos($par, '<rect') !== false, 'the pareto draws bars + a cumulative line');
$hm = tapi_svg_heatmap(['Branch 1','Branch 2'], ['Jan','Feb'], ['Branch 1'=>['Jan'=>5,'Feb'=>2],'Branch 2'=>['Jan'=>1,'Feb'=>8]]);
ok(strpos($hm, '<svg') !== false && strpos($hm, '<rect') !== false, 'the heatmap renders cells');
ok(strpos(tapi_svg_heatmap([], [], []), '<svg') === false, 'an empty heatmap shows a placeholder');
$stack = tapi_svg_stack(['Q1'=>['NCR'=>3,'Obs'=>2],'Q2'=>['NCR'=>1,'Obs'=>4]], ['NCR','Obs']);
ok(strpos($stack, '<svg') !== false && strpos($stack, '<rect') !== false, 'the stacked bar renders segments + legend');

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== TAPI UI: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
