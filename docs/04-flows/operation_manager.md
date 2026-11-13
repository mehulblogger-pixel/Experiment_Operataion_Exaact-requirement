# Flow — OPERATION_MANAGER

Head of delivery for a branch/unit: owns getting calls scheduled, jobs allocated and
reports out. Desk-first. Scope **OWN/OWN**. Permissions: `access.php:448-450`.

```mermaid
flowchart TD
  A[Login → / dashboard] --> B[Operations home / pending tasks]
  B --> C[Raise call / allocate job<br/>like the Coordinator]
  C --> D((Handoff → Inspector /my-jobs))
  B --> E[Endorse contract opening<br/>can_endorse_contract_open]
  E --> F((Handoff → Branch Manager approves))
  B --> G[Finalise/issue reports · approve reports<br/>idems.finalize · workforce.report.approve]
```

- **Landing:** `/` dashboard (ops emphasis). Does the full coordinator operational flow (raise call, allocate, close — see `coordinator.md`) plus management actions.
- **Endorses** contract openings (`can_endorse_contract_open()`, `contracts.php:473-477`) → handoff to the Branch Manager to approve.
- **The signatures are each company's own rule (owner, 13 Nov 2026).** What is described here is the standard rule. A company can change how many approvals a contract needs (two, one, none) and who gives each under **Admin → Approvals → Sales → Contract**. On the screen you are offered the one signature that is yours to give now, and you read — by name — who it is waiting for otherwise.
- **Approves reports** (`workforce.report.approve`) and can **finalise/issue** (`idems.finalize`) — subject to approver≠issuer (`idems.php:4460`).
- Sees profitability (`data.profitability`) and revenue, **not** salary or credit.
- **Must never:** manage users/settings; see salary/credit figures.
- Most common task = same as coordinator (raise call ~4 clicks / allocate ~3 clicks).
