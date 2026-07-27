# Pending / parked items — Exaact Inspection & Operations Management System

Living list of things explicitly deferred, so nothing is forgotten. Newest on top.

## 🏷️ THE ACRONYMS ARE GONE (July 2026)

Owner: *"delete SGS, BOSS etc. and replace them with suitable name — for BOSS it
is Contract. Rename SBU with Business Unit or something like that."*

**BOSS → Contract Number.** Already the shipped term; this pass finished the job
by clearing the word out of every remaining comment, screen and document.

**SBU → Business Unit.** The shipped term is now `Business Unit` / `Business
Units`. Every screen that used to print the acronym now asks the terminology
engine instead (`T('sbu')` / `Tl('sbu')` / `TP('sbu')`), so a company that wants
a different word sets it once under **Settings → Terminology** and every screen
follows. The JavaScript no longer hard-codes it either — the page publishes
`window.TERM_SBU` and `app.js` reads that.

**The role stays `SBU_HEAD` in the database, and reads "Business Unit Head" on
screen.** Renaming the stored code would have orphaned every existing user row.

**Live databases are renamed too, not just fresh ones.** A database set up on an
older version still carried the acronyms as *data* — the list heading `SBU`, the
list heading `BOSS status`, and the designation value `SBU Head`. Two new
helpers, `lk_rename_type_label()` and `lk_rename_value_label()`, rename those in
place from `lk_migrate()`, and the boot probe in `index.php` asserts the old
wording is gone so an existing install actually runs the upgrade. Both only match
the *exact* old wording, so a company that has already renamed a list keeps its
own word.

**"SGS India Pvt. Ltd." on the login screen is not in the code.** It is the
`app_name` setting — whatever was typed into **Settings → Application name**.
Changing it there changes the login screen, the sidebar, the browser tab, the
PDF letterhead and the "From" name on every e-mail. Grep confirms no third-party
inspection-agency or real client name appears anywhere in the source, the seeds
or the documents.

*Verified:* lint green (135 files), 81 screens render, and every screen in the
app was crawled and grepped — no `SBU`, `SBUs`, `BOSS` or `SGS` survives in any
rendered page. Fresh-install boot produces the new wording directly.

## 📉 PROFIT ON ONE INSPECTION (July 2026)

**Profit by call** — a branch manager and the managers under them see what each
inspection actually made. Scoped to the branches they can already see; no new
visibility anywhere else.

    Revenue              invoice less any credit passed over
  − Engineer's salary    unloaded daily cost × days worked
  − Overhead             the branch percentage on that salary
  − Expenses at close    booked against the job when it closed
  − Voucher claims       what the engineer claimed on the monthly voucher
  − Sub-contractor
  − Anything else        hired instrument, permit, courier
  − Contingency          the branch percentage on all of the above
  ──────────────────────────────────────────────────
  = Profit, and margin = profit ÷ revenue

### Two things this corrected

- **Voucher claims were never in the per-job sum.** Closure expenses were;
  what the engineer actually claimed for travel and lodging was not. Every job
  looked better than it was.
- **Overhead was hidden inside the salary.** The daily rate had it baked in, so
  there was no line to point at. Salary and overhead are separate lines now and
  add to exactly what the loaded rate gave — the total is unchanged, the
  statement is readable.

Both percentages are per branch (Masters → Offices), falling back to the
company default in Settings. Nothing is hard-coded.

The branch's own shared costs — rent, the manager's salary, the back office —
are **not** pushed onto individual jobs. A job is judged on what it directly
caused; the branch is judged on the Business Unit P&L, where those are shared out at
month end.

## 🧮 RATE × DAYS, ON BOTH SIDES, AND THEN THE REVENUE (July 2026)

The client charge and the inter-office credit are agreed the same way — a rate
per man-day — so they are entered and totalled the same way, and the one figure
that follows from both is stated rather than left to be worked out on paper.

**On the call and on the allocation:**

| | |
|---|---|
| Unit rate | per man-day, from the order line |
| Total invoice value | rate × days |
| Credit per man-day | what the executing branch is paid for each day |
| Total credit | credit rate × days |
| **Revenue** | **total invoice − credit** |

Worked example, verified end to end: 6 days at 3,000 credited at 1,800 a day →
invoice 18,000, credit 10,800, revenue **7,200**. Change the man-days to 8 at
allocation and all three move: 24,000, 14,400, **9,600**. Either total can be
typed over when that one is billed or credited differently, and typing stops it
being recalculated.

Only the credit total used to be stored. A six-day deputation could carry one
day's credit with nothing on screen to show which figure was wrong.

**Revenue has its own permission — `data.revenue`.** A coordinator has to see
the credit to do the job and has no business seeing what the branch earns on it.
Where it is not granted the screen says so rather than leaving a blank. Granted
by default to the roles that already had contract profitability; the credit
boxes are untouched.

## 💰 THE MAN-DAYS ARE THE QUANTITY (July 2026)

Six man-days entered on the allocate screen, against an order line at 3,000 a
man-day, and the value stayed at 3,000.

The invoice value was carried across from the call **once** and then followed
nothing. If the call was priced as a single visit — which it usually is, because
the man-days are not known until somebody is allocated — the deputation kept
that one day's figure however many days were actually worked. Every multi-day
deputation raised off a single-day call was set up to be invoiced short.

The allocate screen now shows the **unit rate carried from the call** (read-only)
and works the invoice value out as **rate × man-days**, live. Man-days left at 0
means "count them from the dates", and the schedule says how many that is. Typing
over the value stops it being recalculated, and says so.

Recomputed on the server as well as in the browser — a figure that only exists
if JavaScript ran is a figure that will one day be wrong.

## 🧨 TWO THINGS THE SHAPE REWRITE BROKE (July 2026)

Both were mine, both were reported from the live site, and both are the same
kind of mistake — changing one thing and not following the thread to what read
from it.

**The billable value stopped calculating.** The quantity was worked out by
counting filled-in date boxes on the page. The moment the form only shows the
boxes the chosen shape needs, a continuous run of six days has no date boxes at
all — so the count read zero, the quantity fell back to one, and six days at
3,000 priced as 3,000. The quantity now comes from the schedule itself, which
is the only thing that knows: six days is six, a posting is however many
man-months are claimable, a lump sum is one. A hand-typed quantity still wins.

**Allocate died with "Unknown column 'schedule_weekdays' in 'INSERT INTO'".**
The pattern columns existed on `calls` and were added to the deputation's save
list, but never to the `jobs` table. `php -l` cannot see that, and neither can
any test that does not press the button.

`tools/check-columns.php` now closes that whole class: it builds a throwaway
database so every migration runs, then checks each save's field list against
the schema it will actually meet. Wired into `tools/lint.sh`. Verified by
removing the column again and watching it fail.

## 📅 FIVE SHAPES OF ENGAGEMENT (July 2026)

The three dates mean three different things and were being typed as though they
were interchangeable:

| | |
|---|---|
| **Call received** | the day the contracting branch got it |
| **Required** | the day the client asked for |
| **Scheduled** | the day we are actually going |

Only the third is chosen at allocation; the first two are settled on the call
and shown read-only. The end date is never typed — it follows.

An engagement is one of five shapes, and only the boxes that shape needs are
ever shown:

- **Single day** — one date, nothing else.
- **Continuous** — type the number of days. The end date counts *working* days:
  Sundays, the branch's Saturday pattern and the branch's own public holidays
  are stepped over. Five days from Thursday 30 July ends Tuesday 4 August at a
  six-day branch and Wednesday 5 August at a five-day one.
- **Multiple dates** — two date lines, and one more each time you ask. It runs
  from the earliest to the latest; the days between are not inspection days.
  Each visit can carry a different engineer.
- **Pattern** — chosen weekdays, N times a week, every N days, fortnightly or
  once a month, until a date. The dates are worked out, not typed.
- **Monthly deputation** — a posting at the works on a man-month basis.

Holidays now belong to a branch (blank = national). All the arithmetic lives in
`lib/schedule.php` and is asked of the server as the form is filled in, so the
holiday rules exist in exactly one place. Where an engineer is already booked,
the clash is named and the branch's free engineers are offered instead.

### Settled

- **Saturday is a full working day for an inspection engineer.** The 5 / 5.5 / 6
  pattern on an office is about office staff; it never applied to a man on a
  site, and applying it made every end date a day late. Only Sundays and the
  branch's public holidays are stepped over now.
- **A Sunday followed by a Monday holiday pushes the visit to Tuesday** — and
  the coordinator can pull either day back in. Every skipped day inside a run is
  offered as a tick-box; ticking one records that it will be worked and the end
  date moves back with it.
- **A monthly deputation runs the 1st to the last day of the month**, whatever
  day it starts.

### Man-months — configured in three places, most specific wins

| Where | When to use it |
|---|---|
| **Settings → Financial & operations** | the company default |
| **Client master** | this client's contract says something different |
| **The call / the allocation** | this one order differs from what that client normally agrees |

Two definitions:

- **Calendar month** — one man-month whatever the working days come to. 24 days
  or 27, it is one.
- **Minimum working days** (usually 26) — a month falling short is claimable
  pro-rata (21 working days = 21/26 = 0.81 man-months); a month running over is
  still exactly one. The extra day is not billable.

The allocate screen shows the month-by-month working, says which of the three
places the definition came from, and the claimable total becomes the billable
quantity.

## 🛑 SAVE MUST NEVER FAIL SILENTLY (July 2026)

Reported twice. The first fix was wrong, and this records why so it is not
attempted that way again.

A browser refuses to submit a form holding an invalid field and tries to point
at it. If that field is not on screen it cannot point at anything — so it
refuses, says nothing, and the button looks dead. Every searchable dropdown in
this app hides its real `<select>` behind a text box, so **any** required
dropdown was one empty answer away from killing the whole form.

The first attempt listened for the form's `submit` event and took the
requirement off anything hidden. It could never have worked: the browser blocks
*before* `submit` fires, so the listener never ran.

The browser's own checking is now switched off (`noValidate`) and done in the
page instead, where it can be seen:

- on screen and wrong → rung in red, scrolled to, named in a message above the
  form; nothing is submitted;
- off screen → let through to the server, which checks it anyway and answers
  with a message that opens the section it lives in.

Either way something visible happens when Save is pressed. The guard also stops
the one-shot-ticket handler when it blocks, so the button no longer greys out
reading "Saving…" over a form that is not going anywhere.

## 🔗 WINNING IT PUTS THE COMPANY ON FILE (July 2026)

### Bugs fixed, from the live site

- **The PO line item list was empty even when the order existed.** The chain
  broke one step earlier than it looked. A quotation raised against a *typed*
  client name was never bound to the client master, so the Purchase Orders tab
  had no quotation to offer, the order was typed in by hand, and the lines that
  already existed on the quotation never came across. Marking a quotation
  **accepted** now registers the company — deliberately incomplete, tax details
  and address to follow — links every revision to it, and carries the types of
  inspection and the contact across. An order that has no lines now says so, on
  the order itself, on the client master's order list, and inside the call
  form's own dropdown, and offers to take the lines from the quotation.
- **"Allocate & send email" did nothing.** The expected inter-office credit was
  mandatory on *every* deputation, including one where a single office both
  holds the order and does the work — where there is no such credit to state.
  The browser refused the submit and the button died silently. The credit is now
  required only when the deputation really does cross offices; where it does
  not, the value carried is what the client is billed on the call.
- **contract number is now the contract number.** It is not chosen from a register
  any more — it comes down from the quotation to the call to the deputation, and
  the register entry is created on saving. The old register is still there and
  still holds the renewal chain; it just fills itself.

### Revenue and invoice value — the owner's definition, now the only one

    INVOICE VALUE  what the client is charged, as agreed on the purchase order
                   or the quotation. Once a bill is raised it is the bill.
    REVENUE        what a branch keeps out of it. Same branch holding the order
                   and doing the work → the whole invoice value. Two branches →
                   the holder keeps invoice − credit, the executor books the
                   credit.

Every screen reads this from one function. Two things it guarantees, both of
which were wrong before: branch revenues added together come back to the
invoice value exactly, and a same-office job is no longer worth nothing.

Jobs now carry the invoice value and the contracting branch of their own, both
carried from the call. Older rows were filled in from the call they came from
on the next boot; a cross-office job that only ever recorded a credit reads the
credit as the whole of it, so the holding branch shows nil rather than a loss
it never made.

## 🧾 QUOTATIONS, MASTERS & THE CALL FORM (July 2026)

### Bugs fixed, from the live site

- **"Save inspection call is not working."** The credit box was made required
  the moment *any* executing office was chosen — including when it was the same
  office holding the contract, in which case the whole box is hidden. A browser
  will neither submit a form with a required field it cannot show, nor say why.
  The button just died. Now the credit is only required when the call really
  does cross offices, and as a safety net across the whole app no form can be
  blocked by a field nobody can see.
