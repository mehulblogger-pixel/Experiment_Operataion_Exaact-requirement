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
// The common lead stages of the V2.1 templates (owner approved 21 Nov 2026):
// V2.0's order, with the owner's own names for the two closing stages kept —
// "Won — customer" (12 Nov: "converted meant nothing to a user") and "Not
// pursued" (both also printed on the Leads tiles). [name, kind, prob, days].
const INDUSTRY_LEAD_STAGES_V21 = [
    ['New prospect',          'OPEN', 0,  3],
    ['Contact attempted',     'OPEN', 0,  3],
    ['Contact established',   'OPEN', 0,  7],
    ['Qualified',             'OPEN', 0, 14],
    ['Won — customer',        'WON',  0,  0],
    ['Not pursued',           'LOST', 0,  0],
];

const INDUSTRY_TEMPLATES = [

    //  V2.1 (owner approved 21 Nov 2026): renamed for the TPIA buyer, without
    //  "certification" (owner: "no certification"); the deal funnel ends at the
    //  commercial award, not at inspection completion. Stages, percentages and
    //  lists are examples — each company changes them after setup.
    'inspection' => [
        'quote' => 2,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none) — "Commercial proposal submitted"
        'label' => 'Third-party inspection (TPIA) & testing',
        'blurb' => 'Third-party inspection agencies, vendor inspection, expediting, NDT and testing services.',
        'version' => 2,
        'quote_title' => 'Technical & Commercial Quotation',   // owner, 22 Nov 2026 (8a): the printed quotation's heading
        //  Funnels created before V2.1 carry the old name; D3 still recognises them.
        'old_labels' => ['Inspection, testing & certification'],
        'funnel' => [
            ['Enquiry / RFQ received',        'OPEN',  10, 3],
            ['Technical scope review',        'OPEN',  25, 5],
            ['Commercial proposal submitted', 'OPEN',  50, 14],
            ['Clarification / negotiation',   'OPEN',  70, 10],
            ['PO / work order awaited',       'OPEN',  90, 14],
            ['Won — order received',          'WON',  100, 0],
            ['Lost',                          'LOST',   0, 0],
        ],
        'alt' => ['Annual contracts & renewals' => [
                ['Contract due', 'OPEN', 30, 30],
                ['Renewal proposed', 'OPEN', 55, 21],
                ['Rates agreed', 'OPEN', 80, 14],
                ['Renewed', 'WON', 100, 0],
                ['Not renewed', 'LOST', 0, 0],
        ]],
        'leads' => INDUSTRY_LEAD_STAGES_V21,
        'sources' => ['Existing Client', 'Client Reference', 'EPC / PMC Reference', 'Tender / GeM Portal',
                      'Vendor Registration / Empanelment', 'Website Enquiry', 'Direct Marketing',
                      'Industry Networking', 'Cold Approach'],
        'lost'    => ['Commercial Rates Too High', 'Technical Scope Not Accepted', 'Competitor Selected',
                      'Existing Empanelled Agency Preferred', 'Manpower / Resource Unavailability',
                      'Mobilisation Timeline Not Accepted', 'Tender Cancelled', 'Requirement Withdrawn',
                      'No Response', 'Other'],
    ],

    //  V2.1 — technical manpower and deputation, separated from recruitment.
    'manpower' => [
        'quote' => 3,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none) — "Profiles / CVs submitted"
        'label' => 'Technical manpower & deputation',
        'blurb' => 'Deputation and supply of engineers, technicians and skilled staff, billed per month, day, hour or shift.',
        'version' => 1,
        'quote_title' => 'Quotation',   // owner, 22 Nov 2026 (8a): the printed quotation's heading
        'funnel' => [
            ['Manpower requirement received',  'OPEN',  10, 3],
            ['Requirement & scope qualified',  'OPEN',  25, 5],
            ['Commercials / rates agreed',     'OPEN',  45, 7],
            ['Profiles / CVs submitted',       'OPEN',  60, 7],
            ['Client interview / selection',   'OPEN',  80, 10],
            ['Won — PO / deployment work order received', 'WON', 100, 0],
            ['Lost',                           'LOST',   0, 0],
        ],
        'alt' => ['Deployment extensions' => [
                ['Extension due', 'OPEN', 45, 21],
                ['Client confirmed', 'OPEN', 70, 14],
                ['Rates agreed', 'OPEN', 85, 10],
                ['Extended', 'WON', 100, 0],
                ['Not extended', 'LOST', 0, 0],
        ]],
        'leads' => INDUSTRY_LEAD_STAGES_V21,
        'sources' => ['Existing Client', 'Client Reference', 'EPC / PMC Reference', 'Tender / GeM Portal',
                      'Vendor Registration / Empanelment', 'Website Enquiry', 'Direct Marketing',
                      'Industry Networking', 'Cold Approach'],
        'lost'    => ['Commercial Rates Too High', 'Statutory Cost Not Accepted', 'Suitable Personnel Unavailable',
                      'Required Qualification / Certification Not Available', 'Competitor Selected',
                      'Mobilisation Timeline Not Accepted', 'Requirement Withdrawn', 'No Response', 'Other'],
    ],

    //  V2.1 — recruitment and placement, separated from technical manpower.
    'recruitment' => [
        'quote' => 3,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none) — "Profiles submitted"
        'label' => 'Recruitment & placement agency',
        'blurb' => 'Permanent placement, executive search and technical recruitment, for a placement fee.',
        'version' => 1,
        'quote_title' => 'Fee Proposal',   // owner, 22 Nov 2026 (8a): the printed quotation's heading
        'funnel' => [
            ['Job requisition received',  'OPEN', 10, 3],
            ['Mandate / fee terms agreed','OPEN', 25, 7],
            ['Sourcing & shortlisting',   'OPEN', 40, 10],
            ['Profiles submitted',        'OPEN', 50, 7],
            ['Client interviews',         'OPEN', 65, 14],
            ['Offer released',            'OPEN', 80, 7],
            ['Offer accepted',            'OPEN', 90, 14],
            ['Won — candidate joined',    'WON', 100, 0],
            ['Lost',                      'LOST',  0, 0],
        ],
        //  Probabilities on this funnel are examples (V2.1 gives the stages only).
        'alt' => ['Replacements' => [
                ['Replacement due', 'OPEN', 30, 7],
                ['Candidate re-sourced', 'OPEN', 50, 14],
                ['Candidate shortlisted', 'OPEN', 70, 10],
                ['Candidate selected', 'OPEN', 85, 14],
                ['Joined', 'WON', 100, 0],
                ['Replacement closed', 'LOST', 0, 0],
        ]],
        'leads' => INDUSTRY_LEAD_STAGES_V21,
        'sources' => ['Existing Hiring Client', 'Client Reference', 'Website Enquiry', 'Job Portal',
                      'Tender / Recruitment Mandate', 'Industry Networking', 'Direct Marketing', 'Cold Approach'],
        'lost'    => ['Fee Percentage Not Accepted', 'Position Filled Internally', 'Another Recruitment Agency Selected',
                      'Candidate Declined', 'Candidate Not Available', 'Requirement Withdrawn',
                      'Position Put on Hold', 'No Response', 'Other'],
    ],

    //  V2.1 — audit and assurance: ISO / management-system, supplier, vendor,
    //  process, internal and compliance audits. CA / statutory audit is a
    //  separate, later template.
    'audit' => [
        'quote' => 3,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none) — "Proposal submitted"
        'label' => 'Audit & assurance',
        'blurb' => 'ISO / management-system, supplier, vendor, process, internal and compliance audits.',
        'version' => 1,
        'quote_title' => 'Proposal',   // owner, 22 Nov 2026 (8a): the printed quotation's heading
        'funnel' => [
            ['Audit enquiry / RFP received',          'OPEN', 10, 3],
            ['Scope & requirements reviewed',         'OPEN', 25, 5],
            ['Auditor availability assessed',         'OPEN', 40, 5],
            ['Proposal submitted',                    'OPEN', 55, 14],
            ['Commercial clarification / negotiation','OPEN', 75, 10],
            ['Won — engagement confirmed / PO received', 'WON', 100, 0],
            ['Lost',                                  'LOST',  0, 0],
        ],
        //  Probabilities on this funnel are examples (V2.1 gives the stages only).
        'alt' => ['Surveillance / next audit' => [
                ['Next audit due', 'OPEN', 40, 30],
                ['Proposal sent', 'OPEN', 60, 21],
                ['Confirmed', 'OPEN', 85, 14],
                ['Renewed', 'WON', 100, 0],
                ['Not renewed', 'LOST', 0, 0],
        ]],
        'leads' => INDUSTRY_LEAD_STAGES_V21,
        'sources' => ['Existing Client', 'Certification / Audit Reference', 'Vendor / Supplier Referral', 'Tender / RFP',
                      'Website Enquiry', 'Industry Networking', 'Direct Marketing', 'Cold Approach'],
        'lost'    => ['Audit Fee Too High', 'Competitor Selected', 'Auditor Availability',
                      'Independence / Conflict of Interest', 'Scope or Qualification Mismatch',
                      'Schedule Not Accepted', 'Audit Cancelled', 'No Response', 'Other'],
    ],

    'labs' => [
        'quote' => 2,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
        'label' => 'Testing & calibration laboratory',
        'blurb' => 'Materials testing, calibration, NABL / ISO 17025 laboratories, sample analysis.',
        'funnel' => [
            ['New deal',            'OPEN',  10, 5],
            ['Scope & methods',     'OPEN',  30, 7],
            ['Quotation sent',      'OPEN',  50, 10],
            ['Rate negotiation',    'OPEN',  70, 7],
            ['PO / work order',     'OPEN',  90, 5],
            ['Won',                 'WON',  100, 0],
            ['Lost',                'LOST',   0, 0],
        ],
        'alt' => ['Annual testing contracts' => [
                ['Contract due',    'OPEN', 30, 30],
                ['Renewal proposed','OPEN', 55, 21],
                ['Rates agreed',    'OPEN', 80, 14],
                ['Renewed',         'WON', 100, 0],
                ['Not renewed',     'LOST', 0, 0],
        ]],
        'leads' => [
            ['New enquiry',    'OPEN', 0,  3],
            ['Contact made',   'OPEN', 0,  7],
            ['Qualified',      'OPEN', 0, 14],
            ['Won — customer', 'WON',  0,  0],
            ['Not pursued',    'LOST', 0,  0],
        ],
        'sources' => ['Existing client', 'Client referral', 'Tender portal', 'NABL directory',
                      'Website enquiry', 'Trade exhibition', 'Walk-in sample', 'Repeat testing'],
        'lost'    => ['Rate too high', 'Turnaround too long', 'Scope not in our accreditation',
                      'Client used in-house lab', 'No response', 'Sample withdrawn'],
    ],

    'manufacturing' => [
        'quote' => 3,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
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
            ['Won — customer', 'WON',  0,  0],
            ['Not feasible',   'LOST', 0,  0],
        ],
        'sources' => ['Existing customer', 'OEM referral', 'Tender', 'Website enquiry',
                      'Trade exhibition', 'Channel partner', 'Cold approach'],
        'lost'    => ['Price', 'Delivery period', 'Technical rejection', 'Capacity not available',
                      'Customer deferred the project', 'Lost to import'],
    ],

    'trading' => [
        'quote' => 2,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
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
            ['Won — customer', 'WON',  0, 0],
            ['Not buying',     'LOST', 0, 0],
        ],
        'sources' => ['Walk-in', 'Telephone enquiry', 'Existing customer', 'Referral',
                      'Website enquiry', 'Marketplace', 'Field visit', 'Exhibition'],
        'lost'    => ['Price', 'Stock not available', 'Delivery period', 'Credit terms',
                      'Bought from the manufacturer', 'No response'],
    ],

    'services' => [
        'quote' => 2,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
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
            ['Won — customer', 'WON',  0,  0],
            ['Not a fit',      'LOST', 0,  0],
        ],
        'sources' => ['Referral', 'Existing client', 'Network', 'Speaking / event',
                      'Website enquiry', 'LinkedIn', 'Publication', 'Cold approach'],
        'lost'    => ['Fee', 'Chose another firm', 'Went in-house', 'Project shelved',
                      'Timing', 'No response'],
    ],

    'construction' => [
        'quote' => 2,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
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
            ['Won — customer', 'WON',  0,  0],
            ['Not bidding',    'LOST', 0,  0],
        ],
        'sources' => ['Tender portal', 'Client invitation', 'Consultant referral', 'Existing client',
                      'Joint venture partner', 'Industry press', 'Site visit'],
        'lost'    => ['L1 price', 'Pre-qualification failed', 'Bid bond / capacity',
                      'Tender cancelled', 'Award delayed indefinitely', 'Chose not to bid'],
    ],

    'itservices' => [
        'quote' => 3,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
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
            ['Won — customer', 'WON',  0,  0],
            ['Disqualified',   'LOST', 0,  0],
        ],
        'sources' => ['Website enquiry', 'Inbound content', 'Referral', 'Partner',
                      'Existing customer', 'Event', 'Outbound campaign', 'Marketplace'],
        'lost'    => ['Price', 'Chose a competitor', 'Built in-house', 'Budget withdrawn',
                      'Missing feature', 'Went quiet'],
    ],

    'healthcare' => [
        'quote' => 2,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
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
            ['Won — customer', 'WON',  0,  0],
            ['Not pursued',    'LOST', 0,  0],
        ],
        'sources' => ['Doctor referral', 'Corporate tie-up', 'Existing account', 'Tender',
                      'Website enquiry', 'Camp / event', 'Insurance panel'],
        'lost'    => ['Rate', 'Empanelled elsewhere', 'Turnaround time', 'Accreditation required',
                      'Contract renewed with incumbent', 'No response'],
    ],

    'education' => [
        'quote' => 2,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
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
            ['Won — customer', 'WON',  0, 0],
            ['Dropped',        'LOST', 0, 0],
        ],
        'sources' => ['Website enquiry', 'Walk-in', 'Referral', 'Campus visit',
                      'Corporate tie-up', 'Advertisement', 'Alumni', 'Education fair'],
        'lost'    => ['Fee', 'Joined elsewhere', 'Timing / batch', 'Location',
                      'Deferred to next intake', 'No response'],
    ],

    'logistics' => [
        'quote' => 2,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
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
            ['Won — customer', 'WON',  0, 0],
            ['Not pursued',    'LOST', 0, 0],
        ],
        'sources' => ['Existing customer', 'Referral', 'Agent / overseas partner', 'Tender',
                      'Website enquiry', 'Field visit', 'Exhibition'],
        'lost'    => ['Rate', 'Transit time', 'Capacity / equipment', 'Incumbent retained',
                      'Volume did not materialise', 'No response'],
    ],

    'realestate' => [
        'quote' => null,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
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
            ['Won — customer', 'WON',  0, 0],
            ['Dropped',        'LOST', 0, 0],
        ],
        'sources' => ['Portal listing', 'Walk-in', 'Broker / channel partner', 'Referral',
                      'Website enquiry', 'Advertisement', 'Existing tenant'],
        'lost'    => ['Price', 'Location', 'Chose another property', 'Financing fell through',
                      'Deferred', 'No response'],
    ],

    //  V2.1 (21 Nov 2026): split into 'manpower' and 'recruitment'. Kept, as it
    //  was, for the companies already using it; not offered for a new choice
    //  (industry_choices()).
    'staffing' => [
        'quote' => null,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
        'label' => 'Recruitment & staffing',
        'replaced_by' => ['manpower', 'recruitment'],
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
            ['Won — customer', 'WON',  0,  0],
            ['Not pursued',    'LOST', 0,  0],
        ],
        'sources' => ['Referral', 'Existing client', 'LinkedIn', 'Job portal',
                      'Website enquiry', 'Cold approach', 'Event'],
        'lost'    => ['Fee percentage', 'Filled internally', 'Another consultant filled it',
                      'Requirement withdrawn', 'Candidate declined', 'No response'],
    ],

    'generic' => [
        'quote' => 2,   // D3: the funnel step at which a quotation has gone (index in 'funnel'; null = none)
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
            ['Won — customer', 'WON',  0,  0],
            ['Not pursued',    'LOST', 0,  0],
        ],
        'sources' => ['Website enquiry', 'Referral', 'Existing customer', 'Cold approach',
                      'Event', 'Partner'],
        'lost'    => ['Price', 'Chose a competitor', 'No budget', 'Timing', 'No response'],
    ],
];

