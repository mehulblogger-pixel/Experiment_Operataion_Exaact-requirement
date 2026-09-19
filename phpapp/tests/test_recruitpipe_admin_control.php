<?php
// ============================================================================
//  RECRUITMENT PIPELINE — the administrator's workflow controls on the
//  candidate screen, and the template boundary that hid them.
//
//  Found by walking the real product in a browser, not by a failing test: a
//  line of template code was PRINTED under the candidate's name —
//
//      $isAdmin = function_exists('hiring_admin_can') && hiring_admin_can();
//
//  — followed by a stray closing tag, because the PHP block was closed one line
//  too early. (The closing tag is deliberately NOT written out in this comment:
//  PHP ends its block on one even inside a // comment, which is the same family
//  of mistake and cost this very file its first run.) The assignment never
//  ran, so $isAdmin was never true, and BOTH controls it guards were invisible
//  to administrators: the "Edit workflow" link and the workflow switcher.
//
//  Introduced by eb8ad64e (12 Sep 2026), which predates Phase 6 Batch 3; no
//  Batch 3 commit touched this file.
//
//  The panel is captured with an output buffer and read as text, because what
//  a person SEES is the thing that was wrong.
// ============================================================================
t_section('recruitment pipeline · the admin workflow controls (and the template boundary)');

$rpSess = $_SESSION;

//  A candidate the panel will actually draw for. One is created here rather
//  than hunted for in seed data, so the probe has a subject whatever the
//  database happens to hold — the lesson from the empty-premise probes earlier
//  in Phase 6.
recruitpipe_migrate();
$rpCand = null;
foreach (ops_all("SELECT * FROM candidates ORDER BY id") ?: [] as $c) {
    [$pipe, $eff] = recruitpipe_cand_state($c);
    if ($pipe && $eff) { $rpCand = $c; break; }
}
if (!is_array($rpCand)) {
    $rpCols = function_exists('t_columns') ? t_columns('candidates') : [];
    $rpNew  = ['name' => 'Workflow Panel Probe', 'stage' => 'NEW'];
    if (in_array('created_at', $rpCols, true)) $rpNew['created_at'] = date('c');
    $rpKeys = array_values(array_filter(array_keys($rpNew), fn($k) => !$rpCols || in_array($k, $rpCols, true)));
    db()->prepare("INSERT INTO candidates (" . implode(',', $rpKeys) . ") VALUES ("
                  . implode(',', array_fill(0, count($rpKeys), '?')) . ")")
        ->execute(array_map(fn($k) => $rpNew[$k], $rpKeys));
    $rpCand = ops_one("SELECT * FROM candidates WHERE id=?", [(int)db()->lastInsertId()]);
    [$pipe, $eff] = recruitpipe_cand_state($rpCand);
    if (!$pipe || !$eff) $rpCand = null;
}
t_ok(is_array($rpCand), 'RP0 · a candidate on a configured workflow exists to draw');

$rpRender = function ($cand) {
    ob_start();
    try { recruitpipe_candidate_panel($cand); } catch (Throwable $e) { ob_end_clean(); return 'THREW: ' . $e->getMessage(); }
    return (string)ob_get_clean();
};

//  The exact source fragment that was being shown to users. Split so that this
//  test file can never match itself if somebody greps the tree for it.
$rpLeak = '$isAdmin = ' . 'function_exists(\'hiring_admin_can\')';

