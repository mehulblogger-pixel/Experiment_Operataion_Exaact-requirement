<?php
// ---------------------------------------------------------------------------
//  THE APPROVALS SETUP WORKBENCH  —  UAT 1.7.3
//
//  NOT to be confused with lib/approvals_hub.php, which is the INBOX (R-27):
//  "what is waiting on me?" at /approvals. This file is the other half of the
//  question — "where are the rules set?" — at /approvals-setup. One is a queue
//  of work, the other is configuration; keeping them apart is deliberate, and
//  each screen points at the other so nobody hunts for the wrong one.
//
//  The owner, after finding four approval CONFIGURATION screens in three areas:
//
//    "Home › Admin › Reporting › Reports (IDEMS) › Approver map. For this
//     approval chain it shows a different breadcrumb ... sometimes it takes you
//     to this module and that module ... Let us make the approval chain
//     applicable to each of its own module in their own module screens under
//     setup as it is in recruitment."
//
//  They were right about the problem:
//
//     Quote & report approvals   /approval-rules          Admin
//     Recruitment approvals      /recruit-approvals       Recruitment
//     Approval delegation        /approval-delegations    Recruitment
//     Approver mapping           /approver-map            Reporting › Reports
//
//  WHY ONE WORKBENCH RATHER THAN FOUR PER-MODULE SCREENS, which is what was
//  first proposed. Approval policy is a company-wide question wearing a
//  per-module costume: self-approval, delegation and escalation are the same
//  rules everywhere, and splitting them four ways is how they drift apart —
//  which is how four screens in three areas happened in the first place. "Who
//  approves what, anywhere?" would then have no answer short of visiting every
//  module. The established products settled the same way: Salesforce defines an
//  approval process per object but from one place (Setup → Process Automation,
//  choosing the object); Zoho CRM targets one module per process, all under
//  Setup → Process Management.
//
//  AND IT CHANGES NOBODY'S ACCESS. Each destination keeps its own handler and
//  its own gate, untouched. This file only decides what to OFFER, asking
//  exactly the questions the menu tiles already asked — so a recruitment
//  administrator who may configure hiring approvals but not quote approvals is
//  shown the one and not the other. Offering a tab that then refuses would be
//  the same defect in a new place.
// ---------------------------------------------------------------------------

/**
 *  Every approval configuration area, with whether THIS viewer may open it.
 *
 *  The predicates mirror the menu tiles one for one (lib/areas.php for the two
 *  reporting ones, lib/navindex.php for the two recruitment ones). Duplicated
 *  deliberately rather than inferred: a wrong guess here either hides a screen
 *  somebody needs or advertises one they cannot open.
 */
function apsetup_areas() {
    $fx = fn($f) => function_exists($f);
    $lic = fn($m) => !$fx('licence_enabled') || licence_enabled($m);
    $can = fn($p) => $fx('can') && can($p);
    $master = fn() => $fx('is_master') && is_master();

    $reporting   = $lic('reporting');
    $quoteReport = $reporting && ($can('idems.type.manage') || $master());
    $approverMap = $reporting && ($can('idems.type.manage') || $master() || $can('users.manage.global'));
    $hiringAdmin = $fx('hiring_admin_can') ? hiring_admin_can() : ($fx('is_admin_level') && is_admin_level());
    $hiring      = $hiringAdmin && (!$fx('licence_blocks') || !licence_blocks('mod.hiring.view'));

    return [
        [
            'key'   => 'recruitment',
            'label' => 'Recruitment',
            'url'   => '/recruit-approvals',
            'icon'  => '🧷',
            'what'  => 'Who approves hiring requests, offers and salaries — the matrix, SLA days, '
                     . 'reminders, escalation, and the Review Required triggers.',
            'can'   => (bool) $hiring,
        ],
        [
            'key'   => 'quotes',
            'label' => 'Quotes & reports',
            'url'   => '/approval-rules',
            'icon'  => '🔀',
            'what'  => 'Who signs off quotations and inspection reports before they leave the building.',
            'can'   => (bool) $quoteReport,
        ],
        [
            'key'   => 'approvermap',
            'label' => 'Report approver map',
            'url'   => '/approver-map',
            'icon'  => '👤',
            'what'  => 'Which person signs which kind of inspection report.',
            'can'   => (bool) $approverMap,
        ],
        [
            'key'   => 'delegation',
            'label' => 'Delegation',
            'url'   => '/approval-delegations',
            'icon'  => '🤝',
            'what'  => 'Who may approve on somebody else\'s behalf while they are away, and until when. '
                     . 'Company-wide on purpose: cover for somebody on leave applies to everything '
                     . 'they approve, not one module at a time.',
            'can'   => (bool) $hiring,
        ],
    ];
}

/** Only the areas this viewer may actually open. */
function apsetup_open() {
    return array_values(array_filter(apsetup_areas(), fn($a) => !empty($a['can'])));
}

/**
 *  The tab strip, drawn at the top of every approval configuration screen so the
 *  four read as one workbench rather than four destinations in three areas.
 *  Returns '' when the viewer may open fewer than two — a single tab is furniture.
 */
function apsetup_strip($currentKey = '') {
    $open = apsetup_open();
    if (count($open) < 2) return '';
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $out = '<nav class="appr-tabs" aria-label="Approval settings">'
         . '<a class="appr-tab' . ($currentKey === '' ? ' on' : '') . '" href="/approvals-setup">All approvals</a>';
    foreach ($open as $a) {
        $on = $a['key'] === $currentKey;
        $out .= '<a class="appr-tab' . ($on ? ' on' : '') . '" href="' . $e($a['url']) . '"'
              . ($on ? ' aria-current="page"' : '') . '>' . $e($a['icon']) . ' ' . $e($a['label']) . '</a>';
    }
    return $out . '</nav>';
}

/**
 *  The workbench: one screen naming every approval area this person may
 *  configure, so "who approves what, anywhere?" has a single answer.
 *  Adds no permission of its own.
 */
function ops_approvals_setup($route, $method) {
    view('ops/approvals_setup', [
        'areas' => apsetup_areas(),
        'open'  => apsetup_open(),
        'strip' => apsetup_strip(''),
    ]);
    return true;
}
