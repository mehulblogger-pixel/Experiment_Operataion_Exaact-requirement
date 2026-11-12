<?php
// Owner, 12 Nov 2026 — "Even when the lead is marked as won it still shows … to
// move ahead. Also it shows in the qualified? is that OK" — No: a won deal now
// closes the lead it came from as CONVERTED.
t_section('A won deal closes its lead');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
try {
    t_as_admin();
    if (function_exists('leads_migrate')) leads_migrate(); opp_migrate();
    $pdo->prepare("INSERT INTO pipelines (name,entity_kind,is_default,active,created_at) VALUES ('WDL Lead Pipeline','LEAD',0,1,?)")->execute([date('c')]);
    $lp = (int) $pdo->lastInsertId();
    foreach ([[1, 'Qualified', 'OPEN'], [2, 'Converted', 'WON']] as [$seq, $nm, $k])
        $pdo->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active) VALUES (?,?,?,?,50,7,1)")->execute([$lp, $seq, $nm, $k]);
    $qual = (int) pipeline_stages($lp)[0]['id'];
    $mkLead = function ($name, $partner = null) use ($pdo, $lp, $qual) {
        $pdo->prepare("INSERT INTO leads (ref, company_name, requirement, value, status, pipeline_id, stage_id, partner_id, created_at) VALUES (?,?, 'TPI', 450000, 'OPEN', ?, ?, ?, ?)")
            ->execute(['WDL-' . mt_rand(1000, 99999), $name, $lp, $qual, $partner, date('c')]);
        return (int) $pdo->lastInsertId();
    };
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, created_at) VALUES ('WDL Cement Ltd','WDL Cement Ltd',1,?)")->execute([date('c')]);
    $partner = (int) $pdo->lastInsertId();

    $l1 = $mkLead('WDL Cement Ltd', $partner);
    $d1 = (int) opp_from_lead($l1, [])['id'];
    $won = 0; foreach (pipeline_stages((int) opp_row($d1)['pipeline_id']) as $st) if ($st['kind'] === 'WON') { $won = (int) $st['id']; break; }
    t_ok($won > 0, 'ARMING · the deal pipeline has a Won stage');
    $r = opp_move($d1, $won, [], true);
    t_eq($r['status'] ?? '', 'WON', 'the deal is marked won');
    $L = lead_row($l1);
    t_eq([$L['status'], (int) $L['converted_partner_id']], ['CONVERTED', $partner], 'its lead is now Converted, to the deal\'s customer');
    t_eq((string) ops_val("SELECT name FROM pipeline_stages WHERE id=?", [(int) $L['stage_id']]), 'Converted', 'and sits in the Converted column, not Qualified');
    t_eq((int) ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name='WDL Cement Ltd'"), 1, 'no second customer is made');

    $l2 = $mkLead('WDL New Prospect ' . getmypid());
    $d2 = (int) opp_from_lead($l2, [])['id'];
    opp_move($d2, $won, [], true);
    $L2 = lead_row($l2);
    t_ok($L2['status'] === 'CONVERTED' && (int) $L2['converted_partner_id'] > 0, 'a deal with no customer yet: the lead is converted and the customer made');
    t_eq((int) opp_row($d2)['partner_id'], (int) $L2['converted_partner_id'], '… and the deal gets that customer');

    $l3 = $mkLead('WDL Older ' . getmypid(), $partner);
    $d3 = (int) opp_from_lead($l3, [])['id'];
    $pdo->prepare("UPDATE opportunities SET status='WON', stage_id=? WHERE id=?")->execute([$won, $d3]);   // won before this rule
    t_eq(lead_row($l3)['status'], 'OPEN', 'ARMING · a lead whose deal was won earlier is still open');
    lead_close_won_deals_sweep($l3);
    t_eq(lead_row($l3)['status'], 'CONVERTED', 'opening it closes it as Converted');

    $view = (string) file_get_contents(__DIR__ . '/../views/ops/lead_detail.php');
    t_ok(strpos($view, '<details class="fold" data-tab="History" open>') !== false, 'the History tab shows its content straight away (it was folded shut)');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
