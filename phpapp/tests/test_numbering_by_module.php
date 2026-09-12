<?php
// ============================================================================
//  Document numbering only lists the documents a workspace actually raises.
//  A recruitment agency has no enquiries, quotations, inspection calls,
//  deputations, invoices or receipts — so only Requirements and Candidates
//  numbering remain on its System settings screen.
// ============================================================================

t_section('Numbering — filtered by the enabled modules');

if (!function_exists('numbering_types_visible')) { t_ok(true, 'numbering filter not present — skipped'); return; }

$savedOff = (string) setting_get('modules_off', '');
$savedKey = (string) setting_get('licence_key', '');
setting_set('licence_key', ''); if (function_exists('lk_state')) lk_state(true);   // pin OPEN so modules_off is authoritative

// Recruitment plan: everything except People & hiring is off.
setting_set('modules_off', 'sales,operations,money,reporting');
if (function_exists('licence_disabled')) licence_disabled(true);
$vis = array_keys(numbering_types_visible());
t_ok(in_array('REQ', $vis, true) && in_array('CV', $vis, true), 'Requirements & Candidates numbering stay for a recruitment workspace');
t_ok(!in_array('INQ', $vis, true) && !in_array('Q', $vis, true) && !in_array('CALL', $vis, true)
    && !in_array('JOB', $vis, true) && !in_array('INV', $vis, true) && !in_array('RCP', $vis, true) && !in_array('CN', $vis, true),
    'enquiries, quotations, inspection calls, jobs and money documents are hidden');
t_eq(count($vis), 2, 'exactly the two recruitment documents remain');

// With every module on (the single-business / control install), all show.
setting_set('modules_off', '');
if (function_exists('licence_disabled')) licence_disabled(true);
t_eq(count(numbering_types_visible()), count(numbering_types()), 'with every module on, every document is offered');

// Restore.
setting_set('modules_off', $savedOff);
setting_set('licence_key', $savedKey);
if (function_exists('lk_state')) lk_state(true);
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(true, 'module state restored for the rest of the suite');
