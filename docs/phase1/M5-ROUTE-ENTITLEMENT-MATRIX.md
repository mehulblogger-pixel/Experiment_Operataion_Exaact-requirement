# Milestone 5 — Route Entitlement Matrix

Which screens belong to which product module, and how a request is decided.

---

## 1. The decision, in order

Every request that reaches the router is decided at **one** place —
`ops_module_gate()` in `lib/ops.php`, called from `ops_dispatch()`. There is no
second gate and no alternative path.

```
route
  ├─ 1. explicit map            route → access module   (453 entries)
  ├─ 2. family table            unmapped route → access module   (M5, new)
  ├─ 3. licence_owner()         access module → product module
  ├─ 4. licence_enabled()       has this installation bought it?
  └─ 5. can('mod.<module>.view') does THIS PERSON have the right?
```

Steps 3–4 are the **company** question (entitlement). Step 5 is the **person**
question (permission). They stay separate and neither substitutes for the other.

---

## 2. Product modules and what they own

| Product module | Sold as | Access modules it owns |
|---|---|---|
| `admin` **(core)** | Administration | masters, users, settings, clients, vendors, reports, portal |
| `operations` | Operations | calls, jobs, reconcile, vouchers, equipment, competence, impartiality, identity, complaints, ncr, capa, audits, datacontrol, confidentiality, overheads |
| `sales` | Sales & CRM | leads, inquiries, quotes, crm_orders, crm_reports |
| `reporting` | Inspection reporting | idems |
| `money` | Money | invoicing, profitability |
| `hr` | People & hiring | hiring |

`admin` is core: it can never be switched off. Every installation needs masters,
users and settings, and a company without them does not have a smaller product —
it has a broken one.

---

## 3. The family table (new in M5)

Consulted **only** when the explicit map has no entry for a route.

| Route begins with | Access module | Product module |
|---|---|---|
| `candidate`, `candidates`, `requisition`, `requisitions`, `recruit`, `recruitment`, `positions`, `careers`, `jd` | `hiring` | **hr** |
| `lead`, `leads`, `opportunity`, `pipeline`, `pipelines` | `leads` | **sales** |
| `quote`, `quotes` | `quotes` | **sales** |
| `crm` | `crm_reports` | **sales** |
| `invoice`, `invoices`, `receipt`, `receipts`, `tally`, `credit` | `invoicing` | **money** |
| `voucher`, `vouchers` | `vouchers` | operations |

Longest prefix wins (two words before one), so a future two-word rule takes
precedence over a one-word one.

**Coverage.** Of 524 dispatcher routes discovered in the source, the family
table can claim 105 — 35 hiring, 29 quotes, 18 invoicing, 12 vouchers, 9 CRM
reporting, 2 leads. Most of those are already named in the explicit map; the
table's value is the remainder, and every future route added to those families
that nobody remembers to map.

**What it will not do.** A route the table does not name is not guessed at. An
auto-derived recogniser was built and measured first and produced wrong
inferences (`issue-licence` → NCR, `approval-rules` → Sales). Blocking a paying
customer's working screen is as much a defect as letting an unpaid one through,
so unrecognised routes stay unclaimed and are governed by the explicit map alone.

---

## 4. Routes deliberately left ungated

These are ungated by design, documented here so the decision is visible rather
than looking like an oversight:

| Route | Why |
|---|---|
| `trace`, `flow-gaps` | Show only records the person's own scope already returns. Gating by module would hide the very handovers they exist to expose. |
| `advisor` | Spans selling, doing and billing; each of its own checks already asks whether that module is licensed. A single module gate would either hide the whole screen from someone who owns half of it, or show findings they cannot act on. |
| `dashboard` | Renders only the panels the person's modules allow. |

---

## 5. Screens outside the router

| Path | Gate |
|---|---|
| `/careers` (public, pre-login) | `careers_enabled()` → `licence_enabled('hr')` **and** the company's own opt-in setting. **Closed in M5.** |
| `/login`, `/logout` | No module — authentication, not a product module. |
| `service-scope`, `service-formats` | Gate on core `settings`, then a second explicit check for `operations` / `reporting` so a typed URL cannot reach inspection configuration in a Recruitment-only company. |

---

## 6. The S-1 acceptance scenario

Operations ON, Reporting ON, HR / Sales / Money OFF, signed in as a **master**
(the hardest case, because before M5 a master saw everything):

| Asked for | Result |
|---|---|
| Operations — `jobs`, `calls` | **PASS** |
| Reporting — `documents`, `mod.idems.view` | **PASS** |
| Master authority inside Reporting | **PASS** — retained |
| HR — `candidates`, `requisitions`, `recruitment`, `careers-admin` | **DENIED** |
| Money — `invoices`, `receipts`, `to-bill`, `profitability` | **DENIED** |
| Sales — `mod.leads.view`, `mod.quotes.view` | **DENIED** |
| Public careers page | **DENIED** — does not serve |
| Master asking for HR | **DENIED** |

Verified by 76 automated assertions in `tests/test_m5_runtime_entitlement.php`.
