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
//  Operations has its own richer Home (ops_operations_home, with live metrics);
//  every other area is rendered from here.
//
//  Nothing is removed — each tile links to the screen that already exists.
// ============================================================================

// Build the definition for one area: title, icon, subtitle, the visible tiles
// (already filtered by permission), and the route prefixes that mark the rail
// link "current". Only tiles the user may actually open are returned, so an
// empty tile list means "no access to this area".
function ops_area_def($area) {
    $inspPack = !function_exists('accredited_pack_on') || accredited_pack_on();
    // A tile is [icon, label, route, description]; $show decides if it appears.
    $tiles = [];
    $t = function ($show, $icon, $label, $route, $desc = '', $ext = false) use (&$tiles) {
        if ($show) $tiles[] = ['icon' => $icon, 'label' => $label, 'route' => $route, 'desc' => $desc, 'ext' => $ext];
    };
    $fx = fn($f) => function_exists($f);

    switch ($area) {
        case 'sales':
            $title = 'Sales'; $icon = '🎯';
            $sub = 'Leads, opportunities, ' . strtolower(THP('inquiry')) . ', ' . strtolower(THP('quote')) . ' and the pipeline.';
            $routes = ['sales','leads','lead','opportunities','opportunity','inquiries','inquiry','quotes','quote','crm-dashboard','pipelines','pipeline','approvals','stage-gates','ads-roi'];
            $t($fx('leads_can_view') && leads_can_view(), '🎯', 'Leads', '/leads', 'Enquiries not yet qualified.');
            $t($fx('opp_can_view') && opp_can_view(), '💡', 'Opportunities', '/opportunities', 'Qualified deals being worked.');
            $t(can('mod.inquiries.view'), '📨', THP('inquiry'), '/inquiries', 'Requests for a quotation.');
            $t(can('mod.quotes.view'), '📝', THP('quote'), '/quotes', 'Quotations, revisions and approvals.');
            $t($fx('crmdash_can') && crmdash_can(), '📈', 'Sales dashboard', '/crm-dashboard', 'Win rates and value by stage.');
            $t($fx('pipe_can_view') && pipe_can_view(), '🪜', 'Pipelines & funnels', '/pipelines', 'Stages and conversion.');
            $t($fx('gate_can_view') && gate_can_view(), '🛂', 'Approvals', '/approvals', 'Deals held at a stage gate.');
            $t($fx('roi_available') && roi_available() && $fx('roi_can') && roi_can(), '💸', 'Advertising return', '/ads-roi', 'Spend against leads produced.');
            break;

        case 'quality':
            $title = 'Quality & Accreditation'; $icon = '🛡️';
            $sub = 'The registers an accreditation assessor asks for — equipment, competence, impartiality, complaints, nonconformities and the portals.';
            $routes = ['quality','equipment','samples','sample','methods','method','drules','drule','cdocs','cdoc','risks','risk','retention','disclosure','competence','impartiality','complaints','complaint','satisfaction','confidentiality','conf-breach','site-docs','report-reviews','ncr','issues','departures','hold-points','capa','internal-audits','internal-audit','management-reviews','management-review','evidence-review','data-control','portal-users','vendor-users','analytics','identity'];
            $t($inspPack && can('mod.equipment.view'), '📏', 'Equipment & calibration', '/equipment', 'Instruments and calibration status.');
            $t($fx('sample_can_view') && sample_can_view(), '📦', 'Items & samples', '/samples', 'Items received for verification.');
            $t($fx('method_can_view') && method_can_view(), '📚', 'Method library', '/methods', 'Standards and methods applied.');
            $t($fx('drule_can_view') && drule_can_view(), '⚖️', 'Decision rules', '/drules', 'Statements of conformity rules.');
            $t($fx('cdoc_can_view') && cdoc_can_view(), '📄', 'Controlled documents', '/cdocs', 'The controlled document set.');
            $t($fx('risk_can_view') && risk_can_view(), '🎲', 'Risks & opportunities', '/risks', 'The risk register.');
            $t($fx('retention_can_view') && retention_can_view(), '🗄️', 'Retention schedule', '/retention', 'How long records are kept.');
            $t($fx('disclosure_can_view') && disclosure_can_view(), '📢', 'Disclosure consent', '/disclosure', 'Consents to disclose.');
            $t($inspPack && can('mod.competence.view'), '🎓', 'Competence & authorisation', '/competence', 'Training, assessment and authorisation.');
            $t($inspPack && can('mod.impartiality.view'), '⚖️', 'Impartiality', '/impartiality', 'Threats to impartiality and controls.');
            $t(can('mod.complaints.view'), '📮', 'Complaints & appeals', '/complaints', 'Complaints and appeals register.');
            $t($fx('sat_can_view') && sat_can_view(), '⭐', 'Customer satisfaction', '/satisfaction', 'Surveys and follow-up.');
            $t(can('mod.confidentiality.view') || can('mod.identity.view') || is_master_of(['confidentiality','identity']), '🔒', 'Confidentiality', '/confidentiality', 'Undertakings, NDAs and breaches.');
            $t($fx('ops_sitedocs') && licence_enabled('operations') && (can('mod.identity.view') || can('mod.clients.view') || is_master_of(['identity','clients'])), '🛂', 'Site entry documents', '/site-docs', 'Papers needed for site access.');
            $t($fx('rcr_can_view') && rcr_can_view(), '📬', 'Client acceptance', '/report-reviews', 'Reports the client accepted or rejected.');
            $t($inspPack && (can('mod.ncr.view') || can('mod.capa.view')), '⚠', 'Nonconformities', '/ncr', 'The NCR register.');
            $t((can('mod.ncr.view') || can('mod.capa.view')) && (!$fx('svc_globally_active') || svc_globally_active('NCR_CAPA')), '🗂️', 'Issues & departures', '/issues', 'Universal issue, deviation and concession log.');
            $t($fx('hwp_can_view') && hwp_can_view(), '✋', 'Hold & witness points', '/hold-points', 'Points where work must pause for us.');
            $t($inspPack && can('mod.capa.view'), '🛠', 'Corrective actions', '/capa', 'CAPA against nonconformities.');
            $t($inspPack && can('mod.audits.view'), '🔍', 'Internal audits', '/internal-audits', 'The internal audit programme.');
            $t($inspPack && can('mod.audits.view'), '🏛', 'Management review', '/management-reviews', 'Management review records.');
            $t($inspPack && $fx('trust_can_review') && trust_can_review(), '📍', 'Evidence review', '/evidence-review', 'Field evidence awaiting review.');
            $t($inspPack && can('mod.datacontrol.view'), '🗃', 'Data & information control', '/data-control', 'Information management controls.');
            $t(can('mod.portal.view'), '🌐', 'Client portal', '/portal-users', 'Client portal users and requests.');
            $t(can('mod.portal.view'), '🏭', 'Vendor portal', '/vendor-users', 'Vendor portal access.');
            $t($fx('tapi_can') && tapi_can(), '📈', 'Analytics', '/analytics', 'KPI dashboards and drill-down.');
            $t(can('mod.identity.view') && $fx('iddoc_can_view') && iddoc_can_view(), '🪪', 'Identity documents', '/identity', 'ID that gates site access.');
            break;

        case 'reporting':
            $title = 'Reporting'; $icon = '📑';
            $sub = 'Where inspection reports are written, endorsed, expedited and the formats that govern them.';
            $routes = ['reporting','documents','document','endorsements','endorsement','vendors','vendor-profile','expediting','expediting-projects','writing-assistant','phrase-library','learning','approver-map','approval-rules','idems-approval-rules','templates','report-templates','audit-log','compliance'];
            if (can('mod.idems.view')) {
                $t(true, '📑', T_REG('report'), '/documents', 'The report register.');
                $t(can('mod.idems.edit') || is_master_of('idems'), '➕', ucfirst(T_NEW('report')), '/document-new', 'Start a new report.');
                $t(true, '✅', T_REG('endorsement'), '/endorsements', 'Manufacturer document endorsements.');
                $t(true, '🏭', 'Vendor register', '/vendors', 'Vendors and their profiles.');
                $t(!$fx('svc_globally_active') || svc_globally_active('EXPEDITING'), '🚚', 'Expediting register', '/expediting', 'Chasing vendor delivery.');
                $t(!$fx('svc_globally_active') || svc_globally_active('EXPEDITING'), '🗂️', 'Project delivery', '/expediting-projects', 'Multi-vendor projects by milestone.');
                $t(true, '✒️', 'Technical writing', '/writing-assistant', 'Phrase library and writing help.');
                $t(true, '🧠', 'Learning insights', '/learning', 'Suggestions learned from past reports.');
                $t(licence_enabled('reporting') && (can('idems.type.manage') || is_master() || can('users.manage.global')), '👤', 'Approver mapping', '/approver-map', 'Who signs which report.');
                $t(licence_enabled('reporting') && (can('idems.type.manage') || is_master()), '🔀', 'Approval rules', '/approval-rules', 'Routing rules for approval.');
                $t(licence_enabled('reporting') && (can('idems.type.manage') || is_master() || can('crm.template.manage')), '📝', 'Document templates', '/templates', 'The report template library.');
                $t(licence_enabled('reporting') && (can('idems.audit.view') || is_master()), '🛡️', 'Audit trail', '/audit-log', 'Who changed what, and when.');
                $t(is_master() || can('settings.manage'), '⚖️', 'Where we stand', '/compliance', 'Which obligations are met, measured live.');
            }
            break;

        case 'money':
            $title = 'Money'; $icon = '💰';
            $sub = 'From what is waiting to be billed, through invoices and money in, to the profit each ' . strtolower(Tl('sbu')) . ' makes.';
            $routes = ['money','invoicing','to-bill','invoices','invoice','receipts','receipt','receivables','tally','profitability','sbu-pl'];
            $t(can('mod.invoicing.view'), '💳', T_REG('invoice'), '/invoicing', 'The invoice register.');
            if (can('mod.invoicing.view') && (can('finance.reconcile') || can('data.credit') || is_master())) {
                $t(true, '⧗', 'Waiting to be billed', '/to-bill', 'Closed work not yet invoiced.');
                $t(true, '🧾', 'Invoices', '/invoices', 'Invoices with lines and tax.');
                $t(true, '💰', 'Money in', '/receipts', 'Receipts matched to invoices.');
                $t(true, '⏳', 'Receivables ageing', '/receivables', 'What is owed, by age.');
                $t(true, '📤', 'Tally export', '/tally', 'Export for the accounts package.');
            }
            $t($fx('books_switch_ready') && books_switch_ready(), '📗', 'Accounts & GST ↗', ($fx('books_app_url') ? books_app_url() : '#'), 'Open MGH Books, already signed in.', true);
            $t(can('mod.profitability.view'), '💹', T_REG('boss'), '/profitability', 'Profit and margin by job.');
            break;

        case 'insights':
            $title = 'Insights'; $icon = '📊';
            $sub = 'The dashboards that read across everything — role views and the live analytics hub.';
            $routes = ['insights','reports','analytics','analytics-kpis','analytics-quality','analytics-drill'];
            $t(can('mod.reports.view'), '📊', 'Dashboards', '/reports', 'Role dashboards across the business.');
            // The analytics hub was buried under Quality and hard to find; surface it here too.
            $t($fx('tapi_can') && tapi_can(), '📈', 'Analytics & performance', '/analytics', 'KPI cards, trends and drill-down.');
            break;

        case 'directory':
            $title = 'Directory'; $icon = '🏢';
            $sub = 'The people and companies the work is done with.';
            $routes = ['directory','activities','clients','client','vendors','vendor'];
            $t($fx('act_can_view') && act_can_view(), '🕘', 'Activity', '/activities', 'A timeline of what happened.');
            $t(can('mod.clients.view'), '🏢', T_REG('client'), '/clients', 'The client register.');
            $t(can('mod.vendors.view'), '🚚', T_REG('vendor'), '/vendors', 'The vendor register.');
            break;

        case 'admin':
            $title = 'Admin'; $icon = '⚙️';
            $sub = 'Masters, costs, people, access and the settings that shape the app.';
            $routes = ['admin','masters','m/','lookups','office-finance','cost-run','sbu-pl','call-profit','mis','users','user-new','user-edit','hierarchy','access','adspro','sso','licence','settings','terminology','service-scope','service-formats','sla-targets','company-profile','books-bridge'];
            $t(can('mod.masters.view'), '📋', 'Masters', '/masters', 'The configurable lists behind every dropdown.');
            $t(can('mod.overheads.view'), '📐', TH('office') . ' costs & overheads', '/office-finance', 'Per-office cost model.');
            $t(can('mod.overheads.view'), '🧮', 'Month-end cost run', '/cost-run', 'Roll up costs for the month.');
            $t(can('mod.profitability.view'), '📊', T('sbu') . ' profit & loss', '/sbu-pl', 'P&L by business unit.');
            $t(can('mod.profitability.view'), '🧾', 'Profit by ' . strtolower(Tl('call')), '/call-profit', 'What each inspection made.');
            $t(licence_enabled('operations') && (can('mod.reports.view') || can('dash.operations') || can('dash.financial')), '📈', 'Management dashboard', '/mis', 'The MIS overview.');
            $t(can('mod.users.view'), '👥', T_REG('user'), '/users', 'People who can sign in.');
            $t(can('mod.users.view'), '🗂️', 'Organisation', '/hierarchy', 'The reporting hierarchy.');
            $t(is_master(), '🔐', 'Roles & permissions', '/access', 'Who can do what.');
            $t($fx('ads_can_manage') && ads_can_manage(), '📢', 'Ads Pro connection', '/adspro', 'Connect the advertising source.');
            $t($fx('sso_on') && (can('users.manage.global') || is_master()), '🔑', 'Single sign-on', '/sso', 'Identity provider settings.');
            $t($fx('lk_can_manage') && lk_can_manage(), '📜', 'Licence', '/licence', 'The product licence and its state.');
            $t(can('mod.settings.view') && can('settings.manage'), '⚙️', 'System settings', '/settings', 'Company-wide settings and terminology.');
            $t(can('settings.manage') || is_master(), '🧩', 'Service scope', '/service-scope', 'Which services are offered and where.');
            $t(can('settings.manage') || is_master(), '📄', 'Report formats by service', '/service-formats', 'The report format each service allocates.');
            $t(can('settings.manage') || is_master() || ($fx('is_coordinator_level') && is_coordinator_level()), '⏳', 'SLA targets', '/sla-targets', 'Turnaround targets.');
            $t(can('settings.manage') || is_master(), '🏢', 'Company profile', '/company-profile', 'Legal name, logo and details.');
            $t((can('settings.manage') || is_master()) && $fx('books_licensed') && books_licensed(), '📗', 'MGH Books', '/books-bridge', 'The accounts bridge.');
            break;

        default:
            return null;
    }

    return ['key' => $area, 'title' => $title, 'icon' => $icon, 'sub' => $sub, 'tiles' => $tiles, 'routes' => $routes];
}

// Does this user have any access to the area? (Drives both the rail link and
// the route gate.) True when at least one tile is visible.
function ops_area_has($area) {
    $d = ops_area_def($area);
    return $d && !empty($d['tiles']);
}

// The route prefixes that mark this area "current" in the rail.
function ops_area_routes($area) {
    $d = ops_area_def($area);
    return $d ? $d['routes'] : [$area];
}

// Render an area Home.
function ops_area_home($area, $method) {
    $def = ops_area_def($area);
    ops_require($def && !empty($def['tiles']), 'You do not have access to this area.');
    view('ops/area_home', ['def' => $def]);
    return true;
}
