<?php
// ============================================================================
//  THE VERB SPLIT TAKES NOTHING AWAY AND HANDS NOTHING OUT             (R-20)
// ============================================================================
//  The permission model went from two verbs (view, and one tick labelled
//  "add / edit") to six (View / Add / Edit / Archive / Delete / Approve).
//  That is a change to who can do what in a live business, so the promise made
//  to the owner in docs/phase7/PERMISSION-MODEL-AUDIT.md §3.4 is asserted here
//  rather than reviewed by eye:
//
//     On day one, every person can do precisely what they could do the day
//     before. The new power exists but is unused until the owner ticks it.
//
//  Both halves matter. Losing access breaks the business; gaining it silently
//  is a security incident. Each is checked separately below.
// ============================================================================

$ROLES = ['MASTER_ADMIN','ADMIN','BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER',
          'BRANCH_APP_MANAGER','OPERATION_MANAGER','ASST_MANAGER','COORDINATOR',
          'BUSINESS_DEV_MANAGER','KEY_ACCOUNTS_MANAGER','MARKETING_MANAGER',
          'MARKETING_EXECUTIVE','FINANCE','INSPECTOR','SR_INSPECTOR'];
$MODULES = array_keys(ACCESS_MODULES);

t_section('Nothing was taken away: an edit tick still means add AND edit');

// The old tick was labelled "add / edit" and genuinely allowed both. Whoever
// held it must hold both verbs now, or every role quietly lost the ability to
// create records — the single most likely way this change breaks the business.
$lostAdd = [];
foreach ($ROLES as $r) {
    $perms = module_defaults($r);
    foreach ($MODULES as $m) {
        if (!in_array("mod.$m.edit", $perms, true)) continue;
        if (!in_array(perm_verb_key($m, 'add'), $perms, true)) $lostAdd[] = "$r/$m";
    }
}
t_eq($lostAdd, [], '*** no role lost the ability to create records'
     . ($lostAdd ? ' — LOST: ' . implode(', ', array_slice($lostAdd, 0, 8)) : ''));

t_section('Nothing was taken away: the four edit-also-destroys cases carry over');

// The audit's most dangerous finding. On these modules today's edit tick IS the
// delete-or-archive right, so whoever holds edit must receive the stronger verb
// or they lose something they can do right now.
$lostDestructive = [];
foreach (perm_legacy_edit_also_granted() as $m => $verbs) {
    foreach ($ROLES as $r) {
        $perms = module_defaults($r);
        if (!in_array("mod.$m.edit", $perms, true)) continue;
        foreach ($verbs as $v)
            if (!in_array(perm_verb_key($m, $v), $perms, true)) $lostDestructive[] = "$r/$m/$v";
    }
}
t_eq($lostDestructive, [],
     '*** the four modules where the edit tick already destroyed still do'
     . ($lostDestructive ? ' — LOST: ' . implode(', ', $lostDestructive) : ''));

t_section('Nothing was taken away: administrators keep every verb');

// Before R-20 deleting was guarded by is_admin_level(), not by a tick. If the
// new Delete right were withheld from administrators they would LOSE the
// ability to delete — the migration failing in the least obvious direction.
$adminGaps = [];
foreach (['MASTER_ADMIN','ADMIN'] as $r) {
    $perms = module_defaults($r);
    foreach ($MODULES as $m)
        foreach (access_module_verbs($m) as $v)
            if (!in_array(perm_verb_key($m, $v), $perms, true)) $adminGaps[] = "$r/$m/$v";
}
t_eq($adminGaps, [], '*** administrators hold every verb on every module'
     . ($adminGaps ? ' — MISSING: ' . implode(', ', array_slice($adminGaps, 0, 8)) : ''));

t_section('Nothing was handed out: every destructive verb traces to a power held today');

// The other half of the promise, and the strict version of it. A destructive
// verb may appear on a role ONLY if one of the three documented carry-over
// mechanisms justifies it. Anything else is a silent grant of new power.
//
//   1. the module's edit tick already carried the act   perm_legacy_edit_also_granted()
//   2. a job title already carried it                   perm_legacy_role_grants()
//   3. a named right the role holds already carried it  perm_legacy_right_grants()
//
// Each mechanism is traced to the guard it mirrors in lib/access.php, so a new
// grant that no guard justifies fails here rather than being discovered later.
$unjustified = [];
$legacyEdit  = perm_legacy_edit_also_granted();
$roleGrants  = perm_legacy_role_grants();
$rightGrants = perm_legacy_right_grants();
foreach ($ROLES as $r) {
    if (in_array($r, ['MASTER_ADMIN','ADMIN'], true)) continue;   // hold everything, checked above
    $perms   = role_defaults($r)['perms'];
    $isMgmt  = in_array($r, MGMT_ROLES, true);
    $isCoord = $isMgmt || in_array($r, ['ASST_MANAGER','COORDINATOR'], true);
    foreach ($MODULES as $m) {
        foreach (['archive','delete'] as $v) {
            if (!in_array(perm_verb_key($m, $v), $perms, true)) continue;
            // (1) the edit tick already carried it
            if (in_array("mod.$m.edit", $perms, true)
                && in_array($v, $legacyEdit[$m] ?? [], true)) continue;
            // (2) the role's job title already carried it
            $who = $roleGrants[$m][$v] ?? null;
            if ($who === 'MGMT'  && $isMgmt)  continue;
            if ($who === 'COORD' && $isCoord) continue;
            // (3) a named right the role holds already carried it
            $byRight = false;
            foreach ($rightGrants as $right => $byMod) {
                if (!in_array($right, $perms, true)) continue;
                if (in_array($v, $byMod[$m] ?? [], true)) { $byRight = true; break; }
            }
            if ($byRight) continue;
            $unjustified[] = "$r/$m/$v";
        }
    }
}
t_eq($unjustified, [],
     '*** every destructive verb traces to a power the role already held'
     . ($unjustified ? ' — UNJUSTIFIED: ' . implode(', ', array_slice($unjustified, 0, 8)) : ''));

