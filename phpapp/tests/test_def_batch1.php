<?php
// Sales defect register, batch 1 (owner, 21 Nov 2026: "let us fix all the defects").
// Each check below failed on 62d55c3 and passes after the fix.
//   DEF-08  Orders "Received this month", "Days to get paid" and the client register's
//           "Owed to us" ignored the person's branches (and the person picked).
//   DEF-09  Receivables "Open →" opened /job with an invoice id; "Due" always "not set".
//   DEF-02  Editing a quotation wiped each line's sub-types and deliverables.
//   DEF-04  Rewording a pre-order checklist point cleared its ticks on every quotation.
//   DEF-19  /lead-edit without the deputation fields wiped the lead's deputation.
//   DEF-03  The retention-milestone choice carried the phone-hiding class.
t_section('Sales defects batch 1 — DEF-08, 09, 02, 04, 19, 03');

require_once __DIR__ . '/_bbv_fixture.php';
require_once __DIR__ . '/_bb_reports_fixture.php';     // bbr_drive(): one real route, one user, own process
$f = bbv_fixture();
$pdo = db();
$made = ['bp' => [], 'inv' => [], 'rc' => [], 'al' => [], 'q' => [], 'ql' => [], 'l' => []];
$ids = fn($k) => implode(',', array_map('intval', $made[$k] ?: [0]));
$oldItems = (string) setting_get('preorder_checklist_items', ''); $oldOn = (string) setting_get('preorder_checklist_on', '');
try {
    bbv_as($f['master']); if (function_exists('books_migrate')) books_migrate(); leads_migrate();
    $today = date('Y-m-d');

    // ---- One customer, billed from Mumbai and from Ahmedabad --------------------------
    $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status) VALUES (?,?,?,1,'ACTIVE')")
        ->execute([$f['tag'] . '-C', 'Batch1 Customer ' . $f['tag'], 'Batch1 Customer ' . $f['tag']]);
    $pid = (int) $pdo->lastInsertId(); $made['bp'][] = $pid;
    $inv = function ($office, $total, $status, $invDate, $due = '') use ($pdo, $pid, &$made, $f) {
        $pdo->prepare("INSERT INTO invoices (invoice_no, partner_id, partner_name, office_id, invoice_date, due_date, total, status) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$f['tag'] . '-I' . count($made['inv']), $pid, 'Batch1 Customer', $office, $invDate, $due, $total, $status]);
        $id = (int) $pdo->lastInsertId(); $made['inv'][] = $id; return $id;
    };
    $pay = function ($office, $invId, $amt) use ($pdo, $pid, &$made, $today, $f) {
        $pdo->prepare("INSERT INTO receipts (receipt_no, partner_id, office_id, receipt_date, amount) VALUES (?,?,?,?,?)")
            ->execute([$f['tag'] . '-R' . count($made['rc']), $pid, $office, $today, $amt]);
        $rid = (int) $pdo->lastInsertId(); $made['rc'][] = $rid;
        $pdo->prepare("INSERT INTO receipt_allocations (receipt_id, invoice_id, kind, amount, allocated_at) VALUES (?,?,'CASH',?,?)")->execute([$rid, $invId, $amt, date('c')]);
        $made['al'][] = (int) $pdo->lastInsertId();
    };

    // Before any of it: what each person sees today (other tests' rows may exist).
    bbv_as($f['meera']);  $m0 = (float) sx_received_this_month();
    bbv_as($f['rakesh']); $r0 = (float) sx_received_this_month();

    $iM = $inv($f['mum'], 1000, 'PAID', date('Y-m-d', strtotime('-20 days')));
    $iA = $inv($f['ahm'], 2000, 'PAID', date('Y-m-d', strtotime('-40 days')));
    $pay($f['mum'], $iM, 1000); $pay($f['ahm'], $iA, 2000);
    $oM = $inv($f['mum'], 500, 'ISSUED', $today, date('Y-m-d', strtotime('+10 days')));
    $oA = $inv($f['ahm'], 700, 'ISSUED', $today, date('Y-m-d', strtotime('+12 days')));

    // ---- DEF-08 ------------------------------------------------------------------------
    bbv_as($f['meera']);
    t_ok(ar_can() && !sx_scope_is_all(), 'DEF-08 · Meera (Finance, Mumbai only) may see receivables, for Mumbai');
    t_eq(round((float) sx_received_this_month() - $m0, 2), 1000.0, 'DEF-08 · Received this month for Meera grows by Mumbai\'s ₹1,000 only (was ₹3,000 with Ahmedabad\'s)');
    t_eq(sx_avg_days_to_pay(), 20, 'DEF-08 · Days to get paid for Meera = 20 (Mumbai\'s invoice only; Ahmedabad\'s 40-day one is not hers)');
    t_eq((float) books_outstanding($pid, true)['outstanding'], 500.0, 'DEF-08 · Owed to us, branch-limited: ₹500 (Mumbai) — not ₹1,200');
    $cols = sx_client_sales_cols([$pid]);
    t_eq((float) $cols[$pid]['owed'], 500.0, 'DEF-08 · client register, Sales view: Meera sees ₹500 owed');
    bbv_as($f['rakesh']);
    t_ok(sx_scope_is_all(), 'DEF-08 · Rakesh (central Finance) sees every branch');
    t_eq(round((float) sx_received_this_month() - $r0, 2), 3000.0, 'DEF-08 · Rakesh: Received this month grows by ₹3,000 (both branches) — unchanged');
    t_eq((float) sx_client_sales_cols([$pid])[$pid]['owed'], 1200.0, 'DEF-08 · Rakesh: ₹1,200 owed (both branches) — unchanged');
    t_eq((float) books_outstanding($pid)['outstanding'], 1200.0, 'DEF-08 · Customer 360 and the ledger keep the whole-customer figure (₹1,200)');
    // A person picked: the figures follow that person's orders (their invoices).
    t_eq((float) sx_received_this_month([$iM]), 1000.0, 'DEF-08 · one person picked → received this month against their orders\' invoices (₹1,000)');
    t_eq(sx_avg_days_to_pay([$iA]), 40, 'DEF-08 · one person picked → days to get paid over their orders\' invoices (40)');
    t_eq(sx_avg_days_to_pay([]), null, 'DEF-08 · a person with no invoices → no average ("—"), not the company\'s');
    bbv_as($f['kiran']);
    t_eq(sx_received_this_month(), ar_can() ? sx_received_this_month() : null, 'DEF-08 · without the receivables right nothing is read (unchanged rule)');

    // ---- DEF-09 ------------------------------------------------------------------------
    $r = bbr_drive($f['meera'], 'receivables', 'GET', ['client' => (string) $pid], [], ['/invoice?id=' . $oM, '/job?id=' . $oM, fdate(date('Y-m-d', strtotime('+10 days')))]);
    t_ok(!empty($r['has']['/invoice?id=' . $oM]) && empty($r['has']['/job?id=' . $oM]), 'DEF-09 · Receivables "Open →" opens the invoice, not a job with the invoice\'s number' . ($r['error'] ? ' — ' . $r['error'] : ''));
    t_ok(!empty($r['has'][fdate(date('Y-m-d', strtotime('+10 days')))]), 'DEF-09 · the Due column shows the invoice\'s due date');
    t_ok(empty($r['has']['/invoice?id=' . $oA]), 'DEF-09 · Meera still does not see Ahmedabad\'s invoice (scope unchanged)');

    // ---- DEF-02 ------------------------------------------------------------------------
    bbv_as($f['master']);
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, subject, status, created_at) VALUES (?,0,1,?,?,'Batch1','DRAFT',?)")
        ->execute([$f['tag'] . '-Q', $pid, 'Batch1 Customer', date('c')]);
    $qid = (int) $pdo->lastInsertId(); $made['q'][] = $qid;
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, service_type, subtypes, description, qty, unit, rate, amount, deliverables) VALUES (?,0,'',?,?,2,'MANDAY',5000,10000,?)")
        ->execute([$qid, 'Stage-wise', 'Third-party inspection at works', 'Inspection report, release note']);
    $made['ql'][] = (int) $pdo->lastInsertId();
    $r = bbr_drive($f['master'], 'quote-edit', 'GET', ['id' => (string) $qid], [], ['name="l_subtypes[]" value="Stage-wise"', 'name="l_deliv[]" value="Inspection report, release note"']);
    t_ok(!empty($r['has']['name="l_subtypes[]" value="Stage-wise"']), 'DEF-02 · the edit form now sends the line\'s sub-types back' . ($r['error'] ? ' — ' . $r['error'] : ''));
    t_ok(!empty($r['has']['name="l_deliv[]" value="Inspection report, release note"']), 'DEF-02 · …and its deliverables');
    // What the form posts on Save is what crm_save_lines() keeps.
    crm_save_lines($qid, ['l_desc' => ['Third-party inspection at works'], 'l_service' => [''], 'l_sbu' => [''], 'l_order' => ['LINE'], 'l_qty' => ['2'], 'l_unit' => ['MANDAY'],
                          'l_rate' => ['5000'], 'l_subtypes' => ['Stage-wise'], 'l_deliv' => ['Inspection report, release note'], 'l_office' => [''], 'l_location' => [''], 'l_activity' => ['']]);
    $ln = ops_one("SELECT subtypes, deliverables FROM quote_lines WHERE quote_id=?", [$qid]);
    t_eq([$ln['subtypes'], $ln['deliverables']], ['Stage-wise', 'Inspection report, release note'], 'DEF-02 · after Save the line keeps "Stage-wise" and its deliverables');
    $form = file_get_contents(__DIR__ . '/../views/ops/crm/quote_form.php');
    t_ok(strpos($form, "tr.querySelectorAll('input').forEach(function(i){ i.value=''; });") !== false, 'DEF-02 · a new line (+ Add line) still starts with both empty');

    // ---- DEF-04 ------------------------------------------------------------------------
    setting_set('preorder_checklist_on', '1');
    setting_set('preorder_checklist_items', "Scope reviewed\nClient not on hold");
    $kA = preorder_item_key('Scope reviewed'); $kB = preorder_item_key('Client not on hold');
    //  A quotation ticked BEFORE this fix: ticks only, no saved list.
    $pdo->prepare("UPDATE quotations SET preorder_checklist=? WHERE id=?")->execute([json_encode([$kA => ['by' => 'Kiran', 'at' => '2026-11-10T10:00:00+05:30'], $kB => ['by' => 'Kiran', 'at' => '2026-11-10T10:01:00+05:30']]), $qid]);
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_name, subject, status, created_at) VALUES (?,0,1,'X','Fresh','DRAFT',?)")->execute([$f['tag'] . '-Q2', date('c')]);
    $q2 = (int) $pdo->lastInsertId(); $made['q'][] = $q2;
    t_eq(preorder_checklist_progress(crm_quote_get($qid))['done'], 2, 'DEF-04 · before the rewording: 2 of 2 ticked');
    $r = bbr_drive($f['master'], 'preorder-checklist', 'POST', [], ['enabled' => '1', 'items' => "Scope, standards and deliverables reviewed\nClient not on hold"]);
    $sc = &settings_cache(); $sc = null; unset($sc);     // the save ran in its own process: re-read the settings here
    $q1 = crm_quote_get($qid);
    $pg = preorder_checklist_progress($q1);
    t_eq([$pg['done'], $pg['total']], [2, 2], 'DEF-04 · after rewording point 1, the ticked quotation still shows 2 of 2 (was 1 of 2)' . ($r['error'] ? ' — ' . $r['error'] : ''));
    t_eq(preorder_checklist_items_for($q1), ['Scope reviewed', 'Client not on hold'], 'DEF-04 · …against the wording it was ticked on');
    t_eq(preorder_checklist_state($q1)[$kA]['by'] ?? '', 'Kiran', 'DEF-04 · who ticked it and when are kept');
    t_eq(preorder_checklist_items_for(crm_quote_get($q2)), ['Scope, standards and deliverables reviewed', 'Client not on hold'], 'DEF-04 · a quotation not yet started gets the new wording');
    $r = bbr_drive($f['master'], 'quote-preorder-save', 'POST', [], ['id' => (string) $q2, 'pc' => [preorder_item_key('Scope, standards and deliverables reviewed') => '1']]);
    $p2 = preorder_checklist_progress(crm_quote_get($q2));
    t_eq([$p2['done'], $p2['total']], [1, 2], 'DEF-04 · ticking on the new quotation works on the new wording (1 of 2)');
    $r = bbr_drive($f['master'], 'preorder-checklist', 'POST', [], ['enabled' => '1', 'items' => "Scope, standards and deliverables reviewed\nClient not on hold\nEMD noted"]);
    $sc = &settings_cache(); $sc = null; unset($sc);
    t_eq(preorder_checklist_progress(crm_quote_get($q2))['total'], 2, 'DEF-04 · a started checklist keeps its own points when one is added later');

    // ---- DEF-19 ------------------------------------------------------------------------
    $L = lead_create(['company_name' => 'Batch1 Lead ' . $f['tag'], 'contact_phone' => '9825011111', 'source' => 'REFERRAL', 'value' => 100000,
                      'deputation_kind' => 'MANMONTH', 'manpower_count' => 3, 'manpower_skills' => 'NDT Level II', 'site_location' => 'Plant 4, Hazira']);
    $lid = (int) $L['id']; $made['l'][] = $lid;
    $dep = fn() => ops_one("SELECT deputation_kind, manpower_count, manpower_skills, site_location FROM leads WHERE id=?", [$lid]);
    t_eq(array_values($dep()), ['MANMONTH', 3, 'NDT Level II', 'Plant 4, Hazira'], 'DEF-19 · the lead starts with a man-month deputation of 3 at Plant 4');
    $r = bbr_drive($f['master'], 'lead-edit', 'POST', [], ['id' => (string) $lid, 'company_name' => 'Batch1 Lead ' . $f['tag'] . ' Ltd', 'contact_phone' => '9825011111', 'source' => 'REFERRAL', 'value' => '120000']);
    t_eq(array_values($dep()), ['MANMONTH', 3, 'NDT Level II', 'Plant 4, Hazira'], 'DEF-19 · an older form without the deputation fields leaves them as they were' . ($r['error'] ? ' — ' . $r['error'] : ''));
    t_eq((string) ops_val("SELECT company_name FROM leads WHERE id=?", [$lid]), 'Batch1 Lead ' . $f['tag'] . ' Ltd', 'DEF-19 · …while the fields it did send are saved');
    $r = bbr_drive($f['master'], 'lead-edit', 'POST', [], ['id' => (string) $lid, 'company_name' => 'Batch1 Lead ' . $f['tag'] . ' Ltd', 'contact_phone' => '9825011111', 'source' => 'REFERRAL',
                                                          'deputation_kind' => '', 'manpower_count' => '0', 'manpower_skills' => '', 'site_location' => '']);
    t_eq(array_values($dep())[0], '', 'DEF-19 · the lead page, which does send them, can still clear the deputation');
    $r = bbr_drive($f['master'], 'lead-edit', 'POST', [], ['id' => (string) $lid, 'company_name' => 'Batch1 Lead', 'deputation_kind' => 'MANMONTH', 'manpower_count' => '2', 'site_location' => '']);
    t_eq(array_values($dep())[0], '', 'DEF-19 · a man-month sent without a site is still refused (same rule as before)');

    // ---- DEF-03 ------------------------------------------------------------------------
    $ms = file_get_contents(__DIR__ . '/../views/ops/_milestones.php');
    t_ok(strpos($ms, 'class="ms-hide-sm" style="text-align:center"><input type="radio" name="ms_release"') === false && strpos($ms, 'name="ms_release"') !== false,
         'DEF-03 · the "Releases retention" choice no longer carries the phone-hiding class (checked in a real browser at 390 px too)');
} finally {
    setting_set('preorder_checklist_items', $oldItems); setting_set('preorder_checklist_on', $oldOn);
    foreach (['receipt_allocations' => 'al', 'receipts' => 'rc', 'invoices' => 'inv', 'quote_lines' => 'ql', 'quotations' => 'q', 'leads' => 'l', 'business_partners' => 'bp'] as $t => $k)
        if ($made[$k]) $pdo->exec("DELETE FROM $t WHERE id IN (" . $ids($k) . ")");
    bbv_cleanup($f);
}
