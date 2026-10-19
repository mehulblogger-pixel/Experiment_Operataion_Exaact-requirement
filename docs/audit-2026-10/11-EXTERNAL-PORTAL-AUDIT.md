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

### 2.6 Screen quality at phone width, and the accessibility basics

Measured by rendering every screen in a real browser at **390×844** (an iPhone
12/13/14 viewport) as four signed-in personas, and reading the painted boxes
rather than the stylesheet: `tools/portal-mobile-a11y-check.js`.

**The layout holds. Zero horizontal overflow on any of the 39 screens walked.**

That deserves saying plainly, because the client portal carries **no `@media`
query at all** — 33 lines of CSS. It fits a phone anyway, through `clamp()` type,
`flex-wrap` on the header and nav, an `auto-fit` tile grid and
`overflow-x:auto` on tables. I did **not** record the missing media query as a
defect, and measuring proved that right. The professional portal does have a
breakpoint and a dark-mode palette.

Three real defects were found and fixed.

---

#### GAP-015 (P1) — the professional portal wrote messages nobody ever read · **FIXED**

`lib/connect_pro.php` wrote `$_SESSION['pro_flash']` in **ten** places. Every
one is the outcome of **money or reputation**: a payment, a subscription, a
credit pack, a rating report. Among them, verbatim:

- *"That payment is no longer available."*
- the error branch of starting a subscription (`mkt_pay_start` → `'error'`)
- the result of verifying a Razorpay payment
- *"That rating could not be reported."*

**No view read that key.** Confirmed by execution
(`evidence/pro-flash-lost.php`): the message was set, a real `/pro` screen was
rendered, and it does not appear — and it was never cleared either, so it
accumulated in the professional's session.

So a professional whose payment failed was told **nothing at all**. They were
redirected to the plans page and left to guess. The other two portals both
rendered their channel; the professional portal was the one that was missed.

**Fixed:** `connect_pro_flash_set()` / `connect_pro_flash_take()` carrying a
severity, rendered in `views/pro/top.php` using the `.msg.err` / `.msg.ok`
styles that **already existed** in that portal for inline errors. All ten
writes now go through the helper, each tagged by its own outcome flag, so none
can skip the tag.

---

#### GAP-016 (P2) — a refusal arrived wearing the success colour · **FIXED**

`views/portal/top.php` hardcoded `class="pmsg ok"`, so **every** message in the
client portal was painted as a success. Of 42 message sites, at least nine are
refusals or failures — *"That deployment is not yours."*, *"That payment is no
longer available."*, *"Your access here does not include…"* — plus the failure
branch of several `$ok ? 'Done.' : $msg` pairs. The CSS defined `.pmsg.err` and
never used it for the flash.

**Fixed:** the same tagged-channel treatment, plus `role="alert"` on all three
renderers so the message is **announced**, not merely seen.

> **Honest scope.** The vendor portal has the same hardcoded class but **not the
> same defect**: its only flash is a genuine confirmation, and a permission
> refusal there goes to its own "Not available to you" page via
> `cvp_vendor_need()`. Its renderer was made tag-capable so a failure added
> later cannot go green, and that is all it needed. Reporting it as a third
> instance of the bug would have been inflating the finding.

---

#### GAP-017 (P2) — 113 fields had a label that was never attached to them · **FIXED**

The pattern across all three portals was:

```php
<label>Name</label><input name="name">
```

Adjacent, never **associated** — no `for`, no wrapping. It looks correct and is
invisible: a screen reader announces the field as unlabelled, and tapping the
label does not focus it. On the professional portal that matters most, because a
professional is on a phone on a site. The browser pass found unnamed fields on
eight screens, including `name`, `mobile`, `day_rate_min`, `cert_number`,
`expiry_date` and the file pickers.

**Fixed:** 113 pairs wired with matching `for`/`id`, derived from each field's
own `name`; the four whose name is built in PHP carry an explicit `for` or an
`aria-label`. The convention already existed in the codebase — 18 labels used
`for=` — it simply had not been applied consistently.

