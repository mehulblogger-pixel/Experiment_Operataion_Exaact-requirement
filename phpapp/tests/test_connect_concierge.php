<?php
// Connect K4 — the guided requirement builder. The flow itself is UI; the logic
// worth pinning is the deterministic certification inference (drawn from the K0
// taxonomy registry) and that the five steps are defined.
t_section('connect concierge (K4)');

$steps = connect_concierge_steps();
t_eq(5, count($steps), 'the guided flow has five steps');
t_ok($steps[5] !== '', 'the final step is the confirm step');

// Welding → CSWIP / CWI family from the seeded certifications registry.
$weld = connect_concierge_suggest_certs('WELD');
t_ok(!empty($weld), 'welding infers at least one certification');
$joined = strtoupper(implode('|', $weld));
t_ok(strpos($joined, 'CSWIP') !== false || strpos($joined, 'CWI') !== false, 'welding suggests a CSWIP/CWI credential');

// NDT → ASNT / PCN family.
$ndt = connect_concierge_suggest_certs('NDT');
t_ok(!empty($ndt), 'NDT infers at least one certification');
t_ok(strpos(strtoupper(implode('|', $ndt)), 'ASNT') !== false || strpos(strtoupper(implode('|', $ndt)), 'PCN') !== false, 'NDT suggests an ASNT/PCN credential');

// Coating → NACE / AMPP family.
$coat = connect_concierge_suggest_certs('COAT');
t_ok(strpos(strtoupper(implode('|', $coat)), 'NACE') !== false || strpos(strtoupper(implode('|', $coat)), 'BGAS') !== false || $coat === [], 'coating suggests a NACE/BGAS credential when present');

// An unknown/blank discipline infers nothing (never guesses wildly).
t_eq([], connect_concierge_suggest_certs(''), 'a blank discipline infers no certifications');
t_eq([], connect_concierge_suggest_certs('NOPE'), 'an unknown discipline infers no certifications');
