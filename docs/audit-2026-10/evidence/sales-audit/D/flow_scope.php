<?php
// Branch-scope probes for records saved without a branch (the forms do not default one).
require __DIR__ . '/flow_lib.php';
act('bmA', 'project-costing-new', 'POST', [], ['title' => 'Alpha margins, no office picked']);
$pc = q1("SELECT * FROM project_costings ORDER BY id DESC LIMIT 1");
$r = act('bmB', 'project-costing', 'GET', ['id' => $pc['id']]);
$rl = act('bmB', 'project-costings', 'GET');
step('S1', "BRANCH_MANAGER office A creates costing {$pc['code']} leaving 'Executing office' blank (form default '—'); BRANCH_MANAGER office B opens it",
     'refused — the costing belongs to office A (its creator)', 'office_id=' . var_export($pc['office_id'], true) . '; office B opens it: ' . $r['say'] . '; list: ' . $rl['say']);
act('bdmA', 'quote-new', 'POST', [], quote_post(['subject' => 'No-office quote', 'office_id' => '']));
$qq = lastq();
$rb = act('bdmA', 'quote', 'GET', ['id' => $qq['id']]);
$ahm = qv("SELECT COUNT(*) FROM offices WHERE is_ahmedabad=1");
step('S2', "BDM (office A) saves {$qq['quote_no']} with the owning office left blank, then opens it", 'the creator can still see his own quote',
     'office_id=' . var_export($qq['office_id'], true) . "; offices flagged is_ahmedabad=$ahm; BDM opens it: " . $rb['say']);
file_put_contents(__DIR__ . '/flow_scope.out', implode('', $GLOBALS['LOG']));
