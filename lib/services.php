<?php
// ===========================================================================
//  SERVICE SCOPE ENGINE — service independence, activation & override
// ---------------------------------------------------------------------------
//  The governing rule: NO service is a mandatory prerequisite for another at
//  runtime. Inspection, Expediting, Vendor Assessment, Vendor Audit, Project
//  Deputation and Release each run on their own. The build order of the phases
//  is implementation sequence only — it is NOT a business workflow.
//
//  This engine is the mechanism that enforces that. It answers two orthogonal
//  questions the rest of the app was conflating:
//
//    1. Is this SERVICE OFFERED at all on this installation? — the global
//       catalog toggle. Composes with (never fights) the commercial licence:
//       a service whose licence module is switched off is simply unavailable.
//       This drives what appears in the menu.
//
//    2. Is this service IN SCOPE for this client / contract / project / PO /
//       assignment? — the per-scope activation, resolved most-specific-first:
//         Client default → Contract → Project → PO/Package → Assignment.
//       The most specific level that has an explicit setting wins; if nothing
//       is set anywhere, the catalog default applies. This drives what a user
//       may create / see WITHIN a client, without ever making one service
//       depend on another.
//
//  Optional dependencies (Service A before Service B, with a condition /
//  approval / effective date / scope) exist ONLY where a specific client or
//  contract explicitly configures one. There is NO built-in forced workflow,
//  and a module NEVER fails merely because a related module is inactive — every
//  cross-service link in the app is already guarded by function_exists() and
//  reads live data, so an inactive service is simply absent, not an error.
//
//  Additive and non-destructive: with nothing configured, every service is
//  globally active and in scope everywhere — the app behaves exactly as before.
// ===========================================================================

// The saleable / operational services. code => [name, licence module_key,
// permission_key (''=none), route, nav_group, icon, description].
// licence module_key ties a service to the commercial licence (licence_enabled);
// '' means the service is not licence-gated. Nothing downstream hard-codes these
// beyond this table — they are seeded into service_catalog and editable there.
const SERVICE_CATALOG = [
    'INSPECTION'        => ['Inspection',        'reporting',  'mod.idems.view',  '/documents',           'Reporting',  '🔬',
        'Third-party / in-process / final inspection reporting.'],
    'EXPEDITING'        => ['Expediting',        'reporting',  'mod.idems.view',  '/expediting',          'Reporting',  '🚚',
        'Supplier progress, schedule and delivery expediting.'],
    'VENDOR_ASSESSMENT' => ['Vendor Assessment', 'reporting',  'mod.idems.view',  '/documents',           'Reporting',  '🏢',
        'Vendor prequalification, qualification and supplier evaluation.'],
    'VENDOR_AUDIT'      => ['Vendor Audit',      'reporting',  'mod.idems.view',  '/documents',           'Reporting',  '🔍',
        'Vendor / process / management-system audits against defined criteria.'],
    'RELEASE'           => ['Release',           'reporting',  'mod.idems.view',  '/documents',           'Reporting',  '🎫',
        'Release notes / acceptance / disposition as a controlled consequence of verification.'],
    'DEPUTATION'        => ['Project Deputation', 'operations', 'mod.calls.view',  '/calls',               'Operations', '👷',
        'Manpower / project deputation and site assignments.'],
    'NCR_CAPA'          => ['Nonconformity & CAPA', 'operations', 'mod.ncr.view',  '/issues',              'Operations', '⚠️',
        'Universal issue engine — NCR, nonconformity, CAPA, deviation, concession & corrective action.'],
];

// Scope levels, MOST SPECIFIC FIRST. The context key each level reads from the
// resolution context ($ctx) passed by callers.
const SERVICE_SCOPE_LEVELS = [
    'ASSIGNMENT' => ['Assignment',      'assignment_id'],
    'PO'         => ['PO / Package',    'po_id'],
    'PROJECT'    => ['Project',         'project_id'],
    'CONTRACT'   => ['Contract',        'contract_id'],
    'CLIENT'     => ['Client default',  'client_id'],
];

