# Inspection Ops — Master Screen & Function Inventory · v1.0

> **Output of Prompt 1 (Application Discovery).** This is the *locked* single source
> of truth every later prompt cites. Produced by reading the codebase directly:
> the route dispatch (`lib/ops.php`, `index.php`), the portal routers (`lib/portal.php`,
> `lib/cvp.php`), the navigation builder (`lib/areas.php`), `ACCESS_MODULES` /
> `PERMISSIONS` / `ORG_ROLES` (`lib/access.php`), the view files, and the
> `CREATE TABLE` / `ensure_column` / `const …STATUS…` / `setting_*` statements
> across `lib/`.

| | |
|---|---|
| **Application** | Inspection Ops — multi-tenant Third-Party Inspection Agency (TPIA) platform |
| **Stack** | PHP, front controller `index.php`, module handlers in `lib/`, views in `views/`; SQLite (dev/single) or MySQL (multi-user) |
| **Standard** | ISO/IEC 17020-aligned |
| **Repository / branch** | `mehulblogger-pixel/Experiment_Operataion_Exaact-requirement` · `claude/quotation-management-workflow-5dokb2` |
| **Inventory version** | **v1.0** (lock this before running Prompt 2) |
| **Method** | Static discovery from source; no runtime guessing. Items that could not be evidenced are marked `⚠ confirm`. |

## Revision history

| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp on lock) | QA (Prompt 1) | Initial exhaustive discovery. |

---

## 0. Coverage self-check (counts discovered)

| Thing counted | Count | Source of truth |
|---|---:|---|
| Navigation areas | **9** | Sales, Quality, Reporting, Money, Insights, Directory, Admin, Operations, + external Portals (`lib/areas.php`, `lib/ops.php`) |
| Navigation tiles (labelled screens) | **83** | `$t(…)` calls in `lib/areas.php` |
| Route cases — exact (`$route === '…'`) in `lib/ops.php` | **≈325** | dispatch switch |
| Route cases — grouped (`in_array`) | **≈70** | dispatch switch |
| Route cases — prefix (`strncmp`) | **≈27** | dispatch switch |
| Top-level routes in `index.php` | **22** | root dispatch |
| Portal + vendor-portal routes | **≈34** | `lib/portal.php`, `lib/cvp.php` |
| Route→module map entries | **≈425** | module map in `lib/ops.php` (incl. some ref/opt entries) |
| Application modules (real) | **31** | `ACCESS_MODULES` + operations spine |
| Permissions | **147** | `PERMISSIONS` |
| Roles (internal) | **16** | `ORG_ROLES` |
| External role families | **2** | client-portal, vendor-portal |
| Database tables | **210** | `CREATE TABLE IF NOT EXISTS` |
| Lifecycle / status constant sets | **≈60** | `const …STATUS/STATES/STAGES/RESULTS/DECISIONS…` |
| Configurable settings keys | **216** | `setting_get/set('…')` |
| Master / lookup lists | **≈74** | `lk_options_or('…')` + `lookup_types` |
| Report types (seeded) | **37** | `IDEMS_REPORT_SEED` (+ built form types) |
| Output builders (PDF/DOCX/CSV) | **≈27** | `*_pdf_build`, `*_docx_*`, `*_export`, `*_csv` |

> **Areas that need a confirming read during Prompt 2/3:** the ~325 exact route cases
> exceed the 83 labelled tiles because many screens are sub-actions (edit/new/status/
> delete/approve endpoints) reached from a parent screen, not from the menu. Prompt 3
> per module enumerates those sub-actions field-by-field. This inventory lists them by
> **route family per module** (§2) so none is lost.

---

## 1. Areas & screen map

IDs: `SCR-<AREA>-<slug>`. "Roles" = the primary roles the tile/permission targets
(full matrix in §3). Purpose text is the app's own tile description.

### 1.1 Sales  `AREA-SALES`
| Screen ID | Route | Purpose | Primary roles |
|---|---|---|---|
| SCR-SALES-leads | `/leads` | Leads / enquiries not yet qualified | Sales, Marketing, Master |
| SCR-SALES-opportunities | `/opportunities` | Qualified deals being worked | Sales, Master |
| SCR-SALES-inquiries | `/inquiries` | Requests for a quotation | Sales, Coordinator |
| SCR-SALES-quotes | `/quotes` | Quotations, revisions, approvals | Sales, Marketing Mgr, Master |
| SCR-SALES-preorder-checklist | `/preorder-checklist` | Pre-order (enquiry/tender/contract) review checklist config | Master, settings.manage, quote.approve |
| SCR-SALES-project-costings | `/project-costings` | Team cost build-ups → man-month/day/lump + margin | pc_can |
| SCR-SALES-approvals | `/approvals` | Deals held at a stage gate | gate viewers |
| SCR-SALES-crm-dashboard | `/crm-dashboard` | Win rates and value by stage | crmdash_can |
| SCR-SALES-pipelines | `/pipelines` | Pipelines, stages, conversion | pipe viewers |
| SCR-SALES-ads-roi | `/ads-roi` | Advertising spend vs leads produced | roi_can |

Route family (sub-actions): `lead lead-new lead-edit lead-move lead-convert lead-delete lead-contact lead-file(s) lead-file-delete leads-bulk · opportunity opportunity-new/edit/move/from-lead/quote/raise-order · pipeline(s) pipeline-new/save/default/stage-add/stage-delete · stage-gates stage-gate-save/delete approval-act approvals · inquiries inquiry-new/edit · adspro adspro-save/import/spend/sync/test/backfill ads-roi`.

