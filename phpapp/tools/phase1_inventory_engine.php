<?php
// ============================================================================
//  PHASE 1 · STEP 1 — READ-ONLY TENANT ENTITLEMENT INVENTORY (ENGINE)
//
//  PURPOSE
//  Report, for every hosted workspace, what it is entitled to TODAY and what it
//  would be entitled to IF the entitlement ceiling were changed to default-deny.
//  It exists so that decision can be made against real data instead of a guess.
//
//  THIS FILE CHANGES NOTHING. It is NOT required by index.php and is NOT wired
//  into the application. It is loaded only by the standalone runner
//  (phase1-inventory.php) and by tests/test_phase1_inventory.php.
//
//  ── WHY THIS LIVES IN tools/ AND NOT IN lib/ ───────────────────────────────
//  index.php builds a code fingerprint by globbing lib/*.php (index.php:379).
//  Adding ANY file to lib/ changes that fingerprint, which makes the next page
//  load run migrate_all() -> run_schema() -> saas_entitlement_ensure(), which
//  WRITES saas_entitled_modules onto provisioned workspaces with a blank
//  ceiling. Merely deploying this tool inside lib/ would therefore have altered
//  the entitlement data before it could be measured. tools/ is not globbed, so
//  deploying this file is fingerprint-neutral and measurement-safe.
//
//  ── THE CRITICAL SAFETY RULE ───────────────────────────────────────────────
//  The application's own boot chain WRITES entitlement: saas_entitlement_ensure()
//  runs at lib/db.php:523 and stamps saas_entitled_modules on any provisioned
//  tenant that has none. Booting the app, or calling saas_enter_tenant(), would
//  therefore MUTATE the very state this tool is trying to observe — and write to
//  production.
//
//  So this tool NEVER boots the application and NEVER enters a tenant. It opens
//  each database with a raw PDO connection and issues SELECTs only. Every query
//  goes through p1_ro_query(), which hard-refuses any statement that is not a
//  read. A write cannot be issued by this file even by mistake.
//
//  Safe to execute against production.
// ============================================================================

// ---- The read-only guard ---------------------------------------------------
// Refuses anything that is not a read. This is the tool's safety interlock, and
// it is asserted by the test suite.
function p1_is_read_only_sql($sql) {
    $s = ltrim((string) $sql);
    // strip leading SQL comments / whitespace so a write cannot hide behind one
    while ($s !== '') {
        if (strncmp($s, '--', 2) === 0)      { $p = strpos($s, "\n"); $s = $p === false ? '' : ltrim(substr($s, $p + 1)); continue; }
        if (strncmp($s, '/*', 2) === 0)      { $p = strpos($s, '*/'); $s = $p === false ? '' : ltrim(substr($s, $p + 2)); continue; }
        break;
    }
    if ($s === '') return false;
    if (strpos($s, ';') !== false) {                       // no statement stacking
        $tail = trim(substr($s, strpos($s, ';') + 1));
        if ($tail !== '') return false;
    }
    return (bool) preg_match('/^(SELECT|SHOW|PRAGMA)\b/i', $s);
}

function p1_ro_query(PDO $pdo, $sql, array $params = []) {
    if (!p1_is_read_only_sql($sql))
        throw new RuntimeException('phase1-inventory is READ-ONLY; refused: ' . substr(trim((string) $sql), 0, 60));
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st;
}

// ---- Module catalogue ------------------------------------------------------
// Non-core, sellable module keys, and the core keys that are always on.
function p1_module_catalogue() {
    $core = []; $sellable = []; $labels = [];
    if (defined('PRODUCT_MODULES')) {
        foreach (PRODUCT_MODULES as $k => $m) {
            $labels[$k] = (string) ($m[0] ?? $k);
            if (!empty($m[3])) $core[] = $k; else $sellable[] = $k;
        }
    }
    return ['core' => $core, 'sellable' => $sellable, 'labels' => $labels];
}

// Parse a CSV module list, keeping only valid NON-CORE keys — exactly the rule
// licence_entitled_ceiling() and licence_disabled() apply.
function p1_parse_modules($csv) {
    $cat = p1_module_catalogue();
    $out = [];
    foreach (explode(',', strtolower((string) $csv)) as $k) {
        $k = trim($k);
        if ($k !== '' && in_array($k, $cat['sellable'], true)) $out[] = $k;
    }
    return array_values(array_unique($out));
}

// ---- Effective entitlement, mirroring lib/licence.php ----------------------
// $ceilRaw = saas_entitled_modules ; $offRaw = modules_off.
// $denyDefault=false reproduces TODAY (blank ceiling ⇒ no limit ⇒ allow all).
// $denyDefault=true  models the PROPOSAL (blank ceiling ⇒ deny all non-core).
function p1_effective_modules($ceilRaw, $offRaw, $denyDefault = false) {
    $cat  = p1_module_catalogue();
    $blank = trim((string) $ceilRaw) === '';
    $ceil = $blank ? ($denyDefault ? [] : null) : p1_parse_modules($ceilRaw);

    $off = p1_parse_modules($offRaw);                       // the tenant's own choices
    if ($ceil !== null) {                                   // the ceiling forces the rest off
        foreach ($cat['sellable'] as $k) if (!in_array($k, $ceil, true)) $off[] = $k;
    }
    $off = array_values(array_unique($off));

    $on = $cat['core'];                                     // core is always on
    foreach ($cat['sellable'] as $k) if (!in_array($k, $off, true)) $on[] = $k;
    sort($on);
    return $on;
}

// ---- Lockout risk classification ------------------------------------------
// Returns ['risk'=>..., 'severity'=>..., 'note'=>...]. Never guesses: a tenant
// whose correct entitlement cannot be determined is reported as AMBIGUOUS.
function p1_classify(array $t) {
    $lost = (array) ($t['would_lose'] ?? []);
    if (!empty($t['error'])) {
        // A company that has been created but never signed in to has no database
        // yet — that is lazy provisioning working as designed, not a fault. Only
        // a workspace the control database records as already set up
        // (provisioned_at) is genuinely broken when its data is missing.
        // Reporting a brand-new company in red as an ERROR sends the operator
        // looking for a problem that does not exist.
        if (trim((string) ($t['control_provisioned_at'] ?? '')) === '')
            return ['risk' => 'NOT_YET_OPENED', 'severity' => 'NONE',
                    'note' => 'Created but never signed in to, so its database does not exist yet. '
                            . 'It is built the first time its owner signs in. Nothing is wrong; '
                            . 'entitlement cannot be measured until then.'];
        return ['risk' => 'ERROR', 'severity' => 'UNKNOWN',
                'note' => 'Workspace database could not be read: ' . $t['error'] . ' — entitlement unknown.'];
    }
    if (!empty($t['licence_key']))
        return ['risk' => 'LICENCE_GOVERNED', 'severity' => 'NONE',
                'note' => 'A signed licence is present and outranks the cloud ceiling; default-deny does not apply.'];
    if (!$lost)
        return ['risk' => 'SAFE', 'severity' => 'NONE',
                'note' => 'Default-deny changes nothing for this workspace.'];
    if (!empty($t['control_modules']))
        return ['risk' => 'RECOVERABLE', 'severity' => 'HIGH',
                'note' => 'Would lose modules, BUT the control database records what was bought — a backfill source exists.'];
    return ['risk' => 'AMBIGUOUS', 'severity' => 'CRITICAL',
            'note' => 'Would lose modules and NEITHER store records an entitlement. Correct value cannot be determined — STOP, do not guess.'];
}

