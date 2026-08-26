<?php
// Module 27 — Confidentiality (ISO 17020 §4.2). Connect the existing undertaking / NDA / breach
// registers to the WORK (a read-only per-job §4.2 banner) and to the compliance readiness board.
// Plus the first coverage of the governance pillar's own logic.
t_section('Module 27 — confidentiality at the point of work + readiness');

$comp = file_get_contents(__DIR__ . '/../lib/compliance.php');

t_ok(function_exists('conf_undertaking_live'), 'conf_undertaking_live() exists');
t_ok(function_exists('conf_job_status'), 'conf_job_status() exists');
t_ok(function_exists('conf_open_breach_count'), 'conf_open_breach_count() exists');

// ---- undertaking in-force logic (first coverage) ----
$today = date('Y-m-d');
t_ok(conf_undertaking_live(['signed_on' => date('Y-m-d', strtotime('-30 days')), 'valid_to' => '']) === true,
     'an undertaking signed in the past with no expiry is in force');
t_ok(conf_undertaking_live(['signed_on' => date('Y-m-d', strtotime('+5 days')), 'valid_to' => '']) === false,
     'an undertaking dated in the future is not yet in force');
t_ok(conf_undertaking_live(['signed_on' => '2020-01-01', 'valid_to' => date('Y-m-d', strtotime('-1 day'))]) === false,
     'an expired undertaking is not in force');

// ---- NDA obligation end ----
t_eq(conf_nda_obligation_ends(['valid_to' => '2026-12-31', 'survives_months' => 24]), '2028-12-31',
     'the NDA obligation runs survives_months beyond valid_to');
t_eq(conf_nda_obligation_ends(['valid_to' => '', 'survives_months' => 24]), '',
     'no valid_to → no computed obligation end');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('conf_migrate')) conf_migrate();
    $pdo = db();

    // Three inspectors: one covered, one lapsed, one with nothing.
    $mkIns = function ($name) use ($pdo) { $pdo->prepare("INSERT INTO inspectors (name, status) VALUES (?, 'ACTIVE')")->execute([$name]); return (int)$pdo->lastInsertId(); };
    $covered = $mkIns('Covered One');
    $lapsedI = $mkIns('Lapsed One');
    $none    = $mkIns('Bare One');
    $mkU = function ($pid, $signed, $validTo) use ($pdo) {
        $pdo->prepare("INSERT INTO confidentiality_undertakings (person_kind, person_id, kind, signed_on, valid_to) VALUES ('INSPECTOR', ?, 'EMPLOYEE', ?, ?)")
            ->execute([$pid, $signed, $validTo]);
    };
    $mkU($covered, date('Y-m-d', strtotime('-10 days')), '');
    $mkU($lapsedI, '2020-01-01', date('Y-m-d', strtotime('-2 days')));

    $cov = conf_coverage();
    $byId = []; foreach ($cov as $c) $byId[$c['id']] = $c['state'];
    t_eq($byId[$covered], 'ok', 'an inspector with a live undertaking is covered');
    t_eq($byId[$lapsedI], 'lapsed', 'an inspector whose undertaking expired shows as lapsed');
    t_eq($byId[$none], 'none', 'an inspector with nothing on file shows as none');

    $ready = conf_readiness();
    t_ok($ready['covered'] >= 1 && $ready['lapsed'] >= 1 && $ready['none'] >= 1, 'readiness rolls up covered / lapsed / none');

    // A client with an NDA, and a job for that client assigned to the covered inspector.
    $pdo->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, status) VALUES ('NDA Co','NDA Co Ltd',1,'ACTIVE')")->execute();
    $cli = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO client_ndas (partner_id, title, valid_to, survives_months) VALUES (?, 'Master NDA', '2027-01-01', 12)")->execute([$cli]);
    $pdo->prepare("INSERT INTO calls (client_id, call_code, status) VALUES (?, 'C-27', 'OPEN')")->execute([$cli]);
    $callId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (call_id, job_code, inspector_id) VALUES (?, 'J-27', ?)")->execute([$callId, $covered]);
    $job = ops_one("SELECT * FROM jobs WHERE job_code='J-27'");

    $st = conf_job_status($job);
    t_eq($st['inspector']['state'], 'ok', 'the job shows the assigned inspector as confidentiality-covered');
    t_ok($st['nda'] !== null && $st['nda']['ends'] === '2028-01-01', 'the job shows the client NDA obligation end (valid_to + survives)');

    // A job assigned to the bare inspector → none.
    $pdo->prepare("INSERT INTO jobs (call_id, job_code, inspector_id) VALUES (?, 'J-27b', ?)")->execute([$callId, $none]);
    $job2 = ops_one("SELECT * FROM jobs WHERE job_code='J-27b'");
    t_eq(conf_job_status($job2)['inspector']['state'], 'none', 'a job whose inspector has no undertaking shows none');

    // Open-breach count.
    t_ok(conf_open_breach_count() === 0, 'no open breaches on a clean set');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- compliance board registration + preservation ----
t_ok(strpos($comp, "conf_readiness()") !== false, 'confidentiality readiness is now on the compliance board');
t_ok(strpos($comp, "§4.2") !== false, 'the board row cites ISO 17020 §4.2');
t_ok(strpos($comp, 'imp_readiness') !== false, 'the impartiality board row is preserved (additive, not replaced)');
$view = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(strpos($view, 'Confidentiality (§4.2)') !== false, 'the job shows a confidentiality panel');
t_ok(strpos($view, 'nothing here blocks') !== false, 'the job confidentiality surface is advisory, not a gate');
