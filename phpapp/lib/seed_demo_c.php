<?php
// ============================================================================
//  demo_seed_modules_c — Part-three demo data (the ~80 registers the original
//  seed never reached). Assembled from six schema-mapped module groups, each a
//  self-contained function run in its own try/catch by the dispatcher below so
//  one register's schema drift can never abort the rest of the demo load.
//  Anchored entirely on the demo cast in $x; every row carries 'demo' (or a
//  DEMO-/demo: marker) so seed_demo_remove() can take it all back out.
// ============================================================================

function demo_seed_c1($pdo, $x, $has, $ins) {
    $n = [];
// ==================================================================
    //  MODULE C — CRM funnel: leads, opportunities, quotes lifecycle,
    //  approvals, stage-gates, templates.  All rows are demo-provenanced.
    // ==================================================================
    $oid = $x['oid']; $iid = $x['iid']; $cid = $x['cid']; $vid = $x['vid'];
    $callid = $x['callid']; $jid = $x['jid'];
    $now = $x['now']; $today = $x['today']; $d = $x['d']; $hash = $x['hash'];

    // ---- Shared lookups (guard every 0) ------------------------------
    $qA = (int)$pdo->query("SELECT id FROM quotations WHERE quote_no='Q-2607-001'")->fetchColumn(); // ACCEPTED, IND/AMD, 177000
    $qB = (int)$pdo->query("SELECT id FROM quotations WHERE quote_no='Q-2607-002'")->fetchColumn(); // SENT,    OGC/MUM, 283200
    $qC = (int)$pdo->query("SELECT id FROM quotations WHERE quote_no='Q-2607-003'")->fetchColumn(); // LOST,    OGC/AMD, 365800
    $inq1 = (int)$pdo->query("SELECT id FROM crm_inquiries WHERE inquiry_no='INQ-2607-01'")->fetchColumn();
    $uBM  = (int)$pdo->query("SELECT id FROM users WHERE username='bmanager' LIMIT 1")->fetchColumn();
    $uDIR = (int)$pdo->query("SELECT id FROM users WHERE username='director' LIMIT 1")->fetchColumn();

    // Resolve pipelines/stages by kind+name so hard ids never drift.
    $pipeLead = (int)$pdo->query("SELECT id FROM pipelines WHERE entity_kind='LEAD' ORDER BY is_default DESC, id LIMIT 1")->fetchColumn();
    $pipeOpp  = (int)$pdo->query("SELECT id FROM pipelines WHERE entity_kind='OPPORTUNITY' ORDER BY is_default DESC, id LIMIT 1")->fetchColumn();
    $stg = function ($pipe, $name) use ($pdo) {
        if (!$pipe) return null;
        $s = $pdo->prepare("SELECT id FROM pipeline_stages WHERE pipeline_id=? AND name=? LIMIT 1");
        $s->execute([$pipe, $name]);
        return ($v = (int)$s->fetchColumn()) ? $v : null;
    };

    // ------------------------------------------------------------------
    //  CRM templates  (screen: /crm-templates)
    //  prefer calling crm_template save handler (lib/crm.php ~2601); direct here.
    // ------------------------------------------------------------------
    if ($has('crm_templates')) {
        $ct = "INSERT INTO crm_templates (kind,name,subject,body,file_name,file_data,fields,document_number,format_number,doc_revision,issue_date,active,is_default,created_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)";
        $tplEmail = $ins($ct, ['EMAIL_FOLLOWUP','Quotation follow-up (email)',
            'Following up on our quotation {{quote_no}}',
            "Dear {{contact_name}},\n\nGentle reminder on our quotation {{quote_no}} dated {{quote_date}} for {{subject}}. Kindly let us know your feedback so we can schedule the inspection.\n\nRegards,\n{{owner_name}}\nExact Inspection Services",
            '', '', json_encode(['quote_no','contact_name','quote_date','subject','owner_name']),
            'EIS-CRM-F-01','F-01','Rev 2', $d(-200), 1, 1, $now]);
        $ins($ct, ['WHATSAPP_FOLLOWUP','Quotation follow-up (WhatsApp)', '',
            "Hello {{contact_name}}, following up on quotation {{quote_no}} from Exact Inspection. Happy to revise scope/rate if needed. — {{owner_name}}",
            '', '', json_encode(['contact_name','quote_no','owner_name']),
            'EIS-CRM-F-02','F-02','Rev 1', $d(-120), 1, 0, $now]);
        $ins($ct, ['QUOTE_COVER','Quotation covering letter', 'Quotation {{quote_no}} — {{subject}}',
            "Please find enclosed our quotation {{quote_no}} for third-party inspection services as per your enquiry. Validity {{validity_days}} days.",
            '', '', json_encode(['quote_no','subject','validity_days']),
            'EIS-CRM-T-07','T-07','Rev 3', $d(-90), 1, 0, $now]);
        $n['crm_templates'] = 3;
    } else { $tplEmail = null; }

    // ------------------------------------------------------------------
    //  Quote approval rules  (screen: /quote-approval-rules)
    // ------------------------------------------------------------------
    if ($has('quote_approval_rules')) {
        $qr = "INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?)";
        $ins($qr, ['DEMO — OGC quotes above 2.5L need Branch Manager','ALL','OGC',250000,0,1,'BRANCH_MANAGER',$uBM?:null,1,$now]);
        $ins($qr, ['DEMO — Any quote above 5L needs Director','ALL','',500000,0,2,'BUSINESS_DIRECTOR',$uDIR?:null,1,$now]);
        $ins($qr, ['DEMO — IND small jobs (auto, inactive)','ALL','IND',0,100000,1,'SBU_HEAD',null,0,$now]);
        $n['quote_approval_rules'] = 3;
    }

    // ------------------------------------------------------------------
    //  Per-quote lifecycle rows  (screen: /quote?id=…)
    //  quote_approvals / quote_edit_requests / quote_files / quote_followups / quote_revisions
    // ------------------------------------------------------------------
    if ($has('quote_approvals') && $qB) {
        // Q-2607-002 (SENT, 2.83L OGC) — sits PENDING at BM, then Director L2 waiting.
        $ins("INSERT INTO quote_approvals (quote_id,level,approver_role,approver_user_id,status,acted_by,acted_at,remarks)
              VALUES (?,?,?,?,?,?,?,?)",
            [$qB,1,'BRANCH_MANAGER',$uBM?:null,'PENDING','','','[demo] Awaiting BM sign-off — above OGC 2.5L threshold']);
        $ins("INSERT INTO quote_approvals (quote_id,level,approver_role,approver_user_id,status,acted_by,acted_at,remarks)
              VALUES (?,?,?,?,?,?,?,?)",
            [$qB,2,'BUSINESS_DIRECTOR',$uDIR?:null,'PENDING','','','[demo] Blocked until L1 clears']);
        if ($qA) // Q-2607-001 already won — its approval is APPROVED (history)
            $ins("INSERT INTO quote_approvals (quote_id,level,approver_role,approver_user_id,status,acted_by,acted_at,remarks)
                  VALUES (?,?,?,?,?,?,?,?)",
                [$qA,1,'BRANCH_MANAGER',$uBM?:null,'APPROVED','Meena Shah',$d(-35),'[demo] Approved — within delegation']);
        $n['quote_approvals'] = $qA ? 3 : 2;
    }

    if ($has('quote_edit_requests') && $qA) {
        // Edge case: someone wants to reopen an ACCEPTED quote to fix a rate.
        $ins("INSERT INTO quote_edit_requests (quote_id,requested_by,requested_by_id,reason,status,decided_by,decided_at,decision_note,expires_at,created_at)
              VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$qA,'demo',null,'[demo] Client asked to correct vessel count from 12 to 14 before invoicing','PENDING','','','', $d(3), $now]);
        if ($qC) // an approved-then-expired edit window on the LOST quote
            $ins("INSERT INTO quote_edit_requests (quote_id,requested_by,requested_by_id,reason,status,decided_by,decided_at,decision_note,expires_at,created_at)
                  VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$qC,'demo',null,'[demo] Requested to revise price after loss','APPROVED','Meena Shah',$d(-18),'One-time reopen granted',$d(-11),$now]);
        $n['quote_edit_requests'] = $qC ? 2 : 1;
    }

    if ($has('quote_files')) {
        $qf = "INSERT INTO quote_files (quote_id,kind,file_name,mime,file_data,note,share_with_inspector,uploaded_by,uploaded_at)
               VALUES (?,?,?,?,?,?,?, 'demo', ?)";
        $tiny = base64_encode("%PDF-1.4 demo placeholder\n");
        if ($qA) $ins($qf, [$qA,'PO','Narmada-PO-40231.pdf','application/pdf',$tiny,'Signed PO received against the quote',1,$now]);
        if ($qB) $ins($qf, [$qB,'ATTACHMENT','Suryavan-jetty-spec.pdf','application/pdf',$tiny,'Client fabrication spec — read before quoting',1,$now]);
        if ($qB) $ins($qf, [$qB,'QUOTE','Q-2607-002-signed.pdf','application/pdf',$tiny,'Our signed copy sent to client',0,$now]);
        $n['quote_files'] = ($qA?1:0)+($qB?2:0);
    }

    if ($has('quote_followups')) {
        $qfu = "INSERT INTO quote_followups (quote_id,kind,due_date,template_id,status,sent_at,created_at,done_date,note,owner_id)
                VALUES (?,?,?,?,?,?,?,?,?,?)";
        if ($qB) { // OVERDUE pending chase on the SENT quote — the follow-up screen must flag this
            $ins($qfu, [$qB,'EMAIL',$d(-2),$tplEmail,'PENDING','',$now,'','[demo] Validity almost gone — chase Sunita Rao',null]);
            $ins($qfu, [$qB,'CALL',$d(4),null,'PENDING','',$now,'','[demo] Planned call if no email reply',null]);
        }
        if ($qA) // a completed follow-up on the won quote
            $ins($qfu, [$qA,'EMAIL',$d(-33),$tplEmail,'DONE',$d(-33),$now,$d(-33),'[demo] Client confirmed, moved to PO',null]);
        $n['quote_followups'] = ($qB?2:0)+($qA?1:0);
    }

    if ($has('quote_revisions') && $qB) {
        // rev 0 superseded by rev 1 (price revised down in negotiation)
        $ins("INSERT INTO quote_revisions (quote_id,rev,changed_by,changed_at,summary,snapshot) VALUES (?,?,?,?,?,?)",
            [$qB,0,'demo',$d(-13),'[demo] Original issue — 240000 + 18% GST',
             json_encode(['subtotal'=>240000,'gst'=>43200,'total'=>283200,'validity_days'=>15])]);
        $ins("INSERT INTO quote_revisions (quote_id,rev,changed_by,changed_at,summary,snapshot) VALUES (?,?,?,?,?,?)",
            [$qB,1,'demo',$d(-4),'[demo] Rev 1 — dropped rate after negotiation, validity extended to 30d',
             json_encode(['subtotal'=>228000,'gst'=>41040,'total'=>269040,'validity_days'=>30])]);
        $n['quote_revisions'] = 2;
    }

    // ------------------------------------------------------------------
    //  A demo lead to anchor lead_files + lead_stage_history
    //  (leads is not a "module C" table but nothing else seeds it in seed_demo;
    //   prefer calling lead_create() — direct insert here since seed is headless.)
    //  screen: /leads  and  /lead?id=…
    // ------------------------------------------------------------------
    $leadId = 0;
    if ($has('leads')) {
        $leadId = $ins("INSERT INTO leads
            (ref,pipeline_id,stage_id,company_name,partner_id,contact_name,contact_email,contact_phone,
             source,source_note,requirement,value,expected_close,owner_user_id,owner_name,office_id,sbu,
             status,stage_since,next_action_on,next_action,created_by,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?,?,?, 'demo', ?,?)",
            ['DEMO-L-2607-01',$pipeLead,$stg($pipeLead,'Contacted'),'Kutch Petrochem Pvt Ltd',$cid['CL-GHE'],
             'Farida Qureshi','farida.q@example.com','9825022210',
             'REFERRAL','Referred by Girnar Energy','NDT + PWHT witness for a new hydrocracker skid; ~8 weeks at Dahej.',
             450000,$d(40),null,'Nisha Rao',$oid['AMD'],'OGC',
             $d(-9),$d(2),'[demo] Send capability statement and rate card', $d(-14),$now]);
        $n['leads_demo'] = 1;

        if ($has('lead_stage_history') && $leadId) {
            $lh = "INSERT INTO lead_stage_history (lead_id,from_stage_id,to_stage_id,from_name,to_name,days_in_previous,moved_by,moved_at) VALUES (?,?,?,?,?,?, 'demo', ?)";
            $ins($lh, [$leadId,null,$stg($pipeLead,'New'),'','New',0,$d(-14)]);
            $ins($lh, [$leadId,$stg($pipeLead,'New'),$stg($pipeLead,'Contacted'),'New','Contacted',5,$d(-9)]);
            $n['lead_stage_history'] = 2;
        }
        if ($has('lead_files') && $leadId) {
            $lf = "INSERT INTO lead_files (lead_id,kind,file_name,mime,file_data,note,carried_to_quote,uploaded_by,uploaded_at) VALUES (?,?,?,?,?,?,?, 'demo', ?)";
            $tiny = $tiny ?? base64_encode("%PDF-1.4 demo\n");
            $ins($lf, [$leadId,'ATTACHMENT','Kutch-enquiry-email.pdf','application/pdf',$tiny,'Original enquiry forwarded by Girnar',0,$now]);
            $ins($lf, [$leadId,'SPEC','hydrocracker-skid-scope.xlsx','application/vnd.ms-excel',$tiny,'Scope worksheet — to carry into the quote',1,$now]);
            $n['lead_files'] = 2;
        }
    }

    // ------------------------------------------------------------------
    //  Opportunities  (screen: /opportunities ; detail /opportunity?id=…)
    //  prefer calling opp_create() (lib/opportunities.php ~321); direct here.
    //  opportunity_quotes + opportunity_stage_history hang off these.
    // ------------------------------------------------------------------
    if ($has('opportunities')) {
        $oc = "INSERT INTO opportunities
            (ref,name,partner_id,partner_name,lead_id,inquiry_id,pipeline_id,stage_id,value,currency,probability,
             expected_close,source,requirement,competitor,contact_name,contact_email,contact_phone,
             owner_user_id,owner_name,office_id,sbu,status,stage_since,next_action_on,next_action,
             lost_reason,lost_note,lost_to,won_at,won_by,won_quotation_id,created_by,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?, 'INR', ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?,?)";

        // OPP-1: OPEN, quotation sent — anchored to Q-2607-002 (SENT)
        $op1 = $ins($oc, ['DEMO-OPP-2607-01','Suryavan jetty — structural TPI',$cid['CL-SVP'],'Suryavan Ports & SEZ Ltd',
            null,null,$pipeOpp,$stg($pipeOpp,'Quotation sent'),283200,65,$d(20),'INQUIRY',
            'Structural inspection of jetty fabrication at Mundra SEZ.','Bureau Veritas',
            'Sunita Rao','sunita.rao@example.com','9825011002',null,'Nisha Rao',$oid['MUM'],'OGC','OPEN',
            $d(-13),$d(-2),'[demo] BM approval pending; then chase client','','','','','',null,$now,$now]);

        // OPP-2: WON — anchored to Q-2607-001 (ACCEPTED)
        $op2 = $ins($oc, ['DEMO-OPP-2607-02','Narmada — 12 pressure vessels TPI',$cid['CL-NIL'],'Narmada Industries Ltd',
            null,$inq1?:null,$pipeOpp,$stg($pipeOpp,'Won'),177000,100,$d(-30),'INQUIRY',
            'TPI of 12 ASME VIII Div 1 vessels at Vapi.','',
            'Rakesh Menon','rakesh.menon@example.com','9825011001',null,'Nisha Rao',$oid['AMD'],'IND','WON',
            $d(-30),'','','','','',$d(-30),'Meena Shah',$qA?:null,$now,$now]);

        // OPP-3: LOST — CL-GHE, competitor took it on price
        $op3 = $ins($oc, ['DEMO-OPP-2607-03','Girnar — coating inspection, 4 tanks',$cid['CL-GHE'],'Girnar Energy Hydrocarbon',
            null,null,$pipeOpp,$stg($pipeOpp,'Lost'),365800,0,$d(-10),'INQUIRY',
            'Coating/DFT inspection campaign, 4 storage tanks at Chakan.','TUV India',
            'Deepa Balan','deepa.balan@example.com','9825011004',null,'Nisha Rao',$oid['AMD'],'OGC','LOST',
            $d(-8),'','','PRICE','[demo] Undercut on manday rate','TUV India','','',null,$now,$now]);

        // OPP-4: early stage, sprung from the demo lead (lead → opportunity link)
        $op4 = $leadId ? $ins($oc, ['DEMO-OPP-2607-04','Kutch Petrochem — hydrocracker NDT+PWHT',$cid['CL-GHE'],'Kutch Petrochem Pvt Ltd',
            $leadId,null,$pipeOpp,$stg($pipeOpp,'Qualified'),450000,25,$d(45),'REFERRAL',
            'NDT plus PWHT witness for hydrocracker skid at Dahej.','',
            'Farida Qureshi','farida.q@example.com','9825022210',null,'Nisha Rao',$oid['AMD'],'OGC','OPEN',
            $d(-3),$d(5),'[demo] Prepare quotation from lead scope','','','','','',null,$now,$now]) : 0;
        $n['opportunities'] = $op4 ? 4 : 3;

        if ($has('opportunity_quotes')) {
            $oq = "INSERT INTO opportunity_quotes (opportunity_id,quotation_id,linked_by,linked_at) VALUES (?,?, 'demo', ?)";
            if ($qB) $ins($oq, [$op1,$qB,$now]);
            if ($qA) $ins($oq, [$op2,$qA,$now]);
            if ($qC) $ins($oq, [$op3,$qC,$now]);
            $n['opportunity_quotes'] = ($qB?1:0)+($qA?1:0)+($qC?1:0);
        }

        if ($has('opportunity_stage_history')) {
            $oh = "INSERT INTO opportunity_stage_history (opportunity_id,from_stage_id,to_stage_id,from_name,to_name,days_in_previous,moved_by,moved_at) VALUES (?,?,?,?,?,?, 'demo', ?)";
            // OPP-2 full walk to Won
            $ins($oh, [$op2,null,$stg($pipeOpp,'Qualified'),'','Qualified',0,$d(-40)]);
            $ins($oh, [$op2,$stg($pipeOpp,'Qualified'),$stg($pipeOpp,'Quotation sent'),'Qualified','Quotation sent',4,$d(-36)]);
            $ins($oh, [$op2,$stg($pipeOpp,'Quotation sent'),$stg($pipeOpp,'Won'),'Quotation sent','Won',6,$d(-30)]);
            // OPP-1 to Quotation sent
            $ins($oh, [$op1,$stg($pipeOpp,'Qualified'),$stg($pipeOpp,'Quotation sent'),'Qualified','Quotation sent',3,$d(-13)]);
            $n['opportunity_stage_history'] = 4;
        }
    }

    // ------------------------------------------------------------------
    //  Stage gates + a blocked gate request
    //  stage_gates  (screen: /stage-gates)
    //  stage_gate_requests  (screen: /approvals)
    // ------------------------------------------------------------------
    if ($has('stage_gates')) {
        $sg = "INSERT INTO stage_gates (name,entity_kind,pipeline_id,stage_id,stage_kind,sbu,min_amount,max_amount,approver_role,approver_user_id,active,created_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)";
        $gate1 = $ins($sg, ['DEMO — OGC won deals above 2.5L need BM','OPPORTUNITY',$pipeOpp,$stg($pipeOpp,'Won'),'WON','OGC',250000,0,'BRANCH_MANAGER',$uBM?:null,1,$now]);
        $ins($sg, ['DEMO — Any won deal above 5L needs Director','OPPORTUNITY',$pipeOpp,$stg($pipeOpp,'Won'),'WON','',500000,0,'BUSINESS_DIRECTOR',$uDIR?:null,1,$now]);
        $n['stage_gates'] = 2;

        if ($has('stage_gate_requests') && $has('opportunities') && isset($op1)) {
            // OPP-1 (2.83L OGC) trying to move to Won — BLOCKED, pending BM.
            $ins("INSERT INTO stage_gate_requests
                 (entity_kind,entity_id,gate_id,from_stage_id,to_stage_id,amount,sbu,payload,note,approver_role,approver_user_id,status,requested_by,requested_by_id,requested_at,acted_by,acted_at,remarks)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?, 'PENDING', 'demo', ?,?,?,?,?)",
                ['OPPORTUNITY',$op1,$gate1,$stg($pipeOpp,'Quotation sent'),$stg($pipeOpp,'Won'),283200,'OGC',
                 json_encode(['opp'=>'DEMO-OPP-2607-01','quote'=>'Q-2607-002']),
                 '[demo] Client verbally confirmed — awaiting BM to mark Won','BRANCH_MANAGER',$uBM?:null,
                 null,$d(-1),'','','']);
            $n['stage_gate_requests'] = 1;
        }
    }
    return $n;
}

