# Pending / parked items — SGS Ahmedabad Inspection Management System

Living list of things explicitly deferred, so nothing is forgotten. Newest on top.

## Parked (agreed to do later)

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
