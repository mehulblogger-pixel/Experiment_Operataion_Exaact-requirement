# Exaact — Exhaustive UAT Matrix

Every navigable screen, module by module in data-flow order, tested with normal
**and edge-case** input. A screen is only ticked when it has been driven in a
real browser, its forms submitted with good *and* bad values, and the result
recorded here. Nothing is skipped.

**Edge cases checked on every form:** empty required fields · invalid formats
(GSTIN, email, dates, numbers) · boundary values · duplicate prevention ·
permission gates · very long / special-character text · cascading dependents.

Legend: ✅ pass · 🐞 bug (fixed) · ⚠ finding (open) · ⟢ owner decision · ⏳ queued

Progress: **Module 1 of 12 done** (1 bug found & fixed: duplicate master values).

---

## M1 · Setup & Masters   ✅ DONE
`/setup` · `/masters` · `/lookups` · `/lookup` (values) · `/custom-fields` ·
`/terminology` · `/industry` · `/settings` · `/company-profile`

- **All 8 screens load clean** (no PHP errors).
- Edge cases on the lookup engine: empty value label → **rejected** ✅;
  dependent list with no parent chosen → **rejected** ✅; HTML/`<script>` in a
  label → stored raw but **escaped on output** (`&lt;script&gt;`), no XSS ✅.
- 🐞→✅ **Duplicate lookup values were allowed** — adding the same option twice
  created two identical dropdown entries. **Fixed:** the value-add now rejects a
  duplicate label within a list (case-insensitive), scoped to the parent so the
  same label under a *different* parent is still allowed. Verified: 3 add
  attempts (incl. a case variant) → 1 row. `lib/lookups.php`.

## M2 · Directory (Clients & Vendors)   ⏳
`/clients` · `/vendors` · `/partner-new` · `/partner-edit` · `/partner` (detail:
contacts, addresses, contracts, POs) · `/partner-import` · `/activities`

## M3 · CRM / Sales   ⏳
`/leads` · `/lead-new` · `/lead` · `/lead-convert` · `/opportunities` ·
`/opportunity` · `/inquiries` · `/quotes` · `/quote-new` · `/quote` (revise,
approve, send, accept, reject, lock) · `/pipelines` · `/crm-dashboard` ·
`/crm-reports` · `/approvals` · `/approval-rules` · `/crm-templates`

## M4 · Operations (Calls & Jobs)   ⏳
`/calls` · `/call-new` · `/call` · `/jobs` · `/job-new` (allocate) · `/job` ·
`/job-close` · `/my-jobs` · `/availability` · `/timesheet` · `/vouchers` ·
`/voucher` · `/ratings` · `/candidates` (+detail, stages) · `/requisitions` ·
`/attendance-recon` · `/contract-overrides`

## M5 · People & Organisation   ⏳
`/users` · `/user-new` · `/user-edit` · `/hierarchy` · `/access` · `/work-norms`
· `/inspectors` (register + profile) · `/change-password` · `/two-factor`

## M6 · Reporting (IDEMS)   ⏳
`/documents` · `/document-new` · `/document` (fill, submit, review, finalize,
reject) · `/report-builder` (+scope, +fields, conditions) · `/report-type-new` ·
`/endorsements` · `/writing-assistant` · `/learning` · `/approver-map` ·
`/approval-rules` · `/templates` · `/report-reviews` · `/audit-log`

## M7 · Quality & Accreditation   ⏳
`/equipment` · `/competence` · `/impartiality` · `/complaints` · `/satisfaction`
· `/confidentiality` · `/site-docs` · `/ncr` · `/capa` · `/internal-audits` ·
`/management-reviews` · `/evidence-review` · `/data-control` · `/identity` ·
`/risks` · `/retention` · `/disclosure` · `/methods` · `/drules` · `/cdocs`
(each: list + new-form + detail + close/disposition edge cases)

## M8 · Money / Finance   ⏳
`/invoicing` · `/to-bill` · `/invoices` · `/invoice` (issue, cancel, credit note)
· `/receipts` · `/receipt` (allocate cash/TDS) · `/receivables` · `/tally` ·
`/profitability` · `/office-finance` · `/cost-run` · `/sbu-pl` · `/call-profit` ·
`/books-bridge`

## M9 · Insights & Dashboards   ⏳
`/` (dashboard) · `/reports` · `/mis` · `/flow-gaps` · `/advisor` · `/search`
(empty query, no-match, special chars)

## M10 · Licensing & SaaS   ⏳
`/licence` · `/licence-issue` · `/tenants` · `/billing` · `/sso` · `/adspro`

## M11 · Client Portal   ⏳
`/portal-users` · `/portal-user-perms` · client-facing sign-in + report view/accept

## M12 · Admin & Security   ⏳
`/settings` (all tabs) · `/compliance` · `/data-requests` · `/consents` ·
`/data-control` · `/reset-data` (careful) · `/audit-log` · `/sso` · 2FA enforce

---

## Findings log
_(newest first — filled as modules are worked)_
