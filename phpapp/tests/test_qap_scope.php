<?php
// Upload the QAP once → the report's ITP / inspection-scope table fills itself,
// so the inspector only adds observations. AI does the extraction when the
// company has enabled a provider (one cheap call, cached per QAP); with no AI it
// falls back to reading the numbered clauses. These tests exercise the no-AI
// path (there is no provider in the test tenant) plus the mapping and guards.
t_section('QAP → inspection scope autofill');

// The standard inspection report carries the scope_activities table.
$tid = idems_build_mgh_report('MGHIR2', 'Inspection Report (scope test)');
t_ok($tid > 0, 'a report type with a scope table exists');
$field = idems_scope_target_field($tid);
t_ok(is_array($field) && $field['fkey'] === 'scope_activities', 'the ITP / scope table is found as the autofill target');

// A job with a text QAP (a simple numbered ITP), stored as the app stores it.
db()->prepare("INSERT INTO jobs (stage, created_at) VALUES ('REPORT_PENDING', ?)")->execute([date('c')]);
$jobId = (int) db()->lastInsertId();
$qapText = "Inspection & Test Plan — Fasteners to IS 1367\n"
    . "1.0 Raw material test certificate review\n"
    . "2.0 Dimensional inspection of fasteners\n"
    . "3.0 Hardness test witness\n"
    . "4.0 Thread gauging as per gauge plan\n"
    . "5.0 Visual and surface inspection\n"
    . "6.0 Marking, coating and packing verification\n";
$dataUrl = 'data:text/plain;base64,' . base64_encode($qapText);
db()->prepare("INSERT INTO job_qaps (job_id, file_name, mime, data, uploaded_at) VALUES (?,?,?,?,?)")
    ->execute([$jobId, 'ITP.txt', 'text/plain', $dataUrl, date('c')]);
$qapId = (int) db()->lastInsertId();

// A report of that type on that job.
db()->prepare("INSERT INTO report_docs (irn, report_type_id, type_code, title, status, job_id, created_at, updated_at)
    VALUES ('IR-QAP-1', ?, 'IR', 'Fastener inspection', 'DRAFT', ?, ?, ?)")
    ->execute([$tid, $jobId, date('c'), date('c')]);
$docId = (int) db()->lastInsertId();
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$docId]);

// Extract the scope from the QAP (no AI in tests → heuristic clause parse).
$res = idems_scope_from_qap($doc, $qapId);
t_ok(empty($res['err']), 'the QAP is read without error (' . ($res['err'] ?? '') . ')');
t_eq((string)$res['source'], 'heuristic', 'with no AI provider it uses the basic clause parse');
t_ok((int)$res['n'] >= 5, 'every numbered activity in the QAP becomes a scope row (' . (int)$res['n'] . ')');

// The rows landed in the report body under the scope table, keyed by the table's
// own column keys, with the activity text carried across.
$fresh = ops_one("SELECT data FROM report_docs WHERE id=?", [$docId]);
$data = json_decode($fresh['data'] ?: '[]', true);
t_ok(isset($data['scope_activities']) && count($data['scope_activities']) >= 5, 'the scope table is populated in the report data');
$row0 = $data['scope_activities'][0];
t_ok(array_key_exists('description_of_activity', $row0), 'rows use the scope table\'s column keys');
$acts = implode(' | ', array_map(fn($r) => (string)($r['description_of_activity'] ?? ''), $data['scope_activities']));
t_ok(stripos($acts, 'Hardness test') !== false, 'the QAP activities are carried into the description column');
$clauses = array_filter(array_map(fn($r) => (string)($r['itp_clause_no'] ?? ''), $data['scope_activities']));
t_ok(count($clauses) >= 5, 'the clause numbers are carried across too');

// Mapping directly: generic keys → the live column keys (works on any form).
$mapped = idems_scope_map_rows([
    ['clause' => '7.0', 'activity' => 'Hydrostatic test', 'quantum' => '100%', 'inspection_type' => 'Witness',
     'acceptance' => 'ASME VIII', 'remarks' => 'at 1.5x design'],
], $field);
t_eq(count($mapped), 1, 'a generic row maps to one table row');
t_eq((string)$mapped[0]['quantum_of_check'], '100%', 'quantum maps to the quantum column');
t_eq((string)$mapped[0]['inspection_type'], 'Witness', 'inspection type maps across');
t_ok(stripos((string)$mapped[0]['remarks'], 'ASME VIII') !== false, 'acceptance criteria land in the remarks column');

