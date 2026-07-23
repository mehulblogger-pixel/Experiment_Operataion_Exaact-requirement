<?php
// ============================================================================
//  Organisation structure, configurable access (scope + permissions),
//  settings (financial year), and financial-year helpers.
//  Scope = which data you see (offices / SBUs / self).
//  Permissions = which features you may use.
// ============================================================================

// ---- Roles ----------------------------------------------------------------
const ORG_ROLES = [
    'MASTER_ADMIN' => 'Master Admin', 'BUSINESS_DIRECTOR' => 'Business Director',
    'SBU_HEAD' => 'SBU Head', 'BRANCH_MANAGER' => 'Branch Manager',
    'BRANCH_APP_MANAGER' => 'Branch Application Manager', 'OPERATION_MANAGER' => 'Operation Manager',
    'ASST_MANAGER' => 'Asst. Manager', 'COORDINATOR' => 'Coordinator',
    'FINANCE' => 'Finance', 'INSPECTOR' => 'Inspector', 'ADMIN' => 'Admin (legacy)',
];
// Management-level roles (kept compatible with existing is_admin_level gates)
const MGMT_ROLES = ['MASTER_ADMIN','ADMIN','BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER','BRANCH_APP_MANAGER','OPERATION_MANAGER','FINANCE'];

// ---- Permission catalogue --------------------------------------------------
const PERMISSIONS = [
    'dash.operations' => 'Operations dashboard',
    'dash.financial'  => 'Financial dashboard',
    'dash.utilization'=> 'Utilization dashboard',
    'dash.people'     => 'People & compliance dashboard',
    'data.credit'     => 'See credit / revenue figures',
    'data.salary'     => 'See salary / loaded cost',
    'data.profitability' => 'See BOSS / contract profitability',
    'ops.call.create' => 'Create / edit calls',
    'ops.job.allocate'=> 'Allocate / edit jobs',
    'ops.job.close'   => 'Close jobs',
    'ops.call.delete' => 'Delete calls',
    'master.manage'   => 'Manage master data',
    'finance.reconcile'=> 'Credit reconciliation',
    'users.manage.branch' => 'Manage users in own office',
    'users.manage.global' => 'Manage all users & access',
    'settings.manage' => 'Manage system settings',
];

// Role defaults: permissions granted, and scope (offices/sbus: ALL | OWN).
function role_defaults($role) {
    $all = array_keys(PERMISSIONS);
    switch ($role) {
        case 'MASTER_ADMIN': case 'ADMIN':
            return ['perms' => $all, 'offices' => 'ALL', 'sbus' => 'ALL'];
        case 'BUSINESS_DIRECTOR':
            return ['perms' => ['dash.operations','dash.financial','dash.utilization','dash.people','data.credit','data.salary','data.profitability'], 'offices' => 'ALL', 'sbus' => 'ALL'];
        case 'SBU_HEAD':
            return ['perms' => ['dash.operations','dash.financial','dash.utilization','dash.people','data.credit','data.salary','data.profitability'], 'offices' => 'ALL', 'sbus' => 'OWN'];
        case 'BRANCH_MANAGER':
            return ['perms' => ['dash.operations','dash.financial','dash.utilization','dash.people','data.credit','data.salary','data.profitability','ops.call.create','ops.job.allocate','ops.job.close','master.manage','users.manage.branch'], 'offices' => 'OWN', 'sbus' => 'ALL'];
        case 'BRANCH_APP_MANAGER':
            return ['perms' => ['dash.operations','dash.utilization','users.manage.branch','master.manage','ops.call.delete'], 'offices' => 'OWN', 'sbus' => 'ALL'];
        case 'OPERATION_MANAGER':
            // "manager under the branch manager" — may see BOSS/contract profitability
            return ['perms' => ['dash.operations','dash.utilization','data.profitability','ops.call.create','ops.job.allocate','ops.job.close'], 'offices' => 'OWN', 'sbus' => 'OWN'];
        case 'ASST_MANAGER':
            return ['perms' => ['dash.operations','ops.call.create','ops.job.allocate'], 'offices' => 'OWN', 'sbus' => 'OWN'];
        case 'COORDINATOR':
            // per decision: Operations + read-only revenue (financial section visible, but no salary/profit)
            return ['perms' => ['dash.operations','dash.financial','data.credit','ops.call.create','ops.job.allocate','ops.job.close'], 'offices' => 'OWN', 'sbus' => 'OWN'];
        case 'FINANCE':
            return ['perms' => ['dash.financial','data.credit','data.salary','data.profitability','finance.reconcile'], 'offices' => 'ALL', 'sbus' => 'ALL'];
        case 'INSPECTOR':
            return ['perms' => [], 'offices' => 'OWN', 'sbus' => 'OWN'];
    }
    return ['perms' => [], 'offices' => 'OWN', 'sbus' => 'OWN'];
}

