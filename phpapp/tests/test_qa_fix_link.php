<?php
// Every Report-QA finding gets a direct "fix it here" link, routed to the right
// screen: source-document uploads -> the review screen; header identification &
// traceability (PO, QAP/ITP, drawing) -> Edit details; body/quantity/signature
// -> the report fill screen.
t_section('report QA findings link to where they are fixed');

$href = fn($issue) => idems_qa_fix_link($issue, 42)['href'];

// Missing source documents -> upload on the review screen.
t_ok(strpos($href(['category'=>'missing-document', 'title'=>'Missing document', 'why'=>'Purchase Order has not been uploaded against this report.']), '/document-review?id=42') === 0,
     'a missing uploaded document points to the review (upload) screen');
t_ok(strpos($href(['category'=>'missing-document', 'why'=>'QAP / ITP has not been uploaded against this report.']), '/document-review') === 0,
     'a missing QAP/ITP upload points to the review screen');

// Header identification / traceability -> Edit details.
t_ok(strpos($href(['category'=>'completeness', 'title'=>'PO identified is missing or incomplete', 'location'=>'PO identified']), '/document-edit?id=42') === 0,
     'a missing PO identification points to Edit details');
t_ok(strpos($href(['category'=>'completeness', 'title'=>'QAP / ITP identified is missing or incomplete', 'location'=>'QAP / ITP identified']), '/document-edit') === 0,
     'a missing QAP/ITP identification points to Edit details');
t_ok(strpos($href(['category'=>'traceability', 'why'=>'No purchase-order reference is recorded on the report.']), '/document-edit') === 0,
     'a traceability PO-reference gap points to Edit details');
t_ok(strpos($href(['category'=>'traceability', 'why'=>'No drawing number is recorded on the report.']), '/document-edit') === 0,
     'a traceability drawing-number gap points to Edit details');

// Signature and body issues -> the report body (fill screen).
t_ok(strpos($href(['category'=>'completeness', 'title'=>'Signature is missing or incomplete', 'location'=>'Signature']), '/document-fill?id=42') === 0,
     'a missing signature points to the report body');
t_ok(strpos($href(['category'=>'quantity', 'title'=>'Quantity reconciliation error']), '/document-fill') === 0,
     'a quantity error points to the report body');
t_ok(strpos($href(['category'=>'evidence', 'title'=>'A finding mentions a possible defect but no evidence is attached']), '/document-fill') === 0,
     'an evidence gap points to the report body');

// Every finding always yields a usable link and label.
foreach ([['category'=>'missing-document'], ['category'=>'traceability'], ['category'=>'completeness'], ['category'=>'quantity'], ['category'=>'anything-else']] as $i) {
    $lk = idems_qa_fix_link($i, 42);
    t_ok(!empty($lk['href']) && !empty($lk['label']), 'category "' . $i['category'] . '" yields a link + label');
}
