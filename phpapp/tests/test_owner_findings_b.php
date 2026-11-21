<?php
// Owner's opstest findings, 21 Nov 2026 — bugs B1–B8 (QA audit "owner findings").
//   B1 Sales home "New quote" tile opened "Not found" (it pointed at /quote-edit).
//      The robot passed it because it only read the link; this test OPENS every tile.
//   B2 lead page ⋯ menu and stage menu stayed open over each other (shared script).
//   B3 /lead-convert on a lead that is already a customer showed the whole form again.
//   B4 "+ Add new" customer offered to people the add form then refuses.
//   B5 inquiry form: both contact boxes said "— email or mobile".
//   B6 Sales home routine numbers at different heights (CSS).
//   B7 leads board drew only stages holding a lead; deals board draws every stage.
//   B8 lead page Quick actions stretched to the card beside them (CSS).
t_section('Owner findings B1–B8 — Sales fixes after the opstest round');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$made = ['l' => [], 'bp' => [], 'inq' => [], 'u' => []];

// The page's own HTML (not the text the shared helper returns), for link checks.
$raw = function ($route, $uid, array $get = []) {
    $root = dirname(__DIR__);
    $env = (getenv('DB_DRIVER') === 'mysql')
        ? 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'))
        : 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'));
    $cmd = 'BBV_RAW=1 ' . $env . ' php ' . escapeshellarg($root . '/tests/_bbv_worker.php') . ' ' . escapeshellarg($route) . ' GET ' . (int) $uid
         . ' 0 ' . escapeshellarg('{}') . ' ' . escapeshellarg(json_encode((object) $get)) . ' 2>/dev/null';
    foreach (array_reverse(explode("\n", trim((string) shell_exec($cmd)))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['html' => '', 'code' => 0];
};

try {
    bbv_as($f['master']); leads_migrate(); opp_migrate();
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id) VALUES (?,?,?,?,1,?)")
        ->execute([$f['tag'] . '_bdm', 'Bdm', 'T', 'BUSINESS_DEV_MANAGER', $f['mum']]);
    $bdm = (int) $pdo->lastInsertId(); $made['u'][] = $bdm;

    // ---- B1: every Sales home quick tile opens a real page --------------------------
    foreach (['Business Director' => $f['bd'], 'BDM' => $bdm] as $who => $uid) {
        $h = (string) ($raw('sales-home', $uid)['html'] ?? '');
        t_ok($h !== '' && preg_match('#<div class="sxw-qt"[^>]*>(.*?)</div>#s', $h, $m), "B1 · $who · Sales home has its quick tiles");
        preg_match_all('#href="([^"]+)"#', $m[1] ?? '', $hrefs);
        t_ok(count($hrefs[1]) >= 5, "B1 · $who · " . count($hrefs[1]) . ' tiles found');
        t_ok(!in_array('/quote-edit', $hrefs[1], true), "B1 · $who · no tile points at /quote-edit");
        foreach ($hrefs[1] as $href) {
            $u = parse_url($href); parse_str((string) ($u['query'] ?? ''), $q);
            $r = bbv_route(ltrim((string) $u['path'], '/'), 'GET', $uid, 0, [], $q);
            $bad = ($r['code'] ?? 0) === 404 || ($r['view'] ?? '') === 'notfound' || !empty($r['view_err'])
                || stripos((string) ($r['out'] ?? ''), 'does not exist') !== false;
            t_ok(!$bad && ($r['rendered'] ?? false) === true, "B1 · $who · tile $href opens its page (" . ($r['view'] ?? '?') . ')' . ($bad ? ' — ' . json_encode(array_intersect_key($r, ['code' => 1, 'view' => 1, 'view_err' => 1, 'flash' => 1])) : ''));
        }
    }
    $r = bbv_route('quote-new', 'GET', $bdm, 0, [], []);
    t_eq([$r['view'] ?? '', (bool) ($r['view_ok'] ?? false)], ['ops/crm/quote_form', true], 'B1 · BDM · the New quote tile lands on the quotation form');
    // The Business Director approves quotations but may not write one: the tile is not
    // offered to them (it used to lead to "You cannot create or edit quotations").
    bbv_as($f['bd']); $bdQ = crm_quote_can_create(); bbv_as($bdm); $bdmQ = crm_quote_can_create();
    $hd = (string) ($raw('sales-home', $f['bd'])['html'] ?? '');
    t_ok(!$bdQ && $bdmQ && strpos($hd, 'href="/quote-new"') === false, 'B1 · Business Director (cannot write quotations) is not offered the New quote tile; the BDM is');

    // ---- B3: an already-converted lead says so ----------------------------------------
    $G = function ($state, $pan) { $first = $state . $pan . '1Z'; return $first . commercial_gstin_check_char($first); };
    $lr = lead_create(['company_name' => 'Sunrise Valves ' . $f['tag'], 'contact_phone' => '9825012345', 'source' => 'REFERRAL']);
    $made['l'][] = (int) $lr['id'];
    $c = lead_convert((int) $lr['id'], ['gstin' => $G('24', 'AAFCS' . random_int(1000, 9999) . 'M')]);
    $row = ops_one("SELECT status, converted_partner_id, converted_inquiry_id FROM leads WHERE id=?", [(int) $lr['id']]);
    if (!empty($row['converted_partner_id'])) $made['bp'][] = (int) $row['converted_partner_id'];
    if (!empty($row['converted_inquiry_id'])) $made['inq'][] = (int) $row['converted_inquiry_id'];
    t_ok(empty($c['err']) && $row['status'] === 'CONVERTED', 'B3 · lead converted for the test' . (!empty($c['err']) ? ' — ' . $c['err'] : ''));
    $p = bbv_route('lead-convert', 'GET', $f['master'], (int) $lr['id'], [], ['id' => (int) $lr['id']]);
    $out = (string) ($p['out'] ?? '');
    t_ok(strpos($out, 'is already a customer') !== false && strpos($out, 'Already a customer') !== false, 'B3 · the page says it is already a customer');
    t_ok(strpos($out, 'make them a customer') === false && strpos($out, 'create a new one') === false, 'B3 · no conversion form on the page');
    t_ok(strpos($out, 'Open the') !== false, 'B3 · it links to the customer / inquiry');
    $again = lead_convert((int) $lr['id'], ['gstin' => $G('24', 'AAFCZ1111M')]);
    t_ok(!empty($again['err']) && strpos($again['err'], 'already') !== false, 'B3 · a second conversion is still refused');

    // ---- B4: "+ Add new" customer only for people who may add one -----------------------
    bbv_as($bdm); $bdmCan = partner_can_add();
    bbv_as($f['admin']); $admCan = partner_can_add();
    t_ok(!$bdmCan && $admCan, 'B4 · the add rule: BDM may not add a customer, Admin may (same rule as /partner-new)');
    foreach (['opportunity-new' => [], 'inquiry-new' => [], 'quote-new' => []] as $route => $q) {
        $hb = (string) ($raw($route, $bdm, $q)['html'] ?? '');
        $ha = (string) ($raw($route, $f['admin'], $q)['html'] ?? '');
        t_ok($hb !== '' && strpos($hb, 'data-qa="client"') === false, "B4 · $route · BDM is not offered “+ Add new” customer");
        t_ok($ha !== '' && strpos($ha, 'data-qa="client"') !== false, "B4 · $route · Admin still has “+ Add new”");
    }
    // /partner-new lives in index.php (not reachable by the route worker): the link and
    // the form must read the same rule.
    $idx = (string) file_get_contents(dirname(__DIR__) . '/index.php');
    $blk = substr($idx, (int) strpos($idx, "if (\$route === 'partner-new')"), 1200);
    t_ok(strpos($blk, 'ops_require(partner_can_add(),') !== false, 'B4 · the add form refuses by the same rule the link uses (partner_can_add)');

    // ---- B5: one label per contact box -------------------------------------------------
    $h = (string) ($raw('inquiry-new', $f['admin'])['html'] ?? '');
    t_ok(strpos($h, 'Contact e-mail</label>') !== false && strpos($h, 'Contact mobile</label>') !== false, 'B5 · labels "Contact e-mail" and "Contact mobile"');
    t_ok(strpos($h, '— email or mobile') === false, 'B5 · no "— email or mobile" hint left');
    t_ok((bool) preg_match('#type="tel"[^>]*name="contact_mobile"#', $h), 'B5 · the mobile box is a telephone field (phone keypad)');

    // ---- B7: every open lead stage has its column --------------------------------------
    $lp = pipeline_default('LEAD');
    $open = array_values(array_filter(pipeline_stages((int) $lp['id']) ?: [], fn($s) => ($s['kind'] ?? 'OPEN') === 'OPEN'));
    $hb = (string) ($raw('sales-board', $f['bd'])['html'] ?? '');
    $leadsPart = explode('id="sxb-lcols"', $hb, 2)[1] ?? '';
    $leadsPart = explode('<p class="rec-note">Open leads', $leadsPart, 2)[0];
    t_ok($leadsPart !== '' && count($open) >= 1, 'B7 · the leads board is drawn (' . count($open) . ' open lead stages in the pipeline)');
    t_eq(substr_count($leadsPart, '<section class="sxw-col"'), count($open), 'B7 · one column per open lead stage, empty ones included');
    foreach ($open as $s)
        t_ok(strpos($leadsPart, '<b>' . e($s['name']) . '</b>') !== false, 'B7 · leads board has a column for "' . $s['name'] . '"');

    // ---- B2 / B6 / B8: the shared script and the two style rules -----------------------
    $js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/app.js');
    t_ok(strpos($js, "details.rec-menu") !== false && strpos($js, "'Escape'") !== false && strpos($js, "addEventListener('toggle'") !== false,
        'B2 · one shared script: opening a record menu closes the other; click outside / Esc closes');
    $css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/app.css');
    t_ok(strpos($css, '.rec-grid>.rec-card>.rec-qa{flex:1;grid-auto-rows:1fr}') === false && substr_count($css, '.rec-grid>.rec-card>.rec-qa{align-content:start}') === 2,
        'B8 · Quick actions are no longer stretched to the card beside them');
    t_ok((bool) preg_match('#\.sxw-rt a\{[^}]*justify-content:flex-start#', $css), 'B6 · routine boxes put their numbers at the top');
} finally {
    bbv_as($f['master']);
    $del = function ($t, $ids) use ($pdo) { $ids = array_filter(array_map('intval', $ids)); if ($ids) $pdo->exec("DELETE FROM $t WHERE id IN (" . implode(',', $ids) . ")"); };
    $del('crm_inquiries', $made['inq']); $del('leads', $made['l']);
    if ($made['bp']) { $in = implode(',', array_map('intval', $made['bp'])); try { $pdo->exec("DELETE FROM partner_addresses WHERE partner_id IN ($in)"); $pdo->exec("DELETE FROM partner_contacts WHERE partner_id IN ($in)"); } catch (Throwable $e) {} }
    $del('business_partners', $made['bp']); $del('users', $made['u']);
    bbv_cleanup($f);
}
