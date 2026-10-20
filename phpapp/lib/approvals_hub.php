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
    //  Quotations at the level waiting on this person — the approval chain the
    //  owner's organisation runs on (Branch Manager → SBU Head → Business
    //  Director → MD). They were only findable inside Sales.
    if (function_exists('crm_quote_ids_awaiting_me') && (!function_exists('licence_module_live') || licence_module_live('quotes'))) {
        $items = [];
        foreach (crm_quote_ids_awaiting_me() as $qid) if ($q = crm_quote_get((int)$qid)) $items[] = $q;
        if ($items) $out[] = [
            'key'   => 'quotes',
            'title' => 'Quotations',
            'sub'   => 'Quotations waiting at your approval level.',
            'act'   => '/quotes',
            'items' => $items,
        ];
    }
    //  Project costings at the level waiting on this person — the same levels
    //  as a quotation of that value (owner, 20 Oct 2026).
    if (function_exists('pc_waiting_on_me')) {
        $items = pc_waiting_on_me();
        if ($items) $out[] = [
            'key'   => 'costings',
            'title' => 'Project costings',
            'sub'   => 'Costings waiting at your approval level. A man-month quotation is made only from an approved costing.',
            'act'   => '/project-costings',
            'items' => $items,
        ];
    }
    //  Lead reassignments waiting for a manager (owner, 20 Oct 2026).
    if (function_exists('lead_reassign_waiting_on_me')) {
        $items = lead_reassign_waiting_on_me();
        if ($items) $out[] = [
            'key'   => 'lead_reassign',
            'title' => 'Lead reassignments',
            'sub'   => 'Someone asked to give a lead to another person. It changes hands only when a manager approves.',
            'act'   => '',   // decided right here; the approving manager may not open the lead register
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
    if (approvals_hub_recruit_on()) return true;
    //  Someone with a quotation or a lead reassignment waiting on them may open
    //  it too — a Branch Manager approving quotations holds no stage-gate right.
    if (function_exists('crm_quote_ids_awaiting_me') && crm_quote_ids_awaiting_me()) return true;
    if (function_exists('pc_waiting_on_me') && pc_waiting_on_me()) return true;
    return function_exists('lead_reassign_waiting_on_me') && (bool) lead_reassign_waiting_on_me();
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
    if ($sectionKey === 'quotes') {
        return [
            'what'  => (function_exists('quote_label') ? quote_label($r) : (string)($r['quote_no'] ?? 'Quotation')) . ' — ' . (string)($r['client_name'] ?? ''),
            'kind'  => function_exists('fmoney') ? fmoney((float)($r['subtotal'] ?? 0)) . ' before tax' : '',
            'who'   => (string)($r['created_by'] ?? ''),
            'when'  => (string)substr((string)($r['submitted_at'] ?? ''), 0, 10),
            'rule'  => '',
            'url'   => '/quote?id=' . (int)$r['id'],
        ];
    }
    if ($sectionKey === 'costings') {
        return [
            'what'  => (string)($r['code'] ?? '') . ' — ' . (string)($r['title'] ?? ''),
            'kind'  => function_exists('fmoney') ? fmoney((float)($r['exp_revenue'] ?? 0)) . ' before tax' : '',
            'who'   => (string)($r['created_by'] ?? ''),
            'when'  => (string)substr((string)($r['updated_at'] ?? ''), 0, 10),
            'rule'  => '',
            'url'   => '/project-costing?id=' . (int)$r['id'],
        ];
    }
    if ($sectionKey === 'lead_reassign') {
        $to = ops_one("SELECT first_name, last_name, username FROM users WHERE id=?", [(int)($r['to_user_id'] ?? 0)]);
        return [
            'what'  => (string)($r['ref'] ?? '') . ' — ' . (string)($r['company_name'] ?? ''),
            'kind'  => 'From ' . ((string)($r['owner_name'] ?? '') ?: 'its owner') . ' to ' . ($to ? (trim(($to['first_name'] ?? '') . ' ' . ($to['last_name'] ?? '')) ?: $to['username']) : '—'),
            'who'   => (string)($r['requested_by'] ?? ''),
            'when'  => (string)substr((string)($r['created_at'] ?? ''), 0, 10),
            'rule'  => (string)($r['reason'] ?? ''),
            'url'   => '',
            'req'   => (int)($r['id'] ?? 0),
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
