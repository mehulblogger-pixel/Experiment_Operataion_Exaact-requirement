# RB-3 · Step 2 — Test evidence

**Working commit (before mutation testing):** `eff3e5b`
**Engines:** MariaDB 10.11.14 (authoritative) · SQLite 3.45.1 (supplementary)

---

## 1. Regression

| Engine | Result |
|---|---|
| **SQLite 3.45.1** | **12,980 passed · 0 failed** |
| **MariaDB 10.11.14** | **12,983 passed · 0 failed** |

No skips — the harness has no skip facility. `php tools/make_deploy_check.php`
re-run; the shipped-code checksum matches.

`tests/test_rb3_step2_dupmatch.php` — **88 assertions on SQLite, 89 on MariaDB**
(the extra one is the concurrency claim that only the production engine can
demonstrate).

## 2. The acceptance matrix

| ID | Scenario | Expected | Result |
|---|---|---|---|
| **X1** | no duplicate | continue | **pass** — and no tick is even offered |
| **X2** | strong duplicate (mobile) | warning | **pass** — one strong match, basis named, number masked |
| **X3** | duplicate, no acknowledgement | reject | **pass** — refused, nothing written, nothing converted |
| **X4** | duplicate, valid acknowledgement | continue | **pass** |
| **X5** | name-only similarity | must not block | **pass** — Suresh Kumar does not block Suresh Kumar |
| **X6** | same name, different mobile | per the rules | **pass** — weak, proceeds |
| **X7** | same mobile, different name | per the rules | **pass** — strong, refused |
| **X8** | tick reused on another application | reject | **pass** — see §4, this probe had to be rebuilt |
| **X9** | tick reused after the evidence changed | reject | **pass** |
| **X10** | forged tick posted directly | reject | **pass** — including `"1"`, the shape the old hidden field used |
| **X11** | tick from another user | reject | **pass** |
| **X12** | cross-tenant duplicate | must never appear | **pass** — two real separate databases |
| **X13** | cross-tenant tick | reject | **pass** — and tenant A's own tick still works |
| **X14** | concurrent inspection | correct final state | **pass** — engine-aware, see §5 |
| **X15** | a person who has left | continue | **pass** — a leaver is history, not a duplicate |
| **X16** | genuine re-hire | acknowledgement required | **pass** — and it really is a **second** record; nothing merged |
| **X17** | audit entry | pass | **pass** — names *which* matches were shown, not merely that a box was ticked |
| **X18** | unauthorised user | reject | **pass** — see §4, this probe was broken |
| **X19** | CSRF failure | reject | **pass** — central, unconditional, every POST |
| **X20** | existing recruitment flow | pass | **pass** — full regression, both engines |

**Added beyond the required matrix**

| ID | Scenario | Result |
|---|---|---|
| **X-scope** | a match in a branch the recruiter cannot open | **counted, never named — and still blocks.** Not being able to see it is not a way round the gate |
| **X-stale** | a correctly signed but expired tick | rejected |
| **X-future** | a tick dated in the future | rejected |
| **J1–J7** | nothing else moved | the three existing duplicate checks, Step 1's employee-number rule, the allocate picker — all unchanged; **exactly one signing key in `settings`, and no acknowledgement table anywhere** |

## 3. Mutation — 14 of 14 caught

Fresh copy of the whole repository and a fresh database per mutant. **FATAL is
not a catch. ANCHOR-MISS is not a catch. A dirty baseline aborts.** Baselines
verified clean first: SQLite 88/0, MariaDB 89/0.

