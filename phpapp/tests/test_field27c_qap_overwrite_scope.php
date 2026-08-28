<?php
// Field-finding #27 (stage c) — a new/revised QAP (or an added item on the inspection day) can
// OVERWRITE the report's inspection scope with what it reads from the QAP; hold/witness points are
// RETAINED (they live on hw_points, not the report body); and the overwrite happens only after an
// on-screen pop-up confirmation. An empty scope just fills (append), no confirmation needed.
t_section('Field #27c — revised QAP overwrites scope, hold points retained');

if (function_exists('idems_seed_report_types')) idems_seed_report_types();
$rt = (int)ops_val("SELECT id FROM report_types WHERE code='MGHIR' LIMIT 1");
$field = idems_scope_target_field($rt);
t_eq('scope_activities', (string)($field['fkey'] ?? ''), 'the report type has a scope table to fill/overwrite');

$pdo = db();
$pdo->prepare("INSERT INTO jobs (job_code, created_at) VALUES ('JQC-C',?)")->execute([date('c')]);
$job = (int)$pdo->lastInsertId();
$qapText = "Inspection scope\n1. Visual inspection of welds\n2. Dimensional check per drawing\n3. NDT - UT of critical joints\n4. Coating thickness measurement\n";
$pdo->prepare("INSERT INTO job_qaps (job_id,po_line,file_name,mime,data,note,uploaded_by,uploaded_at) VALUES (?,?,?,?,?,?,?,?)")
    ->execute([$job, '', 'itp-rev.txt', 'text/plain', $qapText, '', 'T', date('c')]);
$qid = (int)$pdo->lastInsertId();

$existing = json_encode([$field['fkey'] => [['x' => 'typed row 1'], ['x' => 'typed row 2']]]);
$pdo->prepare("INSERT INTO report_docs (irn,type_code,report_type_id,job_id,status,data,deleted,created_at) VALUES ('IRN-QC-C','MGHIR',?,?,'DRAFT',?,0,?)")
    ->execute([$rt, $job, $existing, date('c')]);
$rep = (int)$pdo->lastInsertId();

// A hold point on this job — it must survive a scope overwrite.
$pdo->prepare("INSERT INTO hw_points (job_id, point_type, qap_clause, status, dedupe_key, created_at) VALUES (?, 'HOLD', '3', 'OPEN', ?, ?)")
    ->execute([$job, 'hp-' . $job . '-3', date('c')]);
$hpBefore = (int)ops_val("SELECT COUNT(*) FROM hw_points WHERE job_id=? AND status='OPEN'", [$job]);
t_ok($hpBefore === 1, 'a hold point exists on the job before the overwrite');

// APPEND (empty-scope behaviour): the 4 QAP rows add to the 2 typed rows → 6.
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$rep]);
$r1 = idems_scope_from_qap($doc, $qid, false);
t_ok(empty($r1['err']) && (int)$r1['n'] === 4, 'the QAP yields 4 scope activities');
$d1 = json_decode((string)ops_val("SELECT data FROM report_docs WHERE id=?", [$rep]), true);
t_eq(6, count($d1[$field['fkey']]), 'append adds the QAP rows to the existing scope (2 + 4 = 6)');

// OVERWRITE: reset to the 2 typed rows, then a revised QAP REPLACES them → just the 4.
$pdo->prepare("UPDATE report_docs SET data=? WHERE id=?")->execute([$existing, $rep]);
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$rep]);
$r2 = idems_scope_from_qap($doc, $qid, true);
t_ok(empty($r2['err']) && (int)$r2['n'] === 4, 'the revised QAP yields 4 activities on overwrite');
t_eq(2, (int)($r2['overwrote'] ?? -1), 'it reports the 2 rows it replaced');
$d2 = json_decode((string)ops_val("SELECT data FROM report_docs WHERE id=?", [$rep]), true);
t_eq(4, count($d2[$field['fkey']]), 'overwrite REPLACES the scope with just the QAP rows (not merged)');

// Hold points are RETAINED across the overwrite (they live on hw_points, not the report body).
t_eq($hpBefore, (int)ops_val("SELECT COUNT(*) FROM hw_points WHERE job_id=? AND status='OPEN'", [$job]),
     'the hold point survives the scope overwrite');

// --- Wiring ---
$src = file_get_contents(__DIR__ . '/../lib/idems.php');
t_ok(strpos($src, 'function idems_scope_from_qap($doc, $qapId, $overwrite = false)') !== false,
     'the scope fill takes an overwrite flag');
t_ok(strpos($src, "\$overwrite = !empty(\$_POST['overwrite'])") !== false,
     'the handler reads overwrite from the request');
$fill = file_get_contents(__DIR__ . '/../views/ops/idems/fill.php');
t_ok(strpos($fill, "\$scopeHasRows = ") !== false && strpos($fill, 'name="overwrite" value="1"') !== false,
     'the fill form sends overwrite=1 only when the scope already has rows');
t_ok(strpos($fill, 'This will OVERWRITE the current inspection scope') !== false
     && strpos($fill, 'hold / witness points are KEPT') !== false,
     'overwriting is behind a pop-up confirmation that reassures hold points are kept');

// Clean up (shared DB).
$pdo->prepare("DELETE FROM hw_points WHERE job_id=?")->execute([$job]);
$pdo->prepare("DELETE FROM job_qaps WHERE job_id=?")->execute([$job]);
$pdo->prepare("DELETE FROM report_docs WHERE id=?")->execute([$rep]);
$pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$job]);
