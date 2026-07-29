<?php
// ============================================================================
//  Terminology — one place that decides what every business noun is called.
//
//  Why this file exists: the same idea used to be written three different ways
//  across the app ("Customer" in CRM, "Client" in Operations; "Quotation" on
//  one screen, "Quote" on the next; "Job" here, "Deputation" there). Screens
//  no longer hard-code those words. They call T()/TP() and get whatever the
//  company has decided the word is, from Settings → Terminology.
//
//  THE SHIPPED WORDS ARE DELIBERATELY PLAIN. They used to be an inspection
//  agency's vocabulary — "Inspection Call", "Deputation", "Inspection Engineer"
//  — which reads as somebody else's software to a manufacturer, a trader, an
//  EPC contractor, a facilities firm or a manpower supplier. The shipped words
//  are now ones every trade already uses, and an inspection agency gets its own
//  back in one click from Settings → Terminology → industry pack.
//
//      T('call')   → "Work Order"   (singular, as a heading / label)
//      TP('call')  → "Work Orders"  (plural)
//      Tl('call')  → "work order"   (lower-case, mid-sentence)
//      Tlp('call') → "work orders"
//      T_REG('call') → "Inspection call register"   (the standard list heading)
//
//  Acronyms (IBO, IRN, NCR, MTC…) are never lower-cased by Tl() — see
//  TERM_ACRONYMS. Overrides live in the single `terms` setting as JSON.
// ============================================================================

// key => [singular, plural, group, help]
const TERM_DEFAULTS = [
    // -- parties -------------------------------------------------------------
    'client'       => ['Client', 'Clients', 'Parties', 'The party that engages us and gets what we produce.'],
    'vendor'       => ['Vendor', 'Vendors', 'Parties', 'The party whose goods or works the job concerns.'],
    'manufacturer' => ['Manufacturer', 'Manufacturers', 'Parties', 'The works that actually makes the item.'],
    'supplier'     => ['Supplier', 'Suppliers', 'Parties', 'The party that supplies the item to the client.'],
    'subvendor'    => ['Sub-vendor', 'Sub-vendors', 'Parties', "The vendor's own subcontractor."],
    // -- sales ---------------------------------------------------------------
    'inquiry'      => ['Inquiry', 'Inquiries', 'Sales', 'An enquiry received from a client.'],
    'quote'        => ['Quote', 'Quotes', 'Sales', 'Our commercial offer against an inquiry.'],
    // "Sales Order" rather than "Order", so it can never be read as the work
    // order below. One is what the customer bought; the other is what we then
    // have to go and do.
    'order'        => ['Sales Order', 'Sales Orders', 'Sales', 'A won quote, registered as an order.'],
    // -- operations ----------------------------------------------------------
    'call'         => ['Work Order', 'Work Orders', 'Operations', 'A piece of work the customer has asked for, with its dates and location.'],
    'job'          => ['Job', 'Jobs', 'Operations', 'One person put on one work order, for particular dates.'],
    'engineer'     => ['Team Member', 'Team Members', 'Operations', 'The person who carries out the work.'],
    // "IBO" was the shipped default and nobody outside the company reads it as
    // anything. "Office" is what people actually say. Anyone who wants the old
    // word back can set it under Settings → Terminology; every screen follows.
    'office'       => ['Office', 'Offices', 'Operations', 'A branch or head office of this company.'],
    'sbu'          => ['Business Unit', 'Business Units', 'Operations',
                       'The line of business a job belongs to — what the branch is judged by.'],
    'manday'       => ['Person-day', 'Person-days', 'Operations', 'One person for one working day.'],
    // -- reporting -----------------------------------------------------------
    'report'       => ['Report', 'Reports', 'Reporting', 'A document we issue against a job and send to the client.'],
    'endorsement'  => ['Endorsement', 'Endorsements', 'Reporting', 'Our review and sign-off of somebody else’s record.'],
    'mfr_record'   => ['Supplier Document', 'Supplier Documents', 'Reporting', 'A document produced by the works rather than by us — test certificate, mill certificate, calibration record.'],
    // -- money ---------------------------------------------------------------
    'boss'         => ['Contract Number', 'Contract Numbers', 'Money', 'The client contract / order number that profitability is tracked against. It is not typed on a deputation — it comes down from the quotation and the inspection call, and the register fills itself.'],
    'invoice'      => ['Invoice', 'Invoices', 'Money', 'A bill raised on the client.'],
    'voucher'      => ['Voucher', 'Vouchers', 'Money', 'The monthly statement of travelling expenses.'],
    // -- people --------------------------------------------------------------
    'user'         => ['User', 'Users', 'People', 'A login account.'],
    'candidate'    => ['Candidate', 'Candidates', 'People', 'A person being considered for hiring.'],
    'requisition'  => ['Requisition', 'Requisitions', 'People', 'Approved demand for a new position.'],
];

