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
        //  A section may be narrower than the set it sits in (the contract rule is a
        //  company setting, not something every quotation approver may rewrite).
        if (isset($sec['can']) && !($sec['can'])()) continue;
        if (!empty($sec['save'])) foreach (($sec['save'])($post) as $line) $changed[] = $line;
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

// The tabs of Admin → Approvals → Sales (owner, 13 Nov 2026: "one sub tab under
// approval as Sales Approval, when clicked it opens sub tabs like quote approval,
// contract approval, costing approval and so on"). Each tab is the SAME sections
// Set up Sales shows, one subject at a time — one copy of every rule, two ways in.
const SALES_APPROVAL_TABS = ['quote' => 'Quotation', 'costing' => 'Project costing', 'contract' => 'Contract', 'deals' => 'Deal stages', 'leads' => 'Leads'];
function ops_sales_approvals($method) {
    $set = setupqa_sales_set();
    ops_require(($set['can'])(), 'Only someone who may change the Sales settings can open the Sales approvals.');
    $tab = (string)($_GET['tab'] ?? $_POST['tab'] ?? 'quote'); if (!isset(SALES_APPROVAL_TABS[$tab])) $tab = 'quote';
    $tabs = SALES_APPROVAL_TABS;
    if (function_exists('Tl')) $tabs['quote'] = ucfirst(Tl('quote'));
    $set['sections'] = array_values(array_filter($set['sections'], fn($sec) => ($sec['tab'] ?? '') === $tab));
    if ($method === 'POST') {
        $changed = setupqa_save($set, $_POST);
        flash($changed ? 'Saved — ' . implode('; ', $changed) . '. It applies from now on.' : 'Nothing changed — the rule was already this.');
        redirect('/sales-approvals?tab=' . $tab);
    }
    //  Back means UP: an administrator came through Admin → Approvals; a quotation
    //  approver (who has no Admin area) came through Sales → Set up Sales.
    $viaAdmin = false;
    if (function_exists('apsetup_open')) foreach (apsetup_open() as $a) if (($a['key'] ?? '') === 'sales') $viaAdmin = true;
    if (function_exists('nav_under')) {
        if ($viaAdmin) nav_under('sales-approvals', 'admin', ['label' => 'Approvals', 'url' => '/approvals-setup']);
        else nav_under('sales-approvals', 'sales', ['label' => 'Set up Sales', 'url' => '/sales-setup']);
    }
    view('ops/sales_approvals', ['set' => $set, 'tab' => $tab, 'tabs' => $tabs, 'viaAdmin' => $viaAdmin,
        'strip' => ($viaAdmin && function_exists('apsetup_strip')) ? apsetup_strip('sales') : '']);
    return true;
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
    $O = setupqa_sales_other_approvals_by_tab();
    return [
        'title' => 'Sales',
        'route' => 'sales-setup',
        'home'  => '/sales',
        'intro' => 'A few questions decide how ' . (function_exists('Tlp') ? Tlp('quote') : 'quotations')
                 . ' are approved and reviewed in your company. Answer them once; change them whenever the company changes.',
        'can'   => fn() => is_master() || can('settings.manage') || can('crm.quote.approve'),
        'sections' => [
            [
                'tab' => 'quote',
                'title' => 'Approving a ' . $L,
                'facts' => setupqa_sales_level_facts(),
                'questions' => [
                    [
                        'key' => 'quote_unmatched_approval',
                        'ask' => 'When a ' . $L . "'s value is not covered by any of your approval levels, what should happen?",
                        'why' => 'For example, a value above your highest level, or between two levels. With no levels at all, no approval is needed (your rule, 21 Oct 2026). A one-person company usually needs no approval.',
                        'options' => function_exists('crm_quote_unmatched_policy') ? QUOTE_UNMATCHED_POLICIES : ['any' => 'Anyone who may approve'],
                        'default' => 'top',
                    ],
                    [
                        'key' => 'quote_self_approval',
                        'ask' => 'May the person who prepared a ' . $L . ' approve it themselves, when that value is within their own approval authority?',
                        'why' => 'For example, a Branch Manager who prepares a ' . $L . ' instead of a Sales Executive. Someone below that level can never approve it either way.',
                        'options' => ['yes' => 'Yes — when it is within their own authority', 'no' => 'No — someone else must always approve it'],
                        'default' => 'yes',
                    ],
                    [
                        //  Owner, 21 Oct 2026: "setting in sales setup wherein
                        //  simultaneous approval are required." Applies to
                        //  quotations and project costings alike.
                        'key' => 'quote_approval_mode',
                        'ask' => 'When a ' . $L . ' needs more than one level, how do the levels approve?',
                        'why' => 'One after another: level 1 first, and level 2 is asked only when level 1 has signed. At the same time: every level is asked at once, and it is approved when all have signed. Either way, top management (a Business Director level — or, without one, the highest level) signs LAST, once every other level has; and nobody signs before the people in the chain who report to them. Project costings follow the same answer.',
                        'options' => ['order' => 'One after another — level 1 first', 'together' => 'At the same time — every level but the top one at once; the top one last'],
                        'default' => 'order',
                    ],
                    [
                        'key' => 'quote_approval_escalate_days',
                        'ask' => 'If a ' . $L . ' waits too long at one approval level, when should the level above be able to approve it too?',
                        'why' => 'The person it is waiting for can still approve it; the level above is told and can step in. Checked every night. (One after another only — at the same time, the levels are all asked already.)',
                        'options' => ['0' => 'Never — it waits for its own approver', '1' => 'After 1 day', '2' => 'After 2 days', '3' => 'After 3 days', '7' => 'After a week'],
                        'default' => '0',
                    ],
                ],
                //  The levels themselves are edited right here (owner, SAL-002:
                //  "in Sales setup we must have option to set the applicable rules
                //  from that screen directly"), not on a screen two clicks away.
                'editor' => 'quote_levels',
                'link2' => ['/quote-delegations', 'Set who covers for an approver who is away'],
            ],
            [
                'tab' => 'costing',
                'title' => 'Approving a project costing',
                'facts' => [$O['costing'], 'The levels are the ' . $L . ' levels — change them on the ' . ucfirst($L) . ' tab; there is no second list to keep in step.'],
                'questions' => [
                    [
                        'key' => 'quote_manmonth_costing',
                        'ask' => 'Must a man-month ' . $L . ' come from an approved project costing before it is submitted, approved or sent?',
                        'why' => 'A man-month price carries salaries, allowances and overheads for months; the costing shows they are covered. Whoever made the ' . $L . ' can still change its prices afterwards, with a note saying why.',
                        'options' => ['yes' => 'Yes — an approved costing first', 'no' => 'No — the costing is optional'],
                        'default' => 'yes',
                    ],
                ],
                'link' => null,
            ],
            [
                //  Owner, 13 Nov 2026: "Hope these approvals are not hard coded … All
                //  approvals shall be set by the individual organisation."
                'tab' => 'contract',
                'title' => 'Opening a contract',
                'can' => fn() => function_exists('contract_rule_editable') && contract_rule_editable(),
                'save' => fn($post) => function_exists('contract_rule_save') ? contract_rule_save($post) : [],
                'facts' => setupqa_sales_contract_facts(),
                'questions' => [
                    [
                        'key' => 'contract_open_signatures',
                        'ask' => 'How many approvals does a new contract need before it opens?',
                        'why' => 'Until a contract is open, no work can be raised against it. A later change to an open contract (value, dates, quantities, billing terms) follows the same answer.',
                        'options' => ['2' => 'Two — a first signature, then a final approval', '1' => 'One — a single approval', '0' => 'None — it opens as soon as it is registered'],
                        'default' => '2',
                    ],
                    [
                        'key' => 'contract_same_person',
                        'ask' => 'With two approvals, may one person give both?',
                        'why' => 'Two different people is the safer answer. A very small company, where one person holds both jobs, may need "yes".',
                        'options' => ['no' => 'No — two different people', 'yes' => 'Yes — one person may give both'],
                        'default' => 'no',
                        'only_when' => ['contract_open_signatures', '2'],
                    ],
                ],
                'editor' => 'contract_signers',
                'link' => null,
            ],
            [
                'tab' => 'deals',
                'title' => 'Approving a deal as it moves stage',
                'facts' => array_merge(setupqa_sales_gate_facts(), [$O['deals']]),
                'questions' => [],
                'link' => ['/stage-gates', 'Add or change the rules for deal stages'],
            ],
            [
                'tab' => 'leads',
                'title' => 'Leads — marking lost, giving to someone else, reopening',
                'facts' => [$O['leads']],
                'questions' => [],
                'link' => null,
            ],
            [
                'tab' => 'quote',
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
                        'ask' => 'If the checklist is shown, must every point be ticked before the ' . $L . ' can be approved, sent or accepted?',
                        'why' => 'A reminder lets people go ahead; a requirement holds approval, sending and the customer\'s acceptance until every point is confirmed. A point that cannot be confirmed can be waived, with a reason, by someone who may approve — the reason and their name show on the ' . $L . '.',
                        'options' => ['1' => 'Yes — every point must be ticked (or waived) first', '0' => 'No — it is a reminder only'],
                        'default' => '0',
                    ],
                ],
                'link' => ['/preorder-checklist', 'Change the checklist points'],
            ],
            [
                'tab' => 'quote',
                'title' => 'Already set by your company\'s rules',
                'facts' => [
                    'Approval values are measured on the ' . $L . ' before tax.',
                    'Any change to the commercial terms or the terms & conditions cancels an approval — the ' . $L . ' goes back to draft and is approved again.',
                    $O['quote'],
                    'The person assigned to a ' . $L . ' records the customer\'s answer — accepted or lost.',
                    'A customer who is blacklisted, inactive, on hold or blocked gets nothing new — only payment reminders — until someone clears it.',
                ],
                'questions' => [],
                'link' => null,
            ],
        ],
    ];
}

