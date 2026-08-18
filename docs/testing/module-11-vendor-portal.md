# Inspection Ops — Vendor Portal — Test & Documentation Report

> **Prompt 3 · Module MOD-VPORTAL.** Read from `lib/cvp.php` (`cvp_vendor_route`,
> `cvp_vendor_login`/`cvp_vendor_invite`/`cvp_vendor_accept`, `cvp_vendor_require`/
> `cvp_vendor_user`, `cvp_vendor_start_session`, `cvp_vendor_reports`/`cvp_vendor_report`/
> `cvp_vendor_report_pdf`, `cvp_vendor_issues`/`cvp_vendor_issue`/`cvp_vendor_route_issues`,
> `cvp_vendor_perms`/`vcan`/`cvp_vendor_need`, `cvp_visibility_sql`, `cvp_access_live_sql`,
> `cvp_action_centre`/`cvp_notify_*`, `cvp_vendor_log`, `cvp_vendor_admin`), the shared
> visibility engine (`CVP_VISIBILITY_AUDIENCE`), views `vendor/login.php`,
> `vendor/dashboard.php`, `vendor/reports.php`, `vendor/issues.php`.

| | |
|---|---|
| **Module** | Vendor Portal (MOD-VPORTAL) · Area External |
| **Personas** | P-VENDOR (external, `cvp_vendor_users`), P-COORD/P-QA (admin: invite/share/NCR), P-MASTER |
| **Risk weight** | **High** — a second external surface; a visibility/scope error shows a vendor another party's report or NCR |
| **Verdict** | Complete-with-defects (confirm vendor isolation, visibility gating, access expiry, NCR response authenticity) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

The Vendor Portal is the vendor-facing half of the Client/Vendor Portal (CVP). It has its
**own identity** (`cvp_vendor_users`, session key `vuid`, distinct from staff and from
client-portal users), is **off by default** (`vendor_portal_enabled`), and shows a vendor
**only what has been deliberately shared with them** by the **visibility engine**: reports
marked `VENDOR_VISIBLE`/`SHARED`, and **nonconformities raised to them**. The central
feature is the **external NCR loop**: a vendor can view an open nonconformity raised
against them and **respond** (a substantive response, not a bare acknowledgement), which
the QA team then decides.

Access is invite-based (`cvp_vendor_invite`, token + expiry, **access can also expire** via
`cvp_access_live_sql`), accept sets a password and starts a clean session
(`cvp_vendor_start_session`). Everything is logged; an action-centre/notification feed
tells the vendor what needs their attention.

Screens: `/vendor/login`, `/vendor/accept?t=`, `/vendor` (dashboard), `/vendor/reports`,
`/vendor/report`, `/vendor/issues`, `/vendor/issue` (respond), `/vendor/alerts`; admin
`/vendor-users`, `/vendor-user-perms`, `/vendor-settings`.
Tables: `cvp_vendor_users`, `nonconformities` (visibility), `cvp_notifications`, `cvp_audit`.

---

## B. Screen-by-screen catalogue

**`/vendor/login`** — email + password (portal off → `cvp_vendor_off`). **`/vendor/accept`**
— invite token (validity checked in and out), set password twice. **`/vendor`** — dashboard:
recent shared reports, open NCRs to respond to, action-centre, unread count.
**`/vendor/reports`** / **`/vendor/report`** — vendor-visible reports + PDF.
**`/vendor/issues`** — nonconformities raised to them; **`/vendor/issue`** — read one +
**respond**. **Admin `/vendor-users`** — invite / re-invite / toggle / per-user permissions
(`reports`, `issues`) / access expiry / settings.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-VP-form-001 | Login: bad credentials / inactive / **access expired** refused; portal off → redirect. |
| TC-VP-form-002 | Accept: token valid + unexpired + unused; password == confirm. |
| TC-VP-form-003 | NCR response: a **substantive response required** — a bare acknowledgement is refused. |
| TC-VP-form-004 | Only NCRs with `partner_id` = this vendor **and** vendor-visible visibility are listed/openable. |

---

## D. Functions & logic  *(visibility + isolation — highest scrutiny)*

- **Visibility gating** (`cvp_visibility_sql('…','VENDOR')` + `CVP_VISIBILITY_AUDIENCE`):
  a vendor sees a report/NCR only if its visibility maps to the VENDOR audience
  (`VENDOR_VISIBLE`/`SHARED`). **TC-VP-fn-001** — an INTERNAL/CLIENT_VISIBLE report is
  never listed or openable by a vendor.
- **Tenant isolation** (`cvp_vendor_id()` filter on every read): a vendor cannot open
  another vendor's report/NCR by id (returns nothing → 404). **TC-VP-fn-002.**
- **Access expiry** (`cvp_access_live_sql`): a vendor whose `access_expires` has passed is
  treated as signed-out on every request, not just at login. **TC-VP-fn-003.**
- **NCR response loop** (`cvp_vendor_issue`): ownership + visibility gated in the WHERE
  clause; the response is recorded and routed to QA for a decision. **TC-VP-fn-004** —
  the response is attributable to this vendor user and confined to their NCRs.
- **Session hygiene** (`cvp_vendor_start_session`): fresh `vuid` session, no staff/client
  identity, fresh CSRF; CSRF on every POST. **TC-VP-fn-005.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| invited → active | accept + password | token valid; within access window |
