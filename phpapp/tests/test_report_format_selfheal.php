<?php
// Standard report FORMATS must always be present — installed automatically for
// every install, and self-healing if a boot ever failed to create them (e.g. a
// half-uploaded deploy once marked the install "done" without it actually
// running, leaving the "design a format" screen). Users can still edit these or
// add their own; this only guarantees the standard set is always there.
t_section('Report formats — always installed & self-healing');

// After a normal boot the standard vendor formats exist (assessment + audit + the
// scored VASR / VAR report types).
$asmt  = (int) ops_val("SELECT COUNT(*) FROM report_types WHERE code LIKE 'UVA\\_%' ESCAPE '\\'");
$audit = (int) ops_val("SELECT COUNT(*) FROM report_types WHERE code LIKE 'UAUD\\_%' ESCAPE '\\'");
$vasr  = (int) ops_val("SELECT COUNT(*) FROM report_types WHERE code IN ('VASR','VAR')");
t_ok($asmt  >= 5, 'the standard Vendor Assessment formats install automatically');
t_ok($audit >= 5, 'the standard Vendor Audit formats install automatically');
t_eq($vasr, 2, 'the VASR (assessment) and VAR (audit) report types are present');

// Self-heal: simulate a database left WITHOUT a standard format — exactly what a
// failed/half-uploaded boot produced — while the old "already installed" flag is
// set. The next boot's migrate must put it back on its own.
t_ok(function_exists('uvae_migrate'), 'the vendor-assessment engine migrate is callable');
db()->exec("DELETE FROM report_types WHERE code='UVA_MFG'");
setting_set('uvae_samples_seeded_v2', '');   // as a database that predates the fix would look
t_eq((int) ops_val("SELECT COUNT(*) FROM report_types WHERE code='UVA_MFG'"), 0, 'the format is missing before the heal (the stuck state)');
uvae_migrate();
t_eq((int) ops_val("SELECT COUNT(*) FROM report_types WHERE code='UVA_MFG'"), 1, 'the missing format is re-created automatically on the next boot');

// And running migrate yet again does NOT duplicate it (the installer is idempotent).
uvae_migrate();
t_eq((int) ops_val("SELECT COUNT(*) FROM report_types WHERE code='UVA_MFG'"), 1, 'a healthy install is never duplicated by the self-heal');

// The FORMS themselves (the field layouts) must also be present — a type with no
// fields is the "(no form yet)" screen the user hit. VASR and VAR ship complete
// forms, and a form wiped by a failed boot must rebuild on the next boot.
if (function_exists('idems_migrate') && function_exists('idems_type_id_by_code')) {
    $fieldCount = function ($code) {
        $tid = idems_type_id_by_code($code);
        return $tid ? (int) ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=?", [$tid]) : 0;
    };
    t_ok($fieldCount('VASR') > 0, 'the Vendor Assessment (VASR) form has fields — not "no form yet"');
    t_ok($fieldCount('VAR')  > 0, 'the Vendor Audit (VAR) form has fields — not "no form yet"');

    // Simulate the stuck state: wipe the VASR form and clear the success flag.
    $vid = idems_type_id_by_code('VASR');
    if ($vid) {
        db()->prepare("DELETE FROM report_fields WHERE report_type_id=?")->execute([$vid]);
        db()->prepare("DELETE FROM report_sections WHERE report_type_id=?")->execute([$vid]);
    }
    setting_set('vasr_form_seeded_v2', '');
    t_eq($fieldCount('VASR'), 0, 'the VASR form is empty before the heal (the stuck state)');
    idems_migrate();
    t_ok($fieldCount('VASR') > 0, 'the VASR form is rebuilt automatically on the next boot');

    // FAR / STIR / NCR now ship ready-to-fill forms (previously "no form yet").
    foreach (['FAR' => 'Factory Assessment', 'STIR' => 'Site Inspection', 'NCR' => 'Non-Conformance'] as $code => $name) {
        t_ok($fieldCount($code) > 0, "the $name report ($code) now has a ready-to-fill form (was 'no form yet')");
    }
}
