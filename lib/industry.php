<?php
// ============================================================================
//  Industry templates — a working CRM on the day it is installed
//
//  THE REQUIREMENT, in the owner's words: "we need a complete professional CRM
//  with its individual dashboard, pipelines, funnels all prebuilt as per the
//  industry the user is of our application."
//
//  The gap that leaves. Today a new installation gets ONE generic pipeline with
//  five stages written for an inspection body. A trading company installing this
//  is handed "Needs understood → Quotation sent → Negotiation" and a lead
//  pipeline about whether somebody is a real prospect — recognisable, but not
//  theirs. They then have to build their own funnel before the product does
//  anything useful, and most people never do. A CRM that has to be configured
//  before it is worth using is a CRM that gets abandoned in week two.
//
//  So: pick the industry at setup, and the whole sales apparatus is there —
//  a lead pipeline, an opportunity funnel with realistic probabilities and
//  service levels, the sources deals actually come from in that trade, and the
//  reasons they are actually lost.
//
//  FOUR DECISIONS WORTH KNOWING ABOUT.
//
//   1. **Applying a template CREATES a pipeline; it never edits one.** A live
//      system has deals sitting on stages. Rewriting those stages would move
//      every deal somewhere it was never put. The new pipeline becomes the
//      default for new work and the old one keeps its deals until they close.
//   2. **The probabilities are the template's opinion, and are meant to be
//      changed.** They are a starting point drawn from how these trades
//      actually buy, not a claim to know a particular company's numbers.
//   3. **Stages are still fully configurable afterwards.** This is a head
//      start, not a cage. Nothing here is enforced anywhere in the code —
//      the WON/LOST/OPEN kind is the only thing any rule reads.
//   4. **It is separate from the inspection PACK.** A pack carries hard
//      accreditation rules. A template carries a starting configuration. A
//      trading company wants the trading template and no pack at all.
// ============================================================================