t_section('Nothing was handed out: the module grid never grants a fine-grained right');

//  THIS TEST EXISTS BECAUSE THE FIRST VERSION OF THE VERB MAP FAILED IT.
//  Add on orders was bound to crm.contract.register, so every role holding the
//  edit tick on orders was auto-granted the right to register a contract — a
//  right the permission matrix deliberately withholds from a sales manager, who
//  owns the deal only until the quote is won. The suite caught it; this test
//  makes the whole class impossible rather than that one instance.
//
//  The rule: module_defaults() hands out MODULE access. If it resolves to one of
//  the 39 fine-grained business rights, that right must ALREADY belong to the
//  role by its own definition in role_defaults_base(), where somebody has
//  thought about who should have it. Granting it as a side effect of a module
//  tick is the bug. (Administrators hold every fine right by definition, so they
//  pass this on merit, not by exemption.)
$leaked = [];
foreach ($ROLES as $r) {
    $own = role_defaults_base($r)['perms'] ?? [];
    foreach (module_defaults($r) as $p) {
        if (strncmp($p, 'mod.', 4) === 0) continue;         // module access — fine
        if (!isset(PERMISSIONS[$p])) continue;              // not a known fine right
        if (in_array($p, $own, true)) continue;             // the role already had it
        $leaked[] = "$r -> $p";
    }
}
t_eq($leaked, [], '*** module defaults never grant a fine-grained business right'
     . ($leaked ? ' — LEAKED: ' . implode(', ', array_slice($leaked, 0, 6)) : ''));

t_section('Nothing was handed out: Approve was not widened');

// Approve is the one verb that must never merge into Edit — for an inspection
// body under ISO 17020 the person who prepares a report and the person who
// signs it off have to be separable. The verb split must not have granted a
// sign-off right to anybody who did not already hold it.
$approveKeys = [];
foreach ($MODULES as $m)
    if (in_array('approve', access_module_verbs($m), true)) $approveKeys[$m] = perm_verb_key($m, 'approve');

// Every Approve column is bound to a right that ALREADY existed. If any resolved
// to a generic mod.*.approve, the split would have invented a second right for
// an act that already had one — and one of the two always gets forgotten.
$invented = array_filter($approveKeys, fn($k) => strncmp($k, 'mod.', 4) === 0);
t_eq($invented, [], '*** every Approve column reuses the existing sign-off right'
     . ($invented ? ' — INVENTED: ' . implode(', ', $invented) : ''));
t_ok(count($approveKeys) >= 7, '*** the seven genuine sign-off rights all surface as Approve'
     . ' (found ' . count($approveKeys) . ')');

t_section('The grid is bound to existing rights, never to duplicates');

// Every bound right must be a real permission that already exists, otherwise the
// grid would show a tick that grants nothing at all.
$unknown = [];
foreach (perm_verb_bindings() as $m => $byVerb)
    foreach ($byVerb as $v => $key)
        if (!isset(PERMISSIONS[$key])) $unknown[] = "$m/$v -> $key";
t_eq($unknown, [], '*** every bound verb points at a permission that exists'
     . ($unknown ? ' — UNKNOWN: ' . implode(', ', $unknown) : ''));

// And no module may bind two verbs to the same right, which would make one tick
// silently switch on two columns and confuse anybody reading the grid.
$dupes = [];
foreach (perm_verb_bindings() as $m => $byVerb) {
    $seen = [];
    foreach ($byVerb as $v => $key) {
        if (isset($seen[$key])) $dupes[] = "$m: $seen[$key] and $v both use $key";
        $seen[$key] = $v;
    }
}
t_eq($dupes, [], '*** no module binds two verbs to one right'
     . ($dupes ? ' — ' . implode('; ', $dupes) : ''));

t_section('Archive is one right, not two');

// The owner chose this deliberately: whoever may take a record out of use may
// put it back. If a separate "activate" ever appears, somebody will be able to
// break something and then need an administrator to undo their own slip.
t_ok(!in_array('activate', PERM_VERBS, true),
     '*** there is no separate activate verb — Archive covers both directions');
t_ok(!in_array('deactivate', PERM_VERBS, true),
     '*** there is no separate deactivate verb');
t_eq(PERM_VERBS, ['view','add','edit','archive','delete','approve'],
     '*** the vocabulary is exactly the six verbs the owner approved');

t_section('Holding a strong verb implies the weaker ones');

// "May delete but may not see" is not a state any screen should have to render.
t_eq(PERM_VERB_IMPLIES['delete'],  ['view','edit'], '*** Delete implies Edit and View');
t_eq(PERM_VERB_IMPLIES['archive'], ['view','edit'], '*** Archive implies Edit and View');
t_eq(PERM_VERB_IMPLIES['add'],     ['view'],        '*** Add implies View');
t_eq(PERM_VERB_IMPLIES['approve'], ['view'],        '*** Approve implies View');
t_eq(PERM_VERB_IMPLIES['view'],    [],              '*** View implies nothing — it is the floor');