### 1.2 Quality  `AREA-QUALITY`
| Screen ID | Route | Purpose | Primary roles |
|---|---|---|---|
| SCR-QLT-equipment | `/equipment` | Instruments & calibration status | Quality, Coordinator |
| SCR-QLT-samples | `/samples` | Items received for verification | Quality |
| SCR-QLT-methods | `/methods` | Standards & methods applied | Quality |
| SCR-QLT-drules | `/drules` | Statements-of-conformity decision rules | Quality |
| SCR-QLT-cdocs | `/cdocs` | Controlled document set | Quality, Master |
| SCR-QLT-retention | `/retention` | Records retention schedule | Master |
| SCR-QLT-data-control | `/data-control` | Data & information control | Master |
| SCR-QLT-risks | `/risks` | Risk & opportunity register | Master |
| SCR-QLT-competence | `/competence` | Training, assessment & authorisation | Master, Managers |
| SCR-QLT-impartiality | `/impartiality` | Impartiality threats & controls | Master |
| SCR-QLT-disclosure | `/disclosure` | Consents to disclose | Master |
| SCR-QLT-ncr | `/ncr` | Nonconformity (NCR) register | Quality, Coordinator |
| SCR-QLT-issues | `/issues` | Issue / deviation / concession log (NCDCA) | Quality |
| SCR-QLT-hold-points | `/hold-points` | Hold & witness points | Coordinator, Inspector |
| SCR-QLT-capa | `/capa` | Corrective actions (CAPA) | Quality, Managers |
| SCR-QLT-internal-audits | `/internal-audits` | Internal audit programme | Master |
| SCR-QLT-management-reviews | `/management-reviews` | Management review records | Master |
| SCR-QLT-evidence-review | `/evidence-review` | Field evidence awaiting review | Coordinator |
| SCR-QLT-complaints | `/complaints` | Complaints & appeals register | Quality, Managers |
| SCR-QLT-satisfaction | `/satisfaction` | Customer satisfaction surveys | Master |
| SCR-QLT-report-reviews | `/report-reviews` | Client acceptance of reports | Coordinator |
| SCR-QLT-confidentiality | `/confidentiality` | Undertakings, NDAs, breaches | Master |
| SCR-QLT-site-docs | `/site-docs` | Papers needed for site access | Coordinator |
| SCR-QLT-identity | `/identity` | ID documents that gate site access | Coordinator, Master |
| SCR-QLT-portal-users | `/portal-users` | Client portal users & requests | Master, Managers |
| SCR-QLT-vendor-users | `/vendor-users` | Vendor portal access | Master, Managers |
| SCR-QLT-analytics | `/analytics` | KPI dashboards & drill-down (TAPI) | dashboards |

Route families: `equipment equip-new/edit/cal-add/cal-del equip-cert report-equip-add/del · competence auth-add/status/enforce witness-add · impartiality imp-declare/type/threat-add/threat-decide · ncr ncr-new/item/contain/disposition/capa/assign/close/reopen · issues issue-classify/extend departures departure-new/status dispute-new/decide extension-approve · capa capa-new/plan/cause/action-add/done/cancel/reopen/verify/close/escalate/from-complaint/settings · complaints complaint-new/ack/investigate/decide/close/reopen/capa/notify/validity/settings · internal-audits internal-audit-new audit-record audit-finding-add/capa/delete audit-close audit-settings · management-reviews management-review-new review-header/input/action-add/action-done/complete/refresh · confidentiality conf-undertaking-add conf-nda-add conf-breach-add/close · identity iddoc-add/file/reveal/redact/share/retention site-docs site-docs-add/delete · hold-points hw-point-derive · data-control data-check-run failure-add/update/capa/resolve sw-validation-add`.

### 1.3 Reporting (IDEMS)  `AREA-REPORTING`
| Screen ID | Route | Purpose | Primary roles |
|---|---|---|---|
| SCR-RPT-documents | `/documents` | The report register | Inspector, Coordinator, Master |
| SCR-RPT-document-new | `/document-new` | Start a new report | mod.idems.edit |
| SCR-RPT-document | `/document?id=` | A single report (detail + playbook + tabs) | mod.idems.view |
| SCR-RPT-document-fill | `/document-fill?id=` | Fill the report body | mod.idems.edit (DRAFT/REJECTED only) |
| SCR-RPT-vet-review | `/document-vet-review?id=` | Side-by-side vetting (report + checklist) | idems.finalize / master |
| SCR-RPT-vetting-checklist | `/vetting-checklist` | Vetting checklist & gate config | Master, idems.type.manage, finalize |
| SCR-RPT-release-notes | `/release-notes` | Release note register | Coordinator, Master |
| SCR-RPT-endorsements | `/endorsements` | Manufacturer document endorsements | Coordinator |
| SCR-RPT-vendors | `/vendors` | Vendor register & profiles (IDEMS view) | Managers |
| SCR-RPT-expediting | `/expediting` | Expediting register | Coordinator |
| SCR-RPT-expediting-projects | `/expediting-projects` | Multi-vendor projects by milestone | Coordinator |
| SCR-RPT-writing-assistant | `/writing-assistant` | Technical writing / phrase library | Inspector |
| SCR-RPT-learning | `/learning` | Suggestions learned from past reports | Master |
| SCR-RPT-approver-map | `/approver-map` | Who signs which report | Master, idems.type.manage |
| SCR-RPT-approval-rules | `/idems-approval-rules` | Approval routing rules | Master, idems.type.manage |
| SCR-RPT-report-types | `/report-types` | Report-type catalogue (+ readiness, curation) | Master, idems.type.manage |
| SCR-RPT-report-builder | `/report-builder?type=` | No-code form designer | Master, idems.type.manage |
| SCR-RPT-report-autoform | `/report-autoform` | Auto-design a form from an uploaded template | Master |
| SCR-RPT-templates | `/report-templates` | Company Word template library | Master |
| SCR-RPT-irn-rules | `/irn-rules` | IRN numbering rules | Master, idems.type.manage |
| SCR-RPT-audit-log | `/audit-log` | Tamper-evident audit trail | Master, idems.audit.view |
| SCR-RPT-compliance | `/compliance` | "Where we stand" — obligations met, live | Master |

Route families: `documents document document-new/edit/fill/submit/vet/vet-review/approve/finalize/delete/pdf/docx/timestamp/evidence/review/smart/ai-review/scope-from-qap/polish-text/release-note/rn-flag report-ack · report-types report-type-edit/preview report-builder report-field-edit report-file report-autoform report-form-from-template · report-templates report-template-edit/preview/download · release-notes · endorsements endorsement endorsement-new/edit/submit/approve/file/cert/delete · expediting expediting-projects · approver-map idems-approval-rules idems-approval-rule-edit irn-rules · vetting-checklist · phrase-library phrase-edit learning · evidence-review evidence-reviewed · audit-log · vendors vendor-profile(-save)`.

### 1.4 Money  `AREA-MONEY`
| Screen ID | Route | Purpose | Primary roles |
|---|---|---|---|
| SCR-MON-invoicing | `/invoicing` | Invoice register | Finance, Coordinator |
| SCR-MON-to-bill | `/to-bill` | Closed work not yet invoiced | Finance, Coordinator |
| SCR-MON-invoices | `/invoices` | Invoices with lines & tax | Finance |
| SCR-MON-receipts | `/receipts` | Receipts matched to invoices | Finance |
| SCR-MON-receivables | `/receivables` | Receivables ageing | Finance |
| SCR-MON-tally | `/tally` | Export to the accounts package | Finance |
| SCR-MON-books-bridge | `/books-bridge` | MGH Books accounts bridge | Finance, Master |
| SCR-MON-profitability | `/profitability` | Profit & margin by job | Managers, Finance |

Route families: `invoicing invoice invoice-new/issue/cancel/line-add/line-delete job-bill job-invoice to-bill · receipts receipt receipt-new/allocate/unallocate receivables credit-note-new · ledger tally tally-export/settings/undo · profitability sbu-pl call-profit boss-renew`.

