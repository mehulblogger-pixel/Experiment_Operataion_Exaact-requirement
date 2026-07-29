<?php
// ============================================================================
//  The chain — CRM to operations to the books, as one thread
//
//  THE COMPLAINT THIS ANSWERS, in the owner's words: "they are very very
//  shallow and is not integrated with the data of the application in different
//  modules as no data is connected to any."
//
//  Measured rather than assumed, the columns were all there and mostly empty:
//
//      leads converted to a customer .......... 1
//      quotations raised from an inquiry ...... 1
//      calls raised from a quotation .......... 0 of 160
//      deputations under a call ............. 165 of 170
//      reports tied to a deputation ........... 4 of 170
//
//  So `calls.quotation_id` existed and NOTHING ever put a value in it. That is
//  the join between selling and doing, and it was empty on every row. Nobody
//  found out, because no screen ever showed the link and nothing ever asked for
//  it. A field that is never displayed and never required is a field that does
//  not exist.
//
//  This file does two things about that:
//
//    1. **Shows the thread.** From any record — a lead, a quotation, a
//       deputation, a report, an invoice — walk to the top of the chain and
//       back down, so somebody looking at an invoice can see which enquiry it
//       started as, and somebody looking at a lead can see whether it was ever
//       paid for.
//    2. **Names where the thread is cut.** /flow-gaps lists the breaks: work
//       done against no order, closed work with no report, closed work nobody
//       billed, invoices past their date. Each one is a real handover that was
//       missed, and each row links to the screen that fixes it.
//
//  Nothing here writes. It is a reader over links that already exist, which is
//  deliberate: a report that quietly repaired the data would hide how often the
//  handover is skipped, and that number is the point.
// ============================================================================

// The stages, in the order work moves through them. The key is also the entity
// kind used by the activity timeline, so the two agree about what things are.
const CHAIN_STAGES = [
    'LEAD'        => ['Lead',        '🎯', '/lead?id='],
    'OPPORTUNITY' => ['Opportunity', '💡', '/opportunity?id='],
    'INQUIRY'     => ['Enquiry',     '📨', '/inquiry-edit?id='],
    'QUOTE'       => ['Quotation',   '📝', '/quote?id='],
    'CALL'        => ['Order',       '📞', '/call?id='],
    'JOB'         => ['Deputation',  '🗂', '/job?id='],
    'REPORT'      => ['Report',      '📑', '/document?id='],
    'INVOICE'     => ['Invoice',     '🧾', '/invoice?id='],
    'RECEIPT'     => ['Money in',    '💰', '/receipt?id='],
];

function chain_try($fn, $fb = null) {
    try { return $fn(); } catch (Throwable $e) { return $fb; }
}
function chain_all($sql, $args = []) { return chain_try(fn() => ops_all($sql, $args), []) ?: []; }
function chain_one($sql, $args = []) { return chain_try(fn() => ops_one($sql, $args), null); }

