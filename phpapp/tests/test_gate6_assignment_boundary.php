<?php
// ============================================================================
//  GATE 6 · A-F1 — WHO MAY BE GIVEN A JOB
// ============================================================================
//  THE BUSINESS RULE
//
//  A person who has accepted an offer but has not started must not be assignable
//  to work. Gate 5 established that boundary, and every operational reader was
//  believed to honour it. One did not.
//
//  The job assignment panel's Reassign dropdown (lib/tosrm.php, the control a
//  coordinator uses to put somebody on a job) asked NEGATIVELY — "anybody who is
//  not INACTIVE" — so a joining-pending person appeared in it and could be given
//  an inspection weeks before their first day.
//
//  WHY GATE 5's MUTATION BATTERY DID NOT CATCH IT
//  Mutations M6 and M8 break wf_is_active() and wf_active_sql(). This screen
//  called neither — it carried its own copy of the rule. A test that exercises
//  the HELPERS can therefore never prove this screen is safe, so this battery
//  asserts the RENDERED DROPDOWN itself: what a coordinator can actually pick.
// ============================================================================
$G6 = 'G6-' . random_int(1000, 9999);
$pdo = db();
if (function_exists('tosrm_migrate_b')) { try { tosrm_migrate_b(); } catch (Throwable $e) {} }
$g6off = (int) ops_val("SELECT MIN(id) FROM offices");
$made = ['ins' => [], 'job' => [], 'user' => []];

//  Act as somebody who may reassign. tosrm_can_edit() allows a master admin, and
//  the dropdown only renders for a user who may edit — so a test run as a
//  read-only user would prove nothing at all.
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES (?,?,'MANAGER',1,1,?,'')")->execute([$G6 . '_u', 'Gate6', $g6off]);
$g6u = (int) $pdo->lastInsertId(); $made['user'][] = $g6u;
$_SESSION['uid'] = $g6u; current_user(true); ua(true);

