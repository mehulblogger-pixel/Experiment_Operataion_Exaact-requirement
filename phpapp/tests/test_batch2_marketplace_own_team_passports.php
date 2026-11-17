<?php
// ============================================================================
//  Batch 2 (owner, 17 Nov 2026 — plan 29, points 13 and 14, section D).
//
//  Point 13: "Put forward one of your own team" on a Marketplace requirement
//            offered every branch's active team members. It now offers the
//            staff member's OWN office's people only (company-wide roles: all),
//            the "apply" action refuses a typed id of another office's person,
//            and the suggested matches follow the same rule for team members.
//            Marketplace professionals in the suggestions are unchanged.
//
//  Point 14 / D: a company's internal team has NO passport ("if I am an
//            employee with SGS and SGS uses this app, as an inspector I will
//            not have any passport"). The staff "Team passports" tile is gone,
//            /passport-share refuses with a plain message and mints nothing,
//            the requirement page has no Passport button for a team member, and
//            the public page /p/<token> says "This passport is not available."
//            for a token on an inspector record. A Marketplace PROFESSIONAL's
//            passport (/p/<their token>, shown on /pro/profile and the pro
//            dashboard) works exactly as before — also for a professional who
//            is linked to an inspector record.
//
//  Route checks run through the production dispatcher in a separate process
//  (bb_drive); every row this file commits is removed again at the end.
// ============================================================================
$__b2mPrevSession = $_SESSION ?? [];
t_section('Batch 2 — Marketplace: "your own team" is your own office; no passports for the internal team (points 13, 14/D)');

require_once __DIR__ . '/_branch_batch_helpers.php';
connect_market_migrate();
if (function_exists('connect_pro_migrate')) connect_pro_migrate();
if (function_exists('connect_identity_migrate')) connect_identity_migrate();
connect_passport_migrate();
$pdo = db();
$tag = 'B2M' . getmypid();
$made = ['inspectors' => [], 'cx_professionals' => [], 'cx_identity_link' => []];
$q = function ($sql, $a = []) use ($pdo) { $pdo->prepare($sql)->execute($a); return (int)$pdo->lastInsertId(); };
$root = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string)getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string)getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string)getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string)getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string)getenv('DB_PASS'));
$publicPage = function ($token) use ($root, $env) {
    $raw = (string)shell_exec($env . ' php ' . escapeshellarg($root . '/tests/_batch2_passport_worker.php') . ' ' . escapeshellarg((string)$token) . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d) && isset($d['html'])) return $d; }
    return ['status' => 0, 'html' => 'no JSON: ' . substr($raw, 0, 300)];
};