function industry_can() { return can('settings.manage') || can('mod.settings.view') || is_master_of('settings'); }
// D-29 (the owner, 20 Oct 2026: "Yes") — adding a template's pipelines is the
// day-to-day act above; setting the COMPANY's industry — the wording everyone
// sees and the accreditation rules — is an administrator's alone.
function industry_can_company() {
    if (function_exists('licence_enabled') && !licence_enabled('sales') && !licence_enabled('operations')) return false;
    return can('settings.manage') || is_master_of('settings');
}

function industry_current() { return (string)setting_get('industry_template', ''); }

// V2.1 (owner approved 21 Nov 2026) — a template's version. Templates written
// before V2.1 carry none and count as 1. Stored with the key when the template
// is applied ("inspection@2"), so a later reader knows which edition of the
// stages and words a company started from. Company changes are never touched
// on an upgrade: applying creates, it never edits.
// Owner, 22 Nov 2026 (8a): the heading printed on a quotation PDF. A company setting
// (Sales settings → "Printed quotation title") wins; otherwise the company's industry
// template gives it (TPIA "Technical & Commercial Quotation", recruitment "Fee Proposal",
// audit "Proposal"); otherwise "Quotation" — what every PDF printed before.
function quote_print_title() {
    $s = trim((string) (function_exists('setting_get') ? setting_get('quote_print_title', '') : ''));
    if ($s !== '') return $s;
    $k = (string) (function_exists('setting_get') ? setting_get('industry_template', '') : '');
    return (string) (INDUSTRY_TEMPLATES[$k]['quote_title'] ?? 'Quotation');
}
function industry_version($key) { return (int) (INDUSTRY_TEMPLATES[$key]['version'] ?? 1); }
function industry_stamp($key) { return $key . '@' . industry_version($key); }

