<?php
// ============================================================================
//  Branch batch B7 — the Marketplace inside the company (owner, 16 Nov 2026).
//
//  DEF-MKT-006 (P2): a marketplace requirement carried no office, so a Mumbai
//  coordinator could award, book and bill a posting Ahmedabad had made. docs/02
//  (owner, 13 Oct 2026): discovery is not office-scoped, every ACTION keeps the
//  record's own scope. Now a posting remembers the poster's office; every office
//  still SEES it and its applicants, only that office (and company-wide roles)
//  may ACT on it. A posting with no office stays everyone's on the desk.
//
//  Outsiders see nothing of this: the professionals'
//  job board and the client portal's closing-date panel are checked unchanged.
//
//  Drives the real routes through tests/_sales_route_worker.php (a separate
//  process — every write route ends in redirect(), which exits).
// ============================================================================
t_section('Marketplace — a posting belongs to the office that made it; every office still sees it (DEF-MKT-006)');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'GET', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string) ($f['text'] ?? $f['msg'] ?? (is_string($f) ? $f : '')), (array) ($r['flash'] ?? [])));
$as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); if (function_exists('ua')) ua(true); };
$nobody = function () { unset($_SESSION['uid']); current_user(true); if (function_exists('ua')) ua(true); };

$pdo = db();
connect_market_migrate();
$office = function ($name) use ($pdo) { $pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute([$name]); return (int) $pdo->lastInsertId(); };
$A = $office('BB7 Ahmedabad'); $B = $office('BB7 Mumbai');
$mk = function ($un, $first, $role, $off) use ($pdo) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, $first, 'Bb7', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$CA = $mk('bb7_coord_a', 'Asha',  'COORDINATOR', $A);        // Ahmedabad coordinator
$CB = $mk('bb7_coord_b', 'Bharat', 'COORDINATOR', $B);       // Mumbai coordinator
$BD = $mk('bb7_dir',     'Dev',   'BUSINESS_DIRECTOR', $A);  // company-wide
$req = fn($id) => ops_one("SELECT * FROM cx_requirements WHERE id=?", [(int) $id]);

t_ok(in_array('office_id', t_columns('cx_requirements'), true), 'cx_requirements has an office column (additive migration)');

// ---- posted from the poster's home office, never from the form --------------------------------
$r = $drive($CA, 'connect-requirements', 'POST', [], ['action' => 'create_post', 'title' => 'BB7 Need 2 NDT Level II', 'location' => 'Hazira',
        'positions' => 2, 'office_id' => $B]);
$RA = (int) ops_val("SELECT id FROM cx_requirements WHERE title='BB7 Need 2 NDT Level II'");
t_ok($RA > 0, 'arranged: the Ahmedabad coordinator posts a requirement (' . $said($r) . ')');
t_eq((int) ($req($RA)['office_id'] ?? 0), $A, 'it belongs to the poster\'s home office — a forged office_id in the form is ignored');

$appX = cx_application_add($RA, ['applicant_name' => 'BB7 Pro One', 'proposed_rate' => 9000]);
cx_application_transition($appX, 'SHORTLISTED');
cx_requirement_transition($RA, 'SHORTLISTING');

// ---- every office SEES it --------------------------------------------------------------------
$pg = $drive($CB, 'connect-requirement', 'GET', ['id' => $RA], [], ['BB7 Need 2 NDT Level II', 'BB7 Pro One', '🤝 Award', 'You can see this requirement', 'value="req_transition"', 'value="apply"']);
t_ok(!empty($pg['has']['BB7 Need 2 NDT Level II']) && !empty($pg['has']['BB7 Pro One']), 'Mumbai opens the Ahmedabad posting and sees its applicants (discovery stays company-wide)');
t_ok(empty($pg['has']['🤝 Award']) && empty($pg['has']['value="req_transition"']) && empty($pg['has']['value="apply"']),
    '…but gets no Award, status or add-applicant buttons');
t_ok(!empty($pg['has']['You can see this requirement']), '…and is told plainly why');
$pg = $drive($CB, 'connect-requirements', 'GET', [], [], ['BB7 Need 2 NDT Level II', 'BB7 Ahmedabad']);
t_ok(!empty($pg['has']['BB7 Need 2 NDT Level II']) && !empty($pg['has']['BB7 Ahmedabad']), 'the Marketplace board lists it for Mumbai too, with its office');
$pg = $drive($CA, 'connect-requirement', 'GET', ['id' => $RA], [], ['🤝 Award', 'You can see this requirement']);
t_ok(!empty($pg['has']['🤝 Award']) && empty($pg['has']['You can see this requirement']), 'control: Ahmedabad sees the Award button');

