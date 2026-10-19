# 12 — The sibling deployables

**Audited** 19 October 2026 · **Status** licence server and Books receiver done
in full; `mgh-hire` surveyed pending Q-C; `sop/` is documentation only

---

## 1. What ships beside the ERP

| Folder | What it is | Lines | Handles money? | Audited |
|---|---|---|---|---|
| `licence-server/` | Turns a Razorpay payment into a subscription renewal; signs the licence key every installation collects | 893 | **Yes** — payments move expiry dates | **Full** |
| `mgh_books_receiver/` | The server half of ERP → Books; the **single ledger** of what is paid and outstanding | 427 | **Yes** — it *is* the money truth | **Full** |
| `mgh-hire/` | A standalone, white-label recruitment product; own login, own database, shares nothing with the ERP | 2,555 | No | Surveyed (§5) |
| `sop/` | Ten Markdown guides | — | — | Nothing to audit |

Two further folders — `exaact_data/` and `exaact_backups/` — are the ERP's
runtime data and backup roots. Both are **untracked by git** and both carry a
deny-all `.htaccess`. Not a leak; noted because data or backups inside a code
repository would have been one.

**None of the three applications is covered by `phpapp/deploy-check.php`.** They
are uploaded by hand to cPanel per their READMEs, so the fingerprint that
catches a stale file in the ERP catches nothing here. Worth knowing before a
hotfix is believed deployed.

---

## 2. Licence server — well built, with three gaps behind the door

### What is right, and deliberately so

The Razorpay webhook computes an HMAC-SHA256 over the **raw** body, compares it
with `hash_equals`, and does so **before parsing anything** — an unsigned
request never reaches code that can act. Events are allow-listed. The signing
key is an EC P-256 key generated on the box, written `0600`, in a folder the
code works out for itself to be **outside the web root** — and it **refuses to
generate one at all** if that folder would be served (`ls_store_problem`). The
admin password is `password_hash`'d; the session id is regenerated on sign-in;
every action is CSRF-checked; setup locks the moment a password exists. The
config is JSON read with `file_get_contents`, and the comment explaining why —
opcache could hand a webhook the *previous* secret for two seconds after a save
— is the kind of reasoning that only comes from having been bitten.

### GAP-025 (P2) — a replayed payment could extend twice · **FIXED**

Razorpay retries, so one payment arrives twice. The guard was a `SELECT COUNT`
then an `INSERT`, nothing atomic between them, and **no UNIQUE constraint** on
the payment reference. Two overlapping deliveries could both pass the count.
The probe (`evidence/licence-server-probe.php`) showed the schema accepting the
duplicate the guard exists to prevent.

**Fixed:** a partial UNIQUE index on `ledger(reference)` for payment rows, the
ledger row written **first**, and both statements in one transaction.

> **The first version of this fix was worse than no fix.** It added the index
> but left the order as it was — expiry updated, *then* ledger row. The second
> delivery moved the expiry a further year and was then refused a ledger row:
> **a free year with no record of it.** Re-running the probe against the fix
> showed expiry 2028 with one ledger row, which is how it was caught. A guard
> that fires after the thing it guards has already happened is a liability, and
> the test now pins "the expiry moved exactly once".

### GAP-026 (P3) — a payment's term had no ceiling · **FIXED**

`months` is read from the payment's notes with no upper bound and no check
against the amount. **Rs 1 with `notes.months=1200` moved an expiry to the year
2126.** The webhook is signed, so this is a control against a slipped finger in
the Razorpay dashboard, not an attacker. Fixed with `LS_MAX_MONTHS = 60` — any
real sale fits, no plausible typo does. **Q-I** asks whether that is the right
ceiling and whether the amount should be checked against a price per month.

### GAP-027 (P2) — unlimited guesses at the console that mints every licence · **FIXED**

The sign-in had no throttle, and the session was started with PHP's default
cookie flags in **four separate places**. Fixed: five misses pause sign-in for
fifteen minutes, held in the same private config file as the password hash
(nothing new a browser can reach, no table to create); and one `ls_session()`
sets `HttpOnly`, `SameSite=Lax` and `Secure` for every start. The sign-in form
carries no CSRF token; `SameSite=Lax` is what makes that acceptable.

Guard rail: `phpapp/tests/test_licence_server.php` (22 assertions) runs the
**real** library against a temp store — with `LICENCE_STORE` set first, because
on the command line the library would otherwise write its database one level
above itself: inside this repository.

---

## 3. Books receiver — it dropped money, and nobody else was holding it

### What is right

Auth refuses everything when no token is configured, compares in constant
time, and refuses an empty token. Documents are idempotent by the ERP's external
id — the same invoice, receipt or credit note re-sent creates nothing twice,
and the existing round-trip test proves the ERP's mappers and the receiver's
readers agree on every field. The arithmetic lives in one place behind a
13-method storage seam, so a real Books adapter can never compute a figure.

