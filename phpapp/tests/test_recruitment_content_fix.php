<?php
// ============================================================================
//  A recruitment workspace created on an OLDER build was seeded with the
//  inspection company's people lists (Inspector / Engineer designations). This
//  corrects that CONTENT in place — but only where the list is still the
//  untouched shipped default, so a customer's own edits are never clobbered.
// ============================================================================

t_section('Recruitment content fix — inspection defaults → recruitment, safely');

if (!function_exists('lk_replace_if_default') || !function_exists('lk_add_type')) { t_ok(true, 'content fix not present — skipped'); return; }

// A throwaway list seeded with the EXACT inspection default is replaced.
$id = lk_add_type('zz_desig_fix', 'ZZ Designation Fix', null, 0, 95);
$so = 0; foreach (DESIGNATIONS as $c => $l) lk_add_value($id, null, $c, $l, $so++);
$did = lk_replace_if_default('zz_desig_fix', DESIGNATIONS, RECRUIT_DESIGNATIONS);
t_ok($did === true, 'an untouched inspection default list is replaced');
$codes = array_map(fn($r) => (string) $r['code'], ops_all("SELECT code FROM lookup_values WHERE type_id=?", [$id]));
sort($codes); $want = array_keys(RECRUIT_DESIGNATIONS); sort($want);
t_eq($codes, $want, 'it now holds exactly the recruitment designations');
t_ok(!in_array('INSPECTOR', $codes, true) && in_array('RECRUITER', $codes, true), 'Inspector is gone; Recruiter is present');

// A CUSTOMISED list (not the shipped default) is left completely alone.
$id2 = lk_add_type('zz_desig_custom', 'ZZ Designation Custom', null, 0, 96);
lk_add_value($id2, null, 'MY_ROLE', 'My Own Role', 0);
lk_add_value($id2, null, 'INSPECTOR', 'Inspector', 1);
$did2 = lk_replace_if_default('zz_desig_custom', DESIGNATIONS, RECRUIT_DESIGNATIONS);
t_ok($did2 === false, 'a customised list is NOT replaced');
t_eq((int) ops_val("SELECT COUNT(*) FROM lookup_values WHERE type_id=?", [$id2]), 2, 'the customised list keeps its own values untouched');

// The whole-workspace helper is a no-op on the control / owner install.
if (function_exists('lk_fix_recruitment_content')) {
    $before = (int) ops_val("SELECT COUNT(*) FROM lookup_values");
    lk_fix_recruitment_content();   // current_tenant()==='' here → does nothing
    t_eq((int) ops_val("SELECT COUNT(*) FROM lookup_values"), $before, 'the content fix does nothing on the control install');
}

// Cleanup the throwaway lists.
foreach ([$id, $id2] as $tid) {
    db()->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([$tid]);
    db()->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$tid]);
}
t_ok(true, 'throwaway lists cleaned up');
