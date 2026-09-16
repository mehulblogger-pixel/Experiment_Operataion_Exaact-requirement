# Phase 3 · M3 CORRECTION #6 — MUTATION RESULTS

Every figure below was produced by a run executed **against the final source**,
from a **clean baseline**. Nothing is carried forward from an earlier correction.

Method: `phpapp/` is copied to a scratch directory, one mutation is applied to the
copy, `tools/make_deploy_check.php` is regenerated, and the suites `p3m`,
`recruit_approval` and `m4_` are run. A mutation is **CAUGHT** when failures
exceed the baseline; a suite that dies without printing `RESULT:` counts as a
detection. The repository itself is never mutated.

---

## Two honesty notes on the first attempt

The first run of this battery is **not** reported here, for two reasons, both of
which were fixed and the whole battery re-run:

1. **The baseline was dirty** — `baseline failures: 1`. The J1 change had broken
   an assertion in the D2 suite (see below), so every verdict was being measured
   against a failing floor.
2. **One mutation never executed.** `J1-G` anchored on a two-line fragment that is
   identical in both audit writers, so it matched twice and was skipped with
   `ANCHOR-MISS (2)`. A skipped mutation is not a caught mutation.

The re-run reported below has `baseline failures: 0` and no anchor misses.

### The baseline failure was real, and is fixed by strengthening

```
FAIL  R7 · D2-5 · every audit row that IS written points at a supported, traceable entity  (want 0, got 1)
```

The D2 suite hard-coded the acceptable subjects as `('HIRING_REQUEST','REQUISITION')`,
written before an unlinkable source entity could be filed under its governing
approval policy. Adding `APPROVAL_POLICY` to that list **on its own would be a
weakening**, so the assertion was strengthened in the same edit: being on the list
is no longer sufficient — the row must point at a record that still exists.

That check needed a watermark. Rows written by *earlier* test files reference
fixtures those files have since cleaned up; they are not dangling, because the
record existed when the row was written. The openability check is therefore
confined to rows the file itself created.

---

## J1 battery — 8 attempted, 8 caught, 0 survivors

| # | Mutation | Result | Failures |
|---|---|---|---:|
| J1-A | The pre-correction behaviour restored: the notifier logs against the source record whatever its state | **CAUGHT** | 64 |
| J1-B | The reference check asks only whether the TYPE is supported (D2's original rule) | **CAUGHT** | 25 |
| J1-C | An absent or cross-tenant record satisfies the audit reference | **CAUGHT** | 21 |
| J1-D | The subject chooser always returns the source entity, dangling or not | **CAUGHT** | 61 |
| J1-E | The fallback subject is an unsupported entity kind | **CAUGHT** | 74 |
| J1-F | The fallback subject is a supported type with an unchecked id | **CAUGHT** | 15 |
| J1-G | The SLA writer goes back to logging the raw entity with no check | **CAUGHT** | 71 |
| J1-H | A live source record is described as unavailable (the misleading event, §2) | **CAUGHT** | 2 |

Mapping to the brief's required mutations: **J1-A** is "restore audit logging
against missing source record"; **J1-B** is "check only entity type and not target
existence"; **J1-C** is "allow cross-tenant source to satisfy audit reference";
**J1-D** is "create dangling supported entity reference"; **J1-E** and **J1-F** are
"replace valid fallback audit subject with unsupported/dangling entity" — split in
two because the fallback can fail in two distinct ways, an unsupported *type* and
an unchecked *id*, and one row could not have represented both.

**J1-G** and **J1-H** are additions of my own. J1-G exists because the sibling call
site (`appr_audit_sla`) is where the rule was most completely absent, and a
mutation battery that only attacked the reported call site would have repeated the
very pattern these corrections keep finding. J1-H exists because the fix introduces
a sentence — *"source record unavailable"* — that can itself become a lie.

## G1 battery — 10 attempted, 10 caught, 0 survivors

Re-run in full against the final source, as required. The figure previously
reported (10/10) belonged to an earlier version of the suite and is superseded by
this one.

| # | Mutation | Result | Failures |
|---|---|---|---:|
| G1-A | The HIRING_REQUEST-only rule restored | **CAUGHT** | 40 |
| G1-B | A missing OFFER record returns eligible | **CAUGHT** | 14 |
| G1-C | A missing SALARY record returns eligible | **CAUGHT** | 13 |
| G1-D | A missing REQUISITION record returns eligible | **CAUGHT** | 13 |
| G1-E | The entity TYPE is treated as sufficient | **CAUGHT** | 40 |
| G1-F | A cross-tenant/absent source record satisfies resolution | **CAUGHT** | 120 |
| G1-G | An informational notification proceeds despite an unresolved entity | **CAUGHT** | 31 |
| G1-H | A missing entity falls through to `appr_can_act()` as a rescue | **CAUGHT** | 56 |
| G1-I | The resolver's failure is swallowed instead of denying | **CAUGHT** | 2 |
| G1-J | Identity resolution no longer requires the source record | **CAUGHT** | 3 |
