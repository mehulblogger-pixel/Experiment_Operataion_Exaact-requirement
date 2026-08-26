# Module 45 — AI / Intelligence · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: AI touches an accredited report, but the trail can't prove what left the tenant

The AI seam (`lib/ai.php`, multi-provider) is well built: opt-in per tenant, graceful when off,
errors sanitised, config audited with the key redacted (Module 14), and report-review output is
**exemplary** on advisory marking ("advisory only… you remain the approving authority", never written
to the report). But the **governance record is incomplete**:

- **Gap B — no record of *what* was sent.** `AI_REVIEW` / `SCOPE_FROM_QAP` / `ITEMS_FROM_QAP` logged
  only *that* a call happened — not **which external provider/model** received the report body, client
  and vendor identities, and attached source documents. On an ISO-17020 report you could prove AI
  touched it, but not what confidential data left the tenant, or to whom.
- **Gap D — text-polish logged nothing.** `document-polish-text` (`idems_polish_text`) sends a report
  field's text to the external provider and recorded **no** audit entry — an AI touch on accredited
  report content with zero provenance.

**Noted, deferred (flagged, not built — heavier / behaviour changes; several are genuine §4.2/DPDP
work to decide deliberately):**
- **A pre-send confidentiality notice / §4.2 gate** before shipping a whole report or QAP to an
  external LLM (tie into Module 27), and a **redaction** pass. Touches the invoke UX/flow.
- A **per-feature / confidentiality-driven off-switch** (allow polish, forbid whole-QAP upload).
- **Per-row provenance markers** on the auto-written scope/items rows (they currently look identical
  to inspector-entered rows once saved).
- **API key encryption at rest** (plaintext in `ai_config`, though UI-masked and audit-redacted).
- The `tosrm_ops_summary()` **mis-consumed return value** (dead AI path) and per-feature model pinning.

---

## 1. Built (additive; completes the audit provenance; changes no AI behaviour)

1. **`idems_ai_provenance($chars=null, $files=0)`** — a consistent note naming **which provider/model**
   received data and **how much** (e.g. `sent to openai/gpt-4o · 320 chars`). It never contains the
   content itself (that stays confidential).
2. **Text-polish now logs `AI_POLISH`** (`idems_polish_text` gained a `$docId`; the fill form passes
   the report id; the AJAX handler forwards it) — the previously-unlogged AI touch is now on the
   sealed chain with provenance, against the report it touched.
3. **`AI_REVIEW`, `SCOPE_FROM_QAP`, `ITEMS_FROM_QAP` logs enriched** with the provenance note (which
   provider got the data). The QAP heuristic (no-AI) path is *not* tagged as AI.
4. **`AI_POLISH` / `SCOPE_FROM_QAP` / `ITEMS_FROM_QAP` registered** as first-class audit actions:
   added to `AUDIT_ACTIONS_ALL`, labelled in `AUDIT_ACTION_LABELS`, and marked **high-risk** in
   `AUDIT_HIGH_RISK` alongside `AI_REVIEW` — so every AI touch shows up on the audit-log high-risk
   filter and the notification/governance surfaces.

---

## 2. Edge cases handled

1. The provenance note names the destination even with no size given; with a size it records chars
   and file count — and never the report text.
2. Heuristic (non-AI) QAP scope/items extraction is not falsely tagged as an AI send.
3. `AI_POLISH` degrades safely — logged only on a successful polish; guarded by `function_exists`.
4. Adding the three actions to `AUDIT_ACTIONS_ALL` keeps them through the `audit_high_risk()`
   intersect even when a company sets a custom high-risk list.
5. Read-through of the advisory system prompt and the "you remain the approving authority" markers is
   preserved — nothing about human authority or advisory framing changed.

## 3. Guardrails (green)

`ai.php`, `idems_ai_review`, the advisory markers, the never-written report-review output, the
setting-secret redaction (Module 14), and the graceful-when-off behaviour — all unchanged. No AI is
newly invoked; no data flow changed; no new permission; no schema change; nothing deleted.

## 4. Tests

`tests/test_module45_ai_governance.php` (17 assertions): the provenance note names the destination and
size and excludes content; `AI_POLISH` is high-risk, master-listed and labelled; the QAP AI actions
are high-risk; an `AI_POLISH` entry lands on the sealed chain carrying provenance but not the report
text; the polish path and fill form are wired; and the advisory system prompt is preserved. Suite 3133
passing (only the 3 pre-existing baseline failures remain).
