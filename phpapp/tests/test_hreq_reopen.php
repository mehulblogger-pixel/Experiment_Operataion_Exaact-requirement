<?php
// ---------------------------------------------------------------------------
//  UAT A1 — A REJECTED HIRING REQUEST COULD NOT BE CORRECTED.
//
//    "Once Hiring Request is Rejected, it cannot be edited. Suppose there is
//     mistake in period of something like that then it will be a problem or if
//     there is any typo mistake."
//
//  REJECTED was terminal, so a request rejected because somebody typed 6 months
//  instead of 16 could only be abandoned and retyped from scratch — losing the
//  discussion, the approval history and the record of why the company hired.
//
//  The transition REJECTED -> DRAFT is recorded in docs/03-object-lifecycles.md
//  in the same commit as the code. These assertions are the three properties
//  that make it safe to have.
// ---------------------------------------------------------------------------
t_section('A1 — reopening a rejected hiring request');
t_as_admin();
hreq_migrate();

$mk = function ($status) {
    db()->prepare("INSERT INTO hiring_requests (req_no,status,designation,job_title,quantity,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute(['HRQ-T-' . substr(md5(uniqid('', true)), 0, 6), $status, 'Inspector', 'Inspector', 1, date('c'), date('c')]);
    return (int) db()->lastInsertId();
};

t_ok(function_exists('hreq_reopen'), 'the reopen action exists');

// ---- a reason is not optional --------------------------------------------
$id = $mk('REJECTED');
[$ok, $msg] = hreq_reopen($id, '');
t_ok(!$ok, '*** reopening without saying what is being corrected is refused');
t_eq(strtoupper((string) hreq_get($id)['status']), 'REJECTED', '*** and nothing moved');

// ---- with a reason, it returns to DRAFT — never to APPROVED ---------------
[$ok2, $msg2] = hreq_reopen($id, 'duration was typed as 6 months, should be 16');
t_ok($ok2, 'with a reason, it reopens: ' . $msg2);
t_eq(strtoupper((string) hreq_get($id)['status']), 'DRAFT',
    '*** it returns to DRAFT, so it must be approved again on its own merits');

//  THE ASSERTION THAT MATTERS MOST. If this ever passes, somebody has built a
//  way to un-reject a request without an approver seeing it again.
$src = (string) file_get_contents(__DIR__ . '/../lib/hiringreq.php');
//  BOUND THE SEARCH TO THE FUNCTION. Asking "/function hreq_reopen.*?X/s" of
//  the whole file finds an X hundreds of lines later and reports it as this
//  function's — which is how the first draft of these assertions "found" a
//  DELETE that belongs to another helper entirely. Everything below is asked of
//  the body alone.
$fnStart = strpos($src, 'function hreq_reopen(');
$fnEnd   = strpos($src, "\nfunction ", $fnStart + 10);
$reopen  = substr($src, $fnStart, ($fnEnd === false ? strlen($src) : $fnEnd) - $fnStart);
t_ok(strlen($reopen) > 400 && strlen($reopen) < 4000,
     'ARMING · the reopen function body was isolated (' . strlen($reopen) . ' bytes)');
t_ok(strpos($reopen, "'APPROVED'") === false || !preg_match("/status\s*=\s*'APPROVED'/", $reopen),
    '*** there is no path from REJECTED straight to APPROVED');
t_ok(strpos($reopen, "SET status='DRAFT'") !== false, 'the only status it writes is DRAFT');
t_eq(preg_match_all("/SET status='[A-Z]+'/", $reopen), 1, '*** and it writes exactly one status, once');

// ---- only what was already allowed ----------------------------------------
t_ok(preg_match('/^function hreq_reopen[^{]*\{\s*if \(!hreq_can_create\(\)\)/s', $reopen) === 1,
    '*** it needs the same right as raising a request — nothing new is granted');
t_ok(strpos($reopen, 'hreq_in_scope($r)') !== false,
    '*** and obeys the same office scope');

// ---- only a REJECTED request ----------------------------------------------
foreach (['DRAFT', 'SUBMITTED', 'APPROVED', 'CANCELLED'] as $st) {
    [$o] = hreq_reopen($mk($st), 'a reason');
    t_ok(!$o, "*** a $st request cannot be reopened — this is not a general unlock");
}

// ---- the rejection survives ------------------------------------------------
t_ok(strpos($reopen, "'Reopened for correction after rejection'") !== false,
    'the reopening is written to the history');
t_ok(stripos($reopen, 'DELETE FROM') === false,
    '*** and nothing in the history is deleted — the rejection stays readable');
//  Any chain left open on the rejected round is closed, so it cannot be decided
//  after the request has moved on.
t_ok(strpos($reopen, 'appr_cancel_open') !== false,
    '*** an approval left open from the rejected round is closed');

// ---- the lifecycle document was updated in the same change ----------------
$doc = (string) file_get_contents(__DIR__ . '/../../docs/03-object-lifecycles.md');
t_ok(strpos($doc, 'reopen for correction') !== false,
    '*** docs/03-object-lifecycles.md documents the transition — CLAUDE.md requires it');
t_ok(strpos($doc, '`REJECTED` → `DRAFT`') !== false, 'and names it exactly');
t_as_nobody();