// Deal-stage rules in sentences (lib/stagegate.php explains each one).
function setupqa_sales_gate_facts() {
    if (!function_exists('gate_rules')) return [];
    $rows = array_values(array_filter(gate_rules(), fn($r) => (int)($r['active'] ?? 1) === 1));
    if (!$rows) return ['There are no rules for deal stages, so a deal (opportunity) moves to any stage — won, lost or open — without anyone having to agree first.'];
    return array_map('gate_explain', $rows);
}

// Every other approval in Sales, with who gives it. Leads, deals and project
// costing follow the company's decided rules (docs/02-permission-matrix.md 19e,
// 19f; D-11 B) and have no setting, so they are stated rather than asked. Opening
// a contract IS a setting (owner, 13 Nov 2026) — its sentence is the rule in force.
function setupqa_sales_other_approvals_by_tab() {
    $q = function_exists('Tl') ? Tl('quote') : 'quotation';
    return [
        'leads' => 'Leads: only the person responsible marks a lead lost, with a reason and remarks. Giving a lead that already has an owner to someone else waits for a manager\'s approval — the owner\'s reporting manager, or a sales manager over that branch. A manager may reopen a lost lead, with a reason that is kept.',
        'deals' => 'Deals (opportunities): only the stage rules above. A manager may reopen a closed deal, with a reason that is kept — unless it has already become an order or contract.',
        'costing' => 'Project costing (man-month work): tagged with its enquiry or lead, and approved through the same levels as a ' . $q . ' of that value — never by the person who prepared it.',
        'quote' => ucfirst($q) . 's: the levels above' . (function_exists('crm_approval_mode') && crm_approval_mode() === 'together' ? ', at the same time — top management last.' : ', in order — level 1 first, then level 2, and so on; top management last.')
            . ' A ' . $q . ' made from an approved project costing, at the costing\'s prices, is not approved a second time. With no levels at all, no approval is needed.',
        'contract' => 'Opening a contract: ' . (function_exists('contract_rule_sentence') ? contract_rule_sentence() : 'two signatures') . '.'
            . (function_exists('contract_rule_sentence') ? ' A later change to an open contract\'s value, dates or quantities: ' . contract_rule_sentence(true) . '.' : ''),
    ];
}
function setupqa_sales_other_approvals() { return array_values(setupqa_sales_other_approvals_by_tab()); }

