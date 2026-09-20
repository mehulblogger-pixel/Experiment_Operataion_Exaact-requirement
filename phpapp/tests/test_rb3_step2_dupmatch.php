<?php
// ============================================================================
//  RB-3 · STEP 2 — duplicate-staff match + explicit acknowledgement
//
//  Owner decision 2 (2026-09-20): "Do not refuse automatically. Do not silently
//  continue. Show the match and require the recruiter to explicitly acknowledge
//  it. The tick is an acknowledgement, not an automatic merge."
//
//  Rules: docs/phase7/RB3-STEP2-DUPLICATE-MATCH-RULES.md
//  Map:   docs/phase7/RB3-STEP2-IMPLEMENTATION-MAP.md
//
//  Every probe asserts its trap is ARMED before it asserts a result. A green
//  test that could pass without exercising the target logic is not acceptable
//  (the Step 1 lesson, carried forward).
// ============================================================================
if (empty($GLOBALS['__test_db'])) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }

t_as_admin();
emp_code_migrate();

$s2root = dirname(__DIR__);
$s2env = function () {
    $e = '';
    foreach (['DB_DRIVER', 'SQLITE_PATH', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
        $v = getenv($k); if ($v !== false && $v !== '') $e .= $k . '=' . escapeshellarg($v) . ' ';
    }
    return $e;
};
$s2one = function ($op, $cid = 0, $tok = '', $uid = 0) use ($s2root, $s2env) {
    $cmd = $s2env() . 'php ' . escapeshellarg($s2root . '/tests/_rb3s2_worker.php') . ' '
         . escapeshellarg($op) . ' ' . (int)$cid . ' ' . escapeshellarg((string)$tok) . ' 0 ' . (int)$uid . ' 2>&1';
    foreach (explode("\n", trim((string)shell_exec($cmd))) as $l) { $j = json_decode(trim($l), true); if (is_array($j)) return $j; }
    return ['ok' => false, 'code' => 'NO_OUTPUT'];
};
$s2race = function (array $specs, $leadMs = 1100) use ($s2root, $s2env) {
    $target = round(microtime(true) * 1000) + $leadMs; $procs = [];
    foreach ($specs as $s) {
        $cmd = $s2env() . 'php ' . escapeshellarg($s2root . '/tests/_rb3s2_worker.php') . ' '
             . escapeshellarg($s[0]) . ' ' . (int)($s[1] ?? 0) . ' ' . escapeshellarg((string)($s[2] ?? '')) . ' '
             . escapeshellarg((string)$target) . ' ' . (int)($s[3] ?? 0) . ' 2>&1';
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$p, $pipes];
    }
    $out = [];
    foreach ($procs as [$p, $pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]); proc_close($p);
        foreach (explode("\n", trim((string)$raw)) as $l) { $j = json_decode(trim($l), true); if (is_array($j)) { $out[] = $j; break; } }
    }
    return $out;
};

// ---- the cast ---------------------------------------------------------------
$s2off = (int)ops_val("SELECT id FROM offices ORDER BY id LIMIT 1");
$s2me  = (int)(current_user()['id'] ?? 0);
db()->prepare("UPDATE users SET home_office_id=? WHERE id=?")->execute([$s2off, $s2me]);

