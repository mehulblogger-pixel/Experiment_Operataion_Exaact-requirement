<?php
// ============================================================================
//  TAPI Slice 5 — targets, the management scorecard, and threshold alerts.
//  Proves: per-scope/period target overrides resolve most-specific-first;
//  target-vs-actual computes variance + achievement; the scorecard is fully
//  transparent (weight · score · contribution) and never fakes a total from
//  missing data; alerts fire on a real breach (and never on no-data); and the
//  fairness / small-sample / causality guardrails exist.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }
if (function_exists('tapi_migrate')) tapi_migrate();
if (function_exists('tapi_score_migrate')) tapi_score_migrate();

head('1. Targets resolve most-specific-first, over the KPI default');
db()->exec("DELETE FROM kpi_targets WHERE kpi_key='job_closure_rate'");
$def = tapi_kpi_get('job_closure_rate');   // default target 90 (or 95 if a prior test edited)
[$t0] = tapi_target_effective($def, ['office'=>7,'sbu'=>'MECH']);
ok($t0 !== null, 'the KPI-definition target applies when there is no override');
tapi_target_set(['kpi_key'=>'job_closure_rate', 'target'=>80]);                          // all-scope override
tapi_target_set(['kpi_key'=>'job_closure_rate', 'office_id'=>7, 'target'=>85]);          // office-specific
[$tAll] = tapi_target_effective($def, ['office'=>3]);
[$tOff] = tapi_target_effective($def, ['office'=>7]);
ok((float)$tAll === 80.0, 'a general override beats the KPI default (80)');
ok((float)$tOff === 85.0, 'an office-specific override beats the general one (85)');
db()->exec("DELETE FROM kpi_targets WHERE kpi_key='job_closure_rate'");

head('2. Target vs actual computes variance + achievement');
db()->exec("DELETE FROM jobs WHERE job_code='TS'");
foreach ([1,1,1,1,0] as $c) db()->prepare("INSERT INTO jobs (job_code,executing_office_id,sbu,scheduled_date,closed_flag,created_at) VALUES ('TS',NULL,'','2099-03-10',?,?)")->execute([$c, date('c')]);
tapi_target_set(['kpi_key'=>'job_closure_rate', 'target'=>90]);
$tva = tapi_target_vs_actual(tapi_kpi_get('job_closure_rate'), ['from'=>'2099-03-01','to'=>'2099-03-31']);
ok(abs((float)$tva['actual'] - 80.0) < 0.1, 'actual closure rate = 4/5 = 80%');
ok((float)$tva['target'] === 90.0 && abs((float)$tva['variance'] + 10) < 0.1, 'variance vs target = −10');
ok(abs((float)$tva['achievement_pct'] - 88.9) < 0.2, 'achievement = 80/90 = 88.9%');
ok($tva['status'] === 'BAD' || $tva['status'] === 'WARN', 'status is graded against the (overridden) target');
db()->exec("DELETE FROM kpi_targets WHERE kpi_key='job_closure_rate'");
db()->exec("DELETE FROM jobs WHERE job_code='TS'");

head('3. The scorecard is transparent — weight, score, contribution, no faked total');
$cards = tapi_scorecards();
ok(!empty($cards), 'a default management scorecard is seeded');
$ev = tapi_scorecard_eval((int)$cards[0]['id'], ['from'=>'2099-02-01','to'=>'2099-02-28']);   // an empty window
ok(is_array($ev['rows']) && count($ev['rows']) >= 4, 'the scorecard lists its category items');
foreach ($ev['rows'] as $r) ok(array_key_exists('weight',$r) && array_key_exists('score',$r) && array_key_exists('contribution',$r), 'each row exposes weight + score + contribution') && $r=null;
ok($ev['total'] === null || is_numeric($ev['total']), 'the total is either a number or NULL (not a fabricated score)');
ok($ev['total'] === null && $ev['excluded'] >= 1, 'with an empty window every item is no-data → excluded → total is NULL, not 0');

head('4. Alerts fire on a real breach and never on no-data');
ok(tapi_alert_test('LT', 70, 80) === true, 'LT fires when value is below threshold');
ok(tapi_alert_test('LT', 90, 80) === false, 'LT does not fire when value is above threshold');
ok(tapi_alert_test('GT', 12, 5) === true && tapi_alert_test('GTE', 5, 5) === true, 'GT / GTE work');
ok(tapi_alert_test('LT', null, 80) === false, 'a no-data value NEVER fires an alert');
db()->exec("DELETE FROM kpi_alerts WHERE name='TA rule'");
$aid = tapi_alert_save(['name'=>'TA rule','kpi_key'=>'open_ncrs','op'=>'GTE','threshold'=>1,'severity'=>'HIGH','recipients'=>'','enabled'=>1]);
db()->exec("DELETE FROM nonconformities WHERE ref='TA/NC'");
db()->prepare("INSERT INTO nonconformities (ref,status,detected_on,created_at) VALUES ('TA/NC','OPEN',?,?)")->execute([date('Y-m-d'), date('c')]);
$firing = tapi_alerts_eval(tapi_ctx_from_filters(tapi_filters(['period'=>'YEAR'])));
$hit = false; foreach ($firing as $f) if ((int)$f['alert']['id'] === $aid) $hit = true;
ok($hit, 'an open-NCR alert fires when there is ≥1 open NCR');
db()->exec("DELETE FROM nonconformities WHERE ref='TA/NC'");
db()->exec("DELETE FROM kpi_alerts WHERE name='TA rule'");

head('5. Guardrails exist (small sample, causality, fairness)');
ok(tapi_small_sample(3, 5) === true && tapi_small_sample(9, 5) === false, 'small-sample suppression triggers below the minimum');
ok(strpos(tapi_causality_caveat(), 'association') !== false, 'the causality caveat states association, not cause');
ok(strpos(tapi_fairness_note('vendor'), 'single finding') !== false, 'the vendor fairness note warns against a one-finding score');

head('6. The cron job + routes are wired');
ok(strpos(file_get_contents(__DIR__ . '/../cron.php'), 'tapi_alerts_run') !== false, 'the nightly cron fires alerts');
ok(strpos(file_get_contents(__DIR__ . '/../lib/tapi_dash.php'), "analytics-scorecard") !== false, 'the scorecard route is wired');
ok(strpos(file_get_contents(__DIR__ . '/../lib/tapi_dash.php'), "analytics-alerts") !== false, 'the alerts route is wired');

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== TAPI SCORE: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
