# Sales fix batch B (V51.1) — the 22 fixes you pre-approved

15 Nov 2026 · Built on V51.1 (plus fix batch 1). Nothing from the held v52/v53 was copied: every fix was re-made and re-tested here.

**What this batch does NOT touch:** no money calculation, billing or contract rule, approval workflow or permission was changed. Each fix is a display change, a clearer message, or a missing check. The A-list items (A1–A18) and your questions (C1–C18, D) are still waiting for your answers and are **not** in this batch.

## The 22 fixes

| # | Defect | What you will see now | Retest |
| --- | --- | --- | --- |
| B1 | DEF-SAL-001 | Money in lakh/crore: ₹5,00,000 · ₹25,00,000, everywhere in the app | Passed |
| B2 | DEF-SAL-002 | A refused form names the box as you see it, with the real reason: "Received date cannot be after 15 Nov 2026", "Qty on line 1 cannot be below 0" | Passed (browser) |
| B3 | DEF-SAL-003 | A file that is too big: "That file is too large — the limit is 8 MB", said at once in the browser; never "session timed out" or a blank page | Passed (browser) |
| B4 | DEF-SAL-010 | Contract rule "None": "Contract … registered and open — your company asks for no approval to open a contract." | Passed (robot) |
| B5 | DEF-SAL-013 | Set up Sales history: one line per answer, nothing cut off | Passed (robot) |
| B6 | DEF-SAL-015 | "✎ Edit" on a costing role stays inside the quotation's pop-up | Passed (robot) |
| B7 | DEF-SAL-016 | Sales funnel never shows more than 100% | Passed |
| B8 | DEF-SAL-017 | A man-month lead needs at least 1 person | Passed |
| B9 | DEF-SAL-018 | Paise shown. On a quotation (page, form, PDF and e-mails) every amount has two decimals: ₹7,999.99 · ₹4,000.00 | Passed (Word not checkable: no Word template on the test copy) |
| B10 | DEF-SAL-021 | "Your next actions" now lists a salesperson's own follow-ups: an overdue lead follow-up, an enquiry assigned to them that has no quotation yet, a chase due on their quotation | Passed |
| B11 | DEF-SAL-023 | Pipeline stages have an Order box; typing 2 moves a stage to second place | Passed |
| B12 | DEF-SAL-024 | Enquiry cards open lists that match their counts ("Quoted" = quoted, won or lost) | Passed |
| B13 | DEF-SAL-025 | "Why we lose" also lists the reasons leads were not pursued, in their own small table | Passed |
| B14 | DEF-SAL-026 | The Year box offers years that have only leads or deals | Passed (also on MariaDB) |
| B15 | DEF-SAL-027 | On a phone, jump links land below the top bar (checked on an iPhone 13 screen) | Passed (phone emulation) |
| B16 | DEF-SAL-030 | A new deal has its History tab ("no moves yet") | Passed |
| B17 | DEF-SAL-031 | An open lead past its "Expected to close" date shows "⏰ close date passed" and is on the needs-attention list | Passed |
| B18 | DEF-SAL-032 | Unticking a pipeline's "In use" is saved | Passed |
| B19 | DEF-SAL-033 | An empty quotation follow-up is refused | Passed |
| B20 | DEF-SAL-034 | A report format needs a name | Passed |
| B21 | DEF-SAL-036 | After a time-out the sign-in page says why. After signing in you return to your form with what you typed | Passed (browser) |
| B22 | DEF-SAL-037 | Back + "Create quote" does not make a second quotation; the form links to the one already saved. A real second quotation is one tick away, and "Quote again" with a new scope is never held | Passed |

## How it was tested

| Check | Result |
| --- | --- |
| New automated tests: `test_sales_bbatch_v511.php` and `test_def_sal037_quote_back_create.php` | 38/38 and 13/13 pass. On the untouched V51.1, 8 of the quotation checks fail, which proves the test catches the problem |
| Browser checks on a private copy with made-up data | 7/7 pass (screenshots in `evidence/fix-batch-B/`) |
| Robot: every Sales test-book case linked to these defects, plus every case that creates a quotation (50 runs) | Every step that failed because of these 22 defects now passes. No case got worse than on V51.1. What still fails there is the A-list (waiting for your yes), screens you changed on purpose, or "needs a person" steps |
| Full automated suite, SQLite | See "Final suite runs" below |
| Full automated suite, MariaDB (the database family your host uses) | See "Final suite runs" below |

