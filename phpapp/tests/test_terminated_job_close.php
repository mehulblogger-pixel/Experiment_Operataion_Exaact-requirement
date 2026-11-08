<?php
// Owner, 8 Nov 2026: "Yes" — a TERMINATED job may be closed without the Final
// Inspection Report and Release Note. And after any early end, visit days past
// the end date (never worked) no longer hold the job open.
t_section('A terminated job closes without its final documents');
setting_set('require_final_docs_on_close', '1');
$base = ['id' => 987654321, 'reporting_frequency' => 'DAILY'];
t_eq(count(job_final_docs_missing($base)), 2, 'an ordinary job still needs a Final Inspection Report and a Release Note');
t_eq(job_final_docs_missing($base + ['billing_stop_kind' => 'TERMINATED']), [], 'a terminated job does not');
t_eq(count(job_final_docs_missing($base + ['billing_stop_kind' => 'COMPLETED_EARLY'])), 2, 'a job completed early still does');
t_eq(count(job_final_docs_missing($base + ['billing_stop_kind' => 'DEMOBILIZED'])), 2, 'and so does a demobilised one');

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
