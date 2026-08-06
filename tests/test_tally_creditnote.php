<?php
// Credit notes export to Tally as their own voucher type (open item M2 tail).
t_section('tally credit-note export (M2)');

books_migrate();
tally_migrate();

// A client, an issued invoice, and a credit note reducing it.
db()->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, gstin, state, created_at)
    VALUES (?,?,?,?,?,?)")->execute(['Essar', 'Essar Steel', 1, '24ABCDE1234F1Z5', 'Gujarat', date('c')]);
$pid = (int)db()->lastInsertId();

db()->prepare("INSERT INTO invoices (invoice_no, partner_id, partner_name, office_id, invoice_date, status,
    subtotal, cgst, sgst, igst, total, is_igst, place_of_supply, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['INV/26/0007', $pid, 'Essar Steel', null, '2026-07-10', 'ISSUED',
               10000, 900, 900, 0, 11800, 0, '24', date('c')]);
$invId = (int)db()->lastInsertId();

db()->prepare("INSERT INTO credit_notes (cn_no, fy, invoice_id, partner_id, partner_name, cn_date, reason,
    subtotal, cgst, sgst, igst, total, status, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'ISSUED',?)")
    ->execute(['CN/26/0002', '2026-27', $invId, $pid, 'Essar Steel', '2026-07-20', 'RATE_ERROR',
               2000, 180, 180, 0, 2360, date('c')]);
$cnId = (int)db()->lastInsertId();

// It surfaces as an exportable row, carrying the original invoice + tax split.
$rows = tally_rows('2026-07-01', '2026-07-31', 'CREDITNOTE');
$cn = null; foreach ($rows as $r) if ((int)$r['id'] === $cnId) $cn = $r;
t_ok($cn !== null, 'the credit note appears in the credit-note export');
t_ok($cn['ok'], 'a complete credit note is ready to export');
t_eq($cn['invoice_number'], 'CN/26/0002', 'the voucher number is the credit-note number');
t_eq($cn['against_invoice'], 'INV/26/0007', 'it references the original invoice');
t_eq((float)$cn['total'], 2360.0, 'the credit-note total carries across');

// The XML is a Credit Note voucher that reverses the sale and hangs off the
// original invoice.
$cfg = tally_settings();
$xml = tally_xml([$cn], $cfg, 'CREDITNOTE');
t_ok(strpos($xml, 'VCHTYPE="Credit Note"') !== false, 'it is a Credit Note voucher');
t_ok(strpos($xml, '<VOUCHERNUMBER>CN/26/0002</VOUCHERNUMBER>') !== false, 'the voucher number is present');
t_ok(strpos($xml, 'INV/26/0007') !== false, 'the bill allocation references the original invoice');
t_ok(strpos($xml, 'Agst Ref') !== false, 'it knocks the original invoice down (Agst Ref)');
// The party is credited (ISDEEMEDPOSITIVE No), the reverse of a sales voucher.
t_ok(strpos($xml, 'strtoupper') === false, 'no raw code leaked into the XML');

// Exporting records it and stamps the credit note, so it is not offered twice.
$ref = tally_new_ref('CREDITNOTE');
tally_record([$cn], 'CREDITNOTE', $ref);
t_eq(ops_one("SELECT tally_ref FROM credit_notes WHERE id=?", [$cnId])['tally_ref'], $ref,
    'the credit note is stamped with the batch reference');
$rows2 = tally_rows('2026-07-01', '2026-07-31', 'CREDITNOTE');
$still = false; foreach ($rows2 as $r) if ((int)$r['id'] === $cnId) $still = true;
t_ok(!$still, 'an exported credit note is not offered again');

// Undo brings it back and clears the stamp.
tally_undo($ref);
t_eq((string)ops_one("SELECT tally_ref FROM credit_notes WHERE id=?", [$cnId])['tally_ref'], '',
    'undo clears the credit-note stamp');
$rows3 = tally_rows('2026-07-01', '2026-07-31', 'CREDITNOTE');
$back = false; foreach ($rows3 as $r) if ((int)$r['id'] === $cnId) $back = true;
t_ok($back, 'after undo the credit note is offered again');

// A credit note whose original invoice is missing is refused, not mis-exported.
db()->prepare("INSERT INTO credit_notes (cn_no, invoice_id, partner_id, partner_name, cn_date, reason,
    subtotal, total, status, created_at) VALUES (?,?,?,?,?,?,?,?,'ISSUED',?)")
    ->execute(['CN/26/0003', 999999, $pid, 'Essar Steel', '2026-07-22', 'OTHER', 500, 590, date('c')]);
$rowsX = tally_rows('2026-07-01', '2026-07-31', 'CREDITNOTE', true);
$bad = null; foreach ($rowsX as $r) if ($r['invoice_number'] === 'CN/26/0003') $bad = $r;
t_ok($bad !== null && !$bad['ok'], 'a credit note with no original invoice is blocked, not exported blindly');