| NCR raised (vendor-visible) → responded | vendor response | ownership + visibility; substantive text |
| responded → decided | QA decision | staff side (MOD-12) |
| active → expired/disabled | time / admin | access window / toggle |

- **TC-VP-life-001:** an expired-access vendor cannot reach any vendor route.
- **TC-VP-life-002:** a vendor cannot respond to a closed or not-visible NCR.

---

## F. Roles, permissions & data scope

Separate vendor auth. Per-user vendor permissions (`VENDOR_PERMS`: `reports`, `issues`)
gate screens (`cvp_vendor_need`/`vcan`). Everything scoped by `vendor_id` **and**
visibility. Admin (invite/perms/toggle/settings): `cvp_can_manage` = master /
`mod.portal.edit` (or the vendor-portal equivalent).

- TC-VP-perm-001: a vendor without `issues` cannot open/respond to NCRs (crafted POST).
- TC-VP-perm-002: a vendor without `reports` cannot list/open reports.
- TC-VP-scope-001: a vendor never sees another vendor's or a client-only report.

---

## G. Settings

`vendor_portal_enabled` (master off/on), invite expiry, **access window** per user,
per-user permissions, report/NCR **visibility** (set on the staff side), notification
events. **TC-VP-set-001:** portal off ⇒ all vendor routes redirect out; **TC-VP-set-002:**
changing a report's visibility from VENDOR_VISIBLE removes it from the vendor's list.

---

## H. Cross-module integration

**IDEMS/Vendor Assessment** (vendor-visible reports; vendor 360), **NCR** (raised to
vendor → external response loop → QA decision — MOD-12), **Vendors master** (contacts →
vendor users; qualification), **Notifications/CVP** (action centre). Shared with the
**client portal** through one visibility engine — the audience mapping is the guard that
keeps the two apart. Idempotency: a double response must not create conflicting records —
TC-VP-int-001.

---

## I. Data integrity & audit

`cvp_audit` logs login/logout/report-view/NCR-response with IP + time. The vendor's NCR
response is stored against the nonconformity and visible to QA. A vendor can never write
outside their `vendor_id`/visible set. **TC-VP-int-010:** every vendor write is
attributable and confined; a report's visibility is the single source of what is shared.

---

## J. Reports & outputs

The shared report PDF (vendor copy, only vendor-visible), the NCR response receipt, the
action-centre feed. No numbers minted by the vendor. **TC-VP-out-001:** the PDF a vendor
downloads is only their own vendor-visible report.

---

## K. Negative, edge & resilience

Guessing another vendor's or a client-only report id (404, no leak); an INTERNAL report a
vendor tries to open; an expired-access session; a bare "noted" NCR response (refused);
responding to a not-visible/closed NCR; portal disabled mid-session; a CSRF-less POST.

---

## L. TPIA operational suitability

Closes the vendor side of the assurance loop: the manufacturer/vendor sees the
nonconformities raised against them and responds on the record, and sees the reports
shared with them — without any view of other parties' data. Visibility is explicit and
staff-controlled, matching how a TPIA decides what a vendor may see.

## M. Management usefulness

Open-NCR-awaiting-response and vendor engagement surface for QA; the response feeds the
NCR/CAPA decision; vendor 360 aggregates performance. Confirm the vendor's visible set
matches what staff intended to share.

## N. UI/UX

Focused vendor dashboard (reports + issues + action centre), a clear respond form that
insists on substance, simple invite flow. Terminology via `T*()`.

## O. Security

Second external surface — same rigor as the client portal: separate identity, expiring
single-use invites, access-window enforcement on every request, fresh session on accept,
CSRF on POSTs, strict `vendor_id` + visibility scoping, ownership proven before a
response, permissions enforced server-side, off by default. Visibility-audience isolation
between vendor and client portals is release-gating.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | **Priority** | §K isolation/visibility |
| 8 Roles | **Priority** | §F vendor perms |
| 9 Scope | **Priority** | §D vendor_id + visibility |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §H NCR loop |
| 12 Integration | Y | §H |
| 13 Data integrity | Y | §I |
| 14 Audit | Y | §I cvp_audit |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O external surface |
| 21 Import | N-A | — |
| 22 Notifications | Y | action centre |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | vendor portal toggle |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | invite + access expiry |
| 28 Performance | Partial | at vendor volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-VP-001 | (verify — **Critical**) | Confirm **visibility + vendor isolation**: a vendor can never list or open an INTERNAL/CLIENT_VISIBLE report or another vendor's report/NCR, on every route including the PDF endpoint. |
| GAP-VP-002 | (verify — Major) | Confirm **access-window enforcement** on every request (not just login) and clean single-use invites/sessions. |
| GAP-VP-003 | (verify — Major) | Confirm the **NCR response** is authenticated as the owning vendor, insists on substance, and cannot be posted to a not-visible/closed NCR via a crafted request. |
| GAP-VP-004 | — | Confirm the visibility engine keeps the vendor and client audiences strictly separate (a SHARED item is intentional; nothing leaks by default). |

---

## R. Traceability

RTM slice: `/vendor/*`, `/vendor-users`, report + NCR routes × dims 1–29 → TC-VP-* →
results → DEF/GAP. **Verdict: Complete-with-defects** — vendor isolation + visibility
gating, access-window enforcement, and NCR-response authenticity are the exit conditions
(second external attack surface).
