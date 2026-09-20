# RB-3 · Step 2 — Duplicate-match rules

*The exact rules by which an applicant is compared against people already on the
team, and which of those comparisons requires a recruiter to acknowledge before
the hire may proceed.*

---

## 1. The only question this asks

> **Is the person we are about to hire already on our team?**

It is **not** asking whether two team members are the same human — that is
"one person = one inspector", which nobody has decided and which this step does
not touch. It is not asking anything about the employee number either: **the
employee number is never a matching signal.** It identifies an *employment
record*, not a *person*, and Step 1 already made it unique for life.

## 2. What evidence actually exists

Searched, not assumed. The candidate and the team-member record have exactly
these in common:

| Field | Candidate | Team member |
|---|---|---|
| mobile | `candidates.mobile` | `inspectors.mobile` |
| e-mail | `candidates.email` | `inspectors.email` |
| given / family name | `first_name`, `middle_name`, `last_name` | same, plus `name` |

**There is no date of birth, no PAN and no Aadhaar in this application.** None is
invented to make a rule or a test work. If the business later captures one, it
becomes a strong signal and this document is the place to add it.

## 3. The scale is the one already in use

`cand_find_duplicates()` (`lib/recruit.php:318`) has compared applicants to each
other on this scale since Phase 3:

| Basis | Confidence |
|---|---|
| same mobile — last 10 digits, non-digits stripped | **96** |
| same e-mail — lower-cased and trimmed | **94** |
| same first **and** family name | **72** |
| same family name **and** same first initial | **46** |
| anything weaker | not reported |

**Reused exactly.** A second scoring model would mean one screen calling
something 96 and another calling the same thing 80, and a recruiter learning that
the numbers mean nothing.

## 4. Strong · possible · weak

| Class | Basis | Confidence | Effect |
|---|---|---|---|
| **STRONG** | same **mobile**, or same **e-mail** | 96 · 94 | **Acknowledgement required.** The hire is refused until the recruiter ticks |
| **WEAK** | same full name · same family name + initial | 72 · 46 | **Shown, never blocks.** Information only |
| **NONE** | anything else | — | nothing shown |

**There is deliberately no "possible" class that blocks.** The instruction's own
example of a possible match is *name + date of birth* — and this application
holds no date of birth, so that class cannot be formed from real evidence. Making
name alone block would be exactly the naive behaviour §6 forbids.

### Why mobile and e-mail are strong, and a name is not

A mobile number and an e-mail address are things a person **chooses and owns**.
Two records carrying the same one are the same person often enough that a human
should look. A name is not an identifier: **Rajesh Patel does not block Rajesh
Patel**, and the test `X5` exists to keep it that way.

## 5. What is compared, and what is not

| | Rule |
|---|---|
| **Who is compared** | **live** team members only — `status` ACTIVE or blank (a blank status counts as active, field-finding #26) |
| **Who is not** | a person who has **left**. They are history, not a duplicate, and warning about them would train recruiters to click past the warning |
| **Scope** | a match the actor may not open is **counted, never named** — a record id is never proof of authorisation (invariant I25) |
| **Tenant** | structural. One database per tenant, so another tenant's staff are not reachable at all — not filtered out, unreachable |
| **Ceiling** | **none.** `cand_find_duplicates()` has `LIMIT 500` and says nothing about it (audit finding **N5**); this must never acquire one, because a check that quietly becomes partial is worse than one that is absent |
| **Normalising** | mobile → digits only, last 10 compared · e-mail → `LOWER(TRIM())` · names → lower-cased and trimmed. Blank on either side never matches blank |

## 6. Worked examples

| Applicant | Existing team member | Class | Outcome |
|---|---|---|---|
| Rajesh Patel · 98200 11111 | Rajesh Patel · 98200 11111 | **STRONG** (mobile) | refused until acknowledged |
| Rajesh Patel · 98200 11111 | Rajesh Patel · 99887 65432 | WEAK (name) | shown, proceeds — **X6** |
| Rajesh Patel · 98200 11111 | Suresh Kumar · 98200 11111 | **STRONG** (mobile) | refused until acknowledged — **X7** |
| Rajesh Patel · r.patel@x.com | Rajesh Patel · r.patel@x.com | **STRONG** (e-mail) | refused until acknowledged |
| Rajesh Patel | Rajesh Patel *(no contact on either)* | WEAK (name) | shown, proceeds — **X5** |
| Rajesh Patel · 98200 11111 | Rajesh Patel · 98200 11111 **(left the company)** | NONE | nothing shown, proceeds |
| Rajesh Patel · 98200 11111 | *(match in a branch this recruiter cannot open)* | **STRONG** | refused, and the match is **counted, not named** |

## 7. The rehire case

A genuine rehire is a **STRONG** match and **must** be acknowledged. That is the
point: the system does not decide whether it is a rehire or a mistake — a person
does, explicitly, and the decision is recorded against the application with the
matches that were shown at the time.

**The tick is an acknowledgement, not a merge.** Nothing is linked, nothing is
merged, no identity is declared. Two team members may legitimately be one human —
a rehire, or supply through two agencies — and deciding otherwise is not this
step's business.

## 8. What would change these rules

Only new evidence. If the application starts capturing a date of birth, a PAN or
another identifier the business genuinely holds, add a row to §4 and a case to
§6, and say which class it lands in. Do not widen the strong class on a hunch,
and do not add a scoring model on top of the one already here.
