# D10 — Roadmap

Everything the audit found, in the order I would do it, with the reasoning.

**Read `08-GAP-REGISTER.md` for the findings themselves and
`09-UAT-PLAN.md` for how to test them.** This is about sequence.

---

## 1. The honest summary first

**EXAACT is a better product than its documentation suggests.**

The audit found a system built by people who understand the business: an
inspection body's quality machinery done properly, GST invoice numbering that
knows a series may not have gaps, TDS as a first-class receipt field, geofenced
site evidence, a 33-term vocabulary with definitions, three external audiences
separated at the router, and — the thing that persuaded me most — **screens
whose job is to show you where the system disagrees with itself.**

The problems are not of competence. They cluster in three places:

| | |
|---|---|
| **Documentation lags the code** | Quality has no lifecycle sections at all; 44 of 63 status vocabularies are undocumented; backup, restore and impersonation have no permission rules |
| **Four forms were missed** | They create numbered ghost records, while three sibling forms refuse beautifully. The knowledge is in the codebase; four screens did not get it |
| **The external surface is unverified** | Three portals, 70 screens, never audited — including by me, until I found the gap |

**And one finding is about this audit, not the product:** my route inventory was
parsed from one file, so the universe was ~160 routes short and the three
portals were never in it (`00a-ERRATUM-COVERAGE.md`, GAP-012).

---

## 2. Sequence — and why this order

Four principles decided it:

1. **Data belonging to the wrong person is unrecoverable.** It goes first.
2. **A one-way door needs testing before it is used in anger**, not after.
3. **A number nobody trusts stops a business making decisions** — but a wrong
   number can be corrected; leaked data cannot.
4. **Documentation last of the serious work, first of the cheap work** — it
   blocks the accreditation assessment but not the business.

---

## Stage 0 — This week, before anything else

| # | Do | Why | Effort |
|---|---|---|---|
| 0.1 | **Confirm what is deployed.** Upload `deploy-check.php`, open `/deploy-check.php` | `APP_VERSION` has read `2026.10.1` all month, so the footer cannot tell you. Earlier UAT runs tested an old build | minutes — **Owner reports 2026.10.1 — that number never changes; the check is `/deploy-check.php`, guide Part 3** |
| 0.2 | **Answer the eight open questions** in §6 | Six findings cannot be fixed without them, and three are one-line answers | an hour |
| 0.3 | **Get a non-production workspace** (open question **Q-D**) | Every test in `09-UAT-PLAN.md` needs one, and T-5 explicitly involves restoring a backup | — — **Owner asked what is needed 19 Oct — listed in `14-LIVE-CHECKS-GUIDE.md` Part 7; a test-copy no-e-mail switch is built** |

---

## Stage 1 — P0: can one customer see another's data?

**Nothing else is started until this is answered.** These are the only findings
where being wrong is unrecoverable.

| # | Work | Test | Finding |
|---|---|---|---|
| 1.1 | **Audit the three external portals** — client (33 routes), vendor (11), professional (26): inventory, probe per external role, test every form | T-1 | **GAP-012** — **DONE 18 Oct: security pass on all 77 portal routes, `11-EXTERNAL-PORTAL-AUDIT.md`; a screen-quality pass remains** |
| 1.2 | **Party isolation:** can client B fetch client A's report, invoice or requirement **by id**? Vendor B another vendor's assessment? A professional another's documents? | T-1 | **GAP-012** — **DONE 18 Oct, same document §2** |
| 1.3 | **Tenant isolation** from outside | T-2 | — — **DONE 19 Oct: a portal session from one company WAS honoured by another (P0, GAP-049); all four identities now bound to their company** |
| 1.4 | **Verify the privacy-safe talent cards** — inspect the HTML and JSON, not the rendering | T-3 | matrix fn. 20 |
| 1.5 | **Restore a *foreign* backup.** If a backup from another workspace is accepted, that is a P0 | T-5 | **A-F3** — **DONE 19 Oct: a foreign backup WAS accepted (P0, GAP-048); now refused unless a master confirms by name** |

**Why first:** 1.1–1.4 cover the surface this audit never touched, and it is the
surface where a mistake is a breach rather than a bug. 1.5 is here because a
restore is the one action that can import another customer's data wholesale.

