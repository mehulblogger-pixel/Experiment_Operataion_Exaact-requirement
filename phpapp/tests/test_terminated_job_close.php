<?php
// Owner, 8 Nov 2026: "Yes" — a TERMINATED job may be closed without the Final
// Inspection Report and Release Note. And after any early end, visit days past
// the end date (never worked) no longer hold the job open.
t_section('A terminated job closes without its final documents');
setting_set('require_final_docs_on_close', '1');
//  TPIA Phase 1 / D7 (owner, 25 Nov 2026): the two documents are required only
//  when ticked in the job's "Types of report required" — so the job here ticks both.
$base = ['id' => 987654321, 'reporting_frequency' => 'DAILY', 'deliverables' => 'IR,FINAL_REP,RN', 'call_id' => 0];
//  Owner, 28 Nov 2026: a Release Note is owed only when the material was released — so the
//  ordinary job here carries a released report (and no Final Inspection Report / Release Note yet).
db()->prepare("DELETE FROM report_docs WHERE job_id=?")->execute([987654321]);
db()->prepare("INSERT INTO report_docs (job_id, type_code, status, result, release_status, deleted, data, created_at) VALUES (?,?,?,?,?,0,'[]',?)")
    ->execute([987654321, 'IR', 'ISSUED', 'ACCEPTED', 'RELEASED', 'x']);
t_eq(count(job_final_docs_missing($base)), 2, 'an ordinary job that ticked both still needs a Final Inspection Report and a Release Note');
t_eq(job_final_docs_missing($base + ['billing_stop_kind' => 'TERMINATED']), [], 'a terminated job does not');
t_eq(count(job_final_docs_missing($base + ['billing_stop_kind' => 'COMPLETED_EARLY'])), 2, 'a job completed early still does');
t_eq(count(job_final_docs_missing($base + ['billing_stop_kind' => 'DEMOBILIZED'])), 2, 'and so does a demobilised one');

db()->prepare("DELETE FROM report_docs WHERE job_id=?")->execute([987654321]);
t_section('Visit days after an early end do not hold the job open');
if (function_exists('job_visits_migrate')) job_visits_migrate();
$jid = 987654321;
db()->prepare("DELETE FROM job_visits WHERE job_id=?")->execute([$jid]);
$days = [];
for ($i = 1, $t = strtotime('2031-03-03'); count($days) < 4; $i++, $t += 86400)
    if (!function_exists('is_working_day') || is_working_day(date('Y-m-d', $t), null)) $days[] = date('Y-m-d', $t);
foreach ($days as $d) db()->prepare("INSERT INTO job_visits (job_id, visit_date, status) VALUES (?,?,'PLANNED')")->execute([$jid, $d]);
t_eq(count(job_visits_open_days(['id' => $jid])), 4, 'without an early end, every open day counts');
$open = job_visits_open_days(['id' => $jid, 'billing_stop_on' => $days[1]]);
t_eq($open, [$days[0], $days[1]], 'after an early end, only the days up to the end date count');
db()->prepare("DELETE FROM job_visits WHERE job_id=?")->execute([$jid]);

$src = (string) file_get_contents(__DIR__ . '/../lib/ops.php');
$a = strpos($src, 'UPDATE jobs SET closed_flag=1, closed_at=?');
$b = strpos($src, "'CLOSED_TERMINATED'");
t_ok($a !== false && $b !== false && $b > $a, 'closing a terminated job is recorded, once the job is really closed');