- **A revision lost the sites and the types of inspection.** See the previous
  section — carried-across columns are now derived from the table rather than
  written out by hand.
- **PO line items were never fetched.** Because purchase orders were created
  empty and the lines had to be typed by hand afterwards. A PO recorded against
  its quotation now copies every quoted line, so the call register finds them.

### What to test

1. **Quotations** — approve, then *Take my approval back*. Mark one sent: it
   locks for everybody, and offers *Raise a revision* instead.
2. **Purchase Orders tab** — pick the quotation first: the contract number, business unit,
   value and every line item come across.
3. **Clients** — complete a client from a name sales typed into an inquiry: the
   types of inspection, the contact and the inquiry/quote links come with it.
4. **Quick-add a vendor from a call** — the GSTIN fills the PAN and the state,
   the state is a dropdown, and the same company cannot be added twice.
5. **A refused save** — the box that stopped it is ringed in red and the form
   scrolls to it.

### Closed since

- [x] **Duplicate review** — Clients → *Possible duplicates* finds pairs the add
      screens cannot: different spellings, M/s, Pvt Ltd, word order. Shows both
      sides with everything hanging off each, and merges only when a person says
      so. The dropped record is marked *merged*, never deleted.
- [x] **An order follows its quotation.** A PO raised against a quotation that is
      later revised says so and offers to pull the new lines through — refused
      once any of the order has been consumed, because the balances were measured
      against the old quantities.
- [x] **Client and vendor lists are one register across all offices** — verified,
      no change needed. If a branch should only see its own, that is a new
      requirement, not a fix.

## 📈 MANAGEMENT DASHBOARD, FINANCIAL YEARS & THE CLOSE-ON-TIME LOCK (July 2026)

### What to test

1. **Management dashboard** (new, in the menu). Filter by financial year,
   month, business unit, activity code, executing office, contracting office, engineer
   and client. Nine KPI cards, eight breakdown tables, each downloadable as a
   spreadsheet. Every figure is counted off ONE set of jobs by ONE rule, so no
   two tables can disagree.
2. **Financial years follow the data.** Enter work dated next year and next
   year appears in the list; the year ahead is always offered so work can be
   entered before April.
3. **The close-on-time lock.** An engineer has 2 days after the inspection ends
   (Settings → *Days to close a job*). Miss it and the job locks: nothing can
   be changed, the engineer/coordinator/branch manager/administrators are
   e-mailed once, and **documents can still be uploaded**. A manager can reopen
   it for a few days with a reason, which is written to the audit trail. The
   register shows a count of locked jobs and marks each row.
4. **Sub-contractor cost** now lands in the month-end run on the job, its
   contract number, its business unit and its activity code — never spread.

### Closed since

- [x] **The lock sweep no longer waits on cron.** Once a day, the first page
      somebody opens runs it. Setting the cPanel cron is still worth doing (it
      is tidier and does not depend on somebody signing in), but the alerts now
      go out either way.
- [x] **Year-on-year** — inspections, man-days, revenue and profit each carry
      the same slice of the calendar a year earlier. A period with nothing a
      year ago says so rather than inventing a percentage.
- [x] **Utilisation %** — man-days worked against days available (working days
      less holidays, across every engineer the filter covers).

### Still open

- [ ] **The dashboard reads jobs, not vouchers.** Where a voucher timesheet
      disagrees with a job's man-days, the job wins here. The reconciliation
      screen is the place that surfaces the difference — this is a deliberate
      choice, recorded so nobody has to rediscover it.

## 💰 COSTING & PROFITABILITY — complete, ready to test (July 2026)

Five commits: `9c75b5f` (the allocation engine), `4bc4c97` (expense heads master +
monthly cost entry), `23068db` (client/vendor import, clear records, the written
explanation in `docs/COSTING.md`), and this one (person cost & split, outstation
tick, month-end run, business unit / activity / contract P&L).

**Read `docs/COSTING.md` first** — the whole model in plain words with worked
figures, and the order to do things in month by month.

### What to test, in order

1. **Masters → Office expense heads** — add one of your own, change how it
   spreads, retire one. Delete the ones you do not use; they stay deleted.
2. **Each person's record → Cost & where it belongs** — monthly cost, and the
   tick for whether they do inspections. Non-engineers get one percentage box
   per business unit in their branch; the running total has to reach 100.
3. **A call → Outstation** — tick it, allocate the call, check it carried over.
4. **Office costs & overheads → Actual costs** — a month of real figures, then
   *Copy last month* into the next one.
5. **Month-end cost run** — preview, read the warnings, *Calculate and store*,
   then *Close the month*. Closing locks the entry screens; reopening is
   recorded in the audit trail.
6. **business unit profit & loss** — revenue against cost by business unit, by activity code, and
   by contract number.

### Closed since

- [x] **The Profitability screen and the Business Unit P&L now cross-link**, each saying
      what it does and does not include.
- [x] **Year to date and whole year** on the Business Unit P&L, alongside one month. It
      says how many of the months in the span have actually been run, and warns
      that months not yet run contribute revenue but no cost.
- [x] **Sub-contractor cost** is in the allocation run, on the job, its contract
      number, its business unit and its activity code.
- [x] **Closing a month closes its timesheets.** A frozen month refuses voucher
      edits with the reason, on every write path including a typed URL. Reopen
      the month to correct a day.

## 🔐 SECURITY & COMPLIANCE — shipped 2026-07, with what is still open

Four commits: `220d5cd` (forgery, sessions, login limits, uploaded files), `1dede08`
(passwords, two-step sign-in, session expiry), `ad44ecb` (attachment checks, host
lockdown), `2ec3caa` (compliance screen, incident register, data-subject rights, SBOM).

### ⚠️ OWNER ACTIONS — not code, nobody else can do these

In priority order. The first five cost almost nothing and matter more than anything
in the code.

- [ ] **1. HTTPS on the live site.** THE biggest single gap. Free Let's Encrypt
      certificate in the MilesWeb cPanel. Once `https://yourdomain` works in a
      browser, remove the `#` from the four lines at the bottom of `phpapp/.htaccess`.
      **Do not uncomment before the certificate works** — visitors would be sent to an
      address that does not answer and the site would look down. Until this is done the
      session cookie cannot be marked "never send unencrypted" and passwords travel in
      the clear on any hotel or plant wifi. The compliance screen reports it red.
- [ ] **2. Change `admin/admin12345`** and the demo passwords (`demo12345` — director,
      bmanager, nisha.mehta, account, insp.ravi). The Users screen now names every
      account still on a shipped password, in red. Tick "they must choose their own at
      the next sign-in" when handing one over.
- [ ] **3. Grievance officer + privacy notice** — Settings → Compliance. Both are
      required by the DPDP Act. A complete draft notice written for an inspection
      business sits in the box as placeholder text: read it, correct what is not true
      of you, paste it in. An afternoon's work.
- [ ] **4. Two e-mails to MilesWeb, replies filed:**
      (a) confirm the account **and its backups** sit in an **Indian data centre**
      (CERT-In log-localisation); (b) confirm NTP is synchronised to
      **time.nplindia.org** or an NIST server (CERT-In clock-sync). Software cannot
      answer either — the compliance screen says "not ours to answer" for both.
- [ ] **5. Backups, and a restore that has actually been tried.** Every photograph,
      report and voucher is in one database. Schedule it in the panel, download a copy
      somewhere else, and **restore it once to prove it works.** Not on any regulatory
      list and more likely to save the business than everything above it.
- [ ] **6. Switch on two-step sign-in** for the roles that can move money or change
      permissions — Settings → Security → "Roles that must use two-step sign-in".
      Those people get nudged on every screen until they set it up.
- [ ] **7. Book the CERT-In empanelled audit.** Required annually. List published on
      cert-in.org.in; roughly ₹75,000–2,50,000 for an app this size. **Do items 1–4
      first** or you will pay to be told what is already written here. Record the date
      in Settings and the compliance screen turns green.
- [ ] **8. ISO/IEC 27001 or SOC 2 — only when a client asks.** These certify the
      *company*, not this software. Voluntary. Roughly ₹4–10 lakh and 6–12 months.
      The IT Act names ISO 27001 as *a* benchmark for "reasonable security", not the
      only one; demonstrable practices plus a CERT-In audit report is defensible for a
      company this size.

### 🔧 CODE — deliberately not built, or built only part way

- [ ] **Content-Security-Policy still carries `'unsafe-inline'`.** The screens use
      inline `onclick` handlers and inline `<style>`, so script injected into a page
      could still run. What it could NOT do is load code from another site or send what
      it found anywhere — `connect-src 'self'`, `object-src 'none'`, `form-action
      'self'` all hold. Closing it properly means moving every inline handler and style
      block out to files and adding a per-request nonce. Sizeable, low urgency while
      the escaping in the views holds.
- [ ] **Consent register is built but not wired in.** `data_consents` exists; nothing
      writes to it yet. For most of what this app holds the lawful basis is
      contract-performance, not consent, so this matters mainly if marketing e-mails
      are ever sent. Wire it at the contact-capture points if that changes.
- [ ] **No encryption at rest, by decision.** On shared hosting the key would sit in
      `config.php` next to the database password — anyone who can read one can read the
      other, so it buys almost nothing and risks losing the data if the key is lost.
      Real disk/database encryption is the hosting provider's to offer. Revisit only if
      a client contract demands it in writing, and then encrypt named columns (salary,
      bank details) rather than everything.
- [ ] **No IP allow-listing.** Would lock the app to the offices' addresses. Not built
      because inspectors work from client sites on mobile data. Worth adding for the
      *accounts and settings screens only* if ever wanted.
- [ ] **Two-step enrolment has no QR code** — the setup key is typed into the
      authenticator app by hand. A QR needs a pure-PHP encoder (~300 lines with
      Reed-Solomon) since no library can be installed on the host. Manual entry works
      with every app; add the QR if enrolment turns out to be a support burden.
- [ ] **Erasure covers system users and client contact persons only.** Candidates
      (`candidates`, with CVs) and inspectors-without-a-login are also personal data and
      have no erase path yet. Add `person_erase_preview()` / `person_erase()` branches
      for both.
- [ ] **Data-subject requests have no clock.** Incidents count down from six hours;
      requests just sit as "Open". The DPDP Act says "without undue delay" rather than
      naming days, but a visible ageing pill would stop one arriving on a Tuesday and
      being remembered in December.
- [ ] **Audit trim is a button, not a schedule.** `audit_trim_old()` runs only when
      somebody clicks it on the compliance screen. Wire it into `cron.php` so retention
      is applied whether or not anyone remembers.
- [ ] **The CERT-In incident report is composed by hand.** The screen states the address
      and everything they expect, all on one page, but does not send it. Wiring it to
      the existing `ops_mail` composer would remove a step at exactly the moment
      somebody is panicking. Worth doing.
- [ ] **SBOM is regenerated by hand** — `php tools/sbom.php` from the `phpapp` folder.
      Should run as part of whatever the release routine becomes, so `SBOM.json` cannot
      quietly go stale. Currently: 127 files, **zero third-party runtime dependencies,
      zero resources loaded from other sites** — measured, not asserted. This is the
      strongest compliance card the app has; keep it true.
- [ ] **Per-source login throttle could lock a whole office.** Counted per IP at 30
      failures / 15 minutes. A NAT'd office shares one address. Deliberately loose, but
      watch it once real staff are on it; the admin can release an account from the
      Users screen.

### 👀 OBSERVED ONCE, NOT REPRODUCED

- [ ] **A session ended unexpectedly straight after sign-in**, once, during testing.
      Never recurred across many later runs. The likely cause is the standard race when
      the session id is replaced at sign-in (`session_regenerate_id(true)`) and a second
      request arrives carrying the old id — every framework has this, and the
      alternative leaves the pre-login id valid, which is the thing being closed. Left
      as-is. **If anyone reports being thrown back to the sign-in page immediately after
      signing in, this is the first place to look.**

### 🐛 TWO REAL BUGS FOUND AND FIXED WHILE TESTING (for the record)

- A submission that was turned away sent the person back to the **wrong screen**. The
  browser reports where it came from as a full address; the code tested it for a
  leading `/`, which never matches, so the record id was dropped and they landed on an
  empty register. It also compared the host *with* the port against the host *without*
  it. **The same flaw was in the duplicate-submission path and had been doing this
  silently all along.** Now parsed properly in `redirect_back()`, same-host only.
- The default-password check ran **four bcrypt comparisons per account on every page
  load** of two screens. Bcrypt is deliberately slow; at fifty staff that was seconds
  of waiting. Now tests only accounts whose password has never been set through the
  app — anything set through it has already been refused by the policy.

### 🧪 TEST-SUITE HOUSEKEEPING (dev only, not shipped)

