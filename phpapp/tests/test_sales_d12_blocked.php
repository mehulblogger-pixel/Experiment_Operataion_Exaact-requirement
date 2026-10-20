<?php
// ============================================================================
//  The owner's rule D-12 (20 Oct 2026): a blacklisted, inactive, on-hold or
//  blocked customer gets "nothing, only the payment reminder. They can be
//  unblocked by a person."
//
//  Before it, a BLACKLISTED or INACTIVE customer was never stopped anywhere, and
//  a BLOCKED one only for a non-manager's new quotation (Sales audit SA-17). Each
//  door is driven through the real dispatcher as the real role, each refusal
//  beside a control that shows the same door open for an active customer.
// ============================================================================
t_section('Sales — D-12: a blocked customer gets nothing new, only payment reminders');

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
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('D12 Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$client = function ($name, $status = 'ACTIVE', $hold = '') use ($pdo) {
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status, hold_status, hold_reason) VALUES (?,?,1,?,?,?)")
        ->execute([$name, $name, $status, $hold, $hold !== '' ? 'Overdue 120 days' : '']);
    return (int) $pdo->lastInsertId();
};
$ME  = $mk('d12_me',  'MARKETING_EXECUTIVE');
$BDM = $mk('d12_bdm', 'BUSINESS_DEV_MANAGER');
$FIN = $mk('d12_fin', 'FINANCE');
$CO  = $mk('d12_co',  'COORDINATOR');

// ---- the rule itself ----------------------------------------------------------------
$open = $client('D12 Active Co');
t_eq(partner_trade_block($open), '', 'an active customer is open for business');
t_eq(partner_trade_block($client('D12 Prospect Co', 'PROSPECT')), '', 'a prospect is open for business');
foreach (['BLACKLISTED', 'INACTIVE', 'ON_HOLD'] as $s)
    t_ok(strpos(partner_trade_block($client("D12 $s Co", $s)), 'only payment reminders') !== false, "a customer whose status is $s gets nothing new");
foreach (['HOLD', 'BLOCKED'] as $h)
    t_ok(strpos(partner_trade_block($client("D12 $h hold Co", 'ACTIVE', $h)), 'Client holds') !== false, "a customer on a $h credit hold gets nothing new, and is told where it is cleared");

// ---- the doors ----------------------------------------------------------------------
$black = $client('D12 Blacklisted Ltd', 'BLACKLISTED');
$enq = fn($cid) => ['client_id' => $cid, 'contact_name' => 'Ravi', 'contact_email' => 'ravi@example.test', 'sbu' => 'TPI', 'subject' => 'Inspection'];
$n0 = (int) ops_val("SELECT COUNT(*) FROM crm_inquiries");
$r = $drive($ME, 'inquiry-new', 'POST', [], $enq($black));
t_eq((int) ops_val("SELECT COUNT(*) FROM crm_inquiries"), $n0, 'no new enquiry for a blacklisted customer');
t_ok(strpos($said($r), 'only payment reminders') !== false, '…and the refusal says why and how it is cleared: ' . $said($r));
$drive($ME, 'inquiry-new', 'POST', [], $enq($open));
t_eq((int) ops_val("SELECT COUNT(*) FROM crm_inquiries"), $n0 + 1, 'control: the same enquiry for an active customer is recorded');

$qf = fn($cid) => ['client_id' => $cid, 'subject' => 'Inspection', 'office_id' => $off, 'validity_days' => 30, 'gst_pct' => 18,
    'l_desc' => ['Inspection'], 'l_qty' => ['1'], 'l_unit' => ['MANDAY'], 'l_rate' => ['5000']];
$q0 = (int) ops_val("SELECT COUNT(*) FROM quotations");
$drive($BDM, 'quote-new', 'POST', [], $qf($black));
t_eq((int) ops_val("SELECT COUNT(*) FROM quotations"), $q0, 'no new quotation for a blacklisted customer — and a Business Development Manager is no exception');
$drive($BDM, 'quote-new', 'POST', [], $qf($open));
t_eq((int) ops_val("SELECT COUNT(*) FROM quotations"), $q0 + 1, 'control: the same quotation for an active customer is created');

