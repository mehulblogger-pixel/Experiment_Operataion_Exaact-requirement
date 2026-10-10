<?php
// ============================================================================
//  ONE ANSWER TO "WHAT NEEDS ME?"                                      (R-27)
// ============================================================================
//  There were two inboxes. A manager who signs things off had to know which
//  KIND of thing they were approving before they knew where to look:
//
//      /approvals     stage gates — deals held until somebody agrees
//      /my-approvals  recruitment chains — hiring requests, offers, salaries
//
//  Twelve routes across four subsystems and ten files (the structure audit,
//  §4.1). Two inboxes for one human question is not a design; it is two
//  features built at different times and never introduced.
//
//  This does NOT merge the two systems. They are genuinely different — they
//  have different records, different rules and, importantly, different
//  AUDIENCES. Recruitment chains gate on the LICENCE rather than on a
//  permission, deliberately, so that a Finance approver can sit on an offer
//  chain without holding any recruitment module (see ops_my_approvals). Forcing
//  one permission model on both would quietly remove approvers.
//
//  What it does is give both one front door. /approvals now answers the whole
//  question; each row still acts where it has always acted.
// ============================================================================

//  Can this person see the recruitment chain inbox at all? The same question
//  ops_my_approvals() asks, in one place so the two cannot drift: entitlement
//  is the workspace's contract, capability is the person's role.
function approvals_hub_recruit_on() {
    if (!function_exists('appr_inbox')) return false;
    if (function_exists('licence_blocks') && licence_blocks('mod.hiring.view')) return false;
    return function_exists('current_user') && current_user();
}

//  Every queue waiting on this person, newest concern first. A section appears
//  only when the viewer may see that queue AND it has something in it — an
//  empty section on a screen that exists to say "here is what needs you" is
//  noise, and it is what made a Finance user wonder why they had a recruitment
//  inbox at all (Journey H6).
function approvals_hub_sections() {
    $out = [];
    if (approvals_hub_recruit_on()) {
        $items = appr_inbox();
        if ($items) $out[] = [
            'key'   => 'recruit',
            'title' => 'Recruitment',
            'sub'   => 'Hiring requests, requisitions, offers and salary structures waiting on your decision.',
            'act'   => '/my-approvals',
            'items' => $items,
        ];
    }
    if (function_exists('gate_can_view') && gate_can_view() && function_exists('gate_queue')) {
        $q = gate_queue('PENDING');
        if (!empty($q['act'])) $out[] = [
            'key'   => 'gates',
            'title' => 'Deals held at a stage',
            'sub'   => 'Deals that cannot move on until somebody with the authority agrees.',
            'act'   => '/approvals',
            'items' => $q['act'],
        ];
    }
    return $out;
}

//  How many things are waiting on this person, across everything. For a badge.
function approvals_hub_count() {
    $n = 0;
    foreach (approvals_hub_sections() as $s) $n += count($s['items']);
    return $n;
}

//  May this person open the single inbox at all? Either queue is enough —
//  requiring gate_can_view() alone (as /approvals did) refused every
//  recruitment approver who had no stage-gate rights, which is most of them.
function approvals_hub_can_view() {
    if (function_exists('gate_can_view') && gate_can_view()) return true;
    return approvals_hub_recruit_on();
}

//  One row, however it arrived, so the screen can render both the same way.
//  Recruitment steps and stage gates share no column names at all.
function approvals_hub_row($sectionKey, array $r) {
    if ($sectionKey === 'recruit') {
        $what = trim((string)($r['subject'] ?? '')) ?: ucfirst(strtolower((string)($r['entity'] ?? 'Request')));
        return [
            'what'  => $what,
            'kind'  => ucfirst(strtolower(str_replace('_', ' ', (string)($r['entity'] ?? '')))),
            'who'   => (string)($r['requester'] ?? ''),
            'when'  => (string)($r['sla_due'] ?? ''),
            'rule'  => (string)($r['rule_name'] ?? ''),
            'url'   => '/my-approvals',
        ];
    }
    return [
        'what'  => (string)($r['deal_name'] ?? 'Deal'),
        'kind'  => trim((string)($r['from_name'] ?? '') . ' → ' . (string)($r['to_name'] ?? ''), ' →'),
        'who'   => (string)($r['partner_name'] ?? ''),
        'when'  => (string)($r['requested_at'] ?? ''),
        'rule'  => '',
        'url'   => '/approvals',
    ];
}
