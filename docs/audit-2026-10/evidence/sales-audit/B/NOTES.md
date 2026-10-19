# Sales-B — pricing, costing, approvals (subagent report, saved verbatim-in-substance by the lead)

Grades: EXEC = executed on throwaway SQLite via real route handlers (harness.php, ops_dispatch in forked child); READ = traced; UNTESTED.
Probes: p1..p7 *.php / p*_output.txt in this folder.

## Data model (EXEC, t_columns)
quotations (crm.php:178-190 + 278-319): id, quote_no, rev, parent_id, is_current, inquiry_id, client_id, client_name, contact_*, sbu, office_id, subject, site_location, location_type, currency VARCHAR(8) default INR, validity_days default 30, payment_terms, advance_pct, advance_required, report_vs_payment, subtotal, gst_pct default 18, gst_amount, total_amount, status, lost_reason/_other, template_id, sent_at, accepted_date, contract_id, contract_number, owner_id, created_by/at, updated_at, origin, origin_portal, origin_ref, inspection_types, product_category, terms_conditions, exec_office_ids, vendor_id, rejected_by/at, reject_remarks, locked, unlocked_until, signatory_user_id, po_number, po_date, submitted_at, lead_id, preorder_checklist (JSON), engagement_id, books_ref.
quote_lines: id, quote_id, line_no, sbu, service_type, subtypes, description, location, location_type, order_type, qty DECIMAL(12,2), unit, rate, amount, deliverables, notes, office_id, location_id, activity_id. NO per-line tax, discount or currency.
quote_locations: id, quote_id, label, location_type, line1, line2, city, state, pincode, country, contact_name, contact_mobile, office_id, sort_order, vendor_id.
Units CHARGE_UNITS crm.php:59-68: MANDAY, MANMONTH, VISIT, AUDIT_DAY, LOT, DOC, PER_KM, OTHER. No HOUR. Server does not validate unit code.
Inclusions/exclusions only on project_costings. Reimbursables only default T&C clause 4 "at actuals" (crm.php:753). Currency free text one per quote; PDF "Total (USD)" but amount in words always Rupees (crm.php:3376).

Quote formulas (EXEC, hand-checked): line = round(qty*rate,2) crm.php:675; subtotal = Σ amount incl. discount lines :622; GST = round(sub*gst%/100,2) one rate per quote :623; total = sub+GST :624; validation qty>=0, GST 0-100, advance 0-100, Σ>=0, negative rate allowed :632-647. Example 3×3333.33 − 999.99 @18% = 9000 / 1620 / 10620. No IGST/CGST split at quote (READ).

Costing formula projcosting.php:234-273 (EXEC vs hand calc):
  direct = Σ cost heads; load% = overhead+insurance+bad-debt+contingency
  COST mode: loaded = direct×(1+load%); rate = manual>0 ? manual : loaded/(1−t) if 0<t<100 else loaded
  RATE mode: rate = direct/(1−t−load%) if denom>0 else direct; loaded = direct + rate×load%
  proposed = rate×(1+negotiation%)
  LONG/DAILY: revenue = proposed×BOQ; cost = loaded×BOQ + one-off.  FIXED: revenue = proposed; cost = loaded + one-off
  margin = profit/revenue  → it is MARGIN on sell price, then a negotiation MARKUP on top: 20% target shows 23.81%.
18 cost heads (salary, statutory, travel, accommodation, food, PPE, capex, tools, calibration, misc…); NO vendor/subcontract head. Defaults (8 / 0.5 / 0 / 0.5, negotiation 5) from setting_get projcosting.php:66-75; NO screen writes them. LONG→MANMONTH, DAILY→MANDAY, FIXED→LOT (projcosting.php:300). No minimum-margin setting anywhere. lib/costing.php is unrelated (actual salary/overhead allocation to BUs).

