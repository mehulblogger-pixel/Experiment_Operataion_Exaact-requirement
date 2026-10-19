# Sales-D — reporting, templates, permissions, forms (subagent report, saved by the lead)

Probes: *.php, act.php (one real request per process), route_matrix.tsv, perm_probe.tsv, flow_*.out, recon_read.json, industry_templates.md. Shipped role defaults, no saved grid. Direct-POST results assume signed-in user with valid CSRF.

## Headline (EXEC unless READ)
F1 P1 BDM, KAM, MM, ME, BM, FIN get 403 on /leads, /opportunities, /pipelines and lead/opportunity create. Only MA and BD have leads module (access.php:813-860; ops.php:3250-3254). BDM flow doc starts there.
F2 P1 FIN (only contract registrar) 403 on /opportunity-contract → deals won without a quote never get a contract.
F3 P1 DRAFT→SENT with 0 approval steps; DRAFT→ACCEPTED (crm.php:1846, 1913). Contradicts 03-object-lifecycles.
F4 P1 APPROVED quote re-priced 1,18,000→3,54,000 and sent without re-approval.
F5 P1 quote-status no perm check for ACCEPTED/LOST/REJECTED/DRAFT; FIN (view only) accepted → cascade; BM marked LOST with no reason, another REJECTED.
F6 P1 BM of office B approved office A's quote by leaving ?id out of the POST: scope gate returns early (crm.php:1410-1411).
F11 P1 contract on DRAFT quote; contract value includes GST (1,18,000 for 1,00,000 work).
F12 P1 WO raised from a won deal carries deal estimate (2,50,000) not accepted quote's 1,00,000 subtotal; opp_quotes() never selects subtotal (opportunities.php:209 vs :694).
F24 P1 BD can apply an industry template; doing so switched ISO/IEC 17020 pack off and replaced company wording. Matrix gives Settings to Admin only.
F7–F10 P2 levels out of order; creator approves own; one-click approval writes no audit row; rule names deactivated approver → quote in nobody's inbox; home tile "Pending your approval" MM 2 (inbox 0), actual BM approver 0 (inbox 1); quote approvals never in /approvals.

## Reconciliation probe (dataset office A SBU IND)
RQ-1 May 100k/118k ACCEPTED; RQ-2 rev0 Jun 50k/59k ACCEPTED then revised via /quote-revise; RQ-2 rev1 Jul 60k/70.8k ACCEPTED; RQ-3 Aug 20k/23.6k LOST(price); RQ-4 Sep 10k/11.8k EXPIRED by sweep; RQ-5 Oct 30k/35.4k DRAFT; RQ-6 Dec 2025 (prev FY) 200k/236k ACCEPTED; RQ-7 external Oct 50k typed, no GST, SENT; RQ-8 Oct 150k/177k PENDING_APPROVAL (L1 BM, L2 MM). Deals O1 5L Quotation sent closes in 10d; O5 (office B) 10L Negotiation; O2 2L Qualified overdue; O3 won 3L; O4 lost 1L. Two inquiries, one 10 days old.
| Figure | Expected | Shown |
| crm-reports Raised / Won value | 7 / 1,88,800 | 7 / 1,88,800 correct |
| crm-reports monthly | June row | June missing; deal in revision month |
| Command Centre Committed | 4,24,800 | 4,83,800 — superseded revision counted (finevent.php:42) |
| Home Sales won (FY) / Won this FY | 1,88,800 / 2 | 4,24,800 / 3 — no date filter (dashboard.php:442-452) |
| Win rate same day | — | 50% crm-reports (EXPIRED as lost) / 60% home (all time) / 50% crm-dashboard (deals) |
| crm-dashboard Became opportunities, BM A | 100% | 200% unscoped (crmdash.php:184) |
| Due to close in 30 days | 1 | 2 incl overdue |
| Funnel conversion | — | 0%/100% on skipped/terminal stages |
| Weighted forecast, open deals, win rate, cycle time | computed | match |