// ---- Resolve a workspace's database from its stored routing ----------------
// Accepts the control row's route_json, else the registry entry. Returns
// ['kind'=>'mysql'|'sqlite'|'none', 'dsn'=>, 'user'=>, 'pass'=>, 'label'=>].
function p1_resolve_route($routeJson, $registryEntry = null) {
    $r = is_string($routeJson) && $routeJson !== '' ? json_decode($routeJson, true) : null;
    if (!is_array($r) || (empty($r['sqlite']) && empty($r['name']))) {
        $e = is_array($registryEntry) ? $registryEntry : [];
        if (!empty($e['sqlite'])) $r = ['sqlite' => $e['sqlite']];
        elseif (!empty($e['db']) && is_array($e['db'])) $r = $e['db'];
        else return ['kind' => 'none', 'label' => 'not wired up'];
    }
    if (!empty($r['sqlite']))
        // 'path' is carried so the caller can verify the file EXISTS before
        // connecting: PDO would otherwise CREATE an empty SQLite file, which
        // would be a write, and this tool must write nothing at all.
        return ['kind' => 'sqlite', 'dsn' => 'sqlite:' . $r['sqlite'], 'user' => null, 'pass' => null,
                'path' => (string) $r['sqlite'],
                'label' => 'file: ' . basename((string) $r['sqlite'])];
    $host = (string) ($r['host'] ?? 'localhost') ?: 'localhost';
    $name = (string) ($r['name'] ?? '');
    if ($name === '') return ['kind' => 'none', 'label' => 'not wired up'];
    return ['kind' => 'mysql', 'dsn' => "mysql:host={$host};dbname={$name};charset=utf8mb4",
            'user' => (string) ($r['user'] ?? ''), 'pass' => (string) ($r['pass'] ?? ''),
            'label' => 'mysql: ' . $name . ' @ ' . $host];   // credentials deliberately NOT exposed
}

// ---- Read the entitlement-relevant settings from one workspace database ----
// SELECT only. Returns a map of setting => value (missing keys come back '').
function p1_read_settings(PDO $pdo) {
    $keys = ['saas_provisioned', 'saas_entitled_modules', 'modules_off', 'licence_key',
             'marketplace_addon', 'connect_enabled', 'saas_paid_modules', 'product_package', 'app_name'];
    $out = array_fill_keys($keys, '');
    $in  = implode(',', array_fill(0, count($keys), '?'));
    $st  = p1_ro_query($pdo, "SELECT skey, svalue FROM settings WHERE skey IN ($in)", $keys);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row)
        $out[(string) $row['skey']] = (string) $row['svalue'];
    return $out;
}

// ---- Build one workspace's inventory row ----------------------------------
function p1_build_row(array $control, array $settings, $routeLabel, $error = '') {
    $ceil = (string) ($settings['saas_entitled_modules'] ?? '');
    $off  = (string) ($settings['modules_off'] ?? '');

    $now  = $error ? [] : p1_effective_modules($ceil, $off, false);
    $deny = $error ? [] : p1_effective_modules($ceil, $off, true);
    $lose = array_values(array_diff($now, $deny));
    sort($lose);

    $ctrlMods = [];
    $em = json_decode((string) ($control['enabled_modules'] ?? '[]'), true);
    if (is_array($em)) $ctrlMods = array_values(array_filter(array_map('strval', $em)));

    $row = [
        'tenant'            => (string) ($control['tenant_key'] ?? ''),
        'company'           => (string) ($control['company'] ?? ''),
        'status'            => (string) ($control['status'] ?? ''),
        'plan'              => (string) ($control['plan'] ?? ''),
        'plan_expiry'       => (string) ($control['plan_expiry'] ?? ''),
        'control_modules'   => $ctrlMods,
        'storage'           => $routeLabel,
        'provisioned'       => (string) ($settings['saas_provisioned'] ?? ''),
        'ceiling_raw'       => $ceil,
        'ceiling_blank'     => trim($ceil) === '',
        'modules_off_raw'   => $off,
        'licence_key'       => ((string) ($settings['licence_key'] ?? '')) !== '' ? 'present' : '',
        'paid_modules'      => (string) ($settings['saas_paid_modules'] ?? ''),
        'effective_now'     => $now,
        'effective_deny'    => $deny,
        'would_lose'        => $lose,
        'marketplace_addon' => (string) ($settings['marketplace_addon'] ?? ''),
        'connect_enabled'   => (string) ($settings['connect_enabled'] ?? ''),
        'error'             => (string) $error,
        // From the CONTROL database, which survives everything: when this
        // workspace was first fully set up. Blank means it has never been opened,
        // and that is what separates "new" from "broken".
        'control_provisioned_at' => (string) ($control['provisioned_at'] ?? ''),
    ];
    $row += p1_classify($row);

    // Marketplace is NOT in PRODUCT_MODULES, so the entitlement ceiling does not
    // govern it today. Three DIFFERENT things must be kept apart here, because
    // conflating them would manufacture an entitlement nobody bought:
    //   purchased      — Marketplace appears in the control record of what was bought
    //   explicitly on  — an administrator deliberately set marketplace_addon = '1'
    //   on by default  — the setting was never touched; cloud installs default to ON
    // Only the first two justify preserving access when Marketplace becomes a
    // real module. "On by default" is an artefact of the default, NOT a purchase.
    $mkRaw = $row['marketplace_addon'];
    $cxRaw = $row['connect_enabled'];
    $purchased   = in_array('marketplace', array_map('strtolower', $ctrlMods), true);
    $explicitOn  = ($mkRaw === '1');
    $explicitOff = ($mkRaw === '0' || $cxRaw === '0');
    $row['marketplace_purchased']    = $purchased;
    $row['marketplace_explicit_on']  = $explicitOn;
    $row['marketplace_currently_on'] = !$explicitOff;          // default is ON on cloud
    $row['marketplace_default_only'] = (!$purchased && !$explicitOn && !$explicitOff);

    $mkOn = $mkRaw === '' ? 'never set (default)' : ($mkRaw === '1' ? 'explicitly ON' : 'explicitly off');
    $cxOn = $cxRaw === '' ? 'never set (default ON)' : ($cxRaw === '1' ? 'ON' : 'off');
    $row['marketplace_state'] = 'addon=' . $mkOn . ' · connect=' . $cxOn
        . ' · purchased=' . ($purchased ? 'YES' : 'NO');

    // Backfill ONLY what was bought or deliberately switched on.
    $row['marketplace_needs_backfill'] = ($purchased || $explicitOn);
    $row['marketplace_note'] = $purchased
        ? 'Marketplace is in the control record of purchased modules — preserve it when Marketplace becomes a module.'
        : ($explicitOn
            ? 'Not in the purchased record, but an administrator explicitly switched it ON — confirm commercially before removing.'
            : 'Reachable only because cloud installs default to ON. NOT purchased and NOT configured — promoting Marketplace to a real module would correctly lock it. No backfill.');
    return $row;
}

