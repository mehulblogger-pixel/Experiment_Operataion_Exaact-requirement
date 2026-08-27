<?php
// Revamp — the first-class Engagement entity (additive groundwork). One
// engagement per contract_number; a nullable engagement_id is stamped onto
// calls/jobs/quotations/invoices. The contract_number string is never dropped;
// reads are dual (string still links everything, id is a stable handle).
t_section('engagement entity — additive, dual-read (Revamp)');

engagement_migrate();

// The additive columns exist on all four spine tables.
foreach (['calls', 'jobs', 'quotations', 'invoices'] as $t) {
    $ok = true;
    try { ops_all("SELECT engagement_id FROM $t LIMIT 1"); } catch (Throwable $e) { $ok = false; }
    t_ok($ok, "engagement_id column exists on $t");
}

t_eq(engagement_ensure(''), 0, 'a blank contract number creates no engagement');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Eng Co',1,'ACTIVE')")->execute();
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title) VALUES (?,?,?)")->execute([$cid, 'ENG-1', 'Framework 2026']);
    // Spine records carrying the contract_number string, none stamped yet.
    db()->prepare("INSERT INTO calls (client_id, contract_number, call_code, created_at) VALUES (?,?,?,?)")->execute([$cid, 'ENG-1', 'C-E1', date('c')]);
    $callId = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (call_id, contract_number, job_code, created_at) VALUES (?,?,?,?)")->execute([$callId, 'ENG-1', 'J-E1', date('c')]);
    db()->prepare("INSERT INTO invoices (invoice_no, partner_id, contract_number, status, total, created_at) VALUES ('INV-E1',?,?, 'ISSUED', 1000, ?)")->execute([$cid, 'ENG-1', date('c')]);

    // ensure is idempotent.
    $e1 = engagement_ensure('ENG-1', $cid, 'Framework 2026');
    t_ok($e1 > 0, 'an engagement is created for the contract number');
    t_eq(engagement_ensure('ENG-1'), $e1, 'ensure is idempotent — same id on a second call');
    t_eq(engagement_id_for('ENG-1'), $e1, 'the id resolves from the contract number');

    // Backfill stamps engagement_id onto the spine records.
    $r = engagement_backfill();
    t_ok(is_array($r) && $r['stamped'] >= 3, 'backfill stamps the calls/jobs/invoices carrying this contract');
    t_eq((int)ops_val("SELECT engagement_id FROM calls WHERE id=?", [$callId]), $e1, 'the call now carries the engagement_id');
    t_eq((int)ops_val("SELECT engagement_id FROM jobs WHERE contract_number='ENG-1'"), $e1, 'the job now carries the engagement_id');

    // Idempotent: a second backfill stamps nothing new.
    $r2 = engagement_backfill();
    t_eq($r2['stamped'], 0, 're-running backfill stamps nothing new (records already linked)');

    // Dual-read: the id resolves back to the same spine the string groups.
    $byId = engagement_by_id($e1);
    $byStr = engagement('ENG-1', $cid);
    t_ok($byId !== null && $byId['contract_number'] === 'ENG-1', 'engagement_by_id resolves to the contract engagement');
    t_eq(count($byId['members']), count($byStr['members']), 'id-read and string-read return the same spine members');

    // The string is intact (never dropped).
    t_eq((string)ops_val("SELECT contract_number FROM calls WHERE id=?", [$callId]), 'ENG-1', 'the contract_number string is preserved alongside the id');

    t_ok(engagement_by_id(99999) === null, 'an unknown engagement id returns null');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
