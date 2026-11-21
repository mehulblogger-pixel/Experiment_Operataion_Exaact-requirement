<?php
// Q10 — whose records a person sees (owner, 21 Nov 2026: "Keep this"), and the
// owner's Sales-manager rule ("Sales manager can see only their own list on sales
// home and today is ok. But they can also see details of his reportee.").
//
//   Own    only the records they own
//   Team   theirs and those of everyone below them in "Reports to"
//   Branch everything in their branches (and units) — every register's rule before Q10
//   All    the whole company = Branch with "All branches"
//
// Proved here:
//   1. DAY ONE — nothing saved: every role, every person (branch-limited, all-branch,
//      unit-limited, custom role) gets exactly the clause the registers built before
//      Q10, and opens exactly what they opened before.
//   2. Own / Team / Branch on leads, deals, inquiries, quotations, orders, Sales home,
//      Today, the board and the dashboard — and on opening one record by its address.
//   3. A person's own choice beats the role's; "All" sets their branches to All.
//   4. Sales home / Today: "My team" picker — self + reportees only; never "Everyone".
//   5. Saving on "Who can do what" stores the choice (role and person) and writes the history.
t_section('Q10 — whose records a person sees (own / team / branch / all) and the Sales manager\'s team picker');

require_once __DIR__ . '/_bbv_fixture.php';

