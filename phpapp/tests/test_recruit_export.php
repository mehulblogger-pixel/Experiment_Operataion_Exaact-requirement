<?php
// Phase 7 — recruitment CSV exports. The export honours the command-centre
// filters, respects the salary permission (no CTC column when not permitted),
// and produces a header row + one row per record. Additive; read-only.
t_section('recruitment exports (Phase 7)');

rcc_migrate();
$pdo = db();

// Seed a small, self-contained dataset.
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,sbu,grade,req_type,status,created_at) VALUES ('RQ-EX1','QA Engineer','Quality','BU1','M2','NEW','OPEN',?)")->execute([date('c')]);
$rq = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,designation,department,source,stage,requisition_id,cv_received_date,created_at) VALUES ('CV-EX1','Nisha','Verma','QA Engineer','Quality','CAREERS','SHORTLISTED',?, '2026-09-01', ?)")->execute([$rq, date('c')]);
$cx = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO job_offers (candidate_id,ctc,status,created_at) VALUES (?,?, 'APPROVED', ?)")->execute([$cx, 900000, date('c')]);

$f = ['fy'=>'', 'range'=>null, 'month'=>'', 'dept'=>'', 'source'=>'', 'manager'=>'', 'opts'=>['month'=>[]]];

// --- Candidates dataset ---
$rows = recruit_export_rows('candidates', $f);
t_ok(count($rows) >= 2, 'candidates export has a header + at least one data row');
t_eq($rows[0][0], 'Code', 'the first column header is the candidate code');
$hit = false; foreach ($rows as $r) if (($r[1] ?? '') === 'Nisha Verma') $hit = true;
t_ok($hit, 'the seeded candidate appears in the candidates export');

// --- Requisitions dataset ---
$rq_rows = recruit_export_rows('requisitions', $f);
$rhit = false; foreach ($rq_rows as $r) if (($r[0] ?? '') === 'RQ-EX1') $rhit = true;
t_ok($rhit, 'the seeded requisition appears in the requisitions export');

// --- Offers dataset: CTC column present when salary is permitted ---
$off_rows = recruit_export_rows('offers', $f);   // tests run as no-user → can_see_salary() true (master fallback)
t_ok(in_array('CTC', $off_rows[0], true) || !can_see_salary(), 'offers export includes a CTC column when salary is visible');
t_ok(count($off_rows) >= 2, 'offers export has a header + at least one data row');

// --- Funnel summary dataset ---
$fn = recruit_export_rows('funnel', $f);
$hasStage = false; foreach ($fn as $r) if (($r[0] ?? '') === 'Stage') $hasStage = true;
t_ok($hasStage, 'the funnel export contains a Stage/Reached section');

// --- Dataset menu + filename ---
t_ok(array_key_exists('candidates', recruit_export_datasets()), 'the dataset menu lists candidates');
$fn_name = recruit_export_filename('candidates', $f);
t_ok(substr($fn_name, -4) === '.csv' && strpos($fn_name, 'recruitment') === 0, 'the export filename is a sensible .csv name');

// --- A department filter narrows the candidate export ---
$f2 = $f; $f2['dept'] = 'Quality';
$rowsQ = recruit_export_rows('candidates', $f2);
$onlyQ = true; foreach (array_slice($rowsQ, 1) as $r) if (($r[3] ?? '') !== 'Quality' && ($r[3] ?? '') !== '') $onlyQ = false;
t_ok($onlyQ, 'a department filter narrows the candidates export');

// Clean up so the shared test DB is left as found.
$pdo->prepare("DELETE FROM job_offers WHERE candidate_id=?")->execute([$cx]);
$pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([$cx]);
$pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$rq]);
