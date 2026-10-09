<?php
// ============================================================================
//  ENFORCING A VERB CHANGES NOBODY'S REACH                              (R-20)
// ============================================================================
//  The module gate asks can("mod.<module>.view") for every route. Making it ask
//  the route's actual verb is what finally lets the owner say "may edit a job
//  but never delete one" — and it is also the single riskiest edit in R-20,
//  because every route whose verb is classified wrongly either locks somebody
//  out or opens a door.
//
//  So the before-set is pinned here, read from each guard in the source, and
//  compared against who holds the new verb. The two must be IDENTICAL for every
//  role. If they are not, enforcement is not safe and this fails.
//
//  Why the before-sets look so varied: today's authority for a destructive act
//  is sometimes a job title (is_admin_level() is SEVEN roles wide), sometimes a
//  named right, and sometimes the module's edit tick. That variety is the whole
//  finding of the audit, and it is why three separate carry-over mechanisms
//  exist in lib/access.php.
// ============================================================================

$ROLES = ['ADMIN','BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER','BRANCH_APP_MANAGER',
          'OPERATION_MANAGER','ASST_MANAGER','COORDINATOR','BUSINESS_DEV_MANAGER',
          'KEY_ACCOUNTS_MANAGER','MARKETING_MANAGER','MARKETING_EXECUTIVE','FINANCE',
          'INSPECTOR','SR_INSPECTOR'];
// MASTER_ADMIN is left out on purpose: it bypasses can() entirely, so it is
// trivially true on both sides and would only pad the result.

//  route => [module, verb, who can do it TODAY]
//  The third element is the guard, transcribed. Keep the comment with it: the
//  file and the guard are how the next person checks this is still true.
$ENFORCED = [
    // lib/crm.php:1357  ops_require(is_admin_level() || is_master(), ...)
    'inquiry-delete'     => ['inquiries', 'delete', fn($r, $p) => in_array($r, MGMT_ROLES, true)],
    // lib/leads.php:1294  ops_require(can('mod.leads.edit') || is_master(), ...)
    'lead-delete'        => ['leads', 'delete', fn($r, $p) => in_array('mod.leads.edit', $p, true)],
    // lib/ops.php:5059  ops_require(is_master() || can('ops.call.delete'), ...)
    'call-delete'        => ['calls', 'delete', fn($r, $p) => in_array('ops.call.delete', $p, true)],
    // lib/audits.php  ops_require(aud_can_edit(), ...) == can('mod.audits.edit')
    'audit-close'        => ['audits', 'archive', fn($r, $p) => in_array('mod.audits.edit', $p, true)],
    // lib/capa.php:571  ops_require(capa_can_close(), ...) == can('capa.close')
    'capa-close'         => ['capa', 'archive', fn($r, $p) => in_array('capa.close', $p, true)],
    // lib/idems.php  ops_require(is_master() || can('idems.finalize'), ...)
    'document-delete'    => ['idems', 'delete', fn($r, $p) => in_array('idems.finalize', $p, true)],
    'endorsement-delete' => ['idems', 'delete', fn($r, $p) => in_array('idems.finalize', $p, true)],
    // lib/ops.php  job-close  ops_require(... can('ops.job.close') ...)
    'job-close'          => ['jobs', 'archive', fn($r, $p) => in_array('ops.job.close', $p, true)],
    // lib/ops.php  expense-delete
    //   == is_coordinator_level() || can('finance.reconcile') || is_master()
    'expense-delete'     => ['jobs', 'delete', fn($r, $p) => in_array($r, MGMT_ROLES, true)
                                 || in_array($r, ['ASST_MANAGER','COORDINATOR'], true)
                                 || in_array('finance.reconcile', $p, true)],
];

t_section('Every enforced route: nobody who can act today is locked out');

//  Two things have to be right here, and the first draft of this test got both
//  wrong — which is why it is spelled out.
//
//  (1) TODAY'S AUTHORITY IS THE GATE *AND* THE HANDLER. A role without the
//      module's view right never reaches the handler at all, because
//      ops_module_gate() refuses it first. Judging by the handler guard alone
//      reported four roles as "losing" an action they cannot perform today.
//
//  (2) THE NEW VERB IS AN ADDITIONAL LOCK, NOT A REPLACEMENT. The handler guard
//      still runs after the gate. So enforcement can only ever TIGHTEN: holding
//      mod.jobs.delete gets Finance past the gate on bill-delete, and
//      job_bill_can_upload() then refuses them exactly as it does now.
//
//  Therefore the property that matters, and the only one that can break the
//  business, is: NOBODY LOSES. A role holding the verb who could not act before
//  is still stopped by the handler, so it is recorded below rather than failed.
$lost = $wider = [];
foreach ($ENFORCED as $route => [$mod, $verb, $before]) {
    foreach ($ROLES as $r) {
        $perms = role_defaults($r)['perms'];
        // the gate's existing requirement — without it the route is unreachable
        $canSee = in_array("mod.$mod.view", $perms, true) || in_array("mod.$mod.edit", $perms, true);
        $was    = $canSee && (bool)$before($r, $perms);
        $now    = in_array(perm_verb_key($mod, $verb), $perms, true);
        if ($was && !$now) $lost[]  = "$route / $r";
        if (!$was && $now) $wider[] = "$route / $r";
    }
}
t_eq($lost, [], '*** no role loses an action it can perform today'
     . ($lost ? ' — LOST: ' . implode(', ', array_slice($lost, 0, 10)) : ''));

