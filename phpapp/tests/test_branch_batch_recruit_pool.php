<?php
// ============================================================================
//  BRANCH BATCH B6 — the company's own candidate pool, shared by every office
//  (owner's final answers, 16 Nov 2026; QA-REPORTS/V51/27 Part 0 item 0.5,
//  Part 0b, Part 3 row B6; DEF-REC-003).
//
//  Two offices, BB6 Ahmedabad and BB6 Mumbai. Every probe drives the PRODUCTION
//  route through the dispatcher as a real signed-in person (tests/
//  _sales_route_worker.php — every write route ends in redirect(), which exits),
//  or calls the production function a route calls.
//
//   A · DEF-REC-003 — the Recruitment home "Today" and "Risks" panels are each
//       office's own to-do list: a Mumbai candidate is not on Ahmedabad's.
//   B · the pool and "Best matches from the pool" are central: every office sees
//       every candidate who has not joined, with the in-process badge.
//   C · the Master Admin switch "Offices can see each other's candidate pool",
//       ON by default; OFF = each office sees only its own.
//   D · the hard block: no other office can start anything with a candidate
//       whose process is with another office — and the refusal names the office.
//   E · when the process ends the candidate is back in the pool for everyone.
//   F · onboarding: "Onboarding in process — <office>".
//
//  BATCH 2 (owner, 17 Nov 2026 — QA-REPORTS/V51/29 C, points 7–9, Q2) changed
//  these expectations, and only these: the badge now reads "Locked — in
//  recruitment with <office>" / "Locked — onboarding with <office>"; a locked
//  candidate can no longer be OPENED by another office (E1, G3 — was "can open
//  and see the badge"); the refusal reads "<Name> is locked — in recruitment
//  with the <office> office …"; in the pool list another office sees only the
//  name and the badge (H1). The lock itself — from the first interview, not
//  on a link — is proved in tests/test_batch2_recruit_lock.php. This file also
//  now removes every row it commits.
// ============================================================================
t_section('Branch batch B6 — the shared candidate pool, the in-process badge and the other-office block');

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
$b6orig = $_SESSION ?? [];
$b6poolWas = function_exists('setting_get') ? setting_get('recruit_pool_shared', null) : null;
if ($b6poolWas !== null) setting_set('recruit_pool_shared', '1');

$office = function ($name) use ($pdo) {
    $pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute([$name]);
    return (int) $pdo->lastInsertId();
};
$AHM = $office('BB6 Ahmedabad'); $MUM = $office('BB6 Mumbai');
$user = function ($un, $role, $off, $super = 0) use ($pdo) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id, scope_offices)
                   VALUES (?,?,?,?,?,1,?,?,'')")->execute([$un, ucfirst($un), 'Probe', '', $role, $super, $off]);
    return (int) $pdo->lastInsertId();
};
$uAhm    = $user('bb6_coord_ahm', 'COORDINATOR', $AHM);
$uMum    = $user('bb6_coord_mum', 'COORDINATOR', $MUM);
$uAdmin  = $user('bb6_admin',     'ADMIN',       $AHM);     // company-wide by role
$uMaster = $user('bb6_master',    'MASTER_ADMIN', $AHM, 1);
$as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