// ---- Summary ---------------------------------------------------------------
function p1_summarise(array $rows) {
    $s = ['total' => count($rows), 'SAFE' => 0, 'RECOVERABLE' => 0, 'AMBIGUOUS' => 0,
          'ERROR' => 0, 'LICENCE_GOVERNED' => 0, 'NOT_YET_OPENED' => 0, 'not_provisioned' => 0,
          'blank_ceiling' => 0, 'marketplace_backfill' => 0];
    foreach ($rows as $r) {
        $k = (string) ($r['risk'] ?? 'ERROR');
        if (isset($s[$k])) $s[$k]++;
        if (($r['provisioned'] ?? '') !== '1') $s['not_provisioned']++;
        if (!empty($r['ceiling_blank']))       $s['blank_ceiling']++;
        if (!empty($r['marketplace_needs_backfill'])) $s['marketplace_backfill']++;
    }
    // A workspace nobody has opened cannot be measured, but it also cannot be
    // harmed: it has no entitlement state yet, and it will be created correctly.
    // It is neither a blocker nor a clean bill of health, so it is reported
    // separately rather than folded into either.
    $s['measurable']   = $s['total'] - $s['NOT_YET_OPENED'];
    $s['safe_to_flip'] = ($s['AMBIGUOUS'] === 0 && $s['ERROR'] === 0 && $s['measurable'] > 0);
    return $s;
}

// ---- Rendering -------------------------------------------------------------
function p1_render_text(array $report) {
    $L = [];
    $sum = $report['summary'];
    $L[] = 'EXAACT — PHASE 1 STEP 1 · READ-ONLY ENTITLEMENT INVENTORY';
    $L[] = 'Generated: ' . $report['generated_at'] . '   (no data was written)';
    $L[] = str_repeat('=', 78);
    $L[] = 'Workspaces: ' . $sum['total']
         . '   SAFE: ' . $sum['SAFE'] . '   RECOVERABLE: ' . $sum['RECOVERABLE']
         . '   AMBIGUOUS: ' . $sum['AMBIGUOUS'] . '   ERROR: ' . $sum['ERROR']
         . '   LICENCE: ' . $sum['LICENCE_GOVERNED']
         . '   NEVER OPENED: ' . $sum['NOT_YET_OPENED'];
    $L[] = 'Blank ceiling: ' . $sum['blank_ceiling']
         . '   Not provisioned: ' . $sum['not_provisioned']
         . '   Marketplace needing backfill: ' . $sum['marketplace_backfill'];
    if ($sum['safe_to_flip'])
        $L[] = 'VERDICT: every workspace that can be measured has a determinable entitlement.';
    elseif ($sum['measurable'] === 0)
        $L[] = 'VERDICT: NOTHING TO MEASURE YET — no workspace has been opened, so none has an '
             . 'entitlement to read. Sign in to one, then run this again.';
    else
        $L[] = 'VERDICT: DO NOT FLIP DEFAULT-DENY — ' . $sum['AMBIGUOUS'] . ' ambiguous, '
             . $sum['ERROR'] . ' unreadable.';
    $L[] = str_repeat('=', 78);
    foreach ($report['tenants'] as $r) {
        $L[] = '';
        $L[] = '[' . $r['risk'] . '] ' . $r['tenant'] . '  —  ' . $r['company'];
        $L[] = '  status/plan      : ' . $r['status'] . ' / ' . $r['plan'] . ($r['plan_expiry'] ? ' (to ' . $r['plan_expiry'] . ')' : '');
        $L[] = '  storage          : ' . $r['storage'];
        if ($r['error']) {
            $L[] = ($r['risk'] === 'NOT_YET_OPENED' ? '  NOT OPENED YET   : ' : '  ERROR            : ')
                 . ($r['risk'] === 'NOT_YET_OPENED'
                     ? 'no database yet — it is built the first time its owner signs in. Nothing is wrong.'
                     : $r['error']);
            $L[] = '  control bought   : ' . ($r['control_modules'] ? implode(',', $r['control_modules']) : '(none recorded)');
            continue;
        }
        $L[] = '  control bought   : ' . ($r['control_modules'] ? implode(',', $r['control_modules']) : '(none recorded)');
        $L[] = '  provisioned      : ' . ($r['provisioned'] !== '' ? $r['provisioned'] : '(not set)');
        $L[] = '  ceiling          : ' . ($r['ceiling_blank'] ? '(BLANK — allows everything today)' : $r['ceiling_raw']);
        $L[] = '  modules_off      : ' . ($r['modules_off_raw'] !== '' ? $r['modules_off_raw'] : '(none)');
        if ($r['licence_key']) $L[] = '  signed licence   : present (outranks the ceiling)';
        $L[] = '  effective NOW    : ' . implode(',', $r['effective_now']);
        $L[] = '  effective DENY   : ' . implode(',', $r['effective_deny']);
        $L[] = '  WOULD LOSE       : ' . ($r['would_lose'] ? implode(',', $r['would_lose']) : '(nothing)');
        $L[] = '  marketplace      : ' . $r['marketplace_state'] . ($r['marketplace_needs_backfill'] ? '  [needs backfill when promoted]' : '');
        $L[] = '  -> ' . $r['note'];
    }
    $L[] = '';
    $L[] = str_repeat('=', 78);
    $L[] = 'END OF REPORT — nothing was written to any database.';
    return implode("\n", $L) . "\n";
}

