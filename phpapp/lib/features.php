<?php
// ============================================================================
//  FEATURE SWITCHES — plan 42, step A (owner, 18 Nov 2026)
// ============================================================================
//  "We must have switches to make the features on and off … for all the
//   features and sub features under each module … it shall be easy to get back
//   all the things as it is today."
//
//  The module switch already exists (Admin → Modules & features → licence_save
//  → settings.modules_off). This adds ONE more level under it: every screen of
//  every module is a feature with its own switch.
//
//  The list of features is NOT typed here. It is read from the menu itself —
//  ops_nav_index(), the "Find a screen" list, which is built from the area
//  tiles (lib/areas.php) plus the Home / Operations / Recruitment entries — so a
//  new screen gets a switch without anybody remembering to add one.
//
//  A feature that is OFF is
//    · not shown as a tile, in "Find a screen", or in the left menu;
//    · refused if its address is typed (it and the screens of its family —
//      /leads also covers /lead and /lead-new — see feat_entry());
//    · hidden as a link or button on other screens (app.js, the same rule).
//  Its records are never touched. Switching it on brings everything back.
//
//  Storage: settings.features_off (JSON). Who: cockpit_can() — the same people
//  as the Modules & features screen today. No new permission (docs/02).
//  "Back to 18 Nov 2026": features_baseline, captured once by feat_migrate().
// ============================================================================

//  Never switchable: the screens a company needs to run itself and to switch
//  things back on. A route in this list is never refused by a feature switch.
const FEAT_CORE = ['', 'admin', 'workspace/setup', 'workspace/setup/modules', 'workspace/setup/features-save',
    'workspace/setup/features-preset', 'workspace/setup/module-toggle', 'users', 'user-new', 'user-edit', 'access',
    'settings', 'masters', 'company-profile', 'licence', 'backup', 'search', 'my-work', 'tasks', 'login', 'logout',
    'sales', 'operations', 'quality', 'reporting', 'money', 'insights', 'directory', 'recruitment', 'marketplace',
    'subscription', 'owner', 'platform-setup', 'audit-log', 'hierarchy', 'role-workspaces',
    // the work itself, and what other screens cannot do without
    'jobs', 'calls', 'clients', 'vendors', 'vouchers',
    // every approvals inbox and its setup: switching one off would leave work stuck
    'approvals', 'my-approvals', 'voucher-approvals', 'sales-approvals', 'approval-rules', 'approvals-setup',
    'approver-map', 'vetting-checklist', 'recruit-approvals', 'approval-delegations', 'voucher-rules',
    // a person's own sign-in and signature
    'my-signature', 'change-password', 'two-factor',
    // the platform owner's screens (never shown inside a customer's company)
    'super-admin', 'companies', 'tenants', 'product-package'];

//  A screen listed in "Find a screen" under another area that belongs on this card.
const FEAT_CARD_OF = ['project-costings' => 'sales', 'crm-dashboard' => 'sales'];

//  The feature groups shown on the screen, by the menu area a screen is filed
//  under (ops_nav_index 'area'). The module key is the existing licence module
//  whose switch sits on the same card ('' = no module switch for that group).
const FEAT_GROUPS = [
    'Home'                    => ['home',       ''],
    'Sales'                   => ['sales',      'sales'],
    'Operations'              => ['operations', 'operations'],
    'Quality & Accreditation' => ['operations', 'operations'],
    'Reporting'               => ['reporting',  'reporting'],
    'Money'                   => ['money',      'money'],
    'Recruitment'             => ['hr',         'hr'],
    'Marketplace'             => ['connect',    'connect'],
    'Insights'                => ['home',       ''],
    'Directory'               => ['admin',      'admin'],
    'Admin'                   => ['admin',      'admin'],
];

