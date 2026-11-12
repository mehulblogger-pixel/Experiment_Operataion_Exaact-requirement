<?php
// Owner, 12 Nov 2026 — the lead screen was "very confusing … where to click, how
// to click, why to click"; a quotation made from a lead "goes to Sales → Quotes
// and does not return to the lead"; "converted" means nothing to a simple user;
// the enquiry register showed no owner or branch (SAL-016); and the leads board
// showed "2 converted" on the card with an empty Converted column.
t_section('Lead — plain words, one next step');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    t_as_admin();
    leads_migrate();
    t_eq(LEAD_STATUS['CONVERTED'], 'Won — customer', 'a won lead reads "Won — customer", not "Converted"');
    $view = (string) file_get_contents(__DIR__ . '/../views/ops/lead_detail.php');
    t_ok(strpos($view, '<ol class="lead-steps"') !== false, 'the lead page shows the three plain steps');
    foreach (['Talk to them', 'Their answer', 'Log your first contact', "Make the <?= e(Tl('quote')) ?> →", 'More — change stage, mark lost'] as $w)
        t_ok(strpos($view, $w) !== false, 'the lead page says "' . $w . '"');
    t_ok(strpos($view, 'data-tab-go') !== false, 'the main button opens the right tab itself');
    t_ok(strpos($view, '$hasNext') !== false, 'a new lead does not draw nine empty "thread" boxes above its next step');
    $conv = (string) file_get_contents(__DIR__ . '/../views/ops/lead_convert.php');
    t_ok(strpos($conv, 'Yes — make them a customer') !== false && strpos($conv, 'Convert it') === false, 'the convert button says what it does');
    $tpl = (string) file_get_contents(__DIR__ . '/../lib/industry.php');
    t_ok(strpos($tpl, "['Converted',") === false, 'new industry pipelines name the won stage "Won — customer"');

    t_section('Lead — the enquiry it raises has an owner and a branch');
    $pdo->prepare("INSERT INTO offices (name) VALUES ('LSP Vadodara')")->execute(); $off = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, role, is_superuser, is_active, first_name, last_name, home_office_id) VALUES (?, 'BUSINESS_DEV_MANAGER', 0, 1, 'Lata', 'Sharma', ?)")
        ->execute(['lsp_lata_' . getmypid(), $off]);
    $lata = (int) $pdo->lastInsertId();
    $mk = function ($name, $ownerId, $ownerName, $office) use ($pdo) {
        $pdo->prepare("INSERT INTO leads (ref, company_name, contact_name, requirement, value, status, owner_user_id, owner_name, office_id, created_at, updated_at)
                       VALUES (?,?, 'Ravi', 'UT of welds', 100000, 'OPEN', ?, ?, ?, ?, ?)")
            ->execute(['LSP-' . mt_rand(1000, 99999), $name, $ownerId, $ownerName, $office, date('c'), date('c')]);
        return (int) $pdo->lastInsertId();
    };
    $typed = lead_row($mk('LSP Typed Owner Co', null, 'lata sharma', null));
    t_eq(lead_enquiry_assignee($typed), [$lata, $off], 'a typed owner name finds its login, and the branch comes from that person');
    $nobody = lead_row($mk('LSP Nobody Co', null, '', null));
    t_eq(lead_enquiry_assignee($nobody)[0], (int) current_user()['id'], 'a lead nobody owns: the person pressing the button gets the enquiry');
    $lid = $mk('LSP Fabricators ' . getmypid(), null, 'Lata Sharma', null);
    $r = lead_convert($lid, []);
    t_ok(!empty($r['ok']) && !empty($r['inquiry_id']), 'ARMING · the lead becomes a customer and an enquiry');
    $inq = ops_one("SELECT status, assigned_to, office_id FROM crm_inquiries WHERE id=?", [(int) $r['inquiry_id']]);
    t_eq([$inq['status'], (int) $inq['assigned_to'], (int) $inq['office_id']], ['OPEN', $lata, $off], 'the enquiry is Open, assigned to the lead\'s owner, in their branch');
    $list = (string) file_get_contents(__DIR__ . '/../views/ops/crm/inquiry_list.php');
    t_ok(strpos($list, '<th>Assigned to</th><th>Branch</th>') !== false, 'the register has its own "Assigned to" and "Branch" columns');

    t_section('Lead — a quotation made from it sits under it');
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, lead_id, status, created_at) VALUES (?, 0, 1, ?, 'DRAFT', ?)")
        ->execute(['LSP-Q-' . getmypid(), $lid, date('c')]);
    $q = crm_quote_get((int) $pdo->lastInsertId());
    $link = crm_quote_lead_link($q);
    t_eq($link['url'] ?? '', '/lead?id=' . $lid, 'Back from the quotation goes to its lead');
    t_ok(strpos((string) ($link['label'] ?? ''), 'LSP Fabricators') !== false, 'and the trail names the lead and the company');
    t_eq(crm_quote_lead_link(['lead_id' => 0]), null, 'a quotation not made from a lead keeps its usual place');
    nav_under('quote', 'leads', $link);
    $trail = nav_trail('quote');
    t_eq($trail[count($trail) - 2]['url'] ?? '', '/lead?id=' . $lid, 'the step above the quotation in the trail is the lead');

    t_section('Leads board — the won card and the won column agree');
    $pdo->prepare("INSERT INTO pipelines (name,entity_kind,is_default,active,created_at) VALUES ('LSP Pipe','LEAD',0,1,?)")->execute([date('c')]);
    $lp = (int) $pdo->lastInsertId();
    foreach ([[1, 'Qualified', 'OPEN'], [2, 'Won — customer', 'WON'], [3, 'Not pursued', 'LOST']] as [$seq, $nm, $k])
        $pdo->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active) VALUES (?,?,?,?,50,7,1)")->execute([$lp, $seq, $nm, $k]);
    $stg = pipeline_stages($lp); $qual = (int) $stg[0]['id']; $wonS = (int) $stg[1]['id']; $lostS = (int) $stg[2]['id'];
    $ins = function ($status, $stage) use ($pdo, $lp) {
        $pdo->prepare("INSERT INTO leads (ref, company_name, requirement, value, status, pipeline_id, stage_id, created_at, updated_at) VALUES (?, 'LSP Board Co', 'x', 1000, ?, ?, ?, ?, ?)")
            ->execute(['LSPB-' . mt_rand(1000, 99999), $status, $lp, $stage, date('c'), date('c')]);
    };
    $ins('OPEN', $qual);
    $ins('CONVERTED', $qual);   // won from an open stage (a won quotation keeps the stage it was on)
    $ins('CONVERTED', $wonS);
    $ins('LOST', $lostS);
    $fy = fy_filter(); $win = $fy['all'] ? [] : ['from' => $fy['from'], 'to_excl' => $fy['to_excl']];
    $b = lead_board($lp, $win);
    t_eq(count($b['columns'][$qual]['leads']), 1, 'the open column holds only the open lead');
    t_eq(count($b['columns'][$wonS]['leads']), 2, 'both won leads show in the won column, whatever stage they were won from');
    t_eq(count($b['columns'][$lostS]['leads']), 1, 'the not-pursued lead shows in its column');
    $wonAll = (int) ops_val("SELECT COUNT(*) FROM leads WHERE status='CONVERTED' AND pipeline_id=?", [$lp]);
    t_eq((int) $b['columns'][$wonS]['total'], $wonAll, 'the won column count is the number of won leads');
    t_ok(leads_counts($win)['converted'] >= $wonAll, 'and the "Won" card counts the same year');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
