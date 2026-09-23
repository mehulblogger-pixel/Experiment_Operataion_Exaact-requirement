# UX-B5-IMPLEMENTATION — Area-home counts and attention

**Scope: B5 only.** No form redesign (B6), mobile tables (B7), search (B8),
dashboard (B9) or visual polish (B10). **No second count engine, KPI engine or
dashboard calculation.** No new business metric. No database change.

---

## 1. Audit

Starting from the **B0 corrected** measurements, not the original audit's "0
tiles carry a count".

| Measure | B0 | Re-verified here |
|---|---:|---:|
| Tiles wired in source | 24 | **24** — parsed from `lib/areas.php` |
| Tiles rendering a badge, seeded workspace | 13 | **14** on *this* workspace |
| Tiles rendering a badge, empty workspace | 0 | 0 — an empty register has nothing to count |

One parse note: an automated scan of the source found 24 wired but mis-read the
**Nonconformity workspace** tile, whose route is a ternary spanning three lines.
It was already wired. Read by hand, the figure stands at 24.

### The gap, per area

| Area | Wired before | Unwired |
|---|---:|---:|
| Quality | 10 | 13 |
| Marketplace | 7 | 7 |
| Money | 4 | 8 |
| Sales | 1 | 9 |
| Directory | 1 | 6 |
| Admin | 1 | 23 |
| Reporting | 0 | 8 |
| Insights | 0 | 4 |

### What could honestly be wired

The constraint that decided this phase: **a tile may only carry a count that
already exists as a function.** Every candidate was read before use, and two
were rejected on inspection:

| Rejected | Why |
|---|---|
| `imp_declaration_due($personId, $kind)` | per-person — there is no workspace-wide figure, and inventing one would be inventing a metric |
| `connect_client_bench_count($clientPartyId)` | per-client, same reason |
| `hwp_open_count($jobId)` | per-job |
| `consent_open_count()` | counts *active* consents — a volume, not an attention |

---

## 2. What was wired

Seven tiles, each to a counter that **already existed and was already used
elsewhere** in the product.

| Area | Tile | Counter | Tone | Means |
|---|---|---|---|---|
| Sales | Leads | `leads_due_count()` | amber | leads due for follow-up |
| Sales | Inquiries | `inquiries_due_count()` | amber | inquiries due |
| Sales | Quotes | `quotes_awaiting_contract_count()` | amber | won quotes still waiting for a contract number |
| Quality | Competence & authorisation | `competence_due_counts()['expired']` | red | expired authorisations — these stop somebody being allocated |
| Quality | Confidentiality | `conf_open_breach_count()` | red | breaches not closed |
| Reporting | Report register | `idems_awaiting_my_approval_count()` | amber | reports awaiting **this person's** approval |
| Admin | Approval rules | `appr_cond_unarmed_count()` | amber | approval conditions that could not be armed |

**24 → 31 wired in source. 14 → 17 rendering on the same seeded workspace.**

The other four render nothing on the demo data because there is genuinely
nothing due — which is the mechanism working. **Where no actionable counter
existed, none was manufactured.**

---

## 3. The rules this phase had to hold

### A count is data

Showing *"3 open breaches"* to somebody who may not open the breach register is
a disclosure, not a cosmetic slip. Every count is passed as the sixth argument
of the **existing** `$t()` tile builder, inside that tile's own `$show` gate, so
a count is never computed for a tile the user cannot open. Asserted: a user with
no permissions is handed **0 tiles and 0 counts**.

### The licence boundary is the route, not the builder

An assertion that `ops_area_def('money')` returns no tiles for an unentitled
workspace **failed** — and that was the test being wrong. `ops_area_def()`
builds its tiles whatever the licence says; the licence is enforced one level up
by `ops_area_has()`, which `ops_area_home()` requires before it renders
anything. The test now asserts the boundary that actually protects.

A second test assumption was also wrong: `quality` is deliberately gated on
`licence_enabled('operations')` — *"accreditation packs are Operations
access-modules"* — so entitling Operations is **supposed** to bring Quality with
it. `money` is gated on its own module, which is what makes it the honest
subject.

### A zero is silence

`$num()` returns null for 0 and `area_home.php` prints a badge only when the
count is non-empty, so an empty register shows **no badge** rather than a
discouraging "0". This is exactly why the original audit read an empty workspace
as "no counts exist". Asserted: no tile carries a literal 0.

### One engine

Every badge goes through the single `$num()` guard declared once in
`ops_area_def()`. Asserted: no tile computes its own SQL — an inline query on a
navigation screen would be a second calculation by definition.

---

## 4. Verification

| Layer | Result |
|---|---|
| `tests/test_b5_area_counts.php` (new, 36 assertions) | **36 passed, 0 failed** |
| `tools/b5-counts-check.js` (new, 16 checks in Chromium) | **16 passed, 0 failed** |
| Badges on a populated workspace | **14 → 17** of 104 tiles |
| Literal zeros rendered | **0** |
| Slowest area home | `/money` at **1,293 ms** — counting did not make navigation slow |
| Mobile 360×800 / 390×844 | no horizontal overflow |
| JavaScript / server errors | none |

