<?php
// C1 — the Sales → Finance → Operations boundary, made visible. Sales own a quotation
// only up to "won"; an ACCEPTED quote is then locked (edits go through a revision) and
// the contract is Accounts' to register. On an accepted quote a SALES viewer now sees a
// clear "Won — handed to Accounts" wall instead of hunting for a next action that is not
// theirs. (The edit-lock itself is quote_is_locked(), already enforced.)
t_section('accepted quote shows the Sales→Finance→Operations handoff wall');

// The banner and its viewer predicate exist in the quote detail.
$qd = file_get_contents(__DIR__ . '/../views/ops/crm/quote_detail.php');
t_ok(strpos($qd, 'Won — handed to Accounts for contract registration') !== false, 'the handoff wall text is present');
t_ok(strpos($qd, '$isSalesViewer =') !== false && strpos($qd, "!\$canContract && !can('ops.call.create') && !is_master()") !== false,
    'the wall targets a sales viewer (owns quotes, cannot register a contract or raise calls)');
t_ok(strpos($qd, "if (\$st === 'ACCEPTED' && \$isSalesViewer)") !== false, 'the wall only shows on an accepted quote');

// The accepted quote is genuinely locked to editing (the enforcement side of the wall).
$acc = ['status' => 'ACCEPTED', 'sent_at' => '2026-01-01', 'unlocked_until' => ''];
t_ok(quote_is_locked($acc) === true, 'an accepted quote is locked (changes require a revision)');

// The viewer predicate resolves correctly per role.
$pdo = db();
$mk = function($u, $role, $super = 0) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, is_superuser) VALUES (?,?,?,1,?)")
        ->execute([$u, ucfirst(strtolower($role)), $role, $super]);
    return (int)$pdo->lastInsertId();
};
$bdm   = $mk('c1_bdm',   'BUSINESS_DEV_MANAGER');
$mktgx = $mk('c1_mktgx', 'MARKETING_EXECUTIVE');
$fin   = $mk('c1_fin',   'FINANCE');
$coord = $mk('c1_coord', 'COORDINATOR');
$mast  = $mk('c1_master','MASTER_ADMIN', 1);

// The exact predicate the view uses ($canContract = can('crm.contract.register') || is_master()).
$isSalesViewer = function() {
    $canContract = can('crm.contract.register') || is_master();
    return (can('crm.quote.create') || can('crm.quote.send') || can('crm.followup.manage'))
        && !$canContract && !can('ops.call.create') && !is_master();
};
$as = function($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

$as($bdm);   t_ok($isSalesViewer() === true,  'a business-dev manager sees the sales handoff wall');
$as($mktgx); t_ok($isSalesViewer() === true,  'a marketing executive sees the sales handoff wall');
$as($fin);   t_ok($isSalesViewer() === false, 'finance (who registers the contract) does not see the sales wall');
$as($coord); t_ok($isSalesViewer() === false, 'a coordinator (operations) does not see the sales wall');
$as($mast);  t_ok($isSalesViewer() === false, 'a master admin does not see the sales wall');

unset($_SESSION['uid']); current_user(true); ua(true);
