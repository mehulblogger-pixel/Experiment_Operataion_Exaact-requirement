# Slice P9 — Revenue Reconciliation Worklist (§29)

**Change-control record (directive Part 25). Classification: REFACTOR / diagnostic
(read-only, additive, non-destructive). Status: DELIVERED (worklist). Reader
switch (§28) staged.**

`02-gap-and-reuse-map.md` §3 / canonical §29/§80. Diagnostic-first, as agreed — the
tool that lets finance reach "green" before any revenue reader is switched.

## 1. Existing state / problem

Two money truths coexist: the legacy per-job snapshot (`jobs.invoice_amount` /
`invoice_raised` / `payment_received`, read by MIS, contract-360, the CRM order
view and the money dashboard) and the books ledger (`invoices` →
`invoice_lines.job_id`). `lib/revrecon.php` (§29) already reconciles them
read-only and surfaces a **count** on the attention band — but there was **no
drill-down**, so finance couldn't see *which* jobs disagree or fix them. And
`§28`/`finance_truth_unified` can only safely switch the revenue readers onto the
ledger once nothing diverges.

## 2. Solution (delivered — read-only worklist)

`lib/revrecon.php`:
- **`revrecon_summary()`** — candidates, reconciled vs diverging, legacy-only /
  ledger-only splits, the two running totals, and a **`green`** flag (true when
  nothing diverges — the signal that readers could be switched).
- **`revrecon_list()`** — the diverging jobs enriched with job code, client, and a
  plain-language reason (legacy-only / ledger-only / matches-neither).
- **`ops_revrecon()` + `/revenue-reconciliation`** — a read-only worklist screen
  (health chips + a table linking each job), gated to finance / figure-holders
  (`can_see_salary` / `finance.reconcile` / `data.revenue` / master); a Money-area
  tile with the diverging count.

It **changes no figure and switches no reader** — it is the instrument to drive
the divergence to zero.

## 3–8. Impact

- **DB / status / permission:** none. Pure read over `jobs` + `invoice_lines`. The
  screen reuses existing finance gates; the route is handler-gated.
- **Behaviour:** additive screen + tile; the existing §29 count on the attention
  band is unchanged.

## 9. Regression & validation

- `php -l` clean.
- New `tests/test_revrecon_worklist.php` — **10/10** (a matching job reconciles; a
  match-neither and a legacy-only job diverge; summary counts + `green=false`; the
  worklist lists diverging jobs, excludes the reconciled one, and carries
  client + reason). Caught and fixed a `COALESCE`-vs-`NULLIF` client-name bug.
- **Full suite: 3923 passed, 0 failed.**

## 10. Rollback

Remove the route + view + the three `revrecon.php` functions + the Money tile. No
data touched.

## Staged — the reader switch (§28, needs a decision, deliberately NOT done)

Once `revrecon_summary()['green']` holds across the real dataset, switch the
remaining legacy revenue readers onto the ledger, one at a time, each validated:
`mis.php` (invoiced/paid), `contracts.php` (contract-360 invoiced/received),
`crm.php` (order-jobs), `ops.php` money dashboard (outstanding). This *changes
displayed revenue figures*, so it is a deliberate §28 step per reader — not part
of this diagnostic slice. The worklist is the gate that makes it safe.
