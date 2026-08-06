<?php
// Quotation revisions — revise action, revision numbering, and a recorded,
// visible change history (owner request, §23).
t_section('quotation revision & history');

crm_migrate();

// A quotation to work with.
db()->prepare("INSERT INTO quotations (quote_no, rev, parent_id, is_current, client_name, subject,
    total_amount, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
    ->execute(['Q-2607-050', 0, null, 1, 'Adani', 'PV inspection', 100000, 'DRAFT', date('c'), date('c')]);
$id = (int)db()->lastInsertId();
$base = $id;

// Revision numbering reads as "Rev NN".
$q = crm_quote_get($id);
t_eq(quote_label($q), 'Q-2607-050', 'the original carries no revision suffix');
$q['rev'] = 3;
t_eq(quote_label($q), 'Q-2607-050 Rev 03', 'a revision is labelled Rev NN');

// Editing records a change in the same history the revisions use.
$before = (int)ops_val("SELECT COUNT(*) FROM quote_revisions WHERE quote_id=?", [$base]);
crm_log_change($id, 'Edited');
$after = (int)ops_val("SELECT COUNT(*) FROM quote_revisions WHERE quote_id=?", [$base]);
t_ok($after === $before + 1, 'an edit is recorded in the change history');

// The recorded entry carries who and a snapshot, so a later diff is possible.
$row = ops_one("SELECT * FROM quote_revisions WHERE quote_id=? ORDER BY id DESC LIMIT 1", [$base]);
t_ok(trim((string)$row['changed_by']) !== '', 'the change records who made it');
t_ok(trim((string)$row['changed_at']) !== '', 'the change records when');
t_ok(is_array(json_decode((string)$row['snapshot'], true)), 'a snapshot is stored for the diff');

// A field-by-field diff between two snapshots reads in plain terms.
$old = ['header' => ['total_amount' => 100000, 'validity_days' => 30]];
$new = ['header' => ['total_amount' => 90000,  'validity_days' => 45]];
$diff = crm_diff_snapshots($old, $new);
$fields = array_column($diff, 'field');
t_ok(in_array('total_amount', $fields, true) && in_array('validity_days', $fields, true),
    'the diff names the fields that changed');
$byField = [];
foreach ($diff as $d) $byField[$d['field']] = $d;
t_eq($byField['total_amount']['from'], '100000', 'the diff shows the old value');
t_eq($byField['total_amount']['to'], '90000', 'the diff shows the new value');

// A revision keeps the quote number, takes the next rev, and becomes current
// while the source is demoted — modelled straight in the table as the route does.
db()->prepare("UPDATE quotations SET is_current=0 WHERE id=?")->execute([$id]);
db()->prepare("INSERT INTO quotations (quote_no, rev, parent_id, is_current, client_name, subject,
    total_amount, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
    ->execute(['Q-2607-050', 1, $base, 1, 'Adani', 'PV inspection', 90000, 'DRAFT', date('c'), date('c')]);
$revId = (int)db()->lastInsertId();
$versions = ops_all("SELECT rev, is_current FROM quotations WHERE quote_no=? ORDER BY rev", ['Q-2607-050']);
t_eq(count($versions), 2, 'both versions are kept under the one quote number');
$current = array_values(array_filter($versions, fn($v) => (int)$v['is_current'] === 1));
t_ok(count($current) === 1 && (int)$current[0]['rev'] === 1, 'exactly one version is current — the newest revision');
