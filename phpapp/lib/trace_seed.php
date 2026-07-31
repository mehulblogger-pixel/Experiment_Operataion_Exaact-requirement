<?php
// ============================================================================
//  Golden-thread traceability seed
//
//  Builds ONE record and walks it the whole way through the business —
//  lead → contact → deal → quotation → accept → contract → work-order → job →
//  site check-in → report → invoice → money-in — and then CHECKS that every
//  link was recorded at every place. It is the answer to "does the data really
//  flow start to end, and is it traceable at each step?".
//
//  It drives the REAL library functions wherever they exist (lead_create,
//  opp_create, opp_move, opp_raise_order, site_checkin, books_*), so the same
//  propagation the app runs for a live user runs here too. The few steps that
//  live only inside a form handler (the quotation header, the job, the report)
//  are inserted the same way that handler would, and then everything is
//  verified by reading the database back — the check is what proves the flow,
//  no matter how each row was written.
//
//  Every row it writes is anchored on one customer (code GT-CLIENT) and one
//  vendor (GT-VENDOR), so trace_seed_remove() can take the whole thread out
//  again by walking from those two, touching nothing real.
// ============================================================================

const TRACE_CLIENT_CODE = 'GT-CLIENT';
const TRACE_VENDOR_CODE = 'GT-VENDOR';
const TRACE_CONTRACT     = 'GT-2026-CONTRACT-001';

function trace_seeded() { return setting_get('trace_seeded', '') === '1'; }

// Act as the Master Admin for the run, so every real function's permission
// checks pass exactly as they would for a signed-in administrator.
function trace_become_admin() {
    $u = ops_one("SELECT id FROM users WHERE is_superuser=1 OR role='MASTER_ADMIN' ORDER BY id LIMIT 1");
    if (!$u) $u = ops_one("SELECT id FROM users ORDER BY id LIMIT 1");
    if ($u) $_SESSION['uid'] = (int)$u['id'];
    return $u ? (int)$u['id'] : 0;
}

function trace_client_id() {
    return (int) ops_val("SELECT id FROM business_partners WHERE code=?", [TRACE_CLIENT_CODE]);
}