// A quotation already in hand when the customer is blocked does not move on.
$mkq = function ($cid, $status) use ($pdo, $BDM, $off) {
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_id, client_name, office_id, subtotal, gst_pct, gst_amount, total_amount,
                   owner_id, created_by, created_at, sent_at) VALUES (?,0,1,?,?,'x',?,5000,18,900,5900,?,'d12_bdm',?,?)")
        ->execute(['D12-' . uniqid(), $status, $cid, $off, $BDM, date('c'), $status === 'SENT' || $status === 'ACCEPTED' ? date('c') : '']);
    return (int) $pdo->lastInsertId();
};
$st = fn($id) => (string) ops_val("SELECT status FROM quotations WHERE id=?", [$id]);
$late = $client('D12 Later Blocked Ltd');
$qs = $mkq($late, 'SENT'); $qd = $mkq($late, 'DRAFT'); $qa = $mkq($late, 'ACCEPTED');
$pdo->prepare("UPDATE business_partners SET hold_status='BLOCKED', hold_reason='Overdue' WHERE id=?")->execute([$late]);
$drive($BDM, 'quote-status', 'POST', ['id' => $qs], ['to' => 'ACCEPTED']);
t_eq($st($qs), 'SENT', 'a quotation already sent cannot be accepted once the customer is blocked');
$drive($BDM, 'quote-status', 'POST', ['id' => $qd], ['to' => 'PENDING_APPROVAL']);
t_eq($st($qd), 'DRAFT', '…a draft cannot be submitted for approval');
$drive($BDM, 'quote-status', 'POST', ['id' => $qs], ['to' => 'LOST', 'lost_reason' => 'PRICE']);
t_eq($st($qs), 'LOST', '…but it can still be marked lost — that closes business, it opens none');
$c0 = (int) ops_val("SELECT COUNT(*) FROM partner_contracts");
$drive($FIN, 'quote-contract', 'POST', ['id' => $qa], ['auto_contract' => '1']);
t_eq((int) ops_val("SELECT COUNT(*) FROM partner_contracts"), $c0, 'no contract is registered for a blocked customer, even on an accepted quotation');

// ---- a work order and a job -----------------------------------------------------------
$r = $drive($CO, 'call-new', 'POST', [], ['client_id' => $black, 'executing_office_id' => $off, 'ibo_office_id' => $off], ['only payment reminders']);
t_ok(!empty($r['has']['only payment reminders']), 'no new work order for a blacklisted customer — the form says why');
$pdo->prepare("INSERT INTO calls (call_code, client_id, status, executing_office_id, ibo_office_id, created_at) VALUES ('D12-CALL',?,'OPEN',?,?,?)")
    ->execute([$late, $off, $off, date('c')]);
$call = (int) $pdo->lastInsertId();
$r = $drive($CO, 'job-new', 'GET', ['call' => $call]);
t_ok(strpos($said($r), 'only payment reminders') !== false || strpos($said($r), 'Client holds') !== false,
     'no new job on a work order already raised, once its customer is blocked: ' . $said($r));

// ---- what they owe is still billed and reminded ------------------------------------------
t_as_admin();
if (function_exists('books_invoice_create')) {
    $inv = books_invoice_create(['partner_id' => $late, 'office_id' => $off]);
    t_ok(!empty($inv['id']), 'an invoice for work already done can still be raised for a blocked customer — the payment reminder needs one');
}

// ---- unblocked by a person -------------------------------------------------------------------
partner_hold_set($late, '', '');
t_eq(partner_trade_block($late), '', 'once a manager clears the hold, the customer is open for business again');
$pdo->prepare("UPDATE business_partners SET status='ACTIVE' WHERE id=?")->execute([$black]);
$drive($ME, 'inquiry-new', 'POST', [], $enq($black));
t_eq((int) ops_val("SELECT COUNT(*) FROM crm_inquiries"), $n0 + 2, '…and a customer made Active again can be given an enquiry');
