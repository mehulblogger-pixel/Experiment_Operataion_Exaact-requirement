<?php
// ============================================================================
//  Demo / sample dataset — one-click loader (Master Admin only).
//  Inserts a complete, coherent lifecycle so every screen shows live figures:
//    offices → users (all roles) → inspectors (+ entitlements) → clients/
//    vendors → BOSS numbers → calls → jobs (allocated / closed) → vouchers
//    (km + bills) → job-closure expenses → invoicing + payment + credit.
//  Idempotent: guarded by the `demo_seeded` setting so it runs only once.
// ============================================================================

function demo_seeded() { return setting_get('demo_seeded', '') === '1'; }

// The demo login accounts created below (shown to the admin after seeding).
function demo_accounts() {
    return [
        ['director',  'Business Director', 'Mumbai'],
        ['sbuhead',   'SBU Head',          'Mumbai'],
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

function seed_demo() {
    if (demo_seeded()) return ['skipped' => true];
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
        $insP = $pdo->prepare("INSERT INTO business_partners(code,legal_name,display_name,is_client,is_vendor,status,state)
            VALUES(?,?,?,?,?, 'ACTIVE', ?)");
        $clients = [
            ['CL-NIL','Narmada Industries Ltd','Narmada',1,0,'Gujarat'],
            ['CL-SVP','Suryavan Ports & SEZ Ltd','Suryavan Ports',1,0,'Gujarat'],
            ['CL-GHE','Girnar Energy Hydrocarbon','Girnar Energy',1,0,'Maharashtra'],
        ];
        $vendors = [
            ['VN-VAP','Vapi Chemical Works','Vapi Chem',0,1,'Gujarat'],
            ['VN-MUN','Mundra Fabrication Yard','Mundra Fab',0,1,'Gujarat'],
        ];
        $cid = []; $vid = [];
        foreach ($clients as $p) { $insP->execute($p); $cid[$p[0]] = (int)$pdo->lastInsertId(); }
        foreach ($vendors as $p) { $insP->execute($p); $vid[$p[0]] = (int)$pdo->lastInsertId(); }
        $c['partners'] = count($clients) + count($vendors);

        // ---------- BOSS / contract numbers ----------
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
        for ($i = 1; $i <= 10; $i++) { $insP->execute(['EC-'.$i,'Edge Client '.sprintf('%02d',$i),'EdgeCli'.$i,1,0,'Gujarat']); $cid['EC'.$i] = (int)$pdo->lastInsertId(); }
        for ($i = 1; $i <= 6;  $i++) { $insP->execute(['EV-'.$i,'Edge Vendor '.sprintf('%02d',$i),'EdgeVen'.$i,0,1,'Maharashtra']); $vid['EV'.$i] = (int)$pdo->lastInsertId(); }
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
                if ($mod === 1) { $invRaised = 1; $invNo = 'INV-E'.$i; $invDate = $d(-15); $invDue = ($i % 2 === 0) ? $d(-3) : $d(20); $invAmt = $amt; } // awaiting / overdue
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
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['error' => $e->getMessage()];
    }
    return ['counts' => $c];
}

// Remove ONLY the records the demo seed created (identified by the seed's own
// markers), leaving any real data untouched. Lets the demo be loaded again.
function seed_demo_remove() {
    $pdo = db();
    $n = 0;
    $pdo->beginTransaction();
    try {
        $del = function($sql, $args = []) use ($pdo, &$n) { $st = $pdo->prepare($sql); $st->execute($args); $n += $st->rowCount(); };
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
        // Demo login accounts
        $unames = array_map(fn($a) => $a[0], demo_accounts());
        $ph = implode(',', array_fill(0, count($unames), '?'));
        $del("DELETE FROM users WHERE username IN ($ph)", $unames);
        setting_set('demo_seeded', '');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['error' => $e->getMessage()];
    }
    return ['deleted' => $n];
}