// The contract rule as it stands, in sentences — with a warning when a signature
// has nobody to give it, so it is fixed here and not discovered on a won order.
function setupqa_sales_contract_facts() {
    if (!function_exists('contract_open_rule')) return [];
    $rule = contract_open_rule(); $n = (int)$rule['signatures'];
    $out = ['Today: ' . contract_rule_sentence() . '.'];
    if ($n === 0) $out[] = 'A later change to an open contract still takes one approval, from ' . contract_sig_label(2, $rule) . ' — it rewrites a contract that is already in force.';
    foreach ($n === 2 ? [1, 2] : [2] as $i)
        if (!contract_sig_people($i, null, 0, $rule))
            $out[] = '⚠ Nobody can give the ' . ($i === 1 ? 'first signature' : 'final approval') . ' yet — no active person holds it (' . contract_sig_label($i, $rule) . '). Until someone is, only the Master Admin can sign.';
    if (!$rule['sig'][1]['standard'] || !$rule['sig'][2]['standard'] || setting_get('contract_open_signatures', '') !== '') $out[] = 'This is your company\'s own rule. The standard one is: two signatures — first Operation Manager, Business Unit Head, Admin, Business Director or anyone who manages a branch\'s users, then Branch Manager or Branch Application Manager, two different people.';
    return $out;
}

