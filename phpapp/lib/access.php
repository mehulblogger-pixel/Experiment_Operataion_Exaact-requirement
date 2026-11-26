<?php
// ============================================================================
//  Organisation structure, configurable access (scope + permissions),
//  settings (financial year), and financial-year helpers.
//  Scope = which data you see (offices / Business Units / self).
//  Permissions = which features you may use.
// ============================================================================

// ---- Roles ----------------------------------------------------------------
const ORG_ROLES = [
    'MASTER_ADMIN' => 'Master Admin', 'BUSINESS_DIRECTOR' => 'Business Director',
    'SBU_HEAD' => 'Business Unit Head', 'BRANCH_MANAGER' => 'Branch Manager',
    'BRANCH_APP_MANAGER' => 'Branch Application Manager', 'OPERATION_MANAGER' => 'Operation Manager',
    'ASST_MANAGER' => 'Asst. Manager', 'COORDINATOR' => 'Coordinator',
    // Marketing & Sales (CRM) roles
    'BUSINESS_DEV_MANAGER' => 'Business Development Manager', 'KEY_ACCOUNTS_MANAGER' => 'Key Accounts Manager',
    'MARKETING_MANAGER' => 'Marketing Manager', 'MARKETING_EXECUTIVE' => 'Marketing Executive',
    'FINANCE' => 'Finance',
    // Quality roles. An inspection body's quality loop has to be owned by somebody
    // who is not the person who did the work, so these two sit outside the
    // operations line and are deliberately NOT in MGMT_ROLES below: they
    // investigate the work, they do not run it.
    'QUALITY_HEAD' => 'Quality Head', 'TECHNICAL_HEAD' => 'Technical Head',
    'SR_INSPECTOR' => 'Senior Inspector', 'INSPECTOR' => 'Inspector', 'ADMIN' => 'Admin (legacy)',
];
// Operations/branch management roles — the ones is_admin_level()/is_coordinator_level()
// are meant to identify (they run calls, jobs, scheduling, vouchers, recruitment).
// SALES roles (BDM/KAM/Marketing) and FINANCE are deliberately NOT here: they are
// not operations managers, and lumping them in made every is_admin_level-gated
// operational widget/action leak to them (a salesperson seeing "vouchers to
// approve", an accountant seeing "raise inspection call"). Their own CRM/finance
// access is enforced by module/permission can() checks, which is unaffected.
const MGMT_ROLES = ['MASTER_ADMIN','ADMIN','BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER','BRANCH_APP_MANAGER','OPERATION_MANAGER'];
// "Coordinator and above" -- the same set is_coordinator_level() means. Who may
// record a nonconformity (owner's rule, 2026-10-19, Q-B1).
const ACCESS_NCR_RECORDERS = ['MASTER_ADMIN','ADMIN','BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER','BRANCH_APP_MANAGER','OPERATION_MANAGER','ASST_MANAGER','COORDINATOR'];
// Marketing & Sales roles (CRM funnel owners)
const SALES_ROLES = ['BUSINESS_DEV_MANAGER','KEY_ACCOUNTS_MANAGER','MARKETING_MANAGER','MARKETING_EXECUTIVE'];
// The owner's rule, 2026-10-20 (Sales audit D-01): "Sales executive, sales
// manager and anyone who is assigned to do the job" work leads and deals. The
// sales roles get the Leads module by default; anyone else the company assigns
// the work to is given it on Roles & access. View + Add only: editing a lead or a
// deal already rides on the Enquiries edit right these roles hold, deleting stays
// with management, and configuring the pipelines stays company setup.
const ACCESS_LEAD_WORKERS = SALES_ROLES;

// Which module a role belongs to — used to hide roles a company can't use.
// Only module-specific roles are listed; management/admin/coordinator roles are
// generic org roles and always available.
// A role is offered when ANY of its modules is on. Roles that span the whole
// business (Business Director, Business Unit Head, Branch Manager, Branch
// Application Manager, Master Admin) have no entry and are always offered.
// The operations line and the quality heads were missing, so a company that
// bought Sales alone was still asked to choose a Coordinator or an Operation
// Manager (owner, test book ONB-041). docs/01-roles.md says what each one sees.
const ROLE_MODULE = [
    'BUSINESS_DEV_MANAGER' => 'sales', 'KEY_ACCOUNTS_MANAGER' => 'sales',
    'MARKETING_MANAGER' => 'sales', 'MARKETING_EXECUTIVE' => 'sales',
    'FINANCE' => 'money',
    'SR_INSPECTOR' => 'operations', 'INSPECTOR' => 'operations',
    'OPERATION_MANAGER' => ['operations', 'hr'], 'COORDINATOR' => ['operations', 'hr'],
    'ASST_MANAGER' => 'operations',
    'QUALITY_HEAD' => 'operations', 'TECHNICAL_HEAD' => 'operations',
];

// The roles worth OFFERING on this installation: hide any role whose owning
// module is switched off, so a Recruitment company is not asked to choose an
// Inspector, a Marketing Manager or a Finance role it has no use for. Pass a
// subset of ORG_ROLES to filter it; $keep is a role to always include (e.g. the
// person's current role, so editing an existing user never drops their setting).
function roles_for_licence($roles = null, $keep = '') {
    // Default to every role including this workspace's own custom ones, so the
    // Add-person dropdown offers them. Custom roles have no ROLE_MODULE, so they
    // are always shown.
    $all = function_exists('roles_all') ? roles_all() : ORG_ROLES;
    $roles = $roles ?? $all;
    if (!function_exists('licence_enabled')) return $roles;
    $out = [];
    foreach ($roles as $k => $v) {
        $mods = (array) (ROLE_MODULE[$k] ?? []);
        if ($mods && $k !== $keep && !array_filter($mods, 'licence_enabled')) continue;
        $out[$k] = $v;
    }
    if ($keep !== '' && !isset($out[$keep]) && isset($all[$keep])) $out[$keep] = $all[$keep];
    return $out;
}

// The role a NEW person starts on: the everyday role of a module this company
// has, never a senior one picked just because it is first on the list.
function role_default_for_licence(array $offered) {
    foreach (['COORDINATOR', 'MARKETING_EXECUTIVE', 'INSPECTOR', 'FINANCE'] as $k)
        if (isset($offered[$k])) return $k;
    foreach ($offered as $k => $v) if ($k !== 'MASTER_ADMIN') return $k;
    return 'COORDINATOR';
}

// ---- Custom (company-defined) roles ---------------------------------------
// A workspace can add its own role names from the Add-person screen. A custom
// role is NEVER free-floating: it copies an existing built-in role's permissions
// (its "base"), so it always means something definite and can never accidentally
// grant everything. Stored per-workspace in the `custom_roles` setting as
// { KEY: { label, base } }. Fine-tuned later by adjusting its base role under
// Roles & access.
function custom_roles_all() {
    $raw = function_exists('setting_get') ? (string) setting_get('custom_roles', '') : '';
    if ($raw === '') return [];
    $d = json_decode($raw, true);
    if (!is_array($d)) return [];
    $out = [];
    foreach ($d as $k => $v) {
        $k = strtoupper(trim((string) $k));
        if ($k === '' || isset(ORG_ROLES[$k]) || !is_array($v)) continue;
        $base = strtoupper((string) ($v['base'] ?? 'COORDINATOR'));
        if (!isset(ORG_ROLES[$base])) $base = 'COORDINATOR';
        $out[$k] = ['label' => (string) ($v['label'] ?? $k), 'base' => $base];
    }
    return $out;
}
// Every selectable role: the built-in org roles plus this workspace's custom ones.
function roles_all() {
    $r = ORG_ROLES;
    foreach (custom_roles_all() as $k => $v) $r[$k] = $v['label'];
    return $r;
}
// Owner, 12 Nov 2026 — "All approval pending under any designations or level
// must show". An approval addressed to a role (a designation) belongs to
// everyone who holds it — including a role this company made from it (Roles &
// access → a custom role "based on" Business Unit Head). A custom role already
// takes its base role's permissions; it now also takes its approvals, so a
// "BU Head – Industrial" is asked to sign a "Business Unit Head" level, sees it on
// the dashboard, and can approve it. Never wider: only roles built on that one.
function approver_role_keys($role) {
    $role = strtoupper(trim((string) $role));
    if ($role === '') return [];
    $keys = [$role];
    if (isset(ORG_ROLES[$role])) foreach (custom_roles_all() as $k => $v) if ($v['base'] === $role) $keys[] = $k;
    return $keys;
}
function user_holds_approver_role($userRole, $stepRole) {
    $u = strtoupper(trim((string) $userRole));
    return $u !== '' && in_array($u, approver_role_keys($stepRole), true);
}
// A role KEY's display label (built-in or custom); the key itself as a last
// resort. (Distinct from ops.php role_label(), which labels a whole user row.)
function role_name($role) {
    $role = strtoupper(trim((string) $role));
    return roles_all()[$role] ?? $role;
}
// Resolve any role key to the BUILT-IN role whose permissions it uses. A custom
// role uses its chosen base; a built-in role is itself; an unknown key is left
// unchanged so each caller keeps its own historical fallback (least-privilege in
// the defaults, ADMIN in the two session choke points). This is the one place
// custom roles enter the permission engine, so a custom role can never mean more
// than the built-in role it was copied from.
function role_effective_key($role) {
    $role = strtoupper(trim((string) $role));
    if (isset(ORG_ROLES[$role])) return $role;
    $c = custom_roles_all();
    return isset($c[$role]) ? $c[$role]['base'] : $role;
}
// Create a custom role (a name + the built-in role it copies permissions from),
// store it on this workspace, and return its new key.
function custom_role_add($label, $base) {
    $label = trim((string) $label);
    if ($label === '') return '';
    $base = strtoupper(trim((string) $base));
    if (!isset(ORG_ROLES[$base])) $base = 'COORDINATOR';
    $key = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($label)), '_');
    if ($key === '') $key = 'ROLE';
    if (isset(ORG_ROLES[$key])) $key = 'CR_' . $key;
    $all = custom_roles_all();
    $stem = $key; $n = 1;
    while (isset($all[$key])) $key = $stem . '_' . (++$n);
    $all[$key] = ['label' => $label, 'base' => $base];
    if (function_exists('setting_set')) setting_set('custom_roles', json_encode($all));
    return $key;
}

// ---- Permission catalogue --------------------------------------------------
const PERMISSIONS = [
    'dash.operations' => 'Operations dashboard',
    'dash.financial'  => 'Financial dashboard',
    'dash.utilization'=> 'Utilization dashboard',
    'dash.people'     => 'People & compliance dashboard',
    'data.credit'     => 'See the inter-office credit figures',
    // What the branch actually keeps — the invoice less any credit passed over.
    // Held apart from the credit itself: a coordinator has to see the credit to
    // do their job, and does not need to see what the branch earns on it.
    // The labels below that name a business noun are re-worded by perm_label()
    // on the way to the screen — a constant cannot call T(). What is written
    // here is only the fallback, so it stays neutral too.
    'data.revenue'    => 'See the revenue figure on a work order / job',
    'data.salary'     => 'See salary / loaded cost',
    'data.profitability' => 'See profitability by contract number',
    'ops.call.create' => 'Create / edit work orders',
    'ops.job.allocate'=> 'Allocate / edit jobs',
    'ops.job.close'   => 'Close jobs',
    'ops.call.delete' => 'Delete work orders',
    'master.manage'   => 'Manage master data',
    'finance.reconcile'=> 'Credit reconciliation',
    'users.manage.branch' => 'Manage users in own office',
    'users.manage.global' => 'Manage all users & access',
    'settings.manage' => 'Manage system settings',
    // Recruitment module — configure ONLY the hiring module (pipelines, salary
    // headings, letter templates, approval matrix, careers page). Lets a customer
    // appoint a Recruitment Manager who configures their hiring product without
    // any system-wide administrator powers.
    'hiring.admin'    => 'Configure the recruitment module',
    //  GATE 2 · C46 — propose a change to an ALREADY-APPROVED requirement.
    //
    //  Held apart from hiring.admin and from ordinary editing on purpose: raising
    //  a requirement, editing an unapproved one and asking to change what an
    //  approver already signed are three different responsibilities. Granted per
    //  ROLE like every other permission (A4) — never per user — and the
    //  recruitment scope still applies on top of it, so it is not a licence to
    //  reach another branch's work.
    //
    //  Deliberately in NO role default. Somebody has to decide who may reopen an
    //  approved requirement, and that decision belongs to the customer.
    'hiring.material_change.propose' => 'Propose a change to an approved requirement (goes to change control)',
    //  GATE 3 (§12) — DECIDING a requirement review, which is not the same thing as
    //  being able to edit recruitment. Somebody who may move candidates all day is
    //  not thereby entitled to rule that a person still meets a requirement the
    //  business has just raised; that judgement is what this permission places.
    //  In no OPERATIONAL role's default: a Coordinator, Branch Manager, Inspector and
    //  so on do not hold it, and an organisation grants it deliberately. An
    //  administrator does hold it, because role_defaults_base() gives MASTER_ADMIN and
    //  ADMIN array_keys(PERMISSIONS) by this product's long-standing design — stated
    //  precisely here because "in no role default" was written first, is what somebody
    //  would rely on when deciding who can do what, and is not true of an admin.
    'hiring.review.clear' => 'Decide a requirement review on a candidate (continue or reject)',
    // ---- CRM / Marketing & Sales (fine-grained actions) ----
    'crm.quote.create'    => 'Create / edit quotations',
    'crm.quote.approve'   => 'Approve quotations (approval chain)',
    'crm.quote.send'      => 'Send quotation to the customer',
    'crm.followup.manage' => 'Manage quote follow-ups & reminders',
    'crm.contract.register' => 'Register client & contract (Accounts)',
    'crm.template.manage' => 'Manage quote / e-mail templates',
    // ---- Workforce & organisation ----
    'workforce.availability' => 'Manage the availability board',
    'workforce.report.approve' => 'Approve reports (reporting manager)',
    'org.hierarchy.view'  => 'View the organisation hierarchy',
    // ---- IDEMS (inspection documentation & endorsement) ----
    'idems.finalize'      => 'Finalize / issue & lock inspection reports',
    'idems.type.manage'   => 'Manage report types & IRN numbering rules',
    'idems.timestamp.edit'=> 'Edit locked timestamps (Branch App Admin only)',
    'idems.template.approve'=> 'Document controller — review & approve report formats before they go live',
    'idems.audit.view'    => 'View the compliance audit log',
    // ---- Identity documents (held under the DPDP Act for one stated purpose) ----
    // Kept out of every role default on purpose. Running operations is not a
    // reason to read a colleague's passport, so somebody has to grant it.
    'person.iddoc.view'   => 'See that an identity document is on file (numbers stay masked)',
    'person.iddoc.manage' => 'Hold identity documents — file, reveal a number, log a copy sent out, redact',
    // ---- Complaints & appeals (ISO/IEC 17020 §7.5, §7.6) ----
    // Recording and investigating is ordinary desk work. Deciding the outcome is
    // not, and the standard cares who did it, so it is its own permission.
    'complaints.decide'   => 'Decide complaints and appeals (§7.5.4 — must not have been involved)',
    // Closing a corrective action means asserting it worked. Held apart from
    // doing the work, because those are two different responsibilities.
    'capa.close'          => 'Close corrective actions (§8.7.3 — asserts the action was effective)',
    'ncr.close'           => 'Close nonconformities (asserts the disposition was carried out)',
    // ---- Sales rights that used to ride on another tick or on a job title ----
    //  Permission foundation A (owner, 21 Nov 2026: "All as recommended", the
    //  permission-screen proposal Q1–Q9). Each was decided by something else —
    //  a job title, the Leads edit tick, "inter-office credit figures", "Manage
    //  system settings" — so it could not be given or taken away on its own.
    //  Each now has a switch, and on the day it shipped it was on for EXACTLY the
    //  people who could already do it: perm_upgrade_split() works that out from
    //  the old rule for every set saved before (docs/02 19r; proved person by
    //  person in tests/test_perm_a_equivalence.php).
    'crm.sales.see_all'   => 'See other salespeople\'s lists (Everyone / Choose a person)',
    'crm.targets.set'     => 'Set sales targets for others',
    'crm.deal.view'       => 'View deals',
    'crm.deal.edit'       => 'Add and edit deals',
    'crm.pipeline.manage' => 'Change pipelines and stages',
    'crm.lead.export'     => 'Download leads to Excel / CSV',
    'crm.deal.export'     => 'Download deals to Excel / CSV',
    'crm.quote.export'    => 'Download quotations to Excel / CSV',
    'crm.settings.manage' => 'Change Sales settings (working days, follow-up days, who gets new leads)',
    //  Owner, 22 Nov 2026 (answer 9: "Don't hardcode it but allowed from permission list"):
    //  the "add the customer" step on a won deal that never had a lead (19y4).
    'crm.deal.add_customer' => 'Add a customer from a won deal that had no lead',
    //  Owner, 24 Nov 2026 ("as recommended"): deleting a deal is a switch of its own, like
    //  deleting a lead — it used to ride on the job title (is_admin_level()) (19y7).
    'crm.deal.delete'     => 'Delete deals (never a won one, or one with a quotation)',
    'data.customer_money' => 'See what customers were billed and paid (Sales screens and Receivables)',
    'costing.view'        => 'View project costings',
    'costing.prepare'     => 'Prepare project costings',
    'costing.heads.manage'=> 'Manage the cost-head library',
];

// Human-friendly grouping of every permission, so the access editor reads clearly
// and an admin can see at a glance which area each permission belongs to.
function permission_groups() {
    return [
        'Dashboards & sensitive figures' => ['dash.operations','dash.financial','dash.utilization','dash.people','data.credit','data.revenue','data.salary','data.profitability'],
        'Operations (calls & jobs)'      => ['ops.call.create','ops.job.allocate','ops.job.close','ops.call.delete','workforce.availability','workforce.report.approve'],
        'Inspection documentation (IDEMS)' => ['idems.finalize','idems.type.manage','idems.timestamp.edit','idems.audit.view'],
        'Money'                          => ['finance.reconcile','data.customer_money'],
        'Marketing & Sales (CRM)'        => ['crm.quote.create','crm.quote.approve','crm.quote.send','crm.followup.manage','crm.contract.register','crm.template.manage',
                                             'crm.sales.see_all','crm.targets.set','crm.deal.view','crm.deal.edit','crm.pipeline.manage',
                                             'crm.lead.export','crm.deal.export','crm.quote.export','crm.settings.manage','crm.deal.add_customer','crm.deal.delete'],
        'Project costing'                => ['costing.view','costing.prepare','costing.heads.manage'],
        'Recruitment'                    => ['hiring.admin','hiring.material_change.propose','hiring.review.clear'],
        'Identity documents (personal data)' => ['person.iddoc.view','person.iddoc.manage'],
        'Complaints & appeals'           => ['complaints.decide','capa.close','ncr.close'],
        'Administration'                 => ['master.manage','users.manage.branch','users.manage.global','org.hierarchy.view','settings.manage'],
    ];
}
// Modules grouped the same way for the view/edit matrix.
//
// The access editor renders THIS, not ACCESS_MODULES — so a module missing from
// here cannot be granted or denied by an administrator at all. That had already
// happened to five of them. The catch-all at the end makes it impossible to
// happen again: anything in the catalogue that no group claims is shown anyway,
// under a heading that says what it is.
// ---------------------------------------------------------------------------
//  WHERE EACH MODULE LIVES — ONE MAP, READ BY EVERY SCREEN THAT GROUPS MODULES.
//
//  There were two maps. The role editor (/access) had its own seven headings;
//  the per-user editor and the left-hand menu used the nine areas of the
//  navigation. Measured: **26 of the 31 modules sat under a different heading
//  on the two screens.** Invoicing and Profitability were filed under
//  "Operations" and Overheads under "Directory & masters", so an administrator
//  looking for Money on the role screen found no Money at all — which is UAT
//  1.5.4, in the owner's words: "There is no money module even when provided in
//  permission. It shows jobs and my voucher."
//
//  This is R-14 again at full scale. R-14 was one module with two names; this
//  was twenty-six modules under two filing systems. The fix is the same one that
//  held for R-14: delete the second map rather than synchronise it.
//
//  The NAVIGATION's grouping wins, because it is the one an administrator has
//  already learnt — they find a screen in the menu, then look for its
//  permission under the same heading. The headings are the nine areas exactly as
//  the menu prints them.
//
//  Grouping and labels only. This grants nothing, removes nothing, and renames
//  no permission key — only the heading a tick-box is printed under.
// ---------------------------------------------------------------------------
function access_module_areas() {
    return [
        'Sales'                   => ['leads','inquiries','quotes','crm_orders','crm_reports'],
        'Operations'              => ['calls','jobs','vouchers','reconcile'],
        'Recruitment'             => ['hiring'],
        'Reporting'               => ['idems'],
        'Money'                   => ['invoicing','profitability','overheads'],
        'Quality & Accreditation' => ['equipment','competence','impartiality','identity',
                                      'complaints','ncr','confidentiality','capa','audits','datacontrol'],
        'Insights'                => ['reports'],
        'Directory'               => ['clients','vendors','masters','portal'],
        'Admin'                   => ['users','settings'],
    ];
}

