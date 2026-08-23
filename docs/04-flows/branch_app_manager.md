# Flow — BRANCH_APP_MANAGER (Branch Application Manager)

The branch's system/config custodian: sets up master data, report types & numbering,
and branch logins, and can delete a wrongly-raised call. Desk-first. Scope OWN/ALL.
Permissions: `access.php:445-447`. **Only role with `idems.timestamp.edit`.**

```mermaid
flowchart TD
  A[Login → / dashboard] --> B[Masters / lookups / overheads]
  A --> C[Users in own office<br/>users.manage.branch]
  A --> D[Report types · numbering · templates<br/>idems.type.manage · via Admin tiles]
  A --> E[Approve contract opening<br/>can_approve_contract_open]
  A --> F[Delete a wrong call<br/>ops.call.delete]
```

- **Landing:** `/` dashboard (ops emphasis).
- **Can:** edit masters/overheads/users; configure report types, IRN numbering and templates (`idems.type.manage`); edit locked report timestamps (`idems.timestamp.edit`, `idems.php:7357`); approve contract openings (`can_approve_contract_open()`, `contracts.php:478-481`); delete calls (`ops.call.delete`).
- **Cannot:** run inspections or approve/issue reports (no `mod.idems.view`/`idems.finalize`).
- **⚠ Quirk:** holds IDEMS-config permissions but **not** `mod.idems.view`, so no Reporting rail item — the config screens are reached only via Admin tiles (`areas.php:220-223`). See `99-gaps-and-risks.md`.
- **Handoffs:** approves an endorsed contract → floats the order to Operations.
- Most common task = maintain masters / add a login (a few clicks per record).
