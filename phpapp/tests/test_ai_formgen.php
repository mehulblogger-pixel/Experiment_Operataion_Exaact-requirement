<?php
// ============================================================================
//  AI Form & Dropdown Builder (pilot).
//
//  The live AI call is not exercised here (no key in the test box) — instead we
//  test the two parts that must be correct regardless of the model: (1) parsing
//  and VALIDATING the model's JSON into a safe plan, and (2) APPLYING an
//  approved plan through the existing Form Designer engine. The apply test
//  creates real rows and then removes them, so it never pollutes the suite.
// ============================================================================

t_section('AI Form Builder — parse, validate, and apply');

if (!function_exists('aifg_parse') || !function_exists('aifg_apply')) { t_ok(true, 'AI form builder not present — skipped'); return; }

// --- Parsing a well-formed (fenced) reply. ---
$reply = "Sure! Here you go:\n```json\n" . json_encode([
    'dropdowns' => [
        ['name' => 'Notice Period', 'values' => ['Immediate', '15 days', '30 days', '30 days']], // dup value
        ['name' => 'Notice Period', 'values' => ['x']],                                           // dup list — dropped
    ],
    'fields' => [
        ['form' => 'candidate', 'label' => 'Current CTC', 'type' => 'number', 'required' => true],
        ['form' => 'candidate', 'label' => 'Notice Period', 'type' => 'select', 'dropdown' => 'Notice Period'],
        ['form' => 'requisition', 'label' => 'Client Budget', 'type' => 'number'],
        ['form' => 'not_a_form', 'label' => 'Bad', 'type' => 'text'],                              // invalid form — dropped
        ['form' => 'candidate', 'label' => 'Ghost', 'type' => 'select', 'dropdown' => 'Missing'],  // select w/o list — dropped
        ['form' => 'candidate', 'label' => 'Notes', 'type' => 'weird'],                            // bad type → text
    ],
]) . "\n```";

[$plan, $err] = aifg_parse($reply);
t_ok($err === null && is_array($plan), 'a fenced JSON reply parses into a plan');
t_eq(count($plan['dropdowns']), 1, 'a duplicate dropdown name is de-duplicated');
t_eq(count($plan['dropdowns'][0]['values']), 3, 'duplicate option values are removed');
t_eq(count($plan['fields']), 4, 'invalid form and a select without its list are dropped');
$byLabel = [];
foreach ($plan['fields'] as $f) $byLabel[$f['label']] = $f;
t_ok(!isset($byLabel['Bad']), 'a field on a non-existent form is rejected');
t_ok(!isset($byLabel['Ghost']), 'a dropdown field pointing at a missing list is rejected');
t_eq($byLabel['Notes']['type'], 'text', 'an unknown field type falls back to text');

// --- Bad input. ---
[$p2, $e2] = aifg_parse('the model refused and wrote prose only');
t_ok($p2 === null && $e2 !== null, 'a reply with no JSON is reported as an error, not applied');

// --- Applying only the APPROVED parts, through the real engine, then cleanup. ---
$applyPlan = [
    'dropdowns' => [
        ['name' => 'ZZ Test Notice', 'values' => ['A', 'B', 'C']],
        ['name' => 'ZZ Test Skipped', 'values' => ['no']],
    ],
    'fields' => [
        ['form' => 'candidate', 'label' => 'ZZ Test CTC', 'type' => 'number', 'required' => true],
        ['form' => 'candidate', 'label' => 'ZZ Test Notice', 'type' => 'select', 'dropdown' => 'ZZ Test Notice'],
        ['form' => 'candidate', 'label' => 'ZZ Test Unpicked', 'type' => 'text'],
    ],
];
$before = (int) ops_val("SELECT COUNT(*) FROM custom_fields WHERE entity='candidate'");
$res = aifg_apply($applyPlan, ['dropdowns' => ['ZZ Test Notice'], 'fields' => [0, 1]]); // only 2 fields + 1 list picked
t_eq($res['dropdowns'], 1, 'only the approved dropdown list is created');
t_eq($res['fields'], 2, 'only the approved fields are added');
$after = (int) ops_val("SELECT COUNT(*) FROM custom_fields WHERE entity='candidate'");
t_eq($after - $before, 2, 'exactly two fields were written to the candidate form');
$sel = ops_one("SELECT field_type, lookup_type_id FROM custom_fields WHERE entity='candidate' AND label='ZZ Test Notice'");
t_ok($sel && $sel['field_type'] === 'select' && (int) $sel['lookup_type_id'] > 0, 'the dropdown field is wired to the list that was just created');
t_ok((int) ops_val("SELECT COUNT(*) FROM custom_fields WHERE entity='candidate' AND label='ZZ Test Unpicked'") === 0, 'an un-ticked field is NOT added');

// Cleanup — remove everything this test created.
foreach (['ZZ Test CTC', 'ZZ Test Notice', 'ZZ Test Unpicked'] as $lbl)
    db()->prepare("DELETE FROM custom_fields WHERE entity='candidate' AND label=?")->execute([$lbl]);
if ($sel && (int) $sel['lookup_type_id'] > 0 && function_exists('lk_type_by_id')) {
    $lt = lk_type_by_id((int) $sel['lookup_type_id']);
    if ($lt && function_exists('lk_drop_type')) lk_drop_type($lt['type_key']);
}
t_ok((int) ops_val("SELECT COUNT(*) FROM custom_fields WHERE entity='candidate' AND label LIKE 'ZZ Test%'") === 0, 'test fields cleaned up');
