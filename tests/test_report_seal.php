<?php
// Report content seal (Roadmap Step 16 — a tampered report, not just tampered
// evidence, is detectable at /verify).
t_section('report content seal (Step 16)');

$now = date('c');
db()->prepare("INSERT INTO report_docs (irn, status, finalized, result, remarks, data, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?)")
    ->execute(['TIR/26/1000', 'ISSUED', 1, 'ACCEPT', 'Original findings', '{"a":1}', $now, $now]);
$id = (int)db()->lastInsertId();

// Before sealing (legacy issued report): reported unsealed, never failed.
$c0 = idems_content_check(ops_one("SELECT * FROM report_docs WHERE id=?", [$id]));
t_ok(empty($c0['sealed']) && $c0['ok'], 'an unsealed (legacy) report is reported unsealed, not failed');

// Seal it (this is what the finalize handler does at issue).
idems_seal_content($id);
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$id]);
t_ok(strlen((string)$doc['content_seal']) === 64, 'a content seal is stored at issue');
$c1 = idems_content_check($doc);
t_ok($c1['sealed'] && $c1['ok'], 'the sealed content verifies as unchanged');

// Tamper the content directly, as a DB-level attacker would.
db()->prepare("UPDATE report_docs SET remarks=? WHERE id=?")->execute(['TAMPERED findings', $id]);
$c2 = idems_content_check(ops_one("SELECT * FROM report_docs WHERE id=?", [$id]));
t_ok($c2['sealed'] && !$c2['ok'], 'altering the report content after issue is detected');

// Restore — verifies clean again.
db()->prepare("UPDATE report_docs SET remarks=? WHERE id=?")->execute(['Original findings', $id]);
t_ok(idems_content_check(ops_one("SELECT * FROM report_docs WHERE id=?", [$id]))['ok'], 'restoring the content verifies clean again');

// The public verify lookup carries the content result through.
db()->prepare("UPDATE report_docs SET verify_code=? WHERE id=?")->execute(['SEAL-TEST-CODE-9', $id]);
$look = verify_lookup('SEAL-TEST-CODE-9');
t_ok(is_array($look) && ($look['content']['ok'] ?? null) === true, 'the public /verify lookup reports content integrity');
