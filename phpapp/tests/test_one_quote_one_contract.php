<?php
// The rule: one quotation -> one branch -> one contract number, and that contract
// carries many inspection calls (man-days and/or man-month). A call INHERITS the
// contract number; it never generates one. These guards keep it that way.
t_section('one quote = one contract; calls inherit the number, never generate it');

$callForm = file_get_contents(__DIR__ . '/../views/ops/call_form.php');
$quoteDet = file_get_contents(__DIR__ . '/../views/ops/crm/quote_detail.php');
$crm      = file_get_contents(__DIR__ . '/../lib/crm.php');
$ops      = file_get_contents(__DIR__ . '/../lib/ops.php');

// A contract number is generated ONLY at contract registration, never per call.
t_ok(substr_count($crm, 'gen_contract_number(') >= 1, 'the contract number is generated at contract registration');
t_ok(strpos($callForm, 'gen_contract_number') === false, 'the call form never generates a contract number');
t_ok(strpos($ops, 'gen_contract_number') === false, 'raising / editing a call never generates a contract number');

// A call inherits the contract number and locks it (no new/different number on a call).
t_ok(strpos($callForm, '$cnInherited') !== false, 'the call form marks an inherited contract number');
t_ok(preg_match('/\$cnInherited\s*\?\s*[\'"]readonly/', $callForm) === 1, 'an inherited contract number is read-only on the call');

// The register-contract form only shows when the quote has NO contract yet — so a
// quote cannot be given a second contract.
t_ok(strpos($quoteDet, "if (\$q['contract_number']):") !== false, 'a registered contract is shown (not the register form again)');
t_ok(strpos($quoteDet, 'elseif ($canContract):') !== false, 'the register-contract form is the alternative to an existing contract, not shown alongside it');

// One contract, many calls — the contract panel invites raising as many calls as needed.
t_ok(stripos($quoteDet, 'as many') !== false && strpos($quoteDet, '/call-new?contract_id=') !== false,
    'an open contract offers to raise as many calls as the work needs');
