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

> This reference receiver is deliberately small — it proves and documents the
> wire format. In a full Books deployment you would point `receiver.php`'s
> storage and auth at Books' own tables and login instead; `api.php` stays as
> is because the contract does not change.
