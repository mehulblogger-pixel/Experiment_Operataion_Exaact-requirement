<?php
// ============================================================================
//  Form Designer — per-company field overrides for the built-in recruitment
//  forms. Proves the overlay is safe and additive: a default form has no
//  overrides (renders as coded), and saved overrides read back correctly and
//  produce an apply-script; hidden fields never leave the DOM (so their value
//  is never blanked on save).
// ============================================================================

t_section('Form Designer — per-company field overrides');

t_ok(function_exists('fd_forms') && function_exists('fd_save') && function_exists('fd_overlay_html'),
    'the form-designer engine is loaded');

// The recruitment forms are designable (People & hiring is on in the test DB).
$forms = fd_forms();
t_ok(isset($forms['requisition']) && isset($forms['candidate']),
    'the Requirement and Candidate forms are offered for design');
t_ok(isset($forms['requisition']['fields']['designation']),
    'the requisition field registry includes real fields (designation)');

// Default: no overrides -> the overlay is a no-op (form renders exactly as coded).
fd_save('requisition', []);
t_eq(fd_overlay_html('requisition'), '', 'with no overrides the form overlay is empty (renders as before)');
t_eq(fd_label('requisition', 'designation', 'Designation / position'), 'Designation / position',
    'with no override a field keeps its coded label');

// Save some overrides: rename, reorder, make optional, hide a non-locked field.
fd_save('requisition', [
    ['field_key' => 'designation', 'label' => 'Position applied for', 'sort_order' => 0, 'hidden' => 0, 'req' => 'yes'],
    ['field_key' => 'project_site', 'label' => '', 'sort_order' => 1, 'hidden' => 1, 'req' => ''],
    ['field_key' => 'notes',        'label' => 'Internal notes',     'sort_order' => 2, 'hidden' => 0, 'req' => 'no'],
]);
$ov = fd_overrides('requisition');
t_eq((string) $ov['designation']['label'], 'Position applied for', 'a renamed label is stored');
t_eq(fd_label('requisition', 'designation', 'Designation / position'), 'Position applied for',
    'fd_label returns the company override');
t_ok(fd_required('requisition', 'designation', false) === true, 'a field can be made required');
t_ok(fd_hidden('requisition', 'project_site') === true, 'a non-locked field can be hidden');
t_ok(fd_required('requisition', 'notes', false) === false, 'a field can be made optional');

// The overlay script now renders and carries the overrides (display-only).
$html = fd_overlay_html('requisition');
t_ok(strpos($html, '<script>') !== false, 'an overlay script is emitted once overrides exist');
t_ok(strpos($html, 'Position applied for') !== false, 'the overlay carries the renamed label');
t_ok(strpos($html, 'display=') !== false || strpos($html, "style.display") !== false,
    'the overlay hides a field by DOM display (keeps it submitting, never blanks it)');

// Reorder helper orders known keys by saved sort_order, unknowns after.
$order = fd_order('requisition', ['notes', 'designation', 'project_site', 'client_id']);
t_eq($order[0], 'designation', 'reorder places the lowest saved order first');
t_ok(array_search('client_id', $order, true) > array_search('project_site', $order, true),
    'a field with no saved order sorts after ordered ones');

// A locked field (quantity drives costing) is renameable but flagged locked.
t_ok(!empty($forms['requisition']['fields']['quantity']['locked']),
    'a logic-critical field (quantity) is marked locked so it cannot be hidden');

// Clean up so later tests see a default form again.
fd_save('requisition', []);
t_eq(fd_overlay_html('requisition'), '', 'clearing overrides restores the default form');
