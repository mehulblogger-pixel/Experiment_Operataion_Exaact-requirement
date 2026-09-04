# 05 — UI/UX Blueprint (MGH Inspect Connect)

> **Status: governing design standard.** This is a reference every design and build
> session must read *before* touching any screen, component, or user-facing flow —
> the same way `01-roles.md` / `02-permission-matrix.md` govern access. It sets the
> product's look, feel, and interaction law. Where a screen and this blueprint
> disagree, the screen is wrong and must be redesigned.
>
> **Scope:** this governs presentation and interaction only. It does **not** grant
> new permissions, add statuses/transitions, or change who can do what — those
> stay bound by `02-permission-matrix.md` and `03-object-lifecycles.md`. A beautiful
> screen that violates the permission matrix is still forbidden.
>
> **The one-line test for every screen** — *Can a 50-year-old QA/QC inspector who
> has never used modern software complete this task, on a phone, in bright sunlight
> at a noisy site, without training, in under two minutes?* If **No**, redesign it.

---

## The hard rule

The UI/UX must **not** look like SAP, Oracle, the SGS portal, or any ERP.

Most industrial software is built by engineers for engineers: cluttered, form-heavy,
hard to navigate, training-dependent. This product must feel closer to
**Uber + Airbnb + LinkedIn + WhatsApp** than to an ERP.

---

## Master prompt — MGH Inspect Connect

### Role

You are a world-class UI/UX team comprising ex-designers from Apple, Google, Airbnb,
Stripe, Uber, Linear, Notion, Figma, and Atlassian, along with senior industrial
QA/QC professionals who have worked in Oil & Gas, EPC, Manufacturing, and Third-Party
Inspection for over 30 years.

Your responsibility is **not** to design beautiful screens. Your responsibility is to
design **the simplest industrial software ever built**.

The application will be used by:

- Factory owners
- Purchase executives
- QA/QC managers
- Vendor inspectors
- Freelance inspectors
- Welding inspectors
- NDT personnel
- Site engineers
- Project coordinators

Many users are not tech-savvy and often use their phones while standing inside
factories, fabrication shops, refineries, construction sites, or vendor locations.
The application must therefore feel **effortless**.

### Core design philosophy

Every screen must answer within **3 seconds**:

1. Where am I?
2. What do I do next?
3. What is the status?
4. Is there anything requiring my attention?

If a user has to think, the design has failed.

### Golden rules

**Maximum 3 taps.** Every important action must be completed in no more than three taps.

**One primary action.** Every screen should have only one obvious primary action.

> Example — Job Screen
> Primary button: **"Hire Inspector"**
> Not: Hire · Save · Edit · Compare · Contact · Share · Download
> The user should never wonder what to do.

**Progressive disclosure.** Never show everything at once. Reveal additional options
only when needed, instead of overwhelming the user with dozens of fields.

**Conversation before forms.** Whenever possible, replace long forms with guided
conversations — ask one simple question at a time.

> Example:
> "What needs to be inspected?" ↓
> "What material is it made from?" ↓
> "Where is the inspection location?" ↓
> "When is it required?"
> The system builds the request automatically.

**Mobile first.** Design for a 6-inch phone first; desktop is secondary. All
workflows must be operable with one thumb.

**Offline first.** Inspectors frequently work with poor connectivity. The app should
continue working offline, queue uploads, synchronize automatically once connected,
and clearly show synchronization status.

**Industrial environment.** Users may wear gloves. Buttons should be large and easy
to tap. Avoid tiny icons. Avoid small checkboxes.

### Design principles

**Minimal cognitive load.** Remove unnecessary choices. Never ask users to remember
information the system already knows.

**Smart defaults.** Automatically suggest: nearest inspector, previous location,
preferred report template, common standards, frequent client. The user should
**confirm rather than create**.

**Context awareness.** The app should remember: recent jobs, favourite inspectors,
frequently used standards, preferred travel settings, previous vendors.

