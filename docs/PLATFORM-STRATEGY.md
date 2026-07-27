# Platform strategy — modules, the wider audit market, and the trust problem

Written July 2026, in answer to three questions: can the modules be sold
separately, what do other kinds of audit firm need, and how do we automate
reporting / track inspectors / close the trust gap between client and inspector.

Feedback was asked for **brutally**, so it is brutal. Nothing here is a guess:
the coupling numbers are measured from this repository, and the market and
standards claims carry sources.

---

## 1. Can every module be separated and sold on its own?

### The measurement, before the opinion

Cross-module function calls, counted across `lib/*.php` (comments stripped):

| module | calls out | called by others | biggest dependencies |
|---|---:|---:|---|
| **ops** | 706 | **712** | helpers 197, db 172, access 126, terms 54 |
| crm (Sales) | 577 | 24 | **ops 174**, helpers 140, access 81 |
| idems (Reporting) | 560 | 55 | **ops 213**, helpers 215, db 52 |
| orgadmin | 160 | 18 | terms 38, helpers 38, ops 36 |
| costing (Money) | 113 | 24 | ops 46, db 25 |
| workforce (HR) | 88 | 14 | **ops 47**, helpers 19 |
| schedule | 53 | 5 | db 26, ops 20 |
| helpers | 10 | **830** | — |
| db | 21 | **484** | — |
| access | 19 | **384** | db 14 |
| terms | 26 | **188** | access 16 |

### The uncomfortable conclusion

**There is no "Operations module" to separate. `ops.php` is the application.**

4,671 lines, called 712 times from everywhere else, and it contains — all in one
file — the router (`dispatch`, `module_gate`), the schema (`ensure_schema`,
`migrate`), authentication and roles (`is_master`, `can_see_salary`), the SMTP
client, the money engine (`job_money`, `job_profit`, invoicing), HR (inspectors,
candidates, requisitions, vouchers), the masters admin, the chart renderer
(`svg_donut`, `svg_gauge`) and the money/date formatters.

So of the six modules asked for:

- **Sales** (`crm.php`) — genuinely separable. 577 calls out, only 24 back in.
  Almost one-directional.
- **Reporting** (`idems.php`) — separable with work. 560 out, 55 back.
- **Money, HR, Operations, Admin** — these are **not four modules**. They are
  four *concerns tangled inside one file*, plus satellites (`costing`,
  `workforce`, `bills`, `contracts`, `schedule`) that each call back into it.

**`helpers` / `db` / `access` / `terms` / `lookups` are not modules and must
never be split.** 830, 484, 384, 188 and 143 inbound calls. That is the
*platform kernel* — every module needs it, and that is correct design, not debt.

### The brutal part

Splitting this into six saleable products is **6–10 weeks of refactoring that no
customer will ever see**. It produces zero new features, risks every one of the
regressions this codebase has already been burned by, and answers a question the
market has not asked.

And the market probably will not buy it. Nobody purchases "the HR module" from
an inspection-software vendor — they already run Zoho, Keka or greytHR, and they
will not run payroll on a TPI product. Selling HR separately invites a
comparison you lose.

### What to do instead

**One product, licensed by module.** Not six products — one product where each
module is switched on by a licence flag. The customer sees "Sales: off", the
menu hides it, the pricing page lists it. You already have most of this:
`ops_module_gate()` maps every route to a module, and `can('mod.quotes.view')`
already gates the menu. **Adding a per-installation module switch on top of that
is days, not weeks, and delivers the commercial outcome without the refactor.**

Do the real separation only when a paying customer demands one module alone.
Until then it is architecture astronomy.

The one split worth doing on merit — regardless of packaging — is carving
**Money** (`job_money`, `job_profit`, `job_revenue_for`, invoicing) and
**Notification** (SMTP, all `send_*_email`) out of `ops.php`. Both are pure
logic, both are tested, both are called from everywhere, and both being buried
in a 4,671-line file is why changing one thing keeps breaking another.

---

## 2. The wider market — what other audit and inspection firms need

### The single most important fact in this document

**ISO/IEC 17020:2026 was published on 27 March 2026 and replaces the 2012
edition. Every accredited inspection body in the world must transition by
27 March 2029.**

And the headline change is *about software*:

> **Control of data and information is now a standalone requirement with no
> equivalent in the 2012 edition** — documented procedures covering software
> validation, data integrity assurance, protection against unauthorised access,
> and system failure logging. Technology systems, AI tools and remote inspection
> platforms now require formal validation records and change management.

Read that again. The standard has handed you a feature list, a deadline, and a
captive audience of every accredited inspection body on earth. Other changes:

- **Type A / B / C becomes Type A / Type non-A.** The independence model in the
  app should follow.
- **Risk-based thinking** made explicit across several clauses — threats to
  impartiality from organisational relationships, outsourcing and *financial
  pressure* must be identified and documented.