### GAP-028 (P1) — money it could not apply simply vanished · **FIXED**

The ERP's own connector says *"the ERP never recomputes any of it."* Books is
the only ledger of paid and outstanding. Driven through the real dispatcher
(`evidence/books-receiver-money.php`):

| Case | Before the fix |
|---|---|
| Customer pays **15,000** against 10,000 owed | 10,000 applied, **5,000 held nowhere** |
| Receipt of 8,000 lands **before** its 8,000 invoice | invoice reports **UNPAID, 8,000 outstanding** |
| Credit note of **5,000** against 2,000 outstanding | 2,000 applied, **3,000 held nowhere** |

The second is ordinary. `books_bridge_drain()` processes the outbox in order
but **continues past a failed push**, so one transient Books error on an invoice
lets the receipt queued behind it overtake. The customer has paid; the ERP shows
them owing.

**Fixed, the standard-practice way:** money with nowhere to go is held **on
account** for the party and pays the next invoice down the moment it arrives.
`status` reports the balance as an additive `on_account` key — and the ERP
now stores it and **shows it on the invoice screen** (*"₹5,000 held on account
for this customer — it will settle their next invoice"*), so held money is
visible to the accounts desk, not merely not-lost. Each import is now one
transaction, so a crash between two rows cannot leave a receipt half-applied.

The 13-method contract a real Books adapter implements is **untouched**: the
on-account balance is a separate optional interface. The reference store
implements it; an adapter that does not keeps today's arithmetic and gets the
unapplied figure **back in the reply** — so on no backend is the money silent.
`test_books_receiver_adapter.php`, whose in-memory store implements only the
13 methods, still compiles and still passes.

**Q-H** asks you to confirm the rule. "On account, applied to the next invoice"
is what every accounting package does; if MGH Books should refund instead, or
hold for a human, it changes in one function.

### GAP-029 (P3) — an edited receipt would disagree with its invoice · **recorded, latent**

Re-importing a receipt with a changed amount updates the receipt's header and
leaves the money already applied alone: the receipt says 7,000, the invoice
shows 3,000 paid. **Not reachable today** — the bridge fires only from
`books_receipt_create`, and there is no receipt-edit path — but the outbox's
hash-based re-queue was designed for exactly this, so it fires the day an edit
path appears. Recorded rather than fixed: unwinding a partially applied receipt
is a business rule (reverse newest-first? refund?) that needs an answer before
it is written.

Guard rail: `phpapp/tests/test_books_receiver_money.php` (20 assertions),
including a 13-method-only store to prove the seam still holds.

---

## 4. `sop/`

Ten Markdown guides, no code. Nothing to audit for behaviour. (Whether the
guides still describe the screens as they are after this audit is a
documentation question for the roadmap, not a defect here.)

---

## 5. `mgh-hire` — surveyed, awaiting Q-C

The ERP does not reference it anywhere; it is standalone by design (*"shares
none of its data with any other system"*); it was last touched on
2026-09-05. **Nothing in the ERP depends on it**, so its state does not affect
anything above.

A grep-level survey, which is a *lead list* and not an audit:

| Question | What the survey found |
|---|---|
| SQL injection | 51 prepared statements, **zero** string-built queries |
| Password storage | `password_hash` / `password_verify`; no plain compares |
| Output escaping | 152 escaping calls; **zero** raw `$_GET`/`$_POST` echoes |
| CSRF | present in 11 files across 25 POST forms — coverage not verified per form |
| Uploads | 13 sites; an 8 MB cap on the **public** careers page; type is the **applicant's** declared MIME, content never inspected |

The last is the one lead worth stating precisely, because it is on the public
internet. I checked how the stored CV reaches a recruiter before writing it:
it is a `download=` link on a `data:` URI. A top-level `data:` navigation runs
in an **opaque origin** in every current browser, so an applicant's
`text/html` "CV" cannot run script in the recruiter's session — **the
stored-script path is closed by the browser model, not by the code.** What
remains is a **P3**: a file named by the applicant, with a type the applicant
chose, handed to the recruiter to open locally. The right fix is the same
discipline the ERP already has (`upload_reject_reason` + an allow-list on the
careers page). Not applied here: a 2,555-line separate product gets a full pass
or none, and that is **Q-C**.

---

## 6. Evidence, all re-runnable

| Script | What it establishes |
|---|---|
| `evidence/licence-server-probe.php` | the schema's answer to a replayed payment, and an unbounded term — before and after |
| `evidence/books-receiver-money.php` | four money cases through the real dispatcher, with what each stored — before and after |
| `phpapp/tests/test_licence_server.php` | replay refused with the expiry moved exactly once; term capped; throttle; cookie flags; no stray session starts |
| `phpapp/tests/test_books_receiver_money.php` | on-account for all three cases, the next invoice paid from it, the ordinary case unchanged, and a 13-method-only adapter told rather than silenced |
