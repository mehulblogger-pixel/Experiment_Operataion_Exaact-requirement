<?php
// ---------------------------------------------------------------------------
//  UAT A16 / A8 — THE WORKFLOW DID NOT MOVE WHEN THE WORK MOVED.
//
//    A16: "It records as fail but there is no option of reject the test
//          candidate."
//    A8:  "Hiring Work flow shall automatically updated stage wise when either
//          Pipeline, Overview, Interviews, documents etc. is updated. IF there
//          is any duplicacy of the work that must be unified."
//
//  Recording Fail / No hire left the candidate at "Interview scheduled", and
//  the control that would move them was a dropdown in another panel far down
//  the page. The owner looked where the decision was made, found nothing, and
//  logged it as missing.
//
//  OFFERED, NOT AUTOMATIC — and that is the design, not a shortcut. A failed L1
//  does not always mean rejected: the recruiter may want an L2 with a different
//  panel, or a hold pending the client's view. Moving them automatically would
//  take a lifecycle decision on their behalf and be wrong often enough to
//  matter. The screen states the consequence and offers it in one click.
// ---------------------------------------------------------------------------
t_section('A16 / A8 — the interview outcome offers the move');

$iv = (string) file_get_contents(__DIR__ . '/../lib/recruit_iv.php');

t_ok(strpos($iv, '$ivMove') !== false, 'the interview card works out what the outcome implies');
t_ok(preg_match("/in_array\(\\\$st, \['FAIL', 'NO_SHOW'\], true\)[^;]*'REJECTED'/s", $iv) === 1,
    '*** a failed or missed interview offers Rejected');
t_ok(strpos($iv, "\$st === 'HOLD'") !== false && strpos($iv, "'HOLD', 'Put this candidate on hold'") !== false,
    'a hold offers On hold');
t_ok(strpos($iv, "\$st === 'PASS'") !== false && strpos($iv, "'OFFERED', 'Move to offer'") !== false,
    'and a pass offers the step forward, not only the step out');

//  ONE STAGE-CHANGE PATH. The "duplicacy ... must be unified" half of the ask:
//  this posts to the same endpoint the "Move this candidate" panel uses, so
//  there is one set of guards and one audit trail.
t_ok(strpos($iv, 'action="/candidate-stage?id=') !== false,
    '*** and it reuses the one stage-change endpoint rather than adding a second');
$cd = (string) file_get_contents(__DIR__ . '/../views/ops/candidate_detail.php');
t_ok(strpos($cd, 'action="/candidate-stage?id=') !== false,
    'ARMING · which is the same one the Move panel posts to');

//  NOT AUTOMATIC. If this ever starts writing the stage itself, this fails.
t_ok(!preg_match('/iv_record\s*\([^)]*\)\s*;[^}]*UPDATE candidates SET stage/s', $iv),
    '*** recording an outcome still does not change the stage by itself');
t_ok(strpos($iv, 'or leave them where they are and decide later') !== false,
    '*** and the screen says out loud that doing nothing is allowed');

//  IT MUST NOT OFFER A MOVE THAT MAKES NO SENSE. Somebody already hired,
//  withdrawn or rejected is not moved again from an old interview card.
t_ok(strpos($iv, "!in_array(\$ivStageNow, ['ACCEPTED', 'WITHDRAWN', 'REJECTED'], true)") !== false,
    '*** no prompt for a candidate already hired, withdrawn or rejected');
//  And only somebody who may move a candidate is offered the button.
t_ok(preg_match('/\$ivMove = null;\s*\$ivStageNow[^;]*;\s*if \(\$can /s', $iv) === 1,
    '*** and only a coordinator-level user sees it, same as the Move panel');

//  THE REASON IS CARRIED, so the stage history says why — the round, the
//  outcome and the recommendation, not a bare "Rejected".
t_ok(strpos($iv, 'name="remark"') !== false, 'the move carries a remark');
t_ok(strpos($iv, "IV_RESULTS[\$st] ?? \$st") !== false,
    '*** and the remark names the round and its outcome, so the history reads back');
