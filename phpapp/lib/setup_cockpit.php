<?php
// ============================================================================
//  COMPANY SETUP COCKPIT — Configuration Orchestrator (Phase 1)
//
//  The single new architectural capability of Phase 1. It answers, for the
//  signed-in company:
//     • What does this company do?            (business capabilities)
//     • What features are turned on?          (modules)
//     • What is configured / still needs work? (status + health)
//     • Where do I go to change it?           (contextual links)
//
//  It is an ORCHESTRATOR, not an engine. It stores nothing that another system
//  already owns — it READS the existing engines and LINKS to their screens:
//     modules      → licence.php        (licence_summary / licence_save / licence_enabled)
//     navigation   → areas.php          (ops_area_def / ops_area_has)
//     forms        → formdesign.php + customforms.php
//     masters      → lookups.php        (lookup_types / custom_fields)
//     roles        → access.php         (ORG_ROLES / role_perms)
//     terminology  → terms.php          (term_overrides)
//     audit        → idems.php          (idems_log, via setting_set)
//
//  The ONLY data it owns is the tenant's multi-select business capability
//  choice (company_capabilities), whose CATALOGUE is reused from
//  connect_cap_catalog() — no new catalogue is invented. See
//  docs/PHASE1-IMPLEMENTATION-MAP.md.
// ============================================================================

// ---- Schema (orchestration-only) ------------------------------------------
function cockpit_migrate() {
    static $doneAt = -1;
    if (function_exists('db_epoch')) { if ($doneAt === db_epoch()) return; $doneAt = db_epoch(); }
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        // The tenant's own "what we do" — many-to-many (one row per chosen
        // capability), so a company can be Recruitment + Technical Manpower at once.
        db()->exec("CREATE TABLE IF NOT EXISTS company_capabilities (
            id $pk,
            capability_code VARCHAR(48) DEFAULT '',
            enabled INT DEFAULT 1,
            chosen_by VARCHAR(120) DEFAULT '',
            chosen_at VARCHAR(30) DEFAULT '')");
        try { db()->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_company_cap ON company_capabilities (capability_code)"); } catch (Throwable $e) {}
    } catch (Throwable $e) { /* never block the boot chain */ }
}

// ---- Who may open the cockpit (§47) ----------------------------------------
// A tenant administrator: master, or the settings.manage grant. A normal
// recruiter/user cannot configure the company.
function cockpit_can() {
    return (function_exists('is_master') && is_master())
        || (function_exists('can') && can('settings.manage'));
}

// ===========================================================================
//  BUSINESS CAPABILITIES  ("what does my company do?")
//  Catalogue REUSED from connect_cap_catalog(); selection stored per tenant.
// ===========================================================================

// The catalogue, grouped, reused from the marketplace capability catalogue so
// the two never diverge. Falls back to a minimal built-in list only if that
// engine is unavailable, so the cockpit still works in isolation.
function cockpit_capability_catalogue() {
    if (function_exists('connect_cap_catalog')) {
        try { $cat = connect_cap_catalog(); if (is_array($cat) && $cat) return $cat; } catch (Throwable $e) {}
    }
    return [
        'TECH_RECRUITMENT'   => ['label' => 'Technical Recruitment', 'group' => 'Recruitment', 'modules' => ['hr']],
        'PERMANENT_PLACEMENT'=> ['label' => 'Permanent Placement', 'group' => 'Recruitment', 'modules' => ['hr']],
        'TECHNICAL_MANPOWER' => ['label' => 'Technical Manpower Supply', 'group' => 'Resource Supply', 'modules' => ['operations', 'hr']],
        'CONTRACT_STAFFING'  => ['label' => 'Contract Staffing', 'group' => 'Resource Supply', 'modules' => ['operations', 'hr']],
        'TPIA'               => ['label' => 'Third-Party Inspection Agency', 'group' => 'Inspection & Technical Services', 'modules' => ['operations', 'reporting']],
        'TECHNICAL_CONSULTANCY' => ['label' => 'Technical Consultancy', 'group' => 'Project Services', 'modules' => ['operations']],
    ];
}

