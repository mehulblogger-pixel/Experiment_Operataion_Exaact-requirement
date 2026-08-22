<?php
// Merging a duplicate office repoints every *office_id reference to the office
// being kept, then removes the duplicate — the safe cleanup for a duplicate that
// already has work booked against it (Delete refuses those).
t_section('merge office repoints references and removes the duplicate');

$pdo = db();
$pdo->prepare("INSERT INTO offices (code,name,city,is_active) VALUES ('OM-KEEP','Keep Office','X',1)")->execute();
$keep = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (code,name,city,is_active) VALUES ('OM-DUP','Dup Office','X',1)")->execute();
$dup = (int)$pdo->lastInsertId();

// Point some records at the duplicate (a user and a job — both have *office_id).
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,home_office_id) VALUES ('om_u','U','COORDINATOR',1,?)")->execute([$dup]);
$uid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code,executing_office_id,created_at) VALUES ('OM-JOB',?,?)")->execute([$dup, date('c')]);
$jid = (int)$pdo->lastInsertId();

// The duplicate is in use, so it cannot simply be deleted.
t_ok(office_in_use($dup) !== [], 'the duplicate office is in use (delete would refuse it)');

$r = office_merge($dup, $keep);
t_ok(!empty($r['ok']), 'the merge completes');
t_ok((int)$r['moved'] >= 2, 'at least the user and the job were repointed');

// References now point at the kept office…
t_eq((int) ops_val("SELECT home_office_id FROM users WHERE id=?", [$uid]), $keep, 'the user now belongs to the kept office');
t_eq((int) ops_val("SELECT executing_office_id FROM jobs WHERE id=?", [$jid]), $keep, 'the job now executes at the kept office');
// …and the duplicate office is gone.
t_eq((int) ops_val("SELECT COUNT(*) FROM offices WHERE id=?", [$dup]), 0, 'the duplicate office has been removed');

// Guard rails.
t_ok(!empty(office_merge($keep, $keep)['err']), 'merging an office into itself is refused');
t_ok(!empty(office_merge($keep, 0)['err']), 'merging with no target is refused');
