# M3 CORRECTION #14 — ADVERSARIAL-READY

The chain the brief requires, end to end, with the evidence for each link and the
mutation that would break it.

```
condition occurs
      │
      ▼
act_set_cond_key()              truthful  (#12)     M-X1-A/B/C
      │  STORED / FAILED / UNAVAILABLE / NOT_ATTEMPTED
      ▼
act_log()                       carries status AND ROW ID  (#13 · Y1, #14 · Z1)
      │                                                     M14-7
      ▼
appr_audit_notify / appr_audit_sla
      │  gate: SUPPRESS · RETRY · WRITE                     (#14 · Z3)   M14-4
      │  outcome: RECORDED · UNARMED · NOT_RECORDED
      │           PENDING_RETRY · RECOVERED · SUPPRESSED    (#14 · Z2)   M14-2, M14-3, M14-6
      ▼
appr_tick() · appr_email_requester()      consume it        (#14 · B-1)  M14-1
      │
      ▼
bounded failure handling        ONE row ever, retry on it   (#14 · B-4)  M14-4
      │                         29 of 30 ticks are silent   (#14 · B-5)
      ▼
business-visible outcome        appr_sla_summary() →        (#14 · B-2)  M14-5, M14-8, M14-9
      │                         Recruitment Command Centre
      │                         cron run prints it                       M14-10
      ▼
recovery                        retry arms the existing row (#14 · TEST E) M14-6
                                ordinary suppression resumes
```

**No decision result is discarded anywhere on this path.** Every arrow above has
a mutation that severs it and a production-path assertion that catches the
mutation.

---

## Where an attacker should look first

I am naming these because the last three corrections were each undone by
something I had not thought to test, and hiding the soft spots wastes a round.

1. **`appr_cond_outcome()` reads state left by the immediately preceding
   `act_log()`.** If anything ever calls `act_log()` between the write and the
   read, the outcome describes the wrong event. Nothing does today. It is a
   proximity contract, not an enforced one.
2. **The fingerprint lives in `body`.** Any future writer that sets `body` on a
   condition event would collide with it, and any existing row whose `body`
   happens to start `PCX|` would be miscounted. Neither happens today.
3. **`NOT_RECORDED` is not bounded across ticks.** When the `activities` table
   itself cannot be written there is no row to count, so the bound that fixes B-4
   cannot exist — see *Known limitations*.
4. **The retry is unbounded in attempts** (one `UPDATE` per tick). It is bounded
   in rows and in log lines, which is what ran away. Whether an unbounded cheap
   retry is acceptable is a judgement I have made and stated, not proved.
5. **`suppression_unarmed` counts distinct fingerprints, not distinct
   conditions.** Two conditions sharing a fingerprint would count once. SHA-1
   truncated to 32 hex characters makes that vanishingly unlikely, not impossible.
6. **The email itself is untouched.** #14 bounds the audit row, not the reminder
   that was already sent before the audit row is written. That was out of scope
   and is listed as a known limitation.
