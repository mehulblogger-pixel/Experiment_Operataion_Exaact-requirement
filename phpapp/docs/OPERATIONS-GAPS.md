# Operations system — the gap list and how each one closes

Scope decided July 2026: **an operations system for services businesses.**
Not an ERP. No purchase, no stock, no production, no dispatch of goods.

That decision removes the largest item from the July audit and changes the
ranking of everything else. What follows is the gap list *for the product we
have decided to build*, measured against the running code.

---

## What is already strong — so we do not rebuild it

Measured, not assumed:

| Capability | State |
|---|---|
| Order → job → report → invoice → receipt | Whole chain, connected, 171 screens tested |
| Scheduling / availability board | Present |
| Skills matching, trade and skill masters | Present |
| Workload ceiling (hours per day, working days) | Present — `hours_cap` |
| Competence and certification with expiry chasing | Present |
| Timesheet / attendance reconciliation against payroll | Present |
| Expense vouchers with per-person rates | Present |
| SLA per stage, TAT tracking | Present, and used in 181 files |
| Multi-branch data scoping | Present |
| Subcontractor / agency staff | Present |
| Client portal with per-user permissions | Present |
| Job costing and branch profitability | Present |
| Escalation and reminder automation | Present |
| Offline drafts + queue-and-sync | Present — **but only on IDEMS forms** |
| Recurring *visit patterns* on one work order | Present — single, continuous, multiple, pattern, monthly |
| Compliance: ISO 17020, DPDP, CERT-In | Present, with a live status screen |

**That is a lot of operations system.** The gaps below are real but none of them
is foundational.

---

## The gaps, in the order I would fix them

### G1 — AMC and recurring service contracts · **the biggest one**

**What is missing.** A work order can repeat on a pattern. A *contract* that
spawns work orders cannot exist. There is no annual agreement holding "12 visits,
quarterly, ₹4,80,000, renews 1 April", nothing that generates the next visit
automatically, nothing that counts visits consumed against visits sold, and no
renewal pipeline.

**Who this blocks.** Every AMC provider, facilities firm, equipment servicer,
lift and HVAC company, laboratory on an annual testing contract, and any
inspection agency on a retainer. In a services business this is the *recurring
revenue* — the most valuable revenue there is, and the product cannot model it.

**Why it matters commercially.** A customer on an AMC renews without being sold
to again. Software that manages AMCs is software a business cannot leave.

**How we fix it.**
1. A `service_contracts` table: customer, period, value, visits sold, frequency,
   scope, renewal date, notice period.
2. A nightly job that raises the next work order when one falls due — using the
   existing order → job machinery, so nothing is duplicated.
3. A consumption meter: visits sold, used, left, and an alert at 80%.
4. A renewal pipeline reusing the opportunity stages already built, so renewals
   are forecast like any other deal.
5. Contract-level profitability, feeding the existing profitability screens.

**Effort:** 2–3 weeks. **Reuses:** orders, jobs, opportunities, profitability,
invoicing. **Builds new:** the contract itself and the generator.

---

### G2 — Proof the person was there

**What is missing.** Site check-in does not exist. It is *referred to* in the
compliance text — "the site check-in on the job carries the fact instead" — and
that was written about a feature nobody built. No arrival time, no location, no
photograph timestamp beyond what IDEMS already keeps.

**Who this blocks.** Every business that bills for attendance. A customer
disputing "your man never came on the 14th" cannot be answered today except by
asking the person.

**How we fix it.**
1. Check-in / check-out on the job, from the phone, capturing time and
   coordinates with the person's consent.
2. Distance from the recorded site address, shown but never used to accuse —
   coordinates on an industrial site are often wrong and a false accusation
   destroys trust faster than a missing feature.
3. Actual hours on site flowing into the timesheet, so the voucher fills itself.
4. On the client portal: "arrived 09:12, left 17:40" — which ends the dispute
   before it starts.

**Effort:** 1 week. **Depends on nothing.** **Highest trust-per-hour on the list.**

---

### G3 — The data-entry aids

**What is missing.** Five of the fifteen exist. The four that matter for an
operations business:

