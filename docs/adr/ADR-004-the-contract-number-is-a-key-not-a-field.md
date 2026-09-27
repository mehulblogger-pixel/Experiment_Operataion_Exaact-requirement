# ADR-004 — A contract number is a key, not a field

**Status:** Accepted · 2026-09-27
**Supersedes:** the comment in `lib/contracts.php` that said the contract number
"is never changed here — it identifies the contract and every call/job join reads
it, so renaming would orphan the history."

## The complaints this answers

Three, raised together by the business owner:

1. "Contact number duplicate creation shall not be allowed. Also Contract
   details shall be fetched automatically from Purchase order and other details
   previously filled. Hence no duplicate entry is required. If contact number is
   edited there must be an option to delete and edit it."
2. "I feel that contract number generation must be single click taking all
   required entry automatically."
3. "When we have already entered the details in the Client under PO and contract,
   again during inspection what inspection must be prefilled if that information
   is already there. This shall be two way if it is not there then it must be
   linked to appropriate PO and Contract accordingly."

## The one root cause

The contract number was being treated as **a field somebody types** when it is in
fact **the join key that threads the whole spine together** — quotation → purchase
order → work order → job → report → invoice → engagement. Everything the owner
complained about follows from that single confusion:

- Because it was typed, it got typed **twice** (two contracts, one deal, the work
  and the billing split across both).
- Because it was typed, it got typed **wrongly** — and because it was the join
  key, a wrong one could **never be corrected**, so it was carried by every
  invoice for the life of the contract.
- Because nothing read it **forward**, every screen after it asked again for facts
  the purchase order and the quotation already held.

So this is one decision, not three fixes.

## The decision

### 1. The number is generated, and the data is inherited

Registration is **one button**. It generates `BRANCH/C/FY/NNNNN` and reads
everything else off what is already recorded, in this order of authority:

| Field | First choice | Then | Then |
|---|---|---|---|
| Value | the client's purchase order | the quotation total | — |
| Start / end date | the purchase order | the day the quotation was accepted | today (start only) |
| Subject | the purchase order title | the quotation subject | — |
| Billing branch | the quotation's office | the client's home branch | the user's own office |

The purchase order wins over the quotation because **the PO is the document that
actually commits the money**; the quotation is what we asked for.

**No end date is ever invented.** A quotation's validity period is how long the
*price* stood, not how long the work runs. Guessing it would put a wrong expiry
on a live contract and block scheduling.

The screen states which document each figure came from. A value that appears by
itself is a value nobody checks.

Ambiguity is never resolved by guessing: two unattached purchase orders on the
client means **neither** is assumed, and both are offered.

### 2. A duplicate is named before it is created, at three different strengths

| Situation | Response |
|---|---|
| Same number, **different** party | **Refused.** Every expiry, quantity and invoice figure downstream would read the other contract. |
| Longer than 40 characters | **Refused.** `invoice_lines.contract_number` is `VARCHAR(40)`; a longer number is stored whole in one place and cut short in another, and the join silently stops matching. |
| Same number, **same** party | **Allowed, and said out loud.** This is a rate contract being drawn down again. The existing row is reused; nothing new is created. |
| Same number with different punctuation or case, same party | **Warned.** `AHM/C/25-26/42` and `ahm-c-2526-42` are one human intention. This is the case the system used to accept in silence. |
| Different number, same party, overlapping dates | **Warned.** This is how one order gets registered twice under two numbers. |

A near-miss is warned about rather than refused because it is occasionally
deliberate, and refusing a deliberate one leaves the person with nowhere to go.
The warning is answered by the server **while the number is being typed**
(`/contract-no-check`), not after the form has been submitted.

The check lives in **one function** — `contract_duplicate_check()` — used by all
three registration paths (from a quotation, for a group company, and from a deal
won without a quotation), so a duplicate cannot enter through a side door.

### 3. A wrong number can be corrected, and everything moves with it

The old reason for refusing this was correct and was also the reason it had to be
built: the number is what everything is filed under. So the correction moves the
contract **and every record filed under it, in one transaction, or none of them**:

`quotations`, `calls`, `jobs`, `invoices`, `invoice_lines`, `billable_events`,
`cost_allocations`, and `engagements.engagement_key`.

That last one matters more than it looks: the engagement is keyed on the number
itself and a nightly reconciliation flags any row whose engagement key and
contract number disagree. Miss it and every job under the contract is reported
broken the next morning.

The list is held in **one place** (`contract_no_tables()`) and the test battery
reads it back against the live schema in both directions — every entry must
exist, and no table may carry a contract number without being in the list. A
table added next year is caught without editing a test.

**What the correction will not do is merge two contracts.** If the new number is
already in use, it is refused. "Correct a typo" and "merge two files" are
different decisions and only the first is safe to do without asking.

The screen states what the correction would move **before** the button exists, so
nobody discovers afterwards that they moved forty invoices.

### 4. The work order inherits, and can be linked back

Forward: a work order raised from a contract opens with its purchase order, the
line still open on it, and the **rate the client actually agreed**. Only what is
unambiguous is filled — one order on the contract, one line with balance left.
Two of either and nothing is assumed, because a guess here becomes a wrong rate
on a real invoice. A fully consumed line is never offered.

Backward: a work order with no contract or order on it offers the right ones to
attach in a click — only that client's **open** contracts, and only ones with
time and quantity left, because the scheduling gate would refuse the rest and
offering them is a dead end. Linking the order also joins it to the contract from
the other end, so the *next* work order fills itself in.

**Both directions close once an invoice has gone out.** The invoice carries its
own copy of the number; changing the work order's number afterwards would leave
the two disagreeing — the exact class of mistake this whole decision exists to
prevent. The remedy for a wrongly numbered invoice is a credit note, not a
dropdown. This is enforced at the write, not only hidden from the screen.

## What was deliberately NOT changed

**The two signatures stay.** A manager endorses that the number is genuinely
needed; the branch manager approves that the branch will carry it; the same person
cannot do both unless they are the Master Admin standing in for a one-person
branch. The owner was asked directly and chose to keep both.

"One click" is about the **data**, never the approval. A newly registered contract
is still `PENDING` and the order is still held back from operations until it is
`OPEN`. Any change to that is a change to `docs/02-permission-matrix.md` and has
to be asked for.

## Consequences

- `CONTRACT_NO_MAX = 40`, asserted against the real narrowest column on both
  engines. Widening any of those columns without widening the rest is now a test
  failure rather than a silent data-truncation bug years later.
- The cascade makes a renumber an O(tables) write. At the volumes in question
  (tens of records per contract) this is irrelevant; if a contract ever carries
  hundreds of thousands of rows this becomes a background job.
- Three registration paths now share one duplicate check, so a change to the
  policy is a change in one function.
- 112 assertions, 17 mutations, all caught.
