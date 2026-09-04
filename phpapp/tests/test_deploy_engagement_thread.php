<?php
// Gap-1 (CONNECT) — a marketplace deployment must thread into the engagement/finance spine.
// Before this, connect_deploy_from_engagement created the operations job with no contract_number
// and no engagement_id, so a marketplace deployment never reached the contract_number-keyed
// readers, the first-class engagement entity, or reconciliation. This proves the deploy now
// stamps both and ensures the engagement row — additively, nothing else changed.
t_section('marketplace deployment threads into the engagement spine (Gap 1)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    foreach (['connect_market_migrate', 'connect_deploy_migrate', 'engagement_migrate', 'connect_pro_migrate', 'connect_engage_migrate'] as $mg)
        if (function_exists($mg)) { try { $mg(); } catch (Throwable $e) {} }

    // A client + an awarded requirement with one professional applicant (mirrors DEMO-S04's award).
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Deploy Thread Co',1,'ACTIVE')")->execute();
    $client = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_professionals (email, name, mobile, is_active, created_at) VALUES ('deploy.pro@ex.com','Deploy Pro','9800011222',1,?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();

    $rid = (int)cx_requirement_create(['title' => 'Piping Inspector (deploy thread)', 'poster_party_id' => $client, 'poster_name' => 'DeployThread',
        'discipline_code' => 'MECH', 'location' => 'Hazira', 'work_type' => 'FREELANCE', 'positions' => 1,
        'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+20 days')), 'rate_min' => 9000, 'rate_max' => 13000, 'rate_unit' => 'day'], true);
    t_ok($rid > 0, 'the requirement is created');
    $aid = (int)cx_application_add($rid, ['applicant_professional_id' => $pro, 'applicant_name' => 'Deploy Pro', 'proposed_rate' => 10000]);
    db()->prepare("UPDATE cx_applications SET status='ACCEPTED' WHERE id=?")->execute([$aid]);
    db()->prepare("UPDATE cx_requirements SET status='AWARDED', awarded_application_id=?, updated_at=? WHERE id=?")->execute([$aid, date('c'), $rid]);

    // Deploy it.
    [$ok, $msg, $jobId] = connect_deploy_from_engagement($rid);
    t_ok($ok === true && $jobId > 0, 'the deployment job is created: ' . $msg);

    $job = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
    $req = cx_requirement_get($rid);
    $expectKey = trim((string)($req['ref_code'] ?? '')) !== '' ? (string)$req['ref_code'] : ('CXR-' . $rid);

    // The threading: both keys are stamped on the operations job.
    t_eq((string)$job['contract_number'], $expectKey, 'the deployment job carries the engagement key as its contract_number');
    t_ok((int)$job['engagement_id'] > 0, 'the deployment job carries an engagement_id');

    // The first-class engagement spine row exists and resolves to the same id.
    t_eq((int)engagement_id_for($expectKey), (int)$job['engagement_id'], 'engagement_id_for(key) resolves to the job\'s engagement_id');
    $eng = ops_one("SELECT * FROM engagements WHERE engagement_key=?", [$expectKey]);
    t_ok($eng !== null && (int)$eng['partner_id'] === $client, 'the engagement row exists and carries the client as partner');

    // The read-side engagement grouping now finds the deployment job under the contract_number.
    if (function_exists('engagement')) {
        $grp = engagement($expectKey);
        t_ok((bool)array_filter($grp['members'] ?? [], fn($m) => ($m['kind'] ?? '') === 'JOB' && (int)($m['id'] ?? 0) === $jobId),
            'the engagement grouping surfaces the deployment job as a member');
    }

    // Idempotent + additive: re-deploying updates the same job and keeps the thread (no duplicate job, no duplicate engagement).
    [$ok2, , $jobId2] = connect_deploy_from_engagement($rid);
    t_ok($ok2 === true && (int)$jobId2 === (int)$jobId, 're-deploying updates the same job, not a duplicate');
    t_eq((int)ops_val("SELECT COUNT(*) FROM engagements WHERE engagement_key=?", [$expectKey]), 1, 'no duplicate engagement row is created');
    t_eq((int)ops_val("SELECT COUNT(*) FROM jobs WHERE source_requirement_id=? AND source_module='connect'", [$rid]), 1, 'exactly one deployment job exists for the requirement');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