function demo_seed_c2($pdo, $x, $has, $ins) {
    $n = [];
// Anchors from the shared seed context (same idiom as _b).
    $oid = $x['oid']; $iid = $x['iid']; $cid = $x['cid']; $vid = $x['vid'];
    $now = $x['now']; $d = $x['d']; $hash = $x['hash'];

    // ------------------------------------------------------------------
    //  Partner directory — notes, group relationships, registrations
    //  (Customer 360 / vendor register read these off the partner id.)
    // ------------------------------------------------------------------
    if ($has('partner_notes')) {
        $ins("INSERT INTO partner_notes (partner_id,note,author_name,created_at) VALUES (?,?,?,?)",
            [$cid['CL-NIL'], 'Prefers stage-plus-final on all pressure-vessel jobs. Single point of contact: Rakesh Menon (works). Sends PO within 3 days of quote acceptance.', 'demo', $now]);
        $ins("INSERT INTO partner_notes (partner_id,note,author_name,created_at) VALUES (?,?,?,?)",
            [$vid['VN-MUN'], 'DEMO — Vendor suspended after the July audit: welder PQR lapsed and an NCR on fit-up tolerance is still open. Do not deploy until requalified.', 'demo', $now]);
        $n['partner_notes'] = 2;
    }

    if ($has('partner_relationships')) {
        // Two clients in the same group; one vendor sub-contracts to another.
        $ins("INSERT INTO partner_relationships (partner_id,related_id,relation_type,notes) VALUES (?,?,?,?)",
            [$cid['CL-NIL'], $cid['CL-SVP'], 'GROUP', 'DEMO — same promoter group (billing consolidated).']);
        $ins("INSERT INTO partner_relationships (partner_id,related_id,relation_type,notes) VALUES (?,?,?,?)",
            [$vid['VN-MUN'], $vid['VN-VAP'], 'SUBCONTRACTOR', 'DEMO — Mundra sublets coating scope to Vapi Chem.']);
        $n['partner_relationships'] = 2;
    }

    if ($has('partner_registrations')) {
        $ins("INSERT INTO partner_registrations (partner_id,doc_type,number,valid_to,notes) VALUES (?,?,?,?,?)",
            [$cid['CL-NIL'], 'GSTIN', '24AABCN1234M1Z5', $d(300), 'DEMO — active']);
        $ins("INSERT INTO partner_registrations (partner_id,doc_type,number,valid_to,notes) VALUES (?,?,?,?,?)",
            [$vid['VN-VAP'], 'PAN', 'AAFCV3456N', '', 'DEMO — no expiry']);
        // Edge: expired registration — the vendor-profile screen flags this.
        $ins("INSERT INTO partner_registrations (partner_id,doc_type,number,valid_to,notes) VALUES (?,?,?,?,?)",
            [$vid['VN-MUN'], 'ISO_9001', 'IN-QMS-88213', $d(-25), 'DEMO — lapsed certificate, renewal overdue']);
        $n['partner_registrations'] = 3;
    }

    // ------------------------------------------------------------------
    //  Contracts + purchase orders + PO line items (order ~85% consumed)
    //  Screens: Customer 360 "Contracts", partner-pos / po-lines drawers.
    // ------------------------------------------------------------------
    if ($has('partner_contracts')) {
        // Live annual TPI contract on the flagship client.
        $ctrNil = $ins("INSERT INTO partner_contracts
                (partner_id,contract_number,title,value,start_date,end_date,is_active,notes,sbu,qty_total,qty_unit,open_status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$cid['CL-NIL'], 'DEMO-CTR-2607-01', 'Annual third-party inspection — pressure equipment',
             1500000, $d(-120), $d(245), 1, 'DEMO — rate contract, 200 mandays', 'IND', 200, 'MANDAY', 'OPEN']);
        // Edge: expired / closed contract.
        $ins("INSERT INTO partner_contracts
                (partner_id,contract_number,title,value,start_date,end_date,is_active,notes,sbu,qty_total,qty_unit,open_status,closed_at,close_reason)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$cid['CL-GHE'], 'DEMO-CTR-2606-09', 'Coating inspection campaign — tank farm',
             365000, $d(-400), $d(-40), 0, 'DEMO — completed and closed', 'OGC', 60, 'MANDAY', 'CLOSED', $now, 'Campaign completed']);
        $n['partner_contracts'] = 2;

        if ($has('partner_purchase_orders')) {
            $poNil = $ins("INSERT INTO partner_purchase_orders
                    (partner_id,contract_id,po_number,po_type,title,value,start_date,end_date,is_active,notes,sbu,lines_synced_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                [$cid['CL-NIL'], $ctrNil, 'DEMO-PO-2607-01', 'REGULAR', 'Release order — vessel inspection Q2',
                 1200000, $d(-90), $d(120), 1, 'DEMO — call-off against annual contract', 'IND', $now]);
            $n['partner_purchase_orders'] = 1;

            if ($has('po_line_items')) {
                // Line 1: 100 mandays, 85 consumed (~85%) — the near-exhausted balance the alert screen wants.
                $ins("INSERT INTO po_line_items
                        (purchase_order_id,description,item_type,quantity,rate,consumed,site,manpower,gst_pct,base_amount,tax_amount,total_amount,last_alert)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [$poNil, 'Stage inspection — pressure vessels', 'MANDAY', 100, 9000, 85, 'Vapi, Gujarat', 1, 18, 900000, 162000, 1062000, '80']);
                // Line 2: partially consumed.
                $ins("INSERT INTO po_line_items
                        (purchase_order_id,description,item_type,quantity,rate,consumed,site,manpower,gst_pct,base_amount,tax_amount,total_amount,last_alert)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [$poNil, 'Final inspection & certification', 'MANDAY', 50, 6000, 42, 'Vapi, Gujarat', 1, 18, 300000, 54000, 354000, '']);
                $n['po_line_items'] = 2;
            }
        }
    }

    // ------------------------------------------------------------------
    //  Vendor qualification — profiles, qualifications, status timeline
    //  Screen: /vendors register + vendor-profile detail (ops/idems).
    // ------------------------------------------------------------------
    if ($has('vendor_profiles')) {
        // Approved, in-date vendor.
        $ins("INSERT INTO vendor_profiles
                (partner_id,vendor_type,product_category,risk_class,approval_status,vendor_rating,last_score,last_band,
                 last_assessed_on,approved_on,valid_until,reassess_on,notes,updated_by,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$vid['VN-VAP'], 'MANUFACTURER', 'Pressure vessels', 'MEDIUM', 'APPROVED', 82, 82, 'A',
             $d(-60), $d(-60), $d(305), $d(305), 'DEMO — approved vendor, no open findings.', 'demo', $now]);
        // Edge: suspended vendor (end of the PROSPECT->APPROVED->SUSPENDED trail below).
        $ins("INSERT INTO vendor_profiles
                (partner_id,vendor_type,product_category,risk_class,approval_status,vendor_rating,last_score,last_band,
                 last_assessed_on,approved_on,valid_until,reassess_on,notes,updated_by,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$vid['VN-MUN'], 'FABRICATOR', 'Structural fabrication', 'HIGH', 'SUSPENDED', 54, 54, 'C',
             $d(-30), $d(-200), $d(-15), $d(-15), 'DEMO — suspended: open audit finding + lapsed qualification.', 'demo', $now]);
        // A never-assessed prospect, if the edge-vendor pool exists.
        if (isset($vid['EV1'])) {
            $ins("INSERT INTO vendor_profiles (partner_id,vendor_type,product_category,risk_class,approval_status,notes,updated_by,updated_at)
                    VALUES (?,?,?,?,?,?,?,?)",
                [$vid['EV1'], 'SERVICE', 'NDT services', 'LOW', 'PROSPECT', 'DEMO — awaiting first assessment.', 'demo', $now]);
            $n['vendor_profiles'] = 3;
        } else {
            $n['vendor_profiles'] = 2;
        }
    }

    if ($has('vendor_qualifications')) {
        $ins("INSERT INTO vendor_qualifications
                (partner_id,product,category,status,scope,conditions,effective_on,expiry_on,risk,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$vid['VN-VAP'], 'Carbon-steel pressure vessels (ASME VIII Div 1)', 'Pressure vessels', 'QUALIFIED',
             'Fabrication + hydro up to 2 m dia', '', $d(-60), $d(305), 'MEDIUM', $now]);
        // Edge: EXPIRED qualification.
        $ins("INSERT INTO vendor_qualifications
                (partner_id,product,category,status,scope,conditions,effective_on,expiry_on,risk,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$vid['VN-MUN'], 'Structural steel fabrication', 'Structural fabrication', 'EXPIRED',
             'Welded structures, shop only', 'Requalification pending — welder PQR to be resubmitted.', $d(-420), $d(-15), 'HIGH', $now]);
        $n['vendor_qualifications'] = 2;
    }

    if ($has('vendor_status_events')) {
        // Full trail on the suspended vendor: PROSPECT -> APPROVED -> SUSPENDED.
        $ins("INSERT INTO vendor_status_events (partner_id,old_status,new_status,source,score,reason,actor,at) VALUES (?,?,?,?,?,?,?,?)",
            [$vid['VN-MUN'], 'PROSPECT', 'APPROVED', 'ASSESSMENT', 78, 'Initial qualification passed.', 'demo', $d(-200).'T10:00:00+05:30']);
        $ins("INSERT INTO vendor_status_events (partner_id,old_status,new_status,source,score,reason,actor,at) VALUES (?,?,?,?,?,?,?,?)",
            [$vid['VN-MUN'], 'APPROVED', 'SUSPENDED', 'AUDIT', 54, 'Surveillance audit: open NCR on fit-up tolerance + welder PQR lapsed.', 'demo', $d(-30).'T15:30:00+05:30']);
        // The approved vendor's single event.
        $ins("INSERT INTO vendor_status_events (partner_id,old_status,new_status,source,score,reason,actor,at) VALUES (?,?,?,?,?,?,?,?)",
            [$vid['VN-VAP'], 'PROSPECT', 'APPROVED', 'ASSESSMENT', 82, 'Qualified — Grade A.', 'demo', $d(-60).'T11:00:00+05:30']);
        $n['vendor_status_events'] = 3;
    }

    // ------------------------------------------------------------------
    //  Vendor portal — login accounts and access audit trail
    //  Screens: vendor-users (portal admin) + portal access log.
    // ------------------------------------------------------------------
    if ($has('vendor_users')) {
        // Active, logged-in vendor user.
        $vuVap = $ins("INSERT INTO vendor_users
                (vendor_id,email,name,password_hash,is_active,must_change,perms,last_login_at,last_login_ip,created_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$vid['VN-VAP'], 'portal@vapichem.example', 'S. Desai', $hash, 1, 0, 'reports,invoices',
             $d(-2).'T09:15:00+05:30', '103.21.44.10', 'demo', $now]);
        // Invited but not yet accepted (edge: pending invite).
        $ins("INSERT INTO vendor_users
                (vendor_id,email,name,password_hash,is_active,must_change,invite_token,invite_expires,created_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$vid['VN-MUN'], 'qa@mundrafab.example', 'A. Rathod', '', 1, 1, 'demo-invite-'.substr($hash, 7, 24), $d(5), 'demo', $now]);
        $n['vendor_users'] = 2;

        if ($has('vendor_audit') && isset($vuVap)) {
            foreach ([['LOGIN', 'Portal sign-in', $d(-2).'T09:15:02+05:30'],
                      ['VIEW_REPORT', 'Viewed VASR-2607-01', $d(-2).'T09:16:40+05:30'],
                      ['DOWNLOAD', 'Downloaded inspection report PDF', $d(-2).'T09:17:22+05:30']] as $a) {
                $ins("INSERT INTO vendor_audit (vendor_user_id,vendor_id,action,detail,ip,at) VALUES (?,?,?,?,?,?)",
                    [$vuVap, $vid['VN-VAP'], $a[0], 'DEMO — '.$a[1], '103.21.44.10', $a[2]]);
            }
            $n['vendor_audit'] = 3;
        }
    }

    // ------------------------------------------------------------------
    //  Inspector -> vendor travel memory (auto-fills voucher km).
    // ------------------------------------------------------------------
    if ($has('vendor_km_memory')) {
        $ins("INSERT INTO vendor_km_memory (inspector_id,vendor_id,mode_code,km) VALUES (?,?,?,?)",
            [$iid['EMP01'], $vid['VN-VAP'], 'BIKE', 40]);
        $ins("INSERT INTO vendor_km_memory (inspector_id,vendor_id,mode_code,km) VALUES (?,?,?,?)",
            [$iid['EMP02'], $vid['VN-MUN'], 'CAR', 120]);
        $n['vendor_km_memory'] = 2;
    }
    return $n;
}

