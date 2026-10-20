# EXAACT Sales — end-to-end test matrix

**For:** whoever tests Sales — today on a throwaway copy, after each fix as a
regression check, and at UAT on the test copy (`../14-LIVE-CHECKS-GUIDE.md`
Part 7). **Written:** 19 Oct 2026.

**How to read a row.** *Preconditions* are what must exist before the step.
*Expected* is the correct **business** behaviour — where it depends on a rule
the owner has not set, it names the decision (`D-nn`,
`SALES-BUSINESS-DECISIONS.md`). *Today* is what the current code does:

- **PASS** — run on a throwaway database and behaved as expected
- **FAIL** — run and did not; the finding id (`SA-nn`) is in
  `SALES-USER-AND-INDUSTRY-AUDIT.md` §10
- **READ** — established from the code, not run
- **UNTESTED** — not established; the row is the procedure to run

**Evidence** codes point at `../evidence/sales-audit/`: `A/p05` means
`A/p05_quote_status.php` and its `.out`; `B/p4` means `B/p4_bands.php` and
`B/p4_output.txt`; `C/p2` means `C/p2_contract.php` and `C/p2.out`; `D/…` the
named file in `D/`.

**Re-runs after the fixes of 20 Oct 2026.** The *Today* column records the
audit run of 19 Oct. Rows since turned to PASS by a guard-rail test are listed
in `SALES-REMEDIATION-ROADMAP.md` §8a with the test that keeps them so; the
Quotation, Approval, Contract, Billing, Customer-data, Permission and History
sections are the ones most changed.

**Never on the live site.** Every FAIL row below changes records in a way that
cannot be cleanly undone (a customer created, a lead converted, a quote pushed to
Books). Run them only on a copy.

**Roles** are written as: MA Master Admin · BD Business Director · BM Branch
Manager · OM Operation Manager · CO Coordinator · MM Marketing Manager · BDM
Business Development Manager · KAM Key Accounts Manager · ME Marketing Executive
· FIN Finance · INS Inspector.

---

## 1. Leads (L)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-L01 | BDM | default rights | Open **Sales → Leads** | Opens (D-01) | **FAIL** — 403 · SA-11 | D/route_matrix.tsv |
| TM-L02 | BD | one OPEN lead | Convert it | One customer, one enquiry, lead CONVERTED, history line | PASS (no history line — SA-12) | A/p01 |
| TM-L03 | BD | a CONVERTED lead | Convert again | Refused | PASS | A/p01 |
| TM-L04 | BD ×2 | one OPEN lead | Two people press Convert at the same instant | One customer, one enquiry; the second told it is already converted | **FAIL** — two customers, two enquiries, 3/3 runs · SA-18 | A/p01 §3 |
| TM-L05 | BD | a LOST lead | Convert it | Refused, or reopen first with a reason (D-22) | **FAIL** — converts · SA-27 | A/p01 |
| TM-L06 | BD | a CONVERTED lead | Edit company name | Locked, or logged old → new | **FAIL** — editable, unlogged · SA-27 | A/p01 |
| TM-L07 | BD | a lead with a quote raised from it | Delete the lead | Refused while anything points at it | **FAIL** — deleted; quote points at nothing · SA-52 | A/p11 |
| TM-L08 | BD | Ads Pro stub returns a lead whose e-mail matches an existing lead except for case and spaces | Bring leads across | One lead, flagged as a possible duplicate | **FAIL** — second lead created · SA-18 | C/p6 |
| TM-L09 | BD | a lead typed by hand; Ads Pro returns the same person | Bring leads across | Matched to the typed lead | **FAIL** — duplicated · SA-18 | C/p6 |
| TM-L10 | BD | Ads Pro returns a lead it already sent | Bring leads across again | No new lead | PASS | C/p6 |

