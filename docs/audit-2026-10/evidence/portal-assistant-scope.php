<?php
/**
 * EXAACT audit — does the PORTAL ASSISTANT respect the signed-in portal user's
 * permissions, or only the company boundary?
 *
 * The client portal lets an administrator split who sees what INSIDE the client
 * company: the ACCOUNTS preset is "invoices only", the QUALITY preset "accepts
 * reports, sees no money". Those presets are the product's promise to a client
 * whose commercial contact must not read technical results, or whose accounts
 * clerk must not read nonconformities.
 *
 * cvp_ai_scope_context() assembles what the assistant is allowed to read. This
 * script signs in a user who has been DENIED reports and nonconformities, and
 * prints what the assistant would be handed about them anyway.
 *
 * Read-only against a throwaway database. No AI key is needed or used: the
 * leak, if there is one, is in the CONTEXT ASSEMBLY, which is callable on its
 * own. Whether a given tenant has the assistant switched on is reported too.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/portal-assistant-scope.php
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';

portal_migrate();
$now = date('c');

db()->prepare("INSERT INTO business_partners (legal_name,is_client,status,created_at)
               VALUES ('Probe Client Ltd',1,'ACTIVE',?)")->execute([$now]);
$pid = (int)db()->lastInsertId();

// Two finalised reports and one nonconformity belonging to that client — the
// kind of technical result a commercial contact is deliberately kept away from.
db()->prepare("INSERT INTO report_docs (client_id,irn,title,type_code,result,release_status,
               inspection_date,finalized,finalized_at,rev,created_at)
               VALUES (?,?,?,?,?,?,?,1,?,1,?)")
    ->execute([$pid, 'IRN/PROBE/0001', 'Pressure vessel weld inspection', 'IR',
               'FAIL', 'NOT_RELEASED', '2026-09-14', $now, $now]);
db()->prepare("INSERT INTO report_docs (client_id,irn,title,type_code,result,release_status,
               inspection_date,finalized,finalized_at,rev,created_at)
               VALUES (?,?,?,?,?,?,?,1,?,1,?)")
    ->execute([$pid, 'IRN/PROBE/0002', 'Valve body dimensional check', 'IR',
               'PASS', 'RELEASED', '2026-09-20', $now, $now]);

// The ACCOUNTS preset: invoices and complaints. No reports. No nonconformities.
db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,perms,created_at)
               VALUES (?,'accounts@client.test','Accounts Clerk','x',1,?,?)")
    ->execute([$pid, implode(',', PORTAL_PRESETS['ACCOUNTS']['perms']), $now]);
$_SESSION['cuid'] = (int)db()->lastInsertId();

echo "PORTAL ASSISTANT — IS IT BOUND BY THE USER'S PERMISSIONS?\n";
echo str_repeat('=', 78) . "\n";
printf("Signed in as       : %s\n", 'accounts@client.test');
printf("Preset             : ACCOUNTS - %s\n", PORTAL_PRESETS['ACCOUNTS']['label']);
printf("Rights carried     : %s\n", implode(', ', portal_perms()));
echo "\nWhat the application refuses this user at the front door:\n";
foreach (['reports' => '/portal/reports', 'issues' => '/portal/issues',
          'calls' => '/portal/calls', 'deputation' => '/portal/deputations'] as $k => $route) {
    printf("   pcan('%-10s) = %-5s  -> %s %s\n", $k . "'", pcan($k) ? 'true' : 'false',
           $route, pcan($k) ? '(allowed)' : 'REFUSED by portal_need()');
}

if (pcan('reports') || pcan('issues')) {
    echo "\nABORTED — the probe user holds reports or issues, so nothing below\n";
    echo "would demonstrate anything. No conclusion is drawn.\n";
    exit(1);
}

echo "\nWhat cvp_ai_scope_context() hands the assistant for that same user:\n";
echo str_repeat('-', 78) . "\n";
$ctx = cvp_ai_scope_context('CLIENT', $pid);
echo $ctx . "\n";
echo str_repeat('-', 78) . "\n";

$leaked = [];
foreach (['IRN/PROBE/0001', 'IRN/PROBE/0002', 'Pressure vessel weld inspection',
          'FAIL', 'NOT_RELEASED'] as $needle)
    if (strpos($ctx, $needle) !== false) $leaked[] = $needle;

printf("\nAssistant switched on for this tenant? %s\n",
       (function_exists('cvp_ai_available') && cvp_ai_available())
       ? 'YES' : 'no (no AI key here — the context assembly above is unaffected)');

if ($leaked) {
    echo "\nRESULT: CONFIRMED BY EXECUTION, precondition asserted.\n";
    echo "This user is REFUSED /portal/reports and /portal/issues, and the\n";
    echo "assistant is nonetheless handed: " . implode(', ', $leaked) . "\n";
    echo "cvp_ai_scope_context() (lib/cvp.php) scopes by client_id -- the COMPANY --\n";
    echo "and never consults pcan(), so every per-user permission the portal team\n";
    echo "editor offers is bypassable by asking the assistant instead. /portal/assistant\n";
    echo "and /vendor/assistant carry no portal_need() of their own either.\n";
    echo "\nTenant-safe: the SQL is bound to this one client, so no other company's\n";
    echo "data is reachable. The control it defeats is the one INSIDE the client.\n";
} else {
    echo "\nRESULT: nothing withheld appeared in the context. The finding does NOT hold.\n";
}