// ---- Walking up -------------------------------------------------------------
// From wherever somebody is standing, find the earliest record in the thread.
// Each hop is one column, and a hop that is missing simply stops the walk —
// which is the normal case today and exactly what /flow-gaps counts.
function chain_root($kind, $id) {
    $kind = strtoupper((string)$kind); $id = (int)$id;
    $guard = 0;
    while ($guard++ < 12) {
        switch ($kind) {
            case 'RECEIPT':
                $a = chain_one("SELECT invoice_id FROM receipt_allocations WHERE receipt_id=? ORDER BY id LIMIT 1", [$id]);
                if ($a && $a['invoice_id']) { $kind = 'INVOICE'; $id = (int)$a['invoice_id']; continue 2; }
                return ['RECEIPT', $id];
            case 'INVOICE':
                $l = chain_one("SELECT job_id FROM invoice_lines WHERE invoice_id=? AND job_id IS NOT NULL ORDER BY seq, id LIMIT 1", [$id]);
                if ($l && $l['job_id']) { $kind = 'JOB'; $id = (int)$l['job_id']; continue 2; }
                return ['INVOICE', $id];
            case 'REPORT':
                $d = chain_one("SELECT job_id, call_id FROM report_docs WHERE id=?", [$id]);
                if ($d && $d['job_id']) { $kind = 'JOB'; $id = (int)$d['job_id']; continue 2; }
                if ($d && $d['call_id']) { $kind = 'CALL'; $id = (int)$d['call_id']; continue 2; }
                return ['REPORT', $id];
            case 'JOB':
                $j = chain_one("SELECT call_id, quotation_id FROM jobs WHERE id=?", [$id]);
                if ($j && $j['call_id']) { $kind = 'CALL'; $id = (int)$j['call_id']; continue 2; }
                if ($j && $j['quotation_id']) { $kind = 'QUOTE'; $id = (int)$j['quotation_id']; continue 2; }
                return ['JOB', $id];
            case 'CALL':
                // An order raised from a won deal points back at it, which is a
                // shorter and truer route than going via the quotation.
                $o = chain_one("SELECT id FROM opportunities WHERE call_id=? LIMIT 1", [$id]);
                if ($o) { $kind = 'OPPORTUNITY'; $id = (int)$o['id']; continue 2; }
                $c = chain_one("SELECT quotation_id FROM calls WHERE id=?", [$id]);
                if ($c && $c['quotation_id']) { $kind = 'QUOTE'; $id = (int)$c['quotation_id']; continue 2; }
                return ['CALL', $id];
            case 'QUOTE':
                // Follow a revision back to the original first, so rev 3 and the
                // original show the same thread rather than two half-threads.
                $q = chain_one("SELECT parent_id, inquiry_id FROM quotations WHERE id=?", [$id]);
                if ($q && $q['parent_id']) { $id = (int)$q['parent_id']; continue 2; }
                $oq = chain_one("SELECT opportunity_id FROM opportunity_quotes WHERE quotation_id=? LIMIT 1", [$id]);
                if ($oq && $oq['opportunity_id']) { $kind = 'OPPORTUNITY'; $id = (int)$oq['opportunity_id']; continue 2; }
                if ($q && $q['inquiry_id']) { $kind = 'INQUIRY'; $id = (int)$q['inquiry_id']; continue 2; }
                return ['QUOTE', $id];
            case 'INQUIRY':
                $o = chain_one("SELECT id FROM opportunities WHERE inquiry_id=? LIMIT 1", [$id]);
                if ($o) { $kind = 'OPPORTUNITY'; $id = (int)$o['id']; continue 2; }
                $l = chain_one("SELECT id FROM leads WHERE converted_inquiry_id=? LIMIT 1", [$id]);
                if ($l) return ['LEAD', (int)$l['id']];
                return ['INQUIRY', $id];
            case 'OPPORTUNITY':
                $o = chain_one("SELECT lead_id FROM opportunities WHERE id=?", [$id]);
                if ($o && $o['lead_id']) return ['LEAD', (int)$o['lead_id']];
                return ['OPPORTUNITY', $id];
            default:
                return ['LEAD', $id];
        }
    }
    return [$kind, $id];
}

