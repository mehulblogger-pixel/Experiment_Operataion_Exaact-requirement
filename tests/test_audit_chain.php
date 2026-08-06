<?php
// The compliance audit trail is sealed and detects tampering (Roadmap Step 8).
t_section('audit trail tamper-evidence');

for ($i = 1; $i <= 4; $i++) {
    idems_log('report_doc', $i, 'TEST_ACTION', ['field' => 'status', 'old' => 'A', 'new' => 'B', 'irn' => "T$i"]);
}
$v = idems_audit_verify();
t_ok(!empty($v['ok']), 'chain valid after writing entries');
t_ok($v['checked'] >= 4, "sealed rows are checked ({$v['checked']})");

// Editing a stored value must break the seal (content tamper).
db()->exec("UPDATE idems_audit SET new_value='HACKED' WHERE action='TEST_ACTION' AND entity_id=2");
$v = idems_audit_verify();
t_ok(empty($v['ok']) && $v['broken'] >= 1, 'content tamper is detected');
t_ok($v['content'] >= 1, 'the break is reported as a content break');

// Put it back — the chain should verify clean again.
db()->exec("UPDATE idems_audit SET new_value='B' WHERE action='TEST_ACTION' AND entity_id=2");
$v = idems_audit_verify();
t_ok(!empty($v['ok']), 'chain valid again after the value is restored');

// Deleting a row must break the link chain for the row that followed it.
$mid = (int)ops_val("SELECT id FROM idems_audit WHERE action='TEST_ACTION' AND entity_id=2");
db()->prepare("DELETE FROM idems_audit WHERE id=?")->execute([$mid]);
$v = idems_audit_verify();
t_ok(empty($v['ok']) && ($v['links'] ?? 0) >= 1, 'row deletion is detected as a broken link');
