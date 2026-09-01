# DEMO-S04 — Marketplace lifecycle (the new engines)

The fourth scenario in the progressive program (S01 freelancer · S02 agency ·
S03 client foundation → **S04 the full marketplace lifecycle**). One namespaced
thread that lights up every capability built in this revamp, so you can click
through and see them working.

## Load / remove

Admin → System Settings → **"DEMO-S04 — marketplace lifecycle"** → Load. Or CLI:
```
php tools/seed-scenario-s04.php            # load / refresh (idempotent)
php tools/seed-scenario-s04.php --status
php tools/seed-scenario-s04.php --remove
```
Password everywhere: `demo12345`. Client login: `s04.tech@demo.test` (`/portal/login`).
Prints a real 10-point PASS/FAIL dashboard; re-running purges DEMO-S04 first.

## What it seeds, and where to see each feature

| Feature (built this revamp) | Where to see it |
|---|---|
| **Supplier types** (§19) | `/portal/find` — two applicants show 🏢 *Freelance Resource Supplier · Apex Technical Manpower* (benched), one shows 👤 *Individual*. Filter with **Supplied by**. |
| **Masked profiles + verification ladder** (§15) | Click any applicant → name masked to first-initial, contact hidden, credentials show *Verified / Document attached / Self-declared*. |
| **Resource conflict flag** (§24) | On the requirement's applicants — the applicant already booked elsewhere over the same dates shows **⚠ Schedule conflict**. |
| **Marketplace → operations bridge** (§20) | The awarded requirement (Piping Inspector) shows a **Deployment** panel — *✓ Deployed to operations · DEMO-S04-DEP-1* — the same person, no re-keying. |
| **Mobilization gate pass** (§26) | `/portal/deputations` — one job **⛔ Not cleared · 1 open** (a required checklist item), one **✓ Gate pass**. |
| **Cancellation / no-show** (§37) | A no-show engagement surfaces as **⚠ Needs cover** with *Find a replacement*. |
| **Billing mismatch** (§30) | `/billable-events` (ops) — a billed event whose invoiced amount drifted from the earned amount is flagged **⚠ differs**. |

## Acceptance — 10/10 PASS

The seed asserts each engine from live data (not screens): supplier type resolves
(org vs individual), the conflict check returns CONFLICT/CLEAR correctly, the gate
is not-cleared vs cleared, needs-cover has the no-show, the deployment links to the
awarded requirement, a billed event is flagged mismatched, and a verified
credential reads VERIFIED.

## Existing-system impact

No workflow, table, permission or lifecycle changed. Reuses `connect_bench`,
`connect_supplier_type`, `connect_conflict`, `connect_engage` (+ cancel),
`connect_deploy`, `pdso` gate pass, `billable`, `connect_privacy`,
`connect_credentials`. Every DEMO-S04 record is namespaced and removed by
`--remove`. App test suite unchanged at 5368 passing.