// ============================================================================
//  STEP 2A — STORAGE RESOLUTION PROBE  (read-only)
//
//  The inventory reported a workspace whose data file could not be found. This
//  probe answers the only question that matters: is the file MISSING, or is it
//  simply somewhere other than where the routing says?
//
//  It matters because the APPLICATION and this TOOL read routing from different
//  places. config.php resolves a workspace ONLY from tenants.php (the routing
//  file). This tool prefers saas_tenants.route_json in the control database and
//  falls back to the routing file. If the two ever disagree, the application and
//  the tool would open different databases.
//
//  Lists filenames and sizes only. Opens nothing, creates nothing, writes nothing.
// ============================================================================

// The places a workspace data file could legitimately live, newest convention first.
function p1_candidate_dirs($appDir) {
    $appDir = rtrim((string) $appDir, '/');
    return [
        'above web root (exaact_data)' => dirname($appDir) . '/exaact_data',
        'app folder /data'             => $appDir . '/data',
        'app folder root (legacy)'     => $appDir,
    ];
}

// Filenames + sizes of workspace data files in a directory. Never opens them.
function p1_scan_sqlite($dir) {
    $out = ['exists' => is_dir($dir), 'readable' => is_dir($dir) && is_readable($dir), 'files' => []];
    if (!$out['readable']) return $out;
    foreach (glob(rtrim($dir, '/') . '/tenant-*.sqlite') ?: [] as $f)
        $out['files'][] = ['name' => basename($f), 'bytes' => (int) @filesize($f)];
    return $out;
}

// Extract just the storage path from a routing value, with credentials stripped.
function p1_route_path($route) {
    $r = is_string($route) ? json_decode($route, true) : $route;
    if (!is_array($r)) return ['kind' => 'none', 'path' => '', 'label' => '(nothing recorded)'];
    if (!empty($r['sqlite'])) return ['kind' => 'sqlite', 'path' => (string) $r['sqlite'], 'label' => (string) $r['sqlite']];
    if (!empty($r['db']) && is_array($r['db'])) $r = $r['db'];
    if (!empty($r['name']))
        return ['kind' => 'mysql', 'path' => '',
                'label' => 'mysql: ' . $r['name'] . ' @ ' . (string) ($r['host'] ?? 'localhost')];
    return ['kind' => 'none', 'path' => '', 'label' => '(nothing recorded)'];
}

// Compare the two routing sources for one workspace and locate the real file.
function p1_probe_tenant($key, $routeJson, $registryEntry, $appDir, $company = '') {
    $ctl = p1_route_path($routeJson);
    $regRoute = null;
    if (is_array($registryEntry)) {
        if (!empty($registryEntry['sqlite'])) $regRoute = ['sqlite' => $registryEntry['sqlite']];
        elseif (!empty($registryEntry['db']))  $regRoute = $registryEntry['db'];
    }
    $reg = p1_route_path($regRoute);

    $row = [
        'tenant'          => (string) $key,
        'control_route'   => $ctl['label'],
        'registry_route'  => $reg['label'],
        // null = the routing file records nothing for this workspace, so there is
        // nothing to disagree WITH. Only a real, populated difference is a conflict.
        'sources_agree'   => ($reg['kind'] === 'none' || $ctl['kind'] === 'none')
                              ? null : ($ctl['label'] === $reg['label']),
        'registry_has_entry' => ($reg['kind'] !== 'none'),
        'app_would_use'   => $reg['label'],     // config.php reads tenants.php ONLY
        'tool_used'       => ($ctl['kind'] !== 'none' ? $ctl['label'] : $reg['label']),
        'control_exists'  => ($ctl['kind'] === 'sqlite') ? is_file($ctl['path']) : null,
        'registry_exists' => ($reg['kind'] === 'sqlite') ? is_file($reg['path']) : null,
        'found_elsewhere' => [],
    ];
    // STEP 2B — full filesystem facts for each routed path.
    $row['control_facts']  = ($ctl['kind'] === 'sqlite') ? p1_path_facts($ctl['path']) : null;
    $row['registry_facts'] = ($reg['kind'] === 'sqlite') ? p1_path_facts($reg['path']) : null;

    // STEP 2B — is MySQL/MariaDB storage configured for this workspace?
    $row['mysql'] = p1_mysql_storage_check($routeJson, $regRoute);

    // STEP 2B — every candidate file for this workspace, including near-miss
    // filename variants, each identity-verified read-only.
    $cands = p1_find_candidates($key, p1_search_dirs($appDir));
    foreach ($cands as &$c)
        $c['identity'] = $c['is_sqlite']
            ? p1_identify_db($c['path'], $key, (string) $company)
            : ['verdict' => 'NOT_A_DATABASE', 'error' => 'header mismatch'];
    unset($c);
    $row['candidates'] = $cands;
    $row['found_elsewhere'] = array_map(
        fn($c) => ['where' => $c['where'], 'path' => $c['path'], 'bytes' => $c['bytes']], $cands);

    // Classification for this workspace alone (H1/H2/H3/H4).
    $credible = array_values(array_filter($cands, fn($c) =>
        in_array(($c['identity']['verdict'] ?? ''), ['MATCHES_EXPECTED_WORKSPACE', 'EXAACT_WORKSPACE_UNNAMED'], true)));
    $routedExists = ($row['control_facts']['exists'] ?? false) || ($row['registry_facts']['exists'] ?? false);

    if ($row['mysql']['configured'])  $row['classification'] = 'H4 - MySQL/MariaDB storage is configured';
    elseif (count($credible) > 1)     $row['classification'] = 'H3 - MULTIPLE credible databases (blocks repair)';
    elseif (count($credible) === 1)   $row['classification'] = $routedExists
                                       ? 'OK - routed database exists'
                                       : 'H2 - database exists at a DIFFERENT location';
    elseif ($cands)                   $row['classification'] = 'H3 - file(s) present but none verified as this workspace';
    else                              $row['classification'] = 'H1 - no database anywhere (never provisioned)';
    return $row;
}