// The catalogue grouped by its display groups → ['Group' => [code => label]],
// FILTERED to what this company can actually use. An activity is shown only when
// the company is entitled to every module that activity needs — so a
// Recruitment-only company sees the Recruitment activities, not the inspection /
// resource-supply / project activities that belong to modules it hasn't
// licensed. Any activity the company has already chosen is always shown, so a
// past choice is never silently dropped. (On the control / owner install nothing
// is entitlement-limited, so the full catalogue shows.)
function cockpit_capability_groups($chosen = []) {
    $chosen = array_flip(array_map('strval', (array) $chosen));
    $out = [];
    foreach (cockpit_capability_catalogue() as $code => $c) {
        if (!isset($chosen[$code])) {
            $relevant = true;
            foreach (($c['modules'] ?? []) as $m) {
                if (function_exists('module_entitled') && !module_entitled($m)) { $relevant = false; break; }
            }
            if (!$relevant) continue;
        }
        $g = $c['group'] ?? 'Other';
        $out[$g][$code] = $c['label'] ?? $code;
    }
    return $out;
}

// The capability codes this company has chosen (enabled).
function cockpit_capabilities() {
    cockpit_migrate();
    try {
        $rows = ops_all("SELECT capability_code FROM company_capabilities WHERE enabled=1");
        $cat = cockpit_capability_catalogue();
        return array_values(array_filter(array_map(fn($r) => (string) $r['capability_code'], $rows), fn($c) => isset($cat[$c])));
    } catch (Throwable $e) { return []; }
}

// Replace the whole chosen set at once (multi-select). Audited via the existing
// chain. Returns the codes that were saved.
function cockpit_capabilities_set(array $codes) {
    cockpit_migrate();
    $cat = cockpit_capability_catalogue();
    $codes = array_values(array_unique(array_filter($codes, fn($c) => isset($cat[$c]))));
    $who = function_exists('user_name') && function_exists('current_user') ? (string) user_name(current_user()) : '';
    $before = cockpit_capabilities();
    try {
        db()->prepare("DELETE FROM company_capabilities")->execute();
        $ins = db()->prepare("INSERT INTO company_capabilities (capability_code,enabled,chosen_by,chosen_at) VALUES (?,?,?,?)");
        foreach ($codes as $c) $ins->execute([$c, 1, $who, date('c')]);
        // Reuse the existing audit chain (§48) — never a second audit engine.
        if (function_exists('idems_log') && $before !== $codes) {
            idems_log('setting', null, 'COMPANY_CAPABILITIES_CHANGED',
                ['field' => 'business_capabilities',
                 'new' => ['from' => $before, 'to' => $codes]]);
        }
    } catch (Throwable $e) { return $before; }
    return $codes;
}

// The modules a set of capabilities makes relevant (data-driven, from the
// catalogue). Phase 1 only READS this to explain "why is this available"; the
// full recommendation/auto-enable engine is Phase 2 and is NOT built here.
function cockpit_capability_modules($codes = null) {
    $codes = $codes === null ? cockpit_capabilities() : $codes;
    $cat = cockpit_capability_catalogue();
    $mods = [];
    foreach ($codes as $c) foreach (($cat[$c]['modules'] ?? []) as $m) $mods[$m] = true;
    return array_keys($mods);
}

// ===========================================================================
//  MODULES & FEATURES  — reads licence.php, never a second module engine (§13)
// ===========================================================================

// Plain-English descriptions for the sellable modules (customer language, §15/§18).
function cockpit_module_blurb($key) {
    static $m = [
        'admin'      => 'The basics every workspace needs — your people, settings and lists.',
        'operations' => 'Plan and run field work — jobs, scheduling and deployment.',
        'sales'      => 'Win work — leads, enquiries, quotations and the pipeline.',
        'reporting'  => 'Write and approve inspection reports and their formats.',
        'money'      => 'Bill your work — invoices, receipts and profitability.',
        'hr'         => 'Hire and place people — requirements, candidates and offers.',
    ];
    return $m[$key] ?? '';
}