try {
    $A = bb_office('B2M Ahmedabad'); $B = bb_office('B2M Mumbai');
    $coordA = bb_user('COORDINATOR', $A);
    $dir    = bb_user('BUSINESS_DIRECTOR', $A);       // company-wide
    $mkInsp = function ($name, $off) use ($q, &$made) {
        $id = $q("INSERT INTO inspectors (name, status, skills, designation, home_office_id, created_at) VALUES (?, 'ACTIVE', 'Welding, NDT, B2M', 'QA/QC Inspector', ?, ?)",
                 [$name, $off, date('c')]);
        $made['inspectors'][] = $id; return $id;
    };
    $iA = $mkInsp($tag . ' Asha Own', $A);
    $iB = $mkInsp($tag . ' Bharat Other', $B);
    $proTok = bin2hex(random_bytes(16));
    $pro = $q("INSERT INTO cx_professionals (email, name, mobile, password_hash, is_active, verification_tier, headline, skills, passport_token, created_at)
               VALUES (?, ?, '', 'x', 1, 'registered', 'NDT Level II', 'Welding, NDT, B2M', ?, ?)",
              [strtolower($tag) . '.pro@example.test', $tag . ' Priya Freelancer', $proTok, date('c')]);
    $made['cx_professionals'][] = $pro;

    // ---- the posting (made by the Ahmedabad coordinator) -------------------------------------
    $r = bb_drive($coordA, 'connect-requirements', 'POST', [], ['action' => 'create_post', 'title' => $tag . ' Need NDT welding inspector',
        'location' => 'Hazira', 'positions' => 1, 'skills' => 'Welding, NDT, B2M']);
    $R = (int)ops_val("SELECT id FROM cx_requirements WHERE title=?", [$tag . ' Need NDT welding inspector']);
    t_ok($R > 0, 'arranged: the Ahmedabad coordinator posts a requirement (' . bb_said($r) . ')');

    // ---- point 13 · "Put forward one of your own team" ---------------------------------------
    $optA = 'value="' . $iA . '">' . $tag . ' Asha Own';
    $optB = 'value="' . $iB . '">' . $tag . ' Bharat Other';
    $cardB = 'name="inspector_id" value="' . $iB . '"';
    $pg = bb_drive($coordA, 'connect-requirement', 'GET', ['id' => $R], [], [$optA, $optB, $tag . ' Bharat Other', 'Put forward one of your own team', $cardB]);
    t_ok(!empty($pg['has']['Put forward one of your own team']), 'the Ahmedabad coordinator sees "Put forward one of your own team"' . (!empty($pg['error']) ? ' (' . $pg['error'] . ')' : ''));
    t_ok(!empty($pg['has'][$optA]), 'it offers their own office\'s team member');
    t_ok(empty($pg['has'][$optB]), 'it does not offer another office\'s team member (point 13)');
    t_ok(empty($pg['has'][$cardB]) && empty($pg['has'][$tag . ' Bharat Other']), 'and the suggested matches do not put that person forward either');

    $r = bb_drive($coordA, 'connect-requirement', 'POST', [], ['id' => $R, 'action' => 'apply', 'inspector_id' => $iB, 'proposed_rate' => 5000]);
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=? AND inspector_id=?", [$R, $iB]), 0,
        'a typed id of another office\'s team member is refused — nothing is recorded');
    t_ok(stripos(bb_said($r), 'only your own office') !== false, '…with a plain message (' . bb_said($r) . ')');
    $r = bb_drive($coordA, 'connect-requirement', 'POST', [], ['id' => $R, 'action' => 'apply', 'inspector_id' => $iA, 'proposed_rate' => 5000]);
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=? AND inspector_id=?", [$R, $iA]), 1,
        'control: putting forward their own office\'s person works (' . bb_said($r) . ')');

    $pg = bb_drive($dir, 'connect-requirement', 'GET', ['id' => $R], [], [$optB]);
    t_ok(!empty($pg['has'][$optB]), 'a company-wide role (Business Director) is still offered every office\'s people');

    // In-process: the helpers and the matcher follow the same rule.
    t_ok(function_exists('cx_own_team_inspectors') && function_exists('cx_own_team_allows'), 'the own-office team helpers exist');
    if (function_exists('cx_own_team_inspectors')) {
    bb_as($coordA);
    $ids = array_map('intval', array_column(cx_own_team_inspectors(), 'id'));
    t_ok(in_array($iA, $ids, true) && !in_array($iB, $ids, true), 'cx_own_team_inspectors(): own office only for a Coordinator');
    t_ok(cx_own_team_allows($iA) && !cx_own_team_allows($iB), 'cx_own_team_allows(): yes for own office, no for another');
    $req = cx_requirement_get($R);
    $m = connect_match_for_requirement($req, 200, $ids);
    $kinds = []; foreach ($m as $row) $kinds[$row['kind'] . ':' . (int)$row['id']] = true;
    t_ok(empty($kinds['inspector:' . $iB]), 'the matcher, given the own-office list, leaves out the other office\'s team member');
    t_ok(!empty($kinds['professional:' . $pro]), 'and still offers the Marketplace professional (professionals are never limited)');
    $all = []; foreach (connect_match_for_requirement($req, 200) as $row) $all[$row['kind'] . ':' . (int)$row['id']] = true;
    t_ok(!empty($all['inspector:' . $iB]), 'without a list the matcher is unchanged (every other caller)');
    bb_as($dir);
    $ids = array_map('intval', array_column(cx_own_team_inspectors(), 'id'));
    t_ok(in_array($iA, $ids, true) && in_array($iB, $ids, true), 'cx_own_team_inspectors(): everyone for a company-wide role');
    bb_as_nobody();
    }

    // ---- point 14 / D · no "Team passports" anywhere on the staff side -----------------------
    bb_as($coordA);
    $areaFlat = json_encode(ops_area_def('marketplace'));
    t_ok(strpos($areaFlat, 'Team passports') === false && strpos($areaFlat, '\/passport-share') === false && strpos($areaFlat, '/passport-share"') === false,
        'the Marketplace area has no "Team passports" tile');
    t_ok(strpos($areaFlat, 'Agency bench') !== false, '…and still has its other tiles (Agency bench)');
    $nav = function_exists('ops_nav_index') ? ops_nav_index(true) : [];
    t_ok(!array_filter($nav, fn($e) => strpos((string)$e['url'], '/passport-share') === 0 || stripos((string)$e['label'], 'passport') !== false),
        '"Go to anything" offers no passport screen');
    bb_as_nobody();

    $pg = bb_drive($coordA, 'passport-share', 'GET', ['id' => $iA], [], ['Passports are for Marketplace professionals; your own team members do not have one.', '/p/']);
    t_ok(!empty($pg['has']['Passports are for Marketplace professionals; your own team members do not have one.']),
        '/passport-share refuses with a plain message' . (!empty($pg['error']) ? ' (' . $pg['error'] . ')' : ''));
    t_ok(empty($pg['has']['/p/']), '…and shows no passport link');
    t_eq((string)ops_val("SELECT COALESCE(passport_token,'') FROM inspectors WHERE id=?", [$iA]), '', '…and mints no token for the team member');
    bb_drive($coordA, 'passport-share', 'POST', [], ['id' => $iA, 'action' => 'regenerate']);
    t_eq((string)ops_val("SELECT COALESCE(passport_token,'') FROM inspectors WHERE id=?", [$iA]), '', '"Regenerate" mints nothing either');

    // The requirement page: no Passport button for a team member in the suggestions
    // (the own-office team member is suggested; the posting is still open).
    $iC = $mkInsp($tag . ' Chetan Own', $A);
    $card = 'name="inspector_id" value="' . $iC . '"';
    $pg = bb_drive($coordA, 'connect-requirement', 'GET', ['id' => $R], [], ['/passport-share', $card]);
    t_ok(!empty($pg['has'][$card]), 'arranged: an own-office team member is among the suggestions');
    t_ok(empty($pg['has']['/passport-share']), 'the requirement page has no internal "Passport" button for a team member');

    // ---- point 14 / D · the public page --------------------------------------------------------
    $inspTok = bin2hex(random_bytes(16));
    $q("UPDATE inspectors SET passport_token=? WHERE id=?", [$inspTok, $iB]);   // a link made before this change
    $p = $publicPage($inspTok);
    t_ok(strpos($p['html'], 'This passport is not available.') !== false, 'an internal team member\'s old link shows "This passport is not available."');
    t_ok(strpos($p['html'], $tag . ' Bharat Other') === false && stripos($p['html'], 'Welding') === false, '…and nothing about the person');
    t_eq((int)$p['status'], 404, '…as "not found" (404)');
    t_eq((string)ops_val("SELECT passport_token FROM inspectors WHERE id=?", [$iB]), $inspTok, 'no data is deleted: the old token stays on the record, unused');

    $p = $publicPage($proTok);
    t_ok(strpos($p['html'], $tag . ' Priya Freelancer') !== false && strpos($p['html'], 'This passport is not available.') === false,
        'a Marketplace professional\'s passport opens exactly as before');
    t_eq((int)$p['status'], 200, '…with 200');

    // A professional who is ALSO an inspector record (a linked identity): the
    // professional's passport stays; the inspector record's token does not open.
    $made['cx_identity_link'][] = $q("INSERT INTO cx_identity_link (professional_id, inspector_id, method, status, linked_by, linked_at) VALUES (?,?, 'manual', 'LINKED', 'b2m', ?)",
        [$pro, $iB, date('c')]);
    $p = $publicPage($proTok);
    t_ok(strpos($p['html'], $tag . ' Priya Freelancer') !== false, 'a linked professional keeps their own passport');
    $p = $publicPage($inspTok);
    t_ok(strpos($p['html'], 'This passport is not available.') !== false, 'while the linked inspector record\'s token stays unavailable');
    t_ok(connect_passport_lookup($proTok)['_kind'] === 'professional', 'connect_passport_lookup() tells them apart by the table that holds the token');

    // A token that is on both tables (never minted that way; defensive) resolves to the professional.
    $q("UPDATE inspectors SET passport_token=? WHERE id=?", [$proTok, $iA]);
    $p = $publicPage($proTok);
    t_ok(strpos($p['html'], $tag . ' Priya Freelancer') !== false, 'a professional\'s token can never be shadowed by a team member\'s');

    $p = $publicPage(str_repeat('ab', 16));
    t_ok(strpos($p['html'], 'Passport not found') !== false, 'an unknown token still says "Passport not found" (unchanged)');
} finally {
    // ---- clean up: every committed row this file made --------------------------------------------
    $del = function ($sql, $a = []) use ($pdo) { try { $pdo->prepare($sql)->execute($a); } catch (Throwable $e) {} };
    $reqs = array_map('intval', array_column(ops_all("SELECT id FROM cx_requirements WHERE title LIKE ?", [$tag . '%']) ?: [], 'id'));
    $in = $reqs ? implode(',', $reqs) : '0';
    foreach (['cx_positions', 'cx_terms', 'cx_readiness', 'cx_messages', 'cx_bench_alloc', 'cx_disputes', 'cx_applications'] as $t)
        $del("DELETE FROM $t WHERE requirement_id IN ($in)");
    $del("DELETE FROM cx_requirements WHERE id IN ($in)");
    foreach ($made as $t => $ids) foreach ($ids as $id) $del("DELETE FROM $t WHERE id=?", [(int)$id]);
    bb_cleanup();
    // Restore the session this file found (the next test files expect it), not a signed-out one.
    $_SESSION = $__b2mPrevSession; current_user(true); if (function_exists('ua')) ua(true);
}
