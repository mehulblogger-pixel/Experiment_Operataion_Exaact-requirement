<?php
// ============================================================================
//  Demo / sample dataset — one-click loader (Master Admin only).
//  Inserts a complete, coherent lifecycle so every screen shows live figures:
//    offices → users (all roles) → inspectors (+ entitlements) → clients/
//    vendors → contract numbers → calls → jobs (allocated / closed) → vouchers
//    (km + bills) → job-closure expenses → invoicing + payment + credit.
//  Idempotent: guarded by the `demo_seeded` setting so it runs only once.
// ============================================================================

function demo_seeded() { return setting_get('demo_seeded', '') === '1'; }

// True when the flag says "loaded" but the demo's own records are not there.
// That happens when a load was cut off part-way — the flag is set inside the
// core transaction, so a host that kills the page during the module pack can
// leave the two disagreeing. The flag is the claim; the rows are the evidence.
function demo_flag_is_stale() {
    if (!demo_seeded()) return false;
    try {
        // Only tables that actually carry the marker — business_partners has no
        // created_by, and querying it would throw straight into the catch and
        // quietly answer "not stale" for every install.
        foreach (['jobs', 'calls'] as $t)
            if ((int)ops_val("SELECT COUNT(*) FROM $t WHERE created_by='demo'") > 0) return false;
        return (int)ops_val("SELECT COUNT(*) FROM users WHERE username='insp.ravi'") === 0;
    } catch (Throwable $e) { return false; }
}

// The demo login accounts created below (shown to the admin after seeding).
function demo_accounts() {
    return [
        ['director',  'Business Director', 'Mumbai'],
        ['sbuhead',   'Business Unit Head',          'Mumbai'],
        ['bmanager',  'Branch Manager',    'Ahmedabad'],
        ['appmanager','Branch Application Manager', 'Ahmedabad'],
        ['opmanager', 'Operation Manager', 'Ahmedabad'],
        ['asstmgr',   'Asst. Manager',     'Pune'],
        ['coord.amd', 'Coordinator',       'Ahmedabad'],
        ['coord.pun', 'Coordinator',       'Pune'],
        ['account',   'Accountant (Finance)', 'Ahmedabad'],
        ['insp.ravi', 'Inspector',         'Ahmedabad'],
        ['insp.anil', 'Inspector',         'Pune'],
    ];
}

// The seed writes a few thousand rows. Over a network to MySQL that is a few
// thousand round trips, and shared hosting stops a page at 30 seconds by
// default — which is why "load demo data" could come back with nothing at all
// and no explanation. Ask for longer; hosts that forbid it ignore this quietly
// and demo_explain() then says what happened in words.
function demo_raise_limits() {
    @set_time_limit(600);
    @ini_set('memory_limit', '512M');
    @ini_set('max_execution_time', '600');
}

