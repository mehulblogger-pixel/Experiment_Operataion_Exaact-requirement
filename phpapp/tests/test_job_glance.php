<?php
// "Everything on this job, at a glance": job_glance() ties a job's related things
// together as counted, linked chips. Guards the counts (reports, release notes,
// NCRs, expenses, photos) and that it degrades safely for an empty job.
t_section('job at-a-glance index');

$pdo = db();
if (function_exists('ncr_migrate')) { try { ncr_migrate(); } catch (Throwable $e) {} }
$typeId = idems_build_fire_extinguisher_report();

// A job with nothing on it yet.
$pdo->prepare("INSERT INTO jobs (job_code, created_at) VALUES ('JG-1', ?)")->execute([date('c')]);
$jid = (int)$pdo->lastInsertId();
$job = ops_one("SELECT * FROM jobs WHERE id=?", [$jid]);

// Make the NCR register reachable for the at-a-glance checks: a master, with the
// inspection pack on (the default). The register is a full route guarded twice —
// mod.ncr.view AND an accreditation pack — so the chip only links when both hold.
setting_set('packs_enabled', 'inspection'); packs_enabled(true);
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser) VALUES ('jg_master','JG','MASTER_ADMIN',1,1)")->execute();
$jgMaster = (int)$pdo->lastInsertId();
$_SESSION['uid'] = $jgMaster; current_user(true); ua(true);

$g = job_glance($job);
foreach (['reports','releases','qaps','ncrs','invoices','expenses','photos'] as $k)
    t_ok(isset($g[$k]) && (int)$g[$k]['n'] === 0, "empty job: $k count is 0");
t_ok($g['reports']['href'] === '#reports', 'reports chip links to the reports section');
// The NCR chip links to the register, scoped to this job so it opens in context.
t_ok(strpos($g['ncrs']['href'], '/ncr') === 0, 'NCR chip links to the NCR register');
t_ok(strpos($g['ncrs']['href'], 'job=' . $jid) !== false, 'the NCR chip is scoped to this job');

// When the viewer cannot reach the register (no session → not a master, no
// mod.ncr.view) the chip must NOT link — a live link would bounce to the
// dashboard. It shows the count, inert.
unset($_SESSION['uid']); current_user(true); ua(true);
$gNo = job_glance($job);
t_ok($gNo['ncrs']['href'] === '', 'the NCR chip does not link when the register is unreachable');
// Restore the master session for the remaining counts.
$_SESSION['uid'] = $jgMaster; current_user(true); ua(true);

// Add an inspection report, a release note, a photo, an expense and an NCR.
$pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,status,job_id,data,created_at) VALUES (?,?,?,?,?,?,?)")
    ->execute([$typeId,'FEXT','JG-IR','ISSUED',$jid,'[]',date('c')]);
$irId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,status,job_id,data,created_at) VALUES (?,?,?,?,?,?,?)")
    ->execute([$typeId,'RN','JG-RN','DRAFT',$jid,'[]',date('c')]);
$pdo->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,created_at) VALUES (?,?,?,?,?,?,?)")
    ->execute([$irId,'photos','photo','p.jpg','image/jpeg','x',date('c')]);
$pdo->prepare("INSERT INTO expenses (job_id, created_at) VALUES (?, ?)")->execute([$jid, date('c')]);
try { $pdo->prepare("INSERT INTO nonconformities (ref, job_id, created_at) VALUES ('NCR-JG', ?, ?)")->execute([$jid, date('c')]); } catch (Throwable $e) {}

$g2 = job_glance($job);
t_eq((int)$g2['reports']['n'], 2, 'both reports (IR + RN) are counted');
t_eq((int)$g2['releases']['n'], 1, 'the release note is counted separately');
t_eq((int)$g2['photos']['n'], 1, 'the photo is counted');
t_eq((int)$g2['expenses']['n'], 1, 'the expense is counted');
t_ok((int)$g2['ncrs']['n'] >= 1, 'the NCR is counted (when the module is present)');

// A missing job id must not error — it simply counts nothing.
$g3 = job_glance(['id' => 0]);
t_ok((int)$g3['reports']['n'] === 0, 'a zero/missing job id counts nothing, without error');

// Do not leak the master session into later tests.
unset($_SESSION['uid']); current_user(true); ua(true);
