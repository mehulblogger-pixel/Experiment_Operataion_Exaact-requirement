# EXAACT Recruitment — Pending enhancements (post-V1 backlog)

**Status:** The Recruitment module is a complete, sellable V1 (Phases 1–7 done —
full hire-to-onboard journey, configurable, tested: 6,160 checks passing). The
items below are **value-adds, not gaps** — none blocks a sale. They deepen the
product and should be built when a paying customer actually asks, so investment
follows real demand rather than guesswork. Order below is a suggested priority.

Every item must follow the same rules as Phases 1–7: **additive and
non-destructive, no new permission without sign-off, docs updated in the same
commit, full test suite kept green.** Work stays on the `Testing` branch.

---

## 1. Candidate self-service status tracking
**What:** A simple, secure page where an applicant can log in (or use a one-time
link) to see where their application stands — received / under review /
interview / offer — without phoning the recruiter.
**Why it matters:** Cuts "what's my status?" calls, looks professional, and
improves candidate experience (a real differentiator in hiring).
**Reuse:** The platform already has client, vendor and freelancer self-service
portals; the same pattern extends to candidates. No new engine needed.
**Rough size:** Medium.

## 2. Reference checks & medicals as structured workflows
**What:** Today these are proper *stages* in the pipeline with document capture.
Deepen them into structured forms — a referee questionnaire (sent, filled,
scored) and a medical result capture (fit / unfit / conditional, with the
certificate on file and an auto-expiry reminder).
**Why it matters:** Regulated and safety-critical industries expect an auditable
reference and medical trail, not just an attached file.
**Reuse:** Interview scorecards (Phase 4) and the document DMS are the templates
to build on.
**Rough size:** Medium.

## 3. Interview calendar invites (.ics)
**What:** When an interview is scheduled, automatically email a proper calendar
invite (.ics) to the panel and the candidate, so it lands in Outlook/Google
Calendar with reminders — plus reschedule/cancel updates.
**Why it matters:** Removes manual diary work and no-shows caused by missed
timings; expected of any modern hiring tool.
**Reuse:** Interviews already store date, panel, mode and location (Phase 4); the
mailer (`ops_mail()`) already sends attachments. This is mostly the .ics format
generator + wiring.
**Rough size:** Small–Medium.

## 4. Bulk candidate import (spreadsheet in)
**What:** Upload a spreadsheet (CSV/Excel) of candidates to create many records
at once, with a preview, column-mapping and the same duplicate guard used on the
single-add form.
**Why it matters:** New customers arrive with an existing candidate list; letting
them import it on day one removes the biggest onboarding friction. (Export is
already done — this is the mirror.)
**Reuse:** The duplicate-detection and candidate-create logic already exist; add
a mapping/preview step in front of them.
**Rough size:** Medium.

## 5. Careers page polish (branded job board)
**What:** Evolve the public careers page from a clean list into a branded
mini job-board — company logo & colours, search and filters (department,
location), category grouping, richer role pages, and social-share links.
**Why it matters:** Larger clients judge an ATS partly by how their careers page
looks to the outside world; a branded board helps them attract better applicants.
**Reuse:** The public careers engine and application intake (Phase 7) already
exist; this is presentation, branding and filtering on top.
**Rough size:** Medium.

---

## ⭐ Delivery reminder — restrict each customer to what they bought (DO NOT FORGET)

Before handing a copy to any customer, make sure they only see the modules they
paid for. There are two ways, and the licence key is the real, tamper-proof one:

1. **Issue a licence key for the plan they bought** (the proper, secure way).
   On the vendor machine: **Admin → Control panel → Licence console →
   pick the "Recruitment" plan (or Starter / Pro / Enterprise) → set seats &
   expiry → Generate signed key.** Send the key; the customer pastes it under
   **Admin → Licence**. A signed key OUTRANKS every in-app switch, so the
   customer can never turn on a module they did not buy, and a tampered key
   grants nothing beyond the core. An expired key goes read-only, never a
   lock-out.
2. **Or ship the Recruitment Edition build** (carries an `edition.txt` marker),
   which self-configures to Recruitment-only on first boot — a friendly default
   before/without a key. A hand switch is also available any time:
   **Admin → Control panel → Product package → "Set up as a Recruitment-only
   company."**

**Why a fresh test install shows every module:** with no licence key it runs in
"Open" mode — everything unlocked — which is intended for the vendor's own
testing. The key is the lock; no key = full access. Remember to apply one of the
two options above for every real customer.

_Module key for recruitment = `hr` (People & hiring). Recruitment plan grants
`admin` + `hr` only._

---

_Maintained as the recruitment product backlog. Update as items ship or new
customer requests arrive._
