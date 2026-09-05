<?php
// Phase 2 — the configurable recruitment pipeline / stage engine.
// Additive: seeds three templates, resolves the applicable pipeline for a
// requisition, and evaluates conditional stages (an L2 only for senior grades,
// a medical only when the requisition requires it).
t_section('recruitment pipeline engine (Phase 2)');

recruitpipe_migrate();

// --- Seed ---
$pipes = recruitpipe_all(false);
t_eq(count($pipes), 3, 'three workflow templates are seeded');
$def = recruitpipe_default();
t_eq($def['code'], 'CORP18', 'the Corporate Recruitment Workflow is the default');

$stages = recruitpipe_stages($def['id'], true);
t_eq(count($stages), 18, 'the corporate workflow has all 18 stages');
$seqs = array_map(fn($s) => (int)$s['seq'], $stages);
$sorted = $seqs; sort($sorted);
t_ok($seqs === $sorted, 'stages come back ordered by sequence');

// --- Conditional stages ---
$senior = array_map(fn($s) => $s['stage_key'],
    recruitpipe_effective_stages($def['id'], ['grade' => 'SENIOR', 'cmp_medical' => '1']));
t_ok(in_array('L2', $senior, true),          'L2 interview is included for a senior grade');
t_ok(in_array('MEDICAL', $senior, true),     'medical is included when the requisition requires it');
t_ok(in_array('MED_FITNESS', $senior, true), 'medical fitness is included when medical is required');

$junior = array_map(fn($s) => $s['stage_key'],
    recruitpipe_effective_stages($def['id'], ['grade' => 'JUNIOR', 'cmp_medical' => '0']));
t_ok(!in_array('L2', $junior, true),          'L2 interview is skipped for a junior grade');
t_ok(!in_array('MEDICAL', $junior, true),     'medical is skipped when not required');
t_ok(!in_array('MED_FITNESS', $junior, true), 'medical fitness is skipped when not required');
t_ok(in_array('SRF', $junior, true) && in_array('ONBOARDING', $junior, true),
    'unconditional stages are always present');
t_eq(count($junior), 15, 'the junior/no-medical path drops exactly the three conditional stages');

// --- Resolver: narrowest match wins ---
$finId = recruitpipe_pipeline_save(0, ['name' => 'Finance Hiring', 'code' => 'FIN', 'applies_department' => 'Finance']);
recruitpipe_stage_save(['pipeline_id' => $finId, 'seq' => 10, 'stage_key' => 'REQ', 'name' => 'Requisition', 'kind' => 'gate', 'mandatory' => 1]);
$pick = recruitpipe_for(['department' => 'Finance', 'grade' => 'JUNIOR']);
t_eq($pick['code'], 'FIN', 'a Finance requisition resolves to the Finance-specific workflow');
$fallback = recruitpipe_for(['department' => 'Marketing']);
t_eq($fallback['code'], 'CORP18', 'a requisition matching no specific workflow falls back to the default');

// --- Unit: condition evaluation ---
t_ok(recruitpipe_stage_applies(['condition_field' => ''], ['x' => 'y']),
    'a stage with no condition always applies');
t_ok(recruitpipe_stage_applies(['condition_field' => 'grade', 'condition_op' => 'in', 'condition_value' => 'A,B'], ['grade' => 'b']),
    'an "is one of" condition matches case-insensitively');
t_ok(!recruitpipe_stage_applies(['condition_field' => 'grade', 'condition_op' => 'eq', 'condition_value' => 'A'], ['grade' => 'B']),
    'an "equals" condition excludes a non-match');

// --- Non-destructive: the legacy hardcoded stage list is untouched ---
t_ok(defined('CAND_STAGES') && isset(CAND_STAGES['ACCEPTED']),
    'the legacy CAND_STAGES flow is preserved (additive change)');
