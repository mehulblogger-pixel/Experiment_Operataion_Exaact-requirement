# Exaact — Test Playbook (exhaustive edition)

A module-by-module acceptance protocol for the whole application: every module and
sub-module with its happy path, its **expected figures**, and the boundary/negative
cases that must fail gracefully. Tick each `[ ]` as you go.

> **Before you start** — Load a clean dataset from **Super Admin → Data at a glance →
> “Load demo data”**. Figures assume that seed; because it uses **relative dates**, all
> date-derived counts (overdue, renewals, ageing) are measured from the day you load it.
> Sign in as `admin` (Master), `director` (ED), `coord.mum` (Coordinator) or
> `insp.ravi` (Inspector), password `demo12345`. `file:line` refs point developers at the
> exact gate.
>
> A visually formatted, printable version with a sign-off box on every case is published
> as the **“Exaact Test Playbook”** artifact.

Legend: **EXP** = expected result · **EDGE** = boundary/negative case.

---

## Part 0 · Global gates (test once, apply everywhere)

- [ ] **TC-0.1 CSRF on every form** `index.php:821` — submit a form with an expired/tampered
  `_csrf`. **EXP:** rejected, form re-renders with typed values preserved, “session had timed
  out” flash, `CSRF_REJECTED` logged. Login has its own check `index.php:627`.
- [ ] **TC-0.2 Licence read-only** `licencekey.php:335` — in READONLY/INVALID/expired-trial, all
  writes refused **except** the allow-list (licence, billing, logout, login, change-password,
  my-signature, verify). **EXP:** every read/export/PDF still works.
- [ ] **TC-0.3 Upload guard** `index.php:847` — **EDGE:** rename a `.php` to `.jpg` (refused);
  DOCX template without `PK` magic bytes rejected `crm.php:2595`.

## Part A · Masters & the lookup engine

- [ ] **TC-A1 Create list & add values** `lookups.php:458` — value appears on its form in sort
  order; removing removes it; Masters is admin-only.
  - EDGE: blank list name → “Give the list a name.”; two names slugifying to one key → “already
    exists.”; blank value label → “Enter a value label.”; **dup value under same parent** → blocked
    (case-insensitive), **same label under different parent** → allowed; child with no parent → “Pick
    which {Parent}…”; delete a built-in list as non-master → refused; after delete, dropdowns fall
    back to shipped constants.
- [ ] **TC-A2 Cascading lists & custom fields** — ≥3-level chain filters children, re-filters on
  change, re-opens a stored leaf; select/dependent custom field needs a master list; key & type
  immutable on edit.
  - EDGE: a **required** custom field is enforced client-side only — confirm server behaviour
    `lookups.php:434`; cleared lists with `masters_seeded` stay empty on reload, forms still work.
- [ ] **TC-A3 New masters exist** — Costing head (18 defaults), Sourcing model (cost), Drop point
  are present & editable; adding a head appears on the costing add-role form.

## Part B · Settings, FY & terminology

- [ ] **TC-B1 Company & FY boundary** — FY re-scopes registers; a record dated on FY first/last
  day lands in the right FY.
- [ ] **TC-B2 Terminology** `terms.php` — rename a noun re-labels UI, not routes/data; only diffs
  stored.
  - EDGE: applying an **industry pack replaces** overrides for covered terms (not merge); hand-edit
    one word → pack no longer “in force”; acronyms (IBO/SBU/NCR/NDT/GST/PWHT) stay upper-case.
- [ ] **TC-B3 Money defaults** — new costing pre-filled 8 / 0.5 / 0 / 0.5 / 5; Field seat 499;
  Portal 99 (10 free); Full 1,799. Changing a default affects only **future** sheets.

## Part C · Directory — clients & vendors

- [ ] **TC-C1 Client, quick-add, dedupe, import** — client with sites/contacts/terms saves;
  “＋ Add new” returns with the new client selected; address-as-site de-duplicates; dedupe screen is
  read-only; import validates malformed rows.
  - EDGE: accepting a quote for an off-master client **auto-creates a partial client**
    `crm.php:2051` — intended.

