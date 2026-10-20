<?php
// ============================================================================
//  Setup in plain questions.
//
//  The owner's rule, 2026-10-20: "every module shall have simple questions and
//  answers for the user to set up all these things very easily."
//
//  One engine; each module registers its own set of questions. Every question is
//  bound to a setting the module's code ALREADY reads — answering it IS
//  configuring the module, so there is no second copy of the configuration to
//  drift from the first. A question never grants a permission: who may open a
//  set is the same authority that may change that module's settings today.
//
//  A set is: title, intro, who may answer (callable), the route it lives on, and
//  sections. A section holds questions and, optionally, plain "facts" — things
//  already true for this company, said in a sentence, with a link to where they
//  are changed when they are changed elsewhere.
// ============================================================================

function setupqa_sets() {
    return ['sales' => setupqa_sales_set()];
}

// The answer currently in force: the stored setting when it is one of the
// choices, otherwise the question's default (which is what the code does today).
function setupqa_value(array $q) {
    $v = function_exists('setting_get') ? (string) setting_get($q['key'], '') : '';
    return array_key_exists($v, $q['options']) ? $v : (string) $q['default'];
}

// Save one set's answers. Only a value that is one of the offered choices is
// written; anything else is ignored rather than stored. Returns the questions
// whose answer changed, in the words the person read.
function setupqa_save(array $set, array $post) {
    $changed = [];
    foreach ($set['sections'] as $sec) {
        foreach ($sec['questions'] ?? [] as $q) {
            if (!array_key_exists($q['key'], $post)) continue;
            $v = (string) $post[$q['key']];
            if (!array_key_exists($v, $q['options'])) continue;
            if ($v === setupqa_value($q)) continue;
            setting_set($q['key'], $v);
            $changed[] = $q['ask'] . ' → ' . $q['options'][$v];
        }
    }
    if ($changed && function_exists('act_log'))
        act_log('SETTINGS', 0, 'SETUP', ucfirst($set['title']) . ' answered: ' . implode('; ', $changed), []);
    return $changed;
}

function ops_setup_questions($setKey, $method) {
    $sets = setupqa_sets();
    $set = $sets[$setKey] ?? null;
    if (!$set) { http_response_code(404); view('notfound'); return true; }
    ops_require(($set['can'])(), 'Only someone who may change the ' . $set['title'] . ' settings can answer these questions.');
    if ($method === 'POST') {
        $changed = setupqa_save($set, $_POST);
        flash($changed ? 'Saved. ' . count($changed) . ' answer' . (count($changed) === 1 ? '' : 's') . ' changed — it applies from now on.'
                       : 'Nothing changed — your answers were already these.');
        redirect('/' . $set['route']);
    }
    view('ops/setup_questions', ['set' => $set]);
    return true;
}

