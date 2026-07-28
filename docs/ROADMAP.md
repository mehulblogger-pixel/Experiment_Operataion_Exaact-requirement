# The complete plan — everything outstanding, in the order it should be done

One list. Everything raised across the gap review, the on-premise study and the
platform strategy, plus the 93 open items already in `PENDING.md`, triaged into
a sequence with honest effort estimates.

**How to read the estimates.** They are days of focused work *by me*, building
in this codebase, including tests and a real browser run. They are not
calendar days and they assume no other work in parallel. Where I am unsure I
have said so rather than picking a comfortable number.

**The one rule for the whole plan:** nothing in Phase 3 onward is worth starting
until Phase 0 and Phase 1 are done. A prospect who sees a broken engineer screen
will not care that you support ISO/IEC 17020:2026.

---

## Phase 0 — Yours, not mine. Blocks everything. (a few hours)

None of this is code. All of it blocks a sale, an audit, or both.

| # | Item | Why it blocks |
|---|---|---|
| 0.1 | ✅ **HTTPS on the live site** — certificate live at MilesWeb (July 2026). The redirect in `.htaccess` was still commented out and is now **on**, because a certificate that exists but is not enforced buys neither the Secure cookie nor the service worker. **Phase 7 is now unblocked.** |
| 0.2 | **Change `admin/admin12345`** and every `demo12345` account | The credentials are in the repository. |
| 0.3 | **Backups running, and one restore actually tried** | Every photo, bill and signed report lives in the database. A backup nobody has restored is a hope. |
| 0.4 | **Grievance officer + privacy notice** (Settings → Compliance) | DPDP Act. Two text fields, legally required. |
| 0.5 | **Two-step sign-in on** for roles that move money | Already built, switched off. |
| 0.6 | **Written confirmation of Indian data centre + NTP sync** from the host | DPDP evidence. Two e-mails. |

---

## Phase 1 — Fix what is broken (4–6 days) — ✅ DONE, commit 5d15879

The app has flow breaks that a demo will expose. These come first, always.

| # | Item | Est. | Notes |
|---|---|---:|---|
| 1.1 | **B1 — Director's menu offers Settings, then refuses it** | 0.25 d | Hide it for the role, or grant it. A menu item that refuses reads as a broken app. |
| 1.2 | **B3 — the engineer's two-item menu** | 1 d | Add report register, evidence, their certificates, their leave. The person who writes every report has no menu entry for reports. |
| 1.3 | **B2 — the report engine is unreachable from a deputation** unless deliverables were ticked | 1 d | Always show the "Reports owed" panel, with a "no formats agreed — write one anyway" path. Also decide the fate of the "report link" box, which is a second, competing reporting world. |
| 1.4 | **C5 — utilisation on the director's dashboard** | 1 d | Billable engineer-days ÷ available engineer-days. The data already exists. It is the first question a director asks and it is not on the screen. |
| 1.5 | **B4 — "Raise an inspection call from this quotation"** | 0.5 d | Sales→ops is pull-only today; somebody has to remember. |
| 1.6 | **A4(a) — block allocation on an expired certificate** | 1 d | Today an engineer with a lapsed certificate can still be deputed; only a reminder e-mail goes out 30 days prior. Safety issue *and* an accreditation nonconformity. Cheap because `inspector_certs` already exists. |

**Exit test:** a stranger can be given a login as each of the nine roles and
complete that role's day without hitting a dead end.

---

## Phase 2 — Make it sellable and installable (4–5 days) — ✅ DONE, commit 70fae4f

This is the "separate modules" answer, done the cheap way that gets the same
commercial result.

| # | Item | Est. | Notes |
|---|---|---:|---|
| 2.1 | **Per-installation module licence flags** | 2 d | Sales / Operations / Money / HR / Reporting / Admin switched on or off per install, on top of the existing `ops_module_gate()` and `can('mod.*.view')`. **This is what you asked for when you asked to separate the modules** — same commercial outcome, days instead of 6–10 weeks, no refactor risk. |
| 2.2 | **Pre-flight requirements page** | 1 d | "PHP 8.1 ✓ · pdo_mysql ✓ · zip ✗ (Word export will be off)". Highest-value single addition for on-premise installs. |
| 2.3 | **Version number in the app + upgrade note** | 0.5 d | Today nobody can tell which build a client is running. This will bite you the first time a customer reports a bug. |
| 2.4 | **Release artifact** — versioned zip with a checksum | 0.5 d | Turns "copy what git has" into "here is the package". |
| 2.5 | **Licence-key decision** | 0.5 d | `SEAT_LIMIT` is read and displayed but nothing enforces it. Decide whether on-premise is licensed at all, then enforce or delete it. |

