<?php
/**
 * EXAACT audit — which rights does each ROLE PRESET actually carry?
 *
 * The route probe answers "what can this user open". This answers the question
 * behind it: "what does the role itself grant", straight from the application's
 * own role_perms(), so a finding is never inferred from a user's stored
 * permission list (which in the probe database is empty — every right comes
 * from the role preset).
 *
 * Read-only. Boots the app against a throwaway database via the test harness.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/role-rights-probe.php
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';

$roles = ['MASTER_ADMIN','ADMIN','BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER',
          'BRANCH_ADMIN_MANAGER','OPERATION_MANAGER','COORDINATOR','ASSISTANT_MANAGER',
          'FINANCE','INSPECTOR','BUSINESS_DEV_MANAGER','KEY_ACCOUNT_MANAGER',
          'MARKETING_MANAGER','MARKETING_EXEC'];

$groups = [
    'Money — who can reach and change the books' => [
        'mod.invoicing.view' => 'open the module',
        'finance.reconcile'  => 'accounts right',
        'data.credit'        => 'see credit figures',
    ],
    'Quality — the two halves of one loop' => [
        'mod.ncr.view'  => 'see a nonconformity',
        'mod.capa.view' => 'see the corrective action',
        'ncr.close'     => 'close a nonconformity',
        'capa.close'    => 'close a corrective action',
    ],
];

foreach ($groups as $title => $perms) {
    echo "\n$title\n" . str_repeat('=', strlen($title)) . "\n";
    printf("%-24s", 'role');
    foreach ($perms as $k => $label) printf("%-22s", $k);
    echo "\n" . str_repeat('-', 24 + 22 * count($perms)) . "\n";
    foreach ($roles as $r) {
        $p = role_perms($r);
        if (!is_array($p)) continue;
        printf("%-24s", $r);
        foreach ($perms as $k => $label) printf("%-22s", in_array($k, $p, true) ? 'yes' : 'NO');
        echo "\n";
    }
}

echo "\nDerived gates, for the money table above:\n";
echo "  books_can()       = licence_module_live('invoicing') && (finance.reconcile || data.credit || master)   books.php:89\n";
echo "  books_can_issue() = licence_module_live('invoicing') && (finance.reconcile || master)                  books.php:90\n";
echo "  the write gate    = ops_require(\$canIssue || can('data.credit'))                                       booksui.php:225\n";
echo "  the Money tile    = can('mod.invoicing.view') && (finance.reconcile || data.credit || master)          areas.php:219\n";
