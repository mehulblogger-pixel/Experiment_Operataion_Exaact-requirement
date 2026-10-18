# D1 — Application inventory

**Phase A + B** · code at `f9fb003` · all figures MEASURED unless marked otherwise.
Method, limitations and the two measurement errors corrected: see
`00-AUDIT-CHARTER-AND-CHECKLIST.md`.

> **Scope of these numbers.** The runtime probe ran as an **administrator**
> against a throwaway copy of a UAT database. Role-dependent navigation — most
> importantly the inspector's own rail — is **not** covered. "Unreachable"
> below means *unreachable for an administrator*.

---

## 1. The shape of the thing

| | Count | Source |
|---|---|---|
| Application PHP (excl. tests) | **190,674 lines** | `find phpapp -name '*.php' -not -path '*/tests/*'` |
| Test PHP | **64,853 lines** in **588 files**, 1,185 sections | `phpapp/tests/` |
| View files | **408** | `phpapp/views/` |
| Library files | **236** | `phpapp/lib/` |
| Routes dispatched (exact match) | **565** | `evidence/routes-dispatched.txt` |
| Routes dispatched (prefix match) | **35 prefixes** | `strncmp($route, …)` in `lib/ops.php` |
| Routes in the permission gate's module map | **466** → **30 modules** | `ops_route_module_map()` |
| Database tables created | **317** | `CREATE TABLE IF NOT EXISTS` across `lib/` |
| Tables extended after creation | **87** | `ensure_column(...)` |
| Roles | **16** | `ORG_ROLES`, `lib/access.php` |
| Permission keys grouped for the access editor | **194** in **9 groups** | `permission_nav_groups()` |
| Documentation files | **671** | `docs/` |

**Observation (READ).** 565 dispatched routes against 466 in the gate's module
map leaves **99 routes with no declared module**. `ops_module_family()` catches
some by prefix; the remainder fall through to no module, which means no module
gate and no area. This is the mechanism behind several findings below and is
Phase F work to quantify exactly.

---

## 2. What the probe found

Every dispatched route was requested as an administrator.

| Result | Count | Meaning |
|---|---|---|
| **SCREEN** | 256 | 200 with a heading or breadcrumb — a real screen |
| **REDIRECT** | 130 | an action handler, or a detail screen needing a record id |
| **NOTFOUND** | 108 | dispatched but 404 — almost all are detail screens requiring `?id=` |
| **PARTIAL** | 50 | 200 with no heading — fragments, exports, JSON endpoints |
| **FORBIDDEN** | 2 | refused to this administrator (licence/role gated) |
| **HTTP 400** | 1 | — |
| *not probed* | 18 | destructive by name; listed in `evidence/routes-not-probed.txt` |

Full table: `evidence/route-probe-admin.tsv`.

---

## 3. THE HEADLINE FINDING — 95 working screens have no way in

| | Count |
|---|---|
| Screens that render | **256** |
| Reachable from an area tile | 89 |
| Reachable from "Find a screen" | 140 |
| Reachable from **either** | **140** |
| **Not reachable from either** | **116** |
| &nbsp;&nbsp;…of which re-render a parent screen (action handlers) | 21 |
| &nbsp;&nbsp;…**genuinely unreachable** | **95** |

The 21 were separated by rendered heading: a route whose heading matches a
reachable screen's is an action handler falling through to its parent, not a
lost screen. The remaining 95 render a heading **no reachable screen renders**.
Full list: `evidence/unreachable-screens.tsv`.

Some of the 95 are correctly address-only — a public `/verify`, `/privacy`,
`/join`, `/welcome`, platform-owner consoles. **Many are not.** The clearest
case is Money.

### Money: the analysis is on the menu, the money is not

`MEASURED` — every tile the Money area offers:

> Billable events · Invoice tracker · Billing workspace · Revenue reconciliation ·
> Contract number register · Office costs & overheads · Month-end cost run ·
> Cost reconciliation · Reimbursable duplication · Business Unit P&L · Profit by work order

All eleven are reconciliation and analysis. **None of these render from the menu:**

| Screen | Heading it renders | Menu entry |
|---|---|---|
| `/invoices` | Invoices | **none** |
| `/invoice-new` | New invoice | **none** |
| `/receipts` | Money in | **none** |
| `/receipt-new` | Record money received | **none** |
| `/receivables` | Receivables ageing | **none** |
| `/tally` · `/tally-export` | Tally export | **none** |