// The templates offered for a NEW choice: every template except one that a
// V2.1 template has replaced ('staffing' → 'manpower' + 'recruitment') —
// unless it is this company's current one, which stays on show so its own
// choice never disappears from the screen.
function industry_choices($current = null) {
    $current = $current ?? industry_current();
    $out = [];
    foreach (INDUSTRY_TEMPLATES as $k => $t)
        if (empty($t['replaced_by']) || $k === $current) $out[$k] = $t;
    return $out;
}
function industry_offered($key) { return isset(industry_choices()[$key]); }

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

// The first-time setup: an administrator setting the company up does both —
// the pipelines, and the company's wording and accreditation.
function industry_apply($key) {
    $r = industry_apply_pipelines($key);
    if (!empty($r['err'])) return $r;
    return array_merge($r, industry_set_company($key));
}

// Add a template's pipelines and list values. Creates; never edits or deletes.
// An existing pipeline keeps its deals and simply stops being the default. It
// changes neither the wording nor the accreditation rules (D-29).
function industry_apply_pipelines($key) {
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
    $unitsAdded = industry_seed_units($key);
    $added += $unitsAdded;

    if (function_exists('act_log'))
        act_log('', 0, 'SYSTEM', 'Industry template pipelines added: ' . $t['label'] . ' (' . industry_stamp($key) . ')', ['auto' => 1]);
    if (function_exists('setting_set')) setting_set('industry_pipelines_version', industry_stamp($key));
    return ['ok' => true, 'pipelines' => $made, 'list_values' => $added, 'units_added' => $unitsAdded, 'label' => $t['label']];
}