//  The switches that already existed on their own screens, shown here too.
//  Same setting, same meaning — not copies. [setting, default, card, label, what]
const FEAT_LEGACY = [
    ['preorder_checklist_on', '0', 'sales',      'Pre-order checklist',   'Items to confirm before an order is accepted'],
    ['satisfaction_on',       '1', 'operations', 'Customer satisfaction', 'A short survey to the customer after a job'],
    ['portal_enabled',        '0', 'admin',      'Client portal',         'Customers sign in to see their reports'],
    ['vendor_portal_enabled', '0', 'admin',      'Vendor portal',         'Vendors sign in to see their orders'],
    ['careers_enabled',       '0', 'hr',         'Careers page',          'A public page listing the openings you advertise'],
];

//  "Simple start" (document 40/42): what the recommended layout turns off.
//  Only the keys that exist in this company's menu are used.
const FEAT_SIMPLE_OFF = ['project-costings', 'stage-gates', 'pipelines', 'ads-roi', 'preorder-checklist',
    'books-bridge', 'sbu-pl', 'cost-reconciliation', 'advisor', 'flow-gaps', 'command-centre'];
const FEAT_SIMPLE_MODULES_OFF = ['hr', 'connect'];

function feat_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('setting_get') || !function_exists('setting_set')) return;
    try {
        if (setting_get('features_baseline', null) !== null) return;
        // Captured ONCE, the first time this version runs: exactly how this company
        // was set up before any switch existed — the "back to today" button restores it.
        $b = ['at' => date('Y-m-d H:i'), 'modules_off' => (string) setting_get('modules_off', ''),
              'features_off' => (string) setting_get('features_off', '')];
        foreach (FEAT_LEGACY as [$k, $def]) $b['legacy'][$k] = (string) setting_get($k, $def);
        setting_set('features_baseline', json_encode($b));
    } catch (Throwable $e) { /* never break boot */ }
}

// ---- The stored switches ----------------------------------------------------
//  features_off = {"<key>": {"p": path, "s": stem, "x": [stems that stay on]}}
function feat_off_entries() {
    $raw = function_exists('setting_get') ? (string) (setting_get('features_off', '') ?? '') : '';
    $d = $raw !== '' ? json_decode($raw, true) : [];
    return is_array($d) ? $d : [];
}
function feat_key($url) {
    $u = trim((string) $url);
    $u = explode('#', $u, 2)[0];
    return trim($u, '/');
}
function feat_path($key) { return trim(explode('?', (string) $key, 2)[0], '/'); }
//  The family stem: /leads → "lead", /opportunities → "opportunit", /jobs → "job".
function feat_stem($path) {
    $p = feat_path($path);
    if (preg_match('/ies$/', $p)) return substr($p, 0, -3);
    if (preg_match('/[^s]s$/', $p)) return substr($p, 0, -1);
    return $p;
}
//  Does this route belong to the family of this stem? "lead" covers lead, leads,
//  lead-new, leads/import — never leaderboard. Short stems (< 4) match exactly.
function feat_family_match($route, $stem) {
    $route = trim((string) $route, '/'); $stem = (string) $stem;
    if ($stem === '') return false;
    if ($route === $stem) return true;
    if (strlen($stem) < 4) return (bool) preg_match('/^' . preg_quote($stem, '/') . '(s|es|ies|y)?$/', $route);
    return (bool) preg_match('/^' . preg_quote($stem, '/') . '(s|es|ies|y)?([-\/].*)?$/', $route);
}
function feat_module_of($route) {
    $route = trim((string) $route, '/');
    $map = function_exists('ops_route_module_map') ? ops_route_module_map() : [];
    if (isset($map[$route])) return (string) $map[$route];
    $f = function_exists('ops_module_family') ? ops_module_family($route) : null;
    //  Unmapped: the name is the only evidence — its first word (project-costing-heads → project).
    return $f === null ? '?' . preg_split('#[-/]#', $route)[0] : (string) $f;
}
function feat_is_core($route) {
    $route = trim((string) $route, '/');
    if (in_array($route, FEAT_CORE, true)) return true;
    return strncmp($route, 'workspace/setup', 15) === 0 || strncmp($route, 'm/', 2) === 0;
}

