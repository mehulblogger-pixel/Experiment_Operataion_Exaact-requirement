<?php
// ============================================================================
//  Demo seed — Project costing sheets.
//
//  Three realistic sheets so the module is populated on day one and the numbers
//  can be checked against a hand calculation:
//    · Mundra Solar — 8 roles, man-month basis, loadings on the sell rate
//      (reproduces the client's own Excel to the rupee). APPROVED.
//    · Hazira shutdown — daily deputation, per man-day. SUBMITTED.
//    · Pressure-vessel campaign — short fixed / lump sum. DRAFT.
//  All rows carry created_by='demo' so the teardown removes exactly these.
// ============================================================================

function demo_seed_costing($pdo, $x, $has, $ins) {
    if (!function_exists('pc_migrate')) return [];
    pc_migrate();
    $n = ['project_costings' => 0, 'project_costing_lines' => 0];

    // Idempotent: skip if the demo sheets are already present.
    try { if ((int)ops_val("SELECT COUNT(*) FROM project_costings WHERE created_by='demo'") > 0) return $n; }
    catch (Throwable $e) { return $n; }

    $now = date('c');
    $office = (int)(ops_val("SELECT id FROM offices ORDER BY id LIMIT 1") ?: 0) ?: null;
    $clientOf = function ($needle) {
        try { return (int)ops_val("SELECT id FROM business_partners WHERE COALESCE(display_name,legal_name) LIKE ? ORDER BY id LIMIT 1", ['%' . $needle . '%']) ?: null; }
        catch (Throwable $e) { return null; }
    };

    // Insert a header, then its role lines. $lines: [group, role, headsMap, boq, rate_manual, target_margin, oneoff].
    $mk = function ($hdr, $lines) use ($pdo, $now, &$n) {
        $pdo->prepare("INSERT INTO project_costings
            (code,title,client_id,office_id,site,basis,load_on,overhead_pct,insurance_pct,baddebt_pct,contingency_pct,negotiation_pct,
             status,approval_ref,approved_by,approved_at,inclusions,exclusions,notes,created_by,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?, 'demo',?,?)")
            ->execute([$hdr['code'], $hdr['title'], $hdr['client_id'], $hdr['office_id'], $hdr['site'], $hdr['basis'], $hdr['load_on'],
                $hdr['overhead_pct'], $hdr['insurance_pct'], $hdr['baddebt_pct'], $hdr['contingency_pct'], $hdr['negotiation_pct'],
                $hdr['status'], $hdr['approval_ref'] ?? '', $hdr['approved_by'] ?? '', $hdr['approved_at'] ?? '',
                $hdr['inclusions'] ?? '', $hdr['exclusions'] ?? '', $hdr['notes'] ?? '', $now, $now]);
        $cid = (int)$pdo->lastInsertId(); $n['project_costings']++;
        $seq = 0;
        foreach ($lines as $ln) {
            $seq++;
            $pdo->prepare("INSERT INTO project_costing_lines
                (costing_id,seq,group_label,role_label,heads,boq_qty,target_margin,rate_manual,oneoff,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$cid, $seq, $ln[0], $ln[1], json_encode($ln[2], JSON_UNESCAPED_UNICODE),
                    $ln[3], $ln[5] ?? 0, $ln[4] ?? 0, $ln[6] ?? 0, $now]);
            $n['project_costing_lines']++;
        }
        if (function_exists('pc_restamp')) pc_restamp($cid);
        return $cid;
    };

    // ---- 1) Mundra Solar — 8 roles, man-month, loadings on the sell rate ----
    // Cost heads per person / month, taken from the client's sheet. rate_manual is
    // the agreed man-month rate; negotiation 5% is added on top; BOQ 24 man-months.
    $H = fn($p, $t, $a, $ppe, $cap, $food) => ['PERSONNEL'=>$p,'TRANSPORT'=>$t,'ACCOMMODATION'=>$a,'PPE'=>$ppe,'CAPEX'=>$cap,'FOOD'=>$food];
    $mundra = [
        ['Module', 'Manager',          $H(120000,3250,8000,166.67,2166.67,7500), 24, 210000],
        ['Module', 'Shift Engineer',   $H(60000, 3250,8000,166.67,1000,   7500), 24, 120000],
        ['Module', 'Shift Associate',  $H(40000, 3250,8000,166.67,0,       7500), 24, 87000],
        ['Module', 'Service Engineer', $H(45000, 3250,0,   167,   2167,   7500), 24, 87000],
        ['Cell',   'Manager',          $H(150000,3250,8000,166.67,2166.67,7500), 24, 250000],
        ['Cell',   'Shift Engineer',   $H(70000, 3250,8000,166.67,1000,   7500), 24, 135000],
        ['Cell',   'Shift Associate',  $H(45000, 3250,8000,166.67,0,       7500), 24, 98000],
        ['Cell',   'Data Entry Operator', $H(30000,3250,0,167,   0,       7500), 24, 61000],
    ];
    $mk([
        'code'=>'PC-DEMO-01', 'title'=>'Mundra Solar — module & cell line manpower',
        'client_id'=>$clientOf('Solar') ?: $clientOf('Mundra'), 'office_id'=>$office, 'site'=>'Mundra, Gujarat',
        'basis'=>'LONG', 'load_on'=>'RATE',
        'overhead_pct'=>8, 'insurance_pct'=>0.5, 'baddebt_pct'=>0, 'contingency_pct'=>2, 'negotiation_pct'=>5,
        'status'=>'APPROVED', 'approval_ref'=>'PC-APP-2026-004', 'approved_by'=>'Rajesh Menon (ED)', 'approved_at'=>$now,
        'inclusions'=>"Deputed manpower on our roll; 1×14-seater bus + 3 cars for the team; shared accommodation; PPE incl. fall-arrestor kit; laptop (shared between shift engineers); food allowance @₹250/day.",
        'exclusions'=>"Not included: daily consumables; spares & services; calibration of instruments; procurement of testers/consumables; general housekeeping & clean-room wearables — these are to be provided/borne by the client.",
        'notes'=>'Loadings recovered on the sell rate (as per client sheet). 24 man-months per role as per BOQ.',
    ], $mundra);

    // ---- 2) Hazira shutdown — daily deputation, per man-day ----
    $D = fn($p, $t, $food, $ppe) => ['PERSONNEL'=>$p,'TRANSPORT'=>$t,'FOOD'=>$food,'PPE'=>$ppe];
    $hazira = [
        ['QA/QC', 'Senior QA/QC Inspector', $D(4200,600,300,120), 90, 6500, 0, 3000],
        ['QA/QC', 'NDT Level II (UT/RT)',   $D(3800,600,300,150), 90, 5800, 0, 3000],
        ['HSE',   'Safety Steward',         $D(2600,600,300,120), 60, 3900, 0, 2000],
    ];
    $mk([
        'code'=>'PC-DEMO-02', 'title'=>'Hazira refinery — shutdown QA/QC & HSE (daily)',
        'client_id'=>$clientOf('Hazira') ?: $clientOf('Reliance') ?: $clientOf('refin'), 'office_id'=>$office, 'site'=>'Hazira, Surat',
        'basis'=>'DAILY', 'load_on'=>'COST',
        'overhead_pct'=>8, 'insurance_pct'=>0.5, 'baddebt_pct'=>0, 'contingency_pct'=>0.5, 'negotiation_pct'=>5,
        'status'=>'SUBMITTED',
        'inclusions'=>'Per man-day deputation for the shutdown window; travel & local conveyance; food; PPE.',
        'exclusions'=>'Not included: NDT consumables (film, chemicals, couplant); calibration; accommodation (client guest-house provided).',
        'notes'=>'Man-day rates; one-time mobilisation (medical + gate pass) per person as a one-off.',
    ], $hazira);

    // ---- 3) Pressure-vessel campaign — short fixed / lump sum ----
    $mk([
        'code'=>'PC-DEMO-03', 'title'=>'Pressure-vessel stage inspection — lump-sum campaign',
        'client_id'=>$clientOf('Engineering') ?: $clientOf('Fabric'), 'office_id'=>$office, 'site'=>'Client works, Vadodara',
        'basis'=>'FIXED', 'load_on'=>'COST',
        'overhead_pct'=>8, 'insurance_pct'=>0.5, 'baddebt_pct'=>0, 'contingency_pct'=>1, 'negotiation_pct'=>0,
        'status'=>'DRAFT',
        'inclusions'=>'Whole-scope stage inspection of 4 vessels incl. final documentation.',
        'exclusions'=>'Third-party lab testing; destructive testing; client-side scaffolding.',
        'notes'=>'Fixed lump sum for the defined scope; margin set via target margin.',
    ], [
        ['', 'Lead Inspection Engineer (whole scope)', ['PERSONNEL'=>180000,'TRANSPORT'=>12000,'FOOD'=>9000,'MISC'=>15000], 1, 0, 30, 20000],
    ]);

    return $n;
}
