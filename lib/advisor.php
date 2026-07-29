<?php
// ============================================================================
//  The advisor — not "here is a problem" but "here is what it costs you,
//  here is exactly how to fix it, and here is who does it"
//
//  THE REQUIREMENT: "It should not only show the problems, but it should also
//  show how to fix it ... it shall be very very detailed." And: "Analytics must
//  be the core of this system and the USP which keeps us far forward of
//  existing CRMs."
//
//  WHY EXISTING CRMs FAIL AT THIS, and it is worth being precise because the
//  gap is the product. Every CRM has dashboards. Dashboards report state: 47
//  open deals, ₹12 lakh overdue, 8 stalled. A number is not an instruction. The
//  person reading it still has to work out (a) whether it matters, (b) what
//  caused it, (c) what to do, and (d) whether it is their job. Most people get
//  as far as (a), decide it is somebody else's, and close the tab. That is why
//  dashboards get looked at in week one and ignored by week six.
//
//  So every finding here carries five things, and a finding without all five is
//  not allowed in:
//
//    1. WHAT — the count, and the rows, so it is checkable rather than asserted.
//    2. WHAT IT COSTS — in rupees or days, computed, not adjectives. "97 jobs
//       unbilled" is a statistic. "₹41 lakh of finished work you have not asked
//       to be paid for" is a decision.
//    3. WHY IT HAPPENED — the mechanism, so the same thing stops recurring.
//    4. HOW TO FIX IT — numbered steps against real screens in THIS product,
//       not advice. "Open /to-bill, tick the customer's work, press Draft."
//    5. WHO — the role that owns it, so it is not everybody's and nobody's.
//
//  RANKED BY MONEY, not by count. Twelve overdue invoices worth ₹80,000 matter
//  less than one worth ₹9 lakh, and a list sorted by count says the opposite.
//
//  IT NEVER FIXES ANYTHING ITSELF. A tool that quietly repaired the data would
//  hide how often the handover is skipped, and that rate is the thing worth
//  managing. It shows, explains, and links to the screen where a person acts.
// ============================================================================

const ADV_SEVERITY = ['CASH' => 'Money', 'RISK' => 'Risk', 'DATA' => 'Data quality', 'SPEED' => 'Speed'];

function adv_can() {
    return can('mod.calls.view') || can('mod.jobs.view') || can('mod.quotes.view')
        || can('mod.invoicing.view') || is_master_of(['calls','jobs','quotes','invoicing']);
}

function adv_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }
function adv_rows($sql, $a = []) { return adv_try(fn() => ops_all($sql, $a), []) ?: []; }
function adv_val($sql, $a = [], $fb = 0) { $v = adv_try(fn() => ops_val($sql, $a), $fb); return $v === false || $v === null ? $fb : $v; }

function adv_on($module) {
    if (function_exists('licence_owner')) {
        $o = licence_owner($module);
        if ($o !== null && function_exists('licence_enabled') && !licence_enabled($o)) return false;
    }
    return can("mod.$module.view") || is_master_of($module);
}

// ---- The checks -------------------------------------------------------------
// Each returns null when it does not apply (module absent) or when there is
// nothing wrong. A finding that says "0 problems" is noise; the screen says
// "nothing wrong here" once, at the end, rather than twelve times.

