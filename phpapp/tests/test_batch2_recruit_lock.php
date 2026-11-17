<?php
// ============================================================================
//  BATCH 2 — THE CANDIDATE LOCK STARTS AT THE FIRST INTERVIEW
//  (owner, 17 Nov 2026 — QA-REPORTS/V51/29 section C, points 7, 8, 9 and Q2)
//
//    "When a candidate is in the pool and no recruitment process has been
//     started by any branch, everything of the candidate is shown to every
//     office. Once the process starts by any office (interview), a badge shall
//     be shown with the candidate as locked, with no access to any other office."
//    Q2 — the lock starts at the FIRST INTERVIEW SCHEDULED, not on a link.
//
//  Two offices, BL Ahmedabad and BL Mumbai. Every probe drives the PRODUCTION
//  route through the dispatcher as a real signed-in person (tests/
//  _sales_route_worker.php), or calls the production function a route calls.
//
//   A · linked to Mumbai's requisition, no interview = open to every office:
//       Ahmedabad opens the full candidate and may take them on.
//   B · Ahmedabad schedules the first interview = locked with Ahmedabad:
//       Mumbai cannot open or act, and sees only the name and the badge in
//       Best matches, the register, search and the pool screen.
//   C · the lock is the person's: another record of the same person is locked.
//   D · point 8 — a second record while locked elsewhere is BLOCKED; a look-alike
//       that is not locked keeps the soft warning.
//   E · company-wide roles are not blocked.
//   F · point 9 — a process that ended puts them back in every office's Best
//       matches and pool (not a hired person).
//   G · the switch OFF = each office sees only its own.
//   H · the process ends → the lock is lifted.
//  Every row this file commits is removed at the end.
// ============================================================================
t_section('Batch 2 — the candidate lock starts at the first interview');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string) ($f['text'] ?? ''), $r['flash'] ?? []));
$has  = fn($r, $n) => !empty($r['has'][$n]);

$pdo = db();
if (function_exists('req_migrate')) req_migrate();
if (function_exists('rasg_migrate')) rasg_migrate();
if (function_exists('recruit_iv_migrate')) recruit_iv_migrate();
if (function_exists('recruit_offer_migrate')) recruit_offer_migrate();
$blOrig = $_SESSION ?? [];
$blPoolWas = function_exists('setting_get') ? setting_get('recruit_pool_shared', null) : null;
setting_set('recruit_pool_shared', '1');

$mine = ['office' => [], 'user' => [], 'req' => [], 'cand' => [], 'pro' => []];
$office = function ($name) use ($pdo, &$mine) {
    $pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute([$name]);
    return $mine['office'][] = (int) $pdo->lastInsertId();
};
$AHM = $office('BL Ahmedabad'); $MUM = $office('BL Mumbai');
$user = function ($un, $role, $off, $super = 0) use ($pdo, &$mine) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id, scope_offices)
                   VALUES (?,?,?,?,?,1,?,?,'')")->execute([$un, ucfirst($un), 'Probe', '', $role, $super, $off]);
    return $mine['user'][] = (int) $pdo->lastInsertId();
};
$uAhm    = $user('bl_coord_ahm', 'COORDINATOR', $AHM);
$uMum    = $user('bl_coord_mum', 'COORDINATOR', $MUM);
$uAdmin  = $user('bl_admin',     'ADMIN',       $MUM);     // company-wide by role
$uMaster = $user('bl_master',    'MASTER_ADMIN', $MUM, 1);
$as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