This is the wedge. "17020:2026 transition-ready" is a reason to buy **this
year**, not eventually. Nothing else in the roadmap has a deadline attached to
somebody else's accreditation certificate.

### The family of standards, and which are worth chasing

| Standard | Who | Worth building for? |
|---|---|---|
| **ISO/IEC 17020** | Inspection bodies — TPI, NDT, lifting, pre-shipment | **Yes — this is you today** |
| **ISO/IEC 17021-1** | Management-system certification (ISO 9001/14001/45001) | **Yes — the biggest adjacent market** |
| ISO/IEC 17065 | Product certification (CE, BIS, certificates of conformity) | Later — close cousin of 17020 |
| ISO/IEC 17025 | Testing & calibration labs | **No.** That is a LIMS. Different product, entrenched competitors |
| ISO/IEC 17024 | Personnel certification (welder, NDT operator) | Niche, small |
| ISO 14065 / 14064 | GHG validation & verification | **Watch closely** — CBAM and ESG reporting are growing this fast |
| SMETA/Sedex, SA8000, BSCI | Social compliance & ethical audits | Adjacent; very different report shape |
| FSSC 22000, BRCGS | Food safety audits | Adjacent; heavy checklist |
| Safety / fire / electrical / energy audits (BEE) | Domestic Indian market | Cheap to add — they are checklist inspections |

**The hard truth about 17021.** A certification body is *not* an inspection body
with different words. An inspection call is one visit. A certification audit is a
**three-year cycle**: Stage 1 → Stage 2 → Surveillance 1 → Surveillance 2 →
Recertification, with audit **man-days computed from a formula** based on
effective employee count and risk category (IAF MD 5), **multi-site sampling**
(IAF MD 1), an **auditor competence matrix per scope/NACE code**, and a
**certificate registry** with suspend/withdraw states.

Your scheduling engine models an inspection call. It does **not** model a
three-year certification cycle. That is the single biggest build if you want
certification bodies — bigger than everything else on this page combined.
Estimate it honestly before promising it.

### On "generic and configurable enough for everyone"

Be careful. **Configurability is not free.** Every option doubles the test
matrix, and this codebase has already been bitten repeatedly by one change
breaking a thing that read from it. Push it far enough and the product becomes a
form builder with no opinion — and in vertical SaaS, opinionated products win.
The buyer wants "this already knows how a TPI firm works", not "you can
configure anything".

You already have the right bones: the terminology engine, the lookup engine, the
custom-field engine, per-office settings. That is the correct amount of
configurable. Resist making the *workflow* configurable — configure the nouns,
not the verbs.

---

## 3. Reporting automation, GPS, and the trust gap

### The trust gap is real and documented

Not theoretical. Recorded cases include inspectors **copy-pasting old inspection
reports** to create new ones, a senior safety inspector **fabricating fire
suppression and flood barrier inspections for years**, and an 11-month
investigation into NYC track inspectors finding *"widespread deception"* with
multiple inspectors **skipping field inspections and filing false reports**.

This is the problem worth solving. It is also the one thing on this page that
could genuinely differentiate the product.

### Brutal: continuous GPS tracking of inspectors is the wrong answer

You asked for it explicitly. I think it is a mistake, for three reasons.

**1. It is legally exposed in India.** Under the DPDP Act consent must be free,
informed, specific and withdrawable — and an employee cannot *freely* refuse.
A blanket monitoring clause in the employment contract **is not sufficient**.
Tracking outside working hours is specifically scrutinised, and using location
data to build performance profiles or make disciplinary decisions moves squarely
into territory the Act polices. Penalties reach **₹250 crore**. You already have
a DPDP compliance module in this app; continuous tracking would contradict it.

**2. It does not work.** The standard fraud tool is a **mock-location app** that
feeds the phone a fake GPS position. Continuous tracking of a cooperative phone
proves nothing about an uncooperative one.

**3. It solves the wrong problem.** The client does not care where the
inspector's phone was at 11:40. The client cares whether the inspection actually
happened and whether the report reflects it. Tracking the person answers a
question nobody is paying for — while poisoning your relationship with the
scarcest resource in this industry, which is competent inspectors.

### What actually closes the trust gap

Bind the **evidence** to place and time — not the **person** to a map.

1. **Geotag at the moment of capture.** Location is a property of *the
   photograph*, not of the employee. Proportionate, defensible under DPDP,
   and it is what the client is actually buying. You already capture GPS in
   `views/ops/idems/fill.php` — this is a small step from where you are.
2. **Detect mock-location and flag it.** Do not block — flag. A flagged photo
   that a supervisor reviews is stronger evidence than a blocked one.
3. **Never trust the device clock.** Stamp server-side. A phone's clock is
   attacker-controlled.