function adv_unbilled_work() {
    if (!adv_on('jobs') || !adv_on('invoicing')) return null;
    [$w, $a] = scope_clause('j.executing_office_id', 'j.sbu');
    $rows = adv_rows(
        "SELECT j.id, j.job_code ref, j.closed_at d, COALESCE(bp.display_name,bp.legal_name) who,
                COALESCE(NULLIF(c.billable_value,0), NULLIF(j.invoice_value,0), 0) amount,
                c.client_id
         FROM jobs j
         LEFT JOIN calls c ON c.id = j.call_id
         LEFT JOIN business_partners bp ON bp.id = c.client_id
         WHERE $w AND j.closed_flag = 1
           AND NOT EXISTS (SELECT 1 FROM invoice_lines l JOIN invoices v ON v.id = l.invoice_id
                           WHERE l.job_id = j.id AND v.status <> 'CANCELLED')
         ORDER BY amount DESC, j.closed_at ASC LIMIT 300", $a);
    if (!$rows) return null;
    $total = 0.0; $noValue = 0; $oldest = null;
    foreach ($rows as $r) {
        $total += (float)$r['amount'];
        if ((float)$r['amount'] <= 0) $noValue++;
        $d = trim((string)$r['d']);
        if ($d !== '' && ($oldest === null || $d < $oldest)) $oldest = $d;
    }
    $ageDays = $oldest ? (int)floor((time() - strtotime($oldest)) / 86400) : null;
    return [
        'key' => 'unbilled', 'severity' => 'CASH',
        'title' => 'Finished work you have never asked to be paid for',
        'n' => count($rows), 'value' => $total,
        'cost' => 'About ' . fmoney($total) . ' of work is done, closed, and on no invoice'
                . ($ageDays !== null ? '. The oldest finished ' . $ageDays . ' days ago' : '') . '.',
        'why' => 'Closing a deputation and invoicing it are two separate acts, and nothing has ever '
               . 'connected them. Work got closed by operations; invoicing happened when somebody in '
               . 'accounts remembered. Nobody was ever shown the list of what had been forgotten.',
        'steps' => [
            'Open <b>Waiting to be billed</b> — it groups every closed, unbilled deputation by customer.',
            'Start with the customer at the top; the list is ordered by value, so that is the biggest cheque.',
            'Tick the deputations this invoice should cover — one invoice across several is normal for a monthly account — and press <b>Draft one invoice</b>.',
            'Check the lines. Each carries the rate from its deputation, so nothing is re-keyed. '
              . ($noValue ? '<b>' . $noValue . ' of these have no value recorded</b> — set the rate on the line before issuing.' : ''),
            'Press <b>Issue</b>. Only then is a number allocated, and the invoice appears in the ledger and the ageing.',
            'Repeat for the next customer. Ten minutes of this is usually the most profitable ten minutes of the week.',
        ],
        'stop' => 'It stops recurring on its own: from now on the same screen shows anything newly closed and unbilled, '
                . 'so the list should be short every week rather than years deep once a year.',
        'who' => 'Accounts, with a coordinator to confirm rates where none is recorded',
        'link' => '/to-bill', 'link_label' => 'Open Waiting to be billed',
        'rows' => array_slice($rows, 0, 12), 'row_url' => '/job?id=',
    ];
}

function adv_overdue_invoices() {
    if (!adv_on('invoicing') || !function_exists('books_settled')) return null;
    books_migrate();
    [$w, $a] = scope_clause('i.office_id', 'i.sbu');
    $today = date('Y-m-d');
    $out = []; $total = 0.0; $worst = 0;
    foreach (adv_rows("SELECT i.id, i.invoice_no ref, i.due_date d, i.partner_name who, i.total, i.partner_id
                       FROM invoices i
                       WHERE $w AND i.status IN ('ISSUED','PART_PAID') AND i.due_date <> '' AND i.due_date < ?
                       ORDER BY i.due_date ASC LIMIT 300", array_merge($a, [$today])) as $r) {
        $s = adv_try(fn() => books_settled((int)$r['id']), null);
        $owed = $s ? $s['outstanding'] : (float)$r['total'];
        if ($owed <= 1) continue;
        $days = (int)floor((strtotime($today) - strtotime((string)$r['d'])) / 86400);
        $worst = max($worst, $days);
        $r['amount'] = $owed; $r['days'] = $days;
        $out[] = $r; $total += $owed;
    }
    if (!$out) return null;
    usort($out, fn($x, $y) => $y['amount'] <=> $x['amount']);
    return [
        'key' => 'overdue', 'severity' => 'CASH',
        'title' => 'Invoices past the date the customer agreed to',
        'n' => count($out), 'value' => $total,
        'cost' => fmoney($total) . ' is overdue, the worst by ' . $worst . ' days.',
        'why' => 'Usually one of three things, and they need different answers: the customer has paid and '
               . 'nobody matched the receipt; the customer is disputing something; or nobody has asked.',
        'steps' => [
            'First rule out the easy one: open <b>Money in → Not yet matched</b>. Money that arrived and was never '
              . 'matched to an invoice leaves that invoice showing as owing even though it is paid.',
            'For the rest, open the biggest first — the list here is sorted by amount, not by age, because a '
              . '₹9 lakh invoice 20 days late matters more than a ₹9,000 one 200 days late.',
            'On the invoice, look at <b>Customer 360</b> for that customer: if there is an open complaint or a '
              . 'rejected report, the invoice is being held for a reason and chasing it will not work — fix that first.',
            'If it is a genuine chase, ring the named contact on the customer record, then <b>record the call</b> '
              . 'against the customer so the next person can see it was chased and when.',
            'If they say they have paid, ask for the UTR, then <b>Money in → Record money received</b> and match it. '
              . 'If they deducted TDS, enter it in the TDS box — otherwise the invoice will look short for ever.',
            'If part of the invoice is genuinely wrong, raise a <b>credit note</b> rather than editing it. Editing an '
              . 'issued invoice leaves two versions of a document the customer already has.',
        ],
        'stop' => 'The ageing now reads the invoice register directly, so a part payment or a TDS deduction reduces '
                . 'what is shown the moment it is recorded, instead of the invoice staying at full value until a flag flips.',
        'who' => 'Accounts',
        'link' => '/receivables', 'link_label' => 'Open the ageing',
        'rows' => array_slice($out, 0, 12), 'row_url' => '/invoice?id=',
    ];
}

function adv_unmatched_receipts() {
    if (!adv_on('invoicing') || !function_exists('books_migrate')) return null;
    books_migrate();
    $rows = adv_rows(
        "SELECT r.id, r.receipt_no ref, r.receipt_date d, r.partner_name who,
                (r.amount + r.tds_amount - COALESCE((SELECT SUM(x.amount) FROM receipt_allocations x
                                                     WHERE x.receipt_id = r.id),0)) amount
         FROM receipts r
         WHERE (r.amount + r.tds_amount) >
               COALESCE((SELECT SUM(x.amount) FROM receipt_allocations x WHERE x.receipt_id = r.id),0) + 0.01
         ORDER BY amount DESC LIMIT 200");
    if (!$rows) return null;
    $total = 0.0; foreach ($rows as $r) $total += (float)$r['amount'];
    return [
        'key' => 'unmatched', 'severity' => 'CASH',
        'title' => 'Money received that nobody has matched to an invoice',
        'n' => count($rows), 'value' => $total,
        'cost' => fmoney($total) . ' is sitting on customers\' ledgers unallocated. Their balances are right, '
                . 'but every invoice it should have settled is still being chased.',
        'why' => 'Money arriving and deciding what it settles are deliberately two separate acts — a customer '
               . 'sends one payment against four invoices, and sometimes sends one nobody can place yet. The '
               . 'second act is easy to forget because the first one already felt like the job.',
        'steps' => [
            'Open <b>Money in → Not yet matched</b>.',
            'Take the largest. The screen lists that customer\'s open invoices with what each still owes.',
            'Type the cash against each invoice it settles. One receipt can be spread across several.',
            'If the customer deducted TDS, put it in the <b>TDS</b> column against the same invoice. It settles '
              . 'the invoice exactly as cash does — leaving it out is the single most common reason an ageing '
              . 'report chases somebody who has paid in full.',
            'Press <b>Match it</b>. Nothing can be matched beyond what arrived or beyond what an invoice owes — '
              . 'both are refused rather than rounded away, so you cannot make the ledger wrong from here.',
            'Anything left over stays on the ledger as a credit, which is correct — match it when the invoice is raised.',
        ],
        'stop' => 'Recording a receipt now ends on the matching screen rather than the register, so the second act '
                . 'is in front of you while you still remember what the payment was for.',
        'who' => 'Accounts',
        'link' => '/receipts?f=unallocated', 'link_label' => 'Open unmatched money',
        'rows' => array_slice($rows, 0, 12), 'row_url' => '/receipt?id=',
    ];
}

function adv_won_not_ordered() {
    if (!adv_on('leads') || !adv_on('calls') || !function_exists('opp_migrate')) return null;
    opp_migrate();
    [$w, $a] = scope_office_clause('o.office_id');
    $rows = adv_rows("SELECT o.id, o.ref, o.name who, o.won_at d, o.value amount
                      FROM opportunities o
                      WHERE $w AND o.status='WON' AND (o.call_id IS NULL OR o.call_id=0)
                      ORDER BY o.value DESC LIMIT 200", $a);
    if (!$rows) return null;
    $total = 0.0; foreach ($rows as $r) $total += (float)$r['amount'];
    return [
        'key' => 'won_no_order', 'severity' => 'SPEED',
        'title' => 'Deals won where nobody raised the order',
        'n' => count($rows), 'value' => $total,
        'cost' => fmoney($total) . ' of business is won and has not started. The customer is waiting and does not know why.',
        'why' => 'Winning a deal and raising the order used to be two unconnected acts in two different screens. '
               . 'Sales marked it won and moved on; operations never heard.',
        'steps' => [
            'Open the deal — the list here is ordered by value.',
            'Press <b>Raise the order</b>. It carries the customer, the accepted quotation, its value and the branch '
              . 'straight across, so nothing is typed twice and the rate on the order is the rate that was agreed.',
            'Check which quotation it says it will carry. If it says <b>not marked accepted</b>, confirm with the '
              . 'owner that it is the right one before pressing — that is the moment a wrong rate reaches a job.',
            'Set the executing branch and, if the customer gave one, the contract number.',
            'The order lands in the register as normal; allocate the work from there.',
        ],
        'stop' => 'The deal now records its order, so it cannot be raised twice, and this list empties itself as '
                . 'each one is done.',
        'who' => 'Whoever owns the deal, then a coordinator to allocate',
        'link' => '/opportunities', 'link_label' => 'Open the pipeline',
        'rows' => array_slice($rows, 0, 12), 'row_url' => '/opportunity?id=',
    ];
}

function adv_stalled_deals() {
    if (!adv_on('leads') || !function_exists('opp_all')) return null;
    $all = adv_try(fn() => opp_all(['open' => 1]), []) ?: [];
    $rows = []; $total = 0.0; $weighted = 0.0;
    foreach ($all as $o) {
        if (!opp_stalled($o)) continue;
        $rows[] = ['id' => $o['id'], 'ref' => $o['ref'], 'who' => $o['name'],
                   'd' => $o['stage_since'], 'amount' => (float)$o['value'],
                   'extra' => opp_days_in_stage($o) . ' days in ' . ($o['stage_name'] ?: 'its stage')];
        $total += (float)$o['value']; $weighted += opp_weighted($o);
    }
    if (!$rows) return null;
    usort($rows, fn($x, $y) => $y['amount'] <=> $x['amount']);
    return [
        'key' => 'stalled', 'severity' => 'SPEED',
        'title' => 'Deals that have stopped moving',
        'n' => count($rows), 'value' => $total,
        'cost' => fmoney($total) . ' of pipeline (' . fmoney($weighted) . ' weighted) has sat longer than its stage allows.',
        'why' => 'Every stage carries a number of days it is expected to take, set on the funnel. A deal past that '
               . 'is not necessarily lost, but nobody has touched it and nobody has decided.',
        'steps' => [
            'Open the biggest. Read the last activity on it — if there is none, that is the finding.',
            'Decide one of three things, and record it: it is still live (move it on, or note the next action and a date); '
              . 'it is dead (mark it lost, <b>with the reason</b>); or the stage service level is wrong for your business.',
            'If several deals are stuck at the same stage, it is usually the third. Open <b>Pipelines &amp; funnels</b>, '
              . 'find that stage and change the days allowed — a funnel that cries wolf gets ignored entirely.',
            'Marking it lost is not a failure to avoid. A deal recorded as lost with a reason feeds the "why we lose" '
              . 'report; a deal left open for ever feeds nothing and inflates the forecast.',
        ],
        'stop' => 'Nothing automatic — this is the list a sales manager works through weekly. The point is that it '
                . 'is a list of five, not a pipeline of two hundred to read.',
        'who' => 'The deal owner, reviewed by the sales manager',
        'link' => '/crm-dashboard', 'link_label' => 'Open the sales dashboard',
        'rows' => array_slice($rows, 0, 12), 'row_url' => '/opportunity?id=',
    ];
}

function adv_order_no_quote() {
    if (!adv_on('calls')) return null;
    [$w, $a] = scope_clause('c.executing_office_id', 'c.sbu');
    $rows = adv_rows("SELECT c.id, c.call_code ref, c.call_received_date d,
                             COALESCE(bp.display_name,bp.legal_name) who,
                             COALESCE(c.billable_value,0) amount
                      FROM calls c LEFT JOIN business_partners bp ON bp.id = c.client_id
                      WHERE $w AND (c.quotation_id IS NULL OR c.quotation_id = 0)
                      ORDER BY c.id DESC LIMIT 300", $a);
    if (!$rows) return null;
    return [
        'key' => 'no_quote', 'severity' => 'RISK',
        'title' => 'Work ordered against no quotation',
        'n' => count($rows), 'value' => 0.0,
        'cost' => count($rows) . ' orders carry no link to what was quoted. Nobody can check the rate that was '
                . 'agreed, so the invoice is argued about after the work is done rather than before.',
        'why' => 'The field has always existed on the order form and nothing ever required it, so it was skipped '
               . 'every time. A field that is never shown and never required is a field that does not exist.',
        'steps' => [
            'You do not have to clear the history. Start with anything <b>still open or unbilled</b> — those are '
              . 'the ones where the rate still matters.',
            'Open the order and press <b>Edit</b>. Choose the quotation it came from; the contract number fills in from it.',
            'If it was a direct order under a running contract with no quotation, type the <b>contract number</b> instead. '
              . 'That is a legitimate answer and the screen accepts it.',
            'For anything closed and paid, leave it. Back-filling a link on settled work is data entry with no benefit.',
            'Going forward: the order screen now says so when no quotation is linked, and a deal won in the pipeline '
              . 'raises the order <b>with</b> its quotation already attached — which is how this stops happening.',
        ],
        'stop' => 'Raising the order from a won deal carries the quotation automatically, so orders created that way '
                . 'can never appear on this list.',
        'who' => 'Coordinators, for open work only',
        'link' => '/flow-gaps', 'link_label' => 'See them all',
        'rows' => array_slice($rows, 0, 12), 'row_url' => '/call?id=',
    ];
}

function adv_closed_no_report() {
    if (!adv_on('jobs') || !adv_on('idems')) return null;
    [$w, $a] = scope_clause('j.executing_office_id', 'j.sbu');
    $rows = adv_rows("SELECT j.id, j.job_code ref, j.closed_at d, i.name who, 0 amount
                      FROM jobs j LEFT JOIN inspectors i ON i.id = j.inspector_id
                      WHERE $w AND j.closed_flag = 1
                        AND NOT EXISTS (SELECT 1 FROM report_docs d WHERE d.job_id = j.id AND d.deleted = 0)
                      ORDER BY j.closed_at DESC LIMIT 300", $a);
    if (!$rows) return null;
    return [
        'key' => 'no_report', 'severity' => 'RISK',
        'title' => 'Work closed with no report on file',
        'n' => count($rows), 'value' => 0.0,
        'cost' => count($rows) . ' closed deputations have no report linked. What the customer was actually sent '
                . 'is not in the system, so it cannot be produced in a dispute or an assessment.',
        'why' => 'Reports were often written outside the system and e-mailed. The deputation was closed on the '
               . 'strength of the work being done, not on the report existing.',
        'steps' => [
            'Sort by most recent — those are the ones whose report still exists somewhere findable.',
            'Open the deputation. If the report was raised in the system but never linked, link it.',
            'If it was written outside the system, raise the report against the deputation and attach the file. '
              . 'That is worth doing for anything in the current financial year.',
            'For older work, decide a cut-off and stop. Reconstructing three years of reports is not a project '
              . 'anybody finishes; being able to produce this year\'s is what an assessor asks for.',
            'Going forward: a deputation closing without a report should be the exception, and this list is how '
              . 'you see whether it is.',
        ],
        'stop' => 'Nothing automatic yet. Making the report a condition of closing is a change I would make only '
                . 'on your instruction — it would stop coordinators closing work on a Friday evening.',
        'who' => 'The engineer who did the work, chased by the coordinator',
        'link' => '/flow-gaps', 'link_label' => 'See them all',
        'rows' => array_slice($rows, 0, 12), 'row_url' => '/job?id=',
    ];
}

function adv_silent_customers() {
    if (!function_exists('act_silent_customers') || !adv_on('clients')) return null;
    $silent = adv_try(fn() => act_silent_customers(90, 200), []) ?: [];
    if (!$silent) return null;
    // Only the ones worth caring about: they have spent money or owe some.
    $rows = []; $value = 0.0;
    foreach ($silent as $s) {
        $out = function_exists('books_outstanding') ? adv_try(fn() => books_outstanding((int)$s['id']), null) : null;
        $billed = $out ? (float)$out['billed'] : 0.0;
        $owing = $out ? (float)$out['outstanding'] : 0.0;
        if ($billed <= 0 && $owing <= 0) continue;
        $rows[] = ['id' => $s['id'], 'ref' => $s['display_name'] ?: $s['legal_name'],
                   'who' => $owing > 0 ? fmoney($owing) . ' outstanding' : 'no balance',
                   'd' => substr((string)$s['last_touch'], 0, 10), 'amount' => $owing];
        $value += $owing;
    }
    if (!$rows) return null;
    usort($rows, fn($x, $y) => $y['amount'] <=> $x['amount']);
    return [
        'key' => 'silent', 'severity' => 'RISK',
        'title' => 'Paying customers nobody has spoken to in 90 days',
        'n' => count($rows), 'value' => $value,
        'cost' => count($rows) . ' customers who have bought from you have had no contact of any kind for three '
                . 'months' . ($value > 0 ? ', and ' . fmoney($value) . ' of it is outstanding' : '') . '.',
        'why' => 'Nobody decided to stop calling them. Accounts move to whoever is shouting, and a quiet customer '
               . 'is quiet right up until they are somebody else\'s.',
        'steps' => [
            'Open <b>Customer 360</b> for the first one. It shows what they owe, what is in flight, what they have '
              . 'complained about, and when anybody last spoke to them — everything you need before dialling.',
            'Check the quality section first. A customer who complained and heard nothing back is not a sales call, '
              . 'it is an apology.',
            'Ring the named contact. Then <b>record the call</b> on the customer — one line is enough. That is what '
              . 'takes them off this list and tells the next person it was done.',
            'If there is nothing to sell them right now, open an <b>opportunity</b> with an expected close date '
              . 'anyway. A deal with a date comes back to you; a good intention does not.',
            'Work down the list by outstanding value — a customer who owes you money and has gone quiet is the one '
              . 'to ring today.',
        ],
        'stop' => 'The timeline fills itself from quotations, reports and invoices, so a customer with real activity '
                . 'never appears here. Only genuine silence does.',
        'who' => 'The account owner',
        'link' => '/activities', 'link_label' => 'Open the timeline',
        'rows' => array_slice($rows, 0, 12), 'row_url' => '/customer?id=',
    ];
}

function adv_customers_untaxable() {
    if (!adv_on('invoicing') || !adv_on('clients')) return null;
    // A customer with neither GSTIN nor state cannot be invoiced at all — the
    // issue gate refuses, because IGST against CGST+SGST would be a guess.
    $rows = adv_rows(
        "SELECT bp.id, COALESCE(bp.display_name, bp.legal_name) ref, bp.code who, '' d, 0 amount
         FROM business_partners bp
         WHERE bp.is_client = 1 AND bp.status = 'ACTIVE'
           AND (bp.gstin IS NULL OR bp.gstin = '') AND (bp.state IS NULL OR bp.state = '')
           AND EXISTS (SELECT 1 FROM calls c WHERE c.client_id = bp.id)
         ORDER BY COALESCE(bp.display_name, bp.legal_name) LIMIT 200");
    if (!$rows) return null;
    return [
        'key' => 'untaxable', 'severity' => 'DATA',
        'title' => 'Customers you have worked for who cannot be invoiced',
        'n' => count($rows), 'value' => 0.0,
        'cost' => count($rows) . ' active customers with work on file have neither a GSTIN nor a state. An invoice '
                . 'to them will be <b>refused at the point of issue</b>, because whether the tax is IGST or CGST+SGST '
                . 'cannot be decided without one of the two.',
        'why' => 'A customer created in a hurry from an order form gets a name and nothing else. It causes no '
               . 'trouble at all until the day somebody tries to bill them, which is usually the day it is urgent.',
        'steps' => [
            'Open the customer and press <b>Edit</b>.',
            'Enter the <b>GSTIN</b> if they have one — it is the reliable answer, because its first two digits are '
              . 'the state and the tax follows from that.',
            'If they are unregistered, set the <b>state</b> instead. That is enough for the invoice to be issued.',
            'Fix these <b>before</b> the month end, not during it. The refusal happens at issue, so an unfixed one '
              . 'turns into a phone call to the customer\'s accounts department at 6pm on the 31st.',
            'While you are there, check there is a named contact with an e-mail. A customer with no contact is a '
              . 'customer nobody can chase.',
        ],
        'stop' => 'The invoice screen lists this as a reason it cannot be issued, so it is caught at the desk rather '
                . 'than discovered by the customer.',
        'who' => 'Whoever owns the customer master — usually accounts or the coordinator who created them',
        'link' => '/clients', 'link_label' => 'Open the customer list',
        'rows' => array_slice($rows, 0, 12), 'row_url' => '/customer?id=',
    ];
}

function adv_draft_invoices() {
    if (!adv_on('invoicing') || !function_exists('books_migrate')) return null;
    books_migrate();
    [$w, $a] = scope_clause('i.office_id', 'i.sbu');
    $cut = date('c', strtotime('-7 days'));
    $rows = adv_rows("SELECT i.id, ('draft #' || i.id) ref, i.partner_name who, i.created_at d, i.total amount
                      FROM invoices i WHERE $w AND i.status='DRAFT' AND i.created_at < ?
                      ORDER BY i.total DESC LIMIT 100", array_merge($a, [$cut]));
    if (!$rows) return null;
    // An empty draft and a fully-priced one that was never issued are different
    // problems, and "₹0 is sitting in draft" would be a true sentence that says
    // nothing. Count them separately and describe whichever is actually there.
    $total = 0.0; $empty = 0;
    foreach ($rows as $r) { $total += (float)$r['amount']; if ((float)$r['amount'] <= 0) $empty++; }
    $valued = count($rows) - $empty;
    $cost = $valued
        ? fmoney($total) . ' is sitting in draft across ' . $valued . ' ' . ($valued === 1 ? 'invoice' : 'invoices')
          . ($empty ? ', and a further ' . $empty . ' ' . ($empty === 1 ? 'draft has' : 'drafts have') . ' no lines on it at all' : '')
          . '. A draft is not in the ledger, not in the ageing, and not in anybody\'s cash forecast — as far as the '
          . 'books are concerned it does not exist.'
        : $empty . ' ' . ($empty === 1 ? 'draft was' : 'drafts were') . ' started and never given a single line. '
          . 'Either somebody was interrupted before adding the work, or the draft is a duplicate that should be cleared.';
    return [
        'key' => 'drafts', 'severity' => 'CASH',
        'title' => 'Invoices drafted more than a week ago and never issued',
        'n' => count($rows), 'value' => $total,
        'cost' => $cost,
        'why' => 'Somebody drafted it, hit something that stopped them issuing, and moved on. The invoice screen '
               . 'lists exactly what is blocking it, but nothing brings the draft back to their attention.',
        'steps' => [
            'Open the draft. The panel at the top lists precisely what is stopping it — no lines, no date, no branch, '
              . 'or a customer whose place of supply is unknown.',
            'Fix those. The commonest by far is the customer having no GSTIN and no state.',
            'Press <b>Issue</b>. The number is allocated only at that moment, which is why an abandoned draft leaves '
              . 'no gap in the series.',
            'If the draft should not exist — a duplicate, or work that was cancelled — there is no harm in leaving it: '
              . 'a draft has burned no number. But clear it so this list stays meaningful.',
        ],
        'stop' => 'Nothing automatic. This list is the reminder.',
        'who' => 'Accounts',
        'link' => '/invoices?f=draft', 'link_label' => 'Open the drafts',
        'rows' => array_slice($rows, 0, 12), 'row_url' => '/invoice?id=',
    ];
}


// Advertising money with nothing to show for it. Only exists once Ads Pro is
// connected — a company that does not advertise must never be told off about
// campaigns it does not run.
function adv_ad_spend_dry() {
    if (!function_exists('ads_on') || !ads_on() || !function_exists('roi_by_campaign')) return null;
    if (!adv_on('leads')) return null;
    $rows = []; $wasted = 0.0; $noLead = 0; $uncollected = 0.0;
    foreach (adv_try(fn() => roi_by_campaign(), []) ?: [] as $c) {
        if ((float)$c['spend'] <= 0) continue;
        // Two different failures, and they need different answers. A campaign
        // producing no leads at all is an advertising problem. A campaign
        // producing leads nobody followed up is a sales problem, and blaming the
        // agency for it is how the wrong thing gets cancelled.
        $dry  = (int)$c['leads'] === 0;
        $cold = !$dry && (int)$c['won'] === 0 && (float)$c['in_play'] <= 0;
        if (!$dry && !$cold) { $uncollected += (float)$c['uncollected']; continue; }
        $wasted += (float)$c['spend'];
        if ($dry) $noLead++;
        $rows[] = ['id' => 0, 'ref' => $c['name'], 'd' => '',
                   'who' => $dry ? 'No leads at all' : (int)$c['leads'] . ' leads, none still alive',
                   'amount' => (float)$c['spend']];
    }
    if (!$rows) return null;
    usort($rows, fn($a, $b) => $b['amount'] <=> $a['amount']);
    return [
        'key' => 'adspend', 'severity' => 'CASH',
        'title' => 'Advertising you are paying for that has produced nothing',
        'n' => count($rows), 'value' => $wasted,
        'cost' => fmoney($wasted) . ' of advertising has produced no live opportunity — '
                . ($noLead ? $noLead . ' ' . ($noLead === 1 ? 'campaign' : 'campaigns') . ' produced no lead at all'
                           : 'every one of them produced leads that then went nowhere') . '.',
        'why' => 'Ad platforms report cost per lead, and by that measure most of these look acceptable. '
               . 'Nothing has ever joined the spend to what the leads actually became, so a campaign can run for '
               . 'months on a good cost per lead while producing no business whatsoever.',
        'steps' => [
            'Open <b>What the advertising actually earned</b> and sort by what was collected, not by what was spent.',
            'Split the list in two, because they are different faults with different owners. '
              . '<b>No leads at all</b> is the agency\'s problem — wrong targeting, wrong creative, wrong offer. '
              . '<b>Leads that went nowhere</b> is ours — nobody rang them.',
            'For the second kind, open the leads and check whether anybody ever made contact. If the answer is no, '
              . 'the campaign is not the thing to cancel.',
            'Pause the genuinely dry ones in Ads Pro. There is no button here that does it — this system reports, '
              . 'it does not spend your advertising budget.',
            'Switch on <b>tell Ads Pro when money is received</b> on the connection screen. Its own optimisation then '
              . 'steers on cash collected instead of a figure estimated from a tracking pixel, and this list gets '
              . 'shorter by itself.',
        ],
        'stop' => ($uncollected > 0
            ? 'Separately, ' . fmoney($uncollected) . ' has been invoiced from advertising leads and not yet received. '
              . 'Collecting it raises the return on this spend without spending anything more.'
            : 'The return report re-checks every time it is opened, so a campaign turning dry shows up in the same week rather than at the year end.'),
        'who' => 'Whoever signs off the advertising budget, with sales for the second kind',
        'link' => '/ads-roi', 'link_label' => 'Open the advertising return',
        'rows' => array_slice($rows, 0, 12), 'row_url' => '',
    ];
}

// ---- Assembly ---------------------------------------------------------------
function adv_all() {
    $checks = ['adv_unbilled_work', 'adv_overdue_invoices', 'adv_unmatched_receipts',
               'adv_won_not_ordered', 'adv_draft_invoices', 'adv_stalled_deals',
               'adv_silent_customers', 'adv_order_no_quote', 'adv_closed_no_report',
               'adv_customers_untaxable', 'adv_ad_spend_dry'];
    $out = [];
    foreach ($checks as $fn) {
        if (!function_exists($fn)) continue;
        $r = adv_try(fn() => $fn(), null);
        if ($r) $out[] = $r;
    }
    // Money first, biggest first. A list ordered by count says twelve small
    // invoices matter more than one large one, which is the wrong instruction.
    usort($out, function ($a, $b) {
        $rank = ['CASH' => 0, 'SPEED' => 1, 'RISK' => 2, 'DATA' => 3];
        $c = ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9);
        return $c !== 0 ? $c : ($b['value'] <=> $a['value']);
    });
    return $out;
}

// What this install was actually able to look at. The all-clear message has to
// be built from this rather than written once, because on a Sales-only licence
// "every invoice is settled" would be a confident statement about a module that
// is not installed — the fastest way to lose a customer's trust in a number.
function adv_checked() {
    $said = [];
    if (adv_on('jobs') && adv_on('invoicing')) $said[] = 'every finished job has been invoiced';
    if (adv_on('invoicing')) {
        $said[] = 'no invoice is past its date';
        $said[] = 'every payment received is matched to an invoice';
    }
    if (adv_on('leads')) {
        $said[] = 'no deal has gone quiet';
        if (adv_on('calls')) $said[] = 'every won deal has become an order';
    }
    if (adv_on('calls') && adv_on('quotes')) $said[] = 'every order names what was quoted';
    if (adv_on('jobs') && adv_on('idems')) $said[] = 'every closed job has a report on file';
    return $said;
}

function ops_advisor($route, $method) {
    ops_require(adv_can(), 'You cannot open the advisor.');
    $found = adv_all();
    $cash = 0.0;
    foreach ($found as $f) if ($f['severity'] === 'CASH') $cash += (float)$f['value'];

    if (wants_csv()) {
        $csv = [['Finding', 'Type', 'Count', 'Value at stake', 'Who should act', 'Reference', 'Date', 'Detail']];
        foreach ($found as $f)
            foreach ($f['rows'] as $r)
                $csv[] = [$f['title'], ADV_SEVERITY[$f['severity']] ?? $f['severity'], $f['n'], $f['value'],
                          $f['who'], $r['ref'] ?? '', $r['d'] ?? '', $r['who'] ?? ''];
        csv_download('what-to-fix-' . date('Y-m-d') . '.csv', $csv);
    }

    view('ops/advisor', ['found' => $found, 'cash' => $cash, 'sev' => ADV_SEVERITY,
                         'checked' => adv_checked(), 'open' => (string)($_GET['open'] ?? '')]);
    return true;
}