// Every sellable module for the cockpit's Modules screen. Each carries: on/off,
// core flag, its features (the access-modules it covers, in friendly words),
// why it is available, and which features would go if it were turned off.
function cockpit_modules() {
    if (!function_exists('licence_summary')) return [];
    $sum = licence_summary();
    $capMods = cockpit_capability_modules();
    $out = [];
    foreach ($sum as $key => $row) {
        $covers = defined('PRODUCT_MODULES') && isset(PRODUCT_MODULES[$key][2]) ? PRODUCT_MODULES[$key][2] : [];
        $features = [];
        foreach ($covers as $c) $features[] = function_exists('access_module_label') ? access_module_label($c) : $c;
        // Is the company entitled to this module (has it paid for / been granted it)?
        // A locked module is outside the plan — it can never be switched on here.
        $entitled = !function_exists('module_entitled') || module_entitled($key);
        $locked   = !$entitled && empty($row['core']);
        // Why is this available? (§16) — data-driven.
        if (!empty($row['core']))              $why = 'Always on — every workspace needs it.';
        elseif ($locked)                       $why = 'Not in your plan — upgrade to add it.';
        elseif (in_array($key, $capMods, true)) $why = 'Available because of what your company does.';
        elseif (!empty($row['on']))             $why = 'Included in your current plan.';
        else                                    $why = 'In your plan — turn it on when you need it.';
        $out[$key] = [
            'key'       => $key,
            'label'     => $row['label'],
            'blurb'     => cockpit_module_blurb($key) ?: ($row['desc'] ?? ''),
            'on'        => !empty($row['on']),
            'core'      => !empty($row['core']),
            'entitled'  => $entitled,
            'locked'    => $locked,
            'features'  => $features,
            'why'       => $why,
        ];
    }
    return $out;
}

// If this module were turned off, which features would the company lose? (§17)
// Read straight from the module's own coverage — no bespoke dependency store.
function cockpit_module_dependents($key) {
    $covers = defined('PRODUCT_MODULES') && isset(PRODUCT_MODULES[$key][2]) ? PRODUCT_MODULES[$key][2] : [];
    $out = [];
    foreach ($covers as $c) $out[] = function_exists('access_module_label') ? access_module_label($c) : $c;
    return $out;
}

// ===========================================================================
//  SETUP SECTIONS + STATUS  (§7, §9)  — computed from real data, not hard-coded
// ===========================================================================

// One status vocabulary (§9). Each section returns one of these.
const COCKPIT_STATUSES = ['not_started', 'in_progress', 'configured', 'needs_attention', 'complete'];

function cockpit_status_label($s) {
    static $m = ['not_started' => 'Not started', 'in_progress' => 'In progress',
        'configured' => 'Configured', 'needs_attention' => 'Needs attention', 'complete' => 'Complete'];
    return $m[$s] ?? ucfirst(str_replace('_', ' ', (string) $s));
}

// A count helper that never throws (missing table reads as 0).
function _ck_count($sql, $args = []) { try { return (int) ops_val($sql, $args); } catch (Throwable $e) { return 0; } }

