<?php
// Field-finding #3 — "Add a contract": (a) "Quantity sold" must be MULTI-LINE (many items — man-days /
// man-months / other), (b) if no quotation/line item exists, allow typing lines inline, and (c) Edit /
// Delete a contract once created. Contracts now carry a contract_line_items list; qty_total stays the
// SUM so every existing quantity gate keeps working.
t_section('Field #3 — contract multi-line quantity, inline lines, edit & delete');

if (function_exists('contracts_migrate')) contracts_migrate();
$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Contract Co','Contract Co',1,'ACTIVE')")->execute();
$pid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO partner_contracts (partner_id,contract_number,title,value,qty_total) VALUES (?,?,?,?,?)")
    ->execute([$pid, 'CON-F3-1', 'Rate contract', 500000, 99]);
$cid = (int)$pdo->lastInsertId();

// (a) Multi-line quantity: replace with two items; qty_total becomes their sum, unit the dominant one.
$kept = contract_replace_lines($cid, [
    ['description' => 'Third-party inspection', 'quantity' => 40, 'unit' => 'MANDAY'],
    ['description' => 'Resident engineer',      'quantity' => 3,  'unit' => 'MANMONTH'],
]);
t_eq(2, $kept, 'two quantity lines are kept');
$lines = contract_lines($cid);
t_eq(2, count($lines), 'the contract lists both lines');
t_eq('Third-party inspection', $lines[0]['description'], 'the first item is stored in order');
$row = ops_one("SELECT qty_total, qty_unit FROM partner_contracts WHERE id=?", [$cid]);
t_ok((float)$row['qty_total'] == 43.0, 'qty_total is kept as the SUM of the line quantities (gates keep working)');
t_eq('MANDAY', $row['qty_unit'], 'the dominant unit (by quantity) labels the total');

// Blank rows are dropped; a single quantity with no description still counts if it has a qty.
$kept2 = contract_replace_lines($cid, [
    ['description' => 'Audit days', 'quantity' => 10, 'unit' => 'AUDIT_DAY'],
    ['description' => '',           'quantity' => 0,  'unit' => 'MANDAY'],   // blank → dropped
]);
t_eq(1, $kept2, 'an empty line is dropped');
t_ok((float)ops_val("SELECT qty_total FROM partner_contracts WHERE id=?", [$cid]) == 10.0, 'qty_total re-syncs after an edit');

// (b) Parse inline-typed lines from the posted parallel arrays.
$parsed = contract_lines_from_post(['cl_desc' => ['A', 'B'], 'cl_qty' => ['5', '2'], 'cl_unit' => ['MANDAY', 'MANMONTH']]);
t_eq(2, count($parsed), 'inline-typed lines parse from the form arrays');
t_eq('B', $parsed[1]['description'], 'the second inline line parses');
t_eq(2.0, (float)$parsed[1]['quantity'], 'its quantity parses');

// (b) Seeded from a quotation's countable line items (a lump sum has no quantity → skipped).
$pdo->prepare("INSERT INTO quotations (quote_no,rev,is_current,client_id,client_name,status,created_at) VALUES ('Q-F3',0,1,?,'Contract Co','DRAFT',?)")->execute([$pid, date('c')]);
$qid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO quote_lines (quote_id,line_no,description,unit,qty) VALUES (?,1,'Inspection','MANDAY',20)")->execute([$qid]);
$pdo->prepare("INSERT INTO quote_lines (quote_id,line_no,description,unit,qty) VALUES (?,2,'Mobilisation','LUMPSUM',1)")->execute([$qid]);
$qLines = contract_lines_from_quote($qid);
t_eq(1, count($qLines), 'only countable quotation lines seed the contract (a lump sum is skipped)');
t_eq(20.0, (float)$qLines[0]['quantity'], 'the countable line carries its quantity');

// (c) Delete guard: a contract with calls under its number cannot be deleted (the handler refuses).
$pdo->prepare("INSERT INTO calls (call_code, client_id, contract_number, status, created_at) VALUES ('CALL-F3',?, 'CON-F3-1', 'OPEN', ?)")->execute([$pid, date('c')]);
$callN = (int)ops_val("SELECT COUNT(*) FROM calls WHERE COALESCE(contract_number,'')=?", ['CON-F3-1']);
t_ok($callN >= 1, 'work exists under this contract number (so delete must be refused)');

// --- Wiring ---
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "\$route === 'contract-edit' && \$method === 'POST'") !== false
     && strpos($ops, "\$route === 'contract-delete' && \$method === 'POST'") !== false,
     'the edit and delete routes are dispatched');
$src = file_get_contents(__DIR__ . '/../lib/contracts.php');
t_ok(strpos($src, 'function ops_contract_edit') !== false && strpos($src, 'contract_replace_lines($id') !== false,
     'edit updates the header and replaces the quantity lines');
$dfn = strpos($src, 'function ops_contract_delete');
$dblk = substr($src, $dfn, 1400);
t_ok(strpos($dblk, "COUNT(*) FROM calls WHERE COALESCE(contract_number,'')=?") !== false
     && strpos($dblk, 'COUNT(*) FROM partner_purchase_orders WHERE contract_id=?') !== false,
     'delete refuses a contract that has calls/jobs or POs under it');
t_ok(strpos($dblk, "can('crm.contract.register') || is_master()") !== false, 'delete is gated to Accounts / back-office');

$det = file_get_contents(__DIR__ . '/../views/detail.php');
t_ok(strpos($det, 'name="cl_desc[]"') !== false && strpos($det, 'name="cl_qty[]"') !== false && strpos($det, 'name="cl_unit[]"') !== false,
     'the Add-contract form captures multi-line quantities');
t_ok(strpos($det, 'class="btn small secondary cl-add"') !== false, 'the Add form can add more lines');
$cd = file_get_contents(__DIR__ . '/../views/ops/contract_detail.php');
t_ok(strpos($cd, 'Quantity sold') !== false && strpos($cd, '/contract-edit') !== false && strpos($cd, '/contract-delete') !== false,
     'the contract detail shows the quantity lines and Edit / Delete');
t_ok(strpos($cd, 'empty($canEditContract)') !== false, 'Edit / Delete are gated on the detail page');

// Clean up (shared DB).
$pdo->prepare("DELETE FROM calls WHERE contract_number='CON-F3-1'")->execute();
$pdo->prepare("DELETE FROM contract_line_items WHERE contract_id=?")->execute([$cid]);
$pdo->prepare("DELETE FROM quote_lines WHERE quote_id=?")->execute([$qid]);
$pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$qid]);
$pdo->prepare("DELETE FROM partner_contracts WHERE id=?")->execute([$cid]);
$pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$pid]);