function demo_seed_c3($pdo, $x, $has, $ins) {
    $oid = $x['oid']; $iid = $x['iid']; $cid = $x['cid']; $vid = $x['vid'];
    $callid = $x['callid']; $jid = $x['jid'];
    $now = $x['now']; $today = $x['today']; $d = $x['d']; $hash = $x['hash'];
    $n = [];
    // Read helper (same shape as $val in demo_seed_modules_b).
    $val = function ($sql, $a = []) use ($pdo) {
        $s = $pdo->prepare($sql); $s->execute($a); $v = $s->fetchColumn(); $s->closeCursor(); return $v;
    };
    $row = function ($sql, $a = []) use ($pdo) {
        $s = $pdo->prepare($sql); $s->execute($a); $r = $s->fetch(PDO::FETCH_ASSOC); $s->closeCursor(); return $r ?: null;
    };

    // ==================================================================
    //  BOOKS — invoice lines, a receipt allocation, a credit note
    //  Anchor onto an invoice the trace seed already issued; only build a
    //  parent if the register is empty on this install.
    // ==================================================================
    $inv = null;
    if ($has('invoices')) {
        $inv = $row("SELECT id,partner_id,partner_name,office_id,subtotal,total,is_igst,invoice_no
                     FROM invoices WHERE status IN('ISSUED','PART_PAID','PAID') ORDER BY id LIMIT 1");
        if (!$inv && $has('invoices')) {
            // Parent was empty — insert one small INTRA-STATE (Gujarat) tax invoice, GST 18%.
            $invId = $ins("INSERT INTO invoices (invoice_no,series,fy,kind,partner_id,partner_name,office_id,sbu,
                           invoice_date,due_date,credit_days,place_of_supply,supplier_state,gstin,is_igst,
                           subtotal,cgst,sgst,igst,total,status,issued_at,issued_by,notes,created_by,created_at)
                           VALUES (?,?,?,'TAX',?,?,?,'IND',?,?,?,?,?,?,0,?,?,?,0,?,'ISSUED',?, 'demo', ?, 'demo', ?)",
                ['DEMO/AMD/2627/0009','AMD','2026-27',$cid['CL-NIL'],'Narmada Industries Ltd',$oid['AMD'],
                 $d(-20),$d(10),30,'24','24','24AABCN1234A1Z5',
                 100000,9000,9000,118000,$d(-20).'T12:00:00+05:30','DEMO seed invoice',$now]);
            $inv = ['id'=>$invId,'partner_id'=>$cid['CL-NIL'],'partner_name'=>'Narmada Industries Ltd',
                    'office_id'=>$oid['AMD'],'subtotal'=>100000,'total'=>118000,'is_igst'=>0,
                    'invoice_no'=>'DEMO/AMD/2627/0009'];
            $n['invoice(demo parent)'] = 1;
        }
    }

    // ---- invoice_lines : two lines that SUM EXACTLY to the invoice subtotal ----
    // prefer books_line_add($id,$body) — but it enforces job-closed/billing gates,
    // so for the seed we insert direct (books_recalc computes the same figures).
    if ($inv && $has('invoice_lines')) {
        $invId = (int)$inv['id'];
        $have  = (int)$val("SELECT COUNT(*) FROM invoice_lines WHERE invoice_id=?", [$invId]);
        if ($have === 0) {
            $S     = round((float)$inv['subtotal'], 2);
            $igst  = (int)$inv['is_igst'] === 1;
            $aAmt  = round($S * 0.6, 2);           // line 1: 60%
            $bAmt  = round($S - $aAmt, 2);          // line 2: remainder — guarantees the sum
            $mk = function ($seq, $desc, $qty, $unit, $rate, $amt) use ($ins, $invId, $inv, $igst, $jid) {
                $tax = round($amt * 0.18, 2);
                $cg  = $igst ? 0 : round($amt * 0.09, 2);
                $sg  = $igst ? 0 : round($tax - $cg, 2);
                $ig  = $igst ? $tax : 0;
                $lt  = round($amt + $cg + $sg + $ig, 2);
                return $ins("INSERT INTO invoice_lines
                    (invoice_id,seq,job_id,call_id,description,hsn_sac,qty,unit,rate,amount,gst_pct,cgst,sgst,igst,line_total)
                    VALUES (?,?,?,?,?,?,?,?,?,?,18,?,?,?,?)",
                    [$invId,$seq,$jid['J-2607-101'],null,$desc,'998311',$qty,$unit,$rate,$amt,$cg,$sg,$ig,$lt]);
            };
            $mk(1, 'DEMO — Stage inspection, 12 pressure vessels', 10, 'MANDAY', round($aAmt/10,2), $aAmt);
            $mk(2, 'DEMO — Final inspection & certification',        1, 'LOT',    $bAmt,             $bAmt);
            $n['invoice_lines'] = 2;
        }
    }

    // ---- receipt_allocations : an existing receipt settling the invoice ----
    if ($inv && $has('receipts') && $has('receipt_allocations')) {
        $rcp = $row("SELECT id,amount FROM receipts WHERE partner_id=? ORDER BY id LIMIT 1", [(int)$inv['partner_id']]);
        if (!$rcp) {
            $rcId = $ins("INSERT INTO receipts (receipt_no,partner_id,partner_name,office_id,receipt_date,mode,reference,
                          bank,amount,notes,created_by,created_at)
                          VALUES (?,?,?,?,?, 'NEFT', ?, 'HDFC 001', ?, 'DEMO receipt', 'demo', ?)",
                ['DEMO/RCP/2627/0009',(int)$inv['partner_id'],(string)$inv['partner_name'],(int)$inv['office_id'],
                 $d(-2),'UTR2607DEMO01',(float)$inv['total'],$now]);
            $rcp = ['id'=>$rcId,'amount'=>(float)$inv['total']];
            $n['receipt(demo parent)'] = 1;
        }
        $already = (float)$val("SELECT COALESCE(SUM(amount),0) FROM receipt_allocations WHERE receipt_id=?", [(int)$rcp['id']]);
        $free    = round((float)$rcp['amount'] - $already, 2);
        $alloc   = min($free, round((float)$inv['total'], 2));
        if ($alloc > 0) {
            $ins("INSERT INTO receipt_allocations (receipt_id,invoice_id,kind,amount,allocated_by,allocated_at)
                  VALUES (?,?, 'CASH', ?, 'demo', ?)", [(int)$rcp['id'],(int)$inv['id'],$alloc,$now]);
            $n['receipt_allocations'] = 1;
        }
    }

    // ---- credit_notes : a partial credit against the same invoice ----
    // prefer books_credit_note(['invoice_id'=>..,'reason'=>..,'subtotal'=>..]) — direct here for the seed.
    if ($inv && $has('credit_notes')) {
        $igst = (int)$inv['is_igst'] === 1;
        $sub  = 8000.0; $tax = round($sub * 0.18, 2);
        $cg = $igst ? 0 : round($sub*0.09,2); $sg = $igst ? 0 : round($tax-$cg,2); $ig = $igst ? $tax : 0;
        $ins("INSERT INTO credit_notes (cn_no,fy,invoice_id,partner_id,partner_name,office_id,cn_date,reason,reason_note,
              subtotal,cgst,sgst,igst,total,status,created_by,created_at)
              VALUES (?, '2026-27', ?,?,?,?,?, 'RATE_DIFF', ?, ?,?,?,?,?, 'ISSUED', 'demo', ?)",
            ['DEMO/CN/2627/0001',(int)$inv['id'],(int)$inv['partner_id'],(string)$inv['partner_name'],
             (int)$inv['office_id'],$d(-1),'DEMO — one vessel de-scoped, rate corrected',
             $sub,$cg,$sg,$ig,round($sub+$cg+$sg+$ig,2),$now]);
        $n['credit_notes'] = 1;
    }

    // ---- credit_recon : inter-office credit, one received + one given ----
    if ($has('credit_recon')) {
        $ic = "INSERT INTO credit_recon (month,ibo_office_id,client_id,boss_number,direction,credit_actual,notes,created_at)
               VALUES (?,?,?,?,?,?,?,?)";
        $ins($ic, ['2026-07',$oid['MUM'],$cid['CL-SVP'],'40881','RECEIVED',25000,'DEMO — Mundra deputation shared with Mumbai office',$now]);
        $ins($ic, ['2026-07',$oid['PUN'],$cid['CL-GHE'],'2201', 'GIVEN',   12000,'DEMO — Pune office covered a Chakan visit',$now]);
        $n['credit_recon'] = 2;
    }

    // ==================================================================
    //  BILLS — a deputation expense bill on an existing job
    // ==================================================================
    if ($has('job_bills')) {
        $bill = 'data:application/pdf;base64,' . base64_encode('%PDF-1.4 DEMO travel voucher');
        $ib = "INSERT INTO job_bills (job_id,head_code,bill_no,bill_date,amount,file_name,mime,file_data,note,uploaded_by,uploaded_at)
               VALUES (?,?,?,?,?,?, 'application/pdf', ?, ?, 'demo', ?)";
        $ins($ib, [$jid['J-2607-101'],'TRAVEL','TKT-2607-11',$d(-11),4200,'travel-voucher.pdf',$bill,'DEMO — Ahmedabad→Vapi return',$now]);
        $ins($ib, [$jid['J-2607-101'],'LODGING','HTL-2607-04',$d(-11),3100,'hotel-bill.pdf',$bill,'DEMO — one night, Vapi',$now]);
        $n['job_bills'] = 2;
    }

    // ==================================================================
    //  BILLING — a paid seat order (SaaS billing history)
    // ==================================================================
    if ($has('billing_orders')) {
        $ins("INSERT INTO billing_orders (seats,period,amount,currency,order_id,payment_id,paid_until,by_user,created_at)
              VALUES (?, 'year', ?, 'INR', ?, ?, ?, 'demo', ?)",
            [25,150000,'DEMO-order_Nabc123XYZ','DEMO-pay_Nxyz789ABC',$d(365),$now]);
        $n['billing_orders'] = 1;
    }

    // ==================================================================
    //  TALLY — one export batch: the invoice and its receipt as vouchers
    // ==================================================================
    if ($inv && $has('tally_exports')) {
        $it = "INSERT INTO tally_exports (batch_ref,kind,job_id,voucher_no,voucher_date,amount,exported_by,exported_at)
               VALUES ('DEMO-TB-2607-01', ?, ?, ?, ?, ?, 'demo', ?)";
        $ins($it, ['INVOICE',$jid['J-2607-101'],(string)$inv['invoice_no'],$d(-9),(float)$inv['total'],$now]);
        $ins($it, ['RCPT',   $jid['J-2607-101'],'DEMO/RCP/2627/0009',       $d(-2),(float)$inv['total'],$now]);
        $n['tally_exports'] = 2;
    }

    // ==================================================================
    //  COSTING — office overheads, a cost run and its allocations
    //  A run per office (AMD + MUM) so the SBU cost view spans offices.
    // ==================================================================
    if ($has('office_expenses')) {
        // head_id: 1=RENT 2=ELECTRICITY 3=INTERNET (office_expense_heads seed order)
        $ie = "INSERT INTO office_expenses (office_id,yr,mon,head_id,amount,notes,set_by,set_at)
               VALUES (?,2026,7,?,?,?, 'demo', ?)";
        $ins($ie, [$oid['AMD'],1,45000,'DEMO — Ahmedabad office rent, July',$now]);
        $ins($ie, [$oid['AMD'],2,12000,'DEMO — electricity & utilities',$now]);
        $ins($ie, [$oid['AMD'],3, 8000,'DEMO — internet & telephone (pending BM approval)',$now]);
        $n['office_expenses'] = 3;
    }

    if ($has('cost_runs') && $has('cost_allocations')) {
        $ia = "INSERT INTO cost_allocations
               (yr,mon,office_id,sbu,activity,boss_id,job_id,contract_number,source_kind,source_id,source_label,basis,amount,created_at)
               VALUES (2026,7,?,?,?,?,?,?,?,?,?,?,?,?)";
        $ir = "INSERT INTO cost_runs (yr,mon,office_id,status,total,note,run_by,run_at)
               VALUES (2026,7,?, 'OPEN', ?, ?, 'demo', ?)";

        // --- Ahmedabad run ---
        $amd = [
            [$oid['AMD'],'IND','PAYROLL',null,null,'',       'SALARY',   0,'DEMO — Inspector payroll share',        'HEADCOUNT',           240000],
            [$oid['AMD'],'IND','OVERHEAD',null,null,'',      'OFFICE_EXP',0,'DEMO — Office overheads (Ahmedabad)',  'EQUAL',                55000],
            [$oid['AMD'],'OGC','SUBCON',  null,$jid['J-2607-102'],'40881','SUBCON', 0,'DEMO — Sub-contractor (Mundra jetty)','THE_JOB_IT_WAS_FOR', 90000],
        ];
        $tAmd = 0; foreach ($amd as $r) { $ins($ia, array_merge($r, [$now])); $tAmd += $r[10]; }
        $ins($ir, [$oid['AMD'],round($tAmd,2),'DEMO — July overhead allocation',$now]);

        // --- Mumbai run (second office) ---
        $mum = [
            [$oid['MUM'],'OGC','PAYROLL',null,null,'', 'SALARY',    0,'DEMO — Inspector payroll share (Mumbai)','HEADCOUNT',180000],
            [$oid['MUM'],'OGC','OVERHEAD',null,null,'','OFFICE_EXP', 0,'DEMO — Office overheads (Mumbai)',       'EQUAL',     40000],
        ];
        $tMum = 0; foreach ($mum as $r) { $ins($ia, array_merge($r, [$now])); $tMum += $r[10]; }
        $ins($ir, [$oid['MUM'],round($tMum,2),'DEMO — July overhead allocation',$now]);

        $n['cost_allocations'] = count($amd) + count($mum);
        $n['cost_runs'] = 2;
    }

    // ==================================================================
    //  LICENCE ISSUE — one issued customer licence (provider console)
    // ==================================================================
    if ($has('issued_licences')) {
        $ins("INSERT INTO issued_licences (ref,customer,install_id,seats,exp,grace,mods,key_text,by_user,created_at)
              VALUES (?,?,?,?,?,?,?,?, 'demo', ?)",
            ['DEMO-LIC-260714-AB12CD','Narmada Industries Ltd','demo-install-01',15,$d(365),15,
             'CORE,REPORTS,BOOKS,COSTING','DEMO-KEY-eyJ0eXAiOiJKV1QiLCJhbGciOiJFUzI1NiJ9.demo.sig',$now]);
        $n['issued_licences'] = 1;
    }

    return $n;
}