// ---- only the posting office ACTS on it ----------------------------------------------------------
$r = $drive($CB, 'connect-requirement', 'POST', [], ['id' => $RA, 'action' => 'award', 'application_id' => $appX]);
t_eq(strtoupper((string) $req($RA)['status']), 'SHORTLISTING', 'Mumbai cannot award the Ahmedabad posting');
t_ok(strpos($said($r), 'belongs to the BB7 Ahmedabad office') !== false, '…and gets a plain refusal (' . $said($r) . ')');
$drive($CB, 'connect-requirement', 'POST', [], ['id' => $RA, 'action' => 'req_transition', 'to' => 'CLOSED']);
t_eq(strtoupper((string) $req($RA)['status']), 'SHORTLISTING', 'Mumbai cannot close it');
$drive($CB, 'connect-requirement', 'POST', [], ['id' => $RA, 'action' => 'req_edit', 'title' => 'BB7 hijacked']);
t_eq((string) $req($RA)['title'], 'BB7 Need 2 NDT Level II', 'Mumbai cannot change its details');
$drive($CB, 'connect-requirement', 'POST', [], ['id' => $RA, 'action' => 'apply', 'applicant_name' => 'BB7 Mumbai pick']);
t_eq((int) ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=?", [$RA]), 1, 'Mumbai cannot add applicants to it');

$r = $drive($CA, 'connect-requirement', 'POST', [], ['id' => $RA, 'action' => 'award', 'application_id' => $appX]);
t_eq(strtoupper((string) $req($RA)['status']), 'AWARDED', 'control: Ahmedabad awards its own posting (' . $said($r) . ')');
$drive($CB, 'connect-requirement', 'POST', [], ['id' => $RA, 'action' => 'book_engagement', 'basis' => 'MAN_DAYS', 'rate' => 9000, 'quantity' => 10, 'rate_unit' => 'day']);
t_eq((int) ops_val("SELECT COUNT(*) FROM cx_engagements WHERE requirement_id=?", [$RA]), 0, 'Mumbai cannot record the booking');
$drive($CA, 'connect-requirement', 'POST', [], ['id' => $RA, 'action' => 'book_engagement', 'basis' => 'MAN_DAYS', 'rate' => 9000, 'quantity' => 10, 'rate_unit' => 'day']);
t_eq((int) ops_val("SELECT COUNT(*) FROM cx_engagements WHERE requirement_id=?", [$RA]), 1, 'control: Ahmedabad records the booking');
//  Now that it is booked, billing and deployment are possible — but not for Mumbai.
$drive($CB, 'connect-requirement', 'POST', [], ['id' => $RA, 'action' => 'send_to_billing']);
t_ok(!connect_engagement_billable_row($RA), 'Mumbai cannot send the booked job to billing');
$drive($CB, 'connect-requirement', 'POST', [], ['id' => $RA, 'action' => 'deploy_to_ops']);
t_ok(!connect_deploy_row_for_requirement($RA), 'Mumbai cannot deploy it to Operations');
$r = $drive($CA, 'connect-requirement', 'POST', [], ['id' => $RA, 'action' => 'send_to_billing']);
t_ok((bool) connect_engagement_billable_row($RA), 'control: Ahmedabad sends it to billing (' . $said($r) . ')');

// ---- a company-wide role acts on any office's posting -------------------------------------------------
$RB = cx_requirement_create(['title' => 'BB7 Mumbai posting'], false);
cx_requirement_set_office($RB, $B);
$drive($BD, 'connect-requirement', 'POST', [], ['id' => $RB, 'action' => 'req_transition', 'to' => 'OPEN']);
t_eq(strtoupper((string) $req($RB)['status']), 'OPEN', 'the Business Director (company-wide) acts on a Mumbai posting');
$drive($CA, 'connect-requirement', 'POST', [], ['id' => $RB, 'action' => 'req_transition', 'to' => 'CANCELLED']);
t_eq(strtoupper((string) $req($RB)['status']), 'OPEN', '…while the Ahmedabad coordinator cannot');

// ---- a form for one's own posting cannot reach another posting's records -------------------------------
$appB = cx_application_add($RB, ['applicant_name' => 'BB7 Mumbai applicant']);
$RA2 = cx_requirement_create(['title' => 'BB7 Ahmedabad second'], true);
cx_requirement_set_office($RA2, $A);
$r = $drive($CA, 'connect-requirement', 'POST', [], ['id' => $RA2, 'action' => 'app_transition', 'application_id' => $appB, 'to' => 'REJECTED']);
t_eq(strtoupper((string) ops_val("SELECT status FROM cx_applications WHERE id=?", [$appB])), 'APPLIED',
    'pointing an own-office form at another posting\'s application changes nothing (' . $said($r) . ')');

// ---- legacy postings with no office stay everyone's ----------------------------------------------------------
$RL = cx_requirement_create(['title' => 'BB7 Legacy posting'], false);
cx_requirement_set_office($RL, null);
$drive($CB, 'connect-requirement', 'POST', [], ['id' => $RL, 'action' => 'req_transition', 'to' => 'OPEN']);
t_eq(strtoupper((string) $req($RL)['status']), 'OPEN', 'a posting with no office is still actionable by any office on the desk (no lock-out)');

// ---- backfill of older rows ------------------------------------------------------------------------------------
$pdo->prepare("INSERT INTO cx_requirements (ref_code,title,created_by,status) VALUES ('BB7-L1','BB7 old by Asha','Asha Bb7','OPEN')")->execute();
$L1 = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO cx_requirements (ref_code,title,created_by,status) VALUES ('BB7-L2','BB7 old by nobody','Somebody Gone','OPEN')")->execute();
$L2 = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO requisitions (req_code, office_id, status) VALUES ('BB7-RQ1', ?, 'OPEN')")->execute([$B]);
$RQ = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO cx_requirements (ref_code,title,created_by,status,requisition_id) VALUES ('BB7-L3','BB7 old from requisition','Asha Bb7','OPEN',?)")->execute([$RQ]);
$L3 = (int) $pdo->lastInsertId();
cx_requirement_backfill_office();
t_eq((int) ($req($L1)['office_id'] ?? 0), $A, 'backfill: an older posting takes its poster\'s home office');
t_eq($req($L2)['office_id'] ?? null, null, 'backfill: a poster nobody can trace leaves it without an office');
t_eq((int) ($req($L3)['office_id'] ?? 0), $B, 'backfill: one made from a requisition takes the requisition\'s office');

// ---- posted from a requisition: the requisition's office ---------------------------------------------------------
$pdo->prepare("INSERT INTO requisitions (req_code, office_id, status, designation) VALUES ('BB7-RQ2', ?, 'OPEN', 'INSPECTOR')")->execute([$B]);
$RQ2 = (int) $pdo->lastInsertId();
$as($BD);
[$fok, , $fid] = cx_requirement_from_requisition($RQ2);
$nobody();
t_ok($fok && $fid > 0, 'arranged: "Post to Marketplace" from a Mumbai requisition');
t_eq((int) ($req($fid)['office_id'] ?? 0), $B, 'the draft belongs to the requisition\'s office, not the poster\'s');

// ---- invite and bench allocation are actions too --------------------------------------------------------------------
$pdo->prepare("INSERT INTO cx_professionals (name, email) VALUES ('BB7 Freelancer', 'bb7.free@example.com')")->execute();
$PRO = (int) $pdo->lastInsertId();
$before = (int) ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=?", [$RA2]);
$r = $drive($CB, 'connect-talent', 'POST', [], ['action' => 'invite', 'requirement_id' => $RA2, 'professional_id' => $PRO]);
t_eq((int) ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=?", [$RA2]), $before, 'Mumbai cannot invite a professional onto an Ahmedabad posting (' . $said($r) . ')');
$drive($CA, 'connect-talent', 'POST', [], ['action' => 'invite', 'requirement_id' => $RA2, 'professional_id' => $PRO]);
t_eq((int) ops_val("SELECT COUNT(*) FROM cx_applications WHERE requirement_id=?", [$RA2]), $before + 1, 'control: Ahmedabad can');

// ---- outsiders: nothing changes ----------------------------------------------------------------------------------------
$openIds = array_map('intval', array_column(cx_open_requirements(), 'id'));
t_ok(in_array($RA2, $openIds, true) && in_array($RB, $openIds, true), 'the professionals\' job board (cx_open_requirements) still lists every office\'s open postings');
$portalHtml = (function ($row) use ($root) {   // drawn exactly as views/portal/hire_req.php includes it
    $req = $row; $req['closes_on'] = date('Y-m-d', time() + 10 * 86400); $cxPostTo = '/portal/hire-req';
    ob_start(); include $root . '/views/ops/_cx_closing.php'; return ob_get_clean();
})(cx_requirement_get($RA2));
t_ok(strpos($portalHtml, 'value="req_extend"') !== false, 'the client portal\'s closing-date panel still offers its extend buttons');