An administrator cannot reach the invoice register, record a receipt, or see
receivables ageing without typing the address. Same pattern elsewhere:
`/ncr` (Nonconformities), `/capa`, `/incidents`, `/departures` in Quality;
`/deputations` in Operations; `/raise-call`, `/inquiry-new`, `/quote-new`,
`/partner-new` as creation entry points.

This is the mechanism behind the owner's repeated UAT reports that features
"do not exist". It is not a missing feature — it is a missing door.

---

## 4. Duplicate-heading check

Two headings are rendered by more than one **reachable** route:

| Heading | Routes |
|---|---|
| Approvals | `/approvals` (the inbox) · `/approvals-setup` (the rule workbench) · `/approval-act` |
| Organisation & people | `/hierarchy` · `/users` |

The first pair is deliberate and documented. **The second is not explained
anywhere** and is a candidate duplicate — Phase C must establish which screen
owns the person record.

---

## 5. Areas, sections and tiles — as the running application declares them

### Sales — `/sales` (9 tiles, area engine)

| Section | Tile | Route | Probe |
|---|---|---|---|
| — | Leads | `/leads` | SCREEN |
| — | Opportunities | `/opportunities` | SCREEN |
| — | Inquiries | `/inquiries` | SCREEN |
| — | Quotes | `/quotes` | SCREEN |
| — | Quote templates | `/templates?kind=quote` | SCREEN |
| — | Pre-order checklist | `/preorder-checklist` | SCREEN |
| — | Project costing | `/project-costings` | SCREEN |
| — | Approvals | `/approvals` | SCREEN |
| — | Pipelines & funnels | `/pipelines` | SCREEN |

### Marketplace — `/marketplace` (14 tiles, area engine)

| Section | Tile | Route | Probe |
|---|---|---|---|
| — | Requirements | `/connect-requirements` | SCREEN |
| — | Guided post | `/connect-concierge` | SCREEN |
| — | Talent search | `/connect-talent` | SCREEN |
| — | Passports | `/passport-share` | SCREEN |
| — | Industry taxonomy | `/connect-taxonomy` | SCREEN |
| — | Qualification taxonomy | `/connect-qualifications` | SCREEN |
| — | Verification desk | `/connect-verify` | SCREEN |
| — | Rating-integrity desk | `/rating-disputes` | SCREEN |
| — | Messages | `/connect-messages` | SCREEN |
| — | Channels | `/connect-channels` | SCREEN |
| — | Agency bench | `/connect-bench` | SCREEN |
| — | Market analytics | `/connect-analytics` | SCREEN |
| — | Organisations | `/connect-orgs` | SCREEN |
| — | Access requests | `/access-requests` | SCREEN |

### Operations — `/operations` (0 tiles, own handler)

This area is served by its own handler and declares no tiles, so the
area engine, the breadcrumb and the search index learn nothing from it.


### Recruitment — `/recruitment` (0 tiles, own handler)

This area is served by its own handler and declares no tiles, so the
area engine, the breadcrumb and the search index learn nothing from it.


### Reporting — `/reporting` (8 tiles, area engine)

| Section | Tile | Route | Probe |
|---|---|---|---|
| Reports | Report register | `/documents` | SCREEN |
| Reports | New report | `/document-new` | SCREEN |
| Reports | Endorsement register | `/endorsements` | SCREEN |
| Expediting | Expediting register | `/expediting` | SCREEN |
| Expediting | Project delivery | `/expediting-projects` | SCREEN |
| Writing | Technical writing | `/writing-assistant` | SCREEN |
| Writing | Learning insights | `/learning` | SCREEN |
| Governance | Where we stand | `/compliance` | SCREEN |

### Money — `/money` (11 tiles, area engine)

| Section | Tile | Route | Probe |
|---|---|---|---|
| Billing | Billable events | `/billable-events` | SCREEN |
| Billing | Invoice tracker | `/invoicing` | SCREEN |
| Billing | Billing workspace | `/to-bill` | SCREEN |
| Billing | Revenue reconciliation | `/revenue-reconciliation` | SCREEN |
| Costs & margins | Contract number register | `/profitability` | SCREEN |
| Costs & margins | Office costs & overheads | `/office-finance` | SCREEN |
| Costs & margins | Month-end cost run | `/cost-run` | SCREEN |
| Costs & margins | Cost reconciliation | `/cost-reconciliation` | SCREEN |
| Costs & margins | Reimbursable duplication | `/reimbursable-dedup` | SCREEN |
| Costs & margins | Business Unit profit & loss | `/sbu-pl` | SCREEN |
| Costs & margins | Profit by work order | `/call-profit` | SCREEN |

### Quality & Accreditation — `/quality` (23 tiles, area engine)

