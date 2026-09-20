# Phase 6 · Batch 3 — Corrective adversarial audit (A1–A6)

**Audit only. No product code, schema, index, route, permission or behaviour was
changed.** Every result below came from a probe run against the shipped tree on
**both** engines; nothing is asserted from reading alone.

---

## 1 · Executive summary

Six findings, five of them validated as **real product behaviour** and one
reclassified. Four were **introduced by the Batch 3 implementation commit
itself** (`c3d558d`), one is pre-existing detector behaviour that Batch 3
newly exposed, and one is new in this audit.

| # | Finding | Status | Engine | Severity |
|---|---|---|---|---|
| **A1** | the one-primary rule is not race-proof | **CONFIRMED DEFECT** | **MariaDB 3/3 fail**, SQLite 1/3 | **HIGH** |
| **A2** | the account key can be side-stepped with whitespace | **CONFIRMED, impact narrower than feared** | both | LOW–MEDIUM |
| **A3** | public registration is an existence oracle | **CONFIRMED, and stronger than reported** | both | MEDIUM |
| **A4** | unauthenticated write into **customer-visible** UI | **CONFIRMED, and worse than reported** | both | **HIGH** |
| **A5** | a closed organisation still blocks registration | **CONFIRMED across four statuses** | both | LOW |
| **A6** | *(new)* the account index can never recover on SQLite | **CONFIRMED DEFECT** | SQLite only | MEDIUM |

**Recommendation: REOPENED FOR CORRECTIVE IMPLEMENTATION** (§22).

## 2 · Baseline and provenance

| | |
|---|---|
| Batch 3 application baseline | `8de9607` |
| Batch 3 evidence / documentation | `c38d243` |
| Recruitment corrective fix | `aef8974` |
| Application state audited | `aef8974` — `git diff aef8974 HEAD -- phpapp` = **0 lines** |

None of those commits was amended, rewritten or force-pushed.

## 3 · Test environment

PHP 8.4.19 · SQLite 3.45.1 · **MariaDB 10.11.14** (authoritative) ·
`isolation=REPEATABLE-READ`, `autocommit=1`.

All probes live **outside** the shipped suite, under the session scratchpad, and
bootstrap the application by parsing `index.php`'s own require list. They were
removed before this commit; `phpapp/tests/` is untouched.

## 4 · Harness validation — the experiment must prove it ran

The previous pass produced **"0 contacts, 0 primaries"**, which reads like a
clean invariant and was in fact an empty experiment: the worker lived in `/tmp`,
so `dirname(__DIR__)` resolved to `/`, it read no `index.php` and loaded no
library. Every harness here therefore carries per-worker markers and refuses to
report a verdict unless all of them are satisfied:

```
workers_started=3 booted=3 db=3 actor=3 barrier=3 attempted=3 created=3
contacts=3 primaries=3
VERDICT=FAIL — 3 primaries, Q22 says 0 or 1
```

`VERDICT` is one of **PASS**, **FAIL**, or **INVALID EXPERIMENT (harness did not
run as specified)**. A count of zero can never reach PASS.

## 5 · A1 — concurrent primary contacts · **CONFIRMED, HIGH**

**Mechanism, read from the shipped code:** `partner_contact_add()` calls
`partner_contact_clear_primary()` and then inserts. Inspection of both functions
finds **no `beginTransaction`, no `FOR UPDATE`, no `LOCK`**, and the schema
carries **no unique constraint** — `partner_contacts` has only the non-unique
`ix_pcont_partner (partner_id)` on both engines. With `autocommit=1` each
statement commits on its own.

**Timeline (three workers, one organisation, barrier-released):**

```
T1  W1 UPDATE …SET is_primary=0 WHERE partner_id=P   → commits (no rows yet)
T2  W2 UPDATE …SET is_primary=0 WHERE partner_id=P   → commits
T3  W3 UPDATE …SET is_primary=0 WHERE partner_id=P   → commits
T4  W1 INSERT (…, is_primary=1)                      → commits
T5  W2 INSERT (…, is_primary=1)                      → commits
T6  W3 INSERT (…, is_primary=1)                      → commits
DB state:    3 rows, all is_primary=1
Commit order: every demotion precedes every insert
Final state: THREE primaries — Q22 says 0 or 1
```

Nothing "wins" and nothing errors: all three succeed, because the demotions all
ran before any insert existed to demote.

**Reproduction, three trials each:**

