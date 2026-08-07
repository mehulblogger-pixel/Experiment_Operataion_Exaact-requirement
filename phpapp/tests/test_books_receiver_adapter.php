<?php
// MGH Books receiver — the storage/login seam.
// The whole reason for the adapter is that Books can point storage at its own
// tables WITHOUT the risk of moving a single figure — because the arithmetic is
// not in the adapter. This test proves exactly that: it runs the identical money
// scenario through a completely different backend (a throwaway in-memory store)
// and a completely different login handler, and asserts the figures come out the
// same to the paisa. If the calculation engine ever leaked into an adapter, the
// two backends would drift and this would fail.
t_section('MGH Books receiver — storage & login seam');

require_once __DIR__ . '/../../mgh_books_receiver/lib/receiver.php';

// A second, totally independent backend: plain PHP arrays, no database at all.
// It holds no arithmetic — every method is a row get or set.
final class Bkrecv_MemStore implements Bkrecv_Store {
    private array $parties = []; private array $docs = [];
    private int $pseq = 0; private int $dseq = 0;
    public function ensureSchema(): void {}
    public function partyByExt(string $ext): ?array {
        return isset($this->parties[$ext]) ? ['id' => $this->parties[$ext]['id'], 'ref' => $this->parties[$ext]['ref']] : null;
    }
    public function partyUpsert(string $ext, array $d, ?array $existing): string {
        if ($existing) return (string)$this->parties[$ext]['ref'];
        $id = ++$this->pseq; $ref = 'BK-CUST-' . $id;
        $this->parties[$ext] = ['id' => $id, 'ref' => $ref, 'name' => (string)($d['name'] ?? '')];
        return $ref;
    }
    public function docByExt(string $ext): ?array {
        foreach ($this->docs as $r) if ($r['ext_id'] === $ext) return ['id' => $r['id'], 'ref' => $r['ref'], 'kind' => $r['kind']];
        return null;
    }
    public function invoiceInsert(string $ext, array $d, float $total, float $tax, string $irn): string {
        $id = ++$this->dseq; $ref = 'BK-INV-' . $id;
        $this->docs[$id] = ['id' => $id, 'ext_id' => $ext, 'kind' => 'INVOICE', 'ref' => $ref,
            'party_ext' => (string)($d['party_ext'] ?? ''), 'total' => $total, 'tax' => $tax,
            'irn' => $irn, 'status' => 'UNPAID', 'paid' => 0.0, 'outstanding' => $total];
        return $ref;
    }
    public function invoiceHeaderUpdate(int $id, array $d, float $total, float $tax): void {
        if (!isset($this->docs[$id])) return;
        $this->docs[$id]['total'] = $total; $this->docs[$id]['tax'] = $tax;
        $this->docs[$id]['party_ext'] = (string)($d['party_ext'] ?? $this->docs[$id]['party_ext']);
    }
    public function invoiceGet(int $id): ?array {
        if (!isset($this->docs[$id])) return null; $r = $this->docs[$id];
        return ['ext_id' => $r['ext_id'], 'total' => $r['total'], 'paid' => $r['paid'],
            'outstanding' => $r['outstanding'], 'irn' => $r['irn'], 'status' => $r['status']];
    }
    public function invoiceByExt(string $ext): ?array {
        foreach ($this->docs as $r) if ($r['kind'] === 'INVOICE' && $r['ext_id'] === $ext)
            return ['id' => $r['id'], 'irn' => $r['irn'], 'status' => $r['status'], 'paid' => $r['paid'], 'outstanding' => $r['outstanding']];
        return null;
    }
    public function openInvoicesByParty(string $partyExt): array {
        $out = [];
        foreach ($this->docs as $r) if ($r['kind'] === 'INVOICE' && $r['party_ext'] === $partyExt && $r['outstanding'] > 0)
            $out[] = ['id' => $r['id'], 'outstanding' => $r['outstanding']];
        usort($out, fn($a, $b) => $a['id'] <=> $b['id']);
        return $out;
    }
    public function invoicePay(int $id, float $take): void {
        if (!isset($this->docs[$id])) return;
        $this->docs[$id]['paid'] += $take; $this->docs[$id]['outstanding'] -= $take;
    }
    public function invoiceSetOutstanding(int $id, float $out): void { if (isset($this->docs[$id])) $this->docs[$id]['outstanding'] = $out; }
    public function invoiceSetStatus(int $id, string $status): void { if (isset($this->docs[$id])) $this->docs[$id]['status'] = $status; }
    public function simpleUpsert(string $kind, string $ext, array $cols, array $payload, string $refPrefix, ?array $existing): string {
        if ($existing) return (string)$existing['ref'];
        $id = ++$this->dseq; $ref = $refPrefix . $id;
        $this->docs[$id] = ['id' => $id, 'ext_id' => $ext, 'kind' => $kind, 'ref' => $ref,
            'party_ext' => (string)($cols['party_ext'] ?? ''), 'total' => (float)($cols['total'] ?? 0),
            'paid' => 0.0, 'outstanding' => 0.0, 'irn' => '', 'status' => 'RECORDED'];
        return $ref;
    }
}