// ---- Industry packs --------------------------------------------------------
//  A whole vocabulary in one click. The shipped words are plain on purpose, but
//  plain is not the same as right: a company that says "site visit" every day
//  should not have to read "work order" on every screen, and the first hour with
//  new software is where you decide whether it was built for you.
//
//  Each pack only lists what it changes. Anything it leaves out keeps the plain
//  word, so a pack is a nudge and not a straitjacket, and every single word
//  stays editable underneath — a pack fills the boxes on the Terminology screen,
//  it does not lock them.
//
//  These are the trades this product is being sold into. Adding another is four
//  lines and no code.
const TERM_PACKS = [
    'general' => [
        'label' => 'General business',
        'note'  => 'The plain words. A good starting point if none of the others quite fit.',
        'terms' => [],
    ],
    'inspection' => [
        'label' => 'Inspection & certification',
        'note'  => 'Third-party inspection, vendor surveillance, expediting, certification bodies.',
        'terms' => [
            'call'       => ['Inspection Call', 'Inspection Calls'],
            'job'        => ['Deputation', 'Deputations'],
            'engineer'   => ['Inspection Engineer', 'Inspection Engineers'],
            'report'     => ['Inspection Report', 'Inspection Reports'],
            'mfr_record' => ['Manufacturer Record', 'Manufacturer Records'],
            'manday'     => ['Man-day', 'Man-days'],
        ],
    ],
    'manufacturing' => [
        'label' => 'Manufacturing & fabrication',
        'note'  => 'Works, job shops, fabricators, process plants.',
        'terms' => [
            'call'     => ['Production Order', 'Production Orders'],
            'job'      => ['Operation', 'Operations'],
            'engineer' => ['Operator', 'Operators'],
            'office'   => ['Plant', 'Plants'],
            'client'   => ['Customer', 'Customers'],
        ],
    ],
    'trading' => [
        'label' => 'Trading & distribution',
        'note'  => 'Traders, stockists, distributors, dealers.',
        'terms' => [
            'call'     => ['Order', 'Orders'],
            'job'      => ['Consignment', 'Consignments'],
            'engineer' => ['Executive', 'Executives'],
            'client'   => ['Customer', 'Customers'],
            'office'   => ['Branch', 'Branches'],
        ],
    ],
    'epc' => [
        'label' => 'EPC & contracting',
        'note'  => 'Project contractors, erection and commissioning, civil and mechanical works.',
        'terms' => [
            'call'     => ['Work Package', 'Work Packages'],
            'job'      => ['Site Activity', 'Site Activities'],
            'engineer' => ['Site Engineer', 'Site Engineers'],
            'office'   => ['Site', 'Sites'],
            'boss'     => ['Contract Number', 'Contract Numbers'],
        ],
    ],
    'fieldservice' => [
        'label' => 'Field service & maintenance',
        'note'  => 'AMC providers, equipment servicing, installation and repair, facilities.',
        'terms' => [
            'call'     => ['Service Call', 'Service Calls'],
            'job'      => ['Visit', 'Visits'],
            'engineer' => ['Technician', 'Technicians'],
            'client'   => ['Customer', 'Customers'],
            'report'   => ['Service Report', 'Service Reports'],
        ],
    ],
    'manpower' => [
        'label' => 'Manpower supply & staffing',
        'note'  => 'Contract staffing, skilled and unskilled labour supply, deputation of people.',
        'terms' => [
            'call'     => ['Requirement', 'Requirements'],
            'job'      => ['Deployment', 'Deployments'],
            'engineer' => ['Worker', 'Workers'],
            'client'   => ['Principal Employer', 'Principal Employers'],
            'manday'   => ['Man-day', 'Man-days'],
        ],
    ],
    'exim' => [
        'label' => 'Export & import',
        'note'  => 'Exporters, importers, merchant traders, buying houses.',
        'terms' => [
            'call'     => ['Shipment', 'Shipments'],
            'job'      => ['Consignment', 'Consignments'],
            'engineer' => ['Executive', 'Executives'],
            'client'   => ['Buyer', 'Buyers'],
            'vendor'   => ['Supplier', 'Suppliers'],
        ],
    ],
    'logistics' => [
        'label' => 'Logistics & transport',
        'note'  => 'Freight forwarders, transporters, CHA, warehousing.',
        'terms' => [
            'call'     => ['Booking', 'Bookings'],
            'job'      => ['Trip', 'Trips'],
            'engineer' => ['Driver', 'Drivers'],
            'client'   => ['Consignor', 'Consignors'],
            'office'   => ['Depot', 'Depots'],
        ],
    ],
    'professional' => [
        'label' => 'Professional services',
        'note'  => 'Consultants, auditors, CA and CS firms, design and engineering consultancies.',
        'terms' => [
            'call'     => ['Engagement', 'Engagements'],
            'job'      => ['Assignment', 'Assignments'],
            'engineer' => ['Consultant', 'Consultants'],
            'report'   => ['Deliverable', 'Deliverables'],
            'manday'   => ['Man-day', 'Man-days'],
        ],
    ],
    'labs' => [
        'label' => 'Testing laboratories',
        'note'  => 'Materials testing, calibration, NABL laboratories, sample analysis.',
        'terms' => [
            'call'       => ['Test Request', 'Test Requests'],
            'job'        => ['Sample', 'Samples'],
            'engineer'   => ['Analyst', 'Analysts'],
            'report'     => ['Test Certificate', 'Test Certificates'],
            'office'     => ['Laboratory', 'Laboratories'],
        ],
    ],
];

