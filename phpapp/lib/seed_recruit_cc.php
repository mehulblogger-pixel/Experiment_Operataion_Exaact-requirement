<?php
// ============================================================================
//  DEMO SEED — Recruitment Command Centre  (Phase 7)
//
//  Fills the command centre with a realistic hiring pipeline so every section —
//  demand, conversion, funnel, status donut, monthly trend, department load,
//  drop reasons, ageing, needs-attention, recruiter performance and the money
//  P&L — shows real shapes out of the box. All rows carry created_by='demo' and
//  are removed by the existing demo cleanup. Expected commercials are stored on
//  every requirement, and approved placement commercials on the hires, so the
//  earned-vs-lost figures are true, not placeholders.
// ============================================================================

function demo_seed_recruit_cc($pdo, $x, $has, $ins) {
    if (!$has('requisitions') || !$has('candidates')) return 0;
    if (function_exists('req_migrate')) req_migrate();
    if (function_exists('rcc_migrate')) rcc_migrate();
    if (function_exists('asg_migrate')) asg_migrate();

    $oid = $x['oid']; $cid = $x['cid']; $iid = $x['iid'];
    $now = $x['now']; $d = $x['d'];

    // Don't double-seed.
    try { if ((int)ops_val("SELECT COUNT(*) FROM candidates WHERE cand_code LIKE 'CV-RC-%'") > 0) return 0; } catch (Throwable $e) {}

    // Demo users → recruiters (Responsible 1) and managers (Responsible 2).
    $uid = function ($username) use ($pdo) { try { return (int)ops_val("SELECT id FROM users WHERE username=?", [$username]); } catch (Throwable $e) { return 0; } };
    $recruiters = array_values(array_filter([$uid('coord.amd'), $uid('coord.pun'), $uid('asstmgr')])) ?: [0];
    $managers   = array_values(array_filter([$uid('bmanager'), $uid('opmanager')])) ?: [0];
    $rc = fn($i) => $recruiters[$i % count($recruiters)];
    $mg = fn($i) => $managers[$i % count($managers)];

    $n = 0;
    $DEPTS  = ['INSPECTION','ENGINEERING','QAQC','NDT','HSE','COMMERCIAL'];
    $SRC    = ['FREELANCER','HR_AGENCY','SUBCON','ASSET'];
    $DROPS  = ['HIGH_SALARY','NOT_RESPONDING','REQ_FULFILLED','NOT_INTERESTED','BETTER_OFFER','LOCATION','COUNTER_OFFER','NOTICE_PERIOD','PROFILE_MISMATCH','PERSONAL'];

    // ---- Requirements (with expected commercials) --------------------------
    // Enrich the two shipped demo requirements with ownership + commercials.
    $enrich = function ($code, $rcId, $mgId, $dept, $qty, $bill, $cost, $months, $start, $end) use ($pdo) {
        try {
            $com = function_exists('req_commercials') ? req_commercials(['quantity'=>$qty,'billing_rate'=>$bill,'budgeted_cost'=>$cost,'rate_basis'=>'MONTHLY','duration_months'=>$months]) : ['revenue'=>0,'profit'=>0];
            $pdo->prepare("UPDATE requisitions SET recruiter_id=?, manager_id=?, department=?, quantity=?, billing_rate=?, budgeted_cost=?, rate_basis='MONTHLY', duration_months=?, start_date=?, end_date=?, expected_revenue=?, expected_profit=? WHERE req_code=?")
                ->execute([$rcId, $mgId, $dept, $qty, $bill, $cost, $months, $start, $end, $com['revenue'], $com['profit'], $code]);
        } catch (Throwable $e) {}
    };
    $enrich('REQ-2607-01', $rc(0), $mg(0), 'INSPECTION', 3, 88000, 60000, 12, $d(-20), $d(345));
    $enrich('REQ-2607-02', $rc(1), $mg(0), 'INSPECTION', 3, 95000, 62000, 12, $d(-10), $d(355));

    // A spread of fresh requirements across departments/offices.
    $insReq = $pdo->prepare("INSERT INTO requisitions(req_code,office_id,sbu,designation,project_site,req_type,department,recruiter_id,manager_id,quantity,billing_rate,budgeted_cost,rate_basis,duration_months,start_date,end_date,expected_revenue,expected_profit,approved_by,approval_ref,approval_date,status,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?, 'MONTHLY', ?,?,?,?,?,?,?,?, 'OPEN', 'demo', ?)");
    $reqPlan = [
        // code, office, sbu, designation, site, dept, qty, bill, cost, months, startOffset, i
        ['REQ-2607-05',$oid['AMD'],'OGC','WELDING_INSPECTOR','Suryavan Ports — jetty fabrication','QAQC',6,72000,48000,10,-24,2],
        ['REQ-2607-06',$oid['MUM'],'IND','QAQC_ENGINEER','GHE Refinery — turnaround','ENGINEERING',4,110000,70000,8,-9,0],
        ['REQ-2607-07',$oid['PUN'],'IND','NDT_TECHNICIAN','Dahej Petrochem — new unit','NDT',4,64000,42000,9,-5,1],
        ['REQ-2607-08',$oid['AMD'],'OGC','HSE_OFFICER','Narmada Jamnagar — expansion','HSE',3,58000,40000,12,-14,2],
        ['REQ-2607-09',$oid['MUM'],'IND','INSPECTOR','Suryavan Mundra — commissioning','COMMERCIAL',5,52000,36000,6,-30,0],
    ];
    foreach ($reqPlan as $r) {
        $com = req_commercials(['quantity'=>$r[6],'billing_rate'=>$r[7],'budgeted_cost'=>$r[8],'rate_basis'=>'MONTHLY','duration_months'=>$r[9]]);
        try {
            $insReq->execute([$r[0],$r[1],$r[2],$r[3],$r[4],'NEW',$r[5],$rc($r[11]),$mg($r[11]),$r[6],$r[7],$r[8],$r[9],$d($r[10]),$d($r[10]+$r[9]*30),$com['revenue'],$com['profit'],'Neha Iyer','HR-APP-2026-'.(30+$n),$d($r[10]),$now]);
            $n++;
        } catch (Throwable $e) {}
    }
    // Map req_code → id for linking hires.
    $reqId = [];
    try { foreach (ops_all("SELECT id, req_code FROM requisitions WHERE created_by='demo' OR req_code LIKE 'REQ-2607-%'") as $rr) $reqId[$rr['req_code']] = (int)$rr['id']; } catch (Throwable $e) {}

    // ---- Candidates: a full pipeline across stages, months, depts, owners ----
    $firsts = ['Rakesh','Priyanka','Imran','Neha','Arjun','Sneha','Vikram','Fatima','Anil','Deepak','Kavya','Rohit','Sanjay','Manish','Pooja','Karan','Divya','Suresh','Meera','Nikhil','Ritu','Ajay','Farida','Gopal','Harish','Isha','Jatin','Komal','Lokesh','Mitesh','Nisha','Omkar','Payal','Qadir','Rekha','Sagar','Tarun','Usha','Varun','Yamini','Zoya','Bhavna','Chetan','Dhruv','Esha','Girish','Hemant','Ira','Jayant','Kiran'];
    $lasts  = ['Sharma','Rao','Qureshi','Kulkarni','Menon','Patil','Singh','Sheikh','Kumar','Joshi','Nair','Desai','Iyer','Reddy','Ghosh','Bose','Shetty','Pillai','Chauhan','Bhat'];
    $DESIG  = ['INSPECTOR','SR_INSPECTOR','WELDING_INSPECTOR','QAQC_ENGINEER','NDT_TECHNICIAN','HSE_OFFICER'];
    // [stage, count]
    $plan = [['RECEIVED',12],['SUBMITTED',8],['SHORTLISTED',7],['INTERVIEW',6],['OFFERED',4],['HOLD',2],['REJECTED',7],['WITHDRAWN',3],['OFFER_DECLINED',1],['ACCEPTED',5]];

    $ic = $pdo->prepare("INSERT INTO candidates
        (cand_code,first_name,middle_name,last_name,client_id,designation,source,source_type,department,recruiter_id,proposed_site,sbu,
         experience_years,email,mobile,cv_link,expected_rate,rate_type,cv_received_date,submitted_client_date,interview_date,
         stage,drop_reason,requisition_id,inspector_id,decided_at,remarks,created_by,created_at,
         asg_status,asg_bill_rate,asg_cost_rate,asg_months,asg_approved_by,asg_approved_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'MANDAY', ?,?,?, ?,?,?,?,?,?, 'demo', ?, ?,?,?,?,?,?)");

    $clients = array_values(array_filter([$cid['CL-SVP'] ?? null, $cid['CL-GHE'] ?? null, $cid['CL-NIL'] ?? null])) ?: [null];
    $inspPool = array_values(array_filter([$iid['EMP01'] ?? null, $iid['EMP02'] ?? null, $iid['EMP03'] ?? null, $iid['EMP04'] ?? null]));
    $reqCodes = ['REQ-2607-05','REQ-2607-06','REQ-2607-01','REQ-2607-02','REQ-2607-07'];
    $k = 0; $hireIdx = 0;
    foreach ($plan as $pl) {
        [$stage, $cnt] = $pl;
        for ($j = 0; $j < $cnt; $j++, $k++) {
            $fn = $firsts[$k % count($firsts)]; $ln = $lasts[$k % count($lasts)];
            $dept = $DEPTS[$k % count($DEPTS)]; $src = $SRC[$k % count($SRC)];
            $desig = $DESIG[$k % count($DESIG)];
            $ageDays = 8 + (($k * 7) % 150);            // spread 8..158 days old → months + ageing buckets
            $cvDate = $d(-$ageDays);
            $subDate = in_array($stage, ['RECEIVED'], true) ? '' : $d(-max(2, $ageDays - 5));
            $intDate = in_array($stage, ['INTERVIEW','OFFERED','ACCEPTED','OFFER_DECLINED'], true) ? $d(-max(1, $ageDays - 12)) : '';
            $rate = 3800 + (($k * 137) % 3200);         // 3800..7000
            $exp  = 4 + (($k * 3) % 16);                // 4..19 yrs
            $mobile = '98' . str_pad((string)(1000000 + $k * 7919 % 8999999), 8, '0', STR_PAD_LEFT);
            $email = strtolower($fn . '.' . $ln . $k . '@example.com');
            $client = $clients[$k % count($clients)];
            $terminal = in_array($stage, ['REJECTED','WITHDRAWN','OFFER_DECLINED','HOLD'], true);
            $drop = $terminal ? $DROPS[$k % count($DROPS)] : null;
            $decided = in_array($stage, ['ACCEPTED','REJECTED','WITHDRAWN','OFFER_DECLINED'], true) ? $d(-max(1, $ageDays - 20)) : '';

            // Hires: link to a requirement + an inspector, with approved commercials.
            $reqIdV = null; $inspV = null; $asgStatus = null; $asgBill = null; $asgCost = null; $asgMonths = null; $asgBy = null; $asgAt = null;
            if ($stage === 'ACCEPTED') {
                $code = $reqCodes[$hireIdx % count($reqCodes)];
                $reqIdV = $reqId[$code] ?? null;
                $inspV = $inspPool ? $inspPool[$hireIdx % count($inspPool)] : null;
                $asgStatus = 'APPROVED'; $asgBill = 90000 - ($hireIdx * 2500); $asgCost = 60000 - ($hireIdx * 1500);
                $asgMonths = 12; $asgBy = 'Meena Shah'; $asgAt = $d(-max(1, $ageDays - 22));
                $hireIdx++;
            }
            try {
                $ins2 = $ic->execute(['CV-RC-'.str_pad((string)($k+1),3,'0',STR_PAD_LEFT), $fn, '', $ln, $client, $desig, $src, $src, $dept, $rc($k),
                    'Client site — '.$dept, ['OGC','IND'][$k%2], $exp, $email, $mobile, 'https://drive.example/cv/'.strtolower($fn.$ln).'.pdf', $rate,
                    $cvDate, $subDate, $intDate, $stage, $drop, $reqIdV, $inspV, $decided,
                    ucfirst(strtolower($dept)).' profile · sourced via '.$src, $now,
                    $asgStatus, $asgBill, $asgCost, $asgMonths, $asgBy, $asgAt]);
                if ($ins2) $n++;
            } catch (Throwable $e) {}
        }
    }
    return ['recruitment_cc_candidates' => $n];
}