// Which accreditation pack (if any) an industry carries. Only inspection and
// laboratory do; every other trade has none.
function industry_accr_pack($key) { return ['inspection' => 'inspection', 'labs' => 'labs'][$key] ?? ''; }
const INDUSTRY_ACCR_LABELS = ['inspection' => 'inspection (ISO/IEC 17020)', 'labs' => 'laboratory (ISO/IEC 17025)'];

// What making $key the company's industry would change, in sentences — shown
// before the administrator confirms, so nothing changes by surprise.
function industry_company_changes($key) {
    $out = [];
    $termKey = INDUSTRY_TERM_MAP[$key] ?? 'general';
    $curTerm = (string) setting_get('terms_pack', '');
    $lbl = fn($k) => defined('TERM_PACKS') ? (TERM_PACKS[$k]['label'] ?? $k) : $k;
    if ($curTerm !== $termKey)
        $out[] = 'The wording everyone sees changes to "' . $lbl($termKey) . '"' . ($curTerm !== '' ? ' (now "' . $lbl($curTerm) . '")' : '') . '.';
    $on = function_exists('packs_enabled') ? packs_enabled(true) : [];
    $want = industry_accr_pack($key);
    foreach (INDUSTRY_ACCR_LABELS as $p => $pl) {
        $isOn = in_array($p, $on, true);
        if ($isOn && $p !== $want) $out[] = 'The ' . $pl . ' accreditation rules will be turned OFF.';
        if (!$isOn && $p === $want) $out[] = 'The ' . $pl . ' accreditation rules will be turned ON.';
    }
    return $out;
}

