<?php
// Connect K0 — the marketplace industry taxonomy (additive masters, cx_*).
// Adopted wholesale from the Inspect Connect blueprint (Part B). Asserts the
// nine master tables exist, seed to the expected counts, and that reseeding is
// idempotent (insert-if-empty — never duplicates, never overwrites admin edits).
t_section('connect industry taxonomy (K0)');

// boot() already migrated + seeded on the fresh test DB. Make it explicit and
// prove a second call is a harmless no-op.
connect_taxonomy_seed();
$s = connect_taxonomy_summary();

t_eq(27, $s['sectors'],          '27 industry sectors seeded');
t_eq(11, $s['equipment_groups'], '11 equipment groups seeded');
t_ok($s['equipment_types'] > 0,  'equipment types seeded under their groups');
t_eq(18, $s['materials'],        '18 materials seeded');
t_eq(22, $s['disciplines'],      '22 disciplines seeded');
t_eq(17, $s['stages'],           '17 inspection stages seeded');
t_eq(13, $s['standards'],        '13 standard families seeded');
t_eq(24, $s['certifications'],   '24 certifications seeded');
t_ok($s['version'] !== '',       'a taxonomy version is recorded');

// Idempotency: seeding again must not duplicate.
$typesBefore = $s['equipment_types'];
connect_taxonomy_seed();
connect_taxonomy_seed();
$s2 = connect_taxonomy_summary();
t_eq(27, $s2['sectors'],               're-seed does not duplicate sectors');
t_eq(24, $s2['certifications'],         're-seed does not duplicate certifications');
t_eq($typesBefore, $s2['equipment_types'], 're-seed does not duplicate equipment types');

// Content spot-checks — the taxonomy is real, not empty scaffolding.
$weld = db()->query("SELECT methods FROM cx_disciplines WHERE code='NDT'")->fetchColumn();
t_ok(strpos((string)$weld, 'UT') !== false, 'NDT discipline carries its methods (UT among them)');
$cswip = db()->query("SELECT issuer FROM cx_certifications_registry WHERE code='CSWIP_3_0'")->fetchColumn();
t_eq('TWI', $cswip, 'CSWIP 3.0 maps to issuer TWI');
$stage1 = db()->query("SELECT name FROM cx_inspection_stages WHERE seq=1")->fetchColumn();
t_ok(stripos((string)$stage1, 'Pre-inspection') !== false, 'stage 1 is the pre-inspection meeting');
