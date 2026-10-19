<?php
/**
 * EXAACT audit — does the MGH Books receiver ever LOSE money?
 *
 * The receiver is the single source of truth for paid/outstanding: the ERP's
 * booksbridge.php says "the ERP never recomputes any of it". So if the receiver
 * drops a figure, nobody else is holding it.
 *
 * Driven through the real dispatcher on an in-memory database, exactly as the
 * ERP's own round-trip test does (tests/test_books_receiver.php). Four cases a
 * live system WILL produce:
 *
 *   1. overpayment      — a receipt larger than the party's open invoices
 *   2. receipt first    — the receipt lands before its invoice (the outbox
 *                         drain continues past a failed invoice push, so a
 *                         transient Books error makes this ordinary)
 *   3. credit note      — larger than the invoice's outstanding
 *   4. edited receipt   — the same ext_id re-imported with a changed amount
 *                         (booksbridge re-queues a record whose payload hash
 *                         changed, so an edit IS re-pushed)
 *
 * Read-only against an in-memory database.
 *   php docs/audit-2026-10/evidence/books-receiver-money.php
 */
require __DIR__ . '/../../../mgh_books_receiver/lib/receiver.php';
bkrecv_db(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));

$pdo = bkrecv_db();
$sumInv = fn($party) => (float)$pdo->query("SELECT COALESCE(SUM(paid),0) FROM bk_documents WHERE kind='INVOICE' AND party_ext='$party'")->fetchColumn();
$sumRcp = fn($party) => (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bk_documents WHERE kind='RECEIPT' AND party_ext='$party'")->fetchColumn();
$inv    = fn($ext) => bkrecv_status($ext)['status'] ?? null;
$onAcct = fn($party) => (float)($pdo->query("SELECT COALESCE(on_account,0) FROM bk_parties WHERE ext_id='$party'")->fetchColumn() ?: 0);
$line   = fn($l, $v) => printf("  %-58s %s\n", $l, $v);
$findings = [];

echo "MGH BOOKS RECEIVER — WHERE DOES THE MONEY GO?\n" . str_repeat('=', 78) . "\n";

// ---- 1. overpayment -------------------------------------------------------
echo "\n1. A customer pays MORE than they owe\n";
bkrecv_dispatch('set', 'PARTY', ['ext_id' => 'P1', 'name' => 'Acme']);
bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'INV-1', 'party_ext' => 'P1', 'total' => 10000]);
bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'RCP-1', 'party_ext' => 'P1', 'amount' => 15000]);
$line('receipts recorded for P1',  number_format($sumRcp('P1'), 2));
$line('money applied to P1 invoices', number_format($sumInv('P1'), 2));
$lost1 = $sumRcp('P1') - $sumInv('P1') - $onAcct('P1');
$line('held on account for P1', number_format($onAcct('P1'), 2));
$line('unaccounted for', number_format($lost1, 2) . ($lost1 > 0.005 ? '   <== LOST' : '   (nothing lost)'));
if ($lost1 > 0.005) $findings[] = sprintf('overpayment: %s received, %s applied, %s vanished', $sumRcp('P1'), $sumInv('P1'), $lost1);

// ---- 2. receipt before invoice -------------------------------------------
echo "\n2. The receipt arrives BEFORE its invoice\n";
bkrecv_dispatch('set', 'PARTY', ['ext_id' => 'P2', 'name' => 'Beta']);
bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'RCP-2', 'party_ext' => 'P2', 'amount' => 8000]);
bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'INV-2', 'party_ext' => 'P2', 'total' => 8000]);
$s = $inv('INV-2');
$line('receipt of 8,000 recorded', number_format($sumRcp('P2'), 2));
$line('invoice INV-2 status', $s['status'] . ' — paid ' . number_format($s['paid'], 2) . ', outstanding ' . number_format($s['outstanding'], 2));
if ($s['status'] !== 'PAID') $findings[] = 'receipt-first: a fully paid invoice reports ' . $s['status'] . ' with ' . $s['outstanding'] . ' outstanding';

// ---- 3. credit note larger than the outstanding ---------------------------
echo "\n3. A credit note LARGER than what is outstanding\n";
bkrecv_dispatch('set', 'PARTY', ['ext_id' => 'P3', 'name' => 'Gamma']);
bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'INV-3', 'party_ext' => 'P3', 'total' => 2000]);
bkrecv_dispatch('import', 'CREDITNOTE', ['ext_id' => 'CN-3', 'party_ext' => 'P3', 'against_inv' => 'INV-3', 'amount' => 5000]);
$s = $inv('INV-3');
$cn = (float)$pdo->query("SELECT total FROM bk_documents WHERE ext_id='CN-3'")->fetchColumn();
$line('credit note recorded', number_format($cn, 2));
$line('INV-3 outstanding after it', number_format($s['outstanding'], 2));
$line('credit applied', number_format(2000 - $s['outstanding'], 2));
$lost3 = $cn - (2000 - $s['outstanding']) - $onAcct('P3');
$line('excess held on account for P3', number_format($onAcct('P3'), 2));
$line('unaccounted for', number_format($lost3, 2) . ($lost3 > 0.005 ? '   <== LOST' : '   (nothing lost)'));
if ($lost3 > 0.005) $findings[] = sprintf('credit note: %s issued, %s applied, %s vanished', $cn, 2000 - $s['outstanding'], $lost3);

// ---- 4. an edited receipt re-imported with a new amount -------------------
echo "\n4. A receipt is EDITED in the ERP and re-pushed with a new amount\n";
bkrecv_dispatch('set', 'PARTY', ['ext_id' => 'P4', 'name' => 'Delta']);
bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'INV-4', 'party_ext' => 'P4', 'total' => 10000]);
bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'RCP-4', 'party_ext' => 'P4', 'amount' => 3000]);
bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'RCP-4', 'party_ext' => 'P4', 'amount' => 7000]);   // corrected
$s = $inv('INV-4');
$line('receipt RCP-4 now says', number_format($sumRcp('P4'), 2));
$line('INV-4 shows paid', number_format($s['paid'], 2) . ', outstanding ' . number_format($s['outstanding'], 2));
if (abs($sumRcp('P4') - $s['paid']) > 0.005)
    $findings[] = sprintf('edited receipt (LATENT — no ERP path re-pushes an edited receipt today): the receipt says %s but the invoice shows %s paid', $sumRcp('P4'), $s['paid']);

// ---- verdict --------------------------------------------------------------
echo "\n" . str_repeat('=', 78) . "\n";
if (!$findings) echo "No money was lost in any case.\n";
else { echo "FINDINGS (" . count($findings) . "):\n"; foreach ($findings as $f) echo "  * $f\n"; }
echo "\nWhat is NOT a finding: the receiver is correctly idempotent — re-sending an\n";
echo "unchanged invoice, receipt or credit note creates nothing twice. And every\n";
echo "figure above is reachable only through the shared token. The point is that\n";
echo "Books is the ONLY ledger of paid/outstanding, so a figure it drops is gone.\n";
