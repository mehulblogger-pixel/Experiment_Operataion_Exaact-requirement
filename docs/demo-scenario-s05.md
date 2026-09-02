# DEMO-S05 — Convergence & reconciliation (the read-only dual-truth detectors)

The fifth scenario in the progressive program (S01 freelancer · S02 agency ·
S03 client foundation · S04 marketplace lifecycle → **S05 the convergence
detectors**). One namespaced thread that lights up every *read-only dual-truth
detector* built in this revamp with live drifting data, so you can click through
and see each one flagging a real disagreement — and, because every drift is
seeded next to a reconciled control row, see that the detector flags the drift
and leaves the matching row alone.

## Load / remove

Admin → System Settings → **"DEMO-S05 — convergence & reconciliation"** → Load. Or CLI:
```
php tools/seed-scenario-s05.php            # load / refresh (idempotent)
php tools/seed-scenario-s05.php --status
php tools/seed-scenario-s05.php --remove
```
Prints a real 10-point PASS/FAIL dashboard; re-running purges DEMO-S05 first.

## What it seeds, and where to see each detector

| Detector (built this revamp) | Where to see it |
|---|---|
| **Revenue reconciliation** (P9 / §29) | `/revenue-reconciliation` — **DEMO-S05-REV-DRIFT** (legacy ₹50,000 vs ledger ₹20,000) and **DEMO-S05-REV-LEGACY** (₹18,000, no ledger invoice) are flagged; **DEMO-S05-REV-OK** (₹30,000 = ₹30,000) is not. |
| **Cost reconciliation** (P10) | `/cost-reconciliation` — **DEMO-S05-COST-DRIFT** (job subcon ₹40,000 vs committed ledger ₹15,000) and **DEMO-S05-COST-LEGACY** (₹12,000, no committed run) are flagged; **DEMO-S05-COST-OK** (₹25,000 = ₹25,000) is not. |
| **Candidate pool convergence** (P11) | `/candidate-pool` — candidate **DEMO-S05-CAND-1** (*Farhan Qureshi*) is also marketplace professional *Farhan Qureshi*, matched by mobile across a leading-0 difference; **DEMO-S05-CAND-2** has no marketplace twin and does not appear. Also visible as an *"Also on the marketplace"* panel on the candidate detail. |

All three detectors are **read-only** — they surface a disagreement and move no
figure. The client on every seeded record is *DEMO-S05 Meridian Refinery Ltd*.

## Acceptance — 10/10 PASS

The seed asserts each detector from live data (not screens): the revenue-drift and
legacy-only jobs diverge while the reconciled one does not; the cost-drift and
legacy-only jobs diverge while the reconciled one does not; the candidate matches
its marketplace twin by mobile (strong key), the reverse lookup resolves back to
the candidate, and a candidate with no twin matches nothing.

## Existing-system impact

No workflow, table, permission or lifecycle changed. Reuses `revrecon`,
`costrecon`, `candpool`, `costing` (cost ledger) and the books `invoices` /
`invoice_lines`. Every DEMO-S05 record is namespaced (`DEMO-S05-*` codes,
`%s05pro@demo.test`) and removed by `--remove`. Guarded by
`tests/test_s05_convergence_seed.php`; app suite at 5403 passing.