**Gate:** do not proceed while any of these is open.

---

## Stage 2 — P1: one-way doors and ghost records

| # | Work | Test | Finding |
|---|---|---|---|
| 2.1 | **Fix the four forms that create numbered ghost records** — `/call-new`, `/candidate-new`, `/incident-new`, `/endorsement-new`. **Server-side**: three already carry `required` attributes the server ignores. Copy the pattern from `/internal-audit-new` | T-10 | **S4-1** |
| 2.2 | **Test `/quote-new`** the same way — 96 fields, 1 required, never tested, and a quote goes to a customer | T-11 | **S4-2** — **TESTED 19 Oct: figures validated (GAP-047); an empty submit creates a numbered ₹0 DRAFT — Sales audit SA-26, fix in Sales roadmap 2.2** |
| 2.3 | **Establish the delete cascade:** work order → job → report → billable event → voucher. Refuse the delete or remove the dependents; never orphan | T-4 | **O-F9** |
| 2.4 | **Backup/restore authority:** gate it, validate the archive, log every restore, add it to the permission matrix | T-5 | **A-F3** — **DONE 19 Oct: gated (settings.manage), file validated, every restore logged, matrix row 16a (GAP-048)** |
| 2.5 | **Impersonation:** confirm every impersonated action is attributable to the real person, log it, make it visible to the customer, document it in the matrix | T-9 | **A-F5** |
| 2.6 | **Merge:** make `reason` required, confirm no record points at the discarded id, and say on the screen if it cannot be undone | T-6 | **D5-3/D-F3** |
| 2.7 | **Mid-year numbering change** — protect the existing series or refuse the change | T-18 | **A-F7** |

**Why here:** 2.1 and 2.2 are cheap and visible — they close the thing most
likely to embarrass you in front of an assessor. 2.3–2.7 are the one-way doors;
each needs one careful test and, mostly, a gate or a log rather than new
features.

---

## Stage 3 — P1: controls that may not control anything

A control that is recorded but not enforced is worse than no control, because
people rely on it.

| # | Work | Test | Finding |
|---|---|---|---|
| 3.1 | **Client hold / blacklist must block ordering** | T-13 | **D-F1** |
| 3.2 | **Quarantined instrument:** block new allocation, and make the reports it produced findable — this is a recall question | T-14 | **Q-F9 / E-7** |
| 3.3 | **Competence enforcement:** when switched on, refuse the allocation and say why | T-15 | **Q-F6** |
| 3.4 | **Pre-order checklist** with `require_all` must block quote approval; decide whether a recorded waiver is needed | T-16 | **S-F / E-8** |
| 3.5 | **Answer Q-B1/Q-B2:** who may *raise* a nonconformity (today an inspector cannot), and should the five roles who see a corrective action see the finding behind it | — | **GAP-004** — **ANSWERED 19 Oct (Q-B1): coordinator level and above record; built** |
| 3.7 | **Sales commercial controls** — the approval, acceptance and contract bypasses and the billing defaults found by the Sales audit. Its own staged plan: `sales/SALES-REMEDIATION-ROADMAP.md` | `sales/SALES-END-TO-END-TEST-MATRIX.md` | **GAP-050** — **Stage 1 built 20 Oct** after the owner's answers; Stage 2 next |
| 3.6 | **Make `test_m7_*` and `test_m8_*` pass on their own.** They pass only after other tests have run, so they could stay green while the rule they guard breaks | the two files, run alone | **GAP-049 note** |

---

## Stage 4 — P1: make the numbers trustworthy

**This is the largest single piece of work in the roadmap**, and it cannot start
until a database is populated.

