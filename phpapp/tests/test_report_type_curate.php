<?php
// Tidy-the-catalogue: hiding empty report types deactivates only types that have
// NO form and NO reports (reversible, never deletes, never touches a type in use).
// The controller runs the same SQL; this guards the exact selection rule.
t_section('report-type curation (hide empty, keep in-use)');

$pdo = db();
// A ready type (has a form) — must survive.
$ready = idems_build_fire_extinguisher_report();
// An empty, unused type — the target to hide.
$pdo->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,created_at) VALUES ('ZEMPTY','Empty Unused','TPIA_REPORT',1,0,999,?)")->execute([date('c')]);
$emptyId = (int)$pdo->lastInsertId();
// An empty type that HAS a filed report — must NOT be hidden (in use).
$pdo->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,created_at) VALUES ('ZUSED','Empty But Used','TPIA_REPORT',1,0,999,?)")->execute([date('c')]);
$usedId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,status,data,created_at) VALUES (?,?,?,?,?,?)")
    ->execute([$usedId, 'ZUSED', 'ZUSED-1', 'draft', '[]', date('c')]);

$sel = "SELECT id FROM report_types rt WHERE COALESCE(rt.active,1)=1
        AND NOT EXISTS (SELECT 1 FROM report_fields f WHERE f.report_type_id=rt.id)
        AND NOT EXISTS (SELECT 1 FROM report_docs d WHERE d.report_type_id=rt.id AND COALESCE(d.deleted,0)=0)";
$victimIds = array_map(fn($r)=>(int)$r['id'], ops_all($sel));

t_ok(in_array($emptyId, $victimIds, true), 'an empty, unused type is selected to hide');
t_ok(!in_array($usedId, $victimIds, true), 'an empty type WITH a filed report is NOT hidden (in use)');
t_ok(!in_array($ready, $victimIds, true), 'a type with a designed form is NOT hidden');

// Apply the hide, as the controller does.
foreach ($victimIds as $vid) $pdo->prepare("UPDATE report_types SET active=0 WHERE id=?")->execute([$vid]);
t_eq((int)ops_val("SELECT active FROM report_types WHERE id=?", [$emptyId]), 0, 'the empty type is now inactive (hidden)');
t_eq((int)ops_val("SELECT active FROM report_types WHERE id=?", [$usedId]), 1, 'the in-use type is still active');
t_eq((int)ops_val("SELECT active FROM report_types WHERE id=?", [$ready]), 1, 'the ready type is still active');

// Nothing was deleted — the hidden type still exists.
t_ok((int)ops_val("SELECT COUNT(*) FROM report_types WHERE id=?", [$emptyId]) === 1, 'the hidden type still exists (not deleted)');

// Show-all brings them back.
$pdo->exec("UPDATE report_types SET active=1 WHERE COALESCE(active,1)=0");
t_eq((int)ops_val("SELECT active FROM report_types WHERE id=?", [$emptyId]), 1, 'show-all re-activates the hidden type');
