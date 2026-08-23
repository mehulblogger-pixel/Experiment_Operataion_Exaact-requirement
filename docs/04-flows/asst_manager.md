# Flow — ASST_MANAGER

A desk deputy who keeps the operations board moving: raises calls and allocates jobs,
but has **no money authority and cannot close jobs**. Scope OWN/OWN. Permissions:
`access.php:451-452`. `is_coordinator_level` but **not** `is_admin_level`.

```mermaid
flowchart TD
  A[Login → / dashboard<br/>scheduling-first] --> B[Raise call → /call-new]
  B --> C[Allocate job → /job-new]
  C --> D((Handoff → Inspector /my-jobs))
  A --> E[Complaints / CAPA (edit) · quality tiles]
```

- **Landing:** `/` scheduling-first dashboard (`dashboard.php:66`).
- **Can:** create/edit calls (`ops.call.create`), allocate jobs (`ops.job.allocate`), manage availability, edit complaints; view clients/vendors/reports/CAPA.
- **Cannot (hard boundary):** close jobs (**no `ops.job.close`** — `access.php:451`), see any money figures, manage users/settings, access Money or Recruitment areas.
- **Handoff:** allocating a job → inspector's `/my-jobs`.
- **Quirk:** the Admin area appears for this role **only** because of the coordinator-level "SLA targets" tile (`areas.php:216`) — it has no real admin access. See `99-gaps-and-risks.md`.
- Most common task = raise a call (~4 clicks) / allocate a job (~3 clicks).
