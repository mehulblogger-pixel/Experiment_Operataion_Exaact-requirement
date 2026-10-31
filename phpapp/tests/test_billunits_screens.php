<?php
// Universal man-day / man-month billing — stage 5 screens: the job's Billing
// panel, the "Due to bill now" board and the contract's billing terms, and who
// sees which control. (The actions themselves are tested in the stage 1–4 files.)
t_section('the billing screens show earned / due / billed, and only the right controls');

$pdo = db();
billunit_migrate();

// Leave the shared test database as found: the invoices (and their numbers),
// lines and billing records this file creates are removed at the end, so a later
// file that types an invoice number by hand never collides with ours.
$__bu_inv0 = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM invoices");
$__bu_ev0  = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM billable_events");
$__bu_bb0  = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM billable_bills");
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state,gstin) VALUES ('Screen Co','Screen Co',1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute();
$pid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (name,is_active) VALUES ('Screen HQ',1)")->execute();
$off = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO calls (client_id, billable_basis, billable_rate, contract_number) VALUES (?, 'MANDAY', 9000, 'CN-SCR')")->execute([$pid]);
$call = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, created_at) VALUES ('SCR-J', ?, ?, ?)")->execute([$call, $off, date('c')]);
$jobId = (int)$pdo->lastInsertId();
$m1 = date('Y-m-01', strtotime('first day of -1 month'));
foreach ([1, 2, 3] as $k) $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, status) VALUES (?,?,'DONE')")->execute([$jobId, date('Y-m-d', strtotime("$m1 +$k day"))]);
$pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, is_active) VALUES (?, 'CN-SCR', 'Screen contract', 1)")->execute([$pid]);
$conId = (int)$pdo->lastInsertId();

$render = function () use ($jobId) {
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [$jobId]);
    ob_start(); include __DIR__ . '/../views/ops/_job_billing.php'; return ob_get_clean();
};
$as = function ($role) use ($pdo) {
    static $n = 0; $n++;
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES (?, ?, ?, 1)")->execute(['bus_' . $role . $n, $role, $role]);
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); ua(true);
};

$as('ADMIN');
$h = $render();
t_ok(strpos($h, 'Billing — man-days') !== false, 'the job shows its Billing panel');
t_ok(strpos($h, 'Man-days earned') !== false && preg_match('/Man-days earned<\/div><div class="v">3</', $h), 'three closed visit days read as 3 man-days earned');
t_ok(strpos($h, 'waiting — due at closure') !== false, 'each day says why it is not yet due (at closure, by default)');
t_ok(strpos($h, 'Raise invoice for what is due') === false, 'nothing is due, so no invoice button');
t_ok(strpos($h, 'name="triggers[]"') !== false, 'the billing terms can be set on the job');
t_ok(strpos($h, 'End early') !== false && strpos($h, 'Add a charge') !== false, 'an administrator sees End early and Add a charge');

// Monthly billing on the contract → the past month is due; the job inherits it.
$pdo->prepare("UPDATE partner_contracts SET billing_triggers='PERIOD', billing_period='MONTHLY' WHERE id=?")->execute([$conId]);
$h = $render();
t_ok(strpos($h, 'set by the contract') !== false, 'the job says its terms come from the contract');
t_ok(strpos($h, 'Raise invoice for what is due (' . cur_sym() . '27,000)') !== false, 'the finished month is due: the button bills 3 × 9,000');

// The board lists them under the client, ready to tick.
$g = billunit_due_groups();
$mine = array_values(array_filter($g, fn($x) => (int)$x['party_id'] === $pid));
t_eq([count($mine), count($mine[0]['units'] ?? []), (float)($mine[0]['total'] ?? 0)], [1, 3, 27000.0], 'the board groups the three due days under Screen Co, 27,000');
ob_start(); $rows = []; $roll = []; $statuses = BILLABLE_STATUS; $status = ''; $canManage = true;
include __DIR__ . '/../views/ops/billable_events.php'; $b = ob_get_clean();
t_ok(strpos($b, 'Due to bill now') !== false && strpos($b, 'action="/billable-invoice"') !== false, 'the board shows "Due to bill now" with one-invoice-per-client forms');

// The contract page carries the billing terms.
$c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$conId]);
$terms = billunit_terms_form_html('/contract-billing', ['contract_id' => $conId], $c['billing_triggers'], $c['billing_period'], true, '');
t_ok(strpos($terms, 'value="PERIOD" checked') !== false, 'the contract form shows its saved trigger ticked');

// A coordinator may set job terms but not add charges or release units (finance).
$as('COORDINATOR');
$h = $render();
t_ok(billunit_can_set_job_terms() && !billunit_can_adjust(), 'a coordinator may set job terms but not add money');
t_ok(strpos($h, 'Add a charge') === false && strpos($h, 'Release ticked for billing now') === false, 'so a coordinator sees no charge or release controls');
// An inspector sees the figures but changes nothing.
$as('INSPECTOR');
t_ok(!billunit_can_set_job_terms() && !billunit_can_end_early() && !billunit_can_adjust(), 'an inspector cannot change terms, end early or add charges');
unset($_SESSION['uid']); current_user(true); ua(true);

// ---- cleanup (see the top of the file) ----
db()->prepare("DELETE FROM invoice_lines WHERE invoice_id > ?")->execute([$__bu_inv0]);
db()->prepare("DELETE FROM invoices WHERE id > ?")->execute([$__bu_inv0]);
db()->prepare("DELETE FROM billable_bills WHERE id > ?")->execute([$__bu_bb0]);
db()->prepare("DELETE FROM billable_events WHERE id > ?")->execute([$__bu_ev0]);