| # | Work | Test | Finding |
|---|---|---|---|
| 4.1 | **Populate a realistic month** — work orders, jobs, reports, billable events, invoices, receipts, office costs, cross-office work | T-17 | prerequisite — **DONE in test data 19 Oct** (one known month, `evidence/kpi-reconcile-probe.php`); **live data still to do — guide Part 4** |
| 4.2 | **Reconcile one month and one business unit by hand** against `/mis`, `/sbu-pl`, `/call-profit`, `/profitability`, `/reports` and `/analytics`. **Any two that disagree is a finding** | T-17 | — — **DONE 19 Oct, in test data: four disagreements found and fixed (GAP-040…043)** |
| 4.3 | **Settle the two revenue sources.** The legacy figure stamped on the job vs the invoice ledger — four files sum the legacy one, **including `tapi.php`**. Decide which each screen reads and **say so on the screen** | T-17 | **D7-2** — **DONE 19 Oct: one source per reader mode across every screen (GAP-040)** |
| 4.4 | **Give margin a named function.** The first figure a director reads has none | — | **D7-1** — **DONE 19 Oct: `mis_margin_pct()`** |
| 4.5 | **One utilisation denominator.** Check all three functions against the same `/work-norms` | — | **D7-5** — **DONE 19 Oct: shared denominators proven; `/reports` corrected (GAP-042)** |
| 4.6 | **Surface the two orphaned disagreement counters** — `billable_mismatch_count`, `calls_status_disagreement_count` — on tile badges, as `revrecon_count` and `costrecon_count` already are | — | **D7-3** |
| 4.7 | **Show the Books outbox.** A failed push is currently invisible in Money; Sales shows a **Failed** tab. Check whether `/integrations` already covers it | T-34 | **Y-F1** |

**Why after Stage 3:** a wrong number is correctable and nobody is harmed in the
meantime. An unenforced credit control loses money while you wait.

---

## Stage 5 — P1/P2: the documentation that blocks an assessment

Cheap, and it is what an assessor asks for.

| # | Work | Why | Effort |
|---|---|---|---|
| 5.1 | **Write the Quality lifecycle sections** — nonconformity, corrective action, departure, internal audit, equipment, samples, hold points, impartiality | **Zero mentions** of NCR or corrective action in `docs/03-object-lifecycles.md`. The CLAUDE.md control cannot bite where there is no section | 1–2 days |
| 5.2 | **Write the Invoice lifecycle section** — DRAFT → ISSUED → PART_PAID → PAID → CANCELLED | Mentioned 17 times, no section. It is the record the money turns on | hours |
| 5.3 | **Write the Deputation lifecycle** — 31 states across four vocabularies, 13 tables, two mentions, no section. **The largest undocumented workflow in the product** | — | 1 day |
| 5.4 | **Document backup, restore, impersonation and the trim right** in the permission matrix | Three of them are authority over everything | hours |
| 5.5 | **Add NCR/CAPA closing authority** — *done 2026-10-18* with the Quality Head and Technical Head roles | ✓ | done |
| 5.6 | **Publish the glossary** from `05-BUSINESS-GLOSSARY.md` to the business | It already exists in `lib/terms.php` and nobody outside the code can see it | hours |
| 5.7 | **Extend the ambiguity rule** beyond Recruitment: "report" and "document" need the qualifier policy that "requirement" already has | — | hours |

---

## Stage 6 — P2: the audit's own unfinished business

| # | Work | Test |
|---|---|---|
| 6.1 | **Re-run the inventory over all ≈725 routes** — screens, fields, module gates, reachability. Ideally make the application **enumerate its own routes** so it can never drift again | T-20 | — **DONE 19 Oct 2026**: the public (pre-login) surface is `13-PUBLIC-SURFACE.md` (GAP-030…033); with `11-` and `12-` this closes the coverage erratum
| 6.2 | **Inventory the record screens** — `/job?id=` first; it is the highest-value remaining measurement in the audit | T-19 |
| 6.3 | **Document the analytics/TAPI hub** and audit its metric definitions against the domain functions they wrap | — |
| 6.4 | **Audit the sibling deployables** — `licence-server/` (Razorpay webhook, issues licences), `mgh_books_receiver/`, `mgh-hire/`, `sop/` | — | — **DONE 19 Oct 2026**: see `12-SIBLING-DEPLOYABLES.md` (GAP-025…029); `mgh-hire` surveyed, full pass waits on Q-C
| 6.5 | **Behaviour at volume** — 10,000 work orders, 50,000 jobs | T-21 |
| 6.6 | **390px and accessibility**, directly rather than by proxy | T-22 |

---

## Stage 7 — P2/P3: usability and tidying

