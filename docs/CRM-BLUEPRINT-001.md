# CRM Blueprint 001 — response, gap analysis and build plan

**Status:** received, analysed against the running application. Not yet built.
**Format note:** blueprint kept short and point-wise, as asked. This reply matches it.

---

## 1 · One thing to settle before any code

The blueprint says *"do not rebuild existing modules — extend and enhance them."*
Measured against the running app, that instruction does most of the work for us:

**Already built and working — must NOT be rebuilt (14 of the 40 mandatory features):**

| Blueprint feature | Where it already lives |
|---|---|
| Inquiry management | `crm_inquiries`, `/inquiries` |
| Quotation management + approval chain | `quotations`, `quote_approvals`, `/quotes` |
| Sales dashboard | `/crm-reports` — FY-filtered, win/loss, top customers |
| Follow-up automation | `crm_run_followups()`, 3/6/9-day, fortnight, month |
| Custom fields | `custom_fields`, auto-rendered per entity |
| Dynamic forms / configurable dropdowns | the lookup engine, 34 lists already moved into it |
| Duplicate detection | `find_duplicate_partner()` — GSTIN, PAN, TAN, normalised name |
| Role-based permissions | `ACCESS_MODULES`, per-module view/edit, per-user override |
| Audit trail | `idems_log`, `portal_audit`, `ncr_events`, `capa_events` |
| Document management | `report_files`, `quote_files`, versioned, in-row |
| Email integration | SMTP + `ops_mail` + attachments + mail log |
| AI provider keys & model selection | `/ai-settings` — 5 providers, live model refresh |
| Multi-company / multi-branch | `scope_clause`, `scope_office_clause` |
| Import & export | partner import, CSV on every register |

**Genuinely absent — this is the real build (12 tables, none exist):**
`leads` · `opportunities` · `activities` · `tasks` · `meetings` · `notes` ·
`tags` · `territories` · `segments` · `calendar_events` · `pipelines` ·
`whatsapp_messages`

**So the blueprint is roughly 35% already done.** The honest scope is a
lead-and-activity layer wrapped around the funnel that exists, not a CRM.

---

## 2 · Where I disagree with the blueprint

Four points, stated now rather than discovered in build:

1. **"Research top 15 CRMs" is the wrong first step here.** Copying feature
   lists from Salesforce and Zoho is how you get the 40-feature product the
   blueprint's own philosophy section warns against. The useful research is
   narrower: *why do inspection and engineering firms abandon generic CRMs?*
   I can do that pass — but say so, because I have not done it and will not
   pretend otherwise.

2. **"Universal CRM supporting 15 industries" fights "minimum clicks".** Every
   industry added is a field somebody must ignore. The configurable-fields
   engine already here is the right answer, but it means shipping ONE industry
   properly first — yours — and proving the configuration carries the second.

3. **Ten AI features on day one is not credible.** Lead scoring and churn
   prediction need *history to learn from*. You have no leads table, so there
   is no history. AI features should come after 6–12 months of real data, not
   in v1. What can ship immediately: AI email drafting and meeting summary,
   because those need no history — the keys and model picker are already built.

4. **WhatsApp integration has a cost and a gatekeeper.** It needs a Meta
   Business account, a verified number and per-conversation charges. Worth
   doing, but it is a commercial decision before it is a technical one.

---

## 3 · Proposed build order

Each phase ships working and is usable on its own.

**P1 · The activity spine** *(the thing everything else hangs off)*
- `activities` — one table for calls, meetings, e-mails, notes, tasks
- Polymorphic: attaches to a lead, customer, inquiry, quotation or job
- Auto-logged, never typed twice: sending a quote writes its own activity
- Timeline component, reused everywhere
- **Why first:** Customer 360° is impossible without it, and it is the single
  highest-value table in the whole blueprint

**P2 · Leads and the pipeline**
- `leads` — capture, source, owner, status
- `pipelines` + stages, configurable per business unit
- Lead → Inquiry conversion (the inquiry module already exists; this feeds it)
- Kanban board, drag between stages

**P3 · Customer 360°**
- One page: profile, contacts, activity timeline, open inquiries, quotations,
  jobs, invoices, outstanding, complaints, reports issued
- Everything on it already exists in the database — this is assembly, not new data
- **This is the screen that makes the CRM feel like a CRM**

**P4 · Tasks, calendar, reminders**
- `tasks` with owner and due date, `calendar_events`
- Auto-created from stage changes and follow-up rules
- Today/This week view per user

**P5 · Segmentation and territory**
- `tags`, `segments` (saved filters, not copies), `territories`
- Assignment rules by territory

**P6 · Opportunities** *(only if your sale needs it)*
- Your funnel is inquiry → quotation → contract. A separate opportunity object
  may be duplication. **Decide before building.**

**P7 · AI, on real data**
- Ships first: e-mail drafting, meeting summary, customer insight summary
- Waits for history: lead scoring, churn, forecasting

**P8 · WhatsApp** — after the commercial decision

---

## 4 · What I need from you before P1

1. **Do you sell to leads that are not yet clients?** If every inquiry comes
   from a known company, the lead table is thinner than the blueprint assumes.
2. **Is an "opportunity" distinct from a quotation for you?** If not, we skip P6
   and save weeks.
3. **Who owns a lead** — the branch, the business unit, or a named person?
4. **WhatsApp — yes, and who pays for the Meta account?**

Answer 1–3 and P1 starts. Question 4 can wait until P8.

---

## 5 · Against the blueprint's own Golden Rules

The blueprint asks ten questions before each feature. Applied to the blueprint
itself, the answers are: **it is too big for one pass, and its own philosophy
says so.** "Simplicity before complexity" and a 40-feature mandatory list are
in tension. This plan resolves that in the blueprint's favour — ship the spine,
prove it, then extend.
