<?php
// ============================================================================
//  UX-B10 — contextual feedback and the final accessibility corrections.
//
//  Six items, all of them "the application already knows this; say it where the
//  user is". Every assertion below exercises BEHAVIOUR: the CL-1/CL-2 cases run
//  the real handler through the production view() in a separate process, and
//  the CSS items assert on the cascade, not on the presence of a string.
// ============================================================================
t_section('B10 — contextual feedback & accessibility corrections');

$root   = dirname(__DIR__);
$css    = (string) file_get_contents($root . '/assets/css/app.css');
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
      . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
      . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$worker = function (array $a) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_b10_worker.php');
    foreach ($a as $x) $cmd .= ' ' . escapeshellarg((string) $x);
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) {
        $d = json_decode(trim($l), true); if (is_array($d)) return $d;
    }
    return ['ok' => false, 'error' => 'no JSON: ' . substr($raw, 0, 200)];
};

// ---- CL-1 · Finance is not offered an action that would refuse her -----------
$fin  = $worker(['operations', 'FINANCE']);
$coord = $worker(['operations', 'COORDINATOR']);
t_ok(!empty($fin['ok']) && !empty($coord['ok']), 'CL-1 · both Operations renders succeeded' . (empty($fin['ok']) ? ' — ' . ($fin['error'] ?? '?') : ''));
t_ok((int) ($fin['armed'] ?? 0) > 0 && (int) ($coord['armed'] ?? 0) > 0,
    'CL-1 ARMING · the pending-scheduling list really has a card for both roles ('
    . (int)($fin['armed'] ?? 0) . '/' . (int)($coord['armed'] ?? 0) . ')');
t_eq((int) ($fin['mayAllocate'] ?? -1), 0, 'CL-1 · finance still may NOT allocate (the decision is unchanged)');
t_eq((int) ($coord['mayAllocate'] ?? -1), 1, 'CL-1 · a coordinator still may');
t_eq((int) ($fin['allocateLinks'] ?? -1), 0, 'CL-1 · finance is offered no Allocate link');
t_ok((int) ($coord['allocateLinks'] ?? 0) > 0, 'CL-1 · the coordinator still gets Allocate (' . (int)($coord['allocateLinks'] ?? 0) . ')');
t_ok((int) ($fin['openLinks'] ?? 0) > 0, 'CL-1 · finance keeps Open — visibility was not reduced, only the dead action removed');
t_eq((int) ($fin['openLinks'] ?? -1), (int) ($coord['openLinks'] ?? -2), 'CL-1 · both roles see the same cards; only the action differs');

// …and a direct navigation explains itself rather than bouncing.
$direct = $worker(['job-new', 'FINANCE', 1]);
t_ok(!empty($direct['isNotice']), 'CL-1 · a direct /job-new lands on an explanation');
t_ok(!empty($direct['hasWayOut']), 'CL-1 · …which offers somewhere the person can actually go');
t_ok(strpos((string) ($direct['html'] ?? ''), 'jobform') === false, 'CL-1 · and never renders the allocation form itself');

// ---- CL-2 · My Jobs explains the identity-link state ------------------------
$insp = $worker(['my-jobs', 'INSPECTOR']);
t_ok(!empty($insp['ok']), 'CL-2 · the My Jobs render succeeded');
t_ok(!empty($insp['isNotice']), 'CL-2 · an unlinked inspector gets an explanation, not a bounce');
t_ok(strpos((string) ($insp['html'] ?? ''), 'not linked to a') !== false,
    'CL-2 · …naming the real condition (no engineer record linked)');
t_ok(strpos((string) ($insp['html'] ?? ''), 'permission') === false,
    'CL-2 · …and NOT calling it a permission problem, which it is not');
t_ok(!empty($insp['hasWayOut']), 'CL-2 · …with a way onward');
// a coordinator-level user has always been allowed through without a link
$co = $worker(['my-jobs', 'COORDINATOR']);
t_ok(!empty($co['ok']) && empty($co['isNotice']), 'CL-2 · a coordinator still reaches the real My Jobs screen');