## Metric definitions
"Incl GST" = quotations.total_amount = subtotal+GST (crm.php:620-626). External quote stores typed total as subtotal AND total, gst_pct 0 (crm.php:1519-1526) → total_amount mixes tax-incl and tax-free.
/crm-dashboard (office scoped): Open deals OPEN ignores window (crmdash.php:112,138); Pipeline value Σ estimate OPEN (:116); Weighted forecast Σ estimate×probability (:117; opportunities.php:237-240); Win rate WON/(WON+LOST) by COALESCE(won_at,updated_at,created_at)≥cut (opportunities.php:259-275); Avg won deal/Days to win (:124-137); Man-days to win/lost from activities (:148-177); Stopped moving (stage SLA); Due in 30 days expected_close≤today+30 incl overdue (:119); Funnel (:49-106); Leads created scoped / Became opportunities UNSCOPED (:183-184); Quotations raised current, ≥cut, office+SBU (:186); Why we lose (opportunities.php:277-290).
/crm-reports (crm.php:3481-3519) current rev, created in FY, office+SBU: Pipeline open = DRAFT,PENDING_APPROVAL,APPROVED,SENT,REJECTED; of X quoted all; Won value ACCEPTED by created month; Win rate ACCEPTED/(ACCEPTED+LOST+EXPIRED); Why we lost LOST+EXPIRED; monthly by current rev created month.
Home (views/dashboard.php:441-452, 513-520, 560) NO date filter though labelled FY: Open quotes/Pipeline DRAFT,PENDING,APPROVED,SENT (excl REJECTED); Won this FY ACCEPTED all time; % win all time; Pending your approval = PENDING steps in scope for anyone holding approve.
Command Centre: Committed Σ every ACCEPTED row incl superseded revisions (finevent.php:40-46, 93-97); Billed/Received/Outstanding.
Attention: Quotations lapsed EXPIRED current (crm.php:1091-1100); Inquiries awaiting a quote OPEN > SLA 7d, SBU scope only, inquiries have no office (crm.php:1261-1298); Contracts expiring soon (ops.php:9414); Quotes to approve current-level step routed to me NO scope (crm.php:3059-3081); Contracts to register (crm.php:2516).
Money concepts: quoted value (GST mixed); weighted pipeline opportunities only; won opp value = deal estimate; accepted quote value three screens three definitions; contracted value partner_contracts.value only, defaults to GST-incl quote total (crm.php:2241-2242), in no report; invoiced/collected Command Centre only. "Pipeline value" 3 meanings, "Win rate" 3 definitions.

## Business questions
Qualified pipeline: partly (no stage marked qualified). Likely to close & when: partly. Enquiries due today: no (only > SLA). Quotes awaiting approval: yes (/quotes?mine=approve), home tile inconsistent. Nearing expiry: no list (7-day banner on one quote, "past validity" pill). Profitable customers/BUs: no (value not margin). Loss reasons: yes, quote reason not server-required. Quote→order conversion: no. Quoted→contracted value: no. Contracted ready for Ops: partly (contracts to register). Contracts at risk: partly (expiring soon).

## Industry templates (industry.php:46-463) — 13 not 12
inspection: Enquiry received 10/7 → Scope agreed 30/10 → Quotation sent 50/14 → Rate negotiation 70/10 → PO/work order 90/7 → Won/Lost; alt funnel "Annual contracts & renewals". Fits; no manpower/deputation path.
manufacturing: RFQ 10 → Technical review 25 → Costing 40 → Offer 55 → Technical approval 75 → Commercial closing 90. Fits.
trading 15→35→55→75→90. services 10→25→45→65→85. construction 5→15→30→50→70→90 long SLAs.
staffing: Requirement received 15 → Terms agreed 35 → Profiles submitted 55 → Interviews 75 → Offer/joining 90; placement loss reasons; no manpower-supply/deputation funnel.
labs, itservices, healthcare, education, logistics, realestate, generic (industry_templates.md).
Applying any template also sets accreditation pack + wording for whole company (F24). No two business lines side by side.
Who configures (EXEC): /industry, /pipelines MA+BD only; quote approval rules, preorder checklist, quote templates MM+MA; lookups (SBU, source, lost reasons) admin-level only (READ).