### 1.5 Insights  `AREA-INSIGHTS`
| Screen ID | Route | Purpose | Primary roles |
|---|---|---|---|
| SCR-INS-reports | `/reports` | Role dashboards across the business | dash.* |
| SCR-INS-analytics | `/analytics` | KPI cards, trends, drill-down (TAPI) | tapi_can |
| SCR-INS-mis | `/mis` | Management dashboard (MIS) | Managers |

Route families: `reports analytics analytics-kpis/kpi-edit/quality/drill/export/scorecard/alerts/review/snapshot/targets mis`.

### 1.6 Directory  `AREA-DIRECTORY`
| Screen ID | Route | Purpose | Primary roles |
|---|---|---|---|
| SCR-DIR-activities | `/activities` | Timeline of what happened | act viewers |
| SCR-DIR-clients | `/clients` | Client register | Managers, Coordinator |
| SCR-DIR-vendors | `/vendors` | Vendor register | Managers, Coordinator |
| SCR-DIR-client-holds | `/client-holds` | Put a client on hold / block before ordering | Master, settings.manage, coordinator+ |
| SCR-DIR-partner | `/partner?id=` | A single company (contacts, contracts, POs, sites) | Managers |

Route families: `clients client customer customer-parent partner partner-new/edit/add partner-import partner-template activities activity-add duplicates client-holds`.

### 1.7 Admin  `AREA-ADMIN`
| Screen ID | Route | Purpose | Primary roles |
|---|---|---|---|
| SCR-ADM-masters | `/masters` | The lists behind every dropdown | master.manage |
| SCR-ADM-lookups | `/lookups` | All dropdown lists (+ dependent lists) | master.manage |
| SCR-ADM-office-finance | `/office-finance` | Per-office cost model | overheads viewers |
| SCR-ADM-cost-run | `/cost-run` | Month-end cost run | overheads |
| SCR-ADM-sbu-pl | `/sbu-pl` | P&L by business unit | profitability |
| SCR-ADM-call-profit | `/call-profit` | Profit per inspection | profitability |
| SCR-ADM-mis | `/mis` | MIS overview | Managers |
| SCR-ADM-users | `/users` | People who can sign in | users.view/manage |
| SCR-ADM-hierarchy | `/hierarchy` | The reporting hierarchy / org chart | users viewers |
| SCR-ADM-access | `/access` | Roles & permissions | Master |
| SCR-ADM-sso | `/sso` | Single sign-on settings | Master |
| SCR-ADM-settings | `/settings` | Company-wide settings & terminology | settings.manage |
| SCR-ADM-terminology | `/terminology` | Rename business nouns | settings.manage |
| SCR-ADM-service-scope | `/service-scope` | Which services are offered & where | Master |
| SCR-ADM-service-formats | `/service-formats` | Report format each service allocates | Master |
| SCR-ADM-sla-targets | `/sla-targets` | Turnaround targets | Managers, Master |
| SCR-ADM-company-profile | `/company-profile` | Legal name, logo, details | Master |
| SCR-ADM-super-admin | `/super-admin` | Licence, seats, modules, subscription, tenants, tools | Master |
| SCR-ADM-licence | `/licence` | Product licence & state | Master |
| SCR-ADM-adspro | `/adspro` | Advertising source connection | Master |
| SCR-ADM-ai-settings | `/ai-settings` | AI provider config (per tenant) | Master |
| SCR-ADM-industry | `/industry` | Industry vocabulary packs | Master |

Route families: `masters work-norms lookups lookup(+value edit) custom-fields · users user-new/edit/retire/unlock/2fa-reset hierarchy org-template · access ai-settings sso · settings terminology industry industry-apply packs-save service-scope service-formats sla-targets company-profile preflight reset-data trace-audit(+thread) · office-finance cost-run sbu-pl call-profit mis · super-admin licence adspro books-bridge`.

### 1.8 Operations  `AREA-OPS` (own home `/operations`, not tile-based)
| Screen ID | Route | Purpose | Primary roles |
|---|---|---|---|
| SCR-OPS-home | `/operations` | Operations landing (all op screens, live state) | Ops roles |
| SCR-OPS-calls | `/calls` | Call (work-order) register | Coordinator, Managers |
| SCR-OPS-call-new | `/call-new` | Register a call | Coordinator |
| SCR-OPS-call | `/call?id=` | Call detail (lead-time, forward, clarifications) | Coordinator |
| SCR-OPS-jobs | `/jobs` | Job / deputation register | Coordinator, Managers |
| SCR-OPS-job-allocate | `/job-new`,`/job-edit` | Allocate a job (per-date inspectors, multi-day) | Coordinator |
| SCR-OPS-job | `/job?id=` | Job detail (visits, check-in, closure, glance) | Coordinator, Inspector |
| SCR-OPS-job-close | `/job-close?id=` | Close a job (report + attendance gates) | Coordinator |
| SCR-OPS-schedule | `/schedule` | Scheduling board + capacity + best-inspector | Coordinator |
| SCR-OPS-availability | `/availability` | Inspector daily availability board | Coordinator |
| SCR-OPS-deputations | `/deputations` | Deputation & site-ops (PDSO) | Coordinator |
| SCR-OPS-my-jobs | `/my-jobs` | Inspector's own jobs (weekly/monthly) | Inspector |
| SCR-OPS-vouchers | `/vouchers` | Voucher / expense entry | Inspector, Finance |
| SCR-OPS-attendance-recon | `/attendance-recon` | Attendance reconciliation | Coordinator, Finance |
| SCR-OPS-recruitment | `/recruitment` | Recruitment command centre | hiring roles |
| SCR-OPS-candidates | `/candidates` | Candidate pipeline | hiring roles |
| SCR-OPS-requisitions | `/requisitions` | Manpower requisitions | hiring roles |
| SCR-OPS-ops-desk | `/ops-desk` | Operations desk (TOSRM) | Coordinator |

Route families: `calls call call-new/edit/status/delete/override call-clar-new/respond/status call-nudges recurring contract-override(s) client-quotes quote-context · jobs job job-new/edit/close/confirm/advance/reassign/unlock/qap(-upload/del)/visit-close schedule availability capacity-outlook deputations dep-* assign-* checkin-photo report-approve ops-desk · vouchers voucher entries expense-delete bill-add/delete/file attendance-recon holidays · candidates candidate candidate-new/edit/stage/cv/credential/commercial/client/link-person/erase requisitions requisition-new/edit recruitment(-cc) recruit-config req-ai-extract`.