function p1_render_storage_text(array $probe) {
    $L = [];
    $L[] = 'EXAACT — PHASE 1 STEP 2A · STORAGE RESOLUTION PROBE';
    $L[] = 'Generated: ' . $probe['generated_at'] . '   (read-only; nothing opened, created or written)';
    $L[] = str_repeat('=', 78);
    $L[] = 'App folder: ' . $probe['app_dir'];
    $L[] = 'Routing file (tenants.php) present: ' . ($probe['registry_present'] ? 'yes' : 'NO');
    $L[] = '';
    $L[] = 'WORKSPACE DATA FILES FOUND ON DISK';
    foreach ($probe['dirs'] as $label => $d) {
        $L[] = '  ' . $label;
        $L[] = '    path     : ' . $d['path'];
        $L[] = '    exists   : ' . ($d['exists'] ? 'yes' : 'no') . ($d['exists'] && !$d['readable'] ? ' (NOT READABLE)' : '');
        if ($d['files']) foreach ($d['files'] as $f)
            $L[] = '    file     : ' . $f['name'] . '  (' . number_format($f['bytes']) . ' bytes)';
        elseif ($d['readable']) $L[] = '    file     : (none)';
    }
    $L[] = '';
    $L[] = 'PER-WORKSPACE ROUTING';
    foreach ($probe['tenants'] as $r) {
        $L[] = '';
        $L[] = '  ' . $r['tenant'] . (isset($r['control_company']) ? '  -  ' . $r['control_company'] : '');
        if (isset($r['control_status']))
            $L[] = '    control record        : status=' . $r['control_status']
                 . ' plan=' . ($r['control_plan'] ?? '')
                 . ' enabled_modules=' . ($r['control_enabled_modules'] ?? '')
                 . (isset($r['control_created_at']) && $r['control_created_at'] !== '' ? ' created=' . $r['control_created_at'] : '')
                 . (isset($r['control_updated_at']) && $r['control_updated_at'] !== '' ? ' updated=' . $r['control_updated_at'] : '');
        $L[] = '    control DB route_json : ' . $r['control_route'] . '  ' . p1_yn($r['control_exists']);
        $L[] = '    tenants.php registry  : ' . $r['registry_route'] . '  ' . p1_yn($r['registry_exists']);
        $L[] = '    sources agree         : ' . ($r['sources_agree'] === null
            ? 'n/a — the routing file records no entry for this workspace'
            : ($r['sources_agree'] ? 'YES' : 'NO  <-- the application and this tool would open DIFFERENT databases'));
        $L[] = '    the APPLICATION uses  : ' . ($r['registry_has_entry']
            ? $r['app_would_use']
            : '(nothing — with no routing entry the application cannot open this workspace at all)');
        $L[] = '    the INVENTORY used    : ' . $r['tool_used'];
        $fct = function ($f) {
            if (!$f) return '';
            return $f['exists']
                ? '[exists - ' . number_format($f['bytes']) . ' bytes - modified ' . $f['modified']
                  . ' - ' . ($f['readable'] ? 'readable' : 'NOT READABLE') . ']'
                : '[FILE ABSENT - NOT OPENED - NOT CREATED]';
        };
        if ($r['control_facts'])  $L[] = '      routed path facts   : ' . $fct($r['control_facts'])
                                       . ($r['control_facts']['absolute'] ? ' (absolute)' : ' (RELATIVE)');
        $L[] = !empty($r['mysql']['configured'])
            ? '    MySQL storage         : CONFIGURED via ' . $r['mysql']['source']
              . ' -> database "' . $r['mysql']['database'] . '" @ ' . $r['mysql']['host']
            : '    MySQL storage         : not configured (file-backed workspace)';
        if (!empty($r['candidates'])) {
            foreach ($r['candidates'] as $c) {
                $i = $c['identity'];
                $L[] = '    CANDIDATE FILE        : ' . $c['path'];
                $L[] = '        location          : ' . $c['where'] . ($c['exact_name'] ? '' : '   <-- FILENAME VARIANT, not the routed name');
                $L[] = '        size / modified   : ' . number_format($c['bytes']) . ' bytes - ' . $c['modified']
                     . ' - ' . ($c['readable'] ? 'readable' : 'NOT READABLE');
                $L[] = '        real SQLite db    : ' . ($c['is_sqlite'] ? 'yes' : 'NO');
                $L[] = '        identity          : ' . ($i['verdict'] ?? 'UNKNOWN') . (!empty($i['error']) ? ' (' . $i['error'] . ')' : '');
                if (!empty($i['opened'])) {
                    $L[] = '        tables            : ' . $i['tables'] . ' (EXAACT: ' . implode(',', $i['exaact_tables']) . ')';
                    $L[] = '        workspace name    : ' . (trim($i['app_name'] . ' ' . $i['company_name']) ?: '(not set)');
                    $L[] = '        provisioned       : ' . ($i['saas_provisioned'] !== '' ? $i['saas_provisioned'] : '(not set)');
                    $L[] = '        ceiling           : ' . ($i['saas_entitled_modules'] !== '' ? $i['saas_entitled_modules'] : '(BLANK)');
                    $L[] = '        modules_off       : ' . ($i['modules_off'] !== '' ? $i['modules_off'] : '(none)');
                    if (!empty($i['counts'])) {
                        $cc = []; foreach ($i['counts'] as $tb => $n) $cc[] = $tb . '=' . $n;
                        $L[] = '        records           : ' . implode('  ', $cc);
                    }
                }
            }
        } else {
            $L[] = '    CANDIDATE FILES       : none - no data file for this workspace in any known location';
        }
        $L[] = '    >> CLASSIFICATION     : ' . ($r['classification'] ?? 'UNKNOWN');
    }
    $L[] = '';
    if (!empty($probe['recovery'])) $L[] = rtrim(p1_render_recovery_text($probe['recovery']));
    $L[] = '';
    $L[] = str_repeat('=', 78);
    $L[] = 'END OF PROBE — nothing was opened, created or written.';
    return implode("\n", $L) . "\n";
}

function p1_yn($v) { return $v === null ? '' : ($v ? '[file exists]' : '[FILE NOT FOUND]'); }

// ============================================================================
//  STEP 2B — LIVE STORAGE DIAGNOSTIC  (read-only, identity-verifying)
//
//  Step 2A answered "is the routed file there?". Step 2B must answer the harder
//  question: "does a REAL Sachee database exist anywhere, and is it genuinely
//  Sachee's?" — without creating, modifying or provisioning anything.
//
//  Two hard guarantees:
//   1. Every SQLite open uses PDO::SQLITE_OPEN_READONLY, which refuses at the
//      operating-system level to create a missing file and cannot write.
//   2. Every statement still passes through p1_ro_query()'s read-only guard.
// ============================================================================

// Filesystem facts about a path. Touches metadata only — never opens the file.
function p1_path_facts($path) {
    $path = (string) $path;
    $f = ['path' => $path, 'absolute' => ($path !== '' && $path[0] === '/'),
          'exists' => false, 'bytes' => 0, 'modified' => '', 'readable' => false];
    if ($path === '' || !file_exists($path)) return $f;
    $f['exists']   = true;
    $f['bytes']    = (int) @filesize($path);
    $mt            = @filemtime($path);
    $f['modified'] = $mt ? date('Y-m-d H:i:s', $mt) : '';
    $f['readable'] = @is_readable($path);
    return $f;
}

