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

// ============================================================================
//  WHERE AN ADDED FIELD GOES.
//
//  The screen asked for a name, a type and "required", and nothing else. Every
//  field an admin added landed in a "More details" block at the very foot of
//  the form, in creation order, with nothing on screen to say so — the owner's
//  report was simply that a field they had added was nowhere to be seen.
// ============================================================================
t_section('Form Designer — placing a field in a section');
t_as_admin();
fd_migrate();

$fdSecs = fd_sections('requisition');
t_ok(count($fdSecs) >= 4, 'FS1 ARMING — the Requirement form offers its real sections (' . count($fdSecs) . ')');
$fdSec = (string) array_key_first($fdSecs);
t_ok($fdSec !== '', 'FS2 ARMING — a section name to place a field in: "' . $fdSec . '"');

// Add a field INTO a section.
$_POST = ['form' => 'requisition', 'nf_label' => 'Placed Field UAT', 'nf_type' => 'text', 'nf_section' => $fdSec];
fd_field_add('requisition');
$fdRow = ops_one("SELECT * FROM custom_fields WHERE entity='requisition' AND label=?", ['Placed Field UAT']);
t_ok($fdRow !== null, 'FS3 — the field was added');
t_eq((string) $fdRow['section'], $fdSec, 'FS4 — and it remembers which section it belongs to');

// A section the form does not have must not be stored: the overlay would look
// for a container that does not exist and the field would vanish from the form.
$_POST = ['form' => 'requisition', 'nf_label' => 'Bogus Section UAT', 'nf_type' => 'text', 'nf_section' => 'Not A Real Section'];
fd_field_add('requisition');
$fdBogus = ops_one("SELECT * FROM custom_fields WHERE entity='requisition' AND label=?", ['Bogus Section UAT']);
t_ok($fdBogus !== null, 'FS5 — a field with an unknown section is still ADDED, not lost');
t_eq((string) $fdBogus['section'], '', 'FS6 — but the unknown section is refused, so it falls back to the end of the form');

// Moving an existing field to another section.
$fdSec2 = (string) array_keys($fdSecs)[1];
t_ok($fdSec2 !== $fdSec, 'FS7 ARMING — a genuinely different second section: "' . $fdSec2 . '"');
$_POST = ['form' => 'requisition', 'field_id' => (int) $fdRow['id'], 'ef_label' => 'Placed Field UAT', 'ef_section' => $fdSec2];
fd_field_edit('requisition');
$fdMoved = ops_one("SELECT * FROM custom_fields WHERE id=?", [(int) $fdRow['id']]);
t_eq((string) $fdMoved['section'], $fdSec2, 'FS8 — the field moved to the other section');
t_eq((string) $fdMoved['label'], 'Placed Field UAT', 'FS9 — and keeps its name');

// A post that carries no section at all (an older browser, a form with no
// sections) must LEAVE IT WHERE IT IS, not quietly send it to the end.
$_POST = ['form' => 'requisition', 'field_id' => (int) $fdRow['id'], 'ef_label' => 'Placed Field UAT'];
fd_field_edit('requisition');
t_eq((string) ops_val("SELECT section FROM custom_fields WHERE id=?", [(int) $fdRow['id']]), $fdSec2,
     'FS10 — a save that says nothing about the section does not move the field');

// The overlay must now be emitted for placement ALONE. It used to bail out when
// there were no rename/hide/order overrides, so a freshly placed field never
// moved and the admin's choice was silently dropped.
db()->exec("DELETE FROM form_field_layout WHERE form_key='requisition'");
fd_overrides('requisition', true);
$fdOv = fd_overlay_html('requisition');
t_ok(trim($fdOv) !== '', 'FS11 — with no other overrides at all, the overlay is still emitted for the placement');
t_ok(strpos($fdOv, 'cf_placed_field_uat') !== false, 'FS12 — and it names the placed field');
t_ok(strpos($fdOv, $fdSec2) !== false, 'FS13 — and the section to move it to');
t_ok(strpos($fdOv, 'cf_bogus_section_uat') === false, 'FS14 — a field with no section is not in the move list');

