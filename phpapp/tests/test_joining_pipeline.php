<?php
// ============================================================================
//  THE JOINING GATE, ASKED OF THE AUTHORITY                (UAT A21.1 · A21.7)
//
//  The owner, twice: "Mark as joined button is not at all working transferring
//  from last pipeline stage to joined", and "Name of joining pending is not
//  showing."
//
//  One defect, three symptoms. recruitpipe_cand_goto() deliberately stopped
//  writing candidates.stage — "every consumer now classifies from the configured
//  stage kind". Every consumer did not. The joining gate still compared that
//  abandoned column with the literal 'ACCEPTED', in THREE places: the route's
//  hired check, its team-record check, and the candidate screen's decision
//  whether to draw the control at all.
//
//  Measured on the DEFAULT shipped pipeline (15 stages, terminal "Onboarding"):
//  a candidate at the terminal stage is authoritatively terminal / FILLED while
//  candidates.stage still reads 'RECEIVED'. So the control was not refusing —
//  it was never drawn. And because nobody could join, nobody could be
//  joining-pending: the availability board had nobody to name (A21.7) and the
//  utilisation report had nobody to exclude (G6B-02.3).
//
//  This file asserts the CLASSIFICATION and what the screens do with it. It does
//  not assert the wording of any message.
// ============================================================================

t_section('Joining — the gate asks the configured stage kind, not a dead column');

$pdo = db();
$origSess = $_SESSION;
$keep = ['c' => [], 'i' => [], 'o' => []];
recruitpipe_migrate();

$mkCand = function ($stage) use ($pdo, &$keep) {
    $pdo->prepare("INSERT INTO candidates (first_name,last_name,stage,created_at) VALUES ('JP','Probe',?,?)")
        ->execute([$stage, date('c')]);
    $id = (int) $pdo->lastInsertId(); $keep['c'][] = $id;
    return ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
};

// ---------------------------------------------------------------------------
//  1. THE DEFECT. A candidate at the pipeline's terminal stage IS hired.
// ---------------------------------------------------------------------------
$c = $mkCand('RECEIVED');
t_ok(!rpipe_cand_hired($c), 'ARMING · a freshly received candidate is not hired');
[$pipe, $eff, $idx] = recruitpipe_cand_state($c);
t_ok($pipe && $eff, 'ARMING · this workspace has a configured pipeline to move along');
$term = null; foreach ($eff as $s) if ((string) ($s['kind'] ?? '') === 'terminal') $term = $s;
t_ok($term !== null, 'ARMING · and that pipeline has a terminal (hired) stage');
if ($term) {
    t_ok(recruitpipe_cand_goto($c, (int) $term['id'], 'joining test', 'test'), 'the candidate moves to it');
    $c = ops_one("SELECT * FROM candidates WHERE id=?", [(int) $c['id']]);
    //  The heart of it: authoritative yes, legacy column untouched.
    t_ok(rpipe_cand_hired($c), '*** a candidate at the terminal stage is HIRED — the joining gate opens');
    t_ok(strtoupper((string) $c['stage']) !== 'ACCEPTED',
         '*** …even though candidates.stage was never written, which is what broke it');
    $st = rpipe_current_state($c);
    t_eq($st['eff_kind'], 'terminal', '…because the configured stage kind says terminal');
    t_eq($st['class'], 'FILLED', '…and it classifies as FILLED');
}

// ---------------------------------------------------------------------------
//  2. THE CONTROL IS NOT LOST. Only a hired person passes.
// ---------------------------------------------------------------------------
foreach (['RECEIVED', 'SUBMITTED', 'OFFERED'] as $stage) {
    $m = $mkCand($stage);
    t_ok(!rpipe_cand_hired($m), "*** somebody at $stage is still NOT hired — the gate still refuses");
}
//  An offer that is out is not a filled seat (G1A-2). Recorded here too, because
//  a looser 'hired' test is exactly how that protection would be lost again.
$off = $mkCand('OFFERED');
t_eq(rpipe_current_state($off)['class'], 'ACTIVE', 'an issued offer is still an open seat, not a hire');

// ---------------------------------------------------------------------------
//  3. THE PRE-PIPELINE PATH IS UNCHANGED.
//     A workspace that never configured a pipeline must behave exactly as before.
// ---------------------------------------------------------------------------
$legacy = $mkCand('ACCEPTED');
t_ok(rpipe_cand_hired($legacy), '*** a legacy ACCEPTED candidate is still hired — nothing regressed');

// ---------------------------------------------------------------------------
//  4. BOTH SCREENS ASK THE SAME QUESTION.
//     The route and the view disagreeing is what made this invisible: the route
//     would have refused, but the control was never drawn to refuse.
// ---------------------------------------------------------------------------
$route = (string) @file_get_contents(__DIR__ . '/../lib/ops.php');
$view  = (string) @file_get_contents(__DIR__ . '/../views/ops/candidate_detail.php');
$strip = fn($t) => (string) preg_replace('~^\s*//.*$~m', '', (string) preg_replace('~/\*.*?\*/~s', '', $t));
t_ok(strpos($strip($route), 'rpipe_cand_hired') !== false,
     '*** the joining route asks the authoritative question');
t_ok(strpos($strip($view), 'rpipe_cand_hired') !== false,
     '*** and so does the screen that draws the control');

// ---------------------------------------------------------------------------
//  5. THE AVAILABILITY BOARD NAMES THE AWAITED PEOPLE (A21.7).
//     "Being kept off jobs is not the same as being hidden" — the guide's own
//     words, and the reason a count with a link was not good enough.
// ---------------------------------------------------------------------------
t_ok(function_exists('wf_joining_pending_people'), '*** the board can ask WHO is awaited, not just how many');
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (951,'JP Branch',1)")->execute(); $keep['o'][] = 951; }
catch (Throwable $e) {}
$pdo->prepare("INSERT INTO inspectors (name,status,home_office_id,staff_kind) VALUES ('JP Awaited Person',?,951,'ASSET')")
    ->execute([WF_ST_JOINING]);
$keep['i'][] = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO inspectors (name,status,home_office_id,staff_kind) VALUES ('JP Active Person','ACTIVE',951,'ASSET')")->execute();
$keep['i'][] = (int) $pdo->lastInsertId();

$who = wf_joining_pending_people([951]);
$names = array_map(fn($p) => $p['name'], $who);
t_ok(in_array('JP Awaited Person', $names, true), '*** the awaited person is NAMED, not merely counted');
t_ok(!in_array('JP Active Person', $names, true), '…and somebody already active is not in that group');
t_eq(count($who), wf_joining_pending_in_offices([951]),
     '*** the names and the count agree — one query, one answer');
//  Scope still applies: another branch does not see them.
t_eq(wf_joining_pending_people([952]), [], 'another branch is not shown this branch\'s awaited people');
//  And the view actually renders them.
$av = (string) @file_get_contents(__DIR__ . '/../views/ops/availability.php');
t_ok(strpos($av, 'joiningPendingPeople') !== false, '*** the board renders the names it is given');

// ---------------------------------------------------------------------------
//  Clean up.
// ---------------------------------------------------------------------------
foreach ($keep['i'] as $i) { try { $pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$i]); } catch (Throwable $e) {} }
foreach ($keep['c'] as $i) { try { $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([$i]); } catch (Throwable $e) {} }
foreach ($keep['o'] as $i) { try { $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$i]); } catch (Throwable $e) {} }
$_SESSION = $origSess; current_user(true); ua(true);
