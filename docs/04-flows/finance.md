# Flow — `FINANCE`

**Device:** desk, laptop, all day in their own screens.
**Landing screen:** the Dashboard, reordered to put **money first** — an ordering
specific to this role (`phpapp/views/dashboard.php:65`).

The accountant. Company-wide scope (`offices => 'ALL'`, `sbus => 'ALL'` —
`phpapp/lib/access.php:388`), which is correct for an accounts function.

---

## Main flow — from won quotation to money in

```mermaid
flowchart TD
  A([Sign in]) --> B[Dashboard — money first]
  B --> C{What is waiting?}
  C -->|"a quote was accepted"| D[Register the client<br/>and the contract]
  C -->|"jobs closed, not billed"| G[Billing workspace]
  C -->|"invoices unpaid"| J[Receivables]
  C -->|"month end"| L[Reconcile & export]

  D --> E([Handoff → BRANCH_MANAGER<br/>endorses and opens it])
  E --> F([Handoff → coordinator<br/>may now raise calls])

  G --> H[Raise the GST invoice<br/>from the job]
  H --> I([Handoff → client])
  J --> K[Record receipts,<br/>allocate against invoices]
  L --> M[Inter-office credit<br/>reconciliation]
  L --> N[Export to Tally]
```

### Walkthrough

1. **Sign in.** Money panels come first. The operational widgets — "raise a call",
   "pending scheduling", "team availability" — are **deliberately not shown to you**,
   because they are gated on holding a real operations permission
   (`phpapp/views/dashboard.php:62`). That was a deliberate fix; it is not something
   missing.

2. **Register the contract.** When a quotation is accepted it is handed to Accounts
   as a pending task (commit `37e46a7`). You hold `crm.contract.register` and
   **nobody else does** (`phpapp/lib/access.php:388`). This is the single narrowest
   handover in the whole system: until you act, operations cannot begin.

3. **Hand it on.** Registering is not the end — the contract must then be **endorsed
   and opened** by the Branch Manager before a call can be raised against it
   (`phpapp/lib/ops.php:3569-3574`). That two-person control is the point.

4. **Bill the closed jobs.** A closed job with no invoice raised sits in the pending
   list (`phpapp/lib/ops.php:1302`). Raising a GST invoice from a job is
   **idempotent** — pressing it twice does not produce two invoices (commit
   `ece1f48`), and each voucher line carries a note explaining what it is.

5. **Chase the money.** Receipts, allocation against invoices, ageing, credit notes.
   A credit note is capped at what the invoice was for, and cancelling is refused
   once anything has settled — the number is kept so the GST series has no gaps
   (`phpapp/PENDING.md:940`).

6. **Reconcile inter-office credit.** You hold `finance.reconcile`
   (`phpapp/lib/access.php:388`). When one branch sells and another executes, this is
   where the split is settled.

7. **Export to Tally.** Invoices go over as Sales vouchers, payments as Receipts with
   an "Agst Ref" that knocks the invoice off, credit notes against the original
   invoice. **Exporting is remembered**, so the same voucher is never imported twice,
   and Undo brings it back and clears the stamp (`phpapp/PENDING.md:1345-1364`).

### 🔁 Handoff points

- **Sales → you.** *An accepted quotation arrives as a "contract to register"
  task.*
- **You → the Branch Manager.** *A registered contract goes to them to endorse and
  open.* You cannot open it yourself.
- **The coordinator → you.** *A closed job with no invoice appears in your billing
  queue.*
- **You → the client.** *The issued invoice.*
- **You → Tally.** *The month's exports.*

---

## Click count — the most common task

**Task: raise the GST invoice on a closed job.**

| Step | Clicks |
|---|---|
| Dashboard → the awaiting-billing card | 1 |
| Open the job | 1 |
| **Raise GST invoice** | 1 |
| Confirm | 1 |

**≈ 4 clicks.**

*How this was counted:* discrete clicks on the shortest path, excluding review time
and excluding any correction to the invoice lines. Because the action is idempotent,
a mistaken second click costs nothing.

---

## ⚠ Two things about this role that need a decision

Finance was **deliberately removed from the operations management tier** (commit
`ff0b94a`, `phpapp/lib/access.php:21-27`). That was the right call — it stopped an
accountant being shown "raise inspection call". But it had two consequences that were
probably not intended.

### 1. You are shown an Operations menu you cannot use

You hold view access to Calls, Jobs and Vouchers, which is enough to put **Operations**
in your sidebar (`phpapp/views/layout_top.php:128`). But the voucher register demands
the management tier, which you are no longer in — so the screen refuses you
(`phpapp/lib/ops.php:4863`).

The menu offers something the application then declines. Your own `PENDING.md` names
this exact pattern as item **B1** and calls it "the smallest fix on this list"
(`phpapp/PENDING.md:2285-2287`). This is a new instance of it.

### 2. You cannot mark a voucher paid — but a coordinator can

Marking a voucher `PAID` requires the management tier
(`phpapp/lib/ops.php:4970`). You are not in it. A `COORDINATOR` is.

```mermaid
flowchart LR
  A["Coordinator<br/>prepares"] --> B["Coordinator<br/>submits"]
  B --> C["Coordinator<br/>approves"]
  C --> D["Coordinator<br/>marks PAID"]
  D --> E["Finance<br/>actually pays the money"]
  E -.->|"but cannot record it"| D
```

**The control is inverted.** The people who prepare the claim can approve it and
record it as paid; the person who actually moves the money cannot. Both points are in
`99-gaps-and-risks.md` with recommended fixes.

---

## What Finance cannot do

| You need to… | Ask |
|---|---|
| Raise a call or allocate a job | `COORDINATOR` or `OPERATION_MANAGER` — correct, and should stay that way |
| Open a contract you have registered | `BRANCH_MANAGER` — the two-person control |
| Approve a quotation | `MARKETING_MANAGER` — you register *after* approval |
| Open a voucher | Nobody today — the screen refuses you. See above. |
| Manage users or settings | `MASTER_ADMIN` |
