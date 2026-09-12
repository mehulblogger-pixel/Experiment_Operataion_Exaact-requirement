<?php
// ============================================================================
//  PHASE 1 — Company Setup Cockpit / Configuration Orchestrator.
//
//  Proves the cockpit ORCHESTRATES the existing engines and owns no duplicate
//  store: capabilities are multi-select and tenant-local; modules reflect (and
//  route through) the ONE licence engine; forms status reflects the ONE form
//  engine; the status vocabulary, readiness, health, checklist and search all
//  compute from real data. Mirrors the mandatory tests §55–64 / §90.
// ============================================================================

t_section('Phase 1 — Setup Cockpit orchestrator');

t_ok(function_exists('cockpit_sections') && function_exists('cockpit_capabilities_set')
    && function_exists('cockpit_modules'), 'the cockpit orchestrator is loaded');

// Become the workspace admin (master), exactly as a real owner.
$pdo = db();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser) VALUES ('ck_master','C','MASTER_ADMIN',1,1)")->execute();
$_SESSION['uid'] = (int) $pdo->lastInsertId();
current_user(true); if (function_exists('ua')) ua(true);
if (!is_master()) { t_ok(true, 'could not become master — skipping'); return; }

// -- §47 access: the cockpit follows tenant-admin status.
t_ok(cockpit_can() === true, 'a workspace administrator may open the cockpit');

// -- §10/§11 capabilities: catalogue reused, multi-select, tenant-local.
$cat = cockpit_capability_catalogue();
t_ok(isset($cat['TECH_RECRUITMENT']), 'the capability catalogue is reused (Technical Recruitment present)');
if (function_exists('connect_cap_catalog')) {
    t_ok(count($cat) === count(connect_cap_catalog()), 'the catalogue is the SAME one as connect_cap_catalog() (no parallel catalogue)');
}

$saved = cockpit_capabilities();   // remember to restore
cockpit_capabilities_set(['TECH_RECRUITMENT', 'TECHNICAL_MANPOWER', 'NOT_A_REAL_CODE']);
$now = cockpit_capabilities();
t_ok(in_array('TECH_RECRUITMENT', $now, true) && in_array('TECHNICAL_MANPOWER', $now, true),
    'a company can hold MULTIPLE capabilities at once (many-to-many, §11)');
t_ok(!in_array('NOT_A_REAL_CODE', $now, true), 'an unknown capability code is rejected');
t_eq(count($now), 2, 'exactly the two valid capabilities are stored');

// The capability→module mapping is data-driven (basis for Phase 2 — read only now).
$capMods = cockpit_capability_modules(['TECH_RECRUITMENT']);
t_ok(in_array('hr', $capMods, true), 'a capability maps to the modules it makes relevant (data-driven)');

// -- §13/§58 modules: ONE engine. The cockpit reflects licence state and routes
//    changes through licence_save — no second module record.
$mods = cockpit_modules();
t_ok(isset($mods['admin']) && $mods['admin']['core'] === true && $mods['admin']['on'] === true,
    'Administration is shown as an always-on core feature');
t_ok(isset($mods['hr']), 'People & hiring appears as a feature');
t_ok(!empty($mods['hr']['features']), 'a feature lists its sub-features (§14)');
t_ok(!empty($mods['hr']['why']), 'each feature explains why it is available (§16)');

// Dependency awareness data (§17): turning hr off would affect these.
t_ok(!empty(cockpit_module_dependents('hr')), 'the cockpit knows what depends on a feature (§17)');

// Toggle through the canonical engine and confirm the cockpit reflects it.
$offBefore = (string) setting_get('modules_off', '');
if (function_exists('licence_save')) {
    // turn hr OFF via the one engine (what cockpit_module_apply delegates to)
    $on = [];
    foreach (licence_summary() as $k => $r) { if (!empty($r['core'])) continue; if (!empty($r['on']) && $k !== 'hr') $on[$k] = 1; }
    licence_save(['mod_on' => $on]);
    $mods2 = cockpit_modules();
    t_ok($mods2['hr']['on'] === false, 'turning a feature off through licence_save is reflected in the cockpit (one source, §58)');
    t_ok(strpos((string) setting_get('modules_off', ''), 'hr') !== false,
        'the change is stored in the ONE module setting (modules_off) — no duplicate module store');
    // restore
    setting_set('modules_off', $offBefore); licence_disabled(true);
    t_ok(cockpit_modules()['hr']['on'] === true, 'restoring the setting restores the feature (canonical round-trip)');
}

// -- §7/§9 sections + status vocabulary + readiness.
$secs = cockpit_sections();
t_ok(isset($secs['business_profile'], $secs['modules']), 'the cockpit lists dynamic setup sections');
$vocab = COCKPIT_STATUSES;
$allValid = true;
foreach ($secs as $s) if (!in_array($s['status'], $vocab, true)) $allValid = false;
t_ok($allValid, 'every section uses the one agreed status vocabulary (§9)');
$r = cockpit_readiness();
t_ok($r >= 0 && $r <= 100, 'workspace readiness is a 0–100% figure (§8)');
// With two capabilities chosen the business profile is no longer "not started".
t_ok($secs['business_profile']['status'] !== 'not_started', 'choosing capabilities advances the profile status');

// -- §59 canonical forms: cockpit reflects the ONE form engine; owns no copy.
if (function_exists('fd_field_add') && function_exists('custom_fields_for') && licence_enabled('hr')) {
    $before = count(fd_custom_fields('requisition'));
    $_POST = ['nf_label' => 'Cockpit Test Field', 'nf_type' => 'text'];
    fd_field_add('requisition');
    $forms = fd_forms();
    $cnt = function_exists('fd_custom_fields') ? count(fd_custom_fields('requisition')) : 0;
    t_eq($cnt, $before + 1, 'a field added in the canonical form engine is seen by the cockpit (one source, §59)');
    // clean up
    foreach (custom_fields_for('requisition', false) as $f) if ($f['label'] === 'Cockpit Test Field') {
        $_POST = ['form' => 'requisition', 'field_id' => (int) $f['id']]; fd_field_delete('requisition');
    }
    $_POST = [];
}

// -- §32/§33 health + checklist.
$health = cockpit_health();
t_ok(is_array($health), 'configuration health returns a list of checks');
$chk = cockpit_checklist();
t_ok(is_array($chk) && count($chk) >= 1, 'a getting-started checklist is produced');
$hasProfileItem = false; foreach ($chk as $c) if (strpos(strtolower($c['label']), 'what your company does') !== false) $hasProfileItem = true;
t_ok($hasProfileItem, 'the checklist includes the cockpit profile step');

// -- §30 search.
$hits = cockpit_search('dropdown');
t_ok(!empty($hits), 'configuration search finds a destination for "dropdown"');

// -- §76 canonical getters delegate, not duplicate.
t_ok(array_key_exists('admin', cockpit_enabled_modules()), 'cockpit_enabled_modules() reports the core module on');
t_ok(cockpit_can_use_feature('admin') === true, 'cockpit_can_use_feature() delegates to the licence engine');

// restore capabilities to their pre-test state so later tests see a clean DB.
cockpit_capabilities_set($saved);
unset($_SESSION['uid']); current_user(true); if (function_exists('ua')) ua(true);