// ---------------------------------------------------------------------------
//  Schema — additive only.
// ---------------------------------------------------------------------------
function services_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = pk_clause();
    db()->exec("CREATE TABLE IF NOT EXISTS service_catalog (
        id $pk, code VARCHAR(40), name VARCHAR(120) DEFAULT '', description VARCHAR(400) DEFAULT '',
        module_key VARCHAR(40) DEFAULT '', permission_key VARCHAR(60) DEFAULT '', route VARCHAR(80) DEFAULT '',
        nav_group VARCHAR(60) DEFAULT '', icon VARCHAR(12) DEFAULT '',
        globally_active INT DEFAULT 1, default_scope_active INT DEFAULT 1,
        is_system INT DEFAULT 0, sort_order INT DEFAULT 0, updated_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('service_catalog', 'code');
    // Per-entity activation. level+ref_id=0 is never used (that is the catalog
    // default). One row per (level, ref_id, service_code).
    db()->exec("CREATE TABLE IF NOT EXISTS service_scope (
        id $pk, level VARCHAR(20) DEFAULT 'CLIENT', ref_id INT DEFAULT 0, service_code VARCHAR(40) DEFAULT '',
        active INT DEFAULT 1, effective_on VARCHAR(20) DEFAULT '', note VARCHAR(400) DEFAULT '',
        set_by VARCHAR(120) DEFAULT '', set_at VARCHAR(30) DEFAULT '')");
    idems_unique_index('service_scope', 'level,ref_id,service_code');
    // Optional, configurable dependencies. service_code REQUIRES requires_code
    // first — but only where explicitly configured (ref_id=0 => global default,
    // else scoped to a specific client/contract/project/PO/assignment).
    db()->exec("CREATE TABLE IF NOT EXISTS service_dependencies (
        id $pk, service_code VARCHAR(40) DEFAULT '', requires_code VARCHAR(40) DEFAULT '',
        condition_note VARCHAR(300) DEFAULT '', requires_approval INT DEFAULT 0,
        effective_on VARCHAR(20) DEFAULT '', level VARCHAR(20) DEFAULT '', ref_id INT DEFAULT 0,
        scope_note VARCHAR(300) DEFAULT '', active INT DEFAULT 1, is_system INT DEFAULT 0,
        set_by VARCHAR(120) DEFAULT '', set_at VARCHAR(30) DEFAULT '')");

    if (function_exists('setting_get') && !setting_get('service_catalog_seeded_v1', '')) {
        try { services_seed_catalog(); } catch (Throwable $e) { /* never break login/migrate */ }
        if (function_exists('setting_set')) setting_set('service_catalog_seeded_v1', '1');
    } else {
        // Keep the catalog in step if a new service code was added to the const
        // in a later release (adds only; never overwrites an admin's edits).
        try { services_seed_catalog(true); } catch (Throwable $e) {}
    }
}

