# Inspection Ops — Settings & Terminology — Test & Documentation Report

> **Prompt 3 · Module MOD-SETTINGS.** Read from `lib/ops.php` (`ops_settings`),
> `lib/terms.php` (`ops_terminology`, `T`/`Tl`/`TH`/`T_*`, `term_apply_pack`,
> `term_save`, `term_overrides`, `TERM_PACKS`), `lib/licence.php` (`licence_save`,
> `PRODUCT_MODULES`), `lib/numbering.php` (`numbering_types`/`numbering_save`),
> `lib/db.php` (`setting_get`/`setting_set`), views `settings.php`, `terminology.php`.

| | |
|---|---|
| **Module** | Settings & Terminology (MOD-SETTINGS) · Area Admin |
| **Personas** | P-ADMIN (`settings.manage`), P-MASTER (modules/licensing), others (read-through only) |
| **Risk weight** | **Medium-High** — a wrong FY, numbering, or security setting affects every module; the terminology engine makes the app agency-agnostic |
| **Verdict** | Complete-with-verification (confirm setting clamps + module-off safety at runtime) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

One admin screen governs the whole installation, plus a terminology screen that renames
every noun in the product so it fits any TPIA (no agency wording is hardcoded). Settings
groups: **modules/licensing** (Master only), **financial year** (start month + current-FY
label), **TAT/escalation**, **man-month basis**, **app name**, **working norms** (daily
hours cap, half-day hours, weekly days, emp-code prefix), **job-close grace + lock**,
**client-issue notification**, **document numbering** (prefix/separator/digits/FY/start),
**quote T&C**, **display** (currency symbol, date format), **reporting controls** (expected
source docs, high-risk audit actions), and **security** (password min length/age, session
idle/max, 2FA roles) — every security control **chooses how strict a guard is, never
switches a guard off**.

Terminology: pick a **wording pack** (`TERM_PACKS`) or edit any noun; every screen follows
via `T()/Tl()/TH()`. Reset restores the standard wording.

Screens: `/settings`, `/terminology`. Storage: `settings` KV via `setting_get/set`,
`term_overrides`.

---

## B. Screen-by-screen catalogue

**`/settings`** — grouped form (modules, FY, operations, working norms, numbering, display,
reporting, security). Module switching and numbering are sub-forms. **`/terminology`** —
apply a pack, edit noun overrides by group, or reset. Both require `settings.manage`;
module licensing additionally requires master (and is pinned if `MODULES_OFF` env is set).

---

## C. Field & validation  *(clamps — the point of this module)*

| TC ID | Title → Expected |
|---|---|
| TC-SET-form-001 | FY start month clamped 1–12 (default 4); current-FY label AUTO or well-formed, else AUTO. |
| TC-SET-form-002 | Working norms clamped: daily hours 0<h≤24 (def 8.5), half-day 0<h≤12 (def 4), weekly days ∈ {5,5.5,6}. |
| TC-SET-form-003 | Security clamps: pwd min 8–64 (def 10), pwd max-age 0 or 30–730, session idle 5–1440, session max 1–168; **none can disable a guard**. |
| TC-SET-form-004 | Grace days clamped 0–365; contract-warn 1–365; TAT default 3. |
| TC-SET-form-005 | Currency symbol defaults ₹; date format restricted to the allowed set. |

---

## D. Functions & logic

- **Module licensing** (`licence_save`): Master-only; switching a module off hides it from
  everyone (including the switcher), so it is deliberately not an ordinary admin setting;
  pinned when `MODULES_OFF` is set. **TC-SET-fn-001** — a non-master module POST is
  refused; **TC-SET-fn-002** — a switched-off module disappears from nav and its routes
  guard off.
- **Terminology** (`term_apply_pack`/`term_save`): a pack renames every noun; individual
  overrides still editable; reset restores defaults. **TC-SET-fn-003** — after a pack +
  an override, every screen (`T*()`) reflects the wording; **TC-SET-fn-004** — an unknown
  pack is rejected.
- **Numbering** (`numbering_save` per type): prefix/separator/digits/FY/start; each type
  independent. **TC-SET-fn-005** — a changed number format applies to new records only.
- **Setting reads** (`setting_get` with defaults): every consumer falls back to a safe
  default when unset. **TC-SET-fn-006.**

---

## E. Status & lifecycle

Settings are point-in-time KV values — no lifecycle — but changes take effect immediately
and only affect **new** records where relevant (numbering, FY). **TC-SET-life-001:**
changing FY start mid-year re-buckets registers on the configured basis without rewriting
historical records; **TC-SET-life-002:** a terminology change is retroactive on display
(labels) but never rewrites stored data.

