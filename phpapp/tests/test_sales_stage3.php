<?php
// Owner, 21 Nov 2026 — Sales redesign Stage 3 (Enquiries and Deals: the inquiries
// register, the inquiry's new read-only page, the inquiry form, the deals register,
// the deal page and the new-deal form), approved design pack, "All as recommended".
// Preserve every field, button, filter and action; figures and permissions unchanged;
// plus the approved backend items:
//   3 — the Inquiries register on the shared register: paging, sorting (question 6),
//       a column chooser and a CSV (the register's own view right, its own scope);
//   4 — the inquiry's read-only page, opened with the view right (DEF-14);
//   5 — after saving an inquiry, back to the inquiry page;
//   6 — the deal form's checks: a value of 0 or more, real dates;
//   7 — a deal's business unit can be set; a typed win % is not taken (DEF-15);
//   DEF-16 — the deal form's wording (a name-only deal CAN be quoted; no "drag").
//
// Each screen is drawn twice through the production dispatcher, as the same person
// on the same data: with the OLD view (tests/fixtures/pre_stage3/) and the NEW one.
// Every figure must read the same; every link, form field and amount of the old page
// must be on the new one (or, where the approved design MOVED it, on the page it moved
// to — each such move is listed and checked); who is refused must not change.
require_once __DIR__ . '/_bbv_fixture.php';

function s3_env() {
    $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
    return $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
}
function s3_draw($uid, $route, array $get = [], $old = false) {
    $root = dirname(__DIR__);
    $env = s3_env() . ($old ? ' STAGE3_OLD=1' : '');
    $raw = (string) shell_exec($env . ' php ' . escapeshellarg($root . '/tests/_stage3_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route) . ' ' . escapeshellarg(json_encode((object) $get)) . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['done' => 'no-json: ' . substr($raw, 0, 300), 'figs' => [], 'hrefs' => [], 'forms' => [], 'money' => [], 'nav' => [], 'bn' => [], 'refused' => false, 'len' => 0, 'text' => '', 'buttons' => [], 'flash' => []];
}
function s3_drive($uid, $route, $method = 'GET', array $get = [], array $post = [], array $needles = []) {
    $root = dirname(__DIR__);
    $cmd = s3_env() . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles)) . ' ' . escapeshellarg(json_encode((object) []));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => [], 'retry' => null];
}
function s3_said($r) { return implode(' | ', array_map(fn($f) => (string) ($f['text'] ?? ''), $r['flash'] ?? [])); }
function s3_form_split($sig) { return preg_match('/^(.*?) \[(.*)\]$/', $sig, $m) ? [$m[1], $m[2] === '' ? [] : explode(',', $m[2])] : [$sig, []]; }
function s3_as_can($uid, $fn) { $keep = $_SESSION['uid'] ?? null; bbv_as($uid); $r = $fn(); if ($keep) bbv_as($keep); return $r; }