// Set the company's industry: its wording, and which accreditation rules run.
// An administrator's act (industry_can_company()); creates no pipeline.
function industry_set_company($key) {
    $t = INDUSTRY_TEMPLATES[$key] ?? null;
    if (!$t) return ['err' => 'That is not one of the industry templates.'];
    $termKey = INDUSTRY_TERM_MAP[$key] ?? 'general';
    $wording = function_exists('term_apply_pack') ? (bool) term_apply_pack($termKey) : false;
    // Only the accreditation packs follow the industry; any other pack the
    // company has switched on is left as it is.
    $accrPack = industry_accr_pack($key);
    if (function_exists('packs_save')) {
        $keep = array_values(array_diff(function_exists('packs_enabled') ? packs_enabled(true) : [], array_keys(INDUSTRY_ACCR_LABELS)));
        packs_save(implode(',', array_filter(array_merge($keep, [$accrPack]))));
    }
    setting_set('industry_template', $key);
    setting_set('industry_template_version', industry_stamp($key));
    setting_set('industry_applied_at', date('c'));
    if (function_exists('act_log'))
        act_log('', 0, 'SYSTEM', 'Company industry set to ' . $t['label'] . ' — wording and accreditation rules', ['auto' => 1]);
    //  Owner, 12 Nov 2026 — the units this trade bills in (see INDUSTRY_UNITS).
    $unitsAdded = industry_seed_units($key);
    return ['units_added' => $unitsAdded, 'ok' => true, 'label' => $t['label'], 'wording' => $wording,
            'inspection_pack' => ($key === 'inspection'), 'accreditation_pack' => $accrPack];
}

