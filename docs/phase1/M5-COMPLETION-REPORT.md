# Milestone 5 — Completion Report

## Verdict: **PASS WITH DOCUMENTED LIMITATIONS**

M5 met every acceptance criterion, including the mandatory S-1 scenario, with a
fully green suite and no weakened tests. The qualifier is not a reservation
about the work delivered — it records seven boundaries that M5 did not set out
to close and did not close, listed in `M5-KNOWN-LIMITATIONS.md`. None is a
regression; each existed before M5.

---

## 1. What was asked, and what happened

| Item | Asked | Delivered |
|---|---|---|
| **A** | Fail closed on unsafe/unmapped runtime routes | Done — `ops_module_family()`, consulted only when the explicit map is silent |
| **B** | Protect the Careers route with HR entitlement | Done — asked once, in `careers_enabled()`; opt-in preserved |
| **C** | Fix the genuine master bypasses (31 sites) | Done — 37 sites scoped via the existing `is_master_of()`; **the prescribed mechanism was corrected, see §2** |
| **D** | Make unowned access modules fail closed | Done — with the verified `admin` product-key exception |
| **E** | M5 tests and documentation | Done — 76 assertions, 5 documents |

---

## 2. One correction to the instruction, and why

Item C specified changing `is_master() || can(...)` to `can(...) || is_master()`.

**That reorder does not close the bypass.** `is_master()` is a single flag read
(`ua()['master']`) with no knowledge of licensing. When a company has not bought
a module, `can()` returns false — and `is_master()` then returns true anyway.
The result is identical before and after the reorder. The change would have
looked like a fix and delivered none.

What closes it is asking the entitlement question about the module in play. The
codebase already had the helper for exactly that — `is_master_of()`, meaning
*"a master, but only for a module this installation actually has."* All 37 sites
now use it, which satisfies the stated goal:

> Paid module + entitled + master → normal access
> Paid module + NOT entitled + master → **DENIED**

Nothing new was invented; no second entitlement engine was built. This is
reported rather than done silently because the difference between the stated
mechanism and the stated goal is exactly the kind of gap that leaves a security
fix looking complete while achieving nothing.

---

## 3. Acceptance criteria

| Criterion | Result |
|---|---|
| S-1 scenario passes in full | **PASS** — 29 assertions, as a master |
| Operations + Reporting unaffected when entitled | **PASS** — full regression green |
| HR / Sales / Money denied when not entitled | **PASS** — incl. to a master |
| Master + non-entitled module denied | **PASS** |
| Public careers page closed without HR | **PASS** |
| Careers page not made mandatory | **PASS** — the setting still decides |
| No existing test weakened, skipped or deleted | **PASS** — zero changes, zero skips |
| Full suite green | **PASS** — 7,474 / 0 failed |
| No schema change | **PASS** |
| No second entitlement/RBAC/routing engine | **PASS** |
| No `hr` rename | **PASS** |
| Protected files respected | **PASS** |
| Control install never locked out | **PASS** — asserted for all six modules |
| No sensitive information exposed or logged | **PASS** — refusal messages name the module, never licence internals, credentials or connection details |

---

## 4. The commercial outcome, in business terms

Before M5, the entitlement engine could answer correctly but the application did
not always ask. A customer on an Operations-only plan could reach hiring screens
through an unlisted address, publish a public jobs page for a module they had
not bought, and — if they were an administrator — open essentially everything.

After M5, what a company has paid for is what a company can reach. The rule is
the same for everyone, including the administrator, and it is applied at one
place rather than trusted to 37 individual screens remembering to ask.

Nothing changes for a customer who has paid: the same regression suite that
proves the denials also proves the permitted screens still open.

---

## 5. Verification record

- Full suite: **7,474 passed, 0 failed, 0 skipped** (baseline 7,398).
- New: `tests/test_m5_runtime_entitlement.php` — 76 assertions, every group
  paired DENY + ALLOW.
- `php -l` clean on all 13 changed files.
- `deploy-check.php` regenerated; its staleness guard had correctly flagged the
  changed libraries beforehand.
- Environment: SQLite. **MySQL was not available and was not tested** — see
  `M5-TEST-RESULTS.md` §2 and limitation L6.

---

## 6. Documents

| Document | Contents |
|---|---|
| `M5-RUNTIME-ENFORCEMENT.md` | The four holes and how each was closed |
| `M5-ROUTE-ENTITLEMENT-MATRIX.md` | Route → module mapping, family table, ungated routes, S-1 matrix |
| `M5-TEST-RESULTS.md` | Results, environment honesty, regression scope |
| `M5-KNOWN-LIMITATIONS.md` | L1–L7, with recommended fixes |
| `M5-COMPLETION-REPORT.md` | This document |

---

## 7. Recommended next, not started

Listed for the record only. **M6 has not been begun**, per instruction.

1. **L1** — bring non-`mod.*` permissions under the ownership map.
2. **L2** — add a build-time test that fails when a dispatched route is claimed
   by neither the map nor the family table.
3. **L7** — upload to the live workspace and confirm with `deploy-check.php`.