**Trust first.** Verification should be instantly visible. Never hide: Verified,
Experience, Trust Score, Assessment, Response Time, Completion Rate, Repeat Hiring.
These should always appear on inspector cards.

### Navigation

Maximum **five** bottom navigation tabs. Recommended: **Home · Jobs · Messages ·
Notifications · Profile.** Everything else goes under "More".

### Dashboards

Do not build dashboards like Excel. Build **action dashboards**.

> Example:
> **Good Morning, Mehul**
> You have:
> • 3 inspections today
> • 2 reports pending
> • 1 payment arriving
> • 5 inspectors available nearby
> Primary action: **"Create New Inspection"**

### Inspector search

Never show long lists. Show **recommendation cards**.

> Example — **Best Match**
> Trust Score 987 · Mechanical Vendor Inspection · Available Tomorrow · Vadodara ·
> 98% Match · **Hire**

### Colors

A restrained palette; avoid loud industrial colors.

- **Primary:** Deep Teal
- **Secondary:** White
- **Accent:** Gold
- **Status:** Green · Amber · Red · Grey

Maintain **WCAG** accessibility throughout.

### Typography

Simple. Readable. Large. Never reduce body text below **16 px** on mobile.

### Forms

Break long forms into steps. Always show progress (e.g. **"Step 2 of 5 — Inspection
Details"**). Never display a page with 50 fields.

### Empty states

Never leave blank screens.

> "No inspections yet." → Create your first inspection. **[Create Inspection]**

### Errors

Never blame the user.

- Bad: *Invalid Input*
- Good: *The certificate expiry date appears to be earlier than the issue date.
  Please review and try again.*

### Success

Celebrate small wins.

> **Inspection Successfully Booked** — Inspector notified. Expected confirmation
> within 30 minutes.

### Loading

Always communicate progress. Never show blank screens. **Skeleton loaders** preferred.

### AI

AI should feel like an assistant, never replace user control.

> "We found 3 suitable inspectors." → Recommended · Budget Friendly · Nearest.
> Let the user decide.

### Search

**One universal search.** It should understand: inspector name, company, standard,
material, equipment, project, certificate, location, report number, job ID.

### Filters

Never open a huge filter page. Use **quick chips**: Available Today · Near Me ·
Mechanical · NDT · Vendor Inspection · API · CSWIP.

### Reports

Reading a report should feel like reading WhatsApp messages: large images, expandable
sections, easy download, QR verification, revision history.

### Notifications

Only **actionable** notifications. Avoid noise.

### Animations

Subtle. Fast. Functional. Never decorative.

### Performance

Every screen should load in under **2 seconds** on average mobile networks.

### Accessibility

High contrast · color-independent status indicators · voice-friendly labels · large
touch targets · readable fonts.

### Final principle

At every design review, ask: *Can a 50-year-old QA/QC inspector who has never used
modern software complete this task without training in under two minutes?* If **No**,
redesign it.

---

## The "Zero Training UI" gate

Before **any** screen is approved, it must pass this checklist:

1. Can a first-time user understand the screen within **5 seconds**?
2. Can the primary task be completed within **2 minutes**?
3. Can it be used comfortably on a phone **in bright sunlight at a noisy industrial site**?
4. Does the screen **avoid jargon** unless absolutely necessary?
5. Can **80% of users** complete the workflow without reading a manual?

If any answer is **No**, the screen must be redesigned. This single principle keeps
the application dramatically simpler than traditional industrial software and is one
of its biggest competitive advantages.

---

## How this reference is used in practice

- **Design/build sessions** read this before creating or changing any user-facing
  screen, and self-check every screen against the Zero-Training gate above.
- **This is a UI/UX law, not a permission grant.** It never overrides
  `02-permission-matrix.md` or `03-object-lifecycles.md`. When a simpler flow would
  need a role to do something the matrix forbids, the flow stops and the question
  goes back to the owner — the blueprint does not authorise it.
- **Phone-first / desk-first split** (from `00-README.md`) still holds: inspectors
  are phone-first in the field; coordinators, managers and finance are desk-first on
  a laptop. Design for both; never average them into one middle.
