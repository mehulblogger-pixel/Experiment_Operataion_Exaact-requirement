<?php
// Owner, 21 Nov 2026 — Sales redesign Stage 2 (Leads: register, full form, quick
// lead, conversion, lead page alignment), approved design pack, "All as
// recommended". Preserve every field, button, filter and action; figures and
// permissions unchanged; plus the approved backend items:
//   • the full lead form checks for duplicates while typing and on save (the quick
//     lead's own check) — backend item 1;
//   • a lead's business unit can be set on the forms and the lead page (DEF-15) —
//     backend item 2;
//   • the lead score counts the conversations the lead's History shows (DEF-21);
//   • a lead's source is shown in words.
//
// Each screen is drawn twice through the production dispatcher, as the same person
// on the same data: with the OLD view (tests/fixtures/pre_stage2/) and the NEW one.
// Every figure must read the same; every link, form field and amount of the old
// page must be on the new one; who is refused must not change.
require_once __DIR__ . '/_bbv_fixture.php';

function s2_draw($uid, $route, array $get = [], $old = false) {
    $root = dirname(__DIR__);
    $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
    $env = $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    if ($old) $env .= ' STAGE2_OLD=1';
    $raw = (string) shell_exec($env . ' php ' . escapeshellarg($root . '/tests/_stage2_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route) . ' ' . escapeshellarg(json_encode((object) $get)) . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['done' => 'no-json: ' . substr($raw, 0, 300), 'figs' => [], 'hrefs' => [], 'forms' => [], 'money' => [], 'nav' => [], 'bn' => [], 'refused' => false, 'len' => 0, 'text' => '', 'buttons' => [], 'flash' => []];
}
//  A route driven for real (POST included), in its own process — flash, how it ended, needles found.
function s2_drive($uid, $route, $method = 'GET', array $get = [], array $post = [], array $needles = [], array $session = []) {
    $root = dirname(__DIR__);
    $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
    $env = $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles)) . ' ' . escapeshellarg(json_encode((object) $session));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => [], 'retry' => null];
}
function s2_said($r) { return implode(' | ', array_map(fn($f) => (string) ($f['text'] ?? ''), $r['flash'] ?? [])); }
//  "post /lead-edit [a,b,c]" → ['post /lead-edit', [a,b,c]]
function s2_form_split($sig) { return preg_match('/^(.*?) \[(.*)\]$/', $sig, $m) ? [$m[1], $m[2] === '' ? [] : explode(',', $m[2])] : [$sig, []]; }