## Part D · CRM — leads & opportunities

- [ ] **TC-D1 Opportunity lifecycle & guards** `opportunities.php` — qualify-from-lead is
  idempotent; stage history records who/when.
  - EDGE: blank name / no customer → prompts; **move to same stage** refused (guards `stage_since`
    reset) `:380`; LOST needs a reason (before the gate); threshold move is **held** (deal doesn’t
    move); pipeline change on closed deal / lead-pipeline / no-open-stage all refused; inactive owner
    refused; customer change after an order refused; non-client partner → “not on the master.”
- [ ] **TC-D2 Raise the order** `opportunities.php:595` — **EDGE:** non-WON → “Only a won
  opportunity becomes an order.”; no partner / already raised → prompts; order carries the **ex-GST
  subtotal** (double-GST guard) `:646`; sales-only install hides orders.

## Part E · CRM — quotations

- [ ] **TC-E0 Build & maths** — Subtotal = Σ(qty×rate); GST 18%; Total consistent on register &
  PDF; client autofills contact/terms; line office defaults to client base office.
  - EDGE: duplicate quote number blocked; 0-line quote can’t be submitted.
- [ ] **TC-E1 Approval, reject, cascade-retract, lock** `crm.php:1451` — “pending with” names the
  person; reject **requires a comment**, shows REJECTED, re-opens submit.
  - EDGE: not-an-approver blocked; over-threshold routed up; **retract level 1 of a 3-level chain →
    2–3 reset** `:1503`; **SENT locks for everyone incl. Master**, only a revision moves forward;
    closed quote needs a Super-Admin time-boxed re-edit, re-locks.
- [ ] **TC-E2 Revision copies everything; contract gate** — revision carries all columns except the
  skip-list (sites, locations, inspection types, offices, terms), location ids remapped; contract
  number rejects duplicates; order needs **manager endorse + BM approve** to open `:1522`.
  - EDGE: send-email failure still SENT + follow-ups (skipped on LOST/ACCEPTED); cross-module sync
    never blocks the action.

## Part F · Project costing (Mundra sheet)

- [ ] **TC-F1 Manager line** — Direct 1,41,083 · Loaded 1,63,133 (10.5% of the ₹2,10,000 rate) ·
  Proposed 2,20,500 · Revenue ×24 = **52,92,000** · margin ≈ 26%.
  - EDGE: switch loadings to **Direct cost** (numbers change); target margin derives the rate; **margin
    = 100** and **margin+loadings ≥ 100** (RATE base) → no divide-by-zero `projcosting.php:172-179`.
- [ ] **TC-F2 Full 8-role team** — **Project revenue ₹2,64,09,600**, margin ≈ 26% (= seed
  `PC-DEMO-01`); table shows only used head columns; totals sum each head.
- [ ] **TC-F3 Lock, quote, requirement, attach, print** — submit locks (edits refused); approve
  stamps who/ref/when; **Create quotation** → per-role lines, subtotal ₹2,64,09,600, GST 18% ⇒ total
  **₹3,11,63,328**; **→ Requirement** carries billing 2,20,500 / loaded 1,63,133 / wage-reimburse
  split; Print/PDF renders; Attach both ways (only unlinked offered); `PC-DEMO-02` daily ₹14,08,050,
  `PC-DEMO-03` fixed ₹3,37,886.

## Part G · Operations — work orders to closure

- [ ] **TC-G1 Raise, allocate, schedule** — lead-time/delay computed; job emails inspectors, hits
  board + My jobs; KPIs update. Seed: open calls **53**, open jobs **53** (13 overdue), closed
  **103**.
  - EDGE: allocate against a closed call refused; weekend/holiday rolls the date + override;
    double-book across overlapping open jobs flagged.
- [ ] **TC-G2 The closure gates** `ops.php:4956` — **EDGE (run each):** double-submit/offline replay
  → “already closed… nothing recorded twice”; close with no report (unless NOREPORT) blocked;
  chargeable head without a bill blocked; **attendance missing** blocks inspector, manager may approve
  **with a recorded reason** (dents rating) `:4996`; past-deadline self-close blocked; per-visit close
  idempotent; comp-off no duplicate on replay.