function demo_seed_c4($pdo, $x, $has, $ins) {
    $oid = $x['oid']; $iid = $x['iid']; $cid = $x['cid']; $vid = $x['vid'];
    $callid = $x['callid']; $jid = $x['jid'];
    $now = $x['now']; $today = $x['today']; $d = $x['d'];
    $n = [];
    $val = function ($sql, $a = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($a); $v = $s->fetchColumn(); $s->closeCursor(); return $v; };
    $uid = function ($u) use ($val) { return ((int)$val("SELECT id FROM users WHERE username=? LIMIT 1", [$u])) ?: null; };

    // Whatever report type this install shipped with (the same lookup _b uses).
    $rt  = (int)$val("SELECT id FROM report_types WHERE active=1 ORDER BY sort_order, id LIMIT 1");
    $rtc = (string)$val("SELECT code FROM report_types WHERE id=?", [$rt]);

    // Approver users from the demo cast (resolved by username so ids stay honest).
    $uOpMgr  = $uid('opmanager');   // AMD operations manager — the everyday approver
    $uBMgr   = $uid('bmanager');    // AMD branch manager — the high-value sign-off
    $uAppMgr = $uid('appmanager');  // AMD — stands in when the BM is on leave
    $uAsst   = $uid('asstmgr');     // PUN assistant manager

    // A tiny .docx stub — the template LIST screen shows name/status/version, not
    // the bytes; a full document is not what is being demonstrated here.
    $stub = 'data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,UEsDBBQAAAAIAA==';

    // ------------------------------------------------------------------
    //  Client-specific report templates (Phase 5)  — /report-templates
    //  A published default, one still in review, and the version it replaced.
    // ------------------------------------------------------------------
    if ($has('report_templates')) {
        $it = "INSERT INTO report_templates
               (name,report_type_id,client_id,office_id,file_name,file_data,document_number,format_number,doc_revision,
                issue_date,active,is_default,status,version,effective_date,superseded_by,published_at,published_by,
                submitted_at,submitted_by,reviewed_at,reviewed_by,review_note,created_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)";
        // Published, default — the one a Narmada final report renders through.
        $tpl1 = $ins($it, ['Final Inspection Report — Narmada client template',$rt,$cid['CL-NIL'],$oid['AMD'],
            'narmada-final-inspection-v3.docx',$stub,'MGH/FMT/IDEMS/012','F-012','3',$d(-120),1,1,'PUBLISHED',3,$d(-120),
            null,$d(-120).'T10:00:00+05:30','Sunil Verma',$d(-124).'T15:00:00+05:30','Priya Nair',
            $d(-121).'T11:30:00+05:30','Sunil Verma','',$now]);
        // In review — submitted, sitting with the reviewer. The edge nobody demos.
        $ins($it, ['Coating Inspection Report — Girnar (draft)',$rt,$cid['CL-GHE'],$oid['MUM'],
            'girnar-coating-inspection-v1.docx',$stub,'MGH/FMT/IDEMS/019','F-019','1','',1,0,'IN_REVIEW',1,'',
            null,'','',$d(-3).'T17:20:00+05:30','Priya Nair','','','',$now]);
        // The version the published one replaced — kept, inactive, superseded.
        $ins($it, ['Final Inspection Report — Narmada client template (v2)',$rt,$cid['CL-NIL'],$oid['AMD'],
            'narmada-final-inspection-v2.docx',$stub,'MGH/FMT/IDEMS/012','F-012','2',$d(-320),0,0,'SUPERSEDED',2,$d(-320),
            $tpl1,$d(-320).'T10:00:00+05:30','Sunil Verma',$d(-324).'T15:00:00+05:30','Priya Nair',
            $d(-321).'T11:30:00+05:30','Sunil Verma','Replaced by rev 3 — client changed the letterhead.',$now]);
        $n['report_templates'] = 3;
    }

    // ------------------------------------------------------------------
    //  Approver mapping (who signs which engineer's reports)  — /approver-map
    //  One row per inspector; UNIQUE(inspector_id), so guard before insert.
    // ------------------------------------------------------------------
    if ($has('idems_approver_map')) {
        $im = "INSERT INTO idems_approver_map (inspector_id,approver_user_id,temp_user_id,temp_from,temp_to,active,updated_at)
               VALUES (?,?,?,?,?,1,?)";
        $mapRow = function ($inspId, $appr, $tmp = null, $from = '', $to = '') use ($val, $ins, $im, $now) {
            if (!$inspId) return 0;
            if ($val("SELECT id FROM idems_approver_map WHERE inspector_id=?", [$inspId])) return 0;
            $ins($im, [$inspId, $appr, $tmp, $from, $to, $now]); return 1;
        };
        $c = 0;
        $c += $mapRow($iid['EMP01'] ?? 0, $uOpMgr);                               // Ravi  → Ops Manager (AMD)
        $c += $mapRow($iid['EMP02'] ?? 0, $uBMgr, $uAppMgr, $d(-2), $d(5));       // Anil  → BM, delegated to App Mgr while on leave
        $c += $mapRow($iid['EMP03'] ?? 0, $uAsst);                               // Priya → Asst Manager (PUN)
        $c += $mapRow($iid['SC-001'] ?? 0, $uOpMgr);                             // Mohan (subcon) → Ops Manager
        $n['idems_approver_map'] = $c;
    }

    // ------------------------------------------------------------------
    //  Approval-chain rules  — /idems-approval-rules   (name-prefixed 'DEMO —')
    // ------------------------------------------------------------------
    if ($has('idems_approval_rules')) {
        $ir = "INSERT INTO idems_approval_rules
               (name,active,report_type_code,office_id,client_id,sbu,level,approver_kind,approver_user_id,approver_role,sla_hours,sort_order,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $ins($ir, ['DEMO — L1 technical approval (AMD)',1,'',$oid['AMD'],null,'',1,'INSPECTOR_MAP',null,'',24,10,$now]);
        $ins($ir, ['DEMO — L2 branch-manager sign-off (high value)',1,'',$oid['AMD'],null,'',2,'USER',$uBMgr,'',48,20,$now]);
        $n['idems_approval_rules'] = 2;
    }

    // ------------------------------------------------------------------
    //  Per-office IRN serial counters  (numbering engine; UNIQUE(scope_key))
    //  No clean setter — direct INSERT only. (idems_next_serial() opens its own
    //  transaction and would nest inside the seed's; do NOT call it here.)
    // ------------------------------------------------------------------
    if ($has('idems_counters')) {
        $ic = "INSERT INTO idems_counters (scope_key,last_serial,updated_at) VALUES (?,?,?)";
        $ctr = function ($k, $s) use ($val, $ins, $ic, $now) {
            if ($val("SELECT id FROM idems_counters WHERE scope_key=?", [$k])) return 0;
            $ins($ic, [$k, $s, $now]); return 1;
        };
        $c = 0;
        $c += $ctr('MGH/AMD/2026', 4);
        $c += $ctr('MGH/PUN/2026', 3);
        $c += $ctr('MGH/MUM/2026', 1);
        $n['idems_counters'] = $c;
    }

    // ------------------------------------------------------------------
    //  The rest attach to EXISTING demo report_docs (seeded by _b). Look one up;
    //  if a part-built install has none, stand up a minimal report_doc so the
    //  child registers still have a parent.  §guard-for-0
    // ------------------------------------------------------------------
    if ($has('report_docs')) {
        $docAny = (int)$val("SELECT id FROM report_docs WHERE created_by='demo' ORDER BY id LIMIT 1");
        if (!$docAny) {
            $docAny = $ins("INSERT INTO report_docs (irn,report_type_id,type_code,title,client_id,office_id,sbu,
                            inspector_id,inspection_date,result,release_status,status,rev,finalized,created_by,created_at)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,1,'demo',?)",
                ['MGH/AMD/2026/DEMO-C-0001',$rt,$rtc,'Demo report (module-C parent)',$cid['CL-NIL'],$oid['AMD'],'IND',
                 $iid['EMP01'] ?? null,$d(-12),'ACCEPTED','RELEASED','ISSUED',$now]);
        }
        // Prefer the report already in each state so the screens read naturally.
        $docSub    = (int)$val("SELECT id FROM report_docs WHERE created_by='demo' AND status='SUBMITTED' ORDER BY id LIMIT 1") ?: $docAny;
        $docIssued = (int)$val("SELECT id FROM report_docs WHERE created_by='demo' AND finalized=1 ORDER BY id LIMIT 1") ?: $docAny;
        $docRej    = (int)$val("SELECT id FROM report_docs WHERE created_by='demo' AND result='REJECTED' AND finalized=1 ORDER BY id LIMIT 1") ?: $docIssued;

        // ---- Approval steps  — shown on /document?id=…  (register: /idems-approval-rules escalations) ----
        if ($has('report_approvals')) {
            $c = 0;
            // A report AWAITING approval. PREFER idems_build_approval_chain(): it
            // reads the rules + approver map we just seeded and writes the PENDING
            // step(s) itself (DELETE+INSERT, no transaction — safe inside the seed).
            $built = false;
            if (function_exists('idems_build_approval_chain')) {
                $subDoc = $val ? $pdo->query("SELECT * FROM report_docs WHERE id=" . (int)$docSub)->fetch(PDO::FETCH_ASSOC) : null;
                if ($subDoc) { try { idems_build_approval_chain($subDoc); $built = true; } catch (Throwable $e) { $built = false; } }
            }
            $ra = "INSERT INTO report_approvals
                   (report_doc_id,level,approver_kind,approver_role,approver_user_id,resolved_user_id,status,acted_by,acted_at,remarks,sla_due,escalated,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
            // Direct-INSERT fallback for the pending step (only if the FUNC path did nothing).
            if (!$built && !$val("SELECT id FROM report_approvals WHERE report_doc_id=?", [$docSub])) {
                $ins($ra, [$docSub,1,'INSPECTOR_MAP','',$uAsst,$uAsst,'PENDING','','','', $d(1).'T18:00:00+05:30',0,$now]);
                $c++;
            }
            // A COMPLETED two-level chain on the issued report — the happy path.
            if (!$val("SELECT id FROM report_approvals WHERE report_doc_id=?", [$docIssued])) {
                $ins($ra, [$docIssued,1,'INSPECTOR_MAP','',$uOpMgr,$uOpMgr,'APPROVED','Suresh (Ops Manager)',$d(-10).'T09:30:00+05:30','Technical review clear.', $d(-11).'T09:00:00+05:30',0,$now]);
                $ins($ra, [$docIssued,2,'USER','',$uBMgr,$uBMgr,'APPROVED','Branch Manager',$d(-10).'T12:15:00+05:30','Approved for issue.', $d(-10).'T09:00:00+05:30',0,$now]);
                $c += 2;
            }
            $n['report_approvals'] = $c;
        }

        // ---- Per-document review states (MTC/QAP/WPS/NDT)  — shown on /document?id=… ----
        //  PREFER idems_doc_review_set($docId,$type,$state,$note); direct INSERT below is the fallback.
        if ($has('report_doc_review')) {
            $rows = [
                ['MTC','RECEIVED','Mill certs verified against the specification.'],
                ['QAP','RECEIVED','Approved QAP rev C on file.'],
                ['WPS','RECEIVED_OBS','WPS supplied but PQR reference not quoted — observation raised.'],
                ['NDT_REPORT','AWAITED','UT report for the last two joints still to come from the vendor.'],
            ];
            if (function_exists('idems_doc_review_set')) {
                foreach ($rows as $r) idems_doc_review_set($docIssued, $r[0], $r[1], $r[2]);
            } else {
                $rr = "INSERT INTO report_doc_review (report_doc_id,doc_type,state,note,reviewed_by,reviewed_at) VALUES (?,?,?,?,?,?)";
                foreach ($rows as $r) $ins($rr, [$docIssued,$r[0],$r[1],$r[2],'Priya Nair',$now]);
            }
            $n['report_doc_review'] = count($rows);
        }

        // ---- Technical vetting / debriefing log  — shown on /document?id=… ----
        //  (idems_vet_action() needs current_user(); seed writes rows directly.)
        if ($has('report_vetting')) {
            $rv = "INSERT INTO report_vetting (report_doc_id,stage,action,note,acted_by,acted_at) VALUES (?,?,?,?,?,?)";
            // First returned with an observation, then vetted clear after correction.
            $ins($rv, [$docSub,'VET','RETURNED','Observation: NDT extent not stated for nozzle welds — add before approval.','Sunil Verma',$d(-7).'T11:00:00+05:30']);
            $ins($rv, [$docSub,'VET','VETTED','Correction made; technically vetted and released to the approver.','Sunil Verma',$d(-6).'T15:30:00+05:30']);
            try { $pdo->prepare("UPDATE report_docs SET vet_status='VETTED', vet_at=?, vet_by=? WHERE id=?")
                      ->execute([$d(-6).'T15:30:00+05:30','Sunil Verma',$docSub]); } catch (Throwable $e) { /* pre-Phase-4 */ }
            $n['report_vetting'] = 2;
        }

        // ---- Client acceptance / rejection  — /report-reviews ----
        //  (rcr_decide() drives NCR creation + notifications for the live flow;
        //   the seed writes the recorded decisions directly.)
        if ($has('report_client_reviews')) {
            $rc = "INSERT INTO report_client_reviews
                   (report_doc_id,rev,partner_id,client_user_id,decided_by_name,decided_by_email,decision,reason,note,
                    ncr_id,ncr_ref,ack_by,ack_at,ack_note,ip,decided_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            // Accepted — and acknowledged at our end.
            $ins($rc, [$docIssued,0,$cid['CL-NIL'],null,'Rakesh Menon','buyer@narmada.example','ACCEPTED','','',
                       null,'','Priya Nair',$d(-9).'T10:00:00+05:30','Noted, report closed.','203.0.113.11',$d(-9).'T09:20:00+05:30']);
            // REJECTED with a comment (reason=DETAILS) — the outcome nobody demos.
            //  Not yet acknowledged, so it sits on the register as an open rejection.
            $ins($rc, [$docRej,0,$cid['CL-SVP'],null,'S. Vasant','buyer@svp.example','REJECTED','DETAILS',
                       "PO number on the cover is PO-SVP-40881 but the body quotes 40818. Please correct and re-issue.",
                       null,'','','', '','203.0.113.24',$d(-7).'T16:40:00+05:30']);
            $n['report_client_reviews'] = 2;
        }

        // ---- Release Note issued, linked to the inspection it releases  — /release-notes ----
        if ($has('release_inspections')) {
            $relDoc = (int)$val("SELECT id FROM report_docs WHERE created_by='demo' AND type_code='RN' ORDER BY id LIMIT 1");
            if (!$relDoc) {
                $relDoc = $ins("INSERT INTO report_docs (irn,report_type_id,type_code,title,client_id,office_id,sbu,
                                inspector_id,inspection_date,result,release_status,status,rev,finalized,release_of_id,created_by,created_at)
                                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,1,?, 'demo', ?)",
                    ['MGH/AMD/2026/RN-0001',$rt,'RN','Release Note — 12 pressure vessels',$cid['CL-NIL'],$oid['AMD'],'IND',
                     $iid['EMP01'] ?? null,$d(-10),'ACCEPTED','RELEASED','ISSUED',$docIssued,$now]);
            }
            // PREFER urade_link_inspection() (idempotent per pair; also sets release_of_id).
            if (function_exists('urade_link_inspection')) {
                urade_link_inspection($relDoc, $docIssued, true);
            } elseif (!$val("SELECT id FROM release_inspections WHERE release_doc_id=? AND inspection_doc_id=?", [$relDoc,$docIssued])) {
                $ins("INSERT INTO release_inspections (release_doc_id,inspection_doc_id,is_primary,created_at) VALUES (?,?,1,?)",
                     [$relDoc,$docIssued,$now]);
            }
            $n['release_inspections'] = 1;
        }
    }

    // ------------------------------------------------------------------
    //  Job QAP uploaded against a PO line  — shown on the job page (/job?id=…)
    // ------------------------------------------------------------------
    if ($has('job_qaps')) {
        $ins("INSERT INTO job_qaps (job_id,po_line,file_name,mime,data,note,uploaded_by,uploaded_at) VALUES (?,?,?,?,?,?,?,?)",
            [$jid['J-2607-101'],'Line 1 — 12 pressure vessels, ASME VIII Div 1','QAP-NIL-vessels-revC.pdf','application/pdf',
             'data:application/pdf;base64,JVBERi0xLjQK','Approved QAP rev C — witness/hold points marked.','demo',$now]);
        $n['job_qaps'] = 1;
    }

    // ------------------------------------------------------------------
    //  Endorsement supporting file (the manufacturer's original)  — /endorsement?id=…
    // ------------------------------------------------------------------
    if ($has('endorsement_files')) {
        $eid = (int)$val("SELECT id FROM endorsements WHERE created_by='demo' ORDER BY id LIMIT 1");
        if ($eid) {
            $ins("INSERT INTO endorsement_files (endorsement_id,kind,file_name,mime,data,note,created_by,created_at)
                  VALUES (?,?,?,?,?,?, 'demo', ?)",
                [$eid,'original','MTC-SA516-Gr70-HT88213.pdf','application/pdf','data:application/pdf;base64,JVBERi0xLjQK',
                 'Mill test certificate as received from the manufacturer.',$now]);
            $n['endorsement_files'] = 1;
        }
    }

    // ------------------------------------------------------------------
    //  Learned phrase suggestions  — /learning   (norm_key prefixed 'demo:')
    //  UNIQUE(norm_key), so guard.  (idems_learn_note() is the live writer.)
    // ------------------------------------------------------------------
    if ($has('learned_suggestions')) {
        $ls = "INSERT INTO learned_suggestions (scope,report_type_id,client_id,field_key,text_value,norm_key,uses,last_seen,muted,created_at)
               VALUES (?,?,?,?,?,?,?,?,0,?)";
        $learn = function ($scope, $client, $field, $text, $key, $uses) use ($val, $ins, $ls, $rt, $now) {
            if ($val("SELECT id FROM learned_suggestions WHERE norm_key=?", [$key])) return 0;
            $ins($ls, [$scope, $rt, $client, $field, $text, $key, $uses, $now, $now]); return 1;
        };
        $c = 0;
        $c += $learn('FIELD', null, 'inspection_result', 'Accepted — released for despatch', 'demo:field:accepted-released', 7);
        $c += $learn('REMARK', null, 'remarks', 'All witnessed points cleared; records verified against the approved QAP.', 'demo:remark:witnessed-cleared', 4);
        $c += $learn('CLIENT', $cid['CL-NIL'], 'inspection_result', 'Accepted with observations (see note)', 'demo:client:nil-accepted-obs', 2);
        $n['learned_suggestions'] = $c;
    }

    return $n;
}