// ---- Effective access for the current user (cached) ------------------------
function ua() {
    static $a = null;
    if ($a !== null) return $a;
    $u = current_user();
    if (!$u) return $a = ['role' => 'GUEST', 'perms' => [], 'offices' => [], 'sbus' => [], 'self' => true, 'home' => null, 'master' => false];
    $role = !empty($u['is_superuser']) ? 'MASTER_ADMIN' : strtoupper($u['role'] ?? 'ADMIN');
    if (!isset(ORG_ROLES[$role])) $role = 'ADMIN';
    $def = role_defaults($role);
    // permissions: stored csv overrides default; master gets everything
    $perms = ($role === 'MASTER_ADMIN') ? array_keys(PERMISSIONS)
        : (trim((string)($u['permissions'] ?? '')) !== '' ? array_filter(explode(',', $u['permissions'])) : $def['perms']);
    // office scope
    $so = trim((string)($u['scope_offices'] ?? ''));
    if ($role === 'MASTER_ADMIN' || $def['offices'] === 'ALL' && $so === '') $offices = 'ALL';
    elseif ($so === 'ALL') $offices = 'ALL';
    elseif ($so !== '') $offices = array_map('intval', array_filter(explode(',', $so)));
    else $offices = $u['home_office_id'] ? [(int)$u['home_office_id']] : 'ALL'; // OWN default
    // sbu scope
    $ss = trim((string)($u['scope_sbus'] ?? ''));
    if ($role === 'MASTER_ADMIN' || ($def['sbus'] === 'ALL' && $ss === '')) $sbus = 'ALL';
    elseif ($ss === 'ALL') $sbus = 'ALL';
    elseif ($ss !== '') $sbus = array_filter(explode(',', $ss));
    else $sbus = 'ALL';
    return $a = [
        'role' => $role, 'perms' => array_values($perms),
        'offices' => $offices, 'sbus' => $sbus,
        'self' => $role === 'INSPECTOR', 'home' => $u['home_office_id'] ?? null,
        'master' => $role === 'MASTER_ADMIN',
    ];
}
function can($perm) { $a = ua(); return $a['master'] || in_array($perm, $a['perms'], true); }
function scope_offices() { return ua()['offices']; } // 'ALL' or int[]
function scope_sbus() { return ua()['sbus']; }        // 'ALL' or string[]

// Build a WHERE fragment scoping calls/jobs by office + SBU. $officeCol is the
// column holding the executing office id (nullable → treated as Ahmedabad).
function scope_clause($officeCol, $sbuCol) {
    $off = scope_offices(); $sbu = scope_sbus();
    $w = []; $args = [];
    if ($off !== 'ALL' && is_array($off) && $off) {
        static $ahm = null;
        if ($ahm === null) $ahm = (int)(ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 LIMIT 1") ?: 0);
        $ph = implode(',', array_fill(0, count($off), '?'));
        $w[] = "COALESCE($officeCol, $ahm) IN ($ph)";
        foreach ($off as $o) $args[] = $o;
    }
    if ($sbu !== 'ALL' && is_array($sbu) && $sbu) {
        $ph = implode(',', array_fill(0, count($sbu), '?'));
        $w[] = "$sbuCol IN ($ph)";
        foreach ($sbu as $s) $args[] = $s;
    }
    return [$w ? implode(' AND ', $w) : '1=1', $args];
}

// ---- Settings (key/value) --------------------------------------------------
function ensure_settings_schema() {
    db()->exec("CREATE TABLE IF NOT EXISTS settings (skey VARCHAR(60) PRIMARY KEY, svalue TEXT)");
    // widen an older VARCHAR(255) svalue so it can hold a base64 logo (MySQL)
    if (db_driver() !== 'sqlite') { try { db()->exec("ALTER TABLE settings MODIFY svalue MEDIUMTEXT"); } catch (Throwable $e) {} }
}

// Theme <style> from settings — overrides --brand and keeps top-bar text legible.
function theme_hex($s) { $s = trim((string)$s); return preg_match('/^#[0-9a-fA-F]{6}$/', $s) ? strtolower($s) : ''; }
function theme_lum($h) { return 0.299 * hexdec(substr($h, 1, 2)) + 0.587 * hexdec(substr($h, 3, 2)) + 0.114 * hexdec(substr($h, 5, 2)); }
// Mix hex $a toward $b by fraction $t (0..1). Used to derive soft/line/muted so
// both light and dark themes stay coherent from just surface + text colours.
function theme_mix($a, $b, $t) {
    $o = '#';
    for ($i = 1; $i < 6; $i += 2) {
        $ca = hexdec(substr($a, $i, 2)); $cb = hexdec(substr($b, $i, 2));
        $o .= str_pad(dechex(max(0, min(255, (int)round($ca * (1 - $t) + $cb * $t)))), 2, '0', STR_PAD_LEFT);
    }
    return $o;
}
function theme_style_tag() {
    $primary = theme_hex(setting_get('c_primary', '')) ?: (theme_hex(setting_get('brand_color', '')) ?: '#1e40af');
    $accent  = theme_hex(setting_get('c_accent', ''))  ?: '#0ea5e9';
    $bg      = theme_hex(setting_get('c_bg', ''))       ?: '#f4f6f9';
    $surface = theme_hex(setting_get('c_surface', ''))  ?: '#ffffff';
    $ink     = theme_hex(setting_get('c_text', ''))     ?: '#1f2937';
    $fs = (int)(setting_get('font_size', '') ?: 14); if ($fs < 12 || $fs > 20) $fs = 14;
    $soft  = theme_mix($surface, $ink, 0.05);
    $line  = theme_mix($surface, $ink, 0.14);
    $muted = theme_mix($surface, $ink, 0.45);
    $navtext = theme_lum($primary) > 150 ? '#111827' : '#ffffff';
    $navlink = theme_lum($primary) > 150 ? 'rgba(17,24,39,.72)' : 'rgba(255,255,255,.82)';
    return "<style>:root{--brand:$primary;--accent:$accent;--bg:$bg;--card:$surface;--panel:$soft;--ink:$ink;--soft:$soft;--line:$line;--muted:$muted;--fs:{$fs}px}"
        . "body{font-size:var(--fs)}.stat-card,.master-card{background:var(--soft)}"
        . ".topbar .brand,.topbar .user{color:$navtext}.topbar nav a{color:$navlink}.topbar nav a:hover{color:$navtext}"
        . "</style>";
}
function logo_html() {
    $d = setting_get('logo_data', '');
    return $d ? '<img src="' . e($d) . '" alt="logo" style="height:30px;vertical-align:middle;background:#fff;border-radius:4px;padding:2px 6px">' : '';
}
function app_name() { return setting_get('app_name', '') ?: 'Inspection Ops'; }
function setting_get($k, $def = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try { foreach (ops_all("SELECT skey, svalue FROM settings") as $r) $cache[$r['skey']] = $r['svalue']; }
        catch (Throwable $e) { $cache = []; }
    }
    return array_key_exists($k, $cache) ? $cache[$k] : $def;
}
function setting_set($k, $v) {
    $pdo = db();
    if (db_driver() === 'sqlite') $pdo->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?) ON CONFLICT(skey) DO UPDATE SET svalue=excluded.svalue")->execute([$k, $v]);
    else $pdo->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)")->execute([$k, $v]);
}