## Role × action (code vs matrix)
Open leads/opps: MA, BD only — matrix silent; BDM flow doc says they can (contradiction).
Edit inquiries: BM via delete grant implying edit — matrix silent.
Create quote MA, MM, BDM, KAM, ME (consistent). Send MA, MM, BDM, KAM (consistent).
Approve a routed step: anyone it is routed to by role in ANY branch — fn.19 "MM only, not BM" (contradiction).
Mark ACCEPTED/LOST: anyone who views quotes incl BD, BM, FIN — contradiction.
Register contract from quote: FIN (even DRAFT). From won deal: FIN refused 403 — contradiction.
See costing margins: everyone except INS — silent. Edit costings: all but FIN, INS (CO via hiring edit) — silent. Approve costings MA, BD, BM, MM — silent.
Apply industry template MA, BD — Settings Admin-only (contradiction).
SBU scope OWN behaves as ALL — 01-roles says ME is OWN (contradiction).
Ungated routes: /project-costings handler-guarded (opens CO, ME, FIN; refuses INS); /approvals opens for all, shows no quote approvals.
Inactive: approver rule accepts deactivated user → quote stuck; opportunity refuses inactive owner; lead silently falls back to current user (leads.php:422-427 READ).

## Forms
/lead-new: company, + site for man-month (leads.php:399,413); keeps typing.
/opportunity-new: name + customer or name; "Give the opportunity a name — what is the piece of business?"; re-shows typing.
/inquiry-new: client, contact, e-mail or mobile, BU, subject (crm.php:1342-1355); screen shows four "*" none browser-enforced; "Please add a client (pick one, or type a name); the contact person; a contact e-mail or mobile; the business unit (business unit); a subject."
/quote-new: nothing (6 tabs, 0 marked required); empty submit → "Q-00001" DRAFT no client ₹0; refused save loses typing (READ). Fails Zero Training (96 fields, 11-column line table on phone, nothing marked, jargon).
/quote-external: nothing server-side (origin, total browser-only); empty submit → SENT ₹0; status=WHATEVER stored; ACCEPTED 9,00,000 no approval.
Project costing: nothing; empty → "Untitled costing"; office not defaulted.
Inquiry→quote prefilled (crm.php:1680, quote_form.php:5-12) except service requirement; lead→quote prefilled (crm.php:1690-1708).
Terminology: Inquiry vs Enquiry views 48 vs 16, lib 334 vs 84, visible text 7 vs 10; terms.php:37 uses both. Duplicate headings: "Sales dashboard" crm_dashboard.php:3 and crm/reports.php:2 (menu "Sales reports" nav.php:132; home link dashboard.php:513 goes to the other); "Document templates" ×2; "Quote & report approvals" ×2 incl IDEMS; "Approvals" ×2.

## Other
F13 P2 revising an ACCEPTED quote allowed → Committed double count. F14 P2 home FY figures all-time. F15 P2 three definitions of Pipeline value / Win rate; EXPIRED counted as lost vs lifecycle doc. F16 P3 monthly table dates revised deal by revision; Won by created month. F17 P2 unscoped Became opportunities. F18 P3 due-in-30 incl overdue. F19 P3 funnel misleading. F20 P2 external quote. F21 P2 empty quote-new / refused save loses data. F22 P2 quote lost reason not server-required. F23 P2 delete grant implies edit → BD, BM edit rights. F25 P2 template gaps. F26 P2 costing: CO creates & reopens; BM approves DRAFT or own; margins shown to ME and FIN regardless of profitability permission. F27 P2 no-office records: costings visible to all branches; quotes filed under Ahmedabad office (creator loses own quote); inquiries no office. F28 P2 duplicate headings. F29 P3 spelling. F30 P3 crm_orders permission controls nothing. F31 P2 OWN SBU = ALL when own scope blank. F32 P2 missing reports. F33 P3 01-SALES out of date.

## Retain
crm-reports + register current revision only; weighted forecast; history-based funnel; inquiry validation message; lead keeps typing; opportunity re-shows; deal needs loss reason; inactive owner refused; stage gates block self-approval (stagegate.php:166-169) — the model the quote chain lacks; sent/closed quotes locked; FIN can't revise; BM can't one-click approve; scope holds when ?id posted; revisions copy lines/sites; expiry sweep; inquiry/lead prefill quote; inquiries/deals can't be deleted once quoted/won; Sales hub tile wording.
