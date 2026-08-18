# Inspection Ops — Leads & Pipeline — Test & Documentation Report

> **Prompt 3 · Module MOD-LEADS.** Read from `lib/leads.php` (`ops_leads`, `lead_create`,
> `lead_move`, `lead_convert`, `lead_log_contact`, `lead_score`, `lead_possible_duplicate`,
> `lead_stalled`, `lead_board`, `leads_bulk`, `lead_files_carry_to_quote`), `lib/pipelines.php`
> (`ops_pipelines`, `pipe_*`), `lib/opportunities.php` (deals). Views `leads.php`,
> `lead_detail.php`, `lead_form.php`, `lead_convert.php`.

| | |
|---|---|
| **Module** | Leads & Pipeline (MOD-LEADS) · Area Sales |
| **Personas** | P-MKT/P-BDM (own leads), P-BM/P-SBU (oversight), P-MASTER |
| **Risk weight** | Medium — the top of the sales funnel; conversion seeds the customer + inquiry |
| **Verdict** | Complete-with-defects (confirm stage guards, conversion atomicity, SBU scoping) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

A configurable **pipeline** (`pipelines` + `pipeline_stages`, each stage kind OPEN/WON/LOST)
carries **leads** (`leads`) from entry to conversion. A lead is OPEN → CONVERTED / LOST;
stage moves are guarded (`lead_move`: only OPEN leads move, target stage must belong to the
lead's pipeline, same-stage rejected, WON forces the **conversion** flow, LOST requires a
reason). **Conversion** (`lead_convert`) creates/attaches a customer + a **primary contact**
and raises a `crm_inquiries` row (and back-fills a linked opportunity's partner). Leads carry
a rules-engine **score** (`lead_score`), duplicate detection (`lead_possible_duplicate`,
warning only), stall/SLA detection, files that **carry to a quote**, and a board/list view.

Screens: `/leads` (board|list), `/lead?id=`, `/lead-new/-move/-convert/-edit/-contact/-files/
-delete`, `/leads-bulk`, `/pipelines*`. Tables: `pipelines`, `pipeline_stages`, `leads`,
`lead_stage_history`, `lead_files`.

---

## B. Screen-by-screen catalogue

**`/leads`** — Kanban board (`?v=board`) or list; stage columns; score, stall flags.
**`/lead`** — detail: contact log, next-action, files, linked quotes, score explanation.
**`/lead-new`** — company-or-partner, pipeline, owner, source. **`/lead-convert`** — create/
attach customer + contact + inquiry. **`/leads-bulk`** — bulk move/lost. **`/pipelines`** —
stage admin.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-LEAD-form-001 | Lead create requires **company or partner**; pipeline must have stages; owner resolved to an active user. |
| TC-LEAD-form-002 | Stage move: only OPEN leads; target stage in the lead's pipeline; same-stage rejected. |
| TC-LEAD-form-003 | LOST move **requires a reason** (`lost_reason`). |
| TC-LEAD-form-004 | Duplicate detection warns (partner + open-lead name match), does not block. |
| TC-LEAD-form-005 | WON forces conversion (cannot just tick a box). |

---

## D. Functions & logic

- **Stage machine** (`lead_move`): guards above; WON → `convert` sentinel; time-in-stage
  recorded in `lead_stage_history`. **TC-LEAD-fn-001.**
- **Conversion** (`lead_convert`): creates/attaches a `business_partner` + primary contact,
  raises `crm_inquiries`, back-fills the opportunity partner. **Not transactional** — a
  mid-flow failure leaves partial links (GAP-LEAD-002). **TC-LEAD-fn-002.**
- **Score** (`lead_score`): 0–100 with explanations; `act_last_touch` keys off `partner_id`,
  so unlinked leads never get the "spoken to" signal (GAP). **TC-LEAD-fn-003.**
- **Bulk lost** (`leads_bulk`): defaults `lost_reason` to NO_RESPONSE and does **not** require
  a reason — contradicting the single-move rule (GAP-LEAD-001). **TC-LEAD-fn-004.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| (new) → OPEN | create | company/partner + pipeline w/ stages |
| OPEN → (stage) | move | in-pipeline; not same stage |
| OPEN → CONVERTED | WON → convert | creates customer + contact + inquiry |
| OPEN → LOST | lost | reason required (single move) |
| CONVERTED | — | cannot be deleted |

- **TC-LEAD-life-001:** a WON lead must convert (produces a customer + inquiry).
- **TC-LEAD-life-002:** a CONVERTED lead cannot be deleted.

---

## F. Roles, permissions & data scope

View/edit: `mod.leads.view`/`mod.leads.edit`. Delete: `mod.leads.edit`/master. **Office-only
scoping** (`scope_office_clause('l.office_id')`) — the `leads.sbu` column exists but is **not
applied**, so a BU-restricted user may see other SBUs' leads in their office (GAP-LEAD-003);
NULL-office leads visible to all. FY window applied.

- TC-LEAD-perm-001 (edit without permission) → refused.
- TC-LEAD-scope-001: office-scoped; SBU leakage to verify.

---

## G. Settings

Pipelines & stages (admin), lookup masters (`lead_source`, `next_action`), licence gate
`licence_enabled('sales')` for quoting from a lead. **TC-LEAD-set-001:** a new pipeline's
stages drive the board columns.

---

## H. Cross-module integration

**Clients** (conversion creates partner + contact — MOD-15), **Inquiries** (conversion raises
`crm_inquiries` — MOD-19), **Quotations** (quote from a lead; files carry over — MOD-03),
**Opportunities/deals** (stage sync; partner back-fill), **Ads Pro** (queued, non-blocking).
Idempotency: duplicate detection is advisory.

---

## I. Data integrity & audit

`lead_stage_history` records each move + time-in-stage. Conversion cross-module writes are
wrapped in silent `catch` (partial links possible — GAP). **TC-LEAD-int-010:** a converted
lead links to exactly one customer + inquiry; **TC-LEAD-int-011:** win/loss reasons are
captured (weakened by the bulk-lost default).

---

## J. Reports & outputs

The board/list, the sales pipeline dashboard (win rates, value by stage), stall/ageing, the
score explanation. **TC-LEAD-out-001:** value-by-stage matches the leads.

---

## K. Negative, edge & resilience

A lead with no company/partner (refused); a same-stage move (rejected); a LOST with no reason
(single-move refused; **bulk-lost accepts it** — GAP); a duplicate lead (warned, allowed); a
CONVERTED lead delete (refused); a partial conversion (partial links); an SBU-restricted user
seeing other SBUs' leads.

---

## L. TPIA operational suitability

A standard configurable sales pipeline feeding the TPIA quote/inquiry flow — WON-forces-
conversion keeps the customer master clean, and lead files carry into the quote. Fits the
top-of-funnel need without over-engineering.

## M. Management usefulness

Pipeline value by stage, win rate, stall/ageing, per-owner activity. Confirm the win/loss
data is reliable (fix the bulk-lost reason gap).

## N. UI/UX

Kanban board + list, guided convert, score with reasons, "+ Add new" flows. Terminology via
`T*()`.

## O. Security

Edit/delete gated; conversion creates masters under permission; office-scoped (fix SBU
leakage); duplicate detection advisory.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E stage machine |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | **Gap** | §F SBU leakage |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §D |
| 12 Integration | Y | §H |
| 13 Data integrity | Partial | §I partial links |
| 14 Audit | Y | §I stage history |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | Partial | ads queue |
| 22 Notifications | Partial | followups |
| 23 Offline | N-A | — |
| 24 AI | Partial | lead score |
| 25 Licensing | Y | sales licence |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | FY window |
| 28 Performance | Partial | board at volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-LEAD-001 | (verify) | **Bulk "mark lost" does not require a reason** (defaults NO_RESPONSE) — contradicts the single-move rule and weakens win/loss data. Require a reason on bulk lost too. |
| GAP-LEAD-002 | (verify) | **Conversion is not transactional** — a mid-flow failure can leave a partial customer/contact/inquiry with no surfaced error. Wrap in a transaction or surface + retry. |
| GAP-LEAD-003 | (verify) | **Leads are office-scoped only** (the `sbu` column is not applied) — a BU-restricted user may see other SBUs' leads. Apply SBU scoping. |

---

## R. Traceability

RTM slice: `/leads`, `/lead-*`, `/pipelines*` × dims 1–29 → TC-LEAD-* → results → DEF/GAP.
**Verdict: Complete-with-defects** — stage-guard integrity, conversion atomicity, and SBU
scoping are the exit conditions.