//  Is the feature with this key (a menu url) on? (Tiles, Find a screen, menu.)
function feat_on($url) {
    if (!empty($GLOBALS['__feat_all'])) return true;          // building the catalogue
    $k = feat_key($url);
    if ($k === '' || feat_is_core(feat_path($k))) return true;
    $off = feat_off_entries();
    return !isset($off[$k]) && !feat_route_blocked(feat_path($k));
}
//  Is this route refused because its feature is off? Returns the key, or ''.
function feat_route_blocked($route) {
    $route = trim((string) $route, '/');
    if ($route === '' || feat_is_core($route)) return '';
    foreach (feat_off_entries() as $k => $e) {
        if (strpos((string) $k, '?') !== false) continue;        // a "?kind=" tile hides only its tile
        if (feat_is_core($e['p'] ?? '')) continue;               // a core screen (and its family) is never switched off
        if ($route !== ($e['p'] ?? '')) {
            if (!feat_family_match($route, $e['s'] ?? '')) continue;
            //  A family is a family only inside one access module: /reports (the
            //  dashboards) does not take /report-approve (jobs) or /report-file with it.
            if (feat_module_of($route) !== feat_module_of($e['p'] ?? '')) continue;
        }
        $kept = false;
        foreach ((array) ($e['x'] ?? []) as $x) if ($x !== '' && feat_family_match($route, $x)) { $kept = true; break; }
        if (!$kept) return (string) $k;
    }
    return '';
}

// ---- The catalogue: every screen, from the menu itself -------------------------
//  [card => [ [key, label, area, desc, icon], … ]] for the current (master) user,
//  switched-off features included.
function feat_catalog() {
    if (!function_exists('ops_nav_index')) return [];
    $GLOBALS['__feat_all'] = true;
    try { $idx = ops_nav_index(true); } finally { $GLOBALS['__feat_all'] = false; }
    if (function_exists('ops_nav_index')) ops_nav_index(true);   // put the normal (filtered) list back in its cache
    $out = []; $seen = [];
    foreach ($idx as $i) {
        if (($i['kind'] ?? 'screen') !== 'screen') continue;
        $k = feat_key($i['url'] ?? '');
        if ($k === '' || preg_match('#^https?:#', (string) ($i['url'] ?? ''))) continue;
        if (feat_is_core(feat_path($k)) || isset($seen[$k])) continue;
        $seen[$k] = true;
        $card = FEAT_CARD_OF[feat_path($k)] ?? (FEAT_GROUPS[$i['area'] ?? ''][0] ?? 'home');
        $out[$card][] = ['key' => $k, 'label' => (string) $i['label'], 'area' => (string) ($i['area'] ?? ''),
                         'desc' => (string) ($i['desc'] ?? ''), 'icon' => (string) ($i['icon'] ?? '•')];
    }
    return $out;
}
function feat_catalog_keys(array $cat = null) {
    $cat = $cat ?? feat_catalog(); $keys = [];
    foreach ($cat as $items) foreach ($items as $it) $keys[$it['key']] = $it['label'];
    return $keys;
}

//  Save the full set of OFF keys. Each entry carries its family stem and the
//  stems of OTHER screens that share it and stay on (report-types is its own
//  screen even though it starts with "report"), so a request is checked cheaply.
function feat_save_off(array $offKeys, array $allKeys) {
    $offKeys = array_values(array_unique(array_filter(array_map('strval', $offKeys), fn($k) => isset($allKeys[$k]))));
    $entries = [];
    foreach ($offKeys as $k) {
        $p = feat_path($k); $s = feat_stem($k); $x = [];
        foreach (array_keys($allKeys) as $other) {
            if ($other === $k || in_array($other, $offKeys, true) || strpos($other, '?') !== false) continue;
            $op = feat_path($other);
            if ($op !== $p && feat_family_match($op, $s)) $x[] = feat_stem($other);
        }
        // The family must never reach a core screen.
        foreach (FEAT_CORE as $c) if ($c !== '' && $c !== $p && feat_family_match($c, $s)) $x[] = $c;
        $entries[$k] = ['p' => $p, 's' => $s, 'x' => array_values(array_unique($x))];
    }
    $old = feat_off_entries();
    setting_set('features_off', $entries ? json_encode($entries) : '');
    feat_note_change();
    return [array_values(array_diff(array_keys($entries), array_keys($old))), array_values(array_diff(array_keys($old), array_keys($entries)))];
}
function feat_note_change() {
    $u = function_exists('current_user') ? current_user() : null;
    $who = $u ? (function_exists('user_name') ? user_name($u) : (string) ($u['username'] ?? '')) : '';
    setting_set('features_changed', json_encode(['at' => date('Y-m-d H:i'), 'by' => $who]));
}
function feat_legacy_on($setting, $default) { return (string) (setting_get($setting, $default) ?? $default) === '1'; }

