# How costing works

Written for the person running the business, not for a programmer.

There are **two separate questions**, and mixing them up is what makes costing
confusing. Keep them apart and it becomes simple.

| Question | Answer comes from | Who asks it |
|---|---|---|
| Did *this order* make money? | The BOSS number's own revenue and its own direct costs | Sales, branch manager, anyone pricing the next job |
| Did *this branch* make money? | Total revenue minus every rupee the branch spent | The owner, the accountant |

The first is a **job-level** number. The second is an **office-level** number.
They are not meant to add up to each other, and they never will.

---

## Question 1 — did this BOSS number make money?

Only what that order actually caused.

```
Revenue billed on the BOSS number
  −  the engineer's time on it   (their monthly cost ÷ working days × days worked)
  −  their travel and site expenses
  −  any sub-contractor paid for it
  −  one overhead %              (optional, see below)
  =  profit on that order
```

Nobody's salary is spread onto it, the rent is not spread onto it, and the
branch manager is not spread onto it. Every figure here is traceable to
something that happened on that job.

### Worked example — BOSS-2231

- Client billed: **₹2,00,000**
- Engineer: Ravi, cost **₹60,000 a month**, 22 working days in the month →
  **₹2,727 a day**
- He spent **11 days** on this order → **₹30,000**
- His travel and hotel on the job: **₹18,000**
- One day of a sub-contractor: **₹9,000**

```
Revenue                    2,00,000
Engineer's time (11 days)  − 30,000
Travel & site expenses     − 18,000
Sub-contractor             −  9,000
                           ─────────
Profit on BOSS-2231         1,43,000   (71.5%)
```

That is what the Profitability screen shows for the order.

### The one overhead %

The system also applies a single **overhead %** on top of the engineer's cost,
so an order is not judged as if the engineer were free of everything else.
It defaults to **8%** and it is **not fixed in the code** — it is set in three
places, in this order of priority:

1. **Per office** — Office costs & overheads → *Overhead % (fallback)* tab
2. **A global default** — same tab, top panel
3. If both are blank, 8%

With 8% on, Ravi's 11 days cost `30,000 × 1.08 = ₹32,400` instead of ₹30,000,
and BOSS-2231 shows ₹1,40,600 instead of ₹1,43,000.

There is also a **contingency %** which works the same way — a buffer added on
top of (labour + expenses + sub-con), also per office, also blank by default.

> **On the "×1.30" I mentioned in an earlier conversation:** that was wrong.
> The multiplier in the code is **8%** (`×1.08`), not 30%, and it is a fallback
> that any office can override. Nothing is hard-coded.

---

## Question 2 — did the branch make money?

Now, and only now, everything the office spends comes in.

```
All revenue the branch billed this month
  −  every engineer's salary          (from their own record)
  −  every non-engineer's salary      (branch manager, coordinator, accountant, admin)
  −  every office cost                (rent, electricity, laptops, contingency, …)
  =  branch profit
```

This is a straight subtraction. Nothing is allocated, nothing is estimated.

### Worked example — Vadodara branch, July

**Revenue**

| | ₹ |
|---|---:|
| 14 BOSS numbers billed | 24,00,000 |

**People**

| Person | Role | Monthly cost |
|---|---|---:|
| Ravi | Inspection engineer | 60,000 |
| Anil | Inspection engineer | 55,000 |
| Sneha | Inspection engineer | 48,000 |
| Bela | Branch manager | 1,20,000 |
| Kiran | Coordinator | 45,000 |
| Meera | Accounts | 38,000 |
| | **Total salary** | **3,66,000** |

**Office costs** (typed on Office costs & overheads → Actual costs)

| Head | ₹ |
|---|---:|
| Office rent | 50,000 |
| Electricity & utilities | 8,000 |
| Internet & telephone | 6,000 |
| Laptops & IT equipment | 12,000 |
| Printing & stationery | 3,000 |
| Contingency | 10,000 |
| **Total** | **89,000** |

**Branch profit**

```
Revenue           24,00,000
Salaries         − 3,66,000
Office costs     −   89,000
                  ──────────
Branch profit     19,45,000
```

Every rupee the branch paid out is in there once, and only once. That is why
**salary is never an expense head** — salaries come from the people, expense
heads hold everything that is not salary, and there is no overlap to argue
about.

---

## Question 3 (bonus) — which SBU is carrying which?

Branch profit is one number. It does not tell you whether Oil & Gas is paying
for Industrial's losses. For that, the ₹3,66,000 of salary and ₹89,000 of
office cost has to be shared out across the SBUs. That is what the allocation
engine does, and it follows the rules you set:

### Rule 1 — inspection engineers follow their work

A day worked goes to that job's SBU and activity code. Nothing is estimated.

