<?php
// Phase 5.1A — configurable compensation setup (headings + statutory).
t_section('configurable compensation (Phase 5.1A)');

comp_migrate();

// Defaults seeded (all editable).
$defs = comp_defs(true);
t_ok(count($defs) >= 5, 'default components are seeded');
$codes = array_column($defs, 'code');
t_ok(in_array('BASIC', $codes, true) && in_array('PF_EE', $codes, true), 'Basic and statutory PF are present');

// Compute from a Basic + Special (HRA % of basic, PF % of basic, etc.).
$r = comp_compute(['BASIC' => 400000, 'SPECIAL' => 100000]);
$byCode = [];
foreach ($r['lines'] as $l) $byCode[$l['code']] = $l['amount'];
t_eq($byCode['BASIC'], 400000.0, 'a fixed component takes the entered amount');
t_eq($byCode['HRA'], 160000.0, 'HRA computes at 40% of Basic');
t_eq($byCode['PF_EE'], 48000.0, 'employee PF computes at 12% of Basic');
t_eq($byCode['PF_ER'], 48000.0, 'employer PF computes at 12% of Basic');
// Gross = Basic + HRA + Special (conveyance 0). Employer = PF_ER + Gratuity(4.81%).
t_eq($r['gross'], 660000.0, 'gross earnings sum the earning components');
t_eq($r['net'], 660000.0 - 48000.0, 'net = gross earnings − employee deductions');
t_ok($r['employer'] > 0, 'employer contributions are computed');
t_eq($r['ctc'], $r['gross'] + $r['employer'], 'CTC = gross earnings + employer contributions');

// Admin can add a new heading and it flows into the compute.
$id = comp_save(0, ['code' => 'LTA', 'name' => 'Leave travel allowance', 'section' => 'EARNING', 'calc' => 'FIXED', 'rate' => 0]);
t_ok($id > 0, 'a new salary heading can be added');
$r2 = comp_compute(['BASIC' => 400000, 'LTA' => 20000]);
$has = false; foreach ($r2['lines'] as $l) if ($l['code'] === 'LTA' && $l['amount'] == 20000) $has = true;
t_ok($has, 'the new heading is included in the computed structure');
t_ok($r2['gross'] > $r['gross'] - 100000, 'the new earning increases gross');

// A % component with a changed rate recomputes.
$hraDef = null; foreach (comp_defs(false) as $d) if ($d['code'] === 'HRA') $hraDef = $d;
comp_save($hraDef['id'], ['code' => 'HRA', 'name' => 'HRA', 'section' => 'EARNING', 'calc' => 'PCT_BASIC', 'rate' => 50]);
$r3 = comp_compute(['BASIC' => 400000]);
$hra3 = 0; foreach ($r3['lines'] as $l) if ($l['code'] === 'HRA') $hra3 = $l['amount'];
t_eq($hra3, 200000.0, 'changing the HRA rate to 50% recomputes it');

// Disabling a component keeps it (never deletes) but drops it from the compute.
comp_set_active($id, false);
t_ok(count(comp_defs(true)) < count(comp_defs(false)), 'a disabled heading is hidden but not deleted');

// Restore the shared seed rate so later test files see the default HRA of 40%.
comp_save($hraDef['id'], ['code' => 'HRA', 'name' => 'HRA', 'section' => 'EARNING', 'calc' => 'PCT_BASIC', 'rate' => 40]);
