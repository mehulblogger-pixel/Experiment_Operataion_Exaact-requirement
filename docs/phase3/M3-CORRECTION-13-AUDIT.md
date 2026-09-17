# M3 CORRECTION #13 — AUDIT

**Scope:** the production caller must consume the condition-persistence result,
and duplicate suppression must fail safely. Nothing else. H1, H3, S2, S3, U2,
id collision, Unicode, engine-length and rollback semantics, and error-message
aggregation are all deliberately untouched.

---

## §1 — the actual production call path, traced in the source

Not inferred from tests. There are **two** production sites, both in
`lib/recruit_approval.php`, and they share one shape:

```
appr_audit_notify($req, $result, $reason)        appr_audit_sla($req, $step, $what, …)
        │  (line 1714)                                   │  (line 287)
        ▼                                                ▼
appr_condition_key(…)   ──────────────────────────────►  builds 'PC|EVENT|ENTITY|id|Rn|Sn|REASON'
        │                                                │   (empty unless the condition is PERMANENT)
        ▼                                                ▼
appr_condition_seen($key)  ────────────────────────────►  SELECT id FROM activities WHERE cond_key=?
        │   seen → return (suppressed)                   │   ← reads the marker OUT OF THE DATABASE
        ▼                                                ▼
appr_audit_subject($req)   ────────────────────────────►  nothing openable → return, no row
        ▼                                                ▼
act_log(…, ['cond_key' => $condKey])
        │
        ▼  lib/activity.php:432
$ck = substr(trim($opt['cond_key']), 0, 160);
if ($id > 0 && $ck !== '') act_set_cond_key($id, $ck);   ← ****  THE STATUS DIES HERE  ****
                                        // "status ignored: the CORE row is what matters"
        ▼
return $id;                              ← act_log() returns the ROW ID and nothing else
```

**Where the status was discarded:** `lib/activity.php:432`. `act_set_cond_key()`
returned one of four constants; the return value was not assigned, and
`act_log()`'s own return carries no room for it. Both production sites then
returned `void`, so no caller could have learned the outcome even if it wanted
to. Confirmed by search: **zero** non-test references to `ACT_COND_STORED`,
`ACT_COND_FAILED`, `ACT_COND_UNAVAILABLE`, `ACT_COND_NOT_ATTEMPTED`,
`act_optional_error()` or `act_optional_state()`.

## Why this was a live product defect, not a missing test

The suppression guard asks the **database**, not memory. So:

```
marker write fails  →  status discarded  →  nobody knows
        ↓
next identical permanent condition
        ↓
appr_condition_seen() finds no marker
        ↓
"never seen" → the event is written AGAIN
```

That is the repetition Correction #7 was raised to stop, silently resumed. The
chain had a truthful answer at one end and no listener at the other.

---

## §2 — how the result is now consumed

**`act_log()`'s contract is unchanged.** It still returns the core row id, and
the 74 callers that never pass a `cond_key` are untouched. The marker's status
is instead recorded in a **workspace-keyed slot** — the same mechanism #11 uses
for the four error channels, keyed on `db_epoch()` — and read through one
accessor, `act_last_cond_status()`.

The slot is **reset on entry to `act_log()`**. Without that, a call writing no
marker would leave the previous call's `STORED` standing and the next reader
would credit this event with a marker belonging to a different one — the
stale-read defect X2 found in the error channels, one slot along.

Both production sites now read it through `appr_cond_outcome()` and return their
own outcome. The status is **not** collapsed to a boolean: a truthy `'FAILED'`
was the trap #11 named, and "did it store" and "is suppression armed" are two
questions that only happen to share an answer today.

| caller outcome | meaning |
|---|---|
| `APPR_COND_NONE` | no permanent condition — nothing to suppress |
| `APPR_COND_SUPPRESSED` | already recorded — deliberately silent |
| `APPR_COND_NO_SUBJECT` | nothing openable remains — no row written |
| `APPR_COND_RECORDED` | row written **and** marker persisted — suppression **armed** |
| `APPR_COND_UNARMED` | row written, marker **not** persisted — **not** armed |

---

## §3 — the fail-safe, chosen from the existing architecture and stated exactly

**The business property:** if the marker cannot be persisted, the application
must not silently behave as though the condition was recorded.

**The chosen behaviour:**

- marker `STORED` → suppression is **armed**; existing behaviour, unchanged.
- marker not stored → suppression is **not armed**, and the application says so.
  The event row still stands (S1 — an audit event is never sacrificed to its own
  metadata), the caller receives `APPR_COND_UNARMED`, and the reason goes to the
  error log. **Nothing anywhere claims the marker was written.**

**Why fail-open, recorded so it is not mistaken for an oversight:**

- Failing **closed** — suppressing the repeat anyway — would mean suppressing on
  the strength of a marker that does not exist. That is precisely "silently
  behaving as though the condition was recorded", which §3 forbids.
- Suppressing from a **second store** (a cache, a session, an in-memory set)
  would be a second deduplication mechanism, which §3 forbids.
- So the repeat is **allowed**, and it is **attributable**. A duplicate timeline
  entry is a visible, correctable annoyance; a suppressed event that was never
  recorded is lost evidence. The audit trail keeps the louder failure.

No approval decision, authority, delegation or notification behaviour changes.
No new notification or deduplication mechanism was created. No permission, role,
status or lifecycle transition was added or changed, so `docs/01-roles.md`,
`docs/02-permission-matrix.md` and `docs/03-object-lifecycles.md` are unaffected
and remain in agreement with the code.