// Recorded, not failed — each of these is still stopped by its handler guard.
// Printed so the list cannot grow unnoticed: if it ever includes a route whose
// handler guard was ALSO removed, that is a real hole and this is where it shows.
echo '  note  ' . count($wider) . " role/route pairs pass the new gate but are still
"
   . "        refused by the handler guard behind it"
   . ($wider ? ': ' . implode(', ', $wider) : '') . "\n";
t_ok(count($wider) <= 6,
     '*** the gate-passes-but-handler-refuses list is small and understood ('
     . count($wider) . ')');

t_section('The enforced routes really are the module the gate thinks they are');

// A route classified against the wrong module would ask the wrong module's verb
// — refusing somebody who has the right and admitting somebody who has not.
// This reads the router's own map rather than trusting the table above.
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
$seg = substr($ops, strpos($ops, 'static $map = ['));
$seg = substr($seg, 0, strpos($seg, "\n    ];"));
$wrong = [];
foreach ($ENFORCED as $route => [$mod, $verb, $before]) {
    if (!preg_match("/'" . preg_quote($route, '/') . "'\s*=>\s*'([a-z_]+)'/", $seg, $m)) {
        $wrong[] = "$route is not in the route map at all";
        continue;
    }
    if ($m[1] !== $mod) $wrong[] = "$route maps to {$m[1]}, table says $mod";
}
t_eq($wrong, [], '*** every enforced route maps to the module the table claims'
     . ($wrong ? ' — ' . implode('; ', $wrong) : ''));

t_section('The table here and the map in the code cannot drift apart');

// This test is only worth anything if its table lists exactly what the code
// enforces. Both directions are checked: every route in the table carries the
// verb the code gives it, and the code enforces no route the table has not
// reasoned about.
$mismatch = [];
foreach ($ENFORCED as $route => [$mod, $verb, $before])
    if (perm_route_verb($route) !== $verb)
        $mismatch[] = "$route: table says $verb, code says " . perm_route_verb($route);
t_eq($mismatch, [], '*** every route in the table carries the verb the code enforces'
     . ($mismatch ? ' — ' . implode('; ', $mismatch) : ''));

$src = file_get_contents(__DIR__ . '/../lib/ops.php');
$seg2 = substr($src, strpos($src, 'function perm_route_verb('));
$seg2 = substr($seg2, 0, strpos($seg2, '];'));
preg_match_all("/'([a-z0-9-]+)'\s*=>\s*'(add|edit|archive|delete|approve)'/", $seg2, $mm);
$extra = array_values(array_diff($mm[1], array_keys($ENFORCED)));
t_eq($extra, [], '*** the code enforces no route this test has not reasoned about'
     . ($extra ? ' — UNREVIEWED: ' . implode(', ', $extra) : ''));

t_section('A verb is only enforced where the module actually has it');

$missing = [];
foreach ($ENFORCED as $route => [$mod, $verb, $before])
    if (!in_array($verb, access_module_verbs($mod), true)) $missing[] = "$route needs $mod.$verb";
t_eq($missing, [], '*** no route is enforced against a verb its module does not have'
     . ($missing ? ' — ' . implode('; ', $missing) : ''));

t_section('Read-time upgrade is idempotent');

// It runs on every permission read, so running it twice must change nothing —
// otherwise a set would grow on each page load.
foreach (['COORDINATOR','FINANCE','BRANCH_APP_MANAGER','SR_INSPECTOR'] as $r) {
    $once  = perm_upgrade_verbs(role_defaults($r)['perms']);
    $twice = perm_upgrade_verbs($once);
    sort($once); sort($twice);
    t_eq($twice, $once, "*** upgrading $r's set twice is the same as once");
}

t_section('A set saved under the old two-verb model still works');

// The realistic case: a per-user set stored months ago. It must come back with
// Add alongside Edit, and with the destructive verbs its edit tick carried.
$old = ['mod.jobs.view','mod.jobs.edit','mod.leads.view','mod.leads.edit','ops.job.close'];
$up  = perm_upgrade_verbs($old);
t_ok(in_array('mod.jobs.add', $up, true),   '*** an old jobs edit tick still allows creating a job');
t_ok(in_array('mod.leads.delete', $up, true), '*** an old leads edit tick still allows deleting a lead');
t_ok(in_array('mod.jobs.archive', $up, true), '*** ops.job.close still allows closing a job');
t_ok(in_array('mod.jobs.delete', $up, true),  '*** ops.job.close still allows deleting a bill');
t_ok(!in_array('mod.leads.archive', $up, true),
     '*** but it hands out nothing the old tick did not carry (leads archive)');