function module_groups() {
    $g = access_module_areas();
    //  A module nobody filed is SHOWN, under its own heading, rather than
    //  dropped. A permission that silently does not appear on the screen that
    //  grants it is how Money went missing in the first place.
    $claimed = array_merge(...array_values($g));
    $orphans = array_values(array_diff(array_keys(ACCESS_MODULES), $claimed));
    if ($orphans) $g['Not yet grouped'] = $orphans;
    return $g;
}

// Is a single permission relevant to THIS workspace's plan? Core permissions
// (master.manage, users.*, settings.*, org.*) are always relevant; a permission
// owned by a switched-off module is not.
function perm_licensed($perm) {
    $m = perm_product_module($perm);
    return $m === null || !function_exists('licence_enabled') || licence_enabled($m);
}
// Is an access-module (mod.<key>) relevant to this workspace's plan?
function module_key_licensed($k) {
    $m = perm_product_module('mod.' . $k . '.view');
    return $m === null || !function_exists('licence_enabled') || licence_enabled($m);
}
// The permission / module groups for the Roles & access editor, filtered to the
// modules this workspace actually has — so a recruitment company is not shown
// inspection, sales, finance or accreditation permissions it can never use.
// (Empty groups drop out entirely.)
function permission_groups_licensed() {
    $out = [];
    foreach (permission_groups() as $g => $perms) {
        $keep = array_values(array_filter($perms, 'perm_licensed'));
        if ($keep) $out[$g] = $keep;
    }
    return $out;
}
function module_groups_licensed() {
    $out = [];
    foreach (module_groups() as $g => $keys) {
        $keep = array_values(array_filter($keys, 'module_key_licensed'));
        if ($keep) $out[$g] = $keep;
    }
    return $out;
}
// Every permission — fine-grained AND per-module view/edit — arranged under the
// SAME headings as the app's main navigation (Sales, Operations, … Admin), so
// the Add/Edit-user screen reads like the menu the person will actually use
// instead of one long undivided wall of tick-boxes. Returns an ordered map of
// heading => [permission keys]. A key the view holds but no group here claims
// is shown under "Other" by the view, so nothing is ever hidden.
function permission_nav_groups() {
    // fine-grained action permissions, by nav area
    $fine = [
        'Sales'                   => ['crm.quote.create','crm.quote.approve','crm.quote.send','crm.followup.manage','crm.contract.register','crm.template.manage',
                                      'crm.sales.see_all','crm.targets.set','crm.deal.view','crm.deal.edit','crm.pipeline.manage',
                                      'crm.lead.export','crm.deal.export','crm.quote.export','crm.settings.manage','crm.deal.add_customer','crm.deal.delete',
                                      'costing.view','costing.prepare','costing.heads.manage'],
        'Operations'              => ['ops.call.create','ops.job.allocate','ops.job.close','ops.call.delete','workforce.availability','workforce.report.approve'],
        //  R-14, second half. module_groups() gave recruitment its own heading on
        //  the ROLE screen, but this map feeds the PER-USER screen and was not
        //  touched — so these three landed in "Other" and the module stayed under
        //  Operations. A business UAT screenshot of Edit user > Permissions showed
        //  exactly that. One name, one place, on both screens.
        'Recruitment'             => ['hiring.admin','hiring.material_change.propose','hiring.review.clear'],
        'Reporting'               => ['idems.finalize','idems.type.manage','idems.timestamp.edit','idems.template.approve'],
        'Money'                   => ['data.credit','data.revenue','data.salary','data.profitability','finance.reconcile','data.customer_money'],
        'Quality & Accreditation' => ['idems.audit.view','person.iddoc.view','person.iddoc.manage','complaints.decide','capa.close','ncr.close'],
        'Insights'                => ['dash.operations','dash.financial','dash.utilization','dash.people'],
        'Directory'               => ['master.manage'],
        'Admin'                   => ['users.manage.branch','users.manage.global','settings.manage','org.hierarchy.view'],
    ];
    // module view/edit access, by nav area — each key expands to .view + .edit
    //  The SAME map the role editor uses — see access_module_areas(). Two copies
    //  of this list is what put 26 of 31 modules under different headings on the
    //  two permission screens.
    $mods = access_module_areas();
    $groups = [];
    foreach ($fine as $g => $keys) $groups[$g] = $keys;
    //  R-20 — expand EVERY verb the module has, not just view and edit. The
    //  verb split created mod.<key>.add / .archive / .delete, and a permission
    //  that no heading claims is rendered under "Other". That is precisely the
    //  R-14 bug the owner caught in a screenshot, and it would have come
    //  straight back for 90-odd new permissions. perm_verb_key() is used rather
    //  than building the string, so a verb bound to an existing right (Approve,
    //  and a few Add / Delete rights) lands under its own heading instead of
    //  appearing twice under two different ones.
    foreach ($mods as $g => $keys) {
        foreach ($keys as $k) {
            foreach (access_module_verbs($k) as $v) {
                $key = perm_verb_key($k, $v);
                if (!in_array($key, $groups[$g], true)) $groups[$g][] = $key;
            }
        }
    }
    return $groups;
}

// The recommended permission set for a role (fine-grained + module view/edit).
function role_recommended_perms($role) { return role_defaults($role)['perms']; }

// ---- Module access catalogue (per-module View + Edit) ----------------------
// Each module yields two permissions: mod.<key>.view and mod.<key>.edit.
// These gate the sidebar and the module routes; the super admin can grant /
// deny any of them per role (Settings → Roles & access) or per user.
const ACCESS_MODULES = [
    // CRM / Marketing & Sales (the pre-operations funnel)
    'inquiries'     => 'CRM — Inquiries',
    'quotes'        => 'CRM — Quotations',
    'crm_orders'    => 'CRM — Orders / contracts',
    'crm_reports'   => 'CRM — Sales reports',
    // IDEMS — inspection documentation & reporting
    'idems'         => 'Inspection reports (IDEMS)',
    // Operations
    'calls'         => 'Calls',
    'jobs'          => 'Jobs',
    'vouchers'      => 'Vouchers',
    'invoicing'     => 'Invoicing',
    'profitability' => 'Profitability',
    'hiring'        => 'Recruitment',
    'reconcile'     => 'Attendance reconcile',
    'clients'       => 'Clients',
    'vendors'       => 'Vendors',
    'equipment'     => 'Equipment & calibration',
    'competence'    => 'Competence & authorisation',
    'impartiality'  => 'Impartiality & conflicts',
    'identity'      => 'Identity documents (site access)',
    'complaints'    => 'Complaints & appeals',
    //  A plain ampersand. It was stored pre-escaped, and every view escapes
    //  again on the way out, so the Roles & access grid printed
    //  "Leads &amp; pipeline" at the owner. Escaping belongs at render time,
    //  never in the data — the other thirty labels already get this right.
    'leads'         => 'Leads & pipeline',
    'ncr'           => 'Nonconformities',
    'confidentiality' => 'Confidentiality (§4.2)',
    'capa'          => 'Corrective actions',
    'audits'        => 'Internal audits & management review',
    'datacontrol'   => 'Data & information control',
    'portal'        => 'Client portal (who at the client can sign in)',
    'masters'       => 'Masters',
    'overheads'     => 'Overheads (office finance)',
    'reports'       => 'Dashboards / reports',
    'users'         => 'Users & access',
    'settings'      => 'Settings',
];

// ---- Labels that follow the company's own wording --------------------------
//  PERMISSIONS and ACCESS_MODULES are PHP constants, so their labels cannot
//  call T() where they are declared — a constant expression may not contain a
//  function call. The words are therefore fixed at the point of declaration and
//  bent here, on the way to the screen. Only the entries that actually name a
//  business noun appear below; everything else is already neutral.
function perm_label($key, $fallback = '') {
    static $m = null;
    if ($m === null) $m = [
        'data.revenue'           => 'See the revenue figure on a ' . Tl('call') . ' / ' . Tl('job'),
        'ops.call.create'        => 'Create / edit ' . Tlp('call'),
        'ops.job.allocate'       => 'Allocate / edit ' . Tlp('job'),
        'ops.job.close'          => 'Close ' . Tlp('job'),
        'ops.call.delete'        => 'Delete ' . Tlp('call'),
        'workforce.availability' => 'Manage the ' . Tl('engineer') . ' availability board',
    ];
    return $m[$key] ?? ($fallback !== '' ? $fallback : (PERMISSIONS[$key] ?? $key));
}

function access_module_label($k) {
    static $m = null;
    if ($m === null) $m = [
        'calls'   => TP('call'),
        'jobs'    => TP('job'),
        'idems'   => THP('report') . ' (IDEMS)',
        'clients' => TP('client'),
        'vendors' => TP('vendor'),
    ];
    return $m[$k] ?? (ACCESS_MODULES[$k] ?? $k);
}

// A one-line, plain-English explanation of what a permission grants and who
// typically needs it — shown as an "ⓘ" next to each tick-box on the access editor
// so whoever assigns a role understands exactly what they are handing out. Covers
// every fine-grained permission and every per-module view/edit permission.
function perm_help($key) {
    static $h = null;
    if ($h === null) {
        $call = Tl('call'); $callP = Tlp('call'); $job = Tl('job'); $jobP = Tlp('job');
        $eng = Tl('engineer'); $client = Tl('client'); $rep = Tl('report');
        $h = [
            // --- Dashboards & sensitive figures ---
            'dash.operations'  => 'See the Operations dashboard — live ' . $callP . ', ' . $jobP . ', allocation and turnaround tiles. Who: coordinators, operations & managers.',
            'dash.financial'   => 'See the Financial dashboard — money-in, invoicing and receivables tiles. Who: finance and branch / business heads.',
            'dash.utilization' => 'See the Utilization dashboard — ' . $eng . ' days used vs available. Who: managers and resourcing.',
            'dash.people'      => 'See the People & compliance dashboard — attendance, competence and HR-compliance tiles. Who: managers, HR and quality.',
            'data.credit'      => 'See the inter-office credit figures on ' . $callP . ' / ' . $jobP . '. Who: coordinators & managers who balance inter-branch work — NOT ' . Tlp('engineer') . '.',
            'data.revenue'     => 'See the revenue / billable value on a ' . $call . ' / ' . $job . '. Who: managers and finance — NOT operational staff.',
            'data.salary'      => 'See salary / loaded-cost figures. Sensitive — grant only to finance / HR / senior managers.',
            'data.profitability' => 'See profitability & margin by contract. Who: managers and above only — never coordinators or ' . Tlp('engineer') . '.',
            // --- Operations ---
            'ops.call.create'  => 'Create and edit ' . $callP . ' — raise the work and forward it to the executing office. Who: coordinators and operations.',
            'ops.job.allocate' => 'Allocate a ' . $job . ' to an ' . $eng . ' and edit it. Who: coordinators.',
            'ops.job.close'    => 'Close a ' . $job . ' — record days & expenses and lock it. Who: coordinators (and the assigned ' . $eng . ' for their own ' . $job . ').',
            'ops.call.delete'  => 'Delete a ' . $call . '. Destructive — managers only.',
            'workforce.availability'   => 'Manage the ' . $eng . ' availability board (who is free when). Who: coordinators and resourcing.',
            'workforce.report.approve' => 'Approve ' . $rep . 's as the reporting manager. Who: managers who sign off their ' . $eng . 's’ work.',
            // --- Reporting (IDEMS) ---
            'idems.finalize'   => 'Finalise / issue & lock an inspection ' . $rep . ' — stamps the signature and produces the final PDF. Who: senior ' . $eng . 's / approvers. Note: the person who APPROVED a ' . $rep . ' cannot also issue it.',
            'idems.type.manage'=> 'Design report types and the IRN numbering rules. Who: quality / administrators.',
            'idems.timestamp.edit' => 'Edit locked report timestamps. Audit-sensitive — Branch App Admin only.',
            'idems.template.approve' => 'Review & approve report FORMATS (templates) before they go live — the document-controller role. Who: quality / document control.',
            'idems.audit.view' => 'View the compliance audit log — who changed what, when. Who: quality, auditors and managers.',
            // --- Money ---
            'finance.reconcile'=> 'Reconcile credit and attendance and act on expense vouchers. Who: finance / accounts.',
            // --- Sales / CRM ---
            'crm.quote.create' => 'Create and edit quotations. Who: sales & marketing.',
            'crm.quote.approve'=> 'Approve quotations in the approval chain. Who: sales managers / designated approvers.',
            'crm.quote.send'   => 'Send a quotation to the customer. Who: sales.',
            'crm.followup.manage' => 'Manage quotation follow-ups & reminders. Who: sales.',
            'crm.contract.register' => 'Register the ' . $client . ' & contract number — turn a won quotation into a live contract and set its branch. Who: accounts / back-office.',
            'crm.template.manage' => 'Manage quotation & e-mail templates. Who: sales / marketing admins.',
            'crm.sales.see_all'   => 'On Sales home, Today, the board, Orders and Targets: look at another salesperson\'s list or "Everyone" instead of only their own. Who: sales managers and management.',
            'crm.targets.set'     => 'Set and change the sales targets of other people. Each change is kept with the figure before it. Who: sales managers and management.',
            'crm.deal.view'       => 'See deals (live pieces of business being worked) — on the board, Sales home and search. Who: sales and whoever follows the pipeline.',
            'crm.deal.edit'       => 'Create a deal, change it and move it between stages. Who: sales.',
            'crm.pipeline.manage' => 'Add, rename and reorder the company\'s pipelines and their stages. Affects everyone\'s board — grant deliberately. Who: the sales head / administrators.',
            'crm.lead.export'     => 'Download the lead list to Excel / CSV. A file can leave the company — switch it off for people who only need to work leads on screen.',
            'crm.deal.export'     => 'Download the deal list to Excel / CSV. A file can leave the company — grant deliberately.',
            'crm.quote.export'    => 'Download the quotation register to Excel / CSV. A file can leave the company — grant deliberately.',
            'crm.settings.manage' => 'Change Sales settings: follow-up outcomes and days, weekly off, when a deal is stale, and who gets new leads in each office. Not the other company settings.',
            'crm.deal.delete'     => 'Delete a deal that is not won and has no quotation (those are part of the record and are kept). It cannot be undone; the deletion is written to History with who and when. Who: administrators and the Business Director — grant deliberately.',
            'crm.deal.add_customer' => 'On a deal that was won without a lead, add its customer to the master (with GSTIN / PAN, checked against the customers already on file). A deal from a lead gets its customer by converting the lead. Who: the people who work leads and deals.',
            'data.customer_money' => 'See what customers were billed, what they paid and what is overdue — on the Sales screens (Orders, Today, Sales home, Advertising return, client register) and in Receivables. Who: finance and those who chase payment.',
            'costing.view'        => 'Open project costings — the cost build-up behind a quotation, including salary figures. Who: sales, recruitment and managers who price work.',
            'costing.prepare'     => 'Prepare and change a project costing and submit it for approval. Approving stays with the approval levels.',
            'costing.heads.manage'=> 'Change the cost-head library and costing templates (with dates from which a value applies). Affects every new costing. Who: administrators / finance head.',
            // --- Directory / masters ---
            'master.manage'    => 'Add and edit master data — ' . Tlp('client') . ', ' . Tlp('vendor') . ', lookups, offices. Who: back-office, coordinators, admins.',
            'org.hierarchy.view' => 'View the organisation hierarchy (who reports to whom). Who: managers.',
            // --- Admin ---
            'users.manage.branch' => 'Create and edit logins in your OWN office only. Who: branch managers / branch app admins.',
            'users.manage.global' => 'Create and edit ALL logins and their access, in any office. Powerful — administrators only.',
            'settings.manage'  => 'Change system settings — industry packs, terminology, numbering, integrations. Administrators only.',
            // --- Quality & Accreditation (ISO/IEC 17020) ---
            'person.iddoc.view'   => 'See that an identity document is on file (the numbers stay masked). Personal data — grant deliberately, to HR / admin with a real need.',
            'person.iddoc.manage' => 'Hold identity documents — file them, reveal a number, log a copy sent out, redact. Highly sensitive (DPDP) — a named custodian only.',
            'complaints.decide'   => 'Decide the outcome of complaints & appeals (§7.5.4 — the decider must not have been involved). Who: an independent manager / quality head.',
            'capa.close'          => 'Close a corrective action — asserts it actually worked (§8.7.3). Kept separate from doing the action. Who: quality.',
            'ncr.close'           => 'Close a nonconformity — asserts the disposition was carried out. Who: the responsible quality / coordinator.',
        ];
    }
    if (isset($h[$key])) return $h[$key];
    // Per-module view / edit permissions: generic, with a note for the sensitive ones.
    if (strncmp($key, 'mod.', 4) === 0
        && preg_match('/^mod\.(.+)\.(view|add|edit|archive|delete|approve)$/', $key, $mm)) {
        $lbl = access_module_label($mm[1]);
        $extra = [
            'users'         => ' Controls login accounts and their access — administrators only.',
            'settings'      => ' System configuration — administrators only.',
            'profitability' => ' Shows margin / profit — managers & finance only.',
            'invoicing'     => ' The billing module — finance / accounts.',
            'identity'      => ' Personal identity documents — grant deliberately.',
            'overheads'     => ' Office finance figures — finance / managers.',
        ][$mm[1]] ?? '';
        // One sentence per verb, written for somebody who has never seen a
        // permission matrix. Each says what the person can DO, not what the
        // right is called, and names what it also carries with it.
        $say = [
            'view'    => 'See the "' . $lbl . '" screens, read-only.',
            'add'     => 'Create new records in "' . $lbl . '" (also lets them view it).',
            'edit'    => 'Change existing records in "' . $lbl . '" (also lets them view it).',
            'archive' => 'Take a record in "' . $lbl . '" out of use, and put it back '
                       . '(also lets them view and change it).',
            'delete'  => 'Permanently destroy a record in "' . $lbl . '". Grant sparingly — '
                       . 'prefer Archive, which is reversible.',
            'approve' => 'Sign off work in "' . $lbl . '". Kept separate from editing so the '
                       . 'person who prepares something is not the person who approves it.',
        ][$mm[2]] ?? ('Act on "' . $lbl . '".');
        return $say . $extra;
    }
    return PERMISSIONS[$key] ?? $key;
}

// ============================================================================
//  THE VERB VOCABULARY                                                  (R-20)
// ============================================================================
//  Before this, a module produced exactly two rights: view, and one tick
//  labelled "add / edit". The owner could not say "may edit a job but never
//  delete one", because deleting was a consequence of being an administrator
//  rather than a right that could be handed out. The audit measured it:
//  docs/phase7/PERMISSION-MODEL-AUDIT.md.
//
//  Six verbs now, as decided by the owner. Two rules stop this from doubling
//  the permission count for nothing:
//
//   1. ARCHIVE IS ONE RIGHT, not Deactivate plus Activate. Whoever may take a
//      record out of use may put it back. Splitting them lets a person break
//      something and then need an administrator to undo their own slip — every
//      mistake becomes somebody else's job.
//
//   2. WHERE A WELL-NAMED RIGHT ALREADY GOVERNS THE ACT, THE VERB BINDS TO IT
//      instead of creating a duplicate. This application already had seven
//      proper sign-off rights and a handful of proper create / delete rights —
//      the one part of the old model that was right. A second permission
//      guarding the same button is a bug, not a feature: one of the two
//      inevitably gets forgotten at a call site, and then the same button is
//      protected on some screens and open on others.
// ----------------------------------------------------------------------------
const PERM_VERBS = ['view', 'add', 'edit', 'archive', 'delete', 'approve'];

//  Holding a verb necessarily grants the weaker ones. Enforced in two places on
//  purpose: the grid ticks the implied cells when you tick a strong one (so what
//  is SAVED is already closed under this), and can() falls back to it (so a set
//  saved by an older build, or by hand, still behaves). Belt and braces, because
//  "may delete but may not see" is not a state any screen should have to handle.
const PERM_VERB_IMPLIES = [
    'view'    => [],
    'add'     => ['view'],
    'edit'    => ['view'],
    'archive' => ['view', 'edit'],
    'delete'  => ['view', 'edit'],
    'approve' => ['view'],
];

