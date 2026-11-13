<?php
// ============================================================================
//  Accounts register the contract for a won deal — driven as a REAL Finance login.
//
//  Bounce list 3.1 (owner, 13 Nov 2026). It was broken twice over:
//    · the dashboard's "Register" opened the deal page, which Finance may not open;
//    · and the handler itself turned Finance away with "You cannot change an
//      opportunity", because it sat below the "can edit deals" check.
//  An older check only asked "did Finance get some message back?" — and the message
//  it got was that refusal. So this one asks for the contract itself.
// ============================================================================
t_section('Accounts register a won deal — as the standard Finance login');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post)) . ' ' . escapeshellarg('[]');
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300)];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? [])) . (isset($r['error']) ? ' [' . $r['error'] . ']' : '');

$pdo = db(); opp_migrate(); contracts_migrate();
$tag = 'arw' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['ARW Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mkU = function ($first, $role) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'Arw', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$FIN = $mkU('Farah', 'FINANCE'); $BDM = $mkU('Bhavin', 'BUSINESS_DEV_MANAGER'); $INS = $mkU('Ishan', 'INSPECTOR');
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES (?,?,1,'ACTIVE')")->execute(['ARW Client ' . $tag, 'ARW Client ' . $tag]);
$cli = (int) $pdo->lastInsertId();
$mkOpp = function ($name) use ($pdo, $cli, $off, $tag) {
    $pdo->prepare("INSERT INTO opportunities (ref, name, partner_id, partner_name, value, status, office_id, sent_to_accounts_at, created_at) VALUES (?,?,?,?,?, 'WON', ?, ?, ?)")
        ->execute([strtoupper($tag) . '-' . mt_rand(100, 99999), $name, $cli, 'ARW Client', 250000, $off, date('c'), date('c')]);
    return (int) $pdo->lastInsertId();
};
$o1 = $mkOpp('Won deal one'); $o2 = $mkOpp('Won deal two'); $o3 = $mkOpp('Won deal three');
$row = fn($id) => ops_one("SELECT * FROM opportunities WHERE id=?", [$id]);

try {
    //  Arming: this Finance login is the ordinary one — it may register contracts, and may NOT open or change deals.
    $perms = role_perms('FINANCE');
    t_ok(in_array('crm.contract.register', $perms, true), 'ARMING · Finance holds the right to register contracts');
    t_ok(!in_array('mod.leads.view', $perms, true) && !in_array('mod.leads.edit', $perms, true), 'ARMING · and has no right to open or change deals');

    //  The screen opens for Finance, and lists the deal.
    $r = $drive($FIN, 'contracts-to-register', 'GET');
    t_ok(($r['done'] ?? '') === 'return' && empty($r['flash']), 'Finance opens "Contracts to register" — no refusal: ' . $said($r));
    $r = $drive($INS, 'contracts-to-register', 'GET');
    t_ok(($r['done'] ?? '') !== 'return' || !empty($r['flash']), 'control: an inspector is turned away from it');

    //  One press, from the list.
    $r = $drive($FIN, 'opportunity-contract', 'POST', [], ['id' => $o1, 'from' => 'list', 'auto_contract' => '1']);
    $c1 = (int) $row($o1)['contract_id'];
    t_ok($c1 > 0, 'Finance presses Register and the contract EXISTS (it used to answer "You cannot change an opportunity"): ' . $said($r));
    t_ok(strpos($said($r), 'You cannot change an opportunity') === false && strpos($said($r), 'registered') !== false, 'the message says it is registered, and who it waits for: ' . $said($r));
    $pc = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$c1]);
    t_eq([(int)$pc['partner_id'], (string)$pc['open_status'], (float)$pc['value'], (string)$pc['requested_by']], [$cli, 'PENDING', 250000.0, 'Farah Arw'],
        'the contract is for the deal\'s customer, at the deal\'s value, waiting for approval, asked for by Finance');
    t_ok(trim((string)$pc['contract_number']) !== '', 'it took the next contract number: ' . $pc['contract_number']);

    //  The client's own number, typed.
    $r = $drive($FIN, 'opportunity-contract', 'POST', [], ['id' => $o2, 'from' => 'list', 'contract_number' => strtoupper($tag) . '/PO/77', 'start_date' => '2026-12-01', 'end_date' => '2027-11-30']);
    $pc2 = ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int) $row($o2)['contract_id']]);
    t_eq([(string)($pc2['contract_number'] ?? ''), (string)($pc2['start_date'] ?? ''), (string)($pc2['end_date'] ?? '')], [strtoupper($tag) . '/PO/77', '2026-12-01', '2027-11-30'],
        '"Enter it differently" keeps the client\'s own number and the dates');

    //  Refusals come back to the list too, and change nothing.
    $r = $drive($FIN, 'opportunity-contract', 'POST', [], ['id' => $o1, 'from' => 'list', 'auto_contract' => '1']);
    t_ok(strpos($said($r), 'already been registered') !== false && (int) $row($o1)['contract_id'] === $c1, 'a second press on the same deal is refused: ' . $said($r));
    $r = $drive($BDM, 'opportunity-contract', 'POST', [], ['id' => $o3, 'auto_contract' => '1']);
    t_eq((int) $row($o3)['contract_id'], 0, 'control: a salesperson, who may not register contracts, still cannot: ' . $said($r));
    $r = $drive($INS, 'opportunity-contract', 'POST', [], ['id' => $o3, 'from' => 'list', 'auto_contract' => '1']);
    t_eq((int) $row($o3)['contract_id'], 0, 'control: nor can an inspector');

    //  Moving the door did not open anything else to Finance.
    $r = $drive($FIN, 'opportunity-send-to-accounts', 'POST', [], ['id' => $o3]);
    t_ok(empty($r['flash']) || strpos($said($r), 'Sent to Accounts') === false, 'Finance still cannot act on the deal itself (send, edit, move)');
    $r = $drive($FIN, 'opportunity-edit', 'POST', [], ['id' => $o3, 'name' => 'Changed by Finance']);
    t_eq((string) $row($o3)['name'], 'Won deal three', '…a deal\'s details are untouched by Finance');
} finally {
    foreach ([$o1, $o2, $o3] as $id) $pdo->prepare("DELETE FROM opportunities WHERE id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM partner_contracts WHERE partner_id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