// The dynamic list of setup sections for THIS company. Only sections relevant to
// the enabled modules appear; each carries status + a percent + where to go.
function cockpit_sections() {
    $hr   = !function_exists('licence_enabled') || licence_enabled('hr');
    $secs = [];

    // 1) Business profile — company name + at least one capability chosen.
    $hasName = trim((string) (function_exists('setting_get') ? setting_get('company_name', '') : '')) !== ''
            || trim((string) (function_exists('setting_get') ? setting_get('app_name', '') : '')) !== '';
    $caps    = cockpit_capabilities();
    $bpPct   = ($hasName ? 50 : 0) + ($caps ? 50 : 0);
    $secs['business_profile'] = [
        'key' => 'business_profile', 'icon' => '🏢', 'label' => 'Company profile',
        'desc' => 'Your company name, details and what you do.',
        'route' => '/company-profile',
        'status' => $bpPct >= 100 ? 'complete' : ($bpPct > 0 ? 'in_progress' : 'not_started'),
        'pct' => $bpPct, 'weight' => 1,
    ];

    // 2) Modules — always decided (a plan is applied). Informational-complete.
    $onCount = 0; foreach (cockpit_modules() as $m) if ($m['on']) $onCount++;
    $secs['modules'] = [
        'key' => 'modules', 'icon' => '🧩', 'label' => 'Features',
        'desc' => 'Which parts of the software your workspace uses.',
        'route' => '/workspace/setup/modules',
        'status' => 'complete', 'pct' => 100, 'weight' => 1, 'note' => $onCount . ' on',
    ];

    // 3) Forms — only when People & hiring is on (the designable forms). Configured
    //    unless a health check finds a problem (computed below and folded in).
    if ($hr && function_exists('fd_forms')) {
        $forms = fd_forms();
        $problem = false;
        foreach (cockpit_health() as $h) if ($h['level'] === 'warn' && ($h['area'] ?? '') === 'forms') $problem = true;
        $secs['forms'] = [
            'key' => 'forms', 'icon' => '📝', 'label' => 'Forms',
            'desc' => 'The fields on your ' . implode(' & ', array_map(fn($f) => $f['label'] ?? '', $forms)) . ' forms.',
            'route' => '/workspace/setup/forms',
            'status' => $problem ? 'needs_attention' : 'configured',
            'pct' => $problem ? 60 : 100, 'weight' => 1,
        ];
    }

    // 4) Masters & dropdowns — In progress until at least one list has values.
    $vals = _ck_count("SELECT COUNT(*) FROM lookup_values WHERE active=1");
    $secs['masters'] = [
        'key' => 'masters', 'icon' => '📋', 'label' => 'Dropdown lists',
        'desc' => 'The options behind your form dropdowns.',
        'route' => '/masters',
        'status' => $vals > 0 ? 'configured' : 'in_progress',
        'pct' => $vals > 0 ? 100 : 40, 'weight' => 1,
    ];

    // 5) People & roles — configured; needs attention if a health check flags a
    //    missing role for an enabled module.
    $roleProblem = false;
    foreach (cockpit_health() as $h) if ($h['level'] === 'warn' && ($h['area'] ?? '') === 'roles') $roleProblem = true;
    $team = _ck_count("SELECT COUNT(*) FROM users WHERE COALESCE(is_active,1)=1");
    $secs['roles'] = [
        'key' => 'roles', 'icon' => '👥', 'label' => 'People & access',
        'desc' => 'Who can sign in and what they can do.',
        'route' => '/users',
        'status' => $roleProblem ? 'needs_attention' : ($team > 1 ? 'complete' : 'configured'),
        'pct' => $roleProblem ? 70 : 100, 'weight' => 1, 'note' => $team . ' ' . ($team === 1 ? 'person' : 'people'),
    ];

    // 6) Terminology — defaults always work; "complete" (customised if overrides).
    $termCust = false;
    if (function_exists('term_overrides')) { try { $termCust = (bool) term_overrides(); } catch (Throwable $e) {} }
    $secs['terminology'] = [
        'key' => 'terminology', 'icon' => '🔤', 'label' => 'Wording',
        'desc' => 'Rename things to match how your business speaks.',
        'route' => '/terminology',
        'status' => 'complete', 'pct' => 100, 'weight' => 0,
        'note' => $termCust ? 'customised' : 'default',
    ];

    return $secs;
}

// Overall workspace readiness (§8) — a weighted average across the meaningful
// sections. Informational only; it never blocks use of the app (§8).
function cockpit_readiness() {
    $tot = 0; $wsum = 0;
    foreach (cockpit_sections() as $s) {
        $w = $s['weight'] ?? 1;
        if ($w <= 0) continue;
        $tot += ($s['pct'] ?? 0) * $w; $wsum += $w;
    }
    return $wsum > 0 ? (int) round($tot / $wsum) : 0;
}

// ===========================================================================
//  CONFIGURATION HEALTH  (§32)  — a small, extensible set of real checks.
//  Deliberately NOT a big rule engine. Each check returns a row or nothing.
// ===========================================================================
function cockpit_health() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $out = [];
    $ok   = fn($area, $msg) => ['level' => 'ok',   'area' => $area, 'msg' => $msg];
    $warn = fn($area, $msg, $route = '') => ['level' => 'warn', 'area' => $area, 'msg' => $msg, 'fix' => $route];
    $hr   = !function_exists('licence_enabled') || licence_enabled('hr');

    // Recruitment on but no recruiter-capable role/person.
    if ($hr) {
        $recruiters = _ck_count("SELECT COUNT(*) FROM users WHERE COALESCE(is_active,1)=1");
        if ($recruiters <= 1)
            $out[] = $warn('roles', 'You are the only user — add your recruiters so work can be shared.', '/users');
        else
            $out[] = $ok('roles', 'Your team is set up.');
    }

    // Dropdown lists still empty (fine to use — forms fall back — but worth noting).
    $vals = _ck_count("SELECT COUNT(*) FROM lookup_values WHERE active=1");
    if ($vals === 0) $out[] = $warn('masters', 'No dropdown options yet — add your departments, sources, etc. when ready.', '/masters');
    else             $out[] = $ok('masters', 'Dropdown lists have options.');

    // Business profile capability not chosen.
    if (!cockpit_capabilities())
        $out[] = $warn('business_profile', 'Tell us what your company does so we can tailor your workspace.', '/company-profile');

    return $cache = $out;
}

