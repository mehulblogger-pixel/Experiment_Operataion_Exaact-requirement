<?php
// Field-finding #4 — a deal opened from a lead kept saying "still to quote" and offered
// "Generate quotation" even though a quotation had already been raised off that same lead
// (its own activity log showed it submitted/won). Root cause: opp_quotes() listed ONLY the
// quotes with an explicit opportunity_quotes link — and that link is written only when the
// quote is ACCEPTED — so a submitted lead-quote was invisible on the deal. quote_linked_deal_id()
// already treats a lead-quote as belonging to the deal (via the shared lead_id); this makes the
// deal's own quote list agree with it. One definition of "which quotes are on this deal", used
// by the list, the delete-guard, the "Generate quotation" gate and the order-of-record.
t_section('Field #4 — a quotation raised off the lead shows on the deal (not "still to quote")');

opp_migrate();
$pdo = db();

// A client, a lead, and a deal opened FROM that lead (lead_id carried onto the opportunity).
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('LeadQuote Client','LeadQuote Client',1,'ACTIVE')")->execute();
$pid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO leads (company_name, requirement, status, created_at) VALUES ('LeadQuote Client','Boiler inspection enquiry','QUALIFIED',?)")->execute([date('c')]);
$lid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO opportunities (ref, name, lead_id, partner_id, partner_name, value, status, created_at) VALUES ('OPP-LQ1','Boiler inspection deal',?,?, 'LeadQuote Client', 90000, 'OPEN', ?)")->execute([$lid, $pid, date('c')]);
$oid = (int)$pdo->lastInsertId();

// The bug precondition: the deal has NO explicit quote link yet.
t_eq(0, (int)ops_val("SELECT COUNT(*) FROM opportunity_quotes WHERE opportunity_id=?", [$oid]),
     'no explicit opportunity_quotes link exists (the quote was raised off the lead, not the deal)');

// A quotation raised off the SAME lead, merely SUBMITTED (never accepted → never auto-linked).
$pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, lead_id, client_id, client_name, subject, total_amount, status, created_at)
               VALUES ('Q-LQ-1', 0, 1, ?, ?, 'LeadQuote Client', 'Boiler inspection', 90000, 'SUBMITTED', ?)")
    ->execute([$lid, $pid, date('c')]);
$qid = (int)$pdo->lastInsertId();

// THE FIX: the deal now lists that lead-quote, so count($quotes) > 0.
$quotes = opp_quotes($oid);
t_eq(1, count($quotes), 'the submitted lead-quote now appears on the deal');
t_eq($qid, (int)$quotes[0]['id'], 'it is exactly the quote raised off the lead');
t_ok(!empty($quotes), 'count($quotes) is non-zero — so "Generate quotation" greys out and the deal is part of the record');

// It flows through to the order-of-record resolver (raise-order carries THIS quote, not a stale estimate).
$oq = opp_order_quote($oid);
t_ok($oq && (int)$oq['id'] === $qid, 'the order-of-record resolver finds the lead-quote');

// The "attach existing quotation" dropdown must NOT offer a quote the deal already lists.
$attachable = ops_all(
    "SELECT id FROM quotations
     WHERE client_id=? AND id NOT IN (SELECT quotation_id FROM opportunity_quotes WHERE opportunity_id=?)
       AND (CAST(? AS INTEGER) = 0 OR COALESCE(lead_id,0) <> CAST(? AS INTEGER))
     ORDER BY id DESC", [$pid, $oid, $lid, $lid]);
t_ok(!in_array($qid, array_map(fn($r) => (int)$r['id'], $attachable), true),
     'the lead-quote is not also offered under "attach an existing quotation"');

// Guard: the fix keys on the deal's OWN lead. A deal with no lead does not slurp unrelated
// same-client quotes — only an explicit link counts there.
$pdo->prepare("INSERT INTO opportunities (ref, name, partner_id, partner_name, value, status, created_at) VALUES ('OPP-LQ2','Leadless deal',?, 'LeadQuote Client', 5000, 'OPEN', ?)")->execute([$pid, date('c')]);
$oid2 = (int)$pdo->lastInsertId();
t_eq(0, count(opp_quotes($oid2)),
     'a deal with no lead does not pull in the client\'s other quotes (no false "quote exists")');
