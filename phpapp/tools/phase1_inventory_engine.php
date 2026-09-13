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
    if (!empty($t['error']))
        return ['risk' => 'ERROR', 'severity' => 'UNKNOWN',
                'note' => 'Workspace database could not be read: ' . $t['error'] . ' — entitlement unknown.'];
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
    ];
    $row += p1_classify($row);

    // Marketplace is NOT in PRODUCT_MODULES, so the ceiling does not govern it
    // today. Record whether it is live, because promoting it to a real module
    // would switch it OFF unless it is backfilled first.
    $mkOn = $row['marketplace_addon'] === '' ? '(default)' : ($row['marketplace_addon'] === '1' ? 'ON' : 'off');
    $cxOn = $row['connect_enabled']  === '' ? '(default ON)' : ($row['connect_enabled'] === '1' ? 'ON' : 'off');
    $row['marketplace_state'] = 'addon=' . $mkOn . ' connect=' . $cxOn;
    $row['marketplace_needs_backfill'] = ($row['marketplace_addon'] !== '0' && $row['connect_enabled'] !== '0');
    return $row;
}

// ---- Summary ---------------------------------------------------------------
function p1_summarise(array $rows) {
    $s = ['total' => count($rows), 'SAFE' => 0, 'RECOVERABLE' => 0, 'AMBIGUOUS' => 0,
          'ERROR' => 0, 'LICENCE_GOVERNED' => 0, 'not_provisioned' => 0,
          'blank_ceiling' => 0, 'marketplace_backfill' => 0];
    foreach ($rows as $r) {
        $k = (string) ($r['risk'] ?? 'ERROR');
        if (isset($s[$k])) $s[$k]++;
        if (($r['provisioned'] ?? '') !== '1') $s['not_provisioned']++;
        if (!empty($r['ceiling_blank']))       $s['blank_ceiling']++;
        if (!empty($r['marketplace_needs_backfill'])) $s['marketplace_backfill']++;
    }
    $s['safe_to_flip'] = ($s['AMBIGUOUS'] === 0 && $s['ERROR'] === 0);
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
         . '   LICENCE: ' . $sum['LICENCE_GOVERNED'];
    $L[] = 'Blank ceiling: ' . $sum['blank_ceiling']
         . '   Not provisioned: ' . $sum['not_provisioned']
         . '   Marketplace needing backfill: ' . $sum['marketplace_backfill'];
    $L[] = 'VERDICT: ' . ($sum['safe_to_flip']
        ? 'every workspace has a determinable entitlement.'
        : 'DO NOT FLIP DEFAULT-DENY — ' . $sum['AMBIGUOUS'] . ' ambiguous, ' . $sum['ERROR'] . ' unreadable.');
    $L[] = str_repeat('=', 78);
    foreach ($report['tenants'] as $r) {
        $L[] = '';
        $L[] = '[' . $r['risk'] . '] ' . $r['tenant'] . '  —  ' . $r['company'];
        $L[] = '  status/plan      : ' . $r['status'] . ' / ' . $r['plan'] . ($r['plan_expiry'] ? ' (to ' . $r['plan_expiry'] . ')' : '');
        $L[] = '  storage          : ' . $r['storage'];
        if ($r['error']) { $L[] = '  ERROR            : ' . $r['error']; continue; }
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