// ---- Financial year (start month is a setting; default April) --------------
function fy_start_month() { $m = (int)setting_get('fy_start_month', 4); return ($m >= 1 && $m <= 12) ? $m : 4; }
// FY label for a given date, e.g. "2025-26" (Apr start) or "2025" (Jan start).
function fy_of($date) {
    $ts = strtotime($date ?: 'now'); if ($ts === false) return '';
    $y = (int)date('Y', $ts); $m = (int)date('n', $ts); $s = fy_start_month();
    $start = ($m >= $s) ? $y : $y - 1;
    return $s === 1 ? (string)$start : sprintf('%d-%02d', $start, ($start + 1) % 100);
}
function current_fy() { return fy_of(date('Y-m-d')); }
// [from,to] YYYY-MM-DD for an FY label.
function fy_range($fyLabel) {
    $s = fy_start_month();
    $start = (int)substr($fyLabel, 0, 4);
    $from = sprintf('%04d-%02d-01', $start, $s);
    $toTs = strtotime($from . ' +1 year -1 day');
    return [$from, date('Y-m-d', $toTs)];
}
// A handful of recent FY labels for the filter dropdown.
function fy_options($n = 5) {
    $out = []; $cur = current_fy();
    $startY = (int)substr($cur, 0, 4);
    for ($i = 0; $i < $n; $i++) {
        $y = $startY - $i;
        $out[] = fy_start_month() === 1 ? (string)$y : sprintf('%d-%02d', $y, ($y + 1) % 100);
    }
    return $out;
}

// ---- Migration -------------------------------------------------------------
function access_migrate() {
    ensure_settings_schema();
    ensure_column('users', 'home_office_id', 'INT NULL');
    ensure_column('users', 'scope_offices', "VARCHAR(255) DEFAULT ''");
    ensure_column('users', 'scope_sbus', "VARCHAR(255) DEFAULT ''");
    ensure_column('users', 'permissions', "VARCHAR(600) DEFAULT ''");
    ensure_column('users', 'reports_to_id', 'INT NULL');
    if (setting_get('fy_start_month') === null) setting_set('fy_start_month', '4');
    // One-time: remove the Candles/Wax/Tier demo (the confusing "Product line" field).
    if (setting_get('demo_removed') === null) {
        try {
            db()->exec("DELETE FROM custom_fields WHERE entity='call' AND field_key='product_line'");
            foreach (['tier','wax_type','product_family'] as $k) {
                $t = ops_one("SELECT id FROM lookup_types WHERE type_key=? AND is_system=0", [$k]);
                if ($t) { db()->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([$t['id']]); db()->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$t['id']]); }
            }
        } catch (Throwable $e) {}
        setting_set('demo_removed', '1');
    }
}
