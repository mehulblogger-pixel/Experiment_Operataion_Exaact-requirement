<?php
// The leads board shows one pipeline at a time. When an industry template makes
// a NEW default pipeline, existing leads keep their old pipeline on purpose — so
// the board must fall back to the pipeline that actually holds open leads,
// otherwise they vanish from the board (the list still shows them).
t_section('leads board falls back to the pipeline that has open leads');

leads_migrate();
$pdo = db();

// Pipeline A (will be the leads' pipeline) with one open stage.
$pdo->prepare("INSERT INTO pipelines (name,entity_kind,is_default,active,created_at) VALUES ('LB Old','LEAD',0,1,?)")->execute([date('c')]);
$pA = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active) VALUES (?,1,'New','OPEN',10,7,1)")->execute([$pA]);
$sA = (int)$pdo->lastInsertId();

// Pipeline B — the new DEFAULT, with no leads on it.
$pdo->prepare("UPDATE pipelines SET is_default=0 WHERE entity_kind='LEAD'")->execute();
$pdo->prepare("INSERT INTO pipelines (name,entity_kind,is_default,active,created_at) VALUES ('LB New Default','LEAD',1,1,?)")->execute([date('c')]);
$pB = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active) VALUES (?,1,'New','OPEN',10,7,1)")->execute([$pB]);

// An open lead sits on pipeline A (the non-default one).
$pdo->prepare("INSERT INTO leads (ref,pipeline_id,stage_id,company_name,status,stage_since,created_at,updated_at)
               VALUES ('LB-1',?,?,'Board Test Co','OPEN',?,?,?)")->execute([$pA, $sA, date('c'), date('c'), date('c')]);

// Default pipeline is B (empty). The board with no explicit pipeline must fall
// back to A and surface the lead.
$board = lead_board(0);
$found = 0;
foreach ($board['columns'] as $col) $found += count($col['leads']);
t_ok($found >= 1, 'the open lead on the non-default pipeline appears on the board');
t_eq((int)$board['pipeline'], $pA, 'the board fell back to the pipeline that holds the open lead');

// Explicitly asking for the empty default still shows it (respect the choice).
$board2 = lead_board($pB);
$found2 = 0; foreach ($board2['columns'] as $col) $found2 += count($col['leads']);
t_eq((int)$board2['pipeline'], $pB, 'an explicit pipeline choice is respected');
t_eq($found2, 0, 'the explicitly-chosen empty pipeline shows no leads');
