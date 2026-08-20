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

// ---------------------------------------------------------------------------
t_section('lead → inquiry origin link (CRM-D)');
if (function_exists('crm_migrate')) crm_migrate();

// The inquiry can hold a structured lead_id and it round-trips.
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO crm_inquiries (inquiry_no, client_name, contact_name, contact_mobile, subject, sbu, lead_id, created_at)
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute(['INQ-TEST-1', 'Narmada', 'Rakesh', '9999999999', 'TPI of vessels', 'IND', 4242, 'x']);
    $iid = (int)db()->lastInsertId();
    t_eq((int)ops_val("SELECT lead_id FROM crm_inquiries WHERE id=?", [$iid]), 4242,
        'an inquiry stores the originating lead_id');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

$leads = (string)file_get_contents(__DIR__ . '/../lib/leads.php');
t_ok(strpos($leads, "UPDATE crm_inquiries SET lead_id=? WHERE id=?") !== false,
    'converting a lead stamps the new inquiry with the lead link');
t_ok(strpos($crm, "ensure_column('crm_inquiries', 'lead_id'") !== false,
    'the crm_inquiries table gains a lead_id column');
t_ok(strpos($crm, 'LEFT JOIN leads l ON l.id=i.lead_id') !== false,
    'the inquiry register shows the origin lead reference');
t_ok(strpos($form, 'Converted from lead') !== false,
    'the inquiry form shows it was converted from a lead');