// Apply a pack: fill the overrides with its words, leaving everything it does
// not mention on the shipped default. Deliberately REPLACES rather than merges
// with whatever was there before — half of one trade's vocabulary mixed with
// half of another's is how you get a screen nobody can read.
function term_apply_pack($packKey) {
    $pack = TERM_PACKS[$packKey] ?? null;
    if ($pack === null) return false;
    $ov = [];
    foreach ($pack['terms'] as $k => $pair) {
        if (!isset(TERM_DEFAULTS[$k])) continue;             // a pack cannot invent a term
        $ov[$k] = [0 => (string)$pair[0], 1 => (string)$pair[1]];
    }
    setting_set('terms', $ov ? json_encode($ov) : '');
    setting_set('terms_pack', $packKey);
    term_overrides($ov);
    return true;
}

// Which pack is in force — for showing the current choice on the screen. A pack
// stops being "in force" the moment somebody edits a word by hand, and saying so
// is more honest than showing a tick against a pack that no longer describes
// what is on screen.
function term_pack_current() {
    $k = (string)setting_get('terms_pack', '');
    if ($k === '' || !isset(TERM_PACKS[$k])) return '';
    $ov = term_overrides();
    foreach (TERM_PACKS[$k]['terms'] as $t => $pair) {
        if (($ov[$t][0] ?? '') !== $pair[0] || ($ov[$t][1] ?? '') !== $pair[1]) return '';
    }
    return $k;
}