## Approval bands (EXEC; matching crm.php:2971-3002, 1129-1138)
| Case | Result |
| value == min or == max | matched (both inclusive) |
| value on shared boundary of two bands | both levels required |
| overlapping bands | every matching rule becomes a step; not cumulative (a 3L quote skipped L1) |
| two rules same level | both must approve |
| gap / above top max / SBU unmatched or blank / no rules | one generic "any approver" step + direct approve allowed; ₹9L approved directly by MARKETING_MANAGER although top band named BUSINESS_DIRECTOR |
| value compared | total_amount: after discount lines, INCLUDING GST |
| rule form | accepts min>max (never matches), blank name, negative level, no approver; approvers manage the rules that bind them (crm.php:3250); no audit of rule changes (READ) |

## Findings
F1 SENT without approval — BD Manager sent DRAFT, PENDING_APPROVAL and REJECTED quotes; only send permission checked (crm.php:1846, 1883). EXEC p5 A. CONFIRMED DEFECT P1.
F2 Edits keep approval — pending ₹50k edited to ₹50 lakh then approved by ≤₹1L approver; APPROVED ₹50k edited to ₹1 stayed APPROVED and was sent (crm.php:1660-1667; edit allowed crm.php:1764). Log says only "Edited". EXEC p5 F. CONFIRMED DEFECT P1.
F3 Any quotes viewer sets ACCEPTED/LOST/DRAFT/REJECTED/EXPIRED — FINANCE marked never-approved never-sent DRAFT ACCEPTED → client landed, deal won, lead converted, Books push. BRANCH_MANAGER moved pending to DRAFT/REJECTED/EXPIRED/LOST. Generic status branch crm.php:2004; ungated buttons quote_detail.php:197/204/215. Transitions absent from 03-object-lifecycles. EXEC p5 A. CONFIRMED DEFECT + DOC GAP P1.
F4 Levels not sequential — L2 approved before L1 (crm.php:3096) although inbox shows lowest level (1440, 3075). EXEC p5 D. DEFECT or DECISION P2.
F5 Self-approval — MARKETING_MANAGER (create+approve+send by default) raised, approved and sent own quote, chain and direct. Org self-approval switches (recruit_approval.php:2836+) not applied to quotes. Contradicts matrix claim of ISO 17020 preparer/approver separation. EXEC p5 C. BUSINESS GAP P1.
F6 Unmatched value gets weakest approval — see band table. EXEC p4 0b,2d,3,6. DECISION + USABILITY P1. Documented behaviour (lifecycle doc: "When no rule matches, the direct set is unchanged") but no coverage check on rule screen.
F7 Rule form validation/authority — min>max, blank name, negative level, no approver accepted (crm.php:3265); anyone with approve right manages rules (3250); no audit (READ). EXEC. DEFECT P2.
F8 Unavailable approvers — step to inactive named user (still "pending with" them), role nobody holds, or COORDINATOR (module gate blocks) is stuck forever; no SLA/escalation for quotes. EXEC p5 E. DEFECT P2.
F9 Delegation not applied — QUOTE not in delegation entity list (recruit_approval.php:21); "everything" delegation ignored by crm_can_act_approval; approvals_setup.php:96 claims delegation applies to everything. EXEC p5 I. DEFECT P2.
F10 Quotes not in /approvals — approvals_hub.php:40-63 has no quote section; hub empty while 2 quotes awaited MM. Contradicts 01-SALES §3.9 and S-1 step 5. EXEC. DOC + USABILITY P2.
F11 Pre-order checklist — gates only chain-step approve (crm.php:2138), not direct approve/send/accept; no who/when; author ticks all; ticks carried to revisions (crm.php:2033 skip list); ticks lost silently if item wording edited (md5 key :2957); empty list passes; approver can switch it off (ops.php:787); no waiver. EXEC p6. PARTIALLY SUPPORTED P1.
F12 Approving after the quote moved on — approving a step on a SENT quote set it APPROVED with sent_at kept → expiry sweep (crm.php:1109) never sees it; stale step on DRAFT made it APPROVED; quote-approve (crm.php:2130) never checks status. EXEC p5 A,B. DEFECT P2.
F13 Line↔site pairing — line stores another quote's site id or non-existent id (crm.php:671-676); posting another quote's site id deletes this quote's own site and drops the edit (crm.php:1212); new quote cannot pair lines to sites before first save (READ). EXEC p1 P2. DEFECT + USABILITY P2.
F14 No costing floor/margin check — quote cut to ₹10k/month vs ₹59,950 loaded cost approved; quote's costing panel shows stale 23.8%; quotes without costing show no cost; no rate cards (only PO type "ARC", helpers.php:263). EXEC p3 F. BUSINESS GAP P1.
F15 Costing approval weak — author approved own DRAFT costing without submit; −68.7% costing approved without warning; reopen keeps approved_by; quote generated from DRAFT costing (projcosting.php:494-507, 550). EXEC p3 C,E. DEFECT P2.
F16 Costing input validation — negative cost heads, negative overhead/negotiation %, negative one-off (raises margin to 47%) accepted; negative BOQ, negative manual rate, target ≥100 or <0 silently ignored; RATE mode denom ≤0 falls back to rate=direct → loss. EXEC p3 D. DEFECT P2.
F17 No settings screen for costing defaults (costing_*_pct read, never written). READ. P3.
F18 Costing→quote loses fields — GST hard-coded 18%; owner, SBU, inclusions/exclusions not carried (projcosting.php:309-332) → SBU-scoped approval rules never match. EXEC. DEFECT P2.
F19 Currency — free text, no validation; bands ignore currency; words always Rupees. EXEC. DEFECT + DECISION P2.
F20 Tax — one GST% per quote, no IGST/CGST; external quotes stored GST 0. READ/EXEC. ENHANCEMENT P3.
F21 Discount — negative line only, invisible to approver; before send a silent edit (F2); after send a revision gets fresh approval (works). EXEC. ENHANCEMENT (+defect via F2) P2.
F22 Degenerate quotes — ₹0 quote, no lines, unknown unit codes accepted; negative validity never expires. EXEC. P3.
F23 /quote-external — status unvalidated from form: MARKETING_EXEC registered ACCEPTED, APPROVED, or NOT_A_STATUS with no approval; typed total 100 vs lines 10,00,000 not reconciled; typed line amount overrides qty×rate (crm.php:1520, 1243). EXEC p7. DEFECT P1.
F24 Rounding — qty not rounded before multiply; on MySQL DECIMAL(12,2) stored qty may not match amount. EXEC SQLite / UNTESTED MySQL. P3.
F25 Rejected chain erased on resubmit — deletes chain, clears remarks (crm.php:2973, 1849); rejection survives only as a log text line. EXEC. P3.
F26 Only first level notified (crm.php:1850-1856 vs 2168-2172). READ. P3.
F27 Reimbursables unstructured — no included/excluded/capped field (marketplace has rate_inclusive). READ. DECISION P3.

