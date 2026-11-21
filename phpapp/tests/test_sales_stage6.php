<?php
// Owner, 21 Nov 2026 — Sales redesign Stage 6 (Sales settings, Set up Sales / Sales approvals,
// Pipelines & funnels, stage gates, Advertising return, Sales funnel report), approved design
// pack, "All as recommended". Preserve every field, button, link and figure; nobody's access
// changes. What the owner decided, each checked below:
//   DEF-13 — Sales settings links to a list only for someone who may open that list; the
//            others are named with who keeps them;
//   DEF-17 — /crm-dashboard is the "Sales funnel report"; its "Pipeline board" opens the Sales
//            pipeline board (for someone who may open it);
//   DEF-20 — the glance strip keeps its "actions only" choice (no management pulse worked out);
//   DEF-22 — Advertising return follows a won deal through its quotation and contract to the
//            work order (approved backend item 20);
//   F-02   — Sales home's "Where to go" tiles: only screens the person may open.
// Each screen is drawn twice through the production dispatcher, as the same person on the same
// data: with the OLD view (tests/fixtures/pre_stage6/) and the NEW one.
require_once __DIR__ . '/_bbv_fixture.php';

function s6_env() {
    $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
    return $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
}
function s6_draw($uid, $route, array $get = [], $old = false) {
    $root = dirname(__DIR__);
    $raw = (string) shell_exec(s6_env() . ($old ? ' STAGE6_OLD=1' : '') . ' php ' . escapeshellarg($root . '/tests/_stage6_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route) . ' ' . escapeshellarg(json_encode((object) $get)) . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['done' => 'no-json: ' . substr($raw, 0, 300), 'figs' => [], 'hrefs' => [], 'forms' => [], 'money' => [], 'nav' => [], 'bn' => [], 'refused' => false, 'len' => 0, 'text' => '', 'buttons' => [], 'flash' => []];
}
//  "Would this address open for this person?" — drawn through the dispatcher; a refusal is a
//  redirect, an error message, or the module-refusal screen.
function s6_opens($uid, $href) {
    $p = parse_url($href); $route = ltrim((string) ($p['path'] ?? ''), '/'); parse_str((string) ($p['query'] ?? ''), $get);
    $d = s6_draw($uid, $route, $get, false);
    $err = (bool) array_filter($d['flash'] ?? [], fn($f) => in_array($f['tag'] ?? '', ['error', 'danger'], true));
    return $d['done'] === 'return' && !$err && !$d['refused'] && !preg_match('/don.t have access to the|is not switched on for this|Only admins can|Only admin-level/i', (string) $d['text']);
}
function s6_form_split($sig) { return preg_match('/^(.*?) \[(.*)\]$/', $sig, $m) ? [$m[1], $m[2] === '' ? [] : explode(',', $m[2])] : [$sig, []]; }
function s6_as($uid, $fn) { $keep = $_SESSION['uid'] ?? null; bbv_as($uid); $r = $fn(); if ($keep) bbv_as($keep); return $r; }

t_section('Sales Stage 6 — settings, set-up, pipelines, stage gates, Advertising return and the funnel report: same figures, links, fields and access as before, in the new look');
$f = bbv_fixture();
$pdo = db();
$made = ['users' => [], 'leads' => [], 'opps' => [], 'al' => [], 'sp' => [], 'quotes' => [], 'oq' => [], 'calls' => [], 'jobs' => [], 'inv' => [], 'il' => [], 'gates' => [], 'greq' => []];
try {
    bbv_as($f['master']);
    leads_migrate(); opp_migrate(); ads_migrate(); if (function_exists('crm_migrate')) crm_migrate(); if (function_exists('books_migrate')) books_migrate(); if (function_exists('gate_migrate')) gate_migrate();

    $mk = function ($key, $role, $office, $scope = '', $perms = '') use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, permissions, email) VALUES (?,?,?,?,1,?,?,?,?)")
            ->execute([$f['tag'] . '_' . $key, ucfirst($key), 'T', $role, $office, $scope, $perms, $f['tag'] . $key . '@example.com']);
        return $made['users'][] = (int) $pdo->lastInsertId();
    };
    $bdm  = $mk('bdm', 'BUSINESS_DEV_MANAGER', $f['mum']);
    $kam  = $mk('kam', 'KEY_ACCOUNTS_MANAGER', $f['mum']);
    $mm   = $mk('mm', 'MARKETING_MANAGER', $f['mum'], 'ALL');
    $mex  = $mk('mex', 'MARKETING_EXECUTIVE', $f['mum']);
    //  A Sales-settings keeper who is not an administrator (DEF-13): may change Sales settings, may not open the lists.
    $sset = $mk('salesset', 'MARKETING_MANAGER', $f['mum'], 'ALL', PERM_DIFF_TAG . ',+crm.settings.manage');

    // ---- Advertising data: one campaign, Mumbai leads, a deal won through quotation → contract → work order ----
    $camp = 'S6-' . $f['tag'];
    $lead = function ($name) use ($pdo, $f, &$made) {
        $r = lead_create(['company_name' => $name . ' ' . uniqid(), 'contact_phone' => '9825011111', 'source' => 'REFERRAL', 'value' => 100000]);
        $pdo->prepare("UPDATE leads SET office_id=? WHERE id=?")->execute([$f['mum'], $r['id']]);
        return $made['leads'][] = (int) $r['id'];
    };
    $l1 = $lead('S6 chain lead'); $l2 = $lead('S6 no-quote lead'); $l3 = $lead('S6 direct order lead');
    foreach ([$l1, $l2, $l3] as $lid) {
        $pdo->prepare("INSERT INTO ads_leads (ext_id, lead_id, campaign_id, campaign_name, platform, contact_name, imported_at) VALUES (?,?,?,?,?,?,?)")
            ->execute(['s6' . $lid, $lid, $camp, 'Campaign ' . $camp, 'META', 'Test', date('c')]);
        $made['al'][] = (int) $pdo->lastInsertId();
    }
    $pdo->prepare("INSERT INTO ads_spend (campaign_id, campaign_name, platform, metric_date, spend, clicks) VALUES (?,?,?,?,?,?)")->execute([$camp, 'Campaign ' . $camp, 'META', date('Y-m-d'), 30000, 90]);
    $made['sp'][] = (int) $pdo->lastInsertId();
    $pipe = pipeline_default('OPPORTUNITY'); $won = ops_one("SELECT * FROM pipeline_stages WHERE pipeline_id=? AND kind='WON'", [$pipe['id']]);
    $wonDeal = function ($lid, $value, $callId = null) use ($pdo, $f, $pipe, $won, &$made) {
        $o = opp_create(['name' => 'S6 deal ' . uniqid(), 'partner_name' => 'S6 Co', 'value' => $value, 'pipeline_id' => $pipe['id']]);
        $pdo->prepare("UPDATE opportunities SET lead_id=?, office_id=?, status='WON', stage_id=?, call_id=?, won_at=? WHERE id=?")->execute([$lid, $f['mum'], $won['id'], $callId, date('c'), $o['id']]);
        return $made['opps'][] = (int) $o['id'];
    };
    $mkCall = function ($quoteId = null, $contract = '') use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO calls (call_code, executing_office_id, ibo_office_id, status, created_at, quotation_id, contract_number) VALUES (?,?,?,?,?,?,?)")
            ->execute(['S6W-' . uniqid(), $f['mum'], $f['mum'], 'OPEN', date('c'), $quoteId, $contract]);
        return $made['calls'][] = (int) $pdo->lastInsertId();
    };
    $invoice = function ($callId, $total) use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO jobs (call_id) VALUES (?)")->execute([$callId]); $jid = $made['jobs'][] = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO invoices (invoice_no, office_id, invoice_date, total, status) VALUES (?,?,?,?, 'ISSUED')")->execute(['S6I-' . $callId, $f['mum'], date('Y-m-d'), $total]);
        $iid = $made['inv'][] = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount) VALUES (?,?,?)")->execute([$iid, $jid, $total]);
        $made['il'][] = (int) $pdo->lastInsertId();
    };
    //  1. Won through quotation → contract → work order; the deal itself has no work-order link (DEF-22).
    $oChain = $wonDeal($l1, 200000);
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_name, subject, office_id, subtotal, gst_pct, total_amount, created_by, created_at, contract_number)
                   VALUES (?,0,1,'ACCEPTED',?,?,?,200000,18,236000,'test',?,?)")
        ->execute(['Q-S6-' . $f['tag'], 'S6 Co', 'S6 chain', $f['mum'], date('c'), 'S6C-' . $f['tag']]);
    $qChain = $made['quotes'][] = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO opportunity_quotes (opportunity_id, quotation_id) VALUES (?,?)")->execute([$oChain, $qChain]);
    $made['oq'][] = (int) $pdo->lastInsertId();
    $cChain = $mkCall($qChain, 'S6C-' . $f['tag']);
    $invoice($cChain, 236000);
    //  2. Won with no quotation and no work order — still reported as "never ordered".
    $oBare = $wonDeal($l2, 90000);
    //  3. Won with its own work-order link (the old path) — unchanged.
    $cDirect = $mkCall(); $invoice($cDirect, 59000);
    $oDirect = $wonDeal($l3, 50000, $cDirect);

    // ---- Stage-gate rules: one used (it has held a request), one not ----
    $pdo->prepare("INSERT INTO stage_gates (name, entity_kind, approver_role, active, created_at) VALUES (?, 'OPPORTUNITY', 'BRANCH_MANAGER', 1, ?)")->execute(['S6 used rule ' . $f['tag'], date('c')]);
    $gUsed = $made['gates'][] = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO stage_gate_requests (entity_kind, entity_id, gate_id, approver_role, status) VALUES ('OPPORTUNITY', ?, ?, 'BRANCH_MANAGER', 'APPROVED')")->execute([$oDirect, $gUsed]);
    $made['greq'][] = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO stage_gates (name, entity_kind, approver_role, active, created_at, min_amount) VALUES (?, 'OPPORTUNITY', 'SBU_HEAD', 1, ?, 1000000)")->execute(['S6 fresh rule ' . $f['tag'], date('c')]);
    $gFresh = $made['gates'][] = (int) $pdo->lastInsertId();

    $people = ['Business Director' => $f['bd'], 'Master admin' => $f['master'], 'Admin' => $f['admin'], 'Business Unit Head' => $f['sbu'],
               'Branch Manager (Mumbai)' => $f['kiran'], 'Finance, Mumbai' => $f['meera'], 'Finance, all branches' => $f['rakesh'], 'Coordinator' => $f['neha'],
               'BDM (Mumbai)' => $bdm, 'KAM (Mumbai)' => $kam, 'Marketing Manager (all)' => $mm, 'Marketing Executive (Mumbai)' => $mex,
               'Sales-settings keeper (not an administrator)' => $sset];
    $pages = [['sales-settings', []], ['sales-setup', []], ['sales-approvals', []], ['sales-approvals', ['tab' => 'contract']],
              ['pipelines', []], ['pipeline', ['id' => (int) $pipe['id']]], ['stage-gates', []], ['stage-gates', ['edit' => $gUsed]],
              ['ads-roi', []], ['crm-dashboard', []], ['crm-dashboard', ['days' => 365]], ['sales-home', []]];

    //  THE CHANGES THE OWNER DECIDED — a link may leave the page only for these reasons, each checked:
    $openCache = [];
    $opens = function ($uid, $href) use (&$openCache) { return $openCache[$uid . ' ' . $href] ??= s6_opens($uid, $href); };
    $gone = function ($uid, $route, $href) use ($opens) {
        //  DEF-13: Sales settings links to a list (or to the stage editor) only for someone who may open it.
        if ($route === 'sales-settings' && preg_match('#^/(lookup\?|m/holidays|sales-setup|pipelines)#', $href)) return !$opens($uid, $href);
        //  F-02: a Sales home tile only for someone who may open its screen.
        if ($route === 'sales-home' && in_array($href, ['/sales-today', '/sales-board', '/sales-dashboard', '/leads', '/opportunities', '/quotes', '/inquiries', '/sales-orders', '/sales-renewals', '/sales-targets', '/clients?view=sales'], true))
            return !$opens($uid, $href);
        //  DEF-17: "Pipeline board" opens the Sales pipeline board for someone who may open it.
        if ($route === 'crm-dashboard' && $href === '/opportunities') return s6_as($uid, fn() => sx_can());
        //  Owner findings B1 (21 Nov 2026): the "New quote" tile was /quote-edit with no quotation
        //  ("Not found"); it is /quote-new now, and only for someone who may write a quotation.
        if ($route === 'sales-home' && $href === '/quote-edit') return true;
        return false;
    };
    $compare = function ($label, $uid, $route, $get) use ($gone) {
        $o = s6_draw($uid, $route, $get, true); $n = s6_draw($uid, $route, $get, false);
        $pg = '/' . $route . ($get ? '?' . http_build_query($get) : '');
        t_eq($n['done'], $o['done'], "$label · $pg — the page opens (or is refused) exactly as before");
        t_eq($n['refused'], $o['refused'], "$label · $pg — refused as before");
        $fd = []; foreach ($o['figs'] as $k => $v) if ($k !== '' && ($n['figs'][$k] ?? null) !== $v) $fd[] = "$k: old \"$v\" new \"" . ($n['figs'][$k] ?? '—') . '"';
        t_eq($fd, [], "$label · $pg — all " . count($o['figs']) . ' figures read the same');
        t_eq(array_values(array_diff($o['money'], $n['money'])), [], "$label · $pg — every amount of the old page is on the new one");
        $lostL = array_values(array_filter(array_diff(array_unique($o['hrefs']), $n['hrefs']), fn($h) => !$gone($uid, $route, $h)));
        t_eq($lostL, [], "$label · $pg — every link of the old page is on the new one (owner-decided changes listed above)");
        $lost = [];
        foreach (array_unique($o['forms']) as $sig) {
            [$at, $fl] = s6_form_split($sig); $ok = false;
            foreach ($n['forms'] as $ns) { [$nat, $nfl] = s6_form_split($ns); if ($nat === $at && !array_diff($fl, $nfl)) { $ok = true; break; } }
            if (!$ok) $lost[] = $sig;
        }
        t_eq($lost, [], "$label · $pg — every form (same address, every field) is on the new one");
        $nb = array_count_values($n['buttons']);
        $lb = []; foreach (array_count_values($o['buttons']) as $b => $c) if ($b !== '' && ($nb[$b] ?? 0) < $c && !($route === 'stage-gates' && $b === 'Remove')) $lb[] = $b;
        t_eq($lb, [], "$label · $pg — every button of the old page is on the new one");
        return [$o, $n];
    };
    $draws = [];
    foreach ($people as $label => $uid) foreach ($pages as [$route, $get]) $draws[$label][$route . json_encode($get)] = $compare($label, $uid, $route, $get);

    t_section('Sales Stage 6 — the Sales look on every screen');
    foreach (['sales-settings[]', 'sales-setup[]', 'pipelines[]', 'stage-gates[]', 'ads-roi[]', 'crm-dashboard[]'] as $k) {
        $n = $draws['Master admin'][$k][1];
        t_ok(in_array('/sales-home', $n['nav'], true) && in_array('/sales-home', $n['bn'], true), "$k — the Sales bar and the phone bottom bar (Master admin)");
    }
    $pe = $draws['Master admin']['pipeline' . json_encode(['id' => (int) $pipe['id']])][1];
    t_ok(in_array('/pipelines', $pe['hrefs'], true) && strpos($pe['text'], 'All pipelines') !== false, 'Pipelines: the edit screen has a way back to the list');
    t_ok(strpos($pe['text'], 'In use (offered for new work)') !== false && strpos($draws['Master admin']['pipelines[]'][1]['text'], 'Records on it') !== false, 'Pipelines: "In use" now says what it means on each screen');
    $sq = $draws['Master admin']['sales-setup[]'][1];
    t_ok(strpos($sq['text'], 'saved by its own') !== false, 'Set up Sales: it says the level cards save on their own');
    t_ok((bool) array_filter($sq['hrefs'], fn($h) => strpos($h, '#sq-') === 0), 'Set up Sales: jump links to each part of the page');

    t_section('Sales Stage 6 — DEF-13: Sales settings links only to lists the person may open');
    $ss = $draws['Sales-settings keeper (not an administrator)']['sales-settings[]'][1];
    t_ok(!$ss['refused'] && $ss['done'] === 'return', 'a non-administrator with "Change Sales settings" opens Sales settings (as before)');
    t_ok(!array_filter($ss['hrefs'], fn($h) => strpos($h, '/lookup') === 0 || strpos($h, '/m/holidays') === 0), 'they are not offered the lead-source, loss-reason, buying-role or holiday lists — those refuse them');
    t_ok(!s6_opens($sset, '/lookup?key=lead_source') && !s6_opens($sset, '/m/holidays'), '…and those screens do refuse them (the reason the links went)');
    t_ok(strpos($ss['text'], 'kept by an administrator') !== false, '…instead each list is named, with who keeps it');
    $ms = $draws['Master admin']['sales-settings[]'][1];
    foreach (['/lookup?key=lead_source', '/lookup?key=quote_lost_reason', '/lookup?key=contact_buying_role', '/m/holidays', '/sales-setup'] as $h)
        t_ok(in_array($h, $ms['hrefs'], true) && s6_opens($f['master'], $h), "Master admin is offered $h, and it opens");
    t_ok(strpos($ms['text'], 'kept by') === false, 'Master admin sees no "kept by" note');

    t_section('Sales Stage 6 — DEF-17: the Sales funnel report');
    $cd = $draws['Master admin']['crm-dashboard[]'][1];
    t_ok(strpos($cd['text'], 'Sales funnel report') !== false && substr_count($cd['text'], 'Sales dashboard') === (in_array('/sales-dashboard', $cd['nav'], true) ? 1 : 0), '/crm-dashboard is called "Sales funnel report"; "Sales dashboard" is only the Sales bar\'s link to /sales-dashboard');
    $cdo = $draws['Master admin']['crm-dashboard[]'][0];
    t_ok(in_array('/sales-board', $cd['hrefs'], true) && !in_array('/sales-board', $cdo['hrefs'], true), '"Pipeline board" opens the Sales pipeline board (it opened the deals register)');
    t_ok(strpos($cd['text'], 'Window · last') !== false && strpos($cd['text'], '12 months') !== false, 'the rolling window says so; its choices are unchanged');
    $ins = s6_as($f['master'], fn() => ops_area_def('insights'));
    $labs = []; foreach (($ins['sections'] ?? []) as $sec) foreach ($sec['tiles'] as $t) $labs[$t['route']] = $t['label'];
    t_eq($labs['/crm-dashboard'] ?? '', 'Sales funnel report', 'Insights → the tile reads "Sales funnel report"');
    $cdFin = s6_draw($f['rakesh'], 'crm-dashboard', [], false);
    $cdFinOld = s6_draw($f['rakesh'], 'crm-dashboard', [], true);
    t_eq($cdFin['refused'] || $cdFin['done'] !== 'return', $cdFinOld['refused'] || $cdFinOld['done'] !== 'return', 'who may open the funnel report is unchanged (Finance as before)');

    t_section('Sales Stage 6 — stage gates');
    $sg = $draws['Master admin']['stage-gates[]'][1];
    t_ok(strpos($sg['text'], ORG_ROLES['BRANCH_MANAGER'] ?? 'Branch Manager') !== false && strpos($sg['text'], 'BRANCH_MANAGER') === false, 'the approver column names the role in words, not its code');
    t_ok(in_array('Switch off', $sg['buttons'], true) && in_array('Remove', $sg['buttons'], true), 'a rule that has held requests offers "Switch off"; an unused one "Remove"');
    $before = (int) ops_val("SELECT COUNT(*) FROM stage_gates WHERE id=?", [$gUsed]);
    t_eq(gate_rule_delete($gUsed) !== '' && (int) ops_val("SELECT active FROM stage_gates WHERE id=?", [$gUsed]) === 0 && $before === 1, true, '…and pressing it switches the rule off, kept for its history (the server rule, unchanged)');

    t_section('Sales Stage 6 — DEF-22: Advertising return follows a deal won through its quotation and contract');
    $find = function ($withMoney) use ($camp) { foreach (roi_by_campaign('', '', $withMoney) as $b) if ($b['campaign_id'] === $camp) return $b; return null; };
    bbv_as($f['master']);
    $c = $find(true);
    t_eq([(int) $c['leads'], (int) $c['won']], [3, 3], 'three leads, three deals won');
    t_eq((float) $c['invoiced'], 295000.0, 'invoiced ₹2,95,000 = ₹2,36,000 through quotation → contract → work order + ₹59,000 through the deal\'s own work-order link (was ₹59,000)');
    t_eq($c['breaks']['won-no-order'] ?? 0, 1, 'only the deal with no quotation and no work order is still reported "Won, but no order was ever raised" (was 2)');
    t_eq(roi_calls_via_quote($oChain), [$cChain], 'the chain: the deal\'s quotation → the work order raised from it');
    t_eq(roi_calls_via_quote($oBare), [], 'a deal with no quotation reaches no work order');
    $pdo->prepare("UPDATE calls SET quotation_id=NULL WHERE id=?")->execute([$cChain]);
    t_eq(roi_calls_via_quote($oChain), [$cChain], '…the contract number alone also leads to it');
    $pdo->prepare("UPDATE calls SET quotation_id=? WHERE id=?")->execute([$qChain, $cChain]);
    bbv_as($mex);
    $cm = $find(null);
    t_eq([(float) $cm['invoiced'], isset($cm['breaks']['order-no-invoice']) || isset($cm['breaks']['invoiced-unpaid'])], [0.0, false], 'without the receivables right no invoice is read, through the chain either (DEF-01 unchanged)');
    t_eq($cm['breaks']['won-no-order'] ?? 0, 1, '…but the "never ordered" break is the same for them');
    bbv_as($f['master']);

    t_section('Sales Stage 6 — F-02: Sales home tiles are only screens the person may open');
    foreach ($people as $label => $uid) {
        $h = $draws[$label]['sales-home[]'][1];
        if ($h['done'] !== 'return' || $h['refused']) continue;
        $tiles = array_values(array_intersect(array_unique($h['hrefs']), ['/sales-today', '/sales-board', '/sales-dashboard', '/leads', '/opportunities', '/quotes', '/inquiries', '/sales-orders', '/sales-renewals', '/sales-targets', '/clients?view=sales']));
        $dead = array_values(array_filter($tiles, fn($t) => !$opens($uid, $t)));
        t_eq($dead, [], "$label — every Sales home tile shown opens (" . count($tiles) . ' tiles)');
    }

    t_section('Sales Stage 6 — DEF-20: the glance strip keeps its "actions only" choice');
    $src = (string) file_get_contents(dirname(__DIR__) . '/views/ops/_glance_strip.php');
    t_eq(substr_count($src, "function_exists('dashboard_glance') ? dashboard_glance()"), 1, 'the strip asks for the management pulse in one place only — the "wanted" branch');
    $draw = function ($want) { $glanceWantMgmt = $want; ob_start(); include dirname(__DIR__) . '/views/ops/_glance_strip.php'; return (string) ob_get_clean(); };
    bbv_as($f['master']);
    $ao = $draw(false); $al = $draw(true);
    $acts = array_map(fn($a) => $a['href'], action_centre(4));
    preg_match_all('/<a href="([^"]+)" style="display:flex;align-items:center;gap:10px/', $ao, $m1);
    preg_match_all('/<a href="([^"]+)" style="display:flex;align-items:center;gap:10px/', $al, $m2);
    t_eq(array_map('html_entity_decode', $m1[1]), $acts, 'actions only (Dashboard, Operations home): the same ranked actions as My Work, unchanged');
    t_eq($m1[1], $m2[1], '…and the same actions as on the area homes');
    t_ok(strpos($ao, 'Needs attention') === false, 'actions only: the management pulse is not drawn (and so not worked out)');
    t_ok(strpos($al, 'Needs attention') !== false, 'area homes (pulse wanted): the management pulse is drawn, as before');

    t_section('Sales Stage 6 — Set up Sales wording');
    $lv = (string) file_get_contents(dirname(__DIR__) . '/views/ops/crm/approval_levels_inline.php');
    t_ok(strpos($lv, 'needs no approval') !== false, 'no levels: the page says a quotation then needs no approval (rule 19k), not just "add the first one"');
} finally {
    $del = function ($table, $ids) use ($pdo) { if ($ids) try { $pdo->exec("DELETE FROM $table WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")"); } catch (Throwable $e) {} };
    $del('stage_gate_requests', $made['greq']); $del('stage_gates', $made['gates']);
    $del('invoice_lines', $made['il']); $del('invoices', $made['inv']); $del('jobs', $made['jobs']); $del('calls', $made['calls']);
    $del('opportunity_quotes', $made['oq']); $del('quotations', $made['quotes']);
    $del('opportunities', $made['opps']); $del('ads_leads', $made['al']); $del('ads_spend', $made['sp']); $del('leads', $made['leads']);
    $del('users', $made['users']);
    bbv_cleanup($f);
}