// Each template: the funnel (opportunity stages), the lead stages, where deals
// come from, and why they are lost. Stage = [name, kind, probability, sla_days].
//
// The funnels differ in ways that matter, not cosmetically: a construction bid
// sits at 20% for months and is lost on price; a subscription renewal starts at
// 60% because the customer is already yours; a diagnostics account is won or
// lost on an empanelment decision nobody controls.
const INDUSTRY_TEMPLATES = [

    'inspection' => [
        'label' => 'Inspection, testing & certification',
        'blurb' => 'Third-party inspection, NDT, vendor assessment, certification bodies.',
        'funnel' => [
            ['Enquiry received',   'OPEN',  10, 7],
            ['Scope agreed',       'OPEN',  30, 10],
            ['Quotation sent',     'OPEN',  50, 14],
            ['Rate negotiation',   'OPEN',  70, 10],
            ['PO / work order',    'OPEN',  90, 7],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Annual contracts & renewals' => [
                ['Contract due', 'OPEN', 30, 30],
                ['Renewal proposed', 'OPEN', 55, 21],
                ['Rates agreed', 'OPEN', 80, 14],
                ['Renewed', 'WON', 100, 0],
                ['Not renewed', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['New enquiry',    'OPEN', 0,  3],
            ['Contact made',   'OPEN', 0,  7],
            ['Qualified',      'OPEN', 0, 14],
            ['Converted',      'WON',  0,  0],
            ['Not pursued',    'LOST', 0,  0],
        ],
        'sources' => ['Existing client', 'Client referral', 'Tender portal', 'Empanelment',
                      'Website enquiry', 'Trade exhibition', 'Cold approach', 'Repeat order'],
        'lost'    => ['Rate too high', 'Competitor empanelled', 'Client cancelled the project',
                      'No accreditation for that scope', 'No response', 'Awarded in-house'],
    ],

    'manufacturing' => [
        'label' => 'Manufacturing & engineering',
        'blurb' => 'Fabrication, machining, capital equipment, industrial supply.',
        'funnel' => [
            ['Enquiry / RFQ',      'OPEN',  10, 7],
            ['Technical review',   'OPEN',  25, 14],
            ['Costing done',       'OPEN',  40, 10],
            ['Offer submitted',    'OPEN',  55, 21],
            ['Technical approval', 'OPEN',  75, 21],
            ['Commercial closing', 'OPEN',  90, 14],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Repeat & schedule orders' => [
                ['Schedule received', 'OPEN', 40, 7],
                ['Capacity confirmed', 'OPEN', 65, 7],
                ['Price confirmed', 'OPEN', 85, 7],
                ['Order released', 'WON', 100, 0],
                ['Not released', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['Enquiry',        'OPEN', 0,  3],
            ['Drawing received','OPEN',0,  7],
            ['Feasible',       'OPEN', 0, 14],
            ['Converted',      'WON',  0,  0],
            ['Not feasible',   'LOST', 0,  0],
        ],
        'sources' => ['Existing customer', 'OEM referral', 'Tender', 'Website enquiry',
                      'Trade exhibition', 'Channel partner', 'Cold approach'],
        'lost'    => ['Price', 'Delivery period', 'Technical rejection', 'Capacity not available',
                      'Customer deferred the project', 'Lost to import'],
    ],

    'trading' => [
        'label' => 'Trading & distribution',
        'blurb' => 'Stockists, distributors, importers, industrial supply houses.',
        'funnel' => [
            ['Enquiry',            'OPEN',  15, 3],
            ['Availability checked','OPEN', 35, 3],
            ['Quotation sent',     'OPEN',  55, 7],
            ['Negotiation',        'OPEN',  75, 7],
            ['Order expected',     'OPEN',  90, 5],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Regular supply accounts' => [
                ['Account opened', 'OPEN', 30, 14],
                ['Rate contract', 'OPEN', 60, 14],
                ['First order', 'OPEN', 85, 10],
                ['Account running', 'WON', 100, 0],
                ['Dormant', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['Enquiry',        'OPEN', 0, 2],
            ['Contact made',   'OPEN', 0, 5],
            ['Buying',         'OPEN', 0, 7],
            ['Converted',      'WON',  0, 0],
            ['Not buying',     'LOST', 0, 0],
        ],
        'sources' => ['Walk-in', 'Telephone enquiry', 'Existing customer', 'Referral',
                      'Website enquiry', 'Marketplace', 'Field visit', 'Exhibition'],
        'lost'    => ['Price', 'Stock not available', 'Delivery period', 'Credit terms',
                      'Bought from the manufacturer', 'No response'],
    ],

    'services' => [
        'label' => 'Professional & consulting services',
        'blurb' => 'Consultancy, audit, advisory, design, and other fee-based practices.',
        'funnel' => [
            ['Introductory call',  'OPEN',  10, 7],
            ['Needs understood',   'OPEN',  25, 14],
            ['Proposal sent',      'OPEN',  45, 14],
            ['Presentation done',  'OPEN',  65, 14],
            ['Fee agreed',         'OPEN',  85, 10],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Retainer renewals' => [
                ['Renewal due', 'OPEN', 40, 30],
                ['Scope reviewed', 'OPEN', 60, 21],
                ['Fee agreed', 'OPEN', 85, 14],
                ['Renewed', 'WON', 100, 0],
                ['Not renewed', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['New contact',    'OPEN', 0,  5],
            ['Conversation',   'OPEN', 0, 10],
            ['Qualified',      'OPEN', 0, 14],
            ['Converted',      'WON',  0,  0],
            ['Not a fit',      'LOST', 0,  0],
        ],
        'sources' => ['Referral', 'Existing client', 'Network', 'Speaking / event',
                      'Website enquiry', 'LinkedIn', 'Publication', 'Cold approach'],
        'lost'    => ['Fee', 'Chose another firm', 'Went in-house', 'Project shelved',
                      'Timing', 'No response'],
    ],

    'construction' => [
        'label' => 'Construction, projects & infrastructure',
        'blurb' => 'Contractors, EPC, project services. Long cycles, tender-driven.',
        'funnel' => [
            ['Opportunity spotted','OPEN',   5, 14],
            ['Pre-qualified',      'OPEN',  15, 30],
            ['Tender submitted',   'OPEN',  30, 45],
            ['Technical shortlist','OPEN',  50, 30],
            ['Commercial opening', 'OPEN',  70, 21],
            ['LOI / award',        'OPEN',  90, 14],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Variations & extra works' => [
                ['Variation raised', 'OPEN', 35, 14],
                ['Rate submitted', 'OPEN', 55, 21],
                ['Client approval', 'OPEN', 80, 21],
                ['Approved', 'WON', 100, 0],
                ['Rejected', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['Identified',     'OPEN', 0, 14],
            ['Client contact', 'OPEN', 0, 21],
            ['Pre-qualified',  'OPEN', 0, 30],
            ['Converted',      'WON',  0,  0],
            ['Not bidding',    'LOST', 0,  0],
        ],
        'sources' => ['Tender portal', 'Client invitation', 'Consultant referral', 'Existing client',
                      'Joint venture partner', 'Industry press', 'Site visit'],
        'lost'    => ['L1 price', 'Pre-qualification failed', 'Bid bond / capacity',
                      'Tender cancelled', 'Award delayed indefinitely', 'Chose not to bid'],
    ],

    'itservices' => [
        'label' => 'IT & software services',
        'blurb' => 'Software development, implementation, managed services, SaaS.',
        'funnel' => [
            ['Discovery',          'OPEN',  10, 7],
            ['Demo done',          'OPEN',  30, 10],
            ['Requirements agreed','OPEN',  45, 14],
            ['Proposal sent',      'OPEN',  60, 14],
            ['Pilot / POC',        'OPEN',  75, 21],
            ['Contract',           'OPEN',  90, 14],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Renewals & upsell' => [
                ['Renewal window', 'OPEN', 45, 30],
                ['Usage reviewed', 'OPEN', 65, 14],
                ['Quote sent', 'OPEN', 80, 14],
                ['Renewed', 'WON', 100, 0],
                ['Churned', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['Inbound',        'OPEN', 0,  2],
            ['Contacted',      'OPEN', 0,  5],
            ['Qualified',      'OPEN', 0, 10],
            ['Converted',      'WON',  0,  0],
            ['Disqualified',   'LOST', 0,  0],
        ],
        'sources' => ['Website enquiry', 'Inbound content', 'Referral', 'Partner',
                      'Existing customer', 'Event', 'Outbound campaign', 'Marketplace'],
        'lost'    => ['Price', 'Chose a competitor', 'Built in-house', 'Budget withdrawn',
                      'Missing feature', 'Went quiet'],
    ],

    'healthcare' => [
        'label' => 'Healthcare & diagnostics',
        'blurb' => 'Diagnostic labs, medical equipment, hospital supply, corporate health.',
        'funnel' => [
            ['Enquiry',            'OPEN',  10, 7],
            ['Requirement mapped', 'OPEN',  25, 14],
            ['Proposal / rate card','OPEN', 45, 14],
            ['Sample / trial',     'OPEN',  65, 21],
            ['Empanelment',        'OPEN',  85, 30],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Corporate health camps' => [
                ['Camp enquiry', 'OPEN', 20, 7],
                ['Dates & scope', 'OPEN', 45, 10],
                ['Rate card sent', 'OPEN', 70, 10],
                ['Camp confirmed', 'WON', 100, 0],
                ['Cancelled', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['Enquiry',        'OPEN', 0,  3],
            ['Contact made',   'OPEN', 0,  7],
            ['Qualified',      'OPEN', 0, 14],
            ['Converted',      'WON',  0,  0],
            ['Not pursued',    'LOST', 0,  0],
        ],
        'sources' => ['Doctor referral', 'Corporate tie-up', 'Existing account', 'Tender',
                      'Website enquiry', 'Camp / event', 'Insurance panel'],
        'lost'    => ['Rate', 'Empanelled elsewhere', 'Turnaround time', 'Accreditation required',
                      'Contract renewed with incumbent', 'No response'],
    ],

    'education' => [
        'label' => 'Education & training',
        'blurb' => 'Institutes, corporate training, certification and skilling providers.',
        'funnel' => [
            ['Enquiry',            'OPEN',  15, 3],
            ['Counselling done',   'OPEN',  35, 7],
            ['Proposal / fee sent','OPEN',  55, 7],
            ['Batch confirmed',    'OPEN',  80, 10],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Corporate & institutional' => [
                ['Requirement', 'OPEN', 20, 10],
                ['Curriculum agreed', 'OPEN', 45, 14],
                ['Commercials', 'OPEN', 70, 14],
                ['Batch confirmed', 'WON', 100, 0],
                ['Dropped', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['Enquiry',        'OPEN', 0, 1],
            ['Counselled',     'OPEN', 0, 3],
            ['Interested',     'OPEN', 0, 7],
            ['Converted',      'WON',  0, 0],
            ['Dropped',        'LOST', 0, 0],
        ],
        'sources' => ['Website enquiry', 'Walk-in', 'Referral', 'Campus visit',
                      'Corporate tie-up', 'Advertisement', 'Alumni', 'Education fair'],
        'lost'    => ['Fee', 'Joined elsewhere', 'Timing / batch', 'Location',
                      'Deferred to next intake', 'No response'],
    ],

    'logistics' => [
        'label' => 'Logistics & transport',
        'blurb' => 'Freight forwarding, warehousing, fleet, courier and 3PL.',
        'funnel' => [
            ['Enquiry',            'OPEN',  15, 3],
            ['Lanes / volumes',    'OPEN',  35, 7],
            ['Rate quoted',        'OPEN',  55, 7],
            ['Trial shipment',     'OPEN',  75, 14],
            ['Contract / rate lock','OPEN', 90, 14],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Contract renewals' => [
                ['Renewal due', 'OPEN', 40, 21],
                ['Rates revised', 'OPEN', 65, 14],
                ['Contract signed', 'OPEN', 85, 14],
                ['Renewed', 'WON', 100, 0],
                ['Lost to another', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['Enquiry',        'OPEN', 0, 2],
            ['Contact made',   'OPEN', 0, 5],
            ['Qualified',      'OPEN', 0, 7],
            ['Converted',      'WON',  0, 0],
            ['Not pursued',    'LOST', 0, 0],
        ],
        'sources' => ['Existing customer', 'Referral', 'Agent / overseas partner', 'Tender',
                      'Website enquiry', 'Field visit', 'Exhibition'],
        'lost'    => ['Rate', 'Transit time', 'Capacity / equipment', 'Incumbent retained',
                      'Volume did not materialise', 'No response'],
    ],

    'realestate' => [
        'label' => 'Real estate & facilities',
        'blurb' => 'Developers, brokers, leasing, facility and property management.',
        'funnel' => [
            ['Enquiry',            'OPEN',  10, 3],
            ['Site visit done',    'OPEN',  30, 7],
            ['Shortlisted',        'OPEN',  50, 14],
            ['Negotiation',        'OPEN',  70, 14],
            ['Booking / agreement','OPEN',  90, 10],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Leasing renewals' => [
                ['Lease expiring', 'OPEN', 40, 45],
                ['Terms discussed', 'OPEN', 60, 30],
                ['Renewal agreed', 'OPEN', 85, 21],
                ['Renewed', 'WON', 100, 0],
                ['Vacating', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['Enquiry',        'OPEN', 0, 1],
            ['Contacted',      'OPEN', 0, 3],
            ['Site visit set',  'OPEN', 0, 7],
            ['Converted',      'WON',  0, 0],
            ['Dropped',        'LOST', 0, 0],
        ],
        'sources' => ['Portal listing', 'Walk-in', 'Broker / channel partner', 'Referral',
                      'Website enquiry', 'Advertisement', 'Existing tenant'],
        'lost'    => ['Price', 'Location', 'Chose another property', 'Financing fell through',
                      'Deferred', 'No response'],
    ],

    'staffing' => [
        'label' => 'Recruitment & staffing',
        'blurb' => 'Permanent placement, contract staffing, payroll and manpower supply.',
        'funnel' => [
            ['Requirement received','OPEN', 15, 3],
            ['Terms agreed',       'OPEN',  35, 7],
            ['Profiles submitted', 'OPEN',  55, 7],
            ['Interviews on',      'OPEN',  75, 14],
            ['Offer / joining',    'OPEN',  90, 14],
            ['Won',                'WON',  100, 0],
            ['Lost',               'LOST',   0, 0],
        ],
        'alt' => ['Contract extensions' => [
                ['Extension due', 'OPEN', 45, 21],
                ['Client confirmed', 'OPEN', 70, 14],
                ['Rates agreed', 'OPEN', 85, 10],
                ['Extended', 'WON', 100, 0],
                ['Not extended', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['New client',     'OPEN', 0, 3],
            ['Contact made',   'OPEN', 0, 7],
            ['Terms discussed','OPEN', 0, 10],
            ['Converted',      'WON',  0,  0],
            ['Not pursued',    'LOST', 0,  0],
        ],
        'sources' => ['Referral', 'Existing client', 'LinkedIn', 'Job portal',
                      'Website enquiry', 'Cold approach', 'Event'],
        'lost'    => ['Fee percentage', 'Filled internally', 'Another consultant filled it',
                      'Requirement withdrawn', 'Candidate declined', 'No response'],
    ],

    'generic' => [
        'label' => 'General B2B (no specific industry)',
        'blurb' => 'A plain, sensible funnel. Start here and shape it as you learn.',
        'funnel' => [
            ['Qualified',        'OPEN',  10, 14],
            ['Needs understood', 'OPEN',  25, 14],
            ['Quotation sent',   'OPEN',  50, 10],
            ['Negotiation',      'OPEN',  75, 10],
            ['Won',              'WON',  100,  0],
            ['Lost',             'LOST',   0,  0],
        ],
        'alt' => ['Renewals & repeat business' => [
                ['Renewal due', 'OPEN', 40, 30],
                ['Discussed', 'OPEN', 60, 21],
                ['Agreed', 'OPEN', 85, 14],
                ['Renewed', 'WON', 100, 0],
                ['Not renewed', 'LOST', 0, 0],
        ]],
        'leads' => [
            ['New',            'OPEN', 0,  3],
            ['Contacted',      'OPEN', 0,  7],
            ['Qualified',      'OPEN', 0, 14],
            ['Converted',      'WON',  0,  0],
            ['Not pursued',    'LOST', 0,  0],
        ],
        'sources' => ['Website enquiry', 'Referral', 'Existing customer', 'Cold approach',
                      'Event', 'Partner'],
        'lost'    => ['Price', 'Chose a competitor', 'No budget', 'Timing', 'No response'],
    ],
];

function industry_can() { return can('settings.manage') || can('mod.settings.view') || is_master_of('settings'); }

function industry_current() { return (string)setting_get('industry_template', ''); }

function industry_label($key) { return INDUSTRY_TEMPLATES[$key]['label'] ?? ''; }

function industry_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }

// What applying would do, said before it is done. A setup step that changes
// things without showing what it will change is one people click nervously.
function industry_preview($key) {
    $t = INDUSTRY_TEMPLATES[$key] ?? null;
    if (!$t) return null;
    return [
        'label'  => $t['label'],
        'blurb'  => $t['blurb'],
        'funnel' => $t['funnel'],
        'alt'    => $t['alt'] ?? [],
        'leads'  => $t['leads'],
        'sources'=> $t['sources'],
        'lost'   => $t['lost'],
        'existing_open' => (int)industry_try(fn() => ops_val(
            "SELECT COUNT(*) FROM opportunities WHERE status='OPEN'"), 0),
    ];
}

// Build one pipeline and its stages. Returns the pipeline id.
function industry_make_pipeline($name, $entityKind, array $stages, $makeDefault = true) {
    // Only one default per kind; two is a coin toss about which funnel a new
    // deal lands in.
    if ($makeDefault)
        industry_try(fn() => db()->prepare("UPDATE pipelines SET is_default=0 WHERE entity_kind=?")
                                ->execute([$entityKind]));
    db()->prepare("INSERT INTO pipelines (name,entity_kind,is_default,active,created_by,created_at)
                   VALUES (?,?,?,1,?,?)")
       ->execute([$name, $entityKind, $makeDefault ? 1 : 0, user_name(current_user()), date('c')]);
    $pid = (int)db()->lastInsertId();
    $st = db()->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active)
                         VALUES (?,?,?,?,?,?,1)");
    foreach ($stages as $i => [$n, $k, $p, $sla]) $st->execute([$pid, $i + 1, $n, $k, $p, $sla]);
    return $pid;
}

// Apply a template. Creates; never edits or deletes. An existing pipeline keeps
// its deals and simply stops being the default.
function industry_apply($key) {
    $t = INDUSTRY_TEMPLATES[$key] ?? null;
    if (!$t) return ['err' => 'That is not one of the industry templates.'];
    if (function_exists('leads_migrate')) leads_migrate();
    if (function_exists('opp_migrate')) opp_migrate();

    $made = [];
    try {
        // The main funnel first — it becomes the default. The alternates are
        // created alongside but NOT made default: renewals, repeat orders and
        // extensions do not behave like new business and deserve their own
        // funnel, but new work should still land in the main one.
        $made['funnel'] = industry_make_pipeline($t['label'] . ' — new business', 'OPPORTUNITY', $t['funnel']);
        foreach (($t['alt'] ?? []) as $altName => $altStages)
            $made['alt'][] = industry_make_pipeline($altName, 'OPPORTUNITY', $altStages, false);
        $made['leads']  = industry_make_pipeline($t['label'] . ' — leads', 'LEAD', $t['leads']);
    } catch (Throwable $e) {
        return ['err' => 'The pipelines could not be created: ' . $e->getMessage()];
    }

    // The lists. Added to whatever is there rather than replacing it — somebody
    // who has already added their own source must not lose it.
    $added = 0;
    if (function_exists('lk_ensure_type_map')) {
        $added += industry_seed_list('lead_source', 'Lead source', $t['sources']);
        $added += industry_seed_list('quote_lost_reason', 'Lost reason', $t['lost']);
    }

    setting_set('industry_template', $key);
    setting_set('industry_applied_at', date('c'));
    if (function_exists('act_log'))
        act_log('', 0, 'SYSTEM', 'Industry template applied: ' . $t['label'], ['auto' => 1]);
    return ['ok' => true, 'pipelines' => $made, 'list_values' => $added, 'label' => $t['label']];
}

// Add values to a master list without disturbing what is already there.
function industry_seed_list($typeKey, $typeLabel, array $values) {
    $n = 0;
    try {
        lk_ensure_type_map($typeKey, $typeLabel, [], 'leads');
        $tid = (int)ops_val("SELECT id FROM lookup_types WHERE type_key=?", [$typeKey]);
        if (!$tid) return 0;
        $seq = (int)ops_val("SELECT COALESCE(MAX(sort_order),0) FROM lookup_values WHERE type_id=?", [$tid]);
        foreach ($values as $v) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $v));
            $has = ops_val("SELECT id FROM lookup_values WHERE type_id=? AND (label=? OR value_key=?)",
                           [$tid, $v, $code]);
            if ($has) continue;
            db()->prepare("INSERT INTO lookup_values (type_id,value_key,label,sort_order,active)
                           VALUES (?,?,?,?,1)")->execute([$tid, $code, $v, ++$seq]);
            $n++;
        }
    } catch (Throwable $e) { /* lookups shaped differently on an older install */ }
    return $n;
}

function ops_industry($route, $method) {
    ops_require(industry_can(), 'Only an administrator can set the industry template.');

    if ($route === 'industry-apply' && $method === 'POST') {
        $key = preg_replace('/[^a-z]/', '', (string)($_POST['key'] ?? ''));
        $r = industry_apply($key);
        if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/industry'); }
            $n = 2 + count($r['pipelines']['alt'] ?? []);
        flash($r['label'] . ' applied. ' . $n . ' pipelines created — a main funnel (now the default), '
            . 'an alternate funnel and a lead pipeline'
            . ($r['list_values'] ? ', with ' . $r['list_values'] . ' list values added' : '')
            . '. Anything already in flight keeps the pipeline it was on.');
        redirect('/opportunities');
    }

    view('ops/industry', [
        'templates' => INDUSTRY_TEMPLATES,
        'current'   => industry_current(),
        'appliedAt' => (string)setting_get('industry_applied_at', ''),
        'preview'   => industry_preview((string)($_GET['show'] ?? '') ?: (industry_current() ?: 'generic')),
        'showKey'   => (string)($_GET['show'] ?? '') ?: (industry_current() ?: 'generic'),
    ]);
    return true;
}
