<?php
// Module 44 — Evidence. The report now "knows about" its own evidence: an advisory Evidence &
// on-site row on the issue-readiness preview (photos, site check-in, chain intact), and an on-site
// line on the public /verify. Plus the FIRST tamper coverage of the evidence hash chain.
t_section('Module 44 — evidence readiness + chain tamper detection');

$idems = file_get_contents(__DIR__ . '/../lib/idems.php');
$trust = file_get_contents(__DIR__ . '/../lib/trust.php');
$vview = file_get_contents(__DIR__ . '/../views/ops/verify.php');

t_ok(function_exists('idems_evidence_readiness'), 'idems_evidence_readiness() exists');
t_ok(function_exists('chain_verify'), 'chain_verify() exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('idems_migrate')) idems_migrate();
    if (function_exists('trust_migrate')) trust_migrate();
    $pdo = db();

    // A job with an arrival + departure check-in, and a report on it with a photo.
    $pdo->prepare("INSERT INTO jobs (job_code) VALUES ('J-EV')")->execute();
    $jid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO site_visits (job_id, kind, at, created_at) VALUES (?, 'ENTRY', '2026-07-01T09:00:00+00:00', ?)")->execute([$jid, date('c')]);
    $pdo->prepare("INSERT INTO site_visits (job_id, kind, at, created_at) VALUES (?, 'EXIT',  '2026-07-01T15:00:00+00:00', ?)")->execute([$jid, date('c')]);
    $pdo->prepare("INSERT INTO report_docs (irn, status, finalized, job_id, created_at) VALUES ('EV-1','ISSUED',1,?, ?)")->execute([$jid, date('c')]);
    $did = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_files (report_doc_id, field_key, kind, file_name, sha1, geo_source, created_at) VALUES (?, '_supporting','photo','p.jpg','abc123','EXIF', ?)")->execute([$did, date('c')]);
    $fid = (int)$pdo->lastInsertId();

    $ev = idems_evidence_readiness(['id' => $did, 'job_id' => $jid]);
    t_eq($ev['photos'], 1, 'the evidence readiness counts the photo');
    t_eq($ev['onsite'], 1, 'it counts the on-site (EXIF) photo');
    t_ok($ev['has_entry'] && $ev['has_exit'], 'it sees the arrival + departure check-in');

    // A report with NO photos and no check-in → the readiness row warns (never blocks).
    $pdo->prepare("INSERT INTO report_docs (irn, status, finalized, created_at) VALUES ('EV-2','APPROVED',0, ?)")->execute([date('c')]);
    $bare = ops_one("SELECT * FROM report_docs WHERE irn='EV-2'");
    $rd = idems_issue_readiness($bare);
    $evItem = null; foreach ($rd['items'] as $i) if ($i['label'] === 'Evidence & on-site') $evItem = $i;
    t_ok($evItem !== null, 'the issue-readiness preview has an Evidence & on-site row');
    t_eq($evItem['state'], 'warn', 'a report with no photos / no check-in warns');
    t_ok($evItem['state'] !== 'block', 'evidence is advisory — it never blocks issue');

    // ---- the FIRST evidence-chain tamper test ----
    if (function_exists('chain_append')) {
        chain_append($fid);
        $ok = chain_verify($did);
        t_ok(!empty($ok['ok']) && (int)$ok['entries'] >= 1, 'a freshly sealed evidence chain verifies');
        // Tamper: the bytes change under an intact chain entry (sha1 no longer matches).
        $pdo->prepare("UPDATE report_files SET sha1='TAMPERED' WHERE id=?")->execute([$fid]);
        $bad = chain_verify($did);
        t_ok(empty($bad['ok']) && count($bad['problems']) >= 1, 'altering the evidence bytes is detected (CONTENT)');
        t_ok($bad['problems'][0]['kind'] === 'CONTENT', 'the failure is classified as a content change');
    }
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
t_ok(strpos($idems, "'Evidence & on-site'") !== false, 'the readiness row is wired into idems_issue_readiness');
t_ok(strpos($trust, "'checkin' => \$checkin") !== false, 'the verify lookup now carries the on-site check-in');
t_ok(strpos($vview, 'On-site check-in') !== false, 'the public verify page shows the on-site check-in line');
t_ok(strpos($idems, 'does not block issue') !== false, 'the evidence row is explicitly advisory');
