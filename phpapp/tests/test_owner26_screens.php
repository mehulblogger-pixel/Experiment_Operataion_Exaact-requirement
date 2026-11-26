<?php
// Owner, 26 Nov 2026 — "go ahead and fix everything as recommended", screens batch:
//   C1 (O-08)       Renewals → Log a call no longer runs off a phone screen;
//   C2 (OS22-02)    the ⌂ Sales home button also on the customer register's Sales view and on
//                   a client page / 360 opened from Sales; "← Sales" reads "← All Sales screens";
//   C3 (OS22-06, R-03) the KPI cards on Leads, Deals and Renewals open exactly what they count;
//   C4 (R-05)       a Branch filter on the Sales screens for people who see more than one branch —
//                   it only narrows, a one-branch person never sees it, a typed branch outside
//                   the person's branches changes nothing, and the CSV follows it.
t_section('Owner 26 Nov 2026 — Sales screens: phone log form, Sales home button, clickable cards, Branch filter');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
putenv('BBV_RAW=1');
$pdo = db();
$tag = $f['tag'];
$made = ['leads' => [], 'opps' => [], 'users' => []];
try {
    if (function_exists('leads_migrate')) leads_migrate();
    if (function_exists('opp_migrate')) opp_migrate();
    // A BDM who works in Ahmedabad only.
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices) VALUES (?,?,?,?,1,?,?)")
        ->execute([$tag . '_bdm', 'Bdm', 'T', 'BUSINESS_DEV_MANAGER', $f['ahm'], (string) $f['ahm']]);
    $bdm = (int) $pdo->lastInsertId();
    $now = date('c');
    $lead = function ($ref, $office, $status = 'OPEN', $owner = null) use ($pdo, $now, &$made) {
        $pdo->prepare("INSERT INTO leads (ref, company_name, office_id, status, value, owner_user_id, created_at, stage_since) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$ref, 'Co ' . $ref, $office, $status, 100000, $owner, $now, $now]);
        return $made['leads'][] = (int) $pdo->lastInsertId();
    };
    $opp = function ($ref, $office, $status = 'OPEN') use ($pdo, $now, &$made) {
        $pdo->prepare("INSERT INTO opportunities (ref, name, office_id, status, value, created_at, stage_since, updated_at) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$ref, 'Deal ' . $ref, $office, $status, 50000, $now, $now, $now]);
        return $made['opps'][] = (int) $pdo->lastInsertId();
    };
    $lead($tag . '-LM1', $f['mum']); $lead($tag . '-LM2', $f['mum']); $lead($tag . '-LMW', $f['mum'], 'CONVERTED');
    $lead($tag . '-LA1', $f['ahm'], 'OPEN', $bdm);
    $opp($tag . '-DM1', $f['mum']); $opp($tag . '-DA1', $f['ahm']); $opp($tag . '-DA2', $f['ahm']);
    $opp($tag . '-DAW', $f['ahm'], 'WON');

    $kpi = function ($html, $key) {   // the number on a card, by its data-k
        if (!preg_match('/data-k="' . preg_quote(htmlspecialchars($key, ENT_QUOTES), '/') . '[^"]*"[^>]*>.*?data-v>([^<]*)</s', $html, $m)) return null;
        return trim(html_entity_decode($m[1]));
    };
    $html = fn($r) => (string) ($r['html'] ?? '');
    $dir = $f['bd'];

    // =================================================================== C4 — Branch filter
    $r = bbv_route('leads', 'GET', $dir, 0, [], ['v' => 'list', 'fy' => 'ALL', 'br' => (string) $f['mum']]);
    $h = $html($r);
    t_ok(strpos($h, 'name="br"') !== false, 'C4 · a Business Director (every branch) is offered the Branch filter on Leads' . ($h === '' ? ' — ' . json_encode(array_intersect_key($r, ['code' => 1, 'view_err' => 1, 'raw' => 1])) : ''));
    t_ok(strpos($h, $tag . '-LM1') !== false && strpos($h, $tag . '-LM2') !== false, 'C4 · Mumbai picked → the Mumbai leads are listed');
    t_ok(strpos($h, $tag . '-LA1') === false, 'C4 · Mumbai picked → the Ahmedabad lead is not');
    t_eq($kpi($h, 'Open'), (string) (int) ops_val("SELECT COUNT(*) FROM leads WHERE status='OPEN' AND office_id=?", [$f['mum']]), 'C4 · the Open card counts Mumbai only (2)');
    t_ok(preg_match('/<option value="' . $f['mum'] . '" selected>/', $h) === 1, 'C4 · the filter shows Mumbai as picked');

    $r = bbv_route('leads', 'GET', $dir, 0, [], ['v' => 'list', 'fy' => 'ALL', 'br' => '999999']);
    $h = $html($r);
    t_ok(strpos($h, $tag . '-LM1') !== false && strpos($h, $tag . '-LA1') !== false, 'C4 · a typed branch the person does not see is ignored (all their branches shown)');

    $r = bbv_route('leads', 'GET', $bdm, 0, [], ['v' => 'list', 'fy' => 'ALL']);
    $hb = $html($r);
    t_ok(strpos($hb, 'name="br"') === false, 'C4 · a BDM who works in one branch (Ahmedabad) sees no Branch filter');
    t_ok(strpos($hb, $tag . '-LA1') !== false, 'C4 · … and still sees their Ahmedabad lead');
    $r = bbv_route('leads', 'GET', $bdm, 0, [], ['v' => 'list', 'fy' => 'ALL', 'br' => (string) $f['mum']]);
    $hb2 = $html($r);
    t_ok(strpos($hb2, $tag . '-LA1') !== false && strpos($hb2, $tag . '-LM1') === false, 'C4 · a typed br=<Mumbai> by the one-branch BDM changes nothing (Ahmedabad lead still there, no Mumbai lead)');
    t_eq($kpi($hb2, 'Open'), $kpi($hb, 'Open'), 'C4 · … and the BDM\'s cards are the same with or without it');

    // The CSV follows the filter.
    $r = bbv_route('leads', 'GET', $dir, 0, [], ['v' => 'list', 'fy' => 'ALL', 'br' => (string) $f['mum'], 'export' => 'csv']);
    $csv = $html($r);
    t_ok(strpos($csv, $tag . '-LM1') !== false && strpos($csv, $tag . '-LA1') === false, 'C4 · the Leads CSV carries only the picked branch');

    // Deals, Enquiries and the four Sales workspace screens.
    $r = bbv_route('opportunities', 'GET', $dir, 0, [], ['v' => 'list', 'fy' => 'ALL', 'br' => (string) $f['ahm']]);
    $h = $html($r);
    t_ok(strpos($h, 'name="br"') !== false && strpos($h, $tag . '-DA1') !== false && strpos($h, $tag . '-DM1') === false, 'C4 · Deals: Ahmedabad picked → only Ahmedabad deals');
    t_eq($kpi($h, 'Open'), (string) (int) ops_val("SELECT COUNT(*) FROM opportunities WHERE status='OPEN' AND office_id=?", [$f['ahm']]), 'C4 · Deals: the Open card counts Ahmedabad only (2)');
    $r = bbv_route('opportunities', 'GET', $dir, 0, [], ['v' => 'list', 'fy' => 'ALL', 'br' => (string) $f['ahm'], 'export' => 'csv']);
    t_ok(strpos($html($r), $tag . '-DA1') !== false && strpos($html($r), $tag . '-DM1') === false, 'C4 · the Deals CSV follows the filter');
    $r = bbv_route('inquiries', 'GET', $dir, 0, [], ['br' => (string) $f['mum']]);
    t_ok(!empty($r['view_ok']) && strpos($html($r), 'name="br"') !== false, 'C4 · Enquiries: the register opens with the Branch filter' . (empty($r['view_ok']) ? ' — ' . ($r['view_err'] ?? '') : ''));
    foreach (['sales-home', 'sales-today', 'sales-board', 'sales-dashboard'] as $sc) {
        $r = bbv_route($sc, 'GET', $dir, 0, [], ['who' => 'all', 'br' => (string) $f['mum']]);
        t_ok(!empty($r['view_ok']) && strpos($html($r), 'name="br"') !== false, "C4 · /$sc opens with the Branch filter (Mumbai picked)" . (empty($r['view_ok']) ? ' — ' . ($r['view_err'] ?? json_encode(array_intersect_key($r, ['code' => 1, 'raw' => 1]))) : ''));
    }
    $r = bbv_route('sales-board', 'GET', $dir, 0, [], ['who' => 'all', 'br' => (string) $f['mum']]);
    $h = $html($r);
    t_ok(strpos($h, $tag . '-DA1') === false && strpos($h, 'Deal ' . $tag . '-DA1') === false, 'C4 · Pipeline board, Mumbai picked: no Ahmedabad deal card');

    // In-process: the pick is remembered per screen, only on GET, only for the person's branches.
    bbv_as($dir);
    if (!isset($_SESSION) || !is_array($_SESSION)) $_SESSION = [];
    $_GET = ['br' => (string) $f['mum']];
    t_eq(sx_branch_begin('leads', 'GET'), $f['mum'], 'C4 · picking Mumbai on Leads narrows this request to Mumbai');
    $_GET = [];
    t_eq(sx_branch_begin('leads', 'GET'), $f['mum'], 'C4 · opening Leads again without a pick keeps Mumbai (remembered for the session)');
    t_eq(sx_branch_begin('opportunities', 'GET'), 0, 'C4 · … per screen: Deals is not narrowed by the Leads pick');
    t_eq(sx_branch_begin('leads', 'POST'), 0, 'C4 · a save (POST) is never narrowed');
    t_eq(sx_branch_begin('quotes', 'GET'), 0, 'C4 · a screen outside the seven is never narrowed (Quotes keeps its own filter)');
    $_GET = ['br' => ''];
    t_eq(sx_branch_begin('leads', 'GET'), 0, 'C4 · "All my branches" clears the pick');
    $_GET = [];
    t_eq(sx_branch_begin('leads', 'GET'), 0, 'C4 · … and it stays cleared');
    $GLOBALS['__sales_branch'] = $f['mum'];
    [$sw] = scope_sales_clause('l');
    t_ok(strpos($sw, 'l.office_id = ' . $f['mum']) !== false, 'C4 · the pick is added to the Sales scope clause (narrows only)');
    $GLOBALS['__sales_branch'] = 0;
    [$sw] = scope_sales_clause('l');
    t_ok(strpos($sw, 'l.office_id = ') === false, 'C4 · no pick → the scope clause is exactly as before');
    bbv_as($bdm);
    $_GET = ['br' => (string) $f['mum']];
    t_eq(sx_branch_begin('leads', 'GET'), 0, 'C4 · a one-branch person: a typed branch sets nothing');
    t_eq(sx_branch_select('leads'), '', 'C4 · … and no control is drawn');
    $_GET = [];

    // =================================================================== C3 — clickable cards
    $r = bbv_route('leads', 'GET', $dir, 0, [], ['v' => 'board', 'fy' => 'ALL', 'br' => (string) $f['mum']]);
    $h = $html($r);
    foreach (['open', 'stalled', 'due', 'won', 'lost'] as $t)
        t_ok(strpos($h, 'tile=' . $t) !== false, "C3 · Leads: a card opens tile=$t");
    t_ok(preg_match('/<a class="kpi sxw-kpi[^"]*" data-k="Open[^"]*" href="\/leads\?v=list&amp;tile=open/', $h) === 1, 'C3 · Leads: the Open card is a link (a.kpi — hover and focus ring)');
    $won = $kpi($h, 'Won');
    $r = bbv_route('leads', 'GET', $dir, 0, [], ['v' => 'list', 'fy' => 'ALL', 'tile' => 'won', 'br' => (string) $f['mum']]);
    $h2 = $html($r);
    t_ok(strpos($h2, 'Showing: <b>' . $won . '</b> · leads won') !== false && strpos($h2, 'Show all') !== false, "C3 · Leads: Won card ($won) → \"Showing: $won leads won …\" with Show all");
    t_ok(strpos($h2, $tag . '-LMW') !== false && strpos($h2, $tag . '-LM1') === false, 'C3 · … and the list holds the won lead only');
    $open = $kpi($h, 'Open');
    $r = bbv_route('leads', 'GET', $dir, 0, [], ['v' => 'list', 'fy' => 'ALL', 'tile' => 'open', 'br' => (string) $f['mum']]);
    t_ok(strpos($html($r), 'Showing: <b>' . $open . '</b> · open leads') !== false, "C3 · Leads: Open card ($open) = rows of its list");
    $r = bbv_route('leads', 'GET', $dir, 0, [], ['v' => 'list', 'fy' => 'ALL', 'tile' => 'due']);
    $dueN = $kpi($html($r), 'Need attention');
    t_ok(strpos($html($r), 'Showing: <b>' . $dueN . '</b> · leads that need attention') !== false, "C3 · Leads: Need attention card ($dueN) = rows of its list");

    $r = bbv_route('opportunities', 'GET', $dir, 0, [], ['v' => 'board', 'br' => (string) $f['ahm']]);
    $h = $html($r);
    foreach (['open', 'stalled', 'closed'] as $t) t_ok(strpos($h, 'tile=' . $t) !== false, "C3 · Deals: a card opens tile=$t");
    $o = $kpi($h, 'Open');
    $r = bbv_route('opportunities', 'GET', $dir, 0, [], ['v' => 'list', 'tile' => 'open', 'br' => (string) $f['ahm']]);
    t_ok(strpos($html($r), 'Showing: <b>' . $o . '</b> · open') !== false, "C3 · Deals: Open card ($o) = rows of its list");
    $wl = $kpi($h, 'Won · Lost'); $sum = array_sum(array_map('intval', explode('·', (string) $wl)));
    $r = bbv_route('opportunities', 'GET', $dir, 0, [], ['v' => 'list', 'tile' => 'closed', 'br' => (string) $f['ahm']]);
    t_ok(strpos($html($r), 'Showing: <b>' . $sum . '</b> · ') !== false && strpos($html($r), $tag . '-DAW') !== false, "C3 · Deals: Won · Lost card ($wl) → its $sum deals, the won one listed");

    // Renewals: the view, with three contracts (one per band).
    bbv_as($f['master']);
    require_once __DIR__ . '/../views/ops/_sxw_ui.php';
    $rv = function ($band) {
        $rows = [['id' => 1, 'contract_number' => 'RNW-LATE', 'title' => '', 'partner_id' => 0, 'end_date' => date('Y-m-d', strtotime('-5 days')), 'value' => 0],
                 ['id' => 2, 'contract_number' => 'RNW-SOON', 'title' => '', 'partner_id' => 0, 'end_date' => date('Y-m-d', strtotime('+10 days')), 'value' => 0],
                 ['id' => 3, 'contract_number' => 'RNW-SOON2', 'title' => '', 'partner_id' => 0, 'end_date' => date('Y-m-d', strtotime('+20 days')), 'value' => 0],
                 ['id' => 4, 'contract_number' => 'RNW-LATER', 'title' => '', 'partner_id' => 0, 'end_date' => date('Y-m-d', strtotime('+50 days')), 'value' => 0]];
        $days = 60; $opts = [30, 60, 90, 180]; $def = 60; $example = false; $money = false; $canLog = false; $canQuote = false; $canContract = false;
        ob_start(); include __DIR__ . '/../views/ops/sx_renewals.php'; return (string) ob_get_clean();
    };
    $h = $rv('');
    foreach (['late', 'm', 'later'] as $b) t_ok(strpos($h, 'band=' . $b) !== false, "C3 · Renewals: a card opens band=$b");
    $h = $rv('m');
    t_ok(strpos($h, 'RNW-SOON') !== false && strpos($h, 'RNW-SOON2') !== false && strpos($h, 'RNW-LATE<') === false && strpos($h, 'RNW-LATER') === false,
         'C3 · Renewals: "Ending in 30 days" shows only those two contracts');
    t_ok(strpos($h, 'Showing: <b>2</b> · contracts ending in 30 days') !== false && strpos($h, 'Show all') !== false, 'C3 · … the card said 2, the list says "Showing: 2 …" with Show all');
    t_ok(preg_match('/data-k="Ending in 30 days"[^>]*>.*?data-v>2</s', $h) === 1, 'C3 · … and the card still counts the whole window (2)');

    // =================================================================== C2 — Sales home button
    bbv_as($f['master']);
    t_ok(nav_is_sales_screen('clients', ['view' => 'sales']), 'C2 · the customer register\'s Sales view is a Sales screen');
    t_ok(!nav_is_sales_screen('clients', []), 'C2 · the customer register opened from the Directory is not');
    t_ok(nav_is_sales_screen('partner', ['from' => 'sales']) && nav_is_sales_screen('customer', ['from' => 'sales']), 'C2 · a client page / customer 360 opened from Sales is');
    t_ok(!nav_is_sales_screen('partner', ['id' => 5]), 'C2 · the same client page opened from the Directory is not');
    t_ok(nav_sales_home_button('clients', ['url' => '/sales', 'label' => 'Sales'], ['view' => 'sales']), 'C2 · so the ⌂ Sales home button is drawn on the Sales view of the register');
    t_ok(nav_sales_home_button('leads', ['url' => '/sales', 'label' => 'Sales'], []), 'C2 · Leads still has it');
    t_ok(!nav_sales_home_button('sales-home', null, []), 'C2 · never on Sales home itself');
    t_ok(!nav_sales_home_button('leads', ['url' => '/sales-home', 'label' => 'Sales home'], []), 'C2 · never twice (Back already goes to Sales home)');
    bbv_as($f['rakesh']);   // central Finance — refused Sales home (D1)
    t_ok(!nav_sales_home_button('clients', ['url' => '/sales', 'label' => 'Sales'], ['view' => 'sales']), 'C2 · Finance (who may not open Sales home) never gets it');
    t_eq(nav_back_words(['url' => '/sales', 'label' => 'Sales']), 'All Sales screens', 'C2 · Back to the Sales menu reads "All Sales screens"');
    t_eq(nav_back_words(['url' => '/leads', 'label' => 'Leads']), 'Leads', 'C2 · any other Back keeps its own words');
    $lt = file_get_contents(__DIR__ . '/../views/layout_top.php');
    t_ok(strpos($lt, 'nav_sales_home_button($cur, $__back)') !== false && strpos($lt, 'nav_back_words($__back)') !== false, 'C2 · the layout draws both through the one rule');
    $ls = file_get_contents(__DIR__ . '/../views/list.php');
    t_eq(substr_count($ls, '&amp;from=sales'), 4, 'C2 · the Sales view\'s client and 360 links carry from=sales');
    t_ok(strpos(file_get_contents(__DIR__ . '/../views/ops/sx_orders.php'), '&amp;from=sales') !== false
         && strpos(file_get_contents(__DIR__ . '/../views/ops/sx_renewals.php'), '&amp;from=sales') !== false, 'C2 · so do Orders & payments and Renewals');

    // =================================================================== C1 — phone log form
    $css = file_get_contents(__DIR__ . '/../assets/css/app.css');
    t_ok(strpos($css, '.sx5-log form{min-width:240px;white-space:normal}') !== false, 'C1 · the log form wraps normally inside the nowrap table cell');
    t_ok(preg_match('/@media \(max-width:640px\)\{\s*\.sx5-log\[open\]\{position:fixed;left:12px;right:12px;bottom:calc\(84px \+ env\(safe-area-inset-bottom,0px\)\);\s*z-index:60/', $css) === 1,
         'C1 · on a phone it opens as a panel above the bottom bar (z-index 60 > 45)');
    t_ok(strpos(file_get_contents(__DIR__ . '/../views/ops/sx_renewals.php'), 'min-width:240px') === false, 'C1 · the inline 240 px minimum on the form is gone');
} finally {
    putenv('BBV_RAW');
    $GLOBALS['__sales_branch'] = 0; $_GET = [];
    if ($made['leads']) $pdo->exec("DELETE FROM leads WHERE id IN (" . implode(',', $made['leads']) . ")");
    if ($made['opps']) $pdo->exec("DELETE FROM opportunities WHERE id IN (" . implode(',', $made['opps']) . ")");
    bbv_cleanup($f);
}
