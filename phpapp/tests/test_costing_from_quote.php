<?php
// Owner, 31 Oct 2026 (test book MOD-SAL): "I have provided 1,20,000 per man-month
// in the quotation … I got rate as 1,23,418 which is not as per the quotation.
// Also this costing shows as linked to this quote but when I submit for approval
// it shows an error that project costing is mandatory to attach."
// (1) A costing started from a quotation now begins with its lines at the quoted
// rate and quantity. (2) The submit message says the costing IS linked and what
// to do next.
t_section('Project costing made from a quotation starts at the quoted price');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    pc_migrate();
    $pdo->prepare("INSERT INTO quotations (quote_no,rev,is_current,client_name,subject,currency,validity_days,subtotal,gst_pct,gst_amount,total_amount,status,created_at)
                   VALUES ('Q-T1',0,1,'TB Client','Electrical Engineer','INR',30,8640000,18,1555200,10195200,'DRAFT',?)")->execute([date('c')]);
    $qid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quote_lines (quote_id,line_no,description,order_type,qty,unit,rate,amount) VALUES (?,1,'Man Month Deputation','LINE',72,'MANMONTH',120000,8640000)")->execute([$qid]);
    $pdo->prepare("INSERT INTO project_costings (code,title,basis,overhead_pct,insurance_pct,baddebt_pct,contingency_pct,negotiation_pct,status,quote_id,created_at)
                   VALUES ('PC-T1','From Q-T1','LONG',8,0.5,0,0.5,5,'DRAFT',?,?)")->execute([$qid, date('c')]);
    $cid = (int)$pdo->lastInsertId();

    t_eq(pc_seed_from_quote($cid, $qid), 1, "the quotation's man-month line is copied into the costing");
    $roll = pc_rollup($cid); $c = pc_line_calc($roll['lines'][0], $roll['header']);
    t_eq([(float)$roll['lines'][0]['boq_qty'], (float)$c['proposed']], [72.0, 120000.0], 'at the quoted quantity (72) and rate (₹1,20,000) — no negotiation buffer added');
    t_eq((float)$roll['totals']['revenue'], 8640000.0, 'so the costing\'s revenue is the quotation\'s ₹86,40,000');
    t_ok(crm_saved_price_rows($qid) === crm_costing_price_rows($roll), 'once approved, the quotation counts as priced from it (no second approval)');
    t_eq(pc_seed_from_quote($cid, $qid), 0, 'a costing that already has lines is never overwritten');

    // The submit message: linked, and what to do next.
    $q = ops_one("SELECT * FROM quotations WHERE id=?", [$qid]);
    $prev = (string) setting_get('quote_manmonth_costing', '');
    setting_set('quote_manmonth_costing', 'yes');
    $m = crm_quote_costing_problem($q);
    t_ok(strpos($m, 'Costing PC-T1 is linked') !== false && strpos($m, 'press "Submit for approval"') !== false, 'a draft costing: "' . $m . '"');
    $pdo->prepare("UPDATE project_costings SET status='SUBMITTED' WHERE id=?")->execute([$cid]);
    t_ok(strpos(crm_quote_costing_problem($q), 'waiting for its approval levels') !== false, 'a submitted costing: waiting for its approval levels');
    $pdo->prepare("UPDATE project_costings SET status='APPROVED' WHERE id=?")->execute([$cid]);
    t_eq(crm_quote_costing_problem($q), '', 'an approved costing: the quotation may go forward');
    setting_set('quote_manmonth_costing', $prev);
} finally { if ($own) $pdo->rollBack(); }
