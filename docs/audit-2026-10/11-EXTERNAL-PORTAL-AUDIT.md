# 11 — The three external portals

**Audited** 18 October 2026 · **Status** security pass complete on all three
portals · reduces **GAP-012** to a screen-quality audit

---

## 1. Why this document exists

The audit's first route inventory was parsed from `ops.php` alone. Three entire
signed-in applications are dispatched *before* the staff application is ever
reached, so none of them appeared — not one screen, not one permission, not one
route. They were invisible to every deliverable from 01 to 10.

That is the single largest error in this audit, recorded as
`00a-ERRATUM-COVERAGE.md` and **GAP-012 (P1)**. These are the screens your
**customers**, your **vendors** and your **marketplace professionals** see. A
defect here is visible outside the company, which makes it worth more attention
than anything internal, not less.

**Three separate front doors**, each with its own sign-in, its own user table and
its own session key — deliberately, so that an outsider is never pushed towards a
staff login:

| Portal | Address | Who signs in | User table | Session key |
|---|---|---|---|---|
| **Client** | `/portal/*` | a contact at a client company | `client_users` | `cuid` |
| **Vendor** | `/vendor/*` | a vendor, supplier or agency | `vendor_users` | `vuid` |
| **Professional** | `/pro/*` | one individual technical professional | `cx_professionals` | `cxpid` |

**77 routes** in total: 35 client, 13 vendor, 29 professional.
Evidence: `evidence/portal-route-extract.py` (re-runnable).

---

## 2. What was measured, and what was found

Two questions decide whether an outside-facing portal is safe:

1. **Can one party read another's records** by changing a number in the address?
2. **Is each permission the administrator is offered actually enforced**, or only
   hidden from the menu?

### 2.1 Cross-party isolation — holds

Of the 77 routes, **25 read a record id**. Every one of them constrains that id
to the signed-in party, either in the handler or in the read helper it calls.
The pattern is deliberate and documented in the code itself
(`lib/portal.php:330`):

> *"The partner id is in the WHERE clause, not checked afterwards — an id
> belonging to somebody else simply does not exist as far as this is
> concerned."*

That is the right way round. A check applied *after* a read can be forgotten; a
party id inside the `WHERE` clause cannot. Nested reads inherit it — e.g.
`portal_jobs($callId)` resolves the call through `portal_call()` first, so a
visit cannot be reached via a work order that is not yours.

**No cross-party exposure was found.** This is a genuine strength, and it is the
hardest of the two questions to get right.

> **Method note.** My first scan reported **18 routes** with "no owner
> constraint". That was my scan's limitation, not a defect: it read only the
> handler and the constraint lives one level down, in the read helper. The
> script now follows helper calls and reports **zero**. Recorded because the
> first number, had it been published, would have been eighteen false alarms on
> the most sensitive part of the system.

### 2.2 Permission enforcement — two real defects, both now fixed

The client portal carries **eleven** named permissions (`PORTAL_PERMS`), which a
client's main contact grants and withholds per colleague through the portal team
editor, with five starting presets:

| Preset | What it promises |
|---|---|
| `FULL` | a main contact — everything bar the marketplace |
| `QUALITY` | *"accepts reports, sees no money"* |
| `COMMERCIAL` | *"sees reports and invoices, does not accept reports"* |
| `ACCOUNTS` | *"invoices only"* |
| `READONLY` | *"sees everything, changes nothing"* |

Those presets are a promise to a client whose commercial contact must not read
technical results, and whose accounts clerk must not read nonconformities. The
file states the rule it means to keep (`lib/portal.php:1745`):

> *"Refuse rather than hide: hiding a link but leaving the address reachable is
> the bug this whole file exists to avoid."*

Ten of the eleven permissions keep that rule exactly. Two defects broke it.

---

#### GAP-013 (P2) — "Ask for a new job" was never enforced · **FIXED**

`/portal/request` had **no permission check at all**. The menu link was correctly
hidden from a user without the right (`views/portal/top.php:26`), and the address
was reachable by anyone signed in.

So a contact on the **ACCOUNTS** preset (*"invoices only"*), or on **READONLY**
(*"sees everything, changes nothing"*), could raise a job request in the
company's name. Worse, **three screens invited them to**: the invoices screen —
which an Accounts-only contact *can* open — carried the sentence *"If any line is
in query with us, say so on the request form"*, with the link unconditional. The
read-only contact was being walked to a door marked for someone else.

**Confirmed by execution**, precondition asserted:
`evidence/portal-perm-enforcement.php`. A user with `pcan('request') === false`
created a row in `portal_requests`.

**Fixed, in three places:**
1. `portal_need('request', …)` on the route — which gives the user the sentence
   that tells them *who to ask*, not a bare refusal.
