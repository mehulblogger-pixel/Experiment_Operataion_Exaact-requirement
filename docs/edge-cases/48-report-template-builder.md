# Module 48 — Report Template Builder (URFE) · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: the .docx template is validated; the form schema is not

The report-format subsystem has two definition layers: the **form schema** (`report_types` →
`report_sections` → `report_fields`, the no-code builder — defines what a report *collects*) and the
**.docx template** (`report_templates` — defines how it *prints*). The .docx side has a full
draft→review→approve lifecycle **and** `idems_template_validate()` (orphan tokens, missing required
tokens, valid .docx). The **form schema has neither** — saving a field *is* publishing it, and
**nothing validates the field rows**: a duplicate `fkey` (index-less, ungated in the hand-edit path)
silently collides on storage and on `{{token}}` rendering; an option-less choice, an empty table, or
a `cond_field` pointing nowhere can all go live and break report *entry*. There was no test coverage
of format validity at all.

---

## 1. Built (additive, read-only, no hard block)

1. `idems_format_validate($typeId)` — the missing twin of `idems_template_validate()`, same
   `{level, issues:[{level,msg}]}` shape, computed from `idems_fields()`:
   - **duplicate field key** → ERROR (the ungated hole);
   - `select`/`radio`/`multiselect` with no options → WARNING;
   - `table` field with no columns → WARNING;
   - `heading`/`note` marked required → WARNING;
   - `cond_field` referencing a non-existent field → WARNING;
   - a format with no fields → WARNING.
2. A **"Format integrity"** panel on the form builder (issues listed, explicitly "nothing is
   blocked").
3. A per-type **integrity pill** on the report-types list (⛔ N issues / ⚠ check), linking to the
   builder.
4. The **first tests** over format validity (there were none — for `idems_format_validate` or
   `idems_template_validate`).

---

## 2. Edge cases handled

1. A clean form → PASS (no panel, no pill).
2. Duplicate fkey → ERROR (the highest-risk case: silent data/token collision).
3. Option-less choice / empty table / dangling condition → WARNING, never a block.
4. A `lookup:`/`call:`/`equip:` source in `options` is a non-empty string → not falsely flagged as
   "no options".
5. Only types with a designed form are validated on the list (empty types already show "No form
   yet").
6. Advisory throughout — it changes no behaviour; making duplicate-fkey a hard stop (or adding a
   `(report_type_id, fkey)` unique index) is a follow-up that would need a go.

## 3. Guardrails (green)

`idems_template_validate`, the template lifecycle/approval, the freeze-at-issue, `idems_fields`/
`idems_sections`, and the builder itself — all unchanged. No new permission (reuses the existing
`idems.type.manage` view gate); no schema change; no hard block; nothing deleted.

## 4. Deferred (noted)

Making duplicate-fkey a hard stop / adding a `(report_type_id, fkey)` unique index (a hard control);
an approval lifecycle for the form schema to match the .docx template; the autoform/plain-upload
path that bypasses the template approval gate. Each is a deliberate governance decision.

## 5. Tests

`tests/test_module48_format.php` (15 assertions): a clean form passes; a duplicate fkey is an ERROR
naming the key; option-less choice / empty table / dangling condition / no-fields are WARNINGs; the
shape matches the template validator; the builder panel and list pill are wired; the template
validator is unchanged.
