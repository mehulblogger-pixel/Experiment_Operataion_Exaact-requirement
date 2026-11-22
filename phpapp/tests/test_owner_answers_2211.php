<?php
// Owner's answers of 22 Nov 2026 to the Operations test book (F-OP-01..05, Q-OP-01..04)
// and Sales answer 9 (a switch for adding a customer from a won deal with no lead).
//
//   1. F-OP-01 — only the engineer assigned to a job records its site check-in.
//   2. F-OP-02 — a delay needs its reason and who is responsible.
//   3. F-OP-03 / Q-OP-01 / Q-OP-02 / Q-OP-03 — a call's received date is not after today,
//      the client's required date is not before it, and the unit rate is 0 … the ceiling
//      in Settings (the owner's ₹2,50,000), checked only on what a save changes.
//   4. F-OP-04 — two people editing a never-edited call: the second save is refused.
//   5. F-OP-05 — the two allocation messages read correctly.
//   6. Q-OP-04 — recording an invoice / payment on a job is Finance's.
//   7. Answer 9 — "Add a customer from a won deal that had no lead" is a switch, held on
//      day one by exactly the people the old rule let in.
t_section('Owner answers 22 Nov 2026 — Operations fixes and the add-customer switch');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$made = ['calls' => [], 'jobs' => [], 'visits' => [], 'delays' => []];
$prevMax = setting_get('call_rate_max', null);
try {
    bbv_as($f['master']);
    if (function_exists('tosrm_migrate_d')) tosrm_migrate_d();
    if (function_exists('trust_migrate')) trust_migrate();

    // ---- A call and a job for Amit (Mumbai) --------------------------------------
    $pdo->prepare("INSERT INTO calls (call_code, executing_office_id, ibo_office_id, call_received_date, inspection_required_date, billable_rate, status, created_by, created_at)
                   VALUES (?,?,?,?,?,?, 'OPEN', 'test', ?)")
        ->execute(['OA-' . $f['tag'], $f['mum'], $f['mum'], date('Y-m-d', strtotime('-3 days')), date('Y-m-d', strtotime('+2 days')), 5000, date('c')]);
    $callId = (int) $pdo->lastInsertId(); $made['calls'][] = $callId;
    $pdo->prepare("INSERT INTO jobs (call_id, job_code, inspector_id, executing_office_id, scheduled_date, closed_flag, created_at) VALUES (?,?,?,?,?,0,?)")
        ->execute([$callId, 'OAJ-' . $f['tag'], $f['amit_ins'], $f['mum'], date('Y-m-d'), date('c')]);
    $jobId = (int) $pdo->lastInsertId(); $made['jobs'][] = $jobId;

    // ---- 1. F-OP-01 -----------------------------------------------------------------
    t_ok(site_checkin_is_assigned($jobId, $f['amit_ins']), 'F-OP-01 · the job\'s own engineer is assigned');
    t_ok(!site_checkin_is_assigned($jobId, $f['rahul_ins']), 'F-OP-01 · another engineer is not');
    t_ok(!site_checkin_is_assigned($jobId, 0), 'F-OP-01 · a login with no engineer record (coordinator, sales) is not');
    try {
        $pdo->prepare("INSERT INTO job_visits (job_id, inspector_id, visit_date) VALUES (?,?,?)")->execute([$jobId, $f['rahul_ins'], date('Y-m-d')]);
        $made['visits'][] = (int) $pdo->lastInsertId();
        t_ok(site_checkin_is_assigned($jobId, $f['rahul_ins']), 'F-OP-01 · an engineer given a day of the job in the per-day plan is assigned');
    } catch (Throwable $e) { t_ok(true, 'F-OP-01 · (no per-day plan table on this install)'); }
    $n0 = (int) ops_val("SELECT COUNT(*) FROM site_visits WHERE job_id=?", [$jobId]);
    $r = bbv_route('site-checkin', 'POST', $f['neha'], 0, ['job_id' => $jobId, 'kind' => 'ENTRY', 'gps' => '19.07,72.87,10']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM site_visits WHERE job_id=?", [$jobId]), $n0, 'F-OP-01 · the coordinator\'s check-in on Amit\'s job is refused — nothing written');
    t_ok(strpos(implode(' ', $r['flash'] ?? []), 'Only the') !== false, 'F-OP-01 · …with the reason (' . implode(' | ', $r['flash'] ?? []) . ')');
    $r = bbv_route('site-checkin', 'POST', $f['sr'], 0, ['job_id' => $jobId, 'kind' => 'ENTRY', 'gps' => '19.07,72.87,10']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM site_visits WHERE job_id=?", [$jobId]), $n0, 'F-OP-01 · another engineer (not on the job) is refused too');

    // ---- 2. F-OP-02 -----------------------------------------------------------------
    t_ok(tosrm_delay_problem([]) !== '', 'F-OP-02 · an empty delay is refused: ' . tosrm_delay_problem([]));
    t_ok(strpos(tosrm_delay_problem(['reason' => 'CLIENT']), 'who is responsible') !== false, 'F-OP-02 · a reason without responsibility is refused');
    t_ok(tosrm_delay_problem(['reason' => 'MADE_UP', 'responsibility' => 'CLIENT']) !== '', 'F-OP-02 · a reason not on the list is refused');
    t_eq(tosrm_delay_problem(['reason' => 'CLIENT', 'responsibility' => 'CLIENT']), '', 'F-OP-02 · reason + responsibility from the lists is accepted');
    $d0 = (int) ops_val("SELECT COUNT(*) FROM job_delays WHERE job_id=?", [$jobId]);
    $tok = function_exists('csrf_token') ? csrf_token() : '';
    $r = bbv_route('delay-add', 'POST', $f['neha'], 0, ['job_id' => $jobId, '_csrf' => $tok]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM job_delays WHERE job_id=?", [$jobId]), $d0, 'F-OP-02 · posting an empty delay through the route writes nothing');

    // ---- 3. dates and rate ------------------------------------------------------------
    setting_set('call_rate_max', '');
    t_eq(call_rate_max(), 250000.0, 'Q-OP-03 · with nothing set, the ceiling is the owner\'s ₹2,50,000');
    $today = date('Y-m-d'); $tomorrow = date('Y-m-d', strtotime('+1 day')); $yday = date('Y-m-d', strtotime('-1 day'));
    [$m] = call_entry_problems(['call_received_date' => $tomorrow, 'inspection_required_date' => $tomorrow, 'billable_rate' => '1000'], null);
    t_ok(strpos($m, 'received date cannot be after today') !== false, 'Q-OP-01 · a new call received tomorrow is refused: ' . $m);
    [$m] = call_entry_problems(['call_received_date' => $today, 'inspection_required_date' => $yday, 'billable_rate' => '1000'], null);
    t_ok(strpos($m, 'required date cannot be earlier than the received date') !== false, 'Q-OP-02 · required before received is refused: ' . $m);
    [$m, $bad] = call_entry_problems(['call_received_date' => $today, 'inspection_required_date' => $today, 'billable_rate' => '-1000'], null);
    t_ok(strpos($m, 'cannot be below 0') !== false && in_array('billable_rate', $bad, true), 'F-OP-03 · a rate of −1,000 is refused');
    [$m] = call_entry_problems(['call_received_date' => $today, 'inspection_required_date' => $today, 'billable_rate' => '250001'], null);
    t_ok(strpos($m, 'cannot be above') !== false && strpos($m, 'Settings → Financial & norms') !== false, 'Q-OP-03 · ₹2,50,001 is refused and the message says where the limit is set: ' . $m);
    [$m] = call_entry_problems(['call_received_date' => $today, 'inspection_required_date' => $today, 'billable_rate' => '250000'], null);
    t_eq($m, '', 'Q-OP-03 · exactly ₹2,50,000 is accepted');
    setting_set('call_rate_max', '300000');
    [$m] = call_entry_problems(['call_received_date' => $today, 'inspection_required_date' => $today, 'billable_rate' => '260000'], null);
    t_eq($m, '', 'Q-OP-03 · the ceiling follows the setting (₹3,00,000 set → ₹2,60,000 accepted)');
    setting_set('call_rate_max', '0');
    [$m] = call_entry_problems(['call_received_date' => $today, 'inspection_required_date' => $today, 'billable_rate' => '99999999'], null);
    t_eq($m, '', 'Q-OP-03 · 0 means no ceiling');
    setting_set('call_rate_max', '');
    //  An existing call with old values is not blocked on an unrelated edit.
    $old = ['id' => 1, 'call_received_date' => $tomorrow, 'inspection_required_date' => $yday, 'billable_rate' => 900000];
    [$m] = call_entry_problems(['call_received_date' => $tomorrow, 'inspection_required_date' => $yday, 'billable_rate' => '900000'], $old);
    t_eq($m, '', 'an edit that changes none of the three is not blocked by older data');
    [$m] = call_entry_problems(['call_received_date' => $tomorrow, 'inspection_required_date' => $yday, 'billable_rate' => '900001'], $old);
    t_ok(strpos($m, 'cannot be above') !== false, '…but changing the rate is checked');

    // ---- 4. F-OP-04 -----------------------------------------------------------------
    $pdo->prepare("UPDATE calls SET updated_at='' WHERE id=?")->execute([$callId]);
    $r1 = bbv_route('call-edit', 'GET', $f['neha'], $callId);
    $v1 = (string) ops_val("SELECT updated_at FROM calls WHERE id=?", [$callId]);
    t_ok($v1 !== '', 'F-OP-04 · opening a version-less call stamps a version, so both editors carry a baseline');
    t_eq(stale_edit_block('calls', $callId, $v1), '', 'F-OP-04 · the first save (same baseline) is allowed');
    touch_row_version('calls', $callId);   // the first person's save
    t_ok(stale_edit_block('calls', $callId, $v1) !== '', 'F-OP-04 · the second person, holding the old baseline, is refused');

    // ---- 5. F-OP-05 -----------------------------------------------------------------
    $jf = file_get_contents(__DIR__ . '/../views/ops/job_form.php');
    t_ok(strpos($jf, "/ engineer, or a sub-contracting") === false && strpos($jf, "Choose <?= e(Tla('engineer')) ?>, or a sub-contracting agency") !== false, 'F-OP-05 · "Choose an inspection engineer / engineer" reads "Choose an inspection engineer, or a sub-contracting agency"');
    $ops = file_get_contents(__DIR__ . '/../lib/ops.php');
    t_ok(strpos($ops, "' Pick a ' . Tl('engineer')") === false && strpos($ops, "' Pick ' . Tla('engineer')") !== false, 'F-OP-05 · "Pick a inspection engineer" reads "Pick an inspection engineer"');
    t_eq(Tla('engineer'), (preg_match('/^[aeiou]/i', Tl('engineer')) ? 'an ' : 'a ') . Tl('engineer'), 'F-OP-05 · the article follows the company\'s word');

    // ---- 6. Q-OP-04 -----------------------------------------------------------------
    bbv_as($f['neha']);  t_ok(!job_invoice_record_can(), 'Q-OP-04 · a coordinator may not record an invoice / payment');
    bbv_as($f['meera']); t_ok(job_invoice_record_can(), 'Q-OP-04 · Finance may');
    bbv_as($f['admin']); t_ok(job_invoice_record_can(), 'Q-OP-04 · the Admin role keeps it (holds credit reconciliation)');
    bbv_as($f['master']); t_ok(job_invoice_record_can(), 'Q-OP-04 · the Master Admin keeps it');
    $r = bbv_route('job-invoice', 'POST', $f['neha'], $jobId, ['invoice_raised' => '1', 'invoice_number' => 'X-1']);
    t_eq((string) ops_val("SELECT COALESCE(invoice_number,'') FROM jobs WHERE id=?", [$jobId]), '', 'Q-OP-04 · the coordinator\'s POST writes nothing');
    $r = bbv_route('job-invoice', 'POST', $f['meera'], $jobId, ['invoice_raised' => '1', 'invoice_number' => 'FIN-1']);
    t_eq((string) ops_val("SELECT invoice_number FROM jobs WHERE id=?", [$jobId]), 'FIN-1', 'Q-OP-04 · Finance\'s POST records it');

    // ---- 7. Answer 9 ----------------------------------------------------------------
    foreach (['MASTER_ADMIN' => true, 'ADMIN' => true, 'BUSINESS_DIRECTOR' => true, 'BUSINESS_DEV_MANAGER' => true, 'KEY_ACCOUNTS_MANAGER' => true,
              'MARKETING_MANAGER' => true, 'MARKETING_EXECUTIVE' => true, 'BRANCH_MANAGER' => false, 'SBU_HEAD' => false, 'COORDINATOR' => false,
              'FINANCE' => false, 'INSPECTOR' => false] as $role => $want) {
        $s = role_perms($role); $h = fn($p) => perm_set_has($s, $p);
        $oldRule = ($h('mod.leads.edit') || $h('mod.inquiries.edit')) && ($h('mod.leads.add') || $h('mod.leads.edit'));
        $has = $role === 'MASTER_ADMIN' || in_array('crm.deal.add_customer', $s, true);
        t_ok($has === $want && ($role === 'MASTER_ADMIN' || $has === $oldRule), "answer 9 · $role " . ($want ? 'holds' : 'does not hold') . ' the switch on day one — the old rule says ' . ($oldRule ? 'yes' : 'no'));
    }
    t_ok(isset(PERMISSIONS['crm.deal.add_customer']) && in_array('crm.deal.add_customer', permission_groups()['Marketing & Sales (CRM)'], true), 'answer 9 · the switch is in the permission list, under Marketing & Sales');
    $wc = file_get_contents(__DIR__ . '/../lib/whocan.php');
    t_ok(strpos($wc, "whocan_row('crm.deal.add_customer'") !== false, 'answer 9 · and on Who can do what (Deals)');
    //  A saved grid (stamped @split1) gets it by the one-time rule, on the old rule only.
    $prevRA = setting_get('role_access', ''); $prevApplied = setting_get('access_owner_grants_applied', '');
    setting_set('role_access', json_encode(['BUSINESS_DEV_MANAGER' => ['mod.leads.view', 'mod.leads.add', 'mod.leads.edit', PERM_SPLIT_TAG],
                                            'COORDINATOR'          => ['mod.leads.view', PERM_SPLIT_TAG]]));
    setting_set('access_owner_grants_applied', implode(',', array_diff(array_keys(access_owner_grants()), ['ADD-CUSTOMER-SWITCH-2026-11-22'])));
    $done = access_apply_owner_grants(true);
    $st = json_decode((string) setting_get('role_access', ''), true);
    t_ok(in_array('ADD-CUSTOMER-SWITCH-2026-11-22', $done, true) && in_array('crm.deal.add_customer', $st['BUSINESS_DEV_MANAGER'], true), 'answer 9 · a saved BDM grid that works leads gains the switch once');
    t_ok(!in_array('crm.deal.add_customer', $st['COORDINATOR'], true), 'answer 9 · a saved grid that only views leads does not');
    t_eq(access_apply_owner_grants(true), [], 'answer 9 · the one-time rule does not run twice');
    setting_set('role_access', (string) $prevRA); setting_set('access_owner_grants_applied', (string) $prevApplied);
} finally {
    setting_set('call_rate_max', $prevMax === null ? '' : (string) $prevMax);
    $del = function ($table, $ids) use ($pdo) { if ($ids) try { $pdo->exec("DELETE FROM $table WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")"); } catch (Throwable $e) {} };
    try { if ($made['jobs']) $pdo->exec("DELETE FROM site_visits WHERE job_id IN (" . implode(',', $made['jobs']) . ")"); } catch (Throwable $e) {}
    try { if ($made['jobs']) $pdo->exec("DELETE FROM job_delays WHERE job_id IN (" . implode(',', $made['jobs']) . ")"); } catch (Throwable $e) {}
    $del('job_visits', $made['visits']); $del('jobs', $made['jobs']); $del('calls', $made['calls']);
    bbv_cleanup($f);
}
