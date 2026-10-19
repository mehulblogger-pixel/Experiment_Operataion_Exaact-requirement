# Sales-C — handoffs & customer data (subagent report, saved by the lead)

Probes in this folder; run_all.sh re-runs on fresh db (P11 separately). Real ops_dispatch per process, shipped role presets, mail off (sendmail=/bin/true), Books dry-run, Ads Pro stub 127.0.0.1:8765. 31 findings: 28 EXEC, 3 READ.

## Field mapping quote→contract→WO→job→billable→invoice
- Client: linked through to WO, job, billable. Re-chosen on invoice; invoice does not check the job belongs to that client (F26).
- Contract no: generated at registration, inherited read-only on WO. Hand-typed number on a direct WO accepted; typo becomes a boss_numbers contract at allocation (ops.php:1891).
- Lines/qty: NOT copied to contract (contract_lines_from_quote() contracts.php:205 never called). Contract qty sums all units (F7). Invoice qty = WO qty, not job's (F14).
- Rate: contract has no rate column. WO rate filled by browser JS from quote line, or by server from a PO with exactly one open line (contracts.php:1500); editable; never compared to quote (F13). Rate flows WO→job→billable→invoice line (books.php:657).
- Value: contract value = PO value else quote total INCL GST (crm.php:2241), freely editable; WO value = rate×qty (ops.php:5586); no ceiling anywhere (F10).
- Lost at contract: GST% (invoice uses books_default_gst() books.php:670); validity (end date blank unless PO); sites (quote vendor_id copied to WO, address re-picked; invoice one GSTIN); payment terms (from partner master).
- Advance + report-hold flags: quote→job (crm.php:2641).
- Reimbursables: engineer uploads job_bills; no invoice code reads them → travel retyped as free line (F17).

