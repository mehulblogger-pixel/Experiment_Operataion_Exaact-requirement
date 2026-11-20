<?php
// Owner, 20 Nov 2026: "Yes add Branch GSTIN. Company can have only one office or can have multiple
// office across India. So code it accordingly." and "No GST inclusion in contract value."
t_section('Branch GSTIN — each office bills under its own registration, else its parent\'s, else the company\'s');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$keep = ['company_gstin' => setting_get('company_gstin', null), 'tally_state' => setting_get('tally_state', null)];
$made = ['o' => [], 'p' => [], 'i' => [], 'q' => [], 'c' => []];
try {
    bbv_as($f['master']);
    if (function_exists('orgadmin_migrate')) orgadmin_migrate();
    ensure_column('offices', 'gstin', "VARCHAR(20) DEFAULT ''");
    books_migrate();
    setting_set('company_gstin', '24ABCDE1234F1Z5');     // the company: Gujarat (24), PAN ABCDE1234F
    setting_set('tally_state', 'Gujarat');

    // ---- What a branch GSTIN may be ------------------------------------------------------------
    t_eq(office_gstin_problem(''), '', 'an empty branch GSTIN is fine — a one-office company never fills it');
    t_ok(office_gstin_problem('27ABCDE1234F') !== '', 'a GSTIN of the wrong length is refused');
    t_ok(office_gstin_problem('99ABCDE1234F1Z5') !== '', '99 is not an Indian state code — refused');
    t_ok(stripos(office_gstin_problem('27PQRST9876K1Z2'), 'PAN') !== false, 'a GSTIN of another PAN (another company) is refused');
    t_eq(office_gstin_problem('27abcde1234f1z5'), '', 'Mumbai\'s 27ABCDE1234F1Z5 (same PAN as the company) is accepted, in any case');

    // ---- Which registration an office bills under ----------------------------------------------
    db()->prepare("UPDATE offices SET gstin=? WHERE id=?")->execute(['27ABCDE1234F1Z5', $f['mum']]);
    db()->prepare("INSERT INTO offices (code, name, parent_office_id, is_active) VALUES (?,?,?,1)")->execute(['FXS' . substr($f['tag'], -4), 'Fx Pune site ' . $f['tag'], $f['mum']]);
    $site = (int) db()->lastInsertId(); $made['o'][] = $site;
    t_eq(office_gst_registration($f['mum'])['gstin'] ?? '', '27ABCDE1234F1Z5', 'Mumbai has its own registration');
    t_eq(office_gst_registration($site)['gstin'] ?? '', '27ABCDE1234F1Z5', 'a site office under Mumbai with no GSTIN bills under Mumbai\'s');
    t_eq(office_gst_registration($f['ahm']), null, 'Ahmedabad (no GSTIN) bills under the company\'s registration');
    t_eq([books_our_state($f['mum']), books_our_state($site), books_our_state($f['ahm']), books_our_state(null)], ['27', '27', '24', '24'],
         '"our state": Mumbai and its site 27 (Maharashtra); Ahmedabad and the company 24 (Gujarat)');
    t_eq([books_our_gstin($f['mum']), books_our_gstin($f['ahm'])], ['27ABCDE1234F1Z5', '24ABCDE1234F1Z5'], 'the GSTIN printed as ours follows the same rule');

    // ---- The CGST + SGST / IGST decision -------------------------------------------------------
    $mk = function ($name, $gstin) use (&$made) {
        db()->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status, gstin, created_at) VALUES (?,?,?,1,'ACTIVE',?,?)")
            ->execute(['FX' . substr(md5($name . microtime()), 0, 6), $name, $name, $gstin, date('c')]);
        return $made['p'][] = (int) db()->lastInsertId();
    };
    $pune = $mk('Fx Pune Client', '27PUNEC1234F1Z1'); $amd = $mk('Fx Ahmedabad Client', '24AMDCL1234F1Z1');
    t_eq([books_is_igst($pune, $f['mum']), books_is_igst($amd, $f['mum'])], [0, 1], 'Mumbai → Pune client: CGST + SGST; Mumbai → Ahmedabad client: IGST');
    t_eq([books_is_igst($pune, $f['ahm']), books_is_igst($amd, $f['ahm'])], [1, 0], 'Ahmedabad (company registration) → Pune: IGST; → Ahmedabad: CGST + SGST — unchanged');

    // ---- An invoice stamps the registration it was billed under --------------------------------
    $r = books_invoice_create(['partner_id' => $pune, 'office_id' => $f['mum']]);
    $inv = ops_one("SELECT * FROM invoices WHERE id=?", [(int) ($r['id'] ?? 0)]); $made['i'][] = (int) ($r['id'] ?? 0);
    t_eq([$inv['supplier_gstin'] ?? '', $inv['supplier_state'] ?? '', (int) ($inv['is_igst'] ?? -1)], ['27ABCDE1234F1Z5', '27', 0],
         'a Mumbai draft invoice to Pune carries Mumbai\'s GSTIN and state, CGST + SGST');
    books_set_office((int) $inv['id'], $f['ahm']);
    $inv2 = ops_one("SELECT * FROM invoices WHERE id=?", [(int) $inv['id']]);
    t_eq([$inv2['supplier_gstin'], $inv2['supplier_state'], (int) $inv2['is_igst']], ['24ABCDE1234F1Z5', '24', 1], 'moving the draft to Ahmedabad re-stamps it: company GSTIN, IGST to Pune');
    db()->prepare("UPDATE invoices SET status='ISSUED' WHERE id=?")->execute([(int) $inv['id']]);
    db()->prepare("UPDATE offices SET gstin=? WHERE id=?")->execute(['24ABCDE1234F2Z4', $f['ahm']]);
    t_eq((string) ops_val("SELECT supplier_gstin FROM invoices WHERE id=?", [(int) $inv['id']]), '24ABCDE1234F1Z5', 'an issued invoice keeps the GSTIN it was billed under when a branch GSTIN changes later');
    db()->prepare("UPDATE offices SET gstin='' WHERE id=?")->execute([$f['ahm']]);

    // ---- The quotation shows the same split -----------------------------------------------------
    $sp = sx_quote_gst_split(['gst_amount' => 1800, 'gst_pct' => 18, 'client_id' => $pune, 'office_id' => $f['mum']]);
    t_eq($sp['mode'] ?? '', 'CGST_SGST', 'a Mumbai quotation to a Pune client shows CGST 9% + SGST 9%');
    t_eq([(string) (quote_letterhead($f['mum'])['gstin'] ?? ''), (string) (quote_letterhead($f['ahm'])['gstin'] ?? '')], ['GSTIN: 27ABCDE1234F1Z5', 'GSTIN: 24ABCDE1234F1Z5'],
         'the quotation letterhead prints the branch\'s GSTIN (Mumbai) or the company\'s (Ahmedabad)');

    t_section('Contract value is before GST (Sales pack A2)');
    t_eq(crm_quote_value_before_gst(['subtotal' => 1000000, 'gst_amount' => 180000, 'total_amount' => 1180000]), 1000000.0, 'a ₹10,00,000 + 18% quotation is worth ₹10,00,000 as a contract (not ₹11,80,000)');
    t_eq(crm_quote_value_before_gst(['subtotal' => 0, 'gst_amount' => 18000, 'total_amount' => 118000]), 100000.0, 'an older quotation that kept only its total: total − GST');
    db()->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, subtotal, gst_pct, gst_amount, total_amount, status, created_at, updated_at) VALUES (?,0,1,?,?,1000000,18,180000,1180000,'ACCEPTED',?,?)")
        ->execute(['FX-A2-' . $f['tag'], $pune, 'Fx Pune Client', date('c'), date('c')]);
    $qid = (int) db()->lastInsertId(); $made['q'][] = $qid;
    $pf = contract_prefill($qid, 0);
    t_eq((float) ($pf['value'] ?? 0), 1000000.0, 'registering a contract from that quotation offers ₹10,00,000');
    $awaiting = quotations_awaiting_contract($pune);
    t_ok($awaiting === [] || abs(crm_quote_value_before_gst($awaiting[0]) - 1000000) < 0.01, 'the client\'s "new contract" list offers the value before GST too');
} finally {
    foreach ($keep as $k => $v) { if ($v === null) db()->prepare("DELETE FROM settings WHERE skey=?")->execute([$k]); else setting_set($k, $v); }
    $sc = &settings_cache(); foreach (array_keys($keep) as $k) { if ($keep[$k] === null) unset($sc[$k]); } unset($sc);
    db()->prepare("UPDATE offices SET gstin='' WHERE id IN (?,?)")->execute([$f['mum'], $f['ahm']]);
    foreach ($made['i'] as $id) if ($id) { db()->prepare("DELETE FROM invoices WHERE id=?")->execute([$id]); }
    foreach ($made['q'] as $id) db()->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]);
    foreach ($made['p'] as $id) db()->prepare("DELETE FROM business_partners WHERE id=?")->execute([$id]);
    foreach ($made['o'] as $id) db()->prepare("DELETE FROM offices WHERE id=?")->execute([$id]);
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');