- [ ] Several scratchpad suites hard-code record ids (`job=315`, `call=326`,
      `document?id=1`) from whichever database existed when they were written, and fail
      as "precondition missing" on a fresh one. Worth changing to *find* a suitable
      record instead. `tools/smoke.js` and `tools/lint.sh` (incl. `check-dupes.php`,
      `check-strings.php`) are the two that always work and should be run before every
      upload: `sh tools/lint.sh && node tools/smoke.js`.

## 🚀 NEXT BIG BUILD — IDEMS: Intelligent Inspection Documentation, Reporting & Endorsement Engine (TPIA Industry Pack) — owner spec 2026-07, roadmap pending approval

A world-class TPIA documentation ecosystem. 24-part spec. Two core workflows:
(A) TPIA prepares & issues its OWN reports; (B) TPIA reviews/verifies/endorses/certifies
manufacturer/vendor/contractor documents. One platform for both. Configurable, mobile-friendly,
offline-capable, AI-ready, no-code report builder.

REUSE (already built — do NOT duplicate): crm_templates docx engine + doc/format-number stamping,
lib/pdf.php SimplePDF + signature image + per-company letterhead, custom-fields engine (dynamic
fields on any entity, cascading lookups), lookup masters, approval-chain (quote_approval_rules +
REPORTS_TO reporting-manager chain), report-approval + escalation, lib/ai.php provider seam,
email_log, deliverables master (IR/IRN/NCR/CoC…), FY/office/business unit scope.

Proposed phasing (each phase = its own commit, tested):
- P1 Foundation ✅ SHIPPED — lib/idems.php: report_types registry (32 TPIA types seeded + admin CRUD,
  unlimited) + configurable no-code IRN engine (token format, zero-duplicate via unique index +
  scope counters) + report_docs instance model + DRAFT→SUBMITTED→ISSUED with immutable finalize +
  idems_audit log + Document Register + IRN-rules + audit-log viewer. Module 'idems' + perms
  (idems.finalize/type.manage/timestamp.edit/audit.view) wired into roles.
- P2 No-code Report Builder ✅ SHIPPED — report_sections/report_fields/report_files schema; builder UI
  (18 field types incl. conditional show-if, calculated formulas, mandatory/hidden, repeatable tables,
  photo/file/GPS/signature, options incl. lookup:key); fill renderer with live conditional/calc JS,
  table add-row, signature canvas, GPS capture, file/photo upload (base64 in report_files, 6 MB cap);
  values saved to report_docs.data JSON; detail renders filled body + evidence thumbnails; finalize
  still locks it. Routes: /report-builder, /report-field-edit, /document-fill, /report-file.
- P3 Workflow & approvals ✅ SHIPPED — idems_approver_map (per-inspector approver, common approver,
  temp cover during leave), idems_approval_rules (configurable multi-level chain matched by report
  type/office/client/business unit; approver = inspector-map / reporting-manager / specific user / role; per-level
  SLA), report_approvals steps built on submit (submit blocked if no approver), approve/reject/
  send-back/delegate with mandatory remarks, finalize gated on full approval, SLA auto-escalation in
  cron, approval-chain panel + act buttons on the report detail. Routes: /approver-map,
  /idems-approval-rules(-edit), /document-approve.
- P4 Auto signatures + immutable timestamps ✅ SHIPPED — signature on users + inspectors; self-service
  /my-signature (draw or upload) + inspector-master signature panel; report PDF (/document-pdf) with
  letterhead + key refs + designed body (fields/tables/photos) + auto signature block (inspector +
  final approver, images + name/designation/emp-code/branch + system timestamps) + DRAFT watermark;
  signatures snapshotted onto the report at finalize (frozen); Branch-App-Mgr-only date edit
  (/document-timestamp) with mandatory reason → tamper-proof audit (old/new/user/reason/ip/time).
- P5 Client-specific formats ✅ SHIPPED — report_templates (upload .docx per report type/client/office,
  with doc/format/revision numbers); generic {{token}} fill reusing the docx engine + a generalised
  repeatable-table row expander ({{fkey.col}}); token map from standard fields + the type's designed
  fields; most-specific template auto-selected; /document-docx generates output in the client's exact
  format (fonts/headers/footers/logo/tables preserved); token-reference UI. NO agency names seeded
  (owner instruction) — admin uploads their own client templates. Routes: /report-templates(-edit/
  -download), /document-docx.
- P6 Manufacturer document verification & endorsement ✅ SHIPPED — endorsements + endorsement_files
  tables; upload manufacturer quality records (MTC/NDT/hydro/PWHT/hardness/FAT/calibration/welding/HTR/
  etc.) — the ORIGINAL is stored & never altered; metadata + supporting evidence + linked inspection
  report; auto endorsement number (reuses IRN engine, END type); assigned approver (mapped or picked);
  submit→review→endorse/endorse-with-comments/reject (reject remarks mandatory); auto inspector+approver
  signatures snapshotted at endorse; SEPARATE endorsement-certificate PDF (green band) referencing the
  original; immutable after endorse; full endorsement audit trail; soft-delete archive. Routes:
  /endorsements, /endorsement(-new/-edit/-submit/-approve/-delete/-file), /endorsement-cert.
- P7 Technical Writing Assistant (no AI) ✅ SHIPPED — tech_phrases library (35 standard phrases seeded
  across observation/acceptance/rejection/conclusion/recommendation/hold/witness/deviation; admin adds
  unlimited); rule-based tech_expand(): per-line shorthand→library match (exact/contains/word-overlap)
  else tidy pass (domain spell-check, abbreviation expansion, capitalisation, terminal punctuation);
  standalone /writing-assistant tool + clickable phrase panel; inline "✒️ Improve wording" button on
  every report textarea via AJAX; usage counters feed future self-learning (P13). Routes:
  /writing-assistant, /phrase-library, /phrase-edit.
- P8 Smart Remarks & auto Release Note ✅ SHIPPED — rule-based idems_smart_remarks() scans the filled
  body for adverse signals (not-ok/reject/deviation/defect/expired/…) and drafts summary, observations,
  deviations, hold/witness points, conclusion, acceptance and recommendations from the phrase library;
  proposes result + release status; /document-smart preview screen (editable) with one-click apply.
  /document-release-note auto-drafts an RN report from an APPROVED/ISSUED report — carries all refs
  across, wording follows the findings, links back to the source (shown as a banner), duplicate-guarded
  on source_report_id, left as DRAFT for review before issue.
- P9 AI-assisted documentation ✅ SHIPPED — ai_chat() POST seam added to lib/ai.php (openai-compatible,
  anthropic, gemini, perplexity, copilot; normalised reply, graceful errors). Source-document library
  per report (PO/call/QAP/drawing/spec/standard/MTC/calibration/previous report/customer instruction)
  stored as report_files kind=src_*; best-effort text extraction (text, docx via zip, uncompressed PDF).
  Rule-based checks ALWAYS run without AI: missing expected documents, blank PO/drawing/QAP/standard
  traceability, drawing-not-found-in-attachments, drawing REVISION MISMATCH, expired calibration vs
  inspection date. Optional AI layer reads the pack + findings and returns missing docs / revision-spec
  conflicts / traceability issues / suggested hold + witness points / draft remarks, parsed into
  sections. Inspector is always the approving authority (stated in UI). Route: /document-review.
- P10 Smart photo & evidence management ✅ SHIPPED — report_files gains sha1/caption/taken_at/bytes/
  orig_bytes. Two-stage compression: browser-side canvas shrink before upload (saves mobile data) +
  server-side GD resize/recompress (max 1600px, q82, transparency flattened) — ~80-90% smaller on real
  camera photos, deterministic output. EXIF capture-time extraction, auto GPS capture on photo select,
  sha1 duplicate detection (same content skipped, reported in the flash). New /document-evidence
  gallery: organised by report section → field, thumbnails, capture time, clickable GPS (maps link),
  size + saving per item, editable captions, remove; KPI strip (items / stored size / space saved /
  GPS-tagged). Locked once the report is finalized.
- P11 Super-Admin audit & compliance dashboard ✅ SHIPPED — login/logout/failed-login now audited too.
  /audit-log rebuilt as a compliance dashboard: KPI strip (total events, today, high-risk 30d, active
  users), automated compliance checks (inspectors with no approver, reports stuck in review >7d,
  timestamp changes in 30d, users without a signature, failed-login spikes, soft-deleted reports),
  activity-by-action bar chart (high-risk flagged), most-active users, by-branch chips, full filter set
  (action / branch / user / date range / free-text over IRN+user+value+reason / high-risk-only),
  plain-English action labels, high-risk rows highlighted, and CSV export of the filtered view.
  Records are immutable and never purged; deletes stay soft.