// Guard: a report type with no scope table is refused clearly, not fatally.
db()->prepare("INSERT INTO report_types (code,name,category,active,is_system,sort_order,created_at) VALUES ('NOSCOPE','No scope','TPIA_REPORT',1,0,999,?)")->execute([date('c')]);
$noScopeId = (int) db()->lastInsertId();
db()->prepare("INSERT INTO report_docs (irn, report_type_id, type_code, status, job_id, created_at, updated_at) VALUES ('IR-NS-1', ?, 'X', 'DRAFT', ?, ?, ?)")
    ->execute([$noScopeId, $jobId, date('c'), date('c')]);
$nsDoc = ops_one("SELECT * FROM report_docs WHERE irn='IR-NS-1'");
$res2 = idems_scope_from_qap($nsDoc, $qapId);
t_ok((int)$res2['n'] === 0 && !empty($res2['err']), 'a report type without a scope table is refused with a clear reason, not a crash');

// Guard: a QAP that belongs to a different job is refused.
$res3 = idems_scope_from_qap($doc, 999999);
t_ok(!empty($res3['err']), 'a QAP that is not on this report\'s job is refused');

// --- Item particulars: copied FREE from the linked purchase order -----------
t_section('QAP/PO → item particulars autofill');

$itemsField = idems_items_target_field($tid);
t_ok(is_array($itemsField) && $itemsField['fkey'] === 'po_items', 'the PO-items table is found as the item autofill target');

// A purchase order with two line items, linked through call → job → report.
db()->prepare("INSERT INTO partner_purchase_orders (partner_id, po_number, po_type, value) VALUES (0,'PO-IT-1','REGULAR',0)")->execute();
$poId = (int) db()->lastInsertId();
db()->prepare("INSERT INTO po_line_items (purchase_order_id, description, item_type, quantity) VALUES (?,?,?,?)")
    ->execute([$poId, 'Hex bolts M16x60 Gr 8.8', 'NOS', 500]);
db()->prepare("INSERT INTO po_line_items (purchase_order_id, description, item_type, quantity) VALUES (?,?,?,?)")
    ->execute([$poId, 'Hex nuts M16 Gr 8', 'NOS', 500]);
db()->prepare("INSERT INTO calls (po_id, created_at) VALUES (?, ?)")->execute([$poId, date('c')]);
$callId = (int) db()->lastInsertId();
db()->prepare("UPDATE jobs SET call_id=? WHERE id=?")->execute([$callId, $jobId]);

$doc2 = ops_one("SELECT * FROM report_docs WHERE id=?", [$docId]);
t_eq(idems_report_po_id($doc2), $poId, 'the report resolves its linked purchase order (report → call → PO)');

$ir = idems_items_from_qap($doc2, $qapId);
t_eq((string)$ir['source'], 'po', 'items are taken straight from the linked PO (no AI)');
t_ok((int)$ir['n'] === 2, 'both PO line items become item rows');

$fresh2 = ops_one("SELECT data FROM report_docs WHERE id=?", [$docId]);
$data2 = json_decode($fresh2['data'] ?: '[]', true);
t_ok(isset($data2['po_items']) && count($data2['po_items']) === 2, 'the PO-items table is populated');
$descs = implode(' | ', array_map(fn($r) => (string)($r['description_as_per_po'] ?? ''), $data2['po_items']));
t_ok(stripos($descs, 'Hex bolts') !== false, 'the PO line description lands in the description column');
$qtys = array_map(fn($r) => (string)($r['po_qty'] ?? ''), $data2['po_items']);
t_ok(in_array('500', $qtys, true), 'the ordered quantity lands in the PO Qty column');

// Idempotent — running again does not double-fill an already-populated table.
$ir2 = idems_items_from_qap(ops_one("SELECT * FROM report_docs WHERE id=?", [$docId]), $qapId);
t_eq((int)$ir2['n'], 0, 'a second run does not duplicate the item rows');

// Item mapping picks PO Qty specifically, not offered/passed/rejected qty.
$mi = idems_items_map_rows([['sr' => '1', 'description' => 'Test item', 'size' => '10', 'unit' => 'nos', 'po_qty' => '42', 'heat_no' => 'H99']], $itemsField);
t_eq(count($mi), 1, 'a generic item row maps to one table row');
t_eq((string)$mi[0]['po_qty'], '42', 'the quantity maps to the PO Qty column (not offered/passed)');
t_eq((string)$mi[0]['heat_no'], 'H99', 'the heat number maps across');
t_eq((string)$mi[0]['offered_qty'], '', 'the offered/passed columns are left blank for the inspector');