function demo_seed_c5($pdo, $x, $has, $ins) {
    $oid = $x['oid']; $iid = $x['iid']; $cid = $x['cid']; $vid = $x['vid'];
    $callid = $x['callid']; $jid = $x['jid'];
    $now = $x['now']; $d = $x['d'];
    $n = [];
    $val = function ($sql, $a = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($a); $v = $s->fetchColumn(); $s->closeCursor(); return $v; };

    // ------------------------------------------------------------------
    //  Inspector certificates (§6.1). One EXPIRING SOON, one already lapsed.
    //  prefer inspector_cert_add() exists but reads $_FILES/$_POST and forces
    //  status — direct INSERT is what lets us set the edge-case validity dates.
    //  Provenance: updated_by='demo' (no created_by column on this table).
    // ------------------------------------------------------------------
    if ($has('inspector_certs')) {
        $ic = "INSERT INTO inspector_certs
               (inspector_id,name,number,issued_date,valid_from,valid_to,status,is_mandatory,file_name,file_mime,file_data,updated_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)";
        $ins($ic, [$iid['EMP01'],'CSWIP 3.1 Welding Inspector','CSWIP/3.1/778120',$d(-400),$d(-400),$d(420),'VALID',1,'','','',$now]);
        // Expires in 20 days — the certificate register should be warning about it.
        $ins($ic, [$iid['EMP02'],'AMPP/NACE Coating Inspector Level 2','AMPP-CIP2-55210',$d(-1000),$d(-1000),$d(20),'VALID',1,'','','',$now]);
        $ins($ic, [$iid['EMP03'],'ASNT NDT Level II — UT','ASNT-UT2-40988',$d(-300),$d(-300),$d(500),'VALID',1,'','','',$now]);
        // Already lapsed, and he is a sub-contractor — the combination that gets missed.
        $ins($ic, [$iid['SC-001'],'Rigging & Lifting Supervisor','RLS-2024-3312',$d(-800),$d(-800),$d(-25),'EXPIRED',1,'','','',$now]);
        $n['inspector_certs'] = 4;
    }

    // ------------------------------------------------------------------
    //  Test/inspection methods (ISO/IEC 17025 §7.2). prefer method_save() —
    //  but it forces status=DRAFT and uses current_user(); direct INSERT lets
    //  us seed CURRENT/validated rows and an overdue review.
    // ------------------------------------------------------------------
    $m1 = $m2 = null;
    if ($has('methods')) {
        $im = "INSERT INTO methods
               (method_code,title,standard_ref,revision,category,discipline,status,effective_date,review_due,owner,description,is_validated,validated_on,validated_by,validation_note,created_by,created_at,updated_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?, ?)";
        $m1 = $ins($im, ['MTH-UT-01','Ultrasonic thickness measurement of pressure parts','ASTM E797 / ISO 16809','Rev 2','NDT','MECHANICAL','CURRENT',$d(-200),$d(160),'Meena Shah','Contact-type UT wall-thickness measurement on carbon-steel pressure parts.',1,$d(-190),'Sunil Verma','Validated against a certified step wedge; bias within ±0.1 mm.',$now,$now]);
        // Review date already PAST — the method register should flag it for review.
        $m2 = $ins($im, ['MTH-VT-02','Visual inspection of welds','ISO 17637','Rev 1','NDT','WELDING','CURRENT',$d(-180),$d(-12),'Meena Shah','Direct and remote visual examination of fusion welds.',1,$d(-175),'Sunil Verma','',$now,$now]);
        // Still a draft, not validated.
        $ins($im, ['MTH-DFT-03','Dry film thickness of protective coatings','ISO 19840 / SSPC-PA2','Rev 0','COATING','COATING','DRAFT',$d(-20),$d(340),'Anil Sharma','DFT of protective coatings on structural steel.',0,'','','',$now,$now]);
        $n['methods'] = 3;
    }

    // ------------------------------------------------------------------
    //  Decision rules — acceptance criteria with a guard band (ILAC-G8).
    //  prefer drule_save() (same caveat: forces DRAFT + current_user()).
    // ------------------------------------------------------------------
    if ($has('decision_rules')) {
        $idr = "INSERT INTO decision_rules
                (rule_code,title,method_id,report_type_id,characteristic,accept_criteria,reject_criteria,uncertainty_rule,decision_basis,status,owner,notes,created_by,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?, ?)";
        $ins($idr, ['DR-UT-01','Wall-thickness acceptance — pressure-vessel shell',$m1,null,'Minimum wall thickness',
                    'Measured thickness ≥ 12.0 mm (nominal 12.5 mm, corrosion allowance 0.5 mm).',
                    'Any reading below the acceptance limit after the guard band is applied.',
                    'Guarded acceptance: expanded uncertainty U = 0.2 mm (k=2). Accept only if reading ≥ 12.0 + 0.2 = 12.2 mm.',
                    'GUARD_BAND (ILAC-G8)','CURRENT','Meena Shah','Agreed with client engineering for the Narmada vessels.',$now,$now]);
        // Draft rule, simple acceptance (no account of uncertainty).
        $ins($idr, ['DR-VT-02','Visual weld acceptance — no cracks',$m2,null,'Surface condition',
                    'No cracks; undercut ≤ 0.5 mm per ISO 5817 level C.','Any crack, or undercut > 0.5 mm.',
                    'Simple acceptance — decision takes no account of measurement uncertainty (visual).',
                    'SIMPLE_ACCEPTANCE','DRAFT','Sunil Verma','',$now,$now]);
        $n['decision_rules'] = 2;
    }

    // ------------------------------------------------------------------
    //  Controlled documents (§8.3). No PENDING_REVIEW status exists
    //  (DRAFT/CURRENT/SUPERSEDED/WITHDRAWN) — a doc "pending review" is a
    //  CURRENT one whose review_due has passed; a DRAFT is pending approval.
    // ------------------------------------------------------------------
    if ($has('controlled_docs')) {
        $icd = "INSERT INTO controlled_docs
                (doc_code,title,doc_type,revision,status,owner,approved_by,approved_on,effective_date,review_due,distribution,description,file_name,mime,file_data,supersedes_id,superseded_by_id,created_by,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?, ?)";
        $ins($icd, ['QM-01','Quality Manual','MANUAL','Rev 5','CURRENT','Meena Shah','A. Desai (Technical Head)',$d(-150),$d(-150),$d(210),'All staff; controlled soft copy on the server.','Top-level quality manual to ISO/IEC 17020.','','','',null,null,$now,$now]);
        // CURRENT but its review fell due 10 days ago — pending review.
        $ins($icd, ['SOP-INS-04','SOP — Third-party inspection at works','SOP','Rev 3','CURRENT','Sunil Verma','A. Desai',$d(-120),$d(-120),$d(-10),'Inspection team.','Method-independent works-inspection procedure.','','','',null,null,$now,$now]);
        // Draft, pending approval — must not read as effective.
        $ins($icd, ['FRM-REP-11','Form — Inspection report','FORM','Rev 0','DRAFT','Anil Sharma','','','','','','New report form, pending review and approval.','','','',null,null,$now,$now]);
        $n['controlled_docs'] = 3;
    }

    // ------------------------------------------------------------------
    //  Risk & opportunity register (§8.5). prefer risk_save().
    // ------------------------------------------------------------------
    if ($has('risk_items')) {
        $iri = "INSERT INTO risk_items
                (risk_code,kind,title,category,context,description,likelihood,impact,rating,treatment,owner,status,review_due,office_id,sbu,created_by,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?, ?)";
        $ins($iri, ['RSK-01','RISK','Loss of impartiality under client pressure','IMPARTIALITY','ISO/IEC 17020 §4.1','Client asks for a rejected item to be re-graded before despatch.','M','H','High','Two-person review of adverse decisions; impartiality committee reviews threats quarterly.','Meena Shah','TREATING',$d(60),$oid['AMD'],'IND',$now,$now]);
        $ins($iri, ['RSK-02','RISK','Calibration lapse on field instruments','COMPETENCE','§6.2 measuring equipment','An instrument is used past its calibration due date.','L','H','Medium','Calibration-due register with 30-day reminders; instrument withdrawn on lapse.','Sunil Verma','MONITORING',$d(90),$oid['AMD'],'IND',$now,$now]);
        $ins($iri, ['OPP-01','OPPORTUNITY','Extend NDT scope to advanced UT (PAUT)','GROWTH','Market demand','Clients asking for phased-array UT reporting.','M','M','Medium','Assess certification and equipment; pilot with one client.','A. Desai','OPEN',$d(120),$oid['MUM'],'OGC',$now,$now]);
        $n['risk_items'] = 3;
    }

    // ------------------------------------------------------------------
    //  Samples and their chain of custody (ISO/IEC 17025 sampling).
    //  prefer sample_save()+sample_event() (they force status=RECEIVED and
    //  write custody as current_user()); direct INSERT keeps the demo dates.
    // ------------------------------------------------------------------
    if ($has('sample_items')) {
        $isi = "INSERT INTO sample_items
                (item_code,partner_id,call_id,job_id,description,item_type,maker_ref,quantity,unit,received_on,received_by,condition_code,condition_note,storage_code,status,disposition,closed_on,closed_by,closed_note,office_id,sbu,notes,created_by,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?, ?)";
        $s1 = $ins($isi, ['SMP-26-0001',$cid['CL-NIL'],$callid['C-2607-001'],$jid['J-2607-101'],'Weld coupon — vessel 1 longitudinal seam','WELD_COUPON','HT-88213','2','pcs',$d(-11),'Ravi Kumar','GOOD','Received intact, tagged.','RACK-A2','IN_TESTING','','','','',$oid['AMD'],'IND','Destructive check at client request.',$now,$now]);
        $s2 = $ins($isi, ['SMP-26-0002',$cid['CL-SVP'],$callid['C-2607-002'],$jid['J-2607-102'],'Weld consumable sample — E7018 batch','CONSUMABLE','HT-91004','1','packet',$d(-9),'Anil Sharma','GOOD','','RACK-B1','RETURNED','RETURN',$d(-2),'Anil Sharma','Returned to client after review.',$oid['AMD'],'OGC','',$now,$now]);
        $n['sample_items'] = 2;

        if ($has('sample_custody')) {
            $isc = "INSERT INTO sample_custody (sample_id,at,action,actor,location,note) VALUES (?,?,?,?,?,?)";
            $ins($isc, [$s1,$d(-11).'T10:15:00+05:30','RECEIVED','Ravi Kumar','AMD lab — RACK-A2','Sealed bag, tag SMP-26-0001.']);
            $ins($isc, [$s1,$d(-8).'T09:40:00+05:30','MOVED','Priya Nair','AMD lab — testing bay','Moved for hardness survey.']);
            $ins($isc, [$s1,$d(-8).'T15:00:00+05:30','IN_TESTING','Priya Nair','AMD lab — testing bay','Hardness and macro in progress.']);
            $ins($isc, [$s2,$d(-9).'T11:00:00+05:30','RECEIVED','Anil Sharma','AMD lab — RACK-B1','']);
            $ins($isc, [$s2,$d(-2).'T16:20:00+05:30','RETURNED','Anil Sharma','Client representative','Handed back, signed for.']);
            $n['sample_custody'] = 5;
        }
    }

    // ------------------------------------------------------------------
    //  Customer satisfaction surveys (§7 feedback). One low score + follow-up.
    //  prefer sat_request()/sat_record() (force status + current_user()).
    //  Scale is 1..sat_scale() (default 5).
    // ------------------------------------------------------------------
    if ($has('satisfaction_surveys')) {
        $iss = "INSERT INTO satisfaction_surveys
                (survey_code,client_id,about,job_id,report_ref,office_id,sbu,status,requested_on,requested_by,respondent,respondent_role,received_on,score,recommend,aspects,comments,followup_needed,followup_note,followup_done,created_by,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?, ?)";
        $ins($iss, ['CSS-26-0001',$cid['CL-NIL'],'Final inspection — 12 pressure vessels',$jid['J-2607-101'],'MGH/AMD/2026/D-0001',$oid['AMD'],'IND','RECEIVED',$d(-8),'Meena Shah','Rakesh Menon','Project Manager',$d(-5),5,'Y',json_encode(['timeliness'=>5,'technical'=>5,'communication'=>4,'professionalism'=>5]),'Very responsive team; clean records.',0,'',0,$now,$now]);
        // Low score (2 of 5), recommend=N, follow-up flagged and not yet done.
        $ins($iss, ['CSS-26-0002',$cid['CL-SVP'],'Stage inspection — jetty fabrication',$jid['J-2607-102'],'MGH/AMD/2026/D-0002',$oid['AMD'],'OGC','RECEIVED',$d(-6),'Meena Shah','Sunita Rao','QA Lead',$d(-3),2,'N',json_encode(['timeliness'=>2,'technical'=>3,'communication'=>2,'professionalism'=>3]),'Report turnaround slow; communication patchy.',1,'BM to call the client and agree a recovery plan.',0,$now,$now]);
        // Requested only — no response yet (score NULL).
        $ins($iss, ['CSS-26-0003',$cid['CL-GHE'],'Coating inspection — storage tanks',null,'',$oid['MUM'],'OGC','REQUESTED',$d(-2),'Meena Shah','','',null,'','','','',0,'',0,$now,$now]);
        $n['satisfaction_surveys'] = 3;
    }

    // ------------------------------------------------------------------
    //  Security incidents (DPDP / CERT-In). One OPEN, one CLOSED+reported.
    //  prefer nothing clean — direct INSERT (the route builds columns from $_POST).
    // ------------------------------------------------------------------
    // Each register below runs in its own guard so a single failing INSERT
    // (a strict-MySQL rejection, a missing column, a null FK) can only blank its
    // OWN register — never cascade and take the next four down with it — and it
    // records the exact reason, which the Settings coverage board then shows.
    $seedBlock = function (string $table, callable $fn) use (&$n) {
        try { $fn(); }
        catch (Throwable $e) { $GLOBALS['__seedc_fail'][] = $table . ': ' . $e->getMessage(); }
    };
    if ($has('security_incidents')) $seedBlock('security_incidents', function () use ($ins, $d, $now, &$n) {
        $ise = "INSERT INTO security_incidents
                (ref,detected_at,kind,severity,summary,systems,people_affected,data_kinds,immediate_action,root_cause,certin_reported_at,certin_ref,dpb_reported_at,people_told_at,status,closed_at,created_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)";
        // OPEN — still being worked.
        $ins($ise, ['SEC-2026-001',$d(-3).'T22:10:00+05:30','PHISHING','HIGH','A finance user entered credentials on a fake invoice-portal page.','Microsoft 365 mailbox (finance)',1,'E-mail contents; no client reports exposed.','Password reset; MFA enforced; inbox rules reviewed.','','','','','','OPEN','',$now]);
        // CLOSED, CERT-In reported, people told.
        $ins($ise, ['SEC-2026-002',$d(-40).'T09:00:00+05:30','LOST_DEVICE','MEDIUM','An inspector laptop was lost in transit; disk was encrypted.','Field laptop',0,'Cached inspection reports (encrypted at rest).','Remote wipe issued; device certificate revoked.','Bag left in a taxi.',$d(-39).'T14:00:00+05:30','CERTIN-2026-4471','',$d(-38),'CLOSED',$d(-10),$now]);
        $n['security_incidents'] = 2;
    });

    // ------------------------------------------------------------------
    //  Site document requirements (client gate/access docs). doc_kind must be
    //  one of IDDOC_KINDS (GATE_PASS/MEDICAL/POLICE_VER/WORK_PERMIT/...).
    // ------------------------------------------------------------------
    if ($has('site_doc_requirements')) {
        $isd = "INSERT INTO site_doc_requirements
                (partner_id,site_address_id,doc_kind,is_mandatory,valid_days_after,note,created_by,created_at)
                VALUES (?,?,?,?,?,?, 'demo', ?)";
        $ins($isd, [$cid['CL-NIL'],null,'GATE_PASS',1,0,'Gate pass required at the Vapi works; passport on the gate list.',$now]);
        $ins($isd, [$cid['CL-SVP'],null,'MEDICAL',1,365,'Medical fitness certificate for Mundra SEZ, valid one year.',$now]);
        $ins($isd, [$cid['CL-GHE'],null,'POLICE_VER',0,180,'Police verification for refinery entry (preferred, not mandatory).',$now]);
        $n['site_doc_requirements'] = 3;
    }

    // ------------------------------------------------------------------
    //  Confidentiality: staff undertakings, client NDAs, and a breach.
    //  prefer the confidentiality route handlers ($_POST/$_FILES + current_user);
    //  conf_breach_create() ALSO auto-raises an NCR — direct INSERT avoids that
    //  side effect, so seed via the route only if you want the linked NCR.
    // ------------------------------------------------------------------
    if ($has('confidentiality_undertakings')) {
        $icu = "INSERT INTO confidentiality_undertakings
                (person_kind,person_id,person_name,kind,wording_ref,signed_on,valid_to,file_name,mime,file_data,note,recorded_by,created_at)
                VALUES ('INSPECTOR',?,?,?,?,?,?,?,?,?,?, 'demo', ?)";
        // Signed and in force.
        $ins($icu, [$iid['EMP01'],'Ravi Kumar','EMPLOYEE','CU-EMP Rev2',$d(-380),$d(350),'','','','Signed on joining.',$now]);
        // Sub-contractor undertaking lapsed 5 days ago.
        $ins($icu, [$iid['SC-001'],'Mohan Rao','SUBCON','CU-SUB Rev1',$d(-200),$d(-5),'','','','Agency undertaking lapsed — renewal pending.',$now]);
        $n['confidentiality_undertakings'] = 2;
    }
    if ($has('client_ndas')) $seedBlock('client_ndas', function () use ($ins, $cid, $d, $now, &$n) {
        $ind = "INSERT INTO client_ndas
                (partner_id,title,signed_on,valid_to,survives_months,terms,file_name,mime,file_data,recorded_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?, 'demo', ?)";
        $ins($ind, [$cid['CL-NIL'],'Mutual NDA — Narmada Industries',$d(-300),$d(430),24,'Mutual confidentiality; survives 24 months after expiry.','','','',$now]);
        $ins($ind, [$cid['CL-SVP'],'NDA — Suryavan Ports project',$d(-90),$d(640),36,'One-way NDA covering project drawings and reports.','','','',$now]);
        $n['client_ndas'] = 2;
    });
    if ($has('confidentiality_breaches')) $seedBlock('confidentiality_breaches', function () use ($ins, $cid, $jid, $oid, $d, $now, &$n) {
        $icb = "INSERT INTO confidentiality_breaches
                (ref,kind,partner_id,job_id,office_id,happened_on,discovered_on,what_got_out,who_saw_it,by_whom,containment,party_told,party_told_note,regulator_told,status,raised_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)";
        // OPEN — under investigation.
        $ins($icb, ['CB-2026-001','WRONG_RECIPIENT',$cid['CL-NIL'],$jid['J-2607-101'],$oid['AMD'],$d(-6),$d(-5),
                    'A draft inspection report for Narmada was e-mailed to a similarly named contact at another company.',
                    'One external recipient (wrong domain).','Sana Kapoor',
                    'Recipient asked to delete and confirm; awaiting confirmation.','NO','Assessing severity before deciding on notification.','NO','OPEN',$now]);
        $n['confidentiality_breaches'] = 1;
    });

    // ------------------------------------------------------------------
    //  DPDP consent register and data-subject requests (DSAR).
    // ------------------------------------------------------------------
    if ($has('data_consents')) $seedBlock('data_consents', function () use ($ins, $cid, $iid, $d, &$n) {
        $idc = "INSERT INTO data_consents
                (subject_kind,subject_id,subject_name,purpose,basis,given_at,withdrawn_at,note,recorded_by)
                VALUES (?,?,?,?,?,?,?,?, 'demo')";
        $ins($idc, ['CONTACT',$cid['CL-NIL'],'Rakesh Menon','Sending inspection reports and invoices by e-mail.','CONTRACT',$d(-120),'','Captured at contract signing.']);
        $ins($idc, ['STAFF',$iid['EMP01'],'Ravi Kumar','Holding passport details for client site gate passes.','CONSENT',$d(-90),'','Written consent, withdrawable at any time.']);
        // Withdrawn consent — the trigger to review what was kept.
        $ins($idc, ['CONTACT',$cid['CL-GHE'],'Deepa Balan','Marketing updates about services.','CONSENT',$d(-200),$d(-15),'Consent withdrawn; removed from the mailing list.']);
        $n['data_consents'] = 3;
    });
    if ($has('data_requests')) $seedBlock('data_requests', function () use ($ins, $cid, $d, &$n) {
        // NOTE: handled_by='demo' is the provenance marker AND shows as the owner
        // on screen — fine for the in-progress DSAR ("someone is handling it").
        $idr2 = "INSERT INTO data_requests
                 (received_at,requester,contact,kind,subject_kind,subject_id,detail,status,answered_at,answer,handled_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?, 'demo')";
        // In progress.
        $ins($idr2, [$d(-4).'T11:00:00+05:30','Rakesh Menon','rakesh.menon@example.com','ACCESS','CONTACT',$cid['CL-NIL'],'Requests a copy of all personal data held about him. Compiling the export.','OPEN','','']);
        // Answered / closed.
        $ins($idr2, [$d(-30).'T10:00:00+05:30','Deepa Balan','deepa.balan@example.com','ERASURE','CONTACT',$cid['CL-GHE'],'Asked to be removed from marketing lists.','CLOSED',$d(-28).'T16:00:00+05:30','Removed from all marketing lists; confirmed by e-mail.']);
        $n['data_requests'] = 2;
    });

    // ------------------------------------------------------------------
    //  Disclosure consents (permission to disclose results to a third party).
    //  prefer disclosure_save() (forces status/current_user()).
    // ------------------------------------------------------------------
    if ($has('disclosure_consents')) {
        $idis = "INSERT INTO disclosure_consents
                 (partner_id,partner_name,subject,scope,status,requested_on,consent_on,consent_by,note,created_by,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?, 'demo', ?, ?)";
        $ins($idis, [$cid['CL-NIL'],'Narmada Industries Ltd','Disclose inspection results to their insurer','Final report MGH/AMD/2026/D-0001 to United Assurance','CONSENTED',$d(-20),$d(-18),'Rakesh Menon','Written consent received.',$now,$now]);
        $ins($idis, [$cid['CL-SVP'],'Suryavan Ports & SEZ Ltd','Share NDT records with a third-party certifier','Radiography records for the jetty','REQUESTED',$d(-5),'','','Awaiting client sign-off.',$now,$now]);
        $n['disclosure_consents'] = 2;
    }

    // ------------------------------------------------------------------
    //  Access log against an existing demo identity document (person_documents
    //  rows are seeded with uploaded_by='demo'). Guard for 0 — the identity
    //  module may not be built on this install.
    // ------------------------------------------------------------------
    if ($has('person_document_access') && $has('person_documents')) {
        $doc  = (int)$val("SELECT id FROM person_documents WHERE uploaded_by='demo' ORDER BY id LIMIT 1");
        $dpid = (int)$val("SELECT person_id FROM person_documents WHERE uploaded_by='demo' ORDER BY id LIMIT 1");
        if ($doc) {
            $ipa = "INSERT INTO person_document_access
                    (document_id,person_id,action,username,reason,recipient,ip,at)
                    VALUES (?,?,?, 'demo', ?,?,?,?)";
            $ins($ipa, [$doc,$dpid,'VIEW','Verifying passport number for the Vapi gate list.','','127.0.0.1',$d(-9).'T09:30:00+05:30']);
            $ins($ipa, [$doc,$dpid,'REVEAL','Client gate office requested the full number.','Gate office — Vapi works','127.0.0.1',$d(-9).'T09:32:00+05:30']);
            $ins($ipa, [$doc,$dpid,'SHARE','Sent gate-pass details to the site.','security@narmada.example','127.0.0.1',$d(-9).'T09:40:00+05:30']);
            $n['person_document_access'] = 3;
        }
    }

    // ------------------------------------------------------------------
    //  Person SBU split — monthly cost apportionment for non-production people.
    //  Keyed by users.id (NOT inspector id); attach to whatever demo users exist.
    //  prefer person_split_save() but it deletes+rewrites a whole month and reads
    //  current period — direct INSERT is simpler here. Provenance: set_by='demo'.
    // ------------------------------------------------------------------
    if ($has('person_sbu_split')) {
        $uids = $pdo->query("SELECT id FROM users ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
        if ($uids) {
            $yr = (int)date('Y'); $mon = (int)date('n');
            $isp = "INSERT INTO person_sbu_split (user_id,yr,mon,sbu,pct,set_by,set_at) VALUES (?,?,?,?,?, 'demo', ?)";
            $cnt = 0;
            // First user: 60/40 across two Business Units.
            $ins($isp, [(int)$uids[0],$yr,$mon,'IND',60,$now]); $cnt++;
            $ins($isp, [(int)$uids[0],$yr,$mon,'OGC',40,$now]); $cnt++;
            if (isset($uids[1])) {
                $ins($isp, [(int)$uids[1],$yr,$mon,'IND',50,$now]); $cnt++;
                $ins($isp, [(int)$uids[1],$yr,$mon,'MIN',50,$now]); $cnt++;
            }
            $n['person_sbu_split'] = $cnt;
        }
    }

    return $n;
}

// ============================================================================
//  Part three — the workforce, hiring, sub-con, analytics-governance, custom
//  forms, hold/witness and client-feed registers the earlier parts never
//  reached. Same rule as parts one and two: most rows exist to make a screen
//  show real, connected figures (and a couple to make a RULE visible — an
//  exhausted contract, a breached KPI). Anchored ONLY on the demo cast in $x.
//  Everything carries a 'demo' marker (see seed_demo_remove additions).
// ============================================================================
function demo_seed_c6($pdo, $x, $has, $ins) {
    $oid = $x['oid']; $iid = $x['iid']; $cid = $x['cid']; $vid = $x['vid'];
    $callid = $x['callid']; $jid = $x['jid']; $bid = $x['bid'];
    $now = $x['now']; $today = $x['today']; $d = $x['d'];
    $pk = substr((string)$today, 0, 7);                 // current period key 'YYYY-MM'
    $n = [];

    // ------------------------------------------------------------------
    //  Attendance — one engineer's self-marked month (check-in/out + geo)
    //  (real writer attend_punch() is $_POST/current_user()-only; direct here)
    //  Vapi works ~20.372,72.908 · Ahmedabad office ~23.0225,72.5714
    // ------------------------------------------------------------------
    if ($has('attendance')) {
        $ia = "INSERT INTO attendance
               (att_date,inspector_id,status,remarks,job_id,check_in_at,in_lat,in_lon,in_acc,
                check_out_at,out_lat,out_lon,out_acc,marked_at,source,marked_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'SELF', 'demo', ?)";
        // [offset, status, remark, job, geo?]  — a realistic run within this month
        $days = [
            [-1,'SITE','Vapi works — vessel witness', $jid['J-2607-104'] ?? 0, 'site'],
            [-2,'OFFICE','', 0, 'office'],
            [-3,'WFH','Report write-up', 0, ''],
            [-4,'LEAVE','Casual leave', 0, ''],
            [-5,'SITE','Vapi works — stage inspection', $jid['J-2607-104'] ?? 0, 'site'],
            [-8,'TRAVEL','Out-station to Mundra', 0, ''],
            [-9,'OFFICE','', 0, 'office'],
            [-11,'SITE','Vapi works', $jid['J-2607-101'] ?? 0, 'site'],
        ];
        foreach ($days as $r) {
            $dt = $d($r[0]);
            [$ilat,$ilon,$iacc,$olat,$olon,$oacc,$cin,$cout] = [null,null,null,null,null,null,'',''];
            if ($r[4] === 'site')  { $ilat=20.3720000; $ilon=72.9080000; $iacc=14; $olat=20.3721000; $olon=72.9079000; $oacc=16; $cin=$dt.'T08:55:00+05:30'; $cout=$dt.'T18:20:00+05:30'; }
            if ($r[4] === 'office'){ $ilat=23.0225000; $ilon=72.5714000; $iacc=12; $olat=23.0225000; $olon=72.5714000; $oacc=12; $cin=$dt.'T09:30:00+05:30'; $cout=$dt.'T18:00:00+05:30'; }
            $ins($ia, [$dt,$iid['EMP01'],$r[1],$r[2],$r[3] ?: 0,$cin,$ilat,$ilon,$iacc,$cout,$olat,$olon,$oacc,$cin ?: $now,$now]);
        }
        $n['attendance'] = count($days);
    }

    // ------------------------------------------------------------------
    //  Public holidays — national list for FY 2026-27 + one Gujarat office day
    //  (no provenance column — removal keys on these exact hol_dates)
    // ------------------------------------------------------------------
    if ($has('holidays')) {
        $ih = "INSERT INTO holidays (hol_date,name,region,office_id) VALUES (?,?,?,?)";
        $hols = [
            ['2026-08-15','Independence Day','IN',null],
            ['2026-10-02','Gandhi Jayanti','IN',null],
            ['2026-10-20','Dussehra','IN',null],
            ['2026-11-08','Diwali','IN',null],
            ['2026-12-25','Christmas','IN',null],
            ['2027-01-26','Republic Day','IN',null],
            ['2027-03-14','Holi','IN',null],
            ['2027-01-14','Uttarayan (Gujarat)','GJ',$oid['AMD']],  // office-specific
        ];
        foreach ($hols as $h) $ins($ih, $h);
        $n['holidays'] = count($hols);
    }

    // ------------------------------------------------------------------
    //  Work norms — per designation / office (prefer ops_work_norms(), but it
    //  is $_POST-driven; direct INSERT matches its own INSERT exactly)
    // ------------------------------------------------------------------
    if ($has('work_norms')) {
        $iw = "INSERT INTO work_norms (designation,office_id,weekly_days,weekly_hours,updated_by,updated_at)
               VALUES (?,?,?,?, 'demo', ?)";
        $ins($iw, ['Sr. Inspector',$oid['AMD'],6,48,$now]);
        $ins($iw, ['Inspector',$oid['PUN'],5.5,44,$now]);
        $ins($iw, ['Inspector',$oid['AMD'],6,48,$now]);
        $ins($iw, ['',null,6,48,$now]);                    // company default (all designations/offices)
        $n['work_norms'] = 4;
    }

    // ------------------------------------------------------------------
    //  Back-office staff  (/m/back-office — kept as a table master)
    //  (no created_by — removal keys on emp_code prefix 'BOS-DEMO')
    // ------------------------------------------------------------------
    if ($has('back_office_staff')) {
        $ib = "INSERT INTO back_office_staff (name,emp_code,designation,department,office_id,email,mobile,ctc,allowances,status,created_at)
               VALUES (?,?,?,?,?,?,?,?,?, 'ACTIVE', ?)";
        $ins($ib, ['Ananya Kulkarni','BOS-DEMO-01','Coordinator','OPERATIONS',$oid['AMD'],'ananya.kulkarni@example.com','9825030001',420000,24000,$now]);
        $ins($ib, ['Rohit Shah','BOS-DEMO-02','Accountant','FINANCE',$oid['AMD'],'rohit.shah@example.com','9825030002',480000,18000,$now]);
        $n['back_office_staff'] = 2;
    }

    // ------------------------------------------------------------------
    //  Availability board — inspector_day_status for today (/availability)
    //  (real writer inside attend_punch(); direct INSERT matches it)
    // ------------------------------------------------------------------
    if ($has('inspector_day_status')) {
        $is = "INSERT INTO inspector_day_status (inspector_id,day,status,note,set_by,created_at) VALUES (?,?,?,?, 'demo', ?)";
        $ins($is, [$iid['EMP01'],$today,'ON_JOB','Vapi works — final inspection',$now]);
        $ins($is, [$iid['EMP02'],$today,'AVAILABLE','',$now]);
        $ins($is, [$iid['EMP03'],$today,'LEAVE','Casual leave',$now]);
        $ins($is, [$iid['SC-001'],$today,'TRAVEL','En route to Pune site',$now]);
        $n['inspector_day_status'] = 4;
    }

    // ------------------------------------------------------------------
    //  Hiring — one candidate mid-pipeline with its full event trail
    //  (real writer is candidate-new $_POST handler; direct here)
    // ------------------------------------------------------------------
    if ($has('candidates')) {
        $ic = "INSERT INTO candidates
               (cand_code,first_name,middle_name,last_name,client_id,call_id,designation,source,agency,proposed_site,
                sbu,experience_years,email,mobile,cv_link,expected_rate,rate_type,cv_received_date,stage,inspector_id,
                remarks,submitted_client_date,interview_required,interview_date,created_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'MANDAY', ?,?,?,?,?,?,?, 'demo', ?)";
        $candId = $ins($ic, ['CV-DEMO-01','Farhan','','Shaikh',$cid['CL-SVP'],$callid['C-2607-002'] ?? null,'Sr. Inspector',
            'HR_AGENCY','TalentFirst Recruitment','Suryavan Ports — jetty fabrication','OGC',9.5,
            'farhan.shaikh@example.com','9812345678','https://drive.example/cv/farhan-shaikh.pdf',5200,
            $d(-18),'INTERVIEW',null,'Strong structural / welding background; client shortlisted.',$d(-12),1,$d(2),$now]);
        $n['candidates'] = 1;

        if ($has('candidate_events')) {
            $ie = "INSERT INTO candidate_events (candidate_id,from_stage,to_stage,remark,actor,created_at) VALUES (?,?,?,?,?,?)";
            $ins($ie, [$candId,'','RECEIVED','CV received from TalentFirst','Sana Kapoor',$d(-18).'T10:00:00+05:30']);
            $ins($ie, [$candId,'RECEIVED','SUBMITTED','Submitted to Suryavan QA','Sana Kapoor',$d(-12).'T15:30:00+05:30']);
            $ins($ie, [$candId,'SUBMITTED','SHORTLISTED','Client shortlisted for interview','Sunita Rao',$d(-8).'T11:15:00+05:30']);
            $ins($ie, [$candId,'SHORTLISTED','INTERVIEW','Interview scheduled at Mundra site','Sana Kapoor',$d(-3).'T09:40:00+05:30']);
            $n['candidate_events'] = 4;
        }
    }

    // ------------------------------------------------------------------
    //  Sub-contractors + their rate card  (/m/subcons, /m/subcon-rates)
    //  (no created_by — removal keys on the agency names / subcon_id)
    // ------------------------------------------------------------------
    if ($has('subcons')) {
        $isc = "INSERT INTO subcons (agency,inspector_name,skill,experience_level,regions,email,mobile,compliance,active,created_at)
                VALUES (?,?,?,?,?,?,?,?,1,?)";
        $sc1 = $ins($isc, ['TechInspect Services','Mohan Rao','Lifting, Cranes','SENIOR','WEST,SOUTH',
                           'ops@techinspect.example','9820044441','PF/ESIC, WC policy valid',$now]);
        $sc2 = $ins($isc, ['SafeLift Inspection Co','Ganesh Pillai','NDT, Welding','MID','WEST',
                           'desk@safelift.example','9820044442','GST + WC policy',$now]);
        $n['subcons'] = 2;

        if ($has('subcon_rates')) {
            $ir = "INSERT INTO subcon_rates (subcon_id,agency,skill,experience_level,region,rate_type,rate) VALUES (?,?,?,?,?,?,?)";
            $ins($ir, [$sc1,'TechInspect Services','Lifting, Cranes','SENIOR','WEST','MANDAY',6500]);
            $ins($ir, [$sc1,'TechInspect Services','Lifting, Cranes','SENIOR','SOUTH','MANDAY',7000]);
            $ins($ir, [$sc1,'TechInspect Services','Cranes','SENIOR','WEST','MONTHLY',145000]);
            $ins($ir, [$sc2,'SafeLift Inspection Co','NDT','MID','WEST','MANDAY',4800]);
            $ins($ir, [$sc2,'SafeLift Inspection Co','Welding','MID','WEST','MANDAY',5000]);
            $n['subcon_rates'] = 5;
        }
    }

    // ------------------------------------------------------------------
    //  Contract exception — quantity EXHAUSTED and approved (the rule case)
    //  (real writer ops_contract_overrides() is $_POST; direct here)
    // ------------------------------------------------------------------
    if ($has('contract_overrides')) {
        $io = "INSERT INTO contract_overrides
               (quotation_id,contract_id,call_id,kind,reason,status,requested_by,requested_by_id,requested_at,
                endorsed_by,endorsed_at,endorse_note,decided_by,decided_at,decide_note,valid_until,uses_allowed,uses_taken,created_at)
               VALUES (?,?,?, 'EXHAUSTED', ?, 'APPROVED', 'demo', ?,?,?,?,?,?,?,?,?,?,?,?)";
        $ins($io, [null,null,$callid['C-2607-005'] ?? null,
            'PO quantity fully consumed but one urgent lifting inspection remains — approved as a one-off top-up.',
            null,$d(-6).'T10:00:00+05:30',
            'Meena Shah',$d(-5).'T12:00:00+05:30','Genuine shortfall; client confirmed the extra visit.',
            'Master Admin',$d(-5).'T16:30:00+05:30','Approved for 2 uses.',
            $d(30),2,2,$now]);                              // uses_taken == uses_allowed => exhausted
        $n['contract_overrides'] = 1;
    }

    // ------------------------------------------------------------------
    //  Analytics governance — a target vs a BREACHING snapshot that fires an
    //  alert, plus the KPI version row and the period it sits in.
    //  (real writers tapi_*() evaluate KPIs live; direct INSERTs give a
    //   deterministic breach — 'sla_compliance' HIGHER, target 95 / floor 90.)
    // ------------------------------------------------------------------
    if ($has('analytics_periods')) {
        $ins("INSERT INTO analytics_periods (period_key,state,note,changed_by,updated_at) VALUES (?, 'REVIEW', ?, 'demo', ?)",
             [$pk,'Month under review before sign-off.',$now]);
        $n['analytics_periods'] = 1;
    }
    if ($has('kpi_versions')) {
        $ins("INSERT INTO kpi_versions (kpi_key,version,formula,unit,target,threshold,direction,effective_from,effective_to,changed_by,reason,created_at)
              VALUES ('sla_compliance',1,'on_time_reports / total_reports * 100','%',95,90,'HIGHER',?,'', 'demo', ?, ?)",
             [$d(-120),'Baseline SLA definition adopted at go-live.',$now]);
        $n['kpi_versions'] = 1;
    }
    if ($has('kpi_targets')) {
        $it = "INSERT INTO kpi_targets (kpi_key,office_id,sbu,period_key,target,threshold,note,created_by,created_at)
               VALUES (?,?,?,?,?,?,?, 'demo', ?)";
        $ins($it, ['sla_compliance',0,'',$pk,95,90,'Company SLA floor 90%.',$now]);
        $ins($it, ['sla_compliance',$oid['AMD'],'',$pk,96,92,'Ahmedabad stretch target.',$now]);
        $ins($it, ['job_closure_rate',0,'',$pk,85,75,'Monthly closure target.',$now]);
        $n['kpi_targets'] = 3;
    }
    if ($has('kpi_snapshots')) {
        $isn = "INSERT INTO kpi_snapshots (period_key,kpi_key,scope_office,scope_sbu,value,no_data,target,status,kpi_version,taken_at,taken_by)
                VALUES (?,?,?,?,?,0,?,?,1,?, 'demo')";
        // BREACH: 82.5 < floor 90 => 'BAD'  (this is the row that raises the alert)
        $ins($isn, [$pk,'sla_compliance',0,'',82.5,95,'BAD',$now]);
        // A healthy one for contrast on the same board.
        $ins($isn, [$pk,'job_closure_rate',0,'',88.0,85,'GOOD',$now]);
        $n['kpi_snapshots'] = 2;
    }
    if ($has('kpi_alerts')) {
        $ins("INSERT INTO kpi_alerts (name,kpi_key,op,threshold,severity,recipients,scope_office,enabled,last_fired_at,created_by,created_at)
              VALUES (?, 'sla_compliance', 'LT', 90, 'CRIT', ?, 0, 1, ?, 'demo', ?)",
             ['SLA compliance below floor','director@example.com,sbuhead@example.com',$now,$now]);   // last_fired_at set: it breached
        $n['kpi_alerts'] = 1;
    }

    // ------------------------------------------------------------------
    //  Custom form — a live register with fields and one filled record
    //  (real writers customforms.php / lookups.php are $_POST; direct here)
    // ------------------------------------------------------------------
    // custom_forms / custom_records are created lazily by cforms_migrate() —
    // only when a Custom-forms screen is opened. On an install where nobody has,
    // the tables are absent, $has() is false and this block is skipped (the demo
    // "coverage" board then reads "custom forms — not installed"). Create them
    // here so the seed is self-sufficient.
    if (function_exists('cforms_migrate')) { try { cforms_migrate(); } catch (Throwable $e) {} }
    if ($has('custom_forms') && $has('custom_fields') && $has('custom_records') && $has('custom_values')) {
        $slug = 'toolbox-talk';
        $formId = $ins("INSERT INTO custom_forms (name,slug,nav_group,icon,help,active,sort_order,created_by,created_at)
                        VALUES (?,?, 'Operations', '🦺', ?,1,50, 'demo', ?)",
            ['Toolbox Talk Register',$slug,'Daily site safety briefings — area, topic and attendance.',$now]);
        $iff = "INSERT INTO custom_fields (entity,field_key,label,field_type,lookup_type_id,required,sort_order,active,created_at)
                VALUES (?,?,?,?,NULL,?,?,1,?)";
        $fields = [
            ['area','Work area','text',1,1],
            ['talk_date','Date','date',1,2],
            ['attendees','No. of attendees','number',0,3],
            ['topic','Topic covered','text',1,4],
        ];
        foreach ($fields as $f) $ins($iff, [$slug,$f[0],$f[1],$f[2],$f[3],$f[4],$now]);
        $n['custom_forms'] = 1; $n['custom_fields'] = count($fields);

        $recId = $ins("INSERT INTO custom_records (form_id,title,created_by,created_at,updated_at) VALUES (?,?, 'demo', ?, ?)",
            [$formId,'Toolbox Talk — Vapi works',$now,$now]);
        $ivl = "INSERT INTO custom_values (entity,record_id,field_key,value_text,value_id,created_at) VALUES (?,?,?,?,NULL,?)";
        $ins($ivl, [$slug,$recId,'area','Vapi Chemical Works — Bay 3',$now]);
        $ins($ivl, [$slug,$recId,'talk_date',$d(-1),$now]);
        $ins($ivl, [$slug,$recId,'attendees','8',$now]);
        $ins($ivl, [$slug,$recId,'topic','Working at height — scaffold tags and harness checks',$now]);
        $n['custom_records'] = 1; $n['custom_values'] = 4;
    }

    // ------------------------------------------------------------------
    //  Job visits — a scheduled check-in and a closed one
    //  (real writers schedule.php are $_POST; direct here)
    // ------------------------------------------------------------------
    if ($has('job_visits')) {
        $iv = "INSERT INTO job_visits (job_id,visit_date,inspector_id,status,note,report_link,report_doc_id,closed_by,closed_at)
               VALUES (?,?,?,?,?,?,?,?,?)";
        // Planned upcoming visit on the in-progress job.
        $ins($iv, [$jid['J-2607-104'],$d(1),$iid['EMP01'],'PLANNED','Welding audit — fabrication shop','',null,'','']);
        // A closed visit with report evidence on the finished job.
        $ins($iv, [$jid['J-2607-101'],$d(-11),$iid['EMP01'],'DONE','Final inspection — 12 vessels',
                   'https://drive.example/reports/J-2607-101.pdf',null,'Ravi Kumar',$d(-10).'T18:30:00+05:30']);
        $n['job_visits'] = 2;
    }

    // ------------------------------------------------------------------
    //  Deputation site history — an assignment then a transfer (§9 retained)
    // ------------------------------------------------------------------
    if ($has('dep_site_history')) {
        $idh = "INSERT INTO dep_site_history (job_id,inspector_id,site,location,from_date,to_date,reason,kind,created_by,created_at)
               VALUES (?,?,?,?,?,?,?,?, 'demo', ?)";
        $ins($idh, [$jid['J-2607-106'] ?? 0,$iid['EMP02'],'Dahej Petrochemical Complex','Dahej, Gujarat',
                   $d(-40),$d(-11),'Initial deputation posting','ASSIGNMENT',$now]);
        $ins($idh, [$jid['J-2607-106'] ?? 0,$iid['EMP02'],'Narmada Jamnagar Refinery','Jamnagar, Gujarat',
                   $d(-10),'','Rotated to replacement site','TRANSFER',$now]);
        $n['dep_site_history'] = 2;
    }

    // ------------------------------------------------------------------
    //  Hold / witness points — two open interventions (/hw-points)
    //  (prefer hwp_create(); it is clean but stamps raised_by from
    //   current_user() — empty during seed. Direct INSERT sets raised_by='demo'.)
    // ------------------------------------------------------------------
    if ($has('hw_points')) {
        $ip = "INSERT INTO hw_points
               (job_id,call_id,report_doc_id,office_id,irn,point_type,qap_clause,sub_clause,activity,description,
                source,status,dedupe_key,raised_by,raised_at,remark,created_at,updated_at)
               VALUES (?,?,?,?, '', ?,?,?,?,?, 'MANUAL', 'OPEN', ?, 'demo', ?,?,?,?)";
        $ins($ip, [$jid['J-2607-106'] ?? 0,$callid['C-2607-006'] ?? null,null,$oid['AMD'],'HOLD','7.2','a',
                   'Hydro test','Hold released only after client witnesses the hydro test.','demo-hw-1',$now,'',$now,$now]);
        $ins($ip, [$jid['J-2607-106'] ?? 0,$callid['C-2607-006'] ?? null,null,$oid['AMD'],'WITNESS','8.1','',
                   'DFT check','Witness coating DFT readings at final.','demo-hw-2',$now,'',$now,$now]);
        $n['hw_points'] = 2;
    }

    // ------------------------------------------------------------------
    //  Client portal feed — notifications for a demo client
    //  (real writer cvp_notify_once(); direct with a 'DEMO —' title marker)
    // ------------------------------------------------------------------
    if ($has('portal_notifications')) {
        $ipn = "INSERT INTO portal_notifications (audience,partner_id,event,ref_kind,ref_id,title,url,is_read,created_at)
                VALUES ('CLIENT',?,?,?,?,?,?,?,?)";
        $ins($ipn, [$cid['CL-NIL'],'REPORT_ISSUED','REPORT',$jid['J-2607-101'] ?? 0,
                    'DEMO — Final inspection report issued for 12 pressure vessels','/portal',0,$now]);
        $ins($ipn, [$cid['CL-NIL'],'VISIT_SCHEDULED','JOB',$jid['J-2607-104'] ?? 0,
                    'DEMO — Welding audit scheduled at your Vapi works','/portal',1,$d(-2).'T09:00:00+05:30']);
        $n['portal_notifications'] = 2;
    }

    return $n;
}

// ----------------------------------------------------------------------------
//  Part-three, group 7 — the last screen-backed registers: CAPA action plans,
//  the service-scope engine (per-client enablement + dependencies), the client
//  portal audit trail and the ad-campaign ROI feed. (The remaining empty tables
//  — ads_outbox, ads_sync_log, books_outbox, install_beats, login_attempts,
//  sso_attempts, user_prefs — are transient send/sync/telemetry queues with no
//  register screen, and are intentionally left empty.)
// ----------------------------------------------------------------------------
function demo_seed_c7($pdo, $x, $has, $ins) {
    $oid = $x['oid']; $iid = $x['iid']; $cid = $x['cid']; $vid = $x['vid'];
    $now = $x['now']; $d = $x['d'];
    $n = [];
    $val = function ($sql, $a = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($a); $v = $s->fetchColumn(); $s->closeCursor(); return $v; };

    // CAPA corrective-action plan — one done, one overdue-open, one future-open.
    if ($has('capa_actions')) {
        $capaId = (int)$val("SELECT id FROM capa WHERE ref LIKE 'CAPA-2026-%' ORDER BY id LIMIT 1");
        if ($capaId) {
            $ia = "INSERT INTO capa_actions (capa_id,seq,description,owner,due_on,status,done_on,done_by,done_note,created_by,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?, 'demo', ?)";
            $ins($ia, [$capaId,1,'Re-train inspectors on the revised QAP witness-point procedure.','Sunil Verma',$d(-5),'DONE',$d(-4),'Sunil Verma','Toolbox session held; attendance recorded.',$now]);
            $ins($ia, [$capaId,2,'Update the report template to force the NDT-extent field.','Priya Nair',$d(-2),'OPEN','','','',$now]);   // overdue
            $ins($ia, [$capaId,3,'Add a checklist gate in IDEMS before approval can proceed.','Meena Shah',$d(12),'OPEN','','','',$now]);
            $n['capa_actions'] = 3;
        }
    }

    // Service scope engine — per-client enablement + service dependencies.
    if ($has('service_scope')) {
        $isc = "INSERT INTO service_scope (level,ref_id,service_code,active,effective_on,note,set_by,set_at) VALUES (?,?,?,?,?,?, 'demo', ?)";
        $ins($isc, ['CLIENT',$cid['CL-NIL'],'INSPECTION',1,$d(-120),'DEMO — enabled for Narmada TPI contract.',$now]);
        $ins($isc, ['CLIENT',$cid['CL-NIL'],'RELEASE',1,$d(-120),'DEMO — Release Notes enabled.',$now]);
        $ins($isc, ['CLIENT',$cid['CL-GHE'],'EXPEDITING',0,$d(-30),'DEMO — trialled, then switched off.',$now]);   // inactive edge
        $n['service_scope'] = 3;
    }
    if ($has('service_dependencies')) {
        $isd = "INSERT INTO service_dependencies (service_code,requires_code,condition_note,requires_approval,effective_on,level,ref_id,scope_note,active,is_system,set_by,set_at)
                VALUES (?,?,?,?,?,?,?,?,?,0, 'demo', ?)";
        $ins($isd, ['RELEASE','INSPECTION','A Release Note can only be raised after an accepted inspection.',1,$d(-120),'GLOBAL',0,'DEMO — applies to all clients.',1,$now]);
        $ins($isd, ['NCR_CAPA','INSPECTION','NCR / CAPA follows from an inspection finding.',0,$d(-120),'GLOBAL',0,'DEMO',1,$now]);
        $n['service_dependencies'] = 2;
    }

    // Client portal audit trail — a sign-in, a report view, an acceptance.
    if ($has('portal_audit') && $has('client_users')) {
        $cu = (int)$val("SELECT id FROM client_users WHERE created_by='demo' ORDER BY id LIMIT 1");
        $pp = (int)$val("SELECT partner_id FROM client_users WHERE created_by='demo' ORDER BY id LIMIT 1");
        if ($cu) {
            $ipa = "INSERT INTO portal_audit (client_user_id,partner_id,action,detail,ip,at) VALUES (?,?,?,?,?,?)";
            $ins($ipa, [$cu,$pp,'LOGIN','Client portal sign-in','203.0.113.11',$d(-3).'T10:02:00+05:30']);
            $ins($ipa, [$cu,$pp,'VIEW_REPORT','Viewed final inspection report','203.0.113.11',$d(-3).'T10:03:20+05:30']);
            $ins($ipa, [$cu,$pp,'ACCEPT_REPORT','Accepted report MGH/AMD/2026/D-0001','203.0.113.11',$d(-3).'T10:05:00+05:30']);
            $n['portal_audit'] = 3;
        }
    }

    // Ad-campaign ROI feed — spend rows plus attributed lead(s).
    if ($has('ads_spend')) {
        $ias = "INSERT INTO ads_spend (workspace_id,campaign_id,campaign_name,platform,metric_date,impressions,clicks,spend,conversions,pulled_at)
                VALUES ('demo-ws',?,?,?,?,?,?,?,?,?)";
        $ins($ias, ['DEMO-CMP-01','TPI Services — Gujarat','GOOGLE',$d(-7),12000,320,8500,6,$now]);
        $ins($ias, ['DEMO-CMP-01','TPI Services — Gujarat','GOOGLE',$d(-6),13500,410,9200,9,$now]);
        $ins($ias, ['DEMO-CMP-02','Vendor Assessment — Maharashtra','META',$d(-6),8000,150,4200,3,$now]);
        $n['ads_spend'] = 3;
    }
    if ($has('ads_leads')) {
        $leadId = (int)$val("SELECT id FROM leads WHERE created_by='demo' ORDER BY id LIMIT 1");
        $ial = "INSERT INTO ads_leads (ext_id,ext_kind,workspace_id,lead_id,partner_id,campaign_id,campaign_name,platform,utm_source,utm_medium,utm_campaign,contact_name,contact_email,contact_phone,occurred_at,imported_at,local_kind,local_id,origin)
                VALUES (?,?, 'demo-ws', ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'ADSPRO')";
        $ins($ial, ['demo-wa-01','WHATSAPP',$leadId ?: null,$cid['CL-GHE'],'DEMO-CMP-01','TPI Services — Gujarat','GOOGLE','google','cpc','tpi-guj','Farida Qureshi','farida.q@example.com','9825022210',$d(-14),$now,'LEAD',$leadId ?: null]);
        $ins($ial, ['demo-wa-02','WHATSAPP',null,null,'DEMO-CMP-02','Vendor Assessment — Maharashtra','META','facebook','cpc','va-mh','Prospect Two','p2@example.com','9812000002',$d(-9),$now,'LEAD',null]);
        $n['ads_leads'] = 2;
    }
    return $n;
}

// Dispatcher — runs every part-three group, isolating failures.
function demo_seed_modules_c($pdo, $x, $has, $ins) {
    $n = []; $GLOBALS['__seedc_fail'] = [];
    // The seed is authored against SQLite (typeless): several rows carry an empty
    // string '' where a date is blank. Strict MySQL (STRICT_TRANS_TABLES +
    // NO_ZERO_DATE, the modern default) rejects those, so a block throws and its
    // register loads empty on production. Relax this connection to match SQLite
    // for the duration of the seed, so the fix travels with the seed itself even
    // if db.php was not yet updated. Per-connection only; wrapped for locked hosts.
    try { $pdo->exec("SET SESSION sql_mode = ''"); } catch (Throwable $e) {}

    // Several registers live in tables that are created LAZILY — the first time
    // someone opens that module, its *_migrate() runs and builds the table. On a
    // fresh production database those tables do not exist yet when the seed runs,
    // so $has() returns false and the block is silently skipped: the register
    // loads empty with no error to show for it (the "6 of 83" gap — security
    // incidents, client NDAs, confidentiality breaches, consent register, DSAR,
    // custom forms). Build the tables here, before any block checks for them, so
    // the seed no longer depends on the module having been opened first. Each
    // migrate has its own static guard and is a no-op if already run; wrapped so a
    // locked host cannot take the whole seed down.
    // lk_ensure_schema builds custom_fields + custom_values (the custom-form field
    // engine); cforms_migrate builds custom_forms + custom_records. The custom-form
    // seed needs all four, so both must run or that block is skipped and "Custom
    // forms" reads empty.
    foreach (['compliance_migrate','conf_migrate','cforms_migrate','lk_ensure_schema'] as $mig) {
        if (function_exists($mig)) { try { $mig(); } catch (Throwable $e) { $GLOBALS['__seedc_fail'][] = $mig . ': ' . $e->getMessage(); } }
    }

    foreach (['demo_seed_c1','demo_seed_c2','demo_seed_c3','demo_seed_c4','demo_seed_c5','demo_seed_c6','demo_seed_c7','demo_seed_recruit_cc','demo_seed_costing'] as $fn) {
        if (!function_exists($fn)) continue;
        try {
            $r = $fn($pdo, $x, $has, $ins);
            if (is_array($r)) foreach ($r as $k => $v) { $n[$k] = ($n[$k] ?? 0) + $v; }
        } catch (Throwable $e) {
            $GLOBALS['__seedc_fail'][] = $fn . ': ' . $e->getMessage();
        }
    }
    return $n;
}

// ============================================================================
//  Part-three teardown — removes everything demo_seed_modules_c inserted, in
//  FK-safe order (children before parents). Must run INSIDE seed_demo_remove()
//  BEFORE the base seed deletes business_partners / report_docs / jobs / calls,
//  because several of these rows hang off those parents. Returns rows deleted.
// ============================================================================
function demo_seed_c_remove($pdo) {
    $n = 0;
    $try = function ($sql, $args = []) use ($pdo, &$n) {
        try { $st = $pdo->prepare($sql); $st->execute($args); $n += $st->rowCount(); }
        catch (Throwable $e) { /* table not built on this install */ }
    };
    $demoPartners = "SELECT id FROM business_partners WHERE code IN ('CL-NIL','CL-SVP','CL-GHE','VN-VAP','VN-MUN') OR code LIKE 'EC-%' OR code LIKE 'EV-%'";
    $demoReports  = "SELECT id FROM report_docs WHERE created_by='demo'";
    $demoJobs     = "SELECT id FROM jobs WHERE created_by='demo'";
    $demoQuotes   = "SELECT id FROM quotations WHERE quote_no LIKE 'Q-2607-%'";

    // ---- project costing (demo sheets) ----
    $try("DELETE FROM project_costing_lines WHERE costing_id IN (SELECT id FROM project_costings WHERE created_by='demo')");
    $try("DELETE FROM project_costings WHERE created_by='demo'");
    // ---- c7: CAPA actions / service scope / portal audit / ads ----
    $try("DELETE FROM capa_actions WHERE created_by='demo'");
    $try("DELETE FROM service_dependencies WHERE set_by='demo'");
    $try("DELETE FROM service_scope WHERE set_by='demo'");
    $try("DELETE FROM ads_leads WHERE workspace_id='demo-ws'");
    $try("DELETE FROM ads_spend WHERE workspace_id='demo-ws'");
    // portal_audit for demo client users (also covered by base removal, harmless twice)
    $try("DELETE FROM portal_audit WHERE client_user_id IN (SELECT id FROM client_users WHERE created_by='demo')");

    // ---- c1: CRM funnel (children first) ----
    $try("DELETE FROM stage_gate_requests WHERE requested_by='demo'");
    $try("DELETE FROM stage_gates WHERE created_by='demo'");
    $try("DELETE FROM opportunity_stage_history WHERE moved_by='demo'");
    $try("DELETE FROM opportunity_quotes WHERE linked_by='demo'");
    $try("DELETE FROM opportunities WHERE created_by='demo'");
    $try("DELETE FROM lead_files WHERE uploaded_by='demo'");
    $try("DELETE FROM lead_stage_history WHERE moved_by='demo'");
    $try("DELETE FROM leads WHERE created_by='demo'");
    $try("DELETE FROM quote_revisions WHERE changed_by='demo'");
    $try("DELETE FROM quote_followups WHERE quote_id IN ($demoQuotes)");
    $try("DELETE FROM quote_files WHERE uploaded_by='demo'");
    $try("DELETE FROM quote_edit_requests WHERE requested_by='demo'");
    $try("DELETE FROM quote_approvals WHERE quote_id IN ($demoQuotes)");
    $try("DELETE FROM quote_approval_rules WHERE name LIKE 'DEMO %'");
    $try("DELETE FROM crm_templates WHERE created_by='demo'");

    // ---- c2: partners / vendors ----
    $try("DELETE FROM po_line_items WHERE purchase_order_id IN (SELECT id FROM partner_purchase_orders WHERE partner_id IN ($demoPartners))");
    $try("DELETE FROM vendor_audit WHERE vendor_id IN ($demoPartners)");
    $try("DELETE FROM vendor_users WHERE created_by='demo'");
    $try("DELETE FROM vendor_status_events WHERE partner_id IN ($demoPartners)");
    $try("DELETE FROM vendor_qualifications WHERE partner_id IN ($demoPartners)");
    $try("DELETE FROM vendor_profiles WHERE partner_id IN ($demoPartners)");
    $try("DELETE FROM partner_purchase_orders WHERE partner_id IN ($demoPartners)");
    $try("DELETE FROM partner_contracts WHERE partner_id IN ($demoPartners)");
    $try("DELETE FROM partner_registrations WHERE partner_id IN ($demoPartners)");
    $try("DELETE FROM partner_relationships WHERE partner_id IN ($demoPartners) OR related_id IN ($demoPartners)");
    $try("DELETE FROM partner_notes WHERE partner_id IN ($demoPartners)");

    // ---- c3: money / books ----
    $try("DELETE FROM invoice_lines WHERE description LIKE 'DEMO —%'");
    $try("DELETE FROM receipt_allocations WHERE allocated_by='demo'");
    $try("DELETE FROM credit_notes WHERE created_by='demo' OR cn_no LIKE 'DEMO/%'");
    $try("DELETE FROM credit_recon WHERE notes LIKE 'DEMO —%'");
    $try("DELETE FROM job_bills WHERE uploaded_by='demo'");
    $try("DELETE FROM billing_orders WHERE order_id LIKE 'DEMO-%'");
    $try("DELETE FROM tally_exports WHERE batch_ref LIKE 'DEMO-%'");
    $try("DELETE FROM office_expenses WHERE set_by='demo'");
    $try("DELETE FROM cost_allocations WHERE source_label LIKE 'DEMO —%'");
    $try("DELETE FROM cost_runs WHERE run_by='demo'");
    $try("DELETE FROM issued_licences WHERE ref LIKE 'DEMO-%'");
    $try("DELETE FROM receipts WHERE receipt_no LIKE 'DEMO/%'");
    $try("DELETE FROM invoices WHERE invoice_no LIKE 'DEMO/%' AND created_by='demo'");

    // ---- c4: reports / IDEMS (children of report_docs first) ----
    $try("DELETE FROM report_client_reviews WHERE report_doc_id IN ($demoReports)");
    $try("DELETE FROM report_vetting WHERE report_doc_id IN ($demoReports)");
    $try("DELETE FROM report_doc_review WHERE report_doc_id IN ($demoReports)");
    $try("DELETE FROM report_approvals WHERE report_doc_id IN ($demoReports)");
    $try("DELETE FROM release_inspections WHERE release_doc_id IN ($demoReports) OR inspection_doc_id IN ($demoReports)");
    $try("DELETE FROM endorsement_files WHERE created_by='demo'");
    $try("DELETE FROM job_qaps WHERE uploaded_by='demo'");
    $try("DELETE FROM report_templates WHERE created_by='demo'");
    $try("DELETE FROM idems_approval_rules WHERE name LIKE 'DEMO —%'");
    $try("DELETE FROM idems_counters WHERE scope_key IN ('MGH/AMD/2026','MGH/PUN/2026','MGH/MUM/2026')");
    $try("DELETE FROM learned_suggestions WHERE norm_key LIKE 'demo:%'");
    $try("DELETE FROM idems_approver_map WHERE inspector_id IN (SELECT id FROM inspectors WHERE emp_code IN ('EMP01','EMP02','EMP03','EMP04','SC-001'))");

    // ---- c5: quality / competence / confidentiality ----
    $try("DELETE FROM sample_custody WHERE sample_id IN (SELECT id FROM sample_items WHERE created_by='demo')");
    $try("DELETE FROM person_document_access WHERE username='demo'");
    $try("DELETE FROM decision_rules WHERE created_by='demo'");
    $try("DELETE FROM methods WHERE created_by='demo'");
    $try("DELETE FROM sample_items WHERE created_by='demo'");
    $try("DELETE FROM inspector_certs WHERE updated_by='demo'");
    $try("DELETE FROM controlled_docs WHERE created_by='demo'");
    $try("DELETE FROM risk_items WHERE created_by='demo'");
    $try("DELETE FROM satisfaction_surveys WHERE created_by='demo'");
    $try("DELETE FROM security_incidents WHERE created_by='demo'");
    $try("DELETE FROM site_doc_requirements WHERE created_by='demo'");
    $try("DELETE FROM confidentiality_undertakings WHERE recorded_by='demo'");
    $try("DELETE FROM client_ndas WHERE recorded_by='demo'");
    $try("DELETE FROM confidentiality_breaches WHERE raised_by='demo'");
    $try("DELETE FROM data_consents WHERE recorded_by='demo'");
    $try("DELETE FROM data_requests WHERE handled_by='demo'");
    $try("DELETE FROM disclosure_consents WHERE created_by='demo'");
    $try("DELETE FROM person_sbu_split WHERE set_by='demo'");

    // ---- c6: workforce / KPI / custom forms ----
    $try("DELETE FROM custom_values WHERE entity='toolbox-talk'");
    $try("DELETE FROM custom_fields WHERE entity='toolbox-talk'");
    $try("DELETE FROM custom_records WHERE created_by='demo'");
    $try("DELETE FROM custom_forms WHERE created_by='demo'");
    $try("DELETE FROM candidate_events WHERE candidate_id IN (SELECT id FROM candidates WHERE created_by='demo')");
    $try("DELETE FROM candidates WHERE created_by='demo'");
    $try("DELETE FROM subcon_rates WHERE agency IN ('TechInspect Services','SafeLift Inspection Co')");
    $try("DELETE FROM subcons WHERE agency IN ('TechInspect Services','SafeLift Inspection Co')");
    $try("DELETE FROM job_visits WHERE job_id IN ($demoJobs)");
    $try("DELETE FROM hw_points WHERE raised_by='demo'");
    $try("DELETE FROM dep_site_history WHERE created_by='demo'");
    $try("DELETE FROM contract_overrides WHERE requested_by='demo'");
    $try("DELETE FROM kpi_snapshots WHERE taken_by='demo'");
    $try("DELETE FROM kpi_targets WHERE created_by='demo'");
    $try("DELETE FROM kpi_alerts WHERE created_by='demo'");
    $try("DELETE FROM kpi_versions WHERE changed_by='demo'");
    $try("DELETE FROM analytics_periods WHERE changed_by='demo'");
    $try("DELETE FROM portal_notifications WHERE title LIKE 'DEMO —%'");
    $try("DELETE FROM attendance WHERE marked_by='demo'");
    $try("DELETE FROM inspector_day_status WHERE set_by='demo'");
    $try("DELETE FROM work_norms WHERE updated_by='demo'");
    $try("DELETE FROM back_office_staff WHERE emp_code LIKE 'BOS-DEMO%'");
    $try("DELETE FROM holidays WHERE hol_date IN ('2026-08-15','2026-10-02','2026-10-20','2026-11-08','2026-12-25','2027-01-26','2027-03-14','2027-01-14')");

    return $n;
}
