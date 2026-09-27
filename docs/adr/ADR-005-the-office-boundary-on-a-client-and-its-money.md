# ADR-005 — The office boundary on a client record, and on the money

**Status:** Accepted · 2026-09-27
**Related:** ADR-004 (the contract number), and the office-scoping work that
locked the client and vendor *registers*.

## What was found

A testing session raised four things. Each was reproduced on the live database
before a line was written.

### 1. A Mumbai coordinator could rewrite an Ahmedabad client

> "When Tested the coordinator is able to edit the client details associated with
> the ahmedabad office. Wherein coordinator is from Mumbai so this must not be
> possible."

Confirmed, and worse than reported. Logged in as a Mumbai-only coordinator, the
client was renamed and its **credit terms set to 999 days**. A contact and a site
address were planted on it. Ahmedabad would never have known.

The registers had been scoped long ago. The **record** never was: the edit door
asked *what role are you*, never *whose client is this*. The register hid the
client from Mumbai, but search still found it, and from there every form opened.

This is a different and more serious class than the leak ADR-004 closed. That one
was a branch *seeing* another branch's figures. This was a branch **silently
rewriting** another branch's commercial terms.

### 2. Another office could read the client-billable figure

> "Invoice value and bill value if call allocated to other office shall not be
> seen at any cost by the other office. Yes they can check the credit they will
> get. Nothing else."

Mostly already correct, and worth saying so: the work-order screen, the job
screen, both registers, To-bill and call-profit all showed the executing office
its credit and nothing more — for a coordinator and for a branch manager alike.

**The Reports screen did not.** A Mumbai branch manager saw Ahmedabad's
₹987,654 in *Top 10 clients by revenue* with the customer named beside it, in
*Revenue by contract number*, and in the business-unit revenue table.

### 3. "Once reassigned it cannot be again reassigned"

Not true, and that is the point. Reassigning the same job twice in a row works
and always did. What happens is that the **Reassign dropdown opens on the person
already assigned**, so pressing the button without changing it answers *"Choose a
different resource to reassign to."* — which reads exactly like a lock.

### 4. "…or another update cannot be done"

A separate, real mechanism: a job **locks itself two days after its inspection end
date**. Dates, man-days, expenses and credit freeze, and the Edit button goes. On
the live data **35 of 115 open jobs were already locked**.

## The decisions

### Reading is a spectrum; writing is not

The owner's ruling: *"Client basic details can be seen else then the contact and
commercial parts."*

| | Another office | The owning office |
|---|---|---|
| Who the company is (name, code, type, industry, status, group) | visible | visible |
| Contact people, phone, e-mail, site addresses | hidden | visible |
| Payment terms, credit days, contracts, purchase orders, registrations, internal notes | hidden | visible |
| Work orders on the record | only those it is part of, by the existing two-office rule | its own |
| **Any change at all** | **refused** | allowed |

Hiding the record outright was rejected: the same company is often worked on by
two branches, and a coordinator arranging a visit has to know who they are
dealing with.

`partner_view_level()` answers the read question and `partner_can_write()` the
write question. They are **deliberately two functions**. Answering both with one
flag is how a screen that correctly hid a figure still accepted a save.

A party with **no branch set stays fully visible and writable** — the same rule
`partner_office_sql()` already applies. Every existing client is unassigned until
somebody sets the field, so without this the change would have locked the entire
customer master on the day it shipped.

### The restricted rows are never loaded

The handler does not fetch contacts, addresses, registrations, contracts,
purchase orders or notes for another office. A template that forgets one line
leaks; a handler that never reads the rows cannot. The tabs that would now be
blank are dropped too — an empty tab says "there is nothing here", which is a
different and misleading statement — and the screen says plainly whose client it
is and who to ask.

### Every door asks, including the ones that render nothing

The edit form was the reported door. Also closed: every sub-form behind it
(contact, address, registration, contract, relationship, note), setting the
parent company, and the two JSON endpoints that hand out **site addresses** and
**purchase orders** without drawing a page at all.

### Reports read the books of the office reading them

`job_profit()` has always accepted an office id and done the right thing with it.
The report simply never passed one, so every total was the **company's** view
handed to whoever opened the page. One argument moves credit, revenue, the
business-unit split and the per-office split at once. Invoicing and payment
totals stay with the office that raises the invoice: an office that only carried
the work out is owed a credit, not paid by the client.

The two offices' shares add up to the invoice exactly — no rupee is
double-counted or lost. That is asserted, because a split that does not reconcile
is worse than no split.

### The reassign box opens on nobody

It no longer pre-selects the current holder, and no longer offers them at all —
reassigning somebody to themselves is not a thing to do. Who holds it is said
beside the box instead. The engine is untouched: it still refuses only the no-op,
and still writes every move to history.

## What was NOT changed, and why

**The job lock, and who may reopen it.** The owner approved letting a branch
manager reopen a locked job. On reading the live role map, **they already could**
— `BRANCH_MANAGER` carries `workforce.report.approve`, which `can_unlock_job()`
has always accepted. A change naming the role explicitly was written, then
reverted: it granted the branch manager nothing it did not have, while quietly
adding `BRANCH_APP_MANAGER`, which nobody discussed.

So the list stands as it was, and a test now guards it: the lock asks for a
**permission**, never a role name. A role list in a permission check is precisely
how a right gets granted to a role nobody talked about.

The coordinator still cannot reopen. If the person who missed the deadline can
undo it, there is no deadline.

## Consequences

- One more question on every client screen and every client write. Measured on
  the work-order screen at ~17 ms against a ~340 ms page; the client screen is
  the same shape.
- A branch that genuinely needs another branch's client contact must ask that
  branch. That is the intended cost.
- 52 assertions, 15 mutations, all caught.
