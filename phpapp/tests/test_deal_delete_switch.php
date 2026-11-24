<?php
// ============================================================================
//  "Delete deals" — a switch of its own (owner, 24 Nov 2026, "as recommended";
//  docs/02 19y7).
//
//  Before: deleting a deal was any management role (is_admin_level()), so the
//  Branch Manager and the SBU Head — view and download only since 21 Nov — and
//  the Branch App Manager and the Operation Manager could delete deals.
//  Now: crm.deal.delete, starting with the Admin, the Business Director and the
//  Master Admin only. The won / quoted blocks and the History entry are unchanged.
// ============================================================================
t_section('Delete deals — its own switch; Admin, Business Director and Master Admin only');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$tag = $f['tag'];
$made = ['opp' => [], 'link' => [], 'users' => []];
$prevRA = (string) setting_get('role_access', '');
$prevApplied = (string) setting_get('access_owner_grants_applied', '');

$mkU = function ($key, $role) use ($pdo, $f, &$made) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices) VALUES (?,?,?,?,1,?,?)")
        ->execute([$f['tag'] . '_' . $key, ucfirst($key), 'T', $role, $f['mum'], '']);
    return $made['users'][] = (int) $pdo->lastInsertId();
};
$mkOpp = function ($name, $status = 'OPEN') use ($pdo, $f, &$made) {
    $pdo->prepare("INSERT INTO opportunities (ref, name, partner_name, value, status, office_id, created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([strtoupper($f['tag']) . '-' . mt_rand(1000, 999999), $name, 'DDS Client', 100000, $status, $f['mum'], date('c')]);
    return $made['opp'][] = (int) $pdo->lastInsertId();
};
$exists = fn($id) => (bool) ops_val("SELECT 1 FROM opportunities WHERE id=?", [$id]);
//  The page's own HTML (BBV_RAW), so the Delete form's action can be looked for; a page that did
//  not render at all must not pass a "no button" check.
putenv('BBV_RAW=1');
$html = function ($uid, $id) { $r = bbv_route('opportunity', 'GET', $uid, $id); return (string) ($r['html'] ?? ''); };
$hasBtn = fn($h) => strpos($h, 'action="/opportunity-delete"') !== false;
$rendered = fn($h) => strpos($h, 'DDS Client') !== false || strpos($h, 'A won deal') !== false;

try {
    // ---- 1. The switch itself ------------------------------------------------------
    t_ok(isset(PERMISSIONS['crm.deal.delete']) && strlen(perm_help('crm.deal.delete')) > 40, 'the switch has a plain name and an explanation');
    $onRole = false; foreach (permission_groups() as $ks) if (in_array('crm.deal.delete', $ks, true)) $onRole = true;
    $onPerson = false; foreach (permission_nav_groups() as $ks) if (in_array('crm.deal.delete', $ks, true)) $onPerson = true;
    t_ok($onRole && $onPerson, 'it is on the role screen and on the person screen (a save never drops it)');
    t_ok(strpos((string) file_get_contents(__DIR__ . '/../lib/whocan.php'), "whocan_row('crm.deal.delete'") !== false, 'it is on Who can do what (Deals)');

    // ---- 2. Who holds it on day one (built-in role rights) ---------------------------
    foreach (['ADMIN' => true, 'BUSINESS_DIRECTOR' => true, 'SBU_HEAD' => false, 'BRANCH_MANAGER' => false, 'BRANCH_APP_MANAGER' => false,
              'OPERATION_MANAGER' => false, 'BUSINESS_DEV_MANAGER' => false, 'KEY_ACCOUNTS_MANAGER' => false, 'MARKETING_MANAGER' => false,
              'MARKETING_EXECUTIVE' => false, 'COORDINATOR' => false, 'FINANCE' => false, 'INSPECTOR' => false] as $role => $want)
        t_ok(in_array('crm.deal.delete', role_perms($role), true) === $want, "$role " . ($want ? 'holds' : 'does not hold') . ' "Delete deals" by default');
    //  A set saved before foundation A (no @split1 stamp) derives it from the old rule —
    //  except for the four roles the owner narrowed.
    t_ok(in_array('crm.deal.delete', perm_upgrade_split(['mod.leads.view'], 'BUSINESS_DIRECTOR'), true), 'an older saved set of the Business Director derives it (old rule: management)');
    foreach (ACCESS_DEAL_DELETE_NARROWED as $role)
        t_ok(!in_array('crm.deal.delete', perm_upgrade_split(['mod.leads.view'], $role), true), "an older saved set of $role does not derive it (the owner's narrowing)");
    t_ok(!in_array('crm.deal.delete', perm_upgrade_split(['mod.leads.view', PERM_SPLIT_TAG], 'BUSINESS_DIRECTOR'), true), 'a stamped set is left as saved (the one-time rule below handles it)');

    // ---- 3. The one-time rule reaches a SAVED grid (stamped @split1) -------------------
    setting_set('role_access', json_encode(['ADMIN' => ['mod.leads.view', PERM_SPLIT_TAG], 'BUSINESS_DIRECTOR' => ['mod.leads.view', PERM_SPLIT_TAG],
                                            'BRANCH_MANAGER' => ['mod.leads.view', PERM_SPLIT_TAG], 'SBU_HEAD' => ['mod.leads.view', PERM_SPLIT_TAG]]));
    setting_set('access_owner_grants_applied', implode(',', array_diff(array_keys(access_owner_grants()), ['DEAL-DELETE-SWITCH-2026-11-24'])));
    $done = access_apply_owner_grants(true);
    $st = json_decode((string) setting_get('role_access', ''), true);
    t_ok(in_array('DEAL-DELETE-SWITCH-2026-11-24', $done, true), 'the one-time rule ran');
    t_ok(in_array('crm.deal.delete', $st['ADMIN'], true) && in_array('crm.deal.delete', $st['BUSINESS_DIRECTOR'], true), 'a saved Admin and Business Director grid gain the switch once');
    t_ok(!in_array('crm.deal.delete', $st['BRANCH_MANAGER'], true) && !in_array('crm.deal.delete', $st['SBU_HEAD'], true), 'a saved Branch Manager / SBU Head grid does not');
    t_eq(access_apply_owner_grants(true), [], 'the one-time rule does not run twice');
    setting_set('role_access', $prevRA); setting_set('access_owner_grants_applied', $prevApplied);

    // ---- 4. As real sign-ins: who may delete -----------------------------------------
    $bam = $mkU('bam', 'BRANCH_APP_MANAGER'); $bdm = $mkU('bdm', 'BUSINESS_DEV_MANAGER'); $kam = $mkU('kam', 'KEY_ACCOUNTS_MANAGER');
    $refused = ['Branch Manager' => $f['kiran'], 'SBU Head' => $f['sbu'], 'Operation Manager' => $f['sunil'], 'Branch App Manager' => $bam,
                'Business Development Manager' => $bdm, 'Key Accounts Manager' => $kam];
    foreach ($refused as $who => $uid) {
        $id = $mkOpp('Refused for ' . $who);
        bbv_route('opportunity-delete', 'POST', $uid, $id, ['id' => $id]);
        t_ok($exists($id), "$who cannot delete a deal — it is still there");
        $h = $html($uid, $id);
        //  The Operation Manager and the Branch App Manager do not open deals at all (no "View deals").
        $opens = !in_array($who, ['Operation Manager', 'Branch App Manager'], true);
        t_ok(!$hasBtn($h) && $rendered($h) === $opens, "$who " . ($opens ? 'opens the deal page and' : 'cannot open the deal page, so') . ' is not shown the Delete button');
    }
    foreach (['Admin' => $f['admin'], 'Business Director' => $f['bd'], 'Master Admin' => $f['master']] as $who => $uid) {
        $id = $mkOpp('Deleted by ' . $who);
        t_ok($hasBtn($html($uid, $id)), "$who is shown the Delete button");
        bbv_route('opportunity-delete', 'POST', $uid, $id, ['id' => $id]);
        t_ok(!$exists($id), "$who can delete a deal");
        t_ok((bool) ops_val("SELECT 1 FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=? AND subject LIKE '%deleted%'", [$id]),
             "the deletion by $who is written to History (who and when)");
    }

    // ---- 5. The existing blocks still hold -------------------------------------------
    $won = $mkOpp('A won deal', 'WON');
    bbv_route('opportunity-delete', 'POST', $f['admin'], $won, ['id' => $won]);
    t_ok($exists($won), 'a won deal cannot be deleted, even by the Admin');
    $h = $html($f['admin'], $won);
    t_ok($rendered($h) && !$hasBtn($h), 'and no Delete button is offered on it');
    $quoted = $mkOpp('A deal with a quotation');
    $pdo->prepare("INSERT INTO opportunity_quotes (opportunity_id, quotation_id, linked_by, linked_at) VALUES (?,?,?,?)")->execute([$quoted, 999999, 'test', date('c')]);
    $made['link'][] = (int) $pdo->lastInsertId();
    bbv_route('opportunity-delete', 'POST', $f['master'], $quoted, ['id' => $quoted]);
    t_ok($exists($quoted), 'a deal linked to a quotation cannot be deleted, even by the Master Admin');

    // ---- 6. An exception an administrator gives on purpose works ----------------------
    $bmGiven = array_values(array_merge(role_perms('BRANCH_MANAGER'), ['crm.deal.delete', PERM_SPLIT_TAG]));
    setting_set('role_access', json_encode(['BRANCH_MANAGER' => $bmGiven]));
    $id = $mkOpp('Given on purpose');
    bbv_route('opportunity-delete', 'POST', $f['kiran'], $id, ['id' => $id]);
    t_ok(!$exists($id), 'a Branch Manager given the switch on purpose may delete (the switch is what decides)');
    setting_set('role_access', $prevRA);
} finally {
    putenv('BBV_RAW');
    setting_set('role_access', $prevRA); setting_set('access_owner_grants_applied', $prevApplied);
    if ($made['link']) try { $pdo->exec("DELETE FROM opportunity_quotes WHERE id IN (" . implode(',', $made['link']) . ")"); } catch (Throwable $e) {}
    if ($made['opp']) {
        try { $pdo->exec("DELETE FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id IN (" . implode(',', $made['opp']) . ")"); } catch (Throwable $e) {}
        try { $pdo->exec("DELETE FROM opportunities WHERE id IN (" . implode(',', $made['opp']) . ")"); } catch (Throwable $e) {}
    }
    if ($made['users']) try { $pdo->exec("DELETE FROM users WHERE id IN (" . implode(',', $made['users']) . ")"); } catch (Throwable $e) {}
    bbv_cleanup($f);
}
