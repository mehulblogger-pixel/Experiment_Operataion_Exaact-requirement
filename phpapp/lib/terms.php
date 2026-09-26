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
    // -- recruitment (People & hiring) ---------------------------------------
    //  Homed in their own group so a recruitment workspace (hr on) can rename its
    //  everyday words even when Operations/Sales/etc. are switched off. These are
    //  the words a recruiter actually reads on screen.
    'candidate'    => ['Candidate', 'Candidates', 'Recruitment', 'A person being considered for hiring.'],
    'requisition'  => ['Requisition', 'Requisitions', 'Recruitment', 'Approved demand for a new position — the role you are hiring for.'],
    'offer'        => ['Offer', 'Offers', 'Recruitment', 'The job offer made to a selected candidate.'],
    'placement'    => ['Placement', 'Placements', 'Recruitment', 'A candidate successfully hired and placed.'],
    'recruiter'    => ['Recruiter', 'Recruiters', 'Recruitment', 'The person who runs the hiring for a requisition.'],
    //  B4 — the six words the confusion audit found people hold apart every day
    //  and which had NO written definition anywhere. Each is taken from a
    //  decision already recorded, not invented here:
    //
    //   hiring request      the request layer built in M4, before execution
    //   workforce/inspector Q32 Model D, §10 — one record, a role marker, and
    //                       "Inspector" means team_role = FIELD
    //   qa                  a stage of a report's life, not a separate object
    //   billing readiness   a CHECK, not a document — the distinction that
    //                       stops an invoice being raised early
    //   professional        a Marketplace idea; not this company's staff
    //
    //  None of them changes a status, a rule or a model. They are the sentence
    //  a person needed and could not find.
    'hiring_request'    => ['Hiring Request', 'Hiring Requests', 'Recruitment',
        'A request for headcount, raised before recruiting starts so it can be approved. Approving it is what allows a {requisition} to be raised against it.'],
    'workforce'         => ['Workforce', 'Workforce', 'People',
        'Everybody employed or engaged by this company. A workforce record is created when somebody is hired, whatever job they do.'],
    'inspector'         => ['Inspector', 'Inspectors', 'Operations',
        'A workforce member whose team role is Field — the ones who can be sent to site. Every inspector is workforce; not every workforce member is an inspector.'],
    'qa'                => ['QA', 'QA', 'Reporting',
        'The review a report goes through before it is issued. QA is a stage in a report\'s life, not a separate document.'],
    'billing_readiness' => ['Billing readiness', 'Billing readiness', 'Money',
        'A check that everything needed to bill is present and agreed. It is not an invoice and raises no money — it is what tells you an invoice can safely be raised.'],
    'professional'      => ['Professional', 'Professionals', 'Recruitment',
        'Somebody who lists themselves on the marketplace. A professional is not this company\'s staff until they are hired, which is what makes them workforce.'],
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
            //  Same reasoning as the recruitment pack: the thing people are
            //  recruited against is a job order, which keeps it distinct from
            //  the client requirement above that asked for them.
            'requisition' => ['Job Order', 'Job Orders'],
            'client'   => ['Principal Employer', 'Principal Employers'],
            'manday'   => ['Man-day', 'Man-days'],
        ],
    ],
    'recruitment' => [
        'label' => 'Recruitment agency',
        'note'  => 'Permanent placement, executive search and technical recruitment — requirement to CV to offer to placement.',
        'terms' => [
            'call'        => ['Requirement', 'Requirements'],
            'job'         => ['Placement', 'Placements'],
            'engineer'    => ['Recruiter', 'Recruiters'],
            //  "Job Order", not "Requirement". Two reasons, both measured:
            //  (1) this pack previously gave 'call' AND 'requisition' the same
            //  word, so two different objects wore one name on the same screens;
            //  (2) "requirement" already means the CRITERIA on a requisition —
            //  certificates required, minimum qualification — so the record and
            //  its own fields collided. "Job order" is the term the staffing
            //  industry actually uses for work a client places (it is what Zoho's
            //  agency edition calls it), and it reads correctly here: a client
            //  places a job order; you fill it with people.
            'requisition' => ['Job Order', 'Job Orders'],
            'candidate'   => ['Candidate', 'Candidates'],
            'client'      => ['Client', 'Clients'],
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

// ---------------------------------------------------------------------------
//  B4 — a definition, where the word is actually used.
//
//  All 26 definitions existed. Exactly ONE screen printed them:
//  views/ops/terminology.php, the admin rename page. That is the one place a
//  person is not confused, because they went there on purpose.
//
//  These two put the sentence where the word is. There is no new component and
//  no tooltip: a tooltip cannot be read on a phone, and inspectors are
//  phone-first. It renders as the same muted one-liner that 295 of 401 views
//  already carry under their title.
//
//  Deliberately NOT a glossary. The confusion audit's rule is that a
//  RELATIONSHIP beats a definition -- "Raised from hiring request HR-00231"
//  tells you more than any paragraph about requisitions -- so this is used only
//  at the points the audit actually measured confusion, never sprayed across
//  every screen.
function T_HELP($key) {
    $d = TERM_DEFAULTS[$key] ?? null;
    if (!$d) return '';
    //  A help sentence may NAME another object — "…allows a {requisition} to be
    //  raised against it". A const cannot call a function, so the other object's
    //  name is a {key} token, resolved here from this workspace's own wording.
    //  Typing the word instead is how the help text came to say "requisition" to
    //  a workspace that had renamed it (ADR-002).
    return (string) preg_replace_callback('~\{([a-z_]+)\}~', function ($m) {
        return isset(TERM_DEFAULTS[$m[1]]) ? term_lower(term_raw($m[1], 0)) : $m[0];
    }, (string) ($d[3] ?? ''));
}
//  One muted line, ready to echo. Returns '' for an unknown key, so a caller
//  can never print an empty box.
function T_NOTE($key, $prefix = '') {
    $h = T_HELP($key);
    if ($h === '') return '';
    return '<p class="sub t-note" style="margin:4px 0 0">'
         . ($prefix !== '' ? '<b>' . htmlspecialchars($prefix, ENT_QUOTES) . '</b> ' : '')
         . htmlspecialchars($h, ENT_QUOTES) . '</p>';
}

// Groups, for the Settings → Terminology screen.
function term_groups() {
    $g = [];
    foreach (TERM_DEFAULTS as $k => $d) $g[$d[2]][$k] = $d;
    return $g;
}
// The word groups to SHOW on the terminology screen, filtered to the modules
// this workspace has. Parties and People are core (every business names its
// clients and its staff); Sales / Operations / Reporting / Money are hidden when
// their module is off — so a recruitment company is not asked to rename
// "Inspection Call", "Deputation", "Voucher" or "Endorsement".
function term_group_module($group) {
    static $m = ['Sales' => 'sales', 'Operations' => 'operations', 'Reporting' => 'reporting',
                 'Money' => 'money', 'Recruitment' => 'hr'];
    return $m[$group] ?? null;   // Parties / People / anything else = core
}
function term_groups_licensed() {
    $out = [];
    foreach (term_groups() as $group => $rows) {
        $mod = term_group_module($group);
        if ($mod !== null && function_exists('licence_enabled') && !licence_enabled($mod)) continue;
        $out[$group] = $rows;
    }
    return $out;
}
function term_save($post) {
    // Start from what is already saved, so a screen that shows only SOME word
    // groups (a recruitment plan hides Operations / Sales / Money / Reporting)
    // can never wipe the words it is not displaying. Only the keys whose input
    // was actually on the submitted form are rebuilt from the POST; every other
    // key keeps its existing override untouched.
    $ov = term_overrides();
    if (!is_array($ov)) $ov = [];
    foreach (TERM_DEFAULTS as $k => $d) {
        if (!array_key_exists('t_' . $k . '_s', $post) && !array_key_exists('t_' . $k . '_p', $post)) continue;
        unset($ov[$k]);                                    // this word was on the form — rebuild it
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
        'groups' => function_exists('term_groups_licensed') ? term_groups_licensed() : term_groups(),
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
    // Merged Sales/Reporting screen — reachable only where one of those modules is
    // on (the Admin tiles are already gated; this guards a typed/bookmarked URL).
    ops_require(!function_exists('licence_enabled') || licence_enabled('sales') || licence_enabled('reporting') || licence_enabled('operations'),
        'Approval rules apply to the Sales and Reporting modules, which are not part of your plan.');
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
    ops_require(!function_exists('licence_enabled') || licence_enabled('sales') || licence_enabled('reporting') || licence_enabled('operations'),
        'Document templates apply to the Sales and Reporting modules, which are not part of your plan.');
    $kind = ($_GET['kind'] ?? '') === 'quote' ? 'quote' : 'report';
    if ($kind === 'report' && !can('mod.idems.view') && can('mod.quotes.view')) $kind = 'quote';
    if ($kind === 'quote' && !can('mod.quotes.view') && can('mod.idems.view')) $kind = 'report';
    if ($kind === 'quote') { ops_crm_templates('crm-templates', $method); return true; }
    return ops_idems_templates('report-templates', $method);
}
