<?php
// ============================================================================
//  PHASE 1 · MILESTONE 4 — EXISTING CUSTOMER ENTITLEMENT MIGRATION
//
//  M3 made a missing entitlement record mean DENY. M4 answers what happens to
//  the customers who already exist, and its one rule is that entitlement is
//  never manufactured: a plan name, a package, an old default or a blank record
//  prove nothing about what was bought.
//
//  The assessment is a pure function over two plain arrays, so every case below
//  is exercised directly rather than through a database — including the ones a
//  live system would rarely reach and must still get right.
// ============================================================================

t_section('Milestone 4 — existing customer entitlement migration');

$ctl = fn(array $o = []) => array_merge(
    ['tenant' => 'acme', 'company' => 'Acme Ltd', 'status' => 'active', 'plan' => 'RECRUITMENT',
     'enabled_modules' => ['admin', 'hr']], $o);
$run = fn(array $o = []) => array_merge(
    ['reachable' => true, 'provisioned' => '1', 'ceiling' => '', 'modules_off' => '',
     'paid' => '', 'package' => '', 'licence_key' => ''], $o);

// ---- A · Already correct -------------------------------------------------
$a = entmig_assess($ctl(), $run(['ceiling' => 'hr']));
t_eq($a['class'], 'NO_CHANGE_REQUIRED', 'A · a workspace already matching the commercial record is left alone');
t_eq($a['after'], ['hr'], 'A · and its entitlement is unchanged');
t_eq($a['lost'], [], 'A · nothing is taken away');

// admin is core, so it is not a ceiling entry — a record of [admin,hr] and a
// ceiling of [hr] are the SAME thing and must not read as a disagreement.
t_eq(entmig_assess($ctl(['enabled_modules' => ['admin', 'hr']]), $run(['ceiling' => 'hr']))['class'],
     'NO_CHANGE_REQUIRED', 'A · core is normalised away, so equal records compare as equal');

// ---- B · Safe migration --------------------------------------------------
$b = entmig_assess($ctl(), $run(['ceiling' => '']));
t_eq($b['class'], 'SAFE_TO_MIGRATE', 'B · a blank ceiling with a commercial record is safe to migrate');
t_eq($b['after'], ['hr'], 'B · to exactly what was sold');
t_eq($b['before'], [], 'B · from nothing — which is what M3 gives a blank record');
t_eq($b['gained'], ['hr'], 'B · so the customer regains what they bought');
t_eq($b['lost'], [], 'B · and loses nothing');
t_eq($b['evidence'], 'control saas_tenants.enabled_modules', 'B · the evidence is named');
t_eq($b['confidence'], 'HIGH', 'B · with high confidence');

// ---- C/D · Blank everywhere = ambiguous, never invented -------------------
$c = entmig_assess($ctl(['enabled_modules' => []]), $run(['ceiling' => '']));
t_eq($c['class'], 'AMBIGUOUS', 'C · blank ceiling AND blank commercial record is AMBIGUOUS');
t_eq($c['after'], [], 'C · nothing is granted');
t_eq($c['evidence'], 'none', 'C · because there is no evidence');
t_ok(stripos($c['reason'], 'plan name is not proof') !== false, 'D · and the plan name is explicitly not used as proof');

// A commercial record naming ONLY core is no evidence of a purchase either.
t_eq(entmig_assess($ctl(['enabled_modules' => ['admin']]), $run())['class'], 'AMBIGUOUS',
     'D · a record naming only the core module entitles nothing');

// The two records disagreeing is a human decision, not a migration.
$d = entmig_assess($ctl(['enabled_modules' => ['admin', 'hr']]), $run(['ceiling' => 'money']));
t_eq($d['class'], 'AMBIGUOUS', 'D · two deliberate records that disagree are AMBIGUOUS');
t_eq($d['after'], ['money'], 'D · and the workspace is left exactly as it is');
t_ok(stripos($d['reason'], 'resolved by a person') !== false, 'D · to be resolved by a person');

// ---- E/F · Provisioned vs never opened -----------------------------------
t_eq(entmig_assess($ctl(), $run(['provisioned' => '1', 'ceiling' => '']))['class'], 'SAFE_TO_MIGRATE',
     'E · a provisioned workspace is assessed');
$f = entmig_assess($ctl(), $run(['provisioned' => '', 'ceiling' => '']));
t_eq($f['class'], 'NO_CHANGE_REQUIRED', 'F · a workspace never opened has nothing to migrate');
t_ok(stripos($f['reason'], 'first sign-in') !== false, 'F · its ceiling is written when its owner first signs in');

// ---- G · Several modules -------------------------------------------------
$g = entmig_assess($ctl(['enabled_modules' => ['admin', 'hr', 'operations', 'reporting']]), $run());
t_eq($g['after'], ['hr', 'operations', 'reporting'], 'G · every sold module is carried across, core excluded');

// ---- H · A module the tenant switched off is still owned ------------------
$h = entmig_assess($ctl(['enabled_modules' => ['admin', 'hr', 'operations']]),
                   $run(['ceiling' => '', 'modules_off' => 'operations']));
