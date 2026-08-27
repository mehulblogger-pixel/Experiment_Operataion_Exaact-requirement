<?php
// Field-finding #5 — "a lead moved to Opportunity/Deal must transfer and appear there, and the
// board must update." The transfer itself (opp_from_lead) does land the deal on the opportunity
// board — this pins that end-to-end so it can't regress. The gap was that the LEADS board, where
// the person is standing, gave no sign the lead had become a deal, so it read as "nothing
// transferred." lead_board() now marks each card that has a deal opened from it.
t_section('Field #5 — lead → deal transfers onto the opportunity board, and the leads board shows it');

opp_migrate();
$pdo = db();

// A dedicated lead pipeline for this test — the suite shares one accumulating DB,
// so we must not inflate the default pipeline's open-lead count (a downstream test
// asserts which pipeline the board falls back to).
$pdo->prepare("INSERT INTO pipelines (name,entity_kind,is_default,active,created_at) VALUES ('T5 Lead Pipeline','LEAD',0,1,?)")->execute([date('c')]);
$leadPipe = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active) VALUES (?,1,'New','OPEN',10,7,1)")->execute([$leadPipe]);
$firstLeadStage = (int)((pipeline_stages($leadPipe)[0]['id']) ?? 0);
$pdo->prepare("INSERT INTO leads (ref, company_name, requirement, value, status, pipeline_id, stage_id, created_at)
               VALUES ('L-T5-1','Transfer Co','Crane load test', 120000, 'OPEN', ?, ?, ?)")
    ->execute([$leadPipe ?: null, $firstLeadStage ?: null, date('c')]);
$lid = (int)$pdo->lastInsertId();

// Precondition — nothing on the opportunity board for this lead yet.
t_eq(0, (int)ops_val("SELECT COUNT(*) FROM opportunities WHERE lead_id=?", [$lid]), 'no deal exists before the transfer');

// Work it as a deal.
$r = opp_from_lead($lid, []);
t_ok(!empty($r['id']) && empty($r['err']), 'the deal is opened from the lead');
$oid = (int)$r['id'];

// It TRANSFERS: the deal carries the lead's essentials, and lands on a real pipeline + stage.
$o = ops_one("SELECT lead_id, partner_name, value, pipeline_id, stage_id, status FROM opportunities WHERE id=?", [$oid]);
t_eq($lid, (int)$o['lead_id'], 'the deal is linked back to the lead');
t_eq('Transfer Co', (string)$o['partner_name'], 'the customer name carried across');
t_ok((float)$o['value'] == 120000.0, 'the value carried across');
t_ok((int)$o['pipeline_id'] > 0 && (int)$o['stage_id'] > 0, 'it lands on a real pipeline and stage (not stranded)');

// It APPEARS THERE: the opportunity board (default pipeline) shows it in its stage column.
$board = opp_board();
$onBoard = false; $inRightPipe = ((int)$board['pipeline'] === (int)$o['pipeline_id']);
foreach ($board['columns'] as $sid => $col)
    foreach ($col['rows'] as $row) if ((int)$row['id'] === $oid) $onBoard = true;
t_ok($inRightPipe, 'the deal landed on the board\'s default opportunity pipeline');
t_ok($onBoard, 'the deal appears on the opportunity board (the board updates)');

// Opening a deal twice from the same lead does not make a second — it returns the first.
$r2 = opp_from_lead($lid, []);
t_ok((int)$r2['id'] === $oid && !empty($r2['existing']), 'a second attempt returns the same deal, not a duplicate');

// And the LEADS board now shows the lead has a deal, so the transfer is visible where the user works.
$lb = lead_board($leadPipe);
$deal_id = null;
foreach ($lb['columns'] as $sid => $col)
    foreach ($col['leads'] as $row) if ((int)$row['id'] === $lid) $deal_id = (int)($row['deal_id'] ?? 0);
t_eq($oid, (int)$deal_id, 'the leads board marks the lead with its deal (the "◆ deal" chip)');

// A lead with no deal is not marked.
$pdo->prepare("INSERT INTO leads (ref, company_name, value, status, pipeline_id, stage_id, created_at)
               VALUES ('L-T5-2','Plain Co', 1000, 'OPEN', ?, ?, ?)")->execute([$leadPipe ?: null, $firstLeadStage ?: null, date('c')]);
$lid2 = (int)$pdo->lastInsertId();
$lb2 = lead_board($leadPipe);
$deal2 = 0;
foreach ($lb2['columns'] as $sid => $col)
    foreach ($col['leads'] as $row) if ((int)$row['id'] === $lid2) $deal2 = (int)($row['deal_id'] ?? 0);
t_eq(0, $deal2, 'a lead with no deal carries no deal marker');

// The card wiring exists in the view.
$view = file_get_contents(__DIR__ . '/../views/ops/leads.php');
t_ok(strpos($view, "!empty(\$l['deal_id'])") !== false && strpos($view, '◆ deal') !== false,
     'the board card shows a deal chip when the lead has one');

// Clean up — the suite shares one accumulating DB with no per-file rollback, so
// leave nothing behind: two open leads on a fresh pipeline would otherwise tip a
// later test that asserts which pipeline the board falls back to.
$pdo->prepare("DELETE FROM opportunities WHERE lead_id IN (?,?)")->execute([$lid, $lid2]);
$pdo->prepare("DELETE FROM leads WHERE id IN (?,?)")->execute([$lid, $lid2]);
$pdo->prepare("DELETE FROM pipeline_stages WHERE pipeline_id=?")->execute([$leadPipe]);
$pdo->prepare("DELETE FROM pipelines WHERE id=?")->execute([$leadPipe]);