> **Method note — a regression I introduced, caught by the browser.** My first
> transform used `<label…>(.*?)</label>\s*<control>` with `DOTALL`. On a
> **wrapping** label — `<label>Rating <select…></select></label>`, which needs no
> `for=` because the control is already inside it — that match fails in the right
> place and the engine **backtracks**, extending `.*?` forward until it finds
> some later `</label>` that *is* followed by a control. In
> `views/portal/roster.php` it swallowed 35 lines and pointed a label reading
> **"Rating" at the name field** — worse than no label, because a screen reader
> then announces the wrong one with confidence.
>
> I had copied the views to a backup before running it, restored them, and
> rewrote the rule so the label's content may not contain a control or another
> label — which excludes every wrapping label by construction. I also added a
> **duplicate-id check to the browser probe** before re-running, because a
> template inside a `foreach` is exactly what would emit a repeated id, and
> static analysis cannot see it. The rendered pages reported zero collisions.

---

#### Tap targets: counted honestly

The first run reported **48 failures**, almost all of them text links inside
sentences. That was wrong of the probe, not of the portal: **WCAG 2.5.8 exempts
a target "in a sentence or whose size is otherwise constrained by the
line-height of non-target text."** Counting *"say so on the request form"* as a
failure buries the ones that matter.

The probe now applies that exemption and counts only controls that **stand
alone**. On that basis the real list was the header **Sign out**, the
`← My passport` back-links, the **`✕`** remove controls at **14px** — a
destructive action with a 14-pixel target — and the client dashboard's
**"Awaiting you"** links, which are the one block a client is meant to act on.

All now clear 24px. Final measurement: **234 passed, 0 failed, 51 warnings.**