### 1.9 External — Portals  `AREA-PORTAL`
| Screen ID | Route | Purpose | Roles |
|---|---|---|---|
| SCR-PORTAL-login | `/portal/login` `/portal/accept` | Client-portal sign-in / invite accept | client-portal |
| SCR-PORTAL-home | `/portal` | Client's reports, invoices, requests, complaints | client-portal (QUALITY / COMMERCIAL perms) |
| SCR-PORTAL-report-decide | `/portal` (reports.decide) | Client accepts / rejects an issued report | client-portal QUALITY |
| SCR-VPORTAL-login | `/vendor/login` `/vendor/accept` | Vendor-portal sign-in / invite accept | vendor-portal |
| SCR-VPORTAL-issues | `/vendor/issues` `/vendor/issue` | Vendor's inspection outcomes / external NCRs | vendor-portal |
| SCR-PUBLIC-verify | `/verify` | Public QR verification of an issued report | anyone with the code |
| SCR-PUBLIC-buy | `/buy` `/buy-verify` | Purchase / licence flow | prospect |
| SCR-SETUP | `/setup` `/setup-save` `/setup-db` | First-run setup (SQLite/MySQL) | installer |

---

## 2. Module register  (real modules only)

IDs: `MOD-<code>`. "Tables" lists the primary tables the module owns (full list §10).

