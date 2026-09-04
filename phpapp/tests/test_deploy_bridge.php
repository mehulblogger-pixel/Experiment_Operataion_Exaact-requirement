<?php
// Marketplace → operations bridge (Stage 5 / §20). An awarded marketplace
// requirement becomes an operational deployment (a job tagged with its source
// requirement) — no duplicate creation. connect_deploy_row_for_requirement() is
// the link the client portal surfaces.
t_section('marketplace → operations deployment link');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    if (function_exists('connect_deploy_migrate')) connect_deploy_migrate();

    // a requirement with no deployment yet → no link
    $rid = 55501;
    t_ok(connect_deploy_row_for_requirement($rid) === null, 'a requirement with no deployment has no operational job yet');

    // the operations team deploys it → one job tagged with the source requirement
    db()->prepare("INSERT INTO jobs (job_code,job_type,dep_status,dep_site,source_module,source_requirement_id,sbu,created_at) VALUES ('DEP-BRIDGE-1','DEPUTATION','MOB_PENDING','Jamnagar','connect',?, 'IND', ?)")->execute([$rid, date('c')]);
    $row = connect_deploy_row_for_requirement($rid);
    t_ok($row !== null, 'once deployed, the requirement links to an operational job');
    t_eq($row['job_code'], 'DEP-BRIDGE-1', 'the linked job is the deployment created from the award');
    t_eq((int)$row['source_requirement_id'], $rid, 'the job carries its source requirement (no re-keying)');

    // a different requirement is not linked to this job
    t_ok(connect_deploy_row_for_requirement(99999) === null, 'the link is scoped to the requirement it came from');

    // the newest deployment wins if re-deployed (no stale link)
    db()->prepare("INSERT INTO jobs (job_code,job_type,dep_status,source_module,source_requirement_id,sbu,created_at) VALUES ('DEP-BRIDGE-2','DEPUTATION','ACTIVE','connect',?, 'IND', ?)")->execute([$rid, date('c')]);
    t_eq(connect_deploy_row_for_requirement($rid)['job_code'], 'DEP-BRIDGE-2', 'the most recent deployment is the current link');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
