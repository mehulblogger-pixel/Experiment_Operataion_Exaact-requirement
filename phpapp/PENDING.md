# Pending / parked items — SGS Ahmedabad Inspection Management System

Living list of things explicitly deferred, so nothing is forgotten. Newest on top.

## Additional features (user will provide details / build later)

- [ ] **Inspector expenses linked strictly to the job done** — an inspector's
      expenses must attach only to the job they performed (fuller rules to be
      provided by the user).
- [x] **CV / hiring pipeline (deputation resourcing)** — DONE. New "Hiring" tab.
      Add a candidate CV (name, trade→skill, client, against-call, proposed site,
      SBU, designation, source [asset/freelancer/sub-con], experience, rate, CV
      link, CV-received date). Move through **CV received → Submitted to client →
      Shortlisted → Interview → Hold / Reject / Accept(=Hired) / Withdrawn**, each
      transition logged with a remark + who/when (full history on the candidate).
      On **Accept** you can tick "add to Inspectors" and the person is created as
      an inspector (carrying trade/skill/SBU/designation and the freelancer/
      sub-con type) ready for deputation-job allocation. Stage filter chips +
      counts on the list. Tables: `candidates`, `candidate_events`.

## Parked (agreed to do later)

- [ ] **Full organisation structure** — model the hierarchy Business Director →
      SBU Heads → Branch Managers → Managers → Asst. Managers → Coordinators →
      Inspectors, with **multiple** Operation Managers and Coordinators **per
      office**, and users linked to their office. Today each office stores a
      single coordinator + manager email (used for forwarding/notifications), and
      there are 4 access roles. This is a role/permission + org redesign for a
      dedicated pass.
- [ ] **Multi-SBU cost distribution in dashboards** — inspectors can now be
      tagged to multiple SBUs; split their monthly loaded cost across those SBUs
      in the profitability/utilization reports (currently cost sits on the job's
      single SBU). The data (inspectors.sbus) is captured; the report split is
      pending.


- [ ] **Reminder cron jobs** — set up the two cPanel Cron entries (07:00 report-due,
      18:00 overdue-closure) pointing at `cron.php`. Deferred by user; steps are in
      `README-MilesWeb-PHP.md`.
- [ ] **Office 365 automatic email sending (SMTP)** — send assignment/closure/
      forward/reminder emails automatically from a real mailbox, no clicking.
      Needs a sending mailbox + 3 settings in `config.php`. (Today: emails are
      logged, and each Call/Job has an **Open in Outlook** button that pre-fills
      the mail so the user can attach the original client email and send.)
- [ ] **License server + per-user billing** — support **both** deployment models
      (client's own server **and** our hosted server) for different industries,
      with remote seat-limit enforcement, subscription expiry, module toggles, and
      a control panel we run from our side. Ties to Razorpay/Stripe for the
      one-time setup fee + monthly per-user recurring. (Roadmap artifact already
      shared.)

## Module A/B sub-items — DONE (this session)

- [x] PO/line-item selection on a call + qty tracking + near-completion alert.
- [x] Project deputation → client sites dropdown (shown only for deputation).
- [x] Executing-branch confirmation status on the call.
- [x] PO line items: manpower/site/trade→subcategory + GST/Tax/Total + rollup;
      activity per line respecting the PO's SBU; multi-SBU on the PO.
- [x] Projects tab lists the partner's calls.
- [x] City light auto-correct; Type-of-inspection 'Other' free text on the call.

## Modules C / D / E — not yet started

- [ ] C: Logo upload + editable theme (kept legible); per-SBU expense headings.
- [ ] D: inspection lifecycle/status flow; designations master (Inspector,
      Sr. Inspector, Sr. Executive…); back-office staff with costing (CTC,
      allowances).
- [ ] E: add your own dropdown/text box to any master form (custom fields on
      masters).

## Dashboards — refinements to follow

- [ ] **Per-SBU configurable expense headings** — expenses are currently entered
      under 5 fixed headings (travel/local/food/lodging/misc) and the financial
      dashboard breaks them down accordingly. An `expense_heading` master now
      exists; the remaining work is dynamic heading entry configurable per SBU.
- [ ] **Persona landing pages** — today all four dashboard families live on one
      /reports page with each section gated by permission (so each person sees
      only their allowed sections). A future refinement gives each role a
      tailored default landing layout (Director = office comparison, SBU Head =
      SBU-across-offices, etc.).

## Nice-to-have / minor

- [ ] Generic master "checkbox" fields default to ticked on new records (fine for
      "Active" on sub-cons, not ideal for "is Ahmedabad" on a new office). Low impact.

## Done recently (for reference)

- [x] Configurable master lists + dependent (hierarchical) lists + custom fields.
- [x] New Call: quick-add client/vendor/office/product/activity, executing-branch
      forwarding with mandatory credit + coordinator/manager email, lead times.
- [x] Readable error page instead of blank 500.
- [x] Full operations system (Calls, Jobs, Closure, Expenses, SubCon, Attendance,
      Holidays, Comp-off, Credit, Dashboards) with 4 roles + salary security.