//  module => verb => the right that ALREADY governs it. Anything not listed
//  here uses the generic mod.<module>.<verb>. A verb absent from both means the
//  module has no such act — Approve, mostly: an office or a holiday list is not
//  signed off by anybody, and showing an Approve tick for one would be a lie.
function perm_verb_bindings() {
    static $b = null;
    if ($b !== null) return $b;
    return $b = [
        //  ONLY APPROVE IS BOUND. Add, Edit, Archive and Delete are always the
        //  generic mod.<module>.<verb>, and the reason is a regression this very
        //  map caused once: binding Add on orders to crm.contract.register meant
        //  every role holding the edit tick on orders was auto-granted the right
        //  to register a contract — a right the permission matrix deliberately
        //  withholds from a sales manager, who owns the deal only until the quote
        //  is won. The suite caught it (test_sales_handoff).
        //
        //  The lesson generalises: crm.quote.create, ops.call.create,
        //  crm.contract.register and ops.call.delete are not "the Add verb" or
        //  "the Delete verb" wearing a different name — they are narrower
        //  business rights that happen to sit on a creating or deleting screen.
        //  They stay exactly as they are, asked by their handlers, on top of the
        //  coarse verb the gate asks. Coarse gate plus fine check is how this
        //  application already works.
        //
        //  Approve is different and is bound: a sign-off right is the whole of
        //  what the Approve column means, there is exactly one per module, and
        //  no role default hands it out — so binding it creates no duplicate and
        //  grants nobody anything. It also keeps the ISO 17020 separation of
        //  preparing from approving in the one place it is already correct.
        'quotes'      => ['approve' => 'crm.quote.approve'],
        'idems'       => ['approve' => 'idems.finalize'],
        'complaints'  => ['approve' => 'complaints.decide'],
        'hiring'      => ['approve' => 'hiring.review.clear'],
        'reconcile'   => ['approve' => 'workforce.report.approve'],
        // Closing a corrective action or a nonconformity asserts it actually
        // worked, so close is this module's Approve and stays the dedicated
        // right it already was. Note it is NOT also this module's Archive:
        // today RE-OPENING a closed nonconformity needs only the edit tick, so
        // treating Archive as ncr.close would make re-opening harder than it is
        // now and take it away from people who can do it. Archive is generic,
        // and perm_legacy_edit_also_granted() hands it to everyone whose edit
        // tick already carried it.
        'capa'        => ['approve' => 'capa.close'],
        'ncr'         => ['approve' => 'ncr.close'],
    ];
}

//  The permission key that grants <verb> on <module> — the bound right if there
//  is one, otherwise the generic one. Every caller must go through this; writing
//  "mod.$k.delete" by hand is how the duplicate-right bug gets reintroduced.
function perm_verb_key($module, $verb) {
    $bound = perm_verb_bindings()[$module][$verb] ?? null;
    return $bound ?: "mod.$module.$verb";
}

//  Which verbs this module actually has. Approve exists only where a real
//  sign-off right is bound; the other five exist everywhere.
function access_module_verbs($module) {
    $v = ['view', 'add', 'edit', 'archive', 'delete'];
    if (isset(perm_verb_bindings()[$module]['approve'])) $v[] = 'approve';
    return $v;
}

//  WHERE TODAY'S EDIT TICK ALREADY GRANTS A DESTRUCTIVE ACT.
//
//  The audit's most dangerous finding: in these four places the generic
//  "add / edit" tick IS the delete-or-archive right. Read from the code, not
//  from the labels (docs/phase7/PERMISSION-MODEL-AUDIT.md §1):
//
//    leads   edit -> deletes a lead           (lib/leads.php  leads_can_edit)
//    ncr     edit -> re-opens a closed NCR    (lib/ncr.php    ncr_can_raise)
//    audits  edit -> closes an internal audit (lib/audits.php aud_can_edit)
//    jobs    edit -> deletes a bill on a job  (lib/bills.php  job_bill_can_upload)
//
//  So the obvious migration rule — "everyone keeps View + Add + Edit, nobody
//  gets Delete until it is ticked" — would QUIETLY TAKE AWAY what these people
//  can do today. Whoever holds the edit tick on these modules must receive the
//  stronger verb too. This map is the whole reason the migration is safe, and
//  it is asserted by tests/test_permission_verb_migration.php.
function perm_legacy_edit_also_granted() {
    return [
        'leads'  => ['delete'],
        'ncr'    => ['archive'],
        'audits' => ['archive'],
        'jobs'   => ['delete'],
    ];
}

//  WHERE TODAY'S AUTHORITY IS A JOB TITLE, NOT A TICK.
//
//  Most destructive actions are guarded by is_admin_level() or
//  is_coordinator_level() rather than by any permission. is_admin_level() is
//  SEVEN roles wide, not two (MGMT_ROLES), so handing the new Delete verb only
//  to administrators would strip it from five roles that can delete today.
//
//    inquiries  delete  is_admin_level()        (lib/crm.php inquiry-delete)
//    leads      delete  is_admin_level()        (lib/opportunities.php opportunity-delete —
//                                              since 24 Nov 2026 its own switch crm.deal.delete)
//    jobs       delete  is_coordinator_level()  (lib/ops.php expense-delete, lib/bills.php bill-delete)
//
//  Granting these keeps day one identical. The owner can then UNTICK them on
//  the grid — which is the whole point of the change: narrowing becomes a
//  decision somebody makes and can see, instead of a side effect of a job title.
function perm_legacy_role_grants() {
    return [
        'inquiries' => ['delete' => 'MGMT'],
        'leads'     => ['delete' => 'MGMT'],
        'jobs'      => ['delete' => 'COORD'],
    ];
}

//  WHERE TODAY'S AUTHORITY IS A NAMED RIGHT THAT IS NOT THE EDIT TICK.
//
//  Read from each guard (docs/phase7/PERMISSION-MODEL-AUDIT.md). Without these,
//  a Branch Application Manager would lose the ability to delete a call, a
//  Senior Inspector would lose document deletion, Finance would lose expense
//  deletion, and a Coordinator would lose closing a job — each because the right
//  that authorises them today is not the module's edit tick.
function perm_legacy_right_grants() {
    return [
        'ops.call.delete'   => ['calls' => ['delete']],             // call-delete
        'ops.job.close'     => ['jobs'  => ['archive', 'delete']],  // job-close, bill-delete
        // finance.reconcile used to carry Delete on Jobs here, for expense-delete.
        // Delete implies Edit, so Finance passed every "may edit jobs" check
        // (bills, job site, hold points) against the matrix's Jobs = View. The
        // expense-delete handler admits finance.reconcile itself, and the route
        // is no longer verb-gated (perm_route_verb), so nothing is lost (owner,
        // test book SAL-000, 24 Oct 2026).
        'idems.finalize'    => ['idems' => ['delete']],             // document/endorsement-delete
        'capa.close'        => ['capa'  => ['archive']],            // capa-close
    ];
}

//  THE STAMP THAT STOPS THE MIGRATION RUNNING FOREVER.
//
//  perm_upgrade_verbs() is a ONE-TIME migration that happens to run at read
//  time. Without a way to tell "saved before the verbs existed" from "the owner
//  deliberately withheld this", it re-applied the carry-over on every single
//  read — so unticking Delete on Jobs appeared to save, and came back ticked.
//  That is not a cosmetic bug: it made the entire point of R-20 impossible.
//  A browser round-trip caught it; no unit test would have, because each
//  function was behaving exactly as written.
//
//  Every set written by the new editor carries this marker (added by
//  perm_close_implications, which every save path runs through). A set without
//  it predates the verb split and is upgraded as it is read. It is a value in
//  the list rather than a column because both stores are untyped lists — a JSON
//  array of strings, and a comma-separated string — and neither has anywhere
//  else to put it. can() never asks for it, and the editors never render it.
const PERM_VOCAB_TAG = '@verbs2';

//  Upgrade a SAVED permission set to the six-verb vocabulary.
//
//  Deliberately a read-time interpretation, not a database rewrite: it never
//  touches stored data, so it needs no downtime and cannot half-apply. A set
//  saved before the verb split is simply read correctly; one saved after it is
//  returned untouched, because every tick in it was somebody's decision.
function perm_upgrade_verbs(array $perms) {
    if (in_array(PERM_VOCAB_TAG, $perms, true)) return $perms;   // already in the new vocabulary
    $add = [];
    // The old tick was labelled "add / edit" and genuinely allowed both.
    $legacyEdit = perm_legacy_edit_also_granted();
    foreach ($perms as $p) {
        if (!preg_match('/^mod\.(\w+)\.edit$/', $p, $m)) continue;
        $add[] = perm_verb_key($m[1], 'add');
        foreach ($legacyEdit[$m[1]] ?? [] as $v) $add[] = perm_verb_key($m[1], $v);
    }
    // A named right that authorised a destructive act carries its verb across.
    foreach (perm_legacy_right_grants() as $right => $byMod) {
        if (!in_array($right, $perms, true)) continue;
        foreach ($byMod as $mod => $verbs)
            foreach ($verbs as $v) $add[] = perm_verb_key($mod, $v);
    }
    return array_values(array_unique(array_merge($perms, $add)));
}

//  Close a ticked set under PERM_VERB_IMPLIES, so what gets SAVED is already
//  consistent. Ticking Delete on Jobs saves Edit and View as well — "may delete
//  but may not see" is not a state any screen should have to render, and a
//  person who finds that in their access would reasonably think the software is
//  broken. The grid also does this live in the browser, so the ticks the
//  administrator sees match the ticks that are stored.
function perm_close_implications(array $perms) {
    $out = $perms;
    foreach ($perms as $p) {
        if (!preg_match('/^mod\.(\w+)\.(\w+)$/', $p, $m)) continue;
        foreach (PERM_VERB_IMPLIES[$m[2]] ?? [] as $weaker) $out[] = perm_verb_key($m[1], $weaker);
    }
    //  Stamp it. Every save path runs through here, so this is the one place
    //  that can promise a stored set is in the new vocabulary — and therefore
    //  that its gaps are deliberate and must never be filled in again.
    $out[] = PERM_VOCAB_TAG;
    //  …and in the vocabulary that has the switches of permission foundation A,
    //  so a gap in it is a decision and is never worked out again from old rules.
    $out[] = PERM_SPLIT_TAG;
    return array_values(array_unique($out));
}

// ============================================================================
//  PERMISSION FOUNDATION A — NINE SALES RIGHTS BECOME SWITCHES     (docs/02 19r)
// ============================================================================
//  Owner, 21 Nov 2026 ("All as recommended", proposal questions Q1–Q9). Each of
//  these used to be decided by something the administrator could not see or
//  separate: a job title (seeing other salespeople's lists, setting targets,
//  the cost-head library), the Leads edit tick (changing the company's
//  pipelines), "inter-office credit figures" (customers' billed and paid
//  amounts), "Manage system settings" (Sales settings), "anyone who can view"
//  (downloads), or one of three other modules (deals, project costing).
//
//  THE RULE THAT MADE IT SAFE: on day one each switch is on for exactly the
//  people who could already do the thing. That is not a list somebody typed —
//  it is the old rule itself, kept below, applied to each saved set as it is
//  read. The same read-time pattern as the verb split (PERM_VOCAB_TAG): a set
//  saved before this carries no '@split1' stamp, so its new switches are worked
//  out from what it already holds; a set saved after it carries the stamp, so
//  every gap in it is somebody's decision and is left alone. Nothing in the
//  database is rewritten for this, so it needs no downtime and cannot half-apply.
//
//  Proved for every role, custom roles, a saved Roles & access grid and every
//  format a person's own access has ever been stored in, gate by gate and as
//  real sign-ins: tests/test_perm_a_equivalence.php.
const PERM_SPLIT_TAG = '@split1';

//  Does a saved set hold this right, the way can() would answer it? A held
//  stronger verb carries the weaker one (Delete on Leads means View on Leads),
//  exactly as can()'s fallback does — so the old rule is evaluated faithfully.
function perm_set_has(array $set, $perm) {
    if (in_array($perm, $set, true)) return true;
    if (!preg_match('/^mod\.(.+)\.(view|edit)$/', (string) $perm, $mm)) return false;
    foreach (PERM_VERB_IMPLIES as $verb => $implies)
        if (in_array($mm[2], $implies, true) && in_array(perm_verb_key($mm[1], $verb), $set, true)) return true;
    return false;
}

//  The old rule of each new switch, as it stood on 21 Nov 2026 (c41919a). $h =
//  "does the set hold", $mgmt = the role is a management role (is_admin_level()).
//  Masters are never asked: they hold every right.
function perm_split_rules() {
    return [
        //  sx_can_see_all(): is_master() || is_admin_level()
        'crm.sales.see_all'   => fn($h, $mgmt) => $mgmt,
        //  sxt_can_set(): sx_can_see_all() && crmdash_can()
        'crm.targets.set'     => fn($h, $mgmt) => $mgmt && $h('mod.crm_reports.view'),
        //  opp_can_view() / opp_can_edit(): Leads, Quotations or Enquiries
        'crm.deal.view'       => fn($h, $mgmt) => $h('mod.leads.view') || $h('mod.quotes.view') || $h('mod.inquiries.view'),
        'crm.deal.edit'       => fn($h, $mgmt) => $h('mod.leads.edit') || $h('mod.quotes.edit') || $h('mod.inquiries.edit'),
        //  pipe_can(): Leads edit or Manage system settings
        'crm.pipeline.manage' => fn($h, $mgmt) => $h('mod.leads.edit') || $h('settings.manage'),
        //  The three downloads: whoever could open the list (its route gate).
        'crm.lead.export'     => fn($h, $mgmt) => $h('mod.leads.view'),
        'crm.deal.export'     => fn($h, $mgmt) => $h('mod.leads.view'),
        'crm.quote.export'    => fn($h, $mgmt) => $h('mod.quotes.view'),
        //  /sales-settings: is_master() || can('settings.manage')
        'crm.settings.manage' => fn($h, $mgmt) => $h('settings.manage'),
        //  Owner, 22 Nov 2026 (answer 9): the won-deal "add the customer" step was
        //  leads_can_edit() && leads_works_leads() — Leads or Enquiries edit, and Leads
        //  add or edit. That rule, kept, is who holds the switch on day one.
        'crm.deal.add_customer' => fn($h, $mgmt) => ($h('mod.leads.edit') || $h('mod.inquiries.edit')) && ($h('mod.leads.add') || $h('mod.leads.edit')),
        //  opportunity-delete: is_master() || is_admin_level() — narrowed by the owner (24 Nov
        //  2026) for the roles in ACCESS_DEAL_DELETE_NARROWED, through PERM_SPLIT_NOT_FOR.
        'crm.deal.delete'     => fn($h, $mgmt) => $mgmt,
        //  ar_can(): finance.reconcile || data.credit (|| master), with Invoicing
        'data.customer_money' => fn($h, $mgmt) => $h('finance.reconcile') || $h('data.credit'),
        //  pc_can() / pc_can_edit(): Quotations, Recruitment or Enquiries — or a
        //  management role; ch_can_manage(): a management role.
        'costing.view'        => fn($h, $mgmt) => $mgmt || $h('mod.quotes.view') || $h('mod.hiring.view') || $h('mod.inquiries.view'),
        'costing.prepare'     => fn($h, $mgmt) => $mgmt || $h('mod.quotes.edit') || $h('mod.hiring.edit'),
        'costing.heads.manage'=> fn($h, $mgmt) => $mgmt,
    ];
}

//  Bring a set saved before foundation A into its vocabulary: add each new
//  switch the old rule would have answered "yes" to. Additive only, and a set
//  stamped '@split1' is returned untouched.
//  Owner decisions that a split rule must NOT re-derive for a role (the rule itself
//  stays the historical one). D1 (owner, 21 Nov 2026): Finance reads Quotes but
//  does not see deals — so Quotes view no longer brings "View deals" to Finance.
//  An administrator may still switch it on for the role on "Who can do what".
//  Owner, 24 Nov 2026 ("as recommended"): "Delete deals" is NOT carried to the Branch
//  Manager and the SBU Head (view and download only since 21 Nov, 19x), nor to the Branch
//  App Manager and the Operation Manager, though all four are management roles. It starts
//  with the Admin and the Business Director (and the Master Admin, who holds everything).
const ACCESS_DEAL_DELETE_NARROWED = ['SBU_HEAD', 'BRANCH_MANAGER', 'BRANCH_APP_MANAGER', 'OPERATION_MANAGER'];
const PERM_SPLIT_NOT_FOR = ['crm.deal.view' => ['FINANCE'], 'crm.deal.delete' => ACCESS_DEAL_DELETE_NARROWED];
function perm_upgrade_split(array $perms, $role) {
    if (in_array(PERM_SPLIT_TAG, $perms, true)) return $perms;
    $base = function_exists('role_effective_key') ? role_effective_key(strtoupper((string) $role)) : strtoupper((string) $role);
    $mgmt = in_array($base, MGMT_ROLES, true);
    $h = fn($p) => perm_set_has($perms, $p);
    $add = [];
    foreach (perm_split_rules() as $key => $rule)
        if (!in_array($key, $perms, true) && !in_array($base, PERM_SPLIT_NOT_FOR[$key] ?? [], true) && $rule($h, $mgmt)) $add[] = $key;
    return $add ? array_values(array_merge($perms, $add)) : $perms;
}

function perm_verb_label($verb) {
    return [
        'view'    => 'View',    'add'     => 'Add',    'edit'   => 'Edit',
        'archive' => 'Archive', 'delete'  => 'Delete', 'approve'=> 'Approve',
    ][$verb] ?? $verb;
}

//  What each verb means, in the words the grid shows on hover. Written for
//  somebody who has never read a permission matrix.
function perm_verb_help($verb) {
    return [
        'view'    => 'Can open and read it.',
        'add'     => 'Can create a new one.',
        'edit'    => 'Can change one that already exists.',
        'archive' => 'Can take it out of use — and put it back.',
        'delete'  => 'Can destroy it permanently. Grant sparingly.',
        'approve' => 'Can sign it off. Deliberately separate from editing, so the '
                   . 'person who prepares something is not the person who approves it.',
    ][$verb] ?? '';
}

// Full permission map = fine-grained perms + every verb each module supports.
function all_permissions() {
    $p = [];
    foreach (PERMISSIONS as $k => $lbl) $p[$k] = perm_label($k, $lbl);
    foreach (ACCESS_MODULES as $k => $lbl) {
        $l = access_module_label($k);
        foreach (access_module_verbs($k) as $v) {
            $key = perm_verb_key($k, $v);
            // A bound right keeps the label it already had — it is the same
            // permission, shown in a new column, not a new permission.
            if (!isset($p[$key])) $p[$key] = "$l — " . strtolower(perm_verb_label($v));
        }
    }
    return $p;
}

