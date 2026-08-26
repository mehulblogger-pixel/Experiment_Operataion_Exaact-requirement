<?php
// Module 45 — AI / Intelligence governance. AI touches on an accredited report were only partly on
// the sealed audit chain: AI_REVIEW/SCOPE_FROM_QAP/ITEMS_FROM_QAP logged only THAT a call happened
// (not which external provider received data), and text-polish — an AI touch on report field text —
// logged NOTHING. Add a provenance note (which provider/model, how much) and log text-polish, so a
// §4.2/DPDP reviewer can see what left the tenant. Advisory marking + human authority unchanged.
t_section('Module 45 — AI use is fully on the audit trail (provenance)');

t_ok(function_exists('idems_ai_provenance'), 'idems_ai_provenance() exists');

// The provenance note names the provider/model and never contains report content.
$note = idems_ai_provenance(1234, 2);
t_ok(strpos($note, 'sent to') === 0, 'the note says where the data was sent');
t_ok(strpos($note, '1,234 chars') !== false, 'the note records how much was sent');
t_ok(strpos($note, '2 file(s)') !== false, 'the note records how many files were sent');
$note0 = idems_ai_provenance();
t_ok(strpos($note0, 'sent to') === 0 && strpos($note0, 'chars') === false, 'with no size it still names the destination');

// AI_POLISH is now a first-class, high-risk, labelled audit action.
t_ok(in_array('AI_POLISH', AUDIT_HIGH_RISK, true),  'AI_POLISH is treated as high-risk (like AI_REVIEW)');
t_ok(in_array('AI_POLISH', AUDIT_ACTIONS_ALL, true),'AI_POLISH is in the master action list');
t_ok(AUDIT_ACTION_LABELS['AI_POLISH'] === 'AI text polish', 'AI_POLISH has a plain-English label');
t_ok(in_array('SCOPE_FROM_QAP', AUDIT_HIGH_RISK, true) && in_array('ITEMS_FROM_QAP', AUDIT_HIGH_RISK, true),
    'the QAP auto-fill AI actions are high-risk too');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('idems_migrate')) idems_migrate();

    // Simulate what the polish path now logs (the AI provider being unavailable in a test doesn't
    // matter — we assert the audit CALL the feature makes carries provenance and lands on the chain).
    idems_log('report_doc', 4242, 'AI_POLISH', ['field' => 'Observation', 'reason' => idems_ai_provenance(320)]);
    $row = ops_one("SELECT action, field, reason FROM idems_audit WHERE entity='report_doc' AND entity_id=4242 AND action='AI_POLISH' ORDER BY id DESC LIMIT 1");
    t_ok($row !== null, 'an AI_POLISH entry is written to the sealed chain');
    t_ok(strpos((string)$row['reason'], 'sent to') !== false, 'the AI_POLISH entry carries provenance (where the text went)');
    t_ok(strpos((string)$row['reason'], 'Observation') === false, 'the entry does NOT contain the report content itself');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$idems = file_get_contents(__DIR__ . '/../lib/idems.php');
$fill  = file_get_contents(__DIR__ . '/../views/ops/idems/fill.php');
t_ok(strpos($idems, "'AI_POLISH',") !== false && strpos($idems, 'idems_ai_provenance(strlen($text))') !== false,
    'text-polish now logs AI_POLISH with provenance (the previously-unlogged AI touch)');
t_ok(strpos($idems, "'AI_REVIEW', ['irn'=>\$doc['irn'], 'reason'=>idems_ai_provenance()]") !== false,
    'the AI review log is enriched with provenance');
t_ok(strpos($idems, "function idems_polish_text(\$text, \$fieldLabel = '', \$docId = 0)") !== false,
    'idems_polish_text takes the doc id so the AI touch is logged against the report');
t_ok(strpos($fill, "doc: '<?= (int)\$doc['id'] ?>'") !== false, 'the fill form passes the report id to the polish call');
// The advisory marking and human authority are untouched.
t_ok(strpos($idems, 'You are an assistant, not the approving authority') !== false,
    'the AI-is-advisory system prompt is preserved');
