# 62 · A customer's GSTIN, PAN and billing locations

**Owner's decision (21 Nov 2026):** D1–D4 "as recommended".
**Your words:** *"When we win the customer it shall be mandatory to have GSTIN and PAN and that shall be matched to central customer database to avoid duplication; also a customer can have one PAN but multiple billing states with respect to their branches."*
**Status:** built and tested on a working branch (`claude/gstin-billing-0gqe8n`); it goes into the next test-copy update with the rest of the Sales work. Nothing is live.

The example used below is made up: **TB Refinery Ltd**, PAN **AAACT1234F**, registered in Gujarat (main), Maharashtra and Tamil Nadu.

---

## 1. What changes for your team, in one paragraph

A customer is now **one PAN**. When a customer is won, its **GSTIN and PAN** are asked for — typing the GSTIN fills the PAN and the state, and a typing mistake in the GSTIN is caught at once. If the customer is registered in more than one state, each further state is a **billing location** of the same customer (state + GSTIN + billing address) — never a second customer. A quotation, a contract and an invoice say which location they are billed to, and CGST + SGST or IGST follows it. Every invoice keeps its own copy of who it was made out to, so editing a customer later never rewrites a bill already sent.

## 2. When the GSTIN and PAN are asked

| Where a customer is won / put on file | What happens now |
|---|---|
| New client form (and its "+ Add new" pop-up) | GSTIN and PAN required for a client (a vendor-only record: optional) |
| Lead → customer | Required when the lead becomes a **new** client (not when it is attached to one already on file) |
| Won deal → customer | Required |
| Quotation accepted for a name not on the client list | Asked on the "Mark accepted (won)" button itself |
| Branch company | GSTIN required; the same PAN as the group becomes a billing location instead (see §4) |
| Excel import | A new client row without them is not added — the preview says why |
| Public sign-up (marketplace) | Checked if typed (check digit, PAN inside GSTIN), not required — a sign-up is not yet a won customer; it is asked at its first invoice (D3) |

**Example.** Typing `24AAACT1234F1ZX` fills PAN `AAACT1234F` and state Gujarat. Typing `24AAACT1234F1ZA` (last character wrong) is refused: *"That GSTIN has a typing mistake (its last character does not match the rest) — please copy it again from the GST certificate."* Typing a PAN that is not the one inside the GSTIN is refused, with both shown.

**The three exceptions (D2)** — chosen with one click on the same form:

| Exception | What is asked | Example |
|---|---|---|
| Not GST-registered | PAN + reason | Nova Castings, PAN AAJFN7777P — "below the GST threshold" |
| Overseas customer | Country + their own tax number | a Dubai company, TRN 100200300400003 |
| Government department without a PAN | Reason (GSTIN if they have one) | "State PWD — no PAN issued"; listed for Finance |

## 3. Matched against the central customer list

| You type | What the system finds | What happens |
|---|---|---|
| GSTIN `24AAACT1234F1ZX` | It is TB Refinery's GSTIN | **Refused**: "This GSTIN already belongs to … Open that client instead." |
| GSTIN `27AAACT1234F1ZR` (Maharashtra) | Same PAN as TB Refinery | **No second client.** "TB Refinery Ltd already has this PAN, so it is the same company. Add Maharashtra as a new billing location of it instead." — tick the box and save; you land on TB Refinery with the new location. |
| Name "TB Refinery Limited" | A similar name | A warning; tick "it is a different company" to go on |
| Name "Sunrise Valves" | An open lead "Sunrise Valves Pvt Ltd" | Flagged as a possible duplicate (Pvt Ltd, Ltd, LLP, Co, Inc and punctuation are ignored — your Q-02) |

Only someone who may edit TB Refinery's record can add a location to it.

## 4. Billing locations

On the client's page a new card, **Billing locations**, lists the main location (the record's own GSTIN and address) and every further state. **+ Add a billing location** asks for the GSTIN in that state, a name and the billing address; the GSTIN must carry the client's PAN. A location can be renamed or **stopped for new bills** (bills already raised keep it).

| Location | State | GSTIN | Tax from our Gujarat branch |
|---|---|---|---|
| Main | Gujarat | 24AAACT1234F1ZX | CGST 9% + SGST 9% |
| Mumbai plant | Maharashtra | 27AAACT1234F1ZR | IGST 18% |
| Chennai | Tamil Nadu | 33AAACT1234F1Z… | IGST 18% |

- **Quotation:** for a client with more than one location, the form shows **Billing location**; the tax preview follows it.
- **Contract:** registered from a quotation, it is billed where the quotation was.
- **Invoice:** raised from that quotation or contract, it starts at that location; on a draft, **Billed to** lets you change it, and the place of supply, the GSTIN printed and CGST + SGST / IGST are re-read.
- **Branch company:** "Create branch company" with the same PAN as the group now adds a billing location instead of a second record. A different PAN (a separate legal company in the group) is still a separate branch company.

## 5. Bills never change afterwards

Every invoice now keeps a copy of who it was made out to — name, GSTIN, state and billing address. It is refreshed while the invoice is a draft and frozen when it is issued; the print reads the copy.

**Example.** An invoice to TB Refinery prints "Plot 7, GIDC Vadodara Gujarat 390010 — GSTIN 24AAACT1234F1ZX". Renaming the client and changing its address afterwards: the invoice prints exactly the same.

Invoices raised **before** this update have no copy; they print the customer record exactly as they did yesterday.

## 6. Existing customers (D3) and existing branch companies (D1)

