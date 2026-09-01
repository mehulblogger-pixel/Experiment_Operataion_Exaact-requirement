<?php
// ============================================================================
//  CONNECT — Company Business Capabilities  (additive, backward-compatible)
//
//  A company is NOT a single fixed type. It ENABLES one, several or all business
//  capabilities — TPIA, technical-manpower supply, FREELANCE RESOURCE SUPPLY,
//  recruitment, project services, QA/QC, NDT … — and the app adapts to the
//  combination. This layers ON TOP OF the existing single `org_type` model
//  (connect_org.php) without replacing it: org_type stays the primary audience;
//  capabilities add the finer, multi-select business mix the market needs.
//
//  Design law (docs/00-master-revamp-prompt.md §4/§9, phase-2 canonical model):
//  additive table + read helpers only; capabilities gate VISIBILITY, never
//  permissions (the permission matrix still governs who-can-do-what); and when a
//  company has NO capabilities configured the engine is fully permissive, so
//  every existing company behaves exactly as before. Nothing is deleted, no
//  workflow changes, no permission is granted here.
// ============================================================================

/** The capability catalogue, grouped per master-prompt §7. Each capability maps
 *  to the coarse module keys it makes relevant (connect_all_modules()), which is
 *  what the Combination Engine reads. Extend this list additively; never remove. */
function connect_cap_catalog() {
    return [
        // ---- Inspection & Technical Services ----
        'TPIA'                 => ['label' => 'Third-Party Inspection Agency', 'group' => 'Inspection & Technical Services', 'modules' => ['operations', 'reporting']],
        'TIC'                  => ['label' => 'Testing, Inspection & Certification', 'group' => 'Inspection & Technical Services', 'modules' => ['operations', 'reporting']],
        'VENDOR_INSPECTION'    => ['label' => 'Vendor Inspection', 'group' => 'Inspection & Technical Services', 'modules' => ['operations', 'reporting']],
        'SHOP_INSPECTION'      => ['label' => 'Shop Inspection', 'group' => 'Inspection & Technical Services', 'modules' => ['operations', 'reporting']],
        'RESIDENT_INSPECTION'  => ['label' => 'Resident Inspection', 'group' => 'Inspection & Technical Services', 'modules' => ['operations', 'reporting']],
        'SITE_INSPECTION'      => ['label' => 'Site Inspection', 'group' => 'Inspection & Technical Services', 'modules' => ['operations', 'reporting']],
        'EXPEDITING'           => ['label' => 'Expediting', 'group' => 'Inspection & Technical Services', 'modules' => ['operations']],
        'VENDOR_SURVEILLANCE'  => ['label' => 'Vendor Surveillance', 'group' => 'Inspection & Technical Services', 'modules' => ['operations', 'reporting']],
        'QAQC'                 => ['label' => 'QA/QC Services', 'group' => 'Inspection & Technical Services', 'modules' => ['operations', 'reporting']],
        'NDT'                  => ['label' => 'NDT Services', 'group' => 'Inspection & Technical Services', 'modules' => ['operations', 'reporting']],
        // ---- Resource Supply ----
        'TECHNICAL_MANPOWER'   => ['label' => 'Technical Manpower Supply', 'group' => 'Resource Supply', 'modules' => ['operations', 'hr']],
        'CONTRACT_STAFFING'    => ['label' => 'Contract Staffing', 'group' => 'Resource Supply', 'modules' => ['operations', 'hr']],
        'PROJECT_STAFFING'     => ['label' => 'Project Staffing', 'group' => 'Resource Supply', 'modules' => ['operations', 'hr']],
        'SHUTDOWN_STAFFING'    => ['label' => 'Shutdown Staffing', 'group' => 'Resource Supply', 'modules' => ['operations', 'hr']],
        'TURNAROUND_STAFFING'  => ['label' => 'Turnaround Staffing', 'group' => 'Resource Supply', 'modules' => ['operations', 'hr']],
        'FREELANCE_SUPPLY'     => ['label' => 'Freelance Resource Supply', 'group' => 'Resource Supply', 'modules' => ['operations', 'hr']],
        'FREELANCE_INSPECTOR_SUPPLY' => ['label' => 'Freelance Inspector Supply', 'group' => 'Resource Supply', 'modules' => ['operations', 'hr', 'reporting']],
        'TECHNICAL_SPECIALIST_SUPPLY' => ['label' => 'Technical Specialist Supply', 'group' => 'Resource Supply', 'modules' => ['operations', 'hr']],
        // ---- Recruitment ----
        'TECH_RECRUITMENT'     => ['label' => 'Technical Recruitment', 'group' => 'Recruitment', 'modules' => ['hr']],
        'PERMANENT_PLACEMENT'  => ['label' => 'Permanent Placement', 'group' => 'Recruitment', 'modules' => ['hr']],
        'EXECUTIVE_SEARCH'     => ['label' => 'Executive Search', 'group' => 'Recruitment', 'modules' => ['hr']],
        'CONTRACT_RECRUITMENT' => ['label' => 'Contract Recruitment', 'group' => 'Recruitment', 'modules' => ['hr']],
        // ---- Project Services ----
        'PROJECT_MANAGEMENT'   => ['label' => 'Project Management', 'group' => 'Project Services', 'modules' => ['operations']],
        'PROJECT_ENGINEERING'  => ['label' => 'Project Engineering', 'group' => 'Project Services', 'modules' => ['operations']],
        'COMMISSIONING_SUPPORT'=> ['label' => 'Commissioning Support', 'group' => 'Project Services', 'modules' => ['operations']],
        'CONSTRUCTION_SUPPORT' => ['label' => 'Construction Support', 'group' => 'Project Services', 'modules' => ['operations']],
        'TECHNICAL_CONSULTANCY'=> ['label' => 'Technical Consultancy', 'group' => 'Project Services', 'modules' => ['operations']],
    ];
}

