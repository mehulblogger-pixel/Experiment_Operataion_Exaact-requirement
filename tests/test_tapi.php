<?php
// ============================================================================
//  TAPI Slice 1 — the analytics foundation.
//  Proves: the KPI master seeds and is configurable; the formula engine composes
//  named metrics safely (no eval surface — arbitrary code is rejected); metrics
//  are scope-gated and period-aware; the zero-vs-no-data distinction holds (a
//  count of 0 is real, an average/ratio with no records is NO DATA); and a KPI
//  evaluates to value + status + lineage.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

if (function_exists('tapi_migrate')) tapi_migrate();

head('1. The safe formula engine — arithmetic, precedence, parens, functions');
ok(tapi_formula_eval('2 + 3 * 4', []) === 14.0, 'operator precedence holds (2+3*4=14)');
ok(tapi_formula_eval('(2 + 3) * 4', []) === 20.0, 'parentheses override precedence');
ok(tapi_formula_eval('10 / 4', []) === 2.5, 'division');
ok(tapi_formula_eval('round(10 / 3, 2)', []) === 3.33, 'round(x,n) works');
ok(tapi_formula_eval('max(3, 7)', []) === 7.0 && tapi_formula_eval('min(3, 7)', []) === 3.0, 'min/max work');
ok(tapi_formula_eval('-5 + 2', []) === -3.0, 'unary minus');

head('2. Null propagation is the no-data rule');
ok(tapi_formula_eval('5 / 0', []) === null, 'divide-by-zero → null (NO DATA), never a bogus number');
ok(tapi_formula_eval('a.missing + 1', []) === null, 'a metric with no value propagates null through arithmetic');
ok(tapi_formula_eval('coalesce(a.missing, 0)', []) === 0.0, 'coalesce supplies an explicit fallback when wanted');
ok(tapi_formula_eval('round(a.missing / b.missing * 100, 1)', []) === null, 'a ratio with a missing base is NO DATA');

head('3. There is NO eval surface — hostile input is refused, not executed');
ok(tapi_formula_tokens('phpinfo()') !== null && tapi_formula_valid('phpinfo()') === false, 'a PHP function name is not a valid formula');
ok(tapi_formula_valid('1; system("ls")') === false, 'a statement separator / shell call is rejected');
ok(tapi_formula_tokens('`rm -rf`') === null, 'backticks tokenize to nothing usable (rejected)');
ok(tapi_formula_tokens('$x + 1') === null, 'a PHP variable is an illegal character');
ok(tapi_formula_valid('jobs.closed + not_a_metric') === false, 'an unknown identifier is rejected');
ok(tapi_formula_valid('round(jobs.closed / jobs.total * 100, 1)') === true, 'a real KPI formula validates');