| # | Work | Finding |
|---|---|---|
| 7.1 | **Make "Find a screen…" find screens.** The visible box filters 15 rail items; the 167-screen index is behind ⌘K. **This is the mechanism behind repeated reports that a feature "does not exist"** | **GAP-001** |
| 7.2 | **Give the twelve door-less screens a door, or retire them** | **GAP-002** |
| 7.3 | **One refusal shape per meaning** — 403 with an explanation for a refusal, 404 for a missing record, 302 only after success. Today a refusal and a dead link look identical | **GAP-006** |
| 7.4 | **Fix the duplicate headings** — `/users` + `/hierarchy`; three screens headed "Document templates" and a fourth system in Recruitment; two "Sales dashboard"; five list/edit pairs in Reporting | **GAP-009, A-F1, R-F5, S-F2/3** |
| 7.5 | **Split the three form-fatigue screens** — `/connect-qualifications` (619 fields, 549 KB, five tabs), `/call-new` (140), `/quote-new` (97) | **S4-6** |
| 7.6 | **Decide Delete vs Remove** (304 and 90 uses) and settle "Save" vs "Save changes" | **S4-5, S4-9** |
| 7.7 | **Give `/connect-concierge` a heading** — the only screen of 256 without one | **M-F3** |
| 7.8 | **Extend concurrency protection** beyond `calls` and `jobs`, starting with `quotations`, `invoices` and `business_partners` | **S4-4, D-F2** |
| 7.9 | **Say on `/connect-capabilities`** that switching a capability off removes tiles in other areas | **M-F7** |
| 7.10 | **Replace `data.credit` as the books write key** with a named `finance.books.write`. Guarded for now by `test_route_gate_regression.php` | **GAP-010 / Y-F4** |
| 7.11 | **Make the ungated-route absence deliberate** — an explicit allow-list plus a test that fails when a new route is neither gated nor listed | **GAP-008** |

---

## 2a. Security work still open — the register at 19 Oct 2026

One list, so nothing on it is lost between sessions. Each line is either
untested or known-unsafe; none has been verified as safe. Order is the order
of work: highest consequence first, cheapest first where equal.

| # | What is open | Why it matters | Roadmap | Test | State |
|---|---|---|---|---|---|
| S-0 | **Nothing fixed since the branch began is live.** GAP-048 (foreign backup) and GAP-049 (cross-company portal session) are fixed only in the branch | Both are P0 on the live site until the upload | 0.1 | guide Parts 2–3 | **owner action** |
| S-1 | **Talent cards shown to clients:** confirm the HTML and JSON carry no contact or identity field the matrix withholds — not just the rendering | A leak of a professional's contact details to a client is a privacy breach and bypasses the marketplace | 1.4 | T-3 | untested |
| S-2 | **Impersonation ("Login as"):** every action taken while impersonating must name the real person; it must be logged and listed in the matrix | Today it is not established who is accountable for an impersonated change | 2.5 | T-9 | untested |
| S-3 | **Delete cascade:** work order → job → report → billable event → voucher | A delete that orphans a voucher leaves money with no source | 2.3 | T-4 | untested |
| S-4 | **Four forms create numbered empty records** (`/call-new`, `/candidate-new`, `/incident-new`, `/endorsement-new`) | Gaps and ghosts in a numbered register are what an assessor finds first | 2.1 | T-10 | confirmed, not fixed |
| S-5 | **`/quote-new` with an empty submit** — creates a numbered DRAFT with no customer and ₹0. Since 20 Oct it can no longer be sent unapproved or accepted by anyone but the person assigned; the empty save itself is Sales Stage 2.2 | A quotation goes to a customer | 2.2 | T-11 | partly fixed 20 Oct (SA-26) |
| S-6 | **Merge:** reason required; nothing left pointing at the discarded record; say on the screen that it cannot be undone | One-way door with no record of why | 2.6 | T-6 | untested |
| S-7 | **Changing the numbering scheme mid-year** — protect the existing series or refuse | Duplicate invoice or certificate numbers | 2.7 | T-18 | untested |
| S-8 | **Staff sessions without a company stamp** are still accepted anywhere (kept deliberately: the overnight jobs act through them). A real sign-in always stamps, so only a session older than M13 could carry none | Low residual risk; closing it needs the overnight jobs to stamp first | — | `test_cross_company_session` | accepted for now, recorded |
| S-10 | **A Branch Manager can approve another branch's quotation** — the quotation scope gate returns before it resolves an approval step to its quote | Branch segregation of commercial authority does not hold | Sales 1.1 | TM-A20 | **fixed 20 Oct** (GAP-050, SA-06) |
| S-11 | **View is treated as decide on quotations** — anyone who can open a quote can mark it accepted, lost or back to draft, and the portal register stores any status | Read access changes the customer master, the pipeline and the accounting system | Sales 1.2, 1.5 | TM-Q20…Q33 | **fixed 20 Oct** — the person assigned records the customer's answer (GAP-050, SA-03/04) |
| S-9 | **Two test files pass only in the full suite** (`test_m7_*`, `test_m8_*`) | A guard rail that only holds in company can hide a regression | 3.6 | run alone | recorded |

