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
    'FINANCE' => 'Finance', 'SR_INSPECTOR' => 'Senior Inspector', 'INSPECTOR' => 'Inspector', 'ADMIN' => 'Admin (legacy)',
];
// Operations/branch management roles — the ones is_admin_level()/is_coordinator_level()
// are meant to identify (they run calls, jobs, scheduling, vouchers, recruitment).
// SALES roles (BDM/KAM/Marketing) and FINANCE are deliberately NOT here: they are
// not operations managers, and lumping them in made every is_admin_level-gated
// operational widget/action leak to them (a salesperson seeing "vouchers to
// approve", an accountant seeing "raise inspection call"). Their own CRM/finance
// access is enforced by module/permission can() checks, which is unaffected.
const MGMT_ROLES = ['MASTER_ADMIN','ADMIN','BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER','BRANCH_APP_MANAGER','OPERATION_MANAGER'];
// Marketing & Sales roles (CRM funnel owners)
const SALES_ROLES = ['BUSINESS_DEV_MANAGER','KEY_ACCOUNTS_MANAGER','MARKETING_MANAGER','MARKETING_EXECUTIVE'];

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
];

// Human-friendly grouping of every permission, so the access editor reads clearly
// and an admin can see at a glance which area each permission belongs to.
function permission_groups() {
    return [
        'Dashboards & sensitive figures' => ['dash.operations','dash.financial','dash.utilization','dash.people','data.credit','data.revenue','data.salary','data.profitability'],
        'Operations (calls & jobs)'      => ['ops.call.create','ops.job.allocate','ops.job.close','ops.call.delete','workforce.availability','workforce.report.approve'],
        'Inspection documentation (IDEMS)' => ['idems.finalize','idems.type.manage','idems.timestamp.edit','idems.audit.view'],
        'Money'                          => ['finance.reconcile'],
        'Marketing & Sales (CRM)'        => ['crm.quote.create','crm.quote.approve','crm.quote.send','crm.followup.manage','crm.contract.register','crm.template.manage'],
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
function module_groups() {
    $g = [
        'Marketing & Sales (CRM)' => ['leads','inquiries','quotes','crm_orders','crm_reports'],
        'Inspection documentation' => ['idems'],
        'Operations'              => ['calls','jobs','vouchers','invoicing','profitability','hiring','reconcile'],
        'Accreditation & compliance' => ['equipment','competence','impartiality','complaints','ncr','capa','audits','datacontrol','identity','confidentiality'],
        'Directory & masters'     => ['clients','vendors','masters','overheads','portal'],
        'Insights & admin'        => ['reports','users','settings'],
    ];
    $claimed = array_merge(...array_values($g));
    $orphans = array_values(array_diff(array_keys(ACCESS_MODULES), $claimed));
    if ($orphans) $g['Not yet grouped'] = $orphans;
    return $g;
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
        'Sales'                   => ['crm.quote.create','crm.quote.approve','crm.quote.send','crm.followup.manage','crm.contract.register','crm.template.manage'],
        'Operations'              => ['ops.call.create','ops.job.allocate','ops.job.close','ops.call.delete','workforce.availability','workforce.report.approve'],
        'Reporting'               => ['idems.finalize','idems.type.manage','idems.timestamp.edit','idems.template.approve'],
        'Money'                   => ['data.credit','data.revenue','data.salary','data.profitability','finance.reconcile'],
        'Quality & Accreditation' => ['idems.audit.view','person.iddoc.view','person.iddoc.manage','complaints.decide','capa.close','ncr.close'],
        'Insights'                => ['dash.operations','dash.financial','dash.utilization','dash.people'],
        'Directory'               => ['master.manage'],
        'Admin'                   => ['users.manage.branch','users.manage.global','settings.manage','org.hierarchy.view'],
    ];
    // module view/edit access, by nav area — each key expands to .view + .edit
    $mods = [
        'Sales'                   => ['leads','inquiries','quotes','crm_orders','crm_reports'],
        'Operations'              => ['calls','jobs','vouchers','hiring','reconcile'],
        'Reporting'               => ['idems'],
        'Money'                   => ['invoicing','profitability','overheads'],
        'Quality & Accreditation' => ['equipment','competence','impartiality','identity','complaints','ncr','confidentiality','capa','audits','datacontrol'],
        'Insights'                => ['reports'],
        'Directory'               => ['clients','vendors','masters','portal'],
        'Admin'                   => ['users','settings'],
    ];
    $groups = [];
    foreach ($fine as $g => $keys) $groups[$g] = $keys;
    foreach ($mods as $g => $keys) {
        foreach ($keys as $k) { $groups[$g][] = "mod.$k.view"; $groups[$g][] = "mod.$k.edit"; }
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
    'hiring'        => 'Hiring / candidates',
    'reconcile'     => 'Attendance reconcile',
    'clients'       => 'Clients',
    'vendors'       => 'Vendors',
    'equipment'     => 'Equipment & calibration',
    'competence'    => 'Competence & authorisation',
    'impartiality'  => 'Impartiality & conflicts',
    'identity'      => 'Identity documents (site access)',
    'complaints'    => 'Complaints & appeals',
    'leads'         => 'Leads &amp; pipeline',
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
    if (strncmp($key, 'mod.', 4) === 0 && preg_match('/^mod\.(.+)\.(view|edit)$/', $key, $mm)) {
        $lbl = access_module_label($mm[1]);
        $extra = [
            'users'         => ' Controls login accounts and their access — administrators only.',
            'settings'      => ' System configuration — administrators only.',
            'profitability' => ' Shows margin / profit — managers & finance only.',
            'invoicing'     => ' The billing module — finance / accounts.',
            'identity'      => ' Personal identity documents — grant deliberately.',
            'overheads'     => ' Office finance figures — finance / managers.',
        ][$mm[1]] ?? '';
        return $mm[2] === 'edit'
            ? 'Add and change records in "' . $lbl . '" (also lets them view it).' . $extra
            : 'See the "' . $lbl . '" screens, read-only.' . $extra;
    }
    return PERMISSIONS[$key] ?? $key;
}

// Full permission map = fine-grained perms + per-module view/edit perms.
function all_permissions() {
    $p = [];
    foreach (PERMISSIONS as $k => $lbl) $p[$k] = perm_label($k, $lbl);
    foreach (ACCESS_MODULES as $k => $lbl) {
        $l = access_module_label($k);
        $p["mod.$k.view"] = "$l — view";
        $p["mod.$k.edit"] = "$l — add / edit";
    }
    return $p;
}

// Which modules each role can view / edit by default (edit implies view).
// Used as the starting point; the super admin can override per role / per user.
function module_defaults($role) {
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
            $edit = ['masters','overheads','users'];
            $view = ['calls','jobs','reports']; break;
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
            $edit = ['invoicing','crm_orders'];
            $view = ['quotes','crm_reports','profitability','reports','jobs','calls','vouchers','idems']; break;
        case 'INSPECTOR': case 'SR_INSPECTOR': $edit = ['idems']; break; // inspectors write reports; else My Jobs / My Voucher
    }
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
    foreach ($edit as $k) { $out[] = "mod.$k.view"; $out[] = "mod.$k.edit"; }
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

function merge_new_module_defaults($perms, $role) {
    $known = modules_at_last_save();
    foreach (module_defaults($role) as $dp) {
        if (!preg_match('/^mod\.(\w+)\.(view|edit)$/', $dp, $m)) continue;
        $isNew = $known === null ? in_array($m[1], NEW_MODULES, true) : !in_array($m[1], $known, true);
        if ($isNew && !in_array($dp, $perms, true)) $perms[] = $dp;
    }
    return array_values(array_unique($perms));
}

// Effective default permission set for a role: a super-admin override stored in
// settings (Roles & access) wins; otherwise the built-in role defaults.
function role_perms($role) {
    $raw = setting_get('role_access', '');
    if ($raw !== '') {
        $ov = json_decode($raw, true);
        if (is_array($ov) && isset($ov[$role]) && is_array($ov[$role])) return merge_new_module_defaults(array_values($ov[$role]), $role);
    }
    return role_defaults($role)['perms'];
}

// Role defaults: permissions granted, and scope (offices/sbus: ALL | OWN).
// Wrapper merges the per-module view/edit defaults onto the fine-grained ones.
function role_defaults($role) {
    $d = role_defaults_base($role);
    $d['perms'] = array_values(array_unique(array_merge($d['perms'], module_defaults($role))));
    return $d;
}

// The effective permission set for a given user ROW (not the current session) —
// the same resolution ua() applies, factored out so the access editor can pre-tick
// exactly what a login actually has (R3). Master = everything; a per-user override
// wins (with the legacy module back-fill); otherwise the role's effective set.
function user_effective_perms($u) {
    $role = !empty($u['is_superuser']) ? 'MASTER_ADMIN' : strtoupper($u['role'] ?? 'ADMIN');
    if (!isset(ORG_ROLES[$role])) $role = 'ADMIN';
    if ($role === 'MASTER_ADMIN') return array_keys(all_permissions());
    if (trim((string)($u['permissions'] ?? '')) !== '') {
        $perms = array_values(array_filter(explode(',', (string)$u['permissions'])));
        $hasMod = false; foreach ($perms as $p) if (strncmp($p, 'mod.', 4) === 0) { $hasMod = true; break; }
        if (!$hasMod) $perms = array_merge($perms, module_defaults($role));   // pre-module logins keep module access
        else $perms = merge_new_module_defaults($perms, $role);               // grant brand-new modules
        return array_values(array_unique($perms));
    }
    return role_perms($role);
}

// The permissions an administrator is allowed to TOGGLE on the access editor. A
// global user-manager may set anything; a branch-level manager may set only a safe
// operational subset (never salary / global-user / settings). Used by BOTH the form
// (what it renders) and the save handler (what it accepts + what it must preserve),
// so the two can never drift and silently drop a permission the editor never saw.
function assignable_permissions($globalMgr) {
    if ($globalMgr) return all_permissions();
    $keys = ['mod.calls.view','mod.calls.edit','mod.jobs.view','mod.jobs.edit','mod.vouchers.view','mod.vouchers.edit',
        'mod.hiring.view','mod.hiring.edit','mod.reconcile.view','mod.reconcile.edit','mod.clients.view','mod.vendors.view',
        'mod.masters.view','mod.masters.edit','mod.reports.view','mod.invoicing.view',
        'dash.operations','dash.utilization','data.credit','ops.call.create','ops.job.allocate','ops.job.close','master.manage'];
    return array_intersect_key(all_permissions(), array_flip($keys));
}
function role_defaults_base($role) {
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
            return ['perms' => ['dash.operations','dash.financial','data.credit','data.revenue','data.profitability','crm.quote.create','crm.quote.approve','crm.quote.send','crm.followup.manage','crm.template.manage'], 'offices' => 'ALL', 'sbus' => 'ALL'];
        case 'MARKETING_EXECUTIVE':
            return ['perms' => ['crm.quote.create','crm.followup.manage'], 'offices' => 'OWN', 'sbus' => 'OWN'];
        case 'FINANCE':
            return ['perms' => ['dash.financial','data.credit','data.revenue','data.salary','data.profitability','finance.reconcile','crm.contract.register'], 'offices' => 'ALL', 'sbus' => 'ALL'];
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
function ua($fresh = false) {
    static $a = null;
    if (!$fresh && $a !== null) return $a;
    if ($fresh) $a = null;
    $u = current_user($fresh);
    if (!$u) return $a = ['role' => 'GUEST', 'perms' => [], 'offices' => [], 'sbus' => [], 'self' => true, 'home' => null, 'master' => false];
    $role = !empty($u['is_superuser']) ? 'MASTER_ADMIN' : strtoupper($u['role'] ?? 'ADMIN');
    if (!isset(ORG_ROLES[$role])) $role = 'ADMIN';
    $def = role_defaults($role);
    // permissions: per-user csv override wins; else the role's effective set
    // (which itself honours a Settings → Roles & access override). Master = all.
    // Resolved by user_effective_perms() so the access editor pre-ticks EXACTLY
    // what this same choke point grants (R3 — no drift between what is shown and
    // what is enforced).
    $perms = user_effective_perms($u);
    // office scope
    $so = trim((string)($u['scope_offices'] ?? ''));
    if ($role === 'MASTER_ADMIN' || $def['offices'] === 'ALL' && $so === '') $offices = 'ALL';
    elseif ($so === 'ALL') $offices = 'ALL';
    elseif ($so !== '') $offices = array_map('intval', array_filter(explode(',', $so)));
    else $offices = $u['home_office_id'] ? [(int)$u['home_office_id']] : 'ALL'; // OWN default
    // sbu scope
    $ss = trim((string)($u['scope_sbus'] ?? ''));
    if ($role === 'MASTER_ADMIN' || ($def['sbus'] === 'ALL' && $ss === '')) $sbus = 'ALL';
    elseif ($ss === 'ALL') $sbus = 'ALL';
    elseif ($ss !== '') $sbus = array_filter(explode(',', $ss));
    else $sbus = 'ALL';
    return $a = [
        'role' => $role, 'perms' => array_values($perms),
        'offices' => $offices, 'sbus' => $sbus,
        'self' => $role === 'INSPECTOR', 'home' => $u['home_office_id'] ?? null,
        'master' => $role === 'MASTER_ADMIN',
    ];
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
    return $a['master'] || in_array($perm, $a['perms'], true);
}
function scope_offices() { return ua()['offices']; } // 'ALL' or int[]
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
    if ($off === 'ALL' || !is_array($off) || !$off) return ['1=1', []];
    $ids = implode(',', array_map('intval', $off));
    return ["($officeCol IS NULL OR $officeCol IN ($ids))", []];
}

// Build a WHERE fragment scoping calls/jobs by office + Business Unit. $officeCol is the
// column holding the executing office id (nullable → treated as Ahmedabad).
function scope_clause($officeCol, $sbuCol) {
    $off = scope_offices(); $sbu = scope_sbus();
    $w = []; $args = [];
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
function theme_style_tag() {
    $primary = theme_hex(setting_get('c_primary', '')) ?: (theme_hex(setting_get('brand_color', '')) ?: '#1e40af');
    $accent  = theme_hex(setting_get('c_accent', ''))  ?: '#0ea5e9';
    $bg      = theme_hex(setting_get('c_bg', ''))       ?: '#f4f6f9';
    $surface = theme_hex(setting_get('c_surface', ''))  ?: '#ffffff';
    $ink     = theme_hex(setting_get('c_text', ''))     ?: '#1f2937';
    $fs = (int)(setting_get('font_size', '') ?: 14); if ($fs < 12 || $fs > 20) $fs = 14;
    $soft  = theme_mix($surface, $ink, 0.05);
    $line  = theme_mix($surface, $ink, 0.14);
    $muted = theme_mix($surface, $ink, 0.45);
    $field = theme_mix($surface, $ink, 0.04);
    $isDark = theme_lum($surface) < 128;
    $shadow   = $isDark ? '0 1px 2px rgba(0,0,0,.4), 0 12px 34px rgba(0,0,0,.5)' : '0 1px 2px rgba(18,32,60,.05), 0 10px 30px rgba(18,32,60,.08)';
    $shadowSm = $isDark ? '0 1px 2px rgba(0,0,0,.45)' : '0 1px 2px rgba(18,32,60,.06)';
    $navtext = theme_lum($primary) > 150 ? '#111827' : '#ffffff';
    $navlink = theme_lum($primary) > 150 ? 'rgba(17,24,39,.72)' : 'rgba(255,255,255,.82)';
    return "<style>:root{--brand:$primary;--accent:$accent;--bg:$bg;--card:$surface;--panel:$soft;--ink:$ink;--soft:$soft;--line:$line;--muted:$muted;--field:$field;--field-line:$line;--shadow:$shadow;--shadow-sm:$shadowSm;--fs:{$fs}px}"
        . "body{font-size:var(--fs)}.stat-card,.master-card{background:var(--soft)}"
        . ".topbar .brand,.topbar .user{color:$navtext}.topbar nav a{color:$navlink}.topbar nav a:hover{color:$navtext}"
        . "</style>";
}
function logo_html() {
    $d = setting_get('logo_data', '');
    return $d ? '<img src="' . e($d) . '" alt="logo" style="height:30px;vertical-align:middle;background:#fff;border-radius:4px;padding:2px 6px">' : '';
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
    if ($cache === null) {
        $cache = [];
        try { foreach (ops_all("SELECT skey, svalue FROM settings") as $r) $cache[$r['skey']] = $r['svalue']; }
        catch (Throwable $e) { $cache = []; }
    }
    return $cache;
}
function setting_get($k, $def = null) {
    $cache = &settings_cache();
    return array_key_exists($k, $cache) ? $cache[$k] : $def;
}
function setting_set($k, $v) {
    $pdo = db();
    if (db_driver() === 'sqlite') $pdo->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?) ON CONFLICT(skey) DO UPDATE SET svalue=excluded.svalue")->execute([$k, $v]);
    else $pdo->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)")->execute([$k, $v]);
    $cache = &settings_cache(); $cache[$k] = $v; // keep in-memory cache fresh
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