// A dynamic getting-started checklist (§33). Reuses onboarding_steps() where
// present (one set of steps, not a competing list) and adds cockpit-owned items.
function cockpit_checklist() {
    $items = [];
    // Business profile item (cockpit-owned).
    $items[] = ['done' => (bool) cockpit_capabilities(),
        'label' => 'Tell us what your company does', 'fix' => '/company-profile'];
    // Reuse the existing onboarding steps verbatim (§36 — no duplicate questions).
    if (function_exists('onboarding_steps')) {
        try {
            foreach (onboarding_steps() as $s) {
                $items[] = ['done' => !empty($s['done']),
                    'label' => (string) ($s['label'] ?? ''), 'fix' => (string) ($s['where'] ?? '/')];
            }
        } catch (Throwable $e) {}
    }
    return $items;
}

// ===========================================================================
//  CONFIGURATION SEARCH  (§30)  — over a small index of real destinations.
// ===========================================================================
function cockpit_search_index() {
    $hr = !function_exists('licence_enabled') || licence_enabled('hr');
    $idx = [
        ['label' => 'Business profile', 'route' => '/company-profile', 'kw' => 'company what we do capabilities industry activity'],
        ['label' => 'Features & modules', 'route' => '/workspace/setup/modules', 'kw' => 'module feature turn on off enable recruitment manpower crm inspection'],
        ['label' => 'Forms', 'route' => '/workspace/setup/forms', 'kw' => 'form field label required dropdown candidate requisition requirement builder'],
        ['label' => 'Dropdown lists', 'route' => '/masters', 'kw' => 'dropdown list master value option department source employment type'],
        ['label' => 'People & access', 'route' => '/users', 'kw' => 'user role permission recruiter manager access who can'],
        ['label' => 'Roles & permissions', 'route' => '/access', 'kw' => 'role permission access grant recruiter hiring manager'],
        ['label' => 'Wording / terminology', 'route' => '/terminology', 'kw' => 'terminology wording rename candidate client label word'],
        ['label' => 'Company profile', 'route' => '/company-profile', 'kw' => 'legal name logo address company details'],
    ];
    if ($hr) {
        $idx[] = ['label' => 'Candidate form', 'route' => '/form-designer?form=candidate', 'kw' => 'candidate applicant form field name experience'];
        $idx[] = ['label' => 'Requirement form', 'route' => '/form-designer?form=requisition', 'kw' => 'requisition requirement job form field'];
    }
    return $idx;
}
function cockpit_search($q) {
    $q = strtolower(trim((string) $q));
    if ($q === '') return [];
    $terms = preg_split('/\s+/', $q);
    $out = [];
    foreach (cockpit_search_index() as $row) {
        $hay = strtolower($row['label'] . ' ' . $row['kw']);
        $hit = true;
        foreach ($terms as $t) if ($t !== '' && strpos($hay, $t) === false) { $hit = false; break; }
        if ($hit) $out[] = ['label' => $row['label'], 'route' => $row['route']];
    }
    return $out;
}

// ===========================================================================
//  §76 CANONICAL GETTERS — thin delegators over the existing engines, so the
//  cockpit exposes one orchestration surface WITHOUT duplicating logic. Named in
//  the codebase's snake_case; the mapping to the brief's names is in the comment.
// ===========================================================================
function cockpit_enabled_modules() {           // getEnabledModules()
    $out = [];
    foreach (cockpit_modules() as $k => $m) if ($m['on']) $out[$k] = $m['label'];
    return $out;
}
function cockpit_enabled_features() {           // getEnabledFeatures()
    $out = [];
    foreach (cockpit_modules() as $m) if ($m['on']) foreach ($m['features'] as $f) $out[] = $f;
    return array_values(array_unique($out));
}
function cockpit_can_use_feature($moduleKey) {  // canTenantUseFeature()
    return !function_exists('licence_enabled') || licence_enabled($moduleKey);
}
function cockpit_configuration() {              // getTenantConfiguration()
    return [
        'capabilities' => cockpit_capabilities(),
        'modules'      => cockpit_enabled_modules(),
        'features'     => cockpit_enabled_features(),
        'sections'     => cockpit_sections(),
        'readiness'    => cockpit_readiness(),
        'health'       => cockpit_health(),
    ];
}

