<?php
// Recruitment module fixes (from the field review). Grows as each item lands.
t_section('recruitment: navigation & cross-linking (1a)');

// Breadcrumbs on the two list screens that lacked them.
$reqList = file_get_contents(__DIR__ . '/../views/ops/requisition_list.php');
$candList = file_get_contents(__DIR__ . '/../views/ops/candidate_list.php');
t_ok(strpos($reqList, 'class="crumbs"') !== false && strpos($reqList, '/recruitment') !== false,
    'the requisition list has breadcrumbs back to Recruitment');
t_ok(strpos($candList, 'class="crumbs"') !== false && strpos($candList, '/recruitment') !== false,
    'the candidate list has breadcrumbs back to Recruitment');

// Candidate detail links back to its requisition.
$candDetail = file_get_contents(__DIR__ . '/../views/ops/candidate_detail.php');
t_ok(strpos($candDetail, '/requisition?id=<?= (int)$linkReq[\'id\'] ?>') !== false,
    'the candidate detail links to its requisition');

t_section('recruitment: PO ref / contract number split, facilities provider, editable masters (1d/1e/1f)');
req_migrate();

// 1d — PO ref and contract number are now separate columns and fields.
$reqCols = array_map(fn($c) => $c['name'], ops_all("PRAGMA table_info(requisitions)"));
t_ok(in_array('po_ref', $reqCols, true) && in_array('contract_ref', $reqCols, true),
    'the requisition has separate po_ref and contract_ref columns');
t_ok(in_array('po_ref', req_extra_fields(), true), 'po_ref is persisted on save');
$reqForm = file_get_contents(__DIR__ . '/../views/ops/requisition_form.php');
t_ok(strpos($reqForm, 'name="contract_ref"') !== false && strpos($reqForm, 'name="po_ref"') !== false,
    'the form has both a Contract number and a PO reference box');

// 1f — facilities have a Client/Us provider selector, incl. Local conveyance.
t_ok(in_array('prov_food_by', $reqCols, true) && in_array('prov_local_by', $reqCols, true),
    'facility provider columns exist (incl. local conveyance)');
t_ok(strpos($reqForm, "\$provBy('prov_local_by', 'Local conveyance')") !== false
    && strpos($reqForm, "\$provBy('prov_food_by', 'Food')") !== false
    && strpos($reqForm, "'CLIENT' => 'Client'") !== false,
    'the form offers a provided-by (Us / Client) selector for each facility, including Local conveyance');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "\$b['prov_food']          = ((\$b['prov_food_by']   ?? '') === 'US')") !== false,
    'the legacy prov_* booleans are kept in sync with the new selectors');

// 1e — the previously hard-coded requisition dropdowns are registered as masters and
// read through lk_options_or (editable in Admin -> Masters -> People).
$lk = file_get_contents(__DIR__ . '/../lib/lookups.php');
t_ok(strpos($lk, "'req_work_model'") !== false && strpos($lk, "'req_shift'") !== false && strpos($lk, "'req_rate_basis'") !== false,
    'work model / shift / rate basis are registered as editable masters');
t_ok(strpos($reqForm, "lk_options_or('req_work_model'") !== false
    && strpos($reqForm, "lk_options_or('req_shift'") !== false
    && strpos($reqForm, "lk_options_or('req_rate_basis'") !== false
    && strpos($reqForm, "lk_options_or('sbu'") !== false,
    'the requisition form reads those dropdowns (and SBU) from the editable lookups');

t_section('recruitment: requisition → candidate data flow (1b)');
// The picker list carries the fields the candidate form pre-fills.
$rl = requisitions_list(false);
// (Insert a requisition to prove the enriched columns come back.)
db()->prepare("INSERT INTO requisitions (req_code, designation, sbu, client_id, billing_rate, rate_basis, status, created_at) VALUES ('REQ-PF1','WELDING_INSPECTOR','NDT', 7, 90000, 'MONTHLY', 'OPEN', ?)")->execute([date('c')]);
$row = ops_one("SELECT * FROM requisitions WHERE req_code='REQ-PF1'");
$listed = null; foreach (requisitions_list(true) as $r) if ((int)$r['id'] === (int)$row['id']) { $listed = $r; break; }
t_ok($listed && array_key_exists('client_id', $listed) && array_key_exists('sbu', $listed) && array_key_exists('billing_rate', $listed),
    'requisitions_list carries client / SBU / rate for prefill');
$candForm = file_get_contents(__DIR__ . '/../views/ops/candidate_form.php');
t_ok(strpos($candForm, 'data-client="') !== false && strpos($candForm, 'data-designation="') !== false
    && strpos($candForm, 'data-sbu="') !== false && strpos($candForm, 'data-rate="') !== false,
    'the requisition picker carries the prefill fields as data-attributes');
