<?php
// The contract-openings hand-off screen: the people who sign reach their button
// here without needing to open the quotation (which an Operation Manager may not
// have rights to view). Each viewer sees only the ONE signature that is theirs to
// give now, and reads who it waits for otherwise. The rule is the company's own
// (owner, 13 Nov 2026) — here, the standard one: first signature from a manager,
// final approval from a Branch Manager, two different people.
t_section('contract-openings screen shows the right action per role');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
$prevSession = $_SESSION ?? [];
try {
    $mkUser = function ($name, $role) use ($pdo) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_superuser, is_active) VALUES (?, ?, 'Tester', ?, 0, 1)")->execute(['co_' . strtolower($name) . getmypid(), $name, $role]);
        return (int)$pdo->lastInsertId();
    };
    $as = function ($uid) { $_SESSION['uid'] = $uid; unset($_SESSION['uid_ws']); current_user(true); ua(true); };
    $om = $mkUser('Omkar', 'OPERATION_MANAGER'); $bm = $mkUser('Bina', 'BRANCH_MANAGER');
    $render = function ($rows, $myCoordinators = [], $myOfficeName = '', $canSeeQuote = false) {
        $canEndorse = can_endorse_contract_open(); $canApprove = can_approve_contract_open(); $ruleWords = contract_rule_sentence();
        ob_start();
        require __DIR__ . '/../views/ops/contract_openings.php';
        return (string) ob_get_clean();
    };
    $base = ['id' => 1, 'contract_number' => 'CON/TEST/1', 'quote_id' => 0, 'quote_no' => '', 'branch_id' => 0,
        'quote_subject' => 'Coating inspection', 'client_name' => 'ACME Ltd', 'requested_by' => 'Sana',
        'requested_at' => '2026-08-20', 'mgr_endorsed_at' => '', 'mgr_endorsed_by' => '', 'mgr_endorsed_by_id' => 0, 'bm_approved_at' => ''];

    // The manager, before any signature — gives the first signature, cannot approve.
    $as($om);
    $h = $render([$base]);
    t_ok(strpos($h, 'CON/TEST/1') !== false, 'the pending contract is listed');
    t_ok(strpos($h, 'Give the first signature') !== false, 'the manager sees "Give the first signature"');
    t_ok(strpos($h, 'Approve &amp; open') === false, 'someone who gives only the first signature does not see Approve');
    t_ok(strpos($h, 'Waiting for the first signature') !== false, 'the step reads "Waiting for the first signature"');
    t_ok(strpos($h, 'Your company&#039;s rule: two signatures') !== false || strpos($h, "Your company's rule: two signatures") !== false, 'the screen states the company\'s rule, not a fixed sentence');

    // The Branch Manager, after the first signature — approves.
    $endorsed = array_merge($base, ['mgr_endorsed_at' => '2026-08-21', 'mgr_endorsed_by' => 'Omkar Tester', 'mgr_endorsed_by_id' => $om]);
    $as($bm);
    $h2 = $render([$endorsed]);
    t_ok(strpos($h2, 'Approve &amp; open the contract') !== false, 'the Branch Manager sees "Approve & open the contract"');
    t_ok(strpos($h2, 'disabled') === false, 'approve is enabled once the first signature is in');
    t_ok(strpos($h2, 'Waiting for the final approval') !== false, 'the step reads "Waiting for the final approval"');
    t_ok(strpos($h2, 'first signature: Omkar Tester') !== false, 'and says who gave the first signature');

    // Nothing pending.
    $h3 = $render([]);
    t_ok(strpos($h3, 'Nothing is waiting') !== false, 'an empty state shows when nothing is pending');

    // The first signer can forward to a coordinator in his office — an office has
    // several, so the form offers a coordinator picker when coordinators are on file.
    $as($om);
    $coords = [['id' => 7, 'name' => 'Anita', 'role' => 'COORDINATOR'], ['id' => 8, 'name' => 'Arjun', 'role' => 'ASST_MANAGER']];
    $h4 = $render([$base], $coords, 'Head Office');
    t_ok(strpos($h4, 'name="coordinator_id"') !== false, 'the first-signature form offers a coordinator picker');
    t_ok(strpos($h4, 'Anita') !== false && strpos($h4, 'Arjun') !== false, 'the office coordinators are listed to forward to');

    // With no coordinators on file, the picker is simply absent (still signable).
    $h5 = $render([$base]);
    t_ok(strpos($h5, 'name="coordinator_id"') === false, 'no picker when the office has no coordinators');
    t_ok(strpos($h5, 'Give the first signature') !== false, 'the first signature is still offered without a coordinator');

    // Once a coordinator is nominated, the row says who will coordinate.
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES (?, 'Anita', 'COORDINATOR', 1)")->execute(['co_anita' . getmypid()]);
    $anitaId = (int)$pdo->lastInsertId();
    $withCoord = array_merge($endorsed, ['coordinator_id' => $anitaId]);
    $as($bm);
    $h6 = $render([$withCoord]);
    t_ok(strpos($h6, 'Anita to coordinate') !== false, 'the row shows the nominated coordinator once set');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
    $_SESSION = $prevSession; current_user(true); ua(true);
}