// Which vocabulary pack (terms.php TERM_PACKS) each industry template lines up
// with. Not every template has an exact word-pack; the nearest is used, and the
// general default covers the rest. Only 'inspection' also turns on the
// accreditation pack — that is decided in industry_apply(), not here.
const INDUSTRY_TERM_MAP = [
    'inspection'    => 'tpia',          // V2.1 — the refined TPIA words
    'labs'          => 'labs',
    'manufacturing' => 'manufacturing',
    'trading'       => 'trading',
    'services'      => 'professional',
    'construction'  => 'epc',
    'itservices'    => 'professional',
    'healthcare'    => 'general',
    'education'     => 'professional',
    'logistics'     => 'logistics',
    'realestate'    => 'general',
    'staffing'      => 'manpower',
    'generic'       => 'general',
    'manpower'      => 'techmanpower',
    'recruitment'   => 'recruitplace',
    'audit'         => 'audit',
];

// ============================================================================
//  BILLING UNITS BY INDUSTRY — owner, 12 Nov 2026 (test book, Profile E):
//  "Unit remains the same for trading and distribution and all other Industry
//  types as of Inspection and Testing." Quote, order and contract lines offered
//  only inspection units (man-day, visit …), so a trader could not quote 20 valves
//  or a builder a square metre. Each industry now brings the units its trade
//  bills in. Added to the company's unit list (Masters → Charge unit), never
//  replacing what is there — the inspection units stay for service lines — and
//  the first one becomes the unit a new line starts with. (Codes avoid the old
//  aliases in CHARGE_UNIT_ALIASES — "MONTH" there means Man-month.)
// ============================================================================
const INDUSTRY_UNITS = [
    'inspection'    => [],
    'labs'          => ['SAMPLE' => 'Per sample', 'TEST' => 'Per test', 'PARAMETER' => 'Per parameter', 'HOUR' => 'Per hour'],
    'manufacturing' => ['NOS' => 'Nos', 'SET' => 'Set', 'KG' => 'Kg', 'TONNE' => 'Tonne', 'METRE' => 'Metre', 'LITRE' => 'Litre', 'BOX' => 'Box', 'PAIR' => 'Pair'],
    'trading'       => ['NOS' => 'Nos', 'SET' => 'Set', 'BOX' => 'Box', 'CARTON' => 'Carton', 'DOZEN' => 'Dozen', 'KG' => 'Kg', 'TONNE' => 'Tonne', 'METRE' => 'Metre', 'LITRE' => 'Litre', 'ROLL' => 'Roll', 'PAIR' => 'Pair'],
    'services'      => ['HOUR' => 'Per hour', 'PER_MONTH' => 'Per month', 'SESSION' => 'Per session'],
    'construction'  => ['STAGE' => 'Per stage / milestone', 'SQM' => 'Square metre', 'CUM' => 'Cubic metre', 'RMT' => 'Running metre', 'TONNE' => 'Tonne', 'NOS' => 'Nos', 'PER_MONTH' => 'Per month'],
    'itservices'    => ['HOUR' => 'Per hour', 'PER_MONTH' => 'Per month', 'USER_MONTH' => 'Per user per month', 'LICENCE' => 'Per licence'],
    'healthcare'    => ['SESSION' => 'Per session', 'TEST' => 'Per test', 'PER_MONTH' => 'Per month'],
    'education'     => ['SEAT' => 'Per participant', 'SESSION' => 'Per session', 'HOUR' => 'Per hour', 'COURSE' => 'Per course'],
    'logistics'     => ['TRIP' => 'Per trip', 'CONTAINER' => 'Per container', 'KG' => 'Kg', 'TONNE' => 'Tonne', 'CUM' => 'Cubic metre', 'PALLET' => 'Per pallet'],
    'realestate'    => ['SQFT' => 'Square foot', 'SQM' => 'Square metre', 'PER_MONTH' => 'Per month', 'NOS' => 'Nos'],
    'staffing'      => ['HOUR' => 'Per hour', 'PER_MONTH' => 'Per month'],
    //  V2.1 — the first unit is the one a new line starts with. Man-day, Audit
    //  day and Lump sum are standard units already on the list (only chosen as
    //  the starting unit); Per hour and Per shift are added where missing.
    'manpower'      => ['MANDAY' => 'Man-day', 'HOUR' => 'Per hour', 'SHIFT' => 'Per shift'],
    'recruitment'   => ['LOT' => 'Per lot / lump sum'],
    'audit'         => ['AUDIT_DAY' => 'Audit day', 'HOUR' => 'Per hour'],
    'generic'       => ['NOS' => 'Nos', 'HOUR' => 'Per hour', 'PER_MONTH' => 'Per month'],
];
function industry_seed_units($key) {
    $units = INDUSTRY_UNITS[$key] ?? [];
    $n = 0;
    try {
        if (function_exists('lk_ensure_type_map')) lk_ensure_type_map('charge_unit', 'Charge unit', defined('CHARGE_UNITS') ? CHARGE_UNITS : [], 'Operations');
        $t = function_exists('lk_type') ? lk_type('charge_unit') : null;
        //  A company without Operations still quotes: the list is made for Sales,
        //  with the standard units first so no existing line loses its unit.
        if (!$t && function_exists('lk_add_type')) {
            $tid = lk_add_type('charge_unit', 'Charge unit', null, 0, 50); $so = 0;
            foreach ((defined('CHARGE_UNITS') ? CHARGE_UNITS : []) as $c => $l) lk_add_value($tid, null, $c, $l, $so++);
            if (function_exists('lk_set_module')) lk_set_module('charge_unit', 'Sales');
            $t = lk_type('charge_unit');
        }
        if (!$t) return 0;
        $tid = (int)$t['id'];
        $seq = (int)ops_val("SELECT COALESCE(MAX(sort_order),0) FROM lookup_values WHERE type_id=?", [$tid]);
        foreach ($units as $code => $label) {
            if (ops_val("SELECT id FROM lookup_values WHERE type_id=? AND (code=? OR label=?)", [$tid, $code, $label])) continue;
            lk_add_value($tid, null, $code, $label, ++$seq);
            $n++;
        }
        if (function_exists('setting_set')) setting_set('default_charge_unit', $units ? (string)array_key_first($units) : 'MANDAY');
    } catch (Throwable $e) { /* lookups shaped differently on an older install */ }
    return $n;
}
// The unit a new quote / order line starts with: the company's industry's first
// unit (Nos for a trader), else Man-day.
function default_charge_unit() {
    $u = function_exists('setting_get') ? (string)setting_get('default_charge_unit', '') : '';
    return $u !== '' ? $u : 'MANDAY';
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
            //  Owner, 12 Nov 2026 — this named a column that does not exist
            //  (value_key; it is code), and the try/catch below hid it, so applying
            //  an industry never added its lead sources or lost reasons.
            $has = ops_val("SELECT id FROM lookup_values WHERE type_id=? AND (label=? OR code=?)",
                           [$tid, $v, $code]);
            if ($has) continue;
            lk_add_value($tid, null, $code, $v, ++$seq);
            $n++;
        }
    } catch (Throwable $e) { /* lookups shaped differently on an older install */ }
    return $n;
}