t_ok(strpos($candForm, 'prefillFromReq') !== false && strpos($candForm, 'setIfEmpty') !== false,
    'the candidate form prefills client/designation/SBU/rate from the chosen requisition (only blank fields)');

t_section('recruitment: deployment groups — headcount + reporting contact + site (1c)');
req_groups_migrate();
$pdo = db();
// A client with two contacts, and a requisition.
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Grp Client','Grp Client',1,'ACTIVE')")->execute();
$gcid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO partner_contacts (partner_id, name, designation, mobile, is_primary) VALUES (?, 'Mr A','Site Head','9990001111',1)")->execute([$gcid]);
$ctA = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO requisitions (req_code, designation, client_id, quantity, status, created_at) VALUES ('REQ-GRP1','WELDING_INSPECTOR',?, 1, 'OPEN', ?)")->execute([$gcid, date('c')]);
$grpReq = (int)$pdo->lastInsertId();

// Save three groups: 3 under contact A, 3 under a typed name B, 2 under C — total 8.
$total = req_groups_save($grpReq, [
    'group_headcount'    => ['3', '3', '2', ''],   // last row empty → skipped
    'group_contact_id'   => [(string)$ctA, '', '', ''],
    'group_report_name'  => ['', 'Mr B', 'Mr C', ''],
    'group_report_phone' => ['', '8880002222', '', ''],
    'group_report_email' => ['', '', '', ''],
    'group_site'         => ['Dahej', 'Hazira', 'Mundra', ''],
    'group_notes'        => ['', '', '', ''],
]);
t_ok($total === 8, 'the group headcounts total correctly (3+3+2)');
$gs = req_groups($grpReq);
t_ok(count($gs) === 3, 'three groups were saved (the empty row was skipped)');
t_ok($gs[0]['report_display'] === 'Mr A' && (int)$gs[0]['headcount'] === 3,
    'a group linked to a client contact resolves that contact\'s name');
t_ok($gs[1]['report_display'] === 'Mr B' && $gs[1]['report_phone_display'] === '8880002222',
    'a group with a typed reporting person keeps the typed name & phone');
t_ok(req_groups_total($grpReq) === 8, 'the requisition total headcount is the sum of the groups');

// Re-saving replaces (not appends) the groups.
req_groups_save($grpReq, ['group_headcount' => ['5'], 'group_contact_id' => [''], 'group_report_name' => ['Solo'], 'group_site' => ['One site']]);
t_ok(count(req_groups($grpReq)) === 1 && req_groups_total($grpReq) === 5, 're-saving groups replaces the previous set');

// Wiring: the client-contacts endpoint, the form group rows, and the detail display.
t_ok(strpos($ops, "route === 'client-contacts'") !== false, 'a client-contacts JSON endpoint feeds the reporting-contact picker');
t_ok(strpos($reqForm, 'group_headcount[]') !== false && strpos($reqForm, 'rqg-contact') !== false && strpos($reqForm, '/client-contacts?id=') !== false,
    'the requisition form has repeatable group rows with a client-contact picker');
$reqDetail = file_get_contents(__DIR__ . '/../views/ops/requisition_detail.php');
t_ok(strpos($reqDetail, 'Deployment groups') !== false, 'the requisition detail shows the deployment groups');

t_section('recruitment: tag a candidate to a deployment group + universal back button');
// candidates carry a group_id, coerced like the other ids, and saved.
$candCols = array_map(fn($c) => $c['name'], ops_all("PRAGMA table_info(candidates)"));
t_ok(in_array('group_id', $candCols, true), 'candidates carry a group_id');
t_ok(nzc_cand('group_id', '') === null && nzc_cand('group_id', '7') === 7, 'group_id is coerced to an int / null');
t_ok(strpos($candForm, 'name="group_id"') !== false && strpos($candForm, 'REQ_GROUPS') !== false && strpos($candForm, 'fillGroups') !== false,
    'the candidate form offers the requirement\'s deployment groups');
t_ok(strpos($reqDetail, "\$grpFilled") !== false && strpos($reqDetail, '>Filled<') !== false,
    'the requisition detail shows how many of each group are filled');

// Universal "back one step" button on the top bar.
$layout = file_get_contents(__DIR__ . '/../views/layout_top.php');
t_ok(strpos($layout, 'id="tbBack"') !== false && strpos($layout, 'history.back()') !== false,
    'the top bar has a universal back-one-step button');
t_ok(strpos($layout, 'history.length>1') !== false,
    'the back button only shows when there is a step to go back to');