// Words that must keep their capitals when used mid-sentence.
// SBU and BOSS stay listed: they are no longer the shipped defaults, but a
// company that sets either word back under Settings must still get its
// capitals kept mid-sentence.
const TERM_ACRONYMS = ['IBO','IBOs','SBU','SBUs','IRN','NCR','BOSS','MTC','NDT','FAT','SAT','PWHT','CoC','CV','CVs','AI','GST','TPI','QAP','PO','HSE','ARC'];

// Pass $set to replace the cached overrides (used right after saving).
function term_overrides($set = null) {
    static $ov = null;
    if ($set !== null) return $ov = $set;
    if ($ov === null) {
        $raw = setting_get('terms', '');
        $ov  = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    return $ov;
}

// Raw lookup: $form is 0 = singular, 1 = plural.
function term_raw($key, $form = 0) {
    $ov = term_overrides();
    if (isset($ov[$key][$form]) && $ov[$key][$form] !== '') return $ov[$key][$form];
    return TERM_DEFAULTS[$key][$form] ?? ($key);
}
function T($key)   { return term_raw($key, 0); }
function TP($key)  { return term_raw($key, 1); }

// Lower-cased for mid-sentence use, but acronyms are left alone. A multi-word
// term only lower-cases the words that are not acronyms ("Inspection Call" →
// "inspection call"; "IBO" stays "IBO"; "Contract Number" → "contract number").
function term_lower($s) {
    $out = [];
    foreach (explode(' ', $s) as $w) {
        $bare = trim($w, '(),.:');
        $out[] = in_array($bare, TERM_ACRONYMS, true) ? $w : mb_strtolower($w);
    }
    return implode(' ', $out);
}
function Tl($key)  { return term_lower(T($key)); }
function Tlp($key) { return term_lower(TP($key)); }
// Sentence-case, for the start of a heading or a nav label: "Inspection engineer".
function TH($key)  { return term_head(T($key)); }
function THP($key) { return term_head(TP($key)); }

// Sentence-case a term for the START of a heading: first character upper,
// the rest as-is unless the whole word is an acronym.
function term_head($s) {
    $l = term_lower($s);
    $first = mb_substr($l, 0, 1); $rest = mb_substr($l, 1);
    $w0 = explode(' ', $l)[0];
    if (in_array(trim($w0, '(),.:'), TERM_ACRONYMS, true)) return $l;
    return mb_strtoupper($first) . $rest;
}

// ---- The standard heading builders ----------------------------------------
// Every list screen in the app is "<Thing> register" (ISO/IEC 17020 §7.3/§8.4
// is written around records and registers, and it is how inspection desks
// already speak). Every detail screen is "<Thing> <code>". Every form is
// "New <thing>" / "Edit <thing>". Nothing invents its own pattern.
function T_REG($key)          { return term_head(T($key)) . ' register'; }
function T_DETAIL($key, $code){ return term_head(T($key)) . ($code !== '' && $code !== null ? ' ' . $code : ''); }
function T_NEW($key)          { return 'New ' . Tl($key); }
function T_EDIT($key)         { return 'Edit ' . Tl($key); }

// Groups, for the Settings → Terminology screen.
function term_groups() {
    $g = [];
    foreach (TERM_DEFAULTS as $k => $d) $g[$d[2]][$k] = $d;
    return $g;
}
function term_save($post) {
    $ov = [];
    foreach (TERM_DEFAULTS as $k => $d) {
        $s = trim((string)($post['t_' . $k . '_s'] ?? ''));
        $p = trim((string)($post['t_' . $k . '_p'] ?? ''));
        // only store what actually differs from the shipped default
        if ($s !== '' && $s !== $d[0]) $ov[$k][0] = $s;
        if ($p !== '' && $p !== $d[1]) $ov[$k][1] = $p;
        if (isset($ov[$k])) { $ov[$k][0] = $ov[$k][0] ?? $d[0]; $ov[$k][1] = $ov[$k][1] ?? $d[1]; ksort($ov[$k]); }
    }
    setting_set('terms', $ov ? json_encode($ov) : '');
    term_overrides($ov);
}

// ---- Shared screen furniture ----------------------------------------------
// One tab strip, used wherever two modules share a screen (approval rules,
// document templates, masters) so the heading stays the same and only the
// module underneath changes. $tabs = [href => label].
function module_tabs(array $tabs, $activeHref) {
    $h = '<div class="tabs">';
    foreach ($tabs as $href => $label) {
        $on = ($href === $activeHref) ? ' class="active"' : '';
        $h .= '<a href="' . e($href) . '"' . $on . '>' . e($label) . '</a>';
    }
    return $h . '</div>';
}

// ---- Screen ---------------------------------------------------------------
function ops_terminology($method) {
    ops_require(can('settings.manage'), 'Only admins can change terminology.');
    if ($method === 'POST') {
        $pack = trim((string)($_POST['pack'] ?? ''));
        if (($_POST['reset'] ?? '') === '1') {
            setting_set('terms', ''); setting_set('terms_pack', ''); term_overrides([]);
            flash('Terminology reset to the standard wording.');
        } elseif ($pack !== '') {
            if (term_apply_pack($pack)) {
                flash('Wording changed to ' . (TERM_PACKS[$pack]['label'] ?? $pack) . '. Every screen follows — and every word below is still yours to edit.');
            } else {
                flash('That is not a wording set this system knows.', 'error');
            }
        } else {
            term_save($_POST);
            flash('Terminology saved. Every screen now uses your wording.');
        }
        redirect('/terminology');
    }
    view('ops/terminology', [
        'groups' => term_groups(),
        'ov'     => term_overrides(),
        'packs'  => TERM_PACKS,
        'pack'   => term_pack_current(),
    ]);
}

// ---- Merged screens -------------------------------------------------------
// "Approval rules" used to be two screens with near-identical names, one for
// quotes and one for reports. It is now one screen with a module tab.
function approval_rule_tabs($active) {
    $t = [];
    if (can('mod.quotes.view')) $t['/approval-rules?module=quote'] = T('quote') . ' approvals';
    if (can('mod.idems.view'))  $t['/approval-rules?module=report'] = T('report') . ' approvals';
    return count($t) > 1 ? module_tabs($t, $active) : '';
}
function ops_approval_rules($method) {
    $mod = ($_GET['module'] ?? '') === 'quote' ? 'quote' : 'report';
    // Land on a tab the person can actually open.
    if ($mod === 'report' && !can('mod.idems.view') && can('mod.quotes.view')) $mod = 'quote';
    if ($mod === 'quote' && !can('mod.quotes.view') && can('mod.idems.view')) $mod = 'report';
    if ($mod === 'quote') { ops_crm_approval_rules('quote-approval-rules', $method); return true; }
    return ops_idems_approval_rules('idems-approval-rules', $method);
}
// Likewise "Document templates" — one screen, one tab per kind of template.
function template_tabs($active) {
    $t = [];
    if (can('mod.idems.view'))  $t['/templates?kind=report'] = T('report') . ' formats';
    if (can('mod.quotes.view')) $t['/templates?kind=quote']  = T('quote') . ' & e-mail';
    return count($t) > 1 ? module_tabs($t, $active) : '';
}
function ops_templates($method) {
    $kind = ($_GET['kind'] ?? '') === 'quote' ? 'quote' : 'report';
    if ($kind === 'report' && !can('mod.idems.view') && can('mod.quotes.view')) $kind = 'quote';
    if ($kind === 'quote' && !can('mod.quotes.view') && can('mod.idems.view')) $kind = 'report';
    if ($kind === 'quote') { ops_crm_templates('crm-templates', $method); return true; }
    return ops_idems_templates('report-templates', $method);
}