The 51 warnings are deliberately left: targets between 24 and 40px (compliant,
below Apple's 44px suggestion) and tile micro-labels at 10.5–11.5px
(*"Inspections"*, *"Billed to you"*). Changing the type scale is a design
decision under `docs/05-ui-ux-blueprint.md`, not an audit fix, so it is recorded
rather than altered.

---

### 2.7 Terminology, and colour contrast

#### GAP-018 (P2) — customer-facing screens hardcoded words the tenant chooses · **FIXED**

`lib/terms.php` exists so a tenant picks its own vocabulary, and the presets
genuinely differ. Measured, not assumed:

| Preset | `report` | `job` (plural) | `call` |
|---|---|---|---|
| general | Report | Jobs | Work Order |
| **inspection** | **Inspection Report** | **Deputations** | **Inspection Call** |
| manufacturing | Report | Operations | Production Order |
| trading | Report | Consignments | Order |

Four customer-facing screens hardcoded those nouns. A **trading** tenant's own
client would have opened their portal and read a heading saying
*"Inspection report"* and a column headed *"Visits"* — for what their business
calls a consignment. On an outside-facing screen that is not a cosmetic slip;
it tells the customer the software was built for somebody else.

Fixed in `portal/voucher.php`, `pro/voucher.php` (the marketplace report
deliverable, 7 places), `portal/call.php` and `portal/complaint_new.php`
(3 places), all now via `T()` / `Tl()` / `TP()`.

> **What was deliberately NOT changed.** The first scan counted **22** hardcoded
> nouns. Reading all 22 in context cut it to 10. The rest are **trade names** in
> the manpower marketplace — *"inspector, welder, NDT technician, site
> engineer"*, *"e.g. CSWIP welding inspector"*, *"pressure vessel inspector"* —
> and those are **occupations a client is hiring**, not the tenant's word for its
> own people. Replacing them would have been wrong, and would have damaged
> exactly the marketplace wording the owner described. The test asserts they
> survived, so a later pass cannot "tidy" them away.

---

#### GAP-019 (P3) — the professional portal's header failed contrast by a hair · **FIXED**

`tools/b1-contrast-check.js` has measured the staff application all along, but
it signs in at `/login` as staff and can therefore never reach these three
portals — so the portals had **never** been measured. The same algorithm now
runs inside `tools/portal-mobile-a11y-check.js`.

One real failure: **"Sign out" in the professional portal header at 4.33:1**,
where WCAG 1.4.3 requires 4.5:1 for normal text.

The cause was not the brand colour. The teal gives white text **4.94:1 — already
compliant**; `opacity:.9` on the link pulled it down to 4.33. So the fix was to
drop the fade and leave the palette exactly as designed.

Everything else passes: lead and note text, empty states, tile labels, table
headers, footers, nav links, form labels, flashes, chips and buttons, across all
three portals.

> **Method note — a 1.07:1 reading that was my probe's fault, not the
> portal's.** The first contrast run reported the professional header at
> **1.07:1**, which would be invisible text. That absurdity was the tell. The
> header is painted with a `linear-gradient`, which lives in
> `background-image`; the algorithm walked past it looking for a
> `background-color` and landed on the near-white page background, so
> white-on-teal measured as white-on-white. The probe now detects a gradient,
> reads its colour stops and uses the **worst** one, so the answer is the least
> favourable real pixel rather than an average. **`b1-contrast-check.js` shares
> this blind spot** — it has not bitten there because the staff chrome uses flat
> colours, but it is worth knowing before that changes.

**Final measurement across all three portals at 390px: 273 passed, 0 failed,
51 warnings.**

---

### 2.8 What the forms do with bad input — and the hole that found

HTML `required` is a courtesy to the browser. It is client-side only, trivially
bypassed, and absent entirely from a crafted request. What decides whether a
blank record lands in the database is the **server** — and that matters here
because the same failure was already proven on the **staff** side of this
application: four forms created numbered ghost records (`CALL-00001`,
`CV-00001`, `INC-2026-001`) from an empty submission while three siblings
refused properly (`04-SCREEN-AND-FIELD-AUDIT.md`). A demonstrated bug class,
not a hypothetical one.

Every creating form in the portals was submitted **empty**, over HTTP, with a
valid token so that the refusal under test was the *validation* and not the CSRF
gate (`evidence/portal-empty-submit.py`). **Nothing created a blank row.** The
messages are in the house voice:

| Form | What it said |
|---|---|
| `/portal/request` | *"Tell us what you need and where — a line each is enough."* |
| `/portal/complaint-new` | *"Please give it a one-line subject."* |
| `/portal/roster` | *"Add a professional from the pool, or enter a name."* |
| `/portal/bench` | *"A name is required."* |
| `/portal/hire` | **nothing at all** — see GAP-021 |

---

#### GAP-020 (P1) — the professional portal had no protection against forged requests · **FIXED**

Looking for the two professional forms the empty-submit probe had **skipped** —
and a skip is not a pass — turned up something much worse: `/pro/credentials`
served **two forms and zero CSRF tokens**.

All three portals are dispatched **before** `require_login()`, so nothing
downstream applies the application's CSRF gate. `portal.php` and `cvp.php` each
carry their own: they stamp a token into every POST form and refuse a POST
without it. `connect_pro.php` carried **neither** — no stamping, no gate, no
mention of CSRF anywhere in the file.

**Proven by execution** (`evidence/pro-csrf-probe.sh`): a POST to `/pro/profile`
with **no token at all** and `Referer: https://evil.example/attack` returned
**HTTP 200** and rewrote the professional's record.

Every POST route in that portal was forgeable. A signed-in professional who
opened a hostile page in another tab could have:

- their **rates, availability and contact details** rewritten
- their **certificates and documents deleted**
- their **job applications withdrawn**
- their **contact details revealed to a client** (`reveal_approve`)
- their subscription cancelled or a credit pack bought
- and worst, a voucher marked **"payment received"** — the handshake that
  releases the deliverable to the client and settles the engagement. Forging it
  releases the work product for a payment the professional never got.

**Fixed** by mirroring the two sibling portals exactly: `connect_pro_view()`
buffers and calls `csrf_stamp_forms()`; `connect_pro_csrf_or_die()` refuses any
POST without a valid token, with a 400 and the same plain-words page the other
portals use (and a new `views/pro/message.php` for it to render).

Checked for collateral damage rather than assumed: the four `/pro/tax-*` lookup
endpoints the screens `fetch()` are **GET**, so a POST-only gate cannot touch
them, and a test asserts they stay that way. All 43 portal screens still render
(`tools/portal-crawl.js`), and a **real browser** was driven through the profile
form to confirm a legitimate save still works.

> **Method note — my probe reported the fix as broken.** The first run of
> `pro-csrf-probe.sh` showed the forged request refused (400) *and the
> legitimate one refused too*, which would have meant the fix broke the portal.
> It had not: the same steps by hand returned 200. The script defined a shell
> function named **`head`**, which shadowed the `head` **command** used in
> `| head -1` to extract the tokens, so both tokens came out as garbage. Renamed
> to `say`. **A tool that shadows a builtin can make a working fix look
> broken — and the next person's instinct would have been to revert it.**

---

#### GAP-021 (P2) — the marketplace's own posting form refused in silence · **FIXED**

`/portal/hire` is where a client posts a manpower requirement — the front door
of the marketplace. Its handler reads
`elseif (trim($_POST['title']) !== '')`, so an **empty title matched no branch
at all**: the page re-rendered, nothing was saved, and **nothing was said**.

The client types their need, presses *Post requirement*, the page comes back
looking the same, and they believe it is posted. Its four sibling forms all
answer plainly. This one — the one that earns marketplace revenue — did not.

The codebase already states the principle, in `portal_request_create()`:

> *"A request nobody sees is worse than no portal at all — the client believes
> they have asked, and nothing happens for a week."*

**Fixed:** *"Give the requirement a one-line title — for example 'Welding
inspector, Ahmedabad, 20 man-days'. Nothing was posted."* — tagged as a
refusal, so it is no longer painted green either (§2.6). The two marketplace
plan limits on the same route were tagged as refusals at the same time.

---

## 3. Still to do on these portals

This pass answered the two security questions. It did **not** do for the portals
what deliverables 04 and 05 did for the staff application:

| Outstanding | Why it matters |
|---|---|
| Bad-input (not just empty) validation | §2.8 drove every creating form **empty**; it did not drive each field with the *wrong* value — a date in the past, a negative rate, an oversized file, a malformed email |
| Empty states on the 38 screens not walked | the 39 walked were those the four seeded personas could reach |
| The professional portal's **write** paths | isolation is proven on reads and on the one delete tested; the profile, CV, credentials and voucher-line writes are not each individually driven |
| `/pro/tax-*` (4 routes) and `/pro/geo` | small lookup endpoints, read only for isolation so far |
| The remaining 51 warnings | 24–40px tap targets (compliant) and 10.5px tile micro-labels — a type-scale decision under `docs/05-ui-ux-blueprint.md`, not an audit fix |

Closed since the first pass: the vendor portal's four permissions (§2.4), the
assistant on the vendor side (§2.4), the professional portal's isolation
including all three file routes (§2.5), phone-width layout, message delivery
and field labelling across 39 screens (§2.6), terminology and colour
contrast (§2.7), and what the forms do with empty input (§2.8).

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
| `evidence/pro-flash-lost.php` | renders a real `/pro` screen with a message set and reports whether the professional sees it |
| `phpapp/tools/portal-mobile-a11y-check.js` | 39 screens at 390px as four personas: overflow, tap targets (with the WCAG inline exemption), tiny text, unnamed fields, duplicate ids, missing alt, header-less tables |
| `phpapp/tests/test_portal_flash_and_labels.php` | 49 assertions freezing the message, label and terminology fixes — including that the marketplace trade names are left alone |
| `evidence/portal-empty-submit.py` | every creating form submitted empty, with the row count before and after and the message quoted |
| `evidence/pro-csrf-probe.sh` | a forged POST with no token and a hostile referer, then the legitimate one, against the running application |
| `phpapp/tests/test_pro_portal_csrf.php` | 30 assertions: all three portals define a gate, call it from their router, and stamp their forms; the lookup endpoints stay GET; the empty-title refusal speaks |
