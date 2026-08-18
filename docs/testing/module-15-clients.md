# Inspection Ops — Clients — Test & Documentation Report

> **Prompt 3 · Module MOD-CLIENTS.** Read from `lib/ops.php` (`business_partners`
> is_client, `partner_hold`/`partner_hold_set`/`partner_hold_blocks`,
> `ops_client_holds`, `partner_missing`/`partner_missing_text`, `clients_list`,
> directory/partner handlers), `lib/customer360.php` (`ops_customer360`), `lib/portal.php`
> (`portal_contacts_for`, client-user linkage), `lib/crm.php` (client → quote/order/call).
> Builds on MOD-MASTERS (the base partner record).

| | |
|---|---|
| **Module** | Clients (MOD-CLIENTS) · Area Directory |
| **Personas** | P-COORD/P-MKT (maintain), P-BM/P-MASTER (hold/block), P-ACCTS (tax identity), P-INSP (read) |
| **Risk weight** | **Medium-High** — the client identity feeds every call, quote, report and invoice; hold/block is a commercial control |
| **Verdict** | Complete-with-verification (confirm hold gate, tax identity completeness, 360 scoping) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. Blocked/on-hold client gate (B1) verified. |

---

## A. Module overview

A client is a `business_partners` row with `is_client=1`, carrying legal/display name,
code, **tax identity** (GSTIN/PAN/state), contacts, sites/addresses, inspection types,
currency/payment terms, and a **hold/block** status. The client-specific controls are:
the **blocked/on-hold gate** (B1) — a BLOCKED client refuses new quotes/calls for
non-managers, a HOLD warns — the **client holds** admin screen, the **master-gap** check
(`partner_missing` — a client missing tax/contact details is refused at the first forward),
**Customer 360** (a unified view of the client's quotes, orders, calls, jobs, reports,
invoices, complaints), and the **portal** linkage (contacts → `client_users`, MOD-10).

Screens: `/directory` (client register), `/partner-new` `/partner-edit`, `/partner?id=`
(detail), `/client-holds` (hold/block admin), `/customer360`, `/client-quotes`.
Tables: `business_partners`, `partner_contacts`, site/address tables, `client_users`.

---

## B. Screen-by-screen catalogue

**Directory / register** — clients scoped by office/SBU; search; export. **`/partner-*`** —
identity, tax, contacts (primary flag, email/phone), sites, inspection types, terms.
**`/client-holds`** — set HOLD / BLOCKED / clear, with a reason; lists held/blocked
clients (names via `COALESCE(NULLIF(display_name,''),legal_name)`). **`/customer360`** —
the 360 view. **`/client-quotes`** — the client's quotations.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-CLI-form-001 | Legal name required; display name falls back to legal where blank (register never shows an empty name). |
| TC-CLI-form-002 | GSTIN/PAN/state well-formed; state drives the invoice tax split (MOD-09). |
| TC-CLI-form-003 | At least one contact; primary flag; email valid for portal invite. |
| TC-CLI-form-004 | Hold/block: reason required to set HOLD/BLOCKED; clear restores. |
| TC-CLI-form-005 | Editing an existing client is never blocked by the master-gap check (only the first forward is gated). |

---

## D. Functions & logic

- **Hold/block gate** (`partner_hold`, `partner_hold_blocks`): BLOCKED ⇒ a non-manager
  cannot raise a new quote/call; HOLD ⇒ warned; a manager may proceed. **TC-CLI-fn-001**
  (blocked refused for non-manager), **TC-CLI-fn-002** (hold warns), **TC-CLI-fn-003**
  (gate holds on a crafted quote/call POST, not just the button).
- **Master-gap** (`partner_missing`/`partner_missing_text`): a client missing tax/contact
  details is refused at the first **forward** with the exact gap named. **TC-CLI-fn-004.**
- **Customer 360** (`ops_customer360`): aggregates the client's records, scoped to the
  client. **TC-CLI-fn-005** — 360 shows only this client's data.
- **Name resolution:** display falls back to legal everywhere (`COALESCE(NULLIF(...))`).
  **TC-CLI-fn-006** — no blank names in lists.

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| (new) → ACTIVE | create | legal name |
| ACTIVE → HOLD/BLOCKED | client-holds | reason required |
| HOLD/BLOCKED → ACTIVE | clear | authorised |
| contact → portal user | invite | MOD-10 |

