<?php
// Owner, 31 Oct 2026 (test book MOD-ADM step 3): "Unable to find Masters →
// Business Unit when logged into the company … having operations as its paid
// module." A company workspace never got the editable Business Unit list; forms
// fell back to built-in values. It is now created for a company with Operations
// or Sales, also when the module is bought later.
t_section('Business Unit — an editable list in every company with Operations or Sales');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
$prevT = $GLOBALS['__tenant'] ?? null; $prevOff = (string) setting_get('modules_off', ''); $prevCeil = (string) setting_get('saas_entitled_modules', '');
try {
    $drop = function () use ($pdo) { $t = lk_type('sbu'); if ($t) { $pdo->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([$t['id']]); $pdo->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$t['id']]); } };
    // The company workspace "acme". Since DEF-24 (21 Nov 2026) nothing silently resets it to the main
    // site mid-run any more, so each step also gives the company the plan it has bought
    // (saas_entitled_modules) — a company with no plan record is entitled to nothing (Milestone 3).
    $in = function () { $GLOBALS['__tenant'] = ['key' => 'acme', 'company' => 'Acme', 'error' => '', 'saas' => true, 'base' => 'x.test']; };
    $off = function ($keep) { $o = []; foreach (PRODUCT_MODULES as $k => $m) if (empty($m[3]) && !in_array($k, $keep, true)) $o[] = $k;
        setting_set('modules_off', implode(',', $o)); setting_set('saas_entitled_modules', implode(',', $keep)); licence_disabled(true); };

    $off(['operations']); $drop(); $in(); t_eq(current_tenant(), 'acme', '(working as a company workspace)'); lk_register_module_lists();
    $t = lk_type('sbu');
    t_ok($t && count(lk_root_values($t['id'])) > 0, 'a company with Operations has the Business Unit list, with values to edit');

    $off(['sales']); $drop(); $in(); lk_register_module_lists();
    t_ok((bool) lk_type('sbu'), 'a Sales-only company has it too (approval levels per business unit)');

    $off(['hr']); $drop(); $in(); lk_register_module_lists();
    t_ok(!lk_type('sbu'), 'a Recruitment-only company does not');

    $drop(); $off(['hr']); $in();
    //  Buying Operations through the real plan switch (billing / self-serve): it raises the
    //  company's plan and switches the module on.
    saas_plan_switch_to(['hr', 'operations'], (int) setting_get('saas_seat_limit', 0));
    t_ok((bool) lk_type('sbu'), 'buying Operations later brings the list at once');
} finally {
    if ($prevT === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $prevT;
    setting_set('modules_off', $prevOff); setting_set('saas_entitled_modules', $prevCeil); licence_disabled(true);
    if ($own) $pdo->rollBack();
}
