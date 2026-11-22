<?php
// ============================================================================
//  WHO CAN DO WHAT — one screen for roles, people, branches and switches
//  (owner, 21 Nov 2026: "in permission settings a clear and detailed feature
//   then its sub features shall be able to allow or disallow. At present the
//   permission screen is also very weird and confusing." — proposal approved
//   "All as recommended"; permission foundation A built first, batch B is this.)
// ============================================================================
//
//  WHAT THIS IS — AND WHAT IT IS NOT.
//
//  A new way of LOOKING AT and SWITCHING the rights that already exist. It is
//  not a second permission system and it decides nothing:
//
//    · A role is saved by POSTING TO THE EXISTING ROLE HANDLER (/access,
//      ops_access()) with the same field names the old screen posts. The same
//      boxes in, the same stored value out — by construction, not by copying.
//    · A person's switches and branches are saved through user_access_from_post(),
//      the helper the person form (/user-edit) itself now calls, then the same
//      user_perms_to_store() (Q11 "@diff" storage) and the same audit entry.
//    · The screen shows exactly the rights the old screens showed: for a role,
//      what views/ops/access.php drew (whocan_role_keys()); for a person, what
//      the editor may hand out (assignable_permissions()). A right is never
//      hidden from one and shown on the other.
//    · ONE SWITCH = ONE RIGHT. Two rights are never merged under one switch,
//      because a merged switch has a third, half-on state that neither old
//      screen could save.
//
//  The tree (Area → Feature → Sub-feature) is grouping and wording only. Sales is
//  laid out by hand from the approved proposal; every other area is built from
//  the menu's own map (access_module_areas() / permission_nav_groups()), and any
//  right nobody placed is shown under "Other rights" — never dropped (R-14).
//
//  "What they will see" for a PERSON is worked out by signing in as them for an
//  instant (appr_as_user(), the helper approvals already use) and asking the
//  real gates — so it cannot disagree with what they will actually get.
// ============================================================================

function whocan_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }

//  Who may open which part. The same gates as the screens they replace:
//  Roles & permissions is the Master Admin's (ops_access); people are for the
//  user managers (ops_users), and a branch-level manager only for their branch.
function whocan_can_roles()  { return is_master(); }
function whocan_can_people() { return can('users.manage.branch') || can('users.manage.global'); }
function whocan_can()        { return whocan_can_roles() || whocan_can_people(); }

// ---- Which rights each tab shows --------------------------------------------

//  EXACTLY the boxes views/ops/access.php draws: the module grid of every
//  licensed module (every verb the module has) plus the licensed fine rights.
//  Saving posts to the same handler, so the same set must be drawn — a right
//  drawn on one and not the other would be dropped by one and kept by the other.
function whocan_role_keys() {
    $allPerm = all_permissions();
    $keys = [];
    $mg = function_exists('module_groups_licensed') ? module_groups_licensed() : module_groups();
    foreach ($mg as $mods) foreach ($mods as $k) {
        if (!isset(ACCESS_MODULES[$k])) continue;
        foreach (PERM_VERBS as $v) if (in_array($v, access_module_verbs($k), true)) $keys[perm_verb_key($k, $v)] = true;
    }
    $pg = function_exists('permission_groups_licensed') ? permission_groups_licensed() : permission_groups();
    foreach ($pg as $ks) foreach ($ks as $k) if (isset($allPerm[$k])) $keys[$k] = true;
    return array_keys($keys);
}

//  EXACTLY what the person form lets this administrator hand out (and what its
//  save accepts) — assignable_permissions(), shared by both.
function whocan_person_keys($globalMgr) { return array_keys(assignable_permissions((bool) $globalMgr)); }

// ---- The tree ---------------------------------------------------------------

//  The words a module verb row is given in Sales (elsewhere the verb alone).
function whocan_sales_noun($mod) {
    //  The company's own words (Admin → Terminology), as every other screen.
    return ['leads' => 'leads', 'inquiries' => Tlp('inquiry'), 'quotes' => Tlp('quote'),
            'crm_orders' => 'orders and contracts', 'crm_reports' => 'sales reports'][$mod] ?? $mod;
}

//  One row of the tree for a key: its label, help, and (for a module verb) the
//  module and verb, so the screen can tick the weaker verbs the way the grid does.
function whocan_row($key, $label = '', $help = '', $mod = '', $verb = '') {
    $all = all_permissions();
    if ($label === '') $label = $all[$key] ?? $key;
    if ($help === '' && function_exists('perm_help')) $help = (string) perm_help($key);
    return ['type' => 'key', 'key' => $key, 'label' => $label, 'help' => $help, 'mod' => $mod, 'verb' => $verb];
}
//  Q10 — the "Whose records they see" control (owner, 21 Nov 2026: "Keep this").
//  $ctx 'role': Own / Team / Branch (Branch vs All is each person's branches);
//  $ctx 'person': follow the role, or Own / Team / Branch / All ($canAll: may set
//  "All branches", which only a company-wide user manager may).
function whocan_records_ctl($ctx, $cur, $roleMode = 'branch', $canAll = false) {
    $opt = ['own' => 'Own — only the records they own', 'team' => 'Team — theirs and those of everyone who reports to them',
            'branch' => 'Branch — everything in their ' . Tlp('office')];
    if ($ctx === 'person') {
        $opt = ['' => 'As the role: ' . (SALES_REC_MODES[$roleMode] ?? 'Branch')] + $opt;
        if ($canAll) $opt['all'] = 'All — the whole company (sets ' . Tlp('office') . ' to all)';
    }
    $h = '<div class="wc-rec"><label class="sxs6-lab" for="wc-rec-' . e($ctx) . '">Whose records they see</label>'
       . '<select class="form-control" id="wc-rec-' . e($ctx) . '" name="records_mode" data-wc-rec>';
    foreach ($opt as $k => $l) $h .= '<option value="' . e($k) . '"' . ((string) $k === (string) $cur ? ' selected' : '') . '>' . e($l) . '</option>';
    return $h . '</select><small>Leads, ' . e(Tlp('opportunity')) . ', ' . e(Tlp('inquiry')) . ', ' . e(Tlp('quote'))
        . ', orders, Sales home, Today, the board and reports. Own and Team narrow inside the ' . e(Tlp('office')) . ' they see; they never add one.'
        . ($ctx === 'role' ? ' Branch or All for one person: their ' . e(Tlp('office')) . ' under People.' : '') . '</small></div>';
}
function whocan_rule($label, $how) { return ['type' => 'rule', 'label' => $label, 'help' => $how]; }
function whocan_decision($label, $how) { return ['type' => 'decision', 'label' => $label, 'help' => $how]; }

