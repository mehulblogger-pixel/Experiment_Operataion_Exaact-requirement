# Inspection Ops — Confidentiality — Test & Documentation Report

> **Prompt 3 · Module MOD-CONF.** Read from `lib/confidentiality.php` (`conf_undertaking_live`/
> `conf_coverage`, `conf_ndas`/`conf_nda_obligation_ends`, `conf_breach_create`/
> `conf_breach_close_missing`, `ops_confidentiality`), `lib/disclosure.php`
> (`disclosure_save`, `ops_disclosure`), the visibility engine `lib/cvp.php`
> (`cvp_can_see`/`cvp_visible_codes`/`cvp_visibility_sql`, `CVP_VISIBILITY_AUDIENCE`),
> codes in `lib/ncdca.php`. Views `confidentiality.php`, `conf_breach.php`, `disclosure.php`.

| | |
|---|---|
| **Module** | Confidentiality (MOD-CONF) · Area Accreditation |
| **Personas** | P-QA/P-QM (undertakings, breaches), P-ADMIN (NDAs, disclosure), P-MASTER |
| **Risk weight** | **High** — ISO/IEC 17020 §4.2; a breach or a wrong disclosure damages client trust and accreditation |
| **Verdict** | Complete-with-defects (confirm breach close gate, visibility gating, NDA enforcement) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Three cooperating parts: (1) **confidentiality undertakings** (`confidentiality_undertakings`)
— per-person signings (employee/subcon/contractor/visitor), renewal = a new row, coverage
engine reports who is **not** covered; (2) **client NDAs** (`client_ndas`) — recorded with a
`survives_months` tail so the obligation outlives the contract (a filing record — **not
enforced by the visibility engine**, GAP); and (3) a **breach register**
(`confidentiality_breaches`) — a breach auto-raises a **MAJOR NCR (clause 4.2)**, moves
OPEN → CONTAINED → CLOSED, and **cannot close** without containment + a decision on whether
the affected party was told + (if not) a reason + the linked NCR closed. Plus **advance
public-disclosure consent** (`disclosure_consents`, §4.2) and the shared **classification/
visibility engine** (INTERNAL / CLIENT_VISIBLE / VENDOR_VISIBLE / RESTRICTED / MGMT_ONLY)
that gates what each portal audience may read (fail-closed for unknown codes).

Screens: `/confidentiality?t=people|ndas|breaches`, `/conf-*`, `/conf-breach?id=`,
`/disclosure*`. Tables: `confidentiality_undertakings`, `client_ndas`,
`confidentiality_breaches`, `disclosure_consents`; consumes `nonconformities.visibility`.

---

## B. Screen-by-screen catalogue

**`/confidentiality`** — tabs: **people** (undertaking coverage, ok/lapsed/none), **NDAs**
(client-imposed, obligation-end), **breaches** (office-scoped register). **`/conf-breach`** —
breach detail: containment, party-told (yes/no + note), regulator-told, linked NCR, close
(gated). **`/disclosure`** — advance public-disclosure consent (requested/consented/
declined/withdrawn).

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-CONF-form-001 | Undertaking: person required; kind whitelisted; file MIME-gated. |
| TC-CONF-form-002 | NDA: partner required; `survives_months` ≥ 0. |
| TC-CONF-form-003 | Breach: `what_got_out` required; auto-raises a MAJOR NCR. |
| TC-CONF-form-004 | Breach close: containment + party-told decision + (if no) reason + NCR closed. |

---

## D. Functions & logic  *(breach + visibility — highest scrutiny)*

- **Coverage** (`conf_coverage`): joins inspectors to their live/lapsed undertaking; lapsed
  reported as lapsed, not silently signed. **TC-CONF-fn-001** — an uncovered person surfaces.
- **Breach → NCR** (`conf_breach_create`): every breach auto-raises a MAJOR NCR (clause 4.2)
  and links it. **TC-CONF-fn-002.**
- **Breach close gate** (`conf_breach_close_missing`): containment + party-told decision +
  reason-if-no + **NCR closed first**. **TC-CONF-fn-003** — each missing element blocks close.
- **Visibility gate** (`cvp_visibility_sql`, `CVP_VISIBILITY_AUDIENCE`): every external
  portal read passes the code→audience map; unknown/blank → fail-closed (`1=0`); reports
  reach vendors only when staff set `vendor_visible=1`. **TC-CONF-fn-004** — a RESTRICTED/
  INTERNAL item is never returned to a portal audience.
- **Disclosure consent** (`disclosure_save`): records advance §4.2 public-domain consent,
  logged. **TC-CONF-fn-005.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| undertaking → ok/lapsed/none | derived | signed on/before + not expired |
| breach OPEN → CONTAINED → CLOSED | actions | close gate + NCR closed |
| disclosure REQUESTED → CONSENTED/DECLINED/WITHDRAWN | decision | — |
| classification set | on the record | code→audience map |

