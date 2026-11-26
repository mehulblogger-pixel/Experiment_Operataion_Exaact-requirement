<?php
// ============================================================================
//  Q-08 (owner, 24 Nov 2026: "Yes") — up to FIVE ordered signatures to open a
//  contract (and to approve an amendment), set on the same settings screen.
//
//  What is proved here:
//   1. A company that never answered, or answered 0/1/2, is unchanged.
//   2. Three and five signatures: each waits for the one before it; posting a
//      later one early is refused; the final approval opens the contract.
//   3. "May one person give more than one" still holds across all of them.
//   4. A re-opening (or a change to the figures) voids the middle signatures —
//      they are kept, never deleted.
//   5. An amendment with three signatures follows the same order.
//   6. No new status: the contract stays PENDING until the final approval.
// ============================================================================
t_section('Q-08 — ordered contract signatures (five on 24 Nov; any number from 26 Nov)');

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
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? [])) . (isset($r['error']) ? ' [' . $r['error'] . ']' : '');

$pdo = db();
contracts_migrate();
$KEYS = ['contract_open_signatures', 'contract_same_person'];
foreach (range(1, 5) as $i) { $KEYS[] = 'contract_sig' . $i . '_roles'; $KEYS[] = 'contract_sig' . $i . '_user'; }
$wasSet = []; foreach ($KEYS as $k) $wasSet[$k] = (string) setting_get($k, '');
$prevSession = $_SESSION ?? [];
$rule = function (array $x = []) use ($KEYS) { foreach ($KEYS as $k) setting_set($k, (string) ($x[$k] ?? '')); };
$as = function ($uid) { $_SESSION['uid'] = $uid; unset($_SESSION['uid_ws']); current_user(true); ua(true); };
$tag = 'cms' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['CMS Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mk = function ($first, $role) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'Cms', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$OM  = $mk('Omkar',  'OPERATION_MANAGER');
$BM  = $mk('Bina',   'BRANCH_MANAGER');
$FIN = $mk('Farah',  'FINANCE');
$KAM = $mk('Kiran',  'KEY_ACCOUNTS_MANAGER');
$SBU = $mk('Sunil',  'SBU_HEAD');
$BD  = $mk('Divya',  'BUSINESS_DIRECTOR');
$MA  = (int) ops_val("SELECT id FROM users WHERE is_active=1 AND (role='MASTER_ADMIN' OR is_superuser=1) ORDER BY id LIMIT 1");
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES (?,?,1,'ACTIVE')")->execute(['CMS Client ' . $tag, 'CMS Client ' . $tag]);
$cli = (int) $pdo->lastInsertId();
$n = 0;
$mkC = function ($status = 'PENDING') use ($pdo, $cli, $off, $tag, &$n) {
    $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, start_date, end_date, branch_id, open_status, is_active, requested_by, requested_at)
                   VALUES (?,?,?,?,?,?,?,?,0,'Farah Cms',?)")->execute([$cli, strtoupper($tag) . '-' . (++$n), 'Many-signature probe', 100000, '2026-04-01', '2027-03-31', $off, $status, date('c')]);
    return (int) $pdo->lastInsertId();
};
$C = fn($id) => ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]);
$midCount = fn($id, $am = 0) => (int) ops_val("SELECT COUNT(*) FROM contract_signatures WHERE contract_id=? AND amendment_id=? AND COALESCE(voided_at,'')=''", [$id, $am]);