t_section('Sales Stage 3 — the inquiry and deal screens show the same figures, links, fields and access as before, in the new look');
$f = bbv_fixture();
$pdo = db();
$made = ['inq' => [], 'opps' => [], 'quotes' => [], 'partners' => [], 'users' => []];
try {
    bbv_as($f['master']);
    leads_migrate(); if (function_exists('opp_migrate')) opp_migrate(); if (function_exists('act_migrate')) act_migrate();
    if (function_exists('crm_migrate')) crm_migrate();

    // ---- People: the fixture's, plus the four Sales roles (docs/02 19b) ----------------------------
    $mk = function ($key, $role, $office, $scope = '', $perms = '') use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, permissions) VALUES (?,?,?,?,1,?,?,?)")
            ->execute([$f['tag'] . '_' . $key, ucfirst($key), 'T', $role, $office, $scope, $perms]);
        return $made['users'][] = (int) $pdo->lastInsertId();
    };
    $bdm  = $mk('bdm', 'BUSINESS_DEV_MANAGER', $f['mum']);
    $kam  = $mk('kam', 'KEY_ACCOUNTS_MANAGER', $f['mum']);
    $mm   = $mk('mm', 'MARKETING_MANAGER', $f['mum'], 'ALL');
    $mex  = $mk('mex', 'MARKETING_EXECUTIVE', $f['mum']);
    $noxp = $mk('noexport', 'BUSINESS_DEV_MANAGER', $f['mum'], '', PERM_DIFF_TAG . ',-crm.deal.export');          // own exception: no deal download
    $view = $mk('inqview', 'BUSINESS_DEV_MANAGER', $f['mum'], '', PERM_DIFF_TAG . ',-mod.inquiries.edit,-mod.inquiries.add');   // inquiries: view only

    // ---- Records -----------------------------------------------------------------------------------
    $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status, home_branch_id) VALUES (?,?,?,1,'ACTIVE',?)")
        ->execute(['S3-' . $f['tag'], 'Stage Three Pumps ' . $f['tag'], 'Stage Three Pumps ' . $f['tag'], $f['mum']]);
    $made['partners'][] = $cid = (int) $pdo->lastInsertId();
    $sbus = lk_options_or('sbu', OPS_SBUS); $sk = (string) array_key_first($sbus);
    $inq = function ($subject, $office, $status = 'OPEN', array $over = []) use ($pdo, $f, $cid, $sk, &$made) {
        $no = 'S3' . substr($f['tag'], -4) . '-' . (count($made['inq']) + 1);
        $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, client_id, client_name, contact_name, contact_email, contact_mobile, subject, service_requirement, sbu, source, received_date, assigned_to, status, notes, created_by, created_at, office_id, drop_reason)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$no, $over['client_id'] ?? $cid, $over['client_name'] ?? '', 'Meena', 'meena@example.com', '9898012345', $subject . ' ' . $f['tag'], 'Two welding inspectors', $over['sbu'] ?? $sk, 'EMAIL',
                       $over['received'] ?? date('Y-m-d', strtotime('-2 days')), $over['assigned'] ?? null, $status, 'prefers 1 Jan', 'test', date('c'), $office, $over['drop'] ?? '']);
        return $made['inq'][] = (int) $pdo->lastInsertId();
    };
    $iA = $inq('S3 Open Mumbai', $f['mum'], 'OPEN', ['received' => date('Y-m-d', strtotime('-20 days'))]);      // past the response level
    $iQ = $inq('S3 Quoted Mumbai', $f['mum'], 'QUOTED', ['assigned' => $bdm]);
    $iD = $inq('S3 Dropped Mumbai', $f['mum'], 'DROPPED', ['drop' => (string) array_key_first(INQUIRY_DROP_REASONS)]);
    $iH = $inq('S3 Ahmedabad', $f['ahm'], 'OPEN');
    $iK = $inq('S3 Assigned KAM', $f['mum'], 'OPEN', ['assigned' => $kam]);
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, client_id, inquiry_id, status, is_current, subtotal, total_amount, office_id, sbu, created_at) VALUES (?,0,?,?, 'SENT', 1, 400000, 472000, ?, ?, ?)")
        ->execute(['Q-S3-' . $f['tag'], $cid, $iQ, $f['mum'], $sk, date('c')]);
    $made['quotes'][] = $qid = (int) $pdo->lastInsertId();

    $pipe = pipeline_default('OPPORTUNITY'); $stgs = pipeline_stages((int) $pipe['id']);
    $openSt = array_values(array_filter($stgs, fn($s) => $s['kind'] === 'OPEN'));
    $wonSt = array_values(array_filter($stgs, fn($s) => $s['kind'] === 'WON'))[0] ?? null;
    $lostSt = array_values(array_filter($stgs, fn($s) => $s['kind'] === 'LOST'))[0] ?? null;
    $deal = function ($name, $office, array $over = []) use ($pipe, $cid, &$made) {
        $r = opp_create($over + ['name' => $name, 'partner_id' => $cid, 'value' => 1840000, 'pipeline_id' => $pipe['id'], 'office_id' => $office,
                                 'expected_close' => date('Y-m-d', strtotime('+20 days')), 'next_action' => 'Chase', 'next_action_on' => date('Y-m-d', strtotime('-1 day'))]);
        t_ok(!empty($r['id']), 'ARMING · deal "' . $name . '" made' . (!empty($r['err']) ? ' — ' . $r['err'] : ''));
        return $made['opps'][] = (int) ($r['id'] ?? 0);
    };
    $oA = $deal('S3 Deal Mumbai ' . $f['tag'], $f['mum'], ['competitor' => 'ABC Inspection', 'requirement' => 'Calibration of 3 tanks']);
    //  D2 (owner, 21 Nov 2026): a deal is worked by its owner — the BDM who works it in these checks.
    $pdo->prepare("UPDATE opportunities SET owner_user_id=? WHERE id=?")->execute([$bdm, $oA]);
    opp_move($oA, (int) $openSt[min(2, count($openSt) - 1)]['id'], [], true);
    $pdo->prepare("UPDATE opportunities SET stage_since=? WHERE id=?")->execute([date('c', strtotime('-40 days')), $oA]);   // stalled
    opp_link_quote($oA, $qid);
    $oH = $deal('S3 Deal Ahmedabad ' . $f['tag'], $f['ahm']);
    $oW = $deal('S3 Deal Won ' . $f['tag'], $f['mum']);
    if ($wonSt) opp_move($oW, (int) $wonSt['id'], [], true);
    $oL = $deal('S3 Deal Lost ' . $f['tag'], $f['mum']);
    if ($lostSt) opp_move($oL, (int) $lostSt['id'], ['lost_reason' => (string) array_key_first(opp_lost_reasons()), 'lost_to' => 'XYZ', 'lost_note' => 'price'], true);
    $oN = $deal('S3 Deal Name Only ' . $f['tag'], $f['mum'], ['partner_id' => 0, 'partner_name' => 'Nobody Yet Co']);

    $people = ['Business Director' => $f['bd'], 'Master admin' => $f['master'], 'Admin' => $f['admin'], 'Business Unit Head' => $f['sbu'],
               'Branch Manager (Mumbai)' => $f['kiran'], 'Finance, Mumbai' => $f['meera'], 'Coordinator' => $f['neha'],
               'BDM (Mumbai)' => $bdm, 'KAM (Mumbai)' => $kam, 'Marketing Manager (all)' => $mm, 'Marketing Executive (Mumbai)' => $mex,
               'BDM without the deal download' => $noxp, 'BDM, inquiries view only' => $view];
    $pages = [['inquiries', []], ['inquiries', ['st' => 'OPEN']], ['inquiries', ['fy' => 'all', 'q' => 'S3']],
              ['inquiry-new', []], ['inquiry-edit', ['id' => $iA]], ['inquiry-edit', ['id' => $iD]], ['inquiry-edit', ['id' => $iQ]],
              ['opportunities', []], ['opportunities', ['v' => 'list']], ['opportunities', ['v' => 'list', 'status' => 'OPEN']],
              ['opportunity-new', []], ['opportunity', ['id' => $oA]], ['opportunity', ['id' => $oW]], ['opportunity', ['id' => $oL]], ['opportunity', ['id' => $oN]]];

    //  THE MOVES THE APPROVED DESIGN MADE (each one is checked, not excused):
    //   • inquiries register: "Edit" moved into the inquiry's own page (checked below on /inquiry);
    //     the one GET filter form became three (status, year, search) — every old field is still there;
    //   • inquiry form: "Where it stands", Drop / Reopen and the history moved to /inquiry?id= — so the
    //     old edit page is compared with the new edit page AND the new inquiry page together;
    //   • deals board: the year picker, which the board never applied, is on the list only;
    //   • deal page: the customer opens its customer page (/client?id=), no longer the finance ledger.
    $moved = function ($route, $get, $href) {
        if ($route === 'inquiries' && preg_match('#^/inquiry-edit\?id=\d+$#', $href)) return true;
        if ($route === 'opportunity' && preg_match('#^/ledger\?id=\d+$#', $href)) return true;
        return false;
    };
    $compare = function ($label, $uid, $route, $get) use ($moved) {
        $o = s3_draw($uid, $route, $get, true); $n = s3_draw($uid, $route, $get, false);
        $pg = '/' . $route . ($get ? '?' . http_build_query($get) : '');
        t_eq($n['done'], $o['done'], "$label · $pg — the page opens (or is refused) exactly as before");
        t_eq($n['refused'], $o['refused'], "$label · $pg — refused as before");
        //  The inquiry form's "Where it stands", Drop/Reopen and history now live on the inquiry page.
        $nn = $n;
        if ($route === 'inquiry-edit' && !$o['refused']) {
            $p = s3_draw($uid, 'inquiry', $get, false);
            $nn['hrefs'] = array_merge($n['hrefs'], $p['hrefs']); $nn['forms'] = array_merge($n['forms'], $p['forms']); $nn['money'] = array_merge($n['money'], $p['money']);
        }
        $fd = []; foreach ($o['figs'] as $k => $v) if (($n['figs'][$k] ?? null) !== $v) $fd[] = "$k: old \"$v\" new \"" . ($n['figs'][$k] ?? '—') . '"';
        t_eq($fd, [], "$label · $pg — all " . count($o['figs']) . ' figures read the same');
        $lostL = array_values(array_filter(array_diff(array_unique($o['hrefs']), $nn['hrefs']), fn($h) => !$moved($route, $GLOBALS['__s3g'] ?? [], $h)));
        //  Owner findings B4 (21 Nov 2026): "+ Add new" customer (href "#") is not offered to
        //  someone the add form refuses (partner_can_add — the form's own rule).
        $keep = $_SESSION['uid'] ?? null; bbv_as($uid); $mayAdd = partner_can_add(); if ($keep) bbv_as($keep);
        if (!$mayAdd && in_array($route, ['inquiry-new', 'inquiry-edit', 'opportunity-new', 'opportunity-edit', 'quote-new', 'quote-edit', 'lead-convert'], true))
            $lostL = array_values(array_diff($lostL, ['#']));
        t_eq($lostL, [], "$label · $pg — every link of the old page is on the new one (moves listed above)");
        //  A form keeps its address and every field it had (a new page may add one). A GET filter
        //  form may be split into several on the same page: every old field must be on one of them.
        $lost = [];
        foreach (array_unique($o['forms']) as $sig) {
            [$at, $fl] = s3_form_split($sig); $ok = false;
            foreach ($nn['forms'] as $ns) { [$nat, $nfl] = s3_form_split($ns); if ($nat === $at && !array_diff($fl, $nfl)) { $ok = true; break; } }
            if (!$ok && strpos($at, 'get ') === 0) {
                $all = []; foreach ($nn['forms'] as $ns) { [$nat, $nfl] = s3_form_split($ns); if (strpos($nat, 'get ') === 0) $all = array_merge($all, $nfl); }
                $ok = !array_diff($fl, $all) || ($route === 'opportunities' && ($GLOBALS['__s3view'] ?? '') === 'board' && !array_diff($fl, ['fy', 'v', 'q']));
            }
            if (!$ok) $lost[] = $sig;
        }
        t_eq($lost, [], "$label · $pg — every form (same address, every field) is on the new one");
        t_eq(array_values(array_diff($o['money'], $nn['money'])), [], "$label · $pg — every amount of the old page is on the new one");
        return [$o, $n];
    };
    $draws = [];
    foreach ($people as $label => $uid) foreach ($pages as [$route, $get]) {
        $GLOBALS['__s3view'] = $route === 'opportunities' ? ($get['v'] ?? 'board') : '';
        $draws[$label][$route . json_encode($get)] = $compare($label, $uid, $route, $get);
    }

    t_section('Sales Stage 3 — the inquiries register on the shared register (backend item 3, question 6)');
    $reg = $draws['BDM (Mumbai)']['inquiries[]'][1];
    t_ok(strpos($reg['text'], 'S3 Open Mumbai') !== false && strpos($reg['text'], 'S3 Ahmedabad') === false, 'branch scope: a Mumbai BDM sees the Mumbai inquiries and not the Ahmedabad one (as before)');
    t_ok(strpos($draws['Marketing Manager (all)']['inquiries' . json_encode(['fy' => 'all', 'q' => 'S3'])][1]['text'], 'S3 Ahmedabad') !== false, 'an all-branch Marketing Manager sees the Ahmedabad inquiry too');
    t_ok((bool) array_filter($reg['forms'], fn($s) => strpos($s, 'post /dt-columns [') === 0 && strpos($s, 'cols') !== false), 'a column chooser (the shared register\'s own)');
    t_ok((bool) array_filter($reg['hrefs'], fn($h) => strpos($h, 'export=csv') !== false), 'a CSV');
    t_ok((bool) array_filter($reg['hrefs'], fn($h) => strpos($h, 'sort=') !== false), 'sortable column headings (question 6)');
    t_ok((bool) array_filter($reg['hrefs'], fn($h) => $h === '/inquiry?id=' . $iA), 'each row opens the inquiry\'s own page');
    t_ok(in_array('/inquiries', $reg['nav'], true) && in_array('/leads', $reg['bn'], true), 'the register carries the Sales bar and the phone bottom bar');
    //  Paging: 30 more Mumbai inquiries, 50 a page by default; 25 a page shows page 2.
    for ($i = 0; $i < 30; $i++) $inq('S3 Bulk ' . $i, $f['mum'], 'OPEN', ['received' => date('Y-m-d')]);
    $p1 = s3_draw($bdm, 'inquiries', ['per' => '25', 'fy' => 'all', 'q' => 'S3'], false);
    t_ok(strpos($p1['text'], 'Page 1 of 2') !== false, 'paging: 25 a page gives "Page 1 of 2" for the ' . (count($made['inq']) - 1) . ' Mumbai inquiries');
    $p2 = s3_draw($bdm, 'inquiries', ['per' => '25', 'page' => '2', 'fy' => 'all', 'q' => 'S3'], false);
    t_ok(strpos($p2['text'], 'Page 2 of 2') !== false && strpos($p2['text'], 'S3 Open Mumbai') !== false, '…and page 2 holds the oldest ones (newest first, as before)');
    $asc = s3_draw($bdm, 'inquiries', ['per' => '25', 'fy' => 'all', 'q' => 'S3', 'sort' => 'no', 'dir' => 'asc'], false);
    t_ok(strpos($asc['text'], 'S3 Open Mumbai') !== false && strpos($asc['text'], 'S3 Bulk 29') === false, 'sorted oldest first, the first page starts with the oldest');
    //  The CSV: the register's own scope and filter — never another branch's row.
    $csv = s3_drive($bdm, 'inquiries', 'GET', ['export' => 'csv', 'fy' => 'all'], [], ['S3 Open Mumbai', 'S3 Ahmedabad', 'Received']);
    t_ok(!empty($csv['has']['S3 Open Mumbai']) && !empty($csv['has']['Received']), 'the CSV carries the Mumbai inquiries');
    t_ok(empty($csv['has']['S3 Ahmedabad']), '…and not the Ahmedabad one (the person\'s branches only)');
    $csvNo = s3_drive($f['neha'], 'inquiries', 'GET', ['export' => 'csv', 'fy' => 'all'], [], ['S3 Open Mumbai']);
    t_eq(!empty($csvNo['has']['S3 Open Mumbai']), s3_as_can($f['neha'], fn() => can('mod.inquiries.view')), 'someone without the Inquiries view right gets no CSV (the register\'s own gate)');

    t_section('Sales Stage 3 — DEF-14: the inquiry\'s own read-only page');
    $pv = s3_draw($view, 'inquiry', ['id' => $iA], false);
    t_ok(s3_as_can($view, fn() => can('mod.inquiries.view') && !can('mod.inquiries.edit')), 'ARMING · the person has the Inquiries view right and not edit');
    t_ok(!$pv['refused'] && strpos($pv['text'], 'S3 Open Mumbai') !== false, 'a view-only person opens the inquiry and reads it');
    t_ok(!array_filter($pv['hrefs'], fn($h) => strpos($h, '/inquiry-edit') === 0), '…with no Edit');
    t_ok(!array_filter($pv['forms'], fn($s) => strpos($s, '/inquiry-drop') !== false), '…and no Drop');
    $ve = s3_draw($view, 'inquiry-edit', ['id' => $iA], false);
    t_ok($ve['refused'] || $ve['done'] !== 'return', 'the edit form stays refused to them (its own right, unchanged)');
    $pe = s3_draw($bdm, 'inquiry', ['id' => $iA], false);
    t_ok((bool) array_filter($pe['hrefs'], fn($h) => $h === '/inquiry-edit?id=' . $iA), 'someone who may edit it finds Edit on the page (moved from the register row)');
    t_ok((bool) array_filter($pe['forms'], fn($s) => strpos($s, 'post /inquiry-drop [') === 0), '…and "Drop this inquiry" (moved from the edit form)');
    t_ok(strpos($pe['text'], 'History of this') !== false, '…and the history (moved from the edit form)');
    $pd = s3_draw($bdm, 'inquiry', ['id' => $iD], false);
    t_ok((bool) array_filter($pd['forms'], fn($s) => strpos($s, 'post /inquiry-reopen [') === 0), 'a dropped one offers "Reopen it"');
    $pq = s3_draw($bdm, 'inquiry', ['id' => $iQ], false);
    t_ok((bool) array_filter($pq['hrefs'], fn($h) => $h === '/quote?id=' . $qid) && strpos($pq['text'], 'Sent — waiting for their answer') !== false, 'a quoted one shows its quotation, its status in words');
    t_ok(in_array('₹4,72,000', $pq['money'], true), '…and its value');
    $out = s3_draw($bdm, 'inquiry', ['id' => $iH], false);
    t_ok($out['refused'] || $out['done'] !== 'return', 'a Mumbai person cannot open the Ahmedabad inquiry (the register\'s branch scope)');
    $noView = s3_draw($f['neha'], 'inquiry', ['id' => $iA], false);
    t_eq($noView['refused'] || $noView['done'] !== 'return', !s3_as_can($f['neha'], fn() => can('mod.inquiries.view')), 'someone without the Inquiries view right is refused (the module gate)');
    $kl = s3_draw($bdm, 'inquiry', ['id' => $iK], false);
    t_ok(!$kl['refused'] && !array_filter($kl['hrefs'], fn($h) => strpos($h, '/inquiry-edit') === 0) && strpos($kl['text'], 'you can view it') !== false,
         'one assigned to someone else: a BDM outside its line reads it, with the "assigned to" line and no Edit');
    t_ok(!array_filter($kl['hrefs'], fn($h) => strpos($h, '/quote-new?inquiry=' . $iK) === 0), '…and no Quote button (the "may work it" rule, as on the register)');
    t_eq(chain_stages()['INQUIRY'][2] ?? '', '/inquiry?id=', 'the chain strip opens an inquiry on its page');
    t_eq(ACT_ENTITIES['INQUIRY'][1] ?? '', '/inquiry?id=', 'the activity list opens an inquiry on its page');

    t_section('Sales Stage 3 — backend item 5: after saving, back to the inquiry');
    $r = s3_drive($bdm, 'inquiry-edit', 'POST', ['id' => $iA], ['_embed' => '1', 'client_id' => $cid, 'contact_name' => 'Meena', 'contact_email' => 'meena@example.com',
                                                                  'sbu' => $sk, 'subject' => 'S3 Open Mumbai ' . $f['tag'] . ' edited', 'source' => 'EMAIL', 'received_date' => date('Y-m-d', strtotime('-20 days'))],
                   ['\/inquiry?id=' . $iA . '"']);
    t_ok(!empty($r['has']['\/inquiry?id=' . $iA . '"']), 'saving an inquiry returns to /inquiry?id=' . $iA . ' (' . s3_said($r) . ')');
    t_eq((string) ops_val("SELECT subject FROM crm_inquiries WHERE id=?", [$iA]), 'S3 Open Mumbai ' . $f['tag'] . ' edited', '…and the change is saved');
    $subj = 'S3 Brand New ' . $f['tag'];
    $r = s3_drive($bdm, 'inquiry-new', 'POST', [], ['_embed' => '1', 'client_id' => $cid, 'contact_name' => 'Ravi', 'contact_mobile' => '9898011111', 'sbu' => $sk, 'subject' => $subj, 'source' => 'EMAIL', 'received_date' => date('Y-m-d')], ['\/inquiry?id=']);
    $nid = (int) ops_val("SELECT id FROM crm_inquiries WHERE subject=?", [$subj]); if ($nid) $made['inq'][] = $nid;
    t_ok($nid > 0 && !empty($r['has']['\/inquiry?id=']), 'a new inquiry opens its own page after Create (' . s3_said($r) . ')');
    $r = s3_drive($bdm, 'inquiry-drop', 'POST', [], ['_embed' => '1', 'id' => $iA, 'drop_reason' => (string) array_key_first(INQUIRY_DROP_REASONS)], ['\/inquiry?id=' . $iA . '"']);
    t_ok(!empty($r['has']['\/inquiry?id=' . $iA . '"']) && (string) ops_val("SELECT status FROM crm_inquiries WHERE id=?", [$iA]) === 'DROPPED', 'Drop returns to the inquiry page, and it is dropped');
    $r = s3_drive($bdm, 'inquiry-reopen', 'POST', [], ['_embed' => '1', 'id' => $iA], ['\/inquiry?id=' . $iA . '"']);
    t_ok((string) ops_val("SELECT status FROM crm_inquiries WHERE id=?", [$iA]) === 'OPEN', 'Reopen opens it again');

    t_section('Sales Stage 3 — the deals register');
    $db = $draws['BDM (Mumbai)']['opportunities[]'][1];
    t_ok(strpos($db['text'], 'S3 Deal Mumbai') !== false && strpos($db['text'], 'S3 Deal Ahmedabad') === false, 'branch scope: a Mumbai BDM\'s board shows the Mumbai deal and not the Ahmedabad one (as before)');
    t_ok(strpos($db['text'], 'Why deals were lost') !== false, '"Why deals were lost — last 12 months" stays under the board');
    $hasCsv = fn($d) => (bool) array_filter($d['hrefs'], fn($h) => strpos($h, 'export=csv') !== false);
    t_ok($hasCsv($db) && $hasCsv($draws['BDM (Mumbai)']['opportunities' . json_encode(['v' => 'list'])][1]), 'a BDM with the download right sees the CSV on the board and the list');
    t_ok(!$hasCsv($draws['BDM without the deal download']['opportunities[]'][1]) && !$hasCsv($draws['BDM without the deal download']['opportunities' . json_encode(['v' => 'list'])][1]),
         'a BDM whose own exception removes "Download deals" sees no CSV (Batch A switch crm.deal.export)');
    t_ok(isset($db['figs']['Won Lost, 12 months']), 'the won and lost counts (12 months), computed before but shown only in a footnote, are a tile');
    $lst = $draws['BDM (Mumbai)']['opportunities' . json_encode(['v' => 'list'])][1];
    t_ok((bool) array_filter($lst['forms'], fn($s) => strpos($s, 'get /opportunities [') === 0 && strpos($s, 'status') !== false), 'the status filter the server always honoured now has a control (list)');
    t_ok(in_array('/opportunities', $db['nav'], true), 'the register carries the Sales bar');

    t_section('Sales Stage 3 — the deal page');
    $dp = $draws['BDM (Mumbai)']['opportunity' . json_encode(['id' => $oA])][1];
    t_ok((bool) array_filter($dp['hrefs'], fn($h) => $h === '/client?id=' . $cid), 'the customer opens its customer page (it opened the finance ledger)');
    t_ok((bool) array_filter($dp['forms'], fn($s) => strpos($s, 'post /opportunity-move [') === 0 && strpos($s, 'gate_note') !== false), '"Move it" carries a note for the approver (the server always took one)');
    t_ok((bool) array_filter($dp['forms'], fn($s) => strpos($s, 'post /opportunity-edit [') === 0 && strpos($s, 'sbu') !== false), '"Edit the details" offers the business unit (DEF-15)');
    t_ok((bool) array_filter($dp['forms'], fn($s) => strpos($s, 'post /sales-log [') === 0), 'the ONE log form is on the page (inside the History tab now)');
    t_ok(strpos($dp['text'], 'Sent — waiting for their answer') !== false && strpos($dp['text'], '(SENT)') === false, 'a quotation\'s status reads in words, not its code');
    t_ok(in_array('/opportunities', $dp['nav'], true) && in_array('/leads', $dp['bn'], true), 'the deal page carries the Sales bar and the phone bottom bar');
    $dw = $draws['BDM (Mumbai)']['opportunity' . json_encode(['id' => $oW])][1];
    t_ok(strpos($dw['text'], 'said yes. 🎉') !== false || strpos($dw['text'], 'said yes.') !== false, 'a won deal still says "Won — … said yes."');
    t_ok((bool) array_filter($dw['buttons'], fn($b) => strpos($b, 'Send to Accounts to register the contract') !== false), '…and offers "Send to Accounts to register the contract"');
    $ex = $draws['Marketing Executive (Mumbai)']['opportunity' . json_encode(['id' => $oA])];
    t_eq((bool) array_filter($ex[1]['forms'], fn($s) => strpos($s, 'post /opportunity-move [') === 0), (bool) array_filter($ex[0]['forms'], fn($s) => strpos($s, 'post /opportunity-move [') === 0), 'Marketing Executive: "Move it" only if they had it before');
    foreach (['Business Director' => $f['bd'], 'BDM (Mumbai)' => $bdm] as $lbl => $uid) {
        $o = $draws[$lbl]['opportunity' . json_encode(['id' => $oA])];
        t_eq((bool) array_filter($o[1]['forms'], fn($s) => strpos($s, 'post /opportunity-delete [') === 0), (bool) array_filter($o[0]['forms'], fn($s) => strpos($s, 'post /opportunity-delete [') === 0), "$lbl: Delete exactly as before");
    }
    $dout = s3_draw($bdm, 'opportunity', ['id' => $oH], false);
    t_ok($dout['refused'] || $dout['done'] !== 'return', 'a Mumbai BDM cannot open the Ahmedabad deal (as before)');

    t_section('Sales Stage 3 — backend items 6 and 7, DEF-15 and DEF-16: the deal form');
    $nf = s3_draw($bdm, 'opportunity-new', [], false);
    t_ok((bool) array_filter($nf['forms'], fn($s) => strpos($s, 'post /opportunity-new [') === 0 && strpos($s, 'sbu') !== false), 'the new-deal form offers the business unit');
    t_ok(strpos($nf['text'], "can't be quoted") === false && strpos($nf['text'], 'can still be quoted') !== false, 'DEF-16: it no longer says a name-only deal cannot be quoted — it can');
    $cnt = fn($n) => (int) ops_val("SELECT COUNT(*) FROM opportunities WHERE name=?", [$n]);
    $name = 'S3 Form Check ' . $f['tag'];
    $r = s3_drive($bdm, 'opportunity-new', 'POST', [], ['name' => $name, 'partner_name' => 'X Co', 'value' => '-5', 'office_id' => $f['mum']]);
    t_eq($cnt($name), 0, 'a value below zero is refused — "' . s3_said($r) . '"');
    $r = s3_drive($bdm, 'opportunity-new', 'POST', [], ['name' => $name, 'partner_name' => 'X Co', 'value' => '100', 'expected_close' => '2026-02-30', 'office_id' => $f['mum']]);
    t_eq($cnt($name), 0, '30 February is not a real date — refused');
    $r = s3_drive($bdm, 'opportunity-new', 'POST', [], ['name' => $name, 'partner_name' => 'X Co', 'value' => '100', 'next_action_on' => '2026-13-01', 'office_id' => $f['mum']]);
    t_eq($cnt($name), 0, 'month 13 is not a real date — refused');
    $r = s3_drive($bdm, 'opportunity-new', 'POST', [], ['name' => $name, 'partner_name' => 'X Co', 'value' => '100', 'sbu' => 'NOT_A_UNIT', 'office_id' => $f['mum']]);
    t_eq($cnt($name), 0, 'an unknown business unit is refused');
    $r = s3_drive($bdm, 'opportunity-new', 'POST', [], ['name' => $name, 'partner_name' => 'X Co', 'value' => '250000', 'sbu' => $sk, 'probability' => '95', 'office_id' => $f['mum'],
                                                       'expected_close' => date('Y-m-d', strtotime('+10 days'))]);
    $nd = ops_one("SELECT * FROM opportunities WHERE name=?", [$name]); if ($nd) $made['opps'][] = (int) $nd['id'];
    t_ok($nd !== null, 'a correct form opens the deal (' . s3_said($r) . ')');
    t_eq((string) ($nd['sbu'] ?? ''), $sk, '…with the business unit chosen');
    t_eq((int) ($nd['probability'] ?? -1), 0, '…and the typed 95% is not taken: its win % follows the stage (DEF-15)');
    $row = $nd ? opp_row((int) $nd['id']) : null;
    t_eq($row ? (int) ($row['probability'] ?: $row['stage_prob']) : -1, (int) ($openSt[0]['probability'] ?? 0), '…the stage\'s own ' . (int) ($openSt[0]['probability'] ?? 0) . '%');
    //  Edit: the same checks; a unit sent unchanged (even one outside the person's units) is left alone.
    $r = s3_drive($bdm, 'opportunity-edit', 'POST', [], ['id' => $oA, 'value' => '-1']);
    t_ok(stripos(s3_said($r), '0 or more') !== false && (float) ops_val("SELECT value FROM opportunities WHERE id=?", [$oA]) === 1840000.0, 'Edit: a value below zero is refused and nothing changes');
    $r = s3_drive($bdm, 'opportunity-edit', 'POST', [], ['id' => $oA, 'next_action_on' => '2026-02-31']);
    t_ok(stripos(s3_said($r), 'not a real date') !== false, 'Edit: a date that is not real is refused');
    $r = s3_drive($bdm, 'opportunity-edit', 'POST', [], ['id' => $oA, 'sbu' => $sk]);
    t_eq((string) ops_val("SELECT sbu FROM opportunities WHERE id=?", [$oA]), $sk, 'Edit: the business unit is set (' . s3_said($r) . ')');
    $r = s3_drive($bdm, 'opportunity-edit', 'POST', [], ['id' => $oA, 'sbu' => 'NOT_A_UNIT']);
    t_eq((string) ops_val("SELECT sbu FROM opportunities WHERE id=?", [$oA]), $sk, 'Edit: an unknown unit is refused');
    $r = s3_drive($bdm, 'opportunity-edit', 'POST', [], ['id' => $oA, 'competitor' => 'DEF Ltd']);
    t_eq((string) ops_val("SELECT sbu FROM opportunities WHERE id=?", [$oA]), $sk, 'Edit: a form that does not send the unit leaves it as it is');
    t_eq((float) ops_val("SELECT value FROM opportunities WHERE id=?", [$oA]), 1840000.0, '…and the value too');
    //  DEF-16: no "drag" where nothing can be dragged.
    foreach (['opportunities', 'opportunity_detail', 'opportunity_form', 'leads', 'lead_detail', 'sx_board', 'stage_gates'] as $v) {
        $p = dirname(__DIR__) . '/views/ops/' . $v . '.php'; if (!is_file($p)) continue;
        $txt = preg_replace('#<\?php.*?\?>#s', '', (string) file_get_contents($p));
        t_ok(!preg_match('/\bdrag(s|ged|ging)?\b/i', strip_tags($txt)), "DEF-16: \"$v\" does not tell anyone to drag");
    }

    t_section('Sales Stage 3 — the new pages use the shared kit');
    foreach (['crm/inquiry_list', 'crm/inquiry_detail', 'crm/inquiry_form', 'opportunities', 'opportunity_detail', 'opportunity_form'] as $v) {
        $s = (string) file_get_contents(dirname(__DIR__) . '/views/ops/' . $v . '.php');
        t_ok(strpos($s, '_sxw_ui.php') !== false && strpos($s, 'sxw_nav(') !== false && strpos($s, 'sxw_bottom(') !== false, "$v draws with the Sales workspace kit (bar on a laptop, bottom bar on a phone)");
    }
} finally {
    foreach ($made['quotes'] as $id) { try { $pdo->prepare("DELETE FROM opportunity_quotes WHERE quotation_id=?")->execute([$id]); } catch (Throwable $e) {} $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]); }
    foreach ($made['opps'] as $id) if ($id) {
        foreach (['opportunity_stage_history', 'opportunity_quotes'] as $t) { try { $pdo->prepare("DELETE FROM $t WHERE opportunity_id=?")->execute([$id]); } catch (Throwable $e) {} }
        try { $pdo->prepare("DELETE FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=?")->execute([$id]); } catch (Throwable $e) {}
        $pdo->prepare("DELETE FROM opportunities WHERE id=?")->execute([$id]);
    }
    foreach ($made['inq'] as $id) { try { $pdo->prepare("DELETE FROM activities WHERE entity_kind='INQUIRY' AND entity_id=?")->execute([$id]); } catch (Throwable $e) {} $pdo->prepare("DELETE FROM crm_inquiries WHERE id=?")->execute([$id]); }
    foreach ($made['partners'] as $id) { try { $pdo->prepare("DELETE FROM activities WHERE partner_id=?")->execute([$id]); } catch (Throwable $e) {} $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$id]); }
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');