// The form still renders, and carries the section in its markup.
$_POST = [];
ob_start(); render_custom_fields('requisition', []); $fdHtml = ob_get_clean();
t_ok(strpos($fdHtml, 'data-cf-section="' . $fdSec2 . '"') !== false,
     'FS15 — the rendered field carries its section, for the overlay to act on');
t_ok(strpos($fdHtml, 'Bogus Section UAT') !== false,
     'FS16 — and the unplaced field still renders, rather than disappearing');

// Clean up so the next run starts where this one did.
foreach ([(int) $fdRow['id'], (int) $fdBogus['id']] as $fdDel) {
    $_POST = ['form' => 'requisition', 'field_id' => $fdDel];
    fd_field_delete('requisition');
}
$_POST = [];
t_eq((int) ops_val("SELECT COUNT(*) FROM custom_fields WHERE entity='requisition' AND label LIKE '%UAT'"), 0,
     'FS17 — the test fields are cleaned up');
t_as_nobody();

// ============================================================================
//  EVERY DECLARED FORM MUST ACTUALLY BE DESIGNABLE.
//
//  The registry and the form view are two separate files, and nothing made them
//  agree. A form declared here but missing the one fd_overlay_html() line gives
//  an admin a full design screen whose every change is silently discarded —
//  worse than not offering the form at all. A field key that no longer exists
//  in the view is the same failure one row down: it renames nothing.
// ============================================================================
t_section('Form Designer — a declared form is a wired form');
t_as_admin();

$fdViews = [
    'requisition'    => 'views/ops/requisition_form.php',
    'candidate'      => 'views/ops/candidate_form.php',
    'sample'         => 'views/ops/sample_form.php',
    'method'         => 'views/ops/method_form.php',
    'risk'           => 'views/ops/risk_form.php',
    'decision_rule'  => 'views/ops/drule_form.php',
    'controlled_doc' => 'views/ops/cdoc_form.php',
    'satisfaction'   => 'views/ops/satisfaction_form.php',
    'call'           => 'views/ops/call_form.php',
    'job'            => 'views/ops/job_form.php',
];
$fdAll = fd_forms();
t_ok(count($fdAll) >= 10, 'FW1 ARMING — the designer offers more than the original two forms (' . count($fdAll) . ')');

foreach ($fdAll as $fdKey => $fdDef) {
    $fdPath = $fdViews[$fdKey] ?? '';
    if (!t_ok($fdPath !== '', "FW2 · '$fdKey' is a form this test knows where to find")) continue;
    $fdSrc = @file_get_contents(__DIR__ . '/../' . $fdPath);
    if (!t_ok($fdSrc !== false, "FW3 · $fdPath exists")) continue;

    // The one line without which every design change is discarded.
    t_ok(strpos($fdSrc, 'fd_overlay_html') !== false,
         "FW4 · '$fdKey' — its view applies the design overrides");
    // And the form must accept added fields, or "add a field" goes nowhere.
    t_ok(strpos($fdSrc, 'render_custom_fields') !== false,
         "FW5 · '$fdKey' — its view renders fields the admin adds");

    // Every declared key must be a control that really exists on that form.
    $fdMissing = [];
    foreach (array_keys($fdDef['fields'] ?? []) as $fdF) {
        if (strpos($fdSrc, 'name="' . $fdF . '"') === false
            && strpos($fdSrc, "name=\"{$fdF}[]\"") === false
            && strpos($fdSrc, "'" . $fdF . "'") === false) $fdMissing[] = $fdF;
    }
    t_eq($fdMissing, [], "FW6 · '$fdKey' — every declared field exists on the form");

    // Sections must be offered, or the "where should it go?" picker is empty
    // and an added field can only ever land at the foot of the form.
    t_ok(count(fd_sections($fdKey)) >= 1, "FW7 · '$fdKey' — offers at least one section to place a field in");
}
t_as_nobody();

