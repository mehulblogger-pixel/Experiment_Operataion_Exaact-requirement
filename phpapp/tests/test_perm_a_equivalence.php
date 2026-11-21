<?php
// ============================================================================
//  Permission foundation, batch A (owner, 21 Nov 2026: "All as recommended").
//
//  THE PROOF THAT NOBODY GAINS OR LOSES ANYTHING ON DAY ONE.
//
//  Nine Sales rights that used to ride on other ticks or on a job title became
//  switches of their own (docs/02 19r), and a person's own access is now stored
//  as differences from their role (Q11). Both are only acceptable if, on the day
//  they ship, every person can do exactly what they could do the day before.
//
//  This test is that proof. The same file ran on the code BEFORE the change
//  (commit c41919a) and wrote tests/fixtures/pre_perm_a/snapshot.json: for each
//  of 46 people — every role, branch-limited and all-branch, four custom roles,
//  and people whose own access was stored by every format the program has ever
//  written (pre-module, pre-verb, verb-era, stamped) — the answer of every gate
//  these switches touch, every one of the old rights through can(), the Sales
//  tiles they are offered and their branches. Twice: with the built-in role
//  defaults, and with a saved Roles & access grid. Then, as real sign-ins in a
//  second process, nine screens and downloads.
//
//  Here the same people are put back and every answer must be identical —
//  before the one-time move to "differences from the role", and after it.
// ============================================================================
t_section('Permission foundation A — every person keeps exactly today\'s access');

require_once __DIR__ . '/_bbv_fixture.php';
$PA_DIR = __DIR__ . '/fixtures/pre_perm_a';
$fx = json_decode((string) file_get_contents($PA_DIR . '/fixture.json'), true);
$writeTo = (string) getenv('PA_WRITE');
$snap = $writeTo === '' ? json_decode((string) @file_get_contents($PA_DIR . '/snapshot.json'), true) : null;
t_ok(is_array($fx) && ($writeTo !== '' || is_array($snap)), 'fixture and the before-snapshot are present');

$pdo = db();
$prev = ['role_access' => (string) setting_get('role_access', ''), 'custom_roles' => (string) setting_get('custom_roles', ''),
         'flag_q11' => (string) setting_get('access_user_diff_q11_applied', '')];
