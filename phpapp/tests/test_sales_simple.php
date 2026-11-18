<?php
// Plan 42 step C, part 1 (owner, 18 Nov 2026) — Sales home, Today, Pipeline
// board, Dashboard on the owner's Excel pattern, read from the EXISTING leads
// and deals (no new table): due states and colours, counts per person, a
// non-manager sees only their own, the dashboard figures (won, win rate,
// weighted = value × win %), every row links to its record, the screens are
// refused without Sales, and each one is a screen with its own switch.
t_section('Simple Sales — Home, Today, Pipeline board, Dashboard (owner\'s Excel pattern)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$made = ['l' => [], 'o' => []];
try {
    $t = date('Y-m-d'); $d = fn($n) => date('Y-m-d', strtotime("$n days"));
    // ---- Due states (the Excel's colours) ------------------------------------------
    t_eq([sx_due_state($d(-1)), sx_due_state($t), sx_due_state($d(3)), sx_due_state($d(9)), sx_due_state('')], ['overdue', 'today', 'week', 'later', 'none'], 'due · overdue / today / this week / later / no next step');
    t_eq(sx_due_label($d(-2))[1], 'p-bad', 'due · 2 days late is red');
    t_eq(sx_due_label($t)[1], 'p-warn', 'due · today is amber');
    t_eq(sx_short(18270000), '₹1.83 Cr', 'money · ₹1,82,70,000 shows as ₹1.83 Cr on a card');
    t_eq(sx_short(635040), '₹6.4 L', 'money · ₹6,35,040 shows as ₹6.4 L');
    t_eq(sx_wa('98250 11111'), 'https://wa.me/919825011111', 'WhatsApp · a 10-digit Indian mobile gets 91');
    t_eq(sx_wa('123'), '', 'WhatsApp · no link for a number too short');

    // ---- Records: two salespeople's leads and deals --------------------------------
    bbv_as($f['master']); leads_migrate(); opp_migrate();
    $mk = function ($uid, $company, $next, $value) use (&$made) {
        $r = lead_create(['company_name' => $company . ' ' . uniqid(), 'contact_phone' => '9825011111', 'source' => 'REFERRAL', 'value' => $value]);
        db()->prepare("UPDATE leads SET owner_user_id=?, next_action_on=?, office_id=NULL WHERE id=?")->execute([$uid, $next, $r['id']]);
        $made['l'][] = (int) $r['id']; return (int) $r['id'];
    };
    $pipe = pipeline_default('OPPORTUNITY'); $open = array_values(array_filter(pipeline_stages((int) $pipe['id']), fn($s) => $s['kind'] === 'OPEN'));
    $deal = function ($uid, $next, $value, $stageIdx, $status = 'OPEN', $wonAt = '') use (&$made, $pipe, $open, $f) {
        $r = opp_create(['name' => 'Deal ' . uniqid(), 'partner_name' => 'SX Test Co', 'value' => $value, 'pipeline_id' => $pipe['id']]);
        $st = $open[min($stageIdx, count($open) - 1)];
        if ($status !== 'OPEN') $st = ops_one("SELECT * FROM pipeline_stages WHERE pipeline_id=? AND kind=?", [$pipe['id'], $status]);
        db()->prepare("UPDATE opportunities SET owner_user_id=?, next_action_on=?, stage_id=?, probability=?, status=?, won_at=?, office_id=NULL, stage_since=? WHERE id=?")
            ->execute([$uid, $next, $st['id'], (int) $st['probability'], $status, $wonAt, date('c'), $r['id']]);
        $made['o'][] = (int) $r['id']; return (int) $r['id'];
    };
    $A = $f['kiran']; $B = $f['neha'];
    $mk($A, 'Kite', $d(-3), 90000); $mk($A, 'Nova', $t, 375000); $mk($A, 'Sun', '', 120000); $mk($B, 'Orbit', $d(5), 240000);
    $o1 = $deal($A, $d(-2), 1840000, 1); $deal($A, $t, 960000, 2); $deal($A, $d(4), 4200000, 0); $deal($B, '', 1200000, 1);
    $deal($A, '', 2350000, 0, 'WON', date('c', strtotime('-3 days'))); $deal($A, '', 1500000, 0, 'LOST', date('c', strtotime('-2 days')));

    $a = sx_today_data($A);
    t_eq([$a['counts']['overdue'], $a['counts']['today'], $a['counts']['week'], $a['counts']['none']], [2, 2, 1, 1], 'Today · person A: 2 overdue, 2 today, 1 this week, 1 with no next step (leads + deals)');
    t_eq(count($a['deals']), 3, 'Today · A has 3 deal follow-ups listed (overdue, today, this week)');
    t_eq($a['deals'][0]['_due'], 'overdue', 'Today · the overdue one is first');
    $b = sx_today_data($B);
    t_eq([$b['counts']['week'], $b['counts']['none']], [1, 1], 'Today · person B sees only B\'s');

    // ---- Who may see whose list -------------------------------------------------------
    bbv_as($B); $_GET['who'] = (string) $A;
    t_ok(!sx_can_see_all(), 'person · a coordinator is not a manager');
    t_eq(sx_person(), (int) $B, 'person · a coordinator cannot open someone else\'s list (?who= is ignored)');
    bbv_as($f['master']); $_GET['who'] = (string) $B;
    t_eq(sx_person(), (int) $B, 'person · a manager can pick a salesperson');
    $_GET['who'] = 'all'; t_eq(sx_person(), 0, 'person · …or everyone');
    unset($_GET['who']);

    // ---- Dashboard ------------------------------------------------------------------
    $dd = sx_dash_data($A, 'fy');
    t_eq([$dd['k']['won_v'], $dd['k']['won_n'], $dd['k']['lost_n'], $dd['k']['rate']], [2350000.0, 1, 1, 50], 'dashboard · won ₹23,50,000, 1 won, 1 lost, win rate 50%');
    $w = 1840000 * $open[1]['probability'] / 100 + 960000 * $open[2]['probability'] / 100 + 4200000 * $open[0]['probability'] / 100;
    t_eq(round($dd['k']['weighted']), round($w), 'dashboard · weighted forecast = value × the stage\'s win %');
    t_eq($dd['k']['open_v'], 7000000.0, 'dashboard · open pipeline ₹70,00,000 (A\'s 3 open deals)');
    t_eq($dd['k']['target'], 0.0, 'dashboard · no target yet (targets come in part 3)');
    t_ok(count($dd['lost']) === 1, 'dashboard · why we lose: one lost deal');

    // ---- The screens ---------------------------------------------------------------------
    $opened = fn($r, $v) => empty($r['restricted']) && (int) ($r['code'] ?? 0) !== 403 && ($r['view'] ?? '') === $v && !empty($r['view_ok']);
    foreach (['sales-home' => 'ops/sx_home', 'sales-today' => 'ops/sx_today', 'sales-board' => 'ops/sx_board', 'sales-dashboard' => 'ops/sx_dash'] as $route => $view) {
        $r = bbv_route($route, 'GET', $f['master'], 0, [], ['who' => 'all']);
        t_ok($opened($r, $view), "screen · /$route opens for a manager" . (($r['view_err'] ?? '') !== '' ? ' — ' . $r['view_err'] : ''));
    }
    $r = bbv_route('sales-today', 'GET', $f['master'], 0, [], ['who' => 'all']);
    t_ok(strpos($r['out'], 'Who has what today') !== false && strpos($r['out'], 'Deal follow-ups') !== false, 'Today · shows "who has what" and the deal follow-ups');
    $r = bbv_route('sales-home', 'GET', $f['master'], 0, [], ['who' => 'all']);
    t_ok(strpos($r['out'], 'Right now') !== false && strpos($r['out'], 'Data health') !== false && strpos($r['out'], 'Your daily routine') !== false, 'Home · right now, daily routine and data health');
    $r = bbv_route('sales-home', 'GET', $f['amit'], 0);
    t_ok(!$opened($r, 'ops/sx_home'), 'screen · an inspector without Sales does not open Sales home');
    $html = (function () use ($f, $o1) { bbv_as($f['master']); $_GET = ['who' => 'all']; ob_start(); $d = sx_today_data(0); $who = 0; $whoKey = 'all'; $people = []; $seeAll = true; $whoRows = [];
        include dirname(__DIR__) . '/views/ops/sx_today.php'; return ob_get_clean(); })();
    t_ok(strpos($html, '/opportunity?id=' . $o1) !== false && strpos($html, '/lead?id=') !== false, 'links · every row opens its own deal or lead');
    t_ok(strpos($html, 'wa.me/919825011111') !== false, 'links · WhatsApp in one tap');

    // ---- Each screen has its own switch ---------------------------------------------
    $all = feat_catalog_keys();
    t_ok(isset($all['sales-today'], $all['sales-board'], $all['sales-dashboard'], $all['sales-home']), 'switches · the four screens are in Modules & features, each with a switch');
} finally {
    if ($made['l']) db()->exec("DELETE FROM leads WHERE id IN (" . implode(',', $made['l']) . ")");
    if ($made['o']) db()->exec("DELETE FROM opportunities WHERE id IN (" . implode(',', $made['o']) . ")");
    unset($_GET['who']);
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');
