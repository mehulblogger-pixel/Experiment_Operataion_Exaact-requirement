<?php
// Field-finding #22 — inspector scope leak. Two logins pointed at the SAME team member (`inspectors` row)
// each saw that person's jobs/schedule, because every schedule query correctly filters by inspector_id —
// so a shared inspector_id leaks work between two people. Fix: one team member = one login. A save-time
// guard refuses linking a second login to an already-linked member, and a §7.11 integrity check flags any
// collision that predates the guard. Read-only detection; the guard blocks the bad save.
t_section('Field #22 — one team member, one login (inspector scope leak)');

t_ok(function_exists('inspector_login_conflict') && function_exists('inspector_shared_login_count'),
     'the conflict + count helpers exist');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, 'inspector_login_conflict((int)$insId') !== false, 'the user-save form checks for a login conflict');
$dc = file_get_contents(__DIR__ . '/../lib/datacontrol.php');
t_ok(strpos($dc, "'inspector_one_login'") !== false, 'the integrity board includes the one-login check');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    $pdo->prepare("INSERT INTO inspectors (name, status) VALUES ('Vijay','ACTIVE')")->execute();
    $vijay = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO inspectors (name, status) VALUES ('Ravi','ACTIVE')")->execute();
    $ravi = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, role, inspector_id, is_active) VALUES ('vijay','INSPECTOR',?,1)")->execute([$vijay]);
    $uVijay = (int)$pdo->lastInsertId();

    // The guard: a NEW login trying to claim Vijay's team member is refused (names the clash).
    t_eq(inspector_login_conflict($vijay, 0), 'vijay', 'picking an already-linked team member reports the conflict');
    // Editing Vijay's own login keeps his link (no false conflict with himself).
    t_eq(inspector_login_conflict($vijay, $uVijay), '', 'a login editing its own linked member is not a conflict');
    // A different, unclaimed team member is free.
    t_eq(inspector_login_conflict($ravi, 0), '', 'an unlinked team member has no conflict');

    // Before any collision, the detector is clean.
    $base = inspector_shared_login_count();
    // Create the collision (the old mis-link) and confirm it is detected + surfaces on the board.
    $pdo->prepare("INSERT INTO users (username, role, inspector_id, is_active) VALUES ('ravi','INSPECTOR',?,1)")->execute([$vijay]);
    t_eq(inspector_shared_login_count(), $base + 1, 'a team member shared by two logins is counted once');

    // Prove the leak the fix prevents: with the shared link, both logins resolve to the same inspector and
    // see the same job — which is exactly why the guard now refuses that link.
    $pdo->prepare("INSERT INTO jobs (job_code, inspector_id, closed_flag) VALUES ('JB-V',?,0)")->execute([$vijay]);
    $vSees = (int) ops_val("SELECT COUNT(*) FROM jobs WHERE inspector_id=(SELECT inspector_id FROM users WHERE username='vijay')");
    $rSees = (int) ops_val("SELECT COUNT(*) FROM jobs WHERE inspector_id=(SELECT inspector_id FROM users WHERE username='ravi')");
    t_ok($vSees === 1 && $rSees === 1, 'the shared link is what leaks the job to both logins (root cause)');

    // The integrity check flags it (found > 0, not ok).
    if (function_exists('integrity_checks')) {
        $row = null; foreach (integrity_checks() as $c) if ($c['key'] === 'inspector_one_login') $row = $c;
        t_ok($row !== null, 'the one-login integrity check is registered');
        t_ok(($row['found'] ?? 0) >= 1 && ($row['ok'] ?? true) === false, 'the check flags the shared-login collision');
    }

    // Give Ravi his OWN team member — the collision clears.
    $pdo->prepare("UPDATE users SET inspector_id=? WHERE username='ravi'")->execute([$ravi]);
    t_eq(inspector_shared_login_count(), $base, 'once each login has its own team member, nothing is shared');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