function seed_demo($force = false) {
    // A flag saying the demo is loaded, over a database with none of it in,
    // used to be unrecoverable from the screen: load refused as "already
    // loaded", and there was nothing to remove. Trust the data, not the flag.
    $recovering = demo_seeded() && ($force || demo_flag_is_stale());
    if (demo_seeded() && !$recovering) return ['skipped' => true];
    demo_raise_limits();
    // Recovering from a load that stopped part-way means there are leftovers —
    // the quotations or the reports that did get written before the page was
    // killed. Left alone they collide on the way back in and the pack fails
    // again for a reason that has nothing to do with the original problem, so
    // clear the demo's own records first. Real records are never touched:
    // removal goes by the seed's own markers.
    if ($recovering) seed_demo_remove();
    $pdo = db();
    $now = date('c'); $today = date('Y-m-d'); $m = date('Y-m');
    $hash = password_hash('demo12345', PASSWORD_DEFAULT);
    $d = fn($days) => date('Y-m-d', strtotime("$days days"));
    $c = [];

    $pdo->beginTransaction();
    try {
        // ---------- Offices (peer offices; Mumbai = commercial HO) ----------
        $hasAhm = (int)ops_val("SELECT COUNT(*) FROM offices WHERE is_ahmedabad=1");
        $offices = [
            ['MUM','Mumbai','Mumbai',0,15,3],
            ['AMD','Ahmedabad','Ahmedabad',$hasAhm?0:1,14,3],
            ['PUN','Pune','Pune',0,16,4],
        ];
        $oid = [];
        $insO = $pdo->prepare("INSERT INTO offices(code,name,city,is_ahmedabad,overhead_pct,contingency_pct) VALUES(?,?,?,?,?,?)");
        foreach ($offices as $o) { $insO->execute($o); $oid[$o[0]] = (int)$pdo->lastInsertId(); }
        $c['offices'] = count($offices);

        // ---------- Inspectors (assets + one agency sub-con) ----------
        $insI = $pdo->prepare("INSERT INTO inspectors(name,first_name,last_name,emp_code,sbu,sbus,skills,email,mobile,salary_ctc,status,designation,staff_kind,agency_name,agency_cost,created_at)
            VALUES(?,?,?,?,?,?,?,?,?,?, 'ACTIVE', ?,?,?,?,?)");
        $inspectors = [
            //name, first, last, emp, sbu, skills, email, mobile, ctc, desig, kind, agency, agencycost
            ['Ravi Kumar','Ravi','Kumar','EMP01','IND','IND','Welding, NDT','ravi@example.com','9800000001',720000,'Sr. Inspector','ASSET','',0],
            ['Anil Sharma','Anil','Sharma','EMP02','OGC','OGC','Piping, Coating','anil@example.com','9800000002',840000,'Inspector','ASSET','',0],
            ['Priya Nair','Priya','Nair','EMP03','IND','IND','Mechanical','priya@example.com','9800000003',600000,'Inspector','ASSET','',0],
            ['Mohan Rao','Mohan','Rao','SC-001','MIN','MIN','Lifting, Cranes','mohan@example.com','9800000004',0,'Inspector','SUBCON','TechInspect Services',480000],
        ];
        // row indices: 0 name,1 first,2 last,3 emp,4 sbu,5 sbus,6 skills,7 email,
        //              8 mobile,9 ctc,10 designation,11 staff_kind,12 agency,13 agency_cost
        $iid = [];
        foreach ($inspectors as $r) {
            $insI->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],$r[8],$r[9],$r[10],$r[11],$r[12],$r[13],$now]);
            $iid[$r[3]] = (int)$pdo->lastInsertId();
        }
        $c['inspectors'] = count($inspectors);

        // Entitlements: allow Bike/Car modes + Food/Hotel/Auto heads for each
        $insA = $pdo->prepare("INSERT INTO inspector_allowances(inspector_id,kind,code,allowed,rate_override) VALUES(?,?,?,1,NULL)");
        foreach ($iid as $ins) {
            foreach (['BIKE','CAR'] as $mc) $insA->execute([$ins,'MODE',$mc]);
            foreach (['FOOD','HOTEL','AUTO'] as $hc) $insA->execute([$ins,'HEAD',$hc]);
        }

        // ---------- Agencies (recruitment + manpower, with contract dates) ----------
        $insAg = $pdo->prepare("INSERT INTO agencies(name,agency_type,contact_person,email,mobile,contract_number,contract_start,contract_end,one_time_fee,monthly_rate,guarantee_days,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,1,?)");
        $insAg->execute(['TalentFirst Recruitment','RECRUITMENT','R. Menon','hr@talentfirst.example','9820011111','TF-2026-07',$d(-300),$d(20),50000,0,90,$now]); // renewing soon
        $agRec = (int)$pdo->lastInsertId();
        $insAg->execute(['SiteForce Manpower','MANPOWER','K. Patil','ops@siteforce.example','9820022222','SF-2026-04',$d(-200),$d(180),0,90000,90,$now]);
        $c['agencies'] = 2;
        // demo placement fees: Priya = provisional (guarantee not yet lapsed), Ravi = confirmed (past guarantee)
        $pdo->prepare("UPDATE inspectors SET agency_id=?, placement_fee=50000, fee_status='PROVISIONAL', guarantee_upto=? WHERE id=?")->execute([$agRec, $d(45), $iid['EMP03']]);
        $pdo->prepare("UPDATE inspectors SET agency_id=?, placement_fee=45000, fee_status='CONFIRMED', guarantee_upto=? WHERE id=?")->execute([$agRec, $d(-30), $iid['EMP01']]);

        // ---------- Manpower requisitions (approvals) ----------
        $insReq = $pdo->prepare("INSERT INTO requisitions(req_code,office_id,sbu,designation,project_site,req_type,outgoing_inspector_id,budgeted_cost,approved_by,approval_ref,approval_date,status,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)");
        $insReq->execute(['REQ-2607-01',$oid['PUN'],'IND','INSPECTOR','Suryavan Mundra — new project','NEW',null,75000,'Neha Iyer','HR-APP-2026-014',$d(-20),'OPEN',$now]);
        $insReq->execute(['REQ-2607-02',$oid['AMD'],'OGC','SR_INSPECTOR','Narmada Jamnagar — replacement','REPLACEMENT',$iid['EMP02'],95000,'Meena Shah','HR-APP-2026-021',$d(-10),'OPEN',$now]);
        $c['requisitions'] = 2;

        // ---------- Users (one per role; inspectors linked) ----------
        $insU = $pdo->prepare("INSERT INTO users(username,password_hash,first_name,last_name,email,role,is_superuser,is_active,home_office_id,inspector_id)
            VALUES(?,?,?,?,?,?,0,1,?,?)");
        $users = [
            //user, first, last, role, office, inspector_id
            ['director','Rahul','Desai','BUSINESS_DIRECTOR','MUM',null],
            ['sbuhead','Neha','Iyer','SBU_HEAD','MUM',null],
            ['bmanager','Meena','Shah','BRANCH_MANAGER','AMD',null],
            ['appmanager','Arjun','Patel','BRANCH_APP_MANAGER','AMD',null],
            ['opmanager','Sunil','Verma','OPERATION_MANAGER','AMD',null],
            ['asstmgr','Kiran','Joshi','ASST_MANAGER','PUN',null],
            ['coord.amd','Sana','Kapoor','COORDINATOR','AMD',null],
            ['coord.pun','Vivek','Menon','COORDINATOR','PUN',null],
            ['account','Nikhil','Jain','FINANCE','AMD',null],
            ['insp.ravi','Ravi','Kumar','INSPECTOR','AMD',$iid['EMP01']],
            ['insp.anil','Anil','Sharma','INSPECTOR','PUN',$iid['EMP02']],
        ];
        $made = 0;
        foreach ($users as $u) {
            if (ops_val("SELECT COUNT(*) FROM users WHERE username=?", [$u[0]])) continue; // don't clobber
            $insU->execute([$u[0],$hash,$u[1],$u[2],$u[0].'@example.com',$u[3],$oid[$u[4]],$u[5]]);
            $made++;
        }
        $c['users'] = $made;

        // ---------- Clients & vendors ----------
        // GSTIN is carried on the named clients because the Tally export reads
        // the state out of its first two digits to decide IGST against
        // CGST+SGST. Girnar is in Maharashtra against a Gujarat seller, so the
        // demo has one inter-state invoice and two within-state ones. The ten
        // Edge Clients below are deliberately left with no GSTIN — that is the
        // refusal path on the export screen, and it needs rows to refuse.
        $insP = $pdo->prepare("INSERT INTO business_partners(code,legal_name,display_name,is_client,is_vendor,status,state,gstin)
            VALUES(?,?,?,?,?, 'ACTIVE', ?,?)");
        $clients = [
            ['CL-NIL','Narmada Industries Ltd','Narmada',1,0,'Gujarat','24AABCN1234M1Z5'],
            ['CL-SVP','Suryavan Ports & SEZ Ltd','Suryavan Ports',1,0,'Gujarat','24AACCS5678K1ZP'],
            ['CL-GHE','Girnar Energy Hydrocarbon','Girnar Energy',1,0,'Maharashtra','27AAECG9012L1Z8'],
        ];
        $vendors = [
            ['VN-VAP','Vapi Chemical Works','Vapi Chem',0,1,'Gujarat','24AAFCV3456N1ZQ'],
            ['VN-MUN','Mundra Fabrication Yard','Mundra Fab',0,1,'Gujarat','24AAGCM7890P1ZR'],
        ];
        $cid = []; $vid = [];
        foreach ($clients as $p) { $insP->execute($p); $cid[$p[0]] = (int)$pdo->lastInsertId(); }
        foreach ($vendors as $p) { $insP->execute($p); $vid[$p[0]] = (int)$pdo->lastInsertId(); }
        $c['partners'] = count($clients) + count($vendors);

        // Contacts and a site address for every demo partner. Without these the
        // engineer's job screen has nobody to ring and nowhere to go, which is
        // exactly how the inspector view came to look empty and half-built.
        $insC = $pdo->prepare("INSERT INTO partner_contacts(partner_id,name,designation,department,email,mobile,is_primary) VALUES(?,?,?,?,?,?,?)");
        $insA = $pdo->prepare("INSERT INTO partner_addresses(partner_id,address_type,label,line1,line2,city,state,pincode,country,is_primary)
                               VALUES(?,?,?,?,?,?,?,?, 'India', ?)");
        $people = [
            ['Rakesh Menon','Purchase Head','PROCUREMENT','9825011001'],
            ['Sunita Rao','QA Manager','QUALITY','9825011002'],
            ['Imran Qureshi','Works Manager','PRODUCTION','9825011003'],
            ['Deepa Balan','Project Engineer','PROJECTS','9825011004'],
        ];
        $sites = [
            ['Plot 42, GIDC Estate','Phase II','Vapi','Gujarat','396195'],
            ['Survey 118, Mundra SEZ','Near North Gate','Mundra','Gujarat','370421'],
            ['B-7, MIDC Industrial Area','Chakan','Pune','Maharashtra','410501'],
            ['Unit 9, Hosur Road','Bommasandra','Bengaluru','Karnataka','560099'],
        ];
        $pk = 0;
        foreach (array_merge(array_values($cid), array_values($vid)) as $pid) {
            $a = $people[$pk % count($people)];
            $b = $people[($pk + 1) % count($people)];
            $mail = fn($n) => strtolower(str_replace(' ', '.', $n)) . '@example.com';
            $insC->execute([$pid, $a[0], $a[1], $a[2], $mail($a[0]), $a[3], 1]);
            $insC->execute([$pid, $b[0], $b[1], $b[2], $mail($b[0]), $b[3], 0]);
            $st = $sites[$pk % count($sites)];
            $insA->execute([$pid, 'WORKS', 'Works', $st[0], $st[1], $st[2], $st[3], $st[4], 1]);
            $pk++;
        }

        // ---------- contract numbers ----------
        $insB = $pdo->prepare("INSERT INTO boss_numbers(client_id,boss_number,start_date,end_date,status) VALUES(?,?,?,?, 'ACTIVE')");
        $boss = [
            [$cid['CL-NIL'],'40231',$d(-120),$d(240)],
            [$cid['CL-SVP'],'40198',$d(-90),$d(270)],
            [$cid['CL-GHE'],'40155',$d(-60),$d(300)],
        ];
        $bid = [];
        foreach ($boss as $b) { $insB->execute($b); $bid[$b[1]] = (int)$pdo->lastInsertId(); }
        $c['boss'] = count($boss);

        // ---------- Calls ----------
        $insC = $pdo->prepare("INSERT INTO calls(call_code,client_id,vendor_id,ibo_office_id,region,sbu,product_category,call_received_date,inspection_required_date,notes,status,executing_office_id,expected_credit,credit_type,billable_value,billable_basis,inspection_type,created_by,created_at)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)");
        // [code, client, vendor, ibo(managing/contracting), region, sbu, prodcat, recd, reqBy, status, exec, credit, credit_type, billable, billable_basis, itype]
        $calls = [
            ['C-2607-001',$cid['CL-NIL'],$vid['VN-VAP'],$oid['AMD'],'WEST','IND','Pressure vessel',$d(-20),$d(-10),'Same office (Ahmedabad manages & executes) — billable only','CLOSED',$oid['AMD'],0,'',150000,'MANDAY','INSPECTION'],
            ['C-2607-002',$cid['CL-SVP'],$vid['VN-MUN'],$oid['MUM'],'WEST','OGC','Structural',$d(-18),$d(-8),'Managed by Mumbai, executed by Ahmedabad — credit to AMD','CLOSED',$oid['AMD'],184000,'MANDAY',0,'','INSPECTION'],
            ['C-2607-003',$cid['CL-GHE'],null,$oid['MUM'],'WEST','IND','Piping',$d(-16),$d(-6),'Managed by Mumbai, executed by Pune','CLOSED',$oid['PUN'],96000,'MANDAY',0,'','INSPECTION'],
            ['C-2607-004',$cid['CL-NIL'],$vid['VN-VAP'],$oid['AMD'],'WEST','IND','Welding audit',$d(-4),$d(3),'Ahmedabad own job — in progress (billable only)','OPEN',$oid['AMD'],0,'',80000,'MANDAY','INSPECTION'],
            ['C-2607-005',$cid['CL-SVP'],$vid['VN-MUN'],$oid['PUN'],'WEST','MIN','Crane / lifting',$d(-3),$d(4),'Pune own job — sub-con deployed','OPEN',$oid['PUN'],0,'',60000,'MANDAY','INSPECTION'],
            ['C-2607-006',$cid['CL-GHE'],null,$oid['MUM'],'WEST','OGC','Coating',$d(-12),$d(-2),'Managed by Mumbai, executed by Ahmedabad — credit to AMD','OPEN',$oid['AMD'],110000,'MANDAY',0,'','INSPECTION'],
        ];
        $callid = [];
        foreach ($calls as $r) {
            $insC->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],$r[8],$r[9],$r[10],$r[11],$r[12],$r[13],$r[14],$r[15],$r[16],$now]);
            $callid[$r[0]] = (int)$pdo->lastInsertId();
        }
        $c['calls'] = count($calls);

        // ---------- Jobs (allocated / closed, with invoice + payment + credit) ----------
        $insJ = $pdo->prepare("INSERT INTO jobs(job_code,call_id,executing_office_id,inspector_id,subcon_id,scheduled_date,inspection_start_date,inspection_end_date,boss_id,expected_credit,credit_type,credit_direction,reporting_frequency,report_upload_date,closed_flag,closed_at,tat_days,sbu,mandays,subcon_cost,job_type,stage,invoice_raised,invoice_number,invoice_date,invoice_due_date,invoice_amount,payment_received,payment_date,payment_amount,credit_received,created_by,created_at)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'INSPECTION', ?,?,?,?,?,?,?,?,?,?, 'demo', ?)");
        // Each row is a full lifecycle example.
        $jobs = [
            // code, call, office, insp, subcon, sched, start, end, boss, credit, credit_type, direction, freq, reportDate, closed, closedAt, tat, sbu, mandays, subconCost, stage, invRaised, invNo, invDate, invDue, invAmt, payRecv, payDate, payAmt, creditRecv
            ['J-2607-101',$callid['C-2607-001'],$oid['AMD'],$iid['EMP01'],null,$d(-12),$d(-12),$d(-11),$bid['40231'],120000,'MANDAY','RECEIVED','SINGLE',$d(-10),1,$d(-9),2,'IND',2,0,'CLOSED',1,'INV-5511',$d(-9),$d(21),120000,1,$d(-2),120000,0],
            ['J-2607-102',$callid['C-2607-002'],$oid['AMD'],$iid['EMP02'],null,$d(-10),$d(-10),$d(-9),$bid['40198'],184000,'MANDAY','RECEIVED','SINGLE',$d(-8),1,$d(-7),3,'OGC',2,0,'CLOSED',1,'INV-5521',$d(-8),$d(-1),184000,0,'',0,1],
            ['J-2607-103',$callid['C-2607-003'],$oid['PUN'],$iid['EMP03'],null,$d(-8),$d(-8),$d(-7),$bid['40155'],96000,'MANDAY','RECEIVED','NOREPORT','',1,$d(-6),2,'IND',1.5,0,'CLOSED',0,'','','',0,0,'',0,0],
            ['J-2607-104',$callid['C-2607-004'],$oid['AMD'],$iid['EMP01'],null,$d(1),'','',$bid['40231'],60000,'MANDAY','RECEIVED','SINGLE','',0,'',null,'IND',1,0,'IN_PROGRESS',0,'','','',0,0,'',0,0],
            ['J-2607-105',$callid['C-2607-005'],$oid['PUN'],null,null,$d(2),'','',$bid['40198'],80000,'MANDAY','RECEIVED','SINGLE','',0,'',null,'MIN',1,24000,'ALLOCATED',0,'','','',0,0,'',0,0],
            ['J-2607-106',$callid['C-2607-006'],$oid['AMD'],$iid['EMP02'],null,$d(-5),$d(-5),'',$bid['40155'],110000,'MANDAY','RECEIVED','SINGLE','',0,'',null,'OGC',2,0,'REPORT_PENDING',0,'','','',0,0,'',0,0],
        ];
        $jid = [];
        foreach ($jobs as $r) {
            $insJ->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],$r[8],$r[9],$r[10],$r[11],$r[12],$r[13],$r[14],$r[15],$r[16],$r[17],$r[18],$r[19],$r[20],$r[21],$r[22],$r[23],$r[24],$r[25],$r[26],$r[27],$r[28],$r[29],$now]);
            $jid[$r[0]] = (int)$pdo->lastInsertId();
        }
        $c['jobs'] = count($jobs);

        // ---------- Job-closure expenses (a couple of closed jobs) ----------
        $insE = $pdo->prepare("INSERT INTO expenses(job_id,inspector_id,sbu,travel,local,food,lodging,misc,exp_date,notes,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
        $insE->execute([$jid['J-2607-101'],$iid['EMP01'],'IND',240,60,150,0,0,$d(-11),'Vapi site visit',$now]);
        $insE->execute([$jid['J-2607-102'],$iid['EMP02'],'OGC',480,80,200,1800,0,$d(-9),'Mundra out-station',$now]);
        $c['expenses'] = 2;

        // ---------- Vouchers (Ravi + Anil), current month ----------
        $insV = $pdo->prepare("INSERT INTO vouchers(inspector_id,office_id,month,status,advance,office_incurred,total,approved_by,created_by,created_at) VALUES(?,?,?,?,?,?,?,?, 'demo', ?)");
        $insVE = $pdo->prepare("INSERT INTO voucher_entries(voucher_id,entry_date,day_type,job_id,boss_id,vendor_id,file_no,line_no,sbu,site_label,hours,mode_code,km,travel_amount,amounts,row_total,leave_code,is_auto)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        // Voucher A — Ravi, DRAFT
        $insV->execute([$iid['EMP01'],$oid['AMD'],$m,'DRAFT',500,0,0,'',$now]);
        $vA = (int)$pdo->lastInsertId();
        // one work day (bike 40km => 240 travel + 150 food) + one leave day
        $insVE->execute([$vA,$m.'-05','WORK',$jid['J-2607-101'],$bid['40231'],$vid['VN-VAP'],'40231','12','IND','Vapi Chem',8,'BIKE',40,240,json_encode(['FOOD'=>150]),390,'',1]);
        $insVE->execute([$vA,$m.'-06','LEAVE',null,null,null,'','','','',0,'',0,0,'',0,'CL',0]);
        $pdo->prepare("UPDATE vouchers SET total=? WHERE id=?")->execute([390,$vA]);
        // Voucher B — Anil, APPROVED
        $insV->execute([$iid['EMP02'],$oid['AMD'],$m,'APPROVED',0,0,0,'Meena Shah',$now]);
        $vB = (int)$pdo->lastInsertId();
        $insVE->execute([$vB,$m.'-04','WORK',$jid['J-2607-102'],$bid['40198'],$vid['VN-MUN'],'40198','21','OGC','Mundra Fab',8,'CAR',120,1440,json_encode(['HOTEL'=>1800,'FOOD'=>200]),3440,'',1]);
        $pdo->prepare("UPDATE vouchers SET total=? WHERE id=?")->execute([3440,$vB]);
        $c['vouchers'] = 2;

        // ================= 200+ EDGE CASES (deterministic generator) =================
        // Extra masters for spread
        for ($i = 1; $i <= 10; $i++) { $insP->execute(['EC-'.$i,'Edge Client '.sprintf('%02d',$i),'EdgeCli'.$i,1,0,'Gujarat','']); $cid['EC'.$i] = (int)$pdo->lastInsertId(); }
        for ($i = 1; $i <= 6;  $i++) { $insP->execute(['EV-'.$i,'Edge Vendor '.sprintf('%02d',$i),'EdgeVen'.$i,0,1,'Maharashtra','']); $vid['EV'.$i] = (int)$pdo->lastInsertId(); }
        $clientPool = array_values($cid); $vendorPool = array_values($vid); $bossPool = array_values($bid);
        for ($i = 1; $i <= 12; $i++) { $cc = $clientPool[$i % count($clientPool)]; $insB->execute([$cc,'5090'.sprintf('%02d',$i),$d(-100),$d(260)]); $bossPool[] = (int)$pdo->lastInsertId(); }
        $offK = ['AMD','PUN','MUM']; $sbus = ['IND','OGC','MIN','GIS','AGRI','CRS','ENV','OTHER'];
        $inspPool = array_values($iid);
        $edge = 0;

        // 150 calls, each with a job — every index toggles a different edge dimension
        for ($i = 0; $i < 150; $i++) {
            $office = $oid[$offK[$i % 3]];
            $cross  = ($i % 5 === 0);                                   // 1 in 5 is cross-office
            $ibo    = $cross ? $oid[$offK[($i + 1) % 3]] : $office;      // managing/contracting office
            $sbu    = $sbus[$i % 8];
            $client = $clientPool[$i % count($clientPool)];
            $vendor = ($i % 4 === 0) ? null : $vendorPool[$i % count($vendorPool)]; // some have no vendor
            $boss   = $bossPool[$i % count($bossPool)];
            $req    = ($i % 6 === 0) ? '' : $d(-25 + ($i % 50));         // some missing / past / future
            $recd   = $d(-45 + ($i % 30));
            $closed = ($i % 3 !== 2);                                    // ~2/3 closed
            $status = $closed ? 'CLOSED' : 'OPEN';
            $credit = $cross ? (10000 * (1 + ($i % 20))) : 0;           // cross-office credit; edge: up to 200k
            $billable = $cross ? 0 : (($i % 9 === 0) ? 0 : (40000 + 1000 * ($i % 60))); // same-office billable; some ₹0
            $ccode = 'CALL-E' . sprintf('%04d', $i);
            $insC->execute([$ccode,$client,$vendor,$ibo,'WEST',$sbu,'',$recd,$req,'Edge case #'.$i.($cross?' (cross-office)':' (same office)'),$status,$office,$credit,$cross?'MANDAY':'',$billable,$cross?'':'MANDAY','INSPECTION',$now]);
            $callId = (int)$pdo->lastInsertId();

            $sched = $closed ? $d(-20 + ($i % 15)) : (($i % 4 === 0) ? $d(-8) : $d(3 + ($i % 10))); // some open+overdue
            $insp  = ($i % 8 === 0) ? null : $inspPool[$i % count($inspPool)]; // some unassigned
            $subcost = ($i % 11 === 0) ? (12000 + ($i % 5) * 3000) : 0;        // some sub-con cost
            $freq  = ($i % 2 === 0) ? 'SINGLE' : 'NOREPORT';
            $reportUp = ($closed && $freq === 'SINGLE' && $i % 3 === 0) ? $d(-18) : ''; // some reports pending
            $tat   = $closed ? ($i % 6) : null;                          // edge: 0-day TAT, and null
            $mandays = ($i % 10 === 0) ? 0 : (1 + ($i % 4));             // some zero man-days
            $stage = $closed ? 'CLOSED' : ['ALLOCATED','TRAVELLING','IN_PROGRESS','REPORT_PENDING'][$i % 4];
            $invRaised = 0; $invNo = ''; $invDate = ''; $invDue = ''; $invAmt = 0; $payRecv = 0; $payDate = ''; $payAmt = 0;
            if ($closed) {
                $mod = $i % 3; $amt = $credit ?: ($billable ?: 50000);
                // Ages spread on purpose, so receivables ageing has something in
                // every bucket. All of these used to be fifteen days old, which
                // made the ageing report a single column and proved nothing —
                // the row that matters is the invoice everybody has forgotten.
                // Negative = not due yet; 30-day terms throughout.
                if ($mod === 1) {
                    $past = [10, 40, 75, 130, -20][$i % 5];              // days past due
                    $invRaised = 1; $invNo = 'INV-E'.$i; $invAmt = $amt;
                    $invDue = $d(-$past); $invDate = $d(-$past - 30);
                }
                elseif ($mod === 2) { $invRaised = 1; $invNo = 'INV-E'.$i; $invDate = $d(-15); $invDue = $d(15); $invAmt = $amt; $payRecv = 1; $payDate = $d(-2); $payAmt = $amt; } // paid
            }
            $creditRecv = $cross ? ($closed ? ($i % 2) : 0) : 1;         // same-office excluded from "credit pending"
            $jr = ['JOB-E'.sprintf('%04d',$i),$callId,$office,$insp,null,$sched,$closed?$sched:'',$closed?$d(-19+($i%15)):'',$boss,$credit,'MANDAY',$cross?'RECEIVED':'RECEIVED',$freq,$reportUp,$closed?1:0,$closed?$d(-19):'',$tat,$sbu,$mandays,$subcost,$stage,$invRaised,$invNo,$invDate,$invDue,$invAmt,$payRecv,$payDate,$payAmt,$creditRecv];
            $insJ->execute(array_merge($jr, [$now]));
            $edge += 2;
        }

        // 32 vouchers across 4 inspectors × 8 months — statuses, leave-only, high km, many bills
        $vStat = ['DRAFT','SUBMITTED','APPROVED','PAID'];
        $rate = ['BIKE' => 6, 'CAR' => 12];
        $vi = 0;
        foreach ($inspPool as $ins) {
            for ($k = 1; $k <= 8; $k++) {
                $mm = date('Y-m', strtotime("first day of -$k months"));
                $st = $vStat[$vi % 4];
                $adv = ($vi % 3 === 0) ? 500 : 0;
                $insV->execute([$ins,$oid['AMD'],$mm,$st,$adv,0,0,($st==='APPROVED'||$st==='PAID')?'Meena Shah':'',$now]);
                $vv = (int)$pdo->lastInsertId();
                $total = 0;
                if ($vi % 5 !== 0) { // most have a work day; 1 in 5 is leave-only (₹0 edge)
                    $mode = ($vi % 2 === 0) ? 'BIKE' : 'CAR';
                    $km = 20 + ($vi % 9) * 15;                 // edge: up to ~140 km
                    $travel = $km * $rate[$mode];
                    $bills = ($vi % 4 === 0) ? ['HOTEL' => 1800, 'FOOD' => 250, 'AUTO' => 120] : ['FOOD' => 150];
                    $rowTot = $travel + array_sum($bills);
                    $insVE->execute([$vv,$mm.'-08','WORK',null,null,$vendorPool[$vi % count($vendorPool)],'','','IND','Edge site',8,$mode,$km,$travel,json_encode($bills),$rowTot,'',0]);
                    $total += $rowTot;
                }
                $insVE->execute([$vv,$mm.'-09','LEAVE',null,null,null,'','','','',0,'',0,0,'',0,($vi%2?'SL':'CL'),0]);
                $pdo->prepare("UPDATE vouchers SET total=? WHERE id=?")->execute([$total, $vv]);
                $vi++; $edge++;
            }
        }
        $c['edge_cases'] = $edge;
        // ================= end edge cases =================

        // Place the demo users under a reporting manager, so the organisation
        // chart shows an actual tree rather than a flat row of roots.
        if (function_exists('org_auto_arrange')) $c['reporting_lines'] = count(org_auto_arrange(true, true));

        setting_set('demo_seeded', '1');
        if ($pdo->inTransaction()) $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['error' => demo_explain($e)];
    }

    // ---------- Everything the original seed never reached ----------
    // Sales, reporting, the accreditation pack, the trust layer and the client
    // portal, seeded from the same cast of clients, engineers and deputations
    // so the modules are visibly ABOUT each other rather than twenty unrelated
    // islands. See demo_seed_modules() and docs/DEMO-TEST-PACK.md.
    //
    // DELIBERATELY OUTSIDE the core transaction, and its own failure is not
    // fatal. It used to be one transaction over all of it, which meant a single
    // module whose table had not been built on a part-upgraded install threw
    // away the offices, the users, the calls and the deputations too — the
    // whole demo, because of one register. Now the core is committed first and
    // the pack is added on top: worst case you get the operations demo and a
    // named list of which registers did not fill.
    $failed = [];
    try {
        $pdo->beginTransaction();
        $c += demo_seed_modules($pdo, [
            'oid' => $oid, 'iid' => $iid, 'cid' => $cid, 'vid' => $vid,
            'callid' => $callid, 'jid' => $jid, 'bid' => $bid,
            'now' => $now, 'today' => $today, 'd' => $d, 'hash' => $hash,
        ]);
        if ($pdo->inTransaction()) $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $failed[] = demo_explain($e);
    }

    // Phases 4-8, in their own non-fatal transaction so a register not built on
    // this install (or an older schema) cannot strand the operations demo.
    try {
        $pdo->beginTransaction();
        $c += demo_seed_phase48($pdo, [
            'oid' => $oid, 'iid' => $iid, 'cid' => $cid, 'vid' => $vid,
            'callid' => $callid, 'jid' => $jid, 'bid' => $bid,
            'now' => $now, 'today' => $today, 'd' => $d, 'hash' => $hash,
        ]);
        if ($pdo->inTransaction()) $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $failed[] = demo_explain($e);
    }

    // Phase 9 (TOSRM) demo — service-request lifecycle, assignment lifecycle,
    // readiness, SLA targets, a delay, a recurring schedule and a comms entry.
    // MYSQL-SAFE: every migration is run OUTSIDE the transaction below, so no DDL
    // can land inside it (that was the cause of the earlier "no active
    // transaction" error). The block is DML-only and the commit is guarded.
    try {
        if (function_exists('tosrm_migrate_d')) tosrm_migrate_d();   // no-op after boot; if it ever runs, its DDL is OUTSIDE the tx
        if (function_exists('act_migrate'))      act_migrate();       // same — keep any activity-table DDL out of the tx
        $pdo->beginTransaction();
        $c += demo_seed_phase9($pdo, [
            'cid' => $cid, 'vid' => $vid, 'callid' => $callid, 'jid' => $jid, 'oid' => $oid,
            'now' => $now, 'today' => $today, 'd' => $d,
        ]);
        if ($pdo->inTransaction()) $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $failed[] = demo_explain($e);
    }
    return ['counts' => $c, 'failed' => $failed];
}