t_eq($h['class'], 'SAFE_TO_MIGRATE', 'H · a tenant-disabled module does not block migration');
t_ok(in_array('operations', $h['after'], true), 'H · and stays ENTITLED — switching off is not cancelling a purchase');
t_eq($h['tenant_disabled'], ['operations'], 'H · while the disabled state is recorded separately');

// ---- I · Core --------------------------------------------------------------
foreach ([$a, $b, $g, $h] as $i => $case)
    t_ok(!in_array('admin', $case['after'], true), "I · core admin is never written into a ceiling (case $i)");

// ---- J-N · Every product module ------------------------------------------
foreach (['hr' => 'J', 'operations' => 'K', 'reporting' => 'L', 'money' => 'M', 'sales' => 'N'] as $mod => $case) {
    $x = entmig_assess($ctl(['enabled_modules' => ['admin', $mod]]), $run(['ceiling' => '']));
    t_eq($x['class'], 'SAFE_TO_MIGRATE', "$case · $mod migrates from the commercial record");
    t_eq($x['after'], [$mod], "$case · to exactly [$mod]");
}

// ---- O · Inactive customer ------------------------------------------------
foreach (['suspended', 'pending', 'closed'] as $st) {
    $o = entmig_assess($ctl(['status' => $st]), $run(['ceiling' => '']));
    t_eq($o['class'], 'BLOCKED', "O · a $st customer is BLOCKED, not migrated");
    t_eq($o['after'], $o['before'], "O · and is left exactly as it is ($st)");
}

// A signed licence outranks any cloud record; migration must not reach past it.
$lic = entmig_assess($ctl(), $run(['ceiling' => '', 'licence_key' => 'SIGNED-KEY']));
t_eq($lic['class'], 'BLOCKED', 'O · a signed licence blocks migration');
t_ok(stripos($lic['reason'], 'authoritative') !== false, 'O · because the licence is authoritative');

// ---- Unreadable workspace -------------------------------------------------
$err = entmig_assess($ctl(), $run(['reachable' => false]));
t_eq($err['class'], 'ERROR', 'a workspace that cannot be read is ERROR, never assumed');
t_eq($err['after'], [], 'and nothing is proposed for it');

// ---- P · No manufactured entitlement — the rule, stated as a test --------
// Nothing may appear in 'after' that was not in the commercial record.
foreach ([[[], ''], [['admin'], ''], [[], 'hr'], [['admin', 'hr'], 'money']] as [$sold, $ceil]) {
    $x = entmig_assess($ctl(['enabled_modules' => $sold]), $run(['ceiling' => $ceil]));
    if ($x['class'] !== 'SAFE_TO_MIGRATE') continue;
    $invented = array_diff($x['after'], entmig_sellable($sold));
    t_ok(!$invented, 'P · nothing is granted that the commercial record does not name');
}
$p = entmig_assess($ctl(['plan' => 'FULL_SUITE', 'enabled_modules' => []]), $run(['package' => 'FULL_SUITE']));
t_eq($p['after'], [], 'P · a rich plan name with no commercial record grants NOTHING');

// ---- W · Unknown stays denied --------------------------------------------
t_ok(!entmig_is_applicable($c), 'W · an AMBIGUOUS record is not applied');
t_ok(!entmig_is_applicable($d), 'W · nor a disagreeing one');
t_ok(!entmig_is_applicable($err), 'W · nor an unreadable one');
t_ok(!entmig_is_applicable($lic), 'W · nor a licence-governed one');
t_ok(entmig_is_applicable($b), 'W · only a SAFE_TO_MIGRATE record is applied');

// ---- Q/R/S/T/U · Apply, twice, against real settings ---------------------
$origCeil = setting_get('saas_entitled_modules', '');
$origPrev = setting_get(ENTMIG_PREV_KEY, '');
$origAt   = setting_get(ENTMIG_AT_KEY, '');
setting_set('saas_entitled_modules', ''); setting_set(ENTMIG_PREV_KEY, ''); setting_set(ENTMIG_AT_KEY, '');

$first = entmig_apply_here($b);
t_ok($first['changed'], 'Q · the first run applies the change');
t_eq(setting_get('saas_entitled_modules', ''), 'hr', 'S · and the workspace now carries exactly what was sold');
t_eq(setting_get(ENTMIG_PREV_KEY, ''), '', 'U · the previous value is preserved for recovery (it was blank)');
t_ok(setting_get(ENTMIG_AT_KEY, '') !== '', 'T · with a timestamp, so the change is explainable afterwards');
t_eq(setting_get(ENTMIG_SRC_KEY, ''), 'control saas_tenants.enabled_modules', 'T · and the evidence it came from');

$second = entmig_apply_here($b);
t_ok(!$second['changed'], 'Q · a second run with an unchanged source writes NOTHING — idempotent');
t_eq(setting_get('saas_entitled_modules', ''), 'hr', 'R · and the data is identical after the repeat');

