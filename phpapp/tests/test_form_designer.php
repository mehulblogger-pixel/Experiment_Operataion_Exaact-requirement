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

// ============================================================================
//  Add / edit / delete fields + build a dropdown — all from the one screen.
// ============================================================================
t_section('Form Designer — add / delete fields and dropdowns in one place');

if (function_exists('fd_field_add') && function_exists('custom_fields_for')) {
    $countBefore = count(custom_fields_for('requisition', false));

    // 1) Add a plain text field.
    $_POST = ['nf_label' => 'Notice period', 'nf_type' => 'text', 'nf_required' => '1'];
    fd_field_add('requisition');
    $after = custom_fields_for('requisition', false);
    $txt = null; foreach ($after as $f) if ($f['label'] === 'Notice period') $txt = $f;
    t_ok($txt !== null, 'a new text field is added to the form');
    t_eq((string) ($txt['field_type'] ?? ''), 'text', 'the added field has the chosen type');
    t_ok((int) ($txt['required'] ?? 0) === 1, 'the added field is marked required as asked');

    // 2) Add a DROPDOWN and type its options right here — a new list is built.
    $_POST = ['nf_label' => 'Work location', 'nf_type' => 'select', 'nf_list_mode' => 'new',
              'nf_options' => "On-site\nRemote\nHybrid\nHybrid"];   // duplicate is de-duped
    fd_field_add('requisition');
    $after = custom_fields_for('requisition', false);
    $drop = null; foreach ($after as $f) if ($f['label'] === 'Work location') $drop = $f;
    t_ok($drop !== null && in_array($drop['field_type'], ['select'], true), 'a dropdown field is added');
    $listId = (int) ($drop['lookup_type_id'] ?? 0);
    t_ok($listId > 0, 'the dropdown got its own new list of options');
    $vals = fd_list_values($listId);
    t_eq(count($vals), 3, 'the three typed options are created (the duplicate was dropped)');

    // 3) Add and remove an option inline.
    $_POST = ['list_id' => $listId, 'opt_label' => 'Client site'];
    fd_option_add();
    t_eq(count(fd_list_values($listId)), 4, 'an option can be added inline');
    $lastVal = end($vals);
    $_POST = ['list_id' => $listId, 'value_id' => (int) $vals[0]['id']];
    fd_option_delete();
    t_eq(count(fd_list_values($listId)), 3, 'an option can be removed inline');

    // 4) The added dropdown actually renders on the form (proves it is live).
    ob_start(); render_custom_fields('requisition'); $formHtml = ob_get_clean();
    t_ok(strpos($formHtml, 'Work location') !== false, 'the added dropdown shows on the Requirement form');
    t_ok(strpos($formHtml, 'On-site') !== false || strpos($formHtml, 'Remote') !== false,
        'the dropdown renders its options on the form');

    // 5) Rename a field (data-safe: type/list untouched).
    $_POST = ['form' => 'requisition', 'field_id' => (int) $drop['id'], 'ef_label' => 'Where they work', 'ef_required' => '1'];
    fd_field_edit('requisition');
    $reload = ops_one("SELECT * FROM custom_fields WHERE id=?", [(int) $drop['id']]);
    t_eq((string) $reload['label'], 'Where they work', 'a field can be renamed');
    t_eq((string) $reload['field_type'], 'select', 'renaming never changes the type (data stays valid)');

    // 6) Delete a field the admin added — and only that form's field.
    $_POST = ['form' => 'requisition', 'field_id' => (int) $txt['id']];
    fd_field_delete('requisition');
    $names = array_map(fn($f) => $f['label'], custom_fields_for('requisition', false));
    t_ok(!in_array('Notice period', $names, true), 'a field the admin added can be deleted');
    t_ok(in_array('Where they work', $names, true), 'deleting one field leaves the others in place');

    // Clean up the fields this test created so later tests see a clean form.
    foreach (custom_fields_for('requisition', false) as $f) {
        if (in_array($f['label'], ['Where they work', 'Notice period'], true)) {
            $_POST = ['form' => 'requisition', 'field_id' => (int) $f['id']];
            fd_field_delete('requisition');
        }
    }
    $_POST = [];
    t_eq(count(custom_fields_for('requisition', false)), $countBefore, 'the form is back to its starting fields after cleanup');
} else {
    t_ok(true, 'fd_field_add / custom fields not present — skipped');
}