| Section | Tile | Route | Probe |
|---|---|---|---|
| Everyday quality | Complaints & appeals | `/complaints` | SCREEN |
| Everyday quality | Client acceptance | `/report-reviews` | SCREEN |
| Everyday quality | Customer satisfaction | `/satisfaction` | — |
| Everyday quality | Items & samples | `/samples` | — |
| Everyday quality | Hold & witness points | `/hold-points` | FORBIDDEN |
| Everyday quality | Evidence review | `/evidence-review` | SCREEN |
| Everyday quality | Nonconformity workspace | `/issues` | SCREEN |
| Accreditation registers | Equipment & calibration | `/equipment` | — |
| Accreditation registers | Method library | `/methods` | — |
| Accreditation registers | Decision rules | `/drules` | — |
| Accreditation registers | Controlled documents | `/cdocs` | — |
| Accreditation registers | Retention schedule | `/retention` | — |
| Accreditation registers | Data & information control | `/data-control` | SCREEN |
| Accreditation registers | Risks & opportunities | `/risks` | — |
| Accreditation registers | Competence & authorisation | `/competence` | SCREEN |
| Accreditation registers | Impartiality | `/impartiality` | SCREEN |
| Accreditation registers | Disclosure consent | `/disclosure` | — |
| Accreditation registers | Internal audits | `/internal-audits` | SCREEN |
| Accreditation registers | Management review | `/management-reviews` | SCREEN |
| Accreditation registers | Confidentiality | `/confidentiality` | SCREEN |
| Accreditation registers | Site entry documents | `/site-docs` | SCREEN |
| Accreditation registers | Identity documents | `/identity` | SCREEN |
| Accreditation registers | SLA targets | `/sla-targets` | SCREEN |

### Insights — `/insights` (4 tiles, area engine)

| Section | Tile | Route | Probe |
|---|---|---|---|
| — | Dashboards | `/reports` | SCREEN |
| — | Analytics & performance | `/analytics` | — |
| — | Sales dashboard | `/crm-dashboard` | SCREEN |
| — | Management dashboard | `/mis` | SCREEN |

### Directory — `/directory` (8 tiles, area engine)

| Section | Tile | Route | Probe |
|---|---|---|---|
| — | Activity | `/activities` | SCREEN |
| — | Client register | `/clients` | SCREEN |
| — | Vendor register | `/vendors` | SCREEN |
| — | Client holds | `/client-holds` | SCREEN |
| — | Find duplicates | `/duplicates` | SCREEN |
| — | Asset issuance | `/asset-register` | SCREEN |
| Portals | Client portal | `/portal-users` | SCREEN |
| Portals | Vendor portal | `/vendor-users` | SCREEN |

### Admin — `/admin` (26 tiles, area engine)

| Section | Tile | Route | Probe |
|---|---|---|---|
| Set up your workspace | Company setup | `/workspace/setup` | SCREEN |
| Set up your workspace | Modules & features | `/workspace/setup/modules` | — |
| Masters | Masters | `/masters` | SCREEN |
| People | User register | `/users` | SCREEN |
| People | Organisation | `/hierarchy` | SCREEN |
| People | Roles & permissions | `/access` | SCREEN |
| People | Role workspaces | `/role-workspaces` | SCREEN |
| People | Single sign-on | `/sso` | SCREEN |
| Configuration | System settings | `/settings` | SCREEN |
| Configuration | Backup & restore | `/backup` | SCREEN |
| Configuration | AI settings | `/ai-settings` | SCREEN |
| Configuration | Terminology / wording | `/terminology` | SCREEN |
| Configuration | Form Designer | `/form-designer` | — |
| Configuration | Build forms with AI | `/ai-forms` | SCREEN |
| Configuration | Service scope | `/service-scope` | SCREEN |
| Configuration | Report formats by service | `/service-formats` | SCREEN |
| Configuration | Company profile | `/company-profile` | SCREEN |
| Approvals | Approvals | `/approvals-setup` | SCREEN |
| Report configuration | Report templates | `/templates?kind=report` | SCREEN |
| Report configuration | Report audit trail | `/audit-log` | SCREEN |
| Super admin | Control panel | `/super-admin` | SCREEN |
| Super admin | Companies | `/companies` | SCREEN |
| Super admin | Product package | `/product-package` | SCREEN |
| Connections | Ads Pro connection | `/adspro` | SCREEN |
| Connections | Licence | `/licence` | SCREEN |
| Connections | MGH Books | `/books-bridge` | SCREEN |

---

## 6. Open questions for the owner

Numbered for reference; they cannot be settled from the repository.

See the chat summary accompanying this phase.

