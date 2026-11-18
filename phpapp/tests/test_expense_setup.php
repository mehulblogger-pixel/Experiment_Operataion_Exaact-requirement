<?php
// Expense setup (owner, 18 Nov 2026) — ONE screen with four tabs, and the rule
// the owner set: "Band limits are company fixed limits" and "a person gets his
// own additional allowance to adjust the salary" — a PERSONAL allowance, added on
// top of the band, never by moving him to another band. Everyone in the approval
// chain sees the personal lines.
//   * every save on the screen is the Master Admin's (an Admin is refused);
//   * types are added and changed HERE (bill, no bill, per day, per month, per km),
//     a duplicate name is refused, a personal type is per site day or per month;
//   * a band's table: one box per type (rate, or "up to" for a bill), personal
//     types never in it, a minus refused, "copy of" another band;
//   * a default band for each role; a person picked directly wins; blank follows;
//   * the person's record: band + personal allowances, refused without an amount;
//   * a personal allowance is its own line on the claim, marked "personal";
//   * the old Masters lists lead the Master Admin here; breadcrumb Admin › Expense setup.
t_section('Expense setup — one screen; band = company policy; personal allowance on top');

require_once __DIR__ . '/_exp1_fixture.php';
$f = exp1_fixture();
$opened = fn($r, $view) => empty($r['restricted']) && (int) ($r['code'] ?? 0) !== 403 && ($r['view'] ?? '') === $view && !empty($r['view_ok']);
$flash = fn($r) => implode(' | ', $r['flash'] ?? []);
$tag = $f['tag'];
$newModes = []; $newBands = [];
$oldRoleBands = setting_get('exp_role_bands', null);
//  The screens run in a second process; read back what they saved.
$syncRB = function () { $c = &settings_cache(); $v = ops_val("SELECT svalue FROM settings WHERE skey='exp_role_bands'"); if ($v === null || $v === false) unset($c['exp_role_bands']); else $c['exp_role_bands'] = (string) $v; };
try {
    $t = $f['today'];
    // ---- The four tabs ----------------------------------------------------------
    foreach (['rules' => 'Claim period', 'types' => 'Vehicles (per km)', 'bands' => 'what it may claim', 'people' => 'Default band for each role'] as $tab => $want) {
        $r = bbv_route('voucher-rules', 'GET', $f['master'], 0, [], ['tab' => $tab]);
        t_ok($opened($r, 'ops/voucher_rules') && strpos($r['out'], $want) !== false, "tab $tab · opens and shows \"$want\"");
    }
    $r = bbv_route('voucher-rules', 'GET', $f['master'], 0, [], ['tab' => 'bands']);
    t_ok(strpos($r['out'], 'Field ' . $tag) !== false, 'bands · the first band opens by itself (no "Open" button at the bottom)');
    t_ok(strpos($r['out'], 'Site allowance ' . $tag) === false, 'bands · a personal type is never in a band\'s table');
    t_ok(strpos($r['out'], 'per km') !== false && strpos($r['out'], 'up to, per bill') !== false && strpos($r['out'], 'per site day') !== false, 'bands · each box says what it means');
    $r = bbv_route('voucher-rules', 'GET', $f['master'], 0, [], ['tab' => 'types']);
    t_ok(strpos($r['out'], 'Masters') === false, 'types · no jump to the Masters screen');
    t_ok(strpos($r['out'], 'Personal allowance') !== false, 'types · the personal type says what it is');
    $r = bbv_route('voucher-rules', 'GET', $f['admin'], 0, [], ['tab' => 'types']);
    t_ok(!$opened($r, 'ops/voucher_rules'), 'an Admin does not open Expense setup (Master Admin only, as before)');
    nav_under('voucher-rules', 'admin');      // what the route itself declares
    t_eq(array_column(nav_trail('voucher-rules'), 'label'), ['Home', 'Admin', 'Expense setup'], 'breadcrumb · Home › Admin › Expense setup');

    // ---- Tab 2 — types, here ------------------------------------------------------
    $cnt = fn() => (int) ops_val("SELECT COUNT(*) FROM expense_heads");
    $before = $cnt();
    bbv_route('voucher-rules', 'POST', $f['admin'], 0, ['_do' => 'type_add', 'label' => 'Admin try ' . $tag, 'kind' => 'BILL']);
    t_eq($cnt(), $before, 'types · an Admin cannot add a type');
    $r = bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_add', 'label' => 'Toll ' . $tag, 'kind' => 'BILL', 'needs_receipt' => '1', 'scope' => 'JOB']);
    $toll = ops_one("SELECT * FROM expense_heads WHERE label=?", ['Toll ' . $tag]);
    t_ok($toll && $toll['head_type'] === 'BILL' && (int) $toll['needs_receipt'] === 1 && (int) $toll['personal'] === 0 && $toll['code'] !== '', 'types · a bill type with photo is added here: ' . $flash($r));
    $r = bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_add', 'label' => 'toll ' . $tag, 'kind' => 'NOBILL']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM expense_heads WHERE LOWER(label)=LOWER(?)", ['Toll ' . $tag]), 1, 'types · the same name again is refused: ' . $flash($r));
    $r = bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_add', 'label' => '   ', 'kind' => 'BILL']);
    t_eq($cnt(), $before + 1, 'types · an empty name is refused');
    $r = bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_add', 'label' => 'Pay top-up ' . $tag, 'kind' => 'BILL', 'personal' => '1']);
    t_ok(!ops_val("SELECT id FROM expense_heads WHERE label=?", ['Pay top-up ' . $tag]), 'types · a personal type cannot be a bill: ' . $flash($r));
    bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_add', 'label' => 'Pay top-up ' . $tag, 'kind' => 'MONTHLY', 'personal' => '1']);
    $top = ops_one("SELECT * FROM expense_heads WHERE label=?", ['Pay top-up ' . $tag]);
    t_ok($top && (int) $top['personal'] === 1 && $top['head_type'] === 'MONTHLY', 'types · a personal type once a month is added');
    bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_add', 'label' => 'Night halt ' . $tag, 'kind' => 'PER_DAY', 'personal' => '1', 'scope' => 'NONJOB', 'add_mode' => 'TICK']);
    $nh = ops_one("SELECT * FROM expense_heads WHERE label=?", ['Night halt ' . $tag]);
    t_ok($nh && $nh['scope'] === 'JOB' && $nh['add_mode'] === 'AUTO', 'types · a personal per-day type is paid by itself on each site day (never "not for a job" / tick)');
    bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_add', 'label' => 'Own scooter ' . $tag, 'kind' => 'PER_KM']);
    $sc = ops_one("SELECT * FROM travel_modes WHERE label=?", ['Own scooter ' . $tag]);
    if ($sc) $newModes[] = $sc['code'];
    t_ok($sc && $sc['basis'] === 'PER_KM' && (int) $sc['active'] === 1, 'types · a per-km vehicle is added to the same list');
    $rick = ops_one("SELECT * FROM expense_heads WHERE code=?", [$f['RICK']]);
    $r = bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_save', 'id' => $rick['id'], 'label' => $rick['label'], 'kind' => 'BILL', 'needs_receipt' => '0', 'active' => '1', 'scope' => 'JOB']);
    t_eq((string) ops_val("SELECT head_type FROM expense_heads WHERE id=?", [$rick['id']]), 'BILL', 'types · the kind is changed right here: ' . $flash($r));
    bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_save', 'id' => $rick['id'], 'label' => $rick['label'], 'kind' => 'NOBILL', 'active' => '1', 'scope' => 'JOB']);
    $r = bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_save', 'id' => $rick['id'], 'label' => 'Toll ' . $tag, 'kind' => 'NOBILL', 'active' => '1']);
    t_eq((string) ops_val("SELECT label FROM expense_heads WHERE id=?", [$rick['id']]), $rick['label'], 'types · renaming to a name already used is refused');
    $kmId = (int) ops_val("SELECT id FROM expense_heads WHERE code='KMTRAVEL'");
    if ($kmId) {
        $kmLab = (string) ops_val("SELECT label FROM expense_heads WHERE id=?", [$kmId]);
        bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'type_save', 'id' => $kmId, 'label' => 'Changed ' . $tag, 'kind' => 'BILL', 'active' => '1']);
        t_eq((string) ops_val("SELECT label FROM expense_heads WHERE id=?", [$kmId]), $kmLab, 'types · the built-in km line cannot be changed');
    }
    bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'mode_save', 'id' => $sc['id'] ?? 0, 'label' => 'Own scooter ' . $tag]);
    t_eq((int) ops_val("SELECT active FROM travel_modes WHERE code=?", [$sc['code'] ?? '']), 0, 'types · a vehicle is switched off here');

    // ---- Tab 3 — bands ------------------------------------------------------------
    $r = bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'band_add', 'name' => 'field ' . $tag]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM expense_bands WHERE LOWER(name)=LOWER(?)", ['Field ' . $tag]), 1, 'bands · the same name again is refused: ' . $flash($r));
    bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'band_add', 'name' => 'Director ' . $tag, 'copy_from' => $f['band']]);
    $dir = (int) ops_val("SELECT id FROM expense_bands WHERE name=?", ['Director ' . $tag]); $newBands[] = $dir;
    t_eq((float) (exp_ent_latest('band', $dir, 'HEAD', $f['HOTEL'], $t)['limit_amount'] ?? 0), 3000.0, 'bands · "start as a copy" copies the other band\'s amounts');
    $post = ['_do' => 'band_save', 'band_id' => $dir, 'name' => 'Director ' . $tag, 'active' => '1', 'allow_from' => $t,
             'allow_mode' => [$f['mode2'] => '1'], 'amt_mode' => [$f['mode2'] => '15'],
             'allow_head' => [$f['HOTEL'] => '1', $f['DA'] => '1', $f['PERS'] => '1'], 'amt_head' => [$f['HOTEL'] => '10000', $f['DA'] => '600', $f['PERS'] => '777']];
    bbv_route('voucher-rules', 'POST', $f['admin'], 0, $post);
    t_ok((float) (exp_ent_latest('band', $dir, 'HEAD', $f['HOTEL'], $t)['limit_amount'] ?? 0) === 3000.0, 'bands · an Admin cannot change a band');
    $r = bbv_route('voucher-rules', 'POST', $f['master'], 0, ['amt_head' => [$f['HOTEL'] => '-1']] + $post);
    t_ok((float) (exp_ent_latest('band', $dir, 'HEAD', $f['HOTEL'], $t)['limit_amount'] ?? 0) === 3000.0, 'bands · a minus amount is refused: ' . $flash($r));
    bbv_route('voucher-rules', 'POST', $f['master'], 0, $post);
    $h = exp_ent_latest('band', $dir, 'HEAD', $f['HOTEL'], $t); $d = exp_ent_latest('band', $dir, 'HEAD', $f['DA'], $t); $c = exp_ent_latest('band', $dir, 'MODE', $f['mode2'], $t);
    t_ok($h && (float) $h['limit_amount'] === 10000.0 && $h['rate_override'] === null, 'bands · a bill\'s box is its "up to" amount (hotel up to ₹10,000 for a director)');
    t_ok($d && (float) $d['rate_override'] === 600.0, 'bands · a per-day box is its rate');
    t_ok($c && (int) $c['allowed'] === 1 && (float) $c['rate_override'] === 15.0, 'bands · a per-km box is its rate');
    t_ok(!exp_ent_latest('band', $dir, 'HEAD', $f['RICK'], $t) || (int) exp_ent_latest('band', $dir, 'HEAD', $f['RICK'], $t)['allowed'] === 0, 'bands · an unticked type is not claimable');
    $p = exp_ent_latest('band', $dir, 'HEAD', $f['PERS'], $t);
    t_ok(!$p || $p['effective_from'] !== $t, 'bands · nothing is saved for a personal type on a band');

    // ---- Tab 4 — default band per role, and each person ---------------------------
    bbv_route('voucher-rules', 'POST', $f['admin'], 0, ['_do' => 'people_save', 'role_band' => ['INSPECTOR' => $dir]]); $syncRB();
    t_ok(!isset(exp_role_bands()['INSPECTOR']) || exp_role_bands()['INSPECTOR'] !== $dir, 'people · an Admin cannot set the role bands');
    db()->prepare("UPDATE inspectors SET band_id=NULL WHERE id IN (?,?)")->execute([$f['amit_ins'], $f['rahul_ins']]);
    bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'people_save', 'role_band' => ['INSPECTOR' => $f['band'], 'SR_INSPECTOR' => $dir, 'BAD ROLE' => $dir]]); $syncRB();
    t_eq(exp_band_of($f['rahul_ins']), [$f['band'], 'role'], 'people · an inspector with no band of his own follows his role\'s band');
    t_eq(exp_band_of($f['sr_ins']), [$dir, 'role'], 'people · a senior inspector follows his own role\'s band');
    t_ok(!isset(exp_role_bands()['BAD ROLE']), 'people · a malformed role key is not stored');
    $en = exp_entitlement($f['rahul_ins'], 'HEAD', $f['DA'], $t);
    t_eq([$en['rate'], $en['source']], [300.0, 'band'], 'people · …and is paid by it (₹300 a day from the role band) ' . json_encode($en) . json_encode(exp_ent_latest('band', $f['band'], 'HEAD', $f['DA'], $t)));
    bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'people_save', 'role_band' => ['INSPECTOR' => $f['band'], 'SR_INSPECTOR' => $dir], 'band' => [$f['rahul_ins'] => $dir, $f['amit_ins'] => 999999]]); $syncRB();
    t_eq(exp_band_of($f['rahul_ins']), [$dir, 'person'], 'people · a band picked on the person wins over the role');
    t_eq(exp_own_band($f['amit_ins']), 0, 'people · a band that does not exist is ignored');
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='INSPECTOR' AND entity_id=? AND subject LIKE 'Band%'", [$f['rahul_ins']]) >= 1, 'people · the band change is in History');
    bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'people_save', 'role_band' => ['INSPECTOR' => $f['band'], 'SR_INSPECTOR' => $dir], 'band' => [$f['rahul_ins'] => '']]); $syncRB();
    t_eq(exp_band_of($f['rahul_ins']), [$f['band'], 'role'], 'people · blank goes back to following the role');
    $r = bbv_route('voucher-rules', 'GET', $f['master'], 0, [], ['tab' => 'people']);
    t_ok(strpos($r['out'], 'Field ' . $tag . ' (from role)') !== false, 'people · the list says "(from role)"');
    db()->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$f['lone']]);
    t_eq(exp_band_of($f['lone_ins']), [0, ''], 'people · a person without an active login has no role band');
    db()->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$f['lone']]);

    // ---- The person's record: band + personal allowances ------------------------
    $edit = fn($uid, $post) => bbv_route('m/inspectors/edit', 'POST', $uid, 0, ['_do' => 'allow_save'] + $post, ['id' => $f['amit_ins']]);
    $edit($f['admin'], ['band_id' => $dir, 'allow_from' => $t, 'pa_on' => [$f['PERS'] => '1'], 'pa_amt' => [$f['PERS'] => '250']]);
    t_ok(exp_own_band($f['amit_ins']) !== $dir && !exp_entitlement($f['amit_ins'], 'HEAD', $f['PERS'], $t)['allowed'], 'record · an Admin cannot set a band or a personal allowance');
    $r = $edit($f['master'], ['band_id' => '', 'allow_from' => $t, 'pa_on' => [$f['PERS'] => '1'], 'pa_amt' => [$f['PERS'] => '']]);
    t_ok(!exp_entitlement($f['amit_ins'], 'HEAD', $f['PERS'], $t)['allowed'] && stripos($flash($r), 'needs an amount') !== false, 'record · on without an amount is refused, and says why');
    $edit($f['master'], ['band_id' => '', 'allow_from' => $t, 'pa_on' => [$f['PERS'] => '1'], 'pa_amt' => [$f['PERS'] => '100']]);
    //  A once-a-month allowance applies from the first claim period that starts on or after its date.
    $edit($f['master'], ['band_id' => '', 'allow_from' => exp_period_for($t)[0], 'pa_on' => [$top['code'] => '1'], 'pa_amt' => [$top['code'] => '2000']]);
    t_eq(exp_band_of($f['amit_ins']), [$f['band'], 'role'], 'record · blank band = follows his role');
    $en = exp_entitlement($f['amit_ins'], 'HEAD', $f['PERS'], $t);
    t_ok($en['allowed'] && $en['rate'] == 100.0, 'record · his site allowance ₹100 a site day is saved');
    t_eq(exp_entitlement($f['amit_ins'], 'HEAD', $f['DA'], $t)['rate'], 300.0, 'record · his band\'s food allowance stays ₹300 (policy unchanged)');
    t_eq(exp_entitlement($f['rahul_ins'], 'HEAD', $f['DA'], $t)['rate'], 300.0, 'record · and so does everyone else\'s in the band');
    $r = bbv_route('m/inspectors/edit', 'GET', $f['master'], 0, [], ['id' => $f['amit_ins']]);
    t_ok(strpos($r['out'], 'Personal allowances (only for') !== false && strpos($r['out'], 'Policy for this band') !== false, 'record · the Expenses tab shows his band\'s policy and his personal allowances');
    t_ok(strpos($r['out'], 'the old way') === false, 'record · with a band, the old per-person table is not shown');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $edit($f['master'], ['band_id' => '', 'allow_from' => $tomorrow, 'pa_on' => [$f['PERS'] => '1', $top['code'] => '1'], 'pa_amt' => [$f['PERS'] => '150', $top['code'] => '2000']]);
    t_eq(exp_entitlement($f['amit_ins'], 'HEAD', $f['PERS'], $t)['rate'], 100.0, 'record · a new amount from tomorrow leaves today at ₹100 (history kept)');
    t_eq(exp_entitlement($f['amit_ins'], 'HEAD', $f['PERS'], $tomorrow)['rate'], 150.0, 'record · …and is ₹150 from tomorrow');
    t_eq((string) (exp_ent_latest('person', $f['amit_ins'], 'HEAD', $top['code'], $tomorrow)['effective_from'] ?? ''), exp_period_for($t)[0], 'record · an unchanged allowance keeps its own "from" date');
    $edit($f['master'], ['band_id' => '', 'allow_from' => $tomorrow, 'pa_on' => [$top['code'] => '1'], 'pa_amt' => [$f['PERS'] => '150', $top['code'] => '2000']]);
    t_ok(!exp_entitlement($f['amit_ins'], 'HEAD', $f['PERS'], $tomorrow)['allowed'] && exp_entitlement($f['amit_ins'], 'HEAD', $f['PERS'], $t)['allowed'], 'record · switched off from tomorrow; today keeps it');

    // ---- On the claim: its own line, marked "personal", seen by the approver --------
    $r = bbv_route('job-close', 'GET', $f['amit'], 0, [], ['id' => $f['job']]);
    t_ok(strpos($r['out'], 'Site allowance') !== false && strpos($r['out'], 'your personal allowance') !== false, 'close the day · the site allowance is filled in and says "your personal allowance"');
    exp1_close_job($f['job']);
    bbv_as($f['amit']);
    [$ok, $msg, $cv] = exp_day_confirm($f['amit_ins'], $f['job'], $t, ['km' => '10', 'mode' => $f['mode']]);
    t_ok($ok, 'claim · the day is confirmed: ' . $msg);
    $f['vids'][] = (int) $cv['id'];
    $by = []; foreach (exp1_lines($cv['id']) as $l) $by[$l['head_code']] = $l;
    t_eq([(float) ($by[$f['DA']]['row_total'] ?? 0), (float) ($by[$f['PERS']]['row_total'] ?? 0)], [300.0, 100.0], 'claim · food ₹300 (policy) and site allowance ₹100 (personal) are two lines');
    t_eq(count(array_filter(exp1_lines($cv['id']), fn($l) => $l['head_code'] === $top['code'])), 1, 'claim · the monthly personal top-up is added once to the month');
    exp_claim_refresh(ops_one("SELECT * FROM vouchers WHERE id=?", [$cv['id']]));
    t_eq(count(array_filter(exp1_lines($cv['id']), fn($l) => $l['head_code'] === $top['code'])), 1, 'claim · …and only once');
    [$ok, $msg] = exp_add_line($f['amit_ins'], $f['PERS'], 500, $t, $f['job'], 'extra');
    t_ok(!$ok, 'claim · a personal allowance cannot be typed in as a bill: ' . $msg);
    //  A draft is not shown to the approver before it is sent; the claim screen is the
    //  same one for everybody, so it is read as the claimant and as the Master Admin.
    $r = bbv_route('voucher', 'GET', $f['amit'], (int) $cv['id']);
    t_ok(strpos($r['out'], 'personal') !== false, 'claim · the claimant sees the line marked "personal"');
    $r = bbv_route('voucher-status', 'POST', $f['amit'], (int) $cv['id'], ['action' => 'submit']);
    t_eq($r['status'] ?? '', 'SUBMITTED', 'claim · submitted: ' . $flash($r));
    $r = bbv_route('voucher', 'GET', $f['neha'], (int) $cv['id']);
    t_ok(strpos($r['out'], 'personal') !== false && strpos($r['out'], 'Site allowance') !== false, 'claim · his approver (N+1) sees the personal line marked "personal" (P2: everyone in the chain)');

    // ---- The old Masters lists lead the Master Admin here ---------------------------
    $r = bbv_route('m/expense-heads', 'GET', $f['master'], 0);
    t_ok(($r['view'] ?? '') !== 'list' && !$opened($r, 'list'), 'masters · the Master Admin is taken to Expense setup');
    $r = bbv_route('m/expense-heads', 'GET', $f['admin'], 0);
    t_ok(($r['view'] ?? '') !== 'ops/voucher_rules', 'masters · an Admin keeps the old list (permissions unchanged)');
} finally {
    if ($oldRoleBands === null) { try { db()->prepare("DELETE FROM settings WHERE skey='exp_role_bands'")->execute(); } catch (Throwable $e) {} if (function_exists('settings_cache')) { $sc_ = &settings_cache(); unset($sc_['exp_role_bands']); unset($sc_); } }
    else setting_set('exp_role_bands', (string) $oldRoleBands);
    foreach ($newModes as $mc) try { db()->prepare("DELETE FROM travel_modes WHERE code=?")->execute([$mc]); } catch (Throwable $e) {}
    foreach ($newBands as $b) try { db()->prepare("DELETE FROM inspector_allowances WHERE band_id=?")->execute([$b]); db()->prepare("DELETE FROM expense_bands WHERE id=?")->execute([$b]); } catch (Throwable $e) {}
    try { db()->prepare("DELETE FROM expense_heads WHERE label LIKE ?")->execute(['% ' . $tag]); } catch (Throwable $e) {}
    exp1_cleanup($f);
}
t_ok(!ops_val("SELECT id FROM expense_bands WHERE name LIKE ?", ['% ' . $tag]), 'the test removed its own rows');
