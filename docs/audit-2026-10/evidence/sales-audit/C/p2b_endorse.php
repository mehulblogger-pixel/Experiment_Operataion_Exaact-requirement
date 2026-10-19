<?php
// P2b — can the Operation Manager actually endorse a contract opening? The write
// route /contract-open is mapped to the 'quotes' module (ops.php:3313).
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true); $P = json_decode(file_get_contents(__DIR__ . '/p2_ids.json'), true);
$cid = (int)$P['cid'];
foreach (['operation_manager', 'business_director', 'branch_manager'] as $k) { as_user($I[$k]);
  echo "  $k: mod.quotes.view=" . (can('mod.quotes.view') ? 'Y' : 'N') . " can_endorse=" . (can_endorse_contract_open() ? 'Y' : 'N') . " can_approve=" . (can_approve_contract_open() ? 'Y' : 'N') . "\n"; }
$o = req($I['operation_manager'], 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'endorse']);
echo "  OM endorse -> rendered " . $o['rendered'] . " bytes, flash " . json_encode($o['flash']) . "; endorsed_by='" . val("SELECT mgr_endorsed_by FROM partner_contracts WHERE id=?", [$cid]) . "'\n";
$o = req($I['operation_manager'], 'contract-openings', 'GET');
echo "  OM opens /contract-openings -> rendered " . $o['rendered'] . " bytes, flash " . json_encode($o['flash']) . "\n";
show('BD endorse', req($I['business_director'], 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'endorse']));
show('BM approve', req($I['branch_manager'], 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'approve']));
echo "  contract: " . json_encode(row("SELECT open_status, mgr_endorsed_by, bm_approved_by, value, end_date FROM partner_contracts WHERE id=?", [$cid])) . "\n";
