<?php
// Field-finding #16 — (1) the inspector must be able to download a DRAFT copy to review before
// sending for approval, and (2) document how vetting is turned on/off.
//   (1) already works: the report PDF endpoint prints a not-yet-issued report watermarked DRAFT
//       (_DRAFT filename), and the detail's primary download button is always available. The gap
//       was labelling — the button now plainly says "Download draft" before the report is issued.
//   (2) vetting is one admin switch: setting `vetting_gate_required` (Settings → Vetting checklist,
//       /vetting-checklist), off by default; Release Notes (RN/IRN) are exempt even when it is on.
t_section('Field #16 — draft download before approval, and the vetting on/off switch');

// --- (1) The draft download is labelled as a draft before issue, final after ---
$view = file_get_contents(__DIR__ . '/../views/ops/idems/doc_detail.php');
t_ok(strpos($view, "\$isDraftDl = empty(\$doc['finalized'])") !== false,
     'the primary download button distinguishes a draft (not yet issued) from the final report');
t_ok(strpos($view, "Download <?= \$isDraftDl ? 'draft' : 'report' ?>") !== false
     || strpos($view, "Download <?= \$isDraftDl ? 'draft PDF' : 'PDF' ?>") !== false,
     'the button reads "Download draft" before issue');
// The PDF handler stamps a _DRAFT suffix on a not-yet-finalized report.
$src = file_get_contents(__DIR__ . '/../lib/idems.php');
t_ok(strpos($src, "empty(\$doc['finalized']) ? '_DRAFT'") !== false,
     'a not-yet-issued report downloads as a DRAFT copy');

// --- (2) The vetting switch: off by default, on when the setting is '1' ---
t_ok(function_exists('idems_vetting_gate_on') && function_exists('idems_vetting_required'),
     'the vetting gate helpers exist');

setting_set('vetting_gate_required', '');          // default / cleared
t_ok(idems_vetting_gate_on() === false, 'vetting is OFF by default (no setting)');
$mkDoc = fn($tc) => ['type_code' => $tc];
t_ok(idems_vetting_required($mkDoc('MRIR')) === false, 'with the gate off, no report needs vetting');

setting_set('vetting_gate_required', '1');         // switch ON
t_ok(idems_vetting_gate_on() === true, 'setting vetting_gate_required=1 turns the gate ON');
t_ok(idems_vetting_required($mkDoc('MRIR')) === true, 'with the gate on, an ordinary report must be vetted');
// Release notes are exempt even when the gate is on.
t_ok(idems_vetting_required($mkDoc('RN')) === false, 'a Release Note (RN) is exempt from vetting');
t_ok(idems_vetting_required($mkDoc('IRN')) === false, 'an IRN is exempt from vetting');

setting_set('vetting_gate_required', '');          // restore default (be a good citizen — shared DB)
t_ok(idems_vetting_gate_on() === false, 'the gate is restored to OFF after the test');

// The switch lives on a real, permission-guarded settings screen.
t_ok(strpos($src, "setting_set('vetting_gate_required'") !== false
     && strpos($src, "redirect('/vetting-checklist')") !== false,
     'the switch is saved from the /vetting-checklist settings screen');

// And it is documented (the finding asked us to document the switch).
$doc07 = file_get_contents(__DIR__ . '/../../docs/edge-cases/07-vetting-review-approval.md');
t_ok(strpos($doc07, 'vetting_gate_required') !== false && strpos($doc07, '/vetting-checklist') !== false,
     'the vetting switch is documented in docs/edge-cases/07');
