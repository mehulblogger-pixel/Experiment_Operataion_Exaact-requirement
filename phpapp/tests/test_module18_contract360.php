<?php
// Module 18 — Orders / Contracts 360. contract_state() computes the live expiry/quantity verdict
// (EXPIRING/EXPIRED/QTY_LOW/EXHAUSTED) that the SCHEDULING GATE already enforces — but the Contract
// 360 detail screen never showed it, and never showed value-minus-invoiced. Surface both, read-only.
// The verdict logic is extracted into contract_classify() so the gate and the 360 read ONE formula.
t_section('Module 18 — contract 360 surfaces the live state + remaining-to-invoice');

t_ok(function_exists('contract_classify'),    'contract_classify() (the shared verdict) exists');
t_ok(function_exists('contract_state_row'),   'contract_state_row() (contract-keyed state) exists');
t_ok(function_exists('contract_state_label'), 'contract_state_label() exists');

// contract_state_row on a contract recorded directly on the client (no quotation) still classifies
// from the row's own end_date / quantity, through the same classifier the gate uses.
$warn = function_exists('contract_warn_days') ? contract_warn_days() : 30;

$expired = contract_state_row(['id'=>1, 'end_date'=>date('Y-m-d', strtotime('-3 days')), 'quotation_id'=>0]);
t_eq($expired['state'], 'EXPIRED', 'a past end date reads EXPIRED');
t_ok($expired['days_left'] < 0, 'an expired contract reports negative days-left');

$expiring = contract_state_row(['id'=>2, 'end_date'=>date('Y-m-d', strtotime('+'.max(1,$warn-1).' days')), 'quotation_id'=>0]);
t_eq($expiring['state'], 'EXPIRING', 'an end date inside the warning window reads EXPIRING');

$ok = contract_state_row(['id'=>3, 'end_date'=>date('Y-m-d', strtotime('+400 days')), 'quotation_id'=>0]);
t_eq($ok['state'], 'OK', 'a far-future end date reads OK (in force)');

$exhausted = contract_state_row(['id'=>4, 'end_date'=>'', 'qty_total'=>10, 'quotation_id'=>0]);
// qty_used defaults to 0 with no quote, so qty_left=10 → not exhausted; force the exhausted branch:
$exhausted2 = contract_classify(['state'=>'NONE','end_date'=>'','days_left'=>null,'qty_total'=>10.0,'qty_used'=>10.0,'qty_left'=>0.0]);
t_eq($exhausted2['state'], 'EXHAUSTED', 'zero quantity left reads EXHAUSTED');
$qtyLow = contract_classify(['state'=>'NONE','end_date'=>'','days_left'=>null,'qty_total'=>100.0,'qty_used'=>95.0,'qty_left'=>5.0]);
t_eq($qtyLow['state'], 'QTY_LOW', 'under 10% quantity remaining reads QTY_LOW');

$none = contract_state_row(['id'=>5, 'end_date'=>'', 'quotation_id'=>0]);
t_eq($none['state'], 'NONE', 'no term and no quantity → NONE (no gate applies)');

// EXPIRED / EXHAUSTED are the blocking states; the label carries a tone.
t_ok(contract_state_blocks('EXPIRED') && contract_state_blocks('EXHAUSTED'), 'EXPIRED and EXHAUSTED block scheduling');
t_ok(!contract_state_blocks('EXPIRING'), 'EXPIRING does not block (advisory)');
[$tone, $lbl, $desc] = contract_state_label('EXPIRED');
t_eq($tone, 'bad', 'the EXPIRED label is severity "bad"');
t_ok($lbl !== '' && $desc !== '', 'the label has a title and description');

// contract_classify is the ONE formula: same inputs → same verdict as the gate engine reads.
$viaClassify = contract_classify(['state'=>'NONE','end_date'=>date('Y-m-d', strtotime('-1 day')),
    'days_left'=>-1, 'qty_total'=>null, 'qty_left'=>null]);
t_eq($viaClassify['state'], 'EXPIRED', 'the shared classifier decides EXPIRED for a past date (one formula)');

// ---- the 360 view renders the banner + the remaining-to-invoice KPI ----
$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('M18 Client','M18 Client',1,'ACTIVE')")->execute();
$pid = (int)$pdo->lastInsertId();
$c = ['id'=>1, 'partner_id'=>$pid, 'contract_number'=>'CON/18/1', 'title'=>'Rate', 'open_status'=>'OPEN',
      'value'=>500000, 'start_date'=>'2026-04-01', 'end_date'=>date('Y-m-d', strtotime('-2 days')), 'branch_name'=>'Ahmedabad',
      'legal_name'=>'M18 Client', 'display_name'=>'M18 Client', 'notes'=>'', 'quotation_id'=>0,
      'requested_by'=>'', 'requested_at'=>'', 'mgr_endorsed_at'=>'', 'bm_approved_at'=>''];
$quote = null; $pos = []; $calls = []; $jobs = []; $reports = [];
$money = ['value'=>500000, 'invoiced'=>120000, 'received'=>0, 'outstanding'=>120000, 'remaining'=>380000];
$state = contract_state_row($c);
$canSeeMoney = true;

$html = (function() use ($c,$quote,$pos,$calls,$jobs,$reports,$money,$state,$canSeeMoney) {
    ob_start(); include __DIR__ . '/../views/ops/contract_detail.php'; return ob_get_clean();
})();

t_ok(strpos($html, 'Expired') !== false, 'the 360 shows the EXPIRED state banner');
t_ok(strpos($html, 'Request an override') !== false, 'a blocking state offers the override path');
t_ok(strpos($html, 'Left to invoice') !== false, 'the money row shows remaining-to-invoice');
t_ok(strpos($html, '380,000') !== false, 'remaining-to-invoice = value − invoiced is shown');

// An over-billed contract flips the label.
$money2 = ['value'=>100000, 'invoiced'=>140000, 'received'=>0, 'outstanding'=>140000, 'remaining'=>-40000];
$html2 = (function() use ($c,$quote,$pos,$calls,$jobs,$reports,$money2,$state,$canSeeMoney) {
    $money = $money2;
    ob_start(); include __DIR__ . '/../views/ops/contract_detail.php'; return ob_get_clean();
})();
t_ok(strpos($html2, 'Over-billed') !== false, 'invoiced beyond contract value reads "Over-billed"');

// ---- preservation ----
$lib = file_get_contents(__DIR__ . '/../lib/contracts.php');
t_ok(strpos($lib, "'state' => contract_state_row(\$c)") !== false, 'the 360 handler passes the live state to the view');
t_ok(strpos($lib, 'function contract_state(') !== false, 'the original contract_state() engine is preserved');
t_ok(strpos($lib, "return contract_classify(\$out);") !== false, 'contract_state() delegates to the shared classifier (no second formula)');