*Ravi, July: 10 days Industrial, 5 days Oil & Gas, 7 days not chargeable
(office, leave, holidays).*

- His 22 available days cost ₹60,000, so ₹2,727 a day.
- 10 days Industrial → **₹27,273**
- 5 days Oil & Gas → **₹13,636**
- The 7 non-chargeable days (**₹19,091**) split in the ratio of the work he
  actually did that month — 10:5, or two-thirds/one-third →
  **₹12,727 Industrial**, **₹6,364 Oil & Gas**

**Totals: ₹40,000 Industrial, ₹20,000 Oil & Gas.** His whole salary lands
somewhere; nothing is lost and nothing is invented.

### Rule 2 — travel days belong to the inspection they are for

A travel day carries no job of its own. By your rule it takes the SBU and
activity code of the inspection it is travelling for — whether you travel out
the day before, or travel back the day after.

Mark the call **outstation** on the call allocation screen and the travel days
either side attach to that job automatically.

### Rule 3 — everybody else splits by percentage, set monthly

A branch manager looks after the whole branch, so their salary cannot follow
any single job. Instead each non-engineer gets a **percentage box per SBU on
their own record**, set each month.

*Bela the branch manager, ₹1,20,000, four SBUs in the branch:*

| SBU | % | ₹ |
|---|---:|---:|
| Industrial | 40 | 48,000 |
| Oil & Gas | 40 | 48,000 |
| Minerals | 10 | 12,000 |
| Government | 10 | 12,000 |
| | **100** | **1,20,000** |

Ten SBUs in the branch means ten boxes. Equal across all of them is fine too
(25/25/25/25); so is 40/40/20.

**If the split is not entered this month, last month's carries forward.**
Changing it in September does **not** rewrite August — a month that has been
calculated and frozen stays as it was.

### Rule 4 — office costs spread by the basis on each head

Each expense head says how it should spread, and you choose:

| Basis | Used for |
|---|---|
| Equally across the SBUs | Rent, electricity, general overheads |
| By man-days worked | Anything driven by activity |
| By revenue earned | Professional fees, contingency |
| By the number of people | Laptops, internet, stationery |

*Rent ₹50,000, spread equally across four SBUs → ₹12,500 each.*
*Laptops ₹12,000 by headcount, where Industrial holds half the people →
₹6,000 Industrial, the rest shared.*

All of this is editable in **Masters → Office expense heads**. Add a head,
rename it, change its basis, retire it. Nothing there is fixed in the code.

### Rule 5 — an engineer with no chargeable day at all

Rare, but it has to land somewhere. **You decide**, on Office costs &
overheads → *SBUs in this office*:

- by the office's overall SBU mix that month (the default), or
- equally across the branch's SBUs, or
- by a fixed percentage set on that person's record

---

## Every scenario, in one table

| Situation | Where the cost goes |
|---|---|
| Engineer works a day on a job | That job's SBU + activity code + BOSS number |
| Engineer in the office / on leave / on a holiday | Split by *his own* SBU mix that month |
| Engineer travels out the day before an outstation job | The SBU + activity of the job he is travelling to |
| Engineer travels back the day after | The same job he was travelling from |
| Engineer with no chargeable day that month at all | The rule the admin picked for the office |
| Assistant manager who inspects sometimes | Days on a job → that job. The rest → their own % split |
| Branch manager, coordinator, accountant, admin | Their own % split per SBU, set monthly |
| Rent, electricity, laptops, contingency | Their expense head's basis (equal / man-days / revenue / headcount) |
| Salary | **Never** an expense head — always from the person's record |

---

## What is configurable, and where

| Thing | Where you change it |
|---|---|
| Expense heads: add, rename, change basis, retire | Masters → Office expense heads |
| How much was spent on each head, per month | Office costs & overheads → Actual costs |
| Which SBUs an office runs | Office costs & overheads → SBUs in this office |
| Rule for an engineer with no chargeable day | Office costs & overheads → SBUs in this office |
| Overhead % and contingency % | Office costs & overheads → Overhead % (fallback) |
| A person's monthly cost | Their own record |
| A person's SBU % split | Their own record, month by month |
| Working days in a month | Masters → Holidays, and the working-norms settings |

Nothing in that list is fixed in the code. The 8% overhead is a fallback used
only when an office has not set its own.

---

## Which method is an office on?

Every screen that shows a cost says which of the two it used:

- **Real costs** — that office has entered salaries and office costs, so the
  figures are actual money.
- **Percentage** — that office has not entered them yet, so the overhead % is
  standing in.

An office can move from one to the other simply by entering its figures. Both
are labelled on screen, because a number calculated two different ways for two
different branches, with nothing saying which, is worse than either.
