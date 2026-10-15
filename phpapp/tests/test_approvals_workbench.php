<?php
// ---------------------------------------------------------------------------
//  THE APPROVALS SETUP WORKBENCH — UAT 1.7.3
//
//  The owner found four approval CONFIGURATION screens in three areas and said:
//
//    "sometimes it takes you to this module and that module under the pretext
//     of this bloody things. Let us make the approval chain applicable to each
//     of its own module in their own module screens under setup."
//
//  The shape built is one workbench organised by module, with the module's own
//  screens unchanged behind it — the arrangement Salesforce and Zoho both
//  settled on. The thing this test exists to guard is the part that could do
//  real damage:
//
//      A UNIFIED WAY IN MUST NOT BE A UNIFIED WAY PAST THE GATES.
//
//  The four destinations have genuinely different audiences. A recruitment
//  administrator (hiring.admin) may configure hiring approvals and must NOT
//  reach quote approvals. If the workbench offered every tile to everybody it
//  would either advertise screens that then refuse — the same defect in a new
//  place — or, far worse, become a door around the gates.
// ---------------------------------------------------------------------------

t_ok(function_exists('apsetup_areas'), 'the workbench knows its areas');
t_ok(function_exists('apsetup_open'),  'and which of them this viewer may open');
t_ok(function_exists('apsetup_strip'), 'and can draw the shared tab strip');
t_ok(function_exists('ops_approvals_setup'), 'the workbench screen exists');

// ---- it offers the four, and only the four -------------------------------
$areas = apsetup_areas();
t_eq(count($areas), 4, 'four approval areas, which is how many the product has');
$keys = array_map(fn($a) => $a['key'], $areas);
foreach (['recruitment','quotes','approvermap','delegation'] as $k)
    t_ok(in_array($k, $keys, true), "the workbench knows about '$k'");

//  Every area points at the screen that already existed. The workbench is a
//  way IN, never a reimplementation: a second copy of this configuration would
//  be a second place for it to drift.
$urls = array_map(fn($a) => $a['url'], $areas);
foreach (['/recruit-approvals','/approval-rules','/approver-map','/approval-delegations'] as $u)
    t_ok(in_array($u, $urls, true), "it links to the existing screen $u");

//  Each one says what it governs, in business words. A tile called "Approvals"
//  with no sentence under it is how somebody opens the wrong one.
foreach ($areas as $a) {
    t_ok(strlen(trim((string)$a['what'])) > 30, $a['label'] . ' says what it governs');
    t_ok(array_key_exists('can', $a), $a['label'] . ' carries its own may-open answer');
}

// ---- THE SAFETY PROPERTY -------------------------------------------------
//  apsetup_open() is a FILTER of apsetup_areas(), never a superset: it can only
//  ever return areas whose own `can` is true.
$open = apsetup_open();
t_ok(count($open) <= count($areas), 'what is offered is never more than what exists');
foreach ($open as $o)
    t_ok(!empty($o['can']), $o['label'] . ' is offered only because this viewer may open it');
foreach ($areas as $a)
    if (empty($a['can']))
        t_ok(!in_array($a['key'], array_map(fn($x) => $x['key'], $open), true),
            $a['label'] . ' is NOT offered when this viewer may not open it');

//  The strip is built from the same filtered list, so it cannot advertise more
//  than the cards do.
$strip = apsetup_strip('recruitment');
foreach ($areas as $a) {
    if (!empty($a['can'])) continue;
    t_ok(strpos($strip, $a['url']) === false,
        'the tab strip does not offer ' . $a['label'] . ', which this viewer may not open');
}

//  A single destination needs no tab strip — one tab is furniture, not navigation.
t_ok(count($open) >= 2 ? $strip !== '' : $strip === '',
    'the strip appears only when there is more than one place to go');

// ---- the workbench adds no permission of its own -------------------------
$lib = file_get_contents(__DIR__ . '/../lib/approvals_setup.php');
t_ok(strpos($lib, 'ops_require') === false,
    'the workbench itself refuses nobody — each destination keeps its own gate');
t_ok(strpos($lib, "can('idems.type.manage')") !== false
     && strpos($lib, 'hiring_admin_can()') !== false,
    'and it asks the SAME questions the menu tiles asked, rather than inventing a rule');

// ---- it is not confused with the INBOX ----------------------------------
//  /approvals is the inbox (R-27, lib/approvals_hub.php): "what is waiting on
//  me?". /approvals-setup is where the rules are set. They were nearly merged
//  by accident while building this, which would have replaced the inbox with a
//  settings page.
t_ok(file_exists(__DIR__ . '/../lib/approvals_hub.php'), 'the approvals INBOX still exists');
$hub = file_get_contents(__DIR__ . '/../lib/approvals_hub.php');
t_ok(strpos($hub, 'approvals_hub_sections') !== false,
    'and still carries its own sections — the inbox was not overwritten');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "\$route === 'approvals' || \$route === 'approval-act'") !== false,
    '/approvals still routes to the inbox, not to the settings workbench');
t_ok(strpos($ops, "\$route === 'approvals-setup'") !== false,
    'and the workbench has its own route');

// ---- one way in from Admin, not four ------------------------------------
$areasPhp = file_get_contents(__DIR__ . '/../lib/areas.php');
t_ok(strpos($areasPhp, "'/approvals-setup'") !== false,
    'Admin offers the workbench');
t_ok(strpos($areasPhp, "'Approver mapping', '/approver-map'") === false,
    'and no longer carries a separate Approver mapping tile');
t_ok(strpos($areasPhp, "'Quote & report approvals', '/approval-rules'") === false,
    'nor a separate Quote & report approvals tile');
t_ok(strpos($areasPhp, 'approvals-setup') !== false,
    'and the workbench is an Admin route, so its trail and back control work');

// ---- every approval screen carries the strip ----------------------------
foreach ([
    'views/ops/approval_rules.php'           => 'recruitment',
    'views/ops/crm/approval_rule_list.php'   => 'quotes',
    'views/ops/idems/approval_rules.php'     => 'quotes',
    'views/ops/idems/approver_map.php'       => 'approvermap',
    'views/ops/approval_delegations.php'     => 'delegation',
] as $view => $key) {
    $v = file_get_contents(__DIR__ . '/../' . $view);
    t_ok(strpos($v, 'apsetup_strip') !== false, basename($view) . ' carries the shared tab strip');
    t_ok(strpos($v, "apsetup_strip('" . $key . "')") !== false,
        basename($view) . " marks '$key' as the open tab");
}