### Mutation tests

| Mutation | Caught by | Result |
|---|---|---|
| A tile computes its own inline SQL | B3 | FAIL, as required |
| `$num()` allowed to return 0 | D2 | FAIL, as required |

### A brittle existing test, corrected rather than worked around

The regression failed on one assertion: *"Reporting still has writing help"* in
`test_simplify_reportcfg.php`. **The tile was still there.** The test read a
fixed **1600-byte** window from `case 'reporting':`, and B5's added line pushed
"Technical writing" past it — it sat at byte ~1553 already, and `substr()`
counts bytes while `lib/areas.php` is full of multi-byte emoji, so the real
margin was smaller than it looked.

A fixed length cannot survive an area gaining a line, and every area eventually
does. The window was replaced with a slice that ends at the **next `case '`** —
exact, needs no upkeep, and with two arming assertions proving the slice was
found and did not run on into the following area. 12 assertions became 14, and a
mutation (moving the tile away) was confirmed still to fail it.

### A second widening of the concurrency test, on new evidence

The authoritative run also failed `X2d` in `test_rb3_emp_code.php` — the
four-way conversion race. The diagnostic added earlier printed the codes:
`[RACE_LOST RACE_LOST WORKFORCE_MATCH CONVERTED]`.

A third loser was refused by the **duplicate guard**: the winner's team record
now existed and shared the candidate's e-mail, so *"is this person already on
the team?"* answered yes before the straggler ever reached the conditional
UPDATE. That is a correct, specific, deterministic refusal — arguably the best
of the three.

The accepted set had been widened once before, on `ALREADY` evidence alone, and
did not include it. It now does. **Widening again on new evidence is right;
widening until it goes green would not be** — the principle is unchanged, and
the arming assertion still rejects a generic `FAILED` or a lock-timeout `BUSY`.
Three consecutive MariaDB runs then passed.

### A fault in the check itself

The first browser run reported **0 tiles** across all eight area homes. The
check was looking for `.master-card` / `.tile` / `.qcard` — components this
screen does not use. `area_home.php` renders `.op-tile` with an `.op-badge`.
Corrected, and the before/after was then measured on the **same** workspace
rather than comparing against B0's different seeded database.

---

## 5. Deferred

| Finding | Why | Phase |
|---|---|---|
| ~73 tiles still unwired | No workspace-wide counter exists for them, and B5 may not write one | **needs a counter first — not a UX task** |
| Insights (0 of 4) and most of Admin | These are configuration and analytics destinations; a badge on "System settings" would be noise | **by design** |
| `imp_declaration_due`, `connect_client_bench_count`, `hwp_open_count` are per-record | A workspace-wide figure would be a new metric | **product decision if wanted** |
| QA / billing-readiness / professional definitions not yet at point of use | carried from B4 | B4 follow-on / B10 |
| `.btn.small` 36px vs 44px | | B7/B10 |
| Nine hand-written `.nowband` blocks | | B10 |

---

## 6. Files changed

| File | Change |
|---|---|
| `phpapp/lib/areas.php` | seven tiles given a count + tone, through the existing `$t()` signature and the existing `$num()` guard |
| `phpapp/tests/test_b5_area_counts.php` | **new** — 36 assertions |
| `phpapp/tools/b5-counts-check.js` | **new** — 16 checks |
| `phpapp/deploy-check.php` | regenerated |

**No view changed. `app.css` unchanged.** `area_home.php` already knew how to
paint a badge; it simply had nothing to paint on those tiles.

---

## 7. Regression

| Engine | Result |
|---|---|
| SQLite | **13,800 passed, 0 failed** |
| **MariaDB (authoritative)** | **13,801 passed, 0 failed** |

### Protected modules — MariaDB

| Module | Suite | Result |
|---|---|---|
| Recruitment | `recruit` | 296 / 0 |
| Recruitment — numbers & races | `rb3` | 276 / 0 |
| Workforce identity | `r20` | 44 / 0 |
| Workforce duplicate doors | `fa7` | 29 / 0 |
| Operations | `p2` | 437 / 0 |
| Operations | `p3` | 2,611 / 0 |
| Reporting | `report` | 354 / 0 |
| Money — billing | `billable` | 90 / 0 |
| Money — vouchers | `voucher` | 117 / 0 |
| Marketplace | `connect` | 1,020 / 0 |
| Marketplace | `mkt` | 174 / 0 |
| Tenant isolation & entitlement | `saas` | 169 / 0 |
| Tenant API | `tapi` | 170 / 0 |
| Navigation invariants | `m11` | 60 / 0 |
| Terminology engine | `terms` | 69 / 0 |
| **Area simplification (the corrected slice)** | `simplify` | **153 / 0** |
| B1 accessibility | `b1_access` | 112 / 0 |
| B2 navigation | `b2_nav` | 42 / 0 |
| B3 next action | `b3_next` | 55 / 0 |
| B4 terminology & relationships | `b4_term` | 67 / 0 |
| B5 area counts | `b5_area` | 36 / 0 |
