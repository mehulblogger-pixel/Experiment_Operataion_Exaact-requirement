# RB-3 · Step 2 — Completion

## 1. What changed

Before this step, nothing in EXAACT ever asked whether the person about to be
hired was **already on the team**. Now it asks, and when the evidence is strong
it stops and makes a person answer.

## 2. Files changed

| File | Change |
|---|---|
| `lib/recruit.php` | `workforce_matches()`, `workforce_strong_matches()`, the two masking helpers, `workforce_ack_secret/evidence/issue/ok/note()`, one new gate in `rcv_convert()`, one new refusal code |
| `lib/ops.php` | the `/candidate` controller issues the tick; the `candidate-stage` route carries it to the action |
| `views/ops/candidate_detail.php` | the warning and the tick |
| `tests/test_rb3_step2_dupmatch.php` · `tests/_rb3s2_worker.php` · `tests/_rb3s2_tenant.php` | new |
| `phpapp/deploy-check.php` | regenerated |

## 3. Engines reused — nothing new was built

`cand_find_duplicates()`'s confidence scale · `connect_identity_scope_ok()` ·
`act_log()` / `rcv_log()` · the `settings` store · the central CSRF gate ·
`rcv_convert()`'s existing refusal contract. **No new engine, no new permission,
no new status, no new lifecycle transition, no new route.**

## 4. Database changes

**None.** No table, no column, no index, no migration.

The only stored item is one row in the **existing** `settings` table holding a
per-workspace random signing key. `J6`/`J7` assert exactly that: one key, and no
acknowledgement table anywhere.

## 5. Duplicate matching rules

Full rules: `RB3-STEP2-DUPLICATE-MATCH-RULES.md`.

| Basis | Confidence | Effect |
|---|---|---|
| same mobile | 96 | **STRONG — acknowledgement required** |
| same e-mail | 94 | **STRONG — acknowledgement required** |
| same full name | 72 | shown, never blocks |
| same surname + initial | 46 | shown, never blocks |

Live team members only · scope-filtered (counted, never named) · no `LIMIT` ·
**the employee number is never a matching signal** · no date of birth, PAN or
Aadhaar exists in this application and none was invented.

## 6. The acknowledgement

A keyed signature carried as the tick's **own value**, so an unticked box sends
nothing and the default is always NOT ACKNOWLEDGED. Inside the signature:
**the application · the user · the evidence · the time of issue.** That is what
makes it unusable on another application, by another person, after the duplicate
picture changes, once it has aged, or in another workspace.

## 7. Security

Checked **in the action**, not the route, so a forged POST that never rendered a
warning fails exactly as the screen does. Tenant isolation is structural and was
proved against **two real separate databases**. CSRF is central and
unconditional. Authorisation is unchanged — acknowledging carries the same right
as converting. A match the actor may not open is **counted but never named**, and
still blocks.

## 8. Audit

Through `act_log()` via `rcv_log()`. A refusal records how many possible matches
there were **and which team members they are**. An acknowledged hire records
**what the recruiter confirmed**, not merely that they confirmed. No second audit
system.

## 9. Concurrency

Four real OS processes on a wall-clock microsecond barrier: one holding a tick,
three without. The three are always refused; the holder is never refused for lack
of one. On MariaDB exactly one converts. Exactly one team member is created by
the whole race. **No locking was introduced** — the gate refuses before any
transaction is opened.

## 10. Test results

| Engine | Result |
|---|---|
| SQLite | **12,980 passed · 0 failed** |
| MariaDB | **12,983 passed · 0 failed** |

## 11. Mutation results

**14 targets · CAUGHT 14 · SURVIVED 0 · FATAL 0 · ANCHOR-MISS 0.**

## 12. Known limitations

1. **Matching can only use what the application holds** — mobile, e-mail, name.
   No date of birth, PAN or Aadhaar exists, and none was invented. A person who
   changes both their number and their address will not be matched.
2. **The tick expires after 30 minutes.** A recruiter who leaves the screen open
   longer must reload; the refusal says what to do.
3. **SQLite has no busy timeout** (Step 1 finding F1), so under a real write race
   a conversion may be told `BUSY`. Production is MariaDB.
4. **`cand_find_duplicates()` still has its 500-row ceiling** (finding N5). The
   new check deliberately has none; the old one is untouched.
5. **The acknowledgement is not stored.** It authorises one hire and is recorded
   in the audit trail, not kept as state.

## 13. Deferred

- The **atomic acceptance flow**, **stage-move ordering** (§0.2, still awaiting
  the owner), **RB-2**, **hidden-checkbox removal** — RB-3 Step 3 and beyond.
- The **`dup_ack` hidden field on the candidate *creation* form**, which
  acknowledges itself on the next submit. A different screen and a different
  action; recorded, not changed.
- The **demo unload deleting by employee number** (Step 1 finding F2).
- **"One person = one inspector"** — not decided, not touched.

## 14. Commits

| | |
|---|---|
| Working implementation, before mutation testing | **`eff3e5b`** |
| Final, after the mutation corrections | **`47fabb7`** |

*(This line is recorded by a doc-only commit that follows `47fabb7`; `47fabb7` is
the commit the test and mutation figures above were produced from.)*
