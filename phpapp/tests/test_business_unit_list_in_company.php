<?php
// Owner, 31 Oct 2026 (test book MOD-ADM step 3): "Unable to find Masters →
// Business Unit when logged into the company … having operations as its paid
// module." A company workspace never got the editable Business Unit list; forms
// fell back to built-in values. It is now created for a company with Operations
// or Sales, also when the module is bought later.
t_section('Business Unit — an editable list in every company with Operations or Sales');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
$prevT = $GLOBALS['__tenant'] ?? null; $prevOff = (string) setting_get('modules_off', '');
try {
    $drop = function () use ($pdo) { $t = lk_type('sbu'); if ($t) { $pdo->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([$t['id']]); $pdo->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$t['id']]); } };
    // Re-entered right before each step: opening the database resets it to the main site.
    $in = function () { $GLOBALS['__tenant'] = ['key' => 'acme', 'company' => 'Acme', 'error' => '', 'saas' => true, 'base' => 'x.test']; };
    $off = function ($keep) { $o = []; foreach (PRODUCT_MODULES as $k => $m) if (empty($m[3]) && !in_array($k, $keep, true)) $o[] = $k; setting_set('modules_off', implode(',', $o)); licence_disabled(true); };

    $off(['operations']); $drop(); $in(); t_eq(current_tenant(), 'acme', '(working as a company workspace)'); lk_register_module_lists();
    $t = lk_type('sbu');
    t_ok($t && count(lk_root_values($t['id'])) > 0, 'a company with Operations has the Business Unit list, with values to edit');

    $off(['sales']); $drop(); $in(); lk_register_module_lists();
    t_ok((bool) lk_type('sbu'), 'a Sales-only company has it too (approval levels per business unit)');

    $off(['hr']); $drop(); $in(); lk_register_module_lists();
    t_ok(!lk_type('sbu'), 'a Recruitment-only company does not');

    $drop(); $off(['hr']); $in();
    saas_apply_modules_list(['hr', 'operations']);
    t_ok((bool) lk_type('sbu'), 'buying Operations later brings the list at once');
} finally {
    if ($prevT === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $prevT;
    setting_set('modules_off', $prevOff); licence_disabled(true);
    if ($own) $pdo->rollBack();
}