/** The catalogue's groups, in display order. */
function connect_cap_groups() {
    $g = [];
    foreach (connect_cap_catalog() as $c) if (!in_array($c['group'], $g, true)) $g[] = $c['group'];
    return $g;
}

/** The capabilities that always belong to the Freelance Technical Resource
 *  Supplier operating model (master-prompt §8). */
function connect_cap_freelance_supplier_codes() { return ['FREELANCE_SUPPLY', 'FREELANCE_INSPECTOR_SUPPLY']; }

function connect_cap_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS cx_org_capabilities (
        id $pk, org_party_id INT DEFAULT 0, capability_code VARCHAR(40) DEFAULT '',
        enabled INT DEFAULT 1, activated_by VARCHAR(150) DEFAULT '', activated_at VARCHAR(30) DEFAULT '')");
    try { db()->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_org_cap ON cx_org_capabilities (org_party_id, capability_code)"); } catch (Throwable $e) {}
}

/** Every capability code enabled for a company (its business_partners party id). */
function connect_org_caps($party) {
    connect_cap_migrate();
    $rows = ops_all("SELECT capability_code FROM cx_org_capabilities WHERE org_party_id=? AND enabled=1", [(int)$party]) ?: [];
    $cat = connect_cap_catalog();
    return array_values(array_filter(array_map(fn($r) => (string)$r['capability_code'], $rows), fn($c) => isset($cat[$c])));
}

/** Whether a company has a capability enabled. */
function connect_org_has_cap($party, $code) {
    return in_array(strtoupper((string)$code), array_map('strtoupper', connect_org_caps($party)), true);
}

/** Does this company have at least one capability ENABLED? Only then does the
 *  Combination Engine narrow what it sees; with none enabled (never set, or reset
 *  to empty) the company stays fully permissive — sees everything, as before. */
function connect_cap_configured($party) {
    connect_cap_migrate();
    return (int)ops_val("SELECT COUNT(*) FROM cx_org_capabilities WHERE org_party_id=? AND enabled=1", [(int)$party]) > 0;
}

