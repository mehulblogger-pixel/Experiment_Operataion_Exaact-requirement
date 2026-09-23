<?php
// ============================================================================
//  F-A7-1 — THE THIRD DOOR ASKS THE SAME QUESTION AS THE OTHER TWO.
//
//  Three paths create a workforce record: hiring a candidate (rcv_convert),
//  the user form / org import (team_member_create), and Masters -> Add a
//  person. The first two have asked "is this person already on the team?"
//  since RB-3. The third wrote its own INSERT and never did.
//
//  The DATA was never at risk — the e-mail key refused the second row. What
//  was at risk was the person: the refusal arrived as an uncaught
//  PDOException, so somebody adding a colleague was shown
//
//      SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
//
//  reproduced in Chromium during the UX audit. This battery pins BOTH halves:
//  that the guard is now asked BEFORE the insert, and that the key violation
//  can never again reach a user as a database error.
//
//  ARMING FIRST, as ever. A test asserting "the duplicate was refused" proves
//  nothing if the first person was never created — then everything is refused
//  and the assertion passes for the wrong reason.
// ============================================================================

t_as_admin();
if (function_exists('emp_code_migrate')) emp_code_migrate();
if (function_exists('email_key_migrate')) email_key_migrate();

$faEmail = 'fa7.door.' . substr(md5((string) mt_rand()), 0, 6) . '@example.com';
$faName  = 'Arun Verma FA7';

// ---------------------------------------------------------------------------
t_section('FA7-A · arming — the guard engine and the first person both exist');
// ---------------------------------------------------------------------------
t_ok(function_exists('workforce_direct_matches'),
     'A1 ARMING — the shared duplicate engine the other two doors use is present');
t_ok(function_exists('email_key_is_taken'),
     'A2 ARMING — the key-violation recogniser is present (this is what stops the SQLSTATE)');

$faFirst = team_member_create($faName, 'FIELD', null, $faEmail);
t_ok($faFirst > 0, 'A3 ARMING — a first team member really was created (id ' . (int)$faFirst . ')');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE email=?", [$faEmail]), 1,
     'A4 ARMING — exactly one row carries that e-mail, so a second add is genuinely a duplicate');

// ---------------------------------------------------------------------------
t_section('FA7-B · the three doors now agree');
// ---------------------------------------------------------------------------
//  Door B, asked directly: refuses and explains.
$faSecond = team_member_create($faName, 'FIELD', null, $faEmail);
t_eq($faSecond, 0, 'B1 — door B (team_member_create) refuses the second add');
$faWhy = function_exists('team_member_last_refusal') ? team_member_last_refusal() : '';
t_ok(strpos($faWhy, 'already be on your team') !== false,
     'B2 — and says so in words: "' . substr($faWhy, 0, 58) . '…"');
t_ok(strpos($faWhy, $faName) !== false, 'B3 — naming the person they may already be');

//  Door C — the Masters form — now consults the SAME engine before inserting.
//  Asked with the same details, it must see the same match.
$faHit = workforce_direct_matches($faName, $faEmail, '');
t_ok(!empty($faHit), 'B4 — door C\'s pre-check sees the same match the other doors see');
$faNames = array_map(fn($h) => (string)($h['name'] ?? ''), $faHit);
t_ok(in_array($faName, $faNames, true), 'B5 — and can name them, which is what the screen shows');

// ---------------------------------------------------------------------------
t_section('FA7-C · a key violation is an ANSWER, never a SQLSTATE');
// ---------------------------------------------------------------------------
//  The pre-check cannot see a row inserted a microsecond ago by somebody else.
//  The key is the last line of defence, and its refusal must be recognised
//  rather than thrown. This constructs the real violation and proves the
//  recogniser fires on it.
$faThrew = null;
try {
    db()->prepare("INSERT INTO inspectors (name,email,emp_code,status,dup_ack,created_at) VALUES (?,?,?,?,0,?)")
        ->execute([$faName . ' Race', $faEmail, 'FA7-RACE-' . substr(md5((string)mt_rand()), 0, 5), 'ACTIVE', date('c')]);
} catch (Throwable $e) { $faThrew = $e; }

t_ok($faThrew !== null, 'C1 ARMING — the e-mail key really does refuse a second ACTIVE row');
t_ok($faThrew !== null && email_key_is_taken($faThrew),
     'C2 — and the recogniser identifies it, so the route can answer instead of crashing');
t_ok($faThrew !== null && stripos((string)$faThrew->getMessage(), 'SQLSTATE') !== false,
     'C3 ARMING — the raw message IS the SQLSTATE a user used to be shown');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE email=?", [$faEmail]), 1,
     'C4 — and the data stayed clean throughout: still exactly one row');

// A throwable that is NOT a key violation must NOT be swallowed as a duplicate,
// or a real fault would be reported to the user as "already on your team".
t_ok(!email_key_is_taken(new RuntimeException('disk full')),
     'C5 — an unrelated failure is not mistaken for a duplicate');

