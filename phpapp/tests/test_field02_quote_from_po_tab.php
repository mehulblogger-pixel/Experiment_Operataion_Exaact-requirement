<?php
// Field-finding #2 — when a client has no quotation, the PO tab (Client Registration → Purchase
// Orders) gave no way to raise one: you could only pick an existing quote or fill the PO by hand.
// Now the tab offers "+ New quotation" which opens the quote form in-page (the #9 embed popup),
// pre-set to this client; on save the page refreshes and the new quote is in the list to pick.
t_section('Field #2 — raise a quotation from the Purchase Orders tab');

// A brand-new DRAFT quote for the client is offered in the PO tab's list (so the one just raised
// in the popup appears there after the reload).
$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('POQuote Co','POQuote Co',1,'ACTIVE')")->execute();
$cid = (int)$pdo->lastInsertId();
t_eq([], quotations_for_po($cid), 'the client starts with no quotation for the PO tab');
$pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, subject, total_amount, status, created_at)
               VALUES ('Q-PO-1', 0, 1, ?, 'POQuote Co', 'Weld inspection', 50000, 'DRAFT', ?)")->execute([$cid, date('c')]);
$rows = quotations_for_po($cid);
t_eq(1, count($rows), 'a freshly-raised DRAFT quotation appears in the PO tab list');
t_eq('Q-PO-1', $rows[0]['quote_no'], 'it is the quote just raised');

// The quote form pre-selects the client passed as ?client=<id> (so the order can name it).
$crm = file_get_contents(__DIR__ . '/../lib/crm.php');
t_ok(strpos($crm, "(\$_GET['client'] ?? '') !== ''") !== false
     && strpos($crm, "\$preClient = ['client_id' => \$pc]") !== false,
     'quote-new?client=<id> pre-selects that client (validated as a real client)');
t_ok(strpos($crm, "'preClient' => \$preClient") !== false, 'the prefill is passed to the quote form');
$qf = file_get_contents(__DIR__ . '/../views/ops/crm/quote_form.php');
t_ok(strpos($qf, '$preClient = $preClient ?? null') !== false && strpos($qf, 'use ($q, $preInq, $preLead, $preClient)') !== false,
     'the quote form applies the client prefill');

// The PO tab shows a guarded "+ New quotation" trigger that opens the quote form in-page,
// with a plain-href fallback for JS-off.
$det = file_get_contents(__DIR__ . '/../views/detail.php');
t_ok(strpos($det, 'data-embed="/quote-new?client=<?= $id ?>"') !== false,
     'the PO tab opens the quote form in an in-page popup, pre-set to this client');
t_ok(strpos($det, 'href="/quote-new?client=<?= $id ?>"') !== false,
     'the trigger keeps a plain href fallback (works with JS off)');
t_ok(strpos($det, "can('crm.quote.create') || can('mod.quotes.edit') || is_master()") !== false,
     'the "+ New quotation" affordance is gated to who may create quotations');

// Clean up (shared DB).
$pdo->prepare("DELETE FROM quotations WHERE client_id=?")->execute([$cid]);
$pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$cid]);
