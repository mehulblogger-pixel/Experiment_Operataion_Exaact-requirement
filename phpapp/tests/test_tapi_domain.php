<?php
// ============================================================================
//  TAPI Slice 3 — domain roll-ups (the bare-gap aggregations).
//  Proves: the new portfolio metrics compute over the SAME source tables the
//  domains own (report pipeline, release, SLA-over-tosrm_sla_eval, CAPA
//  effectiveness, vendor re-assessment, portal adoption); the breakdown
//  functions return chart-ready [label=>value] (delay categories, root causes,
//  service mix, vendor bands, …); and the derived KPIs honour zero-vs-no-data.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }
if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }
if (function_exists('tapi_migrate')) tapi_migrate();

$P = ['from'=>'2099-05-01', 'to'=>'2099-05-31'];   // a private window for determinism

head('1. Report pipeline + revision (bare gap: only inline register buckets)');
db()->exec("DELETE FROM report_docs WHERE irn LIKE 'TD/%'");
$mkRep = function($status, $rev, $rel='') {
    db()->prepare("INSERT INTO report_docs (irn,office_id,type_code,status,rev,release_status,finalized,deleted,issue_date,created_at,updated_at)
        VALUES (?,NULL,'IR',?,?,?,?,0,'2099-05-10',?,?)")->execute(['TD/'.uniqid(), $status, $rev, $rel, $status==='ISSUED'?1:0, date('c'), date('c')]);
};
$mkRep('DRAFT',0); $mkRep('UNDER_REVIEW',0); $mkRep('REJECTED',1); $mkRep('ISSUED',0,'RELEASED'); $mkRep('ISSUED',2,'RELEASED_COND');
ok(tapi_metric_value('reports.draft', $P) === 1, 'reports.draft = 1');
ok(tapi_metric_value('reports.under_review', $P) === 1, 'reports.under_review = 1');
ok(tapi_metric_value('reports.rejected', $P) === 1, 'reports.rejected (returned) = 1');
ok(tapi_metric_value('reports.revised', $P) === 2, 'reports.revised counts rev>0 (2)');
$pipe = tapi_bd_report_pipeline($P);
ok(array_sum($pipe) === 5 && ($pipe['DRAFT'] ?? 0) === 1, 'the pipeline breakdown buckets by status');

head('2. Release portfolio (bare gap: release_status never GROUP BY-aggregated)');
ok(tapi_metric_value('release.released', $P) === 1 && tapi_metric_value('release.conditional', $P) === 1, 'release.released + conditional counted');
$rel = tapi_bd_release_status($P);
ok(($rel['RELEASED'] ?? 0) === 1 && ($rel['RELEASED_COND'] ?? 0) === 1, 'the release breakdown groups by release_status');
$mix = tapi_bd_service_mix($P);
ok(($mix['IR'] ?? 0) === 5, 'the service-mix breakdown groups by report type');
db()->exec("DELETE FROM report_docs WHERE irn LIKE 'TD/%'");

head('3. Delay categories (bare gap: job_delays.category never aggregated)');
db()->exec("DELETE FROM job_delays WHERE impact='TDLY'");
foreach (['VENDOR','VENDOR','CLIENT','DOCUMENTATION'] as $cat)
    db()->prepare("INSERT INTO job_delays (job_id,call_id,scheduled_date,delay_days,category,impact,created_at) VALUES (0,0,'2099-05-05',3,?, 'TDLY', ?)")->execute([$cat, date('c')]);
$dc = tapi_bd_delay_categories($P);
ok(($dc['VENDOR'] ?? 0) === 2 && ($dc['CLIENT'] ?? 0) === 1 && ($dc['DOCUMENTATION'] ?? 0) === 1, 'delays group by category (VENDOR=2, CLIENT=1, DOCUMENTATION=1)');
db()->exec("DELETE FROM job_delays WHERE impact='TDLY'");

head('4. SLA portfolio reuses the per-call engine tosrm_sla_eval');
ok(function_exists('tapi_sla_tally'), 'the SLA tally exists');
$t = tapi_sla_tally($P);
ok(isset($t['within']) && isset($t['breached']) && isset($t['evaluable']), 'it returns within / breached / evaluable');
$bd = tapi_bd_sla_status($P);
ok(isset($bd['Within']) && isset($bd['Breached']) && isset($bd['At risk']), 'the SLA breakdown is chart-ready');
// derived KPI is no-data when there are no evaluable calls in the window
$sla = tapi_kpi_eval(tapi_kpi_get('sla_compliance'), $P);
ok($sla['no_data'] === true, 'SLA compliance is NO_DATA when no calls have an SLA in the window (not a fake 100%)');

head('5. CAPA effectiveness (bare gap: counts existed, not a rate)');
db()->exec("DELETE FROM capa WHERE ref LIKE 'TCAP/%'");
$mkCapa = function($eff) { db()->prepare("INSERT INTO capa (ref,office_id,rc_method,eff_result,raised_on,created_at) VALUES (?,NULL,'5WHY',?,'2099-05-08',?)")->execute(['TCAP/'.uniqid(), $eff, date('c')]); };
$mkCapa('EFFECTIVE'); $mkCapa('EFFECTIVE'); $mkCapa('NOT_EFFECTIVE'); $mkCapa('');   // last is unrated
ok(tapi_metric_value('capa.rated', $P) === 3, 'capa.rated counts only rated CAPAs (3)');
ok(tapi_metric_value('capa.effective', $P) === 2, 'capa.effective = 2');
$ce = tapi_kpi_eval(tapi_kpi_get('capa_effectiveness'), $P);
ok(abs((float)$ce['value'] - 66.7) < 0.1, 'CAPA effectiveness rate = 2/3 = 66.7%');
$rc = tapi_bd_rootcause($P);
ok(($rc['5WHY'] ?? 0) === 4, 'the root-cause breakdown groups by rc_method');
db()->exec("DELETE FROM capa WHERE ref LIKE 'TCAP/%'");

head('6. Vendor re-assessment + score bands');
db()->exec("DELETE FROM vendor_profiles WHERE partner_id IN (990001,990002,990003)");
db()->prepare("INSERT INTO vendor_profiles (partner_id, last_score, reassess_on) VALUES (990001, 88, ?)")->execute([date('Y-m-d', strtotime('-2 days'))]);
db()->prepare("INSERT INTO vendor_profiles (partner_id, last_score, reassess_on) VALUES (990002, 55, ?)")->execute([date('Y-m-d', strtotime('+30 days'))]);
db()->prepare("INSERT INTO vendor_profiles (partner_id, last_score, reassess_on) VALUES (990003, 92, '')")->execute();
ok(tapi_metric_value('vendor.reassess_due', []) >= 1, 'vendor.reassess_due counts overdue re-assessments');
$vb = tapi_bd_vendor_scores([]);
ok(($vb['75–90'] ?? 0) >= 1 && ($vb['90–100'] ?? 0) >= 1 && ($vb['40–60'] ?? 0) >= 1, 'vendor scores fall into the right bands');
db()->exec("DELETE FROM vendor_profiles WHERE partner_id IN (990001,990002,990003)");

head('7. Portal adoption (fully bare: audit tables never aggregated)');
db()->exec("DELETE FROM portal_audit WHERE detail='TPA'");
foreach (['LOGIN','REPORT_OPENED','DASHBOARD'] as $a)
    db()->prepare("INSERT INTO portal_audit (client_user_id,partner_id,action,detail,at) VALUES (1,1,?, 'TPA', ?)")->execute([$a, '2099-05-09T10:00:00']);
ok(tapi_metric_value('portal.events', $P) === 3, 'portal.events aggregates portal_audit in the window (3)');
$pa = tapi_bd_portal_activity($P);
ok(array_sum($pa) === 3 && isset($pa['LOGIN']), 'the portal-activity breakdown groups by action');
db()->exec("DELETE FROM portal_audit WHERE detail='TPA'");

head('8. The domain KPIs are seeded into the master');
foreach (['sla_compliance','report_revision_rate','release_rate','capa_effectiveness','vendor_reassess_due','portal_active_users'] as $k)
    ok(tapi_kpi_get($k) !== null, "KPI '$k' is in the master");

if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== TAPI DOMAIN: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