// ---------------------------------------------------------------------------
//  Phase 9 (TOSRM) demo — layered onto the EXISTING demo cast. DML only (the
//  tables are migrated at boot / before the caller's transaction). Idempotent:
//  a marker recurring row guards re-seeding; tagged 'DEMO' so removal is clean.
// ---------------------------------------------------------------------------
function demo_seed_phase9($pdo, $x) {
    $n = [];
    if (!function_exists('tosrm_sla_set')) return $n;               // Phase 9 not present
    if (ops_one("SELECT id FROM recurring_services WHERE title=?", ['DEMO — Monthly vendor audit'])) return $n;  // already seeded
    $first = fn($m) => (int)(is_array($m) && $m ? reset($m) : 0);
    $d = $x['d']; $now = $x['now']; $today = $x['today'];
    $clientId = $first($x['cid'] ?? []); $callId = $first($x['callid'] ?? []); $jobId = $first($x['jid'] ?? []);

    // 1) Default SLA target set (tagged DEMO so removal finds them).
    foreach (['RESPONSE'=>3, 'SCHEDULING'=>5, 'EXECUTION'=>7, 'REPORT'=>3, 'CLOSURE'=>20] as $st=>$days) {
        tosrm_sla_set(0, $st, $days, 'DEMO'); $n['sla'] = ($n['sla'] ?? 0) + 1;
    }
    // 2) A recurring schedule (the marker row). Not generated here — the cron does that.
    if (tosrm_recurring_create(['client_id'=>$clientId, 'title'=>'DEMO — Monthly vendor audit', 'frequency'=>'MONTHLY',
        'start_date'=>$today, 'template'=>['inspection_type'=>'VENDOR_AUDIT', 'deliverables'=>'VAR', 'sbu'=>'IND']])) $n['recurring'] = 1;

    // 3) Lifecycle on an existing demo call: status, priority/criticality/source, a clarification.
    if ($callId) {
        tosrm_set_status($callId, 'UNDER_REVIEW', 'DEMO — triage');
        $pdo->prepare("UPDATE calls SET priority='HIGH', criticality='SCHEDULE', source='EMAIL' WHERE id=?")->execute([$callId]);
        tosrm_clar_create($callId, ['subject'=>'DEMO — confirm PWHT is in scope', 'detail'=>'Awaiting client reply', 'raised_to'=>'CLIENT']);
        if (function_exists('act_log')) act_log('CALL', $callId, 'EMAIL', 'DEMO — client sent revised PO', ['body'=>'PO rev 2 attached']);
        $n['call'] = 1;
    }
    // 4) Assignment lifecycle + readiness + a delay on an existing demo job.
    if ($jobId) {
        tosrm_assign_hold($jobId, 'CONFIRMED', 'DEMO');
        tosrm_assign_accept($jobId, 'ACCEPTED');
        $seeded = tosrm_readiness_seed($jobId);
        if ($seeded) {
            $items = tosrm_readiness_list($jobId);
            if (isset($items[0])) tosrm_readiness_set((int)$items[0]['id'], 'READY');
            if (isset($items[2])) tosrm_readiness_set((int)$items[2]['id'], 'BLOCKED', 'DEMO — material not at works');
        }
        tosrm_confirm_set($jobId, 'CLIENT', true, 'DEMO — confirmed by email');
        tosrm_delay_add($jobId, ['reason'=>'CLIENT', 'responsibility'=>'CLIENT', 'impact'=>'DEMO — client shifted the date']);
        $n['job'] = 1;
    }
    return $n;
}

