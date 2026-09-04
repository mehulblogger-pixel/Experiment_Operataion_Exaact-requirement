# Slice P11 — Candidate Pool Convergence

**Change-control record (directive Part 25). Classification: REFACTOR / detector +
confirm-link (additive, reversible, non-destructive). Status: DELIVERED (detector +
confirm/link). Physical record merge deliberately NOT done.**

The identity-side twin of the reconciliation detectors (P9 revenue, P10 cost) and
the direct cross-pool extension of `recruit.php`'s `person_applications()` — which
already links a candidate to their *other recruitment applications*. This links a
candidate to their *marketplace professional* record.

## 1. Existing state / problem

The platform keeps **two identity pools of the same real people**:

- the **recruitment pool** — `candidates` (people we place on a client's roll,
  worked through requisitions); and
- the **marketplace pool** — `cx_professionals` (people on the bench / passport for
  freelance deployment, with a verification tier, availability and rates on file).

They were built separately and **never linked**. A recruiter working a candidate
could not see that the same person is already a verified, benched marketplace
professional; the bench could not see a professional had a live recruitment
application. Both pools already dedupe *within* themselves (`person_key` /
`connect_pro_duplicates`) — nothing dedupes *across* them.

## 2. Solution (delivered — read-only detector)

`lib/candpool.php` (new):
- **`candpool_pro_matches($cand)`** → the marketplace professionals that are the
  same person as this candidate, matched by the shared keys the app already uses —
  last-10-digit mobile (strong), lower-cased e-mail (strong), dedupe name key
  (soft) — strongest reason kept per professional.
- **`candpool_cand_matches($pro)`** → the reverse (candidates for a professional).
- **`candpool_pro_index($rebuild)`** → a request-cached `{mobile, email, name} →
  ids` index of the (active) marketplace pool, so a scan is O(n+m), not O(n·m).
- **`candpool_scan($limit)` / `candpool_count()` / `candpool_summary()`** → the
  overlap worklist, count and pool-health summary.
- **`ops_candpool()`** → the read-only **Candidate pool** overview screen
  (`views/ops/candidate_pool.php`), gated to recruitment/HR viewers
  (`recruit_home_can()` / master).

Surfaced at:
- **Recruitment home → "🔗 Candidate pool"** button, route `/candidate-pool`.
- a panel on the **candidate detail** (`views/ops/candidate_detail.php`) — *"Also
  on the marketplace"* — beside the existing *"This person's other applications"*
  panel, so the cross-pool link is where the recruiter already looks.
- CLI: **`tools/candidate-pool.php`** (`--list`) — a read-only report.

Deliberately **not** on the system-status attention band: an overlap is useful
information, not a drift to fix, so it is discoverable, not an alert (and avoids a
per-request cross-pool scan).

It **merges nothing, deletes nothing and moves no figure** — each pool keeps its
own record; a soft *same-name* match is labelled as such for a human to confirm.

## 3–8. Impact

- **DB / status / permission:** none. Pure read over `candidates` +
  `cx_professionals`. The screen reuses the existing recruitment gate; the route is
  handler-gated. No permission-matrix or lifecycle change.
- **Behaviour:** additive screen + button + candidate-detail panel + CLI; every
  recruitment and marketplace reader is unchanged.

## 9. Regression & validation

- `php -l` clean on all changed files.
- New `tests/test_candpool_convergence.php` — **12/12** (same-mobile/same-name
  professional matched, mobile beats name, e-mail-only match, inactive professional
  ignored, reverse lookup, a solo candidate matches nothing, scan lists exactly the
  overlapping person with the strongest reason and a multi-match count, and the
  detector leaves both the candidate and the professional untouched — read-only).
- Direct view render verified with a real overlapping person seeded across both
  pools: renders with no error, shows the person, and matches by mobile across a
  leading-0 difference.
- **Full suite: 5393 passed, 0 failed** (was 5381).
- **Auto-walk: 203 screens across every role render cleanly.**

## 10. Rollback

Remove the route + view + `lib/candpool.php` + the recruitment-home button + the
candidate-detail panel + the CLI. No data touched.

## Confirm / link — DELIVERED (the safe, reversible step)

The detector's matches can now be **confirmed** into a persisted candidate↔professional
link, additively and reversibly — nothing is merged and neither record is touched:

- The link reuses the existing identity ledger `cx_identity_link` (which already links a
  professional ↔ an operations inspector) via an additive nullable `candidate_id` column;
  a candidate-axis row carries `inspector_id=0`. `connect_identity_candidate_link_create()`
  creates it (refusing a second professional for an already-linked candidate, validating
  both ids), `connect_identity_of_candidate()` resolves it, and the existing
  `connect_identity_unlink()` reverses it.
- Surfaced on the candidate detail *"Also on the marketplace"* panel — a **Confirm same
  person** button per match, a **✓ Confirmed / Unlink** state once linked (coordinator-gated,
  mirroring the existing *"same person"* application link). The `/candidate-pool` worklist
  shows a **✓ confirmed** pill on linked rows.
- `tests/test_candidate_pro_link.php` (15/15): create, idempotent re-confirm, refusal of a
  second professional, id validation, the worklist mark, unlink, and the invariant that
  **neither the candidate nor the professional row is ever mutated or deleted**.

## Staged — the physical record merge (needs a decision, deliberately NOT done)

Collapsing the two pools into *one* physical record (one shared person row, or rewriting
one module to resolve through the other) changes how both modules resolve a person and
remains deliberately deferred — a mismatched merge is destructive. The confirmed link above
is the safe, reversible foundation and the reviewable gate that any later merge would build
on, exactly as the reconciliation worklists gate the reader switches.
