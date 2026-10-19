<?php
// ============================================================================
//  MGH Books receiver — money that has nowhere to go is HELD, not dropped.
//
//  Books is the only ledger of paid/outstanding ("the ERP never recomputes any
//  of it"), so a figure it drops is gone. Before this, an overpayment, a
//  receipt arriving before its invoice (ordinary: the outbox drain continues
//  past a failed invoice push), and a credit note larger than its invoice were
//  each silently discarded. They now go ON ACCOUNT for the party and pay the
//  next invoice down; a store that cannot hold an on-account balance gets the
//  unapplied figure back in the reply instead of silence.
//
//  The 13-method Bkrecv_Store contract is untouched: the capability is a
//  separate optional interface, so test_books_receiver_adapter.php's in-memory
//  store still compiles and still passes.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('books receiver — on-account money');

require_once __DIR__ . '/../../mgh_books_receiver/lib/receiver.php';
bkrecv_db(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));
$pdo = bkrecv_db();
$st  = fn($ext) => bkrecv_status($ext)['status'];
$oa  = fn($p) => (float)$pdo->query("SELECT COALESCE(on_account,0) FROM bk_parties WHERE ext_id='$p'")->fetchColumn();

t_ok(bkrecv_holds_on_account(), 'the reference store can hold money on account');

// 1. overpayment
bkrecv_dispatch('set', 'PARTY', ['ext_id' => 'P1', 'name' => 'Acme']);
bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'INV-1', 'party_ext' => 'P1', 'total' => 10000]);
[$code, $r] = bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'RCP-1', 'party_ext' => 'P1', 'amount' => 15000]);
t_eq($code, 200, 'an overpayment is accepted');
t_eq($st('INV-1')['status'], 'PAID', 'the invoice is paid in full');
t_eq($oa('P1'), 5000.0, 'and the 5,000 excess is held on account for the party');
t_eq($st('INV-1')['on_account'] ?? null, 5000.0, 'status reports the on-account balance alongside the invoice');
t_ok(!isset($r['unapplied']), 'nothing is reported as unapplied, because it was held');

// ...and the NEXT invoice is paid from it
bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'INV-1b', 'party_ext' => 'P1', 'total' => 3000]);
t_eq($st('INV-1b')['status'], 'PAID', 'the next invoice for that party is paid from the money on account');
t_eq($oa('P1'), 2000.0, '...leaving 2,000 still on account');

// 2. receipt before invoice
bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'RCP-2', 'party_ext' => 'P2', 'amount' => 8000]);
t_eq($oa('P2'), 8000.0, 'a receipt for a party with no invoice yet (not even a party row yet) is held on account');
bkrecv_dispatch('set', 'PARTY', ['ext_id' => 'P2', 'name' => 'Beta']);
t_eq($oa('P2'), 8000.0, 'the party arriving afterwards does not disturb it');
bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'INV-2', 'party_ext' => 'P2', 'total' => 8000]);
t_eq($st('INV-2')['status'], 'PAID', 'the invoice that arrives after its receipt is PAID, not UNPAID');
t_eq($oa('P2'), 0.0, 'and the on-account balance is consumed');

// 3. credit note larger than the outstanding
bkrecv_dispatch('set', 'PARTY', ['ext_id' => 'P3', 'name' => 'Gamma']);
bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'INV-3', 'party_ext' => 'P3', 'total' => 2000]);
bkrecv_dispatch('import', 'CREDITNOTE', ['ext_id' => 'CN-3', 'party_ext' => 'P3', 'against_inv' => 'INV-3', 'amount' => 5000]);
t_eq($st('INV-3')['outstanding'], 0.0, 'the invoice is cleared');
t_eq($oa('P3'), 3000.0, 'the 3,000 excess credit is held on account');

// 4. the ordinary case still works exactly as before
bkrecv_dispatch('set', 'PARTY', ['ext_id' => 'P5', 'name' => 'Eps']);
bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'INV-5', 'party_ext' => 'P5', 'total' => 11800]);
bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'RCP-5', 'party_ext' => 'P5', 'amount' => 5000]);
t_eq($st('INV-5')['outstanding'], 6800.0, 'a part payment still leaves the right outstanding');
t_eq($st('INV-5')['status'], 'PART_PAID', '...and the right status');
t_eq($oa('P5'), 0.0, '...with nothing on account');
bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'RCP-5', 'party_ext' => 'P5', 'amount' => 5000]);
t_eq($st('INV-5')['outstanding'], 6800.0, 'the same receipt re-sent is still not applied twice');

// 5. a store WITHOUT the capability is told, not silenced
final class Bkrecv_NoAccountStore implements Bkrecv_Store {
    private $inner;
    public function __construct() { $this->inner = new Bkrecv_PdoStore(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION])); }
    public function partyByExt(string $e): ?array { return $this->inner->partyByExt($e); }
    public function partyUpsert(string $e, array $d, ?array $x): string { return $this->inner->partyUpsert($e, $d, $x); }
    public function docByExt(string $e): ?array { return $this->inner->docByExt($e); }
    public function invoiceInsert(string $e, array $d, float $t, float $x, string $i): string { return $this->inner->invoiceInsert($e, $d, $t, $x, $i); }
    public function invoiceHeaderUpdate(int $i, array $d, float $t, float $x): void { $this->inner->invoiceHeaderUpdate($i, $d, $t, $x); }
    public function invoiceGet(int $i): ?array { return $this->inner->invoiceGet($i); }
    public function invoiceByExt(string $e): ?array { return $this->inner->invoiceByExt($e); }
    public function openInvoicesByParty(string $p): array { return $this->inner->openInvoicesByParty($p); }
    public function invoicePay(int $i, float $t): void { $this->inner->invoicePay($i, $t); }
    public function invoiceSetOutstanding(int $i, float $o): void { $this->inner->invoiceSetOutstanding($i, $o); }
    public function invoiceSetStatus(int $i, string $s): void { $this->inner->invoiceSetStatus($i, $s); }
    public function simpleUpsert(string $k, string $e, array $c, array $p, string $r, ?array $x): string { return $this->inner->simpleUpsert($k, $e, $c, $p, $r, $x); }
    public function ensureSchema(): void { $this->inner->ensureSchema(); }
}
bkrecv_store(new Bkrecv_NoAccountStore());
t_ok(!bkrecv_holds_on_account(), 'a plain 13-method adapter still compiles and reports no on-account support');
bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'INV-9', 'party_ext' => 'P9', 'total' => 1000]);
[, $r] = bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'RCP-9', 'party_ext' => 'P9', 'amount' => 1500]);
t_eq($r['unapplied'] ?? null, 500.0, 'on such a store the unapplied 500 is RETURNED in the reply — never silent');
bkrecv_db(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));   // restore the reference store for later tests
