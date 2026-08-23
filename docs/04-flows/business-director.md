# Flow — `BUSINESS_DIRECTOR`

**Device:** desk or tablet. Monthly, or when something needs explaining.
**Landing screen:** the Dashboard, every panel.

A board-level view. Sees the whole company and — by design — changes none of it.

---

## Main flow

```mermaid
flowchart TD
  A([Sign in]) --> B[Dashboard — whole company]
  B --> C{What am I checking?}
  C -->|"how are we doing"| D[Profitability · P&L · utilisation]
  C -->|"are we compliant"| E[Compliance audit log]
  C -->|"who reports to whom"| F[Organisation hierarchy]
  C -->|"a specific concern"| G[Drill into any register — read only]
  D --> H([Handoff → SBU_HEADs and<br/>BRANCH_MANAGERs for action])
```

### Walkthrough

1. **Sign in.** Scope is `ALL` / `ALL` (`phpapp/lib/access.php:365`). Every office,
   every business unit.
2. **Read the numbers.** All four dashboards, all four money permissions.
3. **Check compliance.** `idems.audit.view` — who changed what, and when.
4. **See the structure.** `org.hierarchy.view`.
5. **Look at anything.** View access to every module.
6. **Ask someone else to act.** No `ops.*` permission, no `master.manage`, no
   `users.manage.*`, no `settings.manage` — nothing to change anything with.

### The one thing deliberately withheld

**Identity documents.** The blanket "every module" grant explicitly removes
`identity` (`phpapp/lib/access.php:288-293`). The code's reasoning: a Business
Director getting every module by default "is a reasonable default for revenue figures
and a bad one for passports". It must be granted deliberately. Do not undo this
casually.

### 🔁 Handoff points

- **Everyone → you.** *The numbers.*
- **You → the SBU Heads and Branch Managers.** *Everything, because you cannot act
  yourself.*

### Click count

**Task: check company profitability for the quarter.** Dashboard → Money (1) →
Profitability (1) → set the period (1) = **≈ 3 clicks**, counted as discrete clicks on
the shortest path.

### Cannot do

Change anything at all — that is the entire design of the role.

> ✅ **Read-only now means read-only.** This role used to sit in the management tier,
> which the job and voucher routes mistook for operational authority — so a
> board-level, view-only role could allocate work and approve pay. `OPS_ROLES`
> (`phpapp/lib/access.php:55`) now names only the six roles that actually run
> operations, and this is not one of them. You can no longer allocate, edit or
> reassign a job, and the voucher register refuses you. See `99-gaps-and-risks.md`
> risk 4.
>
> ⚠ **One thing survives.** You can still *close* a job, because the close route
> checks no permission at all — only `mod.jobs.view`, which you hold. That is risk 5,
> still open, and it is now the most valuable item on that list.