// Is this really a SQLite database? Reads the 16-byte header only — no DB open.
function p1_is_sqlite_file($path) {
    if (!is_file($path) || !is_readable($path)) return false;
    $fh = @fopen($path, 'rb');
    if (!$fh) return false;
    $magic = @fread($fh, 16);
    @fclose($fh);
    return $magic === "SQLite format 3\0";
}

// Open a SQLite file STRICTLY read-only. Refuses to create a missing file.
function p1_ro_sqlite($path) {
    if (!is_file($path)) throw new RuntimeException('file does not exist — not opened, not created');
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    if (defined('PDO::SQLITE_ATTR_OPEN_FLAGS') && defined('PDO::SQLITE_OPEN_READONLY'))
        $opts[PDO::SQLITE_ATTR_OPEN_FLAGS] = PDO::SQLITE_OPEN_READONLY;
    return new PDO('sqlite:' . $path, null, null, $opts);
}

// Filename variants for a workspace key, so a near-miss spelling is still found
// (e.g. "recurit" vs "recuirt" — adjacent-character transpositions).
function p1_key_variants($key) {
    $k = preg_replace('/[^a-z0-9_\-]/', '_', strtolower(trim((string) $key)));
    $out = [$k];
    $len = strlen($k);
    for ($i = 0; $i < $len - 1; $i++) {                    // single adjacent swap
        $v = $k; $t = $v[$i]; $v[$i] = $v[$i + 1]; $v[$i + 1] = $t;
        if ($v !== $k) $out[] = $v;
    }
    $out[] = str_replace('-', '_', $k);
    $out[] = str_replace('_', '-', $k);
    return array_values(array_unique($out));
}

// EVERY location worth searching for a workspace data file: the routing
// locations, plus one level above the app folder and a sibling 'data' folder.
// Using one list everywhere keeps the per-workspace classification and the
// recovery scan from ever disagreeing.
function p1_search_dirs($appDir) {
    $appDir = rtrim((string) $appDir, '/');
    $places = p1_candidate_dirs($appDir);
    $places['one level above the app folder'] = dirname($appDir);
    // On this server the sibling 'data' folder belongs to a DIFFERENT
    // application, so only 'tenant-*.sqlite' is ever globbed there.
    $places["sibling 'data' folder (shared account root)"] = dirname($appDir) . '/data';
    return $places;
}

// Every workspace data file in $dirs that could belong to this key.
// $exactName is the file the routing expects; anything else is a variant.
function p1_find_candidates($key, array $dirs) {
    $variants = p1_key_variants($key);
    $exact    = 'tenant-' . $variants[0] . '.sqlite';
    $found    = [];
    foreach ($dirs as $label => $dir) {
        if (!is_dir($dir) || !is_readable($dir)) continue;
        foreach (glob(rtrim($dir, '/') . '/tenant-*.sqlite') ?: [] as $f) {
            $base = basename($f);
            $stem = strtolower(preg_replace('/^tenant-|\.sqlite$/', '', $base));
            if (!in_array($stem, $variants, true)) continue;      // not this workspace
            $found[] = p1_path_facts($f) + [
                'where'      => $label,
                'name'       => $base,
                'exact_name' => ($base === $exact),
                'is_sqlite'  => p1_is_sqlite_file($f),
            ];
        }
    }
    return $found;
}

// Read-only identity check: is this database really the named workspace's?
// Opens READONLY, lists tables, and reads a few identifying settings.
function p1_identify_db($path, $expectKey = '', $expectCompany = '') {
    $id = ['opened' => false, 'error' => '', 'tables' => 0, 'exaact_tables' => [],
           'app_name' => '', 'saas_provisioned' => '', 'saas_entitled_modules' => '',
           'modules_off' => '', 'company_name' => '', 'counts' => [], 'verdict' => 'UNKNOWN'];
    if (!p1_is_sqlite_file($path)) {
        $id['error'] = 'not a SQLite database (header mismatch)';
        $id['verdict'] = 'NOT_A_DATABASE';
        return $id;
    }
    try {
        $db = p1_ro_sqlite($path);
        $id['opened'] = true;
        $names = p1_ro_query($db, "SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $names = array_map('strval', (array) $names);
        $id['tables'] = count($names);
        // Tables an EXAACT workspace is expected to have.
        foreach (['settings', 'users', 'candidates', 'requisitions', 'lookup_values', 'offices'] as $t)
            if (in_array($t, $names, true)) $id['exaact_tables'][] = $t;

        if (in_array('settings', $names, true)) {
            $keys = ['app_name', 'saas_provisioned', 'saas_entitled_modules', 'modules_off', 'company_name'];
            $in = implode(',', array_fill(0, count($keys), '?'));
            foreach (p1_ro_query($db, "SELECT skey, svalue FROM settings WHERE skey IN ($in)", $keys)->fetchAll() as $r)
                $id[(string) $r['skey']] = (string) $r['svalue'];
        }
        foreach (['users', 'candidates', 'requisitions'] as $t)
            if (in_array($t, $names, true)) {
                try { $id['counts'][$t] = (int) p1_ro_query($db, "SELECT COUNT(*) c FROM \"$t\"")->fetch()['c']; }
                catch (Throwable $e) {}
            }
        $db = null;
    } catch (Throwable $e) {
        $id['error'] = $e->getMessage();
        $id['verdict'] = 'UNREADABLE';
        return $id;
    }

    // Verdict, from evidence only.
    $looksExaact = count($id['exaact_tables']) >= 3;
    $label = strtolower(trim($id['app_name'] . ' ' . $id['company_name']));
    $want  = strtolower(trim((string) $expectCompany));
    $keyw  = strtolower(str_replace('-', ' ', (string) $expectKey));
    $firstWord = strtok($keyw, ' ');
    $nameMatch = ($want !== '' && $label !== '' && (strpos($label, $want) !== false || strpos($want, $label) !== false))
              || ($firstWord !== false && $firstWord !== '' && strlen($firstWord) > 3 && strpos($label, $firstWord) !== false);

    if (!$looksExaact)              $id['verdict'] = 'NOT_AN_EXAACT_WORKSPACE';
    elseif ($label === '')          $id['verdict'] = 'EXAACT_WORKSPACE_UNNAMED';
    elseif ($nameMatch)             $id['verdict'] = 'MATCHES_EXPECTED_WORKSPACE';
    else                            $id['verdict'] = 'BELONGS_TO_ANOTHER_WORKSPACE';
    return $id;
}

// Does this workspace have MySQL/MariaDB storage configured (from either source)?
function p1_mysql_storage_check($routeJson, $registryEntry) {
    $out = ['configured' => false, 'source' => '', 'database' => '', 'host' => ''];
    foreach ([['control database route_json', $routeJson],
              ['tenants.php routing file', $registryEntry]] as [$src, $raw]) {
        $r = p1_route_path($raw);
        if ($r['kind'] === 'mysql') {
            $d = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($d) && !empty($d['db']) && is_array($d['db'])) $d = $d['db'];
            $out = ['configured' => true, 'source' => $src,
                    'database' => (string) ($d['name'] ?? ''), 'host' => (string) ($d['host'] ?? '')];
            return $out;                      // credentials deliberately not returned
        }
    }
    return $out;
}