// ---------------------------------------------------------------------------
t_section('FA7-D · acknowledging is not merging');
// ---------------------------------------------------------------------------
//  The owner's decision, unchanged: a tick records that a human looked and
//  chose to proceed. The second record is MARKED, so the key admits it
//  deliberately — and two separate records remain. Nothing is merged.
$faAck = team_member_create($faName, 'FIELD', null, $faEmail, ['dup_ack' => 1]);
t_ok($faAck > 0, 'D1 — an acknowledged second engagement is allowed through');
t_ok($faAck !== $faFirst, 'D2 — as a SECOND record, not by editing the first');
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE email=?", [$faEmail]), 2,
     'D3 — two records now exist: acknowledging is not merging');
t_eq((int) ops_val("SELECT dup_ack FROM inspectors WHERE id=?", [$faAck]), 1,
     'D4 — and the second is marked, so an operator can see somebody decided this');

// ---------------------------------------------------------------------------
t_section('FA7-E · the route itself is wired, not just the engine');
// ---------------------------------------------------------------------------
//  The engine could be perfect while the Masters route still ignored it — that
//  was precisely the defect. These read the route as shipped.
$faSrc = file_get_contents(__DIR__ . '/../lib/ops.php');
$faAdd = strpos($faSrc, 'INSERT INTO inspectors (first_name,middle_name,last_name,name,emp_code');
t_ok($faAdd !== false, 'E1 ARMING — the Masters add path was located in the source');
//  The window must reach PAST the insert as well as before it: the pre-check
//  sits above, the catch below, and the INSERT statement between them is ~700
//  characters on its own. A window that stopped at the insert would fail E3
//  while the code was correct — which is exactly what the first run did.
$faWindow = substr($faSrc, max(0, $faAdd - 2600), 5200);
t_ok(strpos($faWindow, 'workforce_direct_matches') !== false,
     'E2 — the Masters add consults the shared engine BEFORE inserting');
t_ok(strpos($faWindow, 'email_key_is_taken') !== false,
     'E3 — and recognises a key violation instead of letting it escape');
t_ok(strpos($faWindow, 'try {') !== false,
     'E4 — the insert is wrapped, which it was not before');
t_ok(strpos($faWindow, 'dup_ack') !== false,
     'E5 — and an acknowledged add is marked, as the other doors mark it');

// The view must offer the tick, or the refusal would be a dead end.
$faView = file_get_contents(__DIR__ . '/../views/ops/inspector_form.php');
t_ok(strpos($faView, 'name="dup_ack"') !== false,
     'E6 — the form offers the acknowledgement tick, so a real second engagement is not blocked');
t_ok(strpos($faView, '$isEdit') !== false,
     'E7 — and "edit" keys off the row id, so a refused add re-posts to /new, not /edit?id=0');

//  E8 — THE HALF OF E7 THAT WAS NEARLY MISSED.
//
//  Switching the title, the breadcrumb and the form action to $isEdit is not
//  enough. The page also carries panels that only make sense for a row that
//  exists — the signature pad, the certificate register, the Super-Admin
//  allowances form, the "Documents & KYC" button — and each of those was still
//  keyed off "$ins is truthy". On a refused add $ins IS truthy (it holds what
//  the person typed), so every one of them would have rendered against id 0:
//  three forms posting to /m/inspectors/edit?id=0, and a KYC link to
//  /identity?i=0. Worse in the other direction, the "First certificate"
//  fieldset — which belongs to ADDING — would have disappeared from a page
//  that is still an add.
//
//  So: no record-scoped test may key off $ins truthiness at all. $ins is for
//  READING VALUES BACK; $isEdit is for deciding what the page is.
$faCond = [];
if (preg_match_all('#<\?php\s+if\s*\(\s*!?\s*\$ins\s*(?:\)|&&)#', $faView, $mCond))
    $faCond = $mCond[0];
t_eq($faCond, [], 'E8 — no panel on the form decides what it is from $ins being truthy');
//  Prove the scan would catch one, so a pass means something.
t_ok(preg_match('#<\?php\s+if\s*\(\s*!?\s*\$ins\s*(?:\)|&&)#', '<?php if ($ins && is_master()): ?>') === 1,
     'E8 ARMING — the scan does detect the shape it forbids');
//  And the two legitimate value-reads are still there, so E8 did not pass by
//  the page having been emptied.
t_ok(substr_count($faView, '($ins && !empty($ins[') === 2,
     'E8 ARMING — the two value-carry-back reads remain, so the page was not gutted');
t_ok(strpos($faView, 'First certificate') !== false,
     'E9 — the first-certificate fieldset still exists for the add path');

db()->prepare("DELETE FROM inspectors WHERE email=?")->execute([$faEmail]);
t_as_nobody();