// ---------------------------------------------------------------------------
//  Phases 4-8 demo data — vendor assessment/audit/expediting report documents,
//  a fully-populated DEPUTATION posting (Phase 7) and a spread of ISSUES
//  (Phase 8: NCR, observation, discrepancy, CAPA, a deviation and a concession),
//  seeded off the SAME demo cast so the newest modules demo with real, connected
//  records. Idempotent (existence-guarded) and tagged so "Remove demo data"
//  cleans it. Its own failure is non-fatal — reported, never fatal.
// ---------------------------------------------------------------------------
function demo_seed_phase48($pdo, $x) {
    $n = [];
    $now = $x['now']; $today = $x['today']; $d = $x['d'];
    $first = fn($m) => (int)(is_array($m) && $m ? reset($m) : 0);
    $vendorId = $first($x['vid'] ?? []); $clientId = $first($x['cid'] ?? []);
    $inspId   = $first($x['iid'] ?? []); $officeId = $first($x['oid'] ?? []);

    // ---- Phase 7: a fully-populated deputation posting -------------------
    if (function_exists('pdso_migrate') && !ops_one("SELECT id FROM jobs WHERE job_code='JOB-DEP-DEMO'")) {
        try {
            pdso_migrate();
            $pdo->prepare("INSERT INTO calls (call_code,client_id,vendor_id,inspection_type,executing_office_id,sbu,inspection_required_date,status,created_by,created_at) VALUES (?,?,?,?,?,?,?, 'OPEN','demo',?)")
                ->execute(['CALL-DEP-DEMO', $clientId, $vendorId, 'DEPUTATION', $officeId, 'IND', $d(-20), $now]);
            $depCall = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO jobs (job_code,call_id,executing_office_id,inspector_id,job_type,dep_status,dep_site,scheduled_date,inspection_start_date,inspection_end_date,reporting_frequency,sbu,mandays,created_by,created_at) VALUES (?,?,?,?, 'DEPUTATION','','Dahej Petrochemical Complex',?,?,?, 'WEEKLY','IND',30,'demo',?)")
                ->execute(['JOB-DEP-DEMO', $depCall, $officeId, $inspId, $d(-20), $d(-20), $d(25), $now]);
            $depJob = (int)$pdo->lastInsertId();
            pdso_set_status($depJob, 'MOBILIZED', 'Client approval received');
            pdso_set_status($depJob, 'ACTIVE', 'Reported to site');
            pdso_checklist_seed($depJob, 'MOB');
            foreach (pdso_checklist($depJob, 'MOB') as $ci) if ((int)$ci['required'] === 1) pdso_checklist_set((int)$ci['id'], 'COMPLETED');
            pdso_site_log_add(['job_id'=>$depJob,'client_id'=>$clientId,'kind'=>'OBSERVATION','log_date'=>$d(-5),'detail'=>'Scaffold tag missing at unit 3','risk'=>'Medium','action'=>'Retag before next lift','responsible'=>'Site QA','due_on'=>$d(-1),'status'=>'OPEN']);
            pdso_site_log_add(['job_id'=>$depJob,'client_id'=>$clientId,'kind'=>'INSTRUCTION','log_date'=>$d(-3),'detail'=>'Client asked for daily progress photographs','responsible'=>'Resident Engineer','status'=>'CLOSED']);
            foreach ([[-14,'Witness hydro test',8,1],[-13,'Document review — MTC',6,0],[-12,'Stage inspection — piping',8,0]] as $t)
                pdso_timesheet_add(['job_id'=>$depJob,'inspector_id'=>$inspId,'ts_date'=>$d($t[0]),'activity'=>$t[1],'hours'=>$t[2],'ot_hours'=>$t[3]]);
            pdso_att_approval_add(['job_id'=>$depJob,'client_id'=>$clientId,'period_from'=>$d(-20),'period_to'=>$d(-1),'basis'=>'MANDAY','billable_days'=>18,'status'=>'SUBMITTED']);
            pdso_manpower_add(['client_id'=>$clientId,'project'=>'Dahej Petrochemical Complex','position'=>'QA/QC Engineer','required'=>4,'planned'=>4,'mobilized'=>2]);
            pdso_manpower_add(['client_id'=>$clientId,'project'=>'Dahej Petrochemical Complex','position'=>'Welding Inspector','required'=>2,'planned'=>2,'mobilized'=>2]);
            $n['deputation'] = 1;
        } catch (Throwable $e) { /* non-fatal */ }
    }

    // ---- Phase 8: a spread of issues + CAPA + deviation + concession ------
    if (function_exists('ncr_create') && !ops_one("SELECT id FROM nonconformities WHERE source_note='DEMO'")) {
        try {
            $ncr1 = ncr_create(['title'=>'Weld undercut beyond acceptance','severity'=>'MAJOR','source'=>'INTERNAL','source_note'=>'DEMO','issue_type'=>'NCR','issue_class'=>'MAJOR_NC','responsibility'=>'VENDOR','partner_id'=>$vendorId,'office_id'=>$officeId,'clause'=>'AWS D1.1','description'=>'Undercut 1.2mm over 150mm at joint W-14, exceeds 0.5mm acceptance.']);
            ncr_create(['title'=>'Housekeeping in paint booth could improve','severity'=>'OBSERVATION','source'=>'INTERNAL','source_note'=>'DEMO','issue_type'=>'OBSERVATION','issue_class'=>'OBSERVATION','partner_id'=>$vendorId,'office_id'=>$officeId]);
            ncr_create(['title'=>'Heat number not legible on drawing','severity'=>'MINOR','source'=>'REPORT','source_note'=>'DEMO','issue_type'=>'DOC_DISCREPANCY','issue_class'=>'MINOR_NC','partner_id'=>$vendorId,'office_id'=>$officeId]);
            if (function_exists('ncr_to_capa')) { try { ncr_to_capa($ncr1); } catch (Throwable $e) {} }
            if (function_exists('ncdca_departure_create')) {
                $dev = ncdca_departure_create('DEVIATION', ['ncr_id'=>$ncr1,'client_id'=>$clientId,'vendor_id'=>$vendorId,'office_id'=>$officeId,'title'=>'Coating DFT 230µm vs 250µm spec','requirement'=>'DFT ≥ 250µm','planned_condition'=>'250µm','actual_condition'=>'230µm','reason'=>'Applicator maximum build','justification'=>'Salt-spray passed at 230µm; C3 environment','risk'=>'Low','valid_to'=>$d(180)]);
                ncdca_departure_set_status($dev, 'SUBMITTED'); ncdca_departure_set_status($dev, 'APPROVED', 'Technical Authority', 1);
                $con = ncdca_departure_create('CONCESSION', ['client_id'=>$clientId,'vendor_id'=>$vendorId,'office_id'=>$officeId,'title'=>'Accept 2 vessels 1mm under nominal','requirement'=>'Nominal 12mm','reason'=>'Within corrosion allowance','risk'=>'Low']);
                $pdo->prepare("UPDATE issue_departures SET created_by='demo' WHERE id IN (?,?)")->execute([$dev, $con]);
            }
            if (function_exists('ncdca_dispute_create')) ncdca_dispute_create($ncr1, ['party'=>'VENDOR','disputed_field'=>'severity','reason'=>'Vendor believes minor; localised','response_kind'=>'DISAGREE']);
            if (function_exists('ncdca_extension_request')) { $nr = ncr_row($ncr1); $ex = ncdca_extension_request('NCR', $ncr1, (string)($nr['due_on'] ?? ''), $d(30), 'Awaiting replacement material'); if (function_exists('ncdca_extension_approve')) ncdca_extension_approve($ex, 'QA Manager'); }
            $n['issues'] = 3;
        } catch (Throwable $e) { /* non-fatal */ }
    }

    // ---- Phase 4/5/6: assessment / audit / expediting report documents ---
    if (!ops_one("SELECT id FROM report_docs WHERE irn='VASR-DEMO-01'")) {
        try {
            $mkDoc = function($code, $title, $data) use ($pdo, $vendorId, $clientId, $now, $today) {
                $t = ops_one("SELECT id FROM report_types WHERE code=?", [$code]); if (!$t) return 0;
                $pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,title,status,data,vendor_id,client_id,issue_date,finalized,created_by,created_at) VALUES (?,?,?,?, 'ISSUED',?,?,?,?,1,'demo',?)")
                    ->execute([(int)$t['id'], $code, $code.'-DEMO-01', $title, json_encode($data), $vendorId, $clientId, $today, $now]);
                return (int)$pdo->lastInsertId();
            };
            $r = 0;
            $r += $mkDoc('VASR', 'Vendor Assessment — demo vendor', ['assessment_result'=>'QUALIFIED']) ? 1 : 0;
            $r += $mkDoc('VAR',  'Vendor Audit — demo vendor', ['audit_conclusion'=>'CONDITIONAL']) ? 1 : 0;
            $r += $mkDoc('ER',   'Expediting Report — demo PO', []) ? 1 : 0;
            if ($r) $n['review_docs'] = $r;
        } catch (Throwable $e) { /* non-fatal */ }
    }

    return $n;
}

// Turn a driver message into something the person pressing the button can act
// on. The raw SQLSTATE is kept on the end because that is what gets sent to me.
function demo_explain(Throwable $e) {
    $m = $e->getMessage();
    if (stripos($m, 'maximum execution time') !== false || stripos($m, 'server has gone away') !== false)
        return 'The load ran out of time on this server. It writes a few thousand rows, and shared hosting '
             . 'often stops a page at 30 seconds. Ask the host to raise max_execution_time, or load it from '
             . 'the command line: php tools/seed-demo.php. (' . $m . ')';
    if (stripos($m, 'no such table') !== false || stripos($m, "doesn't exist") !== false)
        return 'A table this build expects is not in the database yet — the upload is probably part-finished. '
             . 'Re-upload the app so every file is present, load any page once to let it build the schema, '
             . 'then try again. (' . $m . ')';
    if (stripos($m, 'unknown column') !== false)
        return 'The database is from an older build and is missing a column. Load any page once so the '
             . 'upgrade runs, then try again. (' . $m . ')';
    if (stripos($m, 'duplicate') !== false)
        return 'Some of the demo records are already there. Use "Remove demo data" first, then load again. ('
             . $m . ')';
    if (stripos($m, 'denied') !== false || stripos($m, 'command') !== false)
        return 'The database user is not allowed to do this. It needs INSERT, UPDATE, DELETE and CREATE on '
             . 'the application database. (' . $m . ')';
    return $m;
}