## 2. Opportunities (O)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-O01 | BD | an OPEN deal | Move to WON | WON; nothing else is auto-created (D-21) | PASS | A/p02 §2 |
| TM-O02 | BD | an OPEN deal with two alternative quotes | Mark quote 1 LOST | Deal stays OPEN while quote 2 is live | **FAIL** — deal LOST · SA-27 | A/p02 §3 |
| TM-O03 | BD | TM-O02 done | Accept quote 2 | Deal WON | **FAIL** — cannot win it back · SA-27 | A/p02 §4 |
| TM-O04 | BD | a WON deal | Change its value | Locked, or logged | **FAIL** — set to 1, unlogged · SA-27 | A/p02 |
| TM-O05 | BD | a deal for customer X | Accept a quote for customer Y linked to it | Refused | **FAIL** — wins the deal · SA-27 | A/p11 |
| TM-O06 | BD | an OPEN deal past its expected closing date | Move to WON | Allowed; the late close is visible on the deal | READ — `opp_move()` moves any OPEN deal; no "late" marker | A |
| TM-O07 | BD | a LOST deal | Reopen it | Manager may reopen with a reason (D-22) | READ — no reopen path | A |
| TM-O08 | BD | an OPEN deal | Assign it to an inactive owner | Refused | PASS | D/P17 |
| TM-O09 | FIN | a deal WON without a quote | Register its contract | Allowed | **FAIL** — 403 · SA-11 | D/flow_perm.out P12 |
| TM-O10 | BD | a WON deal with an accepted quote of 1,00,000 and an estimate of 2,50,000 | Raise the work order from the deal | Work order value 1,00,000 | **FAIL** — 2,50,000 · SA-22 | D/H2 |
| TM-O11 | FIN | a deal won through the deal path, then its quote accepted | Register through both doors | One contract | **FAIL** — two contracts · SA-10/SA-11 | A/p02 §5b |

## 3. Enquiries (E)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-E01 | ME | — | Submit an empty enquiry | Refused, naming the five things needed | PASS (corrects 01-SALES E-1) | D |
| TM-E02 | ME | — | Post status "BANANA" | Refused | **FAIL** — stored · SA-27 | A/p03 |
| TM-E03 | ME | an enquiry with a live quote | Set it back to OPEN | Refused | **FAIL** — allowed · SA-27 | A/p03 |
| TM-E04 | ME | a DROPPED enquiry | Raise a quote from it | Allowed, logged as revived | ◐ allowed, not logged | A/p03 |
| TM-E05 | ME | an enquiry | Raise two alternative quotes | Both linked; enquiry QUOTED | PASS | A/p03 |
| TM-E06 | ME | a customer exists as "Acme Valves Pvt Ltd" | Type "ACME VALVES PRIVATE LIMITED" on a new enquiry | "Did you mean Acme Valves Pvt Ltd?" | **FAIL** — no warning · SA-18 | C/p5 |
| TM-E07 | ME | a customer not yet on file | Type the name and save | Saved; the register does not stall | PASS | A/p03 |
| TM-E08 | — | two enquiries converted the same day | Look at both numbers | Different | **FAIL** — INQ-2610-003 twice · SA-18 | A/p01b |
| TM-E09 | BD | an enquiry ten days old, SLA 7 | Home → attention | "Enquiries awaiting a quote" lists it | READ — `crm.php:1261-1298` | D |
| TM-E10 | BM | an enquiry for office B, user scoped to office A | Open the enquiry register | Office B's enquiry not shown (enquiries have no office — D-30a) | **FAIL** — scoped by business unit only · SA-33 | D |