$req = function ($office, $code) use ($pdo, &$mine) {
    $pdo->prepare("INSERT INTO requisitions (req_code, office_id, sbu, designation, status, quantity, created_at)
                   VALUES (?,?,?,?,?,?,?)")->execute([$code, $office, '', 'BL_FITTER', 'OPEN', 5, date('c')]);
    return $mine['req'][] = (int) $pdo->lastInsertId();
};
$rAhm = $req($AHM, 'REQ-BL-AHM'); $rMum = $req($MUM, 'REQ-BL-MUM');
$ago20 = date('Y-m-d', strtotime('-20 days'));
$cand = function ($first, $last, $reqId, $stage, array $x = []) use ($pdo, $ago20, &$mine) {
    $d = array_merge(['cand_code' => 'BLK-' . strtoupper($last), 'first_name' => $first, 'last_name' => $last, 'stage' => $stage,
                      'requisition_id' => $reqId, 'designation' => 'BL_FITTER', 'sbu' => '', 'mobile' => '', 'email' => '',
                      'cv_received_date' => $ago20, 'decided_at' => '', 'cv_text' => '', 'joined_at' => null, 'created_at' => date('c')], $x);
    $pdo->prepare("INSERT INTO candidates (cand_code, first_name, last_name, stage, requisition_id, designation, sbu, mobile, email,
                   cv_received_date, decided_at, cv_text, joined_at, created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute(array_values($d));
    return $mine['cand'][] = (int) $pdo->lastInsertId();
};
$iv = function ($candId, $reqId, $result) use ($pdo) {
    try {
        $pdo->prepare("INSERT INTO interviews (candidate_id, round, scheduled_at, panel, result, requisition_id, created_at)
                       VALUES (?,?,?,?,?,?,?)")->execute([$candId, 'L1', date('Y-m-d') . 'T09:00', 'Old Panel', $result, $reqId, date('c')]);
    } catch (Throwable $e) {   // the code before this batch had no requisition_id on an interview
        $pdo->prepare("INSERT INTO interviews (candidate_id, round, scheduled_at, panel, result, created_at)
                       VALUES (?,?,?,?,?,?)")->execute([$candId, 'L1', date('Y-m-d') . 'T09:00', 'Old Panel', $result, date('c')]);
    }
};
$tomorrow = date('Y-m-d', strtotime('+1 day'));
//  Linked to MUMBAI's requisition, shortlisted, no interview: open to everyone.
$cOpen  = $cand('Blk', 'Openpool', $rMum, 'SHORTLISTED', ['mobile' => '9100000001', 'email' => 'blk.open@pool.test', 'cv_text' => 'BLKCVMARKER pipe fitting']);
//  A second record of the SAME person (same e-mail, different case), on Mumbai's requisition.
$cSib   = $cand('Blk', 'Sibling',  $rMum, 'RECEIVED',    ['email' => 'BLK.Open@Pool.test ']);
//  Processes that ENDED with Ahmedabad (interviewed, then closed out) — back in the pool.
$cRej   = $cand('Blk', 'Rejected', $rAhm, 'REJECTED',       ['decided_at' => date('c')]);
$cDecl  = $cand('Blk', 'Declined', $rAhm, 'OFFER_DECLINED', ['decided_at' => date('c'), 'mobile' => '9100000005']);
$cDnj   = $cand('Blk', 'Nojoin',   $rAhm, 'WITHDRAWN',      ['decided_at' => date('c')]);
$iv($cRej, $rAhm, 'FAIL'); $iv($cDecl, $rAhm, 'PASS'); $iv($cDnj, $rAhm, 'PASS');
//  Hired and joined with Ahmedabad: an employee, not a pool candidate.
$cHired = $cand('Blk', 'Hiredjoined', $rAhm, 'ACCEPTED', ['decided_at' => date('c'), 'joined_at' => date('c')]);
//  Onboarding with Ahmedabad (offer accepted, not joined).
$cOnb   = $cand('Blk', 'Onboarding', $rAhm, 'ACCEPTED', ['decided_at' => date('c')]);
//  Its only interview was CANCELLED: nothing was ever held — not locked.
$cCanc  = $cand('Blk', 'Cancelled', $rAhm, 'SHORTLISTED', ['designation' => 'BL_OTHER']);
$iv($cCanc, $rAhm, 'CANCELLED');
$reqOf = fn($id) => (int) ops_val("SELECT COALESCE(requisition_id,0) FROM candidates WHERE id=?", [$id]);
$ivCount = fn($id) => (int) ops_val("SELECT COUNT(*) FROM interviews WHERE candidate_id=?", [$id]);
$badge = fn($id) => function_exists('cand_process_badge') ? cand_process_badge($id) : '';

// ---- A · linked, no interview — open to every office ---------------------------------------
t_section('A · linked to an office\'s requisition, no interview yet = open to every office (Q2)');
t_eq($badge($cOpen), '', 'A1 · *** linked to Mumbai\'s requisition with no interview: no lock, no badge ***');
$as($uAhm);
t_eq(function_exists('cand_other_office_block') ? cand_other_office_block($cOpen, ['process_only' => true]) : 'x', '',
     'A2 · *** Ahmedabad is not locked out of a Mumbai candidate who has not been interviewed ***');
$pg = $drive($uAhm, 'candidate', 'GET', ['id' => $cOpen], [], ['Blk Openpool', 'BLKCVMARKER', 'Locked —']);
t_ok($has($pg, 'Blk Openpool') && $has($pg, 'BLKCVMARKER'), 'A3 · *** Ahmedabad opens the FULL candidate — page and CV *** (' . $said($pg) . ')');
t_ok(!$has($pg, 'Locked —'), 'A4 · …with no lock badge');
t_eq($badge($cCanc), '', 'A5 · an interview that was CANCELLED is not an interview held — no lock');
//  Before the lock another office may take them on: not by working on Mumbai's
//  requisition, but by linking them to its own.
$r = $drive($uAhm, 'candidate-interview', 'POST', ['id' => $cOpen],
            ['do' => 'schedule', 'round' => 'L1', 'scheduled_at' => $tomorrow . 'T10:00', 'mode' => 'In person', 'location' => 'Office', 'panel_extra' => 'Outside Panel']);
t_eq($ivCount($cOpen), 0, 'A6 · Ahmedabad does not schedule on MUMBAI\'s requisition — nothing written');
t_ok(strpos($said($r), 'link them to one of your own office') !== false, 'A7 · …and is told how to take them on: ' . $said($r));
$r = $drive($uAhm, 'candidate-edit', 'POST', ['id' => $cOpen],
            ['first_name' => 'Blk', 'last_name' => 'Openpool', 'designation' => 'BL_FITTER', 'mobile' => '9100000001',
             'email' => 'blk.open@pool.test', 'requisition_id' => (string) $rAhm]);
t_eq($reqOf($cOpen), $rAhm, 'A8 · *** Ahmedabad links them to its own requisition *** (' . $said($r) . ')');
t_eq($badge($cOpen), '', 'A9 · *** still not locked: a link alone does not lock (Q2) ***');
$as($uMum);
t_eq(function_exists('cand_other_office_block') ? cand_other_office_block($cOpen, ['process_only' => true]) : 'x', '',
     'A10 · …so Mumbai is not locked out either — the first to schedule takes the lock');

// ---- B · the first interview scheduled = locked ---------------------------------------------
t_section('B · Ahmedabad schedules the first interview — the candidate is locked with Ahmedabad');
$r = $drive($uAhm, 'candidate-interview', 'POST', ['id' => $cOpen],
            ['do' => 'schedule', 'round' => 'L1', 'scheduled_at' => $tomorrow . 'T10:00', 'mode' => 'In person', 'location' => 'Office', 'panel_extra' => 'Outside Panel']);
t_eq($ivCount($cOpen), 1, 'B1 · Ahmedabad schedules L1 (' . $said($r) . ')');
try { $ivReq = (int) ops_val("SELECT COALESCE(requisition_id,0) FROM interviews WHERE candidate_id=?", [$cOpen]); } catch (Throwable $e) { $ivReq = -1; }
t_eq($ivReq, $rAhm,
     'B2 · the interview records the requisition it was scheduled against');
t_eq($badge($cOpen), 'Locked — in recruitment with BL Ahmedabad', 'B3 · *** the badge: "Locked — in recruitment with BL Ahmedabad" ***');
$lockMsg = 'Blk Openpool is locked — in recruitment with the BL Ahmedabad office';
$pg = $drive($uMum, 'candidate', 'GET', ['id' => $cOpen], [], ['BLKCVMARKER']);
t_ok(!$has($pg, 'BLKCVMARKER'), 'B4 · *** Mumbai can no longer OPEN the candidate ***');
t_ok(strpos($said($pg), $lockMsg) !== false, 'B5 · …refused in plain words naming the office: ' . $said($pg));
$r = $drive($uMum, 'candidate-edit', 'GET', ['id' => $cOpen], [], ['BLKCVMARKER', 'blk.open@pool.test']);
t_ok(!$has($r, 'blk.open@pool.test') && strpos($said($r), $lockMsg) !== false, 'B6 · nor open the edit form (' . $said($r) . ')');
$r = $drive($uMum, 'candidate-interview', 'POST', ['id' => $cOpen],
            ['do' => 'schedule', 'round' => 'L2', 'scheduled_at' => $tomorrow . 'T12:00', 'mode' => 'In person', 'location' => 'Office', 'panel_extra' => 'Outside Panel']);
t_eq($ivCount($cOpen), 1, 'B7 · *** Mumbai cannot schedule an interview ***');
t_ok(strpos($said($r), $lockMsg) !== false, 'B8 · …the refusal names the office: ' . $said($r));
$r = $drive($uMum, 'candidate-edit', 'POST', ['id' => $cOpen],
            ['first_name' => 'Blk', 'last_name' => 'Openpool', 'designation' => 'BL_FITTER', 'requisition_id' => (string) $rMum]);
t_eq($reqOf($cOpen), $rAhm, 'B9 · *** Mumbai cannot take them back to its own requisition ***');
$r = $drive($uMum, 'candidate-doc', 'POST', ['id' => $cOpen], ['do' => 'add', 'doc_type' => 'Other']);
t_eq((int) ops_val("SELECT COUNT(*) FROM candidate_docs WHERE candidate_id=?", [$cOpen]), 0, 'B10 · nor add a document');
$r = $drive($uMum, 'offer-letter', 'GET', ['id' => $cOpen]);
t_ok(strpos($said($r), $lockMsg) !== false, 'B11 · nor open any other candidate route, e.g. the offer letter (' . $said($r) . ')');
$as($uAhm);
t_eq(cand_other_office_block($cOpen), '', 'B12 · Ahmedabad, which holds the lock, works as normal');
$pg = $drive($uAhm, 'candidate', 'GET', ['id' => $cOpen], [], ['BLKCVMARKER', 'Locked — in recruitment with BL Ahmedabad']);
t_ok($has($pg, 'BLKCVMARKER') && $has($pg, 'Locked — in recruitment with BL Ahmedabad'), 'B13 · …opens the candidate and sees the badge too');

//  Lists: Mumbai sees ONLY the name and the badge.
$pg = $drive($uMum, 'requisition', 'GET', ['id' => $rMum], [], ['Best matches from the pool', 'Blk Openpool', 'Locked — in recruitment with BL Ahmedabad', 'BLK-OPENPOOL', '/candidate?id=' . $cOpen . '"']);
t_ok($has($pg, 'Best matches from the pool') && $has($pg, 'Blk Openpool'), 'B14 · Best matches: Mumbai still sees the name (' . ($pg['error'] ?? $pg['done']) . ')');
t_ok($has($pg, 'Locked — in recruitment with BL Ahmedabad'), 'B15 · *** …with the badge ***');
t_ok(!$has($pg, 'BLK-OPENPOOL') && !$has($pg, '/candidate?id=' . $cOpen . '"'), 'B16 · *** …and nothing else: no code, no Open link ***');
$pg = $drive($uMum, 'candidates', 'GET', [], [], ['Blk Openpool', 'Locked — in recruitment with BL Ahmedabad', 'BLK-OPENPOOL', 'candidate-edit?id=' . $cOpen . '"']);
t_ok($has($pg, 'Blk Openpool') && $has($pg, 'Locked — in recruitment with BL Ahmedabad'), 'B17 · register: name and badge (' . ($pg['error'] ?? $pg['done']) . ')');
t_ok(!$has($pg, 'BLK-OPENPOOL') && !$has($pg, 'candidate-edit?id=' . $cOpen . '"'), 'B18 · *** register: no code, no Edit ***');
$pg = $drive($uMum, 'search', 'GET', ['q' => 'Openpool'], [], ['Blk Openpool', 'Locked — in recruitment with BL Ahmedabad', 'BLK-OPENPOOL']);
t_ok($has($pg, 'Blk Openpool') && $has($pg, 'Locked — in recruitment with BL Ahmedabad') && !$has($pg, 'BLK-OPENPOOL'),
     'B19 · *** search: name and badge only *** (' . ($pg['error'] ?? $pg['done']) . ')');
try {
    $pdo->prepare("INSERT INTO cx_professionals (name, email, mobile, verification_tier, is_active, created_at) VALUES (?,?,?,?,1,?)")
        ->execute(['BL Pro Open', 'bl.pro@pool.test', '9100000001', 'BASIC', date('c')]);
    $mine['pro'][] = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO cx_professionals (name, email, mobile, verification_tier, is_active, created_at) VALUES (?,?,?,?,1,?)")
        ->execute(['BL Pro Declined', 'bl.pro2@pool.test', '9100000005', 'BASIC', date('c')]);
    $mine['pro'][] = (int) $pdo->lastInsertId();
} catch (Throwable $e) {}
$pg = $drive($uMum, 'candidate-pool', 'GET', [], [], ['Blk Openpool', 'Locked — in recruitment with BL Ahmedabad', 'BLK-OPENPOOL', 'BLK-DECLINED']);
t_ok($has($pg, 'Blk Openpool') && $has($pg, 'Locked — in recruitment with BL Ahmedabad') && !$has($pg, 'BLK-OPENPOOL'),
     'B20 · *** the pool screen: name and badge only *** (' . ($pg['error'] ?? $pg['done']) . ')');
t_ok($has($pg, 'BLK-DECLINED'), 'B21 · …while a candidate whose process ended is shown in full');

// ---- C · the lock is the person's -----------------------------------------------------------
t_section('C · another record of the same person is locked with them');
t_eq($badge($cSib), 'Locked — in recruitment with BL Ahmedabad', 'C1 · *** Mumbai\'s own older record of the same person (same e-mail) carries the lock ***');
$r = $drive($uMum, 'candidate-interview', 'POST', ['id' => $cSib],
            ['do' => 'schedule', 'round' => 'L1', 'scheduled_at' => $tomorrow . 'T15:00', 'mode' => 'In person', 'location' => 'Office', 'panel_extra' => 'Outside Panel']);
t_eq($ivCount($cSib), 0, 'C2 · *** Mumbai cannot start a second process on it — Ahmedabad scheduled first ***');
t_ok(strpos($said($r), 'locked — in recruitment with the BL Ahmedabad office') !== false, 'C3 · …told why: ' . $said($r));

// ---- D · point 8 — duplicates -----------------------------------------------------------------
t_section('D · a second record while locked elsewhere is BLOCKED (point 8)');
$before = (int) ops_val("SELECT COUNT(*) FROM candidates");
$r = $drive($uMum, 'candidate-new', 'POST', [],
            ['first_name' => 'Blk', 'last_name' => 'Dupagain', 'designation' => 'BL_FITTER', 'mobile' => '+91 91000 00001', 'dup_ack' => '1'],
            ['Save anyway', 'Possible duplicate', 'BL Ahmedabad office', 'cannot be created']);
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates"), $before, 'D1 · *** same mobile, written differently, even with "Save anyway" ticked: NOT created ***');
t_ok($has($r, 'BL Ahmedabad office') && $has($r, 'cannot be created') && !$has($r, 'Save anyway'),
     'D2 · …the form is drawn again with a plain message naming the office, and no "Save anyway" (' . ($r['error'] ?? $r['done']) . ')');
$r = $drive($uMum, 'candidate-new', 'POST', [],
            ['first_name' => 'Someone', 'last_name' => 'Else', 'designation' => 'BL_FITTER', 'email' => ' BLK.OPEN@pool.TEST', 'dup_ack' => '1']);
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates"), $before, 'D3 · same e-mail (any case): not created either');
$as($uAhm);
t_eq(function_exists('cand_duplicate_lock_block') ? cand_duplicate_lock_block(['mobile' => '9100000001']) : 'x', '',
     'D4 · the office holding the lock is not blocked (its own business; the soft warning still shows)');
//  Not locked → the soft warning, as before.
$r = $drive($uMum, 'candidate-new', 'POST', [],
            ['first_name' => 'Blk', 'last_name' => 'Declinedtwo', 'designation' => 'BL_FITTER', 'mobile' => '9100000005'],
            ['Possible duplicate']);
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates"), $before, 'D5 · a look-alike that is NOT locked: stopped at the soft warning…');
t_ok($has($r, 'Possible duplicate'), 'D6 · …which says "Possible duplicate" (' . ($r['error'] ?? $r['done']) . ')');
$r = $drive($uMum, 'candidate-new', 'POST', [],
            ['first_name' => 'Blk', 'last_name' => 'Declinedtwo', 'designation' => 'BL_FITTER', 'mobile' => '9100000005', 'dup_ack' => '1']);
$newDup = (int) ops_val("SELECT id FROM candidates WHERE last_name='Declinedtwo' ORDER BY id DESC LIMIT 1");
if ($newDup > 0) $mine['cand'][] = $newDup;
t_ok($newDup > 0, 'D7 · *** …and "Save anyway" still creates it *** (' . $said($r) . ')');
//  (removed at once: an unattached look-alike would otherwise crowd Best matches below)
if ($newDup > 0) { try { $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([$newDup]); } catch (Throwable $e) {} }

// ---- E · company-wide roles -------------------------------------------------------------------
t_section('E · Admin and Master Admin are not blocked');
$as($uAdmin);
t_eq(cand_other_office_block($cOpen), '', 'E1 · a company-wide Admin is not blocked');
t_eq(function_exists('cand_duplicate_lock_block') ? cand_duplicate_lock_block(['mobile' => '9100000001']) : 'x', '', 'E2 · nor from creating a record');
$pg = $drive($uAdmin, 'candidate', 'GET', ['id' => $cOpen], [], ['BLKCVMARKER']);
t_ok($has($pg, 'BLKCVMARKER'), 'E3 · *** the Admin opens the locked candidate *** (' . $said($pg) . ')');
$as($uMaster);
t_eq(cand_other_office_block($cOpen), '', 'E4 · nor is the Master Admin');

// ---- F · point 9 — ended processes are back in Best matches ------------------------------------
t_section('F · a process that ended is back in every office\'s Best matches and pool (point 9)');
$pg = $drive($uMum, 'requisition', 'GET', ['id' => $rMum], [], ['Blk Rejected', 'Blk Declined', 'Blk Nojoin', 'Blk Hiredjoined', 'Blk Onboarding',
                                                                  '/candidate?id=' . $cRej . '"']);
t_ok($has($pg, 'Blk Rejected'), 'F1 · *** rejected by Ahmedabad → in Mumbai\'s Best matches ***');
t_ok($has($pg, 'Blk Declined'), 'F2 · *** offer declined → in Best matches ***');
t_ok($has($pg, 'Blk Nojoin'), 'F3 · *** did not join (withdrawn) → in Best matches ***');
t_ok($has($pg, '/candidate?id=' . $cRej . '"'), 'F4 · …in full, with Open');
t_ok(!$has($pg, 'Blk Hiredjoined'), 'F5 · a person hired and joined is not offered');
t_ok(!$has($pg, 'Blk Onboarding'), 'F6 · nor one hired and onboarding');
t_eq($badge($cRej), '', 'F7 · an ended process carries no lock, though an interview was held');
$as($uMum);
t_ok(cand_pool_may_view($cRej), 'F8 · Mumbai may open the rejected candidate');
t_eq($badge($cOnb), 'Locked — onboarding with BL Ahmedabad', 'F9 · onboarding: "Locked — onboarding with BL Ahmedabad"');
t_ok(!cand_pool_may_view($cOnb), 'F10 · *** …and Mumbai cannot open them ***');

// ---- G · switch OFF ---------------------------------------------------------------------------
t_section('G · switch OFF — each office sees only its own');
setting_set('recruit_pool_shared', '0');
$pg = $drive($uMum, 'requisition', 'GET', ['id' => $rMum], [], ['Blk Rejected', 'Blk Openpool']);
t_ok(!$has($pg, 'Blk Rejected') && !$has($pg, 'Blk Openpool'), 'G1 · *** OFF: Ahmedabad\'s candidates are not in Mumbai\'s Best matches ***');
$pg = $drive($uMum, 'candidate', 'GET', ['id' => $cRej], [], ['Blk Rejected']);
t_ok(!$has($pg, 'Blk Rejected'), 'G2 · OFF: Mumbai cannot open Ahmedabad\'s ended candidate');
$as($uMum);
t_ok(strpos(cand_other_office_block($cOpen), $lockMsg) === 0, 'G3 · OFF: the lock still holds');
setting_set('recruit_pool_shared', '1');

// ---- H · the process ends → unlocked ----------------------------------------------------------
t_section('H · Ahmedabad closes the process — the lock is lifted');
$r = $drive($uAhm, 'candidate-stage', 'POST', ['id' => $cOpen], ['to_stage' => 'REJECTED', 'remark' => 'Not suitable for this site']);
t_eq(reqf_classify($cOpen), 'LOST', 'H1 · Ahmedabad rejects them — the process has ended (' . $said($r) . ')');
t_eq($badge($cOpen), '', 'H2 · *** no lock any more ***');
t_eq($badge($cSib), '', 'H3 · …on the other record of the same person too');
$pg = $drive($uMum, 'candidate', 'GET', ['id' => $cOpen], [], ['BLKCVMARKER']);
t_ok($has($pg, 'BLKCVMARKER'), 'H4 · *** Mumbai opens the full candidate again *** (' . $said($pg) . ')');
$pg = $drive($uMum, 'requisition', 'GET', ['id' => $rMum], [], ['/candidate?id=' . $cOpen . '"']);
t_ok($has($pg, '/candidate?id=' . $cOpen . '"'), 'H5 · …and finds them in its Best matches, with Open');
$as($uMum);
t_eq(function_exists('cand_duplicate_lock_block') ? cand_duplicate_lock_block(['mobile' => '9100000001']) : 'x', '', 'H6 · a new record would now get only the soft warning');

// ---- I · docs ---------------------------------------------------------------------------------
t_section('I · the docs say the same as the code');
$d2 = (string) @file_get_contents(dirname($root) . '/docs/02-permission-matrix.md');
$d3 = (string) @file_get_contents(dirname($root) . '/docs/03-object-lifecycles.md');
t_ok(strpos($d2, 'first interview') !== false && strpos($d2, 'cand_duplicate_lock_block') !== false, 'I1 · docs/02 footnote 8 records the lock at the first interview and the duplicate block');
t_ok(strpos($d3, 'first interview') !== false, 'I2 · docs/03 Candidate records when the lock starts');

// ---- tidy: every row this file committed --------------------------------------------------------
$ids = $mine['cand'];
foreach (ops_all("SELECT id FROM candidates WHERE cand_code LIKE 'BLK-%' OR last_name IN ('Dupagain','Else','Declinedtwo')") ?: [] as $x) $ids[] = (int) $x['id'];
$ids = array_values(array_unique(array_filter($ids)));
$del = function ($sql, array $a = []) use ($pdo) { try { $pdo->prepare($sql)->execute($a); } catch (Throwable $e) {} };
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    foreach (['interviews', 'candidate_events', 'candidate_docs', 'job_offers', 'salary_structures', 'hr_discussions', 'candidate_stage_data', 'cx_identity_link']
             as $t) $del("DELETE FROM $t WHERE candidate_id IN ($ph)", $ids);
    $del("DELETE FROM recruiter_assignments WHERE entity_id IN ($ph) AND subject LIKE 'CAND%'", $ids);
    $del("DELETE FROM activities WHERE entity_kind='CANDIDATE' AND entity_id IN ($ph)", $ids);
    $del("DELETE FROM candidates WHERE id IN ($ph)", $ids);
}
foreach ($mine['pro'] as $p) { $del("DELETE FROM cx_identity_link WHERE professional_id=?", [$p]); $del("DELETE FROM cx_professionals WHERE id=?", [$p]); }
foreach ($mine['req'] as $q) { $del("DELETE FROM recruiter_assignments WHERE entity_id=? AND subject LIKE 'REQ%'", [$q]); $del("DELETE FROM requisitions WHERE id=?", [$q]); }
foreach ($mine['user'] as $u) $del("DELETE FROM users WHERE id=?", [$u]);
foreach ($mine['office'] as $o) $del("DELETE FROM offices WHERE id=?", [$o]);
if ($blPoolWas === null) $del("DELETE FROM settings WHERE skey='recruit_pool_shared'");
else setting_set('recruit_pool_shared', (string) $blPoolWas);
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE cand_code LIKE 'BLK-%'"), 0, 'tidy · no candidate row of this file is left behind');
$_SESSION = $blOrig; current_user(true); ua(true);