2. The check repeated **inside `portal_request_create()`**, so the write defends
   itself whichever door calls it. This is the same reasoning as the party id in
   the `WHERE` clause: a write that defends itself cannot be reached through a
   door somebody forgets to lock. (The probe found the route fix alone
   insufficient, because it called the helper directly — which is exactly what a
   future second caller would do.)
3. All three invitations now sit behind `pcan('request')`.

---

#### GAP-014 (P1) — the assistant answered questions the user was refused · **FIXED**

Both the client and the vendor portal offer an **Assistant** — ask about your
records in plain words. Its context was assembled by `cvp_ai_scope_context()`,
which scoped by **company** and **never consulted the signed-in user's
permissions**.

The consequence, confirmed by execution
(`evidence/portal-assistant-scope.php`):

> A contact on the **ACCOUNTS** preset, refused `/portal/reports` and
> `/portal/issues` at the front door, was handed **`IRN/PROBE/0001` · Pressure
> vessel weld inspection · result: FAIL · release: NOT_RELEASED`** — and every
> nonconformity's reference, title, severity, status and due date — simply by
> asking the assistant instead.

A failed, unreleased inspection result is precisely the thing a client's quality
manager withholds from a purchasing contact. The permission control the product
advertises was bypassable by typing a question.

**Not a tenant leak.** Every query was still bound to the one company, and the
system prompt forbids naming another. The control it defeated was the one
*inside* the client company — which is the control the portal team editor exists
to provide.

**Fixed:**
- `cvp_ai_scope_context($audience, $partnerId, $can)` now takes the audience's
  permission test and includes the reports section only on `reports`, the
  nonconformity section only on `issues`. The signature matches
  `cvp_action_centre($audience, $partnerId, $can)` — which, two functions away in
  the same file, **already did this correctly**. The assistant simply had not
  followed the pattern beside it.
- New `cvp_ai_scope_keys()` says which sections a user may read.
- `cvp_ai_answer()` refuses before calling the model when that list is empty —
  and the permission guard runs **ahead of** the "assistant not switched on"
  guard, so no future change to how availability is decided can get in front of
  the security check.
- The model is now told its context may be a **subset** of the company's
  records, so it cannot imply it has seen everything.
- Both navigation menus offer the Assistant only to a user who can read
  something through it; both screens say, in plain words, what it can and cannot
  see.

> **Method note.** The first version of the probe printed *"CONFIRMED BY
> EXECUTION"* for the request defect without having established it, through two
> defects of my own: it re-permissioned one user between probes (and
> `portal_user()` memoises on the signed-in id, so the user never actually lost
> the right), and its conclusion fired on *"a row was created"* alone without
> asserting that the permission had been withheld. It therefore announced a
> permission bypass performed by a user who held the permission. Both are fixed
> and both are recorded in the script's own header, because they are easy to
> repeat. **A probe needs to assert its own precondition, or it is theatre.**

---

### 2.3 Routes open to any signed-in user — checked, correct

Ten client routes carry no `portal_need()`. Each was read; all ten are right:

| Route | Why it is correctly open |
|---|---|
| `/portal`, `/portal/alerts`, `/portal/reputation` | each tile and row checks `pcan()` for itself; the page is a frame |
| `/portal/login`, `/portal/logout`, `/portal/accept`, `/portal/password` | sign-in and one's own password |
| `/portal/plans`, `/portal/pay` | the company's own subscription |
| `/portal/team` | gated **harder** — `cvp_client_is_admin()`, and it returns **404**, so it does not even admit the screen exists |
| `/portal/bench` | gated structurally: the signed-in party must resolve to an active agency organisation |
| `/portal/assistant` | now bounded by what the user may read (GAP-014) |

---

### 2.4 The vendor portal — clean, and the assistant fix verified there

The vendor portal carries **four** permissions (`VENDOR_PERMS`): `reports`,
`issues`, `qualification`, `market.apply`. **All four are enforced at their
routes** — nothing like GAP-013 exists here. Evidence:
`evidence/portal-perm-enforcement-vendor.php`.

The assistant fix was written once, for a function **both** portals share. That
is a reason to test both, not to test one: a shared function still takes a
different permission test and reads a different column per audience. Run on the
vendor side, a vendor user holding only `qualification` is handed **nothing**,
is refused by the **permission** guard specifically, and a vendor who *does*
hold `reports` still receives their own shared report and still **not** the
nonconformity section. Frozen in the test as its own section.

> **Method note — a wrong row I caught before publishing.** The first run of the
> vendor probe reported `issues` as being enforced at **`/vendor/password`**,
> which is nonsense. The cause was a regex of mine: written `"…\$route…"` inside
> a double-quoted PHP string, PHP collapses `\$` to `$` and PCRE then reads it
> as an **end-of-line anchor**, so the `if ($route === …)` dispatch form never
> matched. Only the `case` arms were found, the last of them ran to the end of
> the file, and it was credited with every gate below it —
> `/vendor/issues` and `/vendor/issue` live in their own function further down.
>
> That flaw also sat in the **client** fixture generator, which split on `case`
> alone. I checked rather than assumed: in `lib/portal.php` there are **zero**
> `portal_need()` calls after the last `case`, so nothing had been mis-credited
> and the committed fixture was right — but it was right by luck. Both fixtures
> now split on **both** dispatch forms (the client one grew from 32 routes to 35,
> gaining the three sign-in routes), and the test asserts that the gates after
> the final route mark are **exactly** what the fixture records for that route,
> so a gate added below the router can never again be silently attributed to it.

---

### 2.5 The professional portal — isolation proven by execution

It has no per-user permission model at all, and that is correct: a professional
account is **one individual**, with no colleagues to restrict. So its only
security question is ownership — and it matters most on the **three routes that
serve files**, because a document handed to the wrong person cannot be recalled.

Signed in as professional A, pushing professional B's ids through the same
ownership checks each route performs
(`evidence/pro-portal-isolation.php`):

| Route | A tried to reach | Result |
|---|---|---|
| `/pro/file` | B's uploaded certificate | **refused** |
| `/pro/documents` | **delete** B's document | **refused**, B's row untouched |
| `/pro/voucher` | B's payment voucher | **refused** |
| `/pro/voucher-file` | B's expense receipt | **refused** (two hops: file → voucher → subject) |
| `/pro/report-file` | B's site-report deliverable | **refused** |
| `/pro/messages` | B's message thread | **refused** |

Each refusal is paired with a **control** — the rightful owner reaching the same
record — and with a **sanity check** that the row was found at all, so a refusal
can never be mistaken for an empty table. The file routes answer **404**, not
403; that is the right choice, because a 403 confirms the record exists.

> **Method note.** The message-thread control failed on the first run: B was
> refused B's own thread. That was my seed, not the product —
> `connect_msg_app()` inner-joins `cx_requirements`, and I had pointed the
> application at a requirement that did not exist, making the row invisible to
> everyone. Without that control, the refusal beside it would have looked like
> proof of a working ownership check when it proved only that the row could not
> be seen by anybody. **A probe needs a control as much as it needs a
> precondition.** (An earlier run also died because I invented a `status` column
> on `cx_professionals`, which has `is_active`. Cheap failure; the schema is now
> read, not assumed.)

---

## 3. Still to do on these portals

This pass answered the two security questions. It did **not** do for the portals
what deliverables 04 and 05 did for the staff application:

| Outstanding | Why it matters |
|---|---|
| Field-by-field screen audit of all 77 routes | the terminology, validation and empty-state work done for staff screens has not been done here |
| Phone-width pass at 390px | a professional in the field is phone-first by definition |
| Accessibility | these screens are used by people outside your organisation, on equipment you do not control |
| The professional portal's **write** paths | isolation is proven on reads and on the one delete tested; the profile, CV, credentials and voucher-line writes are not each individually driven |
| `/pro/tax-*` (4 routes) and `/pro/geo` | small lookup endpoints, read only for isolation so far |

Closed since the first pass: the vendor portal's four permissions (§2.4), the
assistant on the vendor side (§2.4), and the professional portal's isolation
including all three file routes (§2.5).

**GAP-012 is therefore reduced, not closed.** The coverage hole is mapped and its
two security defects are fixed; the quality audit of these 77 screens remains.

---

## 4. Evidence, all re-runnable

| Script | What it establishes |
|---|---|
| `evidence/portal-route-extract.py` | the 77 routes, their gates, and the owner constraint on each of the 25 that read an id |
| `evidence/portal-perm-enforcement.php` | per-permission enforcement; executes the write a withheld user should not be able to make, and asserts its own precondition first |
| `evidence/portal-assistant-scope.php` | what the assistant is handed for a user denied reports and nonconformities |
| `evidence/portal-perm-enforcement-vendor.php` | the vendor portal's four permissions, and the assistant fix on the vendor side |
| `evidence/pro-portal-isolation.php` | professional A against professional B's files, voucher, receipt, deliverable and messages — each with a control and a sanity check |
| `phpapp/tests/test_portal_perm_enforcement.php` | 62 assertions freezing both fixes, across the client and vendor portals |
| `phpapp/tests/fixtures/portal_routes_gated.tsv` | all 35 client routes and their gates; a lost gate fails by name |
| `phpapp/tests/fixtures/vendor_routes_gated.tsv` | all 13 vendor routes and their gates |
