<?php
// P1 — who can move a quotation to ACCEPTED, from which status, and is a repeat
// acceptance idempotent? Also: approval skipped on the way to SENT.
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true);
$L = [['Third-party inspection', 'TPI', 10, 'MANDAY', 8000], ['Final inspection visit', 'TPI', 4, 'VISIT', 5000]];
// Books bridge in DRY RUN so the outbox is exercised with no network at all.
setting_set('books_connected', '1'); setting_set('books_dryrun', '1');
echo "books_connected=", var_export(books_connected(), true), " dryrun=", var_export(books_dryrun(), true), "\n";

echo "\n[1a] BDM creates a quote (expect DRAFT)\n";
[$q1, $o] = mkquote($I['business_dev_manager'], ['client_id' => $I['p_active'], 'office_id' => $I['office']], $L); show('quote-new', $o);
$r = row("SELECT id, quote_no, status, subtotal, gst_amount, total_amount FROM quotations WHERE id=?", [$q1]); echo "  quote: " . json_encode($r) . "\n";

foreach (['finance' => 'FINANCE (mod.quotes.view only)', 'business_director' => 'BUSINESS_DIRECTOR (view only)', 'marketing_executive' => 'MARKETING_EXECUTIVE (create, no send)'] as $k => $lbl) {
    [$qx] = mkquote($I['business_dev_manager'], ['client_id' => $I['p_active'], 'office_id' => $I['office']], $L);
    echo "\n[1b] $lbl moves a DRAFT quote #$qx straight to ACCEPTED\n";
    $o = req($I[$k], 'quote-status', 'POST', ['id' => $qx], ['to' => 'ACCEPTED']); show('quote-status ACCEPTED', $o);
    echo "  status now: " . qstatus($qx) . ", approvals rows: " . (int)val("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=?", [$qx]) . ", sent_at='" . val("SELECT sent_at FROM quotations WHERE id=?", [$qx]) . "'\n";
}

echo "\n[1c] BDM sends a DRAFT quote without any approval (DRAFT -> SENT)\n";
[$q2] = mkquote($I['business_dev_manager'], ['client_id' => $I['p_active'], 'office_id' => $I['office']], $L);
$o = req($I['business_dev_manager'], 'quote-status', 'POST', ['id' => $q2], ['to' => 'SENT']); show('quote-status SENT', $o);
echo "  status now: " . qstatus($q2) . " (approval rules active: " . (int)val("SELECT COUNT(*) FROM quote_approval_rules WHERE active=1") . ")\n";

echo "\n[1d] FINANCE marks the SENT quote ACCEPTED, then a second time\n";
$o = req($I['finance'], 'quote-status', 'POST', ['id' => $q2], ['to' => 'ACCEPTED']); show('ACCEPTED #1', $o);
$ob1 = rows("SELECT kind, local_id, status FROM books_outbox WHERE (kind='QUOTE' AND local_id=?) OR kind='PARTY' ORDER BY id", [$q2]);
echo "  books_outbox after #1: " . json_encode($ob1) . "\n";
$o = req($I['finance'], 'quote-status', 'POST', ['id' => $q2], ['to' => 'ACCEPTED']); show('ACCEPTED #2 (locked quote, non-master)', $o);
$o = req($I['master'], 'quote-status', 'POST', ['id' => $q2], ['to' => 'ACCEPTED']); show('ACCEPTED #3 (master)', $o);
echo "  books_outbox QUOTE rows for #$q2: " . (int)val("SELECT COUNT(*) FROM books_outbox WHERE kind='QUOTE' AND local_id=?", [$q2]) . "\n";
echo "  quote_revisions trail rows for #$q2: " . json_encode(array_column(rows("SELECT summary FROM quote_revisions WHERE quote_id=? ORDER BY id", [$q2]), 'summary')) . "\n";

echo "\n[1e] Revision after SENT: R00 SENT -> revise -> R01; accept BOTH revisions\n";
[$q3] = mkquote($I['business_dev_manager'], ['client_id' => $I['p_active'], 'office_id' => $I['office']], $L);
req($I['business_dev_manager'], 'quote-status', 'POST', ['id' => $q3], ['to' => 'SENT']);
$o = req($I['business_dev_manager'], 'quote-revise', 'POST', ['id' => $q3], ['summary' => 'price revised']); show('quote-revise', $o);
$q3r = (int)val("SELECT id FROM quotations WHERE parent_id=? ORDER BY id DESC LIMIT 1", [$q3]);
db()->prepare("UPDATE quote_lines SET rate=7000, amount=qty*7000 WHERE quote_id=? AND unit='MANDAY'")->execute([$q3r]); crm_quote_recalc($q3r);
req($I['business_dev_manager'], 'quote-status', 'POST', ['id' => $q3r], ['to' => 'SENT']);
$o = req($I['finance'], 'quote-status', 'POST', ['id' => $q3r], ['to' => 'ACCEPTED']); show('accept R01 (current)', $o);
$o = req($I['finance'], 'quote-status', 'POST', ['id' => $q3], ['to' => 'ACCEPTED']); show('accept R00 (superseded, is_current=0)', $o);
foreach (rows("SELECT id, rev, is_current, status, total_amount FROM quotations WHERE id IN (?,?)", [$q3, $q3r]) as $x) echo "  " . json_encode($x) . "\n";
echo "  books_outbox QUOTE payloads for this deal: " . json_encode(rows("SELECT local_id, status, json_extract(payload,'$.quote_no') qno, json_extract(payload,'$.total') total FROM books_outbox WHERE kind='QUOTE' AND local_id IN (?,?)", [$q3, $q3r])) . "\n";
echo "  quotes_awaiting_contract lists: " . json_encode(array_map(fn($r) => $r['id'] . '/R' . $r['rev'], array_values(array_filter(quotes_awaiting_contract(500), fn($r) => in_array((int)$r['id'], [$q3, $q3r], true))))) . "\n";

echo "\n[1f] Drain the outbox in dry run (no network) and re-accept: loop-breaker?\n";
print_r(books_bridge_drain());
$o = req($I['master'], 'quote-status', 'POST', ['id' => $q2], ['to' => 'ACCEPTED']);
echo "  outbox rows QUOTE #$q2 after drain + re-accept (unchanged payload): " . json_encode(rows("SELECT id,status FROM books_outbox WHERE kind='QUOTE' AND local_id=?", [$q2])) . "\n";
db()->prepare("UPDATE quotations SET subject='changed after push' WHERE id=?")->execute([$q2]);
$o = req($I['master'], 'quote-status', 'POST', ['id' => $q2], ['to' => 'ACCEPTED']);
echo "  outbox rows QUOTE #$q2 after payload change + re-accept: " . json_encode(rows("SELECT id,status,json_extract(payload,'$.subject') s FROM books_outbox WHERE kind='QUOTE' AND local_id=?", [$q2])) . "\n";
file_put_contents(__DIR__ . '/p1_ids.json', json_encode(['q1' => $q1, 'q2' => $q2, 'q3' => $q3, 'q3r' => $q3r]));