| Module ID | Name | Area | Route family (representative) | Owns tables (primary) |
|---|---|---|---|---|
| MOD-LEADS | Leads & pipeline | Sales | leads, lead-*, opportunities, opportunity-*, pipelines, stage-gates, approvals, adspro, ads-roi | leads, lead_files, lead_stage_history, opportunities, opportunity_quotes, opportunity_stage_history, pipelines, pipeline_stages, stage_gates, stage_gate_requests, ads_leads, ads_spend, ads_outbox, ads_sync_log |
| MOD-INQUIRIES | CRM inquiries | Sales | inquiries, inquiry-new/edit | crm_inquiries |
| MOD-QUOTES | Quotations | Sales | quotes, quote-*, preorder-checklist, contract-open | quotations, quote_lines, quote_locations, quote_approvals, quote_approval_rules, quote_revisions, quote_files, quote_followups, quote_edit_requests |
| MOD-CRMORD | Orders / contracts | Sales | contract-*, opportunity-raise-order | partner_contracts, partner_purchase_orders, contract_overrides, billing_orders |
| MOD-PROJCOST | Project costing | Sales | project-costings, project-costing | project_costings, project_costing_lines |
| MOD-IDEMS | Inspection reports (IDEMS) | Reporting | documents, document-*, report-*, release-notes, endorsements, vetting-checklist, approver-map, irn-rules, audit-log | report_types, report_sections, report_fields, report_docs, report_files, report_approvals, report_vetting, report_templates, report_statuses, report_client_reviews, report_doc_review, report_equipment, idems_approval_rules, idems_approver_map, idems_counters, idems_audit, endorsements, endorsement_files, tech_phrases, learned_suggestions, evidence_chain, release_inspections, release_rules, qap_scope_cache, report_lib_* |
| MOD-CALLS | Calls (work orders) | Operations | calls, call-*, recurring | calls, call_clarifications, call_nudges, call_status_events, contract_overrides, recurring_services |
| MOD-JOBS | Jobs / deputations | Operations | jobs, job-*, schedule, deputations, dep-*, assign-* | jobs, job_visits, job_qaps, job_bills, job_delays, job_readiness, site_visits, assignment_events, dep_checklist, dep_manpower, dep_site_log, dep_site_history, dep_timesheet, dep_att_approval, dep_status_events, hw_points |
| MOD-VOUCHERS | Vouchers / expenses | Money/Ops | vouchers, voucher-entries, expense-* | vouchers, voucher_entries, expenses, expense_heads, inspector_allowances, travel_modes |
| MOD-INVOICING | Invoicing | Money | invoicing, invoice-*, receipts, receivables, tally, credit-note | invoices, invoice_lines, receipts, receipt_allocations, credit_notes, tally_exports, billing_orders, books_outbox |
| MOD-PROFIT | Profitability | Money/Insights | profitability, sbu-pl, call-profit | cost_allocations, cost_runs, credit_recon |
| MOD-OVERHEADS | Overheads (office finance) | Admin | office-finance, cost-run | office_expenses, office_expense_heads, cost_runs |
| MOD-RECONCILE | Attendance reconcile | Ops/Money | attendance-recon | attendance, credit_recon, inspector_day_status |
| MOD-HIRING | Recruitment / workforce | Operations | candidates, requisitions, recruitment | candidates, candidate_events, requisitions, back_office_staff, person_documents, person_document_access, person_sbu_split, qualifications |
| MOD-CLIENTS | Clients | Directory | clients, client, partner-*, client-holds | business_partners, partner_contacts, partner_addresses, partner_registrations, partner_notes, partner_relationships |
| MOD-VENDORS | Vendors | Directory/Reporting | vendors, vendor-profile | business_partners, vendor_profiles, vendor_qualifications, vendor_status_events, vendor_audit, vendor_km_memory |
| MOD-EQUIP | Equipment & calibration | Quality | equipment, equip-*, report-equip | equipment, equipment_calibrations, report_equipment |
| MOD-COMPETENCE | Competence & authorisation | Quality | competence, auth-*, witness-* | inspector_certs, authorisations, witness_assessments, qualifications |
| MOD-IMPART | Impartiality & conflicts | Quality | impartiality, imp-* | impartiality_declarations, impartiality_threats |
| MOD-IDENTITY | Identity documents | Quality | identity, iddoc-*, site-docs | person_documents, person_document_access, site_doc_requirements, data_consents |
| MOD-COMPLAINTS | Complaints & appeals | Quality | complaints, complaint-* | complaints, complaint_events, satisfaction_surveys |
| MOD-NCR | Nonconformities | Quality | ncr, ncr-*, issues, departures, disputes | nonconformities, ncr_events, issue_departures, issue_disputes, issue_extensions |
| MOD-CONF | Confidentiality | Quality | confidentiality, conf-* | confidentiality_undertakings, confidentiality_breaches, client_ndas, disclosure_consents |
| MOD-CAPA | Corrective actions | Quality | capa, capa-* | capa, capa_actions, capa_events |
| MOD-AUDITS | Internal audits & mgmt review | Quality | internal-audits, audit-*, management-reviews, review-* | internal_audits, audit_findings, audit_criteria(_packs), audit_conclusion_rules, mgmt_reviews, mr_inputs, mr_actions |
| MOD-DATACTRL | Data & information control | Quality/Admin | data-control, data-check-*, failure-*, sw-validation | data_check_runs, system_failures, sw_validations, controlled_docs, retention_rules, security_incidents |
| MOD-PORTAL | Client portal | Directory/ext | portal, portal-*, /portal/* | client_users, portal_requests, portal_notifications, portal_audit, report_client_reviews |
| MOD-VPORTAL | Vendor portal | ext | vendor-users, vendor-*, /vendor/* | vendor_users, vendor_status_events |
| MOD-MASTERS | Masters (lookups) | Admin | masters, lookups, lookup, custom-fields, work-norms | lookup_types, lookup_values, custom_fields, custom_forms, custom_records, custom_values, work_norms, holidays |
| MOD-REPORTS | Dashboards / analytics (TAPI) | Insights | reports, analytics, analytics-*, mis | kpi_defs, kpi_versions, kpi_targets, kpi_snapshots, kpi_alerts, analytics_periods, scorecards, scorecard_items |
| MOD-USERS | Users & access | Admin | users, user-*, hierarchy, access, sso | users, user_prefs, login_attempts, sso_attempts |
| MOD-SETTINGS | Settings / config | Admin | settings, terminology, industry, service-scope/-formats, sla-targets, company-profile, super-admin, licence, ai-settings | settings, service_catalog, service_dependencies, service_report_map, service_scope, sla_targets, issued_licences, agencies |

Additional cross-cutting entities not owned by a single module: `offices`, `subcons`, `subcon_rates`, `boss_numbers`, `holidays`, `form_tokens`, `email_log`, `install_beats`, `sample_items`, `sample_custody`, `methods`, `decision_rules`, `risk_items`.

---

## 3. Role & permission matrix

**Internal roles (`ORG_ROLES`, 16):** MASTER_ADMIN, BUSINESS_DIRECTOR, SBU_HEAD,
BRANCH_MANAGER, BRANCH_APP_MANAGER, OPERATION_MANAGER, ASST_MANAGER, COORDINATOR,
BUSINESS_DEV_MANAGER, KEY_ACCOUNTS_MANAGER, MARKETING_MANAGER, MARKETING_EXECUTIVE,
FINANCE, **SR_INSPECTOR (Senior Inspector)**, INSPECTOR, ADMIN (legacy).
**External:** client-portal users (perm sets **QUALITY** = accepts reports/no money,
**COMMERCIAL** = reports+invoices/cannot accept), vendor-portal users.

**Module access by role** (from `module_defaults`; `E`=edit, `V`=view, `–`=none):

| Role | idems | calls | jobs | quotes | invoicing | clients | vendors | masters | users | reports | NCR/CAPA/audits | Scope |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| MASTER_ADMIN / ADMIN | E | E | E | E | E | E | E | E | E | E | E | ALL / ALL |
| BUSINESS_DIRECTOR | V | V | V | V | V | V | V | V | – | V | V | ALL / ALL |
| SBU_HEAD | V | V | V | V | V | V | V | V | – | V | V | ALL / OWN |
| BRANCH_MANAGER | E | E | E | V | V | E | E | E | E | E | E | OWN / ALL |
| BRANCH_APP_MANAGER | V | V | V | – | – | – | – | E | E | V | – | OWN / ALL |
| OPERATION_MANAGER | E | E | E | V | – | V | V | V | – | V | E | OWN / OWN |
| ASST_MANAGER | E | E | E | – | – | V | V | – | – | V | V(capa) | OWN / OWN |
| COORDINATOR | E | E | E | V | V | V | V | V | – | V | E | OWN / OWN |
| BUSINESS_DEV / KEY_ACCTS | – | – | – | E | – | V | – | – | – | V | – | OWN / ALL |
| MARKETING_MANAGER | – | – | – | E(+approve) | – | E | – | – | – | V | – | ALL / ALL |
| MARKETING_EXECUTIVE | – | – | – | E | – | V | – | – | – | – | – | OWN / OWN |
| FINANCE | V | V | V | V | E | – | – | – | – | V | – | ALL / ALL |
| SR_INSPECTOR | E (+vet/finalize) | – | – | – | – | – | – | – | – | – | – | OWN / OWN |
| INSPECTOR | E | – | – | – | – | – | – | – | – | – | – | OWN(self) / OWN |
| client-portal | (own reports) | – | – | – | (own, COMMERCIAL) | – | – | – | – | – | – | own company |
| vendor-portal | (own outcomes) | – | – | – | – | – | – | – | – | – | – | own company |

> The full 16-role × 31-module × {view/edit/act} grid, with the **147 permissions** and
> the **data-scope** (office/branch, business-unit) per role, is generated by Prompt 2
> (§9 personas) and proven per module by Prompt 3 §F — including the **negative** cells
> (what each role must not do) and the **crafted-request** checks.

**Permission catalogue:** 147 permissions in `PERMISSIONS` + `module_defaults`
(`mod.<x>.view/edit`) — e.g. `dash.*`, `data.credit/revenue/salary/profitability`,
`ops.call.create/…`, `ops.job.allocate/close`, `idems.finalize/type.manage/
timestamp.edit/audit.view`, `crm.quote.create/approve/send`, `users.manage.*`,
`settings.manage`, `master.manage`, `workforce.report.approve`, and the module
view/edit pairs. Enumerate in full during Prompt 2.

---

## 4. Status / lifecycle register

The application defines **≈60** status/state constant sets. The stateful entities and
their states:

| Entity | Constant | States |
|---|---|---|
| Inspection report | `IDEMS_STATUS` | DRAFT → SUBMITTED → **VETTING** → UNDER_REVIEW → APPROVED → ISSUED; REJECTED (sent back); ARCHIVED. Open set: `IDEMS_OPEN_STATES` = DRAFT/SUBMITTED/VETTING/UNDER_REVIEW/REJECTED. Edit allowed only in DRAFT/REJECTED; ISSUED = immutable. |
| Vetting | `IDEMS_VET_STATUS` | VETTED, RETURNED (for correction), DEBRIEFED |
| Approval step | `IDEMS_APPR_STATUS` | PENDING → APPROVED / REJECTED / SENTBACK (per level) |
| Report result / release | `IDEMS_RESULTS` / `release_status` | ACCEPTED, ACCEPTED_COND, REJECTED, HOLD, NA; RELEASED / RELEASED_COND |
| Client acceptance | `RCR_DECISIONS` | ACCEPTED, REJECTED (portal) |
| Endorsement | `ENDORSE_STATUS` | UPLOADED → UNDER_REVIEW → ENDORSED / REJECTED; ARCHIVED |
| Quotation | `QUOTE_STATUS` (+ `QUOTE_PENDING_STATES`) | DRAFT → PENDING_APPROVAL → APPROVED → (sent) → WON/LOST/REJECTED; locked after close |
| Lead / Opportunity | `LEAD_STATUS` / `OPP_STATUS` | OPEN → CONVERTED/LOST · OPEN → WON/LOST (+ configurable stages) |
| Stage gate | `GATE_STATUS` | pending/approved/rejected |
| Contract | `CONTRACT_STATES` / `CONTRACT_OPEN_STATES` | NONE/OK/EXPIRING/EXHAUSTED · PENDING/OPEN/REJECTED |
| Call | `TOSRM_*` / `call_status_events` | OPEN → FORWARDED → … ; clarification `TOSRM_CLAR_STATUS`; ready `TOSRM_READY_STATUS`; SLA `TOSRM_SLA_STATUS/STAGES` |
| Job | `JOB_STAGES` | ALLOCATED → TRAVELLING → IN_PROGRESS → REPORT_PENDING → SUBMITTED → CLOSED; ON_HOLD; CANCELLED |
| Assignment | `TOSRM_ASSIGN_STATES` | TENTATIVE / CONFIRMED / RELEASED |
| Deputation (PDSO) | `PDSO_STATUS` (+ MOB/LOG/APPROVAL) | PLANNED→APPROVED→MOB_PENDING→MOBILIZED→ACTIVE→(ON_LEAVE/SUSPENDED/REPLACEMENT_REQ)→DEMOB_PLANNED→DEMOBILIZED→CLOSED; CANCELLED |
| NCR | `NCR_STATUS` | OPEN → CONTAINED → DISPOSITIONED → … → CLOSED |
| Issue / departure / dispute | `NCDCA_DEPARTURE_STATUS` / `NCDCA_DISPUTE_STATUS` / `UIRE_POINT_STATUS` | departure & dispute lifecycles; hold/witness point OPEN/CLOSED (`HW_POINT_STATUS`) |
| CAPA | `CAPA_STATUS` | OPEN → IN_PROGRESS → VERIFYING → … → CLOSED |
| Complaint / appeal | complaint events | NEW → ACK → INVESTIGATE → DECIDE → CLOSE; REOPEN |
| Internal audit / review | `AUDIT_STATUS` | PLANNED → IN_PROGRESS → REPORTED → … CLOSED |
| Invoice | `INV_STATUS` | DRAFT → ISSUED → PART_PAID → PAID; CANCELLED (`GST_STATES`, `FEE_STATUS`) |
| Candidate / requisition | `CAND_STAGES` / `REQ_STATUS` | RECEIVED→SUBMITTED→SHORTLISTED→… · OPEN/PROPOSED/OFFERED/HIRED/CLOSED/CANCELLED/HOLD |
| Portal request | `PORTAL_REQ_STATUS` | requested/approved/declined |
| Client hold | `PARTNER_HOLD_STATES` | HOLD / BLOCKED |
| Equipment / authorisation | `EQUIP_STATUS` / `AUTH_STATUS` | valid/expiring/expired · authorised/pending/lapsed |
| Confidentiality breach | `CONF_BREACH_STATUS` | open/closed |
| Licence | `LICENCE_STATES` | trial/active/expired/suspended |
| Others | `ATT_STATUS`, `AVAIL_STATUS`, `BOSS_STATUS`, `OVERRIDE_STATUS`, `FAILURE_STATUS`, `TEMPLATE_STATUS`, `DOC_REVIEW_STATES`, `SW_RESULTS`, `TAPI_PERIOD_STATES`, `ADS_OUT_STATUS`, `UVAE_RESULTS`, `UVAAE_*` | audit/assessment engine states |

Each transition's **trigger, actor, lock effect and notification** is enumerated per
module in Prompt 3 §E, and end-to-end in Prompt 4.

---

## 5. Settings register  (216 keys)

Grouped by concern (test each **on and off** where boolean):

- **Identity / branding:** app_name, company_* (legal_name, name, address, email, phone, state, gstin, pan, website), logo_data, brand_color, report_brand_color, theme_preset, c_* / font_size / date_format / currency_symbol.
- **Numbering & reports:** idems_irn_format, idems_company_code, idems_serial_width, numbering_*, invoice_series, report_blank_fill, inspection_body_type, release_note_disclaimer, release_note_label, release_statement_default, expected_source_docs, require_final_docs_on_close, notify_client_on_issue.
- **Vetting / release / pre-order (this cycle):** vetting_gate_required, vetting_checklist_on, vetting_checklist_require, vetting_checklist_items, rn_require_client_acceptance, preorder_checklist_on, preorder_checklist_require, preorder_checklist_items.
- **Workflow timings / SLA:** report_escalate_days, tat_threshold_days, dsr_target_days, job_close_grace_days, job_lock_enabled, contract_idle_close_days, contract_warn_days, complaint_ack_days, complaint_decide_days, capa_due_days, capa_verify_days, audit_cycle_days, audit_high_risk, audit_retain_days, vendor_reminder_days, vendor_requal_months, iddoc_retain_days, user_retire_days.
- **Work norms:** daily_hours_cap, half_day_hours, default_weekly_days, manmonth_basis/min_days, idle_basis, contingency_pct, overhead_pct, fy_current, fy_start_month, fy_revenue_target.
- **AI:** ai_config (provider/keys/enabled).
- **Access / security:** role_access, role_access_modules, seat_field_roles, twofa_roles, session_idle_min, session_max_hours, pwd_min_len, pwd_max_age_days, pwd_default_cache, licence_* (key, enforce, server, pubkey, install, checked_at…), modules_off, packs_enabled.
- **Portals / satisfaction:** portal_enabled, vendor_portal_enabled, billing_portal_free, satisfaction_on/scale/followup_below.
- **Billing / licensing:** billing_* (seats, currency, customer, paid_until, price_* per field/portal/user month/year), rzp_key_*, upload_max_mb.
- **Mail:** smtp_* (host/port/user/pass/from), certin_email, grievance_* , vendor_reminder_email.
- **Integrations:** books_* (api_url/token/app_url/connected/dryrun), cpanel_* (host/port/user/token/docroot/rootdomain/make_subdomain/verify_ssl), adspro_* (url/token/workspace/autopush), public_base_url, sso.
- **Geo / evidence:** geofence_on, geofence_radius_m, checkin_photo_required, checkin_entry_exit_required.
- **One-time seed / repair flags (≈40):** *_seeded / *_seeded_v1 / *_v1 / *_fixed / *_repaired — idempotency guards (e.g. fext_report_seeded, progress_reports_seeded, masters_seeded, demo_seeded, labels_amp_fixed…). Test that they run **once** and are safe to re-run.

---

## 6. Masters / lookup register  (≈74 lists)

Editable dropdown lists (`lookup_types`/`lookup_values`, surfaced at `/lookups`), each
feeding one or more forms; several are **dependent (cascading)**:

`activity_progress, address_type, assessment_type, attendance_self, audit_clause,
audit_type, avail_status, boss_status, call_source, call_status, candidate_source,
candidate_stage, charge_unit, clarification_to, day_code, departure_status,
deputation_approval, deputation_log_kind, deputation_mob_status, deputation_shift,
deputation_status, designation, drop_point, drop_reason, effectiveness_result,
endorse_decision, endorse_doc_type, engagement_type, expense_heading, gst_state,
hr_department, identity_doc, inquiry_status, inspection_disposition, inspection_result,
inspection_type, issue_class, issue_responsibility, issue_type, issue_visibility,
lead_source, leave_type, measurement_units, order_type, payment_term, phrase_category,
po_type, product, projcosting_head, quote_location_type, quote_lost_reason,
quote_origin, quote_status, release_status, report_category, report_status, sbu,
service_criticality, service_priority, site_location_type, source_doc_type,
template_kind, vendor_approval_status, vendor_product_category, vendor_risk_class,
vendor_type` (+ custom fields per entity via `custom_fields`).

Also **custom fields / custom forms** engine (`custom_fields`, `custom_forms`,
`custom_records`, `custom_values`) — user-added boxes on Calls/Jobs/Partners, incl.
cascading lookups. **Terminology engine** renames any business noun (Settings →
Terminology / industry packs) and must flow to every screen and output.

---

## 7. Output register

| Builder | Output | Where |
|---|---|---|
| `report_pdf_build` | System report PDF (letterhead, body, auto-signature block, QR) | `/document-pdf` |
| `report_docx_build` / `_fill` / `_expand_tables` / `_plain` | Company Word (.docx) in the client/company format (token-mapped) | `/document-docx` |
| `endorsement_pdf_build` | Manufacturer endorsement certificate PDF | `/endorsement-cert` |
| `quote_pdf_build` | Quotation PDF (letterhead, T&C, signature) | `/quote-pdf` |
| `portal_report_pdf` / `cvp_vendor_report_pdf` | Client/vendor-portal report PDF | portals |
| Release Note (via `report_pdf_build` + release block) | Release / Discrepancy Note | `/document-release-note` → PDF |
| `mis_export`, `tapi_export_matrix`, `tapi_kpi_export_meta`, `crm_quotes_export`, `org_register_csv`, `tally_csv_rows`/`tally_export`, `person_data_export`, `partner_template_csv` | CSV / matrix exports | dashboards, registers, tally |
| `autoform_analyze_docx` / `report_form_from_template` | Auto-designed form from an uploaded Word template | `/report-autoform` |
| QR verify page | Public verification of an issued report | `/verify` |
| Email templates | Assignment, approval, overdue/SLA escalation, MIS digest, client "report issued", vetting-required, complaint/CAPA notices | `ops_mail`, `email_log` |

Fidelity checks (Prompt 3 §J): figures on PDF/Word/register all match source; photos
(any format) embed or show a labelled placeholder; captions print; QR resolves.

---

## 8. Cross-cutting subsystem register

| Subsystem | What it does | Key files/tables |
|---|---|---|
| SUB-IRN | Configurable IRN numbering (token pattern, per-office override, zero-duplicate serial scope) | `idems_counters`, settings `idems_irn_format/company_code/serial_width` |
| SUB-AUDIT | Tamper-evident **hash-chain audit trail** (every change; verifies; detects tamper) | `idems_audit`, `evidence_chain`, `chain_*` |
| SUB-SIGN | Automatic signatures (set once, stamped on every issued PDF; inspector & approver) | `users.signature`, `inspectors.signature`, `report_files` |
| SUB-TIME | Controlled timestamps (server time is truth; device clock recorded, never trusted) | report finalize path |
| SUB-GEO | Geo-tagged / EXIF evidence integrity (camera location/time from file; browser location kept separate; not spoofable) | `report_files` (exif_lat/lon, up_lat/lon, geo_source), `site_visits`, geofence settings |
| SUB-EVID | Smart photo & evidence (compress, dedupe, captions, PDF embed, supporting gallery) | `report_files`, `idems_compress_image`, `image_to_jpeg` |
| SUB-TERM | Terminology / vocabulary engine (rename any noun; industry packs; neutral defaults; no hardcoded agency name) | `T()/Tl()/TH()`, settings terms/industry |
| SUB-LIC | Seat & module licensing (seat prices/limits; module licence gates everywhere) | `issued_licences`, `agencies`, billing_* settings, `licence_*` |
| SUB-PWA | Offline-first / mobile PWA (service worker, manifest, offline capture + sync) | `/sw.js`, `/manifest.php` |
| SUB-AI | AI-assist (QAP→scope extract, dictation + polish/translate, smart remarks, auto Release-Note draft, deterministic QA auditor) — on/off, never alters facts | `lib/ai.php`, `ai_config` |
| SUB-NOTIFY | Notifications / email (assignment, approval, SLA escalation, MIS digest, client-issued, vetting-required) | `ops_mail`, `email_log`, `portal_notifications` |
| SUB-SVCSCOPE | Service-scope engine & report-formats-by-service | `service_catalog`, `service_dependencies`, `service_report_map`, `service_scope` |
| SUB-HWP | Hold / witness points (derived from dispositions; open points gate release) | `hw_points` |
| SUB-TAPI | KPI / analytics engine (KPI master, formula engine, dashboards, drill-down, targets, scorecard, alerts, data-quality) | `kpi_*`, `scorecards`, `analytics_periods` |
| SUB-WF | Recruitment / workforce command centre (fit/health/readiness, commercials, manpower modes) | `candidates`, `requisitions`, `back_office_staff` |
| SUB-PDSO | Deputation & site operations layer over a job | `dep_*` tables |
| SUB-CONF | Confidentiality spine (undertakings, NDAs, breach register, file authorisation) | `confidentiality_*`, `client_ndas`, `form_tokens` |
| SUB-IMPORT | Excel/CSV import building the org hierarchy; partner import; malformed-row handling | `org_register_csv`, `attendance_parse_csv`, partner import |
| SUB-PREORDER | Pre-order controls (blocked/on-hold client gate; pre-order review checklist) | `business_partners.hold_status`, preorder_* settings |
| SUB-DASHTASK | Pending-tasks panel on every dashboard | `ops_pending_tasks` |

---

## 9. Report-type catalogue (IDEMS)

**Seeded (37, `IDEMS_REPORT_SEED`):** DIR Daily Inspection · DVR Daily Visit · FLR
Flash · IR Inspection Report · SIR Stage Inspection · FIR Final Inspection · RN
Release Note · IRN Inspection Release Note · COC Certificate of Conformity · TCRV Test
Certificate Review · PHOTO Photographic · DIM Dimensional · IC Inspection Certificate ·
WC Witness Certificate · HC Hold Certificate · SUR Surveillance · ER Expediting · MPR
Manufacturing Progress · VAR Vendor Audit · VASR Vendor Assessment · FAR Factory
Assessment · STIR Site Inspection · NCR Non-Conformance · OBR Observation · DEV
Deviation · TCR Technical Clarification · CAVR Corrective-Action Verification · PPR
Punch Point · RIR Re-Inspection · TS Time Sheet · ATR Attendance · TVR Travel · EXP
Expense · WS Weekly Summary · FNR Fortnightly Progress · MPGR Monthly Progress · CSR
Client Summary · PCR Project Closure.

**Built-with-forms (ready to fill):** IR, RN, VAR, VASR, ER, **FEXT** (Fire
Extinguisher), the project-site progress family **DPR/WPR/FNR/MPGR**, and the
universal families **UIR_*/URD_*/UVA_*/UAUD_*/PDSO_***. The Report-types screen shows
"ready vs no form yet" and offers a reversible "hide empty types". Many seeded codes
are form-less until designed (via the no-code builder or auto-design from template).

