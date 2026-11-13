<?php
// ============================================================================
//  Who opens a contract is each company's own rule — owner, 13 Nov 2026:
//  "I am logged in as branch manager and approved it. It still says that it is
//   now pending with branch manager … Hope these approvals are not hard coded in
//   our application. This all approvals shall be set by individual organization."
//
//  What is proved here:
//   1. A company that never opens the setting gets exactly the rule it had.
//   2. It can ask for two approvals, one, or none; name its own roles or a person
//      for each; and allow (or not) one person to give both.
//   3. Each viewer is offered ONE button — the signature that is theirs to give
//      now — and everybody reads, by name, who it is waiting for.
//   4. The quotation says "waiting for approval" until the contract is open.
// ============================================================================
t_section('Contract opening — the standard rule is unchanged');

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
$KEYS = ['contract_open_signatures', 'contract_same_person', 'contract_sig1_roles', 'contract_sig1_user', 'contract_sig2_roles', 'contract_sig2_user'];
$wasSet = []; foreach ($KEYS as $k) $wasSet[$k] = (string) setting_get($k, '');
$prevSession = $_SESSION ?? [];
$rule = function (array $x = []) use ($KEYS) {            // set the rule; anything not named goes back to "never answered"
    foreach ($KEYS as $k) setting_set($k, (string) ($x[$k] ?? ''));
};
$as = function ($uid) { $_SESSION['uid'] = $uid; unset($_SESSION['uid_ws']); current_user(true); ua(true); };
$tag = 'cor' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['COR Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mk = function ($first, $role) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'Cor', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$OM  = $mk('Omkar',  'OPERATION_MANAGER');
$BM  = $mk('Bina',   'BRANCH_MANAGER');
$BM2 = $mk('Bharat', 'BRANCH_MANAGER');
$FIN = $mk('Farah',  'FINANCE');
$KAM = $mk('Kiran',  'KEY_ACCOUNTS_MANAGER');
$U   = fn($id) => ops_one("SELECT * FROM users WHERE id=?", [$id]);
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES (?,?,1,'ACTIVE')")->execute(['COR Client ' . $tag, 'COR Client ' . $tag]);
$cli = (int) $pdo->lastInsertId();
$n = 0;
$mkC = function () use ($pdo, $cli, $off, $tag, &$n) {
    $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, start_date, end_date, branch_id, open_status, is_active, requested_by, requested_at)
                   VALUES (?,?,?,?,?,?,?,'PENDING',0,'Farah Cor',?)")->execute([$cli, strtoupper($tag) . '-' . (++$n), 'Rule probe', 100000, '2026-04-01', '2027-03-31', $off, date('c')]);
    return (int) $pdo->lastInsertId();
};
$C = fn($id) => ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]);

