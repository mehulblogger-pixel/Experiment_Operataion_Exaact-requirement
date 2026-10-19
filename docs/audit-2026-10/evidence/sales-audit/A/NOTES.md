# Sales-A — lifecycle (subagent report, saved by the lead)

Harness plib.php / w.php (real routes via ops_dispatch in subprocess per role; RACE() fires identical requests at once). Probes p00–p11 *.php with *.out. Re-run: cd phpapp && TMPDIR=<folder>/tmp php <folder>/p05_quote_status.php. Default role rights.

## State machines (code; most exercised)
Lead (leads.php): lead_move() :587 moves only OPEN (stage, LOST w/ reason, WON→convert). lead_convert() :647 refuses only CONVERTED (:650) → a LOST lead can be converted; no lock. Conversion creates customer + ENQUIRY, NOT an opportunity. lead-edit :1229 no status check; lead-delete :1293 refuses only converted. Only MASTER_ADMIN and BUSINESS_DIRECTOR reach any lead route by default (module leads).
Opportunity (opportunities.php): opp_move() :385 OPEN only → WON/LOST terminal; LOST needs reason; stage gates can hold. Accepting a linked quote wins deal; marking ANY linked quote LOST loses it. WON creates nothing (no enquiry, no quote) (p02 §2). opp_update :446 edits closed deals. All opp routes gated by leads → MM, BDM, FIN, BM get 403.
Enquiry: OPEN/QUOTED/DROPPED but status taken from form unvalidated (crm.php:1337). Raising a quote sets QUOTED (:1680) — at creation, not approval. Many quotes per enquiry allowed. Delete refused once a quote exists. Nothing reacts to quote won/lost.
Quotation (quote-status crm.php:1815): route gate only mod.quotes.view. Lock quote_is_locked :1034 allows only ACCEPTED/LOST once sent. APPROVED needs approve right, SENT needs send right; NO from-state check. quote-external :1520 stores any posted status. quote-revise :2010 new row rev+1 DRAFT, only is_current=0 on old. crm_expire_quotes :1106 SENT→EXPIRED daily. No WITHDRAWN/CANCELLED/PARTIALLY ACCEPTED; no delete/void.
Project costing (projcosting.php:487-510): submit/approve/reject/reopen from ANY state; approve needs admin level or crm.quote.approve; no maker-checker. Link project_costings.quote_id; latest overwrites.
Quote→Contract (crm.php:2211): checks the right, NOT the quote status. Different number refused while a contract is live; same number reused; after REJECTED contract a new one allowed.

