# Module 03 — Quotations · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-25). Decision: (A) expiry awareness + approval-bypass guard;
margin-at-quote and the online client accept portal deferred. Read-only `quote_validity` + an
opt-in `crm_expire_quotes()` cron that stamps the already-defined EXPIRED status (never blocking
accept/revise), EXPIRED split out of LOST analytics, and the direct-approval bypass closed for a
matching approval chain (master override logged). Asserted by `tests/test_module03_quotations.php`.
The quotation lifecycle is now documented in `docs/03-object-lifecycles.md`. P1.

---

## 0. Headline: the subsystem is mature — the gap is *validity that means nothing*

The quotation engine (`lib/crm.php`) is one of the richest in the app: a full revision chain
(`parent_id`/`rev`/`is_current`, immutable once SENT — the only way forward is a new revision),
an amount/BU-driven **approval chain** with maker-checker retract, an optional pre-order review
checklist, contract conversion with a **bidirectional FK** and a one-quote→one-primary-contract
guard, Word + PDF output, auto-email + a D3/D6/D9/fortnight/month follow-up cadence, and a
sales→Accounts handoff wall. It is not the place for a rebuild.

**The concrete dead spot:** a quotation carries `validity_days` (default 30) and the lifecycle
already defines an **`EXPIRED`** status (`crm.php:137`) — but:

- `validity_days` is only ever *displayed* (PDF `pdf.php:336,405`; detail `quote_detail.php:370`;
  form). It is **never compared** to `sent_at` / today.
- **No code anywhere assigns `EXPIRED`.** It appears only in a demo seed row, and in read-only
  filters/playbook/analytics that are ready to handle it but never receive it.
- There is **no cron** that expires quotes (`cron.php` only mails follow-ups).

So a quotation sent 18 months ago, long past its 30-day validity, still shows as **SENT — open**,
counts as live pipeline, and keeps generating follow-up nudges. The validity clause on the client
PDF is decorative. The lifecycle was clearly *designed* for `EXPIRED` to be a real state — it is
simply never reached.

**Second gap (guardrail):** two paths reach `APPROVED` — the multi-level `quote_approvals` chain
(`quote-approve`) and a **direct** `quote-status to=APPROVED` (`crm.php:1548`, permission-gated).
The direct path can stamp APPROVED **without building or consuming the chain**, quietly bypassing
multi-level approval when a rule would otherwise apply.

---

## 1. What is deliberately NOT in scope (honouring the program rules)

- **Margin / profitability at quote time.** `quote_lines` has `rate`/`amount` but no cost/margin;
  approval authority is amount-band only. Costing is a separate linked entity (`projcosting.php`).
  Per the program's "one canonical engine" rule, margin belongs to Module 20 / 32, not a bespoke
  formula bolted onto the quote. Deferred.
- **Online client accept/reject portal.** Acceptance is staff-entered; the client only gets a PDF.
  A real client-facing accept page (with an audit that the *client*, not staff, accepted) is a
  portal feature — larger, its own module. Deferred (noted as §5-C).
- **Restructuring the approval model.** We *guard* the bypass; we do not rebuild the chain.

---

## 2. Proposed additive layer (recommended = §5-A)

**Make validity real — as a derived state first, with an opt-in cron to stamp it.**

