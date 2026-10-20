<?php
// ============================================================================
//  Contract amendments — the owner's answer of 20 Oct 2026 (D-25, "Yes"): a
//  change to an open contract's value, dates or quantities takes the same two
//  signatures as its opening, and the values it replaces are kept.
//  Before: an edit changed an open contract's value and dates at once, with no
//  approval and no trace of the old figures; "Reopen" on an open contract wiped
//  both of its signatures (Sales audit SA-19).
// ============================================================================
t_section('Sales — an amendment to an open contract takes two signatures and keeps the old figures');

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
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? []));
$pdo = db();
contracts_migrate();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('CA Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$FIN = $mk('ca_fin', 'FINANCE');            // registers and edits contracts
$OM  = $mk('ca_om',  'OPERATION_MANAGER');  // endorses
$BM  = $mk('ca_bm',  'BRANCH_MANAGER');     // approves
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('CA Client','CA Client',1,'ACTIVE')")->execute();
$cli = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, start_date, end_date, branch_id, open_status, is_active,
                 mgr_endorsed_by, mgr_endorsed_by_id, mgr_endorsed_at, bm_approved_by, bm_approved_by_id, bm_approved_at)
               VALUES (?,?,?,?,?,?,?,'OPEN',1,'Ca_om Probe',?,?,'Ca_bm Probe',?,?)")
    ->execute([$cli, 'CA-2026-01', 'Annual inspection', 500000, '2026-04-01', '2027-03-31', $off, $OM, date('c'), $BM, date('c')]);
$cid = (int) $pdo->lastInsertId();
contract_replace_lines($cid, [['description' => 'Inspection', 'quantity' => 100, 'unit' => 'MANDAY']]);
$c   = fn() => ops_one("SELECT * FROM partner_contracts WHERE id=?", [$cid]);
$am  = fn() => ops_one("SELECT * FROM contract_amendments WHERE contract_id=? ORDER BY id DESC LIMIT 1", [$cid]);
$edit = fn(array $x = []) => $drive($FIN, 'contract-edit', 'POST', [], array_merge(['id' => $cid, 'title' => 'Annual inspection', 'sbu' => '',
    'value' => '500000', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'notes' => '',
    'cl_desc' => ['Inspection'], 'cl_qty' => ['100'], 'cl_unit' => ['MANDAY']], $x));

// ---- a non-commercial edit saves at once --------------------------------------------------------
$edit(['title' => 'Annual third-party inspection', 'notes' => 'Site contact changed']);
t_eq([(string) $c()['title'], (string) $c()['notes']], ['Annual third-party inspection', 'Site contact changed'], 'a change of title or notes saves at once');
t_ok(!$am(), '…and is not an amendment');

// ---- a change of value is an amendment ----------------------------------------------------------
$r = $edit(['title' => 'Annual third-party inspection', 'value' => '650000', 'end_date' => '2027-06-30']);
t_eq((float) $c()['value'], 500000.0, 'a new value without a reason is not applied: ' . $said($r));
$r = $edit(['title' => 'Annual third-party inspection', 'value' => '650000', 'end_date' => '2027-06-30',
            'cl_qty' => ['130'], 'amend_reason' => 'PO amendment 2 — three more months']);
t_eq([(float) $c()['value'], (string) $c()['end_date'], (float) contract_lines($cid)[0]['quantity']], [500000.0, '2027-03-31', 100.0],
     'with a reason it becomes an amendment — the contract keeps its signed figures meanwhile: ' . $said($r));
$a = $am();
t_eq((string) $a['status'], 'PENDING', 'the amendment waits for its signatures');
$old = json_decode((string) $a['old_values'], true); $new = json_decode((string) $a['new_values'], true);
t_eq([(float) $old['value'], (float) $new['value'], (string) $new['end_date']], [500000.0, 650000.0, '2027-06-30'], 'it holds the old figures and the new');
$r = $edit(['value' => '700000', 'amend_reason' => 'Another']);
t_eq((int) ops_val("SELECT COUNT(*) FROM contract_amendments WHERE contract_id=?", [$cid]), 1, 'a second amendment waits until the first is decided');

// ---- two signatures, by two people --------------------------------------------------------------
$drive($BM, 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'amend-approve']);
t_eq((string) $am()['status'], 'PENDING', 'the branch manager cannot approve before a manager endorses');
$drive($FIN, 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'amend-endorse']);
t_eq((string) $am()['endorsed_at'], '', 'Finance, who asked for it, cannot endorse it');
$drive($OM, 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'amend-endorse']);
t_ok(trim((string) $am()['endorsed_at']) !== '', 'the operation manager endorses it');
$drive($OM, 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'amend-approve']);
t_eq((string) $am()['status'], 'PENDING', 'the endorser cannot also approve it');
$r = $drive($BM, 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'amend-approve']);
t_eq((string) $am()['status'], 'APPROVED', 'the branch manager approves it: ' . $said($r));
t_eq([(float) $c()['value'], (string) $c()['end_date'], (float) contract_lines($cid)[0]['quantity']], [650000.0, '2027-06-30', 130.0],
     'only now does the contract carry the new figures');
t_eq((float) json_decode((string) $am()['old_values'], true)['value'], 500000.0, 'and the old figures are kept on the amendment');
t_eq((string) $c()['bm_approved_by'], 'Ca_bm Probe', 'the opening signatures are untouched');
$pg = $drive($FIN, 'contract', 'GET', ['id' => $cid], [], ['Amendments', 'PO amendment 2']);
t_ok(!empty($pg['has']['Amendments']) && !empty($pg['has']['PO amendment 2']), 'the contract page lists the amendment, with its reason');

// ---- a rejected amendment changes nothing --------------------------------------------------------
$edit(['value' => '900000', 'end_date' => '2027-06-30', 'cl_qty' => ['130'], 'amend_reason' => 'Try']);
$drive($OM, 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'amend-reject', 'note' => '']);
t_eq((string) $am()['status'], 'PENDING', 'rejecting needs a reason');
$drive($OM, 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'amend-reject', 'note' => 'Not agreed with the customer']);
t_eq([(string) $am()['status'], (float) $c()['value']], ['REJECTED', 650000.0], 'a rejected amendment leaves the contract as it was');

// ---- "Reopen" no longer wipes an open contract's signatures -----------------------------------------
$r = $drive($FIN, 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'reopen']);
t_eq([(string) $c()['open_status'], (string) $c()['bm_approved_by']], ['OPEN', 'Ca_bm Probe'], 'an open contract cannot be "re-opened" — its signatures stay: ' . $said($r));
$pdo->prepare("UPDATE partner_contracts SET open_status='CLOSED', is_active=0 WHERE id=?")->execute([$cid]);
$drive($FIN, 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'reopen']);
t_eq((string) $c()['open_status'], 'PENDING', 'a closed one is re-opened for two new signatures, as before');

// ---- a contract still awaiting its signatures --------------------------------------------------------
$pdo->prepare("UPDATE partner_contracts SET mgr_endorsed_by='Ca_om Probe', mgr_endorsed_by_id=?, mgr_endorsed_at=? WHERE id=?")->execute([$OM, date('c'), $cid]);
$edit(['value' => '660000', 'end_date' => '2027-06-30', 'cl_qty' => ['130']]);
t_eq([(float) $c()['value'], (string) $c()['mgr_endorsed_at']], [660000.0, ''],
     'before it is opened, its figures can change — and an endorsement given for the old figures is withdrawn');
