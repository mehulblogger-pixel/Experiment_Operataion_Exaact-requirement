# Phase 3 · M3 CORRECTION #7 — COMPLETION REPORT

**Scope: one reusable rule for permanent-condition events (K1, K2, H2), plus the
K3 architectural decision.** H1 and H3 were not touched. No M4 work.

---

## 1 · The rule

> **A permanent unresolved condition is a STATE, not a new EVENT on every
> observation.**

One helper, one place, called by every writer in the module:

```
OBSERVATION → CLASSIFY → STABLE IDENTITY → ALREADY RECORDED? → RECORD
```

K1 (decision path) and H2 (scheduler path) are the same rule missing twice, so
they are fixed once rather than patched twice.

## 2 · Classification (§2)

**PERMANENT** — `ENTITY_UNRESOLVED`, `TENANT_MISMATCH`, `IDENTITY_UNRESOLVED`.
The test is not *"did it fail twice"* but *"can re-asking change the answer without
the underlying data changing?"* — and when the data does change, the reason changes
with it, producing a different key and a new event.

**TRANSIENT** — everything else, explicitly **not** suppressed: a person is
reactivated, a module is bought, an address is added, a provider recovers.

## 3 · Identity (§3/§4)

`PC | event | entity | entity_id | R<chain> | S<step> | reason`

Deliberately **not** `rule_id` — two dead offers under one policy are two
conditions, and a chain with no rule still has an identity. Deliberately **no
timestamp**. Tenant is the connection, not a column. Stored in a new additive,
nullable `activities.cond_key` on the existing spine, so the duplicate question is
one exact-match query — **no second event engine**, and no deduplication derived
from display prose.

## 4 · K3 — the architectural decision, re-priced

The correction #6 report described "no openable subject → no row" as a narrow
legacy case. **It was not.** `appr_tick()` builds its own request row and never
carried `rule_id`, so for the **scheduler that branch was the normal case**: after
correction #6, every orphaned chain's SLA events were dropped entirely. H2 looked
fixed because its history was being thrown away.

**Decision, in order of preference:**

1. the **source record**, when it is openable here;
2. else the **approval policy governing the chain** — `APPROVAL_POLICY`, already on
   the spine, opened by an existing route, proved through `act_link()`;
3. else **do not persist the event**, and never fabricate a reference.

The scheduler now carries `rule_id`, so step 2 is reachable on that path and step 3
is genuinely a last resort. Mutation **L9b** exists specifically to stop step 3
quietly becoming the default again.

## 5 · Evidence

| | |
|---|---|
| **New suite `p3m3c7_idempotent`** | **86 / 0**, both engines |
| **All M3 suites (`p3m`)** | **1208 / 0** |
| **Full regression · SQLite** | **10 025 passed, 0 failed** |
| **Full regression · MariaDB 10.11.14** | **10 026 passed, 0 failed** |
| **Mutations** | **12 attempted, 12 caught, 0 survivors**, clean baseline, no anchor misses |

### K1, measured with the instrument that found it

| Entity | Before #6 | After #6 | **After #7** |
|---|---:|---:|---:|
| Hiring Request | 10 | 10 | **1** |
| Requisition | 10 | 10 | **1** |
| Offer | 0 | 10 | **1** |
| Salary | 0 | 10 | **1** |

Offer and Salary were 0 before correction #6 because the event was **dropped**, not
suppressed — so this is not a return to the old silence. The requirement was
**valid event + valid reference + idempotent recording**, and that is what the
figures show.

## 6 · §15 acceptance criteria

| | |
|---|---|
| K1 behaviourally fixed | ✅ 10 → 1 on all four entities |
| K2 proven with both fixtures | ✅ `C7.4 A` and `C7.4 B`, each asserting its fixture first |
| H2 fixed by the same central rule | ✅ `C7.5`, three runs, clock advanced, `acted > 0` each time |
| initial legitimate event retained | ✅ `C7.3` asserts the first row separately; mutation L5 |
| repeated observation creates no duplicate | ✅ `C7.3`, `C7.4`, `C7.5` |
| genuinely changed condition can create a new event | ✅ `C7.6`; mutation L6 |
| transient conditions not suppressed | ✅ `C7.7`; mutation L6b |
| K3 decision documented | ✅ §4 above |
| `act_link` / openability proven | ✅ `C7.8 Q` |
| no dangling entity references | ✅ `C7.8`; mutations L7, L8, L9 |
| tenant isolation proven | ✅ `C7.8 N`; mutation L8; `C5.7` two-database proof unchanged |
| fail-closed behaviour proven | ✅ `C7.8 L–P` |
| previous M3 corrections green | ✅ `p3m` 1208 / 0 |
| M1 / M2 approval tests green | ✅ within the above |
| Operations / Marketplace / Money / Quality green | ✅ within the full regression |
| SQLite passes | ✅ 10 025 / 0 |
| MariaDB passes | ✅ 10 026 / 0 |
| mutation results honestly reported | ✅ including the two mutations I added |
| fixtures clean up | ✅ asserted by the suite |
| tree clean | ✅ |
| H1 / H3 limitations identified | ✅ §7 below |

## 7 · What this correction did NOT do (§14 scope lock)

**H1 and H3 remain open and were not touched.**

* **H1** — a direct call to `appr_act()` still approves an orphan chain and reports
  "fully cleared".
* **H3** — `appr_sla_summary()` still counts orphans in its tiles.

Neither is affected by this change: correction #7 alters **how often a condition is
recorded**, not what a decision does or what the dashboard counts. They remain
separately tracked and will need their own explicit acceptance.

---

**PHASE 3 — M3 CORRECTION #7 COMPLETE — HARD STOP — READY FOR ADVERSARIAL AUDIT**