---

## 10. Data model — 210 tables (grouped)

- **CRM / Sales:** crm_inquiries, leads, lead_files, lead_stage_history, opportunities,
  opportunity_quotes, opportunity_stage_history, pipelines, pipeline_stages,
  stage_gates, stage_gate_requests, quotations, quote_lines, quote_locations,
  quote_approvals, quote_approval_rules, quote_revisions, quote_files, quote_followups,
  quote_edit_requests, project_costings, project_costing_lines, ads_leads, ads_spend,
  ads_outbox, ads_sync_log, billing_orders.
- **Directory / partners:** business_partners, partner_contacts, partner_addresses,
  partner_registrations, partner_notes, partner_relationships, partner_contracts,
  partner_purchase_orders, po_line_items, contract_overrides, vendor_profiles,
  vendor_qualifications, vendor_status_events, vendor_audit, vendor_km_memory, agencies.
- **Operations:** calls, call_clarifications, call_nudges, call_status_events,
  recurring_services, jobs, job_visits, job_qaps, job_bills, job_delays, job_readiness,
  site_visits, assignment_events, dep_checklist, dep_manpower, dep_site_log,
  dep_site_history, dep_timesheet, dep_att_approval, dep_status_events, hw_points,
  install_beats, ops-desk (TOSRM) tables.
