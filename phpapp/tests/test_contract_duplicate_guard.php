<?php
// A won deal gets ONE contract. Registering it must not be possible a SECOND time
// under a new number — not from the quotation screen, not from the partner screen.
// The legitimate ways to hold more than one contract stay open: a genuinely
// separate agreement (warned, not blocked), and the group-company path. A prior
// contract that was REJECTED or CLOSED may be registered afresh (a real restart).
t_section('a won deal cannot be re-forwarded to Finance under a second contract number');

$crm = file_get_contents(__DIR__ . '/../lib/crm.php');
$idx = file_get_contents(__DIR__ . '/../index.php');
$det = file_get_contents(__DIR__ . '/../views/detail.php');

// Door A — the quotation "register contract" path refuses a different number when
// the quote already carries a LIVE (OPEN/PENDING) contract, and the refusal sits
// before the INSERT / the quotations.contract_id overwrite.
t_ok(strpos($crm, "in_array((string)(\$prev['open_status'] ?? 'OPEN'), ['OPEN','PENDING'], true)") !== false,
    'Door A refuses only when the existing contract is OPEN or PENDING (REJECTED/CLOSED may restart)');
$guardA   = strpos($crm, "\$prev = ops_one(\"SELECT id, contract_number, open_status FROM partner_contracts WHERE id=?\"");
$insertA  = $guardA !== false ? strpos($crm, 'INSERT INTO partner_contracts', $guardA) : false;  // this door's own INSERT, after the guard
$overwrite = strpos($crm, 'UPDATE quotations SET client_id=?, contract_number=?, contract_id=?');
t_ok($guardA !== false && $insertA !== false && $guardA < $insertA, 'Door A guard runs before the contract INSERT');
t_ok($guardA !== false && $overwrite !== false && $guardA < $overwrite, 'Door A guard runs before the quotation contract_id is overwritten');

// Door C — the partner-screen door refuses when the named quotation already has a
// live contract, pointing to the group path; runs before the generic INSERT.
t_ok(strpos($idx, "\$lq = ops_one(\"SELECT contract_id FROM quotations WHERE id=?\", [\$linkQid])") !== false,
    'Door C checks whether the named quotation is already contracted');
t_ok(strpos($idx, "in_array((string)(\$prevC['open_status'] ?? 'OPEN'), ['OPEN','PENDING'], true)") !== false,
    'Door C refuses only for a LIVE prior contract');
$guardC  = strpos($idx, '$linkQid = (int)($b[\'quotation_id\'] ?? 0);');
$insertC = strpos($idx, 'INSERT INTO $table');
t_ok($guardC !== false && $insertC !== false && $guardC < $insertC, 'Door C guard runs before the contract INSERT');

// The partner Add-contract form WARNS (does not block) when live contracts exist.
t_ok(strpos($det, '$liveContracts = array_values(array_filter($contracts') !== false
    && strpos($det, 'Add another <b>only</b> if it is a genuinely separate agreement') !== false,
    'the Add-contract form names the client\'s live contracts as a caution, and still allows the add');

// Door D — the deliberate group-company path is untouched: it still creates an
// ADDITIONAL contract linked to the same quotation without a REJECTED/CLOSED
// requirement, so "one quote → many group contracts" keeps working.
t_ok(function_exists('crm_add_group_contract'), 'the group-company contract path still exists');
