<?php
// Phase 1 of the reconciliation probe: a small KNOWN dataset (run after seed.php).
require __DIR__ . '/lib_boot.php';
$ids = json_decode(file_get_contents(__DIR__ . '/ids.json'), true);
as_user($ids['U']['master']);
$A = $ids['A']; $B = $ids['B']; $C = $ids['C'];
$pdo = db();
$ins = function ($no, $created, $sub, $status, $extra = []) use ($pdo, $A, $C) {
    $gst = $extra['gst_pct'] ?? 18; $tot = round($sub * (1 + $gst / 100), 2);
    $row = array_merge(['quote_no' => $no, 'rev' => 0, 'is_current' => 1, 'client_id' => $C, 'client_name' => 'Probe Client', 'sbu' => 'IND',
        'office_id' => $A, 'subject' => "Recon $no", 'validity_days' => 30, 'subtotal' => $sub, 'gst_pct' => $gst, 'gst_amount' => $tot - $sub,
        'total_amount' => $tot, 'status' => $status, 'created_by' => 'Pmaster Probe', 'created_at' => $created . 'T10:00:00+05:30', 'owner_id' => 2], $extra);
    $pdo->prepare("INSERT INTO quotations (" . implode(',', array_keys($row)) . ") VALUES (" . implode(',', array_fill(0, count($row), '?')) . ")")->execute(array_values($row));
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quote_lines (quote_id,line_no,description,service_type,qty,unit,rate,amount) VALUES (?,0,'Man-days','INSPECTION',1,'MANDAY',?,?)")->execute([$id, $sub, $sub]);
    return $id;
};
$q = [];
$q['Q1'] = $ins('RQ-1', '2026-05-10', 100000, 'ACCEPTED', ['accepted_date' => '2026-05-20', 'sent_at' => '2026-05-11']);
$q['Q2'] = $ins('RQ-2', '2026-06-05', 50000, 'ACCEPTED', ['accepted_date' => '2026-06-20', 'sent_at' => '2026-06-06']);   // revised below
$q['Q3'] = $ins('RQ-3', '2026-08-01', 20000, 'LOST', ['lost_reason' => 'PRICE_HIGH', 'sent_at' => '2026-08-02']);
$q['Q4'] = $ins('RQ-4', '2026-09-01', 10000, 'SENT', ['sent_at' => '2026-09-01T10:00:00']);                               // expires via the real sweep
$q['Q5'] = $ins('RQ-5', '2026-10-01', 30000, 'DRAFT');
$q['Q6'] = $ins('RQ-6', '2025-12-01', 200000, 'ACCEPTED', ['accepted_date' => '2025-12-15', 'sent_at' => '2025-12-02']);   // previous FY
$q['Q7'] = $ins('RQ-7', '2026-10-05', 50000, 'SENT', ['gst_pct' => 0, 'origin' => 'CLIENT_PORTAL', 'sent_at' => '2026-10-05']); // external: typed total, no GST
$q['Q8'] = $ins('RQ-8', '2026-10-10', 150000, 'PENDING_APPROVAL');
// approval rules + Q8 chain (L1 BM / IND, L2 MM >= 1L) built by the real builder
$pdo->exec("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,active,created_at) VALUES ('L1','Business Unit','IND',0,0,1,'BRANCH_MANAGER',1,'x')");
$pdo->exec("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,active,created_at) VALUES ('L2','ANY','',100000,0,2,'MARKETING_MANAGER',1,'x')");
crm_build_approvals(crm_quote_get($q['Q8']));
echo "expired by sweep: ", crm_expire_quotes('2026-10-19'), "\n";

// Leads and opportunities, through the real functions.
$l1 = lead_create(['company_name' => 'Lead Co A', 'office_id' => $A, 'value' => 500000]);
$l2 = lead_create(['company_name' => 'Lead Co B', 'office_id' => $B, 'value' => 1000000]);
$o1 = opp_from_lead($l1['id'], ['value' => 500000, 'expected_close' => date('Y-m-d', strtotime('+10 days'))]);
$o5 = opp_from_lead($l2['id'], ['value' => 1000000, 'expected_close' => date('Y-m-d', strtotime('+60 days'))]);
$o2 = opp_create(['name' => 'Deal 2', 'partner_id' => $C, 'value' => 200000, 'office_id' => $A, 'expected_close' => '2026-09-30']);
$o3 = opp_create(['name' => 'Deal 3 won', 'partner_id' => $C, 'value' => 300000, 'office_id' => $A]);
$o4 = opp_create(['name' => 'Deal 4 lost', 'partner_id' => $C, 'value' => 100000, 'office_id' => $A]);
$pipe = (int)pipeline_default('OPPORTUNITY')['id'];
$st = []; foreach (pipeline_stages($pipe) as $s) $st[$s['name']] = (int)$s['id'];
opp_move($o1['id'], $st['Quotation sent']);
opp_move($o5['id'], $st['Negotiation']);
opp_move($o3['id'], $st['Won']);
opp_move($o4['id'], $st['Lost'], ['lost_reason' => 'PRICE_HIGH']);
$pdo->prepare("UPDATE opportunities SET created_at='2026-06-01T10:00:00+05:30' WHERE id=?")->execute([$o3['id']]);
// inquiries: one 10 days old, one received today
$pdo->prepare("INSERT INTO crm_inquiries (inquiry_no,client_id,client_name,contact_name,sbu,subject,received_date,status,created_at) VALUES ('RI-1',?,'Probe Client','Ravi','IND','old one',?, 'OPEN', ?)")->execute([$C, date('Y-m-d', strtotime('-10 days')), date('c')]);
$pdo->prepare("INSERT INTO crm_inquiries (inquiry_no,client_id,client_name,contact_name,sbu,subject,received_date,status,created_at) VALUES ('RI-2',?,'Probe Client','Ravi','IND','today',?, 'OPEN', ?)")->execute([$C, date('Y-m-d'), date('c')]);
file_put_contents(__DIR__ . '/recon_ids.json', json_encode(['q' => $q, 'o' => [$o1['id'], $o2['id'], $o3['id'], $o4['id'], $o5['id']], 'stages' => $st]));
echo json_encode(['q' => $q, 'o1' => $o1, 'o5' => $o5]), "\n";
