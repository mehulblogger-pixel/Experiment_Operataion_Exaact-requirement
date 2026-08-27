<?php
// Field-finding #24 — a job closes ONCE. The close buttons were already hidden on every list/detail once a
// job is closed, but the /job-close FORM itself (GET) had no closed guard — so reaching it on a closed job
// (a stale page, the back button, a bookmarked form, a second tab, the offline queue) re-showed the expense
// sheet and let the same day's expenses be entered again. Fix: one closed guard that short-circuits BOTH
// GET and POST, positioned before the POST branch. No data written; the guard redirects with a message.
t_section('Field #24 — job closes once (no re-close, no re-entered expenses)');

$ops = file_get_contents(__DIR__ . '/../lib/ops.php');

// Locate the job-close handler.
$h = strpos($ops, "if (\$route === 'job-close')");
t_ok($h !== false, 'the job-close handler exists');
$body = substr($ops, $h, 2600);

// The already-closed guard exists...
$guard = strpos($body, "Field-finding #24");
t_ok($guard !== false, 'an already-closed guard is present in the job-close handler');
// ...and it sits BEFORE the POST branch, so a GET (the form) is short-circuited too, not just the POST.
$post = strpos($body, "if (\$method === 'POST')");
t_ok($guard !== false && $post !== false && $guard < $post, 'the closed guard runs before the POST branch (covers the GET form)');
// The guard redirects rather than rendering the form.
$guardBlock = substr($body, $guard, ($post - $guard));
t_ok(strpos($guardBlock, "!empty(\$job['closed_flag'])") !== false, 'the guard keys off closed_flag');
t_ok(strpos($guardBlock, "redirect('/job?id=") !== false, 'the guard redirects to the job (form not shown)');
// The old duplicate POST-only block is gone (single source of the rule).
t_ok(substr_count($body, "Nothing was recorded twice") === 1, 'the double-close message lives in exactly one place');

// The close buttons on every surface are hidden once the job is closed (no un-greyed button).
$jd = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(strpos($jd, "!\$job['closed_flag']") !== false, 'job detail hides Close when closed');
$mj = file_get_contents(__DIR__ . '/../views/ops/my_jobs.php');
t_ok(strpos($mj, "!\$j['closed_flag']") !== false, 'the inspector dashboard hides Close when closed');
$jl = file_get_contents(__DIR__ . '/../views/ops/jobs.php');
t_ok(strpos($jl, "!\$j['closed_flag']") !== false, 'the jobs register hides Close when closed');