**Not doing, deliberately:** the actual six-way code split. See §"Why not to
split" below.

---

## Phase 3 — The ISO/IEC 17020:2026 transition pack (12–16 days)

**This is the commercial centre of the whole plan.** The standard was published
27 March 2026; every accredited inspection body must transition by **27 March
2029**. Its headline change is a standalone *control of data and information*
clause that did not exist in 2012 — and it is about the software they use.

You are selling into a market with a deadline, a mandatory scope, and no
alternative. Nothing else in this document has that.

| # | Item | Est. | Clause |
|---|---|---:|---|
| 3.1 | ✅ **DONE** — **Equipment & calibration register** — instrument ID, owner, certificate, due date; and the report **refuses to finalise** naming an instrument out of calibration | 3 d | §6.2 |
| 3.2 | ✅ **3.2a DONE** (spine) — **Competence & authorisation matrix** — which inspection types / methods / clients each person is authorised for; witnessed-inspection record; periodic monitoring | 3 d | §6.1 (incl. 6.1.8) |
| 3.3 | ✅ **DONE** — **Impartiality & conflict-of-interest** — per-deputation declaration, plus a register of declared threats and how each was resolved. **2026 explicitly adds threats from organisational relationships, outsourcing and financial pressure** | 2 d | §4.1 |
| 3.4 | ✅ **DONE** — **Complaints & appeals register** — logged, acknowledged, investigated, closed; the decider is refused if they were involved (§7.5.4), an appeal cannot be decided by whoever decided the original (§7.6), and nothing closes until the complainant has been written to. The process description is published at `/complaints-policy`, readable without signing in | 2 d | §7.5, §7.6 |
| 3.5 | ✅ **DONE** — **Corrective action · internal audit · management review** — nothing closes without the effectiveness review (§8.7.3) and the "did it happen elsewhere" answer (§8.7.2 d); an auditor cannot audit their own work (§8.8.2); a clause-coverage board; and the management review reads fourteen of its fifteen required inputs off the running system (§8.9.2) and will not complete without a decision (§8.9.3) | 3 d | §8.7–8.9 |
| 3.6 | ✅ **DONE** — **Data & information control** — a software-validation register that flags when the version you are *running* has no record against it; seventeen integrity checks that actually run and are recorded pass or fail; an access report read from the permission engine; and a failure log that writes itself on a fatal error and cannot be resolved until it says what happened to the data | 2 d | **new in 2026, no 2012 equivalent** |
| 3.7 | ✅ **DONE** — **Type A / Type non-A** on the organisation record (was A/B/C) | 0.25 d | 2026 model change |
| 3.8 | ✅ **DONE** — **Identity documents for site access** — passport / visa / ID held under a stated purpose, a separate permission, a retention limit that runs nightly, masked numbers, and a log of every open, reveal and copy sent out | 1.5 d | DPDP Act 2023, not 17020 |

**Sell it as one thing:** *"17020:2026 transition-ready."* That is a reason to
buy this year rather than eventually.

---

## Phase 4 — The trust layer (6–8 days) — ✅ DONE

The differentiator. Bind the **evidence** to place and time — not the **person**
to a map. See the strategy note for why continuous GPS tracking is the wrong
answer legally, technically and commercially.

| # | Item | Est. | Notes |
|---|---|---:|---|
| 4.1 | ✅ **DONE** — **Geotag at the moment of capture** — read from the photograph's own EXIF, so it survives the drive home and the evening spent writing the report. The upload location is kept separately and never presented as the inspection location. Plus a **site check-in** (arrival / departure, optional photograph) for the many phones that strip EXIF | 1 d | |
| 4.2 | ✅ **DONE** — **Fake-GPS signals, flagged not blocked** — far from the check-in, impossible travel, sub-metre "accuracy", coordinates too round to be a fix. A late upload is deliberately **never** flagged: it is the normal working day | 1 d | |
| 4.3 | ✅ **DONE** — **Server-side timestamps everywhere** — the device clock is recorded only to be compared, never trusted. Mobile network time is not available to a web page (nor really to an app), which is exactly why the server stamps it | 0.5 d | |
| 4.4 | ✅ **DONE** — **Hash chain over the evidence** — append-only, sha256, each entry hashing the one before it; altering or removing anything breaks every hash after it. Verification distinguishes a broken link, a deleted file and changed bytes | 2 d | |
| 4.5 | ✅ **DONE** — **Client-verifiable report** — a readable code printed on the report, checked at `/verify` with no account. Genuine or not, unaltered or not, how much evidence was located on site, whether the engineer was authorised — and no client name, findings or prices. **QR image still to do**: an unverifiable hand-written encoder on a client-facing certificate is worse than a typed address, so it is deferred rather than guessed | 3 d | |

