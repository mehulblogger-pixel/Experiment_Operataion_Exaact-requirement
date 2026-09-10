<?php
// ============================================================================
//  EXAACT — Configurable Role Workspaces (additive, non-destructive).
//
//  The app already shows each user only the menu items and dashboard bands their
//  role/permissions allow (the choke point is can()). This layer adds, on top of
//  that, per-tenant CONFIGURATION an administrator controls with no code:
//    • a LANDING page per role — where that role opens after sign-in;
//    • a curated LAUNCHPAD per role — the handful of screens that role uses
//      every day, shown as quick-access cards on their home;
//    • a personal "set as my start page" override each user can choose.
//
//  Safety: every landing and every tile is re-checked against the user's own
//  permission-filtered menu (ops_nav_index) at render time, so a workspace can
//  never expose a screen the user is not allowed to open. Nothing configured ⇒
//  the app behaves exactly as before (dashboard for everyone).
// ============================================================================

function workspace_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    if (function_exists('ensure_column')) {
        try { ensure_column('users', 'start_route', "VARCHAR(120) DEFAULT ''"); } catch (Throwable $e) {}
    }
}

// ---- Config store (one settings row, like role_access) ---------------------
function workspace_config() {
    $raw = function_exists('setting_get') ? (string)setting_get('workspace_config', '') : '';
    $d = $raw !== '' ? json_decode($raw, true) : [];
    return is_array($d) ? $d : [];
}
function workspace_role_config($role) {
    $c = workspace_config();
    $r = $c[$role] ?? [];
    return ['landing' => (string)($r['landing'] ?? ''), 'tiles' => array_values(array_filter((array)($r['tiles'] ?? []), 'is_string'))];
}
function workspace_config_save($role, $landing, array $tiles) {
    if (!isset(ORG_ROLES[$role])) return;
    $c = workspace_config();
    $landing = trim((string)$landing);
    $tiles = array_values(array_unique(array_filter(array_map('strval', $tiles), fn($t) => trim($t) !== '')));
    if ($landing === '' && !$tiles) unset($c[$role]);
    else $c[$role] = ['landing' => $landing, 'tiles' => $tiles];
    if (function_exists('setting_set')) setting_set('workspace_config', json_encode($c));
}

// ---- Catalogue of destinations an admin can pick from ----------------------
// Built from the CURRENT user's permission-filtered menu. The configurator is
// master-only and a master sees the full menu, so this is the whole catalogue.
function workspace_catalog() {
    $out = []; $seen = [];
    if (!function_exists('ops_nav_index')) return $out;
    foreach (ops_nav_index() as $n) {
        $url = (string)($n['url'] ?? '');
        if ($url === '' || $url === '#' || ($n['kind'] ?? '') === 'action') continue;
        if (isset($seen[$url])) continue; $seen[$url] = true;
        $out[] = ['url' => $url, 'label' => (string)($n['label'] ?? $url),
                  'icon' => (string)($n['icon'] ?? '•'), 'area' => (string)($n['area'] ?? '')];
    }
    return $out;
}

// The set of routes the given (current) user is actually allowed to open.
function workspace_allowed_routes() {
    $set = [];
    if (function_exists('ops_nav_index')) foreach (ops_nav_index() as $n) { $u = (string)($n['url'] ?? ''); if ($u !== '') $set[$u] = true; }
    return $set;
}
function workspace_route_label($url) {
    foreach (workspace_catalog() as $c) if ($c['url'] === $url) return $c;
    return ['url' => $url, 'label' => $url, 'icon' => '•', 'area' => ''];
}

// ---- Resolution (per current user) -----------------------------------------
// Where should this user land on sign-in? '' means the default dashboard.
function workspace_landing_for($user) {
    if (!$user) return '';
    $allowed = workspace_allowed_routes();
    $cand = trim((string)($user['start_route'] ?? ''));            // personal override wins
    if ($cand === '') {
        $role = (string)($user['role'] ?? '');
        $cand = trim((string)(workspace_role_config($role)['landing'] ?? ''));
    }
    if ($cand === '' || $cand === '/') return '';
    return isset($allowed[$cand]) ? $cand : '';                    // never send them somewhere they can't open
}

// The quick-access tiles this user should see on their home (role tiles ∩ allowed).
function workspace_tiles_for($user) {
    if (!$user) return [];
    $role = (string)($user['role'] ?? '');
    $tiles = workspace_role_config($role)['tiles'];
    if (!$tiles) return [];
    $allowed = workspace_allowed_routes();
    $out = [];
    foreach ($tiles as $url) { if (!isset($allowed[$url])) continue; $out[] = workspace_route_label($url); }
    return $out;
}