// Which modules each role can view / edit by default (edit implies view).
// Used as the starting point; the super admin can override per role / per user.
function module_defaults($role) {
    if (function_exists('role_effective_key')) $role = role_effective_key($role);   // custom role → its base
    $edit = []; $view = [];
    $all = array_keys(ACCESS_MODULES);
    switch ($role) {
        case 'MASTER_ADMIN': case 'ADMIN': $edit = $all; break;
        case 'BUSINESS_DIRECTOR': $view = $all; break;
        case 'SBU_HEAD':
            $view = ['inquiries','quotes','crm_orders','crm_reports','idems','calls','jobs','vouchers','invoicing','profitability','hiring','reconcile','clients','vendors','masters','reports','complaints','capa','audits']; break;
        case 'BRANCH_MANAGER':
            $edit = ['calls','jobs','idems','vouchers','hiring','reconcile','clients','vendors','masters','reports','users','complaints','capa','audits','datacontrol','portal'];
            $view = ['inquiries','quotes','crm_orders','crm_reports','invoicing','profitability','overheads']; break;
        case 'BRANCH_APP_MANAGER':
            // R8 — this role holds the IDEMS config permissions (idems.type.manage,
            // idems.timestamp.edit) but had no mod.idems.view, so there was no Reporting
            // rail item and the config screens were reachable only via Admin tiles.
            $edit = ['masters','overheads','users'];
            $view = ['calls','jobs','reports','idems']; break;
        case 'OPERATION_MANAGER':
            $edit = ['calls','jobs','idems','vouchers','hiring','reconcile','complaints','capa','portal'];
            $view = ['crm_orders','clients','vendors','masters','profitability','reports']; break;
        case 'ASST_MANAGER':
            $edit = ['calls','jobs','idems','complaints'];
            $view = ['clients','vendors','reports','capa']; break;
        case 'COORDINATOR':
            $edit = ['calls','jobs','idems','vouchers','hiring','reconcile','complaints','portal'];
            $view = ['crm_orders','clients','vendors','masters','reports','invoicing','capa']; break;
        // ---- Marketing & Sales (CRM) ----
        case 'BUSINESS_DEV_MANAGER': case 'KEY_ACCOUNTS_MANAGER':
            $edit = ['inquiries','quotes','crm_orders'];
            $view = ['crm_reports','clients','reports']; break;
        case 'MARKETING_MANAGER':
            $edit = ['inquiries','quotes','crm_orders','crm_reports','clients'];
            $view = ['reports','profitability']; break;
        case 'MARKETING_EXECUTIVE':
            $edit = ['inquiries','quotes'];
            $view = ['crm_orders','crm_reports','clients']; break;
        case 'FINANCE':
            //  Jobs, calls and vouchers are money: finance bills from the first
            //  two and pays the third, so it reads all three (matrix, D-07).
            //  Inspection reports are not — they are the technical finding, with
            //  no figure on them. Carrying them made a finance login open on an
            //  operations menu, which is what UAT 1.5.4 reported.
            //  Vendors: finance pays them. The owner's answer, 2026-10-19 (Q-G): yes,
            //  Finance sees the vendor register -- it had been the one role refused,
            //  while the matrix already said View. Read only.
            //  Clients: finance invoices and chases them. The owner's answer,
            //  2026-10-19 (Q-K): yes, read only -- which is what the matrix said.
            //  D1 (owner, 21 Nov 2026, findings SH-08 "as recommended"): in Sales, Finance
            //  reads the Quotes register only (view — no create, submit, approve or send)
            //  and the Client register's normal view. Sales reports and Orders / contracts
            //  are no longer Finance's; ageing stays in Money → Receivables, contract
            //  registration on crm.contract.register (docs/02 19y).
            $edit = ['invoicing'];
            $view = ['quotes','profitability','reports','jobs','calls','vouchers','vendors','clients']; break;
        case 'QUALITY_HEAD': case 'TECHNICAL_HEAD':
            //  The owner's rule, 2026-10-18: "only Quality or Technical Head can
            //  close it". Closing a nonconformity means owning the whole loop —
            //  raise it, investigate it, record the corrective action, assert the
            //  disposition was carried out — so both modules are EDIT, not view.
            //
            //  Everything else is read-only and deliberately narrow: quality
            //  judges the work, it does not do the work. The context a
            //  nonconformity actually needs is the work order, the job and the
            //  report it came off, plus the complaints register a finding may
            //  have arrived through.
            //
            //  The accreditation registers (equipment, competence, impartiality,
            //  internal audits, data control) are NOT granted here. A quality
            //  head plausibly owns them, but that is a second decision and nobody
            //  has made it; granting ten modules nobody asked for is how a role
            //  quietly becomes an administrator. Ask before widening.
            $edit = ['ncr','capa'];
            $view = ['calls','jobs','idems','reports','complaints']; break;
        case 'INSPECTOR': case 'SR_INSPECTOR': $edit = ['idems']; break; // inspectors write reports; else My Jobs / My Voucher
    }
    //  The owner's rule, 2026-10-19 (Q-B1): "Coordinator, Manager, Branch Manager
    //  or application manager -- in short all equal to coordinator and above --
    //  can record" a nonconformity. Recording is the Add verb, which implies View
    //  (PERM_VERB_IMPLIES), so these roles also read the register. Containment,
    //  disposition, the corrective action and closing are NOT granted here: they
    //  stay with mod.ncr.edit and ncr.close (the quality roles). Inspectors are
    //  below coordinator level and are deliberately not included.
    $ncrRecord = in_array($role, ACCESS_NCR_RECORDERS, true) && !in_array('ncr', $edit, true);
    // Identity documents are never handed out by a blanket "everything" grant.
    // A Business Director gets every module by default; that is a reasonable
    // default for revenue figures and a bad one for passports, so it is taken
    // back out here and has to be granted deliberately.
    if (!in_array($role, ['MASTER_ADMIN', 'ADMIN'], true)) {
        $view = array_values(array_diff($view, ['identity']));
        $edit = array_values(array_diff($edit, ['identity']));
    }
    $out = [];
    foreach ($view as $k) $out[] = "mod.$k.view";
    if ($ncrRecord) { $out[] = 'mod.ncr.view'; $out[] = perm_verb_key('ncr', 'add'); }
    if (in_array($role, ACCESS_LEAD_WORKERS, true) && !in_array('leads', $edit, true)) { $out[] = 'mod.leads.view'; $out[] = perm_verb_key('leads', 'add'); }
    //  Owner, 21 Nov 2026 ("Yes but yes they shall be able to download"): the Branch
    //  Manager opens and downloads the leads and deals of their branch, the SBU Head
    //  of their business unit — VIEW only. Added here, not to $view, so the job-title
    //  verbs below (management may delete leads) are NOT carried onto it.
    if (in_array($role, ACCESS_LEAD_VIEWERS, true)) $out[] = 'mod.leads.view';
    //  R-20 — the old edit tick meant "add / edit", so a role that had it must
    //  now receive BOTH Add and Edit, or every role quietly loses the ability to
    //  create records. And on the four modules where the edit tick also carried
    //  a destructive act, it must receive that verb too. Nobody's reach changes.
    $legacy = perm_legacy_edit_also_granted();
    //  Administrators hold EVERY verb. This is not generosity: before R-20,
    //  deleting was guarded by is_admin_level() rather than by a tick, so an
    //  administrator could already delete. If the new Delete right were withheld
    //  from them, the migration would TAKE AWAY an ability they have today —
    //  the exact failure the no-loss rule exists to prevent.
    $isAdmin = in_array($role, ['MASTER_ADMIN', 'ADMIN'], true);
    foreach ($edit as $k) {
        $out[] = "mod.$k.view";
        if ($isAdmin) {
            foreach (access_module_verbs($k) as $v) $out[] = perm_verb_key($k, $v);
            continue;
        }
        $out[] = perm_verb_key($k, 'add');
        $out[] = perm_verb_key($k, 'edit');
        foreach ($legacy[$k] ?? [] as $v) $out[] = perm_verb_key($k, $v);
    }
    //  Verbs a job title grants today. Scoped to modules the role can already
    //  see, so this widens nobody's reach into a module they have no business in
    //  — it only names a power they already hold.
    $isMgmt  = in_array($role, MGMT_ROLES, true);
    $isCoord = $isMgmt || in_array($role, ['ASST_MANAGER', 'COORDINATOR'], true);
    $seen    = array_merge($view, $edit);
    foreach (perm_legacy_role_grants() as $k => $byVerb) {
        if (!in_array($k, $seen, true)) continue;
        foreach ($byVerb as $v => $who) {
            if ($who === 'MGMT'  && !$isMgmt)  continue;
            if ($who === 'COORD' && !$isCoord) continue;
            $out[] = perm_verb_key($k, $v);
        }
    }
    return array_values(array_unique($out));
}

// ---- Modules that did not exist when somebody last saved their access -------
//
// A saved role or per-user permission set is a list of what was ticked. A module
// added later is simply absent from that list — indistinguishable, on the face
// of it, from a module that was deliberately untieked. Get this wrong in one
// direction and a new module is invisible for ever; get it wrong in the other
// and unticking a module silently un-does itself.
//
// So the saved set carries a companion: the list of modules that existed at the
// moment it was saved. Anything not on that list is genuinely new and gets the
// role's default. Nothing is guessed.
//
// The fallback below is for installs that saved their access before this
// companion list existed. It names every module added after the access editor
// first shipped — and it is a one-off catch-up, not a list to keep extending:
// from now on modules_at_last_save() answers the question by itself.
const NEW_MODULES = ['inquiries', 'quotes', 'crm_orders', 'crm_reports', 'idems',
                     'equipment', 'competence', 'impartiality', 'identity', 'complaints',
                     'capa', 'audits', 'datacontrol'];

// Snapshot the module catalogue. Called whenever a role's access is saved.
function stamp_modules_at_save() {
    setting_set('role_access_modules', implode(',', array_keys(ACCESS_MODULES)));
}
function modules_at_last_save() {
    $raw = trim((string)setting_get('role_access_modules', ''));
    if ($raw === '') return null;                     // saved before the stamp existed
    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

//  THE PERSON'S OWN SET REMEMBERS WHICH MODULES EXISTED WHEN IT WAS SAVED.
//
//  modules_at_last_save() is stamped only when Roles & access is saved. An
//  install that never saved it (the owner's live site, and every test copy)
//  answered null, so every module on NEW_MODULES — Enquiries, Quotes, Orders,
//  Sales reports, Reports, Complaints … — counted as "new" for every person,
//  and the role's default ticks came back on every read. Unticking anything
//  in those modules never stuck (owner, test book SAL-000: "if we add any
//  other permission or delete any permission it still … remains default").
//
//  So a per-user set now carries its own '@mods=a|b|c' stamp, written by
//  user_perms_to_store(). A set saved by the verb-era editor ('@verbs2') but
//  before this stamp was saved after every NEW_MODULES entry existed, so none
//  of those is new for it either.
const PERM_MODS_TAG = '@mods=';
function perm_mods_stamp(array $perms) {
    $perms = array_values(array_filter($perms, fn($p) => strncmp((string) $p, PERM_MODS_TAG, strlen(PERM_MODS_TAG)) !== 0));
    $perms[] = PERM_MODS_TAG . implode('|', array_keys(ACCESS_MODULES));
    return $perms;
}
function perm_mods_known(array $perms) {
    foreach ($perms as $p)
        if (strncmp((string) $p, PERM_MODS_TAG, strlen(PERM_MODS_TAG)) === 0)
            return array_values(array_filter(explode('|', substr((string) $p, strlen(PERM_MODS_TAG)))));
    return null;
}
function perm_strip_bookkeeping(array $perms) {
    return array_values(array_filter($perms, fn($p) => strncmp((string) $p, PERM_MODS_TAG, strlen(PERM_MODS_TAG)) !== 0));
}

function merge_new_module_defaults($perms, $role) {
    $known = perm_mods_known($perms) ?? modules_at_last_save();
    $legacyNew = ($known === null && in_array(PERM_VOCAB_TAG, $perms, true)) ? [] : NEW_MODULES;
    foreach (module_defaults($role) as $dp) {
        // R-20 — must accept every verb, or a module added later would grant
        // only view/edit and silently withhold Add, Archive and Delete.
        if (!preg_match('/^mod\.(\w+)\.(view|add|edit|archive|delete|approve)$/', $dp, $m)) continue;
        $isNew = $known === null ? in_array($m[1], $legacyNew, true) : !in_array($m[1], $known, true);
        if ($isNew && !in_array($dp, $perms, true)) $perms[] = $dp;
    }
    return array_values(array_unique($perms));
}

// Effective default permission set for a role: a super-admin override stored in
// settings (Roles & access) wins; otherwise the built-in role defaults.
function role_perms($role) {
    if (function_exists('role_effective_key')) $role = role_effective_key($role);   // custom role → its base
    $raw = setting_get('role_access', '');
    if ($raw !== '') {
        $ov = json_decode($raw, true);
        //  A stored override was saved under the two-verb vocabulary, so it is
        //  upgraded as it is read. Read-time, never rewritten: idempotent, no
        //  downtime, and it cannot half-apply.
        if (is_array($ov) && isset($ov[$role]) && is_array($ov[$role]))
            return perm_upgrade_split(perm_upgrade_verbs(merge_new_module_defaults(array_values($ov[$role]), $role)), $role);
    }
    return role_defaults($role)['perms'];
}

// Grant a single permission to a role, preserving everything the role already
// has (seeds the override from the role's current effective set the first time,
// so nothing is lost). Non-destructive; used by one-click setup presets.
function role_grant_perm($role, $perm) {
    if (!isset(ORG_ROLES[$role]) || !array_key_exists($perm, PERMISSIONS)) return false;
    $raw = setting_get('role_access', '');
    $store = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($store)) $store = [];
    $cur = (isset($store[$role]) && is_array($store[$role])) ? array_values($store[$role]) : role_perms($role);
    if (!in_array($perm, $cur, true)) $cur[] = $perm;
    $store[$role] = array_values(array_unique($cur));
    setting_set('role_access', json_encode($store));
    return true;
}

// Role defaults: permissions granted, and scope (offices/sbus: ALL | OWN).
// Wrapper merges the per-module view/edit defaults onto the fine-grained ones.
function role_defaults($role) {
    $d = role_defaults_base($role);
    $d['perms'] = array_values(array_unique(array_merge($d['perms'], module_defaults($role))));
    //  R-20 — the single place where the role's fine-grained rights and its
    //  module verbs are both in hand, so it is the only place the right-level
    //  carry-over can be worked out: a Branch Application Manager holds
    //  ops.call.delete from its own definition, and that is what entitles it to
    //  Delete on Calls. module_defaults() cannot see fine rights, so doing it
    //  there would have missed exactly these people.
    $d['perms'] = perm_upgrade_verbs($d['perms']);
    //  Foundation A — the role's new switches, from its own rights (docs/02 19r).
    $d['perms'] = perm_upgrade_split($d['perms'], $role);
    return $d;
}

// The effective permission set for a given user ROW (not the current session) —
// the same resolution ua() applies, factored out so the access editor can pre-tick
// exactly what a login actually has (R3). Master = everything; a per-user override
// wins (with the legacy module back-fill); otherwise the role's effective set.
function user_effective_perms($u) {
    $role = !empty($u['is_superuser']) ? 'MASTER_ADMIN' : strtoupper($u['role'] ?? 'ADMIN');
    if (function_exists('role_effective_key')) $role = role_effective_key($role);   // custom role → its base (so it never falls through to ADMIN)
    if (!isset(ORG_ROLES[$role])) $role = 'ADMIN';
    if ($role === 'MASTER_ADMIN') return array_keys(all_permissions());
    return perm_resolve_stored((string)($u['permissions'] ?? ''), $role);
}

//  What a person's stored value grants, for a role (already resolved to its base).
//  Three shapes, read the same way everywhere (the editor, the audit, can()):
//    ''                → follows the role exactly;
//    '@diff,+a,-b'     → the role, plus a, minus b (Q11, owner 21 Nov 2026) — so a
//                        later change to the role still reaches the person;
//    anything else     → an older FROZEN copy, read as it always was.
function perm_resolve_stored($csv, $role) {
    $csv = trim((string) $csv);
    if ($csv === '') return role_perms($role);
    $perms = array_values(array_filter(array_map('trim', explode(',', $csv)), 'strlen'));
    if (in_array(PERM_DIFF_TAG, $perms, true)) {
        $plus = []; $minus = [];
        foreach ($perms as $p) {
            if ($p[0] === '+') $plus[] = substr($p, 1);
            elseif ($p[0] === '-') $minus[] = substr($p, 1);
        }
        $eff = array_values(array_unique(array_merge(role_perms($role), $plus)));
        return perm_strip_bookkeeping(array_values(array_diff($eff, $minus)));
    }
    $hasMod = false; foreach ($perms as $p) if (strncmp($p, 'mod.', 4) === 0) { $hasMod = true; break; }
    if (!$hasMod) $perms = array_merge($perms, module_defaults($role));   // pre-module logins keep module access
    else $perms = merge_new_module_defaults($perms, $role);               // grant brand-new modules
    //  R-20 — a per-user set stored before the verb split says "mod.X.edit",
    //  which meant add AND edit, and on four modules carried a destructive
    //  act as well. Upgrading it here is what keeps that person able to do
    //  on Monday exactly what they did on Friday. Foundation A the same way:
    //  a copy frozen before 21 Nov 2026 gets the new switches its rights
    //  already gave (perm_upgrade_split).
    return perm_strip_bookkeeping(perm_upgrade_split(perm_upgrade_verbs(array_values(array_unique($perms))), $role));
}

// ---- Q11 (owner, 21 Nov 2026): a person's own access = the role + differences
//
//  A person given their own access used to be stored as a full copy of the
//  ticks, frozen at that moment — so allowing "Send quotations" for every
//  Marketing Executive later did not reach the one who had a single exception.
//  It is now stored as what differs from the role: '+right' given beyond it,
//  '-right' taken away from it. Everything else follows the role, today and
//  after any later change to it.
const PERM_DIFF_TAG = '@diff';

//  The value to store so that a person has exactly $effective under $role:
//  '' when that is the role's own set, else '@diff,+…,-…'. Bookkeeping stamps
//  ('@…') are not rights and never become a difference.
function perm_diff_from(array $effective, $role) {
    $real = fn($a) => array_values(array_unique(array_filter($a, fn($p) => $p !== '' && $p[0] !== '@')));
    $want = $real($effective);
    $have = $real(role_perms($role));
    $plus = array_values(array_diff($want, $have)); sort($plus);
    $minus = array_values(array_diff($have, $want)); sort($minus);
    if (!$plus && !$minus) return '';
    return implode(',', array_merge([PERM_DIFF_TAG], array_map(fn($p) => '+' . $p, $plus), array_map(fn($p) => '-' . $p, $minus)));
}