// What the "who gives each signature" editor needs.
function setupqa_contract_signers_data() {
    $rule = function_exists('contract_open_rule') ? contract_open_rule() : null;
    $roles = function_exists('roles_all') ? roles_all() : ORG_ROLES; unset($roles['INSPECTOR'], $roles['MASTER_ADMIN']);
    $can = function_exists('contract_rule_editable') && contract_rule_editable();
    return ['rule' => $rule, 'roles' => $roles, 'can' => $can,
            'users' => $can ? (ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 AND COALESCE(is_superuser,0)=0 ORDER BY first_name, username") ?: []) : []];
}

// What the in-place level editor on Set up Sales needs. Editing needs the same
// right the rule screen has always asked for (ops_crm_approval_rules()).
function setupqa_quote_levels_data() {
    $can = function_exists('can') && (can('crm.quote.approve') || (function_exists('is_master') && is_master()));
    $rows = function_exists('ops_all') ? (ops_all("SELECT * FROM quote_approval_rules ORDER BY level, min_amount, id") ?: []) : [];
    //  Owner, 12 Nov 2026 — the company's own roles too (Roles & access).
    $roles = function_exists('roles_all') ? roles_all() : ORG_ROLES; unset($roles['INSPECTOR']);
    return ['can' => $can, 'rows' => $rows, 'roles' => $roles,
            'users' => $can ? (ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name, username") ?: []) : [],
            'sbuOpts' => function_exists('lk_options_or') ? lk_options_or('sbu', OPS_SBUS) : []];
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
    $units = function_exists('lk_options_or') ? lk_options_or('sbu', OPS_SBUS) : [];
    $out = []; $top = 0.0; $open = false;
    foreach ($rows as $r) {
        $min = (float) $r['min_amount']; $max = (float) $r['max_amount'];
        //  A value on a shared boundary belongs to the lower level (crm_rule_covers()),
        //  so a level that starts where another ends is "above" that value.
        $above = false;
        foreach ($rows as $o) if ((int) $o['id'] !== (int) $r['id'] && $min > 0 && (float) $o['max_amount'] == $min
                                  && crm_rule_is_unit($o) === crm_rule_is_unit($r) && (!crm_rule_is_unit($r) || $o['sbu'] === $r['sbu'])) $above = true;
        $from  = ($above ? 'above ' : 'from ') . $money($min);
        $range = $max > 0 ? ($from . ' to ' . $money($max)) : ($from . ($above ? '' : ' upward'));
        $out[] = 'Level ' . (int) $r['level'] . ' — ' . $range . ' before tax'
               . (crm_rule_is_unit($r) ? ' (' . ($units[$r['sbu']] ?? $r['sbu']) . ' only)' : '')
               . ': ' . $who($r) . '.';
        if ($max <= 0) $open = true; else $top = max($top, $max);
    }
    if (!$open) $out[] = 'Nothing above ' . $money($top) . ' is covered by a level — the first question below decides what happens to it.';
    if (count($rows) > 1)
        $out[] = 'A ' . (function_exists('Tl') ? Tl('quote') : 'quotation') . ' goes up the levels: it is signed by the level its value falls in and by every level below it, in order.';
    // Levels saved before overlaps were refused (24 Oct 2026) are named, so they can be put right.
    if (function_exists('crm_approval_band_problem'))
        foreach ($rows as $r) {
            $why = crm_approval_band_problem($r, (int) $r['id']);
            if ($why !== '') { $out[] = '⚠ Level ' . (int) $r['level'] . ' (' . ((float) $r['max_amount'] > 0 ? 'from ' . $money($r['min_amount']) . ' to ' . $money($r['max_amount']) : 'from ' . $money($r['min_amount']) . ' upward') . '): ' . $why; break; }
        }
    return array_merge($out, setupqa_sales_coverage_gaps($rows, $units));
}

// A level addressed to a role goes to the people in that role who cover the
// quotation's branch and business unit (crm_role_approvers_for()). Where nobody
// does, it falls back to everyone in the role — said here, so it can be fixed
// on the person's Data scope before a quotation meets it.
function setupqa_sales_coverage_gaps(array $rows, array $units) {
    if (!function_exists('user_scope_covers')) return [];
    $offices = function_exists('offices_list') ? (offices_list() ?: []) : [];
    $out = []; $seen = [];
    foreach ($rows as $r) {
        $role = (string) ($r['approver_role'] ?? '');
        if ($role === '' || $role === 'REPORTS_TO' || !empty($r['approver_user_id']) || isset($seen[$role])) continue;
        $seen[$role] = true;
        $people = ops_all("SELECT * FROM users WHERE role=? AND is_active=1", [$role]) ?: [];
        $name = ORG_ROLES[$role] ?? $role;
        if (!$people) { $out[] = '⚠ Nobody has the role ' . $name . ' yet, so level ' . (int) $r['level'] . ' cannot be signed by role.'; continue; }
        $missOff = [];
        foreach ($offices as $o) {
            $covered = false; foreach ($people as $u) if (user_scope_covers($u, (int) $o['id'], '')) { $covered = true; break; }
            if (!$covered) $missOff[] = $o['name'];
        }
        $missUnit = [];
        foreach ($units as $code => $label) {
            $covered = false; foreach ($people as $u) if (user_scope_covers($u, null, (string) $code)) { $covered = true; break; }
            if (!$covered) $missUnit[] = $label;
        }
        if ($missOff) $out[] = '⚠ No ' . $name . ' covers ' . implode(', ', array_slice($missOff, 0, 8)) . (count($missOff) > 8 ? ' and ' . (count($missOff) - 8) . ' more' : '')
                             . ' — a quotation from there goes to every ' . $name . '. Set it on that person\'s Data scope.';
        if ($missUnit) $out[] = '⚠ No ' . $name . ' covers the business unit ' . implode(', ', $missUnit) . ' — such a quotation goes to every ' . $name . '.';
    }
    return $out;
}
