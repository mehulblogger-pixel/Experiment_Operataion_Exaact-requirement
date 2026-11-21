<?php
// DEF-01 (security review, 21 Nov 2026; owner: "proceed with your recommendation").
// "What the advertising actually earned" (/ads-roi) opens on Leads view, but it
// showed INVOICED and COLLECTED money (receivables) to every Leads viewer, and
// for every branch. The permission matrix (docs/02) shows receivables only to
// ar_can() and limits every register to the person's own branches.
//
// After the fix:
//   * leads and deals on the screen are the viewer's branches only;
//   * invoiced / collected / not yet collected appear only for ar_can();
//   * cost per lead and the return per rupee (company spend ÷ some branches'
//     leads) appear only to someone who sees every branch;
//   * the CSV drops exactly the same columns as the screen;
//   * nobody who could open the screen before is refused it now.
t_section('DEF-01 — Advertising return: money only with the receivables right, branches only');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$made = ['l' => [], 'o' => [], 'al' => [], 'sp' => [], 'inv' => [], 'il' => [], 'j' => []];
$camp = 'DEF01-' . $f['tag'];
try {
    bbv_as($f['master']); leads_migrate(); opp_migrate(); ads_migrate();
    if (function_exists('books_migrate')) books_migrate();
    $pdo = db();

    // ---- One campaign, two leads: one Mumbai, one Ahmedabad -----------------------
    $lead = function ($office, $name) use (&$made, $pdo) {
        $r = lead_create(['company_name' => $name . ' ' . uniqid(), 'contact_phone' => '9825011111', 'source' => 'REFERRAL', 'value' => 100000]);
        $pdo->prepare("UPDATE leads SET office_id=? WHERE id=?")->execute([$office, $r['id']]);
        $made['l'][] = (int) $r['id']; return (int) $r['id'];
    };
    $lm = $lead($f['mum'], 'Mumbai ad lead'); $la = $lead($f['ahm'], 'Ahmedabad ad lead');
    foreach ([$lm, $la] as $lid) {
        $pdo->prepare("INSERT INTO ads_leads (ext_id, lead_id, campaign_id, campaign_name, platform, contact_name, imported_at) VALUES (?,?,?,?,?,?,?)")
            ->execute(['x' . $lid, $lid, $camp, 'Campaign ' . $camp, 'META', 'Test', date('c')]);
        $made['al'][] = (int) $pdo->lastInsertId();
    }
    $pdo->prepare("INSERT INTO ads_spend (campaign_id, campaign_name, platform, metric_date, spend, clicks) VALUES (?,?,?,?,?,?)")
        ->execute([$camp, 'Campaign ' . $camp, 'META', date('Y-m-d'), 10000, 50]);
    $made['sp'][] = (int) $pdo->lastInsertId();

    // Each lead became a WON deal in its own branch, with an order (call) and an invoice.
    $pipe = pipeline_default('OPPORTUNITY');
    $won  = ops_one("SELECT * FROM pipeline_stages WHERE pipeline_id=? AND kind='WON'", [$pipe['id']]);
    $wonDeal = function ($lid, $office, $value, $call, $invTotal) use (&$made, $pdo, $pipe, $won) {
        $o = opp_create(['name' => 'DEF01 deal ' . uniqid(), 'partner_name' => 'DEF01 Co', 'value' => $value, 'pipeline_id' => $pipe['id']]);
        $pdo->prepare("UPDATE opportunities SET lead_id=?, office_id=?, status='WON', stage_id=?, call_id=?, won_at=? WHERE id=?")
            ->execute([$lid, $office, $won['id'], $call, date('c'), $o['id']]);
        $made['o'][] = (int) $o['id'];
        $pdo->prepare("INSERT INTO jobs (call_id) VALUES (?)")->execute([$call]);
        $jid = (int) $pdo->lastInsertId(); $made['j'][] = $jid;
        $pdo->prepare("INSERT INTO invoices (invoice_no, office_id, invoice_date, total, status) VALUES (?,?,?,?, 'ISSUED')")
            ->execute(['DEF01-' . $call, $office, date('Y-m-d'), $invTotal]);
        $iid = (int) $pdo->lastInsertId(); $made['inv'][] = $iid;
        $pdo->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount) VALUES (?,?,?)")->execute([$iid, $jid, $invTotal]);
        $made['il'][] = (int) $pdo->lastInsertId();
    };
    $callBase = 900000000 + random_int(1, 999999);
    $wonDeal($lm, $f['mum'], 50000, $callBase + 1, 59000);
    $wonDeal($la, $f['ahm'], 70000, $callBase + 2, 82600);

    $mine = function ($withMoney = null) use ($camp) {
        foreach (roi_by_campaign('', '', $withMoney) as $b) if ($b['campaign_id'] === $camp) return $b;
        return null;
    };

    // ---- Who is who (the real roles' own rights, measured, not assumed) ---------
    $mkUser = function ($key, $role, $office, $scope = '') use ($pdo, $f) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices) VALUES (?,?,?,?,1,?,?)")
            ->execute([$f['tag'] . '_' . $key, ucfirst($key), 'T', $role, $office, $scope]);
        return (int) $pdo->lastInsertId();
    };
    $me  = $mkUser('mexec', 'MARKETING_EXECUTIVE', $f['mum']);    // Leads view, no receivables right, Mumbai only
    $bdm = $mkUser('bdm', 'BUSINESS_DEV_MANAGER', $f['mum']);      // Leads view + receivables right, Mumbai only
    $bd  = $f['bd'];                                                // Business Director: every branch, receivables right

    bbv_as($me);
    t_ok(roi_can() && !roi_money_can() && !roi_whole_company(), 'Marketing Executive (Mumbai) · opens the screen (unchanged), no receivables right, one branch');
    $b = $mine();
    t_ok($b !== null, 'Marketing Executive · sees the campaign');
    t_eq([(int) $b['leads'], (int) $b['won']], [1, 1], 'Marketing Executive · 1 lead, 1 won — Mumbai only (Ahmedabad\'s is not counted)');
    t_eq([(float) $b['invoiced'], (float) $b['collected'], (float) $b['uncollected']], [0.0, 0.0, 0.0], 'Marketing Executive · invoiced / collected / not received are not read at all');
    t_eq((float) $b['spend'], 10000.0, 'Marketing Executive · spend still shown (₹10,000)');
    t_ok(!isset($b['breaks']['order-no-invoice']) && !isset($b['breaks']['invoiced-unpaid']), 'Marketing Executive · no billing / payment "break" (that is receivables)');

    bbv_as($bdm);
    t_ok(roi_can() && roi_money_can() && !roi_whole_company(), 'BDM (Mumbai) · opens the screen, holds the receivables right, one branch');
    $b = $mine();
    t_eq([(int) $b['leads'], (float) $b['invoiced']], [1, 59000.0], 'BDM (Mumbai) · only the Mumbai lead and its ₹59,000 invoice — not Ahmedabad\'s ₹82,600');

    bbv_as($bd);
    t_ok(roi_can() && roi_money_can() && roi_whole_company(), 'Business Director · every branch and the receivables right');
    $m = $mine();
    t_eq([(int) $m['leads'], (int) $m['won']], [2, 2], 'Business Director · both leads and both won deals');
    t_eq((float) $m['invoiced'], 141600.0, 'Business Director · invoiced ₹1,41,600 (₹59,000 + ₹82,600) — unchanged');
    t_eq((float) $m['uncollected'], 141600.0, 'Business Director · nothing received yet, ₹1,41,600 outstanding — unchanged');
    t_ok($m['cpl'] !== null && abs($m['cpl'] - 5000) < 0.01, 'Business Director · cost per lead ₹5,000 (₹10,000 ÷ 2) — unchanged');

    // ---- The screen and the CSV, through the real route ------------------------------
    $page = bbv_route('ads-roi', 'GET', $me, 0, [], []);
    $out  = (string) ($page['out'] ?? '');
    t_ok($out !== '' && strpos($out, 'Campaign ' . $camp) !== false, 'screen · Marketing Executive opens /ads-roi and sees the campaign' . ($out === '' ? ' — ' . json_encode(array_intersect_key($page, ['code' => 1, 'flash' => 1, 'view_err' => 1, 'raw' => 1])) : ''));
    t_ok(strpos($out, 'Invoiced from those leads') === false && strpos($out, 'Actually collected') === false && strpos($out, '>Invoiced<') === false, 'screen · Marketing Executive sees no invoiced / collected card or column');
    t_ok(strpos($out, 'shown only to people who may see receivables') !== false, 'screen · Marketing Executive is told why the money is not there');
    t_ok(strpos($out, '59,000') === false && strpos($out, '82,600') === false && strpos($out, '1,41,600') === false, 'screen · no invoice amount anywhere on the Marketing Executive\'s page');

    $page = bbv_route('ads-roi', 'GET', $bdm, 0, [], []);
    $out  = (string) ($page['out'] ?? '');
    t_ok(strpos($out, 'Invoiced from those leads') !== false && strpos($out, '59,000') !== false && strpos($out, '82,600') === false, 'screen · BDM (Mumbai) sees invoiced money for Mumbai only');
    t_ok(strpos($out, "your branches' only") !== false && strpos($out, 'Per ₹ spent') === false && strpos($out, 'Cost per lead ₹') === false, 'screen · BDM (Mumbai): no cost per lead or return per rupee, with the reason');

    $page = bbv_route('ads-roi', 'GET', $bd, 0, [], []);
    $mo   = (string) ($page['out'] ?? '');
    t_ok(strpos($mo, 'Invoiced from those leads') !== false && strpos($mo, 'Actually collected') !== false && strpos($mo, 'Per ₹ spent') !== false, 'screen · Business Director sees the same four money cards and the return column as before');
    t_ok(strpos($mo, 'shown only to people who may see receivables') === false && strpos($mo, "your branches' only") === false, 'screen · Business Director sees no restriction note');

    // CSV columns, as each person gets them.
    $csvHead = function ($uid) {
        $r = bbv_route('ads-roi', 'GET', $uid, 0, [], ['export' => 'csv']);
        $first = strtok((string) ($r['out'] ?? ''), "\n");
        return trim(str_replace(["\xEF\xBB\xBF", '"'], '', (string) $first));
    };
    $h = $csvHead($me);
    t_ok($h !== '' && stripos($h, 'Invoiced') === false && stripos($h, 'Collected') === false && stripos($h, 'Return') === false && stripos($h, 'Cost per lead') === false, 'CSV · Marketing Executive: no money or ratio columns (' . $h . ')');
    $h = $csvHead($bd);
    t_ok(stripos($h, 'Invoiced') !== false && stripos($h, 'Return on cash') !== false && stripos($h, 'Cost per lead') !== false, 'CSV · Business Director: the same 12 columns as before (' . $h . ')');
} finally {
    $pdo = db();
    $del = function ($table, $ids) use ($pdo) { if ($ids) $pdo->exec("DELETE FROM $table WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")"); };
    $del('invoice_lines', $made['il']); $del('invoices', $made['inv']); $del('jobs', $made['j']);
    $del('opportunities', $made['o']); $del('ads_leads', $made['al']); $del('ads_spend', $made['sp']); $del('leads', $made['l']);
    bbv_cleanup($f);
}