## Findings
P0 F-01 Approved quote re-priced and sent as approved: approved at ₹59,000, edited via normal Edit to ₹1,06,20,000, stayed APPROVED, then SENT; log "Edited" only. p06 §C EXEC defect.
P1 F-02 Edit while pending does not rebuild chain: raised to ₹5,90,000, approved by L1 only though L2 rule matched. p06 §B EXEC.
P1 F-03 DRAFT→SENT with no approval even when a rule requires one; UI hides, server allows. p06 §A, p05 §1 EXEC.
P1 F-04 L1 REJECTED overridden when L2 approves → quote APPROVED (:2163 counts only pending steps). p06 §D EXEC.
P1 F-05 View-only roles change status: FIN, BM, BD set ACCEPTED/LOST/EXPIRED/REJECTED; MM and MEX moved DRAFT→ACCEPTED directly. Matrix grants none. p05 §2,§4 EXEC.
P1 F-06 ACCEPTED↔LOST flips repeatedly, no history; quote with live contract can sit LOST; master can return never-sent ACCEPTED to DRAFT. p05 §5, p08 §4 EXEC.
P1 F-07 No history written for: direct APPROVED, SENT, ACCEPTED, LOST, manual REJECTED, EXPIRED. Who accepted is never recorded. Lead, deal, enquiry and costing edits write nothing. p10, p05 §8 EXEC. BUSINESS GAP.
P1 F-17 Superseded revision stays live: rev 0 could be ACCEPTED, contracted, or SENT after rev 1 existed; register filters is_current so accepted rev 0 vanishes from it. p07 §1,§5 EXEC.
P1 F-20 Contract registered on DRAFT, PENDING_APPROVAL and LOST quotes. p08 §1 EXEC.
P2 F-08 Creator approves own quote (direct, generic step, role step); org self-approval setting (Gate 4) applies only to recruitment. EXEC. DECISION.
P2 F-10 No version check on quote edit; second save from same copy silently wins; only calls/jobs protected (ops.php:616). EXEC.
P2 F-11 (S-5/T-11) Empty /quote-new creates a numbered DRAFT with no client, no lines, total 0; taken all the way to ACCEPTED. p04 §1 EXEC.
P2 F-12 /quote-new twice → two quotes; simultaneous: one saved, other raw PDOException page; no idempotency token. p04 §2 EXEC.
P2 F-13 /quote-external stored ACCEPTED, APPROVED, "BOGUS"; MEX registered ₹9L straight to ACCEPTED. p04 §3 EXEC.
P2 F-15 BLOCKED client refused a NEW quote, but an existing quote can still be sent, accepted, contracted. p11 §4 EXEC. DECISION.
P2 F-18 Revision of an accepted quote inherits its contract number; that revision can go LOST while contract stays live. p07 §2 EXEC.
P2 F-21 One won deal gets two contracts (via quote and via deal's direct-win path). p02 §5b EXEC.
P2 F-22 Finance cannot open opportunity-contract (leads gate) → direct-win handover to Accounts dead-ends. EXEC.
P2 F-25 Two simultaneous conversions of one lead → 2 customers + 2 enquiries, 3/3 attempts. Sequential re-convert correctly refused. p01 §3 EXEC.
P2 F-26 Converted-enquiry numbers COUNT+1; INQ-2610-003 issued twice; no unique index; register uses two number formats. p01b EXEC.
P2 F-27 LOST lead can be converted; CONVERTED/LOST leads editable; accepting a quote on a LOST lead leaves lead LOST. EXEC.
P2 F-29 MM, BDM, MEX, FIN, BM cannot open Leads or Opportunities; matrix has no rows for them. EXEC. DECISION + DOC.
P2 F-30 Marking one alternative quote LOST loses the whole deal; accepting the other cannot win it back. p02 §3,§4 EXEC.
P2 F-32 Won/lost deals editable (value set to 1), no history. EXEC.
P2 F-33 Enquiry status accepts anything (QUOTED with no quote; "BANANA"); DROPPED silently revived when quote raised; QUOTED editable back to OPEN while quotes exist. EXEC.
P2 F-36 Costing: DRAFT→APPROVED skipping submit; creator approved own; APPROVED reopened to DRAFT and edited still shows old approver; nothing logged. p09 §2 EXEC.
P2 F-37 Quotes raised from DRAFT and REJECTED costings, repeatedly; link overwritten; quote ignores costing status; costing editable after its quote accepted. p09 §3,§4 EXEC.
P2 F-40 Docs out of step: docs/03 no Lead/Opportunity/Enquiry/Costing lifecycle and Quotation diagram omits allowed transitions; docs/02 no rows for leads, deals, costing; 01-SALES §4 wrong twice (conversion does not create opportunity; WON does not create enquiry); S-1 step 6 wrong (enquiry QUOTED at quote creation, not approval).
P3 F-09 resubmit deletes rejected steps. F-14 line points at another quote's site. F-16 LOST with no reason (quote). F-19 any status can be revised; revising an older revision again crashes (unique index). F-23 reusing a contract number moves the contract's quote pointer. F-24 simultaneous contract registration → raw error page (no duplicate). F-28 deleting a lead leaves its quote pointing at nothing. F-31 a quote for a different client can win a deal. F-34 empty enquiry now refused server-side (corrects E-1) but form marks none of the 5 required fields. F-38 no withdraw/void status.

## Retain (EXEC)
Sent quote immutable even for master; revision carries all columns/lines/sites + snapshot; unique indexes (quote_no,rev) and contract_number hold under race; direct approve routed to chain when rule matches, master override logged; expiry run + accepted-after-expiry note; one live contract per quote; contract registration needs crm.contract.register; acceptance lands customer, wins deal, converts lead, idempotent; enquiry with quotes cannot be deleted; lead/deal terminal moves refused, LOST needs reason; BLOCKED client cannot get new quote; direct RFQ and several quotes per enquiry supported.

## Untested
Stage gates and preorder require_all (read only — but see sales-B F11 which executed it); MySQL race behaviour; Books push; browser rendering.