if (is_array($rpCand)) {

    // ---- TEST A · ADMINISTRATOR -------------------------------------------
    t_as_admin();
    t_ok(hiring_admin_can(), 'RP-A1 · the test actor really does hold the recruitment-admin right');
    $rpAdminHtml = $rpRender($rpCand);
    t_ok(strpos($rpAdminHtml, 'Hiring workflow') !== false, 'RP-A2 · the workflow panel renders for an administrator');
    t_ok(strpos($rpAdminHtml, 'Edit workflow') !== false,
         'RP-A3 · *** the Edit workflow control APPEARS for an administrator ***');
    t_ok(strpos($rpAdminHtml, '/recruit-pipelines') !== false,
         'RP-A4 · and it points at the existing workflow-editing screen');
    //  The same flag guards a second control — the workflow switcher — so the
    //  defect cost an administrator two things, not one.
    $rpPipes = function_exists('recruitpipe_all') ? recruitpipe_all(true) : [];
    if (count($rpPipes) > 1)
        t_ok(strpos($rpAdminHtml, 'value="setpipe"') !== false,
             'RP-A5 · the workflow switcher also appears — the same flag guards both');
    else
        t_ok(true, 'RP-A5 · only one workflow configured, so the switcher is correctly absent');

    // ---- TEST C · THE TEMPLATE BOUNDARY (the defect itself) ---------------
    t_ok(strpos($rpAdminHtml, $rpLeak) === false,
         'RP-C1 · *** no template source is printed to the page ***');
    t_ok(strpos($rpAdminHtml, 'hiring_admin_can()') === false,
         'RP-C2 · nor any other fragment of the authorisation expression');
    t_ok(strpos($rpAdminHtml, '<?php') === false && strpos($rpAdminHtml, '?>') === false,
         'RP-C3 · and no unexecuted PHP tag survives into the output');

    // ---- TEST B · NON-ADMINISTRATOR ---------------------------------------
    //  A real user without the recruitment-admin right — not a logged-out
    //  session, because the question is whether the CONTROL is withheld from
    //  somebody who can legitimately see the candidate.
    $rpUid = (int)(ops_val("SELECT id FROM users WHERE is_active=1 AND COALESCE(is_superuser,0)=0
                            AND username <> 'admin' ORDER BY id LIMIT 1") ?: 0);
    if (!$rpUid) {                       // create one, so the probe has a subject
        db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active)
                       VALUES ('rp.field',?,'Field','Person','rp.field@x.test','INSPECTOR',0,1)")
            ->execute([password_hash('x', PASSWORD_DEFAULT)]);
        $rpUid = (int)db()->lastInsertId();
    }
    if ($rpUid) {
        $_SESSION['uid'] = $rpUid; current_user(true); ua(true);
        if (hiring_admin_can()) {
            t_ok(true, 'RP-B0 · the only available non-superuser also holds the admin right — see RP-B4');
        } else {
            $rpPlainHtml = $rpRender($rpCand);
            t_ok(strpos($rpPlainHtml, 'Hiring workflow') !== false,
                 'RP-B1 · the panel still renders for somebody without the right');
            t_ok(strpos($rpPlainHtml, 'Edit workflow') === false,
                 'RP-B2 · *** but the Edit workflow control is WITHHELD ***');
            t_ok(strpos($rpPlainHtml, 'value="setpipe"') === false,
                 'RP-B3 · and so is the workflow switcher');
            t_ok(strpos($rpPlainHtml, $rpLeak) === false,
                 'RP-B4 · no template source is printed to them either');
        }
    } else {
        foreach (['RP-B1','RP-B2','RP-B3','RP-B4'] as $k) t_ok(false, $k . ' · no non-admin user to act as');
    }

    // ---- TEST D · THE ROUTE BEHIND THE CONTROL ----------------------------
    //  Hiding a link is not authorisation. The screen it opens asks for the
    //  right itself, and the action it posts asks again — so the control is
    //  presentation and the server is authority.
    $rpSrc  = (string)@file_get_contents(dirname(__DIR__) . '/lib/recruitpipe.php');
    $rpRoute = substr($rpSrc, strpos($rpSrc, 'function ops_recruit_pipelines'), 260);
    t_ok(strpos($rpRoute, 'ops_require(hiring_admin_can()') !== false,
         'RP-D1 · the workflow-editing screen refuses anyone without the right, on the server');
    $rpSet = substr($rpSrc, strpos($rpSrc, "\$action === 'setpipe'"), 220);
    t_ok(strpos($rpSet, '!hiring_admin_can()') !== false,
         'RP-D2 · and the switch action re-checks it when posted, not merely when drawn');
    t_ok(strpos($rpSrc, '$isAdmin = true') === false && strpos($rpSrc, '$isAdmin = false') === false,
         'RP-D3 · the flag is never hard-coded — it is always the real authorisation question');
}

$_SESSION = $rpSess; current_user(true); ua(true);