try {
    // ---- 1. the standard rule ---------------------------------------------------------------------
    $rule();
    $r0 = contract_open_rule();
    t_eq([$r0['signatures'], $r0['same_person'], $r0['sig'][1]['standard'], $r0['sig'][2]['standard']], [2, false, true, true], 'a company that never answered: two signatures, two different people, the standard signers');
    t_eq([$r0['sig'][1]['roles'], $r0['sig'][2]['roles']], [['OPERATION_MANAGER', 'SBU_HEAD', 'ADMIN', 'BUSINESS_DIRECTOR'], ['BRANCH_MANAGER', 'BRANCH_APP_MANAGER']], 'the standard signers are the roles the code used to name');
    $as($OM);  t_eq([can_endorse_contract_open(), can_approve_contract_open()], [true, false], 'an Operation Manager gives the first signature, not the final approval');
    $as($FIN); t_eq([can_endorse_contract_open(), can_approve_contract_open()], [false, false], 'Finance gives neither');
    $as($BM);  t_ok(can_approve_contract_open(), 'a Branch Manager gives the final approval');
    $bmBoth = can_endorse_contract_open();   // true where the role manages its branch's users — the owner's case
    t_eq($bmBoth, can('users.manage.branch'), 'a Branch Manager who manages the branch\'s users may also give the first signature, as before');
    t_ok(strpos(contract_rule_sentence(), 'two signatures — first Operation Manager') !== false && strpos(contract_rule_sentence(), '(two different people)') !== false, 'the rule reads as one sentence: ' . contract_rule_sentence());

    // ---- 2. one button per viewer, and names --------------------------------------------------------
    t_section('Contract opening — each viewer gets the one signature that is theirs');
    $c1 = $mkC(); $row = $C($c1);
    $as($OM);  $sg = contract_open_stage($row);
    t_eq([$sg['need'], $sg['primary'], $sg['alt']], ['first', 'endorse', ''], 'the manager is offered the first signature');
    t_ok(strpos($sg['waiting'], 'Omkar Cor') !== false && strpos($sg['waiting'], 'Then the final approval') !== false, 'everyone reads who it waits for, by name: ' . $sg['waiting']);
    $as($FIN); $sg = contract_open_stage($row);
    t_eq([$sg['primary'], $sg['alt'], $sg['reject']], ['', '', false], 'someone who signs nothing sees no button — only who it waits for');
    $as($BM);  $sg = contract_open_stage($row);
    if ($bmBoth) {
        //  The owner's case: signed in as Branch Manager, pressed "Endorse", then read "now with the branch manager".
        t_eq([$sg['primary'], $sg['alt']], ['', 'endorse'], 'a Branch Manager who could give either signature is NOT offered "first signature" as the main button — theirs is the final approval');
        t_ok(strpos($sg['hint'], 'You give the final approval') !== false, 'and is told so: ' . $sg['hint']);
    } else {
        t_eq([$sg['primary'], $sg['alt']], ['', ''], 'a Branch Manager waits for the first signature');
        t_ok(strpos($sg['hint'], 'You give the final approval') !== false, 'and is told theirs comes next');
    }
    $r = $drive($OM, 'contract-open', 'POST', [], ['id' => $c1, 'do' => 'endorse']);
    t_ok(strpos($said($r), 'First signature given.') !== false && strpos($said($r), 'Bina Cor') !== false && stripos($said($r), 'now with the branch manager') === false,
        'after the first signature the message names who gives the final approval: ' . $said($r));
    $row = $C($c1);
    $as($OM);  t_eq(contract_open_stage($row)['primary'], '', 'the manager has nothing more to press');
    $as($BM);  t_eq(contract_open_stage($row)['primary'], 'approve', 'the Branch Manager is now offered "Approve & open"');
    $r = $drive($FIN, 'contract-open', 'POST', [], ['id' => $c1, 'do' => 'approve']);
    t_eq((string) $C($c1)['open_status'], 'PENDING', 'someone who is not a final approver cannot open it by posting the form');
    $r = $drive($BM, 'contract-open', 'POST', [], ['id' => $c1, 'do' => 'approve']);
    t_eq([(string) $C($c1)['open_status'], (string) $C($c1)['bm_approved_by']], ['OPEN', 'Bina Cor'], 'the Branch Manager opens it: ' . $said($r));

    // ---- 3. two different people, unless the company says otherwise --------------------------------
    t_section('Contract opening — two different people, unless the company allows one');
    $rule(['contract_sig1_roles' => 'BRANCH_MANAGER', 'contract_sig2_roles' => 'BRANCH_MANAGER']);
    $c2 = $mkC();
    $drive($BM, 'contract-open', 'POST', [], ['id' => $c2, 'do' => 'endorse']);
    t_eq((int) $C($c2)['mgr_endorsed_by_id'], $BM, 'ARMING · a Branch Manager gave the first signature');
    $as($BM);  $sg = contract_open_stage($C($c2));
    t_ok($sg['primary'] === '' && strpos($sg['hint'], 'someone else') !== false, 'the same person is not offered the final approval — and is told why');
    t_ok(strpos($sg['waiting'], 'Bharat Cor') !== false && strpos($sg['waiting'], 'Bina Cor') === false, 'the waiting line names the OTHER approver, not the one who already signed: ' . $sg['waiting']);
    $r = $drive($BM, 'contract-open', 'POST', [], ['id' => $c2, 'do' => 'approve']);
    t_ok((string) $C($c2)['open_status'] === 'PENDING' && strpos($said($r), 'someone else') !== false, 'posting the approval anyway is refused: ' . $said($r));
    $rule(['contract_sig1_roles' => 'BRANCH_MANAGER', 'contract_sig2_roles' => 'BRANCH_MANAGER', 'contract_same_person' => 'yes']);
    $as($BM);  t_eq(contract_open_stage($C($c2))['primary'], 'approve', 'with "one person may give both", the same person is offered the final approval');
    $r = $drive($BM, 'contract-open', 'POST', [], ['id' => $c2, 'do' => 'approve']);
    t_eq((string) $C($c2)['open_status'], 'OPEN', 'and it opens: ' . $said($r));

    // ---- 4. one approval --------------------------------------------------------------------------
    t_section('Contract opening — one approval');
    $rule(['contract_open_signatures' => '1']);
    $c3 = $mkC(); $row = $C($c3);
    $as($OM);  t_eq([can_endorse_contract_open(), contract_open_stage($row)['primary']], [false, ''], 'with one approval there is no first signature to give');
    $as($BM);  $sg = contract_open_stage($row);
    t_ok($sg['primary'] === 'approve' && strpos($sg['waiting'], 'Waiting for approval — ') === 0, 'the Branch Manager approves straight away: ' . $sg['waiting']);
    $r = $drive($OM, 'contract-open', 'POST', [], ['id' => $c3, 'do' => 'endorse']);
    t_eq((string) $C($c3)['mgr_endorsed_at'], '', 'a "first signature" posted anyway is not recorded');
    $r = $drive($BM, 'contract-open', 'POST', [], ['id' => $c3, 'do' => 'approve']);
    t_eq((string) $C($c3)['open_status'], 'OPEN', 'one approval opens it: ' . $said($r));
    t_ok(strpos(contract_rule_sentence(), 'one approval, from Branch Manager') === 0, 'the sentence says so: ' . contract_rule_sentence());

    // ---- 5. the company's own roles, and a named person ---------------------------------------------
    t_section('Contract opening — the company\'s own roles and a named person');
    $rule(['contract_open_signatures' => '1', 'contract_sig2_roles' => 'FINANCE', 'contract_sig2_user' => (string) $KAM]);
    $as($FIN); t_ok(can_approve_contract_open(), 'a role the company ticked (Finance) gives the final approval');
    $as($KAM); t_ok(can_approve_contract_open(), 'and so does the one named person');
    $as($BM);  t_ok(!can_approve_contract_open(), 'a Branch Manager no longer does — the company\'s choice replaces the standard one');
    t_ok(strpos(contract_sig_label(2), 'Finance') !== false && strpos(contract_sig_label(2), 'Kiran Cor') !== false, 'the rule names the role and the person: ' . contract_sig_label(2));
    $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$KAM]);
    t_eq(contract_open_rule()['sig'][2]['user'], 0, 'a named person who has left signs nothing');
    $pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$KAM]);
    //  A role nobody holds yet (a real one — an unknown code is ignored, never trusted).
    $empty = '';
    foreach (array_keys(roles_all()) as $rk)
        if (!in_array($rk, ['MASTER_ADMIN', 'INSPECTOR'], true) && !ops_val("SELECT COUNT(*) FROM users WHERE role=? AND is_active=1 AND COALESCE(is_superuser,0)=0", [$rk])) { $empty = $rk; break; }
    if ($empty === '') { $empty = 'QUALITY_HEAD'; $pdo->prepare("UPDATE users SET is_active=0 WHERE role=? AND username NOT LIKE ?")->execute([$empty, $tag . '%']); }
    $rule(['contract_open_signatures' => '1', 'contract_sig2_roles' => $empty]);
    t_eq(contract_open_rule()['sig'][2]['roles'], [$empty], 'ARMING · the final approval is addressed to a role nobody holds (' . $empty . ')');
    $nobody = contract_sig_names(2);
    t_ok(strpos($nobody, 'nobody yet') === 0 && strpos($nobody, 'Admin → Approvals → Sales → Contract') !== false, 'when nobody holds the role it says so, and where to fix it: ' . $nobody);
    t_ok((bool) array_filter(setupqa_sales_contract_facts(), fn($f) => strpos($f, '⚠ Nobody can give the final approval yet') === 0), 'and the setup tab warns before a won order meets it');
    $rule(['contract_sig2_roles' => 'NOT_A_ROLE']);
    t_ok(contract_open_rule()['sig'][2]['standard'], 'a role code that does not exist is ignored — the standard rule applies');

    // ---- 6. no approval ---------------------------------------------------------------------------
    t_section('Contract opening — no approval');
    $rule(['contract_open_signatures' => '0']);
    $c4 = $mkC();
    t_eq([contract_after_register($c4), (string) $C($c4)['open_status'], (int) $C($c4)['is_active']], ['OPEN', 'OPEN', 1], 'a newly registered contract is open at once');
    t_ok(strpos(contract_registered_words($c4), 'registered and open') === 0, 'and the message says why: ' . contract_registered_words($c4));
    t_eq(contract_amend_signatures(), 1, 'a later change to an open contract still takes one approval');
    $rule();
    $c5 = $mkC();
    t_eq([contract_after_register($c5), (string) $C($c5)['open_status']], ['PENDING', 'PENDING'], 'with the standard rule a new contract waits, as before');
    t_ok(strpos(contract_registered_words($c5), 'waiting for approval. Waiting for the first signature — ') !== false, 'and whoever registered it reads who it waits for: ' . contract_registered_words($c5));

    // ---- 7. saving the rule -----------------------------------------------------------------------
    t_section('Contract opening — who may change the rule, and what is kept');
    $rule();
    $as($BM);
    t_eq(contract_rule_save(['contract_sig2_sent' => '1', 'contract_sig2_roles' => ['FINANCE']]), [], 'someone who may not change company settings changes nothing');
    t_ok(contract_open_rule()['sig'][2]['standard'], '…the standard rule still stands');
    t_as_admin();
    t_eq(contract_rule_save(['contract_sig1_sent' => '1', 'contract_sig1_roles' => CONTRACT_SIG_STANDARD[1], 'contract_sig2_sent' => '1', 'contract_sig2_roles' => CONTRACT_SIG_STANDARD[2]]), [],
        'saving the page without touching the ticks keeps the standard rule (it does not quietly drop "anyone who manages a branch\'s users")');
    t_ok(contract_open_rule()['sig'][1]['standard'], '…still standard');
    $ch = contract_rule_save(['contract_sig2_sent' => '1', 'contract_sig2_roles' => ['FINANCE', 'NOT_A_ROLE', 'MASTER_ADMIN'], 'contract_sig2_user' => '999999']);
    t_eq([contract_open_rule()['sig'][2]['roles'], contract_open_rule()['sig'][2]['user'], count($ch)], [['FINANCE'], 0, 1], 'only real roles and active people are stored; the change is reported in words: ' . implode('; ', $ch));
    $set = setupqa_sales_set();
    $sec = array_values(array_filter($set['sections'], fn($s) => ($s['tab'] ?? '') === 'contract'))[0] ?? null;
    t_ok($sec && ($sec['editor'] ?? '') === 'contract_signers' && array_column($sec['questions'], 'key') === ['contract_open_signatures', 'contract_same_person'], 'the Contract tab asks: how many approvals, may one person give both, and who signs');
    $as($BM);
    $changed = setupqa_save(['title' => 'Sales', 'sections' => [$sec]], ['contract_open_signatures' => '0']);
    t_eq([$changed, contract_open_rule()['signatures']], [[], 2], 'the questions on the Contract tab are closed to someone who may not change company settings');

    // ---- 8. the screens ---------------------------------------------------------------------------
    t_section('Contract opening — the screens say the same thing everywhere');
    $q = (string) file_get_contents($root . '/views/ops/crm/quote_detail.php');
    t_ok(strpos($q, "is waiting for approval.'; \$nbNext = contract_waiting_words(\$contractRow);") !== false, 'a won quotation whose contract is not open yet says "waiting for approval" and who it waits for — not "raise calls from it"');
    t_ok(strpos($q, 'Endorse (manager)') === false && strpos($q, 'Approve &amp; open (branch manager)') === false, 'the fixed "manager" / "branch manager" buttons are gone from the quotation');
    foreach (['views/ops/crm/quote_detail.php', 'views/ops/contract_openings.php', 'views/ops/contract_detail.php'] as $f)
        t_ok(strpos((string) file_get_contents($root . '/' . $f), '_contract_sign.php') !== false, basename($f) . ' draws its buttons from the one shared piece');
    //  Found while walking this on the practice copy: the milestone section, included bare, used a
    //  working name "$st" that replaced the quotation page's status — so on a quotation WITH a
    //  billing schedule the whole "Won — contract registration" panel silently vanished.
    t_ok(strpos($q, "(static function (array \$__page) { extract(\$__page); include __DIR__ . '/../_milestones.php'; })(get_defined_vars());") !== false,
        'the milestone section is drawn in its own scope on the quotation — it cannot overwrite the page\'s status');
    t_ok(strpos((string) file_get_contents($root . '/views/ops/contract_detail.php'), "(static function (array \$__page) { extract(\$__page); include __DIR__ . '/_milestones.php'; })(get_defined_vars());") !== false,
        '…and on the contract page');
    $probe = (static function (array $__page) { extract($__page); $st = 'changed inside'; return $st; });
    $st = 'ACCEPTED'; $probe(get_defined_vars());
    t_eq($st, 'ACCEPTED', 'a name changed inside such a piece does not change the page\'s own');
    //  Found walking the flow: an Operation Manager signed in the queue and was sent to the
    //  quotation, which they may not open — "Sales & CRM — restricted" after a good signature.
    $sign = (string) file_get_contents($root . '/views/ops/_contract_sign.php');
    t_ok(strpos($sign, "\$sgFrom = \$sgCompact ? '<input type=\"hidden\" name=\"from\" value=\"queue\">' : '';") !== false && substr_count($sign, '<?= $sgFrom ?>') === 3,
        'every button pressed in the Contract openings queue says it came from the queue');
    t_ok(strpos((string) file_get_contents($root . '/lib/contracts.php'), "if ((\$_POST['from'] ?? '') === 'queue') \$back = '/contract-openings';") !== false,
        '…and the signer comes back to the queue (that one address only — never a free redirect)');
    $lib = (string) file_get_contents($root . '/lib/contracts.php');
    t_ok(strpos($lib, 'now with the branch manager for approval') === false && strpos($lib, 'Only the branch manager can approve') === false, 'no message names a fixed role any more');
    $areas = array_column(apsetup_areas(), null, 'key');
    t_ok(isset($areas['sales']) && $areas['sales']['url'] === '/sales-approvals', 'Admin → Approvals offers "Sales"');
    t_eq(array_keys(SALES_APPROVAL_TABS), ['quote', 'costing', 'contract', 'deals', 'leads'], 'with one tab for each approval in Sales');
} finally {
    foreach ($KEYS as $k) setting_set($k, $wasSet[$k]);
    $pdo->prepare("DELETE FROM partner_contracts WHERE partner_id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
    $_SESSION = $prevSession; current_user(true); ua(true);
}