4. **Hash each piece of evidence at capture and chain the hashes**, so an
   altered photo is detectable afterwards. This is what ISO/IEC 17020:2026's new
   data-integrity clause is reaching for. You do not need a blockchain for this
   — a hash chain plus an append-only log gets you there, and is far easier to
   explain to an assessor.
5. **The killer feature — client-verifiable reports.** The most useful line I
   found in the research:

   > *"The inspection body that offers 'verify it yourself' wins contracts; the
   > one that offers 'trust us' cannot."*

   A QR code or link on every issued report that lets the **client** confirm,
   without logging in and without asking you, that the report is genuine,
   unaltered, issued by an authorised inspector, and backed by evidence carrying
   a geotag and a server timestamp.

That is the product. Not surveillance of your own staff — **independently
verifiable evidence handed to the customer.** It closes the trust gap from the
right end, it is what 17020:2026 is pushing everyone toward, and it is a feature
a client will pay a premium for because it reduces *their* risk.

### Reporting automation

Administrative work is estimated at **30–40% of total audit time** in
traditional certification-body operations. That is the number to attack, and it
is the same number that justifies the licence fee. You already have the report
builder, client formats, the phrase library and the release-note generator. The
gap is not more automation — it is that the engineer **cannot reach the report
engine from the deputation** unless deliverables happened to be ticked (see the
gap review, item B2). Fix the plumbing before adding more cleverness.

---

## 4. What I would actually do, in order

1. **Do not modularise yet.** Add a per-installation module licence flag on top
   of the existing `ops_module_gate()`. Days, not weeks. Same commercial result.
2. **Close the flow breaks** from the gap review (B1, B2, B3). The field user's
   day is broken today; that costs you a customer faster than a missing module.
3. **Build the 17020:2026 transition pack** — impartiality declarations, the
   complaints & appeals register, the competence/authorisation matrix with
   enforcement, the equipment & calibration register, and the new data-integrity
   clause (validation record, access control, failure log). Every one of these
   is already on the gap list; the 2029 deadline turns them from "should" into
   "sellable".
4. **Then the trust layer** — geotag at capture, mock-location flag, server
   timestamps, hash chain, and the client-verifiable QR report.
5. **Only then** consider 17021 certification bodies, with the three-year audit
   cycle scoped and estimated honestly as its own project.
6. **Carve Money and Notification out of `ops.php`** whenever there is a quiet
   week. Not for packaging — because that file is where the regressions come
   from.

---

## Sources

- [Key Differences Between ISO/IEC 17020:2012 and ISO/IEC 17020:2026 — A2LA](https://a2la.org/key-differences-between-iso-iec-170202012-and-iso-iec-170202026/)
- [2026 Revision of ISO/IEC 17020 — ANSI/ANAB blog](https://blog.ansi.org/anab/iso-iec-17020-2026-revision-changes-inspection/)
- [Transition arrangements for ISO/IEC 17020:2026 — UKAS](https://www.ukas.com/resources/technical-bulletins/iso-iec-17020-2026-transition/)
- [Publication of ISO/IEC 17020:2026 — Singapore Accreditation Council](https://www.sac-accreditation.gov.sg/publication-of-iso-iec-17020-2026-requirements-for-bodies-performing-inspections/)
- [Why Inspection Bodies Should Think Differently About Digital Evidence Under ISO/IEC 17020:2026 — Quality Magazine](https://www.qualitymag.com/articles/99714-why-inspection-bodies-should-think-differently-about-digital-evidence-under-iso-iec-17020-2026)
- [ISO/IEC 17025 vs ISO/IEC 17020 — A2LA](https://a2la.org/iso-iec-17025-vs-iso-iec-17020/)
- [ISO/IEC 17021-1:2015 — ISO](https://www.iso.org/standard/61651.html)
- [Trust but verify: combating inspector fraud — Fulcrum](https://www.fulcrumapp.com/blog/trust-but-verify-combating-inspector-fraud/)
- [GPS-Fake Detection: 7 Tactics to Catch Ghost Visits](https://sortstring.com/blogs/gps-fake-detection-ghost-visits)
- [Fraud-Proof Virtual Inspections — Truepic Vision](https://www.truepic.com/vision/fraud-prevention-detection)
- [DPDP Act compliance: GPS tracking, telematics and workforce data risks — Legal500 / K&K](https://www.legal500.com/developments/thought-leadership/dpdp-act-compliance-for-logistics-and-supply-chain-companies-in-india-gps-tracking-telematics-and-workforce-data-risks/)
- [Employee Monitoring Laws India 2026 — DPDP Act guide](https://www.employee-monitoring.net/blog/employee-monitoring-laws-india)
- [The 4 best audit management software tools for certification bodies in 2026 — AuditOne](https://www.auditone.io/blog-posts/the-4-best-audit-management-software-tools-for-certification-bodies-in-2026)