- **People / workforce:** inspectors, inspector_certs, inspector_allowances,
  inspector_day_status, authorisations, witness_assessments, qualifications, subcons,
  subcon_rates, back_office_staff, candidates, candidate_events, requisitions,
  person_documents, person_document_access, person_sbu_split, users, user_prefs,
  login_attempts, sso_attempts, work_norms, holidays, offices, boss_numbers, travel_modes,
  attendance, voucher_entries, vouchers, expenses, expense_heads.
- **IDEMS reporting:** report_types, report_sections, report_fields, report_docs,
  report_files, report_approvals, report_vetting, report_statuses, report_templates,
  report_client_reviews, report_doc_review, report_equipment, report_lib_fields,
  report_lib_sections, report_lib_section_fields, idems_approval_rules,
  idems_approver_map, idems_counters, idems_audit, endorsements, endorsement_files,
  tech_phrases, learned_suggestions, evidence_chain, release_inspections, release_rules,
  qap_scope_cache.
- **Quality / compliance:** equipment, equipment_calibrations, methods, decision_rules,
  sample_items, sample_custody, nonconformities, ncr_events, issue_departures,
  issue_disputes, issue_extensions, capa, capa_actions, capa_events, complaints,
  complaint_events, satisfaction_surveys, internal_audits, audit_findings,
  audit_criteria, audit_criteria_packs, audit_conclusion_rules, mgmt_reviews, mr_inputs,
  mr_actions, impartiality_declarations, impartiality_threats, confidentiality_undertakings,
  confidentiality_breaches, client_ndas, disclosure_consents, data_consents,
  data_requests, controlled_docs, retention_rules, data_check_runs, system_failures,
  sw_validations, security_incidents, risk_items, site_doc_requirements, person documents.
