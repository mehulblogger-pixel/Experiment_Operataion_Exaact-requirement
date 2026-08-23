# Flow — `BRANCH_APP_MANAGER`

**Device:** desk, laptop. Occasional deep sessions rather than continuous use.
**Landing screen:** the Dashboard, operations and utilisation panels only.

The branch's system custodian — part office manager, part local IT. Holds the keys
to the filing cabinet without being allowed to read the accounts.

---

## Main flow

```mermaid
flowchart TD
  A([Sign in]) --> B[Dashboard]
  B --> C{What needs me?}
  C -->|"a new starter"| D[Users — create the login]
  C -->|"a dropdown is wrong"| E[Masters and lookups]
  C -->|"numbering broke"| F[Report numbering rules]
  C -->|"a locked record is wrong"| G[Correct the timestamp]
  C -->|"a call raised in error"| H[Delete the call]
  C -->|"office costs"| I[Overheads]
  D --> D2([Handoff → the person can sign in])
  H --> H2[/Irreversible — nobody else can do this/]
```

### Walkthrough

1. **Sign in.** You see operations and utilisation, and **no money at all** — no
   credit, no revenue, no salary, no profitability (`phpapp/lib/access.php:372`).
   That is the point of the role.
2. **Create logins.** You hold `users.manage.branch` — your own office only.
3. **Keep the lists right.** Masters and lookups are yours. Note these are gated on
   the management tier, not on the `master.manage` permission
   (`phpapp/lib/lookups.php:635`).
4. **Own the numbering.** `idems.type.manage` — report types and IRN rules.
5. **Correct a locked timestamp.** `idems.timestamp.edit`. The code names your role
   as the **only** one that may (`phpapp/lib/access.php:370-371`).
6. **Delete a call raised in error.** `ops.call.delete` — again, uniquely yours
   (`phpapp/lib/ops.php:3630`). Not even the Branch Manager has it.
7. **Office overheads.** The per-office cost model and the month-end cost run.

### 🔁 Handoff points

- **The Branch Manager → you.** *Anything needing a correction they cannot make.*
- **You → everyone.** *Correct reference data is what every other role's dropdowns
  depend on.*

### Click count

**Task: add a new value to a dropdown list.** Admin → Masters (1) → the list (1) →
add (1) → type the value → save (1) = **≈ 4 clicks plus one typed field**, counted as
discrete clicks on the shortest path.

### Three things about this role that were wrong — two are now fixed

**Your two signature powers are unreachable.** You hold `idems.type.manage` and
`idems.timestamp.edit` — and the role has **no IDEMS module access**
(`phpapp/lib/access.php:261-262`), while every one of those screens is gated on that
module (`phpapp/lib/ops.php:2293-2295`). The permissions are real; the screens refuse
you.

**You can see money you were never granted.** `can_see_revenue()` is
`can('data.revenue') || is_admin_level()` (`phpapp/lib/ops.php:604`) and you are still
in the *seniority* tier — so revenue figures appear despite the role being built to
exclude them. Narrowing the operations tier did not touch this, because it hangs off
`is_admin_level()`. That is risk 14, still open.

✅ **You can no longer allocate work.** Being in the management tier used to put you
past the job-allocation gate even though you hold no `ops.job.allocate`. `OPS_ROLES`
(`phpapp/lib/access.php:55`) now separates seniority from operational authority, and
a records custodian is not operations staff. See `99-gaps-and-risks.md` risk 4.

✅ **Vouchers are no longer reachable.** You hold no voucher module and the module is
now checked (`phpapp/lib/ops.php:2368-2375`), so the claims you were never meant to
see are genuinely closed to you.

✅ **Your master lists are untouched.** Narrowing the tier deliberately did not take
the sub-contractor, rate and contract lists away from you — master data is reference
data, not an operational action (`phpapp/lib/ops.php:2262`).

⚠ **You can still close a job** (risk 5) and still see revenue figures (risk 14).
Both remain open.
