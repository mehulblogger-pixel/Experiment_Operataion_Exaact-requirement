# UX-CONFUSION-AUDIT

**Deliverable for Parts 38–39.** Evidence and method in `UX-A8-CONFUSION.md`.

The brief's rule governs every row: improve **labels, helper text, hierarchy and
contextual relationships WITHOUT changing the underlying model**. Nothing below
proposes a model change.

---

## The pairs Part 38 names

"Definition exists" means a written one-line explanation already ships in
`TERM_DEFAULTS` — today reachable only on the admin rename screen.

| # | Pair | Definition exists? | Any screen distinguishes them? | Verdict | Proposed treatment |
|---|---|:--:|:--:|---|---|
| 1 | **Hiring Request vs Requisition** | ✗ / ✓ | **0 screens** | **Confusing** | Write the missing definition; state the relationship on the requirement ("Raised from hiring request HR-00231 →") |
| 2 | **Candidate vs Professional** | ✓ / ✗ | 0 | **Confusing** | Professional is a Marketplace idea, not staff — say so where the two meet |
| 3 | **Professional vs Workforce** | ✗ / ✗ | 0 | **Confusing** | Both definitions missing; write both |
| 4 | **Workforce vs Inspector** | ✗ / ✗ | 0 | **Confusing** | An inspector is a workforce member whose team is Field *and* whose company does site work — this rule is in the code, not on screen |
| 5 | **Client vs Organisation** | ✓ / n/a | — | **Not confusing** | "The party that engages us and gets what we produce" already settles it |
| 6 | **Marketplace Requirement vs Recruitment Requisition** | ✗ / ✓ | 1 mentions, none distinguishes | **Confusing** | The owner has ruled these must never merge; the UI should say why they differ |
| 7 | **Approval vs Acceptance** | ✗ / ✗ | 0 | **Confusing** | Approval authorises recruiting; acceptance hires a person. Different objects, different actors |
| 8 | **Accepted vs Joined** | ✓ / ✗ | **1 — solved** | **Already handled** | The candidate screen asks "Have they actually joined?" with a separate dated action. **Copy this pattern**, do not replace it |
| 9 | **Inspection vs Job** | ✓ / ✓ | via definitions | **Low risk** | "One person put on one work order, for particular dates" — already written, just unreachable |
| 10 | **Report vs QA** | ✓ / ✗ | 0 | **Confusing** | QA has no definition; it is a stage of a report's life, not a separate object |
| 11 | **Invoice vs Billing readiness** | ✓ / ✗ | 0 | **Confusing** | Readiness is a *check*, not a document. The distinction protects the owner from issuing early |

**Seven of eleven pairs are genuinely confusing. Five of those seven are
confusing only because one side has no written definition** — five sentences,
checked against `docs/03-object-lifecycles.md`, close most of this.

---

## Part 39 — per role

| Role | Sees another role's vocabulary? | Residual confusion within their own work |
|---|---|---|
| Admin / Master | Yes — by design, sees everything | Pairs 1, 3, 4, 7 |
| Business Development | No — Sales gating | Pair 6 (marketplace vs recruitment) |
| Recruiter / HR | No | Pairs 1, 7, 8 — the core recruitment ambiguities |
| Coordinator | No | Pairs 4, 9 |
| Operations Manager | No | Pairs 4, 9, 10 |
| Inspector | No — phone-first, own cockpit | None material |
| QA | No | Pair 10 |
| Finance | No — Money gating | Pair 11 |
| Management | Yes — cross-module by design | Pairs 1, 6 |
| Company portal user | No — portal scope | None material |
| Agency portal user | No — portal scope | None material |
| Professional (Marketplace) | No | Pairs 2, 3 |

**The cross-role problem the brief anticipates does not exist**, and not because
of labelling. Licence gating removes whole areas a workspace has not bought, and
permission gating shapes the dashboard (21 conditionals) and search (a gate per
source). A recruiter never meets QA vocabulary; an inspector never meets billing
readiness.

What remains is **within-role** ambiguity — and it concentrates almost entirely
on the recruiter, who must hold pairs 1, 7 and 8 apart every day.

---

## Recommendation

1. **Write five definitions** — hiring request, workforce, inspector, QA,
   billing readiness — against the lifecycle document. Content, not code.
2. **Surface all 30** where the word is used, not only on the rename screen.
3. **Show the relationship, don't explain it.** Pair 8 is already solved this
   way and is the model: make the distinction visible in the interface.

No model change. No new status. No permission change.