// ============================================================================
//  RECOVERY SCAN  (read-only)
//
//  Added when both workspaces became unreadable between two inventory runs.
//  Its only job is to answer: does the data still exist ANYWHERE — as a live
//  file that has merely moved, or as a backup snapshot?
//
//  Lists names, sizes and dates. Opens nothing. Creates nothing. Writes nothing.
// ============================================================================

// Where the backup engine stores snapshots (lib/backup.php backup_root()).
function p1_backup_dirs($appDir) {
    $appDir = rtrim((string) $appDir, '/');
    return [
        'exaact_backups (above web root)' => dirname($appDir) . '/exaact_backups',
        'data-backups (inside app folder)' => $appDir . '/data-backups',
    ];
}

// Per-workspace backup folders: how many snapshots, newest, total size.
function p1_scan_backups($dir) {
    $out = ['path' => $dir, 'exists' => is_dir($dir), 'readable' => is_dir($dir) && is_readable($dir), 'workspaces' => []];
    if (!$out['readable']) return $out;
    foreach (glob(rtrim($dir, '/') . '/*', GLOB_ONLYDIR) ?: [] as $wd) {
        $files = array_merge(glob($wd . '/*.json.gz') ?: [], glob($wd . '/*.json') ?: []);
        if (!$files) continue;
        // Snapshot filenames are YYYYMMDD_HHMMSS_reason.json[.gz], so sorting by
        // NAME is the reliable "newest" — file timestamps tie when several are
        // written in the same second, or are rewritten by an upload.
        $bytes = 0;
        foreach ($files as $f) $bytes += (int) @filesize($f);
        $names = array_map('basename', $files);
        rsort($names);
        $newestName = $names[0] ?? '';
        $newest = $newestName !== '' ? (int) @filemtime(rtrim($wd, '/') . '/' . $newestName) : 0;
        $out['workspaces'][] = [
            'workspace'   => basename($wd),
            'snapshots'   => count($files),
            'total_bytes' => $bytes,
            'newest'      => $newestName,
            'newest_at'   => $newest ? date('Y-m-d H:i:s', $newest) : '',
        ];
    }
    return $out;
}

// Every folder swept for workspace data files: the locations this application
// uses, plus one level beneath the shared account root, so a file that was
// MOVED into a sub-folder is still found.
//
// Other applications' folders are traversed by NAME ONLY. Nothing is matched
// unless its filename carries this application's own 'tenant-' prefix or a
// workspace key from the control database, so a neighbouring app's databases
// are never listed, never opened and never touched.
function p1_sweep_dirs($appDir) {
    $appDir = rtrim((string) $appDir, '/');
    $places = p1_search_dirs($appDir);
    foreach (glob(dirname($appDir) . '/*', GLOB_ONLYDIR) ?: [] as $d) {
        if (in_array($d, $places, true)) continue;
        $places['account root -> ' . basename($d)] = $d;
    }
    return $places;
}

// Filename patterns that identify THIS application's workspace data, including
// copies that were renamed or given a suffix (…​.sqlite.bak, …​.sqlite.old).
function p1_sweep_patterns(array $keys = []) {
    $pats = ['/tenant-*.sqlite', '/tenant-*.sqlite.*', '/tenant-*.db', '/tenant-*.sqlite3'];
    foreach ($keys as $k) {
        $k = trim((string) $k);
        if ($k !== '') $pats[] = '/*' . $k . '*';
    }
    return $pats;
}

// A wide sweep for workspace data files that may simply have moved.
// Returns BOTH what was found and every folder that was looked in, so a
// "nothing found" result can be audited rather than taken on trust.
function p1_sweep($appDir, array $keys = []) {
    $places = p1_sweep_dirs($appDir);
    $pats   = p1_sweep_patterns($keys);
    $searched = [];
    $files = [];
    $seen  = [];
    foreach ($places as $label => $dir) {
        $rec = ['path' => $dir, 'exists' => is_dir($dir),
                'readable' => is_dir($dir) && is_readable($dir), 'matches' => 0];
        if ($rec['readable']) {
            foreach ($pats as $pat) {
                foreach (glob(rtrim($dir, '/') . $pat) ?: [] as $f) {
                    if (!is_file($f) || isset($seen[$f])) continue;
                    $seen[$f] = true;
                    $rec['matches']++;
                    $files[] = p1_path_facts($f) + ['where' => $label, 'name' => basename($f),
                                                    'is_sqlite' => p1_is_sqlite_file($f)];
                }
            }
        }
        $searched[$label] = $rec;
    }
    return ['searched' => $searched, 'files' => $files];
}

// Backwards-compatible: just the files.
function p1_sweep_tenant_files($appDir, array $keys = []) {
    $s = p1_sweep($appDir, $keys);
    return $s['files'];
}