- P12 Offline-first / mobile field UX ✅ SHIPPED (PWA-lite, as scoped for shared hosting) —
  manifest.php (installable, app name/theme follow Settings, inline SVG icon so no binary assets),
  sw.js service worker (network-first pages cached for re-open, cache-first assets, offline fallback
  page, never caches POSTs or auth), assets/js/offline.js: per-form localStorage draft autosave with a
  "restore this draft?" prompt, offline submit queue replayed automatically on reconnect (forms with
  photos are held back with a clear message since images need a live upload), and a fixed connection
  banner showing offline / syncing state. Fill form marked data-autosave + field-mode CSS (single
  column, 16px inputs to stop iOS zoom, 44px+ touch targets). index.php serves /sw.js, /manifest.php
  and /assets/* before the auth gate (path-traversal guarded) so it works on any host rewrite;
  .htaccess sets Service-Worker-Allowed + no-cache for sw.js.
- P13 Self-learning suggestions ✅ SHIPPED — learned_suggestions table; learn_from_report() harvests
  wording when a report is APPROVED or ISSUED (never from drafts): per report-type field wording,
  per-client wording, remark sentences, and recurring NCR causes (adverse results). Ranked
  learn_suggestions() (client-specific first, then type-wide) surfaces as clickable "Used before ×N"
  chips on text/textarea fields in the fill form — click to insert, nothing auto-applied. /learning
  insights screen: KPIs (learned entries, times seen, reports learned from, NCR patterns), most-used
  standard phrases chart, scope filter, and per-entry Standardise (promote into tech_phrases) / Mute /
  Restore. Suggestions only ever enhance — technical conclusions and approvals are never altered.

=== IDEMS COMPLETE: all 13 phases shipped ===

- ➕ Form-from-format generator ✅ SHIPPED (owner request) — /report-form-from-template reads an
  uploaded client .docx, extracts every {{token}} in document order, derives the LABEL from the text
  immediately before the token ("Description of non-conformance: {{nc_description}}" → that label),
  guesses the field type from key+label (date/time/number/textarea/select/text; "…No." stays text),
  groups {{field.col}} tokens into a repeatable table with its columns, skips standard header tokens
  (irn/client/po/drawing/…) and fields that already exist, then shows an EDITABLE plan (tick, rename,
  change type, edit table columns, choose/name the section) and creates the fields in one click.
  Buttons on Report templates ("🪄 Build form") and in an empty form builder. Verified round-trip:
  NCR format uploaded → form generated → filled → client-format .docx produced with no leftover tokens.

- ➕ Prefill from the call / job ✅ SHIPPED (owner request) — idems_context_for() gathers everything
  already known (call code, client, vendor, business unit, office, product, inspection type, PO number + line
  item, site address, notes; job code, inspector, inspection dates, contract number, quote/contract).
  "New report" from a call or job (buttons on call_detail + job_detail, plus a call picker on the
  new-report screen) prefills the whole header. idems_autofill() then ALIGNS THE DESIGNED FORM FIELDS
  via an alias map (customer/purchaser→client, supplier/manufacturer/works→vendor, dwg_no→drawing,
  date_of_inspection→date, inspected_by→inspector, works_location/site→location, equipment/item→
  product, call_no→call code, division→business unit, contract_ref/boss→contract no., qty_offered→PO line qty …),
  so a client-worded format fills itself. Never overwrites what the inspector typed; auto-filled
  fields are badged "auto" with a summary banner. report_docs.job_id now stored alongside call_id.
  Verified: customer→Northern Petrochem, supplier→Vapi Chem, date_of_inspection→2026-07-13, inspected_by→Ravi
  Kumar, equipment→Pressure vessel, call_no→C-2607-001, division→Industrial, contract_ref→40231.

Constraint note: MilesWeb shared PHP hosting — no Node/build; "offline-first" is delivered as a
responsive PWA-lite (localStorage drafts + autosave + sync), not a native app.

## 🧭 NEXT BIG BUILD — Workforce, hierarchy & permissions pack (owner request 2026-07, before dashboards)

Five owner requirements, to be built as extensions of existing tables (reuse, don't duplicate):

1. **Daily inspector-availability board** on the dashboard of the *office's* Coordinator,
   Operation Manager & Branch Manager: how many inspectors are FREE vs ALLOCATED today,
   with a one-click per-inspector dropdown to set today's status — Available / On job /
   On leave / Training / In office / Half-day / etc. "Allocated" is auto-derived from jobs
   scheduled today; the dropdown writes a date-specific override.
   → new `inspector_day_status` table (inspector_id, day, status, note, set_by), AJAX set
   endpoint, dashboard panel scoped to the viewer's office(s).
2. **8.5-hour daily cap** — no inspector may log > 8 h 30 m (= 8.5) of working hours on any
   single working day. Enforce on the timesheet/voucher `hours` entry (sum per inspector+date),
   block + warn on exceed.
3. **Weekly working days = 5 or 5.5 per employee** — configurable field on inspectors (and
   surfaced on users). Feeds per-person working-day / leave / utilisation maths.
4. **Reporting-manager chain + auto org hierarchy** — assign each position a reporting manager
   manually (name, position, email; link a system user when one exists). System then derives the
   N+1 chain automatically (already have `users.reports_to_id`). Wire the chain into:
   (a) CRM quote approvals — option to route up the reporting chain in addition to the existing
   amount/business unit rules; (b) inspection/report approval — report/closure routes to the inspector's
   reporting manager. Org-hierarchy view under Settings.
5. **Exhaustive, role-divided permissions with one-click presets** — make the permission catalogue
   complete & clearly worded, grouped, and give the access admin a "recommended set per role"
   that applies in a single selection, with a readable per-role explanation so an admin knows
   at a glance who gets what.

Status: 1 ✅ 2 ✅ 3 ✅ 4 ✅ 5 ✅ — ALL SHIPPED. (availability board, 8.5h cap, weekly-days,
reporting chain + hierarchy + CRM REPORTS_TO approval routing + inspection report sign-off,
grouped exhaustive permissions + one-click recommended-per-role presets.)

Follow-on requests (2026-07) — ALL SHIPPED:
- Working weekly hours/days per designation per office → work_norms master + inheritance ✅
- Automate reporting → overdue-report escalation to reporting manager + weekly/monthly MIS digest ✅
- Dashboards for all roles → executive strategic board (FY revenue + YoY + target + top clients +
  sales won) added on top of the existing role-based section ordering ✅


## 🧭 NEXT BIG BUILD — CRM / Marketing & Sales module (roadmap pending owner approval, 2026-07)

The whole pre-operations sales funnel: **inquiry → quotation → approval → send →
follow-up → acceptance → client/contract registration → hand-off to Operations →
revenue tracking.** To be built as a new module that plugs into the EXISTING
operations system (do NOT duplicate — reuse the tables/masters/email/roles below).
**Build CRM first; the Executive-Director dashboard rebuild is parked to do
afterwards (current landing dashboard "is not what we're expecting").**

### Owner's 25-point requirement (verbatim intent, condensed)
1. Customer inquiry received via email (capture it).
2. Quotation number generation (auto).
3. Quotation generation (the document).
4. Allocate to different business unit(s) when generating the quote (per line if needed).
5. Upload the quote WORD format; it must adapt to a new/revised format whenever a
   new .docx is uploaded (template-driven, hot-swappable).
6. Fields designable — create / edit / delete fields to match the new format.
7. Person only enters data → system auto-creates the quotation document.
8. Every data field should be able to offer drop-downs for fast entry.
9. After creation it moves through an approval chain as required.
10. Once fully approved → sent to the customer directly on their email.
11. Auto follow-up reminder emails at 3 / 6 / 9 days, fortnight, month, with a
    pre-drafted follow-up message. Templates editable by admin / marketing mgr.
12. If acceptance arrives mid-chain → close the follow-up chain → route to Accounts
    to register the client → create a contract + contract number (here or external).
13. On contract-number entry, auto-float an Operations packet: client name, quote
    no., contract no., contact person / email / mobile, service requirement,
    techno-commercial proposal.
14. Always show OPEN / PENDING / CLOSED (accepted) quotes.
15. Monthly report.
16. Integrate operations so revenue is tracked line-item-wise against each quote
    number AND contract number.
17. Two order types: OPEN (no PO — e.g. ARC) vs line-item orders (X days, Y months,
    technical-audit days, …). Sales rep enters the order lines once the contract
    number is generated.
18. Job type configurable: Inspection, Project deputation, … Inspection sub-cats:
    Product Inspection, Expediting Visit, Tender Document Review, Document Review,
    Vendor Assessment, Vendor Audit, …; Project-deputation sub-cats: Site QA/QC,
    Commissioning, O&M, Erection, … — multiple-selection allowed.
19. Inspection location may differ from the client's registered/contract address —
    "agreed location" or "Pan-India" must be clearly stated.
20. CV-to-client tracking for deputation: client requirement, which CVs submitted &
    when, client feedback (shortlisted / rejected), interview required? planned for /
    completed on / outcome; on selection → auto-email candidate requesting all
    credentials (CV, salary slip, …) via a configurable template.
21. Advance-payment flag so Operations knows payment must be received BEFORE
    scheduling the inspection.
22. Payment-linked deliverable: report released only against payment; show the
    inspector a HOLD so they don't issue the deliverable; fetch this rule from the
    quotation / final accepted terms.
23. Quote revision is compulsory when needed → auto Rev. 01 numbering, with an edit
    history of what changed (reuse the contract supersede/hierarchy pattern).
24. Fetch the required deliverable(s) from the quote into Operations.
25. Job types (full list): Inspection, Project deputation, Supply-chain deputation,
    Site supervision, Commissioning & Installation, Site QA/QC, Technical audit,
    Type test, Vendor Assessment, Vendor Audit, Document Review, Tender Review, …

### What ALREADY EXISTS to reuse (integration surface — researched)
- `business_partners` (clients/vendors, GSTIN, `contract_number`, inspection_types),
  `partner_contacts` (name/email/mobile), `partner_site_addresses` (locations),
  `partner_contracts` (contract_number/title/sbu/value/dates),
  `partner_purchase_orders` + `po_line_items` (trade/skill/site/manpower/activity/
  gst/base/tax/total — this is largely the "order line items" of §17), contract numbers.
- Calls→Jobs pipeline with `inspection_type`, `job_type` (INSPECTION/DEPUTATION),
  `deliverables`, `site_address_id`, `po_id`, `po_line_item_id`, activity, business unit,
  mandays; invoicing + payment + credit + profitability.
- Masters/lookups engine (configurable dropdowns → §8), `INSPECTION_TYPES` (30+),
  `JOB_TYPES`, `DELIVERABLES` constants + lookup overrides (§18/§25).
- Candidate + Requisition + offer-stage pipeline (partial base for §20).
- Email infra (`ops_mail`, SMTP settings) + daily reminder cron (`ops_run_reminders`,
  cert reminders) — the scaffold for §11 follow-ups.
- Access/roles + per-module View/Edit + scope; theme/masters admin.

### GAPS I flagged (owner may have missed / to confirm)
- **No quotation/inquiry module at all** — quotations, quote numbers, quote line
  items, revisions, approval chain, send-to-customer: all NEW.
- **Marketing/Sales roles missing** — add BDM, Key Accounts Manager, Marketing
  Manager, Marketing Executive (Business Development). Map to access modules.
- **Word-template engine (§5–7) is the key technical decision.** On MilesWeb shared
  PHP (no Composer/PHPWord), the pragmatic path is a **.docx with placeholder tokens**
  (e.g. `{{client_name}}`, `{{line_items}}`) that we fill by unzipping the docx with
  PHP `ZipArchive` and string-replacing in `word/document.xml` — no library, upload-
  and-go, hot-swappable format. Alternative: HTML→print-to-PDF. Needs owner's pick.
- **Approval chain needs a rule/matrix** — "as required" must become configurable
  (levels by value threshold and/or business unit, with named approvers). Confirm the rule.
- **Lost/Rejected quote status** — owner listed Open/Pending/Closed(accepted) only;
  need a LOST/REGRETTED state + reason for win/loss analytics.
- **One quote → multiple business units** (§4) implies business unit per quote line (split), not one business unit
  per quote — confirm.
- **Advance %/payment terms & "report-vs-payment" gate** need explicit fields on the
  quote that flow to the job and show the inspector a HOLD (§21/§22).
- **Revision history** — capture field-level diff, not just a new rev number (§23).
- **Duplicate-inquiry / duplicate-quote** guard; attachments (techno-commercial PDF).

### Proposed phased roadmap (see chat for the approved-pending version)
- P0 Foundations: sales roles + access modules; CRM masters (job-type / inspection
  sub-category as multi-select configurable masters); quote/inquiry numbering.
- P1 Inquiry + Quotation core: inquiry capture, quote header + line items (business unit per
  line), auto quote number, revisions (Rev 01) + history, dropdown-driven entry.
- P2 Template engine: upload .docx template, map/define fields (add/edit/delete),
  auto-fill via ZipArchive token replacement → downloadable quote.
- P3 Approval + send + follow-up: configurable approval chain → approve → auto-email
  customer with the quote → 3/6/9/fortnight/month follow-ups with editable templates;
  Open / Pending / Closed / Lost states.
- P4 Acceptance → hand-off: acceptance closes follow-ups → Accounts registers client +
  contract number → auto-float Operations packet (§13); OPEN (ARC) vs line-item
  orders (§17) entered by sales rep after contract number.
- P5 Ops integration + revenue: link quote/contract line items to calls/jobs; revenue
  line-item-wise per quote & contract (§16); advance-payment flag + payment-before-
  report HOLD visible to inspector (§21/§22); deliverables/terms fetched from quote.
- P6 CV-to-client tracking (§20): client submission → feedback → interview → selection
  → auto credential-request email (configurable template).
- P7 CRM dashboards + monthly report + win/loss analytics.

### ✅ P0 shipped (2026-07) — CRM foundations
- **4 sales roles** added (Business Development Manager, Key Accounts Manager,
  Marketing Manager, Marketing Executive) with sensible scope + management
  classification; they appear in the user-creation role dropdown.
- **CRM permission selection list** built: 4 access modules (Inquiries, Quotations,
  Orders/contracts, Sales reports) each with View/Edit, plus fine-grained actions
  (create / approve / send quote, manage follow-ups, register client+contract,
  manage templates) — all editable per role (Settings → Roles & access) and per user.
- **Lost-reason master** (`quote_lost_reason`, 12 researched reasons incl. "Other
  (specify)" → free-text on the form in P1) + **CRM service-type master**
  (`crm_service_type`, §18/§25) — both editable lookups.
- **CRM data model created** (idempotent, SQLite+MySQL): `crm_inquiries`,
  `quotations` (+rev/parent for §23 revisions, advance/report-vs-payment flags),
  `quote_lines` (business unit per line, order type, units), `quote_revisions`,
  `quote_approvals`, `quote_approval_rules` (amount band and/or business unit — §owner),
  `quote_followups`, `crm_templates` (docx + email, with a JSON field map for §6).
- Boot probe extended so live MySQL auto-creates the CRM tables on next load.
- **Next: P1** — Inquiry + Quotation screens (list/form/detail), quote numbering,
  revisions, dropdown-driven entry.

### ✅ P1 shipped (2026-07) — Inquiry + Quotation core
- **Inquiry register** (§1): list + new/edit, auto `INQ-#####` number, client/contact/
  business unit/source/status, "Quote" button that carries an inquiry into a new quotation.
- **Quotation core** (§2,3,4,8,14,23): list with **Open / Pending / Closed(won) / Lost**
  views + KPI counts; new/edit form with header (client, contacts, business unit, site +
  location type, validity, payment terms, **advance % + advance-required**,
  **report-vs-payment** flag, GST) and **dynamic line items** (business unit per line, service
  type, order type OPEN/LINE, qty/unit/rate, live totals); auto `Q-#####` number.
- **Status workflow**: Draft → Submit → Approve → Send → Accepted / Lost, gated by
  the CRM permissions; on **Sent** the 3/6/9-day, fortnight, month **follow-ups are
  scheduled**; on **Lost** the researched reason dropdown + "Other (specify)" free text.
- **Revisions** (§23): "Revise" creates Rev 01/02… as a fresh draft, keeps the old
  version + a change-note in history; list shows only the current revision.
- CRM nav group (Sales / CRM → Inquiries, Quotations) gated on the CRM modules.
- **KNOWN DEV-ONLY QUIRK:** the *revise* row-copy binds only leading columns under
  **SQLite + PHP's built-in `php -S` dev server** (a pdo_sqlite/built-in-server
  defect — every CLI run and the create/status/lost paths are fine). **Production is
  MySQL, which uses real prepared statements and is unaffected.** Verify "Revise" once
  on the live site; if a revision ever copies blank there, tell me.
### ✅ P2 shipped (2026-07) — .docx quote template engine
- **Quote templates admin** (CRM → Quotations → Templates, gated by
  `crm.template.manage`): upload the Word quotation **format** (.docx), set it default,
  activate/deactivate, re-upload a revised format anytime, download the original.
- **Controlled-document identity carried from the uploaded format** (owner's ask):
  each template stores a **Document number, Format number, Revision and Issue date**;
  these stamp onto every generated quote via `{{doc_number}}` / `{{format_number}}` /
  `{{doc_rev}}` / `{{doc_date}}`. Upload a revised format with a new number → new quotes
  show the new number automatically.
- **Token engine** (no external library — `ZipArchive` + string replace): fills header
  tokens (quote no, client, contacts, business unit, commercials, totals, **amount in words**),
  **repeats a table row per line item** (`{{l_desc}}` etc.), and **repairs tokens Word
  splits across runs** (verified: `{{cli|ent_name}}` rejoined correctly). Tokens are
  documented on the template form.
- **"⬇ Generate Word quote"** button on the quote detail → downloads the filled .docx.
- Verified end-to-end over HTTP (upload → create quote → generate): doc/format numbers
  stamped, line rows repeated, totals + words correct, no unreplaced tokens.
### ✅ P3 shipped (2026-07) — approval chain + send + follow-up emails
- **Configurable approval matrix** (Quotations → Approval rules): rules by **amount
  band** and/or **business unit**, with a **level** (chain order) and an **approver role or a
  specific person**. On "Submit for approval" the matching rules become the quote's
  chain; with no rule it needs one approval from any approver.
- **Approval flow**: each approver sees Approve/Reject (with remarks) for their step
  on the quote; the quote auto-moves to **Approved** when all steps pass; a reject
  sends it back to draft. Gated by the "Approve quotations" permission.
- **Send to customer** (§10): "✉ Send to customer" generates the .docx and **e-mails
  it (attached) to the contact** using the EMAIL_QUOTE template (or a sensible default),
  marks Sent, and schedules the follow-ups. Uses the existing SMTP settings; if SMTP
  isn't set it's logged (email_log) and still marked sent.
- **E-mail now supports attachments** (multipart/mixed added to smtp_send/ops_mail).
- **Follow-up e-mails** (§11): `crm_run_followups()` (wired into cron.php) sends any due
  3/6/9-day, fortnight, month follow-up whose quote is still awaiting a reply, using the
  EMAIL_FOLLOWUP template; skips once the quote is accepted/lost.
- Verified end-to-end: rule match → chain built → approve → Approved → send (logged) →
  follow-up cron sends the due one and skips the rest.
### ✅ P4 shipped (2026-07) — acceptance → contract → Operations hand-off
- **Acceptance → register client** (§12): marking a quote **Accepted (won)** opens an
  Accounts panel; entering the **contract number** auto-registers the customer as a
  client (if only a name was typed → `GEN-<name>-####`) and links `client_id`.
- **Contract record** (§13): a `partner_contracts` row is created (number, title,
  value, dates) and linked to the quotation (`contract_id`, `contract_number`).
- **Operations packet auto-floated** (§13): on contract entry an e-mail goes to the
  coordinators + ops managers with **client, quotation no, contract no, contact
  person/email/mobile, business unit, location, value, advance/report-vs-payment flags, the
  service requirement, and the order lines** — with the **techno-commercial (.docx)
  attached**. A "Re-send to operations" button re-floats it.
- **Open (ARC) vs line-item orders** (§17): each order line is labelled
  **[Open order (ARC / call-off)]** or **[Line-item order]** in the packet (the type
  is captured per line on the quote).
- Verified end-to-end: typed-name client auto-registered (GEN-BRAND-0001), contract
  linked (₹7,67,000), packet body correct with ARC vs line-item labels + service req.
### ✅ P5 shipped (2026-07) — operations / revenue integration
- **Job ↔ quotation link** (§16): the job allocate/edit form has an "Against quotation
  / contract" picker (accepted/in-flight quotes for that client); `jobs.quotation_id`.
- **Revenue per quote/contract** (§16): the quote detail shows a "Jobs &amp; revenue
  against this order" panel — ordered vs invoiced vs received, with each linked job.
- **Advance / report HOLD for the inspector** (§21,§22): linking a job inherits the
  quote's **advance-required / advance-% / report-vs-payment** onto the job; the
  inspector's **My Jobs** cards and the job detail show a red **"HOLD — do not issue the
  report/deliverable"** banner while the advance/payment is pending. Coordinator/Accounts
  can **Mark advance received** (`/job-advance`); the hold clears on payment.
- **Deliverables from the quote** (§24): a linked job with no deliverables inherits the
  ones listed on the quote's lines.
- Verified: inherit set adv_required/adv_pct/report_hold + deliverables (IR,COC), both
  HOLD reasons shown when unpaid and cleared when paid, revenue panel + advance toggle.
### ✅ P6 shipped (2026-07) — CV analysis + client-submission tracking
- **CV keyword analysis &amp; search** (owner's ask): on the candidate, upload the CV
  (.docx / .txt; .pdf best-effort) or paste the text → an **internal, dependency-free
  engine** extracts keywords (a curated inspection/QA-QC/TIC vocabulary — CSWIP, NACE,
  ASNT, NDT methods, API/ASME codes, disciplines, sectors — plus the trade/skill masters
  and top frequent terms) and stores them. Keywords show as clickable chips; the hiring
  search now matches **cv_keywords + cv_text**, so you can find CVs by skill for future
  requirements. **AI-ready:** `cv_extract_keywords()` has a `cv_ai_available()` seam so
  it can defer to a provider once the AI-keys feature lands — no caller changes needed.
- **CV-to-client tracking** (§20): per candidate — CV submitted-to-client date, client
  feedback (Shortlisted / Rejected) + date + note, interview required / planned-for /
  completed-on / outcome (Selected / Rejected / Hold).
- **On Selected → credential-request e-mail** (§20): one click e-mails the candidate for
  CV, salary slips, IDs, certificates (EMAIL_CREDENTIAL template or a sensible default).
- Verified: keyword extraction, keyword search hit, tracking saved, credential e-mail
  logged to the candidate.
### ✅ P7 shipped (2026-07) — Sales / CRM dashboard + monthly report + win/loss
- **Sales dashboard** (CRM → Sales dashboard, gated `mod.crm_reports.view`): FY-filtered,
  scope-aware. KPIs — quotations, **open pipeline value**, **won value**, **win rate**.
- Charts: quotes by status (donut), quoted value by business unit, **top customers by quoted &
  by won value**, and **"Why we lost" win/loss** breakdown by reason (§ lost-reason master).
- **Monthly performance table** (§15): per month — raised / won / lost / won value.
- **CSV export** of all quotes in scope for the FY.

### ✅ Client PDF + signature + customisable letterhead (2026-07)
- **Client-facing quote is now a PDF** (the .docx stays for internal editing). A
  dependency-free pure-PHP writer (`lib/pdf.php`, no Composer/library) renders a
  professional quotation — line items, totals, amount-in-words, terms — and the
  **"Send to customer" e-mail now attaches the PDF**. Buttons on the quote: **PDF (for
  client)** + **Word (editable)**.
- **Signature image** (upload PNG/JPG under CRM → Templates) + name/designation are
  **stamped on the PDF** (GD normalises PNG → JPEG; embedded via DCTDecode).
- **Customisable per-company letterhead** (owner's ask): upload **logo**, set company
  **name / address / contact line / footer** — rendered as the PDF letterhead; the
  document & format numbers from the uploaded format print top-right.
- Verified: valid PDF (correct xref/EOF), letterhead + logo + signature embedded,
  generated over HTTP (application/pdf), admin panels save.

### ✅ CRM ROADMAP COMPLETE (P0–P7)
Inquiry → quotation (+ revisions, Word template w/ doc & format numbers) → approval
chain (amount/business unit) → send-to-customer (Word attached) → follow-up e-mails → acceptance
→ client + contract registration → Operations hand-off → job link (revenue, HOLD,
deliverables) → CV analysis + client-submission tracking → sales dashboard/reports.
Remaining big items: the **AI-keys** master-settings feature, then **dashboards for all
roles** (incl. the Executive-Director rebuild).

### ✅ AI keys in master settings — SHIPPED (2026-07)
- **Settings → AI providers & models** (`/ai-settings`, master/settings.manage): store an
  **API key per provider** — OpenAI, Claude (Anthropic), Google Gemini, Perplexity,
  GitHub Copilot / Models — masked in the UI (never shown in full; re-save with the mask
  keeps the key; "Clear key" removes it). Per-provider **enable** toggle + optional base URL.
- **Auto-refreshing model lists:** "Refresh models" pulls each provider's live list
  (OpenAI `/v1/models`, Anthropic models list, Gemini `/v1beta/models`; Copilot catalog);
  **retired models drop off** (active selection is intersected with the fresh list).
  Providers without a public list API (Perplexity) use a curated known-model list; any
  refresh failure falls back to known models so a model can still be picked.
- **Pick active model(s)** per provider (checkbox grid).
- Foundation helpers `ai_enabled()` / `ai_active()` + a proxy/CA-aware cURL client;
  `cv_ai_available()` now reflects the config so the CV keyword engine can defer to AI.
- Verified: save/mask/enable/select, masked re-save keeps key, refresh fallback works.
- **Follow-up (not yet wired):** actually calling the selected model in features (e.g.
  CV analysis, quote drafting) — a generic `ai_chat()` per provider. Keys/models are ready.

### 🤖 (superseded) original AI-keys request note
Administrator can, under master settings, enter **API keys for multiple AI
platforms** of their choice — **Copilot, Gemini, Claude, Perplexity, OpenAI** —
and then **select which model(s)** to use under each provider. Requirements:
- Per-provider key entry (stored in settings; masked in the UI).
- Model list **auto-updates** from each provider and **auto-discards** old/retired
  models no longer offered (so the dropdown always reflects live availability).
- Select one/more active models per enabled provider.
- Research note: auto-refresh needs each provider's "list models" endpoint (e.g.
  OpenAI `/v1/models`, Gemini `/v1beta/models`, Anthropic models list); "Copilot"
  has no public models API — confirm whether that means GitHub Models or Azure
  OpenAI. Outbound network from MilesWeb must be confirmed. To sequence after (or
  alongside) CRM — owner to confirm ordering.

### PARKED (do after CRM): Executive-Director dashboard rebuild
Current landing dashboard for the Business/Executive Director "is not what we're
expecting." Rebuild to a strategic C-suite view (pipeline value, win rate, revenue
by business unit/customer/project, forecast vs actual, top accounts) — AFTER the CRM lands so
it can draw on real pipeline data.

## ✅ Just shipped (2026-07 — owner's screenshot batch)
- **Distinct employee-code series for contractors.** A new inspector saved with a
  blank Employee code now auto-gets a code by engagement kind: **SC-###** for
  sub-contractors, **FL-###** for freelancers, **EMP##** for our own staff — so
  payroll/accounts can tell them apart at a glance. Manually typed codes are kept
  as-is. Demo sub-con Mohan reseeds as **SC-001**. (`next_emp_code()` in ops.php.)
- **"Food bills (actual)" expense head** added alongside "Food allowance (meals)"
  (now an ALLOWANCE; the new head is an actual BILL needing a receipt). Expense
  heads are now ensured **by code** on boot, so existing live databases gain the
  new head automatically without wiping custom heads.
- **contract numbers list** (Profitability screen) rebuilt as an accessible table with
  **Sr No · contract number · Client · Status · Created on · Expires on · Renewed into
  (renewal hierarchy) · Jobs · Invoicing done · Expenses booked** + salary-gated
  **Salary costing · Profit INR · Profit %**, KPI cards, expiry pills, and CSV.
- **Vouchers screen role-scoped cards** — Total expense claimed · This month ·
  Awaiting approval · Paid, scoped to the role (inspector sees only their own).
- **Insights dashboard (/reports):** added a **client-name filter** to the filter
  bar, a **Top 10 customers by revenue** chart and a **Revenue by contract**
  chart in the Financial section. The **Certificates-expiring** panel is now hidden
  for the **Business Director** role (strategic view, not an ops-compliance task).
- **Demo reload guidance:** the "already loaded" message now tells the user to
  Remove + Load again to pick up newer sample records. Root cause of "agencies /
  requisitions look empty" is the one-shot `demo_seeded` flag — the seed itself is
  correct (verified: 2 agencies, 2 requisitions render for admin *and* director).
- Remaining voucher/contract polish parked below (§ Reports Phase 2, deputation).

## 🆕 Requested — to build next (noted 2026-07, owner)

### 1a-i. Recruitment-fee costing — the GUARANTEE model (resolves owner's confusion)
The one-time recruitment fee is **conditional**, so it is NOT a fixed cost:
- Agency contract carries a **guarantee / replacement period** (e.g. 90 days).
- On the hire, the fee has a status: **Provisional** (still inside the guarantee
  window) → **Confirmed** (person stayed past it → fee is a real cost) OR
  **Waived** (person left inside the window → we don't pay; agency gives a free
  replacement → fee cost = 0, carried to the replacement).
- **Costing rule:** the fee counts in the inspector's cost **only when Confirmed**;
  shown as "provisional" until the guarantee lapses; ₹0 if Waived. No arbitrary
  monthly spreading. Build: `guarantee_days` on the agency; `fee_status` +
  `guarantee_upto` on the inspector; a small daily check (cron) that flips
  Provisional→Confirmed when the date passes; a "provisional fees / guarantees
  lapsing" dashboard card.

### 1a-ii. Offer stage: released → declined (candidate reneges)
- Add pipeline stages **Offer released** and **Offer declined** (candidate backed
  out at the last moment) so we can see offer-decline rate and re-open the
  requisition to the next candidate.

### 1e. Manpower Requisition / Position Approval module — ✅ CORE DONE
- [x] **DONE** — `requisitions` (New/Replacement, office/business unit/designation/site,
      budgeted cost, approval ref/date/by, status Open→Proposed→Offer→Hired→
      Closed); Requisitions screen (list/form/detail) under Hiring; REPLACEMENT
      links the outgoing engineer; detail shows **Outgoing vs Budgeted vs Hired**
      monthly-cost comparison (salary-gated); the candidate CV form **requires an
      approved requisition** and Accept auto-fills it (status→HIRED, inspector
      linked); sidebar item + dashboard "open requisitions" card. Guarantee-fee
      costing + Offer/Declined stages also done (§1a-i, §1a-ii).
- [ ] **Remaining polish**: hard-block hiring if no requisition (currently the
      form requires it, but server doesn't reject a hand-crafted post); WAIVE the
      placement fee automatically when a replacement is raised for someone who
      left within guarantee; requisition CSV/PDF approval register; email on
      approval / on fill.
- [ ] *(original design, for reference)* Management approves **every position**
Management approves **every position** (new or replacement); the whole hiring
chain hangs off that approval.
- [ ] **`requisitions` table**: req_code, office, business unit, designation/position,
      project/site, **type NEW vs REPLACEMENT**, budgeted monthly cost, approved_by,
      approval ref + date, status (Open → Proposed → Offer released → Hired →
      Closed / Cancelled), notes.
- [ ] **Replacement linkage**: when REPLACEMENT, link the **outgoing (resigned)
      inspector** — auto-fetch their current salary/cost as the benchmark.
- [ ] **Candidate ↔ requisition**: a candidate CV is raised **against a
      requisition**; the pipeline (with the new offer stages) runs inside it; on
      Accept the **hired candidate → inspector** is linked back to the requisition
      and it closes.
- [ ] **Auto-fetch salary / cost comparison**: pull the proposed candidate's
      expected rate and the hired inspector's salary_ctc / agency_cost; for a
      replacement, show **outgoing vs new** cost side by side (budget vs actual).
- [ ] **Dashboard**: open requisitions, positions pending fill, replacements in
      progress; approval register export (CSV/PDF).
This becomes the front of the hiring flow:
**Requisition (approval) → Candidate(s) → Offer → Hire (inspector) → close**,
and feeds the agency/roll/fee logic already built.

### 1. Complete demo / seed dataset (uploadable, all expected values) — ✅ DONE
- [x] **200+ edge cases** — DONE. The seed now generates **332 edge-case records**
      (150 calls + 150 jobs + 32 vouchers) covering same/cross office, missing
      vendor/dates, overdue, zero & large amounts, billable-vs-credit, every
      invoice/payment/credit state, sub-con, zero man-days, 0-day/null TAT, all
      stages, and voucher statuses incl. leave-only zero-total. Count shown on load.
- [x] **DONE** — `lib/seed_demo.php` + a Master-Admin-only **"Load demo data"**
      button in **Settings** (POST `/seed-demo`, idempotent via `demo_seeded`).
      One click inserts 3 peer offices (Mumbai HO + Ahmedabad + Pune), 11 users
      (every role, password `demo12345`), 4 inspectors (incl. an agency sub-con)
      with entitlements, 3 clients + 2 vendors, 3 contract numbers, 6 calls, 6 jobs
      across the full lifecycle (paid / awaiting / overdue / unbilled / in-progress
      / sub-con), closure expenses, and 2 vouchers (DRAFT + APPROVED). Every
      screen shows live figures immediately. *Follow-ups when the credit rules
      below land: extend the seed with same-vs-different-office credit examples.*
- [ ] *(original ask, for reference)* A **ready-made sample dataset** that can be loaded into a fresh install so
      the whole system can be explored end-to-end with realistic values —
      **from user creation → multiple offices → clients/vendors → contract
      numbers → calls → job allocation & scheduling → inspection → voucher
      (km + bills) → closure → invoicing → payment → inter-office credit.**
      Purpose: demos, training, and testing every screen with data already in
      place. Should include: several **offices** (peer offices + Mumbai as the
      commercial HO), **users of every role** (Master Admin, Business Director,
      Business Unit Head, Branch/Branch-App Manager, Operation/Asst. Manager, Coordinator,
      Accountant, Inspector), **inspectors** with salary + entitlements, a few
      **clients/vendors/sites**, **contract numbers**, a spread of **calls & jobs**
      (some same-office, some cross-office), **completed vouchers**, and
      **invoiced + paid + credit-settled** examples so profitability, dashboards
      and the money desk all show live figures immediately. Delivered as a
      one-click "Load demo data" action or an importable seed the owner can run.

### 1b. Full access / permission control (every module & feature) — ✅ DONE
- [x] **DONE** — per-module **View + Edit** permissions for all 14 modules
      (Calls, Jobs, Vouchers, Invoicing, Profitability, Hiring, Reconcile,
      Clients, Vendors, Masters, Overheads, Reports, Users, Settings) plus the
      fine data/feature perms. Managed **both** ways: a **Settings → Roles &
      access** editor (per-role, "edit implies view", stored as an override) and
      the **per-user** panel (full checklist). Sidebar + every module route are
      gated on the view perm; inspector My Jobs/My Voucher stay exempt;
      backward-compatible for users saved before the change. Verified across all
      demo roles. *Follow-up ideas: office-scoped module grants; an audit log of
      access changes.*
- [ ] *(original ask, for reference)* **Comprehensive access matrix** — today only ~17 permissions exist and most
      screens are gated by *role level* (coordinator/admin), not fine permissions.
      Owner wants the super admin to grant/deny **each and every module and
      feature**, not a limited set, and to manage it **in Settings** (like the
      permission checkboxes already in the user-create panel, but complete).
      Build: (a) expand the `PERMISSIONS` catalogue to one "can access" entry per
      module — Calls, Jobs, Vouchers, Invoicing, Profitability, Hiring, Reconcile,
      Clients, Vendors, Masters, Overheads, Reports, Users, Settings — plus the
      finer action perms (create call, allocate/close job, see credit, see salary,
      manage masters, reconcile, etc.), grouped by module in the user panel;
      (b) gate the sidebar nav + each route on these perms (defaulting them ON for
      roles that have access today, so nothing locks out); (c) a **Settings →
      Roles & access** editor — a role × permission grid the super admin edits,
      stored in settings and overriding `role_defaults()`. Backbone change —
      scope confirmed with owner before building.
- [x] **Clearer "inspector not linked" message** — DONE. An Inspector login with
      no linked inspector profile now gets one actionable message on My Jobs and
      My Voucher (Users → Linked inspector) instead of "You cannot view vouchers".

### 1c. Agency master + contracts + roll-conversion + costing — ✅ CORE DONE
Two agency types, each with a contract and a different fee model — both feed
**inspector costing** and need **renewal reminders**.
- [x] **DONE** — `agencies` master (type Recruitment/Manpower, contact, contract
      no. + start/end, one-time fee, monthly rate); **renewal reminder card** on
      the dashboard (≤30 days, colour-coded); Candidate **Accept** now picks the
      supplying agency + roll (own payroll vs agency) + fee, and the new inspector stores
      agency_id / roll_type / placement_fee (one-time, tracked separately) /
      agency_cost (monthly, into loaded cost). One-time recruitment fee is
      **recorded, not amortised** (owner: tenure is unpredictable).
- [ ] **Remaining follow-ups**: (a) show/edit agency, roll, placement fee on the
      **inspector edit form** + inspector costing breakdown; (b) turn the renewal
      reminder into an **email** via `cron.php` (currently a dashboard card only);
      (c) **manpower pass-through invoicing** — we invoice the client our rate
      while the agency bills us their monthly charge (margin = our rate − agency
      charge); ties into §1d monthly invoicing.

- [ ] **Agency master with a type**: **Recruitment agency** (CVs only, one-time
      placement) vs **Manpower / supply agency** (supplies people we run).
      Reuse/extend `subcons` or `business_partners` (is_subcontractor) with a
      `agency_type` flag.
- [ ] **Agency CONTRACT with renewal reminder** — every agency engagement is a
      contract with a start/end (or renewal) date. Send a **reminder ~1 month
      before the due/renewal date** (reuse the existing cert-expiry reminder
      pattern in `cron.php` + a dashboard "expiring soon" card). Applies to
      BOTH recruitment and manpower agencies.
- [ ] **Fee model by type → inspector costing**:
      • **Recruitment** → person is on **our own payroll** (salary CTC) **plus a
        one-time fixed placement/consulting fee** paid to the agency; that fee is
        **included in the inspector's costing** (decide: one-time in the hire
        month vs amortised over expected tenure — confirm with owner).
      • **Manpower** → agency **bills us monthly**; that monthly charge is the
        inspector's `agency_cost`, and **we invoice the client** for the manpower
        (pass-through — ties to §1d monthly invoicing).
- [ ] **On Accept, choose the roll + agency + fee**: own payroll (salary) vs agency
      roll (monthly charge); pick the agency (from the master) and its
      contract; capture the one-time fee (recruitment) or monthly charge
      (manpower). Writes `agency_name` + `agency_cost` (+ new one-time-fee field)
      on the inspector so loaded-cost/profitability already reflect it.

### 1d. Monthly / recurring invoicing for deputation (man-month / man-day)
- [ ] **NOT built yet.** Invoicing is currently **one invoice per job**. A
      man-month resident deputation (or a man-day contract billed monthly) needs
      a **billing schedule**: for a deputation job with a rate + start/end,
      generate a **monthly invoice line** per active month, so the accountant
      gets a **month-wise list of pending invoices** ("Deputations to bill for
      July", man-day contracts rolling up that month's man-days, etc.). Pairs
      with the Invoicing FY/Month filter (item under §2 reports). Applies to
      man-month, man-day and lumpsum. New model: an `invoices` / `billing_lines`
      table keyed by job + month, feeding the money desk and CSV/PDF exports.

### 2. Credit tab — driven by contracting vs executing office — ✅ DONE
- [x] **DONE** — calls carry a **contracting office** + executing office. On the
      call form the credit section toggles: **same office → "Billable value
      (ex-GST)" + basis** (no inter-office credit); **different office → "Credit
      to executing office" + type** (mandatory). Call detail shows a "Credit /
      billing & cost" panel; for cross-office the **executing office can revert
      with the credit it requires** (COUNTERED / AGREED). **Cost incurred**
      (vouchers + expenses) is shown to **both** offices, and the calls list is
      visible to both contracting & executing offices with a **cost column +
      min-cost filter**. (Also fixed a latent scope bug so branch users actually
      see their office's records.) *Follow-ups: voucher auto-download+submit step;
      email the executing office when credit is proposed/countered.*
- [ ] *(original ask, for reference)* **Same contracting & executing office** → the **Credit tab is DISABLED**
      (no inter-office credit to record), BUT the call must still **show the
      billable value** — invoice / **man-day** / **man-month** value —
      **excluding GST**. (So a single-office job still shows what it's worth,
      just with no credit hand-off.)
- [ ] **Different contracting & executing office** → the **Credit tab is FULLY
      OPEN**. The **credit to be given to the executing office** is **clearly
      stated** on the call. The **executing office can revert with the value of
      credit it requires** for that call (a counter-value / negotiation back to
      the contracting office), so both sides agree the credit.
- [ ] **Voucher officially downloaded & submitted** — the prepared voucher
      (Statement of Travelling Expenses) must be **officially downloadable** and
      **submitted** as the record for the call (PDF download + submit step).
- [ ] **Both offices see the spend** — the **contracting office AND the executing
      office** can both **check the amount spent on the call**. Both can **filter
      the inspection list and see the cost incurred** (including **all expenses**)
      for each inspection, shown to each office **according to its scope**
      (contracting sees its calls; executing sees the calls it executed).
- [ ] **Invoicing filters** — the Invoicing / money desk (`/invoicing`) needs a
      **filter bar** like the Dashboards: **Financial Year, Month**, plus office,
      business unit, client and status bucket (pending / awaiting / overdue / credit). The
      counts and worklist recompute for the chosen period so an accountant can
      pull, e.g., "unpaid invoices for FY 2026-27, July, Ahmedabad." Filters
      respect the user's scope, and the filtered view should also be exportable
      (ties into the downloadable-reports item below).

### 3. Downloadable reports — ✅ PHASE 1 DONE (CSV exports)
- [x] **DONE** — dependency-free CSV export (UTF-8 BOM for Excel). "Download CSV"
      buttons on **Jobs, Calls (with cost incurred), Invoicing, Profitability**
      (each respects the current scope + filters), a **Download-reports** section
      on the Dashboards page (permission-gated), and **voucher download**
      (`/voucher-csv` → full Statement of Travelling Expenses, plus Print/Save-PDF).
      *Remaining from the catalogue below (future): TAT report, office/Business Unit P&L,
      utilization/productivity, overdue-aging, inter-office credit statement,
      and PDF statements for invoices/credit notes.*

### 3b. Downloadable reports — remaining catalogue (research, for later)
Goal: let every function pull the data it needs as a file (Excel/CSV for
analysis, PDF for official statements). Proposed catalogue to build:

**Operations**
- [ ] Call register (with lead-times, status, pending-scheduling flag)
- [ ] Job register / allocation report (inspector, dates, contract numbers, status)
- [ ] **TAT report** — on-time vs late, average TAT, by office / business unit / inspector
- [ ] Overdue-closure report (jobs past scheduled/required date)
- [ ] Scheduling / dispatch board export (what's due, who's free)
- [ ] Inspection volume by client / vendor / site / inspection-type

**Finance**
- [ ] **Profitability by contract / contract** (revenue − labour − exp − subcon − OH − contingency)
- [ ] **Office P&L** (per peer office; own targets vs achieved)
- [ ] **Business Unit P&L** (credit vs distributed loaded cost vs net)
- [ ] **Voucher / expense register** (per inspector/month, per expense head)
- [ ] **Invoicing & payment** — raised / received / outstanding / **overdue aging** (30/60/90)
- [ ] **Inter-office credit statement** — given vs received, expected vs actual, reconciliation
- [ ] Expense analysis by head (travel, food, lodging, bills, …)
- [ ] Cost-per-call and cost-per-man-day
- [ ] **Billable value (ex-GST)** per call — man-day / man-month / invoice value

**Efficiency / People**
- [ ] **Inspector utilization** (man-days, % of working days) monthly + trend
- [ ] Attendance / leave summary (from voucher-derived present/leave)
- [ ] Inspector productivity (jobs, man-days, credit, cost, net)
- [ ] Work-type mix (day-based vs deputation vs sub-con)
- [ ] Certificate-expiry / compliance report

**Formats & mechanics to decide**: CSV + Excel (`.xls` via HTML table or
`.csv`, no library needed on MilesWeb) for data; **PDF/print** for official
statements (voucher, credit note, invoice summary) using the existing
print-page approach; every report **respects the user's office/business unit scope** and
the current dashboard **filters** (FY, month, office, business unit, inspector).


## 🚧 Expense / Inspector-Voucher module (IN PROGRESS — the profitability engine)

Modelled on the real "Statement of Travelling Expenses" the inspectors use.
Wide grid: one column per configurable expense head, TOTAL row at bottom + grand
total. Auto-fills from Jobs; inspector only enters hours + km + bills.

- [x] **P1 · Masters & codes** — `expense_heads` master (code, type PER_KM/BILL/
      ALLOWANCE, default rate, needs-receipt, column order — each is a voucher
      column); `travel_modes` master (Bike ₹6, Car ₹12, Own-car, Ola/Uber, Auto,
      Bus, Train, Air — per-km vs actual); `leave_type` + `day_code` lookups
      (CL/SL/PL/LWP/COMPOFF/ML + OFFICE/WFH/TRAINING/HOLIDAY/WEEKOFF). Seeded on
      fresh + upgrade; both masters on the Masters page; boot probes added.
- [x] **P2 · Inspector entitlements (Super-Admin only)** — DONE.
      `inspector_allowances` table + a "🔒 Allowances & rates" panel on the
      inspector edit page. Per inspector: tick which travel modes & expense heads
      they may claim, and set a **personal rate override** (blank = master
      default). Helpers `inspector_mode_rate()` / `inspector_head_allowed()` /
      `inspector_mode_allowed()` drive the voucher later. Verified: panel + save
      work for Super Admin; a normal inspector save does NOT wipe the
      entitlements; and a Branch Manager cannot see the panel nor POST to it
      (0 rows written). Boot probe added.
- [x] **P3 · Voucher auto-fill** — DONE. `vouchers` + `voucher_entries` tables;
      new **Vouchers** tab (inspectors see "My Voucher"). Open/create a voucher
      per inspector+month; **"Pull working days from jobs"** auto-fills one row per
      inspection day (date, **vendor display name** as site, **File No = contract number**,
      business unit, 8h, tagged `auto`) — idempotent, never duplicates. **Multiple rows per
      date** supported with a per-day **hours subtotal** + month total; hours
      **editable**; **Line No editable** (from Accounts), File No editable on work
      rows. Add non-inspection days (Office / Leave-with-code / Holiday / Week-off).
      Access: inspector = own; coordinator+ = any. Verified end-to-end; boot probe
      added. (KM/expense columns + totals arrive in P4.)
- [x] **P4 · Fast entry** — DONE. Wide grid: per row a **Mode** select + **KM**
      (auto-filled from vendor memory ↺, editable) → **Travel ₹ = km × the
      inspector's entitled rate**, plus one **bill column per entitled expense
      head** (only the heads/modes the Super Admin allowed appear). **Bottom TOTAL
      row** sums every column + **Grand Total**; JS recomputes live as you type,
      server recomputes authoritatively on **Save all**. `vendor_km_memory`
      remembers km per inspector+vendor. Verified: Bike ₹6 → 38/40/38 km, Food/
      Hotel bills, grand total ₹1,839, memory stored, Bus/Train hidden (not
      entitled). Boot probe added. *(One supporting file per voucher lands in P5.)*
- [x] **P5 · Output & workflow** — DONE. On the voucher: a **Summary — particulars**
      panel (Travel + each head → Grand Total, Less Advance, Less Office-incurred,
      **Balance to be paid/recovered**); a **single supporting file** per voucher
      (one upload backs all bills; streamed via `/voucher-file`); a **printable
      "Statement of Travelling Expenses"** (`/voucher-print`, standalone, browser
      print) matching the real format with the 3 signature blocks; and the
      **status workflow** DRAFT → SUBMITTED → APPROVED → PAID with **Checked /
      Approved / Authorized** captured, edit-locked once out of DRAFT, and Reopen.
      Verified: total ₹1,611, balance ₹1,011, file round-trip, print page, all
      transitions. `supporting_mime` migration + boot-safe.
- [x] **P6 · Attendance reconciliation** — DONE. New **Reconcile** tab. Upload the
      HR payroll Leave &amp; Attendance export **saved as CSV**; it is parsed **in
      memory only and never stored** (respects "don't copy the company doc").
      Auto-detects the header row + Employee Code / Present / Leave columns, matches
      by Employee Code to the inspector master, and compares **HR present/leave vs
      the app's voucher-derived present/leave** for the month — flagging OK /
      MISMATCH / In-HR-only / In-app-only with the differing cells highlighted.
      Verified: match=OK, HR4-vs-app2=MISMATCH, unknown code=In-HR-only.
- [x] **P7 · Profitability by contract number/Contract** — DONE. New **Profitability** tab
      (gated by new `data.profitability` perm — granted to Master Admin, Business
      Director, Business Unit Head, Branch Manager, **Operation Manager** [manager under the
      branch manager] and Finance; **not** Coordinator/Inspector). List of contract number
      numbers with Revenue / Expenses / Sub-con / Labour / **Margin ₹ + %**;
      detail page with stat row + **expense drill-down** (each line shows which
      inspector visited which vendor, hours, travel + bills + line total, with a
      **+ toggle** for the per-head breakdown) + invoice/job lines. Expenses roll
      voucher `row_total` (by boss_id) + job-closure expenses. Labour counted only
      when salary is visible (else "Contribution"). Verified: revenue 50k, expenses
      668, margin/%, drill-down. Super Admin can grant/revoke the perm per user.
- [x] **P8 · Contract/contract carry-forward** — DONE. On a contract profitability page,
      **Renew / change contract number (ARC/Open)** creates a new contract number
      linked to the old (`supersedes`/`superseded_by`), carries the **open jobs
      (and their voucher lines) forward** to the new number, closes the old, and
      shows the chain both ways ("continues from…", "renewed as…"). Closed/
      historical jobs stay on the old number. Verified: open job → new, closed job
      stays, chain linked.

## UI/UX rebuild v2 (staged, signed off) — DONE

Full app-wide rebuild on the new design system, done screen-by-screen with
sign-off between stages. Sidebar + slim top bar replace the old header; a
role-aware dashboard, an accountant money desk, and every core screen now share
the same cards / status pills / summary chips / clean tables — all driven by
the theme builder (no colour hardcoded, no CSS variable renamed).

- [x] **Sidebar shell** — grouped left rail (Operations / Money / Insights /
      Directory / Admin) with active highlighting + per-role visibility; slim
      top bar (office + FY chips, search, user); mobile drawer + scrim.
- [x] **Role-aware dashboard** — one template filled per role & scope (Director,
      Business Unit Head, Branch/Branch-App Manager, Manager/Asst, Coordinator, Accountant,
      Inspector); KPI tiles, money desk, expected-credit-by-office bars, job
      donut, quick actions, pending-scheduling — sections shown by permission,
      ordered per role.
- [x] **Accountant money desk** (`/invoicing`) — confirm-cards (Invoice pending /
      Awaiting payment / Overdue / Credit not received) over a worklist that
      writes the invoice/payment/credit fields already on jobs; office-scoped.
- [x] **Inspector phone view** — card-based My Jobs (To do / Completed, overdue
      flag) + a mobile bottom tab bar (Home / My Jobs / Voucher).
- [x] **Jobs & Calls lists** — summary chips + clean tables + status/money pills.
- [x] **Voucher grid** — status pill + headline KPI strip; mechanics (form=,
      live recalc, totals) untouched.
- [x] **Profitability list + detail** — KPI cards, margin pills, clean drill-down.
- [x] **Reports/Dashboards** — theme-variable colours; sticky filter re-aligned.

## UI/UX refresh — DONE

- [x] **Role-appropriate landing** — every user lands on the **home dashboard**
      after login (login → `/`); it is role-aware (managers get New Call / Jobs /
      Vouchers / Profitability / Dashboards / Masters; inspectors get My Jobs /
      My Voucher) with KPI cards + a live status chart, and all other screens are
      reached from there.
- [x] **Agency hiring cost on inspector** — when an engineer is engaged via an
      external agency, capture the **hiring agency** + **annual agency cost** on
      the inspector (salary-gated). It adds to that engineer's loaded labour
      (`salary_ctc + agency_cost`) so profitability/dashboards reflect the true
      cost. Verified: ₹240k agency cost on ₹600k CTC raised loaded labour
      correctly.
- [x] **Dashboards visual polish** — filter bar is now a sticky card; the four
      family sections (Operations / Financial / Utilization / People) have bold
      accent-underlined headers; chart panel sub-headers tidied.


- [x] **Professional UI refresh** — a design layer on top of `app.css` (sticky
      top bar with pill-hover nav, softer card radii + real elevation, gradient
      buttons with coloured shadow, soft form fields with focus rings, hover-lift
      stat/master cards, gentle table row-hover). Kept as a layer so it restyles
      **every existing screen** while the **theme builder** still drives all
      colours. `theme_style_tag()` now also emits `--field` and a luminance-aware
      `--shadow` (dark themes get proper dark fields/shadows).
- [x] **Landing / sign-in redesign** — `views/login_page.php`: a branded
      split-screen sign-in (value story + live chips on the left, clean sign-in
      card on the right, show/hide password), rendered standalone (no top-bar) and
      fully theme-driven (brand-gradient from `--brand`, accent glow from
      `--accent`). Verified: renders 200, theme (Forest) applies app-wide, no
      warnings.

## Per-office finance (overhead / contingency) — DONE

- [x] **Per-office Overhead % + Contingency %** — new **Overheads** screen
      (`/office-finance`). Each office sets its own Overhead % and Contingency %
      (Branch Application Manager edits their own office; global managers edit any
      office + the global default). Loaded labour = (CTC/12 × (1 + Overhead%)) /
      working days; Contingency % adds a buffer on (labour + expenses + sub-con).
      Both flow into `job_profit` and `boss_profit` → Profitability + Financial
      dashboards. Verified: OH 20% + contingency 5% raised labour 40k→44.4k, added
      ₹2,472 contingency, margin 55k→48.1k. (Replaces the flat 8% constant, which
      remains only as the ultimate fallback.)

## 🔮 Future phases (noted 2026-07, owner)

### Reports — Phase 2 (advanced, downloadable)
- [ ] Beyond the Phase-1 CSV exports (Jobs / Calls / Invoicing / Profitability /
      voucher), build the deeper analytics from the catalogue: **TAT report**
      (on-time vs late, avg, by office/business unit/inspector), **Office P&L** and **business unit
      P&L**, **inspector utilization & productivity**, **overdue aging (30/60/90)**
      on receivables, **inter-office credit statement** (given/received, expected
      vs actual reconciliation), and **true PDF** documents for invoices / credit
      notes / the signed voucher (currently print-to-PDF). Reuse the same
      scope + dashboard-filter pattern; add FY/Month/office/business unit pickers to each.

### CRM system (new module — before the Call in the chain)
- [ ] **CRM / quotation pipeline** — a front-end sales process that feeds the
      existing operations spine: **Lead / Enquiry → Quotation → Follow-up →
      Won/Lost → (on Won) auto-create a Call/contract number**. Scope to define with owner,
      but likely includes: enquiry capture (client, contact, business unit, scope, source),
      **quotation builder** (line items, rates, GST, validity, revisions,
      PDF/print + email to client), **follow-up reminders & status** (open /
      quoted / negotiating / won / lost with reason), a **sales pipeline board**
      + conversion dashboard, and a hand-off that turns a won quotation into a
      **contract number + Call** (carrying client, business unit, PO, agreed value) so nothing
      is re-keyed. Reuses clients/contacts, offices, business units, access control and the
      CSV/PDF export already built. Sits *before* Calls in the Enquiry → Quotation
      → Call → Job → Voucher → Profitability chain. **Note:** the git branch is
      already named `…quotation-management-workflow…`, but no CRM/quotation code
      exists yet — this item is that module.

## 💡 Separate product idea (future — not part of this app)

- [ ] **Freelancer ⇄ Agency connect platform** — a standalone application (its own
      product, separate from this inspection system) where **freelancers and
      agencies can find and connect with each other**: freelancers publish
      profiles/skills/availability/rates, agencies post requirements, and the two
      sides discover, message and engage each other (a two-sided marketplace).
      Could reuse concepts from our CV/hiring pipeline (candidate profiles, trade/
      skill masters, shortlisting) but is a NEW app for a broader audience — to be
      scoped separately later. Owner's idea, parked here so it isn't forgotten.

## Additional features (user will provide details / build later)

- [ ] **Inspector expenses linked strictly to the job done** — an inspector's
      expenses must attach only to the job they performed (fuller rules to be
      provided by the user).
- [x] **CV / hiring pipeline (deputation resourcing)** — DONE. New "Hiring" tab.
      Add a candidate CV (name, trade→skill, client, against-call, proposed site,
      business unit, designation, source [asset/freelancer/sub-con], experience, rate, CV
      link, CV-received date). Move through **CV received → Submitted to client →
      Shortlisted → Interview → Hold / Reject / Accept(=Hired) / Withdrawn**, each
      transition logged with a remark + who/when (full history on the candidate).
      On **Accept** you can tick "add to Inspectors" and the person is created as
      an inspector (carrying trade/skill/business unit/designation and the freelancer/
      sub-con type) ready for deputation-job allocation. Stage filter chips +
      counts on the list. Tables: `candidates`, `candidate_events`.

## Parked (agreed to do later)

- [ ] **Full organisation structure** — model **independent, peer offices**.
      IMPORTANT org model (confirmed by owner): commercially the **HO is Mumbai**,
      but **operationally there is NO head office** — every office is its own unit
      with **its own targets, operations and P&L**. Offices are peers; inter-office
      work is a **credit handoff between equals**, never HQ→branch. Build: users
      linked to their office; **multiple** Operation Managers / Coordinators per
      office; per-office targets; each office's dashboards default to *its own*
      numbers, with cross-office roll-ups only for roles whose scope spans offices
      (e.g. a commercial/Director view from Mumbai). Do **not** treat Ahmedabad (or
      any office) as a managing HQ — the old `is_ahmedabad` "managing office" idea
      is being unwound. This is a role/permission + org redesign for a dedicated
      pass; needs the owner's intended per-office roles before building.
- [x] **Multi-business unit cost distribution in dashboards** — DONE. The Financial
      dashboard's "By business unit" panel now shows Credit vs **distributed loaded cost**
      vs Net per business unit. Each active engineer's monthly loaded cost (CTC/12 + 8%
      overhead) is split equally across the business units they're tagged to, respecting
      business unit scope + the business unit/inspector filter. Salary-gated (`data.salary`).


- [ ] **Reminder cron jobs** — set up the two cPanel Cron entries (07:00 report-due,
      18:00 overdue-closure) pointing at `cron.php`. Deferred by user; steps are in
      `README-MilesWeb-PHP.md`.
- [x] **Office 365 automatic email sending (SMTP)** — DONE. A **Settings → Email
      (Office 365 SMTP)** section takes host / port / username / app-password /
      from. When filled, assignment/closure/forward/reminder emails **auto-send**
      via a built-in SMTP client (STARTTLS + AUTH LOGIN, no library — works on
      MilesWeb). Left blank = current behaviour (logged + Open-in-Outlook). Safe:
      SMTP failures are caught and logged, never crash. Password blank-keeps the
      stored one. *(User just needs to enter their mailbox + app password.)*
- [ ] **License server + per-user billing** — support **both** deployment models
      (client's own server **and** our hosted server) for different industries,
      with remote seat-limit enforcement, subscription expiry, module toggles, and
      a control panel we run from our side. Ties to Razorpay/Stripe for the
      one-time setup fee + monthly per-user recurring. (Roadmap artifact already
      shared.)

## Module A/B sub-items — DONE (this session)

- [x] PO/line-item selection on a call + qty tracking + near-completion alert.
- [x] Project deputation → client sites dropdown (shown only for deputation).
- [x] Executing-branch confirmation status on the call.
- [x] PO line items: manpower/site/trade→subcategory + GST/Tax/Total + rollup;
      activity per line respecting the PO's business unit; multi-business unit on the PO.
- [x] Projects tab lists the partner's calls.
- [x] City light auto-correct; Type-of-inspection 'Other' free text on the call.

## Modules C / D / E — not yet started

- [ ] C: Logo upload + editable theme (kept legible); per-business unit expense headings.
- [ ] D: inspection lifecycle/status flow; designations master (Inspector,
      Sr. Inspector, Sr. Executive…); back-office staff with costing (CTC,
      allowances).
- [ ] E: add your own dropdown/text box to any master form (custom fields on
      masters).

## Dashboards — refinements to follow

- [x] **Configurable expense headings** — DONE (global). The 5 base headings can
      now be **renamed** via the `expense_heading` list, and **any extra headings**
      you add there appear automatically on the job-close form, flow into the job
      total/profit, the job-detail expense table, and the Financial dashboard's
      "Expenses by heading" breakdown. Extras are stored per expense row as JSON
      (`expenses.extra`), so nothing about the fixed 5 columns changed — fully
      backward-compatible. *Remaining refinement:* scope headings **per business unit**
      (make `expense_heading` a child list under business unit) — small follow-up.
- [ ] **Persona landing pages** — today all four dashboard families live on one
      /reports page with each section gated by permission (so each person sees
      only their allowed sections). A future refinement gives each role a
      tailored default landing layout (Director = office comparison, Business Unit Head =
      business unit-across-offices, etc.).

## Nice-to-have / minor

- [ ] Generic master "checkbox" fields default to ticked on new records (fine for
      "Active" on sub-cons, not ideal for "is Ahmedabad" on a new office). Low impact.

## Done recently (for reference)

- [x] Configurable master lists + dependent (hierarchical) lists + custom fields.
- [x] New Call: quick-add client/vendor/office/product/activity, executing-branch
      forwarding with mandatory credit + coordinator/manager email, lead times.
- [x] Readable error page instead of blank 500.
- [x] Full operations system (Calls, Jobs, Closure, Expenses, SubCon, Attendance,
      Holidays, Comp-off, Credit, Dashboards) with 4 roles + salary security.

## Consistency pass — headings, dropdowns, terminology (done)

Audited first (no code), reported, then built to the agreed decisions.

- **Terminology engine** `lib/terms.php` + `/terminology`: 27 business nouns,
  each renameable once and followed everywhere. Shipped vocabulary: Client,
  Quote, Inspection Call, Deputation, Report, Inspection Engineer + User, IBO,
  business unit, contract Number, Man-day; Vendor / Manufacturer / Supplier / Sub-vendor all
  kept as distinct parties.
- **One heading standard** across all 55 screens; sidebar label = the heading it
  opens; emoji and trailing spaces removed from card titles.
- **Screens merged**: Approval rules (was 2), Document templates (was 2),
  Masters (was 2), Users vs Roles & permissions.
- **Every dropdown editable**: ~60 lists / ~500 values under Masters, grouped by
  module with a search box. Constants remain as fallbacks.
- **One list per concept**: work type (Sales + Ops), charge unit (quote + rate +
  PO), deliverables (from the report-types register), ISO/IEC 17020 result
  wording, and only two rejection words (Rejected / Sent back).
- **Settings** gained: hours cap, default weekly working days, employee-code
  prefix, currency symbol, date format, required source documents, high-risk
  audit actions.
- **De-branded**: no third-party agency name anywhere in code, seeds, themes,
  e-mails or placeholders.

Data-level upgrades (renamed lists, rewritten codes, dropped lists) cannot be
detected by a missing table or column, so the boot probe asserts them and each
assertion self-cancels once applied. All were tested against a simulated older
database as well as a fresh one.

## Quote / CRM pack (done)

- Client picked ⇒ free-text name disabled; contact person / e-mail / mobile
  auto-filled from the client's primary contact, still editable.
- Sites are real addresses, many per quote; every line item names its site.
  The client's addresses on file are offered as one-click additions.
- Executing office: a primary office plus any others, and a per-line office.
- Types of inspection = the call's master, narrowed to that client's types.
- Payment terms, quote origin and site type are masters (17 payment terms).
- Product category unifies spelling automatically (exact → plural → edit
  distance), office-scoped, cumulative for cross-office access.
- Editable grids readable: minimum width, room per cell, full-height controls.
- Approvals show who they are waiting on by name; reject needs a comment;
  rejected shows as Rejected by whom, when and why.
- Accepted / lost quotes lock; re-edit is a request the Super Admin grants for
  N hours, after which it re-locks itself.
- Multiple attachments per quote, typed (our quotation, attachment, client doc,
  PO, inspection doc); PO number and date captured; shared files follow the
  work to the job so the engineer sees them.
- External quotes (client / tender portal) get their own registration screen.
- Terms & conditions default in Settings, editable per quote.
- Stored signature of the named signatory stamped on the quote PDF.
- Change log shows field-level differences; accepted quotes offer a final copy.
- Register export with submission / approval / sent / acceptance dates, contact
  details, contract and PO numbers, sites and every follow-up.
- Follow-ups editable (date, status, done-on, note) and can be added by hand.

## Calls pack (done)

New call
- Client → quotation → contract number; the quote's line items are listed and
  the call can be tied to one. business unit, activity, type of inspection, product,
  billable value and basis all inherit from the quote (blanks only, on edit).
- Up to 5 visit dates, or a weekday pattern to an end date that expands into
  real dates — all editable afterwards.
- Cross-office credit explained in the offices' own names, on the form and in
  the refusal. Every office both contracts and executes.
- Clickable shared folder / drive link, carried to the deputation.
- Region shown only to business unit heads and the Business Director.

Call register
- Executing office, activity, credit to give, coordinator, engineer, received /
  forwarded / allocated / required / scheduled dates, three lead times, delay
  pill, late-row tint, days-waiting when unallocated. Export matches.

Allocate
- Everything inherits from the call, shown in a "from the call" strip.
- Inspection dates up to 20 (replaces random date 1/2/3; old values folded in).
- Own employee vs not-ours → freelancer / sub-contractor, engineer list filtered.
- Credit direction defaults to Given for cross-office.
- Filters: engineer, office, month, date range, nobody-allocated.

## Inspector availability (done)

- Six cards: total, available, on job, on leave, in office, training/other.
- "Free to allocate" = free today AND tomorrow only.
- Date check: pick a date + days needed -> who is free, for how long, and what
  they are on next; whole-period cover highlighted; 45-day horizon.
- Filters: name, office, business unit, status; plus a month grid (free / on job / leave /
  other per day, with a free-day count).
- Look-ahead reads open deputations (scheduled day, start-end period, and the
  deputation's visit dates) plus manual day statuses - the same sources as the
  day view, so the two can never disagree.
