# Inspection Ops — Inquiries — Test & Documentation Report

> **Prompt 3 · Module MOD-INQUIRIES.** Read from `lib/crm.php` (`ops_crm_inquiries`,
> `crm_inquiry_get`, `crm_next_inquiry_no`, `INQUIRY_STATUS`, quote-from-inquiry linkage),
> conversion source `lib/leads.php` (`lead_convert` → `crm_inquiries`). Views
> `crm/inquiry_list.php`, `crm/inquiry_form.php`.

| | |
|---|---|
| **Module** | Inquiries (MOD-INQUIRIES) · Area Sales |
| **Personas** | P-MKT/P-BDM (capture), P-COORD (view), P-MASTER |
| **Risk weight** | Medium — the enquiry that seeds a quote; capture quality drives the funnel |
| **Verdict** | Complete-with-defects (confirm capture validation, view scoping, status flow) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

An inquiry (`crm_inquiries`) is the recorded enquiry that precedes a quotation. It is created
directly, or raised automatically when a **lead converts** (source CLIENT/lead), and moves
OPEN → QUOTED → DROPPED — flipping to **QUOTED** automatically when a quote is created from it
or accepted. It seeds `/quote-new?inquiry=`. The register is SBU-scoped (`crm_inquiries` has
no `office_id`).

Screens: `/inquiries`, `/inquiry-new`, `/inquiry-edit`. Tables: `crm_inquiries`.

---

## B. Screen-by-screen catalogue

**`/inquiries`** — SBU-scoped register; search/filter. **`/inquiry-new` / `/inquiry-edit`** —
client (or free text), contact, subject, requirement/scope, inspection type, product category,
SBU, source, expected value, status.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-INQ-form-001 | **Currently: no required fields** (client-or-server) — a fully blank inquiry can be created (GAP-INQ-001). Expected: at least a client/subject/requirement. |
| TC-INQ-form-002 | Client name authoritative when a client is picked; free text otherwise. |
| TC-INQ-form-003 | Status ∈ OPEN/QUOTED/DROPPED. |
| TC-INQ-form-004 | Inquiry number unique (`crm_next_inquiry_no`). |

---

## D. Functions & logic

- **Auto-QUOTED**: creating a quote from an inquiry, or accepting that quote, flips the
  inquiry to QUOTED. **TC-INQ-fn-001.**
- **Lead conversion** (`lead_convert`): raises a `crm_inquiries` row with source lead/CLIENT.
  **TC-INQ-fn-002.**
- **Numbering** (`crm_next_inquiry_no`): unique inquiry codes. **TC-INQ-fn-003.**
- **Status is a free select** with no transition guards. **TC-INQ-fn-004.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| (new) → OPEN | create | (none enforced — GAP) |
| OPEN → QUOTED | quote created/accepted | automatic |
| OPEN → DROPPED | manual | free select |

- **TC-INQ-life-001:** an inquiry flips to QUOTED when its quote is created/accepted.
- **TC-INQ-life-002:** status has no guards — any transition is accepted.

---

## F. Roles, permissions & data scope

Add/edit: `mod.inquiries.edit`. View: `mod.inquiries.view` (nav-gated; **the list route has
no explicit `ops_require` for view** — relies on the dispatcher/nav — GAP-INQ-002). SBU-only
scoping (`scope_sbus`); empty-SBU rows visible to all.

- TC-INQ-perm-001 (add without `mod.inquiries.edit`) → refused.
- TC-INQ-scope-001: SBU-scoped list; verify the view route is gated server-side.

---

## G. Settings

Lookup masters (`inspection_type`, `product`, `sbu`, source). **TC-INQ-set-001:** masters
populate the pickers.

---

## H. Cross-module integration

**Leads** (conversion raises an inquiry — MOD-17), **Quotations** (quote from an inquiry;
auto-QUOTED — MOD-03), **Clients** (client link), **Project costing** (via the quote).
Idempotency: one inquiry per capture (unique number).

---

## I. Data integrity & audit

Inquiry ↔ quote linkage via `quotation_id`/`inquiry_id`; status reflects the quote state.
Weak capture validation risks empty/duplicate enquiries. **TC-INQ-int-010:** an inquiry with
a created quote shows QUOTED; **TC-INQ-int-011:** no duplicate inquiry numbers.

---

## J. Reports & outputs

The register/export; the inquiry feeds the quote prefill. No document of its own.
**TC-INQ-out-001:** a quote raised from an inquiry carries the client/subject/requirement.

---

## K. Negative, edge & resilience

A fully blank inquiry (currently accepted — GAP); a duplicate; an inquiry viewed without the
view route being gated; an inquiry auto-flipped to QUOTED then the quote lost.

---

## L. TPIA operational suitability

Captures the pre-quote enquiry a TPIA logs (scope, inspection type, product), threaded to the
quote and to leads. Light but sufficient; capture discipline is the main improvement.

## M. Management usefulness

Enquiry volume, source mix, conversion-to-quote. Confirm QUOTED reflects real quotes.

## N. UI/UX

Simple capture form, quote-from-inquiry. Terminology via `T*()`.

## O. Security

Add/edit gated; **verify the view route is server-gated** (currently nav-reliant); SBU-scoped.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | **Gap** | §C no required fields |
| 7 Negative | Y | §K |
| 8 Roles | Partial | §F view gate |
| 9 Scope | Y | §F SBU |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §D auto-QUOTED |
| 12 Integration | Y | §H |
| 13 Data integrity | Partial | §I capture |
| 14 Audit | Partial | §I |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Partial | §O view gate |
| 21 Import | N-A | — |
| 22 Notifications | N-A | — |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | sales |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | FY window |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-INQ-001 | (verify) | **Inquiry capture has no validation** — no required fields client- or server-side; a fully blank inquiry is accepted. Require at least a client/subject/requirement. |
| GAP-INQ-002 | (verify) | The **inquiry list route has no explicit view `ops_require`** — it relies on nav gating. Add a server-side view check so a crafted `/inquiries` request is authorised. |

---

## R. Traceability

RTM slice: `/inquiries`, `/inquiry-*` × dims 1–29 → TC-INQ-* → results → DEF/GAP.
**Verdict: Complete-with-defects** — capture validation and server-side view gating are the
exit conditions.
