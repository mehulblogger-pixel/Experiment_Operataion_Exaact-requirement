<?php
// C2 — coordinator screen polish. The call form was already decluttered (tabs, plus
// the credit/cross-office fields and the pattern/monthly/multi-date fields only appear
// when that option is chosen — a contextual toggle, better than a blanket fold). The
// one always-expanded secondary panel on the job detail was "Hold & witness points":
// it is only actionable when a point is OPEN, so it is now a fold that auto-opens when
// there is something to clear/waive and stays collapsed otherwise.
t_section('job detail: Hold & witness points folds when there is nothing to act on');

$src = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');

// The panel is now a fold, not an always-open panel.
t_ok(strpos($src, '<details class="fold" id="holdpoints" data-tab="Reports &amp; QA"') !== false,
    'the hold/witness panel is a collapsible fold');
// It auto-opens only when there are open points (action needed).
t_ok(strpos($src, "id=\"holdpoints\" data-tab=\"Reports &amp; QA\" <?= \$hwOpen ? 'open' : '' ?>") !== false,
    'the fold auto-opens only when a point is open');
// The summary still shows the count so a collapsed panel is not hidden knowledge.
t_ok(preg_match('/<summary>Hold &amp; witness points <span class="sub">\(<\?= count\(\$hwPts\)/', $src) === 1,
    'the collapsed summary still shows the point count');
// The forms (clear/waive, add, re-scan) survive inside the fold body (in DOM, just collapsed).
t_ok(strpos($src, '/hw-point-new') !== false && strpos($src, '/hw-point-derive') !== false,
    'the add / re-scan forms are still present');
// The deep-link opener already expands a <details> for a #hash target (kept).
t_ok(strpos($src, "el.closest('details')") !== false,
    'a #holdpoints deep link still opens the collapsed fold');

// The call form's advanced groups are contextually toggled (the better-than-a-fold
// mechanism), so they are genuinely off the common path already.
$cf = file_get_contents(__DIR__ . '/../views/ops/call_form.php');
t_ok(strpos($cf, 'class="ff eng-box" data-for="PATTERN"') !== false
    && strpos($cf, 'data-for="MONTHLY"') !== false,
    'pattern / monthly fields only show for that engagement shape (contextual, not on the common path)');
t_ok(strpos($cf, 'id="crossbox" style="display:none"') !== false,
    'the cross-office credit fields stay hidden until a cross-office call is set up');
