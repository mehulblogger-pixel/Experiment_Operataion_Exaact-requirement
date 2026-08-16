<?php
// Data-loss guard: when opening an opportunity fails validation, the form must
// come back with everything the user typed still in it (and the reason shown),
// not redirect to an empty register. This renders the actual form view with a
// $prefill (what the handler now passes on a failed POST) and checks the values
// survive.
t_section('opportunity form keeps typed values on a validation error');

$prefill = [
    'name'          => 'Annual vessel inspection contract',
    'partner_name'  => 'EdgeCraft Industries',
    'value'         => '250000',
    'expected_close'=> '2026-12-31',
    'competitor'    => 'RivalCo Inspections',
    'contact_name'  => 'R. Shah',
    'contact_email' => 'r.shah@edgecraft.example',
    'contact_phone' => '9800000000',
    'next_action'   => 'Send the scope for review',
    'next_action_on'=> '2026-09-01',
    'requirement'   => 'Two vessels at Bharuch — hydro + NDT',
];
$form_err  = 'Say which customer, or who it is for.';
$pipelines = []; $offices = []; $clients = []; $sources = [];

ob_start();
include dirname(__DIR__) . '/views/ops/opportunity_form.php';
$html = ob_get_clean();

t_ok(strpos($html, 'Annual vessel inspection contract') !== false, 'the opportunity name is preserved');
t_ok(strpos($html, 'EdgeCraft Industries') !== false, 'the customer name is preserved');
t_ok(strpos($html, '250000') !== false, 'the estimated value is preserved');
t_ok(strpos($html, 'RivalCo Inspections') !== false, 'the competitor is preserved');
t_ok(strpos($html, 'R. Shah') !== false, 'the contact name is preserved');
t_ok(strpos($html, 'r.shah@edgecraft.example') !== false, 'the contact e-mail is preserved');
t_ok(strpos($html, 'Two vessels at Bharuch — hydro + NDT') !== false, 'the requirement text is preserved');
t_ok(strpos($html, 'Send the scope for review') !== false, 'the next action is preserved');
t_ok(strpos($html, htmlspecialchars($form_err, ENT_QUOTES)) !== false, 'the validation reason is shown on the form itself');