## 4. Quotations (Q)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-Q01 | ME | — | Submit an empty **New quote** | Refused: "add a customer and a subject" | **FAIL** — numbered DRAFT, no customer, ₹0 · SA-26 | A/p04 §1 |
| TM-Q02 | ME | TM-Q01's empty quote | Take it to ACCEPTED | Refused at every step | **FAIL** — reached ACCEPTED · SA-26 | A/p04 §1 |
| TM-Q03 | ME | — | Double-click **Create quote** | One quote | **FAIL** — two · SA-26 | A/p04 §2 |
| TM-Q04 | ME ×2 | — | Two creates at the same instant | One quote, or two with clean messages | **FAIL** — one saved, one raw database error · SA-26 | A/p04 §2 |
| TM-Q05 | ME ×2 | one DRAFT open in two tabs | Save tab 1, then tab 2 | Tab 2 told it changed; nothing lost | **FAIL** — tab 2 silently wins · SA-26 | A |
| TM-Q06 | ME | a form with a negative GST typed | Create quote | Refused, **typing kept** | ◐ refused (GAP-047), typing lost (READ) · SA-26 | D |
| TM-Q07 | BDM | a DRAFT; a band covers its value | Send | Refused: "needs approval first" (D-02) | **FAIL** — sent · SA-01 | A/p06 §A |
| TM-Q08 | BDM | a PENDING_APPROVAL quote | Send | Refused | **FAIL** — sent · SA-01 | B/p5 §A |
| TM-Q09 | BDM | a REJECTED quote | Send | Refused | **FAIL** — sent · SA-01 | B/p5 §A |
| TM-Q10 | BDM | an APPROVED quote | Send | Sent; locked; follow-ups scheduled | PASS | A/p05 |
| TM-Q11 | MA | a SENT quote | Edit a rate | Refused — revise instead | PASS | A/p05 |
| TM-Q12 | BDM | a SENT quote | Revise | New DRAFT rev 1 with every line and site; rev 0 frozen | PASS | A/p07 |
| TM-Q13 | MM | SENT quote; customer asks for 10 % off | Revise, add discount, submit | Rev 1 needs a fresh approval | PASS | B/p5 |
| TM-Q14 | ME | an APPROVED ₹59,000 quote | Edit to ₹1,06,20,000 | Back to DRAFT; steps voided; old → new logged (D-07) | **FAIL** — stays APPROVED · SA-02 | A/p06 §C |
| TM-Q15 | ME | a PENDING ₹50,000 quote (band ≤ ₹1 L) | Edit to ₹50 L | Chain rebuilt for the new value | **FAIL** — ≤ ₹1 L approver approved it · SA-02 | B/p5 §F |
| TM-Q16 | ME | an APPROVED ₹50,000 quote | Edit to ₹1 | Back to DRAFT | **FAIL** — stays APPROVED, sent · SA-02 | B/p5 §F |
| TM-Q20 | FIN | a DRAFT never approved | Post status ACCEPTED | Refused (D-08) | **FAIL** — accepted; customer, deal, lead, Books cascade · SA-03 | C/p1 |
| TM-Q21 | BM | a PENDING quote | Post DRAFT / REJECTED / EXPIRED / LOST | Refused | **FAIL** — all four · SA-03 | A/p05 §2 |
| TM-Q22 | MM | an ACCEPTED quote with a live contract | Post LOST, then ACCEPTED, then LOST | Refused — ACCEPTED is final | **FAIL** — flips, no history · SA-03 | A/p05 §5 |
| TM-Q23 | MA | an ACCEPTED, never-sent quote | Post DRAFT | Refused | **FAIL** — allowed · SA-03 | A/p08 §4 |
| TM-Q24 | (owner's role, D-08) | a SENT quote | Record ACCEPTED | Accepted; who and when recorded | ◐ accepted; who not recorded · SA-12 | A/p10 |
| TM-Q25 | (owner's role) | an ACCEPTED quote | Record ACCEPTED again | Nothing happens | **FAIL** — side effects re-run, date restamped · SA-48 | C/p9 |
| TM-Q26 | (owner's role) | a SENT quote | Record LOST with no reason | Refused | **FAIL** — accepted · SA-49 | D/P4 |
| TM-Q27 | — | a SENT quote, validity 30 days, sent 31 days ago | Run the nightly sweep | EXPIRED | PASS | A/p05 |
| TM-Q28 | (owner's role) | an EXPIRED quote | Record ACCEPTED | Allowed; noted "accepted after expiry" | PASS | A/p05 |
| TM-Q29 | ME | a quote with validity −5 | Save; run sweep | Refused at save | **FAIL** — saved; never expires · SA-43 | B/p1 |
| TM-Q30 | ME | — | `/quote-external` with status ACCEPTED, ₹9 L | Registered as SENT, or refused (D-18) | **FAIL** — ACCEPTED, no approval · SA-04 | A/p04 §3 |
| TM-Q31 | ME | — | `/quote-external` with status "NOT_A_STATUS" | Refused | **FAIL** — stored · SA-04 | B/p7 |
| TM-Q32 | ME | — | `/quote-external`: total 100, lines 10,00,000 | Refused, or total = Σ lines | **FAIL** — stored as typed · SA-04 | B/p7 |
| TM-Q33 | ME | — | Submit `/quote-external` empty | Refused: origin and total | **FAIL** — SENT ₹0 · SA-04 | D |
| TM-Q40 | BDM | rev 0 SENT, rev 1 DRAFT | Send rev 0 | Refused — superseded | **FAIL** · SA-10 | A/p07 §1 |
| TM-Q41 | (owner's role) | rev 0 SENT, rev 1 SENT | Accept rev 0 | Refused — superseded | **FAIL** — and both later ACCEPTED, both pushed · SA-10 | C/p9 |
| TM-Q42 | FIN | rev 0 ACCEPTED, rev 1 exists | Register contract on rev 0 | Refused | **FAIL** · SA-10 | A/p07 §5 |
| TM-Q43 | BDM | an ACCEPTED quote | Revise it | Refused, or an amendment (D-25) | **FAIL** — revised; revision inherits contract number, can go LOST · SA-10 | A/p07 §2 |
| TM-Q44 | BDM | rev 0 and rev 1 exist | Revise rev 0 again | Refused with a sentence | **FAIL** — database error · SA-51 | A/p07 |
| TM-Q50 | ME | quote A with site S | Save quote B with a line pointing at S | Refused | **FAIL** — stored · SA-24 | B/p1 |
| TM-Q51 | ME | quote B | Post quote A's site id in B's sites | Refused; B's sites untouched | **FAIL** — B's own site deleted, edit dropped · SA-24 | B/p1 |
| TM-Q52 | ME | a new quote with two sites | Pair line 2 with site 2 before the first save | Possible | READ — only after saving once · SA-24 | B |
| TM-Q53 | ME | a quote, unit "XYZ" | Save | Refused — not a known unit | **FAIL** — stored · SA-43 | B/p1 |
| TM-Q54 | MA | a BLOCKED customer | ME raises a new quote | Refused | PASS | A/p11 §4 |
| TM-Q60 | ME | currency USD | Print the quote | Amount in words in dollars | **FAIL** — "… Rupees Only" · SA-29 | B/p1 |
| TM-Q61 | MM | a quote with a 10 % discount | Open it as approver | Discount visible as a discount (D-13a) | **FAIL** — a negative line · SA-39 | B/p1 |
| TM-Q62 | ME | 3 × 3,333.33 − 999.99 at 18 % | Save | 9,000 / 1,620 / 10,620 | PASS | B/p1 |
| TM-Q63 | ME | a negative quantity, GST 500 % | Save | Refused, naming the line | PASS (GAP-047) | B/p1 |

## 5. Approvals (A)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-A01 | MM | band L1 ₹0–1 L | Quote exactly ₹1,00,000 | L1 | PASS — inclusive | B/p4 |
| TM-A02 | MM | bands ₹0–1 L and ₹1–5 L | Quote ₹1,00,000 | One band (D-04a) | ◐ both levels required | B/p4 |
| TM-A03 | MM | bands ₹0–1 L and ₹2–5 L | Quote ₹1.5 L (in the gap) | Refused or top band (D-03) | **FAIL** — any approver; direct approve allowed · SA-08 | B/p4 |
| TM-A04 | MM | top band ends at ₹5 L, BD | Quote ₹9 L | Top band's approver (D-03) | **FAIL** — MM approved directly · SA-08 | B/p4 |
| TM-A05 | MM | no rules at all | Any quote | Per D-03 | ◐ direct approval, as documented | B/p4 |
| TM-A06 | MM | a rule for business unit IND only | Quote with business unit blank | Per D-03 | **FAIL** — unmatched → weakest · SA-08 | B/p4 |
| TM-A07 | MM | a ₹1 L quote, GST 18 % | Which value is compared? | Per D-04 | READ — total incl. GST, after discount | B/p4 |
| TM-A10 | L1 BM, L2 MM | L1 rejects | L2 approves | Quote REJECTED; L2 cannot sign | **FAIL** — APPROVED · SA-05 | A/p06 §D |
| TM-A11 | L1, L2 | nothing signed | L2 signs first | Per D-05 | **FAIL** if sequential — L2 signed · SA-05 | B/p5 §D |
| TM-A12 | approver | a step left open; the quote was SENT | Sign the step | Refused | **FAIL** — SENT → APPROVED, never expires · SA-05 | B/p5 §A |
| TM-A13 | approver | a step left open; the quote moved back to DRAFT | Sign the step | Refused | **FAIL** — DRAFT → APPROVED · SA-05 | B/p5 §B |
| TM-A14 | approver | a REJECTED quote resubmitted | Look at the chain | New chain; the old rejection kept in history | ◐ new chain; old one deleted · SA-12 | B/p5 |
| TM-A15 | MM | MM raised the quote | MM approves it | Per D-06 | **FAIL** if separation is required · SA-07 | B/p5 §C |
| TM-A16 | approver | approve, then take it back | Un-approve | Own approval only; later levels reset | PASS | B/p5 |
| TM-A17 | approver | — | Reject with no comment | Refused | PASS | B/p5 |
| TM-A20 | BM office B | band names BM; quote is office A's | Approve, posting only the step | Refused | **FAIL** — approved · SA-06 | D/P7c |
| TM-A21 | BM office B | as TM-A20, posting the quote id too | Approve | Refused | PASS | D/P7b |
| TM-A30 | MM | checklist on, *require all*, one item unticked | Approve the chain step | Refused | PASS | B/p6 |
| TM-A31 | MM | as TM-A30, no rule matches | Approve directly | Refused | **FAIL** — approved · SA-13 | B/p6 |
| TM-A32 | BDM | as TM-A30 | Send | Refused | **FAIL** — sent · SA-13 | B/p6 |
| TM-A33 | ME (author) | checklist on | Tick every item | Ticks record who and when; per D-10 who may tick | **FAIL** — no who/when; author ticks all · SA-13 | B/p6 |
| TM-A34 | MM | ticks done; item wording edited | Reopen the quote | Ticks shown against the item they were made on | **FAIL** — silently lost · SA-13 | B/p6 |
| TM-A35 | BDM | ticks done | Revise | Revision starts unticked | **FAIL** — ticks copied · SA-13 | B/p6 |
| TM-A40 | MA | — | Add a band: min 5 L, max 1 L, no name, no approver | Refused | **FAIL** — saved · SA-25 | B |
| TM-A41 | MA | band names a user later deactivated | Submit a quote in that band | Refused at rule save, or rerouted (D-11a) | **FAIL** — stuck, "pending with" them forever · SA-25 | B/p5 §E |
| TM-A42 | MA | band names COORDINATOR | Coordinator approves | Coordinator can open and sign — or the band refuses that role | **FAIL** — module locked; stuck · SA-25 | B/p5 §E |
| TM-A43 | MM | MM delegates "everything" to BDM | BDM signs MM's step | Per D-11 | **FAIL** — delegation ignored though the screen says "everything" · SA-25 | B/p5 §I |
| TM-A44 | approver | a quote waiting for me | Open **Approvals** | Listed there | **FAIL** — not listed; only at Quotes → To approve · SA-25 | B, D |
| TM-A45 | MM, BM | one quote waiting for BM | Home → "Pending your approval" for each | BM 1, MM 0 | **FAIL** — MM 2, BM 0 · SA-25 | D/recon |

## 6. Project costing (C)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-C01 | estimator | heads 50,000, loadings 9 %, target 20 %, negotiation 5 % | Compute | Rate = 54,500 ÷ 0.8 × 1.05; label shows "target margin 20 %, negotiation allowance 5 % on top" | ◐ arithmetic PASS; shown as 23.81 % margin | B/p3 |
| TM-C02 | estimator | — | Enter a negative cost head | Refused | **FAIL** — accepted · SA-14 | B/p3 §D |
| TM-C03 | estimator | — | Enter a negative one-off cost | Refused | **FAIL** — margin rose to 47 % · SA-14 | B/p3 §D |
| TM-C04 | estimator | rate mode, target + loadings ≥ 100 % | Compute | Refused with a sentence | **FAIL** — falls back to a loss · SA-14 | B/p3 §D |
| TM-C05 | author | a DRAFT costing | Approve own, without submitting | Refused (D-11) | **FAIL** — approved · SA-14 | A/p09 §2 |
| TM-C06 | approver | a costing at −68.7 % | Approve | Warned or refused (D-09) | **FAIL** — approved silently · SA-14 | B/p3 §C |
| TM-C07 | approver | an APPROVED costing | Reopen | DRAFT; approval cleared; logged | **FAIL** — keeps the approver's name · SA-14 | A/p09 §2 |
| TM-C08 | estimator | a DRAFT and a REJECTED costing | Make a quote from each | Refused (D-11) | **FAIL** — both made, repeatedly · SA-14 | A/p09 §3 |
| TM-C09 | approver | a quote at ₹10,000/month, costing loaded ₹59,950 | Approve the quote | Margin shown live; per D-09 | **FAIL** — approved; panel shows stale 23.8 % · SA-14 | B/p3 §F |
| TM-C10 | estimator | costing with business unit IND, owner X, GST 12 % | Make the quote | Quote carries IND, X, 12 %, inclusions | **FAIL** — blank, blank, 18 % · SA-30 | B/p3 |
| TM-C11 | estimator | two quotes with different assumptions | Make both from one costing | Each quote keeps its own costing snapshot | **FAIL** — the link is overwritten · SA-14 | A/p09 §4 |

## 7. Contracts and the handoff to Operations (K)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-K01 | FIN | a DRAFT quote | Register contract | Refused | **FAIL** · SA-09 | C/p2 |
| TM-K02 | FIN | a LOST quote | Register contract | Refused | **FAIL** · SA-09 | C/p2 |
| TM-K03 | FIN | an ACCEPTED quote | Register contract | PENDING contract; quote shows the number | PASS | C/p2 |
| TM-K04 | FIN | TM-K03 done | Register again with a different number | Refused | PASS | A/p08 |
| TM-K05 | OM, BM | a PENDING contract | OM endorses, BM approves | OPEN | **FAIL** — OM "module locked" · SA-20 | C/p2b |
| TM-K06 | BM | a PENDING contract endorsed by BM | BM approves | Refused — approver ≠ endorser | PASS | C/p2b |
| TM-K07 | FIN | fault injected after the contract insert | Register | Nothing saved, or retried cleanly | **FAIL** — orphan PENDING contract · SA-47 | C/p2 |
| TM-K08 | FIN ×2 | an ACCEPTED quote | Two registrations at the same instant | One contract, a clean message | ◐ one contract, a raw error page · SA-51 | A/p08 |
| TM-K10 | FIN | an OPEN contract of 118,000 | Change value to 500,000 | A pending amendment needing two signatures (D-25) | **FAIL** — saved; "edited" · SA-19 | C/p2 |
| TM-K11 | FIN | an OPEN contract | Reopen | Refused (Reopen is for CLOSED / REJECTED) | **FAIL** — PENDING, both signatures wiped · SA-19 | C/p2 |
| TM-K12 | FIN | an OPEN contract | Cancel it | A cancellation status with reason (D-25) | READ — none exists | C |
| TM-K20 | CO | won deal: estimate 2,50,000, accepted quote 1,00,000 | Raise work order | 1,00,000 | **FAIL** · SA-22 | D/H2 |
| TM-K21 | CO | an OPEN contract from a quote | Raise a work order | Customer, contract number (locked), quote, business unit, office, coordinator inherited | PASS | C/p3 |
| TM-K22 | CO | a visit schedule beyond the quote line's man-days or past the contract end | Save | Refused | PASS | C/p3 |
| TM-K30 | CO | quote line 8,000/man-day | Work order rate 9,500 | Defaults to 8,000; a difference is flagged | **FAIL** — 9,500 accepted, flows to invoice · SA-23 | C/p3 |
| TM-K31 | FIN | contract 500,000 | Invoice 1,100,586 against it | Per D-16 (warn or block) | **FAIL** — no check · SA-23 | C/p3 |
| TM-K32 | FIN | quote 1,00,000 + 18 % GST | Register | Contract value stored as 1,00,000 + tax shown separately (D-16a) | **FAIL** — 1,18,000 · SA-23 | C/p2 |
| TM-K33 | FIN | quote: 10 man-days + 4 visits | Register; read contract quantity | Quantity per unit | **FAIL** — 14 · SA-23 | C/p2 |
| TM-K40 | KAM | an OPEN rate contract; a repeat quote reuses its number | Accept and register | Draw-down recorded; contract's source quote unchanged | **FAIL** — contract re-pointed, value not updated · SA-31 | C/p7 |
| TM-K41 | CO | an OPEN contract | Raise a second work order, no new quote | Allowed | PASS | C/p7 |
| TM-K50 | CO | a DRAFT quote | Raise a work order against it | Per D-19 | **FAIL** — accepted · SA-34 | C/p3 |
| TM-K51 | CO | contract number "XX/99" that does not exist | Raise a work order | Refused | **FAIL** — becomes a contract record at allocation · SA-34 | C/p3 |
| TM-K52 | CO | a CLOSED contract | Raise a work order | Refused, saying CLOSED | ◐ refused, "not open yet — needs endorsement" · SA-34 | C/p10 |
| TM-K53 | CO | a CLOSED contract with an existing order | Allocate a job | Refused | **FAIL** — allocated · SA-34 | C/p10 |

## 8. Billing and payment (B)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-B01 | FIN | a closed job | Invoice it | Line from the job | PASS | C/p3e |
| TM-B02 | FIN | a job already invoiced | Invoice again | Refused | PASS | C/p3e |
| TM-B03 | FIN | an open job | Invoice it | Refused unless Finance overrides | READ — `books.php:645` | — |
| TM-B05 | FIN | order 3 man-days at 9,500 delivered as a 2-day job and a 1-day job | Invoice the 2-day job, accepting defaults | 2 × 9,500 = 19,000 | **FAIL** — 28,500 · SA-15 | C/p3e |
| TM-B06 | FIN | two jobs on one 47,790 invoice; issue it | Run the billable reconcile | Events total the taxable value of those jobs | **FAIL** — each set to 47,790 (95,580) · SA-16 | C/p3e |
| TM-B10 | INS → FIN | engineer uploads a travel bill | Invoice the job | Bill offered as a reimbursable line (D-13) | **FAIL** — never reaches invoice · SA-32 | C |
| TM-B11 | FIN | invoice to customer B | Add customer B's job | Allowed | READ — the control case of C/p11 | C/p11 |
| TM-B12 | FIN | invoice to customer B | Add customer A's job | Refused | **FAIL** — accepted · SA-36 | C/p11 |
| TM-B13 | FIN | quote GST 12 % | Invoice the job | GST 12 % by default | **FAIL** — default 18 % · SA-23 | C |
| TM-B14 | FIN | receipt with bank charges | Record | Invoice stays short by that amount; notice shown | PASS (Q-J, 19 Oct) | — |

## 9. Customer data (D)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-D01 | (owner's role) | "Acme Valves Pvt Ltd" on file; quote typed "ACME VALVES PRIVATE LIMITED" | Accept | "Did you mean…?"; one customer | **FAIL** — duplicate created · SA-18 | C/p5 |
| TM-D02 | (owner's role) | quote typed exactly "Acme Valves Pvt Ltd" | Accept | Existing customer reused | PASS | C/p5 |
| TM-D03 | — | TM-D01 done; a loose enquiry "Acme Valves Ltd." | Accept another quote | Linked to the original | **FAIL** — linked to the duplicate · SA-18 | C/p5 |
| TM-D04 | ME | a customer with three sites and two billing GSTINs | Invoice site 3 | Bill-to with its own GSTIN | READ — one GSTIN per customer · SA-38 | C |
| TM-D05 | BDM, KAM | both approach the same customer | Both create deals | Both visible; owner shown; no merge | READ — allowed; no "already worked by" notice | A |
| TM-D10 | ME | a BLACKLISTED customer | Raise enquiry, quote; approve; accept; register; work order; draft invoice | Per D-12 | **FAIL** — every step succeeds · SA-17 | C/p5 |
| TM-D11 | ME | an INACTIVE customer | Same as TM-D10 | Per D-12 | **FAIL** — every step succeeds · SA-17 | C/p5 |
| TM-D12 | CO | a BLOCKED (on hold) customer | Raise a work order | Per D-12 | ◐ passes with a warning · SA-17 | C/p5 |
| TM-D13 | BDM | a BLOCKED customer with a SENT quote | Accept, register | Per D-12 | **FAIL** — accepted, registered · SA-17 | A/p11 §4 |

## 10. Permissions and scope (P)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-P01 | BDM, KAM, MM, ME | default rights | Open Leads, Opportunities, Pipelines | Per D-01 | **FAIL** — 403 · SA-11 | `lead-roles.out` |
| TM-P02 | BM, FIN | default rights | Same | Per D-01 | **FAIL** — 403 · SA-11 | D |
| TM-P03 | INS | default rights | Open Sales | 403 | PASS | D |
| TM-P04 | BDM | default rights | Register a contract (direct POST) | Refused | PASS | C/p2 |
| TM-P05 | FIN | default rights | Revise a quote | Refused | PASS | D/P18 |
| TM-P06 | BM | default rights | One-click approve | Refused | PASS | D/P11d |
| TM-P07 | ME | default rights | Open a costing; read its margin | Per D-09a (who sees margin) | **FAIL** if margin is restricted — shown · SA-33 | D/P13 |
| TM-P08 | CO | default rights | Create and reopen a costing | Per D-11 | ◐ allowed via the hiring edit right | D/P13 |
| TM-P09 | BD | default rights | Apply an industry template | Refused — Settings is MA/Admin (D-29) | **FAIL** — applied; accreditation switched off · SA-21 | D/I1 |
| TM-P10 | BDM office B | a costing saved with no office | Open the costing list | Not listed | **FAIL** — visible to every branch · SA-33 | D/S1 |
| TM-P11 | BDM office B | BDM saves a quote with no office chosen | Open own quote | Visible to its creator | **FAIL** — filed under the Ahmedabad office, lost to its creator · SA-33 | D/S2 |
| TM-P12 | ME, own business unit blank, scope OWN | quotes in two business units | Open the register | Nothing, until the unit is set | **FAIL** — sees ALL · SA-33 | D |
| TM-P13 | BM | default rights (delete granted) | Edit an enquiry | Per matrix | ◐ allowed — delete implies edit · SA-33 | D/perm_probe.tsv |
| TM-P14 | any | a quote id from another tenant | Open `/quote?id=` on this tenant | Not found | UNTESTED here — covered by GAP-049 and the per-tenant database | — |

## 11. History and accountability (H)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-H01 | any | a quote | Each of: direct approve, send, accept, lose, reject, expire | One history line each: who, when, from, to, reason | **FAIL** — none of the six · SA-12 | A/p10 |
| TM-H02 | MM | — | One-click approve | Audit row | **FAIL** — none · SA-12 | D/P11c |
| TM-H03 | BD | a lead, a deal, an enquiry, a costing | Edit each | Logged | **FAIL** — nothing · SA-12 | A/p10 |
| TM-H04 | MA | a band | Change it | Logged | READ — nothing writes it · SA-25 | B |
| TM-H05 | MA | a direct approval where a rule matched | Master bypasses | Logged as a bypass | PASS | A/p06 |

## 12. Reporting (R)

Dataset for R01–R08: `../evidence/sales-audit/D/recon_read.json` — nine quotes
across six months (one revised and re-accepted, one LOST, one EXPIRED by the
sweep, one in the previous year, one external, one pending), five deals, two
enquiries.

| ID | Measure | Expected | Today | Ev. |
|---|---|---|---|---|
| TM-R01 | `/crm-reports` raised / won value | 7 / 1,88,800 | PASS | D/recon |
| TM-R02 | `/crm-reports` monthly table, June | the row of the quote first raised in June | **FAIL** — row missing; dated by the revision · SA-28 | D/recon |
| TM-R03 | Home "Sales won (FY)", "Won this FY" | 1,88,800 / 2 | **FAIL** — 4,24,800 / 3, all time · SA-28 | D/recon |
| TM-R04 | Win rate on three screens, same day | one definition per measure | **FAIL** — 50 / 60 / 50 % · SA-28 | D/recon |
| TM-R05 | Command Centre "Committed" | 4,24,800 | **FAIL** — 4,83,800, superseded revision counted · SA-10 | D/recon |
| TM-R06 | `/crm-dashboard` "Became opportunities" for BM office A | 100 % | **FAIL** — 200 %, unscoped · SA-28 | D/recon |
| TM-R07 | "Due to close in 30 days" | 1 | **FAIL** — 2, includes overdue · SA-28 | D/recon |
| TM-R08 | Weighted forecast, open deals, deal win rate, cycle time | as computed by hand | PASS | D/recon |
| TM-R10 | Quote-to-order conversion | a report | READ — none exists · SA-40 | — |
| TM-R11 | Quoted → contracted value | a report | READ — none exists · SA-40 | — |
| TM-R12 | Quotes nearing expiry (next 7 days) | a list | READ — none exists · SA-40 | — |
| TM-R13 | Enquiries needing a response today | a list | READ — only "older than SLA" · SA-40 | — |
| TM-R14 | Margin by customer and business unit | a report | READ — none exists · SA-40 | — |

## 13. Templates, configuration, integrations (T, X)

| ID | Persona | Preconditions | Action | Expected | Today | Ev. |
|---|---|---|---|---|---|---|
| TM-T01 | MA | a new company | Apply *Inspection* | Pipelines, stages, list values, 17020 pack on | READ — `industry_apply()` | D |
| TM-T02 | MA | Inspection applied | Add the *Staffing* pipelines alongside | Both funnels available; wording and accreditation unchanged (D-29) | READ — one template per company; applying replaces | D |
| TM-T03 | BD | Inspection applied | Apply *Manufacturing* | Refused (TM-P09), or pipelines only | **FAIL** — 17020 pack OFF, wording replaced · SA-21 | D/I1 |
| TM-T04 | MM | — | Edit costing loading defaults | A settings screen | READ — none exists · SA-46 | B |
| TM-X01 | MM | quote rev 0 accepted and pushed; rev 1 accepted | Look at the Books outbox | One document for the deal (D-20) | **FAIL** — two documents, no retraction · SA-35 | C |
| TM-X02 | MM | Books unreachable | Accept a quote | Acceptance completes; push queued and retried | READ — best-effort propagation; outbox tested only in dry run | C |
| TM-X03 | MM | identical payload queued twice | Run the outbox | Sent once | PASS (dry run) | C |

---

## 14. Counts

| | Rows | PASS | FAIL | ◐ partial | READ / UNTESTED |
|---|---|---|---|---|---|
| Leads | 10 | 3 | 7 | 0 | 0 |
| Opportunities | 11 | 2 | 7 | 0 | 2 |
| Enquiries | 10 | 3 | 5 | 1 | 1 |
| Quotations | 44 | 9 | 32 | 2 | 1 |
| Approvals | 29 | 5 | 20 | 3 | 1 |
| Project costing | 11 | 0 | 10 | 1 | 0 |
| Contracts and the handoff to Operations | 24 | 6 | 15 | 2 | 1 |
| Billing and payment | 10 | 3 | 5 | 0 | 2 |
| Customer data | 9 | 1 | 5 | 1 | 2 |
| Permissions and scope | 14 | 4 | 7 | 2 | 1 |
| History and accountability | 5 | 1 | 3 | 0 | 1 |
| Reporting | 13 | 2 | 6 | 0 | 5 |
| Templates, configuration, integrations | 7 | 1 | 2 | 0 | 4 |
| **Total** | **197** | **40** | **124** | **12** | **21** |

Counts are recomputed by `../evidence/sales-audit/count_matrix.py` from the
tables above, so they cannot drift from them.