try {
    // ---- 1. nothing changes for 0/1/2 -------------------------------------------------------------
    $rule();
    t_eq(contract_open_rule()['signatures'], 2, 'a company that never answered still has two signatures');
    t_eq([contract_sig_order(0), contract_sig_order(1), contract_sig_order(2)], [[], [2], [1, 2]], 'the order for none, one and two is the one the app always had');
    t_eq([contract_sig_order(3), contract_sig_order(5)], [[1, 3, 2], [1, 3, 4, 5, 2]], 'three and five: first, the ones in between, then the final approval');
    $c0 = $mkC();
    $as($OM); $sg = contract_open_stage($C($c0));
    t_eq([$sg['need'], $sg['primary']], ['first', 'endorse'], 'an existing two-signature company: the manager still gives the first signature');
    $drive($OM, 'contract-open', 'POST', [], ['id' => $c0, 'do' => 'endorse']);
    $as($BM); t_eq(contract_open_stage($C($c0))['primary'], 'approve', '…then the Branch Manager approves, as before');
    $r = $drive($BM, 'contract-open', 'POST', [], ['id' => $c0, 'do' => 'sign', 'slot' => 3]);
    t_ok(strpos($said($r), 'no middle signature') !== false && $midCount($c0) === 0, 'a "middle signature" posted on a two-signature company is refused: ' . $said($r));
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c0, 'do' => 'approve']);
    t_eq((string) $C($c0)['open_status'], 'OPEN', 'a two-signature contract opens exactly as before');
    //  Owner, 26 Nov 2026 ("as many as needed"): any whole number is a number of signatures;
    //  something that is not a whole number is not trusted — the standard two applies.
    setting_set('contract_open_signatures', '9');
    t_eq(contract_open_rule()['signatures'], 9, 'nine signatures is a number the company may now ask for');
    setting_set('contract_open_signatures', 'nine');
    t_eq(contract_open_rule()['signatures'], 2, 'a value that is not a whole number is not trusted — the standard two applies');

    // ---- 2. three signatures, in order -----------------------------------------------------------
    t_section('Q-08 — three signatures, one after another');
    $rule(['contract_open_signatures' => '3', 'contract_sig3_roles' => 'FINANCE']);
    t_ok(strpos(contract_rule_sentence(), '3 signatures, one after another') === 0 && strpos(contract_rule_sentence(), 'then Finance') !== false, 'the rule reads as one sentence: ' . contract_rule_sentence());
    $c3 = $mkC();
    $as($FIN); $sg = contract_open_stage($C($c3));
    t_eq([$sg['need'], $sg['primary']], ['first', ''], 'Finance (signature 2) sees no button while the first signature is missing');
    $r = $drive($FIN, 'contract-open', 'POST', [], ['id' => $c3, 'do' => 'sign', 'slot' => 3]);
    t_ok($midCount($c3) === 0 && strpos($said($r), 'not the one waiting now') !== false, 'signature 2 posted before the first is refused: ' . $said($r));
    $r = $drive($BM, 'contract-open', 'POST', [], ['id' => $c3, 'do' => 'approve']);
    t_ok((string) $C($c3)['open_status'] === 'PENDING' && strpos($said($r), 'earlier signatures') !== false, 'the final approval posted first is refused: ' . $said($r));
    $drive($OM, 'contract-open', 'POST', [], ['id' => $c3, 'do' => 'endorse']);
    t_eq((int) $C($c3)['mgr_endorsed_by_id'], $OM, 'the first signature is given');
    $as($BM); $sg = contract_open_stage($C($c3));
    t_eq([$sg['need'], $sg['primary']], ['middle', ''], 'the Branch Manager still waits — signature 2 comes before the final approval');
    t_ok(strpos($sg['waiting'], '1 of 3 signatures given') === 0 && strpos($sg['waiting'], 'signature 2 of 3 — Farah Cms') !== false, 'and everyone reads who it waits for: ' . $sg['waiting']);
    $r = $drive($BM, 'contract-open', 'POST', [], ['id' => $c3, 'do' => 'approve']);
    t_eq((string) $C($c3)['open_status'], 'PENDING', 'the final approval is still refused after only the first signature');
    $as($FIN); $sg = contract_open_stage($C($c3));
    t_eq([$sg['need'], $sg['primary'], $sg['slot']], ['middle', 'sign', 3], 'Finance is now offered signature 2');
    $r = $drive($FIN, 'contract-open', 'POST', [], ['id' => $c3, 'do' => 'sign', 'slot' => 3]);
    t_ok($midCount($c3) === 1 && strpos($said($r), 'Signature 2 of 3 given') === 0, 'Finance gives signature 2: ' . $said($r));
    $r = $drive($FIN, 'contract-open', 'POST', [], ['id' => $c3, 'do' => 'sign', 'slot' => 3]);
    t_eq($midCount($c3), 1, 'pressing it twice gives it once');
    t_eq((string) $C($c3)['open_status'], 'PENDING', 'no new status: the contract is still PENDING with two of three signatures');
    $as($BM); t_eq(contract_open_stage($C($c3))['primary'], 'approve', 'the Branch Manager is now offered the final approval');
    $r = $drive($BM, 'contract-open', 'POST', [], ['id' => $c3, 'do' => 'approve']);
    t_eq([(string) $C($c3)['open_status'], (string) $C($c3)['bm_approved_by']], ['OPEN', 'Bina Cms'], 'and it opens: ' . $said($r));

    // ---- 3. five signatures, and one person per signature -----------------------------------------
    t_section('Q-08 — five signatures; each from a different person unless allowed');
    $rule(['contract_open_signatures' => '5', 'contract_sig3_roles' => 'FINANCE', 'contract_sig4_user' => (string) $BD, 'contract_sig5_roles' => 'SBU_HEAD,FINANCE']);
    $c5 = $mkC();
    $drive($OM, 'contract-open', 'POST', [], ['id' => $c5, 'do' => 'endorse']);
    $r = $drive($BD, 'contract-open', 'POST', [], ['id' => $c5, 'do' => 'sign', 'slot' => 4]);
    t_ok($midCount($c5) === 0 && strpos($said($r), 'not the one waiting now') !== false, 'signature 3 waits for signature 2: ' . $said($r));
    $drive($FIN, 'contract-open', 'POST', [], ['id' => $c5, 'do' => 'sign', 'slot' => 3]);
    $r = $drive($BD, 'contract-open', 'POST', [], ['id' => $c5, 'do' => 'sign', 'slot' => 4]);
    t_eq($midCount($c5), 2, 'the named person gives signature 3: ' . $said($r));
    $as($FIN); $sg = contract_open_stage($C($c5));
    t_ok($sg['slot'] === 5 && $sg['primary'] === '' && strpos($sg['hint'], 'already signed') !== false, 'Finance holds signature 4 too, but has signed already — no button, and told why');
    t_ok(strpos($sg['waiting'], 'signature 4 of 5 — Sunil Cms') !== false && strpos($sg['waiting'], 'Farah Cms') === false, 'the waiting line names the one who has NOT signed: ' . $sg['waiting']);
    $r = $drive($FIN, 'contract-open', 'POST', [], ['id' => $c5, 'do' => 'sign', 'slot' => 5]);
    t_ok($midCount($c5) === 2 && strpos($said($r), 'someone else') !== false, 'Finance posting signature 4 anyway is refused: ' . $said($r));
    $drive($SBU, 'contract-open', 'POST', [], ['id' => $c5, 'do' => 'sign', 'slot' => 5]);
    t_eq($midCount($c5), 3, 'the Business Unit Head gives signature 4');
    t_eq((string) $C($c5)['open_status'], 'PENDING', 'four of five: still PENDING');
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c5, 'do' => 'approve']);
    t_eq((string) $C($c5)['open_status'], 'OPEN', 'the fifth — the final approval — opens it');
    $hist = (string) ops_val("SELECT GROUP_CONCAT(slot) FROM (SELECT slot FROM contract_signatures WHERE contract_id=? ORDER BY id) x", [$c5]);
    t_eq($hist, '3,4,5', 'the signatures in between are kept on record, in the order given');

    //  One person may give more than one, when the company says so.
    $rule(['contract_open_signatures' => '3', 'contract_sig1_roles' => 'BRANCH_MANAGER', 'contract_sig3_roles' => 'BRANCH_MANAGER', 'contract_sig2_roles' => 'BRANCH_MANAGER', 'contract_same_person' => 'yes']);
    $c6 = $mkC();
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c6, 'do' => 'endorse']);
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c6, 'do' => 'sign', 'slot' => 3]);
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c6, 'do' => 'approve']);
    t_eq((string) $C($c6)['open_status'], 'OPEN', 'with "one person may give more than one", the same person can give all three');
    $rule(['contract_open_signatures' => '3', 'contract_sig1_roles' => 'BRANCH_MANAGER', 'contract_sig3_roles' => 'BRANCH_MANAGER', 'contract_sig2_roles' => 'BRANCH_MANAGER']);
    $c7 = $mkC();
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c7, 'do' => 'endorse']);
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c7, 'do' => 'sign', 'slot' => 3]);
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c7, 'do' => 'approve']);
    t_eq([(string) $C($c7)['open_status'], $midCount($c7)], ['PENDING', 0], 'without it, the same person cannot give a second signature');

    //  A middle signature nobody holds: only the Master Admin, and the settings say so.
    $rule(['contract_open_signatures' => '3']);
    $facts = implode(' ', setupqa_sales_contract_facts());
    t_ok(strpos($facts, 'Nobody can give the signature 2 of 3 yet') !== false, 'the settings warn when a middle signature has nobody: ' . $facts);

    // ---- 4. a re-opening and a change of figures void the middle signatures ------------------------
    t_section('Q-08 — a re-opening starts the signatures again; nothing is deleted');
    $rule(['contract_open_signatures' => '3', 'contract_sig3_roles' => 'FINANCE']);
    $c8 = $mkC();
    $drive($OM, 'contract-open', 'POST', [], ['id' => $c8, 'do' => 'endorse']);
    $drive($FIN, 'contract-open', 'POST', [], ['id' => $c8, 'do' => 'sign', 'slot' => 3]);
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c8, 'do' => 'reject', 'note' => 'wrong client']);
    t_eq((string) $C($c8)['open_status'], 'REJECTED', 'ARMING · the opening was rejected after two signatures');
    $r = $drive($MA, 'contract-open', 'POST', [], ['id' => $c8, 'do' => 'reopen']);
    t_eq((string) $C($c8)['open_status'], 'PENDING', 'ARMING · re-opened: ' . $said($r));
    t_eq([$midCount($c8), (int) ops_val("SELECT COUNT(*) FROM contract_signatures WHERE contract_id=?", [$c8])], [0, 1], 'after a re-opening the old signature 2 no longer counts — but it is still on record');
    $as($FIN); t_eq(contract_open_stage($C($c8))['need'], 'first', 'the re-opened contract waits for its first signature again');
    $lib = (string) file_get_contents($root . '/lib/contracts.php');
    t_ok(strpos($lib, "contract_mid_void((int)\$c['id'], 0);   // Q-08 — earlier middle signatures are kept, voided") !== false, 'the re-open action voids the middle signatures');
    t_ok(strpos($lib, "contract_mid_void(\$id, 0);   // Q-08 — the signatures after it fall with it") !== false
        && strpos((string) file_get_contents($root . '/lib/billunits.php'), 'contract_mid_void($id, 0)') !== false
        && strpos((string) file_get_contents($root . '/lib/milestones.php'), "contract_mid_void((int)\$c['id'], 0)") !== false,
        'a change to the figures, billing terms or billing schedule before approval withdraws them too');

    // ---- 5. an amendment with three signatures ---------------------------------------------------
    t_section('Q-08 — an amendment follows the same order');
    $c9 = $mkC('OPEN');
    $pdo->prepare("UPDATE partner_contracts SET is_active=1 WHERE id=?")->execute([$c9]);
    $old = contract_commercial($C($c9), []);
    $new = $old; $new['value'] = 150000.0;
    $pdo->prepare("INSERT INTO contract_amendments (contract_id,status,old_values,new_values,reason,requested_by,requested_by_id,requested_at) VALUES (?, 'PENDING', ?,?,?,?,?,?)")
        ->execute([$c9, json_encode($old), json_encode($new), 'More visits', 'Farah Cms', $FIN, date('c')]);
    $am = contract_pending_amendment($c9);
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c9, 'do' => 'amend-approve']);
    t_eq((string) contract_pending_amendment($c9)['status'], 'PENDING', 'the final approval on an amendment waits for the earlier signatures');
    $drive($OM, 'contract-open', 'POST', [], ['id' => $c9, 'do' => 'amend-endorse']);
    $r = $drive($FIN, 'contract-open', 'POST', [], ['id' => $c9, 'do' => 'amend-sign', 'slot' => 3]);
    t_eq($midCount($c9, (int) $am['id']), 1, 'Finance gives signature 2 on the amendment: ' . $said($r));
    t_eq($midCount($c9, 0), 0, '…kept apart from the opening\'s signatures');
    $r = $drive($BM, 'contract-open', 'POST', [], ['id' => $c9, 'do' => 'amend-approve']);
    t_eq([(float) $C($c9)['value'], (string) ops_val("SELECT status FROM contract_amendments WHERE id=?", [(int) $am['id']])], [150000.0, 'APPROVED'], 'the final approval applies the new figures: ' . $said($r));

    // ---- 6. the settings screen ------------------------------------------------------------------
    t_section('Q-08 — the settings screen');
    $sec = array_values(array_filter(setupqa_sales_set()['sections'] ?? [], fn($s) => ($s['tab'] ?? '') === 'contract'))[0] ?? ['questions' => []];
    $qs = array_column($sec['questions'], null, 'key');
    //  From 26 Nov 2026 the number is typed (any number; tests/test_owner26_quotes.php proves seven).
    t_eq([$qs['contract_open_signatures']['type'] ?? '', (int) ($qs['contract_open_signatures']['min'] ?? -1)], ['number', 0], 'the number of signatures is any whole number from 0');
    t_eq($qs['contract_same_person']['only_when'][1], '2+', '"may one person give more than one" is asked whenever there are two or more');
    ob_start(); $sections = [$sec]; include $root . '/views/ops/_setup_sections.php'; $signers = (string) ob_get_clean();
    t_ok(strpos($signers, 'Who gives signature 2?') !== false && strpos($signers, 'Who gives signature 4?') !== false, 'a box for each signature in between');
    $as($MA);
    $saved = contract_rule_save(['contract_sig4_sent' => '1', 'contract_sig4_roles' => ['FINANCE']]);
    t_ok(contract_open_rule()['sig'][4]['roles'] === ['FINANCE'] && strpos(implode(' ', $saved), 'Signature 3') !== false, 'the box for signature 3 saves: ' . implode(' ', $saved));
} finally {
    foreach ($KEYS as $k) setting_set($k, $wasSet[$k]);
    $ids = array_map('intval', array_column(ops_all("SELECT id FROM partner_contracts WHERE partner_id=?", [$cli]) ?: [], 'id'));
    foreach ($ids as $id) {
        $pdo->prepare("DELETE FROM contract_signatures WHERE contract_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM contract_amendments WHERE contract_id=?")->execute([$id]);
    }
    $pdo->prepare("DELETE FROM partner_contracts WHERE partner_id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
    $_SESSION = $prevSession; current_user(true); ua(true);
}
