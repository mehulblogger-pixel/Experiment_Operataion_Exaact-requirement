<?php
// R10 — three fields imply lifecycles the code never runs: legacy `calls.status` never
// reaches CLOSED in app flow, `jobs.stage` only ever becomes CANCELLED (never the
// intermediate stages), and `report_docs` ARCHIVED is never set. Rather than silently
// leave the schema misleading, each is annotated in-code so a reader (or a future dev
// wiring reporting on them) is warned. This test locks those notes in place.
t_section('vestigial statuses/fields are annotated in code (R10)');

$ops    = file_get_contents(__DIR__ . '/../lib/ops.php');
$idems  = file_get_contents(__DIR__ . '/../lib/idems.php');
$tosrm  = file_get_contents(__DIR__ . '/../lib/tosrm.php');

// jobs.stage — noted as vestigial at the constant AND at the dead dashboard read.
t_ok(strpos($ops, 'R10 — vestigial field): jobs.stage is NOT the real job lifecycle') !== false,
    'JOB_STAGES carries a vestigial-field note');
t_ok(strpos($tosrm, 'jobs.stage is vestigial') !== false,
    'the report_pending desk metric is flagged as reading a value never written');

// report_docs ARCHIVED — noted at IDEMS_STATUS.
t_ok(strpos($idems, "R10 — vestigial value): 'ARCHIVED' is defined for completeness but is never") !== false,
    'IDEMS_STATUS notes that ARCHIVED is never written');

// legacy calls.status CLOSED — noted where the two status columns are described.
t_ok(strpos($tosrm, "R10 — vestigial value): in normal app flow legacy `calls.status`") !== false,
    'the legacy calls.status note explains CLOSED is seed-only');

// The constants themselves are intact (the note documents them; it does not remove them).
t_ok(defined('JOB_STAGES') && array_key_exists('CANCELLED', JOB_STAGES), 'JOB_STAGES is unchanged');
t_ok(defined('IDEMS_STATUS') && array_key_exists('ARCHIVED', IDEMS_STATUS), 'IDEMS_STATUS is unchanged');
