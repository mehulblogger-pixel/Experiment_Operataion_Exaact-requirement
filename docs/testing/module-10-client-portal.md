# Inspection Ops — Client Portal — Test & Documentation Report

> **Prompt 3 · Module MOD-PORTAL.** Read from `lib/portal.php` (`portal_route`,
> `portal_login`/`portal_accept`/`portal_invite`, `portal_require`/`portal_user`,
> `portal_start_session`, `portal_calls`/`portal_jobs`/`portal_reports`/`portal_invoices`,
> `portal_report`/`portal_report_pdf`, `portal_site_sql`/`portal_site_ids`,
> `portal_perms`/`portal_need`/`pcan`, `portal_request_create`/`portal_request_to_call`,
> `rcr_decide`/`rcr_current`/`rcr_history`, `ops_portal_admin`, `portal_log`), views
> `portal/login.php`, `portal/dashboard.php`, `portal/reports.php`,
> `portal/report_decide.php`, `portal/deputations.php`, admin `portal_users.php`.

| | |
|---|---|
| **Module** | Client Portal (MOD-PORTAL) · Area Client-facing |
| **Personas** | P-CLIENT (external, `client_users`), P-COORD/P-BM (admin: invite/perms), P-MASTER |
| **Risk weight** | **High** — an external-facing surface; a scope leak exposes another client's reports; the acceptance here gates release |
| **Verdict** | Complete-with-defects (confirm cross-tenant isolation, invite/session security, decision authenticity) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

A separate, **off-by-default** (`portal_enabled`) external surface where a client signs
in with their **own identity** (`client_users`, distinct from staff `users`) and sees
**only their own** calls, jobs, issued reports, invoices and deputations — everything
scoped by `client_id` **and** site permissions (`portal_site_sql`). The portal is
**read-mostly**: a client can **accept/reject an issued report** (`rcr_decide` → sets
`client_decision`, which feeds the Release-Note gate, MOD-08), **approve deputation
attendance** (man-days that support billing), read notifications, and **request** work
(a request becomes a call only when a coordinator accepts it — *a client cannot create
work directly*).

Access is by **invite**: staff invite a contact (`portal_invite`, magic token + expiry);
the client sets a password (`portal_accept`); a fresh session with no staff identity is
started (`portal_start_session`, new CSRF). Everything is logged (`portal_audit`).

Screens: `/portal/login`, `/portal/accept?t=`, `/portal` (dashboard), `/portal/calls`,
`/portal/call`, `/portal/reports`, `/portal/report`, `/portal/report-decision`,
`/portal/deputations`, `/portal/dep-approve`, `/portal/alerts`, `/portal/requests`;
admin `/portal-users`, `/portal-user-perms`, `/portal-settings`.
Tables: `client_users`, `portal_requests`, `portal_audit`, `portal_contacts`.

---

## B. Screen-by-screen catalogue

**`/portal/login`** — email + password (portal off → `portal_off`). **`/portal/accept`** —
invite token (checked in and out; used/expired says so immediately), set password twice.
**`/portal`** — dashboard (their counts/feed). **`/portal/reports`** — their issued
reports, each with the client decision state; **`/portal/report`** — read + PDF
(`portal_report_pdf`). **`/portal/report-decision`** — accept / reject (reason), history.
**`/portal/deputations`** + **`/dep-approve`** — attendance periods; approve/return the
man-day quantity. **`/portal/alerts`** — notification feed (mark read). **Requests** —
raise a work request. **Admin `/portal-users`** — invite / re-invite / toggle / per-user
permissions / site scope / settings.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-PORTAL-form-001 | Login: bad credentials refused; inactive user refused; portal-off → redirect. |
| TC-PORTAL-form-002 | Accept: token must be valid + unexpired + unused; password == confirm; a used/expired token is refused **before** password entry. |
| TC-PORTAL-form-003 | Report decision: ACCEPTED or REJECTED; **reject requires a reason**. |
| TC-PORTAL-form-004 | Deputation approval: APPROVED or RETURNED only from SUBMITTED/CLIENT_REVIEW; rep name captured. |
| TC-PORTAL-form-005 | Work request: fields captured; it creates a **request**, not a call. |

---

## D. Functions & logic  *(isolation — highest scrutiny)*

- **Tenant isolation:** every read helper filters by `portal_partner_id()` **and**
  `portal_site_sql()`. **TC-PORTAL-fn-001** — a client cannot open another client's
  call/report/invoice by guessing an id (`portal_call`/`portal_report` return nothing →
  404); **TC-PORTAL-fn-002** — a report outside the client's **site** permissions is not
  listed or openable.
- **Report decision → RN gate** (`rcr_decide`): sets `client_decision`; `portal_report()`
  is what proves ownership and `rcr_decide` trusts it — nothing else may call it with an
  unchecked row. **TC-PORTAL-fn-003** — ACCEPTED/REJECTED is recorded, authenticated as
  this client, and drives the RN blocker (MOD-08).
- **Deputation approval → billing** (`pdso_att_approval_set_status`): the client-approved
  man-days support the invoice. **TC-PORTAL-fn-004.**
- **Request → call** (`portal_request_to_call`): a coordinator converts a request; the
  client never writes into operational tables directly. **TC-PORTAL-fn-005.**
- **Session hygiene** (`portal_start_session`): fresh session id, no staff identity, fresh
  CSRF; `portal_csrf_or_die` on every POST. **TC-PORTAL-fn-006.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| invited → active | accept + set password | token valid/unexpired/unused |