| | Mutant | Result | Killed by |
|---|---|---|---|
| **S1** | duplicate detection removed | **CAUGHT** | X2 (42 assertions) |
| **S2** | the acknowledgement requirement removed | **CAUGHT** | X3 (19) |
| **S3** | acknowledgement validation removed — any tick accepted | **CAUGHT** | X8, X9 (9) |
| **S4** | **candidate binding removed** | **CAUGHT** | X8 — *only after the probe was rebuilt; see §4* |
| **S5** | evidence binding removed | **CAUGHT** | X9 |
| **S6** | the key stops being per-workspace | **CAUGHT** | X13 |
| **S7** | user binding removed | **CAUGHT** | X11 |
| **S8** | the audit event removed | **CAUGHT** | X17 |
| **S9** | server-side enforcement removed — the posted value trusted | **CAUGHT** | X8, X9, X10 |
| **S10** | a shared NAME becomes strong | **CAUGHT** | X5, X6 |
| **S11** | leavers treated as duplicates | **CAUGHT** | X15 |
| **S12** | scope ignored — hidden matches named | **CAUGHT** | X-scope |
| **S13** | the tick never expires | **CAUGHT** | X-stale, X-future |
| **S14** | the mobile stops being masked | **CAUGHT** | X2c |

**CAUGHT 14 · SURVIVED 0 · FATAL 0 · ANCHOR-MISS 0.**

## 4. Three defective instruments — published, not quietly repaired

### D1 · X18 was a SECURITY assertion that proved nothing

It passed in isolation and **failed only in the full suite**. `t_as_nobody()`
restores whatever session existed *before* `t_as_admin()` — which in a
whole-suite run is an earlier file's **authorised** user. So the probe ran as an
administrator, converted successfully, and the assertion that an unauthorised
person is refused was never exercised.

It now builds an unprivileged login explicitly and **asserts
`!is_coordinator_level()` before attempting anything**, then asserts the right is
restored afterwards so the refusal cannot be a broken session masquerading as a
working gate.

*This is the most serious of the three: a green security test is worse than no
security test.*

### D2 · X8 proved the wrong binding, and mutant S4 exposed it

X8 claims to prove that a tick issued for one application cannot authorise
another. The first version gave the two applications **different e-mail
addresses** and converted A **before** trying A's tick on B. Both mistakes hid
what it was measuring: converting A created a second team member on the same
mobile number, so by the time B was tried its duplicate picture had **changed** —
and the refusal came from the *evidence* binding, not the *candidate* binding.

**Mutant S4 — which removes the candidate binding entirely — survived it
untouched.**

Rebuilt: A and B are given **identical** contact details, A's tick is tried on B
**before anything changes**, and the probe now asserts the two evidence
fingerprints are **equal** before drawing any conclusion. With that arming
assertion in place the only difference left between them is which application it
is, and S4 dies.

### D3 · X14 was flaky — one run in six

The three refused workers each write an audit row, and SQLite takes a single
database-wide write lock with no busy timeout, so roughly one run in six the
converting process lost the lock and was honestly told `BUSY`.

That is the engine, not the gate (Step 1 finding **F1**, still open). The claim
is now stated as what is actually true on both engines — *whoever holds the tick
is never refused for lack of one, and whoever does not always is* — and the
stronger "exactly one converted" is asserted on **MariaDB**, where it holds. Six
consecutive SQLite runs and two MariaDB runs after the change: **stable**.

*A fourth, smaller one: `X10a` asserted exactly one strong match where an earlier
acknowledged hire had legitimately created a second. What that probe needs is
that a tick is required at all, not how many people triggered it.*

## 5. Why some claims are proved only on MariaDB

**SQLite cannot stage a write race.** One database-wide write lock, no busy
timeout. Asserting "exactly one converted" there would be asserting the engine
rather than the gate, and would be flaky. MariaDB is the production engine and
is where the concurrency claims are made.

**MySQL ignores trailing spaces in string comparison**, so every whitespace probe
in this programme uses a **leading** space.

## 6. Negative testing

Every one of these was attempted and failed safely: missing tick · forged tick
(`"1"`, `"yes"`, `"on"`, `"true"`, a 64-char hex string, a well-formed but
unsigned token, an empty string) · a tick for a different application · a tick
from a different user · a tick from a different tenant · a tick whose evidence
has changed · an expired tick · a tick dated in the future · a direct call to the
action with no UI involved · an unauthorised actor · a match the actor cannot see.