head('4. Metrics are scope-gated, period-aware, and read real data');
db()->prepare("INSERT INTO offices (name, is_ahmedabad) VALUES ('TAPI Office', 0)")->execute();
$off = (int)db()->lastInsertId();
$mkJob = function($closed, $date, $amount=0) use ($off) {
    db()->prepare("INSERT INTO jobs (job_code, executing_office_id, sbu, scheduled_date, closed_flag, invoice_raised, invoice_amount, mandays, created_at)
        VALUES ('TJ',?,'',?,?,?,?,1,?)")->execute([$off, $date, $closed, $amount>0?1:0, $amount, date('c')]);
    return (int)db()->lastInsertId();
};
db()->exec("DELETE FROM jobs WHERE job_code='TJ'");
// A far-future window no other test touches, so absolute counts are deterministic
// even in the shared full-suite database.
$mkJob(1, '2099-07-05', 1000); $mkJob(1, '2099-07-20', 2000); $mkJob(0, '2099-07-25'); $mkJob(1, '2099-01-10', 500);
$ctxJul = ['from'=>'2099-07-01', 'to'=>'2099-07-31'];
ok(tapi_metric_value('jobs.total', $ctxJul) === 3, 'jobs.total respects the period window (Jul 2099 = 3)');
ok(tapi_metric_value('jobs.closed', $ctxJul) === 2, 'jobs.closed counts only closed jobs in period (2)');
ok(tapi_metric_value('jobs.open', $ctxJul) === 1, 'jobs.open = 1');
ok((float)tapi_metric_value('revenue.invoiced', $ctxJul) === 3000.0, 'revenue.invoiced sums invoiced amounts in period');
ok(tapi_metric_value('jobs.total', $ctxJul) !== tapi_metric_value('jobs.total', ['from'=>'2099-01-01','to'=>'2099-12-31']), 'a wider period window changes the count (period-aware)');
db()->exec("DELETE FROM jobs WHERE job_code='TJ'");

head('5. Zero vs No-data — a real 0 count, but NO DATA for an empty average');
db()->exec("DELETE FROM nonconformities WHERE ref='TAPI/NC'");
ok(tapi_metric_value('ncr.open', ['from'=>'2000-01-01','to'=>'2000-12-31']) === 0, 'no NCRs in a window → a REAL 0 for a count');
ok(tapi_metric_value('ncr.closure_avg_days', ['from'=>'2000-01-01','to'=>'2000-12-31']) === null, 'no closed NCRs → NO DATA (null), not 0 days');
ok(tapi_metric_value('report.tat_avg_days', ['from'=>'2000-01-01','to'=>'2000-12-31']) === null, 'no issued reports → NO DATA, not 0 days');
// prove closure avg computes when data exists
db()->prepare("INSERT INTO nonconformities (ref, status, detected_on, closed_on, created_at) VALUES ('TAPI/NC','CLOSED','2026-06-01','2026-06-11',?)")->execute([date('c')]);
ok((float)tapi_metric_value('ncr.closure_avg_days', ['from'=>'2026-06-01','to'=>'2026-06-30']) === 10.0, 'closure average computes correctly (10 days) when data exists');
db()->exec("DELETE FROM nonconformities WHERE ref='TAPI/NC'");

head('6. KPI master seeds, is configurable, and evaluates with lineage');
ok(count(tapi_kpi_defs()) >= 8, 'the starter KPI set is seeded (' . count(tapi_kpi_defs()) . ')');
ok(tapi_kpi_get('job_closure_rate') !== null, 'a named KPI is present in the master');
$def = tapi_kpi_get('open_ncrs');
$card = tapi_kpi_eval($def, ['from'=>'2000-01-01','to'=>'2000-12-31']);
ok($card['value'] === 0 && $card['no_data'] === false, 'Open NCRs evaluates to a real 0 (not no-data) when the window is genuinely empty of NCRs');
$tat = tapi_kpi_eval(tapi_kpi_get('report_tat'), ['from'=>'2000-01-01','to'=>'2000-12-31']);
ok($tat['no_data'] === true && $tat['status'] === 'NO_DATA', 'Report TAT with no reports reports NO_DATA, not a misleading 0');
ok(!empty($card['lineage']) && $card['lineage'][0]['source'] === 'ncr/nonconformities', 'every KPI carries its data lineage (source module)');

head('7. Status is judged against target + direction');
$hi = tapi_status(95, ['direction'=>'HIGHER','target'=>90,'threshold'=>75], false);
$mid = tapi_status(80, ['direction'=>'HIGHER','target'=>90,'threshold'=>75], false);
$lo = tapi_status(60, ['direction'=>'HIGHER','target'=>90,'threshold'=>75], false);
ok($hi === 'GOOD' && $mid === 'WARN' && $lo === 'BAD', 'higher-is-better grades GOOD/WARN/BAD against target+threshold');
ok(tapi_status(2, ['direction'=>'LOWER','target'=>3,'threshold'=>5], false) === 'GOOD', 'lower-is-better: under target is GOOD');
ok(tapi_status(5, ['direction'=>'INFO','target'=>null], false) === 'INFO', 'an informational KPI is never graded');

head('8. A configurable edit sticks');
tapi_kpi_save(['kpi_key'=>'job_closure_rate', 'name'=>'Job closure rate', 'formula'=>'round(jobs.closed / jobs.total * 100, 1)',
    'unit'=>'%', 'direction'=>'HIGHER', 'target'=>95, 'threshold'=>80, 'category'=>'OPERATIONS']);
ok((float)tapi_kpi_get('job_closure_rate')['target'] === 95.0, 'editing a KPI target persists (configurable master)');
db()->exec("DELETE FROM offices WHERE id=$off");

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== TAPI: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
