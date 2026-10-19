<?php
// Reference numbers derived from COUNT(*)+1: lead refs and converted-inquiry numbers
// re-issue a number after a delete.
require __DIR__ . '/plib.php';
function mklead($name) { $b = lastid('leads'); R('ADMIN', 'lead-new', 'POST', [], ['company_name' => $name, 'contact_name' => 'A', 'requirement' => 'x', 'office_id' => (string)$GLOBALS['OFF']]); return lastid('leads'); }
H('A. lead ref after deleting a lead');
$a = mklead('Ref A Ltd'); $b = mklead('Ref B Ltd');
echo "    created " . ops_val("SELECT ref FROM leads WHERE id=?", [$a]) . ", " . ops_val("SELECT ref FROM leads WHERE id=?", [$b]) . "\n";
show('lead-delete first', R('ADMIN', 'lead-delete', 'POST', [], ['id' => $a]));
$c = mklead('Ref C Ltd');
echo "    next lead gets " . ops_val("SELECT ref FROM leads WHERE id=?", [$c]) . " (the deleted lead's ref was " . "L-" . date('Y') . "-0001)\n";
H('B. converted-inquiry number after deleting an inquiry');
$ids = [];
foreach (['Inq One Ltd','Inq Two Ltd','Inq Three Ltd'] as $n) { $l = mklead($n); R('ADMIN', 'lead-convert', 'POST', ['id' => $l], ['id' => $l]); }
foreach (ops_all("SELECT id, inquiry_no FROM crm_inquiries ORDER BY id") as $r) echo "    {$r['id']} {$r['inquiry_no']}\n";
$first = (int)ops_val("SELECT MIN(id) FROM crm_inquiries");
show('inquiry-delete first converted inquiry', R('ADMIN', 'inquiry-delete', 'POST', [], ['id' => $first]));
$l = mklead('Inq Four Ltd'); show('lead-convert fourth', R('ADMIN', 'lead-convert', 'POST', ['id' => $l], ['id' => $l]));
foreach (ops_all("SELECT id, inquiry_no FROM crm_inquiries ORDER BY id") as $r) echo "    {$r['id']} {$r['inquiry_no']}\n";
foreach (ops_all("SELECT inquiry_no, COUNT(*) n FROM crm_inquiries GROUP BY inquiry_no HAVING COUNT(*)>1") as $d) echo "    *** DUPLICATE inquiry_no {$d['inquiry_no']} x{$d['n']}\n";
echo "    indexes on crm_inquiries: " . json_encode(ops_all("SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name='crm_inquiries'")) . "\n";
