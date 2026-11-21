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

#### Back means UP. Always. (ratified 2026-10-15)

Every screen carries one back control, and it goes **one step up the breadcrumb
trail** — to the screen this one sits inside. It never goes to the screen you
happened to arrive from.

Why, in business terms: the browser already has "where I came from", on every
device, including a swipe on a phone. Spending our one control on a duplicate of
it leaves the question the browser *cannot* answer — "what is this screen inside
of?" — with no control at all. That is the question you need exactly when the
browser's own Back is useless: you typed the address, followed a link from an
e-mail, or landed here after a save.

Two consequences are binding:

- **The back control and the breadcrumb may never disagree.** The control is
  built from the trail, so they are the same answer shown twice. An owner
  photographed a screen whose back control said "← Masters" while the trail an
  inch below said the parent was "Users & access"; that must be impossible.
- **An area home's one step up is Home.** Never a screen inside that area. The
  same report showed Admin offering "← Approvals setup" — a screen *within*
  Admin, so "back" pointed downwards.

#### The menu decides where a screen lives

The breadcrumb names the area **the menu puts the screen in**, not the area its
permissions are grouped under. These are different questions and only one of
them is about place: permission grouping decides which checkboxes sit together
on the access editor. A trail that contradicts the menu the person just used is
worse than a short one, because it teaches the wrong place to look next time.

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
6. Does the screen call every object by **the same name every other screen uses**,
   taken from the terminology engine (`T()` / `Tl()` / `TH()` / `T_NEW()` …) rather
   than typed as a literal? See **ADR-002 — one word per object**.
7. Does every value the system filled in **say where it came from**, and does the
   screen refuse to guess when the source is ambiguous? See **ADR-004 — a contract
   number is a key, not a field**.

Item 6 is not a style preference. The Recruitment Command Centre once printed
"requirement" for the record every register called a "Requisition"; the owner read
the two screens side by side and concluded the system held two different objects.
A screen cannot be understood in five seconds if it disagrees with the screen one
click away, and a hard-coded word also silently defeats Admin → Terminology, which
makes a workspace's own rename look broken. `tests/test_recruit_terminology.php`
enforces this for the recruitment screens.

Item 7 is the other half of not making people re-type. A field the system filled in
is only trustworthy if the screen names the document it was read from — "value
₹450,000 — from PO 4500123" is a figure somebody can check in two seconds, while the
same figure appearing on its own is a figure nobody checks and everybody re-enters.
And when the source is genuinely ambiguous — two purchase orders, two open lines —
the screen must **offer the choice rather than pick one**, because a silent guess
here ends up on a real invoice. Prefilling is a convenience; prefilling without
provenance is a liability.

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

## The record page kit — the standard layout for one record (owner, 20 Nov 2026)

The owner approved the lead page drawn from their own picture and asked for it to
be "made universal ... for all the screen". It is the standard for every screen
that shows ONE record (lead first; deal, quotation, enquiry, project costing and
order next). Look and feel only — it decides nothing about who may do what.

- **Header card:** an icon tile, the record's name, its number and branch, a
  **status pill** (tap it to move the stage when the person may), the contact line,
  and ✏️ Edit · 📎 Attach · ⋯ More. A person who may not change the record sees the
  status as a plain label.
- **Three equal fact boxes** (value, date, who) — always the same height.
- **Stage path:** the company's own stages, then the next document (Quotation),
  then Won, then Lost (a red ✕ only when it closed there).
- **Icon tabs:** Overview · Details · Contacts · Quotes · More (with counts). Only
  the tabs a record has are shown.
- **Cards** with a title row and one action at its right (Edit, View all).
  Label / value rows share one label width; long words (e-mails) wrap inside.
- **Overview** carries the one next step and its single main button, four equal
  quick actions, a summary, the latest history. On a laptop cards sit in pairs of
  the same height; on a phone they stack in one column.
- **Closed records:** a locked card says why it closed (never editable — reopening
  is the way to change it); a won record shows a green banner with links onward.
  Figures that only mean something while open (e.g. "How promising") are hidden.
- Built with `views/ops/_rec_ui.php` (helpers and the one line-icon set) and the
  "RECORD PAGE KIT" block in `assets/css/app.css`. Never set a column count as an
  inline `grid-template-columns` — pass `--n` instead (the phone stylesheet
  collapses inline grids to one column).

### The Sales workspace screens (owner, 21 Nov 2026 — Sales redesign Stage 1)

Sales home (the Sales Command Centre), Today / My work, the Pipeline board and the
Sales dashboard share one look, built on the record page kit above. Look and feel
only: every figure, list and permission comes from the same handlers as before.