// Save a user's personal start page (validated against what they may open).
function workspace_set_user_start($user, $route) {
    workspace_migrate();
    $route = trim((string)$route);
    if ($route !== '' && $route !== '/' && !isset(workspace_allowed_routes()[$route])) return [false, 'That screen is not available to you.'];
    if ($route === '/') $route = '';
    db()->prepare("UPDATE users SET start_route=? WHERE id=?")->execute([$route, (int)$user['id']]);
    return [true, $route === '' ? 'Start page reset to the dashboard.' : 'Start page saved.'];
}

// ---- Launchpad (rendered additively at the top of the dashboard) -----------
function workspace_launchpad_html($user) {
    $tiles = workspace_tiles_for($user);
    if (!$tiles) return '';
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $cur = trim((string)($user['start_route'] ?? ''));
    $h  = '<section class="ws-launch" style="margin:18px 0 6px">';
    $h .= '<div style="display:flex;align-items:baseline;gap:10px;margin-bottom:10px">'
        . '<h3 class="tab-sub" style="margin:0">Your workspace</h3>'
        . '<span class="muted" style="font-size:12px">quick access to the screens you use most</span></div>';
    $h .= '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px">';
    foreach ($tiles as $t) {
        $isStart = ($cur !== '' && $cur === $t['url']);
        $h .= '<a href="' . $e($t['url']) . '" style="display:flex;align-items:center;gap:11px;padding:14px 15px;border:1px solid var(--line,#e5e9f0);border-radius:13px;background:var(--card,#fff);text-decoration:none;color:inherit;box-shadow:0 1px 2px rgba(16,24,40,.04)">'
            . '<span style="font-size:20px;flex:0 0 auto">' . $e($t['icon']) . '</span>'
            . '<span style="font-weight:600;font-size:14px">' . $e($t['label']) . '</span>'
            . ($isStart ? '<span class="pill p-ok" style="margin-left:auto;font-size:9.5px">start</span>' : '')
            . '</a>';
    }
    $h .= '</div>';
    // Personal start-page control.
    $h .= '<form method="post" action="/my-start" style="display:flex;gap:8px;align-items:center;margin-top:11px;flex-wrap:wrap">';
    if (function_exists('csrf_field')) $h .= csrf_field();
    $h .= '<label class="muted" style="font-size:12px">Open on sign-in:</label><select name="start_route" class="form-control" style="width:auto;min-width:180px;padding:7px 10px">';
    $h .= '<option value="">Dashboard (default)</option>';
    foreach ($tiles as $t) $h .= '<option value="' . $e($t['url']) . '"' . ($cur === $t['url'] ? ' selected' : '') . '>' . $e($t['label']) . '</option>';
    $h .= '</select><button class="btn secondary" style="padding:7px 13px;font-size:12.5px">Save</button></form>';
    $h .= '</section>';
    return $h;
}

// ============================================================================
//  Admin screen — configure a role's landing + launchpad
// ============================================================================
function ops_role_workspaces($route, $method) {
    ops_require(function_exists('is_master') && is_master(), 'Only a master administrator can configure role workspaces.');
    workspace_migrate();
    if ($method === 'POST' && (string)($_POST['do'] ?? '') === 'save') {
        $role = (string)($_POST['role'] ?? '');
        workspace_config_save($role, (string)($_POST['landing'] ?? ''), (array)($_POST['tiles'] ?? []));
        flash('Workspace saved for ' . (ORG_ROLES[$role] ?? $role) . '.');
        redirect('/role-workspaces?role=' . urlencode($role));
        return true;
    }
    $roles = ORG_ROLES;
    $sel = (string)($_GET['role'] ?? array_key_first($roles));
    if (!isset($roles[$sel])) $sel = array_key_first($roles);
    view('ops/role_workspaces', [
        'roles'   => $roles,
        'sel'     => $sel,
        'cfg'     => workspace_role_config($sel),
        'catalog' => workspace_catalog(),
        'config'  => workspace_config(),
    ]);
    return true;
}

// Self-service: a user sets their own start page.
function ops_my_start($route, $method) {
    ops_require(function_exists('current_user') && current_user(), 'Sign in.');
    if ($method === 'POST') {
        [$ok, $msg] = workspace_set_user_start(current_user(), (string)($_POST['start_route'] ?? ''));
        current_user(true);   // refresh the cached user so the change takes effect now
        flash($msg, $ok ? 'success' : 'error');
    }
    redirect('/');
    return true;
}