## Retain
GAP-047 validator; chain guard on direct approve + logged master bypass; inclusive bands consistent in both functions; same-level both required; reject needs comment; resubmit rebuilds chain; un-approve only your own, later levels reset; rule mgmt limited to approvers; role-routed step signable without blanket perm (by design); lock on SENT; revision = new DRAFT carrying lines+sites; checklist blocks chain approve; costing arithmetic; expiry sweep + accepted-after-expiry log; blocked-client refusal (READ).

## Owner policy questions
1 approve before send? accept only after send/approve? 2 band value pre/post tax, pre/post discount? 3 unmatched value: fail-safe/refuse/any approver? 4 bands cumulative or exclusive? 5 levels in order? 6 preparer approve own? org self-approval switch cover quotes? 7 which edits void approval? 8 who records won/lost, who may move back to DRAFT? 9 margin floor — who sets, warn/block, override? 10 costing mandatory? approved first? author approve own? 11 checklist gate direct approve/send? who ticks? waiver? who edits? carry to revisions? 12 delegation + escalation for quotes? 13 discount field, visible to approver, per-role limits? 14 multi-currency: which, band conversion, wording? 15 GST type on quote? 16 reimbursables included/excluded/actuals/capped? 17 client rate contracts? 18 external portal quote statuses and must it be approved?