| report issued → ACCEPTED/REJECTED | client decision | ownership proven; reject reason |
| deputation SUBMITTED → APPROVED/RETURNED | client approval | only from SUBMITTED/CLIENT_REVIEW |
| request → call | coordinator accepts | client cannot self-convert |
| active → disabled | admin toggle | staff only |

- **TC-PORTAL-life-001:** an expired/used invite cannot activate an account.
- **TC-PORTAL-life-002:** a disabled portal user cannot log in.
- **TC-PORTAL-life-003:** a client decision on an already-decided report follows the
  current-decision rule (`rcr_current`) — no silent re-flip.

---

## F. Roles, permissions & data scope

Portal auth is entirely separate from staff auth. Per-user portal permissions
(`portal_perms`: calls / reports / reports.decide / invoices / deputation /
deputation.approve) gate each screen (`portal_need`/`pcan`). Site scope
(`portal_site_ids`) narrows to permitted sites. Admin (invite/perms/toggle/settings):
`portal_can_manage` = master / `mod.portal.edit`.

- TC-PORTAL-perm-001: a client without `reports.decide` cannot POST a decision (crafted).
- TC-PORTAL-perm-002: a client without `deputation.approve` cannot approve attendance.
- TC-PORTAL-scope-001: site-scoped client sees only permitted sites' calls/reports.

---

## G. Settings

`portal_enabled` (master off/on), invite expiry, portal base URL, per-user permissions &
site scope, notification events (CVP). **TC-PORTAL-set-001:** portal off ⇒ every portal
route redirects out; **TC-PORTAL-set-002:** a permission removed hides + blocks that
screen server-side.

---

## H. Cross-module integration

**IDEMS** (issued reports delivered; acceptance → RN gate — MOD-08), **Jobs/Deputation**
(attendance approval → billing — MOD-05/09), **Calls** (requests → calls; scoped
visibility), **Invoicing** (client sees their invoices), **Clients** (contacts →
`client_users`; site scope), **Notifications/CVP** (alert feed). Idempotency: a
double-submitted decision must not create conflicting states — TC-PORTAL-int-001.

---

## I. Data integrity & audit

`portal_audit` logs login/logout/dashboard/decision/approval with IP + time. The client
decision is stored against the report and is the same value the RN gate reads. A client
can never write outside their partner scope. **TC-PORTAL-int-010:** every portal write is
attributable to a `client_user` and confined to their partner/sites.

---

## J. Reports & outputs

The issued report PDF (client copy), the invoice list, the decision receipt/flash, the
notification feed. No new numbers minted by the client. **TC-PORTAL-out-001:** the PDF a
client downloads is the issued, sealed version and only for their own report.

---

## K. Negative, edge & resilience

Guessing another client's report/call/invoice id (404, no leak); an expired/used invite;
a decision without `reports.decide`; a reject with no reason; approving a deputation not
in an approvable state; a client trying to create a call directly; portal disabled
mid-session; a CSRF-less POST; a site-scoped user reaching an out-of-scope site.

---

## L. TPIA operational suitability

Gives the client the transparency a TPIA engagement needs — visibility of their
inspections and issued reports, a formal acceptance/rejection that feeds release, and
attendance approval for deputations that supports honest billing — without letting the
client write into the operational system. Off by default so a tenant opts in.

## M. Management usefulness

Client acceptance status surfaces on the report (drives RN); attendance approvals
evidence billable man-days; the audit log shows client engagement. Confirm acceptance
state matches what staff see.

## N. UI/UX

Simple client dashboard, one-click accept/reject with reason, clear invite flow, alert
feed. Terminology (report/call/client via `T*()`) carries through to the portal.

## O. Security

**The highest-exposure module.** Separate identity store; magic-token invites with expiry
and single use; fresh session on accept with no staff identity; CSRF on every POST; strict
partner + site scoping on every read; ownership proven before a decision; permissions
enforced server-side (not hidden links); portal off by default. Cross-tenant isolation is
the release-gating concern.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | **Priority** | §K isolation cases |
| 8 Roles | **Priority** | §F portal perms |
| 9 Scope | **Priority** | §D/§F partner + site |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §H (decision→RN) |
| 12 Integration | Y | §H |
| 13 Data integrity | Y | §I |
| 14 Audit | Y | §I portal_audit |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O external surface |
| 21 Import | N-A | — |
| 22 Notifications | Y | alert feed |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | portal toggle |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | invite expiry |
| 28 Performance | Partial | at client volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-PORTAL-001 | (verify — **Critical**) | Confirm **cross-tenant isolation** end-to-end: a crafted id for another client's call/report/invoice/deputation returns 404 with no data leak, on every route and in the PDF endpoint. |
| GAP-PORTAL-002 | (verify — Major) | Confirm **invite/session security**: tokens are single-use + expiring, accept starts a clean session with a fresh CSRF, and no staff identity survives into a portal session. |
| GAP-PORTAL-003 | (verify — Major) | Confirm the **report decision** is authenticated as the owning client and cannot be spoofed to clear the RN acceptance blocker (ties to GAP-RN-004). |
| GAP-PORTAL-004 | — | Confirm every portal permission and site scope is enforced on the POST, not only by hiding the link. |

---

## R. Traceability

RTM slice: `/portal/*`, `/portal-users`, decision & approval routes × dims 1–29 →
TC-PORTAL-* → results → DEF/GAP. **Verdict: Complete-with-defects** — cross-tenant
isolation, invite/session security, and decision authenticity are the exit conditions
(this is the app's principal external attack surface).
