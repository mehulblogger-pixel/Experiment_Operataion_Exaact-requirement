<?php
// Contract 360 — search a contract (or click it on the client) and see EVERYTHING
// on one screen: commercial terms + opening trail, the source quotation, purchase
// orders, every inspection call under it, the jobs, the reports, and the money.
// Drill into any of them and the universal Back button steps you back out.
t_section('Contract 360 — the whole contract on one screen');

// The route is dispatched and the handler exists.
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "route === 'contract'") !== false && strpos($ops, 'ops_contract_360()') !== false,
    'the /contract route is dispatched to the 360 handler');
t_ok(function_exists('ops_contract_360'), 'the Contract 360 handler exists');

// Search has a Contracts register that opens the 360.
$search = file_get_contents(__DIR__ . '/../lib/search.php');
t_ok(strpos($search, "'/contract?id='") !== false && strpos($search, 'partner_contracts pc') !== false,
    'search can find a contract and open its 360');
// The client's Contracts tab links each contract number to the 360.
$detail = file_get_contents(__DIR__ . '/../views/detail.php');
t_ok(strpos($detail, '/contract?id=<?= (int)$c[\'id\'] ?>') !== false, 'the client\'s Contracts tab links to the 360');

// Render the view with a full stub thread and confirm every layer shows and links.
$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('C360 Client','C360 Client',1,'ACTIVE')")->execute();
$pid = (int)$pdo->lastInsertId();
$c = ['id'=>1, 'partner_id'=>$pid, 'contract_number'=>'CON/360/1', 'title'=>'Rate contract', 'open_status'=>'OPEN',
      'value'=>500000, 'start_date'=>'2026-04-01', 'end_date'=>'2027-03-31', 'branch_name'=>'Ahmedabad',
      'legal_name'=>'C360 Client', 'display_name'=>'C360 Client', 'notes'=>'', 'quotation_id'=>0,
      'requested_by'=>'Acc', 'requested_at'=>'2026-04-01', 'mgr_endorsed_at'=>'', 'bm_approved_at'=>''];
$quote  = ['id'=>7, 'quote_no'=>'Q-360', 'rev'=>0, 'status'=>'ACCEPTED', 'subject'=>'Inspection', 'total_amount'=>500000];
$pos    = [['id'=>3, 'po_number'=>'PO-9', 'po_type'=>'REGULAR', 'title'=>'PO one', 'value'=>200000, 'start_date'=>'2026-04-01', 'end_date'=>'']];
$calls  = [['id'=>11, 'call_code'=>'CALL-360', 'status'=>'OPEN', 'created_at'=>'2026-05-01', 'inspection_type'=>'', 'inspection_required_date'=>'', 'job_count'=>1, 'invoiced'=>100000]];
$jobs   = [['id'=>21, 'job_code'=>'JOB-360', 'stage'=>'ALLOCATED', 'closed_flag'=>1, 'invoice_raised'=>1, 'invoice_amount'=>100000, 'payment_received'=>0, 'payment_amount'=>0, 'inspector_name'=>'Ravi', 'call_code'=>'CALL-360', 'report_count'=>1]];
$reports= [['id'=>31, 'irn'=>'IRN-360', 'type_code'=>'IC', 'status'=>'ISSUED', 'finalized'=>1, 'job_id'=>21, 'job_code'=>'JOB-360']];
$money  = ['value'=>500000, 'invoiced'=>100000, 'received'=>0, 'outstanding'=>100000];
$canSeeMoney = true;

$html = (function() use ($c,$quote,$pos,$calls,$jobs,$reports,$money,$canSeeMoney) {
    ob_start(); include __DIR__ . '/../views/ops/contract_detail.php'; return ob_get_clean();
})();

t_ok(strpos($html, 'CON/360/1') !== false, 'the contract number is shown');
t_ok(strpos($html, '/quote?id=7') !== false, 'the source quotation links out');
t_ok(strpos($html, '/po?id=3') !== false, 'the purchase order links out');
t_ok(strpos($html, '/call?id=11') !== false && strpos($html, 'CALL-360') !== false, 'the inspection call is listed and links out');
t_ok(strpos($html, '/job?id=21') !== false && strpos($html, 'JOB-360') !== false, 'the job is listed and links out');
t_ok(strpos($html, '/document?id=31') !== false && strpos($html, 'IRN-360') !== false, 'the report is listed and links out');
t_ok(strpos($html, 'Outstanding') !== false && strpos($html, '100,000') !== false, 'the commercial rollup (invoiced / outstanding) is shown');