**Deliberately not building: continuous inspector tracking.** Under DPDP consent
must be free, informed, specific and withdrawable, and an employee cannot freely
refuse; tracking outside working hours and using location for disciplinary
decisions are specifically scrutinised; penalties reach ₹250 crore. It also does
not work — mock-location apps defeat it. If you still want it after reading
this, say so and I will build it with working-hours limits, explicit consent
capture and a withdrawal path — but I will argue against it first.

---

## Phase 5 — Close the commercial gaps (10–14 days)

| # | Item | Est. | Notes |
|---|---|---:|---|
| 5.1 | **Client portal** | 5 d | The biggest thing competitors sell on: the client logs in, sees call status, downloads reports, raises the next call. Also kills the daily "where is my report" e-mail. Pairs naturally with 4.5. |
| 5.2 | **GST on the invoice side** *or* **a Tally export** | 3 d | Today tax stops at the quotation. `/invoicing` is a tracker, not an invoicing module. **Decide which** — building a full GST engine (HSN/SAC, place of supply, IGST vs CGST+SGST, e-invoice/IRN) is 3× the work of exporting to Tally, and most Indian firms bill in Tally anyway. My recommendation is the export. |
| 5.3 | **Receivables ageing** — 0–30 / 31–60 / 61–90 / 90+ by client | 1 d | Overdue is a yes/no today. |
| 5.4 | **Customer satisfaction capture** after closure | 1 d | ISO 9001 expectation and a normal account tool. |
| 5.5 | **Consolidated invoicing** — one invoice across many deputations | 2 d | Currently one invoice per job. Already flagged in `PENDING.md`. |

---

## Phase 6 — ISO/IEC 17021 certification bodies (15–20 days)

**See the section below.** This is a real project, not a configuration change.
Do it only after Phases 1–4, and only if you have a certification body willing
to be the design partner.

---

## Phase 7 — Store presence for mobile (5–8 days)

Only after 0.1 (HTTPS), which the whole thing depends on.

| # | Item | Est. | Notes |
|---|---|---:|---|
| 7.1 | **Google Play via Trusted Web Activity** | 2 d | Wraps the live site as a real Android app; stays in sync automatically. |
| 7.2 | **Apple App Store shell** | 4–6 d | Apple rejects pure website wrappers (guideline 4.2), so it needs native camera, push and offline handling to pass. Honest uncertainty: review rejections can add weeks that no estimate can absorb. |

The app is **already an installable PWA** — manifest, service worker, offline
draft queue, camera capture and GPS all work today. "Add to Home Screen" needs
only HTTPS. Store presence is packaging, not a rewrite.

---

## Ongoing — health work, not features (3–4 days, whenever there is a quiet week)

| # | Item | Est. | Why |
|---|---|---:|---|
| H1 | **Carve `Money` out of `ops.php`** | 2 d | `job_money`, `job_profit`, `job_revenue_for`, invoicing. Pure logic, well tested, called everywhere. |
| H2 | **Carve `Notification` out of `ops.php`** | 1 d | SMTP and every `send_*_email`. |
| H3 | **Triage the 93 open `PENDING.md` items** | 0.5 d | Many are legacy, duplicated or superseded. My estimate is **fewer than 30 still matter**. Carrying 93 makes the list useless as a decision tool. |

`ops.php` is 4,671 lines and is called **712 times** from other modules. It is
where the regressions come from. H1 and H2 are not cosmetic.

---

## Why I am not recommending the six-way module split

You asked to separate Sales, Operations, Money, HR, Reporting and Admin into
products. I measured the coupling before answering:

- **`ops.php` is not the Operations module — it is the application.** 4,671
  lines holding the router, the schema, authentication, SMTP, the money engine,
  HR, masters, the chart renderer and the formatters. Called 712 times.
- **Sales (`crm`) and Reporting (`idems`) are genuinely separable** — 577 and
  560 calls out, only 24 and 55 back.
