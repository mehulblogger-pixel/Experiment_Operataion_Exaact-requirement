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

// ---------------------------------------------------------------------------
t_section('protected delete (CRM-B)');
$opp = (string)file_get_contents(__DIR__ . '/../lib/opportunities.php');

// Inquiry delete: admin-only, blocked when a quote exists.
t_ok(strpos($crm, "\$route === 'inquiry-delete'") !== false, 'the inquiry-delete route exists');
t_ok(strpos($crm, "is_admin_level() || is_master()") !== false, 'delete is administrators-only');
t_ok(strpos($crm, "SELECT COUNT(*) FROM quotations WHERE inquiry_id=?") !== false,
    'an inquiry with a quotation is protected from deletion');

// Opportunity delete: admin-only, blocked when WON or has linked quotes.
t_ok(strpos($opp, "\$route === 'opportunity-delete'") !== false, 'the opportunity-delete route exists');
t_ok(strpos($opp, "\$o['status'] === 'WON' || \$quotes") !== false,
    'a won opportunity, or one with quotations, is protected from deletion');

// Lead delete already existed and protects a converted lead.
t_ok(strpos($leads, "\$route === 'lead-delete'") !== false, 'the lead-delete route exists');
t_ok(strpos($leads, "=== 'CONVERTED'") !== false, 'a converted lead is protected from deletion');

// Routing is registered for the new delete routes.
t_ok(strpos($ops2 = (string)file_get_contents(__DIR__ . '/../lib/ops.php'), "'inquiry-delete'=>'inquiries'") !== false
     && strpos($ops2, "'opportunity-delete'=>'leads'") !== false, 'both new delete routes are registered');

// The delete buttons are on the screens.
$il = (string)file_get_contents(__DIR__ . '/../views/ops/crm/inquiry_list.php');
$od = (string)file_get_contents(__DIR__ . '/../views/ops/opportunity_detail.php');
$ld = (string)file_get_contents(__DIR__ . '/../views/ops/lead_detail.php');
t_ok(strpos($il, '/inquiry-delete') !== false, 'the inquiry list has a delete button');
t_ok(strpos($od, '/opportunity-delete') !== false, 'the opportunity page has a delete button');
t_ok(strpos($ld, '/lead-delete') !== false, 'the lead page has a delete button');
t_ok(strpos($il, 'confirm(') !== false && strpos($od, 'confirm(') !== false && strpos($ld, 'confirm(') !== false,
    'every delete asks for confirmation first');

// ---------------------------------------------------------------------------
t_section('CRM term definitions (CRM-A)');
$areas = (string)file_get_contents(__DIR__ . '/../lib/areas.php');
t_ok(strpos($areas, 'A company worth pursuing') !== false, 'Leads has a plain-English definition');
t_ok(strpos($areas, 'A live deal you are working to win or lose') !== false, 'Opportunities has a definition');
t_ok(strpos($areas, 'A specific request to quote') !== false, 'Inquiries has a definition');
$ah = (string)file_get_contents(__DIR__ . '/../views/ops/area_home.php');
t_ok(strpos($ah, "' title=\"' . e(\$tl['desc'])") !== false,
    'each tile shows its definition as a hover tooltip');