// Register the alternate backend and an alternate login (Books' own would go
// here). From now on the receiver stores nowhere near a database.
bkrecv_store(new Bkrecv_MemStore());
bkrecv_auth_handler(fn($t) => $t === 'books-native-login');
t_ok(bkrecv_auth('books-native-login'), 'the custom login handler accepts its own token');
t_ok(!bkrecv_auth('round-trip-secret'), 'the custom login handler rejects the old token');

// The SAME money scenario as the PDO round-trip: 11,800 invoice, 5,000 receipt,
// 1,800 credit note. Through arrays instead of SQL, the figures must be identical.
bkrecv_dispatch('set', 'PARTY', ['ext_id' => 'ERP-BP-77', 'name' => 'Torrent']);
[$c, $r] = bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'ERP-INV-77', 'invoice_no' => 'INV/77',
    'date' => '2026-07-01', 'party_ext' => 'ERP-BP-77', 'total' => 11800, 'cgst' => 900, 'sgst' => 900, 'igst' => 0]);
t_ok($c === 200 && strncmp($r['ref'], 'BK-INV-', 7) === 0, 'the invoice imports through the in-memory backend');
$invRef = $r['ref'];

[$c, $st] = bkrecv_dispatch('status', '', [], 'ERP-INV-77');
t_eq((float)$st['status']['outstanding'], 11800.0, 'same backend, same maths: full total outstanding');
t_eq($st['status']['status'], 'UNPAID', 'a fresh invoice is UNPAID on any backend');

bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'ERP-RCP-77', 'party_ext' => 'ERP-BP-77', 'amount' => 5000]);
[$c, $st] = bkrecv_dispatch('status', '', [], 'ERP-INV-77');
t_eq((float)$st['status']['paid'], 5000.0, 'the receipt applies the same as under SQL');
t_eq((float)$st['status']['outstanding'], 6800.0, 'the outstanding drops the same');
t_eq($st['status']['status'], 'PART_PAID', 'part-paid on any backend');

bkrecv_dispatch('import', 'CREDITNOTE', ['ext_id' => 'ERP-CN-77', 'against_inv' => 'ERP-INV-77', 'amount' => 1800]);
[$c, $st] = bkrecv_dispatch('status', '', [], 'ERP-INV-77');
t_eq((float)$st['status']['outstanding'], 5000.0, 'the credit note reduces it the same — figures never move with the backend');

// Idempotency still holds through the seam: re-imports do not double-apply.
[$c, $r2] = bkrecv_dispatch('import', 'INVOICE', ['ext_id' => 'ERP-INV-77', 'invoice_no' => 'INV/77',
    'date' => '2026-07-01', 'party_ext' => 'ERP-BP-77', 'total' => 11800, 'cgst' => 900, 'sgst' => 900, 'igst' => 0]);
t_eq($r2['ref'], $invRef, 're-importing the invoice returns the same ref (idempotent through the adapter)');
bkrecv_dispatch('import', 'RECEIPT', ['ext_id' => 'ERP-RCP-77', 'party_ext' => 'ERP-BP-77', 'amount' => 5000]);
[$c, $st] = bkrecv_dispatch('status', '', [], 'ERP-INV-77');
t_eq((float)$st['status']['outstanding'], 5000.0, 'the same receipt is not applied twice — outstanding unchanged');

// Restore the reference backend + default login so later tests are unaffected.
bkrecv_store(new Bkrecv_PdoStore(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION])));
bkrecv_auth_handler('bkrecv_default_auth');
