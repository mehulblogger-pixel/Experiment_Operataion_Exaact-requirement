<?php
// Field-finding #20 — after closing/waiving a hold-witness point the screen jumped to the job's "main
// screen" and the change looked "not reflected", even for one of several points. Root cause: the handler
// correctly redirects to /job?id=X#holdpoints and hwp_close persists the status — but the job screen is
// tab-based, and the tab init only understood a #job=<tab-slug> hash, NOT a bare element-id hash like
// #holdpoints. So the page opened on the first tab (Overview) with the hold-points panel (on "Reports &
// QA") hidden — reading as "went to the main screen" and "not reflected". Fix: the tab init now opens the
// tab that CONTAINS a bare-id hash target and scrolls to it.
t_section('Field #20 — hold-point close stays on the panel + is reflected');

// --- The JS fix: a bare-id hash activates its tab ---
$js = file_get_contents(__DIR__ . '/../assets/js/app.js');
t_ok(strpos($js, 'Field-finding #20') !== false, 'the tab init documents the bare-id-hash fix');
t_ok(strpos($js, 'wrap.querySelector(location.hash)') !== false, 'the tab init resolves a bare element-id hash within the tab wrap');
t_ok(strpos($js, 'p === hashEl || p.contains(hashEl)') !== false, 'it opens the tab whose panel contains the hash target');

// --- The handler redirects back to the hold-points panel (does not go elsewhere) ---
$hw = file_get_contents(__DIR__ . '/../lib/hwpoints.php');
t_ok(strpos($hw, "'#holdpoints'") !== false && strpos($hw, 'redirect($back)') !== false, 'clear/waive redirects back to the job hold-points anchor');
// The hold-points panel lives on a tab, which is why the hash-to-tab fix is needed.
$jd = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(strpos($jd, 'id="holdpoints"') !== false && strpos($jd, "data-tab=\"Reports &amp; QA\"") !== false,
     'the hold-points panel is a tab panel (needs the hash-to-tab activation)');

// --- The data side: closing one of several points IS reflected ---
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$prevUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('hwp_migrate')) hwp_migrate();
    $pdo = db();
    $pdo->prepare("INSERT INTO users (username, is_superuser, is_active) VALUES ('m20',1,1)")->execute();
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);
    $pdo->prepare("INSERT INTO hw_points (job_id, point_type, description, status, dedupe_key, created_at) VALUES (7720,'HOLD','A','OPEN','k20a','2026-06-01')")->execute();
    $pdo->prepare("INSERT INTO hw_points (job_id, point_type, description, status, dedupe_key, created_at) VALUES (7720,'WITNESS','B','OPEN','k20b','2026-06-01')")->execute();

    $open = 0; foreach (hwp_for_job(7720) as $p) if ($p['status'] === 'OPEN') $open++;
    t_eq($open, 2, 'both points start open');

    $first = hwp_for_job(7720)[0]['id'];
    t_ok(hwp_close((int)$first, 'CLEARED', '', 'done'), 'one point clears');

    $o = 0; $c = 0; foreach (hwp_for_job(7720) as $p) { if ($p['status'] === 'OPEN') $o++; if ($p['status'] === 'CLEARED') $c++; }
    t_eq($o, 1, 'exactly one point remains open (the other is reflected as closed)');
    t_eq($c, 1, 'the closed point is reflected as CLEARED — not lost');
} finally {
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}
