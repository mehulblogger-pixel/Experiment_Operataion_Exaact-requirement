<?php
// Phase 2 §11 — the issued-report content seal was fail-OPEN: if idems_seal_content() errored, the
// report was left with an empty seal and idems_content_check() reported it "unsealed / ok", making a
// seal FAILURE indistinguishable from a legitimately-old (pre-feature) report. Now a failed seal is
// marked with a distinct sentinel, verification flags it (without falsely claiming tampering), a
// compliance row surfaces it, and cron re-seals it. Non-destructive: issuance is never blocked.
t_section('Phase 2 §11 — content seal is fail-closed / self-healing');

t_ok(function_exists('idems_seal_content') && function_exists('idems_content_check'), 'seal + check functions exist');
t_ok(function_exists('idems_reseal_failed') && function_exists('idems_seal_failed_count'), 'reseal + count helpers exist');
t_ok(defined('IDEMS_SEAL_FAILED'), 'the SEAL_FAILED sentinel is defined');

// A pre-feature report (empty seal) is unsealed but NOT a failure — unchanged behaviour.
$pre = idems_content_check(['content_seal' => '']);
t_ok($pre['sealed'] === false && $pre['ok'] === true, 'an empty seal reads unsealed/ok (pre-feature, not flagged)');

// A SEAL_FAILED sentinel reads NOT-ok with a seal problem — but sealed=false, so the public /verify
// page (which shows the integrity row only when sealed=true) never claims the content was altered.
$fail = idems_content_check(['content_seal' => IDEMS_SEAL_FAILED]);
t_ok($fail['sealed'] === false, 'a failed seal is not marked "sealed" (public verify stays silent, no false tamper claim)');
t_ok($fail['ok'] === false && ($fail['problem'] ?? '') === 'seal_failed', 'a failed seal reads NOT-ok with problem=seal_failed (internal flag)');

// A real seal still verifies content, and detects tampering.
$doc = ['irn'=>'IRN-1','rev'=>0,'report_type_id'=>1,'type_code'=>'IC','title'=>'T','client_id'=>1,'vendor_id'=>0,
        'call_id'=>0,'job_id'=>0,'office_id'=>1,'sbu'=>'','inspector_id'=>1,'approver_user_id'=>2,
        'inspection_date'=>'2026-08-01','issue_date'=>'2026-08-02','result'=>'PASS','release_status'=>'','remarks'=>'','data'=>'{}'];
$doc['content_seal'] = idems_content_seal_compute($doc);
t_ok(idems_content_check($doc)['ok'] === true, 'a valid seal verifies as intact');
$tampered = $doc; $tampered['result'] = 'FAIL';
t_ok(idems_content_check($tampered)['ok'] === false, 'altering sealed content is detected (ok=false)');

// End-to-end: a real report gets a real seal; a SEAL_FAILED report is counted and re-sealed by the healer.
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    $mk = function ($seal) use ($pdo) {
        $pdo->prepare("INSERT INTO report_docs (irn, rev, type_code, title, status, finalized, content_seal, deleted, data)
                       VALUES (?,0,'IC','T','ISSUED',1,?,0,'{}')")->execute(['IRN-SEAL-'.substr(md5($seal.mt_rand()),0,6), $seal]);
        return (int)$pdo->lastInsertId();
    };
    $base = idems_seal_failed_count();
    $failedId = $mk(IDEMS_SEAL_FAILED);           // simulate a seal that failed at issue
    $mk('');                                       // a pre-feature report — must NOT be touched
    t_eq(idems_seal_failed_count(), $base + 1, 'a failed-seal report is counted');

    // idems_seal_content on the failed one now writes a real hash (self-heal).
    t_ok(idems_seal_content($failedId) === true, 'idems_seal_content returns true on success');
    $healed = ops_one("SELECT content_seal FROM report_docs WHERE id=?", [$failedId]);
    t_ok($healed['content_seal'] !== IDEMS_SEAL_FAILED && strlen((string)$healed['content_seal']) === 64,
        'the failed seal is repaired to a real 64-char hash');
    t_eq(idems_seal_failed_count(), $base, 'the failed-seal count returns to baseline after repair');

    // idems_reseal_failed heals in bulk and leaves pre-feature (empty) seals alone.
    $f2 = $mk(IDEMS_SEAL_FAILED);
    $n = idems_reseal_failed();
    t_ok($n >= 1, 'idems_reseal_failed repairs pending failed seals');
    $preSeal = (int)ops_val("SELECT COUNT(*) FROM report_docs WHERE COALESCE(content_seal,'')='' AND finalized=1");
    t_ok($preSeal >= 1, 'pre-feature (empty-seal) reports are left untouched by the healer');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The seal-failed state is surfaced to admins on the compliance checks.
$idems = file_get_contents(__DIR__ . '/../lib/idems.php');
$cron  = file_get_contents(__DIR__ . '/../cron.php');
t_ok(strpos($idems, 'idems_seal_failed_count()') !== false && strpos($idems, 'could not be content-sealed') !== false,
    'a failed seal shows on the IDEMS compliance checks');
t_ok(strpos($cron, 'idems_reseal_failed()') !== false, 'cron self-heals failed seals nightly');
