<?php
// Field-finding #13 — the QA report opens with details autofilled, but the final button read
// "Create report & generate IRN" — record-keeping jargon before the action. It should lead with
// what the inspector came to do: "Generate report". The IRN is still minted automatically on save
// (said in the subtitle and the button tooltip); it just no longer crowds the button label.
t_section('Field #13 — the report-create button reads "Generate report"');

$form = file_get_contents(__DIR__ . '/../views/ops/idems/doc_form.php');

t_ok(strpos($form, "'Generate report'") !== false, 'the create button now reads "Generate report"');
t_ok(strpos($form, 'Create report &amp; generate IRN') === false, 'the old jargon label is gone');
t_ok(strpos($form, "\$doc ? 'Save report' : 'Generate report'") !== false,
     'editing an existing report still reads "Save report"; creating reads "Generate report"');
// The IRN-is-automatic fact is not lost — kept in the subtitle and the button tooltip.
t_ok(strpos($form, 'IRN (Inspection Reference Number) is generated automatically when you save') !== false,
     'the subtitle still tells the user the IRN is generated automatically');
t_ok(strpos($form, 'mints its IRN automatically') !== false,
     'the button carries a tooltip explaining the IRN is minted automatically');