- **Money:** invoices, invoice_lines, receipts, receipt_allocations, credit_notes,
  tally_exports, books_outbox, cost_allocations, cost_runs, credit_recon,
  office_expenses, office_expense_heads.
- **Portals:** client_users, portal_requests, portal_notifications, portal_audit,
  vendor_users, form_tokens, email_log.
- **Masters / config / analytics:** lookup_types, lookup_values, custom_fields,
  custom_forms, custom_records, custom_values, settings, service_catalog,
  service_dependencies, service_report_map, service_scope, sla_targets, issued_licences,
  kpi_defs, kpi_versions, kpi_targets, kpi_snapshots, kpi_alerts, analytics_periods,
  scorecards, scorecard_items, assessment_criteria(_packs), assessment_disqual_rules,
  audit_conclusion_rules, inspection_criteria(_packs).

*(Full column-level detail per table is enumerated in the relevant module's Prompt 3
§B/§I. The 210-table list above is the complete set of `CREATE TABLE` statements.)*

---

## 11. Discovery observations

1. **Sub-actions vs menu screens.** 83 menu tiles but ~325 route cases: most modules
   expose many `-new / -edit / -status / -delete / -approve / -*` endpoints reached from
   a parent screen. All are captured by **route family** in §2 and enumerated
   field-by-field in Prompt 3. None is dropped.
2. **Grep false-positives filtered.** The raw route→module map contained non-module
   tokens from unrelated `'x'=>'y'` literals (tones `ok/warn/bad/info`, labels
   `photos/invoices/expenses`, ref/opt configs `clients_list/inspectors_list/
   offices_list/subcons_list/agency_inspector/partner/name`). These are **not modules**
   and are excluded from §2.
3. **Terminology-keyed labels.** Several tile labels resolve through the terminology
   engine (`inquiry, quote, report, invoice, client, vendor, boss, office, user,
   endorsement`) — the on-screen word depends on the tenant's vocabulary. Test that a
   renamed term flows to every screen **and** every output (SUB-TERM).
4. **Two report registers.** `/documents` (all reports) and `/release-notes` (RN only)
   both read `report_docs`; confirm no divergence. `/vendors` appears under both
   Reporting and Directory — same register, two entry points.
5. **Deactivated / form-less types.** Many seeded report codes have no designed form yet
   (form-less) and some (e.g. legacy MGHIR) may be deactivated; the Report-types screen
   surfaces "no form yet" and offers reversible hiding. Confirm the active set per tenant.
6. **Multi-tenant generic.** No agency name is hardcoded (verified during recent work);
   all agency identity is in Settings/terminology. This is a **standing test** (SUB-TERM,
   SUB-LIC) — a hardcoded name anywhere is a defect.
7. **Known automated-suite state.** `php tests/run.php` = **1532 passing, 3 failing**
   (the 3 are pre-existing NCDCA release-dependency tests, accepted). Treat the pass
   count as a regression gate in Prompt 2.
8. **Areas to confirm-read in Prompt 3:** the TOSRM ops-desk internals, the NCDCA
   issue/departure/dispute engine, the universal audit/assessment criteria-pack engine,
   and the TAPI formula engine are large and warrant a dedicated per-module pass.

---

*End of Master Inventory v1.0. Lock this (stamp the date in the revision history),
then run Prompt 2 (Test Governance).*