- **TC-CONF-life-001:** a breach cannot close while its NCR is open or the party-told
  decision is missing.
- **TC-CONF-life-002:** a lapsed undertaking is reported, not treated as signed.

---

## F. Roles, permissions & data scope

View (`conf_can_view`): `mod.confidentiality.view`/`mod.identity.view`/master. Manage:
`mod.confidentiality.edit`/`mod.identity.edit`/master. Disclosure adds `mod.datacontrol.
view`/`mod.clients.view`. Breaches office-scoped. Portal audiences kept in separate
identity tables (vendor `vuid` never returned by `current_user`).

- TC-CONF-perm-001 (manage without permission) → refused.
- TC-CONF-scope-001: breaches office-scoped.

---

## G. Settings

`vendor_portal_enabled`, visibility codes (`issue_visibility` lookup), `access_expires` per
portal user. **TC-CONF-set-001:** changing a report's visibility from VENDOR_VISIBLE removes
it from the vendor's list.

---

## H. Cross-module integration

**NCR** (breach → MAJOR NCR; close dependency — MOD-12), **Portals** (visibility gate for
client/vendor reads — MOD-10/11), **Personnel** (undertaking coverage from inspectors),
**Clients** (NDAs; disclosure consent), **Data control** (disclosure register). Idempotency:
a breach raises one NCR.

---

## I. Data integrity & audit

Breach ↔ NCR bidirectional link; disclosure logged via `idems_log`. Undertaking state is
**computed only, never persisted, with no expiry cron** — a lapse surfaces only when the
screen is opened (GAP). NDA terms are a **filing record not enforced** by the visibility
engine (GAP-CONF-002). Breach/NDA file uploads not all MIME-validated (GAP).
**TC-CONF-int-010:** a breach's NCR must close before the breach closes.

---

## J. Reports & outputs

Coverage report, NDA obligation-end, breach register, disclosure register, the visibility
gate on every portal read. **TC-CONF-out-001:** the breach record shows containment, party
notified, and the linked NCR.

---

## K. Negative, edge & resilience

A lapsed undertaking (reported); a breach closed with an open NCR (refused); a breach with
no party-told decision (refused); an NDA past its survival (obligation-end shown but not
enforced); a RESTRICTED report requested via a portal (fail-closed); a LEGAL/PUBLICATION
breach closed without regulator notification (allowed — GAP); a hard-deleted disclosure.

---

## L. TPIA operational suitability

Covers §4.2: staff undertakings with coverage tracking, client NDAs with survival, a breach
register that forces containment/notification and escalates to a MAJOR NCR, advance
public-disclosure consent, and a fail-closed classification engine driving portal
disclosure. Strong on breach discipline; NDA enforcement and undertaking-lapse alerting are
the soft spots.

## M. Management usefulness

`conf_readiness` (people/covered/lapsed/none) and the breach register surface confidentiality
risk; disclosure consents evidence §4.2 handling. Confirm lapses are seen without opening the
screen.

## N. UI/UX

Tabbed register, breach workflow, classification set on the record. Terminology via `T*()`.

## O. Security

Manage gated; breach close gate holds; visibility engine fail-closed and audience-separated;
but NDA terms unenforced, undertaking lapse not alerted, and some uploads unvalidated —
harden these.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F breaches |
| 10 Settings | Y | §G |
| 11 Workflow | **Priority** | §D breach close + visibility |
| 12 Integration | Y | §H NCR/portals |
| 13 Data integrity | **Priority** | §I NDA/lapse |
| 14 Audit | Y | §I |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L §4.2 |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O |
| 21 Import | N-A | — |
| 22 Notifications | **Gap** | §I no lapse cron |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | accreditation pack |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | undertaking/NDA validity |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-CONF-001 | (verify) | Undertaking state is **computed only, no expiry cron/alert** — a lapse surfaces only when the coverage screen is opened. Add a reminder; and non-inspector person kinds (subcon/contractor/visitor) are never surfaced as uncovered. |
| GAP-CONF-002 | (verify) | **Client NDA terms/expiry are a filing record only** — they do not tighten the visibility engine. Either enforce or clearly mark as non-enforcing. |
| GAP-CONF-003 | (verify) | Confirm the **visibility engine fail-closes** for every portal read (INTERNAL/RESTRICTED/MGMT_ONLY never reach client/vendor) and that regulator notification is required to close a LEGAL/PUBLICATION breach; validate breach/NDA uploads. |

---

## R. Traceability

RTM slice: `/confidentiality`, `/conf-*`, `/disclosure*`, portal visibility × dims 1–29 →
TC-CONF-* → results → DEF/GAP. **Verdict: Complete-with-defects** — breach close discipline,
fail-closed visibility, and NDA/lapse handling are the exit conditions.
