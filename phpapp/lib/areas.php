<?php
// ============================================================================
//  AREA HOMES — the flat-navigation engine.
//
//  The left rail is a flat list of areas; tapping one navigates to that area's
//  Home, a page that lays out every screen in the area as a tile (instead of a
//  folding menu group). This file is the single source of truth for what each
//  area contains: the same permission and feature gates the sidebar used, in
//  one place, so the rail link and the landing page can never disagree.
//
//  Tiles can carry a live count badge and are grouped into sections; a big area
//  (Quality, Reporting, Admin) reads as a few labelled groups rather than a
//  wall. Operations has its own richer Home (ops_operations_home).
//
//  Nothing is removed — each tile links to the screen that already exists.
// ============================================================================

// Build the definition for one area: title, icon, subtitle, the visible tiles
// grouped into sections (already filtered by permission), and the route
// prefixes that mark the rail link "current". Only tiles the user may open are
// returned, so an empty area means "no access".
function ops_area_def($area) {
    $inspPack = !function_exists('accredited_pack_on') || accredited_pack_on();
    $fx = fn($f) => function_exists($f);
    // A positive integer or null. Never let a count query break the page.
    $num = function ($fn) { try { $v = (int)$fn(); return $v > 0 ? $v : null; } catch (Throwable $e) { return null; } };

    $sections = [];
    // Start a new (optionally labelled) section.
    $sec = function ($label = '') use (&$sections) { $sections[] = ['label' => $label, 'tiles' => []]; };
    // Append a tile to the current section. $count -> a badge; $tone one of
    // 'red' | 'amber' | 'green' | '' (neutral). $ext opens in a new tab.
    $t = function ($show, $icon, $label, $route, $desc = '', $count = null, $tone = '', $ext = false) use (&$sections) {
        if (!$show) return;
        if (empty($sections)) $sections[] = ['label' => '', 'tiles' => []];
        $sections[count($sections) - 1]['tiles'][] = [
            'icon' => $icon, 'label' => $label, 'route' => $route, 'desc' => $desc,
            'count' => $count, 'tone' => $tone, 'ext' => $ext,
        ];
    };

    switch ($area) {
        case 'marketplace':
            // Connect — the technical-manpower marketplace, dissolved into EXAACT.
            // The whole area appears only when the module is enabled and the
            // viewer is coordinator/master (connect_market_can). One integrated
            // area, cleanly optional.
            $title = 'Marketplace'; $icon = '🧑‍🏭';
            $sub = 'Post technical-manpower requirements, match and shortlist professionals, and their public passports.';
            $routes = ['connect-requirements','connect-requirement','connect-concierge','connect-talent','connect-orgs','passport-share','connect-taxonomy','connect-qualifications','connect-verify','connect-messages','connect-channels','connect-bench','connect-analytics'];
            $on = $fx('connect_market_can') && connect_market_can();
            if ($on) {
                $sec('');
                $t(true, '📋', 'Requirements', '/connect-requirements', 'Post a manpower requirement and manage who applies.',
                    $num(fn() => $fx('cx_market_summary') ? (cx_market_summary()['open'] ?? 0) : 0), 'green');
                $t(true, '💬', 'Guided post', '/connect-concierge', 'Build a requirement by answering a few questions.');
                $t(true, '🔎', 'Talent search', '/connect-talent', 'Search the shared pool of self-listed professionals.',
                    $num(fn() => $fx('connect_pro_pool_count') ? connect_pro_pool_count() : 0));
                $t(true, '🪪', 'Passports', '/passport-share', 'A professional\'s public, verifiable credential page.');
                $t(true, '🏭', 'Industry taxonomy', '/connect-taxonomy', 'Sectors, equipment, materials, disciplines, standards, certifications.');
                $t(true, '🎓', 'Qualification taxonomy', '/connect-qualifications', 'ITI → diploma → engineer → MBA ladder, job families, roles and certifications.');
                $t(true, '✅', 'Verification desk', '/connect-verify', 'Confirm identity & credential checks; move professionals up the trust ladder.',
                    $num(fn() => $fx('connect_verify_pending_count') ? connect_verify_pending_count() : 0), 'amber');
                $t(true, '⚖️', 'Rating-integrity desk', '/rating-disputes', 'Investigate reported ratings; uphold, annotate or remove them from scores.',
                    $num(fn() => $fx('cx_rating_disputes_open_count') ? cx_rating_disputes_open_count() : 0), 'amber');
                $t(true, '💬', 'Messages', '/connect-messages', 'Two-way chat with applicants, tied to each requirement.',
                    $num(fn() => $fx('connect_msg_staff_unread') ? connect_msg_staff_unread() : 0));
                $t(true, '📲', 'Channels', '/connect-channels', 'WhatsApp / SMS / email alerts, templates and delivery log.');
                $t(true, '🏗️', 'Agency bench', '/connect-bench', 'An agency\'s own private roster — add people and allocate them to jobs.');
                $t(true, '📊', 'Market analytics', '/connect-analytics', 'Supply vs demand, fill funnel, time-to-award, rate benchmarks, pool growth.');
                $t(is_master(), '🏢', 'Organisations', '/connect-orgs', 'Register organisations and their module entitlements (TPIA / agency / company).',
                    $num(fn() => $fx('connect_org_pending_count') ? connect_org_pending_count() : 0), 'amber');
            }
            break;
        case 'sales':
            $title = 'Sales'; $icon = '🎯';
            $sub = 'Leads, opportunities, ' . strtolower(THP('inquiry')) . ', ' . strtolower(THP('quote')) . ' and the pipeline.';
            $routes = ['sales','leads','lead','opportunities','opportunity','inquiries','inquiry','quotes','quote','pipelines','pipeline','approvals','stage-gates','ads-roi','project-costings','project-costing','preorder-checklist','templates'];
            $t($fx('leads_can_view') && leads_can_view(), '🎯', 'Leads', '/leads', 'A company worth pursuing — before any specific job.');
            $t($fx('opp_can_view') && opp_can_view(), '💡', 'Opportunities', '/opportunities', 'A live deal you are working to win or lose.');
            $t(can('mod.inquiries.view'), '📨', THP('inquiry'), '/inquiries', 'A specific request to quote — a ' . strtolower(Tl('quote')) . ' is raised from it.');
            $t(can('mod.quotes.view'), '📝', THP('quote'), '/quotes', 'Quotations, revisions and approvals.');
            // R11 — document / template library for marketing (crm.template.manage) moved
            // here from Admin, so a marketing manager reaches it in Sales — where their work
            // is — instead of via an Admin area that implied administrative power.
            $t(can('crm.template.manage') || is_master(), '📝', 'Document templates', '/templates', 'The document / report template library.');
            $t(is_master() || can('settings.manage') || can('crm.quote.approve'), '☑', 'Pre-order checklist', '/preorder-checklist', 'Enquiry / tender / contract review before a quote is approved.');
            $t(function_exists('pc_can') && pc_can(), '🧮', 'Project costing', '/project-costings', 'Team cost build-ups → man-month / man-day / lump rates and margin.');
            $t($fx('gate_can_view') && gate_can_view(), '🛂', 'Approvals', '/approvals', 'Deals held at a stage gate.',
                $num(fn() => $fx('gate_pending_count') ? gate_pending_count() : 0), 'amber');
            // Sales dashboard now lives under Insights (one dashboards home).
            $t($fx('pipe_can_view') && pipe_can_view(), '🪜', 'Pipelines & funnels', '/pipelines', 'Stages and conversion.');
            $t($fx('roi_available') && roi_available() && $fx('roi_can') && roi_can(), '💸', 'Advertising return', '/ads-roi', 'Spend against leads produced.');
            break;

        // Stage 6 Combination Engine: the quality area's ISO/inspection registers
        // ($inspPack tiles) show only when the operating company does inspection —
        // a pure recruiter/staffer never sees them. Permissive until an operating
        // company is designated. (connect_cap_owner_does_inspection, connect_capability.php)
        case 'quality':
            $title = 'Quality & Accreditation'; $icon = '🛡️';
            $sub = 'Two halves: the everyday quality work you touch during jobs, and the accreditation registers an assessor asks for.';
            $routes = ['quality','equipment','samples','sample','methods','method','drules','drule','cdocs','cdoc','risks','risk','retention','disclosure','competence','impartiality','complaints','complaint','satisfaction','confidentiality','conf-breach','site-docs','report-reviews','ncr','issues','departures','hold-points','capa','internal-audits','internal-audit','management-reviews','management-review','evidence-review','data-control','identity','sla-targets'];
            if (function_exists('connect_cap_owner_does_inspection')) $inspPack = $inspPack && connect_cap_owner_does_inspection();

            // ── Everyday quality — the things you touch during live jobs. ──
            $sec('Everyday quality');
            $t(can('mod.complaints.view'), '📮', 'Complaints & appeals', '/complaints', 'Complaints and appeals register.',
                $num(fn() => $fx('cmp_all') ? count(cmp_all(['status' => 'OPEN'])) : 0), 'amber');
            $t($fx('rcr_can_view') && rcr_can_view(), '📬', 'Client acceptance', '/report-reviews', 'Reports accepted or rejected.',
                $num(fn() => $fx('rcr_counts') ? rcr_counts()['rejected'] : 0), 'red');
            $t($fx('sat_can_view') && sat_can_view(), '⭐', 'Customer satisfaction', '/satisfaction', 'Surveys and follow-up.',
                $num(fn() => $fx('sat_summary') ? sat_summary()['followup'] : 0), 'amber');
            $t($fx('sample_can_view') && sample_can_view(), '📦', 'Items & samples', '/samples', 'Items received for verification.',
                $num(fn() => $fx('sample_counts') ? sample_counts()['open'] : 0), 'amber');
            $t($fx('hwp_can_view') && hwp_can_view(), '✋', 'Hold & witness points', '/hold-points', 'Points where work pauses for us.',
                $num(fn() => $fx('hwp_open_all') ? count(hwp_open_all($fx('scope_offices') && is_array(scope_offices()) ? scope_offices() : null)) : 0), 'amber');
            $t($inspPack && $fx('trust_can_review') && trust_can_review(), '📍', 'Evidence review', '/evidence-review', 'Field evidence awaiting review.',
                $num(fn() => $fx('trust_readiness') ? trust_readiness()['pending'] : 0), 'amber');
            // Issues, NCRs, corrective actions and departures are one family — now
            // one tabbed workspace. The tile lands on the umbrella Issue register
            // and the tab strip carries you across NCR / CAPA / Departures. The
            // count badge is open nonconformities, the thing worth chasing.
            $t($inspPack && (can('mod.ncr.view') || can('mod.capa.view')), '⚠', 'Nonconformity workspace',
                (($fx('ncdca_enabled') && ncdca_enabled()) ? '/issues' : '/ncr'),
                'Issues, NCRs, corrective actions and departures — one screen with tabs.',
                $num(fn() => $fx('ncr_counts') ? ncr_counts()['open'] : 0), 'red');

            // ── Accreditation registers — the set-once/periodic records an assessor asks for. ──
            $sec('Accreditation registers');
            $t($inspPack && can('mod.equipment.view'), '📏', 'Equipment & calibration', '/equipment', 'Instruments and calibration status.');
            $t($fx('method_can_view') && method_can_view(), '📚', 'Method library', '/methods', 'Standards and methods applied.');
            $t($fx('drule_can_view') && drule_can_view(), '⚖️', 'Decision rules', '/drules', 'Statements of conformity rules.');
            $t($fx('cdoc_can_view') && cdoc_can_view(), '📄', 'Controlled documents', '/cdocs', 'The controlled document set.',
                $num(fn() => $fx('cdoc_counts') ? cdoc_counts()['review_due'] : 0), 'amber');
            $t($fx('retention_can_view') && retention_can_view(), '🗄️', 'Retention schedule', '/retention', 'How long records are kept.');
            $t($inspPack && can('mod.datacontrol.view'), '🗃', 'Data & information control', '/data-control', 'Information management controls.');
            $t($fx('risk_can_view') && risk_can_view(), '🎲', 'Risks & opportunities', '/risks', 'The risk register.',
                $num(fn() => $fx('risk_counts') ? risk_counts()['high'] : 0), 'red');
            $t($inspPack && can('mod.competence.view'), '🎓', 'Competence & authorisation', '/competence', 'Training, assessment and authorisation.');
            $t($inspPack && can('mod.impartiality.view'), '⚖️', 'Impartiality', '/impartiality', 'Threats to impartiality and controls.');
            $t($fx('disclosure_can_view') && disclosure_can_view(), '📢', 'Disclosure consent', '/disclosure', 'Consents to disclose.',
                $num(fn() => $fx('disclosure_counts') ? disclosure_counts()['pending'] : 0), 'amber');
            $t($inspPack && can('mod.audits.view'), '🔍', 'Internal audits', '/internal-audits', 'The internal audit programme.');
            $t($inspPack && can('mod.audits.view'), '🏛', 'Management review', '/management-reviews', 'Management review records.');
            $t(can('mod.confidentiality.view') || can('mod.identity.view') || is_master_of(['confidentiality','identity']), '🔒', 'Confidentiality', '/confidentiality', 'Undertakings, NDAs and breaches.');
            $t($fx('ops_sitedocs') && licence_enabled('operations') && (can('mod.identity.view') || can('mod.clients.view') || is_master_of(['identity','clients'])), '🛂', 'Site entry documents', '/site-docs', 'Papers needed for site access.');
            $t(can('mod.identity.view') && $fx('iddoc_can_view') && iddoc_can_view(), '🪪', 'Identity documents', '/identity', 'ID that gates site access.');
            // R11 — SLA / turnaround targets moved here from Admin. It is a
            // service-delivery setting, not core administration, so surfacing it here
            // stops it forcing coordinators and asst. managers into the Admin area
            // (which implied administrative power they do not have).
            $t(can('settings.manage') || is_master() || ($fx('is_coordinator_level') && is_coordinator_level()), '⏳', 'SLA targets', '/sla-targets', 'Turnaround targets for service delivery.');
            // Client & vendor portal administration now lives under Directory (it is
            // portal admin, not a quality register). Analytics lives under Insights;
            // the duplicate that was here is parked in Admin.
            break;

        // Stage 6 Combination Engine: the inspection Reporting area shows only when
        // the operating company's capabilities produce reports (inspection work or
        // freelance-inspector supply). Permissive until an operating company is set.
        case 'reporting':
            $title = 'Reporting'; $icon = '📑';
            $sub = 'Where inspection reports are written, endorsed, expedited and the formats that govern them.';
            $routes = ['reporting','documents','document','endorsements','endorsement','vendors','vendor-profile','expediting','expediting-projects','writing-assistant','phrase-library','learning','compliance'];
            if ((!function_exists('connect_cap_owner_shows') || connect_cap_owner_shows('reporting')) && can('mod.idems.view')) {
                $sec('Reports');
                $t(true, '📑', T_REG('report'), '/documents', 'The report register.');
                $t(can('mod.idems.edit') || is_master_of('idems'), '➕', ucfirst(T_NEW('report')), '/document-new', 'Start a new report.');
                $t(true, '✅', T_REG('endorsement'), '/endorsements', 'Manufacturer document endorsements.');
                // Vendor register lives under Directory; the duplicate that was here is parked in Admin.

                $sec('Expediting');
                $t(!$fx('svc_globally_active') || svc_globally_active('EXPEDITING'), '🚚', 'Expediting register', '/expediting', 'Chasing vendor delivery.');
                $t(!$fx('svc_globally_active') || svc_globally_active('EXPEDITING'), '🗂️', 'Project delivery', '/expediting-projects', 'Multi-vendor projects by milestone.');

                $sec('Writing');
                $t(true, '✒️', 'Technical writing', '/writing-assistant', 'Phrase library and writing help.');
                $t(true, '🧠', 'Learning insights', '/learning', 'Suggestions learned from past reports.');
                // Approver mapping, Approval rules, Document templates and the audit
                // trail are set-once configuration — they now live under Admin so
                // Reporting stays about writing reports.

                $sec('Governance');
                $t(is_master() || can('settings.manage'), '⚖️', 'Where we stand', '/compliance', 'Which obligations are met, measured live.');
            }
            break;

        case 'money':
            $title = 'Money'; $icon = '💰';
            $sub = 'From what is waiting to be billed, through invoices and money in, to the profit each ' . strtolower(Tl('sbu')) . ' makes.';
            $routes = ['money','invoicing','to-bill','invoices','invoice','receipts','receipt','receivables','tally','profitability','sbu-pl','office-finance','cost-run','call-profit','billable-events','revenue-reconciliation','cost-reconciliation','reimbursable-dedup'];
            $sec('Billing');
            // Revamp P4 — the operational→commercial bridge: approved work on its
            // way to an invoice, so nothing done is lost before it is billed.
            if (can('mod.invoicing.view') && $fx('billable_can') && billable_can()) {
                $t(true, '🧾', 'Billable events', '/billable-events', 'Approved operational work waiting to be invoiced.',
                    $num(fn() => $fx('billable_pending_count') ? billable_pending_count() : 0), 'amber');
            }
            // A quick per-job tracker (invoiced? paid? credit received?) that feeds
            // profitability — kept as its own tile because everyone uses it.
            $t(can('mod.invoicing.view'), '💳', 'Invoice tracker', '/invoicing', 'Tick each job: invoiced, paid, inter-office credit.');
            // The five accounts screens are now one tabbed workspace — bill closed
            // work, raise invoices, match money in, watch ageing, export to Tally.
            // Every route still resolves; the tab strip carries you across them.
            if (can('mod.invoicing.view') && (can('finance.reconcile') || can('data.credit') || is_master())) {
                $t(true, '🧾', 'Billing workspace', '/to-bill', 'Bill, invoice, collect, age and export — one screen with tabs.');
            }
            $t($fx('books_switch_ready') && books_switch_ready(), '📗', 'Accounts & GST ↗', ($fx('books_app_url') ? books_app_url() : '#'), 'Open MGH Books, already signed in.', null, '', true);
            // Revamp §29 — where the legacy invoice snapshot disagrees with the ledger.
            $t(($fx('can_see_salary') && can_see_salary()) || can('finance.reconcile') || is_master(), '⚖️', 'Revenue reconciliation', '/revenue-reconciliation', 'Where a job’s legacy invoice figure disagrees with the books ledger.',
                $num(fn() => $fx('revrecon_count') ? revrecon_count() : 0), 'amber');

            // Costs & margins — moved here from Admin so all of money lives together.
            $sec('Costs & margins');
            $t(can('mod.profitability.view'), '💹', T_REG('boss'), '/profitability', 'Profit and margin by job.');
            $t(can('mod.overheads.view'), '📐', TH('office') . ' costs & overheads', '/office-finance', 'Per-office cost model.');
            $t(can('mod.overheads.view'), '🧮', 'Month-end cost run', '/cost-run', 'Roll up costs for the month.');
            // Revamp P8 — where a job's legacy sub-contractor cost disagrees with what a committed cost run put in the ledger.
            $t(($fx('can_see_salary') && can_see_salary()) || can('finance.reconcile') || is_master(), '⚖️', 'Cost reconciliation', '/cost-reconciliation', 'Where a job’s legacy sub-contractor cost disagrees with the committed cost ledger.',
                $num(fn() => $fx('costrecon_count') ? costrecon_count() : 0), 'amber');
            // Revamp P8 — reimbursables recorded on both doors (closure expenses + inspector voucher); reconcile before profit is trusted.
            $t(($fx('can_see_salary') && can_see_salary()) || can('finance.reconcile') || can('mod.profitability.view') || is_master(), '🧾', 'Reimbursable duplication', '/reimbursable-dedup', 'Jobs whose reimbursables are recorded on both doors — reconcile so profit is not double-charged.',
                $num(fn() => $fx('cost_dualwrite_count') ? cost_dualwrite_count() : 0), 'amber');
            $t(can('mod.profitability.view'), '📊', T('sbu') . ' profit & loss', '/sbu-pl', 'P&L by business unit.');
            $t(can('mod.profitability.view'), '🧾', 'Profit by ' . strtolower(Tl('call')), '/call-profit', 'What each inspection made.');
            break;

        case 'insights':
            $title = 'Insights'; $icon = '📊';
            $sub = 'One home for every dashboard — role views, the sales view, the management overview and the live analytics hub.';
            $routes = ['insights','reports','analytics','analytics-kpis','analytics-quality','analytics-drill','crm-dashboard','mis'];
            $t(can('mod.reports.view'), '📊', 'Dashboards', '/reports', 'Role dashboards across the business.');
            // The analytics hub was buried under Quality and hard to find; surface it here too.
            $t($fx('tapi_can') && tapi_can(), '📈', 'Analytics & performance', '/analytics', 'KPI cards, trends and drill-down.');
            // Dashboards gathered here from Sales and Admin so there is one home for them.
            $t($fx('crmdash_can') && crmdash_can(), '📈', 'Sales dashboard', '/crm-dashboard', 'Win rates and value by stage.');
            $t(licence_enabled('operations') && (can('mod.reports.view') || can('dash.operations') || can('dash.financial')), '📉', 'Management dashboard', '/mis', 'The MIS overview.');
            break;

        case 'directory':
            $title = 'Directory'; $icon = '🏢';
            $sub = 'The people and companies the work is done with — and the portals they sign in to.';
            $routes = ['directory','activities','clients','client','vendors','vendor','client-holds','asset-register','portal-users','vendor-users'];
            $t($fx('act_can_view') && act_can_view(), '🕘', 'Activity', '/activities', 'A timeline of what happened.');
            $t(can('mod.clients.view'), '🏢', T_REG('client'), '/clients', 'The client register.');
            $t(can('mod.vendors.view'), '🚚', T_REG('vendor'), '/vendors', 'The vendor register.');
            $t(is_master() || can('settings.manage') || ($fx('is_coordinator_level') && is_coordinator_level()), '⛔', 'Client holds', '/client-holds', 'Put a client on hold or block them before ordering.');
            $t($fx('asset_can_view') && asset_can_view(), '📦', 'Asset issuance', '/asset-register', 'Stamps, diaries, safety gear & devices issued to engineers — acknowledged and tracked.');

            // Portal administration — moved here from Quality (it manages the people
            // who sign in to the client/vendor portals, not a quality register).
            $sec('Portals');
            $t(can('mod.portal.view'), '🌐', 'Client portal', '/portal-users', 'Client portal users and requests.',
                $num(fn() => $fx('portal_requests_all') ? count(portal_requests_all('NEW')) : 0), 'amber');
            $t(can('mod.portal.view'), '🏭', 'Vendor portal', '/vendor-users', 'Vendor portal access.');
            break;

        case 'admin':
            $title = 'Admin'; $icon = '⚙️';
            $sub = 'For administrators: masters, people, access, licensing and system configuration.';
            $routes = ['admin','masters','m/','lookups','users','user-new','user-edit','hierarchy','access','adspro','sso','licence','product-package','settings','terminology','service-scope','service-formats','company-profile','books-bridge','approver-map','approval-rules','idems-approval-rules','templates','report-templates','audit-log'];

            $sec('Masters');
            $t(can('mod.masters.view'), '📋', 'Masters', '/masters', 'The lists behind every dropdown.');
            // Office costs, month-end cost run, business-unit P&L and profit-by-call
            // now live under Money (all of money in one place).
            // Management dashboard (MIS) now lives under Insights (one dashboards home).

            $sec('People');
            $t(can('mod.users.view'), '👥', T_REG('user'), '/users', 'People who can sign in.');
            $t(can('mod.users.view'), '🗂️', 'Organisation', '/hierarchy', 'The reporting hierarchy.');
            $t(is_master(), '🔐', 'Roles & permissions', '/access', 'Who can do what.');
            $t($fx('sso_on') && (can('users.manage.global') || is_master()), '🔑', 'Single sign-on', '/sso', 'Identity provider settings.');

            $sec('Configuration');
            $t(can('mod.settings.view') && can('settings.manage'), '⚙️', 'System settings', '/settings', 'Company-wide settings and terminology.');
            $t(can('settings.manage') || is_master(), '🧩', 'Service scope', '/service-scope', 'Which services are offered and where.');
            $t(can('settings.manage') || is_master(), '📄', 'Report formats by service', '/service-formats', 'The report format each service allocates.');
            // R11 — SLA targets moved to Quality (service delivery) so it no longer pulls
            // coordinators / asst. managers into Admin. See the 'quality' area above.
            $t(can('settings.manage') || is_master(), '🏢', 'Company profile', '/company-profile', 'Legal name, logo and details.');

            $sec('Report configuration');
            $t(licence_enabled('reporting') && (can('idems.type.manage') || is_master() || can('users.manage.global')), '👤', 'Approver mapping', '/approver-map', 'Who signs which report.');
            $t(licence_enabled('reporting') && (can('idems.type.manage') || is_master()), '🔀', 'Approval rules', '/approval-rules', 'Routing rules for approval.');
            // R11 — the crm.template.manage grant moved to Sales (Document templates), so
            // holding only that permission no longer forces a marketing manager into Admin.
            $t(licence_enabled('reporting') && (can('idems.type.manage') || is_master()), '📝', 'Document templates', '/templates', 'The report template library.');
            $t(licence_enabled('reporting') && (can('idems.audit.view') || is_master()), '🛡️', 'Report audit trail', '/audit-log', 'Who changed what, and when.');

            $sec('Super admin');
            $t(is_master(), '🛰️', 'Control panel', '/super-admin', 'Licence, seats, modules, subscription, tenants and system tools in one place.');
            // Revamp P6 — pick which EXAACT this install is (TPIA / Staffing / Recruitment / Enterprise).
            $t($fx('product_package_can') && product_package_can(), '📦', 'Product package', '/product-package', 'TPIA, Staffing, Recruitment or Enterprise — set the pack & bundles in one click.');

            $sec('Connections');
            $t($fx('ads_can_manage') && ads_can_manage(), '📢', 'Ads Pro connection', '/adspro', 'Connect the advertising source.');
            $t($fx('lk_can_manage') && lk_can_manage(), '📜', 'Licence', '/licence', 'The product licence and its state.');
            $t((can('settings.manage') || is_master()) && $fx('books_licensed') && books_licensed(), '📗', 'MGH Books', '/books-bridge', 'The accounts bridge.',
                $num(fn() => $fx('books_outbox_counts') ? (books_outbox_counts()['stuck'] ?? 0) : 0), 'red');

            // Parked-duplicates review complete: the two duplicate menu tiles
            // (Analytics under Quality, Vendors under Reporting) were dropped.
            // Both screens keep their canonical homes — Insights → Analytics and
            // Directory → Vendors — and their routes are untouched.
            break;

        default:
            return null;
    }

    // Drop any section that ended up empty (all its tiles were gated away).
    $sections = array_values(array_filter($sections, fn($s) => !empty($s['tiles'])));
    return ['key' => $area, 'title' => $title, 'icon' => $icon, 'sub' => $sub, 'sections' => $sections, 'routes' => $routes];
}

// Total visible tiles across all sections.
function ops_area_tile_count($area) {
    $d = ops_area_def($area);
    if (!$d) return 0;
    $n = 0; foreach ($d['sections'] as $s) $n += count($s['tiles']);
    return $n;
}

// Does this user have any access to the area? Drives both the rail link and the
// route gate.
function ops_area_has($area) { return ops_area_tile_count($area) > 0; }

// The route prefixes that mark this area "current" in the rail.
function ops_area_routes($area) {
    $d = ops_area_def($area);
    return $d ? $d['routes'] : [$area];
}

// Render an area Home.
function ops_area_home($area, $method) {
    $def = ops_area_def($area);
    ops_require($def && ops_area_tile_count($area) > 0, 'You do not have access to this area.');
    view('ops/area_home', ['def' => $def]);
    return true;
}
