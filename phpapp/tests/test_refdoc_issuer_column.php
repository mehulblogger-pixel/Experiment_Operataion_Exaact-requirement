<?php
// The report's Reference-documents table gained an "Approved / Issued by" column.
// New report types get it from the templates; existing ones get it by migration —
// inserted right after "Approval Code", idempotently, and only on the standard
// column set (a table a user has customised away is left untouched).
t_section('Reference documents gains an "Approved / Issued by" column');

$pdo = db();
if (function_exists('idems_migrate')) { try { idems_migrate(); } catch (Throwable $e) {} }

// Need a report type to hang fields on.
$pdo->prepare("INSERT INTO report_types (code, name, active) VALUES ('RDT', 'Refdoc test', 1)")->execute();
$typeId = (int)$pdo->lastInsertId();

$mkField = function ($cols) use ($pdo, $typeId) {
    $pdo->prepare("INSERT INTO report_fields (report_type_id, fkey, label, ftype, table_cols, sort_order) VALUES (?,?,?,?,?,1)")
        ->execute([$typeId, 'reference_documents', 'Reference documents', 'table', $cols]);
    return (int)$pdo->lastInsertId();
};
$get = fn($id) => (string)ops_val("SELECT table_cols FROM report_fields WHERE id=?", [$id]);

// 1) The old standard column set gains the new column, right after Approval Code.
$oldId = $mkField("Document Name\nDocument Number\nRevision No.\nApproval Code\nDate of Approval|date");
idems_add_refdoc_issuer_column();
$after = $get($oldId);
t_ok(strpos($after, 'Approved / Issued by') !== false, 'the new column is added to an existing table');
$parts = preg_split('/\r?\n/', $after);
$iApproval = array_search('Approval Code', $parts, true);
$iIssuer   = array_search('Approved / Issued by', $parts, true);
$iDate     = array_search('Date of Approval|date', $parts, true);
t_ok($iApproval !== false && $iIssuer === $iApproval + 1, 'it is inserted immediately after Approval Code');
t_ok($iDate !== false && $iIssuer < $iDate, 'it sits before Date of Approval');

// 2) Idempotent — running again does not add a second copy.
idems_add_refdoc_issuer_column();
t_ok(substr_count($get($oldId), 'Approved / Issued by') === 1, 'the migration does not duplicate the column');

// 3) A field already carrying the column is untouched.
$newId = $mkField("Document Name\nDocument Number\nRevision No.\nApproval Code\nApproved / Issued by\nDate of Approval|date");
$before = $get($newId);
idems_add_refdoc_issuer_column();
t_ok($get($newId) === $before, 'a table that already has the column is left unchanged');

// 4) A customised table without Approval Code is left alone (never guessed at).
$custId = $mkField("Document Name\nMy Custom Column");
idems_add_refdoc_issuer_column();
t_ok(strpos($get($custId), 'Approved / Issued by') === false, 'a customised table is not modified');