// ----------------------------------------------------------------------------
//  Build the thread. Returns ['ok'=>bool, 'ids'=>[...], 'steps'=>[...], 'checks'=>[...]].
// ----------------------------------------------------------------------------
function trace_seed($force = false) {
    if (trace_seeded() && !$force) return ['skipped' => true];
    if (trace_seeded() && $force) trace_seed_remove();

    trace_become_admin();
    $pdo = db();
    $now = date('c'); $today = date('Y-m-d');
    $steps = []; $ids = [];
    $step = function ($title, $ref, $detail) use (&$steps) { $steps[] = ['title' => $title, 'ref' => $ref, 'detail' => $detail]; };

    // ---- 1. Client master + vendor master --------------------------------
    $insP = $pdo->prepare("INSERT INTO business_partners(code,legal_name,display_name,is_client,is_vendor,status,state,gstin,created_at)
                           VALUES(?,?,?,?,?, 'ACTIVE', ?,?,?)");
    $insP->execute([TRACE_CLIENT_CODE, 'Golden Thread Traders Pvt Ltd', 'Golden Thread Traders', 1, 0, 'Gujarat', '24AAGTT1234G1Z9', $now]);
    $clientId = (int)$pdo->lastInsertId();
    $insP->execute([TRACE_VENDOR_CODE, 'Golden Thread Fabrication Works', 'Golden Thread Fab', 0, 1, 'Gujarat', '24AAGTF5678G1Z1', $now]);
    $vendorId = (int)$pdo->lastInsertId();
    $ids['client'] = $clientId; $ids['vendor'] = $vendorId;
    // A contact and a site, so the engineer's screen has somebody to ring and
    // somewhere to go — the same things a real customer needs on file.
    try {
        $pdo->prepare("INSERT INTO partner_contacts(partner_id,name,designation,email,mobile,is_primary) VALUES(?,?,?,?,?,1)")
            ->execute([$clientId, 'Rakesh Menon', 'Purchase Head', 'rakesh.menon@example.com', '9825000001']);
        $pdo->prepare("INSERT INTO partner_addresses(partner_id,address_type,label,line1,city,state,pincode,country,is_primary)
                       VALUES(?,'WORKS','Works','Plot 7, GIDC Estate','Vapi','Gujarat','396195','India',1)")->execute([$clientId]);
    } catch (Throwable $e) { /* older contact/address shape */ }
    $step('Customer put on the master', TRACE_CLIENT_CODE, 'Golden Thread Traders Pvt Ltd — plus a vendor, ' . TRACE_VENDOR_CODE . '.');

    // ---- 2. Lead ---------------------------------------------------------
    $lead = lead_create([
        'partner_id'   => $clientId,           // chosen from the client master
        'contact_name' => 'Rakesh Menon', 'contact_email' => 'rakesh.menon@example.com', 'contact_phone' => '9825000001',
        'source'       => 'REFERRAL',
        'requirement'  => 'Third-party inspection of 4 pressure vessels before dispatch',
        'value'        => 100000, 'sbu' => 'IND',
        'next_action'  => 'Send a quotation', 'next_action_on' => $today,
    ]);
    if (empty($lead['id'])) return ['ok' => false, 'error' => 'Lead step failed: ' . ($lead['err'] ?? '?'), 'steps' => $steps];
    $leadId = (int)$lead['id']; $ids['lead'] = $leadId;
    $step('Lead raised', $lead['ref'], 'Company taken from the customer master, so the lead points at the real customer.');

    // ---- 3. Log a contact on the lead -----------------------------------
    lead_log_contact($leadId, [
        'method' => 'CALL', 'direction' => 'OUT', 'with_whom' => 'Rakesh Menon',
        'note' => 'Discussed scope — 4 vessels, dispatch in three weeks. Sending a quote.',
        'time_spent' => 45, 'outcome' => 'POSITIVE',
        'next_action' => 'Send a quotation', 'next_action_on' => $today,
    ]);
    $step('Contact logged on the lead', $lead['ref'], 'A 45-minute call recorded — this is the raw material of the effort-to-win figure.');

    // ---- 4. Deal (opportunity) opened from the lead ----------------------
    $opp = opp_create([
        'name'       => 'Golden Thread — pre-dispatch vessel inspection',
        'partner_id' => $clientId, 'lead_id' => $leadId,
        'value'      => 100000, 'probability' => 60, 'sbu' => 'IND',
        'requirement'=> 'Third-party inspection of 4 pressure vessels before dispatch',
    ]);
    if (empty($opp['id'])) return ['ok' => false, 'error' => 'Deal step failed: ' . ($opp['err'] ?? '?'), 'steps' => $steps];
    $oppId = (int)$opp['id']; $ids['opp'] = $oppId;
    $step('Deal opened from the lead', $opp['ref'], 'Carries the lead and the customer, so the pipeline knows where it came from.');

    // ---- 5. Quotation (header inserted as the form handler does; lines and
    //         totals via the real crm_save_lines + crm_quote_recalc) --------
    $qno = crm_next_quote_no();
    $hdr = [
        'client_id' => $clientId, 'client_name' => 'Golden Thread Traders',
        'contact_name' => 'Rakesh Menon', 'contact_email' => 'rakesh.menon@example.com', 'contact_mobile' => '9825000001',
        'sbu' => 'IND', 'office_id' => null, 'subject' => 'Third-party inspection of 4 pressure vessels before dispatch',
        'currency' => 'INR', 'validity_days' => 30, 'gst_pct' => 18,
    ];
    $cols = array_merge(['quote_no','rev','is_current','inquiry_id','lead_id','status','owner_id','created_by','created_at'], array_keys($hdr));
    $vals = array_merge([$qno, 0, 1, null, $leadId, 'DRAFT', $_SESSION['uid'] ?? null, 'trace', $now], array_values($hdr));
    $pdo->prepare("INSERT INTO quotations (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute($vals);
    $quoteId = (int)$pdo->lastInsertId(); $ids['quote'] = $quoteId;
    crm_save_lines($quoteId, [
        'l_desc'    => ['Third-party inspection of pressure vessels (4 nos), pre-dispatch, incl. report'],
        'l_service' => ['INSPECTION'], 'l_sbu' => ['IND'],
        'l_qty'     => [1], 'l_unit' => ['LOT'], 'l_rate' => [100000],
    ]);
    crm_quote_recalc($quoteId);
    $step('Quotation made from the lead', $qno, '1 line, ₹1,00,000 + 18% GST = ₹1,18,000. Linked to the lead.');

    // ---- 6. Accept the quotation — the cross-object sync ------------------
    //  Mirrors the accept path in the quote-status handler (lib/crm.php): land
    //  the customer, attach the quote to the deal, carry the customer onto the
    //  deal, and win it in the pipeline. All the real work is the real helpers.
    $q = crm_quote_get($quoteId);
    $pdo->prepare("UPDATE quotations SET status='ACCEPTED', accepted_date=? WHERE id=?")->execute([$today, $quoteId]);
    $res = function_exists('quote_land_on_client') ? quote_land_on_client($q) : ['client_id' => $clientId];
    $dealWon = false;
    if (function_exists('opp_move') && function_exists('opp_row')) {
        $oid = function_exists('quote_linked_deal_id') ? quote_linked_deal_id($q) : $oppId;
        if (!$oid) $oid = $oppId;
        if (!(int) ops_val("SELECT COUNT(*) FROM opportunity_quotes WHERE opportunity_id=? AND quotation_id=?", [$oid, $quoteId]))
            $pdo->prepare("INSERT INTO opportunity_quotes (opportunity_id, quotation_id) VALUES (?,?)")->execute([$oid, $quoteId]);
        $o = opp_row($oid);
        if ($o && empty($o['partner_id']) && !empty($res['client_id'])) {
            $pn = (string) ops_val("SELECT COALESCE(display_name, legal_name) FROM business_partners WHERE id=?", [(int)$res['client_id']]);
            $pdo->prepare("UPDATE opportunities SET partner_id=?, partner_name=? WHERE id=?")->execute([(int)$res['client_id'], $pn, $oid]);
        }
        if ($o && ($o['status'] ?? '') === 'OPEN') {
            $wonStage = 0;
            foreach (pipeline_stages((int)$o['pipeline_id']) as $st) if (($st['kind'] ?? '') === 'WON') { $wonStage = (int)$st['id']; break; }
            if ($wonStage) { $rw = opp_move($oid, $wonStage, [], true); $dealWon = !empty($rw['ok']); }
        }
    }
    $step('Quotation accepted', $qno, 'Attached to the deal, and the deal is now ' . ($dealWon ? 'won' : 'closed') . ' in the pipeline.');

    // ---- 7. Convert the lead to a customer (status flows back) ------------
    if (($ln = lead_convert($leadId, ['partner_id' => $clientId])) && empty($ln['err']))
        $step('Lead converted to a customer', $lead['ref'], 'The lead now reads Converted and points at the customer on the master.');

    // ---- 8. Register the contract number on the quotation ----------------
    $contractId = null;
    try {
        $pdo->prepare("INSERT INTO partner_contracts(partner_id,contract_number,title,value,start_date,status,created_by,created_at)
                       VALUES(?,?,?,?,?, 'ACTIVE', 'trace', ?)")
            ->execute([$clientId, TRACE_CONTRACT, 'Pre-dispatch vessel inspection', 118000, $today, $now]);
        $contractId = (int)$pdo->lastInsertId();
    } catch (Throwable $e) { /* partner_contracts not built here */ }
    $pdo->prepare("UPDATE quotations SET contract_number=?" . ($contractId ? ", contract_id=?" : "") . " WHERE id=?")
        ->execute($contractId ? [TRACE_CONTRACT, $contractId, $quoteId] : [TRACE_CONTRACT, $quoteId]);
    $ids['contract'] = $contractId;
    $step('Contract number registered', TRACE_CONTRACT, 'Entered once on the accepted quotation — it now flows to the work-order and the job.');

    // ---- 9. Work-order (call) raised from the won deal -------------------
    $ord = opp_raise_order($oppId, ['contract_number' => TRACE_CONTRACT, 'inspection_required_date' => date('Y-m-d', strtotime('+14 days'))]);
    if (empty($ord['call_id'])) return ['ok' => false, 'error' => 'Work-order step failed: ' . ($ord['err'] ?? '?'), 'ids' => $ids, 'steps' => $steps];
    $callId = (int)$ord['call_id']; $ids['call'] = $callId;
    // Fill the operating details the order needs but the deal did not carry.
    $insp = ops_one("SELECT id FROM inspectors ORDER BY id LIMIT 1");
    $office = ops_one("SELECT id FROM offices ORDER BY id LIMIT 1");
    $pdo->prepare("UPDATE calls SET vendor_id=?, sbu='IND', activity_id=NULL, inspection_type='TPI', executing_office_id=? WHERE id=?")
        ->execute([$vendorId, (int)($office['id'] ?? 0) ?: null, $callId]);
    $step('Work-order raised from the deal', $ord['code'], 'Carries the quotation and the contract number straight from the accepted quote.');

    // ---- 10. Job allocated from the work-order ---------------------------
    $jcode = ops_next_code('jobs', 'job_code', 'JOB');
    $pdo->prepare("INSERT INTO jobs(job_code,call_id,quotation_id,contract_number,inspector_id,executing_office_id,sbu,
                    job_type,stage,scheduled_date,inspection_type,expected_credit,reporting_frequency,deliverables,created_by,created_at)
                   VALUES(?,?,?,?,?,?,?, 'INSPECTION','ALLOCATED',?, 'TPI', ?, 'PER_VISIT', 'DIR', 'trace', ?)")
        ->execute([$jcode, $callId, $quoteId, TRACE_CONTRACT, (int)($insp['id'] ?? 0) ?: null,
                   (int)($office['id'] ?? 0) ?: null, 'IND', date('Y-m-d', strtotime('+7 days')), 100000, $now]);
    $jobId = (int)$pdo->lastInsertId(); $ids['job'] = $jobId;
    $pdo->prepare("UPDATE calls SET status='ALLOCATED', allocated_at=? WHERE id=?")->execute([$now, $callId]);
    $step('Job allocated from the work-order', $jcode, 'Points back at the work-order, the quotation and the contract number.');

    // ---- 11. Site check-in on the job (real function) --------------------
    if (function_exists('site_checkin')) {
        try {
            site_checkin($jobId, ['kind' => 'ENTRY', 'gps' => '20.3712,72.9034,11', 'device_at' => $now,
                                  'note' => 'Arrived at Gate 2, met QA (Rakesh Menon).'], null);
            $step('Site check-in recorded', $jcode, 'The engineer checked in on site — a GPS-stamped visit against the job.');
        } catch (Throwable $e) { /* check-in schema differs */ }
    }

    // ---- 12. Report issued on the job ------------------------------------
    $rt = ops_one("SELECT id, code, name FROM report_types ORDER BY id LIMIT 1");
    $irn = 'GT/AMD/2026/GTT/' . ($rt['code'] ?? 'DIR') . '/TRACE';
    try {
        $pdo->prepare("INSERT INTO report_docs(irn,report_type_id,type_code,title,job_id,call_id,client_id,vendor_id,
                        inspector_id,office_id,status,finalized,inspection_date,issue_date,result,release_status,created_by,created_at)
                       VALUES(?,?,?,?,?,?,?,?,?,?, 'ISSUED', 1, ?,?, 'PASS','RELEASED','trace',?)")
            ->execute([$irn, (int)($rt['id'] ?? 0) ?: null, $rt['code'] ?? 'DIR',
                       'Pre-dispatch inspection — 4 vessels', $jobId, $callId, $clientId, $vendorId,
                       (int)($insp['id'] ?? 0) ?: null, (int)($office['id'] ?? 0) ?: null, $today, $today, $now]);
        $ids['report'] = (int)$pdo->lastInsertId();
        $step('Report issued on the job', $irn, 'Written against the job — carries the job, the work-order and the customer.');
    } catch (Throwable $e) { $step('Report step skipped', '—', 'Reporting module not built on this install.'); }

    // ---- 13. Invoice raised, billing the job (real books_* functions) ----
    if (function_exists('books_invoice_create')) {
        $inv = books_invoice_create(['partner_id' => $clientId, 'quotation_id' => $quoteId,
                                     'contract_number' => TRACE_CONTRACT, 'sbu' => 'IND',
                                     'office_id' => (int)($office['id'] ?? 0) ?: null]);
        if (!empty($inv['id'])) {
            $invId = (int)$inv['id']; $ids['invoice'] = $invId;
            // A line billing the job. If the job has no billable value on its
            // call, fall back to a described line at the quoted figure.
            $addErr = books_line_add($invId, ['job_id' => $jobId]);
            if ($addErr !== '') $addErr = books_line_add($invId, [
                'description' => 'Third-party inspection of 4 pressure vessels', 'hsn_sac' => '998346',
                'qty' => 1, 'rate' => 100000, 'gst_pct' => 18]);
            $issueErr = books_issue($invId);
            $step('Invoice raised & issued', ops_val("SELECT invoice_no FROM invoices WHERE id=?", [$invId]) ?: ('draft #' . $invId),
                  $issueErr === '' ? 'Bills the job, and quotes the contract number.' : ('Draft — ' . $issueErr));

            // ---- 14. Money-in recorded and matched to the invoice --------
            if (function_exists('books_receipt_create') && $issueErr === '') {
                $total = (float) ops_val("SELECT total FROM invoices WHERE id=?", [$invId]);
                $rc = books_receipt_create(['partner_id' => $clientId, 'amount' => $total, 'tds_amount' => 0,
                                            'mode' => 'NEFT', 'reference' => 'UTR-GT-0001', 'receipt_date' => $today]);
                if (!empty($rc['id'])) {
                    $ids['receipt'] = (int)$rc['id'];
                    books_allocate((int)$rc['id'], [$invId => ['cash' => $total, 'tds' => 0]]);
                    $step('Money-in recorded & matched', $rc['no'] ?? ('#' . $rc['id']),
                          '₹' . number_format($total) . ' received and matched to the invoice — it now shows as paid.');
                }
            }
        }
    }

    setting_set('trace_seeded', '1');
    $checks = trace_checks($ids);
    return ['ok' => true, 'ids' => $ids, 'steps' => $steps, 'checks' => $checks];
}

// ----------------------------------------------------------------------------
//  The traceability checks — read the database back and confirm every link.
//  Each row: [area, what it proves, expected, actual, ok].
// ----------------------------------------------------------------------------
function trace_checks(array $ids) {
    $c = []; $v = fn($sql, $a = []) => ops_val($sql, $a);
    $add = function ($area, $proves, $expected, $actual) use (&$c) {
        $c[] = ['area' => $area, 'proves' => $proves, 'expected' => (string)$expected,
                'actual' => (string)$actual, 'ok' => (string)$expected === (string)$actual];
    };
    $clientId = $ids['client'] ?? 0; $leadId = $ids['lead'] ?? 0; $oppId = $ids['opp'] ?? 0;
    $quoteId = $ids['quote'] ?? 0; $callId = $ids['call'] ?? 0; $jobId = $ids['job'] ?? 0;
    $invId = $ids['invoice'] ?? 0; $rcId = $ids['receipt'] ?? 0;

    // Quotation ↔ lead & customer, and the maths.
    $add('Quotation', 'The quote knows the lead it came from', $leadId, $v("SELECT lead_id FROM quotations WHERE id=?", [$quoteId]));
    $add('Quotation', 'The quote is against the right customer', $clientId, $v("SELECT client_id FROM quotations WHERE id=?", [$quoteId]));
    $add('Quotation', 'Subtotal is ₹1,00,000', '100000', (int)$v("SELECT subtotal FROM quotations WHERE id=?", [$quoteId]));
    $add('Quotation', 'GST is ₹18,000', '18000', (int)$v("SELECT gst_amount FROM quotations WHERE id=?", [$quoteId]));
    $add('Quotation', 'Total is ₹1,18,000', '118000', (int)$v("SELECT total_amount FROM quotations WHERE id=?", [$quoteId]));
    $add('Quotation', 'The quote is marked accepted', 'ACCEPTED', $v("SELECT status FROM quotations WHERE id=?", [$quoteId]));
    $add('Quotation', 'The contract number is on the quote', TRACE_CONTRACT, $v("SELECT contract_number FROM quotations WHERE id=?", [$quoteId]));

    // Accept → deal sync.
    $add('Deal', 'The accepted quote is attached to the deal', '1',
         (int)$v("SELECT COUNT(*) FROM opportunity_quotes WHERE opportunity_id=? AND quotation_id=?", [$oppId, $quoteId]));
    $add('Deal', 'The customer was carried onto the deal', $clientId, $v("SELECT partner_id FROM opportunities WHERE id=?", [$oppId]));
    $add('Deal', 'The deal is won', 'WON', $v("SELECT status FROM opportunities WHERE id=?", [$oppId]));
    $add('Deal', 'The deal knows its work-order', $callId, $v("SELECT call_id FROM opportunities WHERE id=?", [$oppId]));

    // Lead status flowed back.
    $add('Lead', 'The lead now reads converted', 'CONVERTED', $v("SELECT status FROM leads WHERE id=?", [$leadId]));
    $add('Lead', 'The lead points at the customer', $clientId, $v("SELECT converted_partner_id FROM leads WHERE id=?", [$leadId]));

    // Work-order (call) carries the quote + contract + customer.
    $add('Work-order', 'The work-order carries the quotation', $quoteId, $v("SELECT quotation_id FROM calls WHERE id=?", [$callId]));
    $add('Work-order', 'The contract number reached the work-order', TRACE_CONTRACT, $v("SELECT contract_number FROM calls WHERE id=?", [$callId]));
    $add('Work-order', 'The work-order is for the right customer', $clientId, $v("SELECT client_id FROM calls WHERE id=?", [$callId]));
    $add('Work-order', 'Billable base is the quote ex-GST subtotal (₹1,00,000)', '100000', (int)$v("SELECT billable_value FROM calls WHERE id=?", [$callId]));

    // Job carries everything from the work-order.
    $add('Job', 'The job belongs to the work-order', $callId, $v("SELECT call_id FROM jobs WHERE id=?", [$jobId]));
    $add('Job', 'The job carries the quotation', $quoteId, $v("SELECT quotation_id FROM jobs WHERE id=?", [$jobId]));
    $add('Job', 'The contract number reached the job', TRACE_CONTRACT, $v("SELECT contract_number FROM jobs WHERE id=?", [$jobId]));
    $add('Job', 'The site check-in is against the job', '1',
         (int)$v("SELECT COUNT(*) FROM site_visits WHERE job_id=?", [$jobId]));

    // Report on the job.
    if (!empty($ids['report'])) {
        $add('Report', 'The report is written against the job', $jobId, $v("SELECT job_id FROM report_docs WHERE id=?", [$ids['report']]));
        $add('Report', 'The report is for the right customer', $clientId, $v("SELECT client_id FROM report_docs WHERE id=?", [$ids['report']]));
        $add('Report', 'The report is issued', 'ISSUED', $v("SELECT status FROM report_docs WHERE id=?", [$ids['report']]));
    }

    // Invoice bills the job; money matches the invoice.
    if ($invId) {
        $add('Invoice', 'The invoice is for the right customer', $clientId, $v("SELECT partner_id FROM invoices WHERE id=?", [$invId]));
        $add('Invoice', 'The invoice carries the quotation', $quoteId, $v("SELECT quotation_id FROM invoices WHERE id=?", [$invId]));
        $add('Invoice', 'A line bills the job', '1', (int)$v("SELECT COUNT(*) FROM invoice_lines WHERE invoice_id=? AND job_id=?", [$invId, $jobId]));
        $add('Invoice', 'The invoice is issued (has a number)', '1',
             (int)$v("SELECT CASE WHEN COALESCE(invoice_no,'')<>'' THEN 1 ELSE 0 END FROM invoices WHERE id=?", [$invId]));
        if ($rcId) {
            $add('Money-in', 'The receipt is for the right customer', $clientId, $v("SELECT partner_id FROM receipts WHERE id=?", [$rcId]));
            $add('Money-in', 'The receipt is matched to the invoice', '1',
                 (int)$v("SELECT COUNT(*) FROM receipt_allocations WHERE receipt_id=? AND invoice_id=?", [$rcId, $invId]));
            $add('Money-in', 'The invoice now shows as paid', 'PAID', $v("SELECT status FROM invoices WHERE id=?", [$invId]));
        }
    }
    return $c;
}

// ----------------------------------------------------------------------------
//  Remove the whole thread by walking from the two anchor partners.
// ----------------------------------------------------------------------------
function trace_seed_remove() {
    $pdo = db();
    $n = 0;
    $clientId = trace_client_id();
    $vendorId = (int) ops_val("SELECT id FROM business_partners WHERE code=?", [TRACE_VENDOR_CODE]);
    $del = function ($sql, $args = []) use ($pdo, &$n) {
        try { $st = $pdo->prepare($sql); $st->execute($args); $n += $st->rowCount(); } catch (Throwable $e) {}
    };
    if ($clientId) {
        // Children first, walking outward from the customer.
        $del("DELETE FROM receipt_allocations WHERE receipt_id IN (SELECT id FROM receipts WHERE partner_id=?)", [$clientId]);
        $del("DELETE FROM receipts WHERE partner_id=?", [$clientId]);
        $del("DELETE FROM invoice_lines WHERE invoice_id IN (SELECT id FROM invoices WHERE partner_id=?)", [$clientId]);
        $del("DELETE FROM invoices WHERE partner_id=?", [$clientId]);
        $del("DELETE FROM site_visits WHERE job_id IN (SELECT id FROM jobs WHERE call_id IN (SELECT id FROM calls WHERE client_id=?))", [$clientId]);
        $del("DELETE FROM report_docs WHERE client_id=?", [$clientId]);
        $del("DELETE FROM jobs WHERE call_id IN (SELECT id FROM calls WHERE client_id=?)", [$clientId]);
        $del("DELETE FROM calls WHERE client_id=?", [$clientId]);
        $del("DELETE FROM opportunity_quotes WHERE opportunity_id IN (SELECT id FROM opportunities WHERE partner_id=?)", [$clientId]);
        $del("DELETE FROM opportunities WHERE partner_id=?", [$clientId]);
        $del("DELETE FROM quote_lines WHERE quote_id IN (SELECT id FROM quotations WHERE client_id=?)", [$clientId]);
        $del("DELETE FROM quotations WHERE client_id=?", [$clientId]);
        $del("DELETE FROM partner_contracts WHERE partner_id=?", [$clientId]);
        $del("DELETE FROM crm_inquiries WHERE client_id=?", [$clientId]);
        $del("DELETE FROM leads WHERE partner_id=? OR converted_partner_id=?", [$clientId, $clientId]);
        $del("DELETE FROM partner_contacts WHERE partner_id=?", [$clientId]);
        $del("DELETE FROM partner_addresses WHERE partner_id=?", [$clientId]);
    }
    // Anything created_by 'trace' as a backstop.
    foreach (['report_docs','jobs','calls','quotations'] as $t) $del("DELETE FROM $t WHERE created_by='trace'");
    $del("DELETE FROM business_partners WHERE code IN (?,?)", [TRACE_CLIENT_CODE, TRACE_VENDOR_CODE]);
    setting_set('trace_seeded', '');
    return ['deleted' => $n];
}