| Gap | Fix | Effort |
|---|---|---|
| GST / PAN auto-fetch | Public API; type a GSTIN and the name, address and state fill themselves | ½ day |
| PIN code auto-fill | Public API; city, district and state from six digits | ½ day |
| Keyboard shortcuts on registers | `/` to search, `n` for new, `j`/`k` to move, `Enter` to open | 1 day |
| Recently used values | Last five per field per person, at the top of the dropdown | 2 days |

**Why these four.** A coordinator raising twenty work orders a day types the same
customer, site and PIN code twenty times. This is the difference between software
people tolerate and software they like. It also demonstrates extremely well.

**Effort:** 4 days for all four.

---

### G4 — Offline for the field, not just for reports

**What is missing.** The offline engine exists and works — drafts in local
storage, submissions queued and replayed — but only on forms marked
`data-autosave`, which today means IDEMS report forms. The field person's own
screens are not covered: their job list, closing a job, the expense voucher,
check-in.

**Who this blocks.** Anyone working in a basement, a plant, a lift shaft or a
village. Which in field operations is most days.

**How we fix it.** Mark the four field screens for autosave and queueing. The
machinery is built; this is applying it. **Effort:** 3 days.

---

### G5 — Travel and same-day clustering

**What is missing.** Nothing groups jobs by location. Two people are sent to the
same industrial estate on the same morning, in two vehicles, and only the
expense voucher ever notices.

**How we fix it.** Not route optimisation — that is a research project. Just:
when allocating, show the other jobs already scheduled near that site that week,
and who is going. A human then makes the obvious call. **Effort:** 4 days.

---

### G6 — A report the customer can build themselves

**What is missing.** Dashboards are fixed. Every new question — "utilisation by
trade by branch last quarter" — needs us.

**How we fix it.** A picker over the registers already exposed: choose a
register, columns, filters, grouping, save it, share it with a role. Not SQL, not
a query language. The lookup engine and register definitions already describe
every column. **Effort:** 2 weeks. **Do it after G1–G4** — it is the thing that
stops support calls scaling with customers, which only matters once there are
customers.

---

### G7 — WhatsApp for job assignment

**What is missing.** Jobs are assigned by e-mail. Field staff in India do not
read e-mail.

**How we fix it.** WhatsApp Business API: job assigned, job tomorrow, report
overdue, plus a reply to accept. **Effort:** 1 week plus Meta approval, which is
the slow part and should be started early. **Caution:** template approval and
per-message cost. Worth confirming the economics before building.

---

### G8 — The technical debt, honestly listed

| Item | Effort |
|---|---|
| Finish migrating 18 forms onto the one system, then a lint rule so it cannot drift back | 1 week |
| 2,785 inline styles down to near zero | 1 week, mechanical |
| CSP `unsafe-inline` removed — move inline handlers out | 1 week |
| Load test at real volume against the real server | 1 day |
| MySQL charset audit — the `?` bug proved the database default is not utf8mb4 | ½ day |

The lint rule matters more than the sweep. Without it the drift returns, because
it arrived one reasonable local decision at a time.

---

## Sequence

**Weeks 1–2 — trust and speed.** G2 check-in · G3 data-entry aids · G4 offline.
Small, visible, and every one of them is felt on the first day of use.

**Weeks 3–5 — the revenue engine.** G1 service contracts and renewals. This is
the one that changes what the product is worth.

**Weeks 6–7 — stop support scaling.** G6 report builder.

**Then, in parallel with selling.** G5 clustering · G7 WhatsApp · G8 debt, a day
a week.

---

## What we are deliberately NOT building

Written down so it stops being reconsidered every month.

- Purchase, inventory, production, dispatch of goods — **out of scope by decision**
- OCR, voice input, business-card scanning — demo features
- Churn prediction, sentiment analysis — no data to train on and no decision they'd change
- Route optimisation — a research project; G5 gets 80% of the value in 4 days
- ISO 27001 / SOC 2 — only when a client asks in writing

---

## The one number to hold onto

Of the sixty industries in the original brief, **roughly thirty-five are
workable today** and the twenty-five that are not were blocked by stock and
production — which we have now decided not to build.

So the addressable market is thirty-five trades, and **G1 is the only gap on this
list that opens more of them.** Everything else makes the product better for the
trades it already serves. That is the right shape for a first product.
