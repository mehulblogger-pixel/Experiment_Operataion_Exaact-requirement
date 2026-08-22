<?php
// The report CREATION form was decluttered: drawing/QAP references (already in the
// document reference), project code/name, material grade, applicable standards and
// location (carried or captured on the report type's own form), and the result /
// release status (an outcome set only when the report is filled) are no longer
// typed on this header. They stay as HIDDEN values so editing never wipes them and
// the IRN / PDF references keep working.
t_section('the report creation form is decluttered but loses no data');

$src = file_get_contents(__DIR__ . '/../views/ops/idems/doc_form.php');

// The visible input labels for the removed fields are gone.
foreach ([
    'Project code', 'Project name', 'Drawing no.', 'Drawing rev.', 'QAP rev.',
    'Material grade', 'Applicable standards', 'Location', 'Inspection result', 'Release status',
] as $label) {
    t_ok(strpos($src, '>' . $label . '<') === false, "the visible '$label' field is removed from the form");
}

// But every one is preserved as a hidden input, so nothing is wiped on save.
foreach (['project_code','project_name','drawing_no','drawing_rev','qap_rev',
          'material_grade','standards','location','result','release_status'] as $name) {
    t_ok(preg_match('/type="hidden"\s+name="' . preg_quote($name, '/') . '"/', $src) === 1,
        "the '$name' value is kept as a hidden input (no data loss)");
}

// The fields that remain useful at creation are still visible.
foreach (['Client', 'Vendor / manufacturer', 'Purchase order', 'Product category', 'Inspector', 'Approver'] as $keep) {
    t_ok(strpos($src, '>' . $keep) !== false, "the '$keep' field is still on the form");
}