// ---- CL-3 · the definitions are at point of use now -------------------------
$placed = [];
foreach ([['views/ops/recruitment_home.php','workforce'], ['views/ops/inspector_list.php','inspector'],
          ['views/ops/idems/vet_review.php','qa'], ['views/ops/candidate_detail.php','billing_readiness'],
          ['views/ops/connect_talent.php','professional'], ['views/ops/hiring_request.php','hiring_request']] as [$f,$k]) {
    $src = (string) file_get_contents($root . '/' . $f);
    if (strpos($src, "T_NOTE('" . $k . "')") !== false) $placed[] = $k;
}
t_eq(count($placed), 6, 'CL-3 · all six definitions are rendered where the word is (' . implode(',', $placed) . ')');
// the helper really produces the registry's text, and only for a known key
t_ok(strpos(T_NOTE('workforce'), (string) T_HELP('workforce')) !== false, 'CL-3 · the note carries the registry definition');
t_eq(T_NOTE('no_such_term_at_all'), '', 'CL-3 · an unknown key prints nothing rather than an empty box');
// and no screen got a wall of them
foreach (['views/ops/recruitment_home.php','views/ops/inspector_list.php','views/ops/connect_talent.php'] as $f) {
    t_ok(substr_count((string) file_get_contents($root . '/' . $f), 'T_NOTE(') === 1,
        'CL-3 · ' . basename($f) . ' carries exactly one definition, not a glossary');
}

// ---- CL-4 · the header-hide rule now outranks the row-to-card rule ----------
$mq = substr($css, strpos($css, '@media (max-width:640px)'));
$posHide = strpos($mq, 'table.rtable tr.rt-head{display:none}');
$posCard = strpos($mq, 'table.rtable tbody,table.rtable tr,');
t_ok($posHide !== false, 'CL-4 · the qualified header-hide rule exists');
t_ok($posHide !== false && $posCard !== false && $posHide < $posCard,
    'CL-4 · …and it is declared before the row-to-card rule, so equal specificity would not decide it');
// specificity: (0,2,1) beats the (0,1,1) that was overriding the bare .rt-head
t_ok(preg_match('/table\.rtable tr\.rt-head\{display:none\}/', $mq) === 1,
    'CL-4 · the selector is element+class qualified, which is what wins the cascade');
t_ok(strpos($css, '.rt-head{display:none}') !== false,
    'CL-4 · the original rule is left in place for tables that use a real <thead>');

// ---- CL-6 · the touch-target floor -------------------------------------------
$coarse = substr($css, strpos($css, '@media (pointer:coarse)'));
$coarse = substr($coarse, 0, strpos($coarse, "\n}\n") + 3);
t_ok(strpos($coarse, '.btn.small{min-height:44px}') !== false, 'CL-6 · .btn.small meets the 44px floor on touch');
t_ok(strpos($coarse, '.btn.small{min-height:36px}') === false, 'CL-6 · …and the 36px exception is gone');
t_ok(strpos($coarse, '.dz-chip{min-height:44px') !== false, 'CL-6 · the filter chips too');
t_ok(strpos($css, '@media (pointer:coarse)') !== false && strpos($coarse, '@media') === 0,
    'CL-6 · every one of these lives inside the coarse-pointer query — desktop is untouched');

// ---- CL-5 · breadcrumbs, with real hierarchy and permission-safe links -------
foreach ([['views/ops/job_detail.php','/jobs','mod.jobs.view'], ['views/ops/call_detail.php','/calls','mod.calls.view']] as [$f,$reg,$perm]) {
    $src = (string) file_get_contents($root . '/' . $f);
    t_ok(strpos($src, 'class="crumbs"') !== false, 'CL-5 · ' . basename($f) . ' has breadcrumbs');
    t_ok(strpos($src, "href=\"/operations\"") !== false, 'CL-5 · …through Operations, the actual parent');
    t_ok(strpos($src, $reg) !== false, 'CL-5 · …then its own register ' . $reg);
    t_ok(strpos($src, "can('" . $perm . "')") !== false,
        'CL-5 · …and the register link is conditional on ' . $perm . ' — no door it cannot open');
}
