# M3 FINAL STABILISATION — ADVERSARIAL-READY

M3 is submitted for **one final independent adversarial acceptance test**, against
the whole milestone rather than the latest function.

## The complete chain, with the mutation that severs each link

```
condition occurs
  → act_set_cond_key()        truthful, reads the value back          #12   M-X1-A/B/C
  → act_log()                 carries the status AND the row id       #13/#14  M14-7
  → appr_audit_notify/_sla    consume it; gate: SUPPRESS/RETRY/WRITE  #13/#14  M14-1/4
  → appr_tick, appr_email_*   consume the outcome; cron reports it    #14   M14-10
  → bounded failure           one row ever; 30 ticks, one line        #14   M14-4
  → condition record          ONE SETTINGS ROW, atomic per condition  FINAL M-F1
  → reconciliation            recovers without the condition recurring FINAL M-F7
  → business-visible warning  on the dashboard that already exists    #14   M14-5/9
  → recovery / TERMINAL       marker read back, or truthfully closed  FINAL M-F4/F9
```

## Where I would attack first

Named deliberately: every round so far was decided by something I had not thought
to test, and hiding the soft spots wastes the gate.

1. **`appr_cond_outcome()` is a proximity contract.** It reads state left by the
   immediately preceding `act_log()`. Nothing violates it today; nothing enforces it.
2. **`appr_cond_unarmed_count()` decodes every open record on each call.** Free in
   a healthy workspace (no records), 1 ms with 500. It is not paginated.
3. **`NOT_RECORDED` recovery writes one activity row** as its proof of writability.
   That is a write performed by a reconciliation pass — bounded and idempotent, but
   it is a write.
4. **Two workers writing the SAME condition** end with one row and last-writer-wins
   on its *content*. Proved not to duplicate; not proved to merge.
5. **`TERMINAL` records accumulate** and are never pruned, by choice — pruning
   risks the silent loss D-2 was about.
6. **C-3 is untouched by instruction**: the fingerprint is still written to `body`,
   a column the shared timeline panel renders. Not reachable for these entity kinds
   today.
7. **The settings cache exclusion** (`NOT LIKE 'apprcond%'`) is a global change to a
   shared function, made for one caller.

## Known open findings, deliberately not absorbed

**H1** direct `appr_act()` still approves an orphan chain · **H3** orphans counted
in the SLA summary · **S2** condition identity omits the decision result · **S3** a
condition key never expires · **U2** unindexed `cond_key` lookup where the optional
index is absent · **C-3** above · **A-2** a colliding id stamps the wrong record ·
**A-3** `STORED` survives a rollback · **A-4** engines disagree on an over-long key ·
**A-5** byte-truncation mangles a non-ASCII key · **A-6** the loose-comparison
mutation is unpinned · **A-7** the column channel masks the write channel.

None was reopened in this stage, per the governance rule.