//  The rows for a module's verbs (only the verbs it has), worded for Sales when
//  a noun is given.
function whocan_module_rows($mod, $noun = '') {
    $rows = [];
    foreach (PERM_VERBS as $v) {
        if (!in_array($v, access_module_verbs($mod), true)) continue;
        $key = perm_verb_key($mod, $v);
        $named = $key !== "mod.$mod.$v";                       // a bound sign-off right
        if ($noun !== '') $label = perm_verb_label($v) . ' ' . $noun;
        else $label = $named ? perm_label($key) : perm_verb_label($v);
        $help = $named && function_exists('perm_help') ? (string) perm_help($key) : perm_verb_help($v);
        $rows[] = whocan_row($key, $label, $help, $mod, $v);
    }
    return $rows;
}

//  Sales, laid out as the approved proposal (EXAACT-Permission-Screen-Redesign-
//  Proposal.pdf, picture 5). Rows marked rule/decision carry no switch: they are
//  decided by a rule the owner set, or wait for the owner (question 10).
function whocan_sales_features() {
    $f = [];
    $f[] = ['f' => 'Sales workspace', 'd' => 'Sales home, Today and the Pipeline board', 'rows' => [
        whocan_row('crm.sales.see_all', "See other salespeople's lists", 'The "Everyone" view and "Choose a person" on Sales home, Today and the board. Without it a person sees their own list.'),
        whocan_rule('Whose records they see — own, team, branch or all', 'Set in "Whose records they see" at the top (your decision of 21 Nov, question 10).'),
    ]];
    $f[] = ['f' => 'Leads', 'd' => 'People and firms who may buy', 'rows' => array_merge(whocan_module_rows('leads', 'leads'), [
        whocan_row('crm.lead.export', 'Download leads to Excel / CSV'),
        whocan_rule('Reopen a lost lead', 'Decided by: Edit leads or Approve quotations (your rule 19f).'),
        whocan_rule('Mark a lead lost', "Decided by a rule: the lead's owner (your rule 19g)."),
        whocan_rule('Hand a lead to someone else', 'Decided by a rule: a manager approves (your rule 19g).'),
    ])];
    $f[] = ['f' => TP('opportunity'), 'd' => 'Opportunities in the pipeline', 'rows' => [
        whocan_row('crm.deal.view', 'View ' . Tlp('opportunity')),
        whocan_row('crm.deal.edit', 'Add and edit ' . Tlp('opportunity')),
        whocan_row('crm.deal.export', 'Download ' . Tlp('opportunity') . ' to Excel / CSV'),
        whocan_row('crm.deal.add_customer', 'Add a ' . Tl('client') . ' from a won ' . Tl('opportunity') . ' that had no lead'),
    ]];
    $f[] = ['f' => 'Pipelines & stages', 'd' => 'The steps a lead or deal moves through', 'rows' => [
        whocan_row('crm.pipeline.manage', 'Change pipelines and stages'),
        whocan_rule('Set stage checks (stage gates)', 'Decided by: Manage system settings or Approve quotations.'),
    ]];
    $f[] = ['f' => TP('inquiry'), 'd' => 'Requests for a ' . Tl('quote'), 'rows' => whocan_module_rows('inquiries', whocan_sales_noun('inquiries'))];
    $f[] = ['f' => TP('quote'), 'd' => 'Prices sent to ' . Tlp('client'), 'rows' => array_merge(
        whocan_module_rows('quotes', whocan_sales_noun('quotes')), [
        whocan_row('crm.quote.create', 'Create and edit ' . Tlp('quote') . ' (the ' . Tl('quote') . ' form)'),
        whocan_row('crm.quote.send', 'Send to the ' . Tl('client')),
        whocan_row('crm.followup.manage', 'Follow-ups and reminders'),
        whocan_row('crm.template.manage', T('quote') . ' and e-mail templates'),
        whocan_row('crm.quote.export', 'Download ' . Tlp('quote') . ' to Excel / CSV'),
    ])];
    $f[] = ['f' => 'Project costing', 'd' => 'Cost build-up behind a ' . Tl('quote'), 'rows' => [
        whocan_row('costing.view', 'View costings'),
        whocan_row('costing.prepare', 'Prepare costings'),
        whocan_row('costing.heads.manage', 'Manage the cost-head library'),
        whocan_rule('Approve costings', 'Decided by the approval levels set in Approvals (your rule 19i).'),
    ]];
    $f[] = ['f' => 'Orders & contracts', 'd' => 'Work won and signed', 'rows' => array_merge(
        whocan_module_rows('crm_orders', 'orders and contracts'), [
        whocan_row('crm.contract.register', 'Register a ' . Tl('client') . ' and contract'),
    ])];
    $f[] = ['f' => 'Customer money in Sales', 'd' => 'Billed, received, overdue, owed', 'rows' => [
        whocan_row('data.customer_money', 'See what ' . Tlp('client') . ' were billed and paid'),
    ]];
    $f[] = ['f' => 'Targets', 'd' => 'Sales targets per person', 'rows' => [
        whocan_row('crm.targets.set', 'Set targets for others'),
        whocan_rule('See own target', 'Everyone in Sales sees their own target.'),
    ]];
    $f[] = ['f' => 'Sales reports', 'd' => 'Sales dashboard and funnel report', 'rows' => whocan_module_rows('crm_reports', 'sales reports')];
    $f[] = ['f' => 'Sales settings', 'd' => 'Working days, stale days, who gets new leads', 'rows' => [
        whocan_row('crm.settings.manage', 'Change Sales settings'),
    ]];
    return $f;
}