1. **`quote_validity($q)`** (read-only, `lib/crm.php`) — returns `['expires_on'=>…, 'days_left'=>…,
   'expired'=>bool]` for a quote that is **SENT** (or otherwise open) and has a `sent_at` +
   positive `validity_days`. `expires_on = sent_at + validity_days`. A DRAFT/PENDING/closed quote
   is never "expired" (nothing was promised to a client yet, or it's already resolved). A quote
   with `validity_days=0`/blank never expires (open-ended by intent).

2. **Surface it, non-destructively:**
   - **Register** (`quote_list.php`) — a quote past validity shows an **"Expired"** pill (derived)
     beside its status, and the open/pending tab counts reflect that it is no longer live pipeline.
   - **Detail** (`quote_detail.php`) — a banner on an expired open quote: *"This quotation's
     validity lapsed on <date>. Revise it to re-issue with a fresh validity, or record the client's
     acceptance below with a note."* Never blocks: **an expired quote can still be revised or
     accepted** (an "accepted after expiry" note is recorded on the acceptance).
   - **Follow-ups** — the derived-expired state is available so the cadence/worklist can stop
     nudging on a lapsed quote (advisory; existing follow-up rows untouched).

3. **`crm_expire_quotes()`** — an **opt-in** cron (same shape as `equipment_run_cal_reminders`)
   that stamps the **already-defined** `EXPIRED` status on open SENT quotes past validity, writing
   the change to the quote's audit/change-history like any status move. Because `EXPIRED` is a
   status the lifecycle already lists and the list/playbook/analytics already handle, this
   *completes* the intended design rather than adding a new state. **Guarded so it never touches**
   a quote that is ACCEPTED/LOST/REJECTED, locked into a contract, or already EXPIRED. If the
   lifecycle doc does not yet document EXPIRED for quotations, that doc is updated in the same
   commit (per CLAUDE.md); if it forbids the auto-transition, the cron ships **disabled** and only
   the derived display lands — the decision below calls this out.

4. **Disambiguate EXPIRED from LOST in analytics** — today the list "lost" tab and lost KPIs would
   swallow EXPIRED (`crm.php:1239`). An expired quote is *not* a regretted loss; surface it as its
   own count so "lost" stays meaningful.

5. **Guardrail — close the approval bypass:** when an approval **rule matches** a quote (a chain
   would be built), the direct `quote-status to=APPROVED` path defers to the chain instead of
   stamping APPROVED — i.e. it routes to PENDING_APPROVAL / requires the chain be consumed. When no
   rule matches (the single-generic-step / no-approval case), behaviour is unchanged. This preserves
   every current path except the one that skips a required multi-level approval.

Reuses: the `EXPIRED` status + `QUOTE_CLOSED_STATES` handling, the change-history/audit writer, the
cron harness, the approval-rule matcher (`crm_build_approvals`/`APPROVAL_MATCH`). **No new status;
no new permission.** Schema: none required (all fields already exist).

---

## 3. Edge cases

1. **DRAFT / PENDING_APPROVAL quote** → never "expired" (nothing was sent to a client). Validity
   only starts counting at SENT (`sent_at`).
2. **`validity_days = 0` or blank** → open-ended; never expires. (Some framework/rate-contract
   quotes are deliberately open.)
3. **`sent_at` missing but status SENT** (legacy/oddly-set) → fall back to `updated_at`/`created_at`
   for the clock, or treat as non-expiring rather than guessing — never crash.
4. **Expired then revised** → the new revision is DRAFT with a fresh clock; the old revision is no
   longer `is_current`, so it drops out of the live/expired counts. Revision always re-opens the
   door (it already bypasses the SENT lock).
5. **Accepted after expiry** → allowed; acceptance records an "accepted after validity lapsed on
   <date>" note so the segregation/audit trail is honest. Expiry is a flag, not a block.
6. **Already ACCEPTED / LOST / REJECTED** → closed; never expired, never re-stamped by the cron.
7. **Locked into a contract** → `contract_id` set; the cron skips it (a quote that became a
   contract is not "expired" even if old).
8. **Cron idempotency** → an already-EXPIRED quote is not re-stamped; the job is a no-op on a
   second run the same day.
9. **Timezone / date-only** → compare date-only (`Y-m-d`), consistent with how the calibration and
   contract-idle checks compare, so a same-day boundary isn't off-by-one across TZs.
10. **Approval bypass guard** — a quote with **no** matching rule still reaches APPROVED directly
    (unchanged); only a quote that *would* build a chain is routed through it. A master override, if
    one exists on that path, is preserved and audited, not silently removed.
11. **Performance** → expiry is a per-row date comparison in the list (no extra query); the cron is
    one scan of open SENT quotes, not per-quote N+1.
12. **Read-only display vs. the cron** → even with the cron disabled, the derived pill/banner make
    expiry visible immediately; the cron only turns "visible" into "stored status".
13. **Mobile** → sales are desk-first, but the pill/banner degrade to single-column cleanly.

---

## 4. Guardrails (must stay green)

- The revision chain (immutable-once-SENT, one `is_current`, JSON snapshots), the approval chain +
  retract, the pre-order checklist gate, the one-quote→one-contract guard, the contract-registration
  PENDING→endorse→approve flow, the sales handoff wall, Word/PDF output, and the follow-up cadence —
  **all unchanged**. Expiry only *reads* them; the cron only *completes* an already-defined status.
- `test_quote_revision`, `test_quote_approve_gate`, `test_quote_playbook` (it already expects
  **expired = closed banner** — this module finally makes that reachable), `test_one_quote_one_contract`,
  `test_quote_group_contracts`, `test_sales_handoff_wall`, `test_opp_*` — untouched.
- No existing route, table, column, status or permission removed or narrowed.

---

## 5. OPEN DECISION — how far to take validity, and the approval guard

- **(A) Expiry awareness + the approval-bypass guard (recommended, P1):** derived `quote_validity`
  surfaced as an Expired pill/banner (never blocking — revise or accept-with-note still work), an
  **opt-in** `crm_expire_quotes()` cron that stamps the already-defined `EXPIRED` status on lapsed
  open quotes (disambiguated from LOST in analytics), **and** close the direct-approval bypass so a
  matching approval chain can't be skipped. No new status; no new permission; no schema change.
- **(B) Expiry awareness only** — everything in (A) except the approval-bypass guard, left for a
  dedicated segregation-of-duties pass. Smaller blast radius on the approval path.
- **(C) Also build the online client accept/reject portal** — a real client-facing accept page with
  an audit that the client (not staff) accepted, capturing the client PO at accept. Larger; portal
  work; its own module. Deferred by default.

Default if you don't specify: **(A)**.

---

## 6. Tests

1. `quote_validity`: a SENT quote past `sent_at + validity_days` is expired; within validity is not;
   a DRAFT/PENDING/closed quote is never expired; `validity_days=0`/blank never expires; missing
   `sent_at` doesn't crash.
2. `crm_expire_quotes`: stamps EXPIRED on a lapsed open SENT quote; is a no-op on one within
   validity, on an ACCEPTED/LOST/REJECTED quote, on a contract-locked quote, and on a second run
   (idempotent); writes the change to history.
3. Revision re-opens the clock (an expired quote's new revision is DRAFT, not expired); acceptance
   after expiry is allowed and records the note.
4. Analytics: an expired quote is counted as expired, **not** folded into "lost".
5. Approval guard: a quote with a matching approval rule cannot be stamped APPROVED by the direct
   status path (routes through the chain); a quote with no matching rule is unchanged.
6. No new status constant beyond the existing EXPIRED; no new permission; the revision/approval/
   contract/handoff guards are unchanged.
