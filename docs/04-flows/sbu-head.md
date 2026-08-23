# Flow — `SBU_HEAD`

**Device:** desk, laptop. Weekly and monthly rather than daily; travels.
**Landing screen:** the Dashboard, all four panels.

Runs one line of business across **every** branch. Where a Branch Manager asks "how
did Vadodara do?", you ask "how did welding inspection do, everywhere?"

---

## Main flow

```mermaid
flowchart TD
  A([Sign in]) --> B[Dashboard — all offices,<br/>your business unit only]
  B --> C{What am I looking at?}
  C -->|"the numbers"| D[Profitability · utilisation · P&L]
  C -->|"reports waiting"| E[Approve reports]
  C -->|"a branch is behind"| F[Drill into that branch]
  D --> G([Handoff → BUSINESS_DIRECTOR])
  E --> H([Handoff → whoever issues it])
  F --> I([Handoff → that BRANCH_MANAGER])
```

### Walkthrough

1. **Sign in.** Your scope is `offices => 'ALL'`, `sbus => 'OWN'`
   (`phpapp/lib/access.php:367`) — every office, one business unit. The exact mirror
   of the Branch Manager.
2. **Read the numbers.** All four dashboards and all four money permissions are yours.
3. **Approve reports.** Despite being read-only on every module, you hold
   `idems.finalize` and `workforce.report.approve` (`phpapp/lib/access.php:367`) —
   a business-unit head signing off technical work is exactly what an accreditation
   body expects.
4. **Drill into a branch.** You can see it; fixing it belongs to its Branch Manager.

### 🔁 Handoff points

- **Branch Managers → you.** *Their branch's contribution to your unit.*
- **You → the Business Director.** *Your unit's performance.*
- **You → a Branch Manager.** *Anything needing operational action.*

### Click count

**Task: check this month's margin for your business unit.** Dashboard → Money (1) →
Profitability (1) → set the period (1) = **≈ 3 clicks**, counted as discrete clicks on
the shortest path.

### Cannot do

Create or edit any operational record · manage users · change settings · reach
another business unit.

> ⚠ **"Read-only on every module" is not true in practice.** You are in the
> management tier (`phpapp/lib/access.php:27`) and hold `mod.jobs.view`, which is all
> the job routes require — so you can allocate, edit, reassign and close jobs
> (`phpapp/lib/ops.php:5136`, `:5531`). You hold `mod.vouchers.view` too, so you can
> still approve vouchers — though the register is now scoped to your offices and you
> can no longer approve one you submitted yourself. This is risk 4 in
> `99-gaps-and-risks.md`, and it is not yet fixed.