## Findings
F1 P1 DEFECT EXEC — any quotes viewer accepts an unapproved quote: quote-status checks perms only for APPROVED/SENT (crm.php:1815-1846). FINANCE, BUSINESS_DIRECTOR, MARKETING_EXECUTIVE moved DRAFT→ACCEPTED (0 approvals, never sent), also with a rule active (p8). Acceptance creates client, wins deal, converts lead, queues Books. UI hides button on drafts → crafted POST.
F2 P1 DEFECT EXEC — DRAFT→SENT skips approval even when rule requires (crm.php:1846); price reaches customer then locks; lifecycle doc allows only APPROVED→SENT.
F3 P2 DECISION+DOC EXEC — nobody owns "record acceptance": "Mark accepted" drawn for every viewer of a SENT quote (quote_detail.php:204); no matrix row.
F4 P1 DEFECT EXEC — contract registered on DRAFT (/00002) and LOST (/00003) quotes; crm.php:2211 no status check.
F5 P3 DEFECT EXEC (injected fault) — registration not atomic: failure after insert leaves orphan PENDING contract w/o quote link; same-number retry heals, auto-number retry leaves orphan.
F6 P2 GAP EXEC — contract holds no lines, rates, GST, PO no., sites; end date blank; value 118,000 incl GST (subtotal 100,000).
F7 P2 DEFECT EXEC — contract qty mixes units: 10 man-days + 4 visits = 14; consumption counts man-days only (contracts.php:143, :222).
F8 P1 GAP+DEFECT EXEC — OPEN contract amended w/o approval or old/new values: 118,000→500,000 + end date, trail "Contract … edited"; sidesteps two-signature opening and Super Admin expiry override; no amendment/cancellation status.
F9 P3 DEFECT EXEC — Reopen has no status guard: on OPEN contract resets to PENDING, wipes both signatures (contracts.php:868).
F10 P2 GAP EXEC — no invoice ceiling: 1,100,586 invoiced under 500,000 contract from 118,000 quote; PO lines track man-days only.
F11 P1 DEFECT EXEC — OPERATION_MANAGER cannot endorse contract opening by default: /contract-open mapped to quotes module (ops.php:3313), OM lacks mod.quotes.view → "module locked". Branch with only OM + BM cannot open a contract without master.
F12 P2 DEFECT+DECISION EXEC — WO raised against DRAFT quote accepted; non-existent contract number accepted → boss_numbers record at allocation; expiry/qty gate at allocation runs only when a quote is linked.
F13 P2 GAP EXEC — WO rate retyped, never reconciled: 9,500 vs quote line 8,000 accepted, flowed to invoice. Resolves 01-SALES §5 SUSPECTED row: quote rates are NOT the enforced basis of the invoice.
F14 P1 DEFECT EXEC — every job on a WO bills the whole order: books.php:657-662 and billable.php:253 use WO qty & value per job; 2-man-day job invoiced 3 man-days × 9,500 = 28,500.
F15 P1 DEFECT EXEC — billable events overwritten with entire invoice total: billable.php:372 sets each event to invoice total incl GST + other lines; two events on one 47,790 invoice recorded 95,580; every GST invoice shows a mismatch.
F16 P3 GAP — invoice GST & payment terms not from quote (EXEC/READ).
F17 P2 GAP READ+EXEC — reimbursable job_bills never reach invoice.
F18 P2 DEFECT+USABILITY EXEC — job allocated after contract CLOSED; new WO refused with misleading "not open yet — needs endorsement".
F19 P2 DEFECT EXEC — repeat-order quote re-points contract: reusing number overwrites partner_contracts.quotation_id (contracts.php:351); contract reads only new quote; value not updated.
F20 P2 DECISION+DEFECT — Books bridge outbox works (dedupe identical, update pending in place, changed payload → new import; EXEC dry-run). Every revision pushed as separate ERP-QT-id, no retraction; quote pushed "so Books can convert it" while ERP also pushes issued invoices → double invoicing in Books possible (READ).
F21 P2 DEFECT EXEC — superseded revision accepted: R00 and R01 both ACCEPTED, both pushed to Books (118,000 and 106,200).
F22 P3 DEFECT EXEC — acceptance not idempotent: re-accept re-runs side effects, restamps accepted date.
F23 P1 DEFECT EXEC — free-text client duplicated at acceptance: "ACME VALVES PRIVATE LIMITED" created new client though "Acme Valves Pvt Ltd" existed; only an activity note; quote has no GSTIN field; partner_carry_from_crm() linked a loose "Acme Valves Ltd." inquiry to the duplicate (crm.php:2690-2703). Exact repeated name reused correctly.
F24 P2 DEFECT EXEC (stub) — manual inquiry with typed client gets no duplicate warning; Ads Pro dedupes by its own contact id only: same person, email differing in case/spacing → 2 leads; lead typed by hand before import duplicated; contacts for existing clients arrive unlinked (partner_id/client_id NULL); lead_possible_duplicate() exists but import never calls it.
F25 P1 DEFECT+DECISION EXEC — BLACKLISTED and INACTIVE clients never stopped: inquiry, quote, approval, acceptance, contract, WO, draft invoice all succeed. BLOCKED (hold): only a non-manager's quote refused; coordinators raising WOs pass with a warning (ops.php:761). Two unrelated status systems (status, hold_status).
F26 P2 DEFECT — invoice to client B can carry client A's job: books_line_add() no client check (EXEC function level); invoice-line-add route passes posted job (booksui.php:285) READ.
F27 P2 PARTIAL READ — many addresses/contacts per client; invoice one GSTIN, no bill-to choice → separate clients → feeds F23.
F28 P3 DECISION+DEFECT EXEC — external quote registered straight ACCEPTED (2.5M) with no approval and none of the acceptance side effects (crm.php:1520).
F29 P2 PARTIAL EXEC — many WOs under one OPEN contract without new quote; PO lines pulled from quote carry rate (7,500) to WO; no rate card on contract.
F30 P2 GAP+DECISION READ — recruitment requisitions have own billing_rate, rate_basis, free-text contract/PO/quote refs, not linked to quotations/partner_contracts; placement fee one typed amount, guarantee date from agency; no %/fixed choice, no milestones, no client replacement terms; ambiguous fee-we-charge vs cost-we-pay (beside agency_cost, but billable.php bills it to client). Quote form has no business-model switch; industry.php only seeds pipelines & lists.
F31 P3 latent DEFECT EXEC — POST /call-new?contract_id= says "Call updated." and saves nothing; shipped form posts without the query string.

## Retain
Contract registration right enforced (BDM refused); one quote → one contract, number duplicate checks; two-signature opening with approver≠endorser; WO inherits client, contract no (locked), quote, SBU, office, coordinator; PO line carries rate with "Read off PO" note; schedule limits (visits ≤ quote line man-days, ≤ contract end); a job cannot be invoiced twice; billable reconciles only against issued invoices; Books outbox loop-breaker + dry-run; Ads Pro dedupe by external id across both doors; exact client name reused at acceptance; revision carries all.

## Caveats
Job close simulated (closed_flag + billable_on_job_closed()); views not rendered; shipped role presets only.
