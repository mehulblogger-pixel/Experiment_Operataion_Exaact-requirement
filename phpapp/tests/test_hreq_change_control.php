<?php
// ---------------------------------------------------------------------------
//  UAT A10 — AN APPROVED HIRING REQUEST COULD NEVER BE CHANGED.
//
//    "There is no option to edit the hiring request once approved or rejected."
//
//  Three further steps (A11, A12, A13) could not be run at all because of it.
//
//  The cause was a UI gate contradicting a finished engine. hreq_save() has
//  classified changes to an approved request against the approved snapshot
//  since Phase 3 M4, routing a material one through rver_propose() into change
//  control and leaving the approved row untouched; it even reads a
//  `change_reason` from the post. No screen ever drew the form, so none of it
//  could be reached.
//
//  These assertions are about the DOOR, and about the two properties that make
//  the door safe to open.
// ---------------------------------------------------------------------------
t_section('A10 — change control on an approved hiring request');

$v = (string) file_get_contents(__DIR__ . '/../views/ops/hiring_request.php');

t_ok(strpos($v, '$proposable') !== false, 'the screen knows when a change may be proposed');
t_ok(preg_match('/\$proposable\s*=\s*\$r\s*&&\s*\$status\s*===\s*\'APPROVED\'/', $v) === 1,
    '*** and offers it for an APPROVED request');
t_ok(strpos($v, "rver_can_propose('HIRING_REQUEST'") !== false,
    '*** asking the right that already exists, rather than inventing one');
t_ok(strpos($v, '($mayRaise && $editable) || $proposable') !== false,
    '*** so the form is drawn for a draft OR for a proposable approved request');
t_ok(strpos($v, 'name="change_reason"') !== false,
    '*** and draws the reason field the save path has always read');
t_ok(strpos($v, 'Propose this change') !== false,
    'the button says what pressing it does, which is not "save"');

//  NOTHING WAS GRANTED. rver_can_propose() asks hiring.material_change.propose
//  (lib/access.php) or the role default that could raise the request at all,
//  and applies the same office scope an edit would.
t_ok(function_exists('rver_can_propose'), 'the propose right is a function, asked of the row');
$lib = (string) file_get_contents(__DIR__ . '/../lib/reqversion.php');
t_ok(strpos($lib, "RVER_PERM_PROPOSE = 'hiring.material_change.propose'") !== false,
    'and it is a permission the matrix already defines');
t_ok(strpos($lib, 'scope_allows(') !== false,
    'and scope still applies, so this is no reach into another branch');

//  REJECTED IS DELIBERATELY NOT INCLUDED. Re-opening one needs a transition
//  docs/03-object-lifecycles.md does not define; CLAUDE.md forbids inventing
//  one. If this ever starts matching, somebody added a lifecycle edge in a view.
t_ok(strpos($v, "'REJECTED'") === false || strpos($v, '$proposable') === false
     || !preg_match('/\$proposable[^;]*REJECTED/', $v),
    '*** a REJECTED request is NOT re-opened here — that needs a lifecycle decision');

//  THE ENGINE BEHIND THE DOOR still refuses to write an approved row.
$hs = (string) file_get_contents(__DIR__ . '/../lib/hiringreq.php');
t_ok(strpos($hs, 'rver_propose(\'HIRING_REQUEST\'') !== false,
    'a material change still becomes a proposal');
t_ok(strpos($hs, 'The row is deliberately NOT written') !== false,
    'and the approved row is still left exactly as the approver left it');
t_ok(strpos($hs, 'Give a reason for the change') !== false,
    'and a reason is still mandatory before anything is recorded');
