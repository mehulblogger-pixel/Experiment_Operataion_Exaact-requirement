<?php
// Owner, 28 Nov 2026 ("Yes go ahead with both as recommended"):
//   1. A Release Note is required at job close ONLY when the material was actually released
//      (the latest report says Accepted / Released). After an abortive visit, a hold point not
//      cleared, a rejection / not released, or balance work, the job closes without one and the
//      close form records why (automatic when the record shows it; else picked + a note).
//   2. The Final Inspection Report stays required, EXCEPT after an abortive visit (the last
//      visit day closed as "No inspection").
t_section('Owner 28 Nov 2026 — Release Note only when released; FIR except after an abortive visit');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$made = ['j' => [], 'r' => [], 'v' => []];
putenv('BBV_RAW=1');
$html = fn($r) => (string) ($r['html'] ?? $r['out'] ?? '');
$keep = setting_get('require_final_docs_on_close', '1');
try {
    bbv_as($f['master']);
    if (function_exists('idems_migrate')) idems_migrate();
    if (function_exists('sched_migrate')) sched_migrate();
    job_noreport_migrate();
    setting_set('require_final_docs_on_close', '1');

    $mkJob = function ($code, $del) use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, reporting_frequency, deliverables, inspection_end_date, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$code, $f['mum'], 'DAILY', $del, date('Y-m-d'), date('c')]);
        $id = (int) $pdo->lastInsertId(); $made['j'][] = $id;
        return ops_one("SELECT * FROM jobs WHERE id=?", [$id]);
    };
    $rep = function ($jobId, $type, $result = '', $release = '') use ($pdo, &$made) {
        $pdo->prepare("INSERT INTO report_docs (job_id, type_code, irn, status, result, release_status, deleted, data, created_at) VALUES (?,?,?,?,?,?,0,'[]',?)")
            ->execute([$jobId, $type, 'IRN/O28/' . mt_rand(1000, 99999), 'ISSUED', $result, $release, date('c')]);
        $made['r'][] = (int) $pdo->lastInsertId();
    };
    $visit = function ($jobId, $date, $status, $code = '', $note = '') use ($pdo, &$made) {
        $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, status, reason_code, reason_note) VALUES (?,?,?,?,?)")->execute([$jobId, $date, $status, $code, $note]);
        $made['v'][] = (int) $pdo->lastInsertId();
    };
    $reasons = job_norn_reasons();
    t_ok(isset($reasons['ABORTIVE'], $reasons['HOLD'], $reasons['REJECTED'], $reasons['BALANCE'], $reasons['OTHER']), 'the reasons list has the five keys (labels are a Masters list)');

    // ---- released → RN still required (as before)
    $jr = $mkJob($f['tag'] . '-RN1', 'IR,RN');
    $rep($jr['id'], 'IR', 'ACCEPTED', 'RELEASED');
    t_eq(job_final_docs_missing($jr), ['a Release Note'], 'material released, no Release Note yet: still required (the existing message)');
    t_eq(job_final_docs_exceptions($jr)['rn'], null, '…and no "why no Release Note" box is offered');

    // ---- hold → not required, reason automatic
    $jh = $mkJob($f['tag'] . '-RN2', 'IR,RN');
    $rep($jh['id'], 'IR', 'HOLD');
    t_eq(job_final_docs_missing($jh), [], 'report says Hold: the Release Note is not required');
    t_eq((string) job_final_docs_exceptions($jh)['rn']['auto'], 'HOLD', '…the reason is chosen by itself: Hold point not cleared');

    // ---- rejected / not released / pending → not required
    $jx = $mkJob($f['tag'] . '-RN3', 'IR,RN');
    $rep($jx['id'], 'IR', 'REJECTED');
    t_eq((string) job_final_docs_exceptions($jx)['rn']['auto'], 'REJECTED', 'report says Rejected: Rejected — not released');
    $jn = $mkJob($f['tag'] . '-RN4', 'IR,RN');
    $rep($jn['id'], 'IR', '', 'NOT_RELEASED');
    t_eq(job_final_docs_missing($jn), [], 'release status Not released: no Release Note required');
    t_eq((string) job_final_docs_exceptions($jn)['rn']['auto'], 'REJECTED', '…reason Rejected — not released');
    $jp = $mkJob($f['tag'] . '-RN5', 'IR,RN');
    $rep($jp['id'], 'IR', '', 'PENDING');
    t_eq((string) job_final_docs_exceptions($jp)['rn']['auto'], 'REJECTED', 'release status Pending: same');

    // ---- abortive → RN and FIR not required
    $ja = $mkJob($f['tag'] . '-RN6', 'IR,FINAL_REP,RN');
    $visit($ja['id'], date('Y-m-d', strtotime('-2 day')), 'DONE');
    $rep($ja['id'], 'IR', 'ACCEPTED');
    $visit($ja['id'], date('Y-m-d', strtotime('-1 day')), 'NO_INSPECTION', 'MATERIAL_NOT_READY', 'Castings still at the foundry');
    t_eq(job_final_docs_missing($ja), [], 'last visit day closed as No inspection: neither the Release Note nor the Final Inspection Report is required');
    $fx = job_final_docs_exceptions($ja);
    t_eq((string) $fx['rn']['auto'], 'ABORTIVE', '…the reason is Abortive visit');
    t_ok(strpos((string) $fx['rn']['detail'], 'Material not ready') !== false, '…and it shows the No-inspection reason (' . $fx['rn']['detail'] . ')');
    t_ok($fx['fir'] !== null, '…the Final Inspection Report is recorded as not needed (abortive)');

    // ---- FIR still required after a hold
    $jf = $mkJob($f['tag'] . '-RN7', 'IR,FINAL_REP,RN');
    $rep($jf['id'], 'IR', 'HOLD');
    t_eq(job_final_docs_missing($jf), ['a Final Inspection Report'], 'after a hold the Final Inspection Report is still required (it records the result) — the Release Note is not');

    // ---- no result stated → person must pick (balance work)
    $jb = $mkJob($f['tag'] . '-RN8', 'IR,RN');
    $rep($jb['id'], 'IR', '', '');
    t_eq((string) job_final_docs_exceptions($jb)['rn']['auto'], '', 'no result on the report: the person picks the reason');

    // ---- the existing exits are unchanged
    t_eq(job_final_docs_exceptions(array_merge($jh, ['billing_stop_kind' => 'TERMINATED']))['rn'], null, 'a terminated job is not asked');
    setting_set('require_final_docs_on_close', '0');
    t_eq(job_final_docs_exceptions($jh)['rn'], null, 'the company switch still turns the whole check off');
    setting_set('require_final_docs_on_close', '1');

    // ---- the close form and the close itself
    $r = bbv_route('job-close', 'GET', $f['neha'], (int) $jh['id']);
    $h = $html($r);
    t_ok(strpos($h, 'Why no Release Note?') !== false && preg_match('~<option value="HOLD"\s+selected~', $h) === 1,
         'the close form asks "Why no Release Note?" with Hold point not cleared already chosen');
    $r = bbv_route('job-close', 'POST', $f['neha'], (int) $jh['id'], ['report_upload_date' => date('Y-m-d')]);
    $jh2 = ops_one("SELECT * FROM jobs WHERE id=?", [$jh['id']]);
    t_eq((int) $jh2['closed_flag'], 1, 'a Hold job closes without a Release Note (reason automatic)');
    t_eq((string) $jh2['closed_no_rn_reason'], 'HOLD', '…the reason is stored on the job');
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='JOB' AND entity_id=? AND subject LIKE ?", [$jh['id'], 'Closed without a Release Note%']) === 1,
         '…and History reads "Closed without a Release Note — …"');
    $r = bbv_route('job', 'GET', $f['neha'], (int) $jh['id']);
    t_ok(strpos($html($r), 'Closed without Release Note') !== false, 'the closed job page shows it');

    // balance: picked + note required
    $r = bbv_route('job-close', 'POST', $f['neha'], (int) $jb['id'], ['report_upload_date' => date('Y-m-d')]);
    t_eq((int) ops_val("SELECT closed_flag FROM jobs WHERE id=?", [$jb['id']]), 0, 'no reason picked when none is automatic: refused');
    $r = bbv_route('job-close', 'POST', $f['neha'], (int) $jb['id'], ['report_upload_date' => date('Y-m-d'), 'no_rn_reason' => 'BALANCE', 'no_rn_note' => 'short']);
    t_eq((int) ops_val("SELECT closed_flag FROM jobs WHERE id=?", [$jb['id']]), 0, '"Balance activity pending" with a note under ' . JOB_NORN_NOTE_MIN . ' characters: refused');
    $r = bbv_route('job-close', 'POST', $f['neha'], (int) $jb['id'], ['report_upload_date' => date('Y-m-d'), 'no_rn_reason' => 'BALANCE', 'no_rn_note' => 'Hydro test of 3 valves pending — next visit']);
    $jb2 = ops_one("SELECT * FROM jobs WHERE id=?", [$jb['id']]);
    t_eq((int) $jb2['closed_flag'], 1, 'with the reason and what is pending, it closes');
    t_eq([(string) $jb2['closed_no_rn_reason'], (string) $jb2['closed_no_rn_note']], ['BALANCE', 'Hydro test of 3 valves pending — next visit'], '…both are stored');

    // released without RN: close still refused
    $r = bbv_route('job-close', 'POST', $f['neha'], (int) $jr['id'], ['report_upload_date' => date('Y-m-d')]);
    t_eq((int) ops_val("SELECT closed_flag FROM jobs WHERE id=?", [$jr['id']]), 0, 'released material with no Release Note: the close is still refused');

    // abortive: FIR not needed, recorded
    $r = bbv_route('job-close', 'POST', $f['neha'], (int) $ja['id'], ['report_upload_date' => date('Y-m-d')]);
    $ja2 = ops_one("SELECT * FROM jobs WHERE id=?", [$ja['id']]);
    t_eq([(int) $ja2['closed_flag'], (string) $ja2['closed_no_rn_reason'], (int) $ja2['closed_no_fir']], [1, 'ABORTIVE', 1], 'abortive visit: closes; reason Abortive visit; no Final Inspection Report recorded');

    // phone: the select fits (no fixed width wider than the screen)
    $src = (string) file_get_contents(dirname(__DIR__) . '/views/ops/job_close.php');
    t_ok(strpos($src, 'id="no_rn_reason" name="no_rn_reason" style="max-width:100%"') !== false, 'the reason box is full width at most — fits a 390px phone');
} finally {
    putenv('BBV_RAW');
    setting_set('require_final_docs_on_close', $keep);
    $in = fn($a) => implode(',', array_map('intval', $a));
    if ($made['v']) $pdo->exec("DELETE FROM job_visits WHERE id IN (" . $in($made['v']) . ")");
    if ($made['r']) $pdo->exec("DELETE FROM report_docs WHERE id IN (" . $in($made['r']) . ")");
    if ($made['j']) { try { $pdo->exec("DELETE FROM activities WHERE entity_kind='JOB' AND entity_id IN (" . $in($made['j']) . ")"); } catch (Throwable $e) {} $pdo->exec("DELETE FROM jobs WHERE id IN (" . $in($made['j']) . ")"); }
    bbv_cleanup($f);
}