// Remove ONLY the records the demo seed created (identified by the seed's own
// markers), leaving any real data untouched. Lets the demo be loaded again.
function seed_demo_remove() {
    demo_raise_limits();
    $pdo = db();
    $n = 0;
    $pdo->beginTransaction();
    try {
        $del = function($sql, $args = []) use ($pdo, &$n) { $st = $pdo->prepare($sql); $st->execute($args); $n += $st->rowCount(); };
        // Guarded delete — a table not built on this install must not abort the
        // removal and strand the rest of the demo data. Defined early so the
        // Phase-7 child rows can be cleared BEFORE their parent job is deleted.
        $try = function ($sql, $args = []) use ($pdo, &$n) {
            try { $st = $pdo->prepare($sql); $st->execute($args); $n += $st->rowCount(); }
            catch (Throwable $e) { /* table not built here */ }
        };
        // Phase-7 deputation site-ops child rows — cleared while the demo job
        // still exists, so nothing is orphaned when the job is deleted below.
        $try("DELETE FROM dep_status_events WHERE job_id IN (SELECT id FROM jobs WHERE job_code='JOB-DEP-DEMO')");
        $try("DELETE FROM dep_checklist WHERE job_id IN (SELECT id FROM jobs WHERE job_code='JOB-DEP-DEMO')");
        $try("DELETE FROM dep_site_log WHERE job_id IN (SELECT id FROM jobs WHERE job_code='JOB-DEP-DEMO')");
        $try("DELETE FROM dep_timesheet WHERE job_id IN (SELECT id FROM jobs WHERE job_code='JOB-DEP-DEMO')");
        $try("DELETE FROM dep_att_approval WHERE job_id IN (SELECT id FROM jobs WHERE job_code='JOB-DEP-DEMO')");
        $try("DELETE FROM dep_manpower WHERE project='Dahej Petrochemical Complex'");
        // Phase 9 (TOSRM) child rows — cleared while their demo call/job still
        // exists, so nothing is orphaned when the job/call is deleted below.
        $try("DELETE FROM call_status_events WHERE call_id IN (SELECT id FROM calls WHERE created_by='demo')");
        $try("DELETE FROM call_clarifications WHERE call_id IN (SELECT id FROM calls WHERE created_by='demo')");
        $try("DELETE FROM job_readiness WHERE job_id IN (SELECT id FROM jobs WHERE created_by='demo')");
        $try("DELETE FROM job_delays WHERE job_id IN (SELECT id FROM jobs WHERE created_by='demo') OR call_id IN (SELECT id FROM calls WHERE created_by='demo')");
        $try("DELETE FROM assignment_events WHERE job_id IN (SELECT id FROM jobs WHERE created_by='demo')");
        $try("DELETE FROM activities WHERE entity_kind='CALL' AND subject LIKE 'DEMO —%'");
        // Standalone Phase 9 demo markers.
        $try("DELETE FROM recurring_services WHERE title LIKE 'DEMO —%'");
        $try("DELETE FROM sla_targets WHERE note='DEMO'");
        // Transactional records carry created_by='demo'
        $del("DELETE FROM voucher_entries WHERE voucher_id IN (SELECT id FROM vouchers WHERE created_by='demo')");
        $del("DELETE FROM vouchers WHERE created_by='demo'");
        $del("DELETE FROM expenses WHERE job_id IN (SELECT id FROM jobs WHERE created_by='demo')");
        $del("DELETE FROM jobs WHERE created_by='demo'");
        $del("DELETE FROM calls WHERE created_by='demo'");
        // Masters the seed created, by their known codes
        $emps = "('EMP01','EMP02','EMP03','EMP04','SC-001')";
        $del("DELETE FROM inspector_allowances WHERE inspector_id IN (SELECT id FROM inspectors WHERE emp_code IN $emps)");
        $del("DELETE FROM vendor_km_memory WHERE inspector_id IN (SELECT id FROM inspectors WHERE emp_code IN $emps)");
        $del("DELETE FROM inspectors WHERE emp_code IN $emps");
        $del("DELETE FROM boss_numbers WHERE boss_number IN ('40231','40198','40155') OR boss_number LIKE '5090%'");
        $del("DELETE FROM agencies WHERE name IN ('TalentFirst Recruitment','SiteForce Manpower')");
        $del("DELETE FROM requisitions WHERE created_by='demo'");
        $del("DELETE FROM business_partners WHERE code IN ('CL-NIL','CL-SVP','CL-GHE','VN-VAP','VN-MUN') OR code LIKE 'EC-%' OR code LIKE 'EV-%'");
        // ---- Everything the module seed added -------------------------------
        // Phase-8 issue records (tagged source_note='DEMO'), independent of jobs.
        $try("DELETE FROM issue_extensions WHERE entity='NCR' AND entity_id IN (SELECT id FROM nonconformities WHERE source_note='DEMO')");
        $try("DELETE FROM issue_disputes WHERE ncr_id IN (SELECT id FROM nonconformities WHERE source_note='DEMO')");
        $try("DELETE FROM issue_departures WHERE created_by='demo'");
        $try("DELETE FROM ncr_events WHERE ncr_id IN (SELECT id FROM nonconformities WHERE source_note='DEMO')");
        $try("DELETE FROM nonconformities WHERE source_note='DEMO'");
        // Reports first, then what hangs off them.
        $try("DELETE FROM evidence_chain WHERE report_doc_id IN (SELECT id FROM report_docs WHERE created_by='demo')");
        $try("DELETE FROM report_equipment WHERE report_doc_id IN (SELECT id FROM report_docs WHERE created_by='demo')");
        $try("DELETE FROM report_files WHERE created_by='demo'");
        $try("DELETE FROM report_docs WHERE created_by='demo'");
        $try("DELETE FROM endorsements WHERE created_by='demo'");
        $try("DELETE FROM site_visits WHERE by_user IN ('Ravi Kumar','Anil Sharma','Priya Nair','Mohan Rao')
              AND ip='127.0.0.1'");
        // Sales
        $try("DELETE FROM quote_lines WHERE quote_id IN (SELECT id FROM quotations WHERE created_by='demo')");
        $try("DELETE FROM quotations WHERE created_by='demo'");
        $try("DELETE FROM crm_inquiries WHERE created_by='demo'");
        // Accreditation pack — by the seed's own reference prefixes.
        $try("DELETE FROM equipment_calibrations WHERE equipment_id IN (SELECT id FROM equipment WHERE created_by='demo')");
        $try("DELETE FROM equipment WHERE created_by='demo'");
        $try("DELETE FROM qualifications WHERE code IN ('QW-2','NDT-UT2','CIP-1')");
        $try("DELETE FROM witness_assessments WHERE assessor='Sunil Verma'");
        $try("DELETE FROM authorisations WHERE granted_by='Meena Shah'");
        $try("DELETE FROM impartiality_declarations WHERE signed_name IN ('Ravi Kumar','Anil Sharma','Priya Nair','Mohan Rao')");
        $try("DELETE FROM impartiality_threats WHERE raised_by IN ('Sunil Verma','Meena Shah','Sana Kapoor')");
        $try("DELETE FROM person_document_access WHERE document_id IN (SELECT id FROM person_documents WHERE uploaded_by='demo')");
        $try("DELETE FROM person_documents WHERE uploaded_by='demo'");
        $try("DELETE FROM complaint_events WHERE complaint_id IN (SELECT id FROM complaints WHERE ref LIKE 'CMP-2026-%' OR ref LIKE 'APL-2026-%')");
        $try("DELETE FROM complaints WHERE ref LIKE 'CMP-2026-%' OR ref LIKE 'APL-2026-%'");
        $try("DELETE FROM capa_events WHERE capa_id IN (SELECT id FROM capa WHERE ref LIKE 'CAPA-2026-%')");
        $try("DELETE FROM capa WHERE ref LIKE 'CAPA-2026-%'");
        $try("DELETE FROM audit_findings WHERE audit_id IN (SELECT id FROM internal_audits WHERE created_by='demo')");
        $try("DELETE FROM internal_audits WHERE created_by='demo'");
        $try("DELETE FROM mr_inputs WHERE review_id IN (SELECT id FROM mgmt_reviews WHERE created_by='demo')");
        $try("DELETE FROM mr_actions WHERE review_id IN (SELECT id FROM mgmt_reviews WHERE created_by='demo')");
        $try("DELETE FROM mgmt_reviews WHERE created_by='demo'");
        $try("DELETE FROM sw_validations WHERE ref LIKE 'SV-2026-%'");
        $try("DELETE FROM data_check_runs WHERE ran_by='Arjun Patel'");
        $try("DELETE FROM system_failures WHERE ref LIKE 'SF-2026-%'");
        // Client portal
        $try("DELETE FROM portal_audit WHERE client_user_id IN (SELECT id FROM client_users WHERE created_by='demo')");
        $try("DELETE FROM portal_requests WHERE client_user_id IN (SELECT id FROM client_users WHERE created_by='demo')");
        $try("DELETE FROM client_users WHERE created_by='demo'");
        // The portal is switched ON by the demo. Switching it back off on the way
        // out matters more than it looks: leaving a client-facing door open after
        // "remove demo data" is exactly the kind of thing nobody checks.
        setting_set('portal_enabled', '0');

        // Demo login accounts
        $unames = array_map(fn($a) => $a[0], demo_accounts());
        $ph = implode(',', array_fill(0, count($unames), '?'));
        $del("DELETE FROM users WHERE username IN ($ph)", $unames);
        setting_set('demo_seeded', '');
        if ($pdo->inTransaction()) $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['error' => demo_explain($e)];
    }
    return ['deleted' => $n];
}

// ============================================================================
//  The modules the original seed never reached
//
//  Sales, reporting, the whole accreditation pack, the trust layer and the
//  client portal. Every scenario below is deliberate: most exist to make a RULE
//  visible, not to fill a table. A register full of tidy, compliant rows proves
//  nothing — you cannot tell a working gate from a missing one. So roughly half
//  of these are the awkward cases: the expired authorisation, the calibration
//  that ran out, the corrective action that did not work, the complaint nobody
//  acknowledged in time, the management review that cannot be completed.
//
//  docs/DEMO-TEST-PACK.md lists each one with the screen to open and what
//  should happen there.
// ============================================================================
function demo_seed_modules($pdo, $x) {
    $oid = $x['oid']; $iid = $x['iid']; $cid = $x['cid']; $vid = $x['vid'];
    $callid = $x['callid']; $jid = $x['jid'];
    $now = $x['now']; $today = $x['today']; $d = $x['d']; $hash = $x['hash'];
    $n = [];

    // A table that is not built on this install must not take the whole seed
    // down with it — the demo has to survive a partial upgrade.
    $has = function ($t) use ($pdo) {
        try { $pdo->query("SELECT 1 FROM $t LIMIT 1"); return true; }
        catch (Throwable $e) { return false; }
    };
    $ins = function ($sql, $args) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($args); return (int)$pdo->lastInsertId(); };

    // ------------------------------------------------------------------
    //  Sales — inquiries and quotations (CRM)
    // ------------------------------------------------------------------
    if ($has('crm_inquiries') && $has('quotations')) {
        $q1 = $ins("INSERT INTO crm_inquiries (inquiry_no,client_id,client_name,contact_name,contact_email,contact_mobile,
                    subject,service_requirement,sbu,source,received_date,status,created_by,created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)",
            ['INQ-2607-01', $cid['CL-NIL'], 'Narmada Industries Ltd', 'Rakesh Menon', 'rakesh.menon@example.com', '9825011001',
             'Third-party inspection of 12 pressure vessels', "12 vessels, ASME VIII Div 1. Stage plus final at the fabricator's works. Certification required before despatch.",
             'IND', 'EMAIL', $d(-40), 'CONVERTED', $now]);
        // Still sitting there. Deliberately older than the follow-up window, so
        // the inquiry register has something that should be chased.
        $ins("INSERT INTO crm_inquiries (inquiry_no,client_id,client_name,contact_name,contact_email,contact_mobile,
              subject,service_requirement,sbu,source,received_date,status,notes,created_by,created_at)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)",
            ['INQ-2607-02', $cid['CL-GHE'], 'Girnar Energy Hydrocarbon', 'Deepa Balan', 'deepa.balan@example.com', '9825011004',
             'Coating inspection — 4 storage tanks', 'Surface preparation and DFT checks through the painting campaign. Roughly six weeks on site.',
             'OGC', 'PHONE', $d(-26), 'OPEN', 'Chased twice. Waiting on their board approval.', $now]);
        $n['inquiries'] = 2;

        $qi = "INSERT INTO quotations (quote_no,rev,is_current,inquiry_id,client_id,client_name,contact_name,contact_email,
               sbu,office_id,subject,site_location,currency,validity_days,payment_terms,advance_pct,advance_required,
               subtotal,gst_pct,gst_amount,total_amount,status,lost_reason,sent_at,accepted_date,contract_number,
               created_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'INR', ?,?,?,?,?,18,?,?,?,?,?,?,?, 'demo', ?)";
        // Won — and it is the quotation the demo's first contract number came from.
        $qA = $ins($qi, ['Q-2607-001', 0, 1, $q1, $cid['CL-NIL'], 'Narmada Industries Ltd', 'Rakesh Menon', 'rakesh.menon@example.com',
                         'IND', $oid['AMD'], 'TPI — 12 pressure vessels', 'Vapi, Gujarat', 30, '30 days from invoice', 0, 0,
                         150000, 27000, 177000, 'ACCEPTED', '', $d(-36), $d(-30), '40231', $now]);
        // Sent and about to run out of validity — the follow-up screen should care.
        $ins($qi, ['Q-2607-002', 0, 1, null, $cid['CL-SVP'], 'Suryavan Ports & SEZ Ltd', 'Sunita Rao', 'sunita.rao@example.com',
                   'OGC', $oid['MUM'], 'Structural inspection — jetty fabrication', 'Mundra SEZ, Gujarat', 15, '45 days from invoice', 20, 1,
                   240000, 43200, 283200, 'SENT', '', $d(-13), '', '', $now]);
        // Lost, with the reason recorded — the sales dashboard needs a loss to show.
        $ins($qi, ['Q-2607-003', 0, 1, null, $cid['CL-GHE'], 'Girnar Energy Hydrocarbon', 'Deepa Balan', 'deepa.balan@example.com',
                   'OGC', $oid['AMD'], 'Coating inspection — 4 tanks', 'Chakan, Pune', 30, '30 days from invoice', 0, 0,
                   310000, 55800, 365800, 'LOST', 'PRICE', $d(-22), '', '', $now]);
        $n['quotations'] = 3;
        if ($has('quote_lines')) {
            $ql = "INSERT INTO quote_lines (quote_id,line_no,sbu,service_type,description,location,qty,rate,amount) VALUES (?,?,?,?,?,?,?,?,?)";
            try {
                $ins($ql, [$qA, 1, 'IND', 'INSPECTION', 'Stage inspection — 12 vessels', 'Vapi', 10, 9000, 90000]);
                $ins($ql, [$qA, 2, 'IND', 'INSPECTION', 'Final inspection and certification', 'Vapi', 6, 10000, 60000]);
                $n['quote_lines'] = 2;
            } catch (Throwable $e) { /* line schema differs on older installs */ }
        }
    }

    // ------------------------------------------------------------------
    //  Measuring equipment and its calibration (ISO/IEC 17020 §6.2)
    // ------------------------------------------------------------------
    if ($has('equipment')) {
        $ie = "INSERT INTO equipment (code,name,kind,make,model,serial_no,range_spec,accuracy,office_id,inspector_id,
               status,cal_interval_months,owned_by,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)";
        $e1 = $ins($ie, ['EQ-UT-001','Ultrasonic thickness gauge','THICKNESS','Fieldtech','TG-110','TG110-4471','0.8–300 mm','±0.1 mm',
                         $oid['AMD'],$iid['EMP01'],'ACTIVE',12,'OWN','Issued to Ravi Kumar',$now]);
        $e2 = $ins($ie, ['EQ-VC-002','Vernier caliper 0–200 mm','DIMENSIONAL','Metrix','VC-200','VC200-9912','0–200 mm','±0.02 mm',
                         $oid['PUN'],$iid['EMP03'],'ACTIVE',12,'OWN','',$now]);
        $e3 = $ins($ie, ['EQ-DFT-003','Coating thickness gauge','COATING','Layercheck','DFT-9','DFT9-2210','0–1500 µm','±1 µm',
                         $oid['AMD'],$iid['EMP02'],'ACTIVE',6,'OWN','',$now]);
        $e4 = $ins($ie, ['EQ-HT-004','Portable hardness tester','HARDNESS','Duromet','PHT-5','PHT5-0088','20–68 HRC','±2 HRC',
                         $oid['AMD'],null,'ACTIVE',12,'HIRED','Hired for the Mundra campaign',$now]);
        $n['equipment'] = 4;

        if ($has('equipment_calibrations')) {
            $ic = "INSERT INTO equipment_calibrations (equipment_id,cert_no,cal_date,valid_to,cal_body,traceable_to,result,remarks,uploaded_by,uploaded_at)
                   VALUES (?,?,?,?,?,?,?,?, 'demo', ?)";
            // In date, comfortably.
            $cal1 = $ins($ic, [$e1,'CAL/2026/1187',$d(-150),$d(215),'Statewide Calibration Laboratory','National standards','PASS','',$now]);
            // Runs out in twelve days — the register should be warning about it.
            $ins($ic, [$e2,'CAL/2026/1204',$d(-353),$d(12),'Statewide Calibration Laboratory','National standards','PASS','',$now]);
            // ALREADY EXPIRED, and the instrument is still marked ACTIVE. This is
            // the one that matters: an instrument nobody withdrew.
            $ins($ic, [$e3,'CAL/2026/0990',$d(-200),$d(-18),'Statewide Calibration Laboratory','National standards','PASS','',$now]);
            // A calibration that FAILED. It stays on the register, because a
            // failure is the record — the instrument must not be used.
            $ins($ic, [$e4,'CAL/2026/1310',$d(-30),$d(335),'Statewide Calibration Laboratory','National standards','FAIL',
                       'Reading 4 HRC low across the range. Returned to the hire company.',$now]);
            $n['calibrations'] = 4;
            $x['cal1'] = $cal1;
        }
        $x['eq1'] = $e1;
    }

    // ------------------------------------------------------------------
    //  Competence and authorisation (§6.1)
    // ------------------------------------------------------------------
    if ($has('qualifications')) {
        $iq = "INSERT INTO qualifications (code,name,scheme,issuing_body,level,renewal_months,active,notes,created_at) VALUES (?,?,?,?,?,?,1,?,?)";
        $ins($iq, ['QW-2','Welding Inspector Level 2','Welding','National welding institute','2',60,'',$now]);
        $ins($iq, ['NDT-UT2','NDT Ultrasonic Level II','NDT','National NDT board','II',60,'',$now]);
        $ins($iq, ['CIP-1','Coating Inspector Level 1','Coating','Coatings institute','1',36,'',$now]);
        $n['qualifications'] = 3;
    }
    if ($has('authorisations')) {
        $ia = "INSERT INTO authorisations (inspector_id,level,scope_kind,scope_value,valid_from,valid_to,status,status_reason,
               granted_by,granted_at,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        $ins($ia, [$iid['EMP01'],'SENIOR_INSPECTOR','ACTIVITY','WELDING',$d(-300),$d(240),'ACTIVE','','Meena Shah',$now,'']);
        // Expires in nine days. Allocating him after that must be refused when
        // enforcement is switched on — which is the point of the nine days.
        $ins($ia, [$iid['EMP02'],'INSPECTOR','ACTIVITY','COATING',$d(-356),$d(9),'ACTIVE','','Meena Shah',$now,'Renewal paperwork with the training provider.']);
        // Suspended, with the reason on the record.
        $ins($ia, [$iid['EMP03'],'INSPECTOR','ACTIVITY','NDT',$d(-200),$d(160),'SUSPENDED',
                   'Withdrawn pending re-assessment after the Mundra observation.','Meena Shah',$now,'']);
        // Already lapsed — and he is a sub-contractor, which is exactly the
        // combination that gets missed.
        $ins($ia, [$iid['SC-001'],'INSPECTOR','ACTIVITY','LIFTING',$d(-400),$d(-5),'ACTIVE','','Meena Shah',$now,'']);
        $n['authorisations'] = 4;
    }
    if ($has('witness_assessments')) {
        $iw = "INSERT INTO witness_assessments (inspector_id,job_id,assessed_on,assessor,location,scores,outcome,remarks,next_due,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?)";
        $ins($iw, [$iid['EMP01'],$jid['J-2607-101'],$d(-11),'Sunil Verma','Vapi Chemical Works',
                   json_encode(['preparation'=>4,'technique'=>5,'records'=>4,'conduct'=>5]),'PASS','Thorough. Records written up on site.',$d(354),$now]);
        // A failed witness assessment, which is what should drive the suspension above.
        $ins($iw, [$iid['EMP03'],$jid['J-2607-103'],$d(-7),'Sunil Verma','Mundra Fabrication Yard',
                   json_encode(['preparation'=>2,'technique'=>2,'records'=>3,'conduct'=>4]),'FAIL',
                   'Calibration of the gauge not checked before use; readings not recorded contemporaneously.',$d(83),$now]);
        $n['witness_assessments'] = 2;
    }

    // ------------------------------------------------------------------
    //  Impartiality (§4.1)
    // ------------------------------------------------------------------
    if ($has('impartiality_declarations')) {
        $idl = "INSERT INTO impartiality_declarations (person_kind,person_id,declared_on,valid_to,has_conflicts,statement,signed_name,created_at)
                VALUES ('INSPECTOR',?,?,?,?,?,?,?)";
        $ins($idl, [$iid['EMP01'],$d(-120),$d(245),0,'No interest in any client or supplier we inspect for.','Ravi Kumar',$now]);
        $ins($idl, [$iid['EMP02'],$d(-115),$d(250),0,'No interest to declare.','Anil Sharma',$now]);
        // Declares a real conflict — the interesting row.
        $ins($idl, [$iid['EMP03'],$d(-100),$d(265),1,
                    'My brother is a production supervisor at one of our vendor sites (Mundra Fabrication Yard). I have not worked and will not work on their inspections.',
                    'Priya Nair',$now]);
        // Lapsed six days ago. Somebody has to notice.
        $ins($idl, [$iid['SC-001'],$d(-371),$d(-6),0,'No interest to declare.','Mohan Rao',$now]);
        $n['impartiality_declarations'] = 4;
    }
    if ($has('impartiality_threats')) {
        $it = "INSERT INTO impartiality_threats (threat_kind,person_kind,person_id,partner_id,office_id,description,raised_by,raised_on,
               status,safeguards,decided_by,decided_on,review_on,created_at) VALUES (?, 'INSPECTOR',?,?,?,?,?,?,?,?,?,?,?,?)";
        $ins($it, ['FAMILIARITY',$iid['EMP03'],$vid['VN-MUN'],$oid['PUN'],
                   'Engineer has a close relative in production at this vendor.','Sunil Verma',$d(-98),'CLOSED',
                   'Removed from all deputations to this vendor; a second engineer reviews any report naming it.','Meena Shah',$d(-95),$d(85),$now]);
        // Open, and its review date has already passed.
        $ins($it, ['SELF_INTEREST',$iid['EMP02'],$cid['CL-GHE'],$oid['AMD'],
                   'Engineer was employed by this client until two years ago and holds a small shareholding.','Meena Shah',$d(-60),'OPEN',
                   '','','',$d(-9),$now]);
        $ins($it, ['INTIMIDATION',null,$cid['CL-NIL'],$oid['AMD'],
                   'Client asked, on a call, for a rejection to be re-graded to "accepted with observations" before despatch.',
                   'Sana Kapoor',$d(-15),'OPEN','','','',$d(20),$now]);
        $n['impartiality_threats'] = 3;
    }

    // ------------------------------------------------------------------
    //  Identity documents (DPDP guardrails)
    //  Deliberately small. It is real personal data even in a demo, so there
    //  are three rows that show the three states and nothing more.
    // ------------------------------------------------------------------
    if ($has('person_documents')) {
        $ip = "INSERT INTO person_documents (person_kind,person_id,doc_kind,doc_number,number_last4,issuing_authority,issuing_country,
               issued_on,expires_on,purpose,consent_on,consent_note,retain_until,redacted_at,redacted_by,note,uploaded_by,uploaded_at)
               VALUES ('INSPECTOR',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)";
        $ins($ip, [$iid['EMP01'],'PASSPORT','Z1234567','4567','Regional Passport Office','India',$d(-1100),$d(1600),
                   'Refinery site access pass — the client requires passport details for the gate list.',$d(-90),
                   'Given in writing by e-mail, and can be withdrawn at any time.',$d(1965),'','','',$now]);
        // Past its own retention date: the retention run should offer to redact it.
        $ins($ip, [$iid['EMP02'],'DRIVING_LICENCE','GJ0120099887','9887','Regional Transport Office','India',$d(-2000),$d(-400),
                   'Hire-car authorisation for the Mundra campaign, which finished last year.',$d(-800),
                   'Given in writing.', $d(-35),'','','',$now]);
        // Already redacted — the number is gone, the record of having held it stays.
        $ins($ip, [$iid['EMP03'],'PASSPORT','','8812','Regional Passport Office','India',$d(-1500),$d(900),
                   'Site access for a visit that did not go ahead.',$d(-300),'Given in writing.',$d(-60),
                   $d(-58) . 'T10:00:00+05:30','Arjun Patel','Redacted once the purpose fell away.',$now]);
        $n['identity_documents'] = 3;
    }

    return $n + demo_seed_modules_b($pdo, $x, $has, $ins);
}

