# M3 CORRECTION #13 — MUTATION RESULTS

Mutations run against a **copy** of `phpapp/` in the scratchpad, never the
repository. Suites per mutation: `p3m`, `recruit_approval`, `m4_`, `activity`.

**Attempted 6 · Caught 6 · Survived 0.** Counts inherited from Corrections
#5–#12 are not repeated and are not claimed as newly caught.

| mutation | verdict | the assertion that detected it, and why |
|---|---|---|
| **`M-Y2-IGNORE`** — `act_log()` discards the status again (§6's required mutation: the exact #12 defect restored) | **CAUGHT** | `C13.1 · *** the PRODUCTION CALLER observed STORED and returned RECORDED ***` — want `RECORDED`, got `UNARMED`. The helper still works; the caller can no longer see it. |
| **`M-Y2-FAILASOK`** — the caller treats `FAILED` as `STORED` (§6's second required mutation) | **CAUGHT** | `C13.3 · *** the caller observed FAILED and did NOT take the stored path ***` — want `UNARMED`, got `RECORDED` |
| `M-Y2-ANYNONEMPTY` — any status at all counts as stored | **CAUGHT** | same assertion — `UNAVAILABLE` and `NOT_ATTEMPTED` may not be credited either |
| `M-Y1-STALE` — `act_log()` no longer resets the slot on entry | **CAUGHT** | `C13.6 · an act_log() that writes no marker reports NOT_ATTEMPTED` — want `NOT_ATTEMPTED`, got `FAILED` |
| `M-Y1-GLOBAL` — the status slot ignores the workspace | **CAUGHT** | `C13.7 · *** B cannot read A's marker status ***` — want `NOT_ATTEMPTED`, got `STORED` |
| `M-Y2-SIBLING` — only `appr_audit_notify()` consumes it; the SLA writer drops it again | **CAUGHT** | `C13.5 · *** the SLA writer also refuses to claim it was stored ***` — want `UNARMED`, got `RECORDED` |

Every mutation is caught by an assertion in the **production-path** suite, and
the failure text names the mutated behaviour in each case. None is credited to an
unrelated test.

`M-Y2-IGNORE` satisfies §6's requirement literally: it survives helper-level
testing — `act_set_cond_key()` is untouched and every #12 assertion about it
still passes — and is caught only because the new suite exercises the caller.

---

## Two earlier runs of this battery were void, and are reported as such

**Run 1 — baseline FATAL.** The battery ran against a tree still carrying the
leaking-fixture defect (TEST-RESULTS defect 2), so the **baseline itself scored a
fatal**. Every mutation therefore "survived" by arithmetic: nothing could score
above a baseline that was already at the ceiling. The run measured nothing and
is discarded as a broken experiment, not as evidence.

**Run 2 — one genuine survivor.** Against a clean baseline, five of six were
caught and **`M-Y1-GLOBAL` survived**. That was a real finding, not an artefact:
the suite had no assertion that the new status slot is workspace-keyed. C13.7 was
written to close it and the battery re-run. The survival is recorded because it
is the evidence that the scoping was previously untested.
