<?php
// CRM tweaks — inquiry required fields, delete guards, and the lead→inquiry
// origin link. The validation and delete guards live inside route handlers, so
// these lock the behaviour by checking the handler logic and helpers.
t_section('inquiry required fields (CRM-C)');

$crm = (string)file_get_contents(__DIR__ . '/../lib/crm.php');
t_ok(strpos($crm, "a contact e-mail or mobile") !== false,
    'an inquiry requires at least an e-mail or a mobile');
t_ok(strpos($crm, "if (!\$cid && trim(\$b['client_name'] ?? '') === '')") !== false,
    'an inquiry requires a client (picked or typed)');
t_ok(strpos($crm, "the contact person") !== false && strpos($crm, "a subject") !== false,
    'contact person and subject are required');
t_ok(strpos($crm, "(business unit)") !== false, 'the business unit is required');
t_ok(strpos($crm, "'inq' => (\$inq ? array_merge(\$inq, \$b) : \$b)") !== false,
    'on a validation error the typed values are preserved (new vs edit kept straight)');

$form = (string)file_get_contents(__DIR__ . '/../views/ops/crm/inquiry_form.php');
t_ok(strpos($form, '!empty($error)') !== false, 'the inquiry form shows the validation error');
t_ok(strpos($form, '$isEdit = !empty($inq[\'id\'])') !== false,
    'the form detects edit vs new by id, so a re-rendered new inquiry still posts to inquiry-new');