---

## F. Roles, permissions & data scope

All settings: `settings.manage`. Modules/licensing: master (env-pinnable). No data scope
(global config). **TC-SET-perm-001** — a non-admin `/settings` POST is refused;
**TC-SET-perm-002** — a non-master module change is refused even with `settings.manage`.

---

## G. Settings (self)

This module *is* settings. Cross-cutting keys referenced by other modules: `fy_start_month`,
`fy_current`, `tat_threshold_days`, `manmonth_basis`/`manmonth_min_days`,
`job_close_grace_days`/`job_lock_enabled`, `notify_client_on_issue`, `currency_symbol`,
`date_format`, `pwd_*`/`session_*`/`twofa_roles`, `quote_terms`, `expected_source_docs`,
`audit_high_risk`. **TC-SET-set-001:** each key is consumed by its owning module with the
same clamp/default.

---

## H. Cross-module integration

Touches **every** module: FY → all registers; man-month → scheduling/billing; grace/lock →
jobs; numbering → quotes/reports/invoices; currency/date → all outputs; security → auth;
terminology → all UI; expected source docs → IDEMS completeness; high-risk actions →
audit log. Idempotency: saving settings twice is a no-op beyond the last value.

---

## I. Data integrity & audit

Setting changes should be recorded (who/when) for the security- and money-relevant keys;
terminology overrides stored as a map; numbering changes never renumber existing records.
**TC-SET-int-010:** a security-policy change is audited; **TC-SET-int-011:** historical
numbers are immutable after a format change.

---

## J. Reports & outputs

No document of its own; it shapes every other output (numbers, currency, dates, wording,
letterheads). **TC-SET-out-001:** a currency/date/terminology change reflects in a freshly
generated PDF.

---

## K. Negative, edge & resilience

Out-of-range clamp inputs (all snap to bounds); an unknown terminology pack; a non-master
attempting module changes; `MODULES_OFF` pinned; a malformed FY label; disabling a module
that other modules depend on (dependency respected).

---

## L. TPIA operational suitability

The terminology engine is what makes the product genuinely multi-TPIA — "inspection",
"call", "engineer", "office" are all renameable, so a body with different vocabulary is
first-class, not retrofitted. Configurable FY, man-month, numbering and working norms let
each tenant match its own practice without code changes.

## M. Management usefulness

One place to tune the whole installation; module licensing controls scope and cost;
security policy is centrally set. Confirm changes propagate to the owning modules.

## N. UI/UX

Grouped settings with clear defaults; a terminology screen with packs + per-noun editing +
reset; safe clamps so a bad value cannot break the app. Terminology change is immediate
and system-wide.

## O. Security

`settings.manage`-gated; module licensing master-gated and env-pinnable; **no setting can
switch a security guard off** (only its strictness); password/session/2FA policy centrally
enforced; module-off hides routes server-side, not just nav.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | N-A | KV config |
| 6 Validation | **Priority** | §C clamps |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | N-A | global |
| 10 Settings | Y | §G (self) |
| 11 Workflow | N-A | — |
| 12 Integration | **Priority** | §H every module |
| 13 Data integrity | Y | §I immutable numbers |
| 14 Audit | Partial | §I security keys |
| 15 Outputs | Y | §J shapes all |
| 16 TPIA suitability | **Priority** | §L terminology engine |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O guards can't be disabled |
| 21 Import | N-A | — |
| 22 Notifications | Partial | notify_client_on_issue |
| 23 Offline | N-A | — |
| 24 AI | Partial | AI provider config |
| 25 Licensing | **Priority** | §D modules |
| 26 Terminology | **Priority** | §D packs |
| 27 Time/FY | Y | §C FY |
| 28 Performance | N-A | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-verification.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-SET-001 | (verify) | Confirm every clamp holds on a crafted out-of-range POST and no security setting can be posted to a value that disables a guard. |
| GAP-SET-002 | (verify) | Confirm a switched-off module's **routes** guard off server-side (not only hidden nav), and dependencies are respected. |
| GAP-SET-003 | (verify) | Confirm a numbering-format change never renumbers existing records, and security-policy changes are audited. |

---

## R. Traceability

RTM slice: `/settings`, `/terminology` × dims 1–29 → TC-SET-* → results → DEF/GAP.
**Verdict: Complete-with-verification** — clamp enforcement, module-off route safety, and
numbering immutability are the exit conditions.