- [ ] **TC-G3 Contract exhaustion/expiry** `contracts.php:345` — **EDGE:** expired → blocked, only
  **Super-Admin** override (dates before quantity); exhausted → **Branch-Manager** endorsement;
  lump-sum has no quantity; override consumed exactly once.
- [ ] **TC-G4 Vouchers/ratings/timesheet/recurring/invoice** — inspector sees own vouchers (34
  seeded); invoice only after close (auto-filled from quote); recurring spawns work; expired/exhausted
  don’t.

## Part H · Recruitment

- [ ] **TC-H1 Cost build-up — 4 models** `recruit.php:110` — Manpower agency = 50,000 + 10,000
  (20%) + 4,800 (8% of 60,000) + 5,000 = **₹69,800**; Own payroll (statutory, no fee); Sub-contract
  (all-in rate); Freelancer (flat). 6×6 @ 1,07,400 → revenue **₹38,66,400**, cost **₹25,84,800**,
  profit **₹12,81,600**.
  - EDGE: qty floored at 1; MANDAY×22; FIXED = qty×rate; try zero/blank duration and each basis.
- [ ] **TC-H2 Dedupe / submission guard / drop points** `recruit.php:166` — mobile conf 96 / email
  94 / name 72, top 6, read-only; re-submit same person same client flagged, different client allowed;
  ACCEPTED_NO_JOIN is its own bucket, subtracted from Dropped; drop point ≠ drop reason.
- [ ] **TC-H3 Command Centre** `recruit_cc.php` — all panels populate (≈55 candidates, 8 stages, 4
  sources, 6 depts); filters FY/Month/Dept/Source/Manager re-scope everything; out-of-range FY snaps
  to first; conversion denominators floor at 1; read-only.

## Part I · Quality & accreditation

- [ ] **TC-I1 Register smoke test & pack gate** — every register saves & lists; badges update; a
  complaint raises a multi-action/owner/date CAPA across branches.
  - EDGE: with the **accredited pack off**, equipment/competence/impartiality/NCR/CAPA/audits tiles
    disappear; on, they return.
- [ ] **TC-I2 The two blocking registers** — **EDGE:** a new instrument is unusable until a
  calibration cert is on file `equipment.php:290`; an inspector with an **uncleared impartiality
  threat** is refused work `impartiality.php:166`.
- [ ] **TC-I3 Portals** — portal user accepts/rejects a report, raises a complaint, uploads
  evidence; per-user perms limit visibility.
  - EDGE: share a draft/vendor-less report → refused `cvp.php:904`.

## Part J · Reporting (IDEMS)

- [ ] **TC-J1 Create → approve → issue, every gate** `idems.php:3660` — format + QAP auto-fetch;
  approver map with temp-cover fallback; on issue: signatures frozen, content hash sealed for
  `/verify`, schema + Word template frozen, IRN + immutable timestamp set.
  - EDGE: chain incomplete → non-master blocked; **critical QA finding** → blocked, master needs an
    override reason logged to audit `:3677`; **out-of-calibration instrument** → hard block,
    non-overridable `:3700`; unauthorised signer → issues but raises a MAJOR NCR; inspector with no
    approver → can’t submit (advisor flags it); revise twice → “A revision already exists”; edit a
    template/signature after issue → issued PDF & verify hash unchanged.

## Part K · Money

- [ ] **TC-K1 Invoicing/receipts/receivables** — invoice only after close (override); drafts
  visible; receipts/credit notes reconcile. Seed: pending 51, awaiting 51, overdue 26,
  credit-not-received 12, unbilled ₹9,46,000, outstanding ₹36,64,000.
- [ ] **TC-K2 Cost allocation & frozen months** `costing.php` — salary lands on days-worked BU;
  idle by office idle-basis; seed run ₹3,85,000 (AMD) / ₹2,20,000 (MUM).
  - EDGE: **freeze month, change allocation, recompute → frozen unchanged**; freeze before calculate
    blocked; reopen to correct; try each idle-basis.