$tag = 'pa' . substr(md5((string) microtime(true)), 0, 4);
$ids = [];
try {
    $pdo->prepare("INSERT INTO offices (code,name,city,is_ahmedabad) VALUES (?,?,?,0)")->execute([$tag . 'M', 'PA Mumbai ' . $tag, 'Mumbai']);
    $off = ['M' => (int) $pdo->lastInsertId()];
    $pdo->prepare("INSERT INTO offices (code,name,city,is_ahmedabad) VALUES (?,?,?,0)")->execute([$tag . 'A', 'PA Pune ' . $tag, 'Pune']);
    $off['A'] = (int) $pdo->lastInsertId();
    $cr = json_decode($prev['custom_roles'], true); if (!is_array($cr)) $cr = [];
    setting_set('custom_roles', json_encode(array_merge($cr, $fx['custom_roles'])));

    $put = function () use ($fx, $off, $pdo, &$ids) {
        //  Hold the automatic one-time move while the "before" answers are taken,
        //  so they really are the answers on the old storage; it is run on purpose below.
        setting_set('access_user_diff_q11_applied', 'held by the test');
        foreach ($fx['users'] as $u) {
            $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$u['u']]);
            $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, is_superuser, home_office_id, scope_offices, permissions)
                           VALUES (?,?,?,?,1,?,?,?,?)")
                ->execute([$u['u'], 'PA', $u['u'], $u['role'], (int) $u['su'], $u['home'] ? $off[$u['home']] : null, $u['scope'], $u['perms']]);
            $ids[$u['u']] = (int) $pdo->lastInsertId();
        }
    };
    $put();
    $b = fn($v) => $v ? 1 : 0;
    $answers = function ($oldKeys) use ($fx, &$ids, $off, $b) {
        $out = [];
        foreach ($fx['users'] as $u) {
            bbv_as($ids[$u['u']]);
            $sc = scope_offices();
            $g = [
                'sx_can' => $b(sx_can()), 'see_all' => $b(sx_can_see_all()), 'targets' => $b(sxt_can_set()),
                'opp_view' => $b(opp_can_view()), 'opp_edit' => $b(opp_can_edit()), 'pipe' => $b(pipe_can()), 'pipe_view' => $b(pipe_can_view()),
                'ar' => $b(ar_can()), 'pc' => $b(pc_can()), 'pc_edit' => $b(pc_can_edit()), 'pc_approve' => $b(pc_can_approve()), 'heads' => $b(ch_can_manage()),
                'sales_settings' => $b(function_exists('sx_settings_can') ? sx_settings_can() : (is_master() || can('settings.manage'))),
                'lead_export' => $b(can('mod.leads.view') && leads_can_view() && (!function_exists('leads_can_export') || leads_can_export())),
                'deal_export' => $b(can('mod.leads.view') && opp_can_view() && (!function_exists('opp_can_export') || opp_can_export())),
                'quote_export' => $b(can('mod.quotes.view') && (!function_exists('crm_quote_can_export') || crm_quote_can_export())),
                'crmdash' => $b(crmdash_can()), 'leads_view' => $b(leads_can_view()), 'leads_edit' => $b(leads_can_edit()), 'reopen' => $b(leads_can_reopen()),
                'adv' => $b(adv_can()), 'roi' => $b(roi_can()), 'admin_level' => $b(is_admin_level()), 'master' => $b(is_master()),
                'offices' => $sc === 'ALL' ? 'ALL' : implode(',', array_map(fn($i) => array_search((int) $i, $off, true) ?: 'other', (array) $sc)),
            ];
            $tiles = [];
            foreach ((ops_area_def('sales')['sections'] ?? []) as $s) foreach ($s['tiles'] as $t) $tiles[] = $t['route'];
            $g['tiles'] = implode(' ', $tiles);
            $can = [];
            foreach ($oldKeys as $k) $can[$k] = $b(can($k));
            $out[$u['u']] = ['g' => $g, 'can' => $can];
        }
        bbv_nobody();
        return $out;
    };
    // The rights that existed before — the snapshot names them, so a right added
    // by this batch is never compared against a "before" that did not have it.
    $oldKeys = $writeTo !== '' ? array_keys(all_permissions()) : ($snap['keys'] ?? []);
    $migrate = function () { setting_set('access_user_diff_q11_applied', ''); return function_exists('access_migrate_user_diffs') ? access_migrate_user_diffs(true) : null; };
    $compare = function ($got, $want, $label) {
        $bad = [];
        foreach ($want as $user => $w) {
            $gg = $got[$user] ?? null;
            if (!$gg) { $bad[] = "$user missing"; continue; }
            foreach ($w['g'] as $k => $v) if ((string) ($gg['g'][$k] ?? '?') !== (string) $v) $bad[] = "$user · $k: before " . var_export($v, true) . ', now ' . var_export($gg['g'][$k] ?? null, true);
            foreach ($w['can'] as $k => $v) if ((int) ($gg['can'][$k] ?? -1) !== (int) $v) $bad[] = "$user · can($k): before $v, now " . ($gg['can'][$k] ?? '?');
        }
        t_ok(!$bad, $label . ($bad ? ' — ' . count($bad) . ' differences: ' . implode(' | ', array_slice($bad, 0, 12)) : ''));
    };
    $result = ['keys' => $oldKeys];
    foreach (['A' => '', 'B' => json_encode($fx['grid'])] as $sc => $grid) {
        setting_set('role_access', $grid);
        $put();
        $now = $answers($oldKeys);
        $result[$sc] = $now;
        if ($writeTo === '') {
            $compare($now, $snap[$sc], "scenario $sc (" . ($sc === 'A' ? 'built-in role defaults' : 'a saved Roles & access grid') . '): ' . count($now) . ' people, every gate and every old right — identical to before');
            $n = $migrate();
            t_ok(is_int($n) && $n > 0, "scenario $sc: the one-time move to \"differences from the role\" converted $n stored sets");
            $after = $answers($oldKeys);
            $compare($after, $snap[$sc], "scenario $sc: after the move every person still has exactly the same access");
            $stored = array_column(ops_all("SELECT username, permissions FROM users WHERE username LIKE 'pa\\_%' ESCAPE '\\'") ?: [], 'permissions', 'username');
            $legacy = array_filter($stored, fn($p) => trim((string) $p) !== '' && strpos((string) $p, '@diff') === false);
            t_ok(!$legacy, "scenario $sc: no fixture person is left on a frozen copy" . ($legacy ? ' — ' . implode(', ', array_keys($legacy)) : ''));
            if ($sc === 'B') {
                $again = $migrate();
                t_eq($again, 0, 'the move is idempotent: run again, it changes nothing');
            }
        }
    }

    // ---- As real sign-ins, through the dispatcher (scenario B, after the move) --
    $routes = [
        ['sales-settings', []], ['project-costing-heads', []], ['pipeline-new', []], ['sales-targets', []],
        ['leads', ['export' => 'csv']], ['opportunities', ['export' => 'csv', 'v' => 'board']], ['quotes', ['export' => '1']],
        ['receivables', []], ['project-costings', []], ['sales-home', ['who' => 'all']],
    ];
    $routeUsers = ['pa_business_dev_manager', 'pa_marketing_executive', 'pa_marketing_manager', 'pa_finance', 'pa_coordinator', 'pa_branch_manager',
                   'pa_business_director', 'pa_sbu_head', 'pa_operation_manager', 'pa_inspector', 'pa_admin', 'pa_pa_sales_manager',
                   'pa_me_coord', 'pa_bdm_nocredit', 'pa_fin_preverb', 'pa_kam_noleads', 'pa_master', 'pa_admin_noroute', 'pa_custom_override'];
    $outcome = function ($r) {
        $err = false; foreach ($r['flash'] ?? [] as $f) if (stripos($f, 'error') === 0) $err = true;
        $csv = !($r['view'] ?? '') && stripos((string) ($r['out'] ?? ''), ',') !== false && (int) ($r['len'] ?? 0) > 0 && !$err;
        //  Whether the targets screen offers editing is part of the answer.
        $edit = stripos((string) ($r['out'] ?? ''), 'Save targets') !== false ? '+edit' : '';
        return (int) ($r['code'] ?? 0) . '|' . ($err ? 'refused' : 'ok') . '|' . (($r['view'] ?? '') ?: ($csv ? 'csv' : 'none')) . $edit;
    };
    $rr = [];
    foreach ($routeUsers as $ru) foreach ($routes as [$route, $get]) {
        $r = bbv_route($route, 'GET', $ids[$ru], 0, [], $get);
        $rr["$ru $route"] = $outcome($r);
    }
    $result['routes'] = $rr;
    if ($writeTo === '') {
        $bad = [];
        foreach ($snap['routes'] as $k => $v) if (($rr[$k] ?? '?') !== $v) $bad[] = "$k: before $v, now " . ($rr[$k] ?? '?');
        t_ok(!$bad, count($rr) . ' screens and downloads opened as real sign-ins (' . count($routeUsers) . ' people × ' . count($routes) . ' routes) — the same outcome as before' . ($bad ? ': ' . implode(' | ', array_slice($bad, 0, 12)) : ''));
    } else {
        file_put_contents($writeTo, json_encode($result, JSON_PRETTY_PRINT));
        t_ok(true, 'snapshot written');
    }
} finally {
    bbv_nobody();
    foreach (array_keys($ids) as $un) $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("DELETE FROM offices WHERE code IN (?,?)")->execute([$tag . 'M', $tag . 'A']);
    setting_set('role_access', $prev['role_access']);
    setting_set('custom_roles', $prev['custom_roles']);
    setting_set('access_user_diff_q11_applied', $prev['flag_q11']);
}