function services_seed_catalog($addOnly = false) {
    $pdo = db(); $now = date('c'); $so = 0;
    $ins = $pdo->prepare("INSERT INTO service_catalog
        (code,name,description,module_key,permission_key,route,nav_group,icon,globally_active,default_scope_active,is_system,sort_order,updated_at)
        VALUES (?,?,?,?,?,?,?,?,1,1,1,?,?)");
    foreach (SERVICE_CATALOG as $code => $c) {
        $so += 10;
        if ((int)ops_val("SELECT COUNT(*) FROM service_catalog WHERE code=?", [$code]) > 0) continue;
        $ins->execute([$code, $c[0], $c[6] ?? '', $c[1] ?? '', $c[2] ?? '', $c[3] ?? '', $c[4] ?? '', $c[5] ?? '', $so, $now]);
    }
}

// ---------------------------------------------------------------------------
//  Reads.
// ---------------------------------------------------------------------------
function svc_catalog($activeOnly = false) {
    $rows = ops_all("SELECT * FROM service_catalog " . ($activeOnly ? "WHERE globally_active=1 " : "") . "ORDER BY sort_order, name");
    return $rows ?: [];
}
function svc_row($code) { return ops_one("SELECT * FROM service_catalog WHERE code=?", [(string)$code]) ?: null; }
function svc_name($code) { $r = svc_row($code); return $r ? (string)$r['name'] : (string)$code; }

// Is this service OFFERED on this installation at all? Composes with the licence:
// globally_active in the catalog AND (no licence module, or that module enabled).
// This is what the menu asks. Fail-open: if the catalog row is missing entirely
// (e.g. an older DB before this engine), the service is treated as available so
// nothing that worked yesterday disappears.
function svc_globally_active($code) {
    $r = svc_row($code);
    if (!$r) return true;
    if ((int)$r['globally_active'] !== 1) return false;
    $mod = trim((string)$r['module_key']);
    if ($mod !== '' && function_exists('licence_enabled') && !licence_enabled($mod)) return false;
    return true;
}

// The per-scope activation for a service in a context. Resolution is
// MOST-SPECIFIC-FIRST: the first level present in $ctx that has an explicit
// service_scope row decides. If none is set at any level, the catalog default
// applies. A globally-inactive service is never in scope. Returns a bool.
// $ctx keys: client_id, contract_id, project_id, po_id, assignment_id (any subset).
function svc_active($code, $ctx = []) {
    return svc_resolve($code, $ctx)['active'];
}

// Full resolution detail — ['active'=>bool, 'source'=>level|'default'|'global-off', 'row'=>?].
function svc_resolve($code, $ctx = []) {
    if (!svc_globally_active($code)) return ['active' => false, 'source' => 'global-off', 'row' => null];
    foreach (SERVICE_SCOPE_LEVELS as $level => [$label, $key]) {
        $refId = (int)($ctx[$key] ?? 0);
        if ($refId <= 0) continue;
        $row = ops_one("SELECT * FROM service_scope WHERE level=? AND ref_id=? AND service_code=?", [$level, $refId, (string)$code]);
        if ($row) return ['active' => (int)$row['active'] === 1, 'source' => $level, 'row' => $row];
    }
    $r = svc_row($code);
    $def = $r ? ((int)$r['default_scope_active'] === 1) : true;
    return ['active' => $def, 'source' => 'default', 'row' => null];
}

// The whole active-service map for a context — code => bool. Handy for a
// dashboard, a report filter or a permission check that wants to adapt at once.
function svc_scope_map($ctx = []) {
    $out = [];
    foreach (svc_catalog() as $c) $out[$c['code']] = svc_active($c['code'], $ctx);
    return $out;
}
// Just the active service codes for a context.
function svc_active_codes($ctx = []) {
    $out = []; foreach (svc_scope_map($ctx) as $code => $on) if ($on) $out[] = $code; return $out;
}

// ---------------------------------------------------------------------------
//  Writes (used by the admin screen and by any module that wants to set scope
//  programmatically when a contract / PO is created).
// ---------------------------------------------------------------------------
function svc_set_global($code, $active) {
    if (!svc_row($code)) return;
    db()->prepare("UPDATE service_catalog SET globally_active=?, updated_at=? WHERE code=?")
        ->execute([$active ? 1 : 0, date('c'), (string)$code]);
}
function svc_set_default_scope($code, $active) {
    if (!svc_row($code)) return;
    db()->prepare("UPDATE service_catalog SET default_scope_active=?, updated_at=? WHERE code=?")
        ->execute([$active ? 1 : 0, date('c'), (string)$code]);
}
// Set (or clear) a per-scope override. Passing $active === null REMOVES the
// override so the entity falls back to the next level / catalog default.
function svc_set_scope($level, $refId, $code, $active, $note = '') {
    $level = strtoupper((string)$level); $refId = (int)$refId; $code = (string)$code;
    if (!isset(SERVICE_SCOPE_LEVELS[$level]) || $refId <= 0 || !svc_row($code)) return;
    $who = function_exists('current_user') ? (string)(current_user()['username'] ?? 'system') : 'system';
    if ($active === null) {
        db()->prepare("DELETE FROM service_scope WHERE level=? AND ref_id=? AND service_code=?")->execute([$level, $refId, $code]);
        return;
    }
    $exists = ops_one("SELECT id FROM service_scope WHERE level=? AND ref_id=? AND service_code=?", [$level, $refId, $code]);
    if ($exists) db()->prepare("UPDATE service_scope SET active=?, note=?, set_by=?, set_at=? WHERE id=?")
        ->execute([$active ? 1 : 0, (string)$note, $who, date('c'), (int)$exists['id']]);
    else db()->prepare("INSERT INTO service_scope (level,ref_id,service_code,active,note,set_by,set_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([$level, $refId, $code, $active ? 1 : 0, (string)$note, $who, date('c')]);
}
// Every explicit override for one entity (for the admin screen).
function svc_scope_overrides($level, $refId) {
    $rows = ops_all("SELECT * FROM service_scope WHERE level=? AND ref_id=? ORDER BY service_code", [strtoupper((string)$level), (int)$refId]);
    $by = []; foreach ($rows as $r) $by[$r['service_code']] = $r; return $by;
}

// ---------------------------------------------------------------------------
//  Optional configurable dependencies. Returns the CONFIGURED prerequisites for
//  a service in a context (most specific scope wins; ref_id=0 rows are global).
//  Empty => none configured => NO forced workflow (the default everywhere).
// ---------------------------------------------------------------------------
function svc_dependencies($code, $ctx = []) {
    $code = (string)$code;
    $all = ops_all("SELECT * FROM service_dependencies WHERE service_code=? AND active=1", [$code]) ?: [];
    if (!$all) return [];
    // Prefer the most specific scoped rule present in the context; else the
    // global (ref_id=0) rules.
    foreach (SERVICE_SCOPE_LEVELS as $level => [$label, $key]) {
        $refId = (int)($ctx[$key] ?? 0);
        if ($refId <= 0) continue;
        $scoped = array_values(array_filter($all, fn($d) => strtoupper((string)$d['level']) === $level && (int)$d['ref_id'] === $refId));
        if ($scoped) return $scoped;
    }
    return array_values(array_filter($all, fn($d) => (int)$d['ref_id'] === 0));
}
// Given which services are considered SATISFIED (e.g. a completed inspection),
// return the configured prerequisites that are NOT yet met. Advisory input to a
// module that chooses to honour a client's configured dependency — the engine
// itself imposes nothing.
function svc_unmet_dependencies($code, $ctx, $satisfiedCodes = []) {
    $sat = array_map('strtoupper', (array)$satisfiedCodes);
    $out = [];
    foreach (svc_dependencies($code, $ctx) as $d) {
        $req = strtoupper((string)$d['requires_code']);
        if ($req === '' || in_array($req, $sat, true)) continue;
        // A prerequisite that is itself not in scope cannot block anything.
        if (!svc_active($d['requires_code'], $ctx)) continue;
        $out[] = $d;
    }
    return $out;
}
function svc_set_dependency($serviceCode, $requiresCode, $opts = []) {
    $serviceCode = (string)$serviceCode; $requiresCode = (string)$requiresCode;
    if ($serviceCode === '' || $requiresCode === '' || $serviceCode === $requiresCode) return;
    $level = strtoupper((string)($opts['level'] ?? '')); $refId = (int)($opts['ref_id'] ?? 0);
    $who = function_exists('current_user') ? (string)(current_user()['username'] ?? 'system') : 'system';
    $exists = ops_one("SELECT id FROM service_dependencies WHERE service_code=? AND requires_code=? AND level=? AND ref_id=?",
        [$serviceCode, $requiresCode, $level, $refId]);
    if ($exists) db()->prepare("UPDATE service_dependencies SET condition_note=?, requires_approval=?, effective_on=?, scope_note=?, active=?, set_by=?, set_at=? WHERE id=?")
        ->execute([(string)($opts['condition'] ?? ''), !empty($opts['requires_approval']) ? 1 : 0, (string)($opts['effective_on'] ?? ''),
                   (string)($opts['scope_note'] ?? ''), isset($opts['active']) ? ((int)$opts['active']) : 1, $who, date('c'), (int)$exists['id']]);
    else db()->prepare("INSERT INTO service_dependencies (service_code,requires_code,condition_note,requires_approval,effective_on,level,ref_id,scope_note,active,set_by,set_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$serviceCode, $requiresCode, (string)($opts['condition'] ?? ''), !empty($opts['requires_approval']) ? 1 : 0,
                   (string)($opts['effective_on'] ?? ''), $level, $refId, (string)($opts['scope_note'] ?? ''),
                   isset($opts['active']) ? ((int)$opts['active']) : 1, $who, date('c')]);
}
function svc_delete_dependency($id) { db()->prepare("DELETE FROM service_dependencies WHERE id=?")->execute([(int)$id]); }
function svc_all_dependencies() { return ops_all("SELECT * FROM service_dependencies ORDER BY ref_id, service_code, requires_code") ?: []; }

// ---------------------------------------------------------------------------
//  Admin screen — Settings → Service scope. Global catalog toggles + per-client
//  overrides + configurable dependencies. Client is the level the spec calls the
//  "Client default"; the finer levels are set via the same generic API by the
//  module that owns a contract / PO / assignment.
// ---------------------------------------------------------------------------
function svc_can_manage() { return function_exists('can') && (can('settings.manage') || can('mod.settings.view')) || (function_exists('is_master') && is_master()); }

function ops_services($route, $method) {
    ops_require(svc_can_manage(), 'Only an administrator can configure the service scope.');
    services_migrate();

    if ($route === 'service-scope' && $method === 'POST') {
        if (!csrf_ok($_POST['_csrf'] ?? '')) { flash('That form had expired — please try again.', 'error'); redirect('/service-scope'); }
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'global') {
            foreach (svc_catalog() as $c) {
                svc_set_global($c['code'], isset($_POST['g_' . $c['code']]));
                svc_set_default_scope($c['code'], isset($_POST['d_' . $c['code']]));
            }
            flash('Service catalog updated. Menus and workflows now follow the active services.');
        } elseif ($act === 'client') {
            $cid = (int)($_POST['client_id'] ?? 0);
            if ($cid > 0) {
                foreach (svc_catalog() as $c) {
                    $mode = (string)($_POST['s_' . $c['code']] ?? 'default'); // default | on | off
                    if ($mode === 'on')       svc_set_scope('CLIENT', $cid, $c['code'], true);
                    elseif ($mode === 'off')  svc_set_scope('CLIENT', $cid, $c['code'], false);
                    else                      svc_set_scope('CLIENT', $cid, $c['code'], null); // clear → default
                }
                flash('Service scope saved for the client. The most specific active setting always wins.');
            }
            redirect('/service-scope?client_id=' . $cid);
        } elseif ($act === 'dep-add') {
            svc_set_dependency($_POST['dep_service'] ?? '', $_POST['dep_requires'] ?? '', [
                'condition' => $_POST['dep_condition'] ?? '', 'requires_approval' => isset($_POST['dep_approval']),
                'effective_on' => $_POST['dep_effective'] ?? '',
                'level' => $_POST['dep_level'] ?? '', 'ref_id' => (int)($_POST['dep_ref'] ?? 0),
                'scope_note' => $_POST['dep_scope_note'] ?? '',
            ]);
            flash('Dependency saved. It applies only where configured; everywhere else the services stay independent.');
        } elseif ($act === 'dep-del') {
            svc_delete_dependency((int)($_POST['dep_id'] ?? 0));
            flash('Dependency removed.');
        }
        redirect('/service-scope');
    }

    $clientId = (int)($_GET['client_id'] ?? 0);
    // Reuse the app's own naming convention: display_name, falling back to legal_name.
    $nameOf = fn($r) => $r ? (string)(($r['display_name'] ?? '') !== '' ? $r['display_name'] : ($r['legal_name'] ?? '')) : '';
    $client = null;
    if ($clientId > 0) {
        $cr = ops_one("SELECT id, display_name, legal_name FROM business_partners WHERE id=?", [$clientId]);
        if ($cr) $client = ['id' => (int)$cr['id'], 'name' => $nameOf($cr)];
    }
    $rawClients = function_exists('clients_list') ? (clients_list() ?: [])
        : (ops_all("SELECT id, display_name, legal_name FROM business_partners WHERE is_client=1 ORDER BY legal_name") ?: []);
    $clients = [];
    foreach ($rawClients as $r) $clients[] = ['id' => (int)$r['id'], 'name' => $nameOf($r)];
    view('ops/service_scope', [
        'catalog'    => svc_catalog(),
        'clients'    => $clients,
        'client'     => $client,
        'overrides'  => $client ? svc_scope_overrides('CLIENT', $clientId) : [],
        'deps'       => svc_all_dependencies(),
        'levels'     => SERVICE_SCOPE_LEVELS,
    ]);
    return true;
}
