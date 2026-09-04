# DEMO-S06 — Gap-closure showcase (the eight residual gaps)

The sixth scenario in the progressive program (S01 freelancer · S02 agency ·
S03 client foundation · S04 marketplace lifecycle · S05 convergence detectors →
**S06 the gap closures**). One namespaced thread that lights up every gap closed
in the controlled Stage-0 gap-closure program, each asserted from live data.

## Load / remove

Admin → System Settings → **"DEMO-S06 — gap-closure showcase"** → Load. Or CLI:
```
php tools/seed-scenario-s06.php            # load / refresh (idempotent)
php tools/seed-scenario-s06.php --status
php tools/seed-scenario-s06.php --remove
```
Prints a real 10-point PASS/FAIL dashboard; re-running purges DEMO-S06 first.

## What it seeds, and where to see each gap

| Gap (closed) | Where to see it |
|---|---|
| **1 — deployment → engagement/finance spine** | The awarded requirement's deployment job **DEMO-S06-DEP-1** carries a `contract_number` and `engagement_id` — it threads into the engagement grouping, not dangling. |
| **2 — taxonomy in inspector matching** | The inspector "DEMO-S06 Farooq Alam" whose skills say *Zephyrline Inspection* earns a taxonomy bonus against the matching requirement (`/connect-requirement` → matches). |
| **3 — engagement status machine** | A BOOKED→ACTIVE move is allowed; a COMPLETED→BOOKED move is refused. |
| **4 — shift-aware conflict** | The shift-tester professional has a DAY booking on a date; a NIGHT booking the same day is CLEAR, a DAY booking CONFLICTS. |
| **5 — one credential ladder** | The inspector's *ASNT NDT II* cert reads **Verified** on the same Declared→Documented→Verified→Expired ladder the marketplace uses. |
| **6 — near-duplicate requirement** | Posting a *Piping Inspector at Dahej* when one is already open raises the near-duplicate advisory (`/connect-requirements`). |
| **7 — partial billing** | A `/billable-events` timesheet event of ₹1,000 is **part-billed ₹400**, leaving ₹600 open — status still Approved. |
| **8 — unified person** | Candidate **DEMO-S06-CAND** shows *"One person across the platform"* — the same Farooq Alam spans marketplace + operations inspector + recruitment, with credentials from both pools on one ladder. Nothing merged. |

## Acceptance — 10/10 PASS

The seed asserts each gap from live data (not screens): the deployment carries both
spine keys; the taxonomy bonus is positive for the inspector; the invalid transition
is refused and the valid one allowed; the different-shift booking is CLEAR and the
same-shift one CONFLICTS; the inspector cert reads VERIFIED on the unified ladder; a
near-duplicate is detected; the event is part-billed 400 of 1000; and the person
resolves across all three pools with cross-pool credentials.

## Existing-system impact

No workflow, table, permission or lifecycle changed. Reuses `connect_deploy`,
`engagement`, `connect_match`, `connect_engage`, `connect_conflict`,
`connect_credentials`, `connect_reqtools`, `billable` and `connect_person`. Every
DEMO-S06 record is namespaced (`DEMO-S06-*`, `%s06pro@demo.test`) and removed by
`--remove`. Guarded by `tests/test_s06_gap_showcase.php`.