//  A fine right that belongs to one module's feature outside Sales.
function whocan_fine_home() {
    return [
        'ops.call.create' => 'calls', 'ops.call.delete' => 'calls',
        'ops.job.allocate' => 'jobs', 'ops.job.close' => 'jobs',
        'hiring.admin' => 'hiring', 'hiring.material_change.propose' => 'hiring',
        'idems.type.manage' => 'idems', 'idems.timestamp.edit' => 'idems', 'idems.template.approve' => 'idems',
        'person.iddoc.view' => 'identity', 'person.iddoc.manage' => 'identity',
        'master.manage' => 'masters',
        'users.manage.branch' => 'users', 'users.manage.global' => 'users', 'org.hierarchy.view' => 'users',
        'settings.manage' => 'settings',
    ];
}
//  What to call the leftover fine rights of an area.
function whocan_more_name($area) {
    return ['Money' => 'Figures and reconciliation', 'Insights' => 'Dashboards',
            'Operations' => 'Availability and sign-offs', 'Quality & Accreditation' => 'Audit trail'][$area] ?? ('More rights in ' . $area);
}
function whocan_area_icon($area) {
    return ['Sales' => 'target', 'Operations' => 'flow', 'Recruitment' => 'users', 'Reporting' => 'file', 'Money' => 'rupee',
            'Quality & Accreditation' => 'checkc', 'Insights' => 'chart', 'Directory' => 'building', 'Admin' => 'lock',
            'Other' => 'more'][$area] ?? 'more';
}

//  The tree for a set of rights: [area => ['features' => [ [f, d, rows] ]]],
//  holding ONLY rows whose key is in $keys (plus rule/decision rows in a feature
//  that has at least one switch). Every key in $keys appears exactly once.
function whocan_tree(array $keys) {
    $want = array_flip($keys);
    $placed = [];
    $take = function (array $rows) use ($want, &$placed) {
        $out = []; $hasKey = false;
        foreach ($rows as $r) {
            if ($r['type'] !== 'key') { $out[] = $r; continue; }
            if (!isset($want[$r['key']]) || isset($placed[$r['key']])) continue;
            $placed[$r['key']] = true; $out[] = $r; $hasKey = true;
        }
        return $hasKey ? $out : [];
    };
    $tree = [];
    // Sales first, by hand.
    foreach (whocan_sales_features() as $feat) {
        $rows = $take($feat['rows']);
        if ($rows) $tree['Sales'][] = ['f' => $feat['f'], 'd' => $feat['d'], 'rows' => $rows];
    }
    // Every other area from the menu's own map.
    $areas = access_module_areas();
    $nav = function_exists('permission_nav_groups') ? permission_nav_groups() : [];
    $home = whocan_fine_home();
    foreach ($areas as $area => $mods) {
        if ($area === 'Sales') {
            // Anything in Sales the hand layout did not place (a right added later).
            $left = [];
            foreach ($nav['Sales'] ?? [] as $k) $left[] = whocan_row($k);
            foreach ($mods as $m) foreach (whocan_module_rows($m) as $r) $left[] = $r;
            $rows = $take($left);
            if ($rows) $tree['Sales'][] = ['f' => 'More Sales rights', 'd' => '', 'rows' => $rows];
            continue;
        }
        foreach ($mods as $m) {
            if (!isset(ACCESS_MODULES[$m])) continue;
            $rows = whocan_module_rows($m);
            foreach ($nav[$area] ?? [] as $k) if (($home[$k] ?? '') === $m) $rows[] = whocan_row($k);
            $rows = $take($rows);
            if ($rows) $tree[$area][] = ['f' => access_module_label($m), 'd' => '', 'rows' => $rows];
        }
        $more = [];
        foreach ($nav[$area] ?? [] as $k) $more[] = whocan_row($k);
        $rows = $take($more);
        if ($rows) $tree[$area][] = ['f' => whocan_more_name($area), 'd' => '', 'rows' => $rows];
    }
    // Never dropped: whatever no area placed.
    $left = [];
    foreach ($keys as $k) if (!isset($placed[$k])) $left[] = whocan_row($k);
    $rows = $take($left);
    if ($rows) $tree['Other'][] = ['f' => 'Other rights', 'd' => '', 'rows' => $rows];
    return $tree;
}

//  Every key the tree draws — the test checks it equals the keys asked for.
function whocan_tree_keys(array $tree) {
    $out = [];
    foreach ($tree as $feats) foreach ($feats as $f) foreach ($f['rows'] as $r) if ($r['type'] === 'key') $out[] = $r['key'];
    return $out;
}

// ---- Roles -------------------------------------------------------------------