// ---- Sales -------------------------------------------------------------------
function setupqa_sales_set() {
    $L = function_exists('Tl') ? Tl('quote') : 'quotation';
    return [
        'title' => 'Sales',
        'route' => 'sales-setup',
        'home'  => '/sales',
        'intro' => 'A few questions decide how ' . (function_exists('Tlp') ? Tlp('quote') : 'quotations')
                 . ' are approved and reviewed in your company. Answer them once; change them whenever the company changes.',
        'can'   => fn() => is_master() || can('settings.manage') || can('crm.quote.approve'),
        'sections' => [
            [
                'title' => 'Approving a ' . $L,
                'facts' => setupqa_sales_level_facts(),
                'questions' => [
                    [
                        'key' => 'quote_unmatched_approval',
                        'ask' => 'When a ' . $L . "'s value is not covered by any of your approval levels, what should happen?",
                        'why' => 'For example, a value above your highest level, or between two levels, or when you have no levels at all. A one-person company usually needs no approval.',
                        'options' => function_exists('crm_quote_unmatched_policy') ? QUOTE_UNMATCHED_POLICIES : ['any' => 'Anyone who may approve'],
                        'default' => 'any',
                    ],
                    [
                        'key' => 'quote_self_approval',
                        'ask' => 'May the person who prepared a ' . $L . ' approve it themselves, when that value is within their own approval authority?',
                        'why' => 'For example, a Branch Manager who prepares a ' . $L . ' instead of a Sales Executive. Someone below that level can never approve it either way.',
                        'options' => ['yes' => 'Yes — when it is within their own authority', 'no' => 'No — someone else must always approve it'],
                        'default' => 'yes',
                    ],
                ],
                'link' => ['/quote-approval-rules', 'Change who approves, and up to what value'],
            ],
            [
                'title' => 'Reviewing the request before it is approved (contract review)',
                'facts' => [],
                'questions' => [
                    [
                        'key' => 'preorder_checklist_on',
                        'ask' => 'Before a ' . $L . ' is approved, should the team go through your review checklist?',
                        'why' => 'Inspection and testing bodies are expected to review each request, tender and contract before accepting it (ISO/IEC 17020 / 17025).',
                        'options' => ['1' => 'Yes — show the checklist on every ' . $L, '0' => 'No'],
                        'default' => '0',
                    ],
                    [
                        'key' => 'preorder_checklist_require',
                        'ask' => 'If the checklist is shown, must every point be ticked before the ' . $L . ' can be approved?',
                        'why' => 'A reminder lets the approver go ahead; a requirement stops approval until every point is confirmed.',
                        'options' => ['1' => 'Yes — every point must be ticked first', '0' => 'No — it is a reminder only'],
                        'default' => '0',
                    ],
                ],
                'link' => ['/preorder-checklist', 'Change the checklist points'],
            ],
            [
                'title' => 'Already set by your company\'s rules',
                'facts' => [
                    'Approval values are measured on the ' . $L . ' before tax.',
                    'Any change to the commercial terms or the terms & conditions cancels an approval — the ' . $L . ' goes back to draft and is approved again.',
                    'The person assigned to a ' . $L . ' records the customer\'s answer — accepted or lost.',
                    'A customer who is blacklisted, inactive, on hold or blocked gets nothing new — only payment reminders — until someone clears it.',
                ],
                'questions' => [],
                'link' => null,
            ],
        ],
    ];
}

// The approval levels as they stand, in sentences, and what happens to a value
// none of them covers — the coverage check the rule screen never showed.
function setupqa_sales_level_facts() {
    if (!function_exists('ops_all')) return [];
    $rows = ops_all("SELECT r.*, u.first_name, u.last_name, u.username FROM quote_approval_rules r
                     LEFT JOIN users u ON u.id = r.approver_user_id WHERE r.active=1 ORDER BY r.level, r.min_amount, r.id") ?: [];
    $money = fn($n) => function_exists('fmoney') ? fmoney($n) : number_format((float) $n);
    $who = function ($r) {
        if (!empty($r['approver_user_id'])) return trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: (string) $r['username'];
        $role = (string) ($r['approver_role'] ?? '');
        if ($role === 'REPORTS_TO') return 'the preparer\'s reporting manager';
        return $role !== '' ? (ORG_ROLES[$role] ?? $role) : 'anyone who may approve';
    };
    if (!$rows) return ['You have no approval levels yet, so every ' . (function_exists('Tl') ? Tl('quote') : 'quotation') . ' is treated as "not covered" — the first question below decides what happens.'];
    $out = []; $top = 0.0; $open = false;
    foreach ($rows as $r) {
        $min = (float) $r['min_amount']; $max = (float) $r['max_amount'];
        $range = $max > 0 ? ('from ' . $money($min) . ' to ' . $money($max)) : ('from ' . $money($min) . ' upward');
        $out[] = 'Level ' . (int) $r['level'] . ' — ' . $range . ' before tax'
               . (($r['match_type'] ?? '') === 'Business Unit' && ($r['sbu'] ?? '') !== '' ? ' (' . $r['sbu'] . ' only)' : '')
               . ': ' . $who($r) . '.';
        if ($max <= 0) $open = true; else $top = max($top, $max);
    }
    if (!$open) $out[] = 'Nothing above ' . $money($top) . ' is covered by a level — the first question below decides what happens to it.';
    return $out;
}
