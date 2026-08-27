<?php
// Field-finding #11 — allocating a job with no one chosen bounced to the FIRST tab with a highlight, not to
// the field's own tab. The who-carries-it-out rule is an either/or (an inspector OR a sub-contractor), so a
// plain `required` can't express it. Fix: a client-side either/or guard blocks the submit and jumps to the
// Engineer tab + focuses the picker (no server round-trip); and on a server re-render an `error_field` hint
// sends the form to the same field's tab. So a missing choice always lands on the missing screen only.
t_section('Field #11 — a missing allocate field lands on its own tab, not the first');

// The server passes the field hint on the who-carries-it-out error.
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "'error_field' => 'inspector_id'") !== false, 'the allocate handler tells the form which field failed');

// The form carries the either/or guard, the tab-jump, and the message ON the Engineer tab.
$jf = file_get_contents(__DIR__ . '/../views/ops/job_form.php');
t_ok(strpos($jf, "id=\"jobform\"") !== false, 'the allocate form has an id');
t_ok(strpos($jf, "form.addEventListener('submit'") !== false, 'the form validates on submit (client-side)');
t_ok(strpos($jf, '[name="inspector_id"]') !== false && strpos($jf, '[name="subcon_id"]') !== false,
     'the guard checks both the inspector and the sub-contractor (either/or)');
t_ok(strpos($jf, "e.preventDefault()") !== false, 'a missing choice blocks the submit (no bounce)');
t_ok(strpos($jf, "closest('[data-tab]')") !== false && strpos($jf, "b.click()") !== false,
     'it opens the tab that contains the missing field');
t_ok(strpos($jf, 'json_encode($error_field ?? \'\')') !== false, 'a server round-trip focuses the flagged field too');

// The who-carries-it-out message sits on the Engineer tab (the field\'s tab), not only at the top.
$engPos  = strpos($jf, 'data-tab="Engineer"');
$whoPos  = strpos($jf, 'id="who-msg"');
$orderPos = strpos($jf, 'data-tab="Order');
t_ok($engPos !== false && $whoPos !== false && $whoPos > $engPos && ($orderPos === false || $whoPos < $orderPos),
     'the who-carries-it-out message lives on the Engineer tab, beside the pickers');

// The inspector picker still allows an empty option (so the either/or is not broken by a hard `required`).
t_ok(strpos($jf, "name=\"inspector_id\"><option value=\"\">") !== false, 'the inspector select keeps its empty option (either/or preserved)');