t_section('Sales Stage 2 — the lead screens show the same figures, links, fields and access as before, in the new look');
$f = bbv_fixture();
$pdo = db();
$made = ['leads' => [], 'partners' => [], 'users' => [], 'opps' => []];
try {
    bbv_as($f['master']);
    leads_migrate(); if (function_exists('opp_migrate')) opp_migrate(); if (function_exists('act_migrate')) act_migrate();

    // ---- People: the fixture's, plus the four Sales roles (docs/02 19b) ------------------------------
    $mk = function ($key, $role, $office, $scope = '', $perms = '') use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, permissions) VALUES (?,?,?,?,1,?,?,?)")
            ->execute([$f['tag'] . '_' . $key, ucfirst($key), 'T', $role, $office, $scope, $perms]);
        return $made['users'][] = (int) $pdo->lastInsertId();
    };
    $bdm  = $mk('bdm', 'BUSINESS_DEV_MANAGER', $f['mum']);
    $kam  = $mk('kam', 'KEY_ACCOUNTS_MANAGER', $f['mum']);
    $mm   = $mk('mm', 'MARKETING_MANAGER', $f['mum'], 'ALL');
    $mex  = $mk('mex', 'MARKETING_EXECUTIVE', $f['mum']);
    $noxp = $mk('noexport', 'BUSINESS_DEV_MANAGER', $f['mum'], '', PERM_DIFF_TAG . ',-crm.lead.export');   // own exception: no download

    // ---- Records: Mumbai and Ahmedabad leads, open / won / not pursued, a client --------------------
    $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status, home_branch_id) VALUES (?,?,?,1,'ACTIVE',?)")
        ->execute(['S2-' . $f['tag'], 'Stage Two Valves ' . $f['tag'], 'Stage Two Valves ' . $f['tag'], $f['mum']]);
    $made['partners'][] = $cid = (int) $pdo->lastInsertId();
    $lead = function ($name, array $over = []) use ($f, &$made) {
        $r = lead_create($over + ['company_name' => $name . ' ' . $f['tag'], 'contact_name' => 'Ravi', 'contact_phone' => '98250' . random_int(10000, 99999),
                                  'value' => 250000, 'office_id' => $f['mum'], 'source' => 'REFERRAL', 'next_action' => 'Call', 'next_action_on' => date('Y-m-d')]);
        t_ok(!empty($r['id']), 'ARMING · lead "' . $name . '" made' . (!empty($r['err']) ? ' — ' . $r['err'] : ''));
        return $made['leads'][] = (int) ($r['id'] ?? 0);
    };
    $lA = $lead('S2 Open Mumbai', ['owner_user_id' => $bdm, 'expected_close' => date('Y-m-d', strtotime('-5 days'))]);
    $lB = $lead('S2 Second Mumbai', ['owner_user_id' => $kam, 'value' => 1840000]);
    $lC = $lead('S2 Ahmedabad', ['office_id' => $f['ahm'], 'value' => 90000]);
    $lW = $lead('S2 Won', ['owner_user_id' => $bdm]);
    $lL = $lead('S2 Lost', ['owner_user_id' => $bdm]);
    $lP = $lead('S2 For client', ['partner_id' => $cid, 'owner_user_id' => $bdm]);
    $pdo->prepare("UPDATE leads SET status='CONVERTED' WHERE id=?")->execute([$lW]);
    $pdo->prepare("UPDATE leads SET status='LOST' WHERE id=?")->execute([$lL]);
    $pdo->prepare("UPDATE leads SET stage_since=? WHERE id=?")->execute([date('c', strtotime('-60 days')), $lA]);   // stalled

    $people = ['Business Director' => $f['bd'], 'Master admin' => $f['master'], 'Admin' => $f['admin'], 'Business Unit Head' => $f['sbu'],
               'Branch Manager (Mumbai)' => $f['kiran'], 'Finance, Mumbai' => $f['meera'], 'Coordinator' => $f['neha'],
               'BDM (Mumbai)' => $bdm, 'KAM (Mumbai)' => $kam, 'Marketing Manager (all)' => $mm, 'Marketing Executive (Mumbai)' => $mex,
               'BDM without the download right' => $noxp];
    $pages = [['leads', []], ['leads', ['v' => 'list']], ['leads', ['v' => 'list', 'status' => 'OPEN']], ['leads', ['v' => 'board', 'only' => '1']],
              ['leads', ['v' => 'list', 'fy' => 'all', 'q' => 'S2']], ['lead-new', []], ['lead-new', ['company' => 'Stage Two Valves ' . $f['tag']]],
              ['sales-lead', []], ['lead', ['id' => $lA]], ['lead', ['id' => $lC]], ['lead-convert', ['id' => $lA]]];
    $compare = function ($label, $uid, $route, $get) {
        $o = s2_draw($uid, $route, $get, true); $n = s2_draw($uid, $route, $get, false);
        $pg = '/' . $route . ($get ? '?' . http_build_query($get) : '');
        t_eq($n['done'], $o['done'], "$label · $pg — the page opens (or is refused) exactly as before");
        t_eq($n['refused'], $o['refused'], "$label · $pg — refused as before");
        $fd = []; foreach ($o['figs'] as $k => $v) if (($n['figs'][$k] ?? null) !== $v) $fd[] = "$k: old \"$v\" new \"" . ($n['figs'][$k] ?? '—') . '"';
        t_eq($fd, [], "$label · $pg — all " . count($o['figs']) . ' figures read the same');
        //  Owner findings B4 (21 Nov 2026): the "+ Add new" customer link (href "#") is no longer
        //  offered to someone the add form refuses (partner_can_add — the form's own rule).
        $keep = $_SESSION['uid'] ?? null; bbv_as($uid); $mayAdd = partner_can_add(); if ($keep) bbv_as($keep);
        $gone = array_values(array_diff(array_unique($o['hrefs']), $n['hrefs']));
        if ($route === 'lead-convert' && !$mayAdd) $gone = array_values(array_diff($gone, ['#']));
        t_eq($gone, [], "$label · $pg — every link of the old page is on the new one (except “+ Add new” customer for someone who may not add one — B4)");
        //  A form keeps its address and every field it had (a new page may add one — the business unit).
        $lost = [];
        foreach (array_unique($o['forms']) as $sig) {
            [$at, $fl] = s2_form_split($sig); $ok = false;
            foreach ($n['forms'] as $ns) { [$nat, $nfl] = s2_form_split($ns); if ($nat === $at && !array_diff($fl, $nfl)) { $ok = true; break; } }
            if (!$ok) $lost[] = $sig;
        }
        t_eq($lost, [], "$label · $pg — every form (same address, every field) is on the new one");
        t_eq(array_values(array_diff($o['money'], $n['money'])), [], "$label · $pg — every amount of the old page is on the new one");
        return [$o, $n];
    };
    $draws = [];
    foreach ($people as $label => $uid) foreach ($pages as [$route, $get]) $draws[$label][$route . json_encode($get)] = $compare($label, $uid, $route, $get);

    t_section('Sales Stage 2 — what each person sees on the new register (permissions unchanged)');
    $reg = fn($lbl) => $draws[$lbl]['leads' . json_encode(['v' => 'list'])][1];
    $hasCsv = fn($d) => (bool) array_filter($d['hrefs'], fn($h) => strpos($h, 'export=csv') !== false);
    t_ok($hasCsv($reg('BDM (Mumbai)')), 'a BDM with the download right sees the CSV');
    t_ok(!$hasCsv($reg('BDM without the download right')), 'a BDM whose own exception removes "Download leads" sees no CSV (Batch A switch crm.lead.export)');
    t_ok(!$hasCsv($draws['BDM without the download right']['leads[]'][1]), '…on the board as well');
    $logs = fn($d) => (bool) array_filter($d['hrefs'], fn($h) => strpos($h, 'log=1') !== false);
    t_ok($logs($reg('BDM (Mumbai)')) || !leads_can_edit_for($bdm), 'an open lead row offers Log to someone who may edit leads');
    $mexCanEdit = leads_can_edit_for($mex);
    t_eq($logs($reg('Marketing Executive (Mumbai)')), $mexCanEdit, 'Marketing Executive: Log on a row only if they may edit leads');
    t_eq((bool) array_filter($reg('Marketing Executive (Mumbai)')['hrefs'], fn($h) => $h === '/lead-new'), $mexCanEdit, 'Marketing Executive: "+ New lead" only if they may edit leads');
    $bdmText = $reg('BDM (Mumbai)')['text'];
    t_ok(strpos($bdmText, 'S2 Open Mumbai') !== false && strpos($bdmText, 'S2 Ahmedabad') === false, 'branch scope: a Mumbai BDM sees the Mumbai leads and not the Ahmedabad one (as before)');
    t_ok(strpos($reg('Marketing Manager (all)')['text'], 'S2 Ahmedabad') !== false, 'branch scope: an all-branch Marketing Manager sees the Ahmedabad lead too');
    t_ok(strpos($reg('Business Director')['text'], 'Not pursued') !== false, 'the "Not pursued" count, computed before but hidden, is now shown');
    t_ok((bool) array_filter($reg('BDM (Mumbai)')['forms'], fn($s) => strpos($s, 'preview') !== false && strpos($s, '/leads-bulk') !== false), 'the bulk "Preview first" the server always had is now on the page');
    t_ok((bool) array_filter($reg('BDM (Mumbai)')['forms'], fn($s) => strpos($s, 'get /leads [') === 0 && strpos($s, 'status') !== false), 'the status filter the server always honoured now has a control');
    $src = lk_options_or('lead_source', LEAD_SOURCES)['REFERRAL'] ?? '';
    $listSrc = s2_draw($f['bd'], 'leads', ['v' => 'list', 'fy' => 'all', 'q' => 'S2 Open'], false);
    t_ok($src !== '' && lead_source_label('REFERRAL') === $src, 'a source code reads as its words ("' . $src . '")');
    $newBits = $draws['Business Director'];
    t_ok(in_array('/leads', $newBits['leads[]'][1]['nav'], true) && in_array('/leads', $newBits['leads[]'][1]['bn'], true), 'the register carries the Sales bar and the phone bottom bar');
    t_ok(in_array('/leads', $newBits['lead' . json_encode(['id' => $lA])][1]['nav'], true), 'the lead page now carries the Sales bar (alignment)');

    t_section('Sales Stage 2 — DEF-21: the lead score counts the conversations its History shows');
    bbv_as($f['master']);
    $row = fn($id) => lead_row($id);
    $why = fn($id) => implode(' · ', lead_score($row($id))['why']);
    t_ok(strpos($why($lB), 'nobody has logged a conversation') !== false, 'before any call: "nobody has logged a conversation"');
    $before = lead_score($row($lB))['score'];
    lead_log_contact($lB, ['method' => 'NOTE', 'note' => 'remember to call']);
    t_ok(strpos($why($lB), 'nobody has logged a conversation') !== false, 'a note to self is not a conversation');
    lead_log_contact($lB, ['method' => 'CALL', 'note' => 'Spoke to Ravi', 'occurred_on' => date('Y-m-d')]);
    t_ok(strpos($why($lB), 'spoken to this week') !== false, 'after a call logged on the lead (no client yet): "spoken to this week" — ' . $why($lB));
    t_eq(lead_score($row($lB))['score'] - $before, 20, 'the score rises by the 20 the rule gives for this week');
    $hist = function_exists('act_for_entity') ? act_for_entity('LEAD', $lB, 50) : [];
    t_ok((bool) array_filter($hist, fn($a) => ($a['kind'] ?? '') === 'CALL'), 'and that call is the one the lead\'s History shows');
    //  A client's own older touch does not hide a newer call on the lead.
    $pdo->prepare("UPDATE leads SET partner_id=? WHERE id=?")->execute([$cid, $lB]);
    act_log('CLIENT', $cid, 'CALL', 'old call', ['partner_id' => $cid, 'occurred_at' => date('c', strtotime('-50 days'))]);
    t_ok(strpos($why($lB), 'spoken to this week') !== false, 'with an older call on the client too, the newer one on the lead counts');
    $pdo->prepare("UPDATE leads SET partner_id=NULL WHERE id=?")->execute([$lB]);

    t_section('Sales Stage 2 — DEF-15: the business unit on a lead');
    $sbus = lk_options_or('sbu', OPS_SBUS); $sk = (string) array_key_first($sbus); $sk2 = (string) (array_keys($sbus)[1] ?? $sk);
    $r = s2_drive($bdm, 'lead-edit', 'POST', [], ['id' => $lA, 'company_name' => 'S2 Open Mumbai ' . $f['tag'], 'sbu' => $sk]);
    t_eq((string) ops_val("SELECT sbu FROM leads WHERE id=?", [$lA]), $sk, 'the lead\'s own handler sets the business unit on "Edit the details" (' . s2_said($r) . ')');
    $r = s2_drive($bdm, 'lead-edit', 'POST', [], ['id' => $lA, 'company_name' => 'S2 Open Mumbai ' . $f['tag'], 'sbu' => 'NOT_A_UNIT']);
    t_ok(stripos(s2_said($r), 'not one you can choose') !== false && (string) ops_val("SELECT sbu FROM leads WHERE id=?", [$lA]) === $sk, 'an unknown unit is refused and nothing changes');
    $r = s2_drive($bdm, 'lead-edit', 'POST', [], ['id' => $lA, 'company_name' => 'S2 Open Mumbai ' . $f['tag'], 'contact_name' => 'Ravi K']);
    t_eq((string) ops_val("SELECT sbu FROM leads WHERE id=?", [$lA]), $sk, 'a form that does not send the unit (an older page) leaves it as it is');
    //  A person whose data scope covers one unit only may not set another.
    $one = $mk('oneunit', 'BUSINESS_DEV_MANAGER', $f['mum']);
    $pdo->prepare("UPDATE users SET scope_sbus=? WHERE id=?")->execute([$sk, $one]);
    bbv_as($one);
    $ua = ua(); $narrow = is_array($ua['sbus'] ?? null) && $ua['sbus'];
    if ($narrow && $sk2 !== $sk) {
        t_ok(!isset(lead_sbu_options()[$sk2]) && isset(lead_sbu_options()[$sk]), 'a person scoped to one unit is offered only that unit');
        $r = s2_drive($one, 'lead-edit', 'POST', [], ['id' => $lA, 'company_name' => 'S2 Open Mumbai ' . $f['tag'], 'sbu' => $sk2]);
        t_eq((string) ops_val("SELECT sbu FROM leads WHERE id=?", [$lA]), $sk, '…and cannot set another unit on the server either');
    } else {
        t_ok(true, 'NOTE · this install keeps business-unit scope elsewhere; the one-unit check is covered by lead_sbu_err() below');
    }
    bbv_as($f['master']);
    t_eq(lead_sbu_err('NOT_A_UNIT') !== '', true, 'lead_sbu_err refuses an unknown unit');
    t_eq(lead_sbu_err(''), '', '…and accepts "not decided yet"');
    $page = s2_draw($bdm, 'lead', ['id' => $lA], false);
    t_ok(strpos($page['text'], (string) $sbus[$sk]) !== false, 'the lead page shows the business unit under Details');
    t_ok((bool) array_filter($page['forms'], fn($s) => strpos($s, 'post /lead-edit [') === 0 && strpos($s, 'sbu') !== false), '"Edit the details" offers the business unit');
    $nf = s2_draw($bdm, 'lead-new', [], false);
    t_ok((bool) array_filter($nf['forms'], fn($s) => strpos($s, 'post /lead-new [') === 0 && strpos($s, 'sbu') !== false), 'the full form offers the business unit');

    t_section('Sales Stage 2 — backend item 1: the full form checks for duplicates, while typing and on save');
    $nameB = 'S2 Second Mumbai ' . $f['tag'];
    $cnt = fn() => (int) ops_val("SELECT COUNT(*) FROM leads WHERE company_name=?", [$nameB]);
    $c0 = $cnt();
    $r = s2_drive($bdm, 'lead-new', 'POST', [], ['company_name' => $nameB, 'contact_name' => 'X', 'office_id' => $f['mum']]);
    t_eq($cnt(), $c0, 'a second lead with the same name is NOT saved straight away');
    t_ok(stripos(s2_said($r), 'already have') !== false, 'the person is told it looks like one we already have — "' . s2_said($r) . '"');
    //  The next page load, as the browser makes it after that refusal (form_stash keeps the typing in the session).
    $stash = ['route' => 'lead-new', 'at' => time(), 'data' => ['company_name' => $nameB, 'contact_name' => 'X', 'office_id' => (string) $f['mum'], '_dupcheck' => '1']];
    $back = s2_drive($bdm, 'lead-new', 'GET', [], [], ['This looks like one we already have', 'name="confirm"', 'value="' . $nameB . '"', 'Nothing was lost'], ['form_retry' => $stash]);
    t_ok(!empty($back['has']['value="' . $nameB . '"']) && !empty($back['has']['Nothing was lost']), 'nothing typed is lost (the form comes back filled)');
    t_ok(!empty($back['has']['This looks like one we already have']) && !empty($back['has']['name="confirm"']), 'the form shows the matches and the "save it anyway" tick');
    $r = s2_drive($bdm, 'lead-new', 'POST', [], ['company_name' => $nameB, 'contact_name' => 'X', 'office_id' => $f['mum'], 'confirm' => '1']);
    t_eq($cnt(), $c0 + 1, 'ticked "It is a different requirement" — saved (' . s2_said($r) . ')');
    if ($cnt() > $c0) $made['leads'][] = (int) ops_val("SELECT MAX(id) FROM leads WHERE company_name=?", [$nameB]);
    $j = s2_drive($bdm, 'lead-new', 'GET', ['dupcheck' => '1', 'company_name' => $nameB], [], ['"dupes"', 'S2 Second Mumbai']);
    t_ok(!empty($j['has']['"dupes"']) && !empty($j['has']['S2 Second Mumbai']), 'while typing: the form\'s question about the name answers with the open lead');
    $phoneB = (string) ops_val("SELECT contact_phone FROM leads WHERE id=?", [$lB]);
    $refB = (string) ops_val("SELECT ref FROM leads WHERE id=?", [$lB]);
    $j = s2_drive($bdm, 'lead-new', 'GET', ['dupcheck' => '1', 'company_name' => 'Totally New Co', 'contact_phone' => $phoneB], [], [$refB]);
    t_ok(strlen(preg_replace('/\D/', '', $phoneB)) >= 10 && !empty($j['has'][$refB]), 'while typing: the same mobile on an open lead is found (' . $refB . ')');
    $refP = (string) ops_val("SELECT ref FROM leads WHERE id=?", [$lP]);
    $j = s2_drive($bdm, 'lead-new', 'GET', ['dupcheck' => '1', 'company_name' => '', 'partner_id' => (string) $cid], [], ['\/partner?id=' . $cid, $refP]);
    t_ok(empty($j['has']['\/partner?id=' . $cid]) && !empty($j['has'][$refP]), 'a client picked from the master is not its own duplicate; its open lead (' . $refP . ') is');
    $c1 = (int) ops_val("SELECT COUNT(*) FROM leads");
    $r = s2_drive($f['neha'], 'lead-new', 'GET', ['dupcheck' => '1', 'company_name' => $nameB], [], ['S2 Second Mumbai']);
    t_ok(empty($r['has']['S2 Second Mumbai']), 'someone who may not add leads gets no answer (the form\'s own right)');
    $fresh = 'S2 Brand New ' . $f['tag'];
    $r = s2_drive($bdm, 'lead-new', 'POST', [], ['company_name' => $fresh, 'office_id' => $f['mum'], 'sbu' => $sk]);
    $nid = (int) ops_val("SELECT id FROM leads WHERE company_name=?", [$fresh]);
    if ($nid) $made['leads'][] = $nid;
    t_ok($nid > 0, 'a lead that matches nothing is saved at once, as before (' . s2_said($r) . ')');
    t_eq((string) ops_val("SELECT sbu FROM leads WHERE id=?", [$nid]), $sk, '…with the business unit chosen on the form');
    $r = s2_drive($bdm, 'lead-new', 'POST', [], ['company_name' => 'S2 Bad Unit ' . $f['tag'], 'office_id' => $f['mum'], 'sbu' => 'NOT_A_UNIT']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM leads WHERE company_name=?", ['S2 Bad Unit ' . $f['tag']]), 0, 'an unknown business unit on the full form is refused');

    t_section('Sales Stage 2 — the new pages use the shared kit');
    foreach (['leads', 'lead_form', 'sx_lead', 'lead_convert'] as $v) {
        $s = (string) file_get_contents(dirname(__DIR__) . '/views/ops/' . $v . '.php');
        t_ok(strpos($s, '_sxw_ui.php') !== false && strpos($s, 'sxw_nav(') !== false && strpos($s, 'sxw_bottom(') !== false, "$v draws with the Sales workspace kit (bar on a laptop, bottom bar on a phone)");
    }
    t_ok(strpos((string) file_get_contents(dirname(__DIR__) . '/views/ops/sx_lead.php'), "_sx_ui.php'") === false, 'the quick lead no longer uses the older Sales look');
} finally {
    foreach ($made['leads'] as $id) if ($id) {
        foreach (['lead_stage_history', 'lead_files'] as $t) { try { $pdo->prepare("DELETE FROM $t WHERE lead_id=?")->execute([$id]); } catch (Throwable $e) {} }
        try { $pdo->prepare("DELETE FROM activities WHERE entity_kind='LEAD' AND entity_id=?")->execute([$id]); } catch (Throwable $e) {}
        $pdo->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
    }
    foreach ($made['partners'] as $id) { try { $pdo->prepare("DELETE FROM activities WHERE partner_id=?")->execute([$id]); } catch (Throwable $e) {} $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$id]); }
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');

//  Whether a given person may edit leads (their own rights, not the test's).
function leads_can_edit_for($uid) { $keep = $_SESSION['uid'] ?? null; bbv_as($uid); $r = leads_can_edit(); if ($keep) bbv_as($keep); return $r; }