//  The list app.js uses to hide links and buttons that open a switched-off
//  screen (the same rule as feat_route_blocked, in a compact form).
function feat_client_rules() {
    $r = [];
    foreach (feat_off_entries() as $k => $e) {
        if (strpos((string) $k, '?') !== false) { $r[] = ['k' => '/' . $k]; continue; }
        $r[] = ['p' => $e['p'] ?? '', 's' => $e['s'] ?? '', 'x' => array_values((array) ($e['x'] ?? []))];
    }
    return $r;
}

//  The refusal when a switched-off screen is opened by its address.
function feat_deny($key) {
    http_response_code(404);
    $label = $key;
    try { $all = feat_catalog_keys(); if (isset($all[$key])) $label = $all[$key]; } catch (Throwable $e) {}
    $msg = '"' . $label . '" is switched off for your company.'
         . (function_exists('cockpit_can') && cockpit_can() ? ' Switch it on in Admin → Modules & features.' : ' Ask your administrator if you need it.');
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false) {
        header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => $msg]); return;
    }
    if (function_exists('flash')) flash($msg, 'error');
    redirect('/');
}

// ---- Presets ---------------------------------------------------------------
//  Rebuild the module on-set with $offMods switched off (core modules stay on),
//  through the ONE module engine.
function feat_modules_set_off(array $offMods) {
    if (!function_exists('licence_summary') || !function_exists('licence_save')) return;
    $modOn = [];
    foreach (licence_summary() as $k => $r) {
        if (!empty($r['core'])) continue;
        if (!in_array($k, $offMods, true) && (!function_exists('module_entitled') || module_entitled($k))) $modOn[$k] = 1;
    }
    licence_save(['mod_on' => $modOn]);
}
//  What a preset would turn off / back on, for the confirmation panel.
function feat_preset_plan($preset) {
    $cat = feat_catalog(); $all = feat_catalog_keys($cat);
    if ($preset === 'simple') {
        $offF = array_values(array_filter(array_keys($all), fn($k) => in_array(feat_path($k), FEAT_SIMPLE_OFF, true)));
        return ['features' => $offF, 'labels' => array_map(fn($k) => $all[$k], $offF), 'modules' => FEAT_SIMPLE_MODULES_OFF, 'all' => $all];
    }
    return ['all' => $all];
}
function feat_apply_preset($preset) {
    feat_migrate();
    if ($preset === 'baseline') {
        $b = json_decode((string) setting_get('features_baseline', ''), true) ?: [];
        if (function_exists('licence_save')) setting_set('modules_off', (string) ($b['modules_off'] ?? ''));
        setting_set('features_off', (string) ($b['features_off'] ?? ''));
        foreach (FEAT_LEGACY as [$k, $def]) setting_set($k, (string) ($b['legacy'][$k] ?? $def));
        feat_note_change();
        return 'Back to how it was on ' . (isset($b['at']) ? date('j M Y', strtotime($b['at'])) : 'the day the switches arrived') . ': every module and screen as it was then.';
    }
    if ($preset === 'simple') {
        $plan = feat_preset_plan('simple');
        feat_modules_set_off(array_merge(array_filter(explode(',', (string) setting_get('modules_off', ''))), $plan['modules']));
        feat_save_off(array_merge(array_keys(feat_off_entries()), $plan['features']), $plan['all']);
        setting_set('preorder_checklist_on', '0');
        return 'Simple start is on. ' . count($plan['features']) . ' screens and ' . count($plan['modules']) . ' modules are switched off — nothing is deleted.';
    }
    return '';
}
