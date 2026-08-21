<?php
// The job screen's heavy Overview sections are now collapsible, the status /
// actions / contacts stay open, the competence advisory is gated, and a deep
// link opens its collapsed section.
t_section('job screen simplified (collapsible Overview)');

$jd = (string)file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(substr_count($jd, 'class="fold" data-tab="Overview"') >= 4,
    'the heavy Overview sections are collapsed (assignment / readiness / comms / full details)');
t_ok(strpos($jd, 'Assignment lifecycle <span class="sub"') !== false, 'assignment lifecycle is collapsible');
t_ok(strpos($jd, 'Readiness &amp; confirmation <span class="sub"') !== false, 'readiness & confirmation is collapsible');
t_ok(strpos($jd, 'Communication log <span class="sub"') !== false, 'communication log is collapsible');
t_ok(strpos($jd, 'call, stage, office, dates, reporting') !== false, 'the full-details grid is collapsed behind an expander');

// The contacts panel stays open (rendered as a plain panel, not a fold).
$pos = strpos($jd, 'Who to contact, and where to go');
t_ok($pos !== false, 'the contacts panel is still there');
$before = substr($jd, max(0, $pos - 200), 200);
t_ok(strpos($before, '<div class="panel"') !== false && strpos($before, '<details') === false,
    'contacts remain a plain (open) panel, not collapsed');

// A deep link opens its collapsed section.
t_ok(strpos($jd, 'openForHash') !== false, 'a #assign/#ready deep link opens the collapsed section');

$ts = (string)file_get_contents(__DIR__ . '/../lib/tosrm.php');
t_ok(strpos($ts, "\$showComp = \$compErr || (function_exists('auth_enforced')") !== false,
    'the competence advisory is hidden when enforcement is off and there is no real blocker');
