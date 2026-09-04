# Slice P9 — Revenue Reconciliation Worklist (§29)

**Change-control record (directive Part 25). Classification: REFACTOR / diagnostic
(read-only, additive, non-destructive). Status: DELIVERED (worklist + §28 reader
switch, safe default). All three revenue readers — management dashboard,
contract-360, order-360 — now honour the mode.**

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

## The reader switch (§28) — DELIVERED with a safe default + a mode control

The switch mechanism is now built, mirroring `finance_truth_unified()` (the §28 cost
switch). One setting, `revenue_reader_mode`, decides which figure the revenue readers
show for a job's invoiced amount, and the **legacy snapshot is never destroyed**:

- `revenue_reader_mode()` + `revenue_reader_set_mode()` — the three modes, set from a
  control on the `/revenue-reconciliation` screen (finance-gated).
- `job_invoiced_amount($job, $ledgerNet=null)` — the canonical reader.
- `revrecon_ledger_net()` / `revrecon_ledger_net_map()` — the per-job / bulk ledger reads.

Modes:
- **`legacy`** — the per-job snapshot (pre-switch behaviour; the rollback).
- **`reconciled`** (DEFAULT) — the books-ledger net **only where it agrees with the
  snapshot within tolerance**, else the snapshot. Guaranteed to change no unproven
  figure, so it ships on by default without moving a single number on screen. As
  finance drives the worklist to green, more jobs are on the ledger automatically.
- **`ledger`** — the full switch: books-ledger net wherever the books carry the job,
  snapshot otherwise (revenue is never silently zeroed). Turn on once green.

**All three revenue readers now honour the mode:**
- **management dashboard** (`mis.php`, invoiced/paid) — reads through `job_invoiced_amount()`.
- **contract-360** (`contracts.php`) — the money box (invoiced / outstanding / remaining)
  reads through `job_invoiced_amount()`; the per-call line reads through `call_invoiced_map()`.
- **order-360** (`crm.php` quote detail) — order-jobs read through `job_invoiced_amount()`
  (each row stamped `_invoiced`); the per-call line reads through `call_invoiced_map()`.

`call_invoiced_map()` is the per-call helper for the two 360 screens whose legacy figure
was a `SUM(invoice_amount) WHERE invoice_raised=1` sub-select — it preserves that same
`invoice_raised` gate, so under `legacy`/`reconciled` it reproduces the old figure exactly
and only under `ledger` does it move onto the books. Ledger nets are pre-loaded once per
screen (`revrecon_ledger_net_map()`), so no reader adds a per-row query.

Validated by `tests/test_revenue_reader_switch.php` (18/18): the job-level and per-call
readers under all three modes, the parity property (`legacy` == `reconciled` for every
diverging job), the `invoice_raised` gate preservation, and that the snapshot column is
never mutated. Both `/quote` and `/contract` dispatch through the switched handlers with
no error under every mode.