// Per-workspace recovery verdict.
//
// This exists because an earlier version answered "recovery is possible" when
// ANY backup folder held snapshots — including '__control', which holds the
// routing directory and NOT a single workspace's records. A verdict about a
// workspace must be computed from that workspace's own evidence only.
function p1_recovery_per_workspace(array $rec, array $tenants) {
    $out = [];
    foreach ($tenants as $t) {
        $k = is_array($t) ? (string) ($t['tenant'] ?? '') : (string) $t;
        if ($k === '') continue;
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $k);   // backup_tenant_id()

        $live = [];
        foreach ($rec['live_files'] as $f)
            if (strpos((string) $f['name'], $k) !== false) $live[] = $f;

        $snaps = 0; $newest = ''; $newestIn = '';
        foreach ($rec['backups'] as $label => $b) {
            foreach (($b['workspaces'] ?? []) as $w) {
                if ($w['workspace'] !== $k && $w['workspace'] !== $safeKey) continue;
                $snaps += (int) $w['snapshots'];
                if ((string) $w['newest'] > $newest) { $newest = (string) $w['newest']; $newestIn = $label; }
            }
        }

        // A company created moments ago and never opened has no data to lose.
        // Calling that "NOT RECOVERABLE" is alarming and wrong: there is simply
        // nothing there yet. Only a workspace the control database records as
        // already set up can have lost anything.
        $everSetUp = is_array($t) && trim((string) ($t['control_provisioned_at'] ?? '')) !== '';
        $recoverable = ($live || $snaps > 0);

        // A healthy workspace must not be described as recovered from something.
        // "Only the routing is out of step" was printed for a workspace whose
        // routed database was exactly where it should be — alarming, and untrue.
        // Finding a file somewhere says nothing on its own; whether the ROUTED
        // file is present is the question.
        $routedOk = is_array($t) && !empty($t['control_exists']);

        if ($routedOk)          $verdict = 'HEALTHY — the routed database is in place and readable';
        elseif ($live)          $verdict = 'RECOVERABLE — the data file exists, but not where the routing points';
        elseif ($snaps > 0)     $verdict = 'RECOVERABLE FROM BACKUP — no live file, but a snapshot of this workspace exists';
        elseif (!$everSetUp)    $verdict = 'NOTHING TO RECOVER — never signed in to, so it has no data yet. Normal for a new company.';
        else                    $verdict = 'NOT RECOVERABLE FROM THIS SERVER — no data file and no backup of this workspace';

        $out[$k] = ['workspace' => $k, 'live_files' => $live, 'snapshots' => $snaps,
                    'newest' => $newest, 'newest_in' => $newestIn, 'ever_set_up' => $everSetUp,
                    'healthy' => $routedOk, 'recoverable' => ($routedOk || $recoverable),
                    'lost' => (!$routedOk && !$recoverable && $everSetUp),
                    'verdict' => $verdict];
    }
    return $out;
}

function p1_render_recovery_text(array $rec) {
    $L = [];
    $L[] = '';
    $L[] = str_repeat('=', 78);
    $L[] = 'RECOVERY SCAN — does the data still exist anywhere?';
    $L[] = str_repeat('=', 78);
    $L[] = 'App folder            : ' . $rec['app_dir'];
    $L[] = 'One level above it    : ' . dirname($rec['app_dir']);
    $L[] = '';
    $L[] = 'FOLDERS SEARCHED (so a "nothing found" answer can be checked, not trusted)';
    foreach (($rec['searched'] ?? []) as $label => $s) {
        $state = !$s['exists'] ? 'does not exist' : (!$s['readable'] ? 'exists, NOT readable' : 'searched');
        $L[] = '  ' . str_pad($label, 44) . $state
             . ($s['readable'] ? '  — ' . $s['matches'] . ' matching file(s)' : '');
        $L[] = '      ' . $s['path'];
    }
    $L[] = '  Matched by name only: tenant-*.sqlite (and .bak/.old/.db copies) and any';
    $L[] = '  filename carrying a workspace key. No other application\'s files are';
    $L[] = '  listed, opened or touched.';
    $L[] = '';
    $L[] = 'LIVE WORKSPACE FILES FOUND';
    if ($rec['live_files']) {
        foreach ($rec['live_files'] as $f)
            $L[] = '  ' . $f['name'] . '  (' . number_format($f['bytes']) . ' bytes, modified ' . $f['modified']
                 . ', ' . ($f['is_sqlite'] ? 'valid database' : 'NOT a database') . ')  in ' . $f['where'];
    } else {
        $L[] = '  NONE FOUND — no workspace data file exists in any folder listed above.';
    }
    $L[] = '';
    $L[] = 'BACKUP SNAPSHOTS';
    foreach ($rec['backups'] as $label => $b) {
        $L[] = '  ' . $label;
        $L[] = '    path   : ' . $b['path'];
        $L[] = '    exists : ' . ($b['exists'] ? 'yes' : 'no');
        if (!empty($b['workspaces'])) {
            foreach ($b['workspaces'] as $w) {
                $note = $w['workspace'] === '__control'
                      ? '   << the routing directory, NOT any workspace\'s records'
                      : '';
                $L[] = '    -> ' . $w['workspace'] . ' : ' . $w['snapshots'] . ' snapshot(s), '
                     . number_format($w['total_bytes']) . ' bytes, newest ' . $w['newest']
                     . ' at ' . $w['newest_at'] . $note;
            }
        } elseif ($b['exists']) {
            $L[] = '    -> (no snapshots)';
        }
    }
    $per = $rec['per_workspace'] ?? [];
    if ($per) {
        $L[] = '';
        $L[] = 'VERDICT PER WORKSPACE';
        foreach ($per as $w) {
            $L[] = '  ' . $w['workspace'];
            $L[] = '    live data file   : ' . ($w['live_files'] ? count($w['live_files']) . ' found' : 'none');
            $L[] = '    backup snapshots : ' . ($w['snapshots'] ?: 'none')
                 . ($w['newest'] ? '  (newest ' . $w['newest'] . ' in ' . $w['newest_in'] . ')' : '');
            $L[] = '    >> ' . $w['verdict'];
        }
        $lost = array_values(array_filter($per, fn($w) => !empty($w['lost'])));
        $new  = array_values(array_filter($per, fn($w) => empty($w['ever_set_up'])));
        $L[] = '';
        if (!$lost) {
            $healthy = array_values(array_filter($per, fn($w) => !empty($w['healthy'])));
            $L[] = $new && count($new) === count($per)
                ? '>> NOTHING TO RECOVER. Every workspace here is new and has never been opened.'
                : ($healthy && count($healthy) + count($new) === count($per)
                    ? '>> ALL HEALTHY. Every workspace that has been opened has its database in place.'
                    : '>> NOTHING LOST. Every workspace that has ever been used still has its data.');
        } elseif (count($lost) === count($per)) {
            $L[] = '>> NO WORKSPACE CAN BE RECOVERED FROM THIS SERVER.';
            $L[] = '   Next place to look is the hosting account backup (cPanel / JetBackup)';
            $L[] = '   and the File Manager trash. Do not re-create the workspaces first.';
        } else {
            $L[] = '>> PARTIAL. Recoverable: ' . (count($per) - count($lost)) . ' of ' . count($per) . '.';
            foreach ($lost as $w) $L[] = '   NOT recoverable: ' . $w['workspace'];
        }
    } else {
        $L[] = '';
        $L[] = $rec['live_files']
            ? '>> Workspace data files found. Recovery is possible.'
            : '>> No workspace data file found in any folder listed above.';
    }
    $L[] = str_repeat('=', 78);
    return implode("\n", $L) . "\n";
}