- [ ] **TC-K3 Seat billing & Books** `billing.php` — **EDGE:** forged Razorpay callback (bad HMAC)
  refused, seats only after verification; self-hosted enforced install refuses buying seats (key
  governs); pay-early stacks time; Books not connected → clean no-op.

## Part L · Super Admin & licensing

- [ ] **TC-L1 Control panel & seat-class breakdown** — 40 field + 10 full on Professional →
  **₹37,950/mo role-based vs ₹89,950 flat**, saving ₹52,000 (58%); who-is-in-each-class table; field
  roles/prices/free-bundle configurable.
- [ ] **TC-L2 Seat blocks & tier issue** `licencekey.php:301` — **EDGE:** field pool full → field-cap
  message; full pool full → full-pool message; flat key → single pool; seats=0 unlimited; OPEN/TRIAL
  never blocks; blank customer prompt; tier overrides module checkboxes; core force-added; field ≤
  total; unlimited reissue-delta refused; `/vendor` & `/issue-licence` Master-only + refused in a
  tenant.
- [ ] **TC-L3 Licence state machine — 4 rules** `licencekey.php:190` — **EDGE:** OPEN→TRIAL(14d from
  first boot)→VALID→GRACE(14d)→READONLY; test exp / exp+grace / +1; (1) expired = read-only never
  locked out; (2) no enforcement until on; (3) trial from first boot; (4) licence screen always
  accepts a key; **forged key → INVALID, zero modules, data readable**; deleting a key → read-only not
  unlimited; Razorpay grant overlays.

## Part M · Analytics, MIS & profitability

- [ ] **TC-M1 Scope & salary enforcement** — branch user sees a correct smaller slice; director sees
  all-office totals (seed KPIs = all-office); salary-cleared roles see costing/profit columns; KPI
  governance/vetting; advisor lists money-attached actions (incl. “no approver”).
  - EDGE: an over-filtered scope can look **empty** — confirm it’s scope, not missing data.

## Part N · Navigation, security & offline

- [ ] **TC-N1 Command palette & area access** `navindex.php:113` — ⌘/Ctrl-K lists every allowed
  screen, de-duplicated, grouped, with ★/recents; never offers a screen your role can’t open; record
  search is a separate box; an all-gated area is hidden.
- [ ] **TC-N2 Scope clauses & null-office** `access.php:459` — office-scoped user sees own **plus**
  null-office rows (an unassigned NCR mustn’t vanish); a call with no office lands in Ahmedabad scope.
- [ ] **TC-N3 2FA / offline / setup** `index.php:629` — **EDGE:** correct TOTP passes; **>5 min at the
  code step** discards the sign-in; recovery code one-shot (count decrements); wrong code logs
  LOGIN_FAILED; **offline replay of a queued POST → no duplicate**; setup wizard redirects master to
  `/setup` but lets logout/change-password through.

## Regression shortlist (13 most likely to break)

- [ ] 1 Re-post job-close / offline replay → no double expenses
- [ ] 2 Edit a SENT quote as Master → refused, revision offered
- [ ] 3 Retract level 1 of a 3-level chain → 2–3 reset
- [ ] 4 Opportunity “Move” without changing stage → refused
- [ ] 5 Costing target-margin = 100 → no divide-by-zero
- [ ] 6 Issue report with critical QA finding → block / master override logged
- [ ] 7 Issue report on out-of-calibration instrument → hard block
- [ ] 8 Fill field-seat pool, add inspector → field-cap block
- [ ] 9 Forge a licence key → INVALID, zero modules, data readable
- [ ] 10 Freeze a cost-run month, change allocation, recompute → frozen unchanged
- [ ] 11 Book against expired (Super-Admin override) / exhausted (BM endorse)
- [ ] 12 Duplicate lookup value same parent (block) vs different parent (allow)
- [ ] 13 2FA: wait >5 min at code step → sign-in discarded

---

_Coverage: Part 0 + A–N exercise every module and sub-module with expected figures and boundary/
negative cases. Figures assume the shipped demo seed; re-seed between runs. Additive; runs on MySQL
(production) and SQLite (dev)._