| Engine | Trial 1 | Trial 2 | Trial 3 |
|---|---|---|---|
| **MariaDB (authoritative)** | **3 primaries** | **3 primaries** | **3 primaries** |
| SQLite | 1 (held) | 1 (held) | **3 primaries** |

SQLite is **not** safe — it is intermittent, and it failed one trial in three.
The earlier "SQLite holds" reading was luck, not a property.

**Historical data:** a freshly seeded database contains **0** organisations with
more than one primary. That says nothing about a real workspace, which is why
the remedy must tolerate pre-existing duplicates (§17).

## 6 · A2 — whitespace and the account key · **CONFIRMED, impact narrow**

The key is a **database-generated** column, defined once in
`portal_acct_migrate()`:

```php
CASE WHEN COALESCE(is_active,0)=1 AND COALESCE(email,'')<>'' THEN LOWER(email) ELSE NULL END
```

`LOWER`, no `TRIM`. Sign-in (`portal_login`) trims the **typed input** and then
compares `LOWER(email)=?` against the **stored** value.

| Variant | SQLite | MariaDB |
|---|---|---|
| exact repeat | refused | refused |
| different case | refused | refused |
| **trailing space** | **accepted** | refused *(MariaDB ignores trailing spaces when comparing)* |
| **trailing tab** | **accepted** | **accepted** |
| **leading space** | **accepted** | **accepted** |
| **leading NBSP** (U+00A0) | **accepted** | **accepted** |
| active rows for one human-readable address | **5** | **4** |
| **rows the sign-in query resolves** | **1** | **1** |

**The door Q23 was built to close stays closed on both engines.** The padded rows
are unreachable by anyone typing the address, because sign-in trims the input
but the stored value keeps its padding. What is wrong is the *claim*: the
boundary is "one active account per exact lower-cased string", not "per
address". Classification: **specification/documentation issue with an optional
one-word hardening** (`TRIM`), not an exploitable defect.

## 7 · A3 — the existence oracle · **CONFIRMED, stronger than reported**

Measured over real HTTP, unauthenticated, three POSTs to `/join`:

| Probe | Status | Body bytes | Body md5 | Time | Signal |
|---|---:|---:|---|---:|---|
| **taken** organisation | 200 | 12 389 | `243422b09117` | **21 ms** | "We could not complete this registration online…" |
| **free** organisation | 200 | 3 507 | `2cb97fcd7402` | **284 ms** | "Your account is ready" |
| taken again | 200 | 12 389 | `243422b09117` | 19 ms | identical to the first |

The status code is identical and the *wording* of the refusal discloses nothing —
Q21 holds on its own terms. But an outsider distinguishes the two cases by
**body content, by a 9 KB size difference, and by a 13× timing difference**
(the success path runs `password_hash` plus three inserts). It is repeatable and
needs no account. Side effects also differ: a free name creates an organisation
and a login; a taken name creates neither but appends to the customer's record.

## 8 · A4 — unauthenticated write into customer-visible UI · **CONFIRMED, HIGH**

**Trace:** public POST → `find_duplicate_partner()` → match → refusal →
`act_log('PARTNER', $hit['row']['id'], 'SYSTEM', …)`.

| Question | Answer |
|---|---|
| Table | `activities` |
| Kind / entity | `SYSTEM` / `PARTNER`, `entity_id` = the matched customer |
| **`partner_id` set?** | **Yes — to the matched customer** |
| Target chosen by the attacker? | **Indirectly but reliably — by choosing the name** |
| Target taken from request data? | No — derived from the detector |
| Rate limiting | **None** on the route |
| Notifications | none — `act_log` sends nothing |
| Staff visibility | yes (Customer 360, CRM dashboards count by kind) |
| **Customer visibility** | **YES** — `connect_client_dash()` renders `SELECT kind, subject, occurred_at FROM activities WHERE partner_id=? ORDER BY occurred_at DESC, id DESC LIMIT 8` |

**This is not audit noise.** The customer's own marketplace dashboard shows the
last **eight** activities, so a stranger can both write into it and **evict what
is really there**. Demonstrated: three genuine entries were added (a site visit,
a call, a quotation), then ten unauthenticated POSTs were sent:

```
WHAT THE CUSTOMER NOW SEES ON THEIR OWN DASHBOARD (8 slots):
   [SYSTEM] Public registration refused — POSSIBLE match by name   ×8

  of 8 visible rows, genuine customer activity = 0
  total rows now on that customer: 15
```