// A later, different migration must not overwrite the ORIGINAL recovery value.
setting_set('saas_entitled_modules', 'hr');
$g2 = entmig_assess($ctl(['enabled_modules' => ['admin', 'hr', 'money']]), $run(['ceiling' => '']));
$stamp = setting_get(ENTMIG_AT_KEY, '');
entmig_apply_here(['after' => ['hr', 'money'], 'tenant' => 'acme', 'evidence' => 'control saas_tenants.enabled_modules']);
t_eq(setting_get('saas_entitled_modules', ''), 'hr,money', 'a later change still applies');
t_eq(setting_get(ENTMIG_PREV_KEY, ''), '', 'U · but the FIRST recovery value is never overwritten by a later run');

setting_set('saas_entitled_modules', (string) $origCeil);
setting_set(ENTMIG_PREV_KEY, (string) $origPrev);
setting_set(ENTMIG_AT_KEY, (string) $origAt);
setting_set(ENTMIG_SRC_KEY, '');
if (function_exists('licence_disabled')) licence_disabled(true);

// ---- V · Tenant isolation -------------------------------------------------
// The assessment is a pure function of ONE workspace's two records. Assessing a
// second workspace cannot alter the first, and no assessment reads or writes
// anything belonging to another tenant.
$one = entmig_assess($ctl(['tenant' => 'alpha', 'enabled_modules' => ['admin', 'hr']]), $run());
$two = entmig_assess($ctl(['tenant' => 'beta',  'enabled_modules' => ['admin', 'money']]), $run());
t_eq($one['tenant'], 'alpha', 'V · each assessment names its own workspace');
t_eq($one['after'], ['hr'], 'V · alpha gets alpha\'s modules');
t_eq($two['after'], ['money'], 'V · beta gets beta\'s — no bleed between them');
$three = entmig_assess($ctl(['tenant' => 'alpha', 'enabled_modules' => ['admin', 'hr']]), $run());
t_eq($three, $one, 'V · and assessing beta left alpha\'s result byte-for-byte identical');

// ---- The boot-chain backfill: repaired, and now covered -------------------
//
// saas_entitlement_ensure() runs from the boot chain and had NO test at all.
// It used to set a blank ceiling to "whatever is switched on right now" — which,
// with a blank ceiling, was everything. It wrote a manufactured purchase into
// the customer's record on an ordinary page load, permanently. Milestone 3
// closed the hole it fed on; Milestone 4 makes it evidence-based.
t_section('Milestone 4 — the boot-chain backfill grants only on evidence');

if (!function_exists('saas_entitlement_ensure')) { t_ok(true, 'backfill not present — skipped'); return; }

$saveT = $GLOBALS['__tenant'] ?? null;
$saveC = setting_get('saas_entitled_modules', '');
$saveP = setting_get('saas_paid_modules', '');
$saveV = setting_get('saas_provisioned', '');
$tenant = function () { $GLOBALS['__tenant'] = ['key' => 'ensureco', 'company' => 'Ensure Co']; };

// Provisioned, blank ceiling, NO evidence of payment → grant nothing, write nothing.
setting_set('saas_provisioned', '1'); setting_set('saas_entitled_modules', ''); setting_set('saas_paid_modules', '');
$tenant(); saas_entitlement_ensure();
t_eq(setting_get('saas_entitled_modules', ''), '',
     'with no evidence it grants nothing — and does not rewrite a blank value on every page load');

// The same workspace WITH evidence of payment → exactly that, nothing more.
setting_set('saas_paid_modules', 'hr');
$tenant(); saas_entitlement_ensure();
t_eq(setting_get('saas_entitled_modules', ''), 'hr', 'with evidence of payment it grants exactly what was paid for');

// Idempotent: a second run changes nothing.
$tenant(); saas_entitlement_ensure();
t_eq(setting_get('saas_entitled_modules', ''), 'hr', 'and a second run leaves it alone');

// A core module in the paid list is never written into the ceiling.
setting_set('saas_entitled_modules', ''); setting_set('saas_paid_modules', 'admin');
$tenant(); saas_entitlement_ensure();
t_eq(setting_get('saas_entitled_modules', ''), '', 'a paid list naming only core entitles nothing sellable');

// Never opened → untouched.
setting_set('saas_provisioned', ''); setting_set('saas_entitled_modules', ''); setting_set('saas_paid_modules', 'hr');
$tenant(); saas_entitlement_ensure();
t_eq(setting_get('saas_entitled_modules', ''), '', 'an unprovisioned workspace is never written to');

// The control install is never touched by it.
$GLOBALS['__tenant'] = ['key' => '', 'company' => ''];
setting_set('saas_provisioned', '1'); setting_set('saas_entitled_modules', ''); setting_set('saas_paid_modules', 'hr');
saas_entitlement_ensure();
t_eq(setting_get('saas_entitled_modules', ''), '', 'the control install is never given a ceiling');

setting_set('saas_entitled_modules', (string) $saveC);
setting_set('saas_paid_modules', (string) $saveP);
setting_set('saas_provisioned', (string) $saveV);
if ($saveT === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $saveT;
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(true, 'backfill fixtures restored');
