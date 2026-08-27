<?php
// Field-finding #8 — after a quotation raised off a lead is submitted AND won, the lead stayed
// at "Qualified/Open" (status never became CONVERTED) and the deal it came from never showed the
// lead as converted. Root cause: the quote-acceptance sync sent the outcome to the DEAL (won) and
// the CLIENT (landed on the master) but never back to the LEAD. A lead that produced a won quote
// directly (without first being converted into an inquiry) therefore sat in the funnel forever.
// Fix: on acceptance, convert that lead too — idempotently, without a duplicate customer/inquiry.
t_section('Field #8 — a lead is converted when its quotation is won');

opp_migrate();
$pdo = db();

// A client already on the master (by acceptance time quote_land_on_client has created/linked it),
// a lead still OPEN, and a deal opened from that lead with no customer yet.
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('WinConv Client','WinConv Client',1,'ACTIVE')")->execute();
$pid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO leads (ref, company_name, requirement, status, created_at) VALUES ('L-WC-1','WinConv Client','Tank inspection','OPEN',?)")->execute([date('c')]);
$lid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO opportunities (ref, name, lead_id, value, status, created_at) VALUES ('OPP-WC1','Tank inspection deal',?, 60000, 'OPEN', ?)")->execute([$lid, date('c')]);
$oid = (int)$pdo->lastInsertId();

// Precondition — the lead is not converted, and the deal has no customer.
t_eq('OPEN', (string)ops_val("SELECT status FROM leads WHERE id=?", [$lid]), 'the lead starts OPEN (not converted)');
t_eq(0, (int)ops_val("SELECT COALESCE(partner_id,0) FROM opportunities WHERE id=?", [$oid]), 'the deal starts with no customer');

// THE FIX — winning the quote converts the lead.
$ok = lead_convert_on_quote_win($lid, $pid, null);
t_ok($ok === true, 'the conversion runs');
$l = ops_one("SELECT status, converted_partner_id, converted_at, partner_id FROM leads WHERE id=?", [$lid]);
t_eq('CONVERTED', $l['status'], 'the lead is now CONVERTED, not left at Qualified/Open');
t_eq($pid, (int)$l['converted_partner_id'], 'it records which customer it converted into');
t_ok(trim((string)$l['converted_at']) !== '', 'it stamps when it converted');
t_eq($pid, (int)$l['partner_id'], 'the customer is set on the lead');

// The deal from this lead is shown its customer too (so "Convert the lead" stops nagging).
t_eq($pid, (int)ops_val("SELECT partner_id FROM opportunities WHERE id=?", [$oid]),
     'the deal that came from the lead is filled with the customer');

// Idempotent — a second win does not re-convert, and never errors.
t_ok(lead_convert_on_quote_win($lid, $pid, null) === false, 'an already-converted lead is left alone (idempotent)');

// A LOST lead is settled — winning a stray quote must not silently un-lose it.
$pdo->prepare("INSERT INTO leads (ref, company_name, status, created_at) VALUES ('L-WC-2','Lost Co','LOST',?)")->execute([date('c')]);
$lid2 = (int)$pdo->lastInsertId();
t_ok(lead_convert_on_quote_win($lid2, $pid, null) === false, 'a LOST lead is not converted');
t_eq('LOST', (string)ops_val("SELECT status FROM leads WHERE id=?", [$lid2]), 'the LOST lead is untouched');

// Guards on bad input.
t_ok(lead_convert_on_quote_win(0, $pid, null) === false, 'no lead → no-op');
t_ok(lead_convert_on_quote_win($lid, 0, null) === false, 'no customer → no-op');

// Wiring — the quote-acceptance sync calls the converter with the landed client and the quote's lead.
$crm = file_get_contents(__DIR__ . '/../lib/crm.php');
$acc = strpos($crm, "elseif (\$to === 'ACCEPTED')");
t_ok($acc !== false, 'the ACCEPTED branch exists');
$blk = substr($crm, $acc, 6000);
t_ok(strpos($blk, 'lead_convert_on_quote_win(') !== false
     && strpos($blk, "\$res['client_id']") !== false
     && strpos($blk, "\$q['lead_id']") !== false,
     'accepting a quotation converts its lead, using the just-landed customer');
