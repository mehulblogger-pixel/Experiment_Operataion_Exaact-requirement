<?php
// ============================================================================
//  "WHO CAN DO WHAT" — the new permission screen (batch B, owner 21 Nov 2026:
//  "All as recommended"). A new way of seeing and switching the rights that
//  already exist — so the whole test is about one promise:
//
//      THE SAME CHOICES STORE THE SAME VALUE, AND THE SAME PEOPLE GET IN.
//
//   (1) For every role and a set of people, an untouched Save on the new
//       screen stores exactly what an untouched Save on the old screen stores.
//   (2) Switching a sub-feature on the new screen stores the same value as
//       ticking the same box on the old screen.
//   (3) "What they will see" says what the real gates say, for the 46 people of
//       the Batch A snapshot (every role, branch-limited and all-branch, custom
//       roles, every stored format).
//   (4) Only the administrators who could open the old screens open this one.
//   Plus: every right drawn exactly once, nothing dropped; the shared person
//   save (user_access_from_post) and the self-lockout guard.
// ============================================================================
t_section('Who can do what — the same switches, the same storage, the same people');

require_once __DIR__ . '/_bbv_fixture.php';

//  Drive one route in a second process (redirects end a request) and read back
//  the page it drew.
function wc_route($route, $method, $uid, array $get = [], array $post = []) {
    $root = dirname(__DIR__);
    $env = (getenv('DB_DRIVER') === 'mysql')
        ? 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'))
        : 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'));
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_whocan_worker.php') . ' ' . escapeshellarg($route) . ' ' . $method . ' ' . (int) $uid
         . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post)) . ' 2>/dev/null';
    $raw = (string) shell_exec($cmd);
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['code' => 0, 'flash' => [], 'html' => '', 'view' => '', 'view_ok' => false, 'view_err' => 'no output: ' . substr($raw, -300)];
}

//  Every control of the FIRST form whose action contains $action, as the browser
//  would send it untouched: [name => value | [values]].
function wc_form_fields($html, $action) {
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>');
    libxml_clear_errors();
    $form = null;
    foreach ($doc->getElementsByTagName('form') as $f) {
        if (strtolower($f->getAttribute('method')) !== 'post') continue;
        if (strpos($f->getAttribute('action'), $action) === false) continue;
        // skip the small one-button forms (preset / reset)
        $hasBox = false;
        foreach ($f->getElementsByTagName('input') as $i) if ($i->getAttribute('type') === 'checkbox') { $hasBox = true; break; }
        if ($hasBox) { $form = $f; break; }
    }
    if (!$form) return null;
    $out = [];
    $add = function ($n, $v) use (&$out) {
        if (substr($n, -2) === '[]') { $out[substr($n, 0, -2)][] = $v; return; }
        if (preg_match('/^(\w+)\[([^\]]+)\]$/', $n, $m)) { $out[$m[1]][$m[2]] = $v; return; }
        $out[$n] = $v;
    };
    $disabled = function ($el) {
        for ($p = $el; $p && $p->nodeType === XML_ELEMENT_NODE; $p = $p->parentNode)
            if ($p->hasAttribute('disabled') && in_array(strtolower($p->nodeName), ['input', 'select', 'textarea', 'fieldset', 'button'], true)) return true;
        return false;
    };
    foreach ($form->getElementsByTagName('input') as $i) {
        $n = $i->getAttribute('name'); if ($n === '' || $disabled($i)) continue;
        $t = strtolower($i->getAttribute('type') ?: 'text');
        if (in_array($t, ['submit', 'button', 'reset', 'file', 'image'], true)) continue;
        if (in_array($t, ['checkbox', 'radio'], true) && !$i->hasAttribute('checked')) continue;
        $add($n, $i->hasAttribute('value') ? $i->getAttribute('value') : (in_array($t, ['checkbox', 'radio'], true) ? 'on' : ''));
    }
    foreach ($form->getElementsByTagName('select') as $s) {
        $n = $s->getAttribute('name'); if ($n === '' || $disabled($s)) continue;
        $val = null; $first = null;
        foreach ($s->getElementsByTagName('option') as $o) {
            $v = $o->hasAttribute('value') ? $o->getAttribute('value') : $o->textContent;
            if ($first === null) $first = $v;
            if ($o->hasAttribute('selected')) $val = $v;
        }
        $add($n, $val ?? $first ?? '');
    }
    foreach ($form->getElementsByTagName('textarea') as $t) {
        $n = $t->getAttribute('name'); if ($n === '' || $disabled($t)) continue;
        $add($n, $t->textContent);
    }
    return $out;
}