//  ONE-TIME, ON THE LIVE DATA: every person still on a frozen copy is moved to
//  "role + differences", worked out so that what they can do is IDENTICAL before
//  and after — checked person by person before anything is written, and skipped
//  (left as it is) if it would not be. The value that was there is kept on the
//  sealed audit chain. Idempotent: once moved, a person is never moved again, and
//  the flag stops the scan from running on every request.
const ACCESS_USER_DIFF_FLAG = 'access_user_diff_q11_applied';
function access_migrate_user_diffs($force = false) {
    static $ran = false;
    if (($ran && !$force) || !function_exists('setting_get') || !function_exists('setting_set')) return 0;
    $ran = true;
    try {
        if (!$force && (string) setting_get(ACCESS_USER_DIFF_FLAG, '') !== '') return 0;
        $n = 0; $kept = 0;
        $real = function ($a) { $a = array_values(array_unique(array_filter($a, fn($p) => $p !== '' && $p[0] !== '@'))); sort($a); return $a; };
        foreach (ops_all("SELECT id, username, role, is_superuser, permissions FROM users
                          WHERE COALESCE(permissions,'')<>'' AND COALESCE(is_superuser,0)=0") ?: [] as $u) {
            $csv = (string) $u['permissions'];
            if (strpos($csv, PERM_DIFF_TAG) !== false) continue;          // already moved
            $role = strtoupper((string) $u['role']);
            if (function_exists('role_effective_key')) $role = role_effective_key($role);
            if (!isset(ORG_ROLES[$role])) $role = 'ADMIN';
            if ($role === 'MASTER_ADMIN') continue;
            $before = $real(user_effective_perms($u));
            $new = perm_diff_from($before, $role);
            $after = $real(perm_resolve_stored($new, $role));
            if ($after !== $before) { $kept++; continue; }               // would change what they can do: leave it
            db()->prepare("UPDATE users SET permissions=? WHERE id=?")->execute([$new, (int) $u['id']]);
            if (function_exists('idems_log')) try {
                idems_log('user', (int) $u['id'], 'ACCESS_STORED_AS_DIFFERENCES',
                    ['field' => (string) $u['username'], 'old' => $csv, 'new' => $new,
                     'reason' => 'Owner decision 21 Nov 2026 (Q11): own access stored as differences from the role; what the person can do is unchanged']);
            } catch (Throwable $e) {}
            $n++;
        }
        setting_set(ACCESS_USER_DIFF_FLAG, date('c') . ' (' . $n . ' moved' . ($kept ? ', ' . $kept . ' kept as they were' : '') . ')');
        return $n;
    } catch (Throwable $e) { return 0; }   // never block a sign-in over this; it retries next request
}

//  WHAT A ROLE GIVES, AND WHEN A PERSON FOLLOWS IT.
//
//  A person either FOLLOWS their role (no per-user set stored: they get the
//  role's access as Roles & permissions defines it today, including anything
//  added later) or has their OWN access (a stored set that replaces the role's).
//
//  The user form used to store whatever was ticked, every time. A new person's
//  screen arrived pre-ticked with the Coordinator's access whatever role was
//  picked, so a Marketing Executive saved untouched got a Coordinator's
//  invoicing, vendors and credit figures — and lost "Create / edit quotations",
//  which a Coordinator does not hold (owner's video SAL_002, 20 Oct 2026). An
//  ordinary edit froze a person on a copy of their role, so a later change
//  under Roles & permissions silently passed them by.
//
//  role_perm_set() is the one answer to "what does this role give", shared by
//  the form (what it pre-ticks) and the save (what it compares against), so the
//  two cannot drift. user_perms_to_store() decides what is stored: '' when the
//  ticks are exactly the role's, so the person keeps following it.
function role_perm_set($role) {
    return perm_close_implications(array_values(array_unique(role_perms($role))));
}

// $chosen      what the editor ticked (already limited to $assignable)
// $assignable  the permissions this editor can see and change
// $existingCsv the person's stored set ('' = follows the role, or a new person)
// Returns '' (follow the role) or the comma list to store.
function user_perms_to_store(array $chosen, $role, array $assignable, $existingCsv = '') {
    if (function_exists('role_effective_key')) $role = role_effective_key(strtoupper((string) $role));
    $chosen  = perm_close_implications(array_values(array_unique(array_filter($chosen))));
    $roleSet = role_perm_set($role);
    $inside  = function ($s) use ($assignable) { $x = array_values(array_unique(array_intersect($s, $assignable))); sort($x); return $x; };
    $outside = function ($s) use ($assignable) { $x = array_values(array_unique(array_diff($s, $assignable))); sort($x); return $x; };
    // What the person keeps beyond this editor's view (R3 — an editor never takes
    // away what they cannot see): what they hold there now if they have their own
    // access, else the part their role gives them there today.
    // Nothing ticked has always meant "the role's access" — an empty set cannot
    // be stored (empty IS "follow the role"). The screen says so before saving;
    // taking somebody's access away is Deactivate, not unticking every box.
    if ($inside($chosen) === []) return '';
    $existing = trim((string) $existingCsv) !== '' ? perm_close_implications(perm_resolve_stored((string) $existingCsv, $role)) : [];
    $keep = $existing ? $outside($existing) : $outside($roleSet);
    //  Q11 — stored as the differences from the role ('' when there are none), so
    //  a later change to the role still reaches this person.
    $want = perm_close_implications(array_values(array_unique(array_merge($inside($chosen), $keep))));
    $real = function ($a) { $a = array_values(array_unique(array_filter($a, fn($p) => $p !== '' && $p[0] !== '@'))); sort($a); return $a; };
    if ($real($want) === $real($roleSet)) return '';
    return perm_diff_from($want, $role);
}

// True when a person has their OWN access that differs from what their role
// gives — shown on the user register so an administrator can see who departs
// from the permission matrix, and why the role's rules do not reach them.
function user_has_own_access($u) {
    if (!empty($u['is_superuser']) || trim((string) ($u['permissions'] ?? '')) === '') return false;
    static $memo = [];
    $role = strtoupper((string) ($u['role'] ?? ''));
    if (!isset($memo[$role])) { $r = role_perm_set($role); sort($r); $memo[$role] = $r; }
    $mine = perm_close_implications(user_effective_perms($u)); $mine = array_values(array_unique($mine)); sort($mine);
    return $mine !== $memo[$role];
}

// Module 02 — a plain-English diff of two users' RESOLVED access, for the audit
// trail. Returns [] when nothing that matters to authorization changed, so a save
// that touched only a name or a phone number writes no access-audit entry.
function access_diff($oldRow, $newRow) {
    $oldP = $oldRow ? user_effective_perms($oldRow) : [];
    $newP = user_effective_perms($newRow);
    $out = [];
    if ((string)($oldRow['role'] ?? '') !== (string)($newRow['role'] ?? ''))
        $out['role'] = [(string)($oldRow['role'] ?? ''), (string)($newRow['role'] ?? '')];
    $granted = array_values(array_diff($newP, $oldP));
    $revoked = array_values(array_diff($oldP, $newP));
    if ($granted) $out['granted'] = $granted;
    if ($revoked) $out['revoked'] = $revoked;
    foreach (['scope_offices' => 'offices', 'scope_sbus' => 'business units'] as $k => $lbl)
        if ((string)($oldRow[$k] ?? '') !== (string)($newRow[$k] ?? ''))
            $out['scope_' . $lbl] = [(string)($oldRow[$k] ?? ''), (string)($newRow[$k] ?? '')];
    if ((int)($oldRow['is_superuser'] ?? 0) !== (int)($newRow['is_superuser'] ?? 0))
        $out['master'] = [(int)($oldRow['is_superuser'] ?? 0), (int)($newRow['is_superuser'] ?? 0)];
    if ((int)($oldRow['is_active'] ?? 1) !== (int)($newRow['is_active'] ?? 1))
        $out['active'] = [(int)($oldRow['is_active'] ?? 1), (int)($newRow['is_active'] ?? 1)];
    return $out;
}

// The permissions an administrator is allowed to TOGGLE on the access editor. A
// global user-manager may set anything; a branch-level manager may set only a safe
// operational subset (never salary / global-user / settings). Used by BOTH the form
// (what it renders) and the save handler (what it accepts + what it must preserve),
// so the two can never drift and silently drop a permission the editor never saw.
// Which sellable MODULE a permission belongs to, or null when it is always
// available (administration, users, settings, org hierarchy, people dashboards).
// Used to hide permissions for modules a company has not licensed, so the access
// editor shows only what the company can actually use.
function perm_product_module($perm) {
    if (strncmp($perm, 'mod.', 4) === 0) {
        $p = explode('.', $perm);
        return function_exists('licence_owner') ? licence_owner($p[1] ?? '') : null;   // via the access-module's owner
    }
    static $exact = [
        'data.credit' => 'money', 'data.revenue' => 'money', 'data.salary' => 'money', 'data.profitability' => 'money',
        'data.customer_money' => 'money',
        'dash.operations' => 'operations', 'dash.financial' => 'money',
    ];
    if (isset($exact[$perm])) return $exact[$perm];
    static $pre = [
        'crm.' => 'sales', 'ops.' => 'operations', 'workforce.' => 'operations', 'idems.' => 'reporting',
        'finance.' => 'money', 'hiring.' => 'hr',
        'complaints.' => 'operations', 'capa.' => 'operations', 'ncr.' => 'operations', 'person.iddoc' => 'operations',
    ];
    foreach ($pre as $k => $m) if (strncmp($perm, $k, strlen($k)) === 0) return $m;
    return null;   // master.manage, users.*, settings.manage, org.*, dash.utilization, dash.people → always available
}

// Drop permissions whose owning module is switched off for this company. can()
// already refuses them; hiding them from the editor removes the confusion of
// showing Sales / Reporting / Money / Quality to a company that has none of them.
function permissions_drop_unlicensed(array $perms) {
    if (!function_exists('licence_enabled')) return $perms;
    foreach (array_keys($perms) as $k) {
        $m = perm_product_module($k);
        if ($m !== null && !licence_enabled($m)) unset($perms[$k]);
    }
    return $perms;
}

function assignable_permissions($globalMgr) {
    if ($globalMgr) return permissions_drop_unlicensed(all_permissions());
    $keys = ['mod.calls.view','mod.calls.edit','mod.jobs.view','mod.jobs.edit','mod.vouchers.view','mod.vouchers.edit',
        'mod.hiring.view','mod.hiring.edit','mod.reconcile.view','mod.reconcile.edit','mod.clients.view','mod.vendors.view',
        'mod.masters.view','mod.masters.edit','mod.reports.view','mod.invoicing.view',
        'dash.operations','dash.utilization','data.credit','ops.call.create','ops.job.allocate','ops.job.close','master.manage'];
    return permissions_drop_unlicensed(array_intersect_key(all_permissions(), array_flip($keys)));
}
function role_defaults_base($role) {
    if (function_exists('role_effective_key')) $role = role_effective_key($role);   // custom role → its base
    $all = array_keys(PERMISSIONS);
    switch ($role) {
        case 'MASTER_ADMIN': case 'ADMIN':
            return ['perms' => $all, 'offices' => 'ALL', 'sbus' => 'ALL'];
        case 'BUSINESS_DIRECTOR':
            return ['perms' => ['dash.operations','dash.financial','dash.utilization','dash.people','data.credit','data.revenue','data.salary','data.profitability','org.hierarchy.view','idems.audit.view'], 'offices' => 'ALL', 'sbus' => 'ALL'];
        case 'SBU_HEAD':
            return ['perms' => ['dash.operations','dash.financial','dash.utilization','dash.people','data.credit','data.revenue','data.salary','data.profitability','workforce.report.approve','org.hierarchy.view','idems.finalize','idems.audit.view'], 'offices' => 'ALL', 'sbus' => 'OWN'];
        case 'BRANCH_MANAGER':
            return ['perms' => ['dash.operations','dash.financial','dash.utilization','dash.people','data.credit','data.revenue','data.salary','data.profitability','ops.call.create','ops.job.allocate','ops.job.close','workforce.availability','workforce.report.approve','master.manage','users.manage.branch','org.hierarchy.view','idems.finalize','idems.audit.view'], 'offices' => 'OWN', 'sbus' => 'ALL'];
        case 'BRANCH_APP_MANAGER':
            // The Branch Application Manager is the only role that may edit locked timestamps.
            return ['perms' => ['dash.operations','dash.utilization','users.manage.branch','master.manage','ops.call.delete','org.hierarchy.view','idems.type.manage','idems.timestamp.edit','idems.audit.view'], 'offices' => 'OWN', 'sbus' => 'ALL'];
        case 'OPERATION_MANAGER':
            // "manager under the branch manager" — may see contract profitability
            return ['perms' => ['dash.operations','dash.utilization','data.revenue','data.profitability','ops.call.create','ops.job.allocate','ops.job.close','workforce.availability','workforce.report.approve','idems.finalize'], 'offices' => 'OWN', 'sbus' => 'OWN'];
        case 'ASST_MANAGER':
            return ['perms' => ['dash.operations','ops.call.create','ops.job.allocate','workforce.availability'], 'offices' => 'OWN', 'sbus' => 'OWN'];
        case 'COORDINATOR':
            // per decision: Operations + read-only revenue (financial section visible, but no salary/profit)
            return ['perms' => ['dash.operations','dash.financial','data.credit','ops.call.create','ops.job.allocate','ops.job.close','workforce.availability'], 'offices' => 'OWN', 'sbus' => 'OWN'];
        case 'BUSINESS_DEV_MANAGER': case 'KEY_ACCOUNTS_MANAGER':
            return ['perms' => ['dash.operations','dash.financial','data.credit','crm.quote.create','crm.quote.send','crm.followup.manage'], 'offices' => 'OWN', 'sbus' => 'ALL'];
        case 'MARKETING_MANAGER':
            //  crm.targets.set — DEF-11 (owner, 21 Nov 2026, A): holders of quotation
            //  approval set targets too, for the people of their own branches.
            return ['perms' => ['dash.operations','dash.financial','data.credit','data.revenue','data.profitability','crm.quote.create','crm.quote.approve','crm.quote.send','crm.followup.manage','crm.template.manage','crm.targets.set'], 'offices' => 'OWN', 'sbus' => 'ALL'];
        case 'MARKETING_EXECUTIVE':
            return ['perms' => ['crm.quote.create','crm.followup.manage'], 'offices' => 'OWN', 'sbus' => 'OWN'];
        case 'FINANCE':
            //  Finance and the Marketing Manager are central teams whose reach is
            //  chosen PER PERSON (owner, 16 Nov 2026, change plan 0.1): they see
            //  the offices ticked on their record — "All offices" ticked for the
            //  company-wide picture — or else their home office. Their rights
            //  (finance data / sales data) are unchanged. Everyone who held these
            //  roles when this shipped was given "All offices" once
            //  (access_migrate_central_roles_scope()), so nobody lost anything.
            return ['perms' => ['dash.financial','data.credit','data.revenue','data.salary','data.profitability','finance.reconcile','crm.contract.register'], 'offices' => 'OWN', 'sbus' => 'ALL'];
        case 'QUALITY_HEAD': case 'TECHNICAL_HEAD':
            //  ncr.close and capa.close are the two rights the owner named on
            //  2026-10-18. idems.audit.view is what makes a closure defensible to
            //  an assessor: you cannot assert that a disposition was carried out
            //  without being able to see what actually happened.
            //
            //  Scope is ALL / ALL on purpose. A nonconformity raised in one branch
            //  is the company's nonconformity; a quality function scoped to its
            //  own office could not close it.
            return ['perms' => ['ncr.close','capa.close','idems.audit.view','dash.operations'], 'offices' => 'ALL', 'sbus' => 'ALL'];
        case 'INSPECTOR':
            return ['perms' => [], 'offices' => 'OWN', 'sbus' => 'OWN'];
        case 'SR_INSPECTOR':
            // A senior inspector is an inspector who may also be named an approver
            // (that works by being the resolved approver — no extra permission), and
            // may vet reports. Kept least-privilege otherwise.
            return ['perms' => ['idems.finalize'], 'offices' => 'OWN', 'sbus' => 'OWN'];
    }
    return ['perms' => [], 'offices' => 'OWN', 'sbus' => 'OWN'];
}

// ---- Effective access for the current user (cached) ------------------------
// ---- Owner's rules that must reach a SAVED access grid ---------------------
// A role default reaches only roles nobody has customised: once an administrator
// saves Settings -> Roles & access, the stored grid wins, and a per-user override
// wins over that. So a rule the owner gives ("coordinator and above may record a
// nonconformity") is also written ONCE into every stored grid and per-user set it
// applies to. Once: the rule's id is recorded, so an administrator who later
// unticks it on purpose is not overruled on the next page load. Additive only --
// nothing is ever removed here.
function access_owner_grants() {
    return [
        'Q-B1-2026-10-19' => ['perms' => ['mod.ncr.view', 'mod.ncr.add'], 'roles' => ACCESS_NCR_RECORDERS],
        'Q-G-2026-10-19'  => ['perms' => ['mod.vendors.view'],            'roles' => ['FINANCE']],
        'Q-K-2026-10-19'  => ['perms' => ['mod.clients.view'],            'roles' => ['FINANCE']],
        'D-01-2026-10-20' => ['perms' => ['mod.leads.view', 'mod.leads.add'], 'roles' => ACCESS_LEAD_WORKERS],
        //  Owner, 21 Nov 2026 ("Yes but yes they shall be able to download"): the Branch
        //  Manager and the SBU Head open, read and download the leads and deals of their
        //  branch / business unit. View and download only — never add, edit or move.
        'BM-SBU-LEADS-2026-11-21' => ['perms' => ['mod.leads.view', 'crm.deal.view', 'crm.lead.export', 'crm.deal.export'],
                                      'roles' => ACCESS_LEAD_VIEWERS],
        //  DEF-11 (owner, 21 Nov 2026, A): "Set sales targets for others" for the roles
        //  that hold quotation approval or lead set-up by default and are not already
        //  management — the Marketing Manager. For the people of their own branches
        //  (sxt_people_for_setter()).
        'DEF-11-2026-11-21' => ['perms' => ['crm.targets.set'], 'roles' => ['MARKETING_MANAGER']],
        //  Owner, 22 Nov 2026 (answer 9): the new switch "Add a customer from a won deal
        //  that had no lead" reaches a set SAVED after foundation A (stamped @split1, so the
        //  split rule above no longer reads it) on exactly the old rule — never by role.
        'ADD-CUSTOMER-SWITCH-2026-11-22' => ['perms' => ['crm.deal.add_customer'],
                                             'rule' => fn($h) => perm_split_rules()['crm.deal.add_customer']($h, false)],
        //  Owner, 24 Nov 2026: "Delete deals" reaches a grid SAVED after foundation A (stamped
        //  @split1, which the split rule no longer reads) for exactly the roles it starts with.
        //  Nobody else ever held the switch, so nothing has to be taken away (no revoke).
        'DEAL-DELETE-SWITCH-2026-11-24' => ['perms' => ['crm.deal.delete'], 'roles' => ['ADMIN', 'BUSINESS_DIRECTOR']],
    ];
}
//  Does owner grant $g apply to a set held by a person / role of base role $role?
//  By its role list, or (a rule-based grant) by what the set already holds.
function access_owner_grant_applies(array $g, $role, array $set) {
    if (isset($g['roles'])) return in_array($role, $g['roles'], true);
    if (isset($g['rule']) && is_callable($g['rule'])) return (bool) ($g['rule'])(fn($p) => perm_set_has($set, $p));
    return false;
}
function access_apply_owner_grants($force = false) {
    static $ran = false;
    if (($ran && !$force) || !function_exists('setting_get') || !function_exists('setting_set')) return [];
    $ran = true;
    try {
        $applied = array_values(array_filter(array_map('trim', explode(',', (string)setting_get('access_owner_grants_applied', '')))));
        $pending = array_diff_key(access_owner_grants(), array_flip($applied));
        if (!$pending) return [];
        $base = fn($r) => function_exists('role_effective_key') ? role_effective_key(strtoupper((string)$r)) : strtoupper((string)$r);
        $raw = (string)setting_get('role_access', '');
        $store = $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($store)) {
            foreach ($pending as $g)
                foreach ($store as $roleKey => $perms)
                    if (is_array($perms) && access_owner_grant_applies($g, $base($roleKey), $perms))
                        $store[$roleKey] = array_values(array_unique(array_merge($perms, $g['perms'])));
            setting_set('role_access', json_encode($store));
        }
        foreach (ops_all("SELECT id, role, permissions FROM users WHERE COALESCE(permissions,'')<>'' AND COALESCE(is_superuser,0)=0") ?: [] as $u) {
            $grant = [];
            //  A rule-based grant reads what the person actually holds (role + differences
            //  resolved, or the frozen copy itself).
            $held = strpos((string) $u['permissions'], PERM_DIFF_TAG) !== false
                ? perm_resolve_stored((string) $u['permissions'], $base($u['role']))
                : array_values(array_filter(explode(',', (string) $u['permissions'])));
            foreach ($pending as $g) if (access_owner_grant_applies($g, $base($u['role']), $held)) $grant = array_merge($grant, $g['perms']);
            if (!$grant) continue;
            if (strpos((string) $u['permissions'], PERM_DIFF_TAG) !== false) {
                //  Stored as differences (Q11): the person's access plus the rule.
                $eff = array_values(array_unique(array_merge(perm_resolve_stored((string) $u['permissions'], $base($u['role'])), $grant)));
                $newCsv = perm_diff_from($eff, $base($u['role']));
                if ($newCsv !== (string) $u['permissions']) db()->prepare("UPDATE users SET permissions=? WHERE id=?")->execute([$newCsv, (int)$u['id']]);
                continue;
            }
            $perms = array_values(array_filter(explode(',', (string)$u['permissions'])));
            $new = array_values(array_unique(array_merge($perms, $grant)));
            if ($new !== $perms) db()->prepare("UPDATE users SET permissions=? WHERE id=?")->execute([implode(',', $new), (int)$u['id']]);
        }
        setting_set('access_owner_grants_applied', implode(',', array_merge($applied, array_keys($pending))));
        return array_keys($pending);
    } catch (Throwable $e) { return []; }   // never block a sign-in over this; it retries next request
}

// ---- Owner's rules that TAKE something away from a SAVED access grid -------------
// The counterpart of access_owner_grants() for a narrowing the owner decided. Applied
// ONCE per database (its id is recorded): the rights are taken out of the stored role
// grid of those roles (and of a company role built on one), and out of every older
// frozen per-person copy of someone in those roles. A person stored as "role +
// differences" (19r) follows the role by itself; a '+right' there is an exception an
// administrator gave on purpose and is left alone. Each change is on the audit chain.
function access_owner_revokes() {
    $verbs = fn($mod) => array_map(fn($v) => "mod.$mod.$v", ['view', 'add', 'edit', 'archive', 'delete', 'approve']);
    return [
        //  D1 (owner, 21 Nov 2026 — findings SH-08, "as recommended"): Finance keeps the
        //  Quotes register (view) and the Client register's normal view in Sales; it loses
        //  deals (and with them Sales home, Today, the board, the Sales dashboard, Orders &
        //  payments, Renewals and Targets — all behind sx_can()), Sales reports and
        //  Orders / contracts. Ageing stays in Money → Receivables.
        'D1-FINANCE-SALES-2026-11-21' => ['perms' => array_merge(['crm.deal.view', 'crm.deal.export', 'crm.deal.edit'],
                                              $verbs('crm_reports'), $verbs('crm_orders')), 'roles' => ['FINANCE']],
    ];
}
function access_apply_owner_revokes($force = false) {
    static $ran = false;
    if (($ran && !$force) || !function_exists('setting_get') || !function_exists('setting_set')) return [];
    $ran = true;
    try {
        $applied = array_values(array_filter(array_map('trim', explode(',', (string) setting_get('access_owner_revokes_applied', '')))));
        $pending = array_diff_key(access_owner_revokes(), array_flip($applied));
        if (!$pending) return [];
        $base = fn($r) => function_exists('role_effective_key') ? role_effective_key(strtoupper((string) $r)) : strtoupper((string) $r);
        $raw = (string) setting_get('role_access', '');
        $store = $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($store)) {
            foreach ($pending as $g)
                foreach ($store as $roleKey => $perms)
                    if (is_array($perms) && in_array($base($roleKey), $g['roles'], true))
                        $store[$roleKey] = array_values(array_diff($perms, $g['perms']));
            setting_set('role_access', json_encode($store));
        }
        foreach (ops_all("SELECT id, role, permissions FROM users WHERE COALESCE(permissions,'')<>'' AND COALESCE(is_superuser,0)=0") ?: [] as $u) {
            $csv = (string) $u['permissions'];
            if (strpos($csv, PERM_DIFF_TAG) !== false) continue;          // follows the role; '+' exceptions are deliberate
            $take = [];
            foreach ($pending as $g) if (in_array($base($u['role']), $g['roles'], true)) $take = array_merge($take, $g['perms']);
            if (!$take) continue;
            //  A frozen copy is read through perm_resolve_stored(), which adds what its
            //  rights imply; store what it resolves to, minus the rule, as differences.
            $eff = array_values(array_diff(perm_resolve_stored($csv, $base($u['role'])), $take));
            $new = perm_diff_from($eff, $base($u['role']));
            if ($new !== $csv) {
                db()->prepare("UPDATE users SET permissions=? WHERE id=?")->execute([$new, (int) $u['id']]);
                if (function_exists('idems_log')) { try { idems_log('user', (int) $u['id'], 'ACCESS_CHANGED',
                    ['reason' => "owner's rule " . implode(', ', array_keys($pending)), 'old' => $csv, 'new' => $new]); } catch (Throwable $e) {} }
            }
        }
        setting_set('access_owner_revokes_applied', implode(',', array_merge($applied, array_keys($pending))));
        return array_keys($pending);
    } catch (Throwable $e) { return []; }   // never block a sign-in over this; it retries next request
}

