# M3 CORRECTION #15 — AUDIT

**Scope:** C-1, C-2, C-4 only. H1, H3, S2, S3, U2 and C-3 are untouched.

---

## PART A · What already exists, audited before anything was written

| mechanism | found | fit |
|---|---|---|
| `appr_tick()` — the approval scheduler | `lib/recruit_approval.php`, run per workspace by `cron.php`, already gated on the hiring module | **selected** as the home for reconciliation |
| `joblock_sweep_daily()`, `saas_tenant_seen_alive_sweep()` | existing daily sweeps | correct shape, wrong module — a sweep for approvals belongs to the approval scheduler |
| `act_cond_column_ready()` — the optional-column repair ledger (#9/#10) | bounded 3 attempts, **per epoch** | reused for "is marker storage available", but it cannot carry state between cron runs |
| `settings (skey VARCHAR(60) PRIMARY KEY, svalue TEXT)` + `settings_cache()` | one row per key in the **tenant's own** database, read through an in-memory cache | **selected** as the ledger's home |
| `setting_change_class()` | already excludes cron/bootstrap markers from the audit chain | extended by one key, so reconciliation does not write an audit line per pass |
| `activities` indexes | `idx_act_partner`, `idx_act_entity`, `idx_act_when`, and the optional `idx_act_cond (cond_key)` | nothing indexes `body`, which is what #14 scanned |

**Nothing was built that already existed.** The order was Reuse → Extend → Connect:
reuse `settings` and `appr_tick()`, extend `setting_change_class()` by one key,
connect the existing condition state to both.

**I deliberately did not create `appr_cond_resync()`, `appr_cond_clear()` or
`appr_cond_reconcile()` because the audit named them.** Those names came from my
own #14 attack, where they were evidence of absence, not a design. One function
is added — `appr_cond_reconcile()` — because the audit above shows no existing
sweep owns approval conditions; the name is incidental.

---

## The three findings share one cause

The only record of a failed condition was a row in `activities`, recognised by
scanning an unindexed column.

- **C-1** — recovery lived *inside* the two condition writers, so it ran only if
  the same condition fired again. The conditions most likely to be left unarmed —
  an orphaned chain, a request since decided — are exactly the ones that never
  fire again, so the warning was permanent.
- **C-2** — `NOT_RECORDED` writes no row, so there was nothing to recognise, and
  the diagnostic repeated on every tick. Measured: 30 ticks, 30 lines.
- **C-4** — recognition meant scanning `body`; counting for the dashboard meant a
  `LIKE` over the whole spine **on every page load**.

All three need the same thing: a small, durable, per-workspace note of what is
unresolved that does **not** live in `activities` — because the case that breaks
`activities` is precisely the case that must be recorded.

`settings` already is that store. It is **not** a second suppression store and
never decides suppression: `cond_key` on the spine still does that. The ledger
records only *what is unresolved* and *what has already been said*.

## Why `body` is still written but never searched

The fingerprint stays on the row. Removing it is C-3, which this correction is
forbidden to absorb. What changed is that nothing **looks it up** any more.
