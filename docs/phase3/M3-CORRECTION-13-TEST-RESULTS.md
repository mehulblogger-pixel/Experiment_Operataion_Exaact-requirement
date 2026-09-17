# M3 CORRECTION #13 — TEST RESULTS

Suite: `phpapp/tests/test_p3m3c13_consume.php` — **40 assertions**. Every one of
them goes through a **real production caller** (`appr_audit_notify()` or
`appr_audit_sla()`), never through `act_set_cond_key()` on its own. Proving the
helper and stopping there was #12's mistake.

| run | engine | result |
|---|---|---|
| focused §4/§5 | SQLite 3.45.1 | **40 passed, 0 failed** |
| focused §4/§5 | MariaDB 10.11.14 | **40 passed, 0 failed** |
| **full regression** | SQLite | **10326 passed, 0 failed** |
| **full regression** | MariaDB | **10327 passed, 0 failed** |

No test was weakened, deleted or skipped; no skip was introduced.

---

## §4 — no false success, proved in both directions

**STORED path.** `act_last_cond_status()` is `STORED`, the production caller
returns `APPR_COND_RECORDED`, exactly one event is written, and that event
carries its marker.

**FAILED path.** A genuine `BEFORE UPDATE` failure — `RAISE(ABORT)` on SQLite,
`SIGNAL SQLSTATE '45000'` on MariaDB, fired only on a `cond_key` matching
`'PC|%'` — makes the marker write really fail. The suite asserts
`act_last_cond_status() === FAILED` **first**, so the fixture is proved real
before any claim rests on it. The caller then returns `APPR_COND_UNARMED`, and
is separately asserted to return neither `RECORDED` nor `SUPPRESSED`.

## §5 Scenario A — the real duplicate-suppression cycle

Ten identical refusals on the same permanent condition, all through the
production caller: the first writes one event and arms suppression; the other
nine are suppressed; the row count stays at **one**. K1 and H2 stay fixed.

## §5 Scenario B — the marker cannot be persisted

1. the marker write genuinely fails (asserted, not simulated);
2. the caller observes `FAILED` and returns `UNARMED`;
3. the **event itself is still written** — S1 holds;
4. it carries **no** marker, so suppression is genuinely not armed;
5. the repeat is **not suppressed** — nothing is suppressed on the strength of a
   marker that was never written;
6. the repeat reports `UNARMED` again and a second event appears, which is the
   documented fail-safe rather than a silent claim of success.

**C13.4 · recovery.** With the obstruction removed the marker stores, the caller
sees `RECORDED`, and every later identical refusal is suppressed again. The
fail-safe is not a one-way door.

**C13.5 · the sibling.** `appr_audit_sla()` — the *other* production caller —
gets the identical proof rather than being assumed to follow. #12's lesson was
that a rule applied to one instance and not its siblings is not applied.

**C13.6 · no inherited status.** An ordinary `act_log()` that writes no marker
reports `NOT_ATTEMPTED`, never the previous call's `STORED` or `FAILED`.

**C13.7 · the status is workspace-keyed** — see below.

---

## Three defects in my own fixtures, found and reported

**1 · The counter counted the fixture itself.** Creating an approval policy is an
audited act, so each rule already carried one `APPROVAL_POLICY` event before the
first assertion. Absolute row counts were off by exactly one everywhere. Counts
are now **deltas** from a baseline captured after the fixture is built, and the
baseline is itself asserted non-zero.

**2 · The fixture leaked policy into the shared test database.** This suite's
three rules apply to `HIRING_REQUEST` with an empty department — i.e. to *every*
hiring request — and `test_p3m3c13` sorts **before** `test_p3m3c3` and
`test_p3m3c4`. Those suites matched *this* suite's policy instead of their own,
and the full regression died with a null step at `recruit_approval.php:1608`.
**The focused run was green throughout; only the full, ordered run exposed it.**
The cleanup now removes the rules, their levels and the fixture user, and the
suite asserts it leaves no policy behind.

**3 · A gap found by mutation, not by reading.** `M-Y1-GLOBAL` made the new
status slot ignore the workspace and **survived every assertion in the suite**. I
had introduced a per-workspace slot and never asserted that it was one — X2's
finding, repeated on the very slot added to fix a different gap. C13.7 now
switches workspace through the application's real path (#12 · X3), makes the
database name itself before any claim, and proves B cannot read A's status. The
mutation is caught.