// ===========================================================================
//  Turn a feature on/off — delegates to the ONE module engine (licence_save).
//  Never writes a second module record (§13, §58). Core modules can't change.
//  Turning OFF asks for confirmation first, listing what depends on it (§17).
// ===========================================================================
function cockpit_module_apply($key, $on, $confirmed) {
    if (!function_exists('licence_summary') || !function_exists('licence_save')) {
        flash('The features engine is unavailable.', 'error'); redirect('/workspace/setup/modules');
    }
    $sum = licence_summary();
    if (!isset($sum[$key]) || !empty($sum[$key]['core'])) {
        flash('That is a core feature and can’t be turned off — every workspace needs it.', 'error');
        redirect('/workspace/setup/modules');
    }
    // The entitlement lock: a company can never switch ON a module outside its plan.
    if ($on && function_exists('module_entitled') && !module_entitled($key)) {
        flash('“' . ($sum[$key]['label'] ?? $key) . '” isn’t in your plan yet. Upgrade to add it to your workspace.', 'error');
        redirect('/workspace/setup/modules');
    }
    // Turning OFF with dependents → confirm first (never silently disable, §17).
    if (!$on && !$confirmed && cockpit_module_dependents($key)) {
        redirect('/workspace/setup/modules?confirm_off=' . urlencode($key));
    }
    // Rebuild the full on-set from current state, flip the one, hand to licence_save.
    $modOn = [];
    foreach ($sum as $k => $r) {
        if (!empty($r['core'])) continue;
        $isOn = !empty($r['on']);
        if ($k === $key) $isOn = $on;
        if ($isOn) $modOn[$k] = 1;
    }
    licence_save(['mod_on' => $modOn]);   // writes settings.modules_off — the one engine
    flash(($sum[$key]['label'] ?? $key) . ($on ? ' is now on.' : ' is now off.'));
    redirect('/workspace/setup/modules');
}

// ===========================================================================
//  ROUTE HANDLER — /workspace/setup and its sub-pages. Tenant-admin only (§47).
//  Every sub-page reuses the canonical engines; the cockpit only orchestrates.
// ===========================================================================
function ops_cockpit($route, $method) {
    ops_require(cockpit_can(), 'Only a workspace administrator can configure the company.');
    cockpit_migrate();

    // --- POST actions ---
    if ($route === 'workspace/setup/profile-save' && $method === 'POST') {
        // Company name (optional convenience) + the multi-select capabilities.
        $name = trim((string) ($_POST['company_name'] ?? ''));
        if ($name !== '' && function_exists('setting_set')) setting_set('company_name', $name);
        cockpit_capabilities_set(array_map('strval', (array) ($_POST['caps'] ?? [])));
        // Mark the profile step visited so "resume" advances (§38).
        if (function_exists('setting_set')) setting_set('cockpit_profile_done', '1');
        flash('Saved. Your workspace profile is up to date.');
        redirect('/company-profile');
    }
    if ($route === 'workspace/setup/module-toggle' && $method === 'POST') {
        $key = (string) ($_POST['module'] ?? '');
        $on  = !empty($_POST['on']);
        $confirmed = !empty($_POST['confirm']);
        cockpit_module_apply($key, $on, $confirmed);
        return true;
    }

    // --- GET pages ---
    switch ($route) {
        case 'workspace/setup/profile':
            // Merged into the single "Company profile" screen (identity + what you
            // do). Kept as a redirect so old links / bookmarks keep working.
            redirect('/company-profile');
            return true;
        case 'workspace/setup/modules':
            view('ops/cockpit_modules', [
                'modules'    => cockpit_modules(),
                'confirmOff' => (string) ($_GET['confirm_off'] ?? ''),
            ]);
            return true;
        case 'workspace/setup/forms':
            $forms = function_exists('fd_forms') ? fd_forms() : [];
            $customCounts = [];
            foreach ($forms as $fk => $f) {
                $customCounts[$fk] = function_exists('fd_custom_fields') ? count(fd_custom_fields($fk)) : 0;
            }
            view('ops/cockpit_forms', ['forms' => $forms, 'customCounts' => $customCounts]);
            return true;
        default:
            // The cockpit home.
            view('ops/cockpit_home', [
                'sections'  => cockpit_sections(),
                'readiness' => cockpit_readiness(),
                'health'    => cockpit_health(),
                'checklist' => cockpit_checklist(),
                'caps'      => cockpit_capabilities(),
                'capCat'    => cockpit_capability_catalogue(),
                'search'    => cockpit_search((string) ($_GET['q'] ?? '')),
                'q'         => (string) ($_GET['q'] ?? ''),
            ]);
            return true;
    }
}