- **Customers without GSTIN and PAN** (and no exception) are listed on Sales home → Data health ("Customers without GSTIN and PAN — asked at their next invoice"). The next time someone edits such a client, the form asks for them; its next invoice cannot be **issued** until they are on the record ("… has no GSTIN and PAN on file … Add them on the client's record first."). Old invoices are untouched. A client saved with a GSTIN but no PAN counts as complete — the PAN is inside the GSTIN.
- **Government departments without a PAN** are counted separately for Finance.
- **Existing branch companies with the same PAN** show a note on their page: *"See what moving it under the main client would do."* The preview lists everything that would move (quotations, contracts, invoices, contacts, addresses …). Only when someone presses **Move under …** with a reason:
  1. the branch's invoices take a copy of exactly what they print today;
  2. its GSTIN becomes a billing location of the main client;
  3. its quotations, contracts and invoices point at that location, and everything else moves to the main client;
  4. the branch record is kept, marked as moved, with a note saying where it went — nothing is deleted.
  Who may: the people who may already merge duplicate records, and who may edit both records. A branch company with a different PAN cannot be moved.

## 7. What was reused, and what was added (Reuse Gate)

| Need | Existing piece reused | Added |
|---|---|---|
| A customer's identity | `business_partners.gstin / pan / state` | 4 columns on the same table: tax status, reason, country, foreign tax number |
| Billing locations | `partner_addresses` (already holds BILLING / BRANCH addresses) | 3 columns: GSTIN, state code, "is a billing location" |
| Which location a document is for | — | `billing_address_id` on quotations, contracts, invoices |
| The invoice's copy | `invoices.gstin`, `place_of_supply` (already frozen) | 5 columns on invoices: the copy of name, GSTIN, state, address, and a flag |
| GSTIN check digit | `commercial_gstin_problem()` (already used for platform billing) | — |
| PAN / state from GSTIN | `pan_from_gstin()`, `state_from_gstin()` | — |
| Duplicate detection | `find_duplicate_partner()`, `normalize_name()` | a "same PAN, new GSTIN = location" answer |
| Moving a branch company | `dd_merge()` (the duplicate-merge tool) | the preview, the invoice copy and the location step around it |
| Who may | the client-edit rule, the merge rule | **no new permission** |

**No table is added.** Maintenance cost: one library file (`lib/custid.php`) and two small form pieces, all covered by one test file.

## 8. Problems found and fixed on the way (register notes)

| ID | What was wrong | Now |
|---|---|---|
| DEF-28 | Editing a client never checked its GSTIN | Checked, with the check digit |
| DEF-29 | Excel import overwrote the main company's GSTIN with a branch row's | The row becomes a billing location; the main GSTIN is never overwritten |
| DEF-30 | The invoice "not ready" check read a column that does not exist (PHP warning) | Read safely |
| DEF-31 | Data health's GSTIN count included vendors and checked only the shape | Customers only, check digit |
| DEF-32 | A branch company could not be edited once its main record had a PAN | Allowed inside one group |
| DEF-33 | "Set branch" on a draft invoice led nowhere (route not wired) | Wired |
| DEF-34 | Printed invoices read the customer's current details | New invoices keep their own copy |

## 9. Tests

- **`tests/test_gstin_billing.php` — 72 of 72 pass.** It covers: the check digit; PAN inside the GSTIN; the four kinds (registered / not registered / overseas / government); matching by GSTIN, by PAN (location offer) and by name (Pvt Ltd ignored, lead against lead); adding, refusing and stopping locations; tax following the location (Gujarat → CGST+SGST, Maharashtra → IGST) on the quotation preview and the invoice; the invoice copy and an unchanged print after the client is edited; an old invoice printing exactly as before; D3 at the next invoice and in Data health; lead → customer, won deal → customer and an accepted quotation; the Excel import (location, never overwrite, refusal of a new client without identity); moving a branch company (preview, refused without a reason, audit trail, its old invoice printing exactly as before, a different PAN refused); and who may add a location.
- **Related test files — 291 files** (clients, customer pages, books, invoices, Tally, receivables, billing units, Sales stages 1–6, leads, deals, quotations, approvals, contracts, imports, duplicates, marketplace sign-up, permissions). Every failure was compared with the version before this batch:
  - **11 files** needed their test data to follow the new rule (a new customer now brings its GSTIN / PAN or a recorded exception; made-up GSTINs given their real check character). None was weakened; all pass.
  - **One mistake of this batch** was caught this way and fixed: a duplicate GSTIN on a *retired* record stopped saying which retired record it matched. It says so again.
  - **4 files** fail exactly as they did before this batch (one check each, unrelated: permission labels, term count, organisation structure, a source-text check). They are listed in the register notes.
- **In a real browser** (throwaway copy): new client with GSTIN filling PAN and state; the same-PAN offer and the location being added to the same client; a wrong check digit refused; the Billing locations card; the invoice "Billed to" picker re-reading IGST; the move preview. Four changed screens at 8 widths (1920 → 360 px): no sideways scroll, no PHP message.

## 10. Your checks on opstest (after the update is installed)

1. Directory → New client: type a GSTIN — PAN and State fill in. Change the last character — saving is refused with a plain reason.
2. Choose "Not GST-registered": PAN and a reason are asked; save.
3. Add a second client with the same PAN but another state's GSTIN — you are offered "add as a billing location"; tick and save — you land on the first client, which now shows two locations.
4. On that client, add a third location from the Billing locations card.
5. Make a quotation for it, choose the Maharashtra location — the GST preview says IGST.
6. Raise the invoice — it starts at Maharashtra; switch "Billed to" to the main location — CGST + SGST.
7. Print it, then edit the client's address — print again: unchanged.
8. Convert a lead to a new client without a GSTIN — refused; with one — done.
9. Open a client that has no GSTIN and PAN: Sales home → Data health counts it; its next invoice cannot be issued until they are added.
10. On a branch company with the same PAN as its main client: open "see what moving it would do", read the list, press Move with a reason; its old invoice prints as before.