- **TC-CLI-life-001:** a BLOCKED client cannot start new commercial work until cleared.
- **TC-CLI-life-002:** a hold/block change is recorded with who/why.

---

## F. Roles, permissions & data scope

Maintain: coordinator/marketing (`crm.partner.edit`/`mod.clients.edit`). Hold/block:
manager/master. Tax identity: accounts. Register scoped by office/SBU. Portal invite:
`mod.portal.edit`/master.

- TC-CLI-perm-001 (unauthorised edit) → refused.
- TC-CLI-perm-002 (hold/block by non-manager) → refused.
- TC-CLI-scope-001: only own-office clients in the register/360.

---

## G. Settings

Inspection-type master, currency/payment defaults, hold reasons, portal invite policy,
per-client man-month basis override, per-client requalification of clients (N-A — that is
vendors). **TC-CLI-set-001:** a per-client term override carries into a quote/call.

---

## H. Cross-module integration

**Quotations/Calls/Jobs** (client identity + hold gate + master-gap), **Invoicing** (GSTIN/
state → tax split), **Client portal** (contacts → users; site scope — MOD-10), **Complaints**
(against a client engagement), **Project costing/Profit** (by client). Idempotency:
duplicate-partner detection (`/duplicates`) prevents two records for one client.

---

## I. Data integrity & audit

Hold/block changes and identity edits recorded; tax identity is the single source for the
invoice split; contacts drive portal identity. **TC-CLI-int-010:** a client's state used
on the invoice equals the client master; **TC-CLI-int-011:** no duplicate active clients
for one legal entity (merge/dedupe).

---

## J. Reports & outputs

The client register/export, Customer 360, and the client's identity on every downstream
document (quote/report/invoice header). **TC-CLI-out-001:** the client block on a PDF
matches the master (name, GSTIN, address).

---

## K. Negative, edge & resilience

A client with a blank display name (falls back to legal); a BLOCKED client on a crafted
quote POST; a client missing tax details at first forward; a duplicate client; a client
with no contacts (no portal invite possible); a HOLD cleared without authority (refused).

---

## L. TPIA operational suitability

Models the customer master a TPIA needs: tax identity for compliant invoicing, contacts
and sites for scheduling and portal access, inspection-type scoping, and a commercial
hold/block that stops work for a defaulting or disputed client — an audit-flagged control,
not a silent flag.

## M. Management usefulness

Customer 360 gives a single client view (pipeline → delivery → billing → complaints);
holds surface commercial risk; the register exports for CRM. Confirm 360 totals reconcile
with the source registers.

## N. UI/UX

One directory, inline "+ Add new" from quote/call forms, clear hold/block banners, 360
tabs. Terminology (client/customer via `T*()`).

## O. Security

Edit/hold authorised server-side; hold/block gate enforced on quote/call POSTs; tax
identity edit gated; 360 scoped; portal invite gated; duplicate-merge audited.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E hold/block |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §H gate |
| 12 Integration | **Priority** | §H every commercial module |
| 13 Data integrity | Y | §I tax identity |
| 14 Audit | Y | §I holds |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M 360 |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | Y | partner import/dedupe |
| 22 Notifications | Partial | portal invite |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | N-A | — |
| 26 Terminology | Y | — |
| 27 Time/FY | N-A | — |
| 28 Performance | Partial | register at volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-verification.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-CLI-001 | (verify) | Confirm the **hold/block gate** is enforced on the quote/call POST (crafted request from a non-manager refused), not only by hiding the button. |
| GAP-CLI-002 | (verify) | Confirm the **master-gap** check fires at the first forward and names the exact missing detail, and that editing an existing client is never blocked by it. |
| GAP-CLI-003 | (verify) | Confirm Customer 360 is strictly client-scoped and its totals reconcile with the source registers. |

---

## R. Traceability

RTM slice: `/directory`, `/partner-*`, `/client-holds`, `/customer360` × dims 1–29 →
TC-CLI-* → results → DEF/GAP. **Verdict: Complete-with-verification** — hold-gate
enforcement, master-gap correctness, and 360 scoping are the exit conditions.