**Done this month, for the record:** GAP-008 (ungated routes, none reachable),
GAP-012 (portal party isolation), GAP-048 (foreign backup restore), GAP-049
(cross-company portal session), the office-scope fallback that fell open.

---

## 3. What is already done

Closed during the audit, with tests:

| | Finding |
|---|---|
| ✓ | **Quality Head and Technical Head roles** — closing a nonconformity no longer needs a system administrator (**GAP-004**, part 1) |
| ✓ | **The Quality dead door** — a tile offered to five roles that answered 403 now goes where they can actually go (**GAP-011**) |
| ✓ | **Permission matrix row 215** corrected to match the owner's accepted money behaviour (**GAP-010**) |
| ✓ | **NCR and CAPA rows** added to the permission matrix, generated from `role_perms()` rather than hand-typed |
| ✓ | **A route-gate guard test** — fails, naming the route, if any of 365 gated routes loses its module gate, and asserts all 17 money routes stay gated (**GAP-008** mitigation) |
| ✓ | **GAP-008 tested** — 200 ungated routes probed per role; **no reachable hole found** |
| ✓ | **The invoicing engine validates every figure off a form and bounds credit notes cumulatively** (**GAP-034/035/036**, 19 Oct): an invoice can no longer be credited past zero, a negative receipt cannot be recorded, and the two money INSERTs re-check their ceilings in the database |
| ✓ | **A priced marketplace plan or credit pack is never granted free when payment keys are missing; the credit wallet stops at zero** (**GAP-037/038**, 19 Oct). Proration recorded as GAP-039 for a pricing decision |
| ✓ | **Dashboards reconciled by execution** (**GAP-040…043**, 19 Oct): /mis, /analytics, the contract view and the home rollup now read the same sources for the same month; margin named; the per-person utilisation denominator is the period reported. Evidence `kpi-reconcile-probe.php`, guard rail `test_kpi_reconcile.php` |
| ✓ | **The Tally export balances** (**GAP-044/045**, 19 Oct): the round-off is its own ledger entry so every sales voucher imports; the CSVs now say what the XML says. Bank charges on a receipt recorded as **Q-J** for the owner |
| ✓ | **Claim sheets, job-closure expenses and quotations refuse impossible figures** (**GAP-046/047**, 19 Oct), proven live over HTTP against throwaway copies. A negative row can no longer beat the daily hours cap |
| ☐ | **A discount field on the quotation** — today a discount is a negative-rate line, which GAP-047 had to keep allowed. A named discount figure is clearer for the client and checkable |
| ✓ | Earlier in the project: the navigation model, Back going up, approvals unified and moved out of "Report configuration", hiring-request change control, interview panel, the office-scope failure closing instead of falling open |

---

## 4. If you only do five things

1. **Test portal party isolation** (T-1). The only unrecoverable risk.
2. **Fix the four ghost-record forms** (T-10). Cheap, visible, and an assessor
   will find them.
3. **Gate and log backup/restore, and document impersonation** (T-5, T-9). Two
   unspecified authorities over everything.
4. **Reconcile one month by hand** (T-17). Until then nobody should quote a
   profit figure from this system.
5. **Write the Quality lifecycle sections** (5.1). The one thing software cannot
   produce for you, in the area an assessor examines.

---