// Part two, split only so neither function runs to three hundred lines:
// reports and their evidence, the trust layer, complaints and appeals,
// corrective actions, internal audit, management review, data control and the
// client portal. Same rule as part one — most rows exist to make a gate visible.
function demo_seed_modules_b($pdo, $x, $has, $ins) {
    $oid = $x['oid']; $iid = $x['iid']; $cid = $x['cid']; $vid = $x['vid'];
    $callid = $x['callid']; $jid = $x['jid'];
    $now = $x['now']; $d = $x['d'];
    $n = [];
    $val = function ($sql, $a = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($a); $v = $s->fetchColumn(); $s->closeCursor(); return $v; };

    // ------------------------------------------------------------------
    //  Inspection reports, and the evidence behind them
    // ------------------------------------------------------------------
    $docs = [];
    if ($has('report_docs')) {
        // Whatever report type this install shipped with — the seed must not
        // depend on a particular one existing.
        $rt   = (int)$val("SELECT id FROM report_types WHERE active=1 ORDER BY sort_order, id LIMIT 1");
        $rtc  = (string)$val("SELECT code FROM report_types WHERE id=?", [$rt]);
        $ir = "INSERT INTO report_docs (irn,report_type_id,type_code,title,client_id,vendor_id,call_id,job_id,
               office_id,sbu,po_ref,location,inspector_id,inspection_date,issue_date,result,release_status,status,
               data,remarks,finalized,finalized_at,finalized_by,submitted_at,approved_at,approved_by,deleted,
               created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0, 'demo', ?)";

        // 1. Issued, accepted, and the one the client portal and /verify use.
        $docs['issued'] = $ins($ir, ['MGH/AMD/2026/D-0001',$rt,$rtc,'Final inspection — 12 pressure vessels',
            $cid['CL-NIL'],$vid['VN-VAP'],$callid['C-2607-001'],$jid['J-2607-101'],$oid['AMD'],'IND','PO-NIL-77120',
            'Vapi, Gujarat',$iid['EMP01'],$d(-12),$d(-10),'ACCEPTED','RELEASED','ISSUED','[]',
            'All 12 vessels witnessed. Dimensional and NDT records verified against the approved QAP.',
            1,$d(-10).'T18:20:00+05:30','Ravi Kumar',$d(-11).'T09:00:00+05:30',$d(-10).'T17:40:00+05:30','Sunil Verma',$now]);

        // 2. Issued but REJECTED — the outcome nobody demos, and the one a
        //    client argues about. Release status says not released.
        $docs['rejected'] = $ins($ir, ['MGH/AMD/2026/D-0002','','','Stage inspection — jetty fabrication',
            $cid['CL-SVP'],$vid['VN-MUN'],$callid['C-2607-002'],$jid['J-2607-102'],$oid['AMD'],'OGC','PO-SVP-40881',
            'Mundra SEZ, Gujarat',$iid['EMP02'],$d(-10),$d(-8),'REJECTED','NOT_RELEASED','ISSUED','[]',
            'Weld root penetration outside the specification on 3 of 14 joints. Material not released for despatch.',
            1,$d(-8).'T19:05:00+05:30','Anil Sharma',$d(-9).'T10:00:00+05:30',$d(-8).'T18:30:00+05:30','Sunil Verma',$now]);
        $pdo->prepare("UPDATE report_docs SET report_type_id=?, type_code=? WHERE id=?")->execute([$rt,$rtc,$docs['rejected']]);

        // 3. Submitted, sitting with the approver.
        $docs['submitted'] = $ins($ir, ['MGH/PUN/2026/D-0003',$rt,$rtc,'Piping inspection — line 400 series',
            $cid['CL-GHE'],null,$callid['C-2607-003'],$jid['J-2607-103'],$oid['PUN'],'IND','PO-GHE-2201',
            'Chakan, Pune',$iid['EMP03'],$d(-8),'','ACCEPTED_COND','PENDING','SUBMITTED','[]',
            'Two observations raised; awaiting approval.',0,'','',$d(-6).'T16:10:00+05:30','','',$now]);

        // 4. Still a draft. Must NOT appear on the client portal.
        $docs['draft'] = $ins($ir, ['MGH/AMD/2026/D-0004',$rt,$rtc,'Welding audit — fabrication shop',
            $cid['CL-NIL'],$vid['VN-VAP'],$callid['C-2607-004'],$jid['J-2607-104'],$oid['AMD'],'IND','PO-NIL-77120',
            'Vapi, Gujarat',$iid['EMP01'],$d(-1),'','','PENDING','DRAFT','[]','Being written up.',0,'','','','','',$now]);
        $n['reports'] = 4;

        // Evidence. A 1x1 JPEG is enough — what is being demonstrated is where
        // the location came from, not what the picture shows.
        if ($has('report_files')) {
            $jpg = 'data:image/jpeg;base64,' . base64_encode(base64_decode(
                '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
              . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
              . 'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='));
            $ifl = "INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,gps,note,created_by,created_at)
                    VALUES (?,?,?,?,?,?,?,?, 'demo', ?)";
            $f1 = $ins($ifl, [$docs['issued'],'photos','photo','vessel-01-weld-cap.jpg','image/jpeg',$jpg,'','Cap pass, vessel 1 of 12',$now]);
            $f2 = $ins($ifl, [$docs['issued'],'photos','photo','vessel-01-nameplate.jpg','image/jpeg',$jpg,'','Nameplate and heat number',$now]);
            $f3 = $ins($ifl, [$docs['rejected'],'photos','photo','joint-07-root.jpg','image/jpeg',$jpg,'','Root run, joint 7 — the rejection',$now]);
            $n['report_evidence'] = 3;

            // The location facts, kept apart exactly as the trust layer requires:
            // the camera's fix is the evidence; the upload fix is not.
            // Driver-agnostic column check. `pragma_table_info(...)` is SQLite-only
            // syntax and raised a 1064 error on MySQL/MariaDB, which is what stopped
            // the extra registers loading on a real server.
            if ((function_exists('column_exists') && column_exists('report_files', 'exif_lat'))
                || $has('evidence_chain')) {
                try {
                    $up = $pdo->prepare("UPDATE report_files SET exif_lat=?, exif_lon=?, up_lat=?, up_lon=?, up_acc=?,
                                         geo_source=?, taken_at=?, received_at=?, clock_skew=?, flags=? WHERE id=?");
                    // Taken at the plant (camera), uploaded from home that evening.
                    // A late upload is normal and is deliberately NOT flagged.
                    $up->execute([20.3720000,72.9080000,23.0225000,72.5714000,18,'EXIF',$d(-12).'T11:41:00+05:30',$d(-12).'T21:14:00+05:30',0,'',$f1]);
                    $up->execute([20.3721000,72.9081000,23.0225000,72.5714000,18,'EXIF',$d(-12).'T11:52:00+05:30',$d(-12).'T21:14:00+05:30',0,'',$f2]);
                    // No location in the photograph at all — a corporate phone
                    // that strips EXIF. Flagged as upload-only, not as a lie.
                    $up->execute([null,null,22.8300000,70.1200000,2200,'UPLOAD','',$d(-8).'T20:02:00+05:30',0,'NO_GEO,UPLOAD_ONLY',$f3]);
                } catch (Throwable $e) { /* pre-Phase-4 install */ }
            }
            // Build the hash chain through the real function, so /verify says
            // "unaltered" rather than "broken" — a demo with a broken chain
            // teaches the opposite of the lesson.
            if (function_exists('chain_append')) foreach ([$f1,$f2,$f3] as $fid) chain_append($fid);
        }
        // The public verification code on the issued report.
        if (function_exists('verify_code_for')) {
            foreach (['issued','rejected'] as $k)
                verify_code_for(ops_one("SELECT * FROM report_docs WHERE id=?", [$docs[$k]]));
        }
        // Which instrument the issued report relied on — the link that turns the
        // equipment register into evidence.
        if ($has('report_equipment')) {
            $eq = (int)$val("SELECT id FROM equipment WHERE code='EQ-UT-001'");
            $cal = (int)$val("SELECT id FROM equipment_calibrations WHERE equipment_id=? ORDER BY id DESC LIMIT 1", [$eq]);
            if ($eq) $ins("INSERT INTO report_equipment (report_doc_id,equipment_id,used_on,calibration_id,added_by,added_at)
                           VALUES (?,?,?,?, 'demo', ?)", [$docs['issued'],$eq,$d(-12),$cal ?: null,$now]);
        }
    }

    // Endorsement of a manufacturer's document.
    if ($has('endorsements')) {
        $ie = "INSERT INTO endorsements (endorsement_no,doc_type,title,vendor_id,client_id,report_doc_id,po_ref,heat_no,
               item_desc,office_id,sbu,inspector_id,status,decision,decision_remarks,submitted_at,endorsed_at,endorsed_by,
               finalized,deleted,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0, 'demo', ?)";
        $ins($ie, ['MGH/AMD/2026/E-0001','MTC','Mill test certificate — SA516 Gr 70 plate',$vid['VN-VAP'],$cid['CL-NIL'],
                   $docs['issued'] ?? null,'PO-NIL-77120','HT-88213','12 mm plate, 6 sheets',$oid['AMD'],'IND',$iid['EMP01'],
                   'ENDORSED','ACCEPTED','Chemistry and mechanicals verified against the specification.',
                   $d(-13).'T11:00:00+05:30',$d(-12).'T15:20:00+05:30','Sunil Verma',1,$now]);
        // One refused — an endorsement register with no refusals is not credible.
        $ins($ie, ['MGH/AMD/2026/E-0002','MTC','Mill test certificate — weld consumable batch',$vid['VN-MUN'],$cid['CL-SVP'],
                   null,'PO-SVP-40881','HT-91004','E7018, 40 packets',$oid['AMD'],'OGC',$iid['EMP02'],
                   'REJECTED','REJECTED','Certificate is a photocopy with no mill stamp; original requested and not produced.',
                   $d(-9).'T09:30:00+05:30','','',0,$now]);
        $n['endorsements'] = 2;
    }

    // ------------------------------------------------------------------
    //  The trust layer — somebody was on site, at a time
    // ------------------------------------------------------------------
    if ($has('site_visits')) {
        $iv = "INSERT INTO site_visits (job_id,inspector_id,kind,lat,lon,accuracy,device_at,at,clock_skew,flags,note,by_user,ip,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?, '127.0.0.1', ?)";
        // A clean pair: arrived 09:12, left 14:40. This is the line a client wants.
        $ins($iv, [$jid['J-2607-101'],$iid['EMP01'],'ENTRY',20.3719000,72.9079000,12,$d(-12).'T09:12:00+05:30',$d(-12).'T09:12:04+05:30',0,'','Arrived at the works gate.','Ravi Kumar',$now]);
        $ins($iv, [$jid['J-2607-101'],$iid['EMP01'],'EXIT', 20.3720500,72.9080500,14,$d(-12).'T14:40:00+05:30',$d(-12).'T14:40:03+05:30',0,'','Left site.','Ravi Kumar',$now]);
        // An arrival with no departure — the engineer forgot. Common, and the
        // close gate should notice.
        $ins($iv, [$jid['J-2607-106'],$iid['EMP02'],'ENTRY',19.0760000,72.8777000,25,$d(-5).'T10:05:00+05:30',$d(-5).'T10:05:07+05:30',0,'','','Anil Sharma',$now]);
        // A device clock 47 minutes out. Recorded, compared, never trusted.
        $ins($iv, [$jid['J-2607-103'],$iid['EMP03'],'ENTRY',18.7500000,73.8600000,9,$d(-8).'T08:13:00+05:30',$d(-8).'T09:00:00+05:30',47,'CLOCK_SKEW','','Priya Nair',$now]);
        $n['site_visits'] = 4;
    }

    // ------------------------------------------------------------------
    //  Complaints and appeals (§7.5, §7.6)
    // ------------------------------------------------------------------
    $cmpIds = [];
    if ($has('complaints')) {
        $ic = "INSERT INTO complaints (ref,kind,appeal_of_id,source,channel,partner_id,anonymous,complainant_name,
               complainant_email,complainant_phone,subject,description,received_on,received_by,job_id,report_irn,
               inspector_id,office_id,validity,validity_note,validity_by,validity_on,acknowledged_on,acknowledged_by,
               assigned_to,investigation,root_cause,outcome,decision_note,decided_by,decided_on,notified_on,notified_by,
               notify_note,capa_ref,status,closed_on,closed_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        // 1. The full journey, done properly — and it raised a corrective action.
        $cmpIds['closed'] = $ins($ic, ['CMP-2026-001','COMPLAINT',null,'CLIENT','EMAIL',$cid['CL-NIL'],0,
            'Rakesh Menon','rakesh.menon@example.com','9825011001',
            'Report issued eight days after the inspection',
            "We were told the report would reach us within two working days of the inspection. It arrived on the eighth day and our despatch was held up.",
            $d(-28),'Sana Kapoor',$jid['J-2607-101'],'MGH/AMD/2026/D-0001',$iid['EMP01'],$oid['AMD'],
            'VALID','Our own turnaround commitment was not met.','Meena Shah',$d(-27),$d(-27),'Sana Kapoor','Sunil Verma',
            "Checked the deputation record and the report register. Inspection finished on the 12th, report finalised on the 20th. The engineer was deputed to two further sites in between with no cover arranged.",
            'Scheduling did not allow report-writing time between consecutive deputations.',
            'UPHELD',"Complaint upheld. Our commitment was not met and the cause was ours, not the client's.",
            'Meena Shah',$d(-21),$d(-20),'Sana Kapoor','Told by e-mail, with the corrective action reference.',
            'CAPA-2026-001','CLOSED',$d(-20),'Meena Shah',$now]);

        // 2. Open, and PAST the acknowledgement deadline. The register must say so.
        $cmpIds['late'] = $ins($ic, ['CMP-2026-002','COMPLAINT',null,'CLIENT','PHONE',$cid['CL-SVP'],0,
            'Sunita Rao','sunita.rao@example.com','9825011002',
            'Engineer arrived without the calibration certificate for his gauge',
            "Our gate would not admit the instrument without a current calibration certificate and half a day was lost.",
            $d(-9),'Vivek Menon',$jid['J-2607-102'],'MGH/AMD/2026/D-0002',$iid['EMP02'],$oid['AMD'],
            'PENDING','','','','','','','','', 'PENDING','','','','','','','','OPEN','','',$now]);

        // 3. An APPEAL against the decision on the first one. Under §7.6 the
        //    person who decided the complaint may not decide the appeal.
        $cmpIds['appeal'] = $ins($ic, ['APL-2026-001','APPEAL',$cmpIds['closed'],'CLIENT','EMAIL',$cid['CL-NIL'],0,
            'Rakesh Menon','rakesh.menon@example.com','9825011001',
            'Appeal — we do not accept the compensation offered',
            "We accept the finding but not the remedy. We are asking for the inspection fee to be waived.",
            $d(-16),'Sana Kapoor',$jid['J-2607-101'],'MGH/AMD/2026/D-0001',$iid['EMP01'],$oid['AMD'],
            'VALID','Appeal is against the remedy, not the finding.','Arjun Patel',$d(-15),$d(-15),'Sana Kapoor','Rahul Desai',
            '','','PENDING','','','','','','','','OPEN','','',$now]);

        // 4. Anonymous, raised internally. Somebody has to be able to complain
        //    without putting their name to it.
        $ins($ic, ['CMP-2026-003','COMPLAINT',null,'STAFF','WEB',null,1,'','','',
            'Pressure to soften a finding before despatch',
            "A coordinator was asked by a client contact to re-grade a rejection. Raising this anonymously.",
            $d(-6),'—',null,'',null,$oid['AMD'],
            'PENDING','','','',$d(-5),'Arjun Patel','Rahul Desai','','','PENDING','','','','','','','','OPEN','','',$now]);
        $n['complaints'] = 4;

        if ($has('complaint_events')) {
            $ie = "INSERT INTO complaint_events (complaint_id,at,action,actor,note) VALUES (?,?,?,?,?)";
            foreach ([['RECEIVED',-28,'Sana Kapoor','Taken by e-mail.'],['ACKNOWLEDGED',-27,'Sana Kapoor','Acknowledged same day.'],
                      ['VALIDITY',-27,'Meena Shah','Accepted as valid.'],['INVESTIGATED',-23,'Sunil Verma','Records reviewed.'],
                      ['DECIDED',-21,'Meena Shah','Upheld.'],['NOTIFIED',-20,'Sana Kapoor','Client told.'],
                      ['CLOSED',-20,'Meena Shah','Closed with a corrective action raised.']] as $ev)
                $ins($ie, [$cmpIds['closed'], $d($ev[1]).'T10:00:00+05:30', $ev[0], $ev[2], $ev[3]]);
            $n['complaint_events'] = 7;
        }
    }

    // ------------------------------------------------------------------
    //  Corrective actions (§8.7)
    // ------------------------------------------------------------------
    $capaIds = [];
    if ($has('capa')) {
        $ic = "INSERT INTO capa (ref,source,source_ref,complaint_id,audit_finding_id,follows_id,title,description,clause,
               severity,office_id,raised_by,raised_on,immediate_action,root_cause,rc_method,similar_checked,similar_found,
               similar_note,action_plan,owner,due_on,completed_on,completed_by,verify_due,verified_on,verified_by,
               effective,effectiveness_note,status,closed_on,closed_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        // 1. The complete one: root cause, method, the §8.7.2 d) similar-work
        //    answer, a plan, done, and independently verified as effective.
        $capaIds['closed'] = $ins($ic, ['CAPA-2026-001','COMPLAINT','CMP-2026-001',$cmpIds['closed'] ?? null,null,null,
            'Report turnaround exceeded on consecutive deputations',
            'Reports are late whenever an engineer is deputed to a second site before the first report is written.',
            '7.4','MAJOR',$oid['AMD'],'Meena Shah',$d(-21),
            'The two outstanding reports were written up the same week and the client was told.',
            'Scheduling allowed back-to-back deputations with no report-writing day in between. The rule existed on paper and nothing enforced it.',
            'FIVE_WHYS',1,'YES','Two other engineers had the same pattern in the last quarter; both checked and cleared.',
            "Report-writing time is now blocked in the availability board and the allocation screen refuses a deputation that leaves no writing day. Coordinators briefed on 12th.",
            'Sunil Verma',$d(-7),$d(-9),'Sunil Verma',$d(-2),$d(-2),'Arjun Patel',
            'YES','Checked the last fourteen deputations: no report exceeded two working days. The gate refused two attempts, both re-planned.',
            'CLOSED',$d(-2),'Arjun Patel',$now]);

        // 2. Open and OVERDUE — its due date has already gone.
        $capaIds['overdue'] = $ins($ic, ['CAPA-2026-002','AUDIT','IA-2026-001',null,null,null,
            'Calibration certificates not carried to site',
            'An engineer attended with an instrument whose certificate was not available at the gate.',
            '6.2','MINOR',$oid['AMD'],'Arjun Patel',$d(-18),
            'Certificate e-mailed to the client the same afternoon.',
            //  root_cause, rc_method, similar_checked, similar_found, similar_note
            '','',0,'','',
            //  action_plan, owner, due_on, completed_on, completed_by, verify_due,
            //  verified_on, verified_by, effective, effectiveness_note, status, closed_on, closed_by
            '','Sunil Verma',$d(-4),'','','','','','','','OPEN','','',$now]);

        // 3. Done, verified — and it did NOT work. This must not close as
        //    effective, and it needs a successor action.
        $capaIds['failed'] = $ins($ic, ['CAPA-2026-003','SELF_IDENTIFIED','',null,null,null,
            'Evidence photographs arriving without a location',
            'Photographs from two corporate handsets carry no location, so nothing ties them to the site.',
            '7.3','MINOR',$oid['PUN'],'Sunil Verma',$d(-40),
            'Engineers asked to use the site check-in as well.',
            'Handsets are managed by the client and strip location data on capture.','FIVE_WHYS',1,'YES',
            'Both Pune handsets affected; Ahmedabad unaffected.',
            'Asked the engineers to switch location on before photographing.',
            'Kiran Joshi',$d(-20),$d(-19),'Kiran Joshi',$d(-6),$d(-6),'Arjun Patel',
            'NO','Checked twenty photographs taken since: eleven still carry no location. Asking people to change a setting the handset resets on reboot was never going to hold.',
            'OPEN','','',$now]);
        $n['capa'] = 3;

        if ($has('capa_events')) {
            $iev = "INSERT INTO capa_events (capa_id,at,action,actor,note) VALUES (?,?,?,?,?)";
            foreach ([['RAISED',-21,'Meena Shah',''],['ROOT_CAUSE',-16,'Sunil Verma','Five whys with the coordinators.'],
                      ['PLAN',-14,'Sunil Verma',''],['COMPLETED',-9,'Sunil Verma',''],
                      ['VERIFIED',-2,'Arjun Patel','Effective.'],['CLOSED',-2,'Arjun Patel','']] as $ev)
                $ins($iev, [$capaIds['closed'], $d($ev[1]).'T11:00:00+05:30', $ev[0], $ev[2], $ev[3]]);
            $ins($iev, [$capaIds['failed'], $d(-6).'T11:00:00+05:30','VERIFIED','Arjun Patel','Not effective — see the note.']);
            $n['capa_events'] = 7;
        }
    }

    // ------------------------------------------------------------------
    //  Internal audit and management review (§8.8, §8.9)
    // ------------------------------------------------------------------
    if ($has('internal_audits')) {
        $ia = "INSERT INTO internal_audits (ref,planned_on,carried_out_on,clauses,scope,office_id,area_owner,auditor,method,
               summary,status,reported_on,closed_on,closed_by,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)";
        $a1 = $ins($ia, ['IA-2026-001',$d(-30),$d(-25),'6.2,7.3,7.4',
            'Equipment control, inspection records and report turnaround at Ahmedabad.',$oid['AMD'],'Meena Shah','Arjun Patel',
            'Records sampled (12 deputations), two engineers interviewed, equipment register walked.',
            'Two findings raised. Report turnaround already under corrective action from a client complaint.',
            'CLOSED',$d(-24),$d(-3),'Rahul Desai',$now]);
        // Planned, and the date has passed. An audit programme with nothing
        // overdue on it is not a real audit programme.
        $ins($ia, ['IA-2026-002',$d(-6),'','4.1,6.1','Impartiality and competence at Pune.',$oid['PUN'],'Kiran Joshi','Arjun Patel',
            '','','PLANNED','','','',$now]);
        $n['internal_audits'] = 2;

        if ($has('audit_findings')) {
            $if = "INSERT INTO audit_findings (audit_id,clause,kind,detail,evidence,capa_ref,capa_id,raised_by,raised_on) VALUES (?,?,?,?,?,?,?,?,?)";
            $ins($if, [$a1,'6.2','NONCONFORMITY',
                'An instrument was taken to site without its calibration certificate being available.',
                'Deputation J-2607-102; client complaint CMP-2026-002.','CAPA-2026-002',$capaIds['overdue'] ?? null,'Arjun Patel',$d(-25)]);
            $ins($if, [$a1,'7.4','OBSERVATION',
                'Report turnaround exceeded the stated commitment on three deputations in the sample of twelve.',
                'Report register, 1st to 20th.','CAPA-2026-001',$capaIds['closed'] ?? null,'Arjun Patel',$d(-25)]);
            $ins($if, [$a1,'7.3','OBSERVATION',
                'Two evidence photographs carried no location and no site check-in.','Report MGH/AMD/2026/D-0002.','','','Arjun Patel',$d(-25)]);
            $n['audit_findings'] = 3;
        }
    }
    if ($has('mgmt_reviews')) {
        $im = "INSERT INTO mgmt_reviews (ref,held_on,period_from,period_to,chair,attendees,status,completed_on,completed_by,created_by,created_at)
               VALUES (?,?,?,?,?,?,?,?,?, 'demo', ?)";
        $r1 = $ins($im, ['MR-2026-01',$d(-14),$d(-195),$d(-15),'Rahul Desai',
            'Rahul Desai (chair), Neha Iyer, Meena Shah, Arjun Patel, Sunil Verma','COMPLETED',$d(-14),'Rahul Desai',$now]);
        // A draft with its inputs not yet recorded — completing it must be refused.
        $r2 = $ins($im, ['MR-2026-02',$d(7),$d(-14),$d(6),'Rahul Desai','','DRAFT','','',$now]);
        $n['management_reviews'] = 2;

        if ($has('mr_inputs') && defined('MR_INPUTS')) {
            $ii = "INSERT INTO mr_inputs (review_id,input_key,measured,note,noted_by,noted_at) VALUES (?,?,?,?,?,?)";
            $notes = [
                'Complaints and appeals received, and how they were dealt with.',
                'Results of internal audits and the actions arising.',
                'Corrective actions: raised, closed, and whether they worked.',
                'Impartiality: threats identified in the period and the safeguards applied.',
                'Competence and authorisation status of inspection personnel.',
            ];
            $k = 0;
            foreach (array_keys(MR_INPUTS) as $key) {
                $ins($ii, [$r1, $key, '', $notes[$k % count($notes)], 'Arjun Patel', $d(-15) . 'T12:00:00+05:30']);
                $k++;
            }
            $n['mr_inputs'] = $k;
        }
        if ($has('mr_actions')) {
            $iact = "INSERT INTO mr_actions (review_id,kind,decision,owner,due_on,status,done_on,done_note,capa_ref,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)";
            $ins($iact, [$r1,'IMPROVEMENT','Block report-writing time in the availability board before allocating a second deputation.','Sunil Verma',$d(-4),'DONE',$d(-9),'Gate live since the 12th.','CAPA-2026-001',$now]);
            $ins($iact, [$r1,'RESOURCE','Buy two handsets that do not strip location, for the Pune engineers.','Kiran Joshi',$d(21),'OPEN','','','CAPA-2026-003',$now]);
            $ins($iact, [$r1,'POLICY','Review the impartiality declaration cycle: one lapsed without anybody noticing.','Meena Shah',$d(-2),'OPEN','','','',$now]);
            $n['mr_actions'] = 3;
        }
    }

    // ------------------------------------------------------------------
    //  Control of data and information (§7.11)
    // ------------------------------------------------------------------
    if ($has('sw_validations')) {
        $iv = "INSERT INTO sw_validations (ref,kind,component,version,purpose,what_tested,expected,observed,result,limitations,
               evidence,validated_by,validated_on,next_review,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $ins($iv, ['SV-2026-001','APP','Inspection Ops application', defined('APP_VERSION') ? APP_VERSION : '2026.07.1',
            'Recording inspections, producing reports, and the figures the branch is run on.',
            'Twenty deputations re-checked by hand end to end: credit, invoice value, turnaround and the close gates.',
            'Figures on screen match the manual calculation; every close gate refuses when its condition is unmet.',
            'Matched on all twenty. Both close gates refused as expected.','PASS','',
            'Worksheet held with the quality records.','Arjun Patel',$d(-45),$d(320),$now]);
        // A spreadsheet whose review date has passed.
        $ins($iv, ['SV-2026-002','SPREADSHEET','Deputation costing workbook','3.2',
            'Working out what a deputation costs before it is quoted.',
            'Twenty rows recalculated by hand against the workbook.',
            'Workbook total equals the manual total.','Matched on all twenty rows.','PASS','',
            'Workbook and the check sheet are with the quality records.','Nikhil Jain',$d(-400),$d(-35),$now]);
        $n['sw_validations'] = 2;
    }
    if ($has('data_check_runs') && function_exists('integrity_checks')) {
        try {
            $checks = integrity_checks();
            $failed = 0;
            foreach ($checks as $ck) if (empty($ck['ok']) && empty($ck['skipped'])) $failed++;
            $ins("INSERT INTO data_check_runs (ran_on,ran_by,total,failed,app_version,detail) VALUES (?,?,?,?,?,?)",
                [$d(-5) . 'T09:00:00+05:30','Arjun Patel',count($checks),$failed,
                 defined('APP_VERSION') ? APP_VERSION : '', json_encode($checks)]);
            $n['data_check_runs'] = 1;
        } catch (Throwable $e) { /* checks not available on this install */ }
    }
    if ($has('system_failures')) {
        $isf = "INSERT INTO system_failures (ref,occurred_at,source,fingerprint,summary,detail,data_affected,data_note,
                immediate_action,capa_ref,reported_by,status,resolved_on,resolved_by,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $ins($isf, ['SF-2026-001',$d(-33).'T14:22:00+05:30','REPORTED','demo-seed-a',
            'Report PDF would not open for one report type',
            'Opening the PDF for the coating report type returned a blank page for about two hours.',
            'NO','No inspection record was changed; only the rendering failed.',
            'Report type reverted to the previous template; PDF checked on five reports.','','Sana Kapoor',
            'CLOSED',$d(-32),'Arjun Patel',$now]);
        // Open, and nobody has said yet whether data was affected — the honest
        // state of a failure somebody has only just reported.
        $ins($isf, ['SF-2026-002',$d(-2).'T08:41:00+05:30','REPORTED','demo-seed-b',
            'Evidence upload failed twice on a large photograph',
            'Two uploads of a 14 MB photograph failed. Smaller photographs from the same handset uploaded.',
            'UNKNOWN','','Engineer asked to keep the original until this is settled.','','Vivek Menon',
            'OPEN','','',$now]);
        $n['system_failures'] = 2;
    }

    // ------------------------------------------------------------------
    //  The client portal
    // ------------------------------------------------------------------
    if ($has('client_users')) {
        // On, because a demo of a portal that is switched off shows nothing.
        // Any real install starts with it off.
        setting_set('portal_enabled', '1');
        $icu = "INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,invite_token,
                invite_expires,last_login_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?, 'demo', ?)";
        // Accepted, and can be signed in as. Password: demo12345, like the rest.
        $ins($icu, [$cid['CL-NIL'],'buyer@narmada.example','Rakesh Menon',$x['hash'],1,0,'','',$d(-1).'T09:40:00+05:30',$now]);
        // Invited and not yet accepted — the state most portals never show.
        $ins($icu, [$cid['CL-SVP'],'qa@suryavan.example','Sunita Rao','',1,1,
                    'demoinvite' . str_repeat('0', 38), $d(6) . 'T00:00:00+05:30','',$now]);
        // Withdrawn: somebody who left the client company.
        $ins($icu, [$cid['CL-GHE'],'former.buyer@girnar.example','Deepa Balan',$x['hash'],0,0,'','',$d(-60).'T11:00:00+05:30',$now]);
        $n['portal_users'] = 3;

        if ($has('portal_requests')) {
            $ipr = "INSERT INTO portal_requests (partner_id,client_user_id,subject,detail,site,wanted_on,contact_name,
                    contact_phone,status,handled_by,handled_at,reply,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $cu = (int)$val("SELECT id FROM client_users WHERE email='buyer@narmada.example'");
            $ins($ipr, [$cid['CL-NIL'],$cu,'Final inspection — next batch of 8 vessels',
                "Same specification as the last batch. Ready for final inspection from the 20th.",
                'Vapi works',$d(12),'Rakesh Menon','9825011001','NEW','','','',$now]);
            $ins($ipr, [$cid['CL-NIL'],$cu,'Witness of hydro test — vessel 13',
                'One day. Hydro test is scheduled for the morning.','Vapi works',$d(-3),'Rakesh Menon','9825011001',
                'ACCEPTED','Sana Kapoor',$d(-8).'T10:00:00+05:30','An engineer is booked for the morning of the 9th.',$now]);
            $n['portal_requests'] = 2;
        }
    }

    return $n;
}