Classification against the question posed: **C — both.** The security evidence is
worth keeping, but it is currently written into the *customer's own business
history*, where a stranger can flood it. Separating the two streams preserves the
evidence without handing an outsider a write primitive.

## 9 · A5 — closed organisations · **CONFIRMED across four statuses**

`find_duplicate_partner()` contains **no reference to `status`** (verified: 0
occurrences in the function body). Result, identical on both engines:

| Organisation status | detector (name) | detector (tax id) | public registration |
|---|---|---|---|
| ACTIVE | POSSIBLE | EXACT | REFUSED |
| INACTIVE | POSSIBLE | EXACT | REFUSED |
| CLOSED | POSSIBLE | EXACT | REFUSED |
| SUSPENDED | POSSIBLE | EXACT | REFUSED |

Status is not consulted at any point. Whether that is right is a business
question, not a technical one — but it is currently **undocumented and
unintentional-looking**, because no code comment or test mentions status.

## 10 · A6 — *(new)* the account index can never recover on SQLite · **CONFIRMED**

`portal_acct_migrate()` decides whether to add the key column using
`t_cols_of()`, which on SQLite reads `PRAGMA table_info`. **`PRAGMA table_info`
does not list generated columns** (`PRAGMA table_xinfo` does). So on SQLite the
column is reported missing forever:

```
engine=sqlite   t_cols_of sees uq_active_email: NO
engine=mysql    t_cols_of sees uq_active_email: YES
```

Consequence, from the shipped control flow: on every boot after the first, the
`ALTER TABLE` is retried, fails as a duplicate column, and the `catch` executes
**`continue`** — skipping the index step entirely. A workspace that had duplicate
active accounts when the migration first ran (where the index is deliberately
skipped, correctly) therefore **never gets the protection, even after the data is
put right**:

| | SQLite | MariaDB |
|---|---|---|
| index built on a clean first boot | yes | yes |
| index withheld while duplicates exist | yes (correct) | yes (correct) |
| **index built after the duplicates are resolved** | **NO — never recovers** | **YES — recovers** |

Proved by resolving the duplicate and booting three more times.

## 11 · Database schema evidence

`partner_contacts` (both engines): `id, partner_id, address_id, name,
designation, department, email, mobile, phone, is_primary, project`.
Indexes — SQLite: `ix_pcont_partner` (non-unique). MariaDB: `PRIMARY(id)`,
`ix_pcont_partner` (`Non_unique=1`). **No unique constraint, no trigger, no
foreign key involved in the invariant.**

`client_users`: `uq_active_email varchar(200) … VIRTUAL GENERATED`, `Key=UNI`,
index `ux_client_users_active_email`, unique — present on both engines.

## 12 · Git provenance