function ops_industry($route, $method) {
    ops_require(industry_can(), 'Only an administrator can set the industry template.');
    // The industry template builds sales pipelines and inspection accreditation
    // rules — only meaningful where sales or operations is on. A recruitment
    // workspace configures its wording under Terminology instead.
    ops_require(!function_exists('licence_enabled') || licence_enabled('sales') || licence_enabled('operations'),
        'Industry templates apply to the Sales and Operations modules, which are not part of your plan. Set your wording under Terminology instead.');

    if ($route === 'industry-apply' && $method === 'POST') {
        $key = preg_replace('/[^a-z]/', '', (string)($_POST['key'] ?? ''));
        // V2.1: a template another one has replaced is not a new choice.
        if (isset(INDUSTRY_TEMPLATES[$key]) && !industry_offered($key)) {
            $by = array_map('industry_label', (array) (INDUSTRY_TEMPLATES[$key]['replaced_by'] ?? []));
            flash('"' . industry_label($key) . '" is no longer offered for a new choice — use ' . implode(' or ', $by) . ' instead.', 'error');
            redirect('/industry');
        }
        // The company's industry — wording and accreditation: an administrator,
        // and only once they have confirmed the changes they were shown.
        if (($_POST['do'] ?? '') === 'company') {
            ops_require(industry_can_company(), 'Only an administrator can change the company\'s wording and accreditation rules.');
            if (empty($_POST['confirm'])) { flash('Tick the confirmation first — it lists what will change.', 'error'); redirect('/industry?show=' . $key); }
            $r = industry_set_company($key);
            if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/industry'); }
            flash('Your company\'s industry is now ' . $r['label'] . '. '
                . (!empty($r['units_added']) ? 'Quotation and order lines now offer ' . implode(', ', array_slice(array_values(INDUSTRY_UNITS[$key] ?? []), 0, 4)) . (count(INDUSTRY_UNITS[$key] ?? []) > 4 ? ' and more' : '') . '. ' : '')
                . ($r['accreditation_pack'] !== '' ? 'The ' . INDUSTRY_ACCR_LABELS[$r['accreditation_pack']] . ' accreditation rules are ON.' : 'No accreditation rules are on.'));
            redirect('/industry?show=' . $key);
        }
        // Adding a template's pipelines changes nothing else.
        $r = industry_apply_pipelines($key);
        if (!empty($r['err'])) { flash($r['err'], 'error'); redirect('/industry'); }
        $n = 2 + count($r['pipelines']['alt'] ?? []);
        flash($r['label'] . ' pipelines added. ' . $n . ' pipelines created — a main funnel (now the default), '
            . 'an alternate funnel and a lead pipeline'
            . ($r['list_values'] ? ', with ' . $r['list_values'] . ' list values added' . (!empty($r['units_added']) ? ' (including ' . $r['units_added'] . ' billing units for quotation lines, such as ' . implode(', ', array_slice(array_values(INDUSTRY_UNITS[$key] ?? []), 0, 3)) . ')' : '') : '')
            . '. Your wording and accreditation rules are unchanged. Anything already in flight keeps the pipeline it was on.');
        redirect('/opportunities');
    }

    view('ops/industry', [
        'templates' => industry_choices(),
        'current'   => industry_current(),
        'appliedAt' => (string)setting_get('industry_applied_at', ''),
        'preview'   => industry_preview((string)($_GET['show'] ?? '') ?: (industry_current() ?: 'generic')),
        'showKey'   => (string)($_GET['show'] ?? '') ?: (industry_current() ?: 'generic'),
        'canCompany'=> industry_can_company(),
        'companyChanges' => industry_company_changes((string)($_GET['show'] ?? '') ?: (industry_current() ?: 'generic')),
    ]);
    return true;
}