- **Money, HR, Operations and Admin are not four modules.** They are four
  concerns tangled inside one file.
- **`helpers` (830 inbound), `db` (484), `access` (384), `terms` (188),
  `lookups` (143) must never be split** — that is the platform kernel, and it
  is correct as it stands.

A true split is **6–10 weeks with no customer-visible feature**, in a codebase
that has already been burned repeatedly by one change breaking something that
read from it. Phase 2.1 gives you the commercial outcome — "Sales module: off,
₹X/month to switch on" — in **two days**.

Do the real split the day a paying customer demands one module standalone.

---

## Why ISO/IEC 17021 *can* be included — and what it actually costs

**Correction first: I never said it cannot be.** What I said was that 17021 is
the biggest adjacent market and worth chasing, but that it is a **new engine,
not a configuration change**. If that read as "we cannot", that is on me — so
here it is precisely.

**It can absolutely live in this application**, as a second engagement type
alongside inspection. The parties, offices, people, competence records,
impartiality register, complaints register, CAPA log, document engine, billing
and dashboards are all shared. That is exactly why it is the right adjacency.

**What does not transfer is the shape of the work.**

An inspection call is *one engagement, one or more visits, a report*.
A certification audit is a *three-year programme*:

```
Stage 1 → Stage 2 → Surveillance 1 → Surveillance 2 → Recertification
             │
             └── findings → major/minor NC → corrective action →
                 verification → certification decision → certificate
```

Eight things your current model has no representation for:

1. **Audit man-days are calculated, not quoted.** IAF MD 5 derives them from
   effective employee count and risk category. Your app takes a rate × quantity
   from a quotation. Different mechanism entirely.
2. **Multi-site sampling** — IAF MD 1, a √n rule deciding which sites get
   visited in which year. You have no concept of a sampled site.
3. **The certification decision must be taken by someone who was not on the
   audit team.** A hard 17021 rule. Your approval chain routes to the reporting
   manager — who may well have audited. This needs a *different* rule, not a
   different setting.
4. **Certificate lifecycle** — issued / suspended / withdrawn / expired, with a
   public register clients can check. You have no certificate object at all.
5. **Auditor competence is per scope and NACE/EA code**, not per trade and
   skill. Related to Phase 3.2 but not the same table.
6. **Non-conformity lifecycle with a clock** — a *major* NC must be closed
   before certification; a *minor* may be carried to the next surveillance.
   That is a workflow with deadlines, not a report field.
7. **Impartiality has a hard rule**: you may not audit a client you consulted
   for within two years. That is an engine, not a declaration.
8. **The programme spans three years.** Your scheduler models one engagement.
   This is the single biggest piece — and it is the same scheduling engine that
   was rebuilt from scratch this month, so I know its shape well enough to say
   it does not stretch to cover this.

**Honest estimate: 15–20 days for a credible v1**, and my confidence in that
number is lower than for anything else on this page, because IAF Mandatory
Documents carry detail that only shows up once you build against them.

**My recommendation:** do it — after Phases 1–4, and only with a certification
body as a design partner. Building IAF MD 5 man-day rules from a reading of the
document, without somebody who runs audits checking each rule, is how you end up
with a plausible engine that no accredited body can actually use.

---

## The whole thing, in one view

| Phase | What | Est. | Status |
|---|---|---:|---|
| 0 | Owner actions | hours | HTTPS ✅ done — **Phase 7 unblocked**. Passwords, backups, DPDP notice still open |
| 1 | Fix the broken flows | 4–6 d | ✅ done `5d15879` |
| 2 | Module licensing, pre-flight, versioning | 4–5 d | ✅ done `70fae4f` |
| 3 | **ISO/IEC 17020:2026 transition pack** | 12–16 d | after 1 |
| 4 | Trust layer + verifiable reports | 6–8 d | after 3.6 |
| 5 | Client portal, tax, ageing, feedback | 10–14 d | after 4 |
| 6 | ISO/IEC 17021 certification bodies | 15–20 d | after 4, with a design partner |
| 7 | Play Store / App Store | 5–8 d | after 0.1 |
| H | Money + Notification carve-out, backlog triage | 3–4 d | any quiet week |

**Roughly 60–80 days of build.** Phases 1–4 — about 25–35 days — get you to a
product that is demonstrably better than what you have and has a reason to be
bought this year.

**And the one thing not on any list: sell one to somebody who is not you.**
Every estimate above gets more accurate, and every priority gets more honest,
the moment a stranger has paid for it.