/** Enable or disable one capability for a company (idempotent upsert). */
function connect_org_cap_set($party, $code, $on = true, $who = '') {
    connect_cap_migrate();
    $code = strtoupper((string)$code);
    if (!isset(connect_cap_catalog()[$code])) return false;
    $party = (int)$party; $on = $on ? 1 : 0;
    $exists = (int)ops_val("SELECT COUNT(*) FROM cx_org_capabilities WHERE org_party_id=? AND capability_code=?", [$party, $code]) > 0;
    if ($exists) db()->prepare("UPDATE cx_org_capabilities SET enabled=?, activated_by=?, activated_at=? WHERE org_party_id=? AND capability_code=?")->execute([$on, (string)$who, date('c'), $party, $code]);
    else        db()->prepare("INSERT INTO cx_org_capabilities (org_party_id,capability_code,enabled,activated_by,activated_at) VALUES (?,?,?,?,?)")->execute([$party, $code, $on, (string)$who, date('c')]);
    return true;
}

/** Set a company's FULL capability set at once: codes listed are enabled, all
 *  others in the catalogue disabled. This is what the admin screen submits. */
function connect_org_cap_bulk_set($party, array $codes, $who = '') {
    connect_cap_migrate();
    $want = array_map('strtoupper', $codes);
    foreach (array_keys(connect_cap_catalog()) as $code) connect_org_cap_set($party, $code, in_array($code, $want, true), $who);
    return true;
}

/** The Combination Engine: the union of module keys the company's enabled
 *  capabilities make relevant. Empty when nothing is configured. */
function connect_cap_modules($party) {
    $mods = [];
    $cat = connect_cap_catalog();
    foreach (connect_org_caps($party) as $code) foreach ((array)($cat[$code]['modules'] ?? []) as $m) $mods[$m] = true;
    return array_keys($mods);
}

/** Should a module surface for this company? VISIBILITY ONLY — never a permission.
 *  Fully permissive (returns true) until the company's capabilities are
 *  configured, so every existing company is unaffected. 'connect' and 'admin'
 *  are always visible. */
function connect_cap_shows($party, $moduleKey) {
    $moduleKey = (string)$moduleKey;
    // Universal modules every business needs — never gated by capabilities.
    // (Marketplace, Admin, Money/billing and Sales/CRM.) Only the specialist
    // modules — operations, reporting, hr — follow the capability mix.
    if (in_array($moduleKey, ['connect', 'admin', 'money', 'sales'], true)) return true;
    if (!connect_cap_configured($party)) return true;      // not configured → show everything (backward compatible)
    return in_array($moduleKey, connect_cap_modules($party), true);
}

/** The Freelance Technical Resource Supplier's three sourcing pools, read-only,
 *  reusing the existing bench / sourcing engines. Guarded so a missing engine or
 *  column never fatals — it degrades to zero. (master-prompt §8/§23) */
function connect_supplier_pools($party) {
    $party = (int)$party;
    $count = function ($sql, $args = []) { try { return (int)ops_val($sql, $args); } catch (Throwable $e) { return 0; } };
    return [
        'internal'    => $count("SELECT COUNT(*) FROM cx_bench_alloc WHERE professional_id>0"),                 // directly managed
        'associated'  => $count("SELECT COUNT(*) FROM cx_professionals WHERE COALESCE(association,'')<>''"),     // associated freelancers
        'marketplace' => $count("SELECT COUNT(*) FROM cx_professionals WHERE is_active=1"),                      // marketplace-sourced pool
    ];
}

// ---------------------------------------------------------------------------
//  Stage 6 — the operating company drives the workspace navigation.
//  In this (single-tenant) install one company owns the workspace; its
//  capabilities tailor which specialist areas the staff nav shows. Until an
//  operating company is designated the engine stays fully permissive, so the
//  nav is unchanged for every existing install.
// ---------------------------------------------------------------------------

/** The operating company's party id (0 = not designated → permissive). */
function connect_cap_owner_party() {
    $p = function_exists('setting_get') ? (int)setting_get('cap_owner_party') : 0;
    return $p > 0 ? $p : 0;
}

