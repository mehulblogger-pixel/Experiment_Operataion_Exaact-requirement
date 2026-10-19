<?php
// P11 — can a job done for client A be billed on an invoice addressed to client B?
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true); $E = json_decode(file_get_contents(__DIR__ . '/p3e_ids.json'), true);
as_user($I['finance']);
$job = (int)val("SELECT id FROM jobs WHERE id=?", [(int)val("SELECT MAX(id) FROM jobs")]);
db()->prepare("UPDATE jobs SET closed_flag=1 WHERE id=?")->execute([$job]);
$jc = row("SELECT j.id, j.job_code, c.client_id FROM jobs j JOIN calls c ON c.id=j.call_id WHERE j.id=?", [$job]);
echo "job " . json_encode($jc) . " (client " . val("SELECT legal_name FROM business_partners WHERE id=?", [$jc['client_id']]) . ")\n";
$inv = books_invoice_create(['partner_id' => $I['p_hold'], 'office_id' => $I['office']]);
echo "invoice #{$inv['id']} addressed to " . val("SELECT partner_name FROM invoices WHERE id=?", [$inv['id']]) . "\n";
echo "books_line_add(job of another client): '" . books_line_add((int)$inv['id'], ['job_id' => $job]) . "'\n";
echo "lines: " . json_encode(array_map(fn($l) => [$l['job_id'], $l['description'], $l['line_total']], books_lines((int)$inv['id']))) . "\n";