// ---- Walking down -----------------------------------------------------------
// From the root, collect every record at every later stage. Returns the stages
// in order, each with its rows; a stage with nothing in it is kept and marked,
// because an EMPTY stage is the interesting one.
function chain_from($kind, $id) {
    $kind = strtoupper((string)$kind); $id = (int)$id;
    $out = [];
    $put = function ($k, array $rows) use (&$out) { $out[$k] = array_values(array_filter($rows)); };

    $leads = $opps = $inqs = $quotes = $calls = $jobs = $reports = $invoices = $receipts = [];

    if ($kind === 'LEAD') {
        $l = chain_one("SELECT l.*, s.name stage_name FROM leads l LEFT JOIN pipeline_stages s ON s.id=l.stage_id WHERE l.id=?", [$id]);
        if ($l) {
            $leads = [$l];
            $o = chain_one("SELECT o.*, s.name stage_name FROM opportunities o LEFT JOIN pipeline_stages s ON s.id=o.stage_id WHERE o.lead_id=? LIMIT 1", [(int)$l['id']]);
            if ($o) { $kind = 'OPPORTUNITY'; $id = (int)$o['id']; }
            elseif ($l['converted_inquiry_id']) { $kind = 'INQUIRY'; $id = (int)$l['converted_inquiry_id']; }
        }
    }
    if ($kind === 'OPPORTUNITY') {
        $o = chain_one("SELECT o.*, s.name stage_name FROM opportunities o LEFT JOIN pipeline_stages s ON s.id=o.stage_id WHERE o.id=?", [$id]);
        if ($o) {
            $opps = [$o];
            if (!$leads && $o['lead_id']) {
                $l = chain_one("SELECT l.*, s.name stage_name FROM leads l LEFT JOIN pipeline_stages s ON s.id=l.stage_id WHERE l.id=?", [(int)$o['lead_id']]);
                if ($l) $leads = [$l];
            }
            // A deal's quotations come from the join table; they are the reason
            // one opportunity must not be counted as three.
            $quotes = chain_all("SELECT q.* FROM opportunity_quotes oq JOIN quotations q ON q.id=oq.quotation_id
                                 WHERE oq.opportunity_id=? ORDER BY q.quote_no, q.rev", [(int)$o['id']]);
            // An order raised straight from the deal is the strongest link
            // there is; follow it rather than hunting through the paperwork.
            if (!empty($o['call_id'])) {
                $c = chain_one("SELECT c.*, COALESCE(bp.display_name,bp.legal_name) client_name FROM calls c
                                LEFT JOIN business_partners bp ON bp.id=c.client_id WHERE c.id=?", [(int)$o['call_id']]);
                if ($c) { $calls = [$c]; $kind = 'CALL'; $id = (int)$c['id']; }
            }
            if ($kind === 'OPPORTUNITY') {
                if ($o['inquiry_id']) { $kind = 'INQUIRY'; $id = (int)$o['inquiry_id']; }
                elseif ($quotes) { $kind = 'QUOTE'; $id = (int)$quotes[0]['id']; }
            }
        }
    }
    if ($kind === 'INQUIRY') {
        $i = chain_one("SELECT * FROM crm_inquiries WHERE id=?", [$id]);
        if ($i) { $inqs = [$i]; }
        $quotes = chain_all("SELECT * FROM quotations WHERE inquiry_id=? ORDER BY id", [$id]);
        $kind = 'QUOTE'; $id = $quotes ? (int)$quotes[0]['id'] : 0;
    }
    if ($kind === 'QUOTE' && $id) {
        if (!$quotes) {
            // Every revision of the same quotation is one node in the thread.
            $quotes = chain_all("SELECT * FROM quotations WHERE id=? OR parent_id=? ORDER BY rev, id", [$id, $id]);
            if (!$quotes) { $q = chain_one("SELECT * FROM quotations WHERE id=?", [$id]); if ($q) $quotes = [$q]; }
        }
        if (!$inqs && $quotes && !empty($quotes[0]['inquiry_id'])) {
            $i = chain_one("SELECT * FROM crm_inquiries WHERE id=?", [(int)$quotes[0]['inquiry_id']]);
            if ($i) $inqs = [$i];
        }
    }
    $qIds = array_map(fn($q) => (int)$q['id'], $quotes);
    if ($qIds) {
        $in = implode(',', array_fill(0, count($qIds), '?'));
        $calls = chain_all("SELECT c.*, COALESCE(bp.display_name,bp.legal_name) client_name FROM calls c
                            LEFT JOIN business_partners bp ON bp.id=c.client_id
                            WHERE c.quotation_id IN ($in) ORDER BY c.id", $qIds);
    }
    if ($kind === 'CALL' && $id) {
        $c = chain_one("SELECT c.*, COALESCE(bp.display_name,bp.legal_name) client_name FROM calls c
                        LEFT JOIN business_partners bp ON bp.id=c.client_id WHERE c.id=?", [$id]);
        if ($c) $calls = [$c];
    }
    $cIds = array_map(fn($c) => (int)$c['id'], $calls);
    if ($cIds) {
        $in = implode(',', array_fill(0, count($cIds), '?'));
        $jobs = chain_all("SELECT j.*, i.name inspector_name FROM jobs j
                           LEFT JOIN inspectors i ON i.id=j.inspector_id
                           WHERE j.call_id IN ($in) ORDER BY j.id", $cIds);
    }
    if ($kind === 'JOB' && $id) {
        $j = chain_one("SELECT j.*, i.name inspector_name FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id WHERE j.id=?", [$id]);
        if ($j) {
            $jobs = [$j];
            if ($j['call_id'] && !$calls) {
                $c = chain_one("SELECT c.*, COALESCE(bp.display_name,bp.legal_name) client_name FROM calls c
                                LEFT JOIN business_partners bp ON bp.id=c.client_id WHERE c.id=?", [(int)$j['call_id']]);
                if ($c) $calls = [$c];
            }
        }
    }
    if ($kind === 'REPORT' && $id && !$jobs) {
        $d = chain_one("SELECT * FROM report_docs WHERE id=?", [$id]);
        if ($d) $reports = [$d];
    }
    $jIds = array_map(fn($j) => (int)$j['id'], $jobs);
    if ($jIds) {
        $in = implode(',', array_fill(0, count($jIds), '?'));
        $reports  = chain_all("SELECT * FROM report_docs WHERE job_id IN ($in) AND deleted=0 ORDER BY id", $jIds);
        $invoices = chain_all("SELECT DISTINCT i.* FROM invoices i JOIN invoice_lines l ON l.invoice_id=i.id
                               WHERE l.job_id IN ($in) ORDER BY i.id", $jIds);
    }
    if ($kind === 'INVOICE' && $id && !$invoices) {
        $inv = chain_one("SELECT * FROM invoices WHERE id=?", [$id]);
        if ($inv) $invoices = [$inv];
    }
    $iIds = array_map(fn($i) => (int)$i['id'], $invoices);
    if ($iIds) {
        $in = implode(',', array_fill(0, count($iIds), '?'));
        // One row per RECEIPT, not per allocation. A receipt that settles an
        // invoice with cash and TDS has two allocations, and listing it twice
        // reads as the customer having paid twice.
        $receipts = chain_all("SELECT r.*, SUM(a.amount) alloc_amount,
                                      MAX(CASE WHEN a.kind='TDS' THEN 1 ELSE 0 END) has_tds
                               FROM receipts r JOIN receipt_allocations a ON a.receipt_id=r.id
                               WHERE a.invoice_id IN ($in)
                               GROUP BY r.id ORDER BY r.receipt_date, r.id", $iIds);
    }

    $put('LEAD', $leads);     $put('OPPORTUNITY', $opps);  $put('INQUIRY', $inqs);   $put('QUOTE', $quotes);
    $put('CALL', $calls);     $put('JOB', $jobs);       $put('REPORT', $reports);
    $put('INVOICE', $invoices); $put('RECEIPT', $receipts);
    return $out;
}

// One line per record, in the shape the strip and the trace page both draw.
function chain_label($stage, array $r) {
    switch ($stage) {
        case 'LEAD':    return [$r['ref'] ?: 'Lead', $r['company_name'] ?? '', $r['stage_name'] ?? ''];
        case 'OPPORTUNITY': return [$r['ref'] ?: 'Opportunity', $r['name'] ?? '',
                                    OPP_STATUS[$r['status']] ?? ($r['stage_name'] ?? '')];
        case 'INQUIRY': return [$r['inquiry_no'] ?: 'Enquiry', $r['subject'] ?? '', $r['status'] ?? ''];
        case 'QUOTE':   return [$r['quote_no'] . ((int)($r['rev'] ?? 0) ? ' r' . (int)$r['rev'] : ''),
                                $r['subject'] ?? '', $r['status'] ?? ''];
        case 'CALL':    return [$r['call_code'] ?? '', $r['client_name'] ?? '', $r['status'] ?? ''];
        case 'JOB':     return [$r['job_code'] ?? '', $r['inspector_name'] ?? '',
                                !empty($r['closed_flag']) ? 'Closed' : 'Open'];
        case 'REPORT':  return [$r['irn'] ?: 'Report', $r['title'] ?? '', $r['status'] ?? ''];
        case 'INVOICE': return [$r['invoice_no'] ?: 'Draft', fmoney($r['total'] ?? 0),
                                INV_STATUS[$r['status']] ?? ($r['status'] ?? '')];
        case 'RECEIPT': return [$r['receipt_no'] ?? '', fmoney($r['alloc_amount'] ?? $r['amount'] ?? 0),
                                !empty($r['has_tds']) ? 'Cash + TDS' : 'Received'];
    }
    return ['', '', ''];
}

// The compact bar that goes at the top of a detail screen. Deliberately small:
// its job is to say "here is where this sits and what is missing", not to be a
// second copy of every screen it links to.
function chain_strip($kind, $id, $hereKind = '', $hereId = 0) {
    [$rk, $ri] = chain_root($kind, $id);
    $chain = chain_from($rk, $ri);
    $hereKind = strtoupper($hereKind ?: $kind); $hereId = (int)($hereId ?: $id);
    $any = false; foreach ($chain as $rows) if ($rows) { $any = true; break; }
    if (!$any) return '';

    $h = '<nav class="chain" aria-label="Where this sits, from enquiry to payment">';
    foreach (CHAIN_STAGES as $key => [$label, $icon, $url]) {
        $rows = $chain[$key] ?? [];
        if (!$rows) {
            $h .= '<span class="chain-step chain-none" title="' . e($label) . ' — nothing at this stage">'
               . '<span class="chain-ic">' . $icon . '</span><span class="chain-lb">' . e($label) . '</span>'
               . '<span class="chain-v">—</span></span>';
            continue;
        }
        $first = $rows[0];
        [$ref, , $state] = chain_label($key, $first);
        $isHere = ($key === $hereKind) && (int)($first['id'] ?? 0) === $hereId;
        $more = count($rows) > 1 ? ' +' . (count($rows) - 1) : '';
        $h .= '<a class="chain-step' . ($isHere ? ' chain-here' : '') . '" href="' . e($url . (int)$first['id']) . '"'
           . ' title="' . e($label . ' — ' . $state) . '">'
           . '<span class="chain-ic">' . $icon . '</span><span class="chain-lb">' . e($label) . '</span>'
           . '<span class="chain-v">' . e($ref) . e($more) . '</span></a>';
    }
    $h .= '<a class="chain-step chain-all" href="' . e('/trace?kind=' . urlencode($hereKind) . '&id=' . $hereId) . '">'
       . '<span class="chain-ic">⇢</span><span class="chain-lb">Whole thread</span><span class="chain-v">open</span></a>';
    $h .= '</nav>';
    return $h;
}

// ---- Where the thread is cut ------------------------------------------------
// Each gap is a handover somebody skipped, with the screen that closes it. The
// counts are the honest measure of how joined-up the data actually is.
function chain_gaps() {
    $g = [];

    [$cw, $ca] = scope_clause('c.executing_office_id', 'c.sbu');
    $g['call_no_quote'] = [
        'label' => 'Work ordered against no quotation',
        'why'   => 'The order carries no link to what was quoted, so nobody can check the rate that was agreed, and the sale never joins up with the work.',
        'fix'   => 'Open the order and set the quotation it came from.',
        'rows'  => chain_all("SELECT c.id, c.call_code ref, c.call_received_date d,
                                     COALESCE(bp.display_name,bp.legal_name) who, '/call?id=' url_id
                              FROM calls c LEFT JOIN business_partners bp ON bp.id=c.client_id
                              WHERE $cw AND (c.quotation_id IS NULL OR c.quotation_id=0)
                              ORDER BY c.id DESC LIMIT 200", $ca),
        'url'   => '/call?id=',
    ];

    [$jw, $ja] = scope_clause('j.executing_office_id', 'j.sbu');
    $g['job_no_report'] = [
        'label' => 'Work closed with no report on file',
        'why'   => 'The deputation is closed but no report is linked to it, so what the customer was actually sent is not in the system.',
        'fix'   => 'Open the deputation and attach or raise the report.',
        'rows'  => chain_all("SELECT j.id, j.job_code ref, j.closed_at d, i.name who
                              FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id
                              WHERE $jw AND j.closed_flag=1
                                AND NOT EXISTS (SELECT 1 FROM report_docs d WHERE d.job_id=j.id AND d.deleted=0)
                              ORDER BY j.closed_at DESC, j.id DESC LIMIT 200", $ja),
        'url'   => '/job?id=',
    ];

    $g['job_no_invoice'] = [
        'label' => 'Work closed and never billed',
        'why'   => 'Finished work that is on no invoice. This is money the company has earned and not asked for.',
        'fix'   => 'Bill it from the waiting-to-be-billed screen.',
        'rows'  => chain_all("SELECT j.id, j.job_code ref, j.closed_at d,
                                     COALESCE(bp.display_name,bp.legal_name) who
                              FROM jobs j
                              LEFT JOIN calls c ON c.id=j.call_id
                              LEFT JOIN business_partners bp ON bp.id=c.client_id
                              WHERE $jw AND j.closed_flag=1
                                AND NOT EXISTS (SELECT 1 FROM invoice_lines l JOIN invoices v ON v.id=l.invoice_id
                                                WHERE l.job_id=j.id AND v.status <> 'CANCELLED')
                              ORDER BY j.closed_at DESC, j.id DESC LIMIT 200", $ja),
        'url'   => '/job?id=',
        'action'=> ['/to-bill', 'Open the billing handover'],
    ];

    $g['job_no_call'] = [
        'label' => 'Deputations under no order',
        'why'   => 'Somebody was sent to do work with no order behind it, so it cannot be traced to a customer, a rate or a quotation.',
        'fix'   => 'Open the deputation and set the order.',
        'rows'  => chain_all("SELECT j.id, j.job_code ref, j.scheduled_date d, i.name who
                              FROM jobs j LEFT JOIN inspectors i ON i.id=j.inspector_id
                              WHERE $jw AND (j.call_id IS NULL OR j.call_id=0)
                              ORDER BY j.id DESC LIMIT 200", $ja),
        'url'   => '/job?id=',
    ];

    if (function_exists('opp_migrate') && function_exists('licence_enabled') && licence_enabled('operations')) {
        opp_migrate();
        [$ow, $oa] = scope_office_clause('o.office_id');
        $g['won_no_order'] = [
            'label' => 'Deals won and never ordered',
            'why'   => 'The deal was won and nobody raised the order, so the work has not started and the customer is waiting.',
            'fix'   => 'Open the deal and raise the order — it carries the quotation and the agreed value across.',
            'rows'  => chain_all("SELECT o.id, o.ref, o.name who, o.won_at d, o.partner_name
                                  FROM opportunities o
                                  WHERE $ow AND o.status='WON' AND (o.call_id IS NULL OR o.call_id=0)
                                  ORDER BY o.won_at DESC LIMIT 200", $oa),
            'url'   => '/opportunity?id=',
            'action'=> ['/opportunities', 'Open the pipeline'],
        ];
    }

    if (function_exists('books_migrate')) {
        books_migrate();
        [$iw, $ia] = scope_clause('i.office_id', 'i.sbu');
        $today = date('Y-m-d');
        $g['invoice_overdue'] = [
            'label' => 'Invoices past their date',
            'why'   => 'Issued, still outstanding, and past the date the customer agreed to.',
            'fix'   => 'Chase it, or record the money if it has arrived.',
            'rows'  => array_values(array_filter(array_map(function ($r) {
                            $s = books_settled((int)$r['id']);
                            if ($s['outstanding'] <= 1) return null;
                            $r['who'] = $r['who'] . ' — ' . fmoney($s['outstanding']) . ' outstanding';
                            return $r;
                        }, chain_all("SELECT i.id, i.invoice_no ref, i.due_date d, i.partner_name who
                                      FROM invoices i
                                      WHERE $iw AND i.status IN ('ISSUED','PART_PAID')
                                        AND i.due_date <> '' AND i.due_date < ?
                                      ORDER BY i.due_date ASC LIMIT 200", array_merge($ia, [$today]))))),
            'url'   => '/invoice?id=',
            'action'=> ['/receivables', 'Open the ageing'],
        ];
        $g['receipt_unmatched'] = [
            'label' => 'Money received and not matched',
            'why'   => 'It is in the customer\'s ledger, so their balance is right, but nobody has said which invoice it settles — so the ageing still shows those invoices as owing.',
            'fix'   => 'Open the receipt and match it.',
            'rows'  => chain_all("SELECT r.id, r.receipt_no ref, r.receipt_date d, r.partner_name who
                                  FROM receipts r
                                  WHERE (r.amount + r.tds_amount) >
                                        COALESCE((SELECT SUM(x.amount) FROM receipt_allocations x WHERE x.receipt_id=r.id),0) + 0.01
                                  ORDER BY r.receipt_date DESC LIMIT 200"),
            'url'   => '/receipt?id=',
            'action'=> ['/receipts?f=unallocated', 'Open money in'],
        ];
    }

    foreach ($g as $k => $v) $g[$k]['n'] = count($v['rows']);
    return $g;
}

function chain_can() {
    return can('mod.calls.view') || can('mod.jobs.view') || can('mod.quotes.view') || is_master_of(['calls','jobs','quotes']);
}

function ops_chain($route, $method) {
    ops_require(chain_can(), 'You cannot open the trace.');

    if ($route === 'flow-gaps') {
        $gaps = chain_gaps();
        if (wants_csv()) {
            $csv = [['Gap','Reference','Date','Who']];
            foreach ($gaps as $g) foreach ($g['rows'] as $r)
                $csv[] = [$g['label'], $r['ref'], $r['d'], $r['who']];
            csv_download('flow-gaps-' . date('Y-m-d') . '.csv', $csv);
        }
        view('ops/flow_gaps', ['gaps' => $gaps]);
        return true;
    }

    // /trace
    $kind = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_GET['kind'] ?? '')));
    $id   = (int)($_GET['id'] ?? 0);
    if (!isset(CHAIN_STAGES[$kind]) || !$id) { http_response_code(404); view('notfound'); return true; }
    [$rk, $ri] = chain_root($kind, $id);
    view('ops/trace', ['chain' => chain_from($rk, $ri), 'kind' => $kind, 'id' => $id]);
    return true;
}
