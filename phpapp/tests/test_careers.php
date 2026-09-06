<?php
// Phase 7 — public careers page + application intake. Publishing is opt-in per
// requisition; an application creates a real candidate (source=CAREERS) against
// that requisition; the honeypot and light de-dupe guards hold. Additive.
t_section('careers page & application intake (Phase 7)');

careers_migrate();
$pdo = db();

// Two requirements — one advertised, one not.
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,status,careers_published,careers_summary,created_at) VALUES ('RQ-CAR1','Site Supervisor','Projects','OPEN',1,'Lead site works.', ?)")->execute([date('c')]);
$pub = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,status,careers_published,created_at) VALUES ('RQ-CAR2','Internal Only','HR','OPEN',0, ?)")->execute([date('c')]);
$priv = (int)$pdo->lastInsertId();

// --- Only published openings are public ---
$open = careers_open_jobs();
$ids = array_map(fn($r) => (int)$r['id'], $open);
t_ok(in_array($pub, $ids, true), 'a published opening is listed on the careers page');
t_ok(!in_array($priv, $ids, true), 'an unpublished opening is NOT listed publicly');
t_ok(careers_job($pub) !== null, 'a published opening can be fetched by id');
t_ok(careers_job($priv) === null, 'an unpublished opening cannot be fetched publicly');

// --- A valid application creates a candidate on that requisition ---
$job = careers_job($pub);
[$ok, $msg, $cid] = careers_apply($job, ['first_name'=>'Ramesh','last_name'=>'Iyer','email'=>'ramesh.iyer@example.com','mobile'=>'9876500011','experience_years'=>'6','message'=>'Keen to join.'], []);
t_ok($ok && $cid > 0, 'a valid application creates a candidate');
$c = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
t_eq($c['source'], 'CAREERS', 'the candidate is tagged source=CAREERS');
t_eq((int)$c['requisition_id'], $pub, 'the candidate is linked to the advertised requisition');
t_eq($c['stage'], 'RECEIVED', 'the candidate starts at RECEIVED');
t_eq($c['designation'], 'Site Supervisor', 'the candidate inherits the role title from the requisition');
t_ok((int)ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=? AND to_stage='RECEIVED'", [$cid]) >= 1, 'the intake is logged to the candidate timeline');

// --- Honeypot: a bot submission creates nothing ---
$before = (int)ops_val("SELECT COUNT(*) FROM candidates", []);
[$hok, $hmsg, $hid] = careers_apply($job, ['first_name'=>'Bot','last_name'=>'Spam','email'=>'bot@spam.test','website'=>'http://spam'], []);
t_ok($hok && $hid === 0, 'a honeypot submission is silently accepted but creates nothing');
t_eq((int)ops_val("SELECT COUNT(*) FROM candidates", []), $before, 'no candidate row is created for the honeypot submission');

// --- Validation: missing name / missing contact ---
[$vok] = careers_apply($job, ['first_name'=>'', 'email'=>'x@y.com'], []);
t_ok(!$vok, 'an application with no name is rejected');
[$vok2] = careers_apply($job, ['first_name'=>'NoContact'], []);
t_ok(!$vok2, 'an application with neither email nor mobile is rejected');
[$vok3] = careers_apply($job, ['first_name'=>'Bad','email'=>'not-an-email'], []);
t_ok(!$vok3, 'an application with a malformed email is rejected');

// --- Light de-dupe: same email + same opening does not double-create ---
$n1 = (int)ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=?", [$pub]);
[$dok, $dmsg, $did] = careers_apply($job, ['first_name'=>'Ramesh','last_name'=>'Iyer','email'=>'ramesh.iyer@example.com'], []);
t_ok($dok, 're-applying to the same opening is accepted gracefully');
t_eq((int)ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=?", [$pub]), $n1, 'the same applicant is not created twice on one opening');

// --- Enable flag gates the public site ---
setting_set('careers_enabled', '1');
t_ok(careers_enabled(), 'the careers page reports enabled when the setting is on');
setting_set('careers_enabled', '0');
t_ok(!careers_enabled(), 'the careers page reports disabled when the setting is off');

// Clean up.
$pdo->prepare("DELETE FROM candidate_events WHERE candidate_id IN (SELECT id FROM candidates WHERE requisition_id IN (?,?))")->execute([$pub, $priv]);
$pdo->prepare("DELETE FROM candidates WHERE requisition_id IN (?,?)")->execute([$pub, $priv]);
$pdo->prepare("DELETE FROM requisitions WHERE id IN (?,?)")->execute([$pub, $priv]);
