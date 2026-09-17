# M3 — FINAL ADVERSARIAL ACCEPTANCE GATE

An attack on the **complete M3**, not the latest function. Probes ran against a
**copy** of `phpapp/` in the scratchpad; no product code was modified during the
attack itself. The targeted fix that follows was applied afterwards, as the
governance rule provides.

## Gate result

> ### M3 — NOT ACCEPTED on first pass.
> One **blocking** defect (G1) and one associated concurrency defect (G2) were
> found, both **introduced by the final stabilisation itself**. Both were fixed
> under the targeted-fix rule and verified. Re-run: **M3 — ACCEPTED**, subject to
> the open findings listed at the end, none of which was reopened.

---

## G1 · BLOCKER — the stabilisation reintroduced the orphan row J1 eliminated

The `NOT_RECORDED` recovery path proved the spine was writable by filing a note:

```php
act_log('APPROVAL_POLICY', 0, 'SYSTEM', 'Approval condition could not be recorded …');
```

`act_log()` stores `(int)$entityId ?: null`, so the row carries an entity **kind**
with a **NULL id**. Measured on both engines:

| | |
|---|---|
| the note is written | yes |
| `entity_kind` | `APPROVAL_POLICY` |
| `entity_id` | **NULL** |
| `appr_audit_ref_ok('APPROVAL_POLICY', 0)` | **false — the row cannot be opened** |

Correction #6 settled this question for this module, and the rule is written in
the file itself: *"A row nobody can follow is worse than no row."* The final
stabilisation had put one back — a self-inflicted regression of an explicit M3
rule, in the component built to make failures visible.

**Why blocking rather than latent:** it is a rule this milestone already
established and tested, the row is written on a real production path, and the
defect was created by the change under review.

### The fix
The subject `appr_audit_subject()` resolves is now **remembered on the condition
record** at the moment of failure — and a `NOT_RECORDED` condition always has one,
because `act_log()` is only reached after a subject resolved. Reconciliation files
the note **against that subject**, and only while it still resolves. If it no
longer resolves, the condition is closed as `TERMINAL` and **no row is written at
all** — nothing unopenable is ever invented.

## G2 · Concurrent reconciliation filed the note twice

Three **real** reconciliation processes against one `NOT_RECORDED` condition
produced **two** notes. Idempotency held within a process and not across them.

### The fix
An **atomic claim**: a compare-and-swap on that condition's own `settings` row
(`UPDATE … WHERE skey=? AND svalue=?`), one statement on one row chosen by the
PRIMARY KEY. Only the winner writes. If the write then fails, the record is put
back so a later pass retries it, and nothing claims it succeeded.

**A defect in my own first fix, reported:** the claim initially compared against
whatever was *currently* stored rather than against the state the caller
**expected**, so a process arriving after the winner had already closed the
condition still matched and filed a second note. Corrected to compare against the
expected state.

---

## What the gate could NOT break

- **Security.** A condition key is a fixed ASCII shape from a closed set of
  reasons; the fingerprint is a bounded 32-hex hash, so no caller-supplied text
  reaches the settings key. A hostile key (`PC|'; DROP TABLE settings; --`) still
  produces `apprcond<32 hex>`, inside `skey`'s VARCHAR(60), with no truncation
  collision. Every write is parameterised.
- **Concurrency.** Eight processes writing eight conditions: all eight survive.
  Four writing one condition: one row. A process holding a loaded cache sees
  another process's condition and does not erase it.
- **Tenant isolation.** DB A ≠ DB B; B cannot count, see, read, reconcile or
  delete A's conditions, and a real B worker's condition never reaches A.
- **Recovery.** Works without the condition recurring; the warning clears only
  after the marker is read back off the row.
- **The business outcome.** Ten identical refusals still produce one timeline
  entry, and nothing is flagged when nothing is wrong.
- **Regression.** SQLite **10553/0**, MariaDB **10554/0**.

## Two surviving mutations, and what they meant

`M-G1b` and `M-G2b` — both defensive branches of the fix — **survived the first
battery**. Neither was excused:

- `M-G1b` (file the note without checking the subject still resolves) survived
  because no test had a condition whose subject was deleted before reconciliation.
  **FS.12c** now does.
- `M-G2b` (claim against the current value instead of the expected state) survived
  because the three-process test catches the *whole claim* being removed but
  detects this narrower sub-bug **only when scheduling happens to expose it**. A
  race that reproduces sometimes is not a detector. **FS.12d** pins it
  deterministically instead.

Both are now caught.

## Verification of the targeted fix

| | SQLite | MariaDB |
|---|---|---|
| Final stabilisation suite | **97 / 0** | **97 / 0** |
| Complete regression | **10553 / 0** | **10554 / 0** |

Gate mutations: **4 attempted · 4 caught · 0 survived** —
`M-G1` by `FS.12 · the note CAN be opened`; `M-G1b` by `FS.12c · NO note was
written`; `M-G2` by `FS.12b · three concurrent passes filed exactly ONE note`;
`M-G2b` by `FS.12d · a later claimant holding the OLD state is refused`.

---

## Open findings carried forward, NOT reopened

Per the governance rule, low-risk latent issues were not reopened:

**H1** direct `appr_act()` still approves an orphan chain · **H3** orphans counted
in the SLA summary · **S2** condition identity omits the decision result · **S3** a
condition key never expires · **U2** unindexed `cond_key` lookup where the optional
index is absent · **C-3** the fingerprint is written to a display column · **A-2** a
colliding id stamps the wrong record · **A-3** `STORED` survives a rollback ·
**A-4** engines disagree on an over-long key · **A-5** byte-truncation mangles a
non-ASCII key · **A-6** the loose-comparison mutation is unpinned · **A-7** the
column channel masks the write channel.

Plus the eight limitations in the completion report, unchanged.

## Verdict

> ## M3 — ACCEPTED
>
> after one blocking defect (G1) and one associated concurrency defect (G2) were
> found by this gate, fixed under the targeted-fix rule, pinned by permanent
> assertions, and verified on both engines with a clean full regression.
>
> **M4 is NOT started.** The findings above remain open and are the input to
> whatever milestone addresses them; none was silently absorbed here.