// ---- C1 (owner, 16 Nov 2026): central roles become office-based ---------------
// Finance and the Marketing Manager used to see every office by role. They now
// see the offices ticked on their record (role_defaults_base(), 'OWN'). So that
// nobody who holds one of these roles TODAY loses anything — and quotation
// approvals waiting for the Marketing Manager keep reaching somebody — every
// active person in either role (a custom role built on one of them included)
// whose "Offices covered" is empty is given "All offices", ONCE per database.
// The flag makes it idempotent and means a later deliberate narrowing by an
// administrator is never undone; people added after this ran must be given
// their offices (the User register flags anyone left without a branch).
const ACCESS_LEAD_VIEWERS = ['BRANCH_MANAGER', 'SBU_HEAD'];   // owner, 21 Nov 2026 — view + download only
const ACCESS_CENTRAL_SCOPE_ROLES = ['FINANCE', 'MARKETING_MANAGER'];
const ACCESS_CENTRAL_SCOPE_FLAG  = 'scope_central_roles_c1_applied';
function access_migrate_central_roles_scope($force = false) {
    static $ran = false;
    if (($ran && !$force) || !function_exists('setting_get') || !function_exists('setting_set')) return 0;
    $ran = true;
    try {
        if ((string) setting_get(ACCESS_CENTRAL_SCOPE_FLAG, '') !== '') return 0;
        $n = 0;
        $rows = ops_all("SELECT id, role, scope_offices FROM users
                         WHERE COALESCE(is_active,1)=1 AND COALESCE(is_superuser,0)=0") ?: [];
        foreach ($rows as $u) {
            $base = function_exists('role_effective_key') ? role_effective_key(strtoupper((string) $u['role'])) : strtoupper((string) $u['role']);
            if (!in_array($base, ACCESS_CENTRAL_SCOPE_ROLES, true)) continue;
            if (trim((string) ($u['scope_offices'] ?? '')) !== '') continue;   // already given offices — keep them
            db()->prepare("UPDATE users SET scope_offices='ALL' WHERE id=?")->execute([(int) $u['id']]);
            $n++;
        }
        setting_set(ACCESS_CENTRAL_SCOPE_FLAG, date('c') . ' (' . $n . ')');
        return $n;
    } catch (Throwable $e) { return 0; }   // never block a sign-in over this; it retries next request
}

function ua($fresh = false) {
    static $a = null;
    if (!$fresh && $a !== null) return $a;
    if (function_exists('access_apply_owner_grants')) access_apply_owner_grants();
    if (function_exists('access_migrate_central_roles_scope')) access_migrate_central_roles_scope();
    if (function_exists('access_migrate_user_diffs')) access_migrate_user_diffs();
    if (function_exists('access_apply_owner_revokes')) access_apply_owner_revokes();
    if ($fresh) $a = null;
    $u = current_user($fresh);
    if (!$u) return $a = ['role' => 'GUEST', 'perms' => [], 'offices' => [], 'sbus' => [], 'self' => true, 'home' => null, 'master' => false];
    $role = !empty($u['is_superuser']) ? 'MASTER_ADMIN' : strtoupper($u['role'] ?? 'ADMIN');
    if (function_exists('role_effective_key')) $role = role_effective_key($role);   // custom role → its base (so it never falls through to ADMIN)
    if (!isset(ORG_ROLES[$role])) $role = 'ADMIN';
    $def = role_defaults($role);
    // permissions: per-user csv override wins; else the role's effective set
    // (which itself honours a Settings → Roles & access override). Master = all.
    // Resolved by user_effective_perms() so the access editor pre-ticks EXACTLY
    // what this same choke point grants (R3 — no drift between what is shown and
    // what is enforced).
    $perms = user_effective_perms($u);
    $sc = user_scope_of($u);
    return $a = [
        'role' => $role, 'perms' => array_values($perms),
        'offices' => $sc['offices'], 'sbus' => $sc['sbus'],
        'self' => $role === 'INSPECTOR', 'home' => $u['home_office_id'] ?? null,
        'master' => $role === 'MASTER_ADMIN',
    ];
}

// Which branches and business units ANY person covers — the same answer ua()
// gives for the person signed in, for anyone. Approvals need it: a quotation
// waiting for "a Branch Manager" waits for the branch manager over THAT branch,
// and "an SBU Head" for the head of THAT business unit, not every one in the
// company (owner's organisation chart, 20 Oct 2026).
function user_scope_of($u) {
    $role = !empty($u['is_superuser']) ? 'MASTER_ADMIN' : strtoupper($u['role'] ?? 'ADMIN');
    if (function_exists('role_effective_key')) $role = role_effective_key($role);
    if (!isset(ORG_ROLES[$role])) $role = 'ADMIN';
    $def = role_defaults($role);
    // office scope
    $so = trim((string)($u['scope_offices'] ?? ''));
    if ($role === 'MASTER_ADMIN' || $def['offices'] === 'ALL' && $so === '') $offices = 'ALL';
    elseif ($so === 'ALL') $offices = 'ALL';
    elseif ($so !== '') $offices = array_map('intval', array_filter(explode(',', $so)));
    //  OWN, AND OWN MEANS OWN.
    //
    //  This read: own office if one is set, otherwise EVERY office. So a
    //  Coordinator, Inspector or Branch Manager — all of whom the permission
    //  matrix confines to their own branch — silently received the whole
    //  company the moment nobody filled in their home office. Measured, and it
    //  is exactly the owner's UAT 1.5.4 and H10: "Even Mumbai office coordinator
    //  has all access", "All things are available to other offices coordinator
    //  including the new inspection call."
    //
    //  The failure direction was wrong. When the system does not know which
    //  branch somebody belongs to, the honest answer is "none of them" — not
    //  "all of them". An empty scope shows an administrator something is missing
    //  on the next screen the person opens; a wide-open one shows nobody
    //  anything, and the first time you learn is when a branch reads another
    //  branch's commercial terms.
    //
    //  scope_needs_home() tells the screens apart from an ordinary empty result,
    //  so the person is told what to ask for instead of staring at a blank list.
    else $offices = $u['home_office_id'] ? [(int)$u['home_office_id']] : [];     // OWN, unresolved → nothing
    // sbu scope
    $ss = trim((string)($u['scope_sbus'] ?? ''));
    if ($role === 'MASTER_ADMIN' || ($def['sbus'] === 'ALL' && $ss === '')) $sbus = 'ALL';
    elseif ($ss === 'ALL') $sbus = 'ALL';
    elseif ($ss !== '') $sbus = array_filter(explode(',', $ss));
    else $sbus = 'ALL';
    return ['offices' => $offices, 'sbus' => $sbus];
}

// Does this person's scope cover a record in this branch and business unit?
// A record with no branch, or no business unit, is not held back on that count.
function user_scope_covers($u, $officeId, $sbu) {
    if (!empty($u['is_superuser']) || strtoupper((string) ($u['role'] ?? '')) === 'MASTER_ADMIN') return true;
    $sc = user_scope_of($u);
    if ($officeId && $sc['offices'] !== 'ALL' && !in_array((int) $officeId, array_map('intval', (array) $sc['offices']), true)) return false;
    if ((string) $sbu !== '' && $sc['sbus'] !== 'ALL' && !in_array((string) $sbu, array_map('strval', (array) $sc['sbus']), true)) return false;
    return true;
}
// The single choke point. Every screen, every menu item and every module gate
// in this app comes through here, which is why the module licence is applied
// here and nowhere else: switch a module off and every gate that was already
// written starts saying no, with nothing else to change.
//
// Note the order — the licence is checked BEFORE the master-admin bypass. A
// module the installation has not bought is not a permissions question, and a
// Master Admin who could still open it would be looking at a screen the
// customer has not paid for and cannot be supported on.
function can($perm) {
    if (function_exists('licence_blocks') && licence_blocks($perm)) return false;
    $a = ua();
    if ($a['master'] || in_array($perm, $a['perms'], true)) return true;
    // R-20 — verb implication, checked ONLY on a miss so the common path is
    // untouched. Holding Delete on Jobs means holding Edit and View on Jobs; a
    // set that says otherwise is malformed, and "may delete but may not see" is
    // not a state any screen should have to render. The grid saves sets already
    // closed under this, so in practice this catches sets written by an older
    // build or edited by hand.
    if (strncmp($perm, 'mod.', 4) !== 0) return false;
    if (!preg_match('/^mod\.(.+)\.(view|edit)$/', $perm, $mm)) return false;
    foreach (PERM_VERB_IMPLIES as $verb => $implies) {
        if (!in_array($mm[2], $implies, true)) continue;
        if (can_direct(perm_verb_key($mm[1], $verb))) return true;
    }
    return false;
}

// can() without the implication fallback — the plain "is this exact right
// ticked?" question. Separate so the fallback above cannot recurse.
function can_direct($perm) {
    if (function_exists('licence_blocks') && licence_blocks($perm)) return false;
    $a = ua();
    return $a['master'] || in_array($perm, $a['perms'], true);
}
function scope_offices() { return ua()['offices']; } // 'ALL' or int[]

//  Is this account confined to its own branch but has not been given one?
//  Returns true only for that specific misconfiguration — never for a user who
//  legitimately has an empty result. The screens use it to say "your account has
//  no branch yet" instead of showing an empty list with no explanation, and
//  /users uses it to show an administrator who is affected BEFORE they call.
function scope_needs_home($u = null) {
    $u = $u ?: (function_exists('current_user') ? current_user() : null);
    if (!$u) return false;
    $role = strtoupper((string) ($u['role'] ?? ''));
    if ($role === 'MASTER_ADMIN' || !empty($u['is_superuser'])) return false;
    if (trim((string) ($u['scope_offices'] ?? '')) !== '') return false;   // explicitly scoped
    if (!function_exists('role_defaults')) return false;
    $def = role_defaults($role);
    if (($def['offices'] ?? 'OWN') === 'ALL') return false;               // company-wide by design
    return (int) ($u['home_office_id'] ?? 0) <= 0;
}

//  Every account this workspace cannot place in a branch. An administrator sees
//  this on /users, so a person is never quietly left unable to see their work.
function scope_unplaced_users() {
    //  Asked once per request: the banner that reports it is drawn on every
    //  screen, and the /users screen asks again to mark the rows.
    static $cache = null, $at = -1;
    $epoch = function_exists('db_epoch') ? db_epoch() : 0;
    if ($cache !== null && $at === $epoch) return $cache;
    try { $rows = ops_all("SELECT id, username, first_name, last_name, role, home_office_id, scope_offices
                           FROM users WHERE COALESCE(is_active,1)=1"); }
    catch (Throwable $e) { return []; }
    $at = $epoch;
    return $cache = array_values(array_filter($rows, 'scope_needs_home'));
}
function scope_sbus() { return ua()['sbus']; }        // 'ALL' or string[]

// Scope by office ALONE, for registers that have a branch but no Business Unit
// — complaints and corrective actions, which record where something happened
// rather than which unit sold it. Written separately rather than passing a fake
// column to scope_clause(): a Business-Unit-scoped user matched against a
// literal would have every row filtered out, and an empty register reads as
// "nothing has gone wrong" instead of "you are not being shown it".
//
// A row with no office is deliberately visible to everyone. A nonconformity or
// complaint that nobody assigned to a branch must not become invisible to every
// branch — that is how things get lost.
function scope_office_clause($officeCol) {
    $off = scope_offices();
    if ($off === 'ALL' || !is_array($off)) return ['1=1', []];
    //  AN EMPTY SCOPE IS "NOTHING" — BUT ONLY FOR THE ONE CASE THAT MEANS IT.
    //
    //  This read `|| !$off` and answered 1=1, no filter, for an empty list. That
    //  would have undone the ua() fix entirely: an account confined to a branch
    //  nobody gave it would have been handed the whole company by the very
    //  clause meant to confine it.
    //
    //  It cannot simply become 1=0, because an empty list also arises where
    //  there is no signed-in user at all — the nightly export, the Tally run,
    //  the CLI, the test bootstrap. Those authorise themselves and have always
    //  run unscoped; turning them off would break working exports to fix a
    //  permission bug, which is not a trade anybody asked for. (Measured: the
    //  credit-note export went empty the moment this returned a blanket 1=0.)
    //
    //  So the question asked is the specific one: is there a PERSON here whom
    //  this workspace cannot place in a branch? Then they see nothing. Any other
    //  empty list behaves exactly as it did before.
    if (!$off) return [scope_needs_home() ? '1=0' : '1=1', []];
    $ids = implode(',', array_map('intval', $off));
    return ["($officeCol IS NULL OR $officeCol IN ($ids))", []];
}

// ---- Leads and deals: branch, and business unit for unit-based managers --------
// Owner, 21 Nov 2026: "branch manager and SBU head can sure open the lead and check
// all other details as they are also responsible for business individually for
// their branch and business units respectively." Leads and deals were scoped by
// branch alone, which is right for a Branch Manager (their branch) but would show
// an SBU Head — who covers every branch — the whole company. So a person who covers
// EVERY branch but only SOME business units sees the leads and deals of their
// units. A record with no unit stays visible, as a record with no branch does.
// Nobody limited by branch is affected (their branch already limits them).
function scope_sales_unit_list() {
    if (function_exists('is_master') && is_master()) return null;
    $off = scope_offices(); $sbu = scope_sbus();
    if ($off !== 'ALL') return null;
    if ($sbu === 'ALL' || !is_array($sbu) || !$sbu) return null;
    return array_values(array_map('strval', $sbu));
}
function scope_sales_clause($alias) {
    [$w, $a] = scope_office_clause("$alias.office_id");
    $units = scope_sales_unit_list();
    //  Q10 — whose records (own / team), inside the branch and unit limits above.
    $own = sales_owner_sql("$alias.owner_user_id");
    //  R-05 — the one branch picked on this Sales screen (narrows only).
    $bn = sales_branch_sql("$alias.office_id");
    if ($own === '1=1') $own = $bn; elseif ($bn !== '1=1') $own = "$own AND $bn";
    if ($units === null) return [$own === '1=1' ? $w : "($w) AND $own", $a];
    $q = implode(',', array_map(fn($u) => db()->quote($u), $units));
    return ["($w) AND (COALESCE($alias.sbu,'') = '' OR $alias.sbu IN ($q))" . ($own === '1=1' ? '' : " AND $own"), $a];
}

// ---- R-05 (owner, 26 Nov 2026) — "for Director or person having central data access
//  … they shall have filter branch wise also." --------------------------------------
//  A person who sees more than one branch picks ONE branch on a Sales screen (Leads,
//  Deals, Enquiries, Pipeline board, Sales dashboard, Sales home, Today). The pick is
//  set for that one request by sx_branch_begin() (lib/sales_simple.php), only on a
//  GET of those screens (their CSV downloads included), and only to a branch the
//  person already sees — so it can only NARROW what the branch / unit / whose-records
//  rules above allow, never widen it, and never touches a save. 0 = no pick.
function sales_branch_narrow() { return (int) ($GLOBALS['__sales_branch'] ?? 0); }
function sales_branch_sql($col) { $b = sales_branch_narrow(); return $b > 0 ? "$col = " . $b : '1=1'; }
//  $ownerId: the record's owner (leads / deals), or false when the caller has no
//  owner to ask about — then only branch and unit decide, as before Q10.
function scope_sales_allows($officeId, $sbu, $ownerId = false) {
    if (function_exists('scope_office_allows') && !scope_office_allows($officeId)) return false;
    if ($ownerId !== false && !sales_owner_allows($ownerId)) return false;
    $units = scope_sales_unit_list();
    return $units === null || trim((string) $sbu) === '' || in_array((string) $sbu, $units, true);
}

// ---- Q10 — whose records a person sees (owner, 21 Nov 2026: "Keep this") -------
//  Own    only the records they own;
//  Team   theirs and those of everyone who reports to them (the whole line below);
//  Branch everything in their branches (and business units) — the rule every
//         Sales register has always followed;
//  All    the whole company = Branch for a person whose branches are "All
//         branches". Branches stay where they have always been set (the person's
//         branches / data scope); "All" on the screen sets them to All branches.
//  Own and Team NARROW inside the branch and unit limits; they never let anyone
//  see a branch they could not see before.
//
//  Stored as a setting, not as permission ticks: "Select everything" and the
//  Master Admin's every-right must never switch a person to "own records only".
//  A role with nothing saved, and a person with no override, is Branch — exactly
//  what every register did before Q10 (proved person by person in
//  tests/test_q10_scope.php). Owner of a lead / deal = its owner; of an enquiry =
//  the person it is assigned to; of a quotation = its owner (else whoever made it).
const SALES_REC_MODES = ['own' => 'Own', 'team' => 'Team', 'branch' => 'Branch'];
function sales_rec_store() {
    $s = json_decode((string) (function_exists('setting_get') ? setting_get('sales_records_mode', '') : ''), true);
    $s = is_array($s) ? $s : [];
    return ['roles' => (array) ($s['roles'] ?? []), 'users' => (array) ($s['users'] ?? [])];
}
function sales_rec_store_put(array $st) { setting_set('sales_records_mode', json_encode(['roles' => (object) $st['roles'], 'users' => (object) $st['users']])); }
function sales_rec_role_mode($role) {
    $st = sales_rec_store(); $role = strtoupper((string) $role);
    $m = $st['roles'][$role] ?? null;
    if ($m === null && function_exists('role_effective_key')) $m = $st['roles'][role_effective_key($role)] ?? null;
    return in_array($m, ['own', 'team'], true) ? $m : 'branch';
}
//  A person's own choice ('' = follows the role).
function sales_rec_user_override($uid) {
    $m = sales_rec_store()['users'][(string) (int) $uid] ?? '';
    return in_array($m, ['own', 'team', 'branch'], true) ? $m : '';
}
function sales_rec_mode_for($u) {
    if (!$u) return 'branch';
    if (!empty($u['is_superuser']) || strtoupper((string) ($u['role'] ?? '')) === 'MASTER_ADMIN') return 'branch';
    $o = sales_rec_user_override((int) ($u['id'] ?? 0));
    return $o !== '' ? $o : sales_rec_role_mode($u['role'] ?? '');
}
function sales_rec_mode() {
    $u = function_exists('current_user') ? current_user() : null;
    return $u ? sales_rec_mode_for($u) : 'branch';   // no signed-in person (CLI, nightly runs): unchanged
}
//  The person and everyone below them in "Reports to", however deep (loop-safe).
function sales_team_ids($uid) {
    $uid = (int) $uid; if ($uid <= 0) return [];
    $kids = [];
    try { foreach (ops_all("SELECT id, reports_to_id FROM users WHERE COALESCE(reports_to_id,0) > 0") ?: [] as $r) $kids[(int) $r['reports_to_id']][] = (int) $r['id']; }
    catch (Throwable $e) { return [$uid]; }
    $seen = [$uid => true]; $todo = [$uid];
    while ($todo) { $c = array_pop($todo); foreach ($kids[$c] ?? [] as $k) if (!isset($seen[$k])) { $seen[$k] = true; $todo[] = $k; } }
    return array_keys($seen);
}
//  Everyone who reports to $uid (not $uid), for the Sales home / Today picker.
function sales_reportee_ids($uid) { return array_values(array_diff(sales_team_ids($uid), [(int) $uid])); }
//  The owners whose records the current person sees; null = no owner limit (Branch).
function sales_rec_ids() {
    $u = function_exists('current_user') ? current_user() : null;
    $mode = sales_rec_mode();
    if ($mode === 'branch' || !$u) return null;
    $me = (int) ($u['id'] ?? 0);
    return $mode === 'own' ? [$me] : sales_team_ids($me);
}
//  Unassigned records (owner, 21 Nov 2026, answer "a"): a lead, deal or inquiry
//  with NO owner stays visible to an Own / Team person inside their branches and
//  units, so somebody picks it up ("Take these myself" / assigning). The branch
//  and unit limits are applied beside this, never widened by it. A quotation
//  always has a maker, so its callers pass $unassigned = false (unchanged).
function sales_owner_sql($col, $unassigned = true) {
    $ids = sales_rec_ids();
    if ($ids === null) return '1=1';
    $ids = array_map('intval', $ids ?: []);
    if ($unassigned) $ids[] = 0;
    return "COALESCE($col,0) IN (" . implode(',', array_values(array_unique($ids ?: [0]))) . ")";
}
function sales_owner_allows($ownerId, $unassigned = true) {
    $ids = sales_rec_ids();
    if ($ids === null) return true;
    if ((int) $ownerId <= 0) return (bool) $unassigned;
    return in_array((int) $ownerId, $ids, true);
}
//  A quotation's owner: owner_id, else the person who made it (created_by holds a
//  username or a full name — crm_quote_owner_user_id()'s own rule).
function sales_quote_owner_sql($a = 'q') {
    $ids = sales_rec_ids();
    if ($ids === null) return '1=1';
    $in = implode(',', array_map('intval', $ids ?: [0]));
    $names = [];
    try { foreach (ops_all("SELECT username, first_name, last_name FROM users WHERE id IN ($in)") ?: [] as $r) {
        if (trim((string) $r['username']) !== '') $names[] = (string) $r['username'];
        $full = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); if ($full !== '') $names[] = $full;
    } } catch (Throwable $e) {}
    $nm = $names ? implode(',', array_map(fn($n) => db()->quote($n), array_values(array_unique($names)))) : "''";
    return "(COALESCE($a.owner_id,0) IN ($in) OR (COALESCE($a.owner_id,0)=0 AND $a.created_by IN ($nm)))";
}
//  scope_clause() for a quotation, with Q10 on top.
function scope_quote_clause($officeCol = 'q.office_id', $sbuCol = 'q.sbu', $a = 'q') {
    [$w, $args] = scope_clause($officeCol, $sbuCol);
    $o = sales_quote_owner_sql($a);
    $bn = sales_branch_sql($officeCol);   // R-05 — the branch picked on a Sales screen (narrows only)
    if ($bn !== '1=1') $o = $o === '1=1' ? $bn : "$o AND $bn";
    return [$o === '1=1' ? $w : "($w) AND $o", $args];
}

// Build a WHERE fragment scoping calls/jobs by office + Business Unit. $officeCol is the
// column holding the executing office id (nullable → treated as Ahmedabad).
function scope_clause($officeCol, $sbuCol) {
    $off = scope_offices(); $sbu = scope_sbus();
    $w = []; $args = [];
    //  Same rule as scope_office_clause(): an account resolved to no branch sees
    //  no rows, never every row. Without this the `&& $off` below quietly skips
    //  the office condition for an empty list and returns 1=1.
    if ($off !== 'ALL' && is_array($off) && !$off && scope_needs_home()) return ['1=0', []];
    if ($off !== 'ALL' && is_array($off) && $off) {
        static $ahm = null;
        if ($ahm === null) $ahm = (int)(ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 LIMIT 1") ?: 0);
        // Inline integer office ids: PDO binds params as strings, and a COALESCE()
        // expression drops integer affinity, so a bound '20' would not match int 20.
        $ids = implode(',', array_map('intval', $off));
        $w[] = "COALESCE($officeCol, $ahm) IN ($ids)";
    }
    if ($sbu !== 'ALL' && is_array($sbu) && $sbu) {
        $ph = implode(',', array_fill(0, count($sbu), '?'));
        $w[] = "$sbuCol IN ($ph)";
        foreach ($sbu as $s) $args[] = $s;
    }
    return [$w ? implode(' AND ', $w) : '1=1', $args];
}

// ============================================================================
//  WHICH PARTIES THIS BRANCH MAY SEE — one definition, every list and picker.
//
//  Owner decision: a branch sees its own clients and vendors, and the dropdown
//  a person picks from must offer exactly what their register shows. A register
//  that hides a party while the form beside it still offers that party is worse
//  than no scoping at all — it tells two different stories about the same data.
//
//  So every party list asks this one function: the registers, the dashboard
//  counts, and all ~40 pickers spread across operations, sales, reporting,
//  quality and books.
//
//  A party with NO branch set stays visible to everyone. Same rule as leads,
//  opportunities and complaints, and for the same reason: an unassigned party
//  belongs to nobody, and hiding it from everybody would strand it. Every party
//  is unassigned until somebody sets the field, so this is also what stops the
//  change emptying every dropdown in the product on the day it ships.
//
//  Returns SQL only, never bound parameters: scope_office_clause() inlines its
//  office ids as integers, so a caller can drop this into an existing query
//  without disturbing that query's own arguments. That is deliberate — it is
//  what let this be applied at every call site without re-plumbing each one.
// ============================================================================
function partner_office_sql($col = 'home_branch_id') {
    if (!function_exists('scope_office_clause')) return '1=1';
    [$w, $a] = scope_office_clause($col);
    return $w;
}

// ============================================================================
//  HOW MUCH OF A CLIENT ANOTHER OFFICE MAY SEE — and that it may change NOTHING
//
//  The registers were scoped long ago, but the client RECORD was not: a Mumbai
//  coordinator who reached an Ahmedabad client — through search, or a bookmarked
//  link — could open every form on it and save. Tested on the live data, a
//  Mumbai-only coordinator renamed an Ahmedabad client and set its credit terms
//  to 999 days. Ahmedabad would never have known.
//
//  Hiding the record outright was the wrong answer: the same company is often
//  worked on by more than one branch, and a coordinator arranging a visit needs
//  to know WHO the client is. So the owner's rule is:
//
//      basic identity  — visible to any office (who this company is)
//      contact details — the owning office's only
//      commercial      — the owning office's only
//      any change      — the owning office's only, always
//
//  Returns 'FULL' or 'BASIC'. A party with no branch set belongs to nobody and
//  stays FULL, the same rule partner_office_sql() applies — otherwise the day
//  this shipped every existing client would have locked itself.
// ============================================================================
function partner_view_level($partner) {
    if (function_exists('is_master') && is_master()) return 'FULL';
    $branch = (int) (is_array($partner) ? ($partner['home_branch_id'] ?? 0) : $partner);
    if (!$branch) return 'FULL';                       // unassigned — belongs to nobody
    if (!function_exists('scope_offices')) return 'FULL';
    $off = scope_offices();
    if ($off === 'ALL' || !is_array($off)) return 'FULL';
    //  An empty office list is "no branch", not "every branch" (DEF-ADM-002):
    //  a person nobody placed in a branch may not change another office's party.
    if (!$off) return (function_exists('scope_needs_home') && scope_needs_home()) ? 'BASIC' : 'FULL';
    return in_array($branch, array_map('intval', $off), true) ? 'FULL' : 'BASIC';
}

// May this person change this client / vendor at all? Only the office that owns
// it. This is the WRITE half of the rule above and is deliberately a separate
// function, because "may see some of it" and "may change it" are different
// questions and answering both with one flag is how the leak happened.
function partner_can_write($partner) { return partner_view_level($partner) === 'FULL'; }

// The one refusal message, so every door says the same thing and names the
// office to ask. A refusal that does not say who owns the record just sends
// somebody hunting.
function partner_write_denied_msg($partner) {
    $branch = (int) (is_array($partner) ? ($partner['home_branch_id'] ?? 0) : $partner);
    $name = '';
    if ($branch && function_exists('ops_val')) {
        try { $name = (string) ops_val("SELECT name FROM offices WHERE id=?", [$branch]); } catch (Throwable $e) {}
    }
    return 'This ' . (function_exists('Tl') ? Tl('client') : 'client') . ' belongs to '
        . ($name !== '' ? 'the ' . $name . ' office' : 'another office')
        . '. You can see who they are, but their contact details, commercial terms and any change to them '
        . 'are that office\'s to make — ask them.';
}

// ============================================================================
//  THE TWO-OFFICE RULE FOR WORK ORDERS — one definition, every caller.
//
//  A work order belongs to TWO offices: the CONTRACTING office, which holds the
//  client's order, and the EXECUTING office, which does the work. Both must see
//  it — a branch that sold the work cannot chase it if the register hides it the
//  moment another branch is asked to carry it out.
//
//  This was written out by hand in the calls register and, separately and
//  DIFFERENTLY, in the dashboard's open-work-orders count: the count scoped on
//  the executing office alone, so it under-reported against the very list it
//  linked to. A rule stated twice is a rule that drifts, so it is stated once
//  here and both callers ask this function.
//
//  A NULL executing office is treated as the managing (Ahmedabad) office, which
//  is what scope_clause() above already does — the two must not disagree about
//  an unassigned row.
//
//  This GRANTS NOTHING new: it is the calls register's existing rule, moved. The
//  Business-Unit half is still scope_clause()'s job and is applied by the caller.
//  $execCol / $contractCol are the executing and contracting office columns.
// ============================================================================
function call_office_clause($execCol, $contractCol) {
    $off = function_exists('scope_offices') ? scope_offices() : 'ALL';
    if ($off === 'ALL' || !is_array($off)) return ['1=1', []];
    //  DEF-ADM-002 / DEF-OPS-001 — an EMPTY office list was read as "every
    //  office", so a branch person whose home office was left blank saw every
    //  branch's work orders under the banner telling them their lists were empty.
    //  The same answer as scope_clause() and scope_allows(): a person this
    //  workspace cannot place in a branch sees no row; a run with nobody signed
    //  in (export, CLI) stays unscoped, as it always has.
    if (!$off) return [(function_exists('scope_needs_home') && scope_needs_home()) ? '1=0' : '1=1', []];
    static $ahm = null;
    if ($ahm === null) $ahm = (int)(ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 LIMIT 1") ?: 0);
    // Inline integer ids for the same reason scope_clause() does: a bound '20'
    // does not match int 20 once COALESCE() has dropped integer affinity.
    $ids = implode(',', array_map('intval', $off));
    return ["(COALESCE($execCol, $ahm) IN ($ids) OR $contractCol IN ($ids))", []];
}

// THE OFFICE THAT RUNS A WORK ORDER, as one SQL expression — the SQL twin of
// call_exec_office() in lib/ops.php: the executing office when one is set,
// otherwise the contracting office (a work order nobody forwarded is carried out
// by the office that took it), otherwise the managing (Ahmedabad) office, as
// scope_clause() reads an unassigned row. The Operations home, its registers and
// the scheduling board — the executing office's desk — scope on this
// (DEF-OPS-002: a blank executing office used to be shown to EVERY branch).
function call_exec_office_sql($execCol, $contractCol) {
    static $ahm = null;
    if ($ahm === null) $ahm = (int)(ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 LIMIT 1") ?: 0);
    return "COALESCE(NULLIF($execCol, 0), NULLIF($contractCol, 0), $ahm)";
}

// Phase 2 §51 — the SCALAR twin of scope_clause(), for guarding a single record
// fetched by id (detail / PDF / file-stream handlers). The list layer scopes with
// scope_clause(); this closes the matching cross-office IDOR on the fetch-by-id
// layer by applying the SAME rule to one record: a non-master whose office/SBU
// scope does not include the record cannot open it either. Masters and ALL-scope
// roles are unaffected; a NULL office is treated as Ahmedabad, exactly as
// scope_clause() does, and a record with no SBU is not SBU-filtered.
function scope_allows($officeId, $sbu = null) {
    if (function_exists('is_master') && is_master()) return true;
    $off = function_exists('scope_offices') ? scope_offices() : 'ALL';
    //  No branch resolved → no record. The scalar guards have to answer the same
    //  way as the clause builders they are twins of, or a list would hide a row
    //  that its own detail screen still opened.
    if ($off !== 'ALL' && is_array($off) && !$off && scope_needs_home()) return false;
    if ($off !== 'ALL' && is_array($off) && $off) {
        static $ahm = null;
        if ($ahm === null) $ahm = (int)(ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 LIMIT 1") ?: 0);
        $rowOff = (int)($officeId ?: $ahm);            // null office → Ahmedabad, like scope_clause()
        if (!in_array($rowOff, array_map('intval', $off), true)) return false;
    }
    if ($sbu !== null && (string)$sbu !== '') {
        $sc = function_exists('scope_sbus') ? scope_sbus() : 'ALL';
        if ($sc !== 'ALL' && is_array($sc) && $sc && !in_array((string)$sbu, array_map('strval', $sc), true)) return false;
    }
    return true;
}

// M14 §51b — the SCALAR twin of scope_office_clause(), the same way scope_allows()
// above is the scalar twin of scope_clause(). It exists because the two list
// rules disagree about one thing, and an object-level guard that borrowed the
// wrong one would be wrong in a way nobody would notice:
//
//   scope_clause()        COALESCE(office, Ahmedabad) IN (...)   — no office means Ahmedabad
//   scope_office_clause() (office IS NULL OR office IN (...))    — no office means EVERYONE sees it
//
// The second is deliberate: a complaint or a lead nobody has assigned to a
// branch must not become invisible to every branch — that is how things get
// lost. So a register scoped by scope_office_clause() (leads, opportunities,
// complaints, receipts) must be guarded by THIS, not by scope_allows(), or the
// detail would start hiding unassigned records its own list shows.
//
// One record, the same rule the list applies. Masters and ALL-scope roles are
// unaffected.
function scope_office_allows($officeId) {
    if (function_exists('is_master') && is_master()) return true;
    $off = function_exists('scope_offices') ? scope_offices() : 'ALL';
    if ($off === 'ALL' || !is_array($off)) return true;
    if (!$off) return !scope_needs_home();   // a person with no branch sees no record; a CLI run is unscoped
    // Unassigned → visible, exactly as scope_office_clause() has it.
    if ($officeId === null || $officeId === '' || (int)$officeId === 0) return true;
    return in_array((int)$officeId, array_map('intval', $off), true);
}

// Phase 6 · Batch 1 — the SBU-ONLY scalar guard, the third member of the family
// beside scope_allows() and scope_office_allows().
//
// It exists because some registers record a business unit and no branch at all:
// `candidates` carries `sbu` and has no office column. Guarding one of those
// with scope_allows(null, $sbu) would silently apply the office half of that
// rule — a NULL office is read as Ahmedabad — and refuse every branch-scoped
// user a record that has no branch to be wrong about. That is the same trap
// documented above between scope_clause() and scope_office_clause(), one table
// along.
//
// Same SBU semantics as scope_allows(): a record with no SBU is not filtered,
// and masters and ALL-scope roles are unaffected.
function scope_sbu_allows($sbu) {
    if (function_exists('is_master') && is_master()) return true;
    $sbu = (string)$sbu;
    if ($sbu === '') return true;
    $sc = function_exists('scope_sbus') ? scope_sbus() : 'ALL';
    if ($sc === 'ALL' || !is_array($sc) || !$sc) return true;
    return in_array($sbu, array_map('strval', $sc), true);
}

// Field-finding #22 — segregation of inspectors. A "team member" (an `inspectors` row) must belong to at
// most ONE active login: two logins sharing one inspector_id would each see that inspector's jobs and
// schedule (a leak between people). This returns the username of a DIFFERENT active login already linked
// to the given inspector, or '' if none — the guard the user-save form checks before saving a link.
function inspector_login_conflict($inspectorId, $exceptUserId = 0) {
    $inspectorId = (int)$inspectorId; if (!$inspectorId) return '';
    try {
        $u = ops_one("SELECT username FROM users WHERE inspector_id=? AND is_active=1 AND id<>? LIMIT 1",
                     [$inspectorId, (int)$exceptUserId]);
        return $u ? (string)($u['username'] ?? '') : '';
    } catch (Throwable $e) { return ''; }
}
// How many team members are (wrongly) shared by 2+ active logins — for the §7.11 data-integrity board, so
// any existing collision from before the guard surfaces for repair rather than leaking silently.
function inspector_shared_login_count() {
    try {
        return (int) ops_val("SELECT COUNT(*) FROM (
                    SELECT inspector_id FROM users
                    WHERE is_active=1 AND COALESCE(inspector_id,0)<>0
                    GROUP BY inspector_id HAVING COUNT(*) > 1) t");
    } catch (Throwable $e) { return 0; }
}

// ---- Settings (key/value) --------------------------------------------------
function ensure_settings_schema() {
    db()->exec("CREATE TABLE IF NOT EXISTS settings (skey VARCHAR(60) PRIMARY KEY, svalue TEXT)");
    // widen an older VARCHAR(255) svalue so it can hold a base64 logo (MySQL)
    if (db_driver() !== 'sqlite') { try { db()->exec("ALTER TABLE settings MODIFY svalue MEDIUMTEXT"); } catch (Throwable $e) {} }
}

// Theme <style> from settings — overrides --brand and keeps top-bar text legible.
function theme_hex($s) { $s = trim((string)$s); return preg_match('/^#[0-9a-fA-F]{6}$/', $s) ? strtolower($s) : ''; }
function theme_lum($h) { return 0.299 * hexdec(substr($h, 1, 2)) + 0.587 * hexdec(substr($h, 3, 2)) + 0.114 * hexdec(substr($h, 5, 2)); }
// Mix hex $a toward $b by fraction $t (0..1). Used to derive soft/line/muted so
// both light and dark themes stay coherent from just surface + text colours.
function theme_mix($a, $b, $t) {
    $o = '#';
    for ($i = 1; $i < 6; $i += 2) {
        $ca = hexdec(substr($a, $i, 2)); $cb = hexdec(substr($b, $i, 2));
        $o .= str_pad(dechex(max(0, min(255, (int)round($ca * (1 - $t) + $cb * $t)))), 2, '0', STR_PAD_LEFT);
    }
    return $o;
}
//  Deep Teal, per the UI/UX blueprint. Only a fallback — a workspace that has
//  chosen a colour keeps it. 7.47:1 on white, so the ring derived from it is
//  visible without adjustment on a default install.
const THEME_BRAND = '#0f5f5c';

//  ---- Accessibility (B1) --------------------------------------------------
//  theme_lum() above is the OLD perceived-brightness formula (0.299/0.587/0.114).
//  It is kept because two call sites use it only to choose black-or-white text
//  on the top bar, where it is adequate. It is NOT the WCAG formula and must
//  never be used to claim a contrast ratio. These three are.
//
//  WCAG 2.1 relative luminance. sRGB channel -> linear, then weighted.
function theme_rel_lum($hex) {
    $c = [];
    foreach ([1, 3, 5] as $i) {
        $v = hexdec(substr($hex, $i, 2)) / 255;
        $c[] = $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
//  Contrast ratio between two colours, 1..21.
function theme_contrast($a, $b) {
    $la = theme_rel_lum($a); $lb = theme_rel_lum($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}
//  Return $c, adjusted only as far as necessary to reach $target contrast
//  against EVERY background in $bgs.
//
//  Why this exists (F-A2-2): the derived tokens below were produced by mixing
//  the surface toward the ink by a fixed fraction. A fixed fraction cannot
//  promise a contrast ratio -- it produced 2.67:1 for secondary text on white,
//  where WCAG AA asks 4.5:1, on EVERY workspace including the unbranded one.
//
//  The correction keeps the tenant's own colours and moves the derived value
//  the minimum distance needed, so a workspace that already passes is left
//  exactly as it is. It darkens on light surfaces and lightens on dark ones,
//  and gives up at pure black/white rather than looping, because a mid-grey
//  background genuinely cannot carry 4.5:1 in either direction.
function theme_readable($c, $bgs, $target = 4.5) {
    $bgs = array_values(array_filter((array) $bgs));
    if (!$bgs) return $c;
    $ok = function ($x) use ($bgs, $target) {
        foreach ($bgs as $b) if (theme_contrast($x, $b) < $target) return false;
        return true;
    };
    if ($ok($c)) return $c;                       // already compliant: do not touch it
    $avg = 0.0; foreach ($bgs as $b) $avg += theme_rel_lum($b);
    $avg /= count($bgs);
    $towards = $avg > 0.18 ? '#000000' : '#ffffff';
    for ($t = 0.02; $t < 1.0; $t += 0.02) {
        $x = theme_mix($c, $towards, $t);
        if ($ok($x)) return $x;
    }
    return $towards;
}

function theme_style_tag() {
    //  F-A2-1 — the blueprint mandates a Deep Teal primary; the stylesheet
    //  shipped blue, so an unbranded workspace showed the wrong product. This
    //  is only the FALLBACK: a workspace that has chosen its own colour is
    //  untouched.
    //
    //  The blueprint also names Gold as the accent, and that is NOT applied
    //  here. In this codebase --accent is wired to --info (app.css), so it is
    //  not decoration -- it is the informational status colour, and changing it
    //  would change the meaning of a status. Deferred to B10, recorded in the
    //  B1 report.
    $primary = theme_hex(setting_get('c_primary', '')) ?: (theme_hex(setting_get('brand_color', '')) ?: THEME_BRAND);
    $accent  = theme_hex(setting_get('c_accent', ''))  ?: '#0ea5e9';
    $bg      = theme_hex(setting_get('c_bg', ''))       ?: '#f4f6f9';
    $surface = theme_hex(setting_get('c_surface', ''))  ?: '#ffffff';
    $ink     = theme_hex(setting_get('c_text', ''))     ?: '#1f2937';
    $fs = (int)(setting_get('font_size', '') ?: 14); if ($fs < 12 || $fs > 20) $fs = 14;
    $soft  = theme_mix($surface, $ink, 0.05);
    $line  = theme_mix($surface, $ink, 0.14);
    $field = theme_mix($surface, $ink, 0.04);
    //  Secondary text must be readable on every surface it actually lands on --
    //  the card, the panel and the page -- not merely on the lightest of them.
    //  Measured before this change: 2.67 / 2.43 / 2.46:1.
    $muted = theme_readable(theme_mix($surface, $ink, 0.45), [$surface, $soft, $bg], 4.5);
    //  A form control's boundary is a UI component under WCAG 1.4.11, which asks
    //  3:1. The shared --line stays as it is, because it also draws table row
    //  separators, where a 3:1 rule would make every list look like a spreadsheet.
    //  --field-line already existed as a separate token and simply pointed at
    //  --line; it now carries the requirement that belongs to it.
    $fieldLine = theme_readable($line, [$surface, $field, $bg], 3.0);
    //  The focus ring was painted in the raw brand colour, so its visibility was
    //  whatever the tenant happened to choose: a pale gold brand measured 2.1:1,
    //  which leaves a keyboard user unable to see where they are. Derived from
    //  the brand, so the identity is kept, but never below 3:1.
    $focus = theme_readable($primary, [$surface, $soft, $bg], 3.0);
    //  Brand colour used as TEXT on a surface — a secondary button's label, a
    //  brand-tinted link. Same derivation as the focus ring but at the text
    //  threshold, because it is text.
    $brandInk = theme_readable($primary, [$surface, $soft, $bg], 4.5);
    //  A solid brand button. The label was hard-coded #fff, so a pale brand gave
    //  2.1:1 and the button could not be read at all. Choose the label colour the
    //  brand can actually carry, then -- only if even that is not enough -- move
    //  the BUTTON'S OWN background far enough to carry it. The hue is kept, so a
    //  gold workspace still has gold buttons; they simply become legible.
    $onBrand = theme_contrast($primary, '#ffffff') >= theme_contrast($primary, '#000000') ? '#ffffff' : '#000000';
    $btnBg   = theme_readable($primary, [$onBrand], 4.5);
    //  The top bar sat on the same hard-coded guess, decided by the OLD
    //  perceived-brightness formula rather than a contrast ratio.
    $navtext = $onBrand;
    $navlink = $onBrand === '#ffffff' ? 'rgba(255,255,255,.86)' : 'rgba(17,24,39,.78)';
    $isDark = theme_lum($surface) < 128;
    $shadow   = $isDark ? '0 1px 2px rgba(0,0,0,.4), 0 12px 34px rgba(0,0,0,.5)' : '0 1px 2px rgba(18,32,60,.05), 0 10px 30px rgba(18,32,60,.08)';
    $shadowSm = $isDark ? '0 1px 2px rgba(0,0,0,.45)' : '0 1px 2px rgba(18,32,60,.06)';
    return "<style>:root{--brand:$primary;--brand-ink:$brandInk;--btn-bg:$btnBg;--on-brand:$onBrand;--accent:$accent;--bg:$bg;--card:$surface;--panel:$soft;--ink:$ink;--soft:$soft;--line:$line;--muted:$muted;--field:$field;--field-line:$fieldLine;--focus:$focus;--shadow:$shadow;--shadow-sm:$shadowSm;--fs:{$fs}px}"
        . "body{font-size:var(--fs)}.stat-card,.master-card{background:var(--soft)}"
        . ".topbar .brand,.topbar .user{color:$navtext}.topbar nav a{color:$navlink}.topbar nav a:hover{color:$navtext}"
        . "</style>";
}
// The header logo: Branding's own upload, else the company logo from Your
// company (stored for documents as bare base64 JPEG). One upload is enough —
// a logo given during company set-up must not be asked for again here.
function logo_src() {
    $d = trim((string) setting_get('logo_data', ''));
    if ($d !== '') return $d;
    $c = preg_replace('~\s+~', '', (string) setting_get('quote_lh_logo', ''));
    return ($c !== '' && preg_match('~^[A-Za-z0-9+/=]+$~', $c)) ? 'data:image/jpeg;base64,' . $c : '';
}
function logo_html() {
    $d = logo_src();
    //  Only emit an <img> for something that can actually BE an image source.
    //  This used to trust the setting blindly, so a corrupt or truncated value —
    //  found in the wild as 5,000 letter A's — became src="AAAA…" on EVERY page:
    //  a broken image in the header and a doomed request to /AAAA… on every load.
    //  Falling back to the text logo is the correct failure: the header still
    //  reads, and nobody chases a 404 that is really a bad setting.
    $okSrc = $d !== '' && (stripos($d, 'data:image/') === 0
                        || preg_match('~^https?://~i', $d) === 1
                        || strpos($d, '/') === 0);
    return $okSrc
        ? '<img src="' . e($d) . '" alt="logo" style="height:30px;vertical-align:middle;background:#fff;border-radius:4px;padding:2px 6px">'
        : '';
}
// The name shown in the header/title. An explicit app_name still wins, but when
// it is blank we fall back to the company brand/legal name set in Company profile
// — so filling that screen updates the header, which is what a user expects
// (previously the two were separate settings and the header never changed).
function app_name() {
    return setting_get('app_name', '')
        ?: (setting_get('company_name', '')
        ?: (setting_get('company_legal_name', '')
        ?: 'Inspection Ops'));
}
function &settings_cache() {
    static $cache = null;
    static $atEpoch = -1;
    // Reload when the live database is switched (choosing a company at login,
    // "Log in as", provisioning). Without this, the settings read inside a
    // company's workspace would be the CONTROL database's — a cross-company
    // leak of modules, seat limits and every other setting.
    $epoch = function_exists('db_epoch') ? db_epoch() : 0;
    //  S1-B0 — the cache is handed out BY REFERENCE; if a caller ever wrote a non-array
    //  into it (a test re-using a variable name did, and crashed the full suite), reload.
    if (!is_array($cache) || $atEpoch !== $epoch) {
        $cache = [];
        $atEpoch = $epoch;
        //  M3 FINAL STABILISATION — approval condition records live in `settings`
        //  as one row each, and are always read straight from the database because
        //  another process may be writing them concurrently. They are excluded here
        //  so a workspace with many open conditions does not enlarge every other
        //  settings read, and so nothing can accidentally read them from a cache
        //  that is loaded once per epoch.
        try { foreach (ops_all("SELECT skey, svalue FROM settings WHERE skey NOT LIKE 'apprcond%'") as $r) $cache[$r['skey']] = $r['svalue']; }
        catch (Throwable $e) { $cache = []; }
    }
    return $cache;
}
function setting_get($k, $def = null) {
    $cache = &settings_cache();
    return array_key_exists($k, $cache) ? $cache[$k] : $def;
}
// Module 14 — is a settings change a controlled, auditable event, and is its
// value a secret? System/bootstrap markers (schema/seed/cfg signatures) are NOT
// user configuration — logging them would flood the chain and fire during
// migration — so they are skipped. Secrets (passwords, API keys, tokens) are
// audited as an event, but the value itself is never written to the trail.
function setting_change_class($k) {
    $k = (string)$k;
    // Internal bootstrap / cache / cron-reminder markers — not configuration.
    if (preg_match('/(_seeded(_v\d+)?|_repaired(_v\d+)?|_sig|_checked_at|_week|_last_weekly|_last_monthly)$/', $k)) return ['audit' => false, 'secret' => false];
    //  M3 CORRECTION #15 — the approval condition ledger is a cron/repair marker,
    //  not user configuration: it is written by the scheduler, never by a person,
    //  and auditing it would put a line on the chain on every reconciliation pass.
    //  It holds no secret — condition keys, row ids and a status word.
    if (in_array($k, ['setup_done', 'schema_sig', 'admin_cfg_sig', 'partners_seeded',
                      'demo_seed_last_fail', 'demo_removed', 'billing_paid_until', 'audit_trim_anchor',
                      'appr_cond_ledger'], true)) return ['audit' => false, 'secret' => false];
    //  Condition records are written directly (one row each) and never through
    //  setting_set(), so this is belt and braces rather than the live path.
    if (strpos($k, 'apprcond') === 0) return ['audit' => false, 'secret' => false];
    // Module 46 — the signed licence key and install id are sensitive entitlement
    // artifacts; without this they were audited on the non-secret path (value on the trail).
    $secret = (bool)preg_match('/(pass|secret|token|api_?key|ai_config|rzp_key|licence_key|licence_install)/i', $k);
    return ['audit' => true, 'secret' => $secret];
}
function setting_set($k, $v) {
    $cache = &settings_cache();
    $prev  = array_key_exists($k, $cache) ? $cache[$k] : null; // capture BEFORE the overwrite
    $pdo = db();
    if (db_driver() === 'sqlite') $pdo->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?) ON CONFLICT(skey) DO UPDATE SET svalue=excluded.svalue")->execute([$k, $v]);
    else $pdo->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)")->execute([$k, $v]);
    $cache[$k] = $v; // keep in-memory cache fresh
    // Module 14 — record WHO changed WHICH setting, on the sealed audit chain that
    // already supports a 'setting' entity. Additive: the write above is unchanged;
    // logging never blocks it (guarded, and skipped for system keys / no-op writes).
    static $inLog = false;
    if (!$inLog && (string)$prev !== (string)$v && function_exists('idems_log')) {
        $cls = setting_change_class($k);
        if ($cls['audit']) {
            $inLog = true;
            try {
                if ($cls['secret']) {
                    // Log the event, never the value.
                    $old = ($prev === null || $prev === '') ? '(unset)' : '(set)';
                    $new = ($v === '' || $v === null) ? '(cleared)' : '(updated)';
                } else {
                    // Keep the trail lean: summarise very large values (logo base64,
                    // role_access JSON) rather than copying them into every entry.
                    $cap = function ($s) { $s = (string)$s; return strlen($s) > 200 ? '(' . strlen($s) . ' chars changed)' : $s; };
                    $old = ($prev === null) ? '(unset)' : $cap($prev);
                    $new = $cap($v);
                }
                idems_log('setting', 0, 'SETTING_CHANGED', ['field' => $k, 'old' => $old, 'new' => $new]);
            } catch (Throwable $e) { /* logging must never break a save */ }
            $inLog = false;
        }
    }
}

// ---- Financial year (start month is a setting; default April) --------------
function fy_start_month() { $m = (int)setting_get('fy_start_month', 4); return ($m >= 1 && $m <= 12) ? $m : 4; }
// FY label for a given date, e.g. "2025-26" (Apr start) or "2025" (Jan start).
function fy_of($date) {
    $ts = strtotime($date ?: 'now'); if ($ts === false) return '';
    $y = (int)date('Y', $ts); $m = (int)date('n', $ts); $s = fy_start_month();
    $start = ($m >= $s) ? $y : $y - 1;
    return $s === 1 ? (string)$start : sprintf('%d-%02d', $start, ($start + 1) % 100);
}
function current_fy() { return fy_of(date('Y-m-d')); }
// The financial year the whole app opens registers on by default. Normally the
// year we are actually in (from today's date), but an administrator can pin a
// specific year under Settings — e.g. to keep everyone working in the year being
// closed off, or to prepare next year's data early. A per-screen ?fy= filter
// still wins over this, and "All years" still shows everything. Stored as the FY
// label in the `fy_current` setting; blank or "AUTO" means follow today.
function active_fy() {
    $pin = strtoupper(trim((string)setting_get('fy_current', '')));
    if ($pin === '' || $pin === 'AUTO') return current_fy();
    return preg_match('/^\d{4}(-\d{2})?$/', $pin) ? $pin : current_fy();
}
// True when an administrator has pinned the year, i.e. it is not just following
// today. Used to label the top-bar chip so nobody is confused about why a
// register opened on a year that is not the calendar one.
function fy_is_pinned() {
    $pin = strtoupper(trim((string)setting_get('fy_current', '')));
    return $pin !== '' && $pin !== 'AUTO' && $pin !== strtoupper(current_fy());
}
// [from,to] YYYY-MM-DD for an FY label.
function fy_range($fyLabel) {
    $s = fy_start_month();
    $start = (int)substr($fyLabel, 0, 4);
    $from = sprintf('%04d-%02d-01', $start, $s);
    $toTs = strtotime($from . ' +1 year -1 day');
    return [$from, date('Y-m-d', $toTs)];
}
// The FY label for a starting calendar year: 2026 -> "2026-27" (or "2026" when
// the year starts in January).
function fy_label($startYear) {
    $startYear = (int)$startYear;
    return fy_start_month() === 1 ? (string)$startYear : sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
}

// ---- Shared FY filter for data screens (registers) -------------------------
// The chosen financial year for a list screen. Reads ?fy=, defaults to the
// current FY, and understands 'ALL' (every year). Returns the label, the date
// range, and a half-open upper bound `to_excl` (the day AFTER the FY end) — used
// for created_at, which is stored as an ISO datetime string, so a plain
// "<= end-date" would drop records made on the last day. This is what lets a
// register pull up any past year without the top-bar year being a global switch.
function fy_filter($default = null) {
    $sel = trim((string)($_GET['fy'] ?? ''));
    if ($sel === '') $sel = $default ?? active_fy();               // the pinned year, else this year
    if (strtoupper($sel) === 'ALL') return ['fy' => 'ALL', 'from' => '', 'to' => '', 'to_excl' => '', 'all' => true];
    if (!in_array($sel, fy_options(), true)) $sel = active_fy();   // only a year we actually offer
    [$from, $to] = fy_range($sel);
    return ['fy' => $sel, 'from' => $from, 'to' => $to, 'to_excl' => date('Y-m-d', strtotime($to . ' +1 day')), 'all' => false];
}
// A SQL fragment + args restricting a created-date column to the chosen FY.
// Empty (matches everything) when 'All years' is chosen. $fy defaults to the
// current request's fy_filter().
function fy_sql($col, $fy = null) {
    $fy = $fy ?? fy_filter();
    if (!empty($fy['all'])) return ['', []];
    return [" AND $col >= ? AND $col < ?", [$fy['from'], $fy['to_excl']]];
}
// The <select name="fy"> for a screen's own GET filter form. Drop it inside the
// existing filter <form> so the other filters are kept. Includes "All years" so
// nothing is ever hidden permanently.
function fy_select_html($current) {
    $h = '<select class="form-control" name="fy" onchange="this.form.submit()" aria-label="Financial year" title="Financial year">';
    $h .= '<option value="ALL"' . ($current === 'ALL' ? ' selected' : '') . '>All years</option>';
    foreach (fy_options() as $o) $h .= '<option value="' . e($o) . '"' . ($current === $o ? ' selected' : '') . '>FY ' . e($o) . '</option>';
    return $h . '</select>';
}

// Every financial year the data actually touches, newest first, always
// including the one we are in.
//
// The old version counted backwards from today, so a branch that entered next
// year's work could not select the year it had just typed, and a five-year-old
// record was unreachable. This looks at the real span of the data instead: put
// a job in 2028-29 and 2028-29 appears; open the app in April and the new year
// is simply there.
function fy_options($n = 6) {
    $curStart = (int)substr(current_fy(), 0, 4);
    $lo = $curStart; $hi = $curStart;
    $spans = [
        ['jobs',  ['inspection_end_date', 'inspection_start_date', 'scheduled_date', 'created_at']],
        ['calls', ['inspection_required_date', 'call_received_date', 'created_at']],
        ['crm_inquiries', ['created_at']],
        ['quotations', ['created_at']],
        // DEF-SAL-026 — a year with only leads or deals could not be picked on the Leads board.
        ['leads', ['created_at']],
        ['opportunities', ['created_at']],
    ];
    foreach ($spans as [$table, $cols]) {
        foreach ($cols as $c) {
            try {
                $r = db()->query("SELECT MIN($c) a, MAX($c) b FROM $table WHERE $c IS NOT NULL AND $c <> ''")->fetch();
            } catch (Throwable $e) { continue; }
            foreach ([$r['a'] ?? '', $r['b'] ?? ''] as $d) {
                $d = substr((string)$d, 0, 10);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) continue;
                $y = (int)substr(fy_of($d), 0, 4);
                if ($y < 1990 || $y > 2200) continue;
                $lo = min($lo, $y); $hi = max($hi, $y);
            }
        }
    }
    // Always offer the year ahead, so next year's work can be entered before it
    // starts without anybody having to wait for April.
    $hi = max($hi, $curStart + 1);
    // Always offer a pinned "current FY" too, so the year an admin selected in
    // Settings is a real, selectable option even if no data falls in it yet.
    $pinStart = (int)substr(active_fy(), 0, 4);
    if ($pinStart >= 1990 && $pinStart <= 2200) { $lo = min($lo, $pinStart); $hi = max($hi, $pinStart); }
    // A very old stray date must not produce a hundred-entry dropdown.
    $lo = max($lo, $hi - max(1, (int)$n) - 4);
    $out = [];
    for ($y = $hi; $y >= $lo; $y--) $out[] = fy_label($y);
    return $out;
}

// The months of one FY, in the order the year runs: April..March for an April
// start. Returns ['YYYY-MM' => 'April 2026', ...].
function fy_months($fyLabel) {
    [$from] = fy_range($fyLabel);
    $out = [];
    for ($i = 0; $i < 12; $i++) {
        $ts = strtotime($from . " +$i month");
        $out[date('Y-m', $ts)] = date('F Y', $ts);
    }
    return $out;
}

// One month inside an FY, or the whole FY. Returns [from, to] as YYYY-MM-DD so
// every screen filters the same way and no two of them disagree by a day.
function fy_month_range($fyLabel, $ym = '') {
    $ym = trim((string)$ym);
    if ($ym !== '' && preg_match('/^(\d{4})-(\d{1,2})$/', $ym, $m)) {
        $from = sprintf('%04d-%02d-01', (int)$m[1], (int)$m[2]);
        return [$from, date('Y-m-t', strtotime($from))];
    }
    return fy_range($fyLabel);
}

// ---- Migration -------------------------------------------------------------
function access_migrate() {
    ensure_settings_schema();
    ensure_column('users', 'home_office_id', 'INT NULL');
    ensure_column('users', 'scope_offices', "VARCHAR(255) DEFAULT ''");
    ensure_column('users', 'scope_sbus', "VARCHAR(255) DEFAULT ''");
    ensure_column('users', 'permissions', "VARCHAR(600) DEFAULT ''");
    ensure_column('users', 'reports_to_id', 'INT NULL');
    if (setting_get('fy_start_month') === null) setting_set('fy_start_month', '4');
    // C1 — Finance / Marketing Manager keep every office they had (once).
    access_migrate_central_roles_scope(true);
    // One-time: remove the Candles/Wax/Tier demo (the confusing "Product line" field).
    if (setting_get('demo_removed') === null) {
        try {
            db()->exec("DELETE FROM custom_fields WHERE entity='call' AND field_key='product_line'");
            foreach (['tier','wax_type','product_family'] as $k) {
                $t = ops_one("SELECT id FROM lookup_types WHERE type_key=? AND is_system=0", [$k]);
                if ($t) { db()->prepare("DELETE FROM lookup_values WHERE type_id=?")->execute([$t['id']]); db()->prepare("DELETE FROM lookup_types WHERE id=?")->execute([$t['id']]); }
            }
        } catch (Throwable $e) {}
        setting_set('demo_removed', '1');
    }
}