- **Sales section bar** (laptop and tablet): one dark-teal bar listing the Sales
  screens the person may open (read from the Sales area itself, `ops_area_def`),
  plus "All Sales screens" (/sales). It is the only dark-teal element; the app's
  own menu and header are not restyled.
- **Phone bottom bar** on these four screens: Home · Leads · Pipeline · Actions
  (Today) · Reports (Sales dashboard), each only when the person may open it and
  its feature is on. It never covers content (the page keeps room below).
- **Figure tiles:** status colours only (red overdue, amber due/warning, blue
  information, green won/on track, teal renewals, grey neutral); a tile that opens
  a screen is a link; numbers are written out, never only on hover.
- **Person filter:** Me · Everyone, and a list of salespeople — the same addresses
  as before (?who=…), managers only.
- **Long lists** (Today's follow-ups): a searchable, sortable table on a wide page,
  cards with Call · WhatsApp · Log on a narrow one; deals and leads in one list,
  each row marked Deal / Lead.
- **Board:** a Leads / Deals switch, a box per stage (count · value · win %) and a
  Total box; on a phone, tap a stage box to see its cards. No drag-and-drop — a
  stage is changed on the record, as before.
- Built with `views/ops/_sxw_ui.php` and the "SALES WORKSPACE" block in
  `assets/css/app.css`. The left menu's "Sales" opens Sales home for a person who
  may open it; everyone else still lands on /sales, which keeps working.

**Stage 2 — the lead screens (owner, 21 Nov 2026).** The leads register, the full lead
form, the quick lead, the lead → customer screen and the lead page carry the same
Sales bar and phone bottom bar ("SALES — LEADS" block in `app.css`):
- **Register:** six figure tiles (the "Not pursued" count included), one control row
  (Board · List, year, status on the list, pipeline on the board, find, one CSV),
  a board of stage columns that stack on a phone, and the shared register for the
  list with Call · WhatsApp · Log on each open lead and "Preview first" for bulk actions.
  Codes are shown in words (a lead's source).
- **Forms:** numbered steps (later steps fold on a phone until used), a right-hand
  column that says what happens next, and the buttons at the end of the form — never
  floating over it on a phone. A possible duplicate is shown under step 1 with an
  explicit "save it anyway" choice; nothing typed is lost after a refused save.

**Stage 3 — enquiries and deals (owner, 21 Nov 2026).** The inquiries register, the inquiry's
new read-only page, the inquiry form, the deals register, the deal page and the new-deal form
carry the Sales bar and phone bottom bar ("SALES — ENQUIRIES AND DEALS" block in `app.css`):
- **Registers:** figure tiles that say their period; one control row; the inquiries register on
  the shared register (paging, sorting, Columns, CSV); the deals board stacks on a phone and has
  a pipeline picker; the year picker only where it applies (the list).
- **Record pages** (inquiry, deal) use the record-page kit like the lead page: header card with
  status, contact and actions; three fact boxes; the stage path (deal); tabs — inquiry: Overview,
  Details, Quotes, History; deal: Overview, Quotes, Actions, History (the log form and timeline
  now inside History). Quotation statuses are always words, never codes. What a won deal is
  waiting on (the order raised, the contract's state, or "add the customer first" with its short
  form) sits above the tabs so it shows whichever tab is open; a win lands on Actions (the next
  step), a loss on Overview (why it was lost).
- **Forms** (inquiry, new deal): numbered sections, an action bar that says where you land after
  saving, the company's own extra fields above the buttons.

**Stage 4 — quotations, costing, templates, approvals (owner, 21 Nov 2026).** The quotation
register and page, the quotation form, external quotation, templates, approval rules and
delegations, the pre-order checklist, costing (list, page, heads) and the Quotation report carry
the Sales bar and phone bottom bar ("SALES — QUOTATIONS…" block in `app.css`); the Approvals inbox
uses the record-page look without the Sales bar (it serves every module):
- **Register:** figure tiles, one row of view tabs with "Awaiting your approval" and "To register a
  contract", the shared register (paging, sorting, Columns) with owner / branch / unit filters;
  values **before GST first**, the total with GST beside each (DEF-06).
- **Quotation page:** a header card (status, versions, PDF / Word / Edit / Raise) and three figures —
  Before GST, With GST, Validity; every panel and tab below as before; "Withdraw to draft" for the
  preparer; earlier revisions' files listed "from Rev NN" (DEF-05).
- **Other Stage 4 screens** keep their page head and panels inside the Sales frame (never inside
  the in-page popup).
- **Approvals inbox:** figure tiles per queue; quotations decided in the row (Approve / Reject with
  a comment); contract openings signed in the row; deals held at a stage and lead hand-overs as before.