## 5. Effort, roughly

Deliberately coarse — these are weeks of someone's attention, not estimates.

| Stage | Size |
|---|---|
| 0 — confirm the build, answer the questions | days |
| 1 — portal and tenant isolation | **1–2 weeks** |
| 2 — one-way doors and ghost records | 1–2 weeks |
| 3 — controls that may not control | 1 week |
| 4 — make the numbers trustworthy | **2–3 weeks**, mostly populating and reconciling |
| 5 — documentation | 1 week |
| 6 — the audit's unfinished business | 2 weeks |
| 7 — usability and tidying | 2–3 weeks, and can run alongside |

Stages 1 and 4 are the expensive ones. Stage 5 is the cheapest and closes the
most assessment risk.

---

## 6. The eight questions only you can answer

Six findings cannot be closed without these. Three are one-line answers.

| | Question | Blocks |
|---|---|---|
| **Q-B1** | **Who may raise a nonconformity?** Today nobody below Business Director can open the register — and an inspector, who meets the problem on site, cannot. *Recommend: inspectors and coordinators raise, quality closes* | GAP-004 |
| **Q-B2** | Should the five roles who see a **corrective action** also see the **nonconformity** behind it? *Recommend: yes, read-only. It is one loop* | GAP-004 |
| **Q-B3** | Should your two **administrator logins** keep the right to close a nonconformity? Strictly "only Quality or Technical Head" would remove it | GAP-004 |
| **Q-C** | **Is Marketplace live?** It is 68 of 317 tables — the largest block in the schema, bigger than Quality | scope of everything |
| **Q-M3** | **Which product packs are you selling?** `/product-package` offers four — TPIA, Technical Staffing, Recruitment, Recruitment In-house HR. It decides whether **`mgh-hire/`** is a product or a fifth wheel. *(The "four ways to match people to work" half of this question is withdrawn — see GAP-007)* | GAP-007, Q-C |
| **Q-M2** | Who may change a **commission rate, a fee rule, or release escrow**? Observed as "Coordinator and above"; documented nowhere. *(Q-M1 — "should marketplace revenue reach the same P&L" — is **answered**: it already does, via `connect_bridge.php` → billable event → finance attests → invoice)* | M-F6a |
| **Q-G** | **Who should see the vendor register?** Today 17 of 18 roles can, and the **only role refused is Finance** — who pays the vendors | `06-DATA-AND-PERMISSION-AUDIT.md` §4 |
| **Q-D** | Is a **non-production workspace** available? Every test in `09-UAT-PLAN.md` needs one | the whole plan |

---

## 7. A closing note on what this audit is worth

Five of my own findings were withdrawn after checking them properly, or after
the owner corrected me:

| Withdrawn | What it actually was |
|---|---|
| "The audit trail has a **Clear** button" | It clears the **filter** |
| "**Vendor** and **Supplier** are synonyms" | Two of **five separately defined parties** |
| "A **KPI framework** nobody uses" | A built analytics subsystem I had not found |
| "**Four implementations** of matching people to work" | **One supply chain**, internal and external branches, joined in code — the owner's correction, and the most consequential one |
| "Marketplace runs a **second set of books** with nothing joining them" | `connect_bridge.php` hands an award to the existing invoicing chain. The `mkt_*` tables are the platform's own commission economics, correctly separate |

A sixth — my route inventory — was short by ~160 routes and three whole
portals.

Each is recorded in place rather than edited away, because **a reader needs to
know which conclusions were re-derived and why.**

**The pattern in all six is the same, and it is worth naming precisely: I
inferred an absence from my own incomplete map.** No link in the navigation
became "nothing joins them"; one file's routes became "the routes"; a button's
label became what the button does. The fix each time was to go and look at what
the thing was actually wired to.

**So the gradings to trust least in this folder are the ones that assert a
negative** — anything marked *"CONFIRMED (absence)"*. Those were the three that
turned out wrong. The positive measurements — a field that exists, a record that
was written, a role that was refused — all held.

**So: trust the measurements in this folder, and treat any conclusion that
rests on a label, a count or a single file as provisional until it has been
driven.** The evidence scripts are all committed and re-runnable, which is the
point — you should not have to take my word for any of it.