| Finding | Introducing commit | Classification |
|---|---|---|
| **A1** | `c3d558d` (Batch 3 implementation) | **INTRODUCED BY BATCH 3** — the rule and its enforcement are Batch 3's; the *absence* of a constraint on `partner_contacts` is older, but before Q22 there was no rule to break |
| **A2** | `c3d558d` | **INTRODUCED BY BATCH 3** (the key's definition) |
| **A3** | `c3d558d` | **INTRODUCED BY BATCH 3** — as a side-effect of the protection. Before it, *both* cases succeeded, which was a worse defect |
| **A4** | `c3d558d` | **INTRODUCED BY BATCH 3** (the refusal audit) |
| **A5** | `b963490` (Phase 2) | **PRE-EXISTING**, **EXPOSED BY BATCH 3** — the detector never filtered status; Batch 3 wired it to a public route |
| **A6** | `c3d558d` | **INTRODUCED BY BATCH 3** |

Batch 3's adversarial pass discovering them does not make them Batch 3's — but
the history says that, for five of six, they are.

## 13 · Why the previous gates did not detect these

Not a failure of effort — a set of assumptions, each identifiable:

| Finding | The assumption that let it through |
|---|---|
| **A1** | **every G-series assertion is sequential.** Batch 3 *did* test concurrency — on `/join` and on portal invites — but never on contacts. Mutation testing mutates code, not schedules: no mutant can expose a missing lock. And no assertion ever asked the database for a constraint |
| A2 | the H-series tested exact repeats and case, and stopped there; no test stated the key's normalisation contract, so no test could notice it was narrower than the prose |
| A3 | `A6` asserted the *message* discloses nothing — true, and still true. Nobody compared the two **outcomes**: size, timing, side effects |
| A4 | `I3` asserted a refusal **is** audited, treating the write as a feature. No test asked *who can cause it* or *who can see it* |
| A5 | every fixture was `ACTIVE`; a varying-status fixture was never built |
| A6 | the migration was tested on a **clean first boot**. Nothing re-ran it against an existing generated column, and nothing asserted recovery after remediation |

The pattern: **the gates proved the happy path of each new rule, and the tests
inherited the implementation's own mental model.** Concurrency, adversarial
observation, and second-boot behaviour sat outside that model.

## 14 · Security assessment

* **A4 is the security finding**: an unauthenticated, unthrottled write into a
  named customer's business history, visible on the customer's own dashboard,
  with display eviction at 8 rows. No authentication, no rate limit, no cost.
* **A3** is an information-disclosure channel of moderate value: it reveals
  whether an organisation is already known to the business.
* **A2** is *not* exploitable for account takeover or sign-in ambiguity, and the
  audit says so plainly rather than inflating it.
* Tenant isolation, authorisation and scope were **not** affected by any finding;
  every probe stayed inside one workspace.

## 15 · Data-integrity assessment

* **A1 breaks an approved invariant on the authoritative engine.** "The primary
  contact" becomes ambiguous, and every reader that takes the first primary row
  gets a non-deterministic answer.
* **A6** leaves the account-boundary protection permanently absent on SQLite in
  exactly the workspaces that had something to fix.
* A2 admits rows that are unreachable but countable — they inflate "active
  accounts" without being usable.
* A5 has no integrity impact; it is a policy question.

## 16 · Concurrency assessment

Only A1 is a concurrency defect. Its shape is the one this project keeps meeting:
**check-then-write with no database invariant underneath.** Batch 3 solved it
correctly for accounts (a generated key plus a unique index) and did not apply
the same reasoning to contacts. Nothing else audited here is timing-dependent.

## 17 · Proposed remediation options — evaluated, **not implemented**

### A1 · the proposed live-primary key — **tested on both engines, it works**

A scratch table was built with historical rows *including a pre-existing double
primary*, to see what a real workspace would meet:

| Step | SQLite 3.45.1 | MariaDB 10.11.14 |
|---|---|---|
| `ADD COLUMN uq_primary … GENERATED ALWAYS AS (CASE WHEN is_primary=1 THEN partner_id ELSE NULL END) VIRTUAL` | OK | OK |
| `CREATE UNIQUE INDEX` over data containing a double primary | **REFUSED** (as it should be) | **REFUSED** (1062) |
| the same index after resolving that one row | OK | OK |
| a second primary for one organisation | **refused by the database** | **refused by the database** |
| many *non*-primaries for one organisation | accepted — NULLs do not collide | accepted |
| a writer supplying the key itself | **rejected** ("cannot INSERT into generated column") | not independently observed — the probe collided on uniqueness first |

Alternatives, for completeness: a transaction plus `SELECT … FOR UPDATE` (works
on MariaDB, no equivalent on SQLite, and leaves the rule in code where a writer
can forget it); or application-level serialisation (weaker still). **The
generated key is the only option that no writer can bypass and no schedule can
beat**, and it is the technique Batch 3 already proved.

### A2 · `LOWER(TRIM(email))`
One-word change to the generated expression. Requires rebuilding the column and
index; existing padded rows would then collide with their clean twin, so the
same skip-and-report treatment as the original migration is needed. Optional.

### A3 · options, none chosen
(a) leave as is and document; (b) make the success path indistinguishable
(always answer "check your e-mail", create nothing until a link is followed);
(c) route every registration through the controlled claim/access flow; (d) add
deliberate cost — throttling or a delay — so the channel is expensive rather
than closed.

### A4 · options, none chosen
(a) keep it in the customer's history (status quo); (b) write refusals to a
system/security stream that staff can read and customers cannot; (c) keep the
customer-visible entry but only for the **first** refusal in a window, with the
rest counted; (d) rate-limit the route so the primitive is bounded.
Whatever is chosen, **the security evidence should be preserved**.

### A5 · confirm intent, then encode it
Either filter the detector on status, or state that a closed organisation keeps
its identity and say so where a refused applicant can be told something useful.

### A6 · use a column probe that sees generated columns
`PRAGMA table_xinfo` on SQLite, or make the index step unconditional and let its
own `CREATE UNIQUE INDEX … IF NOT EXISTS` decide. Either way the `continue` that
skips the index must not be reachable merely because the column already exists.

## 18 · Migration considerations

* Additive and idempotent in every case; no historical row need be rewritten.
* **A1 and A2 both meet the same obstacle**: a real workspace may already violate
  the new key. The established Batch 3 pattern applies — add the column, attempt
  the index, and on refusal **skip it and report the offending rows** rather than
  "cleaning" customer data. §NO HISTORICAL CLEANUP still governs.
* **A6 must be fixed first, or A1 and A2 inherit it**: any new generated column
  will be invisible to `t_cols_of()` on SQLite for exactly the same reason.
* Callers: the insert currently returns an id or 0. With a unique index it can
  also throw a 23000, which `partner_contact_add()` does not yet translate —
  that conversion is part of the work, not an afterthought.

## 19 · Tests required for a future implementation

Smallest additions that fit the existing architecture — **no new framework, no
new mutation engine, no new detector**:

1. A concurrency probe for contacts, modelled on `_p6b3_worker.php`, with the
   harness markers used in this audit (**PASS / FAIL / INVALID EXPERIMENT**).
2. A schema assertion that the invariant exists **in the database**, not merely
   in code — the assertion class that was missing everywhere.
3. A migration-recovery test: duplicates present → index withheld → duplicates
   resolved → **index built on the next boot**, on both engines (A6).
4. A normalisation table for the account key, listing each whitespace and case
   variant and the expected verdict (A2).
5. An observability test for the public route: two outcomes, one body signature —
   or an explicit, documented acceptance that they differ (A3).
6. A visibility test asserting who can see a refusal record (A4).
7. A status matrix for the detector (A5).
8. Mutants for each: drop the index; widen the key; restore the `continue`.

## 20 · Owner decision matrix

| | Question | Options | My recommendation |
|---|---|---|---|
| **Q24** | Enforce the one-primary invariant in the database? | (a) generated `uq_primary` + unique index *(proved on both engines)*; (b) transaction + `FOR UPDATE` *(MariaDB only)*; (c) accept and downgrade Q22 to "best effort" | **(a)** — it is the only one a writer cannot forget |
| **Q25** | Make the account key `LOWER(TRIM(email))`? | (a) yes, rebuild column + index; (b) no, and reword the claim to "one per exact lower-cased address" | either is defensible; **(a)** is one word and makes the prose true |
| **Q26** | Should the public route stop distinguishing existing from new? | (a) leave and document; (b) uniform response, nothing created until verified; (c) always route through the claim flow; (d) add cost | needs a product decision — **(a) or (d)** are cheapest |
| **Q27** | Where do refused public registrations belong? | (a) customer history *(today)*; (b) system/security stream; (c) first-only + counter; (d) rate-limit | **(b) with (d)** — keep the evidence, take away the primitive |
| **Q28** | Should a closed organisation keep its duplicate namespace? | (a) yes *(today)*; (b) eligible for controlled re-registration; (c) status-specific rules | owner's call; whichever is chosen, **encode and document it** |

## 21 · Explicit non-changes

```
No product code changed.
No schema changed.
No index added.
No route, permission or authentication changed.
No Batch 1 change.
No Batch 2 change.
No Batch 3 implementation change.
No Recruitment change.
No Batch 4 work started.
git diff aef8974 HEAD -- phpapp  →  0 lines
```

All experimental probes lived outside `phpapp/` and were removed; the shipped
test suite is untouched.

## 22 · Final recommendation

> ## REOPENED FOR CORRECTIVE IMPLEMENTATION

Three findings justify this, and only the owner can authorise the work:

1. **A1** — an approved invariant (Q22) does not hold on the **authoritative**
   engine, reproducibly, in code Batch 3 introduced.
2. **A4** — an unauthenticated stranger can write into a paying customer's own
   dashboard and push their real activity out of view.
3. **A6** — the account protection Batch 3 built **never recovers** on SQLite in
   the workspaces that most needed it.

A2, A3 and A5 do **not** require reopening on their own: A2 is a wording issue
with an optional hardening, A3 is an accepted trade-off that should be stated
plainly, and A5 is a policy question.

Nothing here reflects on Batch 1, Batch 2 or the Recruitment fix, and none of it
was reachable from the evidence the gates produced — which is the point of
running this pass after them rather than instead of them.
