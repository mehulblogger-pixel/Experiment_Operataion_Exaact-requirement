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

// ============================================================================
//  Phase 2b — driving a real candidate along its configured pipeline
// ============================================================================
t_section('recruitment pipeline — candidate flow (Phase 2b)');

if (function_exists('req_migrate')) req_migrate();   // adds grade / cmp_medical columns

// A requisition (senior grade, medical required) + a candidate on it.
db()->prepare("INSERT INTO requisitions (req_code,designation,grade,cmp_medical,status,created_at) VALUES ('SRF-T','Accountant','SENIOR',1,'OPEN',?)")->execute([date('c')]);
$reqId = (int)db()->lastInsertId();
db()->prepare("INSERT INTO candidates (cand_code,requisition_id,first_name,stage,created_at) VALUES ('CAN-T',?,'Test','RECEIVED',?)")->execute([$reqId, date('c')]);
$cid = (int)db()->lastInsertId();
$cand = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);

// Resolve position — starts at the first stage of the default pipeline.
[$pipe, $eff, $idx] = recruitpipe_cand_state($cand);
t_eq($pipe['code'], 'CORP18', 'the candidate resolves to the default corporate workflow');
t_eq($idx, 0, 'a fresh candidate starts at the first stage');
// SENIOR + medical required → all 18 stages apply.
t_eq(count($eff), 18, 'the full 18-stage path applies for a senior, medical-required requisition');

// Advance to the L1 interview stage → legacy stage coarse-syncs to INTERVIEW.
$l1 = null; foreach ($eff as $s) if ($s['stage_key'] === 'L1') $l1 = $s;
recruitpipe_cand_goto($cand, (int)$l1['id'], 'cleared screening', 'tester');
$c2 = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
t_eq((int)$c2['pipeline_stage_id'], (int)$l1['id'], 'the candidate now sits at L1 in the configured pipeline');
t_eq($c2['stage'], 'INTERVIEW', 'reaching an interview stage coarse-syncs the legacy stage to INTERVIEW');
t_ok((int)$c2['pipeline_id'] > 0, 'the pipeline is locked onto the candidate on first move');

// Advance to the offer stage → legacy stage coarse-syncs to OFFERED.
$offer = null; foreach ($eff as $s) if ($s['kind'] === 'offer') $offer = $s;
recruitpipe_cand_goto($c2, (int)$offer['id'], '', 'tester');
$c3 = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
t_eq($c3['stage'], 'OFFERED', 'reaching the offer stage coarse-syncs the legacy stage to OFFERED');

// The move is audited in candidate_events.
$evN = (int)ops_one("SELECT COUNT(*) c FROM candidate_events WHERE candidate_id=?", [$cid])['c'];
t_ok($evN >= 2, 'every configured move is written to the candidate timeline');

// Terminal legacy stage is never overwritten by a coarse sync.
db()->prepare("UPDATE candidates SET stage='ACCEPTED' WHERE id=?")->execute([$cid]);
$c4 = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
recruitpipe_cand_goto($c4, (int)$l1['id'], '', 'tester');
$c5 = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
t_eq($c5['stage'], 'ACCEPTED', 'a closed (ACCEPTED) candidate is never coarse-synced back to an earlier stage');

// A junior/no-medical candidate gets the shorter effective path (conditional skip).
db()->prepare("INSERT INTO requisitions (req_code,designation,grade,cmp_medical,status,created_at) VALUES ('SRF-J','Clerk','JUNIOR',0,'OPEN',?)")->execute([date('c')]);
$reqJ = (int)db()->lastInsertId();
db()->prepare("INSERT INTO candidates (cand_code,requisition_id,first_name,stage,created_at) VALUES ('CAN-J',?,'Junior','RECEIVED',?)")->execute([$reqJ, date('c')]);
$candJ = ops_one("SELECT * FROM candidates WHERE id=?", [(int)db()->lastInsertId()]);
[$pj, $ej, $ij] = recruitpipe_cand_state($candJ);
t_eq(count($ej), 15, 'a junior/no-medical candidate is driven through the shorter 15-stage path');