$mkStaff = function ($first, $last, $mobile = '', $email = '', $status = 'ACTIVE') {
    db()->prepare("INSERT INTO inspectors (name,first_name,last_name,mobile,email,status,home_office_id,created_at)
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute([trim("$first $last"), $first, $last, $mobile, $email, $status, null, date('c')]);
    return (int)db()->lastInsertId();
};
$mkCand = function ($first, $last, $mobile = '', $email = '') {
    db()->prepare("INSERT INTO candidates (first_name,last_name,mobile,email,stage,sbu,created_at)
                   VALUES (?,?,?,?, 'ACCEPTED','IND',?)")
        ->execute([$first, $last, $mobile, $email, date('c')]);
    return (int)db()->lastInsertId();
};
$row     = fn($id) => ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
$strongN = fn($id) => count(workforce_strong_matches(workforce_matches($row($id))));
$weakN   = fn($id) => count(workforce_matches($row($id))) - count(workforce_strong_matches(workforce_matches($row($id))));
$linked  = fn($id) => (int)ops_val("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [$id]);
$staffN  = fn() => (int)ops_val("SELECT COUNT(*) FROM inspectors");
$tokenFor = function ($cid) use ($row, $s2me) { $c = $row($cid); return workforce_ack_issue($cid, workforce_matches($c), $c, $s2me); };

// ---------------------------------------------------------------------------
t_section('RB3S2 · X1 — nobody like this on the team');
// ---------------------------------------------------------------------------
$x1 = $mkCand('Unique', 'Newcomer', '9000000101', 'unique.newcomer@s2.test');
t_eq($strongN($x1), 0, 'X1a · no strong match — the probe is starting from "nobody like this"');
t_eq(workforce_ack_issue($x1, workforce_matches($row($x1)), $row($x1), $s2me), '',
     'X1b · no acknowledgement is even offered, so there is no tick to render');
$r1 = rcv_convert($x1, ['actor_id' => $s2me]);
t_ok(!empty($r1['ok']), 'X1 · the hire goes through untouched (' . ($r1['code'] ?? '?') . ')');

// ---------------------------------------------------------------------------
t_section('RB3S2 · X2 / X3 / X4 — the same mobile number');
// ---------------------------------------------------------------------------
$sMob = $mkStaff('Rajesh', 'Patel', '9820011111', 'rajesh.patel@s2.test');
$x3   = $mkCand('Rajesh', 'Patel', '9820011111', 'different.address@s2.test');
t_ok($sMob > 0, 'X2a · a team member exists with that mobile — the trap is armed');
$m3 = workforce_matches($row($x3));
t_eq(count(workforce_strong_matches($m3)), 1, 'X2 · the applicant raises exactly one STRONG match');
t_eq((string)workforce_strong_matches($m3)[0]['basis'], 'mobile', 'X2b · …and it says why: the mobile number');
t_ok(strpos((string)workforce_strong_matches($m3)[0]['mobile'], 'XXXX') === 0,
     'X2c · the number is MASKED on the way to the screen (' . workforce_strong_matches($m3)[0]['mobile'] . ')');

$before = $staffN();
$r3 = rcv_convert($x3, ['actor_id' => $s2me]);                      // no tick at all
t_eq((string)($r3['code'] ?? ''), 'WORKFORCE_MATCH', 'X3 · without the tick the hire is REFUSED');
t_eq($linked($x3), 0, 'X3b · …and the application was not converted');
t_eq($staffN(), $before, 'X3c · …and NOTHING was written — no half-made team member');

$tok3 = $tokenFor($x3);
t_ok($tok3 !== '', 'X4a · a tick is offered for this application — the probe has something to submit');
$r4 = rcv_convert($x3, ['actor_id' => $s2me, 'dup_ack' => $tok3]);
t_ok(!empty($r4['ok']), 'X4 · with the tick the recruiter may proceed (' . ($r4['code'] ?? '?') . ')');
t_ok($linked($x3) > 0, 'X4b · …and the team member really was created');

// ---------------------------------------------------------------------------
t_section('RB3S2 · X5 / X6 / X7 — a name is not an identifier');
// ---------------------------------------------------------------------------
$sName = $mkStaff('Suresh', 'Kumar', '9820022222', 'suresh.kumar@s2.test');
$x5 = $mkCand('Suresh', 'Kumar', '', '');                            // same name, no contact at all
t_ok($weakN($x5) >= 1, 'X5a · the name really does match somebody — the trap is armed');
t_eq($strongN($x5), 0, 'X5b · …but a shared NAME is never strong');
$r5 = rcv_convert($x5, ['actor_id' => $s2me]);
t_ok(!empty($r5['ok']), 'X5 · Suresh Kumar does not block Suresh Kumar (' . ($r5['code'] ?? '?') . ')');

$x6 = $mkCand('Suresh', 'Kumar', '9999900006', 'other.suresh@s2.test'); // same name, DIFFERENT mobile
t_ok($weakN($x6) >= 1, 'X6a · the name matches — the trap is armed');
t_eq($strongN($x6), 0, 'X6b · a different mobile keeps it weak');
t_ok(!empty(rcv_convert($x6, ['actor_id' => $s2me])['ok']), 'X6 · same name + different mobile proceeds');

$x7 = $mkCand('Completely', 'Different', '9820022222', '');            // same mobile, DIFFERENT name
t_eq($strongN($x7), 1, 'X7a · a shared mobile is strong even when the names differ — the trap is armed');
t_eq((string)rcv_convert($x7, ['actor_id' => $s2me])['code'], 'WORKFORCE_MATCH',
     'X7 · same mobile + different name is REFUSED until acknowledged');

// ---------------------------------------------------------------------------
t_section('RB3S2 · X8 / X9 / X10 / X11 — the tick cannot be moved, forged or aged');
// ---------------------------------------------------------------------------
//  ISOLATING THE CANDIDATE BINDING, which is harder than it looks.
//
//  The first version of this probe gave A and B different e-mail addresses and
//  converted A BEFORE trying A's tick on B. Both mistakes hid the thing it was
//  meant to prove: converting A created a second team member on the same mobile,
//  so B's duplicate picture had CHANGED by then, and the refusal came from the
//  evidence binding rather than the candidate binding. Mutant S4 — which removes
//  the candidate binding entirely — survived it untouched.
//
//  So A and B are given IDENTICAL contact details, A's tick is tried on B while
//  NOTHING has changed, and the probe asserts the two evidence fingerprints are
//  the same before drawing any conclusion. Then the only difference left between
//  them is which application it is.
$sShare = $mkStaff('Meena', 'Iyer', '9820033333', 'meena.shared@s2.test');
$x8a = $mkCand('Meena', 'Iyer', '9820033333', 'meena.shared@s2.test');
$x8b = $mkCand('Meena', 'Twin', '9820033333', 'meena.shared@s2.test');
t_eq($strongN($x8a), 1, 'X8a · application A raises a strong match — armed');
t_eq($strongN($x8b), 1, 'X8b · so does application B — armed');
t_eq(workforce_ack_evidence(workforce_matches($row($x8a)), $row($x8a)),
     workforce_ack_evidence(workforce_matches($row($x8b)), $row($x8b)),
     'X8c · A and B have the SAME evidence fingerprint — so nothing but the application itself can tell them apart');
$tokA = $tokenFor($x8a);
t_ok($tokA !== '', 'X8d · A has a genuine tick');
//  Tried on B FIRST, before anything about either of them changes.
t_eq((string)rcv_convert($x8b, ['actor_id' => $s2me, 'dup_ack' => $tokA])['code'], 'WORKFORCE_MATCH',
     'X8 · A\'s tick does NOT authorise B — the application is inside the signature');
t_eq($linked($x8b), 0, 'X8e · …and B was not converted');
t_ok(!empty(rcv_convert($x8a, ['actor_id' => $s2me, 'dup_ack' => $tokA])['ok']),
     'X8f · …and that very same tick DOES work on A, so the refusal was about the application and not a dud tick');

$x9 = $mkCand('Nikhil', 'Rao', '9820044444', '');
$s9 = $mkStaff('Nikhil', 'Rao', '9820044444', '');
t_eq($strongN($x9), 1, 'X9a · a strong match exists — armed');
$tok9 = $tokenFor($x9);
t_ok(workforce_ack_ok($tok9, $x9, workforce_matches($row($x9)), $row($x9), $s2me),
     'X9b · the tick is valid right now — so a later refusal is about the CHANGE, not about the tick');
//  The evidence changes: a SECOND team member now shares the number too.
$s9b = $mkStaff('Nikhil', 'Rao Junior', '9820044444', '');
t_eq($strongN($x9), 2, 'X9c · the duplicate picture really did change — two strong matches now');
t_ok(!workforce_ack_ok($tok9, $x9, workforce_matches($row($x9)), $row($x9), $s2me),
     'X9 · the earlier tick no longer answers the new question');
t_eq((string)rcv_convert($x9, ['actor_id' => $s2me, 'dup_ack' => $tok9])['code'], 'WORKFORCE_MATCH',
     'X9d · …and the hire is refused with the stale tick');

$x10 = $mkCand('Forge', 'Attempt', '9820033333', '');
//  >= 1, not == 1: X8 legitimately left a SECOND team member on this number when
//  its acknowledged hire went through. What this probe needs is that a tick is
//  required at all, not how many people triggered it.
t_ok($strongN($x10) >= 1, 'X10a · at least one strong match exists (' . $strongN($x10) . ') — armed');
foreach (['1', 'yes', 'on', 'true', '0.' . str_repeat('a', 64), str_repeat('f', 64),
          time() . '.' . str_repeat('f', 64), 'x.y', ''] as $bad) {
    if (workforce_ack_ok($bad, $x10, workforce_matches($row($x10)), $row($x10), $s2me)) {
        t_ok(false, 'X10 · a forged tick was ACCEPTED: "' . $bad . '"'); break;
    }
}
t_ok(!workforce_ack_ok('1', $x10, workforce_matches($row($x10)), $row($x10), $s2me),
     'X10 · a hand-made tick ("1", the shape the old hidden field used) is rejected');
t_eq((string)rcv_convert($x10, ['actor_id' => $s2me, 'dup_ack' => '1'])['code'], 'WORKFORCE_MATCH',
     'X10b · …and posting it directly to the action changes nothing');

//  A second real login. The throwaway database has only the administrator, so
//  "another user" has to be created before it can mean anything.
$other = (int)ops_val("SELECT id FROM users WHERE id<>? ORDER BY id LIMIT 1", [$s2me]);
if (!$other) {
    db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,role,is_active,home_office_id)
                   VALUES ('rb3s2.other',?, 'Other','Recruiter','COORDINATOR',1,?)")
        ->execute([password_hash('x', PASSWORD_DEFAULT), $s2off]);
    $other = (int)db()->lastInsertId();
}
t_ok($other > 0 && $other !== $s2me, 'X11a · there is a SECOND, different login to test against — armed');
$tok11 = workforce_ack_issue($x10, workforce_matches($row($x10)), $row($x10), $other);
t_ok($tok11 !== '', 'X11b · that other user can be issued a tick of their own');
t_ok(!workforce_ack_ok($tok11, $x10, workforce_matches($row($x10)), $row($x10), $s2me),
     'X11 · one person\'s tick does not speak for another');

//  An expired tick, forged CORRECTLY with the real key, so only the age is wrong.
$sec = workforce_ack_secret();
$old = time() - WORKFORCE_ACK_TTL - 60;
$expired = $old . '.' . hash_hmac('sha256',
    implode('|', [$x10, $s2me, workforce_ack_evidence(workforce_matches($row($x10)), $row($x10)), $old]), $sec);
t_ok($sec !== '', 'X-stale-a · the signing key exists, so this tick is genuinely well-formed — only its age is wrong');
t_ok(!workforce_ack_ok($expired, $x10, workforce_matches($row($x10)), $row($x10), $s2me),
     'X-stale · a correctly signed but EXPIRED tick is rejected');
$future = (time() + 3600) . '.' . hash_hmac('sha256',
    implode('|', [$x10, $s2me, workforce_ack_evidence(workforce_matches($row($x10)), $row($x10)), time() + 3600]), $sec);
t_ok(!workforce_ack_ok($future, $x10, workforce_matches($row($x10)), $row($x10), $s2me),
     'X-future · a tick dated in the future is rejected too');

// ---------------------------------------------------------------------------
t_section('RB3S2 · X15 / X16 — leavers, and the genuine re-hire');
// ---------------------------------------------------------------------------
$sGone = $mkStaff('Departed', 'Person', '9820055555', 'departed@s2.test', 'INACTIVE');
$x15 = $mkCand('Departed', 'Person', '9820055555', 'departed@s2.test');
t_eq((string)ops_val("SELECT status FROM inspectors WHERE id=?", [$sGone]), 'INACTIVE',
     'X15a · that person really has left — the probe has the state it is about');
t_eq($strongN($x15), 0, 'X15b · a leaver is history, not a duplicate');
t_ok(!empty(rcv_convert($x15, ['actor_id' => $s2me])['ok']), 'X15 · re-hiring a leaver is not obstructed');

$sHere = $mkStaff('Rehire', 'Candidate', '9820066666', 'rehire@s2.test');
$x16 = $mkCand('Rehire', 'Candidate', '9820066666', 'rehire@s2.test');
t_eq($strongN($x16), 1, 'X16a · a LIVE team member shares the details — armed');
t_eq((string)rcv_convert($x16, ['actor_id' => $s2me])['code'], 'WORKFORCE_MATCH', 'X16b · refused without the tick');
$r16 = rcv_convert($x16, ['actor_id' => $s2me, 'dup_ack' => $tokenFor($x16)]);
t_ok(!empty($r16['ok']), 'X16 · a legitimate second engagement proceeds once a person says so');
t_ok((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE mobile='9820066666'") === 2,
     'X16c · …and it really is a SECOND record — nothing was merged');

// ---------------------------------------------------------------------------
t_section('RB3S2 · X17 — the audit says what was shown, not merely that a box was ticked');
// ---------------------------------------------------------------------------
$aud = fn($cid, $kind) => ops_all("SELECT subject FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind=? ORDER BY id DESC", [$cid, $kind]) ?: [];
$refA = $aud($x16, 'IDENTITY_REFUSED');
t_ok(count($refA) >= 1, 'X17a · the refusal was recorded');
t_ok(strpos((string)$refA[0]['subject'], '#' . $sHere) !== false,
     'X17b · …naming WHICH team member triggered it [' . substr((string)$refA[0]['subject'], 0, 90) . ']');
$okA = $aud($x16, 'IDENTITY_LINKED');
t_ok(count($okA) >= 1, 'X17c · the acknowledged hire was recorded');
t_ok(strpos((string)$okA[0]['subject'], 'reviewed and confirmed') !== false
  && strpos((string)$okA[0]['subject'], '#' . $sHere) !== false,
     'X17 · …and it names what the recruiter confirmed, not just that they confirmed');

// ---------------------------------------------------------------------------
t_section('RB3S2 · X18 / X19 — authority and CSRF');
// ---------------------------------------------------------------------------
$sX18 = $mkStaff('Gate', 'Test', '9820077777', '');
$x18 = $mkCand('Gate', 'Test', '9820077777', '');
t_eq($strongN($x18), 1, 'X18a · a strong match exists — armed');
$tok18 = $tokenFor($x18);
//  t_as_nobody() restores whatever session existed BEFORE t_as_admin() — which,
//  in a full-suite run, is an earlier file's authorised session. Used on its own
//  it left this probe authorised and X18 passed while proving nothing: it was
//  green in isolation and only failed in the whole suite. So the unprivileged
//  actor is built explicitly here, and the probe REFUSES TO PROCEED until it has
//  established that the actor genuinely lacks the right.
$noRight = (int)ops_val("SELECT id FROM users WHERE username=?", ['rb3s2.norights']);
if (!$noRight) {
    db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,role,is_superuser,is_active,permissions)
                   VALUES ('rb3s2.norights',?, 'No','Rights','INSPECTOR',0,1,'')")
        ->execute([password_hash('x', PASSWORD_DEFAULT)]);
    $noRight = (int)db()->lastInsertId();
}
$_SESSION['uid'] = $noRight; current_user(true); ua(true);
t_ok(!is_coordinator_level(),
     'X18c · the actor genuinely has no right to convert (role ' . user_role() . ') — the trap is ARMED');
t_eq((string)rcv_convert($x18, ['dup_ack' => $tok18])['code'], 'NOT_ALLOWED',
     'X18 · somebody without the right cannot convert, tick or no tick');
$_SESSION['uid'] = $s2me; current_user(true); ua(true);
t_ok(is_coordinator_level(), 'X18d · …and the right is back, so the refusal was about authority and not a broken session');
t_eq($linked($x18), 0, 'X18b · …and nothing was created by the attempt');

t_ok(!csrf_ok('not-the-token'), 'X19a · a wrong CSRF token is rejected by the checker');
t_ok(csrf_ok(csrf_token()), 'X19b · …and the right one is accepted, so the checker is not simply always-false');
t_ok(strpos((string)@file_get_contents($s2root . '/index.php'), "if (\$method === 'POST' && !csrf_ok(") !== false,
     'X19 · every POST passes that checker centrally before any route runs');

// ---------------------------------------------------------------------------
t_section('RB3S2 · X-scope — a match you may not open is COUNTED, never NAMED');
// ---------------------------------------------------------------------------
//  A record id is never proof of authorisation (invariant I25). But a match the
//  recruiter cannot see must still STOP the hire — otherwise "I cannot open that
//  branch" would be the way round the gate.
$offA = (int)ops_val("SELECT id FROM offices ORDER BY id LIMIT 1");
$offB = (int)ops_val("SELECT id FROM offices WHERE id<>? ORDER BY id LIMIT 1", [$offA]);
if (!$offB) { db()->prepare("INSERT INTO offices (code,name) VALUES ('RB3B','RB3 Far Branch')")->execute(); $offB = (int)db()->lastInsertId(); }
db()->prepare("INSERT INTO inspectors (name,first_name,last_name,mobile,status,home_office_id,created_at)
               VALUES ('Far Branch Twin','Far','Twin','9820099999','ACTIVE',?,?)")->execute([$offB, date('c')]);
$sFar = (int)db()->lastInsertId();
$xsc  = $mkCand('Far', 'Twin', '9820099999', '');
//  A coordinator who can only see branch A.
db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,role,is_superuser,is_active,home_office_id,scope_offices,scope_sbus,permissions)
               VALUES ('rb3s2.scoped',?, 'Scoped','Recruiter','COORDINATOR',0,1,?,?, 'ALL','')")
    ->execute([password_hash('x', PASSWORD_DEFAULT), $offA, (string)$offA]);
$uScoped = (int)db()->lastInsertId();
$_SESSION['uid'] = $uScoped; current_user(true); ua(true);
t_ok(is_coordinator_level(), 'Xsc-a · the scoped user may convert at all — so a refusal below is about scope, not authority');
t_ok(!connect_identity_scope_ok('inspector', $sFar),
     'Xsc-b · …and genuinely CANNOT open that team member — the trap is ARMED');
$mSc = workforce_matches($row($xsc));
$sSc = workforce_strong_matches($mSc);
t_eq(count($sSc), 1, 'Xsc-c · the hidden team member is still FOUND — scope does not hide a duplicate');
t_ok(empty($sSc[0]['visible']), 'Xsc-d · …but is marked not-visible');
t_eq((string)$sSc[0]['name'], '', 'Xsc · …and is NOT NAMED — a record id is never proof of authorisation');
t_eq((string)$sSc[0]['emp_code'], '', 'Xsc-e · nor is their employee number leaked');
t_eq((string)rcv_convert($xsc, ['actor_id' => $uScoped])['code'], 'WORKFORCE_MATCH',
     'Xsc-f · and it STILL blocks — not being able to see it is not a way round the gate');
$_SESSION['uid'] = $s2me; current_user(true); ua(true);

// ---------------------------------------------------------------------------
t_section('RB3S2 · X14 — two recruiters at the same instant');
// ---------------------------------------------------------------------------
$sRace = $mkStaff('Race', 'Twin', '9820088888', '');
$xr = $mkCand('Race', 'Twin', '9820088888', '');
t_eq($strongN($xr), 1, 'X14a · a strong match exists — armed');
$tokR = $s2one('issue', $xr, '', $s2me);
t_ok(!empty($tokR['token']), 'X14b · a real separate process can be issued a tick');
$staffBefore = $staffN();
//  One recruiter holds the tick; three do not. All four fire at one microsecond.
$res = $s2race([['convert', $xr, (string)$tokR['token'], $s2me], ['convert', $xr, '', $s2me],
                ['convert', $xr, '', $s2me], ['convert', $xr, '', $s2me]]);
$codes = array_map(fn($r) => (string)($r['code'] ?? '?'), $res);
$won   = count(array_filter($res, fn($r) => !empty($r['ok'])));
$mtch  = count(array_filter($codes, fn($c) => $c === 'WORKFORCE_MATCH'));
$holder = (string)($codes[0] ?? '?');                       // the first spec is the one holding the tick

//  THE CLAIM, and it is the same on both engines: the tick is what separates
//  them. Whoever holds it is never refused for lack of one; whoever does not
//  hold it always is.
t_eq($mtch, 3, 'X14 · the three WITHOUT a tick were each refused WORKFORCE_MATCH [' . implode(' ', $codes) . ']');
t_ok($holder !== 'WORKFORCE_MATCH', 'X14c · the one WITH the tick was never refused for lack of one (it got ' . $holder . ')');
t_ok($staffN() - $staffBefore === $won && $won <= 1,
     'X14d · the race created exactly as many team members as it reported (' . ($staffN() - $staffBefore) . '), and never more than one');

//  HOW MANY writers can be in flight is an ENGINE fact. The three refusals each
//  write an audit row, and SQLite takes a single database-wide write lock with
//  no busy timeout — so roughly one run in six the converter loses the lock to a
//  refusal's audit write and is honestly told BUSY. That is SQLite being SQLite
//  (Step 1 finding F1, still open); it is not the acknowledgement failing, and
//  asserting CONVERTED there would be asserting the engine rather than the gate.
//  MariaDB is the production engine and is where the claim is proved.
if (t_driver() === 'sqlite') {
    t_ok($holder === 'CONVERTED' || $holder === 'BUSY',
         'X14e (sqlite) · the tick-holder either converted or was told to try again — never silently wrong (' . $holder . ')');
} else {
    t_eq($holder, 'CONVERTED', 'X14e · the tick-holder converted');
    t_eq($won, 1, 'X14f · exactly one of the four succeeded');
}

// ---------------------------------------------------------------------------
t_section('RB3S2 · X12 / X13 — two real, separate tenant databases');
// ---------------------------------------------------------------------------
$tA = sys_get_temp_dir() . '/rb3s2_a_' . getmypid() . '.sqlite';
$tB = sys_get_temp_dir() . '/rb3s2_b_' . getmypid() . '.sqlite';
@unlink($tA); @unlink($tB);
if (t_driver() === 'sqlite') { $specA = 'sqlite:' . $tA; $specB = 'sqlite:' . $tB; }
else {
    $specA = 'mysql:rb3s2_a_' . getmypid(); $specB = 'mysql:rb3s2_b_' . getmypid();
    foreach ([$specA, $specB] as $sp) { $n = explode(':', $sp, 2)[1];
        try { db()->exec("DROP DATABASE IF EXISTS `$n`"); db()->exec("CREATE DATABASE `$n`"); } catch (Throwable $e) {} }
}
$tEnv = '';
foreach (['DB_HOST', 'DB_USER', 'DB_PASS'] as $k) { $v = getenv($k); if ($v !== false && $v !== '') $tEnv .= $k . '=' . escapeshellarg($v) . ' '; }
$tRaw = (string)shell_exec($tEnv . 'php ' . escapeshellarg($s2root . '/tests/_rb3s2_tenant.php') . ' '
       . escapeshellarg($specA) . ' ' . escapeshellarg($specB) . ' 2>&1');
$tJ = null; foreach (explode("\n", trim($tRaw)) as $l) { $j = json_decode(trim($l), true); if (is_array($j)) { $tJ = $j; break; } }
if (!is_array($tJ) || empty($tJ['ok'])) {
    t_ok(false, 'X12/X13 · the two-tenant probe ran (' . substr(preg_replace('/\s+/', ' ', $tRaw), 0, 200) . ')');
} else {
    $st = $tJ['steps'];
    t_eq((int)$st['b_strong'], 1, 'X12a · inside tenant B its own twin IS found — so the check works there, and the next line means something');
    t_ok(!empty($st['b_token_issued']), 'X12b · tenant B could issue a tick of its own');
    t_eq((int)$st['a_strong'], 0, 'X12 · tenant A cannot see tenant B\'s staff at all — one database per tenant, not a filter');
    t_eq((int)$st['a_strong_after'], 1, 'X13a · once tenant A has a twin of its OWN, a tick is genuinely required — armed');
    t_eq((string)$st['foreign_token_code'], 'WORKFORCE_MATCH', 'X13 · tenant B\'s tick does not authorise a hire in tenant A');
    t_eq((int)$st['a_converted_by_foreign_token'], 0, 'X13b · …and nothing was created by it');
    t_eq((string)$st['own_token_code'], 'CONVERTED', 'X13c · tenant A\'s OWN tick still works — the refusal was about the tick, not a broken gate');
}
@unlink($tA); @unlink($tB);
if (t_driver() !== 'sqlite') foreach ([$specA, $specB] as $sp) { $n = explode(':', $sp, 2)[1]; try { db()->exec("DROP DATABASE IF EXISTS `$n`"); } catch (Throwable $e) {} }

// ---------------------------------------------------------------------------
t_section('RB3S2 · X20 / J — nothing else moved');
// ---------------------------------------------------------------------------
t_ok(function_exists('cand_find_duplicates'), 'J1 · the applicant-to-applicant duplicate check still exists');
t_ok(function_exists('cand_submission_dupes'), 'J2 · the same-client submission check still exists');
t_ok(function_exists('connect_identity_suggestions'), 'J3 · the marketplace identity suggester still exists');
t_ok(in_array(EMP_CODE_KEY_IX, table_index_names('inspectors'), true), 'J4 · Step 1\'s employee-number rule is untouched');
t_ok(count(inspectors_list(false)) > 0, 'J5 · the allocate picker still lists people (Operations consumer)');
t_eq((int)ops_val("SELECT COUNT(*) FROM settings WHERE skey='rb3_ack_key'"), 1,
     'J6 · exactly one signing key, in the settings store — NO new table was created');
t_ok(!t_table_exists('workforce_ack') && !t_table_exists('dup_ack'),
     'J7 · …and no acknowledgement table exists anywhere');