//  Four people, one per status the column can hold, plus an unrecognised value.
$mk = function ($tag, $status) use ($pdo, $G6, $g6off, &$made) {
    $pdo->prepare("INSERT INTO inspectors (name,emp_code,home_office_id,staff_kind,team_role,status,created_at)
                   VALUES (?,?,?,'ASSET','FIELD',?,?)")
        ->execute([$G6 . ' ' . $tag, $G6 . '-' . $tag, $g6off, $status, date('c')]);
    $id = (int) $pdo->lastInsertId(); $made['ins'][] = $id;
    return $id;
};
$iActive  = $mk('Activeone',  WF_ST_ACTIVE);
$iJoining = $mk('Joiningone', WF_ST_JOINING);
$iLeft    = $mk('Leftone',    WF_ST_INACTIVE);
$iBlank   = $mk('Blankone',   '');
$iJunk    = $mk('Junkone',    'WHATEVER');

//  A job with NOBODY on it, so no candidate is skipped as "already assigned".
$pdo->prepare("INSERT INTO jobs (job_code,inspector_id,executing_office_id,scheduled_date,sbu,created_at)
               VALUES (?,0,?,?,'IND',?)")
    ->execute([$G6 . '-JOB', $g6off, date('Y-m-d'), date('c')]);
$jid = (int) $pdo->lastInsertId(); $made['job'][] = $jid;
$job = ops_one("SELECT * FROM jobs WHERE id=?", [$jid]);

// ---------------------------------------------------------------------------
t_section('G6 A-F1 · THE ASSIGNMENT DROPDOWN A COORDINATOR ACTUALLY SEES');
// ---------------------------------------------------------------------------
t_ok(function_exists('tosrm_render_job_panel'), 'AF1-0 · the assignment panel exists');
t_ok((bool) $job, 'AF1-0b · a job with nobody assigned was created');

//  Render the REAL panel and capture what it produced.
$html = '';
try {
    ob_start();
    tosrm_render_job_panel($job);
    $html = (string) ob_get_clean();
} catch (Throwable $e) {
    if (ob_get_level() > 0) { ob_end_clean(); }
    $html = '';
    t_ok(false, 'AF1-0c · the panel threw: ' . $e->getMessage());
}

//  GUARD AGAINST A VACUOUS PASS. If the dropdown did not render at all then every
//  "is absent" assertion below would pass for the wrong reason — a green tick for
//  a control nobody can see. Stop instead.
$hasSelect = strpos($html, 'name="inspector_id"') !== false;
t_ok($hasSelect, 'AF1-1 · the Reassign dropdown rendered (without this, nothing below means anything)');
if (!$hasSelect) {
    echo "    >>> panel output was " . strlen($html) . " bytes\n";
    t_ok(false, 'AF1-1x · dropdown absent — cannot test the assignment boundary, stopping');
    return;
}

//  Only the part of the page that IS the dropdown, so a name appearing elsewhere
//  on the panel (the assignment history, for instance) cannot be mistaken for an
//  assignable option.
$selStart = strpos($html, 'name="inspector_id"');
$selEnd   = strpos($html, '</select>', $selStart);
$dropdown = substr($html, $selStart, max(0, $selEnd - $selStart));
$inDropdown = fn($id, $tag) => strpos($dropdown, 'value="' . (int) $id . '"') !== false
                            || strpos($dropdown, $G6 . ' ' . $tag) !== false;

t_ok($inDropdown($iActive, 'Activeone'),
     'AF1-2 · an ACTIVE colleague IS offered — the control still does its job');
t_ok(!$inDropdown($iJoining, 'Joiningone'),
     'AF1-3 · *** a JOINING-PENDING person is NOT offered — they cannot be given work before day one ***');
t_ok(!$inDropdown($iLeft, 'Leftone'),
     'AF1-4 · somebody who has LEFT is not offered (unchanged behaviour)');
t_ok($inDropdown($iBlank, 'Blankone'),
     'AF1-5 · a blank status still counts as active, as it does everywhere else (field-finding #26)');
t_ok(!$inDropdown($iJunk, 'Junkone'),
     'AF1-6 · an unrecognised status is excluded rather than admitted by default');

// ---------------------------------------------------------------------------
t_section('G6 A-F1 · THE SAME QUESTION, ASKED OF THE CANONICAL HELPER');
// ---------------------------------------------------------------------------
//  The screen must not carry its own definition of "active". If this ever stops
//  agreeing with the dropdown above, two definitions have grown apart again.
$viaHelper = ops_all("SELECT id FROM inspectors WHERE " . wf_active_sql()
                   . " AND id IN (?,?,?,?,?) ORDER BY id",
                   [$iActive, $iJoining, $iLeft, $iBlank, $iJunk]) ?: [];
$ids = array_map('intval', array_column($viaHelper, 'id'));
sort($ids); $expect = [$iActive, $iBlank]; sort($expect);
t_eq(implode(',', $ids), implode(',', $expect),
     'AF1-7 · wf_active_sql() selects exactly the same people the dropdown offers');
//  The CALL SITE, not merely the words. The first version of this assertion
//  looked for "wf_active_sql()" anywhere in the file, and so PASSED under
//  mutation — because the explanatory comment above the query names the helper
//  too. It now requires the helper to be what the query is actually built from.
t_ok(strpos((string) @file_get_contents(dirname(__DIR__) . '/lib/tosrm.php'),
            'FROM inspectors WHERE " . wf_active_sql() . "') !== false,
     'AF1-8 · the screen BUILDS its query from the canonical helper, not a restated rule');
t_ok(strpos((string) @file_get_contents(dirname(__DIR__) . '/lib/tosrm.php'), "COALESCE(status,'')<>'INACTIVE'") === false,
     'AF1-9 · *** and the negative filter is gone from the file ***');

// ---------------------------------------------------------------------------
t_section('G6 A-F1 · Z · PUT THE FIXTURES BACK');
// ---------------------------------------------------------------------------
try {
    foreach ($made['job'] as $j) $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$j]);
    foreach ($made['ins'] as $i) $pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$i]);
    foreach ($made['user'] as $u) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$u]);
} catch (Throwable $e) {}
t_eq((int) ops_val("SELECT COUNT(*) FROM inspectors WHERE emp_code LIKE ?", [$G6 . '%']), 0,
     'AF1-Z1 · the people this suite created are gone');
$_SESSION['uid'] = null; current_user(true); ua(true);