//  The roles the role tab lists: what /access lists (this plan's roles plus the
//  company's own), Master Admin left out — it always has everything.
function whocan_roles() {
    $roles = function_exists('roles_for_licence') ? roles_for_licence() : ORG_ROLES;
    unset($roles['MASTER_ADMIN']);
    return $roles;
}

//  Who a role reaches: active people (not masters) whose role resolves to it.
function whocan_role_people_counts() {
    $out = [];
    foreach (whocan_try(fn() => ops_all("SELECT role, permissions FROM users WHERE is_active=1 AND COALESCE(is_superuser,0)=0"), []) ?: [] as $u) {
        $r = strtoupper((string) $u['role']);
        $base = function_exists('role_effective_key') ? role_effective_key($r) : $r;
        $out[$base]['people'] = ($out[$base]['people'] ?? 0) + 1;
        if (trim((string) $u['permissions']) !== '') $out[$base]['own'] = ($out[$base]['own'] ?? 0) + 1;
        if ($r !== $base) $out[$r]['people'] = ($out[$r]['people'] ?? 0) + 1;
    }
    return $out;
}

//  A short plain-English line for each role (moved here from views/ops/access.php
//  so the old and the new screen say the same thing).
function role_blurb($role) {
    $role = strtoupper((string) $role);
    $b = [
        'BUSINESS_DIRECTOR' => 'Top management. Sees everything across all offices and ' . Tlp('sbu') . ' — all dashboards, revenue, salary and profitability — but does not run day-to-day operations.',
        'SBU_HEAD' => 'Heads one ' . Tl('sbu') . ' across all offices. Full dashboards & figures for their ' . Tl('sbu') . '; approves inspection reports for their people.',
        'BRANCH_MANAGER' => 'Runs one office end-to-end: calls, jobs, availability, report approvals, masters, and users in that office.',
        'BRANCH_APP_MANAGER' => 'Application manager in a branch — masters, overheads and branch users; limited operational view.',
        'OPERATION_MANAGER' => 'Under the branch manager — allocates & closes jobs, runs the availability board, approves reports.',
        'ASST_MANAGER' => 'Assists operations — creates calls, allocates jobs, manages availability. No money figures.',
        'COORDINATOR' => 'Day-to-day desk — schedules and closes jobs, availability board, sees revenue but not salary/profit.',
        'BUSINESS_DEV_MANAGER' => 'Sales — creates & sends ' . Tlp('quote') . ' and runs follow-ups across offices.',
        'KEY_ACCOUNTS_MANAGER' => 'Sales — owns key accounts; creates & sends ' . Tlp('quote') . ' and follow-ups.',
        'MARKETING_MANAGER' => 'Senior sales — everything BDM/KAM can do plus approve ' . Tlp('quote') . ' and manage templates.',
        'MARKETING_EXECUTIVE' => 'Junior sales — drafts ' . Tlp('quote') . ' and manages follow-ups (no send/approve).',
        'FINANCE' => 'Accounts — invoicing, credit reconciliation, register clients/contracts; sees all figures.',
        'INSPECTOR' => 'Field engineer — only their own My Jobs / My Voucher; no admin screens.',
    ];
    return $b[$role] ?? '';
}

//  A role's switches, what it gives today (role_perms(), the set the old screen
//  pre-ticks). A custom role has no switches of its own — it uses its base's.
function whocan_role_set($role) { return array_values(role_perms($role)); }

// ---- People ------------------------------------------------------------------

