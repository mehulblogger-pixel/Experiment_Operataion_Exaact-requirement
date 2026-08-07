# MGH Books — inbound receiver (the ERP connector's server side)

This is the **server half** of the ERP → Books data flow. The ERP's
`phpapp/lib/booksbridge.php` pushes billable records here and reads paid/
outstanding status back. This folder is what the **MGH Books** application
serves at its API address — it is *not* part of the ERP app.

Zero dependencies, one PDO (SQLite for dev, MySQL for prod), idempotent by the
ERP's external id so nothing is ever created twice.

## Files

| File | What it is |
|------|------------|
| `api.php` | The HTTP endpoint. Verifies the shared token, reads the request, dispatches, replies in JSON. |
| `lib/receiver.php` | All the logic (storage, auth, the three verbs). `api.php` and the ERP's test both call the same dispatcher. |
| `data/` | Where the dev SQLite database lands (created on first request; git-ignored). |

## The contract

The ERP connector calls exactly three things:

```
POST /api.php        { "action":"set",    "kind":"PARTY",      "data":{…} }   → { ok:true, ref:"BK-CUST-1" }
POST /api.php        { "action":"import", "kind":"INVOICE",    "data":{…} }   → { ok:true, ref:"BK-INV-1" }
                     …also QUOTE, RECEIPT, CREDITNOTE
GET  /api.php?action=status&ext_id=ERP-INV-42                                → { ok:true, status:{ irn, status, paid, outstanding } }
```

Every request carries `Authorization: Bearer <token>`. The token is the one
value that must match on both sides.

The money truth lives here: an invoice arrives `UNPAID` with its full total
outstanding; a `RECEIPT` reduces the party's open invoices oldest-first; a
`CREDITNOTE` reduces the invoice it names; and `status` reports back the paid,
outstanding and IRN the ERP then shows on its invoice screen. The ERP never
recomputes any of it, so the two can never disagree.

## Install (cPanel File Manager)

1. Upload this whole `mgh_books_receiver` folder into the MGH Books web root
   (or wherever Books serves its API from). The ERP's **Books API address** is
   the URL of the folder — e.g. `https://books.yourcompany.com` (the connector
   appends `/api.php`).
2. Set two environment values on the Books server (in its own config, or as
   host environment variables):
   - `BOOKS_API_TOKEN` — the shared secret. Paste the **same** value into the
     ERP at **System settings → 📗 MGH Books connection → API token**.
   - For production: `BOOKS_DB_DRIVER=mysql` plus `BOOKS_DB_HOST`,
     `BOOKS_DB_NAME`, `BOOKS_DB_USER`, `BOOKS_DB_PASS`. Left unset, it uses a
     local SQLite file under `data/` — fine for a pilot.
3. In the ERP, turn **Books connected** on and clear **Dry run**. Issue a test
   invoice; it appears here, and its paid/outstanding flows back.

## Wiring it to Books' own tables and login (the seam)

The reference stores into its own `bk_parties` / `bk_documents` tables and checks
a shared token. A real Books deployment points both at its own tables and its own
login — and it does so **without any risk to the numbers**, because the money
maths does not live in the storage layer.

Two seams, nothing else to touch:

**1. Storage.** Implement the `Bkrecv_Store` interface (13 small methods, each a
plain row read or write — *no arithmetic*) against Books' real tables, then
register it once at the top of `api.php`:

```php
require __DIR__ . '/lib/receiver.php';
require __DIR__ . '/lib/books_store.php';      // your class BooksStore implements Bkrecv_Store
bkrecv_store(new BooksStore($booksPdo));
```

**2. Login.** Hand the receiver Books' own check:

```php
bkrecv_auth_handler(fn($token) => books_verify_api_key($token));   // your function
```

That is the whole change. `api.php` and the entire calculation engine in
`receiver.php` stay untouched.

### Why this is safe

The arithmetic — a receipt paying invoices oldest-first, a credit note reducing
one, the paid/part/unpaid label, the outstanding figure — is written exactly once
in `receiver.php` and only ever calls the store to read and write rows. An adapter
cannot compute a figure, so a wrong adapter can misfile or lose a record, but it
**cannot make Books disagree with the ERP**. The test suite proves this: the same
money scenario is run through the SQL store *and* a completely different in-memory
store, and both return the identical figures to the paisa
(`tests/test_books_receiver_adapter.php`). If any maths ever leaked into a store,
those two backends would diverge and the test would fail.

### The 13 methods

| Method | Does |
|--------|------|
| `partyByExt` / `partyUpsert` | find / create-or-update a customer by ERP ext id |
| `docByExt` | find any document by ERP ext id (the idempotency check) |
| `invoiceInsert` / `invoiceHeaderUpdate` | create / refresh an invoice header |
| `invoiceGet` / `invoiceByExt` | read an invoice's figures by id / by ext id |
| `openInvoicesByParty` | a party's unpaid invoices, oldest first |
| `invoicePay` | add a payment to one invoice |
| `invoiceSetOutstanding` / `invoiceSetStatus` | set the two derived fields |
| `simpleUpsert` | store a quote / receipt / credit note and reference it |
| `ensureSchema` | create/verify tables once (a no-op if Books already has them) |

> The reference `Bkrecv_PdoStore` at the bottom of `receiver.php` is a complete
> worked example of all 13 — copy its shape, point the SQL at Books' tables.
