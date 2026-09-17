# M3 CORRECTION #12 — ADVERSARIAL AUDIT

An attack on my own completed work. Probes ran against a **copy** of `phpapp/`
in the scratchpad; **no product code was modified**. Every claim below is a
measured result on both engines, not a reading of the source.

**Seven findings. One is material and live. One is an overclaim in my own
completion report. One is a mutation that survived. Four are latent traps.**

---

## A-1 · MATERIAL — the entire four-valued contract is never read by the product

Corrections #10, #11 and #12 built a diagnostic surface: `STORED` / `FAILED` /
`UNAVAILABLE` / `NOT_ATTEMPTED`, four workspace-keyed error channels, and a
read-only state reporter. #12 made `STORED` truthful.

**Nothing in the product asks.** The only production caller is one line:

```php
// lib/activity.php:432
if ($id > 0 && $ck !== '') act_set_cond_key($id, $ck);   // status ignored
```

Searched across every non-test `.php` file:

| symbol | product callers outside `lib/activity.php` |
|---|---|
| `ACT_COND_STORED` / `_FAILED` / `_UNAVAILABLE` / `_NOT_ATTEMPTED` | **0** |
| `act_optional_error()` | **0** |
| `act_optional_state()` | **0** |
| `act_cond_column_error()` / `act_cond_write_error()` | **0** (added by #12) |

### Why this is not merely cosmetic

`appr_condition_seen()` — the idempotency guard — asks the database directly:

```php
return (bool) ops_one("SELECT id FROM activities WHERE cond_key=?", [$key]);
```

So when the `cond_key` write fails and the status is discarded, the guard cannot
find the key, concludes the condition has never been seen, and **records the
permanent-condition event again**. That is K1/H2 returning through a different
door: the suppression #7 built silently stops suppressing, and nothing anywhere
reports that it has.

**Verdict:** #12 correctly made the answer truthful. It did not make anyone
listen. The correction's business value is currently confined to the test suite.

---

## A-2 · OVERCLAIM IN MY OWN REPORT — a colliding id is not caught

COMPLETION-REPORT §4 states: *"A write addressed to an id owned by another
workspace reports FAILED."* That is true **only when that id is absent from the
current workspace**, which is the only case C12.3 tried.

EXAACT is one-database-per-tenant, so the id sequence restarts in every
workspace: **the same number routinely names a real, different record in each
one**. Probe P2 built exactly that and ran it on both engines:

| step | result (SQLite and MariaDB alike) |
|---|---|
| A and B are genuinely different databases | confirmed by the database naming itself |
| B owns its own record at the same id | confirmed |
| a caller in B writes using an id obtained from A | **reports `STORED`** |
| what actually happened | **B's unrelated record was stamped with A's key** |
| A's own record | untouched — the damage is entirely inside B |

The record id was taken as proof of which record was meant. It is not. The
standing rule — *never treat a record ID as proof of authorization* — is
satisfied for *isolation* (nothing crossed the tenant boundary) but not for
*identity* (the wrong record inside the tenant was written, and the caller was
told it succeeded).

**Not reachable through today's only caller**, which passes an id it obtained
from `lastInsertId()` microseconds earlier on the same connection. It is a trap
for the second caller, and the completion report should not have implied
otherwise. **The wording is the defect here; I am recording it rather than
quietly softening it.**

---

## A-3 · LATENT — `STORED` inside a transaction that is later rolled back

The read-back runs on the same connection, so it sees **uncommitted** data.
Probe P1, both engines:

- inside the transaction: `STORED`, and the read-back genuinely sees the value;
- after `rollBack()`: **the value is gone**, and `STORED` was already returned.

The contract says *"actually present on the intended record"*. After a rollback
it is not. Not reachable today — the nearest transaction in the codebase
(`lib/idems.php:3741`) spans nine lines and contains no `act_log()`, and the
other four are backup, seeding and tenant migration — but the contract is
stronger than what the implementation can guarantee, and that gap is not
recorded anywhere in #12's documents.

---

## A-4 · LATENT — the same input gets a different verdict on each engine

Probe P3, a 400-character key against `VARCHAR(160)`:

| engine | result |
|---|---|
| MariaDB (strict mode off) | silently truncated to 160 → read-back mismatch → **`FAILED`** |
| SQLite (ignores declared lengths) | stored in full → **`STORED`** |

**This is the X1 fix doing real work**, and worth saying plainly: neither
`rowCount()` nor an exception would have caught the MariaDB truncation. But the
two engines now disagree about the same input, and #12 documented the fix as
"engine-independent by construction". It is engine-independent in *method*, not
in *outcome*. Not reachable through `act_log()`, which pre-truncates to exactly
160.

---

## A-5 · LATENT — `act_log()` truncates the key by BYTES, not characters

`substr(trim($opt['cond_key']), 0, 160)` counts bytes. Probe P5 used a
Devanagari key: the cut lands **mid-character**, and the result is no longer
valid UTF-8.

| engine | result for the mangled key |
|---|---|
| MariaDB | **`FAILED`** — so that condition key can never be stored, and (per A-1) nobody is told |
| SQLite | `STORED` — the invalid bytes are kept |

**Not reachable today**, and the protection that remains is real and evidenced:
`appr_condition_key()` emits a fixed ASCII format —
`PC|DECISION|OFFER|12|R3|S0|ENTITY_UNRESOLVED` — from a closed set of reasons,
always far under 160 bytes. It is a trap for the first key that ever carries a
person's name, a department or a job title, which for an Indian SaaS is a matter
of when, not whether.

---

## A-6 · A MUTATION THAT SURVIVED — the suite does not pin strict comparison

`M-X1-D` weakens the read-back's `!==` to `!=`:

```
M-X1-D (loose ==) against the M3 suites → 1469 passed, 0 failed → SURVIVED
```

In PHP 8 two **numeric** strings compare numerically, so `'1e2' == '100'` is
true: a key requested as `1e2` and stored as `100` would report `STORED`.

**Assessed, not excused.** An independent protection does remain — every key
begins `PC|` and is never a numeric string — but that is a property of *today's
key generator*, not of `act_set_cond_key()`, which accepts any string from any
caller. The honest conclusion is that **my suite has a gap**: 54 assertions
about persistence, none pinning that the comparison is exact.

---

## A-7 · DESIGN — one channel still hides another

`act_optional_error()` returns `col ?: write`. Probe P6: with both channels
full, the write failure is invisible. #12 added per-channel readers but left the
combined one as the only composite — and, per A-1, it has **zero** product
callers, so no screen sees either message today.

---

## What survived the attack

Stated as plainly as the failures:

- **X3 holds.** The workspace switch is real. A, B and C are different databases,
  proved by asking the database its own name, and a row written in A is invisible
  in B. No channel carried a message across a real switch in either direction on
  either engine.
- **The four channel mutations are genuinely caught**, each by its own channel's
  assertion with its own message in the failure text — verified individually,
  including `C12.4 core` failing on its own (52 passed, 2 failed) rather than
  leaning on the #11 suite.
- **The read-back beats both alternatives it replaced.** A-4 is the proof: it
  catches a silent MariaDB truncation that `rowCount()` and exception-handling
  would both have called success.

## A probe of mine that was invalid, and what it actually tested

P2's first version ran `DELETE FROM activities` in a workspace that had never
been migrated. It died on `no such table: activities` — so it tested **schema
bootstrap in a fresh workspace**, not id collision. It was not discarded: B is
now bootstrapped through `act_log()` (which migrates) and the colliding id is
set explicitly, which is also engine-independent — `DELETE` rewinds SQLite's
rowid but not MariaDB's `AUTO_INCREMENT`, so the original filler-loop approach
would have produced a different id on each engine and quietly tested nothing on
one of them.

---

## The pattern, again

Every correction from #8 onward has been the same shape: **a rule applied to the
reported instance and not to its siblings.** #12 continued it. The sibling this
time is not another channel or another observer — it is **the caller**. I proved
the answer is now true and never checked whether anyone reads it.

Ranked for whoever writes the next correction prompt:

| # | finding | live? | severity |
|---|---|---|---|
| A-1 | the four-valued contract has no reader; a failed key silently re-arms duplicate events | **yes** | **high** |
| A-2 | a colliding id stamps the wrong record and reports `STORED`; my report overclaims | no (one caller) | **high if a second caller appears** |
| A-6 | mutation survived — strictness of the comparison is unpinned | test gap | medium |
| A-3 | `STORED` survives a rollback | no | medium |
| A-5 | byte-truncation mangles any non-ASCII key | no | medium |
| A-4 | engines disagree on an over-long key | no | low |
| A-7 | the column channel masks the write channel | no (no readers) | low |

Nothing here was fixed. No product code was changed during this audit.
H1, H3, S2, S3 and U2 remain open. M3 is not accepted; M4 is not started.
