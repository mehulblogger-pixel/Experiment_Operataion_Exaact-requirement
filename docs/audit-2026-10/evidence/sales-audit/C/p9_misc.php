<?php
// P9 — side registers and small checks.
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
echo "boss_numbers rows created from work: " . json_encode(rows("SELECT id, client_id, boss_number, start_date, end_date, status, comments FROM boss_numbers WHERE comments LIKE 'Created automatically%' ORDER BY id")) . "\n";
echo "partner_contracts with that typo number: " . (int)val("SELECT COUNT(*) FROM partner_contracts WHERE contract_number='TYPO-NOT-A-CONTRACT'") . "\n";
echo "contract value vs quote subtotal/total (contract #1 at registration took total incl. GST): see p2.out [2b]\n";
echo "requisitions commercial columns: " . json_encode(array_values(array_intersect(array_column(ops_all("PRAGMA table_info(requisitions)"), 'name'), ['client_id','contract_ref','po_ref','quotation_ref','billing_rate','rate_basis','work_model','expected_revenue','target_margin']))) . "\n";
echo "inspectors fee columns: " . json_encode(array_values(array_intersect(array_column(ops_all("PRAGMA table_info(inspectors)"), 'name'), ['placement_fee','fee_status','guarantee_upto','agency_id','agency_cost']))) . "\n";
echo "quote_lines columns: " . json_encode(array_column(ops_all("PRAGMA table_info(quote_lines)"), 'name')) . "\n";
echo "partner_contracts columns: " . json_encode(array_column(ops_all("PRAGMA table_info(partner_contracts)"), 'name')) . "\n";
echo "contract_line_items columns: " . json_encode(array_column(ops_all("PRAGMA table_info(contract_line_items)"), 'name')) . "\n";
echo "invoices has billing-address / client-GSTIN-per-site column? " . json_encode(array_values(array_filter(array_column(ops_all("PRAGMA table_info(invoices)"), 'name'), fn($c) => preg_match('/addr|gstin|site|ship|bill_to/i', $c)))) . "\n";
echo "partner_addresses types available: " . json_encode(array_keys(ADDRESS_TYPES)) . "\n";