if (!function_exists('q10_route')) {
    function q10_route($route, $method, $uid, array $get = [], array $post = []) {
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
    //  Every control of the first form whose action contains $action, as a browser sends it untouched.
    function q10_form_fields($html, $action) {
        $doc = new DOMDocument(); libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>'); libxml_clear_errors();
        $out = [];
        foreach ($doc->getElementsByTagName('form') as $f) {
            if (strpos($f->getAttribute('action'), $action) === false || strtolower($f->getAttribute('method')) !== 'post' || $f->getAttribute('id') !== 'wc-form') continue;
            $xp = new DOMXPath($doc);
            foreach ($xp->query('.//input|.//select|.//textarea', $f) as $el) {
                $n = $el->getAttribute('name'); if ($n === '' || $el->hasAttribute('disabled')) continue;
                $fs = $el->parentNode; $dis = false;
                while ($fs && $fs !== $f) { if ($fs->nodeName === 'fieldset' && $fs instanceof DOMElement && $fs->hasAttribute('disabled')) { $dis = true; break; } $fs = $fs->parentNode; }
                if ($dis) continue;
                $tag = strtolower($el->nodeName); $type = strtolower($el->getAttribute('type'));
                if ($tag === 'input' && in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) continue;
                if ($tag === 'input' && in_array($type, ['submit', 'button', 'reset'], true)) continue;
                if ($tag === 'select') { $v = ''; foreach ($el->getElementsByTagName('option') as $o) { if ($o->hasAttribute('selected')) { $v = $o->getAttribute('value'); break; } }
                    if ($v === '' && $el->getElementsByTagName('option')->length && !$el->getElementsByTagName('option')->item(0)->hasAttribute('selected')) {
                        $first = $el->getElementsByTagName('option')->item(0); $v = $first->getAttribute('value');
                        foreach ($el->getElementsByTagName('option') as $o) if ($o->hasAttribute('selected')) $v = $o->getAttribute('value'); }
                } else $v = $tag === 'textarea' ? $el->textContent : $el->getAttribute('value');
                if (substr($n, -2) === '[]') { $out[substr($n, 0, -2)][] = $v; continue; }
                if (preg_match('/^([^\[]+)\[([^\]]+)\]$/', $n, $m)) { $out[$m[1]][$m[2]] = $v; continue; }
                $out[$n] = $v;
            }
            break;
        }
        return $out;
    }
}

//  The clause leads and deals were limited by BEFORE Q10 (21 Nov, a83effc) — copied verbatim.
$oldSalesClause = function ($alias) {
    [$w, $a] = scope_office_clause("$alias.office_id");
    $units = scope_sales_unit_list();
    if ($units === null) return [$w, $a];
    $q = implode(',', array_map(fn($u) => db()->quote($u), $units));
    return ["($w) AND (COALESCE($alias.sbu,'') = '' OR $alias.sbu IN ($q))", $a];
};

$f = bbv_fixture(); $pdo = db();
$recBefore = (string) setting_get('sales_records_mode', '');
$roleAccessBefore = (string) setting_get('role_access', '');
$made = ['u' => [], 'l' => [], 'o' => [], 'i' => [], 'q' => []];
try {
    bbv_as($f['master']); leads_migrate(); opp_migrate(); if (function_exists('crm_migrate')) crm_migrate();
    setting_set('sales_records_mode', '');
    $mum = $f['mum'];
    $mk = function ($key, $role, $office, $reportsTo = null, $scope = '') use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, reports_to_id) VALUES (?,?,?,?,1,?,?,?)")
            ->execute([$f['tag'] . '_q' . $key, 'Q10' . ucfirst($key), 'T', $role, $office, $scope, $reportsTo]);
        $id = (int) $pdo->lastInsertId(); $made['u'][] = $id; return $id;
    };
    //  Mgr (Marketing Manager, the owner's "Sales manager") ← Asha (BDM) ← Bala (KAM); Chetan (KAM) reports to nobody.
    $M = $mk('mgr', 'MARKETING_MANAGER', $mum);
    $A = $mk('asha', 'BUSINESS_DEV_MANAGER', $mum, $M);
    $B = $mk('bala', 'KEY_ACCOUNTS_MANAGER', $mum, $A);
    $C = $mk('chetan', 'KEY_ACCOUNTS_MANAGER', $mum);
    $owners = ['M' => $M, 'A' => $A, 'B' => $B, 'C' => $C, '-' => null];

    //  One lead, one deal, one inquiry, one accepted quotation per owner (and one with nobody), all Mumbai.
    $pipe = pipeline_default('OPPORTUNITY'); $tag = $f['tag'];
    $acc = date('Y-m-d');
    foreach ($owners as $k => $uid) {
        $lid = (int) lead_create(['company_name' => "Q10L-$k-$tag", 'contact_phone' => '9825011111', 'source' => 'REFERRAL', 'value' => 100000])['id'];
        $pdo->prepare("UPDATE leads SET owner_user_id=?, office_id=?, next_action_on=? WHERE id=?")->execute([$uid, $mum, $acc, $lid]); $made['l'][$k] = $lid;
        $oid = (int) opp_create(['name' => "Q10D-$k-$tag", 'partner_name' => 'Q10 Co', 'value' => 200000, 'pipeline_id' => $pipe['id']])['id'];
        $pdo->prepare("UPDATE opportunities SET owner_user_id=?, office_id=?, next_action_on=? WHERE id=?")->execute([$uid, $mum, $acc, $oid]); $made['o'][$k] = $oid;
        $pdo->prepare("INSERT INTO crm_inquiries (subject, client_name, office_id, sbu, assigned_to, status, received_date, created_at) VALUES (?,?,?,?,?, 'OPEN', ?, ?)")
            ->execute(["Q10I-$k-$tag", 'Q10 Co', $mum, '', $uid, $acc, date('c')]); $made['i'][$k] = (int) $pdo->lastInsertId();
        $u = $uid ? ops_one("SELECT username FROM users WHERE id=?", [$uid]) : null;
        //  Half the quotations carry owner_id, half only created_by (the older rows' form).
        $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, office_id, sbu, owner_id, created_by, client_name, subject, subtotal, total_amount, accepted_date, created_at, updated_at)
                       VALUES (?,0,1,'ACCEPTED',?,'',?,?,?,?,?,?,?,?,?)")
            ->execute(["Q10Q-$k-$tag", $mum, in_array($k, ['M', 'B'], true) ? $uid : null, $u ? $u['username'] : '', 'Q10 Co', "Q10Q-$k-$tag", 50000, 59000, $acc, date('c'), date('c')]);
        $made['q'][$k] = (int) $pdo->lastInsertId();
    }
    $setRole = function ($role, $mode) { $st = sales_rec_store(); if ($mode === 'branch') unset($st['roles'][$role]); else $st['roles'][$role] = $mode; sales_rec_store_put($st); };
    $setUser = function ($uid, $mode) { $st = sales_rec_store(); if ($mode === '') unset($st['users'][(string) $uid]); else $st['users'][(string) $uid] = $mode; sales_rec_store_put($st); };
    $seen = function ($uid) use ($made) {
        bbv_as($uid);
        $L = array_map('intval', array_column(leads_all() ?: [], 'id'));
        $O = array_map('intval', array_column(opp_all() ?: [], 'id'));
        [$qw, $qa] = scope_quote_clause('q.office_id', 'q.sbu');
        $Q = array_map('intval', array_column(ops_all("SELECT q.id FROM quotations q WHERE $qw", $qa) ?: [], 'id'));
        $I = array_values(array_filter(array_map('intval', array_column(ops_all("SELECT * FROM crm_inquiries") ?: [], 'id')), fn($id) => crm_inquiry_in_scope(crm_inquiry_get($id))));
        $R = array_map('intval', array_column(sx_orders(0, date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+1 day'))) ?: [], 'id'));
        [$hl, $hd] = sx_open_items(sx_person());   // Sales home / Today: "Me" (no ?who)
        $out = [];
        foreach (['l' => $L, 'o' => $O, 'q' => $Q, 'i' => $I, 'r' => $R, 'hl' => array_map('intval', array_column($hl, 'id')), 'hd' => array_map('intval', array_column($hd, 'id'))] as $kind => $ids) {
            $src = $kind === 'r' ? $made['q'] : ($kind === 'hl' ? $made['l'] : ($kind === 'hd' ? $made['o'] : $made[$kind]));
            $who = []; foreach ($src as $k => $id) if (in_array($id, $ids, true)) $who[] = $k;
            sort($who); $out[$kind] = implode('', $who);
        }
        return $out;
    };
    $all5 = '-ABCM';   // sorted: '-' < 'A' < 'B' < 'C' < 'M'

    // ---- 1. Day one: nothing saved = exactly the old rule, for every role and person ----------
    $people = [];
    foreach (['bd', 'kiran', 'sunil', 'neha', 'co_ahm', 'am', 'meera', 'rakesh', 'admin', 'sbu', 'master'] as $k) if (isset($f[$k])) $people[$k] = $f[$k];
    $people += ['mgr' => $M, 'asha' => $A, 'bala' => $B, 'chetan' => $C];
    foreach ((function_exists('roles_for_licence') ? array_keys(roles_for_licence()) : array_keys(ORG_ROLES)) as $role) {
        $people['role:' . $role . ':mum'] = $mk('r' . strtolower($role) . 'm', $role, $mum);
        $people['role:' . $role . ':all'] = $mk('r' . strtolower($role) . 'a', $role, $mum, null, 'ALL');
    }
    //  A unit-limited all-branch person (the SBU Head shape) and a custom-role person.
    $pdo->prepare("UPDATE users SET scope_offices='ALL', scope_sbus='INDUSTRIAL' WHERE id=?")->execute([$people['role:SBU_HEAD:all'] ?? $f['sbu']]);
    $diffClause = []; $diffIds = [];
    foreach ($people as $k => $uid) {
        bbv_as($uid);
        if (sales_rec_ids() !== null) { $diffIds[] = $k; continue; }
        foreach (['l', 'o'] as $al) if (scope_sales_clause($al) !== $oldSalesClause($al)) $diffClause[] = "$k/$al";
        if (scope_quote_clause('q.office_id', 'q.sbu') !== scope_clause('q.office_id', 'q.sbu')) $diffClause[] = "$k/q";
        if (sales_owner_allows(987654) !== true) $diffClause[] = "$k/allows";
    }
    t_eq($diffIds, [], 'day one · nothing saved → no owner limit for any of ' . count($people) . ' people (every role, branch-limited and all-branch, unit-limited, management, master)');
    t_eq($diffClause, [], 'day one · the lead, deal and quotation clauses are character-for-character the ones built before Q10, for every person');
    t_eq($seen($M), ['l' => $all5, 'o' => $all5, 'q' => $all5, 'i' => $all5, 'r' => $all5, 'hl' => 'M', 'hd' => 'M'],
         'day one · Marketing Manager: registers show all of Mumbai (as before); Sales home "Me" shows only their own (as before)');
    t_eq(sales_rec_mode_for(ops_one("SELECT * FROM users WHERE id=?", [$f['master']])), 'branch', 'day one · the Master Admin is never limited');

    // ---- 2. Team / Own / Branch for a role -------------------------------------------------------
    $setRole('MARKETING_MANAGER', 'team');
    t_eq($seen($M), ['l' => 'ABM', 'o' => 'ABM', 'q' => 'ABM', 'i' => 'ABM', 'r' => 'ABM', 'hl' => 'M', 'hd' => 'M'],
         'Team · the manager sees their own and their whole line (Asha, and Bala below her) — not Chetan, not unowned — on leads, deals, quotations, inquiries, orders');
    bbv_as($M); $_GET['who'] = 'all'; t_eq(sx_person(), $M, 'Team · without "See other salespeople\'s lists" there is no "Everyone" (?who=all is ignored)'); unset($_GET['who']);
    $setRole('MARKETING_MANAGER', 'own');
    t_eq($seen($M), ['l' => 'M', 'o' => 'M', 'q' => 'M', 'i' => 'M', 'r' => 'M', 'hl' => 'M', 'hd' => 'M'], 'Own · only their own records, everywhere');
    $setRole('MARKETING_MANAGER', 'branch');
    t_eq($seen($M)['l'], $all5, 'Branch · back to every record of their branch');
    //  Own never ADDS a branch: an Ahmedabad coordinator set to Team still sees no Mumbai record.
    $setRole('COORDINATOR', 'team');
    bbv_as($f['co_ahm']); t_eq(array_values(array_intersect(array_map('intval', array_column(leads_all() ?: [], 'id')), array_values($made['l']))), [], 'Team · never reaches another branch (Ahmedabad coordinator sees no Mumbai lead)');
    $setRole('COORDINATOR', 'branch');

    // ---- 3. A person's own choice beats the role ----------------------------------------------------
    $setUser($A, 'own');
    t_eq($seen($A)['l'], 'A', 'person · Asha set to Own (her role is Branch) sees only her lead');
    $setRole('BUSINESS_DEV_MANAGER', 'own'); $setUser($A, 'branch');
    t_eq($seen($A)['l'], $all5, 'person · Asha set to Branch while her role is Own sees the whole branch');
    $setRole('BUSINESS_DEV_MANAGER', 'branch'); $setUser($A, '');
    t_eq(sales_rec_user_override($A), '', 'person · "as the role" removes her own choice');

    // ---- Opening one record by its address follows the same rule --------------------------------
    $setRole('MARKETING_MANAGER', 'team');
    //  A refusal is a flash error and a redirect (ops_require()).
    $open = function ($route, $id) use ($M) { $r = bbv_route($route, 'GET', $M, 0, [], ['id' => $id]);
        foreach ((array) ($r['flash'] ?? []) as $fl) if (strpos((string) $fl, 'error:') === 0) return false;
        return !empty($r['view_ok']); };
    t_ok($open('lead', $made['l']['B']) && !$open('lead', $made['l']['C']), 'opening · Team: the manager opens Bala\'s lead, not Chetan\'s');
    t_ok($open('opportunity', $made['o']['A']) && !$open('opportunity', $made['o']['C']), 'opening · Team: Asha\'s deal opens, Chetan\'s does not');
    t_ok($open('quote', $made['q']['A']) && !$open('quote', $made['q']['C']), 'opening · Team: a quotation known only by who made it (Asha) opens; Chetan\'s does not');
    t_ok($open('inquiry', $made['i']['B']) && !$open('inquiry', $made['i']['C']), 'opening · Team: Bala\'s inquiry opens, Chetan\'s does not');
    $setRole('MARKETING_MANAGER', 'branch');
    t_ok($open('lead', $made['l']['C']) && $open('opportunity', $made['o']['C']), 'opening · Branch (day one): Chetan\'s lead and deal open again');

    // ---- 4. Sales home / Today: "My team" picker ------------------------------------------------------
    bbv_as($M);
    t_ok(!sx_can_see_all(), 'team picker · the Marketing Manager has no "See other salespeople\'s lists" (as before)');
    t_eq(array_keys(sx_team_people()), [$A, $B], 'team picker · lists Asha and Bala (the whole line below), not Chetan');
    $_GET['who'] = (string) $B; t_eq(sx_person(), $B, 'team picker · ?who=Bala shows Bala\'s list');
    $_GET['who'] = (string) $C; t_eq(sx_person(), $M, 'team picker · ?who=Chetan (not in the line) falls back to their own list'); unset($_GET['who']);
    $h = bbv_route('sales-home', 'GET', $M, 0, [], ['who' => (string) $A]); $o = (string) ($h['out'] ?? '');
    t_ok(strpos($o, 'Q10Asha') !== false, 'team picker · Sales home offers the team picker, with Asha picked' . ($o === '' ? ' — ' . json_encode(array_intersect_key($h, ['code' => 1, 'flash' => 1, 'view_err' => 1])) : ' — ' . substr($o, 0, 0)));
    t_ok(strpos($o, 'Everyone') === false, 'team picker · no "Everyone" on Sales home for the manager');
    $t = bbv_route('sales-today', 'GET', $M, 0, [], []); $to = (string) ($t['out'] ?? '');
    t_ok(strpos($to, 'Q10L-M-' . $tag) !== false && strpos($to, 'Q10L-A-' . $tag) === false && strpos($to, 'Everyone') === false, 'team picker · Today opens on their own list by default; no "Everyone" link');
    bbv_as($C); t_eq(sx_team_people(), [], 'team picker · Chetan (nobody reports to him) gets no picker — exactly as before');
    $setUser($M, 'own'); bbv_as($M); t_eq(sx_team_people(), [], 'team picker · a manager set to Own has no team picker (they would see nothing of them)'); $setUser($M, '');
    bbv_as($f['bd']); t_ok(sx_can_see_all(), 'team picker · the Business Director still has "Everyone" and any person (unchanged)');

    // ---- 5. Saving on "Who can do what" ------------------------------------------------------------
    $master = $f['master'];
    //  A save happens in a second process; read what it stored straight from the database.
    $fresh = function () { $c = &settings_cache(); $c['sales_records_mode'] = (string) ops_val("SELECT svalue FROM settings WHERE skey='sales_records_mode'", [], ''); };
    $pg = q10_route('who-can', 'GET', $master, ['tab' => 'roles', 'role' => 'MARKETING_MANAGER']);
    $html = (string) ($pg['html'] ?? '');
    t_ok(strpos($html, 'name="records_mode"') !== false, 'screen · the role page shows "Whose records they see"' . ($html === '' ? ' — ' . ($pg['view_err'] ?? '') : ''));
    t_ok(strpos($html, 'Waiting for your decision after Stage 2') === false, 'screen · question 10 no longer says "Needs your decision"');
    $fields = q10_form_fields($html, '/access');
    t_eq($fields['records_mode'] ?? null, 'branch', 'screen · the role page shows the saved choice (Branch)');
    $fields['records_mode'] = 'team';
    $rightsBefore = role_perms('MARKETING_MANAGER'); sort($rightsBefore);
    q10_route('access', 'POST', $master, [], $fields);
    $fresh(); $rc = &settings_cache(); $rc['role_access'] = (string) ops_val("SELECT svalue FROM settings WHERE skey='role_access'", [], '');
    $rightsAfter = role_perms('MARKETING_MANAGER'); sort($rightsAfter);
    t_eq(sales_rec_role_mode('MARKETING_MANAGER'), 'team', 'save · the role page saves Team for the Marketing Manager');
    t_eq(array_values(array_diff($rightsAfter, ['@split1', '@verbs2'])), array_values(array_diff($rightsBefore, ['@split1', '@verbs2'])), 'save · the role\'s switches are unchanged by saving the records choice');
    $logged = (int) ops_val("SELECT COUNT(*) FROM idems_audit WHERE action='ROLE_RECORDS_SCOPE_CHANGED'", [], 0);
    t_ok($logged >= 1, 'save · the change is written to the history (sealed audit chain)');
    $setRole('MARKETING_MANAGER', 'branch');

    $pp = q10_route('who-can', 'GET', $master, ['tab' => 'people', 'id' => $A]);
    $pf = q10_form_fields((string) ($pp['html'] ?? ''), '/who-can-person');
    t_eq($pf['records_mode'] ?? null, '', 'screen · the person page shows "As the role" for Asha');
    $permsBefore = (string) ops_val("SELECT permissions FROM users WHERE id=?", [$A]);
    $pf['records_mode'] = 'own';
    q10_route('who-can-person', 'POST', $master, ['id' => $A], $pf); $fresh();
    t_eq(sales_rec_user_override($A), 'own', 'save · the person page saves Own for Asha');
    t_eq((string) ops_val("SELECT permissions FROM users WHERE id=?", [$A]), $permsBefore, 'save · her switches are stored exactly as before (only the records choice changed)');
    $pf['records_mode'] = 'all';
    q10_route('who-can-person', 'POST', $master, ['id' => $A], $pf); $fresh();
    $row = ops_one("SELECT scope_offices FROM users WHERE id=?", [$A]);
    t_ok(sales_rec_user_override($A) === 'branch' && strtoupper((string) $row['scope_offices']) === 'ALL', 'save · "All" = Branch with every branch (her branches set to All)');
} finally {
    setting_set('sales_records_mode', $recBefore);
    setting_set('role_access', $roleAccessBefore);
    $pdo = db();
    $del = function ($table, $ids) use ($pdo) { $ids = array_values(array_filter(array_map('intval', $ids))); if ($ids) $pdo->exec("DELETE FROM $table WHERE id IN (" . implode(',', $ids) . ")"); };
    $del('leads', $made['l']); $del('opportunities', $made['o']); $del('crm_inquiries', $made['i']); $del('quotations', $made['q']); $del('users', $made['u']);
    unset($_GET['who']);
    bbv_cleanup($f);
}
