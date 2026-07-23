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
            ['Mohan Rao','Mohan','Rao','EMP04','MIN','MIN','Lifting, Cranes','mohan@example.com','9800000004',0,'Inspector','SUBCON','TechInspect Services',480000],
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
        $insP = $pdo->prepare("INSERT INTO business_partners(code,legal_name,display_name,is_client,is_vendor,status,state,created_at)
            VALUES(?,?,?,?,?, 'ACTIVE', ?, ?)");
        $clients = [
            ['CL-RIL','Reliance Industries Ltd','Reliance',1,0,'Gujarat'],
            ['CL-ADN','Adani Ports & SEZ Ltd','Adani Ports',1,0,'Gujarat'],
            ['CL-LNT','L&T Energy Hydrocarbon','L&T Energy',1,0,'Maharashtra'],
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
            [$cid['CL-RIL'],'40231',$d(-120),$d(240)],
            [$cid['CL-ADN'],'40198',$d(-90),$d(270)],
            [$cid['CL-LNT'],'40155',$d(-60),$d(300)],
        ];
        $bid = [];
        foreach ($boss as $b) { $insB->execute($b); $bid[$b[1]] = (int)$pdo->lastInsertId(); }
        $c['boss'] = count($boss);

        // ---------- Calls ----------
        $insC = $pdo->prepare("INSERT INTO calls(call_code,client_id,vendor_id,ibo_office_id,region,sbu,product_category,call_received_date,inspection_required_date,notes,status,executing_office_id,expected_credit,credit_type,inspection_type,created_by,created_at)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'demo', ?)");
        // [code, client, vendor, iboOffice(null=same), region, sbu, prodcat, recd, reqBy, status, execOffice, credit, credit_type, itype]
        $calls = [
            ['C-2607-001',$cid['CL-RIL'],$vid['VN-VAP'],null,'WEST','IND','Pressure vessel',$d(-20),$d(-10),'Same office (Ahmedabad) — no inter-office credit','CLOSED',$oid['AMD'],0,'MANDAY','INSPECTION'],
            ['C-2607-002',$cid['CL-ADN'],$vid['VN-MUN'],$oid['MUM'],'WEST','OGC','Structural',$d(-18),$d(-8),'Contracted by Mumbai, executed by Ahmedabad — credit to AMD','CLOSED',$oid['AMD'],184000,'MANDAY','INSPECTION'],
            ['C-2607-003',$cid['CL-LNT'],null,$oid['MUM'],'WEST','IND','Piping',$d(-16),$d(-6),'Contracted by Mumbai, executed by Pune','CLOSED',$oid['PUN'],96000,'MANDAY','INSPECTION'],
            ['C-2607-004',$cid['CL-RIL'],$vid['VN-VAP'],null,'WEST','IND','Welding audit',$d(-4),$d(3),'Ahmedabad own job — in progress','OPEN',$oid['AMD'],0,'MANDAY','INSPECTION'],
            ['C-2607-005',$cid['CL-ADN'],$vid['VN-MUN'],null,'WEST','MIN','Crane / lifting',$d(-3),$d(4),'Pune — sub-con deployed','OPEN',$oid['PUN'],0,'MANDAY','INSPECTION'],
            ['C-2607-006',$cid['CL-LNT'],null,$oid['MUM'],'WEST','OGC','Coating',$d(-12),$d(-2),'Cross-office, overdue closure','OPEN',$oid['AMD'],110000,'MANDAY','INSPECTION'],
        ];
        $callid = [];
        foreach ($calls as $r) {
            $insC->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],$r[8],$r[9],$r[10],$r[11],$r[12],$r[13],$now]);
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

        setting_set('demo_seeded', '1');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['error' => $e->getMessage()];
    }
    return ['counts' => $c];
}
