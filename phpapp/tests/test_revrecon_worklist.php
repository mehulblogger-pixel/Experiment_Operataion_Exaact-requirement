<?php
// Revamp §29 — recognised-revenue reconciliation worklist (read-only). Surfaces
// jobs whose legacy invoice snapshot (jobs.invoice_amount) matches neither the net
// nor gross books-ledger total, so finance can drive it to green before any reader
// is switched (§28). Changes no figure.
t_section('revenue reconciliation worklist (Revamp §29)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('RR Co',1,'ACTIVE')")->execute();
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (client_id, call_code, created_at) VALUES (?,?,?)")->execute([$cid, 'C-RR', date('c')]);
    $callId = (int)db()->lastInsertId();

    $mkJob = function ($code, $legacy) use ($callId) {
        db()->prepare("INSERT INTO jobs (call_id, job_code, closed_flag, invoice_raised, invoice_amount, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$callId, $code, 1, ($legacy > 0 ? 1 : 0), $legacy, date('c')]);
        return (int)db()->lastInsertId();
    };
    $mkInvLine = function ($jobId, $net, $gross) use ($cid) {
        db()->prepare("INSERT INTO invoices (invoice_no, partner_id, status, total, created_at) VALUES (?,?, 'ISSUED', ?, ?)")
            ->execute(['INV-RR-' . $jobId, $cid, $gross, date('c')]);
        $inv = (int)db()->lastInsertId();
        db()->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount, line_total) VALUES (?,?,?,?)")->execute([$inv, $jobId, $net, $gross]);
    };

    // A: legacy 1000 matches the ledger net 1000 → reconciles.
    $ok = $mkJob('J-RR-OK', 1000); $mkInvLine($ok, 1000, 1180);
    // B: legacy 5000 but ledger says 2000/2360 → diverges (matches neither).
    $bad = $mkJob('J-RR-BAD', 5000); $mkInvLine($bad, 2000, 2360);
    // C: legacy 800, no ledger invoice at all → legacy-only divergence.
    $legOnly = $mkJob('J-RR-LEGONLY', 800);

    $sj_ok  = revrecon_job($ok);
    t_ok($sj_ok['diverges'] === false, 'a job whose legacy figure matches the ledger net reconciles');
    $sj_bad = revrecon_job($bad);
    t_ok($sj_bad['diverges'] === true, 'a job matching neither net nor gross diverges');
    $sj_leg = revrecon_job($legOnly);
    t_ok($sj_leg['diverges'] === true && $sj_leg['legacy_only'] === true, 'a legacy figure with no ledger invoice diverges (legacy-only)');

    $sum = revrecon_summary();
    t_ok($sum['candidates'] >= 3, 'the summary considers all candidate jobs');
    t_ok($sum['reconciled'] >= 1, 'the reconciled count includes the matching job');
    t_ok($sum['diverging'] >= 2, 'the diverging count includes both mismatches');
    t_ok($sum['green'] === false, 'green is false while divergences exist');

    $list = revrecon_list(200);
    $codes = array_column($list, 'job_code');
    t_ok(in_array('J-RR-BAD', $codes, true) && in_array('J-RR-LEGONLY', $codes, true), 'the worklist lists the diverging jobs');
    t_ok(!in_array('J-RR-OK', $codes, true), 'a reconciled job is not on the worklist');
    $badRow = null; foreach ($list as $r) if ($r['job_code'] === 'J-RR-BAD') $badRow = $r;
    t_ok($badRow && $badRow['client_name'] === 'RR Co' && $badRow['reason'] !== '', 'worklist rows carry client + a plain-language reason');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