// ============================================================================
//  THE OPERATIONS FORMS SPEAK THE COMPANY'S OWN WORDS.
//
//  These two forms could not be declared the way the quality forms were. Almost
//  every label on them is built from the company's terminology — a workspace
//  that calls a client a "Customer" reads "Customer" throughout. A registry with
//  the English words hardcoded would show an admin one set of words in the Form
//  Designer and a different set on the form itself, and the label they typed
//  would land on the wrong row.
//
//  It matters twice for SECTIONS: the overlay finds a section by matching the
//  heading text on the page, so a section name that stopped tracking the
//  terminology would quietly stop matching, and a field placed there would
//  never move — with nothing on screen to say why.
// ============================================================================
t_section('Form Designer — Operations forms follow the company’s wording');
t_as_admin();

$fdTermsBefore = term_overrides();
$fdCallBefore  = fd_forms()['call']['fields']['client_id']['label'] ?? '';
t_ok($fdCallBefore !== '', 'OT1 ARMING — the Test request form declares a client field, labelled "' . $fdCallBefore . '"');

// Rename the two words these forms lean on hardest.
term_overrides(['client' => ['Customer', 'Customers'], 'office' => ['Branch', 'Branches']]);
$fdCall = fd_forms()['call'] ?? [];
$fdJob  = fd_forms()['job'] ?? [];

t_eq($fdCall['fields']['client_id']['label'] ?? '', 'Customer',
     'OT2 — renaming "client" renames it on the Test request form too');
t_ok(strpos($fdCall['fields']['billable_value']['label'] ?? '', 'customer') !== false,
     'OT3 — and mid-sentence: "' . ($fdCall['fields']['billable_value']['label'] ?? '') . '"');
t_ok(strpos($fdCall['fields']['ibo_office_id']['label'] ?? '', 'Branch') !== false,
     'OT4 — renaming "office" follows too: "' . ($fdCall['fields']['ibo_office_id']['label'] ?? '') . '"');
t_ok(strpos($fdJob['fields']['executing_office_id']['label'] ?? '', 'Branch') !== false,
     'OT5 — on the Job form as well: "' . ($fdJob['fields']['executing_office_id']['label'] ?? '') . '"');

// The sections must track it as well, or a placed field silently stops moving.
$fdSecsRenamed = fd_sections('call');
t_ok(count(array_filter(array_keys($fdSecsRenamed), fn($x) => strpos($x, 'Customer') !== false)) > 0,
     'OT6 — the section names follow the wording: ' . implode(' · ', array_slice(array_keys($fdSecsRenamed), 0, 3)));
t_ok(count(array_filter(array_keys($fdSecsRenamed), fn($x) => strpos($x, 'Branches') !== false)) > 0,
     'OT7 — including the plural form');

// Put it back, and prove the rename really was the cause rather than something
// that happened to be true either way.
term_overrides($fdTermsBefore);
t_eq(fd_forms()['call']['fields']['client_id']['label'] ?? '', $fdCallBefore,
     'OT8 — restoring the wording restores the label (so OT2 measured the rename)');

// The Test request form asks for the quotation line in TWO places. Declared
// once, the overlay must apply a hide to BOTH — hiding one copy and leaving the
// other is the half-hidden field an admin would report as "it didn't work".
$fdCallSrc = file_get_contents(__DIR__ . '/../views/ops/call_form.php');
t_ok(substr_count($fdCallSrc, 'name="quote_line_id"') >= 2,
     'OT9 ARMING — quote_line_id really does appear twice on that form');
db()->exec("DELETE FROM form_field_layout WHERE form_key='call'");
fd_save('call', [['field_key' => 'quote_line_id', 'label' => '', 'hidden' => 1, 'req' => '', 'sort_order' => 1]]);
$fdOvCall = fd_overlay_html('call');
t_ok(strpos($fdOvCall, 'fieldEls') !== false,
     'OT10 — the overlay collects every control with that name, not just the first');
t_ok(strpos($fdOvCall, '"quote_line_id"') !== false, 'OT11 — and carries the hide for it');
db()->exec("DELETE FROM form_field_layout WHERE form_key='call'");
fd_overrides('call', true);

t_as_nobody();
