# PROMPT 5 — Final Gap, Risk & Application Readiness Audit

> **Run last, after every module document and the end-to-end report are done.** It
> rolls everything into one auditable verdict: is the application ready to be used by
> a third-party inspection agency, and if not, exactly what stands in the way.

---

## Paste this to the AI developer/tester

You are performing the **final readiness audit** of **Inspection Ops** (multi-tenant
TPIA platform, ISO/IEC 17020-aligned). All inputs are attached: the locked inventory,
the governance, every module Test & Documentation report, and the End-to-End Workflow
report. Do not re-run tests — **synthesise, weigh and judge**. Where a module report
is thin on a dimension, say so; an unexamined dimension is itself a risk.

### Output — one Word (.docx) document titled *"Inspection Ops — Gap, Risk & Readiness Assessment v1.0"*

**1. Executive summary (one page).** The readiness verdict in plain language for a
non-technical owner: **Ready / Ready-with-conditions / Not-ready**, the top 5 risks,
and the shortest path to "Ready".

**2. Coverage roll-up.** A matrix of **every module × the 29 coverage dimensions**,
each cell Green / Amber / Red from the module scorecards. Column and row totals make
the weakest modules and the weakest dimensions obvious at a glance. List every
**Red/unexamined** cell as an explicit coverage gap.

**3. Defect roll-up.** All defects by severity and by module; the count of open
**Blocker/Critical** (any open Blocker = Not-ready). Trend if a prior cycle exists.

**4. Gap register (consolidated).** Every `GAP-*` from the modules and E2E, grouped
as: **(a) Functional gaps** (missing/weak features), **(b) Control/compliance gaps**
(ISO/IEC 17020, audit trail, immutability, scope leakage, permission holes),
**(c) Operational-fit gaps** (does not match how a TPIA works), **(d) Management-
usefulness gaps** (decisions the data cannot yet support), **(e) Technical/non-
functional gaps** (security, performance, migration, offline, notifications,
licensing, backup/DR, accessibility). Each: impact, likelihood, **risk rating**,
recommendation, rough effort, and owner.

**5. Risk assessment.** A risk matrix (impact × likelihood) over the gaps and open
defects, with the residual risk of shipping as-is and the mitigation for each high
risk. Call out anything that could put **wrong information in front of a client**,
**let a control be bypassed**, or **compromise evidence integrity** — these are
release-blocking by definition.

**6. TPIA operational readiness.** Against the real operating flow (enquiry →
quotation → order → call → allocation → inspection → report → vetting → approval →
issue → release note → invoice, plus NCR/CAPA, equipment/competence/impartiality,
portals): is each stage fit for real use? Where would an inspector, coordinator or
manager still be blocked, confused, or forced outside the system?

**7. Compliance readiness (ISO/IEC 17020).** Map the controls the platform provides
(impartiality, confidentiality, competence & authorisation, equipment &
calibration, complaints & appeals, records & audit trail, internal audit &
management review, document control) to the standard's expectations, and flag any
control that is present-but-weak or absent.

**8. Data integrity & audit-trail verdict.** Is finalised data truly immutable? Does
the tamper-evident **hash chain verify** end-to-end? Are timestamps and evidence
geo/EXIF trustworthy? Any hole here is Blocker.

**9. Security posture.** Summary of authorisation-on-action, CSRF, file-auth,
injection, privilege-escalation and sensitive-data-exposure findings across the
modules, with the residual risk.

**10. Non-functional readiness.** Performance/scale at realistic volumes; multi-
tenant isolation & terminology; licensing/seats; offline/mobile; notifications/email
deliverability; backup/restore/continuity; accessibility.

**11. Regression & automation health.** State of the standing automated suite (pass
count, any known-failing tests and whether they are pre-existing/accepted), and the
recommendation on gating releases against it.

**12. Readiness scorecard & verdict.** A weighted scorecard across the dimensions
producing a single **Ready / Ready-with-conditions / Not-ready** call, the explicit
**conditions/exit list** that must be closed, and a prioritised remediation roadmap
(P1 now → P4 later) with sequencing.

**13. Sign-off & revision history.** Who signs the readiness call; the date; the app
build/commit and inventory version it pertains to.

### Rules
- **Traceable:** every statement cites the module report, TC, defect or gap it rests
  on. No new claims without evidence in the inputs.
- **Honest:** if a module was under-tested on a dimension, the roll-up shows Amber/
  Red — do not average away a real hole.
- **Decision-useful:** the owner must be able to read §1 and §12 and know exactly
  what to do next and why.