/** Designate (or clear, with 0) the operating company. */
function connect_cap_owner_set($party) {
    if (function_exists('setting_set')) setting_set('cap_owner_party', (string)(int)$party);
}

/** The capability codes in one catalogue group. */
function connect_cap_group_codes($group) {
    $codes = [];
    foreach (connect_cap_catalog() as $code => $c) if ($c['group'] === $group) $codes[] = $code;
    return $codes;
}

/** Does the OPERATING company have any capability in this group? Permissive
 *  (true) when no operating company is set or it is unconfigured — so the nav
 *  only ever narrows once an admin has deliberately tailored the workspace. */
function connect_cap_owner_has_group($group) {
    $owner = connect_cap_owner_party();
    if (!$owner || !connect_cap_configured($owner)) return true;
    return (bool)array_intersect(connect_cap_group_codes($group), connect_org_caps($owner));
}

/** Whether the operating company does any inspection / technical-services work
 *  (gates the Quality & Accreditation area — a pure recruiter never sees it). */
function connect_cap_owner_does_inspection() {
    return connect_cap_owner_has_group('Inspection & Technical Services');
}

/** Should the operating company see a module area? Reads connect_cap_shows for the
 *  operating company, permissive when none is designated. Drives area-level nav
 *  gating (e.g. Reporting shows only when the company's mix produces reports —
 *  inspection work or freelance-inspector supply). */
function connect_cap_owner_shows($moduleKey) {
    $owner = connect_cap_owner_party();
    if (!$owner) return true;
    return connect_cap_shows($owner, $moduleKey);
}

// ---------------------------------------------------------------------------
//  Master-admin screen — set each company's business capabilities.
// ---------------------------------------------------------------------------
function ops_connect_capabilities($method) {
    ops_require(function_exists('is_master') && is_master(), 'Only a master admin can manage company capabilities.');
    connect_cap_migrate();
    if ($method === 'POST') {
        $party = (int)($_POST['party_id'] ?? 0);
        $act = (string)($_POST['action'] ?? 'save');
        if ($act === 'set_owner') {
            connect_cap_owner_set($party);
            flash($party > 0 ? 'Operating company set — the workspace now follows its capabilities.' : 'Operating company cleared — the workspace shows everything again.');
            redirect('/connect-capabilities' . ($party > 0 ? '?party=' . $party : ''));
        }
        if ($party > 0) {
            $codes = array_map('strval', (array)($_POST['caps'] ?? []));
            connect_org_cap_bulk_set($party, $codes, function_exists('user_name') ? user_name(current_user()) : 'admin');
            flash('Capabilities saved for the company.');
        } else flash('Pick a company first.', 'error');
        redirect('/connect-capabilities' . ($party > 0 ? '?party=' . $party : ''));
    }
    // Companies = the client/vendor/agency masters, plus any registered orgs.
    // Agencies are represented as vendors in business_partners (there is no
    // is_agency column) — is_client OR is_vendor covers every company master.
    $companies = ops_all("SELECT id, COALESCE(display_name, legal_name, code) AS name, code FROM business_partners
                          WHERE COALESCE(is_client,0)=1 OR COALESCE(is_vendor,0)=1
                          ORDER BY name LIMIT 500") ?: [];
    $sel = (int)($_GET['party'] ?? ($companies[0]['id'] ?? 0));
    view('ops/connect_capabilities', [
        'companies' => $companies, 'sel' => $sel,
        'catalog' => connect_cap_catalog(), 'groups' => connect_cap_groups(),
        'enabled' => $sel ? connect_org_caps($sel) : [],
        'configured' => $sel ? connect_cap_configured($sel) : false,
        'pools' => $sel ? connect_supplier_pools($sel) : ['internal' => 0, 'associated' => 0, 'marketplace' => 0],
        'is_supplier' => $sel ? (bool)array_intersect(connect_cap_freelance_supplier_codes(), connect_org_caps($sel)) : false,
        'owner' => connect_cap_owner_party(), 'is_owner' => ($sel && $sel === connect_cap_owner_party()),
        'does_inspection' => connect_cap_owner_does_inspection(),
    ]);
    return true;
}