$req = function ($office, $code) use ($pdo) {
    $pdo->prepare("INSERT INTO requisitions (req_code, office_id, sbu, designation, status, quantity, created_at)
                   VALUES (?,?,?,?,?,?,?)")->execute([$code, $office, '', 'BB6_WELDER', 'OPEN', 5, date('c')]);
    return (int) $pdo->lastInsertId();
};
$rAhm = $req($AHM, 'REQ-BB6-AHM'); $rMum = $req($MUM, 'REQ-BB6-MUM');
$ago20 = date('Y-m-d', strtotime('-20 days'));
$cand = function ($first, $last, $reqId, $stage, array $x = []) use ($pdo, $ago20) {
    $d = array_merge(['cand_code' => 'BB6-' . strtoupper($last), 'first_name' => $first, 'last_name' => $last, 'stage' => $stage,
                      'requisition_id' => $reqId, 'designation' => 'BB6_WELDER', 'sbu' => '', 'mobile' => '',
                      'cv_received_date' => $ago20, 'decided_at' => '', 'interview_required' => 0, 'interview_date' => '',
                      'joined_at' => null, 'created_at' => date('c')], $x);
    $pdo->prepare("INSERT INTO candidates (cand_code, first_name, last_name, stage, requisition_id, designation, sbu, mobile,
                   cv_received_date, decided_at, interview_required, interview_date, joined_at, created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute(array_values($d));
    return (int) $pdo->lastInsertId();
};
$tomorrow  = date('Y-m-d', strtotime('+1 day'));
$yesterday = date('Y-m-d', strtotime('-1 day'));
$cAhmWait  = $cand('Bbsix', 'Ahmwait',   $rAhm, 'RECEIVED', ['mobile' => '9000000101']);
$cMumWait  = $cand('Bbsix', 'Mumwait',   $rMum, 'RECEIVED');
$cMumIv    = $cand('Bbsix', 'Mumactive', $rMum, 'INTERVIEW', ['interview_required' => 1, 'interview_date' => $tomorrow, 'mobile' => '9000000102']);
$cMumLate  = $cand('Bbsix', 'Mumlate',   $rMum, 'INTERVIEW', ['interview_required' => 1, 'interview_date' => $yesterday]);
$cMumLost  = $cand('Bbsix', 'Mumlost',   $rMum, 'REJECTED', ['decided_at' => date('c')]);
$cMumOnb   = $cand('Bbsix', 'Mumonboard', $rMum, 'ACCEPTED', ['decided_at' => date('c')]);
$cMumJoin  = $cand('Bbsix', 'Mumjoined', $rMum, 'ACCEPTED', ['decided_at' => date('c'), 'joined_at' => date('c')]);
$cFree     = $cand('Bbsix', 'Unattached', null, 'RECEIVED');
$stageOf = fn($id) => (string) ops_val("SELECT stage FROM candidates WHERE id=?", [$id]);
$reqOf   = fn($id) => (int) ops_val("SELECT COALESCE(requisition_id,0) FROM candidates WHERE id=?", [$id]);
$names   = fn(array $rows) => array_map(fn($r) => trim((string) ($r['nm'] ?? '')), $rows);

// ---- A · DEF-REC-003 — Today and Risks are each office's own --------------------------------
t_section('A · the Recruitment home "Today" and "Risks" panels are each office\'s own (DEF-REC-003)');
$as($uAhm);
$d = recruit_data();
t_ok(in_array('Bbsix Ahmwait', $names($d['t_followups'] ?? []), true), 'A1 · Ahmedabad\'s own waiting candidate is on its "Today" follow-ups');
t_ok(!in_array('Bbsix Mumwait', $names($d['t_followups'] ?? []), true), 'A2 · *** a Mumbai candidate is NOT on Ahmedabad\'s "Today" follow-ups ***');
t_ok(!in_array('Bbsix Mumactive', $names($d['t_interviews'] ?? []), true), 'A3 · a Mumbai interview is NOT on Ahmedabad\'s "Today" interviews');
t_ok(!in_array('Bbsix Mumwait', $names($d['r_dormant'] ?? []), true), 'A4 · *** a Mumbai candidate is NOT on Ahmedabad\'s "Risks" (dormant) ***');
t_ok(in_array('Bbsix Ahmwait', $names($d['r_dormant'] ?? []), true), 'A5 · …while Ahmedabad\'s own dormant candidate is');
t_ok(!in_array('Bbsix Mumlate', $names($d['r_interviews'] ?? []), true), 'A6 · a Mumbai overdue interview is NOT on Ahmedabad\'s "Risks"');
$as($uMum);
$d = recruit_data();
t_ok(!in_array('Bbsix Ahmwait', $names($d['t_followups'] ?? []), true), 'A7 · and the reverse: an Ahmedabad candidate is not on Mumbai\'s "Today"');
t_ok(in_array('Bbsix Mumwait', $names($d['t_followups'] ?? []), true) && in_array('Bbsix Mumlate', $names($d['r_interviews'] ?? []), true),
     'A8 · Mumbai sees its own follow-up and its own overdue interview');
$as($uAdmin);
$d = recruit_data();
t_ok(in_array('Bbsix Ahmwait', $names($d['t_followups'] ?? []), true) && in_array('Bbsix Mumwait', $names($d['t_followups'] ?? []), true),
     'A9 · a company-wide Admin sees both offices\' to-do lists');
$pg = $drive($uAhm, 'recruitment', 'GET', [], [], ['Bbsix Mumwait', 'Bbsix Ahmwait']);
t_ok(!$has($pg, 'Bbsix Mumwait'), 'A10 · *** the Recruitment home drawn for Ahmedabad does not name the Mumbai candidate *** (' . ($pg['error'] ?? $pg['done']) . ')');

// ---- B · the switch, ON by default -------------------------------------------------------
t_section('B · "Offices can see each other\'s candidate pool" is ON by default');
t_ok(function_exists('rpool_shared') && rpool_shared() === true, 'B1 · with nothing saved the pool is shared');

// ---- C · Best matches + the badge ---------------------------------------------------------
t_section('C · "Best matches from the pool" is central and shows the in-process badge');
$pg = $drive($uAhm, 'requisition', 'GET', ['id' => $rAhm], [], ['Bbsix Mumactive', 'Bbsix Unattached', 'Locked — in recruitment with BB6 Mumbai', 'Bbsix Mumjoined', 'Best matches from the pool']);
t_ok($has($pg, 'Best matches from the pool'), 'C1 · arranged: the Ahmedabad requisition shows its pool panel (' . ($pg['error'] ?? $pg['done']) . ')');
t_ok($has($pg, 'Bbsix Mumactive'), 'C2 · a Mumbai candidate who fits is offered to Ahmedabad (switch ON)');
t_ok($has($pg, 'Locked — in recruitment with BB6 Mumbai'), 'C3 · *** …with the badge "Locked — in recruitment with BB6 Mumbai" ***');
t_ok($has($pg, 'Bbsix Unattached'), 'C4 · an unattached candidate is offered to every office');
t_ok(!$has($pg, 'Bbsix Mumjoined'), 'C5 · a person who has joined is not a pool candidate');
$badge = function_exists('cand_process_badge') ? cand_process_badge($cMumIv) : '';
t_eq($badge, 'Locked — in recruitment with BB6 Mumbai', 'C6 · the badge names the office (batch 2: the owner\'s words, no stage)');
t_eq(function_exists('cand_process_badge') ? cand_process_badge($cMumLost) : 'x', '', 'C7 · a closed-out candidate carries no badge');
t_eq(function_exists('cand_process_badge') ? cand_process_badge($cFree) : 'x', '', 'C8 · an unattached candidate carries no badge');

// ---- D · the switch OFF ------------------------------------------------------------------
t_section('D · switch OFF — each office sees only its own pool');
setting_set('recruit_pool_shared', '0');
t_ok(function_exists('rpool_shared') && rpool_shared() === false, 'D1 · the saved answer is read');
$pg = $drive($uAhm, 'requisition', 'GET', ['id' => $rAhm], [], ['Bbsix Mumactive', 'Bbsix Mumwait', 'Bbsix Unattached']);
t_ok(!$has($pg, 'Bbsix Mumactive') && !$has($pg, 'Bbsix Mumwait'), 'D2 · *** OFF: Mumbai candidates are not offered to Ahmedabad ***');
t_ok($has($pg, 'Bbsix Unattached'), 'D3 · OFF: an unattached candidate still belongs to nobody and stays visible');
$pg = $drive($uAhm, 'candidate', 'GET', ['id' => $cMumLost], [], ['Bbsix Mumlost']);
t_ok(!$has($pg, 'Bbsix Mumlost'), 'D4 · OFF: another office\'s closed-out candidate cannot be opened');
setting_set('recruit_pool_shared', '1');

// ---- E · the candidate page and the hard block ---------------------------------------------
t_section('E · the hard block — no other office can start anything');
$msg = 'Bbsix Mumactive is locked — in recruitment with the BB6 Mumbai office. Only that office can open or work on this';
$pg = $drive($uAhm, 'candidate', 'GET', ['id' => $cMumIv], [], ['9000000102']);
t_ok(!$has($pg, '9000000102') && strpos($said($pg), $msg) !== false,
     'E1 · *** batch 2: Ahmedabad can NOT open the locked Mumbai candidate — refused naming the office *** (' . $said($pg) . ')');

$r = $drive($uAhm, 'candidate-interview', 'POST', ['id' => $cMumIv],
            ['do' => 'schedule', 'round' => 'L1', 'scheduled_at' => $tomorrow . 'T10:00', 'mode' => 'In person', 'location' => 'Office', 'panel_extra' => 'Outside Panel']);
t_eq((int) ops_val("SELECT COUNT(*) FROM interviews WHERE candidate_id=?", [$cMumIv]), 0, 'E2 · *** Ahmedabad cannot schedule an interview — nothing written ***');
t_ok(strpos($said($r), $msg) !== false, 'E3 · …and is told why, naming the office: ' . $said($r));

$r = $drive($uAhm, 'candidate-stage', 'POST', ['id' => $cMumIv], ['to_stage' => 'SHORTLISTED', 'remark' => 'x']);
t_eq($stageOf($cMumIv), 'INTERVIEW', 'E4 · Ahmedabad cannot move the stage');
t_ok(strpos($said($r), $msg) !== false, 'E5 · …the refusal names the office: ' . $said($r));
$r = $drive($uAhm, 'candidate-stage', 'POST', ['id' => $cMumIv], ['to_stage' => 'REJECTED', 'remark' => 'not for us']);
t_eq($stageOf($cMumIv), 'INTERVIEW', 'E6 · nor close them out');

$r = $drive($uAhm, 'candidate-offer', 'POST', ['id' => $cMumIv], ['do' => 'offer_create', 'ctc' => '100000']);
t_eq((int) ops_val("SELECT COUNT(*) FROM job_offers WHERE candidate_id=?", [$cMumIv]), 0, 'E7 · *** Ahmedabad cannot draft an offer ***');
t_ok(strpos($said($r), $msg) !== false, 'E8 · …and is told why: ' . $said($r));

$r = $drive($uAhm, 'candidate-doc', 'POST', ['id' => $cMumIv], ['do' => 'add', 'doc_type' => 'Other']);
t_eq((int) ops_val("SELECT COUNT(*) FROM candidate_docs WHERE candidate_id=?", [$cMumIv]), 0, 'E9 · Ahmedabad cannot add a document');

$r = $drive($uAhm, 'candidate-edit', 'POST', ['id' => $cMumIv],
            ['first_name' => 'Bbsix', 'last_name' => 'Mumactive', 'designation' => 'BB6_WELDER', 'requisition_id' => (string) $rAhm]);
t_eq($reqOf($cMumIv), $rMum, 'E10 · *** Ahmedabad cannot link them to its own requisition ***');
t_ok(strpos($said($r), $msg) !== false, 'E11 · …refused with the office named: ' . $said($r));

if (function_exists('rasg_assign')) {
    $as($uAhm);
    $res = rasg_assign('CAND_RECRUITER', $cMumIv, $uAhm, ['expect' => null, 'source' => 'test', 'requisition_id' => $rAhm]);
    t_eq($res['code'], 'IN_PROCESS_ELSEWHERE', 'E12 · *** the assignment door refuses to give an Ahmedabad recruiter a Mumbai-in-process candidate ***');
}

//  The guard function itself — asked by every write path.
$as($uAhm);
$blk = function_exists('cand_other_office_block') ? cand_other_office_block($cMumIv) : '';
t_ok(strpos($blk, $msg) === 0, 'E13 · cand_other_office_block() says it in the owner\'s words: ' . $blk);
$as($uMum);
t_eq(function_exists('cand_other_office_block') ? cand_other_office_block($cMumIv) : 'x', '', 'E14 · the office running the process is not blocked');
$as($uAdmin);
t_eq(function_exists('cand_other_office_block') ? cand_other_office_block($cMumIv) : 'x', '', 'E15 · a company-wide Admin covers every office and is not blocked');
$as($uMaster);
t_eq(function_exists('cand_other_office_block') ? cand_other_office_block($cMumIv) : 'x', '', 'E16 · nor is the Master Admin');

$r = $drive($uMum, 'candidate-interview', 'POST', ['id' => $cMumIv],
            ['do' => 'schedule', 'round' => 'L1', 'scheduled_at' => $tomorrow . 'T11:00', 'mode' => 'In person', 'location' => 'Office', 'panel_extra' => 'Outside Panel']);
t_eq((int) ops_val("SELECT COUNT(*) FROM interviews WHERE candidate_id=?", [$cMumIv]), 1, 'E17 · Mumbai, whose process it is, schedules the interview (' . $said($r) . ')');

//  Every candidate write route asks the one guard (pinned, so a new route cannot forget it).
$src = function ($f) use ($root) { return preg_replace('~^\s*//.*$~m', '', (string) file_get_contents($root . '/lib/' . $f)); };
$fn  = function ($s, $name) { $i = strpos($s, "function $name("); if ($i === false) return ''; $j = strpos($s, "\nfunction ", $i + 10); return substr($s, $i, $j === false ? null : $j - $i); };
foreach ([['recruit_iv.php', 'ops_candidate_interview'], ['recruit_iv.php', 'ops_candidate_doc'], ['recruit_offer.php', 'ops_candidate_offer'],
          ['recruitpipe.php', 'ops_recruit_candidate_flow'], ['recruitpipe.php', 'ops_recruit_candidate_stage'],
          ['candreview.php', 'ops_candidate_review'], ['ops.php', 'cand_scope_gate'],
          ['recruit_iv.php', 'iv_schedule'], ['recruit_offer.php', 'offer_create'], ['recruitpipe.php', 'recruitpipe_cand_goto'],
          ['recruit_assign.php', 'rasg_may_touch']] as [$f, $name]) {
    $body = $fn($src($f), $name);
    t_ok($body !== '' && strpos($body, 'cand_other_office_') !== false, "E18 · $name() asks the other-office guard");
}

// ---- F · the process ended: back in the pool -----------------------------------------------
t_section('F · when the process ends, the candidate is back in the pool for everyone');
$pg = $drive($uAhm, 'candidate', 'GET', ['id' => $cMumLost], [], ['Bbsix Mumlost', 'in process']);
t_ok($has($pg, 'Bbsix Mumlost'), 'F1 · *** Ahmedabad can open the candidate Mumbai rejected *** (' . $said($pg) . ')');
$r = $drive($uAhm, 'candidate-stage', 'POST', ['id' => $cMumLost], ['to_stage' => 'SHORTLISTED', 'remark' => 'x']);
t_eq($stageOf($cMumLost), 'REJECTED', 'F2 · but does not restart them on MUMBAI\'s requisition');
t_ok(strpos($said($r), 'link them to one of your own office') !== false, 'F3 · …and is told how to start: ' . $said($r));
$r = $drive($uAhm, 'candidate-edit', 'POST', ['id' => $cMumLost],
            ['first_name' => 'Bbsix', 'last_name' => 'Mumlost', 'designation' => 'BB6_WELDER', 'requisition_id' => (string) $rAhm]);
t_eq($reqOf($cMumLost), $rAhm, 'F4 · *** Ahmedabad links them to its own requisition — the process starts with Ahmedabad *** (' . $said($r) . ')');
$as($uAhm);
t_eq(function_exists('cand_other_office_block') ? cand_other_office_block($cMumLost) : 'x', '', 'F5 · it is now Ahmedabad\'s to work on');

// ---- G · onboarding ------------------------------------------------------------------------
t_section('G · onboarding — "Locked — onboarding with <office>"');
t_eq(function_exists('cand_process_badge') ? cand_process_badge($cMumOnb) : '', 'Locked — onboarding with BB6 Mumbai', 'G1 · the onboarding badge');
$as($uAhm);
t_ok(function_exists('cand_other_office_block') && strpos(cand_other_office_block($cMumOnb), 'is locked — onboarding with the BB6 Mumbai office') !== false,
     'G2 · and other offices are refused, naming the office');
t_ok(function_exists('cand_pool_may_view') && !cand_pool_may_view($cMumOnb), 'G3 · batch 2: other offices can NOT open them (they see the name and the badge in lists)');
t_ok(function_exists('cand_pool_may_view') && !cand_pool_may_view($cMumJoin), 'G4 · a person who has JOINED Mumbai stays Mumbai\'s record');
t_eq(function_exists('cand_process_badge') ? cand_process_badge($cMumJoin) : 'x', '', 'G5 · and carries no pool badge');

// ---- H · the candidate pool screen ---------------------------------------------------------
t_section('H · the candidate pool screen follows the same rule');
try {
    foreach ([['BB6 Pro One', '9000000102', 'bb6.one@pool.test'], ['BB6 Pro Two', '9000000101', 'bb6.two@pool.test']] as [$pn, $pm, $pe])
        $pdo->prepare("INSERT INTO cx_professionals (name, email, mobile, verification_tier, is_active, created_at) VALUES (?,?,?,?,1,?)")
            ->execute([$pn, $pe, $pm, 'BASIC', date('c')]);
} catch (Throwable $e) {}
$pg = $drive($uAhm, 'candidate-pool', 'GET', [], [], ['Bbsix Mumactive', 'BB6-MUMACTIVE', 'BB6-AHMWAIT', 'Locked — in recruitment with BB6 Mumbai']);
t_ok($has($pg, 'Bbsix Mumactive') && $has($pg, 'BB6-AHMWAIT'), 'H1 · ON: the pool lists both offices\' candidates (' . ($pg['error'] ?? $pg['done']) . ')');
t_ok($has($pg, 'Locked — in recruitment with BB6 Mumbai') && !$has($pg, 'BB6-MUMACTIVE'), 'H2 · *** with the lock badge — and, batch 2, only the name and the badge ***');
setting_set('recruit_pool_shared', '0');
$pg = $drive($uAhm, 'candidate-pool', 'GET', [], [], ['Bbsix Mumactive', 'BB6-AHMWAIT']);
t_ok(!$has($pg, 'Bbsix Mumactive') && $has($pg, 'BB6-AHMWAIT'), 'H3 · *** OFF: the pool lists only the office\'s own ***');
setting_set('recruit_pool_shared', '1');

// ---- I · the switch belongs to the Master Admin --------------------------------------------
t_section('I · only the Master Admin may change the switch');
$pg = $drive($uAdmin, 'recruit-pipelines', 'GET', [], [], ["Offices can see each other"]);
t_ok(!$has($pg, "Offices can see each other"), 'I1 · an Admin does not see the switch');
$drive($uAdmin, 'recruit-pipelines', 'POST', [], ['do' => 'pool_shared', 'pool_shared' => '0']);
t_eq((string) ops_val("SELECT svalue FROM settings WHERE skey='recruit_pool_shared'"), '1', 'I2 · *** an Admin who posts it anyway changes nothing ***');
$pg = $drive($uMaster, 'recruit-pipelines', 'GET', [], [], ["Offices can see each other"]);
t_ok($has($pg, "Offices can see each other"), 'I3 · the Master Admin sees the switch on Hiring workflows (' . ($pg['error'] ?? $pg['done']) . ')');
$r = $drive($uMaster, 'recruit-pipelines', 'POST', [], ['do' => 'pool_shared', 'pool_shared' => '0']);
t_eq((string) ops_val("SELECT svalue FROM settings WHERE skey='recruit_pool_shared'"), '0', 'I4 · the Master Admin turns it off (' . $said($r) . ')');
$drive($uMaster, 'recruit-pipelines', 'POST', [], ['do' => 'pool_shared', 'pool_shared' => '1']);
t_eq((string) ops_val("SELECT svalue FROM settings WHERE skey='recruit_pool_shared'"), '1', 'I5 · …and on again');

// ---- J · the docs say the same as the code -------------------------------------------------
t_section('J · the permission matrix records the switch and the block');
$doc = (string) @file_get_contents(dirname($root) . '/docs/02-permission-matrix.md');
t_ok(strpos($doc, "Offices can see each other's candidate pool") !== false && strpos($doc, 'cand_other_office_block') !== false,
     'J1 · docs/02 Hiring footnote 8 describes the switch and the guard');

// ---- tidy: every row this file committed (batch 2 — leftover rows broke other tests) ----------
$b6del = function ($sql, array $a = []) use ($pdo) { try { $pdo->prepare($sql)->execute($a); } catch (Throwable $e) {} };
$b6ids = array_map('intval', array_column(ops_all("SELECT id FROM candidates WHERE cand_code LIKE 'BB6-%'") ?: [], 'id'));
if ($b6ids) {
    $ph = implode(',', array_fill(0, count($b6ids), '?'));
    foreach (['interviews', 'candidate_events', 'candidate_docs', 'job_offers', 'salary_structures', 'hr_discussions', 'candidate_stage_data', 'cx_identity_link']
             as $t) $b6del("DELETE FROM $t WHERE candidate_id IN ($ph)", $b6ids);
    $b6del("DELETE FROM recruiter_assignments WHERE entity_id IN ($ph) AND subject LIKE 'CAND%'", $b6ids);
    $b6del("DELETE FROM activities WHERE entity_kind='CANDIDATE' AND entity_id IN ($ph)", $b6ids);
    $b6del("DELETE FROM candidates WHERE id IN ($ph)", $b6ids);
}
foreach (ops_all("SELECT id FROM cx_professionals WHERE name IN ('BB6 Pro One','BB6 Pro Two')") ?: [] as $p) {
    $b6del("DELETE FROM cx_identity_link WHERE professional_id=?", [(int) $p['id']]);
    $b6del("DELETE FROM cx_professionals WHERE id=?", [(int) $p['id']]);
}
foreach ([$rAhm, $rMum] as $q) { $b6del("DELETE FROM recruiter_assignments WHERE entity_id=? AND subject LIKE 'REQ%'", [$q]); $b6del("DELETE FROM requisitions WHERE id=?", [$q]); }
foreach ([$uAhm, $uMum, $uAdmin, $uMaster] as $u) $b6del("DELETE FROM users WHERE id=?", [$u]);
foreach ([$AHM, $MUM] as $o) $b6del("DELETE FROM offices WHERE id=?", [$o]);
setting_set('recruit_pool_shared', $b6poolWas === null ? '1' : (string) $b6poolWas);
$_SESSION = $b6orig; current_user(true); ua(true);