The fixes changed six older tests: five now expect "1,00,000" instead of "100,000", and one gives a deliberate second quotation its own subject. Two robot scripts were corrected; neither was an app problem:
- SAL-011 now opens Leads directly, because the Leads tile can carry a count badge.
- My first edit of that script had a typing error; I fixed it before the rerun that counts.

## Things I found and corrected along the way

1. **The duplicate guard first held a deliberate second quotation.** In SAL-185, "Quote again" with a revised scope was held. A repeat now has to be the same quotation: same lines, and the earlier one still a draft.
2. **The "Leads" count in "Why we lose" first made that panel differ from Opportunities.** Lead reasons now sit in their own table, and the deals table reads exactly as before.
3. **An over-size file was first silently dropped.** It is now kept, and Save is refused with the reason.
4. **The amount on a quotation showed "4,000"** where the PDF showed "4,000.00". Quotations now always show two decimals.

## Known limits (honest list)

- **Lakh grouping applies only to rupee companies.** A company whose currency symbol in Settings is not ₹ / Rs / INR keeps the 500,000 style. A few Marketplace screens show plan prices with their own symbol and follow the same rule.
- **Restoring typing after a time-out** fills the boxes that are on the screen. Extra quotation lines that were added with "+ Add line" are not re-created.
- **Word (.docx) quotations** could not be compared: the test copy has no quotation Word template (SAL-060).

## Rollback

If anything looks wrong on the test copy:

1. Restore the backup you took before uploading. That is always the safest way back.
2. Or upload the previous update zip (V51.1 + fix batch 1) over it in the same way.

No database change is made by this batch: no new table and no new column. Going back is therefore only a matter of files.

## Final suite runs (on the final code, commit 573fc43 + stamp)

| Run | Result |
| --- | --- |
| Full suite, SQLite | **20,246 passed, 0 failed** |
| Full suite, MariaDB | **No new failures compared with V51.1**. It stops at the same known test-harness crash as the V51.1 run (test_workspace_requests_visible.php, already recorded; a test-order problem in the tests, not the app). One old test-order failure (X14) has disappeared. 20,236 checks passed before the stop (V51.1: 20,171) |

## The update zip — for the TEST COPY only

`EXAACT-V51.1-SalesB-TEST-COPY-update.zip`
- **Contents:** 814 files, placed at the top of the zip, so extracting overwrites them in place.
- **Left out:** no `tests/` folder, `config.local.php`, `tenants.php`, database file, or `exaact_data` / `exaact_backups` folder.
- **Contains:** fix batch 1 (DEF-0001 and the packaging fix) and this batch B. No v52/v53 changes and no A-list item.
- **SHA-256:** `c1a08d35512a7a6a1f6b1211592cf1d6f39039dd1d83d2a5332ab472ca7727ab`

**To try it on the test copy (opstest):**
1. Back up the opstest app folder and its data first.
2. Upload the zip into the opstest **app** folder.
3. Extract it, overwriting the existing files.
4. Open `/deploy-check.php`. It should show code 573fc43 and the red TEST COPY bar.

**Not for the live site yet.** When you are happy with it on the test copy, tell me and I will ask "Shall I prepare the live update?"

## Appendix — every app file changed (158, tests not included)

Most of these files changed only one thing: `number_format(…)` became `nf(…)`, which writes lakh/crore grouping. The files with logic changes are listed in "What changed where" above.