//  The permission boxes a page draws: [key => checked?] for perms[...] or permissions[].
function wc_boxes($html, $field) {
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>');
    libxml_clear_errors();
    $out = [];
    foreach ($doc->getElementsByTagName('input') as $i) {
        if ($i->getAttribute('type') !== 'checkbox') continue;
        $n = $i->getAttribute('name');
        if ($field === 'perms' && preg_match('/^perms\[([^\]]+)\]$/', $n, $m)) $k = $m[1];
        elseif ($field === 'permissions' && $n === 'permissions[]') $k = $i->getAttribute('value');
        else continue;
        $out[$k] = ($out[$k] ?? false) || $i->hasAttribute('checked');
    }
    ksort($out);
    return $out;
}

$f = bbv_fixture();
$pdo = db();
$prev = ['role_access' => (string) setting_get('role_access', ''), 'custom_roles' => (string) setting_get('custom_roles', ''),
         'flag_q11' => (string) setting_get('access_user_diff_q11_applied', '')];
$fx = json_decode((string) file_get_contents(__DIR__ . '/fixtures/pre_perm_a/fixture.json'), true);
$made = []; $madeIns = [];
$snapRow = fn($id) => ops_one("SELECT * FROM users WHERE id=?", [(int) $id]);
$putRow = function (array $row) use ($pdo) {
    $cols = array_keys($row); $set = implode(',', array_map(fn($c) => "$c=?", $cols));
    $pdo->prepare("UPDATE users SET $set WHERE id=?")->execute(array_merge(array_values($row), [(int) $row['id']]));
};
$stored = fn($id) => ($r = ops_one("SELECT permissions, scope_offices, scope_sbus FROM users WHERE id=?", [(int) $id]))
    ? [(string) $r['permissions'], (string) $r['scope_offices'], (string) $r['scope_sbus']] : null;