// ============================================================================
//  What the demo actually covers — checked, not remembered
//
//  This module fell two years behind without anybody noticing: the seed filled
//  Operations and every register added afterwards loaded empty, so a demo showed
//  a third of the product and two dozen blank screens. That is the same trap as
//  every other hand-maintained list in this application — module_groups(), the
//  "new modules" guess, the smoke crawl's fixed path list. Each one was fixed by
//  deriving it from the real thing instead of trusting somebody to remember.
//
//  So: this is the list, it is checked against the database, and the result is
//  shown on the screen where the demo is loaded. Add a module tomorrow, add its
//  line here, and the screen says "no demo data" in plain sight until it has
//  some. It cannot rot quietly again.
// ============================================================================
function demo_modules_expected() {
    return [
        'Inspection calls'          => 'calls',
        'Deputations'               => 'jobs',
        'Clients & vendors'         => 'business_partners',
        'Inspection engineers'      => 'inspectors',
        'Vouchers'                  => 'vouchers',
        'Hiring / requisitions'     => 'requisitions',
        'CRM — inquiries'           => 'crm_inquiries',
        'CRM — quotations'          => 'quotations',
        'Inspection reports'        => 'report_docs',
        'Report evidence'           => 'report_files',
        'Evidence chain'            => 'evidence_chain',
        'Endorsements'              => 'endorsements',
        'Site check-ins'            => 'site_visits',
        'Equipment'                 => 'equipment',
        'Calibration certificates'  => 'equipment_calibrations',
        'Qualifications'            => 'qualifications',
        'Authorisations'            => 'authorisations',
        'Witness assessments'       => 'witness_assessments',
        'Impartiality declarations' => 'impartiality_declarations',
        'Impartiality threats'      => 'impartiality_threats',
        'Identity documents'        => 'person_documents',
        'Complaints & appeals'      => 'complaints',
        'Corrective actions'        => 'capa',
        'Internal audits'           => 'internal_audits',
        'Audit findings'            => 'audit_findings',
        'Management reviews'        => 'mgmt_reviews',
        'Review actions'            => 'mr_actions',
        'Software validations'      => 'sw_validations',
        'Integrity check runs'      => 'data_check_runs',
        'System failures'           => 'system_failures',
        'Client portal users'       => 'client_users',
        'Client portal requests'    => 'portal_requests',
    ];
}

// [label => ['rows' => n, 'state' => 'ok'|'empty'|'missing'], …]
function demo_coverage() {
    $out = [];
    foreach (demo_modules_expected() as $label => $table) {
        try {
            $n = (int)db()->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            $out[$label] = ['rows' => $n, 'table' => $table, 'state' => $n > 0 ? 'ok' : 'empty'];
        } catch (Throwable $e) {
            $out[$label] = ['rows' => 0, 'table' => $table, 'state' => 'missing'];
        }
    }
    return $out;
}
function demo_coverage_gaps() {
    return array_keys(array_filter(demo_coverage(), fn($c) => $c['state'] !== 'ok'));
}