```
assets/js/app.js
deploy-check.php
index.php
lib/access.php
lib/activity.php
lib/billable.php
lib/billunits.php
lib/comp_config.php
lib/connect_bridge.php
lib/connect_engage.php
lib/connect_engvoucher.php
lib/connect_kpi.php
lib/connect_market.php
lib/contracts.php
lib/costing.php
lib/costing_heads.php
lib/crm.php
lib/crmdash.php
lib/doc_templates.php
lib/engagement.php
lib/finevent.php
lib/helpers.php
lib/hiringreq.php
lib/idems.php
lib/leads.php
lib/licenceissue.php
lib/milestones.php
lib/mkt_billing.php
lib/mkt_pay.php
lib/ops.php
lib/pdf.php
lib/pipelines.php
lib/projcosting.php
lib/recruit.php
lib/recruit_export.php
lib/recruit_jd.php
lib/recruit_offer.php
lib/reset.php
lib/saas_payments.php
lib/saas_tenants.php
lib/schedule.php
lib/security.php
lib/selfserve.php
lib/settlement.php
lib/setupqa.php
lib/tapi.php
lib/timesheet.php
lib/trace_seed.php
lib/trust.php
lib/uire.php
lib/uvae.php
lib/workforce.php
views/detail.php
views/layout_bottom.php
views/layout_embed_top.php
views/layout_top.php
views/login_page.php
views/ops/_company_payments.php
views/ops/_deputation_panel.php
views/ops/_glance_strip.php
views/ops/_job_billing.php
views/ops/_milestones.php
views/ops/_subscription_builder.php
views/ops/_subscription_paid.php
views/ops/_subscription_plan.php
views/ops/adsroi.php
views/ops/ai_forms.php
views/ops/ai_topup_pay.php
views/ops/approval_rules.php
views/ops/backup.php
views/ops/billable_events.php
views/ops/billing.php
views/ops/billing_pay.php
views/ops/call_detail.php
views/ops/call_form.php
views/ops/call_profit.php
views/ops/candidate_detail.php
views/ops/command_centre.php
views/ops/connect_analytics.php
views/ops/connect_front.php
views/ops/connect_requirement.php
views/ops/connect_requirements.php
views/ops/contract_detail.php
views/ops/cost_reconciliation.php
views/ops/cost_run.php
views/ops/credit_note.php
views/ops/crm/_quote_costing_tab.php
views/ops/crm/approval_rule_list.php
views/ops/crm/inquiry_list.php
views/ops/crm/quote_detail.php
views/ops/crm/quote_form.php
views/ops/crm/quote_list.php
views/ops/crm/reports.php
views/ops/crm_dashboard.php
views/ops/gst_invoices.php
views/ops/hiring_request.php
views/ops/hiring_request_list.php
views/ops/idems/audit.php
views/ops/idems/doc_detail.php
views/ops/idems/expediting_projects.php
views/ops/idems/expediting_register.php
views/ops/idems/learning.php
views/ops/idems/templates.php
views/ops/idems/vendor_detail.php
views/ops/idems/vendor_register.php
views/ops/industry.php
views/ops/inspector_profile.php
views/ops/invoice_detail.php
views/ops/invoice_print.php
views/ops/job_detail.php
views/ops/job_form.php
views/ops/leads.php
views/ops/licence_issue.php
views/ops/licence_issued.php
views/ops/mis.php
views/ops/mkt_escrow.php
views/ops/mkt_ledger.php
views/ops/mkt_plans.php
views/ops/mkt_rules.php
views/ops/my_approvals.php
views/ops/notifications.php
views/ops/office_finance.php
views/ops/opportunity_detail.php
views/ops/pipeline_edit.php
views/ops/pricing_usage.php
views/ops/project_costing.php
views/ops/project_costing_print.php
views/ops/project_costings.php
views/ops/recruitment_cc.php
views/ops/reimbursable_dedup.php
views/ops/requisition_detail.php
views/ops/reset_data.php
views/ops/revenue_reconciliation.php
views/ops/saas_companies.php
views/ops/sbu_pl.php
views/ops/subscription.php
views/ops/subscription_invoice.php
views/ops/super_admin.php
views/ops/voucher_detail.php
views/ops/voucher_list.php
views/ops/voucher_print.php
views/ops/work_norms.php
views/partner_contract_new.php
views/po_detail.php
views/portal/bench.php
views/portal/deputations.php
views/portal/hire_req.php
views/portal/hiring.php
views/portal/plans.php
views/portal/reputation.php
views/portal/roster.php
views/portal/voucher.php
views/pro/bookings.php
views/pro/jobs.php
views/pro/plans.php
views/pro/reputation.php
views/pro/voucher.php
views/pro/vouchers.php
```