//  The people this administrator may open: everybody for a global user manager,
//  their own branch for a branch-level one (the rule /user-edit applies).
function whocan_people($q = '') {
    $globalMgr = can('users.manage.global');
    $myOffice = (int) (current_user()['home_office_id'] ?? 0);
    $w = $globalMgr ? '1=1' : 'u.home_office_id = ' . $myOffice;
    $a = [];
    if (trim((string) $q) !== '') {
        $w .= " AND (u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.role LIKE ?)";
        $like = '%' . trim((string) $q) . '%'; $a = [$like, $like, $like, $like];
    }
    return whocan_try(fn() => ops_all("SELECT u.*, o.name AS home_office_name FROM users u LEFT JOIN offices o ON o.id = u.home_office_id
                                       WHERE $w ORDER BY u.is_active DESC, u.first_name, u.last_name, u.username LIMIT 500", $a), []) ?: [];
}

//  May this administrator open this person? The checks /user-edit makes.
function whocan_person_allowed($user) {
    if (!$user || !whocan_can_people()) return false;
    if (!can('users.manage.global') && (int) ($user['home_office_id'] ?? 0) !== (int) (current_user()['home_office_id'] ?? 0)) return false;
    return true;
}
function whocan_is_master_row($u) {
    return !empty($u['is_superuser']) || strtoupper((string) ($u['role'] ?? '')) === 'MASTER_ADMIN';
}

//  A person's display name.
function whocan_person_name($u) {
    $n = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    return $n !== '' ? $n : (string) ($u['username'] ?? '');
}

//  The person's branches and business units, in words.
function whocan_scope_words($u) {
    $sc = function_exists('user_scope_of') ? user_scope_of($u) : ['offices' => 'ALL', 'sbus' => 'ALL'];
    if ($sc['offices'] === 'ALL') $off = 'Every ' . Tl('office');
    elseif (!$sc['offices']) $off = 'None — no home ' . Tl('office') . ' is set, so they see no records';
    else {
        $names = [];
        foreach ($sc['offices'] as $id) $names[] = (string) (ops_val("SELECT name FROM offices WHERE id=?", [(int) $id]) ?: ('#' . (int) $id));
        $off = implode(', ', $names);
    }
    $sbu = $sc['sbus'] === 'ALL' ? 'All' : implode(', ', (array) $sc['sbus']);
    return ['offices' => $off, 'sbus' => $sbu, 'raw' => $sc];
}

// ---- The person save, shared with /user-edit ---------------------------------

//  What the person form's permission and branch fields store. ONE implementation,
//  called by the person form (/user-edit, ops_users()) and by this screen — so
//  the two cannot store different values for the same ticks. Returns
//  [permissions, scope_offices, scope_sbus], exactly as the form always did:
//    · offices: a global user manager sets them ("every" → ALL); a branch-level
//      manager's save stores '' (their own branch) — unchanged behaviour;
//    · business units: "every" → ALL, else the ticked units that exist;
//    · permissions: what this administrator may hand out, stored by
//      user_perms_to_store() ('' when it is the role's own set, else "@diff");
//      "Reset to the role" stores ''.
function user_access_from_post(array $b, $role, $user, $globalMgr) {
    if ($globalMgr) {
        $scopeOffices = !empty($b['scope_offices_all']) ? 'ALL'
            : implode(',', array_map('intval', array_filter((array) ($b['scope_offices'] ?? []))));
    } else { $scopeOffices = ''; }                                       // '' = OWN(home)
    $sbuOpts = lk_options_or('sbu', OPS_SBUS);
    $scopeSbus = !empty($b['scope_sbus_all']) ? 'ALL'
        : implode(',', array_values(array_intersect(array_keys($sbuOpts), (array) ($b['scope_sbus'] ?? []))));
    $assignable = array_keys(assignable_permissions($globalMgr));
    $chosen = array_intersect(array_filter((array) ($b['permissions'] ?? [])), $assignable);
    $perms = user_perms_to_store($chosen, $role, $assignable, $user['permissions'] ?? '');
    if ($globalMgr && !empty($b['reset_perms'])) $perms = '';
    return [$perms, $scopeOffices, $scopeSbus];
}

//  Does a stored value still let the person manage users? Read from what it
//  GRANTS — the "@diff" form never holds the bare word (person form guard).
function user_access_keeps_user_mgmt($perms, $role) {
    $role = function_exists('role_effective_key') ? role_effective_key(strtoupper((string) $role)) : strtoupper((string) $role);
    $eff = perm_resolve_stored((string) $perms, $role);
    return in_array('users.manage.branch', $eff, true) || in_array('users.manage.global', $eff, true);
}

//  POST /who-can-person?id=N — a person's switches and branches. The same gates,
//  guards, storage and audit entry as the person form; only these three fields.
function whocan_save_person() {
    ops_require(whocan_can_people(), 'You cannot manage users.');
    $globalMgr = can('users.manage.global');
    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    $user = ops_one("SELECT * FROM users WHERE id=?", [$id]);
    if (!$user) { http_response_code(404); view('notfound'); return true; }
    if (!whocan_person_allowed($user)) { flash('You can only manage users in your own office.', 'error'); redirect('/who-can?tab=people'); }
    if (whocan_is_master_row($user)) {
        // A Master Admin has everything; only a Master Admin may change one (the
        // person form's rule) — and there are no switches to save.
        flash(is_master() ? 'A Master Admin always has everything — there is nothing to switch.' : 'Only a Master Admin can create or change a Master Admin account.', is_master() ? 'info' : 'error');
        redirect('/who-can?tab=people&id=' . $id);
    }
    $role = strtoupper((string) ($user['role'] ?? 'COORDINATOR'));
    [$perms, $scopeOffices, $scopeSbus] = user_access_from_post($_POST, $role, $user, $globalMgr);
    // The person form's own guard: nobody removes their OWN user-management.
    $meId = (int) (current_user()['id'] ?? 0);
    if ((int) $user['id'] === $meId && !is_master() && $perms !== '' && !user_access_keeps_user_mgmt($perms, $role)) {
        flash('You cannot remove your own user-management access — ask another administrator to change it.', 'error');
        redirect('/who-can?tab=people&id=' . $id);
    }
    //  Q10 — this person's "whose records" choice ('' = as the role). "All" = Branch
    //  with every branch (only a company-wide user manager may set branches).
    $recNote = null;
    if (isset($_POST['records_mode']) && function_exists('sales_rec_store')) {
        $rm = (string) $_POST['records_mode'];
        if ($rm === 'all' && $globalMgr) { $scopeOffices = 'ALL'; $rm = 'branch'; }
        if (!in_array($rm, ['', 'own', 'team', 'branch'], true)) $rm = '';
        $st = sales_rec_store(); $was = (string) ($st['users'][(string) (int) $user['id']] ?? '');
        if ($rm !== $was) {
            if ($rm === '') unset($st['users'][(string) (int) $user['id']]); else $st['users'][(string) (int) $user['id']] = $rm;
            sales_rec_store_put($st);
            $recNote = ['records_seen' => ['from' => $was === '' ? 'as role' : $was, 'to' => $rm === '' ? 'as role' : $rm]];
        }
    }
    db()->prepare("UPDATE users SET scope_offices=?, scope_sbus=?, permissions=? WHERE id=?")
        ->execute([$scopeOffices, $scopeSbus, $perms, (int) $user['id']]);
    $newRow = ['role' => $user['role'], 'permissions' => $perms, 'is_superuser' => (int) ($user['is_superuser'] ?? 0),
               'is_active' => (int) ($user['is_active'] ?? 1), 'scope_offices' => $scopeOffices, 'scope_sbus' => $scopeSbus];
    $adiff = function_exists('access_diff') ? access_diff($user, $newRow) : [];
    if ($recNote) $adiff = array_merge((array) $adiff, $recNote);
    if ($adiff && function_exists('idems_log')) idems_log('user', (int) $user['id'], 'ACCESS_CHANGED',
        ['field' => $user['username'], 'reason' => 'by ' . user_name(current_user()) . ' (Who can do what)', 'new' => $adiff]);
    flash($adiff ? 'Saved for ' . whocan_person_name($user) . '.' : 'Nothing changed for ' . whocan_person_name($user) . '.');
    redirect('/who-can?tab=people&id=' . (int) $user['id']);
}

// ---- What they will see -----------------------------------------------------

//  For a PERSON: their own gates, asked as them. Each line: [ok, text].
function whocan_preview_user($u) {
    if (!$u) return [];
    if (whocan_is_master_row($u)) return [[true, 'Everything, in every branch — a Master Admin always has every right.']];
    if (empty($u['is_active'])) return [[false, 'Nothing — this login is switched off.']];
    $run = function () {
        $L = [];
        $f = fn($n) => function_exists($n);
        $sx = $f('sx_can') && sx_can();
        $L[] = $sx ? [true, 'Sales home, Today and the Pipeline board — ' . ($f('sx_can_see_all') && sx_can_see_all() ? "everyone's lists" : 'their own list only')]
                   : [false, 'The Sales workspace (Sales home, Today, Pipeline board)'];
        $lv = $f('leads_can_view') && leads_can_view();
        $L[] = $lv ? [true, 'Leads: view' . (can('mod.leads.add') ? ', add' : '') . ($f('leads_can_edit') && leads_can_edit() ? ', edit' : '')
                         . ($f('leads_can_export') && can('mod.leads.view') && leads_can_export() ? ', download' : '')]
                   : [false, 'Leads'];
        $ov = $f('opp_can_view') && opp_can_view();
        $L[] = $ov ? [true, TP('opportunity') . ': view' . ($f('opp_can_edit') && opp_can_edit() ? ', add and edit' : '')] : [false, TP('opportunity')];
        $L[] = can('mod.inquiries.view') ? [true, TP('inquiry') . ': view' . (can('mod.inquiries.add') ? ', add' : '') . (can('mod.inquiries.edit') ? ', edit' : '')] : [false, TP('inquiry')];
        if (can('mod.quotes.view')) {
            $q = [];
            if (can('crm.quote.create')) $q[] = 'create';
            $q[] = can('crm.quote.send') ? 'send' : 'cannot send';
            $q[] = can('crm.quote.approve') ? 'approve' : 'cannot approve';
            if (can('crm.followup.manage')) $q[] = 'follow up';
            $L[] = [true, TP('quote') . ': view, ' . implode(', ', $q)];
        } else $L[] = [false, TP('quote')];
        $pc = $f('pc_can') && pc_can();
        $L[] = $pc ? [true, 'Project costing: view' . ($f('pc_can_edit') && pc_can_edit() ? ' and prepare' : '')] : [false, 'Project costing'];
        $L[] = $f('ar_can') && ar_can() ? [true, 'What customers were billed and paid (Sales screens, Receivables)'] : [false, 'Customer payments (billed, received, overdue)'];
        $L[] = $f('sxt_can_set') && sxt_can_set() ? [true, 'Set targets for others'] : [false, 'Targets of others (they see their own)'];
        $L[] = $f('pipe_can') && pipe_can() ? [true, 'Change pipelines and stages'] : [false, 'Changing pipelines and stages'];
        $L[] = $f('sx_settings_can') && sx_settings_can() ? [true, 'Change Sales settings'] : [false, 'Sales settings'];
        // Branches.
        $sc = scope_offices();
        if ($sc === 'ALL') $L[] = [true, 'Records of every ' . Tl('office')];
        elseif (!$sc) $L[] = [false, 'Records of any ' . Tl('office') . ' — no home ' . Tl('office') . ' is set'];
        else {
            $names = [];
            foreach ($sc as $id) $names[] = (string) (ops_val("SELECT name FROM offices WHERE id=?", [(int) $id]) ?: ('#' . (int) $id));
            $L[] = [true, 'Records of ' . (count($names) === 1 ? 'their ' . Tl('office') . ' only: ' : 'these ' . Tlp('office') . ': ') . implode(', ', $names)];
        }
        // Every other area, by the modules it can open.
        foreach (access_module_areas() as $area => $mods) {
            if ($area === 'Sales') continue;
            $open = [];
            foreach ($mods as $m) if (isset(ACCESS_MODULES[$m]) && can("mod.$m.view")) $open[] = access_module_label($m);
            $L[] = $open ? [true, $area . ': ' . implode(', ', $open)] : [false, $area];
        }
        return $L;
    };
    if (function_exists('appr_as_user')) return appr_as_user($u, $run) ?? [];
    $prev = $_SESSION['uid'] ?? null;
    $_SESSION['uid'] = (int) $u['id']; current_user(true); ua(true);
    try { return $run(); }
    finally { if ($prev === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prev; current_user(true); ua(true); }
}

//  For a ROLE: what a person in it gets, read from the role's set (no person is
//  signed in as). Branches are the role's recommended scope.
function whocan_preview_role($role, array $set) {
    $h = fn($k) => function_exists('perm_set_has') ? perm_set_has($set, $k) : in_array($k, $set, true);
    $L = [];
    $sales = function_exists('licence_enabled') ? licence_enabled('sales') : true;
    $lv = $sales && ($h('mod.leads.view') || $h('mod.inquiries.view'));
    $ov = $sales && $h('crm.deal.view');
    $L[] = ($lv || $ov) ? [true, 'Sales home, Today and the Pipeline board — ' . ($h('crm.sales.see_all') ? "everyone's lists" : 'their own list only')]
                        : [false, 'The Sales workspace (Sales home, Today, Pipeline board)'];
    $L[] = $lv ? [true, 'Leads: view' . ($h('mod.leads.add') ? ', add' : '') . (($h('mod.leads.edit') || $h('mod.inquiries.edit')) ? ', edit' : '') . ($h('crm.lead.export') && $h('mod.leads.view') ? ', download' : '')] : [false, 'Leads'];
    $L[] = $ov ? [true, TP('opportunity') . ': view' . ($h('crm.deal.edit') ? ', add and edit' : '')] : [false, TP('opportunity')];
    $L[] = $h('mod.inquiries.view') ? [true, TP('inquiry') . ': view' . ($h('mod.inquiries.add') ? ', add' : '') . ($h('mod.inquiries.edit') ? ', edit' : '')] : [false, TP('inquiry')];
    if ($h('mod.quotes.view')) {
        $q = [];
        if ($h('crm.quote.create')) $q[] = 'create';
        $q[] = $h('crm.quote.send') ? 'send' : 'cannot send';
        $q[] = $h('crm.quote.approve') ? 'approve' : 'cannot approve';
        if ($h('crm.followup.manage')) $q[] = 'follow up';
        $L[] = [true, TP('quote') . ': view, ' . implode(', ', $q)];
    } else $L[] = [false, TP('quote')];
    $L[] = $h('costing.view') ? [true, 'Project costing: view' . ($h('costing.prepare') ? ' and prepare' : '')] : [false, 'Project costing'];
    $L[] = $h('data.customer_money') ? [true, 'What customers were billed and paid (Sales screens, Receivables)'] : [false, 'Customer payments (billed, received, overdue)'];
    $L[] = $h('crm.targets.set') ? [true, 'Set targets for others'] : [false, 'Targets of others (they see their own)'];
    $L[] = $h('crm.pipeline.manage') ? [true, 'Change pipelines and stages'] : [false, 'Changing pipelines and stages'];
    $L[] = $h('crm.settings.manage') ? [true, 'Change Sales settings'] : [false, 'Sales settings'];
    $base = function_exists('role_defaults_base') ? role_defaults_base($role) : ['offices' => 'OWN'];
    $L[] = [true, ($base['offices'] ?? 'OWN') === 'ALL' ? 'Records of every ' . Tl('office') : 'Records of their own ' . Tl('office') . ' (set per person under People)'];
    foreach (access_module_areas() as $area => $mods) {
        if ($area === 'Sales') continue;
        $open = [];
        foreach ($mods as $m) if (isset(ACCESS_MODULES[$m]) && $h("mod.$m.view") && (!function_exists('module_key_licensed') || module_key_licensed($m))) $open[] = access_module_label($m);
        $L[] = $open ? [true, $area . ': ' . implode(', ', $open)] : [false, $area];
    }
    return $L;
}

// ---- History (the sealed audit chain) -----------------------------------------

//  Recent changes to a role's switches, or to a person's access, in words.
function whocan_history($kind, $id, $limit = 8) {
    if ($kind === 'role') {
        $rows = whocan_try(fn() => ops_all("SELECT created_at, username, action, new_value, reason FROM idems_audit
                                             WHERE entity='role' AND field=? ORDER BY id DESC LIMIT " . (int) $limit, [(string) $id]), []) ?: [];
    } else {
        $rows = whocan_try(fn() => ops_all("SELECT created_at, username, action, new_value, reason FROM idems_audit
                                             WHERE entity='user' AND entity_id=? AND action IN ('ACCESS_CHANGED','ACCESS_STORED_AS_DIFFERENCES')
                                             ORDER BY id DESC LIMIT " . (int) $limit, [(int) $id]), []) ?: [];
    }
    $all = all_permissions();
    $lbl = fn($k) => $all[$k] ?? $k;
    $out = [];
    foreach ($rows as $r) {
        $n = json_decode((string) $r['new_value'], true);
        $bits = [];
        if ($r['action'] === 'ACCESS_STORED_AS_DIFFERENCES') $bits[] = 'own access stored as differences from the role — nothing changed';
        if (is_array($n)) {
            $g = array_values(array_filter((array) ($n['granted'] ?? []), fn($k) => $k !== '' && $k[0] !== '@'));
            $v = array_values(array_filter((array) ($n['revoked'] ?? []), fn($k) => $k !== '' && $k[0] !== '@'));
            if ($g) $bits[] = 'allowed ' . implode(', ', array_map($lbl, array_slice($g, 0, 4))) . (count($g) > 4 ? ' and ' . (count($g) - 4) . ' more' : '');
            if ($v) $bits[] = 'took away ' . implode(', ', array_map($lbl, array_slice($v, 0, 4))) . (count($v) > 4 ? ' and ' . (count($v) - 4) . ' more' : '');
            if (isset($n['role']) && is_array($n['role']))
                $bits[] = (string) $n['role'][0] === '' ? 'role set to ' . role_name((string) $n['role'][1])
                        : 'role ' . role_name((string) $n['role'][0]) . ' → ' . role_name((string) $n['role'][1]);
            foreach (['scope_offices' => Tlp('office'), 'scope_business units' => 'business units'] as $k => $w)
                if (isset($n[$k]) && is_array($n[$k])) $bits[] = $w . ' changed';
            if (isset($n['active'])) $bits[] = !empty($n['active'][1]) ? 'switched on' : 'switched off';
        }
        $bits = array_values(array_filter($bits));
        $out[] = ['when' => substr((string) $r['created_at'], 0, 10), 'who' => (string) $r['username'],
                  'what' => $bits ? implode('; ', $bits) : 'saved (no right changed)'];
    }
    return $out;
}

// ---- Company switches and plan (read here, changed where they always were) ----

function whocan_plan() {
    $out = [];
    foreach (PRODUCT_MODULES as $k => $m)
        $out[] = ['key' => $k, 'name' => $m[0], 'desc' => $m[1], 'on' => !function_exists('licence_enabled') || licence_enabled($k), 'core' => !empty($m[3])];
    return $out;
}
function whocan_switches_summary() {
    $off = function_exists('feat_off_entries') ? (array) whocan_try(fn() => feat_off_entries(), []) : [];
    $mods = [];
    foreach (whocan_plan() as $p) if (!$p['on']) $mods[] = $p['name'];
    return ['features_off' => count($off), 'modules_off' => $mods];
}

// ---- The route -----------------------------------------------------------------

//  GET  /who-can?tab=roles|people|compare|switches|plan
//  POST /who-can-person?id=N        (a role is saved by the existing /access)
function ops_whocan($route, $method) {
    ops_require(whocan_can(), 'Only administrators can open Who can do what.');
    if ($route === 'who-can-person') {
        if ($method !== 'POST') redirect('/who-can?tab=people' . (isset($_GET['id']) ? '&id=' . (int) $_GET['id'] : ''));
        return whocan_save_person();
    }
    $canRoles = whocan_can_roles(); $canPeople = whocan_can_people();
    $tab = (string) ($_GET['tab'] ?? '');
    if (!in_array($tab, ['roles', 'people', 'compare', 'switches', 'plan'], true)) $tab = $canRoles ? 'roles' : 'people';
    if (in_array($tab, ['roles', 'compare'], true) && !$canRoles) $tab = 'people';
    if ($tab === 'people' && !$canPeople && !$canRoles) $tab = 'roles';
    $v = ['tab' => $tab, 'canRoles' => $canRoles, 'canPeople' => $canPeople,
          'canSwitches' => function_exists('cockpit_can') ? cockpit_can() : is_master()];

    if ($tab === 'roles' || $tab === 'compare') {
        $roles = whocan_roles();
        $v['roles'] = $roles;
        $v['counts'] = whocan_role_people_counts();
        $keys = whocan_role_keys();
        $v['tree'] = whocan_tree($keys);
        if ($tab === 'roles') {
            $sel = strtoupper((string) ($_GET['role'] ?? ''));
            if (!isset($roles[$sel])) $sel = isset($roles['MARKETING_EXECUTIVE']) ? 'MARKETING_EXECUTIVE' : (string) array_key_first($roles);
            $from = strtoupper((string) ($_GET['from'] ?? ''));
            if (!isset($roles[$from]) || $from === $sel) $from = '';
            $base = function_exists('role_effective_key') ? role_effective_key($sel) : $sel;
            $v += ['sel' => $sel, 'base' => $base, 'isCustom' => $base !== $sel, 'from' => $from,
                   'set' => whocan_role_set($from !== '' ? $from : $sel),
                   'savedSet' => whocan_role_set($sel),
                   'recommended' => role_recommended_perms($sel),
                   'scope' => role_defaults_base($sel),
                   'preview' => whocan_preview_role($sel, whocan_role_set($sel)),
                   'history' => whocan_history('role', $base)];
        } else {
            $a = strtoupper((string) ($_GET['a'] ?? '')); $b = strtoupper((string) ($_GET['b'] ?? ''));
            $keysR = array_keys($roles);
            if (!isset($roles[$a])) $a = isset($roles['MARKETING_EXECUTIVE']) ? 'MARKETING_EXECUTIVE' : $keysR[0];
            if (!isset($roles[$b])) $b = isset($roles['MARKETING_MANAGER']) && $a !== 'MARKETING_MANAGER' ? 'MARKETING_MANAGER' : ($keysR[1] ?? $keysR[0]);
            $v += ['a' => $a, 'b' => $b, 'setA' => whocan_role_set($a), 'setB' => whocan_role_set($b)];
        }
    }
    if ($tab === 'people') {
        $globalMgr = can('users.manage.global');
        $v['globalMgr'] = $globalMgr;
        $v['q'] = trim((string) ($_GET['q'] ?? ''));
        $v['people'] = $canPeople ? whocan_people($v['q']) : [];
        $id = (int) ($_GET['id'] ?? 0);
        $user = $id ? ops_one("SELECT * FROM users WHERE id=?", [$id]) : null;
        if ($user && !whocan_person_allowed($user)) { flash('You can only manage users in your own office.', 'error'); $user = null; }
        if (!$user && $v['people']) foreach ($v['people'] as $p) if (!empty($p['is_active']) && !whocan_is_master_row($p)) { $user = $p; break; }
        $v['user'] = $user;
        if ($user) {
            $role = strtoupper((string) ($user['role'] ?? 'COORDINATOR'));
            $from = strtoupper((string) ($_GET['from'] ?? ''));
            $roles = function_exists('roles_all') ? roles_all() : ORG_ROLES;
            if (!isset($roles[$from])) $from = '';
            $eff = user_effective_perms($user);                       // what the person form pre-ticks
            $v += ['role' => $role, 'from' => $from,
                   'set' => $from !== '' ? role_perm_set($from) : $eff,
                   'roleSet' => role_perm_set($role),
                   'keys' => whocan_person_keys($globalMgr),
                   'scopeWords' => whocan_scope_words($user),
                   'offices' => offices_list(), 'sbuOpts' => lk_options_or('sbu', OPS_SBUS),
                   'preview' => whocan_preview_user($user),
                   'history' => whocan_history('user', (int) $user['id'])];
            $v['tree'] = whocan_tree($v['keys']);
        }
    }
    if ($tab === 'switches') $v['switches'] = whocan_switches_summary();
    if ($tab === 'plan') $v['plan'] = whocan_plan();
    view('ops/whocan', $v);
    return true;
}