try {
    $master = $f['master'];
    $tag = $f['tag'];

    // ---- Smoke: every tab draws for the Master Admin ----------------------------------
    foreach (['roles', 'people', 'compare', 'switches', 'plan'] as $tab) {
        $r = wc_route('who-can', 'GET', $master, ['tab' => $tab]);
        t_ok($r['view'] === 'ops/whocan' && $r['view_ok'] && strpos($r['html'], 'data-tab="' . $tab . '"') !== false,
             "screen · the $tab tab opens for the Master Admin" . ($r['view_err'] ? ' — ' . $r['view_err'] : ''));
    }

    // ---- Every right drawn exactly once; nothing dropped ---------------------------------
    $rk = whocan_role_keys(); sort($rk);
    $tk = whocan_tree_keys(whocan_tree($rk));
    t_eq(count($tk), count(array_unique($tk)), 'tree · no right is drawn twice on the role tab');
    $tks = array_values(array_unique($tk)); sort($tks);
    t_eq($tks, $rk, 'tree · the role tab draws exactly the ' . count($rk) . ' rights the old Roles screen draws');
    foreach ([true => 'a company-wide user manager', false => 'a branch user manager'] as $g => $who) {
        $pk = whocan_person_keys((bool) $g); sort($pk);
        $tk = whocan_tree_keys(whocan_tree($pk)); $u = array_values(array_unique($tk)); sort($u);
        t_ok(count($tk) === count($u) && $u === $pk, "tree · the people tab draws exactly the " . count($pk) . " rights $who may hand out, once each");
    }
    $sales = whocan_tree($rk)['Sales'] ?? [];
    $feats = array_column($sales, 'f');
    t_ok(in_array('Deals', $feats, true) && in_array('Project costing', $feats, true) && in_array('Customer money in Sales', $feats, true) && in_array('Sales settings', $feats, true),
         'tree · Sales is laid out as approved: Deals, Project costing, Customer money and Sales settings are features of their own');
    $dec = 0; foreach ($sales as $ft) foreach ($ft['rows'] as $r) if ($r['type'] === 'decision') $dec++;
    //  Q10 was decided and built on 21 Nov (docs/02 19z): nothing waits for the owner any more.
    t_eq($dec, 0, 'tree · no row waits for the owner (question 10 was decided and built, 21 Nov)');

    // ---- (1) Roles: the new screen draws the same boxes, ticked the same way --------------
    $cr = json_decode($prev['custom_roles'], true); if (!is_array($cr)) $cr = [];
    setting_set('custom_roles', json_encode(array_merge($cr, $fx['custom_roles'])));
    $rolesBuiltIn = array_keys(array_filter(whocan_roles(), fn($l, $k) => isset(ORG_ROLES[$k]), ARRAY_FILTER_USE_BOTH));
    $sameRoles = []; $diffRoles = [];
    foreach ([['', 'built-in defaults'], [json_encode($fx['grid']), 'a saved Roles & access grid']] as [$grid, $how]) {
        setting_set('role_access', $grid);
        foreach ($rolesBuiltIn as $role) {
            $old = wc_boxes(wc_route('access', 'GET', $master, ['role' => $role])['html'], 'perms');
            $new = wc_boxes(wc_route('who-can', 'GET', $master, ['tab' => 'roles', 'role' => $role])['html'], 'perms');
            if ($old && $old === $new) $sameRoles[] = "$role ($how)"; else $diffRoles[] = "$role ($how): " . json_encode(array_diff_assoc($old, $new) + array_diff_assoc($new, $old));
        }
    }
    t_ok(!$diffRoles, '(1) roles · every role draws the same boxes ticked the same way on both screens — ' . count($sameRoles) . ' role screens compared' . ($diffRoles ? ': ' . implode(' | ', array_slice($diffRoles, 0, 3)) : ''));

    // ---- (1)+(2) Roles: the same Save stores the same value --------------------------------
    setting_set('role_access', json_encode($fx['grid']));
    //  A one-time owner rule still pending (e.g. ADD-CUSTOMER-SWITCH-2026-11-22) would be
    //  applied by whichever request comes first and so change only ONE of the two saves
    //  compared below. Apply what is pending first, so both screens start from one state.
    if (function_exists('access_apply_owner_grants')) access_apply_owner_grants(true);
    if (function_exists('access_apply_owner_revokes')) access_apply_owner_revokes(true);
    $grid0 = (string) setting_get('role_access', '');
    $saveRole = function ($fields) use ($master) { wc_route('access', 'POST', $master, [], $fields); return (string) setting_get('role_access', ''); };
    $flip = function ($fields, $key, $field) {
        if ($field === 'perms') { if (isset($fields['perms'][$key])) unset($fields['perms'][$key]); else $fields['perms'][$key] = '1'; return $fields; }
        $l = (array) ($fields['permissions'] ?? []);
        $fields['permissions'] = in_array($key, $l, true) ? array_values(array_diff($l, [$key])) : array_merge($l, [$key]);
        return $fields;
    };
    $roleCases = ['MARKETING_EXECUTIVE' => ['', 'mod.leads.delete', 'crm.quote.send', 'crm.quote.approve', 'mod.leads.view', 'crm.deal.export'],
                  'BRANCH_MANAGER' => ['', 'crm.sales.see_all', 'mod.calls.archive', 'data.customer_money'],
                  'FINANCE' => ['', 'costing.heads.manage', 'mod.invoicing.edit']];
    $ok = 0; $bad = [];
    foreach ($roleCases as $role => $toggles) {
        $oldF = wc_form_fields(wc_route('access', 'GET', $master, ['role' => $role])['html'], '/access');
        $newF = wc_form_fields(wc_route('who-can', 'GET', $master, ['tab' => 'roles', 'role' => $role])['html'], '/access');
        t_ok(is_array($oldF) && is_array($newF) && ($newF['_back'] ?? '') === 'who' && ($newF['role'] ?? '') === $role,
             "(1) roles · $role: the new screen posts to the same Roles handler, for the same role");
        foreach ($toggles as $k) {
            setting_set('role_access', $grid0); ua(true);
            $a = $saveRole($k === '' ? $oldF : $flip($oldF, $k, 'perms'));
            setting_set('role_access', $grid0); ua(true);
            $b = $saveRole($k === '' ? $newF : $flip($newF, $k, 'perms'));
            $sa = json_decode($a, true)[$role] ?? null; $sb = json_decode($b, true)[$role] ?? null;
            if ($sa !== null && $sa === $sb) $ok++; else $bad[] = "$role " . ($k ?: 'untouched');
        }
    }
    t_ok(!$bad, "(1)(2) roles · untouched saves and switched sub-features store the identical value through both screens — $ok saves compared" . ($bad ? ': ' . implode(', ', $bad) : ''));
    setting_set('role_access', $prev['role_access']);

    // ---- The 41 people of the Batch A snapshot ---------------------------------------------
    setting_set('access_user_diff_q11_applied', 'held by the test');
    $off = ['M' => $f['mum'], 'A' => $f['ahm']];
    $ids = [];
    foreach ($fx['users'] as $u) {
        $uname = $tag . '_' . $u['u'];
        $pdo->prepare("INSERT INTO inspectors (name, home_office_id, status) VALUES (?,?, 'ACTIVE')")->execute(['WC ' . $u['u'] . ' ' . $tag, $u['home'] ? $off[$u['home']] : null]);
        $insId = (int) $pdo->lastInsertId(); $madeIns[] = $insId;
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, is_superuser, home_office_id, scope_offices, permissions, inspector_id)
                       VALUES (?,?,?,?,1,?,?,?,?,?)")
            ->execute([$uname, 'WC', $u['u'], $u['role'], (int) $u['su'], $u['home'] ? $off[$u['home']] : null, $u['scope'], $u['perms'], $insId]);
        $ids[$u['u']] = (int) $pdo->lastInsertId(); $made[] = $ids[$u['u']];
    }
    access_migrate_user_diffs(true);   // as on a live site: every frozen copy moved to "role + differences"

    // (1) People: the same boxes and branches, ticked the same way ------------------------------
    $samePeople = 0; $diffPeople = [];
    $scopeOf = function ($fields) {
        $o = $fields ?: [];
        $l = fn($x) => is_array($x) ? array_values($x) : [];
        $so = $l($o['scope_offices'] ?? []); sort($so); $ss = $l($o['scope_sbus'] ?? []); sort($ss);
        return [isset($o['scope_offices_all']), $so, isset($o['scope_sbus_all']), $ss];
    };
    foreach ($fx['users'] as $u) {
        if ($u['su']) continue;
        $id = $ids[$u['u']];
        $oh = wc_route('user-edit', 'GET', $master, ['id' => $id])['html'];
        $nh = wc_route('who-can', 'GET', $master, ['tab' => 'people', 'id' => $id])['html'];
        $ob = wc_boxes($oh, 'permissions'); $nb = wc_boxes($nh, 'permissions');
        $os = $scopeOf(wc_form_fields($oh, '/user-edit')); $ns = $scopeOf(wc_form_fields($nh, '/who-can-person'));
        if ($ob && $ob === $nb && $os === $ns) $samePeople++;
        else $diffPeople[] = $u['u'] . (($ob !== $nb) ? ' boxes ' . json_encode(array_diff_assoc($ob, $nb) + array_diff_assoc($nb, $ob)) : ' branches');
    }
    t_ok(!$diffPeople, "(1) people · every person draws the same switches and branches, set the same way, as on the person form — $samePeople people compared"
         . ($diffPeople ? ': ' . implode(' | ', array_slice($diffPeople, 0, 3)) : ''));

    // (1)+(2) People: the same Save stores the same value ------------------------------------------
    $personCases = [
        'pa_marketing_executive' => ['', 'crm.quote.send', 'mod.leads.delete', 'crm.lead.export'],
        'pa_business_dev_manager' => ['', 'data.customer_money', 'crm.sales.see_all'],
        'pa_branch_manager' => ['', 'mod.calls.delete'],
        'pa_finance' => ['', 'costing.view'],
        'pa_pa_field_sales' => [''], 'pa_custom_override' => ['', 'crm.quote.send'],
        'pa_kam_noleads' => [''], 'pa_bdm_nocredit' => [''], 'pa_me_coord' => [''], 'pa_om_premod' => [''], 'pa_fin_preverb' => [''],
    ];
    $ok = 0; $bad = []; $missing = [];
    foreach ($personCases as $key => $toggles) {
        if (!isset($ids[$key])) { $missing[] = $key; continue; }
        $id = $ids[$key]; $row0 = $snapRow($id);
        $oldF = wc_form_fields(wc_route('user-edit', 'GET', $master, ['id' => $id])['html'], '/user-edit');
        $newF = wc_form_fields(wc_route('who-can', 'GET', $master, ['tab' => 'people', 'id' => $id])['html'], '/who-can-person');
        foreach ($toggles as $k) {
            $putRow($row0);
            wc_route('user-edit', 'POST', $master, ['id' => $id], $k === '' ? $oldF : $flip($oldF, $k, 'permissions'));
            $a = $stored($id);
            $putRow($row0);
            wc_route('who-can-person', 'POST', $master, ['id' => $id], $k === '' ? $newF : $flip($newF, $k, 'permissions'));
            $b = $stored($id);
            if ($a !== null && $a === $b) $ok++; else $bad[] = "$key " . ($k ?: 'untouched') . ' old=' . json_encode($a) . ' new=' . json_encode($b);
        }
        $putRow($row0);
    }
    // Branches: give one person a second branch on both screens.
    $id = $ids['pa_marketing_executive']; $row0 = $snapRow($id);
    $oldF = wc_form_fields(wc_route('user-edit', 'GET', $master, ['id' => $id])['html'], '/user-edit');
    $newF = wc_form_fields(wc_route('who-can', 'GET', $master, ['tab' => 'people', 'id' => $id])['html'], '/who-can-person');
    $addOff = function ($F) use ($f) { $F['scope_offices'] = array_values(array_unique(array_merge((array) ($F['scope_offices'] ?? []), [(string) $f['mum'], (string) $f['ahm']]))); unset($F['scope_offices_all']); return $F; };
    $putRow($row0); wc_route('user-edit', 'POST', $master, ['id' => $id], $addOff($oldF)); $a = $stored($id);
    $putRow($row0); wc_route('who-can-person', 'POST', $master, ['id' => $id], $addOff($newF)); $b = $stored($id);
    if ($a === $b && strpos((string) $a[1], (string) $f['ahm']) !== false) $ok++; else $bad[] = 'branches old=' . json_encode($a) . ' new=' . json_encode($b);
    $putRow($row0);
    t_ok(!$bad && !$missing, "(1)(2) people · untouched saves, switched sub-features and a changed branch store the identical value through both screens — $ok saves compared"
         . ($bad ? ': ' . implode(' | ', array_slice($bad, 0, 2)) : '') . ($missing ? ' (missing fixture people: ' . implode(', ', $missing) . ')' : ''));

    // A branch user manager: the same subset of rights, the same storage.
    bbv_as($f['kiran']);
    $kiranPeople = can('users.manage.branch') && !can('users.manage.global');
    bbv_as($master);
    if ($kiranPeople) {
        $id = $ids['pa_coordinator'] ?? $ids['pa_marketing_executive'];
        $row0 = $snapRow($id);
        $oh = wc_route('user-edit', 'GET', $f['kiran'], ['id' => $id])['html']; $nh = wc_route('who-can', 'GET', $f['kiran'], ['tab' => 'people', 'id' => $id])['html'];
        t_ok(wc_boxes($oh, 'permissions') && wc_boxes($oh, 'permissions') === wc_boxes($nh, 'permissions'),
             '(1) people · a branch user manager (Branch Manager, Mumbai) sees the same subset of switches on both screens');
        $oldF = wc_form_fields($oh, '/user-edit'); $newF = wc_form_fields($nh, '/who-can-person');
        $putRow($row0); wc_route('user-edit', 'POST', $f['kiran'], ['id' => $id], $flip($oldF, 'ops.job.close', 'permissions')); $a = $stored($id);
        $putRow($row0); wc_route('who-can-person', 'POST', $f['kiran'], ['id' => $id], $flip($newF, 'ops.job.close', 'permissions')); $b = $stored($id);
        $putRow($row0);
        t_ok($a !== null && $a === $b, '(2) people · a branch user manager switching "Close jobs" stores the same value through both screens');
    } else {
        t_ok(true, '(1) people · (the Branch Manager here is not a branch-only user manager — branch-manager comparison not applicable)');
    }

    // ---- (3) What they will see = what the real gates say ---------------------------------
    bbv_as($master);
    $lineGate = [0 => 'sx_can', 1 => 'leads_can_view', 2 => 'opp_can_view', 5 => 'pc_can', 6 => 'ar_can', 7 => 'sxt_can_set', 8 => 'pipe_can', 9 => 'sx_settings_can'];
    $agree = 0; $disagree = [];
    foreach ($fx['users'] as $u) {
        $id = $ids[$u['u']];
        $row = $snapRow($id);
        bbv_as($master);
        $lines = whocan_preview_user($row);
        $stillMaster = is_master() && (int) current_user()['id'] === (int) $master;
        bbv_as($id);
        if ($u['su']) { $ok1 = count($lines) === 1 && $lines[0][0] === true && is_master(); }
        else {
            $ok1 = $stillMaster;
            foreach ($lineGate as $i => $fn) if (($lines[$i][0] ?? null) !== (bool) $fn()) { $ok1 = false; $disagree[] = $u['u'] . " line $i ($fn)"; }
            if (($lines[3][0] ?? null) !== can('mod.inquiries.view')) { $ok1 = false; $disagree[] = $u['u'] . ' enquiries'; }
            if (($lines[4][0] ?? null) !== can('mod.quotes.view')) { $ok1 = false; $disagree[] = $u['u'] . ' quotations'; }
            if (sx_can() && (strpos($lines[0][1], "everyone's lists") !== false) !== sx_can_see_all()) { $ok1 = false; $disagree[] = $u['u'] . ' see-all words'; }
            $sc = scope_offices();
            $offLine = $lines[10][1] ?? '';
            if ($sc === 'ALL' ? strpos($offLine, 'every') === false : ($sc ? strpos($offLine, 'every') !== false : ($lines[10][0] ?? true) !== false)) { $ok1 = false; $disagree[] = $u['u'] . ' branches'; }
            $i = 11;
            foreach (access_module_areas() as $area => $mods) {
                if ($area === 'Sales') continue;
                $any = false; foreach ($mods as $m) if (isset(ACCESS_MODULES[$m]) && can("mod.$m.view")) $any = true;
                if (($lines[$i][0] ?? null) !== $any) { $ok1 = false; $disagree[] = $u['u'] . " area $area"; }
                $i++;
            }
        }
        if ($ok1) $agree++;
    }
    bbv_as($master);
    t_ok(!$disagree, '(3) preview · "What they will see" agrees with the real gates for all ' . $agree . ' of ' . count($fx['users']) . ' people of the Batch A snapshot, and the administrator stays signed in as themself'
         . ($disagree ? ': ' . implode(', ', array_slice(array_unique($disagree), 0, 6)) : ''));

    // A person who follows their role exactly sees what the role preview says.
    $agreeR = 0; $badR = [];
    foreach ($rolesBuiltIn as $role) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, permissions) VALUES (?,?,?,?,1,?,?,?)")
            ->execute([$tag . '_rp_' . strtolower($role), 'WC', $role, $role, $f['mum'], 'ALL', '']);
        $pid = (int) $pdo->lastInsertId(); $made[] = $pid;
        bbv_as($master);
        $rl = whocan_preview_role($role, whocan_role_set($role));
        $pl = whocan_preview_user($snapRow($pid));
        $same = true; foreach (range(0, 9) as $i) if (($rl[$i][0] ?? null) !== ($pl[$i][0] ?? null)) { $same = false; $badR[] = "$role line $i"; }
        if ($same) $agreeR++;
    }
    t_ok(!$badR, "(3) preview · for every role, the role's preview says what a person following it exactly is told — $agreeR roles" . ($badR ? ': ' . implode(', ', array_slice($badR, 0, 5)) : ''));

    // ---- (4) The same administrators get in -------------------------------------------------
    $target = $ids['pa_coordinator'] ?? $ids['pa_marketing_executive'];
    $who = ['Master Admin' => $master, 'Admin' => $f['admin'], 'Branch Manager' => $f['kiran'], 'Coordinator' => $f['neha'],
            'Finance (Mumbai)' => $f['meera'], 'Business Director' => $f['bd'], 'Marketing Executive' => $ids['pa_marketing_executive']];
    $gateBad = [];
    foreach ($who as $label => $uid) {
        $oldR = wc_route('access', 'GET', $uid, ['role' => 'COORDINATOR']); $newR = wc_route('who-can', 'GET', $uid, ['tab' => 'roles']);
        $oldRoles = $oldR['view'] === 'ops/access' && $oldR['view_ok'];
        $newRoles = $newR['view'] === 'ops/whocan' && strpos($newR['html'], 'data-tab="roles"') !== false;
        $oldP = wc_route('user-edit', 'GET', $uid, ['id' => $target]); $newP = wc_route('who-can', 'GET', $uid, ['tab' => 'people', 'id' => $target]);
        $oldPeople = $oldP['view'] === 'ops/user_form' && $oldP['view_ok'];
        $newPeople = $newP['view'] === 'ops/whocan' && strpos($newP['html'], 'action="/who-can-person?id=' . $target . '"') !== false;
        if ($oldRoles !== $newRoles) $gateBad[] = "$label roles old=" . (int) $oldRoles . ' new=' . (int) $newRoles;
        if ($oldPeople !== $newPeople) $gateBad[] = "$label people old=" . (int) $oldPeople . ' new=' . (int) $newPeople;
    }
    t_ok(!$gateBad, '(4) gates · for ' . count($who) . ' kinds of user, the Roles tab opens exactly when the old Roles screen does, and a person opens exactly when the person form does'
         . ($gateBad ? ': ' . implode('; ', $gateBad) : ''));
    $row0 = $snapRow($target);
    wc_route('who-can-person', 'POST', $ids['pa_marketing_executive'], ['id' => $target], ['permissions' => ['crm.quote.send']]);
    t_eq($stored($target), [(string) $row0['permissions'], (string) $row0['scope_offices'], (string) $row0['scope_sbus']],
         '(4) gates · a Marketing Executive posting to the person save changes nothing');
    $putRow($row0);

    // ---- History, custom roles, the self-lockout guard --------------------------------------
    $id = $ids['pa_marketing_executive']; $row0 = $snapRow($id);
    $newF = wc_form_fields(wc_route('who-can', 'GET', $master, ['tab' => 'people', 'id' => $id])['html'], '/who-can-person');
    wc_route('who-can-person', 'POST', $master, ['id' => $id], $flip($newF, 'crm.quote.send', 'permissions'));
    $h = whocan_history('user', $id);
    t_ok($h && strpos($h[0]['what'], 'Send') !== false, 'history · a save on the new screen is on the audit trail and shown in words ("' . ($h[0]['what'] ?? '') . '")');
    $putRow($row0);
    $r = wc_route('who-can', 'GET', $master, ['tab' => 'roles', 'role' => 'PA_FIELD_SALES']);
    t_ok(strpos($r['html'], '<fieldset disabled') !== false && strpos($r['html'], 'uses the switches of') !== false,
         'custom role · a role the company added shows its base role\'s switches, locked, with a link to change them there (the old screen accepted a save that changed nothing)');
    t_ok(user_access_keeps_user_mgmt('@diff,+crm.quote.send', 'BRANCH_MANAGER') === in_array('users.manage.branch', role_perms('BRANCH_MANAGER'), true)
         && !user_access_keeps_user_mgmt('@diff,-users.manage.branch,-users.manage.global', 'BRANCH_MANAGER'),
         'self-lockout · the guard reads what a stored value GRANTS ("@diff" form), not its text');
} finally {
    setting_set('role_access', $prev['role_access']);
    setting_set('custom_roles', $prev['custom_roles']);
    setting_set('access_user_diff_q11_applied', $prev['flag_q11']);
    foreach ($made as $id) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    foreach ($madeIns as $id) $pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$id]);
    try { $pdo->exec("DELETE FROM inspectors WHERE name LIKE 'WC %'"); } catch (Throwable $e) {}
    bbv_cleanup($f);
}
